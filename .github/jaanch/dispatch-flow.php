<?php
require_once __DIR__.'/../../config.php';
if(DB_NAME!=='maakit_jaanch')throw new RuntimeException('CI database only');
require_once __DIR__.'/../../inc/db.php';
require_once __DIR__.'/../../inc/dispatch.php';
if(($argv[1]??'')==='worker'){dispatch_assign($pdo,(int)$argv[2]);exit;}
function dp_check($ok,$message){if(!$ok)throw new RuntimeException($message);}
$area=0;$blockedArea=0;$driver=0;$orders=[];$jar=tempnam(sys_get_temp_dir(),'mk-dispatch-');
function dp_http($path,$post=null,$expected=200){global $jar;$c=curl_init('http://127.0.0.1:8099'.$path);curl_setopt_array($c,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_COOKIEFILE=>$jar,CURLOPT_COOKIEJAR=>$jar,CURLOPT_TIMEOUT=>20,CURLOPT_PROXY=>'']);if($post!==null)curl_setopt_array($c,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>http_build_query($post)]);$html=curl_exec($c);$status=curl_getinfo($c,CURLINFO_HTTP_CODE);curl_close($c);dp_check($status===$expected&&is_string($html)&&!preg_match('/Fatal error|Warning:|Uncaught/',$html),'Dispatch HTTP failed '.$path);return $html;}
function dp_order($status='Confirm',$locality='CI Auto Area'){global $pdo,$orders;$no='CI-AUTO-'.bin2hex(random_bytes(4));$pdo->prepare("INSERT INTO orders(order_no,code,customer_name,mobile,village,items,status) VALUES (?,'1234','CI Auto','9000000055',?,'test goods',?)")->execute([$no,$locality,$status]);$id=(int)$pdo->lastInsertId();$orders[]=$id;return $id;}
try{
 $password=bin2hex(random_bytes(12));$pdo->prepare("INSERT INTO users(name,username,password,role) VALUES ('CI Auto Driver','jaanch-auto-driver',?,'delivery')")->execute([password_hash($password,PASSWORD_DEFAULT)]);$driver=(int)$pdo->lastInsertId();
 $pdo->exec("INSERT INTO villages(name,live) VALUES ('CI Auto Area',1)");$area=(int)$pdo->lastInsertId();$pdo->prepare("INSERT INTO service_area_meta(village_id,city,state,pincode,delivery_on) VALUES (?,'CI City','CI State','221403',1)")->execute([$area]);$pdo->prepare('INSERT INTO service_area_drivers VALUES (?,?)')->execute([$area,$driver]);$pdo->prepare('INSERT INTO service_area_dispatch VALUES (?,0)')->execute([$area]);
 $a=dp_order();$b=dp_order();$unconfirmed=dp_order('Naya');$cross=dp_order('Confirm','CI Other Area');$cancelled=dp_order('Cancel');
 dp_check(dispatch_assign($pdo,$a)===null,'Auto dispatch defaults off');
 $pdo->prepare('UPDATE service_area_dispatch SET enabled=1 WHERE village_id=?')->execute([$area]);dp_check(dispatch_assign($pdo,$a)===null,'No duty lease means no automatic assignment');
 dp_http('/delivery/',null,302);$html=dp_http('/login.php?as=team');preg_match('/name="csrf" value="([^"]+)"/',$html,$m);dp_http('/login.php?as=team',['csrf'=>$m[1],'do'=>'team','username'=>'jaanch-auto-driver','password'=>$password],302);
 $html=dp_http('/delivery/?lang=en');preg_match('/name="csrf" value="([^"]+)"/',$html,$m);
 dp_http('/delivery/',['csrf'=>'forged','status'=>'availability','available'=>'1']);dp_check(dispatch_assign($pdo,$a)===null,'Forged duty change rejected');
 dp_http('/delivery/',['csrf'=>$m[1],'status'=>'availability','available'=>'1'],302);
 $q=$pdo->prepare('SELECT status,delivery_user FROM orders WHERE id=?');$q->execute([$a]);$r=$q->fetch();dp_check($r['status']==='Assign'&&(int)$r['delivery_user']===$driver,'Duty activation assigns oldest confirmed area order');$q->execute([$b]);dp_check($q->fetch()['status']==='Confirm','Busy driver receives no second automatic order');
 foreach([$unconfirmed,$cross,$cancelled] as $id)dp_check(dispatch_assign($pdo,$id)===null,'Unconfirmed, cross-area and cancelled order excluded');dp_check(dispatch_assign($pdo,$a)===null,'Replay preserves assignment');
 $pdo->prepare("UPDATE orders SET status='Delivered' WHERE id=?")->execute([$a]);$pdo->prepare("UPDATE driver_availability SET available_until='2000-01-01' WHERE user_id=?")->execute([$driver]);dp_check(dispatch_assign($pdo,$b)===null,'Expired duty lease excluded');
 $pdo->prepare('UPDATE driver_availability SET available_until=UTC_TIMESTAMP()+INTERVAL 15 MINUTE WHERE user_id=?')->execute([$driver]);$pdo->prepare('UPDATE users SET active=0 WHERE id=?')->execute([$driver]);dp_check(dispatch_assign($pdo,$b)===null,'Inactive driver excluded');$pdo->prepare('UPDATE users SET active=1 WHERE id=?')->execute([$driver]);
 dp_check(dispatch_assign($pdo,$b)===$driver,'Fresh available driver receives confirmed order');
 dp_http('/delivery/',['csrf'=>$m[1],'status'=>'availability','available'=>'0'],302);$pdo->prepare("UPDATE orders SET status='Delivered' WHERE id=?")->execute([$b]);dp_check(dispatch_assign($pdo,dp_order())===null,'Stopping duty stops new automatic assignments');
 $pdo->prepare('UPDATE driver_availability SET available_until=UTC_TIMESTAMP()+INTERVAL 15 MINUTE WHERE user_id=?')->execute([$driver]);
 $race=[dp_order(),dp_order()];$workers=[];
 foreach($race as $id){$pipes=[];$proc=proc_open([PHP_BINARY,__FILE__,'worker',(string)$id],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);dp_check(is_resource($proc),'Worker started');fclose($pipes[0]);$workers[]=[$proc,$pipes];}
 foreach($workers as [$proc,$pipes]){$out=stream_get_contents($pipes[1]);$error=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);dp_check(proc_close($proc)===0,'Concurrent dispatch worker failed: '.$error);}
 $q=$pdo->prepare("SELECT COUNT(*) FROM orders WHERE id IN (?,?) AND status='Assign' AND delivery_user=?");$q->execute([$race[0],$race[1],$driver]);dp_check((int)$q->fetchColumn()===1,'Concurrent workers cannot double-load one driver');
 foreach($orders as $id)$pdo->prepare("UPDATE orders SET status='Delivered' WHERE id=? AND status IN ('Confirm','Assign')")->execute([$id]);
 $pdo->exec("INSERT INTO villages(name,live) VALUES ('CI Blocked Auto Area',1)");$blockedArea=(int)$pdo->lastInsertId();$pdo->prepare("INSERT INTO service_area_meta(village_id,city,state,pincode,delivery_on) VALUES (?,'CI','CI','221403',1)")->execute([$blockedArea]);$pdo->prepare('INSERT INTO service_area_dispatch VALUES (?,1)')->execute([$blockedArea]);
 for($i=0;$i<25;$i++)dp_order('Confirm','CI Blocked Auto Area');
 $ready=dp_order();dispatch_queue($pdo);$q=$pdo->prepare('SELECT delivery_user FROM orders WHERE id=?');$q->execute([$ready]);dp_check((int)$q->fetchColumn()===$driver,'Unavailable areas cannot starve ready queue behind twenty older orders');
 echo "Dispatch availability, ownership, queue, lease and concurrent allocation checks passed\n";
}finally{
 foreach($orders as $id)$pdo->prepare('DELETE FROM orders WHERE id=?')->execute([$id]);
 if($blockedArea){foreach(['service_area_meta','service_area_dispatch'] as $table)$pdo->prepare('DELETE FROM '.$table.' WHERE village_id=?')->execute([$blockedArea]);$pdo->prepare('DELETE FROM villages WHERE id=?')->execute([$blockedArea]);}
 if($area){foreach(['service_area_drivers','service_area_meta','service_area_dispatch'] as $table)$pdo->prepare('DELETE FROM '.$table.' WHERE village_id=?')->execute([$area]);$pdo->prepare('DELETE FROM villages WHERE id=?')->execute([$area]);}
 if($driver){$pdo->prepare('DELETE FROM driver_availability WHERE user_id=?')->execute([$driver]);$pdo->prepare('DELETE FROM users WHERE id=?')->execute([$driver]);}unlink($jar);
}
