<?php
declare(strict_types=1);
use FeedMySheep\HttpRequest;
use FeedMySheep\JsonResponse;
use FeedMySheep\Session;
use FeedMySheep\Validator;
require __DIR__ . '/_init.php';
HttpRequest::requireMethod('POST'); Session::requireCsrf(); $input = HttpRequest::json();
$id = Validator::string($input['group_id'] ?? null, 36, 36); $role = $input['role'] ?? 'member';
$days = isset($input['expires_in_days']) ? Validator::positiveInteger($input['expires_in_days']) : 30;
if ($id === null || !in_array($role, ['admin', 'member'], true) || $days === null || $days > 90) JsonResponse::error('validation_failed', 'The invite settings are invalid.', 422);
JsonResponse::success(['invite' => $groups->createInvite($id, $userId, $role, $days)], 201);
