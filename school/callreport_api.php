<?php
// ============================================================================
//  Fee Calling Report (owner, 25 Nov 2026: "fee calling report date or remarks mai uska use nahi le paa raha").
//  Every saved fee call (acctCall — ERP office, Chairman app, Teacher App) for a date range, with the child,
//  result, the date and amount the family gave, the remark and who called — plus whether the family has paid
//  since the call (new-ERP receipts, ofcRcpt). Read-only. Page: callreport.php / callreport.js.
//   GET ?a=data&from=YYYY-MM-DD&to=YYYY-MM-DD
//  Open for: Principal, Chairman, Manager, Accountant, Reception and the Teacher App fee callers (Rati / Palak Ma'am).
// ============================================================================
declare(strict_types=1);
require __DIR__ . '/inc.php';

try { $db = sch_db(); } catch (Throwable $e) { sch_fail('Server is not reachable right now. Please try again in a few minutes.', 503); }
$u = sch_auth(); $role = (string)$u['role'];
$ok = in_array($role, ['principal', 'chairman', 'manager', 'accountant', 'reception'], true);
if (!$ok && $role === 'teacher') {
    $tid = (string)($u['tid'] ?? ''); $L = (sch_rec_get('settings', 'main') ?? [])['feeCallers'] ?? null;
    $ok = is_array($L) ? in_array($tid, $L, true) : sch_default_fee_caller($tid);
}
if (!$ok) sch_fail('The Fee Calling Report is open for the Principal, Chairman, Manager, office and the fee callers.', 403);

$a = (string)($_GET['a'] ?? 'data');
if ($a !== 'data') sch_fail('Unknown request.', 404);
$re = '/^\d{4}-\d{2}-\d{2}$/';
$to = (string)($_GET['to'] ?? date('Y-m-d')); if (!preg_match($re, $to)) $to = date('Y-m-d');
$from = (string)($_GET['from'] ?? date('Y-m-d', strtotime('-6 days'))); if (!preg_match($re, $from)) $from = date('Y-m-d', strtotime('-6 days'));
if ($from > $to) [$from, $to] = [$to, $from];
if (strtotime($to) - strtotime($from) > 400 * 86400) sch_fail('Choose up to 400 days at a time.');

$calls = [];
foreach ($db->query("SELECT d FROM sch_records WHERE k='acctCall' AND deleted=0") as $row) {
    $c = json_decode((string)$row['d'], true); if (!is_array($c)) continue;
    $d = (string)($c['completedDate'] ?? $c['date'] ?? ''); if ($d === '' && !empty($c['at'])) $d = date('Y-m-d', (int)($c['at'] / 1000));
    if ($d < $from || $d > $to) continue;
    $calls[] = ['id' => (string)($c['id'] ?? ''), 'date' => $d, 'at' => (int)($c['completedAt'] ?? $c['at'] ?? 0), 'sid' => (string)($c['sid'] ?? ''),
        'sids' => array_values(array_map('strval', (array)($c['sids'] ?? [$c['sid'] ?? '']))), 'outcome' => (string)($c['outcome'] ?? ''),
        'promise' => (string)($c['promise'] ?? ''), 'amount' => (int)($c['amount'] ?? 0), 'note' => (string)($c['note'] ?? ''),
        'with' => (string)($c['with'] ?? ''), 'by' => (string)($c['by'] ?? ''), 'tag' => (string)($c['tag'] ?? '')];
}
usort($calls, fn($x, $y) => $y['at'] <=> $x['at']);

$need = []; foreach ($calls as $c) foreach ($c['sids'] as $s) $need[$s] = true;
$students = [];
if ($need) foreach ($db->query("SELECT r, d FROM sch_records WHERE k='student' AND deleted=0") as $row) {
    $sid = (string)$row['r']; if (!isset($need[$sid])) continue;
    $s = json_decode((string)$row['d'], true); if (!is_array($s)) continue;
    $students[$sid] = ['name' => (string)($s['name'] ?? ''), 'cls' => (string)($s['cls'] ?? ''), 'section' => (string)($s['section'] ?? ''),
        'father' => (string)($s['father'] ?? ''), 'phone' => sch_phone10((string)($s['phone'] ?? ''))];
}
// New-ERP receipts of these children from the first call date on: [sid => [[date, paise, no], ...]]
$paid = [];
if ($need) foreach ($db->query("SELECT d FROM sch_records WHERE k='ofcRcpt' AND deleted=0") as $row) {
    $r = json_decode((string)$row['d'], true); if (!is_array($r)) continue;
    $sid = (string)($r['sid'] ?? ''); if (!isset($need[$sid]) || ($r['status'] ?? '') === 'Cancelled') continue;
    $d = (string)($r['date'] ?? ''); if ($d < $from) continue;
    $paid[$sid][] = [$d, (int)($r['received'] ?? 0), (string)($r['no'] ?? '')];
}
sch_json(['ok' => true, 'from' => $from, 'to' => $to, 'today' => date('Y-m-d'), 'calls' => $calls, 'students' => (object)$students, 'paid' => (object)$paid,
    'school' => (string)((sch_rec_get('settings', 'main') ?? [])['school'] ?? 'SN Mishra Singapore International School'), 'me' => (string)($u['label'] ?? '')]);
