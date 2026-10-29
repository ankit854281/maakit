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
sr_jobs_upgrade($pdo);   // kind (ride/delivery) + GPS khaane
$rid = (int)$me['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_ok()) {
    $do = post('do');

    if ($do === 'bahar')    { rider_location_clear($pdo, $rid); rider_logout(); redirect('/'); }
    // Launch (29 Oct 2026): hosting GPS re-enabled until Firebase tracking module; sends every 20s.
    if ($do === 'location') {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        [$ok, $out] = rider_location_update($pdo, $rid, post('lat'), post('lng'));
        echo json_encode($ok ? ['ok' => true, 'location' => $out] : ['ok' => false, 'error' => $out], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if ($do === 'location_off') {
        rider_location_clear($pdo, $rid);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode(['ok' => true]);
        exit;
    }
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
    <div class="sx-slide" data-slide data-label="<?= h(t('Swipe to go online', 'स्वाइप करके हाज़िरी लगाइए')) ?>"><button type="submit" class="sx-go"><span><?= h(t('Go online', 'हाज़िरी लगाइए')) ?></span><?= sr_icon('arrow', 20) ?></button></div>
  </form>
<?php endif; ?>

<?php sr_jobs_upgrade($pdo); $wk = $pdo->prepare("SELECT do_delivery, do_ride, ride_ok FROM riders WHERE id=?"); $wk->execute([(int)$me['id']]); $wk = $wk->fetch() ?: []; ?>
<a class="sx-worknow" href="<?= h(u('/jankari.php')) ?>">
  <span><?= h(t('Receiving', 'आ रहा है')) ?>:</span>
  <?php if (!empty($wk['do_delivery'])): ?><em class="d"><?= sr_icon('box', 14) ?> <?= h(t('Deliveries', 'डिलीवरी')) ?></em><?php endif; ?>
  <?php if (!empty($wk['do_ride']) && !empty($wk['ride_ok'])): ?><em class="r"><?= sr_icon('bike', 14) ?> <?= h(t('Rides', 'सवारी')) ?></em><?php endif; ?>
  <b><?= h(t('Change', 'बदलिए')) ?> ›</b>
</a>

<?php
// Profile poori karne ki yaad + app ka prachar (owner design P1/P3, 29 Oct 2026)
$myProfile = (sr_firebase_enabled() && function_exists('sr_account') && ($acc0 = sr_account($pdo))) ? sr_profile($pdo, (int)$acc0['id']) : null;
if ($myProfile && function_exists('sr_profile_docs_missing') && sr_profile_docs_missing($myProfile)): ?>
<a class="sx-card" href="<?= h(u('/apply.php')) ?>" style="display:block;text-decoration:none">
  <b><?= h(t('Complete your profile', 'प्रोफ़ाइल पूरी कीजिए')) ?></b>
  <?php [$dd, $dt] = function_exists('sr_doc_count') ? sr_doc_count($myProfile) : [0, 0]; ?>
  <small style="display:block"><?= h(t('Documents ', 'काग़ज़ ') . $dd . '/' . $dt . ' — ' . t('add Aadhaar and PAN. Customers trust verified Sarathis more.', 'आधार और पैन लगाइए। सत्यापित सारथी पर ग्राहक ज़्यादा भरोसा करते हैं।')) ?></small>
</a>
<?php endif;
$shareText = t('Book bike, auto, e-rickshaw, parcel and vehicles in Bhadohi with Smart Sarathi. Ask for me: ', 'भदोही में बाइक, ऑटो, ई-रिक्शा, पार्सल और गाड़ी — स्मार्ट सारथी से बुक कीजिए। मेरा नाम लीजिए: ') . $me['name'] . ' — ' . (defined('SR_MAAKIT_URL') ? rtrim(SR_MAAKIT_URL, '/') : 'https://maakit.in') . '/smartsarathi/ghar.php';
?>
<a class="sx-card" target="_blank" rel="noopener" href="https://wa.me/?text=<?= h(rawurlencode($shareText)) ?>" style="display:block;text-decoration:none">
  <b><?= h(t('Share the app on WhatsApp', 'WhatsApp पर ऐप शेयर कीजिए')) ?></b>
  <small style="display:block"><?= h(t('More customers = more jobs for you.', 'जितने ग्राहक, उतना आपको काम।')) ?></small>
</a>
<!-- आज का हिसाब -->
<div class="sx-stats">
  <a class="sx-stat" href="<?= h(u('/khata.php')) ?>"><small><?= h(t("Today's earnings", 'आज की कमाई')) ?></small><b>₹<?= (int)$k['aaj'] ?></b></a>
  <div class="sx-stat"><small><?= h(t('Delivered today', 'आज पहुँचाए')) ?></small><b><?= (int)$aaj ?></b></div>
  <div class="sx-stat"><small><?= h(t('Active jobs', 'अभी के काम')) ?></small><b><?= count($jobs) ?></b></div>
</div>

<?php if ($jobs): ?>
<section class="sx-loc-share" data-location-share data-csrf="<?= $csrf ?>">
  <div><b><?= h(t('Share your live location', 'अपनी लाइव जगह साझा करें')) ?></b>
    <small><?= h(t('Customers with your active jobs can see your current position. You can stop sharing any time.', 'आपके चालू काम वाले ग्राहक आपकी अभी की जगह देख सकेंगे। आप इसे कभी भी बंद कर सकते हैं।')) ?></small></div>
  <button type="button" data-location-toggle><?= h(t('Turn on sharing', 'जगह साझा करें')) ?></button>
  <p role="status" aria-live="polite" data-location-status><?= h(t('Off — your location is not being shared.', 'बंद — आपकी जगह साझा नहीं हो रही है।')) ?></p>
</section>
<?php endif; ?>

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
    $map   = maps_next($j);
    $isRide = ($j['kind'] ?? 'delivery') === 'ride';
    $pickGeo = preg_match('/^-?\d{1,2}\.\d+,-?\d{1,3}\.\d+$/D', (string)($j['pick_geo'] ?? '')) ? (string)$j['pick_geo'] : '';
    $dropGeo = preg_match('/^-?\d{1,2}\.\d+,-?\d{1,3}\.\d+$/D', (string)($j['drop_geo'] ?? '')) ? (string)$j['drop_geo'] : '';
    $hello = rawurlencode($isRide
        ? t('Hello, I am your Sarathi rider. I am coming to pick you up. Booking ', 'नमस्ते, मैं आपका सारथी राइडर हूँ। आपको लेने आ रहा हूँ। बुकिंग ') . ($j['client_ref'] ?: $j['job_no'])
        : t('Hello, I am your Sarathi delivery partner. I am on the way with your order ', 'नमस्ते, मैं सारथी डिलीवरी पार्टनर हूँ। आपका सामान लेकर आ रहा हूँ — ') . $j['job_no']);
?>
  <details class="sx-job<?= $utha ? ' is-picked' : '' ?>" <?= $khula ? 'open' : '' ?>>
    <summary>
      <span class="sx-job-st<?= $isRide ? ' ride' : '' ?>"><?= $isRide ? sr_icon('bike', 18) : ($utha ? sr_icon('flag', 18) : sr_icon('box', 18)) ?></span>
      <span class="sx-job-t"><b><?= h($j['drop_name']) ?></b><small><?= h($j['drop_village'] ?: $j['drop_address']) ?> · <?= h($j['client_name']) ?></small></span>
      <span class="sx-tag <?= $isRide ? 'blue' : ($utha ? 'mint' : 'sun') ?>"><?= $isRide ? ($utha ? h(t('On ride', 'सवारी चालू')) : h(t('Ride pickup', 'सवारी लेनी है'))) : ($utha ? h(t('Deliver', 'पहुँचाना है')) : h(t('Pick up', 'उठाना है'))) ?></span>
    </summary>

    <div class="sx-job-b">
      <ol class="sx-route">
        <li class="<?= $utha ? 'done' : 'now' ?>"><i></i><div><small><?= $isRide ? h(t('Pick up passenger', 'सवारी यहाँ से')) : h(t('Pick up', 'उठाइए')) ?></small>
          <b><?= h($j['pick_name'] ?: $j['client_name']) ?></b><?php if ($j['pick_address']): ?><span><?= h($j['pick_address']) ?></span><?php endif; ?></div></li>
        <li class="<?= $utha ? 'now' : '' ?>"><i></i><div><small><?= $isRide ? h(t('Drop passenger', 'सवारी यहाँ तक')) : h(t('Deliver to', 'पहुँचाइए')) ?></small>
          <b><?= h($j['drop_name']) ?></b><span><?= h($j['drop_address']) ?></span></div></li>
      </ol>

      <?php if ($pickGeo || $dropGeo): ?>
        <div class="sx-job-map" data-job-map data-pick="<?= h($pickGeo) ?>" data-drop="<?= h($dropGeo) ?>" data-next="<?= h($utha ? 'drop' : 'pick') ?>" aria-label="<?= h(t('Pickup and drop map', 'पिकअप और पहुँचने की जगह का नक्शा')) ?>"></div>
        <p class="sx-map-caption"><?= h(t('Green pin: pickup · Yellow pin: drop. Dashed line is a direct line, not a road route. Navigate opens turn-by-turn directions.', 'हरा निशान: पिकअप · पीला निशान: पहुँचने की जगह। टूटी रेखा सीधी दूरी है, सड़क का रास्ता नहीं। रास्ता दबाने पर दिशा खुलेगी।')) ?></p>
      <?php else: ?>
        <p class="sx-map-caption missing"><?= h(t('No exact GPS pin was sent for this job. Use the address or call before leaving.', 'इस काम के लिए GPS पिन नहीं मिला। निकलने से पहले पता देखकर फ़ोन कर लें।')) ?></p>
      <?php endif; ?>

      <?php if ($j['items']): ?><div class="sx-items"><?= sr_icon('box', 16) ?> <?= h($j['items']) ?></div><?php endif; ?>
      <?php if (!empty($j['note'])):
        // note me maps ka link ho to use "नक्शा खोलिए" button bana do
        $nt = h($j['note']);
        $nt = preg_replace('#https://maps\.google\.com/\?q=(-?[0-9.]+),(-?[0-9.]+)#', '<a class="sx-maplink" href="https://maps.google.com/?q=$1,$2" target="_blank" rel="noopener">' . h(t('Open map', 'नक्शा खोलिए')) . ' ↗</a>', $nt); ?>
        <div class="sx-items note"><?= sr_icon('alert', 16) ?> <span><?= $nt ?></span></div><?php endif; ?>

      <div class="sx-money">
        <?php if ($j['cod_amount'] !== null): ?><div class="cash"><small><?= $isRide ? h(t('Fare (approx.)', 'किराया (लगभग)')) : h(t('Collect cash', 'नगद लेना है')) ?></small><b>₹<?= (int)$j['cod_amount'] ?></b></div><?php endif; ?>
        <?php if ($rate > 0): ?><div><small><?= h(t('You earn', 'आपकी कमाई')) ?></small><b>₹<?= (int)$mera ?></b></div><?php endif; ?>
        <div><small><?= h(t('Job no.', 'काम नं.')) ?></small><b class="sm"><?= h($j['job_no']) ?></b></div>
      </div>

      <div class="sx-acts">
        <?php if ($map): ?><a class="sx-act" href="<?= h($map) ?>" target="_blank" rel="noopener"><?= sr_icon('nav', 20) ?><span><?= h(t('Navigate', 'रास्ता')) ?></span></a><?php endif; ?>
        <a class="sx-act" href="tel:<?= h($j['drop_mobile']) ?>"><?= sr_icon('phone', 20) ?><span><?= $isRide ? h(t('Passenger', 'सवारी')) : h(t('Customer', 'ग्राहक')) ?></span></a>
        <?php if ($j['pick_mobile'] && $j['pick_mobile'] !== $j['drop_mobile']): ?><a class="sx-act" href="tel:<?= h($j['pick_mobile']) ?>"><?= sr_icon('phone', 20) ?><span><?= h(t('Pickup', 'दुकान')) ?></span></a><?php endif; ?>
        <a class="sx-act" href="https://wa.me/91<?= h(preg_replace('/\D/', '', (string)$j['drop_mobile'])) ?>?text=<?= h($hello) ?>" target="_blank" rel="noopener"><?= sr_icon('wa', 20) ?><span>WhatsApp</span></a>
      </div>

      <?php if (!$utha): ?>
        <form method="post" class="sx-step">
          <input type="hidden" name="csrf" value="<?= $csrf ?>"><input type="hidden" name="do" value="uthaya"><input type="hidden" name="jid" value="<?= (int)$j['id'] ?>">
          <label class="sx-step-l"><?= $isRide ? h(t('Ride code from the passenger', 'सवारी से 4 अंक का कोड पूछिए')) : h(t('Pickup code from the sender', 'भेजने वाले से 4 अंक का कोड पूछिए')) ?></label>
          <div class="sx-otp" data-otp>
            <input type="hidden" name="otp" value="">
            <?php for ($i = 0; $i < 4; $i++): ?><input class="sx-otp-b" inputmode="numeric" maxlength="1" autocomplete="one-time-code" aria-label="<?= $i + 1 ?>"><?php endfor; ?>
          </div>
          <noscript><input class="sx-in" name="otp" inputmode="numeric" maxlength="4" pattern="[0-9]{4}" required></noscript>
          <div class="sx-slide" data-slide data-need-otp data-label="<?= h($isRide ? t('Swipe — passenger on board', 'स्वाइप — सवारी बैठ गई') : t('Swipe — picked up', 'स्वाइप — उठा लिया')) ?>"><button class="sx-btn sx-btn-mint" type="submit"><?= sr_icon('check', 20) ?> <?= h(t('Picked up', 'उठा लिया')) ?></button></div>
        </form>
      <?php else: ?>
        <form method="post" class="sx-step" enctype="multipart/form-data">
          <input type="hidden" name="csrf" value="<?= $csrf ?>"><input type="hidden" name="do" value="de_diya"><input type="hidden" name="jid" value="<?= (int)$j['id'] ?>">
          <?php if ($j['cod_amount'] !== null && defined('SR_UPI_ID') && SR_UPI_ID !== ''):
            $upi = 'upi://pay?pa=' . rawurlencode(SR_UPI_ID) . '&pn=' . rawurlencode(defined('SR_UPI_NAME') ? SR_UPI_NAME : 'Smart Sarathi') . '&am=' . (int)$j['cod_amount'] . '&cu=INR&tn=' . rawurlencode($j['job_no']); ?>
          <details class="sx-upi"><summary><?= h(t('Take payment by UPI QR', 'UPI QR से पैसे लीजिए')) ?> · ₹<?= (int)$j['cod_amount'] ?></summary>
            <div class="sx-upi-qr" data-upi="<?= h($upi) ?>" role="img" aria-label="<?= h(t('UPI QR code', 'UPI QR कोड')) ?>"></div>
            <p class="sx-fine"><?= h(t('Customer scans with any UPI app. Check the payment on your phone before ending.', 'ग्राहक किसी भी UPI ऐप से स्कैन करे। ख़त्म करने से पहले पैसा आया है, देख लीजिए।')) ?></p>
          </details>
          <?php endif; ?>
          <label class="sx-step-l"><?= $isRide ? h(t('End code from the passenger', 'सवारी से आख़िरी कोड पूछिए')) : h(t('Delivery code from the customer', 'ग्राहक से 4 अंक का कोड पूछिए')) ?></label>
          <div class="sx-otp" data-otp>
            <input type="hidden" name="otp" value="">
            <?php for ($i = 0; $i < 4; $i++): ?><input class="sx-otp-b" inputmode="numeric" maxlength="1" autocomplete="one-time-code" aria-label="<?= $i + 1 ?>"><?php endfor; ?>
          </div>
          <noscript><input class="sx-in" name="otp" inputmode="numeric" maxlength="4" pattern="[0-9]{4}" required></noscript>
          <?php if (!$isRide): ?><label class="sx-photo"><input type="file" name="pod" accept="image/*" capture="environment" onchange="this.parentNode.classList.toggle('has', !!this.files.length)">
            <?= sr_icon('cam', 20) ?><span><b><?= h(t('Delivery photo', 'सामान की फ़ोटो')) ?></b><small><?= h(t('Recommended — tap to take', 'अच्छा रहेगा — दबाकर खींचिए')) ?></small></span><i class="sx-photo-ok"><?= sr_icon('check', 16) ?></i></label><?php endif; ?>
          <div class="sx-slide" data-slide data-need-otp data-label="<?= h($isRide ? t('Swipe — ride complete', 'स्वाइप — सवारी पहुँच गई') : t('Swipe — delivered', 'स्वाइप — पहुँचा दिया')) ?>"><button class="sx-btn sx-btn-mint" type="submit"><?= sr_icon('check', 20) ?> <?= h(t('Delivered', 'पहुँचा दिया')) ?></button></div>
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
// Rider map and opt-in GPS sharing. The browser starts location access only
// after the rider presses the button; only the latest point is sent.
(function () {
  var maps = [];
  function point(raw) {
    if (!raw) return null;
    var p = raw.split(',').map(Number);
    return p.length === 2 && isFinite(p[0]) && isFinite(p[1]) && Math.abs(p[0]) <= 90 && Math.abs(p[1]) <= 180 ? p : null;
  }
  function makeIcon(kind, label) {
    return L.divIcon({className: 'sx-map-pin ' + kind, html: '<span>' + label + '</span>', iconSize: [30, 38], iconAnchor: [15, 36]});
  }
  function initMap(el) {
    if (!window.L) { el.insertAdjacentHTML('afterend', '<p class="sx-map-caption missing"><?= h(t('Map could not load. Use Navigate or call the customer.', 'नक्शा नहीं खुला। रास्ता खोलें या ग्राहक को फ़ोन करें।')) ?></p>'); return; }
    var a = point(el.dataset.pick), b = point(el.dataset.drop);
    if (!a && !b) return;
    var map = L.map(el, {zoomControl: false, scrollWheelZoom: false});
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {maxZoom: 19, attribution: '© OpenStreetMap'}).addTo(map);
    var pts = [];
    if (a) { L.marker(a, {icon: makeIcon('pick', 'P')}).addTo(map).bindPopup(<?= json_encode(t('Pickup', 'पिकअप')) ?>); pts.push(a); }
    if (b) { L.marker(b, {icon: makeIcon('drop', 'D')}).addTo(map).bindPopup(<?= json_encode(t('Drop', 'पहुँचने की जगह')) ?>); pts.push(b); }
    if (a && b) L.polyline([a, b], {color: '#0C831F', weight: 3, dashArray: '6 7', opacity: .75}).addTo(map);
    if (pts.length > 1) map.fitBounds(pts, {padding: [24, 24], maxZoom: 16}); else map.setView(pts[0], 15);
    var rider = null;
    function showRider(p) {
      if (!p) { if (rider) { map.removeLayer(rider); rider = null; } return; }
      if (!rider) rider = L.circleMarker(p, {radius: 9, color: 'var(--card)', weight: 3, fillColor: 'var(--navy)', fillOpacity: 1}).addTo(map).bindPopup(<?= json_encode(t('You', 'आप')) ?>);
      else rider.setLatLng(p);
      if (!map.getBounds().contains(p)) map.panTo(p);
    }
    el._srMap = {map: map, showRider: showRider};
    maps.push(el);
    var job = el.closest('.sx-job');
    if (job) job.addEventListener('toggle', function () { if (job.open) setTimeout(function () { map.invalidateSize(); }, 80); });
  }
  window.addEventListener('load', function () { document.querySelectorAll('[data-job-map]').forEach(initMap); });
  document.addEventListener('sr:rider-location', function (e) {
    var p = e.detail && e.detail.lat != null ? [e.detail.lat, e.detail.lng] : null;
    maps.forEach(function (el) { if (el._srMap) el._srMap.showRider(p); });
  });

  var box = document.querySelector('[data-location-share]');
  if (!box) return;
  var button = box.querySelector('[data-location-toggle]'), status = box.querySelector('[data-location-status]');
  var watch = null, active = false, lastSent = 0;
  function post(action, coords, keepalive) {
    var body = new URLSearchParams({csrf: box.dataset.csrf, do: action});
    if (coords) { body.set('lat', String(coords.latitude)); body.set('lng', String(coords.longitude)); }
    return fetch(location.pathname, {method: 'POST', credentials: 'same-origin', headers: {'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8'}, body: body.toString(), keepalive: !!keepalive});
  }
  function stop(clear) {
    active = false;
    if (watch !== null && navigator.geolocation) navigator.geolocation.clearWatch(watch);
    watch = null; button.textContent = <?= json_encode(t('Turn on sharing', 'जगह साझा करें'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    document.dispatchEvent(new CustomEvent('sr:rider-location', {detail: null}));
    if (clear) post('location_off').then(function () { status.textContent = <?= json_encode(t('Off — your location is not being shared.', 'बंद — आपकी जगह साझा नहीं हो रही है।'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>; }).catch(function () {});
  }
  button.addEventListener('click', function () {
    if (active) { stop(true); return; }
    if (!navigator.geolocation) { status.textContent = <?= json_encode(t('This phone cannot share GPS here. Use Navigate or call the customer.', 'इस फ़ोन में GPS उपलब्ध नहीं। रास्ता खोलें या ग्राहक को फ़ोन करें।'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>; return; }
    active = true; lastSent = 0;
    button.textContent = <?= json_encode(t('Stop sharing', 'साझा करना बंद करें'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    status.textContent = <?= json_encode(t('Waiting for GPS permission…', 'GPS की अनुमति का इंतज़ार…'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    watch = navigator.geolocation.watchPosition(function (pos) {
      if (!active || Date.now() - lastSent < 20000) return;
      lastSent = Date.now();
      post('location', pos.coords).then(function (r) { return r.json(); }).then(function (j) {
        if (!j.ok) { status.textContent = j.error || <?= json_encode(t('Location sharing stopped.', 'जगह साझा करना रुक गया।')) ?>; stop(false); return; }
        document.dispatchEvent(new CustomEvent('sr:rider-location', {detail: j.location}));
        status.textContent = <?= json_encode(t('Live — shared with customers on your active jobs.', 'लाइव — आपके चालू काम वाले ग्राहकों से साझा है।'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
      }).catch(function () { status.textContent = <?= json_encode(t('Network weak — waiting to send your position.', 'नेट धीमा है — जगह भेजने का इंतज़ार है।'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>; });
    }, function (err) {
      status.textContent = err.code === 1
        ? <?= json_encode(t('Location permission denied. Allow location in your browser to share it.', 'लोकेशन की अनुमति नहीं मिली। साझा करने के लिए ब्राउज़र में अनुमति दें।'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>
        : <?= json_encode(t('GPS signal unavailable. Check the phone location setting and try again.', 'GPS सिग्नल नहीं मिला। फ़ोन की लोकेशन चालू करके फिर कोशिश करें।'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
      stop(true);
    }, {enableHighAccuracy: true, maximumAge: 5000, timeout: 20000});
  });
  window.addEventListener('pagehide', function () {
    if (!active) return;
    active = false;
    if (watch !== null && navigator.geolocation) navigator.geolocation.clearWatch(watch);
    var body = new URLSearchParams({csrf: box.dataset.csrf, do: 'location_off'});
    if (navigator.sendBeacon) navigator.sendBeacon(location.pathname, new Blob([body.toString()], {type: 'application/x-www-form-urlencoded;charset=UTF-8'}));
  });
})();
</script>

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

<?php if (defined('SR_UPI_ID') && SR_UPI_ID !== ''): ?>
<style>.sx-upi{border:1px solid var(--line,#E3E8EF);border-radius:14px;padding:10px 12px;margin:8px 0}.sx-upi summary{min-height:44px;display:flex;align-items:center;font-weight:800;cursor:pointer}.sx-upi-qr{display:grid;place-items:center;padding:10px;background:#fff}.sx-upi-qr svg{width:220px;height:220px}</style>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcode-generator/1.4.4/qrcode.min.js" defer></script>
<script>
document.addEventListener('toggle', function (e) {
  var d = e.target; if (!d.classList || !d.classList.contains('sx-upi') || !d.open) return;
  var box = d.querySelector('[data-upi]'); if (!box || box.firstChild || !window.qrcode) return;
  var q = qrcode(0, 'M'); q.addData(box.getAttribute('data-upi')); q.make(); box.innerHTML = q.createSvgTag({ cellSize: 6, margin: 2 });
}, true);
</script>
<?php endif; ?>
<?php sr_foot('kaam', ['kaam' => count($jobs)]); ?>
