<?php
declare(strict_types=1);
use FeedMySheep\HttpRequest;
use FeedMySheep\JsonResponse;
use FeedMySheep\RateLimiter;
use FeedMySheep\Session;
use FeedMySheep\Validator;
require __DIR__ . '/_init.php';
HttpRequest::requireMethod('POST'); Session::requireCsrf(); $input = HttpRequest::json();
$code = Validator::string($input['code'] ?? null, 4, 4);
if ($code === null) JsonResponse::error('invalid_invite', 'That invitation is invalid or expired.', 404);
(new RateLimiter($pdo))->hit('join_group', HttpRequest::clientKey(), 10, 900);
$group = $groups->join($code, $userId);
if ($group === []) JsonResponse::error('invalid_invite', 'That invitation is invalid or expired.', 404);
JsonResponse::success(['group' => $group]);
