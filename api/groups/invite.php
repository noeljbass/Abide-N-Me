<?php
declare(strict_types=1);
use FeedMySheep\HttpRequest;
use FeedMySheep\JsonResponse;
use FeedMySheep\Session;
require __DIR__ . '/_init.php';
HttpRequest::requireMethod('POST'); Session::requireCsrf(); $input = HttpRequest::json();
$id = \FeedMySheep\Validator::string($input['group_id'] ?? null, 36, 36);
if ($id === null) JsonResponse::error('validation_failed', 'The group is invalid.', 422);
JsonResponse::success(['invite' => $groups->createInvite($id, $userId)]);
