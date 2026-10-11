<?php
require_once __DIR__ . "/lang.php";
// ============================================================
//  सारथी — ऐप का ढाँचा (हर पन्ने पर एक जैसा)
//
//  Owner-requested navy/teal dashboard: desktop sidebar and mobile tabs.
//  Drawer bina JavaScript ke chalta hai — ek chhupa checkbox
//  aur CSS. Purane aur dheeme phone par bhi khulta hai.
// ============================================================

function sr_tabs() {
    return [
        ['kaam',    '/kaam.php',    t('Work', 'काम'),          'box'],
        ['khata',   '/khata.php',   t('Earnings', 'खाता'),         'rupee'],
        ['beete',   '/beete.php',   t('History', 'बीते काम'),      'clock'],
        ['jankari', '/jankari.php', t('My profile', 'मेरी जानकारी'),  'user'],
    ];
}

/** chhote chitra — SVG, taaki kisi bahari file ki zaroorat na pade */
function sr_icon($name, $size = 22) {
    $d = [
        'box'   => '<path d="M3 7l9-4 9 4v10l-9 4-9-4V7z"/><path d="M3 7l9 4 9-4M12 11v10"/>',
        'rupee' => '<path d="M6 3h12M6 8h12M16 3c0 5-4 5-10 5l9 13"/>',
        'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 3"/>',
        'user'  => '<circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 4-6 8-6s8 2 8 6"/>',
        'help'  => '<circle cx="12" cy="12" r="9"/><path d="M9.5 9a2.5 2.5 0 115 .5c0 1.5-2.5 2-2.5 3.5"/><circle cx="12" cy="17" r=".6" fill="currentColor"/>',
        'out'   => '<path d="M14 3h5v18h-5M10 8l-4 4 4 4M6 12h10"/>',
        'phone' => '<path d="M5 3h4l2 5-3 2a12 12 0 006 6l2-3 5 2v4a2 2 0 01-2 2A17 17 0 013 5a2 2 0 012-2z"/>',
    ][$name] ?? '';
    return '<svg class="sr-i" width="' . (int)$size . '" height="' . (int)$size . '" viewBox="0 0 24 24"'
         . ' fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"'
         . ' stroke-linejoin="round" aria-hidden="true">' . $d . '</svg>';
}

function sr_head($me, $tab, $title, $ready = null, $count = []) {
    ?><!doctype html>
<html lang="<?= h(sr_lang()) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="theme-color" content="#14283f">
<meta name="robots" content="noindex,nofollow">
<title><?= h($title) ?> — <?= h(t('Sarathi', 'सारथी')) ?></title>
<link rel="stylesheet" href="<?= h(u('/assets/app.css')) ?>?v=<?= (int)@filemtime(__DIR__ . '/../assets/app.css') ?>">
</head>
<body class="sd-partner">

<input type="checkbox" id="sr-nav" class="sr-nav-sw" hidden>

<header class="sr-top">
  <label class="sr-ham" for="sr-nav" aria-label="<?= h(t('Menu', 'मेन्यू')) ?>"><span></span><span></span><span></span></label>
  <div class="sr-top-t">
    <b><?= h($title) ?></b>
    <small><?= $ready ? t('Available until ', 'हाज़िर — ') . h(date('g:i a', strtotime($ready))) . t('', ' तक') : t('Currently offline', 'अभी बंद हैं') ?></small>
  </div>
  <span class="sr-top-dot <?= $ready ? 'on' : '' ?>"></span>
  <?php sr_language_switch(); ?>
</header>

<label class="sr-veil" for="sr-nav"></label>
<nav class="sr-draw">
  <a class="sd-brand" href="<?= h(u('/')) ?>"><span class="sd-mark">↗</span><span>Sarathi<span class="sd-brand-sub">DELIVERY PARTNER</span></span></a>
  <div class="sr-draw-top">
    <div class="sr-ava"><?= h(mb_substr(trim($me['name']), 0, 1)) ?></div>
    <div><b><?= h($me['name']) ?></b><small><?= h(t('Sarathi', 'सारथी')) ?></small></div>
    <label class="sr-draw-x" for="sr-nav" aria-label="<?= h(t('Close menu', 'बंद कीजिए')) ?>">&times;</label>
  </div>
  <?php foreach (sr_tabs() as [$k, $url, $lbl, $ic]): ?>
    <a class="<?= $tab === $k ? 'on' : '' ?>" href="<?= h(u($url)) ?>">
      <?= sr_icon($ic) ?> <span><?= h($lbl) ?></span>
      <?php if (!empty($count[$k])): ?><em><?= (int)$count[$k] ?></em><?php endif; ?>
    </a>
  <?php endforeach; ?>
  <div class="sr-draw-gap"></div>
  <a href="tel:<?= h(SR_PHONE) ?>"><?= sr_icon('phone') ?> <span><?= h(t('Call support', 'दफ़्तर को फ़ोन')) ?></span></a>
  <a href="<?= h(u('/madad.php')) ?>"><?= sr_icon('help') ?> <span><?= h(t('Help', 'मदद')) ?></span></a>
  <form method="post" action="<?= h(u('/kaam.php')) ?>">
    <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
    <input type="hidden" name="do" value="bahar">
    <button type="submit" class="sr-draw-out"><?= sr_icon('out') ?> <span><?= h(t('Sign out', 'बाहर निकलिए')) ?></span></button>
  </form>
  <p class="sr-draw-foot"><?= h(t('Sarathi', 'सारथी')) ?> · <?= h(SR_PHONE_SHOW) ?></p>
</nav>

<main class="sr-main sd-native">
  <section class="sd-welcome">
    <div><span class="sd-eyebrow">DELIVERY PARTNER</span>
      <h1><?= $tab === 'kaam' ? t('Hello, ', 'नमस्ते, ') . h($me['name']) : h($title) ?></h1>
      <p><?= $tab === 'kaam' ? t('Your next delivery starts here.', 'आपका अगला काम, यहीं से शुरू होता है।') : t('All your work details, in one place.', 'आपके काम की सारी जानकारी, एक जगह।') ?></p>
    </div>
    <a class="sd-support" href="<?= h(u('/madad.php')) ?>"><?= h(t('Help & support ↗', 'मदद और सहायता ↗')) ?></a>
  </section>
  <?php if ($tab === 'kaam'): ?>
  <div class="sd-stats">
    <div class="sd-stat"><small><?= h(t('Active jobs', 'अभी के काम')) ?></small><strong><?= (int)($count['kaam'] ?? 0) ?></strong><span><?= h(t('Jobs assigned to you', 'आपको मिले हुए काम')) ?></span></div>
    <div class="sd-stat"><small><?= h(t('Availability', 'आपकी हाज़िरी')) ?></small><strong class="sd-word"><?= $ready ? t('Online', 'हाज़िर') : t('Offline', 'बंद') ?></strong><span><?= $ready ? t('Ready to receive new jobs', 'नए काम मिल सकते हैं') : t('Go online when you are ready', 'तैयार होने पर हाज़िरी लगाएँ') ?></span></div>
    <a class="sd-stat" href="<?= h(u('/khata.php')) ?>"><small><?= h(t('Earnings & payments', 'कमाई और भुगतान')) ?></small><strong class="sd-word"><?= h(t('View earnings ↗', 'खाता देखें ↗')) ?></strong><span><?= h(t('Your complete earnings record', 'आपका पूरा हिसाब')) ?></span></a>
  </div>
  <div class="sd-grid"><div class="sd-work">
    <section class="sd-hero"><span class="sd-eyebrow"><?= h(t('EVERY DELIVERY MATTERS', 'हर डिलीवरी मायने रखती है')) ?></span><h2><?= h(t('Your next delivery.', 'चलें, अगला काम')) ?><br><?= h(t('Made simpler.', 'आसान बनाते हैं।')) ?></h2><p><?= h(t('With you, from pickup to delivery.', 'पिकअप से डिलीवरी तक, आपका साथी।')) ?></p><span class="sd-orbit" aria-hidden="true">↗</span></section>
  <?php endif; ?>
<?php
}

function sr_foot($tab, $count = []) {
    ?>
<?php if ($tab === 'kaam'): ?>
  </div><aside class="sd-rail">
    <section class="sd-tip"><span class="sd-eyebrow"><?= h(t('WITH YOU, EVERY STEP', 'आपके साथ, हर रास्ते पर')) ?></span><h3><?= h(t('Travel safely.', 'सुरक्षित सफ़र,')) ?><br><?= h(t('Deliver confidently.', 'बेहतर डिलीवरी।')) ?></h3><p><?= h(t('Stop somewhere safe before using your phone while travelling.', 'चलती गाड़ी में फ़ोन इस्तेमाल करने से पहले सुरक्षित जगह रुकें।')) ?></p><a href="<?= h(u('/madad.php')) ?>"><?= h(t('Get help →', 'मदद देखिए →')) ?></a></section>
    <section class="sd-rail-card"><span class="sd-eyebrow"><?= h(t('YOUR EARNINGS', 'आपका हिसाब')) ?></span><h3><?= h(t('Work done. Earnings clear.', 'काम पूरा। हिसाब साफ़।')) ?></h3><p><?= h(t('See your earnings, payments received and outstanding balance.', 'कमाई, मिला हुआ भुगतान और बाकी रकम अपने खाते में देखें।')) ?></p><a href="<?= h(u('/khata.php')) ?>"><?= h(t('Open earnings →', 'खाता खोलिए →')) ?></a></section>
  </aside></div>
<?php endif; ?>
</main>
<nav class="sr-bar">
  <?php foreach (sr_tabs() as [$k, $url, $lbl, $ic]): ?>
    <a class="<?= $tab === $k ? 'on' : '' ?>" href="<?= h(u($url)) ?>">
      <?= sr_icon($ic, 23) ?><span><?= h($lbl) ?></span>
      <?php if (!empty($count[$k])): ?><em><?= (int)$count[$k] ?></em><?php endif; ?>
    </a>
  <?php endforeach; ?>
</nav>

<script>
// नेट चला गया तो बता दीजिए.
// Gaon me net aata-jata rehta hai. Rider "utha liya" daba kar
// aage badh jaye aur wo darj hi na ho — ye sabse bura hai.
(function () {
  var p = document.createElement('div');
  p.className = 'sr-net'; p.textContent = <?= json_encode(t('You are offline — actions cannot be saved', 'नेट नहीं है — दबाया हुआ दर्ज नहीं होगा'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
  function dekho() { if (navigator.onLine) p.remove(); else document.body.appendChild(p); }
  window.addEventListener('online', dekho);
  window.addEventListener('offline', dekho);
  dekho();
})();
</script>
</body>
</html>
<?php
}


