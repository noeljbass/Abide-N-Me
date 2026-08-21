<?php

declare(strict_types=1);

namespace FeedMySheep\Audio;

use InvalidArgumentException;

final class AudioMetadata
{
    public static function chapter(array $value): array
    {
        $url = filter_var($value['url'] ?? null, FILTER_VALIDATE_URL);
        $duration = filter_var($value['duration_seconds'] ?? null, FILTER_VALIDATE_FLOAT);
        if (!$url || !in_array(parse_url($url, PHP_URL_SCHEME), ['https'], true)) {
            throw new InvalidArgumentException('The audio provider returned an invalid media URL.');
        }
        if ($duration === false || $duration <= 0 || $duration > 86400) {
            throw new InvalidArgumentException('The audio provider returned an invalid duration.');
        }
        return [
            'url' => $url,
            'duration_seconds' => round((float) $duration, 3),
            'content_type' => self::contentType((string) ($value['content_type'] ?? 'audio/mpeg')),
            'expires_at' => self::optionalTimestamp($value['expires_at'] ?? null),
            'attribution' => self::text($value['attribution'] ?? '', 500),
        ];
    }

    private static function contentType(string $value): string
    {
        $allowed = ['audio/mpeg', 'audio/mp4', 'audio/aac', 'audio/ogg', 'audio/webm'];
        if (!in_array(strtolower($value), $allowed, true)) {
            throw new InvalidArgumentException('The audio provider returned an unsupported media type.');
        }
        return strtolower($value);
    }

    private static function optionalTimestamp(mixed $value): ?string
    {
        if ($value === null || $value === '') return null;
        $timestamp = strtotime((string) $value);
        if ($timestamp === false) throw new InvalidArgumentException('The audio provider returned an invalid expiry time.');
        return gmdate('c', $timestamp);
    }

    private static function text(mixed $value, int $max): string
    {
        $text = trim((string) $value);
        if (mb_strlen($text) > $max) throw new InvalidArgumentException('The audio provider returned invalid attribution.');
        return $text;
    }
}
