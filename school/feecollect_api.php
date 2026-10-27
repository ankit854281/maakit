<?php
// ============================================================
// Fee Receipt API (old-ERP style fee collection, see feecollect_lib.php)
//
//   GET  ?a=state&ses=2026-27          sessions, students, who may do what, next receipt no
//   GET  ?a=ledger&ses=&sid=           one child: heads, totals, old due, receipts, old card
//   POST ?a=collect {ses,sid,alloc[{i,amt}],late,discount,mode,…,nonce}   Accountant / Principal
//   POST ?a=cancel  {ses,no,reason,nonce}                                  Principal
//   POST ?a=start   {ses,apply,confirm:'START'}                            Principal
//   POST ?a=newLedger {ses,sid,from}                                       Principal
//   POST ?a=addFee  {ses,sids[],m,type,amt,remark,nonce}  Add Other Fee / Old Due (one child or a class)   Accountant / Principal
//   POST ?a=dropFee {ses,sid,i,reason}                    remove an added head nothing was paid on            Principal
//   POST ?a=discount {ses,sid,i,amt,reason}               Fee Discount (Head Wise)                            Principal
//   POST ?a=undoDisc {ses,sid,i,reason}                   take a head-wise discount back                      Principal
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
        'next' => $seq ? $seq['prefix'] . ($seq['n'] + 1) : '', 'banks' => $banks, 'modes' => OFC_MODES, 'feeTypes' => OFC_FEE_TYPES, 'months' => OFC_MONTHS, 'today' => (new DateTimeImmutable('now', new DateTimeZone('Asia/Kolkata')))->format('Y-m-d'),
        'school' => (string)($set['school'] ?? 'SN Mishra Singapore International School')]);
}
case 'ledger': {
    $sid = (string)($_GET['sid'] ?? ''); if ($ses === '' || $sid === '') sch_fail('Choose the session and the student.');
    $L = sch_rec_get('ofcLed', $ses . '|' . $sid);
    $card = ofc_cards($ses)[$sid] ?? null;
    if ($card) unset($card['months']);   // the live heads come from the ledger once a session is started
    $t = $L ? ofc_totals($L) : null;
    $ph = sch_rec_get('phoStu', $sid); $photo = $ph ? (string)((sch_rec_get('phoImg', (string)$ph['img']) ?? [])['data'] ?? '') : '';
    sch_json(['ok' => true, 'photo' => $photo, 'ledger' => $L, 'totals' => $t, 'oldDue' => $L ? ofc_old_due($ses, $sid, $L) : null, 'receipts' => $rcpts($ses, $sid), 'card' => $card, 'adj' => ofc_adj_list($ses, $sid),
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
case 'addFee': {
    // Old ERP Fee › Add Other Fee (studentothfee.php / bulk stu_addotherfee.php): a fee head added to the child's card in a
    // month. Fee Type "Old Due" puts the child's old due on the card (blank amount = all of it) so it can be collected.
    if ($method !== 'POST') sch_fail('POST only', 405);
    if (!$canCollect) sch_fail('Only the Accountant or the Principal can add fees.', 403);
    if (!ofc_live($ses)) throw new InvalidArgumentException('Fees for ' . $ses . ' are not taken here yet.');
    $type = (string)($in['type'] ?? ''); if (!in_array($type, OFC_FEE_TYPES, true)) throw new InvalidArgumentException('Select Fee Type');
    $m = (int)($in['m'] ?? 4); if (!isset(OFC_MONTHS[$m])) throw new InvalidArgumentException('Select Month');
    $remark = mb_substr(trim(preg_replace('/[\x00-\x1f\x7f]/u', ' ', (string)($in['remark'] ?? ''))), 0, 80);
    $amtIn = trim((string)($in['amt'] ?? ''));
    $amt = $amtIn === '' ? 0 : ofc_amount($amtIn, 'Amount', false);
    if ($type !== 'Old Due' && $amt <= 0) throw new InvalidArgumentException('Enter Amount');
    $sids = array_values(array_unique(array_map('strval', (array)($in['sids'] ?? []))));
    if (!$sids || count($sids) > 600) throw new InvalidArgumentException('Choose the student.');
    $nonce = (string)($in['nonce'] ?? ''); if (!preg_match('/^[a-f0-9]{16,64}$/', $nonce)) sch_fail('Bad request.');
    $lock();
    if ($prior = sch_rec_get('ofcReq', $nonce)) { $db->commit(); sch_json(['ok' => true, 'repeat' => true] + $prior['res']); }
    $added = 0; $skipped = []; $one = count($sids) === 1;
    foreach ($sids as $sid) {
        $L = sch_rec_get('ofcLed', $ses . '|' . $sid);
        if (!$L) { if ($one) throw new InvalidArgumentException('This child has no fee card for ' . $ses . '.'); $skipped[] = $sid . ' (no fee card)'; continue; }
        $x = $amt;
        if ($type === 'Old Due') {
            $od = ofc_old_due($ses, $sid, $L); $x = $amt ?: $od;
            if ($od <= 0 || $x > $od) { $why = $od <= 0 ? 'has no old due' : 'old due is only ' . number_format($od / 100, 2); if ($one) throw new InvalidArgumentException('This child ' . $why . '.'); $skipped[] = $L['name'] . ' (' . $why . ')'; continue; }
            $L['oldMoved'] = (int)($L['oldMoved'] ?? 0) + $x;
        }
        $i = max(array_merge([0], array_column($L['heads'], 'i'))) + 1;
        $name = $type . ($remark !== '' ? ' - ' . $remark : '');
        $L['heads'][] = ['i' => $i, 'm' => $m, 'name' => $name, 'amt' => $x, 'paid' => 0, 'na' => false, 'add' => ['type' => $type, 'by' => $u['label'] ?? $uid, 'at' => sch_now_ms()]];
        sch_rec_put('ofcLed', $ses . '|' . $sid, $L, $uid);
        ofc_adj_log($ses, $sid, ['act' => 'add', 'type' => $type, 'i' => $i, 'm' => $m, 'name' => $name, 'amt' => $x, 'remark' => $remark, 'by' => $u['label'] ?? $uid], $uid);
        $added++;
    }
    if (!$added) throw new InvalidArgumentException('Nothing was added: ' . implode(', ', array_slice($skipped, 0, 5)));
    $res = ['added' => $added, 'skipped' => $skipped];
    sch_rec_put('ofcReq', $nonce, ['res' => $res], $uid);
    sch_log($u, 'ofcLed', 'Add Other Fee ' . $type . ' (' . OFC_MONTHS[$m] . ') ' . ($amt ? '₹' . number_format($amt / 100, 2) : 'all old due') . ' to ' . $added . ' fee card(s), ' . $ses);
    $db->commit(); sch_json(['ok' => true] + $res);
}
case 'dropFee':
case 'discount':
case 'undoDisc': {
    if ($method !== 'POST') sch_fail('POST only', 405);
    if (!$isP) sch_fail('Only the Principal can do this.', 403);
    if (!ofc_live($ses)) throw new InvalidArgumentException('Fees for ' . $ses . ' are not taken here yet.');
    $sid = (string)($in['sid'] ?? ''); $i = (int)($in['i'] ?? 0);
    $reason = mb_substr(trim(preg_replace('/[\x00-\x1f\x7f]/u', ' ', (string)($in['reason'] ?? ''))), 0, 200);
    if (mb_strlen($reason) < 3) throw new InvalidArgumentException('Write the reason.');
    $amt = $a === 'discount' ? ofc_amount($in['amt'] ?? '', 'Discount Amount', false) : 0;
    $lock();
    $L = sch_rec_get('ofcLed', $ses . '|' . $sid); if (!$L) throw new InvalidArgumentException('This child has no fee card for ' . $ses . '.');
    $k = array_search($i, array_column($L['heads'], 'i'), true); if ($k === false) throw new InvalidArgumentException('Select the fee head.');
    $h = $L['heads'][$k]; $disc = (int)($h['disc'] ?? 0); $label = $h['name'] . ' (' . OFC_MONTHS[$h['m']] . ')';
    if ($a === 'dropFee') {
        if (empty($h['add'])) throw new InvalidArgumentException('Only a fee added here (Add Other Fee) can be removed.');
        if ($h['paid'] > 0) throw new InvalidArgumentException($label . ' is already paid or discounted. Cancel that first.');
        if ($h['add']['type'] === 'Old Due') $L['oldMoved'] = max(0, (int)($L['oldMoved'] ?? 0) - $h['amt']);
        array_splice($L['heads'], $k, 1); $amt = $h['amt'];
    } elseif ($a === 'discount') {
        if (!empty($h['na'])) throw new InvalidArgumentException($label . ' is not active for this child.');
        if ($amt > $h['amt'] - $h['paid']) throw new InvalidArgumentException('Discount is more than the balance of ' . $label . ' (' . number_format(($h['amt'] - $h['paid']) / 100, 2) . ').');
        $L['heads'][$k]['paid'] += $amt; $L['heads'][$k]['disc'] = $disc + $amt;
    } else {
        if ($disc <= 0) throw new InvalidArgumentException($label . ' has no discount given here.');
        $L['heads'][$k]['paid'] = max(0, $h['paid'] - $disc); $L['heads'][$k]['disc'] = 0; $amt = $disc;
    }
    sch_rec_put('ofcLed', $ses . '|' . $sid, $L, $uid);
    ofc_adj_log($ses, $sid, ['act' => $a === 'dropFee' ? 'drop' : ($a === 'discount' ? 'disc' : 'undisc'), 'i' => $i, 'm' => $h['m'], 'name' => $h['name'], 'amt' => $amt, 'remark' => $reason, 'by' => $u['label'] ?? $uid], $uid);
    sch_log($u, 'ofcLed', ['dropFee' => 'Removed added fee ', 'discount' => 'Fee discount ', 'undoDisc' => 'Discount taken back '][$a] . $label . ' ₹' . number_format($amt / 100, 2) . ' · ' . $L['name'] . ' · ' . $reason);
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
