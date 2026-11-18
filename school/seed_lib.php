<?php
// ============================================================
// One-time data changes the owner asked for, done on the server so nobody has to tap through the ERP.
// Each runs ONCE (flag in `salSeed`, a "sal" kind that never goes to phones) the first time anyone's
// app loads data (api.php load/since) or the Salary app opens its sheet, and is written as uid `system`
// with a line in the Activity Log.
// ============================================================
declare(strict_types=1);
require_once __DIR__ . '/salary_lib.php';

// Owner, 18 Nov 2026: "Ritu Ma'am fir se aa gayi hain, UKG class teacher bana do, add kar do, Rati Ma'am ko hata do".
// Ritu: the active teacher of that name; else the same person brought back under her old ID from settings.leftTeachers
// (so her old attendance/salary still match); else a new teacher. Her salary entry is made active again. UKG's class
// teacher becomes Ritu — that is what removes Rati Ma'am from UKG. Rati Ma'am stays on the staff (only the class changes).
function sch_seed_ritu_ukg(): void {
    if (sch_rec_get('salSeed', 'ritu-ukg-2026-11')) return;
    $cls = sch_rec_get('classes', 'main');
    if (!is_array($cls) || !array_filter($cls, fn($c) => ($c['c'] ?? '') === 'UKG')) return;   // classes not saved yet — try on a later load
    $set = sch_rec_get('settings', 'main') ?? []; $left = (array)($set['leftTeachers'] ?? []);
    $teachers = sal_rows('teacher'); $done = []; $tid = null;
    foreach ($teachers as $k => $t) if (sal_name_same((string)($t['name'] ?? ''), 'Ritu Singh')) { $tid = (string)$k; break; }
    if ($tid === null) {
        foreach ($left as $k => $n) if (sal_name_same((string)$n, 'Ritu Singh')) { $tid = (string)$k; break; }
        if ($tid !== null) {
            sch_rec_put('teacher', $tid, ['name' => (string)$left[$tid], 'role' => 'UKG Class Teacher', 'phone' => ''], 'system');
            unset($left[$tid]); $set['leftTeachers'] = $left; sch_rec_put('settings', 'main', $set, 'system');
            $done[] = "Ritu Ma'am back on the staff (old ID $tid)";
        } else {
            $n = 1; while (isset($teachers['T' . $n]) || isset($left['T' . $n])) $n++; $tid = 'T' . $n;
            sch_rec_put('teacher', $tid, ['name' => "Ritu Singh Ma'am", 'role' => 'UKG Class Teacher', 'phone' => ''], 'system');
            $done[] = "Ritu Singh Ma'am added as a teacher ($tid) — set her PIN in Settings";
        }
    }
    $ss = sch_rec_get('salStaff', 't:' . $tid);
    if ($ss && (($ss['until'] ?? '') !== '' || ($ss['active'] ?? true) === false)) { unset($ss['until']); $ss['active'] = true; sch_rec_put('salStaff', 't:' . $tid, $ss, 'system'); $done[] = 'her salary entry is active again'; }
    $all = sal_rows('teacher'); $old = '';
    foreach ($cls as &$c) if (($c['c'] ?? '') === 'UKG') { $old = (string)($c['ct'] ?? ''); $c['ct'] = $tid; } unset($c);
    if ($old !== $tid) { sch_rec_put('classes', 'main', array_values($cls), 'system'); $done[] = 'UKG class teacher: ' . (string)($all[$old]['name'] ?? ($left[$old] ?? ($old ?: 'none'))) . ' → ' . (string)($all[$tid]['name'] ?? 'Ritu'); }
    sch_rec_put('salSeed', 'ritu-ukg-2026-11', ['at' => sch_now_ms(), 'tid' => $tid, 'done' => $done], 'system');
    sch_log(['uid' => 'system', 'role' => 'system', 'label' => 'System'], 'Settings', 'Owner request (18 Nov 2026): ' . (implode('; ', $done) ?: 'Ritu Ma\'am was already UKG class teacher'));
}
