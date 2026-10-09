<?php
declare(strict_types=1);
namespace Maakit\Api;
use PDO;
use Throwable;
require_once __DIR__.'/checkout.php';
require_once __DIR__.'/../config/config.php';
function dispatch_authorize(PDO $db,array $auth,string $order): void {
    if (in_array('ADMIN',$auth['roles'],true)) { require_permission($auth,'DISPATCH_WRITE');return; }
    require_permission($auth,'DISPATCH_OWN');require_ownership($db,$auth,'order',$order,'vendor');
}
function dispatch_start(PDO $db,array $auth,string $orderId): array {
    $orderId=valid_uuid($orderId);$db->beginTransaction();
    try {
        $auth=live_identity($db,$auth['claims'],true);dispatch_authorize($db,$auth,$orderId);
        $order=query($db,'SELECT * FROM mk_orders WHERE id=? FOR UPDATE',[$orderId])->fetch();
        if (!$order) throw new ApiError(404,'NOT_FOUND','Order not found.');
        if ($order['status']!=='READY') throw new ApiError(409,'ORDER_NOT_READY','The shop must confirm and pack the order before dispatch.');
        if (!query($db,'SELECT id FROM mk_dispatch_config WHERE store_id=? AND enabled=1',[$order['store_id']])->fetch()) throw new ApiError(409,'DISPATCH_DISABLED','Dispatch is not enabled for this shop.');
        $job=query($db,'SELECT * FROM mk_dispatch_jobs WHERE order_id=? FOR UPDATE',[$orderId])->fetch();
        if (!$job) {
            $id=uuid4();query($db,'INSERT INTO mk_dispatch_jobs(id,order_id) VALUES(?,?)',[$id,$orderId]);
            $job=query($db,'SELECT * FROM mk_dispatch_jobs WHERE id=?',[$id])->fetch();
        }
        $db->commit();return ['job_id'=>$job['id'],'state'=>$job['state'],'level'=>(int)$job['level']];
    } catch (Throwable $e) { if ($db->inTransaction()) $db->rollBack();throw $e; }
}
function dispatch_event(PDO $db,array $order,string $type,array $data=[]): void {
    row_insert($db,'mk_outbox',['id'=>uuid4(),'order_id'=>$order['id'],'event_type'=>$type,
        'dedupe_key'=>$type.':'.($data['attempt_id']??$order['id']),'payload_json'=>json_data(['order_id'=>$order['id']]+$data)]);
}
/** Pure decisions are injectable for tests. Production network calls use only private operator config. */
function carrier_transport(string $provider,array $request,string $operation='BOOK'): array {
    $file=settings()['carrier_config'];
    if (!$file) return ['outcome'=>'DISABLED'];
    $all=json_decode(read_private_file($file),true,16,JSON_THROW_ON_ERROR);$c=$all[$provider]??null;
    if (!is_array($c)||($c['enabled']??false)!==true) return ['outcome'=>'DISABLED'];
    if (!in_array($request['fulfillment_type'],$c['modes']??[],true)) return ['outcome'=>'UNSUPPORTED_MODE'];
    // India Post is never an implicit conversion of a customer's local delivery order.
    if ($provider==='INDIA_POST' && $request['fulfillment_type']!=='COURIER') return ['outcome'=>'UNSUPPORTED_MODE'];
    if (($c['idempotency_supported']??false)!==true||($c['lookup_supported']??false)!==true) return ['outcome'=>'DISABLED'];
    $url=$c['endpoint']??'';$parts=is_string($url)?parse_url($url):false;
    $host=$parts['host']??'';
    if (!$parts||($parts['scheme']??'')!=='https'||isset($parts['user'])||isset($parts['pass'])||(($parts['port']??443)!==443)
        || !in_array($host,$c['allowed_hosts']??[],true)||!preg_match('/^[a-z0-9.-]+$/iD',$host)||!function_exists('curl_init')||!is_string($c['token']??null)||$c['token']==='') return ['outcome'=>'DISABLED'];
    $records=dns_get_record($host,DNS_A);$ips=[];
    foreach ($records?:[] as $record) {
        $ip=$record['ip']??'';
        if (!filter_var($ip,FILTER_VALIDATE_IP,FILTER_FLAG_NO_PRIV_RANGE|FILTER_FLAG_NO_RES_RANGE)) return ['outcome'=>'DISABLED'];
        $ips[]=$ip;
    }
    if (!$ips) return ['outcome'=>'DISABLED'];
    $curl=curl_init($url);
    curl_setopt_array($curl,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>json_data(['operation'=>$operation]+$request),
        CURLOPT_HTTPHEADER=>['Content-Type: application/json','Authorization: Bearer '.$c['token'],'Idempotency-Key: '.$request['request_id']],
        CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>3,CURLOPT_TIMEOUT=>10,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,
        CURLOPT_FOLLOWLOCATION=>false,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_RESOLVE=>[$host.':443:'.$ips[0]],
        CURLOPT_WRITEFUNCTION=>static function($ch,string $bytes) use (&$response) { $response=($response??'').$bytes;return strlen($response)>65536?0:strlen($bytes); }]);
    $response='';$ok=curl_exec($curl);$status=(int)curl_getinfo($curl,CURLINFO_HTTP_CODE);curl_close($curl);
    // A timeout/5xx/malformed response may already have booked a shipment. Never fall through blindly.
    if ($ok===false || $status!==200) return ['outcome'=>'UNKNOWN'];
    try { $result=json_decode($response,true,16,JSON_THROW_ON_ERROR); } catch (Throwable $e) { return ['outcome'=>'UNKNOWN']; }
    return is_array($result)?$result:['outcome'=>'UNKNOWN'];
}
/** Run each minute from cPanel cron. Claims and outbound operations survive overlapping/crashed workers. */
function dispatch_tick(PDO $db,?callable $transport=null): array {
    $transport=$transport??__NAMESPACE__.'\\carrier_transport';$processed=0;$deferred=0;
    // READY orders join the queue without a single administrator repeatedly clicking dispatch.
    $ready=query($db,"SELECT o.id FROM mk_orders o JOIN mk_dispatch_config c ON c.store_id=o.store_id AND c.enabled=1 LEFT JOIN mk_dispatch_jobs j ON j.order_id=o.id WHERE o.status='READY' AND j.id IS NULL ORDER BY o.created_at LIMIT 20")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($ready as $id) query($db,'INSERT IGNORE INTO mk_dispatch_jobs(id,order_id) VALUES(?,?)',[uuid4(),$id]);
    $jobs=query($db,"SELECT j.id,j.order_id FROM mk_dispatch_jobs j JOIN mk_orders o ON o.id=j.order_id WHERE (j.state IN ('READY','OFFERED','PROVIDER_WAIT') AND j.next_action_at<=UTC_TIMESTAMP(6)) OR (j.state='ASSIGNED' AND o.status IN ('DELIVERED','CANCELLED','RTO')) ORDER BY j.next_action_at LIMIT 20")->fetchAll();
    foreach ($jobs as $item) {
        try { $plan=dispatch_plan($db,$item['id'],$item['order_id']);if ($plan) {
            try { $response=$transport($plan['provider'],$plan['payload'],$plan['operation']); } catch (Throwable $e) { $response=['outcome'=>'UNKNOWN']; }
            dispatch_result($db,$plan,$response);
        }$processed++; } catch (Throwable $e) { if ($db->inTransaction()) $db->rollBack();$deferred++;error_log('Maakit dispatch deferred class='.get_class($e)); }
    }
    return compact('processed','deferred');
}
/** All paths lock ORDER -> JOB -> RIDER; no external request occurs while any transaction is open. */
function dispatch_plan(PDO $db,string $jobId,string $orderId): ?array {
    $db->beginTransaction();
    try {
        $order=query($db,'SELECT * FROM mk_orders WHERE id=? FOR UPDATE',[$orderId])->fetch();
        $job=query($db,'SELECT *,next_action_at<=UTC_TIMESTAMP(6) AS due FROM mk_dispatch_jobs WHERE id=? AND order_id=? FOR UPDATE',[$jobId,$orderId])->fetch();
        if (!$job||!$order) { $db->commit();return null; }
        if (in_array($order['status'],['DELIVERED','CANCELLED','RTO'],true)) {
            query($db,'DELETE FROM mk_rider_slots WHERE order_id=?',[$orderId]);
            query($db,'UPDATE mk_dispatch_jobs SET state=? WHERE id=?',[$order['status']==='DELIVERED'?'COMPLETED':'CANCELLED',$jobId]);$db->commit();return null;
        }
        if (!$job['due']||in_array($job['state'],['ASSIGNED','MANUAL_REQUIRED','COMPLETED','CANCELLED'],true)) { $db->commit();return null; }
        $store=query($db,"SELECT s.id FROM mk_stores s JOIN mk_vendors v ON v.id=s.vendor_id WHERE s.id=? AND s.active=1 AND v.status='ACTIVE'",[$order['store_id']])->fetch();
        if (!$store || $order['status']!=='READY' || !query($db,'SELECT id FROM mk_dispatch_config WHERE store_id=? AND enabled=1',[$order['store_id']])->fetch()) {
            query($db,"UPDATE mk_dispatch_jobs SET state='MANUAL_REQUIRED',reason_code='ORDER_OR_AREA_DISABLED' WHERE id=?",[$jobId]);$db->commit();return null;
        }
        $level=(int)$job['level'];
        if ($job['state']==='OFFERED') {
            // Only timeouts advance. A late driver acceptance cannot seize an expired offer.
            query($db,"UPDATE mk_dispatch_attempts SET status='EXPIRED' WHERE order_id=? AND status='OFFERED' AND expires_at<=UTC_TIMESTAMP(6)",[$orderId]);
            query($db,"DELETE FROM mk_rider_slots WHERE order_id=? AND state='OFFERED'",[$orderId]);$level++;
        }
        if ($job['state']==='PROVIDER_WAIT') {
            $request=query($db,"SELECT *,lease_until>UTC_TIMESTAMP(6) AS leased FROM mk_provider_requests WHERE job_id=? AND state IN ('SENDING','UNKNOWN') ORDER BY provider LIMIT 1 FOR UPDATE",[$jobId])->fetch();
            if (!$request || $request['leased']) { $db->commit();return null; }
            // A previous worker may have booked before crashing. Lookup the SAME durable key.
            query($db,"UPDATE mk_provider_requests SET state='UNKNOWN',lease_until=DATE_ADD(UTC_TIMESTAMP(6),INTERVAL 2 MINUTE),attempts=attempts+1 WHERE id=?",[$request['id']]);
            query($db,'UPDATE mk_dispatch_jobs SET next_action_at=DATE_ADD(UTC_TIMESTAMP(6),INTERVAL 2 MINUTE) WHERE id=?',[$jobId]);
            $plan=provider_plan($db,$order,$job,$request,'LOOKUP');$db->commit();return $plan;
        }
        while ($level<=2) {
            $scope=$level===1?'r.vendor_id=?':'r.vendor_id IS NULL';$params=[$order['store_id'],$order['fulfillment_type']];if ($level===1) $params[]=$order['vendor_id'];
            $ids=query($db,"SELECT r.id FROM mk_riders r JOIN mk_users u ON u.id=r.user_id JOIN mk_rider_duty d ON d.rider_id=r.id JOIN mk_rider_zones z ON z.rider_id=r.id AND z.store_id=? AND z.fulfillment_type=? AND z.active=1 WHERE $scope AND r.active=1 AND r.kyc_status='VERIFIED' AND u.status='ACTIVE' AND d.available_until>UTC_TIMESTAMP(6) ORDER BY r.id LIMIT 100",$params)->fetchAll(PDO::FETCH_COLUMN);
            foreach ($ids as $riderId) {
                $rider=query($db,"SELECT r.* FROM mk_riders r JOIN mk_users u ON u.id=r.user_id JOIN mk_rider_duty d ON d.rider_id=r.id WHERE r.id=? AND r.active=1 AND r.kyc_status='VERIFIED' AND u.status='ACTIVE' AND d.available_until>UTC_TIMESTAMP(6) FOR UPDATE",[$riderId])->fetch();
                if (!$rider || query($db,'SELECT id FROM mk_rider_slots WHERE rider_id=? FOR UPDATE',[$riderId])->fetch()) continue;
                $attempt=uuid4();
                query($db,"INSERT INTO mk_dispatch_attempts(id,order_id,rider_id,level,status,expires_at) VALUES(?,?,?,?,'OFFERED',DATE_ADD(UTC_TIMESTAMP(6),INTERVAL 60 SECOND))",[$attempt,$orderId,$riderId,$level]);
                query($db,"INSERT INTO mk_rider_slots(id,rider_id,order_id,state) VALUES(?,?,?,'OFFERED')",[uuid4(),$riderId,$orderId]);
                query($db,"UPDATE mk_dispatch_jobs SET state='OFFERED',level=?,next_action_at=DATE_ADD(UTC_TIMESTAMP(6),INTERVAL 60 SECOND),reason_code=NULL WHERE id=?",[$level,$jobId]);
                dispatch_event($db,$order,'DRIVER_OFFERED',['attempt_id'=>$attempt,'rider_id'=>$riderId]);$db->commit();return null;
            }
            $level++;
        }
        if ($level>4 || ($level===4 && $order['fulfillment_type']!=='COURIER')) {
            query($db,"UPDATE mk_dispatch_jobs SET level=4,state='MANUAL_REQUIRED',reason_code=? WHERE id=?",[$order['fulfillment_type']==='HYPERLOCAL'?'COURIER_CONSENT_REQUIRED':'NO_PROVIDER_AVAILABLE',$jobId]);$db->commit();return null;
        }
        if ($order['fulfillment_type']==='COURIER' && !query($db,'SELECT id FROM mk_carrier_parcels WHERE order_id=?',[$orderId])->fetch()) {
            query($db,"UPDATE mk_dispatch_jobs SET state='MANUAL_REQUIRED',reason_code='PACKAGING_REQUIRED' WHERE id=?",[$jobId]);$db->commit();return null;
        }
        $provider=$level===3?'THREE_PL':'INDIA_POST';
        $request=query($db,'SELECT * FROM mk_provider_requests WHERE job_id=? AND provider=? FOR UPDATE',[$jobId,$provider])->fetch();
        if (!$request) {
            $request=['id'=>uuid4(),'provider'=>$provider];query($db,'INSERT INTO mk_provider_requests(id,job_id,order_id,provider) VALUES(?,?,?,?)',[$request['id'],$jobId,$orderId,$provider]);
        } elseif (in_array($request['state'],['BOOKED','SENDING','UNKNOWN'],true)) { $db->commit();return null; }
        query($db,"UPDATE mk_provider_requests SET state='SENDING',lease_until=DATE_ADD(UTC_TIMESTAMP(6),INTERVAL 2 MINUTE),attempts=attempts+1 WHERE id=?",[$request['id']]);
        query($db,"UPDATE mk_dispatch_jobs SET state='PROVIDER_WAIT',level=?,next_action_at=DATE_ADD(UTC_TIMESTAMP(6),INTERVAL 2 MINUTE) WHERE id=?",[$level,$jobId]);
        $job['level']=$level;$plan=provider_plan($db,$order,$job,$request,'BOOK');$db->commit();return $plan;
    } catch (Throwable $e) { if ($db->inTransaction()) $db->rollBack();throw $e; }
}
function provider_plan(PDO $db,array $order,array $job,array $request,string $operation): array {
    $store=query($db,'SELECT address_json,latitude,longitude FROM mk_stores WHERE id=?',[$order['store_id']])->fetch();
    $parcel=query($db,'SELECT weight_g,length_mm,width_mm,height_mm FROM mk_carrier_parcels WHERE order_id=?',[$order['id']])->fetch()?:null;
    $items=query($db,'SELECT quantity,product_snapshot FROM mk_order_items WHERE order_id=? ORDER BY id',[$order['id']])->fetchAll();
    return ['job_id'=>$job['id'],'order_id'=>$order['id'],'request_id'=>$request['id'],'provider'=>$request['provider'],'operation'=>$operation,
        'payload'=>['request_id'=>$request['id'],'order_id'=>$order['id'],'fulfillment_type'=>$order['fulfillment_type'],
            'store_id'=>$order['store_id'],'pickup'=>$store,'parcel'=>$parcel,'items'=>$items,'address'=>json_decode($order['address_snapshot'],true,32,JSON_THROW_ON_ERROR),
            'maximum_charge_minor'=>(string)$order['delivery_minor'],'currency'=>'INR']];
}
function dispatch_result(PDO $db,array $plan,array $result): void {
    $db->beginTransaction();
    try {
        $order=query($db,'SELECT * FROM mk_orders WHERE id=? FOR UPDATE',[$plan['order_id']])->fetch();
        $job=query($db,'SELECT * FROM mk_dispatch_jobs WHERE id=? FOR UPDATE',[$plan['job_id']])->fetch();
        $request=query($db,'SELECT * FROM mk_provider_requests WHERE id=? FOR UPDATE',[$plan['request_id']])->fetch();
        if (!$job||!$request||$request['state']==='BOOKED'||$job['state']!=='PROVIDER_WAIT') { $db->commit();return; }
        $outcome=$result['outcome']??'UNKNOWN';
        if ($outcome==='BOOKED') {
            $reference=$result['tracking_reference']??null;$charge=$result['charge_minor']??null;
            if (($result['request_id']??null)!==$plan['request_id']||$order['status']!=='READY'||!is_string($reference)||!preg_match('/^[A-Za-z0-9_.-]{1,150}$/D',$reference)||($result['fulfillment_type']??null)!==$order['fulfillment_type']
                || (!is_int($charge)&&!is_string($charge)) || !preg_match('/^[0-9]{1,13}$/D',(string)$charge) || (int)$charge>money($order['delivery_minor'])) $outcome='UNKNOWN';
            else {
                // Postal shipping cannot replace local delivery without a new confirmed courier order.
                if ($plan['provider']==='INDIA_POST' && $order['fulfillment_type']!=='COURIER') $outcome='UNKNOWN';
                else {
                    query($db,'INSERT INTO mk_shipments(id,order_id,fulfillment_type,provider,provider_reference,accepted_at) VALUES(?,?,?,?,?,UTC_TIMESTAMP(6))',[uuid4(),$order['id'],$order['fulfillment_type'],$plan['provider'],$reference]);
                    query($db,"UPDATE mk_provider_requests SET state='BOOKED',tracking_reference=?,lease_until=NULL WHERE id=?",[$reference,$request['id']]);
                    query($db,"UPDATE mk_dispatch_jobs SET state='ASSIGNED',reason_code=NULL WHERE id=?",[$job['id']]);dispatch_event($db,$order,'CARRIER_ASSIGNED');$db->commit();return;
                }
            }
        }
        if (in_array($outcome,['DISABLED','UNSUPPORTED_MODE','DECLINED'],true) && ($plan['operation']==='BOOK'||$outcome==='DECLINED')) {
            query($db,'UPDATE mk_provider_requests SET state=?,lease_until=NULL WHERE id=?',[$outcome==='DECLINED'?'DECLINED':'DISABLED',$request['id']]);
            if ((int)$job['level']===3) query($db,"UPDATE mk_dispatch_jobs SET level=4,state='READY',next_action_at=UTC_TIMESTAMP(6),reason_code=NULL WHERE id=?",[$job['id']]);
            else query($db,"UPDATE mk_dispatch_jobs SET state='MANUAL_REQUIRED',reason_code='NO_PROVIDER_AVAILABLE' WHERE id=?",[$job['id']]);
        } else {
            // UNKNOWN, disabled lookup, or mismatched booked response freezes fallback to prevent duplicate labels.
            query($db,"UPDATE mk_provider_requests SET state='UNKNOWN',lease_until=DATE_ADD(UTC_TIMESTAMP(6),INTERVAL 2 MINUTE) WHERE id=?",[$request['id']]);
            query($db,"UPDATE mk_dispatch_jobs SET reason_code='PROVIDER_RECONCILIATION',next_action_at=DATE_ADD(UTC_TIMESTAMP(6),INTERVAL 2 MINUTE) WHERE id=?",[$job['id']]);
        }
        $db->commit();
    } catch (Throwable $e) { if ($db->inTransaction()) $db->rollBack();throw $e; }
}
function dispatch_accept(PDO $db,array $auth,string $attemptId): array {
    $attemptId=valid_uuid($attemptId);$entry=query($db,'SELECT order_id FROM mk_dispatch_attempts WHERE id=?',[$attemptId])->fetch();
    if (!$entry) throw new ApiError(404,'NOT_FOUND','Offer not found.');
    $db->beginTransaction();
    try {
        $auth=live_identity($db,$auth['claims'],true);require_roles($auth,['RIDER']);
        $order=query($db,'SELECT * FROM mk_orders WHERE id=? FOR UPDATE',[$entry['order_id']])->fetch();
        $job=query($db,'SELECT * FROM mk_dispatch_jobs WHERE order_id=? FOR UPDATE',[$entry['order_id']])->fetch();
        $attempt=query($db,"SELECT *,expires_at>UTC_TIMESTAMP(6) AS fresh FROM mk_dispatch_attempts WHERE id=? FOR UPDATE",[$attemptId])->fetch();
        $rider=query($db,"SELECT * FROM mk_riders WHERE id=? AND user_id=? AND active=1 AND kyc_status='VERIFIED' FOR UPDATE",[$attempt['rider_id'],$auth['user_id']])->fetch();
        if (!$rider) throw new ApiError(404,'NOT_FOUND','Offer not found.');
        if ($attempt['status']==='ACCEPTED' && $job['state']==='ASSIGNED') { $db->commit();return ['accepted'=>true,'order_id'=>$order['id']]; }
        if (!query($db,"SELECT d.id FROM mk_rider_duty d JOIN mk_rider_zones z ON z.rider_id=d.rider_id WHERE d.rider_id=? AND d.available_until>UTC_TIMESTAMP(6) AND z.store_id=? AND z.fulfillment_type=? AND z.active=1",[$rider['id'],$order['store_id'],$order['fulfillment_type']])->fetch()) throw new ApiError(409,'OFFER_EXPIRED','Your availability or service area changed.');
        if (!$attempt['fresh']||$attempt['status']!=='OFFERED'||$job['state']!=='OFFERED'||(int)$job['level']!==(int)$attempt['level']||$order['status']!=='READY') throw new ApiError(409,'OFFER_EXPIRED','This offer expired. Check your latest offers.');
        if (!query($db,"SELECT id FROM mk_rider_slots WHERE order_id=? AND rider_id=? AND state='OFFERED' FOR UPDATE",[$order['id'],$rider['id']])->fetch()) throw new ApiError(409,'OFFER_EXPIRED','This offer expired.');
        $provider=(int)$attempt['level']===1?'MERCHANT_STAFF':'LOCAL_POOL';
        query($db,'INSERT INTO mk_shipments(id,order_id,rider_id,fulfillment_type,provider,accepted_at) VALUES(?,?,?,?,?,UTC_TIMESTAMP(6))',[uuid4(),$order['id'],$rider['id'],$order['fulfillment_type'],$provider]);
        query($db,"UPDATE mk_dispatch_attempts SET status='ACCEPTED' WHERE id=?",[$attemptId]);query($db,"UPDATE mk_rider_slots SET state='ASSIGNED' WHERE order_id=?",[$order['id']]);
        query($db,"UPDATE mk_dispatch_jobs SET state='ASSIGNED',reason_code=NULL WHERE id=?",[$job['id']]);dispatch_event($db,$order,'RIDER_ASSIGNED');$db->commit();
        return ['accepted'=>true,'order_id'=>$order['id']];
    } catch (Throwable $e) { if ($db->inTransaction()) $db->rollBack();throw $e; }
}
function rider_duty(PDO $db,array $auth,string $status='online'): array {
    if (!in_array($status,['online','offline'],true)) throw new ApiError(400,'INVALID_STATUS','Choose online or offline.');
    $db->beginTransaction();
    try {
        $auth=live_identity($db,$auth['claims'],true);require_roles($auth,['RIDER']);
        $r=query($db,"SELECT id FROM mk_riders WHERE user_id=? AND active=1 AND kyc_status='VERIFIED' FOR UPDATE",[$auth['user_id']])->fetch();
        if (!$r) throw new ApiError(403,'RIDER_NOT_VERIFIED','Your rider profile must be verified first.');
        if ($status==='online') query($db,'INSERT INTO mk_rider_duty(id,rider_id,available_until) VALUES(?,?,DATE_ADD(UTC_TIMESTAMP(6),INTERVAL 5 MINUTE)) ON DUPLICATE KEY UPDATE available_until=VALUES(available_until)',[uuid4(),$r['id']]);
        else query($db,'UPDATE mk_rider_duty SET available_until=UTC_TIMESTAMP(6) WHERE rider_id=?',[$r['id']]);
        $db->commit();return ['status'=>$status,'available_for_seconds'=>$status==='online'?300:0];
    } catch (Throwable $e) { if ($db->inTransaction()) $db->rollBack();throw $e; }
}
function dispatch_view(PDO $db,array $auth): array {
    if (in_array('ADMIN',$auth['roles'],true)) {
        require_permission($auth,'DISPATCH_WRITE');
        return ['pending_orders'=>query($db,"SELECT o.id AS order_id,o.status,s.name AS store_name FROM mk_orders o JOIN mk_stores s ON s.id=o.store_id WHERE o.status IN ('AWAITING_VENDOR','CONFIRMED','PACKING','READY') ORDER BY o.created_at DESC LIMIT 50")->fetchAll(),'jobs'=>query($db,'SELECT j.id,j.order_id,j.level,j.state,j.reason_code,o.status FROM mk_dispatch_jobs j JOIN mk_orders o ON o.id=j.order_id ORDER BY j.created_at DESC LIMIT 50')->fetchAll()];
    }
    if (in_array('VENDOR',$auth['roles'],true)) {
        require_permission($auth,'DISPATCH_OWN');
        $scope="(v.owner_user_id=? OR EXISTS(SELECT 1 FROM mk_vendor_members m WHERE m.vendor_id=v.id AND m.user_id=? AND m.active=1))";
        $jobs=query($db,"SELECT j.id,j.order_id,j.level,j.state,j.reason_code,o.status FROM mk_dispatch_jobs j JOIN mk_orders o ON o.id=j.order_id JOIN mk_vendors v ON v.id=o.vendor_id WHERE $scope ORDER BY j.created_at DESC LIMIT 50",[$auth['user_id'],$auth['user_id']])->fetchAll();
        $rfqs=query($db,"SELECT r.id,r.quantity,r.message,r.status,p.name AS product FROM mk_b2b_rfqs r JOIN mk_offers o ON o.id=r.offer_id JOIN mk_products p ON p.id=o.product_id JOIN mk_stores s ON s.id=o.store_id JOIN mk_vendors v ON v.id=s.vendor_id WHERE $scope ORDER BY r.created_at DESC LIMIT 50",[$auth['user_id'],$auth['user_id']])->fetchAll();
        $pending_orders=query($db,"SELECT o.id AS order_id,o.status,s.name AS store_name FROM mk_orders o JOIN mk_stores s ON s.id=o.store_id JOIN mk_vendors v ON v.id=o.vendor_id WHERE $scope AND o.status IN ('AWAITING_VENDOR','CONFIRMED','PACKING','READY') ORDER BY o.created_at DESC LIMIT 50",[$auth['user_id'],$auth['user_id']])->fetchAll();
        return compact('jobs','rfqs','pending_orders');
    }
    require_roles($auth,['RIDER']);
    $offers=query($db,"SELECT a.id,a.order_id,a.level,a.expires_at,s.name AS store_name FROM mk_dispatch_attempts a JOIN mk_riders r ON r.id=a.rider_id JOIN mk_orders o ON o.id=a.order_id JOIN mk_stores s ON s.id=o.store_id WHERE r.user_id=? AND r.active=1 AND r.kyc_status='VERIFIED' AND EXISTS(SELECT 1 FROM mk_rider_duty d WHERE d.rider_id=r.id AND d.available_until>UTC_TIMESTAMP(6)) AND a.status='OFFERED' AND a.expires_at>UTC_TIMESTAMP(6) AND o.status='READY' ORDER BY a.expires_at LIMIT 20",[$auth['user_id']])->fetchAll();
    $assigned=query($db,"SELECT sh.order_id,o.status,s.name AS store_name FROM mk_shipments sh JOIN mk_riders r ON r.id=sh.rider_id JOIN mk_orders o ON o.id=sh.order_id JOIN mk_stores s ON s.id=o.store_id WHERE r.user_id=? AND r.active=1 AND r.kyc_status='VERIFIED' AND o.status NOT IN ('DELIVERED','CANCELLED','RTO') LIMIT 20",[$auth['user_id']])->fetchAll();
    $availability=query($db,"SELECT GREATEST(0,TIMESTAMPDIFF(SECOND,UTC_TIMESTAMP(6),d.available_until)) FROM mk_riders r JOIN mk_rider_duty d ON d.rider_id=r.id WHERE r.user_id=? AND r.active=1 AND r.kyc_status='VERIFIED'",[$auth['user_id']])->fetchColumn();
    return ['offers'=>$offers,'assigned'=>$assigned,'duty'=>['status'=>(int)$availability>0?'online':'offline','available_for_seconds'=>(int)$availability]];
}
function dispatch_parcel(PDO $db,array $auth,array $body): array {
    $order=valid_uuid($body['order_id']??null);$values=[];
    foreach (['weight_g'=>100000,'length_mm'=>3000,'width_mm'=>3000,'height_mm'=>3000] as $field=>$max) {
        if (!is_int($body[$field]??null)||$body[$field]<1||$body[$field]>$max) throw new ApiError(400,'INVALID_PARCEL','Enter actual parcel weight and dimensions.');$values[]=$body[$field];
    }
    $db->beginTransaction();
    try {
        $auth=live_identity($db,$auth['claims'],true);dispatch_authorize($db,$auth,$order);
        $row=query($db,"SELECT id FROM mk_orders WHERE id=? AND status IN ('CONFIRMED','PACKING','READY') FOR UPDATE",[$order])->fetch();
        if (!$row) throw new ApiError(409,'ORDER_NOT_READY','Confirm the order before entering parcel measurements.');
        if (query($db,"SELECT id FROM mk_provider_requests WHERE order_id=? AND state IN ('SENDING','UNKNOWN','BOOKED')",[$order])->fetch()) throw new ApiError(409,'PROVIDER_IN_PROGRESS','Reconcile the carrier request before changing the parcel.');
        query($db,'INSERT INTO mk_carrier_parcels(id,order_id,weight_g,length_mm,width_mm,height_mm,verified_by) VALUES(?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE weight_g=VALUES(weight_g),length_mm=VALUES(length_mm),width_mm=VALUES(width_mm),height_mm=VALUES(height_mm),verified_by=VALUES(verified_by)',[uuid4(),$order,...$values,$auth['user_id']]);
        $job=query($db,"SELECT id FROM mk_dispatch_jobs WHERE order_id=? AND state='MANUAL_REQUIRED' AND reason_code='PACKAGING_REQUIRED' FOR UPDATE",[$order])->fetch();
        if ($job) query($db,"UPDATE mk_dispatch_jobs SET state='READY',reason_code=NULL,next_action_at=UTC_TIMESTAMP(6) WHERE id=?",[$job['id']]);$db->commit();return ['saved'=>true];
    } catch (Throwable $e) { if ($db->inTransaction()) $db->rollBack();throw $e; }
}
