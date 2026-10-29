<?php
require_once __DIR__.'/public.php';
header('Cache-Control: private, no-store');
if (in_array($_GET['intent'] ?? '', ['customer', 'partner'], true)) $_SESSION['sr_intent'] = $_GET['intent'];
if (sr_account($pdo)) redirect(sr_home_path($pdo));
$config=sr_firebase_config();
$appCheckSiteKey=defined('SR_FIREBASE_APP_CHECK_SITE_KEY') ? (string)SR_FIREBASE_APP_CHECK_SITE_KEY : '';
// App Check is optional (owner decision 28 Oct 2026): used only when a site key is configured.
$ready=!in_array('',array_values($config),true);
sr_customer_head(t('Login','लॉगिन'),t('Just your mobile number and the SMS code.','बस मोबाइल नंबर और SMS कोड।'));
?>
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
<script id="firebase-login-config" type="application/json"><?= json_encode(['firebase'=>$config,'appCheckSiteKey'=>$appCheckSiteKey,'endpoint'=>u('/auth.php'),'csrf'=>csrf(),'lang'=>sr_lang()],JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?></script>
<script type="module" src="<?= h(u('/assets/firebase-login.js')) ?>?v=4"></script>
<?php endif; ?></section>
<?php sr_customer_foot(); ?>
