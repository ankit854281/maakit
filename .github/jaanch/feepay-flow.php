<?php
// ============================================================
//  जाँच — Online fee payment from the demand bill QR (school/pay.php, feepay_lib.php, 24 Nov 2026)
//
//  Keys off → bill has no pay link. Keys on (second server + fake Razorpay) → bill carries a signed link →
//  pay page shows today's payable (siblings, old due) → order → checkout signature → receipts made in the
//  Fee Receipt series (Online, deposit bank), ledgers paid, old due put on the card → checkout + webhook
//  again make NO second receipt → bad signature / forged link refused → cash taken meanwhile = "excess".
// ============================================================
require_once __DIR__ . '/../../config.php';
if (DB_NAME !== 'maakit_jaanch') throw new RuntimeException('CI database only');
require_once __DIR__ . '/../../inc/db.php';
require_once __DIR__ . '/../../school/inc.php';
function fpc($ok, $m) { if (!$ok) throw new RuntimeException('Fee pay flow: ' . $m); }
function fph(int $port, string $path, ?array $post = null, ?string $tok = null, array $hdr = []): array {
    $c = curl_init('http://127.0.0.1:' . $port . $path);
    curl_setopt_array($c, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30, CURLOPT_PROXY => '', CURLOPT_HEADER => true, CURLOPT_HTTPHEADER => array_merge(['X-School-Token: ' . ($tok ?? '')], $hdr)]
        + ($post !== null ? [CURLOPT_POST => true, CURLOPT_POSTFIELDS => isset($post['__raw']) ? $post['__raw'] : http_build_query($post)] : []));
    $raw = (string)curl_exec($c); $code = curl_getinfo($c, CURLINFO_HTTP_CODE); $hs = curl_getinfo($c, CURLINFO_HEADER_SIZE); curl_close($c);
    $head = substr($raw, 0, $hs); $body = substr($raw, $hs);
    fpc(!preg_match('/Fatal error|Warning:|Uncaught|Deprecated|Notice:/', $body), "PHP error at $path: " . substr($body, 0, 400));
    preg_match('/^Location:\s*(.+)$/mi', $head, $m);
    return [$code, $body, trim($m[1] ?? ''), json_decode($body, true)];
}
$login = function (array $b) { $c = curl_init('http://127.0.0.1:8099/school/api.php?a=login'); curl_setopt_array($c, [CURLOPT_RETURNTRANSFER => true, CURLOPT_PROXY => '', CURLOPT_POST => true, CURLOPT_HTTPHEADER => ['Content-Type: application/json'], CURLOPT_POSTFIELDS => json_encode($b)]); $j = json_decode((string)curl_exec($c), true); curl_close($c); return $j['token'] ?? null; };

foreach (['sch_sessions', 'sch_users', 'sch_records', 'sch_log', 'sch_login_fail'] as $t) $pdo->exec("DELETE FROM $t");
$put = fn($k, $r, $d) => sch_rec_put($k, $r, $d, 'principal');
$put('settings', 'main', ['school' => 'Pay Test School', 'session' => '2026-27']);
$put('student', 'p1', ['id' => 'p1', 'name' => 'Aarav Mishra', 'cls' => '2nd', 'section' => 'A', 'adm' => '501', 'father' => 'Ravi Mishra', 'phone' => '9000011111']);
$put('student', 'p2', ['id' => 'p2', 'name' => 'Anvi Mishra', 'cls' => 'LKG', 'section' => 'A', 'adm' => '502', 'father' => 'Ravi Mishra', 'phone' => '9000011111']);
$put('ofcCfg', 'main', ['sessions' => ['2026-27' => ['from' => '2026-04-01', 'by' => 'test', 'at' => 1]]]);
$put('ofcSeq', '2026-27', ['prefix' => '1-', 'n' => 400]);
$L = fn($sid, $name, $cls, $heads, $old = 0) => ['session' => '2026-27', 'sid' => $sid, 'oldId' => '', 'name' => $name, 'cls' => $cls, 'section' => 'A', 'adm' => $sid === 'p1' ? '501' : '502', 'father' => 'Ravi Mishra', 'mother' => '', 'mobile' => '9000011111', 'scheme' => '', 'oldFee' => $old, 'transferFee' => 0, 'heads' => $heads];
// paise. Aarav: April paid; November tuition 600 + transport 500 due; March tuition (after the bill's month) not billed.
$put('ofcLed', '2026-27|p1', $L('p1', 'Aarav Mishra', '2nd', [['i' => 1, 'm' => 4, 'name' => 'Tution Fee', 'amt' => 60000, 'paid' => 60000, 'na' => false],
    ['i' => 2, 'm' => 11, 'name' => 'Tution Fee', 'amt' => 60000, 'paid' => 0, 'na' => false], ['i' => 3, 'm' => 11, 'name' => 'Transport', 'amt' => 50000, 'paid' => 0, 'na' => false],
    ['i' => 4, 'm' => 3, 'name' => 'Tution Fee', 'amt' => 60000, 'paid' => 0, 'na' => false]]));
// Anvi: old ERP old fee 200 (not on the card yet) + April tuition 700
$put('ofcLed', '2026-27|p2', $L('p2', 'Anvi Mishra', 'LKG', [['i' => 1, 'm' => 4, 'name' => 'Tution Fee', 'amt' => 70000, 'paid' => 0, 'na' => false]], 20000));
$pdo->prepare('INSERT INTO sch_users (uid, role, tid, pin_hash, must_change) VALUES (?,?,?,?,0)')->execute(['chairman', 'chairman', null, password_hash('6666', PASSWORD_DEFAULT)]);
$CH = $login(['role' => 'chairman', 'pin' => '6666']); fpc($CH, 'chairman login');

// ---- keys off: no pay link, pay page says office ----
[$c, , , $j] = fph(8099, '/school/demandbill_api.php?a=bill&sid=p1&upto=11', null, $CH); fpc($c === 200 && $j['pay'] === '' && count($j['kids']) === 2, 'keys off → no pay link: ' . json_encode($j['pay'] ?? $j));
[$c, $body] = fph(8099, '/school/pay.php?b=abc.def'); fpc($c === 200 && str_contains($body, 'not valid'), 'forged link refused');

// ---- keys on: second server + fake Razorpay ----
$root = realpath(__DIR__ . '/../..'); @unlink(sys_get_temp_dir() . '/rzp-fake-orders.json');
$SEC = 'test_secret_' . bin2hex(random_bytes(4)); $WH = 'wh_' . bin2hex(random_bytes(4));
$env = array_merge(getenv(), ['SCHOOL_RZP_KEY_ID' => 'rzp_test_x', 'SCHOOL_RZP_KEY_SECRET' => $SEC, 'SCHOOL_RZP_WEBHOOK_SECRET' => $WH, 'SCHOOL_PAY_SECRET' => 'ci-pay-secret',
    'SCHOOL_RZP_API' => 'http://127.0.0.1:8094/v1/', 'no_proxy' => '127.0.0.1', 'NO_PROXY' => '127.0.0.1']);
$fake = proc_open([PHP_BINARY, '-S', '127.0.0.1:8094', __DIR__ . '/rzp-fake/index.php'], [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $p1, null, $env);
$srv  = proc_open([PHP_BINARY, '-S', '127.0.0.1:8093', '-t', $root], [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $p2, null, $env);
try {
    for ($i = 0; $i < 50; $i++) { $s = @fsockopen('127.0.0.1', 8093); $f = @fsockopen('127.0.0.1', 8094); if ($s && $f) break; usleep(100000); }
    [$c, , , $j] = fph(8093, '/school/demandbill_api.php?a=bill&sid=p1&upto=11', null, $CH);
    fpc($c === 200 && preg_match('#/school/pay\.php\?b=([A-Za-z0-9_.-]+)$#', $j['pay'], $m), 'keys on → signed pay link on the bill: ' . ($j['pay'] ?? ''));
    $b = $m[1];
    [$c, , , $j2] = fph(8093, '/school/demandbill_api.php?a=bulk&due=0&upto=11', null, $CH); fpc($c === 200 && count($j2['pays']) === count($j2['bills']) && $j2['pays'][0] !== '', 'bulk bills carry pay links');
    [$c, $body] = fph(8093, '/school/pay.php?b=' . substr($b, 0, -2) . 'xx'); fpc(str_contains($body, 'not valid'), 'tampered link refused');
    [$c, $body] = fph(8093, '/school/pay.php?b=' . $b);
    fpc($c === 200 && str_contains($body, 'Aarav Mishra') && str_contains($body, 'Anvi Mishra') && str_contains($body, '₹2,000') && !str_contains($body, 'March'), 'pay page: 1100 + 900 (old due 200) = 2000, March not billed');
    // wrong amount (dues changed) → no order
    [$c, $body] = fph(8093, '/school/pay.php?b=' . $b, ['a' => 'order', 'b' => $b, 'amt' => '100']); fpc(str_contains($body, 'changed just now') && !str_contains($body, 'order_T'), 'stale amount → no order');
    [$c, $body] = fph(8093, '/school/pay.php?b=' . $b, ['a' => 'order', 'b' => $b, 'amt' => '200000']);
    fpc(preg_match('/"order_id":"(order_T[a-f0-9]+)"/', $body, $m), 'order made and checkout opens'); $oid = $m[1]; $pid = 'pay_' . substr($oid, 6);
    fpc(sch_rec_get('ofcPay', $oid)['status'] === 'created', 'order kept as created');
    // bad signature → nothing
    [$c, $body] = fph(8093, '/school/pay.php?b=' . $b, ['a' => 'done', 'b' => $b, 'razorpay_order_id' => $oid, 'razorpay_payment_id' => $pid, 'razorpay_signature' => 'bad']);
    fpc(str_contains($body, 'could not confirm') && !sch_rec_get('ofcRcpt', '2026-27|1-401'), 'bad signature → no receipt');
    $sig = hash_hmac('sha256', $oid . '|' . $pid, $SEC);
    [$c, , $loc] = fph(8093, '/school/pay.php?b=' . $b, ['a' => 'done', 'b' => $b, 'razorpay_order_id' => $oid, 'razorpay_payment_id' => $pid, 'razorpay_signature' => $sig]);
    fpc($c === 303 && str_contains($loc, '&o=' . $oid), 'checkout ok → redirect to receipts: ' . $c . ' ' . $loc);
    $r1 = sch_rec_get('ofcRcpt', '2026-27|1-401'); $r2 = sch_rec_get('ofcRcpt', '2026-27|1-402');
    fpc($r1 && $r1['sid'] === 'p1' && $r1['received'] === 110000 && $r1['mode'] === 'Online' && $r1['depositBank'] === 'HDFC BANK-3039' && str_contains($r1['remark'], $pid), 'Aarav receipt 1-401 ₹1100 Online: ' . json_encode($r1));
    fpc($r2 && $r2['sid'] === 'p2' && $r2['received'] === 90000 && $r2['lines'][0][1] === 'Old Due', 'Anvi receipt 1-402 ₹900 with Old Due first: ' . json_encode($r2['lines'] ?? null));
    $A = sch_rec_get('ofcLed', '2026-27|p1'); fpc($A['heads'][1]['paid'] === 60000 && $A['heads'][2]['paid'] === 50000 && $A['heads'][3]['paid'] === 0, 'Aarav ledger: Nov paid, March still due');
    $B2 = sch_rec_get('ofcLed', '2026-27|p2'); fpc((int)$B2['oldMoved'] === 20000 && count($B2['heads']) === 2 && $B2['heads'][1]['paid'] === 20000, 'Anvi: old due moved to the card and paid');
    fpc(sch_rec_get('ofcSeq', '2026-27')['n'] === 402 && sch_rec_get('ofcPay', $oid)['status'] === 'paid', 'series continues, order paid');
    [$c, $body] = fph(8093, '/school/pay.php?b=' . $b . '&o=' . $oid); fpc(str_contains($body, 'Payment received') && str_contains($body, '1-401') && str_contains($body, '1-402'), 'receipts page');
    preg_match('/pay\.php\?rc=([A-Za-z0-9_.-]+)/', $body, $m); [$c, $body] = fph(8093, '/school/pay.php?rc=' . $m[1]); fpc(str_contains($body, 'FEE RECEIPT') && str_contains($body, $pid), 'receipt opens');
    [$c, $body] = fph(8093, '/school/pay.php?rc=' . substr($m[1], 0, -2) . 'zz'); fpc(str_contains($body, 'not valid'), 'forged receipt link refused');
    // same payment again (checkout + webhook) → no second receipt
    fph(8093, '/school/pay.php?b=' . $b, ['a' => 'done', 'b' => $b, 'razorpay_order_id' => $oid, 'razorpay_payment_id' => $pid, 'razorpay_signature' => $sig]);
    $ev = json_encode(['event' => 'payment.captured', 'payload' => ['payment' => ['entity' => ['id' => $pid, 'order_id' => $oid, 'amount' => 200000, 'status' => 'captured']]]]);
    [$c] = fph(8093, '/school/pay_webhook.php', ['__raw' => $ev], null, ['X-Razorpay-Signature: bad', 'Content-Type: application/json']); fpc($c === 401, 'webhook: bad signature refused');
    [$c, , , $j] = fph(8093, '/school/pay_webhook.php', ['__raw' => $ev], null, ['X-Razorpay-Signature: ' . hash_hmac('sha256', $ev, $WH), 'Content-Type: application/json']);
    fpc($c === 200 && $j['status'] === 'paid' && !sch_rec_get('ofcRcpt', '2026-27|1-403'), 'checkout + webhook twice = still 2 receipts');
    [$c, $body] = fph(8093, '/school/pay.php?b=' . $b); fpc(str_contains($body, 'No fee due till November'), 'pay page now: nothing due');
    // webhook-only payment where the office took cash in between → receipt for what was still due, extra flagged
    $A = sch_rec_get('ofcLed', '2026-27|p1'); $A['heads'][3]['m'] = 11; $put('ofcLed', '2026-27|p1', $A);   // March head moved to Nov → ₹600 due
    [$c, , , $j] = fph(8093, '/school/demandbill_api.php?a=bill&sid=p1&mode=single&upto=11', null, $CH); preg_match('#b=([A-Za-z0-9_.-]+)$#', $j['pay'], $m); $b2 = $m[1];
    [$c, $body] = fph(8093, '/school/pay.php?b=' . $b2, ['a' => 'order', 'b' => $b2, 'amt' => '60000']); preg_match('/"order_id":"(order_T[a-f0-9]+)"/', $body, $m); $oid2 = $m[1];
    $A = sch_rec_get('ofcLed', '2026-27|p1'); $A['heads'][3]['paid'] = 40000; $put('ofcLed', '2026-27|p1', $A);   // ₹400 taken in cash meanwhile
    $ev = json_encode(['event' => 'order.paid', 'payload' => ['payment' => ['entity' => ['id' => 'pay_' . substr($oid2, 6), 'order_id' => $oid2, 'amount' => 60000, 'status' => 'captured']]]]);
    [$c, , , $j] = fph(8093, '/school/pay_webhook.php', ['__raw' => $ev], null, ['X-Razorpay-Signature: ' . hash_hmac('sha256', $ev, $WH), 'Content-Type: application/json']);
    $O2 = sch_rec_get('ofcPay', $oid2); $r3 = sch_rec_get('ofcRcpt', '2026-27|1-403');
    fpc($c === 200 && $O2['status'] === 'excess' && $O2['excess'] === 40000 && $r3 && $r3['received'] === 20000, 'cash meanwhile → receipt ₹200, extra ₹400 flagged: ' . json_encode($O2));
    fpc((int)$pdo->query("SELECT COUNT(*) FROM sch_log WHERE k='ofcPay' AND x LIKE '%EXTRA%'")->fetchColumn() === 1, 'extra written in the log for the office');
} finally { proc_terminate($fake); proc_terminate($srv); }
[, , , $j] = fph(8099, '/school/api.php?a=load', null, $CH);
fpc(!array_filter($j['records'] ?? [], fn($r) => str_starts_with($r['k'], 'ofc')), 'pay records not in legacy load');
echo "Fee pay flow: OK\n";
