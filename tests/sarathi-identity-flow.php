<?php
// Isolated CI database only. Does not load or write live config.php.
if (!getenv('SR_TEST_DB_HOST')) throw new RuntimeException('Set SR_TEST_DB_HOST for isolated CI database');
define('SR_FIREBASE_ENABLED',true);
define('SR_FIREBASE_ADMIN_MOBILES',['9000000001']);
$private=sys_get_temp_dir().'/sarathi-identity-ci-'.bin2hex(random_bytes(6));mkdir($private,0700);
define('SR_IDENTITY_PRIVATE_DIR',$private);
function t($en,$hi){return $en;}
require_once __DIR__.'/../smartsarathi/inc/kaam.php';
function ic($ok,$label){if(!$ok)throw new RuntimeException($label);}
function denied(callable $f,$label){try{$f();}catch(Throwable $e){return;}throw new RuntimeException('Allowed '.$label);}
$pdo=new PDO('mysql:host='.getenv('SR_TEST_DB_HOST').';dbname=sarathi_identity_test;charset=utf8mb4','root',getenv('SR_TEST_DB_PASSWORD'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
ic($pdo->query('SELECT DATABASE()')->fetchColumn()==='sarathi_identity_test','Test database guard');
foreach([1,2] as $round)foreach(['001-base.sql','002-unified-identity.sql'] as $f)$pdo->exec(file_get_contents(__DIR__.'/../smartsarathi/sql/'.$f));
sr_jobs_upgrade($pdo);
session_start();
$ids=[];$rid=null;
$photo=str_repeat('a',48).'.jpg';$licence=str_repeat('b',48).'.jpg';file_put_contents($private.'/'.$photo,'fixture');file_put_contents($private.'/'.$licence,'fixture');
try {
    sr_identity_signin($pdo,['uid'=>'ci-admin','mobile'=>'9000000001']);$admin=sr_account($pdo);$ids[]=$admin['id'];$adminSession=$_SESSION;
    ic(sr_identity_admin($pdo),'Admin allowlist');
    sr_identity_signin($pdo,['uid'=>'ci-rider','mobile'=>'9000000002']);$a=sr_account($pdo);$ids[]=$a['id'];$riderSession=$_SESSION;
    ic(!sr_identity_admin($pdo),'No admin from customer mode');
    $_SESSION['sr_admin']=1;ic(!sr_identity_admin($pdo),'Legacy admin cookie ignored');
    ic(!rider_login($pdo,'9000000002','anything'),'Legacy code disabled');
    ic(!rider_me($pdo),'New account not rider');
    sr_profile_submit($pdo,$a,['name'=>'CI Rider','vehicle_type'=>'bike','vehicle_number'=>'UP66AB1234'],$photo,$licence);
    $_SESSION['sr_mode']='rider';ic(!rider_me($pdo),'Mode cannot bypass pending approval');
    denied(fn()=>sr_profile_submit($pdo,$a,['name'=>'Changed','vehicle_type'=>'bike','vehicle_number'=>'UP66AB1234'],$photo,$licence),'overwrite pending profile');
    denied(fn()=>sr_profile_review($pdo,(int)$a['id'],1,'approved',''),'self approval');
    $_SESSION=$adminSession;
    sr_profile_review($pdo,(int)$a['id'],1,'rejected','Please send clear photos');
    $_SESSION=$riderSession;
    // Use new random files; production upload returns unique names each time.
    $photo2=str_repeat('c',48).'.jpg';$licence2=str_repeat('d',48).'.jpg';file_put_contents($private.'/'.$photo2,'fixture');file_put_contents($private.'/'.$licence2,'fixture');
    sr_profile_submit($pdo,$a,['name'=>'CI Rider','vehicle_type'=>'bike','vehicle_number'=>'UP66AB1234'],$photo2,$licence2);
    ic(sr_profile($pdo,(int)$a['id'])['status']==='pending','Resubmission pending');
    // Existing rider history must attach, not duplicate or change rates.
    $pdo->prepare('INSERT INTO riders (name,mobile,pass_hash,rate,extra_rate) VALUES (?,?,?,?,?)')->execute(['Old rider',$a['mobile'],password_hash('ci-code',PASSWORD_DEFAULT),45,25]);$rid=(int)$pdo->lastInsertId();
    $_SESSION=$adminSession;
    denied(fn()=>sr_profile_review($pdo,(int)$a['id'],1,'approved',''),'stale review');
    $p=sr_profile($pdo,(int)$a['id']);sr_profile_review($pdo,(int)$a['id'],(int)$p['revision'],'approved','Verified');
    $_SESSION=$riderSession;ic(!rider_me($pdo),'Customer mode stays customer');
    $_SESSION['sr_mode']='rider';$r=rider_me($pdo);ic($r && (int)$r['id']===$rid && (int)$r['rate']===45,'Existing rider and earnings preserved');
    ic((int)$r['do_delivery']===1&&(int)$r['do_ride']===1&&(int)$r['ride_ok']===1,'Both types enabled');
    denied(fn()=>sr_identity_signin($pdo,['uid'=>'different-uid','mobile'=>$a['mobile']]),'phone merge');
    denied(fn()=>sr_identity_signin($pdo,['uid'=>'ci-rider','mobile'=>'9000000003']),'UID phone change');
    $_SESSION=$adminSession;$p=sr_profile($pdo,(int)$a['id']);sr_profile_review($pdo,(int)$a['id'],(int)$p['revision'],'suspended','Office review');
    $_SESSION=$riderSession;$_SESSION['sr_mode']='rider';ic(!rider_me($pdo),'Suspension invalidates rider access');
    ic(!sr_identity_rider_allowed($pdo,$rid),'Suspended rider cannot receive work');
    ic(job_give($pdo,999999,$rid)[0]===false,'Manual dispatch blocked');
    $pdo->prepare('UPDATE sr_accounts SET session_version=session_version+1 WHERE id=?')->execute([$a['id']]);ic(!sr_account($pdo),'Session revocation');
    $_SESSION=$adminSession;$_SESSION['sr_auth_until']=time()-1;ic(!sr_identity_admin($pdo),'Expired admin denied');
    echo "Identity migration, linking, approval, modes, suspension and revocation passed\n";
} finally {
    foreach($ids as $id){$pdo->prepare('DELETE FROM sr_identity_audit WHERE account_id=? OR actor_id=?')->execute([$id,$id]);$pdo->prepare('DELETE FROM sr_rider_profiles WHERE account_id=?')->execute([$id]);}
    if($rid)$pdo->prepare('DELETE FROM riders WHERE id=?')->execute([$rid]);
    foreach($ids as $id)$pdo->prepare('DELETE FROM sr_accounts WHERE id=?')->execute([$id]);
    foreach(glob($private.'/*') as $file)unlink($file);rmdir($private);
}
