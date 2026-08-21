<?php
declare(strict_types=1);
use FeedMySheep\JsonResponse;
use FeedMySheep\Session;
use FeedMySheep\Auth;
require __DIR__ . '/_init.php';
$userId = Session::userId();
if ($userId === null) {
    JsonResponse::error('authentication_required', 'Please sign in to continue.', 401);
}
$auth = new Auth($database->connection());
$user = $auth->requireUser();
JsonResponse::success(['user' => $user, 'csrf_token' => Session::csrfToken()]);
