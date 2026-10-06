<?php
require_once __DIR__ . '/inc/fn.php';
require_once __DIR__ . '/inc/salon.php';
require_once __DIR__ . '/inc/dukan.php';
$no_tabbar = true;
$page_title = 'मेरी दुकान — Maakit';
$err = '';

// ---------------- लॉगिन ----------------
// Login ka ek hi darwaza hai — /login.php. Yahan andar aane ka
// apna form nahi hai, taaki dukandar ko do jagah yaad na rakhni pade.
$b = shop_login_business($pdo);
if (!$b) { redirect('/login.php'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('do') === 'logout') { shop_logout($pdo); redirect('/login.php'); }

// ---------------- दुकान पैनल के काम (सामान, ऑर्डर, हिसाब, खाता) ----------------
if ($b && $_SERVER['REQUEST_METHOD'] === 'POST' && csrf_ok()) {
    $do = post('do'); $bid = (int)$b['id'];
    $wapas = '/shop.php?tab=' . urlencode(post('tab') ?: 'kaam');

    // --- दुकान खुली / बंद ---
    if ($do === 'khuli') {
        $pdo->prepare("UPDATE businesses SET shop_open=?, shop_updated=NOW() WHERE id=?")
            ->execute([post('open') === '1' ? 1 : 0, $bid]);
        flash(post('open') === '1' ? 'दुकान खुली दिखेगी।' : 'दुकान बंद दिखेगी।');
        redirect($wapas);
    }

    // --- सामान जोड़ना (अपने हाथ से, या Maakit की सूची से) ---
    if ($do === 'item_add') {
        $photo = save_item_photo('photo', 'sh');
        list($ok, $msg) = dukan_item_save($pdo, $bid, [
            'name' => post('name'), 'unit' => post('unit'), 'price' => post('price'),
            'mrp' => post('mrp'), 'item_id' => (int)post('item_id'), 'photo' => $photo,
        ]);
        if (!$ok && $photo) drop_photo($photo);
        flash($msg);
        redirect('/shop.php?tab=saaman');
    }

    // --- बड़ी सूची से कई सामान एक बार में (सिर्फ़ दाम भरे हुए) ---
    if ($do === 'item_bulk') {
        $daam = (array)($_POST['daam'] ?? []);
        $naap = (array)($_POST['naap'] ?? []);
        $jude = 0;
        foreach ($daam as $cid => $p) {
            $p = (int)$p;
            if ($p <= 0) continue;
            $st = $pdo->prepare("SELECT id, name_en, name_hi, unit_hint FROM catalog_items WHERE id=?");
            $st->execute([(int)$cid]);
            if (!$it = $st->fetch()) continue;
            $nm = ($it['name_hi'] !== '' && $it['name_hi'] !== null) ? $it['name_hi'] : $it['name_en'];
            list($ok, $msg, $sid) = dukan_item_save($pdo, $bid, [
                'name'  => $nm,
                'unit'  => trim((string)($naap[$cid] ?? '')) ?: $it['unit_hint'],
                'price' => $p,
            ]);
            if ($ok) {
                $pdo->prepare("UPDATE shop_items SET cat_id=? WHERE id=?")->execute([(int)$it['id'], $sid]);
                $jude++;
            }
        }
        $pdo->prepare("UPDATE businesses SET items_on=1, shop_updated=NOW() WHERE id=?")->execute([$bid]);
        flash($jude ? "$jude सामान जुड़ गए।" : 'किसी का दाम नहीं भरा था।');
        redirect('/shop.php?tab=saaman' . (post('q') !== '' ? '&q=' . urlencode(post('q')) : ''));
    }

    // --- दुकान किस किस्म की है (एक बार चुनना है) ---
    if ($do === 'kism') {
        $k = trim(post('shop_type'));
        $ok = $pdo->prepare("SELECT COUNT(*) c FROM catalog_types WHERE slug=?");
        $ok->execute([$k]);
        if ($k === '' || (int)$ok->fetch()['c']) {
            $pdo->prepare("UPDATE businesses SET shop_type=?, shop_updated=NOW() WHERE id=?")
                ->execute([$k ?: null, $bid]);
            flash($k ? 'किस्म सेव हो गई — अब आपका ही सामान पहले दिखेगा।' : 'किस्म हटा दी।');
        }
        redirect('/shop.php?tab=saaman');
    }

    // --- दाम बदलना / है-ख़त्म / हटाना ---
    if ($do === 'item_daam') {
        if ($it = dukan_item($pdo, $bid, post('id'))) {
            $p = max(0, (int)post('price'));
            if ($p > 0 && $p <= 200000) {
                $pdo->prepare("UPDATE shop_items SET price=? WHERE id=?")->execute([$p, $it['id']]);
                flash('दाम बदल दिया।');
            } else { flash('दाम ठीक नहीं लगा।'); }
        }
        redirect('/shop.php?tab=saaman');
    }
    if ($do === 'item_stock') {
        if ($it = dukan_item($pdo, $bid, post('id'))) {
            $pdo->prepare("UPDATE shop_items SET stock=? WHERE id=?")
                ->execute([$it['stock'] === 'hai' ? 'khatam' : 'hai', $it['id']]);
        }
        redirect('/shop.php?tab=saaman');
    }
    if ($do === 'item_del') {
        if ($it = dukan_item($pdo, $bid, post('id'))) {
            $pdo->prepare("DELETE FROM shop_items WHERE id=?")->execute([$it['id']]);
            if ($it['photo']) drop_photo($it['photo']);
            flash('हटा दिया।');
        }
        redirect('/shop.php?tab=saaman');
    }
    if ($do === 'item_photo') {
        if ($it = dukan_item($pdo, $bid, post('id'))) {
            if ($nayi = save_item_photo('photo', 'sh')) {
                $pdo->prepare("UPDATE shop_items SET photo=? WHERE id=?")->execute([$nayi, $it['id']]);
                if ($it['photo']) drop_photo($it['photo']);
                flash('फ़ोटो लग गई।');
            } else { flash('फ़ोटो नहीं लग पाई — दोबारा कीजिए।'); }
        }
        redirect('/shop.php?tab=saaman');
    }

    // --- ऑर्डर पर दुकानदार का जवाब ---
    if ($do === 'order_do') {
        dukan_order_status($pdo, $bid, (int)post('id'), post('kya'));
        redirect('/shop.php?tab=order');
    }

    // --- दुकान पर ही बिका (अपनी बही) ---
    if ($do === 'khata_add') {
        $r = (int)post('amount');
        $kharch = (post('kind') === 'kharch');
        if ($r > 0 && $r <= 500000) {
            // ख़र्च घटाव में जाता है, बिक्री जोड़ में
            dukan_khata_likho($pdo, $bid, $kharch ? -$r : $r, [
                'source'  => 'dukaan',
                'kind'    => $kharch ? 'kharch' : 'bikri',
                'paid_by' => in_array(post('paid_by'), ['nagad','upi','baad'], true) ? post('paid_by') : 'nagad',
                'note'    => post('note'),
            ]);
            flash('बही में लिख दिया।');
        } else { flash('रकम ठीक नहीं लगी।'); }
        redirect('/shop.php?tab=hisab');
    }

    // --- मेरा खाता (UPI) ---
    if ($do === 'khata_set') {
        $vpa = trim(post('upi_id'));
        if ($vpa !== '' && !preg_match('/^[\w.\-]{2,}@[a-zA-Z]{2,}$/', $vpa)) {
            flash('UPI ID ऐसी होती है — जैसे 9876543210@ybl');
        } else {
            $pdo->prepare("UPDATE businesses SET upi_id=?, upi_name=?, shop_updated=NOW() WHERE id=?")
                ->execute([$vpa ?: null, trim(post('upi_name')) ?: null, $bid]);
            flash('खाता सेव हो गया।');
        }
        redirect('/shop.php?tab=khata');
    }
    if ($do === 'samay') {
        $pdo->prepare("UPDATE businesses SET open_time=?, close_time=?, shop_updated=NOW() WHERE id=?")
            ->execute([post('open_time') ?: '08:00', post('close_time') ?: '20:00', $bid]);
        flash('समय सेव हो गया।');
        redirect('/shop.php?tab=khata');
    }
}

// ---------------- नाई / पार्लर के काम (सीट बुकिंग) ----------------
if ($b && $_SERVER['REQUEST_METHOD'] === 'POST' && csrf_ok()) {
    $do = post('do'); $bid = (int)$b['id'];
    $own = $pdo->prepare("SELECT id FROM bookings WHERE id=? AND business_id=?");

    if ($do === 'mode') {
        $m = in_array(post('mode'), ['auto','busy','off'], true) ? post('mode') : 'auto';
        $pdo->prepare("UPDATE businesses SET mode=?, salon_updated=NOW() WHERE id=?")->execute([$m, $bid]);
    }
    if ($do === 'done') {
        $id = (int)post('id'); $own->execute([$id, $bid]);
        if ($own->fetch()) {
            // जितने मिनट सच में लगे, वही मान लीजिए (अगला अनुमान अपने आप सही होता जाएगा)
            $st = $pdo->prepare("SELECT started_at, minutes FROM bookings WHERE id=?"); $st->execute([$id]); $r = $st->fetch();
            $real = $r['started_at'] ? max(3, (int)round((time() - strtotime($r['started_at'])) / 60)) : (int)$r['minutes'];
            $pdo->prepare("UPDATE bookings SET status='done', minutes=? WHERE id=?")->execute([$real, $id]);
            $pdo->prepare("UPDATE salon_customers sc JOIN bookings bk ON bk.mobile=sc.mobile SET sc.visits=sc.visits+1 WHERE bk.id=?")->execute([$id]);
        }
        shop_touch($pdo, $bid);
    }
    if ($do === 'call_next') {
        $chair = max(1, (int)post('chair'));
        $st = $pdo->prepare("SELECT id FROM bookings WHERE business_id=? AND status='waiting' AND DATE(created_at)=CURDATE() ORDER BY id LIMIT 1");
        $st->execute([$bid]);
        if ($n = $st->fetch()) {
            $pdo->prepare("UPDATE bookings SET status='in_chair', chair_no=?, started_at=NOW() WHERE id=?")->execute([$chair, $n['id']]);
        }
        shop_touch($pdo, $bid);
    }
    if ($do === 'accept' || $do === 'reject') {
        $id = (int)post('id'); $own->execute([$id, $bid]);
        if ($own->fetch()) {
            $pdo->prepare("UPDATE bookings SET status=? WHERE id=?")->execute([$do === 'accept' ? 'waiting' : 'cancelled', $id]);
        }
        shop_touch($pdo, $bid);
    }
    if ($do === 'time') {
        $id = (int)post('id'); $d = (int)post('delta'); $own->execute([$id, $bid]);
        if ($own->fetch()) { $pdo->prepare("UPDATE bookings SET minutes=GREATEST(5, minutes + ?) WHERE id=?")->execute([$d, $id]); }
        shop_touch($pdo, $bid);
    }
    if ($do === 'noshow') {
        $id = (int)post('id'); $own->execute([$id, $bid]);
        if ($own->fetch()) {
            $pdo->prepare("UPDATE bookings SET status='no_show' WHERE id=?")->execute([$id]);
            $pdo->prepare("UPDATE salon_customers sc JOIN bookings bk ON bk.mobile=sc.mobile SET sc.no_shows=sc.no_shows+1 WHERE bk.id=?")->execute([$id]);
        }
        shop_touch($pdo, $bid);
    }
    if ($do === 'walkin') {
        $nm = post('wname') ?: 'दुकान पर आया ग्राहक';
        $mn = max(5, (int)post('wmin', 15));
        $pdo->prepare("INSERT INTO bookings (business_id, ref, customer_name, mobile, service_text, minutes, status, source)
                       VALUES (?,?,?,?,?,?, 'waiting','walkin')")
            ->execute([$bid, salon_ref(), $nm, '', post('wsvc') ?: 'दुकान पर आया', $mn]);
        shop_touch($pdo, $bid);
    }
    if ($do === 'setup') {
        $pdo->prepare("UPDATE businesses SET workers=?, open_time=?, close_time=?, salon_updated=NOW() WHERE id=?")
            ->execute([max(1, min(6, (int)post('workers', 1))), post('open_time') ?: '08:00', post('close_time') ?: '20:00', $bid]);
    }
    if ($do === 'svc_add' && post('sname')) {
        $pdo->prepare("INSERT INTO services (business_id, name, price, minutes) VALUES (?,?,?,?)")
            ->execute([$bid, post('sname'), max(0, (int)post('sprice')), max(3, (int)post('smin', 15))]);
    }
    if ($do === 'svc_del') {
        $pdo->prepare("DELETE FROM services WHERE id=? AND business_id=?")->execute([(int)post('id'), $bid]);
    }
    redirect('/shop.php' . (in_array($do, ['setup','svc_add','svc_del'], true) ? '?tab=setup' : ''));
}
include __DIR__ . '/inc/head.php';
?>
<?php
  // Yahan tak wahi pahunchta hai jo andar aa chuka hai
  // (bina login ke upar hi /login.php bhej diya jata hai).
  $bid   = (int)$b['id'];
  $nai   = (int)$b['salon_on'] === 1;          // नाई / पार्लर है?

  // ---- कौन-कौन से tab दिखेंगे ----
  $tabs = ['kaam' => 'मेरी दुकान', 'saaman' => 'मेरा सामान', 'order' => 'ऑर्डर',
           'hisab' => 'मेरा हिसाब', 'khata' => 'मेरा खाता'];
  if ($nai) { $tabs = ['live' => 'आज की बुकिंग', 'setup' => 'रेट और सेटिंग'] + $tabs; }
  $tab = get('tab');
  if (!isset($tabs[$tab])) $tab = $nai ? 'live' : 'kaam';

  // ---- नाई वाले tab का सामान ----
  if ($nai) {
      $board = salon_board($pdo, $b);
      $req = $pdo->prepare("SELECT * FROM bookings WHERE business_id=? AND status='requested' AND DATE(created_at)=CURDATE() ORDER BY id");
      $req->execute([$bid]); $requests = $req->fetchAll();
      $svc = salon_services($pdo, $bid, false);
      $dsum = $pdo->prepare("SELECT COUNT(*) c, COALESCE(SUM(price),0) p FROM bookings WHERE business_id=? AND status='done' AND DATE(created_at)=CURDATE()");
      $dsum->execute([$bid]); $today = $dsum->fetch();
  }
  $aaj = date('Y-m-d');
?>
<nav class="panelnav"><div class="wrap">
  <?php foreach ($tabs as $k => $lbl): ?>
    <a class="<?= $tab===$k?'on':'' ?>" href="/shop.php?tab=<?= h($k) ?>"><?= h($lbl) ?></a>
  <?php endforeach; ?>
  <a href="<?= $nai ? '/salon.php?id=' . $bid : '/business.php?id=' . $bid ?>" target="_blank">ग्राहक को कैसा दिखता है</a>
  <form method="post" style="margin-left:auto"><input type="hidden" name="do" value="logout"><button class="btn btn-sm" style="background:rgba(251,244,230,.18);color:#fff">बंद कीजिए</button></form>
</div></nav>
<?php if ($m = flash()): ?><div class="wrap" style="max-width:820px"><div class="ok" style="margin-top:12px"><?= h($m) ?></div></div><?php endif; ?>

<?php if ($tab === 'kaam')   { include __DIR__ . '/inc/dukan-kaam.php'; } ?>
<?php if ($tab === 'saaman') { include __DIR__ . '/inc/dukan-saaman.php'; } ?>
<?php if ($tab === 'order')  { include __DIR__ . '/inc/dukan-order.php'; } ?>
<?php if ($tab === 'hisab')  { include __DIR__ . '/inc/dukan-hisab.php'; } ?>
<?php if ($tab === 'khata')  { include __DIR__ . '/inc/dukan-khata.php'; } ?>

<?php if ($tab === 'live'): ?>
<section><div class="wrap" style="max-width:820px">
  <div style="display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap;align-items:center">
    <h2 style="margin:0"><?= h($b['name']) ?></h2>
    <div class="meta">आज: <?= (int)$today['c'] ?> ग्राहक · ₹<?= (int)$today['p'] ?></div>
  </div>

  <div class="box" style="margin-top:12px">
    <label>दुकान की हालत</label>
    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:6px">
      <?php foreach (salon_modes() as $k => $lbl): ?>
        <form method="post"><input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="do" value="mode"><input type="hidden" name="mode" value="<?= h($k) ?>">
          <button class="btn btn-sm <?= $b['mode']===$k ? ($k==='auto'?'btn-green':($k==='busy'?'btn-gold':'btn-brand')) : '' ?>" style="<?= $b['mode']===$k ? '' : 'background:#EFEAE0' ?>"><?= h($lbl) ?></button>
        </form>
      <?php endforeach; ?>
    </div>
    <p class="help" style="margin-top:8px">
      <b>खुली है:</b> बुकिंग अपने आप पक्की — फ़ोन छूने की ज़रूरत नहीं।
      <b>व्यस्त है:</b> बुकिंग अनुरोध बनकर आएगी, आप हाँ करेंगे तभी पक्की।
    </p>
  </div>

  <?php if ($requests): ?>
    <div class="box" style="margin-top:12px;border-color:var(--gold)">
      <h3 style="margin-top:0">नए अनुरोध</h3>
      <?php foreach ($requests as $r): $c = salon_customer($pdo, $r['mobile']); list($bl,$bc) = salon_customer_badge($c); ?>
        <div style="border-top:1px solid var(--line);padding:10px 0;display:flex;gap:10px;flex-wrap:wrap;align-items:center">
          <div style="flex:1;min-width:150px">
            <b><?= h($r['customer_name']) ?></b> <span class="tag <?= h($bc) ?>"><?= h($bl) ?></span>
            <div class="meta"><?= h($r['service_text']) ?> · <?= (int)$r['seats'] ?> सीट · <?= (int)$r['minutes'] ?> मिनट · ₹<?= (int)$r['price'] ?></div>
          </div>
          <form method="post"><input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="do" value="accept"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn btn-green btn-sm">हाँ</button></form>
          <form method="post"><input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="do" value="reject"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn btn-sm" style="background:#EFEAE0">अभी नहीं</button></form>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <div class="grid g2" style="margin-top:12px">
    <?php for ($i = 1; $i <= $board['workers']; $i++): $c = $board['chairs'][$i]; ?>
      <div class="box">
        <div class="meta">कुर्सी <?= $i ?></div>
        <?php if ($c): ?>
          <div style="font-size:22px;font-weight:800"><?= h($c['customer_name']) ?></div>
          <div class="meta"><?= h($c['service_text']) ?> · ₹<?= (int)$c['price'] ?></div>
          <div class="big gold" style="color:var(--brand)"><?= (int)$c['left_min'] ?> मिनट बाकी</div>
          <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:10px">
            <form method="post"><input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="do" value="done"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>"><button class="btn btn-gold">हो गया — अगले</button></form>
            <form method="post"><input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="do" value="time"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>"><input type="hidden" name="delta" value="5"><button class="btn btn-sm" style="background:#EFEAE0">+5 मिनट</button></form>
            <form method="post"><input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="do" value="time"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>"><input type="hidden" name="delta" value="-5"><button class="btn btn-sm" style="background:#EFEAE0">−5 मिनट</button></form>
          </div>
        <?php else: ?>
          <div style="font-size:22px;font-weight:800;color:var(--muted)">खाली</div>
          <form method="post" style="margin-top:10px"><input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="do" value="call_next"><input type="hidden" name="chair" value="<?= $i ?>">
            <button class="btn btn-brand" <?= $board['waiting'] ? '' : 'disabled' ?>>अगले को बिठाइए</button></form>
        <?php endif; ?>
      </div>
    <?php endfor; ?>
  </div>

  <div class="box" style="margin-top:12px">
    <h3 style="margin-top:0">इंतज़ार में (<?= count($board['waiting']) ?>)</h3>
    <?php if (!$board['waiting']): ?><p class="help">अभी कोई नहीं है।</p><?php endif; ?>
    <?php foreach ($board['waiting'] as $i => $w): $c = salon_customer($pdo, $w['mobile']); list($bl,$bc) = salon_customer_badge($c); ?>
      <div style="border-top:1px solid var(--line);padding:10px 0;display:flex;gap:10px;flex-wrap:wrap;align-items:center">
        <div style="flex:1;min-width:160px">
          <b><?= $i+1 ?>. <?= h($w['customer_name']) ?></b>
          <?php if ($w['source']==='online'): ?><span class="tag <?= h($bc) ?>"><?= h($bl) ?></span><?php else: ?><span class="tag tag-off">दुकान पर आए</span><?php endif; ?>
          <?php if ($w['coming']==='yes'): ?><span class="tag tag-live">आ रहे हैं</span><?php endif; ?>
          <div class="meta"><?= h($w['service_text']) ?> · <?= (int)$w['minutes'] ?> मिनट · लगभग <?= h(date('h:i A', $w['eta'])) ?>
            <?= $w['mobile'] ? ' · <a href="tel:+91' . h($w['mobile']) . '">' . h($w['mobile']) . '</a>' : '' ?></div>
        </div>
        <form method="post"><input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="do" value="time"><input type="hidden" name="id" value="<?= (int)$w['id'] ?>"><input type="hidden" name="delta" value="5"><button class="btn btn-sm" style="background:#EFEAE0">+5</button></form>
        <form method="post"><input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="do" value="time"><input type="hidden" name="id" value="<?= (int)$w['id'] ?>"><input type="hidden" name="delta" value="-5"><button class="btn btn-sm" style="background:#EFEAE0">−5</button></form>
        <form method="post"><input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="do" value="noshow"><input type="hidden" name="id" value="<?= (int)$w['id'] ?>"><button class="btn btn-sm" style="background:#EFEAE0">नहीं आए</button></form>
      </div>
    <?php endforeach; ?>

    <form method="post" style="display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end;border-top:2px solid var(--line);padding-top:14px;margin-top:10px">
      <input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="do" value="walkin">
      <div style="min-width:150px"><label>दुकान पर आया ग्राहक</label><input type="text" name="wname" placeholder="नाम"></div>
      <div style="min-width:140px"><label>काम</label><input type="text" name="wsvc" placeholder="जैसे बाल+दाढ़ी"></div>
      <div style="max-width:110px"><label>मिनट</label><input type="number" name="wmin" value="15"></div>
      <button class="btn btn-brand btn-sm">+ लाइन में जोड़िए</button>
    </form>
  </div>
  <script>setTimeout(function(){ location.reload(); }, 45000);</script>
</div></section>

<?php elseif ($tab === 'setup'): ?>
<section><div class="wrap" style="max-width:720px">
  <h2>रेट और सेटिंग</h2>
  <form method="post" class="box">
    <input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="do" value="setup">
    <div class="field"><label>बाल बनाने वाले कितने लोग हैं?</label>
      <select name="workers"><?php for ($i=1;$i<=6;$i++): ?><option value="<?= $i ?>" <?= (int)$b['workers']===$i?'selected':'' ?>><?= $i ?></option><?php endfor; ?></select>
      <p class="help">इतनी ही कुर्सियाँ ग्राहक को दिखेंगी, बाकी इंतज़ार वाली।</p></div>
    <div class="grid g2">
      <div class="field"><label>दुकान खुलती है</label><input type="time" name="open_time" value="<?= h(substr($b['open_time'],0,5)) ?>"></div>
      <div class="field"><label>दुकान बंद होती है</label><input type="time" name="close_time" value="<?= h(substr($b['close_time'],0,5)) ?>"></div>
    </div>
    <button class="btn btn-brand">सेव कीजिए</button>
  </form>

  <div class="box" style="margin-top:16px">
    <h3 style="margin-top:0">अपने रेट</h3>
    <?php foreach ($svc as $s): ?>
      <div style="border-top:1px solid var(--line);padding:9px 0;display:flex;gap:10px;align-items:center">
        <div style="flex:1"><b><?= h($s['name']) ?></b><div class="meta"><?= (int)$s['minutes'] ?> मिनट</div></div>
        <div style="font-weight:700">₹<?= (int)$s['price'] ?></div>
        <form method="post"><input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="do" value="svc_del"><input type="hidden" name="id" value="<?= (int)$s['id'] ?>"><button class="btn btn-sm" style="background:#EFEAE0">हटाइए</button></form>
      </div>
    <?php endforeach; ?>
    <form method="post" style="display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end;margin-top:12px">
      <input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="do" value="svc_add">
      <div style="min-width:160px"><label>काम का नाम</label><input type="text" name="sname" placeholder="जैसे बाल कटिंग"></div>
      <div style="max-width:110px"><label>दाम ₹</label><input type="number" name="sprice" value="50"></div>
      <div style="max-width:110px"><label>मिनट</label><input type="number" name="smin" value="15"></div>
      <button class="btn btn-brand btn-sm">जोड़िए</button>
    </form>
    <p class="help" style="margin-top:10px">मिनट अंदाज़े से भर दीजिए। “हो गया” दबाते रहने पर असली समय अपने आप सीख लिया जाएगा।</p>
  </div>
</div></section>
<?php endif; ?>
<?php include __DIR__ . '/inc/foot.php'; ?>
