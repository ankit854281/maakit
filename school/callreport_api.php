<?php
// ============================================================================
//  Fee Calling Report (owner, 25 Nov 2026: "fee calling report date or remarks mai uska use nahi le paa raha").
//  Every saved fee call (acctCall — ERP office, Chairman app, Teacher App) for a date range, with the child,
//  result, the date and amount the family gave, the remark and who called — plus whether the family has paid
//  since the call (new-ERP receipts, ofcRcpt). Read-only. Page: callreport.php / callreport.js.
//   GET ?a=data&from=YYYY-MM-DD&to=YYYY-MM-DD[&by=promise]
//       by=promise (owner, 27 Nov 2026: "aaj 27 hai to 27 ko kitne jan ne bola hai payment karne ko"): the range is the date
//       the FAMILY GAVE to pay. Only each family's latest call counts (a family that first said 27 and later said 30 is
//       counted on 30 only), so the list is who should pay on those days — calls made on any day.
//   GET ?a=status (owner, 27 Nov 2026: "kaise record track hoga — kitne jan ne date de diya hai aur kitne jan ka fee baaki hai but
//       baat nahi hua hai"): every FAMILY (same 10-digit mobile) with fee due today (ff_snapshot, same numbers as the calling
//       lists) and its latest fee call this session, sorted into: date given — still ahead · date passed and nothing paid since ·
//       paid something after the call · talked, no date (call back / will visit) · not reached (not answered / switched off /
//       wrong number) · never called. Company / FD families are counted apart (settled through dealings / fixed plan).
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
if ($a === 'status') {
    require_once __DIR__ . '/followup_lib.php';
    $session = ff_session(); $snap = ff_snapshot($db, $session); $today = date('Y-m-d');
    $fam = []; $tagged = ['n' => 0, 'due' => 0];
    foreach ($snap as $sid => $x) {
        if ((int)$x['due'] <= 0) continue;
        if (in_array((string)$x['tag'], ['Company', 'FD'], true)) { $tagged['n']++; $tagged['due'] += (int)$x['due']; continue; }
        $k = $x['phone'] !== '' ? $x['phone'] : 'sid:' . $sid;
        $fam[$k] ??= ['key' => $k, 'phone' => $x['phone'], 'father' => (string)$x['father'], 'due' => 0, 'kids' => [], 'receipts' => []];
        $fam[$k]['due'] += (int)$x['due']; $fam[$k]['kids'][] = ['sid' => (string)$sid, 'name' => (string)$x['name'], 'cls' => (string)$x['cls'], 'section' => (string)$x['section']];
        foreach ($x['receipts'] as $r) $fam[$k]['receipts'][] = [(string)$r['date'], (int)round(((float)$r['received']) * 100)];
    }
    $bySid = []; foreach ($fam as $k => $f) foreach ($f['kids'] as $c) $bySid[$c['sid']] = $k;
    $last = [];
    foreach (ff_rows('acctCall') as $c) {
        if (($c['session'] ?? $session) !== $session || !empty($c['initiated'])) continue;
        $at = (int)($c['completedAt'] ?? $c['at'] ?? 0);
        foreach ((array)($c['sids'] ?? [$c['sid'] ?? '']) as $sid) { $k = $bySid[(string)$sid] ?? null; if ($k === null) continue;
            if (!isset($last[$k]) || $at > $last[$k]['at']) $last[$k] = ['at' => $at, 'date' => (string)($c['completedDate'] ?? $c['date'] ?? ($at ? date('Y-m-d', intdiv($at, 1000)) : '')),
                'outcome' => (string)($c['outcome'] ?? ''), 'promise' => (string)($c['promise'] ?? ''), 'amount' => (int)($c['amount'] ?? 0), 'by' => (string)($c['by'] ?? ''), 'note' => (string)($c['note'] ?? '')]; }
    }
    $out = [];
    foreach ($fam as $k => $f) {
        $l = $last[$k] ?? null; $paid = 0;
        if ($l) foreach ($f['receipts'] as [$d, $p]) if ($d >= $l['date']) $paid += $p;
        $st = !$l ? 'never'
            : (($paid > 0 || $l['outcome'] === 'Already paid') ? 'paid'
            : ($l['outcome'] === 'Promised to pay' && $l['promise'] !== '' ? ($l['promise'] >= $today ? 'date' : 'missed')
            : (in_array($l['outcome'], ['Not answered', 'Switched off', 'Wrong number'], true) ? 'noreach' : 'talked')));
        unset($f['receipts']); $out[] = $f + ['last' => $l, 'paid' => $paid, 'st' => $st];
    }
    usort($out, fn($x, $y) => $y['due'] <=> $x['due']);
    sch_json(['ok' => true, 'session' => $session, 'today' => $today, 'families' => $out, 'tagged' => $tagged,
        'school' => (string)((sch_rec_get('settings', 'main') ?? [])['school'] ?? 'SN Mishra Singapore International School')]);
}
if ($a !== 'data') sch_fail('Unknown request.', 404);
$re = '/^\d{4}-\d{2}-\d{2}$/';
$byPromise = ($_GET['by'] ?? '') === 'promise';
$to = (string)($_GET['to'] ?? date('Y-m-d')); if (!preg_match($re, $to)) $to = date('Y-m-d');
$from = (string)($_GET['from'] ?? date('Y-m-d', strtotime('-6 days'))); if (!preg_match($re, $from)) $from = date('Y-m-d', strtotime('-6 days'));
if ($from > $to) [$from, $to] = [$to, $from];
if (!$byPromise && $to > date('Y-m-d')) $to = date('Y-m-d');
if (strtotime($to) - strtotime($from) > 400 * 86400) sch_fail('Choose up to 400 days at a time.');

$calls = [];
foreach ($db->query("SELECT d FROM sch_records WHERE k='acctCall' AND deleted=0") as $row) {
    $c = json_decode((string)$row['d'], true); if (!is_array($c)) continue;
    $d = (string)($c['completedDate'] ?? $c['date'] ?? ''); if ($d === '' && !empty($c['at'])) $d = date('Y-m-d', (int)($c['at'] / 1000));
    if (!$byPromise && ($d < $from || $d > $to)) continue;
    $calls[] = ['id' => (string)($c['id'] ?? ''), 'date' => $d, 'at' => (int)($c['completedAt'] ?? $c['at'] ?? 0), 'sid' => (string)($c['sid'] ?? ''),
        'sids' => array_values(array_map('strval', (array)($c['sids'] ?? [$c['sid'] ?? '']))), 'outcome' => (string)($c['outcome'] ?? ''),
        'promise' => (string)($c['promise'] ?? ''), 'amount' => (int)($c['amount'] ?? 0), 'note' => (string)($c['note'] ?? ''),
        'with' => (string)($c['with'] ?? ''), 'by' => (string)($c['by'] ?? ''), 'tag' => (string)($c['tag'] ?? '')];
}
usort($calls, fn($x, $y) => $y['at'] <=> $x['at']);
$paidFrom = $from;
if ($byPromise) {   // latest call of each family, kept when the date it gave falls in the range
    $seen = []; $keep = [];
    foreach ($calls as $c) { $fk = implode(',', (function ($a) { sort($a); return $a; })($c['sids'])); if (isset($seen[$fk])) continue; $seen[$fk] = true;
        if ($c['promise'] !== '' && $c['promise'] >= $from && $c['promise'] <= $to) $keep[] = $c; }
    $calls = $keep; foreach ($calls as $c) if ($c['date'] < $paidFrom) $paidFrom = $c['date'];
}

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
    $d = (string)($r['date'] ?? ''); if ($d < $paidFrom) continue;
    $paid[$sid][] = [$d, (int)($r['received'] ?? 0), (string)($r['no'] ?? '')];
}
sch_json(['ok' => true, 'by' => $byPromise ? 'promise' : 'call', 'from' => $from, 'to' => $to, 'today' => date('Y-m-d'), 'calls' => $calls, 'students' => (object)$students, 'paid' => (object)$paid,
    'school' => (string)((sch_rec_get('settings', 'main') ?? [])['school'] ?? 'SN Mishra Singapore International School'), 'me' => (string)($u['label'] ?? '')]);
