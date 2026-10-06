<?php
// panel ka upar wala menu (bpo / delivery / admin)
$u = user();
$page_title = $page_title ?? 'Maakit पैनल';
$no_tabbar = true;                 // panel me neeche ka customer menu nahi
include __DIR__ . '/head.php';
$role = $u['role'];
$menu = [];
if ($role === 'admin') {
    $menu = ['/admin/dash.php' => 'Dashboard', '/muneem.php' => 'मुनीम', '/admin/' => 'ऑर्डर', '/bpo/bookings.php' => 'बुकिंग',
             '/admin/items.php' => 'सामान', '/admin/photos.php' => 'फ़ोटो', '/admin/daam.php' => 'दाम/ब्रांड', '/admin/books.php' => 'किताबें',
             '/admin/transport.php' => 'गाड़ियाँ', '/admin/banners.php' => 'ऑफ़र', '/admin/areas.php' => 'नए गाँव',
             '/admin/summary.php' => 'हिसाब', '/admin/businesses.php' => 'दुकान/कारीगर',
             '/admin/feedback.php' => 'राय', '/admin/rates.php' => 'रेट', '/admin/settings.php' => 'समय/छुट्टी', '/admin/users.php' => 'टीम'];
} elseif ($role === 'designer') {
    // Designer ka kaam sirf dikhne wali cheezein — offer, photo, home page.
    // Order, hisaab, rate, team isko nahi dikhte.
    $menu = ['/admin/banners.php' => 'ऑफ़र / विज्ञापन', '/admin/photos.php' => 'फ़ोटो',
             '/admin/items.php' => 'सामान'];
} elseif ($role === 'bpo') {
    $menu = ['/bpo/' => 'आज के ऑर्डर', '/bpo/new.php' => 'नया ऑर्डर', '/bpo/bookings.php' => 'बुकिंग',
             '/bpo/summary.php' => 'आज का हिसाब'];
} else {
    $menu = ['/delivery/' => 'मेरे ऑर्डर', '/delivery/photo.php' => 'सामान की फ़ोटो'];
}
$cur = strtok($_SERVER['REQUEST_URI'], '?');
?>
<nav class="panelnav">
  <div class="wrap">
    <?php foreach ($menu as $href => $label): ?>
      <a class="<?= $cur === $href ? 'on' : '' ?>" href="<?= h($href) ?>"><?= h($label) ?></a>
    <?php endforeach; ?>
    <a href="/logout.php" style="margin-left:auto">लॉगआउट (<?= h($u['name']) ?>)</a>
  </div>
</nav>
