<?php

declare(strict_types=1);

namespace FeedMySheep;

final class Validator
{
    public static function string(mixed $value, int $min, int $max): ?string
    {
        if (!is_string($value)) {
            return null;
        }
        $value = trim($value);
        $length = mb_strlen($value);
        return $length >= $min && $length <= $max ? $value : null;
    }

    public static function username(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }
        $value = mb_strtolower(trim($value));
        return preg_match('/^[a-z0-9][a-z0-9._-]{2,31}$/', $value) === 1 ? $value : null;
    }

    public static function positiveInteger(mixed $value): ?int
    {
        $filtered = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        return $filtered === false ? null : $filtered;
    }
}
