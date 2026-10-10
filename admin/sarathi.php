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
$me = need_role('admin');
$page_title = 'सारथी — Maakit';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_ok()) {

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
