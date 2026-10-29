<?php
require_once __DIR__.'/login-limit.php';
// Passwords and device tokens never enter localStorage or client JavaScript.
function sr_access_upgrade(PDO $pdo): void {
    static $done=false; if($done) return;
    $sql=file_get_contents(__DIR__.'/../sql/004-password-devices.sql');
    foreach(explode(';',$sql) as $statement) if(trim($statement)!=='') $pdo->exec($statement);
    $done=true;
}
function sr_device_cookie(string $value, int $expires): void {
    if(headers_sent()) return;
    setcookie('SRDEVICE',$value,['expires'=>$expires,'path'=>function_exists('u') ? u('/') : '/',
        'secure'=>(!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off'),
        'httponly'=>true,'samesite'=>'Lax']);
}
function sr_remember_login(PDO $pdo,int $id,int $version): void {
    sr_access_upgrade($pdo);
    sr_forget_login($pdo);
    $token=bin2hex(random_bytes(32)); $expires=time()+30*86400;
    $pdo->prepare('DELETE FROM sr_device_logins WHERE expires_at<NOW()')->execute();
    $pdo->prepare('INSERT INTO sr_device_logins(token_hash,account_id,session_version,expires_at) VALUES(?,?,?,?)')
        ->execute([hash('sha256',$token),$id,$version,date('Y-m-d H:i:s',$expires)]);
    sr_device_cookie($token,$expires); $_COOKIE['SRDEVICE']=$token;
}
function sr_restore_login(PDO $pdo): bool {
    $token=(string)($_COOKIE['SRDEVICE'] ?? '');
    if(!preg_match('/^[a-f0-9]{64}$/D',$token)) return false;
    sr_access_upgrade($pdo);
    $q=$pdo->prepare('SELECT a.* FROM sr_device_logins d JOIN sr_accounts a ON a.id=d.account_id WHERE d.token_hash=? AND d.expires_at>NOW() AND a.active=1 AND a.session_version=d.session_version');
    $q->execute([hash('sha256',$token)]); $a=$q->fetch();
    if(!$a) { sr_device_cookie('',time()-3600); unset($_COOKIE['SRDEVICE']); return false; }
    session_regenerate_id(true);
    $_SESSION['sr_account']=(int)$a['id']; $_SESSION['sr_account_version']=(int)$a['session_version'];
    $_SESSION['sr_auth_until']=time()+12*3600;
    unset($_SESSION['sr_verified_at']);
    return true;
}
function sr_forget_login(PDO $pdo): void {
    $token=(string)($_COOKIE['SRDEVICE'] ?? '');
    if(preg_match('/^[a-f0-9]{64}$/D',$token)) {
        $pdo->prepare('DELETE FROM sr_device_logins WHERE token_hash=?')->execute([hash('sha256',$token)]);
    }
    sr_device_cookie('',time()-3600); unset($_COOKIE['SRDEVICE']);
}
function sr_password_valid(string $password): bool {
    return strlen($password)>=8 && strlen($password)<=72 && trim($password)!=='';
}
function sr_password_signin(PDO $pdo,string $mobile,string $password): bool {
    sr_access_upgrade($pdo);
    $scope='password:'.$mobile;
    if(try_blocked($pdo,$scope)) return false;
    $q=$pdo->prepare('SELECT a.*,p.pass_hash FROM sr_accounts a JOIN sr_passwords p ON p.account_id=a.id WHERE a.mobile=? AND a.active=1');
    $q->execute([$mobile]); $a=$q->fetch();
    $dummy='$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.';
    $valid=password_verify($password,$a['pass_hash'] ?? $dummy);
    if(!$a || !$valid) return false;
    $lang=$_SESSION['sr_lang'] ?? 'hi'; $intent=$_SESSION['sr_intent'] ?? null;
    $_SESSION=[]; session_regenerate_id(true);
    $_SESSION['sr_lang']=$lang; $_SESSION['sr_intent']=$intent;
    $_SESSION['sr_account']=(int)$a['id']; $_SESSION['sr_account_version']=(int)$a['session_version'];
    $_SESSION['sr_auth_until']=time()+12*3600; $_SESSION['sr_verified_at']=time();
    try_ok($pdo,$scope); sr_remember_login($pdo,(int)$a['id'],(int)$a['session_version']);
    return true;
}
function sr_password_save(PDO $pdo,array $a,string $current,string $password,string $confirm): void {
    sr_access_upgrade($pdo);
    if(!sr_password_valid($password) || !hash_equals($password,$confirm)) throw new RuntimeException('Password mismatch');
    if(try_blocked($pdo,'password-change:'.$a['id'])) throw new RuntimeException('Rate limit');
    $pdo->beginTransaction();
    try {
        $pdo->prepare('SELECT id FROM sr_accounts WHERE id=? FOR UPDATE')->execute([$a['id']]);
        $q=$pdo->prepare('SELECT pass_hash FROM sr_passwords WHERE account_id=?'); $q->execute([$a['id']]); $hash=$q->fetchColumn();
        $recent=(int)($_SESSION['sr_verified_at'] ?? 0)>time()-600;
        if(($hash && !password_verify($current,$hash) && !$recent) || (!$hash && !$recent)) throw new RuntimeException('Reauthenticate');
        $pdo->prepare('INSERT INTO sr_passwords(account_id,pass_hash) VALUES(?,?) ON DUPLICATE KEY UPDATE pass_hash=VALUES(pass_hash)')
            ->execute([$a['id'],password_hash($password,PASSWORD_DEFAULT)]);
        $pdo->prepare('UPDATE sr_accounts SET session_version=session_version+1 WHERE id=?')->execute([$a['id']]);
        $pdo->prepare('DELETE FROM sr_device_logins WHERE account_id=?')->execute([$a['id']]);
        $q=$pdo->prepare('SELECT session_version FROM sr_accounts WHERE id=?'); $q->execute([$a['id']]); $version=(int)$q->fetchColumn();
        $pdo->commit();
    } catch(Throwable $e) { if($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
    $_SESSION['sr_account_version']=$version; $_SESSION['sr_verified_at']=time();
    session_regenerate_id(true); sr_remember_login($pdo,(int)$a['id'],$version);
    try_ok($pdo,'password-change:'.$a['id']);
}
