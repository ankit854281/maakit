<?php
// ============================================================
//  जाँच — Teacher App (school/teacher_api.php, teacher.php)
//
//  BSA Bhadohi chhuttiyan ek hi baar judti hain (Principal ki hatayi
//  wapas nahi aati, pehle se wali tareekh par dobara nahi) → class
//  holiday sirf class teacher / Principal → us din ki haazri hat jaati
//  hai aur month register me holiday dikhta hai → bulk back-days
//  haazri sync se (sirf apni class) → notes sirf apne class-subject,
//  sirf likhne wala badle → notes aam load me kabhi nahi, parent ko
//  sirf apne bachche ki class ke shared notes → photo se notes ka
//  mode maana jaata hai.
// ============================================================
require_once __DIR__ . '/../../config.php';
if (DB_NAME !== 'maakit_jaanch') throw new RuntimeException('CI database only');
require_once __DIR__ . '/../../inc/db.php';
require_once __DIR__ . '/../../school/inc.php';

function tf_check($ok, $m) { if (!$ok) throw new RuntimeException('Teacher app flow: ' . $m); }
function tf_http(string $path, $body = null, ?string $tok = null): array {
    $c = curl_init('http://127.0.0.1:8099' . $path);
    $h = ['X-School-Token: ' . ($tok ?? '')]; $o = [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30, CURLOPT_PROXY => ''];
    if ($body !== null) { $h[] = 'Content-Type: application/json'; $o[CURLOPT_POST] = true; $o[CURLOPT_POSTFIELDS] = json_encode($body); }
    $o[CURLOPT_HTTPHEADER] = $h; curl_setopt_array($c, $o);
    $raw = (string)curl_exec($c); $code = curl_getinfo($c, CURLINFO_HTTP_CODE); curl_close($c);
    tf_check(!preg_match('/Fatal error|Warning:|Uncaught|Deprecated|Notice:/', $raw), "PHP error at $path: " . substr($raw, 0, 300));
    return [$code, json_decode($raw, true), $raw];
}
$api = fn($a, $b = null, $t = null) => tf_http('/school/api.php?a=' . $a, $b, $t);
$ta  = fn($a, $b = null, $t = null, $q = '') => tf_http('/school/teacher_api.php?a=' . $a . $q, $b, $t);

foreach (['sch_sessions', 'sch_users', 'sch_records', 'sch_log', 'sch_login_fail'] as $t) $pdo->exec("DELETE FROM $t");
$put = fn($k, $r, $d) => sch_rec_put($k, $r, $d, 'principal');
$put('settings', 'main', ['school' => 'Teacher App Test School', 'session' => '2026–27', 'sessionStart' => '2026-04-01']);
$put('teacher', 'K', ['name' => 'Kartikey Sir']); $put('teacher', 'MA', ['name' => 'Mamta Ma\'am']);
$put('classes', 'main', [['c' => '9th', 'ct' => 'K'], ['c' => '10th', 'ct' => 'MA']]);
$put('alloc', 'main', [['c' => '9th', 's' => 'Social Science', 't' => 'K', 'n' => 6], ['c' => '10th', 's' => 'Hindi', 't' => 'MA', 'n' => 6], ['c' => '9th', 's' => 'Hindi', 't' => 'MA', 'n' => 5]]);
$put('student', 's1', ['id' => 's1', 'name' => 'Aman', 'cls' => '9th', 'adm' => '101', 'phone' => '9876500001']);
$put('student', 's2', ['id' => 's2', 'name' => 'Bina', 'cls' => '9th', 'adm' => '102', 'phone' => '9876500002']);
$put('student', 's3', ['id' => 's3', 'name' => 'Chetan', 'cls' => '10th', 'adm' => '103', 'phone' => '9876500003']);
$put('cal', 'x-old', ['id' => 'x-old', 'date' => '2026-11-11', 'to' => '', 'title' => 'Already a holiday', 'type' => 'Holiday']);
$pin = fn($uid, $role, $tid, $p) => $pdo->prepare('INSERT INTO sch_users (uid, role, tid, pin_hash, must_change) VALUES (?,?,?,?,0)')->execute([$uid, $role, $tid, password_hash($p, PASSWORD_DEFAULT)]);
$pin('principal', 'principal', 'P', '2468'); $pin('t:K', 'teacher', 'K', '7777'); $pin('t:MA', 'teacher', 'MA', '8888'); $pin('chairman', 'chairman', null, '6666');
$tok = [];
foreach ([['P', ['role' => 'principal', 'pin' => '2468']], ['K', ['role' => 'teacher', 'tid' => 'K', 'pin' => '7777']], ['MA', ['role' => 'teacher', 'tid' => 'MA', 'pin' => '8888']], ['CH', ['role' => 'chairman', 'pin' => '6666']]] as [$n, $b]) {
    [, $j] = $api('login', $b); tf_check(!empty($j['token']), "login $n"); $tok[$n] = $j['token'];
}
[, $j] = $api('login', ['role' => 'parent', 'phone' => '9876500001', 'pin' => '1234']); $par = $j['token'];
$api('setpin', ['old' => '1234', 'pin' => '4455'], $par); [, $j] = $api('login', ['role' => 'parent', 'phone' => '9876500001', 'pin' => '4455']); $par = $j['token'];

// ---- pages open ----
[$c, , $raw] = tf_http('/school/teacher.php'); tf_check($c === 200 && str_contains($raw, 'teacher.js'), 'teacher.php opens');
[$c] = tf_http('/school/teacher-manifest.json'); tf_check($c === 200, 'manifest');

// ---- local holiday list: added once, skips a date that is already a holiday, never re-adds a removed one ----
[$c, $j] = $ta('state', null, $tok['K']); tf_check($c === 200 && $j['ok'], 'state for teacher');
$cal = fn() => array_column(array_map(fn($r) => json_decode($r, true), $pdo->query("SELECT d FROM sch_records WHERE k='cal' AND deleted=0")->fetchAll(PDO::FETCH_COLUMN)), null, 'id');
$all = $cal(); tf_check(isset($all['bsa26-govardhan'], $all['bsa26-gurunanak']), 'BSA holidays added');
tf_check(!isset($all['bsa26-dooj']), '11 Nov was already a holiday — not added twice');
$n1 = count($all); $ta('state', null, $tok['MA']); tf_check(count($cal()) === $n1, 'second call adds nothing');
sch_rec_del('cal', 'bsa26-govardhan', 'principal'); $ta('state', null, $tok['K']); tf_check(!isset($cal()['bsa26-govardhan']), 'a holiday the Principal removed does not come back');
[$c] = $ta('state', null, $par); tf_check($c === 403, 'parents cannot use the teacher API');

// ---- class holiday: only the class teacher / Principal; it removes that day's attendance ----
$put('studAtt', '2026-10-05|9th', ['absent' => ['s2'], 'by' => 'K', 'at' => 1]);
[$c] = $ta('holiday', ['cls' => '9th', 'date' => '2026-10-05', 'title' => 'Class trip'], $tok['MA']); tf_check($c === 403, 'another teacher cannot mark a holiday for 9th');
[$c] = $ta('holiday', ['cls' => '9th', 'date' => '2026-10-05', 'title' => 'Class trip'], $tok['CH']); tf_check($c === 403, 'chairman cannot write');
[$c] = $ta('holiday', ['cls' => '9th', 'date' => '2026-10-04', 'title' => 'x'], $tok['K']); tf_check($c === 400, 'Sunday refused');
[$c, $j] = $ta('holiday', ['cls' => '9th', 'date' => '2026-10-05', 'title' => 'Class trip'], $tok['K']); tf_check($c === 200 && $j['removedAttendance'] === true, 'class teacher marks holiday');
tf_check(sch_rec_get('studAtt', '2026-10-05|9th') === null && sch_rec_get('clsHol', '2026-10-05|9th')['title'] === 'Class trip', 'attendance removed, holiday saved');
[$c, $j] = $ta('month', null, $tok['K'], '&cls=9th&ym=2026-10'); tf_check($c === 200 && isset($j['hol']['2026-10-05']) && !isset($j['att']['2026-10-05']) && $j['canEdit'] === true, 'month register shows the holiday');
[$c] = $ta('month', null, $tok['K'], '&cls=10th&ym=2026-10'); tf_check($c === 403, 'teacher cannot read a class they do not teach');
[$c, $j] = $ta('month', null, $tok['MA'], '&cls=9th&ym=2026-10'); tf_check($c === 200 && $j['canEdit'] === false, 'subject teacher reads but cannot edit');
[$c, $j] = tf_http('/school/api.php?a=load&days=400', null, $tok['P']); tf_check(in_array('clsHol', array_column($j['records'], 'k'), true), 'web ERP gets class holidays in load');
$ta('holiday', ['cls' => '9th', 'date' => '2026-10-05', 'on' => false], $tok['K']); tf_check(sch_rec_get('clsHol', '2026-10-05|9th') === null, 'holiday removed');

// ---- bulk back days through the normal sync: own class only ----
$days = []; foreach (['2026-10-06', '2026-10-07', '2026-10-08'] as $d) $days[] = ['k' => 'studAtt', 'r' => "$d|9th", 'd' => ['absent' => $d === '2026-10-07' ? ['s1'] : [], 'by' => 'Kartikey Sir', 'at' => 1]];
[$c, $j] = $api('sync', ['put' => $days, 'del' => [], 'log' => [['k' => 'Student Attendance', 'x' => 'Class 9th: 3 back days — Teacher App']]], $tok['K']);
tf_check($c === 200 && count($j['done']) === 3 && !$j['rejected'], 'class teacher saves 3 back days at once');
[, $j] = $api('sync', ['put' => [['k' => 'studAtt', 'r' => '2026-10-06|10th', 'd' => ['absent' => []]]], 'del' => [], 'log' => []], $tok['K']);
tf_check(count($j['rejected']) === 1, 'not another class');
[, $j] = $ta('month', null, $tok['K'], '&cls=9th&ym=2026-10'); tf_check(count((array)$j['att']) === 3 && $j['att']['2026-10-07']['absent'] === ['s1'], 'register shows the back days');

// ---- notes ----
$note = ['cls' => '9th', 'subj' => 'Social Science', 'chapter' => 'The French Revolution', 'body' => 'Summary', 'points' => ['1789 storming of the Bastille', ''], 'words' => [['w' => 'Estate', 'm' => 'वर्ग']], 'qa' => [['q' => 'When?', 'a' => '1789']]];
[$c] = $ta('note', $note, $tok['MA']); tf_check($c === 403, 'notes only for own subject');
[$c, $j] = $ta('note', $note, $tok['K']); tf_check($c === 200 && $j['note']['points'] === ['1789 storming of the Bastille'] && $j['note']['share'] === true, 'teacher saves notes');
$id = $j['note']['id'];
// ---- Principal and Chairman: read-only, redacted overview; no note body in bulk ----
[$c] = $ta('overview', null, $tok['K']); tf_check($c === 403, 'teacher cannot open the school-wide overview');
[$c, $ov] = $ta('overview', null, $tok['P']);
tf_check($c === 200 && $ov['ok'] && isset($ov['notes']['9th']['Social Science'][0]['pointsCount']), 'Principal sees note metadata');
tf_check(!isset($ov['notes']['9th']['Social Science'][0]['body']) && !isset($ov['notes']['9th']['Social Science'][0]['qa']), 'overview keeps private note text out');
[$c, $ov] = $ta('overview', null, $tok['CH']);
tf_check($c === 200 && $ov['ok'] && isset($ov['notes']['9th']['Social Science'][0]['id']), 'Chairman can read overview');

[$c] = $ta('note', array_merge($note, ['id' => $id, 'body' => 'changed']), $tok['MA']); tf_check($c === 403, 'another teacher cannot change them');
[$c] = $ta('note', ['id' => $id, 'cls' => '9th', 'subj' => 'Hindi', 'chapter' => 'x', 'body' => 'y'], $tok['MA']); tf_check($c === 403 || $c === 400, 'cannot move a note to another subject');
[$c, $j] = $ta('note', array_merge($note, ['id' => $id, 'body' => 'changed']), $tok['K']); tf_check($c === 200 && $j['note']['body'] === 'changed', 'author updates');
[$c] = $ta('note', ['cls' => '9th', 'subj' => 'Social Science', 'chapter' => 'Empty'], $tok['K']); tf_check($c === 400, 'empty note refused');
$ta('note', ['cls' => '9th', 'subj' => 'Social Science', 'chapter' => 'Draft only', 'body' => 'not yet', 'share' => false], $tok['K']);
[$c, $j] = $ta('notes', null, $tok['MA'], '&cls=9th&subj=Social+Science'); tf_check($c === 200 && count($j['notes']) === 2, 'teachers of the class read the notes');
[$c] = $ta('notes', null, $tok['K'], '&cls=10th'); tf_check($c === 403, 'not of other classes');
[, $j] = $api('load', null, $tok['K']); tf_check(!in_array('tchNote', array_column($j['records'], 'k'), true), 'notes never in legacy load');
[, $j] = $api('pdata', null, $par); tf_check(count($j['notes']) === 1 && $j['notes'][0]['chapter'] === 'The French Revolution' && !isset($j['notes'][0]['tid']), 'parent sees only shared notes of the child\'s class');
[$c] = $ta('delnote', ['id' => $id], $tok['MA']); tf_check($c === 403, 'another teacher cannot delete');
[$c] = $ta('delnote', ['id' => $id], $tok['K']); tf_check($c === 200 && sch_rec_get('tchNote', $id) === null, 'author deletes');
[$c] = $ta('note', ['id' => $id] + $note, $tok['K']); tf_check($c === 409, 'a deleted note is not brought back by an old phone');

// ---- photo → notes is an accepted mode (AI off in CI: the phone reads it) ----
$png = 'data:image/png;base64,' . base64_encode(str_repeat("\x89PNG", 1) . str_repeat('x', 400));
[$c, $j] = tf_http('/school/scan_api.php?a=read', ['mode' => 'notes', 'cls' => '9th', 'subj' => 'Social Science', 'images' => [$png]], $tok['K']);
tf_check(($c === 409 && !empty($j['noAi'])) || $c === 200 || $c === 502, 'notes mode accepted (' . $c . ')');
[$c] = tf_http('/school/scan_api.php?a=read', ['mode' => 'notes', 'cls' => '10th', 'subj' => 'Hindi', 'images' => [$png]], $tok['K']); tf_check($c === 403, 'notes photo only for own subject');

// ---- homework + copy checking (5 Nov 2026): only the subject's teacher writes; teachers of the class read; never in legacy load ----
$chk = ['date' => '2026-11-04', 'cls' => '9th', 'subj' => 'Social Science', 'hw' => 'Learn Q&A', 'rows' => ['s1' => ['hw' => 'not', 'cp' => 'ok'], 's2' => ['hw' => 'done', 'cp' => ''], 's3' => ['hw' => 'done'], 'zz' => ['hw' => 'done']]];
[$c] = $ta('check', $chk, $tok['MA']); tf_check($c === 403, 'a teacher of another subject cannot check Social Science');
[$c] = $ta('check', $chk, $tok['CH']); tf_check($c === 403, 'Chairman only views');
[$c, $j] = $ta('check', $chk, $tok['K']); tf_check($c === 200 && array_keys($j['rec']['rows']) === ['s1', 's2'], 'subject teacher saves; other-class and unknown children dropped');
[$c] = $ta('check', ['rows' => ['s1' => ['hw' => 'bad']]] + $chk, $tok['K']); tf_check($c === 400, 'nothing valid → refused');
[$c, $j] = $ta('checks', null, $tok['MA'], '&cls=9th&subj=Social+Science&date=2026-11-04'); tf_check($c === 200 && $j['rec']['rows']['s1']['hw'] === 'not', 'another teacher of the class reads it');
[$c] = $ta('checks', null, $tok['MA'], '&cls=10th&subj=Hindi&date=2026-11-04'); tf_check($c === 200, 'own class');
[$c] = $ta('checks', null, $tok['K'], '&cls=10th&subj=Hindi&date=2026-11-04'); tf_check($c === 403, 'not another class');
$ta('check', ['date' => '2026-11-05'] + $chk, $tok['K']);
[, $j] = $ta('checks', null, $tok['K'], '&cls=9th&subj=Social+Science&date=2026-11-06'); tf_check(($j['miss']['s1'] ?? 0) === 2 && $j['checked'] === 2, 'not-done count over the last 30 days');
[, $j] = $api('load', null, $tok['K']); tf_check(!in_array('tchChk', array_column($j['records'], 'k'), true), 'checking never in legacy load');

echo "Teacher app flow: all checks passed\n";
