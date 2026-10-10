<?php
declare(strict_types=1);
require_once __DIR__.'/../../config/db.php';require_once __DIR__.'/../../controllers/sarathi.php';
use Maakit\Api\ApiError;
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');ini_set('display_errors','0');
try{
    if(($_SERVER['REQUEST_METHOD']??'')!=='POST')throw new ApiError(405,'METHOD_NOT_ALLOWED','Use POST.');
    $secret=Maakit\Api\sarathi_payment_value('MAAKIT_RAZORPAY_WEBHOOK_SECRET');$sig=$_SERVER['HTTP_X_RAZORPAY_SIGNATURE']??'';
    $stream=fopen('php://input','rb');$raw=stream_get_contents($stream,65537);fclose($stream);
    if($secret==='')throw new ApiError(503,'PAYMENT_SETUP_REQUIRED','Webhook setup required.');
    if(strlen($raw)>65536||!is_string($sig)||!preg_match('/^[a-f0-9]{64}$/D',$sig)||!hash_equals(hash_hmac('sha256',$raw,$secret),$sig))throw new ApiError(400,'INVALID_SIGNATURE','Webhook signature rejected.');
    $data=json_decode($raw,true,32,JSON_THROW_ON_ERROR);
    if(($data['event']??null)==='payment.captured'){
        $p=$data['payload']['payment']['entity']??null;if(!is_array($p))throw new ApiError(400,'INVALID_EVENT','Payment entity missing.');
        $db=Maakit\Api\database();
        // Ignore payments outside this integration; never record unrelated goods or another platform's funds.
        if(Maakit\Api\query($db,'SELECT id FROM mk_delivery_fee_payments WHERE provider_order_id=?',[$p['order_id']??''])->fetch())Maakit\Api\sarathi_capture($db,$p);
    }
    echo '{"received":true}';
}catch(ApiError $e){http_response_code($e->status);echo Maakit\Api\json_data(['error'=>['code'=>$e->apiCode]]);}
catch(Throwable $e){error_log('Sarathi payment webhook unavailable class='.get_class($e));http_response_code(503);echo '{"error":{"code":"WEBHOOK_UNAVAILABLE"}}';}
