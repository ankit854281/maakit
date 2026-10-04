<?php
require_once __DIR__ . '/../inc/fn.php';
$u = need_role(['bpo', 'admin']);
$page_title = 'आज का हिसाब — Maakit';
$day = get('day', date('Y-m-d'));
$st = $pdo->prepare("SELECT
    COUNT(*) total,
    SUM(status IN ('Delivered','Paisa jama')) delivered,
    SUM(status='Cancel') cancelled,
    SUM(CASE WHEN status IN ('Delivered','Paisa jama') THEN COALESCE(delivery_charge,0) ELSE 0 END) kamai,
    SUM(CASE WHEN status IN ('Delivered','Paisa jama') AND payment LIKE '%कैश%' THEN COALESCE(goods_amount,0)+COALESCE(delivery_charge,0) ELSE 0 END) cash
  FROM orders WHERE DATE(created_at)=?");
$st->execute([$day]); $s = $st->fetch();
$vs = $pdo->prepare("SELECT village, COUNT(*) c FROM orders WHERE DATE(created_at)=? GROUP BY village ORDER BY c DESC");
$vs->execute([$day]); $vrows = $vs->fetchAll();
include __DIR__ . '/../inc/panel.php';
?>
<section><div class="wrap">
  <h2>हिसाब — <?= h(date('d-m-Y', strtotime($day))) ?></h2>
  <form method="get" style="margin-bottom:16px;display:flex;gap:8px"><input type="date" name="day" value="<?= h($day) ?>" style="max-width:200px"><button class="btn btn-brand btn-sm">देखिए</button></form>
  <div class="grid g4">
    <div class="stat"><div class="n"><?= (int)$s['total'] ?></div><div class="l">कुल ऑर्डर</div></div>
    <div class="stat"><div class="n"><?= (int)$s['delivered'] ?></div><div class="l">पहुँचाए गए</div></div>
    <div class="stat"><div class="n"><?= (int)$s['cancelled'] ?></div><div class="l">कैंसिल</div></div>
    <div class="stat"><div class="n">₹<?= (int)$s['kamai'] ?></div><div class="l">डिलीवरी से कमाई</div></div>
  </div>
  <div class="box" style="margin-top:16px">
    <h3 style="margin-top:0">कैश जो जमा होना है: ₹<?= (int)$s['cash'] ?></h3>
    <p class="help">यह उन ऑर्डर का जोड़ है जिनमें डिलीवरी पर कैश लिया गया (सामान + डिलीवरी चार्ज)।</p>
  </div>
  <?php if ($vrows): ?>
  <div class="box" style="margin-top:16px">
    <h3 style="margin-top:0">गाँव के हिसाब से</h3>
    <div class="tablewrap"><table><tr><th>गाँव</th><th>ऑर्डर</th></tr>
      <?php foreach ($vrows as $v): ?><tr><td><?= h($v['village']) ?></td><td><?= (int)$v['c'] ?></td></tr><?php endforeach; ?>
    </table></div>
  </div>
  <?php endif; ?>
</div></section>
<?php include __DIR__ . '/../inc/foot.php'; ?>
