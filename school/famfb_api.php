<?php
// ============================================================
// Parents' feedback — maakit.in/school/famfb_api.php  (owner, 28 Nov 2026: "feedback kaise liya parents ka, kuchh batana
// chahe to vo kaise hoga, jaise inko ye app sabse achha laga")
//
// Parent / student:   POST ?a=send {rating 1-5, liked[], kind, text}   kind: feedback | suggestion | complaint
//                     GET  ?a=mine                                       what this login sent (+ the school's reply)
// Principal/Chairman: GET  ?a=list                                       everything, newest first, with averages
//                     POST ?a=reply {id, text}                           reply shown to the parent in the app
// Record famFb (kept out of the legacy load by the "fam" prefix). Limit: 5 a day per login.
// ============================================================
declare(strict_types=1);
require __DIR__ . '/inc.php';

const FB_LIKES = ['Homework', 'Attendance', 'Results', 'Calendar', 'Learning Hub', 'Game Zone', 'Class test', 'Leave', 'Notices', 'Design'];

try {
    $db = sch_db(); $u = sch_auth(); $a = (string)($_GET['a'] ?? 'mine'); $uid = sch_uid_of($u); $now = time();
    $in = [];
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') { $in = json_decode(file_get_contents('php://input', false, null, 0, 8000) ?: '', true); if (!is_array($in)) sch_fail('Bad request.'); }
    $all = []; foreach ($db->query("SELECT r, d FROM sch_records WHERE k='famFb' AND deleted=0") as $row) { $d = json_decode((string)$row['d'], true); if (is_array($d)) $all[(string)$row['r']] = $d; }

    if ($a === 'list' || $a === 'reply') {
        if (!in_array($u['role'], ['principal', 'chairman'], true)) sch_fail('Only the Principal and the Chairman can see feedback.', 403);
        if ($a === 'list') {
            usort($all, fn($x, $y) => $y['at'] <=> $x['at']);
            $r = array_filter(array_column($all, 'rating')); $likes = [];
            foreach ($all as $x) foreach ((array)($x['liked'] ?? []) as $l) $likes[$l] = ($likes[$l] ?? 0) + 1;
            arsort($likes);
            sch_json(['ok' => true, 'items' => array_values($all), 'avg' => $r ? round(array_sum($r) / count($r), 1) : null, 'n' => count($all), 'likes' => $likes]);
        }
        $x = $all[(string)($in['id'] ?? '')] ?? null; if (!$x) sch_fail('Feedback not found.');
        $x['reply'] = mb_substr(trim((string)($in['text'] ?? '')), 0, 400); $x['replyBy'] = (string)$u['label']; $x['replyAt'] = $now;
        sch_rec_put('famFb', $x['id'], $x, $uid); sch_json(['ok' => true]);
    }
    if (!in_array($u['role'], ['parent', 'student'], true)) sch_fail('Not allowed.', 403);
    if ($a === 'mine') {
        $mine = array_values(array_filter($all, fn($x) => $x['uid'] === $u['uid'])); usort($mine, fn($x, $y) => $y['at'] <=> $x['at']);
        sch_json(['ok' => true, 'items' => $mine, 'likes' => FB_LIKES]);
    }
    if ($a === 'send') {
        $rating = (int)($in['rating'] ?? 0); $kind = (string)($in['kind'] ?? 'feedback'); $text = mb_substr(trim(preg_replace('/\s+/u', ' ', (string)($in['text'] ?? ''))), 0, 600);
        $liked = array_values(array_intersect(FB_LIKES, array_map('strval', (array)($in['liked'] ?? []))));
        if (!in_array($kind, ['feedback', 'suggestion', 'complaint'], true)) sch_fail('Choose feedback, suggestion or complaint.');
        if ($kind === 'feedback' && ($rating < 1 || $rating > 5)) sch_fail('Please choose 1 to 5 stars.');
        if ($kind !== 'feedback' && mb_strlen($text) < 5) sch_fail('Please write a few words.');
        if (count(array_filter($all, fn($x) => $x['uid'] === $u['uid'] && date('Y-m-d', (int)$x['at']) === date('Y-m-d', $now))) >= 5) sch_fail('Thank you — you have sent 5 messages today. Please send more tomorrow.');
        $kidsTxt = $u['role'] === 'parent' ? implode(', ', array_map(fn($k) => ($k['name'] ?? '') . ' (' . ($k['cls'] ?? '') . ')', sch_children((string)$u['tid']))) : (string)$u['label'];
        $id = 'fb' . substr(hash('sha256', $u['uid'] . '|' . $now . '|' . random_int(0, PHP_INT_MAX)), 0, 14);
        sch_rec_put('famFb', $id, ['id' => $id, 'uid' => (string)$u['uid'], 'role' => $u['role'], 'who' => $kidsTxt, 'phone' => $u['role'] === 'parent' ? (string)$u['tid'] : '',
            'rating' => $kind === 'feedback' ? $rating : null, 'liked' => $liked, 'kind' => $kind, 'text' => $text, 'at' => $now], $uid);
        sch_log($u, 'Family feedback', ucfirst($kind) . ($kind === 'feedback' ? " ($rating★)" : '') . ' received');
        sch_json(['ok' => true]);
    }
    sch_fail('Unknown action.', 404);
} catch (Throwable $e) {
    error_log('school famfb: ' . $e->getMessage());
    sch_fail('Something went wrong on the server. Please try again.', 500);
}
