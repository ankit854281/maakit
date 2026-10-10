<?php
// ============================================================
//  सारथी — ऐप का ढाँचा (हर पन्ने पर एक जैसा)
//
//  App jaisa dhancha teen cheezon se banta hai:
//
//    1. ऊपर  — ☰ teen line, naam, aur haazri ki batti
//    2. ☰ se — kheenchkar aane wala menu (drawer): mera kaam,
//              khata, beete kaam, meri jaankari, madad, bahar
//    3. नीचे  — tab patti (काम · खाता · बीते · मदद)
//
//  Drawer bina JavaScript ke chalta hai — ek chhupa hua checkbox
//  aur CSS. Purane aur dheeme phone par bhi khulta hai, aur agar
//  net adha load ho to bhi atakta nahi.
//
//  Har panna sirf apna beech ka hissa likhta hai. Upar-neeche ka
//  dhancha yahan se aata hai, taaki har panne par ek jaisa rahe.
// ============================================================

/** menu aur neeche ki patti — ek hi jagah tay, taaki bikhre na */
function sr_menu() {
    return [
        ['kaam',    '/sarathi/kaam.php',     'काम',          'box'],
        ['khata',   '/sarathi/khata.php',    'खाता',         'rupee'],
        ['beete',   '/sarathi/beete.php',    'बीते काम',      'clock'],
        ['jankari', '/sarathi/jankari.php',  'मेरी जानकारी',  'user'],
    ];
}

/** chhote chitra — SVG, taaki kisi bahari font ya file ki zaroorat na pade */
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
    return '<svg class="sr-i" width="' . (int)$size . '" height="' . (int)$size . '" viewBox="0 0 24 24" '
         . 'fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" '
         . 'stroke-linejoin="round" aria-hidden="true">' . $d . '</svg>';
}

/**
 * Panne ka upari hissa.
 *   $tab   — abhi kaun sa panna khula hai (menu ki pehli cheez)
 *   $title — upar bade akshar me kya likha jaye
 */
function sr_shell_head($me, $tab, $title, $duty_tak = null, $ginti = []) {
    $csrf = csrf();
    ?><!doctype html>
<html lang="hi">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="theme-color" content="#7A1F1F">
<meta name="robots" content="noindex,nofollow">
<title><?= h($title) ?> — सारथी</title>
<link rel="stylesheet" href="/assets/sarathi.css?v=<?= (int)@filemtime(__DIR__.'/../assets/sarathi.css') ?>">
</head>
<body>

<!-- ☰ का स्विच — छुपा हुआ checkbox, इसी से menu खुलता-बंद होता है -->
<input type="checkbox" id="sr-nav" class="sr-nav-sw" hidden>

<header class="sr-top">
  <label class="sr-ham" for="sr-nav" aria-label="मेन्यू"><span></span><span></span><span></span></label>
  <div class="sr-top-t">
    <b><?= h($title) ?></b>
    <small><?= $duty_tak
        ? 'हाज़िर — ' . h(date('g:i a', strtotime($duty_tak))) . ' तक'
        : 'अभी बंद हैं' ?></small>
  </div>
  <span class="sr-top-dot <?= $duty_tak ? 'on' : '' ?>"></span>
</header>

<!-- ☰ का मेन्यू -->
<label class="sr-veil" for="sr-nav"></label>
<nav class="sr-draw">
  <div class="sr-draw-top">
    <div class="sr-ava"><?= h(mb_substr(trim($me['name']), 0, 1)) ?></div>
    <div>
      <b><?= h($me['name']) ?></b>
      <small><?= $me['role'] === 'rider' ? 'सारथी' : 'डिलीवरी' ?></small>
    </div>
    <label class="sr-draw-x" for="sr-nav" aria-label="बंद कीजिए">&times;</label>
  </div>

  <?php foreach (sr_menu() as [$k, $url, $lbl, $ic]): ?>
    <a class="<?= $tab === $k ? 'on' : '' ?>" href="<?= h($url) ?>">
      <?= sr_icon($ic) ?> <span><?= h($lbl) ?></span>
      <?php if (!empty($ginti[$k])): ?><em><?= (int)$ginti[$k] ?></em><?php endif; ?>
    </a>
  <?php endforeach; ?>

  <div class="sr-draw-gap"></div>

  <a href="tel:<?= h(MAAKIT_PHONE) ?>"><?= sr_icon('phone') ?> <span>Maakit को फ़ोन</span></a>
  <a href="/sarathi/madad.php"><?= sr_icon('help') ?> <span>मदद</span></a>
  <form method="post" action="/sarathi/kaam.php">
    <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
    <input type="hidden" name="do" value="bahar">
    <button type="submit" class="sr-draw-out"><?= sr_icon('out') ?> <span>बाहर निकलिए</span></button>
  </form>
  <p class="sr-draw-foot">Maakit · <?= h(MAAKIT_NUMBER_SHOW) ?></p>
</nav>

<main class="sr-main">
<?php
}

/** Panne ka nichla hissa — tab patti */
function sr_shell_foot($tab, $ginti = []) {
    ?>
</main>

<nav class="sr-bar">
  <?php foreach (sr_menu() as [$k, $url, $lbl, $ic]): ?>
    <a class="<?= $tab === $k ? 'on' : '' ?>" href="<?= h($url) ?>">
      <?= sr_icon($ic, 23) ?>
      <span><?= h($lbl) ?></span>
      <?php if (!empty($ginti[$k])): ?><em><?= (int)$ginti[$k] ?></em><?php endif; ?>
    </a>
  <?php endforeach; ?>
</nav>

</body>
</html>
<?php
}
