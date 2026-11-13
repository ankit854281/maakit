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
    ['rows' => $out, 'openBank' => $openBank] = fr_account_rows($db);   // feereports_lib.php (also the Manager's Cash Book, cashdesk_api.php)
    $set = sch_rec_get('settings', 'main') ?? [];
    sch_json(['ok' => true, 'rows' => $out, 'openBank' => $openBank, 'school' => (string)($set['school'] ?? ''), 'session' => (string)($set['session'] ?? '')]);
}
default: sch_fail('Unknown action.', 404);
}
} catch (Throwable $e) {
    error_log('feereports_api: ' . $e->getMessage());
    sch_fail('Could not open the report. Please try again.', 500);
}
