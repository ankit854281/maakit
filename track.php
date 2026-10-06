<?php
require_once __DIR__ . '/inc/fn.php';
require_once __DIR__ . '/inc/dakiya.php';
require_once __DIR__ . '/inc/items.php';
require_once __DIR__ . '/inc/services.php';

$page_title = t('My orders — Maakit', 'मेरे ऑर्डर — Maakit');
$tab = 'mere';

$no  = strtoupper(trim(get('no', post('no'))));
$bno = strtoupper(trim(get('b', post('b'))));
$mob = preg_replace('/\D/', '', get('m', post('m')));
$o = null; $bk = null; $err = ''; $steps = [];

// khate me hain to apne order ka number bharne ki zaroorat nahi
$me = cust();
if ($me && $mob === '') { $mob = $me['mobile']; }

// ek session me 30 baar se zyada dekhna band — galat number aazmane se bachav
$_SESSION['tr'] = (int)($_SESSION['tr'] ?? 0);

if ($no !== '' && strlen($mob) === 10) {
    if ($_SESSION['tr'] > 30) {
        $err = t('Too many tries. Please wait a while, or call us.', 'बहुत बार कोशिश हो गई। थोड़ी देर बाद देखिए या हमें कॉल कीजिए।');
    } else {
        $_SESSION['tr']++;
        $st = $pdo->prepare("SELECT * FROM orders WHERE order_no=? AND mobile=? LIMIT 1");
        $st->execute([$no, $mob]);
        $o = $st->fetch();
        if (!$o) { $err = t('That order number and mobile do not match. Please check both.', 'यह ऑर्डर नंबर और मोबाइल मिल नहीं रहा। दोनों एक बार देख लीजिए।'); }
        else { $steps = order_steps($o['status'], $o); }
    }
} elseif ($bno !== '' && strlen($mob) === 10) {
    $_SESSION['tr']++;
    if ($_SESSION['tr'] > 30) { $err = t('Too many tries. Please wait a while.', 'बहुत बार कोशिश हो गई। थोड़ी देर बाद देखिए।'); }
    else {
        $st = $pdo->prepare("SELECT * FROM service_bookings WHERE booking_no=? AND mobile=? LIMIT 1");
        $st->execute([$bno, $mob]);
        $bk = $st->fetch();
        if (!$bk) { $err = t('That booking number and mobile do not match.', 'यह बुकिंग नंबर और मोबाइल मिल नहीं रहा।'); }
        else { $steps = booking_steps($bk['status']); }
    }
} elseif ($no !== '' || $bno !== '' || $mob !== '') {
    if ($no === '' && $bno === '') { $err = t('Enter an order or booking number.', 'ऑर्डर या बुकिंग नंबर लिखिए।'); }
    elseif (strlen($mob) !== 10) { $err = t('Mobile number must be 10 digits.', 'मोबाइल नंबर 10 अंकों का लिखिए।'); }
}

// ऑर्डर कैंसिल (सिर्फ़ जब तक नया है)
if ($o && post('act') === 'cancel' && csrf_ok() && $o['status'] === 'Naya') {
    $pdo->prepare("UPDATE orders SET status='Cancel', note=CONCAT(COALESCE(note,''),'\n[customer ne website se cancel kiya]') WHERE id=?")
        ->execute([$o['id']]);
    flash(t('Your order has been cancelled.', 'आपका ऑर्डर कैंसिल कर दिया गया।'));
    redirect('/track.php?no=' . urlencode($no) . '&m=' . urlencode($mob));
}

$lines = $o ? (json_decode((string)$o['items_json'], true) ?: []) : [];
include __DIR__ . '/inc/head.php';
?>
<div class="wrap" style="max-width:640px">

<?php if ($o): ?>
  <!-- ================= एक ऑर्डर का सफ़र ================= -->
  <?php list($pc, $pl) = status_pill($o['status']); ?>
  <div style="padding-top:18px">
    <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
      <h2 style="margin:0"><?= h($o['order_no']) ?></h2>
      <span class="pill <?= h($pc) ?>"><?= h($pl) ?></span>
    </div>
    <p class="lead" style="margin:4px 0 0"><?= date('d/m/Y, h:i A', strtotime($o['created_at'])) ?> · <?= h($o['village']) ?></p>
  </div>

  <?php if ($o['status'] === 'Cancel'): ?>
    <div class="err" style="margin-top:16px"><?= t('This order was cancelled.', 'यह ऑर्डर कैंसिल हो गया है।') ?> <a href="/order.php"><?= t('Place a new one', 'नया ऑर्डर कीजिए') ?></a>.</div>
  <?php else: ?>
    <div class="box" style="margin-top:16px">
      <h3 style="margin:0 0 12px;font-size:18px"><?= t('Order journey', 'ऑर्डर का सफ़र') ?></h3>
      <div class="safar">
        <?php foreach ($steps as $s): ?>
          <div class="s <?= h($s['state']) ?>">
            <b><?= h($s['title']) ?></b>
            <span><?= h($s['sub']) ?></span>
            <?php if (!empty($s['when'])): ?><em><?= h($s['when']) ?></em><?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <?php if (in_array($o['status'], ['Assign','Pickup'], true)): ?>
      <div class="box" id="liveBox" style="margin-top:14px;display:none">
        <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:10px">
          <h3 style="margin:0;font-size:18px"><?= t('Where your order is', 'आपका सामान कहाँ है') ?></h3>
          <span class="liveon"><i></i> <?= t('LIVE', 'अभी') ?></span>
        </div>
        <div class="livemap"><iframe id="liveFrame" title="<?= h(t('Live map', 'चलता हुआ नक्शा')) ?>" loading="lazy"></iframe></div>
        <div class="distbar">
          <div><div class="d" id="liveD">—</div><div class="l" id="liveL"><?= t('away from you', 'आपसे दूर') ?></div></div>
          <div class="r"><div style="font-weight:700" id="liveR"></div>
            <div class="l" id="liveT"></div></div>
        </div>
        <p class="help" style="margin-top:8px"><?= t(
          'Updates every few seconds while the delivery partner is on the way.',
          'डिलीवरी पार्टनर जब तक रास्ते में हैं, यह अपने आप चलता रहेगा।') ?></p>
      </div>
      <div class="box" id="liveOff" style="margin-top:14px;display:none">
        <b><?= t('Live location is off right now', 'अभी जगह नहीं दिख रही') ?></b>
        <p class="help" style="margin-top:4px"><?= t(
          'The delivery partner has not started sharing yet, or the network dropped. Call them if you need to.',
          'डिलीवरी पार्टनर ने अभी शुरू नहीं किया, या नेट चला गया। ज़रूरत हो तो कॉल कर लीजिए।') ?></p>
      </div>
    <?php endif; ?>

    <?php if (!in_array($o['status'], ['Delivered','Paisa jama'], true)): ?>
      <div class="codebox">
        <div class="l"><?= t('DELIVERY CODE', 'डिलीवरी कोड') ?></div>
        <div class="c"><?php foreach (str_split($o['code']) as $d): ?><span><?= h($d) ?></span><?php endforeach; ?></div>
        <div class="h"><?= t('Say this code to the delivery partner.<br>Do not share it with anyone else.', 'सामान लेते समय यही कोड डिलीवरी पार्टनर को बताइए।<br>किसी और को मत बताइए।') ?></div>
      </div>
    <?php else: ?>
      <!-- Saaman pahunch gaya — yahi sabse achha pal hai batane ka -->
      <div class="box" style="margin-top:14px;text-align:center">
        <b style="font-size:16px"><?= t('Did it reach you fine?', 'सामान ठीक पहुँच गया?') ?></b>
        <p class="help" style="margin:5px 0 11px"><?= t(
            'If it did, tell someone in your village. That is how Maakit grows.',
            'तो गाँव में किसी को बता दीजिए। Maakit ऐसे ही बढ़ता है।') ?></p>
        <button type="button" class="btn btn-brand btn-sm" id="dostBtn"><?= t('Tell a friend', 'दोस्त को भेजिए') ?></button>
      </div>
      <script>
      (function(){
        var b = document.getElementById('dostBtn');
        if (!b) return;
        var msg = <?= json_encode(dak_dost(), JSON_UNESCAPED_UNICODE) ?>;
        b.addEventListener('click', function(){
          if (navigator.share) { navigator.share({ text: msg }).catch(function(){}); return; }
          window.open('https://wa.me/?text=' + encodeURIComponent(msg), '_blank', 'noopener');
        });
      })();
      </script>
    <?php endif; ?>
  <?php endif; ?>

  <div class="box" style="margin-top:14px">
    <h3 style="margin:0 0 6px;font-size:18px"><?= t('Your items', 'आपका सामान') ?></h3>
    <?php if ($lines): ?>
      <?php foreach ($lines as $l): ?>
        <div class="crow">
          <div class="ci"><?php $ph = item_photo_of($pdo, $l['id'] ?? 0);
  echo $ph ? '<img src="/uploads/' . h($ph) . '" alt="" class="pimg">' : prod_icon_by_key($l['ic'] ?? 'bag', 22); ?></div>
          <div class="cn"><b><?= h($l['name'] ?? '') ?></b><span><?= h($l['unit'] ?? '') ?></span></div>
          <div style="font-weight:800;color:var(--brand)">× <?= (int)($l['q'] ?? 1) ?></div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
    <?php if (!empty($o['goods_note'])): ?>
      <div class="note" style="margin-top:10px"><b><?= t('You wrote:', 'आपने लिखा:') ?></b><br><?= nl2br(h($o['goods_note'])) ?></div>
    <?php elseif (!$lines): ?>
      <div class="note" style="margin-top:6px"><?= nl2br(h($o['items'])) ?></div>
    <?php endif; ?>
  </div>

  <?php if (!empty($o['bill_photo'])): ?>
    <div class="box" style="margin-top:14px">
      <h3 style="margin:0 0 4px;font-size:18px"><?= t('Shop bill', 'दुकान का बिल') ?></h3>
      <p class="help" style="margin:0 0 10px"><?= t('This is the slip the shop gave. Please check the amounts.', 'यही पर्ची दुकान ने दी है। दाम मिला लीजिए।') ?></p>
      <a href="/uploads/<?= h($o['bill_photo']) ?>" target="_blank" rel="noopener">
        <img src="/uploads/<?= h($o['bill_photo']) ?>" alt="" class="proof"></a>
    </div>
  <?php endif; ?>

  <?php if (!empty($o['photo'])): ?>
    <div class="box" style="margin-top:14px">
      <h3 style="margin:0 0 10px;font-size:18px"><?= t('The photo you sent', 'आपकी भेजी फ़ोटो') ?></h3>
      <img src="/uploads/<?= h($o['photo']) ?>" alt="" class="proof">
    </div>
  <?php endif; ?>

  <div class="box" style="margin-top:14px">
    <div class="sticky-tot" style="background:transparent;padding:0">
      <div class="r"><span><?= t('Goods', 'सामान का दाम') ?></span><b><?= $o['goods_amount'] !== null ? '₹' . (int)$o['goods_amount'] : t('as per shop bill', 'दुकान की पर्ची से') ?></b></div>
      <div class="r"><span><?= t('Delivery charge', 'डिलीवरी चार्ज') ?></span><b>
        <?php if ($o['delivery_charge'] === null): ?><?= t('on call', 'कॉल पर') ?>
        <?php elseif ((int)$o['first_order'] === 1): ?><span class="freebadge"><?= t('First delivery FREE', 'पहली डिलीवरी फ़्री') ?></span><?= (int)$o['delivery_charge'] ? ' + ₹' . (int)$o['delivery_charge'] . ' (' . t('weight/size', 'वज़न/आकार') . ')' : '' ?>
        <?php else: ?>₹<?= (int)$o['delivery_charge'] ?><?php endif; ?>
      </b></div>
      <div class="r"><span><?= t('Payment', 'पेमेंट') ?></span><b><?= h($o['payment'] ?: '—') ?></b></div>
      <?php if ($o['shop']): ?><div class="r"><span><?= t('Shop', 'दुकान') ?></span><b><?= h($o['shop']) ?></b></div><?php endif; ?>
    </div>
  </div>

  <div style="display:grid;gap:9px;margin-top:14px">
    <a class="btn btn-green" href="<?= h(wa_link(MAAKIT_WA, "नमस्ते Maakit, मेरे ऑर्डर " . $o['order_no'] . " के बारे में बात करनी है।")) ?>" target="_blank" rel="noopener"><?= t('Chat about this order', 'इस ऑर्डर पर बात कीजिए') ?></a>
    <a class="btn btn-brand" href="tel:<?= MAAKIT_PHONE ?>"><?= t('Call us', 'कॉल कीजिए') ?></a>
    <?php if ($o['status'] === 'Naya'): ?>
      <form method="post" onsubmit="return confirm('<?= h(t('Cancel this order?', 'ऑर्डर कैंसिल कर दें?')) ?>')">
        <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
        <input type="hidden" name="no" value="<?= h($no) ?>">
        <input type="hidden" name="m" value="<?= h($mob) ?>">
        <input type="hidden" name="act" value="cancel">
        <button class="btn btn-line" type="submit" style="width:100%;color:var(--bad);border-color:var(--bad)"><?= t('Cancel this order', 'ऑर्डर कैंसिल कीजिए') ?></button>
      </form>
      <p class="help" style="text-align:center;margin:0"><?= t('Once the rider has left, please call to cancel.', 'डिलीवरी पार्टनर निकलने के बाद कैंसिल के लिए कॉल कीजिए।') ?></p>
    <?php endif; ?>
  </div>

  <p style="text-align:center;margin:22px 0 30px"><a href="/track.php">← <?= t('Check another order', 'दूसरा ऑर्डर देखिए') ?></a></p>

  <?php if (in_array($o['status'], ['Assign','Pickup'], true)): ?>
  <script>
  (function(){
    var NO = <?= json_encode($o['order_no']) ?>, M = <?= json_encode($o['mobile']) ?>;
    var L = <?= json_encode([
      'away'    => t('away from you', 'आपसे दूर'),
      'reach'   => t('reaching in about', 'लगभग'),
      'mins'    => t('min', 'मिनट में'),
      'rider'   => t('Delivery partner', 'डिलीवरी पार्टनर'),
      'seen'    => t('seen', 'देखा'),
      'secago'  => t('s ago', ' सेकंड पहले'),
      'nodest'  => t('distance unknown', 'दूरी पता नहीं'),
      'onway'   => t('On the way', 'रास्ते में हैं'),
    ], JSON_UNESCAPED_UNICODE) ?>;
    var box = document.getElementById('liveBox'), off = document.getElementById('liveOff'),
        fr = document.getElementById('liveFrame'), lastSrc = '';

    function km(a1, o1, a2, o2){
      var R = 6371, p = Math.PI / 180;
      var d = 2 * R * Math.asin(Math.sqrt(
        Math.pow(Math.sin((a2 - a1) * p / 2), 2) +
        Math.cos(a1 * p) * Math.cos(a2 * p) * Math.pow(Math.sin((o2 - o1) * p / 2), 2)));
      return d;
    }
    function tick(){
      fetch('/api.php?a=where&no=' + encodeURIComponent(NO) + '&m=' + encodeURIComponent(M))
        .then(function(r){ return r.json(); })
        .then(function(d){
          if (!d || !d.ok || !d.live) { box.style.display = 'none'; off.style.display = 'block'; return; }
          off.style.display = 'none'; box.style.display = 'block';

          // naksha (OpenStreetMap — koi key nahi chahiye)
          var b = 0.008;
          var src = 'https://www.openstreetmap.org/export/embed.html?bbox='
            + (d.lng - b) + '%2C' + (d.lat - b) + '%2C' + (d.lng + b) + '%2C' + (d.lat + b)
            + '&layer=mapnik&marker=' + d.lat + '%2C' + d.lng;
          if (src !== lastSrc) { fr.src = src; lastSrc = src; }

          if (d.dlat !== null && d.dlng !== null) {
            var k = km(d.lat, d.lng, d.dlat, d.dlng);
            document.getElementById('liveD').textContent = (k < 1 ? Math.round(k * 1000) + ' m' : k.toFixed(1) + ' km');
            document.getElementById('liveL').textContent = L.away;
            // gaon ki sadak par karib 20 km/ghanta
            var mins = Math.max(1, Math.round(k / 20 * 60));
            document.getElementById('liveR').textContent = L.reach + ' ' + mins + ' ' + L.mins;
          } else {
            document.getElementById('liveD').textContent = L.onway;
            document.getElementById('liveL').textContent = L.nodest;
            document.getElementById('liveR').textContent = '';
          }
          document.getElementById('liveT').textContent =
            (d.rider ? d.rider + ' · ' : '') + L.seen + ' ' + (d.age || 0) + L.secago;
        })
        .catch(function(){});
    }
    tick();
    var iv = setInterval(function(){ if (!document.hidden) tick(); }, 15000);
    document.addEventListener('visibilitychange', function(){ if (!document.hidden) tick(); });
  })();
  </script>
  <?php endif; ?>

<?php elseif ($bk): ?>
  <!-- ================= बुकिंग का सफ़र ================= -->
  <?php list($pc, $pl) = booking_pill($bk['status']); $svc = service_get($bk['service']); ?>
  <div style="padding-top:18px">
    <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
      <h2 style="margin:0"><?= h($bk['booking_no']) ?></h2>
      <span class="pill <?= h($pc) ?>"><?= h($pl) ?></span>
    </div>
    <p class="lead" style="margin:4px 0 0"><?= h($svc ? svc_name($svc) : $bk['service']) ?> ·
      <?= date('d/m/Y, h:i A', strtotime($bk['created_at'])) ?></p>
  </div>

  <?php if ($bk['status'] === 'Cancel'): ?>
    <div class="err" style="margin-top:16px"><?= t('This booking was cancelled.', 'यह बुकिंग कैंसिल हो गई है।') ?></div>
  <?php else: ?>
    <div class="box" style="margin-top:16px">
      <h3 style="margin:0 0 12px;font-size:18px"><?= t('Booking journey', 'बुकिंग का सफ़र') ?></h3>
      <div class="safar">
        <?php foreach ($steps as $s): ?>
          <div class="s <?= h($s['state']) ?>"><b><?= h($s['title']) ?></b><span><?= h($s['sub']) ?></span></div>
        <?php endforeach; ?>
      </div>
      <?php if ($bk['quote']): ?>
        <div class="note" style="margin-top:12px;display:flex;justify-content:space-between">
          <span><?= t('Agreed price', 'तय हुआ रेट') ?></span><b>₹<?= (int)$bk['quote'] ?></b></div>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <div class="box" style="margin-top:14px">
    <h3 style="margin:0 0 10px;font-size:18px"><?= t('What you told us', 'आपने क्या बताया') ?></h3>
    <div class="note"><?= nl2br(h($bk['summary'])) ?></div>
    <?php if ($bk['note']): ?><div class="note" style="margin-top:8px"><b><?= t('Also:', 'और:') ?></b><br><?= nl2br(h(trim($bk['note']))) ?></div><?php endif; ?>
  </div>

  <div style="display:grid;gap:9px;margin-top:14px">
    <a class="btn btn-green" href="<?= h(wa_link(MAAKIT_WA, "नमस्ते Maakit, मेरी बुकिंग " . $bk['booking_no'] . " के बारे में बात करनी है।")) ?>" target="_blank" rel="noopener"><?= t('Chat about this booking', 'इस बुकिंग पर बात कीजिए') ?></a>
    <a class="btn btn-brand" href="tel:<?= MAAKIT_PHONE ?>"><?= t('Call us', 'कॉल कीजिए') ?></a>
  </div>
  <p style="text-align:center;margin:22px 0 30px"><a href="/track.php">← <?= t('Check another order', 'दूसरा ऑर्डर देखिए') ?></a></p>

<?php else: ?>
  <!-- ================= मेरे ऑर्डर ================= -->
  <div style="padding-top:18px">
    <h2 style="margin:0"><?= t('My orders', 'मेरे ऑर्डर') ?></h2>
    <p class="lead"><?= t('Enter your order number and mobile — you’ll see the whole journey.', 'ऑर्डर नंबर और अपना मोबाइल नंबर डालिए — पूरा सफ़र दिख जाएगा।') ?></p>
  </div>

  <?php if ($err): ?><div class="err"><?= h($err) ?></div><?php endif; ?>

  <div id="mine"></div>

  <?php if (!$me): ?>
    <a class="box" href="/account.php" style="display:flex;align-items:center;gap:12px;text-decoration:none;color:inherit;margin-bottom:14px">
      <span style="width:44px;height:44px;border-radius:50%;background:var(--soft);color:var(--brand);display:grid;place-items:center;flex-shrink:0"><?= svc_icon('user', 22) ?></span>
      <span><b style="display:block;font-size:15.5px"><?= t('Create an account', 'खाता बना लीजिए') ?></b>
        <i style="font-style:normal;font-size:13.5px;color:var(--muted)"><?= t('Then you never need to remember numbers — everything shows up', 'फिर नंबर याद रखने की ज़रूरत नहीं — सब अपने आप दिखेगा') ?></i></span>
    </a>
  <?php endif; ?>

  <form method="get" class="box">
    <div class="field"><label for="tno"><?= t('Order or booking number', 'ऑर्डर या बुकिंग नंबर') ?></label>
      <input type="text" id="tno" name="no" value="<?= h($no) ?>" placeholder="MK-2609-01 या BK-2609-01" autocapitalize="characters" required>
      <input type="hidden" name="b" id="tb" value=""></div>
    <div class="field"><label for="tm"><?= t('Your mobile number', 'आपका मोबाइल नंबर') ?></label>
      <input type="tel" id="tm" name="m" value="<?= h($mob) ?>" inputmode="numeric" maxlength="10" required></div>
    <button class="btn btn-brand" type="submit" style="width:100%"><?= t('Show my order', 'देखिए') ?></button>
  </form>

  <div class="box" style="margin-top:14px">
    <b><?= t('Where do I find the number?', 'नंबर कहाँ मिलेगा?') ?></b>
    <p class="help" style="margin-top:4px"><?= t('It appears on screen and in your WhatsApp message — goods start with', 'ऑर्डर या बुकिंग करने पर स्क्रीन पर और WhatsApp के मैसेज में मिलता है — सामान के लिए') ?>
      <b>MK-</b> <?= t('and bookings with', 'और बुकिंग के लिए') ?> <b>BK-</b>.<br>
      <?= t('Ordered by phone? Just', 'कॉल पर किया है तो सीधे') ?> <a href="tel:<?= MAAKIT_PHONE ?>"><?= t('call', 'कॉल') ?></a> <?= t('or', 'या') ?>
      <a href="<?= h(wa_link(MAAKIT_WA, 'नमस्ते Maakit, मुझे अपने ऑर्डर की जानकारी चाहिए।')) ?>" target="_blank" rel="noopener">WhatsApp</a> कीजिए।</p>
  </div>

  <div style="margin:18px 0 30px"><a class="btn btn-gold" href="/order.php" style="width:100%"><?= t('Place a new order', 'नया ऑर्डर कीजिए') ?></a></div>

  <script>
  /* is phone se kiye gaye order aur booking — ek tap me khul jaayen */
  (function(){
    var o=[], bk=[], me={};
    try{ o=JSON.parse(localStorage.getItem('mk_orders')||'[]'); }catch(e){}
    try{ bk=JSON.parse(localStorage.getItem('mk_books')||'[]'); }catch(e){}
    try{ me=JSON.parse(localStorage.getItem('mk_me')||'{}')||{}; }catch(e){}
    var tm=document.getElementById('tm');
    if (me.m && !tm.value) tm.value = me.m;

    // BK- se shuru hone wala number booking ka hai
    var frm = tm.form, tno = document.getElementById('tno'), tb = document.getElementById('tb');
    frm.addEventListener('submit', function(){
      var v = (tno.value||'').trim().toUpperCase();
      if (v.indexOf('BK') === 0) { tb.value = v; tno.value = ''; tno.removeAttribute('required'); }
    });

    function dt(t){ var d=new Date(t||Date.now()); return d.getDate()+'/'+(d.getMonth()+1)+'/'+d.getFullYear(); }
    var LT = <?= json_encode([
      'orders'  => t('Orders from this phone', 'इस फ़ोन से किए गए ऑर्डर'),
      'books'   => t('Bookings from this phone', 'इस फ़ोन से की गई बुकिंग'),
      'kinds'   => t('kinds of items', 'तरह का सामान'),
      'tap'     => t('Tap to open', 'देखने के लिए दबाइए'),
      'booking' => t('Booking', 'बुकिंग'),
    ], JSON_UNESCAPED_UNICODE) ?>;
    var h='';
    if (o.length) {
      h += '<h3 class="ghead" style="margin-top:6px">'+LT.orders+'</h3>';
      o.slice(0,6).forEach(function(x){
        h += '<a class="ocard" href="/track.php?no='+encodeURIComponent(x.no)+'&m='+encodeURIComponent(x.m||me.m||'')+'">'
           + '<div class="hd"><span class="no">'+x.no+'</span><span class="dt">'+dt(x.t)+'</span></div>'
           + '<div class="li">'+((x.c&&x.c.length)?(x.c.length+' '+LT.kinds):LT.tap)+' →</div></a>';
      });
    }
    if (bk.length) {
      h += '<h3 class="ghead">'+LT.books+'</h3>';
      bk.slice(0,6).forEach(function(x){
        h += '<a class="ocard" href="/track.php?b='+encodeURIComponent(x.no)+'&m='+encodeURIComponent(x.m||me.m||'')+'">'
           + '<div class="hd"><span class="no">'+x.no+'</span><span class="dt">'+dt(x.t)+'</span></div>'
           + '<div class="li">'+(x.s||LT.booking)+' →</div></a>';
      });
    }
    if (h) document.getElementById('mine').innerHTML = h;
  })();
  </script>
<?php endif; ?>

</div>
<?php include __DIR__ . '/inc/foot.php'; ?>
