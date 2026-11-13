<?php
// ============================================================
//  जाँच — Fee demand bill for parents (school/demandbill_api.php, 13 Nov 2026)
//
//  Chairman / Manager / Principal / Accountant open a child's bill → heads with
//  Paid (from receipt lines) and Concession kept apart, school fee and transport
//  due apart, payable till this month → siblings by the same mobile with a
//  family total → teachers cannot open it.
// ============================================================
require_once __DIR__ . '/../../config.php';
if (DB_NAME !== 'maakit_jaanch') throw new RuntimeException('CI database only');
require_once __DIR__ . '/../../inc/db.php';
require_once __DIR__ . '/../../school/inc.php';
function dbc($ok, $m) { if (!$ok) throw new RuntimeException('Demand bill flow: ' . $m); }
function dbh(string $path, ?string $tok = null): array {
    $c = curl_init('http://127.0.0.1:8099' . $path); curl_setopt_array($c, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30, CURLOPT_PROXY => '', CURLOPT_HTTPHEADER => ['X-School-Token: ' . ($tok ?? '')]]);
    $raw = (string)curl_exec($c); $code = curl_getinfo($c, CURLINFO_HTTP_CODE); curl_close($c);
    dbc(!preg_match('/Fatal error|Warning:|Uncaught|Deprecated/', $raw), "PHP error at $path: " . substr($raw, 0, 300));
    return [$code, json_decode($raw, true)];
}
$login = function (array $b) { $c = curl_init('http://127.0.0.1:8099/school/api.php?a=login'); curl_setopt_array($c, [CURLOPT_RETURNTRANSFER => true, CURLOPT_PROXY => '', CURLOPT_POST => true, CURLOPT_HTTPHEADER => ['Content-Type: application/json'], CURLOPT_POSTFIELDS => json_encode($b)]); $j = json_decode((string)curl_exec($c), true); curl_close($c); return $j['token'] ?? null; };

foreach (['sch_sessions', 'sch_users', 'sch_records', 'sch_log', 'sch_login_fail'] as $t) $pdo->exec("DELETE FROM $t");
$put = fn($k, $r, $d) => sch_rec_put($k, $r, $d, 'principal');
$put('settings', 'main', ['school' => 'Bill Test School', 'session' => '2026-27']);
$put('teacher', 'K', ['name' => 'Kartikey Sir']);
$put('student', 'db1', ['id' => 'db1', 'name' => 'Raghav Tiwari', 'cls' => 'PG', 'section' => 'A', 'adm' => '24554', 'father' => 'Anand Tiwari', 'phone' => '8808585945']);
$put('student', 'db2', ['id' => 'db2', 'name' => 'Riya Tiwari', 'cls' => '3rd', 'section' => 'A', 'adm' => '24001', 'father' => 'Anand Tiwari', 'phone' => '+91 88085 85945']);
$put('student', 'db3', ['id' => 'db3', 'name' => 'Other Child', 'cls' => '1st', 'section' => 'A', 'adm' => '24002', 'father' => 'X', 'phone' => '9000000000']);
$hd = ['', 'D/A', 'Fee Name', 'Amount', 'Paid/Disc Amount', 'Balance', 'N Paid', 'Status'];
$put('regRow', 'oldfeecard|db1', ['session' => '2026-27', 'oldId' => '1', 'liveId' => 'db1', 'name' => 'Raghav Tiwari', 'cls' => 'PG', 'section' => 'A', 'oldFee' => '0', 'transferFee' => '0', 'asOn' => '11-11-2026',
    'months' => [$hd, ['April'], ['', '', 'Admission Fee', '6000', '6000', '0', '', 'Paid'], ['', '', 'Tution Fee', '600', '600', '0', '', 'Paid'], ['July'], ['', '', 'Tution Fee', '600', '600', '0', '', 'Paid'], ['', '', 'Transport', '500', '500', '0', '', 'Paid'], ['November'], ['', '', 'Tution Fee', '600', '100', '500', '', 'Not Paid'], ['', '', 'Transport', '500', '0', '500', '', 'Not Paid'], ['March'], ['', '', 'Tution Fee', '600', '0', '600', '', 'Not Paid']]]);
$put('regRow', 'oldfeecard|db2', ['session' => '2026-27', 'oldId' => '2', 'liveId' => 'db2', 'name' => 'Riya Tiwari', 'cls' => '3rd', 'section' => 'A', 'oldFee' => '200', 'transferFee' => '0', 'asOn' => '11-11-2026',
    'months' => [$hd, ['April'], ['', '', 'Tution Fee', '700', '0', '700', '', 'Not Paid']]]);
$put('regRow', 'oldfeecard|db3', ['session' => '2026-27', 'oldId' => '3', 'liveId' => 'db3', 'name' => 'Other Child', 'cls' => '1st', 'section' => 'A', 'oldFee' => '0', 'transferFee' => '0', 'asOn' => '11-11-2026', 'months' => [$hd, ['April'], ['', '', 'Tution Fee', '700', '700', '0', '', 'Paid']]]);
$put('regRow', 'oldreceipt|db1r', ['session' => '2026-27', 'receiptNo' => '1-179', 'date' => '01-08-2026', 'student' => 'db1', 'scholarNo' => '24554', 'name' => 'Raghav Tiwari', 'cls' => 'PG', 'section' => 'A', 'months' => 'July', 'total' => 90000, 'discount' => 0, 'lateFee' => 0, 'received' => 90000, 'mode' => 'Cash', 'status' => 'Cleared']);
$put('regRow', 'oldrcptdet|db1r', ['session' => '2026-27', 'receiptNo' => '1-179', 'lines' => [['1', 'Tution Fee', 'July', '400'], ['2', 'Transport', 'July', '500']]]);
$pin = fn($uid, $role, $tid, $p) => $pdo->prepare('INSERT INTO sch_users (uid, role, tid, pin_hash, must_change) VALUES (?,?,?,?,0)')->execute([$uid, $role, $tid, password_hash($p, PASSWORD_DEFAULT)]);
$pin('chairman', 'chairman', null, '6666'); $pin('manager', 'manager', null, '9191'); $pin('t:K', 'teacher', 'K', '7777');
$CH = $login(['role' => 'chairman', 'pin' => '6666']); $M = $login(['role' => 'manager', 'pin' => '9191']); $K = $login(['role' => 'teacher', 'tid' => 'K', 'pin' => '7777']);

[$c] = dbh('/school/demandbill_api.php?a=list', $K); dbc($c === 403, 'teacher cannot open demand bills');
[$c, $j] = dbh('/school/demandbill_api.php?a=list', $M); dbc($c === 200 && count($j['rows']) === 3 && $j['ses'] === '2026-27', 'Manager sees the list: ' . json_encode($j));
[$c, $j] = dbh('/school/demandbill_api.php?a=bill&sid=db1', $CH); dbc($c === 200 && count($j['kids']) === 2, 'bill with the sibling (same mobile): ' . json_encode($j['kids'] ?? $j));
$k = $j['kids'][0]; $byM = []; foreach ($k['heads'] as $h) $byM[$h['m'] . '|' . $h['name']] = $h;
dbc($byM['April|Admission Fee']['paid'] === 0 && $byM['April|Admission Fee']['conc'] === 6000, 'admission fee settled without a receipt = concession');
dbc($byM['July|Tuition Fee']['paid'] === 400 && $byM['July|Tuition Fee']['conc'] === 200, 'July tuition: 400 paid by receipt, 200 concession');
dbc($byM['July|Transport']['tr'] === true && $byM['July|Transport']['paid'] === 500, 'transport row flagged');
$nowM = (int)date('n'); $nov = (($nowM + 8) % 12) >= ((11 + 8) % 12);
if ($nov) dbc($k['total']['nowFee'] === 500 && $k['total']['nowTr'] === 500 && $k['total']['now'] === 1000, 'payable till now = Nov tuition 500 + transport 500: ' . json_encode($k['total']));
dbc($k['total']['received'] === 900 && $k['total']['due'] === 1600, 'cash received 900, session due 1600: ' . json_encode($k['total']));
dbc($j['kids'][1]['name'] === 'Riya Tiwari' && $j['kids'][1]['total']['oldDue'] === 200, 'sibling with old due');
[$c] = dbh('/school/demandbill_api.php?a=bill&sid=nope', $CH); dbc($c === 400, 'unknown child');
echo "Demand bill flow: OK\n";
