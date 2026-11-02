<?php
// ============================================================================
//  Fee Reports in the old ERP's own layout (27 Oct 2026) — batch 2 of "bilkul
//  difference na feel ho". Read-only. One list of receipts, whichever ERP took
//  them:
//    old ERP receipts — School Office registers oldreceipt (summary) +
//                       oldrcptdet (every head line as printed)
//    new receipts     — Fee Receipt (ofcRcpt), once a session is started there
//  Amounts are rupees here (old ERP registers keep paise for the summary and
//  the printed rupee text for the lines; ofcRcpt keeps paise).
//
//   GET ?a=receipts           every receipt, every session (no deleted ones)
//   GET ?a=accounts           cash book entries (fee receipts + old ERP vouchers + School Office Cash Book)
//   GET ?a=dues&ses=          each child's heads, old due and receipts for the dues reports
// ============================================================================
declare(strict_types=1);
require __DIR__ . '/inc.php';
require __DIR__ . '/registers_lib.php';
require __DIR__ . '/feecollect_lib.php';
require __DIR__ . '/feereports_lib.php';

$a = (string)($_GET['a'] ?? 'receipts');
try { $db = sch_db(); } catch (Throwable $e) { sch_fail('Server is not reachable right now. Please try again in a few minutes.', 503); }
$u = sch_auth(); $role = (string)$u['role'];
if (!in_array($role, ['principal', 'chairman', 'accountant'], true)) sch_fail('Fee reports are open for the Principal, Chairman and Accountant.', 403);

try {
switch ($a) {
case 'receipts': {
    $set = sch_rec_get('settings', 'main') ?? [];
    sch_json(['ok' => true, 'receipts' => fr_receipts($db), 'school' => (string)($set['school'] ?? ''), 'session' => (string)($set['session'] ?? '')]);
}
case 'dues': {
    $ses = ofc_ses((string)($_GET['ses'] ?? '')); if ($ses === '') sch_fail('Choose the session.');
    sch_json(['ok' => true] + fr_dues($db, $ses));
}
case 'accounts': {
    // Cash book / bank / payments in the old ERP's way: every receipt goes into CASH (online ones are later "deposited"
    // to the bank by a BD voucher), payments come out of cash or bank, a bank deposit moves cash to the bank.
    //   fee receipts — every receipt of both ERPs (fr_receipts)
    //   old vouchers — the old ERP's own Cashbook as copied (Old ERP Reports): RV / OFREC receipts, JV / SV payments,
    //                  BD deposits (fee lines there are skipped, they come from the receipts above)
    //   new vouchers — School Office › Cash Book register entries
    if ($role === 'accountant' || in_array($role, ['principal', 'chairman'], true)) {} else sch_fail('Not allowed.', 403);
    $out = [];
    foreach (fr_receipts($db) as $r) {
        if ($r['status'] === 'Cancelled') continue;
        $out[] = ['src' => 'fee', 'date' => $r['date'], 'session' => $r['session'], 'vno' => $r['no'], 'name' => $r['name'], 'adm' => $r['adm'], 'cls' => $r['cls'], 'remark' => $r['remark'],
            'head' => 'Fee', 'sub' => 'Fee', 'kind' => 'Fee', 'drCash' => $r['received'], 'drBank' => 0, 'crCash' => 0, 'crBank' => 0, 'mode' => $r['mode'], 'pmode' => $r['mode'] . ($r['mode'] !== 'Cash' ? ' ' . ($r['custBank'] ?: 'NA') : ''), 'user' => $r['user'], 'bank' => '', 'cheque' => $r['cheque']];
    }
    $page = function (string $name) use ($db): ?array {
        $st = $db->prepare("SELECT d FROM sch_records WHERE k='regPage' AND r LIKE ? AND deleted=0 ORDER BY r DESC LIMIT 1"); $st->execute(['%|' . $name]);
        $pg = json_decode((string)$st->fetchColumn(), true); $bin = base64_decode((string)($pg['h'] ?? ''), true);
        $t = $bin === false ? null : json_decode((string)@gzdecode($bin), true); return is_array($t) ? $t : null;
    };
    $heads = [];   // JV voucher -> "HEAD->Sub" from the old Income & Expenditure Report
    if ($t = $page('report_incexpregrpt.php')) { $H = array_flip($t[0]); foreach (array_slice($t, 1) as $x) if (isset($H['Voucher No'], $x[$H['Voucher No']])) $heads[(string)$x[$H['Voucher No']]] = (string)$x[$H['Particular']]; }
    $openBank = 0.0;
    if ($t = $page('report_bankstatement.php')) { $H = array_flip($t[0]); $openBank = fr_rs($t[1][$H['Opening Balance']] ?? 0); }
    if ($t = $page('report_cashbook.php')) {
        foreach (array_slice($t, 2) as $x) {
            if (count($x) < 14 || !preg_match('/^[A-Z]/', (string)$x[3])) continue;
            $vno = (string)$x[3]; $kind = explode('-', $vno)[0]; $hs = explode('->', $heads[$vno] ?? '');
            $out[] = ['src' => 'old', 'date' => fr_date((string)$x[1]), 'session' => (string)$x[2], 'vno' => $vno, 'name' => (string)$x[4], 'adm' => (string)$x[5], 'cls' => (string)$x[6], 'remark' => (string)$x[7],
                'head' => count($hs) > 1 ? $hs[0] : (string)$x[8], 'sub' => (string)$x[8], 'kind' => $kind, 'drCash' => fr_rs($x[9]), 'drBank' => fr_rs($x[10]), 'crCash' => fr_rs($x[11]), 'crBank' => fr_rs($x[12]),
                'mode' => explode(' ', (string)$x[13])[0], 'pmode' => (string)$x[13], 'user' => (string)$x[14], 'bank' => '', 'cheque' => ''];
        }
    }
    foreach (reg_rows('cashbook') as $r) {
        $amt = ((int)($r['amount'] ?? 0)) / 100; $side = (string)$r['side']; $cash = ($r['mode'] ?? 'Cash') === 'Cash' || ($r['mode'] ?? '') === '';
        $e = ['drCash' => 0, 'drBank' => 0, 'crCash' => 0, 'crBank' => 0];
        if (str_starts_with($side, 'Receipt')) $e['drCash'] = $amt; elseif (str_starts_with($side, 'Payment')) $e[$cash ? 'crCash' : 'crBank'] = $amt;
        elseif (str_starts_with($side, 'Bank')) { $e['crCash'] = $amt; $e['drBank'] = $amt; } elseif (str_starts_with($side, 'Self')) { $e['drCash'] = $amt; $e['crBank'] = $amt; }
        $out[] = ['src' => 'new', 'date' => (string)$r['date'], 'session' => '', 'vno' => (string)($r['no'] ?? ''), 'name' => (string)($r['party'] ?? ''), 'adm' => '', 'cls' => '', 'remark' => (string)($r['narration'] ?? ''),
            'head' => (string)$r['head'], 'sub' => (string)$r['head'], 'kind' => str_starts_with($side, 'Receipt') ? 'RV' : (str_starts_with($side, 'Payment') ? 'JV' : (str_starts_with($side, 'Bank') ? 'BD' : 'SW')),
            'mode' => (string)($r['mode'] ?? ''), 'pmode' => trim((string)($r['mode'] ?? '') . ' ' . (string)($r['ref'] ?? '')), 'user' => (string)($r['_by'] ?? ''), 'bank' => '', 'cheque' => (string)($r['ref'] ?? '')] + $e;
    }
    usort($out, fn($x, $y) => [$x['date'], $x['src'] === 'fee' ? 0 : 1, $x['vno']] <=> [$y['date'], $y['src'] === 'fee' ? 0 : 1, $y['vno']]);
    $set = sch_rec_get('settings', 'main') ?? [];
    sch_json(['ok' => true, 'rows' => $out, 'openBank' => $openBank, 'school' => (string)($set['school'] ?? ''), 'session' => (string)($set['session'] ?? '')]);
}
default: sch_fail('Unknown action.', 404);
}
} catch (Throwable $e) {
    error_log('feereports_api: ' . $e->getMessage());
    sch_fail('Could not open the report. Please try again.', 500);
}
