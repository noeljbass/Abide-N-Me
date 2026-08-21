<?php
declare(strict_types=1);
use FeedMySheep\HttpRequest;
use FeedMySheep\JsonResponse;
use FeedMySheep\Session;
use FeedMySheep\Validator;
require __DIR__ . '/_init.php';
HttpRequest::requireMethod('POST'); Session::requireCsrf(); $input = HttpRequest::json();
$groupId = Validator::string($input['group_id'] ?? null, 36, 36); $memberId = Validator::string($input['member_id'] ?? null, 36, 36); $action = $input['action'] ?? '';
if ($groupId === null || $memberId === null) JsonResponse::error('validation_failed', 'Valid group and member identifiers are required.', 422);
if ($action === 'role' && in_array($input['role'] ?? '', ['admin', 'member'], true)) $groups->changeRole($groupId, $userId, $memberId, $input['role']);
elseif ($action === 'remove') $groups->removeMember($groupId, $userId, $memberId);
else JsonResponse::error('validation_failed', 'That member action is invalid.', 422);
JsonResponse::success(['updated' => true]);
