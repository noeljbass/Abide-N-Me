<?php

declare(strict_types=1);

use FeedMySheep\HttpRequest;
use FeedMySheep\JsonResponse;
use FeedMySheep\RateLimiter;
use FeedMySheep\Session;
use FeedMySheep\Validator;

require __DIR__ . '/_init.php';
HttpRequest::requireMethod('POST');
Session::requireCsrf();
$input = HttpRequest::json();
$username = Validator::username($input['username'] ?? null);
if ($username === null) {
    JsonResponse::error('validation_failed', 'Enter a valid username.', 422);
}
$pdo = $database->connection();
(new RateLimiter($pdo))->hit('password_reset', HttpRequest::clientKey() . ':' . $username, 3, 3600);
JsonResponse::success(['message' => 'Password recovery is not available without email. Contact support if you need help accessing your account.']);
