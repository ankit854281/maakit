<?php
// ============================================================
// Staff salary — hisaab (school/salary_api.php)
//
// Ek mahine ki salary:
//   kaam ke din   = mahine ke din − Sunday − chhutti (calendar me "Holiday")
//   gair-hazri    = staff attendance me absent (half day = 0.5) + approved leave
//                   (sirf kaam ke dino par; ek din ek hi baar ginta hai)
//                   — non-teaching staff ke liye accountant khud din likhta hai
//   bina-vetan din = gair-hazri − mahine ki paid leave (default 1)
//   katauti       = salary ÷ kaam ke din × bina-vetan din
//   net           = salary − katauti − advance + bonus
// "Paid" hone par us waqt ke number jam (freeze) jaate hain.
// ============================================================
declare(strict_types=1);

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
    $out = []; $n = (int)date('t', strtotime($ym . '-01'));
    for ($i = 1; $i <= $n; $i++) { $d = sprintf('%s-%02d', $ym, $i); if (date('w', strtotime($d)) !== '0' && !isset($hol[$d])) $out[] = $d; }
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

function sal_calc(array $staff, array $adj, array $work, ?array $auto): array {
    $gross = (int)($staff['monthly'] ?? 0);
    $wd = count($work);
    $manual = isset($adj['manualAbsent']) && $adj['manualAbsent'] !== null && $adj['manualAbsent'] !== '';
    $absent = $manual ? (float)$adj['manualAbsent'] : (float)($auto['total'] ?? 0);
    $paidLeave = (float)($staff['paidLeave'] ?? 1);
    $unpaid = max(0.0, $absent - $paidLeave);
    $ded = $wd ? (int)round($gross / $wd * min($unpaid, $wd)) : 0;
    $adv = (int)($adj['advance'] ?? 0); $bonus = (int)($adj['bonus'] ?? 0);
    return ['gross' => $gross, 'workDays' => $wd, 'absent' => $absent, 'manual' => $manual, 'auto' => $auto, 'paidLeave' => $paidLeave,
            'unpaid' => $unpaid, 'deduction' => $ded, 'advance' => $adv, 'bonus' => $bonus, 'net' => $gross - $ded - $adv + $bonus, 'note' => (string)($adj['note'] ?? '')];
}

// Sab staff: teacher records (apne aap) + non-teaching staff (salStaff, id "x…")
function sal_staff_list(): array {
    $set = sal_rows('salStaff'); $out = [];
    foreach (sal_rows('teacher') as $tid => $t) {
        $id = 't:' . $tid; $s = $set[$id] ?? [];
        $out[$id] = ['id' => $id, 'tid' => (string)$tid, 'name' => (string)($t['name'] ?? $tid), 'role' => (string)($t['role'] ?? 'Teacher'),
                     'monthly' => $s['monthly'] ?? null, 'paidLeave' => $s['paidLeave'] ?? 1, 'active' => $s['active'] ?? true, 'teaching' => true];
    }
    foreach ($set as $id => $s) if (str_starts_with($id, 'x')) $out[$id] = ['id' => $id, 'tid' => null, 'name' => (string)($s['name'] ?? ''), 'role' => (string)($s['role'] ?? 'Staff'),
        'monthly' => $s['monthly'] ?? null, 'paidLeave' => $s['paidLeave'] ?? 1, 'active' => $s['active'] ?? true, 'teaching' => false];
    uasort($out, fn($a, $b) => [!$a['active'], !$a['teaching'], $a['name']] <=> [!$b['active'], !$b['teaching'], $b['name']]);
    return $out;
}

// Ek mahine ki poori sheet
function sal_sheet(string $ym, ?string $onlyId = null): array {
    $work = sal_workdays($ym, sal_rows('cal'));
    $att = sal_rows('staffAtt', $ym); $leaves = sal_rows('leave'); $saved = sal_rows('salMonth', $ym . '|');
    $rows = [];
    foreach (sal_staff_list() as $id => $s) {
        if ($onlyId !== null && $id !== $onlyId) continue;
        $m = $saved[$ym . '|' . $id] ?? [];
        if (($m['status'] ?? '') === 'Paid') { $rows[] = ['staff' => $s, 'calc' => $m['calc'], 'status' => 'Paid', 'pay' => array_intersect_key($m, array_flip(['paidOn', 'mode', 'ref', 'by', 'at']))]; continue; }
        if (!$s['active'] && !$m) continue;
        $auto = $s['tid'] !== null ? sal_absent_days($s['tid'], $work, $att, $leaves) : null;
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
