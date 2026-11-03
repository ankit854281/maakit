<?php
// Synthetic CI-only Teacher App browser data. NEVER run this against a live database.
require __DIR__ . '/../../config.php';
if (DB_NAME !== 'maakit_jaanch') throw new RuntimeException('CI database only');
require_once __DIR__ . '/../../inc/db.php';
require_once __DIR__ . '/../../school/inc.php';
$put = fn($k, $r, $d) => sch_rec_put($k, $r, $d, 'principal');
$put('teacher', 'S', ['name' => 'Demo Teacher Shreya']);
$put('teacher', 'Q', ['name' => 'Demo Teacher Shyam']);
$grid = [];
foreach (['9th', '10th'] as $c) {
    $grid[$c] = array_fill(0, 6, array_fill(0, 7, -1));
    for ($day = 0; $day < 6; $day++) $grid[$c][$day][0] = $c === '9th' ? 0 : 1;
}
$put('grid', 'main', $grid);
for ($i = 3; $i <= 12; $i++) {
    $id = sprintf('ui9-%02d', $i);
    $put('student', $id, ['id' => $id, 'name' => sprintf('Demo Student %02d', $i), 'cls' => '9th',
        'adm' => (string)(100 + $i), 'phone' => sprintf('98765%05d', $i)]);
}
for ($i = 2; $i <= 6; $i++) {
    $id = sprintf('ui10-%02d', $i);
    $put('student', $id, ['id' => $id, 'name' => sprintf('Class Ten Demo %02d', $i), 'cls' => '10th',
        'adm' => (string)(200 + $i), 'phone' => sprintf('98766%05d', $i)]);
}
$put('syl', '9th|Social Science', ['chapters' => [
    ['n' => 'The French Revolution', 'done' => null, 'plan' => '2026-11', 'topics' => ['Background', '1789']],
    ['n' => 'Socialism in Europe', 'done' => null, 'plan' => '2026-12', 'topics' => ['Introduction']],
    ['n' => 'Nazism and the Rise of Hitler', 'done' => null, 'plan' => '2027-01', 'topics' => []]
], 'targetCount' => 3, 'targetDate' => '2027-02-28']);
$put('notice', 'ui-demo', ['id' => 'ui-demo', 'title' => 'CI classroom notice', 'body' => 'Class teacher sample notice', 'date' => '2026-11-03', 'to' => 'All']);
echo "CI Teacher UI seed: 12 students in 9th, 6 in 10th\n";
