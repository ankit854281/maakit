<?php
// ============================================================================
//  Staff attendance for ALL staff — teachers and non-teaching (drivers, Dai,
//  watchman, PRO…) — one month on one screen, many days at once, or a CSV file.
//  Owner, 12 Nov 2026: "teachers ka attendance bulk me upload karne ka option do —
//  teachers ka hi nahi, sabhi staff ka, driver ka bhi".
//
//   GET  ?a=month&ym=YYYY-MM     Principal / Accountant / Chairman (view)
//        → staff (teachers + non-teaching from Staff Salary), days (work / Sunday / holiday),
//          att {id: {date: 'A' | 'H' | 'L'}}  (L = approved leave of a teacher, read-only)
//   POST ?a=save {ym, set: {date: {id: 'P'|'A'|'H'}}}   Principal / Accountant
//
//  Teachers are saved in the SAME record the ERP / Teacher App use: staffAtt[date]
//  {absent:[tid], from:{tid: bell}} — so the arrangement, cross-check and salary see it
//  (half day = absent from the 4th bell, the ERP's "from bell" rule; any substitutes
//  already chosen are kept). Non-teaching staff go to salAtt[date] {a:[id], h:[id]}
//  ("sal" prefix — never in the normal load), read by salary_lib.php.
// ============================================================================
declare(strict_types=1);
require __DIR__ . '/inc.php';
require __DIR__ . '/salary_lib.php';

const SA_HALF_BELL = 4;   // a half day = absent from this bell onwards (same as the ERP's "from bell")

$a = (string)($_GET['a'] ?? '');
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
try { $db = sch_db(); } catch (Throwable $e) { sch_fail('Server is not reachable right now. Please try again in a few minutes.', 503); }
$u = sch_auth(); $role = (string)$u['role']; $uid = sch_uid_of($u);
if (!in_array($role, ['principal', 'accountant', 'chairman', 'dataentry', 'manager'], true)) sch_fail('Staff attendance is open for the Principal, Accountant, Data Entry and Chairman.', 403);

function sa_ym(string $ym): string { if (!sal_ym_ok($ym) || $ym > date('Y-m')) sch_fail('Choose a month (not a future month).'); return $ym; }
// Staff who LEFT keep their months (owner, 12 Nov 2026: "Akash Tiwari, Amisha Pandey Ma'am, Brijesh Kumar Yadav,
// Shiva Rai Ma'am oct tak working me the — inka attendance add kariye"). salStaff[id].until = last working day:
// shown for months up to that month, and days after it cannot be saved. Left teachers (settings.leftTeachers) with
// salStaff['t:tid'].until come back for those months under their old tid, so staffAtt / arrangement keep matching.
function sa_staff(string $ym = ''): array {
    $left = (array)((sch_rec_get('settings', 'main') ?? [])['leftTeachers'] ?? []); $set = sal_rows('salStaff'); $out = [];
    $show = function (array $x) use ($ym): bool { $u = (string)($x['until'] ?? ''); return $u === '' || $ym === '' || $ym <= substr($u, 0, 7); };
    foreach (sal_staff_list() as $id => $s) {
        $x = $set[$id] ?? []; $until = (string)($x['until'] ?? '');
        if ($until === '' && (!$s['active'] || ($s['tid'] !== null && isset($left[$s['tid']])))) continue;
        if (!$show($x)) continue;
        $out[] = ['id' => $id, 'name' => $s['name'], 'role' => $s['role'], 'teaching' => $s['teaching'], 'until' => $until];
    }
    foreach ($left as $tid => $name) {
        $id = 't:' . $tid; $x = $set[$id] ?? []; if (empty($x['until']) || !$show($x) || in_array($id, array_column($out, 'id'), true)) continue;
        $out[] = ['id' => $id, 'name' => (string)$name, 'role' => (string)($x['role'] ?? 'Teacher'), 'teaching' => true, 'until' => (string)$x['until']];
    }
    return $out;
}
function sa_norm(string $n): array {
    $n = strtolower($n); $n = preg_replace("/\b(ma'?am|madam|sir|mr|mrs|ms|miss|dr)\b\.?/", ' ', $n); $n = preg_replace('/[^a-z]+/', ' ', $n);
    return array_values(array_filter(explode(' ', trim($n))));
}
function sa_same(string $a, string $b): bool {
    $x = sa_norm($a); $y = sa_norm($b); if (count($x) < 2 || count($y) < 2) return $x && $x === $y;
    return !array_diff($x, $y) || !array_diff($y, $x);
}
// One-time (flag salSeed/former-2026-10): the four who worked till October 2026. An existing teacher / staff entry
// with the same name gets `until`; otherwise a new non-teaching entry is made (salary not set).
function sa_seed_former(string $uid): void {
    if (sch_rec_get('salSeed', 'former-2026-10')) return;
    $people = [["Akash Tiwari", 'Accountant'], ["Amisha Pandey Ma'am", 'Teacher'], ['Brijesh Kumar Yadav', 'Teacher'], ["Shiva Rai Ma'am", 'Reception']];
    $until = '2026-10-31'; $set = sal_rows('salStaff'); $cand = [];
    foreach (sal_staff_list() as $id => $s) $cand[$id] = $s['name'];
    foreach ((array)((sch_rec_get('settings', 'main') ?? [])['leftTeachers'] ?? []) as $tid => $n) $cand['t:' . $tid] ??= (string)$n;
    $done = [];
    foreach ($people as [$name, $role]) {
        $hit = null; foreach ($cand as $id => $n) if (sa_same($n, $name)) { $hit = $id; break; }
        if ($hit !== null) { $d = ($set[$hit] ?? []) + []; $d['until'] = $until; if (!isset($d['role']) && str_starts_with($hit, 't:')) $d['role'] = $role; sch_rec_put('salStaff', $hit, $d, $uid); $done[] = "$name (found, worked till 31 Oct)"; continue; }
        $id = 'x' . substr(md5('former|' . $name), 0, 12);
        sch_rec_put('salStaff', $id, ['name' => $name, 'role' => $role, 'monthly' => null, 'paidLeave' => 1, 'active' => false, 'until' => $until], $uid);
        $done[] = "$name (added, worked till 31 Oct)";
    }
    sch_rec_put('salSeed', 'former-2026-10', ['at' => sch_now_ms(), 'done' => $done], $uid);
    sch_log(['uid' => $uid, 'role' => 'system', 'label' => 'System'], 'Staff Attendance', 'Former staff added for their months: ' . implode('; ', $done));
}

try {
switch ($a) {
case 'month': {
    $ym = sa_ym((string)($_GET['ym'] ?? date('Y-m')));
    if ($role !== 'chairman') sa_seed_former('system');
    $cal = sal_rows('cal'); $work = array_flip(sal_workdays($ym, $cal)); $n = (int)date('t', strtotime($ym . '-01')); $today = date('Y-m-d');
    $hol = []; foreach ($cal as $e) if (($e['type'] ?? '') === 'Holiday' && !empty($e['date'])) { $d = (string)$e['date']; $to = (string)(($e['to'] ?? '') ?: $d); for ($i = 0; $d <= $to && $i < 400; $i++, $d = date('Y-m-d', strtotime($d . ' +1 day'))) $hol[$d] = (string)($e['title'] ?? 'Holiday'); }
    $days = [];
    for ($i = 1; $i <= $n; $i++) { $d = sprintf('%s-%02d', $ym, $i); $days[] = ['date' => $d, 'kind' => isset($work[$d]) ? 'work' : (date('w', strtotime($d)) === '0' ? 'sun' : 'hol'), 'title' => $hol[$d] ?? '', 'future' => $d > $today]; }
    $att = [];
    foreach (sal_rows('staffAtt', $ym) as $d => $r) foreach ((array)($r['absent'] ?? []) as $t) $att['t:' . $t][$d] = ((int)(($r['from'] ?? [])[$t] ?? 0)) > 0 ? 'H' : 'A';
    foreach (sal_rows('leave') as $l) if (($l['status'] ?? '') === 'Approved' && !empty($l['tid'])) {
        for ($d = max((string)$l['from'], $ym . '-01'), $i = 0; $d <= min((string)$l['to'], $ym . '-31') && $i < 40; $i++, $d = date('Y-m-d', strtotime($d . ' +1 day'))) $att['t:' . $l['tid']][$d] = 'L';
    }
    foreach (sal_rows('salAtt', $ym) as $d => $r) { foreach ((array)($r['a'] ?? []) as $x) $att[$x][$d] = 'A'; foreach ((array)($r['h'] ?? []) as $x) $att[$x][$d] = 'H'; }
    sch_json(['ok' => true, 'ym' => $ym, 'staff' => sa_staff($ym), 'days' => $days, 'att' => (object)$att, 'can' => $role !== 'chairman', 'school' => (string)((sch_rec_get('settings', 'main') ?? [])['school'] ?? '')]);
}
case 'save': {
    if ($method !== 'POST') sch_fail('POST only', 405);
    if ($role === 'chairman') sch_fail('The Chairman login can only view.', 403);
    $in = json_decode(file_get_contents('php://input', false, null, 0, 400000) ?: '', true); if (!is_array($in)) sch_fail('Bad request.');
    $ym = sa_ym((string)($in['ym'] ?? '')); $set = is_array($in['set'] ?? null) ? $in['set'] : [];
    $work = array_flip(sal_workdays($ym, sal_rows('cal'))); $today = date('Y-m-d');
    $L = sa_staff($ym); $ids = array_column($L, 'name', 'id'); $till = array_column($L, 'until', 'id'); $changed = 0; $skipped = 0;
    $db->beginTransaction();
    $db->query("SELECT uid FROM sch_users WHERE uid='principal' FOR UPDATE")->fetch();
    foreach ($set as $d => $row) {
        $d = (string)$d;
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) || substr($d, 0, 7) !== $ym || $d > $today || !isset($work[$d]) || !is_array($row)) { $skipped += is_array($row) ? count($row) : 1; continue; }
        $sa = sch_rec_get('staffAtt', $d) ?? []; $na = sch_rec_get('salAtt', $d) ?? []; $sa0 = $sa; $na0 = $na;
        foreach ($row as $id => $v) {
            $id = (string)$id; $v = strtoupper((string)$v);
            if (!isset($ids[$id]) || !in_array($v, ['P', 'A', 'H'], true) || ($till[$id] !== '' && $d > $till[$id])) { $skipped++; continue; }
            if (str_starts_with($id, 't:')) {
                $t = substr($id, 2); $abs = array_values(array_diff((array)($sa['absent'] ?? []), [$t])); $from = (array)($sa['from'] ?? []); unset($from[$t]);
                if ($v !== 'P') { $abs[] = $t; if ($v === 'H') $from[$t] = SA_HALF_BELL; }
                $sa['absent'] = $abs; $sa['from'] = (object)$from;
            } else {
                $na['a'] = array_values(array_diff((array)($na['a'] ?? []), [$id])); $na['h'] = array_values(array_diff((array)($na['h'] ?? []), [$id]));
                if ($v === 'A') $na['a'][] = $id; if ($v === 'H') $na['h'][] = $id;
            }
            $changed++;
        }
        if (json_encode($sa) !== json_encode($sa0)) sch_rec_put('staffAtt', $d, $sa, $uid);
        if (json_encode($na) !== json_encode($na0)) sch_rec_put('salAtt', $d, $na, $uid);
    }
    sch_log($u, 'Staff Attendance', "Bulk update $ym: $changed entr" . ($changed === 1 ? 'y' : 'ies') . ($skipped ? ", $skipped skipped (Sunday / holiday / future / unknown)" : ''));
    $db->commit();
    sch_json(['ok' => true, 'changed' => $changed, 'skipped' => $skipped]);
}
default: sch_fail('Unknown action.');
}
} catch (Throwable $e) { if ($db->inTransaction()) $db->rollBack(); throw $e; }
