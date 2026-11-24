<?php
// ============================================================================
//  Fee demand bill for parents — owner, 13 Nov 2026: "jo demand bill format banaya, manager aur chairman ke app me
//  add kar dijiye, jisse wo parents ko demand bill share kar paaye". Same bill as the PDF made on 12 Nov: child's
//  details, month-wise heads with Paid / Concession / Due kept apart, school fee and transport shown apart, receipts,
//  siblings (same mobile number) with a family total. Drawn on the phone by demandbill.js (share as a picture).
//
//   GET ?a=list                 every active child: name, class, father, mobile, due till this month (for search)
//   GET ?a=bill&sid=            one child's bill (+ siblings), session = the school's current session (+ pay: online pay link for the QR)
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
require __DIR__ . '/feepay_lib.php';

$a = (string)($_GET['a'] ?? 'list');
try { $db = sch_db(); } catch (Throwable $e) { sch_fail('Server is not reachable right now. Please try again in a few minutes.', 503); }
$u = sch_auth(); $role = (string)$u['role'];
if (!in_array($role, ['chairman', 'manager', 'principal', 'accountant', 'teacher'], true)) sch_fail('Demand bills are open for the Chairman, Manager, Principal, Accountant and teachers who call parents.', 403);
// Teachers (owner, 20 Nov 2026: "teachers ke app me bhi add kar dijiye kyoki vo call karte hain"): only ONE child's bill (a=bill) —
// the class teacher for a child of his / her class, the school's fee caller (settings.feeCallers, default Rati Ma'am) for any child.
$tid = (string)($u['tid'] ?? '');
$feeCaller = function () use ($tid): bool { $L = (sch_rec_get('settings', 'main') ?? [])['feeCallers'] ?? null; return is_array($L) ? in_array($tid, $L, true) : sch_default_fee_caller($tid); };
if ($role === 'teacher' && $a !== 'bill') sch_fail('Teachers open one child\'s demand bill from Fee calls.', 403);

const DB_MN = [4 => 'April', 5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August', 9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December', 1 => 'January', 2 => 'February', 3 => 'March'];
function db_ord(int $m): int { return ($m + 8) % 12; }
function db_tr(string $h): bool { return (bool)preg_match('/^(Transport|Hostel)$/i', $h); }
function db_phone(string $p): string { $p = preg_replace('/\D/', '', $p); return strlen($p) >= 10 ? substr($p, -10) : ''; }
// bills are "payable till this month" — or till the month chosen on the ERP Demand Bill page (?upto=1..12, 20 Nov 2026)
$DB_UPTO = (int)($_GET['upto'] ?? 0); if ($DB_UPTO < 1 || $DB_UPTO > 12) $DB_UPTO = (int)date('n');
function db_upto(): int { return db_ord($GLOBALS['DB_UPTO']); }

/** One child's bill: heads with paid / concession / due, totals, receipts. */
function db_bill(array $r): array {
    $NUM = array_flip(DB_MN); $P = [];
    foreach ($r['receipts'] as $x) {
        // A receipt's lines can be money AND discount: old ERP receipt 1-253 = Net 7800, Discount 7800, Paid 0 ("july me admison
        // hua tha", owner 20 Nov 2026: "vo jama nahi 7800 discount hai"). Only the money part counts as Paid, spread over its lines.
        $lt = array_sum(array_map(fn($l) => (float)$l['amt'], $x['lines'])); $share = $lt > 0 ? max(0, min(1, (float)$x['received'] / $lt)) : 0;
        foreach ($x['lines'] as $l) {
            $ms = array_values(array_filter(array_map(fn($m) => $NUM[trim($m)] ?? null, explode(',', (string)$l['months']))));
            if (!$ms) continue;
            foreach ($ms as $m) { $k = $l['head'] . '|' . $m; $P[$k] = ($P[$k] ?? 0) + $l['amt'] * $share / count($ms); }
        }
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

/** The child + brothers / sisters: same 10-digit father, mother or own mobile number. */
function db_family(array $me, array $rows): array {
    $ph = array_filter([db_phone($me['fatherMobile']), db_phone($me['phone']), db_phone($me['motherMobile'])]); $out = [$me];
    if ($ph) foreach ($rows as $r) if ($r['sid'] !== $me['sid'] && array_intersect($ph, array_filter([db_phone($r['fatherMobile']), db_phone($r['phone']), db_phone($r['motherMobile'])]))) $out[] = $r;
    return $out;
}
function db_cls_ord(string $c): int { $i = array_search($c, ['PG', 'LKG', 'UKG', '1st', '2nd', '3rd', '4th', '5th', '6th', '7th', '8th', '9th', '10th', '11th', '12th'], true); return $i === false ? 99 : $i; }
function db_head(array $set, string $ses, array $D): array {
    $m = $GLOBALS['DB_UPTO']; $y = (int)substr($ses, 0, 4) + ($m < 4 ? 1 : 0);
    return ['ok' => true, 'school' => (string)($set['school'] ?? 'SN Mishra Singapore International School'), 'ses' => $ses, 'upto' => DB_MN[$m], 'uptoYear' => (string)$y,
            'date' => date('d-m-Y'), 'live' => $D['live'], 'asOn' => $D['asOn']];
}

$set = sch_rec_get('settings', 'main') ?? []; $ses = ofc_ses((string)($set['session'] ?? ''));
if ($ses === '') sch_fail('The school session is not set (ERP → Settings).');
$D = fr_dues($db, $ses);
$rows = array_values(array_filter($D['rows'], fn($r) => !in_array($r['status'], ['TC', 'Deactive'], true)));
// Online payment QR on the bill (24 Nov 2026, owner: "demand bill me QR — pay karte he fee update, receipt bhi bankar chala jaye"):
// a signed pay.php link for the children on the bill, only when the school's Razorpay keys are set and the session is taken here.
$PAYON = fp_ready() && ofc_live($ses);
function db_pay(array $kids, string $ses): string { return $GLOBALS['PAYON'] ? fp_url(fp_token($ses, array_column($kids, 'sid'), $GLOBALS['DB_UPTO'])) : ''; }

try {
switch ($a) {
case 'list': {
    $out = [];
    foreach ($rows as $r) { $b = db_bill($r); $out[] = ['sid' => $r['sid'], 'name' => $r['name'], 'adm' => $r['adm'], 'cls' => $r['cls'], 'section' => (string)$r['section'], 'father' => $r['father'], 'mobile' => db_phone($r['fatherMobile'] ?: $r['phone']), 'due' => $b['total']['now']]; }
    usort($out, fn($x, $y) => $y['due'] <=> $x['due'] ?: strcmp($x['name'], $y['name']));
    sch_json(['ok' => true, 'ses' => $ses, 'upto' => DB_MN[(int)date('n')], 'rows' => $out]);
}
case 'bill': {
    // mode=single → only this child; default (sibling) → this child + brothers/sisters with the same mobile number
    $sid = (string)($_GET['sid'] ?? ''); $me = null; $single = ($_GET['mode'] ?? '') === 'single';
    foreach ($rows as $r) if ($r['sid'] === $sid) $me = $r;
    if (!$me) sch_fail('No fee card for this child in ' . $ses . '.');
    if ($role === 'teacher' && !$feeCaller()) { $ok = false; foreach ((array)sch_rec_get('classes', 'main') as $c) if (($c['c'] ?? '') === $me['cls'] && ($c['ct'] ?? '') === $tid) $ok = true;
        if (!$ok) sch_fail('Only the class teacher of class ' . $me['cls'] . ' (or the school\'s fee caller) can open this bill.', 403); }
    $kids = array_map('db_bill', $single ? [$me] : db_family($me, $rows));
    sch_log($u, 'Demand bill', 'Opened demand bill of ' . $me['name'] . ' (' . $me['cls'] . ')' . (count($kids) > 1 ? ' with ' . (count($kids) - 1) . ' sibling(s)' : ''));
    sch_json(db_head($set, $ses, $D) + ['kids' => $kids, 'pay' => db_pay($kids, $ses)]);
}
case 'bulk': {
    // ERP → Fee Report → Demand Bill (20 Nov 2026): every child of a class (or the whole school) at once, single or sibling-wise.
    // Sibling-wise: one bill per family — the family is printed once, under the first of its children met in the chosen class.
    $cls = (string)($_GET['cls'] ?? ''); $sec = (string)($_GET['section'] ?? ''); $single = ($_GET['mode'] ?? '') === 'single'; $dueOnly = ($_GET['due'] ?? '') === '1';
    $pick = array_values(array_filter($rows, fn($r) => ($cls === '' || $r['cls'] === $cls) && ($sec === '' || strcasecmp((string)$r['section'], $sec) === 0)));
    usort($pick, fn($x, $y) => db_cls_ord($x['cls']) <=> db_cls_ord($y['cls']) ?: strcmp((string)$x['section'], (string)$y['section']) ?: strcasecmp($x['name'], $y['name']));
    $done = []; $bills = [];
    foreach ($pick as $r) {
        if (isset($done[$r['sid']])) continue;
        $fam = $single ? [$r] : db_family($r, $rows); foreach ($fam as $f) $done[$f['sid']] = true;
        $kids = array_map('db_bill', $fam);
        if ($dueOnly && array_sum(array_map(fn($k) => $k['total']['now'], $kids)) <= 0) continue;
        $bills[] = $kids;
    }
    if (count($bills) > 400) sch_fail('Too many bills at once (' . count($bills) . '). Choose one class.');
    sch_log($u, 'Demand bill', 'Printed ' . count($bills) . ' demand bill(s) — ' . ($cls === '' ? 'all classes' : 'class ' . $cls . ($sec !== '' ? '-' . $sec : '')) . ($single ? ', single' : ', sibling-wise'));
    sch_json(db_head($set, $ses, $D) + ['bills' => $bills, 'pays' => array_map(fn($k) => db_pay($k, $ses), $bills)]);
}
default: sch_fail('Unknown action.');
}
} catch (Throwable $e) { error_log('demandbill_api: ' . $e->getMessage()); sch_fail('Something went wrong. Please try again.', 500); }
