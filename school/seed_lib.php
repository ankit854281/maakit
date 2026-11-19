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

// ============================================================
// LKG Half Yearly 2026-27 marks from the class register (owner, 19 Nov 2026: "Ye marks add dijiye please" — two photos of
// the LKG register: Hindi, English, Math, EVS, Drawing; Pre test, Copy, SEA, hf for 30 children). Saved as Term-1 (the
// Half Yearly) like the 5th-class register. Register Pre test is out of 20 → halved to the card's 10 (same as 5th);
// Copy → Notebook /5, SEA /5, hf → Term Exam /80, Ab → AB, blank stays blank.
// Safe: a child already having ANY mark in a subject keeps it (nothing typed by a teacher is overwritten); children are
// matched by name inside LKG (exact words first, then the only close match); register names that are not found or
// match more than one child (two "Shivansh Rai") are skipped and named in the Activity Log for the class teacher.
// ============================================================
const SCH_LKG_HY_SUBJ = ['Hindi', 'English', 'Math', 'EVS', 'Drawing'];
const SCH_LKG_HY = [
    [1, 'Abhinav Rai', [[8, 5, 5, 61], [8, 5, 5, 73], [8, 5, 5, 'AB'], [7, 5, 5, 79], [5, 5, 5, 65]]],
    [2, 'Ananya Pandey', [[6, 5, 5, 67], [8, 5, 5, 75], [10, 5, 5, 67], [9, 5, 5, 80], [7, 5, 5, 65]]],
    [3, 'Ansh Prajapati', [[8, 5, 5, 75], [8, 5, 5, 76], [10, 5, 5, 71], [8, 5, 5, 80], [8, 5, 5, 70]]],
    [4, 'Arushi Chauhan', [[8, 5, 5, 77], [7, 5, 5, 77], [10, 5, 5, 77], [7, 5, 5, 78], [6, 5, 5, 65]]],
    [5, 'Arnay Rai', [[6, 5, 5, 70], [7, 5, 5, 75], [5, 5, 5, 57], [6, 5, 5, 'AB'], [5, 5, 5, 60]]],
    [6, 'Divas Arya', [[8, 5, 5, 78], [8, 5, 5, 66], [8, 5, 5, 55], [5, 5, 5, 'AB'], [7, 5, 5, 'AB']]],
    [7, 'Kritika', [[7, 5, 5, 75], [6, 5, 5, 76], [9, 5, 5, 80], [5, 5, 5, 'AB'], [9, 5, 5, 65]]],
    [8, 'Ruhi Yadav', [[7, 5, 5, 64], [8, 5, 5, 80], [7, 5, 5, 63], [5, 5, 5, 80], [6, 5, 5, 70]]],
    [9, 'Nikki Gautam', [[7, 5, 5, 80], [10, 5, 5, 80], [10, 5, 5, 80], [7, 5, 5, 'AB'], [5, 5, 5, 70]]],
    [10, 'Manshvi Raghuvanshi', [[9, 5, 5, 80], [9, 5, 5, 80], [10, 5, 5, 80], [9, 5, 5, 80], [7, 5, 5, 55]]],
    [11, 'Shiva Saroj', [[12, 5, 5, 75], [14, 5, 5, 75], [5, 5, 5, 65], [7, 5, 5, 80], [9, 5, 5, 70]]],
    [12, 'Parth Pandey', [[9, 5, 5, 77], [9, 5, 5, 77], [9, 5, 5, 74], [9, 5, 5, 'AB'], [7, 5, 5, 70]]],
    [13, 'Rishabh Pal', [[6, 5, 5, 75], [7, 5, 5, 74], [4, 5, 5, 65], [5, 5, 5, 66], [8, 5, 5, 65]]],
    [14, 'Suyam Tiwari', [[16, 5, 5, 80], [9, 5, 5, 79], [9, 5, 5, 80], [9, 5, 5, 80], [9, 5, 5, 70]]],
    [15, 'Shivansh Rai', [[7, 5, 5, 65], [7, 5, 5, 62], [10, 5, 5, 55], [4, 5, 5, 'AB'], [6, 5, 5, 60]]],
    [16, 'Shreyam Bind', [[4, 5, 5, 'AB'], [8, 5, 5, 'AB'], [8, 5, 5, 'AB'], [6, 5, 5, 'AB'], [7, 5, 5, 'AB']]],
    [17, 'Yansh Pasi', [[8, 5, 5, 77], [10, 5, 5, 80], [9, 5, 5, 80], [8, 5, 5, 80], [8, 5, 5, 55]]],
    [18, 'Ananya Tiwari', [[7, 5, 5, 'AB'], [7, 5, 5, 'AB'], [5, 5, 5, 59], [5, 5, 5, 46], [5, 5, 5, 55]]],
    [19, 'Vaishnavi Bind', [[10, 5, 5, 'AB'], [7, 5, 5, 'AB'], [8, 5, 5, 'AB'], [12, 5, 5, 'AB'], [7, 5, 5, 'AB']]],
    [20, 'Diksha Kumari', [[7, 5, 5, 75], [8, 5, 5, 80], [10, 5, 5, 78], [7, 5, 5, 80], [6, 5, 5, 70]]],
    [21, 'Ananya Maurya', [[7, 5, 5, 77], [9, 5, 5, 'AB'], [9, 5, 5, 55], [7, 5, 5, 43], [7, 5, 5, 70]]],
    [22, 'Pragya Sharma', [[8, 5, 5, 77], [8, 5, 5, 77], [8, 5, 5, 75], [8, 5, 5, 62], [5, 5, 5, 70]]],
    [23, 'Shivansh Singh', [[11, 5, 5, 75], [13, 5, 5, 77], [9, 5, 5, 66], [6, 5, 5, 61], [8, 5, 5, 70]]],
    [24, 'Siddharth Arya', [[6, 5, 5, 76], [9, 5, 5, 76], [8, 5, 5, 59], [7, 5, 5, 38], [9, 5, 5, 60]]],
    [25, 'Arnav Sharma', [[7, 5, 5, 72], [8, 5, 5, 72], [6, 5, 5, 75], [7, 5, 5, 'AB'], [7, 5, 5, 70]]],
    [26, 'Parishu Saroj', [[11, 5, 5, 67], [7, 5, 5, 76], [7, 5, 5, 64], [10, 5, 5, 61], [6, 5, 5, 55]]],
    [27, 'Samarth Jaiswal', [[7, 5, 5, ''], [8, 5, 5, 80], [8, 5, 5, 65], [8, 5, 5, 47], [8, 5, 5, 55]]],
    [28, 'Shivansh Rai', [[7, 5, 5, 65], [12, 5, 5, 77], [7, 5, 5, 65], [7, 5, 5, 'AB'], [7, 5, 5, 60]]],
    [29, 'Kritika Rai', [[5, 5, 5, 72], [12, 5, 5, 70], [6, 5, 5, 75], [6, 5, 5, 'AB'], [5, 5, 5, 70]]],
    [30, 'Kavya', [[6, 5, 5, 71], [13, 5, 5, 77], [7, 5, 5, 59], [6, 5, 5, 'AB'], [6, 5, 5, 'AB']]],
];
const SCH_LKG_HY_CHECK = [
    '5|EVS' => 'hf: number struck, red Ab written',
    '6|EVS' => 'hf: number struck, red Ab written',
    '7|EVS' => 'hf: number struck, red Ab written',
    '9|EVS' => 'hf: "46" and red Ab both written',
    '12|EVS' => 'hf: "79" and red Ab both written',
    '13|EVS' => 'hf: 66 or 68',
    '18|EVS' => 'hf: 46 written over red Ab',
    '24|Math' => 'hf: 59 or 51',
    '27|Hindi' => 'hf blank in register'
];
function sch_seed_lkg_hy_subject(string $reg, array $alloc): string {
    $k = fn($s) => preg_replace('/[^a-z]/', '', strtolower($s));
    $syn = ['math' => ['math', 'maths', 'mathematics', 'mathsandmental', 'numbers'], 'evs' => ['evs', 'environmentalstudies', 'environment', 'environmentalscience', 'science'],
            'drawing' => ['drawing', 'art', 'artcraft', 'artandcraft', 'drawingcolouring', 'drawingandcolouring'], 'hindi' => ['hindi'], 'english' => ['english']][$k($reg)] ?? [$k($reg)];
    foreach ($alloc as $s) if (in_array($k($s), $syn, true)) return $s;
    foreach ($alloc as $s) foreach ($syn as $x) if (str_starts_with($k($s), $x)) return $s;
    return $reg;
}
function sch_seed_lkg_hy(): void {
    $flag = 'lkg-halfyearly-2026-27';
    if (sch_rec_get('salSeed', $flag)) return;
    $kids = array_filter(sal_rows('student'), fn($s) => ($s['cls'] ?? '') === 'LKG');
    if (count($kids) < 5) return;                                   // LKG children not in the ERP yet — try on a later load
    $ses = '2026-27'; $exam = 'Term-1';
    $alloc = []; foreach ((array)sch_rec_get('alloc', 'main') as $al) if (($al['c'] ?? '') === 'LKG' && ($al['s'] ?? '') !== '' && !in_array($al['s'], $alloc, true)) $alloc[] = (string)$al['s'];
    $subj = array_map(fn($s) => sch_seed_lkg_hy_subject($s, $alloc), SCH_LKG_HY_SUBJ);
    // match register names → LKG children
    $nameCount = array_count_values(array_map(fn($r) => strtolower(trim($r[1])), SCH_LKG_HY));
    $words = fn($n) => (function ($w) { sort($w); return implode(' ', $w); })(sal_name_words($n));
    $claimed = []; $match = []; $skip = [];
    foreach (SCH_LKG_HY as [$n, $name]) {                              // pass 1: same words exactly
        if ($nameCount[strtolower(trim($name))] > 1) { $skip[$n] = "$n. $name (two children with this name in the register)"; continue; }
        $hit = array_keys(array_filter($kids, fn($s) => $words((string)($s['name'] ?? '')) === $words($name)));
        if (count($hit) === 1 && !isset($claimed[$hit[0]])) { $match[$n] = $hit[0]; $claimed[$hit[0]] = 1; }
        elseif (count($hit) > 1) $skip[$n] = "$n. $name (more than one child of this name in the ERP)";
    }
    foreach (SCH_LKG_HY as [$n, $name]) {                              // pass 2: the only close match among children left
        if (isset($match[$n]) || isset($skip[$n])) continue;
        $hit = array_keys(array_filter($kids, fn($s, $id) => !isset($claimed[$id]) && sal_name_same((string)($s['name'] ?? ''), $name), ARRAY_FILTER_USE_BOTH));
        if (!$hit) {                                                     // pass 3: first name spelt a little differently (Suyam / Swayam), rest the same
            $rw = sal_name_words($name);
            $hit = array_keys(array_filter($kids, function ($s, $id) use ($claimed, $rw) {
                if (isset($claimed[$id])) return false; $kw = sal_name_words((string)($s['name'] ?? ''));
                if (!$kw || !$rw || levenshtein($kw[0], $rw[0]) > 2 || min(strlen($kw[0]), strlen($rw[0])) < 4) return false;
                $a = array_slice($kw, 1); $b = array_slice($rw, 1); return !array_diff($a, $b) || !array_diff($b, $a);
            }, ARRAY_FILTER_USE_BOTH));
        }
        if (count($hit) === 1) { $match[$n] = $hit[0]; $claimed[$hit[0]] = 1; } else $skip[$n] = "$n. $name (" . (count($hit) ? 'more than one close name' : 'not found in ERP LKG') . ')';
    }
    $half = fn($v) => $v === 'AB' || $v === '' ? $v : round($v / 2, 2) + 0;
    $filled = 0; $kept = 0; $kidsDone = [];
    foreach (SCH_LKG_HY_SUBJ as $i => $reg) {
        $key = "$ses|$exam|LKG|{$subj[$i]}"; $rec = sch_rec_get('prcMarks', $key) ?? ['subject' => $subj[$i], 'rows' => []];
        $rows = (array)($rec['rows'] ?? []); $changed = false;
        foreach (SCH_LKG_HY as [$n, $name, $m]) {
            if (!isset($match[$n])) continue; $sid = $match[$n];
            $old = (array)($rows[$sid] ?? []);
            if (array_filter($old, fn($x) => (string)$x !== '')) { $kept++; continue; }
            [$pt, $cp, $sea, $hf] = $m[$i];
            $rows[$sid] = ['pt' => $half($pt), 'nb' => $cp, 'sea' => $sea, 'te' => $hf]; $filled++; $changed = true; $kidsDone[$sid] = 1;
        }
        if ($changed) sch_rec_put('prcMarks', $key, ['subject' => $subj[$i], 'rows' => $rows, 'by' => 'System (LKG register, owner 19 Nov 2026)', 'at' => sch_now_ms()], 'system');
    }
    $checks = []; foreach (SCH_LKG_HY_CHECK as $k => $t) { [$n, $s] = explode('|', $k); $nm = ''; foreach (SCH_LKG_HY as $r) if ($r[0] === (int)$n) $nm = $r[1]; $checks[] = "$n. $nm $s — $t"; }
    sch_rec_put('salSeed', $flag, ['at' => sch_now_ms(), 'matched' => $match, 'skipped' => array_values($skip), 'filled' => $filled, 'kept' => $kept, 'subjects' => $subj], 'system');
    $sys = ['uid' => 'system', 'role' => 'system', 'label' => 'System'];          // log lines are cut at 1000 characters — one line per part
    sch_log($sys, 'Progress Report', 'LKG Half Yearly (Term-1) marks from the register: ' . count($kidsDone) . ' children, ' . $filled . ' subject entries filled (' . implode(', ', $subj) . ')' . ($kept ? "; $kept already entered — kept" : ''));
    foreach (array_chunk(array_values($skip), 8) as $part) sch_log($sys, 'Progress Report', 'LKG Half Yearly — NOT entered, fill by hand: ' . implode('; ', $part));
    sch_log($sys, 'Progress Report', 'LKG Half Yearly — please check against the register: ' . implode('; ', $checks));
}

// Every one-time change, in order (called from api.php load / since and salary_api.php sheet).
function sch_seeds(): void {
    foreach (['sch_seed_ritu_ukg', 'sch_seed_ankit_8th', 'sch_seed_lkg_hy'] as $f) { try { $f(); } catch (Throwable $e) { error_log("$f: " . $e->getMessage()); } }
}
