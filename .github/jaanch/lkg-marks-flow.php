<?php
// जाँच — one-time LKG Half Yearly marks from the register (school/seed_lib.php sch_seed_lkg_hy, 19 Nov 2026).
require_once __DIR__ . '/../../config.php';
if (DB_NAME !== 'maakit_jaanch') throw new RuntimeException('CI database only');
require_once __DIR__ . '/../../inc/db.php';
require_once __DIR__ . '/../../school/inc.php';
require_once __DIR__ . '/../../school/seed_lib.php';
function lk_check($ok, $m) { if (!$ok) throw new RuntimeException('LKG marks flow: ' . $m); }
foreach (['sch_sessions', 'sch_users', 'sch_records', 'sch_log', 'sch_login_fail'] as $t) $pdo->exec("DELETE FROM $t");
$put = fn($k, $r, $d) => sch_rec_put($k, $r, $d, 'principal');
$put('settings', 'main', ['school' => 'T', 'session' => '2026-27']);
sch_seed_lkg_hy(); lk_check(sch_rec_get('salSeed', 'lkg-halfyearly-2026-27') === null, 'waits for LKG children');
$names = ['k1' => 'Abhinav Rai', 'k2' => "Ananya Pandey", 'k7' => 'Kritika', 'k29' => 'Kritika Rai', 'k14' => 'Swayam Tiwari', 'k20' => 'Diksha Kumari',
          'k15' => 'Shivansh Rai', 'k28' => 'Shivansh Rai', 'k16' => 'Shreyam Bind', 'k99' => 'Somebody Else', 'k6' => 'Divas Arya'];
foreach ($names as $id => $n) $put('student', $id, ['id' => $id, 'name' => $n, 'cls' => 'LKG']);
$put('student', 'x1', ['id' => 'x1', 'name' => 'Abhinav Rai', 'cls' => 'UKG']);   // other class, same name
$put('alloc', 'main', [['t' => 'A', 'c' => 'LKG', 's' => 'Hindi'], ['t' => 'A', 'c' => 'LKG', 's' => 'English'], ['t' => 'A', 'c' => 'LKG', 's' => 'Maths'], ['t' => 'A', 'c' => 'LKG', 's' => 'E.V.S.'], ['t' => 'A', 'c' => 'LKG', 's' => 'Art & Craft']]);
$put('prcMarks', '2026-27|Term-1|LKG|English', ['subject' => 'English', 'rows' => ['k2' => ['pt' => 9, 'nb' => 5, 'sea' => 5, 'te' => 70]]]);   // teacher already typed
sch_seeds();
$m = fn($s) => sch_rec_get('prcMarks', "2026-27|Term-1|LKG|$s")['rows'] ?? [];
lk_check($m('Hindi')['k1'] === ['pt' => 4, 'nb' => 5, 'sea' => 5, 'te' => 61], 'Abhinav Hindi, pre test halved: ' . json_encode($m('Hindi')['k1'] ?? null));
lk_check($m('Maths')['k1']['te'] === 'AB' && $m('E.V.S.')['k1']['te'] === 79 && $m('Art & Craft')['k1']['te'] === 65, 'subjects mapped to the timetable names (Maths, E.V.S., Art & Craft)');
lk_check($m('English')['k2'] === ['pt' => 9, 'nb' => 5, 'sea' => 5, 'te' => 70], 'marks already typed by a teacher kept');
lk_check($m('Hindi')['k2']['te'] === 67, 'other subjects of that child filled');
lk_check($m('Hindi')['k7']['te'] === 75 && $m('Hindi')['k29']['te'] === 72, 'Kritika and Kritika Rai kept apart');
lk_check($m('Hindi')['k14']['pt'] === 8 && $m('Hindi')['k20']['te'] === 75, 'close spelling (Suyam → Swayam) matched, 16 → 8');
lk_check(!isset($m('Hindi')['k15']) && !isset($m('Hindi')['k28']), 'two Shivansh Rai skipped');
lk_check(!isset($m('Hindi')['x1']) && !isset($m('Hindi')['k99']), 'other class / unknown child untouched');
lk_check($m('Hindi')['k16']['te'] === 'AB' && $m('Hindi')['k6']['pt'] === 4, 'AB kept, Divas Arya matched');
$log = implode(' | ', $pdo->query("SELECT x FROM sch_log WHERE x LIKE 'LKG Half Yearly%' ORDER BY id")->fetchAll(PDO::FETCH_COLUMN));
lk_check(str_contains($log, 'Shivansh Rai') && str_contains($log, 'NOT entered') && str_contains($log, 'already entered — kept') && str_contains($log, 'check against the register'), 'log explains: ' . $log);
$n = (int)$pdo->query("SELECT COUNT(*) FROM sch_log WHERE x LIKE 'LKG Half Yearly%'")->fetchColumn(); sch_seeds();
lk_check((int)$pdo->query("SELECT COUNT(*) FROM sch_log WHERE x LIKE 'LKG Half Yearly%'")->fetchColumn() === $n, 'runs once');
echo "LKG marks flow: OK\n";
