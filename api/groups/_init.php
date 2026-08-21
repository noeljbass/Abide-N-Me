<?php
declare(strict_types=1);
use FeedMySheep\Auth;
use FeedMySheep\Database;
use FeedMySheep\GroupService;
use FeedMySheep\Session;
$app = require dirname(__DIR__, 2) . '/src/bootstrap.php';
Session::start();
$pdo = (new Database($app['config']))->connection();
$auth = new Auth($pdo);
$user = $auth->requireUser();
$groups = new GroupService($pdo);
$userId = $groups->requireInternalUserId($user['id']);
