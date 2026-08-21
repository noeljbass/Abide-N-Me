<?php
declare(strict_types=1);
use FeedMySheep\HttpRequest;
use FeedMySheep\JsonResponse;
use FeedMySheep\RateLimiter;
use FeedMySheep\Session;
use FeedMySheep\Validator;
use FeedMySheep\Auth;
require __DIR__ . '/_init.php';
HttpRequest::requireMethod('POST');
Session::requireCsrf();
$input = HttpRequest::json();
$name = Validator::string($input['name'] ?? null, 2, 100);
$email = Validator::email($input['email'] ?? null);
$password = is_string($input['password'] ?? null) ? $input['password'] : '';
if ($name === null || $email === null || strlen($password) < 10 || strlen($password) > 4096) {
    JsonResponse::error('validation_failed', 'Enter a valid name, email, and password of at least 10 characters.', 422);
}
$pdo = $database->connection();
$auth = new Auth($pdo);
(new RateLimiter($pdo))->hit('register', HttpRequest::clientKey(), 5, 900);
try {
    $pdo->beginTransaction();
    $user = $auth->register($name, $email, $password);
    $pdo->commit();
} catch (PDOException $exception) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    if ((string) $exception->getCode() === '23000') JsonResponse::error('email_unavailable', 'An account with that email already exists.', 409);
    throw $exception;
}
$lookup = $pdo->prepare('SELECT id FROM users WHERE public_id = :public_id');
$lookup->execute(['public_id' => $user['id']]);
Session::login((int) $lookup->fetchColumn());
JsonResponse::success(['user' => $user, 'csrf_token' => Session::csrfToken()], 201);
