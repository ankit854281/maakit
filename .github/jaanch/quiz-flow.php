<?php
// ============================================================
//  जाँच — Quiz Duel (school/quiz_api.php)
//
//  Bachche sirf padhe hue topic par sawaal bana sakte hain →
//  teacher ki manzoori ke bina sawaal kisi ko nahi jaata → sahi
//  jawab pehle phone par nahi aata → ghadi server par → galat jawab
//  par poochne wale ka point tabhi jab koi aur sahi kare → ek din me
//  ek dost ko ek hi chunauti → roz ka quiz → parent sirf apne bachche
//  ke liye → quiz ka data aam sync me nahi.
//  Sirf CI database par (sch_ tables saaf karta hai).
// ============================================================
require_once __DIR__ . '/../../config.php';
if (DB_NAME !== 'maakit_jaanch') throw new RuntimeException('CI database only');
require_once __DIR__ . '/../../inc/db.php';

function qf_check($ok, $m) { if (!$ok) throw new RuntimeException('Quiz flow: ' . $m); }
function qf_http(string $path, $body = null, ?string $tok = null): array {
    $c = curl_init('http://127.0.0.1:8099' . $path);
    $h = ['X-School-Token: ' . ($tok ?? '')];
    $o = [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20, CURLOPT_PROXY => ''];
    if ($body !== null) { $h[] = 'Content-Type: application/json'; $o[CURLOPT_POST] = true; $o[CURLOPT_POSTFIELDS] = json_encode($body); }
    $o[CURLOPT_HTTPHEADER] = $h; curl_setopt_array($c, $o);
    $raw = (string)curl_exec($c); $code = curl_getinfo($c, CURLINFO_HTTP_CODE); curl_close($c);
    qf_check(!preg_match('/Fatal error|Warning:|Uncaught/', $raw), "PHP error at $path: " . substr($raw, 0, 200));
    return [$code, json_decode($raw, true), $raw];
}
$api = fn(string $a, $b = null, ?string $t = null, string $q = '') => qf_http('/school/api.php?a=' . $a . $q, $b, $t);
$qz  = fn(string $a, $b = null, ?string $t = null, string $q = '') => qf_http('/school/quiz_api.php?a=' . $a . $q, $b, $t);

foreach (['sch_sessions', 'sch_users', 'sch_records', 'sch_log', 'sch_login_fail'] as $t) $pdo->exec("DELETE FROM $t");
$pin = fn(string $uid, string $role, ?string $tid, string $p) => $pdo->prepare('INSERT INTO sch_users (uid, role, tid, pin_hash, must_change) VALUES (?,?,?,?,0)')->execute([$uid, $role, $tid, password_hash($p, PASSWORD_DEFAULT)]);
$pin('principal', 'principal', 'P', '2468');
[, $j] = $api('login', ['role' => 'principal', 'pin' => '2468']); $P = $j['token'];
$today = date('Y-m-d'); $yday = date('Y-m-d', time() - 86400);
$kids = [['s1', 'Aman Gupta', '9839000001'], ['s2', 'Riya Pal', '9839000002'], ['s3', 'Neha Bind', '9839000003'], ['s4', 'Vivek Rai', '9839000004'], ['s5', 'Sona Das', '9839000005']];
$put = [['k' => 'settings', 'r' => 'main', 'd' => ['school' => 'SN Mishra Singapore International School', 'session' => '2026-27']],
        ['k' => 'classes', 'r' => 'main', 'd' => [['c' => '9th', 'ct' => 'K'], ['c' => '8th', 'ct' => 'MA']]],
        ['k' => 'teacher', 'r' => 'K', 'd' => ['name' => 'Kartikey Sir']], ['k' => 'teacher', 'r' => 'MA', 'd' => ['name' => "Mamta Ma'am"]],
        ['k' => 'alloc', 'r' => 'main', 'd' => [['c' => '9th', 's' => 'Social Science', 't' => 'K', 'n' => 7], ['c' => '8th', 's' => 'Hindi', 't' => 'MA', 'n' => 5]]],
        ['k' => 'diary', 'r' => "$yday|K|0", 'd' => ['cls' => '9th', 'subj' => 'Social Science', 'topic' => 'Federalism', 'hw' => '']],
        ['k' => 'student', 'r' => 's9', 'd' => ['id' => 's9', 'adm' => '99', 'name' => 'Other Kid', 'cls' => '8th', 'phone' => '9839000009']]];
foreach ($kids as [$id, $n, $ph]) $put[] = ['k' => 'student', 'r' => $id, 'd' => ['id' => $id, 'adm' => substr($id, 1), 'name' => $n, 'cls' => '9th', 'phone' => $ph]];
[, $j] = $api('sync', ['put' => $put], $P);
qf_check(!$j['rejected'], 'seed rejected');
$tok = [];
foreach ($kids as [$id]) { $pin('s:' . $id, 'student', $id, '5555'); [, $j] = $api('login', ['role' => 'student', 'adm' => substr($id, 1), 'pin' => '5555']); $tok[$id] = $j['token']; }
$pin('t:K', 'teacher', 'K', '7777'); [, $j] = $api('login', ['role' => 'teacher', 'tid' => 'K', 'pin' => '7777']); $K = $j['token'];
$pin('t:MA', 'teacher', 'MA', '7778'); [, $j] = $api('login', ['role' => 'teacher', 'tid' => 'MA', 'pin' => '7778']); $MA = $j['token'];
$pin('p:9839000001', 'parent', '9839000001', '3333'); [, $j] = $api('login', ['role' => 'parent', 'phone' => '9839000001', 'pin' => '3333']); $PA = $j['token'];

// ---- topics only from what was taught ----
[$c, $st] = $qz('state', null, $tok['s1']);
qf_check($c === 200 && count($st['topics']) === 1 && $st['topics'][0]['topic'] === 'Federalism', 'taught topic not offered');
$topic = $st['topics'][0];
$good = ['sid' => 's1', 'topic' => $topic, 'text' => 'Which list has subjects of national importance?', 'opts' => ['Union List', 'State List', 'Concurrent List', 'Residuary'], 'ans' => 1];
[$c] = $qz('ask', ['topic' => ['date' => $today, 'subj' => 'Maths', 'topic' => 'Made up']] + $good, $tok['s1']);
qf_check($c === 400, 'question on an untaught topic accepted');
[$c] = $qz('ask', ['opts' => ['a', 'a', 'b', 'c']] + $good, $tok['s1']);
qf_check($c === 400, 'repeated choices accepted');
[$c, $j] = $qz('ask', $good, $tok['s1']);
qf_check($c === 200, 'student could not make a question');
$qid = $j['id'];

// ---- teacher approval needed ----
[$c] = $qz('challenge', ['sid' => 's1', 'qid' => $qid, 'to' => 's2'], $tok['s1']);
qf_check($c === 400, 'unapproved question was sent');
[$c] = $qz('decide', ['qid' => $qid, 'status' => 'approved'], $MA);
qf_check($c === 403, 'teacher approved a subject they do not teach');
[$c] = $qz('decide', ['qid' => $qid, 'status' => 'approved'], $tok['s1']);
qf_check($c === 403, 'student approved their own question');
[$c] = $qz('decide', ['qid' => $qid, 'status' => 'approved', 'ans' => 0], $K);           // teacher fixes the key: Union List
qf_check($c === 200, 'teacher could not approve');

// ---- challenge: no answer key before answering; timer; fairness ----
[$c] = $qz('challenge', ['sid' => 's1', 'qid' => $qid, 'to' => 's9'], $tok['s1']);
qf_check($c === 400, 'challenge sent outside the class');
[$c] = $qz('challenge', ['sid' => 's1', 'qid' => $qid, 'to' => 's2'], $tok['s1']);
qf_check($c === 200, 'challenge not sent');
[$c] = $qz('challenge', ['sid' => 's1', 'qid' => $qid, 'to' => 's2'], $tok['s1']);
qf_check($c === 400, 'same classmate challenged twice');
[, $st2] = $qz('state', null, $tok['s2']);
$cid = $st2['incoming'][0]['id'];
[$c] = $qz('open', ['sid' => 's2', 'cid' => $cid], $tok['s3']);
qf_check($c === 403, 'someone else opened the challenge');
[$c, $j, $raw] = $qz('open', ['sid' => 's2', 'cid' => $cid], $tok['s2']);
qf_check($c === 200 && !str_contains($raw, '"ans"') && $j['left'] <= 45, 'answer key reached the phone before answering');
[, $j] = $qz('answer', ['sid' => 's2', 'cid' => $cid, 'choice' => 2], $tok['s2']);
qf_check($j['correct'] === false && $j['ans'] === 0, 'wrong answer not marked wrong');
[, $st] = $qz('state', null, $tok['s1']);
qf_check($st['points']['asked'] === 0 && $st['points']['pending'] === 1, 'asker got a point before the question was proved fair');

$qz('challenge', ['sid' => 's1', 'qid' => $qid, 'to' => 's3'], $tok['s1']);
[, $st3] = $qz('state', null, $tok['s3']); $cid3 = $st3['incoming'][0]['id'];
$qz('open', ['sid' => 's3', 'cid' => $cid3], $tok['s3']);
[, $j] = $qz('answer', ['sid' => 's3', 'cid' => $cid3, 'choice' => 0], $tok['s3']);
qf_check($j['correct'] === true, 'right answer not marked right');
[, $st] = $qz('state', null, $tok['s1']); [, $st3] = $qz('state', null, $tok['s3']);
qf_check($st['points']['asked'] === 1 && $st3['points']['answered'] === 2, 'points after a fair question are wrong');

// timer: opened long ago, answered now = wrong even with the right choice
$qz('challenge', ['sid' => 's1', 'qid' => $qid, 'to' => 's4'], $tok['s1']);
[, $st4] = $qz('state', null, $tok['s4']); $cid4 = $st4['incoming'][0]['id'];
$qz('open', ['sid' => 's4', 'cid' => $cid4], $tok['s4']);
$row = $pdo->query("SELECT d FROM sch_records WHERE k='quizC' AND r=" . $pdo->quote($cid4))->fetchColumn();
$d = json_decode($row, true); $d['openedAt'] = time() - 120;
$pdo->prepare("UPDATE sch_records SET d=? WHERE k='quizC' AND r=?")->execute([json_encode($d), $cid4]);
[, $j] = $qz('answer', ['sid' => 's4', 'cid' => $cid4, 'choice' => 0], $tok['s4']);
qf_check($j['correct'] === false, 'late answer was accepted');
[$c] = $qz('challenge', ['sid' => 's1', 'qid' => $qid, 'to' => 's5'], $tok['s1']);
qf_check($c === 400, 'question sent to more than 3 classmates');

// ---- daily quiz: teacher questions, one a day, no key in advance ----
foreach (['Who heads the Union government?' => ['Prime Minister', 'Governor', 'Mayor', 'Sarpanch'], 'Panchayati Raj is which level?' => ['Third tier', 'First tier', 'Union', 'State'], 'How many lists in the Seventh Schedule?' => ['Three', 'Two', 'Four', 'Five']] as $t => $o) {
    [$c] = $qz('add', ['cls' => '9th', 'subj' => 'Social Science', 'topic' => 'Federalism', 'text' => $t, 'opts' => $o, 'ans' => 0], $K);
    qf_check($c === 200, 'teacher could not add a question');
}
[$c] = $qz('add', ['cls' => '8th', 'subj' => 'Hindi', 'topic' => 'x', 'text' => 'Not my subject?', 'opts' => ['a', 'b', 'c', 'd'], 'ans' => 0], $K);
qf_check($c === 403, 'teacher added a question for another subject');
[$c, $j, $raw] = $qz('quiz', ['sid' => 's5'], $tok['s5']);
qf_check($c === 200 && $j['n'] === 4 && !str_contains($raw, '"ans"'), 'daily quiz did not start cleanly');
$right = 0;
for ($i = 0; $i < $j['n']; $i++) { [, $j2] = $qz('quiz', ['sid' => 's5', 'choice' => 0], $tok['s5']); if ($j2['last']['correct']) $right++; $j = $j2 + ['n' => $j['n']]; }
qf_check($right === 4 && $j2['q'] === null, 'daily quiz answers not checked');
[, $j] = $qz('quiz', ['sid' => 's5'], $tok['s5']);
qf_check($j['q'] === null && $j['done'] === 4, 'daily quiz could be taken twice');
[, $st5] = $qz('state', null, $tok['s5']);
qf_check($st5['points']['quiz'] === 4, 'daily quiz points wrong');

// ---- parent plays only for own child; teacher sees stats; nobody else gets in ----
[$c] = $qz('state', null, $PA, '&sid=s1');
qf_check($c === 200, 'parent could not open the quiz for own child');
[$c] = $qz('state', null, $PA, '&sid=s2');
qf_check($c === 403, "parent opened another child's quiz");
[$c, $j] = $qz('tstate', null, $K);
qf_check($c === 200 && count($j['questions']) === 4 && isset($j['boards']['9th']), 'teacher view incomplete');
[$c] = $qz('tstate', null, $tok['s1']);
qf_check($c === 403, 'student opened the teacher view');

// ---- quiz data stays out of normal sync ----
[, , $raw] = $api('load', null, $P);
qf_check(!str_contains($raw, '"quizQ"') && !str_contains($raw, '"quizC"'), 'quiz records leaked into normal load');
[, $j] = $api('sync', ['put' => [['k' => 'quizQ', 'r' => 'hack', 'd' => ['status' => 'approved']]]], $P);
qf_check(count($j['rejected']) === 1, 'quiz record written through normal sync');

foreach (['sch_sessions', 'sch_users', 'sch_records', 'sch_log', 'sch_login_fail'] as $t) $pdo->exec("DELETE FROM $t");
echo "✅ Quiz Duel: padhe hue topic, teacher ki manzoori, chhupa jawab, ghadi, fair-play point, roz ka quiz, parent ki seema — sab theek\n";
