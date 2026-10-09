<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') { http_response_code(404);exit; }
ob_start();session_start();
require __DIR__.'/security.php'; // Existing security/HTTP/concurrency suite and reusable fixtures, once.
require_once __DIR__.'/../../middleware/legacy.php';
require_once __DIR__.'/../../controllers/orderLifecycle.php';
require_once __DIR__.'/../../controllers/leads.php';
use function Maakit\Api\{query,bridge_session,jwt_material,dispatch_start,dispatch_tick,dispatch_accept,dispatch_parcel,dispatch_view,rider_duty,request_wholesale_quote,order_decision,expire_reservations,revoke_legacy_sessions};
// Minimal real legacy tables in this isolated test DB; the full legacy site is tested by जाँच.
foreach (["CREATE TABLE IF NOT EXISTS customers(id INT AUTO_INCREMENT PRIMARY KEY,name VARCHAR(80),mobile VARCHAR(15),password VARCHAR(255),active TINYINT DEFAULT 1)",
"CREATE TABLE IF NOT EXISTS users(id INT AUTO_INCREMENT PRIMARY KEY,name VARCHAR(80),role VARCHAR(20),password VARCHAR(255),active TINYINT DEFAULT 1)",
"CREATE TABLE IF NOT EXISTS businesses(id INT AUTO_INCREMENT PRIMARY KEY,name VARCHAR(80),access_code VARCHAR(20),status VARCHAR(20))",
"CREATE TABLE IF NOT EXISTS shop_tokens(id INT AUTO_INCREMENT PRIMARY KEY,business_id INT,token VARCHAR(64) UNIQUE,expires DATETIME)"] as $ddl) $db->exec($ddl);
putenv('MAAKIT_JWT_KEY_FILE');putenv('MAAKIT_JWT_PRIVATE_KEY_FILE');$privateDir=sys_get_temp_dir().'/maakit-private-'.bin2hex(random_bytes(6));putenv('MAAKIT_PRIVATE_DIR='.$privateDir);
check(Maakit\Api\settings()['carrier_config']===null,'missing private credentials keep carriers disabled');
$carrierFile=$privateDir.'/carriers.json';mkdir($privateDir,0700);file_put_contents($carrierFile,'{"THREE_PL":{"enabled":false}}');chmod($carrierFile,0600);
check(Maakit\Api\settings()['carrier_config']===$carrierFile,'cPanel private carrier configuration discovered');
check(Maakit\Api\carrier_transport('THREE_PL',['fulfillment_type'=>'COURIER'])['outcome']==='DISABLED','discovered disabled provider cannot book');
putenv('MAAKIT_CARRIER_CONFIG=/tmp/explicit-carrier-config.json');check(Maakit\Api\settings()['carrier_config']==='/tmp/explicit-carrier-config.json','explicit carrier path takes precedence');putenv('MAAKIT_CARRIER_CONFIG');
$db->prepare('INSERT INTO customers(name,mobile,password) VALUES(?,?,?)')->execute(['Legacy customer','9000040001',password_hash('test-password',PASSWORD_DEFAULT)]);$legacyId=(int)$db->lastInsertId();
$_SESSION['cust']=['id'=>$legacyId,'name'=>'Client cannot choose real identity'];
$exchange=bridge_session($db,'customer');$material=jwt_material();
$auth=Maakit\Api\authenticate($db,'Bearer '.$exchange['access_token'],$material['keys'],$material['issuer'],$material['audience']);
check($auth['roles']===['CUSTOMER'],'legacy customer maps to scoped UUID customer');
$firstSid=$auth['claims']['sid'];$again=bridge_session($db,'customer');$againAuth=Maakit\Api\authenticate($db,'Bearer '.$again['access_token'],$material['keys'],$material['issuer'],$material['audience']);
check($againAuth['claims']['sid']===$firstSid,'existing PHP login exchanges without duplicate sessions');
rejected(fn()=>bridge_session($db,'staff'),'UNAUTHENTICATED');
$db->prepare('UPDATE customers SET password=? WHERE id=?')->execute([password_hash('new-password',PASSWORD_DEFAULT),$legacyId]);
rejected(fn()=>Maakit\Api\live_identity($db,$auth['claims']),'SESSION_REVOKED');rejected(fn()=>bridge_session($db,'customer'),'SESSION_REVOKED');
revoke_legacy_sessions($db,'customer');$new=bridge_session($db,'customer');revoke_legacy_sessions($db);
rejected(fn()=>Maakit\Api\authenticate($db,'Bearer '.$new['access_token'],$material['keys'],$material['issuer'],$material['audience']),'SESSION_REVOKED');
foreach (['bpo'=>'BPO','designer'=>'DESIGNER','delivery'=>'RIDER','admin'=>'ADMIN'] as $legacyRole=>$role) {
    $db->prepare('INSERT INTO users(name,role,password) VALUES(?,?,?)')->execute(['Legacy staff',$legacyRole,password_hash('staff-password',PASSWORD_DEFAULT)]);$id=(int)$db->lastInsertId();
    $_SESSION['user']=['id'=>$id,'role'=>'admin'];$exchange=bridge_session($db,'staff');
    $a=Maakit\Api\authenticate($db,'Bearer '.$exchange['access_token'],$material['keys'],$material['issuer'],$material['audience']);
    check($a['roles']===[$role],'DB role wins over cached session role '.$legacyRole);
    rejected(fn()=>Maakit\Api\require_permission($a,'FINANCE_WRITE'),'FORBIDDEN');
    if ($role==='ADMIN') { Maakit\Api\require_permission($a,'DISPATCH_WRITE');check(true,'verified legacy admin gets explicit dispatch grant'); }
    else rejected(fn()=>Maakit\Api\require_permission($a,'DISPATCH_WRITE'),'FORBIDDEN');
    revoke_legacy_sessions($db,'staff');
}
$db->exec("INSERT INTO businesses(name,access_code,status) VALUES('Legacy shop','ABC-1234','approved')");$shop=(int)$db->lastInsertId();$token=bin2hex(random_bytes(24));
$db->prepare('INSERT INTO shop_tokens(business_id,token,expires) VALUES(?,?,DATE_ADD(NOW(),INTERVAL 90 DAY))')->execute([$shop,$token]);$tokenId=(int)$db->lastInsertId();$_COOKIE['mk_shop']=$token;
$exchange=bridge_session($db,'shop');$a=Maakit\Api\authenticate($db,'Bearer '.$exchange['access_token'],$material['keys'],$material['issuer'],$material['audience']);check($a['roles']===['VENDOR'],'validated shop remember-cookie exchanges');
$db->prepare('DELETE FROM shop_tokens WHERE id=?')->execute([$tokenId]);rejected(fn()=>Maakit\Api\live_identity($db,$a['claims']),'SESSION_REVOKED');
revoke_legacy_sessions($db);$_SESSION=[];$_COOKIE=[];
function ready_order(PDO $db,string $mode='HYPERLOCAL'): array {
    $f=fixture($db);if ($mode==='COURIER') query($db,"UPDATE mk_carts SET fulfillment_type='COURIER' WHERE id=?",[$f['cart']]);
    $b=quoted($db,$f);$result=Maakit\Api\checkout($db,$f['a'],$b,Maakit\Api\uuid4());$f['order']=$result['order']['id'];
    add($db,'mk_dispatch_config',['store_id'=>$f['store'],'enabled'=>1]);
    order_decision($db,$f['owner'],['order_id'=>$f['order'],'operation'=>'confirm']);order_decision($db,$f['owner'],['order_id'=>$f['order'],'operation'=>'ready']);
    if ($mode==='COURIER') dispatch_parcel($db,$f['owner'],['order_id'=>$f['order'],'weight_g'=>500,'length_mm'=>100,'width_mm'=>100,'height_mm'=>100]);
    dispatch_start($db,$f['owner'],$f['order']);return $f;
}
function rider(PDO $db,array $f,bool $merchant): array {
    $a=person($db,'RIDER');$id=add($db,'mk_riders',['user_id'=>$a['user_id'],'vendor_id'=>$merchant?$f['vendor']:null,'kyc_status'=>'VERIFIED','active'=>1,'vehicle_type'=>'BIKE']);
    add($db,'mk_rider_zones',['rider_id'=>$id,'store_id'=>$f['store'],'fulfillment_type'=>'HYPERLOCAL','active'=>1]);rider_duty($db,$a);return ['auth'=>$a,'id'=>$id];
}
$f=ready_order($db);$merchant=rider($db,$f,true);$pool=rider($db,$f,false);
$noNetwork=fn()=>['outcome'=>'DISABLED'];dispatch_tick($db,$noNetwork);
$attempt=query($db,"SELECT * FROM mk_dispatch_attempts WHERE order_id=? AND status='OFFERED'",[$f['order']])->fetch();check($attempt['rider_id']===$merchant['id'] && (int)$attempt['level']===1,'merchant rider offered first');
check(count(dispatch_view($db,$merchant['auth'])['offers'])>=1,'assigned rider can see own offer');
rejected(fn()=>dispatch_accept($db,$pool['auth'],$attempt['id']),'NOT_FOUND');
query($db,'UPDATE mk_dispatch_attempts SET expires_at=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 SECOND) WHERE id=?',[$attempt['id']]);query($db,'UPDATE mk_dispatch_jobs SET next_action_at=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 SECOND) WHERE order_id=?',[$f['order']]);
rejected(fn()=>dispatch_accept($db,$merchant['auth'],$attempt['id']),'OFFER_EXPIRED');dispatch_tick($db,$noNetwork);
$attempt=query($db,"SELECT * FROM mk_dispatch_attempts WHERE order_id=? AND status='OFFERED'",[$f['order']])->fetch();check($attempt['rider_id']===$pool['id'] && (int)$attempt['level']===2,'60-second timeout falls back to local pool');
// Offer acceptance requires current availability; offline never frees an assigned job.
check(rider_duty($db,$pool['auth'],'offline')['status']==='offline','explicit offline status');
check(dispatch_view($db,$pool['auth'])['offers']===[],'offline rider does not see actionable offers');
rejected(fn()=>dispatch_accept($db,$pool['auth'],$attempt['id']),'OFFER_EXPIRED');
rider_duty($db,$pool['auth'],'online');
rejected(fn()=>rider_duty($db,$pool['auth'],'busy'),'INVALID_STATUS');
$accepted=dispatch_accept($db,$pool['auth'],$attempt['id']);check($accepted['accepted'],'rider accepts a fresh own offer');
check(dispatch_accept($db,$pool['auth'],$attempt['id'])['accepted'],'accept replay does not duplicate shipment');
check((int)query($db,'SELECT COUNT(*) FROM mk_shipments WHERE order_id=?',[$f['order']])->fetchColumn()===1,'exactly one shipment');
$f2=ready_order($db);add($db,'mk_rider_zones',['rider_id'=>$pool['id'],'store_id'=>$f2['store'],'fulfillment_type'=>'HYPERLOCAL','active'=>1]);dispatch_tick($db,$noNetwork);
check(!query($db,"SELECT id FROM mk_dispatch_attempts WHERE order_id=? AND rider_id=? AND status='OFFERED'",[$f2['order'],$pool['id']])->fetch(),'assigned rider cannot take a second auto job');
// Scoped progression, invalid states and live account/profile checks.
require_once __DIR__.'/../../controllers/riderController.php';
$progress=['order_id'=>$f['order'],'operation'=>'pickup'];
rejected(fn()=>Maakit\Api\rider_progress($db,$merchant['auth'],$progress),'NOT_FOUND');
rejected(fn()=>Maakit\Api\rider_progress($db,$f['a'],$progress),'FORBIDDEN');
rejected(fn()=>Maakit\Api\rider_progress($db,$pool['auth'],['order_id'=>$f['order'],'operation'=>'complete']),'INVALID_TRANSITION');
rejected(fn()=>Maakit\Api\rider_progress($db,$pool['auth'],['order_id'=>$f['order'],'operation'=>'cancel']),'INVALID_DECISION');
query($db,"UPDATE mk_riders SET kyc_status='REJECTED' WHERE id=?",[$pool['id']]);
rejected(fn()=>Maakit\Api\rider_progress($db,$pool['auth'],$progress),'NOT_FOUND');
rejected(fn()=>rider_duty($db,$pool['auth']),'RIDER_NOT_VERIFIED');
query($db,"UPDATE mk_riders SET kyc_status='VERIFIED' WHERE id=?",[$pool['id']]);
query($db,"UPDATE mk_users SET status='SUSPENDED' WHERE id=?",[$pool['auth']['user_id']]);
rejected(fn()=>Maakit\Api\rider_progress($db,$pool['auth'],$progress),'SESSION_REVOKED');
query($db,"UPDATE mk_users SET status='ACTIVE' WHERE id=?",[$pool['auth']['user_id']]);
$slot=query($db,'SELECT id FROM mk_rider_slots WHERE order_id=?',[$f['order']])->fetchColumn();
rider_duty($db,$pool['auth'],'offline');
check(query($db,'SELECT id FROM mk_rider_slots WHERE order_id=?',[$f['order']])->fetchColumn()===$slot,'offline retains assigned slot');
function progress_race(PDO $db,array $auth,array $body): void {
    $file=tempnam(sys_get_temp_dir(),'mk-rider-race-');file_put_contents($file,json_encode([$auth,$body],JSON_THROW_ON_ERROR));$workers=[];
    try {
        for($i=0;$i<2;$i++){$pipes=[];$proc=proc_open([PHP_BINARY,__DIR__.'/rider-race.php',$file],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);check(is_resource($proc),'progress worker starts');fclose($pipes[0]);$workers[]=[$proc,$pipes];}
        $results=[];foreach($workers as [$proc,$pipes]){$out=stream_get_contents($pipes[1]);$error=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);check(proc_close($proc)===0,'concurrent rider progress '.$error);$results[]=json_decode($out,true,16,JSON_THROW_ON_ERROR);}
        check(count(array_filter($results,fn($r)=>!$r['replayed']))===1,'one transition and one replay under concurrent requests');
    } finally {unlink($file);}
}
progress_race($db,$pool['auth'],$progress);
check(query($db,'SELECT status FROM mk_orders WHERE id=?',[$f['order']])->fetchColumn()==='DISPATCHED','pickup starts delivery');
check((int)query($db,'SELECT on_hand FROM mk_inventory WHERE id=?',[$f['inventory']])->fetchColumn()===19,'seller stock consumed once');
check((int)query($db,'SELECT reserved FROM mk_inventory WHERE id=?',[$f['inventory']])->fetchColumn()===0,'pickup releases held stock once');
check(query($db,'SELECT dispatched_at FROM mk_shipments WHERE order_id=?',[$f['order']])->fetchColumn()!==null,'pickup timestamp recorded');
progress_race($db,$pool['auth'],['order_id'=>$f['order'],'operation'=>'complete']);
check(query($db,'SELECT delivered_at FROM mk_orders WHERE id=?',[$f['order']])->fetchColumn()!==null,'completion records delivery time');
check(query($db,'SELECT state FROM mk_dispatch_jobs WHERE order_id=?',[$f['order']])->fetchColumn()==='COMPLETED','job completes immediately');
check(!query($db,'SELECT id FROM mk_rider_slots WHERE order_id=?',[$f['order']])->fetch(),'completion frees assigned rider slot');
check((int)query($db,"SELECT COUNT(*) FROM mk_order_events WHERE order_id=? AND status='DELIVERED'",[$f['order']])->fetchColumn()===1,'completion audit exactly once');
check(Maakit\Api\rider_progress($db,$pool['auth'],$progress)['status']==='DELIVERED','late pickup replay cannot reopen delivered order');
rejected(fn()=>Maakit\Api\rider_progress($db,$merchant['auth'],['order_id'=>$f['order'],'operation'=>'complete']),'NOT_FOUND');
// API route requires Bearer auth and permits only POST with checked status fields.
$material=jwt_material(true);$httpClaims=['iss'=>$material['issuer'],'aud'=>$material['audience'],'sub'=>$pool['auth']['user_id'],'sid'=>$pool['auth']['claims']['sid'],'session_version'=>0,'jti'=>Maakit\Api\uuid4(),'iat'=>time(),'exp'=>time()+900];
$bearer=sign_token($httpClaims,$material['private'],['alg'=>'RS256','typ'=>'JWT','kid'=>$material['kid']]);
$log=tempnam(sys_get_temp_dir(),'mk-rider-http-');$pipes=[];
$server=proc_open([PHP_BINARY,'-S','127.0.0.1:19099','-t',dirname(__DIR__,2)],[0=>['pipe','r'],1=>['file',$log,'a'],2=>['file',$log,'a']],$pipes);fclose($pipes[0]);
try {
    $ready=false;for($i=0;$i<50;$i++){$socket=@fsockopen('127.0.0.1',19099,$errno,$error,0.1);if($socket){fclose($socket);$ready=true;break;}usleep(100000);}check($ready,'rider API server starts');
    [$status,$data]=http_api('rider_progress','POST',$bearer,['order_id'=>$f['order'],'operation'=>'complete']);check($status===200&&$data['data']['replayed'],'HTTP own completion replay');
    [$status]=http_api('rider_progress','POST','Bearer invalid',$progress);check($status===401,'HTTP invalid rider token');
    [$status]=http_api('rider_progress','GET',$bearer);check($status===405,'HTTP GET cannot complete');
    [$status]=http_api('rider_duty','POST',$bearer,['status'=>[]]);check($status===400,'HTTP malformed status rejected');
    [$status,$data]=http_api('rider_duty','POST',$bearer,['status'=>'offline']);check($status===200&&$data['data']['status']==='offline','HTTP offline status');
} finally {proc_terminate($server);proc_close($server);unlink($log);}
// Reset active local queues so carrier tests do not consume unrelated fixture jobs.
query($db,"UPDATE mk_dispatch_jobs SET state='MANUAL_REQUIRED' WHERE state NOT IN ('ASSIGNED','COMPLETED','CANCELLED')");
$f=ready_order($db,'COURIER');$calls=[];
$carrier=function(string $provider,array $payload,string $op) use (&$calls) { $calls[]=$provider.':'.$op;return $provider==='THREE_PL'?['outcome'=>'DECLINED']:['outcome'=>'BOOKED','request_id'=>$payload['request_id'],'tracking_reference'=>'POST_TEST_123','charge_minor'=>'3000','fulfillment_type'=>'COURIER']; };
dispatch_tick($db,$carrier);dispatch_tick($db,$carrier);
check($calls===['THREE_PL:BOOK','INDIA_POST:BOOK'],'definite 3PL decline reaches India Post fourth level');
check(query($db,'SELECT provider FROM mk_shipments WHERE order_id=?',[$f['order']])->fetchColumn()==='INDIA_POST','postal assignment only after real confirmed adapter result');
$f=ready_order($db,'COURIER');$calls=[];
$unknown=function(string $provider,array $payload,string $op) use (&$calls) { $calls[]=$provider.':'.$op;return ['outcome'=>'UNKNOWN']; };
dispatch_tick($db,$unknown);
query($db,'UPDATE mk_provider_requests SET lease_until=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 SECOND) WHERE order_id=?',[$f['order']]);query($db,'UPDATE mk_dispatch_jobs SET next_action_at=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 SECOND) WHERE order_id=?',[$f['order']]);dispatch_tick($db,$unknown);
check($calls===['THREE_PL:BOOK','THREE_PL:LOOKUP'],'ambiguous provider outcome reconciles same key before any fallback');
check(!query($db,'SELECT id FROM mk_shipments WHERE order_id=?',[$f['order']])->fetch(),'no fake tracking for timeout');
query($db,"UPDATE mk_dispatch_jobs SET state='MANUAL_REQUIRED' WHERE state='PROVIDER_WAIT'");
$f=ready_order($db);dispatch_tick($db,$noNetwork);dispatch_tick($db,$noNetwork);
$job=query($db,'SELECT state,reason_code FROM mk_dispatch_jobs WHERE order_id=?',[$f['order']])->fetch();
check($job['state']==='MANUAL_REQUIRED' && $job['reason_code']==='COURIER_CONSENT_REQUIRED','local delivery never silently becomes postal shipping');
// Local 3PL needs an explicit supported mode; the injected test contract returns local delivery intact.
$f=ready_order($db);dispatch_tick($db,fn($provider,$payload,$op)=>['outcome'=>'BOOKED','request_id'=>$payload['request_id'],'tracking_reference'=>'LOCAL_TEST_123','charge_minor'=>'3000','fulfillment_type'=>'HYPERLOCAL']);
check(query($db,'SELECT fulfillment_type FROM mk_shipments WHERE order_id=?',[$f['order']])->fetchColumn()==='HYPERLOCAL','3PL can preserve explicit local fulfillment');
$f=fixture($db);$b=quoted($db,$f);$o=Maakit\Api\checkout($db,$f['a'],$b,Maakit\Api\uuid4())['order']['id'];
query($db,'UPDATE mk_inventory_reservations r JOIN mk_order_items i ON i.id=r.order_item_id SET r.expires_at=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 SECOND) WHERE i.order_id=?',[$o]);
check(expire_reservations($db)>=1,'unconfirmed expired reservations are cancelled');expire_reservations($db);
check((int)query($db,'SELECT reserved FROM mk_inventory WHERE id=?',[$f['inventory']])->fetchColumn()===0,'reservation release is exactly once');
check(query($db,'SELECT status FROM mk_orders WHERE id=?',[$o])->fetchColumn()==='CANCELLED','expired requests cannot dispatch');
$f=fixture($db);query($db,'UPDATE mk_offers SET is_b2b=1 WHERE id=?',[$f['offer']]);$key=Maakit\Api\uuid4();$body=['offer_id'=>$f['offer'],'quantity'=>50,'message'=>'Test bulk requirement'];
$lead=request_wholesale_quote($db,$f['a'],$body,$key);check(request_wholesale_quote($db,$f['a'],$body,$key)['replayed'],'RFQ is separate and idempotent');
check(in_array($lead['rfq_id'],array_column(dispatch_view($db,$f['owner'])['rfqs'],'id'),true),'supplier sees own wholesale lead');
check(hash_file('sha256',__DIR__.'/../../database/025-session-dispatch.sql')===hash_file('sha256',__DIR__.'/../../sql/025-session-dispatch.sql'),'session/dispatch migration mirrors standalone supplement');
foreach (glob($privateDir.'/*') as $file) unlink($file);rmdir($privateDir);
echo "Session bridge, dispatch fallbacks, durable carrier recovery, RFQs and reservation expiry passed.\n";
ob_end_flush();
