<?php
// Fixed local CI target and bounded test: never benchmark the live shared host.
require_once __DIR__.'/../../config.php';
if(DB_NAME!=='maakit_jaanch')throw new RuntimeException('CI database only');
require_once __DIR__.'/../../inc/db.php';
function tf_check($ok,$message){if(!$ok)throw new RuntimeException($message);}
$multi=null;$handles=[];$latencies=[];$start=microtime(true);
try{
 $pdo->beginTransaction();$insert=$pdo->prepare("INSERT INTO orders(order_no,code,customer_name,mobile,village,items,status,delivery_charge,goods_amount) VALUES (?,'1234','CI Traffic','9000000033','CI Traffic Area','CI goods','Delivered',0,0)");for($i=0;$i<1000;$i++)$insert->execute(['CI-LOAD-'.str_pad((string)$i,4,'0',STR_PAD_LEFT)]);$pdo->commit();
 $count=(int)$pdo->query('SELECT COUNT(*) FROM orders')->fetchColumn();$bookings=(int)$pdo->query('SELECT COUNT(*) FROM service_bookings')->fetchColumn();
 $pdo->query('ANALYZE TABLE orders')->fetchAll();
 $plan=$pdo->query("EXPLAIN SELECT id FROM orders WHERE delivery_user=999999 AND status IN ('Naya','Confirm','Assign','Pickup') LIMIT 1")->fetch();tf_check(strpos($plan['possible_keys']??'','idx_driver_workload')!==false,'Driver workload index available with volume');
 $paths=['/?lang=en','/location.php?lang=en','/bazaar.php?lang=en','/search.php?q=atta&lang=en','/track.php?no=CI-LOAD-0000&m=9000000033&lang=en'];
 $total=160;$width=8;
 for($batch=0;$batch<$total;$batch+=$width){
  $multi=curl_multi_init();$handles=[];
  for($i=$batch;$i<min($batch+$width,$total);$i++){$c=curl_init('http://127.0.0.1:8099'.$paths[$i%count($paths)]);curl_setopt_array($c,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>15,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_PROXY=>'']);curl_multi_add_handle($multi,$c);$handles[]=$c;}
  do{$code=curl_multi_exec($multi,$running);tf_check($code===CURLM_OK,'Multi-request execution failed');if($running)curl_multi_select($multi,0.5);tf_check(microtime(true)-$start<60,'CI traffic test exceeded one minute');}while($running);
  foreach($handles as $c){$html=curl_multi_getcontent($c);tf_check(curl_errno($c)===0&&curl_getinfo($c,CURLINFO_HTTP_CODE)===200&&strpos($html,'Maakit')!==false&&!preg_match('/Fatal error|Warning:|Uncaught|SQLSTATE/',$html),'Concurrent page failed');$latencies[]=curl_getinfo($c,CURLINFO_TOTAL_TIME);curl_multi_remove_handle($multi,$c);curl_close($c);}
  $handles=[];curl_multi_close($multi);$multi=null;
 }
 tf_check((int)$pdo->query('SELECT COUNT(*) FROM orders')->fetchColumn()===$count&&(int)$pdo->query('SELECT COUNT(*) FROM service_bookings')->fetchColumn()===$bookings,'Read traffic creates no orders/bookings');
 sort($latencies);$p95=$latencies[(int)ceil(count($latencies)*0.95)-1];
 printf("CI traffic: 1000 order fixtures, %d successful requests, %d concurrently queued against 4 PHP workers; p95 %.0f ms; no order/booking side effects. This is not production capacity certification.\n",count($latencies),$width,$p95*1000);
}finally{
 if($multi){foreach($handles as $c){curl_multi_remove_handle($multi,$c);curl_close($c);}curl_multi_close($multi);}
 if($pdo->inTransaction())$pdo->rollBack();
 $pdo->exec("DELETE FROM orders WHERE mobile='9000000033' AND order_no LIKE 'CI-LOAD-%'");
}
