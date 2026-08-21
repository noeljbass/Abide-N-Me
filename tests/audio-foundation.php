<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

use FeedMySheep\Audio\AudioMetadata;
use FeedMySheep\Audio\SafeHttpClient;

$normalized = AudioMetadata::chapter([
    'url' => 'https://media.example.test/john-1.mp3',
    'duration_seconds' => '302.125',
    'content_type' => 'audio/mpeg',
    'expires_at' => '2030-01-01T00:00:00Z',
    'attribution' => 'Fixture attribution',
]);
assert($normalized['duration_seconds'] === 302.125);
assert($normalized['content_type'] === 'audio/mpeg');

$invalid = [
    ['url' => 'http://media.example.test/a.mp3', 'duration_seconds' => 3],
    ['url' => 'file:///etc/passwd', 'duration_seconds' => 3],
    ['url' => 'https://media.example.test/a.mp3', 'duration_seconds' => -1],
    ['url' => 'https://media.example.test/a.mp3', 'duration_seconds' => 3, 'content_type' => 'text/html'],
];
foreach ($invalid as $fixture) {
    try { AudioMetadata::chapter($fixture); throw new RuntimeException('Invalid audio metadata was accepted.'); }
    catch (InvalidArgumentException) {}
}

$client = new SafeHttpClient(['allowed.example.test']);
foreach (['http://allowed.example.test/x', 'https://user:pass@allowed.example.test/x', 'https://blocked.example.test/x'] as $url) {
    try { $client->getJson($url); throw new RuntimeException('Unsafe provider URL was accepted.'); }
    catch (RuntimeException $exception) { assert(str_contains($exception->getMessage(), 'not allowed')); }
}

echo "audio foundation tests passed\n";
