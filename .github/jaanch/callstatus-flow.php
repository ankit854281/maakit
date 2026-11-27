<?php
// ============================================================
//  जाँच — Fee Calling Report → Status (callreport_api.php?a=status), 27 Nov 2026.
//  Owner: "kitne jan ne date de diya hai aur kitne jan ka fee baaki hai but baat nahi hua hai".
//  Every family with fee due is in exactly one group by its LATEST call this session: never called · not reached ·
//  talked, no date · date ahead · date passed · paid / said paid. Company / FD families are counted apart.
// ============================================================
require_once __DIR__ . '/../../config.php';
if (DB_NAME !== 'maakit_jaanch') throw new RuntimeException('CI database only');
require_once __DIR__ . '/../../inc/db.php';
require_once __DIR__ . '/../../school/inc.php';
require_once __DIR__ . '/../../school/followup_lib.php';
function cs_check($ok, $m) { if (!$ok) throw new RuntimeException('Call status flow: ' . $m); }
function cs_http(string $path, $body = null, ?string $tok = null): array {
    $c = curl_init('http://127.0.0.1:8099' . $path); $h = ['X-School-Token: ' . ($tok ?? '')]; $o = [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 60, CURLOPT_PROXY => ''];
    if ($body !== null) { $h[] = 'Content-Type: application/json'; $o[CURLOPT_POST] = true; $o[CURLOPT_POSTFIELDS] = json_encode($body); }
    $o[CURLOPT_HTTPHEADER] = $h; curl_setopt_array($c, $o); $raw = (string)curl_exec($c); $code = curl_getinfo($c, CURLINFO_HTTP_CODE); curl_close($c);
    cs_check(!preg_match('/Fatal error|Warning:|Uncaught|Deprecated|Notice:/', $raw), "PHP error at $path: " . substr($raw, 0, 300));
    return [$code, json_decode($raw, true)];
}
foreach (['sch_sessions', 'sch_users', 'sch_records', 'sch_log', 'sch_login_fail'] as $t) $pdo->exec("DELETE FROM $t");
$y = (int)date('Y') - ((int)date('n') < 4 ? 1 : 0); $session = $y . '-' . substr((string)($y + 1), 2);
sch_rec_put('settings', 'main', ['session' => $session, 'sessionStart' => $y . '-04-01', 'school' => 'Synthetic status school'], 'principal');
$session = ff_session();
$fams = ['cs-never' => '9811200001', 'cs-noreach' => '9811200002', 'cs-talk' => '9811200003', 'cs-date' => '9811200004', 'cs-miss' => '9811200005', 'cs-paid' => '9811200006', 'cs-comp' => '9811200007'];
foreach ($fams as $sid => $phone) {
    sch_rec_put('student', $sid, ['id' => $sid, 'adm' => $sid, 'name' => 'Child ' . $sid, 'cls' => '2nd', 'section' => 'A', 'father' => 'Parent ' . $sid, 'phone' => $phone], 'principal');
    $months = [['', 'D/A', 'Fee Name', 'Amount', 'Paid/Disc Amount', 'Balance', 'N Paid', 'Status'], [date('F')], ['', '', 'Tution Fee', '1000', '0', '1000', '', 'Not Paid']];
    sch_rec_put('regRow', 'oldfeecard|cs-' . $sid, ['session' => $session, 'liveId' => $sid, 'oldId' => $sid, 'name' => 'Child ' . $sid, 'cls' => '2nd', 'section' => 'A', 'months' => $months, 'oldFee' => '0', 'transferFee' => '0', 'asOn' => date('d-m-Y'), 'nextReceipt' => '1-901'], 'principal');
}
sch_rec_put('fdTag', 'cs-comp', ['tag' => 'Company', 'fixed' => 0], 'principal');
$call = function (string $sid, string $outcome, string $promise = '', int $ago = 0) use ($session) {
    $at = (time() - $ago * 3600) * 1000; $id = 'c-' . $sid . '-' . $ago;
    sch_rec_put('acctCall', $id, ['id' => $id, 'sid' => $sid, 'sids' => [$sid], 'session' => $session, 'outcome' => $outcome, 'promise' => $promise, 'amount' => $promise ? 50000 : 0, 'note' => 'n', 'by' => "Rati Ma'am", 'at' => $at, 'date' => date('Y-m-d', intdiv($at, 1000))], 'principal');
};
$call('cs-noreach', 'Not answered');
$call('cs-talk', 'Promised to pay', date('Y-m-d', strtotime('+2 days')), 5); $call('cs-talk', 'Call back later', '', 1);   // the latest call counts
$call('cs-date', 'Promised to pay', date('Y-m-d', strtotime('+3 days')));
$call('cs-miss', 'Promised to pay', date('Y-m-d', strtotime('-2 days')), 72);
$call('cs-paid', 'Already paid');
$call('cs-comp', 'Not answered');
$pdo->prepare('INSERT INTO sch_users (uid, role, tid, pin_hash, must_change) VALUES (?,?,?,?,0)')->execute(['principal', 'principal', 'P', password_hash('2468', PASSWORD_DEFAULT)]);
$pdo->prepare('INSERT INTO sch_users (uid, role, tid, pin_hash, must_change) VALUES (?,?,?,?,0)')->execute(['t:K', 'teacher', 'K', password_hash('7777', PASSWORD_DEFAULT)]);
sch_rec_put('teacher', 'K', ['name' => 'Kartikey Sir'], 'principal');
[, $j] = cs_http('/school/api.php?a=login', ['role' => 'principal', 'pin' => '2468']); $P = $j['token'];
[, $j] = cs_http('/school/api.php?a=login', ['role' => 'teacher', 'tid' => 'K', 'pin' => '7777']); $K = $j['token'];
[$c] = cs_http('/school/callreport_api.php?a=status', null, $K); cs_check($c === 403, 'an ordinary teacher cannot open it');
[$c, $j] = cs_http('/school/callreport_api.php?a=status', null, $P); cs_check($c === 200 && $j['ok'], 'Principal opens the status');
$st = []; foreach ($j['families'] as $f) $st[$f['kids'][0]['sid']] = $f['st'];
$want = ['cs-never' => 'never', 'cs-noreach' => 'noreach', 'cs-talk' => 'talked', 'cs-date' => 'date', 'cs-miss' => 'missed', 'cs-paid' => 'paid'];
foreach ($want as $sid => $w) cs_check(($st[$sid] ?? '?') === $w, "$sid is '$w' (got " . ($st[$sid] ?? 'missing') . ')');
cs_check(!isset($st['cs-comp']) && $j['tagged']['n'] === 1, 'Company family counted apart');
cs_check($j['families'][0]['due'] === 100000, 'due in paise');
echo "Call status flow: OK\n";
