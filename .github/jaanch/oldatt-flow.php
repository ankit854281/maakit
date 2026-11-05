<?php
// ============================================================
//  जाँच — Old ERP attendance import (school/oldatt_api.php)
//
//  Sirf Principal → bachche Scholar No / Child Id / naam se milte
//  hain → jis din teacher ne nayi ERP me haziri lagayi woh kabhi
//  nahi badalta → Sunday / chhutti / class holiday / bina P-A-L
//  wala din chhoot jaata hai → L = leave → dobara chalane par kuch
//  double nahi, aur pichhle import wala din badla ho to refresh.
// ============================================================
require_once __DIR__ . '/../../config.php';
if (DB_NAME !== 'maakit_jaanch') throw new RuntimeException('CI database only');
require_once __DIR__ . '/../../inc/db.php';
require_once __DIR__ . '/../../school/inc.php';

function oa_check($ok, $m) { if (!$ok) throw new RuntimeException('Old ERP attendance: ' . $m); }
function oa_http(string $path, $body = null, ?string $tok = null): array {
    $c = curl_init('http://127.0.0.1:8099' . $path);
    $h = ['X-School-Token: ' . ($tok ?? '')]; $o = [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30, CURLOPT_PROXY => ''];
    if ($body !== null) { $h[] = 'Content-Type: application/json'; $o[CURLOPT_POST] = true; $o[CURLOPT_POSTFIELDS] = json_encode($body); }
    $o[CURLOPT_HTTPHEADER] = $h; curl_setopt_array($c, $o);
    $raw = (string)curl_exec($c); $code = curl_getinfo($c, CURLINFO_HTTP_CODE); curl_close($c);
    oa_check(!preg_match('/Fatal error|Warning:|Uncaught|Deprecated|Notice:/', $raw), "PHP error at $path: " . substr($raw, 0, 300));
    return [$code, json_decode($raw, true)];
}
$api = fn($a, $b = null, $t = null) => oa_http('/school/api.php?a=' . $a, $b, $t);
$oa = fn($a, $b, $t) => oa_http('/school/oldatt_api.php?a=' . $a, $b, $t);

foreach (['sch_sessions', 'sch_users', 'sch_records', 'sch_log', 'sch_login_fail'] as $t) $pdo->exec("DELETE FROM $t");
$put = fn($k, $r, $d) => sch_rec_put($k, $r, $d, 'principal');
$put('settings', 'main', ['school' => 'Old Att Test', 'session' => '2026–27', 'sessionStart' => '2026-04-01']);
$put('teacher', 'K', ['name' => 'Kartikey Sir']);
$put('classes', 'main', [['c' => '9th', 'ct' => 'K'], ['c' => 'LKG', 'ct' => '']]);
$put('student', 'erp-snmishra-1', ['id' => 'erp-snmishra-1', 'name' => 'ADITYA PAL', 'cls' => '9th', 'adm' => '1142', 'childId' => '600372']);
$put('student', 'erp-snmishra-2', ['id' => 'erp-snmishra-2', 'name' => 'Anchal Yadav', 'cls' => '9th', 'adm' => '24517']);
$put('student', 'erp-snmishra-3', ['id' => 'erp-snmishra-3', 'name' => 'Ansh Jaiswal', 'cls' => '9th', 'adm' => 'SDSD']);
$put('student', 'erp-snmishra-4', ['id' => 'erp-snmishra-4', 'name' => 'Bina New', 'cls' => '9th', 'adm' => '9999']);
$put('cal', 'h1', ['id' => 'h1', 'date' => '2026-04-08', 'to' => '', 'title' => 'Holiday', 'type' => 'Holiday']);
$put('clsHol', '2026-04-09|9th', ['cls' => '9th', 'date' => '2026-04-09', 'title' => 'Class trip']);
$put('studAtt', '2026-04-06|9th', ['absent' => [], 'by' => 'Kartikey Sir', 'at' => 1]);          // teacher marked → never changed
$pin = fn($uid, $role, $tid, $p) => $pdo->prepare('INSERT INTO sch_users (uid, role, tid, pin_hash, must_change) VALUES (?,?,?,?,0)')->execute([$uid, $role, $tid, password_hash($p, PASSWORD_DEFAULT)]);
$pin('principal', 'principal', 'P', '2468'); $pin('t:K', 'teacher', 'K', '7777'); $pin('chairman', 'chairman', null, '6666');
[, $j] = $api('login', ['role' => 'principal', 'pin' => '2468']); $P = $j['token'];
[, $j] = $api('login', ['role' => 'teacher', 'tid' => 'K', 'pin' => '7777']); $K = $j['token'];
[, $j] = $api('login', ['role' => 'chairman', 'pin' => '6666']); $CH = $j['token'];

// days: Sun 5, Mon 6 (teacher has it), Tue 7, Wed 8 (holiday), Thu 9 (class holiday), Fri 10 (nobody marked), Sat 11
$days = ['2026-04-05', '2026-04-06', '2026-04-07', '2026-04-08', '2026-04-09', '2026-04-10', '2026-04-11'];
$body = ['cls' => '9th', 'days' => $days, 'kids' => [
    ['scholar' => '1142', 'child' => '600372', 'name' => 'ADITYA PAL', 'codes' => 'S,A,A,P,P,-,P'],
    ['scholar' => '', 'child' => '', 'name' => 'Anchal  Yadav', 'codes' => 'S,P,L,P,P,-,A'],          // matched by name
    ['scholar' => 'SDSD', 'child' => '', 'name' => 'Ansh Jaiswal', 'codes' => 'S,P,P,P,P,-,P'],
    ['scholar' => '777', 'child' => '', 'name' => 'Gone Child', 'codes' => 'S,P,A,P,P,-,P'],           // not in new ERP
]];
[$c] = $oa('preview', $body, $K); oa_check($c === 403, 'a teacher cannot import');
[$c] = $oa('preview', $body, $CH); oa_check($c === 403, 'Chairman cannot import');
[$c, $j] = $oa('preview', $body, $P);
oa_check($c === 200 && $j['matched'] === 3 && $j['unmatched'] === ['Gone Child (Scholar 777)'], 'children matched by scholar / name (' . json_encode($j) . ')');
oa_check($j['add'] === 2 && $j['kept'] === 1 && $j['holiday'] === 2 && $j['notMarked'] === 2, 'only Tue 7 and Sat 11 are added (' . json_encode($j) . ')');
oa_check(sch_rec_get('studAtt', '2026-04-07|9th') === null, 'preview writes nothing');
[$c, $j] = $oa('apply', $body, $P); oa_check($c === 200 && !empty($j['applied']) && $j['add'] === 2, 'applied');
$d7 = sch_rec_get('studAtt', '2026-04-07|9th'); $d11 = sch_rec_get('studAtt', '2026-04-11|9th');
oa_check($d7['absent'] === ['erp-snmishra-1', 'erp-snmishra-2'] && $d7['st'] === ['erp-snmishra-2' => 'L'] && $d7['src'] === 'oldErp', 'absent + leave saved like the app does');
oa_check($d11['absent'] === ['erp-snmishra-2'] && !isset($d11['st']), 'Saturday saved');
oa_check(sch_rec_get('studAtt', '2026-04-06|9th')['by'] === 'Kartikey Sir', 'teacher\'s day untouched');
foreach (['2026-04-05', '2026-04-08', '2026-04-09', '2026-04-10'] as $d) oa_check(sch_rec_get('studAtt', "$d|9th") === null, "$d skipped");
[, $j] = $oa('apply', $body, $P); oa_check($j['add'] === 0 && $j['update'] === 0 && $j['kept'] === 3, 'second run adds nothing');
$body['kids'][0]['codes'] = 'S,A,P,P,P,-,P';                                                     // old ERP corrected Tue 7
[, $j] = $oa('apply', $body, $P); oa_check($j['update'] === 1 && sch_rec_get('studAtt', '2026-04-07|9th')['absent'] === ['erp-snmishra-2'], 'an earlier imported day is refreshed');
[$c, $j] = $oa('preview', ['cls' => '11th', 'days' => $days, 'kids' => []], $P); oa_check($c === 200 && isset($j['skipped']), 'unknown class skipped');
[$c] = $oa('preview', ['cls' => '9th', 'days' => $days, 'kids' => [['scholar' => '1', 'name' => 'x', 'codes' => 'P,P']]], $P); oa_check($c === 400, 'damaged file refused');
echo "Old ERP attendance import: all checks passed\n";
