<?php
declare(strict_types=1);
use Maakit\Api\ApiError;
use function Maakit\Api\{database,authenticate,checkout,quote_checkout,require_ownership,query,public_order,json_data,uuid4};
require_once __DIR__.'/../../config/db.php';
require_once __DIR__.'/../../controllers/checkout.php';
ini_set('display_errors','0');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
$requestId=uuid4();header('X-Request-ID: '.$requestId);
try {
    if (PHP_INT_SIZE!==8) throw new RuntimeException('64-bit PHP required');
    $action=$_GET['action']??'';$method=$_SERVER['REQUEST_METHOD']??'GET';
    $routes=['quote'=>'POST','checkout'=>'POST','order'=>'GET'];
    if (!is_string($action) || !isset($routes[$action])) throw new ApiError(404,'NOT_FOUND','API endpoint not found.');
    if ($method!==$routes[$action]) { header('Allow: '.$routes[$action]);throw new ApiError(405,'METHOD_NOT_ALLOWED','Use the supported request method.'); }
    $db=database();
    // File must be outside public_html. Only public verification keys are read, never fetched remotely.
    $keyFile=getenv('MAAKIT_JWT_KEY_FILE')?: (defined('MAAKIT_JWT_KEY_FILE')?MAAKIT_JWT_KEY_FILE:'');
    $issuer=getenv('MAAKIT_JWT_ISSUER')?: (defined('MAAKIT_JWT_ISSUER')?MAAKIT_JWT_ISSUER:'');
    $audience=getenv('MAAKIT_JWT_AUDIENCE')?: (defined('MAAKIT_JWT_AUDIENCE')?MAAKIT_JWT_AUDIENCE:'');
    if (!is_string($keyFile) || !$keyFile || !is_readable($keyFile) || filesize($keyFile)>65536) throw new RuntimeException('JWT keys unavailable');
    $resolvedKey=realpath($keyFile);$publicRoot=realpath(dirname(__DIR__,2));
    if (!$resolvedKey || !$publicRoot || str_starts_with($resolvedKey,$publicRoot.DIRECTORY_SEPARATOR)) throw new RuntimeException('Verification keys must be outside public root');
    $keys=json_decode(file_get_contents($resolvedKey),true,8,JSON_THROW_ON_ERROR);
    if (!is_array($keys)) throw new RuntimeException('JWT keys invalid');
    $auth=authenticate($db,$_SERVER['HTTP_AUTHORIZATION']??'', $keys,$issuer,$audience);
    $status=200;
    if ($method==='POST') {
        if (strtolower(trim(explode(';',$_SERVER['CONTENT_TYPE']??'')[0]))!=='application/json') throw new ApiError(415,'JSON_REQUIRED','Send JSON request data.');
        if ((int)($_SERVER['CONTENT_LENGTH']??0)>16384) throw new ApiError(413,'REQUEST_TOO_LARGE','Reduce request size.');
        $stream=fopen('php://input','rb');$raw=stream_get_contents($stream,16385);fclose($stream);
        if (strlen($raw)>16384) throw new ApiError(413,'REQUEST_TOO_LARGE','Reduce request size.');
        try { $body=json_decode($raw,true,16,JSON_THROW_ON_ERROR); } catch (JsonException $e) { throw new ApiError(400,'INVALID_JSON','Check request JSON.'); }
        if (!is_array($body) || !str_starts_with(ltrim($raw),'{')) throw new ApiError(400,'INVALID_JSON','Send a JSON object.');
        if ($action==='quote') $result=quote_checkout($db,$auth,$body);
        else { $result=checkout($db,$auth,$body,$_SERVER['HTTP_IDEMPOTENCY_KEY']??'');$status=$result['replayed']?200:201; }
    } else {
        require_ownership($db,$auth,'order',$_GET['uuid']??null);
        $result=['order'=>public_order(query($db,'SELECT * FROM mk_orders WHERE id=? AND customer_id=?',[$_GET['uuid'],$auth['user_id']])->fetch())];
    }
    http_response_code($status);echo json_data(['data'=>$result,'request_id'=>$requestId]);
} catch (ApiError $e) {
    http_response_code($e->status);echo json_data(['error'=>['code'=>$e->apiCode,'message'=>$e->getMessage()],'request_id'=>$requestId]);
} catch (Throwable $e) {
    // Avoid logging SQL bindings, DB credentials, tokens, phone/address data or raw exception text.
    error_log('Maakit API unavailable request='.$requestId.' class='.get_class($e));
    http_response_code(503);echo json_data(['error'=>['code'=>'TEMPORARILY_UNAVAILABLE','message'=>'Please try again shortly.'],'request_id'=>$requestId]);
}
