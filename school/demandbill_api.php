<?php
// ============================================================================
//  Fee demand bill for parents — owner, 13 Nov 2026: "jo demand bill format banaya, manager aur chairman ke app me
//  add kar dijiye, jisse wo parents ko demand bill share kar paaye". Same bill as the PDF made on 12 Nov: child's
//  details, month-wise heads with Paid / Concession / Due kept apart, school fee and transport shown apart, receipts,
//  siblings (same mobile number) with a family total. Drawn on the phone by demandbill.js (share as a picture).
//
//   GET ?a=list                 every active child: name, class, father, mobile, due till this month (for search)
//   GET ?a=bill&sid=            one child's bill (+ siblings), session = the school's current session
//
//  Numbers come from fr_dues() (feereports_lib.php) — the Fee Receipt ledger once the session is started there,
//  otherwise the copied old ERP fee card, exactly like Fee Reports. Paid per head = the receipt lines of that head
//  spread over the months the receipt names; the rest of what the card shows as settled = concession.
//  Open for: Chairman, Manager, Principal, Accountant (read-only).
// ============================================================================
declare(strict_types=1);
require __DIR__ . '/inc.php';
require __DIR__ . '/registers_lib.php';
require __DIR__ . '/feecollect_lib.php';
require __DIR__ . '/feereports_lib.php';

$a = (string)($_GET['a'] ?? 'list');
try { $db = sch_db(); } catch (Throwable $e) { sch_fail('Server is not reachable right now. Please try again in a few minutes.', 503); }
$u = sch_auth(); $role = (string)$u['role'];
if (!in_array($role, ['chairman', 'manager', 'principal', 'accountant'], true)) sch_fail('Demand bills are open for the Chairman, Manager, Principal and Accountant.', 403);

const DB_MN = [4 => 'April', 5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August', 9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December', 1 => 'January', 2 => 'February', 3 => 'March'];
function db_ord(int $m): int { return ($m + 8) % 12; }
function db_tr(string $h): bool { return (bool)preg_match('/^(Transport|Hostel)$/i', $h); }
function db_phone(string $p): string { $p = preg_replace('/\D/', '', $p); return strlen($p) >= 10 ? substr($p, -10) : ''; }
function db_upto(): int { return db_ord((int)date('n')); }   // bills are "payable till this month"

/** One child's bill: heads with paid / concession / due, totals, receipts. */
function db_bill(array $r): array {
    $NUM = array_flip(DB_MN); $P = [];
    foreach ($r['receipts'] as $x) foreach ($x['lines'] as $l) {
        $ms = array_values(array_filter(array_map(fn($m) => $NUM[trim($m)] ?? null, explode(',', (string)$l['months']))));
        if (!$ms) continue;
        foreach ($ms as $m) { $k = $l['head'] . '|' . $m; $P[$k] = ($P[$k] ?? 0) + $l['amt'] / count($ms); }
    }
    $up = db_upto(); $heads = []; $t = ['fee' => 0, 'paid' => 0, 'conc' => 0, 'due' => 0, 'nowFee' => 0, 'nowTr' => 0];
    usort($r['heads'], fn($x, $y) => db_ord($x['m']) <=> db_ord($y['m']));
    foreach ($r['heads'] as $h) {
        $settled = (float)$h['paid']; $pd = min((float)($P[$h['name'] . '|' . $h['m']] ?? 0), $settled); $cn = $settled - $pd; $due = (float)$h['amt'] - $settled;
        $heads[] = ['m' => DB_MN[$h['m']] ?? (string)$h['m'], 'name' => str_replace(['Tution', 'Sefety'], ['Tuition', 'Safety'], $h['name']), 'tr' => db_tr($h['name']),
                    'amt' => round((float)$h['amt']), 'paid' => round($pd), 'conc' => round($cn), 'due' => round($due), 'now' => db_ord($h['m']) <= $up];
        $t['fee'] += $h['amt']; $t['paid'] += $pd; $t['conc'] += $cn; $t['due'] += $due;
        if (db_ord($h['m']) <= $up) { if (db_tr($h['name'])) $t['nowTr'] += $due; else $t['nowFee'] += $due; }
    }
    $oldLeft = max(0, $r['oldDue'] - $r['oldPaid']); $t['nowFee'] += $oldLeft; $t['due'] += $oldLeft;
    $cash = array_sum(array_column($r['receipts'], 'received'));
    $t = array_map(fn($v) => (int)round($v), $t); $t['now'] = $t['nowFee'] + $t['nowTr']; $t['received'] = (int)round($cash); $t['oldDue'] = (int)round($oldLeft);
    return ['sid' => $r['sid'], 'name' => $r['name'], 'adm' => $r['adm'], 'cls' => $r['cls'], 'section' => $r['section'], 'father' => $r['father'], 'mother' => $r['mother'],
            'mobile' => trim(implode(', ', array_filter([$r['fatherMobile'] ?: $r['phone'], $r['motherMobile']]))), 'address' => $r['address'], 'admAs' => $r['admAs'],
            'transport' => $r['faclty'] === 'Transport' ? (string)$r['route'] : 'No', 'heads' => $heads, 'total' => $t,
            'receipts' => array_map(fn($x) => ['no' => $x['no'], 'date' => $x['date'], 'amt' => round($x['received']), 'lines' => array_map(fn($l) => ['head' => str_replace(['Tution', 'Sefety'], ['Tuition', 'Safety'], $l['head']), 'months' => $l['months'], 'amt' => round($l['amt'])], $x['lines'])], $r['receipts'])];
}

$set = sch_rec_get('settings', 'main') ?? []; $ses = ofc_ses((string)($set['session'] ?? ''));
if ($ses === '') sch_fail('The school session is not set (ERP → Settings).');
$D = fr_dues($db, $ses);
$rows = array_values(array_filter($D['rows'], fn($r) => !in_array($r['status'], ['TC', 'Deactive'], true)));

try {
switch ($a) {
case 'list': {
    $out = [];
    foreach ($rows as $r) { $b = db_bill($r); $out[] = ['sid' => $r['sid'], 'name' => $r['name'], 'adm' => $r['adm'], 'cls' => $r['cls'], 'father' => $r['father'], 'mobile' => db_phone($r['fatherMobile'] ?: $r['phone']), 'due' => $b['total']['now']]; }
    usort($out, fn($x, $y) => $y['due'] <=> $x['due'] ?: strcmp($x['name'], $y['name']));
    sch_json(['ok' => true, 'ses' => $ses, 'upto' => DB_MN[(int)date('n')], 'rows' => $out]);
}
case 'bill': {
    $sid = (string)($_GET['sid'] ?? ''); $me = null;
    foreach ($rows as $r) if ($r['sid'] === $sid) $me = $r;
    if (!$me) sch_fail('No fee card for this child in ' . $ses . '.');
    $ph = array_filter([db_phone($me['fatherMobile']), db_phone($me['phone']), db_phone($me['motherMobile'])]);
    $kids = [db_bill($me)];
    if ($ph) foreach ($rows as $r) if ($r['sid'] !== $sid && array_intersect($ph, array_filter([db_phone($r['fatherMobile']), db_phone($r['phone']), db_phone($r['motherMobile'])]))) $kids[] = db_bill($r);
    sch_log($u, 'Demand bill', 'Opened demand bill of ' . $me['name'] . ' (' . $me['cls'] . ')' . (count($kids) > 1 ? ' with ' . (count($kids) - 1) . ' sibling(s)' : ''));
    sch_json(['ok' => true, 'school' => (string)($set['school'] ?? 'SN Mishra Singapore International School'), 'ses' => $ses, 'upto' => DB_MN[(int)date('n')], 'uptoYear' => date('Y'),
              'date' => date('d-m-Y'), 'live' => $D['live'], 'asOn' => $D['asOn'], 'kids' => $kids]);
}
default: sch_fail('Unknown action.');
}
} catch (Throwable $e) { error_log('demandbill_api: ' . $e->getMessage()); sch_fail('Something went wrong. Please try again.', 500); }
