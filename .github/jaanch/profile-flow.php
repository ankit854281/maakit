<?php
// ============================================================
//  जाँच — Profiles (school/profile_api.php) + salary summary
//
//  Purani attendance (5 mahine) sirf Principal/Chairman ko → teacher ki
//  salary history sirf Principal → shikayat/note sirf Principal likhe,
//  Chairman padh sake, teacher/parent kabhi nahi → "prof" records aam
//  sync/load me nahi (teacher ke phone par shikayat nahi pahunchni chahiye).
// ============================================================
require_once __DIR__ . '/../../config.php';
if (DB_NAME !== 'maakit_jaanch') throw new RuntimeException('CI database only');
require_once __DIR__ . '/../../inc/db.php';
require_once __DIR__ . '/../../school/inc.php';

function pf_check($ok, $m) { if (!$ok) throw new RuntimeException('Profile flow: ' . $m); }
function pf_http(string $path, $body = null, ?string $tok = null): array {
    $c = curl_init('http://127.0.0.1:8099' . $path);
    $h = ['X-School-Token: ' . ($tok ?? '')]; $o = [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30, CURLOPT_PROXY => ''];
    if ($body !== null) { $h[] = 'Content-Type: application/json'; $o[CURLOPT_POST] = true; $o[CURLOPT_POSTFIELDS] = json_encode($body); }
    $o[CURLOPT_HTTPHEADER] = $h; curl_setopt_array($c, $o);
    $raw = (string)curl_exec($c); $code = curl_getinfo($c, CURLINFO_HTTP_CODE); curl_close($c);
    pf_check(!preg_match('/Fatal error|Warning:|Uncaught|Deprecated/', $raw), "PHP error at $path: " . substr($raw, 0, 300));
    return [$code, json_decode($raw, true), $raw];
}
$api = fn($a, $b = null, $t = null, $q = '') => pf_http('/school/api.php?a=' . $a . $q, $b, $t);
$p   = fn($a, $b = null, $t = null, $q = '') => pf_http('/school/profile_api.php?a=' . $a . $q, $b, $t);

foreach (['sch_sessions', 'sch_users', 'sch_records', 'sch_log', 'sch_login_fail'] as $t) $pdo->exec("DELETE FROM $t");
$put = fn($k, $r, $d) => sch_rec_put($k, $r, $d, 'principal');
$put('settings', 'main', ['school' => 'Profile Test School', 'session' => '2026–27', 'sessionStart' => '2026-04-01']);
$put('teacher', 'K', ['name' => 'Kartikey Sir', 'role' => 'SST']);
$put('student', 's1', ['id' => 's1', 'adm' => '1', 'name' => 'Asha', 'cls' => '9th', 'phone' => '9839100001']);
$old = date('Y-m-d', strtotime('-120 days')); $older = date('Y-m-d', strtotime('-500 days'));
$put('studAtt', $old . '|9th', ['absent' => ['s1']]);
$put('staffAtt', $old, ['absent' => ['K']]);
$put('studAtt', $older . '|9th', ['absent' => []]);
$put('salStaff', 't:K', ['monthly' => 25000, 'paidLeave' => 1, 'active' => true]);
$put('salMonth', date('Y-m', strtotime('-1 month')) . '|t:K', ['status' => 'Paid', 'calc' => ['net' => 24000, 'gross' => 25000, 'absent' => 2], 'paidOn' => date('Y-m-d'), 'mode' => 'Cash']);
$pin = fn($uid, $role, $tid, $pp) => $pdo->prepare('INSERT INTO sch_users (uid, role, tid, pin_hash, must_change) VALUES (?,?,?,?,0)')->execute([$uid, $role, $tid, password_hash($pp, PASSWORD_DEFAULT)]);
$pin('principal', 'principal', 'P', '2468'); $pin('t:K', 'teacher', 'K', '7777'); $pin('chairman', 'chairman', null, '6666');
$pin('p:9839100001', 'parent', '9839100001', '3333'); $pin('accountant', 'accountant', null, '4321');
$tok = [];
foreach ([['P', ['role' => 'principal', 'pin' => '2468']], ['K', ['role' => 'teacher', 'tid' => 'K', 'pin' => '7777']], ['CH', ['role' => 'chairman', 'pin' => '6666']],
          ['PA', ['role' => 'parent', 'phone' => '9839100001', 'pin' => '3333']], ['A', ['role' => 'accountant', 'pin' => '4321']]] as [$n, $b]) {
    [, $j] = $api('login', $b); $tok[$n] = $j['token'];
}
$range = '&from=' . date('Y-m-d', strtotime('-160 days')) . '&to=' . date('Y-m-d');

// ---- who may open profiles ----
foreach (['K', 'PA', 'A'] as $n) { [$c] = $p('att', null, $tok[$n], $range); pf_check($c === 403, "$n cannot load profile attendance"); }
foreach (['K', 'PA'] as $n) { [$c] = $p('notes', null, $tok[$n], '&who=t:K'); pf_check($c === 403, "$n cannot read notes"); }

// ---- attendance history ----
[$c, $j] = $p('att', null, $tok['P'], $range);
$ks = array_map(fn($r) => $r['k'] . '|' . $r['r'], $j['records']);
pf_check($c === 200 && in_array("studAtt|$old|9th", $ks, true) && in_array("staffAtt|$old", $ks, true) && !in_array("studAtt|$older|9th", $ks, true), 'history range: ' . json_encode($ks));
[$c] = $p('att', null, $tok['P'], '&from=2020-01-01&to=' . date('Y-m-d')); pf_check($c === 400, 'max 400 days');
[$c] = $p('att', null, $tok['P'], '&from=x&to=y'); pf_check($c === 400, 'bad dates');

// ---- teacher salary history: Principal only ----
[$c, $j] = $p('teacher', null, $tok['P'], '&tid=K');
pf_check($c === 200 && $j['salary']['monthly'] === 25000 && count($j['salary']['months']) === 1 && $j['salary']['months'][0]['net'] === 24000, 'salary history');
[$c] = $p('teacher', null, $tok['P'], '&tid=ZZ'); pf_check($c === 400, 'unknown teacher');

// ---- notes ----
[$c] = $p('note', ['who' => 't:K', 'type' => 'Complaint', 'text' => 'Late twice this week'], $tok['CH']); pf_check($c === 403, 'chairman cannot write notes');
[$c] = $p('note', ['who' => 't:K', 'type' => 'Gossip', 'text' => 'x y z'], $tok['P']); pf_check($c === 400, 'type from list');
[$c] = $p('note', ['who' => 's:nobody', 'type' => 'Note', 'text' => 'x y z'], $tok['P']); pf_check($c === 400, 'unknown student');
[$c, $j] = $p('note', ['who' => 't:K', 'type' => 'Complaint', 'text' => 'Late twice this week'], $tok['P']); pf_check($c === 200, 'complaint saved'); $nid = $j['note']['id'];
[$c, $j] = $p('note', ['who' => 's:s1', 'type' => 'Parent meeting', 'text' => 'Father met about homework'], $tok['P']); pf_check($c === 200, 'student note saved');
[$c, $j] = $p('notes', null, $tok['CH'], '&who=t:K'); pf_check($c === 200 && count($j['notes']) === 1 && $j['notes'][0]['text'] === 'Late twice this week', 'chairman reads notes');
[, $j] = $api('load', null, $tok['K']); pf_check(!array_filter($j['records'], fn($r) => str_starts_with($r['k'], 'prof') || str_starts_with($r['k'], 'sal')), 'complaints and salaries never reach a teacher');
[$c, $j] = $api('sync', ['put' => [['k' => 'profNote', 'r' => 'x', 'd' => ['who' => 't:K']]], 'del' => [], 'log' => []], $tok['P']); pf_check(count($j['rejected']) === 1, 'notes cannot be written through sync');
[$c] = $p('delnote', ['id' => $nid], $tok['P']); pf_check($c === 200, 'note removed');
[, $j] = $p('notes', null, $tok['P'], '&who=t:K'); pf_check(!$j['notes'], 'removed note gone');

// ---- salary summary for the Admin dashboard ----
[$c, $j] = pf_http('/school/salary_api.php?a=summary', null, $tok['P']); pf_check($c === 200 && count($j['months']) === 6 && $j['months'][5]['staff'] === 1, 'salary summary: ' . json_encode($j['months'] ?? null));
[$c] = pf_http('/school/salary_api.php?a=summary', null, $tok['K']); pf_check($c === 403, 'teacher cannot see salary summary');
echo "Profile flow: OK\n";
