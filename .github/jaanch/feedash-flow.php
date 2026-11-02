<?php
// Fee Control Room (school/feedash_api.php) — CI database only, synthetic rows only.
// Who may open it, what Reception may not see (money taken), dues from the fee card, receipts of the session,
// and a call saved by Reception showing up in the call log.
require __DIR__ . '/../../config.php';
if (DB_NAME !== 'maakit_jaanch') throw new RuntimeException('CI database only');
require __DIR__ . '/../../school/inc.php';
function fd($v, $m) { if (!$v) throw new RuntimeException('Fee Control Room flow: ' . $m); }
function fdtok($uid, $role) {
    $db = sch_db();
    $db->prepare('INSERT INTO sch_users(uid,role,tid,must_change) VALUES(?,?,NULL,0) ON DUPLICATE KEY UPDATE role=VALUES(role),must_change=0')->execute([$uid, $role]);
    $t = bin2hex(random_bytes(32));
    $db->prepare('INSERT INTO sch_sessions(token_hash,uid,expires_at) VALUES(?,?,DATE_ADD(NOW(),INTERVAL 1 DAY))')->execute([hash('sha256', $t), $uid]);
    return $t;
}
function fdreq($path, $token, $body = null) {
    $h = ['X-School-Token: ' . $token]; $o = [CURLOPT_RETURNTRANSFER => true, CURLOPT_PROXY => '', CURLOPT_TIMEOUT => 20];
    if ($body !== null) { $o[CURLOPT_POST] = true; $h[] = 'Content-Type: application/json'; $o[CURLOPT_POSTFIELDS] = json_encode($body); }
    $o[CURLOPT_HTTPHEADER] = $h;
    $c = curl_init('http://127.0.0.1:8099/school/' . $path); curl_setopt_array($c, $o);
    $raw = curl_exec($c); $code = curl_getinfo($c, CURLINFO_HTTP_CODE); curl_close($c);
    fd(is_string($raw) && !preg_match('/Fatal error|Warning:|Uncaught|Deprecated:|Notice:/', $raw), 'PHP response ' . $path . ': ' . substr((string)$raw, 0, 300));
    return [$code, json_decode((string)$raw, true)];
}
$db = sch_db();
$oldSettings = sch_rec_get('settings', 'main');
$C = fdtok('chairman', 'chairman'); $P = fdtok('principal', 'principal'); $R = fdtok('reception', 'reception'); $T = fdtok('fd-teacher', 'teacher');
$clean = function () use ($db) { $db->exec("DELETE FROM sch_records WHERE k LIKE 'ofc%' OR (k='regRow' AND (r LIKE 'oldfeecard|%' OR r LIKE 'oldreceipt|fd%')) OR (k='student' AND r IN ('fd-s1','fd-s2')) OR (k='acctCall' AND d LIKE '%fd-s1%') OR (k='regRow' AND r LIKE 'oldrcptdet|fd%') OR (k='fdTag' AND r IN ('fd-s1','fd-s2'))"); };
try {
    $clean();
    sch_rec_put('settings', 'main', ['session' => '2026-27', 'sessionStart' => '2026-04-01', 'school' => 'Synthetic Dash School', 'place' => 'Test'], 'principal');
    foreach ([['fd-s1', 'Synthetic Dash One', '9811100001'], ['fd-s2', 'Synthetic Dash Two', '9811100002']] as [$id, $name, $ph])
        sch_rec_put('student', $id, ['id' => $id, 'adm' => strtoupper($id), 'name' => $name, 'cls' => '2nd', 'section' => 'A', 'father' => 'Synthetic Father', 'phone' => $ph], 'principal');
    $months = [['', 'D/A', 'Fee Name', 'Amount', 'Paid/Disc Amount', 'Balance', 'N Paid', 'Status'],
        ['April'], ['', '', 'Tution Fee', '790', '790', '0', '', 'Paid'], ['May'], ['', '', 'Tution Fee', '790', '0', '790', '', 'Not Paid'], ['March'], ['', '', 'Tution Fee', '790', '0', '790', '', 'Not Paid']];
    $card = fn($sid, $old) => ['session' => '2026-27', 'oldId' => $old, 'liveId' => $sid, 'name' => 'Synthetic', 'cls' => '2nd', 'section' => 'A', 'scholar' => '1 /', 'scheme' => 'Regular-01-1',
        'months' => $months, 'oldFee' => '0', 'transferFee' => '0', 'nextReceipt' => '1-900', 'asOn' => '01-11-2026'];
    sch_rec_put('regRow', 'oldfeecard|fd1', $card('fd-s1', '991'), 'principal');
    sch_rec_put('regRow', 'oldfeecard|fd2', $card('fd-s2', '992'), 'principal');
    sch_rec_put('regRow', 'oldreceipt|fd1', ['session' => '2026-2027', 'receiptNo' => '1-899', 'date' => date('d-m-Y'), 'student' => 'fd-s1', 'name' => 'Synthetic Dash One', 'cls' => '2nd', 'section' => 'A',
        'total' => 79000, 'discount' => 0, 'lateFee' => 0, 'received' => 79000, 'mode' => 'Cash', 'status' => 'Cleared', 'user' => 'admin'], 'principal');
    sch_rec_put('regRow', 'oldrcptdet|fd1', ['session' => '2026-2027', 'receiptNo' => '1-899', 'lines' => [['1', 'Tution Fee', 'May', '590'], ['2', 'Transport', 'May', '200']]], 'principal');

    [$c] = fdreq('feedash_api.php?a=data', $T); fd($c === 403, 'teacher cannot open it');
    [$c, $d] = fdreq('feedash_api.php?a=data&ses=2026-27', $C);
    fd($c === 200 && $d['ses'] === '2026-27' && $d['money'] === true && !$d['canCall'], 'chairman sees money, does not call ' . json_encode(array_intersect_key((array)$d, ['ses' => 1, 'money' => 1, 'canCall' => 1, 'error' => 1])));
    $mine = array_values(array_filter($d['dues'], fn($r) => in_array($r['sid'], ['fd-s1', 'fd-s2'], true)));
    fd(count($mine) === 2 && !isset($mine[0]['receipts']) && count($mine[0]['heads']) === 3, 'dues from the copied fee card ' . json_encode($mine[0] ?? null));
    fd((bool)array_filter($d['receipts'], fn($r) => $r['no'] === '1-899' && $r['session'] === '2026-27' && $r['received'] == 790 && $r['date'] === date('Y-m-d') && $r['split'] && $r['tr'] == 200), 'today\'s old ERP receipt counted, transport split from its lines');
    // Tags: Principal only; a fixed monthly amount (FD / fixed plan) travels with the tag
    [$c] = fdreq('feedash_api.php?a=setTag', $C, ['sid' => 'fd-s1', 'tag' => 'FD', 'fixed' => '500']); fd($c === 403, 'chairman cannot set tags');
    [$c] = fdreq('feedash_api.php?a=setTag', $P, ['sid' => 'fd-s1', 'tag' => 'FD', 'fixed' => 'abc']); fd($c === 400, 'fixed amount must be a number');
    [$c, $x] = fdreq('feedash_api.php?a=setTag', $P, ['sid' => 'fd-s1', 'tag' => ' FD ', 'fixed' => '500', 'note' => 'plan']); fd($c === 200 && $x['tag']['tag'] === 'FD' && $x['tag']['fixed'] === 50000, 'tag saved ' . json_encode($x));
    [$c, $d] = fdreq('feedash_api.php?a=data&ses=2026-27', $C); $one = array_values(array_filter($d['dues'], fn($r) => $r['sid'] === 'fd-s1'))[0];
    fd($one['tag'] === 'FD' && $one['fixed'] == 500 && $one['tagNote'] === 'plan', 'tag and fixed plan in the data ' . json_encode(array_intersect_key($one, ['tag' => 1, 'fixed' => 1, 'tagNote' => 1])));
    [$c, $d] = fdreq('feedash_api.php?a=data&ses=2026-27', $R); fd($c === 200 && $d['money'] === false && $d['receipts'] === [] && $d['canCall'], 'reception gets the calling list without the money taken');
    [$c, $x] = fdreq('office_api.php?a=call', $R, ['sid' => 'fd-s1', 'outcome' => 'Promised to pay', 'promise' => date('Y-m-d', strtotime('+2 day')), 'note' => 'synthetic']);
    fd($c === 200, 'reception saves a call ' . json_encode($x));
    [$c, $d] = fdreq('feedash_api.php?a=data&ses=2026-27', $C); fd((bool)array_filter($d['calls'], fn($k) => in_array('fd-s1', $k['sids'], true) && $k['outcome'] === 'Promised to pay'), 'call shows in the log');
    [$c] = fdreq('office_api.php?a=call', $C, ['sid' => 'fd-s1', 'outcome' => 'Not answered']); fd($c === 403, 'chairman does not save calls');
    echo "Fee Control Room: roles, Reception without money, dues from the fee card, today's receipt with transport split, tags with fixed plan and the call log passed\n";
} finally {
    $clean();
    if ($oldSettings !== null) sch_rec_put('settings', 'main', $oldSettings, 'principal'); else $db->exec("DELETE FROM sch_records WHERE k='settings' AND r='main'");
}
