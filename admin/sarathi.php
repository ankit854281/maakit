<?php
// ============================================================
//  Admin — सारथी (डिलीवरी वालों का हिसाब और उनकी दिक्कतें)
//
//  Teen cheez ek jagah:
//    1. खुली दिक्कतें — rider ne raste se jo bataya
//    2. हर सारथी का रेट — ek delivery ka kitna (iske bina
//       uske khate me ₹0 dikhta hai)
//    3. पैसा दिया — jab aap paisa bhejein, yahan likh dijiye
//
//  Yaad rahe: paisa Maakit apne paas nahi rakhta. Ye panna
//  sirf GINTI rakhta hai. Paisa aap apne khate se seedhe
//  rider ke khate me bhejte hain.
// ============================================================
require_once __DIR__ . '/../inc/fn.php';
require_once __DIR__ . '/../inc/sarathi.php';
require_once __DIR__ . '/../inc/sarathi-bridge.php';
require_once __DIR__ . '/../inc/trips.php';
$me = need_role('admin');
$page_title = 'सारथी — Maakit';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_ok()) {

    // ---- Smart Sarathi se jodna (ab sab delivery wahin) ----
    if (post('do') === 'bridge') {
        $url = trim((string)post('sb_url')); $key = trim((string)post('sb_key'));
        if ($url !== '' && !preg_match('#^https?://[^\s]+$#', $url)) { $err = 'API का पता http(s):// से शुरू होना चाहिए।'; }
        else {
            $save = $pdo->prepare("INSERT INTO settings (k,v) VALUES (?,?) ON DUPLICATE KEY UPDATE v=VALUES(v)");
            $save->execute(['sarathi_api_url', $url]);
            if ($key !== '') $save->execute(['sarathi_api_key', $key]);          // khaali chhoda = purani chaabi rahe
            $save->execute(['sarathi_auto', post('sb_auto') === '1' ? '1' : '0']);
            flash('Smart Sarathi की सेटिंग सेव हो गई।'); redirect('/admin/sarathi.php?ping=1');
        }
    }

    // ---- sawari / parcel ka kiraya (maakit.in/ride.php) ----
    if (post('do') === 'fares') {
        $save = $pdo->prepare("INSERT INTO settings (k,v) VALUES (?,?) ON DUPLICATE KEY UPDATE v=VALUES(v)");
        foreach (['bike', 'auto', 'erick', 'parcel'] as $vk) {
            $save->execute(["fare_{$vk}_base", (string)max(0, min(2000, (int)post("{$vk}_base")))]);
            $save->execute(["fare_{$vk}_km",   (string)max(0, min(500, (int)post("{$vk}_km")))]);
            $save->execute(["fare_{$vk}_on",   post("{$vk}_on") === '1' ? '1' : '0']);
        }
        $save->execute(['ride_max_km', (string)max(1, min(300, (int)post('max_km')))]);
        flash('सवारी / पार्सल का किराया सेव हो गया।'); redirect('/admin/sarathi.php#kiraya');
    }
    if (post('do') === 'ride_send') {
        [$okr, $mr] = trip_send($pdo, (int)post('rid'));
        flash($okr ? 'Smart Sarathi को भेज दिया: ' . $mr : 'नहीं गया: ' . $mr); redirect('/admin/sarathi.php#bookings');
    }
    if (post('do') === 'ride_cancel' && ($tr = trip_get($pdo, (int)post('rid')))) {
        [$okr, $mr] = trip_cancel($pdo, $tr);
        flash($okr ? 'बुकिंग रद्द हो गई।' : $mr); redirect('/admin/sarathi.php#bookings');
    }

    if (post('do') === 'rate') {
        $r = (int)post('rate');
        if ($r < 0 || $r > 5000) { $err = 'रेट 0 से 5000 के बीच रखिए।'; }
        else {
            $pdo->prepare("UPDATE users SET rider_rate=? WHERE id=? AND role IN ('delivery','rider')")
                ->execute([$r, (int)post('id')]);
            flash('रेट सेव हो गया।'); redirect('/admin/sarathi.php');
        }
    }

    elseif (post('do') === 'diya') {
        $ok = sarathi_diya_likho($pdo, (int)post('id'), post('amount'), post('how'), post('note'), $me['id']);
        flash($ok ? 'खाते में दर्ज हो गया।' : 'रकम सही नहीं है।');
        redirect('/admin/sarathi.php');
    }

    elseif (post('do') === 'extra') {
        $e = (int)post('extra');
        if ($e < 0 || $e > 5000) { $err = 'रेट 0 से 5000 के बीच रखिए।'; }
        else {
            $pdo->prepare("INSERT INTO settings (k,v) VALUES ('multidrop_rate',?)
                           ON DUPLICATE KEY UPDATE v=VALUES(v)")->execute([(string)$e]);
            flash('एक ही चक्कर वाला रेट सेव हो गया।'); redirect('/admin/sarathi.php');
        }
    }

    elseif (post('do') === 'nipta') {
        $pdo->prepare("UPDATE sarathi_samasya SET nipta=1 WHERE id=?")->execute([(int)post('sid')]);
        flash('निपटा हुआ मान लिया गया।'); redirect('/admin/sarathi.php');
    }
}

$sb = sb_cfg($pdo); $sb_ping = null;
if (get('ping') === '1' && sb_ready($pdo)) { [$okp, $rp] = sb_call($pdo, 'ping'); $sb_ping = $okp ? '✓ जुड़ गया — ' . ($rp['client'] ?? '') : '✗ ' . $rp; }
$VEH = trip_vehicles($pdo);
$rides = trips_table($pdo) ? $pdo->query("SELECT * FROM rides ORDER BY id DESC LIMIT 25")->fetchAll() : [];
foreach ($rides as $i => $rr) {
    if (in_array($rr['status'], ['new', 'assigned', 'picked'], true) && $i < 10) { try { $rides[$i] = trip_sync($pdo, $rr); } catch (Throwable $e) {} }
}
$samasya = sarathi_samasya_khuli($pdo);
$kisme   = sarathi_samasya_kism();
$extra   = sarathi_extra_rate($pdo);

$riders = $pdo->query("SELECT id, name, username, mobile, role, rider_rate, active
                         FROM users WHERE role IN ('delivery','rider') ORDER BY active DESC, name")->fetchAll();

include __DIR__ . '/../inc/head.php';
include __DIR__ . '/../inc/panel.php';
?>
<section><div class="wrap" style="max-width:860px">

  <h2>सारथी</h2>
  <?php if ($err): ?><div class="err"><?= h($err) ?></div><?php endif; ?>
  <?php if ($f = flash()): ?><div class="ok"><?= h($f) ?></div><?php endif; ?>

  <!-- ===== Smart Sarathi — ab rider yahin se kaam lete hain ===== -->
  <form method="post" class="box" style="margin-top:12px;border-left:4px solid #0f766e">
    <input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="do" value="bridge">
    <b>Smart Sarathi से जोड़िए</b>
    <p class="help" style="margin:4px 0 10px">राइडर अब सिर्फ़ <a href="/smartsarathi/" target="_blank">Smart Sarathi</a> पर लॉगिन करते हैं।
      Maakit का ऑर्डर वहीं "काम" बनकर जाता है। Smart Sarathi के मालिक पन्ने (<a href="/smartsarathi/admin/" target="_blank">/smartsarathi/admin/</a>)
      में "ग्राहक कंपनियाँ" → Maakit जोड़िए → "नई चाबी" बनाइए, और वो चाबी यहाँ डालिए।</p>
    <?php if ($sb_ping): ?><div class="<?= strpos($sb_ping, '✓') === 0 ? 'ok' : 'err' ?>"><?= h($sb_ping) ?></div><?php endif; ?>
    <div class="field"><label>API का पता</label>
      <input type="text" name="sb_url" value="<?= h($sb['url']) ?>" placeholder="https://maakit.in/smartsarathi/api/v1.php"></div>
    <div class="field"><label>चाबी (sk_…)</label>
      <input type="password" name="sb_key" value="" placeholder="<?= $sb['key'] !== '' ? 'लगी हुई है — बदलनी हो तभी लिखिए' : 'sk_xxxxxxxx_…' ?>" autocomplete="off"></div>
    <label style="display:flex;gap:8px;align-items:center;font-weight:600"><input type="checkbox" name="sb_auto" value="1" style="width:auto" <?= $sb['auto'] ? 'checked' : '' ?>>
      ऑर्डर "Confirm" होते ही अपने आप Smart Sarathi को भेजिए</label>
    <p class="help">बंद रखें तो "सारे ऑर्डर" पेज पर हर ऑर्डर के आगे "सारथी को भेजिए" बटन आएगा। दुकान का OTP और ग्राहक का कोड वही रहते हैं।</p>
    <button class="btn btn-brand btn-sm">सेव करके जाँचिए</button>
  </form>

  <!-- ===== सवारी / पार्सल — maakit.in/ride.php ===== -->
  <h3 style="margin-top:18px" id="bookings">सवारी और पार्सल बुकिंग
    <?php $khuli = count(array_filter($rides, fn($r) => in_array($r['status'], ['new', 'assigned', 'picked'], true))); if ($khuli): ?><span class="tag tag-gold"><?= $khuli ?> चालू</span><?php endif; ?></h3>
  <p class="help" style="margin:0 0 8px">ग्राहक <a href="/ride.php" target="_blank">maakit.in/ride.php</a> से बाइक / ऑटो / ई-रिक्शा या पार्सल बुक करते हैं।
    बुकिंग अपने आप Smart Sarathi पर राइडरों को जाती है। लाल वाली नहीं गई — "भेजिए" दबाइए या ग्राहक को फ़ोन कीजिए।</p>
  <?php if (!$rides): ?>
    <div class="box"><b>अभी कोई बुकिंग नहीं</b></div>
  <?php else: ?>
    <div class="box" style="padding:0;overflow-x:auto">
      <table class="tbl" style="width:100%;border-collapse:collapse;font-size:14px">
        <tr style="background:#F7F8FA;text-align:left"><th style="padding:8px">बुकिंग</th><th style="padding:8px">ग्राहक</th><th style="padding:8px">कहाँ से → कहाँ तक</th><th style="padding:8px">किराया</th><th style="padding:8px">हालत</th><th style="padding:8px"></th></tr>
        <?php foreach ($rides as $r): $bad = !$r['sarathi_job'] && $r['status'] === 'new'; ?>
          <tr style="border-top:1px solid #eee<?= $bad ? ';background:#FFF5F5' : '' ?>">
            <td style="padding:8px;white-space:nowrap"><b><?= $r['kind'] === 'parcel' ? '📦' : h($VEH[$r['vehicle']]['emoji'] ?? '🏍️') ?> <?= h($r['trip_no']) ?></b><div class="meta"><?= h(date('j/n g:i a', strtotime($r['created_at']))) ?><?= $r['when_at'] ? ' · समय ' . h($r['when_at']) : '' ?></div></td>
            <td style="padding:8px"><?= h($r['name']) ?><div class="meta"><a href="tel:<?= h($r['mobile']) ?>"><?= h($r['mobile']) ?></a></div></td>
            <td style="padding:8px;min-width:200px"><?= h(mb_strimwidth($r['pick_address'], 0, 40, '…')) ?> → <?= h(mb_strimwidth($r['drop_address'], 0, 40, '…')) ?><?= $r['km'] !== null ? '<div class="meta">' . h($r['km']) . ' km</div>' : '' ?></td>
            <td style="padding:8px"><?= $r['fare_est'] !== null ? '₹' . (int)$r['fare_est'] : '—' ?></td>
            <td style="padding:8px"><?php [$sk, $sn] = trip_steps($r); ?><?= $r['status'] === 'cancelled' ? 'रद्द' : h($sn[max(0, $sk)]) ?>
              <div class="meta"><?= $r['sarathi_job'] ? h($r['sarathi_job']) . ($r['rider_name'] ? ' · ' . h($r['rider_name']) : '') : '<span style="color:#B42318">' . h($r['send_error'] ?: 'नहीं गई') . '</span>' ?></div></td>
            <td style="padding:8px;white-space:nowrap">
              <?php if ($bad): ?><form method="post" style="display:inline"><input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="do" value="ride_send"><input type="hidden" name="rid" value="<?= (int)$r['id'] ?>"><button class="btn btn-brand btn-sm">भेजिए</button></form><?php endif; ?>
              <?php if (in_array($r['status'], ['new', 'assigned'], true)): ?><form method="post" style="display:inline" onsubmit="return confirm('यह बुकिंग रद्द करें?')"><input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="do" value="ride_cancel"><input type="hidden" name="rid" value="<?= (int)$r['id'] ?>"><button class="btn btn-sm">रद्द</button></form><?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </table>
    </div>
  <?php endif; ?>

  <form method="post" class="box" style="margin-top:12px" id="kiraya">
    <input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="do" value="fares">
    <b>सवारी / पार्सल का किराया</b>
    <p class="help" style="margin:4px 0 10px">किराया = शुरू का + (किलोमीटर × हर km का), ₹5 में गोल। ग्राहक को "लगभग" लिखा दिखता है।
      km = GPS की सीधी दूरी × 1.3 (सड़क घूमकर जाती है)। जो गाड़ी बंद करेंगे, वो बुकिंग में नहीं दिखेगी।</p>
    <div style="display:grid;gap:8px">
      <?php foreach ($VEH as $vk => $vv): ?>
        <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
          <label style="display:flex;gap:6px;align-items:center;font-weight:700;min-width:140px"><input type="checkbox" name="<?= h($vk) ?>_on" value="1" style="width:auto" <?= $vv['on'] ? 'checked' : '' ?>> <?= h($vv['emoji'] . ' ' . $vv['name']) ?></label>
          <label>शुरू का ₹ <input type="number" name="<?= h($vk) ?>_base" value="<?= (int)$vv['base'] ?>" min="0" max="2000" style="width:90px"></label>
          <label>हर km ₹ <input type="number" name="<?= h($vk) ?>_km" value="<?= (int)$vv['km'] ?>" min="0" max="500" style="width:80px"></label>
        </div>
      <?php endforeach; ?>
      <label>ज़्यादा से ज़्यादा दूरी (km) <input type="number" name="max_km" value="<?= (int)trip_max_km($pdo) ?>" min="1" max="300" style="width:90px"></label>
    </div>
    <button class="btn btn-brand btn-sm" style="margin-top:10px">किराया सेव कीजिए</button>
  </form>

  <!-- ===== खुली दिक्कतें ===== -->
  <h3 style="margin-top:18px">रास्ते से आई दिक्कतें
    <?php if ($samasya): ?><span class="tag tag-gold"><?= count($samasya) ?></span><?php endif; ?></h3>

  <?php if (!$samasya): ?>
    <div class="box"><b>अभी कोई दिक्कत नहीं</b>
      <p class="help" style="margin:6px 0 0">डिलीवरी वाला रास्ते से जो बताएगा, वो यहाँ आएगा।</p></div>
  <?php else: foreach ($samasya as $s): ?>
    <div class="box" style="margin-bottom:10px;border-left:4px solid var(--bad,#A33427)">
      <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-start">
        <div style="flex:1;min-width:200px">
          <b><?= h($kisme[$s['kism']] ?? $s['kism']) ?></b>
          <div class="meta"><?= h($s['order_no']) ?> · <?= h($s['customer_name']) ?> · <?= h($s['village']) ?></div>
          <div class="meta"><?= h($s['rider_name'] ?: 'सारथी') ?> ·
            <?= h(date('j/n g:i a', strtotime($s['created_at']))) ?></div>
          <?php if ($s['note']): ?>
            <div style="margin-top:7px;background:var(--cream,#FBF4E6);border-radius:8px;padding:8px 10px"><?= h($s['note']) ?></div>
          <?php endif; ?>
        </div>
        <form method="post">
          <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
          <input type="hidden" name="do" value="nipta">
          <input type="hidden" name="sid" value="<?= (int)$s['id'] ?>">
          <button class="btn btn-sm" type="submit">निपट गया</button>
        </form>
      </div>
    </div>
  <?php endforeach; endif; ?>

  <!-- ===== एक ही चक्कर वाला रेट ===== -->
  <h3 style="margin-top:22px">एक ही चक्कर में अगली डिलीवरी का रेट</h3>
  <div class="box">
    <p class="help" style="margin:0 0 10px">चक्कर की पहली डिलीवरी पर सारथी का पूरा रेट लगता है।
      उसी चक्कर में अगली-अगली डिलीवरी पर यह रेट। इसी वजह से एक ऑर्डर का ख़र्च गिरता है —
      और आप दुकान को सस्ता दे पाते हैं।</p>
    <form method="post" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
      <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
      <input type="hidden" name="do" value="extra">
      <span>₹</span>
      <input type="number" name="extra" value="<?= (int)$extra ?>" min="0" max="5000"
             style="width:110px;padding:10px;border:1px solid var(--line);border-radius:9px">
      <button class="btn btn-brand btn-sm" type="submit">सेव</button>
    </form>
  </div>

  <!-- ===== हर सारथी ===== -->
  <h3 style="margin-top:22px">सारथी और उनका खाता</h3>

  <?php if (!$riders): ?>
    <div class="box"><b>अभी कोई डिलीवरी वाला नहीं है</b>
      <p class="help" style="margin:6px 0 0">टीम में नया लॉगिन बनाइए (role: delivery), या
        पार्टनर आवेदन मंज़ूर कीजिए।</p></div>
  <?php else: foreach ($riders as $r):
      $k = sarathi_khata($pdo, $r['id']); ?>
    <div class="box" style="margin-bottom:12px<?= $r['active'] ? '' : ';opacity:.6' ?>">
      <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:baseline">
        <b style="font-size:18px"><?= h($r['name']) ?></b>
        <span class="tag <?= $r['active'] ? 'tag-live' : 'tag-off' ?>"><?= $r['active'] ? 'चालू' : 'बंद' ?></span>
        <span class="meta"><?= h($r['mobile'] ?: $r['username']) ?> · <?= h($r['role']) ?></span>
      </div>

      <div style="display:flex;gap:16px;flex-wrap:wrap;margin:10px 0;font-size:15px">
        <span>बना <b>₹<?= $k['kul'] ?></b> <span class="meta">(<?= $k['kul_n'] ?>)</span></span>
        <span>दिया <b>₹<?= $k['diya'] ?></b></span>
        <span><?= $k['baaki'] >= 0 ? 'बाकी' : 'एडवांस' ?>
          <b style="color:var(--brand,#7A1F1F)">₹<?= abs($k['baaki']) ?></b></span>
      </div>

      <?php if ((int)$r['rider_rate'] <= 0): ?>
        <div class="err" style="margin:8px 0">रेट तय नहीं है — इसीलिए इनके खाते में ₹0 दिख रहा है।</div>
      <?php endif; ?>

      <div style="display:flex;gap:16px;flex-wrap:wrap;margin-top:10px">
        <form method="post" style="display:flex;gap:7px;align-items:center">
          <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
          <input type="hidden" name="do" value="rate">
          <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
          <label class="meta">एक डिलीवरी का ₹</label>
          <input type="number" name="rate" value="<?= (int)$r['rider_rate'] ?>" min="0" max="5000"
                 style="width:92px;padding:9px;border:1px solid var(--line);border-radius:9px">
          <button class="btn btn-sm" type="submit">सेव</button>
        </form>

        <form method="post" style="display:flex;gap:7px;align-items:center;flex-wrap:wrap">
          <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
          <input type="hidden" name="do" value="diya">
          <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
          <label class="meta">पैसा दिया ₹</label>
          <input type="number" name="amount" min="1" max="500000" required
                 style="width:102px;padding:9px;border:1px solid var(--line);border-radius:9px">
          <select name="how" style="padding:9px;border:1px solid var(--line);border-radius:9px">
            <option>नगद</option><option>UPI</option><option>बैंक</option>
          </select>
          <button class="btn btn-gold btn-sm" type="submit">दर्ज कीजिए</button>
        </form>
      </div>
    </div>
  <?php endforeach; endif; ?>

  <p class="help" style="margin-top:16px">
    <b>याद रखिए:</b> Maakit किसी का पैसा अपने पास नहीं रखता। यह पन्ना सिर्फ़ गिनती रखता है —
    पैसा आप अपने खाते से सीधे सारथी के खाते में भेजते हैं।
  </p>

</div></section>
<?php include __DIR__ . '/../inc/foot.php'; ?>
