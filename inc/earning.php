<?php
// Goods belong to shops. Only completed deliveries and explicit shop commission
// are Maakit revenue. Missing costs must never be interpreted as zero expense.
function earning_cost($value) {
    if (!is_scalar($value) || !preg_match('/^\d{1,7}$/', (string)$value)) return null;
    $n = (int)$value;
    return $n <= 1000000 ? $n : null;
}
function earning_date($value) {
    if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) return false;
    [$y, $m, $d] = array_map('intval', explode('-', $value));
    return checkdate($m, $d, $y);
}
function goods_paid_to_shop($payment) {
    return stripos((string)$payment, 'upi') !== false;
}
function earning_days(PDO $pdo, $from, $to) {
    $st = $pdo->prepare("SELECT DATE(COALESCE(delivered_at, created_at)) d, COUNT(*) delivered,
        SUM(COALESCE(delivery_charge,0)) delivery,
        SUM(delivery_charge IS NULL) unpriced
        FROM orders WHERE status IN ('Delivered','Paisa jama')
        AND DATE(COALESCE(delivered_at,created_at)) BETWEEN ? AND ? GROUP BY d");
    $st->execute([$from,$to]); $orders = array_column($st->fetchAll(), null, 'd');
    // The ledger records the agreed commission; never recompute using today's percentage.
    $st = $pdo->prepare("SELECT DATE(COALESCE(o.delivered_at,o.created_at)) d, SUM(-l.amount) commission
        FROM shop_ledger l JOIN orders o ON o.id=l.order_id
        WHERE l.kind='commission' AND l.source='maakit' AND o.status IN ('Delivered','Paisa jama')
        AND DATE(COALESCE(o.delivered_at,o.created_at)) BETWEEN ? AND ? GROUP BY d");
    $st->execute([$from,$to]); $commission = array_column($st->fetchAll(), null, 'd');
    $st = $pdo->prepare('SELECT * FROM operating_costs WHERE cost_date BETWEEN ? AND ?');
    $st->execute([$from,$to]); $costs = array_column($st->fetchAll(), null, 'cost_date');
    $days=[];
    for ($d=$to; $d >= $from; $d=date('Y-m-d',strtotime($d . ' -1 day'))) {
        $o=$orders[$d] ?? [];
        $cost=$costs[$d] ?? null;
        $revenue=(int)($o['delivery'] ?? 0)+(int)($commission[$d]['commission'] ?? 0);
        $expense=$cost ? (int)$cost['fuel']+(int)$cost['staff']+(int)$cost['other'] : null;
        $days[]=['date'=>$d,'delivered'=>(int)($o['delivered'] ?? 0),
            'delivery'=>(int)($o['delivery'] ?? 0),'commission'=>(int)($commission[$d]['commission'] ?? 0),
            'unpriced'=>(int)($o['unpriced'] ?? 0),'revenue'=>$revenue,'cost'=>$cost,'expense'=>$expense,
            'balance'=>$expense !== null && empty($o['unpriced']) ? $revenue-$expense : null];
    }
    return $days;
}
