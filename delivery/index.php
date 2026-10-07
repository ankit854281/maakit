<?php
require_once __DIR__ . '/../inc/fn.php';
require_once __DIR__ . '/../inc/dakiya.php';
require_once __DIR__ . '/../inc/earning.php';
$u = need_role(['delivery', 'admin']);
$page_title = 'मेरे ऑर्डर — Maakit';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_ok()) {
    $id = (int)post('id'); $status = post('status');
    if ($status === 'Pickup') {
        $pickup = $pdo->prepare("UPDATE orders SET status='Pickup', picked_at=COALESCE(picked_at, NOW()) WHERE id=? AND (delivery_user=? OR ?='admin') AND status IN ('Naya','Confirm','Assign')");
        $pickup->execute([$id, $u['id'], $u['role']]);
        flash($pickup->rowCount() === 1 ? t('Pickup recorded.', 'दुकान से लिया — दर्ज हो गया।') : t('Order state or assignment changed. Refresh the list.', 'ऑर्डर की स्थिति या जिम्मेदारी बदल गई है। सूची दोबारा देखें।'));
    } elseif ($status === 'bill') {
        // Authorize and validate before storing a customer-visible bill photo.
        $own = $pdo->prepare("SELECT id FROM orders WHERE id=? AND (delivery_user=? OR ?='admin') AND status IN ('Naya','Confirm','Assign','Pickup')");
        $own->execute([$id, $u['id'], $u['role']]);
        $raw_amount = post('amount');
        $amt = $raw_amount === '' ? null : earning_cost($raw_amount);
        if (!$own->fetch()) {
            flash(t('This order is not assigned to you or can no longer be changed.', 'यह ऑर्डर आपके पास नहीं है या अब बदला नहीं जा सकता।'));
        } elseif ($raw_amount !== '' && $amt === null) {
            flash(t('Enter a whole goods amount from 0 to 1000000, or leave it blank.', 'सामान की रकम 0 से 1000000 तक पूरी संख्या में भरें, या खाली छोड़ें।'));
        } else {
            $ph = save_photo('bill', 'bill');
            if (!$ph) {
                flash(t('Photo could not be saved. Try a clear image up to 4 MB.', 'फ़ोटो नहीं लग पाई। 4 MB तक की साफ़ तस्वीर से दोबारा कोशिश करें।'));
            } else {
                $bill = $pdo->prepare("UPDATE orders SET bill_photo=?, goods_amount=COALESCE(?, goods_amount) WHERE id=? AND (delivery_user=? OR ?='admin') AND status IN ('Naya','Confirm','Assign','Pickup')");
                $bill->execute([$ph, $amt, $id, $u['id'], $u['role']]);
                if ($bill->rowCount() === 1) {
                    flash(t('Bill saved. The customer can see it.', 'बिल सेव हो गया। ग्राहक देख सकता है।'));
                } else {
                    drop_photo($ph);
                    flash(t('Order state or assignment changed. Refresh the list.', 'ऑर्डर की स्थिति या जिम्मेदारी बदल गई है। सूची दोबारा देखें।'));
                }
            }
        }
    } elseif ($status === 'Delivered') {
        // Grahak ka 4 ank ka code sahi hoga tabhi "pahuncha diya" darj hoga
        $code = preg_replace('/\D/', '', post('code'));
        $chk = $pdo->prepare("SELECT code, status FROM orders WHERE id=? AND (delivery_user=? OR ?='admin')");
        $chk->execute([$id, $u['id'], $u['role']]);
        $row = $chk->fetch();
        if (!$row) {
            flash('यह ऑर्डर आपके पास नहीं है।');
        } elseif ($row['status'] !== 'Pickup') {
            flash(t('Delivery can be recorded only after pickup. Refresh the order.', 'दुकान से सामान लेने के बाद ही डिलीवरी दर्ज होगी। ऑर्डर दोबारा देखें।'));
        } elseif ($code !== $row['code']) {
            flash('कोड मेल नहीं खाया। ग्राहक से दोबारा पूछिए — सही कोड के बिना सामान मत दीजिए।');
        } else {
            $finish = $pdo->prepare("UPDATE orders SET status='Delivered', delivered_at=COALESCE(delivered_at, NOW()) WHERE id=? AND status='Pickup' AND code=? AND (delivery_user=? OR ?='admin')");
            $finish->execute([$id, $code, $u['id'], $u['role']]);
            if ($finish->rowCount() === 1) {
                $pdo->prepare("DELETE FROM live_tracks WHERE order_id=?")->execute([$id]);
                flash(t('Delivery recorded.', 'कोड सही — पहुँचा दिया, दर्ज हो गया।'));
            } else {
                flash(t('Order state or assignment changed. Refresh the list.', 'ऑर्डर की स्थिति या जिम्मेदारी बदल गई है। सूची दोबारा देखें।'));
            }
        }
    }
    redirect('/delivery/');
}

$sql = "SELECT * FROM orders WHERE DATE(created_at)>=CURDATE() - INTERVAL 1 DAY AND status NOT IN ('Cancel','Paisa jama')";
$args = [];
if ($u['role'] === 'delivery') { $sql .= " AND delivery_user=?"; $args[] = $u['id']; }
$sql .= " ORDER BY FIELD(status,'Assign','Confirm','Naya','Pickup','Delivered'), id DESC";
$st = $pdo->prepare($sql); $st->execute($args); $orders = $st->fetchAll();
include __DIR__ . '/../inc/panel.php';
?>
<section><div class="wrap">
  <h2>मेरे ऑर्डर</h2>
  <p class="lead">दुकान से सामान लीजिए, ग्राहक के दरवाज़े पर कोड पूछिए, फिर “पहुँचा दिया” दबाइए।</p>
  <?php if (!$orders): ?><div class="box">अभी आपके पास कोई ऑर्डर नहीं है।</div><?php endif; ?>
  <?php foreach ($orders as $o): ?>
    <div class="box" style="margin-bottom:14px">
      <div style="display:flex;justify-content:space-between;flex-wrap:wrap;gap:6px">
        <b style="font-size:19px"><?= h($o['order_no']) ?></b>
        <span class="tag tag-off"><?= h(status_hi($o['status'])) ?></span>
      </div>
      <div style="margin-top:8px"><b><?= h($o['customer_name']) ?></b> · <a href="tel:+91<?= h($o['mobile']) ?>"><?= h($o['mobile']) ?></a></div>
      <div class="meta"><?= h($o['village']) ?><?= $o['landmark'] ? ' · ' . h($o['landmark']) : '' ?></div>
      <?php if ($o['lat'] !== null && $o['lng'] !== null): ?>
        <a class="btn btn-sm btn-gold" style="margin-top:8px"
           href="https://www.google.com/maps/dir/?api=1&destination=<?= h($o['lat']) ?>,<?= h($o['lng']) ?>"
           target="_blank" rel="noopener">📍 घर का रास्ता (Map)</a>
      <?php endif; ?>
      <div style="margin-top:6px"><?= nl2br(h($o['items'])) ?></div>
      <div class="meta"><?= $o['shop'] ? 'दुकान: ' . h($o['shop']) : 'दुकान: अपनी पसंद' ?> · <?= h(markets()[$o['market']] ?? '') ?></div>
      <div class="note" style="margin-top:10px">
        लेना है: <b><?= goods_paid_to_shop($o['payment']) ? 'सिर्फ़ डिलीवरी चार्ज' : 'सामान + डिलीवरी' ?></b>
        · डिलीवरी: <?= $o['delivery_charge'] === null ? h(t('Confirm the charge first', 'चार्ज पहले पक्का कीजिए')) : '₹' . (int)$o['delivery_charge'] ?>
        <?= $o['goods_amount'] ? ' · सामान ₹' . (int)$o['goods_amount'] : '' ?>
        · पेमेंट: <?= h($o['payment']) ?>
      </div>
      <?php if (in_array($o['status'], ['Assign','Pickup'], true)): ?>
        <div class="note trk" data-o="<?= (int)$o['id'] ?>" style="margin-top:12px">
          <b>अपनी जगह ग्राहक को दिखाइए</b>
          <p class="help" style="margin:4px 0 10px">दबाते ही ग्राहक अपने ऑर्डर पेज पर आपकी गाड़ी चलती देखेगा।
            “पहुँचा दिया” दबाते ही अपने आप बंद हो जाएगा। पेज खुला रखिए।</p>
          <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
            <button type="button" class="btn btn-brand btn-sm trkOn">📍 मैं निकल गया</button>
            <button type="button" class="btn btn-sm trkOff" style="display:none;background:var(--soft);color:var(--bad)">बंद कीजिए</button>
            <span class="help trkMsg"></span>
          </div>
        </div>
      <?php endif; ?>

      <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:12px;align-items:center">
        <a class="btn btn-green btn-sm" href="tel:+91<?= h($o['mobile']) ?>">📞 कॉल</a>
        <?php // ---- डाकिया: दुकान से निकलते ही ग्राहक को बता दीजिए ----
          echo dak_btn($o['mobile'], dak_order($o, 'nikla'), '💬 आ रहा हूँ — भेजिए', 'btn-brand');
        ?>
        <?php if ($o['status'] !== 'Pickup' && $o['status'] !== 'Delivered'): ?>
          <form method="post" style="display:inline">
            <input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="id" value="<?= (int)$o['id'] ?>"><input type="hidden" name="status" value="Pickup">
            <button class="btn btn-brand btn-sm">दुकान से लिया</button>
          </form>
        <?php endif; ?>
      </div>

      <div class="note" style="margin-top:12px">
        <?php if ($o['bill_photo']): ?>
          <div style="display:flex;align-items:center;gap:12px">
            <img src="/uploads/<?= h($o['bill_photo']) ?>" alt="बिल" style="width:56px;height:56px;border-radius:10px;object-fit:cover">
            <div><b>बिल लग चुका है</b><div class="help" style="margin:0">ग्राहक को दिख रहा है<?= $o['goods_amount'] ? ' · सामान ₹' . (int)$o['goods_amount'] : '' ?></div></div>
            <a class="btn btn-sm" href="/uploads/<?= h($o['bill_photo']) ?>" target="_blank" rel="noopener" style="margin-left:auto;background:var(--soft);color:var(--brand)">देखिए</a>
          </div>
        <?php else: ?>
          <b>दुकान से निकलते ही बिल की फ़ोटो खींचिए</b>
          <p class="help" style="margin:4px 0 10px">इससे ग्राहक को भरोसा रहता है और आपका हिसाब भी साफ़ रहता है।</p>
          <form method="post" enctype="multipart/form-data" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
            <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
            <input type="hidden" name="id" value="<?= (int)$o['id'] ?>">
            <input type="hidden" name="status" value="bill">
            <input type="file" name="bill" accept="image/*" required style="max-width:210px">
            <input type="number" name="amount" placeholder="सामान ₹" style="max-width:110px">
            <button class="btn btn-brand btn-sm">बिल लगाइए</button>
          </form>
        <?php endif; ?>
      </div>

      <?php if ($o['status'] !== 'Delivered'): ?>
        <div class="note" style="margin-top:12px">
          <b>दरवाज़े पर ग्राहक से 4 अंकों का कोड पूछिए</b>
          <p class="help" style="margin:4px 0 10px">कोड यहाँ डालिए। सही होने पर ही “पहुँचा दिया” दर्ज होगा — इससे आपका भी हिसाब साफ़ रहेगा।</p>
          <form method="post" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
            <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
            <input type="hidden" name="id" value="<?= (int)$o['id'] ?>">
            <input type="hidden" name="status" value="Delivered">
            <input type="tel" name="code" inputmode="numeric" maxlength="4" required placeholder="— — — —"
                   style="max-width:130px;text-align:center;letter-spacing:8px;font-weight:800;font-size:20px">
            <button class="btn btn-gold btn-sm">पहुँचा दिया</button>
          </form>
        </div>
      <?php endif; ?>
      <p class="help" style="margin-top:8px">सामान की फ़ोटो ग्रुप में भेजिए। कोड ग़लत हो तो सामान मत दीजिए — ऑफ़िस को फ़ोन कीजिए।</p>
    </div>
  <?php endforeach; ?>
</div></section>
<script>
/* ---- apni jagah ग्राहक ko bhejte rehna ---- */
(function(){
  var W = null, T = null, box = null;
  function stop(msg){
    if (W !== null) { navigator.geolocation.clearWatch(W); W = null; }
    if (T) { clearInterval(T); T = null; }
    if (box) {
      box.querySelector('.trkOn').style.display = '';
      box.querySelector('.trkOff').style.display = 'none';
      box.querySelector('.trkMsg').textContent = msg || '';
      box.classList.remove('on');
    }
    box = null;
    try { sessionStorage.removeItem('mk_trk'); } catch(e){}
  }
  function send(oid, pos){
    var d = new FormData();
    d.append('a','ping'); d.append('o', oid);
    d.append('lat', pos.coords.latitude); d.append('lng', pos.coords.longitude);
    d.append('acc', Math.round(pos.coords.accuracy || 0));
    fetch('/api.php', { method:'POST', body:d, credentials:'same-origin' })
      .then(function(r){ if (!r.ok) stop('अब बंद है'); })
      .catch(function(){});
  }
  function start(b){
    if (!navigator.geolocation) { b.querySelector('.trkMsg').textContent = 'इस फ़ोन में जगह बताने की सुविधा नहीं'; return; }
    if (box) stop('');
    box = b;
    var oid = b.getAttribute('data-o'), last = null;
    b.querySelector('.trkOn').style.display = 'none';
    b.querySelector('.trkOff').style.display = '';
    b.querySelector('.trkMsg').textContent = 'जगह ढूंढ रहे हैं…';
    b.classList.add('on');
    try { sessionStorage.setItem('mk_trk', oid); } catch(e){}
    W = navigator.geolocation.watchPosition(function(pos){
      last = pos;
      send(oid, pos);
      b.querySelector('.trkMsg').textContent = 'ग्राहक को दिख रहे हैं ✓';
    }, function(err){
      b.querySelector('.trkMsg').textContent = err.code === 1 ? 'माइक की तरह जगह की भी इजाज़त दीजिए' : 'जगह नहीं मिल रही';
    }, { enableHighAccuracy: true, maximumAge: 10000, timeout: 20000 });
    // har 25 second par dobara bhejte rehte hain
    T = setInterval(function(){ if (last) send(oid, last); }, 25000);
  }
  document.addEventListener('click', function(e){
    var on = e.target.closest('.trkOn'), off = e.target.closest('.trkOff');
    if (on)  start(on.closest('.trk'));
    if (off) stop('बंद है');
  });
  // "pahuncha diya" dabate hi band
  document.addEventListener('submit', function(e){
    var f = e.target;
    if (f.querySelector && f.querySelector('input[name=status][value=Delivered]')) stop('');
  });
  // page wapas khula to dobara chalu
  try {
    var last = sessionStorage.getItem('mk_trk');
    if (last) { var b = document.querySelector('.trk[data-o="' + last + '"]'); if (b) start(b); }
  } catch(e){}
})();
</script>
<?php include __DIR__ . '/../inc/foot.php'; ?>
