<?php
// ============================================================
//  सारथी — पार्सल कहाँ है (grahak)
//
//  Kaam number (SRxxxx) + bhejne ya paane wale ka mobile.
//  Dono mile tabhi kuch dikhta hai — aur galat koshish ki hadd
//  wahi try_blocked() jo login par hai.
//  Rider ka sirf pehla naam dikhta hai, number nahi (wo khud
//  grahak ko phone karta hai).
//
//  Nayi bookings maakit.in par hoti hain (ride.php / trip.php).
//  Ye panna sirf purane "SR…" number wale links ke liye bacha
//  hai; khaali khulne par maakit.in/trip.php par bhejta hai.
// ============================================================
require_once __DIR__ . '/inc/public.php';

$trackingAccount = sr_firebase_enabled() ? sr_account_require($pdo) : null;

if (!sr_firebase_enabled() && !isset($_GET['no']) && !isset($_POST['no']) && empty($_SESSION['sr_track'])) {
    header('Location: ' . sr_maakit_url('/trip.php'), true, 302);
    exit;
}

$no = strtoupper(trim((string)($_POST['no'] ?? $_GET['no'] ?? ($_SESSION['sr_track']['no'] ?? ''))));
$m  = preg_replace('/\D/', '', (string)($_POST['m'] ?? $_GET['m'] ?? ($_SESSION['sr_track']['m'] ?? '')));
$job = null; $err = '';
if ($trackingAccount) $m = $trackingAccount['mobile'];

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

sr_public_head('none', t('Track your parcel', 'पार्सल ट्रैक कीजिए'), t('LIVE STATUS', 'लाइव हालत'),
    t('Where is', 'आपका पार्सल'), t('my parcel?', 'कहाँ पहुँचा?'),
    t('Enter your booking number to check the latest status.', 'ताज़ा स्थिति देखने के लिए बुकिंग नंबर भरिए।'),
    [['route', t('Booking status', 'बुकिंग की स्थिति')], ['check', t('Code-safe delivery', 'कोड से पक्की डिलीवरी')], ['phone', t('Help one call away', 'मदद बस एक फ़ोन पर')]]);
?>
  <?php if ($err): ?><div class="sx-alert"><?= sr_icon('alert', 18) ?> <?= h($err) ?></div><?php endif; ?>

  <?php if ($job): [$k, $steps] = sr_job_steps($job['status']); $live = in_array($job['status'], ['new', 'assigned', 'picked'], true); ?>
    <div class="sx-card sx-trk">
      <div class="sx-trk-h">
        <div><small><?= h(t('Booking', 'बुकिंग')) ?></small><b><?= h($job['job_no']) ?></b></div>
        <span class="sx-tag <?= $job['status'] === 'delivered' ? 'mint' : ($job['status'] === 'cancelled' ? 'red' : 'sun') ?>"><?= $job['status'] === 'cancelled' ? h(t('Cancelled', 'रद्द')) : h($steps[max(0, $k)]) ?></span>
      </div>
      <?php if ($job['status'] !== 'cancelled'): ?>
        <div class="sx-bar"><i style="width:<?= max(8, min(100, ($k / 3) * 100)) ?>%"></i></div>
        <ol class="sx-tl">
          <?php foreach ($steps as $i => $st): ?>
            <li class="<?= $i < $k ? 'done' : ($i === $k ? 'now' : '') ?>"><i></i><div><b><?= h($st) ?></b>
              <?php if ($i === 1 && $job['rider_name'] && $k >= 1): ?><small><?= h(explode(' ', trim($job['rider_name']))[0]) ?> · <?= h(t('Sarathi rider', 'सारथी राइडर')) ?></small><?php endif; ?>
              <?php if ($i === 2 && $job['picked_at']): ?><small><?= h(date('h:i A', strtotime($job['picked_at']))) ?></small><?php endif; ?>
              <?php if ($i === 3 && $job['delivered_at']): ?><small><?= h(date('d M, h:i A', strtotime($job['delivered_at']))) ?></small><?php endif; ?>
            </div></li>
          <?php endforeach; ?>
        </ol>
      <?php endif; ?>
      <div class="sx-sum">
        <?php if ($job['pick_address']): ?><div><small><?= h(t('From', 'कहाँ से')) ?></small><b><?= h($job['pick_address']) ?></b></div><?php endif; ?>
        <div><small><?= h(t('To', 'कहाँ तक')) ?></small><b><?= h($job['drop_name']) ?> · <?= h($job['drop_address']) ?></b></div>
        <?php if ($job['items']): ?><div><small><?= h(t('Parcel', 'सामान')) ?></small><b><?= h($job['items']) ?></b></div><?php endif; ?>
      </div>
      <?php if ($live && !sr_firebase_enabled()): ?>
        <p class="sx-fine"><span class="sx-live"></span><?= h(t('Updating automatically', 'अपने आप ताज़ा हो रहा है')) ?></p>
        <script>setTimeout(function () { if (!document.querySelector('input:focus')) location.reload(); }, 30000);</script>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <form method="post" class="sx-card sx-form">
    <?php if ($job): ?><div class="sx-pane-h"><span class="sx-pin c"><?= sr_icon('route', 16) ?></span><div><b><?= h(t('Track another', 'दूसरा पार्सल देखिए')) ?></b></div></div><?php endif; ?>
    <label class="sx-field"><span><?= h(t('Booking number', 'बुकिंग नंबर')) ?></span><input name="no" value="<?= h($no) ?>" placeholder="SR…" autocapitalize="characters" required></label>
    <label class="sx-field"><span><?= h(t('Mobile (sender or receiver)', 'मोबाइल (भेजने या पाने वाला)')) ?></span><input name="m" <?= $trackingAccount ? 'readonly' : '' ?> type="tel" inputmode="numeric" value="<?= h($m) ?>" required></label>
    <button class="sx-btn sx-btn-navy" type="submit"><?= sr_icon('route', 18) ?> <?= h(t('Check status', 'हालत देखिए')) ?></button>
  </form>
<?php sr_public_foot();
