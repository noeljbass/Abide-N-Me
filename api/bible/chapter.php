<?php
declare(strict_types=1);
use FeedMySheep\Bible\BibleReaderService; use FeedMySheep\JsonResponse;
require __DIR__.'/_init.php';
try{$translation=strtoupper(trim($_GET['translation']??'DRA1899'));$book=strtoupper(trim($_GET['book']??''));$chapter=filter_var($_GET['chapter']??null,FILTER_VALIDATE_INT);if(!preg_match('/^[0-9A-Z]{3}$/',$book)||$chapter===false)JsonResponse::error('validation_failed','Choose a valid book and chapter.',422);JsonResponse::success((new BibleReaderService($pdo))->chapter($translation,$book,$chapter));}catch(InvalidArgumentException $e){JsonResponse::error('not_found',$e->getMessage(),404);}
