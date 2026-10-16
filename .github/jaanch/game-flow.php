<?php
// ============================================================
//  जाँच — Game Zone (school/game_api.php)
//
//  Bina AI ke bhi khel chalta hai (maths + English words) → jawab
//  pehle phone par nahi aata → ghadi tab shuru jab bachcha sawaal
//  khole → der se jawab galat → galat sawaal "revenge" me wapas →
//  din me 4 round → AI pack ka sawaal 2 report par chhup jaata hai,
//  teacher wapas la sakta hai → parent sirf apne bachche ka → game ka
//  data aam sync me nahi. Saath me 26,000 maths sawaalon ki jaanch.
// ============================================================
require_once __DIR__ . '/../../config.php';
if (DB_NAME !== 'maakit_jaanch') throw new RuntimeException('CI database only');
require_once __DIR__ . '/../../inc/db.php';
require_once __DIR__ . '/../../school/game_lib.php';
date_default_timezone_set('Asia/Kolkata');   // same as school/inc.php

function gf_check($ok, $m) { if (!$ok) throw new RuntimeException('Game flow: ' . $m); }
function gf_http(string $path, $body = null, ?string $tok = null): array {
    $c = curl_init('http://127.0.0.1:8099' . $path);
    $h = ['X-School-Token: ' . ($tok ?? '')]; $o = [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30, CURLOPT_PROXY => ''];
    if ($body !== null) { $h[] = 'Content-Type: application/json'; $o[CURLOPT_POST] = true; $o[CURLOPT_POSTFIELDS] = json_encode($body); }
    $o[CURLOPT_HTTPHEADER] = $h; curl_setopt_array($c, $o);
    $raw = (string)curl_exec($c); $code = curl_getinfo($c, CURLINFO_HTTP_CODE); curl_close($c);
    gf_check(!preg_match('/Fatal error|Warning:|Uncaught/', $raw), "PHP error at $path: " . substr($raw, 0, 300));
    return [$code, json_decode($raw, true), $raw];
}
$api = fn($a, $b = null, $t = null, $q = '') => gf_http('/school/api.php?a=' . $a . $q, $b, $t);
$g   = fn($a, $b = null, $t = null, $q = '') => gf_http('/school/game_api.php?a=' . $a . $q, $b, $t);
$rec = function (string $k, string $r) use ($pdo) { $s = $pdo->prepare('SELECT d FROM sch_records WHERE k=? AND r=?'); $s->execute([$k, $r]); return json_decode((string)$s->fetchColumn(), true); };
$set = function (string $k, string $r, array $d) use ($pdo) { $pdo->prepare('UPDATE sch_records SET d=? WHERE k=? AND r=?')->execute([json_encode($d, JSON_UNESCAPED_UNICODE), $k, $r]); };

// ---- generators: every maths answer re-worked independently ----
$bad = 0;
foreach (['PG', '1st', '3rd', '5th', '6th', '8th', '9th', '10th'] as $cls) for ($i = 0; $i < 3000; $i++) {
    $q = gm_math($cls, "ci$cls$i"); $v = (float)$q['opts'][$q['ans']]; $t = $q['q']; $e = null;
    if (count(array_unique($q['opts'])) !== 4) { $bad++; continue; }
    if (preg_match('/^\(?(-?\d+)\)? \+ \(?(-?\d+)\)? = \?$/', $t, $m)) $e = $m[1] + $m[2];
    elseif (preg_match('/^(\d+) − (\d+) = \?$/u', $t, $m)) $e = $m[1] - $m[2];
    elseif (preg_match('/^(\d+) × (\d+) = \?$/u', $t, $m)) $e = $m[1] * $m[2];
    elseif (preg_match('/^(\d+) ÷ (\d+) = \?$/u', $t, $m)) $e = $m[1] / $m[2];
    elseif (preg_match('/^What is (\d+)% of (\d+)\?$/', $t, $m)) $e = $m[1] * $m[2] / 100;
    elseif (preg_match('/^Find x:  x \+ (\d+) = (\d+)$/', $t, $m)) $e = $m[2] - $m[1];
    elseif (preg_match('/^Find x:  (\d+)x \+ (\d+) = (\d+)$/', $t, $m)) $e = ($m[3] - $m[2]) / $m[1];
    elseif (preg_match('/^(\d+)² = \?$/u', $t, $m)) $e = $m[1] ** 2;
    elseif (preg_match('/^√(\d+) = \?$/u', $t, $m)) $e = sqrt((float)$m[1]);
    elseif (preg_match('/^(\d+)\^(\d+) = \?$/', $t, $m)) $e = $m[1] ** $m[2];
    elseif (preg_match('/^(\d+) 🍬 \+ (\d+) 🍬 = \?$/u', $t, $m)) $e = $m[1] + $m[2];
    elseif (preg_match('/^How many\? (.+)$/u', $t, $m)) $e = count(preg_split('//u', $m[1], -1, PREG_SPLIT_NO_EMPTY));
    elseif (preg_match('/^Which number is BIGGER: (\d+) or (\d+)\?$/', $t, $m)) { $e = max($m[1], $m[2]); }
    else { $bad++; continue; }
    if ($v !== (float)$e) $bad++;
}
gf_check($bad === 0, "$bad generated maths questions were wrong");

// ---- AI pack filter: bad shapes dropped; only answers the second check agrees with are kept ----
$made = ['questions' => [
    ['q' => 'Capital of India?', 'opts' => ['Delhi', 'Mumbai', 'Pune', 'Agra'], 'ans' => 0, 'fun' => 'f', 'rule' => 'r', 'word' => 'capital', 'hindi' => 'राजधानी'],
    ['q' => 'Same options here?', 'opts' => ['A', 'a', 'B', 'C'], 'ans' => 1, 'fun' => '', 'rule' => '', 'word' => '', 'hindi' => ''],
    ['q' => 'Answer out of range?', 'opts' => ['1', '2', '3', '4'], 'ans' => 4, 'fun' => '', 'rule' => '', 'word' => '', 'hindi' => ''],
    ['q' => 'Largest planet in our solar system?', 'opts' => ['Mars', 'Jupiter', 'Earth', 'Venus'], 'ans' => 1, 'fun' => '', 'rule' => '', 'word' => '', 'hindi' => ''],
    ['q' => 'Wrong key from the writer?', 'opts' => ['x', 'y', 'z', 'w'], 'ans' => 2, 'fun' => '', 'rule' => '', 'word' => '', 'hindi' => '']]];
$clean = gm_pack_clean($made);
gf_check(count($clean) === 3, 'bad AI questions were not dropped');
$kept = gm_pack_keep($clean, ['answers' => [0, 1, 0]]);
gf_check(count($kept) === 2 && $kept[1]['opts'][$kept[1]['ans']] === 'Jupiter', 'question with a disputed answer was kept');

foreach (['sch_sessions', 'sch_users', 'sch_records', 'sch_log', 'sch_login_fail'] as $t) $pdo->exec("DELETE FROM $t");
$pin = fn($uid, $role, $tid, $p) => $pdo->prepare('INSERT INTO sch_users (uid, role, tid, pin_hash, must_change) VALUES (?,?,?,?,0)')->execute([$uid, $role, $tid, password_hash($p, PASSWORD_DEFAULT)]);
$pin('principal', 'principal', 'P', '2468'); [, $j] = $api('login', ['role' => 'principal', 'pin' => '2468']); $P = $j['token'];
$put = [['k' => 'settings', 'r' => 'main', 'd' => ['school' => 'SN Mishra Singapore International School', 'session' => '2026-27']],
        ['k' => 'classes', 'r' => 'main', 'd' => [['c' => '7th', 'ct' => 'K']]], ['k' => 'teacher', 'r' => 'K', 'd' => ['name' => 'Kartikey Sir']],
        ['k' => 'alloc', 'r' => 'main', 'd' => [['c' => '7th', 's' => 'Social Science', 't' => 'K', 'n' => 6]]]];
foreach ([['g1', 'Aman Gupta', '9839100001'], ['g2', 'Riya Pal', '9839100002'], ['g3', 'Neha Bind', '9839100003']] as [$id, $n, $ph]) $put[] = ['k' => 'student', 'r' => $id, 'd' => ['id' => $id, 'adm' => $id, 'name' => $n, 'cls' => '7th', 'phone' => $ph]];
$api('sync', ['put' => $put], $P);
$tok = [];
foreach (['g1', 'g2', 'g3'] as $id) { $pin('s:' . $id, 'student', $id, '5555'); [, $j] = $api('login', ['role' => 'student', 'adm' => $id, 'pin' => '5555']); $tok[$id] = $j['token']; }
$pin('t:K', 'teacher', 'K', '7777'); [, $j] = $api('login', ['role' => 'teacher', 'tid' => 'K', 'pin' => '7777']); $K = $j['token'];
$pin('p:9839100001', 'parent', '9839100001', '3333'); [, $j] = $api('login', ['role' => 'parent', 'phone' => '9839100001', 'pin' => '3333']); $PA = $j['token'];

// an AI-style pack, as if made from a taught topic (no network in CI)
$pdo->prepare("INSERT INTO sch_records (k,r,d,deleted,ver) VALUES ('gamePack','pX',?,0,1)")->execute([json_encode(['id' => 'pX', 'cls' => '7th', 'subj' => 'Social Science', 'topic' => 'Democracy',
    'qs' => [['q' => 'In a democracy, who chooses the government?', 'opts' => ['The people', 'The king', 'The army', 'The judge'], 'ans' => 0, 'fun' => 'Vote = your remote control 📺', 'rule' => 'Democracy: rule by the people.', 'word' => ['democracy', 'लोकतंत्र']],
             ['q' => 'How often are Lok Sabha elections held normally?', 'opts' => ['Every 5 years', 'Every year', 'Every 10 years', 'Every 2 years'], 'ans' => 0, 'fun' => 'Like a 5-year cricket tour 🏏', 'rule' => 'Lok Sabha term: 5 years.', 'word' => ['election', 'चुनाव']]],
    'hidden' => [], 'flags' => (object)[], 'tried' => time(), 'made' => time()])]);

// ---- state, start, no key in advance ----
[$c, $st] = $g('state', null, $tok['g1']);
gf_check($c === 200 && $st['roundsLeft'] === 4 && $st['topicPacks'] === 1 && $st['words'] === true, 'state wrong');
[$c, $j, $raw] = $g('start', ['sid' => 'g1', 'mode' => 'daily'], $tok['g1']);
gf_check($c === 200 && $j['n'] === 5 && !str_contains($raw, '"ans"') && !str_contains($raw, '"fun"') && !str_contains($raw, '"rule"'), 'round leaked the answer');
$rid = $j['rid'];
$R = $rec('gameR', $rid); $srcs = array_column($R['items'], 'src');
gf_check(count(array_filter($srcs, fn($x) => $x === 'pack')) === 2, 'studied-topic questions were not put first');
[$c] = $g('start', ['sid' => 'g1', 'mode' => 'math'], $tok['g1']);
gf_check($c === 400, 'a second round started before finishing the first');

// answer every question correctly (the test knows the key from the database)
$xp = 0;
for ($k = 0; $k < 5; $k++) {
    if ($k > 0) { [, $nx] = $g('next', ['sid' => 'g1', 'rid' => $rid], $tok['g1']); gf_check($nx['i'] === $k, 'next question not served'); }
    $R = $rec('gameR', $rid); $packs = ['pX' => $rec('gamePack', 'pX')];
    $q = $R['items'][$k]['src'] === 'pack' ? $packs['pX']['qs'][(int)explode('#', $R['items'][$k]['ref'])[1]] : ($R['items'][$k]['src'] === 'math' ? gm_math('7th', $R['items'][$k]['ref']) : gm_word($R['items'][$k]['ref'], gm_band('7th')));
    [, $a] = $g('answer', ['sid' => 'g1', 'rid' => $rid, 'choice' => $q['ans']], $tok['g1']);
    gf_check($a['correct'] === true && $a['fun'] !== '' && $a['rule'] !== '', 'right answer not accepted or no explanation');
    if ($k === 0) { [$c] = $g('answer', ['sid' => 'g1', 'rid' => $rid, 'choice' => 0], $tok['g1']); gf_check($c === 400, 'answered a question that was never opened'); }
    $xp += $a['xp'];
}
gf_check($a['summary']['right'] === 5 && $a['combo'] === 5, 'round summary wrong');
gf_check($xp === 20 + 50 + 15, "XP should be 85 (day bonus + 5×10 + combo), got $xp");

// ---- late answer is wrong and comes back as revenge ----
[, $j] = $g('start', ['sid' => 'g2', 'mode' => 'math'], $tok['g2']); $rid2 = $j['rid'];
$R = $rec('gameR', $rid2); $R['items'][0]['servedAt'] = time() - 120; $set('gameR', $rid2, $R);
$q = gm_math('7th', $R['items'][0]['ref']);
[, $a] = $g('answer', ['sid' => 'g2', 'rid' => $rid2, 'choice' => $q['ans']], $tok['g2']);
gf_check($a['correct'] === false && $a['late'] === true, 'late answer accepted');
$M = $rec('gameMiss', 'g2');
gf_check(count($M['items']) === 1 && $M['items'][0]['due'] === date('Y-m-d', strtotime('+1 day')), 'missed question not scheduled for tomorrow');
for ($k = 1; $k < 5; $k++) { $g('next', ['sid' => 'g2', 'rid' => $rid2], $tok['g2']); $g('answer', ['sid' => 'g2', 'rid' => $rid2, 'choice' => 9], $tok['g2']); }
$M = $rec('gameMiss', 'g2'); foreach ($M['items'] as &$m) $m['due'] = date('Y-m-d'); unset($m); $set('gameMiss', 'g2', $M);
[$c, $j] = $g('start', ['sid' => 'g2', 'mode' => 'revenge'], $tok['g2']);
gf_check($c === 200 && $j['n'] === 5, 'revenge round did not bring the missed questions back');

// ---- report: 2 students hide an AI question; the teacher can bring it back ----
[, $j] = $g('start', ['sid' => 'g3', 'mode' => 'daily'], $tok['g3']);
$rid3 = $j['rid']; $R3 = $rec('gameR', $rid3);
gf_check($R3['items'][0]['src'] === 'pack', 'first daily question should be a studied-topic question');
$g('answer', ['sid' => 'g3', 'rid' => $rid3, 'choice' => 1], $tok['g3']);
[$c, $j] = $g('flag', ['sid' => 'g3', 'rid' => $rid3, 'i' => 0], $tok['g3']);
gf_check($c === 200 && $j['hidden'] === false, 'first report failed');
[$c] = $g('flag', ['sid' => 'g3', 'rid' => $rid3, 'i' => 1], $tok['g3']);
gf_check($c === 400, 'reported a question before answering it');
$p = $rec('gamePack', 'pX'); $qi = (int)explode('#', $R3['items'][0]['ref'])[1];
$f = (array)$p['flags']; $f[(string)$qi][] = 'g1'; $p['flags'] = $f; $p['hidden'] = [$qi]; $set('gamePack', 'pX', $p);
[$c, $j] = $g('tpacks', null, $K);
gf_check($c === 200 && $j['aiReady'] === false && count($j['packs'][0]['flagged']) === 1, 'teacher cannot see reported questions');
[$c] = $g('packfix', ['pid' => 'pX', 'i' => $qi, 'action' => 'restore'], $K);
gf_check($c === 200 && $rec('gamePack', 'pX')['hidden'] === [], 'teacher could not restore');
[$c] = $g('tpacks', null, $tok['g1']);
gf_check($c === 403, 'student opened the teacher packs');
[$c] = $g('makepacks', [], $K);
gf_check($c === 400, 'pack making did not say AI is not connected');

// ---- limits, parent, sync isolation ----
for ($n = 0; $n < 3; $n++) {   // g1 already played 1; play 3 more quick math rounds
    [$c, $j] = $g('start', ['sid' => 'g1', 'mode' => 'math'], $tok['g1']); gf_check($c === 200, 'could not start round ' . ($n + 2));
    $r = $j['rid']; for ($k = 0; $k < 5; $k++) { if ($k) $g('next', ['sid' => 'g1', 'rid' => $r], $tok['g1']); $g('answer', ['sid' => 'g1', 'rid' => $r, 'choice' => 0], $tok['g1']); }
}
[$c] = $g('start', ['sid' => 'g1', 'mode' => 'math'], $tok['g1']);
gf_check($c === 400, 'more than 4 rounds in a day');
[$c, $st] = $g('state', null, $PA, '&sid=g1');
gf_check($c === 200 && $st['stats']['xp'] > 0 && $st['stats']['streak'] === 1, 'parent cannot see own child\'s game');
[$c] = $g('state', null, $PA, '&sid=g2');
gf_check($c === 403, "parent opened another child's game");
[, , $raw] = $api('load', null, $P);
gf_check(!str_contains($raw, '"gameR"') && !str_contains($raw, '"gamePack"'), 'game records leaked into normal load');
[, $j] = $api('sync', ['put' => [['k' => 'gameR', 'r' => 'x', 'd' => ['sid' => 'g1']]]], $P);
gf_check(count($j['rejected']) === 1, 'game record written through normal sync');

foreach (['sch_sessions', 'sch_users', 'sch_records', 'sch_log', 'sch_login_fail'] as $t) $pdo->exec("DELETE FROM $t");
echo "✅ Game Zone: 24,000 maths sawaal sahi, chhupa jawab, ghadi, revenge, report, din ki hadd, parent ki seema — sab theek\n";
