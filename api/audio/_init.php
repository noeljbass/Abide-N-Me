<?php
declare(strict_types=1);
use FeedMySheep\Audio\AudioService;use FeedMySheep\Database;
$app=require dirname(__DIR__,2).'/src/bootstrap.php';$pdo=(new Database($app['config']))->connection();$audio=new AudioService($pdo);
