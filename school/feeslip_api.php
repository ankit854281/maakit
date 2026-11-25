<?php
// ============================================================================
//  Fee Reminder Slip (owner, 25 Nov 2026: "fee reminder me se bacho ko message ke slip dena hai jo baaki hai,
//  sabhi parents, aur jinko de raha hu unka list bhi chahiye — kitne jan ko diya gaya").
//  Page: feeslip.php / feeslip.js.
//   GET  ?a=list                   every child with fee due till now (same figures as Fee Calling — ff_snapshot),
//                                  plus when a slip was last given to each child
//   POST ?a=give {sids:[], due:{sid:paise}, mode:'print'|'whatsapp'}   mark slips as given today
//   GET  ?a=given&from=&to=        slips given in a date range (who, when, due on the slip, by whom)
//  Records: acctSlip  <session>|<date>|<sid>  ('acct' prefix — never in the normal load). Read-only on fees.
//  Open for: Principal, Chairman, Manager, Accountant, Reception and the Teacher App fee callers (Rati / Palak Ma'am).
// ============================================================================
declare(strict_types=1);
require __DIR__ . '/inc.php';
require_once __DIR__ . '/followup_lib.php';

$a = (string)($_GET['a'] ?? 'list');
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
try { $db = sch_db(); } catch (Throwable $e) { sch_fail('Server is not reachable right now. Please try again in a few minutes.', 503); }
$u = sch_auth(); $role = (string)$u['role']; $uid = sch_uid_of($u);
$ok = in_array($role, ['principal', 'chairman', 'manager', 'accountant', 'reception'], true);
if (!$ok && $role === 'teacher') {
    $tid = (string)($u['tid'] ?? ''); $L = (sch_rec_get('settings', 'main') ?? [])['feeCallers'] ?? null;
    $ok = is_array($L) ? in_array($tid, $L, true) : sch_default_fee_caller($tid);
}
if (!$ok) sch_fail('Fee Reminder Slips are open for the Principal, Chairman, Manager, office and the fee callers.', 403);
$in = json_decode((string)file_get_contents('php://input'), true) ?: [];
$ses = ff_session();
$settings = sch_rec_get('settings', 'main') ?? [];

$slips = function (?string $from = null, ?string $to = null) use ($db, $ses): array {
    $q = $db->prepare("SELECT r, d FROM sch_records WHERE k='acctSlip' AND deleted=0 AND r LIKE ?"); $q->execute([$ses . '|%']); $o = [];
    foreach ($q as $row) { $d = json_decode((string)$row['d'], true); if (!is_array($d)) continue;
        if ($from !== null && ((string)$d['date'] < $from || (string)$d['date'] > $to)) continue; $o[] = $d; }
    return $o;
};

if ($a === 'list' && $method === 'GET') {
    $snap = ff_snapshot($db, $ses); $last = []; $cnt = [];
    foreach ($slips() as $d) { $s = (string)$d['sid']; $cnt[$s] = ($cnt[$s] ?? 0) + 1;
        if (!isset($last[$s]) || strcmp((string)$d['date'], $last[$s]['date']) > 0) $last[$s] = ['date' => (string)$d['date']]; }
    $rows = [];
    foreach ($snap as $c) {
        if ((int)$c['due'] <= 0) continue;
        $sid = (string)$c['sid']; $st = sch_rec_get('student', $sid) ?? [];
        if (!empty($st['inactive'])) continue;
        $rows[] = ['sid' => $sid, 'name' => (string)$c['name'], 'cls' => (string)$c['cls'], 'section' => (string)$c['section'], 'father' => (string)$c['father'],
            'phone' => (string)$c['phone'], 'adm' => (string)($st['adm'] ?? $st['admNo'] ?? ''), 'due' => (int)$c['due'], 'oldDue' => (int)$c['oldDue'], 'tag' => (string)$c['tag'],
            'last' => $last[$sid]['date'] ?? '', 'times' => $cnt[$sid] ?? 0];
    }
    sch_json(['ok' => true, 'session' => $ses, 'today' => date('Y-m-d'), 'rows' => $rows, 'me' => (string)$u['label'],
        'school' => (string)($settings['school'] ?? 'SN Mishra Singapore International School'), 'phone' => (string)($settings['schoolPhone'] ?? $settings['officePhone'] ?? '')]);
}
if ($a === 'give' && $method === 'POST') {
    $sids = array_values(array_unique(array_filter(array_map('strval', (array)($in['sids'] ?? [])), fn($s) => preg_match('/^[A-Za-z0-9_\-]{1,40}$/', $s))));
    if (!$sids) sch_fail('Choose the children first.');
    if (count($sids) > 1500) sch_fail('Too many at once.');
    $mode = in_array(($in['mode'] ?? ''), ['print', 'whatsapp'], true) ? (string)$in['mode'] : 'print';
    $dues = (array)($in['due'] ?? []); $date = date('Y-m-d'); $now = sch_now_ms(); $n = 0;
    foreach ($sids as $sid) { $st = sch_rec_get('student', $sid); if (!$st) continue;
        sch_rec_put('acctSlip', "$ses|$date|$sid", ['sid' => $sid, 'date' => $date, 'at' => $now, 'due' => max(0, (int)($dues[$sid] ?? 0)), 'mode' => $mode,
            'by' => (string)$u['label'], 'uid' => $uid, 'name' => (string)($st['name'] ?? ''), 'cls' => (string)($st['cls'] ?? ''), 'section' => (string)($st['section'] ?? ''),
            'father' => (string)($st['father'] ?? ''), 'phone' => sch_phone10((string)($st['phone'] ?? ''))], $uid); $n++; }
    sch_log($u, 'Fee Reminder Slip', "$n slip" . ($n === 1 ? '' : 's') . ' given (' . $mode . ')');
    sch_json(['ok' => true, 'given' => $n, 'date' => $date]);
}
if ($a === 'given' && $method === 'GET') {
    $re = '/^\d{4}-\d{2}-\d{2}$/';
    $to = (string)($_GET['to'] ?? date('Y-m-d')); if (!preg_match($re, $to)) $to = date('Y-m-d');
    $from = (string)($_GET['from'] ?? $to); if (!preg_match($re, $from)) $from = $to;
    if ($from > $to) [$from, $to] = [$to, $from];
    $L = $slips($from, $to); usort($L, fn($x, $y) => ($y['at'] ?? 0) <=> ($x['at'] ?? 0));
    sch_json(['ok' => true, 'from' => $from, 'to' => $to, 'rows' => $L, 'school' => (string)($settings['school'] ?? 'SN Mishra Singapore International School')]);
}
sch_fail('Unknown request.', 404);
