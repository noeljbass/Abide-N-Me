<?php
declare(strict_types=1);
use FeedMySheep\Bible\BibleReaderService;use FeedMySheep\Bible\ReferenceParser;use FeedMySheep\JsonResponse;
require __DIR__.'/_init.php';
try{$value=trim($_GET['reference']??'');if($value==='')JsonResponse::error('validation_failed','Enter a Bible reference.',422);$reference=(new ReferenceParser($catalog->aliases()))->parse($value);JsonResponse::success((new BibleReaderService($pdo))->passage(strtoupper($_GET['translation']??'DRA1899'),$reference));}catch(InvalidArgumentException $e){JsonResponse::error('invalid_reference',$e->getMessage(),422);}
