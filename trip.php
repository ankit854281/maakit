<?php
// ============================================================
//  सवारी / पार्सल कहाँ है — maakit.in/trip.php
//
//  Booking number + mobile (bookh karne wala YA parcel paane wala).
//  Is phone se bani booking bina mobile ke bhi khulti hai
//  ($_SESSION['my_trips']). Halat Smart Sarathi se aati hai
//  (inc/trips.php → trip_sync), har 15 second me ek baar tak.
//  ?json=1 — panna khud dekhta rehta hai ki halat badli ya nahi.
// ============================================================
require_once __DIR__ . '/inc/fn.php';
require_once __DIR__ . '/inc/trips.php';

$tab = 'mere';
$no = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string)($_POST['no'] ?? $_GET['no'] ?? '')));
$m  = preg_replace('/\D/', '', (string)($_POST['m'] ?? $_GET['m'] ?? ''));
$mine = array_map('strval', $_SESSION['my_trips'] ?? []);
$t = null; $err = ''; $msg = '';

if ($no !== '' && trips_table($pdo) && ($m !== '' || in_array($no, $mine, true))) {
    $_SESSION['trk_n'] = (int)($_SESSION['trk_n'] ?? 0);
    if ($_SESSION['trk_n'] > 40 && !in_array($no, $mine, true)) {
        $err = t('Too many tries. Please wait a while, or call us.', 'बहुत बार कोशिश हो गई। थोड़ी देर बाद देखिए या हमें फ़ोन कीजिए।');
    } else {
        $st = $pdo->prepare("SELECT * FROM rides WHERE trip_no=? LIMIT 1");
        $st->execute([$no]);
        $row = $st->fetch();
        if ($row && (in_array($no, $mine, true) || ($m !== '' && ($row['mobile'] === $m || $row['drop_mobile'] === $m)))) {
            $t = $row;
            if (!in_array($no, $mine, true)) $_SESSION['my_trips'][] = $no;
        } else {
            $_SESSION['trk_n']++;
            $err = t('No booking found with this number and mobile.', 'इस नंबर और मोबाइल से कोई बुकिंग नहीं मिली।');
        }
    }
}

if ($t) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('do') === 'cancel') {
        if (!csrf_ok()) $err = t('Page expired — try again.', 'पेज पुराना हो गया — दोबारा दबाइए।');
        else { [$okc, $msg] = trip_cancel($pdo, $t); if (!$okc) { $err = $msg; $msg = ''; } $t = trip_get($pdo, $t['id']); }
    }
    try { $t = trip_sync($pdo, $t); } catch (Throwable $e) { error_log('trip_sync: ' . $e->getMessage()); }
    if (get('json') === '1') {
        header('Content-Type: application/json');
        echo json_encode(['s' => $t['status'], 'r' => (string)$t['rider_name']]);
        exit;
    }
}

$page_title = t('Track booking — Maakit', 'बुकिंग देखिए — Maakit');
$page_css = ['https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.css', '/assets/ride.css?v=' . (int)@filemtime(__DIR__ . '/assets/ride.css')];
include __DIR__ . '/inc/head.php';
$V = trip_vehicles($pdo);
?>
<main class="rd rt">
<div class="rd-wrap">
  <div class="rd-top">
    <a class="rd-back" href="/ride.php<?= $t && $t['kind'] === 'parcel' ? '?t=parcel' : '' ?>" aria-label="<?= h(t('Back', 'वापस')) ?>"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6"/></svg></a>
    <div><h1><?= $t ? ($t['kind'] === 'parcel' ? t('Your parcel', 'आपका पार्सल') : t('Your ride', 'आपकी सवारी')) : t('Track a booking', 'बुकिंग देखिए') ?></h1>
      <p><?= t('Status updates by itself', 'हालत अपने आप ताज़ा होती है') ?></p></div>
  </div>

  <?php if ($err): ?><div class="rd-err" role="alert"><?= h($err) ?></div><?php endif; ?>
  <?php if ($msg): ?><div class="rt-ok"><?= h($msg) ?></div><?php endif; ?>

<?php if ($t):
    $ride = $t['kind'] === 'ride';
    [$k, $names] = trip_steps($t);
    $live = in_array($t['status'], ['new', 'assigned', 'picked'], true);
    $v = $V[$t['vehicle']] ?? $V['bike'];
    $cls = $t['status'] === 'delivered' ? ' done' : ($t['status'] === 'cancelled' ? ' cancel' : '');
    $sub = [
        'new' => $t['sarathi_job'] ? t('Finding a rider near you…', 'आपके पास का राइडर ढूँढ रहे हैं…') : t('Booking received — our team will assign a rider and call you.', 'बुकिंग मिल गई — हमारी टीम राइडर लगाकर आपको फ़ोन करेगी।'),
        'assigned' => $ride ? t('Your rider is coming to the pickup point.', 'राइडर आपको लेने आ रहा है।') : t('Rider is coming to pick up the parcel.', 'राइडर पार्सल उठाने आ रहा है।'),
        'picked' => $ride ? t('Enjoy the ride. Share the end code only at your drop.', 'सफ़र का आनंद लीजिए। आख़िरी कोड उतरते समय ही बताइए।') : t('Parcel is on the way.', 'पार्सल रास्ते में है।'),
        'delivered' => $ride ? t('Thanks for riding with Maakit.', 'Maakit के साथ सफ़र के लिए धन्यवाद।') : t('Delivered safely.', 'सही-सलामत पहुँच गया।'),
        'cancelled' => t('This booking was cancelled.', 'यह बुकिंग रद्द हुई।'),
    ][$t['status']] ?? '';
?>
  <?php if (get('new') === '1' && $t['status'] !== 'cancelled'): ?><div class="rt-ok">✓ <?= $ride ? t('Ride booked!', 'सवारी बुक हो गई!') : t('Pickup booked!', 'पिकअप बुक हो गया!') ?> <?= t('Keep this page open.', 'यह पेज खुला रखिए।') ?></div><?php endif; ?>

  <section class="rt-hero<?= $cls ?>" data-status="<?= h($t['status']) ?>">
    <small><?= $ride ? h($v['emoji'] . ' ' . $v['name']) : '📦 ' . t('Parcel', 'पार्सल') ?> · <?= h($t['trip_no']) ?></small>
    <h1><?= $t['status'] === 'cancelled' ? t('Cancelled', 'रद्द') : h($names[max(0, $k)]) ?></h1>
    <p><?= h($sub) ?></p>
    <?php if ($t['status'] !== 'cancelled'): ?><div class="rt-bar" aria-hidden="true"><?php for ($i = 0; $i < 4; $i++): ?><i class="<?= $i <= $k ? 'on' : '' ?>"></i><?php endfor; ?></div><?php endif; ?>
    <?php if ($live): ?><span class="rt-live"><i></i><?= t('Live', 'लाइव') ?></span><?php endif; ?>
  </section>

  <?php if ($live): ?>
    <section class="rd-card">
      <?php if ($ride): $code = $t['status'] === 'picked' ? $t['drop_code'] : $t['pick_code']; ?>
        <div class="rt-code">
          <div class="rt-code-n" aria-label="<?= h($code) ?>"><?php foreach (str_split($code) as $d): ?><b><?= h($d) ?></b><?php endforeach; ?></div>
          <div class="rt-code-t"><b><?= $t['status'] === 'picked' ? t('End code', 'आख़िरी कोड') : t('Start code', 'शुरू का कोड') ?></b>
            <small><?= $t['status'] === 'picked' ? t('Tell the rider only when you reach.', 'पहुँचकर ही राइडर को बताइए।') : t('Tell the rider when you sit. The ride starts only with this code.', 'बैठते समय राइडर को बताइए। इसी कोड से सवारी शुरू होगी।') ?></small></div>
        </div>
      <?php else:
        $base = (isset($_SERVER['HTTP_HOST']) ? ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https://' : 'http://') . $_SERVER['HTTP_HOST']) : 'https://maakit.in');
        $wa = t('Your parcel is coming via Maakit. Booking ', 'आपका पार्सल Maakit से आ रहा है। बुकिंग ') . $t['trip_no'] . '. ' . t('Tell this code to the rider when you receive it: ', 'लेते समय राइडर को यह कोड बताइए: ') . $t['drop_code'] . '. ' . t('Track: ', 'देखिए: ') . $base . '/trip.php?no=' . $t['trip_no'] . '&m=' . $t['drop_mobile']; ?>
        <div class="rt-codes2">
          <div><small><?= t('Pickup code', 'देने का कोड') ?></small><b><?= h($t['pick_code']) ?></b><span><?= t('Tell the rider when handing over', 'पार्सल देते समय राइडर को बताइए') ?></span></div>
          <div><small><?= t('Delivery code', 'लेने का कोड') ?></small><b><?= h($t['drop_code']) ?></b><span><?= t('The receiver tells the rider', 'पाने वाला राइडर को बताए') ?></span></div>
        </div>
        <?php if ($t['drop_mobile'] && $t['drop_mobile'] !== $t['mobile']): ?>
          <a class="rt-btn wa" style="margin-top:12px" href="https://wa.me/91<?= h($t['drop_mobile']) ?>?text=<?= h(rawurlencode($wa)) ?>" target="_blank" rel="noopener"><svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2z"/></svg> <?= t('Send code to receiver', 'पाने वाले को कोड भेजिए') ?></a>
        <?php endif; ?>
      <?php endif; ?>
    </section>

    <section class="rd-card">
      <?php if ($t['rider_name']): $fn = explode(' ', trim($t['rider_name']))[0]; ?>
        <div class="rt-rider">
          <span class="rt-av"><?= h(mb_strtoupper(mb_substr($fn, 0, 1))) ?></span>
          <div class="rt-rider-t"><b><?= h($fn) ?></b><small><?= t('Sarathi rider · verified by our office', 'सारथी राइडर · दफ़्तर से जाँचा हुआ') ?></small></div>
          <?php if ($t['rider_mobile']): ?>
            <a class="rt-call wa" href="https://wa.me/91<?= h($t['rider_mobile']) ?>" target="_blank" rel="noopener" aria-label="WhatsApp"><svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2z"/></svg></a>
            <a class="rt-call" href="tel:<?= h($t['rider_mobile']) ?>" aria-label="<?= h(t('Call rider', 'राइडर को फ़ोन')) ?>"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2"/></svg></a>
          <?php endif; ?>
        </div>
      <?php else: ?>
        <div class="rt-wait"><span class="rt-spin" aria-hidden="true"></span><span><?= $t['sarathi_job'] ? t('Looking for the nearest free rider. You will see their name and number here.', 'सबसे पास का खाली राइडर ढूँढ रहे हैं। उनका नाम और नंबर यहीं दिखेगा।') : t('We have your booking. A rider will be assigned shortly — you will get a call.', 'आपकी बुकिंग हमारे पास है। जल्दी राइडर लगेगा — आपको फ़ोन आएगा।') ?></span></div>
      <?php endif; ?>
    </section>
  <?php endif; ?>

  <section class="rd-card">
    <div class="rt-map" id="rtMap" hidden data-a="<?= h((string)$t['pick_geo']) ?>" data-b="<?= h((string)$t['drop_geo']) ?>"></div>
    <ol class="rt-route">
      <li><small><?= $ride ? t('Pickup', 'कहाँ से') : t('From', 'कहाँ से') ?></small><b><?= h($t['pick_address']) ?></b></li>
      <li class="b"><small><?= $ride ? t('Drop', 'कहाँ तक') : t('To', 'कहाँ तक') ?></small><b><?= !$ride && $t['drop_name'] && $t['drop_mobile'] !== $t['mobile'] ? h($t['drop_name']) . ' · ' : '' ?><?= h($t['drop_address']) ?></b></li>
    </ol>
    <div class="rt-meta">
      <div><small><?= t('Fare', 'किराया') ?></small><b><?= $t['fare_est'] !== null ? '₹' . (int)$t['fare_est'] : t('On call', 'फ़ोन पर') ?></b></div>
      <div><small><?= t('Distance', 'दूरी') ?></small><b><?= $t['km'] !== null ? h(rtrim(rtrim((string)$t['km'], '0'), '.')) . ' km' : '—' ?></b></div>
      <div><small><?= t('When', 'कब') ?></small><b><?= $t['when_at'] ? h(date('h:i A', strtotime($t['when_at']))) : t('Now', 'अभी') ?></b></div>
    </div>
    <?php if ($t['fare_est'] !== null): ?><p class="rd-fine" style="margin-top:10px"><?= $ride ? t('Approximate fare by distance — pay the rider in cash or UPI at the end.', 'दूरी के हिसाब से लगभग किराया — आख़िर में राइडर को नगद या UPI से दीजिए।') : ($t['pay_by'] === 'receiver' ? t('Receiver pays the rider at delivery (approx.).', 'पाने वाला पहुँचने पर राइडर को देगा (लगभग)।') : t('You pay the rider at pickup (approx.).', 'आप पार्सल देते समय राइडर को देंगे (लगभग)।')) ?></p><?php endif; ?>
  </section>

  <div class="rt-acts">
    <?php if (in_array($t['status'], ['new', 'assigned'], true)): ?>
      <form method="post" onsubmit="return confirm('<?= h(t('Cancel this booking?', 'यह बुकिंग रद्द करें?')) ?>')">
        <input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="do" value="cancel">
        <input type="hidden" name="no" value="<?= h($t['trip_no']) ?>"><input type="hidden" name="m" value="<?= h($m) ?>">
        <button class="rt-btn red" type="submit"><?= t('Cancel', 'रद्द कीजिए') ?></button>
      </form>
    <?php else: ?>
      <a class="rt-btn dark" href="/ride.php<?= $ride ? '' : '?t=parcel' ?>"><?= $ride ? t('Book another ride', 'एक और सवारी') : t('Send another', 'एक और पार्सल') ?></a>
    <?php endif; ?>
    <a class="rt-btn" href="tel:<?= h(MAAKIT_PHONE) ?>"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2"/></svg> <?= t('Help', 'मदद') ?></a>
  </div>

  <script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.js" defer></script>
  <script>
  window.addEventListener('load', function () {
    var el = document.getElementById('rtMap');
    function p(g) { if (!g) return null; var x = g.split(','); return [+x[0], +x[1]]; }
    var a = p(el.dataset.a), b = p(el.dataset.b);
    if (window.L && (a || b)) {
      el.hidden = false;
      var map = L.map(el, { zoomControl: false, dragging: !L.Browser.mobile, scrollWheelZoom: false });
      L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '© OpenStreetMap' }).addTo(map);
      function ic(c, t) { return L.divIcon({ className: 'rd-mk ' + c, html: '<span>' + t + '</span>', iconSize: [30, 38], iconAnchor: [15, 36] }); }
      if (a) L.marker(a, { icon: ic('a', 'A') }).addTo(map);
      if (b) L.marker(b, { icon: ic('b', 'B') }).addTo(map);
      if (a && b) { L.polyline([a, b], { color: '#111827', weight: 3, dashArray: '6 8' }).addTo(map); map.fitBounds([a, b], { padding: [30, 30], maxZoom: 16 }); }
      else map.setView(a || b, 15);
    }
    <?php if ($live): ?>
    // halat badli to panna taaza
    var s = <?= json_encode($t['status']) ?>, r = <?= json_encode((string)$t['rider_name']) ?>;
    setInterval(function () {
      if (document.hidden) return;
      fetch(location.pathname + '?no=<?= h(urlencode($t['trip_no'])) ?>&m=<?= h(urlencode($m)) ?>&json=1', { credentials: 'same-origin' })
        .then(function (x) { return x.json(); }).then(function (j) {
          if (j.s !== s || j.r !== r) { try { navigator.vibrate && navigator.vibrate([60, 40, 60]); } catch (e) {} location.replace(location.pathname + '?no=<?= h(urlencode($t['trip_no'])) ?>&m=<?= h(urlencode($m)) ?>'); }
        }).catch(function () {});
    }, 15000);
    <?php endif; ?>
  });
  </script>

<?php else: ?>
  <form method="post" class="rd-card rt-find">
    <label class="rd-f"><span><?= t('Booking number', 'बुकिंग नंबर') ?></span><input name="no" value="<?= h($no) ?>" placeholder="RD… / PL…" autocapitalize="characters" required></label>
    <label class="rd-f"><span><?= t('Mobile used for booking (or receiver)', 'बुकिंग वाला मोबाइल (या पाने वाले का)') ?></span><input name="m" type="tel" inputmode="numeric" value="<?= h($m) ?>" required pattern="[6-9][0-9]{9}"></label>
    <button class="rt-btn dark" type="submit"><?= t('Show status', 'हालत देखिए') ?></button>
  </form>
  <?php $list = trip_mine($pdo, (function_exists('cust') && cust()) ? cust()['mobile'] : null, 5); if ($list): ?>
  <section class="rd-card rd-mine">
    <h2 class="rd-h"><?= t('Your recent bookings', 'आपकी पिछली बुकिंग') ?></h2>
    <?php foreach ($list as $tr): [$kk, $nm] = trip_steps($tr); ?>
      <a href="/trip.php?no=<?= h(urlencode($tr['trip_no'])) ?>&amp;m=<?= h(urlencode($tr['mobile'])) ?>">
        <span class="rd-mine-ic"><?= $tr['kind'] === 'parcel' ? '📦' : ($V[$tr['vehicle']]['emoji'] ?? '🏍️') ?></span>
        <span class="rd-mine-t"><b><?= h(mb_strimwidth($tr['drop_address'], 0, 42, '…')) ?></b><small><?= h($tr['trip_no']) ?> · <?= h(date('d M, h:i A', strtotime($tr['created_at']))) ?></small></span>
        <em class="st-<?= h($tr['status']) ?>"><?= $tr['status'] === 'cancelled' ? t('Cancelled', 'रद्द') : h($nm[max(0, $kk)]) ?></em>
      </a>
    <?php endforeach; ?>
  </section>
  <?php endif; ?>
<?php endif; ?>
</div>
</main>
<?php include __DIR__ . '/inc/foot.php'; ?>
