<?php
// Fake Razorpay for feepay-flow.php (CI only). Orders are kept in a temp file; payment pay_<order suffix> is "captured".
$f = sys_get_temp_dir() . '/rzp-fake-orders.json'; $O = is_file($f) ? (json_decode((string)file_get_contents($f), true) ?: []) : [];
$path = preg_replace('#^/v1/#', '', parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)); header('Content-Type: application/json');
if ($path === 'orders' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $b = json_decode((string)file_get_contents('php://input'), true); $id = 'order_T' . bin2hex(random_bytes(5));
    $O[$id] = (int)$b['amount']; file_put_contents($f, json_encode($O)); echo json_encode(['id' => $id, 'amount' => (int)$b['amount'], 'currency' => 'INR', 'status' => 'created']); exit;
}
if (preg_match('#^payments/pay_(T[a-f0-9]+)$#', $path, $m) && isset($O['order_' . $m[1]])) {
    echo json_encode(['id' => 'pay_' . $m[1], 'order_id' => 'order_' . $m[1], 'amount' => $O['order_' . $m[1]], 'status' => 'captured']); exit;
}
http_response_code(404); echo '{"error":{"description":"not found"}}';
