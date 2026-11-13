<?php
// जाँच — whole-school fee calling from the Teacher App (owner, 13 Nov 2026: Adarsh Dubey Sir, settings.feeCallers, default 'AD')
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
$put('teacher', 'AD', ['name' => 'Adarsh Dubey Sir']); $put('teacher', 'K', ['name' => 'Kartikey Sir']); $put('teacher', 'MA', ['name' => 'Mamta']);
$put('classes', 'main', [['c' => '9th', 'ct' => 'K'], ['c' => '10th', 'ct' => 'MA']]);
$put('student', 'f1', ['id' => 'f1', 'name' => 'Aman', 'cls' => '10th', 'phone' => '9839100011']);
$pin = fn($uid, $tid, $p) => $pdo->prepare('INSERT INTO sch_users (uid, role, tid, pin_hash, must_change) VALUES (?,?,?,?,0)')->execute([$uid, 'teacher', $tid, password_hash($p, PASSWORD_DEFAULT)]);
$pin('t:AD', 'AD', '1111'); $pin('t:K', 'K', '2222');
[, $j] = fch('/school/api.php?a=login', ['role' => 'teacher', 'tid' => 'AD', 'pin' => '1111'], null); $AD = $j['token'];
[, $j] = fch('/school/api.php?a=login', ['role' => 'teacher', 'tid' => 'K', 'pin' => '2222'], null); $K = $j['token'];
[$c] = fch('/school/teacher_api.php?a=fees&cls=10th', null, $AD); fcc($c === 200, 'Adarsh Sir (default fee caller) opens any class');
[$c] = fch('/school/teacher_api.php?a=fees&cls=10th', null, $K); fcc($c === 403, 'another class teacher cannot open 10th');
[$c] = fch('/school/teacher_api.php?a=fees&cls=9th', null, $K); fcc($c === 200, 'class teacher still opens own class');
[$c, $j] = fch('/school/teacher_api.php?a=feecall', ['sid' => 'f1', 'outcome' => 'Not answered'], $AD); fcc($c !== 403 && str_contains($j['error'] ?? 'fee card', 'fee card'), 'Adarsh Sir is allowed to save a call (this synthetic child has no fee card): ' . json_encode($j));
[$c] = fch('/school/teacher_api.php?a=feecall', ['sid' => 'f1', 'outcome' => 'Not answered'], $K); fcc($c === 403, 'other class teacher cannot call for 10th');
$put('settings', 'main', ['school' => 'FC School', 'session' => '2026-27', 'sessionStart' => '2026-04-01', 'feeCallers' => ['K']]);
[$c] = fch('/school/teacher_api.php?a=fees&cls=10th', null, $AD); fcc($c === 403, 'removed from feeCallers → no access');
[$c] = fch('/school/teacher_api.php?a=fees&cls=10th', null, $K); fcc($c === 200, 'new fee caller gets every class');
echo "Fee caller flow: OK\n";
