<?php

declare(strict_types=1);

namespace FeedMySheep;

final class Session
{
    private const LIFETIME = 31536000;

    /** Value the packaged applications send in their X-App-Client header. */
    private const APP_CLIENT = 'abiden-native';

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

    /**
     * The SameSite value this response's cookie should carry.
     *
     * A cookie sent to the native applications is, by the browser's reckoning,
     * a third-party cookie: their pages live on capacitor://localhost while the
     * session belongs to abiden.me. SameSite=Lax would have it dropped, so those
     * responses relax to None, which the specification only permits on a secure
     * connection. Web traffic is untouched and stays on Lax.
     */
    private static function sameSite(): string
    {
        return (self::isSecure() && Cors::isNativeRequest()) ? 'None' : 'Lax';
    }

    private static function isSecure(): bool
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    }

    /**
     * True when the request came from the packaged Android or iOS application.
     *
     * The app sends this header on every call. It is not a security boundary —
     * anyone can send a header — it only decides whether a response should
     * carry the session identifier the app needs to keep its session alive.
     */
    public static function isAppClient(): bool
    {
        return ($_SERVER['HTTP_X_APP_CLIENT'] ?? '') === self::APP_CLIENT;
    }

    /**
     * Adopts a session identifier supplied by the app, if it looks like one.
     *
     * Inside the native shell the pages are served from the app's own origin
     * while the session belongs to abiden.me, and the cookie does not reliably
     * survive that boundary. Rather than fight the platform's cookie handling,
     * the app is told its session identifier and hands it back on each request,
     * which is what a bearer token would do anyway.
     *
     * `session.use_strict_mode` is on, so an identifier the server does not
     * recognise is discarded and a fresh session is created instead of being
     * adopted. That is what stops this from being a session fixation hole.
     */
    private static function adoptSuppliedId(): void
    {
        if (!self::isAppClient()) {
            return;
        }

        $supplied = $_SERVER['HTTP_X_SESSION_ID'] ?? '';
        if (is_string($supplied) && preg_match('/^[A-Za-z0-9,\-]{22,128}$/', $supplied) === 1) {
            session_id($supplied);
        }
    }

    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $secure = self::isSecure();

        $storage = self::storagePath();
        if ($storage !== null) {
            ini_set('session.save_path', $storage);
            ini_set('session.gc_probability', '1');
            ini_set('session.gc_divisor', '500');
        }

        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_samesite', self::sameSite());
        ini_set('session.cookie_secure', $secure ? '1' : '0');
        ini_set('session.gc_maxlifetime', (string) self::LIFETIME);
        session_set_cookie_params([
            'lifetime' => self::LIFETIME,
            'path' => '/',
            'domain' => '',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => self::sameSite(),
        ]);
        session_name('FMS_SESSION');
        self::adoptSuppliedId();
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
                'samesite' => self::sameSite(),
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

        $secure = self::isSecure();
        setcookie(session_name(), session_id(), [
            'expires' => time() + self::LIFETIME,
            'path' => '/',
            'domain' => '',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => self::sameSite(),
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
