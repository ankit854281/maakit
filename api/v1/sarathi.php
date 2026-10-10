<?php
declare(strict_types=1);
require_once __DIR__.'/../../config/db.php';
require_once __DIR__.'/../../config/config.php';
require_once __DIR__.'/../../controllers/deliverySafety.php';
use Maakit\Api\ApiError;
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');header('X-Content-Type-Options: nosniff');ini_set('display_errors','0');
try {
    $action=$_GET['action']??'';$method=$_SERVER['REQUEST_METHOD']??'GET';
    $routes=['order_proof'=>'GET','safety_codes'=>'POST','dashboard'=>'GET','location'=>'POST','contacts'=>'POST','tracking'=>'GET','order_tools'=>'GET','fee_create'=>'POST','fee_verify'=>'POST'];
    if(!is_string($action)||!isset($routes[$action]))throw new ApiError(404,'NOT_FOUND','Endpoint not found.');
    if($method!==$routes[$action]){header('Allow: '.$routes[$action]);throw new ApiError(405,'METHOD_NOT_ALLOWED','Use the supported method.');}
    $db=Maakit\Api\database();$m=Maakit\Api\jwt_material();$auth=Maakit\Api\authenticate($db,$_SERVER['HTTP_AUTHORIZATION']??'',$m['keys'],$m['issuer'],$m['audience']);
    if($method==='POST'){
        if(strtolower(trim(explode(';',$_SERVER['CONTENT_TYPE']??'')[0]))!=='application/json')throw new ApiError(415,'JSON_REQUIRED','Send JSON data.');
        $stream=fopen('php://input','rb');$raw=stream_get_contents($stream,16385);fclose($stream);if(strlen($raw)>16384)throw new ApiError(413,'REQUEST_TOO_LARGE','Reduce request size.');
        try{$body=json_decode($raw,true,16,JSON_THROW_ON_ERROR);}catch(JsonException $e){throw new ApiError(400,'INVALID_JSON','Check the request data.');}
        if(!is_array($body)||!str_starts_with(ltrim($raw),'{'))throw new ApiError(400,'INVALID_JSON','Send a JSON object.');
        $fn=['safety_codes'=>'order_safety_codes','location'=>'sarathi_location','contacts'=>'sarathi_contacts','fee_create'=>'sarathi_fee_create','fee_verify'=>'sarathi_fee_verify'][$action];$result=('Maakit\\Api\\'.$fn)($db,$auth,$body);
    }elseif($action==='dashboard')$result=Maakit\Api\sarathi_dashboard($db,$auth);
    elseif($action==='order_proof')$result=Maakit\Api\order_safety_proof($db,$auth,Maakit\Api\valid_uuid($_GET['order_id']??null));
    elseif($action==='order_tools')$result=Maakit\Api\sarathi_order_tools($db,$auth,Maakit\Api\valid_uuid($_GET['order_id']??null));
    else $result=Maakit\Api\sarathi_tracking($db,$auth,Maakit\Api\valid_uuid($_GET['order_id']??null));
    echo Maakit\Api\json_data(['data'=>$result]);
}catch(ApiError $e){http_response_code($e->status);echo Maakit\Api\json_data(['error'=>['code'=>$e->apiCode,'message'=>$e->getMessage()]]);}
catch(Throwable $e){error_log('Sarathi API unavailable class='.get_class($e));http_response_code(503);echo '{"error":{"code":"SARATHI_SETUP_REQUIRED","message":"Integration setup or service availability needs review."}}';}
