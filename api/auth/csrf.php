<?php
declare(strict_types=1);
use FeedMySheep\JsonResponse;
use FeedMySheep\Session;
require dirname(__DIR__, 2) . '/src/bootstrap.php';
Session::start();
JsonResponse::success(['csrf_token' => Session::csrfToken()]);
