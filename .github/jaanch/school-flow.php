<?php
// ============================================================
//  जाँच — School ERP (maakit.in/school)
//
//  Ye dekhta hai:
//    panna, app.js, app.css khulte hain →
//    setup galat chaabi se nahi hota →
//    galat PIN ruka, 5 galti par taala →
//    Principal sab likh sakte hain →
//    teacher SIRF apna kaam likh sakta hai (apni diary, apni class
//    ki attendance, apne subject ke marks, apni chhutti "Pending") →
//    baaki sab mana → khaali {} waisa hi wapas aata hai →
//    Activity Log me har kaam → logout ke baad token bekaar.
//
//  Sirf CI ki database par chalta hai (sch_ tables saaf karta hai).
// ============================================================
require_once __DIR__ . '/../../config.php';
if (DB_NAME !== 'maakit_jaanch') throw new RuntimeException('CI database only');
require_once __DIR__ . '/../../inc/db.php';

function sf_check($ok, $m) { if (!$ok) throw new RuntimeException('School flow: ' . $m); }
function sf_http(string $path, $body = null, ?string $tok = null): array {
    $c = curl_init('http://127.0.0.1:8099' . $path);
    $h = ['X-School-Token: ' . ($tok ?? '')];
    $o = [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20, CURLOPT_PROXY => ''];
    if ($body !== null) { $h[] = 'Content-Type: application/json'; $o[CURLOPT_POST] = true; $o[CURLOPT_POSTFIELDS] = is_string($body) ? $body : json_encode($body); }
    $o[CURLOPT_HTTPHEADER] = $h;
    curl_setopt_array($c, $o);
    $raw = (string)curl_exec($c); $code = curl_getinfo($c, CURLINFO_HTTP_CODE); curl_close($c);
    sf_check(!preg_match('/Fatal error|Warning:|Uncaught/', $raw), "PHP error at $path");
    return [$code, $raw, json_decode($raw, true)];
}
function sf_api(string $a, $body = null, ?string $tok = null, string $q = ''): array { return sf_http('/school/api.php?a=' . $a . $q, $body, $tok); }

foreach (['sch_sessions', 'sch_users', 'sch_records', 'sch_log', 'sch_login_fail'] as $t) $pdo->exec("DELETE FROM $t");

// ---- panna ----
[$c, $html] = sf_http('/school/');
sf_check($c === 200 && str_contains($html, 'School ERP'), 'page did not open');
[$c, $js] = sf_http('/school/app.js');  sf_check($c === 200 && str_contains($js, 'Powered by Maakit'), 'app.js missing');
[$c] = sf_http('/school/app.css'); sf_check($c === 200, 'app.css missing');

// ---- setup ----
[, , $j] = sf_api('status');
sf_check($j['ok'] && $j['setup'] === true, 'status should ask for setup');
[$c] = sf_api('setup', ['key' => 'galat-chaabi', 'pin' => '246810']);
sf_check($c === 403, 'setup accepted a wrong key');
$pdo->prepare("INSERT INTO sch_users (uid, role, tid, pin_hash) VALUES ('principal','principal','P',?)")->execute([password_hash('246810', PASSWORD_DEFAULT)]);
[, , $j] = sf_api('status');
sf_check($j['setup'] === false, 'setup still asked after principal exists');

// ---- login ----
[$c] = sf_api('login', ['role' => 'principal', 'pin' => '111111']);
sf_check($c === 403, 'wrong PIN not refused');
[$c, , $j] = sf_api('login', ['role' => 'principal', 'pin' => '246810']);
sf_check($c === 200 && strlen($j['token']) === 64, 'principal login failed');
$P = $j['token'];
[$c] = sf_api('load', null, 'deadbeef');
sf_check($c === 401, 'bad token was accepted');

// ---- principal seeds data ----
$today = date('Y-m-d');
$seed = ['put' => [
    ['k' => 'settings', 'r' => 'main', 'd' => ['school' => 'SN Mishra Singapore International School', 'session' => '2026–27']],
    ['k' => 'classes',  'r' => 'main', 'd' => [['c' => '10th', 'ct' => 'MA'], ['c' => '9th', 'ct' => 'K']]],
    ['k' => 'teacher',  'r' => 'P',  'd' => ['name' => 'Prabhakar Dubey Sir', 'role' => 'Principal']],
    ['k' => 'teacher',  'r' => 'K',  'd' => ['name' => 'Kartikey Sir', 'role' => 'SST']],
    ['k' => 'teacher',  'r' => 'MA', 'd' => ['name' => "Mamta Ma'am", 'role' => 'Hindi']],
    ['k' => 'alloc',    'r' => 'main', 'd' => [['c' => '9th', 's' => 'Social Science', 't' => 'K', 'n' => 7], ['c' => '10th', 's' => 'Hindi', 't' => 'MA', 'n' => 6]]],
    ['k' => 'student',  'r' => 's1', 'd' => ['id' => 's1', 'adm' => '74', 'name' => 'Shivam Dubey', 'cls' => '9th']],
], 'log' => [['k' => 'Setup', 'x' => 'CI seed']]];
[$c, , $j] = sf_api('sync', $seed, $P);
sf_check($c === 200 && count($j['done']) === 7 && !$j['rejected'], 'principal could not save');

[$c] = sf_api('setpin', ['uid' => 't:K', 'pin' => '12'], $P);
sf_check($c === 400, 'short PIN accepted');
[$c] = sf_api('setpin', ['uid' => 't:K', 'pin' => '135790'], $P);
sf_check($c === 200, 'principal could not set teacher PIN');
[, , $j] = sf_api('status');
sf_check(count(array_filter($j['teachers'], fn($t) => $t['tid'] === 'K' && $t['pin'])) === 1, 'teacher PIN not shown as set');

// ---- teacher: only own work ----
[$c, , $j] = sf_api('login', ['role' => 'teacher', 'tid' => 'K', 'pin' => '135790']);
sf_check($c === 200, 'teacher login failed');
$K = $j['token'];
$try = function (array $put, bool $allowed, string $what) use ($K) {
    [, , $j] = sf_api('sync', ['put' => [$put]], $K);
    sf_check($allowed ? count($j['done']) === 1 : count($j['rejected']) === 1, $what);
};
$try(['k' => 'studAtt', 'r' => "$today|9th",  'd' => ['absent' => ['s1']]], true,  'class teacher could not mark own class');
$try(['k' => 'studAtt', 'r' => "$today|10th", 'd' => ['absent' => []]],     false, 'teacher marked another class');
$try(['k' => 'diary',   'r' => "$today|K|0",  'd' => ['cls' => '9th', 'topic' => 'Federalism', 'm' => (object)[]]], true, 'teacher could not write own diary');
$try(['k' => 'diary',   'r' => "$today|MA|0", 'd' => ['cls' => '10th', 'topic' => 'x']], false, "teacher wrote someone else's diary");
$try(['k' => 'settings','r' => 'main',        'd' => ['school' => 'HACKED']],  false, 'teacher changed settings');
$try(['k' => 'marks',   'r' => 'hy|9th|Social Science', 'd' => ['max' => 40, 'm' => ['s1' => 30]]], true, 'teacher could not enter own marks');
$try(['k' => 'marks',   'r' => 'hy|10th|Hindi',         'd' => ['max' => 40, 'm' => ['s1' => 40]]], false, 'teacher entered marks of another subject');
$try(['k' => 'leave',   'r' => 'L1', 'd' => ['id' => 'L1', 'tid' => 'K', 'status' => 'Pending', 'from' => $today, 'to' => $today]], true, 'teacher could not ask leave');
$try(['k' => 'leave',   'r' => 'L1', 'd' => ['id' => 'L1', 'tid' => 'K', 'status' => 'Approved', 'from' => $today, 'to' => $today]], false, 'teacher approved own leave');
$try(['k' => 'syl',     'r' => '9th|Social Science', 'd' => ['chapters' => [['n' => 'Ch 1', 'done' => null]]]], true, 'teacher could not update own syllabus');
// naye hisse: behaviour, speaking, lesson plan, calendar
$try(['k' => 'behav', 'r' => "$today|b1", 'd' => ['id' => 'b1', 'sid' => 's1', 'cls' => '9th',  'pts' => 1, 'tid' => 'K']],  true,  'teacher could not give behaviour points in own class');
$try(['k' => 'behav', 'r' => "$today|b2", 'd' => ['id' => 'b2', 'sid' => 's1', 'cls' => '10th', 'pts' => 1, 'tid' => 'K']],  false, 'teacher gave points in a class they do not teach');
$try(['k' => 'behav', 'r' => "$today|b3", 'd' => ['id' => 'b3', 'sid' => 's1', 'cls' => '9th',  'pts' => 1, 'tid' => 'MA']], false, 'teacher wrote behaviour in another teacher\'s name');
$try(['k' => 'speak', 'r' => 'sp1', 'd' => ['id' => 'sp1', 'cls' => '9th', 'students' => ['s1'], 'tid' => 'K']], true, 'teacher could not log speaking');
$wk = date('Y-m-d', strtotime('monday this week'));
$try(['k' => 'lplan', 'r' => "$wk|K|9th|Social Science", 'd' => ['tid' => 'K', 'status' => 'Submitted', 'topics' => 'Federalism']], true,  'teacher could not submit lesson plan');
$try(['k' => 'lplan', 'r' => "$wk|K|9th|Social Science", 'd' => ['tid' => 'K', 'status' => 'Approved',  'topics' => 'Federalism']], false, 'teacher approved own lesson plan');
$try(['k' => 'cal',   'r' => 'c1', 'd' => ['id' => 'c1', 'date' => $today, 'title' => 'Holiday', 'type' => 'Holiday']], false, 'teacher changed the calendar');
[, , $j] = sf_api('sync', ['put' => [['k' => 'lplan', 'r' => "$wk|K|9th|Social Science", 'd' => ['tid' => 'K', 'status' => 'Approved', 'topics' => 'Federalism']]]], $P);
sf_check(count($j['done']) === 1, 'principal could not approve a lesson plan');
$try(['k' => 'lplan', 'r' => "$wk|K|9th|Social Science", 'd' => ['tid' => 'K', 'status' => 'Submitted', 'topics' => 'changed']], false, 'teacher changed an approved plan');
[, , $j] = sf_api('sync', ['del' => [['k' => 'speak', 'r' => 'sp1']]], $K);
sf_check(count($j['done']) === 1, 'teacher could not delete own speaking entry');

[, , $j] = sf_api('sync', ['del' => [['k' => 'student', 'r' => 's1']]], $K);
sf_check(count($j['rejected']) === 1, 'teacher deleted a student');

// ---- principal sees it all, data comes back as written ----
[, $raw, $j] = sf_api('load', null, $P);
$keys = array_map(fn($r) => $r['k'] . ':' . $r['r'], $j['records']);
foreach (["studAtt:$today|9th", "diary:$today|K|0", 'marks:hy|9th|Social Science', 'leave:L1'] as $need) sf_check(in_array($need, $keys, true), "principal cannot see $need");
sf_check(!in_array("diary:$today|MA|0", $keys, true), 'forbidden diary reached the database');
sf_check(str_contains($raw, '"m":{}'), 'empty object {} came back changed');
foreach ($j['records'] as $r) if ($r['k'] === 'settings') sf_check($r['d']['school'] !== 'HACKED', 'settings were changed by a teacher');
$kinds = array_column($j['log'], 'k');
sf_check(in_array('Login', $kinds, true) && in_array('PIN', $kinds, true) && in_array('Setup', $kinds, true), 'activity log incomplete');
[, , $j2] = sf_api('since', null, $P, '&v=0&l=0');
sf_check($j2['ok'] && count($j2['records']) >= 10, 'since returned nothing');
[$c] = sf_api('day', null, $P, '&date=' . $today);
sf_check($c === 200, 'day fetch failed');

// ---- parent: mobile + code, sirf apne bachche ----
$pdo->prepare("INSERT INTO sch_records (k,r,d,deleted,ver) VALUES ('student','s2',?,0,1)")
    ->execute([json_encode(['id' => 's2', 'adm' => '75', 'name' => 'Riya Pal', 'cls' => '9th', 'phone' => '98390 11111'])]);
$pdo->prepare("UPDATE sch_records SET d=? WHERE k='student' AND r='s1'")
    ->execute([json_encode(['id' => 's1', 'adm' => '74', 'name' => 'Shivam Dubey', 'cls' => '9th', 'phone' => '9839022222'])]);
[$c] = sf_api('parentcode', ['phone' => '9839022222'], $K);
sf_check($c === 403, 'teacher made a parent code');
[$c] = sf_api('parentcode', ['phone' => '9000000000'], $P);
sf_check($c === 400, 'parent code made for a number with no student');
[$c, , $j] = sf_api('parentcode', ['phone' => '+91 98390 22222'], $P);
sf_check($c === 200 && preg_match('/^\d{4}$/', $j['code']), 'principal could not make a parent code');
$pcode = $j['code'];
[$c] = sf_api('login', ['role' => 'parent', 'phone' => '9839022222', 'pin' => $pcode === '1234' ? '4321' : '1234']);
sf_check($c === 403, 'parent logged in with a wrong code');
[$c, , $j] = sf_api('login', ['role' => 'parent', 'phone' => '9839022222', 'pin' => $pcode]);
sf_check($c === 200, 'parent could not log in');
$PA = $j['token'];
// date sheet parent ko jaata hai, par kis-kis ke ghar result gaya ('sent') nahi
sf_api('sync', ['put' => [['k' => 'exams', 'r' => 'main', 'd' => [['id' => 'pb1', 'name' => 'Pre-Board 1 (Class 10)', 'date' => '2026-12-21', 'classes' => ['9th'],
    'sheet' => [['date' => '2026-12-21', 'title' => 'Mathematics', 'subjects' => ['Mathematics'], 'max' => 80]], 'sent' => ['s2' => 1]]]]]], $P);
[, $raw, $j] = sf_api('pdata', null, $PA);
sf_check(str_contains($raw, '"sheet"') && !str_contains($raw, '"sent"'), 'parent did not get the date sheet, or saw who else got results');
[$c, $raw, $j] = sf_api('pdata', null, $PA);
sf_check($c === 200 && count($j['kids']) === 1 && $j['kids'][0]['name'] === 'Shivam Dubey', 'parent did not get exactly their own child');
sf_check(!str_contains($raw, 'Riya Pal') && !str_contains($raw, '98390 11111'), "another child's data reached a parent");
foreach ($j['marks'] as $m) sf_check($m['sid'] === 's1', 'parent saw marks of another child');
[$c] = sf_api('load', null, $PA);
sf_check($c === 403, 'parent could open the full school data');
[, , $j] = sf_api('sync', ['put' => [['k' => 'studAtt', 'r' => "$today|9th", 'd' => ['absent' => []]]]], $PA);
sf_check(count($j['rejected']) === 1, 'parent changed attendance');
// ---- Learning Lab: subject permissions, private keys and child-only practice ----
$missionId='10000000-0000-4000-8000-000000000001';
$mission=['id'=>$missionId,'cls'=>'9th','subj'=>'Social Science','topic'=>'Check reasoning','minutes'=>10,'workDate'=>$today,'plan'=>'PRIVATE_TEACHER_PLAN_775','due'=>$today,'reviewed'=>true,'questions'=>[
 ['prompt'=>'1000 + 247','answer'=>'1247','mode'=>'numeric','hint'=>'Add place values'],
 ['prompt'=>'Explain your steps','answer'=>'PRIVATE_TEACHER_KEY_776','mode'=>'manual']]];
[$c]=sf_http('/school/learning_api.php?a=mission',array_merge($mission,['reviewed'=>false]),$K);sf_check($c===400,'Unreviewed mission published');
[$c]=sf_http('/school/learning_api.php?a=mission',array_merge($mission,['cls'=>'10th','subj'=>'Hindi']),$K);sf_check($c===403,'Teacher published another subject');
[$c]=sf_http('/school/learning_api.php?a=mission',$mission,$K);sf_check($c===200,'Reviewed mission not saved');
[$c]=sf_http('/school/learning_api.php?a=mission',$mission,$K);sf_check($c===200,'Mission retry not idempotent');
[$c,$raw,$ls]=sf_http('/school/learning_api.php?a=state',null,$PA);
sf_check($c===200&&count($ls['students'])===1&&$ls['students'][0]['id']==='s1','Parent sees another student in learning');
sf_check(!str_contains($raw,'PRIVATE_TEACHER_PLAN_775')&&!str_contains($raw,'PRIVATE_TEACHER_KEY_776')&&!str_contains($raw,'"answer"'),'Parent received answer key or teacher plan');
[$c]=sf_http('/school/learning_api.php?a=attempt',['id'=>'10000000-0000-4000-8000-000000000002','sid'=>'s2','mission'=>$missionId,'answers'=>['1247','steps']],$PA);sf_check($c===403,'Parent submitted for another child');
[$c,,$tryResult]=sf_http('/school/learning_api.php?a=attempt',['id'=>'10000000-0000-4000-8000-000000000003','sid'=>'s1','mission'=>$missionId,'answers'=>['1247','steps'],'correct'=>999],$PA);
sf_check($c===200&&$tryResult['result']['correct']===1&&$tryResult['result']['pending']===1,'Practice trusted client score or auto-graded writing');
$review=['id'=>'10000000-0000-4000-8000-000000000005','cls'=>'9th','subj'=>'Social Science','attempt'=>'10000000-0000-4000-8000-000000000003','status'=>'Checked','feedback'=>'Explain the same method using a new example'];
[$c]=sf_http('/school/learning_api.php?a=review',$review,$PA);sf_check($c===403,'Parent wrote teacher feedback');
[$c]=sf_http('/school/learning_api.php?a=review',$review,$K);sf_check($c===200,'Teacher review could not be completed');
[$c,,$reviewState]=sf_http('/school/learning_api.php?a=state',null,$PA);sf_check(count($reviewState['reviews'])===1&&$reviewState['reviews'][0]['status']==='Checked','Family cannot see checked practice feedback');
$skill=['id'=>'10000000-0000-4000-8000-000000000004','cls'=>'9th','subj'=>'Social Science','sid'=>'s1','skill'=>'Understanding','level'=>1,'evidence'=>'Needed one hint to explain an example'];
[$c]=sf_http('/school/learning_api.php?a=skill',$skill,$PA);sf_check($c===403,'Parent wrote teacher observation');
[$c]=sf_http('/school/learning_api.php?a=skill',$skill,$K);sf_check($c===200,'Teacher could not save observed skill');
[$c,,$ls]=sf_http('/school/learning_api.php?a=state',null,$PA);sf_check(count($ls['skills'])===1&&count($ls['attempts'])===1&&!isset($ls['attempts'][0]['answers']),'Parent evidence scope failed');
[$c,$raw]=sf_api('load',null,$P);sf_check(!str_contains($raw,'PRIVATE_TEACHER_PLAN_775')&&!str_contains($raw,'PRIVATE_TEACHER_KEY_776'),'Legacy sync leaked learning keys');
[$c,,$dr]=sf_http('/school/learning_api.php?a=draft',['cls'=>'9th','subj'=>'Social Science','topic'=>'Reasoning'],$K);sf_check($c===200&&str_contains($dr['mode'],'not AI'),'Template mislabeled as AI');

// ---- verified ERP import: preview, stable IDs, same admission, repeat and permission ----
$erp=['format'=>'snmishra-students-v1','session'=>'2026-27','students'=>[
 ['erpId'=>'901','adm'=>'ERP-DUP','name'=>'Import Child A','cls'=>'1st','father'=>'Parent A'],
 ['erpId'=>'902','adm'=>'ERP-DUP','name'=>'Import Child B','cls'=>'2nd','father'=>'Parent B']]];
$teachersBefore=$pdo->query("SELECT r,d FROM sch_records WHERE k='teacher' ORDER BY r")->fetchAll();
[$c,,$preview]=sf_api('importstudents',$erp,$P);
sf_check($c===200&&$preview['toAdd']===2&&!$preview['applied'],'student preview failed');
sf_check((int)$pdo->query("SELECT COUNT(*) FROM sch_records WHERE r IN ('erp-snmishra-901','erp-snmishra-902')")->fetchColumn()===0,'preview wrote students');
[$c]=sf_api('importstudents',array_merge($erp,['apply'=>true]),$K);sf_check($c===403,'teacher imported students');
[$c,,$done]=sf_api('importstudents',array_merge($erp,['apply'=>true]),$P);
sf_check($c===200&&$done['added']===2,'same-admission students merged or import failed');
[$c,,$again]=sf_api('importstudents',array_merge($erp,['apply'=>true]),$P);
sf_check($c===200&&$again['added']===0&&$again['unchanged']===2,'repeat duplicated students');
$changed=$erp;$changed['students'][0]['name']='Overwrite attempt';
[$c]=sf_api('importstudents',array_merge($changed,['apply'=>true]),$P);sf_check($c===409,'import overwrote existing student');
$wrong=$erp;$wrong['session']='2025-26';[$c]=sf_api('importstudents',$wrong,$P);sf_check($c===400,'wrong session accepted');
sf_check($teachersBefore===$pdo->query("SELECT r,d FROM sch_records WHERE k='teacher' ORDER BY r")->fetchAll(),'student import changed teachers');

// ---- pehli baar PIN 1234, phir apna PIN chunna zaroori ----
[$c] = sf_api('login', ['role' => 'teacher', 'tid' => 'P', 'pin' => '1234']);
sf_check($c === 400, 'teacher login was allowed for the Principal record');
[$c] = sf_api('login', ['role' => 'principal', 'pin' => '1234']);
sf_check($c === 403, 'principal accepted the default PIN');
[$c, , $j] = sf_api('login', ['role' => 'chairman', 'pin' => '1234']);
sf_check($c === 200 && $j['mustChange'] === true, 'chairman could not log in with 1234');
$CH = $j['token'];
[$c] = sf_api('load', null, $CH);
sf_check($c === 428, 'ERP opened before the chairman chose a PIN');
[$c] = sf_api('setpin', ['old' => '1234', 'pin' => '1234'], $CH);
sf_check($c === 400, '1234 accepted as a chosen PIN');
[$c] = sf_api('setpin', ['uid' => 't:K', 'pin' => '2222'], $CH);
sf_check($c === 403, 'chairman set a teacher PIN');
[$c] = sf_api('setpin', ['old' => '1234', 'pin' => '8642'], $CH);
sf_check($c === 200, 'chairman could not choose a PIN');
[$c] = sf_api('load', null, $CH);
sf_check($c === 200, 'chairman blocked after choosing a PIN');
[$c] = sf_api('login', ['role' => 'chairman', 'pin' => '1234']);
sf_check($c === 403, '1234 still worked after the chairman chose a PIN');

[$c] = sf_api('login', ['role' => 'student', 'adm' => '999', 'pin' => '1234']);
sf_check($c === 403, 'unknown admission number logged in');
[$c, , $j] = sf_api('login', ['role' => 'student', 'adm' => '75', 'pin' => '1234']);
sf_check($c === 200 && $j['mustChange'] === true && $j['me']['role'] === 'student', 'student could not log in with 1234');
$ST = $j['token'];
[$c] = sf_api('pdata', null, $ST);
sf_check($c === 428, 'student data opened before choosing a PIN');
[$c] = sf_api('setpin', ['old' => '1234', 'pin' => '9753'], $ST);
sf_check($c === 200, 'student could not choose a PIN');
[$c, $raw, $j] = sf_api('pdata', null, $ST);
sf_check($c === 200 && count($j['kids']) === 1 && $j['kids'][0]['name'] === 'Riya Pal', 'student did not get exactly their own record');
sf_check(!str_contains($raw, 'Shivam'), 'another student reached a student login');
[$c] = sf_api('load', null, $ST);
sf_check($c === 403, 'student opened the full school data');
[, , $j] = sf_api('sync', ['put' => [['k' => 'studAtt', 'r' => "$today|9th", 'd' => ['absent' => []]]]], $ST);
sf_check(count($j['rejected']) === 1, 'student changed attendance');

[$c, , $j] = sf_api('login', ['role' => 'parent', 'phone' => '98390 11111', 'pin' => '1234']);
sf_check($c === 200 && $j['mustChange'] === true, 'parent could not log in with 1234');

// Principal "reset": PIN 1234 dene par agli baar apna PIN chunna padta hai
[$c] = sf_api('setpin', ['uid' => 't:MA', 'pin' => '1234'], $P);
sf_check($c === 200, 'principal could not reset a teacher to 1234');
[$c, , $j] = sf_api('login', ['role' => 'teacher', 'tid' => 'MA', 'pin' => '1234']);
sf_check($c === 200 && $j['mustChange'] === true, 'reset teacher was not asked to choose a PIN');

// ---- log export ----
[$c, $csv] = sf_api('logcsv', null, $P);
sf_check($c === 200 && str_contains($csv, 'Activity'), 'principal could not export log');
[$c] = sf_api('logcsv', null, $K);
sf_check($c === 403, 'teacher exported the log');

// ---- 4 ank ka PIN chalta hai; 15 kul galti par login band, Principal naya PIN de to khulta ----
[$c] = sf_api('setpin', ['uid' => 't:MA', 'pin' => '4826'], $P);
sf_check($c === 200, '4-digit PIN refused');
$pdo->exec("UPDATE sch_users SET strikes=14 WHERE uid='t:MA'");
[$c] = sf_api('login', ['role' => 'teacher', 'tid' => 'MA', 'pin' => '1111']);
sf_check($c === 423, 'no block after 15 wrong PINs');
[$c] = sf_api('login', ['role' => 'teacher', 'tid' => 'MA', 'pin' => '4826']);
sf_check($c === 423, 'blocked login opened with the right PIN');
sf_api('setpin', ['uid' => 't:MA', 'pin' => '5937'], $P);
[$c] = sf_api('login', ['role' => 'teacher', 'tid' => 'MA', 'pin' => '5937']);
sf_check($c === 200, 'new PIN from Principal did not unblock');

// ---- lock after 5 wrong PINs ----
for ($i = 0; $i < 5; $i++) sf_api('login', ['role' => 'teacher', 'tid' => 'K', 'pin' => '000000']);
[$c, , $j] = sf_api('login', ['role' => 'teacher', 'tid' => 'K', 'pin' => '135790']);
sf_check($c === 429, 'no lock after 5 wrong PINs');

// ---- logout ----
sf_api('logout', [], $P);
[$c] = sf_api('load', null, $P);
sf_check($c === 401, 'token still works after logout');

foreach (['sch_sessions', 'sch_users', 'sch_records', 'sch_log', 'sch_login_fail'] as $t) $pdo->exec("DELETE FROM $t");
echo "✅ School ERP: panna, login, taala, teacher ki seema, log, logout — sab theek\n";
