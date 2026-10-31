<?php
// ============================================================================
//  Fee Receipt (old-ERP style fee collection) — owner decision, 24 Oct 2026
//
//  Ankit: the new ERP must take fees exactly like the old ERP's Fee › Fee
//  Receipt (schoolerpindia.net studentfee.php): same form, same "Upto Month"
//  selection, same Total Fee / Late Fee / Discount / Currently Received maths,
//  same checks, and the same receipt numbers continuing (1-382, 1-383 …).
//  "Data galat na ho, sahi data hona chahiye."
//
//  How the money stays in ONE place
//  - A session is switched on here by the Principal (start). From then on this
//    is the only place fees for that session are taken: office_api.php pay and
//    finance_api.php receive refuse that session (ofc_live()).
//  - Each child's fee card (month-wise heads, already paid) is copied once from
//    the Old ERP Fee Card archive (register oldfeecard). Nothing is invented:
//    a child with no card gets heads only when the Principal copies them from a
//    classmate's card (newLedger), with nothing marked paid.
//  - Receipts are never deleted. The Principal may cancel one with a reason;
//    the heads it paid go back to "Not Paid".
//  - Money is kept in paise (integers).
//
//  Kinds (prefix ofc, kept out of legacy sync in inc.php):
//    ofcCfg  'main'            {sessions:{'2026-27':{from,by,at,firstNo}}}
//    ofcLed  'ses|sid'         one child's heads for a session
//    ofcRcpt 'ses|no'          one receipt
//    ofcSeq  'ses'             {prefix:'1-', n}
//    ofcReq  nonce             idempotency for collect / cancel / addFee
//    ofcAdj  'ses|sid|stamp'   history of Add Other Fee / Old Due / head-wise discount (who, when, why)
//
//  Old due (owner's old ERP, Fee › Add Other Fee, Fee Type "Old Due"): the old
//  ERP refuses a receipt while the child has Old Fee ("Firstly Paid Old Due
//  Amount"). It is paid by adding it to the card as a head "Old Due" (April);
//  from then on it is collected like any other head. $L['oldMoved'] keeps how
//  much old due is already on the card, so it is never counted twice.
//  Head-wise discount is kept inside 'paid' like the old card's
//  "Paid/Disc Amount" column, and separately in 'disc'.
// ============================================================================
declare(strict_types=1);

const OFC_MONTHS = [4 => 'April', 5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August', 9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December', 1 => 'January', 2 => 'February', 3 => 'March'];
const OFC_FEE_TYPES = ['Old Due', 'Board Fee X', 'Book', 'Diary', 'Dress'];
const OFC_MODES = ['Cash', 'Cheque', 'Card', 'Wallet', 'NEFT', 'UPI', 'Net Banking', 'Online'];

function ofc_ses(string $s): string {
    $s = str_replace(['–', '—'], '-', trim($s));
    if (preg_match('/^(20\d\d)-(?:20)?(\d\d)$/', $s, $m)) return $m[1] . '-' . $m[2];
    return '';
}
function ofc_prev(string $ses): string { $y = (int)substr($ses, 0, 4) - 1; return $y . '-' . substr((string)($y + 1), 2); }
function ofc_cfg(): array { return sch_rec_get('ofcCfg', 'main') ?? ['sessions' => []]; }
function ofc_live(string $ses): ?array { $ses = ofc_ses($ses); return $ses === '' ? null : (ofc_cfg()['sessions'][$ses] ?? null); }

/** Rupee text from the old ERP ("1580", "1,580.00", "") -> paise. */
function ofc_paise($v): int {
    $s = str_replace([',', '₹', ' '], '', (string)$v);
    if ($s === '' || !preg_match('/^-?\d+(\.\d+)?$/', $s)) return 0;
    return (int)round(((float)$s) * 100);
}
/** Amount typed on the form -> paise; throws on anything that is not a plain amount. */
function ofc_amount($v, string $label, bool $allowZero = true): int {
    $s = str_replace([',', '₹', ' '], '', (string)$v);
    if ($s === '') $s = '0';
    if (!preg_match('/^\d{1,8}(\.\d{1,2})?$/', $s)) throw new InvalidArgumentException($label . ': enter an amount like 1500 or 1500.50.');
    $p = (int)round(((float)$s) * 100);
    if (!$allowZero && $p <= 0) throw new InvalidArgumentException($label . ' must be more than 0.');
    return $p;
}

/** Heads of an Old ERP fee card ("months" rows) -> [{i,m,name,amt,paid,na}] */
function ofc_heads_from_card(array $months): array {
    $heads = []; $m = 0; $names = array_flip(OFC_MONTHS);
    foreach (array_slice($months, 1) as $r) {
        if (!is_array($r)) continue;
        if (count($r) === 1) { $m = $names[trim((string)$r[0])] ?? 0; continue; }
        if ($m === 0 || trim((string)($r[2] ?? '')) === '') continue;
        $amt = ofc_paise($r[3] ?? ''); $paid = ofc_paise($r[4] ?? ''); $bal = ofc_paise($r[5] ?? '');
        $status = trim((string)($r[7] ?? ''));
        if ($paid + $bal !== $amt && $bal >= 0) $paid = $amt - $bal;          // the old ERP's balance column is the truth
        $heads[] = ['i' => count($heads) + 1, 'm' => $m, 'name' => trim((string)$r[2]), 'amt' => $amt, 'paid' => max(0, $paid), 'na' => $status === 'Not-Active'];
    }
    return $heads;
}
function ofc_totals(array $L): array {
    $net = 0; $paid = 0;
    foreach ($L['heads'] as $h) { if (!empty($h['na'])) continue; $net += $h['amt']; $paid += $h['paid']; }
    return ['net' => $net, 'received' => $paid, 'balance' => $net - $paid];
}
/** Old due for a session still to be put on the card: the session before (its unpaid balance plus its own old due not
 *  put on its card) if that ledger exists here, else the old ERP's "Old Fee"; less what is already added as "Old Due". */
function ofc_old_due(string $ses, string $sid, array $L, int $depth = 0): int {
    $ps = ofc_prev($ses); $p = $depth < 20 ? sch_rec_get('ofcLed', $ps . '|' . $sid) : null;
    $base = $p ? max(0, ofc_totals($p)['balance']) + ofc_old_due($ps, $sid, $p, $depth + 1) : max(0, (int)($L['oldFee'] ?? 0));
    return max(0, $base - (int)($L['oldMoved'] ?? 0));
}
function ofc_adj_log(string $ses, string $sid, array $e, string $uid): void {
    sch_rec_put('ofcAdj', $ses . '|' . $sid . '|' . sch_now_ms() . bin2hex(random_bytes(2)), $e + ['ses' => $ses, 'sid' => $sid, 'at' => sch_now_ms()], $uid);
}
function ofc_adj_list(string $ses, string $sid): array {
    $st = sch_db()->prepare("SELECT d FROM sch_records WHERE k='ofcAdj' AND r LIKE ? AND deleted=0"); $st->execute([$ses . '|' . $sid . '|%']); $out = [];
    foreach ($st as $row) { $r = json_decode((string)$row['d'], true); if (is_array($r)) $out[] = $r; }
    usort($out, fn($a, $b) => $a['at'] <=> $b['at']); return $out;
}

/** Old ERP fee cards of a session, keyed by new student id. */
function ofc_cards(string $ses): array {
    $out = [];
    $st = sch_db()->prepare("SELECT d FROM sch_records WHERE k='regRow' AND r LIKE 'oldfeecard|%' AND deleted=0");
    $st->execute();
    foreach ($st as $row) {
        $c = json_decode((string)$row['d'], true);
        if (!is_array($c) || ofc_ses((string)($c['session'] ?? '')) !== $ses) continue;
        $out[(string)($c['liveId'] ?? '') !== '' ? (string)$c['liveId'] : 'old:' . (string)($c['oldId'] ?? '')] = $c;
    }
    return $out;
}
function ofc_next_from_cards(array $cards): array {
    $best = 0; $prefix = '1-';
    foreach ($cards as $c) if (preg_match('/^(\d+-)(\d+)$/', (string)($c['nextReceipt'] ?? ''), $m) && (int)$m[2] > $best) { $best = (int)$m[2]; $prefix = $m[1]; }
    return [$prefix, $best];
}
function ofc_ledger_from_card(string $ses, string $sid, array $c, array $student): array {
    return ['session' => $ses, 'sid' => $sid, 'oldId' => (string)($c['oldId'] ?? ''), 'name' => (string)($student['name'] ?? $c['name'] ?? ''),
        'cls' => (string)($c['cls'] ?? ''), 'section' => (string)($c['section'] ?? ''), 'adm' => trim(explode('/', (string)($c['scholar'] ?? ''))[0]),
        'father' => (string)($c['father'] ?? ''), 'mother' => (string)($c['mother'] ?? ''), 'mobile' => (string)($c['mobile'] ?? ''), 'scheme' => (string)($c['scheme'] ?? ''),
        'oldFee' => ofc_paise($c['oldFee'] ?? ''), 'transferFee' => ofc_paise($c['transferFee'] ?? ''), 'heads' => ofc_heads_from_card((array)($c['months'] ?? [])),
        'from' => 'Old ERP fee card copied on ' . (string)($c['asOn'] ?? '')];
}

/** Receipt lines grouped like the old print: one line per head, months joined ("April,May"). */
function ofc_print_lines(array $alloc, array $heads): array {
    $by = []; $hi = array_column($heads, null, 'i');
    foreach ($alloc as $a) {
        $h = $hi[$a['i']]; $k = $h['name'];
        if (!isset($by[$k])) $by[$k] = ['name' => $k, 'months' => [], 'amt' => 0];
        if (!in_array(OFC_MONTHS[$h['m']], $by[$k]['months'], true)) $by[$k]['months'][] = OFC_MONTHS[$h['m']];
        $by[$k]['amt'] += $a['amt'];
    }
    $out = []; $n = 1;
    foreach ($by as $l) $out[] = [(string)$n++, $l['name'], implode(',', $l['months']), $l['amt']];
    return $out;
}

/**
 * Check and price one collection exactly like the old ERP's validateForm()/calcudis():
 *   Currently Received = Total Fee + Late Fee - Discount
 *   Total Fee may not be more than the balance; Old due must be cleared first;
 *   a mode other than Cash needs the deposit bank and deposit date.
 * $alloc = [{i, amt}] — which heads this receipt pays and how much of each.
 */
function ofc_price(array $L, array $alloc, int $late, int $discount, int $oldDue): array {
    if ($oldDue > 0) throw new InvalidArgumentException('Firstly Paid Old Due Amount (' . number_format($oldDue / 100, 2) . ').');
    $hi = array_column($L['heads'], null, 'i'); $seen = []; $total = 0;
    foreach ($alloc as $a) {
        $i = (int)($a['i'] ?? 0); $amt = (int)($a['amt'] ?? 0);
        if (!isset($hi[$i]) || isset($seen[$i])) throw new InvalidArgumentException('Select Month');
        $h = $hi[$i]; $bal = $h['amt'] - $h['paid'];
        if (!empty($h['na'])) throw new InvalidArgumentException($h['name'] . ' (' . OFC_MONTHS[$h['m']] . ') is not active for this child.');
        if ($amt <= 0 || $amt > $bal) throw new InvalidArgumentException('not received negative amount');
        $seen[$i] = true; $total += $amt;
    }
    if ($total <= 0) throw new InvalidArgumentException('Select Month');
    if ($total > ofc_totals($L)['balance']) throw new InvalidArgumentException('not received negative amount-2');
    if ($late < 0 || $discount < 0) throw new InvalidArgumentException('Please Check Amount');
    $received = $total + $late - $discount;
    if ($received < 0) throw new InvalidArgumentException('Please Check Amount');
    return ['total' => $total, 'late' => $late, 'discount' => $discount, 'received' => $received];
}
