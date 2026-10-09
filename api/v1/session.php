<?php
declare(strict_types=1);
require_once __DIR__.'/../../inc/fn.php';
require_once __DIR__.'/../../middleware/legacy.php';
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');header('X-Content-Type-Options: nosniff');
ini_set('display_errors','0');
try {
    if (($_SERVER['REQUEST_METHOD']??'')!=='POST') { header('Allow: POST');throw new Maakit\Api\ApiError(405,'METHOD_NOT_ALLOWED','Use POST.'); }
    $expected=$_SESSION['csrf']??'';$provided=$_SERVER['HTTP_X_CSRF_TOKEN']??'';
    if (!is_string($expected)||$expected===''||!is_string($provided)||$provided===''||!hash_equals($expected,$provided)) throw new Maakit\Api\ApiError(403,'CSRF_REQUIRED','Refresh the page and try again.');
    $context=$_POST['context']??'customer';if (!is_string($context)||!in_array($context,['customer','staff','shop'],true)) throw new Maakit\Api\ApiError(400,'INVALID_CONTEXT','Choose a valid login context.');
    $recent=$_SESSION['maakit_exchange_times']??[];$recent=array_values(array_filter($recent,static fn($t)=>is_int($t)&&$t>time()-60));
    if (count($recent)>=6) { header('Retry-After: 60');throw new Maakit\Api\ApiError(429,'RATE_LIMITED','Please wait a minute.'); }
    $recent[]=time();$_SESSION['maakit_exchange_times']=$recent;
    echo Maakit\Api\json_data(['data'=>Maakit\Api\bridge_session($pdo,$context)]);
} catch (Maakit\Api\ApiError $e) {
    if ($e->apiCode==='SESSION_REVOKED' && isset($context)) {
        try { Maakit\Api\revoke_legacy_sessions($pdo,$context); } catch (Throwable $ignored) {}
        if ($context==='customer') unset($_SESSION['cust']);if ($context==='staff') unset($_SESSION['user']);
        if ($context==='shop' && isset($_COOKIE['mk_shop'])) { $pdo->prepare('DELETE FROM shop_tokens WHERE token=?')->execute([$_COOKIE['mk_shop']]);setcookie('mk_shop','',['expires'=>time()-3600,'path'=>'/','httponly'=>true,'samesite'=>'Lax','secure'=>!empty($_SERVER['HTTPS'])]); }
    }
    http_response_code($e->status);echo Maakit\Api\json_data(['error'=>['code'=>$e->apiCode,'message'=>$e->getMessage()]]); }
catch (Throwable $e) { error_log('Maakit session bridge unavailable class='.get_class($e));http_response_code(503);echo '{"error":{"code":"SESSION_SETUP_REQUIRED","message":"Please try again shortly."}}'; }
