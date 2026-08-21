<?php
declare(strict_types=1);
use FeedMySheep\Auth;use FeedMySheep\Database;use FeedMySheep\GroupService;use FeedMySheep\ReadingPlanService;use FeedMySheep\Session;
$app=require dirname(__DIR__,2).'/src/bootstrap.php';Session::start();$pdo=(new Database($app['config']))->connection();$user=(new Auth($pdo))->requireUser();$userId=(new GroupService($pdo))->requireInternalUserId($user['id']);$plans=new ReadingPlanService($pdo);
