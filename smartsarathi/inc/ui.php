<?php
// ============================================================
//  सारथी — ऐप का ढाँचा (हर पन्ने पर एक जैसा)
//
//  Upar ☰ aur naam, bagal se aane wala menu, neeche tab patti.
//  Drawer bina JavaScript ke chalta hai — ek chhupa checkbox
//  aur CSS. Purane aur dheeme phone par bhi khulta hai.
// ============================================================

function sr_tabs() {
    return [
        ['kaam',    '/kaam.php',    'काम',          'box'],
        ['khata',   '/khata.php',   'खाता',         'rupee'],
        ['beete',   '/beete.php',   'बीते काम',      'clock'],
        ['jankari', '/jankari.php', 'मेरी जानकारी',  'user'],
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
<html lang="hi">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="theme-color" content="#7A1F1F">
<meta name="robots" content="noindex,nofollow">
<title><?= h($title) ?> — सारथी</title>
<link rel="stylesheet" href="/assets/app.css?v=<?= (int)@filemtime(__DIR__ . '/../assets/app.css') ?>">
</head>
<body>

<input type="checkbox" id="sr-nav" class="sr-nav-sw" hidden>

<header class="sr-top">
  <label class="sr-ham" for="sr-nav" aria-label="मेन्यू"><span></span><span></span><span></span></label>
  <div class="sr-top-t">
    <b><?= h($title) ?></b>
    <small><?= $ready ? 'हाज़िर — ' . h(date('g:i a', strtotime($ready))) . ' तक' : 'अभी बंद हैं' ?></small>
  </div>
  <span class="sr-top-dot <?= $ready ? 'on' : '' ?>"></span>
</header>

<label class="sr-veil" for="sr-nav"></label>
<nav class="sr-draw">
  <div class="sr-draw-top">
    <div class="sr-ava"><?= h(mb_substr(trim($me['name']), 0, 1)) ?></div>
    <div><b><?= h($me['name']) ?></b><small>सारथी</small></div>
    <label class="sr-draw-x" for="sr-nav" aria-label="बंद कीजिए">&times;</label>
  </div>
  <?php foreach (sr_tabs() as [$k, $url, $lbl, $ic]): ?>
    <a class="<?= $tab === $k ? 'on' : '' ?>" href="<?= h($url) ?>">
      <?= sr_icon($ic) ?> <span><?= h($lbl) ?></span>
      <?php if (!empty($count[$k])): ?><em><?= (int)$count[$k] ?></em><?php endif; ?>
    </a>
  <?php endforeach; ?>
  <div class="sr-draw-gap"></div>
  <a href="tel:<?= h(SR_PHONE) ?>"><?= sr_icon('phone') ?> <span>दफ़्तर को फ़ोन</span></a>
  <a href="/madad.php"><?= sr_icon('help') ?> <span>मदद</span></a>
  <form method="post" action="/kaam.php">
    <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
    <input type="hidden" name="do" value="bahar">
    <button type="submit" class="sr-draw-out"><?= sr_icon('out') ?> <span>बाहर निकलिए</span></button>
  </form>
  <p class="sr-draw-foot">सारथी · <?= h(SR_PHONE_SHOW) ?></p>
</nav>

<main class="sr-main">
<?php
}

function sr_foot($tab, $count = []) {
    ?>
</main>
<nav class="sr-bar">
  <?php foreach (sr_tabs() as [$k, $url, $lbl, $ic]): ?>
    <a class="<?= $tab === $k ? 'on' : '' ?>" href="<?= h($url) ?>">
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
  p.className = 'sr-net'; p.textContent = 'नेट नहीं है — दबाया हुआ दर्ज नहीं होगा';
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
