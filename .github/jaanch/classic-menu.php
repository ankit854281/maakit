<?php
// Old-ERP look (school/classic.js): every menu item must open a screen that exists.
// 'o:' → a register in reg_modules(), 'f:' → a Fee Department tab, 'v:' → a screen in app.js NAV,
// 'r:' → an old ERP page inside Old ERP Reports (the viewer must be in app.js NAV),
// 'm:' → a Student Master mode (add, list, search), 'q:' → a report in feereports.js REP.
require __DIR__ . '/../../school/registers_lib.php';
$js = file_get_contents(__DIR__ . '/../../school/classic.js');
$app = file_get_contents(__DIR__ . '/../../school/app.js');
$rep = file_get_contents(__DIR__ . '/../../school/feereports.js');
$old = file_get_contents(__DIR__ . '/../../school/oldlook.js');
preg_match_all("/\\['([^'\\]]+)', '([ovfmqa]:[a-z0-9]+|r:[a-z0-9_]+\\.php|)'\\]/", $js, $m, PREG_SET_ORDER);
if (count($m) < 380) throw new RuntimeException('Classic menu: expected the whole old-ERP menu, found ' . count($m) . ' items');
$mods = reg_modules(); $tabs = ['dashboard', 'students', 'billing', 'receipts', 'reports', 'follow', 'setup']; $bad = [];
foreach ($m as [, $label, $t]) {
    if ($t === '') continue;
    [$k, $v] = explode(':', $t);
    $ok = $k === 'a' ? (str_contains($app, "['attendance','fa-") && str_contains($old, "$v: '")) : ($k === 'q' ? (str_contains($app, "['feereports','fa-") && str_contains($rep, "\n  $v: {")) : ($k === 'm' ? (str_contains($app, "['stumaster','fa-") && in_array($v, ['add', 'list', 'search'], true)) : ($k === 'r' ? str_contains($app, "['oldreports','fa-") : ($k === 'o' ? isset($mods[$v]) : ($k === 'f' ? in_array($v, $tabs, true) : (bool)preg_match("/\\['" . preg_quote($v, '/') . "','fa-/", $app))))));
    if (!$ok) $bad[] = "$label → $t";
}
if ($bad) throw new RuntimeException('Classic menu points at screens that do not exist: ' . implode('; ', $bad));
$fin = file_get_contents(__DIR__ . '/../../school/finance.js');
foreach ($tabs as $t) if (!str_contains($fin, "'$t'")) throw new RuntimeException("Fee Department has no tab '$t'");
echo 'Classic menu: ' . count($m) . " old-ERP items, every target exists\n";
