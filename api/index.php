<?php

declare(strict_types=1);

use FeedMySheep\JsonResponse;

require dirname(__DIR__) . '/src/bootstrap.php';

JsonResponse::success([
    'service' => 'Abide N Me API',
    'status' => 'ok',
    'version' => 1,
]);
