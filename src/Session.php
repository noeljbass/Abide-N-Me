<?php

declare(strict_types=1);

namespace FeedMySheep;

final class Session
{
    private const LIFETIME = 31536000;

    /**
     * Sessions live in application storage rather than the server default.
     *
     * Shared hosting garbage-collects the default session directory on its own
     * schedule - often every 24 minutes - so a browser could hold a cookie that
     * was valid for a year while the session behind it had already been deleted.
     * That is what made sign-in look like it did not persist.
     */
    private static function storagePath(): ?string
    {
        $path = dirname(__DIR__) . '/storage/sessions';
        if (!is_dir($path) && !@mkdir($path, 0700, true) && !is_dir($path)) {
            return null;
        }
        return is_writable($path) ? $path : null;
    }

    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';

        $storage = self::storagePath();
        if ($storage !== null) {
            ini_set('session.save_path', $storage);
            ini_set('session.gc_probability', '1');
            ini_set('session.gc_divisor', '500');
        }

        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_samesite', 'Lax');
        ini_set('session.cookie_secure', $secure ? '1' : '0');
        ini_set('session.gc_maxlifetime', (string) self::LIFETIME);
        session_set_cookie_params([
            'lifetime' => self::LIFETIME,
            'path' => '/',
            'domain' => '',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_name('FMS_SESSION');
        session_start();

        // Keep authenticated sessions alive for a full year from their most recent use.
        // SameSite has to be repeated here: a cookie re-sent without it is treated as a
        // new, differently scoped cookie by some mobile browsers.
        if (isset($_SESSION['user_id'])) {
            setcookie(session_name(), session_id(), [
                'expires' => time() + self::LIFETIME,
                'path' => '/',
                'domain' => '',
                'secure' => $secure,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }
    }

    /** Diagnostic snapshot used by the health endpoint. */
    public static function storageStatus(): array
    {
        $path = self::storagePath();
        return [
            'store' => $path === null ? 'php-default' : 'application-storage',
            'writable' => $path !== null,
            'lifetime_days' => (int) round(self::LIFETIME / 86400),
        ];
    }

    public static function csrfToken(): string
    {
        self::start();
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    public static function requireCsrf(): void
    {
        self::start();
        $provided = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        $expected = $_SESSION['csrf_token'] ?? '';
        if (!is_string($provided) || $expected === '' || !hash_equals($expected, $provided)) {
            JsonResponse::error('invalid_csrf', 'Your session could not be verified. Refresh and try again.', 403);
        }
    }

    public static function login(int $userId): void
    {
        self::start();
        session_regenerate_id(true);
        $_SESSION['user_id'] = $userId;
        $_SESSION['authenticated_at'] = time();
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
        setcookie(session_name(), session_id(), [
            'expires' => time() + self::LIFETIME,
            'path' => '/',
            'domain' => '',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    public static function userId(): ?int
    {
        self::start();
        $id = $_SESSION['user_id'] ?? null;
        return is_int($id) || ctype_digit((string) $id) ? (int) $id : null;
    }

    public static function logout(): void
    {
        self::start();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $parameters = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $parameters['path'], $parameters['domain'], $parameters['secure'], $parameters['httponly']);
        }
        session_destroy();
    }
}
