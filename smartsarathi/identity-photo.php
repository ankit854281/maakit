<?php
require_once __DIR__.'/inc/fn.php';
require_once __DIR__.'/inc/identity.php';
header('Cache-Control: private, no-store');
$a=sr_account($pdo); $id=(int)get('id');
if(!$a || ((int)$a['id']!==$id && !sr_identity_admin($pdo))){http_response_code(404);exit;}
$p=sr_profile($pdo,$id);$kind=get('kind');
$cols=['photo'=>'photo','licence'=>'licence_photo','dl_back'=>'dl_back','aadhaar_front'=>'aadhaar_front','aadhaar_back'=>'aadhaar_back','pan'=>'pan_photo','rc'=>'rc_photo'];
if(!$p || !is_string($kind) || !isset($cols[$kind])){http_response_code(404);exit;}
$name=(string)($p[$cols[$kind]] ?? '');
if(!preg_match('/^[a-f0-9]{48}\.jpg$/D',$name)){http_response_code(404);exit;}
$path=sr_identity_dir().'/'.$name;
if(!is_file($path)){http_response_code(404);exit;}
order_media_stream($path);
