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
//   GET ?a=dues&ses=          each child's heads, old due and receipts for the dues reports
// ============================================================================
declare(strict_types=1);
require __DIR__ . '/inc.php';
require __DIR__ . '/registers_lib.php';
require __DIR__ . '/feecollect_lib.php';

$a = (string)($_GET['a'] ?? 'receipts');
try { $db = sch_db(); } catch (Throwable $e) { sch_fail('Server is not reachable right now. Please try again in a few minutes.', 503); }
$u = sch_auth(); $role = (string)$u['role'];
if (!in_array($role, ['principal', 'chairman', 'accountant'], true)) sch_fail('Fee reports are open for the Principal, Chairman and Accountant.', 403);

function fr_rs($v): float { $s = str_replace([',', '₹', ' '], '', (string)$v); return is_numeric($s) ? round((float)$s, 2) : 0.0; }
function fr_date(string $d): string {
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) return $d;
    if (preg_match('#^(\d{1,2})[-/](\d{1,2})[-/](\d{4})#', $d, $m)) return sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
    return '';
}

try {
switch ($a) {
case 'receipts': {
    $set = sch_rec_get('settings', 'main') ?? [];
    sch_json(['ok' => true, 'receipts' => fr_receipts($db), 'school' => (string)($set['school'] ?? ''), 'session' => (string)($set['session'] ?? '')]);
}
case 'dues': {
    // Fee Defaulter / Fee Statement / Zero Due / Fee Register: each child's month-wise heads for the session —
    // the Fee Receipt ledger once the session is started here, otherwise the copied old ERP fee card (as on its copy date).
    $ses = ofc_ses((string)($_GET['ses'] ?? '')); if ($ses === '') sch_fail('Choose the session.');
    $live = ofc_live($ses); $students = reg_students(); $cards = ofc_cards($ses);
    $info = []; foreach (reg_rows('studinfo') as $r) if (($r['student'] ?? '') !== '') $info[(string)$r['student']] = $r;
    $rc = []; foreach (fr_receipts($db) as $r) if (ofc_ses($r['session']) === $ses && $r['sid'] !== '') $rc[$r['sid']][] = $r;
    $out = []; $asOn = '';
    foreach ($students as $sid => $s) {
        if (!empty($s['inactive'])) continue;
        $L = $live ? sch_rec_get('ofcLed', $ses . '|' . $sid) : null; $c = $cards[$sid] ?? null;
        if (!$L && $c) { $L = ofc_ledger_from_card($ses, (string)$sid, $c, $s); $d = (string)($c['asOn'] ?? ''); if ($d !== '' && ($asOn === '' || strcmp($d, $asOn) < 0)) $asOn = $d; }
        if (!$L) continue;
        $i = $info[$sid] ?? []; $x = (array)($i['extra'] ?? []); $rs = $rc[$sid] ?? [];
        $heads = array_values(array_map(fn($h) => ['m' => (int)$h['m'], 'name' => (string)$h['name'], 'amt' => $h['amt'] / 100, 'paid' => $h['paid'] / 100], array_filter($L['heads'], fn($h) => empty($h['na']))));
        $ok = array_values(array_filter($rs, fn($r) => $r['status'] !== 'Cancelled'));
        $oldPaid = 0.0; foreach ($ok as $r) foreach ($r['lines'] as $l) if ($l['head'] === 'Old Due') $oldPaid += $l['amt'];
        $last = $ok ? $ok[count($ok) - 1] : null;
        $out[] = ['sid' => $sid, 'name' => $s['name'], 'adm' => $s['adm'], 'cls' => $s['cls'], 'section' => $s['section'] ?: (string)$L['section'], 'father' => $s['father'], 'mother' => (string)($s['mother'] ?? ''),
            'phone' => $s['phone'], 'fatherMobile' => (string)($i['fatherMobile'] ?? ''), 'motherMobile' => (string)($i['motherMobile'] ?? ''), 'address' => (string)(sch_rec_get('student', $sid)['address'] ?? ''),
            'status' => (string)($x['Student Status'] ?? 'Active'), 'sesStatus' => (string)($i['status'] ?? 'Active'), 'admAs' => (string)($i['admAs'] ?? ''), 'stuType' => (string)($i['stuType'] ?? ''), 'stream' => (string)($i['stream'] ?? ''),
            'scheme' => (string)($i['scheme'] ?? $L['scheme'] ?? ''), 'route' => (string)($i['route'] ?? ''), 'faclty' => (string)(sch_rec_get('student', $sid)['facilities'] ?? ($x['Facilites'] ?? '')), 'tag' => (string)($x['Tag'] ?? ''),
            'oldDue' => ofc_old_due($ses, (string)$sid, $L) / 100, 'oldPaid' => $oldPaid, 'transfer' => ((int)($L['transferFee'] ?? 0)) / 100, 'heads' => $heads,
            'received' => array_sum(array_column($ok, 'received')), 'discount' => array_sum(array_column($ok, 'discount')), 'late' => array_sum(array_column($ok, 'late')), 'count' => count($ok),
            'first' => $ok ? $ok[0]['date'] : '', 'lastDate' => $last['date'] ?? '', 'lastAmt' => $last['received'] ?? 0, 'lastMonths' => $last['months'] ?? '', 'followRemark' => $last['followRemark'] ?? '',
            'receipts' => array_map(fn($r) => ['no' => $r['no'], 'date' => $r['date'], 'received' => $r['received'], 'lines' => $r['lines']], $ok)];
    }
    $set = sch_rec_get('settings', 'main') ?? [];
    sch_json(['ok' => true, 'ses' => $ses, 'live' => (bool)$live, 'asOn' => $asOn, 'rows' => $out, 'school' => (string)($set['school'] ?? '')]);
}
default: sch_fail('Unknown action.', 404);
}
} catch (Throwable $e) {
    error_log('feereports_api: ' . $e->getMessage());
    sch_fail('Could not open the report. Please try again.', 500);
}

/** Every receipt, old ERP and new, in rupees, sorted by date. */
function fr_receipts(PDO $db): array {
    $students = reg_students();
    $info = []; foreach (reg_rows('studinfo') as $r) if (($r['student'] ?? '') !== '') $info[(string)$r['student']] = $r;
    $det = []; foreach (reg_rows('oldrcptdet') as $r) $det[(string)$r['session'] . '|' . (string)$r['receiptNo']] = $r;
    $stu = function (string $sid) use ($students, $info): array {
        $i = $info[$sid] ?? []; $s = $students[$sid] ?? [];
        return ['scheme' => (string)($i['scheme'] ?? ''), 'admAs' => (string)($i['admAs'] ?? ''), 'stuType' => (string)($i['stuType'] ?? ''), 'stream' => (string)($i['stream'] ?? ''), 'fatherMobile' => (string)($i['fatherMobile'] ?? ($s['phone'] ?? ''))];
    };
    // Fee Collection Report 1 as copied from the old ERP (Old ERP Reports) carries what the receipt register
    // did not: the last follow-up remark, the customer's bank and the bank the money went to.
    $extra = [];
    foreach ($db->query("SELECT d FROM sch_records WHERE k='regPage' AND r LIKE '%|report\\_fee.php' AND deleted=0") as $row) {
        $pg = json_decode((string)$row['d'], true); $bin = base64_decode((string)($pg['h'] ?? ''), true); $t = $bin === false ? null : json_decode((string)@gzdecode($bin), true);
        if (!is_array($t) || !$t) continue; $H = array_flip((array)$t[0]);
        foreach (['Session', 'Receipt No', 'Last Followup Remark', 'Customer Bank', 'Bank', 'Cheque No', 'Status'] as $need) if (!isset($H[$need])) continue 2;
        foreach (array_slice($t, 1) as $x) {
            $ses = preg_replace('/^(20\d\d)-20(\d\d)$/', '$1-$2', (string)($x[$H['Session']] ?? '')); $no = trim((string)($x[$H['Receipt No']] ?? ''), "' ");
            if ($ses === '' || $no === '' || isset($extra[$ses . '|' . $no])) continue;
            $extra[$ses . '|' . $no] = ['followRemark' => (string)$x[$H['Last Followup Remark']], 'custBank' => (string)$x[$H['Customer Bank']], 'bank' => (string)$x[$H['Bank']], 'cheque' => (string)$x[$H['Cheque No']], 'status' => (string)$x[$H['Status']]];
        }
    }
    $out = [];
    foreach (reg_rows('oldreceipt') as $r) {
        $ses = (string)$r['session']; $no = (string)$r['receiptNo']; $d = $det[$ses . '|' . $no] ?? null;
        $mode = trim(preg_replace('/\s+Cleared$/', '', (string)($r['mode'] ?? '')));
        $lines = [];
        foreach ((array)($d['lines'] ?? []) as $l) { $l = array_values((array)$l); if (count($l) >= 4) $lines[] = ['head' => trim((string)$l[1]), 'months' => trim((string)$l[2]), 'amt' => fr_rs($l[3])]; }
        $sid = (string)($r['student'] ?? '');
        $out[] = ['src' => 'old', 'session' => $ses, 'no' => $no, 'date' => fr_date((string)$r['date']), 'sid' => $sid, 'adm' => (string)($r['scholarNo'] ?? ''), 'name' => (string)($r['name'] ?? ''),
            'father' => (string)($r['father'] ?? ''), 'cls' => (string)($r['cls'] ?? ''), 'section' => (string)($r['section'] ?? ''), 'months' => (string)($r['months'] ?? ''),
            'total' => ((int)($r['total'] ?? 0)) / 100, 'discount' => ((int)($r['discount'] ?? 0)) / 100, 'late' => ((int)($r['lateFee'] ?? 0)) / 100, 'received' => ((int)($r['received'] ?? 0)) / 100,
            'mode' => $mode, 'bank' => (string)($r['bank'] ?? ''), 'custBank' => '', 'cheque' => trim((string)($r['cheque'] ?? ''), '- '), 'chequeDate' => '', 'status' => (string)($r['status'] ?? 'Cleared'),
            'user' => (string)($r['user'] ?? ''), 'remark' => (string)($r['remark'] ?? ''), 'followRemark' => '', 'lines' => $lines] + $stu($sid);
        if ($e = $extra[$ses . '|' . $no] ?? null) { $i = count($out) - 1; foreach ($e as $k => $v) if ($v !== '' || $k !== 'status') $out[$i][$k] = $v; }
    }
    foreach ($db->query("SELECT d FROM sch_records WHERE k='ofcRcpt' AND deleted=0") as $row) {
        $r = json_decode((string)$row['d'], true); if (!is_array($r)) continue;
        $lines = []; foreach ((array)($r['lines'] ?? []) as $l) $lines[] = ['head' => (string)$l[1], 'months' => (string)$l[2], 'amt' => ((int)$l[3]) / 100];
        $sid = (string)$r['sid'];
        $out[] = ['src' => 'new', 'session' => (string)$r['session'], 'no' => (string)$r['no'], 'date' => (string)$r['date'], 'sid' => $sid, 'adm' => (string)($r['adm'] ?? ''), 'name' => (string)($r['name'] ?? ''),
            'father' => (string)($r['father'] ?? ''), 'cls' => (string)($r['cls'] ?? ''), 'section' => (string)($r['section'] ?? ''), 'months' => implode(',', array_unique(array_merge(...array_map(fn($l) => explode(',', $l['months']), $lines ?: [['months' => '']])))),
            'total' => ((int)$r['total']) / 100, 'discount' => ((int)$r['discount']) / 100, 'late' => ((int)$r['late']) / 100, 'received' => ((int)$r['received']) / 100,
            'mode' => (string)$r['mode'], 'bank' => (string)($r['depositBank'] ?? ''), 'custBank' => (string)($r['bank'] ?? ''), 'cheque' => (string)($r['cheque'] ?? ''), 'chequeDate' => (string)($r['chequeDate'] ?? ''),
            'status' => (string)($r['status'] ?? 'Cleared'), 'user' => (string)($r['by'] ?? ''), 'remark' => (string)($r['remark'] ?? ''), 'followRemark' => (string)($r['followupRemark'] ?? ''), 'lines' => $lines] + $stu($sid);
    }
    usort($out, fn($x, $y) => [$x['date'], $x['session'], (int)preg_replace('/\D/', '', $x['no'])] <=> [$y['date'], $y['session'], (int)preg_replace('/\D/', '', $y['no'])]);
    return $out;
}
