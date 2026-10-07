<?php
require_once __DIR__ . "/seo.php";
$seo=seo_page($_SERVER["REQUEST_URI"] ?? "/", $_GET, $_SERVER["REQUEST_METHOD"] ?? "GET");
$page_title = $page_title ?? t('Maakit — shopping, delivery & bookings', 'Maakit — शॉपिंग, डिलीवरी और बुकिंग');
$tab = $tab ?? '';           // ghar | order | mere | kaam
$no_tabbar = $no_tabbar ?? false;
$no_ticker = $no_ticker ?? false;

// ---------------------------------------------------------------
// Upar chalne wali patti.
//
// Pehle isme sirf gaon ke naam chalte the. Ab isme asli hulchul
// chalti hai — kaunse gaon me abhi-abhi order ya booking hui.
// Ye jhoothi nahi hoti: jo sach me database me hai wahi dikhta hai.
// Agar 4 se kam asli khabar ho to gaon ke naam par wapas chala
// jata hai, taaki patti khaali na lage.
// ---------------------------------------------------------------
$tick     = [];
$tick_hul = false;         // true = asli hulchul, false = gaon ke naam

if (!$no_tabbar && !$no_ticker) {
    try {
        $rows = $pdo->query(
            "SELECT village, created_at, 'o' AS kind FROM orders
               WHERE status<>'Cancel' AND created_at > NOW() - INTERVAL 2 DAY
             UNION ALL
             SELECT village, created_at, 'b' FROM service_bookings
               WHERE status<>'Cancel' AND created_at > NOW() - INTERVAL 2 DAY
             ORDER BY created_at DESC LIMIT 10"
        )->fetchAll();

        foreach ($rows as $r) {
            if (empty($r['village'])) continue;
            $tick[] = vname(['name' => $r['village']]) . ' · '
                    . ($r['kind'] === 'o' ? t('order', 'ऑर्डर') : t('booking', 'बुकिंग'))
                    . ' ' . ago($r['created_at']);
        }
        $tick_hul = count($tick) >= 4;

        if (!$tick_hul) {   // abhi itni hulchul nahi — gaon ke naam dikha dijiye
            $tick = [];
            foreach ($pdo->query("SELECT name, name_en FROM villages WHERE live=1 ORDER BY name") as $r) {
                $tick[] = vname($r);
            }
        }
    } catch (Throwable $e) { $tick = []; $tick_hul = false; }
}
?><!doctype html>
<html lang="<?= html_lang() ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= h($page_title) ?></title>
<meta name="description" content="<?= h(t(
  'Discover shops, products, delivery and vehicle or service bookings on Maakit in India. Select your location to check active service coverage and local availability.',
  'Maakit पर भारत में दुकानें, सामान, डिलीवरी, गाड़ी और सेवाओं की बुकिंग देखें। अपना इलाका चुनकर चालू सेवा क्षेत्र और उपलब्धता जाँचें।')) ?>">
<meta name="robots" content="<?= $seo['index'] ? 'index,follow' : 'noindex,follow' ?>">
<?php if ($seo['canonical']): ?>
<link rel="canonical" href="<?= h($seo['canonical']) ?>">
<meta property="og:url" content="<?= h($seo['canonical']) ?>">
<?php endif; ?>
<meta name="theme-color" content="#7A1F1F">
<link rel="manifest" href="/manifest.json">
<link rel="apple-touch-icon" href="/assets/icon-180.png">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="Maakit">
<meta property="og:title" content="<?= h($page_title) ?>">
<meta property="og:type" content="website">
<meta property="og:site_name" content="Maakit">
<?php if ($seo['index'] && $seo['canonical'] === 'https://maakit.in/'): ?>
<!-- Fixed brand identity; no invented reviews, address or social profiles. -->
<script type="application/ld+json">
{"@context":"https://schema.org","@type":"WebSite","@id":"https://maakit.in/#website","name":"Maakit","alternateName":"maakit.in","url":"https://maakit.in/"}
</script>
<?php endif; ?>
<meta property="og:image" content="/assets/icon-512.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/style.css">
<link rel="stylesheet" href="/assets/app.css">
<!-- photo bhejne se pehle chhoti kar deta hai — dheeme net ke liye -->
<script src="/assets/shrink.js?v=<?= defined('MAAKIT_VERSION') ? h(MAAKIT_VERSION) : '1' ?>" defer></script>
</head>
<body<?= $no_tabbar ? '' : ' class="has-tabbar"' ?>>

<?php if ($tick): ?>
<!-- jahan-jahan Maakit pahunchta hai -->
<div class="ticker" aria-label="<?= h($tick_hul ? t('Recent activity', 'अभी-अभी') : t('Service areas', 'सेवा क्षेत्र')) ?>">
  <div class="tk">
    <span class="lbl"><?= svc_icon('box', 13) ?> <?= $tick_hul
        ? t('JUST NOW', 'अभी-अभी')
        : t('WE DELIVER IN', 'यहाँ पहुँचते हैं') ?></span>
    <div class="run"><div class="rr">
      <?php for ($p = 0; $p < 2; $p++): foreach ($tick as $v): ?>
        <span><?= h($v) ?></span><i>·</i>
      <?php endforeach; endfor; ?>
    </div></div>
    <a class="add" href="/location.php"><?= t('Check your area', 'अपना इलाका देखें') ?></a>
  </div>
</div>
<?php endif; ?>

<header class="top">
  <div class="wrap">
    <a class="brand" href="/">
      <span class="mark"><svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#7A1F1F" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3.5 10.5L12 3.5l8.5 7"/><rect x="6.5" y="12" width="11" height="8.5" rx="1.6"/><path d="M9.8 12v-1a2.2 2.2 0 0 1 4.4 0v1"/></svg></span>
      <span class="word">Maa<span>kit</span></span>
    </a>
    <?php $_c = function_exists('cust') ? cust() : null; ?>
    <span class="hdr">
      <a class="lang" href="<?= h(lang_switch_url()) ?>" title="<?= h(lang_other_label()) ?>" rel="nofollow"><?= h(lang_other_label()) ?></a>
      <a class="tlink" href="tel:<?= MAAKIT_PHONE ?>"><?= MAAKIT_NUMBER_SHOW ?></a>
      <?php if (empty($no_tabbar)): ?>
        <a class="acct <?= $_c ? 'in' : '' ?>" href="/account.php">
          <?= svc_icon('user', 18) ?>
          <span class="lbl"><?= $_c ? h(mb_substr(explode(' ', trim($_c['name']))[0], 0, 10)) : t('Sign in', 'लॉगिन') ?></span>
        </a>
      <?php endif; ?>
    </span>
  </div>
</header>
<?php if ($f = flash()): ?><div class="wrap" style="padding-top:14px"><div class="ok"><?= h($f) ?></div></div><?php endif; ?>

<?php if (empty($no_tabbar)): $chosen_area=coverage_selected($pdo); ?>
<div class="locationbar"><div class="wrap"><a href="/location.php"><?= svc_icon('box',18) ?> <b><?= $chosen_area ? h(coverage_label($chosen_area)) : t('Choose city / area / PIN','शहर / इलाका / PIN चुनें') ?></b> <span><?=t('Change','बदलें')?></span></a><a href="/support.php"><?=t('Help','सहायता')?></a></div></div>
<?php endif; ?>
