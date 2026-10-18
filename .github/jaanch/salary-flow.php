<?php
// ============================================================
//  जाँच — Staff salary (school/salary_api.php)
//
//  Kaam ke din (Sunday + Holiday chhod kar) → gair-hazri staff attendance
//  aur approved leave se (half day = 0.5, Sunday/holiday ki absence nahi
//  ginti) → paid leave ke baad katauti → advance/bonus → "Paid" par number
//  jam jaate hain → dobara pay nahi → reopen sirf Principal, wajah ke saath
//  → salary sirf Principal + Accountant dekhein; teacher sirf apni slip →
//  "sal" records aam sync/load me kabhi nahi.
// ============================================================
require_once __DIR__ . '/../../config.php';
if (DB_NAME !== 'maakit_jaanch') throw new RuntimeException('CI database only');
require_once __DIR__ . '/../../inc/db.php';
require_once __DIR__ . '/../../school/inc.php';
require_once __DIR__ . '/../../school/salary_lib.php';

function sl_check($ok, $m) { if (!$ok) throw new RuntimeException('Salary flow: ' . $m); }
function sl_http(string $path, $body = null, ?string $tok = null): array {
    $c = curl_init('http://127.0.0.1:8099' . $path);
    $h = ['X-School-Token: ' . ($tok ?? '')]; $o = [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30, CURLOPT_PROXY => ''];
    if ($body !== null) { $h[] = 'Content-Type: application/json'; $o[CURLOPT_POST] = true; $o[CURLOPT_POSTFIELDS] = json_encode($body); }
    $o[CURLOPT_HTTPHEADER] = $h; curl_setopt_array($c, $o);
    $raw = (string)curl_exec($c); $code = curl_getinfo($c, CURLINFO_HTTP_CODE); curl_close($c);
    sl_check(!preg_match('/Fatal error|Warning:|Uncaught|Deprecated/', $raw), "PHP error at $path: " . substr($raw, 0, 300));
    return [$code, json_decode($raw, true), $raw];
}
$api = fn($a, $b = null, $t = null) => sl_http('/school/api.php?a=' . $a, $b, $t);
$s   = fn($a, $b = null, $t = null, $q = '') => sl_http('/school/salary_api.php?a=' . $a . $q, $b, $t);
$row = fn($j, $id) => array_values(array_filter($j['rows'], fn($r) => $r['staff']['id'] === $id))[0] ?? null;

// ---- working days: September 2026 has 30 days, 4 Sundays, 1 holiday (Tue 15th) = 25 ----
sl_check(count(sal_workdays('2026-09', [['type' => 'Holiday', 'date' => '2026-09-15']])) === 25, 'working days');
sl_check(count(sal_workdays('2026-09', [['type' => 'Holiday', 'date' => '2026-09-14', 'to' => '2026-09-16'], ['type' => 'Event', 'date' => '2026-09-18']])) === 23, 'holiday range; events are working days');

foreach (['sch_sessions', 'sch_users', 'sch_records', 'sch_log', 'sch_login_fail'] as $t) $pdo->exec("DELETE FROM $t");
$put = fn($k, $r, $d) => sch_rec_put($k, $r, $d, 'principal');
$put('settings', 'main', ['school' => 'Salary Test School', 'session' => '2026–27', 'sessionStart' => '2026-04-01']);
$put('teacher', 'K', ['name' => 'Kartikey Sir', 'role' => 'Social Science']);
$put('teacher', 'MA', ['name' => 'Mamta Ma\'am', 'role' => 'Hindi']);
$put('cal', 'h1', ['id' => 'h1', 'date' => '2026-09-15', 'title' => 'Holiday', 'type' => 'Holiday']);
$put('staffAtt', '2026-09-02', ['absent' => ['K']]);
$put('staffAtt', '2026-09-03', ['absent' => ['K'], 'from' => ['K' => 4]]);        // half day
$put('staffAtt', '2026-09-06', ['absent' => ['K']]);                              // Sunday — not counted
$put('staffAtt', '2026-09-15', ['absent' => ['K']]);                              // holiday — not counted
$put('staffAtt', '2026-10-01', ['absent' => ['K']]);                              // next month
$put('leave', 'l1', ['id' => 'l1', 'tid' => 'K', 'from' => '2026-09-10', 'to' => '2026-09-11', 'status' => 'Approved', 'reason' => 'x']);
$put('leave', 'l2', ['id' => 'l2', 'tid' => 'K', 'from' => '2026-09-21', 'to' => '2026-09-21', 'status' => 'Pending', 'reason' => 'x']);
$pin = fn($uid, $role, $tid, $p) => $pdo->prepare('INSERT INTO sch_users (uid, role, tid, pin_hash, must_change) VALUES (?,?,?,?,0)')->execute([$uid, $role, $tid, password_hash($p, PASSWORD_DEFAULT)]);
$pin('principal', 'principal', 'P', '2468'); $pin('accountant', 'accountant', null, '4321'); $pin('reception', 'reception', null, '5555');
$pin('t:K', 'teacher', 'K', '7777'); $pin('t:MA', 'teacher', 'MA', '8888'); $pin('chairman', 'chairman', null, '6666');
$tok = [];
foreach ([['P', ['role' => 'principal', 'pin' => '2468']], ['A', ['role' => 'accountant', 'pin' => '4321']], ['R', ['role' => 'reception', 'pin' => '5555']],
          ['K', ['role' => 'teacher', 'tid' => 'K', 'pin' => '7777']], ['MA', ['role' => 'teacher', 'tid' => 'MA', 'pin' => '8888']], ['CH', ['role' => 'chairman', 'pin' => '6666']]] as [$n, $b]) {
    [, $j] = $api('login', $b); $tok[$n] = $j['token'];
}
$q = '&ym=2026-09';

// ---- who can see salaries ----
foreach (['K', 'R', 'CH'] as $n) { [$c] = $s('sheet', null, $tok[$n], $q); sl_check($c === 403, "$n cannot see the salary sheet"); }
[$c, $j] = $s('sheet', null, $tok['A'], $q); sl_check($c === 200 && $j['workDays'] === 25 && $j['can']['staff'] === false, 'accountant sees sheet');
sl_check($row($j, 't:K')['status'] === 'Not set' && $row($j, 't:K')['calc']['absent'] === 3.5, 'K absence 1 + 0.5 + 2 leave = 3.5: ' . json_encode($row($j, 't:K')['calc']));
[$c] = $s('staff', ['id' => 't:K', 'monthly' => 25000, 'paidLeave' => 1], $tok['A']); sl_check($c === 403, 'only Principal sets salaries');

// ---- Principal sets salaries, adds a driver ----
[$c] = $s('staff', ['id' => 't:K', 'monthly' => 25000, 'paidLeave' => 1], $tok['P']); sl_check($c === 200, 'set K salary');
[$c] = $s('staff', ['id' => 't:MA', 'monthly' => 18000.5, 'paidLeave' => 1], $tok['P']); sl_check($c === 400, 'whole rupees');
[$c] = $s('staff', ['id' => 't:MA', 'monthly' => 18000, 'paidLeave' => 1.3], $tok['P']); sl_check($c === 400, 'paid leave in half days');
[$c] = $s('staff', ['id' => 't:MA', 'monthly' => 18000, 'paidLeave' => 1], $tok['P']); sl_check($c === 200, 'set MA salary');
[$c] = $s('staff', ['id' => '', 'name' => 'R', 'monthly' => 9000], $tok['P']); sl_check($c === 400, 'name needed');
[$c, $j] = $s('staff', ['id' => '', 'name' => 'Ramesh', 'role' => 'Driver', 'monthly' => 9000, 'paidLeave' => 0], $tok['P']); $D = $j['id'];
sl_check($c === 200 && str_starts_with($D, 'x'), 'driver added');

// ---- adjust + calculation ----
[$c] = $s('adjust', ['ym' => '2026-09', 'id' => 't:K', 'advance' => 3000, 'bonus' => 500, 'note' => 'Festival bonus'], $tok['A']); sl_check($c === 200, 'accountant adjusts');
[$c] = $s('adjust', ['ym' => '2026-09', 'id' => $D, 'manualAbsent' => 2], $tok['A']); sl_check($c === 200, 'driver absent days entered');
[$c] = $s('adjust', ['ym' => '2026-09', 'id' => $D, 'manualAbsent' => 2.3], $tok['A']); sl_check($c === 400, 'half days only');
[, $j] = $s('sheet', null, $tok['P'], $q);
$k = $row($j, 't:K')['calc']; $d = $row($j, $D)['calc']; $m = $row($j, 't:MA')['calc'];
sl_check($k['unpaid'] === 2.5 && $k['deduction'] === 2500 && $k['net'] === 25000 - 2500 - 3000 + 500, 'K net 20000: ' . json_encode($k));
sl_check($d['deduction'] === 720 && $d['net'] === 8280 && $d['manual'] === true, 'driver 9000/25*2 = 720: ' . json_encode($d));
sl_check($m['deduction'] === 0 && $m['net'] === 18000, 'MA no absence');

// ---- pay, freeze, reopen ----
[$c] = $s('pay', ['ym' => '2026-09', 'id' => 't:K', 'mode' => 'Gold', 'date' => '2026-10-05'], $tok['A']); sl_check($c === 400, 'pay mode from list');
[$c] = $s('pay', ['ym' => '2026-09', 'id' => 't:K', 'mode' => 'Bank transfer', 'ref' => 'NEFT123', 'date' => date('Y-m-d', strtotime('+2 days'))], $tok['A']); sl_check($c === 400, 'no future pay date');
[$c, $j] = $s('pay', ['ym' => '2026-09', 'id' => 't:K', 'mode' => 'Bank transfer', 'ref' => 'NEFT123', 'date' => date('Y-m-d')], $tok['A']);
sl_check($c === 200 && $j['row']['status'] === 'Paid' && $j['row']['calc']['net'] === 20000, 'K paid');
[$c] = $s('pay', ['ym' => '2026-09', 'id' => 't:K', 'mode' => 'Cash', 'date' => date('Y-m-d')], $tok['P']); sl_check($c === 400, 'cannot pay twice');
[$c] = $s('adjust', ['ym' => '2026-09', 'id' => 't:K', 'advance' => 0], $tok['P']); sl_check($c === 400, 'paid month is locked');
$put('staffAtt', '2026-09-22', ['absent' => ['K']]);
[, $j] = $s('sheet', null, $tok['P'], $q); sl_check($row($j, 't:K')['calc']['net'] === 20000, 'paid numbers frozen after a later attendance change');
[$c] = $s('unpay', ['ym' => '2026-09', 'id' => 't:K', 'reason' => 'x'], $tok['A']); sl_check($c === 403, 'accountant cannot reopen');
[$c] = $s('unpay', ['ym' => '2026-09', 'id' => 't:K', 'reason' => ''], $tok['P']); sl_check($c === 400, 'reopen needs a reason');
[$c] = $s('unpay', ['ym' => '2026-09', 'id' => 't:K', 'reason' => 'Missed absence on 22nd'], $tok['P']); sl_check($c === 200, 'principal reopens');
[, $j] = $s('sheet', null, $tok['P'], $q); $k = $row($j, 't:K')['calc'];
sl_check($row($j, 't:K')['status'] === 'Due' && $k['absent'] === 4.5 && $k['advance'] === 3000 && $k['net'] === 19000, 'reopened: recalculated, adjustments kept: ' . json_encode($k));
[$c] = $s('pay', ['ym' => '2026-09', 'id' => 't:K', 'mode' => 'Cash', 'date' => date('Y-m-d')], $tok['P']); sl_check($c === 200, 'paid again');
sl_check((int)$pdo->query("SELECT COUNT(*) FROM sch_log WHERE k='Salary' AND x LIKE 'REOPENED Kartikey Sir 2026-09%Missed absence on 22nd'")->fetchColumn() === 1, 'reopen logged');

// ---- teacher sees only their own paid slips ----
[$c, $j] = $s('mine', null, $tok['K']); sl_check($c === 200 && count($j['slips']) === 1 && $j['slips'][0]['calc']['net'] === 19000, 'K sees own slip');
[$c, $j] = $s('mine', null, $tok['MA']); sl_check($c === 200 && !$j['slips'], 'MA sees nothing (not paid)');
[$c] = $s('mine', null, $tok['A']); sl_check($c === 403, 'mine is for teachers');

// ---- never in normal sync ----
foreach (['P', 'K'] as $n) { [, $j] = $api('load', null, $tok[$n]); sl_check(!array_filter($j['records'], fn($r) => str_starts_with($r['k'], 'sal')), "sal records not in $n load"); }
[$c, $j] = $api('sync', ['put' => [['k' => 'salStaff', 'r' => 't:K', 'd' => ['monthly' => 99999]]], 'del' => [], 'log' => []], $tok['P']);
sl_check(count($j['rejected']) === 1 && sch_rec_get('salStaff', 't:K')['monthly'] === 25000, 'salary cannot be written through sync');
echo "Salary flow: OK\n";
