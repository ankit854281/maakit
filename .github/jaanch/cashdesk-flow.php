<?php
// ============================================================
//  जाँच — Manager app Cash Book + Tour fee (school/cashdesk_api.php, 13 Nov 2026)
//
//  opening balance (once) → fee receipt of the day comes in by itself → expense
//  and bank deposit go out → closing = opening + in − out, next day opens with it →
//  the same rows are in the account reports (feereports ?a=accounts) → tour:
//  set up, receipt per child (TR/ses/0001) + a Cash Book row, twice refused,
//  cancel removes the money → Chairman views only, teacher refused → Manager
//  can take fees in Fee Receipt.
// ============================================================
require_once __DIR__ . '/../../config.php';
if (DB_NAME !== 'maakit_jaanch') throw new RuntimeException('CI database only');
require_once __DIR__ . '/../../inc/db.php';
require_once __DIR__ . '/../../school/inc.php';

function cd_check($ok, $m) { if (!$ok) throw new RuntimeException('Cash desk flow: ' . $m); }
function cd_http(string $path, $body = null, ?string $tok = null): array {
    $c = curl_init('http://127.0.0.1:8099' . $path);
    $h = ['X-School-Token: ' . ($tok ?? '')]; $o = [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30, CURLOPT_PROXY => ''];
    if ($body !== null) { $h[] = 'Content-Type: application/json'; $o[CURLOPT_POST] = true; $o[CURLOPT_POSTFIELDS] = json_encode($body); }
    $o[CURLOPT_HTTPHEADER] = $h; curl_setopt_array($c, $o);
    $raw = (string)curl_exec($c); $code = curl_getinfo($c, CURLINFO_HTTP_CODE); curl_close($c);
    cd_check(!preg_match('/Fatal error|Warning:|Uncaught|Deprecated/', $raw), "PHP error at $path: " . substr($raw, 0, 300));
    return [$code, json_decode($raw, true), $raw];
}
$api = fn($a, $b = null, $t = null) => cd_http('/school/api.php?a=' . $a, $b, $t);
$cd  = fn($a, $b = null, $t = null, $q = '') => cd_http('/school/cashdesk_api.php?a=' . $a . $q, $b, $t);

foreach (['sch_sessions', 'sch_users', 'sch_records', 'sch_log', 'sch_login_fail'] as $t) $pdo->exec("DELETE FROM $t");
$put = fn($k, $r, $d, $by = 'principal') => sch_rec_put($k, $r, $d, $by);
$put('settings', 'main', ['school' => 'Cash Test School', 'session' => '2026-27', 'managerName' => 'Rakesh Manager']);
$put('teacher', 'K', ['name' => 'Kartikey Sir']); $put('teacher', 'P', ['name' => 'Principal']);
$put('classes', 'main', [['c' => '4th', 'ct' => 'K'], ['c' => '5th', 'ct' => ''], ['c' => '1st', 'ct' => '']]);
foreach ([['s1', 'Aman', '4th', '9876543210'], ['s2', 'Bina', '4th', ''], ['s3', 'Chandu', '5th', ''], ['s4', 'Dev', '1st', '']] as [$id, $n, $c, $ph]) $put('student', $id, ['id' => $id, 'name' => $n, 'cls' => $c, 'phone' => $ph, 'father' => 'F ' . $n]);
$d1 = date('Y-m-d', strtotime('-2 days')); $d2 = date('Y-m-d', strtotime('-1 day'));
$put('ofcRcpt', '2026-27|1-900', ['session' => '2026-27', 'no' => '1-900', 'sid' => 's1', 'date' => $d1, 'name' => 'Aman', 'cls' => '4th', 'section' => '', 'adm' => 'A1', 'father' => 'F Aman',
    'lines' => [['1', 'Tution Fee', 'Nov', 150000]], 'total' => 150000, 'late' => 0, 'discount' => 0, 'received' => 150000, 'mode' => 'Cash', 'bank' => 'NA', 'cheque' => 'NA', 'chequeDate' => $d1, 'depositBank' => '', 'status' => 'Cleared', 'by' => 'Accountant'], 'accountant');
$pin = fn($uid, $role, $tid, $p) => $pdo->prepare('INSERT INTO sch_users (uid, role, tid, pin_hash, must_change) VALUES (?,?,?,?,0)')->execute([$uid, $role, $tid, password_hash($p, PASSWORD_DEFAULT)]);
$pin('principal', 'principal', 'P', '2468'); $pin('t:K', 'teacher', 'K', '7777'); $pin('chairman', 'chairman', null, '6666'); $pin('manager', 'manager', null, '9191');
$tok = [];
foreach ([['P', ['role' => 'principal', 'pin' => '2468']], ['K', ['role' => 'teacher', 'tid' => 'K', 'pin' => '7777']], ['CH', ['role' => 'chairman', 'pin' => '6666']], ['M', ['role' => 'manager', 'pin' => '9191']]] as [$n, $b]) {
    [, $j] = $api('login', $b); $tok[$n] = $j['token'] ?? null; cd_check($tok[$n], "login $n");
}
$M = $tok['M'];

// ---- who ----
[$c] = $cd('day', null, $tok['K']); cd_check($c === 403, 'teacher cannot open the cash book');
[$c, $j] = $cd('day', null, $tok['CH'], "&date=$d1"); cd_check($c === 200 && $j['can'] === false, 'Chairman views');
[$c] = $cd('entry', ['type' => 'expense', 'date' => $d1, 'amount' => 10, 'head' => 'Tea'], $tok['CH']); cd_check($c === 403, 'Chairman cannot write');

// ---- opening, expense, deposit ----
[$c, $j] = $cd('entry', ['type' => 'opening', 'date' => $d1, 'amount' => '5000'], $M); cd_check($c === 200 && str_starts_with($j['no'], 'VCH/2026-27/'), 'opening saved: ' . json_encode($j));
[$c] = $cd('entry', ['type' => 'opening', 'date' => $d1, 'amount' => '100'], $M); cd_check($c === 400, 'opening only once');
[$c] = $cd('entry', ['type' => 'expense', 'date' => $d1, 'amount' => '350.50', 'head' => 'Diesel / Petrol', 'party' => 'HP pump', 'mode' => 'Cash', 'ref' => 'B12'], $M); cd_check($c === 200, 'expense saved');
[$c] = $cd('entry', ['type' => 'deposit', 'date' => $d1, 'amount' => '1000'], $M); cd_check($c === 200, 'bank deposit saved');
[$c] = $cd('entry', ['type' => 'expense', 'date' => date('Y-m-d', strtotime('+1 day')), 'amount' => 5, 'head' => 'Tea'], $M); cd_check($c === 400, 'no future date');
[$c] = $cd('entry', ['type' => 'expense', 'date' => $d1, 'amount' => 0, 'head' => 'Tea'], $M); cd_check($c === 400, 'amount must be more than 0');
[$c] = $cd('entry', ['type' => 'income', 'date' => $d1, 'amount' => 10, 'head' => 'Tour fee — x'], $M); cd_check($c === 400, 'tour fee only through Tour fee');
$cb = array_values(array_filter(array_map(fn($r) => json_decode($r, true), $pdo->query("SELECT d FROM sch_records WHERE k='regRow' AND r LIKE 'cashbook|%' AND deleted=0")->fetchAll(PDO::FETCH_COLUMN))));
$ex = array_values(array_filter($cb, fn($r) => $r['head'] === 'Diesel / Petrol'))[0];
cd_check($ex['amount'] === 35050 && $ex['side'] === 'Payment (money out)' && $ex['_by'] === 'Rakesh Manager (Manager)' && $ex['party'] === 'HP pump', 'same Cash Book register row, paise: ' . json_encode($ex));

[$c, $j] = $cd('day', null, $M, "&date=$d1");
cd_check($c === 200 && $j['opening'] == 0 && $j['in'] == 6500 && $j['out'] == 1350.5 && $j['closing'] == 5149.5, 'day: opening 0, in 5000+1500, out 350.50+1000: ' . json_encode(array_intersect_key($j, array_flip(['opening', 'in', 'out', 'closing']))));
cd_check($j['byKind']['fee'] == 1500 && $j['byKind']['opening'] == 5000 && $j['byKind']['expense'] == 350.5 && $j['byKind']['deposit'] == 1000, 'by kind: ' . json_encode($j['byKind']));
$feeRow = array_values(array_filter($j['rows'], fn($r) => $r['kind'] === 'fee'))[0]; cd_check($feeRow['vno'] === '1-900' && $feeRow['id'] === '', 'fee receipt row, not deletable here');
[, $j2] = $cd('day', null, $M, "&date=$d2"); cd_check($j2['opening'] == 5149.5 && $j2['closing'] == 5149.5, 'next day opens with the closing');
[, $m] = $cd('month', null, $M, '&ym=' . substr($d1, 0, 7)); $md = array_column($m['days'], null, 'date'); cd_check(substr($d1, 0, 7) !== substr($d2, 0, 7) || ($md[$d1]['closing'] == 5149.5 && $md[$d2]['opening'] == 5149.5), 'month day by day');
[, $acc] = cd_http('/school/feereports_api.php?a=accounts', null, $tok['P']);
$sum = 0; foreach ($acc['rows'] as $r) $sum += $r['drCash'] - $r['crCash']; cd_check(abs($sum - 5149.5) < 0.01, 'account reports show the same money: ' . $sum);

// ---- cash count ----
[$c, $j] = $cd('verify', ['date' => $d1, 'counted' => '5100', 'remarks' => 'short'], $M); cd_check($c === 200 && $j['diff'] == -49.5, 'cash count difference');
[, $j] = $cd('day', null, $M, "&date=$d1"); cd_check(count($j['verify']) === 1 && $j['verify'][0]['counted'] == 5100, 'count shown');

// ---- delete ----
$exRow = array_values(array_filter($j['rows'], fn($r) => $r['head'] === 'Diesel / Petrol'))[0];
[$c] = $cd('del', ['id' => $exRow['id'], 'reason' => ''], $M); cd_check($c === 400, 'reason needed');
[$c] = $cd('del', ['id' => $exRow['id'], 'reason' => 'typed twice'], $M); cd_check($c === 200, 'deleted');
[, $j] = $cd('day', null, $M, "&date=$d1"); cd_check($j['out'] == 1000 && sch_rec_get('regRow', 'cashbook|' . $exRow['id']) === null, 'expense gone');

// ---- tour ----
[$c] = $cd('tour', ['name' => 'Prayagraj Tour', 'date' => '2026-11-14', 'amount' => 700, 'classes' => []], $M); cd_check($c === 400, 'classes needed');
[$c, $j] = $cd('tour', ['name' => 'Prayagraj Tour', 'date' => '2026-11-14', 'amount' => 700, 'classes' => ['4th', '5th', 'zz']], $M); $T = $j['id']; cd_check($c === 200 && preg_match('/^t[a-f0-9]{10}$/', $T), 'tour saved');
[, $j] = $cd('tours', null, $M); $t = $j['tours'][0]; cd_check(count($j['tours']) === 1 && $t['classes'] === ['4th', '5th'] && count($t['kids']) === 3 && $t['amount'] == 700, 'tour: classes 4th+5th, 3 children: ' . json_encode($t['classes']));
[$c] = $cd('tourpay', ['tour' => $T, 'sid' => 's4', 'mode' => 'Cash'], $M); cd_check($c === 400, 'class not on the tour');
[$c] = $cd('tourpay', ['tour' => $T, 'sid' => 's1', 'mode' => 'UPI', 'ref' => ''], $M); cd_check($c === 400, 'UPI needs a number');
[$c, $j] = $cd('tourpay', ['tour' => $T, 'sid' => 's1', 'mode' => 'Cash', 'date' => $d2], $M); cd_check($c === 200 && $j['no'] === 'TR/2026-27/0001', 'first tour receipt: ' . json_encode($j));
[$c] = $cd('tourpay', ['tour' => $T, 'sid' => 's1', 'mode' => 'Cash', 'date' => $d2], $M); cd_check($c === 400, 'twice refused');
[$c, $j] = $cd('tourpay', ['tour' => $T, 'sid' => 's3', 'amount' => 500, 'mode' => 'UPI', 'ref' => 'UTR99', 'date' => $d2], $tok['P']); cd_check($c === 200 && $j['no'] === 'TR/2026-27/0002', 'Principal takes a tour fee too');
[, $j] = $cd('day', null, $M, "&date=$d2"); cd_check($j['byKind']['tour'] == 1200 && $j['in'] == 1200 && count(array_filter($j['rows'], fn($r) => $r['kind'] === 'tour' && $r['id'] !== '')) === 2, 'tour money in the day book');
[, $j] = $cd('tours', null, $tok['CH']); $k = array_column($j['tours'][0]['kids'], null, 'id'); cd_check($k['s1']['pay']['no'] === 'TR/2026-27/0001' && $k['s3']['pay']['amount'] == 500 && $k['s2']['pay'] === null && $j['can'] === false, 'Chairman sees who paid');
$row = array_values(array_filter($j['tours'][0]['kids'], fn($x) => $x['id'] === 's1'))[0];
$cbId = sch_rec_get('regTourPay', "$T|s1")['cb'];
[$c] = $cd('del', ['id' => $cbId, 'reason' => 'try'], $M); cd_check($c === 400, 'tour row not deleted from the cash book');
[$c] = $cd('tourcancel', ['tour' => $T, 'sid' => 's1', 'reason' => 'not going'], $M); cd_check($c === 200, 'cancelled');
cd_check(sch_rec_get('regTourPay', "$T|s1")['status'] === 'Cancelled' && sch_rec_get('regRow', "cashbook|$cbId") === null, 'cancel removes the money');
[$c, $j] = $cd('tourpay', ['tour' => $T, 'sid' => 's1', 'mode' => 'Cash', 'date' => $d2], $M); cd_check($c === 200 && $j['no'] === 'TR/2026-27/0003' && count(sch_rec_get('regTourPay', "$T|s1")['earlier']) === 1, 'paid again with a new number, old one kept');
[, $j] = $cd('day', null, $M, "&date=$d2"); cd_check($j['in'] == 1200, 'day total after cancel + new receipt');
[, $j] = $api('load', null, $tok['P']); cd_check(!array_filter($j['records'] ?? [], fn($x) => in_array($x['k'], ['regTour', 'regTourPay'], true)), 'tour records not in the normal load');
cd_check((int)$pdo->query("SELECT COUNT(*) FROM sch_log WHERE k='Tour fee'")->fetchColumn() === 5, 'tour logged');

// ---- Name written now, tour fee later (13 Nov 2026) ----
[$c] = $cd('tourbook', ['tour' => $T, 'sid' => 's2', 'on' => true], $M); cd_check($c === 200, 'name written, pay later');
[$c] = $cd('tourbook', ['tour' => $T, 'sid' => 's4', 'on' => true], $M); cd_check($c === 400, 'class not on the tour cannot be written');
[$c] = $cd('tourbook', ['tour' => $T, 'sid' => 's2', 'on' => true], $tok['CH']); cd_check($c === 403, 'Chairman only views');
[, $j] = $cd('tours', null, $tok['CH']); $k = array_column($j['tours'][0]['kids'], null, 'id'); cd_check($k['s2']['book'] !== null && $k['s2']['pay'] === null && $k['s3']['book'] === null, 'Chairman sees who is going, fee later');
[$c] = $cd('tourbook', ['tour' => $T, 'sid' => 's2', 'on' => false], $M);
[, $j] = $cd('tours', null, $M); $k = array_column($j['tours'][0]['kids'], null, 'id'); cd_check($c === 200 && $k['s2']['book'] === null, 'name removed');

// ---- Manager in Fee Receipt ----
[$c] = cd_http('/school/feecollect_api.php?a=state', null, $M); cd_check($c === 200, 'Manager opens Fee Receipt');

echo "Cash desk flow: OK\n";
