<?php
require_once __DIR__ . '/../inc/fn.php';
need_role('admin');
$day = get('day', '');
$sql = "SELECT * FROM orders WHERE 1"; $args = [];
if ($day) { $sql .= " AND DATE(created_at)=?"; $args[] = $day; }
$sql .= " ORDER BY id DESC";
$st = $pdo->prepare($sql); $st->execute($args);
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=maakit-orders-' . ($day ?: 'all') . '.csv');
$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF");
fputcsv($out, ['Order No','Code','Source','Naam','Mobile','Gaon','Pehchaan','Saaman','Dukaan','Bazaar','Delivery Rs','Saaman Rs','Payment','Status','Tareekh']);
while ($o = $st->fetch()) {
    fputcsv($out, [$o['order_no'],$o['code'],$o['source'],$o['customer_name'],$o['mobile'],$o['village'],$o['landmark'],
        $o['items'],$o['shop'],$o['market'],$o['delivery_charge'],$o['goods_amount'],$o['payment'],$o['status'],$o['created_at']]);
}
fclose($out);
