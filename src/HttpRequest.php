<?php

declare(strict_types=1);

namespace FeedMySheep;

final class HttpRequest
{
    public static function requireMethod(string $method): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== strtoupper($method)) {
            header('Allow: ' . strtoupper($method));
            JsonResponse::error('method_not_allowed', 'This request method is not allowed.', 405);
        }
    }

    public static function json(): array
    {
        $contentType = strtolower(trim(explode(';', $_SERVER['CONTENT_TYPE'] ?? '')[0]));
        if ($contentType !== 'application/json') {
            JsonResponse::error('unsupported_media_type', 'Requests must use application/json.', 415);
        }

        try {
            $payload = json_decode(file_get_contents('php://input') ?: '', true, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            JsonResponse::error('invalid_json', 'The request body is not valid JSON.', 400);
        }

        if (!is_array($payload)) {
            JsonResponse::error('invalid_json', 'The request body must be a JSON object.', 400);
        }
        return $payload;
    }

    public static function clientKey(): string
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        return hash('sha256', $ip . "\0" . $agent);
    }
}

