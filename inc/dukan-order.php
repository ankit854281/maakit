<?php
// ============================================================
//  tab: ऑर्डर
//
//  Dukandar ko sirf teen cheezein chahiye: kiska order hai,
//  kya chahiye, aur kitne ka. Phir teen dabane:
//  मंज़ूर → तैयार है → दे दिया.
//
//  "de diya" dabane par hi paisa hisab me chadhta hai.
// ============================================================
$orders = dukan_orders($pdo, $bid, 7);

$labels = ['naya' => ['नया', 'tag-gold'], 'manzoor' => ['मंज़ूर', 'tag-live'],
           'taiyaar' => ['तैयार है', 'tag-live'], 'diya' => ['दे दिया', 'tag-off'],
           'mana' => ['मना किया', 'tag-off']];
?>
<section><div class="wrap" style="max-width:820px">

  <h2>ऑर्डर</h2>
  <p class="lead">पिछले 7 दिन के, नए सबसे ऊपर।</p>

  <?php if (!$orders): ?>
    <div class="box" style="margin-top:14px">
      <b>अभी कोई ऑर्डर नहीं आया</b>
      <p class="help" style="margin-top:6px">ऑर्डर यहाँ तब दिखेगा जब Maakit उसे आपकी दुकान से
        जोड़ेगा। अपना सामान और दाम चढ़ा देने पर ग्राहक सीधा आपकी दुकान से भी मँगा सकेगा।</p>
      <a class="btn btn-brand btn-sm" href="/shop.php?tab=saaman" style="margin-top:8px">सामान चढ़ाइए</a>
    </div>
  <?php endif; ?>

  <?php foreach ($orders as $o):
      list($lbl, $cls) = $labels[$o['shop_status']] ?? ['नया', 'tag-gold'];
      $naya = $o['shop_status'] === 'naya';
  ?>
    <div class="box" style="margin-top:12px;<?= $naya ? 'border-color:var(--gold)' : '' ?>">
      <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
        <div style="flex:1;min-width:160px">
          <b><?= h($o['order_no']) ?></b> <span class="tag <?= h($cls) ?>"><?= h($lbl) ?></span>
          <div class="meta">
            <?= h($o['customer_name']) ?> · <?= h($o['village']) ?> ·
            <?= h(date('d M, h:i A', strtotime($o['created_at']))) ?>
          </div>
        </div>
        <?php if ((int)$o['goods_amount']): ?>
          <div style="font-size:22px;font-weight:800;color:var(--brand)">₹<?= (int)$o['goods_amount'] ?></div>
        <?php endif; ?>
      </div>

      <div style="border-top:1px solid var(--line);margin-top:10px;padding-top:10px;white-space:pre-line"><?= h($o['items']) ?></div>

      <?php if ($o['goods_note']): ?>
        <div class="meta" style="margin-top:6px">कहा है: <?= h($o['goods_note']) ?></div>
      <?php endif; ?>

      <?php
        // Saaman uthane ka OTP.
        //
        // Maakit ka डिलीवरी वाला jab saaman lene aaye, use ye char ank
        // bataiye. Uske panel me ye daalne se hi "uthaya" darj hoga.
        // Isse aapke paas sabooti rehti hai ki saaman kisko diya —
        // aur "mera saaman gaya kahan" ka jhagda khatm ho jata hai.
        //
        // Ye OTP tabhi dikhta hai jab order kisi delivery wale ke naam
        // par laga ho aur usne abhi uthaya na ho.
        $otp_dikhe = !empty($o['pick_otp']) && empty($o['picked_at'])
                     && !in_array($o['status'], ['Delivered','Cancel'], true);
      ?>
      <?php if ($otp_dikhe): ?>
        <div style="margin-top:10px;background:var(--cream,#FBF4E6);border-left:4px solid var(--gold,#E0A526);border-radius:10px;padding:10px 12px">
          <div class="meta" style="margin:0">डिलीवरी वाले को यही बताइए</div>
          <div style="font-size:26px;font-weight:800;letter-spacing:6px;color:var(--brand,#7A1F1F)"><?= h($o['pick_otp']) ?></div>
          <div class="meta" style="margin:2px 0 0">इसके बिना वो सामान उठा हुआ दर्ज नहीं कर पाएगा।</div>
        </div>
      <?php endif; ?>

      <?php
        // UPI — paisa seedha dukaan ko. Maakit bich me nahi aata.
        $upi = dukan_upi_link($b, (int)$o['goods_amount'], $o['order_no']);
      ?>

      <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:12px">
        <?php if ($o['shop_status'] === 'naya'): ?>
          <form method="post"><input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
            <input type="hidden" name="do" value="order_do"><input type="hidden" name="id" value="<?= (int)$o['id'] ?>">
            <input type="hidden" name="kya" value="manzoor">
            <button class="btn btn-green">मंज़ूर है</button></form>
          <form method="post" onsubmit="return confirm('मना कर दें? Maakit को पता चल जाएगा।')">
            <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
            <input type="hidden" name="do" value="order_do"><input type="hidden" name="id" value="<?= (int)$o['id'] ?>">
            <input type="hidden" name="kya" value="mana">
            <button class="btn btn-sm" style="background:#EFEAE0">नहीं कर पाऊँगा</button></form>
        <?php elseif ($o['shop_status'] === 'manzoor'): ?>
          <form method="post"><input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
            <input type="hidden" name="do" value="order_do"><input type="hidden" name="id" value="<?= (int)$o['id'] ?>">
            <input type="hidden" name="kya" value="taiyaar">
            <button class="btn btn-gold">तैयार है — ले जाइए</button></form>
        <?php elseif ($o['shop_status'] === 'taiyaar'): ?>
          <form method="post"><input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
            <input type="hidden" name="do" value="order_do"><input type="hidden" name="id" value="<?= (int)$o['id'] ?>">
            <input type="hidden" name="kya" value="diya">
            <button class="btn btn-brand">दे दिया</button></form>
          <span class="help" style="align-self:center">“दे दिया” दबाने पर यह आपके हिसाब में चढ़ जाएगा।</span>
        <?php endif; ?>

        <?php if ($upi && in_array($o['shop_status'], ['manzoor','taiyaar'], true)): ?>
          <a class="btn btn-sm" style="background:#EFEAE0" href="<?= h($upi) ?>">UPI से पैसा लीजिए</a>
        <?php endif; ?>

        <?php if ($o['mobile']): ?>
          <a class="btn btn-sm" style="background:#EFEAE0" href="tel:+91<?= h($o['mobile']) ?>">फ़ोन कीजिए</a>
        <?php endif; ?>
      </div>
    </div>
  <?php endforeach; ?>

</div></section>
