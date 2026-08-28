<?php
declare(strict_types=1);
use FeedMySheep\AccountDeletion;
use FeedMySheep\HttpRequest;
use FeedMySheep\JsonResponse;
use FeedMySheep\RateLimiter;
use FeedMySheep\Session;
require dirname(__DIR__) . '/auth/_init.php';
HttpRequest::requireMethod('DELETE');
Session::requireCsrf();
$userId = Session::userId();
if ($userId === null) {
    JsonResponse::error('authentication_required', 'Please sign in to continue.', 401);
}
$input = HttpRequest::json();
$password = is_string($input['password'] ?? null) ? $input['password'] : '';
if ($password === '') {
    JsonResponse::error('validation_failed', 'Enter your password to confirm.', 422);
}
$pdo = $database->connection();
// The password check here is a credential check, so it gets the same brute
// force protection as signing in.
(new RateLimiter($pdo))->hit('account_delete', HttpRequest::clientKey() . ':' . $userId, 5, 900);
try {
    $summary = (new AccountDeletion($pdo))->delete($userId, $password);
} catch (RuntimeException $error) {
    if ($error->getMessage() === 'password_incorrect') {
        JsonResponse::error('invalid_credentials', 'That password is not correct.', 401);
    }
    throw $error;
}
Session::logout();
JsonResponse::success($summary);
