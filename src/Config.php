<?php

declare(strict_types=1);

namespace FeedMySheep;

use RuntimeException;

final class Config
{
    private function __construct(private readonly array $values)
    {
    }

    public static function load(string $root): self
    {
        $values = [
            'app' => [
                'environment' => self::environment('APP_ENV', 'production'),
                'debug' => self::booleanEnvironment('APP_DEBUG', false),
                'base_url' => self::environment('APP_BASE_URL', ''),
                'timezone' => self::environment('APP_TIMEZONE', 'UTC'),
            ],
            'database' => [
                'host' => self::environment('DB_HOST', ''),
                'port' => (int) self::environment('DB_PORT', '3306'),
                'name' => self::environment('DB_NAME', ''),
                'username' => self::environment('DB_USER', ''),
                'password' => self::environment('DB_PASSWORD', ''),
                'charset' => 'utf8mb4',
            ],
        ];

        $localPath = $root . '/config/local.php';
        if (is_file($localPath)) {
            $local = require $localPath;
            if (!is_array($local)) {
                throw new RuntimeException('Local configuration must return an array.');
            }
            $values = array_replace_recursive($values, $local);
        }

        return new self($values);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $value = $this->values;
        foreach (explode('.', $key) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }
        return $value;
    }

    public function require(string $key): mixed
    {
        $value = $this->get($key);
        if ($value === null || $value === '') {
            throw new RuntimeException(sprintf('Required configuration "%s" is missing.', $key));
        }
        return $value;
    }

    private static function environment(string $name, string $default): string
    {
        $value = getenv($name);
        return $value === false ? $default : $value;
    }

    private static function booleanEnvironment(string $name, bool $default): bool
    {
        $value = getenv($name);
        if ($value === false) {
            return $default;
        }
        return filter_var($value, FILTER_VALIDATE_BOOL);
    }
}

