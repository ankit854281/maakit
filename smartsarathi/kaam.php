<?php
// ============================================================
//  सारथी — काम
//
//  Ek hi kaam khula rehta hai (jo abhi karna hai), baaki patli
//  patti me. Dhoop me, gaadi par, ek haath se chalta hai.
//
//  Saare POST yahin aate hain — baaki panne sirf dikhate hain.
// ============================================================
require_once __DIR__ . '/inc/fn.php';
require_once __DIR__ . '/inc/kaam.php';
require_once __DIR__ . '/inc/ui.php';

$me = rider_me($pdo);
if (!$me) { redirect('/'); }
$rid = (int)$me['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_ok()) {
    $do = post('do');

    if ($do === 'bahar')    { rider_logout(); redirect('/'); }
    if ($do === 'duty_on')  { duty_on($pdo, $rid);  flash(t('You are online for the next 90 minutes.', 'हाज़िरी लग गई — अगले 90 मिनट तक।')); redirect('/kaam.php'); }
    if ($do === 'duty_off') { duty_off($pdo, $rid); flash(t('You are now offline.', 'अब आप बंद हैं।'));                   redirect('/kaam.php'); }

    if ($do === 'trip_band') {
        flash(trip_close($pdo, $rid)
            ? t('Trip closed.', 'चक्कर बंद हो गया।')
            : t('This trip still has pending jobs. Complete them first.', 'इस चक्कर में कोई काम बाकी है — पहले उसे निपटाइए।'));
        redirect('/kaam.php');
    }
    if ($do === 'uthaya') {
        [$ok, $msg] = job_pick($pdo, $rid, (int)post('jid'), post('otp'));
        flash(sr_message($msg)); redirect('/kaam.php');
    }
    if ($do === 'de_diya') {
        $photo = save_photo('pod');                       // chhoti karke rakhi jaati hai
        [$ok, $msg] = job_deliver($pdo, $rid, (int)post('jid'), post('otp'), $photo);
        if (!$ok && $photo) drop_photo($photo);
        flash(sr_message($msg)); redirect('/kaam.php');
    }
    if ($do === 'samasya') {
        [$ok, $msg] = problem_add($pdo, $rid, (int)post('jid'), post('kism'), post('note'));
        flash(sr_message($msg)); redirect('/kaam.php');
    }
}

$jobs  = rider_jobs($pdo, $rid);
$ready = duty_until($pdo, $rid);
$aaj   = rider_done_today($pdo, $rid);
$trip  = trip_open($pdo, $rid);
$msg   = flash();
$kisme = problem_kinds();
$rate  = (int)$me['rate'];
$extra = (int)$me['extra_rate'];

// "Is chakkar me kitne" — matlab is baar nikalne par KUL kitne,
// nipte hue bhi. Pehle sirf baaki kaam gine jaate the, to teen
// pahuncha chuke rider ko bhi "0" dikhta tha.
$chakkar = 0;
if ($trip) {
    $st = $pdo->prepare("SELECT COUNT(*) FROM jobs WHERE trip_id=?");
    $st->execute([(int)$trip['id']]);
    $chakkar = (int)$st->fetchColumn();
}

$k = rider_khata($pdo, $rid);
sr_head($me, 'kaam', t('Jobs', 'काम'), $ready, ['kaam' => count($jobs)]);
$csrf = h(csrf());
?>

<?php if ($msg): ?><div class="sr-flash"><?= h(sr_message($msg)) ?></div><?php endif; ?>

<!-- हाज़िरी — सबसे बड़ा बटन -->
<?php if ($ready): ?>
  <section class="sx-duty on">
    <div class="sx-duty-l">
      <span class="sx-pulse" aria-hidden="true"></span>
      <div><b><?= h(t('You are online', 'आप हाज़िर हैं')) ?></b>
        <small><?= h(t('New jobs come to you until', 'नए काम मिलते रहेंगे —')) ?> <?= h(date('g:i A', strtotime($ready))) ?><?= h(t('', ' तक')) ?></small></div>
    </div>
    <div class="sx-duty-r">
      <form method="post"><input type="hidden" name="csrf" value="<?= $csrf ?>"><input type="hidden" name="do" value="duty_on"><button type="submit" class="sx-pill"><?= h(t('+90 min', '+90 मि')) ?></button></form>
      <form method="post"><input type="hidden" name="csrf" value="<?= $csrf ?>"><input type="hidden" name="do" value="duty_off"><button type="submit" class="sx-pill ghost"><?= h(t('Go offline', 'बंद कीजिए')) ?></button></form>
    </div>
  </section>
<?php else: ?>
  <form method="post" class="sx-duty off">
    <input type="hidden" name="csrf" value="<?= $csrf ?>"><input type="hidden" name="do" value="duty_on">
    <div class="sx-duty-l"><span class="sx-off-ic"><?= sr_icon('bolt', 22) ?></span>
      <div><b><?= h(t('You are offline', 'आप अभी बंद हैं')) ?></b><small><?= h(t('Go online to start getting jobs', 'हाज़िरी लगाइए — काम आने लगेंगे')) ?></small></div></div>
    <button type="submit" class="sx-go"><span><?= h(t('Go online', 'हाज़िरी लगाइए')) ?></span><?= sr_icon('arrow', 20) ?></button>
  </form>
<?php endif; ?>

<!-- आज का हिसाब -->
<div class="sx-stats">
  <a class="sx-stat" href="<?= h(u('/khata.php')) ?>"><small><?= h(t("Today's earnings", 'आज की कमाई')) ?></small><b>₹<?= (int)$k['aaj'] ?></b></a>
  <div class="sx-stat"><small><?= h(t('Delivered today', 'आज पहुँचाए')) ?></small><b><?= (int)$aaj ?></b></div>
  <div class="sx-stat"><small><?= h(t('Active jobs', 'अभी के काम')) ?></small><b><?= count($jobs) ?></b></div>
</div>

<?php if ($trip): ?>
  <div class="sx-trip">
    <span><?= sr_icon('route', 18) ?> <?= h(t('This trip', 'इस चक्कर में')) ?> <b><?= $chakkar ?></b> <?= h(t('jobs', 'काम')) ?></span>
    <form method="post"><input type="hidden" name="csrf" value="<?= $csrf ?>"><input type="hidden" name="do" value="trip_band"><button type="submit" class="sx-pill ghost sm"><?= h(t('Close trip', 'चक्कर बंद')) ?></button></form>
  </div>
<?php endif; ?>

<!-- काम -->
<?php if (!$jobs): ?>
  <div class="sx-empty">
    <div class="sx-empty-art" aria-hidden="true"><?= sr_icon('bike', 46) ?></div>
    <b><?= $ready ? h(t('Waiting for the next job…', 'अगले काम का इंतज़ार…')) : h(t('No jobs right now', 'अभी कोई काम नहीं')) ?></b>
    <p><?= $ready ? h(t('Stay nearby. This page checks every minute and rings when a job arrives.', 'आस-पास रहिए। यह पेज हर मिनट देखता है और काम आते ही घंटी बजेगी।')) : h(t('Go online above to receive jobs.', 'ऊपर हाज़िरी लगाइए, तभी काम आएगा।')) ?></p>
  </div>
<?php else:
  $pehla = true;
  foreach ($jobs as $j):
    $utha  = $j['status'] === 'picked';
    $khula = $pehla; $pehla = false;
    $mera  = ($j['trip_id'] && (int)$j['trip_seq'] > 1) ? $extra : $rate;
    $map   = maps_link($j);
    $hello = rawurlencode(t('Hello, I am your Sarathi delivery partner. I am on the way with your order ', 'नमस्ते, मैं सारथी डिलीवरी पार्टनर हूँ। आपका सामान लेकर आ रहा हूँ — ') . $j['job_no']);
?>
  <details class="sx-job<?= $utha ? ' is-picked' : '' ?>" <?= $khula ? 'open' : '' ?>>
    <summary>
      <span class="sx-job-st"><?= $utha ? sr_icon('flag', 18) : sr_icon('box', 18) ?></span>
      <span class="sx-job-t"><b><?= h($j['drop_name']) ?></b><small><?= h($j['drop_village'] ?: $j['drop_address']) ?> · <?= h($j['client_name']) ?></small></span>
      <span class="sx-tag <?= $utha ? 'mint' : 'sun' ?>"><?= $utha ? h(t('Deliver', 'पहुँचाना है')) : h(t('Pick up', 'उठाना है')) ?></span>
    </summary>

    <div class="sx-job-b">
      <ol class="sx-route">
        <li class="<?= $utha ? 'done' : 'now' ?>"><i></i><div><small><?= h(t('Pick up', 'उठाइए')) ?></small>
          <b><?= h($j['pick_name'] ?: $j['client_name']) ?></b><?php if ($j['pick_address']): ?><span><?= h($j['pick_address']) ?></span><?php endif; ?></div></li>
        <li class="<?= $utha ? 'now' : '' ?>"><i></i><div><small><?= h(t('Deliver to', 'पहुँचाइए')) ?></small>
          <b><?= h($j['drop_name']) ?></b><span><?= h($j['drop_address']) ?></span></div></li>
      </ol>

      <?php if ($j['items']): ?><div class="sx-items"><?= sr_icon('box', 16) ?> <?= h($j['items']) ?></div><?php endif; ?>
      <?php if (!empty($j['note'])):
        // note me maps ka link ho to use "नक्शा खोलिए" button bana do
        $nt = h($j['note']);
        $nt = preg_replace('#https://maps\.google\.com/\?q=(-?[0-9.]+),(-?[0-9.]+)#', '<a class="sx-maplink" href="https://maps.google.com/?q=$1,$2" target="_blank" rel="noopener">' . h(t('Open map', 'नक्शा खोलिए')) . ' ↗</a>', $nt); ?>
        <div class="sx-items note"><?= sr_icon('alert', 16) ?> <span><?= $nt ?></span></div><?php endif; ?>

      <div class="sx-money">
        <?php if ($j['cod_amount'] !== null): ?><div class="cash"><small><?= h(t('Collect cash', 'नगद लेना है')) ?></small><b>₹<?= (int)$j['cod_amount'] ?></b></div><?php endif; ?>
        <?php if ($rate > 0): ?><div><small><?= h(t('You earn', 'आपकी कमाई')) ?></small><b>₹<?= (int)$mera ?></b></div><?php endif; ?>
        <div><small><?= h(t('Job no.', 'काम नं.')) ?></small><b class="sm"><?= h($j['job_no']) ?></b></div>
      </div>

      <div class="sx-acts">
        <?php if ($map): ?><a class="sx-act" href="<?= h($map) ?>" target="_blank" rel="noopener"><?= sr_icon('nav', 20) ?><span><?= h(t('Navigate', 'रास्ता')) ?></span></a><?php endif; ?>
        <a class="sx-act" href="tel:<?= h($j['drop_mobile']) ?>"><?= sr_icon('phone', 20) ?><span><?= h(t('Customer', 'ग्राहक')) ?></span></a>
        <?php if ($j['pick_mobile']): ?><a class="sx-act" href="tel:<?= h($j['pick_mobile']) ?>"><?= sr_icon('phone', 20) ?><span><?= h(t('Pickup', 'दुकान')) ?></span></a><?php endif; ?>
        <a class="sx-act" href="https://wa.me/91<?= h(preg_replace('/\D/', '', (string)$j['drop_mobile'])) ?>?text=<?= h($hello) ?>" target="_blank" rel="noopener"><?= sr_icon('wa', 20) ?><span>WhatsApp</span></a>
      </div>

      <?php if (!$utha): ?>
        <form method="post" class="sx-step">
          <input type="hidden" name="csrf" value="<?= $csrf ?>"><input type="hidden" name="do" value="uthaya"><input type="hidden" name="jid" value="<?= (int)$j['id'] ?>">
          <label class="sx-step-l"><?= h(t('Pickup code from the sender', 'भेजने वाले से 4 अंक का कोड पूछिए')) ?></label>
          <div class="sx-otp" data-otp>
            <input type="hidden" name="otp" value="">
            <?php for ($i = 0; $i < 4; $i++): ?><input class="sx-otp-b" inputmode="numeric" maxlength="1" autocomplete="one-time-code" aria-label="<?= $i + 1 ?>"><?php endfor; ?>
          </div>
          <noscript><input class="sx-in" name="otp" inputmode="numeric" maxlength="4" pattern="[0-9]{4}" required></noscript>
          <button class="sx-btn sx-btn-mint" type="submit"><?= sr_icon('check', 20) ?> <?= h(t('Picked up', 'उठा लिया')) ?></button>
        </form>
      <?php else: ?>
        <form method="post" class="sx-step" enctype="multipart/form-data">
          <input type="hidden" name="csrf" value="<?= $csrf ?>"><input type="hidden" name="do" value="de_diya"><input type="hidden" name="jid" value="<?= (int)$j['id'] ?>">
          <label class="sx-step-l"><?= h(t('Delivery code from the customer', 'ग्राहक से 4 अंक का कोड पूछिए')) ?></label>
          <div class="sx-otp" data-otp>
            <input type="hidden" name="otp" value="">
            <?php for ($i = 0; $i < 4; $i++): ?><input class="sx-otp-b" inputmode="numeric" maxlength="1" autocomplete="one-time-code" aria-label="<?= $i + 1 ?>"><?php endfor; ?>
          </div>
          <noscript><input class="sx-in" name="otp" inputmode="numeric" maxlength="4" pattern="[0-9]{4}" required></noscript>
          <label class="sx-photo"><input type="file" name="pod" accept="image/*" capture="environment" onchange="this.parentNode.classList.toggle('has', !!this.files.length)">
            <?= sr_icon('cam', 20) ?><span><b><?= h(t('Delivery photo', 'सामान की फ़ोटो')) ?></b><small><?= h(t('Recommended — tap to take', 'अच्छा रहेगा — दबाकर खींचिए')) ?></small></span><i class="sx-photo-ok"><?= sr_icon('check', 16) ?></i></label>
          <button class="sx-btn sx-btn-mint" type="submit"><?= sr_icon('check', 20) ?> <?= h(t('Delivered', 'पहुँचा दिया')) ?></button>
        </form>
      <?php endif; ?>

      <details class="sx-prob">
        <summary><?= sr_icon('alert', 16) ?> <?= h(t('Report a problem', 'कोई दिक्कत? दफ़्तर को बताइए')) ?></summary>
        <form method="post">
          <input type="hidden" name="csrf" value="<?= $csrf ?>"><input type="hidden" name="do" value="samasya"><input type="hidden" name="jid" value="<?= (int)$j['id'] ?>">
          <div class="sx-chips">
            <?php foreach ($kisme as $kk => $kl): ?>
              <label><input type="radio" name="kism" value="<?= h($kk) ?>" required><span><?= h(sr_message($kl)) ?></span></label>
            <?php endforeach; ?>
          </div>
          <input class="sx-in" type="text" name="note" maxlength="300" placeholder="<?= h(t('Add details (optional)', 'और कुछ बताना हो तो लिखिए')) ?>">
          <button class="sx-btn sx-btn-ghost" type="submit"><?= h(t('Send to office', 'दफ़्तर को भेजिए')) ?></button>
        </form>
      </details>
    </div>
  </details>
<?php endforeach; endif; ?>

<script>
// Naya kaam aaya to ghanti + vibrate (pichhli baar se zyada kaam dikhe to)
(function () {
  var n = <?= count($jobs) ?>, key = 'sx_jobs_' + <?= (int)$rid ?>, old = null;
  try { old = sessionStorage.getItem(key); sessionStorage.setItem(key, n); } catch (e) {}
  if (old !== null && n > parseInt(old, 10)) {
    try { navigator.vibrate && navigator.vibrate([120, 80, 120]); } catch (e) {}
    try { var C = window.AudioContext || window.webkitAudioContext, c = new C(), o = c.createOscillator(), g = c.createGain();
      o.frequency.value = 880; o.connect(g); g.connect(c.destination); g.gain.setValueAtTime(.2, c.currentTime); g.gain.exponentialRampToValueAtTime(.001, c.currentTime + .6); o.start(); o.stop(c.currentTime + .6); } catch (e) {}
  }
})();
</script>
<?php if ($ready && !$jobs): ?>
<script>
// हाज़िर हैं पर काम नहीं — हर मिनट खुद देख लीजिए. Kaam khula ho to refresh NAHI (likha OTP ud jata).
setTimeout(function () { location.reload(); }, 60000);
</script>
<?php endif; ?>

<?php sr_foot('kaam', ['kaam' => count($jobs)]); ?>
