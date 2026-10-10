<?php
declare(strict_types=1);
namespace Maakit\Api;
use PDO;
use Throwable;
require_once __DIR__.'/orderLifecycle.php';
require_once __DIR__.'/deliverySafety.php';
/** Existing login.php/public/auth.php verify hashed credentials and exchange the PHP session. */
function rider_progress(PDO $db,array $auth,array $body): array {
    $id=valid_uuid($body['order_id']??null);$operation=$body['operation']??null;
    if (!is_string($operation)||!in_array($operation,['pickup','complete'],true)) throw new ApiError(400,'INVALID_DECISION','Choose pickup or completion.');
    $db->beginTransaction();
    try {
        $auth=live_identity($db,$auth['claims'],true);require_roles($auth,['RIDER']);
        // Match dispatch lock order: identity -> order -> job -> rider -> slot.
        $order=query($db,'SELECT * FROM mk_orders WHERE id=? FOR UPDATE',[$id])->fetch();
        $job=query($db,'SELECT * FROM mk_dispatch_jobs WHERE order_id=? FOR UPDATE',[$id])->fetch();
        $shipment=query($db,'SELECT * FROM mk_shipments WHERE order_id=? FOR UPDATE',[$id])->fetch();
        $rider=query($db,"SELECT id FROM mk_riders WHERE user_id=? AND active=1 AND kyc_status='VERIFIED' FOR UPDATE",[$auth['user_id']])->fetch();
        if (!$order||!$shipment||!$rider||$shipment['rider_id']!==$rider['id']||!in_array($shipment['provider'],['MERCHANT_STAFF','LOCAL_POOL'],true)) throw new ApiError(404,'NOT_FOUND','Assigned delivery not found.');
        $target=$operation==='pickup'?'DISPATCHED':'DELIVERED';
        if ($order['status']===$target || ($operation==='pickup' && $order['status']==='DELIVERED')) { $db->commit();return ['status'=>$order['status'],'replayed'=>true]; }
        if (!$job||$job['state']!=='ASSIGNED'||$order['status']!==($operation==='pickup'?'READY':'DISPATCHED')) throw new ApiError(409,'INVALID_TRANSITION','Confirm pickup before completing the delivery. Refresh the order.');
        if (!query($db,"SELECT id FROM mk_rider_slots WHERE order_id=? AND rider_id=? AND state='ASSIGNED' FOR UPDATE",[$id,$rider['id']])->fetch()) throw new ApiError(409,'ASSIGNMENT_CHANGED','Your assignment changed. Refresh the list.');
        order_safety_check($db,$id,$operation,$body);
        if ($operation==='pickup') {
            // Seller-owned tracked stock leaves the shop once, at pickup. No platform payment collection.
            $held=query($db,"SELECT r.* FROM mk_inventory_reservations r JOIN mk_order_items i ON i.id=r.order_item_id WHERE i.order_id=? ORDER BY i.variant_id FOR UPDATE",[$id])->fetchAll();
            foreach ($held as $reservation) {
                if ($reservation['status']!=='HELD') throw new ApiError(409,'RESERVATION_UNAVAILABLE','The shop must review this order’s stock before pickup.');
                query($db,'SELECT id FROM mk_inventory WHERE id=? FOR UPDATE',[$reservation['inventory_id']]);
                $qty=(int)$reservation['quantity'];
                if (query($db,'UPDATE mk_inventory SET on_hand=on_hand-?,reserved=reserved-?,version=version+1 WHERE id=? AND on_hand>=? AND reserved>=?',[$qty,$qty,$reservation['inventory_id'],$qty,$qty])->rowCount()!==1) throw new ApiError(409,'RESERVATION_UNAVAILABLE','The shop must review this order’s stock before pickup.');
                query($db,"UPDATE mk_inventory_reservations SET status='CONSUMED' WHERE id=?",[$reservation['id']]);
                row_insert($db,'mk_inventory_movements',['id'=>uuid4(),'inventory_id'=>$reservation['inventory_id'],'reservation_id'=>$reservation['id'],'actor_id'=>$auth['user_id'],'kind'=>'SALE','on_hand_delta'=>-$qty,'reserved_delta'=>-$qty]);
            }
            query($db,'UPDATE mk_shipments SET dispatched_at=UTC_TIMESTAMP(6) WHERE id=?',[$shipment['id']]);
            query($db,"UPDATE mk_orders SET status='DISPATCHED' WHERE id=?",[$id]);
        } else {
            query($db,"UPDATE mk_orders SET status='DELIVERED',delivered_at=UTC_TIMESTAMP(6) WHERE id=?",[$id]);
            query($db,"UPDATE mk_dispatch_jobs SET state='COMPLETED',reason_code=NULL WHERE id=?",[$job['id']]);
            query($db,'DELETE FROM mk_rider_slots WHERE order_id=? AND rider_id=?',[$id,$rider['id']]);
        }
        row_insert($db,'mk_order_events',['id'=>uuid4(),'order_id'=>$id,'actor_id'=>$auth['user_id'],'status'=>$target]);
        dispatch_event($db,$order,'ORDER_'.$target);$db->commit();return ['status'=>$target,'replayed'=>false];
    } catch (Throwable $e) { if ($db->inTransaction()) $db->rollBack();throw $e; }
}

