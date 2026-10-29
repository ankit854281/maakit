<?php
function sr_vehicles_upgrade(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS sr_vehicles (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,account_id BIGINT UNSIGNED NOT NULL,vehicle_type VARCHAR(10) NOT NULL,model VARCHAR(60) NOT NULL,rc_number VARCHAR(30) NOT NULL,UNIQUE KEY(account_id,rc_number),FOREIGN KEY(account_id) REFERENCES sr_accounts(id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}
function sr_vehicles(PDO $pdo,array $a): array {
    sr_vehicles_upgrade($pdo); $p=sr_profile($pdo,(int)$a['id']);
    if($p && !empty($p['vehicle_number'])) $pdo->prepare('INSERT IGNORE INTO sr_vehicles(account_id,vehicle_type,model,rc_number) VALUES(?,?,?,?)')->execute([$a['id'],$p['vehicle_type'],$p['vehicle_model'] ?? $p['vehicle_type'],$p['vehicle_number']]);
    $q=$pdo->prepare('SELECT * FROM sr_vehicles WHERE account_id=? ORDER BY id'); $q->execute([$a['id']]); return $q->fetchAll();
}
function sr_vehicle_change(PDO $pdo,array $a,int $id): void {
    sr_vehicles_upgrade($pdo); sr_docs_upgrade($pdo); sr_jobs_upgrade($pdo);
    $pdo->beginTransaction();
    try {
        $q=$pdo->prepare('SELECT * FROM sr_rider_profiles WHERE account_id=? FOR UPDATE'); $q->execute([$a['id']]); $p=$q->fetch();
        if(!$p || $p['status']==='suspended') throw new RuntimeException('No profile');
        if($p['rider_id']) {
            $pdo->prepare('SELECT id FROM riders WHERE id=? FOR UPDATE')->execute([$p['rider_id']]);
            $q=$pdo->prepare("SELECT id FROM jobs WHERE (rider_id=? AND status IN ('assigned','picked')) OR (offer_rider=? AND offer_until>NOW()) LIMIT 1");
            $q->execute([$p['rider_id'],$p['rider_id']]); if($q->fetch()) throw new RuntimeException('Active job');
        }
        $q=$pdo->prepare('SELECT * FROM sr_vehicles WHERE id=? AND account_id=?'); $q->execute([$id,$a['id']]); $v=$q->fetch();
        if(!$v) throw new RuntimeException('Vehicle missing');
        $pdo->prepare('UPDATE sr_rider_profiles SET rc_photo=IF(vehicle_number=?,rc_photo,\'\'),vehicle_type=?,vehicle_model=?,vehicle_number=? WHERE account_id=?')->execute([$v['rc_number'],$v['vehicle_type'],$v['model'],$v['rc_number'],$a['id']]);
        if($p['rider_id']) $pdo->prepare('UPDATE riders SET vtype=?,vehicle=?,rc_number=?,do_ride=?,do_delivery=?,ready_until=NULL WHERE id=?')->execute([$v['vehicle_type'],$v['model'],$v['rc_number'],sr_vehicle_does_ride($v['vehicle_type']),sr_vehicle_does_delivery($v['vehicle_type']),$p['rider_id']]);
        $pdo->commit();
    } catch(Throwable $e) { if($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
}
