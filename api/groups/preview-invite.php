<?php
declare(strict_types=1);
use FeedMySheep\Database;
use FeedMySheep\GroupService;
use FeedMySheep\HttpRequest;
use FeedMySheep\JsonResponse;
use FeedMySheep\RateLimiter;
use FeedMySheep\Session;
use FeedMySheep\Validator;
$app = require dirname(__DIR__, 2) . '/src/bootstrap.php'; Session::start(); HttpRequest::requireMethod('POST'); Session::requireCsrf(); $input = HttpRequest::json();
$code = Validator::string($input['code'] ?? null, 8, 32); if ($code === null) JsonResponse::error('invalid_invite', 'That invitation is invalid or expired.', 404);
$pdo = (new Database($app['config']))->connection(); (new RateLimiter($pdo))->hit('preview_invite', HttpRequest::clientKey(), 20, 900);
$invite = (new GroupService($pdo))->previewInvite($code); if ($invite === null) JsonResponse::error('invalid_invite', 'That invitation is invalid or expired.', 404);
JsonResponse::success(['invite' => $invite]);
