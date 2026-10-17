<?php
require_once __DIR__.'/inc/fn.php';
require_once __DIR__.'/inc/customer.php';
require_once __DIR__.'/inc/email-otp.php';
header('Cache-Control: no-store, private');
header('X-Robots-Tag: noindex, nofollow');
$err=''; $info=''; $flow=$_SESSION['email_flow'] ?? null;
if ($flow && $flow['expires']<=time()) { unset($_SESSION['email_flow']); $flow=null; }
if (!isset($_SESSION['email_binding'])) $_SESSION['email_binding']=bin2hex(random_bytes(32));
$binding=$_SESSION['email_binding']; $service=new MaakitEmailOtp($pdo); $me=cust();
if ($_SERVER['REQUEST_METHOD']==='POST') {
    if (!csrf_ok()) $err=t('Refresh this page and try again.','पन्ना दोबारा खोलकर कोशिश कीजिए।');
    elseif (!mk_email_enabled()) $err=t('Email sign-in is not enabled yet.','Email लॉगिन अभी चालू नहीं है।');
    else try {
        mk_email_install($pdo); $service->install();
        if (post('action')==='change') {
            if ($flow) $service->invalidate($flow['id']);
            unset($_SESSION['email_flow']); $flow=null;
        } elseif (post('action')==='send') {
            $email=mk_email_address(post('email')); $mode=$me?'link':(post('mode')==='register'?'register':'login');
            $name=trim(post('name')); $mobile=f2s_mobile(post('mobile'));
            $subject='';
            if (!$email) throw new RuntimeException('invalid');
            if ($mode==='link') {
                $q=$pdo->prepare('SELECT * FROM customers WHERE id=? AND active=1'); $q->execute([$me['id']]); $c=$q->fetch();
                // Reauthenticate before adding a new login credential.
                if (!$c || !password_verify(post('password'),$c['password'])) throw new RuntimeException('invalid');
                $subject='link:'.mk_email_fingerprint($c);
            } elseif ($mode==='register') {
                if (!$mobile || mb_strlen($name)<2 || mb_strlen($name)>80) throw new RuntimeException('invalid');
                $q=$pdo->prepare('SELECT id FROM customers WHERE mobile=?'); $q->execute([$mobile]);
                if ($q->fetchColumn() || mk_email_customer($pdo,$email)) throw new RuntimeException('invalid');
                $subject='register:'.hash('sha256',$mobile.'|'.$name);
            } else {
                $c=mk_email_customer($pdo,$email);
                if ($c) $subject='login:'.mk_email_fingerprint($c);
            }
            $id=$subject!=='' ? $service->send($email,'customer_'.$mode,$subject,$binding,substr($_SERVER['REMOTE_ADDR'] ?? '',0,45)) : null;
            if ($id) {
                if ($flow) $service->invalidate($flow['id']);
                $flow=['id'=>$id,'email'=>$email,'mode'=>$mode,'subject'=>$subject,'name'=>$name,'mobile'=>$mobile,'expires'=>time()+300];
                $_SESSION['email_flow']=$flow;
                $info=t('Email accepted for sending. Check your inbox and spam folder; the code expires in 5 minutes.','Email भेजने के लिए स्वीकार हुआ। Inbox और Spam देखिए; कोड 5 मिनट तक मान्य है।');
            } else $err=t('If this email is linked, try again after a minute. For an existing account, sign in once and link your email in My account.','Email जुड़ा है तो एक मिनट बाद फिर कोशिश करें। पुराने खाते में पहले लॉगिन करके My account से email जोड़िए।');
        } elseif (post('action')==='verify' && $flow) {
            $c=null; $valid=false;
            if ($flow['mode']==='login') {
                $c=mk_email_customer($pdo,$flow['email']);
                $valid=$c && hash_equals($flow['subject'],'login:'.mk_email_fingerprint($c));
            } elseif ($flow['mode']==='link' && $me) {
                $q=$pdo->prepare('SELECT * FROM customers WHERE id=? AND active=1');$q->execute([$me['id']]);$c=$q->fetch();
                $valid=$c && hash_equals($flow['subject'],'link:'.mk_email_fingerprint($c));
            } elseif ($flow['mode']==='register' && !$me) $valid=true;
            if (!$valid || !$service->verify($flow['id'],'customer_'.$flow['mode'],$flow['subject'],$binding,post('otp'))) throw new RuntimeException('invalid');
            unset($_SESSION['email_flow']);
            if ($flow['mode']==='login') {
                cust_set($c); redirect('/account.php');
            }
            $pdo->beginTransaction();
            try {
                if ($flow['mode']==='register') {
                    // A phone contact is not proof of phone ownership. Never merge an existing account or guest history.
                    $q=$pdo->prepare('INSERT INTO customers(name,mobile,password,village,landmark) VALUES (?,?,?,?,?)');
                    $q->execute([$flow['name'],$flow['mobile'],password_hash(bin2hex(random_bytes(32)),PASSWORD_DEFAULT),'','']);
                    $c=['id'=>(int)$pdo->lastInsertId(),'name'=>$flow['name'],'mobile'=>$flow['mobile'],'village'=>'','landmark'=>''];
                } else {
                    $pdo->prepare('DELETE FROM mk_customer_emails WHERE customer_id=?')->execute([$c['id']]);
                }
                $pdo->prepare('INSERT INTO mk_customer_emails(customer_id,email,verified_at) VALUES (?,?,?)')->execute([$c['id'],$flow['email'],time()]);
                $pdo->commit();
            } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
            cust_set($c); flash(t('Email verified. You can now sign in with email OTP.','Email की जाँच हो गई। अब Email OTP से लॉगिन कर सकते हैं।')); redirect('/account.php');
        }
    } catch (Throwable $e) {
        $err=t('Could not verify these details. Check the code, name and number. Existing users must sign in before linking email. Request a new code if it expired or failed five times.','जानकारी जाँच नहीं पाए। कोड, नाम और नंबर जाँचिए। पुराने खाते में email जोड़ने से पहले लॉगिन करें। कोड का समय निकले या पाँच बार गलत हो तो नया कोड मँगाएँ।');
        $flow=$_SESSION['email_flow'] ?? null;
    }
}
$page_title=t('Email OTP — Maakit','Email OTP — Maakit'); $no_tabbar=true; include __DIR__.'/inc/head.php';
?>
<section class="wrap" style="max-width:480px;margin:24px auto"><div class="box lg-form">
<h1><?= h(t('Email OTP','Email OTP')) ?></h1>
<?php if($err): ?><p role="alert"><?= h($err) ?></p><?php endif ?>
<?php if($info): ?><p role="status"><?= h($info) ?></p><?php endif ?>
<?php if(!mk_email_enabled()): ?><p><?= h(t('Email sign-in is awaiting sender setup. Please use the existing login for now.','Email भेजने का setup बाकी है। अभी पुराने तरीके से लॉगिन कीजिए।')) ?></p>
<?php elseif($flow): ?>
<p><?= h(t('Enter the code sent to your email.','अपने email पर आया कोड भरिए।')) ?></p>
<form method="post" class="lg-form"><input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="action" value="verify">
<label class="lg-field"><?= h(t('Six-digit OTP','छह अंकों का OTP')) ?><input name="otp" pattern="[0-9]{6}" maxlength="6" inputmode="numeric" autocomplete="one-time-code" required autofocus></label>
<button class="btn btn-brand"><?= h(t('Verify email','Email जाँचिए')) ?></button></form>
<form method="post"><input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="action" value="change"><button class="btn btn-line"><?= h(t('Resend / change email','दोबारा मँगाएँ / email बदलें')) ?></button></form>
<?php else: ?>
<form method="post" class="lg-form"><input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="action" value="send">
<?php if(!$me): ?><label class="lg-field"><?= h(t('Choose','चुनिए')) ?><select name="mode"><option value="login"><?= h(t('Sign in with linked email','जुड़े हुए email से लॉगिन')) ?></option><option value="register"><?= h(t('Create a new account','नया खाता बनाएँ')) ?></option></select></label><?php endif ?>
<label class="lg-field"><?= h(t('Email address','Email पता')) ?><input type="email" name="email" maxlength="254" autocomplete="email" required></label>
<?php if($me): ?>
<label class="lg-field"><?= h(t('Current password (to link email)','अभी का पासवर्ड (email जोड़ने के लिए)')) ?><input type="password" name="password" autocomplete="current-password" required></label>
<?php else: ?>
<p><?= h(t('For a new account only, enter your name and contact number below. For an existing account, first sign in with your current method and link email from My account.','केवल नए खाते के लिए नीचे नाम और संपर्क नंबर भरिए। पुराने खाते में पहले अपने पुराने तरीके से लॉगिन करके My account से email जोड़िए।')) ?></p>
<label class="lg-field"><?= h(t('Name (new account)','नाम (नया खाता)')) ?><input name="name" maxlength="80" autocomplete="name"></label>
<label class="lg-field"><?= h(t('Contact mobile (new account)','संपर्क मोबाइल (नया खाता)')) ?><input name="mobile" type="tel" pattern="[6-9][0-9]{9}" maxlength="10" autocomplete="tel-national"></label>
<?php endif ?>
<button class="btn btn-brand"><?= h(t('Send email OTP','Email पर OTP भेजिए')) ?></button></form>
<?php endif ?>
<p><a href="/account.php"><?= h(t('Back to My account','My account पर वापस')) ?></a></p>
</div></section>
<?php include __DIR__.'/inc/foot.php'; ?>
