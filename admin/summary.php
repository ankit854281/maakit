<?php
require_once __DIR__ . '/../inc/fn.php';
need_role('admin');
$page_title = 'हिसाब — Maakit';
$rows = $pdo->query("SELECT DATE(created_at) d, COUNT(*) total,
   SUM(status IN ('Delivered','Paisa jama')) delivered,
   SUM(status='Cancel') cancelled,
   SUM(CASE WHEN status IN ('Delivered','Paisa jama') THEN COALESCE(delivery_charge,0) ELSE 0 END) kamai
   FROM orders GROUP BY DATE(created_at) ORDER BY d DESC LIMIT 60")->fetchAll();
$top = $pdo->query("SELECT village, COUNT(*) c FROM orders GROUP BY village ORDER BY c DESC LIMIT 15")->fetchAll();
$cust = $pdo->query("SELECT mobile, MAX(customer_name) nm, MAX(village) vl, COUNT(*) c FROM orders GROUP BY mobile ORDER BY c DESC LIMIT 15")->fetchAll();
include __DIR__ . '/../inc/panel.php';
?>
<section><div class="wrap">
  <h2>रोज़ का हिसाब</h2>
  <div class="tablewrap"><table>
    <tr><th>तारीख़</th><th>ऑर्डर</th><th>पहुँचाए</th><th>कैंसिल</th><th>डिलीवरी से कमाई</th></tr>
    <?php foreach ($rows as $r): ?>
      <tr><td><?= h(date('d-m-Y', strtotime($r['d']))) ?></td><td><?= (int)$r['total'] ?></td><td><?= (int)$r['delivered'] ?></td><td><?= (int)$r['cancelled'] ?></td><td>₹<?= (int)$r['kamai'] ?></td></tr>
    <?php endforeach; ?>
  </table></div>
  <div class="grid g2" style="margin-top:20px">
    <div class="box"><h3 style="margin-top:0">सबसे ज़्यादा ऑर्डर वाले गाँव</h3>
      <div class="tablewrap"><table><tr><th>गाँव</th><th>ऑर्डर</th></tr>
        <?php foreach ($top as $t): ?><tr><td><?= h($t['village']) ?></td><td><?= (int)$t['c'] ?></td></tr><?php endforeach; ?></table></div>
    </div>
    <div class="box"><h3 style="margin-top:0">सबसे ज़्यादा ऑर्डर करने वाले ग्राहक</h3>
      <div class="tablewrap"><table><tr><th>नाम</th><th>गाँव</th><th>ऑर्डर</th></tr>
        <?php foreach ($cust as $c): ?><tr><td><?= h($c['nm']) ?><br><span class="meta"><?= h($c['mobile']) ?></span></td><td><?= h($c['vl']) ?></td><td><?= (int)$c['c'] ?></td></tr><?php endforeach; ?></table></div>
    </div>
  </div>
</div></section>
<?php include __DIR__ . '/../inc/foot.php'; ?>
