<?php
// Fee Reports (school/feereports_api.php) — CI database only, synthetic rows only.
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
function frq($token) {
    $c = curl_init('http://127.0.0.1:8099/school/feereports_api.php?a=receipts');
    curl_setopt_array($c, [CURLOPT_RETURNTRANSFER => true, CURLOPT_PROXY => '', CURLOPT_TIMEOUT => 20, CURLOPT_HTTPHEADER => ['X-School-Token: ' . $token]]);
    $raw = curl_exec($c); $code = curl_getinfo($c, CURLINFO_HTTP_CODE); curl_close($c);
    frc(is_string($raw) && !preg_match('/Fatal error|Warning:|Uncaught|Deprecated:|Notice:/', $raw), 'PHP response: ' . substr((string)$raw, 0, 300));
    return [$code, json_decode((string)$raw, true)];
}
$db = sch_db();
$A = frtok('frx:acc', 'accountant'); $R = frtok('frx:rec', 'reception');
$clean = fn() => $db->exec("DELETE FROM sch_records WHERE (k='regRow' AND (r LIKE 'oldreceipt|frx%' OR r LIKE 'oldrcptdet|frx%')) OR (k='ofcRcpt' AND r LIKE '2099-00|%') OR (k='regPage' AND r='2099-00|report_fee.php')");
try {
    $clean();
    sch_rec_put('regRow', 'oldreceipt|frx1', ['session' => '2099-00', 'receiptNo' => '1-7', 'date' => '2099-04-08', 'student' => '', 'scholarNo' => 'FRX', 'name' => 'Synthetic Fee', 'father' => 'F', 'cls' => 'LKG', 'section' => 'A', 'months' => 'April',
        'total' => 510000, 'discount' => 450000, 'lateFee' => 0, 'received' => 60000, 'mode' => 'NEFT Cleared', 'bank' => '', 'cheque' => '', 'status' => 'Cleared', 'user' => 'Swati', 'remark' => ''], 'principal');
    sch_rec_put('regRow', 'oldrcptdet|frx1', ['session' => '2099-00', 'receiptNo' => '1-7', 'lines' => [['1', 'Tution Fee', 'April', '600'], ['2', 'Exam Fee', 'April', '4,500']]], 'principal');
    $page = [['Sno', 'Session', 'Last Followup Remark', 'Receipt No', 'Customer Bank', 'Cheque No', 'Status', 'Bank'], ['1', '2099-2000', 'Synthetic A/C', "'1-7'", 'NA', 'NA', 'Cleared', 'HDFC BANK 3039']];
    sch_rec_put('regPage', '2099-00|report_fee.php', ['session' => '2099-00', 'page' => 'report_fee.php', 'title' => 'Fee Collection Report I', 'group' => '04 Fee', 'h' => base64_encode(gzencode(json_encode($page)))], 'principal');
    sch_rec_put('ofcRcpt', '2099-00|1-8', ['session' => '2099-00', 'no' => '1-8', 'sid' => 'x', 'date' => '2099-04-09', 'name' => 'Synthetic New', 'cls' => 'LKG', 'section' => 'A', 'adm' => 'FRY', 'father' => 'G',
        'lines' => [['1', 'Tution Fee', 'May', 60000]], 'total' => 60000, 'late' => 0, 'discount' => 0, 'received' => 60000, 'mode' => 'UPI', 'bank' => 'NA', 'cheque' => 'NA', 'chequeDate' => '2099-04-09', 'depositBank' => 'UBI-…8120', 'status' => 'Cleared', 'by' => 'Accountant', 'followupRemark' => 'call back'], 'principal');
    [$c] = frq($R); frc($c === 403, 'reception cannot open fee reports');
    [$c, $d] = frq($A); frc($c === 200, 'accountant opens fee reports');
    $mine = array_values(array_filter($d['receipts'], fn($r) => $r['session'] === '2099-00'));
    frc(count($mine) === 2 && $mine[0]['no'] === '1-7' && $mine[1]['no'] === '1-8', 'old and new receipts in one list, by date ' . json_encode($mine));
    [$o, $n] = $mine;
    frc($o['total'] == 5100 && $o['discount'] == 4500 && $o['received'] == 600 && $o['mode'] === 'NEFT' && $o['lines'][1]['amt'] == 4500, 'old receipt in rupees, mode cleaned, printed lines ' . json_encode($o));
    frc($o['followRemark'] === 'Synthetic A/C' && $o['bank'] === 'HDFC BANK 3039' && $o['custBank'] === 'NA', 'follow-up remark and banks from the old Fee Collection Report 1');
    frc($n['received'] == 600 && $n['bank'] === 'UBI-…8120' && $n['followRemark'] === 'call back' && $n['lines'][0]['amt'] == 600 && $n['months'] === 'May', 'new receipt ' . json_encode($n));
    echo "Fee Reports: old ERP and new receipts in one list (rupees, printed head lines, banks and follow-up remark) and roles passed\n";
} finally {
    $clean();
    foreach (['frx:acc', 'frx:rec'] as $x) { $db->prepare('DELETE FROM sch_sessions WHERE uid=?')->execute([$x]); $db->prepare('DELETE FROM sch_users WHERE uid=?')->execute([$x]); }
}
