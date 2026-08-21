<?php
declare(strict_types=1);
use FeedMySheep\HttpRequest;
use FeedMySheep\JsonResponse;
use FeedMySheep\Session;
use FeedMySheep\Validator;
use FeedMySheep\Auth;
require dirname(__DIR__) . '/auth/_init.php';
$pdo = $database->connection();
$auth = new Auth($pdo);
$user = $auth->requireUser();
HttpRequest::requireMethod('PATCH');
Session::requireCsrf();
$input = HttpRequest::json();
$name = Validator::string($input['name'] ?? null, 2, 100);
if ($name === null) JsonResponse::error('validation_failed', 'Name must be between 2 and 100 characters.', 422);
$statement = $pdo->prepare('SELECT id FROM users WHERE public_id = :public_id');
$statement->execute(['public_id' => $user['id']]);
JsonResponse::success(['user' => $auth->updateName((int) $statement->fetchColumn(), $name)]);
