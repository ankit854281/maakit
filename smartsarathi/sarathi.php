<?php
// ============================================================
//  सारथी पार्टनर — सारथियों का अलग ऐप (owner decision, 29 Oct 2026)
//
//  Rapido Captain / Uber Driver jaisa: grahak ka app (ghar.php)
//  alag, sarathi ka alag. Yah panna Sarathi Partner app ka
//  darwaza hai: install button, judne ka raasta, aur login ke
//  baad seedha kaam (kaam.php) ya form (apply.php).
//  Manifest: partner.json (alag naam, peela icon).
// ============================================================
require_once __DIR__ . '/inc/public.php';
require_once __DIR__ . '/inc/identity.php';
header('Cache-Control: private, no-store');

$_SESSION['sr_intent'] = 'partner';          // is app se login → partner
$acc = sr_account($pdo);
if ($acc && (get('src') === 'app' || get('go') === '1')) {
    redirect(sr_home_path($pdo));             // installed app se khula → seedha kaam
}
$login = u('/?intent=partner');
$dash  = u('/sarathi.php?go=1');
?><!doctype html>
<html lang="<?= h(sr_lang()) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="theme-color" content="#0B1F3A">
<title><?= h(t('Sarathi Partner — drive and earn', 'सारथी पार्टनर — गाड़ी चलाइए, कमाइए')) ?></title>
<meta name="description" content="<?= h(t('Join Smart Sarathi as a partner with your bike, auto, e-rickshaw, car or loader in Bhadohi.', 'भदोही में अपनी बाइक, ऑटो, ई-रिक्शा, कार या लोडर से स्मार्ट सारथी के पार्टनर बनिए।')) ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Devanagari:wght@400;600;700;800&family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
<?php if (!function_exists('sr_app_head')) require_once __DIR__ . '/inc/ui.php'; sr_app_head('partner'); ?>
<link rel="stylesheet" href="<?= h(u('/assets/customer.css')) ?>?v=<?= (int)@filemtime(__DIR__ . '/assets/customer.css') ?>">
<style>
:root{--amber:#FFC83D}
body{background:#03070D}
.pa{max-width:520px;margin:0 auto;min-height:calc(var(--vh,1vh)*100);background:#03070D;color:#fff;display:flex;flex-direction:column}
.pa-top{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:16px 16px 0}
.pa-brand{display:flex;align-items:center;gap:10px;color:#fff;text-decoration:none}
.pa-brand img{width:44px;height:44px;border-radius:12px;display:block}
.pa-brand b{display:block;font:800 18px 'Plus Jakarta Sans',sans-serif}
.pa-brand small{display:block;font:700 12px 'Noto Sans Devanagari',sans-serif;color:var(--amber)}
.pa-lang{display:flex;background:rgba(255,255,255,.1);border-radius:999px;padding:3px}
.pa-lang a{min-width:48px;min-height:44px;display:grid;place-items:center;border-radius:999px;color:#fff;text-decoration:none;font:700 14px 'Noto Sans Devanagari',sans-serif;padding:0 10px}
.pa-lang a[aria-current=true]{background:#fff;color:#0B1F3A}
.pa-hero{position:relative;height:calc(var(--vh,1vh)*34);min-height:210px;margin:12px 16px 0;border-radius:24px;overflow:hidden;background:radial-gradient(120% 100% at 75% 0%,#1E4A85,#0B1F3A 70%)}
.pa-hero svg{position:absolute;inset:0;width:100%;height:100%}
.pa-body{padding:22px 16px calc(24px + env(safe-area-inset-bottom));display:flex;flex-direction:column;gap:14px;flex:1}
.pa-body h1{margin:0;font:800 28px/1.25 'Noto Sans Devanagari','Plus Jakarta Sans',sans-serif}
.pa-body h1 em{font-style:normal;color:var(--amber)}
.pa-pts{list-style:none;margin:0;padding:0;display:grid;gap:10px}
.pa-pts li{display:flex;gap:12px;align-items:center;background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.08);border-radius:16px;padding:12px 14px;font:600 15px/1.4 'Noto Sans Devanagari',sans-serif;color:#E6EDF5}
.pa-pts i{flex:none;width:36px;height:36px;border-radius:50%;display:grid;place-items:center;background:var(--amber);color:#0B1F3A;font-style:normal;font-weight:900}
.pa-veh{display:flex;flex-wrap:wrap;gap:8px}
.pa-veh span{border:1px solid rgba(255,255,255,.18);border-radius:999px;padding:6px 12px;font:700 13px 'Noto Sans Devanagari',sans-serif;color:#C9D6E5}
.pa-go{display:grid;place-items:center;min-height:56px;border-radius:16px;background:var(--amber);color:#0B1F3A;text-decoration:none;font:800 17px 'Noto Sans Devanagari','Plus Jakarta Sans',sans-serif;box-shadow:0 10px 24px rgba(255,200,61,.22)}
.pa .ca-install{width:100%;margin:0;background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.14);box-shadow:none}
.pa .ca-install-go{background:var(--amber)}
.pa .ca-ihelp{margin:0}
.pa-cust{display:flex;align-items:center;justify-content:space-between;gap:10px;min-height:56px;padding:0 16px;border-radius:16px;background:#fff;color:#0B1F3A;text-decoration:none;font:800 15px 'Noto Sans Devanagari',sans-serif;margin-top:auto}
.pa-cust em{font-style:normal;color:#1D6FD8}
.pa-help{text-align:center;font:600 13px 'Noto Sans Devanagari',sans-serif;color:#8FA3B8}
.pa-help a{color:#fff;display:inline-flex;align-items:center;min-height:44px}
</style>
</head>
<body>
<main class="pa">
  <header class="pa-top">
    <a class="pa-brand" href="<?= h(u('/sarathi.php')) ?>"><img src="<?= h(u('/assets/partner-192.png')) ?>" alt="" width="44" height="44"><span><b>Sarathi Partner</b><small><?= h(t('Smart Sarathi · Bhadohi', 'स्मार्ट सारथी · भदोही')) ?></small></span></a>
    <nav class="pa-lang" aria-label="<?= h(t('Language', 'भाषा')) ?>">
      <a href="?lang=hi" lang="hi" aria-current="<?= sr_lang() === 'hi' ? 'true' : 'false' ?>">हिं</a>
      <a href="?lang=en" lang="en" aria-current="<?= sr_lang() === 'en' ? 'true' : 'false' ?>">EN</a>
    </nav>
  </header>

  <div class="pa-hero" aria-hidden="true">
    <svg viewBox="0 0 360 230" preserveAspectRatio="xMidYMax meet"><circle cx="292" cy="54" r="30" fill="#FFC83D"/><path d="M0 206h360" stroke="#FFC83D" stroke-width="4" stroke-dasharray="10 12" opacity=".8"/><g transform="translate(70 66)"><circle cx="38" cy="122" r="26" fill="none" stroke="#fff" stroke-width="8"/><circle cx="190" cy="122" r="26" fill="none" stroke="#fff" stroke-width="8"/><path d="M38 122l52-58h50l50 58" fill="none" stroke="#fff" stroke-width="8" stroke-linejoin="round"/><path d="M90 64l-14-28h-22M140 64h30" stroke="#fff" stroke-width="8" stroke-linecap="round"/><circle cx="104" cy="12" r="16" fill="#FFC83D"/><path d="M104 28l-6 40h30" stroke="#FFC83D" stroke-width="12" stroke-linecap="round" fill="none"/><rect x="150" y="20" width="44" height="36" rx="8" fill="#10C98F"/><path d="M160 38h24" stroke="#0B1F3A" stroke-width="4" stroke-linecap="round"/></g></svg>
  </div>

  <section class="pa-body">
    <h1><?= t('Your vehicle. Your time. <em>Your earning.</em>', 'अपनी गाड़ी, अपना समय, <em>अपनी कमाई।</em>') ?></h1>
    <ul class="pa-pts">
      <li><i>1</i><?= h(t('Nearby rides and parcels come straight to your phone', 'पास की सवारी और पार्सल का काम सीधे आपके फ़ोन पर')) ?></li>
      <li><i>2</i><?= h(t('Go online when you want, take a break when you want', 'जब चाहें हाज़िर, जब चाहें छुट्टी')) ?></li>
      <li><i>3</i><?= h(t('OTP at pickup and drop — safe for you and the customer', 'पिकअप और ड्रॉप पर OTP — आप और ग्राहक दोनों सुरक्षित')) ?></li>
    </ul>
    <div class="pa-veh" aria-label="<?= h(t('Vehicles', 'गाड़ियाँ')) ?>">
      <span><?= h(t('Bike', 'बाइक')) ?></span><span><?= h(t('Auto', 'ऑटो')) ?></span><span><?= h(t('E-rickshaw', 'ई-रिक्शा')) ?></span><span><?= h(t('Car / Bolero', 'कार / बोलेरो')) ?></span><span><?= h(t('Loader', 'लोडर')) ?></span><span><?= h(t('Hospital Saathi', 'अस्पताल साथी')) ?></span>
    </div>

    <?php if ($acc): ?>
      <a class="pa-go" href="<?= h($dash) ?>"><?= h(t('Open my work →', 'मेरा काम खोलिए →')) ?></a>
    <?php else: ?>
      <a class="pa-go" href="<?= h($login) ?>"><?= h(t('Join / Log in with mobile', 'जुड़िए / मोबाइल से लॉगिन')) ?></a>
    <?php endif; ?>

    <button type="button" class="ca-install" id="install" hidden>
      <span class="ca-install-ic" aria-hidden="true"><img src="<?= h(u('/assets/partner-192.png')) ?>" alt="" width="40" height="40"></span>
      <span class="ca-install-t"><b><?= h(t('Install the Sarathi Partner app', 'सारथी पार्टनर ऐप फ़ोन में डालिए')) ?></b><small><?= h(t('Separate yellow icon · no Play Store', 'अलग पीला आइकन · Play Store की ज़रूरत नहीं')) ?></small></span>
      <span class="ca-install-go"><?= h(t('Install', 'इंस्टॉल')) ?></span>
    </button>
    <div class="ca-ihelp" id="install-help" hidden>
      <b><?= h(t('How to install', 'ऐसे इंस्टॉल कीजिए')) ?></b>
      <ol data-os="android">
        <li><?= h(t('If this opened inside WhatsApp, tap ⋮ at the top → "Open in Chrome".', 'अगर WhatsApp के अंदर खुला है तो ऊपर ⋮ दबाइए → "Chrome में खोलें"।')) ?></li>
        <li><?= h(t('In Chrome tap ⋮ (top right) → "Install app" or "Add to Home screen".', 'Chrome में ऊपर दाएँ ⋮ दबाइए → "ऐप इंस्टॉल करें" या "होम स्क्रीन पर जोड़ें"।')) ?></li>
        <li><?= h(t('The yellow Sarathi Partner icon will appear on your phone.', 'फ़ोन पर पीले रंग का सारथी पार्टनर आइकन आ जाएगा।')) ?></li>
      </ol>
      <ol data-os="ios" hidden>
        <li><?= h(t('Open this page in Safari.', 'यह पेज Safari में खोलिए।')) ?></li>
        <li><?= h(t('Tap Share (square with arrow) → "Add to Home Screen".', 'शेयर (तीर वाला डिब्बा) दबाइए → "Add to Home Screen"।')) ?></li>
      </ol>
      <button type="button" class="ca-ihelp-x" id="install-x"><?= h(t('Got it', 'समझ गया')) ?></button>
    </div>

    <a class="pa-cust" href="<?= h(u('/ghar.php')) ?>"><span><?= h(t('Want to book a ride?', 'सवारी बुक करनी है?')) ?></span><em><?= h(t('Customer app →', 'ग्राहक ऐप →')) ?></em></a>
    <p class="pa-help"><a href="tel:<?= h(defined('SR_PHONE') ? SR_PHONE : '') ?>"><?= h(t('Need help? Call the office', 'मदद चाहिए? दफ़्तर को फ़ोन')) ?></a></p>
  </section>
</main>
<script>
(function () {
  var btn = document.getElementById('install'), help = document.getElementById('install-help');
  var standalone = (window.matchMedia && matchMedia('(display-mode: standalone)').matches) || navigator.standalone;
  if (standalone) return;
  var ios = /iphone|ipad|ipod/i.test(navigator.userAgent), deferred = null;
  btn.hidden = false;
  window.addEventListener('beforeinstallprompt', function (e) { e.preventDefault(); deferred = e; });
  window.addEventListener('appinstalled', function () { btn.hidden = true; help.hidden = true; });
  btn.addEventListener('click', function () {
    if (deferred) { deferred.prompt(); deferred.userChoice.then(function (r) { if (r && r.outcome === 'accepted') btn.hidden = true; deferred = null; }); return; }
    Array.prototype.forEach.call(help.querySelectorAll('ol'), function (o) { o.hidden = (o.getAttribute('data-os') === 'ios') !== ios; });
    help.hidden = false; help.scrollIntoView({ behavior: 'smooth', block: 'center' });
  });
  document.getElementById('install-x').addEventListener('click', function () { help.hidden = true; });
})();
</script>
</body>
</html>
