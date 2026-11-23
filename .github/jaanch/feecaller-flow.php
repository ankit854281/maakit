<?php
// जाँच — whole-school fee calling from the Teacher App (settings.feeCallers; default was Adarsh Sir 'AD', from 20 Nov 2026 Rati Ma'am 'RT')
//         + Homework share PG–10th for Rati Ma'am (teacher_api.php?a=hwall / hwshared)
require_once __DIR__ . '/../../config.php';
if (DB_NAME !== 'maakit_jaanch') throw new RuntimeException('CI database only');
require_once __DIR__ . '/../../inc/db.php';
require_once __DIR__ . '/../../school/inc.php';
function fcc($ok, $m) { if (!$ok) throw new RuntimeException('Fee caller flow: ' . $m); }
function fch(string $path, $body, ?string $tok): array { $c = curl_init('http://127.0.0.1:8099' . $path); $h = ['X-School-Token: ' . ($tok ?? '')]; $o = [CURLOPT_RETURNTRANSFER => true, CURLOPT_PROXY => '', CURLOPT_TIMEOUT => 30];
    if ($body !== null) { $h[] = 'Content-Type: application/json'; $o[CURLOPT_POST] = true; $o[CURLOPT_POSTFIELDS] = json_encode($body); } $o[CURLOPT_HTTPHEADER] = $h; curl_setopt_array($c, $o);
    $raw = (string)curl_exec($c); $code = curl_getinfo($c, CURLINFO_HTTP_CODE); curl_close($c); fcc(!preg_match('/Fatal error|Warning:|Uncaught/', $raw), "PHP error $path: " . substr($raw, 0, 200)); return [$code, json_decode($raw, true)]; }
foreach (['sch_sessions', 'sch_users', 'sch_records', 'sch_log', 'sch_login_fail'] as $t) $pdo->exec("DELETE FROM $t");
$put = fn($k, $r, $d) => sch_rec_put($k, $r, $d, 'principal');
$put('settings', 'main', ['school' => 'FC School', 'session' => '2026-27', 'sessionStart' => '2026-04-01']);
$put('teacher', 'AD', ['name' => 'Adarsh Dubey Sir']); $put('teacher', 'K', ['name' => 'Kartikey Sir']); $put('teacher', 'RT', ['name' => "Rati Ma'am"]); $put('teacher', 'RA2', ['name' => 'Rati Mishra']); $put('teacher', 'MA', ['name' => 'Mamta']); $put('teacher', 'AR', ['name' => 'Aarati Devi']); $put('teacher', 'PS9', ['name' => "Palak Srivastava Ma'am"]);
$put('classes', 'main', [['c' => '9th', 'ct' => 'K'], ['c' => '10th', 'ct' => 'MA']]);
$put('student', 'f1', ['id' => 'f1', 'name' => 'Aman', 'cls' => '10th', 'phone' => '9839100011']);
$pin = fn($uid, $tid, $p) => $pdo->prepare('INSERT INTO sch_users (uid, role, tid, pin_hash, must_change) VALUES (?,?,?,?,0)')->execute([$uid, 'teacher', $tid, password_hash($p, PASSWORD_DEFAULT)]);
$pin('t:AD', 'AD', '1111'); $pin('t:K', 'K', '2222'); $pin('t:RA2', 'RA2', '3333'); $pin('t:RT', 'RT', '4444'); $pin('t:AR', 'AR', '5555');
[, $j] = fch('/school/api.php?a=login', ['role' => 'teacher', 'tid' => 'AD', 'pin' => '1111'], null); $AD = $j['token'];
[, $j] = fch('/school/api.php?a=login', ['role' => 'teacher', 'tid' => 'K', 'pin' => '2222'], null); $K = $j['token'];
[, $j] = fch('/school/api.php?a=login', ['role' => 'teacher', 'tid' => 'RT', 'pin' => '4444'], null); $RT = $j['token'];
[$c] = fch('/school/teacher_api.php?a=fees&cls=10th', null, $RT); fcc($c === 200, "Rati Ma'am (default fee caller from 20 Nov) opens any class");
[$c] = fch('/school/teacher_api.php?a=fees&cls=10th', null, $AD); fcc($c === 403, 'Adarsh Sir is no longer the fee caller');
[, $j] = fch('/school/api.php?a=login', ['role' => 'teacher', 'tid' => 'RA2', 'pin' => '3333'], null);
[$c] = fch('/school/teacher_api.php?a=fees&cls=10th', null, $j['token']); fcc($c === 200, 'Rati found by name even when her id is not RT');
[, $j] = fch('/school/api.php?a=login', ['role' => 'teacher', 'tid' => 'AR', 'pin' => '5555'], null);
[$c] = fch('/school/teacher_api.php?a=fees&cls=10th', null, $j['token']); fcc($c === 403, '"Aarati" is not Rati');
$pin('t:PS9', 'PS9', '6666'); [, $j] = fch('/school/api.php?a=login', ['role' => 'teacher', 'tid' => 'PS9', 'pin' => '6666'], null); $PS = $j['token'];
[$c] = fch('/school/teacher_api.php?a=fees&cls=10th', null, $PS); fcc($c === 200, "Palak Ma'am also calls every class (owner 23 Nov 2026)");
[$c] = fch('/school/calls_api.php?a=state', null, $PS); fcc($c !== 403, "Palak Ma'am opens parent calls");
// Homework share
$put('classes', 'main', [['c' => '9th', 'ct' => 'K'], ['c' => '10th', 'ct' => 'MA'], ['c' => '11th', 'ct' => 'K']]);
$d0 = date('Y-m-d');
$put('diary', "$d0|K|1", ['cls' => '9th', 'subj' => 'Maths', 'classwork' => 'Ex 4.1 Q1-5', 'hw' => 'Ex 4.1 Q6-9', 'hwDate' => $d0]);
$put('diary', "$d0|MA|2", ['cls' => '10th', 'subj' => 'Science', 'topic' => 'Light', 'hw' => '']);
$put('diary', "$d0|K|3", ['cls' => '11th', 'subj' => 'Physics', 'classwork' => 'Units', 'hw' => 'Read ch 1']);
[$c, $j] = fch('/school/teacher_api.php?a=hwall&date=' . $d0, null, $RT); fcc($c === 200 && count($j['items']) === 2 && $j['items'][0]['cls'] === '9th' && $j['items'][1]['cw'] === 'Light' && $j['classes'] === ['9th', '10th'], 'hwall: PG–10th only, class work falls back to topic ' . json_encode($j));
[$c] = fch('/school/teacher_api.php?a=hwall&date=' . $d0, null, $K); fcc($c === 403, 'other teachers cannot open homework share');
[$c] = fch('/school/teacher_api.php?a=hwall&date=' . $d0, null, $PS); fcc($c === 403, "homework share stays Rati Ma'am only, not Palak Ma'am");
[$c, $j] = fch('/school/teacher_api.php?a=hwshared', ['date' => $d0, 'cls' => '9th', 'on' => 1], $RT); fcc($c === 200 && !empty($j['shared']['at']), 'mark shared');
[$c, $j] = fch('/school/teacher_api.php?a=hwall&date=' . $d0, null, $RT); fcc(isset($j['shared']['9th']), 'shared mark comes back');
[$c] = fch('/school/teacher_api.php?a=fees&cls=10th', null, $K); fcc($c === 403, 'another class teacher cannot open 10th');
[$c] = fch('/school/teacher_api.php?a=fees&cls=9th', null, $K); fcc($c === 200, 'class teacher still opens own class');
[$c, $j] = fch('/school/teacher_api.php?a=feecall', ['sid' => 'f1', 'outcome' => 'Not answered'], $RT); fcc($c !== 403 && str_contains($j['error'] ?? 'fee card', 'fee card'), 'Rati Ma\'am is allowed to save a call (this synthetic child has no fee card): ' . json_encode($j));
[$c] = fch('/school/teacher_api.php?a=feecall', ['sid' => 'f1', 'outcome' => 'Not answered'], $K); fcc($c === 403, 'other class teacher cannot call for 10th');
$put('settings', 'main', ['school' => 'FC School', 'session' => '2026-27', 'sessionStart' => '2026-04-01', 'feeCallers' => ['K']]);
[$c] = fch('/school/teacher_api.php?a=fees&cls=10th', null, $RT); fcc($c === 403, 'removed from feeCallers → no access');
[$c] = fch('/school/teacher_api.php?a=fees&cls=10th', null, $K); fcc($c === 200, 'new fee caller gets every class');
echo "Fee caller flow: OK\n";
