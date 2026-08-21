<?php
declare(strict_types=1);
use FeedMySheep\Bible\Provider\LocalDatabaseBibleProvider;use FeedMySheep\JsonResponse;
require __DIR__.'/_init.php';
$translation=strtoupper(trim($_GET['translation']??'DRA1899'));$books=(new LocalDatabaseBibleProvider($pdo))->getBooks($translation);if(!$books)JsonResponse::error('not_found','Translation not found or has no imported books.',404);foreach($books as &$book)$book['chapter_count']=(int)$book['chapter_count'];JsonResponse::success(['canon'=>'catholic-73','translation'=>$translation,'books'=>$books]);
