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

// ================= BATTLE ARENA (27 Nov 2026) =================
// Aaj ka Test (diary ke topic se, 4 round ki hadd se alag, ek class-din me ek baar), battle (dono ko wahi
// sawaal, jeet/haar rounds se), season RP/tier/K-D, teacher report, aur sab ki seema.
gf_check(gm_tier(0)['name'] === 'Bronze' && gm_tier(0)['div'] === 'V' && gm_tier(399)['div'] === 'I' && gm_tier(1000)['name'] === 'Gold' && gm_tier(9000)['name'] === 'Conqueror' && gm_tier(9000)['div'] === '', 'tier ladder wrong');
$keyOf = function (array $it) use ($rec) {
    if ($it['src'] === 'duel') return $rec('quizQ', $it['ref'])['ans'];
    if ($it['src'] === 'pack') { [$pid, $i] = explode('#', $it['ref']); return $rec('gamePack', $pid)['qs'][(int)$i]['ans']; }
    return $it['src'] === 'math' ? gm_math('7th', $it['ref'])['ans'] : gm_word($it['ref'], gm_band('7th'))['ans'];
};
$playAll = function (string $sid, string $rid, bool $right) use ($g, $tok, $rec, $keyOf) {
    $last = null; $n = count($rec('gameR', $rid)['items']);
    for ($k = 0; $k < $n; $k++) {
        if ($k) $g('next', ['sid' => $sid, 'rid' => $rid], $tok[$sid]);
        $it = $rec('gameR', $rid)['items'][$k];
        [, $last] = $g('answer', ['sid' => $sid, 'rid' => $rid, 'choice' => $right ? $keyOf($it) : 9], $tok[$sid]);
    }
    return $last;
};
$tpid = 'p' . substr(hash('sha256', '7th|Social Science|' . mb_strtolower('Parliament')), 0, 16);
$pdo->prepare("INSERT INTO sch_records (k,r,d,deleted,ver) VALUES ('gamePack',?,?,0,1)")->execute([$tpid, json_encode(['id' => $tpid, 'cls' => '7th', 'subj' => 'Social Science', 'topic' => 'Parliament',
    'qs' => [['q' => 'Which house of Parliament is elected directly by the people?', 'opts' => ['Lok Sabha', 'Rajya Sabha', 'Vidhan Parishad', 'Gram Sabha'], 'ans' => 0, 'fun' => 'Lok = log (people) 👥', 'rule' => 'Lok Sabha members are elected by voters.', 'word' => ['parliament', 'संसद']],
             ['q' => 'Who is the head of the Lok Sabha?', 'opts' => ['The Prime Minister', 'The Speaker', 'The President', 'The Chief Justice'], 'ans' => 1, 'fun' => 'The Speaker keeps the class quiet 🔔', 'rule' => 'The Speaker runs the Lok Sabha.', 'word' => ['speaker', 'अध्यक्ष']],
             ['q' => 'Rajya Sabha is also called the…', 'opts' => ['Lower House', 'Upper House', 'Town Hall', 'People\'s House'], 'ans' => 1, 'fun' => 'Rajya = upar wala ghar 🏠', 'rule' => 'Rajya Sabha = Council of States (Upper House).', 'word' => ['council', 'परिषद']]],
    'hidden' => [], 'flags' => (object)[], 'tried' => time(), 'made' => time()])]);
[$c, $st] = $g('state', null, $tok['g1']);
gf_check($c === 200 && $st['today']['date'] === null && $st['season']['tier']['name'] !== '' && $st['battle']['left'] === 3, 'arena state wrong before the diary');
[$c] = $g('start', ['sid' => 'g1', 'mode' => 'today'], $tok['g1']);
gf_check($c === 400, 'class test opened without a diary topic');
$api('sync', ['put' => [['k' => 'diary', 'r' => date('Y-m-d') . '|K|3', 'd' => ['cls' => '7th', 'subj' => 'Social Science', 'topic' => 'Parliament', 'cw' => 'Notes', 'hw' => 'Q1 to Q3']]]], $K);
[$c, $st] = $g('state', null, $tok['g1']);
gf_check($st['today']['isToday'] === true && $st['today']['ready'] === 3 && $st['today']['topics'][0]['topic'] === 'Parliament' && $st['roundsLeft'] === 0, 'today topic not picked from the diary');
[$c, $j, $raw] = $g('start', ['sid' => 'g1', 'mode' => 'today'], $tok['g1']);   // g1 already used all 4 rounds — the test is separate
gf_check($c === 200 && $j['mode'] === 'today' && $j['n'] === 3 && !str_contains($raw, '"ans"'), 'class test did not start or leaked the answer');
$R = $rec('gameR', $j['rid']);
gf_check($R['tdate'] === date('Y-m-d') && count(array_filter($R['items'], fn($it) => str_starts_with($it['ref'], $tpid . '#'))) === 3, 'class test asked about something else');
$last = $playAll('g1', $j['rid'], true);
gf_check($last['summary']['right'] === 3 && $last['mode'] === 'today', 'class test not auto-checked');
[$c] = $g('start', ['sid' => 'g1', 'mode' => 'today'], $tok['g1']);
gf_check($c === 400, 'class test taken twice for the same class day');

// ---- battle: g1 challenges g2; g2 must finish its open round first; same questions for both ----
[$c] = $g('bstart', ['sid' => 'g1', 'opp' => 'g1'], $tok['g1']);
gf_check($c === 400, 'battle against yourself');
[$c, $op] = $g('bopp', null, $tok['g1'], '&sid=g1');
gf_check($c === 200 && count($op['list']) === 2 && !in_array('g1', array_column($op['list'], 'id'), true), 'rival list wrong');
[$c, $j, $raw] = $g('bstart', ['sid' => 'g1', 'opp' => 'g2'], $tok['g1']);
gf_check($c === 200 && $j['mode'] === 'battle' && $j['n'] === 5 && $j['opp'] === 'Riya P.' && !str_contains($raw, '"ans"'), 'battle did not start');
$bid = $j['bid']; $B = $rec('gameB', $bid);
gf_check($B['a'] === 'g1' && $B['b'] === 'g2' && $B['rb'] === null && count(array_filter($B['items'], fn($it) => $it['src'] === 'pack')) === 3, 'battle record wrong');
$last = $playAll('g1', $j['rid'], true);
gf_check($last['battle']['state'] === 'wait' && $last['battle']['opp'] === 'Riya P.', 'challenger not told to wait');
[$c] = $g('bplay', ['sid' => 'g3', 'bid' => $bid], $tok['g3']);
gf_check($c === 403, 'someone else played a battle not sent to them');
[$c, $st2] = $g('state', null, $tok['g2']);
gf_check(count($st2['battle']['incoming']) === 1 && $st2['battle']['incoming'][0]['from'] === 'Aman G.', 'battle did not reach the rival');
[$c] = $g('bplay', ['sid' => 'g2', 'bid' => $bid], $tok['g2']);
gf_check($c === 400, 'rival played while another round was open');
$open = null; foreach ($pdo->query("SELECT r FROM sch_records WHERE k='gameR' AND r LIKE 'g2|%'") as $row) { $x = $rec('gameR', $row['r']); foreach ($x['items'] as $it) if ($it['correct'] === null) $open = $row['r']; }
for ($k = 0; $k < 5; $k++) { $x = $rec('gameR', $open); $i = null; foreach ($x['items'] as $n => $it) if ($it['correct'] === null) { $i = $n; break; } if ($i === null) break;
    if (empty($x['items'][$i]['servedAt'])) $g('next', ['sid' => 'g2', 'rid' => $open], $tok['g2']); $g('answer', ['sid' => 'g2', 'rid' => $open, 'choice' => 9], $tok['g2']); }
[$c, $j] = $g('bplay', ['sid' => 'g2', 'bid' => $bid], $tok['g2']);
gf_check($c === 200 && $j['opp'] === 'Aman G.', 'rival could not play the battle');
gf_check(array_column($rec('gameR', $j['rid'])['items'], 'ref') === array_column($B['items'], 'ref'), 'rival got different questions');
[$c] = $g('bplay', ['sid' => 'g2', 'bid' => $bid], $tok['g2']);
gf_check($c === 400, 'battle played twice');
$last = $playAll('g2', $j['rid'], false);
gf_check($last['battle']['state'] === 'loss' && $last['battle']['them']['right'] === 5 && $last['battle']['me']['right'] === 0, 'battle result wrong for the rival');
[, $s1] = $g('state', null, $tok['g1']); [, $s2] = $g('state', null, $tok['g2']);
gf_check($s1['season']['win'] === 1 && $s1['battle']['history'][0]['state'] === 'win' && $s2['season']['loss'] === 1, 'battle not counted in the season');
gf_check($s1['season']['rp'] === $s1['season']['xp'] + 30 && $s1['season']['pos'] === 1 && $s1['rank'][0]['me'] === true && $s1['season']['kd'] > 0, 'RP or rank wrong');
gf_check($s2['season']['rp'] === max(0, $s2['season']['xp'] - 10) && $s2['season']['wrong'] > 0, 'loser RP wrong');

// ---- one battle per pair at a time; unanswered battle expires ----
[$c, $j] = $g('bstart', ['sid' => 'g1', 'opp' => 'g3'], $tok['g1']);
gf_check($c === 200, 'second battle did not start');
$b3 = $j['bid']; $playAll('g1', $j['rid'], true);
[$c, $j] = $g('bstart', ['sid' => 'g1', 'opp' => 'g3'], $tok['g1']);
gf_check($c === 400 && str_contains($j['error'], 'already'), 'two battles with the same rival at once');
$B3 = $rec('gameB', $b3); $B3['at'] = time() - 49 * 3600; $set('gameB', $b3, $B3);
[$c] = $g('bplay', ['sid' => 'g3', 'bid' => $b3], $tok['g3']);
gf_check($c === 400, 'expired battle could still be played');
[, $s1] = $g('state', null, $tok['g1']);
gf_check(in_array('expired', array_column($s1['battle']['history'], 'state'), true) && $s1['season']['win'] === 1, 'expired battle counted');

// ---- without AI: a teacher-approved question on the same diary topic joins the class test ----
$pdo->prepare("INSERT INTO sch_records (k,r,d,deleted,ver) VALUES ('quizQ','qT1',?,0,1)")->execute([json_encode(['id' => 'qT1', 'cls' => '7th', 'subj' => 'Social Science', 'topic' => 'parliament ', 'text' => 'How many houses does our Parliament have (with the President)?',
    'opts' => ['Two houses', 'Five houses', 'One house', 'Ten houses'], 'ans' => 0, 'by' => 't:K', 'bySid' => null, 'status' => 'approved'])]);
[, $st2] = $g('state', null, $tok['g2']);
gf_check($st2['today']['ready'] === 4, 'teacher question did not join the class test');
[$c, $j] = $g('start', ['sid' => 'g2', 'mode' => 'today'], $tok['g2']);
gf_check($c === 200 && $j['n'] === 4 && in_array('duel', array_column($rec('gameR', $j['rid'])['items'], 'src'), true), 'class test left out the teacher question');
$last = $playAll('g2', $j['rid'], true);
gf_check($last['summary']['right'] === 4, 'teacher question in the class test not auto-checked');

// ---- teacher report; students cannot open it; parent sees own child only ----
[$c, $T] = $g('tarena', null, $K);
gf_check($c === 200 && $T['cls'] === '7th' && count($T['rows']) === 3 && $T['topics'][0]['topic'] === 'Parliament', 'teacher arena report wrong');
$g1row = array_values(array_filter($T['rows'], fn($r) => $r['name'] === 'Aman Gupta'))[0];
gf_check($g1row['test']['right'] === 3 && $g1row['win'] === 1 && $T['rows'][0]['name'] === 'Aman Gupta', 'teacher report missed the test or the rank');
[$c] = $g('tarena', null, $tok['g1']);
gf_check($c === 403, 'student opened the teacher arena report');
[$c] = $g('bopp', null, $PA, '&sid=g2');
gf_check($c === 403, "parent opened another child's rivals");
[, , $raw] = $api('load', null, $P);
gf_check(!str_contains($raw, '"gameB"'), 'battle records leaked into normal load');

// ================= CLASSMATES' TRICKS (tips_api.php) + parent timetable / session attendance (27 Nov 2026) =================
$tp = fn($a, $b = null, $t = null, $q = '') => gf_http('/school/tips_api.php?a=' . $a . $q, $b, $t);
[$c] = $tp('post', ['sid' => 'g1', 'kind' => 'question', 'text' => 'What is 15% of 40 in one second?'], $tok['g1']);
gf_check($c === 400, 'question without an answer accepted');
[$c, $j] = $tp('post', ['sid' => 'g1', 'kind' => 'question', 'subj' => 'Maths', 'text' => 'What is 15% of 40 in one second?', 'ans' => '40% of 15 = 6'], $tok['g1']);
gf_check($c === 200, 'student could not share a question');
$tid = $j['id'];
[, $l2] = $tp('list', null, $tok['g2'], '&sid=g2');
gf_check(count($l2['tips']) === 0, 'classmates saw a trick before the teacher checked it');
[$c] = $tp('decide', ['id' => $tid, 'status' => 'approved'], $tok['g2']);
gf_check($c === 403, 'student approved a trick');
[$c, $tl] = $tp('tlist', null, $K);
gf_check($c === 200 && $tl['tips'][0]['id'] === $tid && $tl['tips'][0]['name'] === 'Aman Gupta', 'teacher cannot see the trick to check');
[$c] = $tp('decide', ['id' => $tid, 'status' => 'approved'], $K);
gf_check($c === 200, 'teacher could not approve');
[, $l2] = $tp('list', null, $tok['g2'], '&sid=g2');
gf_check(count($l2['tips']) === 1 && $l2['tips'][0]['by'] === 'Aman G.' && $l2['tips'][0]['ans'] === '40% of 15 = 6', 'approved trick not shown to the class with a short name');
for ($n = 0; $n < 2; $n++) $tp('post', ['sid' => 'g1', 'kind' => 'trick', 'text' => 'Trick number ' . $n . ' for the class'], $tok['g1']);
[$c] = $tp('post', ['sid' => 'g1', 'kind' => 'fact', 'text' => 'One more fact for today please'], $tok['g1']);
gf_check($c === 400, 'more than 3 posts in a day');
[$c] = $tp('list', null, $PA, '&sid=g2');
gf_check($c === 403, "parent opened another child's class tricks");
[, , $raw] = $api('load', null, $P);
gf_check(!str_contains($raw, '"quizTip"'), 'tricks leaked into normal load');
// timetable + whole-session attendance in pdata, only for the child's own class
$api('sync', ['put' => [['k' => 'grid', 'r' => 'main', 'd' => ['7th' => array_fill(0, 6, [0, -1, -1, -1, -1, -1, -1]), '9th' => array_fill(0, 6, [0, 0, 0, 0, 0, 0, 0])]],
                       ['k' => 'studAtt', 'r' => date('Y-m-d') . '|7th', 'd' => ['absent' => ['g2']]]]], $P);
[$c, $pd] = $api('pdata', null, $PA);
gf_check($c === 200 && $pd['tt']['7th'][0][0]['s'] === 'Social Science' && $pd['tt']['7th'][0][0]['t'] === 'Kartikey Sir' && $pd['tt']['7th'][0][1] === null && !isset($pd['tt']['9th']), 'parent timetable wrong or leaked another class');
gf_check($pd['attSum']['g1']['total'] >= 1 && $pd['attSum']['g1']['present'] === $pd['attSum']['g1']['total'] && !isset($pd['attSum']['g2']), 'session attendance wrong or leaked');

// ================= HALF-YEARLY RESULT, RANKERS, HOMEWORK + COPY CHECKS IN pdata (27 Nov 2026) =================
$ses = '2026-27';
$pmk = function (string $subj, array $rows) use ($pdo, $ses) { $pdo->prepare("REPLACE INTO sch_records (k,r,d,deleted,ver) VALUES ('prcMarks',?,?,0,1)")->execute(["$ses|Term-1|7th|$subj", json_encode(['subject' => $subj, 'rows' => $rows])]); };
$pmk('Maths', ['g1' => ['pt' => 9, 'nb' => 5, 'sea' => 5, 'te' => 70], 'g2' => ['pt' => 5, 'nb' => 3, 'sea' => 3, 'te' => 18], 'g3' => ['pt' => 8, 'nb' => 4, 'sea' => 4, 'te' => 60]]);
$pmk('English', ['g1' => ['pt' => 8, 'nb' => 5, 'sea' => 4, 'te' => 66], 'g2' => ['pt' => 4, 'nb' => 3, 'sea' => 2, 'te' => 30], 'g3' => ['pt' => 9, 'nb' => 5, 'sea' => 5, 'te' => 71]]);
$pdo->prepare("REPLACE INTO sch_records (k,r,d,deleted,ver) VALUES ('tchChk',?,?,0,1)")->execute([date('Y-m-d') . '|7th|Maths', json_encode(['date' => date('Y-m-d'), 'cls' => '7th', 'subj' => 'Maths', 'hw' => 'Ex 4.2', 'rows' => ['g1' => ['hw' => 'done', 'cp' => ''], 'g2' => ['hw' => 'not', 'cp' => '']], 'by' => 'Kartikey Sir'])]);
$pdo->prepare("REPLACE INTO sch_records (k,r,d,deleted,ver) VALUES ('tchCopy',?,?,0,1)")->execute(['7th|Maths|Fractions', json_encode(['cls' => '7th', 'subj' => 'Maths', 'ch' => 'Fractions', 'rows' => ['g1' => 'ok', 'g2' => 'inc'], 'by' => 'Kartikey Sir', 'at' => 1])]);
[$c, $pd] = $api('pdata', null, $PA);
$t1 = array_values(array_filter($pd['term'], fn($t) => $t['sid'] === 'g1'))[0] ?? null;
gf_check($t1 && $t1['exam'] === 'Half-Yearly' && $t1['pct'] == 86 && $t1['rank'] === 1 && $t1['of'] === 3 && count($t1['toppers']) === 1 && $t1['toppers'][0]['name'] === 'Aman Gupta', 'half-yearly result / rank / rankers wrong (rankers only from the upper half, as on the slip)');
gf_check(!array_filter($pd['term'], fn($t) => $t['sid'] === 'g2'), "another family's result leaked");
gf_check(count($pd['checks']['hw']) === 1 && $pd['checks']['hw'][0]['hw'] === 'done' && count($pd['checks']['copy']) === 1 && $pd['checks']['copy'][0]['st'] === 'ok', 'checks wrong or leaked');
$PB = null; $pin('p:9839100002', 'parent', '9839100002', '3333'); [, $j] = $api('login', ['role' => 'parent', 'phone' => '9839100002', 'pin' => '3333']); $PB = $j['token'];
[, $pd2] = $api('pdata', null, $PB); $t2 = $pd2['term'][0];
gf_check($t2['sid'] === 'g2' && $t2['rank'] === null && (fn($m) => abs($m['t'] - 33) < 0.01 && $m['te'] == 22)(array_values(array_filter($t2['rows'], fn($r) => $r['s'] === 'Maths'))[0]), 'lower-half rank shown, or grace (29 → 33) not applied like the slip');

// ================= STUDENT LOGIN WITH THE FAMILY MOBILE NUMBER (27 Nov 2026) =================
$api('sync', ['put' => [['k' => 'student', 'r' => 'g4', 'd' => ['id' => 'g4', 'adm' => 'g4', 'name' => 'Kavya Gupta', 'cls' => '3rd', 'phone' => '9839100001']],
                       ['k' => 'student', 'r' => 'g5', 'd' => ['id' => 'g5', 'adm' => 'g5', 'name' => 'Vivaan Gupta', 'cls' => '1st', 'phone' => '9839100001']]]], $P);
[$c, $j] = $api('login', ['role' => 'student', 'phone' => '98391 00001', 'pin' => '5555']);
gf_check($c === 200 && !empty($j['token']) && $j['me']['tid'] === 'g1', 'student could not log in with the mobile number and own PIN');
[$c, $j] = $api('login', ['role' => 'student', 'phone' => '9839100001', 'pin' => '1234']);
gf_check($c === 200 && empty($j['token']) && count($j['choose']) === 2 && in_array('Kavya Gupta', array_column($j['choose'], 'name'), true) && !in_array('g1', array_column($j['choose'], 'sid'), true), 'siblings sharing the first PIN were not asked to choose');
[$c, $j] = $api('login', ['role' => 'student', 'phone' => '9839100001', 'pin' => '1234', 'sid' => 'g5']);
gf_check($c === 200 && !empty($j['token']) && $j['me']['tid'] === 'g5' && !empty($j['mustChange']), 'chosen sibling could not log in');
[$c, $j] = $api('login', ['role' => 'student', 'phone' => '9839100001', 'pin' => '9999']);
gf_check($c === 403 && empty($j['choose']), 'wrong PIN showed the children');
[$c] = $api('login', ['role' => 'student', 'phone' => '9839100001', 'pin' => '5555', 'sid' => 'g2']);
gf_check($c === 403, 'a child from another family was picked with this number');
[$c] = $api('login', ['role' => 'student', 'phone' => '9000000000', 'pin' => '1234']);
gf_check($c === 403, 'unknown number logged in');
[$c, $j] = $api('login', ['role' => 'student', 'adm' => 'g2', 'pin' => '5555']);
gf_check($c === 200 && $j['me']['tid'] === 'g2', 'admission-number login broke');
$pdo->exec("DELETE FROM sch_login_fail");

foreach (['sch_sessions', 'sch_users', 'sch_records', 'sch_log', 'sch_login_fail'] as $t) $pdo->exec("DELETE FROM $t");
echo "✅ Game Zone: 24,000 maths sawaal sahi, chhupa jawab, ghadi, revenge, report, din ki hadd, parent ki seema, Aaj ka Test, Battle, Rank/K-D, teacher report — sab theek\n";
