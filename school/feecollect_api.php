<?php
// ============================================================
// Fee Receipt API (old-ERP style fee collection, see feecollect_lib.php)
//
//   GET  ?a=state&ses=2026-27          sessions, students, who may do what, next receipt no
//   GET  ?a=ledger&ses=&sid=           one child: heads, totals, old due, receipts, old card
//   POST ?a=collect {ses,sid,alloc[{i,amt}],late,discount,mode,…,nonce}   Accountant / Principal
//   GET  ?a=receipt&ses=&no=           one receipt to print (new or old ERP copy)
//   POST ?a=cancel  {ses,no,reason,nonce}                                  Principal
//   POST ?a=start   {ses,apply,confirm:'START'}                            Principal
//   POST ?a=newLedger {ses,sid,from}                                       Principal
// ============================================================
declare(strict_types=1);
require __DIR__ . '/inc.php';
require __DIR__ . '/registers_lib.php';
require __DIR__ . '/feecollect_lib.php';

$a = (string)($_GET['a'] ?? 'state');
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
try { $db = sch_db(); } catch (Throwable $e) { sch_fail('Server is not reachable right now. Please try again in a few minutes.', 503); }
$u = sch_auth();
$role = (string)$u['role'];
$uid = sch_uid_of($u);
if (!in_array($role, ['principal', 'chairman', 'accountant', 'reception'], true)) sch_fail('Fee Receipt is not open for this login.', 403);
$canCollect = in_array($role, ['principal', 'accountant'], true);
$isP = $role === 'principal';
$in = [];
if ($method === 'POST') { $in = json_decode((string)file_get_contents('php://input', false, null, 0, 200000), true); if (!is_array($in)) sch_fail('Bad request.'); }
$ses = ofc_ses((string)($in['ses'] ?? $_GET['ses'] ?? ''));
$banks = (sch_rec_get('settings', 'main')['ofcBanks'] ?? null) ?: [['name' => 'UBI-…8120', 'modes' => ['Cash', 'NEFT', 'UPI']], ['name' => 'HDFC BANK-3039', 'modes' => ['Cash', 'Cheque', 'UPI', 'Online']]];
$lock = function () use ($db) { $db->beginTransaction(); $db->query("SELECT uid FROM sch_users WHERE uid='principal' FOR UPDATE")->fetch(); };
$rcpts = function (string $ses, string $sid = '') use ($db): array {
    $st = $db->prepare("SELECT d FROM sch_records WHERE k='ofcRcpt' AND r LIKE ? AND deleted=0"); $st->execute([$ses . '|%']); $out = [];
    foreach ($st as $row) { $r = json_decode((string)$row['d'], true); if (is_array($r) && ($sid === '' || $r['sid'] === $sid)) $out[] = $r; }
    usort($out, fn($x, $y) => [$x['date'], $x['n']] <=> [$y['date'], $y['n']]); return $out;
};

try {
switch ($a) {
case 'state': {
    $cfg = ofc_cfg(); $students = reg_students();
    $sessions = array_values(array_unique(array_merge(array_keys($cfg['sessions']), [ofc_ses(reg_session())], ['2026-27', '2025-26', '2024-25', '2023-24'])));
    $sessions = array_values(array_filter($sessions)); rsort($sessions);
    if ($ses === '') $ses = ofc_ses(reg_session()) ?: $sessions[0];
    $led = [];
    $st = $db->prepare("SELECT r FROM sch_records WHERE k='ofcLed' AND r LIKE ? AND deleted=0"); $st->execute([$ses . '|%']);
    foreach ($st as $row) $led[substr((string)$row['r'], strlen($ses) + 1)] = true;
    $seq = sch_rec_get('ofcSeq', $ses);
    $list = [];
    foreach ($students as $id => $s) $list[] = ['id' => $id, 'name' => (string)($s['name'] ?? ''), 'cls' => (string)($s['cls'] ?? ''), 'section' => (string)($s['section'] ?? ''), 'father' => (string)($s['father'] ?? ''), 'adm' => (string)($s['adm'] ?? ''), 'phone' => (string)($s['phone'] ?? ''), 'ledger' => isset($led[$id]), 'inactive' => !empty($s['inactive'])];
    $set = sch_rec_get('settings', 'main') ?? [];
    sch_json(['ok' => true, 'ses' => $ses, 'sessions' => $sessions, 'live' => $cfg['sessions'][$ses] ?? null, 'students' => $list, 'role' => $role, 'canCollect' => $canCollect, 'isPrincipal' => $isP,
        'next' => $seq ? $seq['prefix'] . ($seq['n'] + 1) : '', 'banks' => $banks, 'modes' => OFC_MODES, 'months' => OFC_MONTHS, 'today' => (new DateTimeImmutable('now', new DateTimeZone('Asia/Kolkata')))->format('Y-m-d'),
        'school' => (string)($set['school'] ?? 'SN Mishra Singapore International School')]);
}
case 'ledger': {
    $sid = (string)($_GET['sid'] ?? ''); if ($ses === '' || $sid === '') sch_fail('Choose the session and the student.');
    $L = sch_rec_get('ofcLed', $ses . '|' . $sid);
    $card = ofc_cards($ses)[$sid] ?? null;
    if ($card) unset($card['months']);   // the live heads come from the ledger once a session is started
    $t = $L ? ofc_totals($L) : null;
    $ph = sch_rec_get('phoStu', $sid); $photo = $ph ? (string)((sch_rec_get('phoImg', (string)$ph['img']) ?? [])['data'] ?? '') : '';
    sch_json(['ok' => true, 'photo' => $photo, 'ledger' => $L, 'totals' => $t, 'oldDue' => $L ? ofc_old_due($ses, $sid, $L) : null, 'receipts' => $rcpts($ses, $sid), 'card' => $card,
        'cardMonths' => $L ? null : ((ofc_cards($ses)[$sid] ?? [])['months'] ?? null)]);
}
case 'collect': {
    if ($method !== 'POST') sch_fail('POST only', 405);
    if (!$canCollect) sch_fail('Only the Accountant or the Principal can take fees.', 403);
    $live = ofc_live($ses); if (!$live) sch_fail('Fees for ' . $ses . ' are not taken here yet. The Principal starts the session first.');
    $sid = (string)($in['sid'] ?? ''); $nonce = (string)($in['nonce'] ?? '');
    if (!preg_match('/^[a-f0-9]{16,64}$/', $nonce)) sch_fail('Bad request.');
    $tz = new DateTimeZone('Asia/Kolkata'); $today = (new DateTimeImmutable('now', $tz))->format('Y-m-d');
    $date = (string)($in['date'] ?? $today);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || $date > $today) throw new InvalidArgumentException('Choose a date that is not in the future.');
    if ($date < $live['from']) throw new InvalidArgumentException('This session is taken in the new ERP from ' . $live['from'] . '. Older dates belong to the old ERP.');
    $mode = (string)($in['mode'] ?? 'Cash'); if (!in_array($mode, OFC_MODES, true)) throw new InvalidArgumentException('Choose the Payment Mode.');
    $txt = fn($k, $n = 120) => mb_substr(trim(preg_replace('/[\x00-\x1f\x7f]/u', ' ', (string)($in[$k] ?? ''))), 0, $n);
    $bank = $txt('bank'); $cheque = $txt('cheque', 40); $chequeDate = $txt('chequeDate', 10); $depBank = $txt('depositBank', 60); $depDate = $txt('depositDate', 10);
    if ($mode !== 'Cash') {
        if ($depBank === '' || !in_array($depBank, array_column($banks, 'name'), true)) throw new InvalidArgumentException('Select Deposit Bank Name First');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $depDate)) throw new InvalidArgumentException('Enter Bank Deposit Date');
        if ($mode !== 'Cheque') { $bank = 'NA'; $cheque = 'NA'; $chequeDate = $date; }
        elseif ($cheque === '') throw new InvalidArgumentException('Enter the Cheque No.');
    } else { $bank = ''; $cheque = ''; $chequeDate = ''; $depBank = ''; $depDate = ''; }
    $late = ofc_amount($in['late'] ?? '0', 'Late Fee'); $disc = ofc_amount($in['discount'] ?? '0', 'Discount');
    $alloc = [];
    foreach ((array)($in['alloc'] ?? []) as $x) $alloc[] = ['i' => (int)($x['i'] ?? 0), 'amt' => ofc_amount($x['amt'] ?? '0', 'Amount')];
    $lock();
    $prior = sch_rec_get('ofcReq', $nonce);
    if ($prior) { $db->commit(); sch_json(['ok' => true, 'receipt' => $prior['receipt'], 'repeat' => true]); }
    $L = sch_rec_get('ofcLed', $ses . '|' . $sid); if (!$L) throw new InvalidArgumentException('This child has no fee card in the new ERP for ' . $ses . '.');
    $p = ofc_price($L, $alloc, $late, $disc, ofc_old_due($ses, $sid, $L));
    $before = ofc_totals($L);
    foreach ($L['heads'] as &$h) foreach ($alloc as $x) if ($x['i'] === $h['i']) $h['paid'] += $x['amt'];
    unset($h);
    $after = ofc_totals($L);
    $seq = sch_rec_get('ofcSeq', $ses) ?? ['prefix' => '1-', 'n' => 0]; $seq['n']++;
    $no = $seq['prefix'] . $seq['n'];
    if (sch_rec_get('ofcRcpt', $ses . '|' . $no)) throw new RuntimeException('Receipt number ' . $no . ' is already used. Nothing was saved; please tell the Principal.');
    $r = ['session' => $ses, 'no' => $no, 'n' => $seq['n'], 'sid' => $sid, 'date' => $date, 'time' => (new DateTimeImmutable('now', $tz))->format('h:i:s A'),
        'name' => $L['name'], 'cls' => $L['cls'], 'section' => $L['section'], 'adm' => $L['adm'], 'father' => $L['father'], 'mother' => $L['mother'], 'scheme' => $L['scheme'],
        'alloc' => $alloc, 'lines' => ofc_print_lines($alloc, $L['heads']), 'total' => $p['total'], 'late' => $p['late'], 'discount' => $p['discount'], 'received' => $p['received'],
        'balanceBefore' => $before['balance'], 'balanceAfter' => $after['balance'], 'mode' => $mode, 'bank' => $bank, 'cheque' => $cheque, 'chequeDate' => $chequeDate,
        'depositBank' => $depBank, 'depositDate' => $depDate, 'remark' => $txt('remark', 300), 'followupDate' => $txt('followupDate', 10), 'followupRemark' => $txt('followupRemark', 300),
        'status' => 'Cleared', 'by' => $u['label'] ?? $uid, 'uid' => $uid, 'at' => sch_now_ms()];
    sch_rec_put('ofcLed', $ses . '|' . $sid, $L, $uid);
    sch_rec_put('ofcSeq', $ses, $seq, $uid);
    sch_rec_put('ofcRcpt', $ses . '|' . $no, $r, $uid);
    sch_rec_put('ofcReq', $nonce, ['receipt' => $r], $uid);
    sch_log($u, 'ofcRcpt', 'Fee Receipt ' . $no . ' · ' . $L['name'] . ' · ₹' . number_format($p['received'] / 100, 2) . ' ' . $mode);
    $db->commit();
    sch_json(['ok' => true, 'receipt' => $r]);
}
case 'receipt': {
    // One receipt for printing (Fee Receipt here, or the old ERP's own print copy).
    $no = (string)($_GET['no'] ?? ''); if ($ses === '' || !preg_match('/^[\w\/-]{1,30}$/', $no)) sch_fail('Choose the receipt.');
    $r = sch_rec_get('ofcRcpt', $ses . '|' . $no);
    if ($r) sch_json(['ok' => true, 'receipt' => $r]);
    foreach (reg_rows('oldrcptdet') as $d) if (ofc_ses((string)$d['session']) === $ses && (string)$d['receiptNo'] === $no) sch_json(['ok' => true, 'old' => $d]);
    sch_fail('Receipt ' . $no . ' is not here.', 404);
}
case 'cancel': {
    if ($method !== 'POST') sch_fail('POST only', 405);
    if (!$isP) sch_fail('Only the Principal can cancel a receipt.', 403);
    $no = (string)($in['no'] ?? ''); $reason = mb_substr(trim((string)($in['reason'] ?? '')), 0, 300);
    if (mb_strlen($reason) < 4) throw new InvalidArgumentException('Write why this receipt is cancelled.');
    $lock();
    $r = sch_rec_get('ofcRcpt', $ses . '|' . $no); if (!$r) throw new InvalidArgumentException('Receipt not found.');
    if ($r['status'] === 'Cancelled') { $db->commit(); sch_json(['ok' => true, 'receipt' => $r]); }
    $L = sch_rec_get('ofcLed', $ses . '|' . $r['sid']);
    foreach ($L['heads'] as &$h) foreach ($r['alloc'] as $x) if ($x['i'] === $h['i']) $h['paid'] = max(0, $h['paid'] - $x['amt']);
    unset($h);
    $r['status'] = 'Cancelled'; $r['cancelReason'] = $reason; $r['cancelBy'] = $u['label'] ?? $uid; $r['cancelAt'] = sch_now_ms();
    sch_rec_put('ofcLed', $ses . '|' . $r['sid'], $L, $uid); sch_rec_put('ofcRcpt', $ses . '|' . $no, $r, $uid);
    sch_log($u, 'ofcRcpt', 'Cancelled Fee Receipt ' . $no . ' · ' . $r['name'] . ' · ' . $reason);
    $db->commit(); sch_json(['ok' => true, 'receipt' => $r]);
}
case 'start': {
    if ($method !== 'POST') sch_fail('POST only', 405);
    if (!$isP) sch_fail('Only the Principal can start fee collection for a session.', 403);
    if ($ses === '') throw new InvalidArgumentException('Choose the session.');
    $students = reg_students(); $cards = ofc_cards($ses);
    $make = []; $skipped = []; $have = 0; $oldest = '';
    foreach ($cards as $sid => $c) {
        if (str_starts_with((string)$sid, 'old:') || !isset($students[$sid])) { $skipped[] = (string)($c['name'] ?? '') . ' (not in the new ERP)'; continue; }
        if (sch_rec_get('ofcLed', $ses . '|' . $sid)) { $have++; continue; }
        $L = ofc_ledger_from_card($ses, (string)$sid, $c, $students[$sid]);
        if (!$L['heads']) { $skipped[] = $L['name'] . ' (no fee heads on the old card)'; continue; }
        $make[$sid] = $L;
        $d = (string)($c['asOn'] ?? ''); if ($d !== '' && ($oldest === '' || strcmp($d, $oldest) < 0)) $oldest = $d;
    }
    [$prefix, $next] = ofc_next_from_cards($cards);
    $live = ofc_live($ses);
    $preview = ['ok' => true, 'ses' => $ses, 'create' => count($make), 'already' => $have, 'skipped' => $skipped, 'firstNo' => $live ? null : ($next ? $prefix . $next : $prefix . '1'), 'copiedOn' => $oldest, 'live' => $live];
    if (($in['apply'] ?? false) !== true) sch_json($preview);
    if (($in['confirm'] ?? '') !== 'START') throw new InvalidArgumentException('Type START to confirm.');
    $from = (string)($in['from'] ?? ''); if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) throw new InvalidArgumentException('Choose the first date fees are taken here.');
    $lock();
    foreach ($make as $sid => $L) if (!sch_rec_get('ofcLed', $ses . '|' . $sid)) sch_rec_put('ofcLed', $ses . '|' . $sid, $L, $uid);
    if (!$live) {
        if (!sch_rec_get('ofcSeq', $ses)) sch_rec_put('ofcSeq', $ses, ['prefix' => $prefix, 'n' => max(0, $next - 1)], $uid);
        $cfg = ofc_cfg(); $cfg['sessions'][$ses] = ['from' => $from, 'by' => $u['label'] ?? $uid, 'at' => sch_now_ms(), 'firstNo' => $prefix . max(1, $next)];
        sch_rec_put('ofcCfg', 'main', $cfg, $uid);
    }
    sch_log($u, 'ofcCfg', 'Fee Receipt started for ' . $ses . ' from ' . $from . ' · ' . count($make) . ' fee cards');
    $db->commit();
    $preview['applied'] = true; $preview['live'] = ofc_live($ses); sch_json($preview);
}
case 'newLedger': {
    if ($method !== 'POST') sch_fail('POST only', 405);
    if (!$isP) sch_fail('Only the Principal can set a child\'s fee card.', 403);
    $sid = (string)($in['sid'] ?? ''); $from = (string)($in['from'] ?? '');
    $students = reg_students(); if (!isset($students[$sid])) throw new InvalidArgumentException('Choose the student.');
    $lock();
    if (sch_rec_get('ofcLed', $ses . '|' . $sid)) throw new InvalidArgumentException('This child already has a fee card for ' . $ses . '.');
    $src = sch_rec_get('ofcLed', $ses . '|' . $from); if (!$src) throw new InvalidArgumentException('Choose a classmate whose fee card should be copied.');
    $heads = array_map(fn($h) => ['i' => $h['i'], 'm' => $h['m'], 'name' => $h['name'], 'amt' => $h['amt'], 'paid' => 0, 'na' => !empty($h['na'])], $src['heads']);
    $s = $students[$sid];
    $L = ['session' => $ses, 'sid' => $sid, 'oldId' => '', 'name' => (string)$s['name'], 'cls' => (string)($s['cls'] ?? ''), 'section' => (string)($s['section'] ?? ''), 'adm' => (string)($s['adm'] ?? ''),
        'father' => (string)($s['father'] ?? ''), 'mother' => (string)($s['mother'] ?? ''), 'mobile' => (string)($s['phone'] ?? ''), 'scheme' => $src['scheme'], 'oldFee' => 0, 'transferFee' => 0,
        'heads' => $heads, 'from' => 'Copied from the fee card of ' . $src['name'] . ' by ' . ($u['label'] ?? $uid)];
    sch_rec_put('ofcLed', $ses . '|' . $sid, $L, $uid);
    sch_log($u, 'ofcLed', 'Fee card for ' . $L['name'] . ' (' . $ses . ') copied from ' . $src['name']);
    $db->commit(); sch_json(['ok' => true, 'ledger' => $L]);
}
default: sch_fail('Unknown action.', 404);
}
} catch (InvalidArgumentException $e) {
    if ($db->inTransaction()) $db->rollBack();
    sch_fail($e->getMessage());
} catch (Throwable $e) {
    if ($db->inTransaction()) $db->rollBack();
    error_log('feecollect_api: ' . $e->getMessage());
    sch_fail($e instanceof RuntimeException ? $e->getMessage() : 'Could not complete this. Nothing was saved.', 500);
}
