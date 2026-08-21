<?php

declare(strict_types=1);

namespace FeedMySheep;

final class Logger
{
    public function __construct(private readonly string $path)
    {
    }

    public function error(string $message, array $context = []): void
    {
        $entry = json_encode([
            'timestamp' => gmdate('c'),
            'level' => 'error',
            'message' => $message,
            'context' => $context,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if ($entry !== false) {
            error_log($entry . PHP_EOL, 3, $this->path);
        }
    }
}

