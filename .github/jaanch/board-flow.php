<?php
// ============================================================
//  जाँच — Class 10 Board Prep (school/board_api.php, 13 Nov 2026)
//
//  question bank is sound (4 different options, answer in range, ids unique) →
//  a Class 10 student logs in with 1234 → own PIN → home → practice shows the
//  answer at once → chapter test hides answers until submit and marks it →
//  mock spreads over chapters → weak chapters → other classes refused →
//  teacher adds a question, it comes in the chapter → class results →
//  brd* never in the normal load.
// ============================================================
require_once __DIR__ . '/../../config.php';
if (DB_NAME !== 'maakit_jaanch') throw new RuntimeException('CI database only');
require_once __DIR__ . '/../../inc/db.php';
require_once __DIR__ . '/../../school/inc.php';

function bd_check($ok, $m) { if (!$ok) throw new RuntimeException('Board prep flow: ' . $m); }
function bd_http(string $path, $body = null, ?string $tok = null): array {
    $c = curl_init('http://127.0.0.1:8099' . $path);
    $h = ['X-School-Token: ' . ($tok ?? '')]; $o = [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30, CURLOPT_PROXY => ''];
    if ($body !== null) { $h[] = 'Content-Type: application/json'; $o[CURLOPT_POST] = true; $o[CURLOPT_POSTFIELDS] = json_encode($body); }
    $o[CURLOPT_HTTPHEADER] = $h; curl_setopt_array($c, $o);
    $raw = (string)curl_exec($c); $code = curl_getinfo($c, CURLINFO_HTTP_CODE); curl_close($c);
    bd_check(!preg_match('/Fatal error|Warning:|Uncaught|Deprecated/', $raw), "PHP error at $path: " . substr($raw, 0, 300));
    return [$code, json_decode($raw, true), $raw];
}
$api = fn($a, $b = null, $t = null) => bd_http('/school/api.php?a=' . $a, $b, $t);
$bd  = fn($a, $b = null, $t = null, $q = '') => bd_http('/school/board_api.php?a=' . $a . $q, $b, $t);

// ---- the bank itself ----
$bank = json_decode((string)file_get_contents(__DIR__ . '/../../school/board_bank.json'), true); $ids = []; $n = 0;
foreach ($bank['subjects'] as $s) foreach ($s['chapters'] as $c) { bd_check(count($c['q']) >= 6, "{$s['code']} {$c['name']} has few questions");
    foreach ($c['q'] as $q) { $n++; bd_check(!isset($ids[$q['id']]), 'duplicate id ' . $q['id']); $ids[$q['id']] = 1;
        bd_check(count($q['o']) === 4 && count(array_unique($q['o'])) === 4 && is_int($q['a']) && $q['a'] >= 0 && $q['a'] <= 3 && trim($q['q']) !== '' && trim($q['e']) !== '', 'bad question ' . $q['id']); } }
bd_check($n >= 300, "bank has $n questions");

foreach (['sch_sessions', 'sch_users', 'sch_records', 'sch_log', 'sch_login_fail'] as $t) $pdo->exec("DELETE FROM $t");
$put = fn($k, $r, $d) => sch_rec_put($k, $r, $d, 'principal');
$put('settings', 'main', ['school' => 'Board Test School', 'session' => '2026-27']);
$put('teacher', 'K', ['name' => 'Kartikey Sir']); $put('teacher', 'P', ['name' => 'Principal']);
foreach ([['s1', 'Aman', '10th', 'A101'], ['s2', 'Bina', '10th', 'A102'], ['s3', 'Chandu', '9th', 'A103']] as [$id, $nm, $c, $adm]) $put('student', $id, ['id' => $id, 'name' => $nm, 'cls' => $c, 'adm' => $adm]);
$pin = fn($uid, $role, $tid, $p) => $pdo->prepare('INSERT INTO sch_users (uid, role, tid, pin_hash, must_change) VALUES (?,?,?,?,0)')->execute([$uid, $role, $tid, password_hash($p, PASSWORD_DEFAULT)]);
$pin('principal', 'principal', 'P', '2468'); $pin('t:K', 'teacher', 'K', '7777'); $pin('chairman', 'chairman', null, '6666');
[, $j] = $api('login', ['role' => 'principal', 'pin' => '2468']); $P = $j['token'];
[, $j] = $api('login', ['role' => 'teacher', 'tid' => 'K', 'pin' => '7777']); $K = $j['token'];
[, $j] = $api('login', ['role' => 'chairman', 'pin' => '6666']); $CH = $j['token'];

// ---- student login ----
[$c, $j] = $api('login', ['role' => 'student', 'adm' => 'A101', 'pin' => '1234']); bd_check($c === 200 && $j['mustChange'] === true, 'first login with 1234');
[$c] = $bd('home', null, $j['token']); bd_check($c === 428, 'must choose own PIN first');
$api('setpin', ['old' => '1234', 'pin' => '4646'], $j['token']); [, $j] = $api('login', ['role' => 'student', 'adm' => 'A101', 'pin' => '4646']); $S = $j['token'];
[, $j] = $api('login', ['role' => 'student', 'adm' => 'A103', 'pin' => '1234']); $api('setpin', ['old' => '1234', 'pin' => '5757'], $j['token']); [, $j] = $api('login', ['role' => 'student', 'adm' => 'A103', 'pin' => '5757']);
[$c] = $bd('home', null, $j['token']); bd_check($c === 403, 'Class 9 child refused');

[$c, $h] = $bd('home', null, $S); bd_check($c === 200 && $h['role'] === 'student' && $h['me']['name'] === 'Aman' && $h['total'] === $n, 'home: ' . substr(json_encode($h), 0, 200));
bd_check(array_column($h['subjects'], 'code') === ['maths', 'science', 'sst', 'english', 'hindi'], 'subjects');

// ---- practice: answer at once ----
[$c, $r] = $bd('start', ['mode' => 'practice', 'subj' => 'maths', 'ch' => 5], $S); bd_check($c === 200 && count($r['questions']) === 8 && !isset($r['questions'][0]['a']), 'practice: 8 AP questions, no answers sent');
$Q = []; foreach ($bank['subjects'][0]['chapters'][4]['q'] as $q) $Q[$q['id']] = $q;
$q0 = $r['questions'][0]; [$c, $k] = $bd('check', ['run' => $r['run'], 'qid' => $q0['id'], 'choice' => $Q[$q0['id']]['a']], $S); bd_check($c === 200 && $k['right'] === true && $k['e'] !== '', 'practice check right');
$q1 = $r['questions'][1]; $wrong = ($Q[$q1['id']]['a'] + 1) % 4; [, $k] = $bd('check', ['run' => $r['run'], 'qid' => $q1['id'], 'choice' => $wrong], $S); bd_check($k['right'] === false && $k['a'] === $Q[$q1['id']]['a'], 'practice check wrong');
[, $k] = $bd('check', ['run' => $r['run'], 'qid' => $q1['id'], 'choice' => $Q[$q1['id']]['a']], $S); bd_check($k['right'] === false, 'first answer counts');
[$c, $res] = $bd('submit', ['run' => $r['run'], 'answers' => []], $S); bd_check($c === 200 && $res['score'] === 1 && $res['total'] === 8, 'practice finished: ' . json_encode([$res['score'], $res['total']]));
[$c] = $bd('submit', ['run' => $r['run'], 'answers' => []], $S); bd_check($c === 400, 'run cannot be submitted twice');

// ---- chapter test ----
[, $r] = $bd('start', ['mode' => 'chapter', 'subj' => 'science', 'ch' => 11], $S); bd_check($r['min'] === 12 && count($r['questions']) === 9, 'chapter test: Electricity 9 questions, 12 minutes');
[$c] = $bd('check', ['run' => $r['run'], 'qid' => $r['questions'][0]['id'], 'choice' => 0], $S); bd_check($c === 400, 'no answers during a test');
$E = []; foreach ($bank['subjects'][1]['chapters'][10]['q'] as $q) $E[$q['id']] = $q['a'];
$ans = []; foreach ($r['questions'] as $i => $q) $ans[$q['id']] = $i < 6 ? $E[$q['id']] : ($E[$q['id']] + 1) % 4;
[, $res] = $bd('submit', ['run' => $r['run'], 'answers' => $ans], $S); bd_check($res['score'] === 6 && count($res['review']) === 9 && isset($res['review'][0]['a'], $res['review'][0]['e']), 'chapter test marked 6/9 with review');

// ---- mock spreads over chapters ----
[, $r] = $bd('start', ['mode' => 'mock', 'subj' => 'all'], $S); $chs = []; foreach ($r['questions'] as $q) $chs[$q['subj'] . '|' . $q['ch']] = 1;
bd_check($r['min'] === 40 && count($r['questions']) === 30 && count($chs) >= 25, 'mock: 30 questions from ' . count($chs) . ' chapters');
[, $r] = $bd('start', ['mode' => 'mock', 'subj' => 'sst'], $S); bd_check(count(array_unique(array_column($r['questions'], 'subj'))) === 1 && $r['questions'][0]['subj'] === 'sst', 'subject mock only that subject');
[$c] = $bd('start', ['mode' => 'chapter', 'subj' => 'hindi', 'ch' => 1], $S); bd_check($c === 400, 'empty chapter says so');

// ---- weak chapters + history ----
[, $r] = $bd('start', ['mode' => 'chapter', 'subj' => 'maths', 'ch' => 1], $S); $ans = []; foreach ($r['questions'] as $q) $ans[$q['id']] = -1;
$bd('submit', ['run' => $r['run'], 'answers' => []], $S);
[, $h] = $bd('home', null, $S); $w = array_column($h['weak'], 'name');
bd_check(in_array('Real Numbers', $w, true) && !in_array('Electricity', $w, true) && $h['done']['tests'] === 2, 'weak chapters: ' . json_encode($w));
bd_check(isset($h['acc']['science|11']) && $h['acc']['science|11']['r'] === 6, 'chapter accuracy kept');
[, $hi] = $bd('history', null, $S); bd_check(count($hi['rows']) === 3, 'history 3 results (practice + 2 tests)');

// ---- teacher ----
[$c] = $bd('addq', ['subj' => 'hindi', 'ch' => 1, 'q' => 'प्रश्न?', 'o' => ['क', 'ख', 'ख', 'घ'], 'a' => 0], $K); bd_check($c === 400, 'four different options needed');
[$c, $j] = $bd('addq', ['subj' => 'hindi', 'ch' => 1, 'q' => '"नेताजी का चश्मा" के लेखक कौन हैं?', 'o' => ['स्वयं प्रकाश', 'प्रेमचंद', 'यशपाल', 'निराला'], 'a' => 0, 'e' => 'Kshitij — Swayam Prakash.'], $K); bd_check($c === 200, 'teacher adds a Hindi question');
[$c] = $bd('addq', ['subj' => 'hindi', 'ch' => 1, 'q' => 'x?', 'o' => ['a', 'b', 'c', 'd'], 'a' => 0], $CH); bd_check($c === 403, 'Chairman cannot add');
[$c] = $bd('addq', ['subj' => 'hindi', 'ch' => 1, 'q' => 'x?', 'o' => ['a', 'b', 'c', 'd'], 'a' => 0], $S); bd_check($c === 403, 'student cannot add');
[$c, $r] = $bd('start', ['mode' => 'practice', 'subj' => 'hindi', 'ch' => 1], $S); bd_check($c === 200 && count($r['questions']) === 1 && $r['questions'][0]['id'] === $j['id'], 'teacher question in the chapter');
[$c, $cl] = $bd('class', null, $CH); $rows = array_column($cl['rows'], null, 'sid');
bd_check($c === 200 && count($cl['rows']) === 2 && $rows['s1']['tests'] === 2 && $rows['s2']['answered'] === 0 && in_array('Real Numbers', $rows['s1']['weak'], true), 'class results: ' . json_encode($rows['s1']));
[$c] = $bd('class', null, $S); bd_check($c === 403, 'student cannot see the class');
[$c] = $bd('delq', ['id' => $j['id']], $P); bd_check($c === 200 && sch_rec_get('brdQ', $j['id']) === null, 'Principal removes the question');
[, $r] = $bd('start', ['mode' => 'mock', 'subj' => 'maths'], $K); [, $res] = $bd('submit', ['run' => $r['run'], 'answers' => []], $K);
bd_check($res['total'] === 30 && (int)$pdo->query("SELECT COUNT(*) FROM sch_records WHERE k='brdRes' AND r LIKE 'staff:%'")->fetchColumn() === 0, 'teacher can try a test, nothing saved');
[, $j] = $api('load', null, $P); bd_check(!array_filter($j['records'] ?? [], fn($x) => str_starts_with($x['k'], 'brd')), 'brd* not in the normal load');

echo "Board prep flow: OK ($n questions)\n";
