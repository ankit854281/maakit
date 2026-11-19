<?php
// ============================================================
//  जाँच — Parent Calls (school/calls_api.php, owner 19 Nov 2026: PTM / event / attendance calling + feedback)
//
//  Principal makes a PTM list for all classes → class teacher sees only own class, calls, marks "Will come" +
//  feedback → complaint needs words → no answer goes to tomorrow's call-again list → attendance: 3 days in a
//  row / 1 day / leave not counted / holiday gap does not break / below 75% → a class teacher's own list only for
//  own class → Chairman views only → report counts + PTM "came" → every call also in the child's tchLog →
//  tchCall never in the normal load.
// ============================================================
require_once __DIR__ . '/../../config.php';
if (DB_NAME !== 'maakit_jaanch') throw new RuntimeException('CI database only');
require_once __DIR__ . '/../../inc/db.php';
require_once __DIR__ . '/../../school/inc.php';
function pc_ok($ok, $m) { if (!$ok) throw new RuntimeException('Parent calls flow: ' . $m); }
function pc_http(string $path, $body, ?string $tok): array {
    $c = curl_init('http://127.0.0.1:8099' . $path); $h = ['X-School-Token: ' . ($tok ?? '')]; $o = [CURLOPT_RETURNTRANSFER => true, CURLOPT_PROXY => '', CURLOPT_TIMEOUT => 30];
    if ($body !== null) { $h[] = 'Content-Type: application/json'; $o[CURLOPT_POST] = true; $o[CURLOPT_POSTFIELDS] = json_encode($body); }
    $o[CURLOPT_HTTPHEADER] = $h; curl_setopt_array($c, $o);
    $raw = (string)curl_exec($c); $code = curl_getinfo($c, CURLINFO_HTTP_CODE); curl_close($c);
    pc_ok(!preg_match('/Fatal error|Warning:|Uncaught|Deprecated|Notice:/', $raw), "PHP error at $path: " . substr($raw, 0, 300));
    return [$code, json_decode($raw, true)];
}
$C = fn($a, $b = null, $t = null, $q = '') => pc_http('/school/calls_api.php?a=' . $a . $q, $b, $t);
foreach (['sch_sessions', 'sch_users', 'sch_records', 'sch_log', 'sch_login_fail'] as $t) $pdo->exec("DELETE FROM $t");
$put = fn($k, $r, $d) => sch_rec_put($k, $r, $d, 'principal');
$put('settings', 'main', ['school' => 'Calls Test School', 'session' => '2026-27', 'sessionStart' => '2026-04-01', 'feeCallers' => []]);
$put('teacher', 'K', ['name' => 'Kartikey Sir']); $put('teacher', 'MA', ['name' => 'Mamta Ma\'am']);
$put('classes', 'main', [['c' => '9th', 'ct' => 'K'], ['c' => '10th', 'ct' => 'MA']]);
$put('student', 's1', ['id' => 's1', 'name' => 'Aman', 'cls' => '9th', 'phone' => '9839100011', 'father' => 'Ram']);
$put('student', 's2', ['id' => 's2', 'name' => 'Bina', 'cls' => '9th', 'phone' => '9839100012']);
$put('student', 's3', ['id' => 's3', 'name' => 'Chetan', 'cls' => '9th', 'phone' => '9839100013']);
$put('student', 's4', ['id' => 's4', 'name' => 'Dev', 'cls' => '10th', 'phone' => '9839100014']);
$put('student', 's5', ['id' => 's5', 'name' => 'Old', 'cls' => '9th', 'inactive' => true]);
// attendance of 9th: last 4 school days (a gap day in between = holiday, no studAtt). s1 absent 3 days in a row, s2 absent only
// today, s3 on leave today and absent before (leave ends the streak → 0).
$d = fn($n) => date('Y-m-d', strtotime("-$n days"));
$put('studAtt', $d(0) . '|9th', ['absent' => ['s1', 's2', 's3'], 'st' => ['s3' => 'L']]);
$put('studAtt', $d(1) . '|9th', ['absent' => ['s1', 's3']]);
$put('studAtt', $d(3) . '|9th', ['absent' => ['s1']]);           // $d(2) = holiday: no record, the streak goes on
$put('studAtt', $d(4) . '|9th', ['absent' => []]);
$pin = fn($uid, $role, $tid, $p) => $pdo->prepare('INSERT INTO sch_users (uid, role, tid, pin_hash, must_change) VALUES (?,?,?,?,0)')->execute([$uid, $role, $tid, password_hash($p, PASSWORD_DEFAULT)]);
$pin('principal', 'principal', 'P', '2468'); $pin('t:K', 'teacher', 'K', '7777'); $pin('t:MA', 'teacher', 'MA', '5555'); $pin('chairman', 'chairman', null, '6666'); $pin('reception', 'reception', null, '3333');
$tok = [];
foreach ([['P', ['role' => 'principal', 'pin' => '2468']], ['K', ['role' => 'teacher', 'tid' => 'K', 'pin' => '7777']], ['MA', ['role' => 'teacher', 'tid' => 'MA', 'pin' => '5555']], ['CH', ['role' => 'chairman', 'pin' => '6666']], ['R', ['role' => 'reception', 'pin' => '3333']]] as [$n, $b]) {
    [, $j] = pc_http('/school/api.php?a=login', $b, null); $tok[$n] = $j['token'] ?? null; pc_ok($tok[$n], "login $n");
}

// ---- campaign ----
[$c] = $C('campaign', ['title' => 'PTM', 'type' => 'ptm', 'date' => $d(0), 'classes' => ['9th', '10th']], $tok['K']); pc_ok($c === 403, 'class teacher cannot make a list for other classes');
[$c, $j] = $C('campaign', ['title' => '', 'type' => 'ptm', 'date' => $d(0)], $tok['P']); pc_ok($c === 400, 'title needed');
[$c, $j] = $C('campaign', ['title' => 'PTM Saturday', 'type' => 'ptm', 'time' => '10 AM'], $tok['P']); pc_ok($c === 400, 'PTM needs a date');
[$c, $j] = $C('campaign', ['title' => 'PTM Saturday', 'type' => 'ptm', 'date' => $d(0), 'time' => '10 AM', 'classes' => [], 'script' => 'Please come'], $tok['P']);
pc_ok($c === 200 && $j['campaign']['active'] === true, 'Principal makes a PTM list for all classes'); $ptm = $j['campaign']['id'];
[$c] = $C('campaign', ['title' => 'Annual day', 'type' => 'event', 'date' => $d(0)], $tok['CH']); pc_ok($c === 403, 'Chairman cannot make lists');
[$c, $j] = $C('campaign', ['title' => 'Class 9 picnic', 'type' => 'event', 'date' => $d(0), 'classes' => ['9th']], $tok['K']); pc_ok($c === 200, 'class teacher makes a list for own class'); $pic = $j['campaign']['id'];
[$c] = $C('campaign', ['title' => 'x', 'type' => 'other', 'classes' => ['10th']], $tok['K']); pc_ok($c === 403, 'not for another class');

// ---- who sees what ----
[, $j] = $C('state', null, $tok['K']);
pc_ok($j['classes'] === ['9th'] && count($j['campaigns']) === 2 && $j['campaigns'][0]['classes'] === ['9th'], 'K sees only 9th in both lists: ' . json_encode($j['classes']));
$pc = array_values(array_filter($j['campaigns'], fn($x) => $x['id'] === $ptm))[0]; pc_ok($pc['n']['kids'] === 3, 'inactive child not counted');
pc_ok($j['att']['9th']['d3'] === 1 && $j['att']['9th']['d1'] === 1 && $j['att']['9th']['todo'] === 2, 'attendance counts (3 days, 1 day; leave today ends the streak): ' . json_encode($j['att']));
[, $j] = $C('state', null, $tok['MA']); pc_ok(count($j['campaigns']) === 1 && $j['classes'] === ['10th'], 'MA does not see the 9th picnic');
[$c] = $C('list', null, $tok['MA'], "&cp=$ptm&cls=9th"); pc_ok($c === 403, 'MA cannot open 9th');
[$c, $j] = $C('list', null, $tok['CH'], "&cp=$ptm&cls=9th"); pc_ok($c === 200 && $j['can'] === false, 'Chairman views only');
[, $j] = $C('list', null, $tok['K'], '&cp=att&cls=9th');
$by = array_column($j['kids'], null, 'sid');
pc_ok(count($j['kids']) === 2 && $by['s1']['abs']['streak'] === 3 && $by['s1']['abs']['from'] === $d(3) && $by['s2']['abs']['streak'] === 1 && !isset($by['s3']) && $j['kids'][0]['sid'] === 's1',
    'attendance list: 3-day child first, holiday gap does not break, leave = 0: ' . json_encode(array_map(fn($k) => $k['abs'], $j['kids'])));

// ---- calls ----
[$c] = $C('call', ['sid' => 's4', 'cp' => $ptm, 'outcome' => 'Talked', 'ans' => 'Will come'], $tok['K']); pc_ok($c === 403, 'K cannot call a 10th parent');
[$c] = $C('call', ['sid' => 's1', 'cp' => $ptm, 'outcome' => 'Talked'], $tok['K']); pc_ok($c === 400, 'PTM: must say if parent comes');
[$c] = $C('call', ['sid' => 's1', 'cp' => $ptm, 'outcome' => 'Talked', 'ans' => 'Will come', 'fb' => 'bad'], $tok['K']); pc_ok($c === 400, 'complaint needs words');
[$c, $j] = $C('call', ['sid' => 's1', 'cp' => $ptm, 'outcome' => 'Talked', 'ans' => 'Will come', 'fb' => 'bad', 'note' => 'Bus comes late'], $tok['K']); pc_ok($c === 200 && $j['call']['ans'] === 'Will come', 'call saved');
[$c, $j] = $C('call', ['sid' => 's2', 'cp' => $ptm, 'outcome' => 'No answer'], $tok['K']); pc_ok($c === 200 && $j['call']['next'] === date('Y-m-d', strtotime('+1 day')), 'no answer → call again tomorrow');
[$c] = $C('call', ['sid' => 's2', 'cp' => $ptm, 'outcome' => 'Talked', 'next' => date('Y-m-d', strtotime('+40 days')), 'ans' => 'Not sure'], $tok['K']); pc_ok($c === 400, 'call-again within 30 days');
[$c] = $C('call', ['sid' => 's4', 'cp' => $ptm, 'outcome' => 'Talked', 'ans' => 'Not coming', 'fb' => 'good'], $tok['R']); pc_ok($c === 200, 'Fee Calling desk calls any class');
[$c] = $C('call', ['sid' => 's1', 'cp' => 'att', 'outcome' => 'Talked', 'reason' => 'Ill / sick', 'comeOn' => date('Y-m-d', strtotime('+1 day'))], $tok['K']); pc_ok($c === 200, 'attendance call');
[$c] = $C('call', ['sid' => 's1', 'cp' => $ptm, 'outcome' => 'Talked', 'ans' => 'Will come'], $tok['CH']); pc_ok($c === 403, 'Chairman cannot save a call');
$logs = array_values(array_filter(array_map(fn($r) => json_decode($r, true), $pdo->query("SELECT d FROM sch_records WHERE k='tchLog' AND deleted=0")->fetchAll(PDO::FETCH_COLUMN)), fn($x) => ($x['sid'] ?? '') === 's1'));
pc_ok(count($logs) === 2 && str_contains($logs[0]['text'] . $logs[1]['text'], 'Ill / sick') && str_contains($logs[0]['text'] . $logs[1]['text'], 'Bus comes late'), 'every call is in the child\'s parent contact log');
[, $j] = $C('list', null, $tok['K'], '&cp=att&cls=9th'); $by = array_column($j['kids'], null, 'sid');
pc_ok($by['s1']['calls'][0]['absFrom'] === $d(3) && $by['s1']['calls'][0]['absDays'] === 3, 'attendance call remembers the absence it was for');
[, $j] = $C('state', null, $tok['K']); pc_ok($j['att']['9th']['todo'] === 1, 'called child leaves the to-call count');
[, $j] = $C('state', null, $tok['P']); pc_ok(count($j['again']) === 0, 'call again is tomorrow, not today');
$pdo->exec("UPDATE sch_records SET d = JSON_SET(d, '$.next', '" . date('Y-m-d') . "') WHERE k='tchCall' AND JSON_EXTRACT(d, '$.outcome') = 'No answer'");
[, $j] = $C('state', null, $tok['P']); pc_ok(count($j['again']) === 1 && $j['again'][0]['name'] === 'Bina', 'call-again list on the day');

// ---- PTM attendance + report ----
$put('tchLog', 'ptm|' . $d(0) . '|9th|s1', ['sid' => 's1', 'cls' => '9th', 'kind' => 'ptm', 'date' => $d(0), 'present' => 1]);
[$c, $j] = $C('report', null, $tok['CH'], "&cp=$ptm"); $r9 = array_column($j['rows'], null, 'cls')['9th']; $r10 = array_column($j['rows'], null, 'cls')['10th'];
pc_ok($c === 200 && $r9['kids'] === 3 && $r9['called'] === 2 && $r9['yes'] === 1 && $r9['noans'] === 1 && $r9['came'] === 1 && $r9['saidCame'] === 1 && $r9['bad'] === 1 && $r10['no'] === 1 && $r10['good'] === 1, 'report: ' . json_encode($j['rows']));
pc_ok(count($j['complaints']) === 1 && $j['complaints'][0]['note'] === 'Bus comes late', 'complaint listed for the Principal / Chairman');
[, $j] = $C('report', null, $tok['P'], '&cp=att'); pc_ok(array_column($j['rows'], null, 'cls')['9th']['reasons']['Ill / sick'] === 1, 'attendance report reasons');

// ---- close / delete ----
[$c] = $C('cpstate', ['id' => $ptm, 'active' => false], $tok['K']); pc_ok($c === 403, 'teacher cannot close the Principal\'s list');
[$c] = $C('cpstate', ['id' => $pic, 'active' => false], $tok['K']); pc_ok($c === 200, 'teacher closes own list');
[$c] = $C('call', ['sid' => 's1', 'cp' => $pic, 'outcome' => 'No answer'], $tok['K']); pc_ok($c === 400, 'no calls on a closed list');
[$c] = $C('cpdel', ['id' => $pic], $tok['K']); pc_ok($c === 403, 'only the Principal deletes');
[$c] = $C('cpdel', ['id' => $pic], $tok['P']); pc_ok($c === 200, 'Principal deletes');

// ---- never in the normal load ----
[, $j] = pc_http('/school/api.php?a=load', null, $tok['P']);
$noLog = $j; unset($noLog['log']);   // the Activity Log (Principal) names every call, as for every other action
pc_ok(!str_contains(json_encode($noLog), 'Bus comes late') && !str_contains(json_encode($j), 'tchCall'), 'tchCall is not in the legacy load: ' . implode(',', array_keys($j)));
echo "Parent calls flow: OK\n";
