<?php
// ============================================================================
//  Fee Control Room (2 Nov 2026) — one page for the Chairman, Principal,
//  Accountant and Reception: what came in, what is still due, who to call,
//  what they promised. Read-only here; calls are saved by office_api.php?a=call
//  (the same Call Log the Dues Calling screen used), fees by Fee Receipt.
//
//   GET ?a=data&ses=2026-27
//     dues     — each child's fee card for the session (feereports_lib.php fr_dues)
//     receipts — every receipt of both ERPs, slim (not for Reception)
//     calls    — the fee call log (acctCall)
//
//  Reception sees dues and calls (it makes the calls) but not the money taken.
// ============================================================================
declare(strict_types=1);
require __DIR__ . '/inc.php';
require __DIR__ . '/registers_lib.php';
require __DIR__ . '/feecollect_lib.php';
require __DIR__ . '/feereports_lib.php';

$a = (string)($_GET['a'] ?? 'data');
try { $db = sch_db(); } catch (Throwable $e) { sch_fail('Server is not reachable right now. Please try again in a few minutes.', 503); }
$u = sch_auth(); $role = (string)$u['role'];
if (!in_array($role, ['principal', 'chairman', 'accountant', 'reception'], true)) sch_fail('Fee Control Room is open for the Principal, Chairman, Accountant and Reception.', 403);
$money = $role !== 'reception';

try {
switch ($a) {
case 'data': {
    $set = sch_rec_get('settings', 'main') ?? [];
    $cur = ofc_ses((string)($set['session'] ?? '')) ?: (date('n') >= 4 ? date('Y') . '-' . date('y', strtotime('+1 year')) : (date('Y') - 1) . '-' . date('y'));
    $ses = ofc_ses((string)($_GET['ses'] ?? '')) ?: $cur;
    $all = fr_receipts($db);
    $sessions = array_values(array_unique(array_merge([$cur], array_map(fn($r) => ofc_ses($r['session']), $all), array_keys(ofc_cfg()['sessions'] ?? []))));
    $sessions = array_values(array_filter($sessions)); rsort($sessions);
    $dues = fr_dues($db, $ses);
    foreach ($dues['rows'] as &$r) { unset($r['receipts']); } unset($r);   // the dashboard needs totals, not every line
    $rc = [];
    if ($money) foreach ($all as $r) $rc[] = ['session' => ofc_ses($r['session']), 'no' => $r['no'], 'date' => $r['date'], 'sid' => $r['sid'], 'name' => $r['name'], 'cls' => $r['cls'], 'section' => $r['section'],
        'received' => $r['received'], 'discount' => $r['discount'], 'late' => $r['late'], 'mode' => $r['mode'], 'status' => $r['status'], 'user' => $r['user'], 'src' => $r['src']];
    $calls = [];
    foreach ($db->query("SELECT d FROM sch_records WHERE k='acctCall' AND deleted=0") as $row) { $c = json_decode((string)$row['d'], true); if (is_array($c)) $calls[] = $c; }
    usort($calls, fn($x, $y) => ($y['at'] ?? 0) <=> ($x['at'] ?? 0));
    sch_json(['ok' => true, 'ses' => $ses, 'sessions' => $sessions, 'today' => date('Y-m-d'), 'role' => $role, 'money' => $money,
        'canCall' => in_array($role, ['principal', 'accountant', 'reception'], true), 'outcomes' => ['Promised to pay', 'Not answered', 'Switched off', 'Call back later', 'Will visit school', 'Already paid', 'Wrong number'],
        'school' => (string)($set['school'] ?? ''), 'live' => $dues['live'], 'asOn' => $dues['asOn'], 'dues' => $dues['rows'], 'receipts' => $rc, 'calls' => array_slice($calls, 0, 4000)]);
}
default: sch_fail('Unknown action.', 404);
}
} catch (Throwable $e) {
    error_log('feedash_api: ' . $e->getMessage());
    sch_fail('Could not open the Fee Control Room. Please try again.', 500);
}
