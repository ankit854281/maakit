<?php
require_once __DIR__.'/../../inc/fn.php';
require_once __DIR__.'/../../controllers/partners.php';
header('Cache-Control: no-store');header('X-Content-Type-Options: nosniff');
$admin=Maakit\Api\query($pdo,"SELECT id FROM users WHERE id=? AND role='admin' AND active=1",[(int)(user()['id']??0)])->fetch();
if(!$admin){http_response_code(403);exit;}
$id=filter_var($_GET['id']??null,FILTER_VALIDATE_INT);
$f=$id?Maakit\Api\query($pdo,'SELECT * FROM mk_partner_documents WHERE id=?',[$id])->fetch():null;
if(!$f || !preg_match('/^[a-f0-9]{64}$/D',$f['storage_key'])){http_response_code(404);exit;}
try{$path=Maakit\Api\document_directory().'/'.$f['storage_key'];if(!is_file($path)||is_link($path))throw new RuntimeException();}
catch(Throwable $e){http_response_code(404);exit;}
$extensions=['image/jpeg'=>'jpg','image/png'=>'png','application/pdf'=>'pdf'];
if(!isset($extensions[$f['mime']])){http_response_code(404);exit;}
header('Content-Security-Policy: sandbox');header('Content-Type: '.$f['mime']);
header('Content-Disposition: attachment; filename="document-'.$f['id'].'.'.$extensions[$f['mime']].'"');
header('Content-Length: '.filesize($path));readfile($path);
