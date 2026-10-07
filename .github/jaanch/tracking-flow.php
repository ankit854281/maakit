<?php
require_once __DIR__.'/../../config.php';
if(DB_NAME!=='maakit_jaanch')throw new RuntimeException('CI only');
require_once __DIR__.'/../../inc/db.php';
function gp_check($ok,$message){if(!$ok)throw new RuntimeException($message);}
$jar=tempnam(sys_get_temp_dir(),'mk-gps-');$uid=0;$oid=0;
function gp_http($path,$post=null,$expected=200){global $jar;$c=curl_init('http://127.0.0.1:8099'.$path);curl_setopt_array($c,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_COOKIEFILE=>$jar,CURLOPT_COOKIEJAR=>$jar,CURLOPT_TIMEOUT=>20,CURLOPT_PROXY=>'']);if($post!==null)curl_setopt_array($c,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>http_build_query($post)]);$html=curl_exec($c);$status=curl_getinfo($c,CURLINFO_HTTP_CODE);curl_close($c);gp_check($status===$expected&&is_string($html)&&!preg_match('/Fatal error|Warning:|Uncaught/',$html),'GPS HTTP failed '.$path);return $html;}
try{
 $password=bin2hex(random_bytes(12));$pdo->prepare("INSERT INTO users(name,username,password,role) VALUES ('CI GPS Driver','jaanch-gps-driver',?,'delivery')")->execute([password_hash($password,PASSWORD_DEFAULT)]);$uid=(int)$pdo->lastInsertId();
 $pdo->prepare("INSERT INTO orders(order_no,code,customer_name,mobile,village,items,status,delivery_user) VALUES ('CI-GPS','1234','CI GPS','9000000044','CI GPS Area','goods','Assign',?)")->execute([$uid]);$oid=(int)$pdo->lastInsertId();
 $start=['a'=>'start_tracking','o'=>$oid];gp_http('/api.php',$start,403);
 $html=gp_http('/login.php?as=team');preg_match('/name="csrf" value="([^"]+)"/',$html,$m);gp_http('/login.php?as=team',['csrf'=>$m[1],'do'=>'team','username'=>'jaanch-gps-driver','password'=>$password],302);
 $html=gp_http('/delivery/');preg_match('/name="csrf" value="([^"]+)"/',$html,$m);$start['csrf']=$m[1];$bad=$start;$bad['csrf']='forged';gp_http('/api.php',$bad,403);
 $pdo->prepare('UPDATE orders SET delivery_user=NULL WHERE id=?')->execute([$oid]);gp_http('/api.php',$start,403);$pdo->prepare('UPDATE orders SET delivery_user=? WHERE id=?')->execute([$uid,$oid]);
 $reply=json_decode(gp_http('/api.php',$start),true);$token=$reply['token'];
 $where='/api.php?a=where&no=CI-GPS&m=9000000044';$r=json_decode(gp_http($where),true);gp_check(!$r['live']&&$r['lat']===null,'Consent alone exposes no placeholder position');
 $ping=['a'=>'ping','csrf'=>$m[1],'o'=>$oid,'token'=>$token,'lat'=>'27.1','lng'=>'82.2','acc'=>'10','at'=>(string)(time()-30)];gp_http('/api.php',$ping);
 $r=json_decode(gp_http($where),true);gp_check($r['live']&&$r['age']>=25&&abs($r['lat']-27.1)<0.00001,'Fresh captured location visible with real sample age');
 $bad=$ping;$bad['at']=(string)(time()-50);$bad['lat']='28.0';gp_http('/api.php',$bad);$r=json_decode(gp_http($where),true);gp_check(abs($r['lat']-27.1)<0.00001,'Out-of-order sample cannot overwrite latest location');
 foreach([['at'=>(string)(time()-200)],['at'=>(string)(time()+60)],['lat'=>'1e309'],['lat'=>['bad']],['acc'=>'-1']] as $invalid)gp_http('/api.php',array_merge($ping,$invalid),400);
 gp_http('/api.php?a=where&no=CI-GPS&m=9000000045',null,404);
 $stop=['a'=>'stop_tracking','csrf'=>$m[1],'o'=>$oid,'token'=>$token];gp_http('/api.php',$stop);gp_http('/api.php',$ping,403);$r=json_decode(gp_http($where),true);gp_check(!$r['live']&&$r['lat']===null,'Stop removes location and rejects late ping');
 $reply=json_decode(gp_http('/api.php',$start),true);$newtoken=$reply['token'];gp_check($newtoken!==$token,'Restart gets new consent identity');gp_http('/api.php',$stop);$ping['token']=$newtoken;$ping['at']=(string)time();gp_http('/api.php',$ping);gp_check(json_decode(gp_http($where),true)['live'],'Old stop cannot erase new sharing session');
 $pdo->prepare("UPDATE live_tracks SET captured_at=UTC_TIMESTAMP()-INTERVAL 4 MINUTE,updated_at=NOW() WHERE order_id=?")->execute([$oid]);$r=json_decode(gp_http($where),true);gp_check(!$r['live']&&$r['lat']===null,'Recent upload does not freshen stale GPS capture');
 $pdo->prepare("UPDATE orders SET status='Cancel' WHERE id=?")->execute([$oid]);gp_http('/api.php',$ping,403);gp_check(!json_decode(gp_http($where),true)['live'],'Cancelled fulfilment never shares location');
 echo "GPS consent, CSRF, ownership, sample freshness, out-of-order and stop/restart race checks passed\n";
}finally{if($oid){$pdo->prepare('DELETE FROM live_tracks WHERE order_id=?')->execute([$oid]);$pdo->prepare('DELETE FROM orders WHERE id=?')->execute([$oid]);}if($uid)$pdo->prepare('DELETE FROM users WHERE id=?')->execute([$uid]);unlink($jar);}
