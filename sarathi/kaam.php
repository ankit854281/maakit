<?php
// ============================================================
//  सारथी — काम
//
//  Sirf wahi jo abhi karna hai. Ek order khula, baaki patli
//  patti me. Haazri upar ek patti. Baaki sab cheezein ☰ ke
//  menu me aur neeche ki tab patti me hain.
//
//  Sare POST yahin aate hain — baaki panne sirf dikhate hain.
// ============================================================
require_once __DIR__ . '/../inc/fn.php';
require_once __DIR__ . '/../inc/sarathi.php';
require_once __DIR__ . '/../inc/sarathi-ui.php';
// dispatch_queue() — haazri lagte hi aur delivery poori hote hi
// intezaar wale order baant deta hai. delivery/index.php bhi yahi karta hai.
require_once __DIR__ . '/../inc/dispatch.php';

$me = sarathi_me();
if (!$me) { redirect('/sarathi/'); }
$uid = (int)$me['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_ok()) {
    $do = post('do');

    if ($do === 'bahar') {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) { session_regenerate_id(true); session_destroy(); }
        redirect('/sarathi/');
    }
    if ($do === 'duty_on')  { sarathi_duty_on($pdo, $uid); dispatch_queue($pdo); flash('हाज़िरी लग गई — अगले 90 मिनट तक।'); redirect('/sarathi/kaam.php'); }
    if ($do === 'duty_off') { sarathi_duty_off($pdo, $uid); flash('अब आप बंद हैं।');                  redirect('/sarathi/kaam.php'); }

    if ($do === 'trip_band') {
        flash(sarathi_trip_band($pdo, $uid)
            ? 'चक्कर बंद हो गया।'
            : 'इस चक्कर में कोई ऑर्डर बाकी है — पहले उसे निपटाइए।');
        redirect('/sarathi/kaam.php');
    }
    if ($do === 'uthaya') {
        [$ok, $msg] = sarathi_uthaya($pdo, $uid, (int)post('oid'), post('otp'));
        flash($msg); redirect('/sarathi/kaam.php');
    }
    if ($do === 'de_diya') {
        $photo = save_item_photo('pod', 'pod', 700);   // dheeme net ke liye chhoti
        $oid = (int)post('oid');
        [$ok, $msg] = sarathi_de_diya($pdo, $uid, $oid, post('otp'), $photo);
        if (!$ok && $photo) { drop_photo($photo); }
        if ($ok) {
            // Wahi do kaam jo delivery/index.php delivery ke baad karta hai:
            // graahak ki live location band, aur agla intezaar wala order.
            $pdo->prepare("DELETE FROM live_tracks WHERE order_id=?")->execute([$oid]);
            dispatch_queue($pdo);
        }
        flash($msg); redirect('/sarathi/kaam.php');
    }
    if ($do === 'samasya') {
        [$ok, $msg] = sarathi_samasya_likho($pdo, $uid, (int)post('oid'), post('kism'), post('note'));
        flash($msg); redirect('/sarathi/kaam.php');
    }
}

$kaam     = sarathi_kaam($pdo, $uid);
$duty_tak = sarathi_duty_tak($pdo, $uid);
$aaj_n    = sarathi_aaj_ginti($pdo, $uid);
$trip     = sarathi_trip_khula($pdo, $uid);
$msg      = flash();
$rate     = sarathi_rate($pdo, $uid);
$extra    = sarathi_extra_rate($pdo);
$kisme    = sarathi_samasya_kism();

$chakkar_me = 0;
foreach ($kaam as $k) { if ($k['trip_id'] && $trip && (int)$k['trip_id'] === (int)$trip['id']) $chakkar_me++; }

sr_shell_head($me, 'kaam', 'काम', $duty_tak, ['kaam' => count($kaam)]);
?>

<?php if ($msg): ?><div class="sr-flash"><?= h($msg) ?></div><?php endif; ?>

<!-- ===== आज का हाल — एक पट्टी ===== -->
<?php if ($duty_tak): ?>
  <?php
    // Haazri ka samay upar patti me pehle se likha hai. Yahan dobara
    // likhne se do line ban jati thi. Yahan sirf aaj ki ginti.
  ?>
  <div class="sr-strip sr-strip-on">
    <span><i></i> आज <b><?= $aaj_n ?></b> पहुँचाए</span>
    <div>
      <form method="post"><input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
        <input type="hidden" name="do" value="duty_on"><button type="submit">+90 मि</button></form>
      <form method="post"><input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
        <input type="hidden" name="do" value="duty_off"><button type="submit" class="sr-x">बंद</button></form>
    </div>
  </div>
<?php else: ?>
  <form method="post" class="sr-strip sr-strip-off">
    <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
    <input type="hidden" name="do" value="duty_on">
    <span>अभी आप बंद हैं — नया काम नहीं आएगा</span>
    <button type="submit">मैं तैयार हूँ</button>
  </form>
<?php endif; ?>

<!-- ===== चक्कर ===== -->
<?php if ($trip): ?>
  <div class="sr-strip sr-strip-trip">
    <span>इस चक्कर में <b><?= $chakkar_me ?></b></span>
    <form method="post"><input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
      <input type="hidden" name="do" value="trip_band"><button type="submit">चक्कर बंद</button></form>
  </div>
<?php endif; ?>

<!-- ===== काम ===== -->
<?php if (!$kaam): ?>
  <div class="sr-khali">
    <?= sr_icon('box', 40) ?>
    <p>अभी कोई काम नहीं है।</p>
    <p class="sr-khali-s">हाज़िरी लगी रहने दीजिए — काम आते ही यहीं दिखेगा।</p>
  </div>
<?php else:
  // Ek baar me SIRF EK khula. Pehle har order poora khula tha aur
  // rider dhoop me gaadi par sirf scroll karta rehta tha.
  $pehla = true;
  foreach ($kaam as $o):
    $utha = (bool)$o['picked_at'];
    $pick_chahiye = !$utha && !empty($o['pick_otp']);
    $khula = $pehla; $pehla = false;
?>
  <details class="sr-ord <?= $utha ? 'sr-ord-utha' : '' ?>" <?= $khula ? 'open' : '' ?>>
    <summary class="sr-head">
      <span class="sr-dot"></span>
      <span class="sr-head-mid">
        <b><?= h($o['customer_name']) ?></b>
        <small><?= h($o['village']) ?><?= $o['shop'] ? ' · ' . h($o['shop']) : '' ?></small>
      </span>
      <span class="sr-head-tag"><?= $utha ? 'साथ में' : 'दुकान पर' ?></span>
    </summary>

    <div class="sr-body">
      <?php if ($o['landmark']): ?><p class="sr-mark"><?= h($o['landmark']) ?></p><?php endif; ?>
      <?php if ($o['items']): ?><div class="sr-items"><?= h($o['items']) ?></div><?php endif; ?>

      <div class="sr-money">
        <span>#<?= h($o['order_no']) ?></span>
        <?php if ($o['delivery_charge'] !== null): ?>
          <span>डिलीवरी <b>₹<?= (int)$o['delivery_charge'] ?></b></span>
        <?php else: ?><span class="sr-warn">चार्ज तय नहीं</span><?php endif; ?>
        <?php if ($o['goods_amount'] !== null): ?>
          <span>सामान <b>₹<?= (int)$o['goods_amount'] ?></b></span>
        <?php endif; ?>
      </div>

      <?php
        // Rider ko sabse pehle yahi jaanna hota hai — "mujhe kitna
        // milega". Pehle ye sirf khate me pata chalta tha, kaam ke
        // waqt nahi. Chakkar ka pehla drop poore rate par, usi
        // chakkar ka agla kam par — isliye dono haal alag dikhate hain.
        $mera = ($o['trip_id'] && (int)$o['trip_seq'] > 1) ? $extra : $rate;
      ?>
      <?php if ($rate > 0): ?>
        <div class="sr-mera">आपको मिलेगा <b>₹<?= (int)$mera ?></b><?php
          if ($extra > 0 && (int)$mera === $rate): ?><small>· इसी चक्कर में अगली का ₹<?= $extra ?></small><?php
          endif; ?></div>
      <?php endif; ?>

      <!-- रास्ता और फ़ोन — एक ही कतार -->
      <div class="sr-go">
        <?php if ($r = sarathi_raasta($o)): ?>
          <a class="sr-go-a sr-go-map" href="<?= h($r) ?>" target="_blank" rel="noopener">रास्ता दिखाइए</a>
        <?php endif; ?>
        <a class="sr-go-a" href="tel:<?= h(preg_replace('/\D/','',$o['mobile'])) ?>"><?= sr_icon('phone',17) ?> ग्राहक</a>
        <?php if (!empty($o['shop_mobile'])): ?>
          <a class="sr-go-a" href="tel:<?= h(preg_replace('/\D/','',$o['shop_mobile'])) ?>"><?= sr_icon('phone',17) ?> दुकान</a>
        <?php endif; ?>
      </div>

      <!-- मैं निकल गया — ग्राहक को live location.
           Ye Maakit me pehle se bana hua hai (api.php + track.php +
           assets/delivery-tracking.js). Naya nahi banaya — wahi
           chalaya hai, taaki do tarah ki tracking na ho jaye. -->
      <?php if ($utha): ?>
        <div class="sr-trk trk" data-o="<?= (int)$o['id'] ?>">
          <button type="button" class="sr-go-a trkOn">📍 मैं निकल गया</button>
          <button type="button" class="sr-go-a sr-go-stop trkOff" style="display:none">location बंद कीजिए</button>
          <span class="sr-trk-m trkMsg"></span>
        </div>
      <?php endif; ?>

      <?php if (!$utha): ?>
        <form method="post" class="sr-act">
          <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
          <input type="hidden" name="do" value="uthaya">
          <input type="hidden" name="oid" value="<?= (int)$o['id'] ?>">
          <?php if ($pick_chahiye): ?>
            <label class="sr-lab">दुकानदार से <b>4 अंक</b> पूछिए</label>
            <div class="sr-pair">
              <input class="sr-otp" name="otp" inputmode="numeric" maxlength="4" size="4"
                     pattern="[0-9]{4}" placeholder="____" required>
              <button class="sr-btn sr-btn-go" type="submit">उठा लिया</button>
            </div>
          <?php else: ?>
            <button class="sr-btn sr-btn-go" type="submit">दुकान से उठा लिया</button>
          <?php endif; ?>
          <a class="sr-call-sm" href="tel:<?= h(preg_replace('/\D/','',$o['mobile'])) ?>"><?= sr_icon('phone',18) ?> ग्राहक को फ़ोन</a>
        </form>
      <?php else: ?>
        <form method="post" class="sr-act" enctype="multipart/form-data">
          <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
          <input type="hidden" name="do" value="de_diya">
          <input type="hidden" name="oid" value="<?= (int)$o['id'] ?>">
          <label class="sr-lab">ग्राहक से <b>4 अंक</b> पूछिए</label>
          <div class="sr-pair">
            <input class="sr-otp" name="otp" inputmode="numeric" maxlength="4" size="4"
                   pattern="[0-9]{4}" placeholder="____" required>
            <button class="sr-btn sr-btn-done" type="submit">पहुँचा दिया</button>
          </div>
          <label class="sr-file-lab">
            <input type="file" name="pod" accept="image/*" capture="environment">
            <span>सामान की फ़ोटो लीजिए <small>(अच्छा रहेगा)</small></span>
          </label>
        </form>
      <?php endif; ?>

      <!-- ===== समस्या बताइए =====
           Raste me sabse zyada yahi hota hai: graahak ghar par
           nahi, dukaan band, pata galat. Pehle rider ke paas
           batane ka koi rasta hi nahi tha — wo atak jata tha.
           Order yahan se radd NAHI hota; wo Maakit ka kaam hai. -->
      <details class="sr-prob">
        <summary>कुछ दिक्कत आ रही है?</summary>
        <form method="post" class="sr-prob-f">
          <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
          <input type="hidden" name="do" value="samasya">
          <input type="hidden" name="oid" value="<?= (int)$o['id'] ?>">
          <?php foreach ($kisme as $kk => $kl): ?>
            <label class="sr-rad">
              <input type="radio" name="kism" value="<?= h($kk) ?>" required>
              <span><?= h($kl) ?></span>
            </label>
          <?php endforeach; ?>
          <input class="sr-in" type="text" name="note" maxlength="300" placeholder="कुछ और बताना हो तो लिखिए">
          <button class="sr-btn sr-btn-warn" type="submit">Maakit को बताइए</button>
        </form>
      </details>

    </div>
  </details>
<?php endforeach; endif; ?>

<?php
// Live location — Maakit ka pehle se bana hua tracking. Naya nahi
// banaya; wahi script aur wahi api.php chal rahe hain.
if ($kaam): ?>
<script>window.MAAKIT_TRACKING=<?= json_encode([
  'csrf'    => csrf(),
  'waiting' => 'जगह ढूँढ रहे हैं…',
  'shared'  => 'ग्राहक को location भेजी जा रही है',
  'failed'  => 'location नहीं भेजी जा सकी। नेट जाँचिए।',
  'denied'  => 'फ़ोन की settings में location चालू कीजिए।',
  'stale'   => 'नई GPS location का इंतज़ार है।',
  'stopped' => 'location भेजनी बंद',
  'paused'  => 'GPS चलने के लिए यह पन्ना सामने रखिए।',
], JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_UNESCAPED_UNICODE) ?>;</script>
<script src="/assets/delivery-tracking.js" defer></script>
<?php endif; ?>

<script>
// ---- नेट चला गया तो बता दीजिए ----
// Gaon me net aata-jata rehta hai. Rider "utha liya" daba kar
// aage badh jaye aur wo pahuncha hi na ho — ye sabse bura hai.
(function () {
  var p = document.createElement('div');
  p.className = 'sr-net'; p.textContent = 'नेट नहीं है — दबाया हुआ दर्ज नहीं होगा';
  function dekho() { if (navigator.onLine) p.remove(); else document.body.appendChild(p); }
  window.addEventListener('online', dekho);
  window.addEventListener('offline', dekho);
  dekho();
})();

<?php if ($duty_tak && !$kaam): ?>
// ---- हाज़िर हैं पर काम नहीं — हर मिनट खुद देख लीजिए ----
// Sirf tabhi jab rider haazir ho AUR koi kaam na ho. Kaam khula
// ho to page refresh karna khatarnak hai — likha hua OTP ud jata.
setTimeout(function () { location.reload(); }, 60000);
<?php endif; ?>
</script>

<?php sr_shell_foot('kaam', ['kaam' => count($kaam)]); ?>
