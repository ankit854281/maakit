<?php
// ============================================================
//  सारथी — पार्सल भेजिए (grahak ke liye pick & drop)
//
//  Grahak seedha yahan se kaam bookh karta hai. Ye wahi "jobs"
//  table me jaata hai jahan Maakit (API) ke kaam jaate hain —
//  client = "सीधे ग्राहक (वेबसाइट)" (apne aap banta hai). Fee wahi
//  jo admin me us client ki rakhi hai; 0 ho to "फ़ोन पर बताएँगे".
//
//  Do code milte hain:
//    pick_otp — uthate waqt rider ko bataiye (bhejne wala)
//    drop_code — paane wala rider ko batata hai, tabhi "pahuncha"
// ============================================================
require_once __DIR__ . '/inc/public.php';
require_once __DIR__ . '/inc/client.php';

$err = ''; $job = null;
$client = sr_direct_client($pdo);
$fee = (int)$client['fee'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_ok()) {
        $err = t('Page expired — please submit again.', 'पेज पुराना हो गया — दोबारा भेजिए।');
    } elseif (post('website') !== '') {
        $err = t('Please try again.', 'दोबारा कोशिश कीजिए।');
    } else {
        $pm = preg_replace('/\D/', '', (string)post('pick_mobile'));
        $same = post('same') === '1';
        if (!preg_match('/^[6-9]\d{9}$/D', $pm)) $err = t('Your mobile number must be 10 digits.', 'अपना मोबाइल नंबर 10 अंकों का लिखिए।');
        elseif (mb_strlen(trim((string)post('pick_address'))) < 3) $err = t('Write the pickup address.', 'सामान कहाँ से उठाना है, लिखिए।');
        elseif (mb_strlen(trim((string)post('items'))) < 2 && post('ptype') === '') $err = t('Choose what you are sending.', 'क्या भेजना है, चुनिए।');
        elseif (!sr_public_limit('book', 8)) $err = t('Too many bookings from this phone. Please call us.', 'इस फ़ोन से बहुत बुकिंग हुई। हमें फ़ोन कीजिए।');
        else {
            // ek hi form do baar dabane par ek hi kaam (ref = form ka token)
            $ref = 'W-' . substr(preg_replace('/[^A-Za-z0-9]/', '', (string)post('form_id')), 0, 20);
            if (strlen($ref) < 10) $ref = 'W-' . bin2hex(random_bytes(8));
            $types = ['tiffin' => t('Tiffin', 'टिफ़िन'), 'docs' => t('Documents', 'काग़ज़ात'), 'medicine' => t('Medicine', 'दवा'), 'shop' => t('Shop goods', 'दुकान का सामान'), 'clothes' => t('Clothes', 'कपड़े'), 'other' => t('Other', 'और कुछ')];
            $sizes = ['s' => t('Small (fits in a bag)', 'छोटा (थैले में)'), 'm' => t('Medium (box)', 'मध्यम (डिब्बा)'), 'l' => t('Large / heavy', 'बड़ा / भारी')];
            $items = trim((string)post('items'));
            $ptype = $types[post('ptype')] ?? '';
            if ($ptype !== '' && mb_stripos($items, $ptype) === false) $items = trim($ptype . ($items !== '' ? ' — ' . $items : ''));
            $noteBits = array_filter([
                isset($sizes[post('psize')]) ? t('Size', 'आकार') . ': ' . $sizes[post('psize')] : '',
                post('when') === 'later' && preg_match('/^\d{2}:\d{2}$/', (string)post('at')) ? t('Pick up at', 'उठाने का समय') . ' ' . post('at') : '',
                preg_match('/^-?\d{1,2}\.\d+,-?\d{1,3}\.\d+$/', (string)post('pick_geo')) ? t('Pickup map', 'उठाने की जगह') . ': https://maps.google.com/?q=' . post('pick_geo') : '',
                trim((string)post('note')),
            ]);
            $_POST['items'] = $items;
            $_POST['note'] = implode(' · ', $noteBits);
            [$ok, $out] = job_create($pdo, $client, [
                'ref' => $ref,
                'pick_name' => trim((string)post('pick_name')), 'pick_mobile' => $pm, 'pick_address' => trim((string)post('pick_address')),
                'drop_name' => $same ? trim((string)post('pick_name')) : trim((string)post('drop_name')),
                'drop_mobile' => $same ? $pm : (string)post('drop_mobile'),
                'drop_address' => trim((string)post('drop_address')), 'drop_village' => trim((string)post('drop_village')),
                'items' => trim((string)post('items')), 'note' => trim((string)post('note')),
            ]);
            if ($ok) {
                $st = $pdo->prepare("SELECT * FROM jobs WHERE client_id=? AND client_ref=?"); $st->execute([(int)$client['id'], $ref]);
                $job = $st->fetch();
                $_SESSION['sr_track'] = ['no' => $job['job_no'], 'm' => $pm];
            } else {
                $map = ['drop_name' => t('Write the receiver’s name.', 'पाने वाले का नाम लिखिए।'), 'drop_mobile' => t('Receiver’s mobile must be 10 digits.', 'पाने वाले का मोबाइल 10 अंकों का लिखिए।'), 'drop_address' => t('Write the delivery address.', 'सामान कहाँ पहुँचाना है, लिखिए।')];
                $err = t('Please check the form.', 'फ़ॉर्म जाँचिए।');
                foreach ($map as $k => $m) if (strpos((string)$out, $k) === 0) { $err = $m; break; }
            }
        }
    }
}

sr_public_head('book', t('Send a parcel', 'पार्सल भेजिए'), t('PICK UP · DROP · SAME DAY', 'उठाना · पहुँचाना · उसी दिन'),
    t('Send anything,', 'कुछ भी भेजिए,'), t('anywhere nearby.', 'आस-पास कहीं भी।'),
    t('Tiffin, documents, medicine or shop goods — a verified Sarathi rider picks up from your door and delivers.', 'टिफ़िन, काग़ज़, दवा या दुकान का सामान — जाँचा-परखा सारथी राइडर आपके दरवाज़े से उठाकर पहुँचाता है।'),
    [['bolt', t('Rider in minutes', 'मिनटों में राइडर')], ['check', t('Code-safe pickup & drop', 'कोड से पक्की डिलीवरी')], ['route', t('Live tracking', 'लाइव ट्रैकिंग')]]);
$csrf = h(csrf());
?>
<?php if ($job): ?>
  <?php
    $trackUrl = (isset($_SERVER['HTTP_HOST']) ? ((!empty($_SERVER['HTTPS']) ? 'https://' : 'http://') . $_SERVER['HTTP_HOST']) : '') . u('/track.php?no=' . urlencode($job['job_no']));
    $waText = t('Your parcel is coming via Sarathi. Booking ', 'आपका पार्सल सारथी से आ रहा है। बुकिंग ') . $job['job_no'] . '. ' . t('Tell this code to the rider at delivery: ', 'पहुँचने पर राइडर को यह कोड बताइए: ') . $job['drop_code'] . '. ' . t('Track: ', 'ट्रैक: ') . $trackUrl;
  ?>
  <div class="sx-card sx-done">
    <div class="sx-done-ic"><?= sr_icon('check', 30) ?></div>
    <h2><?= h(t('Pickup booked!', 'बुकिंग हो गई!')) ?></h2>
    <p><?= $job['rider_id'] ? h(t('A rider is assigned and will call you shortly.', 'राइडर मिल गया है, थोड़ी देर में फ़ोन करेगा।')) : h(t('We are assigning a rider. You will get a call.', 'राइडर लगाया जा रहा है। आपको फ़ोन आएगा।')) ?></p>
    <div class="sx-bno"><span><?= h(t('Booking no.', 'बुकिंग नं.')) ?></span><b><?= h($job['job_no']) ?></b><button type="button" class="sx-ic-btn" data-copy="<?= h($job['job_no']) ?>" aria-label="<?= h(t('Copy', 'कॉपी')) ?>"><?= sr_icon('copy', 16) ?></button></div>
    <div class="sx-codes">
      <div><small><?= h(t('Pickup code', 'उठाने का कोड')) ?></small><b><?= h($job['pick_otp']) ?></b><span><?= h(t('Tell the rider when handing over', 'सामान देते समय राइडर को बताइए')) ?></span></div>
      <div class="b"><small><?= h(t('Delivery code', 'पहुँचाने का कोड')) ?></small><b><?= h($job['drop_code']) ?></b><span><?= h(t('Receiver tells the rider', 'पाने वाला राइडर को बताए')) ?></span></div>
    </div>
    <a class="sx-btn sx-btn-wa" href="https://wa.me/<?= $job['drop_mobile'] !== $job['pick_mobile'] ? '91' . h($job['drop_mobile']) : '' ?>?text=<?= h(rawurlencode($waText)) ?>" target="_blank" rel="noopener"><?= sr_icon('wa', 20) ?> <?= h(t('Send code to receiver on WhatsApp', 'पाने वाले को WhatsApp पर कोड भेजिए')) ?></a>
    <a class="sx-btn sx-btn-navy" href="<?= h(u('/track.php?no=' . urlencode($job['job_no']) . '&m=' . urlencode($job['pick_mobile']))) ?>"><?= sr_icon('route', 20) ?> <?= h(t('Track live', 'लाइव ट्रैक कीजिए')) ?></a>
    <p class="sx-fine"><?= $fee > 0 ? h(t('Delivery charge', 'डिलीवरी चार्ज')) . ' ₹' . $fee . ' · ' : h(t('Charge is confirmed on the call', 'चार्ज फ़ोन पर पक्का होगा')) . ' · ' ?><a href="<?= h(u('/book.php')) ?>"><?= h(t('Book another', 'एक और बुक कीजिए')) ?></a></p>
  </div>
<?php else: ?>
  <?php if ($err): ?><div class="sx-alert"><?= sr_icon('alert', 18) ?> <?= h($err) ?></div><?php endif; ?>
  <form method="post" class="sx-form sx-wiz" data-wiz novalidate>
    <input type="hidden" name="csrf" value="<?= $csrf ?>">
    <input type="hidden" name="form_id" value="<?= h(post('form_id') ?: bin2hex(random_bytes(8))) ?>">
    <input type="hidden" name="pick_geo" value="<?= h(post('pick_geo')) ?>" data-geo>
    <input type="text" name="website" value="" tabindex="-1" autocomplete="off" class="sx-hp" aria-hidden="true">

    <div class="sx-steps" aria-hidden="true"><i class="on"></i><i></i><i></i><span data-step-label><?= h(t('Step 1 of 3', 'चरण 1 / 3')) ?></span></div>

    <!-- 1. Pickup -->
    <section class="sx-card sx-pane" data-pane="1">
      <div class="sx-pane-h"><span class="sx-pin a">A</span><div><b><?= h(t('Pickup', 'कहाँ से उठाना है')) ?></b><small><?= h(t('Where should the rider come?', 'राइडर कहाँ आए?')) ?></small></div></div>
      <div class="sx-grid2">
        <label class="sx-field"><span><?= h(t('Your name', 'आपका नाम')) ?></span><input name="pick_name" value="<?= h(post('pick_name')) ?>" autocomplete="name" required data-keep="pn"></label>
        <label class="sx-field"><span><?= h(t('Your mobile', 'आपका मोबाइल')) ?></span><input name="pick_mobile" type="tel" inputmode="numeric" value="<?= h(post('pick_mobile')) ?>" autocomplete="tel-national" required pattern="[6-9][0-9]{9}" data-keep="pm"></label>
      </div>
      <label class="sx-field"><span><?= h(t('Pickup address', 'उठाने का पता')) ?></span>
        <textarea name="pick_address" rows="2" required placeholder="<?= h(t('House, mohalla, village, landmark', 'घर, मोहल्ला, गाँव, पहचान')) ?>" data-keep="pa"><?= h(post('pick_address')) ?></textarea></label>
      <button type="button" class="sx-gps" data-gps><?= sr_icon('pin', 18) ?><span><?= h(t('Use my current location', 'मेरी अभी की लोकेशन लगाइए')) ?></span><i></i></button>
      <button type="button" class="sx-btn sx-btn-navy" data-next><?= h(t('Next: Drop', 'आगे: कहाँ पहुँचाना है')) ?> <?= sr_icon('arrow', 18) ?></button>
    </section>

    <!-- 2. Drop -->
    <section class="sx-card sx-pane" data-pane="2">
      <div class="sx-pane-h"><span class="sx-pin b">B</span><div><b><?= h(t('Drop', 'कहाँ पहुँचाना है')) ?></b><small><?= h(t('Who receives it?', 'सामान किसे मिलेगा?')) ?></small></div></div>
      <label class="sx-switch-row"><input type="checkbox" name="same" value="1" data-same <?= post('same') === '1' ? 'checked' : '' ?>><span class="sx-sw"></span><span><?= h(t('I will receive it myself', 'मैं ख़ुद लूँगा / लूँगी')) ?></span></label>
      <div class="sx-grid2" data-recv>
        <label class="sx-field"><span><?= h(t('Receiver’s name', 'पाने वाले का नाम')) ?></span><input name="drop_name" value="<?= h(post('drop_name')) ?>" data-dn></label>
        <label class="sx-field"><span><?= h(t('Receiver’s mobile', 'पाने वाले का मोबाइल')) ?></span><input name="drop_mobile" type="tel" inputmode="numeric" value="<?= h(post('drop_mobile')) ?>" pattern="[6-9][0-9]{9}" data-dm></label>
      </div>
      <button type="button" class="sx-link-btn" data-contacts hidden><?= sr_icon('user', 16) ?> <?= h(t('Pick from contacts', 'कॉन्टैक्ट से चुनिए')) ?></button>
      <label class="sx-field"><span><?= h(t('Delivery address', 'पहुँचाने का पता')) ?></span>
        <textarea name="drop_address" rows="2" required placeholder="<?= h(t('House, mohalla, landmark', 'घर, मोहल्ला, पहचान')) ?>" data-da><?= h(post('drop_address')) ?></textarea></label>
      <div class="sx-recent" data-recent hidden><small><?= h(t('Recent', 'पिछले पते')) ?></small><div></div></div>
      <label class="sx-field"><span><?= h(t('Village / town', 'गाँव / कस्बा')) ?></span><input name="drop_village" value="<?= h(post('drop_village')) ?>" data-dv></label>
      <div class="sx-nav2"><button type="button" class="sx-btn sx-btn-ghost" data-back><?= h(t('Back', 'पीछे')) ?></button><button type="button" class="sx-btn sx-btn-navy" data-next><?= h(t('Next: Parcel', 'आगे: सामान')) ?> <?= sr_icon('arrow', 18) ?></button></div>
    </section>

    <!-- 3. Parcel + confirm -->
    <section class="sx-card sx-pane" data-pane="3">
      <div class="sx-pane-h"><span class="sx-pin c"><?= sr_icon('box', 16) ?></span><div><b><?= h(t('Parcel', 'क्या भेजना है')) ?></b><small><?= h(t('Choose the closest match', 'सबसे मिलता-जुलता चुनिए')) ?></small></div></div>
      <div class="sx-tiles">
        <?php foreach (['tiffin' => ['🍱', t('Tiffin', 'टिफ़िन')], 'docs' => ['📄', t('Documents', 'काग़ज़')], 'medicine' => ['💊', t('Medicine', 'दवा')], 'shop' => ['🛍️', t('Shop goods', 'दुकान का सामान')], 'clothes' => ['👕', t('Clothes', 'कपड़े')], 'other' => ['📦', t('Other', 'और कुछ')]] as $k => [$em, $lbl]): ?>
          <label><input type="radio" name="ptype" value="<?= h($k) ?>" <?= post('ptype') === $k ? 'checked' : '' ?>><span><i><?= $em ?></i><?= h($lbl) ?></span></label>
        <?php endforeach; ?>
      </div>
      <div class="sx-seg3">
        <?php foreach (['s' => t('Small', 'छोटा'), 'm' => t('Medium', 'मध्यम'), 'l' => t('Large', 'बड़ा')] as $k => $lbl): ?>
          <label><input type="radio" name="psize" value="<?= h($k) ?>" <?= post('psize', 's') === $k ? 'checked' : '' ?>><span><?= h($lbl) ?></span></label>
        <?php endforeach; ?>
      </div>
      <label class="sx-field"><span><?= h(t('Details (optional)', 'और जानकारी (मर्ज़ी से)')) ?></span><input name="items" value="<?= h(post('items')) ?>" placeholder="<?= h(t('e.g. 2 tiffin boxes, keep upright', 'जैसे 2 टिफ़िन, सीधा रखें')) ?>"></label>
      <div class="sx-seg3 two">
        <label><input type="radio" name="when" value="now" <?= post('when', 'now') === 'now' ? 'checked' : '' ?> data-when><span><?= sr_icon('bolt', 16) ?> <?= h(t('Now', 'अभी')) ?></span></label>
        <label><input type="radio" name="when" value="later" <?= post('when') === 'later' ? 'checked' : '' ?> data-when><span><?= sr_icon('clock', 16) ?> <?= h(t('Later today', 'आज बाद में')) ?></span></label>
      </div>
      <label class="sx-field" data-at hidden><span><?= h(t('Pickup time', 'उठाने का समय')) ?></span><input type="time" name="at" value="<?= h(post('at')) ?>"></label>
      <label class="sx-field"><span><?= h(t('Note for rider (optional)', 'राइडर के लिए बात (मर्ज़ी से)')) ?></span><input name="note" value="<?= h(post('note')) ?>" maxlength="200" placeholder="<?= h(t('e.g. fragile, call before coming', 'जैसे टूटने वाला है, आने से पहले फ़ोन करें')) ?>"></label>

      <div class="sx-sum" data-sum></div>
      <div class="sx-fee"><span><?= h(t('Delivery charge', 'डिलीवरी चार्ज')) ?></span><b><?= $fee > 0 ? '₹' . $fee : h(t('Confirmed on call', 'फ़ोन पर पक्का')) ?></b></div>
      <div class="sx-nav2"><button type="button" class="sx-btn sx-btn-ghost" data-back><?= h(t('Back', 'पीछे')) ?></button>
        <button type="submit" class="sx-btn sx-btn-mint"><?= sr_icon('bike', 20) ?> <?= h(t('Book pickup', 'उठवाइए')) ?></button></div>
      <p class="sx-fine"><?= h(t('Not allowed: alcohol, cash, gold, illegal items.', 'नहीं भेज सकते: शराब, नगद, सोना, ग़ैरक़ानूनी सामान।')) ?></p>
    </section>
  </form>
<?php endif; ?>
<script>
(function () {
  var L = <?= json_encode([
    'step' => t('Step {n} of 3', 'चरण {n} / 3'), 'need' => t('Please fill the highlighted fields', 'लाल वाले खाने भरिए'),
    'finding' => t('Finding your location…', 'लोकेशन ढूँढ रहे हैं…'), 'nogps' => t('Location not available — please type the address', 'लोकेशन नहीं मिली — पता लिख दीजिए'),
    'got' => t('Location added ✓', 'लोकेशन लग गई ✓'), 'copied' => t('Copied', 'कॉपी हो गया'),
    'from' => t('From', 'कहाँ से'), 'to' => t('To', 'कहाँ तक'), 'what' => t('Parcel', 'सामान'), 'me' => t('Myself', 'ख़ुद'),
  ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
  document.querySelectorAll('[data-copy]').forEach(function (b) { b.addEventListener('click', function () { try { navigator.clipboard.writeText(b.dataset.copy); window.sxToast && sxToast(L.copied); } catch (e) {} }); });
  var f = document.querySelector('[data-wiz]'); if (!f) return;
  f.classList.add('js');
  var panes = f.querySelectorAll('[data-pane]'), dots = f.querySelectorAll('.sx-steps i'), lab = f.querySelector('[data-step-label]'), cur = 1;
  function show(n) {
    cur = n; panes.forEach(function (p) { p.hidden = +p.dataset.pane !== n; });
    dots.forEach(function (d, i) { d.classList.toggle('on', i < n); });
    lab.textContent = L.step.replace('{n}', n);
    if (n === 3) summary();
    var top = f.getBoundingClientRect().top + window.scrollY - 70; if (window.scrollY > top) window.scrollTo({ top: top, behavior: 'smooth' });
  }
  function valid(p) {
    var ok = true;
    p.querySelectorAll('input,textarea').forEach(function (i) { if (i.closest('[hidden]')) return; var bad = !i.checkValidity(); i.closest('.sx-field') && i.closest('.sx-field').classList.toggle('bad', bad); if (bad && ok) { ok = false; i.focus(); } });
    if (!ok) window.sxToast && sxToast(L.need);
    return ok;
  }
  f.querySelectorAll('[data-next]').forEach(function (b) { b.addEventListener('click', function () { if (valid(panes[cur - 1])) show(cur + 1); }); });
  f.querySelectorAll('[data-back]').forEach(function (b) { b.addEventListener('click', function () { show(cur - 1); }); });
  // server ne galti batayi ho to pehla adhoora chaaran khol do
  var start = 1; <?php if ($err): ?>start = 3;<?php endif; ?> show(start);

  // yaad rakhna (sirf is phone me)
  try { f.querySelectorAll('[data-keep]').forEach(function (i) { var v = localStorage.getItem('sx_' + i.dataset.keep); if (v && !i.value) i.value = v; }); } catch (e) {}

  // receiver = main
  var same = f.querySelector('[data-same]'), recv = f.querySelector('[data-recv]'), dn = f.querySelector('[data-dn]'), dm = f.querySelector('[data-dm]'), cbtn = f.querySelector('[data-contacts]');
  function syncSame() { recv.hidden = same.checked; dn.required = dm.required = !same.checked; if (cbtn) cbtn.hidden = same.checked || !('contacts' in navigator && 'select' in navigator.contacts); }
  same.addEventListener('change', syncSame); syncSame();
  if (cbtn) cbtn.addEventListener('click', function () {
    navigator.contacts.select(['name', 'tel'], { multiple: false }).then(function (r) {
      if (!r || !r[0]) return; if (r[0].name && r[0].name[0]) dn.value = r[0].name[0];
      if (r[0].tel && r[0].tel[0]) { var d = r[0].tel[0].replace(/\D/g, ''); if (d.length > 10) d = d.slice(-10); dm.value = d; }
    }).catch(function () {});
  });

  // GPS se pickup pata
  var gps = f.querySelector('[data-gps]'), pa = f.querySelector('[name=pick_address]'), geo = f.querySelector('[data-geo]');
  gps.addEventListener('click', function () {
    if (!navigator.geolocation) { sxToast(L.nogps); return; }
    gps.classList.add('busy'); gps.querySelector('span').dataset.t = gps.querySelector('span').textContent; gps.querySelector('span').textContent = L.finding;
    navigator.geolocation.getCurrentPosition(function (p) {
      var lat = p.coords.latitude.toFixed(6), lng = p.coords.longitude.toFixed(6); geo.value = lat + ',' + lng;
      fetch('https://nominatim.openstreetmap.org/reverse?format=jsonv2&zoom=18&accept-language=<?= sr_lang() ?>&lat=' + lat + '&lon=' + lng)
        .then(function (r) { return r.json(); }).then(function (j) {
          var a = j && j.address || {}; var parts = [a.house_number, a.road, a.neighbourhood || a.suburb || a.hamlet, a.village || a.town || a.city, a.postcode].filter(Boolean);
          if (parts.length && !pa.value.trim()) pa.value = parts.join(', ');
        }).catch(function () {}).then(function () { gps.classList.remove('busy'); gps.classList.add('done'); gps.querySelector('span').textContent = L.got; });
    }, function () { gps.classList.remove('busy'); gps.querySelector('span').textContent = gps.querySelector('span').dataset.t; sxToast(L.nogps); }, { enableHighAccuracy: true, timeout: 12000 });
  });

  // pichhle drop pate
  var rec = []; try { rec = JSON.parse(localStorage.getItem('sx_recent') || '[]'); } catch (e) {}
  var rbox = f.querySelector('[data-recent]');
  if (rec.length) { rbox.hidden = false; rec.slice(0, 3).forEach(function (r) { var b = document.createElement('button'); b.type = 'button'; b.textContent = (r.n ? r.n + ' · ' : '') + r.a; b.addEventListener('click', function () { f.querySelector('[data-da]').value = r.a; if (r.v) f.querySelector('[data-dv]').value = r.v; if (r.n && !same.checked) { dn.value = r.n; dm.value = r.m || ''; } }); rbox.querySelector('div').appendChild(b); }); }

  // samay
  var at = f.querySelector('[data-at]');
  f.querySelectorAll('[data-when]').forEach(function (r) { r.addEventListener('change', function () { at.hidden = !(r.checked && r.value === 'later'); }); if (r.checked && r.value === 'later') at.hidden = false; });

  function summary() {
    var t = f.querySelector('[name=ptype]:checked');
    f.querySelector('[data-sum]').innerHTML =
      '<div><small>' + L.from + '</small><b>' + esc(f.pick_address.value) + '</b></div>' +
      '<div><small>' + L.to + '</small><b>' + esc((same.checked ? L.me : dn.value) + ' · ' + f.drop_address.value) + '</b></div>';
  }
  function esc(s) { var d = document.createElement('div'); d.textContent = s || ''; return d.innerHTML; }

  f.addEventListener('submit', function (e) {
    if (!f.querySelector('[name=ptype]:checked') && !f.items.value.trim()) { e.preventDefault(); sxToast(L.need); return; }
    try {
      f.querySelectorAll('[data-keep]').forEach(function (i) { localStorage.setItem('sx_' + i.dataset.keep, i.value); });
      var r = { a: f.drop_address.value.trim(), v: f.drop_village.value.trim(), n: same.checked ? '' : dn.value.trim(), m: same.checked ? '' : dm.value.trim() };
      if (r.a) { rec = rec.filter(function (x) { return x.a !== r.a; }); rec.unshift(r); localStorage.setItem('sx_recent', JSON.stringify(rec.slice(0, 5))); }
    } catch (x) {}
  });
})();
</script>
<?php sr_public_foot();
