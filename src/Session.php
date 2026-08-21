<?php

declare(strict_types=1);

namespace FeedMySheep;

final class Session
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_samesite', 'Lax');
        ini_set('session.cookie_secure', $secure ? '1' : '0');
        ini_set('session.gc_maxlifetime', '1209600');
        session_name('FMS_SESSION');
        session_start();
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

