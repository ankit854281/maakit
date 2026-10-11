<?php
require_once __DIR__ . '/../inc/fn.php';
$u = need_role('admin');
$page_title = t('Orders', 'सारे ऑर्डर') . ' — Maakit';
$day = get('day', '');
$q = get('q', '');
$sql = "SELECT o.*, u.name dname FROM orders o LEFT JOIN users u ON u.id=o.delivery_user WHERE 1";
$args = [];
if ($day) { $sql .= " AND DATE(o.created_at)=?"; $args[] = $day; }
if ($q) { $sql .= " AND (o.order_no LIKE ? OR o.mobile LIKE ? OR o.customer_name LIKE ? OR o.village LIKE ?)";
          array_push($args, "%$q%", "%$q%", "%$q%", "%$q%"); }
$sql .= " ORDER BY o.id DESC LIMIT 300";
$st = $pdo->prepare($sql); $st->execute($args); $rows = $st->fetchAll();
include __DIR__ . '/../inc/panel.php';
?>
<section class="dz">
  <div class="dz-head">
    <div><h1><?= t('Orders', 'सारे ऑर्डर') ?></h1><p><?= count($rows) ?> <?= t('orders', 'ऑर्डर') ?><?= $day ? ' · ' . h(date('d M Y', strtotime($day))) : ' · ' . t('latest 300', 'आख़िरी 300') ?></p></div>
    <div class="dz-actions" style="margin:0">
      <?php if ($u['role'] === 'admin'): /* update.php admin login se bhi khulta hai */ ?><a class="mk-btn mk-btn-sm" href="/update.php"><?= t('Update website', 'वेबसाइट अपडेट') ?></a><?php endif; ?>
      <a class="dz-chip" href="/admin/company-delivery.php"><?= t('Company deliveries', 'कंपनी डिलीवरी') ?></a>
    </div>
  </div>
  <form method="get" class="dz-filter">
    <input type="date" name="day" value="<?= h($day) ?>" aria-label="<?= h(t('Date', 'तारीख़')) ?>">
    <input type="search" name="q" value="<?= h($q) ?>" placeholder="<?= h(t('Name, mobile, village or order no.', 'नाम, नंबर, गाँव या ऑर्डर नंबर')) ?>">
    <button class="mk-btn mk-btn-sm"><?= t('Search', 'खोजिए') ?></button>
    <a class="dz-chip" href="/admin/export.php?day=<?= h($day) ?>"><?= t('Download Excel', 'Excel में उतारिए') ?></a>
  </form>
  <div class="dz-card dz-scroll">
  <?php if ($rows): ?>
  <table class="dz-table">
    <tr><th><?= t('Order', 'ऑर्डर') ?></th><th><?= t('Customer', 'ग्राहक') ?></th><th><?= t('Village', 'गाँव') ?></th><th><?= t('Items', 'सामान') ?></th><th><?= t('Charge', 'चार्ज') ?></th><th><?= t('Status', 'स्थिति') ?></th><th><?= t('Partner', 'पार्टनर') ?></th><th><?= t('Time', 'कब') ?></th></tr>
    <?php foreach ($rows as $o): ?>
      <tr>
        <td><b><?= h($o['order_no']) ?></b><small><?= t('Code', 'कोड') ?> <?= h($o['code']) ?> · <a href="/admin/shipment.php?id=<?= (int)$o['id'] ?>"><?= t('Courier', 'Courier') ?></a></small></td>
        <td><?= h($o['customer_name']) ?><small><?= h($o['mobile']) ?></small></td>
        <td><?= h($o['village']) ?></td>
        <td style="max-width:280px"><?= h(mb_strimwidth($o['items'], 0, 90, '…')) ?></td>
        <td><?= $o['first_order'] ? t('Free', 'फ़्री') : '₹' . (int)$o['delivery_charge'] ?></td>
        <td><span class="dz-pill p-<?= h(strtolower(str_replace(' ', '', $o['status']))) ?>"><?= h(status_hi($o['status'])) ?></span></td>
        <td><?= h($o['dname'] ?: '—') ?></td>
        <td><small><?= h(date('d M, h:i A', strtotime($o['created_at']))) ?></small></td>
      </tr>
    <?php endforeach; ?>
  </table>
  <?php else: ?><p class="dz-empty"><?= t('No orders found.', 'कोई ऑर्डर नहीं मिला।') ?></p><?php endif; ?>
  </div>
</section>
<?php include __DIR__ . '/../inc/foot.php'; ?>
