<?php
require_once __DIR__ . '/../inc/fn.php';
require_once __DIR__ . '/../inc/dakiya.php';
require_once __DIR__ . '/../inc/earning.php';
require_once __DIR__ . '/../inc/dispatch.php';
$u = need_role(['delivery', 'admin']);
$page_title = 'मेरे ऑर्डर — Maakit';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_ok()) {
    $id = (int)post('id'); $status = post('status');
    if($status==='availability'&&$u['role']==='delivery'){
        $until=post('available')==='1'?gmdate('Y-m-d H:i:s',time()+900):null;
        $pdo->prepare("INSERT INTO driver_availability(user_id,available_until) SELECT id,? FROM users WHERE id=? AND active=1 AND role='delivery' ON DUPLICATE KEY UPDATE available_until=VALUES(available_until)")->execute([$until,$u['id']]);
        if($until)dispatch_queue($pdo);
        flash(t('Availability updated. Refresh to see assigned orders.','उपलब्धता अपडेट हुई। मिले order देखने के लिए पेज दोबारा देखें।'));
    } elseif ($status === 'Pickup') {
        $pickup = $pdo->prepare("UPDATE orders SET status='Pickup', picked_at=COALESCE(picked_at, NOW()) WHERE id=? AND (delivery_user=? OR ?='admin') AND status IN ('Naya','Confirm','Assign') AND ".quote_guard());
        $pickup->execute([$id, $u['id'], $u['role']]);
        flash($pickup->rowCount() === 1 ? t('Pickup recorded.', 'दुकान से लिया — दर्ज हो गया।') : t('Order state or assignment changed. Refresh the list.', 'ऑर्डर की स्थिति या जिम्मेदारी बदल गई है। सूची दोबारा देखें।'));
    } elseif ($status === 'bill') {
        // Authorize and validate before storing a customer-visible bill photo.
        $own = $pdo->prepare("SELECT id FROM orders WHERE id=? AND (delivery_user=? OR ?='admin') AND status IN ('Naya','Confirm','Assign','Pickup') AND ".quote_guard());
        $own->execute([$id, $u['id'], $u['role']]);
        $quote=order_quote($pdo,$id);
        $raw_amount = post('amount');
        $amt = $raw_amount === '' ? null : earning_cost($raw_amount);
        if (!$own->fetch()) {
            flash(t('This order is not assigned to you or can no longer be changed.', 'यह ऑर्डर आपके पास नहीं है या अब बदला नहीं जा सकता।'));
        } elseif ($quote && $raw_amount!=='' && ($amt===null || (int)$amt!==(int)$quote['goods_amount'])) {
            flash(t('Bill amount differs from the accepted quote. Contact the team before proceeding.','बिल का दाम स्वीकार किए दाम से अलग है। आगे बढ़ने से पहले टीम से बात कीजिए।'));
        } elseif ($raw_amount !== '' && $amt === null) {
            flash(t('Enter a whole goods amount from 0 to 1000000, or leave it blank.', 'सामान की रकम 0 से 1000000 तक पूरी संख्या में भरें, या खाली छोड़ें।'));
        } else {
            $ph = save_photo('bill', 'bill');
            if (!$ph) {
                flash(t('Photo could not be saved. Try a clear image up to 4 MB.', 'फ़ोटो नहीं लग पाई। 4 MB तक की साफ़ तस्वीर से दोबारा कोशिश करें।'));
            } else {
                $bill = $pdo->prepare("UPDATE orders SET bill_photo=?, goods_amount=COALESCE(?, goods_amount) WHERE id=? AND (delivery_user=? OR ?='admin') AND status IN ('Naya','Confirm','Assign','Pickup') AND ".quote_guard()." AND (NOT EXISTS (SELECT 1 FROM order_quotes q WHERE q.order_id=orders.id) OR ? IS NULL OR ?=goods_amount)");
                $bill->execute([$ph, $amt, $id, $u['id'], $u['role'],$amt,$amt]);
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
            $finish = $pdo->prepare("UPDATE orders SET status='Delivered', delivered_at=COALESCE(delivered_at, NOW()) WHERE id=? AND status='Pickup' AND code=? AND (delivery_user=? OR ?='admin') AND ".quote_guard());
            $finish->execute([$id, $code, $u['id'], $u['role']]);
            if ($finish->rowCount() === 1) {
                $pdo->prepare("DELETE FROM live_tracks WHERE order_id=?")->execute([$id]);
                dispatch_queue($pdo);
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
$available=false;if($u['role']==='delivery'){$s=$pdo->prepare('SELECT available_until>UTC_TIMESTAMP() FROM driver_availability WHERE user_id=?');$s->execute([$u['id']]);$available=(bool)$s->fetchColumn();}
include __DIR__ . '/../inc/panel.php';
?>
<section><div class="wrap">
  <h2>मेरे ऑर्डर</h2>
  <p class="lead">दुकान से सामान लीजिए, ग्राहक के दरवाज़े पर कोड पूछिए, फिर “पहुँचा दिया” दबाइए।</p>
  <?php if($u['role']==='delivery'):?><form method="post" class="box"><input type="hidden" name="csrf" value="<?=h(csrf())?>"><input type="hidden" name="status" value="availability"><b><?=t('Automatic assignment availability','Automatic assignment की उपलब्धता')?>: <?=h($available?t('Available now','अभी उपलब्ध'):t('Unavailable','उपलब्ध नहीं'))?></b><p class="help"><?=t('Choose availability only when ready. It expires in 15 minutes; renew to keep receiving confirmed orders. Check this page for assignments. This does not share GPS.','तैयार होने पर ही उपलब्धता चुनें। यह 15 मिनट में समाप्त होगी; नए confirmed order लेने के लिए फिर चुनें। मिले order इस पेज पर देखें। इससे GPS share नहीं होता।')?></p><button class="btn btn-brand" name="available" value="1"><?=t('Available for 15 minutes','15 मिनट के लिए उपलब्ध')?></button> <button class="btn btn-line" name="available" value="0"><?=t('Stop new assignments','नए assignment रोकें')?></button></form><?php endif;?>
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
          <p class="help" style="margin:4px 0 10px"><?=t('Allow GPS and keep this page visible. Sharing stops after delivery. Screen lock or another app may pause updates.','GPS की अनुमति दें और यह पेज सामने रखें। डिलीवरी के बाद sharing बंद होगी। Screen lock या दूसरे app पर जाने से updates रुक सकते हैं।')?></p>
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
<script>window.MAAKIT_TRACKING=<?=json_encode(['csrf'=>csrf(),'waiting'=>t('Finding location…','जगह ढूँढ रहे हैं…'),'shared'=>t('Location sent to customer','ग्राहक को location भेजी गई'),'failed'=>t('Location could not be sent. Check internet and refresh.','Location नहीं भेजी गई। इंटरनेट जाँचें और पेज दोबारा देखें।'),'denied'=>t('Allow location in your browser settings.','Browser settings में location की अनुमति दें।'),'stale'=>t('Waiting for a fresh GPS location.','नई GPS location का इंतज़ार है।'),'stopped'=>t('Sharing stopped','Sharing बंद हुई'),'paused'=>t('Keep this page visible for GPS updates.','GPS updates के लिए यह पेज सामने रखें।')],JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_UNESCAPED_UNICODE)?>;</script>
<script src="/assets/delivery-tracking.js" defer></script>
<?php include __DIR__ . '/../inc/foot.php'; ?>
