<?php
// ============================================================
// Quiz Duel API — maakit.in/school/quiz_api.php
//
// Bachche (student login, ya parent apne bachche ke liye):
//   GET  ?a=state&sid=            apna sab: topics, sawaal, chunautiyan, roz ka quiz, points
//   POST ?a=ask        {sid, topic:{date,subj,topic}, text, opts[4], ans}
//   POST ?a=challenge  {sid, qid, to}
//   POST ?a=open       {sid, cid}            sawaal + ghadi shuru
//   POST ?a=answer     {sid, cid, choice}
//   POST ?a=quiz       {sid, choice?}        roz ka quiz: shuru / jawab + agla sawaal
// Teacher / Principal:
//   GET  ?a=tstate                           approve karne wale sawaal, kamzor topic, leaderboard, worksheet
//   POST ?a=decide     {qid, status, ans?, text?, opts?, reason?}
//   POST ?a=add        {cls, subj, topic, text, opts, ans}
//
// Sahi jawab kabhi phone par pehle nahi bheja jaata. Ghadi server
// par chalti hai. Points records se gine jaate hain (quiz_lib.php).
// ============================================================
declare(strict_types=1);
require __DIR__ . '/inc.php';
require __DIR__ . '/quiz_lib.php';

function qz_rows(string $k): array {
    $q = sch_db()->prepare('SELECT r, d FROM sch_records WHERE k=? AND deleted=0');
    $q->execute([$k]);
    $out = [];
    foreach ($q as $row) { $d = json_decode((string)$row['d'], true); if (is_array($d)) $out[$row['r']] = $d; }
    return $out;
}
function qz_session(): string { return (string)((sch_rec_get('settings', 'main') ?? [])['session'] ?? ''); }
function qz_public(array $q): array { return ['id' => $q['id'], 'subj' => $q['subj'], 'topic' => $q['topic'], 'text' => $q['text'], 'opts' => $q['opts']]; }

try {
    $db = sch_db();
    $u = sch_auth();
    $a = (string)($_GET['a'] ?? 'state');
    $now = time();
    $today = date('Y-m-d');
    $session = qz_session();
    $uid = sch_uid_of($u);
    $in = [];
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        $in = json_decode(file_get_contents('php://input', false, null, 0, 60000) ?: '', true);
        if (!is_array($in)) sch_fail('Bad request.');
    }
    $students = qz_rows('student');
    $Q = array_filter(qz_rows('quizQ'), fn($q) => ($q['session'] ?? '') === $session);
    $C = array_filter(qz_rows('quizC'), fn($c) => ($c['session'] ?? '') === $session);
    $D = array_filter(qz_rows('quizD'), fn($d) => ($d['session'] ?? '') === $session);

    // ================= TEACHER / PRINCIPAL =================
    if (in_array($a, ['tstate', 'decide', 'add'], true)) {
        if (!in_array($u['role'], ['teacher', 'principal'], true)) sch_fail('Teachers only.', 403);
        $mayClsSubj = fn(string $cls, string $subj) => $u['role'] === 'principal' || sch_teaches((string)$u['tid'], $cls, $subj);

        if ($a === 'tstate') {
            $mine = array_filter($Q, fn($q) => $mayClsSubj((string)$q['cls'], (string)$q['subj']));
            $stats = [];      // har sawaal par kitne sahi / kul
            foreach ($C as $c) { $r = qz_challenge_result($c, $now); if (is_bool($r)) { $stats[$c['qid']]['n'] = ($stats[$c['qid']]['n'] ?? 0) + 1; $stats[$c['qid']]['ok'] = ($stats[$c['qid']]['ok'] ?? 0) + ($r ? 1 : 0); } }
            foreach ($D as $d) foreach ((array)$d['items'] as $it) { $r = qz_item_result($it, $now); if (is_bool($r)) { $stats[$it['qid']]['n'] = ($stats[$it['qid']]['n'] ?? 0) + 1; $stats[$it['qid']]['ok'] = ($stats[$it['qid']]['ok'] ?? 0) + ($r ? 1 : 0); } }
            $out = [];
            foreach ($mine as $id => $q) {
                $q['author'] = !empty($q['bySid']) ? qz_short_name((string)($students[$q['bySid']]['name'] ?? '?')) : sch_teacher_name(substr((string)$q['by'], 2));
                $q['n'] = $stats[$id]['n'] ?? 0; $q['ok'] = $stats[$id]['ok'] ?? 0;
                unset($q['by']);
                $out[] = $q;
            }
            usort($out, fn($x, $y) => ((int)$y['at']) <=> ((int)$x['at']));
            $classes = array_values(array_unique(array_column($out, 'cls')));
            if ($u['role'] === 'principal') $classes = array_values(array_unique(array_column($students, 'cls')));
            else foreach ((array)sch_rec_get('alloc', 'main') as $al) if (($al['t'] ?? '') === $u['tid']) $classes[] = $al['c'];
            $classes = array_values(array_unique($classes));
            $pts = qz_points($Q, $C, $D, $now, date('Y-m'));
            $boards = [];
            foreach ($classes as $cls) {
                $rows = [];
                foreach ($students as $sid => $s) if (($s['cls'] ?? '') === $cls && !empty($pts[$sid]['total'])) $rows[] = ['name' => (string)$s['name'], 'pts' => $pts[$sid]['total']];
                usort($rows, fn($x, $y) => $y['pts'] <=> $x['pts']);
                $boards[$cls] = array_slice($rows, 0, 10);
            }
            sch_json(['ok' => true, 'questions' => $out, 'boards' => $boards]);
        }

        $db->beginTransaction();
        $db->query("SELECT uid FROM sch_users WHERE uid='principal' FOR UPDATE")->fetch();
        if ($a === 'decide') {
            $q = $Q[(string)($in['qid'] ?? '')] ?? null;
            if (!$q || !$mayClsSubj((string)$q['cls'], (string)$q['subj'])) sch_fail('You can check questions only for your own subjects.', 403);
            $st = (string)($in['status'] ?? '');
            if (!in_array($st, ['approved', 'rejected'], true)) sch_fail('Choose approve or reject.');
            if ($st === 'approved' && (isset($in['text']) || isset($in['opts']) || isset($in['ans']))) {
                $fix = qz_clean_question(['text' => $in['text'] ?? $q['text'], 'opts' => $in['opts'] ?? $q['opts'], 'ans' => $in['ans'] ?? $q['ans']]);
                $q = array_merge($q, $fix);
            }
            $q['status'] = $st; $q['reason'] = mb_substr(trim((string)($in['reason'] ?? '')), 0, 200);
            $q['reviewedBy'] = $u['label']; $q['reviewedAt'] = $now;
            sch_rec_put('quizQ', $q['id'], $q, $uid);
            sch_log($u, 'Quiz', ($st === 'approved' ? 'Approved' : 'Rejected') . " a Class {$q['cls']} {$q['subj']} question on \"{$q['topic']}\"");
            $db->commit();
            sch_json(['ok' => true]);
        }
        if ($a === 'add') {
            $cls = (string)($in['cls'] ?? ''); $subj = (string)($in['subj'] ?? ''); $topic = trim((string)($in['topic'] ?? ''));
            $real = array_filter((array)sch_rec_get('alloc', 'main'), fn($al) => ($al['c'] ?? '') === $cls && ($al['s'] ?? '') === $subj);
            if (!$real || !$mayClsSubj($cls, $subj)) sch_fail('Choose a class and subject you teach.', 403);
            if (mb_strlen($topic) < 2 || mb_strlen($topic) > 120) sch_fail('Write the topic.');
            $fix = qz_clean_question($in);
            $id = 'q' . bin2hex(random_bytes(6));
            $q = ['id' => $id, 'session' => $session, 'cls' => $cls, 'subj' => $subj, 'topic' => $topic, 'topicDate' => $today] + $fix
               + ['by' => $uid, 'bySid' => null, 'status' => 'approved', 'at' => $now, 'reviewedBy' => $u['label'], 'reviewedAt' => $now];
            sch_rec_put('quizQ', $id, $q, $uid);
            sch_log($u, 'Quiz', "Added a Class $cls $subj question on \"$topic\"");
            $db->commit();
            sch_json(['ok' => true, 'id' => $id]);
        }
    }

    // ================= STUDENT (ya parent apne bachche ke liye) =================
    if (!in_array($u['role'], ['student', 'parent'], true)) sch_fail('Not allowed.', 403);
    $sid = (string)($_GET['sid'] ?? $in['sid'] ?? '');
    $kids = $u['role'] === 'student' ? [(string)$u['tid']] : array_column(sch_children((string)$u['tid']), 'id');
    if ($u['role'] === 'student') $sid = (string)$u['tid'];
    if ($sid === '' || !in_array($sid, $kids, true) || !isset($students[$sid])) sch_fail('Choose your own child.', 403);
    $me = $students[$sid]; $cls = (string)$me['cls'];
    $mates = array_filter($students, fn($s, $id) => ($s['cls'] ?? '') === $cls && $id !== $sid, ARRAY_FILTER_USE_BOTH);

    // pichhle 14 din me class me kya padhaya gaya (teacher diary se)
    $topics = [];
    $st = $db->prepare("SELECT d, dkey FROM sch_records WHERE k='diary' AND deleted=0 AND dkey >= ?");
    $st->execute([date('Y-m-d', $now - QZ_TOPIC_DAYS * 86400)]);
    foreach ($st as $row) {
        $e = json_decode((string)$row['d'], true);
        if (!is_array($e) || ($e['cls'] ?? '') !== $cls || trim((string)($e['topic'] ?? '')) === '') continue;
        $key = $e['subj'] . '|' . mb_strtolower(trim($e['topic']));
        if (!isset($topics[$key]) || $topics[$key]['date'] < $row['dkey']) $topics[$key] = ['date' => (string)$row['dkey'], 'subj' => (string)$e['subj'], 'topic' => trim((string)$e['topic'])];
    }
    $topics = array_values($topics);
    usort($topics, fn($x, $y) => strcmp($y['date'], $x['date']));

    $seen = [];   // jin sawaalon ka bachcha jawab de chuka
    foreach ($C as $c) if ($c['to'] === $sid) $seen[$c['qid']] = true;
    foreach ($D as $d) if (($d['sid'] ?? '') === $sid) foreach ((array)$d['items'] as $it) $seen[$it['qid']] = true;
    $dKey = $sid . '|' . $today;

    if ($a === 'state') {
        $mineQ = array_values(array_map(function ($q) use ($C, $now) {
            $sends = array_values(array_filter($C, fn($c) => $c['qid'] === $q['id']));
            $q['sent'] = count($sends);
            $q['results'] = array_map(fn($c) => qz_challenge_result($c, $now), $sends);
            unset($q['by'], $q['session']);
            return $q;
        }, array_filter($Q, fn($q) => ($q['bySid'] ?? null) === $sid)));
        usort($mineQ, fn($x, $y) => ((int)$y['at']) <=> ((int)$x['at']));
        $nm = fn($id) => qz_short_name((string)($students[$id]['name'] ?? '?'));
        $fair = qz_fair_map($Q, $C, $D, $now);
        $incoming = []; $outgoing = [];
        foreach ($C as $c) {
            $r = qz_challenge_result($c, $now); $q = $Q[$c['qid']] ?? null; if (!$q) continue;
            if ($c['to'] === $sid) $incoming[] = ['id' => $c['id'], 'from' => $nm($c['from']), 'subj' => $q['subj'], 'topic' => $q['topic'], 'at' => $c['at'], 'opened' => !empty($c['openedAt']), 'result' => $r];
            if ($c['from'] === $sid) $outgoing[] = ['id' => $c['id'], 'to' => $nm($c['to']), 'subj' => $q['subj'], 'topic' => $q['topic'], 'at' => $c['at'], 'result' => $r, 'fair' => !empty($fair[$c['qid']])];
        }
        foreach ([&$incoming, &$outgoing] as &$list) usort($list, fn($x, $y) => $y['at'] <=> $x['at']);
        unset($list);
        $dq = $D[$dKey] ?? null; $quiz = ['state' => 'new'];
        if ($dq) {
            $items = (array)$dq['items']; $right = 0; $cur = null;
            foreach ($items as $it) { $r = qz_item_result($it, $now); if ($r === true) $right++; if ($r === null && $cur === null) $cur = $it; }
            $quiz = $cur ? ['state' => 'open', 'n' => count($items), 'done' => count(array_filter($items, fn($it) => qz_item_result($it, $now) !== null)), 'right' => $right]
                         : ['state' => 'done', 'n' => count($items), 'right' => $right];
        } else {
            $quiz['available'] = count(qz_pick_daily($Q, $sid, $cls, $seen, $today));
        }
        $month = qz_points($Q, $C, $D, $now, date('Y-m'));
        $board = [];
        foreach ($students as $id => $s) if (($s['cls'] ?? '') === $cls && !empty($month[$id]['total'])) $board[] = ['name' => qz_short_name((string)$s['name']), 'pts' => $month[$id]['total'], 'me' => $id === $sid];
        usort($board, fn($x, $y) => $y['pts'] <=> $x['pts']);
        $rank = array_search(true, array_column($board, 'me'), true);
        $sentToday = count(array_filter($C, fn($c) => $c['from'] === $sid && date('Y-m-d', (int)$c['at']) === $today));
        sch_json(['ok' => true, 'kid' => ['id' => $sid, 'name' => $me['name'], 'cls' => $cls],
            'topics' => array_slice($topics, 0, 40), 'mine' => $mineQ, 'incoming' => array_slice($incoming, 0, 15), 'outgoing' => array_slice($outgoing, 0, 15),
            'mates' => array_values(array_map(fn($id) => ['id' => $id, 'name' => $nm($id)], array_keys($mates))),
            'quiz' => $quiz, 'points' => $month[$sid] ?? ['quiz' => 0, 'answered' => 0, 'asked' => 0, 'pending' => 0, 'total' => 0],
            'board' => array_slice($board, 0, 10), 'rank' => $rank === false ? null : $rank + 1, 'sentToday' => $sentToday,
            'rules' => ['challengeSeconds' => QZ_CHALLENGE_SECONDS, 'quizSeconds' => QZ_QUIZ_SECONDS, 'sendsPerDay' => QZ_SENDS_PER_DAY, 'sendsPerQ' => QZ_SENDS_PER_Q, 'asksPerDay' => QZ_ASKS_PER_DAY]]);
    }

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') sch_fail('POST only', 405);
    $db->beginTransaction();
    $db->query("SELECT uid FROM sch_users WHERE uid='principal' FOR UPDATE")->fetch();

    if ($a === 'ask') {
        $t = $in['topic'] ?? null;
        $ok = is_array($t) && array_filter($topics, fn($x) => $x['date'] === ($t['date'] ?? '') && $x['subj'] === ($t['subj'] ?? '') && $x['topic'] === ($t['topic'] ?? ''));
        if (!$ok) sch_fail('Choose a topic your class studied in the last ' . QZ_TOPIC_DAYS . ' days.');
        $todayAsks = count(array_filter($Q, fn($q) => ($q['bySid'] ?? null) === $sid && date('Y-m-d', (int)$q['at']) === $today));
        if ($todayAsks >= QZ_ASKS_PER_DAY) sch_fail('You can make ' . QZ_ASKS_PER_DAY . ' questions a day. Try again tomorrow.');
        $fix = qz_clean_question($in);
        $id = 'q' . bin2hex(random_bytes(6));
        $q = ['id' => $id, 'session' => $session, 'cls' => $cls, 'subj' => $t['subj'], 'topic' => $t['topic'], 'topicDate' => $t['date']] + $fix
           + ['by' => 's:' . $sid, 'bySid' => $sid, 'status' => 'pending', 'at' => $now];
        sch_rec_put('quizQ', $id, $q, $uid);
        sch_log($u, 'Quiz', qz_short_name((string)$me['name']) . " made a {$t['subj']} question on \"{$t['topic']}\" (waiting for teacher)");
        $db->commit();
        sch_json(['ok' => true, 'id' => $id]);
    }

    if ($a === 'challenge') {
        $q = $Q[(string)($in['qid'] ?? '')] ?? null; $to = (string)($in['to'] ?? '');
        if (!$q || ($q['bySid'] ?? null) !== $sid) sch_fail('Choose one of your own questions.');
        if (($q['status'] ?? '') !== 'approved') sch_fail('Your teacher has to approve this question first.');
        if (!isset($mates[$to])) sch_fail('Choose a classmate.');
        $sends = array_filter($C, fn($c) => $c['qid'] === $q['id']);
        if (count($sends) >= QZ_SENDS_PER_Q) sch_fail('This question has already been sent to ' . QZ_SENDS_PER_Q . ' classmates. Make a new one.');
        $seenByTo = array_filter($C, fn($c) => $c['to'] === $to && $c['qid'] === $q['id']);
        foreach ($D as $d) if (($d['sid'] ?? '') === $to) foreach ((array)$d['items'] as $it) if ($it['qid'] === $q['id']) $seenByTo[] = $it;
        if ($seenByTo) sch_fail('This classmate has already seen this question.');
        if (count(array_filter($C, fn($c) => $c['from'] === $sid && date('Y-m-d', (int)$c['at']) === $today)) >= QZ_SENDS_PER_DAY) sch_fail('You can send ' . QZ_SENDS_PER_DAY . ' challenges a day. Try again tomorrow.');
        if (array_filter($C, fn($c) => $c['from'] === $sid && $c['to'] === $to && date('Y-m-d', (int)$c['at']) === $today)) sch_fail('You already challenged this classmate today. Pick someone else.');
        if (array_filter($C, fn($c) => $c['from'] === $sid && $c['to'] === $to && qz_challenge_result($c, $now) === null)) sch_fail('Wait until this classmate answers your last challenge.');
        $id = 'c' . bin2hex(random_bytes(6));
        sch_rec_put('quizC', $id, ['id' => $id, 'session' => $session, 'cls' => $cls, 'qid' => $q['id'], 'from' => $sid, 'to' => $to, 'at' => $now, 'openedAt' => null, 'answeredAt' => null, 'choice' => null, 'correct' => null], $uid);
        sch_log($u, 'Quiz', qz_short_name((string)$me['name']) . ' challenged ' . qz_short_name((string)$students[$to]['name']) . " ({$q['subj']})");
        $db->commit();
        sch_json(['ok' => true]);
    }

    if ($a === 'open' || $a === 'answer') {
        $c = $C[(string)($in['cid'] ?? '')] ?? null;
        if (!$c || $c['to'] !== $sid) sch_fail('This challenge is not for you.', 403);
        $q = $Q[$c['qid']] ?? null; if (!$q) sch_fail('This question was removed.');
        $res = qz_challenge_result($c, $now);
        if ($res === 'expired') sch_fail('This challenge has expired.');
        if ($a === 'open') {
            if ($res !== null) { $db->commit(); sch_json(['ok' => true, 'done' => true, 'correct' => $res, 'ans' => $q['ans'], 'q' => qz_public($q)]); }
            if (empty($c['openedAt'])) { $c['openedAt'] = $now; sch_rec_put('quizC', $c['id'], $c, $uid); }
            $db->commit();
            sch_json(['ok' => true, 'q' => qz_public($q), 'left' => max(0, (int)$c['openedAt'] + QZ_CHALLENGE_SECONDS - $now)]);
        }
        if (empty($c['openedAt'])) sch_fail('Open the challenge first.');
        if ($res !== null) { $db->commit(); sch_json(['ok' => true, 'correct' => $res, 'ans' => $q['ans'], 'late' => true]); }
        $choice = $in['choice'] ?? -1;
        $late = $now > (int)$c['openedAt'] + QZ_CHALLENGE_SECONDS + QZ_GRACE;
        $c['choice'] = is_int($choice) ? $choice : -1; $c['answeredAt'] = $now; $c['correct'] = !$late && $c['choice'] === (int)$q['ans'];
        sch_rec_put('quizC', $c['id'], $c, $uid);
        $db->commit();
        sch_json(['ok' => true, 'correct' => $c['correct'], 'ans' => $q['ans'], 'late' => $late]);
    }

    if ($a === 'quiz') {
        $dq = $D[$dKey] ?? null; $last = null;
        if (!$dq) {
            if (array_key_exists('choice', $in)) sch_fail('Start the quiz first.');
            $pick = qz_pick_daily($Q, $sid, $cls, $seen, $today);
            if (count($pick) < 3) sch_fail('Not enough approved questions on recent topics yet. Make a question, or ask your teacher to add some.');
            $dq = ['sid' => $sid, 'session' => $session, 'cls' => $cls, 'date' => $today, 'items' => array_map(fn($id) => ['qid' => $id, 'servedAt' => null, 'answeredAt' => null, 'choice' => null, 'correct' => null], $pick)];
        } else {
            foreach ($dq['items'] as $i => &$it) {
                if (qz_item_result($it, $now) !== null || empty($it['servedAt'])) continue;
                if (!array_key_exists('choice', $in)) break;        // abhi chal raha hai — wahi sawaal dobara
                $late = $now > (int)$it['servedAt'] + QZ_QUIZ_SECONDS + QZ_GRACE;
                $it['choice'] = is_int($in['choice']) ? $in['choice'] : -1; $it['answeredAt'] = $now;
                $it['correct'] = !$late && $it['choice'] === (int)($Q[$it['qid']]['ans'] ?? -9);
                $last = ['correct' => $it['correct'], 'ans' => (int)($Q[$it['qid']]['ans'] ?? -1), 'late' => $late];
                break;
            }
            unset($it);
            // samay khatam ho chuka par jawab nahi aaya — galat maan lo
            foreach ($dq['items'] as &$it) if (!empty($it['servedAt']) && $it['correct'] === null && qz_item_result($it, $now) === false) { $it['correct'] = false; $it['choice'] = -1; $it['answeredAt'] = $now; }
            unset($it);
        }
        $next = null; $left = 0;
        foreach ($dq['items'] as &$it) {
            if ($it['correct'] !== null) continue;
            if (empty($it['servedAt'])) $it['servedAt'] = $now;
            $q = $Q[$it['qid']] ?? null;
            if (!$q) { $it['correct'] = false; $it['choice'] = -1; continue; }
            $next = qz_public($q); $left = max(0, (int)$it['servedAt'] + QZ_QUIZ_SECONDS - $now);
            break;
        }
        unset($it);
        sch_rec_put('quizD', $dKey, $dq, $uid);
        $right = count(array_filter($dq['items'], fn($it) => $it['correct'] === true));
        $done = count(array_filter($dq['items'], fn($it) => $it['correct'] !== null));
        if (!$next && $last !== null) sch_log($u, 'Quiz', qz_short_name((string)$me['name']) . " finished today's quiz: $right of " . count($dq['items']));
        $db->commit();
        sch_json(['ok' => true, 'last' => $last, 'q' => $next, 'left' => $left, 'n' => count($dq['items']), 'done' => $done, 'right' => $right]);
    }
    sch_fail('Unknown action.', 404);
} catch (InvalidArgumentException $e) {
    if (isset($db) && $db->inTransaction()) $db->rollBack();
    sch_fail($e->getMessage());
} catch (Throwable $e) {
    if (isset($db) && $db->inTransaction()) $db->rollBack();
    error_log('school quiz: ' . $e->getMessage());
    sch_fail('Something went wrong on the server. Please try again.', 500);
}
