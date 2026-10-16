<?php
// ============================================================
// Game Zone API — maakit.in/school/game_api.php
//
// Bachche (student, ya parent apne bachche ke liye):
//   GET  ?a=state&sid=                 level, XP, streak, chest, class mission, league
//   POST ?a=start   {sid, mode}        mode: daily | math | words | revenge
//   POST ?a=answer  {sid, rid, choice} jawab + hasaane wali yaad
//   POST ?a=next    {sid, rid}         agla sawaal (ghadi tabhi shuru hoti hai)
//   POST ?a=flag    {sid, rid, i}      "ye sawaal galat hai" (AI wale sawaal)
// Teacher / Principal:
//   GET  ?a=tpacks                     AI packs, report kiye gaye sawaal
//   POST ?a=makepacks {cls?}           padhaye gaye topics ke packs abhi banao (AI)
//   POST ?a=packfix {pid, i, action}   restore | remove
//
// Records (legacy sync se bahar, "game" prefix): gameR (round), gameMiss
// (galat sawaal — wapas kab aaye), gamePack (AI topic pack), gameLock.
// ============================================================
declare(strict_types=1);
require __DIR__ . '/inc.php';
require_once __DIR__ . '/learning_lib.php';
require __DIR__ . '/game_lib.php';

const GM_ROUNDS_PER_DAY = 4;      // isse zyada nahi — dimaag ko neend me yaad pakka karne do
const GM_SKIP_SUBJ = '/Games|PT|Library|Activity|Drawing|Craft|Rhymes|Play|Moral/i';

function gm_rows(string $k, ?string $prefix = null): array {
    $q = sch_db()->prepare('SELECT r, d FROM sch_records WHERE k=? AND deleted=0' . ($prefix !== null ? ' AND r LIKE ?' : ''));
    $q->execute($prefix !== null ? [$k, $prefix . '%'] : [$k]);
    $out = [];
    foreach ($q as $row) { $d = json_decode((string)$row['d'], true); if (is_array($d)) $out[$row['r']] = $d; }
    return $out;
}
function gm_pid(string $cls, string $subj, string $topic): string { return 'p' . substr(hash('sha256', $cls . '|' . $subj . '|' . mb_strtolower(trim($topic))), 0, 16); }

// Class me kya padhaya gaya: teacher diary (21 din) + syllabus me pure kiye chapter
function gm_taught(string $cls): array {
    $T = [];
    $st = sch_db()->prepare("SELECT d FROM sch_records WHERE k='diary' AND deleted=0 AND dkey >= ?");
    $st->execute([date('Y-m-d', time() - 21 * 86400)]);
    foreach ($st as $row) { $e = json_decode((string)$row['d'], true);
        if (is_array($e) && ($e['cls'] ?? '') === $cls && trim((string)($e['topic'] ?? '')) !== '' && !preg_match(GM_SKIP_SUBJ, (string)$e['subj'])) $T[gm_pid($cls, $e['subj'], $e['topic'])] = ['subj' => $e['subj'], 'topic' => trim($e['topic'])]; }
    foreach (gm_rows('syl', $cls . '|') as $k => $y) { $subj = substr($k, strlen($cls) + 1); if (preg_match(GM_SKIP_SUBJ, $subj)) continue;
        foreach ((array)($y['chapters'] ?? []) as $ch) if (!empty($ch['done'])) $T[gm_pid($cls, $subj, $ch['n'])] = ['subj' => $subj, 'topic' => (string)$ch['n']]; }
    return $T;
}
// AI se packs banao (sirf padhaye gaye topics, jinke pack nahi bane). $max = ek baar me kitne.
function gm_make_packs(string $cls, int $max, string $uid): array {
    if (!gm_ai_ready()) return ['made' => 0, 'error' => 'AI is not connected'];
    $have = gm_rows('gamePack'); $made = 0; $fail = 0;
    foreach (gm_taught($cls) as $pid => $t) {
        if ($made + $fail >= $max) break;
        if (isset($have[$pid]) && (!empty($have[$pid]['qs']) || time() - (int)($have[$pid]['tried'] ?? 0) < 6 * 3600)) continue;
        try { $qs = gm_ai_pack($cls, $t['subj'], $t['topic']); } catch (Throwable $e) { $qs = []; error_log('game pack: ' . $e->getMessage()); }
        sch_rec_put('gamePack', $pid, ['id' => $pid, 'cls' => $cls, 'subj' => $t['subj'], 'topic' => $t['topic'], 'qs' => $qs, 'hidden' => [], 'flags' => (object)[], 'tried' => time(), 'made' => $qs ? time() : null], $uid);
        $qs ? $made++ : $fail++;
    }
    return ['made' => $made, 'failed' => $fail];
}
// Ek item ka sawaal (jawab samet) — server par hi
function gm_item_q(array $it, string $cls, array $packs, array $duelQ): ?array {
    $b = gm_band($cls);
    switch ($it['src']) {
        case 'math': return gm_math($cls, (string)$it['ref']);
        case 'word': return gm_word((string)$it['ref'], $b);
        case 'pack': [$pid, $i] = explode('#', (string)$it['ref']); $p = $packs[$pid] ?? null;
            if (!$p || in_array((int)$i, (array)($p['hidden'] ?? []), true) || !isset($p['qs'][(int)$i])) return null;
            return $p['qs'][(int)$i] + ['subj' => $p['subj'], 'topic' => $p['topic']];
        case 'duel': $q = $duelQ[(string)$it['ref']] ?? null; if (!$q || ($q['status'] ?? '') !== 'approved') return null;
            return ['q' => $q['text'], 'opts' => $q['opts'], 'ans' => (int)$q['ans'], 'fun' => 'This question was made by a classmate and checked by your teacher. 🤝', 'rule' => $q['topic'] . ' (' . $q['subj'] . ')', 'word' => null, 'subj' => $q['subj'], 'topic' => $q['topic']];
    }
    return null;
}
function gm_public(array $q, array $it): array { return ['q' => $q['q'], 'opts' => $q['opts'], 'src' => $it['src'], 'subj' => $q['subj'] ?? ($it['src'] === 'math' ? 'Maths' : ($it['src'] === 'word' ? 'English words' : '')), 'topic' => $q['topic'] ?? '']; }

try {
    $db = sch_db(); $u = sch_auth(); $a = (string)($_GET['a'] ?? 'state'); $now = time(); $today = date('Y-m-d'); $uid = sch_uid_of($u);
    $in = [];
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') { $in = json_decode(file_get_contents('php://input', false, null, 0, 20000) ?: '', true); if (!is_array($in)) sch_fail('Bad request.'); }
    $students = gm_rows('student');

    // ================= TEACHER / PRINCIPAL =================
    if (in_array($a, ['tpacks', 'makepacks', 'packfix'], true)) {
        if (!in_array($u['role'], ['teacher', 'principal'], true)) sch_fail('Teachers only.', 403);
        $myCls = $u['role'] === 'principal' ? array_values(array_unique(array_column($students, 'cls')))
               : array_values(array_unique(array_merge(array_column(array_filter((array)sch_rec_get('alloc', 'main'), fn($x) => ($x['t'] ?? '') === $u['tid']), 'c'),
                                                       array_column(array_filter((array)sch_rec_get('classes', 'main'), fn($x) => ($x['ct'] ?? '') === $u['tid']), 'c'))));
        if ($a === 'tpacks') {
            $packs = array_values(array_filter(gm_rows('gamePack'), fn($p) => in_array($p['cls'], $myCls, true)));
            $taught = []; foreach ($myCls as $c) $taught[$c] = count(gm_taught($c));
            foreach ($packs as &$p) { $p['flagged'] = []; foreach ((array)($p['flags'] ?? []) as $i => $who) $p['flagged'][] = ['i' => (int)$i, 'n' => count((array)$who), 'hidden' => in_array((int)$i, (array)$p['hidden'], true), 'q' => $p['qs'][(int)$i] ?? null]; unset($p['flags']); }
            unset($p);
            sch_json(['ok' => true, 'aiReady' => gm_ai_ready(), 'packs' => $packs, 'taught' => $taught]);
        }
        if ($a === 'makepacks') {
            @set_time_limit(300);
            $cls = (string)($in['cls'] ?? ''); $list = $cls !== '' ? [$cls] : $myCls;
            if ($cls !== '' && !in_array($cls, $myCls, true)) sch_fail('Choose one of your classes.', 403);
            $made = 0; $failed = 0; $err = null;
            foreach ($list as $c) { $r = gm_make_packs($c, 3, $uid); $made += $r['made'] ?? 0; $failed += $r['failed'] ?? 0; $err = $r['error'] ?? $err; if ($made + $failed >= 6) break; }
            if ($err && !$made) sch_fail($err . '. Ask the office to add the AI key; Maths and English games work without it.');
            sch_log($u, 'Game Zone', "Made $made question pack(s)" . ($failed ? ", $failed topic(s) could not be made" : ''));
            sch_json(['ok' => true, 'made' => $made, 'failed' => $failed]);
        }
        $p = gm_rows('gamePack')[(string)($in['pid'] ?? '')] ?? null; $i = (int)($in['i'] ?? -1);
        if (!$p || !in_array($p['cls'], $myCls, true) || ($u['role'] === 'teacher' && !sch_teaches((string)$u['tid'], $p['cls'], $p['subj']))) sch_fail('Only the subject teacher can fix this pack.', 403);
        $act = (string)($in['action'] ?? '');
        if ($act === 'restore') { $p['hidden'] = array_values(array_diff((array)$p['hidden'], [$i])); $f = (array)$p['flags']; unset($f[(string)$i]); $p['flags'] = (object)$f; }
        elseif ($act === 'remove') { $p['hidden'] = array_values(array_unique(array_merge((array)$p['hidden'], [$i]))); $p['removed'][] = $i; }
        else sch_fail('Choose restore or remove.');
        sch_rec_put('gamePack', $p['id'], $p, $uid);
        sch_log($u, 'Game Zone', ($act === 'restore' ? 'Restored' : 'Removed') . " a question in {$p['cls']} {$p['subj']} \"{$p['topic']}\"");
        sch_json(['ok' => true]);
    }

    // ================= STUDENT / PARENT =================
    if (!in_array($u['role'], ['student', 'parent'], true)) sch_fail('Not allowed.', 403);
    $sid = $u['role'] === 'student' ? (string)$u['tid'] : (string)($_GET['sid'] ?? $in['sid'] ?? '');
    $kids = $u['role'] === 'student' ? [(string)$u['tid']] : array_column(sch_children((string)$u['tid']), 'id');
    if ($sid === '' || !in_array($sid, $kids, true) || !isset($students[$sid])) sch_fail('Choose your own child.', 403);
    $me = $students[$sid]; $cls = (string)$me['cls']; $band = gm_band($cls);
    $rounds = gm_rows('gameR', $sid . '|');            // sirf is bachche ke round (key "sid|date|n")
    $miss = sch_rec_get('gameMiss', $sid) ?? ['items' => []];
    $due = array_values(array_filter((array)$miss['items'], fn($m) => ($m['due'] ?? '9') <= $today));
    $todayRounds = array_filter($rounds, fn($r) => ($r['sid'] ?? '') === $sid && ($r['date'] ?? '') === $today);

    if ($a === 'state') {
        $st = gm_stats($rounds, $sid, $now, $today);
        $wk = date('o-W'); $classRight = 0; $weekXp = [];
        $q = $db->prepare("SELECT d FROM sch_records WHERE k='gameR' AND deleted=0 AND updated_at >= ?");
        $q->execute([date('Y-m-d', strtotime('monday this week'))]);
        $week = []; foreach ($q as $row) { $x = json_decode((string)$row['d'], true); if (is_array($x)) $week[] = $x; }
        foreach ($week as $r) {
            if (($r['cls'] ?? '') !== $cls || date('o-W', strtotime((string)$r['date'])) !== $wk) continue;
            foreach ((array)$r['items'] as $it) if (gm_item_result($it, $now) === true) $classRight++;
            $weekXp[$r['sid']] = ($weekXp[$r['sid']] ?? 0) + gm_round_xp($r, $now);
        }
        arsort($weekXp);
        $league = []; foreach (array_slice($weekXp, 0, 10, true) as $id => $x) if ($x > 0) $league[] = ['name' => qz_short_name_gm((string)($students[$id]['name'] ?? '?')), 'xp' => $x, 'me' => $id === $sid];
        $classSize = count(array_filter($students, fn($s) => ($s['cls'] ?? '') === $cls));
        $packs = array_filter(gm_rows('gamePack'), fn($p) => $p['cls'] === $cls && !empty($p['qs']));
        $chests = []; for ($n = 1; $n <= $st['chests']; $n++) $chests[] = gm_chest_reward($sid, $n);
        $resp = ['ok' => true, 'kid' => ['id' => $sid, 'name' => $me['name'], 'cls' => $cls], 'stats' => $st + ['levelName' => gm_level_name($st['level'])],
            'stickers' => $chests, 'revengeDue' => count($due), 'roundsLeft' => max(0, GM_ROUNDS_PER_DAY - count($todayRounds)), 'words' => $band >= 2,
            'mission' => ['right' => $classRight, 'target' => max(100, 25 * $classSize)], 'league' => $league,
            'topicPacks' => count($packs), 'aiReady' => gm_ai_ready(), 'seconds' => GM_SECONDS, 'open' => null];
        foreach ($todayRounds as $r) if (array_filter((array)$r['items'], fn($it) => $it['correct'] === null))
            $resp['open'] = ['rid' => $r['id'], 'mode' => $r['mode'], 'n' => count($r['items']), 'done' => count(array_filter((array)$r['items'], fn($it) => $it['correct'] !== null))];
        // Jawab bhej do, phir (agar ho sake) peeche se is class ke ek naye topic ka pack bana do
        if (gm_ai_ready() && function_exists('fastcgi_finish_request')) {
            $lock = sch_rec_get('gameLock', $cls);
            if (!$lock || $now - (int)$lock['at'] > 600) {
                sch_rec_put('gameLock', $cls, ['at' => $now], $uid);
                header('Content-Type: application/json; charset=utf-8'); header('Cache-Control: no-store');
                echo json_encode($resp, JSON_UNESCAPED_UNICODE); fastcgi_finish_request();
                @set_time_limit(180); gm_make_packs($cls, 1, $uid); exit;
            }
        }
        sch_json($resp);
    }

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') sch_fail('POST only', 405);
    $packs = array_filter(gm_rows('gamePack'), fn($p) => $p['cls'] === $cls);
    $duelQ = array_filter(gm_rows('quizQ'), fn($q) => ($q['cls'] ?? '') === $cls && ($q['status'] ?? '') === 'approved' && ($q['bySid'] ?? null) !== $sid);

    if ($a === 'start') {
        $mode = (string)($in['mode'] ?? 'daily');
        if (!in_array($mode, ['daily', 'math', 'words', 'revenge'], true)) sch_fail('Choose a game.');
        if (count($todayRounds) >= GM_ROUNDS_PER_DAY) sch_fail('That is ' . GM_ROUNDS_PER_DAY . ' rounds today — brilliant! Your brain stores it while you sleep. Come back tomorrow. 😴🧠');
        foreach ($todayRounds as $r) foreach ((array)$r['items'] as $it) if (gm_item_result($it, $now) === null) sch_fail('Finish the round you started first.');
        if ($mode === 'words' && $band < 2) sch_fail('Word games start from Class 3.');
        $n = count($todayRounds) + 1; $seed = $sid . '|' . $today . '|' . $n; $items = [];
        if ($mode === 'revenge') {
            if (!$due) sch_fail('No revenge questions today — you have beaten them all! 💪');
            foreach (array_slice($due, 0, GM_ROUND) as $m) $items[] = ['src' => $m['src'], 'ref' => $m['ref']];
        } else {
            $pool = [];
            if ($mode === 'daily') {
                // jo padha hai wo pehle: AI topic packs (2), dost ke sawaal (1)
                $seen = []; foreach ($rounds as $r) if (($r['sid'] ?? '') === $sid) foreach ((array)$r['items'] as $it) $seen[$it['src'] . ':' . $it['ref']] = true;
                $pk = []; foreach ($packs as $p) foreach ((array)$p['qs'] as $i => $q) if (!in_array($i, (array)$p['hidden'], true) && !isset($seen['pack:' . $p['id'] . '#' . $i])) $pk[] = $p['id'] . '#' . $i;
                usort($pk, fn($x, $y) => strcmp(hash('md5', $seed . $x), hash('md5', $seed . $y)));
                foreach (array_slice($pk, 0, 2) as $ref) $pool[] = ['src' => 'pack', 'ref' => $ref];
                $dq = array_keys(array_filter($duelQ, fn($q, $id) => !isset($seen['duel:' . $id]), ARRAY_FILTER_USE_BOTH));
                usort($dq, fn($x, $y) => strcmp(hash('md5', $seed . $x), hash('md5', $seed . $y)));
                foreach (array_slice($dq, 0, 1) as $ref) $pool[] = ['src' => 'duel', 'ref' => $ref];
            }
            $i = 0;
            while (count($pool) < GM_ROUND) {
                $src = $mode === 'math' ? 'math' : ($mode === 'words' ? 'word' : ($band >= 2 && $i % 2 ? 'word' : 'math'));
                $pool[] = ['src' => $src, 'ref' => $seed . '|' . $i++];
            }
            $items = $pool;
        }
        $items = array_map(fn($it) => $it + ['servedAt' => null, 'answeredAt' => null, 'choice' => null, 'correct' => null], $items);
        $items[0]['servedAt'] = $now;
        $q = gm_item_q($items[0], $cls, $packs, $duelQ);
        if (!$q) { $items[0] = ['src' => 'math', 'ref' => $seed . '|x', 'servedAt' => $now, 'answeredAt' => null, 'choice' => null, 'correct' => null]; $q = gm_item_q($items[0], $cls, $packs, $duelQ); }
        $rid = $sid . '|' . $today . '|' . $n;
        sch_rec_put('gameR', $rid, ['id' => $rid, 'sid' => $sid, 'cls' => $cls, 'date' => $today, 'mode' => $mode, 'items' => $items], $uid);
        sch_json(['ok' => true, 'rid' => $rid, 'q' => gm_public($q, $items[0]), 'i' => 0, 'n' => count($items), 'left' => GM_SECONDS]);
    }

    if ($a === 'answer' || $a === 'flag' || $a === 'next') {
        $rid = (string)($in['rid'] ?? ''); $r = $rounds[$rid] ?? null;
        if (!$r || ($r['sid'] ?? '') !== $sid) sch_fail('This round is not yours.', 403);
        if ($a === 'flag') {
            $it = $r['items'][(int)($in['i'] ?? -1)] ?? null;
            if (!$it || $it['src'] !== 'pack' || $it['correct'] === null) sch_fail('You can report a question after answering it.');
            [$pid, $qi] = explode('#', (string)$it['ref']); $p = $packs[$pid] ?? null; if (!$p) sch_fail('Question not found.');
            $f = (array)($p['flags'] ?? []); $f[$qi] = array_values(array_unique(array_merge((array)($f[$qi] ?? []), [$sid])));
            if (count($f[$qi]) >= 2) $p['hidden'] = array_values(array_unique(array_merge((array)$p['hidden'], [(int)$qi])));
            $p['flags'] = (object)$f; sch_rec_put('gamePack', $pid, $p, $uid);
            sch_log($u, 'Game Zone', 'A student reported a ' . $p['subj'] . ' question on "' . $p['topic'] . '"' . (count($f[$qi]) >= 2 ? ' — hidden until a teacher checks it' : ''));
            sch_json(['ok' => true, 'hidden' => count($f[$qi]) >= 2]);
        }
        if ($a === 'next') {
            foreach ($r['items'] as $k => $x) {
                if ($x['correct'] !== null) continue;
                $nq = gm_item_q($x, $cls, $packs, $duelQ);
                if (!$nq) { $r['items'][$k]['correct'] = false; $r['items'][$k]['choice'] = -1; continue; }   // sawaal hata diya gaya — chhod do
                if (empty($x['servedAt'])) $r['items'][$k]['servedAt'] = $now;
                sch_rec_put('gameR', $rid, $r, $uid);
                sch_json(['ok' => true, 'q' => gm_public($nq, $x), 'i' => $k, 'n' => count($r['items']), 'left' => max(0, (int)$r['items'][$k]['servedAt'] + GM_SECONDS - $now)]);
            }
            sch_rec_put('gameR', $rid, $r, $uid);
            sch_json(['ok' => true, 'q' => null]);
        }
        $before = gm_stats($rounds, $sid, $now, $today);
        $cur = null; foreach ($r['items'] as $k => $it) if ($it['correct'] === null) { $cur = $k; break; }
        if ($cur === null) sch_fail('This round is already finished.');
        if (empty($r['items'][$cur]['servedAt'])) sch_fail('Open the question first.');
        $it = &$r['items'][$cur];
        $q = gm_item_q($it, $cls, $packs, $duelQ);
        $late = empty($it['servedAt']) || $now > (int)$it['servedAt'] + GM_SECONDS + GM_GRACE;
        $choice = is_int($in['choice'] ?? null) ? $in['choice'] : -1;
        $it['choice'] = $choice; $it['answeredAt'] = $now; $it['correct'] = $q && !$late && $choice === (int)$q['ans'];
        // galat = baad me wapas (1, 3, 7 din). Revenge me sahi = agla padaav, aakhri ke baad hata do.
        $key = $it['src'] . ':' . $it['ref']; $list = [];
        foreach ((array)$miss['items'] as $m) $list[$m['src'] . ':' . $m['ref']] = $m;
        if (!$it['correct']) $list[$key] = ['src' => $it['src'], 'ref' => $it['ref'], 'step' => 0, 'due' => date('Y-m-d', strtotime($today . ' +' . GM_REVIEW_DAYS[0] . ' day'))];
        elseif (isset($list[$key])) { $s = (int)$list[$key]['step'] + 1;
            if ($s >= count(GM_REVIEW_DAYS)) unset($list[$key]); else $list[$key] = ['due' => date('Y-m-d', strtotime($today . ' +' . GM_REVIEW_DAYS[$s] . ' day')), 'step' => $s] + $list[$key]; }
        $miss['items'] = array_values($list); sch_rec_put('gameMiss', $sid, $miss, $uid);
        $combo = 0; foreach (array_slice($r['items'], 0, $cur + 1) as $x) $combo = $x['correct'] ? $combo + 1 : 0;
        unset($it);
        $next = null;
        for ($k = $cur + 1; $k < count($r['items']); $k++) { if (gm_item_q($r['items'][$k], $cls, $packs, $duelQ)) { $next = $k; break; } $r['items'][$k]['correct'] = false; $r['items'][$k]['choice'] = -1; }
        sch_rec_put('gameR', $rid, $r, $uid);
        $rounds[$rid] = $r; $after = gm_stats($rounds, $sid, $now, $today);
        $chest = $after['chests'] > $before['chests'] ? gm_chest_reward($sid, $after['chests']) : null;
        if ($next === null) sch_log($u, 'Game Zone', qz_short_name_gm((string)$me['name']) . ' finished a ' . $r['mode'] . ' round: ' . count(array_filter($r['items'], fn($x) => $x['correct'])) . ' of ' . count($r['items']));
        sch_json(['ok' => true, 'correct' => (bool)$r['items'][$cur]['correct'], 'late' => $late, 'ans' => $q ? (int)$q['ans'] : -1,
            'fun' => $q['fun'] ?? '', 'rule' => $q['rule'] ?? '', 'word' => $q['word'] ?? null, 'combo' => $combo, 'flaggable' => $r['items'][$cur]['src'] === 'pack', 'i' => $cur,
            'xp' => $after['xp'] - $before['xp'], 'levelUp' => $after['level'] > $before['level'] ? gm_level_name($after['level']) : null, 'chest' => $chest,
            'more' => $next !== null,
            'summary' => $next === null ? ['right' => count(array_filter($r['items'], fn($x) => $x['correct'])), 'n' => count($r['items']), 'roundXp' => gm_round_xp($r, $now)] : null]);
    }
    sch_fail('Unknown action.', 404);
} catch (InvalidArgumentException $e) {
    sch_fail($e->getMessage());
} catch (Throwable $e) {
    error_log('school game: ' . $e->getMessage());
    sch_fail('Something went wrong on the server. Please try again.', 500);
}

function qz_short_name_gm(string $name): string { $p = preg_split('/\s+/', trim($name)) ?: ['']; return $p[0] . (count($p) > 1 ? ' ' . mb_substr(end($p), 0, 1) . '.' : ''); }
