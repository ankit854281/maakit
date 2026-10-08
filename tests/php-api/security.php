<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') { http_response_code(404);exit; }
require_once __DIR__.'/../../controllers/checkout.php';
require_once __DIR__.'/../../config/db.php';
use Maakit\Api\ApiError;
use function Maakit\Api\{uuid4,valid_uuid,money,jwt_claims,authenticate,live_identity,require_permission,require_roles,require_ownership,checkout,quote_checkout,query,database};
function check(bool $condition,string $label): void { if (!$condition) throw new RuntimeException($label);echo "PASS $label\n"; }
function rejected(callable $fn,string $code): void {
    try { $fn(); } catch (ApiError $e) { check($e->apiCode===$code,'reject '.$code);return; }
    throw new RuntimeException('Expected '.$code);
}
function enc(string $s): string { return rtrim(strtr(base64_encode($s),'+/','-_'),'='); }
function sign_token(array $claims,$private,array $header=[]): string {
    $h=enc(json_encode($header?:['alg'=>'RS256','typ'=>'JWT','kid'=>'test'],JSON_THROW_ON_ERROR));$c=enc(json_encode($claims,JSON_THROW_ON_ERROR));
    openssl_sign($h.'.'.$c,$signature,$private,OPENSSL_ALGO_SHA256);return 'Bearer '.$h.'.'.$c.'.'.enc($signature);
}
$private=openssl_pkey_new(['private_key_bits'=>2048,'private_key_type'=>OPENSSL_KEYTYPE_RSA]);
$public=openssl_pkey_get_details($private)['key'];$keys=['test'=>$public];$now=time();
$claims=['iss'=>'maakit-test','aud'=>'maakit-client','sub'=>uuid4(),'sid'=>uuid4(),'jti'=>uuid4(),'iat'=>$now,'exp'=>$now+900,'session_version'=>0];
check(jwt_claims(sign_token($claims,$private),$keys,'maakit-test','maakit-client')['sub']===$claims['sub'],'valid signed JWT');
foreach ([['exp'=>$now-10,'iat'=>$now-100],['iss'=>'wrong'],['aud'=>'wrong'],['exp'=>$now+901],['iat'=>'bad'],['sub'=>'1'],['session_version'=>-1],['nbf'=>$now+60]] as $bad) rejected(fn()=>jwt_claims(sign_token(array_replace($claims,$bad),$private),$keys,'maakit-test','maakit-client'),'UNAUTHENTICATED');
foreach ([['alg'=>'none','typ'=>'JWT','kid'=>'test'],['alg'=>'HS256','typ'=>'JWT','kid'=>'test'],['alg'=>'RS256','typ'=>'JWT','kid'=>'unknown'],['alg'=>'RS256','typ'=>'JWT','kid'=>'test','jku'=>'https://example.test']] as $h) rejected(fn()=>jwt_claims(sign_token($claims,$private,$h),$keys,'maakit-test','maakit-client'),'UNAUTHENTICATED');
$other=openssl_pkey_new(['private_key_bits'=>2048,'private_key_type'=>OPENSSL_KEYTYPE_RSA]);rejected(fn()=>jwt_claims(sign_token($claims,$other),$keys,'maakit-test','maakit-client'),'UNAUTHENTICATED');
rejected(fn()=>valid_uuid('00000000-0000-1000-8000-000000000001'),'INVALID_ID');
check(valid_uuid(uuid4())!=='' && money('15000')===15000,'UUID v4 and integer paise');
rejected(fn()=>money(150.5),'INVALID_PRICE');
check(Maakit\Api\haversine_m(25,82,25,82)===0.0,'zero distance');
rejected(fn()=>require_permission(['permissions'=>[]],'FINANCE_READ'),'FORBIDDEN');
if (getenv('MAAKIT_DB_NAME')===false) { echo "JWT/unit tests finished; DB integration not requested.\n";exit; }
$db=database();
function add(PDO $db,string $table,array $data): string {
    $id=$data['id']??uuid4();$data=['id'=>$id]+$data;$cols=array_keys($data);
    query($db,'INSERT INTO '.$table.' ('.implode(',',$cols).') VALUES ('.implode(',',array_fill(0,count($cols),'?')).')',array_values($data));return $id;
}
function person(PDO $db,string $role='CUSTOMER'): array {
    $id=add($db,'mk_users',['phone_e164'=>'+1999'.substr(str_replace('-','',uuid4()),0,10),'display_name'=>'Test user']);
    $roleId=query($db,'SELECT id FROM mk_roles WHERE code=?',[$role])->fetchColumn();add($db,'mk_user_roles',['user_id'=>$id,'role_id'=>$roleId]);
    $sid=add($db,'mk_sessions',['user_id'=>$id,'refresh_hash'=>random_bytes(32),'session_version'=>0,'expires_at'=>gmdate('Y-m-d H:i:s',time()+3600)]);
    return live_identity($db,['sub'=>$id,'sid'=>$sid,'session_version'=>0]);
}
function fixture(PDO $db,string $policy='TRACKED',int $stock=20): array {
    $a=person($db);$owner=person($db,'VENDOR');$vendor=add($db,'mk_vendors',['owner_user_id'=>$owner['user_id'],'legal_name'=>'Test vendor','status'=>'ACTIVE']);
    $store=add($db,'mk_stores',['vendor_id'=>$vendor,'name'=>'Test store','address_json'=>'{}','latitude'=>'25.000000','longitude'=>'82.000000','active'=>1,'supports_courier'=>1]);
    add($db,'mk_store_zones',['store_id'=>$store,'fulfillment_type'=>'HYPERLOCAL','pincode'=>'221401']);
    add($db,'mk_store_zones',['store_id'=>$store,'fulfillment_type'=>'COURIER','pincode'=>'221401']);
    add($db,'mk_vendor_tax_profiles',['vendor_id'=>$vendor,'registration_type'=>'UNREGISTERED','state_code'=>'09','valid_from'=>'2020-01-01']);
    add($db,'mk_commission_policies',['store_id'=>$store,'rate'=>'0.00000000','valid_from'=>'2020-01-01']);
    foreach (['HYPERLOCAL','COURIER'] as $mode) add($db,'mk_delivery_policies',['store_id'=>$store,'fulfillment_type'=>$mode,'delivery_minor'=>3000,'active'=>1]);
    $category=add($db,'mk_categories',['name'=>'Tests','slug'=>'test-'.uuid4(),'category_type'=>'LOCAL_SHOPPING']);
    $product=add($db,'mk_products',['category_id'=>$category,'name'=>'Test product']);
    $offer=add($db,'mk_offers',['product_id'=>$product,'store_id'=>$store,'inventory_policy'=>$policy,'active'=>1,'supports_courier'=>1]);
    $variant=add($db,'mk_variants',['offer_id'=>$offer,'sku'=>'SKU','pack_label'=>'1 pack','unit_price_minor'=>20000,'attributes_json'=>'{}']);
    $inventory=$policy==='TRACKED'?add($db,'mk_inventory',['variant_id'=>$variant,'on_hand'=>$stock]):null;
    $cart=add($db,'mk_carts',['customer_id'=>$a['user_id'],'store_id'=>$store,'fulfillment_type'=>'HYPERLOCAL']);
    $item=add($db,'mk_cart_items',['cart_id'=>$cart,'store_id'=>$store,'variant_id'=>$variant,'quantity'=>1]);
    $address=add($db,'mk_addresses',['user_id'=>$a['user_id'],'recipient_name'=>'Test','phone_e164'=>'+19999999999','lines_json'=>'["Test"]','pincode'=>'221401','state_code'=>'09','latitude'=>'25.000000','longitude'=>'82.000000']);
    $body=['cart_id'=>$cart,'address_id'=>$address,'payment_method'=>'COD'];
    return compact('a','owner','vendor','store','category','offer','variant','inventory','cart','item','address','body');
}
function quoted(PDO $db,array $f): array { $b=$f['body'];$b['quote_id']=quote_checkout($db,$f['a'],$b)['quote_id'];return $b; }
$f=fixture($db);$b=quoted($db,$f);$key=uuid4();
$b['unit_price_minor']=1;$b['total_minor']=1;$b['store_id']=uuid4();
$result=checkout($db,$f['a'],$b,$key);$order=$result['order'];
check((string)$order['subtotal_minor']==='20000' && (string)$order['total_minor']==='23000','client price ignored; server quote only');
check($order['status']==='AWAITING_VENDOR' && $order['payment_recipient']==='VENDOR','real fulfillment confirmation and direct shop payment');
check(checkout($db,$f['a'],$b,$key)['replayed']===true,'idempotent replay');
check((int)query($db,'SELECT reserved FROM mk_inventory WHERE id=?',[$f['inventory']])->fetchColumn()===1,'stock reserved exactly once');
$changed=$b;$changed['payment_method']='DIRECT_TO_VENDOR';rejected(fn()=>checkout($db,$f['a'],$changed,$key),'IDEMPOTENCY_CONFLICT');
$stranger=person($db);rejected(fn()=>require_ownership($db,$stranger,'order',$order['id']),'NOT_FOUND');
rejected(fn()=>require_ownership($db,$stranger,'cart',$f['cart']),'NOT_FOUND');
require_ownership($db,$f['owner'],'order',$order['id'],'vendor');check(true,'vendor ownership permitted');
rejected(fn()=>require_ownership($db,$stranger,'order',$order['id'],'vendor'),'FORBIDDEN');
// A signed role claim cannot override DB roles or explicit permissions.
$tokenClaims=$claims;$tokenClaims['sub']=$stranger['user_id'];$tokenClaims['sid']=$stranger['claims']['sid'];$tokenClaims['roles']=['ADMIN'];$tokenClaims['permissions']=['FINANCE_READ'];
$identity=authenticate($db,sign_token($tokenClaims,$private),$keys,'maakit-test','maakit-client');rejected(fn()=>require_roles($identity,['ADMIN']),'FORBIDDEN');
query($db,'UPDATE mk_sessions SET revoked_at=UTC_TIMESTAMP() WHERE id=?',[$stranger['claims']['sid']]);rejected(fn()=>live_identity($db,$stranger['claims']),'SESSION_REVOKED');
$f=fixture($db,'TRACKED',0);$b=quoted($db,$f);rejected(fn()=>checkout($db,$f['a'],$b,uuid4()),'OUT_OF_STOCK');
check((int)query($db,'SELECT COUNT(*) FROM mk_orders WHERE cart_id=?',[$f['cart']])->fetchColumn()===0,'stock failure rolls back entire order');
check(query($db,'SELECT state FROM mk_carts WHERE id=?',[$f['cart']])->fetchColumn()==='ACTIVE','failed cart remains usable');
$f=fixture($db);$b=quoted($db,$f);query($db,'UPDATE mk_variants SET unit_price_minor=25000,version=version+1 WHERE id=?',[$f['variant']]);rejected(fn()=>checkout($db,$f['a'],$b,uuid4()),'QUOTE_EXPIRED');
$f=fixture($db);$b=quoted($db,$f);query($db,'UPDATE mk_users SET rto_risk_score=90 WHERE id=?',[$f['a']['user_id']]);rejected(fn()=>checkout($db,$f['a'],$b,uuid4()),'COD_RESTRICTED');
$f=fixture($db);query($db,'UPDATE mk_stores SET minimum_order_minor=30000 WHERE id=?',[$f['store']]);rejected(fn()=>quoted($db,$f),'MINIMUM_ORDER');
$f=fixture($db);query($db,'UPDATE mk_addresses SET latitude=26 WHERE id=?',[$f['address']]);rejected(fn()=>quoted($db,$f),'OUTSIDE_SERVICE_AREA');
$f=fixture($db);query($db,'UPDATE mk_offers SET is_b2b=1 WHERE id=?',[$f['offer']]);rejected(fn()=>quoted($db,$f),'WHOLESALE_QUOTE_REQUIRED');
$f=fixture($db);query($db,'UPDATE mk_offers SET supports_hyperlocal=0 WHERE id=?',[$f['offer']]);rejected(fn()=>quoted($db,$f),'MIXED_FULFILLMENT');
$f=fixture($db);$otherStore=fixture($db);query($db,'UPDATE mk_cart_items SET variant_id=? WHERE id=?',[$otherStore['variant'],$f['item']]);rejected(fn()=>quoted($db,$f),'SINGLE_STORE_REQUIRED');
$f=fixture($db,'ON_REQUEST');$b=quoted($db,$f);$res=checkout($db,$f['a'],$b,uuid4());
check($res['order']['status']==='AWAITING_VENDOR','unknown stock supports order request without fake availability');
check((int)query($db,'SELECT COUNT(*) FROM mk_inventory_reservations r JOIN mk_order_items i ON i.id=r.order_item_id WHERE i.order_id=?',[$res['order']['id']])->fetchColumn()===0,'on request has no fabricated inventory');
$f=fixture($db);query($db,"UPDATE mk_carts SET fulfillment_type='COURIER' WHERE id=?",[$f['cart']]);$b=quoted($db,$f);check(checkout($db,$f['a'],$b,uuid4())['order']['fulfillment_type']==='COURIER','courier remains separate fulfillment');
// Two actual PHP processes compete for the same last unit, using separate PDO connections.
$f=fixture($db,'TRACKED',1);$b1=quoted($db,$f);$a2=person($db);
$cart2=add($db,'mk_carts',['customer_id'=>$a2['user_id'],'store_id'=>$f['store'],'fulfillment_type'=>'HYPERLOCAL']);
add($db,'mk_cart_items',['cart_id'=>$cart2,'store_id'=>$f['store'],'variant_id'=>$f['variant'],'quantity'=>1]);
$address2=add($db,'mk_addresses',['user_id'=>$a2['user_id'],'recipient_name'=>'Test2','phone_e164'=>'+19999999998','lines_json'=>'["Test"]','pincode'=>'221401','state_code'=>'09','latitude'=>25,'longitude'=>82]);
$b2=['cart_id'=>$cart2,'address_id'=>$address2,'payment_method'=>'COD'];$b2['quote_id']=quote_checkout($db,$a2,$b2)['quote_id'];
$procs=[];$files=[];
foreach ([[$f['a'],$b1],[$a2,$b2]] as $request) {
    $file=tempnam(sys_get_temp_dir(),'maakit-test-');$files[]=$file;file_put_contents($file,json_encode([$request[0],$request[1],uuid4()],JSON_THROW_ON_ERROR));
    $pipes=[];$p=proc_open([PHP_BINARY,__DIR__.'/race.php',$file],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
    fclose($pipes[0]);$procs[]=[$p,$pipes];
}
$success=0;$outOfStock=0;
foreach ($procs as [$p,$pipes]) { $out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$exit=proc_close($p);check($exit===0,'concurrent process completed '.$err);$success+=trim($out)==='CREATED'?1:0;$outOfStock+=trim($out)==='OUT_OF_STOCK'?1:0; }
foreach ($files as $file) unlink($file);
check($success===1 && $outOfStock===1,'two competing checkouts cannot oversell last unit');
check((int)query($db,'SELECT reserved FROM mk_inventory WHERE id=?',[$f['inventory']])->fetchColumn()===1,'concurrent inventory invariant');
check(hash_file('sha256',__DIR__.'/../../database/schema.sql')===hash_file('sha256',__DIR__.'/../../sql/024-php-api.sql'),'updater and standalone schema match');
echo "PHP security and MySQL transaction tests passed.\n";
