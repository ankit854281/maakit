<?php
require_once __DIR__.'/salon.php';
require_once __DIR__.'/../smartsarathi/inc/fast2sms.php';
/** No role comes from an OTP. Resolve only existing approved identities. */
function mk_otp_account(PDO $pdo, $role, $mobile) {
    if ($role === 'team') {
        $p = $_SESSION['sms_team_pending'] ?? [];
        if (($p['expires'] ?? 0) < time()) return null;
        $q=$pdo->prepare('SELECT * FROM users WHERE id=? AND active=1'); $q->execute([$p['id']]);
        $r=$q->fetch();
        return $r && f2s_mobile($r['mobile'] ?? '') === $mobile && hash_equals($p['fingerprint'], hash('sha256',$r['password'])) ? ['kind'=>'team','row'=>$r] : null;
    }
    if ($role === 'customer') {
        $q=$pdo->prepare('SELECT * FROM customers WHERE mobile=?'); $q->execute([$mobile]); $r=$q->fetch();
        return !$r ? ['kind'=>'new','row'=>['id'=>0,'mobile'=>$mobile]] : (!empty($r['active']) ? ['kind'=>'customer','row'=>$r] : null);
    }
    if ($role === 'vehicle') {
        // Gaadi wale (transport.php): wahi number jo unke transport khate me hai
        $q=$pdo->prepare("SELECT * FROM transports WHERE mobile=? AND status<>'hidden' AND business_id IS NOT NULL"); $q->execute([$mobile]); $rows=$q->fetchAll();
        return count($rows)===1 ? ['kind'=>'vehicle','row'=>$rows[0]] : null;
    }
    $q=$pdo->prepare("SELECT * FROM businesses WHERE mobile=? AND status='approved'"); $q->execute([$mobile]); $rows=$q->fetchAll();
    $q=$pdo->prepare("SELECT * FROM users WHERE username=? AND role='vendor' AND active=1"); $q->execute([$mobile]); $vendors=$q->fetchAll();
    if (count($rows)+count($vendors)!==1) return null; // shared number: use existing code door, never guess shop
    return $rows ? ['kind'=>'shop','row'=>$rows[0]] : ['kind'=>'vendor','row'=>$vendors[0]];
}
function mk_otp_subject($a) {
    return $a['kind'].':'.$a['row']['id'].':'.substr(hash('sha256',($a['row']['password'] ?? $a['row']['access_code'] ?? '').'|'.$a['row']['mobile']),0,40);
}
function mk_otp_finish(PDO $pdo, $a, $name) {
    $r=$a['row'];
    if ($a['kind']==='new') {
        [$ok,$message]=cust_register($pdo,$name,$r['mobile'],bin2hex(random_bytes(24)),'','',true);
        if (!$ok) throw new RuntimeException('Account creation not completed');
        $_SESSION['sms_customer_recovery']=['id'=>cust()['id'],'expires'=>time()+300];
        redirect('/account.php');
    }
    if ($a['kind']==='customer') { cust_set($r); $_SESSION['sms_customer_recovery']=['id'=>$r['id'],'expires'=>time()+300]; redirect('/account.php'); }
    if ($a['kind']==='shop') { shop_start_session($pdo,$r['id']); redirect('/shop.php'); }
    if ($a['kind']==='vehicle') { shop_start_session($pdo,(int)$r['business_id']); redirect('/transport.php'); }
    if ($a['kind']==='team') auth_attempt_ok($pdo,'team',$r['username']);
    Maakit\Api\revoke_legacy_sessions($pdo); session_regenerate_id(true);
    unset($_SESSION['sms_team_pending']);
    $_SESSION['user']=['id'=>$r['id'],'name'=>$r['name'],'role'=>$r['role']];
    redirect(panel_home($r['role']));
}
