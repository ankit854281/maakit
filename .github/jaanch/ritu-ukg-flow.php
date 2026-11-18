<?php
// ============================================================
//  जाँच — one-time owner change (school/seed_lib.php sch_seed_ritu_ukg, 18 Nov 2026):
//  Ritu Ma'am back → UKG class teacher (old ID restored from leftTeachers), Rati Ma'am off UKG
//  but still on the staff, salary entry active again, runs once, logged, reaches phones via `since`.
// ============================================================
require_once __DIR__ . '/../../config.php';
if (DB_NAME !== 'maakit_jaanch') throw new RuntimeException('CI database only');
require_once __DIR__ . '/../../inc/db.php';
require_once __DIR__ . '/../../school/inc.php';
require_once __DIR__ . '/../../school/seed_lib.php';
function ru_check($ok, $m) { if (!$ok) throw new RuntimeException('Ritu UKG flow: ' . $m); }
foreach (['sch_sessions', 'sch_users', 'sch_records', 'sch_log', 'sch_login_fail'] as $t) $pdo->exec("DELETE FROM $t");
$put = fn($k, $r, $d) => sch_rec_put($k, $r, $d, 'principal');

// 1) classes not saved yet → nothing happens, tried again later
sch_seed_ritu_ukg(); ru_check(sch_rec_get('salSeed', 'ritu-ukg-2026-11') === null, 'waits for classes');

// 2) Ritu had left (old ID RI in leftTeachers), Rati is UKG class teacher
$put('settings', 'main', ['school' => 'T', 'leftTeachers' => ['RI' => 'Ritu Singh', 'XX' => 'Someone Else']]);
$put('teacher', 'RA', ['name' => "Rati Singh Ma'am", 'role' => 'English']);
$put('teacher', 'MO', ['name' => 'Monika Tiwari', 'role' => 'PG']);
$put('classes', 'main', [['c' => '10th', 'ct' => 'MO'], ['c' => 'UKG', 'ct' => 'RA'], ['c' => 'PG', 'ct' => 'MO']]);
$put('salStaff', 't:RI', ['monthly' => 5500, 'paidLeave' => 1, 'active' => false, 'until' => '2026-10-31']);
sch_seed_ritu_ukg();
$c = array_column(sch_rec_get('classes', 'main'), 'ct', 'c');
ru_check($c['UKG'] === 'RI' && $c['10th'] === 'MO' && $c['PG'] === 'MO', 'UKG → Ritu, other classes unchanged: ' . json_encode($c));
ru_check((sch_rec_get('teacher', 'RI')['name'] ?? '') === 'Ritu Singh', 'Ritu back under her old ID');
ru_check(!isset(sch_rec_get('settings', 'main')['leftTeachers']['RI']) && isset(sch_rec_get('settings', 'main')['leftTeachers']['XX']), 'removed from leftTeachers only');
ru_check(sch_rec_get('teacher', 'RA') !== null, 'Rati stays on the staff');
$ss = sch_rec_get('salStaff', 't:RI'); ru_check($ss['active'] === true && !isset($ss['until']) && $ss['monthly'] === 5500, 'salary active again, amount kept');
ru_check((int)$pdo->query("SELECT COUNT(*) FROM sch_log WHERE x LIKE '%UKG class teacher: Rati Singh Ma''am → Ritu Singh%'")->fetchColumn() === 1, 'logged');

// 3) runs once: a later manual change is never undone
$put('classes', 'main', [['c' => '10th', 'ct' => 'MO'], ['c' => 'UKG', 'ct' => 'RA'], ['c' => 'PG', 'ct' => 'MO']]);
sch_seed_ritu_ukg(); ru_check(array_column(sch_rec_get('classes', 'main'), 'ct', 'c')['UKG'] === 'RA', 'runs only once');

// 4) Ritu never on the staff → new teacher; already active → same ID
$pdo->exec("DELETE FROM sch_records");
$put('settings', 'main', ['school' => 'T']); $put('teacher', 'T1', ['name' => 'A B']); $put('classes', 'main', [['c' => 'UKG', 'ct' => 'T1']]);
sch_seed_ritu_ukg(); ru_check(array_column(sch_rec_get('classes', 'main'), 'ct', 'c')['UKG'] === 'T2' && sch_rec_get('teacher', 'T2')['name'] === "Ritu Singh Ma'am", 'new teacher made');
$pdo->exec("DELETE FROM sch_records");
$put('settings', 'main', []); $put('teacher', 'RI', ['name' => "Ritu Ma'am", 'role' => 'PG']); $put('classes', 'main', [['c' => 'UKG', 'ct' => 'RA']]);
sch_seed_ritu_ukg(); ru_check(array_column(sch_rec_get('classes', 'main'), 'ct', 'c')['UKG'] === 'RI' && sch_rec_get('teacher', 'RI')['role'] === 'PG', 'active Ritu used as she is');
echo "Ritu UKG flow: OK\n";
