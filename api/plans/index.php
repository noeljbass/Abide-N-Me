<?php
declare(strict_types=1);
use FeedMySheep\HttpRequest;use FeedMySheep\JsonResponse;use FeedMySheep\Session;
require __DIR__.'/_init.php';
$method=$_SERVER['REQUEST_METHOD']??'GET';
if($method==='GET')JsonResponse::success(['plans'=>$plans->listForUser($userId)]);
Session::requireCsrf();
try{
    $input=HttpRequest::json();
    if($method==='PATCH')JsonResponse::success(['plan'=>$plans->update($userId,$input)]);
    if($method==='DELETE'){$plans->delete($userId,(string)($input['plan_id']??''),(string)($input['group_id']??''));JsonResponse::success(['deleted'=>true]);}
    HttpRequest::requireMethod('POST');
    JsonResponse::success(['plan'=>$plans->create($userId,$input)],201);
}catch(InvalidArgumentException $e){JsonResponse::error('validation_failed',$e->getMessage(),422);}
