<?php
// Old ERP Fee Reports (school/feereports_api.php) — CI database only, synthetic rows only.
// The same report before and after the switch: old ERP receipts (archive) + receipts taken here, never counted twice.
require __DIR__ . '/../../config.php';
if (DB_NAME !== 'maakit_jaanch') throw new RuntimeException('CI database only');
require __DIR__ . '/../../school/inc.php';
function frc($v, $m) { if (!$v) throw new RuntimeException('Fee Reports flow: ' . $m); }
function frtok($uid, $role) {
    $db = sch_db();
    $db->prepare('INSERT INTO sch_users(uid,role,tid,must_change) VALUES(?,?,NULL,0) ON DUPLICATE KEY UPDATE role=VALUES(role),must_change=0')->execute([$uid, $role]);
    $t = bin2hex(random_bytes(32));
    $db->prepare('INSERT INTO sch_sessions(token_hash,uid,expires_at) VALUES(?,?,DATE_ADD(NOW(),INTERVAL 1 DAY))')->execute([hash('sha256', $t), $uid]);
    return $t;
}
function frq($a, $token, $q = '', $body = null, $api = 'feereports_api.php') {
    $h = ['X-School-Token: ' . $token]; $o = [CURLOPT_RETURNTRANSFER => true, CURLOPT_PROXY => '', CURLOPT_TIMEOUT => 20];
    if ($body !== null) { $o[CURLOPT_POST] = true; $h[] = 'Content-Type: application/json'; $o[CURLOPT_POSTFIELDS] = json_encode($body); }
    $o[CURLOPT_HTTPHEADER] = $h;
    $c = curl_init('http://127.0.0.1:8099/school/' . $api . '?a=' . $a . $q); curl_setopt_array($c, $o);
    $raw = curl_exec($c); $code = curl_getinfo($c, CURLINFO_HTTP_CODE); curl_close($c);
    frc(is_string($raw) && !preg_match('/Fatal error|Warning:|Uncaught|Deprecated:|Notice:/', $raw), 'PHP response ' . $a . ': ' . substr((string)$raw, 0, 300));
    return [$code, json_decode((string)$raw, true)];
}
$db = sch_db();
$P = frtok('principal', 'principal'); $A = frtok('accountant', 'accountant'); $R = frtok('reception', 'reception');
$clean = fn() => $db->exec("DELETE FROM sch_records WHERE k='ofcRcpt' OR (k='regRow' AND (r LIKE 'oldreceipt|%' OR r LIKE 'oldrcptdet|%' OR r LIKE 'oldfeecard|%')) OR (k='student' AND r='fr-s1')");
try {
    $clean();
    $old = [['session' => '2026-27', 'receiptNo' => '1-10', 'date' => '2026-10-01', 'student' => '', 'scholarNo' => 'S1', 'name' => 'Synthetic One', 'cls' => '5th', 'section' => 'A', 'father' => 'F1', 'months' => 'April', 'total' => '6100', 'discount' => '1900', 'received' => '4200', 'mode' => 'Cash', 'status' => 'Cleared', 'user' => 'admin'],
            ['session' => '2026-27', 'receiptNo' => '1-11', 'date' => '2026-10-02', 'student' => '', 'scholarNo' => 'S2', 'name' => 'Synthetic Two', 'cls' => '6th', 'section' => 'A', 'father' => 'F2', 'months' => 'May', 'total' => '2000', 'discount' => '0', 'received' => '2000', 'mode' => 'Online Cleared', 'bank' => 'HDFC BANK 3039', 'status' => 'Cleared', 'user' => 'Swati']];
    $det = [['session' => '2026-27', 'receiptNo' => '1-10', 'name' => 'Synthetic One', 'lines' => [['1', 'Tution Fee', 'April', '5000'], ['2', 'Transport', 'April', '1100']]],
            ['session' => '2026-27', 'receiptNo' => '1-11', 'name' => 'Synthetic Two', 'lines' => [['1', 'Exam Fee', 'May', '2000']]]];
    foreach ([['oldreceipt', $old], ['oldrcptdet', $det]] as [$m, $rows]) { [$c, $d] = frq('import', $P, '', ['m' => $m, 'rows' => $rows, 'apply' => true], 'registers_api.php'); frc($c === 200, 'import ' . $m . ' ' . json_encode($d)); }
    // One receipt taken here after the switch, and one cancelled
    $mk = fn($no, $n, $date, $amt, $mode, $st) => sch_rec_put('ofcRcpt', '2026-27|' . $no, ['session' => '2026-27', 'no' => $no, 'n' => $n, 'sid' => 'x', 'date' => $date, 'time' => '10:00:00 AM', 'name' => 'Synthetic Three', 'cls' => '5th', 'section' => 'A', 'adm' => 'S3', 'father' => 'F3', 'mother' => '', 'scheme' => '',
        'alloc' => [], 'lines' => [['1', 'Tution Fee', 'June', $amt]], 'total' => $amt, 'late' => 0, 'discount' => 0, 'received' => $amt, 'mode' => $mode, 'bank' => 'NA', 'cheque' => 'NA', 'chequeDate' => $date, 'depositBank' => 'UBI-…8120', 'depositDate' => $date,
        'remark' => '', 'status' => $st, 'by' => 'Accountant', 'cancelReason' => $st === 'Cancelled' ? 'Synthetic wrong entry' : ''], 'principal');
    $mk('1-12', 12, '2026-11-16', 150000, 'UPI', 'Cleared'); $mk('1-13', 13, '2026-11-16', 99900, 'Cash', 'Cancelled');

    [$c] = frq('meta', $R); frc($c === 403, 'reception does not open fee reports');
    [$c, $d] = frq('meta', $A); frc($c === 200 && isset($d['reports']['fee1']) && in_array('Swati', $d['users'], true), 'meta');
    $q = '&k=fee1&ses=2026-27&from=2026-10-01&to=2026-11-30';
    [$c, $d] = frq('report', $A, $q); $H = $d['head'][0];
    frc($c === 200 && $d['count'] === 3 && $H[3] === 'Receipt No' && $H[15] === 'Currently Received', 'fee1 lists archive + new, not the cancelled one ' . json_encode($d['rows']));
    frc($d['total'][15] === '7700' && $d['total'][12] === '9600' && $d['total'][13] === '1900', 'fee1 totals ' . json_encode($d['total']));
    frc(array_column($d['summary']['heads'], 1, 0)['Tution Fee'] === '6500' && $d['summary']['net'] === '7700', 'fee1 summary by head ' . json_encode($d['summary']));
    [$c, $d] = frq('report', $A, '&k=fee1&ses=2026-27&from=2026-10-01&to=2026-10-31&mode=Cash'); frc($d['count'] === 1 && $d['rows'][0][3] === '1-10', 'payment mode filter (Online Cleared is not Cash)');
    [$c, $d] = frq('report', $A, '&k=fee2&ses=2026-27&from=2026-10-01&to=2026-11-30'); $H = $d['head'][0]; $ti = array_search('Tution Fee', $H, true); $ei = array_search('Exam Fee', $H, true);
    frc($H[9] === 'Digital Class Room Fee' && $d['rows'][0][$ti] === '5000' && $d['rows'][1][$ei] === '2000' && $d['total'][$ti] === '6500', 'fee2 head columns in the old order');
    [$c, $d] = frq('report', $A, '&k=fee4&ses=2026-27&from=2026-10-01&to=2026-11-30'); frc($d['count'] === 3 && $d['rows'][2][2] === '1-12' && $d['total'][6] === '7700', 'fee4 day-wise');
    [$c, $d] = frq('report', $A, '&k=bank2&ses=2026-27&from=2026-10-01&to=2026-11-30'); frc($d['count'] === 2 && $d['total'][4] === '3500', 'bank report leaves cash out');
    [$c, $d] = frq('report', $A, '&k=deleted&ses=2026-27'); frc($d['count'] === 1 && $d['rows'][0][1] === '1-13' && $d['rows'][0][8] === 'Synthetic wrong entry', 'deleted (cancelled) receipts');
    [$c, $d] = frq('report', $A, '&k=monthly&ses=2026-27'); $m = array_column($d['rows'], 3, 0); frc($m['October'] === '6200' && $m['November'] === '1500', 'monthly collection ' . json_encode($d['rows']));
    [$c, $d] = frq('report', $A, '&k=head&ses=2026-27'); frc($d['count'] === 4 && $d['total'][13] === '9600', 'head wise lines');
    // A receipt taken here with the same number as an archive row is counted once
    $mk('1-11', 11, '2026-10-02', 200000, 'Online', 'Cleared');
    [$c, $d] = frq('report', $A, $q); frc($d['count'] === 3 && $d['total'][15] === '7700', 'never counted twice ' . json_encode($d['total']));
    // Dues reports from the old fee card (session not started): heads up to the month, old due, the receipts' discount
    sch_rec_put('student', 'fr-s1', ['id' => 'fr-s1', 'adm' => 'S9', 'name' => 'Synthetic Due', 'cls' => '7th', 'section' => 'A', 'phone' => '9811100000'], 'principal');
    $card = ['session' => '2025-26', 'oldId' => '9001', 'liveId' => 'fr-s1', 'name' => 'Synthetic Due', 'cls' => '7th', 'section' => 'A', 'scholar' => 'S9 /', 'father' => 'F9', 'mobile' => '9811100000,', 'oldFee' => '200', 'asOn' => '22-10-2026',
        'profile' => [['School'], ['Class', '', '7th - NA', 'NA | Active'], ['Section', '', 'A'], ['Session', '', '2025-2026', 'Scholar No /Roll No', 'S9 /'], ['Admitted Class', '', '-', 'Student Type', 'Old Student']],
        'months' => [['', 'D/A', 'Fee Name', 'Amount', 'Paid/Disc Amount', 'Balance', 'N Paid', 'Status'], ['April'], ['', '', 'Tution Fee', '1000', '600', '400', '', 'Not Paid'], ['May'], ['', '', 'Transport', '500', '0', '500', '', 'Not Paid']]];
    [$c, $d] = frq('import', $P, '', ['m' => 'oldfeecard', 'rows' => [$card], 'apply' => true], 'registers_api.php'); frc($c === 200, 'card import ' . json_encode($d));
    [$c, $d] = frq('import', $P, '', ['m' => 'oldreceipt', 'rows' => [['session' => '2025-26', 'receiptNo' => '1-90', 'date' => '2025-04-10', 'student' => 'fr-s1', 'scholarNo' => 'S9', 'name' => 'Synthetic Due', 'cls' => '7th', 'section' => 'A', 'total' => '600', 'discount' => '100', 'received' => '500', 'mode' => 'Cash', 'status' => 'Cleared', 'user' => 'admin']], 'apply' => true], 'registers_api.php'); frc($c === 200, 'receipt import');
    [$c, $d] = frq('report', $A, '&k=stmt&ses=2025-26&month=3'); $H = $d['head'][1]; $r = array_combine($H, $d['rows'][0]);
    frc($r['Old Due'] === '200' && $r['Cur Session Fee'] === '1500' && $r['Transport/H Amount'] === '500' && $r['Discount Amount'] === '100' && $r['Currently Received'] === '500' && $r['Transport Due Amount'] === '500' && $r['Fee Due Amount'] === '600' && $r['Net Due Amount'] === '1100' && $r['Status'] === 'Active' && $r['Student Type'] === 'Old Student', 'Fee Statement ' . json_encode($r));
    [$c, $d] = frq('report', $A, '&k=stmt&ses=2025-26&month=4'); $r = array_combine($d['head'][1], $d['rows'][0]); frc($r['Cur Session Fee'] === '1000' && $r['Net Due Amount'] === '600', 'upto April only');
    [$c, $d] = frq('report', $A, '&k=def2&ses=2025-26&month=3'); $r = array_combine($d['head'][0], $d['rows'][0]); frc($r['Total Fee - Disc - Rec + Trns = Due'] === '1700 - 100 - 500 + =1100 (Payble Due)' && $r['Due Fees'] === '1100', 'Fee Defaulter - II ' . json_encode($r));
    [$c, $d] = frq('report', $A, '&k=clswise&ses=2025-26'); $r = array_column($d['rows'], null, 0)['7th A']; frc($r === ['7th A', '1500', '100', '500', '200', '1100'], 'class wise ' . json_encode($r));
    [$c] = frq('report', $A, '&k=stmt&ses='); frc($c === 400, 'dues reports need a session');
    [$c] = frq('report', $A, '&k=nope'); frc($c === 400, 'unknown report');
    echo "Fee Reports: old ERP columns, archive + new receipts once each, filters, head/day/month/bank/deleted reports and roles passed\n";
} finally { $clean(); }
