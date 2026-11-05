<?php
// ============================================================================
//  Old ERP student attendance → new ERP (owner, 5 Nov 2026: "purane ERP se attendance lekar new ERP me,
//  fir vo data teacher app me show kare").
//
//  The old ERP (schoolerpindia.net/school/snmishra) Attendance Register (report_sturegdet1.php) is read
//  class by class into a file (one entry per class: days[] + each child's scholar no., child id, name and a
//  comma list of day codes P / A / L / - / SUN). The Principal opens school/oldatt.php, chooses the file,
//  sees a preview per class, then presses Apply. Each class is sent in its own request (the host refuses big ones).
//
//  POST ?a=preview|apply {cls, days[], kids[{scholar, child, name, codes}]}     Principal only
//
//  Rules (never lose a teacher's work):
//   - Writes the SAME studAtt 'date|cls' record the web ERP and the Teacher App use:
//     {absent:[ids of A and L], st:{id:'L'}, by:'Old ERP', at, src:'oldErp'}.
//   - A day that already has attendance in the new ERP (any record, also one a teacher undid) is never touched —
//     except a day that came from an earlier run of this import (src 'oldErp'): that one is refreshed when the
//     old ERP changed it (so the import can be run again until the old ERP licence ends).
//   - A day where no child has P / A / L in the old ERP is not a marked day and is skipped. Sundays, school
//     holidays (cal) and class holidays (clsHol) of the new ERP are skipped.
//   - Children are matched in that class by Scholar No (= adm), then Child Id, then exact name if only one
//     child has it. Old-ERP children not found are listed in the preview; children of the new ERP who are not
//     in the old register count as present on imported days.
// ============================================================================
declare(strict_types=1);
require __DIR__ . '/inc.php';

$a = (string)($_GET['a'] ?? '');
try { $db = sch_db(); } catch (Throwable $e) { sch_fail('Server is not reachable right now. Please try again in a few minutes.', 503); }

function oa_norm(string $s): string { return preg_replace('/[^a-z]/', '', strtolower($s)); }

try {
    $u = sch_auth();
    if (($u['role'] ?? '') !== 'principal') sch_fail('Only the Principal can import old ERP attendance.', 403);
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST' || !in_array($a, ['preview', 'apply'], true)) sch_fail('Bad request.', 405);
    $in = json_decode(file_get_contents('php://input', false, null, 0, 900000) ?: '', true);
    if (!is_array($in)) sch_fail('Bad request.');

    $cls = (string)($in['cls'] ?? '');
    $classes = array_column((array)sch_rec_get('classes', 'main'), 'c');
    if (!in_array($cls, $classes, true)) sch_json(['ok' => true, 'cls' => $cls, 'skipped' => 'This class is not in the new ERP.']);
    $days = is_array($in['days'] ?? null) ? array_values($in['days']) : [];
    $kids = is_array($in['kids'] ?? null) ? array_values($in['kids']) : [];
    if (count($days) > 400 || count($kids) > 300) sch_fail('Too much data for one class.');
    foreach ($days as $d) if (!is_string($d) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) sch_fail('Bad date in the file.');

    // children of this class in the new ERP
    $stu = [];
    foreach ($db->query("SELECT r, d FROM sch_records WHERE k='student' AND deleted=0") as $row) {
        $s = json_decode((string)$row['d'], true); if (!is_array($s) || ($s['cls'] ?? '') !== $cls) continue;
        $stu[(string)$row['r']] = $s;
    }
    $byAdm = []; $byChild = []; $byName = [];
    foreach ($stu as $id => $s) {
        if (($s['adm'] ?? '') !== '') $byAdm[trim((string)$s['adm'])][] = $id;
        if (($s['childId'] ?? '') !== '') $byChild[trim((string)$s['childId'])][] = $id;
        $byName[oa_norm((string)($s['name'] ?? ''))][] = $id;
    }
    $matched = []; $unmatched = []; $used = [];
    foreach ($kids as $k) {
        if (!is_array($k)) continue;
        $codes = explode(',', (string)($k['codes'] ?? ''));
        if (count($codes) !== count($days)) sch_fail('The file is damaged (day codes do not match the dates).');
        $sch = trim((string)($k['scholar'] ?? '')); $ch = trim((string)($k['child'] ?? '')); $nm = oa_norm((string)($k['name'] ?? ''));
        $id = null;
        foreach ([[$byAdm, $sch], [$byChild, $ch], [$byName, $nm]] as [$map, $key]) {
            if ($key !== '' && count($map[$key] ?? []) === 1 && !isset($used[$map[$key][0]])) { $id = $map[$key][0]; break; }
        }
        if ($id === null) { $unmatched[] = mb_substr((string)($k['name'] ?? ''), 0, 80) . ($sch !== '' ? ' (Scholar ' . $sch . ')' : ''); continue; }
        $used[$id] = true; $matched[$id] = array_map(fn($c) => strtoupper(trim($c)), $codes);
    }

    // what the new ERP already has for this class
    $have = [];
    $q = $db->prepare("SELECT r, d, deleted FROM sch_records WHERE k='studAtt' AND r LIKE ?");
    $q->execute(['%|' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $cls)]);
    foreach ($q as $row) { $pp = explode('|', (string)$row['r']); if (($pp[1] ?? '') !== $cls) continue; $have[$pp[0]] = ['del' => (int)$row['deleted'], 'd' => json_decode((string)$row['d'], true)]; }
    $cal = []; foreach ($db->query("SELECT d FROM sch_records WHERE k='cal' AND deleted=0") as $row) { $e = json_decode((string)$row['d'], true); if (is_array($e) && ($e['type'] ?? '') === 'Holiday') $cal[] = $e; }
    $holOn = function (string $d) use ($cal): bool { foreach ($cal as $e) if (($e['date'] ?? '') <= $d && ((($e['to'] ?? '') ?: ($e['date'] ?? '')) >= $d)) return true; return false; };
    $clsHol = []; $q = $db->prepare("SELECT r FROM sch_records WHERE k='clsHol' AND deleted=0 AND r LIKE ?"); $q->execute(['%|' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $cls)]);
    foreach ($q as $row) { $pp = explode('|', (string)$row['r']); if (($pp[1] ?? '') === $cls) $clsHol[$pp[0]] = true; }

    $today = date('Y-m-d'); $add = []; $upd = []; $kept = 0; $holiday = 0; $notMarked = 0; $absentDays = 0;
    foreach ($days as $i => $d) {
        if ($d > $today) continue;
        $marked = false; foreach ($matched as $codes) if (in_array($codes[$i], ['P', 'A', 'L'], true)) { $marked = true; break; }
        if (!$marked) { $notMarked++; continue; }
        if ((int)date('w', strtotime($d)) === 0 || $holOn($d) || isset($clsHol[$d])) { $holiday++; continue; }
        $absent = []; $st = [];
        foreach ($matched as $id => $codes) { if ($codes[$i] === 'A' || $codes[$i] === 'L') $absent[] = $id; if ($codes[$i] === 'L') $st[$id] = 'L'; }
        sort($absent); ksort($st);
        $rec = ['absent' => $absent] + ($st ? ['st' => $st] : []) + ['by' => 'Old ERP (schoolerpindia.net)', 'at' => sch_now_ms(), 'src' => 'oldErp'];
        $h = $have[$d] ?? null;
        if ($h === null) { $add[$d] = $rec; $absentDays += count($absent); continue; }
        if (!$h['del'] && is_array($h['d']) && ($h['d']['src'] ?? '') === 'oldErp') {
            $old = $h['d']['absent'] ?? []; sort($old); $oldSt = (array)($h['d']['st'] ?? []); ksort($oldSt);
            if ($old !== $absent || $oldSt !== $st) $upd[$d] = $rec; else $kept++;
            continue;
        }
        $kept++;
    }
    $sum = ['ok' => true, 'cls' => $cls, 'oldKids' => count($kids), 'newKids' => count($stu), 'matched' => count($matched), 'unmatched' => $unmatched,
            'add' => count($add), 'update' => count($upd), 'kept' => $kept, 'holiday' => $holiday, 'notMarked' => $notMarked,
            'from' => $days[0] ?? '', 'to' => $days ? $days[count($days) - 1] : ''];
    if ($a === 'preview') sch_json($sum);

    $uid = sch_uid_of($u);
    $db->beginTransaction();
    foreach ($add + $upd as $d => $rec) sch_rec_put('studAtt', $d . '|' . $cls, $rec, $uid);
    $db->commit();
    if ($add || $upd) sch_log($u, 'Attendance', "Old ERP attendance imported: Class $cls — " . count($add) . ' day(s) added, ' . count($upd) . ' refreshed, ' . $kept . ' kept as they were in the new ERP' . ($unmatched ? ', ' . count($unmatched) . ' old-ERP child(ren) not found' : ''));
    sch_json($sum + ['applied' => true]);
} catch (Throwable $e) {
    if (isset($db) && $db->inTransaction()) $db->rollBack();
    error_log('oldatt_api: ' . $e->getMessage());
    sch_fail('Something went wrong. Nothing was saved. Please try again.', 500);
}
