<?php
declare(strict_types=1);
use FeedMySheep\HttpRequest;
use FeedMySheep\JsonResponse;
use FeedMySheep\Session;
use FeedMySheep\Validator;
use FeedMySheep\Auth;
require dirname(__DIR__) . '/auth/_init.php';
HttpRequest::requireMethod('PATCH');
Session::requireCsrf();
if (Session::userId() === null) {
    JsonResponse::error('authentication_required', 'Please sign in to continue.', 401);
}
$pdo = $database->connection();
$auth = new Auth($pdo);
$user = $auth->requireUser();
$input = HttpRequest::json();
$name = Validator::string($input['name'] ?? null, 2, 100);
if ($name === null) JsonResponse::error('validation_failed', 'Name must be between 2 and 100 characters.', 422);
$avatar = $input['avatar'] ?? ($user['avatar'] ?? null);
if ($avatar !== null) {
    if (!is_string($avatar) || strlen($avatar) > 500000 || preg_match('#^data:image/(jpeg|png|gif|webp);base64,[A-Za-z0-9+/]+=*$#', $avatar) !== 1) {
        JsonResponse::error('validation_failed', 'Choose a valid profile picture smaller than 350 KB.', 422);
    }
    $encoded = substr($avatar, strpos($avatar, ',') + 1);
    $decoded = base64_decode($encoded, true);
    if ($decoded === false || strlen($decoded) > 350000) JsonResponse::error('validation_failed', 'Choose a profile picture smaller than 350 KB.', 422);
}
$statement = $pdo->prepare('SELECT id FROM users WHERE public_id = :public_id');
$statement->execute(['public_id' => $user['id']]);
JsonResponse::success(['user' => $auth->updateProfile((int) $statement->fetchColumn(), $name, $avatar)]);
