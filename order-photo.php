<?php
require_once __DIR__.'/inc/fn.php';
require_once __DIR__.'/inc/salon.php';
header('Cache-Control: private, no-store, max-age=0');
$name = $_GET['file'] ?? null;
if (!order_media_name($name)) { http_response_code(404); exit; }
$q = $pdo->prepare('SELECT * FROM orders WHERE photo=? OR bill_photo=? OR pod_photo=? LIMIT 1');
$q->execute([$name,$name,$name]); $o = $q->fetch();
$allowed = false;
if ($o) {
    $u = user();
    if ($u) {
        $q=$pdo->prepare('SELECT id,role FROM users WHERE id=? AND active=1');
        $q->execute([(int)$u['id']]); $staff=$q->fetch();
        $allowed=$staff && (in_array($staff['role'],['admin','bpo'],true)
            || (in_array($staff['role'],['delivery','rider'],true) && (int)$o['delivery_user']===(int)$staff['id']));
    }
    $c=cust();
    if (!$allowed && $c && (int)$o['customer_id']===(int)$c['id']) {
        $q=$pdo->prepare('SELECT id FROM customers WHERE id=? AND active=1'); $q->execute([(int)$c['id']]);
        $allowed=(bool)$q->fetchColumn();
    }
    if (!$allowed && $o['business_id']) {
        $shop=shop_login_business($pdo);
        $allowed=$shop && (int)$shop['id']===(int)$o['business_id'];
    }
    if (!$allowed) $allowed=order_media_guest_allowed($o);
}
$file=$allowed ? order_media_path($name) : null;
if (!$file) { http_response_code(404); exit; }
order_media_stream($file);
