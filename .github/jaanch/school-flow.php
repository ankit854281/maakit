<?php
// ============================================================
//  जाँच — School ERP (maakit.in/school)
//
//  Ye dekhta hai:
//    panna, app.js, app.css khulte hain →
//    setup galat chaabi se nahi hota →
//    galat PIN ruka, 5 galti par taala →
//    Principal sab likh sakte hain →
//    teacher SIRF apna kaam likh sakta hai (apni diary, apni class
//    ki attendance, apne subject ke marks, apni chhutti "Pending") →
//    baaki sab mana → khaali {} waisa hi wapas aata hai →
//    Activity Log me har kaam → logout ke baad token bekaar.
//
//  Sirf CI ki database par chalta hai (sch_ tables saaf karta hai).
// ============================================================
require_once __DIR__ . '/../../config.php';
if (DB_NAME !== 'maakit_jaanch') throw new RuntimeException('CI database only');
require_once __DIR__ . '/../../inc/db.php';

function sf_check($ok, $m) { if (!$ok) throw new RuntimeException('School flow: ' . $m); }
function sf_http(string $path, $body = null, ?string $tok = null): array {
    $c = curl_init('http://127.0.0.1:8099' . $path);
    $h = ['X-School-Token: ' . ($tok ?? '')];
    $o = [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20, CURLOPT_PROXY => ''];
    if ($body !== null) { $h[] = 'Content-Type: application/json'; $o[CURLOPT_POST] = true; $o[CURLOPT_POSTFIELDS] = is_string($body) ? $body : json_encode($body); }
    $o[CURLOPT_HTTPHEADER] = $h;
    curl_setopt_array($c, $o);
    $raw = (string)curl_exec($c); $code = curl_getinfo($c, CURLINFO_HTTP_CODE); curl_close($c);
    sf_check(!preg_match('/Fatal error|Warning:|Uncaught/', $raw), "PHP error at $path");
    return [$code, $raw, json_decode($raw, true)];
}
function sf_api(string $a, $body = null, ?string $tok = null, string $q = ''): array { return sf_http('/school/api.php?a=' . $a . $q, $body, $tok); }

foreach (['sch_sessions', 'sch_users', 'sch_records', 'sch_log', 'sch_login_fail'] as $t) $pdo->exec("DELETE FROM $t");

// ---- panna ----
[$c, $html] = sf_http('/school/');
sf_check($c === 200 && str_contains($html, 'School ERP') && str_contains($html, 'Powered by'), 'page did not open');
[$c] = sf_http('/school/app.js');  sf_check($c === 200, 'app.js missing');
[$c] = sf_http('/school/app.css'); sf_check($c === 200, 'app.css missing');

// ---- setup ----
[, , $j] = sf_api('status');
sf_check($j['ok'] && $j['setup'] === true, 'status should ask for setup');
[$c] = sf_api('setup', ['key' => 'galat-chaabi', 'pin' => '246810']);
sf_check($c === 403, 'setup accepted a wrong key');
$pdo->prepare("INSERT INTO sch_users (uid, role, tid, pin_hash) VALUES ('principal','principal','P',?)")->execute([password_hash('246810', PASSWORD_DEFAULT)]);
[, , $j] = sf_api('status');
sf_check($j['setup'] === false, 'setup still asked after principal exists');

// ---- login ----
[$c] = sf_api('login', ['role' => 'principal', 'pin' => '111111']);
sf_check($c === 403, 'wrong PIN not refused');
[$c, , $j] = sf_api('login', ['role' => 'principal', 'pin' => '246810']);
sf_check($c === 200 && strlen($j['token']) === 64, 'principal login failed');
$P = $j['token'];
[$c] = sf_api('load', null, 'deadbeef');
sf_check($c === 401, 'bad token was accepted');

// ---- principal seeds data ----
$today = date('Y-m-d');
$seed = ['put' => [
    ['k' => 'settings', 'r' => 'main', 'd' => ['school' => 'SN Mishra Singapore International School', 'session' => '2026–27']],
    ['k' => 'classes',  'r' => 'main', 'd' => [['c' => '10th', 'ct' => 'MA'], ['c' => '9th', 'ct' => 'K']]],
    ['k' => 'teacher',  'r' => 'P',  'd' => ['name' => 'Prabhakar Dubey Sir', 'role' => 'Principal']],
    ['k' => 'teacher',  'r' => 'K',  'd' => ['name' => 'Kartikey Sir', 'role' => 'SST']],
    ['k' => 'teacher',  'r' => 'MA', 'd' => ['name' => "Mamta Ma'am", 'role' => 'Hindi']],
    ['k' => 'alloc',    'r' => 'main', 'd' => [['c' => '9th', 's' => 'Social Science', 't' => 'K', 'n' => 7], ['c' => '10th', 's' => 'Hindi', 't' => 'MA', 'n' => 6]]],
    ['k' => 'student',  'r' => 's1', 'd' => ['id' => 's1', 'adm' => '74', 'name' => 'Shivam Dubey', 'cls' => '9th']],
], 'log' => [['k' => 'Setup', 'x' => 'CI seed']]];
[$c, , $j] = sf_api('sync', $seed, $P);
sf_check($c === 200 && count($j['done']) === 7 && !$j['rejected'], 'principal could not save');

[$c] = sf_api('setpin', ['uid' => 't:K', 'pin' => '12'], $P);
sf_check($c === 400, 'short PIN accepted');
[$c] = sf_api('setpin', ['uid' => 't:K', 'pin' => '135790'], $P);
sf_check($c === 200, 'principal could not set teacher PIN');
[, , $j] = sf_api('status');
sf_check(count(array_filter($j['teachers'], fn($t) => $t['tid'] === 'K' && $t['pin'])) === 1, 'teacher PIN not shown as set');

// ---- teacher: only own work ----
[$c, , $j] = sf_api('login', ['role' => 'teacher', 'tid' => 'K', 'pin' => '135790']);
sf_check($c === 200, 'teacher login failed');
$K = $j['token'];
$try = function (array $put, bool $allowed, string $what) use ($K) {
    [, , $j] = sf_api('sync', ['put' => [$put]], $K);
    sf_check($allowed ? count($j['done']) === 1 : count($j['rejected']) === 1, $what);
};
$try(['k' => 'studAtt', 'r' => "$today|9th",  'd' => ['absent' => ['s1']]], true,  'class teacher could not mark own class');
$try(['k' => 'studAtt', 'r' => "$today|10th", 'd' => ['absent' => []]],     false, 'teacher marked another class');
$try(['k' => 'diary',   'r' => "$today|K|0",  'd' => ['cls' => '9th', 'topic' => 'Federalism', 'm' => (object)[]]], true, 'teacher could not write own diary');
$try(['k' => 'diary',   'r' => "$today|MA|0", 'd' => ['cls' => '10th', 'topic' => 'x']], false, "teacher wrote someone else's diary");
$try(['k' => 'settings','r' => 'main',        'd' => ['school' => 'HACKED']],  false, 'teacher changed settings');
$try(['k' => 'marks',   'r' => 'hy|9th|Social Science', 'd' => ['max' => 40, 'm' => ['s1' => 30]]], true, 'teacher could not enter own marks');
$try(['k' => 'marks',   'r' => 'hy|10th|Hindi',         'd' => ['max' => 40, 'm' => ['s1' => 40]]], false, 'teacher entered marks of another subject');
$try(['k' => 'leave',   'r' => 'L1', 'd' => ['id' => 'L1', 'tid' => 'K', 'status' => 'Pending', 'from' => $today, 'to' => $today]], true, 'teacher could not ask leave');
$try(['k' => 'leave',   'r' => 'L1', 'd' => ['id' => 'L1', 'tid' => 'K', 'status' => 'Approved', 'from' => $today, 'to' => $today]], false, 'teacher approved own leave');
$try(['k' => 'syl',     'r' => '9th|Social Science', 'd' => ['chapters' => [['n' => 'Ch 1', 'done' => null]]]], true, 'teacher could not update own syllabus');
[, , $j] = sf_api('sync', ['del' => [['k' => 'student', 'r' => 's1']]], $K);
sf_check(count($j['rejected']) === 1, 'teacher deleted a student');

// ---- principal sees it all, data comes back as written ----
[, $raw, $j] = sf_api('load', null, $P);
$keys = array_map(fn($r) => $r['k'] . ':' . $r['r'], $j['records']);
foreach (["studAtt:$today|9th", "diary:$today|K|0", 'marks:hy|9th|Social Science', 'leave:L1'] as $need) sf_check(in_array($need, $keys, true), "principal cannot see $need");
sf_check(!in_array("diary:$today|MA|0", $keys, true), 'forbidden diary reached the database');
sf_check(str_contains($raw, '"m":{}'), 'empty object {} came back changed');
foreach ($j['records'] as $r) if ($r['k'] === 'settings') sf_check($r['d']['school'] !== 'HACKED', 'settings were changed by a teacher');
$kinds = array_column($j['log'], 'k');
sf_check(in_array('Login', $kinds, true) && in_array('PIN', $kinds, true) && in_array('Setup', $kinds, true), 'activity log incomplete');
[, , $j2] = sf_api('since', null, $P, '&v=0&l=0');
sf_check($j2['ok'] && count($j2['records']) >= 10, 'since returned nothing');
[$c] = sf_api('day', null, $P, '&date=' . $today);
sf_check($c === 200, 'day fetch failed');

// ---- log export ----
[$c, $csv] = sf_api('logcsv', null, $P);
sf_check($c === 200 && str_contains($csv, 'Activity'), 'principal could not export log');
[$c] = sf_api('logcsv', null, $K);
sf_check($c === 403, 'teacher exported the log');

// ---- 4 ank ka PIN chalta hai; 15 kul galti par login band, Principal naya PIN de to khulta ----
[$c] = sf_api('setpin', ['uid' => 't:MA', 'pin' => '4826'], $P);
sf_check($c === 200, '4-digit PIN refused');
$pdo->exec("UPDATE sch_users SET strikes=14 WHERE uid='t:MA'");
[$c] = sf_api('login', ['role' => 'teacher', 'tid' => 'MA', 'pin' => '1111']);
sf_check($c === 423, 'no block after 15 wrong PINs');
[$c] = sf_api('login', ['role' => 'teacher', 'tid' => 'MA', 'pin' => '4826']);
sf_check($c === 423, 'blocked login opened with the right PIN');
sf_api('setpin', ['uid' => 't:MA', 'pin' => '5937'], $P);
[$c] = sf_api('login', ['role' => 'teacher', 'tid' => 'MA', 'pin' => '5937']);
sf_check($c === 200, 'new PIN from Principal did not unblock');

// ---- lock after 5 wrong PINs ----
for ($i = 0; $i < 5; $i++) sf_api('login', ['role' => 'teacher', 'tid' => 'K', 'pin' => '000000']);
[$c, , $j] = sf_api('login', ['role' => 'teacher', 'tid' => 'K', 'pin' => '135790']);
sf_check($c === 429, 'no lock after 5 wrong PINs');

// ---- logout ----
sf_api('logout', [], $P);
[$c] = sf_api('load', null, $P);
sf_check($c === 401, 'token still works after logout');

foreach (['sch_sessions', 'sch_users', 'sch_records', 'sch_log', 'sch_login_fail'] as $t) $pdo->exec("DELETE FROM $t");
echo "✅ School ERP: panna, login, taala, teacher ki seema, log, logout — sab theek\n";
