<?php
declare(strict_types=1);
namespace Maakit\Api;
use PDO;
use Throwable;
require_once __DIR__.'/dispatch.php';
/** Caller holds the order lock. Inventory lock order matches checkout's sorted variant IDs. */
function release_order_inventory(PDO $db,string $orderId,string $actor): void {
    $held=query($db,"SELECT r.* FROM mk_inventory_reservations r JOIN mk_order_items i ON i.id=r.order_item_id WHERE i.order_id=? AND r.status='HELD' ORDER BY i.variant_id FOR UPDATE",[$orderId])->fetchAll();
    foreach ($held as $r) {
        query($db,'SELECT id FROM mk_inventory WHERE id=? FOR UPDATE',[$r['inventory_id']]);
        if (query($db,'UPDATE mk_inventory SET reserved=reserved-?,version=version+1 WHERE id=? AND reserved>=?',[$r['quantity'],$r['inventory_id'],$r['quantity']])->rowCount()!==1) throw new \RuntimeException('Inventory invariant violated');
        query($db,"UPDATE mk_inventory_reservations SET status='RELEASED' WHERE id=?",[$r['id']]);
        row_insert($db,'mk_inventory_movements',['id'=>uuid4(),'inventory_id'=>$r['inventory_id'],'reservation_id'=>$r['id'],'actor_id'=>$actor,'kind'=>'RELEASE','on_hand_delta'=>0,'reserved_delta'=>-(int)$r['quantity']]);
    }
}
function order_decision(PDO $db,array $auth,array $body): array {
    $id=valid_uuid($body['order_id']??null);$decision=$body['operation']??null;
    $targets=['confirm'=>'CONFIRMED','ready'=>'READY','cancel'=>'CANCELLED'];
    if (!is_string($decision)||!isset($targets[$decision])) throw new ApiError(400,'INVALID_DECISION','Choose a valid order action.');
    $db->beginTransaction();
    try {
        $auth=live_identity($db,$auth['claims'],true);dispatch_authorize($db,$auth,$id);
        $order=query($db,'SELECT * FROM mk_orders WHERE id=? FOR UPDATE',[$id])->fetch();if (!$order) throw new ApiError(404,'NOT_FOUND','Order not found.');
        if ($order['status']===$targets[$decision]) { $db->commit();return ['status'=>$order['status']]; }
        $allowed=['confirm'=>['AWAITING_VENDOR'],'ready'=>['CONFIRMED','PACKING'],'cancel'=>['AWAITING_VENDOR','CONFIRMED','PACKING','READY']];
        if (!in_array($order['status'],$allowed[$decision],true)) throw new ApiError(409,'INVALID_TRANSITION','Refresh the order before changing its status.');
        if ($decision==='cancel') {
            if (query($db,'SELECT id FROM mk_shipments WHERE order_id=?',[$id])->fetch()||query($db,"SELECT id FROM mk_provider_requests WHERE order_id=? AND state IN ('SENDING','UNKNOWN','BOOKED')",[$id])->fetch()) throw new ApiError(409,'CANCELLATION_REVIEW_REQUIRED','Confirm cancellation with the assigned delivery partner first.');
            release_order_inventory($db,$id,$auth['user_id']);
            query($db,"UPDATE mk_dispatch_attempts SET status='REJECTED' WHERE order_id=? AND status='OFFERED'",[$id]);query($db,'DELETE FROM mk_rider_slots WHERE order_id=?',[$id]);
            query($db,"UPDATE mk_dispatch_jobs SET state='CANCELLED' WHERE order_id=?",[$id]);
        } else {
            $expired=query($db,"SELECT r.id FROM mk_inventory_reservations r JOIN mk_order_items i ON i.id=r.order_item_id WHERE i.order_id=? AND r.status='HELD' AND r.expires_at<=UTC_TIMESTAMP(6) LIMIT 1",[$id])->fetch();
            if ($decision==='confirm' && $expired) throw new ApiError(409,'RESERVATION_EXPIRED','Ask the customer to refresh the order after checking availability.');
        }
        query($db,'UPDATE mk_orders SET status=? WHERE id=?',[$targets[$decision],$id]);row_insert($db,'mk_order_events',['id'=>uuid4(),'order_id'=>$id,'actor_id'=>$auth['user_id'],'status'=>$targets[$decision]]);
        dispatch_event($db,$order,'ORDER_'.$targets[$decision]);$db->commit();return ['status'=>$targets[$decision]];
    } catch (Throwable $e) { if ($db->inTransaction()) $db->rollBack();throw $e; }
}
function expire_reservations(PDO $db): int {
    $ids=query($db,"SELECT DISTINCT o.id,o.customer_id FROM mk_orders o JOIN mk_order_items i ON i.order_id=o.id JOIN mk_inventory_reservations r ON r.order_item_id=i.id WHERE o.status='AWAITING_VENDOR' AND r.status='HELD' AND r.expires_at<=UTC_TIMESTAMP(6) ORDER BY o.id LIMIT 20")->fetchAll();$count=0;
    foreach ($ids as $row) {
        $db->beginTransaction();
        try {
            $order=query($db,"SELECT * FROM mk_orders WHERE id=? AND status='AWAITING_VENDOR' FOR UPDATE",[$row['id']])->fetch();
            if ($order) {
                $expired=query($db,"SELECT r.id FROM mk_inventory_reservations r JOIN mk_order_items i ON i.id=r.order_item_id WHERE i.order_id=? AND r.status='HELD' AND r.expires_at<=UTC_TIMESTAMP(6) LIMIT 1",[$order['id']])->fetch();
                if ($expired) {
                    release_order_inventory($db,$order['id'],$order['customer_id']);query($db,"UPDATE mk_orders SET status='CANCELLED' WHERE id=?",[$order['id']]);
                    row_insert($db,'mk_order_events',['id'=>uuid4(),'order_id'=>$order['id'],'actor_id'=>null,'status'=>'RESERVATION_EXPIRED']);dispatch_event($db,$order,'RESERVATION_EXPIRED');$count++;
                }
            }$db->commit();
        } catch (Throwable $e) { if ($db->inTransaction()) $db->rollBack();throw $e; }
    }return $count;
}
