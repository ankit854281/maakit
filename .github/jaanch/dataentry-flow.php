<?php
// ============================================================
//  जाँच — Data Entry login + "who entered what" (12 Nov 2026)
//
//  dataentry: PIN 1234 then own PIN → no whole-school load / sync →
//  marks a class the teacher did not mark (same studAtt, name kept) →
//  homework list → staff attendance allowed → entry log shows who saved
//  each record; only Principal / Chairman open it.
// ============================================================
require_once __DIR__ . '/../../config.php';
if (DB_NAME !== 'maakit_jaanch') throw new RuntimeException('CI database only');
require_once __DIR__ . '/../../inc/db.php';
require_once __DIR__ . '/../../school/inc.php';

function de_check($ok, $m) { if (!$ok) throw new RuntimeException('Data entry flow: ' . $m); }
function de_http(string $path, $body = null, ?string $tok = null): array {
    $c = curl_init('http://127.0.0.1:8099' . $path);
    $h = ['X-School-Token: ' . ($tok ?? '')]; $o = [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30, CURLOPT_PROXY => ''];
    if ($body !== null) { $h[] = 'Content-Type: application/json'; $o[CURLOPT_POST] = true; $o[CURLOPT_POSTFIELDS] = json_encode($body); }
    $o[CURLOPT_HTTPHEADER] = $h; curl_setopt_array($c, $o);
    $raw = (string)curl_exec($c); $code = curl_getinfo($c, CURLINFO_HTTP_CODE); curl_close($c);
    de_check(!preg_match('/Fatal error|Warning:|Uncaught|Deprecated/', $raw), "PHP error at $path: " . substr($raw, 0, 300));
    return [$code, json_decode($raw, true), $raw];
}
$api = fn($a, $b = null, $t = null) => de_http('/school/api.php?a=' . $a, $b, $t);
$de  = fn($a, $b = null, $t = null, $q = '') => de_http('/school/dataentry_api.php?a=' . $a . $q, $b, $t);

foreach (['sch_sessions', 'sch_users', 'sch_records', 'sch_log', 'sch_login_fail'] as $t) $pdo->exec("DELETE FROM $t");
$put = fn($k, $r, $d, $by = 'principal') => sch_rec_put($k, $r, $d, $by);
$put('settings', 'main', ['school' => 'DE Test School', 'session' => '2026-27', 'dataentryName' => 'Shubh Upadhyay']);
$put('teacher', 'K', ['name' => 'Kartikey Sir']); $put('teacher', 'P', ['name' => 'Prabhakar Dubey Sir']);
$put('classes', 'main', [['c' => '9th', 'ct' => 'K'], ['c' => '10th', 'ct' => '']]);
foreach ([['s1', 'Aman', '9th', '1'], ['s2', 'Bina', '9th', '2'], ['s3', 'Chandu', '10th', '1']] as [$id, $n, $c, $r]) $put('student', $id, ['id' => $id, 'name' => $n, 'cls' => $c, 'rollno' => $r]);
$d = date('Y-m-d'); if (date('w') === '0') $d = date('Y-m-d', strtotime('-1 day'));
$put('studAtt', "$d|10th", ['absent' => [], 'by' => 'Kartikey Sir', 'at' => 1], 't:K');
$put('diary', "$d|K|0", ['cls' => '9th', 'subj' => 'Social Science', 'topic' => 'Maps', 'hw' => 'Draw the map of India', 'hwDate' => $d], 't:K');
$pdo->prepare('INSERT INTO sch_users (uid, role, tid, pin_hash, must_change) VALUES (?,?,?,?,0)')->execute(['principal', 'principal', 'P', password_hash('2468', PASSWORD_DEFAULT)]);
$pdo->prepare('INSERT INTO sch_users (uid, role, tid, pin_hash, must_change) VALUES (?,?,?,?,0)')->execute(['t:K', 'teacher', 'K', password_hash('7777', PASSWORD_DEFAULT)]);
$pdo->prepare('INSERT INTO sch_users (uid, role, tid, pin_hash, must_change) VALUES (?,?,?,?,0)')->execute(['chairman', 'chairman', null, password_hash('6666', PASSWORD_DEFAULT)]);
[, $j] = $api('login', ['role' => 'principal', 'pin' => '2468']); $P = $j['token'];
[, $j] = $api('login', ['role' => 'teacher', 'tid' => 'K', 'pin' => '7777']); $K = $j['token'];
[, $j] = $api('login', ['role' => 'chairman', 'pin' => '6666']); $CH = $j['token'];

// ---- login ----
[$c, $j] = $api('login', ['role' => 'dataentry', 'pin' => '1234']); de_check($c === 200 && $j['mustChange'] === true && $j['me']['label'] === 'Shubh Upadhyay (Data Entry)', 'first login with 1234: ' . json_encode($j));
[$c] = $de('day', null, $j['token']); de_check($c === 428, 'must choose own PIN first');
[$c] = $api('setpin', ['old' => '1234', 'pin' => '5151'], $j['token']); de_check($c === 200, 'own PIN chosen');
[$c, $j] = $api('login', ['role' => 'dataentry', 'pin' => '5151']); $DE = $j['token']; de_check($c === 200 && $j['mustChange'] === false, 'login with own PIN');
[$c] = $api('load', null, $DE); de_check($c === 403, 'no whole-school load');
[, $j] = $api('sync', ['put' => [['k' => 'studAtt', 'r' => "$d|9th", 'd' => ['absent' => []]]], 'del' => [], 'log' => []], $DE); de_check($c !== 200 || !empty($j['rejected']) || sch_rec_get('studAtt', "$d|9th") === null, 'no sync writes');
de_check(sch_rec_get('studAtt', "$d|9th") === null, 'sync did not write');

// ---- day / mark ----
[$c] = $de('day', null, $K); de_check($c === 403, 'teacher cannot open data entry');
[$c, $j] = $de('day', null, $DE, "&date=$d"); de_check($c === 200 && count($j['classes']) === 2, 'day opens');
$by = array_column($j['classes'], null, 'c'); de_check($by['9th']['att'] === null && $by['10th']['att'] !== null && count($by['9th']['students']) === 2, '9th not marked, 10th marked');
[$c] = $de('day', null, $DE, '&date=' . date('Y-m-d', strtotime('+1 day'))); de_check($c === 400, 'no future date');
[$c] = $de('att', ['date' => $d, 'cls' => '9th', 'st' => ['s1' => 'A', 's2' => 'L', 's3' => 'A', 'zz' => 'A']], $DE); de_check($c === 200, 'saved');
$r = sch_rec_get('studAtt', "$d|9th"); de_check($r['absent'] === ['s1', 's2'] && $r['st'] === ['s2' => 'L'] && $r['by'] === 'Shubh Upadhyay (Data Entry)', 'same record, other class child ignored: ' . json_encode($r));
[$c] = $de('att', ['date' => $d, 'cls' => '11th', 'st' => []], $DE); de_check($c === 400, 'unknown class');
de_check((int)$pdo->query("SELECT COUNT(*) FROM sch_log WHERE k='Student Attendance' AND x LIKE 'Class 9th%entered by Data Entry%'")->fetchColumn() === 1, 'logged');

// ---- month: day by day ----
[$c, $j] = $de('month', null, $DE, '&ym=' . substr($d, 0, 7)); $day = array_values(array_filter($j['days'], fn($x) => $x['date'] === $d))[0] ?? null;
de_check($c === 200 && $day && $day['marked'] === 2 && $day['classes'] === 2 && $day['total'] === 3 && $day['present'] === 1 && $day['absent'] === 1 && $day['leave'] === 1, 'day totals: ' . json_encode($day));

// ---- homework / staff attendance ----
[$c, $j] = $de('homework', null, $DE, "&date=$d"); de_check($c === 200 && count($j['items']) === 1 && $j['items'][0]['teacher'] === 'Kartikey Sir', 'homework list');
[$c] = de_http('/school/staffatt_api.php?a=month&ym=' . date('Y-m'), null, $DE); de_check($c === 200, 'data entry opens staff attendance');

// ---- who entered what ----
[$c] = de_http('/school/entrylog_api.php?a=list', null, $DE); de_check($c === 403, 'data entry cannot open the entry log');
[$c] = de_http('/school/entrylog_api.php?a=list', null, $K); de_check($c === 403, 'teacher cannot open the entry log');
[$c, $j] = de_http('/school/entrylog_api.php?a=list&days=1', null, $CH); de_check($c === 200, 'Chairman opens the entry log');
$row = array_values(array_filter($j['rows'], fn($x) => $x['kind'] === 'Student attendance' && str_contains($x['what'], '9th')))[0] ?? null;
de_check($row && $row['who'] === 'Shubh Upadhyay (Data Entry)', 'entry log shows who saved 9th attendance: ' . json_encode($row));
$row = array_values(array_filter($j['rows'], fn($x) => $x['kind'] === 'Class work / homework diary'))[0] ?? null;
de_check($row && $row['who'] === 'Kartikey Sir (Teacher)', 'diary by the teacher');
[, $j] = de_http('/school/entrylog_api.php?a=list&days=1&who=dataentry', null, $P); de_check(count($j['rows']) === 1 && count($j['people']) >= 3, 'filter by person');

echo "Data entry flow: OK\n";
