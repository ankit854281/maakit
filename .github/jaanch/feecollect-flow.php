<?php
// Fee Receipt (school/feecollect_api.php) — CI database only, synthetic rows only.
// Checks the old-ERP maths and rules: numbering continues from the old ERP, Currently Received = Total + Late − Discount,
// Total may not pass the balance, old due first, deposit bank for non-cash, cancel puts heads back, one place to take fees.
require __DIR__ . '/../../config.php';
if (DB_NAME !== 'maakit_jaanch') throw new RuntimeException('CI database only');
require __DIR__ . '/../../school/inc.php';
require __DIR__ . '/../../school/feecollect_lib.php';
function fc($v, $m) { if (!$v) throw new RuntimeException('Fee Receipt flow: ' . $m); }
function fctok($uid, $role) {
    $db = sch_db();
    $db->prepare('INSERT INTO sch_users(uid,role,tid,must_change) VALUES(?,?,NULL,0) ON DUPLICATE KEY UPDATE role=VALUES(role),must_change=0')->execute([$uid, $role]);
    $t = bin2hex(random_bytes(32));
    $db->prepare('INSERT INTO sch_sessions(token_hash,uid,expires_at) VALUES(?,?,DATE_ADD(NOW(),INTERVAL 1 DAY))')->execute([hash('sha256', $t), $uid]);
    return $t;
}
function freq($a, $token, $body = null, $q = '', $api = 'feecollect_api.php') {
    $h = ['X-School-Token: ' . $token]; $o = [CURLOPT_RETURNTRANSFER => true, CURLOPT_PROXY => '', CURLOPT_TIMEOUT => 20];
    if ($body !== null) { $o[CURLOPT_POST] = true; $h[] = 'Content-Type: application/json'; $o[CURLOPT_POSTFIELDS] = json_encode($body); }
    $o[CURLOPT_HTTPHEADER] = $h;
    $c = curl_init('http://127.0.0.1:8099/school/' . $api . '?a=' . $a . $q); curl_setopt_array($c, $o);
    $raw = curl_exec($c); $code = curl_getinfo($c, CURLINFO_HTTP_CODE); curl_close($c);
    fc(is_string($raw) && !preg_match('/Fatal error|Warning:|Uncaught|Deprecated:|Notice:/', $raw), 'PHP response ' . $a . ': ' . substr((string)$raw, 0, 300));
    return [$code, json_decode((string)$raw, true), (string)$raw];
}
$db = sch_db();
$oldSettings = sch_rec_get('settings', 'main');
$P = fctok('principal', 'principal'); $A = fctok('accountant', 'accountant'); $R = fctok('reception', 'reception');
$n = fn() => bin2hex(random_bytes(16));
$clean = function () use ($db) { $db->exec("DELETE FROM sch_records WHERE k LIKE 'ofc%' OR (k='regRow' AND r LIKE 'oldfeecard|%') OR (k='student' AND r IN ('fc-s1','fc-s2','fc-s3'))"); };
try {
    $clean();
    sch_rec_put('settings', 'main', ['session' => '2026-27', 'sessionStart' => '2026-04-01', 'school' => 'Synthetic Fee School', 'place' => 'Test'], 'principal');
    foreach ([['fc-s1', 'Synthetic Fee One'], ['fc-s2', 'Synthetic Fee Two'], ['fc-s3', 'Synthetic New Admission']] as [$id, $name])
        sch_rec_put('student', $id, ['id' => $id, 'adm' => strtoupper($id), 'name' => $name, 'cls' => '1st', 'section' => 'A', 'father' => 'Synthetic Father', 'phone' => '9811100000'], 'principal');
    $months = [['', 'D/A', 'Fee Name', 'Amount', 'Paid/Disc Amount', 'Balance', 'N Paid', 'Status'],
        ['April'], ['', '', 'Admission Fee', '1200', '1200', '0', '', 'Paid'], ['', '', 'Tution Fee', '790', '790', '0', '', 'Paid'],
        ['May'], ['', '', 'Tution Fee', '790', '0', '790', '', 'Not Paid'], ['', '', 'Transport', '600', '0', '600', '', 'Not Paid'],
        ['June'], ['', '', 'Tution Fee', '790', '0', '790', '', 'Not Paid'], ['', '', 'Transport', '600', '0', '600', '', 'Not Paid'],
        ['July'], ['', '', 'Tution Fee', '790', '0', '790', '', 'Not Paid']];
    $card = fn($sid, $old, $oldFee) => ['session' => '2026-27', 'oldId' => $old, 'liveId' => $sid, 'name' => 'Synthetic', 'cls' => '1st', 'section' => 'A', 'scholar' => '24504 /', 'scheme' => 'Regular-01-1',
        'months' => $months, 'oldFee' => $oldFee, 'transferFee' => '0', 'nextReceipt' => '1-382', 'asOn' => '24-10-2026'];
    sch_rec_put('regRow', 'oldfeecard|c1', $card('fc-s1', '901', '0'), 'principal');
    sch_rec_put('regRow', 'oldfeecard|c2', $card('fc-s2', '902', '2100'), 'principal');

    // Not started: nobody takes fees here yet; only the Principal starts
    [$c] = freq('collect', $A, ['ses' => '2026-27', 'sid' => 'fc-s1', 'alloc' => [['i' => 3, 'amt' => '790']], 'nonce' => $n()]); fc($c === 400, 'no collection before start');
    [$c] = freq('start', $A, ['ses' => '2026-27']); fc($c === 403, 'only Principal starts');
    [$c, $d] = freq('start', $P, ['ses' => '2026-27']); fc($c === 200 && $d['create'] === 2 && $d['firstNo'] === '1-382', 'start preview ' . json_encode($d));
    [$c] = freq('start', $P, ['ses' => '2026-27', 'apply' => true, 'confirm' => 'yes', 'from' => '2026-10-24']); fc($c === 400, 'START must be typed');
    [$c, $d] = freq('start', $P, ['ses' => '2026-27', 'apply' => true, 'confirm' => 'START', 'from' => '2026-10-01']); fc($c === 200 && !empty($d['applied']), 'start apply');
    [$c, $d] = freq('state', $A, null, '&ses=2026-27'); fc($c === 200 && $d['next'] === '1-382' && $d['live']['from'] === '2026-10-01', 'numbering continues from the old ERP ' . json_encode($d['next']));
    [$c, $d] = freq('ledger', $A, null, '&ses=2026-27&sid=fc-s1'); fc($d['totals']['net'] === 556000 && $d['totals']['balance'] === 357000, 'heads copied with what is already paid ' . json_encode($d['totals']));

    // Upto Month = June: May + June heads (790+600+790+600 = 2780), Late 50, Discount 30 -> Received 2800
    $alloc = [['i' => 3, 'amt' => '790'], ['i' => 4, 'amt' => '600'], ['i' => 5, 'amt' => '790'], ['i' => 6, 'amt' => '600']];
    [$c] = freq('collect', $R, ['ses' => '2026-27', 'sid' => 'fc-s1', 'alloc' => $alloc, 'nonce' => $n()]); fc($c === 403, 'reception cannot take fees');
    [$c, $d] = freq('collect', $A, ['ses' => '2026-27', 'sid' => 'fc-s1', 'alloc' => $alloc, 'late' => '50', 'discount' => '30', 'mode' => 'UPI', 'nonce' => $n()]); fc($c === 400 && str_contains($d['error'], 'Deposit Bank'), 'non-cash needs the deposit bank');
    $nonce = $n();
    [$c, $d] = freq('collect', $A, ['ses' => '2026-27', 'sid' => 'fc-s1', 'alloc' => $alloc, 'late' => '50', 'discount' => '30', 'mode' => 'Cash', 'remark' => 'Synthetic', 'nonce' => $nonce]);
    fc($c === 200 && $d['receipt']['no'] === '1-382' && $d['receipt']['total'] === 278000 && $d['receipt']['received'] === 280000 && $d['receipt']['balanceAfter'] === 79000, 'collect ' . json_encode($d));
    fc($d['receipt']['lines'] === [['1', 'Tution Fee', 'May,June', 158000], ['2', 'Transport', 'May,June', 120000]], 'receipt lines grouped like the old print ' . json_encode($d['receipt']['lines']));
    [$c, $d] = freq('collect', $A, ['ses' => '2026-27', 'sid' => 'fc-s1', 'alloc' => $alloc, 'late' => '50', 'discount' => '30', 'mode' => 'Cash', 'nonce' => $nonce]); fc($c === 200 && !empty($d['repeat']) && $d['receipt']['no'] === '1-382', 'same request twice makes one receipt');
    [$c, $d] = freq('collect', $A, ['ses' => '2026-27', 'sid' => 'fc-s1', 'alloc' => [['i' => 3, 'amt' => '790']], 'nonce' => $n()]); fc($c === 400, 'a paid head cannot be paid again');
    [$c, $d] = freq('collect', $A, ['ses' => '2026-27', 'sid' => 'fc-s1', 'alloc' => [['i' => 7, 'amt' => '900']], 'nonce' => $n()]); fc($c === 400, 'more than the head balance refused');
    [$c, $d] = freq('collect', $A, ['ses' => '2026-27', 'sid' => 'fc-s1', 'alloc' => [['i' => 7, 'amt' => '400']], 'mode' => 'Cheque', 'cheque' => '000123', 'depositBank' => 'HDFC BANK-3039', 'depositDate' => '2026-10-24', 'nonce' => $n()]);
    fc($c === 200 && $d['receipt']['no'] === '1-383' && $d['receipt']['received'] === 40000 && $d['receipt']['balanceAfter'] === 39000, 'part payment of a head, next number ' . json_encode($d));
    [$c, $d] = freq('collect', $A, ['ses' => '2026-27', 'sid' => 'fc-s1', 'alloc' => [['i' => 7, 'amt' => '390']], 'date' => '2026-09-30', 'nonce' => $n()]); fc($c === 400, 'no date before the start day');

    // Old due first (old ERP: "Firstly Paid Old Due Amount")
    [$c, $d] = freq('collect', $A, ['ses' => '2026-27', 'sid' => 'fc-s2', 'alloc' => [['i' => 3, 'amt' => '790']], 'nonce' => $n()]); fc($c === 400 && str_contains($d['error'], 'Old Due'), 'old due blocks ' . json_encode($d));

    // Old due goes on the card as the head "Old Due" (old ERP Add Other Fee, Fee Type Old Due), then is collected like any head
    [$c] = freq('addFee', $R, ['ses' => '2026-27', 'sids' => ['fc-s2'], 'type' => 'Old Due', 'm' => 4, 'nonce' => $n()]); fc($c === 403, 'reception cannot add fees');
    [$c, $d] = freq('addFee', $A, ['ses' => '2026-27', 'sids' => ['fc-s2'], 'type' => 'Old Due', 'm' => 4, 'amt' => '2200', 'nonce' => $n()]); fc($c === 400, 'more than the old due refused');
    [$c, $d] = freq('addFee', $A, ['ses' => '2026-27', 'sids' => ['fc-s2'], 'type' => 'Old Due', 'm' => 4, 'amt' => '', 'nonce' => $n()]); fc($c === 200 && $d['added'] === 1, 'old due added ' . json_encode($d));
    [$c, $d] = freq('ledger', $A, null, '&ses=2026-27&sid=fc-s2'); $od = end($d['ledger']['heads']);
    fc($d['oldDue'] === 0 && $od['name'] === 'Old Due' && $od['amt'] === 210000 && $od['m'] === 4 && $d['totals']['balance'] === 357000 + 210000 && count($d['adj']) === 1, 'old due is a head now ' . json_encode([$d['oldDue'], $od, $d['totals']]));
    [$c] = freq('addFee', $A, ['ses' => '2026-27', 'sids' => ['fc-s2'], 'type' => 'Old Due', 'm' => 4, 'nonce' => $n()]); fc($c === 400, 'old due cannot be added twice');
    [$c] = freq('dropFee', $A, ['ses' => '2026-27', 'sid' => 'fc-s2', 'i' => $od['i'], 'reason' => 'test']); fc($c === 403, 'only Principal removes');
    [$c] = freq('dropFee', $P, ['ses' => '2026-27', 'sid' => 'fc-s2', 'i' => 3, 'reason' => 'test']); fc($c === 400, 'a head from the old card cannot be removed');
    [$c, $d] = freq('dropFee', $P, ['ses' => '2026-27', 'sid' => 'fc-s2', 'i' => $od['i'], 'reason' => 'wrong month']); fc($c === 200, 'Principal removes an unpaid added head');
    [$c, $d] = freq('ledger', $A, null, '&ses=2026-27&sid=fc-s2'); fc($d['oldDue'] === 210000 && $d['totals']['balance'] === 357000, 'old due back after removal');
    [$c, $d] = freq('addFee', $A, ['ses' => '2026-27', 'sids' => ['fc-s2'], 'type' => 'Old Due', 'm' => 4, 'amt' => '', 'nonce' => $n()]); fc($c === 200, 'old due added again');
    [$c, $d] = freq('ledger', $A, null, '&ses=2026-27&sid=fc-s2'); $odi = end($d['ledger']['heads'])['i'];
    [$c, $d] = freq('collect', $A, ['ses' => '2026-27', 'sid' => 'fc-s2', 'alloc' => [['i' => $odi, 'amt' => '2100'], ['i' => 3, 'amt' => '790']], 'mode' => 'Cash', 'nonce' => $n()]);
    fc($c === 200 && $d['receipt']['lines'] === [['1', 'Old Due', 'April', 210000], ['2', 'Tution Fee', 'May', 79000]], 'old due collected with a month ' . json_encode($d));
    [$c] = freq('dropFee', $P, ['ses' => '2026-27', 'sid' => 'fc-s2', 'i' => $odi, 'reason' => 'test']); fc($c === 400, 'a paid added head cannot be removed');
    // Bulk Add Other Fee (a class): children without a fee card are skipped and listed
    $bn = $n();
    [$c, $d] = freq('addFee', $A, ['ses' => '2026-27', 'sids' => ['fc-s1', 'fc-s2', 'fc-none'], 'type' => 'Book', 'm' => 6, 'amt' => '450', 'remark' => 'Class 1 set', 'nonce' => $bn]); fc($c === 200 && $d['added'] === 2 && count($d['skipped']) === 1, 'bulk add ' . json_encode($d));
    [$c, $d] = freq('addFee', $A, ['ses' => '2026-27', 'sids' => ['fc-s1', 'fc-s2', 'fc-none'], 'type' => 'Book', 'm' => 6, 'amt' => '450', 'remark' => 'Class 1 set', 'nonce' => $bn]); fc($c === 200 && !empty($d['repeat']), 'bulk add sent twice adds once');
    [$c] = freq('addFee', $A, ['ses' => '2026-27', 'sids' => ['fc-s1'], 'type' => 'Pizza', 'm' => 6, 'amt' => '1', 'nonce' => $n()]); fc($c === 400, 'only the old fee types');
    [$c, $d] = freq('ledger', $A, null, '&ses=2026-27&sid=fc-s1'); $bk = end($d['ledger']['heads']); fc($bk['name'] === 'Book - Class 1 set' && $bk['amt'] === 45000 && $bk['m'] === 6 && $d['totals']['balance'] === 39000 + 45000, 'book fee on the card ' . json_encode($bk));
    // Fee Discount (Head Wise): Principal only, kept in Paid/Disc like the old card, can be taken back
    [$c] = freq('discount', $A, ['ses' => '2026-27', 'sid' => 'fc-s1', 'i' => $bk['i'], 'amt' => '50', 'reason' => 'sibling']); fc($c === 403, 'only Principal gives discount');
    [$c] = freq('discount', $P, ['ses' => '2026-27', 'sid' => 'fc-s1', 'i' => $bk['i'], 'amt' => '500', 'reason' => 'sibling']); fc($c === 400, 'discount more than the head balance refused');
    [$c, $d] = freq('discount', $P, ['ses' => '2026-27', 'sid' => 'fc-s1', 'i' => $bk['i'], 'amt' => '50', 'reason' => 'sibling']); fc($c === 200, 'discount given');
    [$c, $d] = freq('ledger', $A, null, '&ses=2026-27&sid=fc-s1'); $bk2 = end($d['ledger']['heads']); fc($bk2['paid'] === 5000 && $bk2['disc'] === 5000 && $d['totals']['balance'] === 39000 + 40000, 'discount lowers the balance ' . json_encode($bk2));
    [$c] = freq('dropFee', $P, ['ses' => '2026-27', 'sid' => 'fc-s1', 'i' => $bk['i'], 'reason' => 'x y']); fc($c === 400, 'a discounted head cannot be removed');
    [$c, $d] = freq('undoDisc', $P, ['ses' => '2026-27', 'sid' => 'fc-s1', 'i' => $bk['i'], 'reason' => 'by mistake']); fc($c === 200 && end($d['ledger']['heads'])['paid'] === 0, 'discount taken back');
    [$c, $d] = freq('dropFee', $P, ['ses' => '2026-27', 'sid' => 'fc-s1', 'i' => $bk['i'], 'reason' => 'not needed']); fc($c === 200, 'added head removed');
    [$c, $d] = freq('ledger', $A, null, '&ses=2026-27&sid=fc-s1'); fc($d['totals']['balance'] === 39000 && count($d['adj']) === 4, 'history kept after removal ' . json_encode($d['adj']));

    // Cancel: Principal only, reason, heads go back to Not Paid
    [$c] = freq('cancel', $A, ['ses' => '2026-27', 'no' => '1-383', 'reason' => 'Wrong child', 'nonce' => $n()]); fc($c === 403, 'only Principal cancels');
    [$c, $d] = freq('cancel', $P, ['ses' => '2026-27', 'no' => '1-383', 'reason' => 'Wrong child', 'nonce' => $n()]); fc($c === 200 && $d['receipt']['status'] === 'Cancelled', 'cancel');
    [$c, $d] = freq('ledger', $A, null, '&ses=2026-27&sid=fc-s1'); fc($d['totals']['balance'] === 79000 && count($d['receipts']) === 2, 'cancel put the head back ' . json_encode($d['totals']));

    // New admission: no card -> Principal copies a classmate's heads with nothing paid
    [$c] = freq('newLedger', $A, ['ses' => '2026-27', 'sid' => 'fc-s3', 'from' => 'fc-s1']); fc($c === 403, 'only Principal copies heads');
    [$c, $d] = freq('newLedger', $P, ['ses' => '2026-27', 'sid' => 'fc-s3', 'from' => 'fc-s1']); fc($c === 200 && array_sum(array_column($d['ledger']['heads'], 'paid')) === 0, 'new admission heads, nothing paid');

    // One place to take fees: Fee Desk refuses a started session; Fee Receipt rows never reach legacy sync
    [$c, $d] = freq('pay', $A, ['sid' => 'fc-s1', 'amt' => '100', 'mode' => 'Cash'], '', 'office_api.php'); fc($c === 409, 'Fee Desk refuses a started session ' . json_encode($d));
    [$c, , $raw] = freq('load', $P, null, '', 'api.php'); $recs = json_encode((json_decode($raw, true) ?? [])['records'] ?? null); fc($c === 200 && $recs !== 'null' && !str_contains($recs, 'ofc') && !str_contains($recs, '1-382'), 'legacy sync hides Fee Receipt records');
    echo "Fee Receipt: start, numbering from the old ERP, Upto Month maths, late fee and discount, balance and old-due checks, deposit bank, idempotency, cancel, new admission and one-place-to-collect passed, plus Old Due on the card, Add Other Fee (single and bulk) and head-wise discount\n";
} finally {
    $clean();
    if ($oldSettings !== null) sch_rec_put('settings', 'main', $oldSettings, 'principal'); else $db->exec("DELETE FROM sch_records WHERE k='settings' AND r='main'");
}
