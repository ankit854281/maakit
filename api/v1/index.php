<?php
declare(strict_types=1);
use Maakit\Api\ApiError;
use function Maakit\Api\{database,authenticate,checkout,quote_checkout,require_ownership,query,public_order,json_data,uuid4,jwt_material,
    request_wholesale_quote,dispatch_start,dispatch_accept,dispatch_view,rider_duty,dispatch_parcel,order_decision,rider_progress};
require_once __DIR__.'/../../config/db.php';
require_once __DIR__.'/../../config/config.php';
require_once __DIR__.'/../../controllers/dispatch.php';
require_once __DIR__.'/../../controllers/leads.php';
require_once __DIR__.'/../../controllers/orderLifecycle.php';
require_once __DIR__.'/../../controllers/riderController.php';
ini_set('display_errors','0');
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');header('X-Content-Type-Options: nosniff');
$requestId=uuid4();header('X-Request-ID: '.$requestId);
try {
    if (PHP_INT_SIZE!==8) throw new RuntimeException('64-bit PHP required');
    $action=$_GET['action']??'';$method=$_SERVER['REQUEST_METHOD']??'GET';
    $routes=['quote'=>'POST','checkout'=>'POST','order'=>'GET','orders'=>'GET','rfq'=>'POST','dispatch_start'=>'POST','dispatch_accept'=>'POST','dispatch_view'=>'GET','rider_duty'=>'POST','rider_progress'=>'POST','parcel'=>'POST','order_decision'=>'POST'];
    if (!is_string($action)||!isset($routes[$action])) throw new ApiError(404,'NOT_FOUND','API endpoint not found.');
    if ($method!==$routes[$action]) { header('Allow: '.$routes[$action]);throw new ApiError(405,'METHOD_NOT_ALLOWED','Use the supported request method.'); }
    $db=database();$material=jwt_material();
    $auth=authenticate($db,$_SERVER['HTTP_AUTHORIZATION']??'',$material['keys'],$material['issuer'],$material['audience']);$status=200;
    if ($method==='POST') {
        if (strtolower(trim(explode(';',$_SERVER['CONTENT_TYPE']??'')[0]))!=='application/json') throw new ApiError(415,'JSON_REQUIRED','Send JSON request data.');
        $limit=$action==='rider_progress'?750000:16384;
        if ((int)($_SERVER['CONTENT_LENGTH']??0)>$limit) throw new ApiError(413,'REQUEST_TOO_LARGE','Reduce request size.');
        $stream=fopen('php://input','rb');$raw=stream_get_contents($stream,$limit+1);fclose($stream);
        if (strlen($raw)>$limit) throw new ApiError(413,'REQUEST_TOO_LARGE','Reduce request size.');
        try { $body=json_decode($raw,true,16,JSON_THROW_ON_ERROR); } catch (JsonException $e) { throw new ApiError(400,'INVALID_JSON','Check request JSON.'); }
        if (!is_array($body)||!str_starts_with(ltrim($raw),'{')) throw new ApiError(400,'INVALID_JSON','Send a JSON object.');
        switch ($action) {
            case 'quote': $result=quote_checkout($db,$auth,$body);break;
            case 'checkout': $result=checkout($db,$auth,$body,$_SERVER['HTTP_IDEMPOTENCY_KEY']??'');$status=$result['replayed']?200:201;break;
            case 'rfq': $result=request_wholesale_quote($db,$auth,$body,$_SERVER['HTTP_IDEMPOTENCY_KEY']??'');$status=$result['replayed']?200:201;break;
            case 'dispatch_start': $result=dispatch_start($db,$auth,Maakit\Api\valid_uuid($body['order_id']??null));break;
            case 'dispatch_accept': $result=dispatch_accept($db,$auth,Maakit\Api\valid_uuid($body['attempt_id']??null));break;
            case 'rider_duty': if(isset($body['status'])&&!is_string($body['status']))throw new ApiError(400,'INVALID_STATUS','Choose online or offline.');$result=rider_duty($db,$auth,$body['status']??'online');break;
            case 'rider_progress': $result=rider_progress($db,$auth,$body);break;
            case 'order_decision': $result=order_decision($db,$auth,$body);break;
            case 'parcel': $result=dispatch_parcel($db,$auth,$body);break;
        }
    } elseif ($action==='dispatch_view') $result=dispatch_view($db,$auth);
    elseif ($action==='orders') {
        $rows=query($db,'SELECT * FROM mk_orders WHERE customer_id=? ORDER BY created_at DESC LIMIT 20',[$auth['user_id']])->fetchAll();$result=['orders'=>array_map('Maakit\\Api\\public_order',$rows)];
    } else {
        $owned=require_ownership($db,$auth,'order',$_GET['uuid']??null);$order=query($db,'SELECT * FROM mk_orders WHERE id=? AND customer_id=?',[$owned['id'],$auth['user_id']])->fetch();
        $shipment=query($db,'SELECT provider,provider_reference,accepted_at,dispatched_at FROM mk_shipments WHERE order_id=?',[$owned['id']])->fetch()?:null;
        $events=query($db,'SELECT status,created_at FROM mk_order_events WHERE order_id=? ORDER BY created_at LIMIT 50',[$owned['id']])->fetchAll();
        $result=['order'=>public_order($order),'shipment'=>$shipment,'events'=>$events];
    }
    http_response_code($status);echo json_data(['data'=>$result,'request_id'=>$requestId]);
} catch (ApiError $e) { http_response_code($e->status);echo json_data(['error'=>['code'=>$e->apiCode,'message'=>$e->getMessage()],'request_id'=>$requestId]); }
catch (Throwable $e) { error_log('Maakit API unavailable request='.$requestId.' class='.get_class($e));http_response_code(503);echo json_data(['error'=>['code'=>'TEMPORARILY_UNAVAILABLE','message'=>'Please try again shortly.'],'request_id'=>$requestId]); }

