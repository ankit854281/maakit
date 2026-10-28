<?php
require_once __DIR__.'/public.php';
header('Cache-Control: private, no-store');
if (sr_account($pdo)) redirect('/account.php');
$config=sr_firebase_config();
$ready=!in_array('',array_values($config),true);
sr_public_head('login',t('Sign in','लॉगिन'),t('SMART SARATHI','स्मार्ट सारथी'),t('One account.','एक खाता।'),t('Ride or deliver.','सवारी और डिलीवरी।'),t('Sign in with your mobile. Choose Customer or Rider mode after login.','मोबाइल से लॉगिन कीजिए। फिर ग्राहक या राइडर मोड चुनिए।'));
?>
<section class="sx-card sx-form" id="phone-login">
<h2><?= h(t('Mobile OTP login','मोबाइल OTP लॉगिन')) ?></h2>
<?php if(!$ready): ?><p role="alert"><?= h(t('Phone login is being configured. Please call the office.','मोबाइल लॉगिन की तैयारी बाकी है। दफ़्तर को फ़ोन कीजिए।')) ?></p><?php else: ?>
<form id="phone-form">
<label class="sx-field"><span><?= h(t('Mobile number (+91)','मोबाइल नंबर (+91)')) ?></span><input id="phone" type="tel" inputmode="numeric" autocomplete="tel-national" pattern="[6-9][0-9]{9}" maxlength="10" required></label>
<label class="sx-check"><input id="consent" type="checkbox" required><span><?= h(t('I agree to receive a login SMS. Google processes and stores my phone number for authentication and abuse prevention.','मैं लॉगिन SMS पाने के लिए सहमत हूँ। Google मेरे नंबर का उपयोग और संग्रह पहचान व दुरुपयोग रोकने के लिए करता है।')) ?></span></label>
<button id="send-otp" class="sx-btn sx-btn-mint" type="submit"><?= h(t('Send OTP','OTP भेजिए')) ?></button>
</form>
<form id="otp-form" hidden>
<label class="sx-field"><span><?= h(t('6-digit SMS code','SMS का 6 अंक का कोड')) ?></span><input id="sms-code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" required></label>
<button id="verify-otp" class="sx-btn sx-btn-mint" type="submit"><?= h(t('Verify and sign in','जाँचकर लॉगिन कीजिए')) ?></button>
<button id="change-phone" class="sx-pill" type="button"><?= h(t('Change number','नंबर बदलिए')) ?></button>
</form>
<div id="recaptcha"></div><p id="auth-status" role="status" aria-live="polite"></p>
<noscript><?= h(t('Enable JavaScript to use secure phone login.','सुरक्षित मोबाइल लॉगिन के लिए JavaScript चालू कीजिए।')) ?></noscript>
<script id="firebase-login-config" type="application/json"><?= json_encode(['firebase'=>$config,'endpoint'=>u('/auth.php'),'csrf'=>csrf(),'lang'=>sr_lang()],JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?></script>
<script type="module" src="<?= h(u('/assets/firebase-login.js')) ?>?v=1"></script>
<?php endif; ?></section>
<?php sr_public_foot(); ?>
