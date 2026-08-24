<?php

declare(strict_types=1);

namespace FeedMySheep;

final class JsonResponse
{
    public static function success(mixed $data = null, int $status = 200): never
    {
        self::send(['success' => true, 'data' => $data, 'error' => null], $status);
    }

    public static function error(string $code, string $message, int $status = 400, ?array $details = null): never
    {
        $error = ['code' => $code, 'message' => $message];
        if ($details !== null) {
            $error['details'] = $details;
        }
        self::send(['success' => false, 'data' => null, 'error' => $error], $status);
    }

    private static function send(array $payload, int $status): never
    {
        // The applications track their session through the response body rather
        // than a cookie. Only they are told the identifier: on the web the
        // session cookie stays httponly, where script cannot read it.
        if (Session::isAppClient() && session_status() === PHP_SESSION_ACTIVE) {
            $payload['session'] = session_id();
        }

        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, private');
        header('X-Content-Type-Options: nosniff');
        echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        exit;
    }
}

