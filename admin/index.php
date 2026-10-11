<?php
require_once __DIR__ . '/../inc/fn.php';
$u = need_role('admin');
$page_title = t('Orders', 'सारे ऑर्डर') . ' — Maakit';
require_once __DIR__ . '/../inc/sarathi-bridge.php';
// Smart Sarathi: ek order bhejiye (button) + "apne aap" wale + halat taaza
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_ok() && post('do') === 'sarathi_send') {
    [$ok, $m] = sb_send($pdo, (int)post('id'));
    flash(($ok ? '✓ ' : '✗ ') . $m);
    redirect('/admin/index.php' . (get('day') || get('q') ? '?' . http_build_query(['day' => get('day'), 'q' => get('q')]) : ''));
}
$sb_on = false;
try { $pdo->query("SELECT sarathi_job FROM orders LIMIT 1"); $sb_on = sb_ready($pdo); } catch (Throwable $e) {}
if ($sb_on) { sb_auto($pdo); }
$day = get('day', '');
$q = get('q', '');
$sql = "SELECT o.*, u.name dname FROM orders o LEFT JOIN users u ON u.id=o.delivery_user WHERE 1";
$args = [];
if ($day) { $sql .= " AND DATE(o.created_at)=?"; $args[] = $day; }
if ($q) { $sql .= " AND (o.order_no LIKE ? OR o.mobile LIKE ? OR o.customer_name LIKE ? OR o.village LIKE ?)";
          array_push($args, "%$q%", "%$q%", "%$q%", "%$q%"); }
$sql .= " ORDER BY o.id DESC LIMIT 300";
$st = $pdo->prepare($sql); $st->execute($args); $rows = $st->fetchAll();
if ($sb_on) { $n = 0; foreach ($rows as &$r) { if (!empty($r['sarathi_job']) && $n < 15 && !in_array($r['status'], ['Delivered','Cancel','Paisa jama'], true)) { $r = sb_sync($pdo, $r); $n++; } } unset($r); }
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
        <td><?php if (!empty($o['sarathi_job'])): ?>
              <b>Sarathi</b><small><?= h($o['sarathi_job']) ?> · <?= h($o['sarathi_status'] ?: 'new') ?></small>
            <?php elseif ($sb_on && in_array($o['status'], ['Naya', 'Confirm'], true)): ?>
              <form method="post" style="margin:0"><input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="do" value="sarathi_send"><input type="hidden" name="id" value="<?= (int)$o['id'] ?>">
                <button class="mk-btn mk-btn-sm" title="<?= h(t('Send to Smart Sarathi riders', 'Smart Sarathi राइडर को भेजिए')) ?>"><?= t('Send to Sarathi', 'सारथी को भेजिए') ?></button></form>
            <?php else: ?><?= h($o['dname'] ?: '—') ?><?php endif; ?></td>
        <td><small><?= h(date('d M, h:i A', strtotime($o['created_at']))) ?></small></td>
      </tr>
    <?php endforeach; ?>
  </table>
  <?php else: ?><p class="dz-empty"><?= t('No orders found.', 'कोई ऑर्डर नहीं मिला।') ?></p><?php endif; ?>
  </div>
</section>
<?php include __DIR__ . '/../inc/foot.php'; ?>
