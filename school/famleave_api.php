<?php
// ============================================================
// Student leave — maakit.in/school/famleave_api.php  (owner, 28 Nov 2026: "students ... leave option")
//
// Parent / student (only their own children):
//   GET  ?a=list&sid=                   this child's leave applications (this session)
//   POST ?a=apply  {sid, from, to, reason, note}   reason: Illness | Family function | Travel | Other
//   POST ?a=cancel {sid, id}            withdraw a pending application
// Class teacher of that class / Principal:
//   GET  ?a=tlist                       pending first, then the last 30 days
//   POST ?a=decide {id, status, note}   approved | rejected
//
// Record famLeave (kept out of the legacy load by the "fam" prefix). An approved leave makes the Teacher App
// attendance start that child as "L" (leave) on those days (teacher-arena.js), so it is not counted as absent.
// ============================================================
declare(strict_types=1);
require __DIR__ . '/inc.php';

const LV_REASONS = ['Illness', 'Family function', 'Travel', 'Other'];
const LV_MAX_DAYS = 15;

function lv_rows(): array {
    $out = [];
    foreach (sch_db()->query("SELECT r, d FROM sch_records WHERE k='famLeave' AND deleted=0") as $row) { $d = json_decode((string)$row['d'], true); if (is_array($d)) $out[(string)$row['r']] = $d; }
    return $out;
}
function lv_days(string $from, string $to): int {
    $n = 0; for ($d = strtotime($from); $d <= strtotime($to); $d += 86400) if (date('w', $d) !== '0') $n++;
    return $n;
}

try {
    $db = sch_db(); $u = sch_auth(); $a = (string)($_GET['a'] ?? 'list'); $uid = sch_uid_of($u); $now = time(); $today = date('Y-m-d');
    $in = [];
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') { $in = json_decode(file_get_contents('php://input', false, null, 0, 8000) ?: '', true); if (!is_array($in)) sch_fail('Bad request.'); }
    $students = []; foreach ($db->query("SELECT r, d FROM sch_records WHERE k='student' AND deleted=0") as $row) { $s = json_decode((string)$row['d'], true); if (is_array($s)) $students[(string)$row['r']] = $s; }
    $L = lv_rows();

    // ================= CLASS TEACHER / PRINCIPAL =================
    if ($a === 'tlist' || $a === 'decide') {
        if (!in_array($u['role'], ['teacher', 'principal'], true)) sch_fail('Teachers only.', 403);
        $ct = []; foreach ((array)sch_rec_get('classes', 'main') as $c) if (($c['ct'] ?? '') === ($u['tid'] ?? '')) $ct[] = (string)$c['c'];
        $may = fn(string $cls) => $u['role'] === 'principal' || in_array($cls, $ct, true) || sch_in_class((string)$u['tid'], $cls);
        if ($a === 'tlist') {
            $from = date('Y-m-d', $now - 30 * 86400);
            $mine = array_values(array_filter($L, fn($x) => $may((string)$x['cls']) && ($x['status'] === 'pending' || $x['to'] >= $from)));
            usort($mine, fn($x, $y) => [$x['status'] !== 'pending', $y['at']] <=> [$y['status'] !== 'pending', $x['at']]);
            sch_json(['ok' => true, 'leaves' => array_map(fn($x) => $x + ['name' => (string)($students[$x['sid']]['name'] ?? '')], $mine)]);
        }
        $x = $L[(string)($in['id'] ?? '')] ?? null; $st = (string)($in['status'] ?? '');
        if (!$x) sch_fail('Leave application not found.');
        if (!($u['role'] === 'principal' || in_array($x['cls'], $ct, true))) sch_fail('Only the class teacher or the Principal can decide a leave.', 403);
        if (!in_array($st, ['approved', 'rejected'], true)) sch_fail('Choose approve or reject.');
        $x['status'] = $st; $x['decidedBy'] = (string)$u['label']; $x['decidedAt'] = $now; $x['reply'] = mb_substr(trim((string)($in['note'] ?? '')), 0, 200);
        sch_rec_put('famLeave', $x['id'], $x, $uid);
        sch_log($u, 'Student leave', ($st === 'approved' ? 'Approved' : 'Rejected') . ' leave of ' . ($students[$x['sid']]['name'] ?? '') . " ({$x['cls']}) {$x['from']} to {$x['to']}");
        sch_json(['ok' => true]);
    }

    // ================= PARENT / STUDENT =================
    if (!in_array($u['role'], ['student', 'parent'], true)) sch_fail('Not allowed.', 403);
    $sid = $u['role'] === 'student' ? (string)$u['tid'] : (string)($_GET['sid'] ?? $in['sid'] ?? '');
    $kids = $u['role'] === 'student' ? [(string)$u['tid']] : array_map('strval', array_column(sch_children((string)$u['tid']), 'id'));
    if ($sid === '' || !in_array($sid, $kids, true) || !isset($students[$sid])) sch_fail('Choose your own child.', 403);

    if ($a === 'list') {
        $mine = array_values(array_filter($L, fn($x) => $x['sid'] === $sid && $x['to'] >= date('Y-m-d', $now - 180 * 86400)));
        usort($mine, fn($x, $y) => $y['at'] <=> $x['at']);
        sch_json(['ok' => true, 'leaves' => $mine, 'reasons' => LV_REASONS]);
    }
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') sch_fail('POST only', 405);
    if ($a === 'apply') {
        $from = (string)($in['from'] ?? ''); $to = (string)($in['to'] ?? $from); $reason = (string)($in['reason'] ?? ''); $note = mb_substr(trim(preg_replace('/\s+/u', ' ', (string)($in['note'] ?? ''))), 0, 300);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to) || $to < $from) sch_fail('Choose the dates (from and to).');
        if ($from < date('Y-m-d', $now - 7 * 86400)) sch_fail('Leave can be applied for up to 7 days back.');
        if ($from > date('Y-m-d', $now + 60 * 86400)) sch_fail('Leave can be applied up to 60 days ahead.');
        if (lv_days($from, $to) > LV_MAX_DAYS) sch_fail('For more than ' . LV_MAX_DAYS . ' school days, please meet the Principal.');
        if (!in_array($reason, LV_REASONS, true)) sch_fail('Choose the reason.');
        if ($reason === 'Other' && mb_strlen($note) < 5) sch_fail('Please write the reason.');
        foreach ($L as $x) if ($x['sid'] === $sid && $x['status'] !== 'rejected' && $x['status'] !== 'cancelled' && !($to < $x['from'] || $from > $x['to'])) sch_fail('A leave for these dates is already applied.');
        $id = 'lv' . substr(hash('sha256', $sid . '|' . $now . '|' . random_int(0, PHP_INT_MAX)), 0, 14);
        $rec = ['id' => $id, 'sid' => $sid, 'cls' => (string)$students[$sid]['cls'], 'from' => $from, 'to' => $to, 'days' => lv_days($from, $to), 'reason' => $reason, 'note' => $note,
                'by' => $u['role'], 'at' => $now, 'status' => 'pending'];
        sch_rec_put('famLeave', $id, $rec, $uid);
        sch_log($u, 'Student leave', 'Leave applied for ' . ($students[$sid]['name'] ?? '') . " ($from to $to, $reason)");
        sch_json(['ok' => true, 'leave' => $rec]);
    }
    if ($a === 'cancel') {
        $x = $L[(string)($in['id'] ?? '')] ?? null;
        if (!$x || $x['sid'] !== $sid) sch_fail('Leave application not found.', 404);
        if ($x['status'] !== 'pending') sch_fail('Only a pending application can be withdrawn.');
        $x['status'] = 'cancelled'; sch_rec_put('famLeave', $x['id'], $x, $uid);
        sch_json(['ok' => true]);
    }
    sch_fail('Unknown action.', 404);
} catch (Throwable $e) {
    error_log('school famleave: ' . $e->getMessage());
    sch_fail('Something went wrong on the server. Please try again.', 500);
}
