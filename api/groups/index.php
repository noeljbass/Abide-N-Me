<?php
declare(strict_types=1);
use FeedMySheep\HttpRequest;
use FeedMySheep\JsonResponse;
use FeedMySheep\Session;
use FeedMySheep\Validator;
require __DIR__ . '/_init.php';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method === 'GET') JsonResponse::success(['groups' => $groups->listForUser($userId)]);
if ($method === 'DELETE') {
    Session::requireCsrf();
    $input = HttpRequest::json();
    $groupId = Validator::string($input['group_id'] ?? null, 36, 36);
    if ($groupId === null) JsonResponse::error('validation_failed', 'A valid group identifier is required.', 422);
    $groups->delete($groupId, $userId);
    JsonResponse::success(['deleted' => true]);
}
HttpRequest::requireMethod('POST'); Session::requireCsrf(); $input = HttpRequest::json();
$name = Validator::string($input['name'] ?? null, 2, 120);
$description = Validator::string($input['description'] ?? '', 0, 500);
if ($name === null || $description === null) JsonResponse::error('validation_failed', 'Enter a group name between 2 and 120 characters.', 422);
JsonResponse::success(['group' => $groups->create($userId, $name, $description === '' ? null : $description)], 201);
