<?php
// ============================================================================
//  Parent pay page — opened from the QR / link on the fee demand bill (owner, 24 Nov 2026: "demand bill me QR —
//  pay karte he fee update, receipt bhi bankar chala jaye"). No login: the link itself is signed (feepay_lib.php).
//
//   GET  ?b=<bill token>              what is payable today for the children on the bill + "Pay online"
//   POST ?b=…  a=order                Razorpay order (school's own keys) → checkout opens
//   POST ?b=…  a=done                 checkout result: signature checked → receipts made (fp_settle) → ?o=
//   GET  ?b=…&o=order_…               "Payment received" + receipts
//   GET  ?rc=<receipt token>          one receipt to view / print / share
// ============================================================================
declare(strict_types=1);
require __DIR__ . '/inc.php';
require __DIR__ . '/registers_lib.php';
require __DIR__ . '/feecollect_lib.php';
require __DIR__ . '/feepay_lib.php';

header('X-Frame-Options: DENY'); header('Referrer-Policy: no-referrer');
try { $db = sch_db(); } catch (Throwable $e) { http_response_code(503); exit('Server is not reachable right now. Please try again in a few minutes.'); }
$h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$rs = fn(int $p) => '₹' . number_format($p / 100, ($p % 100) ? 2 : 0);
$set = sch_rec_get('settings', 'main') ?? []; $school = (string)($set['school'] ?? 'SN Mishra Singapore International School');

function pg_head(string $title, string $school): void {
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex">',
         '<title>', htmlspecialchars($title), '</title><style>
body{margin:0;background:#EEF2F5;font:15px/1.45 Arial,Helvetica,sans-serif;color:#1C1C1E}.wrap{max-width:560px;margin:0 auto;padding:14px}
.hd{background:#17324D;color:#fff;padding:16px 14px;text-align:center}.hd b{display:block;font-size:17px;letter-spacing:.3px}.hd small{color:#cfd8e3}
.card{background:#fff;border-radius:16px;padding:14px;margin:12px 0;box-shadow:0 1px 3px rgba(0,0,0,.06)}.kid{font-weight:700;color:#17324D;font-size:16px}.sub{color:#667;font-size:13px}
table{width:100%;border-collapse:collapse;margin-top:8px}td{padding:6px 0;border-bottom:1px solid #eef1f4;font-size:14px}td.r{text-align:right;font-weight:700;white-space:nowrap}
.tot{display:flex;justify-content:space-between;align-items:center;background:#FDECEC;border:1.5px solid #B30000;border-radius:14px;padding:12px 14px;color:#B30000;font-weight:700}
.tot big{font-size:26px}.btn{display:block;width:100%;box-sizing:border-box;border:0;border-radius:99px;padding:15px;font:inherit;font-size:17px;font-weight:700;text-align:center;text-decoration:none;cursor:pointer;margin-top:12px}
.go{background:#22C55E;color:#fff}.l{background:#E2E7EE;color:#17324D}.y{background:#F2C14E;color:#1d1d1d}.ok{background:#E8F7EE;border:1.5px solid #1D6B2F;color:#1D6B2F;border-radius:14px;padding:14px;font-weight:700;text-align:center}
.warn{background:#FFF8E6;border:1px solid #E0A100;border-radius:12px;padding:10px 12px;font-size:14px}.note{color:#667;font-size:12.5px;text-align:center;margin:10px 0}
.rc{display:flex;justify-content:space-between;gap:8px;padding:9px 0;border-bottom:1px solid #eef1f4}.rc a{color:#17324D;font-weight:700}
@media print{.noprint{display:none!important}body{background:#fff}.card{box-shadow:none;border:1px solid #999}}
</style></head><body><div class="hd"><b>', htmlspecialchars(strtoupper($school)), '</b><small>Jagannathpur, Bhadohi (U.P.) · School fee</small></div><div class="wrap">';
}
function pg_foot(): void { echo '<p class="note">Payment is received in the school\'s own account through Razorpay. The school does not see or keep your card / UPI details.</p></div></body></html>'; exit; }
function pg_err(string $msg, string $school): void { pg_head('Fee payment', $school); echo '<div class="card warn">', htmlspecialchars($msg), '</div>'; pg_foot(); }

// ---------------- one receipt ----------------
if (isset($_GET['rc'])) {
    $R = fp_unrtoken((string)$_GET['rc']); $r = $R ? sch_rec_get('ofcRcpt', $R['ses'] . '|' . $R['no']) : null;
    if (!$r) pg_err('This receipt link is not valid.', $school);
    pg_head('Fee Receipt ' . $r['no'], $school);
    $dmy = implode('-', array_reverse(explode('-', (string)$r['date'])));
    echo '<div class="card"><div style="text-align:center;font-weight:700;font-size:18px;color:#17324D;letter-spacing:1px">FEE RECEIPT</div>',
         $r['status'] === 'Cancelled' ? '<div class="warn" style="margin-top:8px;text-align:center"><b>CANCELLED</b> — ' . $h($r['cancelReason'] ?? '') . '</div>' : '',
         '<table><tr><td>Receipt No.</td><td class="r">', $h($r['no']), '</td></tr><tr><td>Date</td><td class="r">', $h($dmy), ' ', $h($r['time'] ?? ''), '</td></tr>',
         '<tr><td>Session</td><td class="r">', $h($r['session']), '</td></tr><tr><td>Student</td><td class="r">', $h($r['name']), '</td></tr>',
         '<tr><td>Class</td><td class="r">', $h($r['cls'] . ($r['section'] ? '-' . $r['section'] : '')), ' · Adm ', $h($r['adm']), '</td></tr><tr><td>Father</td><td class="r">', $h($r['father']), '</td></tr></table>',
         '<table style="margin-top:12px"><tr><td><b>Fee head</b></td><td><b>Month(s)</b></td><td class="r">Amount</td></tr>';
    foreach ($r['lines'] as $l) echo '<tr><td>', $h(str_replace(['Tution', 'Sefety'], ['Tuition', 'Safety'], $l[1])), '</td><td>', $h($l[2]), '</td><td class="r">', $rs((int)$l[3]), '</td></tr>';
    echo '</table><div class="tot" style="margin-top:12px"><span>Received</span><big>', $rs((int)$r['received']), '</big></div>',
         '<table><tr><td>Payment mode</td><td class="r">', $h($r['mode']), '</td></tr>', !empty($r['online']['pid']) ? '<tr><td>Transaction ID</td><td class="r">' . $h($r['online']['pid']) . '</td></tr>' : '',
         '<tr><td>Balance after this receipt (session)</td><td class="r">', $rs(max(0, (int)$r['balanceAfter'])), '</td></tr></table>',
         '<p class="note">Computer generated receipt — no signature needed.</p></div>',
         '<button class="btn y noprint" onclick="print()">🖨 Print / Save as PDF</button>',
         '<a class="btn l noprint" href="https://wa.me/?text=', rawurlencode('Fee Receipt ' . $r['no'] . ' · ' . $r['name'] . ' · ' . $rs((int)$r['received']) . "\n" . (($_SERVER['HTTPS'] ?? '') ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? '') . strtok((string)$_SERVER['REQUEST_URI'], '?') . '?rc=' . $_GET['rc']), '">💬 Share on WhatsApp</a>';
    pg_foot();
}

$b = (string)($_GET['b'] ?? $_POST['b'] ?? '');
$T = fp_untoken($b);
if (!$T) pg_err('This payment link is not valid. Please ask the school office for a new demand bill.', $school);
$self = 'pay.php?b=' . rawurlencode($b);
$act = (string)($_POST['a'] ?? '');
$err = '';

// ---------------- after checkout: check and make receipts ----------------
if ($act === 'done' && fp_ready()) {
    $oid = (string)($_POST['razorpay_order_id'] ?? ''); $pid = (string)($_POST['razorpay_payment_id'] ?? ''); $sig = (string)($_POST['razorpay_signature'] ?? '');
    $O = preg_match('/^order_[A-Za-z0-9]{6,40}$/', $oid) ? sch_rec_get('ofcPay', $oid) : null;
    $good = $O && ($O['b'] ?? '') === $b && preg_match('/^pay_[A-Za-z0-9]{6,40}$/', $pid) && hash_equals(hash_hmac('sha256', $oid . '|' . $pid, fp_cfg('SCHOOL_RZP_KEY_SECRET')), $sig);
    if ($good) {
        try {
            $P = fp_rzp('GET', 'payments/' . $pid);
            if ($P && ($P['status'] ?? '') === 'authorized') $P = fp_rzp('POST', 'payments/' . $pid . '/capture', ['amount' => (int)$O['amount'], 'currency' => 'INR']) ?: $P;
            if ($P && ($P['status'] ?? '') === 'captured' && ($P['order_id'] ?? '') === $oid) { fp_settle($oid, $pid, (int)$P['amount'], 'checkout'); header('Location: ' . $self . '&o=' . rawurlencode($oid), true, 303); exit; }
        } catch (Throwable $e) { error_log('school pay settle: ' . $e->getMessage()); }
    }
    $err = 'We could not confirm the payment yet. If money was deducted, the receipt will be made automatically within a few minutes — open this link again. Please do not pay twice.';
}

// ---------------- "Payment received" ----------------
if (isset($_GET['o'])) {
    $O = sch_rec_get('ofcPay', (string)$_GET['o']);
    if (!$O || ($O['b'] ?? '') !== $b) pg_err('Payment not found.', $school);
    pg_head('Payment received', $school);
    if ($O['status'] === 'created') echo '<div class="card warn">Payment is being confirmed. Please open this page again in a few minutes. Do not pay twice.</div>';
    else {
        echo '<div class="ok">✅ Payment received — ', $rs((int)$O['amount']), '<br><small style="font-weight:400">Transaction ID ', $h($O['pid']), '</small></div><div class="card"><b>Your fee receipt', count($O['receipts']) > 1 ? 's' : '', '</b>';
        foreach ($O['receipts'] as $x) echo '<div class="rc"><span>', $h($x['name']), '<br><small class="sub">Receipt ', $h($x['no']), ' · Session ', $h($x['ses']), '</small></span><span><b>', $rs((int)$x['amt']), '</b><br><a href="pay.php?rc=', $h(fp_rtoken($x['ses'], $x['no'])), '">Open receipt ›</a></span></div>';
        echo '</div>';
        if ((int)($O['excess'] ?? 0) > 0) echo '<div class="card warn">', $rs((int)$O['excess']), ' was more than the fee due today (it was probably paid at the office already). The school office will adjust it — please keep this page.</div>';
    }
    echo '<a class="btn l" href="', $h($self), '">See fee due now</a>';
    pg_foot();
}

$B = fp_bill($T);

// ---------------- start payment: Razorpay order ----------------
$order = null;
if ($act === 'order' && fp_ready() && $B['amount'] > 0) {
    if ((int)($_POST['amt'] ?? 0) !== $B['amount']) $err = 'The fee due changed just now. Please check the amount below and press Pay again.';
    else {
        $kids = []; foreach ($B['kids'] as $k) if ($k['amt'] > 0) $kids[] = ['sid' => $k['sid'], 'amt' => $k['amt'], 'name' => $k['name']];
        $o = fp_rzp('POST', 'orders', ['amount' => $B['amount'], 'currency' => 'INR', 'receipt' => 'sch-' . substr(md5($b . microtime()), 0, 12),
            'notes' => ['school' => mb_substr($school, 0, 60), 'session' => $T['ses'], 'children' => mb_substr(implode(', ', array_column($kids, 'name')), 0, 200)]]);
        if ($o && !empty($o['id']) && (int)$o['amount'] === $B['amount']) {
            sch_rec_put('ofcPay', (string)$o['id'], ['ses' => $T['ses'], 'upto' => $T['upto'], 'b' => $b, 'kids' => $kids, 'amount' => $B['amount'], 'status' => 'created', 'at' => sch_now_ms(), 'ip' => sch_ip()], 'online');
            $order = $o;
        } else $err = 'Online payment could not start. Please try again after some time, or pay at the school office.';
    }
}

pg_head('Pay school fee', $school);
if ($err) echo '<div class="card warn">', $h($err), '</div>';
$upto = OFC_MONTHS[$T['upto']];
foreach ($B['kids'] as $k) {
    echo '<div class="card"><div class="kid">', $h($k['name']), '</div><div class="sub">Class ', $h($k['cls']), ' · Adm ', $h($k['adm']), $k['father'] !== '' ? ' · ' . $h($k['father']) : '', '</div>';
    if ($k['stop'] !== '') echo '<div class="warn" style="margin-top:8px">', $h($k['stop']), '</div>';
    elseif (!$k['parts']) echo '<div class="ok" style="margin-top:8px">No fee due till ', $h($upto), ' 🙏</div>';
    else { echo '<table>'; foreach ($k['parts'] as $pt) foreach ($pt['lines'] as $l) echo '<tr><td>', $h($l[0]), '<br><small class="sub">', $h($l[1]), $pt['ses'] !== $T['ses'] ? ' · session ' . $h($pt['ses']) : '', '</small></td><td class="r">', $rs((int)$l[2]), '</td></tr>';
        echo '<tr><td><b>Total</b></td><td class="r">', $rs($k['amt']), '</td></tr></table>'; }
    echo '</div>';
}
echo '<div class="tot"><span>Payable till ', $h($upto), '</span><big>', $rs($B['amount']), '</big></div>';
if ($B['amount'] <= 0) echo '<p class="note">Nothing to pay right now. Thank you! 🙏</p>';
elseif (!fp_ready()) echo '<div class="card warn" style="margin-top:12px">Online payment is not switched on yet. Please pay at the school office and take the receipt.<br>ऑनलाइन भुगतान अभी चालू नहीं है — कृपया स्कूल ऑफिस में जमा करें।</div>';
elseif ($order) {
    echo '<button class="btn go" id="go">Pay ', $rs($B['amount']), ' now</button>',
         '<form id="f" method="post" action="', $h($self), '"><input type="hidden" name="a" value="done"><input type="hidden" name="b" value="', $h($b), '">',
         '<input type="hidden" name="razorpay_order_id"><input type="hidden" name="razorpay_payment_id"><input type="hidden" name="razorpay_signature"></form>',
         '<script src="https://checkout.razorpay.com/v1/checkout.js"></script><script>var o=', json_encode(['key' => fp_cfg('SCHOOL_RZP_KEY_ID'), 'order_id' => $order['id'], 'amount' => $order['amount'], 'currency' => 'INR',
            'name' => mb_substr($school, 0, 50), 'description' => 'School fee till ' . $upto, 'theme' => ['color' => '#17324D']], JSON_UNESCAPED_UNICODE), ';',
         'o.handler=function(r){var f=document.getElementById("f");for(var k in r)if(f.elements[k])f.elements[k].value=r[k];f.submit()};',
         'o.modal={ondismiss:function(){document.getElementById("go").textContent="Pay again"}};',
         'document.getElementById("go").onclick=function(){new Razorpay(o).open()};document.getElementById("go").click();</script>';
} else {
    echo '<form method="post" action="', $h($self), '"><input type="hidden" name="a" value="order"><input type="hidden" name="b" value="', $h($b), '"><input type="hidden" name="amt" value="', $B['amount'], '">',
         '<button class="btn go">Pay ', $rs($B['amount']), ' online (UPI / Card)</button></form>',
         '<p class="note">After payment the fee is updated in the school records and your receipt opens here at once.<br>पेमेंट होते ही फीस अपडेट होगी और रसीद यहीं मिल जाएगी।</p>';
}
pg_foot();
