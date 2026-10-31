<?php
// ============================================================
//  जाँच — Coaching Saathi (coaching/)
//
//  Login → coaching banti hai → course, batch, teacher, bachcha →
//  mahine ki fees apne aap (do baar nahi) → paisa mila + receipt
//  (same button do baar = ek receipt) → maaf → sudhaar → receipt radd →
//  haaziri → "3 din se nahi aaya" → teacher ko fees nahi dikhti →
//  DOOSRI COACHING kisi bhi tarah pehli ka data na dekh paye na badle →
//  parent app (fees free, lock sirf payment chalu hone par) →
//  plan ka paisa do baar na gine → receipt QR page → website + enquiry.
// ============================================================
require_once __DIR__ . '/../../config.php';
if (DB_NAME !== 'maakit_jaanch' && getenv('CS_JAANCH_LOCAL') !== '1') throw new RuntimeException('CI database only');
require_once __DIR__ . '/../../coaching/inc.php';

const BASE = 'http://127.0.0.1:8099';
function ck($ok, $m) { if (!$ok) throw new RuntimeException('Coaching flow: ' . $m); }
/** [code, body, location] — $tok = cs_t cookie */
function web(string $path, ?array $post = null, string $tok = ''): array {
    $c = curl_init(BASE . $path);
    $o = [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30, CURLOPT_PROXY => '', CURLOPT_HEADER => true,
          CURLOPT_COOKIE => 'cs_b=' . str_repeat('ab', 16) . ($tok !== '' ? '; cs_t=' . $tok : '')];
    if ($post !== null) { $o[CURLOPT_POST] = true; $o[CURLOPT_POSTFIELDS] = http_build_query($post); }
    curl_setopt_array($c, $o);
    $raw = (string)curl_exec($c); $code = curl_getinfo($c, CURLINFO_HTTP_CODE); $hs = curl_getinfo($c, CURLINFO_HEADER_SIZE); curl_close($c);
    $head = substr($raw, 0, $hs); $body = substr($raw, $hs);
    ck(!preg_match('/Fatal error|Warning:|Uncaught|Deprecated|Notice:/', $body), "PHP error at $path: " . substr(strip_tags($body), 0, 300));
    preg_match('/^Location:\s*(\S+)/mi', $head, $m);
    return [$code, $body, $m[1] ?? ''];
}
function sess(string $kind, string $mobile, ?int $cid = null, ?int $uid = null): string {
    $t = bin2hex(random_bytes(32));
    cs_q('INSERT INTO cs_session (token_hash,kind,mobile,coaching_id,user_id,expires) VALUES (?,?,?,?,?,NOW()+INTERVAL 1 DAY)', [hash('sha256', $t), $kind, $mobile, $cid, $uid]);
    return $t;
}
function csrf(string $t): string { return substr(hash('sha256', $t . '|cs-form'), 0, 32); }
function app(string $tok, array $post): array { $post['csrf'] = csrf($tok); return web('/coaching/app.php?p=home', $post, $tok); }

// ---- saaf shuruaat ----
foreach (['cs_attendance','cs_ledger','cs_receipt','cs_enroll','cs_batch','cs_course','cs_student','cs_enquiry','cs_sub','cs_session','cs_user','cs_coaching'] as $t) cs_q("DELETE FROM $t");
cs_otp_engine()->install(); cs_q("DELETE FROM sms_rate_limits");

[$code, $html] = web('/coaching/');
ck($code === 200 && str_contains($html, 'OTP bhejiye'), 'login page');

// ---- 1. owner: login + coaching banana ----
$OWN = '9876500001';
$vt = sess('verified', $OWN);
[$code, $html] = web('/coaching/index.php?choose=1', null, $vt);
ck(str_contains($html, 'Coaching banaiye'), 'naye number ko coaching banane ka form');
[$code, , $loc] = web('/coaching/index.php', ['a' => 'create', 'csrf' => csrf($vt), 'naam' => 'Gupta Classes', 'own' => 'Ramesh Gupta', 'city' => 'Lucknow', 'ctype' => 'tuition', 'reg' => 'UP/LKO/123'], $vt);
ck($code === 302 && str_contains($loc, 'app.php'), 'coaching banne ke baad app');
$C1 = cs_row("SELECT * FROM cs_coaching WHERE naam='Gupta Classes'");
ck($C1 && $C1['slug'] === 'gupta-classes-lucknow', 'slug: ' . ($C1['slug'] ?? '-'));
$O1 = cs_row("SELECT * FROM cs_user WHERE coaching_id=? AND role='owner'", [$C1['id']]);
$cid = (int)$C1['id'];
$ot = sess('staff', $OWN, $cid, (int)$O1['id']);

// ---- 2. course, teacher, batch, bachcha ----
app($ot, ['a' => 'course', 'naam' => 'Class 10 Maths', 'fee_type' => 'monthly', 'amount' => '500', 'months' => '']);
$course = (int)cs_val('SELECT id FROM cs_course WHERE coaching_id=?', [$cid]);
app($ot, ['a' => 'teacher', 'naam' => 'Sita Ma\'am', 'mobile' => '9876500002']);
$T1 = cs_row("SELECT * FROM cs_user WHERE coaching_id=? AND role='teacher'", [$cid]);
app($ot, ['a' => 'batch', 'naam' => '10th Shaam', 'course' => $course, 'timing' => '5-6 PM', 'seats' => '1', 'teacher' => $T1['id']]);
$batch = (int)cs_val('SELECT id FROM cs_batch WHERE coaching_id=?', [$cid]);
$join = date('Y-m-d', strtotime(date('Y-m-01') . ' -2 months'));
[$code, , $loc] = app($ot, ['a' => 'student', 'naam' => 'Aman', 'father' => 'Suresh', 'mobile' => '9876500003', 'cls' => '10th', 'batch' => $batch, 'fee' => '400', 'fee_note' => 'bhai bhi padhta hai', 'join' => $join]);
ck(str_contains($loc, 'p=student&id='), 'bachcha jud gaya');
$sid = (int)cs_val("SELECT id FROM cs_student WHERE naam='Aman'");
ck(cs_balance($cid, $sid) === 1200, '3 mahine × 400 = 1200, mila ' . cs_balance($cid, $sid));
web('/coaching/app.php?p=home', null, $ot); web('/coaching/app.php?p=dues', null, $ot);
ck((int)cs_val("SELECT COUNT(*) FROM cs_ledger WHERE student_id=? AND kind='due'", [$sid]) === 3, 'mahine ki fees do baar nahi judni chahiye');

// seat limit 1: doosra bachcha bina "over" ke nahi judta
[, , $loc] = app($ot, ['a' => 'student', 'naam' => 'Bina', 'mobile' => '9876500004', 'batch' => $batch, 'fee' => '', 'join' => date('Y-m-d')]);
ck(str_contains($loc, 'full=') && !cs_val("SELECT 1 FROM cs_student WHERE naam='Bina'"), 'seat full par roka');
app($ot, ['a' => 'student', 'naam' => 'Bina', 'mobile' => '9876500003', 'batch' => $batch, 'fee' => '', 'join' => date('Y-m-d'), 'over' => '1']);
$sid2 = (int)cs_val("SELECT id FROM cs_student WHERE naam='Bina'");
ck(cs_balance($cid, $sid2) === 500, 'fees khaali = course ki fees');

// ---- 3. paisa, receipt, maaf, sudhaar, radd ----
$nonce = str_repeat('a1', 8);
[, , $loc] = app($ot, ['a' => 'pay', 'sid' => $sid, 'amount' => '1000', 'mode' => 'upi', 'd' => date('Y-m-d'), 'nonce' => $nonce]);
app($ot, ['a' => 'pay', 'sid' => $sid, 'amount' => '1000', 'mode' => 'upi', 'd' => date('Y-m-d'), 'nonce' => $nonce]);
ck((int)cs_val('SELECT COUNT(*) FROM cs_receipt WHERE coaching_id=?', [$cid]) === 1, 'same button do baar = ek receipt');
ck(cs_balance($cid, $sid) === 200, 'baaki 200');
$R = cs_row('SELECT * FROM cs_receipt WHERE coaching_id=?', [$cid]);
ck((int)$R['no'] === 1, 'receipt no 1');
app($ot, ['a' => 'waive', 'sid' => $sid, 'amount' => '', 'why' => '']);
ck(cs_balance($cid, $sid) === 200, 'bina kaaran maaf nahi');
app($ot, ['a' => 'waive', 'sid' => $sid, 'amount' => '', 'why' => 'ghar ki majboori']);
ck(cs_balance($cid, $sid) === 0, 'maaf ke baad 0');
app($ot, ['a' => 'pay', 'sid' => $sid, 'amount' => '300', 'mode' => 'cash', 'd' => date('Y-m-d'), 'nonce' => str_repeat('b2', 8)]);
ck(cs_balance($cid, $sid) === -300, 'advance jama = -300');
app($ot, ['a' => 'fix', 'sid' => $sid, 'amount' => '50', 'why' => 'kitab']);
ck(cs_balance($cid, $sid) === -250, 'sudhaar +50');
app($ot, ['a' => 'cancel_receipt', 'rid' => $R['id'], 'why' => 'galat bachche ke naam']);
ck(cs_balance($cid, $sid) === 750 && (int)cs_val('SELECT cancelled FROM cs_receipt WHERE id=?', [$R['id']]) === 1, 'radd receipt ka paisa wapas baaki me');
ck((int)cs_val('SELECT COUNT(*) FROM cs_receipt WHERE coaching_id=?', [$cid]) === 2, 'receipt kabhi mitti nahi');
[$code, $html] = web('/coaching/app.php?p=student&id=' . $sid, null, $ot);
ck(str_contains($html, 'Hisaab') && str_contains($html, 'radd'), 'khata page');

// ---- 4. haaziri + khatre me ----
$tt = sess('staff', $T1['mobile'], $cid, (int)$T1['id']);
for ($i = 3; $i >= 1; $i--) app($ot, ['a' => 'att', 'batch' => $batch, 'd' => date('Y-m-d', strtotime("-$i days")), 's' => [$sid => 'A', $sid2 => 'P']]);
ck(in_array($sid, cs_absent3($cid), true) && !in_array($sid2, cs_absent3($cid), true), '3 din absent wala khatre me');
[, , $loc] = app($tt, ['a' => 'att', 'batch' => $batch, 'd' => date('Y-m-d'), 's' => [$sid => 'P', $sid2 => 'A']]);
ck(str_contains($loc, 'done=1'), 'teacher haaziri laga saka');
ck(!in_array($sid, cs_absent3($cid), true), 'aaj aaya to khatre se bahar');
[, , $loc] = app($tt, ['a' => 'att', 'batch' => $batch, 'd' => date('Y-m-d', strtotime('-20 days')), 's' => [$sid => 'A']]);
ck(str_contains($loc, 'm=err'), 'teacher 7 din se purani haaziri nahi badal sakta');

// teacher: fees / hisaab kabhi nahi
[, , $loc] = web('/coaching/app.php?p=dues', null, $tt);
ck(str_contains($loc, 'band'), 'teacher ko baaki fees ka page nahi');
$before = (int)cs_val('SELECT COUNT(*) FROM cs_ledger WHERE coaching_id=?', [$cid]);
app($tt, ['a' => 'pay', 'sid' => $sid, 'amount' => '100', 'mode' => 'cash', 'nonce' => str_repeat('c3', 8)]);
app($tt, ['a' => 'waive', 'sid' => $sid, 'why' => 'x']);
ck((int)cs_val('SELECT COUNT(*) FROM cs_ledger WHERE coaching_id=?', [$cid]) === $before, 'teacher paisa / maaf nahi likh sakta');
[, $html] = web('/coaching/app.php?p=student&id=' . $sid, null, $tt);
ck(!str_contains($html, 'Fees ka khata') && !str_contains($html, '₹750'), 'teacher ko bachche ki fees nahi dikhti');

// ---- 5. DOOSRI COACHING: pehli ka kuch nahi ----
$vt2 = sess('verified', '9876500009');
web('/coaching/index.php', ['a' => 'create', 'csrf' => csrf($vt2), 'naam' => 'Sharma Academy', 'own' => 'Sharma', 'city' => 'Lucknow', 'ctype' => 'sarkari'], $vt2);
$C2 = cs_row("SELECT * FROM cs_coaching WHERE naam='Sharma Academy'");
$O2 = cs_row("SELECT * FROM cs_user WHERE coaching_id=?", [$C2['id']]);
$o2 = sess('staff', '9876500009', (int)$C2['id'], (int)$O2['id']);
[, $html] = web('/coaching/app.php?p=student&id=' . $sid, null, $o2);
ck(str_contains($html, 'nahi mila') && !str_contains($html, 'Aman'), 'doosri coaching bachcha nahi dekh sakti');
[, $html] = web('/coaching/app.php?p=students', null, $o2);
ck(!str_contains($html, 'Aman') && !str_contains($html, 'Bina'), 'doosri coaching ki list me pehli ke bachche nahi');
$snap = (int)cs_val('SELECT COUNT(*) FROM cs_ledger') . '|' . (int)cs_val('SELECT COUNT(*) FROM cs_attendance') . '|' . cs_val('SELECT naam FROM cs_student WHERE id=?', [$sid]);
foreach ([['a' => 'pay', 'sid' => $sid, 'amount' => '5', 'mode' => 'cash', 'nonce' => str_repeat('d4', 8)], ['a' => 'waive', 'sid' => $sid, 'why' => 'x'],
          ['a' => 'fix', 'sid' => $sid, 'amount' => '5', 'why' => 'x'], ['a' => 'edit', 'sid' => $sid, 'naam' => 'Hacked', 'mobile' => '9876500009'],
          ['a' => 'status', 'sid' => $sid], ['a' => 'cancel_receipt', 'rid' => cs_val('SELECT MAX(id) FROM cs_receipt WHERE coaching_id=?', [$cid]), 'why' => 'x'],
          ['a' => 'att', 'batch' => $batch, 'd' => date('Y-m-d'), 's' => [$sid => 'A']], ['a' => 'enroll', 'sid' => $sid, 'batch' => $batch],
          ['a' => 'batch', 'naam' => 'x', 'course' => $course], ['a' => 'batch_off', 'id' => $batch], ['a' => 'teacher_off', 'id' => $T1['id']],
          ['a' => 'enq_upd', 'id' => 1, 'status' => 'closed']] as $evil) app($o2, $evil);
$snap2 = (int)cs_val('SELECT COUNT(*) FROM cs_ledger') . '|' . (int)cs_val('SELECT COUNT(*) FROM cs_attendance') . '|' . cs_val('SELECT naam FROM cs_student WHERE id=?', [$sid]);
ck($snap === $snap2, "doosri coaching ne pehli ka data badal diya ($snap → $snap2)");
ck((int)cs_val('SELECT active FROM cs_batch WHERE id=?', [$batch]) === 1 && (int)cs_val('SELECT active FROM cs_user WHERE id=?', [$T1['id']]) === 1, 'batch / teacher safe');
ck(!cs_val('SELECT 1 FROM cs_batch WHERE coaching_id=? AND course_id=?', [$C2['id'], $course]), 'doosri coaching pehli ke course par batch nahi bana sakti');
// bina CSRF ke kuch nahi
web('/coaching/app.php?p=home', ['a' => 'fix', 'sid' => $sid, 'amount' => '9', 'why' => 'x'], $ot);
ck(cs_balance($cid, $sid) === 750, 'bina csrf ke entry nahi');

// ---- 6. parent app ----
$pt = sess('family', '9876500003');
[$code, $html] = web('/coaching/me.php', null, $pt);
ck($code === 200 && str_contains($html, 'Aman') && str_contains($html, 'Bina'), 'ek number par do bachche, dono dikhe');
[, $html] = web('/coaching/me.php?s=' . $sid2, null, $pt);
ck(str_contains($html, 'Aaj Bina') && str_contains($html, 'nahi aaya'), 'aaj absent ki khabar parent ko');
ck(str_contains($html, 'Free trial'), 'naya bachcha trial par');
[, , $loc] = web('/coaching/me.php', null, sess('family', '9876500099'));
ck(str_contains($loc, 'index.php'), 'anjaan number ko kuch nahi');
[, , $loc] = web('/coaching/pay.php?sid=' . $sid, null, sess('family', '9876500099'));
ck(str_contains($loc, 'band'), 'doosre ke bachche ka pay page nahi');

// lock: payment chalu na ho to kabhi lock nahi
cs_q('UPDATE cs_student SET created_at=NOW()-INTERVAL 30 DAY WHERE id=?', [$sid]);
$S = cs_row('SELECT * FROM cs_student WHERE id=?', [$sid]);
ck(cs_pay_ready() || cs_access($S)['open'] === true, 'payment band ho to app khula');
// plan ka paisa: sahi rakam par hi, do baar nahi
cs_q("INSERT INTO cs_sub (coaching_id,student_id,plan,amount,order_id) VALUES (?,?,'6m',54,'order_T1')", [$cid, $sid]);
ck(!cs_sub_mark_paid('order_T1', 'pay_X', 100), 'galat rakam par plan nahi');
ck(cs_sub_mark_paid('order_T1', 'pay_1', 5400) && cs_sub_mark_paid('order_T1', 'pay_1', 5400), 'plan pakka (do baar bhi theek)');
$till = cs_val("SELECT valid_till FROM cs_sub WHERE order_id='order_T1'");
ck($till === date('Y-m-d', strtotime(date('Y-m-d') . ' +6 months -1 day')), 'plan payment ki tareekh se 6 mahine: ' . $till);
ck(cs_access($S)['why'] === 'paid', 'paid ke baad khula');
cs_q("INSERT INTO cs_sub (coaching_id,student_id,plan,amount,order_id) VALUES (?,?,'12m',99,'order_T2')", [$cid, $sid]);
cs_sub_mark_paid('order_T2', 'pay_2', 9900);
ck(cs_val("SELECT valid_from FROM cs_sub WHERE order_id='order_T2'") === date('Y-m-d', strtotime($till . ' +1 day')), 'naya plan purane ke baad se');
[$code] = web('/coaching/webhook.php', ['x' => 1]);
ck($code === 401, 'bina signature webhook band');

// ---- 7. receipt QR page ----
$R2 = cs_row('SELECT * FROM cs_receipt WHERE coaching_id=? AND cancelled=0', [$cid]);
[$code, $html] = web('/coaching/receipt.php?t=' . $R2['token']);
ck($code === 200 && str_contains($html, 'Asli receipt') && str_contains($html, 'UP/LKO/123') && str_contains($html, 'Ab tak maaf'), 'receipt page');
[$code, $html] = web('/coaching/receipt.php?t=' . $R['token']);
ck(str_contains($html, 'RADD'), 'radd receipt QR par radd dikhe');
[$code] = web('/coaching/receipt.php?t=' . str_repeat('0', 24));
ck($code === 404, 'galat receipt 404');

// ---- 8. website + enquiry ----
[$code, $html] = web('/coaching/site.php?c=gupta-classes-lucknow');
ck($code === 200 && str_contains($html, 'Class 10 Maths') && str_contains($html, 'Seat full') && !str_contains($html, 'Aman') && !str_contains($html, '9876500003'), 'website: course dikhe, bachchon ka data nahi');
web('/coaching/site.php?c=gupta-classes-lucknow', ['naam' => 'Neha', 'mobile' => '9876500077', 'interest' => '12th']);
web('/coaching/site.php?c=gupta-classes-lucknow', ['naam' => 'Bot', 'mobile' => '9876500078', 'website' => 'spam']);
ck((int)cs_val("SELECT COUNT(*) FROM cs_enquiry WHERE coaching_id=? AND source='web'", [$cid]) === 1, 'website enquiry (bot nahi)');
[, $html] = web('/coaching/app.php?p=enq', null, $ot);
ck(str_contains($html, 'Neha') && str_contains($html, 'Aaj call'), 'enquiry owner ko aaj ki call me');
[, $html] = web('/coaching/app.php?p=enq', null, $o2);
ck(!str_contains($html, 'Neha'), 'enquiry doosri coaching ko nahi');

// ---- 9. saare pannon me koi PHP galti nahi ----
foreach (['home','students','add','dues','risk','enq','receipts','report','courses','team','apps','setup','more','att','att&batch=' . $batch . '&done=1'] as $p) {
    [$code] = web('/coaching/app.php?p=' . $p, null, $ot);
    ck($code === 200, "owner page $p: $code");
}
foreach (['home','students','att','risk','more'] as $p) { [$code] = web('/coaching/app.php?p=' . $p, null, $tt); ck($code === 200, "teacher page $p"); }
[, $html] = web('/coaching/app.php?p=apps', null, $ot);
ck(str_contains($html, 'Paid'), 'owner ko paid list');

// login ke baad do raaste (owner + parent same number) par chunna
cs_q("UPDATE cs_student SET mobile=? WHERE id=?", [$OWN, $sid2]);
[, $html] = web('/coaching/index.php?choose=1', null, sess('verified', $OWN));
ck(str_contains($html, 'Gupta Classes') && str_contains($html, 'Student / Parent app'), 'ek number par owner + parent dono raaste');

echo "Coaching Saathi: sab theek\n";
