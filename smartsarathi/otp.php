<?php
require_once __DIR__.'/inc/fn.php';
require_once __DIR__.'/inc/lang.php';
require_once __DIR__.'/inc/kaam.php';
require_once __DIR__.'/inc/public.php';
require_once __DIR__.'/inc/fast2sms.php';
if (sr_firebase_enabled()) redirect('/');
$otp_role = get('role') === 'admin' ? 'admin' : 'rider';
$otp_account = function($mobile) use ($pdo,$otp_role) {
    if ($otp_role === 'admin') {
        $p=$_SESSION['sms_admin_pending'] ?? [];
        if (($p['expires'] ?? 0)<time() || !defined('SR_ADMIN_KEY') || !hash_equals($p['fingerprint'],hash('sha256',SR_ADMIN_KEY))
            || $mobile !== f2s_mobile((string)f2s_config('FAST2SMS_ADMIN_MOBILE'))) return null;
        return ['kind'=>'admin','row'=>['id'=>0,'mobile'=>$mobile,'pass_hash'=>hash('sha256',SR_ADMIN_KEY)]];
    }
    $q=$pdo->prepare('SELECT * FROM riders WHERE mobile=? AND active=1');$q->execute([$mobile]);$rows=$q->fetchAll();
    return count($rows)===1 ? ['kind'=>'rider','row'=>$rows[0]] : null;
};
$otp_subject = function($a) {return $a['kind'].':'.$a['row']['id'].':'.substr(hash('sha256',$a['row']['pass_hash']),0,40);};
$otp_finish = function($a,$name) use ($pdo) {
    if ($a['kind']==='admin') { try_ok($pdo,'admin'); session_regenerate_id(true); unset($_SESSION['sms_admin_pending']); $_SESSION['sr_admin']=1;redirect('/admin/'); }
    rider_start_session($a['row']);redirect('/kaam.php');
};
$otp_back=u('/');
require __DIR__.'/inc/otp-page.php';
