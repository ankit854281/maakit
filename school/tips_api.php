<?php
// ============================================================
// Classmates' Tricks — maakit.in/school/tips_api.php  (owner, 27 Nov 2026:
// "classmates puchhne ke liye question, formula jo kisi ko nahi pata ho — har student ko naya sikhne ko mile")
//
// Bachcha (student, ya parent apne bachche ke liye):
//   GET  ?a=list&sid=                class ke approved tricks (14 din) + apne bheje hue (kis haal me)
//   POST ?a=post {sid, kind, text, ans?}   kind: trick | formula | question | fact; din me 3
// Teacher (us class ka) / Principal:
//   GET  ?a=tlist                    jaanch ke liye pending + haal ke approved
//   POST ?a=decide {id, status}      approved | rejected
//
// Record: quizTip (legacy sync se bahar, "quiz" prefix). Bina teacher ki manzoori ke koi
// bachcha doosre ko kuch nahi dikha sakta. Class ko sirf chhota naam ("Aman G.") dikhta hai.
// ============================================================
declare(strict_types=1);
require __DIR__ . '/inc.php';

const TIP_KINDS = ['trick', 'formula', 'question', 'fact'];
const TIP_PER_DAY = 3;
const TIP_DAYS = 14;

function tip_rows(): array {
    $q = sch_db()->prepare("SELECT r, d FROM sch_records WHERE k='quizTip' AND deleted=0");
    $q->execute();
    $out = [];
    foreach ($q as $row) { $d = json_decode((string)$row['d'], true); if (is_array($d)) $out[$row['r']] = $d; }
    return $out;
}
function tip_short(string $name): string { $p = preg_split('/\s+/', trim($name)) ?: ['']; return $p[0] . (count($p) > 1 ? ' ' . mb_substr(end($p), 0, 1) . '.' : ''); }
function tip_pub(array $t, array $students): array {
    return ['id' => $t['id'], 'kind' => $t['kind'], 'text' => $t['text'], 'ans' => $t['ans'] ?? '', 'subj' => $t['subj'] ?? '', 'at' => (int)$t['at'],
            'by' => tip_short((string)($students[$t['sid']]['name'] ?? '?')), 'status' => $t['status']];
}

try {
    $u = sch_auth(); $a = (string)($_GET['a'] ?? 'list'); $now = time(); $uid = sch_uid_of($u);
    $in = [];
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') { $in = json_decode(file_get_contents('php://input', false, null, 0, 8000) ?: '', true); if (!is_array($in)) sch_fail('Bad request.'); }
    $students = []; foreach ((function () { $q = sch_db()->query("SELECT r, d FROM sch_records WHERE k='student' AND deleted=0"); foreach ($q as $row) yield $row['r'] => json_decode((string)$row['d'], true); })() as $id => $s) if (is_array($s)) $students[$id] = $s;
    $tips = tip_rows();

    // ================= TEACHER / PRINCIPAL =================
    if ($a === 'tlist' || $a === 'decide') {
        if (!in_array($u['role'], ['teacher', 'principal'], true)) sch_fail('Teachers only.', 403);
        $may = fn(string $cls) => $u['role'] === 'principal' || sch_in_class((string)$u['tid'], $cls);
        if ($a === 'tlist') {
            $mine = array_filter($tips, fn($t) => $may((string)$t['cls']) && ($t['status'] === 'pending' || $t['at'] > $now - TIP_DAYS * 86400));
            usort($mine, fn($x, $y) => [$x['status'] !== 'pending', -$x['at']] <=> [$y['status'] !== 'pending', -$y['at']]);
            sch_json(['ok' => true, 'tips' => array_map(fn($t) => tip_pub($t, $students) + ['cls' => $t['cls'], 'name' => (string)($students[$t['sid']]['name'] ?? '')], array_slice(array_values($mine), 0, 60))]);
        }
        $t = $tips[(string)($in['id'] ?? '')] ?? null; $st = (string)($in['status'] ?? '');
        if (!$t || !$may((string)$t['cls'])) sch_fail('Only a teacher of that class can check this.', 403);
        if (!in_array($st, ['approved', 'rejected'], true)) sch_fail('Choose approve or reject.');
        $t['status'] = $st; $t['by'] = $uid; $t['decidedAt'] = $now;
        sch_rec_put('quizTip', $t['id'], $t, $uid);
        sch_log($u, 'Classmates\' Tricks', ($st === 'approved' ? 'Approved' : 'Rejected') . ' a ' . $t['kind'] . ' from Class ' . $t['cls']);
        sch_json(['ok' => true]);
    }

    // ================= STUDENT / PARENT =================
    if (!in_array($u['role'], ['student', 'parent'], true)) sch_fail('Not allowed.', 403);
    $sid = $u['role'] === 'student' ? (string)$u['tid'] : (string)($_GET['sid'] ?? $in['sid'] ?? '');
    $kids = $u['role'] === 'student' ? [(string)$u['tid']] : array_column(sch_children((string)$u['tid']), 'id');
    if ($sid === '' || !in_array($sid, $kids, true) || !isset($students[$sid])) sch_fail('Choose your own child.', 403);
    $cls = (string)$students[$sid]['cls'];

    if ($a === 'list') {
        $class = array_filter($tips, fn($t) => $t['cls'] === $cls && $t['status'] === 'approved' && $t['at'] > $now - TIP_DAYS * 86400);
        $mine = array_filter($tips, fn($t) => $t['sid'] === $sid && $t['at'] > $now - TIP_DAYS * 86400);
        usort($class, fn($x, $y) => $y['at'] <=> $x['at']); usort($mine, fn($x, $y) => $y['at'] <=> $x['at']);
        $today = count(array_filter($tips, fn($t) => $t['sid'] === $sid && date('Y-m-d', (int)$t['at']) === date('Y-m-d', $now)));
        sch_json(['ok' => true, 'tips' => array_map(fn($t) => tip_pub($t, $students), $class), 'mine' => array_map(fn($t) => tip_pub($t, $students), $mine), 'left' => max(0, TIP_PER_DAY - $today)]);
    }
    if ($a === 'post') {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') sch_fail('POST only', 405);
        $kind = (string)($in['kind'] ?? ''); $text = trim(preg_replace('/\s+/u', ' ', (string)($in['text'] ?? ''))); $ans = trim(preg_replace('/\s+/u', ' ', (string)($in['ans'] ?? '')));
        $subj = trim(mb_substr((string)($in['subj'] ?? ''), 0, 40));
        if (!in_array($kind, TIP_KINDS, true)) sch_fail('Please choose shortcut, formula, question or fact.');
        if (mb_strlen($text) < 10) sch_fail('Please write at least 10 characters.');
        if (mb_strlen($text) > 300 || mb_strlen($ans) > 200) sch_fail('Please keep it within 300 characters (answer: 200).');
        if ($kind === 'question' && $ans === '') sch_fail('Please add the answer as well; your teacher reviews it before your class sees it.');
        $today = count(array_filter($tips, fn($t) => $t['sid'] === $sid && date('Y-m-d', (int)$t['at']) === date('Y-m-d', $now)));
        if ($today >= TIP_PER_DAY) sch_fail('You have shared ' . TIP_PER_DAY . ' posts today. You can share more tomorrow.');
        $id = 't' . substr(hash('sha256', $sid . '|' . $now . '|' . random_int(0, PHP_INT_MAX)), 0, 14);
        sch_rec_put('quizTip', $id, ['id' => $id, 'sid' => $sid, 'cls' => $cls, 'kind' => $kind, 'subj' => $subj, 'text' => $text, 'ans' => $kind === 'question' ? $ans : '', 'at' => $now, 'status' => 'pending'], $uid);
        sch_log($u, 'Classmates\' Tricks', tip_short((string)$students[$sid]['name']) . ' sent a ' . $kind . ' for the teacher to check');
        sch_json(['ok' => true, 'id' => $id]);
    }
    sch_fail('Unknown action.', 404);
} catch (Throwable $e) {
    error_log('school tips: ' . $e->getMessage());
    sch_fail('Something went wrong on the server. Please try again.', 500);
}
