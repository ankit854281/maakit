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
$clean = fn() => $db->exec("DELETE FROM sch_records WHERE (k='regRow' AND (r LIKE 'oldreceipt|frx%' OR r LIKE 'oldrcptdet|frx%')) OR (k='ofcRcpt' AND r LIKE '2099-00|%') OR (k='regPage' AND r IN ('2099-00|report_fee.php','2099-00|report_cashbook.php')) OR (k='regRow' AND r LIKE 'oldfeecard|frx%') OR (k='student' AND r='frx-s')");
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
    // Dues: the copied old ERP fee card before Fee Receipt is started; heads, old due and the child's receipts
    sch_rec_put('student', 'frx-s', ['id' => 'frx-s', 'name' => 'Synthetic Due', 'cls' => 'LKG', 'section' => 'A', 'adm' => 'FRZ', 'father' => 'F', 'phone' => '9811100000'], 'principal');
    sch_rec_put('regRow', 'oldfeecard|frx1', ['session' => '2099-00', 'oldId' => '9', 'liveId' => 'frx-s', 'name' => 'Synthetic Due', 'cls' => 'LKG', 'section' => 'A', 'scholar' => 'FRZ / 1', 'oldFee' => '500', 'transferFee' => '0', 'asOn' => '22-10-2099',
        'months' => [['', 'D/A', 'Fee Name', 'Amount', 'Paid/Disc Amount', 'Balance', 'N Paid', 'Status'], ['April'], ['', '', 'Tution Fee', '1000', '1000', '0', '', 'Paid'], ['', '', 'Transport', '300', '0', '300', '', 'Not Paid'], ['May'], ['', '', 'Tution Fee', '1000', '0', '1000', '', 'Not Paid']]], 'principal');
    sch_rec_put('regRow', 'oldreceipt|frx2', ['session' => '2099-00', 'receiptNo' => '1-9', 'date' => '2099-04-10', 'student' => 'frx-s', 'scholarNo' => 'FRZ', 'name' => 'Synthetic Due', 'cls' => 'LKG', 'section' => 'A', 'months' => 'April', 'total' => 100000, 'discount' => 0, 'lateFee' => 0, 'received' => 100000, 'mode' => 'Cash', 'status' => 'Cleared', 'user' => 'Swati'], 'principal');
    $c = curl_init('http://127.0.0.1:8099/school/feereports_api.php?a=dues&ses=2099-00'); curl_setopt_array($c, [CURLOPT_RETURNTRANSFER => true, CURLOPT_PROXY => '', CURLOPT_HTTPHEADER => ['X-School-Token: ' . $A]]); $d = json_decode((string)curl_exec($c), true); curl_close($c);
    $row = array_values(array_filter($d['rows'] ?? [], fn($r) => $r['sid'] === 'frx-s'))[0] ?? null;
    frc($d['ok'] && !$d['live'] && $d['asOn'] === '22-10-2099' && $row && $row['oldDue'] == 500 && count($row['heads']) === 3 && $row['received'] == 1000 && $row['count'] === 1 && $row['lastDate'] === '2099-04-10', 'dues from the copied card ' . json_encode($row));
    frc(array_sum(array_map(fn($h) => $h['amt'] - $h['paid'], $row['heads'])) == 1300, 'balance of heads equals the card');
    // Accounts: fee receipts into cash, old vouchers from the copied old Cashbook (fee lines skipped), new Cash Book entries
    $cbp = [['Sno', 'Date', 'Session', 'Vch No/Rec No', 'Name', 'Scholar No', 'Class', 'Remark', 'Particular', 'Debit(Receipts)', 'Credit(Payments)', '', '', ''], ['Cash Dr', 'Bank Dr', 'Cash Cr', 'Bank Cr', 'PMode', 'UName', 'Print'],
        ['1', '08-04-209908:00:00 AM', '2099-2000', '1-7', 'Synthetic Fee', 'FRX', 'LKG', '', 'Fee', '600', '0', '0', '0', 'Cash', 'Swati', 'Print'],
        ['2', '09-04-2099', '', 'JV-1-0499-1', 'Synthetic Vendor', '', '', 'g pay', 'Diesel', '0', '0', '0', '700', 'UPI 7985293903', 'Swati', ''],
        ['3', '09-04-2099', '', 'BD-0499-1', 'UBI xxxx8120', '', '', '', 'BD', '0', '400', '400', '0', 'Online NA', 'Swati', '']];
    sch_rec_put('regPage', '2099-00|report_cashbook.php', ['session' => '2099-00', 'page' => 'report_cashbook.php', 'title' => 'Cashbook', 'group' => '11 Accounts', 'h' => base64_encode(gzencode(json_encode($cbp)))], 'principal');
    $c = curl_init('http://127.0.0.1:8099/school/feereports_api.php?a=accounts'); curl_setopt_array($c, [CURLOPT_RETURNTRANSFER => true, CURLOPT_PROXY => '', CURLOPT_HTTPHEADER => ['X-School-Token: ' . $A]]); $d = json_decode((string)curl_exec($c), true); curl_close($c);
    $mine = array_values(array_filter($d['rows'] ?? [], fn($r) => str_starts_with($r['date'], '2099-04')));
    $by = array_column($mine, null, 'vno');
    frc(count(array_filter($mine, fn($r) => $r['vno'] === '1-7')) === 1 && $by['1-7']['drCash'] == 600 && $by['JV-1-0499-1']['crBank'] == 700 && $by['JV-1-0499-1']['kind'] === 'JV' && $by['BD-0499-1']['drBank'] == 400 && $by['BD-0499-1']['crCash'] == 400, 'cash book entries, fee counted once ' . json_encode($mine));
    echo "Fee Reports: old ERP and new receipts in one list (rupees, printed head lines, banks and follow-up remark), dues from the fee card and roles passed\n";
} finally {
    $clean();
    foreach (['frx:acc', 'frx:rec'] as $x) { $db->prepare('DELETE FROM sch_sessions WHERE uid=?')->execute([$x]); $db->prepare('DELETE FROM sch_users WHERE uid=?')->execute([$x]); }
}
