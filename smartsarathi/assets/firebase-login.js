// Pinned CDN modules: no npm or Node runtime on shared hosting.
const config = JSON.parse(document.getElementById('firebase-login-config').textContent);
const hi = config.lang === 'hi';
const tr = (en, hindi) => hi ? hindi : en;
const phoneForm = document.getElementById('phone-form');
const otpForm = document.getElementById('otp-form');
const phone = document.getElementById('phone');
const send = document.getElementById('send-otp');
const verify = document.getElementById('verify-otp');
const status = document.getElementById('auth-status');
let auth, attestation, recaptcha, confirmation, sdk, busy = false, nextSend = 0;
const say = message => { status.textContent = message; };
function errorMessage(error) {
  const messages = {
    'auth/invalid-verification-code': tr('Incorrect code. Try again.', 'कोड सही नहीं है। दोबारा कोशिश कीजिए।'),
    'auth/code-expired': tr('Code expired. Request another OTP.', 'कोड पुराना हो गया। नया OTP माँगिए।'),
    'auth/session-expired': tr('Code expired. Request another OTP.', 'कोड पुराना हो गया। नया OTP माँगिए।'),
    'auth/too-many-requests': tr('Too many attempts. Wait before trying again.', 'बहुत कोशिश हुई। कुछ देर बाद कोशिश कीजिए।'),
    'auth/quota-exceeded': tr('SMS is unavailable. Please call the office.', 'अभी SMS नहीं भेज सकते। दफ़्तर को फ़ोन कीजिए।'),
    'auth/network-request-failed': tr('Check your connection and try again.', 'इंटरनेट जाँचकर दोबारा कोशिश कीजिए।'),
    'auth/invalid-phone-number': tr('Enter a valid 10-digit mobile number.', 'सही 10 अंक का मोबाइल नंबर भरिए।'),
    'auth/captcha-check-failed': tr('Verification failed. Please try again.', 'सुरक्षा जाँच नहीं हुई। दोबारा कोशिश कीजिए।')
  };
  return messages[error.code] || tr('Sign-in could not finish. Try again or call the office.', 'लॉगिन पूरा नहीं हुआ। दोबारा कोशिश या दफ़्तर को फ़ोन कीजिए।');
}
async function initialise() {
  const app = await import('https://www.gstatic.com/firebasejs/12.19.0/firebase-app.js');
  sdk = await import('https://www.gstatic.com/firebasejs/12.19.0/firebase-auth.js');
  const instance = app.initializeApp(config.firebase);
  // App Check is optional: enabled only when a reCAPTCHA Enterprise site key is configured.
  if (config.appCheckSiteKey) {
    const check = await import('https://www.gstatic.com/firebasejs/12.19.0/firebase-app-check.js');
    if (!attestation) attestation = check.initializeAppCheck(instance, {
      provider: new check.ReCaptchaEnterpriseProvider(config.appCheckSiteKey),
      isTokenAutoRefreshEnabled: true
    });
    await check.getToken(attestation);
  }
  auth = sdk.getAuth(instance);
  auth.languageCode = hi ? 'hi' : 'en';
  // Never silently turn a remembered browser credential into a new PHP login.
  await sdk.setPersistence(auth, sdk.browserSessionPersistence);
  await sdk.signOut(auth);
}
async function sendCode(event) {
  event.preventDefault();
  if (busy || !phoneForm.reportValidity()) return;
  if (Date.now() < nextSend) { say(tr('Please wait one minute before resending.', 'नया OTP माँगने से पहले एक मिनट रुकिए।')); return; }
  busy = true; send.disabled = true;
  try {
    if (!auth) await initialise();
    // Browser-profile guard, not a tamper-proof physical device identifier.
    // Web Locks serializes tabs; Firebase's blocking hook enforces phone limits.
    const reserve = () => {
      const now = Date.now();
      const key = 'sr-otp-attempts-v1';
      const previous = JSON.parse(localStorage.getItem(key) || '[]');
      if (!Array.isArray(previous)) throw new Error('Invalid attempt history');
      const recent = previous.filter(t => Number.isFinite(t) && t > now - 3600000);
      if (recent.length >= 5 || recent.some(t => t > now - 60000)) {
        throw {code: 'auth/too-many-requests'};
      }
      localStorage.setItem(key, JSON.stringify([...recent, now]));
    };
    if (navigator.locks) await navigator.locks.request('sr-otp-send', reserve);
    else reserve();
    nextSend = Date.now() + 60000;
    confirmation = null;
    if (recaptcha) recaptcha.clear();
    recaptcha = new sdk.RecaptchaVerifier(auth, 'recaptcha', { size: 'invisible' });
    say(tr('Sending OTP…', 'OTP भेज रहे हैं…'));
    confirmation = await sdk.signInWithPhoneNumber(auth, '+91' + phone.value, recaptcha);
    nextSend = Date.now() + 60000;
    otpForm.hidden = false;
    phone.readOnly = true;
    document.getElementById('sms-code').value = '';
    document.getElementById('sms-code').focus();
    say(tr('SMS sent. Enter the code. You can resend after one minute.', 'SMS भेज दिया। कोड भरिए। एक मिनट बाद नया OTP भेज सकते हैं।'));
  } catch (error) {
    say(errorMessage(error));
    if (recaptcha) { recaptcha.clear(); recaptcha = null; }
  } finally { busy = false; send.disabled = false; }
}
phoneForm.addEventListener('submit', sendCode);
// v3.2: 6 अंक पूरे होते ही अपने-आप जाँच (बटन दबाने की ज़रूरत नहीं; बटन फिर भी रहता है)
document.getElementById('sms-code').addEventListener('input', event => {
  const box = event.target;
  box.value = box.value.replace(/\D/g, '').slice(0, 6);
  if (box.value.length === 6 && !busy && confirmation) otpForm.requestSubmit ? otpForm.requestSubmit() : verify.click();
});
otpForm.addEventListener('submit', async event => {
  event.preventDefault();
  if (busy || !confirmation || !otpForm.reportValidity()) return;
  busy = true; verify.disabled = true; send.disabled = true;
  try {
    const result = await confirmation.confirm(document.getElementById('sms-code').value);
    const token = await result.user.getIdToken(true);
    const response = await fetch(config.endpoint, {method: 'POST', credentials: 'same-origin',
      body: new URLSearchParams({csrf: config.csrf, token})});
    const data = await response.json();
    if (!response.ok || !data.ok) throw new Error('Session rejected');
    try { localStorage.setItem('sr_last_phone', phone.value); } catch (e) {}
    window.location.assign(data.next);
  } catch (error) { say(errorMessage(error)); }
  finally { busy = false; verify.disabled = false; send.disabled = false; }
});
document.getElementById('change-phone').addEventListener('click', () => {
  if (busy) return;
  confirmation = null; otpForm.hidden = true; phone.readOnly = false; phone.focus(); say('');
});

// Returning user: one tap on the remembered number sends the OTP straight away.
(function quickContinue() {
  let last = '';
  try { last = localStorage.getItem('sr_last_phone') || ''; } catch (e) { return; }
  if (!/^[6-9][0-9]{9}$/.test(last) || phone.value) return;
  const card = document.createElement('div');
  card.className = 'sr-quick';
  const go = document.createElement('button');
  go.type = 'button'; go.className = 'sr-quick-go';
  go.innerHTML = '<span class="sr-quick-ic" aria-hidden="true">↻</span><span><b></b><small></small></span><span aria-hidden="true">→</span>';
  go.querySelector('b').textContent = tr('Continue as', 'इसी नंबर से जारी रखें');
  go.querySelector('small').textContent = '+91 ' + last.slice(0, 5) + ' ' + last.slice(5);
  const other = document.createElement('button');
  other.type = 'button'; other.className = 'sr-quick-other';
  other.textContent = tr('Use another number', 'दूसरा नंबर डालें');
  card.append(go, other);
  phoneForm.before(card);
  phoneForm.hidden = true;
  go.addEventListener('click', () => { phone.value = last; phoneForm.hidden = false; card.remove(); phoneForm.requestSubmit(); });
  other.addEventListener('click', () => { phoneForm.hidden = false; card.remove(); phone.focus(); });
  const css = document.createElement('style');
  css.textContent = '.sr-quick{display:grid;gap:10px;margin:8px 0 14px}.sr-quick-go{display:flex;align-items:center;gap:12px;width:100%;min-height:64px;padding:10px 16px;border:0;border-radius:16px;background:#0B1F3A;color:#fff;text-align:left;font:inherit;cursor:pointer}.sr-quick-go b{display:block;font-size:15px}.sr-quick-go small{display:block;font-size:17px;font-weight:800;letter-spacing:.5px;color:#10C98F}.sr-quick-go>span:nth-child(2){flex:1}.sr-quick-ic{width:38px;height:38px;border-radius:50%;background:#10C98F;color:#0B1F3A;display:grid;place-items:center;font-weight:900}.sr-quick-other{min-height:48px;border:1px solid #D5DCE5;border-radius:14px;background:#fff;color:#0B1F3A;font:inherit;font-weight:700;cursor:pointer}';
  document.head.append(css);
})();
