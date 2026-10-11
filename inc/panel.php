<?php
// panel ka upar wala menu (bpo / delivery / admin)
$u = user();
$page_title = $page_title ?? 'Maakit पैनल';
$no_tabbar = true;                 // panel me neeche ka customer menu nahi
include __DIR__ . '/head.php';
$role = $u['role'];

// ---- hafte me ek baar database ki nakal, apne aap ----
// Malik panel kholta hai to jaanch leta hai ki pichhli nakal kitni
// purani hai. 7 din se purani ho to nayi bana deta hai. Isse na cron
// chahiye, na kuchh yaad rakhna padta hai.
if ($role === 'admin' && isset($pdo)) {
    @include_once __DIR__ . '/nakal-fn.php';
    if (function_exists('nakal_apne_aap')) {
        try { nakal_apne_aap($pdo, dirname(__DIR__, 2) . '/maakit-backups/db'); }
        catch (Throwable $e) { /* nakal na bane to panel ruke nahi */ }
    }
}
$menu = [];   // [group label => [href => [label, icon]]]
if ($role === 'admin') {
    $menu = [
        t('Overview', 'सारांश') => [
            '/admin/dash.php'      => [t('Dashboard', 'डैशबोर्ड'), 'chart'],
            '/admin/'              => [t('Orders', 'ऑर्डर'), 'box'],
            '/bpo/bookings.php'    => [t('Bookings', 'बुकिंग'), 'calendar'],
            '/admin/summary.php'   => [t('Accounts', 'हिसाब'), 'rupee'],
            '/muneem.php'          => [t('Ledger (Muneem)', 'मुनीम'), 'book'],
        ],
        t('Catalogue', 'सामान') => [
            '/admin/product-photos.php' => [t('Product photos', 'सामान की फ़ोटो'), 'image'],
            '/admin/items.php'     => [t('Products', 'सामान'), 'bag'],
            '/admin/daam.php'      => [t('Prices & brands', 'दाम/ब्रांड'), 'tag'],
            '/admin/photos.php'    => [t('Bulk item photos', 'फ़ोटो एक साथ'), 'image'],
            '/admin/banners.php'   => [t('Offers & banners', 'ऑफ़र'), 'star'],
            '/admin/books.php'     => [t('Old books', 'किताबें'), 'book'],
        ],
        t('Partners', 'साथी') => [
            '/admin/businesses.php' => [t('Shops & workers', 'दुकान/कारीगर'), 'shop'],
            '/admin/transport.php' => [t('Vehicles', 'गाड़ियाँ'), 'truck'],
            '/admin/sarathi.php'   => [t('Sarathi riders', 'सारथी'), 'user'],
            '/admin/rates.php'     => [t('Rates', 'रेट'), 'rupee'],
        ],
        t('Areas & customers', 'इलाके और ग्राहक') => [
            '/admin/coverage.php'  => [t('Service areas', 'सेवा क्षेत्र'), 'map'],
            '/admin/areas.php'     => [t('New area requests', 'नए इलाके'), 'map'],
            '/admin/support.php'   => [t('Support', 'सहायता'), 'help'],
            '/admin/feedback.php'  => [t('Reviews', 'राय'), 'star'],
        ],
        t('Settings', 'सेटिंग') => [
            '/admin/settings.php'  => [t('Hours & festival', 'समय/त्योहार'), 'clock'],
            '/admin/users.php'     => [t('Team', 'टीम'), 'user'],
            '/admin/readiness.php' => [t('Launch readiness', 'Launch तैयारी'), 'check'],
            '/nakal.php'           => [t('Backups', 'नक़ल'), 'shield'],
        ],
    ];
} elseif ($role === 'designer') {
    // Designer ka kaam sirf dikhne wali cheezein — offer, photo, home page.
    $menu = [t('Design', 'डिज़ाइन') => [
        '/admin/product-photos.php' => [t('Product photos', 'सामान की फ़ोटो'), 'image'],
        '/admin/banners.php' => [t('Offers & banners', 'ऑफ़र'), 'star'],
        '/admin/photos.php'  => [t('Bulk item photos', 'फ़ोटो एक साथ'), 'image'],
        '/admin/items.php'   => [t('Products', 'सामान'), 'bag'],
    ]];
} elseif ($role === 'bpo') {
    $menu = [t('Work', 'काम') => [
        '/bpo/' => [t("Today's orders", 'आज के ऑर्डर'), 'box'], '/bpo/new.php' => [t('New order', 'नया ऑर्डर'), 'plus'],
        '/bpo/bookings.php' => [t('Bookings', 'बुकिंग'), 'calendar'], '/bpo/summary.php' => [t("Today's accounts", 'आज का हिसाब'), 'rupee'],
    ]];
} else {
    $menu = [t('Work', 'काम') => [
        '/delivery/' => [t('My orders', 'मेरे ऑर्डर'), 'box'], '/delivery/photo.php' => [t('Product photos', 'सामान की फ़ोटो'), 'image'],
    ]];
}
if (!function_exists('adm_icon')) {
function adm_icon($k) {
    $p = [
        'chart' => '<path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>', 'box' => '<path d="M3.6 7.6 12 3.4l8.4 4.2v8.8L12 20.6l-8.4-4.2z"/><path d="M3.6 7.6 12 11.8l8.4-4.2M12 11.8v8.8"/>',
        'calendar' => '<rect x="3.5" y="5" width="17" height="15.5" rx="2.4"/><path d="M8 3v4M16 3v4M3.5 10h17"/>', 'rupee' => '<path d="M7 5h10M7 9h10M7 5c6 0 6 8 0 8l7 7"/>',
        'book' => '<path d="M4 5.5A2.5 2.5 0 0 1 6.5 3H20v16H6.5A2.5 2.5 0 0 0 4 21.5z"/><path d="M4 5.5v16"/>', 'image' => '<rect x="3" y="4" width="18" height="16" rx="2.5"/><circle cx="9" cy="10" r="2"/><path d="M21 16l-5-5-9 9"/>',
        'bag' => '<path d="M5 8h14l-1.2 12H6.2z"/><path d="M9 8V6.5a3 3 0 0 1 6 0V8"/>', 'tag' => '<path d="M3 12V4h8l10 10-8 8z"/><circle cx="7.5" cy="8.5" r="1.4"/>',
        'star' => '<path d="M12 3l2.7 5.6 6.1.9-4.4 4.3 1 6.1L12 17l-5.4 2.9 1-6.1-4.4-4.3 6.1-.9z"/>', 'shop' => '<path d="M3 9l1.5-5h15L21 9"/><path d="M4 9v11h16V9M3 9h18M9 20v-6h6v6"/>',
        'truck' => '<path d="M2 6h12v10H2zM14 10h4l3 3v3h-7z"/><circle cx="6" cy="18" r="1.8"/><circle cx="17" cy="18" r="1.8"/>', 'user' => '<circle cx="12" cy="8" r="4"/><path d="M4 21c1.5-4 5-6 8-6s6.5 2 8 6"/>',
        'map' => '<path d="M12 21s-7-6.3-7-11.5A7 7 0 0 1 19 9.5C19 14.7 12 21 12 21z"/><circle cx="12" cy="9.5" r="2.5"/>', 'help' => '<circle cx="12" cy="12" r="9"/><path d="M9.5 9.5a2.5 2.5 0 1 1 3.5 2.3c-.6.3-1 .8-1 1.5V14M12 17.5v.01"/>',
        'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>', 'check' => '<path d="M4 12.5l5 5L20 6.5"/>', 'shield' => '<path d="M12 3l8 3v6c0 4.5-3.4 8-8 9-4.6-1-8-4.5-8-9V6z"/>', 'plus' => '<path d="M12 5v14M5 12h14"/>',
        'menu' => '<path d="M4 6h16M4 12h16M4 18h16"/>', 'out' => '<path d="M15 4h4v16h-4M10 8l-4 4 4 4M6 12h10"/>',
    ];
    return '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ($p[$k] ?? '') . '</svg>';
}
}
$cur = strtok($_SERVER['REQUEST_URI'], '?');
$adm_shell = true;
?>
<div class="adm">
  <details class="adm-side" id="admSide">
    <summary><?= adm_icon('menu') ?> <?= t('Menu', 'मेन्यू') ?></summary>
    <nav aria-label="<?= h(t('Admin menu', 'एडमिन मेन्यू')) ?>">
      <?php foreach ($menu as $grp => $links): ?>
        <div class="adm-grp"><?= h($grp) ?></div>
        <?php foreach ($links as $href => [$label, $ic]): ?>
          <a class="<?= $cur === $href ? 'on' : '' ?>" href="<?= h($href) ?>"><?= adm_icon($ic) ?><span><?= h($label) ?></span></a>
        <?php endforeach; ?>
      <?php endforeach; ?>
      <div class="adm-grp"></div>
      <a href="/" target="_blank" rel="noopener"><?= adm_icon('shop') ?><span><?= t('View website', 'वेबसाइट देखिए') ?></span></a>
      <a href="/logout.php"><?= adm_icon('out') ?><span><?= t('Log out', 'लॉगआउट') ?> · <?= h(mb_substr($u['name'], 0, 14)) ?></span></a>
    </nav>
  </details>
  <div class="adm-main">
<script>(function(){var d=document.getElementById('admSide');if(d&&window.matchMedia('(min-width:1000px)').matches)d.open=true;})();</script>


