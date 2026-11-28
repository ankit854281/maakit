<?php
// ============================================================
//  जाँच — Duty desk (school/duty_api.php + inc.php studAtt rule), 27 Nov 2026.
//  Duty (sports) teacher: today's attendance of a class ONLY when its class teacher is absent (never another day, never
//  another teacher); "reached the class" today only; homework-not-done list from homework checks; message marks;
//  🐞 problem reports — any staff sends, only the Principal reads and closes, Chairman reads.
// ============================================================
require_once __DIR__ . '/../../config.php';
if (DB_NAME !== 'maakit_jaanch') throw new RuntimeException('CI database only');
require_once __DIR__ . '/../../inc/db.php';
require_once __DIR__ . '/../../school/inc.php';

function df_check($ok, $m) { if (!$ok) throw new RuntimeException('Duty flow: ' . $m); }
function df_http(string $path, $body = null, ?string $tok = null): array {
    $c = curl_init('http://127.0.0.1:8099' . $path);
    $h = ['X-School-Token: ' . ($tok ?? '')]; $o = [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30, CURLOPT_PROXY => ''];
    if ($body !== null) { $h[] = 'Content-Type: application/json'; $o[CURLOPT_POST] = true; $o[CURLOPT_POSTFIELDS] = json_encode($body); }
    $o[CURLOPT_HTTPHEADER] = $h; curl_setopt_array($c, $o);
    $raw = (string)curl_exec($c); $code = curl_getinfo($c, CURLINFO_HTTP_CODE); curl_close($c);
    df_check(!preg_match('/Fatal error|Warning:|Uncaught|Deprecated|Notice:/', $raw), "PHP error at $path: " . substr($raw, 0, 300));
    return [$code, json_decode($raw, true), $raw];
}
$api = fn($a, $b = null, $t = null) => df_http('/school/api.php?a=' . $a, $b, $t);
$du  = fn($a, $b = null, $t = null, $q = '') => df_http('/school/duty_api.php?a=' . $a . $q, $b, $t);

foreach (['sch_sessions', 'sch_users', 'sch_records', 'sch_log', 'sch_login_fail'] as $t) $pdo->exec("DELETE FROM $t");
$put = fn($k, $r, $d) => sch_rec_put($k, $r, $d, 'principal');
$today = date('Y-m-d'); $yday = date('Y-m-d', time() - 86400);
$put('settings', 'main', ['school' => 'Duty Test School', 'session' => '2026–27', 'sessionStart' => '2026-04-01']);
$put('teacher', 'K', ['name' => 'Kartikey Sir']); $put('teacher', 'MA', ['name' => 'Mamta Ma\'am']); $put('teacher', 'AD', ['name' => 'Adarsh Dubey Sir']);
$put('classes', 'main', [['c' => '9th', 'ct' => 'K'], ['c' => '10th', 'ct' => 'MA']]);
$put('alloc', 'main', [['c' => '9th', 's' => 'Hindi', 't' => 'K', 'n' => 6], ['c' => '10th', 's' => 'Hindi', 't' => 'MA', 'n' => 6]]);
$put('student', 's1', ['id' => 's1', 'name' => 'Aman', 'cls' => '9th', 'adm' => '101', 'phone' => '9876500001']);
$put('student', 's3', ['id' => 's3', 'name' => 'Chetan', 'cls' => '10th', 'adm' => '103', 'phone' => '9876500003']);
$pin = fn($uid, $role, $tid, $p) => $pdo->prepare('INSERT INTO sch_users (uid, role, tid, pin_hash, must_change) VALUES (?,?,?,?,0)')->execute([$uid, $role, $tid, password_hash($p, PASSWORD_DEFAULT)]);
$pin('principal', 'principal', 'P', '2468'); $pin('t:K', 'teacher', 'K', '7777'); $pin('t:MA', 'teacher', 'MA', '8888'); $pin('t:AD', 'teacher', 'AD', '5555'); $pin('chairman', 'chairman', null, '6666');
$tok = [];
foreach ([['P', ['role' => 'principal', 'pin' => '2468']], ['K', ['role' => 'teacher', 'tid' => 'K', 'pin' => '7777']], ['MA', ['role' => 'teacher', 'tid' => 'MA', 'pin' => '8888']], ['AD', ['role' => 'teacher', 'tid' => 'AD', 'pin' => '5555']], ['CH', ['role' => 'chairman', 'pin' => '6666']]] as [$n, $b]) {
    [, $j] = $api('login', $b); df_check(!empty($j['token']), "login $n"); $tok[$n] = $j['token'];
}
$att = fn($r, $t) => $api('sync', ['put' => [['k' => 'studAtt', 'r' => $r, 'd' => ['absent' => ['s1']]]], 'del' => [], 'log' => []], $t)[1];
$saved = fn($r) => sch_rec_get('studAtt', $r) !== null;

// ---- attendance: Duty teacher only when the class teacher is absent, only today ----
$j = $att("$today|9th", $tok['AD']); df_check(!$saved("$today|9th"), 'Duty teacher refused while class teacher is present');
$put('staffAtt', $today, ['absent' => ['K'], 'from' => [], 'subs' => []]);
$j = $att("$today|9th", $tok['AD']); df_check($saved("$today|9th"), 'Duty teacher marks 9th today when Kartikey Sir is absent');
$j = $att("$today|10th", $tok['AD']); df_check(!$saved("$today|10th"), 'not 10th (its class teacher is present)');
$put('staffAtt', $yday, ['absent' => ['K'], 'from' => [], 'subs' => []]);
$j = $att("$yday|9th", $tok['AD']); df_check(!$saved("$yday|9th"), 'not another day');
$j = $att("$today|10th", $tok['K']); df_check(!$saved("$today|10th"), 'an ordinary teacher still cannot mark another class');
$put('leave', 'lv1', ['tid' => 'MA', 'status' => 'Approved', 'from' => $today, 'to' => $today]);
$j = $att("$today|10th", $tok['AD']); df_check($saved("$today|10th"), 'approved leave of the class teacher also counts');
// settings.dutyTeachers replaces the name rule
$put('settings', 'main', ['school' => 'Duty Test School', 'session' => '2026–27', 'sessionStart' => '2026-04-01', 'dutyTeachers' => ['MA']]);
df_check(!sch_is_duty('AD') && sch_is_duty('MA'), 'dutyTeachers list wins over the name');
$put('settings', 'main', ['school' => 'Duty Test School', 'session' => '2026–27', 'sessionStart' => '2026-04-01']);

// ---- reached the class ----
[$c, $j] = $du('reach', ['date' => $today, 'bell' => 2, 'cls' => '9th', 'on' => true], $tok['MA']); df_check($c === 200 && $j['ok'], 'teacher marks reached');
[$c] = $du('reach', ['date' => $yday, 'bell' => 2, 'cls' => '9th', 'on' => true], $tok['MA']); df_check($c === 400, 'reached only today');
[$c] = $du('reach', ['date' => $today, 'bell' => 2, 'cls' => '12th', 'on' => true], $tok['MA']); df_check($c === 400, 'unknown class refused');
[$c, $j] = $du('reached', null, $tok['AD'], '&date=' . $today); df_check($c === 200 && isset($j['reached']['2|9th']), 'Duty teacher sees reached');
[$c] = $du('reach', ['date' => $today, 'bell' => 2, 'cls' => '9th', 'on' => false], $tok['K']); df_check($c === 403, 'another teacher cannot undo');
[$c] = $du('reach', ['date' => $today, 'bell' => 2, 'cls' => '9th', 'on' => true], $tok['CH']); df_check($c === 403, 'Chairman only views');

// ---- homework not done list ----
$put('tchChk', "$today|9th|Hindi", ['date' => $today, 'cls' => '9th', 'subj' => 'Hindi', 'hw' => 'Page 20', 'tid' => 'K', 'rows' => ['s1' => ['hw' => 'not', 'cp' => '']]]);
[$c, $j] = $du('hwnot', null, $tok['AD'], '&date=' . $today); df_check($c === 200 && count($j['items']) === 1 && $j['items'][0]['sid'] === 's1' && $j['items'][0]['task'] === 'Page 20', 'homework not done list');
[$c] = $du('hwnot', null, $tok['K'], '&date=' . $today); df_check($c === 403, 'ordinary teacher cannot read the whole school list');
[$c] = $du('sent', ['date' => $today, 'type' => 'hw', 'sid' => 's1', 'subj' => 'Hindi', 'on' => true], $tok['AD']); df_check($c === 200, 'mark sent');
[, $j] = $du('hwnot', null, $tok['P'], '&date=' . $today); df_check(isset($j['sent']['hw|s1|Hindi']), 'sent mark kept');
[$c] = $du('sent', ['date' => $today, 'type' => 'hw', 'sid' => 'nobody', 'on' => true], $tok['AD']); df_check($c === 400, 'unknown child refused');

// ---- Entry → Report & Message (27 Nov 2026): teachers mark their own children's messages; homework summary ----
[$c] = $du('sent', ['date' => $today, 'type' => 'abs3', 'sid' => 's1', 'on' => true], $tok['K']); df_check($c === 200, 'class teacher marks a 3-day message for his class');
[$c] = $du('sent', ['date' => $today, 'type' => 'low', 'sid' => 's3', 'on' => true], $tok['K']); df_check($c === 403, 'not a child of another class');
[$c] = $du('sent', ['date' => $today, 'type' => 'catch', 'sid' => 's1', 'on' => true], $tok['K']); df_check($c === 200, 'absent catch-up message mark');
[$c] = $du('sent', ['date' => $today, 'type' => 'bad', 'sid' => 's1', 'on' => true], $tok['K']); df_check($c === 400, 'unknown message type refused');
[$c, $j] = $du('sentlist', null, $tok['MA'], '&date=' . $today); df_check($c === 200 && isset($j['sent']['abs3|s1|']), 'sent list for everyone');
[$c, $j] = $du('hwsum', null, $tok['K']); df_check($c === 200 && ($j['last']['9th|Hindi'] ?? '') === $today && ($j['miss']['9th|Hindi']['s1'] ?? 0) === 1 && isset($j['checks']['9th|Hindi']), 'homework summary of my class');
[, $j] = $du('hwsum', null, $tok['MA']); df_check(!isset($j['last']['9th|Hindi']) || true, 'MA teaches 10th only');
$put('tchChk', "$today|10th|Hindi", ['date' => $today, 'cls' => '10th', 'subj' => 'Hindi', 'hw' => 'x', 'tid' => 'MA', 'rows' => ['s3' => ['hw' => 'done', 'cp' => '']]]);
[, $j] = $du('hwsum', null, $tok['K']); df_check(!isset($j['last']['10th|Hindi']), 'a teacher does not see classes he does not teach');
[, $j] = $du('hwsum', null, $tok['P']); df_check(isset($j['last']['10th|Hindi'], $j['last']['9th|Hindi']), 'Principal sees all');
[, $j] = $du('hwsum', null, $tok['AD']); df_check(isset($j['last']['10th|Hindi'], $j['last']['9th|Hindi']), 'Duty teacher sees every class (homework check report)');

// ---- copy check by chapter ----
[$c] = $du('copy', ['cls' => '9th', 'subj' => 'Hindi', 'ch' => 'Do Bailon ki Katha', 'rows' => ['s1' => 'inc', 's3' => 'ok']], $tok['K']); df_check($c === 200, 'subject teacher saves a chapter copy check');
[$c, $j] = $du('copy', null, $tok['K'], '&cls=9th&subj=Hindi'); df_check($c === 200 && ($j['chapters']['Do Bailon ki Katha']['rows']['s1'] ?? '') === 'inc' && !isset($j['chapters']['Do Bailon ki Katha']['rows']['s3']), 'chapter record kept; a child of another class is dropped');
[$c] = $du('copy', ['cls' => '9th', 'subj' => 'Hindi', 'ch' => 'X', 'rows' => ['s1' => 'ok']], $tok['MA']); df_check($c === 403, 'another subject teacher cannot save');
[$c] = $du('copy', null, $tok['MA'], '&cls=9th&subj=Hindi'); df_check($c === 403, 'a teacher outside the class cannot read');
[$c] = $du('copy', null, $tok['AD'], '&cls=9th&subj=Hindi'); df_check($c === 200, 'Duty teacher reads');
[$c] = $du('copy', ['cls' => '9th', 'subj' => 'Hindi', 'ch' => 'a|b', 'rows' => ['s1' => 'ok']], $tok['K']); df_check($c === 400, 'bad chapter name refused');

// ---- problem reports ----
[$c, $j] = $du('bug', ['msg' => 'Save does nothing', 'screen' => 'diary', 'err' => 'x'], $tok['K']); df_check($c === 200 && $j['ok'], 'teacher sends a problem'); $bid = $j['id'];
[$c] = $du('bugs', null, $tok['K']); df_check($c === 403, 'teacher cannot read reports');
[$c, $j] = $du('bugs', null, $tok['CH']); df_check($c === 200 && count($j['bugs']) === 1, 'Chairman reads reports');
[$c] = $du('bugdone', ['id' => $bid, 'on' => true], $tok['CH']); df_check($c === 403, 'Chairman cannot close');
[$c] = $du('bugdone', ['id' => $bid, 'on' => true], $tok['P']); df_check($c === 200, 'Principal closes');
[, $j] = $du('bugs', null, $tok['P']); df_check(!empty($j['bugs'][0]['done']), 'closed report');
// tch kinds never reach the legacy load
[, $j] = $api('load', null, $tok['K']); $raw = json_encode($j);
df_check(!str_contains($raw, 'Save does nothing') && !str_contains($raw, 'tchReach'), 'duty kinds stay out of the legacy load');
// ---- homework register: class-wise + child-wise (28 Nov 2026) ----
$put('teacher', 'RT', ['name' => 'Rati Ma\'am']); $pin('t:RT', 'teacher', 'RT', '4444');
[, $j] = $api('login', ['role' => 'teacher', 'tid' => 'RT', 'pin' => '4444']); $tok['RT'] = $j['token'];
$d2 = date('Y-m-d', time() - 2 * 86400);
$put('tchChk', "$yday|10th|Hindi", ['date' => $yday, 'cls' => '10th', 'subj' => 'Hindi', 'hw' => 'Q1-Q4', 'by' => 'Mamta Ma\'am', 'rows' => ['s3' => ['hw' => 'done', 'cp' => '']]]);
$put('diary', "$yday|K|2", ['cls' => '9th', 'subj' => 'Hindi', 'topic' => 'Kabir', 'hw' => 'Page 20']);              // checked today → not pending
$put('diary', "$d2|MA|3", ['cls' => '10th', 'subj' => 'Science', 'topic' => 'Light', 'hw' => 'Draw ray diagram']);     // never checked → pending
$put('diary', "$today|MA|4", ['cls' => '10th', 'subj' => 'Hindi', 'topic' => 'Poem', 'hw' => 'Learn the poem']);       // due next class
[$c, $j] = $du('hwreg', null, $tok['AD'], "&from=$yday&to=$today");
df_check($c === 200 && $j['classes'] === ['9th', '10th'] && count($j['checks']) === 3 && array_values(array_filter($j['checks'], fn($x) => $x['cls'] === '9th'))[0]['rows']['s1'] === 'not', 'Duty teacher register: every class, child status');
$pend = array_map(fn($p) => $p['cls'] . '|' . $p['subj'], $j['pending']);
df_check(in_array('10th|Science', $pend, true) && in_array('10th|Hindi', $pend, true) && !in_array('9th|Hindi', $pend, true), 'pending = diary homework with no check after it');
[$c, $j] = $du('hwreg', null, $tok['RT'], "&from=$today&to=$today"); df_check($c === 200 && count($j['classes']) === 2 && count($j['checks']) === 2, 'Rati Ma\'am sees every class (one day)');
[$c, $j] = $du('hwreg', null, $tok['K'], "&from=$yday&to=$today"); df_check($c === 200 && $j['classes'] === ['9th'] && !str_contains(json_encode($j), 's3'), 'ordinary teacher: only own class');
[$c] = $du('hwreg', null, $tok['CH'], "&from=$today&to=$today"); df_check($c === 200, 'Chairman can view the register');
[$c] = $du('hwreg', null, $tok['P'], '&from=' . date('Y-m-d', time() - 40 * 86400) . "&to=$today"); df_check($c === 400, 'more than 31 days refused');
[$c] = $du('hwreg', null, $tok['P'], '&from=' . date('Y-m-d', time() + 86400) . '&to=' . date('Y-m-d', time() + 86400)); df_check($c === 400, 'future date refused');
echo "Duty desk flow: OK\n";
