<?php
// Real SQL, isolated CI database; no hosting config and no external SMS credentials.
if (!getenv('SR_TEST_DB_HOST')) throw new RuntimeException('Isolated database required');
define('SR_FIREBASE_ENABLED',true);
define('SR_FIREBASE_ADMIN_MOBILES',['9000000011']);
function t($en,$hi){return $en;}
require_once __DIR__.'/../smartsarathi/inc/client.php';
function flow_check($ok,$label){if(!$ok)throw new RuntimeException($label);}
$pdo=new PDO('mysql:host='.getenv('SR_TEST_DB_HOST').';dbname=sarathi_identity_test;charset=utf8mb4','root',getenv('SR_TEST_DB_PASSWORD'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
foreach(['001-base.sql','002-unified-identity.sql'] as $f)$pdo->exec(file_get_contents(__DIR__.'/../smartsarathi/sql/'.$f));
sr_jobs_upgrade($pdo); session_start();
$ids=[];$rids=[];$cid=null;
try {
    sr_identity_signin($pdo,['uid'=>'flow-admin','mobile'=>'9000000011']);$admin=$_SESSION;$ids[]=(int)sr_account($pdo)['id'];
    sr_identity_signin($pdo,['uid'=>'flow-new','mobile'=>'9000000012']);$a=sr_account($pdo);$ids[]=(int)$a['id'];$session=$_SESSION;
    sr_profile_submit($pdo,$a,['name'=>'बाइक चालक','vehicle_type'=>'bike'],'','');
    $p=sr_profile($pdo,(int)$a['id']);flow_check($p['photo']==='' && $p['vehicle_number']==='' && $p['status']==='pending','Name and vehicle only');
    $_SESSION['sr_mode']='rider';flow_check(!rider_me($pdo),'Pending cannot work');
    $_SESSION=$admin;sr_profile_review($pdo,(int)$a['id'],1,'approved','Known person');
    $_SESSION=$session;$_SESSION['sr_mode']='rider';$r=rider_me($pdo);$rid=(int)$r['id'];$rids[]=$rid;
    flow_check($r && $r['ride_ok'] && $r['do_ride'] && $r['do_delivery'],'Approval enables rides and deliveries');
    duty_on($pdo,$rid);
    $pdo->exec("INSERT INTO clients(name,fee) VALUES ('CI Maakit bridge',25)");$cid=(int)$pdo->lastInsertId();
    $client=$pdo->query('SELECT * FROM clients WHERE id='.$cid)->fetch();
    [$ok,$job]=job_create($pdo,$client,['ref'=>'CI-bike','kind'=>'ride','vehicle'=>'bike','pick_name'=>'Passenger','pick_address'=>'Bhadohi station','pick_geo'=>'25.395,82.571','drop_geo'=>'25.410,82.580','drop_name'=>'Passenger','drop_mobile'=>'9000000013','drop_address'=>'Bhadohi market','pick_otp'=>'1234','drop_code'=>'5678']);
    flow_check($ok && $job['status']==='assigned','Bike bridge job automatically assigned');
    $jobs=rider_jobs($pdo,$rid);flow_check(count($jobs)===1,'Job visible to approved rider');$jid=(int)$jobs[0]['id'];
    flow_check(!job_pick($pdo,$rid,$jid,'9999')[0],'Wrong pickup code rejected');
    flow_check(job_pick($pdo,$rid,$jid,'1234')[0],'Pickup code accepted');
    flow_check(rider_location_update($pdo,$rid,25.4,82.57)[0],'Active rider location accepted');
    [$ok,$status]=job_status($pdo,$client,'CI-bike');flow_check($ok && $status['rider_location']['lat']===25.4,'Location exposed to booking client');
    flow_check(!job_deliver($pdo,$rid,$jid,'9999')[0],'Wrong completion code rejected');
    flow_check(job_deliver($pdo,$rid,$jid,'5678')[0],'Completion code accepted');
    [$ok,$status]=job_status($pdo,$client,'CI-bike');flow_check($status['status']==='delivered' && $status['rider_location']===null,'Completed tracking cleared');
    flow_check(!rider_location_update($pdo,$rid,25.4,82.57)[0],'Idle location rejected');
    duty_off($pdo,$rid);
    // Pre-added rider: preserve admin vehicle choice, rate and work permissions.
    $pdo->prepare('INSERT INTO riders(name,mobile,pass_hash,vehicle,vtype,rate,ride_ok,do_ride) VALUES (?,?,?,?,?,?,1,1)')->execute(['Pre-added','9000000014','disabled-password','Motorcycle','ev',45]);$pre=(int)$pdo->lastInsertId();$rids[]=$pre;
    sr_identity_signin($pdo,['uid'=>'flow-pre','mobile'=>'9000000014']);$a=sr_account($pdo);$ids[]=(int)$a['id'];
    sr_identity_autolink($pdo,$a);sr_identity_autolink($pdo,$a);$_SESSION['sr_mode']='rider';$r=rider_me($pdo);
    flow_check($r && (int)$r['id']===$pre && (int)$r['rate']===45,'Pre-added rider links once, preserves earnings');
    flow_check(sr_profile($pdo,(int)$a['id'])['vehicle_type']==='ev','Admin vehicle type preserved');
    flow_check(sr_vtype_guess('Motorcycle')==='bike','Motorcycle is not a bicycle');
    echo "Rider entry, approval, pre-added linking, bike dispatch, handover codes and location lifecycle passed\n";
} finally {
    if($cid){$pdo->prepare('DELETE FROM jobs WHERE client_id=?')->execute([$cid]);$pdo->prepare('DELETE FROM clients WHERE id=?')->execute([$cid]);}
    foreach($ids as $id){$pdo->prepare('DELETE FROM sr_identity_audit WHERE account_id=? OR actor_id=?')->execute([$id,$id]);$pdo->prepare('DELETE FROM sr_rider_profiles WHERE account_id=?')->execute([$id]);}
    foreach($rids as $id){$pdo->prepare('DELETE FROM trips WHERE rider_id=?')->execute([$id]);$pdo->prepare('DELETE FROM riders WHERE id=?')->execute([$id]);}
    foreach($ids as $id)$pdo->prepare('DELETE FROM sr_accounts WHERE id=?')->execute([$id]);
}
