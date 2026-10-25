<?php
// ============================================================================
//  Old ERP Fee Reports — owner, 25 Oct 2026: "bilkul difference na feel ho"
//
//  The old ERP's Fee Report menu (report_fee.php, report_feen.php, …) rebuilt
//  with the same columns, in the same order, from the receipts this ERP holds:
//    - old ERP receipts copied before the switch (registers oldreceipt +
//      oldrcptdet: one row per receipt + the heads printed on it), and
//    - receipts taken here in Fee Receipt (ofcRcpt) after the switch.
//  So the same report gives the same numbers before and after the switch.
//  A receipt number is never counted twice: a receipt taken here wins over an
//  archive row with the same session and number (that cannot normally happen).
//  Read-only. Money in paise; shown like the old ERP (1580, 1580.5).
// ============================================================================
declare(strict_types=1);

// The old ERP's fee heads in its own column order (report_feen.php, 2023-24 to 2026-27).
const FR_HEADS = ['Digital Class Room Fee', 'School Fee', 'Dress', 'Diary', 'Board Fee X', 'Admission Fee', 'Annual Fee', 'Tution Fee', 'Health And Sefety Fee', 'Exam Fee', 'Old Due', 'Transport', 'Book', 'Hostel', 'Sports Fee'];
const FR_PMODES = ['Cash', 'Cheque', 'Card', 'Wallet', 'NEFT', 'UPI', 'Net Banking', 'Online'];

function fr_rs(int $p): string { $s = number_format($p / 100, 2, '.', ''); return str_contains($s, '.') ? rtrim(rtrim($s, '0'), '.') : $s; }
function fr_p($v): int { if (is_int($v)) return $v; $s = str_replace([',', '₹', ' '], '', (string)$v); return preg_match('/^-?\d+(\.\d+)?$/', $s) ? (int)round(((float)$s) * 100) : 0; }
function fr_dmy(string $iso): string { return preg_match('/^(\d{4})-(\d\d)-(\d\d)$/', $iso, $m) ? "$m[3]-$m[2]-$m[1]" : $iso; }
function fr_long_ses(string $ses): string { return preg_match('/^(20\d\d)-(\d\d)$/', $ses, $m) ? $m[1] . '-20' . $m[2] : $ses; }
/** "Online Cleared" → Online; the old ERP adds the clearing status to the mode. */
function fr_mode(string $m): string { $m = trim(preg_replace('/\s+Cleared$/i', '', $m)); foreach (FR_PMODES as $x) if (strcasecmp($x, $m) === 0) return $x; return $m; }

/** Every receipt of a session (or of every session when $ses is ''), oldest first. */
function fr_receipts(string $ses = ''): array {
    $db = sch_db(); $out = [];
    $det = [];
    foreach (reg_rows('oldrcptdet') as $d) $det[(string)$d['session'] . '|' . (string)$d['receiptNo']] = $d;
    foreach (reg_rows('oldreceipt') as $r) {
        if ($ses !== '' && (string)$r['session'] !== $ses) continue;
        $k = (string)$r['session'] . '|' . (string)$r['receiptNo']; $d = $det[$k] ?? [];
        $lines = [];
        foreach ((array)($d['lines'] ?? []) as $l) if (is_array($l) && trim((string)($l[1] ?? '')) !== '') $lines[] = ['head' => trim((string)$l[1]), 'months' => (string)($l[2] ?? ''), 'amt' => fr_p($l[3] ?? '')];
        preg_match('/(\d{1,2}:\d\d(?::\d\d)?\s*[AP]M)/i', (string)($d['date'] ?? ''), $tm);
        $out[$k] = ['session' => (string)$r['session'], 'no' => (string)$r['receiptNo'], 'n' => (int)preg_replace('/^\D*\d+-/', '', (string)$r['receiptNo']), 'date' => (string)$r['date'], 'time' => $tm[1] ?? '',
            'sid' => (string)($r['student'] ?? ''), 'adm' => (string)($r['scholarNo'] ?? ''), 'name' => (string)$r['name'], 'father' => (string)($r['father'] ?? ''), 'cls' => (string)($r['cls'] ?? ''), 'section' => (string)($r['section'] ?? ''),
            'months' => (string)($r['months'] ?? ''), 'total' => fr_p($r['total'] ?? 0), 'discount' => fr_p($r['discount'] ?? 0), 'late' => fr_p($r['lateFee'] ?? 0), 'received' => fr_p($r['received'] ?? 0),
            'mode' => fr_mode((string)($r['mode'] ?? '')), 'modeText' => (string)($r['mode'] ?? ''), 'bank' => (string)($r['bank'] ?? ''), 'customerBank' => '', 'cheque' => (string)($r['cheque'] ?? ''), 'chequeDate' => '',
            'status' => (string)($r['status'] ?? 'Cleared'), 'user' => (string)($r['user'] ?? ''), 'remark' => (string)($r['remark'] ?? ''), 'followup' => '', 'lines' => $lines, 'old' => true];
    }
    $st = $db->prepare("SELECT d FROM sch_records WHERE k='ofcRcpt' AND r LIKE ? AND deleted=0"); $st->execute([$ses === '' ? '%' : $ses . '|%']);
    foreach ($st as $row) {
        $r = json_decode((string)$row['d'], true); if (!is_array($r)) continue;
        $lines = []; foreach ((array)($r['lines'] ?? []) as $l) $lines[] = ['head' => (string)$l[1], 'months' => (string)$l[2], 'amt' => (int)$l[3]];
        $out[$r['session'] . '|' . $r['no']] = ['session' => (string)$r['session'], 'no' => (string)$r['no'], 'n' => (int)$r['n'], 'date' => (string)$r['date'], 'time' => (string)($r['time'] ?? ''),
            'sid' => (string)$r['sid'], 'adm' => (string)$r['adm'], 'name' => (string)$r['name'], 'father' => (string)$r['father'], 'cls' => (string)$r['cls'], 'section' => (string)$r['section'],
            'months' => implode(',', array_values(array_unique(array_merge(...array_map(fn($l) => explode(',', $l['months']), $lines ?: [['months' => '']]))))), 'total' => (int)$r['total'], 'discount' => (int)$r['discount'], 'late' => (int)$r['late'], 'received' => (int)$r['received'],
            'mode' => (string)$r['mode'], 'modeText' => (string)$r['mode'] . ($r['mode'] === 'Cash' ? '' : ' Cleared'), 'bank' => (string)($r['depositBank'] ?? ''), 'customerBank' => (string)($r['bank'] ?? ''), 'cheque' => (string)($r['cheque'] ?? ''), 'chequeDate' => (string)($r['chequeDate'] ?? ''),
            'status' => (string)$r['status'], 'user' => (string)$r['by'], 'remark' => (string)($r['remark'] ?? ''), 'followup' => (string)($r['followupRemark'] ?? ''), 'lines' => $lines, 'old' => false, 'cancelReason' => (string)($r['cancelReason'] ?? ''), 'cancelBy' => (string)($r['cancelBy'] ?? '')];
    }
    $out = array_values($out);
    usort($out, fn($a, $b) => [$a['date'], $a['session'], $a['n']] <=> [$b['date'], $b['session'], $b['n']]);
    return $out;
}

/** Filters exactly like the old report pages: date range, class, section, payment mode, user. Cancelled receipts are left out except where asked. */
function fr_filter(array $rows, array $f, bool $cancelled = false): array {
    return array_values(array_filter($rows, function ($r) use ($f, $cancelled) {
        if (($r['status'] === 'Cancelled') !== $cancelled) return false;
        if (($f['from'] ?? '') !== '' && $r['date'] < $f['from']) return false;
        if (($f['to'] ?? '') !== '' && $r['date'] > $f['to']) return false;
        if (($f['cls'] ?? '') !== '' && $r['cls'] !== $f['cls']) return false;
        if (($f['sec'] ?? '') !== '' && $r['section'] !== $f['sec']) return false;
        if (($f['mode'] ?? '') !== '' && $r['mode'] !== $f['mode']) return false;
        if (($f['user'] ?? '') !== '' && $r['user'] !== $f['user']) return false;
        return true;
    }));
}
function fr_heads_of(array $rows): array {
    $seen = [];
    foreach ($rows as $r) foreach ($r['lines'] as $l) $seen[$l['head']] = true;
    $out = array_values(array_filter(FR_HEADS, fn($h) => true));
    foreach (array_keys($seen) as $h) if (!in_array($h, $out, true)) $out[] = $h;
    return $out;
}
function fr_head_amts(array $r): array { $a = []; foreach ($r['lines'] as $l) $a[$l['head']] = ($a[$l['head']] ?? 0) + $l['amt']; return $a; }

/** The reports. Each returns [title, head rows, body rows, total row|null, money columns]. Cells are strings; 'act' cells carry {print|cancel}. */
function fr_reports(): array {
    return [
        'fee1' => ['Fee Collection Report I', 'report_fee.php'], 'fee2' => ['Fee Collection Report II', 'report_feen.php'], 'fee4' => ['Fee Collection Report IV', 'report_feecollection_four.php'],
        'fee5' => ['Fee Collection Report V', 'report_feecollection_head.php'], 'head' => ['Head Wise Collection Report', 'report_sheadfee.php'], 'headst' => ['Head Wise Fee Statement', 'report_feestatement.php'],
        'bank1' => ['Bank Report I', 'report_fee_bank.php'], 'bank2' => ['Bank Report II', 'report_fee_bank_sec.php'], 'conc' => ['Fees Concession Report', 'report_fee_oncession.php'],
        'sesfee' => ['Session Wise Fee', 'report_sfee.php'], 'monthly' => ['Monthly Collection Report', 'fees_monthlycollection.php'], 'deleted' => ['Deleted Fee Receipt', 'report_delete_fee.php'],
        'user' => ['Userwise Collection Report', 'report_monthcoll.php'],
        'stmt' => ['Fee Statement', 'studuefeedet.php'], 'def2' => ['Fee Defaulter - II', 'studuedfaulter_new.php'], 'clswise' => ['Class Wise Collection Report', 'fees_classwiseollection.php'],
    ];
}
function fr_build(string $kind, string $ses, array $f): array {
    if (in_array($kind, ['stmt', 'def2', 'clswise'], true)) return fr_dues($kind, $ses, $f);
    $all = fr_receipts($ses); $rows = fr_filter($all, $f); $R = 'fr_rs';
    $sum = fn(array $rs, string $k) => array_sum(array_column($rs, $k));
    switch ($kind) {
    case 'fee1':
        $head = ['Sno', 'Session', 'Last Followup Remark', 'Receipt No', 'Date', 'Scholar No', 'Class', 'Section', 'Name', "Father's Name", 'Month', 'Remark', 'Total Amount', 'Discount', 'Late Fee', 'Currently Received', 'Customer Bank', 'Cheque No', 'Status', 'Bank', 'PMode', 'UName', 'Print', 'Delete'];
        $body = []; foreach ($rows as $i => $r) $body[] = [(string)($i + 1), fr_long_ses($r['session']), $r['followup'], $r['no'], fr_dmy($r['date']), $r['adm'], $r['cls'], $r['section'], $r['name'], $r['father'], $r['months'], $r['remark'],
            $R($r['total']), $R($r['discount']), $R($r['late']), $R($r['received']), $r['customerBank'], $r['cheque'], $r['status'], $r['bank'], $r['modeText'], $r['user'], ['print' => $r['session'] . '|' . $r['no']], ''];
        $tot = array_fill(0, count($head), ''); $tot[11] = 'Total'; $tot[12] = $R($sum($rows, 'total')); $tot[13] = $R($sum($rows, 'discount')); $tot[14] = $R($sum($rows, 'late')); $tot[15] = $R($sum($rows, 'received'));
        return [[$head], $body, $tot];
    case 'fee2': case 'conc': case 'bank1':
        if ($kind === 'conc') $rows = array_values(array_filter($rows, fn($r) => $r['discount'] > 0));
        $H = fr_heads_of($rows);
        $pre = $kind === 'fee2' ? ['Sno', 'Session', 'Receipt No', 'Date', 'SCH.NO.', 'Name', "Father's Name", 'Class', 'Section'] : ($kind === 'conc' ? ['Sno', 'Receipt No', 'Date', 'SCH.NO.', 'Name', "Father's Name", 'Class', 'Section'] : ['Sno', 'Bank Name', 'Cheque No', 'Currently Received', 'Scholar No', 'Name', 'Class', 'Section', 'Receipt No', 'Date']);
        $post = $kind === 'fee2' ? ['Total Amount', 'Discount Amount', 'Other Fee', 'Currently Received', 'PMode', 'Remark'] : ($kind === 'conc' ? ['Total Amount', 'Discount Amount', 'Other Fee', 'Currently Received', 'PMode', 'Remark', 'UName'] : ['Total Amount', 'Discount Amount', 'Late Fee', 'PMode', 'Cheque Date']);
        $body = []; $ht = array_fill_keys($H, 0);
        foreach ($rows as $i => $r) {
            $a = fr_head_amts($r); foreach ($a as $h => $v) $ht[$h] += $v;
            $p = $kind === 'fee2' ? [(string)($i + 1), fr_long_ses($r['session']), $r['no'], fr_dmy($r['date']), $r['adm'], $r['name'], $r['father'], $r['cls'], $r['section']]
                : ($kind === 'conc' ? [(string)($i + 1), $r['no'], fr_dmy($r['date']), $r['adm'], $r['name'], $r['father'], $r['cls'], $r['section']] : [(string)($i + 1), $r['customerBank'], $r['cheque'] === 'NA' ? '' : $r['cheque'], $R($r['received']), $r['adm'], $r['name'], $r['cls'], $r['section'], $r['no'], fr_dmy($r['date'])]);
            $q = $kind === 'bank1' ? [$R($r['total']), $R($r['discount']), $R($r['late']), $r['modeText'], $r['chequeDate'] !== '' ? fr_dmy($r['chequeDate']) : '']
                : [$R($r['total']), $R($r['discount']), '0', $R($r['received']), $r['modeText'], $r['remark']] + ($kind === 'conc' ? [6 => $r['user']] : []);
            $body[] = array_merge($p, array_map(fn($h) => isset($a[$h]) ? $R($a[$h]) : '', $H), $q);
        }
        $tot = array_merge(array_fill(0, count($pre), ''), array_map(fn($h) => $R($ht[$h]), $H), $kind === 'bank1' ? [$R($sum($rows, 'total')), $R($sum($rows, 'discount')), $R($sum($rows, 'late')), '', ''] : [$R($sum($rows, 'total')), $R($sum($rows, 'discount')), '0', $R($sum($rows, 'received')), '', ''] + ($kind === 'conc' ? [6 => ''] : []));
        $tot[count($pre) - 1] = 'Total'; if ($kind === 'bank1') $tot[3] = $R($sum($rows, 'received'));
        return [[array_merge($pre, $H, $post)], $body, $tot];
    case 'fee4': case 'fee5': case 'monthly': case 'user':
        $by = [];
        foreach ($rows as $r) { $k = $kind === 'monthly' ? substr($r['date'], 0, 7) : $r['date']; $by[$k][] = $r; }
        ksort($by);
        if ($kind === 'fee4') {
            $body = []; $i = 0;
            foreach ($by as $d => $rs) { $nos = array_column($rs, 'no'); $body[] = [(string)(++$i), fr_dmy($d), $nos[0] . (count($nos) > 1 ? ' to ' . end($nos) : ''), $R($sum($rs, 'total')) . '(' . count($rs) . ')', $R($sum($rs, 'late')), $R($sum($rs, 'discount')), $R($sum($rs, 'received'))]; }
            return [[['Sno', 'Date', 'Receipt No', 'Total Fee', 'Late Fee', 'Discount', 'Recv Amount']], $body, ['', '', 'Total', $R($sum($rows, 'total')) . '(' . count($rows) . ')', $R($sum($rows, 'late')), $R($sum($rows, 'discount')), $R($sum($rows, 'received'))]];
        }
        if ($kind === 'monthly') {
            $mn = ['04' => 'April', '05' => 'May', '06' => 'June', '07' => 'July', '08' => 'August', '09' => 'September', '10' => 'October', '11' => 'November', '12' => 'December', '01' => 'January', '02' => 'February', '03' => 'March'];
            $body = [];
            foreach ($mn as $m => $name) { $rs = []; foreach ($by as $ym => $x) if ((int)substr($ym, 5, 2) === (int)$m) $rs = array_merge($rs, $x); $body[] = [$name, $R($sum($rs, 'total')), $R($sum($rs, 'discount')), $R($sum($rs, 'received'))]; }
            return [[['Month', 'Total Fee', 'Discount', 'Recv Amount']], $body, ['Total', $R($sum($rows, 'total')), $R($sum($rows, 'discount')), $R($sum($rows, 'received'))]];
        }
        if ($kind === 'user') {
            // Cash taken by each user, day by day (fee receipts). Cash expense is in the Cash Book, not in receipts.
            $users = array_values(array_unique(array_column(array_filter($rows, fn($r) => $r['mode'] === 'Cash'), 'user'))); sort($users);
            $head = ['Sno', 'Date']; foreach ($users as $x) array_push($head, $x . '(Cash Income)', $x . '(Cash Expense)', $x . '(Remaining)');
            $body = []; $i = 0; $tot = array_fill(0, count($head), 0);
            foreach ($by as $d => $rs) { $row = [(string)(++$i), fr_dmy($d)]; foreach ($users as $j => $x) { $v = $sum(array_filter($rs, fn($r) => $r['mode'] === 'Cash' && $r['user'] === $x), 'received'); array_push($row, $R($v), '0', $R($v)); $tot[2 + $j * 3] += $v; $tot[4 + $j * 3] += $v; } $body[] = $row; }
            $t = ['', 'Total']; foreach ($users as $j => $x) array_push($t, $R($tot[2 + $j * 3]), '0', $R($tot[4 + $j * 3]));
            return [[$head], $body, $t];
        }
        $H = fr_heads_of($rows); $modes = FR_PMODES;
        $body = []; $i = 0; $T = ['h' => array_fill_keys($H, 0), 'm' => array_fill_keys($modes, 0)];
        foreach ($by as $d => $rs) {
            $ha = array_fill_keys($H, 0); $ma = array_fill_keys($modes, 0);
            foreach ($rs as $r) { foreach (fr_head_amts($r) as $h => $v) $ha[$h] += $v; if (isset($ma[$r['mode']])) $ma[$r['mode']] += $r['received']; }
            foreach ($ha as $h => $v) $T['h'][$h] += $v; foreach ($ma as $m => $v) $T['m'][$m] += $v;
            $body[] = array_merge([(++$i) . '.', fr_dmy($d)], array_map(fn($v) => $v ? $R($v) : '', array_values($ha)), [$R($sum($rs, 'total')), $R($sum($rs, 'discount')), '0', $R($sum($rs, 'received'))], array_map(fn($v) => $v ? $R($v) : '', array_values($ma)));
        }
        return [[array_merge(['Sno', 'Date'], $H, ['Total Amount', 'Discount Amount', 'Other Fee', 'Currently Received'], $modes)], $body,
            array_merge(['', 'Total'], array_map($R, array_values($T['h'])), [$R($sum($rows, 'total')), $R($sum($rows, 'discount')), '0', $R($sum($rows, 'received'))], array_map($R, array_values($T['m'])))];
    case 'head': case 'headst':
        $body = []; $i = 0; $ht = [];
        foreach ($rows as $r) foreach ($r['lines'] as $l) {
            $ht[$l['head']] = ($ht[$l['head']] ?? 0) + $l['amt'];
            if ($kind === 'head') $body[] = [(string)(++$i), fr_dmy($r['date']), $r['no'], 'Day Scholar', '', $r['adm'], $r['name'], $r['cls'], $r['section'], 'NA', $l['head'], $r['modeText'], $l['months'], $R($l['amt']), '0', $R($l['amt'])];
        }
        if ($kind === 'headst') { $order = fr_heads_of($rows); $body = []; foreach ($order as $h) if (isset($ht[$h])) $body[] = [$h, $R($ht[$h])]; return [[['Fee Head', 'Amount']], $body, ['Total', $R(array_sum($ht))]]; }
        return [[['Sno', 'Date', 'Recpt. No.', 'Admission As', 'Student Type', 'Scholar No', 'Name', 'Class', 'Section', 'Stream', 'Head', 'Payment Mode', 'Month', 'Amount', 'Discount', 'Total Amount']], $body,
            ['', '', '', '', '', '', '', '', '', '', '', '', 'Total', $R(array_sum($ht)), '0', $R(array_sum($ht))]];
    case 'bank2':
        $rows = array_values(array_filter($rows, fn($r) => $r['mode'] !== 'Cash')); $body = [];
        foreach ($rows as $i => $r) $body[] = [(string)($i + 1), $r['customerBank'] !== '' ? $r['customerBank'] : $r['bank'], $r['cheque'] === 'NA' ? '' : $r['cheque'], $r['chequeDate'] !== '' ? fr_dmy($r['chequeDate']) : '', $R($r['received'])];
        return [[['Sno', 'Bank Name', 'Cheque No', 'Cheque Date', 'Currently Received']], $body, ['', '', '', 'Total', $R($sum($rows, 'received'))]];
    case 'sesfee':
        $body = []; foreach ($rows as $i => $r) $body[] = [(string)($i + 1), $r['no'], fr_dmy($r['date']), $r['name'], $R($r['total']), $R($r['discount']), $R($r['received']), $r['modeText'], $r['user'], ['print' => $r['session'] . '|' . $r['no']]];
        return [[['Sno', 'Receipt No', 'Date', 'Name', 'Total Amount', 'Discount Amount', 'Currently Received', 'PMode', 'UName', 'Print']], $body, ['', '', '', 'Total', $R($sum($rows, 'total')), $R($sum($rows, 'discount')), $R($sum($rows, 'received')), '', '', '']];
    case 'deleted':
        $rows = fr_filter($all, $f, true); $body = [];
        foreach ($rows as $i => $r) $body[] = [(string)($i + 1), $r['no'], fr_dmy($r['date']), $r['adm'], $r['name'], $r['cls'], $R($r['received']), $r['modeText'], $r['cancelReason'] ?? '', $r['cancelBy'] ?? ''];
        return [[['Sno', 'Receipt No', 'Date', 'Scholar No', 'Name', 'Class', 'Currently Received', 'PMode', 'Reason', 'Deleted By']], $body, ['', '', '', '', '', 'Total', $R($sum($rows, 'received')), '', '', '']];
    }
    throw new InvalidArgumentException('Choose a report.');
}

/**
 * Each child's fee card for a session, as the dues reports need it: heads up to a month, old due, and the
 * receipts' discount / received. From the Fee Receipt ledger once the session is started, else from the
 * old ERP fee card (its Paid/Disc column, like the old ERP).
 */
function fr_cards(string $ses, int $upto): array {
    $order = [4, 5, 6, 7, 8, 9, 10, 11, 12, 1, 2, 3]; $lim = array_search($upto, $order, true); if ($lim === false) $lim = 11;
    $months = array_slice($order, 0, $lim + 1);
    $cards = ofc_cards($ses); $live = ofc_live($ses); $students = reg_students(); $out = [];
    $rc = []; foreach (fr_receipts($ses) as $r) if ($r['status'] !== 'Cancelled' && $r['sid'] !== '') $rc[$r['sid']][] = $r;
    $keys = array_keys($cards);
    if ($live) { $st = sch_db()->prepare("SELECT r FROM sch_records WHERE k='ofcLed' AND r LIKE ? AND deleted=0"); $st->execute([$ses . '|%']); foreach ($st as $row) $keys[] = substr((string)$row['r'], strlen($ses) + 1); }
    foreach (array_unique($keys) as $sid) {
        $c = $cards[$sid] ?? []; $L = $live ? sch_rec_get('ofcLed', $ses . '|' . $sid) : null;
        $heads = $L ? $L['heads'] : ofc_heads_from_card((array)($c['months'] ?? []));
        $heads = array_values(array_filter($heads, fn($h) => empty($h['na']) && in_array((int)$h['m'], $months, true)));
        $pr = []; foreach ((array)($c['profile'] ?? []) as $row) if (is_array($row)) { $pr[trim((string)($row[0] ?? ''))] = (string)($row[2] ?? ''); if (isset($row[3])) $pr[trim((string)$row[3])] = (string)($row[4] ?? ''); }
        $s = $students[$sid] ?? [];
        $tr = fn($h) => preg_match('/^(Transport|Hostel)$/i', trim($h['name']));
        $sum = fn($f, $k) => array_sum(array_map(fn($h) => $h[$k], array_filter($heads, $f)));
        $R = $rc[$sid] ?? [];
        $out[] = ['sid' => $sid, 'name' => (string)($c['name'] ?? $s['name'] ?? ''), 'father' => (string)($c['father'] ?? $s['father'] ?? ''), 'mobile' => preg_replace('/\D/', '', explode(',', (string)($c['mobile'] ?? $s['phone'] ?? ''))[0]),
            'cls' => (string)($c['cls'] ?? $s['cls'] ?? ''), 'section' => (string)($c['section'] ?? $s['section'] ?? ''), 'adm' => trim(explode('/', (string)($c['scholar'] ?? $s['adm'] ?? ''))[0]),
            'scheme' => (string)($c['scheme'] ?? ($L['scheme'] ?? '')), 'status' => trim(explode('|', (string)((($c['profile'] ?? [])[1] ?? [])[3] ?? ''))[1] ?? '') ?: (str_starts_with((string)$sid, 'old') ? 'Deactive' : 'Active'),
            'stuType' => $pr['Student Type'] ?? '', 'address' => $pr['Address'] ?? '', 'route' => trim(explode('-', $pr['Facilities'] ?? '', 2)[1] ?? ''),
            'oldDue' => $L ? ofc_old_due($ses, (string)$sid, $L) : ofc_paise($c['oldFee'] ?? ''), 'fee' => $sum(fn($h) => true, 'amt'), 'transport' => $sum($tr, 'amt'), 'paid' => $sum(fn($h) => true, 'paid'), 'transportPaid' => $sum($tr, 'paid'),
            'disc' => array_sum(array_column($R, 'discount')), 'recv' => array_sum(array_column($R, 'received')) - array_sum(array_column($R, 'late')), 'late' => array_sum(array_column($R, 'late')),
            'inst' => count($R), 'first' => $R ? $R[0]['date'] : '', 'last' => $R ? end($R)['date'] : ''];
    }
    usort($out, fn($a, $b) => [array_search($a['cls'], FR_CLS, true), $a['section'], strtolower($a['name'])] <=> [array_search($b['cls'], FR_CLS, true), $b['section'], strtolower($b['name'])]);
    return $out;
}
const FR_CLS = ['PG', 'LKG', 'UKG', '1st', '2nd', '3rd', '4th', '5th', '6th', '7th', '8th', '9th', '10th', '11th', '12th'];
function fr_dues(string $kind, string $ses, array $f): array {
    if ($ses === '') throw new InvalidArgumentException('Choose the session.');
    $R = 'fr_rs'; $upto = (int)($f['month'] ?? 3) ?: 3;
    $cs = array_values(array_filter(fr_cards($ses, $upto), fn($c) => ($f['cls'] === '' || $c['cls'] === $f['cls']) && ($f['sec'] === '' || $c['section'] === $f['sec'])));
    $today = new DateTimeImmutable('now', new DateTimeZone('Asia/Kolkata'));
    if ($kind === 'stmt') {
        $head = ['Sno', 'Admission As', 'Status', 'Student Type', 'Class', 'Section', 'Stream', 'Scholar No', 'Name', "Father's Name", 'Father Mobile No', 'Address', 'Scheme', 'Route', 'Old Due', 'Prev Transfer Fee', 'Cur Session Fee', 'Total Amount', 'Transfer Fee', 'Transport/H Amount', 'Fees. Amount', 'Inst. Count', 'First Inst. Date', 'Last Inst. Date', 'Oth Disc Amount', 'Discount Amount', 'Fee Adjust', 'Currently Received', 'Transport Due Amount', 'Fee Due Amount', 'Net Due Amount', 'Oth Fee Rec', 'Fee Refund'];
        $body = []; $t = array_fill(0, count($head), 0);
        foreach ($cs as $i => $c) {
            $tdue = $c['transport'] - $c['transportPaid']; $fdue = ($c['fee'] - $c['transport']) - ($c['paid'] - $c['transportPaid']) + $c['oldDue'];
            $v = [14 => $c['oldDue'], 15 => 0, 16 => $c['fee'], 17 => $c['fee'], 18 => 0, 19 => $c['transport'], 20 => $c['fee'] - $c['transport'], 24 => 0, 25 => $c['disc'], 26 => 0, 27 => $c['recv'], 28 => $tdue, 29 => $fdue, 30 => $tdue + $fdue, 31 => 0, 32 => 0];
            foreach ($v as $k => $x) $t[$k] += $x;
            $body[] = [(string)($i + 1), 'Day Scholar', $c['status'], $c['stuType'], $c['cls'], $c['section'], 'NA', $c['adm'], $c['name'], $c['father'], $c['mobile'], $c['address'], $c['scheme'], $c['route'] ?: 'No',
                $R($v[14]), '0', $R($v[16]), $R($v[17]), '0', $R($v[19]), $R($v[20]), (string)$c['inst'], fr_dmy($c['first']), fr_dmy($c['last']), '0', $R($v[25]), '0', $R($v[27]), $R($v[28]), $R($v[29]), $R($v[30]), '0', '0'];
        }
        $tot = array_fill(0, count($head), ''); $tot[13] = 'Total'; foreach ([14, 15, 16, 17, 18, 19, 20, 24, 25, 26, 27, 28, 29, 30, 31, 32] as $k) $tot[$k] = $R($t[$k]);
        return [[['Session: ' . fr_long_ses($ses) . ' Month ' . OFC_MONTHS[$upto]], $head], $body, $tot];
    }
    if ($kind === 'def2') {
        $head = ['Sno', 'Sch No', 'Name', 'Class', 'Sec', 'Mobile Number', 'Student Type', 'AC Type', 'Total Fee - Disc - Rec + Trns = Due', 'Late Fee', 'Payable Due', 'Total Payable', 'Cur FEE', 'PRV DUE', 'Late+ Add', 'Trns Fee', 'Apply Fees', 'Disc', 'Disc(%)', 'Recv Fees', 'Due Fees', 'Due %', 'Last Pay', 'GRP A/C STATUS'];
        $body = []; $T = ['apply' => 0, 'disc' => 0, 'recv' => 0, 'due' => 0];
        foreach ($cs as $i => $c) {
            $apply = $c['fee'] + $c['oldDue']; $due = $apply - $c['paid']; $recv = max(0, $c['paid'] - $c['disc']);
            $st = $c['paid'] === 0 ? 'Zero Paid' : ($due <= 0 ? 'Clear' : 'Payble Due');
            $pct = fn($x) => $apply ? rtrim(rtrim(number_format($x * 100 / $apply, 2, '.', ''), '0'), '.') . '%' : 'NAN%';
            $last = $c['last'] ? (string)(new DateTimeImmutable($c['last']))->diff($today)->days : '0';
            foreach (['apply' => $apply, 'disc' => $c['disc'], 'recv' => $recv, 'due' => $due] as $k => $x) $T[$k] += $x;
            $body[] = [(string)($i + 1), $c['adm'], $c['name'] . ' / ' . $c['father'], $c['cls'], $c['section'], $c['mobile'], $c['stuType'], 'Single', $R($apply) . ' - ' . $R($c['disc']) . ' - ' . $R($recv) . ' + =' . $R($due) . ' (' . $st . ')',
                '0', $R($due), $R($due), $R($c['fee']), $R($c['oldDue']), '0', '0', $R($apply), $R($c['disc']), $pct($c['disc']), $R($recv), $R($due), $pct($due), $last, $st];
        }
        $tot = array_fill(0, count($head), ''); $tot[2] = 'Total'; $tot[10] = $R($T['due']); $tot[11] = $R($T['due']); $tot[16] = $R($T['apply']); $tot[17] = $R($T['disc']); $tot[19] = $R($T['recv']); $tot[20] = $R($T['due']);
        return [[$head], array_values(array_filter($body, fn($r) => ($f['due'] ?? '') !== '1' || $r[23] !== 'Clear')), $tot];
    }
    // clswise: every class-section, like fees_classwiseollection.php
    $by = []; foreach ($cs as $c) { $k = $c['cls'] . ' ' . ($c['section'] ?: 'A'); $by[$k][] = $c; }
    uksort($by, fn($a, $b) => [array_search(explode(' ', $a)[0], FR_CLS, true), $a] <=> [array_search(explode(' ', $b)[0], FR_CLS, true), $b]);
    $body = []; $T = [0, 0, 0, 0, 0];
    foreach ($by as $k => $g) {
        $v = [array_sum(array_column($g, 'fee')), array_sum(array_column($g, 'disc')), array_sum(array_column($g, 'recv')), array_sum(array_column($g, 'oldDue'))]; $v[4] = $v[0] - $v[1] - $v[2] + $v[3];
        foreach ($v as $j => $x) $T[$j] += $x;
        $body[] = array_merge([$k], array_map($R, $v));
    }
    return [[['Class', 'Total Fee', 'Discount', 'Recv Amount', 'Old Due', 'Net Due']], $body, array_merge(['Total'], array_map($R, $T))];
}
