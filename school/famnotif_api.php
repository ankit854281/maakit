<?php
// ============================================================
// Family notifications — maakit.in/school/famnotif_api.php  (owner, 28 Nov 2026: "notification box bhi add kariye — bachcha
// absent ho to jaye, homework nahi kiye rahe to message jaye")
//
// Parent / student:  GET ?a=list[&since=<unix seconds>]   newest first, last 14 days, only this login's own children
// Nothing is stored: every notification is built from the school's own records when asked —
//   absent / on leave (studAtt) · homework not done or half done (tchChk) · copy incomplete (tchCopy) · today's homework
//   written in the diary (diary, one per class per day) · leave approved / not approved (famLeave) · new notice (notice)
//   · the school's reply to feedback (famFb)
// The app's bell shows them; the Android app checks this every 15–20 minutes and shows new ones as phone notifications.
// ============================================================
declare(strict_types=1);
require __DIR__ . '/inc.php';

try {
    $db = sch_db(); $u = sch_auth();
    if (!in_array($u['role'], ['parent', 'student'], true)) sch_fail('Only for parents and students.', 403);
    if ((string)($_GET['a'] ?? 'list') !== 'list') sch_fail('Unknown action.', 404);
    $kids = $u['role'] === 'student' ? array_filter([sch_rec_get('student', (string)$u['tid'])]) : sch_children((string)$u['tid']);
    $kids = array_values(array_filter($kids, fn($k) => is_array($k) && !empty($k['id'])));
    $from = date('Y-m-d', time() - 14 * 86400); $since = max(0, (int)($_GET['since'] ?? 0));
    $ids = array_map(fn($k) => (string)$k['id'], $kids); $byId = []; foreach ($kids as $k) $byId[(string)$k['id']] = $k;
    $classes = array_values(array_unique(array_map(fn($k) => (string)($k['cls'] ?? ''), $kids)));
    $first = fn(string $sid) => explode(' ', trim((string)($byId[$sid]['name'] ?? '')))[0] ?: 'Your child';
    $rows = function (string $k, bool $dated) use ($db, $from) {
        $q = $db->prepare('SELECT r, d, UNIX_TIMESTAMP(updated_at) t FROM sch_records WHERE k=? AND deleted=0' . ($dated ? ' AND dkey >= ?' : ''));
        $q->execute($dated ? [$k, $from] : [$k]);
        foreach ($q as $row) { $d = json_decode((string)$row['d'], true); if (is_array($d)) yield (string)$row['r'] => [$d, (int)$row['t']]; }
    };
    $N = []; $add = function (string $id, int $at, string $kind, string $title, string $body, string $go, string $sid = '') use (&$N) { $N[$id] = compact('id', 'at', 'kind', 'title', 'body', 'go', 'sid'); };
    $fd = fn(string $d) => date('D, j M', strtotime($d));

    foreach ($rows('studAtt', true) as $r => [$d, $t]) { [$date, $cls] = array_pad(explode('|', $r), 2, ''); if (!in_array($cls, $classes, true)) continue;
        foreach ($ids as $sid) if (($byId[$sid]['cls'] ?? '') === $cls && in_array($sid, array_map('strval', (array)($d['absent'] ?? [])), true)) {
            if ((((array)($d['st'] ?? []))[$sid] ?? '') === 'L') $add("lv|$date|$sid", $t, 'leave', $first($sid) . ' was on leave · ' . $fd($date), 'Marked as leave (L) in class attendance.', 'pcal', $sid);
            else $add("abs|$date|$sid", $t, 'absent', $first($sid) . ' was absent · ' . $fd($date), 'Marked absent in class attendance. Open the app to see the lessons and homework missed.', 'pstudy', $sid);
        } }
    foreach ($rows('tchChk', false) as $r => [$d, $t]) { if (!in_array($d['cls'] ?? '', $classes, true) || (string)($d['date'] ?? '') < $from) continue; $at = (int)(($d['at'] ?? 0) / 1000) ?: $t;
        foreach ((array)($d['rows'] ?? []) as $sid => $x) { $sid = (string)$sid; if (!in_array($sid, $ids, true)) continue;
            if (($x['hw'] ?? '') === 'not') $add("hw|$r|$sid", $at, 'homework', $first($sid) . ' did not do ' . ($d['subj'] ?? '') . ' homework', trim(($d['hw'] ?? '') !== '' ? 'Homework: ' . $d['hw'] . ' · ' : '') . 'Checked by ' . ($d['by'] ?? 'the teacher') . ' on ' . $fd((string)$d['date']) . '.', 'pchk', $sid);
            elseif (($x['hw'] ?? '') === 'half') $add("hw|$r|$sid", $at, 'homework', $first($sid) . '\'s ' . ($d['subj'] ?? '') . ' homework was half done', 'Checked by ' . ($d['by'] ?? 'the teacher') . ' on ' . $fd((string)$d['date']) . '.', 'pchk', $sid);
            if (($x['cp'] ?? '') === 'not') $add("cp|$r|$sid", $at, 'copy', $first($sid) . '\'s ' . ($d['subj'] ?? '') . ' copy was not complete', 'Checked on ' . $fd((string)$d['date']) . '.', 'pchk', $sid); } }
    foreach ($rows('tchCopy', false) as $r => [$d, $t]) { if (!in_array($d['cls'] ?? '', $classes, true)) continue; $at = (int)(($d['at'] ?? 0) / 1000) ?: $t; if ($at < strtotime($from)) continue;
        foreach ((array)($d['rows'] ?? []) as $sid => $v) if (in_array((string)$sid, $ids, true) && $v === 'inc')
            $add("cpc|$r|$sid", $at, 'copy', $first((string)$sid) . '\'s ' . ($d['subj'] ?? '') . ' copy is incomplete', 'Chapter: ' . ($d['ch'] ?? '') . ' · checked by ' . ($d['by'] ?? 'the teacher') . '.', 'pchk', (string)$sid); }
    $hwDay = [];
    foreach ($rows('diary', true) as $r => [$d, $t]) { $c = (string)($d['cls'] ?? ''); if (!in_array($c, $classes, true) || trim((string)($d['hw'] ?? '')) === '') continue;
        $date = explode('|', $r)[0]; $k = "$date|$c"; $hwDay[$k]['t'] = max($hwDay[$k]['t'] ?? 0, $t); $hwDay[$k]['s'][] = (string)($d['subj'] ?? ''); $hwDay[$k]['date'] = $date; $hwDay[$k]['cls'] = $c; }
    foreach ($hwDay as $k => $x) { $subs = array_values(array_unique($x['s'])); $who = implode(', ', array_map($first, array_map(fn($kd) => (string)$kd['id'], array_filter($kids, fn($kd) => ($kd['cls'] ?? '') === $x['cls']))));
        $add("hwd|$k", $x['t'], 'diary', 'Homework for ' . $who . ' · ' . $fd($x['date']), count($subs) . ' subject' . (count($subs) > 1 ? 's' : '') . ': ' . implode(', ', $subs) . '.', 'phw'); }
    foreach ($rows('famLeave', false) as $r => [$d, $t]) { $sid = (string)($d['sid'] ?? ''); if (!in_array($sid, $ids, true) || !in_array($d['status'] ?? '', ['approved', 'rejected'], true)) continue;
        $add("lvd|$r", (int)($d['decidedAt'] ?? $t), 'leave', 'Leave ' . ($d['status'] === 'approved' ? 'approved' : 'not approved') . ' for ' . $first($sid), $fd((string)$d['from']) . ($d['to'] !== $d['from'] ? ' – ' . $fd((string)$d['to']) : '') . ' · by ' . ($d['decidedBy'] ?? 'the class teacher') . (($d['reply'] ?? '') !== '' ? ': ' . $d['reply'] : '') . '.', 'pleave', $sid); }
    foreach ($rows('notice', false) as $r => [$d, $t]) { if (($d['to'] ?? '') === 'Teachers' || $t < strtotime($from)) continue;
        $add("nt|$r", $t, 'notice', (string)($d['title'] ?? 'School notice'), mb_substr(trim((string)($d['body'] ?? '')), 0, 160), 'pnotices'); }
    foreach ($rows('famFb', false) as $r => [$d, $t]) if (($d['uid'] ?? '') === $u['uid'] && ($d['reply'] ?? '') !== '' && (int)($d['replyAt'] ?? 0) >= strtotime($from))
        $add("fb|$r", (int)$d['replyAt'], 'reply', 'The school replied to your message', mb_substr((string)$d['reply'], 0, 160), 'pfb');

    $list = array_values(array_filter($N, fn($x) => $x['at'] > $since));
    usort($list, fn($a, $b) => $b['at'] <=> $a['at']);
    sch_json(['ok' => true, 'now' => time(), 'items' => array_slice($list, 0, 80)]);
} catch (Throwable $e) {
    error_log('school famnotif: ' . $e->getMessage());
    sch_fail('Something went wrong on the server. Please try again.', 500);
}
