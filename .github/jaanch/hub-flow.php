<?php
// Isolated CI only. Exercise actual legacy HTTP login -> CSRF JWT exchange -> logout revocation.
require_once __DIR__.'/../../config.php';
if(DB_NAME!=='maakit_jaanch')throw new RuntimeException('CI only');
require_once __DIR__.'/../../inc/fn.php';
function hub_check($ok,$message){if(!$ok)throw new RuntimeException($message);}
$jar=tempnam(sys_get_temp_dir(),'mk-hub');$id=0;$fixtures=[];
function hub_fixture(PDO $db,string $table,array $data): string {
 $id=Maakit\Api\uuid4();$data=['id'=>$id]+$data;$columns=implode(',',array_keys($data));
 $db->prepare('INSERT INTO '.$table.' ('.$columns.') VALUES ('.implode(',',array_fill(0,count($data),'?')).')')->execute(array_values($data));
 $GLOBALS['fixtures'][]=[$table,$id];return $id;
}
function hub_http($path,$data=null,$expected=200,array $headers=[]){global $jar;$c=curl_init('http://127.0.0.1:8099'.$path);curl_setopt_array($c,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_COOKIEFILE=>$jar,CURLOPT_COOKIEJAR=>$jar,CURLOPT_TIMEOUT=>20,CURLOPT_PROXY=>'',CURLOPT_HTTPHEADER=>$headers]);if($data!==null)curl_setopt_array($c,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>http_build_query($data)]);$html=curl_exec($c);$status=curl_getinfo($c,CURLINFO_HTTP_CODE);curl_close($c);hub_check($status===$expected&&is_string($html)&&!preg_match('/Fatal error|Warning:|Uncaught|SQLSTATE/',$html),'HTTP '.$path.' expected '.$expected.' got '.$status);return $html;}
function hub_csrf($html){preg_match('/name="csrf" value="([^"]+)"/',$html,$m);hub_check(!empty($m[1]),'CSRF rendered');return html_entity_decode($m[1],ENT_QUOTES,'UTF-8');}
try {
 hub_check(substr_count(hub_http('/?lang=en'),'data-segment=')===3,'live entry homepage links all three segments');
 foreach(['LOCAL_SHOPPING','HOME_SERVICES','B2B'] as $segment){$html=hub_http('/public/index.php?segment='.$segment.'&lang=en');hub_check(str_contains($html,'data-context="'.$segment.'"'),'context preserved');hub_check(substr_count($html,'data-segment=')===3,'exactly three main segments');}
 // A committed supplier fixture must render through the hub's real shared PDO database.
 $owner=hub_fixture($pdo,'mk_users',['display_name'=>'CI Hub Supplier']);
 $vendor=hub_fixture($pdo,'mk_vendors',['owner_user_id'=>$owner,'legal_name'=>'CI Supplier','status'=>'ACTIVE']);
 $store=hub_fixture($pdo,'mk_stores',['vendor_id'=>$vendor,'name'=>'CI Supplier Store','address_json'=>'["CI"]','active'=>1]);
 $category=hub_fixture($pdo,'mk_categories',['name'=>'CI Wholesale Category','slug'=>'ci-hub-'.bin2hex(random_bytes(6)),'category_type'=>'B2B']);
 $product=hub_fixture($pdo,'mk_products',['category_id'=>$category,'name'=>'CI Wholesale Connected Product','brand'=>'CI Brand']);
 $offer=hub_fixture($pdo,'mk_offers',['product_id'=>$product,'store_id'=>$store,'is_b2b'=>1,'active'=>1]);
 $wholesalePath='/public/index.php?segment=B2B&q=Connected&type='.$category.'&lang=en';
 $html=hub_http($wholesalePath);hub_check(str_contains($html,'CI Wholesale Connected Product')&&str_contains($html,'data-offer="'.$offer.'"'),'B2B reads actual active offers from shared database');
 $pdo->prepare('UPDATE mk_stores SET active=0 WHERE id=?')->execute([$store]);
 hub_check(!str_contains(hub_http($wholesalePath),'data-offer="'.$offer.'"'),'inactive supplier store is excluded');
 $html=hub_http('/public/index.php?segment=HOME_SERVICES&q=Painting&lang=en');hub_check(str_contains($html,'Painting'),'service search');hub_check(!str_contains($html,'hub-rfq'),'service never routes to wholesale cart');
 $url='/sewa.php?s=mistri&lang=en&work='.rawurlencode('Painting|पुताई / पेंट');$html=hub_http($url);hub_check(str_contains($html,'value="Painting|पुताई / पेंट" selected'),'service card preselects work');
 $html=hub_http('/public/index.php?segment=B2B&q=%3Cscript%3E&lang=en');hub_check(!str_contains($html,'value="<script>"'),'search output escaped');
 $html=hub_http('/account.php?lang=en');$csrf=hub_csrf($html);
 hub_http('/api/v1/session.php',['context'=>'customer'],403);hub_http('/api/v1/session.php',['context'=>'customer'],401,['X-CSRF-Token: '.$csrf]);
 $password=bin2hex(random_bytes(16));$pdo->prepare('INSERT INTO customers(name,mobile,password,village,landmark) VALUES(?,?,?,?,?)')->execute(['CI Hub Customer','9000049999',password_hash($password,PASSWORD_DEFAULT),'','']);$id=(int)$pdo->lastInsertId();
 hub_http('/account.php',['csrf'=>$csrf,'do'=>'login','mobile'=>'9000049999','password'=>$password],302);
 $csrf=hub_csrf(hub_http('/account.php?lang=en'));
 $data=json_decode(hub_http('/api/v1/session.php',['context'=>'customer','legacy_id'=>'1','role'=>'ADMIN'],200,['X-CSRF-Token: '.$csrf]),true,32,JSON_THROW_ON_ERROR);
 hub_check(isset($data['data']['access_token']),'authenticated legacy login exchanges');$token=$data['data']['access_token'];
 $data=json_decode(hub_http('/api/v1/index.php?action=orders',null,200,['Authorization: Bearer '.$token]),true,32,JSON_THROW_ON_ERROR);hub_check(isset($data['data']['orders']),'token reaches secured API');
 hub_http('/api/v1/index.php?action=dispatch_view',null,403,['Authorization: Bearer '.$token]);
 hub_http('/account.php',['csrf'=>$csrf,'do'=>'logout'],302);
 hub_http('/api/v1/index.php?action=orders',null,401,['Authorization: Bearer '.$token]);
 echo "Hub context, service prefill, legacy login exchange, CSRF, RBAC and HTTP logout revocation passed\n";
} finally { foreach(array_reverse($fixtures) as [$table,$fixtureId])$pdo->prepare('DELETE FROM '.$table.' WHERE id=?')->execute([$fixtureId]);if($id)$pdo->prepare('DELETE FROM customers WHERE id=?')->execute([$id]);unlink($jar); }
