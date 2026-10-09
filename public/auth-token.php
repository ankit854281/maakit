<?php
/** Same-origin session-to-JWT bridge. Never accept user IDs or roles from the browser. */
require_once __DIR__.'/../inc/fn.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');header('X-Content-Type-Options: nosniff');
if($_SERVER['REQUEST_METHOD']!=='POST'){header('Allow: POST');http_response_code(405);echo json_encode(['error'=>'Method not allowed']);exit;}
if(!csrf_ok()){http_response_code(403);echo json_encode(['error'=>'Invalid CSRF token']);exit;}
$context=is_string($_POST['context']??null)?$_POST['context']:'';
if(!in_array($context,['customer','staff','shop'],true)){http_response_code(400);echo json_encode(['error'=>'Invalid context']);exit;}
try {
 $result=Maakit\Api\bridge_session($pdo,$context);
 echo json_encode($result,JSON_THROW_ON_ERROR);
}catch(Maakit\Api\ApiError $e){http_response_code(401);echo json_encode(['error'=>'Authentication required']);}
catch(Throwable $e){error_log('JWT bridge failed: '.$e->getMessage());http_response_code(503);echo json_encode(['error'=>'Authentication temporarily unavailable']);}
