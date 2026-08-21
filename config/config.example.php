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
    'database' => [
        'host' => 'YOUR-IONOS-DB-HOST',
        'port' => 3306,
        'name' => 'YOUR-IONOS-DB-NAME',
        'username' => 'YOUR-IONOS-DB-USERNAME',
        'password' => 'YOUR-IONOS-DB-PASSWORD',
        'charset' => 'utf8mb4',
    ],
];
