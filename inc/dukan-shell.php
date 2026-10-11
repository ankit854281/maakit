<?php
// ============================================================
//  Dukaan panel ka dhaancha (shell) — shop.php isse kholta hai.
//
//  Upar: Back + rasta (breadcrumb), khuli/band switch, logout.
//  Phir: dukaan ka naam + 4 chhote number (aaj ki bikri, naye
//  order, dikh raha saaman, daam baaki) — har ek dabane layak.
//  Phir: tab (icon ke saath, naye order ka badge).
//
//  Band karna: inc/dukan-shell-end.php (toast + auto kaam ka JS).
//  Saare style assets/mk.css me ".sp-" se shuru hote hain.
// ============================================================

function sp_icon($n, $s = 18) {
    $p = [
        'home'   => '<path d="M3 11l9-7 9 7"/><path d="M5 10v10h14V10"/><path d="M10 20v-6h4v6"/>',
        'box'    => '<path d="M21 8l-9-5-9 5 9 5 9-5z"/><path d="M3 8v8l9 5 9-5V8"/><path d="M12 13v8"/>',
        'bag'    => '<path d="M6 7h12l1 14H5L6 7z"/><path d="M9 7a3 3 0 0 1 6 0"/>',
        'chart'  => '<path d="M4 20V10"/><path d="M10 20V4"/><path d="M16 20v-7"/><path d="M22 20H2"/>',
        'wallet' => '<rect x="3" y="6" width="18" height="14" rx="2"/><path d="M16 13h2"/><path d="M3 10h18"/>',
        'chair'  => '<path d="M6 3h12v8H6z"/><path d="M4 11h16v4H4z"/><path d="M6 15v6M18 15v6"/>',
        'gear'   => '<circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3M4.9 4.9l2.1 2.1M17 17l2.1 2.1M4.9 19.1L7 17M17 7l2.1-2.1"/>',
        'back'   => '<path d="M15 18l-6-6 6-6"/>',
        'eye'    => '<path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>',
        'out'    => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5"/><path d="M21 12H9"/>',
        'rupee'  => '<path d="M6 4h12M6 9h12M14 20L7 13h3a4 4 0 0 0 0-8"/>',
        'tag'    => '<path d="M20 12l-8 8-9-9V3h8l9 9z"/><circle cx="7.5" cy="7.5" r="1.5"/>',
        'truck'  => '<path d="M3 6h11v10H3z"/><path d="M14 9h4l3 3v4h-7"/><circle cx="7" cy="18" r="2"/><circle cx="17" cy="18" r="2"/>',
        'phone'  => '<path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2"/>',
        'plus'   => '<path d="M12 5v14M5 12h14"/>',
        'cam'    => '<path d="M4 8h3l2-3h6l2 3h3v11H4z"/><circle cx="12" cy="13" r="3.5"/>',
        'search' => '<circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/>',
        'check'  => '<path d="M5 12l5 5L20 7"/>',
        'bell'   => '<path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10 21a2 2 0 0 0 4 0"/>',
        'wa'     => '<path d="M4 20l1.3-4A8 8 0 1 1 8 18.7L4 20z"/>',
    ][$n] ?? '';
    return '<svg class="sp-ic" width="' . (int)$s . '" height="' . (int)$s . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $p . '</svg>';
}

/** Ek chhota POST form ka shuruaati hissa (csrf + do + tab). */
function sp_form_open($do, array $extra = [], $attrs = '') {
    $h = '<form method="post"' . ($attrs ? ' ' . $attrs : '') . '><input type="hidden" name="csrf" value="' . h(csrf()) . '"><input type="hidden" name="do" value="' . h($do) . '">';
    foreach ($extra as $k => $v) $h .= '<input type="hidden" name="' . h($k) . '" value="' . h($v) . '">';
    return $h;
}

// ---------- panel bhar me kaam aane wale number (sab asli) ----------
$sp_naye = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE business_id=? AND shop_status='naya'");
$sp_naye->execute([$bid]); $sp_naye = (int)$sp_naye->fetchColumn();

$sp_chalte = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE business_id=? AND shop_status IN ('manzoor','taiyaar') AND created_at > NOW() - INTERVAL 7 DAY");
$sp_chalte->execute([$bid]); $sp_chalte = (int)$sp_chalte->fetchColumn();

$sp_aaj = dukan_jod($pdo, $bid, $aaj, $aaj);

$sp_it = $pdo->prepare("SELECT
      COALESCE(SUM(active=1 AND stock='hai' AND price>0),0) AS dikh,
      COALESCE(SUM(active=1 AND price<=0),0)               AS baaki,
      COALESCE(SUM(active=1 AND price>0 AND stock='khatam'),0) AS khatam,
      COALESCE(SUM(active=1 AND price>0 AND (photo IS NULL OR photo='') AND NOT EXISTS (SELECT 1 FROM catalog_photos cp WHERE cp.cat_id=shop_items.cat_id)),0) AS bina_photo
    FROM shop_items WHERE business_id=?");
$sp_it->execute([$bid]); $sp_it = array_map('intval', $sp_it->fetch());

$sp_khuli = dukan_khuli($b);
$sp_o = substr((string)$b['open_time'], 0, 5); $sp_c = substr((string)$b['close_time'], 0, 5);
$sp_samay = $sp_o === $sp_c ? t('Open 24 hours', '24 घंटे') : $sp_o . '–' . $sp_c;
$sp_flash = flash();

$sp_tab_icon = ['live' => 'chair', 'setup' => 'gear', 'kaam' => 'home', 'saaman' => 'box', 'order' => 'bag', 'hisab' => 'chart', 'khata' => 'wallet'];
$sp_first = array_key_first($tabs);
$sp_view  = $nai ? '/salon.php?id=' . $bid : '/business.php?id=' . $bid;
$sp_naam  = trim((string)$b['name']);
?>
<div class="sp" data-bid="<?= (int)$bid ?>" data-tab="<?= h($tab) ?>" data-naye="<?= $sp_naye ?>">

  <!-- 1. upar ki patti: Back · rasta · khuli/band · logout -->
  <div class="sp-top"><div class="sp-wrap">
    <a class="sp-back" href="<?= $tab === $sp_first ? '/' : '/shop.php' ?>" data-back><?= sp_icon('back', 16) ?><span><?= t('Back', 'वापस') ?></span></a>
    <nav class="sp-crumb" aria-label="<?= h(t('You are here', 'आप यहाँ हैं')) ?>">
      <a href="/shop.php"><?= t('Shop panel', 'दुकान पैनल') ?></a>
      <span aria-hidden="true">›</span>
      <b><?= h($tabs[$tab]) ?></b>
    </nav>
    <div class="sp-top-r">
      <?= sp_form_open('khuli', ['open' => (int)$b['shop_open'] ? '0' : '1', 'tab' => $tab], 'class="sp-open-f" data-sp-open') ?>
        <button class="sp-switch<?= (int)$b['shop_open'] ? ' on' : '' ?>" role="switch" aria-checked="<?= (int)$b['shop_open'] ? 'true' : 'false' ?>"
                data-on="<?= h(t('Open', 'खुली')) ?>" data-off="<?= h(t('Closed', 'बंद')) ?>">
          <i aria-hidden="true"></i><span><?= (int)$b['shop_open'] ? t('Open', 'खुली') : t('Closed', 'बंद') ?></span>
        </button>
      </form>
      <a class="sp-ghost sp-hide-s" href="<?= h($sp_view) ?>" target="_blank" rel="noopener"><?= sp_icon('eye', 15) ?><span><?= t('Customer view', 'ग्राहक का दृश्य') ?></span></a>
      <?= sp_form_open('logout') ?><button class="sp-ghost" title="<?= h(t('Log out', 'लॉगआउट')) ?>"><?= sp_icon('out', 15) ?><span class="sp-hide-s"><?= t('Log out', 'लॉगआउट') ?></span></button></form>
    </div>
  </div></div>

  <!-- 2. dukaan + aaj ke number -->
  <div class="sp-hero"><div class="sp-wrap">
    <div class="sp-id">
      <span class="sp-av" aria-hidden="true"><?= h(mb_strtoupper(mb_substr($sp_naam, 0, 1))) ?></span>
      <div class="sp-id-t">
        <b><?= h($sp_naam) ?></b>
        <small>
          <span class="sp-dot<?= $sp_khuli ? ' on' : '' ?>"></span><?= $sp_khuli ? t('Visible to customers', 'ग्राहक को दिख रही है') : t('Hidden from customers', 'ग्राहक को नहीं दिख रही') ?>
          · <?= h($b['village']) ?> · <?= h($sp_samay) ?>
        </small>
      </div>
    </div>
    <div class="sp-kpis">
      <a class="sp-kpi" href="/shop.php?tab=hisab"><small><?= t("Today's sales", 'आज की बिक्री') ?></small><b>₹<?= number_format((int)$sp_aaj['bikri']) ?></b><em><?= (int)$sp_aaj['ginti'] ?> <?= t('sales', 'बार') ?></em></a>
      <a class="sp-kpi<?= $sp_naye ? ' hot' : '' ?>" href="/shop.php?tab=order"><small><?= t('New orders', 'नए ऑर्डर') ?></small><b data-sp-naye><?= $sp_naye ?></b><em><?= $sp_chalte ?> <?= t('in progress', 'चल रहे') ?></em></a>
      <a class="sp-kpi" href="/shop.php?tab=saaman"><small><?= t('Live products', 'दिख रहा सामान') ?></small><b><?= number_format($sp_it['dikh']) ?></b><em><?= $sp_it['khatam'] ?> <?= t('out of stock', 'ख़त्म') ?></em></a>
      <a class="sp-kpi<?= $sp_it['baaki'] ? ' warn' : '' ?>" href="/shop.php?tab=saaman#fill"><small><?= t('Prices to fill', 'दाम भरना बाकी') ?></small><b><?= number_format($sp_it['baaki']) ?></b><em><?= $sp_it['baaki'] ? t('Customers cannot see these', 'ये ग्राहक को नहीं दिखते') : t('All done', 'सब भरा है') ?></em></a>
    </div>
  </div></div>

  <!-- 3. tab -->
  <nav class="sp-tabs" aria-label="<?= h(t('Shop panel sections', 'दुकान पैनल के हिस्से')) ?>"><div class="sp-wrap">
    <?php foreach ($tabs as $k => $lbl): ?>
      <a class="<?= $tab === $k ? 'on' : '' ?>" href="/shop.php?tab=<?= h($k) ?>"<?= $tab === $k ? ' aria-current="page"' : '' ?>>
        <?= sp_icon($sp_tab_icon[$k] ?? 'home', 16) ?><span><?= h($lbl) ?></span>
        <?php if ($k === 'order'): ?><i class="sp-badge" data-sp-badge<?= $sp_naye ? '' : ' hidden' ?>><?= $sp_naye ?></i><?php endif; ?>
      </a>
    <?php endforeach; ?>
  </div></nav>

  <main class="sp-main"><div class="sp-wrap">
