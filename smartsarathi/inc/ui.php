<?php
require_once __DIR__ . "/lang.php";
// ============================================================
//  सारथी — ऐप का ढाँचा (हर पन्ने पर एक जैसा)
//
//  Oct 2026 v2 ("app jaisa"): upar chhota header (naam + haazri
//  switch + bhasha), beech me kaam, neeche app jaisi tab-patti.
//  Bada screen ho to baayein taraf sidebar. Style: assets/sr2.css.
//  Function ke naam aur arguments wahi — baaki panne nahi badle.
// ============================================================

function sr_tabs() {
    return [
        ['kaam',    '/kaam.php',    t('Jobs', 'काम'),            'box'],
        ['khata',   '/khata.php',   t('Earnings', 'कमाई'),       'rupee'],
        ['beete',   '/beete.php',   t('History', 'बीते काम'),    'clock'],
        ['jankari', '/jankari.php', t('Profile', 'प्रोफ़ाइल'),    'user'],
    ];
}

/** chhote chitra — SVG, taaki kisi bahari file ki zaroorat na pade */
function sr_icon($name, $size = 22) {
    $d = [
        'box'   => '<path d="M3 7l9-4 9 4v10l-9 4-9-4V7z"/><path d="M3 7l9 4 9-4M12 11v10"/>',
        'sound' => '<path d="M4 9v6h4l5 4V5L8 9H4z"/><path d="M16.5 8.5a5 5 0 0 1 0 7M19 6a8.5 8.5 0 0 1 0 12"/>',
        'rupee' => '<path d="M6 3h12M6 8h12M16 3c0 5-4 5-10 5l9 13"/>',
        'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 3"/>',
        'user'  => '<circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 4-6 8-6s8 2 8 6"/>',
        'help'  => '<circle cx="12" cy="12" r="9"/><path d="M9.5 9a2.5 2.5 0 115 .5c0 1.5-2.5 2-2.5 3.5"/><circle cx="12" cy="17" r=".6" fill="currentColor"/>',
        'out'   => '<path d="M14 3h5v18h-5M10 8l-4 4 4 4M6 12h10"/>',
        'phone' => '<path d="M5 3h4l2 5-3 2a12 12 0 006 6l2-3 5 2v4a2 2 0 01-2 2A17 17 0 013 5a2 2 0 012-2z"/>',
        'nav'   => '<path d="M3 11l18-8-8 18-2-8-8-2z"/>',
        'wa'    => '<path d="M4 20l1.3-4A8 8 0 1 1 8 18.7L4 20z"/><path d="M9 9.5c.5 2 2 3.5 4 4.3l1.2-1 1.8.8"/>',
        'cam'   => '<path d="M4 8h3l2-3h6l2 3h3v11H4z"/><circle cx="12" cy="13" r="3.5"/>',
        'pin'   => '<path d="M12 22s7-7.6 7-13a7 7 0 0 0-14 0c0 5.4 7 13 7 13z"/><circle cx="12" cy="9" r="2.5"/>',
        'flag'  => '<path d="M5 21V4M5 4h11l-2 4 2 4H5"/>',
        'bolt'  => '<path d="M13 2L4 14h7l-1 8 9-12h-7l1-8z"/>',
        'check' => '<path d="M5 12.5l4.5 4.5L19 7.5"/>',
        'alert' => '<path d="M12 3l10 18H2L12 3z"/><path d="M12 10v4M12 17.5v.5"/>',
        'route' => '<circle cx="6" cy="19" r="2"/><circle cx="18" cy="5" r="2"/><path d="M8 19h8a3 3 0 0 0 0-6H8a3 3 0 0 1 0-6h8"/>',
        'spark' => '<path d="M12 3v4M12 17v4M3 12h4M17 12h4M6 6l2.5 2.5M15.5 15.5L18 18M18 6l-2.5 2.5M8.5 15.5L6 18"/>',
        'copy'  => '<rect x="8" y="8" width="12" height="12" rx="2"/><path d="M16 8V5a1 1 0 0 0-1-1H5a1 1 0 0 0-1 1v10a1 1 0 0 0 1 1h3"/>',
        'share' => '<circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><path d="M8.6 13.5l6.8 4M15.4 6.5l-6.8 4"/>',
        'arrow' => '<path d="M5 12h14M13 6l6 6-6 6"/>',
        'eye'   => '<path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>',
        'bike'  => '<circle cx="5.5" cy="17" r="3.5"/><circle cx="18.5" cy="17" r="3.5"/><path d="M5.5 17l4-8h5l4 8M12 9l-2-4H7"/>',
    ][$name] ?? '';
    return '<svg class="sr-i" width="' . (int)$size . '" height="' . (int)$size . '" viewBox="0 0 24 24"'
         . ' fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"'
         . ' stroke-linejoin="round" aria-hidden="true">' . $d . '</svg>';
}

/** Saare panno ke liye font + style (purani app.css ki jagah) */
function sr_assets() {
    ?><link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Noto+Sans+Devanagari:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= h(u('/assets/sr2.css')) ?>?v=<?= (int)@filemtime(__DIR__ . '/../assets/sr2.css') ?>"><?php
    sr_mobile_assets();
    sr_app_head();
}
/** Play Store / install: manifest + offline service worker (only on maakit.in/smartsarathi/) */
/** Do alag app (owner decision, 29 Oct 2026): ग्राहक = Smart Sarathi, सारथी = Sarathi Partner */
function sr_app_kind(): string {
    $page = basename((string)($_SERVER['SCRIPT_NAME'] ?? ''));
    if ($page === 'ghar.php') return 'customer';
    if (in_array($page, ['sarathi.php','niyam.php','kaam.php','apply.php','khata.php','beete.php','madad.php','jankari.php','proof.php','otp.php'], true)) return 'partner';
    if (($_SESSION['sr_intent'] ?? '') === 'partner' || ($_SESSION['sr_mode'] ?? '') === 'rider') return 'partner';
    return 'customer';
}
function sr_app_head(?string $kind = null) {
    if (!defined('SR_BASE') || SR_BASE !== '/smartsarathi') return;
    $partner = ($kind ?? sr_app_kind()) === 'partner';
    ?>
<link rel="manifest" href="/smartsarathi/<?= $partner ? 'partner.json' : 'manifest.json' ?>">
<link rel="apple-touch-icon" href="/smartsarathi/assets/<?= $partner ? 'partner-192.png' : 'icon-192.png' ?>">
<?php if ($partner): ?><meta name="apple-mobile-web-app-title" content="Sarathi Partner"><?php endif; ?>
<script>/* Phone par Chrome "Desktop site" chalu ho to page chhota dikhta hai: use phone ki chaudai par wapas laate hain. */
(function(){var d=document.documentElement;function fit(){d.style.zoom='';var sw=Math.min(screen.width,screen.availWidth||screen.width),w=innerWidth,z=1;
if(sw&&sw<=820&&w>sw*1.25)z=w/sw;if(z>1){d.style.zoom=z.toFixed(3);d.classList.add('sr-dz');}else d.classList.remove('sr-dz');
d.style.setProperty('--vh',(innerHeight/z/100)+'px');}fit();addEventListener('resize',fit);addEventListener('orientationchange',function(){setTimeout(fit,200);});})();</script>
<script>if('serviceWorker' in navigator){addEventListener('load',function(){navigator.serviceWorker.register('/smartsarathi/sw.js',{scope:'/smartsarathi/'}).catch(function(){});});}</script>
<?php
}

function sr_head($me, $tab, $title, $ready = null, $count = []) {
    $first = trim(explode(' ', trim((string)$me['name']))[0]);
    ?><!doctype html>
<html lang="<?= h(sr_lang()) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="theme-color" content="#0B1B36">
<meta name="robots" content="noindex,nofollow">
<title><?= h($title) ?> — <?= h(t('Sarathi', 'सारथी')) ?></title>
<?php sr_assets(); ?>
<?php if ($tab === 'kaam'): ?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.js" defer></script>
<?php endif; ?>
</head>
<body class="sx sx-app<?= $ready ? ' is-online' : '' ?>">

<aside class="sx-side" aria-label="<?= h(t('Menu', 'मेन्यू')) ?>">
  <a class="sx-logo" href="<?= h(u('/kaam.php')) ?>"><span class="sx-mark"><?= sr_icon('bolt', 18) ?></span><span>Sarathi<small>PARTNER</small></span></a>
  <nav>
    <?php foreach (sr_tabs() as [$k, $url, $lbl, $ic]): ?>
      <a class="<?= $tab === $k ? 'on' : '' ?>" href="<?= h(u($url)) ?>"><?= sr_icon($ic, 20) ?><span><?= h($lbl) ?></span>
        <?php if (!empty($count[$k])): ?><em><?= (int)$count[$k] ?></em><?php endif; ?></a>
    <?php endforeach; ?>
  </nav>
  <div class="sx-side-foot">
    <a href="<?= h(u('/madad.php')) ?>"><?= sr_icon('help', 18) ?><span><?= h(t('Help', 'मदद')) ?></span></a>
    <a href="tel:<?= h(SR_PHONE) ?>"><?= sr_icon('phone', 18) ?><span><?= h(t('Call office', 'दफ़्तर को फ़ोन')) ?></span></a>
    <form method="post" action="<?= h(u('/kaam.php')) ?>"><input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="do" value="bahar">
      <button type="submit"><?= sr_icon('out', 18) ?><span><?= h(t('Sign out', 'बाहर निकलिए')) ?></span></button></form>
  </div>
</aside>

<header class="sx-top">
  <div class="sx-me">
    <span class="sx-ava"><?= h(mb_strtoupper(mb_substr($first, 0, 1))) ?><i class="sx-ava-dot"></i></span>
    <span class="sx-me-t"><small><?= h($tab === 'kaam' ? t('Hello', 'नमस्ते') : $first) ?></small><b><?= h($tab === 'kaam' ? $first : $title) ?></b></span>
  </div>
  <div class="sx-top-r">
    <?php if ($tab !== 'kaam'): ?>
      <span class="sx-chip <?= $ready ? 'on' : '' ?>"><i></i><?= $ready ? h(t('Online', 'हाज़िर')) : h(t('Offline', 'बंद')) ?></span>
    <?php endif; ?>
    <?php if (sr_firebase_enabled()): ?><a class="sx-pill" href="<?= h(u('/account.php')) ?>"><?= h(t('Change mode', 'मोड बदलिए')) ?></a><?php endif; ?>
    <?php sr_language_switch(); ?>
  </div>
</header>

<main class="sx-main">
<?php
}

function sr_foot($tab, $count = []) {
    ?>
</main>

<nav class="sx-tabbar" aria-label="<?= h(t('Sections', 'हिस्से')) ?>">
  <?php foreach (sr_tabs() as [$k, $url, $lbl, $ic]): ?>
    <a class="<?= $tab === $k ? 'on' : '' ?>" href="<?= h(u($url)) ?>"<?= $tab === $k ? ' aria-current="page"' : '' ?>>
      <span class="sx-tb-ic"><?= sr_icon($ic, 22) ?><?php if (!empty($count[$k])): ?><em><?= (int)$count[$k] ?></em><?php endif; ?></span><span><?= h($lbl) ?></span>
    </a>
  <?php endforeach; ?>
</nav>

<div class="sx-toast" id="sxToast" role="status" aria-live="polite"></div>
<script>
/* Sarathi v2: net gaya to batao, tap par halka vibrate, OTP dabbe, screen jagi rahe (haazir ho to) */
(function () {
  var net = document.createElement('div'); net.className = 'sx-net';
  net.textContent = <?= json_encode(t('You are offline — taps will not be saved', 'नेट नहीं है — दबाया हुआ दर्ज नहीं होगा'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
  function chk() { if (navigator.onLine) net.remove(); else document.body.appendChild(net); }
  window.addEventListener('online', chk); window.addEventListener('offline', chk); chk();

  document.addEventListener('click', function (e) { if (e.target.closest('button, .sx-btn, .sx-tabbar a')) { try { navigator.vibrate && navigator.vibrate(8); } catch (x) {} } });

  // 4 dabbon wala OTP: likhte hi agla dabba, poora hote hi button chamke
  document.querySelectorAll('[data-otp]').forEach(function (wrap) {
    var boxes = wrap.querySelectorAll('input.sx-otp-b'), hidden = wrap.querySelector('input[type=hidden][name=otp]'), btn = wrap.closest('form').querySelector('button[type=submit]');
    function sync() { var v = ''; boxes.forEach(function (b) { v += b.value; }); hidden.value = v; wrap.classList.toggle('full', v.length === 4); }
    boxes.forEach(function (b, i) {
      b.addEventListener('input', function () { b.value = b.value.replace(/\D/g, '').slice(-1); if (b.value && boxes[i + 1]) boxes[i + 1].focus(); sync(); });
      b.addEventListener('keydown', function (e) { if (e.key === 'Backspace' && !b.value && boxes[i - 1]) { boxes[i - 1].focus(); boxes[i - 1].value = ''; sync(); } });
      b.addEventListener('paste', function (e) { var t = (e.clipboardData || window.clipboardData).getData('text').replace(/\D/g, '').slice(0, 4); if (t.length) { e.preventDefault(); boxes.forEach(function (x, j) { x.value = t[j] || ''; }); sync(); (boxes[Math.min(t.length, 3)]).focus(); } });
    });
    wrap.closest('form').addEventListener('submit', function (e) { sync(); if (hidden.value.length !== 4) { e.preventDefault(); boxes[hidden.value.length] && boxes[hidden.value.length].focus(); wrap.classList.add('shake'); setTimeout(function () { wrap.classList.remove('shake'); }, 400); } else if (btn) { btn.classList.add('busy'); } });
  });

  // Swipe slider — "स्वाइप करके हाज़िरी / उठा लिया / पहुँचा दिया". Bina JS ke asli button dikhta hai.
  var needMsg = <?= json_encode(t('Enter the 4-digit code first', 'पहले 4 अंक का कोड डालिए'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
  document.querySelectorAll('[data-slide]').forEach(function (sl) {
    var btn = sl.querySelector('button[type=submit]'), form = sl.closest('form'); if (!btn || !form) return;
    var tr = document.createElement('div'); tr.className = 'sx-sl';
    tr.innerHTML = '<span class="sx-sl-fill"></span><span class="sx-sl-text"></span><span class="sx-sl-knob" role="button" tabindex="0"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M9 6l6 6-6 6"/></svg></span>';
    tr.querySelector('.sx-sl-text').textContent = sl.dataset.label || btn.textContent.trim();
    tr.querySelector('.sx-sl-knob').setAttribute('aria-label', sl.dataset.label || btn.textContent.trim());
    sl.appendChild(tr); sl.classList.add('js');
    var knob = tr.querySelector('.sx-sl-knob'), fill = tr.querySelector('.sx-sl-fill'), x0 = 0, max = 0, drag = false, done = false, cur = 0;
    function need() {
      if (!sl.hasAttribute('data-need-otp')) return false;
      var o = form.querySelector('[data-otp]'); if (!o || o.classList.contains('full')) return false;
      o.classList.add('shake'); setTimeout(function () { o.classList.remove('shake'); }, 400);
      var first = Array.prototype.find.call(o.querySelectorAll('.sx-otp-b'), function (b) { return !b.value; }); if (first) first.focus();
      var t = document.getElementById('sxToast'); if (t) { t.textContent = needMsg; t.classList.add('show'); setTimeout(function () { t.classList.remove('show'); }, 2000); }
      return true;
    }
    function set(px) { cur = px; knob.style.transform = 'translateX(' + px + 'px)'; fill.style.width = (px + knob.offsetWidth + 6) + 'px'; tr.style.setProperty('--p', max ? px / max : 0); }
    function reset() { done = false; tr.classList.remove('ok'); tr.classList.add('back'); set(0); setTimeout(function () { tr.classList.remove('back'); }, 260); }
    function fire() {
      done = true; set(max); tr.classList.add('ok'); try { navigator.vibrate && navigator.vibrate([20, 40, 30]); } catch (e) {}
      btn.click();
      setTimeout(function () { if (!form.dataset.sent) reset(); }, 500);
    }
    form.addEventListener('submit', function (e) { setTimeout(function () { if (!e.defaultPrevented) { form.dataset.sent = '1'; tr.classList.add('sending'); } }, 0); });
    knob.addEventListener('pointerdown', function (e) { if (done) return; if (need()) return; drag = true; x0 = e.clientX - cur; max = tr.clientWidth - knob.offsetWidth - 8; try { knob.setPointerCapture(e.pointerId); } catch (x) {} tr.classList.add('drag'); });
    knob.addEventListener('pointermove', function (e) { if (!drag) return; set(Math.max(0, Math.min(max, e.clientX - x0))); });
    function up() { if (!drag) return; drag = false; tr.classList.remove('drag'); if (cur >= max * 0.82) fire(); else reset(); }
    knob.addEventListener('pointerup', up); knob.addEventListener('pointercancel', up);
    knob.addEventListener('keydown', function (e) { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); if (!need()) { max = tr.clientWidth - knob.offsetWidth - 8; fire(); } } });
  });

  // haazir hain to screen band na ho (Android Chrome)
  if (document.body.classList.contains('is-online') && 'wakeLock' in navigator) {
    var lock = null, ask = function () { navigator.wakeLock.request('screen').then(function (l) { lock = l; }).catch(function () {}); };
    ask(); document.addEventListener('visibilitychange', function () { if (!document.hidden) ask(); });
  }

  // flash ko toast bana do
  var f = document.querySelector('.sr-flash'), t = document.getElementById('sxToast');
  if (f && t) { t.textContent = f.textContent.trim(); t.classList.add('show'); f.remove(); setTimeout(function () { t.classList.remove('show'); }, 3200); }
})();
</script>
</body>
</html>
<?php
}

/** Shared sizing fixes, also loaded after the legacy admin stylesheet. */
function sr_mobile_assets(): void {
    ?><link rel="stylesheet" href="<?= h(u('/assets/mobile.css')) ?>?v=<?= (int)filemtime(__DIR__.'/../assets/mobile.css') ?>"><?php
}
