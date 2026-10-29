<?php
require_once __DIR__.'/public.php';
header('Cache-Control: private, no-store');
$_SESSION['sr_intent']=$sr_login_kind;
$isPartner=$sr_login_kind==='partner';
if (sr_account($pdo)) redirect(sr_home_path($pdo));
$loginError='';
if ($_SERVER['REQUEST_METHOD']==='POST' && post('do')==='password') {
    $mobile=preg_replace('/\D/','',(string)post('mobile'));
    if(csrf_ok() && preg_match('/^[6-9][0-9]{9}$/D',$mobile) && sr_password_signin($pdo,$mobile,(string)post('password'))) redirect(sr_home_path($pdo));
    $loginError=t('Mobile or password is incorrect, or too many attempts. Try again later or use OTP.','मोबाइल या पासवर्ड सही नहीं है, या बहुत कोशिश हुई। थोड़ी देर बाद कोशिश करें या OTP चुनें।');
}
$config=sr_firebase_config();
$appCheckSiteKey=defined('SR_FIREBASE_APP_CHECK_SITE_KEY') ? (string)SR_FIREBASE_APP_CHECK_SITE_KEY : '';
// App Check is optional (owner decision 28 Oct 2026): used only when a site key is configured.
$ready=!in_array('',array_values($config),true);
sr_customer_head($isPartner ? t('Sarathi Partner login / register','सारथी पार्टनर लॉगिन / रजिस्टर') : t('Customer login','ग्राहक लॉगिन'),t('Use your mobile and password, or verify with OTP.','मोबाइल और पासवर्ड से प्रवेश करें, या OTP चुनें।'),$isPartner ? '/sarathi.php' : '/ghar.php');
?>
<section class="sx-card sx-form" id="password-login" data-login-kind="<?= h($sr_login_kind) ?>">
<h2><?= h(($_SESSION['sr_intent'] ?? '')==='partner' ? t('Partner login','पार्टनर लॉगिन') : t('Customer login','ग्राहक लॉगिन')) ?></h2>
<?php if($loginError): ?><p role="alert"><?= h($loginError) ?></p><?php endif; ?>
<form method="post" class="sx-form">
<input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="do" value="password">
<label class="sx-field"><span><?= h(t('Mobile number','मोबाइल नंबर')) ?></span><input name="mobile" type="tel" inputmode="numeric" pattern="[6-9][0-9]{9}" maxlength="10" autocomplete="username" required></label>
<label class="sx-field"><span><?= h(t('Password','पासवर्ड')) ?></span><input name="password" type="password" minlength="8" maxlength="72" autocomplete="current-password" required></label>
<button class="sx-btn sx-btn-mint" type="submit"><?= h(t('Log in','लॉगिन करें')) ?></button>
</form>
<p><?= h(t('New account or forgot password? Use OTP below. After login, set your password in Settings. This phone stays signed in for up to 30 days; sign out on shared phones.','नया खाता या पासवर्ड भूल गए? नीचे OTP चुनें। लॉगिन के बाद सेटिंग्स में पासवर्ड बनाएँ। इस फ़ोन पर 30 दिन तक लॉगिन रहेगा; साझा फ़ोन पर लॉगआउट करें।')) ?></p>
</section>

<section class="sx-card sx-form" id="phone-login">
<h2><?= h(t('Mobile OTP login','मोबाइल OTP लॉगिन')) ?></h2>
<?php if(!$ready): ?><p role="alert"><?= h(t('Phone login is being configured. Please call the office.','मोबाइल लॉगिन की तैयारी बाकी है। दफ़्तर को फ़ोन कीजिए।')) ?></p><?php else: ?>
<form id="phone-form">
<label class="sx-field"><span><?= h(t('Mobile number (+91)','मोबाइल नंबर (+91)')) ?></span><input id="phone" type="tel" inputmode="numeric" autocomplete="tel-national" pattern="[6-9][0-9]{9}" maxlength="10" required></label>
<p class="sx-fine" style="margin:0;font-size:12.5px"><?= h(t('By continuing you agree to get a login SMS. Google handles your number for sign-in and abuse prevention.','आगे बढ़ने पर लॉगिन SMS आएगा। लॉगिन और सुरक्षा के लिए आपका नंबर Google संभालता है।')) ?> <a href="<?= h(u('/privacy.php')) ?>"><?= h(t('Privacy','प्राइवेसी')) ?></a></p>
<button id="send-otp" class="sx-btn sx-btn-mint" type="submit"><?= h(t('Send OTP','OTP भेजिए')) ?></button>
</form>
<form id="otp-form" hidden>
<label class="sx-field"><span><?= h(t('6-digit SMS code','SMS का 6 अंक का कोड')) ?></span><input id="sms-code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" required></label>
<button id="verify-otp" class="sx-btn sx-btn-mint" type="submit"><?= h(t('Verify and sign in','जाँचकर लॉगिन कीजिए')) ?></button>
<button id="change-phone" class="sx-pill" type="button"><?= h(t('Change number','नंबर बदलिए')) ?></button>
</form>
<div id="recaptcha"></div><p id="auth-status" role="status" aria-live="polite"></p>
<noscript><?= h(t('Enable JavaScript to use secure phone login.','सुरक्षित मोबाइल लॉगिन के लिए JavaScript चालू कीजिए।')) ?></noscript>
<script id="firebase-login-config" type="application/json"><?= json_encode(['firebase'=>$config,'appCheckSiteKey'=>$appCheckSiteKey,'endpoint'=>u('/auth.php'),'intent'=>$sr_login_kind,'csrf'=>csrf(),'lang'=>sr_lang()],JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?></script>
<script type="module" src="<?= h(u('/assets/firebase-login.js')) ?>?v=5"></script>
<?php endif; ?></section>
</main><footer class="sc-help"><a class="sx-pill" href="<?= h(u('/')) ?>"><?= h(t('Back to welcome','स्वागत पेज पर वापस')) ?></a></footer><?php require_once __DIR__.'/companion.php'; sr_companion(); ?></body></html>
