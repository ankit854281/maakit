<?php
// ============================================================
//  जाँच — Staff leave policy (school/leavepolicy_lib.php, 12 Nov 2026)
//
//  Time par maangi gayi chhoti chhutti apne aap approve → late chhutti
//  Pending + "late" → lambi chhutti Principal ke paas → teacher ek baar
//  niyam maanta hai (tchAccept) → niyam badle to dobara → leave report
//  sirf Principal/Chairman → "tch" records aam load me nahi.
// ============================================================
require_once __DIR__ . '/../../config.php';
if (DB_NAME !== 'maakit_jaanch') throw new RuntimeException('CI database only');
require_once __DIR__ . '/../../inc/db.php';
require_once __DIR__ . '/../../school/inc.php';
require_once __DIR__ . '/../../school/leavepolicy_lib.php';

function lp_check($ok, $m) { if (!$ok) throw new RuntimeException('Leave policy flow: ' . $m); }
function lp_http(string $path, $body = null, ?string $tok = null): array {
    $c = curl_init('http://127.0.0.1:8099' . $path);
    $h = ['X-School-Token: ' . ($tok ?? '')]; $o = [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30, CURLOPT_PROXY => ''];
    if ($body !== null) { $h[] = 'Content-Type: application/json'; $o[CURLOPT_POST] = true; $o[CURLOPT_POSTFIELDS] = json_encode($body); }
    $o[CURLOPT_HTTPHEADER] = $h; curl_setopt_array($c, $o);
    $raw = (string)curl_exec($c); $code = curl_getinfo($c, CURLINFO_HTTP_CODE); curl_close($c);
    lp_check(!preg_match('/Fatal error|Warning:|Uncaught|Deprecated/', $raw), "PHP error at $path: " . substr($raw, 0, 300));
    return [$code, json_decode($raw, true), $raw];
}
$api = fn($a, $b = null, $t = null) => lp_http('/school/api.php?a=' . $a, $b, $t);
$ta  = fn($a, $b = null, $t = null, $q = '') => lp_http('/school/teacher_api.php?a=' . $a . $q, $b, $t);

foreach (['sch_sessions', 'sch_users', 'sch_records', 'sch_log', 'sch_login_fail'] as $t) $pdo->exec("DELETE FROM $t");
$put = fn($k, $r, $d) => sch_rec_put($k, $r, $d, 'principal');
$put('settings', 'main', ['school' => 'Leave Test School', 'session' => '2026-27', 'sessionStart' => '2026-04-01']);
$put('teacher', 'K', ['name' => 'Kartikey Sir', 'role' => 'Social Science']);
$put('teacher', 'MA', ['name' => 'Mamta Ma\'am', 'role' => 'Hindi']);

// ---- default (owner, 12 Nov 2026): nothing is approved by itself ----
lp_check(lp_policy()['autoDays'] === 0, 'auto-approve is off by default');
$r0 = lp_on_leave(['id' => 'x', 'tid' => 'K', 'from' => '2026-11-13', 'to' => '2026-11-13', 'status' => 'Pending'], 'K', strtotime('2026-11-12 06:00 Asia/Kolkata'));
lp_check($r0['status'] === 'Pending' && $r0['late'] === false, 'on-time leave still waits for the Principal');
lp_check(str_contains(implode(' ', lp_rules()), 'Har chhutti Principal approve karenge'), 'rules say the Principal approves every leave');
// the rest checks the optional auto-approve (Principal sets autoDays = 2)
$put('tchPolicy', 'main', ['ver' => 2, 'autoDays' => 2]);

// ---- unit: on time / late / long (India time) ----
$at = fn($s) => strtotime($s . ' Asia/Kolkata');
$L = fn($f, $t) => ['id' => 'x', 'tid' => 'K', 'from' => $f, 'to' => $t, 'reason' => 'r', 'status' => 'Pending'];
$r = lp_on_leave($L('2026-11-12', '2026-11-12'), 'K', $at('2026-11-12 06:30'));
lp_check($r['status'] === 'Approved' && !empty($r['auto']) && $r['late'] === false, 'today before 7:00 = on time, approved: ' . json_encode($r));
$r = lp_on_leave($L('2026-11-12', '2026-11-12'), 'K', $at('2026-11-12 08:10'));
lp_check($r['status'] === 'Pending' && $r['late'] === true, 'today after 7:00 = late, waits: ' . json_encode($r));
$r = lp_on_leave($L('2026-11-11', '2026-11-11'), 'K', $at('2026-11-12 06:00'));
lp_check($r['status'] === 'Pending' && $r['late'] === true, 'a past day is late');
$r = lp_on_leave($L('2026-11-13', '2026-11-14'), 'K', $at('2026-11-12 21:00'));
lp_check($r['status'] === 'Approved' && $r['decidedBy'] === 'Auto (leave policy)', 'tomorrow, 2 days = approved by itself');
$r = lp_on_leave($L('2026-11-13', '2026-11-15'), 'K', $at('2026-11-12 10:00'));
lp_check($r['status'] === 'Pending' && $r['late'] === false, '3 days on time = waits for the Principal, not late');
lp_check(count(lp_rules()) >= 8 && str_contains(implode(' ', lp_rules()), '₹300'), 'rules text has the bonus');

// ---- logins ----
$pin = fn($uid, $role, $tid, $p) => $pdo->prepare('INSERT INTO sch_users (uid, role, tid, pin_hash, must_change) VALUES (?,?,?,?,0)')->execute([$uid, $role, $tid, password_hash($p, PASSWORD_DEFAULT)]);
$pin('principal', 'principal', 'P', '2468'); $pin('t:K', 'teacher', 'K', '7777'); $pin('t:MA', 'teacher', 'MA', '8888'); $pin('chairman', 'chairman', null, '6666');
$tok = [];
foreach ([['P', ['role' => 'principal', 'pin' => '2468']], ['K', ['role' => 'teacher', 'tid' => 'K', 'pin' => '7777']], ['MA', ['role' => 'teacher', 'tid' => 'MA', 'pin' => '8888']], ['CH', ['role' => 'chairman', 'pin' => '6666']]] as [$n, $b]) {
    [, $j] = $api('login', $b); $tok[$n] = $j['token'] ?? null; lp_check($tok[$n], "login $n");
}

// ---- accept the rules once ----
[$c, $j] = $ta('policy', null, $tok['K']); lp_check($c === 200 && $j['accepted'] === false && $j['policy']['ver'] === 2 && count($j['rules']) >= 8, 'teacher has not accepted yet');
[$c, $j] = $ta('policy', null, $tok['P']); lp_check($c === 200 && $j['accepted'] === true, 'Principal is never blocked');
[$c] = $ta('accept', ['ver' => 9], $tok['K']); lp_check($c === 400, 'old version refused');
[$c] = $ta('accept', ['ver' => 2], $tok['P']); lp_check($c === 403, 'Principal does not accept');
[$c] = $ta('accept', ['ver' => 2], $tok['CH']); lp_check($c === 403, 'Chairman only views');
[$c] = $ta('accept', ['ver' => 2], $tok['K']); lp_check($c === 200, 'K accepts');
[, $j] = $ta('policy', null, $tok['K']); lp_check($j['accepted'] === true && $j['acceptedAt'] > 0, 'K accepted with the time');
lp_check((int)$pdo->query("SELECT COUNT(*) FROM sch_log WHERE k='Leave policy' AND x LIKE 'Kartikey Sir accepted%'")->fetchColumn() === 1, 'acceptance logged');

// ---- leave through the normal sync ----
$tom = date('Y-m-d', strtotime('+1 day')); $far = date('Y-m-d', strtotime('+10 day')); $far3 = date('Y-m-d', strtotime('+13 day'));
[$c, $j] = $api('sync', ['put' => [['k' => 'leave', 'r' => 'lv1', 'd' => ['id' => 'lv1', 'tid' => 'K', 'from' => $tom, 'to' => $tom, 'reason' => 'Shaadi', 'status' => 'Pending', 'at' => 1]]], 'del' => [], 'log' => []], $tok['K']);
lp_check($c === 200 && !$j['rejected'], 'leave sent');
$v = sch_rec_get('leave', 'lv1'); lp_check($v['status'] === 'Approved' && $v['auto'] === true && $v['late'] === false && $v['appliedAt'] > 0, 'tomorrow 1 day approved by the server itself: ' . json_encode($v));
[, $j] = $api('sync', ['put' => [['k' => 'leave', 'r' => 'lv2', 'd' => ['id' => 'lv2', 'tid' => 'K', 'from' => $far, 'to' => $far3, 'reason' => 'Ghar', 'status' => 'Pending', 'at' => 2]]], 'del' => [], 'log' => []], $tok['K']);
lp_check(sch_rec_get('leave', 'lv2')['status'] === 'Pending', '4-day leave waits for the Principal');
[, $j] = $api('sync', ['put' => [['k' => 'leave', 'r' => 'lv3', 'd' => ['id' => 'lv3', 'tid' => 'K', 'from' => $far, 'to' => $far, 'reason' => 'x', 'status' => 'Approved', 'at' => 3]]], 'del' => [], 'log' => []], $tok['K']);
lp_check(count($j['rejected']) === 1 && sch_rec_get('leave', 'lv3') === null, 'a teacher cannot send an approved leave');
[, $j] = $api('sync', ['put' => [['k' => 'leave', 'r' => 'lv1', 'd' => ['id' => 'lv1', 'tid' => 'K', 'from' => $tom, 'to' => $tom, 'reason' => 'Shaadi', 'status' => 'Pending', 'at' => 1]]], 'del' => [], 'log' => []], $tok['K']);
lp_check(count($j['rejected']) === 1 && sch_rec_get('leave', 'lv1')['status'] === 'Approved', 'an old Pending copy cannot undo the approval');

// ---- report ----
[$c] = $ta('leavereport', null, $tok['K']); lp_check($c === 403, 'teacher cannot see the report');
[$c, $j] = $ta('leavereport', null, $tok['CH'], '&ym=' . date('Y-m')); lp_check($c === 200 && count($j['rows']) === 2, 'Chairman sees the report');
$byT = array_column($j['rows'], null, 'tid'); lp_check($byT['K']['accepted'] === true && $byT['MA']['accepted'] === false, 'report shows who accepted');
[$c] = $ta('leavereport', null, $tok['P'], '&ym=2026-13'); lp_check($c === 400, 'bad month');

// ---- change the rules → everyone accepts again ----
[$c] = $ta('policyset', ['bonus' => 500], $tok['K']); lp_check($c === 403, 'teacher cannot change rules');
[$c, $j] = $ta('policyset', ['bonus' => 500, 'noticeHour' => 6], $tok['P']); lp_check($c === 200 && $j['policy']['ver'] === 3 && $j['policy']['bonus'] === 500, 'Principal changes the bonus');
[, $j] = $ta('policy', null, $tok['K']); lp_check($j['accepted'] === false && str_contains(implode(' ', $j['rules']), '₹500') && str_contains(implode(' ', $j['rules']), '6:00'), 'new version asks again');
lp_check(lp_policy()['bonus'] === 500, 'salary reads the new bonus');

// ---- private records never reach the normal load ----
[, $j] = $api('load', null, $tok['MA']);
lp_check(!array_filter($j['records'], fn($x) => str_starts_with($x['k'], 'tch')), 'tchAccept / tchPolicy not in the teachers\' load');

echo "Leave policy flow: OK\n";
