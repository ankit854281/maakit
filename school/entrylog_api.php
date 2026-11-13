<?php
// ============================================================================
//  Who entered what — 12 Nov 2026, owner: "show kare kaun sa data kisne add kiya hai".
//  Every school record already keeps who saved it last and when (sch_records.by_uid,
//  updated_at). This lists them in plain words for the Principal and Chairman:
//
//   GET ?a=list&days=1..60[&who=uid][&kind=label]   newest first (max 600) + a count per person
//
//  Page: entrylog.php. Read-only. Salary records are left out for the Chairman.
// ============================================================================
declare(strict_types=1);
require __DIR__ . '/inc.php';

try { $db = sch_db(); } catch (Throwable $e) { sch_fail('Server is not reachable right now. Please try again in a few minutes.', 503); }
$u = sch_auth(); $role = (string)$u['role'];
if (!in_array($role, ['principal', 'chairman'], true)) sch_fail('Only the Principal and Chairman see who entered what.', 403);

const EL_KINDS = [
    'studAtt' => 'Student attendance', 'staffAtt' => 'Staff attendance', 'salAtt' => 'Staff attendance (other staff)', 'diary' => 'Class work / homework diary',
    'signed' => 'Diary signed', 'marks' => 'Marks', 'student' => 'Student record', 'leave' => 'Leave', 'lplan' => 'Lesson plan', 'syl' => 'Syllabus',
    'behav' => 'Behaviour note', 'speak' => 'Speaking activity', 'cal' => 'Calendar', 'notice' => 'Notice', 'teacher' => 'Teacher record', 'settings' => 'Settings',
    'alloc' => 'Timetable (periods)', 'grid' => 'Timetable', 'classes' => 'Classes / class teachers', 'exams' => 'Exams', 'fees' => 'Fee structure', 'clsHol' => 'Class holiday',
    'acctPay' => 'Fee receipt (old desk)', 'acctCall' => 'Fee call', 'acctStu' => 'Fee settings of a child', 'ofcRcpt' => 'Fee receipt', 'ofcLed' => 'Fee card', 'ofcSeq' => 'Receipt number',
    'regRow' => 'School Office register', 'salStaff' => 'Staff salary setting', 'salMonth' => 'Salary month', 'phoStu' => 'Student photo', 'phoImg' => 'Photo',
    'tchAccept' => 'Staff rules accepted', 'tchPolicy' => 'Staff rules changed', 'tchKit' => 'Daily check', 'tchChk' => 'Homework / copy check', 'tchNote' => 'Chapter notes',
    'tchMsg' => 'Staff chat', 'profNote' => 'Profile note', 'finTx' => 'Accounts entry', 'regKit' => 'Diary / ID card / Dress / Books given', 'salOpen' => 'Holiday opened for staff',
];
const EL_SKIP = ['regSeq', 'acctSeq', 'acctNonce', 'scanUse', 'phoThumb', 'gameR', 'gameMiss', 'gamePack', 'gameLock', 'quizD', 'quizC', 'regHist', 'regPage', 'prcOld', 'oldfeecard', 'salSeed'];

function el_who(string $uid, array &$cache): string {
    if (isset($cache[$uid])) return $cache[$uid];
    if ($uid === '' ) return $cache[$uid] = 'System / import';
    if (str_starts_with($uid, 't:')) { $t = sch_rec_get('teacher', substr($uid, 2)); return $cache[$uid] = (string)($t['name'] ?? substr($uid, 2)) . ' (Teacher)'; }
    if (str_starts_with($uid, 'p:')) return $cache[$uid] = 'Parent ' . substr($uid, 2, 2) . 'xxxxxx' . substr($uid, -2);
    if (str_starts_with($uid, 's:')) return $cache[$uid] = sch_user_label(['role' => 'student', 'tid' => substr($uid, 2)]);
    if (in_array($uid, ['principal', 'chairman', 'accountant', 'reception', 'dataentry'], true)) {
        if ($uid === 'principal') { $t = sch_rec_get('teacher', 'P'); return $cache[$uid] = (string)($t['name'] ?? 'Principal') . ' (Principal)'; }
        return $cache[$uid] = sch_user_label(['role' => $uid, 'tid' => null]);
    }
    return $cache[$uid] = $uid;
}
function el_what(string $k, string $r, ?array $d): string {
    $p = explode('|', $r); $date = preg_match('/^\d{4}-\d{2}-\d{2}$/', $p[0]) ? date('d M', strtotime($p[0])) : '';
    $t = fn($id) => (string)((sch_rec_get('teacher', $id) ?? [])['name'] ?? $id);
    switch ($k) {
        case 'studAtt': return "$date · Class " . ($p[1] ?? '') . ($d ? ' · ' . count((array)($d['absent'] ?? [])) . ' absent' : '');
        case 'staffAtt': case 'salAtt': return $date;
        case 'diary': return "$date · " . ($d['cls'] ?? '') . ' ' . ($d['subj'] ?? '') . ($d && trim((string)($d['hw'] ?? '')) !== '' ? ' · HW' : '');
        case 'marks': return ($p[0] ?? '') . ' · Class ' . ($p[1] ?? '') . ' ' . ($p[2] ?? '');
        case 'student': return (string)($d['name'] ?? $r) . (isset($d['cls']) ? ' · Class ' . $d['cls'] : '');
        case 'leave': if (!$d) return $r; return $t((string)($d['tid'] ?? '')) . ' · ' . ($d['from'] ?? '') . ((($d['to'] ?? '') !== ($d['from'] ?? '')) ? ' to ' . ($d['to'] ?? '') : '') . ' · ' . ($d['status'] ?? '');
        case 'lplan': return 'Week of ' . ($p[0] ?? '') . ' · ' . ($p[2] ?? '') . ' ' . ($p[3] ?? '');
        case 'syl': return ($p[0] ?? '') . ' ' . ($p[1] ?? '');
        case 'regRow': return ucfirst((string)($p[0] ?? '')) . ($d && isset($d['name']) ? ' · ' . $d['name'] : '');
        case 'ofcRcpt': case 'acctPay': return 'Receipt ' . ($d['no'] ?? $r) . (isset($d['name']) ? ' · ' . $d['name'] : '');
        case 'teacher': return (string)($d['name'] ?? $r);
        case 'cal': case 'notice': return (string)($d['title'] ?? $r) . (isset($d['date']) ? ' · ' . $d['date'] : '');
        case 'clsHol': return "$date · Class " . ($p[1] ?? '');
        case 'tchKit': case 'tchChk': return "$date · " . implode(' ', array_slice($p, 1));
    }
    return $date !== '' ? $date . (count($p) > 1 ? ' · ' . implode(' ', array_slice($p, 1)) : '') : (mb_strlen($r) > 40 ? mb_substr($r, 0, 40) . '…' : $r);
}

$days = max(1, min(60, (int)($_GET['days'] ?? 7))); $who = (string)($_GET['who'] ?? ''); $kind = (string)($_GET['kind'] ?? '');
// ver = save time in ms (PHP clock, India time) — updated_at follows the database server's own time zone
$q = $db->prepare('SELECT k, r, d, deleted, by_uid, ver FROM sch_records WHERE ver >= ? ORDER BY ver DESC LIMIT 4000');
$q->execute([(strtotime('today') - ($days - 1) * 86400) * 1000]);
$rows = []; $per = []; $cache = []; $allKinds = [];
foreach ($q as $row) {
    $k = (string)$row['k'];
    if (in_array($k, EL_SKIP, true) || ($role === 'chairman' && str_starts_with($k, 'sal'))) continue;
    $label = EL_KINDS[$k] ?? $k; $allKinds[$label] = 1; $uid = (string)$row['by_uid']; $name = el_who($uid, $cache);
    $per[$uid] ??= ['uid' => $uid, 'name' => $name, 'n' => 0, 'kinds' => []]; $per[$uid]['n']++; $per[$uid]['kinds'][$label] = ($per[$uid]['kinds'][$label] ?? 0) + 1;
    if (($who !== '' && $uid !== $who) || ($kind !== '' && $label !== $kind) || count($rows) >= 600) continue;
    $d = $row['d'] !== null ? json_decode((string)$row['d'], true) : null;
    $rows[] = ['at' => date('Y-m-d H:i:s', intdiv((int)$row['ver'], 1000)), 'kind' => $label, 'what' => el_what($k, (string)$row['r'], is_array($d) ? $d : null), 'who' => $name, 'uid' => $uid, 'removed' => (int)$row['deleted'] === 1];
}
usort($per, fn($x, $y) => $y['n'] <=> $x['n']);
sch_json(['ok' => true, 'days' => $days, 'rows' => $rows, 'people' => array_values($per), 'kinds' => array_keys($allKinds)]);
