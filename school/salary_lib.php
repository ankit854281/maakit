<?php
// ============================================================
// Staff salary — hisaab (school/salary_api.php)
//
// Ek mahine ki salary:
//   kaam ke din   = mahine ke din − Sunday − chhutti (calendar me "Holiday")
//   gair-hazri    = staff attendance me absent (half day = 0.5) + approved leave
//                   (sirf kaam ke dino par; ek din ek hi baar ginta hai)
//                   — non-teaching staff ke liye accountant khud din likhta hai
//   bina-vetan din = (approved leave + half day) − mahine ki paid leave (default 1)
//                   + bina ijazat absent (staffAtt me absent, approved leave nahi) — ye
//                   paid leave se kabhi nahi bharta (leave policy, 12 Nov 2026)
//   4 se zyada chhutti (policy maxLeave) = salary sirf kaam kiye dino ki: gross ÷ mahine ke din × (kaam ke din − gair-hazri)
//   bonus         = Adjust ka bonus + full-attendance bonus (zero absence, policy ₹300)
//   katauti       = salary ÷ kaam ke din × bina-vetan din
//   net           = salary − katauti − advance + bonus
// "Paid" hone par us waqt ke number jam (freeze) jaate hain.
// ============================================================
declare(strict_types=1);
require_once __DIR__ . '/leavepolicy_lib.php';

const SAL_MODES = ['Bank transfer', 'Cash', 'UPI', 'Cheque'];

function sal_rows(string $k, ?string $prefix = null): array {
    $q = sch_db()->prepare('SELECT r, d FROM sch_records WHERE k=? AND deleted=0' . ($prefix !== null ? ' AND r LIKE ?' : ''));
    $q->execute($prefix !== null ? [$k, $prefix . '%'] : [$k]);
    $out = [];
    foreach ($q as $row) { $d = json_decode((string)$row['d'], true); if (is_array($d)) $out[(string)$row['r']] = $d; }
    return $out;
}

function sal_ym_ok(string $ym): bool { return (bool)preg_match('/^20\d\d-(0[1-9]|1[0-2])$/', $ym); }

// Mahine ke kaam ke din (Sunday aur Holiday chhod kar)
function sal_workdays(string $ym, array $cal): array {
    $hol = [];
    foreach ($cal as $e) if (($e['type'] ?? '') === 'Holiday' && !empty($e['date'])) {
        $d = (string)$e['date']; $to = (string)(($e['to'] ?? '') ?: $d);
        for ($i = 0; $d <= $to && $i < 400; $i++, $d = date('Y-m-d', strtotime($d . ' +1 day'))) $hol[$d] = 1;
    }
    $open = sal_rows('salOpen', $ym . '-');   // holiday / Sunday the staff came to school (staffatt_api.php ?a=open) = a working day for staff
    $out = []; $n = (int)date('t', strtotime($ym . '-01'));
    for ($i = 1; $i <= $n; $i++) { $d = sprintf('%s-%02d', $ym, $i); if ((date('w', strtotime($d)) !== '0' && !isset($hol[$d])) || isset($open[$d])) $out[] = $d; }
    return $out;
}

// Teacher ki gair-hazri (din) us mahine — staffAtt + approved leave
function sal_absent_days(string $tid, array $work, array $att, array $leaves): array {
    $full = 0.0; $half = 0; $leave = 0;
    foreach ($work as $d) {
        $onLeave = false;
        foreach ($leaves as $l) if (($l['status'] ?? '') === 'Approved' && ($l['tid'] ?? '') === $tid && ($l['from'] ?? '9') <= $d && ($l['to'] ?? '') >= $d) { $onLeave = true; break; }
        if ($onLeave) { $leave++; continue; }
        $a = $att[$d] ?? null;
        if ($a && in_array($tid, $a['absent'] ?? [], true)) { if ((int)(($a['from'] ?? [])[$tid] ?? 0) > 0) $half++; else $full++; }
    }
    return ['absent' => $full, 'half' => $half, 'leave' => $leave, 'total' => $full + $leave + $half * 0.5];
}

// Non-teaching staff (driver, Dai, watchman…): absence from Staff Attendance (salAtt). No leave system for them, so an
// absence is counted like leave (paid leave covers it). null = no attendance kept this month → office types the days.
function sal_nt_days(string $id, array $nat): ?array {
    if (!$nat) return null;
    $full = 0; $half = 0;
    foreach ($nat as $r) { if (in_array($id, (array)($r['a'] ?? []), true)) $full++; elseif (in_array($id, (array)($r['h'] ?? []), true)) $half++; }
    return ['absent' => 0, 'half' => $half, 'leave' => $full, 'total' => $full + $half * 0.5, 'nonTeaching' => true];
}

function sal_calc(array $staff, array $adj, array $work, ?array $auto): array {
    $gross = (int)($staff['monthly'] ?? 0);
    $wd = count($work);
    $manual = isset($adj['manualAbsent']) && $adj['manualAbsent'] !== null && $adj['manualAbsent'] !== '';
    $absent = $manual ? (float)$adj['manualAbsent'] : (float)($auto['total'] ?? 0);
    $paidLeave = (float)($staff['paidLeave'] ?? 1);
    // Leave policy: paid leave covers approved leave + half days; absent without permission is always cut.
    $unapproved = (!$manual && $auto !== null) ? (float)($auto['absent'] ?? 0) : 0.0;
    $unpaid = max(0.0, $absent - $unapproved - $paidLeave) + $unapproved;
    $ded = $wd ? (int)round($gross / $wd * min($unpaid, $wd)) : 0;
    $teach = $auto !== null && empty($auto['nonTeaching']);     // staff rules (bonus, worked-only) are for teachers
    $autoBonus = ($teach && !$manual && $gross > 0 && $wd > 0 && (float)($auto['total'] ?? 0) == 0.0) ? lp_policy()['bonus'] : 0;
    // Staff rules v3 (owner): total leave above maxLeave (default 4) in the month = salary only for the days actually worked:
    // gross ÷ days of the month × (working days − absence). Sundays, holidays and paid leave are not paid that month.
    $dim = $wd ? (int)date('t', strtotime(substr((string)$work[0], 0, 7) . '-01')) : 0; $offDays = max(0, $dim - $wd); $offCut = 0;
    if ($teach && !$manual && $dim > 0 && (float)($auto['total'] ?? 0) > lp_policy()['maxLeave']) {
        $workedPay = (int)round($gross / $dim * max(0.0, $wd - $absent));
        $offCut = max(0, ($gross - $workedPay) - $ded); $ded = $gross - $workedPay; $unpaid = $absent;
    }
    $adv = (int)($adj['advance'] ?? 0); $bonus = (int)($adj['bonus'] ?? 0) + $autoBonus;
    return ['gross' => $gross, 'workDays' => $wd, 'absent' => $absent, 'manual' => $manual, 'auto' => $auto, 'paidLeave' => $paidLeave,
            'unapproved' => $unapproved, 'unpaid' => $unpaid, 'deduction' => $ded, 'advance' => $adv, 'bonus' => $bonus, 'autoBonus' => $autoBonus, 'offDays' => $offCut ? $offDays : 0, 'offCut' => $offCut, 'workedOnly' => $teach && !$manual && $dim > 0 && (float)($auto['total'] ?? 0) > lp_policy()['maxLeave'],
            'net' => $gross - $ded - $adv + $bonus, 'note' => (string)($adj['note'] ?? '')];
}

// Sab staff: teacher records (apne aap) + non-teaching staff (salStaff, id "x…")
function sal_staff_list(): array {
    $set = sal_rows('salStaff'); $out = [];
    foreach (sal_rows('teacher') as $tid => $t) {
        $id = 't:' . $tid; $s = $set[$id] ?? [];
        $out[$id] = ['id' => $id, 'tid' => (string)$tid, 'name' => (string)($t['name'] ?? $tid), 'role' => (string)($t['role'] ?? 'Teacher'),
                     'monthly' => $s['monthly'] ?? null, 'paidLeave' => $s['paidLeave'] ?? 1, 'active' => $s['active'] ?? true, 'teaching' => true, 'until' => (string)($s['until'] ?? '')];
    }
    foreach ($set as $id => $s) if (str_starts_with($id, 'x')) $out[$id] = ['id' => $id, 'tid' => null, 'name' => (string)($s['name'] ?? ''), 'role' => (string)($s['role'] ?? 'Staff'),
        'monthly' => $s['monthly'] ?? null, 'paidLeave' => $s['paidLeave'] ?? 1, 'active' => $s['active'] ?? true, 'teaching' => false, 'until' => (string)($s['until'] ?? '')];
    uasort($out, fn($a, $b) => [!$a['active'], !$a['teaching'], $a['name']] <=> [!$b['active'], !$b['teaching'], $b['name']]);
    return $out;
}

// Ek mahine ki poori sheet
function sal_sheet(string $ym, ?string $onlyId = null): array {
    $work = sal_workdays($ym, sal_rows('cal'));
    $att = sal_rows('staffAtt', $ym); $leaves = sal_rows('leave'); $saved = sal_rows('salMonth', $ym . '|');
    $nat = array_intersect_key(sal_rows('salAtt', $ym), array_flip($work));   // non-teaching attendance (staffatt_api.php), working days only
    $rows = [];
    foreach (sal_staff_list() as $id => $s) {
        if ($onlyId !== null && $id !== $onlyId) continue;
        $m = $saved[$ym . '|' . $id] ?? [];
        if (($m['status'] ?? '') === 'Paid') { $rows[] = ['staff' => $s, 'calc' => $m['calc'], 'status' => 'Paid', 'pay' => array_intersect_key($m, array_flip(['paidOn', 'mode', 'ref', 'by', 'at']))]; continue; }
        if (!$s['active'] && !$m) continue;
        if ($s['until'] !== '' && $ym > substr($s['until'], 0, 7) && !$m) continue;   // left the school (staffatt_api.php `until`)
        $auto = $s['tid'] !== null ? sal_absent_days($s['tid'], $work, $att, $leaves) : sal_nt_days($id, $nat);
        $rows[] = ['staff' => $s, 'calc' => sal_calc($s, $m, $work, $auto), 'status' => $s['monthly'] === null ? 'Not set' : 'Due', 'pay' => null];
    }
    return ['ym' => $ym, 'workDays' => count($work), 'rows' => $rows];
}

function sal_rupees($v, int $min, int $max, string $what): int {
    if (is_string($v)) $v = trim($v);
    if ($v === '' || $v === null) return 0;
    if (!is_numeric($v) || (float)$v != floor((float)$v)) throw new InvalidArgumentException("$what must be whole rupees.");
    $n = (int)$v; if ($n < $min || $n > $max) throw new InvalidArgumentException("$what must be between ₹$min and ₹" . number_format($max) . '.');
    return $n;
}

// ============================================================
// September 2026 salary sheet → Salary app (owner, 16 Nov 2026: "ye data school new ERP me update karne ke liye
// diya hai, jisse salary system strong aur auto ho paye"). Monthly salaries from the school's own Excel
// (fac_attendence_of_oct_month.xlsx, sheet "Sep 2026 Month Payout", names as in its Sep attendance sheet).
// The Manager sees a preview (?a=importplan) and applies it (?a=import): ONLY staff whose salary is NOT set get it;
// a salary already set is never changed. Atul Pal had no salary in the sheet, so he is not listed.
// ============================================================
const SAL_SHEET_2026_09 = [
    ['Prabhakar Dubey', 11000], ['Rati Singh', 10000], ['Mamta Shukla', 8000], ['Brijesh Kumar', 6000], ['Pawan Bhalla', 9000],
    ['Ritu Singh', 5500], ['Priyanka Singh', 4500], ['Monika Tiwari', 4000], ['Amisha Pandey', 6000], ['Astha Pandey', 9000],
    ['Shiva Rai', 6000], ['Seema Patel', 4500], ['Shristi Dubey', 3500], ['Durgesh Yadav', 7000], ['Devvrat Yadav', 6500],
    ['Akash Tiwari', 5000], ['Dipshika Mishra', 3000], ['Shradha Singh', 4500], ['Adarsh Dubey', 7000], ['Shikha Pandey', 4000],
];

// Name words, spelling-tolerant: "Shrishti" = "Shristi", "Devvrat" = "Devrat", Ma'am / Sir ignored.
function sal_name_words(string $n): array {
    $n = strtolower($n); $n = preg_replace("/\b(ma'?am|madam|sir|mr|mrs|ms|miss|dr)\b\.?/", ' ', $n); $n = preg_replace('/[^a-z]+/', ' ', $n);
    $out = [];
    foreach (array_filter(explode(' ', trim($n))) as $w) { $w = str_replace(['ee', 'oo', 'w', 'h'], ['i', 'u', 'v', ''], $w); $out[] = preg_replace('/(.)\1+/', '$1', $w); }
    return array_values(array_unique(array_filter($out)));
}
// Same person = same first name AND every word of the shorter name is in the longer one
// ("Brijesh Kumar" = "Brijesh Kumar Yadav", "Ritu Singh" = "Ritu"; "Priyanka Singh" ≠ "Priyanka Jaiswal").
function sal_name_same(string $a, string $b): bool {
    $x = sal_name_words($a); $y = sal_name_words($b);
    if (!$x || !$y || $x[0] !== $y[0]) return false;
    return !array_diff($x, $y) || !array_diff($y, $x);
}

function sal_import_plan(): array {
    $all = sal_staff_list(); $out = [];
    foreach (SAL_SHEET_2026_09 as [$name, $monthly]) {
        $hits = array_values(array_filter($all, fn($s) => sal_name_same($s['name'], $name)));
        if (count($hits) > 1) { $act = array_values(array_filter($hits, fn($s) => $s['active'])); if (count($act) === 1) $hits = $act; }
        $r = ['sheet' => $name, 'monthly' => $monthly, 'id' => null, 'erpName' => '', 'current' => null, 'action' => 'notfound'];
        if (count($hits) > 1) { $r['action'] = 'many'; $r['erpName'] = implode(', ', array_map(fn($s) => $s['name'], $hits)); }
        elseif ($hits) {
            $s = $hits[0]; $r['id'] = $s['id']; $r['erpName'] = $s['name']; $r['current'] = $s['monthly'];
            $r['action'] = $s['monthly'] === null ? 'set' : ((int)$s['monthly'] === $monthly ? 'same' : 'keep');
        }
        $out[] = $r;
    }
    return $out;
}

// ============================================================
// October 2026 teacher register (owner, 17 Nov 2026: "1 2 3 date ko school me teacher work kiye, aap lock karke rakkhe ho,
// attendance update nahi ho payega"). The ERP calendar had school days of October as holidays, so staff attendance could
// not be filled on them. One time (flag salSeed/oct-2026-register): every day the register shows teachers working that is
// closed in the ERP is opened for staff (salOpen, same as the "staff came" tap), and the register's absentees for ONLY those
// newly opened days are written (nobody could fill them before). Days already open are never touched — what the office
// saved stays. Register holidays: Sundays, 10 (blank column), 19–21 (written holiday), 26 and 30 (blank columns).
// ============================================================
const SAL_REG_2026_10_OFF = [4, 10, 11, 18, 19, 20, 21, 25, 26, 30];
const SAL_REG_2026_10_ABSENT = [   // name in the register => days absent
    'Prabhakar Dubey' => [9], 'Rati Singh' => [27, 28, 29], 'Mamta Shukla' => [29], 'Brijesh Kumar' => [13, 15, 23, 24, 27, 28, 29, 31],
    'Pawan Bhalla' => [23, 28], 'Atul Pal' => [15, 23, 29, 31], 'Ritu Singh' => [23], 'Priyanka Singh' => [23, 24], 'Monika Tiwari' => [3, 23, 29, 31],
    'Amisha Pandey' => [1, 2, 3, 9, 15, 16, 17, 22, 23, 24, 27, 28, 29, 31], 'Astha Pandey' => [1, 2, 5, 23], 'Shiva Rai' => [12, 22, 23, 27, 28, 29, 31],
    'Seema Patel' => [29], 'Shradha Singh' => [16, 17, 23], 'Durgesh Yadav' => [23], 'Devvrat Yadav' => [23], 'Akash Tiwari' => [12, 13, 31],
    'Dipshika Mishra' => [3, 9, 31], 'Adarsh Dubey' => [23], 'Shikha Pandey' => [23, 27],
];
// register name → staff id: first word equal and one name's words inside the other's (Sir/Ma'am ignored); only a single match counts
function sal_reg_match(string $name, array $list): ?string {
    $a = sal_name_words($name); if (!$a) return null; $hit = [];
    foreach ($list as $id => $s) { $b = sal_name_words((string)$s['name']); if (!$b || $a[0] !== $b[0]) continue;
        [$sm, $bg] = count($a) <= count($b) ? [$a, $b] : [$b, $a]; if (!array_diff($sm, $bg)) $hit[] = $id; }
    return count($hit) === 1 ? $hit[0] : null;
}
function sal_seed_oct_register(string $uid = 'system'): void {
    if (date('Y-m-d') < '2026-11-01' || sch_rec_get('salSeed', 'oct-2026-register')) return;
    $work = array_flip(sal_workdays('2026-10', sal_rows('cal'))); $opened = [];
    for ($i = 1; $i <= 31; $i++) { $d = sprintf('2026-10-%02d', $i);
        if (in_array($i, SAL_REG_2026_10_OFF, true) || isset($work[$d])) continue;
        sch_rec_put('salOpen', $d, ['by' => 'October register', 'at' => sch_now_ms(), 'title' => date('w', strtotime($d)) === '0' ? 'Sunday' : 'Holiday'], $uid);
        $opened[] = $i; }
    $list = sal_staff_list(); $miss = []; $marked = 0;
    foreach ($opened as $i) { $d = sprintf('2026-10-%02d', $i);
        $sa = sch_rec_get('staffAtt', $d) ?? []; $na = sch_rec_get('salAtt', $d) ?? []; $sa0 = $sa; $na0 = $na;
        foreach (SAL_REG_2026_10_ABSENT as $name => $days) { if (!in_array($i, $days, true)) continue;
            $id = sal_reg_match($name, $list); if ($id === null) { $miss[$name] = 1; continue; }
            if (str_starts_with($id, 't:')) { $t = substr($id, 2); $abs = (array)($sa['absent'] ?? []); if (!in_array($t, $abs, true)) { $abs[] = $t; $marked++; } $sa['absent'] = array_values($abs); $sa['from'] = (object)(array)($sa['from'] ?? []); }
            else { $x = (array)($na['a'] ?? []); if (!in_array($id, $x, true)) { $x[] = $id; $marked++; } $na['a'] = array_values($x); } }
        if ($sa !== $sa0) sch_rec_put('staffAtt', $d, $sa, $uid);
        if ($na !== $na0) sch_rec_put('salAtt', $d, $na, $uid); }
    sch_rec_put('salSeed', 'oct-2026-register', ['at' => sch_now_ms(), 'opened' => $opened, 'absentMarked' => $marked, 'notFound' => array_keys($miss)], $uid);
    sch_log(['uid' => $uid, 'role' => 'system', 'label' => 'System'], 'Staff Attendance', 'October 2026 register: opened ' . ($opened ? implode(', ', $opened) . ' Oct' : 'no day') . " for staff, $marked absent marked" . ($miss ? '; not found: ' . implode(', ', array_keys($miss)) : ''));
}
