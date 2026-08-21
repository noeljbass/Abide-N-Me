<?php
declare(strict_types=1);
use FeedMySheep\JsonResponse;
use FeedMySheep\Validator;
require __DIR__ . '/_init.php';
$id = Validator::string($_GET['group'] ?? null, 36, 36);
if ($id === null) JsonResponse::error('validation_failed', 'A valid group is required.', 422);
JsonResponse::success(['members' => $groups->members($id, $userId)]);
