<?php
// ============================================================
//  जाँच — School office: fee desk + dues calling (school/office_api.php)
//
//  Accountant pehli baar 1234 se → apna PIN → fees jama → raseed number
//  ek ke baad ek (saath-saath dabane par bhi) → ek hi button do baar
//  dabaya to ek hi raseed → bakaya sahi ghata → teesre bachche ki chhoot →
//  free scheme / pichhla bakaya / chhoot → Reception fees nahi le sakti,
//  sirf call likh sakti hai → raseed sirf Principal radd kare → parent
//  ko sirf apne bachche → office login ko poore school ka data nahi →
//  "acct" records aam sync me nahi.
// ============================================================
require_once __DIR__ . '/../../config.php';
if (DB_NAME !== 'maakit_jaanch') throw new RuntimeException('CI database only');
require_once __DIR__ . '/../../inc/db.php';
require_once __DIR__ . '/../../school/inc.php';
require_once __DIR__ . '/../../school/office_lib.php';
date_default_timezone_set('Asia/Kolkata');

function of_check($ok, $m) { if (!$ok) throw new RuntimeException('Office flow: ' . $m); }
function of_http(string $path, $body = null, ?string $tok = null): array {
    $c = curl_init('http://127.0.0.1:8099' . $path);
    $h = ['X-School-Token: ' . ($tok ?? '')]; $o = [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30, CURLOPT_PROXY => ''];
    if ($body !== null) { $h[] = 'Content-Type: application/json'; $o[CURLOPT_POST] = true; $o[CURLOPT_POSTFIELDS] = json_encode($body); }
    $o[CURLOPT_HTTPHEADER] = $h; curl_setopt_array($c, $o);
    $raw = (string)curl_exec($c); $code = curl_getinfo($c, CURLINFO_HTTP_CODE); curl_close($c);
    of_check(!preg_match('/Fatal error|Warning:|Uncaught|Deprecated/', $raw), "PHP error at $path: " . substr($raw, 0, 300));
    return [$code, json_decode($raw, true), $raw];
}
$api = fn($a, $b = null, $t = null, $q = '') => of_http('/school/api.php?a=' . $a . $q, $b, $t);
$o   = fn($a, $b = null, $t = null) => of_http('/school/office_api.php?a=' . $a, $b, $t);
$nonce = fn() => bin2hex(random_bytes(10));

// ---- pure maths ----
of_check(of_words(123456) === 'One Lakh Twenty Three Thousand Four Hundred Fifty Six', 'words: ' . of_words(123456));
of_check(of_words(1500) === 'One Thousand Five Hundred' && of_words(90) === 'Ninety' && of_words(100000) === 'One Lakh', 'words small');
of_check(of_months('2026-04-01', '2026-04-15') === 1 && of_months('2026-04-01', '2026-10-17') === 7 && of_months('2026-04-01', '2027-03-31') === 12
      && of_months('2026-04-01', '2027-06-01') === 12 && of_months('2026-04-01', '2026-03-31') === 0, 'months');

// ---- fresh school ----
foreach (['sch_sessions', 'sch_users', 'sch_records', 'sch_log', 'sch_login_fail'] as $t) $pdo->exec("DELETE FROM $t");
$put = fn($k, $r, $d) => sch_rec_put($k, $r, $d, 'principal');
$today = date('Y-m-d');
$start = (date('n') >= 4 ? date('Y') : (string)((int)date('Y') - 1)) . '-04-01';
$months = of_months($start, $today);
$put('settings', 'main', ['school' => 'Office Test School', 'session' => '2026–27', 'sessionStart' => $start]);
$F = ['session' => 'x', 'monthly' => ['PG' => 600, '2nd' => 790, '5th' => 850, '6th' => 990, '10th' => null],
      'yearly' => [['name' => 'Exam fee', 'amt' => 1800, 'skip' => ['PG']], ['name' => 'Sports', 'amt' => 1000]],
      'admission' => [], 'transport' => [['upto' => 6, 'amt' => 600], ['upto' => 10, 'amt' => 800], ['upto' => null, 'amt' => null]], 'thirdChildPct' => 50, 'notes' => []];
$put('fees', 'main', $F);
$kids = [['id' => 'a1', 'adm' => '11', 'name' => 'Aman', 'cls' => '5th', 'father' => 'Ravi', 'phone' => '9839100011', 'km' => 5],
         ['id' => 'a2', 'adm' => '12', 'name' => 'Asha', 'cls' => '2nd', 'father' => 'Ravi', 'phone' => '+91 98391 00011', 'km' => null],
         ['id' => 'a3', 'adm' => '13', 'name' => 'Anu',  'cls' => 'PG',  'father' => 'Ravi', 'phone' => '9839100011'],
         ['id' => 'b1', 'adm' => '21', 'name' => 'Bina', 'cls' => '10th', 'father' => 'Mohan', 'phone' => '9839100022'],
         ['id' => 'c1', 'adm' => '31', 'name' => 'Chetan', 'cls' => '6th', 'father' => '', 'phone' => '']];
foreach ($kids as $k) $put('student', $k['id'], $k);
$pin = fn($uid, $role, $tid, $p) => $pdo->prepare('INSERT INTO sch_users (uid, role, tid, pin_hash, must_change) VALUES (?,?,?,?,0)')->execute([$uid, $role, $tid, password_hash($p, PASSWORD_DEFAULT)]);
$pin('principal', 'principal', 'P', '2468'); [, $j] = $api('login', ['role' => 'principal', 'pin' => '2468']); $P = $j['token'];

// ---- Accountant: first PIN 1234, must choose own ----
[$c, $j] = $api('login', ['role' => 'accountant', 'pin' => '9999']);
of_check($c === 403, 'accountant wrong first PIN refused');
[$c, $j] = $api('login', ['role' => 'accountant', 'pin' => '1234']);
of_check($c === 200 && $j['mustChange'] === true && $j['me']['label'] === 'Akash Tiwari (Accountant)', 'accountant 1234 login: ' . json_encode($j));
[$c] = $o('data', null, $j['token']); of_check($c === 428, 'must choose PIN before fee desk');
[$c] = $api('setpin', ['old' => '1234', 'pin' => '4321'], $j['token']); of_check($c === 200, 'accountant sets PIN');
[, $j] = $api('login', ['role' => 'accountant', 'pin' => '4321']); $A = $j['token'];
$pin('reception', 'reception', null, '5555'); [, $j] = $api('login', ['role' => 'reception', 'pin' => '5555']); $R = $j['token'];
of_check($j['me']['label'] === 'Shiva Rai (Reception)', 'reception label');
$pin('chairman', 'chairman', null, '6666'); [, $j] = $api('login', ['role' => 'chairman', 'pin' => '6666']); $CH = $j['token'];
$pin('p:9839100011', 'parent', '9839100011', '3333'); [, $j] = $api('login', ['role' => 'parent', 'phone' => '9839100011', 'pin' => '3333']); $PA = $j['token'];
$pin('s:b1', 'student', 'b1', '7777'); [, $j] = $api('login', ['role' => 'student', 'adm' => '21', 'pin' => '7777']); $SB = $j['token'];

// ---- office logins never get the whole school's data ----
foreach ([$A, $R] as $t) {
    foreach (['load', 'since', 'pdata'] as $a) { [$c] = $api($a, null, $t, '&date=' . $today); of_check($c === 403, "office login blocked from $a"); }
    [$c] = $api('day', null, $t, '&date=' . $today); of_check($c === 403, 'office blocked from day');
    [$c, $j] = $api('sync', ['put' => [['k' => 'settings', 'r' => 'main', 'd' => ['school' => 'Hacked']]], 'del' => [], 'log' => [['k' => 'Fee', 'x' => 'fake']]], $t);
    of_check($c === 200 && count($j['rejected']) === 1 && sch_rec_get('settings', 'main')['school'] === 'Office Test School', 'office cannot sync school data');
}
of_check((int)$pdo->query("SELECT COUNT(*) FROM sch_log WHERE x='fake'")->fetchColumn() === 0, 'office cannot write fake logs through sync');
foreach ([$PA, $SB] as $t) { [$c] = $o('data', null, $t); of_check($c === 403, 'family blocked from fee desk'); }

// ---- dues ----
[$c, $D] = $o('data', null, $A); of_check($c === 200 && $D['can']['pay'] && !$D['can']['cancel'], 'accountant data');
$by = fn($D) => array_column($D['students'], null, 'id');
$S = $by($D);
$yA = $months ? 2800 : 0;
of_check($S['a1']['dues']['monthly'] === 850 + 600 && $S['a1']['dues']['due'] === $yA + 1450 * $months, 'a1 due (fee + bus 0–6 km): ' . json_encode($S['a1']['dues']));
of_check($S['a3']['dues']['third'] === true && $S['a3']['dues']['monthly'] === 300 && $S['a3']['dues']['due'] === ($months ? 1000 : 0) + 300 * $months, 'PG 3rd child half fee, no exam fee: ' . json_encode($S['a3']['dues']));
of_check($S['a2']['dues']['third'] === false && $S['a2']['dues']['monthly'] === 790, 'a2 normal');
of_check($S['b1']['dues']['missing'] === true, 'Class 10 fee not set is flagged');
of_check($S['a1']['fam'] === $S['a2']['fam'] && $S['c1']['fam'] === 'sid:c1', 'family key by mobile');

// ---- take fee, receipt numbers, double tap ----
[$c] = $o('pay', ['sid' => 'a1', 'amt' => 500, 'mode' => 'Cheque', 'ref' => '', 'date' => $today, 'nonce' => $nonce()], $A); of_check($c === 400, 'cheque needs number');
[$c] = $o('pay', ['sid' => 'a1', 'amt' => 500, 'mode' => 'Cash', 'date' => date('Y-m-d', time() + 86400), 'nonce' => $nonce()], $A); of_check($c === 400, 'no future receipt');
[$c] = $o('pay', ['sid' => 'a1', 'amt' => 10.5, 'mode' => 'Cash', 'date' => $today, 'nonce' => $nonce()], $A); of_check($c === 400, 'whole rupees only');
[$c] = $o('pay', ['sid' => 'zz', 'amt' => 100, 'mode' => 'Cash', 'date' => $today, 'nonce' => $nonce()], $A); of_check($c === 400, 'unknown student');
$n1 = $nonce();
[$c, $j] = $o('pay', ['sid' => 'a1', 'amt' => 1500, 'mode' => 'Cash', 'date' => $today, 'note' => 'April', 'nonce' => $n1], $A);
of_check($c === 200 && $j['receipt']['no'] === 1 && $j['receipt']['words'] === 'One Thousand Five Hundred Rupees Only' && $j['due'] === $S['a1']['dues']['due'] - 1500, 'first receipt: ' . json_encode($j));
[$c, $j] = $o('pay', ['sid' => 'a1', 'amt' => 1500, 'mode' => 'Cash', 'date' => $today, 'nonce' => $n1], $A);
of_check($c === 200 && $j['again'] === true && $j['receipt']['no'] === 1, 'same button twice = one receipt');
// five desks at the same moment → five different numbers
$mh = curl_multi_init(); $hs = [];
for ($i = 0; $i < 5; $i++) {
    $h = curl_init('http://127.0.0.1:8099/school/office_api.php?a=pay');
    curl_setopt_array($h, [CURLOPT_RETURNTRANSFER => true, CURLOPT_PROXY => '', CURLOPT_POST => true, CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => ['X-School-Token: ' . $P, 'Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode(['sid' => 'a2', 'amt' => 100 + $i, 'mode' => 'UPI', 'ref' => 'U' . $i, 'date' => $today, 'nonce' => $nonce()])]);
    curl_multi_add_handle($mh, $h); $hs[] = $h;
}
do { curl_multi_exec($mh, $run); curl_multi_select($mh); } while ($run);
$nos = array_map(fn($h) => json_decode(curl_multi_getcontent($h), true)['receipt']['no'] ?? null, $hs);
sort($nos); of_check($nos === [2, 3, 4, 5, 6], 'parallel receipts numbered 2..6: ' . json_encode($nos));

// ---- reception: calls only ----
[$c] = $o('pay', ['sid' => 'a1', 'amt' => 100, 'mode' => 'Cash', 'date' => $today, 'nonce' => $nonce()], $R); of_check($c === 403, 'reception cannot take fees');
[$c] = $o('setstu', ['sid' => 'a1', 'opening' => 0], $R); of_check($c === 403, 'reception cannot change fees');
[$c] = $o('call', ['sid' => 'a2', 'outcome' => 'Promised to pay', 'promise' => ''], $R); of_check($c === 400, 'promise needs a date');
[$c] = $o('call', ['sid' => 'a2', 'outcome' => 'Made up'], $R); of_check($c === 400, 'outcome from the list');
[$c, $j] = $o('call', ['sid' => 'a2', 'outcome' => 'Promised to pay', 'promise' => date('Y-m-d', time() + 3 * 86400), 'note' => 'Will pay Monday'], $R);
of_check($c === 200 && $j['call']['fam'] === '9839100011' && count($j['call']['sids']) === 3 && $j['call']['by'] === 'Shiva Rai (Reception)', 'call saved for whole family');
[$c, $D] = $o('data', null, $R); of_check($c === 200 && !$D['can']['pay'] && $D['can']['call'] && count($D['calls']) === 1, 'reception sees the call');

// ---- accountant settings: old due, free scheme, concession, own fee ----
[$c, $j] = $o('setstu', ['sid' => 'c1', 'opening' => 2000, 'custom' => '', 'free' => true, 'scheme' => 'FD company', 'concession' => 0], $A);
of_check($c === 200 && $j['dues']['due'] === 2000 && $j['dues']['free'] === true, 'free scheme: only old due: ' . json_encode($j));
[$c, $j] = $o('setstu', ['sid' => 'b1', 'opening' => 0, 'custom' => 1700, 'free' => false, 'concession' => 500], $A);
of_check($c === 200 && $j['dues']['missing'] === false && $j['dues']['due'] === ($months ? 2800 : 0) + 1700 * $months - 500, 'own monthly fee + concession: ' . json_encode($j));
[$c] = $o('setstu', ['sid' => 'b1', 'custom' => 'abc'], $A); of_check($c === 400, 'bad amount refused');

// ---- cancel: Principal only, with reason; due comes back ----
[$c] = $o('cancel', ['no' => 1, 'reason' => 'x'], $A); of_check($c === 403, 'accountant cannot cancel');
[$c] = $o('cancel', ['no' => 1, 'reason' => ''], $P); of_check($c === 400, 'cancel needs reason');
[$c] = $o('cancel', ['no' => 1, 'reason' => 'Wrong child'], $P); of_check($c === 200, 'principal cancels');
[$c] = $o('cancel', ['no' => 1, 'reason' => 'again'], $P); of_check($c === 400, 'cannot cancel twice');
[, $D] = $o('data', null, $P); $S = $by($D);
of_check($S['a1']['dues']['paid'] === 0 && $D['can']['cancel'], 'cancelled receipt no longer counts');
of_check((int)$pdo->query("SELECT COUNT(*) FROM sch_log WHERE k='Fee' AND x LIKE '%CANCELLED: Wrong child%'")->fetchColumn() === 1, 'cancel is in the activity log');

// ---- chairman looks only ----
[$c, $D] = $o('data', null, $CH); of_check($c === 200 && !$D['can']['pay'] && !$D['can']['call'], 'chairman read-only');
[$c] = $o('pay', ['sid' => 'a1', 'amt' => 100, 'mode' => 'Cash', 'date' => $today, 'nonce' => $nonce()], $CH); of_check($c === 403, 'chairman cannot take fees');
[$c] = $o('call', ['sid' => 'a1', 'outcome' => 'Not answered'], $CH); of_check($c === 403, 'chairman cannot log calls');

// ---- parent / student see only their own ----
[$c, $j] = $o('mine', null, $PA);
of_check($c === 200 && array_column($j['kids'], 'id') == ['a1', 'a2', 'a3'] && count($j['receipts']) === 5, 'parent sees own 3 kids, 5 live receipts: ' . json_encode(array_column($j['kids'], 'id')));
of_check(!isset($j['receipts'][0]['by']) && !isset($j['kids'][0]['dues']['scheme']), 'parent gets no office-only fields');
[$c, $j] = $o('mine', null, $SB); of_check($c === 200 && array_column($j['kids'], 'id') === ['b1'] && !$j['receipts'], 'student sees only self');
[$c] = $o('mine', null, $A); of_check($c === 403, 'mine is for families');

// ---- fee records stay out of normal sync; Principal can reset office PINs ----
[, $j] = $api('load', null, $P);
of_check(!array_filter($j['records'], fn($r) => str_starts_with($r['k'], 'acct')), 'acct records not in load');
[$c] = $api('setpin', ['uid' => 'reception', 'pin' => '1234'], $P); of_check($c === 200, 'principal resets reception PIN');
[$c, $j] = $api('login', ['role' => 'reception', 'pin' => '1234']); of_check($c === 200 && $j['mustChange'] === true, 'reception must choose new PIN after reset');
[$c] = $o('data', null, $R); of_check($c === 401, 'old reception session ended after reset');

echo "Office flow: OK (months so far: $months)\n";
