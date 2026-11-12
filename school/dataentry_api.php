<?php
// ============================================================================
//  Data Entry (non-teaching) — 12 Nov 2026, owner: "non teaching me add kardo data
//  entry se related (Shubh Upadhyay — homework share, jinka attendance update nahi
//  ho raha uska attendance update karenge)". Page: dataentry.php (own PIN login,
//  role 'dataentry', uid 'dataentry', name settings.dataentryName). The role is in
//  SCH_NO_LOAD — it never gets the whole school's data, only what this file gives.
//
//   GET  ?a=day&date=YYYY-MM-DD       classes, children, that day's attendance (or "not marked"), holidays
//   POST ?a=att {date, cls, st:{sid:'A'|'L'}}   student attendance for a class that the class teacher did not mark
//                                     (same studAtt record + format as the Teacher App; who saved it is kept)
//   GET  ?a=month&ym=                  day by day: classes marked, total, present, absent, leave, %
//   GET  ?a=homework&date=            every class's homework of that day from the teachers' diaries
//                                     (for sharing on the class WhatsApp groups)
//  Staff attendance: staffatt.php (staffatt_api.php allows 'dataentry').
//  Principal can use this page too.
// ============================================================================
declare(strict_types=1);
require __DIR__ . '/inc.php';

$a = (string)($_GET['a'] ?? '');
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
try { $db = sch_db(); } catch (Throwable $e) { sch_fail('Server is not reachable right now. Please try again in a few minutes.', 503); }
$u = sch_auth(); $role = (string)$u['role']; $uid = sch_uid_of($u);
if (!in_array($role, ['dataentry', 'principal'], true)) sch_fail('This page is for Data Entry and the Principal.', 403);

function de_date(string $d): string {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) || $d > date('Y-m-d') || $d < date('Y-m-d', strtotime('-120 days'))) sch_fail('Choose a date in the last 120 days.');
    return $d;
}
function de_rows(PDO $db, string $k, ?string $prefix = null): array {
    $q = $db->prepare('SELECT r, d, by_uid FROM sch_records WHERE k=? AND deleted=0' . ($prefix !== null ? ' AND r LIKE ?' : ''));
    $q->execute($prefix !== null ? [$k, $prefix . '%'] : [$k]); $o = [];
    foreach ($q as $row) { $d = json_decode((string)$row['d'], true); if (is_array($d)) $o[(string)$row['r']] = $d + ['_uid' => (string)$row['by_uid']]; }
    return $o;
}
function de_holiday(PDO $db, string $date): string {
    foreach (de_rows($db, 'cal') as $e) if (($e['type'] ?? '') === 'Holiday' && ($e['date'] ?? '9') <= $date && (($e['to'] ?? '') ?: ($e['date'] ?? '')) >= $date) return (string)($e['title'] ?? 'Holiday');
    return date('w', strtotime($date)) === '0' ? 'Sunday' : '';
}
function de_teacher(string $tid): string { $t = sch_rec_get('teacher', $tid); return (string)($t['name'] ?? $tid); }

try {
switch ($a) {
case 'day': {
    $date = de_date((string)($_GET['date'] ?? date('Y-m-d')));
    $classes = (array)(sch_rec_get('classes', 'main') ?? []); $kids = [];
    foreach (de_rows($db, 'student') as $sid => $s) if (empty($s['inactive']) && ($s['cls'] ?? '') !== '') $kids[(string)$s['cls']][] = ['id' => (string)$sid, 'name' => (string)($s['name'] ?? ''), 'roll' => (string)($s['rollno'] ?? '')];
    $att = de_rows($db, 'studAtt', $date . '|'); $hol = de_rows($db, 'clsHol', $date . '|');
    $out = [];
    foreach ($classes as $c) {
        $cn = (string)($c['c'] ?? ''); if ($cn === '') continue;
        $L = $kids[$cn] ?? []; usort($L, fn($x, $y) => [(int)$x['roll'] ?: 999, $x['name']] <=> [(int)$y['roll'] ?: 999, $y['name']]);
        $r = $att[$date . '|' . $cn] ?? null;
        $out[] = ['c' => $cn, 'ct' => ($c['ct'] ?? '') !== '' ? de_teacher((string)$c['ct']) : '', 'students' => $L,
                  'att' => $r ? ['absent' => array_values((array)($r['absent'] ?? [])), 'st' => (object)($r['st'] ?? []), 'by' => (string)($r['by'] ?? ''), 'at' => $r['at'] ?? null] : null,
                  'holiday' => isset($hol[$date . '|' . $cn]) ? (string)($hol[$date . '|' . $cn]['title'] ?? 'Class holiday') : ''];
    }
    $set = sch_rec_get('settings', 'main') ?? [];
    sch_json(['ok' => true, 'date' => $date, 'holiday' => de_holiday($db, $date), 'classes' => $out, 'me' => $u['label'], 'school' => (string)($set['school'] ?? '')]);
}
case 'att': {
    if ($method !== 'POST') sch_fail('POST only', 405);
    $in = json_decode(file_get_contents('php://input', false, null, 0, 200000) ?: '', true); if (!is_array($in)) sch_fail('Bad request.');
    $date = de_date((string)($in['date'] ?? '')); $cls = (string)($in['cls'] ?? '');
    if (($h = de_holiday($db, $date)) !== '') sch_fail("No attendance on a holiday ($h).");
    $ok = false; foreach ((array)(sch_rec_get('classes', 'main') ?? []) as $c) if (($c['c'] ?? '') === $cls) $ok = true;
    if (!$ok) sch_fail('Unknown class.');
    if (sch_rec_get('clsHol', $date . '|' . $cls)) sch_fail('This class has a holiday on that day.');
    $valid = []; foreach (de_rows($db, 'student') as $sid => $s) if (($s['cls'] ?? '') === $cls && empty($s['inactive'])) $valid[(string)$sid] = (string)($s['name'] ?? '');
    $absent = []; $st = [];
    foreach ((array)($in['st'] ?? []) as $sid => $v) { $sid = (string)$sid; if (!isset($valid[$sid]) || !in_array($v, ['A', 'L'], true)) continue; $absent[] = $sid; if ($v === 'L') $st[$sid] = 'L'; }
    $prev = sch_rec_get('studAtt', $date . '|' . $cls);
    $rec = ['absent' => $absent] + ($st ? ['st' => $st] : []) + ['by' => preg_replace('/\s*\(Data Entry\)$/', '', $u['label']) . ' (Data Entry)', 'at' => sch_now_ms()];
    $db->beginTransaction();
    sch_rec_put('studAtt', $date . '|' . $cls, $rec, $uid);
    $names = array_map(fn($s) => $valid[$s], $absent);
    sch_log($u, 'Student Attendance', "Class $cls ($date): " . (count($valid) - count($absent)) . '/' . count($valid) . ' present' . ($names ? '. Absent/leave: ' . implode(', ', $names) : '') . ($prev ? ' — corrected by Data Entry (was saved by ' . ($prev['by'] ?? 'someone') . ')' : ' — entered by Data Entry (class teacher had not marked)'));
    $db->commit();
    sch_json(['ok' => true]);
}
case 'month': {             // GET &ym= — each school day of the month: classes marked, total, present, absent, leave, %
    $ym = (string)($_GET['ym'] ?? date('Y-m')); if (!preg_match('/^20\d\d-(0[1-9]|1[0-2])$/', $ym) || $ym > date('Y-m')) sch_fail('Choose a month.');
    $roll = []; foreach (de_rows($db, 'student') as $s) if (empty($s['inactive']) && ($s['cls'] ?? '') !== '') $roll[(string)$s['cls']] = ($roll[(string)$s['cls']] ?? 0) + 1;
    $classes = array_values(array_filter(array_map(fn($c) => (string)($c['c'] ?? ''), (array)(sch_rec_get('classes', 'main') ?? []))));
    $att = de_rows($db, 'studAtt', $ym . '-'); $hol = de_rows($db, 'clsHol', $ym . '-');
    $days = []; $n = (int)date('t', strtotime($ym . '-01')); $today = date('Y-m-d');
    for ($i = 1; $i <= $n; $i++) {
        $d = sprintf('%s-%02d', $ym, $i); if ($d > $today) break;
        $h = de_holiday($db, $d); $row = ['date' => $d, 'holiday' => $h, 'marked' => 0, 'classes' => 0, 'total' => 0, 'present' => 0, 'absent' => 0, 'leave' => 0];
        if ($h === '') foreach ($classes as $c) {
            if (isset($hol[$d . '|' . $c]) || empty($roll[$c])) continue;
            $row['classes']++; $r = $att[$d . '|' . $c] ?? null; if (!$r) continue;
            $row['marked']++; $ab = count((array)($r['absent'] ?? [])); $lv = count(array_filter((array)($r['st'] ?? []), fn($v) => $v === 'L'));
            $row['total'] += $roll[$c]; $row['absent'] += $ab - $lv; $row['leave'] += $lv; $row['present'] += $roll[$c] - $ab;
        }
        $days[] = $row;
    }
    sch_json(['ok' => true, 'ym' => $ym, 'days' => array_reverse($days)]);
}
case 'homework': {
    $date = de_date((string)($_GET['date'] ?? date('Y-m-d')));
    $out = [];
    foreach (de_rows($db, 'diary', $date . '|') as $r => $e) {
        if (trim((string)($e['hw'] ?? '')) === '') continue;
        $p = explode('|', $r);
        $out[] = ['cls' => (string)($e['cls'] ?? ''), 'subj' => (string)($e['subj'] ?? ''), 'hw' => (string)$e['hw'], 'hwDate' => (string)($e['hwDate'] ?? ''), 'teacher' => de_teacher($p[1] ?? ''), 'bell' => (int)($p[2] ?? 0)];
    }
    usort($out, fn($x, $y) => [$x['cls'], $x['bell']] <=> [$y['cls'], $y['bell']]);
    sch_json(['ok' => true, 'date' => $date, 'items' => $out, 'classes' => array_values(array_filter(array_map(fn($c) => (string)($c['c'] ?? ''), (array)(sch_rec_get('classes', 'main') ?? []))))]);
}
default: sch_fail('Unknown action.');
}
} catch (Throwable $e) { if ($db->inTransaction()) $db->rollBack(); throw $e; }
