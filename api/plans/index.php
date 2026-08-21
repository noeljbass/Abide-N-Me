<?php
declare(strict_types=1);
use FeedMySheep\HttpRequest;use FeedMySheep\JsonResponse;use FeedMySheep\Session;
require __DIR__.'/_init.php';
if(($_SERVER['REQUEST_METHOD']??'GET')==='GET')JsonResponse::success(['plans'=>$plans->listForUser($userId)]);
HttpRequest::requireMethod('POST');Session::requireCsrf();
try{JsonResponse::success(['plan'=>$plans->create($userId,HttpRequest::json())],201);}catch(InvalidArgumentException $e){JsonResponse::error('validation_failed',$e->getMessage(),422);}
