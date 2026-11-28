<?php
// ============================================================================
//  Duty desk + arrangement slip + "Problem batao" (owner, 27 Nov 2026):
//  "arrangement bell na samajh aane ke wajah se random class chal raha hai … sport teacher free rahte hain to unko other work
//   me involve kar sakte hain — attendance lagana jo absent hain teacher unke class ka, arrangement bell ka, jo bachche absent
//   hain unko message share karna, homework nahi kar paa rahe unko message share karna"; "features find na kar pana yaa bug".
//
//   GET  ?a=reached&date=            every arrangement bell marked "reached the class" that day   (any staff login)
//   POST ?a=reach {date,bell,cls,on}  the substitute (or the Duty teacher / Principal) marks "I am in the class" — today only
//   GET  ?a=hwnot&date=              Duty teacher / Principal / Chairman: children marked homework Not done / Half that day
//                                    (from the subject teachers' homework checks, kind tchChk) + messages already sent
//   POST ?a=sent {date,type,sid,subj,on}  Duty teacher / Principal: a parent message was sent on WhatsApp (type abs | hw)
//   POST ?a=bug {screen,msg,err,ver}  any staff login: "🐞 Problem batao" — what went wrong, on which screen
//   GET  ?a=bugs                     Principal / Chairman: the last 60 days' problem reports
//   GET  ?a=copy&cls=&subj=         copy (notebook) checking by chapter of one class + subject (teachers of that class, Principal,
//                                    Chairman, Duty teacher); POST {cls,subj,ch,rows:{sid:'ok'|'inc'}} = the subject teacher / Principal
//                                    (kind tchCopy r = cls|subj|chapter name) — owner, 27 Nov 2026: "syllabus add hai to copy chapter
//                                    ke according waha par entry karne ko de — checked complete or not"
//   GET  ?a=sentlist&date=           parent messages marked sent that day (any staff; used by Report & Message screens)
//   GET  ?a=hwsum                    homework checks of MY classes (teacher: classes he teaches; Principal/Chairman: all):
//                                    last check date per class|subject, 30-day "not done" counts per child, today's checks
//   POST ?a=bugdone {id,on}           Principal: mark a report as fixed / open again
//   GET  ?a=hwreg&from=&to=          homework register: checks with each child's done / half / not + diary homework not checked yet
//                                    (Principal, Chairman, Duty teacher, Rati Ma'am: every class; other teachers: own classes)
//
//  Kinds (all start with "tch" so they never reach the legacy load/sync):
//   tchReach  r = date|bell|cls     {tid, by, at}
//   tchSent   r = date|type|sid|subj {by, at}
//   tchBug    r = id                {at, by, role, tid, screen, msg, err, ver, done, doneBy, doneAt}
// ============================================================================
declare(strict_types=1);
require __DIR__ . '/inc.php';

$a = (string)($_GET['a'] ?? '');
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
try { $db = sch_db(); } catch (Throwable $e) { sch_fail('Server is not reachable right now. Please try again in a few minutes.', 503); }

function du_txt($v, int $max): string { return mb_substr(trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', (string)$v)), 0, $max); }
function du_date($v, bool $todayOnly = false): string {
    $d = (string)$v;
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) || $d > date('Y-m-d')) sch_fail('Choose a date (not in the future).');
    if ($todayOnly && $d !== date('Y-m-d')) sch_fail('This can be marked only for today.');
    return $d;
}
function du_classes(): array { return array_values(array_filter(array_map(fn($c) => (string)($c['c'] ?? ''), (array)(sch_rec_get('classes', 'main') ?? [])))); }
function du_rows(PDO $db, string $kind, string $prefix): array {
    $q = $db->prepare('SELECT r, d FROM sch_records WHERE k=? AND deleted=0 AND r LIKE ?'); $q->execute([$kind, $prefix . '%']); $o = [];
    foreach ($q as $row) { $d = json_decode((string)$row['d'], true); if (is_array($d)) $o[(string)$row['r']] = $d; }
    return $o;
}

try {
$u = sch_auth();
$role = (string)$u['role']; $tid = (string)($u['tid'] ?? ''); $uid = sch_uid_of($u);
if (!in_array($role, ['teacher', 'principal', 'chairman'], true)) sch_fail('This is for school staff.', 403);
$in = [];
if ($method === 'POST') {
    $in = json_decode(file_get_contents('php://input', false, null, 0, 60000) ?: '', true);
    if (!is_array($in)) sch_fail('Bad request.');
    if ($role === 'chairman') sch_fail('The Chairman login can only view.', 403);
}
$duty = $role === 'principal' || ($role === 'teacher' && sch_is_duty($tid));

switch ($a) {

case 'reached': {
    if ($method !== 'GET') sch_fail('GET only', 405);
    $date = du_date($_GET['date'] ?? date('Y-m-d')); $out = [];
    foreach (du_rows($db, 'tchReach', $date . '|') as $r => $d) $out[substr($r, 11)] = ['tid' => (string)($d['tid'] ?? ''), 'by' => (string)($d['by'] ?? ''), 'at' => (int)($d['at'] ?? 0)];
    sch_json(['ok' => true, 'date' => $date, 'reached' => (object)$out]);
}

case 'reach': {
    if ($method !== 'POST') sch_fail('POST only', 405);
    $date = du_date($in['date'] ?? '', true); $bell = (int)($in['bell'] ?? -1); $cls = du_txt($in['cls'] ?? '', 20);
    if ($bell < 0 || $bell > 9 || !in_array($cls, du_classes(), true)) sch_fail('Choose the bell and class.');
    $key = "$date|$bell|$cls";
    if (empty($in['on'])) {
        $old = sch_rec_get('tchReach', $key);
        if ($old && !$duty && ($old['tid'] ?? '') !== $tid) sch_fail('Only the teacher who marked it can take it back.', 403);
        sch_rec_del('tchReach', $key, $uid); sch_json(['ok' => true, 'reached' => null]);
    }
    $rec = ['tid' => $role === 'teacher' ? $tid : 'P', 'by' => (string)($u['label'] ?? $uid), 'at' => sch_now_ms()];
    sch_rec_put('tchReach', $key, $rec, $uid);
    sch_log($u, 'Arrangement', "Reached Class $cls, bell " . ($bell + 1) . " ($date)");
    sch_json(['ok' => true, 'reached' => $rec]);
}

case 'hwnot': {
    if ($method !== 'GET') sch_fail('GET only', 405);
    if (!($duty || $role === 'chairman')) sch_fail('This list is for the Duty teacher and the Principal.', 403);
    $date = du_date($_GET['date'] ?? date('Y-m-d')); $items = [];
    foreach (du_rows($db, 'tchChk', $date . '|') as $r => $d) {
        foreach ((array)($d['rows'] ?? []) as $sid => $x) {
            $hw = (string)($x['hw'] ?? ''); if ($hw !== 'not' && $hw !== 'half') continue;
            $items[] = ['sid' => (string)$sid, 'cls' => (string)($d['cls'] ?? ''), 'subj' => (string)($d['subj'] ?? ''), 'hw' => $hw, 'task' => (string)($d['hw'] ?? ''), 'teacher' => sch_teacher_name((string)($d['tid'] ?? ''))];
        }
    }
    $sent = [];
    foreach (du_rows($db, 'tchSent', $date . '|') as $r => $d) $sent[substr($r, 11)] = ['by' => (string)($d['by'] ?? ''), 'at' => (int)($d['at'] ?? 0)];
    sch_json(['ok' => true, 'date' => $date, 'items' => $items, 'sent' => (object)$sent]);
}

case 'copy': {
    $cls = du_txt($method === 'POST' ? ($in['cls'] ?? '') : ($_GET['cls'] ?? ''), 20); $subj = du_txt($method === 'POST' ? ($in['subj'] ?? '') : ($_GET['subj'] ?? ''), 60);
    if (!in_array($cls, du_classes(), true) || $subj === '') sch_fail('Choose the class and subject.');
    if ($method === 'GET') {
        if ($role === 'teacher' && !$duty && !sch_in_class($tid, $cls)) sch_fail('You can see only your own classes.', 403);
        $out = [];
        foreach (du_rows($db, 'tchCopy', "$cls|$subj|") as $r => $d) $out[substr($r, strlen("$cls|$subj|"))] = ['rows' => (object)($d['rows'] ?? []), 'by' => (string)($d['by'] ?? ''), 'at' => (int)($d['at'] ?? 0)];
        sch_json(['ok' => true, 'chapters' => (object)$out]);
    }
    if (!($role === 'principal' || ($role === 'teacher' && sch_teaches($tid, $cls, $subj)))) sch_fail('Only the teacher of this subject checks its copies.', 403);
    // r ≤ 190 chars: class 20 + subject 60 + chapter 100
    $ch = du_txt($in['ch'] ?? '', 100); if ($ch === '' || str_contains($ch, '|')) sch_fail('Choose the chapter.');
    $ids = []; foreach ($db->query("SELECT r, d FROM sch_records WHERE k='student' AND deleted=0") as $row) { $d = json_decode((string)$row['d'], true); if (($d['cls'] ?? '') === $cls) $ids[(string)$row['r']] = true; }
    $rows = []; foreach ((array)($in['rows'] ?? []) as $sid => $v) { $sid = (string)$sid; if (isset($ids[$sid]) && in_array($v, ['ok', 'inc'], true)) $rows[$sid] = $v; }
    if (!$rows) sch_fail('Mark at least one child.');
    $rec = ['cls' => $cls, 'subj' => $subj, 'ch' => $ch, 'rows' => $rows, 'tid' => $role === 'teacher' ? $tid : 'P', 'by' => (string)($u['label'] ?? $uid), 'at' => sch_now_ms()];
    sch_rec_put('tchCopy', "$cls|$subj|$ch", $rec, $uid);
    $inc = count(array_filter($rows, fn($v) => $v === 'inc'));
    sch_log($u, 'Copy check', "Class $cls $subj, chapter \"$ch\": " . (count($rows) - $inc) . " complete, $inc incomplete");
    sch_json(['ok' => true, 'rec' => $rec]);
}

case 'sentlist': {
    if ($method !== 'GET') sch_fail('GET only', 405);
    $date = du_date($_GET['date'] ?? date('Y-m-d')); $sent = [];
    foreach (du_rows($db, 'tchSent', $date . '|') as $r => $d) $sent[substr($r, 11)] = ['by' => (string)($d['by'] ?? ''), 'at' => (int)($d['at'] ?? 0)];
    sch_json(['ok' => true, 'date' => $date, 'sent' => (object)$sent]);
}

case 'hwsum': {
    // Owner, 27 Nov 2026: "jo homework assign kiya gaya tha vohi show ho … jo homework nahi kiya bas usko tick kar ho jaye".
    if ($method !== 'GET') sch_fail('GET only', 405);
    $today = date('Y-m-d'); $from = date('Y-m-d', strtotime('-30 days'));
    // the Duty teacher (Adarsh Sir) sees every class like the Principal (owner, 27 Nov 2026: homework check report for Adarsh Sir + Principal)
    $mine = fn(string $cls): bool => $role !== 'teacher' || $duty || sch_in_class($tid, $cls);
    $last = []; $miss = []; $todayRecs = [];
    $q = $db->prepare("SELECT r, d FROM sch_records WHERE k='tchChk' AND deleted=0 AND r >= ?"); $q->execute([$from]);
    foreach ($q as $row) {
        [$d0, $c0, $s0] = array_pad(explode('|', (string)$row['r'], 3), 3, ''); if (!$mine($c0)) continue;
        $cs = "$c0|$s0"; if (!isset($last[$cs]) || $d0 > $last[$cs]) $last[$cs] = $d0;
        $d = json_decode((string)$row['d'], true) ?: [];
        foreach ((array)($d['rows'] ?? []) as $sid => $x) if (($x['hw'] ?? '') === 'not') $miss[$cs][(string)$sid] = ($miss[$cs][(string)$sid] ?? 0) + 1;
        if ($d0 === $today) $todayRecs[$cs] = ['hw' => (string)($d['hw'] ?? ''), 'rows' => (object)($d['rows'] ?? []), 'by' => (string)($d['by'] ?? ''), 'at' => (int)($d['at'] ?? 0)];
    }
    sch_json(['ok' => true, 'today' => $today, 'last' => (object)$last, 'miss' => (object)array_map(fn($m) => (object)$m, $miss), 'checks' => (object)$todayRecs]);
}

case 'hwreg': {
    // Homework register (owner, 28 Nov 2026: "Adarsh Sir, Rati Ma'am aur Principal Sir me class ke according, student ke according
    // show kare ki homework done or not done"). GET &from=&to= (max 31 days) → every homework check (tchChk) in those days with each
    // child's done / half / not, plus homework written in the diary (last 14 days up to `to`) that has no check after it yet.
    // Principal / Chairman / Duty teacher / homework sharer (Rati Ma'am): every class. Any other teacher: only own classes.
    if ($method !== 'GET') sch_fail('GET only', 405);
    $to = du_date($_GET['to'] ?? date('Y-m-d')); $from = du_date($_GET['from'] ?? $to);
    if ($from > $to || strtotime($to) - strtotime($from) > 31 * 86400) sch_fail('Choose up to 31 days.');
    $all = $role !== 'teacher' || $duty || sch_hw_sharer($tid);
    $SHOW = ['PG', 'LKG', 'UKG', '1st', '2nd', '3rd', '4th', '5th', '6th', '7th', '8th', '9th', '10th', '11th', '12th'];
    $classes = array_values(array_filter(du_classes(), fn($c) => $all || sch_in_class($tid, $c)));
    usort($classes, fn($x, $y) => (array_search($x, $SHOW, true) === false ? 99 : array_search($x, $SHOW, true)) <=> (array_search($y, $SHOW, true) === false ? 99 : array_search($y, $SHOW, true)));
    $since = date('Y-m-d', strtotime($to) - 14 * 86400); $first = min($since, $from);
    $checks = []; $after = [];   // after[cls|subj] = list of [date, task] for the "not checked yet" rule
    $q = $db->prepare("SELECT r, d FROM sch_records WHERE k='tchChk' AND deleted=0 AND r >= ?"); $q->execute([$first]);
    foreach ($q as $row) { [$d0, $c0, $s0] = array_pad(explode('|', (string)$row['r'], 3), 3, ''); if (!in_array($c0, $classes, true)) continue;
        $d = json_decode((string)$row['d'], true) ?: []; $dt = (string)($d['date'] ?? $d0); $subj = (string)($d['subj'] ?? $s0);
        $after["$c0|$subj"][] = [$dt, trim((string)($d['hw'] ?? ''))];
        if ($dt < $from || $dt > $to) continue;
        $rows = []; foreach ((array)($d['rows'] ?? []) as $sid => $x) { $h = (string)($x['hw'] ?? ''); if (in_array($h, ['done', 'half', 'not'], true)) $rows[(string)$sid] = $h; }
        if ($rows) $checks[] = ['date' => $dt, 'cls' => $c0, 'subj' => $subj, 'hw' => (string)($d['hw'] ?? ''), 'by' => (string)($d['by'] ?? ''), 'rows' => (object)$rows]; }
    usort($checks, fn($x, $y) => strcmp($y['date'], $x['date']) ?: strcmp($x['subj'], $y['subj']));
    $given = [];   // latest diary homework per class|subject up to `to`
    $q = $db->prepare("SELECT r, d FROM sch_records WHERE k='diary' AND deleted=0 AND r >= ? AND r < ?"); $q->execute([$since, date('Y-m-d', strtotime($to) + 86400)]);
    foreach ($q as $row) { $e = json_decode((string)$row['d'], true); if (!is_array($e)) continue; $p = explode('|', (string)$row['r']);
        $c0 = (string)($e['cls'] ?? ''); $hw = trim((string)($e['hw'] ?? '')); $dt = (string)($p[0] ?? ''); if ($hw === '' || !in_array($c0, $classes, true)) continue;
        $k = $c0 . '|' . (string)($e['subj'] ?? ''); if (!isset($given[$k]) || $dt > $given[$k]['date']) $given[$k] = ['date' => $dt, 'cls' => $c0, 'subj' => (string)($e['subj'] ?? ''), 'hw' => $hw, 'teacher' => sch_teacher_name($p[1] ?? '')]; }
    $pending = [];
    foreach ($given as $k => $g) { $ok = false; foreach ($after[$k] ?? [] as [$cd, $task]) if ($cd > $g['date'] || ($cd === $g['date'] && $task !== '' && $task === $g['hw'])) { $ok = true; break; }
        if (!$ok) $pending[] = $g; }
    usort($pending, fn($x, $y) => strcmp($x['date'], $y['date']));
    sch_json(['ok' => true, 'from' => $from, 'to' => $to, 'classes' => $classes, 'checks' => $checks, 'pending' => $pending]);
}

case 'sent': {
    if ($method !== 'POST') sch_fail('POST only', 405);
    $date = du_date($in['date'] ?? ''); $type = (string)($in['type'] ?? ''); $sid = du_txt($in['sid'] ?? '', 40); $subj = du_txt($in['subj'] ?? '', 60);
    $stu = $sid === '' ? null : sch_rec_get('student', $sid);
    if (!in_array($type, ['abs', 'abs3', 'low', 'hw', 'hw3', 'catch', 'copy'], true) || $stu === null) sch_fail('Choose the child.');
    // Duty teacher / Principal: any child. A teacher: children of a class he teaches or is class teacher of (owner, 27 Nov 2026).
    if (!$duty && !($role === 'teacher' && sch_in_class($tid, (string)($stu['cls'] ?? '')))) sch_fail('You can mark messages only for your own classes.', 403);
    $key = "$date|$type|$sid|$subj";
    if (empty($in['on'])) { sch_rec_del('tchSent', $key, $uid); sch_json(['ok' => true, 'sent' => null]); }
    $rec = ['by' => (string)($u['label'] ?? $uid), 'at' => sch_now_ms()];
    sch_rec_put('tchSent', $key, $rec, $uid);
    sch_json(['ok' => true, 'sent' => $rec]);
}

case 'bug': {
    if ($method !== 'POST') sch_fail('POST only', 405);
    $msg = du_txt($in['msg'] ?? '', 1000); $screen = du_txt($in['screen'] ?? '', 120); $err = du_txt($in['err'] ?? '', 1500); $ver = du_txt($in['ver'] ?? '', 60);
    if (mb_strlen($msg) < 3 && $err === '') sch_fail('Write in a few words what went wrong.');
    $id = date('Ymd-His') . '-' . bin2hex(random_bytes(3));
    $rec = ['at' => sch_now_ms(), 'by' => (string)($u['label'] ?? $uid), 'role' => $role, 'tid' => $tid, 'screen' => $screen, 'msg' => $msg, 'err' => $err, 'ver' => $ver, 'done' => false];
    sch_rec_put('tchBug', $id, $rec, $uid);
    sch_log($u, 'Problem report', 'Problem reported' . ($screen !== '' ? " on screen: $screen" : '') . ' (Principal: Problem reports)');   // words stay in tchBug only
    sch_json(['ok' => true, 'id' => $id]);
}

case 'bugs': {
    if ($method !== 'GET') sch_fail('GET only', 405);
    if (!in_array($role, ['principal', 'chairman'], true)) sch_fail('Problem reports are for the Principal.', 403);
    $from = sch_now_ms() - 60 * 86400 * 1000; $out = [];
    foreach (du_rows($db, 'tchBug', '') as $r => $d) if ((int)($d['at'] ?? 0) >= $from) $out[] = ['id' => $r] + $d;
    usort($out, fn($x, $y) => [(int)!empty($x['done']), -(int)$x['at']] <=> [(int)!empty($y['done']), -(int)$y['at']]);
    sch_json(['ok' => true, 'bugs' => $out]);
}

case 'bugdone': {
    if ($method !== 'POST') sch_fail('POST only', 405);
    if ($role !== 'principal') sch_fail('Only the Principal closes a report.', 403);
    $id = du_txt($in['id'] ?? '', 40); $old = $id === '' ? null : sch_rec_get('tchBug', $id);
    if (!$old) sch_fail('Report not found.');
    $old['done'] = !empty($in['on']); $old['doneBy'] = (string)($u['label'] ?? $uid); $old['doneAt'] = sch_now_ms();
    sch_rec_put('tchBug', $id, $old, $uid);
    sch_json(['ok' => true]);
}

default: sch_fail('Unknown request.', 404);
}
} catch (Throwable $e) {
    error_log('duty_api: ' . $e->getMessage());
    sch_fail('Something went wrong. Please try again.', 500);
}
