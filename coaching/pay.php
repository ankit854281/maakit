<?php
// Coaching Saathi — student app ka plan (₹54 / 6 mahine, ₹99 / 12 mahine) Razorpay se.
// Ye paisa Maakit ki apni service (app) ka hai — coaching ki fees nahi. Coaching ki fees
// kabhi is raaste nahi jaati (wo cash / coaching ke apne UPI me hi jama hoti hai).
// Parent khud bhare, ya owner kisi bachche ke liye gift me bhare.
require __DIR__ . '/inc.php';

$s = cs_session();
if (!$s) cs_go('index.php');
$sid = (int)($_GET['sid'] ?? $_POST['sid'] ?? 0);
$st = cs_row("SELECT st.*, c.naam cnaam, c.color FROM cs_student st JOIN cs_coaching c ON c.id=st.coaching_id WHERE st.id=? AND st.status='active'", [$sid]);
$by = null;
if ($st && $s['kind'] === 'family' && $st['mobile'] === $s['mobile']) $by = 'parent';
if ($st && $s['kind'] === 'staff' && (int)$s['coaching_id'] === (int)$st['coaching_id']
    && cs_val("SELECT 1 FROM cs_user WHERE id=? AND coaching_id=? AND role='owner' AND active=1", [$s['user_id'], $st['coaching_id']])) $by = 'owner';
if (!$by) cs_go('index.php?m=band');
$back = $by === 'owner' ? 'app.php?p=student&id=' . $sid : 'me.php?s=' . $sid;
$plans = cs_plans();
$err = '';

function rzp(string $method, string $path, array $body = []): ?array {
    $ch = curl_init('https://api.razorpay.com/v1/' . $path);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_USERPWD => cs_cfg('CS_RAZORPAY_KEY_ID') . ':' . cs_cfg('CS_RAZORPAY_KEY_SECRET'),
        CURLOPT_CUSTOMREQUEST => $method, CURLOPT_HTTPHEADER => ['Content-Type: application/json'], CURLOPT_TIMEOUT => 15,
        CURLOPT_SSL_VERIFYPEER => true] + ($body ? [CURLOPT_POSTFIELDS => json_encode($body)] : []));
    $out = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    $j = is_string($out) ? json_decode($out, true) : null;
    return $code >= 200 && $code < 300 && is_array($j) ? $j : null;
}

if (!cs_pay_ready()) { cs_go($back); }

// ---- checkout ke baad: signature jaanch ke pakka karo ----
if (($_POST['a'] ?? '') === 'done') {
    $oid = (string)($_POST['razorpay_order_id'] ?? ''); $pid = (string)($_POST['razorpay_payment_id'] ?? ''); $sig = (string)($_POST['razorpay_signature'] ?? '');
    $sub = cs_row('SELECT * FROM cs_sub WHERE order_id=? AND student_id=?', [$oid, $sid]);
    $good = $sub && hash_equals(hash_hmac('sha256', $oid . '|' . $pid, (string)cs_cfg('CS_RAZORPAY_KEY_SECRET')), $sig);
    if ($good && cs_sub_mark_paid($oid, $pid, (int)$sub['amount'] * 100)) cs_go($back . '&m=ok');
    $err = 'Payment pakka nahi ho paaya. Agar paisa kat gaya hai to 10 minute me apne aap app khul jayega.';
}

// ---- plan chuna: order banao ----
$order = null; $plan = null;
if (($_POST['a'] ?? '') === 'plan' && cs_csrf_ok() && isset($plans[$_POST['plan'] ?? ''])) {
    $plan = $_POST['plan']; $amt = $plans[$plan]['amount'];
    $o = rzp('POST', 'orders', ['amount' => $amt * 100, 'currency' => 'INR', 'receipt' => 'cs' . $sid . '-' . time(),
        'notes' => ['student_id' => (string)$sid, 'coaching_id' => (string)$st['coaching_id'], 'plan' => $plan, 'by' => $by]]);
    if ($o && !empty($o['id'])) {
        cs_q('INSERT INTO cs_sub (coaching_id,student_id,plan,amount,order_id,paid_by) VALUES (?,?,?,?,?,?)', [$st['coaching_id'], $sid, $plan, $amt, $o['id'], $by]);
        $order = $o;
    } else $err = 'Payment shuru nahi ho paaya. Thodi der baad koshish kariye.';
}

cs_head('App chalu kariye', ['color' => $st['color']]);
echo '<div class="top"><a href="', h($back), '">‹ Wapas</a><b>App chalu kariye</b></div><div class="wrap">';
if ($err) echo '<div class="flash bad">', h($err), '</div>';
echo '<div class="card"><b>', h($st['naam']), '</b><div class="sub">', h($st['cnaam']), $by === 'owner' ? ' · Aap ye gift me bhar rahe hain' : '', '</div></div>';
if ($order) {
    echo '<div class="card"><p>', rs($plans[$plan]['amount']), ' — ', h($plans[$plan]['label']), '</p><button class="btn full" id="go">Pay kariye</button></div>',
         '<form id="f" method="post"><input type="hidden" name="a" value="done"><input type="hidden" name="sid" value="', $sid, '">',
         '<input type="hidden" name="razorpay_order_id"><input type="hidden" name="razorpay_payment_id"><input type="hidden" name="razorpay_signature"></form>',
         '<script src="https://checkout.razorpay.com/v1/checkout.js"></script><script>',
         'var o=', json_encode(['key' => cs_cfg('CS_RAZORPAY_KEY_ID'), 'order_id' => $order['id'], 'amount' => $order['amount'], 'currency' => 'INR',
            'name' => 'Coaching Saathi (Maakit)', 'description' => $st['naam'] . ' — app ' . $plans[$plan]['label']]), ';',
         'o.handler=function(r){var f=document.getElementById("f");for(var k in r)if(f.elements[k])f.elements[k].value=r[k];f.submit()};',
         'document.getElementById("go").onclick=function(){new Razorpay(o).open()};document.getElementById("go").click();</script>';
} else {
    echo '<form method="post">', cs_csrf_field(), '<input type="hidden" name="a" value="plan"><input type="hidden" name="sid" value="', $sid, '">';
    foreach ($plans as $k => $pl)
        echo '<button class="plan" name="plan" value="', $k, '"><strong>', rs($pl['amount']), '</strong> — ', h($pl['label']),
             $k === '12m' ? ' <span class="tag green">sasta</span>' : '', '<br><span class="note">Haaziri, batch, aage notes aur result</span></button>';
    echo '</form><p class="note">Plan payment ki tareekh se shuru hota hai. <b>Refund nahi hota.</b> Fees ka khata aur receipt hamesha free hain. Payment Razorpay se (UPI / card) — Maakit card ya UPI ki details apne paas nahi rakhta.</p>';
}
echo '</div>';
cs_foot();
