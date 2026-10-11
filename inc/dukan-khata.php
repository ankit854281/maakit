<?php
// ============================================================
//  tab: खाता और सेटिंग
//
//  Yahan dukandar apna UPI daalta hai. Paisa SEEDHA uske khate
//  me jata hai — Maakit bich me nahi aata. Ye baat panne par
//  saaf likhi hai, kyunki bharosa isi se banta hai.
//
//  Maakit kabhi bank ka khata number ya PIN nahi maangta.
//  UPI ID likhte hi panna khud bata deta hai ki sahi dikh rahi hai ya nahi.
// ============================================================
$upi = dukan_upi_link($b, 0, '');
$csrf = h(csrf());
?>
<div class="sp-head"><div><h1><?= t('Payments & settings', 'खाता और सेटिंग') ?></h1><p><?= t('Your UPI, shop timings and Maakit’s share.', 'आपका UPI, दुकान का समय और Maakit का हिस्सा।') ?></p></div></div>

<div class="sp-card sp-trust">
  <?= sp_icon('wallet', 22) ?>
  <div>
    <b><?= t('Money comes straight to you', 'पैसा सीधा आपके पास आता है') ?></b>
    <small><?= t('Maakit does not hold your money. Only two ways — cash (the delivery partner brings it) and UPI (straight to your account). Maakit only charges for delivery.', 'Maakit आपका पैसा अपने पास नहीं रखता। दो ही तरीके हैं — नगद (डिलीवरी बॉय लाता है) और UPI (सीधा आपके खाते में)। Maakit सिर्फ़ डिलीवरी का पैसा लेता है।') ?></small>
    <small><b><?= t('Maakit never asks for your bank account number, ATM number or PIN.', 'Maakit कभी आपका बैंक खाता नंबर, ATM नंबर या PIN नहीं माँगता।') ?></b> <?= t('If someone asks, it is not Maakit.', 'कोई माँगे तो समझ जाइए वह Maakit नहीं है।') ?></small>
  </div>
</div>

<div class="sp-grid2 sp-g-top">
  <form method="post" class="sp-card">
    <input type="hidden" name="csrf" value="<?= $csrf ?>"><input type="hidden" name="do" value="khata_set">
    <div class="sp-card-h"><b><?= t('Your UPI', 'अपना UPI') ?></b>
      <span class="sp-pill <?= $b['upi_id'] ? 'ok' : 'new' ?>"><?= $b['upi_id'] ? t('Added', 'लगा है') : t('Not added', 'नहीं लगा') ?></span></div>
    <label class="sp-field"><span>UPI ID</span>
      <input type="text" name="upi_id" value="<?= h($b['upi_id']) ?>" placeholder="<?= h(t('e.g. 9876543210@ybl or name@okaxis', 'जैसे 9876543210@ybl या naam@okaxis')) ?>" autocapitalize="off" spellcheck="false" data-upi>
      <small data-upi-msg><?= t('Open PhonePe / Google Pay / Paytm on your phone — your UPI ID is written there.', 'अपने फ़ोन में PhonePe / Google Pay / Paytm खोलिए — वहीं अपनी UPI ID लिखी होती है।') ?></small></label>
    <label class="sp-field"><span><?= t('Name shown on QR', 'QR पर जो नाम दिखे') ?></span>
      <input type="text" name="upi_name" value="<?= h($b['upi_name']) ?>" placeholder="<?= h($b['name']) ?>"></label>
    <button class="sp-btn sp-btn-go"><?= t('Save', 'सेव कीजिए') ?></button>

    <?php if ($upi): ?>
      <div class="sp-preview">
        <small><?= t('Customers and the delivery partner see this button. It opens their UPI app and pays', 'ग्राहक और डिलीवरी बॉय को यही बटन दिखेगा। दबाने पर उनका UPI ऐप खुलेगा और पैसा जाएगा') ?> <b><?= h($b['upi_name'] ?: $b['name']) ?></b>.</small>
        <a class="sp-btn sp-btn-gold" href="<?= h($upi) ?>">₹ <?= t('Pay by UPI', 'UPI से पैसा दीजिए') ?></a>
        <small><?= t('Press it once yourself to check the name.', 'एक बार ख़ुद दबाकर देख लीजिए — नाम सही दिख रहा है या नहीं।') ?></small>
      </div>
    <?php endif; ?>
  </form>

  <div>
    <form method="post" class="sp-card" id="samay">
      <input type="hidden" name="csrf" value="<?= $csrf ?>"><input type="hidden" name="do" value="samay">
      <div class="sp-card-h"><b><?= t('Shop timings', 'दुकान का समय') ?></b></div>
      <div class="sp-grid2 tight">
        <label class="sp-field"><span><?= t('Opens', 'खुलती है') ?></span><input type="time" name="open_time" value="<?= h(substr((string)$b['open_time'], 0, 5)) ?>"></label>
        <label class="sp-field"><span><?= t('Closes', 'बंद होती है') ?></span><input type="time" name="close_time" value="<?= h(substr((string)$b['close_time'], 0, 5)) ?>"></label>
      </div>
      <p class="sp-note"><?= t('Outside these hours customers don’t see your products.', 'इस समय के बाहर आपका सामान ग्राहक को नहीं दिखेगा।') ?></p>
      <button class="sp-btn sp-btn-go"><?= t('Save', 'सेव कीजिए') ?></button>
    </form>

    <div class="sp-card">
      <div class="sp-card-h"><b><?= t("Maakit's share", 'Maakit का हिस्सा') ?></b>
        <b class="sp-big"><?= (float)$b['commission_pct'] > 0 ? h(rtrim(rtrim(number_format((float)$b['commission_pct'], 1), '0'), '.')) . '%' : t('None', 'कुछ नहीं') ?></b></div>
      <p class="sp-note"><?= (float)$b['commission_pct'] > 0
        ? t('Shown as a separate line in your ledger on every sale — nothing is cut silently.', 'हर बिक्री पर यह हिस्सा आपकी बही में अलग लाइन बनकर दिखता है — छिपाकर कुछ नहीं काटा जाता।')
        : t('Maakit takes nothing from your sales right now. The customer only pays for delivery.', 'अभी आपकी बिक्री पर Maakit कुछ नहीं लेता। ग्राहक सिर्फ़ डिलीवरी का पैसा देता है।') ?></p>
    </div>

    <div class="sp-card">
      <div class="sp-card-h"><b><?= t('Need help?', 'मदद चाहिए?') ?></b></div>
      <p class="sp-note"><?= t('Lost your code, want to change the name, or something is unclear — just call.', 'कोड खो गया, नाम बदलना है, या कुछ समझ नहीं आ रहा — बस फ़ोन कर दीजिए।') ?></p>
      <div class="sp-acts">
        <a class="sp-btn sp-btn-go" href="tel:<?= h(MAAKIT_PHONE) ?>"><?= sp_icon('phone', 15) ?> <?= h(MAAKIT_NUMBER_SHOW) ?></a>
        <a class="sp-btn sp-btn-soft" href="<?= h(wa_link(MAAKIT_WA, 'नमस्ते, मैं ' . $b['name'] . ' से बोल रहा हूँ। मदद चाहिए।')) ?>"><?= sp_icon('wa', 15) ?> WhatsApp</a>
      </div>
    </div>
  </div>
</div>
<script>
(function () {
  var i = document.querySelector('[data-upi]'), m = document.querySelector('[data-upi-msg]'); if (!i || !m) return;
  var hint = m.textContent, ok = <?= json_encode(t('Looks right ✓', 'सही लग रही है ✓')) ?>, bad = <?= json_encode(t('A UPI ID looks like 9876543210@ybl', 'UPI ID ऐसी होती है — जैसे 9876543210@ybl')) ?>;
  function chk() {
    var v = i.value.trim().replace(/\s+/g, '');
    if (v !== i.value) i.value = v;
    if (!v) { m.textContent = hint; m.className = ''; return; }
    var g = /^[\w.\-]{2,}@[a-zA-Z]{2,}$/.test(v);
    m.textContent = g ? ok : bad; m.className = g ? 'sp-pos' : 'sp-neg';
  }
  i.addEventListener('input', chk); chk();
})();
</script>
