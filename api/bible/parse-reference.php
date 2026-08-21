<?php
declare(strict_types=1);
use FeedMySheep\Bible\ReferenceParser;
use FeedMySheep\HttpRequest;
use FeedMySheep\JsonResponse;
use FeedMySheep\Validator;
require __DIR__ . '/_init.php';
HttpRequest::requireMethod('POST');
$input = HttpRequest::json();
$reference = Validator::string($input['reference'] ?? null, 3, 150);
if ($reference === null) JsonResponse::error('validation_failed', 'Enter a valid Bible reference.', 422);
try {
    $parsed = (new ReferenceParser($catalog->aliases()))->parse($reference);
    JsonResponse::success(['reference' => $parsed->toArray()]);
} catch (InvalidArgumentException $exception) {
    JsonResponse::error('invalid_reference', $exception->getMessage(), 422);
}

