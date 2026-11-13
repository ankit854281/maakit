<?php
// ============================================================================
//  Class 10 Board Prep — owner, 13 Nov 2026: "class 10th ke liye students app banaiye, usme NCERT / CBSE side se
//  import karke online test chalu — board ki preparation, final exam ki taiyari". Page: board.php + board.js.
//
//  Questions: board_bank.json — ORIGINAL questions written for the school, chapter by chapter on the CBSE 2026-27
//  Class 10 chapter list (NCERT rationalised books); NCERT books and CBSE sample papers are copyrighted, so the app
//  links to the official sites instead of copying them (source to edit: board-bank-src/*.py → gen.py). Teachers and
//  the Principal add their own questions per chapter (brdQ), which join the same tests.
//
//   GET  ?a=home                 student: subjects, chapters, my accuracy per chapter, weak chapters, last tests
//   POST ?a=start {mode: practice|chapter|mock, subj, ch}   picks questions (no answers sent) → run id
//   POST ?a=check {run, qid, choice}                         practice only: right answer + explanation now
//   POST ?a=submit {run, answers:{qid: choice}}              marks it, saves the result, returns the review
//   GET  ?a=history              my results
//   GET  ?a=class                staff: every Class 10 child — tests, average %, weak chapters; class accuracy per chapter
//   POST ?a=addq {subj, ch, q, o[4], a, e} · POST ?a=delq {id} · GET ?a=myq      staff questions
//  Kinds: brdRun (sid|run), brdRes (sid|run), brdQ (id) — 'brd' prefix, never in the normal load or sync.
//  Who: students of Class 10 (only their own data); teachers, Principal, Chairman (view; teachers + Principal add questions).
// ============================================================================
declare(strict_types=1);
require __DIR__ . '/inc.php';

$a = (string)($_GET['a'] ?? 'home');
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
try { $db = sch_db(); } catch (Throwable $e) { sch_fail('Server is not reachable right now. Please try again in a few minutes.', 503); }
$u = sch_auth(); $role = (string)$u['role']; $uid = sch_uid_of($u);
const BRD_CLASSES = ['10th', '10', 'X', 'Class 10'];
const BRD_MODES = ['practice' => ['n' => 10, 'min' => 0], 'chapter' => ['n' => 10, 'min' => 12], 'mock' => ['n' => 30, 'min' => 40]];
$isStu = $role === 'student'; $isStaff = in_array($role, ['teacher', 'principal', 'chairman'], true);
if (!$isStu && !$isStaff) sch_fail('Board Prep is for Class 10 students and teachers.', 403);
$me = null;
if ($isStu) {
    $me = sch_rec_get('student', (string)$u['tid']);
    if (!$me || !in_array((string)($me['cls'] ?? ''), BRD_CLASSES, true)) sch_fail('Board Prep is for Class 10 students. Your class is ' . ($me['cls'] ?? 'not set') . '.', 403);
}
$in = [];
if ($method === 'POST') { $in = json_decode((string)file_get_contents('php://input', false, null, 0, 60000), true); if (!is_array($in)) sch_fail('Bad request.'); }

function brd_rows(string $k, ?string $prefix = null): array {
    $q = sch_db()->prepare('SELECT r, d FROM sch_records WHERE k=? AND deleted=0' . ($prefix !== null ? ' AND r LIKE ?' : ''));
    $q->execute($prefix !== null ? [$k, str_replace(['%', '_'], ['\\%', '\\_'], $prefix) . '%'] : [$k]); $o = [];
    foreach ($q as $row) { $d = json_decode((string)$row['d'], true); if (is_array($d)) $o[(string)$row['r']] = $d; }
    return $o;
}
// subjects → chapters → questions (bank + teachers' questions); $Q = qid → question with subj / ch
function brd_bank(?array &$Q = null): array {
    static $cache = null, $qs = null;
    if ($cache === null) {
        $b = json_decode((string)file_get_contents(__DIR__ . '/board_bank.json'), true); $cache = []; $qs = [];
        foreach ((array)($b['subjects'] ?? []) as $s) {
            $S = ['code' => $s['code'], 'name' => $s['name'], 'chapters' => []];
            foreach ($s['chapters'] as $c) { $S['chapters'][(int)$c['n']] = ['n' => (int)$c['n'], 'name' => $c['name'], 'q' => []]; foreach ($c['q'] as $q) { $q['subj'] = $s['code']; $q['ch'] = (int)$c['n']; $S['chapters'][(int)$c['n']]['q'][] = $q['id']; $qs[$q['id']] = $q; } }
            $cache[$s['code']] = $S;
        }
        $cache['hindi'] = ['code' => 'hindi', 'name' => 'Hindi', 'chapters' => [1 => ['n' => 1, 'name' => "Hindi (teachers' questions)", 'q' => []]]];
        $n = count($cache['english']['chapters'] ?? []) + 1;
        if (isset($cache['english'])) $cache['english']['chapters'][$n] = ['n' => $n, 'name' => "First Flight & Footprints (teachers' questions)", 'q' => []];
        foreach (brd_rows('brdQ') as $id => $q) {
            if (!isset($cache[$q['subj'] ?? '']['chapters'][(int)($q['ch'] ?? 0)])) continue;
            $q['id'] = $id; $q['t'] = 'mcq'; $q['teacher'] = true; $qs[$id] = $q; $cache[$q['subj']]['chapters'][(int)$q['ch']]['q'][] = $id;
        }
    }
    $Q = $qs; return $cache;
}
function brd_public(array $q): array { return ['id' => $q['id'], 't' => $q['t'], 'q' => $q['q'], 'o' => array_values($q['o']), 'subj' => $q['subj'], 'ch' => $q['ch']]; }
function brd_txt($v, int $max): string { return mb_substr(trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', (string)$v)), 0, $max); }
// per-question history of one child: qid → [right, wrong]
function brd_stats(string $sid): array {
    $st = [];
    foreach (brd_rows('brdRes', $sid . '|') as $r) foreach ((array)($r['marks'] ?? []) as $qid => $ok) { $st[$qid] ??= [0, 0]; $st[$qid][$ok ? 0 : 1]++; }
    return $st;
}
function brd_chapter_acc(array $st, array $Q): array {   // "subj|ch" → [right, total]
    $o = [];
    foreach ($st as $qid => [$r, $w]) { if (!isset($Q[$qid])) continue; $k = $Q[$qid]['subj'] . '|' . $Q[$qid]['ch']; $o[$k] ??= [0, 0]; $o[$k][0] += $r; $o[$k][1] += $r + $w; }
    return $o;
}
function brd_weak(array $acc, array $B): array {
    $w = [];
    foreach ($acc as $k => [$r, $t]) { if ($t < 5) continue; [$s, $c] = explode('|', $k); if ($r / $t < 0.6 && isset($B[$s]['chapters'][(int)$c])) $w[] = ['subj' => $s, 'ch' => (int)$c, 'name' => $B[$s]['chapters'][(int)$c]['name'], 'pc' => (int)round($r / $t * 100)]; }
    usort($w, fn($x, $y) => $x['pc'] <=> $y['pc']); return $w;
}

try {
$B = brd_bank($Q);
switch ($a) {
case 'home': {
    $set = sch_rec_get('settings', 'main') ?? [];
    $subs = []; foreach ($B as $s) { $chs = []; foreach ($s['chapters'] as $c) $chs[] = ['n' => $c['n'], 'name' => $c['name'], 'count' => count($c['q'])]; $subs[] = ['code' => $s['code'], 'name' => $s['name'], 'chapters' => $chs]; }
    $out = ['ok' => true, 'role' => $role, 'subjects' => $subs, 'school' => (string)($set['school'] ?? 'SN Mishra Singapore International School'), 'boardDate' => (string)($set['boardDate'] ?? ''), 'total' => count($Q)];
    if ($isStu) {
        $sid = (string)$u['tid']; $acc = brd_chapter_acc(brd_stats($sid), $Q); $res = brd_rows('brdRes', $sid . '|');
        usort($res, fn($x, $y) => $y['at'] <=> $x['at']);
        $out['me'] = ['name' => (string)($me['name'] ?? ''), 'cls' => (string)$me['cls']];
        $out['acc'] = array_map(fn($x) => ['r' => $x[0], 't' => $x[1]], $acc);
        $out['weak'] = brd_weak($acc, $B);
        $out['recent'] = array_map(fn($r) => array_intersect_key($r, array_flip(['mode', 'subj', 'ch', 'score', 'total', 'at', 'secs'])), array_slice(array_values(array_filter($res, fn($r) => $r['mode'] !== 'practice')), 0, 5));
        $out['done'] = ['tests' => count(array_filter($res, fn($r) => $r['mode'] !== 'practice')), 'questions' => array_sum(array_map(fn($r) => (int)$r['total'], $res)), 'right' => array_sum(array_map(fn($r) => (int)$r['score'], $res))];
    } else $out['me'] = ['name' => (string)$u['label'], 'cls' => ''];
    sch_json($out);
}
case 'start': {
    $mode = (string)($in['mode'] ?? ''); if (!isset(BRD_MODES[$mode])) sch_fail('Choose practice, chapter test or mock test.');
    $subj = (string)($in['subj'] ?? ''); $ch = (int)($in['ch'] ?? 0);
    if ($subj !== 'all' && !isset($B[$subj])) sch_fail('Choose a subject.');
    if ($mode !== 'mock' && ($subj === 'all' || !isset($B[$subj]['chapters'][$ch]))) sch_fail('Choose a chapter.');
    $pool = [];
    if ($mode === 'mock') { foreach ($B as $s) if ($subj === 'all' || $s['code'] === $subj) foreach ($s['chapters'] as $c) foreach ($c['q'] as $id) $pool[] = $id; }
    else $pool = $B[$subj]['chapters'][$ch]['q'];
    if (!$pool) sch_fail('No questions in this chapter yet. Ask your teacher to add some.');
    $n = BRD_MODES[$mode]['n'];
    // students see first the questions they got wrong or never tried
    $st = $isStu ? brd_stats((string)$u['tid']) : [];
    shuffle($pool);
    usort($pool, fn($x, $y) => (isset($st[$x]) ? ($st[$x][1] > 0 ? 1 : 2) : 0) <=> (isset($st[$y]) ? ($st[$y][1] > 0 ? 1 : 2) : 0));
    if ($mode === 'mock') {   // spread over chapters: take round-robin by chapter
        $byCh = []; foreach ($pool as $id) $byCh[$Q[$id]['subj'] . '|' . $Q[$id]['ch']][] = $id;
        $keys = array_keys($byCh); shuffle($keys); $pick = [];
        while (count($pick) < $n && $keys) foreach ($keys as $i => $k) { if (count($pick) >= $n) break; if (!$byCh[$k]) { unset($keys[$i]); continue; } $pick[] = array_shift($byCh[$k]); }
        $pool = $pick;
    }
    $ids = array_slice($pool, 0, $n); shuffle($ids);
    $run = bin2hex(random_bytes(8)); $who = $isStu ? (string)$u['tid'] : 'staff:' . $uid;
    sch_rec_put('brdRun', $who . '|' . $run, ['mode' => $mode, 'subj' => $subj, 'ch' => $ch, 'qids' => $ids, 'start' => time(), 'min' => BRD_MODES[$mode]['min'], 'checked' => (object)[]], $uid);
    sch_json(['ok' => true, 'run' => $run, 'mode' => $mode, 'min' => BRD_MODES[$mode]['min'], 'questions' => array_map(fn($id) => brd_public($Q[$id]), $ids),
              'title' => $mode === 'mock' ? 'Mock test · ' . ($subj === 'all' ? 'All subjects' : $B[$subj]['name']) : $B[$subj]['name'] . ' · ' . $B[$subj]['chapters'][$ch]['name']]);
}
case 'check': case 'submit': {
    $who = $isStu ? (string)$u['tid'] : 'staff:' . $uid; $run = (string)($in['run'] ?? '');
    if (!preg_match('/^[a-f0-9]{16}$/', $run)) sch_fail('Start the test again.');
    $R = sch_rec_get('brdRun', $who . '|' . $run); if (!$R) sch_fail('This test is already finished. Start a new one.');
    if ($a === 'check') {
        if ($R['mode'] !== 'practice') sch_fail('Answers are shown at the end of a test.');
        $qid = (string)($in['qid'] ?? ''); if (!in_array($qid, $R['qids'], true)) sch_fail('Unknown question.');
        $ch = (array)$R['checked']; if (!isset($ch[$qid])) { $ch[$qid] = (int)($in['choice'] ?? -1); $R['checked'] = (object)$ch; sch_rec_put('brdRun', $who . '|' . $run, $R, $uid); }
        $q = $Q[$qid]; sch_json(['ok' => true, 'a' => (int)$q['a'], 'e' => (string)($q['e'] ?? ''), 'right' => (int)$ch[$qid] === (int)$q['a']]);
    }
    $ans = (array)($in['answers'] ?? []); if ($R['mode'] === 'practice') foreach ((array)$R['checked'] as $k => $v) $ans[$k] = $v;   // first answer counts in practice
    $marks = []; $score = 0; $review = []; $byCh = [];
    foreach ($R['qids'] as $qid) {
        if (!isset($Q[$qid])) continue; $q = $Q[$qid];
        $c = array_key_exists($qid, $ans) && $ans[$qid] !== null && $ans[$qid] !== '' ? (int)$ans[$qid] : -1; $ok = $c === (int)$q['a'];
        if ($c >= 0 || $R['mode'] !== 'practice') $marks[$qid] = $ok;
        $score += $ok ? 1 : 0; $k = $q['subj'] . '|' . $q['ch']; $byCh[$k] ??= [0, 0]; $byCh[$k][1]++; if ($ok) $byCh[$k][0]++;
        $review[] = brd_public($q) + ['a' => (int)$q['a'], 'e' => (string)($q['e'] ?? ''), 'mine' => $c];
    }
    $secs = max(0, time() - (int)$R['start']); $late = $R['min'] && $secs > $R['min'] * 60 + 90;
    $res = ['mode' => $R['mode'], 'subj' => $R['subj'], 'ch' => $R['ch'], 'score' => $score, 'total' => count($review), 'marks' => (object)$marks, 'byCh' => $byCh, 'at' => sch_now_ms(), 'secs' => $secs, 'late' => $late];
    $db->beginTransaction();
    if ($isStu && ($R['mode'] !== 'practice' || $marks)) sch_rec_put('brdRes', $who . '|' . $run, $res, $uid);
    sch_rec_del('brdRun', $who . '|' . $run, $uid);
    $db->commit();
    $chs = []; foreach ($byCh as $k => [$r, $t]) { [$s, $c] = explode('|', $k); $chs[] = ['subj' => $s, 'ch' => (int)$c, 'name' => $B[$s]['chapters'][(int)$c]['name'] ?? '', 'r' => $r, 't' => $t]; }
    sch_json(['ok' => true, 'score' => $score, 'total' => count($review), 'secs' => $secs, 'late' => $late, 'review' => $review, 'chapters' => $chs]);
}
case 'history': {
    if (!$isStu) sch_fail('Students only.', 403);
    $res = brd_rows('brdRes', (string)$u['tid'] . '|'); usort($res, fn($x, $y) => $y['at'] <=> $x['at']);
    sch_json(['ok' => true, 'rows' => array_map(fn($r) => array_intersect_key($r, array_flip(['mode', 'subj', 'ch', 'score', 'total', 'at', 'secs', 'late'])), array_slice($res, 0, 100))]);
}
case 'class': {
    if (!$isStaff) sch_fail('Teachers only.', 403);
    $kids = []; foreach (brd_rows('student') as $sid => $s) if (empty($s['inactive']) && in_array((string)($s['cls'] ?? ''), BRD_CLASSES, true)) $kids[(string)$sid] = $s;
    $all = brd_rows('brdRes'); $by = []; foreach ($all as $k => $r) $by[explode('|', $k)[0]][] = $r;
    $rows = []; $classAcc = [];
    foreach ($kids as $sid => $s) {
        $R = $by[$sid] ?? []; $tests = array_values(array_filter($R, fn($r) => $r['mode'] !== 'practice')); $st = [];
        foreach ($R as $r) foreach ((array)($r['marks'] ?? []) as $qid => $ok) { $st[$qid] ??= [0, 0]; $st[$qid][$ok ? 0 : 1]++; }
        $acc = brd_chapter_acc($st, $Q); foreach ($acc as $k => [$r, $t]) { $classAcc[$k] ??= [0, 0]; $classAcc[$k][0] += $r; $classAcc[$k][1] += $t; }
        $right = array_sum(array_map(fn($x) => $x[0], $acc)); $tot = array_sum(array_map(fn($x) => $x[1], $acc));
        $rows[] = ['sid' => $sid, 'name' => (string)($s['name'] ?? ''), 'roll' => (string)($s['rollno'] ?? ''), 'tests' => count($tests), 'answered' => $tot, 'pc' => $tot ? (int)round($right / $tot * 100) : null,
                   'mock' => ($m = array_values(array_filter($tests, fn($r) => $r['mode'] === 'mock'))) ? (int)round(max(array_map(fn($r) => $r['total'] ? $r['score'] / $r['total'] * 100 : 0, $m))) : null,
                   'last' => $R ? max(array_column($R, 'at')) : null, 'weak' => array_slice(array_map(fn($w) => $w['name'], brd_weak($acc, $B)), 0, 3)];
    }
    usort($rows, fn($x, $y) => [$y['pc'] ?? -1, $x['name']] <=> [$x['pc'] ?? -1, $y['name']]);
    $chs = []; foreach ($classAcc as $k => [$r, $t]) { [$s, $c] = explode('|', $k); if (isset($B[$s]['chapters'][(int)$c])) $chs[] = ['subj' => $B[$s]['name'], 'name' => $B[$s]['chapters'][(int)$c]['name'], 'pc' => (int)round($r / $t * 100), 't' => $t]; }
    usort($chs, fn($x, $y) => $x['pc'] <=> $y['pc']);
    sch_json(['ok' => true, 'rows' => $rows, 'chapters' => $chs, 'canAdd' => $role !== 'chairman']);
}
case 'addq': {
    if (!in_array($role, ['teacher', 'principal'], true)) sch_fail('Only teachers and the Principal add questions.', 403);
    $subj = (string)($in['subj'] ?? ''); $ch = (int)($in['ch'] ?? 0); if (!isset($B[$subj]['chapters'][$ch])) sch_fail('Choose the subject and chapter.');
    $q = brd_txt($in['q'] ?? '', 600); if (mb_strlen($q) < 5) sch_fail('Write the question.');
    $o = array_map(fn($x) => brd_txt($x, 200), array_slice(array_values((array)($in['o'] ?? [])), 0, 4));
    if (count($o) !== 4 || in_array('', $o, true) || count(array_unique($o)) !== 4) sch_fail('Write four different options.');
    $ans = (int)($in['a'] ?? -1); if ($ans < 0 || $ans > 3) sch_fail('Choose the right option.');
    $id = 'tq' . bin2hex(random_bytes(5));
    sch_rec_put('brdQ', $id, ['subj' => $subj, 'ch' => $ch, 'q' => $q, 'o' => $o, 'a' => $ans, 'e' => brd_txt($in['e'] ?? '', 400), 'by' => (string)$u['label'], 'byUid' => $uid, 'at' => sch_now_ms()], $uid);
    sch_log($u, 'Board Prep', 'Added a question — ' . $B[$subj]['name'] . ' · ' . $B[$subj]['chapters'][$ch]['name']);
    sch_json(['ok' => true, 'id' => $id]);
}
case 'myq': {
    if (!$isStaff) sch_fail('Teachers only.', 403);
    $L = []; foreach (brd_rows('brdQ') as $id => $q) if ($role !== 'teacher' || ($q['byUid'] ?? '') === $uid) $L[] = ['id' => $id] + $q + ['subjName' => $B[$q['subj']]['name'] ?? '', 'chName' => $B[$q['subj']]['chapters'][(int)$q['ch']]['name'] ?? ''];
    usort($L, fn($x, $y) => $y['at'] <=> $x['at']); sch_json(['ok' => true, 'rows' => $L]);
}
case 'delq': {
    $id = (string)($in['id'] ?? ''); $q = preg_match('/^tq[a-f0-9]{10}$/', $id) ? sch_rec_get('brdQ', $id) : null; if (!$q) sch_fail('Question not found.');
    if (!($role === 'principal' || ($role === 'teacher' && ($q['byUid'] ?? '') === $uid))) sch_fail('Only the teacher who added it or the Principal can remove it.', 403);
    sch_rec_del('brdQ', $id, $uid); sch_log($u, 'Board Prep', 'Removed a question: ' . mb_substr((string)$q['q'], 0, 60));
    sch_json(['ok' => true]);
}
default: sch_fail('Unknown action.');
}
} catch (Throwable $e) { if ($db->inTransaction()) $db->rollBack(); throw $e; }
