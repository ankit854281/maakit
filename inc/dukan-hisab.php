<?php
// ============================================================
//  tab: मेरा हिसाब (बही)
//
//  Yahi tab dukandar ko roz laayega. Isliye:
//    - sabse upar bade akshar me ek hi number: haath me kitna aaya
//    - Maakit se lena kitna hai
//    - dukaan par hui bikri wo khud likh sake (bahi-khata)
//
//  Yahan paisa nahi rakha jata, sirf hisab likha jata hai.
// ============================================================
$kab = get('kab');
if (!in_array($kab, ['aaj', 'hafta', 'mahina'], true)) $kab = 'aaj';
$se  = ['aaj' => $aaj, 'hafta' => date('Y-m-d', strtotime('-6 days')), 'mahina' => date('Y-m-01')][$kab];
$tak = $aaj;
$kabName = ['aaj' => 'आज', 'hafta' => 'इस हफ़्ते', 'mahina' => 'इस महीने'][$kab];

$j    = dukan_jod($pdo, $bid, $se, $tak);
$rows = dukan_khata($pdo, $bid, $se, $tak, 200);
$top  = dukan_top($pdo, $bid, 5);
$lena = dukan_lena_hai($pdo, $bid);

$kaise = ['nagad' => 'नगद', 'upi' => 'UPI', 'baad' => 'उधार'];
$kismein = ['bikri' => 'बिक्री', 'commission' => 'Maakit का हिस्सा', 'jama' => 'जमा', 'kharch' => 'ख़र्च'];
?>
<section><div class="wrap" style="max-width:820px">

  <h2>मेरा हिसाब</h2>

  <div style="display:flex;gap:8px;margin-top:10px">
    <?php foreach (['aaj' => 'आज', 'hafta' => '7 दिन', 'mahina' => 'इस महीने'] as $k => $lbl): ?>
      <a class="btn btn-sm <?= $kab===$k ? 'btn-brand' : '' ?>"
         style="flex:1;text-align:center<?= $kab===$k ? '' : ';background:#EFEAE0' ?>"
         href="/shop.php?tab=hisab&kab=<?= h($k) ?>"><?= h($lbl) ?></a>
    <?php endforeach; ?>
  </div>

  <!-- एक ही बड़ा नंबर: जो हाथ में आया -->
  <div class="box" style="margin-top:12px;border-color:var(--gold)">
    <div class="meta"><?= h($kabName) ?> आपके हाथ में</div>
    <div class="big" style="color:var(--brand);font-size:38px">₹<?= (int)$j['bacha'] ?></div>
    <div class="meta">
      बिक्री ₹<?= (int)$j['bikri'] ?>
      <?php if ((int)$j['commission']): ?> − Maakit का हिस्सा ₹<?= (int)$j['commission'] ?><?php endif; ?>
      <?php if ((int)$j['kharch']): ?> − ख़र्च ₹<?= (int)$j['kharch'] ?><?php endif; ?>
    </div>
  </div>

  <div class="grid g2" style="margin-top:12px">
    <div class="box">
      <div class="meta">पैसा कैसे आया</div>
      <div style="margin-top:6px">नगद <b>₹<?= (int)$j['nagad'] ?></b></div>
      <div>UPI <b>₹<?= (int)$j['upi'] ?></b></div>
      <?php if ((int)$j['udhaar']): ?>
        <div style="color:var(--brand)">उधार <b>₹<?= (int)$j['udhaar'] ?></b></div>
      <?php endif; ?>
    </div>
    <div class="box">
      <div class="meta">कहाँ से आया</div>
      <div style="margin-top:6px">Maakit से <b>₹<?= (int)$j['maakit_se'] ?></b></div>
      <div>दुकान पर <b>₹<?= (int)$j['dukaan_se'] ?></b></div>
      <div class="meta" style="margin-top:6px"><?= (int)$j['ginti'] ?> बार बिका</div>
    </div>
  </div>

  <?php if ($lena > 0): ?>
    <div class="box" style="margin-top:12px">
      <div class="meta">Maakit से लेना बाकी है</div>
      <div class="big" style="color:var(--brand)">₹<?= $lena ?></div>
      <p class="help">नगद वाले ऑर्डर का पैसा डिलीवरी बॉय के पास गया था। Maakit इसे हिसाब करके देता है।</p>
    </div>
  <?php endif; ?>

  <!-- दुकान पर बिका — दुकानदार ख़ुद लिखे -->
  <div class="box" style="margin-top:16px">
    <h3 style="margin-top:0">दुकान पर बिका? यहाँ लिख दीजिए</h3>
    <p class="help">Maakit के ऑर्डर अपने आप चढ़ते हैं। काउंटर पर हुई बिक्री आप यहाँ जोड़िए —
      तब पूरे दिन का हिसाब एक जगह मिलेगा।</p>
    <form method="post" style="display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end;margin-top:12px">
      <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
      <input type="hidden" name="do" value="khata_add">
      <div style="max-width:120px"><label>रकम ₹</label>
        <input type="number" name="amount" min="1" max="500000" inputmode="numeric" required></div>
      <div style="max-width:130px"><label>कैसे</label>
        <select name="paid_by"><option value="nagad">नगद</option><option value="upi">UPI</option>
          <option value="baad">उधार</option></select></div>
      <div style="max-width:130px"><label>क्या</label>
        <select name="kind"><option value="bikri">बिक्री</option><option value="kharch">ख़र्च</option></select></div>
      <div style="flex:1;min-width:150px"><label>किसको / क्या (मर्ज़ी से)</label>
        <input type="text" name="note" maxlength="160" placeholder="जैसे: रमेश जी — चावल"></div>
      <button class="btn btn-brand btn-sm">बही में लिखिए</button>
    </form>
  </div>

  <?php if ($top): ?>
    <div class="box" style="margin-top:12px">
      <h3 style="margin-top:0">सबसे ज़्यादा बिकने वाला</h3>
      <?php foreach ($top as $i => $t): ?>
        <div style="border-top:1px solid var(--line);padding:8px 0;display:flex;gap:10px">
          <div style="flex:1"><b><?= $i+1 ?>. <?= h($t['name']) ?></b>
            <div class="meta"><?= h($t['unit']) ?> · ₹<?= (int)$t['price'] ?></div></div>
          <div style="font-weight:700"><?= (int)$t['sold'] ?> बार</div>
        </div>
      <?php endforeach; ?>
      <p class="help" style="margin-top:8px">इन्हें कभी “ख़त्म” न होने दीजिए — यहीं सबसे ज़्यादा कमाई है।</p>
    </div>
  <?php endif; ?>

  <!-- पूरी बही -->
  <div style="margin-top:20px">
    <h3>पूरी बही (<?= h($kabName) ?>)</h3>
    <?php if (!$rows): ?>
      <p class="help">इस समय में कोई लेन-देन नहीं।</p>
    <?php endif; ?>
    <?php foreach ($rows as $r): $neg = (int)$r['amount'] < 0; ?>
      <div style="border-bottom:1px solid var(--line);padding:10px 0;display:flex;gap:10px;align-items:center">
        <div style="flex:1;min-width:0">
          <b><?= h($kismein[$r['kind']] ?? $r['kind']) ?></b>
          <span class="tag <?= $r['source']==='maakit' ? 'tag-live' : 'tag-off' ?>">
            <?= $r['source']==='maakit' ? 'Maakit' : 'दुकान' ?></span>
          <div class="meta">
            <?= h(date('d M, h:i A', strtotime($r['created_at']))) ?>
            · <?= h($kaise[$r['paid_by']] ?? '') ?>
            <?= $r['note'] ? ' · ' . h($r['note']) : '' ?>
          </div>
        </div>
        <div style="font-weight:800;font-size:18px;color:<?= $neg ? '#B02A2A' : 'var(--brand)' ?>">
          <?= $neg ? '−' : '+' ?>₹<?= abs((int)$r['amount']) ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

</div></section>
