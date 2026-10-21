<?php
require_once __DIR__.'/inc/fn.php';
require_once __DIR__.'/inc/kaam.php';
require_once __DIR__.'/inc/client.php';
header('Cache-Control: private, no-store, max-age=0');
$name=$_GET['file'] ?? null;
if(!order_media_name($name) || !str_starts_with($name,'pod-')) {http_response_code(404);exit;}
$q=$pdo->prepare('SELECT rider_id,client_id FROM jobs WHERE pod_photo=? LIMIT 1');$q->execute([$name]);$job=$q->fetch();
$allowed=false;
if($job){
    $rider=rider_me($pdo);
    $allowed=!empty($_SESSION['sr_admin']) || ($rider && (int)$rider['id']===(int)$job['rider_id']);
    if(!$allowed){$key=key_from_request();$client=$key!=='' ? key_client($pdo,$key) : null;$allowed=$client && (int)$client['id']===(int)$job['client_id'];}
}
$file=$allowed ? order_media_path($name,'sarathi') : null;
if(!$file){http_response_code(404);exit;}
order_media_stream($file);
