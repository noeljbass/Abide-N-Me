<?php
declare(strict_types=1);
use FeedMySheep\Bible\Provider\LocalDatabaseBibleProvider;
use FeedMySheep\JsonResponse;
require __DIR__ . '/_init.php';
$provider = new LocalDatabaseBibleProvider($pdo);
JsonResponse::success(['translations' => $provider->getTranslations()]);

