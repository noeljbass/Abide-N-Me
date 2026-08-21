<?php
declare(strict_types=1);
use FeedMySheep\JsonResponse;
use FeedMySheep\Session;
use FeedMySheep\Auth;
require __DIR__ . '/_init.php';
$auth = new Auth($database->connection());
$user = $auth->requireUser();
JsonResponse::success(['user' => $user, 'csrf_token' => Session::csrfToken()]);
