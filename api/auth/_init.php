<?php

declare(strict_types=1);

use FeedMySheep\Database;
use FeedMySheep\Session;

$app = require dirname(__DIR__, 2) . '/src/bootstrap.php';
Session::start();
$database = new Database($app['config']);
