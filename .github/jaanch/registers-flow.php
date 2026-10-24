<?php
// School Office registers (school/registers_api.php) — CI database only, synthetic rows only.
require __DIR__ . '/../../config.php';
if (DB_NAME !== 'maakit_jaanch') throw new RuntimeException('CI database only');
require __DIR__ . '/../../school/inc.php';
function rc($v, $m) { if (!$v) throw new RuntimeException('Registers flow: ' . $m); }
function rtoken($uid, $role, $tid = null) {
    $db = sch_db();
    $db->prepare('INSERT INTO sch_users(uid,role,tid,must_change) VALUES(?,?,?,0) ON DUPLICATE KEY UPDATE role=VALUES(role),tid=VALUES(tid),must_change=0')->execute([$uid, $role, $tid]);
    $t = bin2hex(random_bytes(32));
    $db->prepare('INSERT INTO sch_sessions(token_hash,uid,expires_at) VALUES(?,?,DATE_ADD(NOW(),INTERVAL 1 DAY))')->execute([hash('sha256', $t), $uid]);
    return $t;
}
function rq($a, $token, $body = null, $api = 'registers_api.php') {
    $h = ['X-School-Token: ' . $token];
    $o = [CURLOPT_RETURNTRANSFER => true, CURLOPT_PROXY => '', CURLOPT_TIMEOUT => 20];
    if ($body !== null) { $o[CURLOPT_POST] = true; $h[] = 'Content-Type: application/json'; $o[CURLOPT_POSTFIELDS] = json_encode($body); }
    $o[CURLOPT_HTTPHEADER] = $h;
    $c = curl_init('http://127.0.0.1:8099/school/' . $api . '?a=' . $a); curl_setopt_array($c, $o);
    $raw = curl_exec($c); $code = curl_getinfo($c, CURLINFO_HTTP_CODE); curl_close($c);
    rc(is_string($raw) && !preg_match('/Fatal error|Warning:|Uncaught|Deprecated:/', $raw), 'PHP response ' . $a . ': ' . substr((string)$raw, 0, 250));
    return [$code, json_decode((string)$raw, true), (string)$raw];
}
$db = sch_db();
$oldSettings = sch_rec_get('settings', 'main');
$P = rtoken('principal', 'principal', 'P'); $A = rtoken('accountant', 'accountant'); $R = rtoken('reception', 'reception');
$C = rtoken('chairman', 'chairman'); $T = rtoken('t:REGT', 'teacher', 'REGT'); $F = rtoken('p:9811122233', 'parent', '9811122233');
try {
    $db->exec("DELETE FROM sch_records WHERE k IN ('regRow','regSeq','regHist')");
    sch_rec_put('settings', 'main', ['session' => '2026-27', 'sessionStart' => '2026-04-01', 'school' => 'Synthetic Registers School', 'place' => 'Test'], 'principal');
    sch_rec_put('student', 'reg-s1', ['id' => 'reg-s1', 'adm' => 'RG1', 'name' => 'Synthetic Register One', 'cls' => '1st', 'section' => 'A', 'phone' => '9811122233', 'father' => 'Synthetic Father'], 'principal');
    sch_rec_put('student', 'reg-s2', ['id' => 'reg-s2', 'adm' => 'RG2', 'name' => 'Synthetic Register Two', 'cls' => '1st', 'section' => 'A', 'phone' => '9822233344', 'father' => 'Synthetic Father 2'], 'principal');
    sch_rec_put('teacher', 'REGT', ['name' => 'Synthetic Register Teacher'], 'principal');

    // Who may open what
    [$c] = rq('meta', ''); rc($c === 401, 'no token');
    [$c] = rq('meta', $F); rc($c === 403, 'parent must not open School Office');
    [$c, $d] = rq('meta', $T); rc($c === 200, 'teacher meta');
    $tm = array_column($d['modules'], 'id'); rc(in_array('event', $tm, true) && !in_array('tc', $tm, true) && !in_array('cashbook', $tm, true) && !in_array('enquiry', $tm, true), 'teacher sees only teacher registers');
    [$c, $d] = rq('meta', $P); rc($c === 200 && count($d['modules']) >= 40, 'principal sees all registers');
    [$c] = rq('rows&m=cashbook', $R); rc($c === 403, 'reception cannot read accounts');
    [$c] = rq('save', $T, ['m' => 'enquiry', 'row' => ['name' => 'X']]); rc($c === 403, 'teacher cannot write enquiry');

    // Auto numbers, validation, money
    $enq = ['date' => '2026-10-20', 'name' => 'Synthetic Enquiry', 'father' => 'Synthetic', 'phone' => '+91 98111 22233', 'cls' => '1st', 'status' => 'Interested', 'nextDate' => '2026-10-21'];
    [$c, $d] = rq('save', $R, ['m' => 'enquiry', 'row' => $enq]); rc($c === 200 && $d['row']['no'] === 'ENQ/2026-27/0001' && $d['row']['phone'] === '9811122233', 'first enquiry number + phone ' . json_encode($d));
    $e1 = $d['row']['_id'];
    [$c, $d] = rq('save', $R, ['m' => 'enquiry', 'row' => array_merge($enq, ['name' => 'Second'])]); rc($c === 200 && $d['row']['no'] === 'ENQ/2026-27/0002', 'second enquiry number');
    [$c, $d] = rq('save', $R, ['m' => 'enquiry', 'id' => $e1, 'row' => array_merge($enq, ['no' => 'HACK', 'status' => 'Admitted'])]); rc($c === 200 && $d["row"]["no"] === "ENQ/2026-27/0001" && $d["row"]["status"] === "Admitted", "number never changes on edit " . json_encode($d));
    [$c] = rq('save', $R, ['m' => 'enquiry', 'row' => ['cls' => 'Nursery'] + $enq]); rc($c === 400, 'class must be from the list');
    [$c] = rq('save', $R, ['m' => 'enquiry', 'row' => ['name' => ''] + $enq]); rc($c === 400, 'required name');
    [$c] = rq('save', $A, ['m' => 'tc', 'row' => ['date' => '2026-10-20', 'student' => 'reg-s1', 'leaveDate' => '2026-10-20', 'reason' => 'Shift']]); rc($c === 403, 'accountant cannot issue TC');
    [$c] = rq('save', $P, ['m' => 'tc', 'row' => ['date' => '2026-10-20', 'student' => 'nobody', 'leaveDate' => '2026-10-20', 'reason' => 'Shift']]); rc($c === 400, 'TC for unknown student');
    [$c, $d] = rq('save', $P, ['m' => 'tc', 'row' => ['date' => '2026-10-20', 'student' => 'reg-s1', 'leaveDate' => '2026-10-20', 'reason' => 'Family shifted']]); rc($c === 200 && $d['row']['no'] === 'TC/2026-27/0001', 'TC number');
    [$c, $d] = rq('save', $A, ['m' => 'cashbook', 'row' => ['date' => '2026-10-01', 'side' => 'Receipt (money in)', 'head' => 'Fee', 'amount' => '1,500.50', 'mode' => 'Cash']]); rc($c === 200 && $d['row']['amount'] === 150050, 'money to paise');
    rq('save', $A, ['m' => 'cashbook', 'row' => ['date' => '2026-10-02', 'side' => 'Payment (money out)', 'head' => 'Diesel', 'amount' => '500', 'mode' => 'Cash']]);
    [$c, $d] = rq('report&k=cashbook', $C); rc($c === 200 && end($d['report']['rows'])['balance'] === 100050, 'cash book balance');
    [$c] = rq('save', $A, ['m' => 'cashbook', 'row' => ['date' => '2026-10-02', 'side' => 'Payment (money out)', 'head' => 'X', 'amount' => '12abc']]); rc($c === 400, 'bad money refused');

    // Stock = purchased − issued − sold (returned sales not counted)
    rq('save', $A, ['m' => 'item', 'row' => ['name' => 'Synthetic Diary', 'category' => 'Diary', 'unit' => 'Piece', 'minStock' => '6']]);
    rq('save', $A, ['m' => 'purchase', 'row' => ['date' => '2026-10-01', 'supplier' => 'S', 'item' => 'Synthetic Diary', 'qty' => '10', 'amount' => '1000']]);
    rq('save', $A, ['m' => 'issue', 'row' => ['date' => '2026-10-02', 'item' => 'Synthetic Diary', 'qty' => '3', 'to' => 'Office']]);
    rq('save', $A, ['m' => 'sale', 'row' => ['date' => '2026-10-03', 'student' => 'reg-s1', 'item' => 'Synthetic Diary', 'qty' => '2', 'amount' => '200', 'mode' => 'Cash']]);
    rq('save', $A, ['m' => 'sale', 'row' => ['date' => '2026-10-03', 'student' => 'reg-s2', 'item' => 'Synthetic Diary', 'qty' => '4', 'amount' => '400', 'mode' => 'Cash', 'returned' => 'Yes']]);
    [$c, $d] = rq('report&k=stock', $A); $st = array_column($d['report']['rows'], null, 'item')['Synthetic Diary'];
    rc($st['balance'] == 5 && $st['low'] === true, 'stock balance and re-order flag ' . json_encode($st));

    // Old ERP import: original receipt numbers kept, preview first, never overwritten
    $rows = [['session' => '2024-25', 'receiptNo' => '1-708', 'date' => '2024-04-04', 'student' => 'reg-s1', 'name' => 'Synthetic Register One', 'total' => '13980', 'discount' => '3230', 'received' => '10750', 'mode' => 'Cash', 'months' => 'April–March'],
             ['session' => '2024-25', 'receiptNo' => '1-7', 'date' => '2024-04-12', 'student' => 'reg-s2', 'name' => 'Synthetic Register Two', 'received' => '7000', 'mode' => 'Cash']];
    [$c] = rq('import', $A, ['m' => 'oldreceipt', 'rows' => $rows]); rc($c === 403, 'only Principal imports');
    [$c] = rq('save', $P, ['m' => 'oldreceipt', 'row' => $rows[0]]); rc($c === 403, 'archive is read-only');
    [$c, $d] = rq('import', $P, ['m' => 'oldreceipt', 'rows' => $rows, 'apply' => false]); rc($c === 200 && $d['toAdd'] === 2 && !$d['applied'], 'import preview');
    [$c, $d] = rq('rows&m=oldreceipt', $P); rc(!$d['rows'], 'preview wrote nothing');
    [$c, $d] = rq('import', $P, ['m' => 'oldreceipt', 'rows' => $rows, 'apply' => true]); rc($c === 200 && $d['added'] === 2, 'import apply');
    [$c, $d] = rq('rows&m=oldreceipt', $A); $nos = array_column($d['rows'], 'received', 'receiptNo'); ksort($nos);
    rc($nos === ['1-7' => 700000, '1-708' => 1075000], 'original receipt numbers and amounts ' . json_encode($nos));
    [$c, $d] = rq('import', $P, ['m' => 'oldreceipt', 'rows' => $rows, 'apply' => true]); rc($c === 200 && $d['added'] === 0 && $d['unchanged'] === 2, 'second import adds nothing');
    $changed = $rows; $changed[0]['received'] = '1';
    [$c] = rq('import', $P, ['m' => 'oldreceipt', 'rows' => $changed, 'apply' => true]); rc($c === 409, 'changed row blocks import');
    [$c, $d] = rq('import', $P, ['m' => 'oldreceipt', 'rows' => [$rows[0], $rows[0]]]); rc($c === 400, 'duplicate in file refused');
    // Full receipt (every fee head, as printed by the old ERP) kept exactly, keyed by session + receipt number
    $det = [['session' => '2024-25', 'receiptNo' => '1-708', 'name' => 'Synthetic Register One', 'lines' => [['1', 'Tuition Fee', 'Apr', '1000'], ['2', 'Exam Fee', 'Apr', '500']], 'payable' => '1500', 'received' => '1500', 'mode' => 'Cash']];
    [$c] = rq('save', $P, ['m' => 'oldrcptdet', 'row' => $det[0]]); rc($c === 403, 'receipt details are read-only');
    [$c, $d] = rq('import', $P, ['m' => 'oldrcptdet', 'rows' => $det, 'apply' => true]); rc($c === 200 && $d['added'] === 1, 'receipt details import');
    [$c, $d] = rq('rows&m=oldrcptdet', $A); rc(($d['rows'][0]['lines'][1][1] ?? '') === 'Exam Fee' && $d['rows'][0]['payable'] === '1500', 'receipt lines kept as printed ' . json_encode($d['rows'][0] ?? null));
    [$c] = rq('rows&m=oldrcptdet', $R); rc($c === 403, 'reception cannot read old receipts');
    // Old ERP fee card (Fee › Fee Receipt): kept for every old student, even one not in the new ERP; keyed by session + old id
    $card = [['session' => '2026-27', 'oldId' => '502', 'liveId' => '', 'name' => 'Synthetic Left Child', 'cls' => '1st', 'section' => 'A',
              'months' => [['', 'D/A', 'Fee Name', 'Amount', 'Paid/Disc Amount', 'Balance', 'N Paid', 'Status'], ['April'], ['', '', 'Tution Fee', '790', '790', '0', '', 'Paid']],
              'receipts' => [['Sno', 'Status', 'R.No.', 'Date', 'Net Amt'], ['1', 'Cleared', '1-34', '14-07-2026', '6080']], 'net' => '13980', 'received' => '7660', 'feeDue' => '6320']];
    [$c] = rq('save', $A, ['m' => 'oldfeecard', 'row' => $card[0]]); rc($c === 403, 'fee card is read-only');
    [$c, $d] = rq('import', $P, ['m' => 'oldfeecard', 'rows' => $card, 'apply' => true]); rc($c === 200 && $d['added'] === 1, 'fee card import');
    [$c, $d] = rq('rows&m=oldfeecard', $A); $fc = $d['rows'][0] ?? [];
    rc(($fc['months'][2][2] ?? '') === 'Tution Fee' && ($fc['receipts'][1][2] ?? '') === '1-34' && $fc['feeDue'] === '6320', 'fee card kept as in the old ERP ' . json_encode($fc));

    // Inactive children: linkable from old records, never in class lists; a fix file moves old records to the right child, safely twice
    $bundle = ['steps' => [
        ['kind' => 'import', 'm' => 'inactivestu', 'rows' => [['oldId' => '9145', 'name' => 'Synthetic Inactive', 'cls' => '1st', 'section' => 'A', 'adm' => 'RG9', 'status' => 'Inactive', 'reason' => 'Synthetic']]],
        ['kind' => 'relink', 'rows' => [['m' => 'oldreceipt', 'key' => ['session' => '2024-25', 'receiptNo' => '1-7'], 'to' => 'old-9145', 'reason' => 'Synthetic wrong match']]],
        ['kind' => 'import', 'm' => 'oldfee', 'rows' => [['session' => '2024-25', 'student' => 'old-9145', 'name' => 'Synthetic Inactive', 'due' => '10']]]]];
    [$c] = rq('bundle', $A, $bundle); rc($c === 403, 'only Principal runs a fix file');
    [$c, $d] = rq('bundle', $P, $bundle); rc($c === 200 && !$d['applied'] && $d['steps'][1]['new'] === 1, 'fix file preview ' . json_encode($d));
    [$c, $d] = rq('rows&m=inactivestu', $P); rc(!$d['rows'], 'preview changed nothing');
    [$c, $d] = rq('bundle', $P, $bundle + ['apply' => true]); rc($c === 200 && $d['applied'] && $d['steps'][2]['new'] === 1, 'fix file apply');
    [$c, $d] = rq('bundle', $P, $bundle + ['apply' => true]); rc($c === 200 && $d['steps'][0]['already'] === 1 && $d['steps'][1]['already'] === 1 && $d['steps'][2]['already'] === 1, 'fix file twice changes nothing ' . json_encode($d));
    [$c, $d] = rq('rows&m=oldreceipt', $A); rc(array_column($d['rows'], 'student', 'receiptNo')['1-7'] === 'old-9145', 'receipt moved to the inactive child');
    [$c, $d] = rq('meta', $P); $ina = array_column($d['students'], null, 'id')['old-9145'] ?? null; rc($ina && !empty($ina['inactive']), 'inactive child can be chosen in School Office');
    [$c, , $raw] = rq('load', $P, null, 'api.php'); $recs = json_encode((json_decode($raw, true) ?? [])['records'] ?? null); rc(!str_contains($recs, 'Synthetic Inactive'), 'inactive children are not in class lists or sync');
    // Old dues carried to Fee Desk only where none is set
    sch_rec_put('acctStu', 'reg-s2', ['opening' => 50], 'principal');
    rq('import', $P, ['m' => 'oldfee', 'rows' => [['session' => '2024-25', 'student' => 'reg-s1', 'name' => 'One', 'due' => '1234.50'], ['session' => '2024-25', 'student' => 'reg-s2', 'name' => 'Two', 'due' => '99']], 'apply' => true]);
    [$c] = rq('carry', $A, ['session' => '2024-25']); rc($c === 403, 'only Principal carries dues');
    [$c, $d] = rq('carry', $P, ['session' => '2024-25', 'apply' => false]); rc($c === 200 && $d['students'] === 1 && $d['total'] === 123450 && count($d['skipped']) === 2 && str_contains(implode(',', $d['skipped']), 'inactive'), 'carry preview (inactive child skipped)');
    rc((sch_rec_get('acctStu', 'reg-s1')['opening'] ?? null) === null, 'preview did not write');
    rq('carry', $P, ['session' => '2024-25', 'apply' => true]);
    rc(abs((float)sch_rec_get('acctStu', 'reg-s1')['opening'] - 1234.5) < 0.001 && (float)sch_rec_get('acctStu', 'reg-s2')['opening'] === 50.0, 'carry wrote only the empty one');

    // Delete needs a reason; office registers never reach legacy sync
    [$c] = rq('del', $R, ['m' => 'enquiry', 'id' => $e1, 'reason' => '']); rc($c === 400, 'reason required');
    [$c] = rq('del', $R, ['m' => 'enquiry', 'id' => $e1, 'reason' => 'Duplicate entry']); rc($c === 200, 'delete with reason');
    [$c, , $raw] = rq('load', $P, null, 'api.php'); rc($c === 200 && !str_contains($raw, 'regRow') && !str_contains($raw, 'Family shifted') && !str_contains($raw, '1-708'), 'legacy sync hides registers');
    echo "Registers: role scoping, auto numbers, validation, paise, stock, cash book, read-only old-ERP import with original receipt numbers, old-due carry and sync privacy passed\n";
} finally {
    $db->exec("DELETE FROM sch_records WHERE k IN ('regRow','regSeq','regHist') OR (k IN ('student','acctStu') AND r IN ('reg-s1','reg-s2')) OR (k='teacher' AND r='REGT')");
    if ($oldSettings !== null) sch_rec_put('settings', 'main', $oldSettings, 'principal'); else $db->exec("DELETE FROM sch_records WHERE k='settings' AND r='main'");
    foreach (['t:REGT', 'p:9811122233'] as $uid) { $db->prepare('DELETE FROM sch_sessions WHERE uid=?')->execute([$uid]); $db->prepare('DELETE FROM sch_users WHERE uid=?')->execute([$uid]); }
}
