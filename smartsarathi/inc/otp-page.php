<?php
// Controllers provide fixed role-specific account/session callbacks.
header('Cache-Control: no-store, private'); header('X-Robots-Tag: noindex, nofollow');
$key='sms_flow_'.$otp_role; $err=''; $info='';
$flow=$_SESSION[$key] ?? null;
if ($flow && $flow['expires']<time()) {unset($_SESSION[$key]);$flow=null;}
if (!isset($_SESSION['sms_binding'])) $_SESSION['sms_binding']=bin2hex(random_bytes(32));
$binding=$_SESSION['sms_binding'];
$service=new Fast2SmsOtp($pdo);
if ($_SERVER['REQUEST_METHOD']==='POST') {
    if (!csrf_ok()) $err=t('Page expired. Refresh and try again.','पन्ना दोबारा खोलकर कोशिश कीजिए।');
    elseif (!f2s_enabled()) $err=t('OTP login is not enabled yet. Use the existing sign-in option.','OTP लॉगिन अभी चालू नहीं है। पुराने तरीके से लॉगिन कीजिए।');
    else try {
        $action=is_string($_POST['action'] ?? null)?$_POST['action']:'';
        if ($action==='change') {
            if ($flow) $service->invalidate($flow['id']);
            unset($_SESSION[$key]);$flow=null;
        } elseif ($action==='send') {
            $mobile=f2s_mobile($_POST['mobile'] ?? '');
            $name=is_string($_POST['name'] ?? null)?trim($_POST['name']):'';
            $account=$mobile ? $otp_account($mobile) : null;
            if (!$account || ($account['kind']==='new' && (mb_strlen($name)<2 || mb_strlen($name)>80))) {
                $err=t('Check your mobile number and name. Shop and rider accounts must be approved first.','मोबाइल नंबर और नाम जाँचिए। दुकान और राइडर का खाता पहले मंजूर होना चाहिए।');
            } else {
                $subject=$otp_subject($account);
                $id=$service->send($mobile,$otp_role.'_login',$subject,$binding,substr($_SERVER['REMOTE_ADDR'] ?? '',0,45));
                if (!$id) $err=t('OTP could not be sent. Wait a minute and retry; after several attempts wait an hour or use the existing login.','OTP नहीं भेज पाए। एक मिनट बाद कोशिश करें; कई बार कोशिश की है तो एक घंटे बाद करें या पुराने तरीके से लॉगिन करें।');
                else {
                    if ($flow) $service->invalidate($flow['id']);
                    $flow=['id'=>$id,'mobile'=>$mobile,'subject'=>$subject,'name'=>$name,'expires'=>time()+300];
                    $_SESSION[$key]=$flow;
                    $flow['via']=$service->lastChannel; $_SESSION[$key]=$flow;
                    $info=$service->lastChannel==='whatsapp'
                        ? t('OTP sent on WhatsApp. Enter the six digits within 5 minutes.','OTP आपके WhatsApp पर भेजा है। छह अंक 5 मिनट के भीतर भरिए।')
                        : t('OTP sent by SMS. Enter the six digits within 5 minutes.','OTP SMS से भेजा है। छह अंक 5 मिनट के भीतर भरिए।');
                }
            }
        } elseif ($action==='verify' && $flow) {
            $account=$otp_account($flow['mobile']);
            if ($account && hash_equals($flow['subject'],$otp_subject($account))
                && $service->verify($flow['id'],$otp_role.'_login',$flow['subject'],$binding,$_POST['otp'] ?? '')) {
                unset($_SESSION[$key]);
                $otp_finish($account,$flow['name']);
            } else $err=t('OTP is incorrect, expired or already used. Request a new code after 5 failed attempts.','OTP गलत है, समय निकल गया है या इस्तेमाल हो चुका है। पाँच गलत कोशिशों के बाद नया कोड मँगाइए।');
        }
    } catch (Throwable $e) { $err=t('Could not complete this request. Try again shortly or use the existing login.','अभी काम पूरा नहीं हुआ। थोड़ी देर बाद या पुराने तरीके से लॉगिन कीजिए।'); }
}
$is_sr=defined('SR_BASE');
if ($is_sr) sr_public_head('login',t('OTP login','OTP लॉगिन'), 'SARATHI',t('Secure sign in','सुरक्षित लॉगिन'),'',t('Use the code sent to your phone.','अपने फ़ोन पर आया कोड डालिए।'),[]);
else { $no_tabbar=true;$page_title=t('OTP login — Maakit','OTP लॉगिन — Maakit');include __DIR__.'/../../inc/head.php'; }
?>
<section class="wrap" style="max-width:480px;margin:24px auto;padding:16px">
<div class="<?= $is_sr?'sx-card sx-form':'box lg-form' ?>">
<h1><?= h(t('Sign in with OTP','OTP से लॉगिन')) ?></h1>
<?php if($err): ?><p role="alert"><?= h($err) ?></p><?php endif ?>
<?php if($info): ?><p role="status"><?= h($info) ?></p><?php endif ?>
<?php if(!f2s_enabled()): ?><p><?= h(t('OTP login is not enabled yet.','OTP लॉगिन अभी चालू नहीं है।')) ?></p>
<?php else: ?>
<?php if($flow): ?>
<p><?= h((($flow['via'] ?? '')==='whatsapp' ? t('Check WhatsApp on the number ending in ','WhatsApp देखिए — नंबर के अंतिम चार अंक: ') : t('Code sent to number ending in ','नंबर के अंतिम चार अंक: ')).substr($flow['mobile'],-4)) ?></p>
<form method="post" class="<?= $is_sr?'sx-form':'lg-form' ?>">
<input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="action" value="verify">
<label class="<?= $is_sr?'sx-field':'lg-field' ?>"><?= h(t('Six-digit OTP','छह अंकों का OTP')) ?><input name="otp" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" required autofocus></label>
<button class="<?= $is_sr?'sx-btn sx-btn-mint':'btn btn-brand' ?>"><?= h(t('Verify and sign in','जाँचकर लॉगिन कीजिए')) ?></button>
</form>
<form method="post"><input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="action" value="change"><button class="<?= $is_sr?'sx-btn':'btn btn-line' ?>"><?= h(t('Change number / request again','नंबर बदलें / दोबारा मँगाएँ')) ?></button></form>
<?php else: ?>
<form method="post" class="<?= $is_sr?'sx-form':'lg-form' ?>">
<input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="action" value="send">
<label class="<?= $is_sr?'sx-field':'lg-field' ?>"><?= h(t('Mobile number','मोबाइल नंबर')) ?><input name="mobile" type="tel" inputmode="numeric" autocomplete="tel-national" pattern="[6-9][0-9]{9}" maxlength="10" required></label>
<?php if($otp_role==='customer'): ?><label class="lg-field"><?= h(t('Name (for a new account)','नाम (नए खाते के लिए)')) ?><input name="name" autocomplete="name" maxlength="80"></label><?php endif ?>
<button class="<?= $is_sr?'sx-btn sx-btn-mint':'btn btn-brand' ?>"><?= h(t('Send OTP (WhatsApp / SMS)','OTP भेजिए (WhatsApp / SMS)')) ?></button>
</form>
<?php endif; endif ?>
<p><a href="<?= h($otp_back) ?>"><?= h(t('Back to sign in','लॉगिन पर वापस')) ?></a></p>
</div></section>
<?php if($is_sr) sr_public_foot(); else include __DIR__.'/../../inc/foot.php';
