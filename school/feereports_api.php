<?php
// ============================================================
// Old ERP Fee Reports API (see feereports_lib.php) — read-only
//   GET ?a=meta                       reports, sessions, classes, users, modes
//   GET ?a=report&k=&ses=&from=&to=&cls=&sec=&mode=&user=   (dues reports: &month=<upto month 1-12>&due=1)
// Principal, Chairman and Accountant (the old ERP's Fee Report menu).
// ============================================================
declare(strict_types=1);
require __DIR__ . '/inc.php';
require __DIR__ . '/registers_lib.php';
require __DIR__ . '/feecollect_lib.php';
require __DIR__ . '/feereports_lib.php';

$a = (string)($_GET['a'] ?? 'meta');
try { $db = sch_db(); } catch (Throwable $e) { sch_fail('Server is not reachable right now. Please try again in a few minutes.', 503); }
$u = sch_auth(); $role = (string)$u['role'];
if (!in_array($role, ['principal', 'chairman', 'accountant'], true)) sch_fail('Fee reports are not open for this login.', 403);
$q = fn($k, $re) => preg_match($re, $v = trim((string)($_GET[$k] ?? ''))) ? $v : '';
try {
    if ($a === 'meta') {
        $all = fr_receipts('');
        $sessions = array_values(array_unique(array_merge(array_column($all, 'session'), ['2026-27', '2025-26', '2024-25', '2023-24']))); rsort($sessions);
        $users = array_values(array_unique(array_filter(array_column($all, 'user')))); sort($users);
        $set = sch_rec_get('settings', 'main') ?? [];
        sch_json(['ok' => true, 'reports' => fr_reports(), 'sessions' => $sessions, 'session' => ofc_ses(reg_session()) ?: $sessions[0], 'users' => $users, 'modes' => FR_PMODES,
            'classes' => ['PG', 'LKG', 'UKG', '1st', '2nd', '3rd', '4th', '5th', '6th', '7th', '8th', '9th', '10th', '11th', '12th'],
            'school' => (string)($set['school'] ?? 'SN Mishra Singapore International School'), 'address' => 'Jagannathpur, Near Sikhdi Khandak, Bhadohi - 221403, UP',
            'today' => (new DateTimeImmutable('now', new DateTimeZone('Asia/Kolkata')))->format('Y-m-d')]);
    }
    if ($a === 'report') {
        $k = (string)($_GET['k'] ?? ''); if (!isset(fr_reports()[$k])) throw new InvalidArgumentException('Choose a report.');
        $ses = ofc_ses((string)($_GET['ses'] ?? ''));
        $f = ['from' => $q('from', '/^\d{4}-\d\d-\d\d$/'), 'to' => $q('to', '/^\d{4}-\d\d-\d\d$/'), 'cls' => $q('cls', '/^[\w ]{1,10}$/'), 'sec' => $q('sec', '/^[\w ]{1,10}$/'),
            'mode' => $q('mode', '/^[\w ]{1,20}$/'), 'user' => $q('user', '/^.{1,60}$/u'), 'month' => (int)$q('month', '/^\d{1,2}$/'), 'due' => $q('due', '/^1$/')];
        [$head, $body, $tot] = fr_build($k, $ses, $f);
        $sum = null;
        if (in_array($k, ['fee1', 'fee2'], true)) {
            // The old result page's summary under the table: payment mode, head and user totals.
            $rows = fr_filter(fr_receipts($ses), $f); $pm = []; $hd = []; $us = [];
            foreach ($rows as $r) { $pm[$r['modeText']] = ($pm[$r['modeText']] ?? 0) + $r['received']; $us[$r['user']] = ($us[$r['user']] ?? 0) + $r['received']; foreach ($r['lines'] as $l) $hd[$l['head']] = ($hd[$l['head']] ?? 0) + $l['amt']; }
            $disc = array_sum(array_column($rows, 'discount')); $late = array_sum(array_column($rows, 'late'));
            $sum = ['modes' => array_map(fn($m, $v) => [$m, fr_rs($v)], array_keys($pm), $pm), 'modeTotal' => fr_rs(array_sum($pm)), 'heads' => array_map(fn($h, $v) => [$h, fr_rs($v)], array_keys($hd), $hd),
                'headTotal' => fr_rs(array_sum($hd)), 'discount' => fr_rs($disc), 'late' => fr_rs($late), 'net' => fr_rs(array_sum($hd) - $disc + $late), 'users' => array_map(fn($x, $v) => [$x, fr_rs($v)], array_keys($us), $us)];
        }
        sch_json(['ok' => true, 'title' => fr_reports()[$k][0], 'head' => $head, 'rows' => $body, 'total' => $tot, 'summary' => $sum, 'count' => count($body)]);
    }
    sch_fail('Unknown action.', 404);
} catch (InvalidArgumentException $e) { sch_fail($e->getMessage()); }
catch (Throwable $e) { error_log('feereports_api: ' . $e->getMessage()); sch_fail('Could not make this report.', 500); }
