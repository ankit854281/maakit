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
//   POST ?a=callNote {sid, outcome, with, note, promise, amount}   Chairman/Principal spoke to a parent —
//                    saved in the same Call Log (acctCall) with the time, who spoke and what was discussed
//   GET  ?a=school   Principal/Chairman: attendance, exams + marks, syllabus, teachers, leaves,
//                    old ERP report cards (oldmarks) and the staff register (no ID/bank fields)
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
case 'callNote': {
    // Chairman / Principal spoke to a parent themselves: when, with whom, what was said. Saved in the same
    // Call Log (acctCall) as the office's calls, so every screen shows one history.
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') sch_fail('POST only', 405);
    $in = json_decode((string)file_get_contents('php://input', false, null, 0, 20000), true); if (!is_array($in)) sch_fail('Bad request.');
    $sid = (string)($in['sid'] ?? ''); $s = sch_rec_get('student', $sid); if (!$s) sch_fail('Student not found.');
    $clean = fn($v, $n) => mb_substr(trim(preg_replace('/[\x00-\x1f\x7f]/u', ' ', (string)$v)), 0, $n);
    $outs = ['Talked to parent', 'Promised to pay', 'Not answered', 'Switched off', 'Call back later', 'Will visit school', 'Already paid', 'Wrong number'];
    $out = (string)($in['outcome'] ?? ''); if (!in_array($out, $outs, true)) sch_fail('Choose what happened on the call.');
    $note = $clean($in['note'] ?? '', 500);
    if ($note === '' && !in_array($out, ['Not answered', 'Switched off', 'Wrong number'], true)) sch_fail('Write what was discussed.');
    $with = $clean($in['with'] ?? '', 30);
    $promise = (string)($in['promise'] ?? '');
    if ($promise !== '' && (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $promise) || $promise < date('Y-m-d') || $promise > date('Y-m-d', time() + 120 * 86400))) sch_fail('The promised date must be from today to the next 4 months.');
    if (in_array($out, ['Promised to pay', 'Call back later', 'Will visit school'], true) && $promise === '') sch_fail('Choose the date they gave.');
    $amt = str_replace([',', ' ', '₹'], '', (string)($in['amount'] ?? '')); if ($amt === '') $amt = '0';
    if (!is_numeric($amt) || (float)$amt < 0 || (float)$amt > 1000000) sch_fail('Amount must be a number.');
    $fam = sch_phone10((string)($s['phone'] ?? '')); $sids = [$sid];
    if ($fam !== '') foreach ($db->query("SELECT r, d FROM sch_records WHERE k='student' AND deleted=0") as $row) {
        $x = json_decode((string)$row['d'], true); if ((string)$row['r'] !== $sid && is_array($x) && sch_phone10((string)($x['phone'] ?? '')) === $fam) $sids[] = (string)$row['r'];
    }
    $set = sch_rec_get('settings', 'main') ?? [];
    $ses = ofc_ses((string)($set['session'] ?? '')) ?: (date('n') >= 4 ? date('Y') . '-' . date('y', strtotime('+1 year')) : (date('Y') - 1) . '-' . date('y'));
    $id = bin2hex(random_bytes(8)); $now = sch_now_ms();
    // When the call was made: the app notes the moment "Call" was tapped (up to 12 hours back), else now.
    $started = (int)($in['startedAt'] ?? 0); if ($started < $now - 12 * 3600 * 1000 || $started > $now) $started = $now;
    $c = ['id' => $id, 'sid' => $sid, 'startedAt' => $started, 'sids' => $sids, 'fam' => $fam ?: 'sid:' . $sid, 'session' => $ses, 'outcome' => $out, 'promise' => $promise,
          'amount' => (int)round((float)$amt * 100), 'note' => $note, 'with' => $with, 'by' => $u['label'] ?? $role, 'uid' => sch_uid_of($u),
          'at' => $now, 'date' => date('Y-m-d'), 'completedAt' => $now, 'completedDate' => date('Y-m-d'), 'source' => 'chairman-app'];
    sch_rec_put('acctCall', $id, $c, sch_uid_of($u));
    sch_log($u, 'Fee Call', 'Spoke to the family of ' . ($s['name'] ?? $sid) . ': ' . $out . ($promise !== '' ? ' — ' . $promise : ''));
    sch_json(['ok' => true, 'call' => $c]);
}
case 'school': {
    // Everything that is not fees, for the Chairman App's School tab: attendance (students and staff),
    // exams and marks, syllabus, teachers, the old ERP report cards and the staff register (safe fields only).
    if (!in_array($role, ['principal', 'chairman'], true)) sch_fail('Only the Principal and Chairman can open this.', 403);
    $set = sch_rec_get('settings', 'main') ?? [];
    $start = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($set['sessionStart'] ?? '')) ? (string)$set['sessionStart'] : (date('n') >= 4 ? date('Y') : date('Y') - 1) . '-04-01';
    $rows = function (string $k) use ($db): array { $q = $db->prepare('SELECT r, d FROM sch_records WHERE k=? AND deleted=0'); $q->execute([$k]); $o = [];
        foreach ($q as $x) { $d = json_decode((string)$x['d'], true); if (is_array($d)) $o[(string)$x['r']] = $d; } return $o; };
    $teachers = []; foreach ($rows('teacher') as $t => $d) $teachers[$t] = ['name' => (string)($d['name'] ?? $t), 'role' => (string)($d['role'] ?? ''), 'phone' => (string)($d['phone'] ?? '')];
    $info = []; foreach (reg_rows('studinfo') as $r) if (($r['student'] ?? '') !== '') $info[(string)$r['student']] = $r;
    $pho = []; foreach ($db->query("SELECT r FROM sch_records WHERE k='phoStu' AND deleted=0") as $x) $pho[(string)$x['r']] = true;
    $students = []; foreach ($rows('student') as $id => $d) { if (!empty($d['sample'])) continue; $i = $info[$id] ?? [];
        $students[] = ['id' => $id, 'name' => (string)($d['name'] ?? ''), 'cls' => (string)($d['cls'] ?? ''), 'section' => (string)($d['section'] ?? ''), 'father' => (string)($d['father'] ?? ''), 'mother' => (string)($d['mother'] ?? ''),
            'phone' => (string)($d['phone'] ?? ''), 'adm' => (string)($d['adm'] ?? ''), 'dob' => (string)($d['dob'] ?? ''), 'gender' => (string)($i['gender'] ?? ''), 'route' => (string)($i['route'] ?? ''),
            'doa' => (string)($i['doa'] ?? ''), 'stuType' => (string)($i['stuType'] ?? ''), 'photo' => isset($pho[$id])]; }
    // Inactive / left children: only for the Chairman's search, never counted in classes, attendance or fees.
    $inactive = []; foreach (reg_students() as $id => $d) if (!empty($d['inactive'])) $inactive[] = ['id' => $id, 'name' => $d['name'], 'cls' => $d['cls'], 'section' => $d['section'], 'father' => $d['father'], 'phone' => $d['phone'], 'adm' => $d['adm'], 'photo' => isset($pho[$id])];
    $cal = []; foreach ($rows('cal') as $d) $cal[] = ['date' => (string)($d['date'] ?? ''), 'to' => (string)($d['to'] ?? ''), 'title' => mb_substr((string)($d['title'] ?? ''), 0, 120), 'type' => (string)($d['type'] ?? '')];
    $att = []; foreach ($rows('studAtt') as $r => $d) { [$dt, $c] = array_pad(explode('|', $r, 2), 2, ''); if ($dt >= $start) $att[$dt][$c] = array_values(array_map('strval', (array)($d['absent'] ?? []))); }
    $staff = []; foreach ($rows('staffAtt') as $dt => $d) if ($dt >= $start) $staff[$dt] = array_values(array_map('strval', (array)($d['absent'] ?? [])));
    $syl = []; foreach ($rows('syl') as $k => $d) $syl[$k] = ['chapters' => array_values(array_map(fn($ch) => ['n' => (string)($ch['n'] ?? ''), 'done' => $ch['done'] ?? null], (array)($d['chapters'] ?? []))), 'targetDate' => (string)($d['targetDate'] ?? '')];
    $marks = []; foreach ($rows('marks') as $r => $d) { $i = strpos($r, '|'); if ($i === false) continue; $marks[substr($r, 0, $i)][substr($r, $i + 1)] = ['max' => $d['max'] ?? 0, 'm' => (object)($d['m'] ?? [])]; }
    $leaves = []; foreach ($rows('leave') as $d) if ((string)($d['from'] ?? '') >= $start || ($d['status'] ?? '') === 'Pending') $leaves[] = ['tid' => (string)($d['tid'] ?? ''), 'from' => (string)($d['from'] ?? ''), 'to' => (string)($d['to'] ?? ''), 'status' => (string)($d['status'] ?? ''), 'reason' => mb_substr((string)($d['reason'] ?? ''), 0, 120)];
    // Old ERP result sheets: per subject only the "Total" columns (a report card line), not every sub-test.
    $old = [];
    foreach (reg_rows('oldmarks') as $r) {
        $subs = [];
        foreach ((array)($r['marks'] ?? []) as $sb => $cols) { $cols = (array)$cols; $tot = array_filter($cols, fn($k) => stripos((string)$k, 'total') !== false, ARRAY_FILTER_USE_KEY); $subs[(string)$sb] = $tot ?: $cols; }
        $old[] = ['sid' => (string)($r['student'] ?? ''), 'name' => (string)($r['name'] ?? ''), 'ses' => (string)($r['session'] ?? ''), 'exam' => (string)($r['exam'] ?? ''), 'cls' => (string)($r['cls'] ?? ''), 'roll' => (string)($r['roll'] ?? ''),
            'total' => (string)($r['total'] ?? ''), 'pct' => is_numeric($r['percent'] ?? null) ? (float)$r['percent'] : null, 'grade' => (string)($r['grade'] ?? ''), 'rank' => (string)($r['rank'] ?? ''), 'att' => (string)($r['attendance'] ?? ''), 'subs' => $subs];
    }
    $emp = []; foreach (reg_rows('employee') as $r) $emp[] = ['name' => (string)($r['name'] ?? ''), 'designation' => (string)($r['designation'] ?? ''), 'department' => (string)($r['department'] ?? ''), 'category' => (string)($r['category'] ?? ''),
        'mobile' => (string)($r['mobile'] ?? ''), 'doj' => (string)($r['doj'] ?? ''), 'qualification' => (string)($r['qualification'] ?? ''), 'experience' => (string)($r['experience'] ?? ''), 'status' => (string)($r['status'] ?? 'Active')];
    sch_json(['ok' => true, 'today' => date('Y-m-d'), 'start' => $start, 'session' => (string)($set['session'] ?? ''), 'cutoff' => (string)($set['attendanceCutoff'] ?? '09:30'),
        'classes' => array_values((array)(sch_rec_get('classes', 'main') ?? [])), 'teachers' => (object)$teachers, 'alloc' => array_values((array)(sch_rec_get('alloc', 'main') ?? [])),
        'exams' => array_values((array)(sch_rec_get('exams', 'main') ?? [])), 'students' => $students, 'inactive' => $inactive, 'att' => (object)$att, 'staffAtt' => (object)$staff, 'syl' => (object)$syl,
        'marks' => (object)$marks, 'leaves' => $leaves, 'old' => $old, 'employees' => $emp, 'cal' => $cal]);
}
default: sch_fail('Unknown action.', 404);
}
} catch (Throwable $e) {
    error_log('feedash_api: ' . $e->getMessage());
    sch_fail('Could not open the Fee Control Room. Please try again.', 500);
}
