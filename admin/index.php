<?php
require_once __DIR__ . '/../inc/fn.php';
$u = need_role('admin');
$page_title = 'सारे ऑर्डर — Maakit';
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
<section><div class="wrap">
  <h2>सारे ऑर्डर</h2>
  <form method="get" style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:14px">
    <input type="date" name="day" value="<?= h($day) ?>" style="max-width:190px">
    <input type="text" name="q" value="<?= h($q) ?>" placeholder="नाम, नंबर, गाँव या ऑर्डर नंबर" style="max-width:280px">
    <button class="btn btn-brand btn-sm">खोजिए</button>
    <a class="btn btn-gold btn-sm" href="/admin/export.php?day=<?= h($day) ?>">Excel में उतारिए</a>
  </form>
  <div class="tablewrap"><table>
    <tr><th>ऑर्डर</th><th>ग्राहक</th><th>गाँव</th><th>सामान</th><th>चार्ज</th><th>स्टेटस</th><th>पार्टनर</th><th>कब</th></tr>
    <?php foreach ($rows as $o): ?>
      <tr>
        <td><?= h($o['order_no']) ?><br><a href="/admin/shipment.php?id=<?= (int)$o['id'] ?>"><?= t('Courier details','Courier जानकारी') ?></a><br><span class="meta">कोड <?= h($o['code']) ?></span></td>
        <td><?= h($o['customer_name']) ?><br><span class="meta"><?= h($o['mobile']) ?></span></td>
        <td><?= h($o['village']) ?></td>
        <td style="max-width:280px"><?= h(mb_strimwidth($o['items'], 0, 90, '…')) ?></td>
        <td><?= $o['first_order'] ? 'फ़्री' : '₹' . (int)$o['delivery_charge'] ?></td>
        <td><?= h(status_hi($o['status'])) ?></td>
        <td><?= h($o['dname']) ?></td>
        <td class="meta"><?= h(date('d-m h:i A', strtotime($o['created_at']))) ?></td>
      </tr>
    <?php endforeach; ?>
  </table></div>
</div></section>
<?php include __DIR__ . '/../inc/foot.php'; ?>
