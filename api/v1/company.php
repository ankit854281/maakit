<?php
declare(strict_types=1);
require_once __DIR__.'/../../config/db.php';require_once __DIR__.'/../../config/config.php';require_once __DIR__.'/../../controllers/companyDelivery.php';
use Maakit\Api\ApiError;
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');header('X-Content-Type-Options: nosniff');header('Referrer-Policy: no-referrer');ini_set('display_errors','0');
try{
 $action=$_GET['action']??'';$method=$_SERVER['REQUEST_METHOD']??'GET';$routes=['proof'=>'GET','codes'=>'POST','list'=>'GET','orders'=>'GET','tracking'=>'GET','apply'=>'POST','configure'=>'POST','key'=>'POST','quote'=>'POST','book'=>'POST','cancel'=>'POST','accept'=>'POST','progress'=>'POST'];
 if(!is_string($action)||!isset($routes[$action]))throw new ApiError(404,'NOT_FOUND','Endpoint not found.');if($method!==$routes[$action]){header('Allow: '.$routes[$action]);throw new ApiError(405,'METHOD_NOT_ALLOWED','Use the supported method.');}$db=Maakit\Api\database();
 if($action==='tracking'){$result=Maakit\Api\company_tracking($db,is_string($_GET['token']??null)?$_GET['token']:'');}
 else{
  $header=$_SERVER['HTTP_AUTHORIZATION']??'';
  if(preg_match('/^Bearer (sdc_[a-f0-9]{64})$/D',$header,$match)){
   if(!in_array($action,['list','orders','quote','book','cancel','codes','proof'],true))throw new ApiError(403,'FORBIDDEN','This key is limited to company deliveries.');
   $k=Maakit\Api\query($db,"SELECT k.id,k.company_id,c.owner_id FROM mk_company_keys k JOIN mk_delivery_companies c ON c.id=k.company_id JOIN mk_users u ON u.id=c.owner_id WHERE k.token_hash=? AND k.revoked_at IS NULL AND k.expires_at>UTC_TIMESTAMP(6) AND c.state='ACTIVE' AND u.status='ACTIVE'",[hash('sha256',$match[1])])->fetch();if(!$k)throw new ApiError(401,'UNAUTHENTICATED','Company key expired or revoked.');$auth=['user_id'=>$k['owner_id'],'company_id'=>$k['company_id'],'company_key'=>$k['id'],'roles'=>[],'permissions'=>[]];
  }else{$m=Maakit\Api\jwt_material();$auth=Maakit\Api\authenticate($db,$header,$m['keys'],$m['issuer'],$m['audience']);}
  if($method==='POST'){
   if(strtolower(trim(explode(';',$_SERVER['CONTENT_TYPE']??'')[0]))!=='application/json')throw new ApiError(415,'JSON_REQUIRED','Send JSON data.');$stream=fopen('php://input','rb');$raw=stream_get_contents($stream,750001);fclose($stream);if(strlen($raw)>750000)throw new ApiError(413,'REQUEST_TOO_LARGE','Reduce proof photo size.');
   try{$b=json_decode($raw,true,16,JSON_THROW_ON_ERROR);}catch(JsonException $e){throw new ApiError(400,'INVALID_JSON','Check request data.');}if(!is_array($b)||!str_starts_with(ltrim($raw),'{'))throw new ApiError(400,'INVALID_JSON','Send an object.');
   $fn=['codes'=>'company_codes','apply'=>'company_apply','configure'=>'company_configure','key'=>'company_key','cancel'=>'company_cancel','accept'=>'company_accept','progress'=>'company_progress'][$action]??null;
   $result=$fn?('Maakit\\Api\\'.$fn)($db,$auth,$b):Maakit\Api\company_booking($db,$auth,$b,$action==='quote');
  }elseif($action==='proof')$result=Maakit\Api\company_view_proof($db,$auth,Maakit\Api\valid_uuid($_GET['company_id']??null),Maakit\Api\valid_uuid($_GET['delivery_id']??null),filter_var($_GET['stop']??null,FILTER_VALIDATE_INT)?:0);elseif($action==='list')$result=Maakit\Api\company_list($db,$auth);else $result=Maakit\Api\company_orders($db,$auth,Maakit\Api\valid_uuid($_GET['company_id']??null));
 }
 echo Maakit\Api\json_data(['data'=>$result]);
}catch(ApiError $e){http_response_code($e->status);echo Maakit\Api\json_data(['error'=>['code'=>$e->apiCode,'message'=>$e->getMessage()]]);}catch(Throwable $e){error_log('Company delivery API unavailable class='.get_class($e));http_response_code(503);echo '{"error":{"code":"COMPANY_SETUP_REQUIRED","message":"Service setup needs review."}}';}
