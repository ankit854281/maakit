<?php
if(!getenv('SR_TEST_DB_HOST')) throw new RuntimeException('Isolated database required');
define('SR_FIREBASE_ENABLED',true);
function t($en,$hi){return $en;}
require_once __DIR__.'/../smartsarathi/inc/kaam.php';
require_once __DIR__.'/../smartsarathi/inc/vehicles.php';
function access_check($ok,$label){if(!$ok)throw new RuntimeException($label);}
$pdo=new PDO('mysql:host='.getenv('SR_TEST_DB_HOST').';dbname=sarathi_identity_test;charset=utf8mb4','root',getenv('SR_TEST_DB_PASSWORD'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
foreach([1,2] as $round)foreach(['001-base.sql','002-unified-identity.sql','004-password-devices.sql'] as $f)$pdo->exec(file_get_contents(__DIR__.'/../smartsarathi/sql/'.$f));
session_start(); $ids=[]; sr_vehicles_upgrade($pdo);
try {
    sr_identity_signin($pdo,['uid'=>'access-customer','mobile'=>'9000000981']); $a=sr_account($pdo); $ids[]=$a['id'];
    access_check(sr_home_path($pdo)==='/ghar.php','Customer routes home');
    sr_password_save($pdo,$a,'','example-password-981','example-password-981');
    $q=$pdo->prepare('SELECT pass_hash FROM sr_passwords WHERE account_id=?');$q->execute([$a['id']]);
    access_check(password_verify('example-password-981',$q->fetchColumn()),'Password stored as verified hash');
    access_check(!sr_password_signin($pdo,$a['mobile'],'incorrect'),'Wrong password denied');
    access_check(sr_password_signin($pdo,$a['mobile'],'example-password-981'),'Password login succeeds');
    $token=$_COOKIE['SRDEVICE']; $_SESSION=[];
    access_check(sr_account($pdo)['id']===$a['id'],'Closed browser restores account from device token');
    access_check(empty($_SESSION['sr_verified_at']),'Restored device cannot silently reset password');
    $denied=false;try{sr_password_save($pdo,$a,'bad','different-password','different-password');}catch(Throwable $e){$denied=true;}
    access_check($denied,'Password change needs recent verification or existing password');
    sr_password_save($pdo,$a,'example-password-981','different-password','different-password');
    $_SESSION=[];$_COOKIE['SRDEVICE']=$token;
    access_check(sr_account($pdo)===null,'Old device token revoked by password change');
    access_check(sr_password_signin($pdo,$a['mobile'],'different-password'),'New password works');
    sr_forget_login($pdo); $_SESSION=[];
    access_check(sr_account($pdo)===null,'Sign out prevents automatic login');
    sr_identity_signin($pdo,['uid'=>'access-partner','mobile'=>'9000000982']);$p=sr_account($pdo);$ids[]=$p['id'];
    sr_profile_submit($pdo,$p,['name'=>'Access Partner','vehicle_type'=>'bike','vehicle_number'=>'UP66AB9982'],'','');
    sr_set_role($pdo,$p,'customer'); $_SESSION['sr_intent']='customer';
    access_check(sr_home_path($pdo)==='/apply.php','Registered pending driver cannot land on customer screen');
    access_check(sr_account_role($pdo,$p)==='partner','Profile takes precedence over customer intent');
    sr_docs_upgrade($pdo); sr_vehicles_upgrade($pdo);
    $pdo->prepare('INSERT INTO sr_vehicles(account_id,vehicle_type,model,rc_number) VALUES(?,?,?,?)')->execute([$p['id'],'auto','Test auto','UP66AB9983']);$vehicle=(int)$pdo->lastInsertId();
    $denied=false;try{sr_vehicle_change($pdo,$a,$vehicle);}catch(Throwable $e){$denied=true;}
    access_check($denied,'Another account cannot switch this vehicle');
    sr_vehicle_change($pdo,$p,$vehicle);
    access_check(sr_profile($pdo,(int)$p['id'])['vehicle_type']==='auto','Vehicle type persisted on profile');
    echo "Password, persistent login, revocation, strict driver routing and vehicle ownership passed\n";
} finally {
    foreach($ids as $id){$pdo->prepare('DELETE FROM sr_vehicles WHERE account_id=?')->execute([$id]);$pdo->prepare('DELETE FROM sr_rider_profiles WHERE account_id=?')->execute([$id]);$pdo->prepare('DELETE FROM sr_accounts WHERE id=?')->execute([$id]);}
}
