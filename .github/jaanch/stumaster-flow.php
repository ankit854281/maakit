<?php
// Student Master (school/stumaster_api.php) — CI database only, synthetic rows only.
require __DIR__ . '/../../config.php';
if (DB_NAME !== 'maakit_jaanch') throw new RuntimeException('CI database only');
require __DIR__ . '/../../school/inc.php';
function smc($v, $m) { if (!$v) throw new RuntimeException('Student Master flow: ' . $m); }
function smtok($uid, $role, $tid = null) {
    $db = sch_db();
    $db->prepare('INSERT INTO sch_users(uid,role,tid,must_change) VALUES(?,?,?,0) ON DUPLICATE KEY UPDATE role=VALUES(role),tid=VALUES(tid),must_change=0')->execute([$uid, $role, $tid]);
    $t = bin2hex(random_bytes(32));
    $db->prepare('INSERT INTO sch_sessions(token_hash,uid,expires_at) VALUES(?,?,DATE_ADD(NOW(),INTERVAL 1 DAY))')->execute([hash('sha256', $t), $uid]);
    return $t;
}
function smq($a, $token, $body = null, $q = '', $api = 'stumaster_api.php') {
    $h = ['X-School-Token: ' . $token]; $o = [CURLOPT_RETURNTRANSFER => true, CURLOPT_PROXY => '', CURLOPT_TIMEOUT => 20];
    if ($body !== null) { $o[CURLOPT_POST] = true; $h[] = 'Content-Type: application/json'; $o[CURLOPT_POSTFIELDS] = json_encode($body); }
    $o[CURLOPT_HTTPHEADER] = $h;
    $c = curl_init('http://127.0.0.1:8099/school/' . $api . '?a=' . $a . $q); curl_setopt_array($c, $o);
    $raw = curl_exec($c); $code = curl_getinfo($c, CURLINFO_HTTP_CODE); curl_close($c);
    smc(is_string($raw) && !preg_match('/Fatal error|Warning:|Uncaught|Deprecated:|Notice:/', $raw), 'PHP response ' . $a . ': ' . substr((string)$raw, 0, 300));
    return [$code, json_decode((string)$raw, true), (string)$raw];
}
$db = sch_db();
$P = smtok('principal', 'principal', 'P'); $C = smtok('chairman', 'chairman'); $A = smtok('smx:acc', 'accountant'); $F = smtok('p:9811155555', 'parent', '9811155555');
$mine = fn() => $db->query("SELECT r FROM sch_records WHERE k='student' AND d LIKE '%Synthetic Master%'")->fetchAll(PDO::FETCH_COLUMN);
$clean = function () use ($db, $mine) {
    foreach ($mine() as $sid) { $db->prepare("DELETE FROM sch_records WHERE (k IN ('student','phoStu') AND r=?) OR (k='regRow' AND r LIKE 'studinfo|%' AND d LIKE ?) OR (k='regHist' AND d LIKE ?)")->execute([$sid, '%"' . $sid . '"%', '%"' . $sid . '"%']); }
    $db->exec("DELETE FROM sch_records WHERE k='student' AND r='sm-old'");
    $db->exec("DELETE FROM sch_records WHERE k='regRow' AND r='studinfo|smoldinfo'");
};
try {
    $clean();
    [$c] = smq('meta', $F); smc($c === 403, 'parents cannot open Student Master');
    [$c] = smq('meta', $A); smc($c === 403, 'accountant cannot open Student Master');
    [$c, $d] = smq('meta', $C); smc($c === 200 && !$d['canEdit'] && in_array('Day Scholar', $d['opts']['admtype'], true) && count($d['opts']['stucast']) > 50, 'chairman reads, old ERP lists present');
    $f = ['rollno' => 'SMX1', 'name' => 'Synthetic Master One', 'clsid' => '2nd', 'secid' => 'A', 'gender' => 'Female', 'cast' => 'OBC', 'fname' => 'Synthetic Father', 'mname' => 'Synthetic Mother',
          'fmobno' => '9811155555', 'telphno' => '9811155555', 'dob' => '05/06/2018', 'cdate' => '01/04/2026', 'paddr' => 'Synthetic Village', 'religion' => 'Hinduism', 'routeid' => 'Jhaua (1-MINI BUS-UP65T4443-31)',
          'faclty' => 'Transport', 'feeapply' => 'Regular-01-1', 'cstatus' => 'Active', 'scount_status' => 'Active', 'custom_text1' => 'Synthetic custom', 'fee_month' => ['4', '5', '99'], 'adharcard' => '123412341234'];
    [$c] = smq('save', $C, ['f' => $f]); smc($c === 403, 'only the Principal saves');
    [$c, $d] = smq('save', $P, ['f' => array_merge($f, ['fname' => ''])]); smc($c === 400 && str_contains($d['error'], "Father's Name"), 'required field (old red label) ' . json_encode($d));
    [$c, $d] = smq('save', $P, ['f' => array_merge($f, ['telphno' => '12345'])]); smc($c === 400 && str_contains($d['error'], '10 digit'), 'mobile must be 10 digits');
    [$c, $d] = smq('save', $P, ['f' => array_merge($f, ['dob' => '2018-06-05'])]); smc($c === 400 && str_contains($d['error'], 'DD/MM/YYYY'), 'date like the old ERP');
    [$c, $d] = smq('save', $P, ['f' => $f]); smc($c === 200 && $d['added'], 'add student ' . json_encode($d)); $sid = $d['id'];
    $s = sch_rec_get('student', $sid);
    smc($s['adm'] === 'SMX1' && $s['cls'] === '2nd' && $s['section'] === 'A' && $s['father'] === 'Synthetic Father' && $s['phone'] === '9811155555' && $s['dob'] === '05/06/2018', 'student record (used everywhere) ' . json_encode($s));
    [$c, $d] = smq('get', $C, null, '&id=' . urlencode($sid));
    smc($c === 200 && $d['f']['routeid'] === 'Jhaua (1-MINI BUS-UP65T4443-31)' && $d['f']['custom_text1'] === 'Synthetic custom' && $d['f']['fee_month'] === ['4', '5'] && $d['f']['cstatus'] === 'Active', 'every old field comes back ' . json_encode($d['f']));
    [$c, $d] = smq('save', $P, ['f' => array_merge($f, ['name' => 'Synthetic Master Two'])]); smc($c === 400 && str_contains($d['error'], 'already given'), 'Scholar No is not given twice');
    [$c, $d] = smq('save', $P, ['id' => $sid, 'f' => array_merge($f, ['scount_status' => 'TC', 'remarks' => 'Synthetic left'])]); smc($c === 200 && !$d['added'] && $d['changed'], 'edit');
    $hist = (int)$db->query("SELECT COUNT(*) FROM sch_records WHERE k='regHist' AND d LIKE '%Edited in Student Master%' AND d LIKE '%$sid%'")->fetchColumn(); smc($hist === 1, 'earlier copy kept in history');
    [$c, $d] = smq('get', $P, null, '&id=' . urlencode($sid)); smc($d['f']['scount_status'] === 'TC' && $d['f']['remarks'] === 'Synthetic left', 'edit kept');
    [$c, $d] = smq('save', $P, ['id' => $sid, 'f' => $d['f']]); smc($c === 200 && !$d['changed'], 'saving unchanged form changes nothing');
    // A record copied from the old ERP with values the form would not accept today is still saved when those fields are not touched.
    sch_rec_put('student', 'sm-old', ['id' => 'sm-old', 'adm' => 'SMOLD', 'name' => 'Synthetic Master Old', 'cls' => '3rd', 'section' => 'A', 'father' => 'Synthetic Old Father', 'phone' => '0000000000', 'dob' => '5-6-2017'], 'principal');
    sch_rec_put('regRow', 'studinfo|smoldinfo', ['student' => 'sm-old', 'status' => 'Inactive (left)', 'category' => 'Select', 'extra' => ['Student Status' => 'IActive']], 'principal');
    [$c, $d] = smq('get', $P, null, '&id=sm-old'); smc($d['f']['scount_status'] === 'IActive' && $d['f']['cstatus'] === 'IActive' && $d['f']['cast'] === 'Select', 'old statuses shown in the old words ' . json_encode($d['f']));
    $o = $d['f']; $o['remarks'] = 'Synthetic note';
    [$c, $d] = smq('save', $P, ['id' => 'sm-old', 'f' => $o]); smc($c === 200 && $d['changed'], 'old record saves when only other fields change ' . json_encode($d));
    smc(sch_rec_get('student', 'sm-old')['dob'] === '5-6-2017' && sch_rec_get('regRow', 'studinfo|smoldinfo')['remarks'] === 'Synthetic note', 'old values left as they were');
    // Photo: one stored copy, used everywhere
    $png = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';
    [$c] = smq('photo', $P, ['id' => $sid, 'data' => 'data:text/html;base64,PGI+']); smc($c === 400, 'only pictures');
    [$c] = smq('photo', $P, ['id' => $sid, 'data' => $png]); smc($c === 200, 'photo saved');
    [$c, $d] = smq('photos', $C, null, '&ids=' . urlencode($sid)); smc($d['photos'][$sid] === $png, 'photo comes back');
    [$c, $d] = smq('list', $C); $row = array_values(array_filter($d['rows'], fn($r) => $r['id'] === $sid))[0] ?? null; smc($row && $row['_photo'] && $row['rollno'] === 'SMX1', 'list row');
    [$c, , $raw] = smq('load', $P, null, '', 'api.php'); smc($c === 200 && !str_contains($raw, 'Synthetic custom') && !str_contains($raw, 'Synthetic left'), 'Full Record stays out of legacy sync');
    echo "Student Master: old ERP form fields kept in one place each, old required fields and checks, untouched old values never refused, history, photo, roles and sync privacy passed\n";
} finally {
    $clean();
    foreach (['smx:acc', 'p:9811155555'] as $x) { $db->prepare('DELETE FROM sch_sessions WHERE uid=?')->execute([$x]); $db->prepare('DELETE FROM sch_users WHERE uid=?')->execute([$x]); }
}
