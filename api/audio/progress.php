<?php
declare(strict_types=1);
use FeedMySheep\Audio\AudioProgressService;use FeedMySheep\Auth;use FeedMySheep\Database;use FeedMySheep\GroupService;use FeedMySheep\HttpRequest;use FeedMySheep\JsonResponse;use FeedMySheep\Session;
$app=require dirname(__DIR__,2).'/src/bootstrap.php';Session::start();$pdo=(new Database($app['config']))->connection();$user=(new Auth($pdo))->requireUser();$userId=(new GroupService($pdo))->requireInternalUserId($user['id']);$progress=new AudioProgressService($pdo);
try{if(($_SERVER['REQUEST_METHOD']??'GET')==='GET'){$id=(string)($_GET['passage_id']??'');JsonResponse::success(['progress'=>$progress->get($userId,$id)]);}HttpRequest::requireMethod('PATCH');Session::requireCsrf();$input=HttpRequest::json();JsonResponse::success(['progress'=>$progress->save($userId,(string)($input['passage_id']??''),$input)]);}catch(InvalidArgumentException $e){JsonResponse::error('validation_failed',$e->getMessage(),422);}
