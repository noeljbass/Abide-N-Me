<?php
declare(strict_types=1);
use FeedMySheep\JsonResponse;
require __DIR__.'/_init.php';
try{$version=trim($_GET['version']??'');$book=strtoupper(trim($_GET['book']??''));$chapter=filter_var($_GET['chapter']??null,FILTER_VALIDATE_INT);if($version===''||$chapter===false)throw new InvalidArgumentException('Choose an audio version, book, and chapter.');JsonResponse::success(['audio'=>$audio->chapter($version,$book,$chapter)]);}catch(InvalidArgumentException $e){JsonResponse::error('audio_unavailable',$e->getMessage(),404);}
