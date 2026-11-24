<?php
// School fee — Razorpay webhook (24 Nov 2026). If the parent's browser closes after paying, the receipt is still made.
// Razorpay dashboard (SCHOOL's account) > Webhooks: URL https://maakit.in/school/pay_webhook.php, events "payment.captured"
// and "order.paid", secret = config.php SCHOOL_RZP_WEBHOOK_SECRET. Same fp_settle() as pay.php — never two receipts.
declare(strict_types=1);
require __DIR__ . '/inc.php';
require __DIR__ . '/registers_lib.php';
require __DIR__ . '/feecollect_lib.php';
require __DIR__ . '/feepay_lib.php';

header('Content-Type: application/json');
try { sch_db(); } catch (Throwable $e) { http_response_code(503); echo '{"ok":false}'; exit; }
$body = (string)file_get_contents('php://input', false, null, 0, 200000);
$sig = (string)($_SERVER['HTTP_X_RAZORPAY_SIGNATURE'] ?? '');
$secret = fp_cfg('SCHOOL_RZP_WEBHOOK_SECRET');
if ($secret === '' || $sig === '' || !hash_equals(hash_hmac('sha256', $body, $secret), $sig)) { http_response_code(401); echo '{"ok":false}'; exit; }
$j = json_decode($body, true);
$pay = $j['payload']['payment']['entity'] ?? null;
if (in_array($j['event'] ?? '', ['payment.captured', 'order.paid'], true) && is_array($pay) && ($pay['status'] ?? '') === 'captured' && !empty($pay['order_id'])) {
    if (!sch_rec_get('ofcPay', (string)$pay['order_id'])) { echo '{"ok":true,"ignored":"not a school fee order"}'; exit; }
    try { $O = fp_settle((string)$pay['order_id'], (string)$pay['id'], (int)$pay['amount'], 'webhook'); echo json_encode(['ok' => true, 'status' => $O['status']]); }
    catch (Throwable $e) { error_log('school pay webhook: ' . $e->getMessage()); http_response_code(500); echo '{"ok":false}'; }
    exit;
}
echo '{"ok":true,"ignored":true}';
