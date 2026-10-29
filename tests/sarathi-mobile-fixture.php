<?php
// CLI-only fixture builder. Never install this fixture server on hosting.
if(PHP_SAPI!=='cli' || !getenv('SR_TEST_DB_HOST'))throw new RuntimeException('CI only');
$root=sys_get_temp_dir().'/sarathi-mobile-'.bin2hex(random_bytes(5));mkdir($root);
$src=dirname(__DIR__).'/smartsarathi';
$it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($src,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::SELF_FIRST);
mkdir($root.'/smartsarathi');
foreach($it as $file){$rel=substr($file->getPathname(),strlen($src));if(str_contains($rel,'/config.php')||str_contains($rel,'/uploads/'))continue;$dest=$root.'/smartsarathi'.$rel;if($file->isDir())mkdir($dest);else copy($file->getPathname(),$dest);}
// Replace only the copied connection preamble. Real repo/private config stays untouched.
$fn=file_get_contents($src.'/inc/fn.php');$fn='<?php require __DIR__."/../../fixture-bootstrap.php";' . "\n" . substr($fn,strpos($fn,'// ---------- panne ka pata'));
file_put_contents($root.'/smartsarathi/inc/fn.php',$fn);
$bootstrap= <<<'BOOT'
<?php
if(!getenv('SR_TEST_DB_HOST'))exit;
define('SR_FIREBASE_ENABLED',true);define('SR_FIREBASE_ADMIN_MOBILES',['9000000021']);
define('SR_PHONE','+919000000021');define('SR_PHONE_SHOW','90000 00021');
$pdo=new PDO('mysql:host='.getenv('SR_TEST_DB_HOST').';dbname=sarathi_identity_test;charset=utf8mb4','root',getenv('SR_TEST_DB_PASSWORD'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
session_start();
$fixture=$_GET['fixture']??'rider';
$q=$pdo->prepare('SELECT * FROM sr_accounts WHERE firebase_uid=?');$q->execute(['ui-'.$fixture]);$account=$q->fetch();
$_SESSION=['sr_lang'=>($_GET['lang']??'hi'),'sr_mode'=>'rider'];
if($account){$_SESSION['sr_account']=$account['id'];$_SESSION['sr_account_version']=1;$_SESSION['sr_auth_until']=time()+3600;}
BOOT;
file_put_contents($root.'/fixture-bootstrap.php',$bootstrap);
require $root.'/fixture-bootstrap.php';
require_once __DIR__.'/../smartsarathi/inc/lang.php';require_once __DIR__.'/../smartsarathi/inc/client.php';
sr_jobs_upgrade($pdo);
foreach(['admin'=>'9000000021','rider'=>'9000000022','new'=>'9000000023'] as $kind=>$mobile){
    $pdo->prepare('INSERT INTO sr_accounts(firebase_uid,mobile) VALUES (?,?)')->execute(['ui-'.$kind,$mobile]);
}
$q=$pdo->query("SELECT * FROM sr_accounts WHERE firebase_uid='ui-rider'");$a=$q->fetch();
sr_profile_submit($pdo,$a,['name'=>str_repeat('लंबा नाम ',6),'vehicle_type'=>'bike'],'','');
$admin=$pdo->query("SELECT * FROM sr_accounts WHERE firebase_uid='ui-admin'")->fetch();
$_SESSION=['sr_account'=>$admin['id'],'sr_account_version'=>1,'sr_auth_until'=>time()+3600];sr_profile_review($pdo,(int)$a['id'],1,'approved','Fixture');
$rid=(int)sr_profile($pdo,(int)$a['id'])['rider_id'];duty_on($pdo,$rid);
$pdo->exec("INSERT INTO clients(name,fee) VALUES ('UI client',25)");$client=$pdo->query('SELECT * FROM clients WHERE id='.(int)$pdo->lastInsertId())->fetch();
job_create($pdo,$client,['ref'=>'UI-bike','kind'=>'ride','vehicle'=>'bike','drop_name'=>'UI Customer','drop_mobile'=>'9000000024','drop_address'=>str_repeat('लंबा पता ',20),'pick_address'=>'Bhadohi station','pick_otp'=>'1234','drop_code'=>'5678']);
$new=$pdo->query("SELECT * FROM sr_accounts WHERE firebase_uid='ui-new'")->fetch();
sr_profile_submit($pdo,$new,['name'=>'Pending applicant','vehicle_type'=>'bike'],'','');
file_put_contents('/tmp/sarathi-mobile-root',$root);
echo "Isolated UI fixture prepared\n";
