<?php
// जाँच — Learning Support (support_api.php): levels, pahade, reading log, support groups and their daily log, permissions.
require_once __DIR__ . '/../../config.php';
if (DB_NAME !== 'maakit_jaanch') throw new RuntimeException('CI database only');
require_once __DIR__ . '/../../inc/db.php';
require_once __DIR__ . '/../../school/inc.php';
function spc($ok, $m) { if (!$ok) throw new RuntimeException('Learning Support flow: ' . $m); }
function sph(string $path, $body, ?string $tok): array { $c = curl_init('http://127.0.0.1:8099' . $path); $h = ['X-School-Token: ' . ($tok ?? '')]; $o = [CURLOPT_RETURNTRANSFER => true, CURLOPT_PROXY => '', CURLOPT_TIMEOUT => 30];
    if ($body !== null) { $h[] = 'Content-Type: application/json'; $o[CURLOPT_POST] = true; $o[CURLOPT_POSTFIELDS] = json_encode($body); } $o[CURLOPT_HTTPHEADER] = $h; curl_setopt_array($c, $o);
    $raw = (string)curl_exec($c); $code = curl_getinfo($c, CURLINFO_HTTP_CODE); curl_close($c); spc(!preg_match('/Fatal error|Warning:|Uncaught/', $raw), "PHP error $path: " . substr($raw, 0, 200)); return [$code, json_decode($raw, true)]; }
$pdo = sch_db();
foreach (['sch_sessions', 'sch_users', 'sch_records', 'sch_log', 'sch_login_fail'] as $t) $pdo->exec("DELETE FROM $t");
$put = fn($k, $r, $d) => sch_rec_put($k, $r, $d, 'principal');
$put('settings', 'main', ['school' => 'LS School', 'session' => '2026-27', 'sessionStart' => '2026-04-01']);
$put('teacher', 'K', ['name' => 'Kartikey Sir']); $put('teacher', 'MA', ['name' => 'Mamta']);
$put('classes', 'main', [['c' => '7th', 'ct' => 'K'], ['c' => '8th', 'ct' => 'MA']]);
$put('alloc', 'main', [['c' => '7th', 's' => 'Maths', 't' => 'K', 'n' => 6], ['c' => '8th', 's' => 'Hindi', 't' => 'MA', 'n' => 6]]);
$put('student', 's7a', ['id' => 's7a', 'name' => 'Aarav', 'cls' => '7th']); $put('student', 's7b', ['id' => 's7b', 'name' => 'Bina', 'cls' => '7th']); $put('student', 's8a', ['id' => 's8a', 'name' => 'Chetan', 'cls' => '8th']);
$pin = fn($uid, $role, $tid, $p) => $pdo->prepare('INSERT INTO sch_users (uid, role, tid, pin_hash, must_change) VALUES (?,?,?,?,0)')->execute([$uid, $role, $tid, password_hash($p, PASSWORD_DEFAULT)]);
$pin('principal', 'principal', 'P', '2468'); $pin('t:K', 'teacher', 'K', '7777'); $pin('t:MA', 'teacher', 'MA', '5555'); $pin('chairman', 'chairman', null, '6666');
$tok = [];
foreach ([['P', ['role' => 'principal', 'pin' => '2468']], ['K', ['role' => 'teacher', 'tid' => 'K', 'pin' => '7777']], ['MA', ['role' => 'teacher', 'tid' => 'MA', 'pin' => '5555']], ['CH', ['role' => 'chairman', 'pin' => '6666']]] as [$n, $b]) {
    [, $j] = sph('/school/api.php?a=login', $b, null); $tok[$n] = $j['token'] ?? null; spc($tok[$n], "login $n");
}
[$c] = sph('/school/support_api.php?a=state', null, null); spc($c === 401, 'no login → 401');
[$c, $j] = sph('/school/support_api.php?a=level', ['sid' => 's7a', 'skill' => 'maths', 'v' => 2], $tok['K']); spc($c === 200 && $j['rec']['lv']['maths'] === 2 && count($j['rec']['hist']) === 1, 'teacher of 7th sets a level');
[$c] = sph('/school/support_api.php?a=level', ['sid' => 's7a', 'skill' => 'maths', 'v' => 2], $tok['K']); $r = sch_rec_get('tchSkill', 's7a'); spc(count($r['hist']) === 1, 'same level again adds no history');
[$c] = sph('/school/support_api.php?a=level', ['sid' => 's8a', 'skill' => 'maths', 'v' => 1], $tok['K']); spc($c === 403, 'K does not teach 8th → 403');
[$c] = sph('/school/support_api.php?a=level', ['sid' => 's7a', 'skill' => 'dance', 'v' => 1], $tok['K']); spc($c === 400, 'unknown skill refused');
[$c] = sph('/school/support_api.php?a=level', ['sid' => 's7a', 'skill' => 'maths', 'v' => 9], $tok['K']); spc($c === 400, 'level above 5 refused');
[$c, $j] = sph('/school/support_api.php?a=levels', ['cls' => '7th', 'skill' => 'readH', 'vals' => ['s7a' => 1, 's7b' => 4, 's8a' => 2]], $tok['K']); spc($c === 200 && $j['saved'] === 2, 'bulk levels: only children of that class: ' . json_encode($j));
[$c] = sph('/school/support_api.php?a=levels', ['cls' => '8th', 'skill' => 'readH', 'vals' => ['s8a' => 2]], $tok['K']); spc($c === 403, 'bulk levels of another class → 403');
[$c, $j] = sph('/school/support_api.php?a=tables', ['sid' => 's7a', 'n' => 7, 'on' => 1], $tok['K']); spc($c === 200 && $j['rec']['tables'] === [7], 'pahada 7 known');
[$c, $j] = sph('/school/support_api.php?a=tables', ['sid' => 's7a', 'n' => 7, 'on' => 0], $tok['K']); spc($j['rec']['tables'] === [], 'pahada 7 un-marked');
[$c] = sph('/school/support_api.php?a=tables', ['sid' => 's7a', 'n' => 25, 'on' => 1], $tok['K']); spc($c === 400, 'pahada 25 refused');
[$c, $j] = sph('/school/support_api.php?a=book', ['sid' => 's7a', 'title' => 'Panchtantra', 'pages' => 4], $tok['K']); spc($c === 200 && $j['rec']['books'][0]['pages'] === 4, 'reading log');
[$c, $j] = sph('/school/support_api.php?a=group', ['name' => 'Pahada group', 'skill' => 'maths', 'kids' => ['s7a', 's8a', 'nobody']], $tok['K']); spc($c === 200 && $j['group']['kids'] === ['s7a', 's8a'] && $j['group']['owner'] === 'K', 'group with children of two classes (unknown child dropped)'); $gid = $j['id'];
[$c] = sph('/school/support_api.php?a=group', ['id' => $gid, 'name' => 'Taken over', 'skill' => 'maths', 'kids' => ['s8a']], $tok['MA']); spc($c === 403, 'another teacher cannot change the group');
[$c] = sph('/school/support_api.php?a=glog', ['gid' => $gid, 'did' => 'Pahada 7', 'res' => ['s7a' => 1, 's8a' => 0]], $tok['MA']); spc($c === 403, 'another teacher cannot write the group day');
[$c] = sph('/school/support_api.php?a=glog', ['gid' => $gid, 'did' => '', 'res' => []], $tok['K']); spc($c === 400, 'empty "what was done" refused');
[$c] = sph('/school/support_api.php?a=glog', ['gid' => $gid, 'did' => 'Pahada 7', 'res' => ['s7a' => 1, 's8a' => 0, 'x' => 1]], $tok['K']); spc($c === 200, 'owner writes the day');
[$c, $j] = sph('/school/support_api.php?a=state', null, $tok['MA']); spc($c === 200 && count($j['logs']) === 1 && $j['logs'][0]['res'] === ['s7a' => 1, 's8a' => 0] && isset($j['groups'][$gid]) && $j['skills']['s7a']['lv']['readH'] === 1, 'state: groups, logs (outsider child dropped), skills');
[$c] = sph('/school/support_api.php?a=glog', ['gid' => $gid, 'did' => 'Revision', 'res' => ['s7a' => 1]], $tok['P']); spc($c === 200, 'Principal can write any group');
[$c] = sph('/school/support_api.php?a=level', ['sid' => 's8a', 'skill' => 'gram', 'v' => 3], $tok['P']); spc($c === 200, 'Principal sets any child');
[$c] = sph('/school/support_api.php?a=level', ['sid' => 's7a', 'skill' => 'gram', 'v' => 3], $tok['CH']); spc($c === 403, 'Chairman only views');
[$c] = sph('/school/support_api.php?a=state', null, $tok['CH']); spc($c === 200, 'Chairman reads');
[$c] = sph('/school/support_api.php?a=delgroup', ['id' => $gid], $tok['MA']); spc($c === 403, 'another teacher cannot remove the group');
[$c] = sph('/school/support_api.php?a=delgroup', ['id' => $gid], $tok['K']); spc($c === 200 && sch_rec_get('tchGrp', $gid) === null, 'owner removes the group');
$q = $pdo->query("SELECT COUNT(*) FROM sch_records WHERE k LIKE 'tch%' AND k IN ('tchSkill','tchGrp','tchGrpLog')"); spc((int)$q->fetchColumn() >= 3, 'records kept');
echo "Learning Support flow: OK\n";
