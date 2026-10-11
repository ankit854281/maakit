<?php
// ============================================================
//  tab: मेरा हिसाब (बही)
//
//  Yahi tab dukandar ko roz laayega. Isliye:
//    - sabse upar ek hi bada number: haath me kitna aaya
//    - nagad / UPI / udhaar ki ek patti (ek nazar me)
//    - Maakit se lena kitna hai
//    - dukaan par hui bikri wo khud likh sake (bahi-khata) —
//      ₹50/100/200/500 ke button se ek dabane me
//
//  Yahan paisa nahi rakha jata, sirf hisab likha jata hai.
// ============================================================
$kab = get('kab');
if (!in_array($kab, ['aaj', 'hafta', 'mahina'], true)) $kab = 'aaj';
$se  = ['aaj' => $aaj, 'hafta' => date('Y-m-d', strtotime('-6 days')), 'mahina' => date('Y-m-01')][$kab];
$tak = $aaj;
$kabName = ['aaj' => t('Today', 'आज'), 'hafta' => t('Last 7 days', 'पिछले 7 दिन'), 'mahina' => t('This month', 'इस महीने')][$kab];

$j    = dukan_jod($pdo, $bid, $se, $tak);
$rows = dukan_khata($pdo, $bid, $se, $tak, 200);
$top  = dukan_top($pdo, $bid, 5);
$lena = dukan_lena_hai($pdo, $bid);

$kaise   = ['nagad' => t('Cash', 'नगद'), 'upi' => 'UPI', 'baad' => t('Credit', 'उधार')];
$kismein = ['bikri' => t('Sale', 'बिक्री'), 'commission' => t("Maakit's share", 'Maakit का हिस्सा'), 'jama' => t('Deposit', 'जमा'), 'kharch' => t('Expense', 'ख़र्च')];
$kul_aaya = max(1, (int)$j['nagad'] + (int)$j['upi'] + (int)$j['udhaar']);
$csrf = h(csrf());
?>
<div class="sp-head">
  <div><h1><?= t('My accounts', 'मेरा हिसाब') ?></h1><p><?= t('Maakit orders are added by themselves. Write counter sales here.', 'Maakit के ऑर्डर अपने आप चढ़ते हैं। काउंटर की बिक्री यहाँ लिखिए।') ?></p></div>
  <div class="sp-seg sm">
    <?php foreach (['aaj' => t('Today', 'आज'), 'hafta' => t('7 days', '7 दिन'), 'mahina' => t('Month', 'महीना')] as $k => $lbl): ?>
      <a class="<?= $kab === $k ? 'on' : '' ?>" href="/shop.php?tab=hisab&amp;kab=<?= h($k) ?>"><?= h($lbl) ?></a>
    <?php endforeach; ?>
  </div>
</div>

<div class="sp-grid2">
  <!-- एक ही बड़ा नंबर: जो हाथ में आया -->
  <div class="sp-card sp-net">
    <small><?= h($kabName) ?> · <?= t('in your hand', 'आपके हाथ में') ?></small>
    <b>₹<?= number_format((int)$j['bacha']) ?></b>
    <small><?= t('Sales', 'बिक्री') ?> ₹<?= number_format((int)$j['bikri']) ?>
      <?php if ((int)$j['commission']): ?> − <?= t("Maakit's share", 'Maakit का हिस्सा') ?> ₹<?= number_format((int)$j['commission']) ?><?php endif; ?>
      <?php if ((int)$j['kharch']): ?> − <?= t('Expenses', 'ख़र्च') ?> ₹<?= number_format((int)$j['kharch']) ?><?php endif; ?>
      · <?= (int)$j['ginti'] ?> <?= t('sales', 'बार बिका') ?></small>
    <div class="sp-split" aria-hidden="true">
      <i class="c" style="width:<?= round((int)$j['nagad'] / $kul_aaya * 100, 1) ?>%"></i><i class="u" style="width:<?= round((int)$j['upi'] / $kul_aaya * 100, 1) ?>%"></i><i class="d" style="width:<?= round((int)$j['udhaar'] / $kul_aaya * 100, 1) ?>%"></i>
    </div>
    <div class="sp-legend">
      <span><i class="c"></i><?= t('Cash', 'नगद') ?> <b>₹<?= number_format((int)$j['nagad']) ?></b></span>
      <span><i class="u"></i>UPI <b>₹<?= number_format((int)$j['upi']) ?></b></span>
      <span><i class="d"></i><?= t('Credit', 'उधार') ?> <b>₹<?= number_format((int)$j['udhaar']) ?></b></span>
    </div>
  </div>

  <div class="sp-card">
    <div class="sp-money">
      <div><small><?= t('From Maakit', 'Maakit से') ?></small><b>₹<?= number_format((int)$j['maakit_se']) ?></b></div>
      <div><small><?= t('At the counter', 'दुकान पर') ?></small><b>₹<?= number_format((int)$j['dukaan_se']) ?></b></div>
      <div class="sp-span2"><small><?= t('Still due from Maakit', 'Maakit से लेना बाकी') ?></small><b class="<?= $lena > 0 ? 'sp-pos' : 'sp-muted' ?>">₹<?= number_format($lena) ?></b>
        <em><?= $lena > 0 ? t('Cash from delivery orders — Maakit settles it with you.', 'नगद वाले ऑर्डर का पैसा डिलीवरी बॉय के पास गया था। Maakit इसे हिसाब करके देता है।') : t('Nothing pending', 'कुछ बाकी नहीं') ?></em></div>
    </div>
  </div>
</div>

<!-- दुकान पर बिका — दुकानदार ख़ुद लिखे -->
<form method="post" class="sp-card sp-entry">
  <input type="hidden" name="csrf" value="<?= $csrf ?>"><input type="hidden" name="do" value="khata_add"><input type="hidden" name="kab" value="<?= h($kab) ?>">
  <div class="sp-card-h"><b><?= t('Write in your ledger', 'बही में लिखिए') ?></b>
    <div class="sp-seg sm" role="radiogroup">
      <label><input type="radio" name="kind" value="bikri" checked><span><?= t('Sale', 'बिक्री') ?></span></label>
      <label><input type="radio" name="kind" value="kharch"><span><?= t('Expense', 'ख़र्च') ?></span></label>
    </div>
  </div>
  <div class="sp-entry-g">
    <label class="sp-rs big"><span>₹</span><input class="sp-in" type="number" name="amount" min="1" max="500000" inputmode="numeric" required placeholder="0" data-amt></label>
    <div class="sp-amts">
      <?php foreach ([50, 100, 200, 500, 1000] as $a): ?><button type="button" data-add="<?= $a ?>">+<?= $a ?></button><?php endforeach; ?>
    </div>
  </div>
  <div class="sp-entry-g">
    <div class="sp-seg sm" role="radiogroup" aria-label="<?= h(t('Paid by', 'कैसे')) ?>">
      <label><input type="radio" name="paid_by" value="nagad" checked><span><?= t('Cash', 'नगद') ?></span></label>
      <label><input type="radio" name="paid_by" value="upi"><span>UPI</span></label>
      <label><input type="radio" name="paid_by" value="baad"><span><?= t('Credit', 'उधार') ?></span></label>
    </div>
    <input class="sp-in sp-grow" type="text" name="note" maxlength="160" placeholder="<?= h(t('Who / what (optional) — e.g. Ramesh ji, rice', 'किसको / क्या (मर्ज़ी से) — जैसे: रमेश जी, चावल')) ?>">
    <button class="sp-btn sp-btn-go"><?= sp_icon('plus', 15) ?> <?= t('Write it', 'लिखिए') ?></button>
  </div>
</form>

<div class="sp-grid2 sp-g-top">
  <!-- पूरी बही -->
  <div class="sp-card">
    <div class="sp-card-h"><b><?= t('Ledger', 'पूरी बही') ?> <span class="sp-muted">· <?= h($kabName) ?></span></b></div>
    <?php if (!$rows): ?><p class="sp-empty"><?= t('No entries in this period.', 'इस समय में कोई लेन-देन नहीं।') ?></p><?php endif; ?>
    <?php foreach ($rows as $r): $neg = (int)$r['amount'] < 0; ?>
      <div class="sp-row">
        <span class="sp-ledg <?= $neg ? 'neg' : '' ?>" aria-hidden="true"><?= $neg ? '−' : '+' ?></span>
        <div class="sp-row-t">
          <b><?= h($kismein[$r['kind']] ?? $r['kind']) ?></b>
          <span class="sp-pill <?= $r['source'] === 'maakit' ? 'go' : 'off' ?>"><?= $r['source'] === 'maakit' ? 'Maakit' : t('Shop', 'दुकान') ?></span>
          <small><?= h(date('d M, h:i A', strtotime($r['created_at']))) ?> · <?= h($kaise[$r['paid_by']] ?? '') ?><?= $r['note'] ? ' · ' . h($r['note']) : '' ?></small>
        </div>
        <b class="sp-amt <?= $neg ? 'sp-neg' : 'sp-pos' ?>"><?= $neg ? '−' : '+' ?>₹<?= number_format(abs((int)$r['amount'])) ?></b>
      </div>
    <?php endforeach; ?>
  </div>

  <?php if ($top): $mx = max(1, (int)$top[0]['sold']); ?>
    <div class="sp-card">
      <div class="sp-card-h"><b><?= t('Best sellers', 'सबसे ज़्यादा बिकने वाला') ?></b></div>
      <?php foreach ($top as $i => $tp): ?>
        <div class="sp-bar-row">
          <div><b><?= $i + 1 ?>. <?= h($tp['name']) ?></b><small><?= h($tp['unit']) ?> · ₹<?= (int)$tp['price'] ?></small></div>
          <span><?= (int)$tp['sold'] ?>×</span>
          <i style="width:<?= round((int)$tp['sold'] / $mx * 100) ?>%"></i>
        </div>
      <?php endforeach; ?>
      <p class="sp-note"><?= t('Never let these run out — this is where you earn most.', 'इन्हें कभी “ख़त्म” न होने दीजिए — यहीं सबसे ज़्यादा कमाई है।') ?></p>
    </div>
  <?php endif; ?>
</div>
<script>
document.querySelectorAll('[data-add]').forEach(function (b) {
  b.addEventListener('click', function () {
    var i = document.querySelector('[data-amt]'); if (!i) return;
    i.value = (parseInt(i.value, 10) || 0) + parseInt(b.dataset.add, 10); i.focus();
    try { navigator.vibrate && navigator.vibrate(8); } catch (e) {}
  });
});
</script>
