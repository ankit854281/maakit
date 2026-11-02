<?php
// ============================================================================
//  Fee Control Room (2 Nov 2026) — one page for the Chairman, Principal,
//  Accountant and Reception: what came in, what is still due, who to call,
//  what they promised. Read-only here; calls are saved by office_api.php?a=call
//  (the same Call Log the Dues Calling screen used), fees by Fee Receipt.
//
//   GET ?a=data&ses=2026-27
//     dues     — each child's fee card for the session (feereports_lib.php fr_dues)
//     receipts — every receipt of both ERPs, slim (not for Reception)
//     calls    — the fee call log (acctCall)
//     tags     — each child's tag: the old ERP Tag, or the one set here (fdTag), with an optional fixed
//                monthly amount ("FD" / fixed plan: the child pays only this much a month)
//   POST ?a=setTag {sid, tag, fixed, note}   Principal
//   POST ?a=msgLog {sids[], kind, text}      a fee message was sent to these parents (WhatsApp, from the app) — kept as a log
//     msgs     — that log, newest first (in ?a=data)
//
//  Reception sees dues and calls (it makes the calls) but not the money taken.
// ============================================================================
declare(strict_types=1);
require __DIR__ . '/inc.php';
require __DIR__ . '/registers_lib.php';
require __DIR__ . '/feecollect_lib.php';
require __DIR__ . '/feereports_lib.php';

$a = (string)($_GET['a'] ?? 'data');
try { $db = sch_db(); } catch (Throwable $e) { sch_fail('Server is not reachable right now. Please try again in a few minutes.', 503); }
$u = sch_auth(); $role = (string)$u['role'];
if (!in_array($role, ['principal', 'chairman', 'accountant', 'reception'], true)) sch_fail('Fee Control Room is open for the Principal, Chairman, Accountant and Reception.', 403);
$money = $role !== 'reception';

/** "Generated()Company()" -> ['Company', 'Generated']. Fee tags win by how specific they are. */
function fd_split_tag(string $raw): array {
    preg_match_all('/([^()]+)\(\s*\)/', $raw, $m); $parts = array_values(array_filter(array_map('trim', $m[1] ?: [trim($raw)])));
    $isPen = fn($t) => (bool)preg_match('/\bPEN\b|^Generated$/i', $t);
    $fee = array_values(array_filter($parts, fn($t) => !$isPen($t))); $pen = array_values(array_filter($parts, $isPen));
    $norm = fn($t) => preg_match('/^one\s*time\s*payment$/i', $t) ? 'One Time Payment' : (preg_match('/^transport\s*\+\s*fee$/i', $t) ? 'Transport + Fee' : $t);
    $fee = array_map($norm, $fee);
    $order = ['Company', 'FD', 'Plan', 'One Time Payment', 'Transport + Fee', 'Fee'];
    usort($fee, fn($x, $y) => (array_search($x, $order, true) === false ? 99 : array_search($x, $order, true)) <=> (array_search($y, $order, true) === false ? 99 : array_search($y, $order, true)));
    return [$fee[0] ?? '', implode(', ', $pen)];
}

try {
switch ($a) {
case 'data': {
    $set = sch_rec_get('settings', 'main') ?? [];
    $cur = ofc_ses((string)($set['session'] ?? '')) ?: (date('n') >= 4 ? date('Y') . '-' . date('y', strtotime('+1 year')) : (date('Y') - 1) . '-' . date('y'));
    $ses = ofc_ses((string)($_GET['ses'] ?? '')) ?: $cur;
    $all = fr_receipts($db);
    $sessions = array_values(array_unique(array_merge([$cur], array_map(fn($r) => ofc_ses($r['session']), $all), array_keys(ofc_cfg()['sessions'] ?? []))));
    $sessions = array_values(array_filter($sessions)); rsort($sessions);
    $dues = fr_dues($db, $ses);
    $tagSet = [];
    foreach ($db->query("SELECT r, d FROM sch_records WHERE k='fdTag' AND deleted=0") as $row) { $t = json_decode((string)$row['d'], true); if (is_array($t)) $tagSet[(string)$row['r']] = $t; }
    foreach ($dues['rows'] as &$r) {
        unset($r['receipts']);   // the dashboard needs totals, not every line
        $t = $tagSet[$r['sid']] ?? null;
        // Old ERP Tag Mgmt joins several tags as "Generated()Company()": PEN tags (UDISE PEN status) and fee tags.
        // The fee tag is the one that matters here; PEN tags are kept apart.
        [$feeTag, $pen] = fd_split_tag((string)$r['tag']);
        $r['oldTag'] = $feeTag; $r['penTag'] = $pen; $r['tag'] = $t ? (string)$t['tag'] : $feeTag;
        $r['fixed'] = $t ? ((int)($t['fixed'] ?? 0)) / 100 : 0; $r['tagNote'] = $t ? (string)($t['note'] ?? '') : '';
    } unset($r);
    $rc = [];
    if ($money) foreach ($all as $r) $rc[] = ['session' => ofc_ses($r['session']), 'no' => $r['no'], 'date' => $r['date'], 'sid' => $r['sid'], 'name' => $r['name'], 'cls' => $r['cls'], 'section' => $r['section'],
        'months' => $r['months'], 'total' => $r['total'], 'received' => $r['received'], 'discount' => $r['discount'], 'late' => $r['late'], 'mode' => $r['mode'], 'status' => $r['status'], 'user' => $r['user'], 'src' => $r['src'],
        // Transport part of the money, from the printed head lines; 'split' false = the old receipt's lines were not copied
        'tr' => round(array_sum(array_map(fn($l) => preg_match('/transport/i', $l['head']) ? $l['amt'] : 0, $r['lines'])), 2), 'split' => (bool)$r['lines']];
    $calls = [];
    foreach ($db->query("SELECT d FROM sch_records WHERE k='acctCall' AND deleted=0") as $row) { $c = json_decode((string)$row['d'], true); if (is_array($c)) $calls[] = $c; }
    usort($calls, fn($x, $y) => ($y['at'] ?? 0) <=> ($x['at'] ?? 0));
    $msgs = [];
    foreach ($db->query("SELECT d FROM sch_records WHERE k='fdMsg' AND deleted=0") as $row) { $m = json_decode((string)$row['d'], true); if (is_array($m)) $msgs[] = $m; }
    usort($msgs, fn($x, $y) => ($y['at'] ?? 0) <=> ($x['at'] ?? 0));
    sch_json(['ok' => true, 'ses' => $ses, 'sessions' => $sessions, 'today' => date('Y-m-d'), 'role' => $role, 'money' => $money,
        'canCall' => in_array($role, ['principal', 'accountant', 'reception'], true), 'outcomes' => ['Promised to pay', 'Not answered', 'Switched off', 'Call back later', 'Will visit school', 'Already paid', 'Wrong number'],
        'school' => (string)($set['school'] ?? ''), 'live' => $dues['live'], 'asOn' => $dues['asOn'], 'dues' => $dues['rows'], 'receipts' => $rc, 'calls' => array_slice($calls, 0, 4000), 'msgs' => array_slice($msgs, 0, 3000)]);
}
case 'setTag': {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') sch_fail('POST only', 405);
    if ($role !== 'principal') sch_fail('Only the Principal can set a tag.', 403);
    $in = json_decode((string)file_get_contents('php://input', false, null, 0, 20000), true); if (!is_array($in)) sch_fail('Bad request.');
    $sid = (string)($in['sid'] ?? ''); $s = sch_rec_get('student', $sid); if (!$s) sch_fail('Student not found.');
    $clean = fn($v, $n) => mb_substr(trim(preg_replace('/[\x00-\x1f\x7f]/u', ' ', (string)$v)), 0, $n);
    $tag = $clean($in['tag'] ?? '', 30); $note = $clean($in['note'] ?? '', 120);
    $fx = str_replace([',', ' ', '₹'], '', (string)($in['fixed'] ?? '0')); if ($fx === '') $fx = '0';
    if (!is_numeric($fx) || (float)$fx < 0 || (float)$fx > 100000) sch_fail('Fixed amount a month must be a number (0 = no fixed plan).');
    $rec = ['tag' => $tag, 'fixed' => (int)round((float)$fx * 100), 'note' => $note, 'by' => $u['label'] ?? 'Principal', 'at' => sch_now_ms()];
    sch_rec_put('fdTag', $sid, $rec, sch_uid_of($u));
    sch_log($u, 'Fee Tag', 'Tag of ' . ($s['name'] ?? $sid) . ': ' . ($tag !== '' ? $tag : '(none)') . ($rec['fixed'] ? ' · fixed ₹' . number_format($rec['fixed'] / 100, 2) . '/month' : ''));
    sch_json(['ok' => true, 'tag' => $rec]);
}
case 'msgLog': {
    // The app opens WhatsApp with the message for each parent; this keeps a record of who was sent what, and when.
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') sch_fail('POST only', 405);
    $in = json_decode((string)file_get_contents('php://input', false, null, 0, 200000), true); if (!is_array($in)) sch_fail('Bad request.');
    $sids = array_values(array_unique(array_filter(array_map('strval', (array)($in['sids'] ?? [])), fn($x) => $x !== '' && strlen($x) <= 60)));
    if (!$sids || count($sids) > 600) sch_fail('Choose the students.');
    $kind = mb_substr(trim(preg_replace('/[\x00-\x1f\x7f]/u', ' ', (string)($in['kind'] ?? 'Fee reminder'))), 0, 40);
    $text = mb_substr(trim((string)($in['text'] ?? '')), 0, 1000);
    $id = bin2hex(random_bytes(8));
    $rec = ['id' => $id, 'sids' => $sids, 'kind' => $kind, 'text' => $text, 'by' => $u['label'] ?? $role, 'at' => sch_now_ms(), 'date' => date('Y-m-d')];
    sch_rec_put('fdMsg', $id, $rec, sch_uid_of($u));
    sch_log($u, 'Fee Message', $kind . ' sent to ' . count($sids) . ' parent(s)');
    sch_json(['ok' => true, 'msg' => $rec]);
}
default: sch_fail('Unknown action.', 404);
}
} catch (Throwable $e) {
    error_log('feedash_api: ' . $e->getMessage());
    sch_fail('Could not open the Fee Control Room. Please try again.', 500);
}
