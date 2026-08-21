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
$email = Validator::email($input['email'] ?? null);
if ($email === null) {
    JsonResponse::error('validation_failed', 'Enter a valid email address.', 422);
}
$pdo = $database->connection();
(new RateLimiter($pdo))->hit('password_reset', HttpRequest::clientKey() . ':' . $email, 3, 3600);
$lookup = $pdo->prepare("SELECT id FROM users WHERE email = :email AND status = 'active' LIMIT 1");
$lookup->execute(['email' => $email]);
$userId = $lookup->fetchColumn();
if ($userId !== false) {
    $pdo->prepare("DELETE FROM auth_tokens WHERE user_id = :user_id AND purpose = 'password_reset' AND used_at IS NULL")->execute(['user_id' => $userId]);
    $token = bin2hex(random_bytes(32));
    $insert = $pdo->prepare("INSERT INTO auth_tokens (user_id, purpose, token_hash, expires_at) VALUES (:user_id, 'password_reset', :token_hash, DATE_ADD(UTC_TIMESTAMP(), INTERVAL 1 HOUR))");
    $insert->execute(['user_id' => $userId, 'token_hash' => hash('sha256', $token)]);
    // The raw token will be passed to the approved mail service in a later iteration.
}
JsonResponse::success(['message' => 'If that account exists, password reset instructions will be sent when email delivery is configured.']);
