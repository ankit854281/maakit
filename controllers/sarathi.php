<?php
declare(strict_types=1);
namespace Maakit\Api;
use PDO;
use Throwable;
require_once __DIR__.'/dispatch.php';
require_once __DIR__.'/companyDelivery.php';
function sarathi_rider(PDO $db,array $auth): array {
    require_roles($auth,['RIDER']);
    $r=query($db,"SELECT id FROM mk_riders WHERE user_id=? AND active=1 AND kyc_status='VERIFIED'",[$auth['user_id']])->fetch();
    if (!$r) throw new ApiError(403,'RIDER_NOT_VERIFIED','Your delivery profile needs approval.');return $r;
}
function sarathi_assigned(PDO $db,array $auth,string $id): array {
    $r=sarathi_rider($db,$auth);
    $o=query($db,"SELECT o.* FROM mk_orders o JOIN mk_shipments sh ON sh.order_id=o.id WHERE o.id=? AND sh.rider_id=? AND o.status IN ('READY','DISPATCHED')",[valid_uuid($id),$r['id']])->fetch();
    if (!$o) throw new ApiError(404,'NOT_FOUND','Active assigned delivery not found.');return $o;
}
function sarathi_dashboard(PDO $db,array $auth): array {
    $r=sarathi_rider($db,$auth);$result=dispatch_view($db,$auth);
    foreach ($result['assigned'] as &$item) {
        $o=sarathi_assigned($db,$auth,$item['order_id']);
        $item['otp_required']=(bool)query($db,'SELECT order_id FROM mk_order_safety WHERE order_id=?',[$o['id']])->fetch();
        $item['drop']=json_decode($o['address_snapshot'],true,16,JSON_THROW_ON_ERROR);
        $item['delivery_fee_minor']=(string)$o['delivery_minor'];
        $item['payment_method']=$o['payment_method'];
        $item['goods_subtotal_minor']=(string)$o['subtotal_minor'];
        $item['fee_payment_state']=query($db,'SELECT state FROM mk_delivery_fee_payments WHERE order_id=?',[$o['id']])->fetchColumn()?:'UNPAID';
        $item['emergency_contacts']=query($db,'SELECT name,phone FROM mk_order_emergency_contacts WHERE order_id=? ORDER BY slot',[$o['id']])->fetchAll();
    }unset($item);
    $result['location']=query($db,"SELECT latitude,longitude,accuracy_m,updated_at,TIMESTAMPDIFF(SECOND,updated_at,UTC_TIMESTAMP(6)) AS age_seconds FROM mk_partner_locations WHERE rider_id=?",[$r['id']])->fetch()?:null;
    $result['payment_gateway_configured']=sarathi_payment_configured();
    $result['company_deliveries']=company_partner($db,$auth);
    $result['history']=query($db,"SELECT b.id,b.reference AS label,c.name AS source,b.delivered_at FROM mk_company_deliveries b JOIN mk_delivery_companies c ON c.id=b.company_id WHERE b.rider_id=? AND b.state='DELIVERED' ORDER BY b.delivered_at DESC LIMIT 20",[$r['id']])->fetchAll();
    $result['maakit_history']=query($db,"SELECT o.id,s.name AS source,o.delivered_at FROM mk_orders o JOIN mk_shipments sh ON sh.order_id=o.id JOIN mk_stores s ON s.id=o.store_id WHERE sh.rider_id=? AND o.status='DELIVERED' ORDER BY o.delivered_at DESC LIMIT 20",[$r['id']])->fetchAll();
    $result['supported_services']=['delivery']; // RIDER is the historical DELIVERY role, not passenger-ride approval.
    return $result;
}
function sarathi_location(PDO $db,array $auth,array $body): array {
    $r=sarathi_rider($db,$auth);
    if (($body['consent']??null)===false) { query($db,'DELETE FROM mk_partner_locations WHERE rider_id=?',[$r['id']]);return ['sharing'=>false]; }
    if (($body['consent']??null)!==true) throw new ApiError(400,'CONSENT_REQUIRED','Allow location sharing for active work.');
    foreach (['latitude'=>[-90,90],'longitude'=>[-180,180],'accuracy_m'=>[0,5000]] as $k=>$range) {
        $v=$body[$k]??null;
        if ((!is_int($v)&&!is_float($v))||!is_finite((float)$v)||$v<$range[0]||$v>$range[1]) throw new ApiError(400,'INVALID_LOCATION','Refresh GPS and send valid coordinates.');
    }
    $db->beginTransaction();try {
        $auth=live_identity($db,$auth['claims'],true);$r=sarathi_rider($db,$auth);
        if (!query($db,"SELECT id FROM mk_rider_duty WHERE rider_id=? AND available_until>UTC_TIMESTAMP(6)",[$r['id']])->fetch()
            && !query($db,"SELECT id FROM mk_company_deliveries WHERE rider_id=? AND state IN ('ASSIGNED','PICKED_UP')",[$r['id']])->fetch()
            && !query($db,"SELECT sh.id FROM mk_shipments sh JOIN mk_orders o ON o.id=sh.order_id WHERE sh.rider_id=? AND o.status IN ('READY','DISPATCHED')",[$r['id']])->fetch()) throw new ApiError(409,'NOT_ON_DUTY','Go online or open your assigned delivery first.');
        $old=query($db,"SELECT updated_at>DATE_SUB(UTC_TIMESTAMP(6),INTERVAL 5 SECOND) AS recent FROM mk_partner_locations WHERE rider_id=? FOR UPDATE",[$r['id']])->fetch();
        if ($old && $old['recent']) {$db->commit();return ['saved'=>false,'retry_after_seconds'=>5];}
        query($db,'INSERT INTO mk_partner_locations(rider_id,latitude,longitude,accuracy_m,updated_at) VALUES(?,?,?,?,UTC_TIMESTAMP(6)) ON DUPLICATE KEY UPDATE latitude=VALUES(latitude),longitude=VALUES(longitude),accuracy_m=VALUES(accuracy_m),updated_at=VALUES(updated_at)',[$r['id'],$body['latitude'],$body['longitude'],$body['accuracy_m']]);
        $db->commit();return ['saved'=>true];
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
function sarathi_contacts(PDO $db,array $auth,array $body): array {
    require_roles($auth,['CUSTOMER']);$id=valid_uuid($body['order_id']??null);require_ownership($db,$auth,'order',$id);
    $contacts=$body['contacts']??null;
    if (($body['consent']??null)!==true||!is_array($contacts)||!array_is_list($contacts)||count($contacts)>3) throw new ApiError(400,'INVALID_CONTACTS','Provide consent and up to three emergency contacts.');
    $clean=[];foreach($contacts as $c){
        if(!is_array($c)||!is_string($c['name']??null)||!is_string($c['phone']??null))throw new ApiError(400,'INVALID_CONTACTS','Enter a name and mobile number.');
        $name=trim($c['name']);$digits=preg_replace('/\D/','',$c['phone']);if(strlen($digits)===12&&str_starts_with($digits,'91'))$digits=substr($digits,2);
        if($name===''||preg_match_all('/./us',$name)>80||preg_match('/[\x00-\x1F\x7F]/',$name)||!preg_match('/^[6-9][0-9]{9}$/D',$digits))throw new ApiError(400,'INVALID_CONTACTS','Enter a contact name and valid Indian mobile number.');
        $clean[]=['name'=>$name,'phone'=>'+91'.$digits];
    }
    $db->beginTransaction();try{
        $auth=live_identity($db,$auth['claims'],true);require_roles($auth,['CUSTOMER']);
        if(!query($db,"SELECT id FROM mk_orders WHERE id=? AND customer_id=? AND status NOT IN ('DELIVERED','CANCELLED','RTO') FOR UPDATE",[$id,$auth['user_id']])->fetch())throw new ApiError(409,'ORDER_CLOSED','Contacts can only be changed for your active order.');
        query($db,'DELETE FROM mk_order_emergency_contacts WHERE order_id=?',[$id]);
        foreach($clean as $i=>$c)query($db,'INSERT INTO mk_order_emergency_contacts(order_id,slot,name,phone) VALUES(?,?,?,?)',[$id,$i+1,$c['name'],$c['phone']]);
        $db->commit();return ['saved'=>true,'count'=>count($clean)];
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
function sarathi_tracking(PDO $db,array $auth,string $id): array {
    require_roles($auth,['CUSTOMER']);$id=valid_uuid($id);require_ownership($db,$auth,'order',$id);
    $r=query($db,"SELECT l.latitude,l.longitude,l.accuracy_m,l.updated_at,TIMESTAMPDIFF(SECOND,l.updated_at,UTC_TIMESTAMP(6)) AS age_seconds FROM mk_partner_locations l JOIN mk_shipments sh ON sh.rider_id=l.rider_id JOIN mk_orders o ON o.id=sh.order_id WHERE o.id=? AND o.customer_id=? AND o.status IN ('READY','DISPATCHED')",[$id,$auth['user_id']])->fetch();
    if(!$r||(int)$r['age_seconds']>60)return ['location'=>null,'status'=>'UNAVAILABLE_OR_STALE'];return ['location'=>$r,'status'=>'FRESH'];
}
function sarathi_payment_value(string $key): string { $v=getenv($key);return is_string($v)&&$v!==''?$v:(defined($key)?(string)constant($key):''); }
function sarathi_payment_configured(): bool { return sarathi_payment_value('MAAKIT_DELIVERY_FEE_ONLINE')==='1' && preg_match('/^rzp_(test|live)_[A-Za-z0-9]+$/D',sarathi_payment_value('MAAKIT_RAZORPAY_KEY_ID'))===1 && sarathi_payment_value('MAAKIT_RAZORPAY_KEY_SECRET')!==''; }
function sarathi_razorpay(string $method,string $path,?array $body=null): array {
    if(!sarathi_payment_configured()||!function_exists('curl_init'))throw new ApiError(503,'PAYMENT_SETUP_REQUIRED','Online delivery-fee payment is not configured.');
    if(!preg_match('#^/(orders|payments)(/[A-Za-z0-9_]+)?$#D',$path))throw new \RuntimeException('Invalid provider path');
    $c=curl_init('https://api.razorpay.com/v1'.$path);$response='';
    curl_setopt_array($c,[CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_USERPWD=>sarathi_payment_value('MAAKIT_RAZORPAY_KEY_ID').':'.sarathi_payment_value('MAAKIT_RAZORPAY_KEY_SECRET'),CURLOPT_HTTPHEADER=>['Content-Type: application/json'],CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>4,CURLOPT_TIMEOUT=>15,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_WRITEFUNCTION=>static function($ch,string $bytes)use(&$response){$response.=$bytes;return strlen($response)>65536?0:strlen($bytes);}]);
    if($body!==null)curl_setopt($c,CURLOPT_POSTFIELDS,json_data($body));$ok=curl_exec($c);$http=(int)curl_getinfo($c,CURLINFO_HTTP_CODE);curl_close($c);
    if($ok===false||$http<200||$http>299)throw new ApiError(503,'PAYMENT_PROVIDER_UNAVAILABLE','Check payment status before trying again.');
    $r=json_decode($response,true,16,JSON_THROW_ON_ERROR);if(!is_array($r))throw new \RuntimeException('Provider response invalid');return $r;
}
/** Only the platform's DELIVERY FEE. Never goods subtotal, total, vendor money or hidden commission. */
function sarathi_fee_create(PDO $db,array $auth,array $body,?callable $transport=null): array {
    require_roles($auth,['CUSTOMER']);$id=valid_uuid($body['order_id']??null);require_ownership($db,$auth,'order',$id);
    if($transport===null&&!sarathi_payment_configured())throw new ApiError(503,'PAYMENT_SETUP_REQUIRED','Online delivery-fee payment is not configured.');
    $db->beginTransaction();try{
        $auth=live_identity($db,$auth['claims'],true);require_roles($auth,['CUSTOMER']);
        $o=query($db,"SELECT id,customer_id,delivery_minor,status FROM mk_orders WHERE id=? AND customer_id=? FOR UPDATE",[$id,$auth['user_id']])->fetch();
        if(!$o||in_array($o['status'],['CANCELLED','RTO'],true))throw new ApiError(409,'ORDER_CLOSED','Review the order before paying its delivery fee.');
        $amount=(int)$o['delivery_minor'];if($amount<100||$amount>10000000)throw new ApiError(409,'INVALID_FEE','This order needs a delivery-fee review.');
        $old=query($db,'SELECT * FROM mk_delivery_fee_payments WHERE order_id=? FOR UPDATE',[$id])->fetch();
        if($old){$db->commit();if(in_array($old['state'],['CREATING','RECONCILE'],true))throw new ApiError(409,'PAYMENT_RECONCILIATION','An earlier payment request needs review. Do not create another payment.');return ['state'=>$old['state'],'provider_order_id'=>$old['provider_order_id'],'amount_minor'=>(string)$old['amount_minor'],'key_id'=>sarathi_payment_value('MAAKIT_RAZORPAY_KEY_ID')];}
        $pid=uuid4();query($db,'INSERT INTO mk_delivery_fee_payments(id,order_id,customer_id,amount_minor) VALUES(?,?,?,?)',[$pid,$id,$auth['user_id'],$amount]);$db->commit();
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
    $transport=$transport??__NAMESPACE__.'\\sarathi_razorpay';
    try{
        $r=$transport('POST','/orders',['amount'=>$amount,'currency'=>'INR','receipt'=>$pid,'notes'=>['maakit_order_id'=>$id,'purpose'=>'DELIVERY_FEE_ONLY']]);
        if(!is_string($r['id']??null)||!preg_match('/^order_[A-Za-z0-9]+$/D',$r['id'])||($r['amount']??null)!==$amount||($r['currency']??null)!=='INR')throw new \RuntimeException('Provider order mismatch');
        query($db,"UPDATE mk_delivery_fee_payments SET provider_order_id=?,state='CREATED' WHERE id=? AND state='CREATING'",[$r['id'],$pid]);
        return ['state'=>'CREATED','provider_order_id'=>$r['id'],'amount_minor'=>(string)$amount,'currency'=>'INR','key_id'=>sarathi_payment_value('MAAKIT_RAZORPAY_KEY_ID')];
    }catch(Throwable $e){query($db,"UPDATE mk_delivery_fee_payments SET state='RECONCILE' WHERE id=? AND state='CREATING'",[$pid]);throw $e;}
}
function sarathi_capture(PDO $db,array $payment): array {
    if(($payment['status']??null)!=='captured'||($payment['currency']??null)!=='INR'||!is_int($payment['amount']??null)||!is_string($payment['id']??null)||!preg_match('/^pay_[A-Za-z0-9]+$/D',$payment['id'])||!is_string($payment['order_id']??null))throw new ApiError(409,'PAYMENT_NOT_CAPTURED','Payment is not confirmed as captured.');
    $db->beginTransaction();try{
        $p=query($db,'SELECT * FROM mk_delivery_fee_payments WHERE provider_order_id=? FOR UPDATE',[$payment['order_id']])->fetch();
        if(!$p||(int)$p['amount_minor']!==$payment['amount'])throw new ApiError(409,'PAYMENT_MISMATCH','Payment details do not match this delivery fee.');
        if($p['state']==='CAPTURED'&&$p['provider_payment_id']!==$payment['id'])throw new ApiError(409,'PAYMENT_MISMATCH','Another payment already settled this fee.');
        query($db,"UPDATE mk_delivery_fee_payments SET state='CAPTURED',provider_payment_id=?,captured_at=COALESCE(captured_at,UTC_TIMESTAMP(6)) WHERE id=?",[$payment['id'],$p['id']]);$db->commit();return ['state'=>'CAPTURED'];
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
function sarathi_fee_verify(PDO $db,array $auth,array $body,?callable $transport=null): array {
    require_roles($auth,['CUSTOMER']);$id=valid_uuid($body['order_id']??null);require_ownership($db,$auth,'order',$id);
    $p=query($db,'SELECT * FROM mk_delivery_fee_payments WHERE order_id=? AND customer_id=?',[$id,$auth['user_id']])->fetch();
    $pay=$body['payment_id']??null;$sig=$body['signature']??null;$secret=sarathi_payment_value('MAAKIT_RAZORPAY_KEY_SECRET');
    if(!$p||!is_string($p['provider_order_id'])||!is_string($pay)||!preg_match('/^pay_[A-Za-z0-9]+$/D',$pay)||!is_string($sig)||!preg_match('/^[a-f0-9]{64}$/D',$sig)||$secret===''||!hash_equals(hash_hmac('sha256',$p['provider_order_id'].'|'.$pay,$secret),$sig))throw new ApiError(400,'INVALID_PAYMENT_SIGNATURE','Payment could not be verified.');
    $transport=$transport??__NAMESPACE__.'\\sarathi_razorpay';$r=$transport('GET','/payments/'.$pay,null);
    if(($r['order_id']??null)!==$p['provider_order_id']||($r['id']??null)!==$pay)throw new ApiError(409,'PAYMENT_MISMATCH','Payment does not match this order.');return sarathi_capture($db,$r);
}

function sarathi_order_tools(PDO $db,array $auth,string $id): array {
    require_roles($auth,['CUSTOMER']);$id=valid_uuid($id);require_ownership($db,$auth,'order',$id);
    $o=query($db,'SELECT id,status,delivery_minor FROM mk_orders WHERE id=? AND customer_id=?',[$id,$auth['user_id']])->fetch();
    return ['order'=>$o,'contacts'=>query($db,'SELECT name,phone FROM mk_order_emergency_contacts WHERE order_id=? ORDER BY slot',[$id])->fetchAll(),'payment'=>query($db,'SELECT state,provider_order_id,amount_minor FROM mk_delivery_fee_payments WHERE order_id=? AND customer_id=?',[$id,$auth['user_id']])->fetch()?:null,'payment_gateway_configured'=>sarathi_payment_configured()];
}
