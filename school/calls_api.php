<?php
// ============================================================================
//  Parent calling — PTM / event / attendance calls with feedback (owner, 19 Nov 2026:
//  "ERP aur Teacher App me calling feature aur feedback mark karne ka option — jaise abhi
//  PTM se related sabko call karna hai, kuch aur hoga to usse related call, aur attendance
//  se related calling — 1 day, 3 day continue").
//
//  Fee calls stay where they are (teacher_api.php?a=fees/feecall, acctCall). This file is for
//  every OTHER reason to phone a parent:
//
//   GET  ?a=state                      campaigns (with counts), classes this login can call,
//                                      today's call-again list, attendance counts per class
//   GET  ?a=list&cp=<id>|att&cls=      children of one class for that campaign (or absentees for 'att')
//                                      with phone, last call, absence streak
//   POST ?a=call {sid,cp,outcome,ans,reason,fb,note,next,comeOn}   one call result
//   POST ?a=campaign {id?,title,type,date,time,classes[],script}   Principal (any classes) /
//                                      class teacher (own classes only)
//   POST ?a=cpstate {id,active}        close / reopen (Principal or who made it)
//   POST ?a=cpdel {id}                 Principal only
//   GET  ?a=report&cp=<id>|att[&days=] class-wise result, complaints, PTM "said will come" vs "came"
//
//  Kind tchCall (prefix tch → never in the legacy load, never to parents):
//    'cp|<id>' = campaign {id,title,type,date,time,classes[],script,active,by,uid,tid,at}
//    'cl|<id>' = call {id,sid,cls,cp,absFrom,absDays,outcome,ans,reason,fb,note,next,comeOn,by,uid,tid,at,date}
//  Every call is ALSO written to the child's tchLog parent-contact list ('c…') so it shows in the
//  child report of the Teacher App like any other parent contact.
//
//  Who: Principal + Reception (fee-calling desk) call every class; a class teacher calls own
//  class; the school's fee caller (settings.feeCallers, default Adarsh Sir) calls every class.
//  Chairman and Accountant only view. Attendance comes only from saved studAtt (a holiday has no
//  studAtt, so it never breaks or counts in a streak). Leave ('L') = parent already informed → not
//  counted as absent and it ends the streak.
// ============================================================================
declare(strict_types=1);
require __DIR__ . '/inc.php';

const PC_TYPES = ['ptm' => 'PTM', 'event' => 'Event / function', 'exam' => 'Exam', 'holiday' => 'Holiday / timing', 'fee' => 'Fee (general)', 'other' => 'Other'];
const PC_OUT = ['Talked', 'No answer', 'Busy — call later', 'Switched off', 'Wrong number'];
const PC_ANS = ['Will come', 'Not coming', 'Not sure'];                       // PTM / event answers
const PC_REASON = ['Ill / sick', 'Out of town', 'Family function', 'Transport problem', 'Child not willing', 'Weather', 'No reason given', 'Other'];
const PC_FB = ['good' => 'Happy', 'ok' => 'Normal', 'bad' => 'Complaint'];

$a = (string)($_GET['a'] ?? 'state');
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
try { $db = sch_db(); } catch (Throwable $e) { sch_fail('Server is not reachable right now. Please try again in a few minutes.', 503); }

function pc_txt($v, int $max): string { return mb_substr(trim(preg_replace('/[\x00-\x1F\x7F]/u', ' ', (string)$v)), 0, $max); }
function pc_date($v): string { $v = (string)$v; return preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) && strtotime($v) ? $v : ''; }
function pc_rows(PDO $db, string $k, string $like = ''): array {
    $q = $db->prepare('SELECT r, d FROM sch_records WHERE k=? AND deleted=0' . ($like !== '' ? ' AND r LIKE ?' : ''));
    $q->execute($like !== '' ? [$k, $like] : [$k]); $o = [];
    foreach ($q as $row) { $d = json_decode((string)$row['d'], true); if (is_array($d)) $o[(string)$row['r']] = $d; }
    return $o;
}
// Class teacher of each class + the order of classes as the school keeps them.
function pc_classes(): array { $o = []; foreach ((array)sch_rec_get('classes', 'main') as $c) if (($c['c'] ?? '') !== '') $o[(string)$c['c']] = (string)($c['ct'] ?? ''); return $o; }
function pc_students(PDO $db): array {
    $o = []; foreach (pc_rows($db, 'student') as $sid => $s) if (empty($s['inactive']) && ($s['cls'] ?? '') !== '')
        $o[$sid] = ['sid' => $sid, 'name' => (string)($s['name'] ?? ''), 'father' => (string)($s['father'] ?? ''), 'mother' => (string)($s['mother'] ?? ''),
                    'phone' => sch_phone10((string)($s['phone'] ?? '')), 'cls' => (string)$s['cls'], 'roll' => (string)($s['roll'] ?? '')];
    uasort($o, fn($x, $y) => strcmp($x['name'], $y['name']));
    return $o;
}
// Absence of every child of the given classes, from saved attendance only (last 60 days).
// streak = school days in a row absent (not leave), counted back from the class's LAST saved day.
function pc_absence(PDO $db, array $classes): array {
    $from = date('Y-m-d', strtotime('-60 days')); $ym = date('Y-m');
    $q = $db->prepare("SELECT r, d FROM sch_records WHERE k='studAtt' AND deleted=0 AND dkey >= ? ORDER BY dkey DESC");
    $q->execute([$from]);
    $by = []; foreach ($q as $row) { [$date, $c] = array_pad(explode('|', (string)$row['r'], 2), 2, '');
        if (!isset($classes[$c])) continue; $d = json_decode((string)$row['d'], true); if (!is_array($d)) continue; $by[$c][$date] = $d; }
    $out = [];
    foreach ($by as $c => $days) {
        krsort($days); $dates = array_keys($days); $last = $dates[0];
        $absMap = []; foreach ($days as $date => $d) { $lv = (array)($d['st'] ?? []); foreach ((array)($d['absent'] ?? []) as $sid) $absMap[(string)$sid][$date] = (($lv[(string)$sid] ?? '') === 'L') ? 'L' : 'A'; }
        $mDays = count(array_filter($dates, fn($x) => str_starts_with($x, $ym)));
        foreach ($absMap as $sid => $m) {
            $streak = 0; $start = '';
            foreach ($dates as $date) { if (($m[$date] ?? '') === 'A') { $streak++; $start = $date; } else break; }
            $ma = 0; foreach ($m as $date => $v) if ($v === 'A' && str_starts_with($date, $ym)) $ma++;
            $out[$sid] = ['cls' => $c, 'last' => $last, 'streak' => $streak, 'from' => $start, 'today' => $streak > 0 && $last === date('Y-m-d'),
                          'monthAbs' => $ma, 'monthDays' => $mDays, 'pct' => $mDays ? (int)round(100 * ($mDays - $ma) / $mDays) : null];
        }
        $out['#' . $c] = ['last' => $last, 'monthDays' => $mDays];
    }
    return $out;
}
// Does this child need an attendance call? → 1+ day streak not called since it started, or month attendance below 75%.
function pc_att_need(array $ab): bool { return ($ab['streak'] ?? 0) > 0 || (($ab['pct'] ?? 100) !== null && ($ab['pct'] ?? 100) < 75 && ($ab['monthDays'] ?? 0) >= 5); }
function pc_cp_classes(array $cp, array $all): array { $L = array_values(array_filter((array)($cp['classes'] ?? []), fn($c) => isset($all[$c]))); return $L ?: array_keys($all); }

try {
$u = sch_auth();
$role = (string)$u['role']; $tid = (string)($u['tid'] ?? ''); $uid = sch_uid_of($u);
if (!in_array($role, ['principal', 'teacher', 'chairman', 'reception', 'accountant'], true)) sch_fail('Parent calling is for school staff.', 403);
$in = [];
if ($method === 'POST') {
    $in = json_decode(file_get_contents('php://input', false, null, 0, 100000) ?: '', true);
    if (!is_array($in)) sch_fail('Bad request.');
    if (in_array($role, ['chairman', 'accountant'], true)) sch_fail('This login can only view the calls.', 403);
}
$CL = pc_classes();
$fcList = (sch_rec_get('settings', 'main') ?? [])['feeCallers'] ?? null;
$caller = $role === 'teacher' && (is_array($fcList) ? in_array($tid, $fcList, true) : ($tid === 'AD' || stripos(sch_teacher_name($tid), 'adarsh') !== false));
$allCls = in_array($role, ['principal', 'reception', 'chairman', 'accountant'], true) || $caller;
$myCls = $allCls ? $CL : array_filter($CL, fn($ct) => $ct === $tid);          // teacher: classes where he/she is class teacher
$canCall = fn(string $c): bool => isset($myCls[$c]) && !in_array($role, ['chairman', 'accountant'], true);
$canCamp = fn(array $classes): bool => $role === 'principal' || ($role === 'teacher' && $classes && !array_diff($classes, array_keys(array_filter($CL, fn($ct) => $ct === $tid))));
$who = sch_user_label($u);

$camps = function () use ($db): array { $o = []; foreach (pc_rows($db, 'tchCall', 'cp|%') as $d) $o[$d['id']] = $d; uasort($o, fn($x, $y) => [($y['active'] ?? true) ? 1 : 0, $y['at'] ?? 0] <=> [($x['active'] ?? true) ? 1 : 0, $x['at'] ?? 0]); return $o; };
$calls = function () use ($db): array { $o = pc_rows($db, 'tchCall', 'cl|%'); usort($o, fn($x, $y) => ($y['at'] ?? 0) <=> ($x['at'] ?? 0)); return $o; };
$pub = fn(array $c): array => array_intersect_key($c, array_flip(['id', 'sid', 'cls', 'cp', 'absFrom', 'absDays', 'outcome', 'ans', 'reason', 'fb', 'note', 'next', 'comeOn', 'by', 'at', 'date']));

switch ($a) {

case 'state': {
    $C = $camps(); $K = $calls(); $kids = pc_students($db); $today = date('Y-m-d');
    $see = fn(string $c): bool => isset($myCls[$c]);
    // latest call per child per campaign
    $last = []; foreach ($K as $c) { $key = $c['sid'] . '|' . $c['cp']; $last[$key] ??= $c; }
    $out = [];
    foreach ($C as $id => $cp) {
        if (empty($cp['active']) && ($cp['at'] ?? 0) < (time() - 90 * 86400) * 1000) continue;
        $cls = array_values(array_filter(pc_cp_classes($cp, $CL), $see)); if (!$cls) continue;
        $n = ['kids' => 0, 'called' => 0, 'talked' => 0, 'yes' => 0, 'no' => 0, 'noans' => 0, 'bad' => 0];
        foreach ($kids as $k) { if (!in_array($k['cls'], $cls, true)) continue; $n['kids']++; $l = $last[$k['sid'] . '|' . $id] ?? null; if (!$l) continue;
            $n['called']++; if ($l['outcome'] === 'Talked') $n['talked']++; else $n['noans']++;
            if (($l['ans'] ?? '') === 'Will come') $n['yes']++; if (($l['ans'] ?? '') === 'Not coming') $n['no']++; if (($l['fb'] ?? '') === 'bad') $n['bad']++; }
        $out[] = array_intersect_key($cp, array_flip(['id', 'title', 'type', 'date', 'time', 'script', 'active', 'by', 'at'])) + ['classes' => $cls, 'allClasses' => empty($cp['classes']), 'n' => $n,
                 'mine' => $role === 'principal' || ($cp['uid'] ?? '') === $uid];
    }
    // attendance: children who need a call, per class
    $ab = pc_absence($db, $myCls); $att = [];
    foreach ($myCls as $c => $ct) $att[$c] = ['d1' => 0, 'd2' => 0, 'd3' => 0, 'low' => 0, 'todo' => 0, 'last' => $ab['#' . $c]['last'] ?? ''];
    foreach ($kids as $k) { $x = $ab[$k['sid']] ?? null; if (!$x || !isset($att[$k['cls']]) || !pc_att_need($x)) continue; $s = $x['streak'];
        if ($s >= 3) $att[$k['cls']]['d3']++; elseif ($s === 2) $att[$k['cls']]['d2']++; elseif ($s === 1) $att[$k['cls']]['d1']++; else $att[$k['cls']]['low']++;
        $l = $last[$k['sid'] . '|att'] ?? null; if (!$l || ($s > 0 ? ($l['date'] ?? '') < $x['from'] : ($l['date'] ?? '') < date('Y-m-d', strtotime('-7 days')))) $att[$k['cls']]['todo']++; }
    // call again today: newest call per child+campaign with a "next" date that has come, and still the latest one
    $again = [];
    foreach ($last as $l) { if (($l['next'] ?? '') === '' || $l['next'] > $today || !isset($kids[$l['sid']]) || !$see($kids[$l['sid']]['cls'])) continue;
        if ($l['cp'] !== 'att' && (!isset($C[$l['cp']]) || empty($C[$l['cp']]['active']))) continue;
        $k = $kids[$l['sid']]; $again[] = $pub($l) + ['name' => $k['name'], 'phone' => $k['phone'], 'title' => $l['cp'] === 'att' ? 'Attendance' : (string)$C[$l['cp']]['title']]; }
    usort($again, fn($x, $y) => strcmp($x['next'], $y['next']));
    $mine = 0; foreach ($K as $c) if (($c['date'] ?? '') === $today && ($c['uid'] ?? '') === $uid) $mine++;
    sch_json(['ok' => true, 'today' => $today, 'role' => $role, 'canCall' => !in_array($role, ['chairman', 'accountant'], true) && (bool)$myCls,
              'canCampaign' => $role === 'principal' || ($role === 'teacher' && in_array($tid, $CL, true)), 'all' => $allCls,
              'classes' => array_keys($myCls), 'ctClasses' => $role === 'teacher' ? array_keys(array_filter($CL, fn($ct) => $ct === $tid)) : array_keys($CL),
              'campaigns' => $out, 'att' => (object)$att, 'again' => array_slice($again, 0, 200), 'myToday' => $mine,
              'types' => PC_TYPES, 'outcomes' => PC_OUT, 'answers' => PC_ANS, 'reasons' => PC_REASON, 'fb' => PC_FB,
              'school' => (string)((sch_rec_get('settings', 'main') ?? [])['school'] ?? '')]);
}

case 'list': {
    $cp = (string)($_GET['cp'] ?? ''); $cls = (string)($_GET['cls'] ?? '');
    if (!isset($myCls[$cls])) sch_fail('You can see only your own classes.', 403);
    $camp = null;
    if ($cp !== 'att') { $camp = preg_match('/^[a-f0-9]{12}$/', $cp) ? sch_rec_get('tchCall', "cp|$cp") : null; if (!$camp) sch_fail('This calling list was not found.', 404);
        if (!in_array($cls, pc_cp_classes($camp, $CL), true)) sch_fail('This class is not in this calling list.'); }
    $kids = array_filter(pc_students($db), fn($k) => $k['cls'] === $cls);
    $hist = []; foreach ($calls() as $c) if (isset($kids[$c['sid'] ?? '']) && ($c['cp'] ?? '') === $cp) $hist[$c['sid']][] = $pub($c);
    $ab = pc_absence($db, [$cls => true]); $L = [];
    foreach ($kids as $sid => $k) {
        $x = $ab[$sid] ?? ['streak' => 0, 'from' => '', 'pct' => null, 'monthAbs' => 0, 'monthDays' => $ab['#' . $cls]['monthDays'] ?? 0, 'today' => false];
        if ($cp === 'att' && !pc_att_need($x)) continue;
        $L[] = $k + ['abs' => array_intersect_key($x, array_flip(['streak', 'from', 'pct', 'monthAbs', 'monthDays', 'today'])), 'calls' => array_slice($hist[$sid] ?? [], 0, 6)];
    }
    if ($cp === 'att') usort($L, fn($p, $q) => [$q['abs']['streak'], -($q['abs']['pct'] ?? 100)] <=> [$p['abs']['streak'], -($p['abs']['pct'] ?? 100)]);
    // PTM attendance already filled by the class teacher (tchLog ptm|date|cls|sid) — shown next to "Will come"
    $came = [];
    if ($camp && ($camp['type'] ?? '') === 'ptm' && pc_date($camp['date'] ?? '') !== '')
        foreach (pc_rows($db, 'tchLog', 'ptm|' . $camp['date'] . '|%') as $r => $d) if (($d['cls'] ?? '') === $cls) $came[(string)$d['sid']] = (int)($d['present'] ?? 0);
    sch_json(['ok' => true, 'cls' => $cls, 'cp' => $cp, 'campaign' => $camp ? array_intersect_key($camp, array_flip(['id', 'title', 'type', 'date', 'time', 'script', 'active'])) : null,
              'last' => $ab['#' . $cls]['last'] ?? '', 'kids' => $L, 'came' => (object)$came, 'can' => $canCall($cls) && (!$camp || !empty($camp['active'])), 'today' => date('Y-m-d')]);
}

case 'call': {
    if ($method !== 'POST') sch_fail('POST only', 405);
    $sid = (string)($in['sid'] ?? ''); $s = $sid !== '' ? sch_rec_get('student', $sid) : null;
    if (!$s || !empty($s['inactive'])) sch_fail('Child not found.', 404);
    $cls = (string)($s['cls'] ?? ''); if (!$canCall($cls)) sch_fail('You can call only the parents of your own class.', 403);
    $cp = (string)($in['cp'] ?? ''); $camp = null; $absFrom = ''; $absDays = 0;
    if ($cp === 'att') { $x = pc_absence($db, [$cls => true])[$sid] ?? null; $absFrom = (string)($x['from'] ?? ''); $absDays = (int)($x['streak'] ?? 0); }
    else { $camp = preg_match('/^[a-f0-9]{12}$/', $cp) ? sch_rec_get('tchCall', "cp|$cp") : null; if (!$camp) sch_fail('This calling list was not found.', 404);
        if (empty($camp['active'])) sch_fail('This calling list is closed.'); if (!in_array($cls, pc_cp_classes($camp, $CL), true)) sch_fail('This class is not in this calling list.'); }
    $out = (string)($in['outcome'] ?? ''); if (!in_array($out, PC_OUT, true)) sch_fail('Choose what happened on the call.');
    $talk = $out === 'Talked';
    $ans = $talk && $camp && in_array($in['ans'] ?? '', PC_ANS, true) ? (string)$in['ans'] : '';
    $reason = $talk && $cp === 'att' && in_array($in['reason'] ?? '', PC_REASON, true) ? (string)$in['reason'] : '';
    $fb = $talk && isset(PC_FB[$in['fb'] ?? '']) ? (string)$in['fb'] : '';
    $note = pc_txt($in['note'] ?? '', 400);
    if ($fb === 'bad' && $note === '') sch_fail('Write the parent\'s complaint in "What was discussed" — the Principal reads it.');
    if ($talk && $camp && in_array($camp['type'] ?? '', ['ptm', 'event'], true) && $ans === '') sch_fail('Choose: will the parent come?');
    $today = date('Y-m-d'); $max = date('Y-m-d', strtotime('+30 days'));
    $next = pc_date($in['next'] ?? ''); if ($next !== '' && ($next < $today || $next > $max)) sch_fail('Call-again date must be within the next 30 days.');
    if ($next === '' && !$talk) $next = date('Y-m-d', strtotime('+1 day'));       // no answer → automatically on tomorrow's call-again list
    $comeOn = $cp === 'att' ? pc_date($in['comeOn'] ?? '') : ''; if ($comeOn !== '' && ($comeOn < $today || $comeOn > $max)) $comeOn = '';
    $id = bin2hex(random_bytes(10)); $now = sch_now_ms();
    $c = ['id' => $id, 'sid' => $sid, 'cls' => $cls, 'cp' => $cp, 'absFrom' => $absFrom, 'absDays' => $absDays, 'outcome' => $out, 'ans' => $ans, 'reason' => $reason, 'fb' => $fb,
          'note' => $note, 'next' => $next, 'comeOn' => $comeOn, 'by' => $who, 'uid' => $uid, 'tid' => $role === 'teacher' ? $tid : ($role === 'principal' ? 'P' : $role), 'at' => $now, 'date' => $today];
    $topic = $cp === 'att' ? 'Attendance' . ($absDays ? " ($absDays day" . ($absDays > 1 ? 's' : '') . ' absent)' : '') : (PC_TYPES[$camp['type'] ?? 'other'] ?? 'Call') . ': ' . $camp['title'];
    $line = "[$topic] $out" . ($ans !== '' ? " — $ans" : '') . ($reason !== '' ? " — $reason" : '') . ($comeOn !== '' ? ' — will come on ' . $comeOn : '') . ($fb !== '' ? ' — parent: ' . PC_FB[$fb] : '') . ($note !== '' ? '. ' . $note : '');
    $db->beginTransaction();
    sch_rec_put('tchCall', "cl|$id", $c, $uid);
    $lid = 'c' . bin2hex(random_bytes(8));
    sch_rec_put('tchLog', $lid, ['id' => $lid, 'sid' => $sid, 'cls' => $cls, 'kind' => 'call', 'date' => $today, 'mode' => 'Call', 'text' => mb_substr($line, 0, 500), 'tid' => $c['tid'], 'by' => $who, 'at' => $now, 'callId' => $id], $uid);
    $db->commit();
    sch_log($u, 'Parent call', ($s['name'] ?? $sid) . " (Class $cls): $line");
    sch_json(['ok' => true, 'call' => $pub($c)]);
}

case 'campaign': {
    if ($method !== 'POST') sch_fail('POST only', 405);
    $id = (string)($in['id'] ?? ''); $old = null;
    if ($id !== '') { $old = preg_match('/^[a-f0-9]{12}$/', $id) ? sch_rec_get('tchCall', "cp|$id") : null; if (!$old) sch_fail('Not found.', 404);
        if ($role !== 'principal' && ($old['uid'] ?? '') !== $uid) sch_fail('Only the Principal or the person who made it can change it.', 403); }
    $title = pc_txt($in['title'] ?? '', 120); if ($title === '') sch_fail('Write what the call is about (e.g. PTM on Saturday).');
    $type = isset(PC_TYPES[$in['type'] ?? '']) ? (string)$in['type'] : 'other';
    $date = pc_date($in['date'] ?? ''); if (in_array($type, ['ptm', 'event'], true) && $date === '') sch_fail('Choose the date of the ' . ($type === 'ptm' ? 'PTM' : 'event') . '.');
    $classes = array_values(array_unique(array_filter(array_map('strval', (array)($in['classes'] ?? [])), fn($c) => isset($CL[$c]))));
    if ($role === 'teacher' && !$classes) $classes = array_keys(array_filter($CL, fn($ct) => $ct === $tid));
    if (!$canCamp($classes ?: array_keys($CL))) sch_fail('A class teacher can make a calling list only for his / her own class. The Principal makes it for the whole school.', 403);
    $id = $id ?: bin2hex(random_bytes(6));
    $d = ['id' => $id, 'title' => $title, 'type' => $type, 'date' => $date, 'time' => pc_txt($in['time'] ?? '', 40), 'classes' => $classes, 'script' => pc_txt($in['script'] ?? '', 600),
          'active' => $old ? !empty($old['active']) : true, 'by' => $old['by'] ?? $who, 'uid' => $old['uid'] ?? $uid, 'tid' => $old['tid'] ?? ($role === 'teacher' ? $tid : 'P'), 'at' => $old['at'] ?? sch_now_ms()];
    sch_rec_put('tchCall', "cp|$id", $d, $uid);
    sch_log($u, 'Parent call', ($old ? 'Calling list changed: ' : 'New calling list: ') . $title . ' — ' . ($classes ? 'Class ' . implode(', ', $classes) : 'all classes'));
    sch_json(['ok' => true, 'campaign' => $d]);
}
case 'cpstate': case 'cpdel': {
    if ($method !== 'POST') sch_fail('POST only', 405);
    $id = (string)($in['id'] ?? ''); $old = preg_match('/^[a-f0-9]{12}$/', $id) ? sch_rec_get('tchCall', "cp|$id") : null; if (!$old) sch_fail('Not found.', 404);
    if ($a === 'cpdel') { if ($role !== 'principal') sch_fail('Only the Principal can delete a calling list.', 403);
        sch_rec_del('tchCall', "cp|$id", $uid); sch_log($u, 'Parent call', 'Calling list deleted: ' . $old['title']); sch_json(['ok' => true]); }
    if ($role !== 'principal' && ($old['uid'] ?? '') !== $uid) sch_fail('Only the Principal or the person who made it can close it.', 403);
    $old['active'] = !empty($in['active']); sch_rec_put('tchCall', "cp|$id", $old, $uid);
    sch_log($u, 'Parent call', 'Calling list ' . ($old['active'] ? 'reopened' : 'closed') . ': ' . $old['title']);
    sch_json(['ok' => true]);
}

case 'report': {
    $cp = (string)($_GET['cp'] ?? ''); $camp = null; $days = max(1, min(90, (int)($_GET['days'] ?? 30)));
    if ($cp !== 'att') { $camp = preg_match('/^[a-f0-9]{12}$/', $cp) ? sch_rec_get('tchCall', "cp|$cp") : null; if (!$camp) sch_fail('Not found.', 404); }
    $kids = pc_students($db); $cls = $camp ? array_values(array_filter(pc_cp_classes($camp, $CL), fn($c) => isset($myCls[$c]))) : array_keys($myCls);
    $since = date('Y-m-d', strtotime("-$days days"));
    $last = []; $all = [];
    foreach ($calls() as $c) { if (($c['cp'] ?? '') !== $cp || !isset($kids[$c['sid'] ?? '']) || !in_array($kids[$c['sid']]['cls'], $cls, true)) continue;
        if (!$camp && ($c['date'] ?? '') < $since) continue; $last[$c['sid']] ??= $c; $all[] = $c; }
    $came = [];
    if ($camp && ($camp['type'] ?? '') === 'ptm' && pc_date($camp['date'] ?? '') !== '') foreach (pc_rows($db, 'tchLog', 'ptm|' . $camp['date'] . '|%') as $d) $came[(string)$d['sid']] = (int)($d['present'] ?? 0);
    $rows = [];
    foreach ($cls as $c) $rows[$c] = ['cls' => $c, 'ct' => sch_teacher_name($CL[$c] ?? ''), 'kids' => 0, 'called' => 0, 'talked' => 0, 'noans' => 0, 'yes' => 0, 'no' => 0, 'maybe' => 0, 'good' => 0, 'bad' => 0, 'came' => 0, 'saidCame' => 0, 'reasons' => []];
    foreach ($kids as $sid => $k) { if (!isset($rows[$k['cls']])) continue; $R = &$rows[$k['cls']]; if ($camp) $R['kids']++;
        $l = $last[$sid] ?? null; if (!empty($came[$sid])) $R['came']++;
        if ($l) { $R['called']++; if ($l['outcome'] === 'Talked') $R['talked']++; else $R['noans']++;
            $ak = ['Will come' => 'yes', 'Not coming' => 'no', 'Not sure' => 'maybe'][$l['ans'] ?? ''] ?? ''; if ($ak !== '') $R[$ak]++;
            if (($l['fb'] ?? '') === 'good') $R['good']++; if (($l['fb'] ?? '') === 'bad') $R['bad']++;
            if (($l['ans'] ?? '') === 'Will come' && !empty($came[$sid])) $R['saidCame']++; }
        unset($R); }
    foreach ($all as $c) if (($c['reason'] ?? '') !== '' && isset($rows[$c['cls']])) $rows[$c['cls']]['reasons'][$c['reason']] = ($rows[$c['cls']]['reasons'][$c['reason']] ?? 0) + 1;
    foreach ($rows as &$R) $R['reasons'] = (object)$R['reasons']; unset($R);
    $complaints = []; foreach ($all as $c) if (($c['fb'] ?? '') === 'bad') $complaints[] = $pub($c) + ['name' => $kids[$c['sid']]['name'], 'phone' => $kids[$c['sid']]['phone']];
    $people = []; foreach ($all as $c) { $people[$c['by']] = ($people[$c['by']] ?? 0) + 1; } arsort($people);
    sch_json(['ok' => true, 'campaign' => $camp ? array_intersect_key($camp, array_flip(['id', 'title', 'type', 'date', 'time', 'active'])) : null, 'days' => $days,
              'rows' => array_values($rows), 'complaints' => array_slice($complaints, 0, 200), 'people' => (object)$people, 'total' => count($all)]);
}

default: sch_fail('Unknown action.', 404);
}
} catch (PDOException $e) {
    if ($db->inTransaction()) $db->rollBack();
    error_log('calls_api: ' . $e->getMessage());
    sch_fail('Could not save right now. Please try again.', 500);
}
