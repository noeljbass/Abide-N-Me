<?php
declare(strict_types=1);
use FeedMySheep\HttpRequest;
use FeedMySheep\JsonResponse;
use FeedMySheep\Session;
require dirname(__DIR__, 2) . '/src/bootstrap.php';
Session::start();
HttpRequest::requireMethod('POST');
Session::requireCsrf();
Session::logout();
JsonResponse::success(['logged_out' => true]);
