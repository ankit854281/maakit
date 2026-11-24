<?php
// ============================================================================
// Learning Support — weak children (owner, 24 Nov 2026: "jo bachche weak hain unke field me record kaise track kiya jaye aur
// uspe work ho — book reading, learning, maths, English grammar"; then "sab banakar add karke cross check karke dijiye").
// Teacher App screen: teacher-support.js. Staff only (teacher / Principal; Chairman reads). Parents never see these records.
//
//   GET  ?a=state                         levels of every child, groups, group logs of the last 90 days
//   POST ?a=level  {sid, skill, v}        one child's level on one ladder (0 = not checked, 1–5)
//   POST ?a=levels {cls, skill, vals:{sid:v}}  the whole class on one ladder (term check)
//   POST ?a=tables {sid, n, on}           pahade: table n (2–20) known / not
//   POST ?a=book   {sid, title, pages}    reading log: a book (or pages) read
//   POST ?a=group  {id?, name, skill, kids[]}  make / change a support group (owner = the teacher who made it)
//   POST ?a=delgroup {id}                 owner / Principal
//   POST ?a=glog   {gid, date, did, res:{sid:1|0}}  one day of the group: what was done + who got it (owner / Principal)
//
// Who: levels / tables / books — a teacher of that child's class (sch_in_class) or the Principal. Groups — any teacher
// makes one (children from any class, e.g. "Pahada group 6th–8th"); only its owner or the Principal changes / logs it.
// Kinds (prefix tch → never in the legacy load): tchSkill r=sid {lv:{readH,readE,maths,gram}, hist[], tables[], books[]},
// tchGrp r=id {name, skill, kids[], owner, at}, tchGrpLog r=gid|date {did, res, by, at}.
// ============================================================================
declare(strict_types=1);
require __DIR__ . '/inc.php';

const SUP_SKILLS = ['readH', 'readE', 'maths', 'gram'];
$a = (string)($_GET['a'] ?? 'state');
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
try { $db = sch_db(); } catch (Throwable $e) { sch_fail('Server is not reachable right now. Please try again in a few minutes.', 503); }
$u = sch_auth();
$role = (string)$u['role']; $tid = (string)($u['tid'] ?? ''); $uid = sch_uid_of($u);
if (!in_array($role, ['teacher', 'principal', 'chairman'], true)) sch_fail('Learning Support is for school staff.', 403);
$in = [];
if ($method === 'POST') {
    $in = json_decode(file_get_contents('php://input', false, null, 0, 200000) ?: '', true);
    if (!is_array($in)) sch_fail('Bad request.');
    if ($role === 'chairman') sch_fail('The Chairman login can only view.', 403);
}
$stu = function (string $sid): array { $s = sch_rec_get('student', $sid); if (!is_array($s)) sch_fail('Student not found.'); return $s; };
$canKid = function (array $s) use ($role, $tid): bool { return $role === 'principal' || ($role === 'teacher' && sch_in_class($tid, (string)($s['cls'] ?? ''))); };
$rows = function (string $k) use ($db): array { $q = $db->prepare('SELECT r, d FROM sch_records WHERE k=? AND deleted=0'); $q->execute([$k]); $o = []; foreach ($q as $x) { $d = json_decode((string)$x['d'], true); if (is_array($d)) $o[(string)$x['r']] = $d; } return $o; };
$skillRec = function (string $sid): array { $r = sch_rec_get('tchSkill', $sid); return is_array($r) ? $r + ['lv' => [], 'hist' => [], 'tables' => [], 'books' => []] : ['lv' => [], 'hist' => [], 'tables' => [], 'books' => []]; };
$setLevel = function (string $sid, string $skill, int $v) use ($skillRec, $uid): array {
    $r = $skillRec($sid); $old = (int)($r['lv'][$skill] ?? 0); $r['lv'][$skill] = $v;
    if ($old !== $v) { $r['hist'][] = ['d' => date('Y-m-d'), 'k' => $skill, 'v' => $v, 'by' => $uid]; $r['hist'] = array_slice($r['hist'], -120); }
    sch_rec_put('tchSkill', $sid, $r, $uid); return $r;
};

switch ($a) {
case 'state': {
    $since = date('Y-m-d', strtotime('-90 days')); $logs = [];
    foreach ($rows('tchGrpLog') as $r => $d) { [$g, $dt] = array_pad(explode('|', $r, 2), 2, ''); if ($dt >= $since) $logs[] = ['gid' => $g, 'date' => $dt] + $d; }
    usort($logs, fn($x, $y) => strcmp($y['date'], $x['date']));
    sch_json(['ok' => true, 'skills' => (object)$rows('tchSkill'), 'groups' => (object)$rows('tchGrp'), 'logs' => $logs, 'me' => $tid, 'principal' => $role === 'principal']);
}
case 'level': {
    $sid = (string)($in['sid'] ?? ''); $skill = (string)($in['skill'] ?? ''); $v = (int)($in['v'] ?? -1);
    if (!in_array($skill, SUP_SKILLS, true) || $v < 0 || $v > 5) sch_fail('Choose a skill and a level 0–5.');
    $s = $stu($sid); if (!$canKid($s)) sch_fail('Only a teacher of this child\'s class can change the level.', 403);
    $r = $setLevel($sid, $skill, $v); sch_log($u, 'Learning Support', "{$s['name']} ({$s['cls']}): $skill level $v");
    sch_json(['ok' => true, 'rec' => $r]);
}
case 'levels': {
    $cls = (string)($in['cls'] ?? ''); $skill = (string)($in['skill'] ?? ''); $vals = is_array($in['vals'] ?? null) ? $in['vals'] : [];
    if (!in_array($skill, SUP_SKILLS, true) || $cls === '' || !$vals) sch_fail('Choose the class, the skill and the levels.');
    if (!($role === 'principal' || sch_in_class($tid, $cls))) sch_fail('Only a teacher of this class can change its levels.', 403);
    $n = 0; $out = [];
    foreach ($vals as $sid => $v) { $sid = (string)$sid; $v = (int)$v; if ($v < 0 || $v > 5) continue; $s = sch_rec_get('student', $sid); if (!is_array($s) || ($s['cls'] ?? '') !== $cls) continue; $out[$sid] = $setLevel($sid, $skill, $v); $n++; }
    sch_log($u, 'Learning Support', "Class $cls: $skill levels saved for $n children");
    sch_json(['ok' => true, 'saved' => $n, 'recs' => (object)$out]);
}
case 'tables': {
    $sid = (string)($in['sid'] ?? ''); $n = (int)($in['n'] ?? 0); $on = !empty($in['on']);
    if ($n < 2 || $n > 20) sch_fail('Pahade 2 to 20.');
    $s = $stu($sid); if (!$canKid($s)) sch_fail('Only a teacher of this child\'s class can mark it.', 403);
    $r = $skillRec($sid); $t = array_values(array_unique(array_map('intval', $r['tables']))); $t = $on ? array_values(array_unique([...$t, $n])) : array_values(array_diff($t, [$n])); sort($t); $r['tables'] = $t;
    sch_rec_put('tchSkill', $sid, $r, $uid); sch_json(['ok' => true, 'rec' => $r]);
}
case 'book': {
    $sid = (string)($in['sid'] ?? ''); $title = trim(mb_substr((string)($in['title'] ?? ''), 0, 80)); $pages = max(0, min(999, (int)($in['pages'] ?? 0)));
    if ($title === '') sch_fail('Write the book name.');
    $s = $stu($sid); if (!$canKid($s)) sch_fail('Only a teacher of this child\'s class can write it.', 403);
    $r = $skillRec($sid); $r['books'][] = ['d' => date('Y-m-d'), 'title' => $title, 'pages' => $pages, 'by' => $uid]; $r['books'] = array_slice($r['books'], -200);
    sch_rec_put('tchSkill', $sid, $r, $uid); sch_json(['ok' => true, 'rec' => $r]);
}
case 'group': {
    if (!in_array($role, ['teacher', 'principal'], true)) sch_fail('Only teachers make groups.', 403);
    $id = preg_replace('/[^a-z0-9]/', '', (string)($in['id'] ?? '')) ?: ('g' . base_convert((string)time(), 10, 36) . bin2hex(random_bytes(2)));
    $old = sch_rec_get('tchGrp', $id);
    if (is_array($old) && $role !== 'principal' && ($old['owner'] ?? '') !== $tid) sch_fail('Only the teacher who made this group (or the Principal) can change it.', 403);
    $name = trim(mb_substr((string)($in['name'] ?? ''), 0, 60)); $skill = (string)($in['skill'] ?? '');
    if ($name === '' || !in_array($skill, [...SUP_SKILLS, 'other'], true)) sch_fail('Write the group name and choose the skill.');
    $kids = []; foreach ((array)($in['kids'] ?? []) as $sid) { $sid = (string)$sid; if (is_array(sch_rec_get('student', $sid))) $kids[] = $sid; }
    $kids = array_values(array_unique($kids)); if (!$kids) sch_fail('Choose the children of the group.'); if (count($kids) > 60) sch_fail('A group can have at most 60 children.');
    $g = ['name' => $name, 'skill' => $skill, 'kids' => $kids, 'owner' => is_array($old) ? ($old['owner'] ?? $tid) : ($role === 'teacher' ? $tid : (string)($in['owner'] ?? '')), 'at' => is_array($old) ? ($old['at'] ?? time()) : time(), 'slot' => trim(mb_substr((string)($in['slot'] ?? ''), 0, 40))];
    sch_rec_put('tchGrp', $id, $g, $uid); sch_log($u, 'Learning Support', "Group \"$name\" saved (" . count($kids) . ' children)');
    sch_json(['ok' => true, 'id' => $id, 'group' => $g]);
}
case 'delgroup': {
    $id = (string)($in['id'] ?? ''); $g = sch_rec_get('tchGrp', $id); if (!is_array($g)) sch_fail('Group not found.');
    if ($role !== 'principal' && ($g['owner'] ?? '') !== $tid) sch_fail('Only the teacher who made this group (or the Principal) can remove it.', 403);
    sch_rec_del('tchGrp', $id, $uid); sch_log($u, 'Learning Support', "Group \"{$g['name']}\" removed");
    sch_json(['ok' => true]);
}
case 'glog': {
    $gid = (string)($in['gid'] ?? ''); $date = (string)($in['date'] ?? date('Y-m-d')); $g = sch_rec_get('tchGrp', $gid); if (!is_array($g)) sch_fail('Group not found.');
    if ($role !== 'principal' && ($g['owner'] ?? '') !== $tid) sch_fail('Only the group\'s teacher (or the Principal) can write its day.', 403);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || $date > date('Y-m-d')) sch_fail('Choose a date (not in the future).');
    $did = trim(mb_substr((string)($in['did'] ?? ''), 0, 200)); if ($did === '') sch_fail('Write what was done today (e.g. "Pahada 7", "Story page 4").');
    $res = []; foreach ((array)($in['res'] ?? []) as $sid => $v) if (in_array((string)$sid, $g['kids'], true)) $res[(string)$sid] = $v ? 1 : 0;
    sch_rec_put('tchGrpLog', "$gid|$date", ['did' => $did, 'res' => $res, 'by' => $uid, 'at' => time()], $uid);
    sch_json(['ok' => true]);
}
default: sch_fail('Unknown action.', 404);
}
