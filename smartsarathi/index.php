<?php
// ============================================================
//  सारथी — दरवाज़ा
//
//  Delivery wala bhai apna mobile aur code daal kar andar
//  aata hai. Password nahi — gaon me yaad nahi rehta.
// ============================================================
require_once __DIR__ . '/inc/fn.php';
require_once __DIR__ . '/inc/lang.php';
require_once __DIR__ . '/inc/kaam.php';
require_once __DIR__.'/inc/fast2sms.php';
require_once __DIR__ . '/inc/public.php';   // tabs: parcel / track / rider banna

if (rider_me($pdo)) { redirect('/kaam.php'); }

$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_ok()) {
    $mob  = preg_replace('/\D/', '', (string)post('mobile'));
    $code = (string)post('code');

    // Hadd: ek number par 6 koshish, ek jagah se 30 — 15 minute me.
    // Ginti database me rehti hai, session me nahi; warna cookie
    // mitate hi ginti shunya ho jaati.
    if (try_blocked($pdo, $mob)) {
        $err = try_error();
    } elseif ($r = rider_login($pdo, $mob, $code)) {
        try_ok($pdo, $mob);
        rider_start_session($r);
        redirect('/kaam.php');
    } else {
        $err = t('The mobile number or code is incorrect. Ask your office for your login code.', 'नंबर या कोड सही नहीं है। दफ़्तर से अपना कोड पूछ लीजिए।');
    }
}
sr_public_head('login', t('Rider login', 'राइडर लॉगिन'), t('SARATHI PARTNER APP', 'सारथी पार्टनर ऐप'),
    t('Every journey,', 'हर सफ़र के साथ,'), t('a fresh start.', 'एक नई शुरुआत।'),
    t('Jobs, pickup codes, directions and your daily earnings — all in one place.', 'काम, पिकअप कोड, रास्ता और रोज़ की कमाई — सब एक जगह।'),
    [['bolt', t('Jobs come to you', 'काम ख़ुद आपके पास')], ['nav', t('One-tap directions', 'एक टैप में रास्ता')], ['rupee', t('Clear daily earnings', 'रोज़ की साफ़ कमाई')]]);
?>
  <?php if ($err): ?><div class="sx-alert"><?= sr_icon('alert', 18) ?> <?= h(sr_message($err)) ?></div><?php endif; ?>
  <?php if(f2s_enabled()): ?><p><a class="sx-btn sx-btn-mint" href="<?= h(u('/otp.php')) ?>"><?= h(t('SMS OTP login / forgot code','SMS OTP से लॉगिन / कोड भूल गए')) ?></a></p><?php endif ?>
  <form method="post" class="sx-card sx-form" autocomplete="on">
    <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
    <div class="sx-pane-h"><span class="sx-pin a"><?= sr_icon('user', 16) ?></span><div><b><?= h(t('Welcome back', 'फिर से स्वागत है')) ?></b><small><?= h(t('Sign in with your mobile and login code', 'मोबाइल और लॉगिन कोड से अंदर आइए')) ?></small></div></div>
    <label class="sx-field sx-tel"><span><?= h(t('Mobile number', 'मोबाइल नंबर')) ?></span><em>+91</em><input id="m" name="mobile" type="tel" inputmode="numeric" value="<?= h(post('mobile')) ?>" autocomplete="tel-national" required></label>
    <label class="sx-field sx-pass"><span><?= h(t('Login code', 'लॉगिन कोड')) ?></span><input id="c" name="code" type="password" autocomplete="current-password" required>
      <button type="button" class="sx-eye" data-eye aria-label="<?= h(t('Show code', 'कोड दिखाइए')) ?>"><?= sr_icon('eye', 18) ?></button></label>
    <label class="sx-check"><input type="checkbox" data-keep checked><span><?= h(t('Remember my number on this phone', 'इस फ़ोन पर मेरा नंबर याद रखिए')) ?></span></label>
    <button class="sx-btn sx-btn-mint" type="submit"><?= h(t('Start work', 'काम पर चलिए')) ?> <?= sr_icon('arrow', 18) ?></button>
    <p class="sx-fine"><?= h(t('Forgot your code? Call the office — they will set a new one.', 'कोड भूल गए? दफ़्तर को फ़ोन कीजिए — नया कोड बना देंगे।')) ?></p>
  </form>
  <a class="sx-join" href="<?= h(u('/apply.php')) ?>"><span class="sx-join-ic"><?= sr_icon('bike', 22) ?></span><span><b><?= h(t('New here? Become a Sarathi rider', 'नए हैं? सारथी राइडर बनिए')) ?></b><small><?= h(t('Apply in 1 minute · no fee', '1 मिनट में अर्ज़ी · कोई फ़ीस नहीं')) ?></small></span><?= sr_icon('arrow', 18) ?></a>
<script>
(function () {
  var m = document.getElementById('m'), c = document.getElementById('c'), keep = document.querySelector('[data-keep]');
  try { var v = localStorage.getItem('sx_rider_m'); if (v && !m.value) { m.value = v; c.focus(); } } catch (e) {}
  document.querySelector('[data-eye]').addEventListener('click', function () { c.type = c.type === 'password' ? 'text' : 'password'; this.classList.toggle('on'); c.focus(); });
  m.form.addEventListener('submit', function () { try { if (keep.checked) localStorage.setItem('sx_rider_m', m.value); else localStorage.removeItem('sx_rider_m'); } catch (e) {} });
})();
</script>
<?php sr_public_foot();
