<?php

declare(strict_types=1);

namespace FeedMySheep\Audio;

use PDO;

final class ApiCache
{
    public function __construct(private readonly PDO $db) {}

    public function remember(string $provider, string $key, int $ttlSeconds, callable $loader): array
    {
        $hash = hash('sha256', $provider . "\0" . $key);
        $query = $this->db->prepare('SELECT payload FROM api_cache WHERE cache_key=? AND provider=? AND expires_at>UTC_TIMESTAMP()');
        $query->execute([$hash, $provider]);
        $payload = $query->fetchColumn();
        if (is_string($payload)) {
            try { return json_decode($payload, true, 64, JSON_THROW_ON_ERROR); } catch (\JsonException) { /* refresh */ }
        }
        $value = $loader();
        $json = json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        $expires = gmdate('Y-m-d H:i:s', time() + max(60, min($ttlSeconds, 86400)));
        $statement = $this->db->prepare('INSERT INTO api_cache(cache_key,provider,payload,expires_at) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE payload=VALUES(payload),expires_at=VALUES(expires_at),created_at=UTC_TIMESTAMP()');
        $statement->execute([$hash, $provider, $json, $expires]);
        return $value;
    }
}
