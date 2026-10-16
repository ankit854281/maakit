<?php
// ============================================================
// Quiz Duel — niyam aur ginti (pure functions, test ho sakte hain)
//
// Records (sch_records, legacy sync se bahar — inc.php "quiz" prefix):
//   quizQ  id         sawaal: cls, subj, topic, topicDate, text, opts[4], ans (0-3),
//                     by ('s:<sid>' ya 't:<tid>'), bySid, status pending|approved|rejected
//   quizC  id         chunauti: qid, from, to (student ids), at, openedAt, answeredAt, choice, correct
//   quizD  sid|date   roz ka quiz: items [{qid, servedAt, answeredAt, choice, correct}]
//
// Points kabhi save nahi hote — har baar records se gine jaate hain,
// isliye koi unhe seedhe badal nahi sakta.
// ============================================================
declare(strict_types=1);

const QZ_CHALLENGE_SECONDS = 45;   // chunauti ka jawab
const QZ_QUIZ_SECONDS      = 30;   // roz ke quiz ka har sawaal
const QZ_GRACE             = 5;    // net ki deri ke liye
const QZ_DAILY_QUIZ        = 5;    // roz ke quiz me sawaal
const QZ_SENDS_PER_Q       = 3;    // ek sawaal kitne doston ko bheja ja sakta hai
const QZ_SENDS_PER_DAY     = 3;    // ek din me kitni chunautiyan
const QZ_ASKS_PER_DAY      = 5;    // ek din me kitne naye sawaal
const QZ_PAIR_WEEK_CAP     = 2;    // ek hi jodi (ek disha) se hafte me kitni baar points
const QZ_TOPIC_DAYS        = 14;   // kitne din purane padhe topic par sawaal
const QZ_EXPIRE_DAYS       = 3;    // na khuli chunauti kitne din baad khatam

function qz_week(int $ts): string { return date('o-W', $ts); }

// Ek chunauti ka nateeja: null = abhi baaki, true/false = sahi/galat, 'expired'
function qz_challenge_result(array $c, int $now) {
    if (array_key_exists('correct', $c) && $c['correct'] !== null) return (bool)$c['correct'];
    if (!empty($c['openedAt']) && $now > (int)$c['openedAt'] + QZ_CHALLENGE_SECONDS + QZ_GRACE) return false;   // khola, jawab nahi diya
    if (empty($c['openedAt']) && $now > (int)$c['at'] + QZ_EXPIRE_DAYS * 86400) return 'expired';
    return null;
}
function qz_item_result(array $it, int $now) {
    if (array_key_exists('correct', $it) && $it['correct'] !== null) return (bool)$it['correct'];
    if (!empty($it['servedAt']) && $now > (int)$it['servedAt'] + QZ_QUIZ_SECONDS + QZ_GRACE) return false;
    return null;
}

// Sawaal "fair" hai jab lekhak ke alawa kisi ek bachche ne use sahi kiya ho
function qz_fair_map(array $Q, array $C, array $D, int $now): array {
    $fair = [];
    foreach ($C as $c) if (qz_challenge_result($c, $now) === true && ($Q[$c['qid']]['bySid'] ?? null) !== $c['to']) $fair[$c['qid']] = true;
    foreach ($D as $d) foreach ((array)($d['items'] ?? []) as $it)
        if (qz_item_result($it, $now) === true && ($Q[$it['qid']]['bySid'] ?? null) !== ($d['sid'] ?? null)) $fair[$it['qid']] = true;
    return $fair;
}

// Points: $ym = 'YYYY-MM' (sirf us mahine ke), ya '' = poora session
function qz_points(array $Q, array $C, array $D, int $now, string $ym = ''): array {
    $P = [];
    $add = function (string $sid, string $kind, int $n) use (&$P) {
        $P[$sid] = $P[$sid] ?? ['quiz' => 0, 'answered' => 0, 'asked' => 0, 'pending' => 0, 'total' => 0];
        $P[$sid][$kind] += $n; if ($kind !== 'pending') $P[$sid]['total'] += $n;
    };
    foreach ($D as $d) {
        if ($ym !== '' && substr((string)($d['date'] ?? ''), 0, 7) !== $ym) continue;
        $items = (array)($d['items'] ?? []); $right = 0;
        foreach ($items as $it) if (qz_item_result($it, $now) === true) $right++;
        if ($right) $add((string)$d['sid'], 'quiz', $right);
        if ($right === QZ_DAILY_QUIZ && count($items) === QZ_DAILY_QUIZ) $add((string)$d['sid'], 'quiz', 2);   // poore sahi = +2 bonus
    }
    $fair = qz_fair_map($Q, $C, $D, $now);
    usort($C, fn($a, $b) => ((int)$a['at']) <=> ((int)$b['at']));
    $pair = [];
    foreach ($C as $c) {
        $res = qz_challenge_result($c, $now);
        if ($res === null || $res === 'expired') continue;
        $when = (int)($c['answeredAt'] ?? $c['openedAt'] ?? $c['at']);
        if ($ym !== '' && date('Y-m', $when) !== $ym) continue;
        $wk = qz_week($when);
        if ($res === true) {
            $k = $c['to'] . '>' . $c['from'] . '|A|' . $wk;
            if (($pair[$k] = ($pair[$k] ?? 0) + 1) <= QZ_PAIR_WEEK_CAP) $add((string)$c['to'], 'answered', 2);
        } else {
            if (empty($fair[$c['qid']])) { $add((string)$c['from'], 'pending', 1); continue; }   // pehle koi aur sahi kare
            $k = $c['from'] . '>' . $c['to'] . '|Q|' . $wk;
            if (($pair[$k] = ($pair[$k] ?? 0) + 1) <= QZ_PAIR_WEEK_CAP) $add((string)$c['from'], 'asked', 1);
        }
    }
    return $P;
}

// Roz ke quiz ke liye sawaal: apni class ke, approved, haal me padhe topic ke,
// khud ka likha nahi, pehle jawab diya hua nahi. Har bachche ka kram alag.
function qz_pick_daily(array $Q, string $sid, string $cls, array $seen, string $date, int $n = QZ_DAILY_QUIZ): array {
    $from = date('Y-m-d', strtotime($date . ' -' . (QZ_TOPIC_DAYS + 7) . ' days'));
    $pool = [];
    foreach ($Q as $id => $q) {
        if (($q['status'] ?? '') !== 'approved' || ($q['cls'] ?? '') !== $cls || ($q['bySid'] ?? null) === $sid || isset($seen[$id])) continue;
        if ((string)($q['topicDate'] ?? '') < $from) continue;
        $pool[] = (string)$id;
    }
    usort($pool, fn($a, $b) => strcmp(hash('sha256', $sid . $date . $a), hash('sha256', $sid . $date . $b)));
    return array_slice($pool, 0, $n);
}

function qz_clean_question(array $in): array {
    $text = trim((string)($in['text'] ?? ''));
    if (mb_strlen($text) < 8 || mb_strlen($text) > 300) throw new InvalidArgumentException('Write a question of 8 to 300 letters.');
    $opts = $in['opts'] ?? null;
    if (!is_array($opts) || count($opts) !== 4) throw new InvalidArgumentException('Give exactly 4 answer choices.');
    $clean = [];
    foreach (array_values($opts) as $o) { $o = trim((string)$o); if ($o === '' || mb_strlen($o) > 120) throw new InvalidArgumentException('Each choice needs 1 to 120 letters.'); $clean[] = $o; }
    if (count(array_unique(array_map('mb_strtolower', $clean))) !== 4) throw new InvalidArgumentException('The 4 choices must all be different.');
    $ans = $in['ans'] ?? null;
    if (!is_int($ans) || $ans < 0 || $ans > 3) throw new InvalidArgumentException('Mark which choice is correct.');
    return ['text' => $text, 'opts' => $clean, 'ans' => $ans];
}

function qz_short_name(string $name): string {
    $p = preg_split('/\s+/', trim($name)) ?: [''];
    return $p[0] . (count($p) > 1 ? ' ' . mb_substr(end($p), 0, 1) . '.' : '');
}
