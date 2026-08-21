<?php

declare(strict_types=1);

// Copy this file to local.php or config.php when environment variables are unavailable.
// Both private filenames are ignored by Git and denied by the included Apache rules.
return [
    'app' => [
        'environment' => 'production',
        'debug' => false,
        'base_url' => 'https://YOUR-DOMAIN.example',
        'timezone' => 'UTC',
    ],
    // Leave disabled until the provider, filesets, and license are approved.
    'audio' => [
        'enabled' => false,
        'provider' => '',
        'api_base_url' => '',
        'api_key' => '', // Private server-side value only. Never expose this in JavaScript.
        'allowed_hosts' => [],
        'request_timeout_seconds' => 10,
    ],
    'database' => [
        'host' => 'YOUR-IONOS-DB-HOST',
        'port' => 3306,
        'name' => 'YOUR-IONOS-DB-NAME',
        'username' => 'YOUR-IONOS-DB-USERNAME',
        'password' => 'YOUR-IONOS-DB-PASSWORD',
        'charset' => 'utf8mb4',
    ],
];
