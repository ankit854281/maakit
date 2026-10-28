<?php
require_once __DIR__.'/inc/fn.php';
require_once __DIR__.'/inc/identity.php';
header('Cache-Control: private, no-store');
$a=sr_account($pdo); $id=(int)get('id');
if(!$a || ((int)$a['id']!==$id && !sr_identity_admin($pdo))){http_response_code(404);exit;}
$p=sr_profile($pdo,$id);$kind=get('kind');
if(!$p || !in_array($kind,['photo','licence'],true)){http_response_code(404);exit;}
$name=$p[$kind==='photo'?'photo':'licence_photo'];
if(!preg_match('/^[a-f0-9]{48}\.jpg$/D',$name)){http_response_code(404);exit;}
$path=sr_identity_dir().'/'.$name;
if(!is_file($path)){http_response_code(404);exit;}
order_media_stream($path);
