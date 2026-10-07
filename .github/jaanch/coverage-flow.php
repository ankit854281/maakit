<?php
require_once __DIR__.'/../../config.php';
if(DB_NAME!=='maakit_jaanch')throw new RuntimeException('CI only');
require_once __DIR__.'/../../inc/fn.php';
function cv_check($ok,$msg){if(!$ok)throw new RuntimeException($msg);}
$cookie=tempnam(sys_get_temp_dir(),'mk-coverage');$id=0;
function cv_request($path,$post=null,$status=200){global $cookie;$ch=curl_init('http://127.0.0.1:8099'.$path);curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_COOKIEJAR=>$cookie,CURLOPT_COOKIEFILE=>$cookie,CURLOPT_TIMEOUT=>20,CURLOPT_PROXY=>'']);if($post!==null)curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>http_build_query($post)]);$html=curl_exec($ch);$code=curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);cv_check($html!==false&&$code===$status,'HTTP failed '.$path);cv_check(!preg_match('/Fatal error|Warning:|Uncaught/',$html),'PHP page error');return $html;}
try{
 $pdo->prepare('INSERT INTO villages(name,live) VALUES (?,0)')->execute(['CI Paused Area']);$id=(int)$pdo->lastInsertId();
 $pdo->prepare('INSERT INTO service_area_meta(village_id,city,state,pincode,delivery_on,booking_on,base_fee) VALUES (?,"CI City","CI State","221403",1,0,0)')->execute([$id]);
 $html=cv_request('/order.php?lang=en');preg_match('/name="csrf" value="([^"]+)"/',$html,$m);cv_check(!empty($m[1]),'CSRF token');
 $before=(int)$pdo->query("SELECT COUNT(*) FROM orders WHERE mobile='9000000099'")->fetchColumn();
 foreach(['CI Paused Area','Forged Area'] as $area){$html=cv_request('/order.php?lang=en',['csrf'=>$m[1],'name'=>'CI Customer','mobile'=>'9000000099','village'=>$area,'note'=>'test product','market'=>'local','payment'=>'cash']);cv_check(strpos($html,'Delivery is not available')!==false,'Reject unavailable area');}
 cv_check((int)$pdo->query("SELECT COUNT(*) FROM orders WHERE mobile='9000000099'")->fetchColumn()===$before,'Rejected request writes no order');
 $pdo->prepare('UPDATE villages SET live=1 WHERE id=?')->execute([$id]);$a=coverage_area($pdo,'CI Paused Area');$c=calc_charge('CI Paused Area','local','0','0',$pdo,true);cv_check($c['total']===0,'Explicit zero fee is known');
 cv_check(!coverage_enabled($a,'booking'),'Separate booking switch');
 $html=cv_request('/sewa.php?s=gaadi&lang=en',['csrf'=>$m[1],'name'=>'CI Customer','mobile'=>'9000000099','village'=>'CI Paused Area']);cv_check(strpos($html,'Bookings are not available')!==false,'Reject booking in delivery-only area');
 $html=cv_request('/location.php?lang=en');cv_check(strpos($html,'CI City')!==false,'Location includes city');cv_request('/location.php',['csrf'=>$m[1],'area'=>'CI Paused Area'],302);
 $html=cv_request('/?lang=en');cv_check(strpos($html,'CI City')!==false,'Chosen location shown');cv_check(strpos($html,'noindex,follow')!==false,'Scoped home is noindex');
 $html=cv_request('/support.php?lang=en');cv_check(strpos($html,'Sign in to send')!==false,'Guest sees support contacts, no private tickets');
 cv_request('/admin/coverage.php',null,302);cv_request('/admin/support.php',null,302);
 echo "Coverage HTTP checks passed\n";
}finally{if($id){$pdo->prepare('DELETE FROM service_area_meta WHERE village_id=?')->execute([$id]);$pdo->prepare('DELETE FROM villages WHERE id=?')->execute([$id]);}unlink($cookie);}
