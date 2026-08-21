<?php
declare(strict_types=1);
use FeedMySheep\HttpRequest;use FeedMySheep\JsonResponse;use FeedMySheep\Session;
require __DIR__.'/_init.php';HttpRequest::requireMethod('PATCH');Session::requireCsrf();
try{$input=HttpRequest::json();$id=(string)($input['passage_id']??'');JsonResponse::success(['progress'=>$progress->update($userId,$id,$input)]);}catch(InvalidArgumentException $e){JsonResponse::error('validation_failed',$e->getMessage(),422);}
