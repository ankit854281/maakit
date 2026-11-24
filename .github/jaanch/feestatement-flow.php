<?php
// जाँच — full fee statement (feecollect_api.php?a=statement): every session from 2024-25, card-only session, cancelled receipt left out, who may read.
require_once __DIR__ . '/../../config.php';
if (DB_NAME !== 'maakit_jaanch') throw new RuntimeException('CI database only');
require_once __DIR__ . '/../../inc/db.php';
require_once __DIR__ . '/../../school/inc.php';
function fsc($ok, $m) { if (!$ok) throw new RuntimeException('Fee statement flow: ' . $m); }
function fsh(string $path, ?string $tok): array { $c = curl_init('http://127.0.0.1:8099' . $path); curl_setopt_array($c, [CURLOPT_RETURNTRANSFER => true, CURLOPT_PROXY => '', CURLOPT_TIMEOUT => 30, CURLOPT_HTTPHEADER => ['X-School-Token: ' . ($tok ?? '')]]);
    $raw = (string)curl_exec($c); $code = curl_getinfo($c, CURLINFO_HTTP_CODE); curl_close($c); fsc(!preg_match('/Fatal error|Warning:|Uncaught/', $raw), "PHP error $path: " . substr($raw, 0, 300)); return [$code, json_decode($raw, true)]; }
function fsl(string $path, array $b): ?string { $c = curl_init('http://127.0.0.1:8099' . $path); curl_setopt_array($c, [CURLOPT_RETURNTRANSFER => true, CURLOPT_PROXY => '', CURLOPT_POST => true, CURLOPT_POSTFIELDS => json_encode($b), CURLOPT_HTTPHEADER => ['Content-Type: application/json']]); $j = json_decode((string)curl_exec($c), true); curl_close($c); return $j['token'] ?? null; }
$pdo = sch_db();
foreach (['sch_sessions', 'sch_users', 'sch_records', 'sch_log', 'sch_login_fail'] as $t) $pdo->exec("DELETE FROM $t");
$put = fn($k, $r, $d) => sch_rec_put($k, $r, $d, 'principal');
$put('settings', 'main', ['school' => 'FS School', 'session' => '2026-27', 'sessionStart' => '2026-04-01']);
$put('student', 'a10', ['id' => 'a10', 'name' => 'Astha Test', 'cls' => '10th', 'father' => 'R Test', 'adm' => '501', 'phone' => '9839100099']);
$put('teacher', 'K', ['name' => 'K Sir']);
// 2025-26 live ledger: April 1000 paid, May 1000 (300 concession, 200 paid) → 500 balance
$put('ofcLed', '2025-26|a10', ['session' => '2025-26', 'sid' => 'a10', 'cls' => '9th', 'oldFee' => 0, 'heads' => [
    ['i' => 1, 'm' => 4, 'name' => 'Tuition Fee', 'amt' => 100000, 'paid' => 100000, 'na' => false],
    ['i' => 2, 'm' => 5, 'name' => 'Tuition Fee', 'amt' => 100000, 'paid' => 50000, 'disc' => 30000, 'na' => false]]]);
// 2026-27 live: April paid, November due, March (future) due
$put('ofcLed', '2026-27|a10', ['session' => '2026-27', 'sid' => 'a10', 'cls' => '10th', 'oldFee' => 0, 'heads' => [
    ['i' => 1, 'm' => 4, 'name' => 'Tuition Fee', 'amt' => 120000, 'paid' => 120000, 'na' => false],
    ['i' => 2, 'm' => 11, 'name' => 'Tuition Fee', 'amt' => 120000, 'paid' => 0, 'na' => false],
    ['i' => 3, 'm' => 3, 'name' => 'Tuition Fee', 'amt' => 120000, 'paid' => 0, 'na' => false],
    ['i' => 4, 'm' => 3, 'name' => 'Exam Fee', 'amt' => 50000, 'paid' => 0, 'na' => true]]]);
$put('ofcRcpt', '2026-27|1-10', ['session' => '2026-27', 'no' => '1-10', 'n' => 10, 'sid' => 'a10', 'date' => '2026-04-10', 'received' => 120000, 'total' => 120000, 'mode' => 'Cash', 'lines' => [['1', 'Tuition Fee', 'April', 120000]], 'status' => 'Active']);
$put('ofcRcpt', '2026-27|1-11', ['session' => '2026-27', 'no' => '1-11', 'n' => 11, 'sid' => 'a10', 'date' => '2026-05-10', 'received' => 5000, 'total' => 5000, 'mode' => 'Cash', 'lines' => [], 'status' => 'Cancelled']);
$pin = fn($uid, $role, $tid, $p) => $pdo->prepare('INSERT INTO sch_users (uid, role, tid, pin_hash, must_change) VALUES (?,?,?,?,0)')->execute([$uid, $role, $tid, password_hash($p, PASSWORD_DEFAULT)]);
$pin('chairman', 'chairman', null, '6666'); $pin('t:K', 'teacher', 'K', '7777');
$CH = fsl('/school/api.php?a=login', ['role' => 'chairman', 'pin' => '6666']); fsc($CH, 'chairman login');
$K = fsl('/school/api.php?a=login', ['role' => 'teacher', 'tid' => 'K', 'pin' => '7777']); fsc($K, 'teacher login');
[$c] = fsh('/school/feecollect_api.php?a=statement&sid=a10', $K); fsc($c === 403, 'teacher cannot read fee statement');
[$c, $j] = fsh('/school/feecollect_api.php?a=statement&sid=a10', $CH); fsc($c === 200, 'chairman reads: ' . json_encode($j));
$S = array_column($j['sessions'], null, 'ses');
fsc(count($j['sessions']) === 3 && !empty($S['2024-25']['none']), 'three sessions, 2024-25 has no card');
fsc($S['2025-26']['oldDue'] === 0 && count($S['2025-26']['heads']) === 2 && $S['2025-26']['heads'][1]['disc'] === 30000, '2025-26 heads with concession');
fsc($S['2026-27']['oldDue'] === 50000, '2026-27 old due = 2025-26 balance 500: ' . $S['2026-27']['oldDue']);
fsc(count($S['2026-27']['receipts']) === 1 && $S['2026-27']['receipts'][0]['no'] === '1-10', 'cancelled receipt left out');
fsc($S['2026-27']['heads'][3]['na'] === true, 'not-active head is marked');
[$c] = fsh('/school/feecollect_api.php?a=statement&sid=nobody', $CH); fsc($c === 400, 'unknown child');
[$c, $j] = fsh('/school/feecollect_api.php?a=statement&sid=a10&from=2025-26', $CH); fsc(count($j['sessions']) === 2, 'from= starts later');
echo "Fee statement flow: OK\n";
