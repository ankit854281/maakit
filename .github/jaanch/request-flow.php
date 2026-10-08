<?php
// Disposable local CI only: cart, saved requests, stale approval and staff guards.
require_once __DIR__.'/../../config.php';
if(DB_NAME!=='maakit_jaanch')throw new RuntimeException('CI only');
require_once __DIR__.'/../../inc/fn.php';
require_once __DIR__.'/../../inc/order-quotes.php';
require_once __DIR__.'/../../inc/dispatch.php';
function rq_check($ok,$message){if(!$ok)throw new RuntimeException($message);}
$jar=tempnam(sys_get_temp_dir(),'mk-request');$staff=0;$driver=0;$oid=0;$vid=0;
function rq_http($path,$data=null,$expected=200){global $jar;$c=curl_init('http://127.0.0.1:8099'.$path);curl_setopt_array($c,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_COOKIEFILE=>$jar,CURLOPT_COOKIEJAR=>$jar,CURLOPT_TIMEOUT=>20,CURLOPT_PROXY=>'']);if($data!==null)curl_setopt_array($c,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>http_build_query($data)]);$html=curl_exec($c);$status=curl_getinfo($c,CURLINFO_HTTP_CODE);curl_close($c);rq_check($status===$expected&&is_string($html)&&!preg_match('/Fatal error|Warning:|Uncaught|SQLSTATE/',$html),'Request HTTP '.$path);return $html;}
function rq_token($html,$name='csrf'){preg_match('/name="'.preg_quote($name,'/').'" value="([^"]+)"/',$html,$m);rq_check(!empty($m[1]),'Missing '.$name);return html_entity_decode($m[1],ENT_QUOTES,'UTF-8');}
try{
 $html=rq_http('/bazaar.php?q=Tata+Salt&lang=en');$csrf=rq_token($html);
 $add=['csrf'=>$csrf,'request_action'=>'add','catalog_id'=>'1666','qty'=>'2','pack'=>'1 kg','urgent'=>'1'];
 rq_http('/bazaar.php?q=Tata+Salt&lang=en',$add,302);
 $html=rq_http('/bazaar.php?type=Hardware+Shop&lang=en');rq_check(strpos($html,'catalogue-request-cart')!==false&&strpos($html,'Tata Salt')!==false,'Cross-category cart persistence');
 rq_http('/bazaar.php?lang=en',array_merge($add,['catalog_id'=>'1517','qty'=>'1','pack'=>'5 kg','urgent'=>'0']),302);
 $service=(int)$pdo->query('SELECT id FROM catalog_items WHERE is_sewa=1 LIMIT 1')->fetchColumn();
 rq_http('/bazaar.php?lang=en',array_merge($add,['catalog_id'=>$service]),302);
 $html=rq_http('/order.php?request_cart=1&lang=en');$key=rq_token($html,'submit_key');
 preg_match('/<textarea id="note"[^>]*>(.*?)<\/textarea>/s',$html,$m);$note=html_entity_decode($m[1]??'',ENT_QUOTES,'UTF-8');
 rq_check(strpos($note,'Tata Salt')!==false&&strpos($note,'Aashirvaad')!==false&&strpos($note,'2 × 1 kg')!==false,'Exact selected request lines');
 $pdo->exec("INSERT INTO villages(name,live) VALUES ('CI Request Area',1)");$vid=(int)$pdo->lastInsertId();
 $pdo->prepare("INSERT INTO service_area_meta(village_id,delivery_on,booking_on,base_fee) VALUES (?,1,0,15)")->execute([$vid]);
 $post=['csrf'=>$csrf,'submit_key'=>$key,'request_cart'=>'1','name'=>'CI Request Customer','mobile'=>'9000000088','village'=>'CI Request Area','note'=>$note,'market'=>'local','payment'=>'डिलीवरी पर कैश','cart_json'=>'[]'];
 rq_http('/order.php?lang=en',$post);$o=$pdo->query("SELECT * FROM orders WHERE mobile='9000000088' ORDER BY id DESC LIMIT 1")->fetch();$oid=(int)($o['id']??0);
 rq_check($oid>0&&$o['items']===$note&&$o['goods_amount']===null,'Request persists without guessed retail price');rq_check(order_quote($pdo,$oid)['state']==='draft','Quote initially waits for team');
 rq_http('/order.php?lang=en',$post);rq_check((int)$pdo->query("SELECT COUNT(*) FROM orders WHERE mobile='9000000088'")->fetchColumn()===1,'Double submit is idempotent');
 rq_check(strpos(rq_http('/bazaar.php?lang=en'),'catalogue-request-cart')===false,'Submitted cart cleared');
 $password=bin2hex(random_bytes(12));$pdo->prepare("INSERT INTO users(name,username,password,role) VALUES ('CI Quote Staff','ci-quote-staff',?,'bpo')")->execute([password_hash($password,PASSWORD_DEFAULT)]);$staff=(int)$pdo->lastInsertId();
 $pdo->prepare("INSERT INTO users(name,username,password,role) VALUES ('CI Quote Driver','ci-quote-driver',?,'delivery')")->execute([password_hash($password,PASSWORD_DEFAULT)]);$driver=(int)$pdo->lastInsertId();$pdo->prepare('INSERT INTO service_area_drivers(village_id,user_id) VALUES (?,?)')->execute([$vid,$driver]);
 $html=rq_http('/login.php?as=team');rq_http('/login.php?as=team',['csrf'=>rq_token($html),'do'=>'team','username'=>'ci-quote-staff','password'=>$password],302);
 $html=rq_http('/bpo/?lang=en');$token=rq_token($html);rq_check(strpos($html,'Customer price approval')!==false,'Staff quote controls wired');
 rq_http('/bpo/',['csrf'=>$token,'do'=>'status','id'=>$oid,'status'=>'Confirm'],302);rq_check($pdo->query('SELECT status FROM orders WHERE id='.$oid)->fetchColumn()==='Naya','Staff cannot bypass customer approval');
 rq_http('/bpo/',['csrf'=>$token,'do'=>'amount','id'=>$oid,'goods_amount'=>'1','delivery_charge'=>'0','payment'=>'डिलीवरी पर कैश'],302);rq_check($pdo->query('SELECT goods_amount FROM orders WHERE id='.$oid)->fetchColumn()===null,'Old amount form cannot bypass quote');
 $quote=['csrf'=>$token,'do'=>'quote','id'=>$oid,'goods_amount'=>'310','delivery_charge'=>'15','shop_details'=>'CI shop one / CI shop two','delivery_time'=>'Today after customer approval','details'=>'Salt 2 packs 56; atta 254'];
 rq_http('/bpo/',$quote,302);$q=order_quote($pdo,$oid);rq_check($q['state']==='ready'&&(int)$q['revision']===1,'Staff publishes first quote');rq_check(dispatch_assign($pdo,$oid,$driver)===null,'Manual driver assignment blocked until acceptance');
 $url='/track.php?lang=en&no='.urlencode($o['order_no']).'&m=9000000088';$html=rq_http($url);$ct=rq_token($html);file_put_contents('/tmp/catalogue-quote-preview.html',$html);rq_check(strpos($html,'Accept these prices')!==false&&strpos($html,'₹325')!==false,'Customer sees exact goods plus delivery');
 $reply=['csrf'=>$ct,'no'=>$o['order_no'],'m'=>'9000000088','revision'=>'1','act'=>'quote_accept'];
 rq_http($url,array_merge($reply,['csrf'=>'forged']));rq_check(order_quote($pdo,$oid)['state']==='ready','Invalid CSRF rejected');
 rq_check(!quote_reply($pdo,$oid,'9000000000',1,true),'Wrong customer rejected');
 rq_http('/bpo/',array_merge($quote,['goods_amount'=>'320']),302);rq_check((int)order_quote($pdo,$oid)['revision']===2,'Changed quote has new revision');
 rq_http($url,$reply,302);rq_check(order_quote($pdo,$oid)['state']==='ready','Stale browser acceptance rejected');
 rq_http($url,array_merge($reply,['revision'=>'2','act'=>'quote_reject']),302);rq_check(order_quote($pdo,$oid)['state']==='rejected','Customer can ask for changes');
 rq_http('/bpo/',array_merge($quote,['goods_amount'=>'300']),302);
 rq_http($url,array_merge($reply,['revision'=>'3']),302);rq_check(order_quote($pdo,$oid)['state']==='accepted','Latest quote accepted');
 rq_check(!quote_reply($pdo,$oid,'9000000088',3,true),'Acceptance replay cannot change state');
 rq_check(dispatch_assign($pdo,$oid,$driver)===$driver,'Approved matching quote allows assignment');
 rq_check(!quote_publish($pdo,$oid,400,15,'CI changed shop','later','changed'),'Assigned quote cannot silently change');
 $pdo->prepare('UPDATE orders SET goods_amount=999 WHERE id=?')->execute([$oid]);$guard=$pdo->prepare('SELECT id FROM orders WHERE id=? AND '.quote_guard());$guard->execute([$oid]);rq_check(!$guard->fetch(),'Changed amount invalidates fulfilment guard');
 echo "Catalogue cart, sourcing requests, quote revisions, customer approval and fulfilment guards passed\n";
}finally{
 if($oid)$pdo->prepare('DELETE FROM orders WHERE id=?')->execute([$oid]);
 if($vid){$pdo->prepare('DELETE FROM service_area_drivers WHERE village_id=?')->execute([$vid]);$pdo->prepare('DELETE FROM service_area_meta WHERE village_id=?')->execute([$vid]);$pdo->prepare('DELETE FROM villages WHERE id=?')->execute([$vid]);}
 foreach([$staff,$driver] as $id)if($id)$pdo->prepare('DELETE FROM users WHERE id=?')->execute([$id]);unlink($jar);
}
