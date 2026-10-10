<?php
require_once __DIR__.'/order-quotes.php';
// Explicit area opt-in. Confirmed orders only, fresh duty lease, one active
// order per automatically selected driver. No GPS/nearest-driver claim.
function dispatch_assign(PDO $pdo,$orderid,$manualDriver=0) {
    if($pdo->inTransaction())return null;
    try{
        $pdo->beginTransaction();
        $s=$pdo->prepare('SELECT id,village,status,delivery_user FROM orders WHERE id=? FOR UPDATE');$s->execute([(int)$orderid]);$order=$s->fetch();
        if(!$order||!in_array($order['status'],$manualDriver?['Naya','Confirm','Assign']:['Confirm'],true)||(!$manualDriver&&$order['delivery_user'])){$pdo->rollBack();return null;}
        $guard=$pdo->prepare('SELECT id FROM orders WHERE id=? AND '.quote_guard());$guard->execute([$orderid]);if(!$guard->fetch()){$pdo->rollBack();return null;}
        if($manualDriver)$ids=[(int)$manualDriver];
        else{
            $s=$pdo->prepare("SELECT d.user_id FROM service_area_drivers d JOIN villages v ON v.id=d.village_id JOIN service_area_meta m ON m.village_id=v.id JOIN service_area_dispatch a ON a.village_id=v.id JOIN driver_availability f ON f.user_id=d.user_id WHERE v.name=? AND v.live=1 AND m.delivery_on=1 AND a.enabled=1 AND f.available_until>UTC_TIMESTAMP() ORDER BY d.user_id LIMIT 100");$s->execute([$order['village']]);$ids=$s->fetchAll(PDO::FETCH_COLUMN);
        }
        foreach($ids as $driver){
            // Manual and automatic dispatch share this lock, including across areas.
            $s=$pdo->prepare("SELECT id FROM users WHERE id=? AND role IN ('delivery','rider') AND active=1 FOR UPDATE");$s->execute([$driver]);if(!$s->fetch())continue;
            if(!$manualDriver){
                $s=$pdo->prepare("SELECT id FROM orders WHERE delivery_user=? AND status IN ('Naya','Confirm','Assign','Pickup') LIMIT 1 FOR UPDATE");$s->execute([$driver]);if($s->fetch())continue;
            }
            $sql="UPDATE orders o JOIN villages v ON v.name=o.village JOIN service_area_drivers d ON d.village_id=v.id AND d.user_id=? JOIN users u ON u.id=d.user_id AND u.role IN ('delivery','rider') AND u.active=1";
            if(!$manualDriver)$sql.=" JOIN service_area_meta m ON m.village_id=v.id AND m.delivery_on=1 JOIN service_area_dispatch a ON a.village_id=v.id AND a.enabled=1 JOIN driver_availability f ON f.user_id=u.id AND f.available_until>UTC_TIMESTAMP()";
            $sql.=" SET o.delivery_user=u.id,o.status='Assign',o.assigned_at=COALESCE(o.assigned_at,NOW()) WHERE o.id=?";
            if(!$manualDriver)$sql.=" AND v.live=1 AND o.status='Confirm' AND o.delivery_user IS NULL";
            else $sql.=" AND o.status IN ('Naya','Confirm','Assign')";
            $sql.=' AND '.quote_guard('o');
            $s=$pdo->prepare($sql);$s->execute([$driver,$orderid]);
            if($s->rowCount()){$pdo->prepare('DELETE FROM live_tracks WHERE order_id=?')->execute([$orderid]);$pdo->commit();return (int)$driver;}
        }
        $pdo->rollBack();return null;
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();error_log('Dispatch deferred: '.$e->getMessage());return null;}
}
function dispatch_queue(PDO $pdo) {
    // Retry a bounded queue on duty/confirmation/completion events; no cron needed.
    $ids=$pdo->query("SELECT o.id FROM orders o JOIN villages v ON v.name=o.village JOIN service_area_dispatch a ON a.village_id=v.id AND a.enabled=1 JOIN service_area_meta m ON m.village_id=v.id AND m.delivery_on=1 WHERE ".quote_guard('o')." AND v.live=1 AND o.status='Confirm' AND o.delivery_user IS NULL AND EXISTS (SELECT 1 FROM service_area_drivers d JOIN users u ON u.id=d.user_id AND u.role IN ('delivery','rider') AND u.active=1 JOIN driver_availability f ON f.user_id=u.id AND f.available_until>UTC_TIMESTAMP() WHERE d.village_id=v.id AND NOT EXISTS (SELECT 1 FROM orders busy WHERE busy.delivery_user=u.id AND busy.status IN ('Naya','Confirm','Assign','Pickup'))) ORDER BY o.id LIMIT 20")->fetchAll(PDO::FETCH_COLUMN);
    foreach($ids as $id)dispatch_assign($pdo,(int)$id);
}
