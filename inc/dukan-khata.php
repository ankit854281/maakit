<?php
// ============================================================
//  tab: मेरा खाता
//
//  Yahan dukandar apna UPI daalta hai. Paisa SEEDHA uske khate
//  me jata hai — Maakit bich me nahi aata. Ye baat panne par
//  saaf likhi hai, kyunki bharosa isi se banta hai.
//
//  Maakit kabhi bank ka khata number ya PIN nahi maangta.
// ============================================================
$upi = dukan_upi_link($b, 0, '');
?>
<section><div class="wrap" style="max-width:720px">

  <h2>मेरा खाता</h2>

  <div class="box" style="margin-top:12px;background:#F4EDE0">
    <b>पैसा सीधा आपके पास आता है</b>
    <p class="help" style="margin-top:6px">
      Maakit आपका पैसा अपने पास नहीं रखता। दो ही तरीके हैं —
      <b>नगद</b> (डिलीवरी बॉय लाता है) और <b>UPI</b> (सीधा आपके खाते में)।
      Maakit सिर्फ़ डिलीवरी का पैसा लेता है।
    </p>
    <p class="help" style="margin-top:6px">
      Maakit कभी आपका <b>बैंक खाता नंबर, ATM नंबर या PIN नहीं माँगता</b>।
      कोई माँगे तो समझ जाइए वह Maakit नहीं है।
    </p>
  </div>

  <form method="post" class="box" style="margin-top:14px">
    <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
    <input type="hidden" name="do" value="khata_set">
    <h3 style="margin-top:0">अपना UPI</h3>
    <div class="field"><label>UPI ID</label>
      <input type="text" name="upi_id" value="<?= h($b['upi_id']) ?>"
             placeholder="जैसे 9876543210@ybl या naam@okaxis" autocapitalize="off" spellcheck="false">
      <p class="help">अपने फ़ोन में PhonePe / Google Pay / Paytm खोलिए — वहीं अपनी UPI ID लिखी होती है।</p></div>
    <div class="field"><label>QR पर जो नाम दिखे</label>
      <input type="text" name="upi_name" value="<?= h($b['upi_name']) ?>" placeholder="<?= h($b['name']) ?>"></div>
    <button class="btn btn-brand">सेव कीजिए</button>
  </form>

  <?php if ($upi): ?>
    <div class="box" style="margin-top:12px">
      <h3 style="margin-top:0">ऐसा दिखेगा</h3>
      <p class="help">ग्राहक और डिलीवरी बॉय को यही बटन दिखेगा। दबाने पर उनका UPI ऐप खुलेगा
        और पैसा <b><?= h($b['upi_name'] ?: $b['name']) ?></b> को जाएगा।</p>
      <a class="btn btn-gold" style="margin-top:8px" href="<?= h($upi) ?>">₹ UPI से पैसा दीजिए</a>
      <p class="help" style="margin-top:8px">एक बार ख़ुद दबाकर देख लीजिए — नाम सही दिख रहा है या नहीं।</p>
    </div>
  <?php endif; ?>

  <form method="post" class="box" style="margin-top:14px">
    <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
    <input type="hidden" name="do" value="samay">
    <h3 style="margin-top:0">दुकान का समय</h3>
    <div class="grid g2">
      <div class="field"><label>खुलती है</label>
        <input type="time" name="open_time" value="<?= h(substr((string)$b['open_time'], 0, 5)) ?>"></div>
      <div class="field"><label>बंद होती है</label>
        <input type="time" name="close_time" value="<?= h(substr((string)$b['close_time'], 0, 5)) ?>"></div>
    </div>
    <p class="help">इस समय के बाहर आपका सामान ग्राहक को नहीं दिखेगा।</p>
    <button class="btn btn-brand">सेव कीजिए</button>
  </form>

  <div class="box" style="margin-top:14px">
    <h3 style="margin-top:0">Maakit का हिस्सा</h3>
    <?php if ((float)$b['commission_pct'] > 0): ?>
      <div class="big" style="color:var(--brand)"><?= h(rtrim(rtrim(number_format((float)$b['commission_pct'], 1), '0'), '.')) ?>%</div>
      <p class="help">हर बिक्री पर यह हिस्सा आपकी बही में अलग लाइन बनकर दिखता है —
        छिपाकर कुछ नहीं काटा जाता।</p>
    <?php else: ?>
      <div class="big" style="color:var(--muted)">कुछ नहीं</div>
      <p class="help">अभी आपकी बिक्री पर Maakit कुछ नहीं लेता। ग्राहक सिर्फ़ डिलीवरी का पैसा देता है।</p>
    <?php endif; ?>
  </div>

  <div class="box" style="margin-top:14px">
    <h3 style="margin-top:0">मदद चाहिए?</h3>
    <p class="help">कोड खो गया, नाम बदलना है, या कुछ समझ नहीं आ रहा — बस फ़ोन कर दीजिए।</p>
    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:8px">
      <a class="btn btn-brand btn-sm" href="tel:<?= h(MAAKIT_PHONE) ?>">फ़ोन — <?= h(MAAKIT_NUMBER_SHOW) ?></a>
      <a class="btn btn-sm" style="background:#EFEAE0"
         href="<?= h(wa_link(MAAKIT_WA, 'नमस्ते, मैं ' . $b['name'] . ' से बोल रहा हूँ। मदद चाहिए।')) ?>">WhatsApp</a>
    </div>
  </div>

</div></section>
