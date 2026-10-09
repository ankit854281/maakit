<?php
declare(strict_types=1);
namespace Maakit\Api;
use PDO;
use Throwable;
require_once __DIR__.'/checkout.php';
function request_wholesale_quote(PDO $db,array $auth,array $body,string $key): array {
    $offer=valid_uuid($body['offer_id']??null,'offer_id');$key=valid_uuid($key,'idempotency_key');
    $quantity=$body['quantity']??null;$message=$body['message']??'';
    if (!is_int($quantity)||$quantity<1||$quantity>1000000||!is_string($message)||strlen($message)>2000) throw new ApiError(400,'INVALID_RFQ','Enter a valid quantity and a short requirement.');
    $fingerprint=hash('sha256',json_data([$offer,$quantity,trim($message)]));$db->beginTransaction();
    try {
        $auth=live_identity($db,$auth['claims'],true);require_roles($auth,['CUSTOMER','B2B_BUYER']);
        $old=query($db,'SELECT * FROM mk_rfq_requests WHERE buyer_id=? AND idempotency_key=? FOR UPDATE',[$auth['user_id'],$key])->fetch();
        if ($old) { if (!hash_equals($old['fingerprint'],$fingerprint)) throw new ApiError(409,'IDEMPOTENCY_CONFLICT','Use a new request key.');$db->commit();return ['rfq_id'=>$old['rfq_id'],'replayed'=>true]; }
        $seller=query($db,"SELECT o.id FROM mk_offers o JOIN mk_stores s ON s.id=o.store_id JOIN mk_vendors v ON v.id=s.vendor_id WHERE o.id=? AND o.is_b2b=1 AND o.active=1 AND s.active=1 AND v.status='ACTIVE' FOR UPDATE",[$offer])->fetch();
        if (!$seller) throw new ApiError(404,'NOT_FOUND','Wholesale supplier not available.');
        $id=uuid4();query($db,'INSERT INTO mk_b2b_rfqs(id,buyer_id,offer_id,quantity,message) VALUES(?,?,?,?,?)',[$id,$auth['user_id'],$offer,$quantity,trim($message)]);
        query($db,'INSERT INTO mk_rfq_requests(id,rfq_id,buyer_id,idempotency_key,fingerprint) VALUES(?,?,?,?,?)',[uuid4(),$id,$auth['user_id'],$key,$fingerprint]);
        row_insert($db,'mk_audit_events',['id'=>uuid4(),'actor_id'=>$auth['user_id'],'action'=>'RFQ_CREATED','resource_id'=>$id,'details_json'=>json_data(['offer_id'=>$offer])]);
        $db->commit();return ['rfq_id'=>$id,'replayed'=>false];
    } catch (Throwable $e) { if ($db->inTransaction()) $db->rollBack();throw $e; }
}
