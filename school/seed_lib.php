<?php
// ============================================================
// One-time data changes the owner asked for, done on the server so nobody has to tap through the ERP.
// Each runs ONCE (flag in `salSeed`, a "sal" kind that never goes to phones) the first time anyone's
// app loads data (api.php load/since) or the Salary app opens its sheet, and is written as uid `system`
// with a line in the Activity Log.
// ============================================================
declare(strict_types=1);
require_once __DIR__ . '/salary_lib.php';

// Shared: make one person a class teacher. The teacher = the active teacher with that name, else the same person brought
// back under the OLD ID from settings.leftTeachers (old attendance / salary keep matching), else a new teacher T<n>.
// A salary entry that was closed (until / inactive) is opened again; a NEW teacher's salary starts from $fromYm (salary not
// set — the Manager sets it). Only that class's `ct` changes; the previous class teacher stays on the staff. Runs once per
// flag and waits while classes/main does not exist yet.
function sch_seed_class_teacher(string $flag, string $match, string $newName, string $role, string $cls, string $fromYm, string $why): void {
    if (sch_rec_get('salSeed', $flag)) return;
    $classes = sch_rec_get('classes', 'main');
    if (!is_array($classes) || !array_filter($classes, fn($c) => ($c['c'] ?? '') === $cls)) return;
    $set = sch_rec_get('settings', 'main') ?? []; $left = (array)($set['leftTeachers'] ?? []);
    $teachers = sal_rows('teacher'); $done = []; $tid = null;
    foreach ($teachers as $k => $t) if (sal_name_same((string)($t['name'] ?? ''), $match)) { $tid = (string)$k; break; }
    if ($tid === null) {
        foreach ($left as $k => $n) if (sal_name_same((string)$n, $match)) { $tid = (string)$k; break; }
        if ($tid !== null) {
            sch_rec_put('teacher', $tid, ['name' => (string)$left[$tid], 'role' => $role, 'phone' => ''], 'system');
            unset($left[$tid]); $set['leftTeachers'] = $left; sch_rec_put('settings', 'main', $set, 'system');
            $done[] = "$newName back on the staff (old ID $tid)";
        } else {
            $n = 1; while (isset($teachers['T' . $n]) || isset($left['T' . $n])) $n++; $tid = 'T' . $n;
            sch_rec_put('teacher', $tid, ['name' => $newName, 'role' => $role, 'phone' => ''], 'system');
            if (!sch_rec_get('salStaff', 't:' . $tid)) sch_rec_put('salStaff', 't:' . $tid, ['monthly' => null, 'paidLeave' => 1, 'active' => true, 'from' => $fromYm . '-01'], 'system');
            $done[] = "$newName added as a teacher ($tid) — set the PIN in Settings and the salary in the Salary app";
        }
    }
    $ss = sch_rec_get('salStaff', 't:' . $tid);
    if ($ss && (($ss['until'] ?? '') !== '' || ($ss['active'] ?? true) === false)) { unset($ss['until']); $ss['active'] = true; sch_rec_put('salStaff', 't:' . $tid, $ss, 'system'); $done[] = 'salary entry active again'; }
    $all = sal_rows('teacher'); $old = '';
    foreach ($classes as &$c) if (($c['c'] ?? '') === $cls) { $old = (string)($c['ct'] ?? ''); $c['ct'] = $tid; } unset($c);
    if ($old !== $tid) { sch_rec_put('classes', 'main', array_values($classes), 'system'); $done[] = "$cls class teacher: " . (string)($all[$old]['name'] ?? ($left[$old] ?? ($old ?: 'none'))) . ' → ' . (string)($all[$tid]['name'] ?? $newName); }
    sch_rec_put('salSeed', $flag, ['at' => sch_now_ms(), 'tid' => $tid, 'done' => $done], 'system');
    sch_log(['uid' => 'system', 'role' => 'system', 'label' => 'System'], 'Settings', "Owner request ($why): " . (implode('; ', $done) ?: "$newName was already $cls class teacher"));
}

// Owner, 18 Nov 2026: "Ritu Ma'am fir se aa gayi hain, UKG class teacher bana do, add kar do, Rati Ma'am ko hata do".
// UKG → Ritu (that removes Rati Ma'am from UKG; she stays on the staff).
function sch_seed_ritu_ukg(): void {
    sch_seed_class_teacher('ritu-ukg-2026-11', 'Ritu Singh', "Ritu Singh Ma'am", 'UKG Class Teacher', 'UKG', '2026-11', '18 Nov 2026');
}

// Owner, 18 Nov 2026: "Ankit Tripathi Sir ko add, class 8th ka" — new teacher, Class 8th class teacher, salary from November.
function sch_seed_ankit_8th(): void {
    sch_seed_class_teacher('ankit-8th-2026-11', 'Ankit Tripathi', 'Ankit Tripathi Sir', 'Class 8th Class Teacher', '8th', '2026-11', '18 Nov 2026');
}

// Every one-time change, in order (called from api.php load / since and salary_api.php sheet).
function sch_seeds(): void {
    foreach (['sch_seed_ritu_ukg', 'sch_seed_ankit_8th'] as $f) { try { $f(); } catch (Throwable $e) { error_log("$f: " . $e->getMessage()); } }
}
