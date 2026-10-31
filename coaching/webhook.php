<?php
// Coaching Saathi — Razorpay webhook. Browser band ho jaye tab bhi payment par app khul jaye.
// Razorpay dashboard > Webhooks: URL https://maakit.in/coaching/webhook.php, event "payment.captured",
// secret = config.php ka CS_RAZORPAY_WEBHOOK_SECRET.
require __DIR__ . '/inc.php';

$body = (string)file_get_contents('php://input');
$sig = (string)($_SERVER['HTTP_X_RAZORPAY_SIGNATURE'] ?? '');
$secret = (string)cs_cfg('CS_RAZORPAY_WEBHOOK_SECRET');
header('Content-Type: application/json');
if ($secret === '' || $sig === '' || !hash_equals(hash_hmac('sha256', $body, $secret), $sig)) { http_response_code(401); echo '{"ok":false}'; exit; }
$j = json_decode($body, true);
$pay = $j['payload']['payment']['entity'] ?? null;
if (in_array($j['event'] ?? '', ['payment.captured', 'order.paid'], true) && is_array($pay) && ($pay['status'] ?? '') === 'captured' && !empty($pay['order_id'])) {
    $ok = cs_sub_mark_paid((string)$pay['order_id'], (string)$pay['id'], (int)$pay['amount']);
    echo json_encode(['ok' => $ok]); exit;
}
echo '{"ok":true,"ignored":true}';
