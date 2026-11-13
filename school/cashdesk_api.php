<?php
// ============================================================================
//  Cash Book for the Manager app — owner, 13 Nov 2026: "manager app me fee receipt karne ka, expense entry,
//  opening aur tour ka bhi fee lene ka format add kariye". Page: cashdesk.php (+ cashdesk.js), opened inside
//  the Salary/Manager app (tk=sal) and usable with an ERP login too.
//
//  Money is the SAME money as the account reports (feereports_lib.php fr_account_rows()): fee receipts of
//  both ERPs, the old ERP's copied cashbook, and School Office › Cash Book rows (regRow 'cashbook|id', amount
//  in paise, voucher VCH/<session>/0001 from regSeq 'cashbook|<session>'). Nothing here keeps a second book.
//
//   GET  ?a=day&date=            opening cash, every entry of the day (fee, tour, expense, income…), in, out, closing
//   GET  ?a=month&ym=            day by day: opening, in, out, closing
//   POST ?a=entry {date, type: expense|income|opening|deposit|withdraw, head, party, amount (₹), mode, ref, narration}
//   POST ?a=del {id, reason}     remove a Cash Book entry (not a tour receipt — cancel that in Tour fee)
//   POST ?a=verify {date, counted, remarks}   cash counted at closing → School Office › Cash Verification
//   GET  ?a=tours                tours, children of the tour classes, who has paid
//   POST ?a=tour {id?, name, date, amount, classes[]}
//   POST ?a=tourpay {tour, sid, amount, mode, ref, date}   receipt TR/<session>/0001 + a Cash Book receipt row
//   POST ?a=tourcancel {tour, sid, reason}                  receipt Cancelled, its Cash Book row removed
//   POST ?a=tourbook {tour, sid, on}                        name written for the tour, fee later (regTourBook tour|sid)
//  Kinds: regTour (tour), regTourPay (tour|sid), regSeq 'tour|<session>' — 'reg' prefix, never in the normal load.
//  Write: Manager, Principal, Accountant. View: Chairman.
// ============================================================================
declare(strict_types=1);
require __DIR__ . '/inc.php';
require __DIR__ . '/registers_lib.php';
require __DIR__ . '/feecollect_lib.php';
require __DIR__ . '/feereports_lib.php';

$a = (string)($_GET['a'] ?? 'day');
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
try { $db = sch_db(); } catch (Throwable $e) { sch_fail('Server is not reachable right now. Please try again in a few minutes.', 503); }
$u = sch_auth(); $role = (string)$u['role']; $uid = sch_uid_of($u);
if (!in_array($role, ['manager', 'principal', 'accountant', 'chairman'], true)) sch_fail('The Cash Book is open for the Manager, Principal, Accountant and Chairman.', 403);
$canWrite = $role !== 'chairman';
$in = [];
if ($method === 'POST') {
    if (!$canWrite) sch_fail('The Chairman login can only view.', 403);
    $in = json_decode((string)file_get_contents('php://input', false, null, 0, 100000), true); if (!is_array($in)) sch_fail('Bad request.');
}
const CD_TYPES = ['expense' => 'Payment (money out)', 'income' => 'Receipt (money in)', 'opening' => 'Receipt (money in)', 'deposit' => 'Bank deposit', 'withdraw' => 'Self withdrawal'];
const CD_MODES = ['Cash', 'UPI', 'Cheque', 'NEFT', 'Card', 'Net Banking', 'Online'];
const CD_EXPENSE_HEADS = ['Electricity', 'Diesel / Petrol', 'Vehicle repair', 'Building repair', 'Stationery & printing', 'Tea & refreshment', 'Cleaning', 'Function / programme', 'Tour expense', 'Salary advance', 'Telephone & internet', 'Rent', 'Other'];

function cd_new_id(): string { return bin2hex(random_bytes(10)); }   // same shape as registers_api.php cd_new_id()
function cd_date($d): string { $d = (string)$d; if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) || !strtotime($d) || $d > date('Y-m-d')) sch_fail('Choose a date that is not in the future.'); return $d; }
function cd_txt($v, int $max): string { return mb_substr(trim(preg_replace('/[\x00-\x1F\x7F]/u', ' ', (string)$v)), 0, $max); }
function cd_paise($v, string $what = 'Amount'): int {
    $s = str_replace([',', '₹', ' '], '', (string)$v);
    if (!is_numeric($s) || (float)$s <= 0 || (float)$s > 10000000) sch_fail("$what must be more than ₹0.");
    return (int)round((float)$s * 100);
}
function cd_session(): string { $s = trim((string)((sch_rec_get('settings', 'main') ?? [])['session'] ?? '')); return $s !== '' ? $s : date('Y'); }
function cd_cashbook_put(array $row, string $id, array $u, string $uid): array {
    // same fields, numbering and stamps as registers_api.php 'save' for the cashbook register
    if (($row['no'] ?? '') === '') {
        $ss = reg_session() ?: date('Y'); $key = 'cashbook|' . $ss; $n = (int)((sch_rec_get('regSeq', $key) ?? ['n' => 0])['n']) + 1;
        sch_rec_put('regSeq', $key, ['n' => $n], $uid); $row['no'] = 'VCH/' . $ss . '/' . sprintf('%04d', $n);
    }
    $row += ['party' => '', 'mode' => 'Cash', 'ref' => '', 'narration' => ''];
    $row['_id'] = $id; $row['_by'] = (string)$u['label']; $row['_at'] = sch_now_ms(); $row['_created'] = $row['_at']; $row['_createdBy'] = $row['_by'];
    sch_rec_put('regRow', 'cashbook|' . $id, $row, $uid);
    return $row;
}
function cd_rows(string $k, ?string $prefix = null): array {
    $q = sch_db()->prepare('SELECT r, d FROM sch_records WHERE k=? AND deleted=0' . ($prefix !== null ? ' AND r LIKE ?' : ''));
    $q->execute($prefix !== null ? [$k, $prefix . '%'] : [$k]); $o = [];
    foreach ($q as $row) { $d = json_decode((string)$row['d'], true); if (is_array($d)) $o[(string)$row['r']] = $d; }
    return $o;
}
function cd_kind(array $r): string {
    if ($r['src'] === 'fee') return 'fee';
    if (str_starts_with((string)$r['head'], 'Tour fee')) return 'tour';
    if ((string)$r['head'] === 'Opening Balance') return 'opening';
    return ['RV' => 'income', 'JV' => 'expense', 'SV' => 'expense', 'BD' => 'deposit', 'SW' => 'withdraw'][$r['kind']] ?? (($r['crCash'] ?? 0) > 0 ? 'expense' : 'income');
}
function cd_students(): array {
    $o = [];
    foreach (cd_rows('student') as $sid => $s) if (empty($s['inactive']) && ($s['cls'] ?? '') !== '')
        $o[(string)$sid] = ['id' => (string)$sid, 'name' => (string)($s['name'] ?? ''), 'cls' => (string)$s['cls'], 'roll' => (string)($s['rollno'] ?? ''), 'father' => (string)($s['father'] ?? ''), 'phone' => (string)($s['phone'] ?? '')];
    return $o;
}

try {
switch ($a) {
case 'day': case 'month': {
    $acc = fr_account_rows($db)['rows'];
    if ($a === 'month') {
        $ym = (string)($_GET['ym'] ?? date('Y-m')); if (!preg_match('/^20\d\d-(0[1-9]|1[0-2])$/', $ym)) sch_fail('Choose a month.');
        $open = 0.0; $by = [];
        foreach ($acc as $r) { $d = (string)$r['date']; if ($d < $ym . '-01') $open += $r['drCash'] - $r['crCash']; elseif (substr($d, 0, 7) === $ym) { $by[$d]['in'] = ($by[$d]['in'] ?? 0) + $r['drCash']; $by[$d]['out'] = ($by[$d]['out'] ?? 0) + $r['crCash']; $by[$d]['n'] = ($by[$d]['n'] ?? 0) + 1; } }
        $days = []; $bal = $open; $n = (int)date('t', strtotime($ym . '-01'));
        for ($i = 1; $i <= $n; $i++) { $d = sprintf('%s-%02d', $ym, $i); if ($d > date('Y-m-d')) break; $x = $by[$d] ?? ['in' => 0, 'out' => 0, 'n' => 0];
            $days[] = ['date' => $d, 'opening' => round($bal, 2), 'in' => round($x['in'], 2), 'out' => round($x['out'], 2), 'closing' => round($bal + $x['in'] - $x['out'], 2), 'entries' => $x['n']]; $bal += $x['in'] - $x['out']; }
        sch_json(['ok' => true, 'ym' => $ym, 'opening' => round($open, 2), 'days' => array_reverse($days)]);
    }
    $date = cd_date($_GET['date'] ?? date('Y-m-d'));
    $open = 0.0; $rows = []; $in = 0.0; $out = 0.0; $byKind = []; $byMode = [];
    $cb = []; foreach (reg_rows('cashbook') as $r) if (($r['no'] ?? '') !== '') $cb[(string)$r['no']] = (string)($r['_id'] ?? '');
    foreach ($acc as $r) {
        if ($r['date'] < $date) { $open += $r['drCash'] - $r['crCash']; continue; }
        if ($r['date'] !== $date) continue;
        $k = cd_kind($r); $in += $r['drCash']; $out += $r['crCash'];
        $byKind[$k] = ($byKind[$k] ?? 0) + $r['drCash'] + $r['crCash'];
        if ($r['drCash'] > 0) $byMode[$r['mode'] ?: 'Cash'] = ($byMode[$r['mode'] ?: 'Cash'] ?? 0) + $r['drCash'];
        $rows[] = ['kind' => $k, 'vno' => $r['vno'], 'head' => $r['head'], 'name' => trim($r['name'] . ($r['cls'] !== '' ? ' (' . $r['cls'] . ')' : '')), 'in' => $r['drCash'], 'out' => $r['crCash'],
                   'mode' => $r['mode'], 'ref' => $r['cheque'], 'remark' => $r['remark'], 'user' => $r['user'], 'id' => $r['src'] === 'new' ? ($cb[$r['vno']] ?? '') : '', 'src' => $r['src']];
    }
    $ver = array_values(array_filter(reg_rows('cashverify'), fn($v) => ($v['date'] ?? '') === $date));
    $heads = CD_EXPENSE_HEADS; foreach (reg_rows('acchead') as $h) if (($h['side'] ?? '') === 'Expense' && ($h['name'] ?? '') !== '' && !in_array($h['name'], $heads, true)) $heads[] = (string)$h['name'];
    $set = sch_rec_get('settings', 'main') ?? [];
    sch_json(['ok' => true, 'date' => $date, 'opening' => round($open, 2), 'in' => round($in, 2), 'out' => round($out, 2), 'closing' => round($open + $in - $out, 2),
              'rows' => $rows, 'byKind' => (object)$byKind, 'byMode' => (object)$byMode, 'verify' => array_map(fn($v) => ['counted' => ((int)$v['counted']) / 100, 'book' => ((int)$v['book']) / 100, 'by' => (string)($v['by'] ?? $v['_by'] ?? ''), 'remarks' => (string)($v['remarks'] ?? '')], $ver),
              'heads' => $heads, 'modes' => CD_MODES, 'can' => $canWrite, 'me' => (string)$u['label'], 'school' => (string)($set['school'] ?? 'SN Mishra Singapore International School')]);
}
case 'entry': {
    $type = (string)($in['type'] ?? ''); if (!isset(CD_TYPES[$type])) sch_fail('Choose what kind of entry this is.');
    $date = cd_date($in['date'] ?? ''); $amt = cd_paise($in['amount'] ?? '');
    $head = $type === 'opening' ? 'Opening Balance' : ($type === 'deposit' ? 'Bank deposit' : ($type === 'withdraw' ? 'Self withdrawal' : cd_txt($in['head'] ?? '', 60)));
    if ($head === '') sch_fail($type === 'expense' ? 'Choose what the money was spent on.' : 'Write where the money came from.');
    if (str_starts_with($head, 'Tour fee')) sch_fail('Tour fee is taken in the Tour fee tab, so each child gets a receipt.');
    $mode = in_array($in['mode'] ?? 'Cash', CD_MODES, true) ? (string)($in['mode'] ?? 'Cash') : 'Cash';
    if ($type === 'opening') {
        foreach (reg_rows('cashbook') as $r) if (($r['head'] ?? '') === 'Opening Balance') sch_fail('The opening balance is already entered (' . ($r['no'] ?? '') . ' on ' . implode('-', array_reverse(explode('-', (string)$r['date']))) . '). Delete that entry first to change it.');
        $mode = 'Cash';
    }
    $db->beginTransaction(); $db->query("SELECT uid FROM sch_users WHERE uid='principal' FOR UPDATE")->fetch();
    $row = cd_cashbook_put(['date' => $date, 'side' => CD_TYPES[$type], 'head' => $head, 'party' => cd_txt($in['party'] ?? '', 80), 'amount' => $amt, 'mode' => $mode, 'ref' => cd_txt($in['ref'] ?? '', 40), 'narration' => cd_txt($in['narration'] ?? '', 300)], cd_new_id(), $u, $uid);
    sch_log($u, 'Cash Book (income & expense)', "Added {$row['no']} — " . ['expense' => 'Expense', 'income' => 'Income', 'opening' => 'Opening balance', 'deposit' => 'Bank deposit', 'withdraw' => 'Self withdrawal'][$type] . ": $head ₹" . number_format($amt / 100, 2) . " ($date)");
    $db->commit();
    sch_json(['ok' => true, 'no' => $row['no']]);
}
case 'del': {
    if (!in_array($role, ['manager', 'principal'], true)) sch_fail('Only the Manager or the Principal can delete a Cash Book entry.', 403);
    $id = (string)($in['id'] ?? ''); $why = cd_txt($in['reason'] ?? '', 200);
    if (!preg_match('/^[a-f0-9]{16,40}$/', $id)) sch_fail('Refresh and try again.');
    if (mb_strlen($why) < 3) sch_fail('Write why this entry is deleted.');
    $r = sch_rec_get('regRow', 'cashbook|' . $id); if (!$r) sch_fail('Entry not found.');
    if (!empty($r['_tour']) || str_starts_with((string)($r['head'] ?? ''), 'Tour fee')) sch_fail('This is a tour receipt — cancel it in the Tour fee tab.');
    $db->beginTransaction();
    sch_rec_put('regHist', 'cashbook|' . $id . '|' . sch_now_ms(), ['m' => 'cashbook', 'row' => $r, 'why' => $why, 'by' => $u['label']], $uid);
    sch_rec_del('regRow', 'cashbook|' . $id, $uid);
    sch_log($u, 'Cash Book (income & expense)', 'Deleted ' . ($r['no'] ?? '') . ' — ' . ($r['head'] ?? '') . ' ₹' . number_format(((int)$r['amount']) / 100, 2) . " — reason: $why");
    $db->commit();
    sch_json(['ok' => true]);
}
case 'verify': {
    $date = cd_date($in['date'] ?? ''); $counted = cd_paise($in['counted'] ?? '', 'Cash counted');
    $acc = fr_account_rows($db)['rows']; $book = 0.0; foreach ($acc as $r) if ($r['date'] <= $date) $book += $r['drCash'] - $r['crCash'];
    $id = cd_new_id(); $row = ['date' => $date, 'book' => (int)round($book * 100), 'counted' => $counted, 'by' => (string)$u['label'], 'remarks' => cd_txt($in['remarks'] ?? '', 200),
        '_id' => $id, '_by' => (string)$u['label'], '_at' => sch_now_ms()]; $row['_created'] = $row['_at']; $row['_createdBy'] = $row['_by'];
    sch_rec_put('regRow', 'cashverify|' . $id, $row, $uid);
    $diff = $counted / 100 - $book;
    sch_log($u, 'Cash Verification', "Closing $date: book ₹" . number_format($book, 2) . ', counted ₹' . number_format($counted / 100, 2) . ($diff ? ', difference ₹' . number_format($diff, 2) : ', matches'));
    sch_json(['ok' => true, 'diff' => round($diff, 2)]);
}
case 'tours': {
    $ses = cd_session(); $kids = cd_students(); $out = [];
    $pays = cd_rows('regTourPay'); $books = cd_rows('regTourBook');
    foreach (cd_rows('regTour') as $tid => $t) {
        if (($t['session'] ?? '') !== $ses) continue;
        $cls = (array)($t['classes'] ?? []); $L = [];
        foreach ($kids as $s) if (!$cls || in_array($s['cls'], $cls, true)) { $p = $pays[$tid . '|' . $s['id']] ?? null; $b = $books[$tid . '|' . $s['id']] ?? null; $L[] = $s + ['book' => $b && !empty($b['on']) ? ['by' => (string)($b['by'] ?? ''), 'at' => (int)($b['at'] ?? 0)] : null, 'pay' => $p ? ['no' => $p['no'], 'amount' => $p['amount'] / 100, 'mode' => $p['mode'], 'ref' => $p['ref'] ?? '', 'date' => $p['date'], 'by' => $p['by'], 'status' => $p['status'], 'reason' => $p['reason'] ?? ''] : null]; }
        foreach ($pays as $k => $p) if (str_starts_with($k, $tid . '|') && !isset($kids[substr($k, strlen($tid) + 1)])) $L[] = ['id' => substr($k, strlen($tid) + 1), 'name' => (string)($p['name'] ?? ''), 'cls' => (string)($p['cls'] ?? ''), 'roll' => '', 'father' => '', 'phone' => '', 'pay' => ['no' => $p['no'], 'amount' => $p['amount'] / 100, 'mode' => $p['mode'], 'ref' => $p['ref'] ?? '', 'date' => $p['date'], 'by' => $p['by'], 'status' => $p['status'], 'reason' => $p['reason'] ?? '']];
        $out[] = ['id' => $tid, 'name' => (string)$t['name'], 'date' => (string)$t['date'], 'amount' => ((int)$t['amount']) / 100, 'classes' => $cls, 'kids' => $L];
    }
    usort($out, fn($x, $y) => strcmp($y['date'], $x['date']));
    $classes = array_values(array_filter(array_map(fn($c) => (string)($c['c'] ?? ''), (array)(sch_rec_get('classes', 'main') ?? []))));
    $set = sch_rec_get('settings', 'main') ?? [];
    sch_json(['ok' => true, 'session' => $ses, 'tours' => $out, 'classes' => $classes, 'modes' => CD_MODES, 'can' => $canWrite, 'me' => (string)$u['label'], 'school' => (string)($set['school'] ?? 'SN Mishra Singapore International School')]);
}
case 'tour': {
    if (!in_array($role, ['manager', 'principal'], true)) sch_fail('Only the Manager or the Principal can set up a tour.', 403);
    $name = cd_txt($in['name'] ?? '', 80); if (mb_strlen($name) < 3) sch_fail('Write the tour name.');
    $date = (string)($in['date'] ?? ''); if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !strtotime($date)) sch_fail('Choose the tour date.');
    $amt = cd_paise($in['amount'] ?? '', 'Tour fee per child');
    $all = array_values(array_filter(array_map(fn($c) => (string)($c['c'] ?? ''), (array)(sch_rec_get('classes', 'main') ?? []))));
    $cls = array_values(array_intersect($all, array_map('strval', (array)($in['classes'] ?? [])))); if (!$cls) sch_fail('Choose the classes going on the tour.');
    $id = (string)($in['id'] ?? ''); $old = null;
    if ($id !== '') { if (!preg_match('/^t[a-f0-9]{10}$/', $id) || !($old = sch_rec_get('regTour', $id))) sch_fail('Tour not found.'); } else $id = 't' . bin2hex(random_bytes(5));
    sch_rec_put('regTour', $id, ['name' => $name, 'date' => $date, 'amount' => $amt, 'classes' => $cls, 'session' => $old['session'] ?? cd_session(), 'by' => $u['label'], 'at' => sch_now_ms()], $uid);
    sch_log($u, 'Tour fee', ($old ? 'Changed' : 'New') . " tour: $name ($date), ₹" . number_format($amt / 100) . ' per child, classes ' . implode(', ', $cls));
    sch_json(['ok' => true, 'id' => $id]);
}
case 'tourpay': {
    $tid = (string)($in['tour'] ?? ''); $sid = (string)($in['sid'] ?? '');
    $t = preg_match('/^t[a-f0-9]{10}$/', $tid) ? sch_rec_get('regTour', $tid) : null; if (!$t) sch_fail('Tour not found.');
    $kids = cd_students(); $s = $kids[$sid] ?? null; if (!$s) sch_fail('Child not found.');
    if (($t['classes'] ?? []) && !in_array($s['cls'], (array)$t['classes'], true)) sch_fail('This child\'s class is not on this tour.');
    $amt = cd_paise($in['amount'] ?? (((int)$t['amount']) / 100)); $date = cd_date($in['date'] ?? date('Y-m-d'));
    $mode = in_array($in['mode'] ?? 'Cash', CD_MODES, true) ? (string)($in['mode'] ?? 'Cash') : 'Cash'; $ref = cd_txt($in['ref'] ?? '', 40);
    if ($mode !== 'Cash' && $ref === '') sch_fail('Write the UPI / cheque / transaction number.');
    $db->beginTransaction(); $db->query("SELECT uid FROM sch_users WHERE uid='principal' FOR UPDATE")->fetch();
    $key = $tid . '|' . $sid; $old = sch_rec_get('regTourPay', $key);
    if ($old && ($old['status'] ?? '') === 'Paid') { $db->rollBack(); sch_fail('Already paid — receipt ' . $old['no'] . '.'); }
    $ses = (string)($t['session'] ?? cd_session()); $sk = 'tour|' . $ses; $n = (int)((sch_rec_get('regSeq', $sk) ?? ['n' => 0])['n']) + 1; sch_rec_put('regSeq', $sk, ['n' => $n], $uid);
    $no = 'TR/' . $ses . '/' . sprintf('%04d', $n); $cb = cd_new_id();
    cd_cashbook_put(['date' => $date, 'side' => 'Receipt (money in)', 'head' => 'Tour fee — ' . $t['name'], 'party' => $s['name'] . ' (' . $s['cls'] . ')', 'amount' => $amt, 'mode' => $mode,
        'ref' => trim($no . ($ref !== '' ? ' · ' . $ref : '')), 'narration' => 'Tour receipt ' . $no, '_tour' => $key], $cb, $u, $uid);
    $p = ['no' => $no, 'amount' => $amt, 'mode' => $mode, 'ref' => $ref, 'date' => $date, 'by' => (string)$u['label'], 'at' => sch_now_ms(), 'status' => 'Paid', 'cb' => $cb, 'name' => $s['name'], 'cls' => $s['cls']];
    if ($old) $p['earlier'] = array_merge((array)($old['earlier'] ?? []), [array_diff_key($old, ['earlier' => 1])]);
    sch_rec_put('regTourPay', $key, $p, $uid);
    sch_log($u, 'Tour fee', "Receipt $no — {$s['name']} ({$s['cls']}), {$t['name']}: ₹" . number_format($amt / 100) . " $mode");
    $db->commit();
    sch_json(['ok' => true, 'no' => $no]);
}
case 'tourcancel': {
    if (!in_array($role, ['manager', 'principal'], true)) sch_fail('Only the Manager or the Principal can cancel a tour receipt.', 403);
    $key = (string)($in['tour'] ?? '') . '|' . (string)($in['sid'] ?? ''); $why = cd_txt($in['reason'] ?? '', 200);
    if (mb_strlen($why) < 3) sch_fail('Write why the receipt is cancelled.');
    $db->beginTransaction(); $db->query("SELECT uid FROM sch_users WHERE uid='principal' FOR UPDATE")->fetch();
    $p = sch_rec_get('regTourPay', $key); if (!$p || ($p['status'] ?? '') !== 'Paid') { $db->rollBack(); sch_fail('No paid receipt to cancel.'); }
    $p['status'] = 'Cancelled'; $p['reason'] = $why; $p['cancelledBy'] = (string)$u['label']; $p['cancelledAt'] = sch_now_ms();
    sch_rec_put('regTourPay', $key, $p, $uid);
    if (!empty($p['cb'])) sch_rec_del('regRow', 'cashbook|' . $p['cb'], $uid);
    sch_log($u, 'Tour fee', "Cancelled receipt {$p['no']} — {$p['name']} ({$p['cls']}) ₹" . number_format($p['amount'] / 100) . " — reason: $why");
    $db->commit();
    sch_json(['ok' => true]);
}
case 'tourbook': {
    // Owner, 13 Nov 2026: "kuch bachche bas name tick kara rahe hain, payment baad me denge" — seat booked, fee later.
    $tid = (string)($in['tour'] ?? ''); $sid = (string)($in['sid'] ?? ''); $on = !empty($in['on']);
    $t = preg_match('/^t[a-f0-9]{10}$/', $tid) ? sch_rec_get('regTour', $tid) : null; if (!$t) sch_fail('Tour not found.');
    $kids = cd_students(); $s = $kids[$sid] ?? null; if (!$s) sch_fail('Child not found.');
    if (($t['classes'] ?? []) && !in_array($s['cls'], (array)$t['classes'], true)) sch_fail('This child\'s class is not on this tour.');
    sch_rec_put('regTourBook', $tid . '|' . $sid, ['on' => $on, 'by' => (string)$u['label'], 'at' => sch_now_ms(), 'name' => $s['name'], 'cls' => $s['cls']], $uid);
    sch_log($u, 'Tour fee', ($on ? 'Name written (going, will pay later): ' : 'Name removed from the tour list: ') . "{$s['name']} ({$s['cls']}), {$t['name']}");
    sch_json(['ok' => true]);
}
default: sch_fail('Unknown action.');
}
} catch (Throwable $e) { if ($db->inTransaction()) $db->rollBack(); throw $e; }
