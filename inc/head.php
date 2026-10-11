<?php
require_once __DIR__ . "/seo.php";
require_once __DIR__ . "/theme.php";
$_theme = maakit_theme($pdo ?? null);
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
<meta name="theme-color" content="<?= h($_theme['brand']) ?>">
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
<link rel="stylesheet" href="/assets/style.css?v=<?= (int)filemtime(__DIR__.'/../assets/style.css') ?>">
<link rel="stylesheet" href="/assets/app.css?v=<?= (int)filemtime(__DIR__.'/../assets/app.css') ?>">
<?php if(!empty($request_design)): ?><link rel="stylesheet" href="/assets/request-design.css?v=<?= (int)filemtime(__DIR__.'/../assets/request-design.css') ?>"><?php endif; ?>
<!-- photo bhejne se pehle chhoti kar deta hai — dheeme net ke liye -->
<script src="/assets/shrink.js?v=<?= defined('MAAKIT_VERSION') ? h(MAAKIT_VERSION) : '1' ?>" defer></script>
<?php if(!empty($hub_design)): ?><link rel="stylesheet" href="/assets/hub.css?v=<?= (int)filemtime(__DIR__.'/../assets/hub.css') ?>"><?php endif; ?>
<!-- naya design (11 Oct 2026) — sabse aakhir me -->
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Noto+Sans+Devanagari:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/mk.css?v=<?= (int)filemtime(__DIR__.'/../assets/mk.css') ?>">
<style><?= maakit_theme_css($pdo ?? null) ?></style>
</head>
<body class="<?= $no_tabbar?'':'has-tabbar' ?><?= !empty($request_design)?' request-design':'' ?>">

<?php
require_once __DIR__ . '/festival.php';
$_fest = empty($no_tabbar) ? maakit_festival_now($pdo ?? null) : null;
$_c = function_exists('cust') ? cust() : null;
$_area = (empty($no_tabbar) && function_exists('coverage_selected')) ? coverage_selected($pdo) : null;
?>
<?php if ($_fest): ?>
<a class="mk-fest-strip" href="/search.php?q=<?= h(urlencode($_fest['q'])) ?>" style="--fbg:<?= h($_fest['bg']) ?>;--facc:<?= h($_fest['accent']) ?>">
  <span class="mk-wrap"><b><?= h(t($_fest['en'], $_fest['hi'])) ?></b><span class="mk-fs-sub"><?= h(t($_fest['cta_en'], $_fest['cta_hi'])) ?> →</span></span>
</a>
<?php elseif ($tick): ?>
<div class="ticker" aria-label="<?= h($tick_hul ? t('Recent activity', 'अभी-अभी') : t('Service areas', 'सेवा क्षेत्र')) ?>">
  <div class="tk">
    <span class="lbl"><?= $tick_hul ? t('JUST NOW', 'अभी-अभी') : t('WE DELIVER IN', 'यहाँ पहुँचते हैं') ?></span>
    <div class="run"><div class="rr">
      <?php for ($p = 0; $p < 2; $p++): foreach ($tick as $v): ?>
        <span><?= h($v) ?></span><i>·</i>
      <?php endforeach; endfor; ?>
    </div></div>
  </div>
</div>
<?php endif; ?>

<header class="mk-head">
  <div class="mk-wrap mk-head-row">
    <a class="mk-brand" href="/" aria-label="Maakit home">
      <?= maakit_logo_svg(36) ?>
      <span class="mk-word">Maakit<small><?= t('Maa hai na', 'माँ है ना') ?></small></span>
    </a>
    <?php if (empty($no_tabbar)): ?>
    <a class="mk-loc" href="/location.php">
      <small><?= t('Delivering to', 'डिलीवरी') ?></small>
      <b><?= $_area ? h(coverage_label($_area)) : t('Select area', 'इलाका चुनिए') ?> <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg></b>
    </a>
    <form class="mk-search" action="/search.php" method="get" role="search">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
      <input type="search" name="q" placeholder="<?= h(t('Search atta, medicine, Bolero…', 'खोजिए: आटा, दवाई, बोलेरो…')) ?>" aria-label="<?= h(t('Search', 'खोजिए')) ?>">
    </form>
    <?php endif; ?>
    <div class="mk-actions">
      <a class="mk-call" href="tel:<?= MAAKIT_PHONE ?>"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2"/></svg><span><?= MAAKIT_NUMBER_SHOW ?></span></a>
      <span class="mk-lang" role="group" aria-label="Language / भाषा">
        <a href="<?= h(lang_url('en')) ?>" lang="en" rel="nofollow"<?= is_hi() ? '' : ' class="on" aria-current="true"' ?>>EN</a>
        <a href="<?= h(lang_url('hi')) ?>" lang="hi" rel="nofollow"<?= is_hi() ? ' class="on" aria-current="true"' : '' ?>>हिं</a>
      </span>
      <?php if (empty($no_tabbar)): ?>
      <a class="mk-acct" href="/account.php" aria-label="<?= h($_c ? t('Your account', 'आपका खाता') : t('Sign in', 'लॉगिन')) ?>">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 21c1.5-4 5-6 8-6s6.5 2 8 6"/></svg>
        <span><?= $_c ? h(mb_substr(explode(' ', trim($_c['name']))[0], 0, 10)) : t('Login', 'लॉगिन') ?></span>
      </a>
      <?php endif; ?>
    </div>
  </div>
</header>
<?php if ($f = flash()): ?><div class="wrap" style="padding-top:14px"><div class="ok"><?= h($f) ?></div></div><?php endif; ?>

