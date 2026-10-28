<?php
// ============================================================
//  स्मार्ट सारथी — ग्राहक का होम (8 सेवाएँ)
//
//  Owner design, 29 Oct 2026: "कहाँ जाना है?" box, pehli line
//  बाइक · ऑटो · ई-रिक्शा, doosri line सामान · पार्सल · गाड़ी बुकिंग ·
//  माल गाड़ी · साथ सफ़र. Login zaroori nahi — sirf booking ke waqt.
//
//  Booking ke panne abhi maakit.in par chalte hain (ride.php,
//  sewa.php) — wahi backend jo riders tak kaam pahunchata hai.
//  Isliye har dibba wahan jaata hai (sr_maakit_url).
// ============================================================
require_once __DIR__ . '/inc/public.php';
require_once __DIR__ . '/inc/identity.php';
header('Cache-Control: private, no-store');

$acc = function_exists('sr_account') ? sr_account($pdo) : null;
$M = fn($p) => sr_maakit_url($p);

$rides = [
    ['बाइक', 'Bike', $M('/ride.php?v=bike'), '<circle cx="11" cy="32" r="7"/><circle cx="37" cy="32" r="7"/><path d="M11 32l9-12h9l8 12M24 20l-3-6h-5M29 20h6"/>'],
    ['ऑटो', 'Auto', $M('/ride.php?v=auto'), '<path d="M8 32V20c0-5 4-9 9-9h12l7 9h4v12H8z"/><path d="M20 11v21M8 24h28"/><circle cx="14" cy="34" r="4"/><circle cx="34" cy="34" r="4"/>'],
    ['ई-रिक्शा', 'E-rickshaw', $M('/ride.php?v=erick'), '<path d="M10 12h26v6H10zM12 18v14h26V22l-4-4"/><circle cx="16" cy="34" r="4"/><circle cx="34" cy="34" r="4"/><path d="M22 10l2-4"/>'],
];
$more = [
    ['सामान', 'Shopping', $M('/bazaar.php'), '#E7F8F2', '<path d="M5 8h14l-1 12H6z"/><path d="M9 8a3 3 0 0 1 6 0"/>'],
    ['पार्सल', 'Parcel', $M('/ride.php?t=parcel'), '#E8F0FB', '<path d="M3 7l9-4 9 4v10l-9 4-9-4z"/><path d="M3 7l9 4 9-4M12 11v10"/>'],
    ['गाड़ी बुकिंग', 'Book a vehicle', $M('/sewa.php?s=gaadi'), '#FDF1E3', '<path d="M3 14l2-6h14l2 6v4H3z"/><circle cx="7.5" cy="18" r="1.8"/><circle cx="16.5" cy="18" r="1.8"/><path d="M3 14h18"/>'],
    ['माल गाड़ी', 'Goods', $M('/sewa.php?s=maal'), '#EFEDFB', '<path d="M2 6h12v10H2zM14 10h4l4 4v2h-8z"/><circle cx="6" cy="18" r="2"/><circle cx="18" cy="18" r="2"/>'],
    ['साथ सफ़र', 'Share a ride', $M('/sewa.php?s=saath'), '#E7F8F2', '<circle cx="8" cy="8" r="3"/><circle cx="17" cy="9" r="2.5"/><path d="M2 20c0-3.3 2.7-6 6-6s6 2.7 6 6M14 15.5c.9-.6 1.9-1 3-1 2.8 0 5 2.2 5 5"/>'],
];
?><!doctype html>
<html lang="<?= h(sr_lang()) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="theme-color" content="#0B1F3A">
<title><?= h(t('Smart Sarathi — rides, parcels, vehicles', 'स्मार्ट सारथी — सवारी, पार्सल, गाड़ी')) ?></title>
<meta name="description" content="<?= h(t('Bike, auto, e-rickshaw, parcel, shopping, vehicle booking, goods transport and shared rides in Bhadohi.', 'भदोही में बाइक, ऑटो, ई-रिक्शा, पार्सल, सामान, गाड़ी बुकिंग, माल गाड़ी और साथ सफ़र।')) ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Devanagari:wght@400;600;700;800&family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
<?php if (!function_exists('sr_app_head')) require_once __DIR__ . '/inc/ui.php'; sr_app_head(); ?>
<style>
:root{--navy:#0B1F3A;--mint:#1FCB97;--bg:#F4F6F9;--line:#E3E8EF;--muted:#4B5A6B;--red:#B42318}
*{box-sizing:border-box}
body{margin:0;background:var(--bg);color:var(--navy);font-family:'Noto Sans Devanagari','Plus Jakarta Sans',system-ui,sans-serif}
a{color:inherit;text-decoration:none}
.gh{max-width:520px;margin:0 auto;min-height:100vh;display:flex;flex-direction:column;padding-bottom:84px}
.gh-top{background:var(--navy);color:#fff;padding:18px 18px 64px}
.gh-row{display:flex;align-items:center;justify-content:space-between;gap:12px}
.gh-logo{display:flex;align-items:center;gap:10px;font:800 18px 'Plus Jakarta Sans',sans-serif}
.gh-mark{width:40px;height:40px;border-radius:12px;background:var(--mint);display:grid;place-items:center}
.gh-lang{display:flex;gap:4px;background:rgba(255,255,255,.12);border-radius:999px;padding:4px}
.gh-lang a{padding:6px 11px;border-radius:999px;font:700 12px 'Plus Jakarta Sans','Noto Sans Devanagari',sans-serif;color:#fff}
.gh-lang a[aria-current]{background:#fff;color:var(--navy)}
.gh-hi{font:700 22px/1.35 'Noto Sans Devanagari',sans-serif;margin:16px 0 0}
.gh-where{margin:-46px 16px 0;background:#fff;border-radius:18px;box-shadow:0 8px 24px rgba(11,31,58,.12);padding:12px 12px 12px 18px;display:flex;align-items:center;gap:12px;min-height:68px}
.gh-where .dot{width:10px;height:10px;border-radius:50%;background:var(--mint);flex:none}
.gh-where b{flex:1;font:700 17px 'Noto Sans Devanagari',sans-serif;color:var(--muted)}
.gh-where .go{width:44px;height:44px;border-radius:12px;background:var(--navy);display:grid;place-items:center}
.gh-sec{padding:20px 16px 0}
.gh-sec h2{margin:0 0 10px;font:700 14px 'Noto Sans Devanagari',sans-serif;color:var(--muted)}
.gh-rides{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px}
.gh-rides a{background:#fff;border:1px solid var(--line);border-radius:16px;padding:16px 8px;display:flex;flex-direction:column;align-items:center;gap:8px;font:700 15px 'Noto Sans Devanagari',sans-serif;transition:border-color .15s,transform .15s}
.gh-rides a:hover,.gh-more a:hover .ic{border-color:var(--navy)}
.gh-rides a:active{transform:scale(.97)}
.gh-more{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:8px}
.gh-more a{display:flex;flex-direction:column;align-items:center;gap:6px;font:600 12.5px/1.25 'Noto Sans Devanagari',sans-serif;text-align:center}
.gh-more .ic{width:58px;height:58px;border-radius:16px;display:grid;place-items:center;border:1px solid transparent}
.gh-care{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}
.gh-care a{background:#fff;border:1px solid var(--line);border-top:4px solid var(--mint);border-radius:16px;padding:14px 12px;display:flex;flex-direction:column;gap:6px;min-height:110px}
.gh-care b{font:800 16px 'Noto Sans Devanagari',sans-serif}
.gh-care small{font:500 12.5px/1.4 'Noto Sans Devanagari',sans-serif;color:var(--muted)}
.gh-sos{margin:20px 16px 0;background:#fff;border:1px solid #F3C9C9;border-radius:16px;padding:12px 14px;display:flex;align-items:center;gap:12px;color:var(--red);font:700 14px 'Noto Sans Devanagari',sans-serif;min-height:56px}
.gh-sos span.c{width:36px;height:36px;border-radius:50%;background:#FBEBEB;display:grid;place-items:center;flex:none}
.gh-join{margin:12px 16px 0;background:var(--navy);color:#fff;border-radius:16px;padding:14px 16px;display:flex;align-items:center;justify-content:space-between;gap:12px;font:600 14px/1.4 'Noto Sans Devanagari',sans-serif}
.gh-join em{font-style:normal;background:var(--mint);color:var(--navy);border-radius:12px;padding:10px 12px;font-weight:800;white-space:nowrap}
.gh-nav{position:fixed;left:0;right:0;bottom:0;background:#fff;border-top:1px solid var(--line);display:flex;justify-content:center;padding:8px 0 calc(10px + env(safe-area-inset-bottom))}
.gh-nav div{max-width:520px;width:100%;display:grid;grid-template-columns:repeat(3,minmax(0,1fr))}
.gh-nav a{display:flex;flex-direction:column;align-items:center;gap:3px;font:600 12px 'Noto Sans Devanagari',sans-serif;color:var(--muted);min-height:44px}
.gh-nav a[aria-current]{color:var(--navy);font-weight:800}
@media(max-width:360px){.gh-more{grid-template-columns:repeat(3,minmax(0,1fr));row-gap:14px}}
</style>
</head>
<body>
<main class="gh">
  <header class="gh-top">
    <div class="gh-row">
      <a class="gh-logo" href="<?= h(u('/ghar.php')) ?>"><span class="gh-mark"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#0B1F3A" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M13 2 4 14h7l-1 8 9-12h-7z"/></svg></span>Sarathi</a>
      <nav class="gh-lang" aria-label="<?= h(t('Language', 'भाषा')) ?>">
        <a href="?lang=hi"<?= sr_lang() === 'hi' ? ' aria-current="true"' : '' ?>>हिन्दी</a>
        <a href="?lang=en"<?= sr_lang() === 'en' ? ' aria-current="true"' : '' ?>>EN</a>
      </nav>
    </div>
    <p class="gh-hi"><?= h(t('Hello! What do you need today?', 'नमस्ते! आज क्या चाहिए?')) ?></p>
  </header>

  <a class="gh-where" href="<?= h($M('/ride.php')) ?>">
    <span class="dot"></span>
    <b><?= h(t('Where to?', 'कहाँ जाना है?')) ?></b>
    <span class="go" aria-hidden="true"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.4" stroke-linecap="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg></span>
  </a>

  <section class="gh-sec" aria-labelledby="h-ride">
    <h2 id="h-ride"><?= h(t('Ride — fare shown before you book', 'सवारी — बुक करने से पहले किराया')) ?></h2>
    <div class="gh-rides">
      <?php foreach ($rides as [$hi, $en, $href, $svg]): ?>
      <a href="<?= h($href) ?>"><svg width="40" height="40" viewBox="0 0 48 48" fill="none" stroke="#0B1F3A" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><?= $svg ?></svg><?= h(t($en, $hi)) ?></a>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="gh-sec" aria-labelledby="h-more">
    <h2 id="h-more"><?= h(t('More services', 'और सेवाएँ')) ?></h2>
    <div class="gh-more">
      <?php foreach ($more as [$hi, $en, $href, $bg, $svg]): ?>
      <a href="<?= h($href) ?>"><span class="ic" style="background:<?= h($bg) ?>"><svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#0B1F3A" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><?= $svg ?></svg></span><?= h(t($en, $hi)) ?></a>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="gh-sec" aria-labelledby="h-care">
    <h2 id="h-care"><?= h(t('Special for families', 'परिवार के लिए ख़ास')) ?></h2>
    <div class="gh-care">
      <a href="<?= h($M('/sewa.php?s=door')) ?>"><b><?= h(t('Care from afar', 'दूर से सेवा')) ?></b><small><?= h(t('Away from home? Book medicine, gas, ration for your parents', 'घर से दूर हैं? माँ-पापा के लिए दवाई, गैस, राशन भेजिए')) ?></small></a>
      <a href="<?= h($M('/sewa.php?s=aspatal')) ?>"><b><?= h(t('Hospital companion', 'अस्पताल साथी')) ?></b><small><?= h(t('Vehicle + helper for the whole hospital visit', 'गाड़ी + पूरे समय साथ रहने वाला साथी')) ?></small></a>
    </div>
  </section>

  <a class="gh-sos" href="tel:<?= h(SR_PHONE) ?>">
    <span class="c" aria-hidden="true"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#B42318" stroke-width="2.4" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg></span>
    <span><?= h(t('Emergency or hospital? Call now — ', 'आपातकाल या अस्पताल? तुरंत फ़ोन — ')) ?><?= h(SR_PHONE_SHOW) ?></span>
  </a>

  <a class="gh-join" href="<?= h(u($acc ? '/apply.php' : '/')) ?>">
    <span><?= h(t('Have a bike, auto or vehicle? Earn with Sarathi.', 'बाइक, ऑटो या गाड़ी है? सारथी के साथ कमाइए।')) ?></span>
    <em><?= h(t('Join', 'जुड़िए')) ?></em>
  </a>
</main>

<nav class="gh-nav" aria-label="<?= h(t('Main', 'मुख्य')) ?>"><div>
  <a href="<?= h(u('/ghar.php')) ?>" aria-current="page"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linejoin="round" aria-hidden="true"><path d="M3 11l9-7 9 7v9H3z"/></svg><?= h(t('Home', 'होम')) ?></a>
  <a href="<?= h($M('/ride.php#mine')) ?>"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="4" y="4" width="16" height="16" rx="3"/><path d="M8 9h8M8 13h8M8 17h5"/></svg><?= h(t('My bookings', 'मेरी बुकिंग')) ?></a>
  <a href="<?= h(u($acc ? '/account.php' : '/')) ?>"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4.4 3.6-8 8-8s8 3.6 8 8"/></svg><?= h($acc ? t('Account', 'खाता') : t('Login', 'लॉगिन')) ?></a>
</div></nav>
<p style="text-align:center;margin:0 0 90px;font:500 12px sans-serif"><a href="<?= h(u('/privacy.php')) ?>" style="color:#4B5A6B"><?= h(t('Privacy policy', 'प्राइवेसी पॉलिसी')) ?></a></p>
</body>
</html>
