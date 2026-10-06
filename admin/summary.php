<?php
require_once __DIR__ . '/../inc/fn.php';
require_once __DIR__ . '/../inc/earning.php';
$u = need_role('admin');
$page_title = 'हिसाब — Maakit';
$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $date = post('cost_date');
    $fuel = earning_cost($_POST['fuel'] ?? '');
    $staff = earning_cost($_POST['staff'] ?? '');
    $other = earning_cost($_POST['other'] ?? '');
    if (!csrf_ok()) $err = t('Please reload this page and try again.', 'पन्ना दोबारा खोलकर खर्च सेव कीजिए।');
    elseif (!earning_date($date) || $date > date('Y-m-d') || $fuel === null || $staff === null || $other === null)
        $err = t('Choose today or an earlier date and enter whole rupee costs from 0 to 1,000,000.', 'आज या पिछली तारीख चुनिए और खर्च 0 से 10,00,000 तक पूरे रुपये में भरिए।');
    else {
        $pdo->prepare("INSERT INTO operating_costs(cost_date,fuel,staff,other,note,updated_by) VALUES (?,?,?,?,?,?)
            ON DUPLICATE KEY UPDATE fuel=VALUES(fuel),staff=VALUES(staff),other=VALUES(other),note=VALUES(note),updated_by=VALUES(updated_by)")
            ->execute([$date,$fuel,$staff,$other,mb_substr(post('note'),0,200),$u['id']]);
        flash(t('Daily costs saved.', 'दिन का खर्च सेव हो गया।'));
        redirect('/admin/summary.php?edit=' . rawurlencode($date));
    }
}
$today=date('Y-m-d');
$rows=earning_days($pdo,date('Y-m-d',strtotime('-59 days')),$today);
$edit=get('edit',$today);
if (!earning_date($edit) || $edit > $today) $edit=$today;
$st=$pdo->prepare('SELECT * FROM operating_costs WHERE cost_date=?'); $st->execute([$edit]); $cost=$st->fetch() ?: [];
$totalRevenue=array_sum(array_column($rows,'revenue'));
$totalCost=array_sum(array_column($rows,'expense'));
$missing=count(array_filter($rows,fn($r)=>$r['expense'] === null));
$unpriced=array_sum(array_column($rows,'unpriced'));
$top = $pdo->query("SELECT village, COUNT(*) c FROM orders GROUP BY village ORDER BY c DESC LIMIT 15")->fetchAll();
$cust = $pdo->query("SELECT mobile, MAX(customer_name) nm, MAX(village) vl, COUNT(*) c FROM orders GROUP BY mobile ORDER BY c DESC LIMIT 15")->fetchAll();
include __DIR__ . '/../inc/panel.php';
?>
<section><div class="wrap">
  <h2><?= t('Delivery revenue and costs', 'डिलीवरी की आमदनी और खर्च') ?></h2>
  <p class="lead"><?= t('Last 60 days. Goods money belongs to shops. Revenue is the delivery fee and recorded shop commission on completed orders; it is not proof of payment collection.', 'पिछले 60 दिन। सामान का पैसा दुकान का है। पहुँचे हुए orders की delivery fee और दर्ज shop commission यहाँ आमदनी हैं; इससे पैसा वसूल होने की पुष्टि नहीं होती।') ?></p>
  <div class="box">
    <b><?= t('Recorded revenue', 'दर्ज आमदनी') ?> ₹<?= $totalRevenue ?></b> · <?= t('Recorded costs', 'दर्ज खर्च') ?> ₹<?= $totalCost ?><br>
    <?php if ($missing || $unpriced): ?>
      <?= h(t('Balance is incomplete: ', 'बचा हुआ हिसाब अधूरा है: ')) ?><?= $missing ?> <?= t('days without costs', 'दिन का खर्च बाकी') ?> · <?= $unpriced ?> <?= t('completed orders without a delivery fee', 'पहुँचे orders का delivery charge बाकी') ?>
    <?php else: ?>
      <b><?= t('Recorded balance', 'दर्ज हिसाब में बचा') ?> ₹<?= $totalRevenue-$totalCost ?></b>
    <?php endif; ?>
    <p class="help"><?= t('This is not guaranteed profit. Include fuel, staff, rent, marketing, refunds and all other costs. Bookings and taxes are outside this delivery report.', 'यह निश्चित मुनाफा नहीं है। petrol, कर्मचारी, किराया, प्रचार, refund और बाकी सभी खर्च भरिए। booking और tax इस delivery report में शामिल नहीं हैं।') ?></p>
  </div>
  <?php if ($err): ?><div class="err"><?= h($err) ?></div><?php endif; ?>
  <form method="post" class="box" id="costs" style="margin-top:16px">
    <h3><?= t('Enter or correct daily costs', 'दिन का खर्च भरिए या सुधारिए') ?></h3>
    <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
    <div class="grid g2">
      <div class="field"><label><?= t('Date', 'तारीख') ?><input type="date" name="cost_date" max="<?= h($today) ?>" value="<?= h($edit) ?>" required></label></div>
      <?php foreach (['fuel'=>t('Fuel ₹','petrol / ईंधन ₹'),'staff'=>t('Staff cost ₹','कर्मचारी का खर्च ₹'),'other'=>t('All other costs ₹','बाकी सभी खर्च ₹')] as $key=>$label): ?>
        <div class="field"><label><?= h($label) ?><input type="number" name="<?= h($key) ?>" min="0" max="1000000" step="1" value="<?= isset($cost[$key]) ? (int)$cost[$key] : '' ?>" required></label></div>
      <?php endforeach; ?>
    </div>
    <label><?= t('Cost details', 'खर्च का विवरण') ?><input type="text" name="note" maxlength="200" value="<?= h($cost['note'] ?? '') ?>"></label>
    <p class="help"><?= t('Enter each cost once. Allocate monthly salary and rent by day; do not also count them again in other costs. Zero means you checked there was no cost. Saving the same date replaces its previous totals.', 'हर खर्च एक बार भरिए। मासिक salary और किराये का दिन वाला हिस्सा भरिए; उसे दूसरे खर्च में फिर न जोड़ें। 0 का मतलब आपने जाँच लिया कि खर्च नहीं हुआ। उसी तारीख को सेव करने से पुराने total बदलेंगे।') ?></p>
    <button class="btn btn-brand"><?= t('Save daily costs', 'दिन का खर्च सेव कीजिए') ?></button>
  </form>
  <div class="tablewrap" style="margin-top:20px"><table>
    <tr><?php foreach ([t('Date','तारीख'),t('Delivered','पहुँचे'),t('Delivery fee','delivery fee'),t('Shop commission','shop commission'),t('Costs','खर्च'),t('Balance','बचा'),t('Edit','सुधारिए')] as $label): ?><th><?= h($label) ?></th><?php endforeach; ?></tr>
    <?php foreach ($rows as $r): ?>
      <tr><td><?= h(date('d-m-Y',strtotime($r['date']))) ?></td><td><?= $r['delivered'] ?></td><td>₹<?= $r['delivery'] ?><?= $r['unpriced'] ? ' + ' . h(t('fee pending','चार्ज बाकी')) : '' ?></td><td>₹<?= $r['commission'] ?></td>
        <td><?= $r['expense'] === null ? h(t('Enter costs','खर्च भरिए')) : '₹' . $r['expense'] ?></td>
        <td style="color:<?= $r['balance'] !== null && $r['balance'] < 0 ? 'var(--bad)' : 'var(--ink)' ?>"><?= $r['balance'] === null ? '—' : '₹' . $r['balance'] ?></td>
        <td><a href="/admin/summary.php?edit=<?= h($r['date']) ?>#costs"><?= t('Edit','भरिए / सुधारिए') ?></a></td></tr>
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
