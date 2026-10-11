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
?><!doctype html>
<html lang="<?= h(sr_lang()) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="theme-color" content="#14283f">
<meta name="robots" content="noindex,nofollow">
<title><?= h(t('Sarathi', 'सारथी')) ?></title>
<link rel="stylesheet" href="<?= h(u('/assets/app.css')) ?>?v=<?= (int)@filemtime(__DIR__ . '/assets/app.css') ?>">
</head>
<body class="sr-login sd-native">
<?php sr_language_switch(true); ?>
<section class="sd-login-intro">
  <a class="sd-brand" href="<?= h(u('/')) ?>"><span class="sd-mark">↗</span><span>Sarathi<span class="sd-brand-sub">DELIVERY PARTNER</span></span></a>
  <span class="sd-eyebrow"><?= h(t('YOUR WORK · YOUR PARTNER', 'आपका काम · आपका साथ')) ?></span>
  <h1><?= h(t('Every journey.', 'हर सफ़र के साथ,')) ?><br><?= h(t('A fresh start.', 'एक नई शुरुआत।')) ?></h1>
  <p><?= h(t('Deliveries, jobs and earnings.', 'डिलीवरी, काम और कमाई का हिसाब।')) ?><br><?= h(t('Everything you need, in one place.', 'सब कुछ एक जगह, आपके साथ।')) ?></p>
  <div class="sd-login-art" aria-hidden="true"><span>↗</span></div>
  <small>SMART SARATHI / PARTNER APP</small>
</section>
<div class="sr-wrap">

  <div class="sr-brand">
    <div class="sr-logo"><?= h(t('Welcome back.', 'फिर से स्वागत है।')) ?></div>
    <p><?= h(t('Delivery partner', 'डिलीवरी साथी')) ?></p>
  </div>

  <?php if ($err): ?><div class="sr-err"><?= h(sr_message($err)) ?></div><?php endif; ?>

  <form method="post" class="sr-card" autocomplete="off">
    <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
    <label class="sr-lab" for="m"><?= h(t('Mobile number', 'अपना मोबाइल नंबर')) ?></label>
    <input class="sr-in" id="m" name="mobile" type="tel" inputmode="numeric"
           value="<?= h(post('mobile')) ?>" required autofocus>
    <label class="sr-lab" for="c"><?= h(t('Login code', 'अपना कोड')) ?></label>
    <input class="sr-in" id="c" name="code" type="password" required>
    <button class="sr-btn sr-btn-go" type="submit"><?= h(t('Sign in to work', 'काम पर चलिए')) ?></button>
  </form>

  <div class="sr-help">
    <b><?= h(t('Need your login code?', 'कोड नहीं मिला?')) ?></b>
    <p><?= h(t('Ask your office for your code. Once signed in, this phone will remember your session.', 'दफ़्तर से अपना कोड पूछ लीजिए। एक बार अंदर आने के बाद यही फ़ोन आपको सीधा अंदर ले जाएगा।')) ?></p>
    <a class="sr-call" href="tel:<?= h(SR_PHONE) ?>"><?= h(t('Call support —', 'फ़ोन कीजिए —')) ?> <?= h(SR_PHONE_SHOW) ?></a>
  </div>

</div>
<script>
var m = document.getElementById('m');
if (m) m.addEventListener('input', function(){ this.value = this.value.replace(/\D/g,'').slice(0,10); });
</script>
</body>
</html>


