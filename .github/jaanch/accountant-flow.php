<?php
// CI only: office add permissions, retry safety, shared records and attendance totals.
require_once __DIR__ . '/../../config.php';
if (DB_NAME !== 'maakit_jaanch') throw new RuntimeException('CI database only');
require_once __DIR__ . '/../../school/inc.php';
$db = sch_db();
function ac_check(bool $ok, string $why): void { if (!$ok) throw new RuntimeException($why); }
function ac_http(string $path, string $token, ?array $body = null): array {
    $c = curl_init('http://127.0.0.1:8099/school/' . $path);
    $options = [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30, CURLOPT_PROXY => '', CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'X-School-Token: ' . $token]];
    if ($body !== null) { $options[CURLOPT_POST] = true; $options[CURLOPT_POSTFIELDS] = json_encode($body); }
    curl_setopt_array($c, $options); $raw = curl_exec($c); $code = curl_getinfo($c, CURLINFO_HTTP_CODE); curl_close($c);
    return [$code, json_decode((string)$raw, true)];
}
foreach (['sch_sessions','sch_users','sch_records','sch_log','sch_login_fail'] as $table) $db->exec('DELETE FROM ' . $table);
$tokens = [];
foreach (['principal','accountant','reception','teacher','chairman'] as $role) {
    $tid = $role === 'teacher' ? 'TEST' : ($role === 'principal' ? 'P' : null);
    $uid = $role === 'teacher' ? 't:TEST' : $role;
    $db->prepare('INSERT INTO sch_users(uid,role,tid,pin_hash,must_change) VALUES(?,?,?,?,0)')->execute([$uid,$role,$tid,password_hash('4567', PASSWORD_DEFAULT)]);
    $token = bin2hex(random_bytes(32)); $tokens[$role] = $token;
    $db->prepare('INSERT INTO sch_sessions(token_hash,uid,expires_at,last_seen) VALUES(?,?,DATE_ADD(NOW(),INTERVAL 1 DAY),NOW())')->execute([hash('sha256',$token),$uid]);
}
sch_rec_put('teacher','TEST',['name'=>'Existing Teacher','role'=>'Teacher'],'principal');
$input = ['kind'=>'teacher','name'=>'Test Added Teacher','phone'=>'9876543210','nonce'=>bin2hex(random_bytes(12))];
foreach (['reception','teacher','chairman'] as $role) {
    [$code] = ac_http('accountant_api.php?a=add',$tokens[$role],$input); ac_check($code === 403, $role . ' cannot add staff');
}
[$code,$data] = ac_http('accountant_api.php?a=add',$tokens['accountant'],$input);
ac_check($code === 200 && isset($data['id']), 'accountant adds teacher'); $id = $data['id'];
ac_check(sch_rec_get('teacher',$id)['phone'] === '9876543210','shared teacher record');
[$code,$data] = ac_http('accountant_api.php?a=add',$tokens['accountant'],$input);
ac_check($code === 200 && $data['id'] === $id && $data['again'], 'retry returns same teacher');
$input['nonce'] = bin2hex(random_bytes(12));
[$code] = ac_http('accountant_api.php?a=add',$tokens['accountant'],$input); ac_check($code === 409,'duplicate name rejected');
$input = ['kind'=>'staff','name'=>'Test Office Assistant','designation'=>'Office Assistant','phone'=>'','nonce'=>bin2hex(random_bytes(12))];
[$code,$data] = ac_http('accountant_api.php?a=add',$tokens['accountant'],$input);
ac_check($code === 200 && sch_rec_get('salStaff',$data['id'])['monthly'] === null,'staff joins salary roster without a fabricated salary');
[$code,$data] = ac_http('accountant_api.php?a=staff',$tokens['accountant']);
ac_check($code === 200 && count($data['staff']) === 3,'staff list includes existing and added staff');
$date = date('Y-m-d'); if (date('w') === '0') $date = date('Y-m-d',strtotime('-1 day'));
sch_rec_put('classes','main',[['c'=>'PG','ct'=>'TEST'],['c'=>'LKG','ct'=>$id]],'principal');
foreach (['one','two','three','four'] as $sid) sch_rec_put('student',$sid,['id'=>$sid,'name'=>$sid,'cls'=>$sid === 'four' ? 'LKG' : 'PG'],'principal');
sch_rec_put('studAtt',$date.'|PG',['absent'=>['two','three'],'st'=>['three'=>'L']],'principal');
[$code,$data] = ac_http('office_api.php?a=attendance&date='.$date,$tokens['accountant']);
ac_check($code === 200 && $data['totals'] === ['total'=>4,'present'=>1,'absent'=>1,'leave'=>1,'pending'=>1,'holiday'=>0],'attendance keeps absent, leave and pending separate');
sch_rec_put('clsHol',$date.'|LKG',['title'=>'Class holiday'],'principal');
[, $data] = ac_http('office_api.php?a=attendance&date='.$date,$tokens['accountant']);
ac_check($data['totals']['holiday'] === 1 && $data['totals']['pending'] === 0,'class holiday replaces pending');
echo "Accountant permission, retry, roster and attendance checks passed\n";
