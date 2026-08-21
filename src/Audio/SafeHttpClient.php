<?php

declare(strict_types=1);

namespace FeedMySheep\Audio;

use RuntimeException;

final class SafeHttpClient
{
    public function __construct(
        private array $allowedHosts,
        private readonly int $timeoutSeconds = 10,
        private readonly int $maximumBytes = 2_000_000
    ) {
        $this->allowedHosts = array_values(array_unique(array_map(
            static fn(string $host): string => strtolower(trim($host)),
            $this->allowedHosts
        )));
    }

    public function getJson(string $url, array $headers = []): array
    {
        $this->assertAllowedUrl($url);
        $handle = curl_init($url);
        $body = '';
        curl_setopt_array($handle, [
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => min(5, $this->timeoutSeconds),
            CURLOPT_TIMEOUT => $this->timeoutSeconds,
            CURLOPT_HTTPHEADER => array_merge(['Accept: application/json'], $headers),
            CURLOPT_USERAGENT => 'FeedMySheep/1.0',
            CURLOPT_WRITEFUNCTION => function ($curl, string $chunk) use (&$body): int {
                if (strlen($body) + strlen($chunk) > $this->maximumBytes) return 0;
                $body .= $chunk;
                return strlen($chunk);
            },
        ]);
        $success = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $error = curl_error($handle);
        curl_close($handle);
        if ($success === false || $error !== '') throw new RuntimeException('The audio service is temporarily unavailable.');
        if ($status < 200 || $status >= 300) throw new RuntimeException('The audio service rejected the request.');
        try {
            $decoded = json_decode($body, true, 64, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new RuntimeException('The audio service returned an unreadable response.');
        }
        if (!is_array($decoded)) throw new RuntimeException('The audio service returned an invalid response.');
        return $decoded;
    }

    private function assertAllowedUrl(string $url): void
    {
        $parts = parse_url($url);
        $host = strtolower((string) ($parts['host'] ?? ''));
        if (($parts['scheme'] ?? '') !== 'https' || !$host || !in_array($host, $this->allowedHosts, true)) {
            throw new RuntimeException('The configured audio service URL is not allowed.');
        }
        if (isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment'])) {
            throw new RuntimeException('The configured audio service URL is not allowed.');
        }
        $records = dns_get_record($host, DNS_A | DNS_AAAA);
        if (!$records) throw new RuntimeException('The configured audio service host could not be resolved.');
        foreach ($records as $record) {
            $ip = $record['ip'] ?? $record['ipv6'] ?? '';
            if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                throw new RuntimeException('The configured audio service host resolves to a private address.');
            }
        }
    }
}
