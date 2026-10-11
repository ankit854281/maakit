<?php
// ============================================================
//  सारथी — पार्सल कहाँ है (grahak)
//
//  Kaam number (SRxxxx) + bhejne ya paane wale ka mobile.
//  Dono mile tabhi kuch dikhta hai — aur galat koshish ki hadd
//  wahi try_blocked() jo login par hai.
//  Rider ka sirf pehla naam dikhta hai, number nahi (wo khud
//  grahak ko phone karta hai).
// ============================================================
require_once __DIR__ . '/inc/public.php';

$no = strtoupper(trim((string)($_POST['no'] ?? $_GET['no'] ?? ($_SESSION['sr_track']['no'] ?? ''))));
$m  = preg_replace('/\D/', '', (string)($_POST['m'] ?? $_GET['m'] ?? ($_SESSION['sr_track']['m'] ?? '')));
$job = null; $err = '';

if ($no !== '' && $m !== '') {
    $who = 'track:' . $no;
    if (try_blocked($pdo, $who)) {
        $err = try_error();
    } else {
        $st = $pdo->prepare("SELECT j.*, r.name AS rider_name FROM jobs j LEFT JOIN riders r ON r.id=j.rider_id
                              WHERE j.job_no=? AND (j.pick_mobile=? OR j.drop_mobile=?) LIMIT 1");
        $st->execute([$no, $m, $m]);
        $job = $st->fetch();
        if ($job) { try_ok($pdo, $who); $_SESSION['sr_track'] = ['no' => $no, 'm' => $m]; }
        else $err = t('No parcel found with this number and mobile.', 'इस नंबर और मोबाइल से कोई पार्सल नहीं मिला।');
    }
}

sr_public_head('track', t('Track your parcel', 'पार्सल कहाँ है'), t('LIVE STATUS', 'अभी की हालत'),
    t('Where is', 'आपका पार्सल'), t('my parcel?', 'कहाँ पहुँचा?'),
    t('Enter the booking number and your mobile. The page refreshes by itself while the parcel is on the way.', 'बुकिंग नंबर और अपना मोबाइल डालिए। पार्सल रास्ते में हो तो पेज अपने आप ताज़ा होता है।'));
?>
  <?php if ($err): ?><div class="sr-err"><?= h($err) ?></div><?php endif; ?>

  <?php if ($job): [$k, $steps] = sr_job_steps($job['status']); ?>
    <div class="sr-card sp-track">
      <div class="sp-track-h">
        <div><small><?= h(t('Booking', 'बुकिंग')) ?></small><b><?= h($job['job_no']) ?></b></div>
        <span class="sp-st st-<?= h($job['status']) ?>"><?= $job['status'] === 'cancelled' ? h(t('Cancelled', 'रद्द')) : h($steps[max(0, $k)]) ?></span>
      </div>
      <?php if ($job['status'] !== 'cancelled'): ?>
        <ol class="sp-steps">
          <?php foreach ($steps as $i => $s): ?><li class="<?= $i < $k ? 'done' : ($i === $k ? 'now' : '') ?>"><i></i><span><?= h($s) ?></span></li><?php endforeach; ?>
        </ol>
      <?php endif; ?>
      <dl class="sp-dl">
        <?php if ($job['pick_address']): ?><dt><?= h(t('From', 'कहाँ से')) ?></dt><dd><?= h($job['pick_address']) ?></dd><?php endif; ?>
        <dt><?= h(t('To', 'कहाँ तक')) ?></dt><dd><?= h($job['drop_name']) ?> · <?= h($job['drop_address']) ?></dd>
        <?php if ($job['items']): ?><dt><?= h(t('Parcel', 'सामान')) ?></dt><dd><?= h($job['items']) ?></dd><?php endif; ?>
        <?php if ($job['rider_name']): ?><dt><?= h(t('Rider', 'राइडर')) ?></dt><dd><?= h(explode(' ', trim($job['rider_name']))[0]) ?></dd><?php endif; ?>
        <?php if ($job['delivered_at']): ?><dt><?= h(t('Delivered at', 'पहुँचा')) ?></dt><dd><?= h(date('d M, h:i A', strtotime($job['delivered_at']))) ?></dd><?php endif; ?>
      </dl>
      <?php if (in_array($job['status'], ['new', 'assigned', 'picked'], true)): ?>
        <p class="sp-small"><span class="sp-live"></span><?= h(t('Updates by itself every 30 seconds.', 'हर 30 सेकंड में अपने आप ताज़ा।')) ?></p>
        <script>setTimeout(function () { location.reload(); }, 30000);</script>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <form method="post" class="sr-card">
    <label class="sr-lab" for="no"><?= h(t('Booking number', 'बुकिंग नंबर')) ?></label>
    <input class="sr-in" id="no" name="no" value="<?= h($no) ?>" placeholder="SR…" autocapitalize="characters" required>
    <label class="sr-lab" for="m"><?= h(t('Your mobile (sender or receiver)', 'आपका मोबाइल (भेजने या पाने वाला)')) ?></label>
    <input class="sr-in" id="m" name="m" type="tel" inputmode="numeric" value="<?= h($m) ?>" required>
    <button class="sr-btn sr-btn-go" type="submit"><?= h(t('Check status', 'हालत देखिए')) ?></button>
  </form>
<?php sr_public_foot();
