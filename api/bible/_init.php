<?php
declare(strict_types=1);
use FeedMySheep\Bible\BookCatalog;
use FeedMySheep\Database;
$app = require dirname(__DIR__, 2) . '/src/bootstrap.php';
$pdo = (new Database($app['config']))->connection();
$catalog = new BookCatalog($pdo);

