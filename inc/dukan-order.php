<?php
// ============================================================
//  tab: ऑर्डर
//
//  Dukandar ko sirf teen cheezein chahiye: kiska order hai,
//  kya chahiye, aur kitne ka. Phir teen dabane:
//  मंज़ूर → तैयार है → दे दिया.
//
//  "de diya" dabane par hi paisa hisab me chadhta hai.
//  Naya order aate hi panel khud ghanti bajata hai aur ye
//  panna apne aap taaza ho jata hai (dukan-shell-end.php).
// ============================================================
$orders = dukan_orders($pdo, $bid, 7);

$labels = ['naya'    => [t('New', 'नया'), 'new'],
           'manzoor' => [t('Accepted', 'मंज़ूर'), 'go'],
           'taiyaar' => [t('Ready', 'तैयार है'), 'go'],
           'diya'    => [t('Handed over', 'दे दिया'), 'done'],
           'mana'    => [t('Declined', 'मना किया'), 'off']];
$kadam = ['naya' => 0, 'manzoor' => 1, 'taiyaar' => 2, 'diya' => 4];   // 4 = sab poore
$f = get('f'); if (!in_array($f, ['naya', 'chal', 'ho'], true)) $f = '';
$gin = ['' => count($orders), 'naya' => 0, 'chal' => 0, 'ho' => 0];
foreach ($orders as $o) {
    if ($o['shop_status'] === 'naya') $gin['naya']++;
    elseif (in_array($o['shop_status'], ['manzoor', 'taiyaar'], true)) $gin['chal']++;
    else $gin['ho']++;
}
$dikhe = array_filter($orders, function ($o) use ($f) {
    if ($f === 'naya') return $o['shop_status'] === 'naya';
    if ($f === 'chal') return in_array($o['shop_status'], ['manzoor', 'taiyaar'], true);
    if ($f === 'ho')   return in_array($o['shop_status'], ['diya', 'mana'], true);
    return true;
});
$csrf = h(csrf());
?>
<div class="sp-head">
  <div>
    <h1><?= t('Orders', 'ऑर्डर') ?></h1>
    <p><?= t('Last 7 days, newest first', 'पिछले 7 दिन के, नए सबसे ऊपर') ?> · <span class="sp-live-dot"></span><?= t('New orders ring and appear by themselves', 'नया ऑर्डर आते ही घंटी बजेगी, अपने आप दिखेगा') ?></p>
  </div>
</div>

<div class="sp-chips sp-chips-bar">
  <?php foreach (['' => t('All', 'सब'), 'naya' => t('New', 'नए'), 'chal' => t('In progress', 'चल रहे'), 'ho' => t('Completed', 'पूरे')] as $k => $lbl): ?>
    <a class="sp-chip<?= $f === $k ? ' on' : '' ?><?= $k === 'naya' && $gin['naya'] ? ' hot' : '' ?>" href="/shop.php?tab=order<?= $k ? '&amp;f=' . $k : '' ?>"><?= h($lbl) ?> <i><?= (int)$gin[$k] ?></i></a>
  <?php endforeach; ?>
</div>

<?php if (!$orders): ?>
  <div class="sp-card sp-blank">
    <?= sp_icon('bag', 34) ?>
    <b><?= t('No orders yet', 'अभी कोई ऑर्डर नहीं आया') ?></b>
    <small><?= t('An order shows here when Maakit connects it to your shop. Put up your products and prices and customers can also order straight from your shop.', 'ऑर्डर यहाँ तब दिखेगा जब Maakit उसे आपकी दुकान से जोड़ेगा। अपना सामान और दाम चढ़ा देने पर ग्राहक सीधा आपकी दुकान से भी मँगा सकेगा।') ?></small>
    <a class="sp-btn sp-btn-go" href="/shop.php?tab=saaman"><?= t('Put up products', 'सामान चढ़ाइए') ?></a>
  </div>
<?php elseif (!$dikhe): ?>
  <p class="sp-empty"><?= t('No orders here.', 'यहाँ कोई ऑर्डर नहीं।') ?></p>
<?php endif; ?>

<div class="sp-orders">
<?php foreach ($dikhe as $o):
    list($lbl, $cls) = $labels[$o['shop_status']] ?? $labels['naya'];
    $k = $kadam[$o['shop_status']] ?? -1;
    $cheezen = array_values(array_filter(array_map('trim', preg_split('/\r\n|\n|\r/', (string)$o['items']))));
    // Saaman uthane ka OTP — delivery wala lene aaye to yahi batana hai.
    // Isse sabooti rehti hai ki saaman kisko diya.
    $otp_dikhe = !empty($o['pick_otp']) && empty($o['picked_at'])
                 && !in_array($o['status'], ['Delivered', 'Cancel'], true);
    // UPI — paisa seedha dukaan ko. Maakit bich me nahi aata.
    $upi = dukan_upi_link($b, (int)$o['goods_amount'], $o['order_no']);
?>
  <article class="sp-order st-<?= h($cls) ?>">
    <header>
      <div>
        <b><?= h($o['order_no']) ?></b> <span class="sp-pill <?= h($cls) ?>"><?= h($lbl) ?></span>
        <small><?= h($o['customer_name']) ?> · <?= h($o['village']) ?> · <?= h(date('d M, h:i A', strtotime($o['created_at']))) ?></small>
      </div>
      <?php if ((int)$o['goods_amount']): ?><strong>₹<?= number_format((int)$o['goods_amount']) ?></strong><?php endif; ?>
    </header>

    <?php if ($k >= 0): ?>
      <ol class="sp-steps" aria-label="<?= h(t('Order progress', 'ऑर्डर कहाँ तक पहुँचा')) ?>">
        <?php foreach ([t('New', 'नया'), t('Accepted', 'मंज़ूर'), t('Ready', 'तैयार'), t('Handed over', 'दे दिया')] as $i => $sl): ?>
          <li class="<?= $i < $k ? 'done' : ($i === $k ? 'now' : '') ?>"><i></i><span><?= h($sl) ?></span></li>
        <?php endforeach; ?>
      </ol>
    <?php endif; ?>

    <ul class="sp-lines">
      <?php foreach ($cheezen as $c): ?><li><?= h($c) ?></li><?php endforeach; ?>
    </ul>
    <?php if ($o['goods_note']): ?><p class="sp-note"><?= t('Customer note', 'कहा है') ?>: <?= h($o['goods_note']) ?></p><?php endif; ?>

    <?php if ($otp_dikhe): ?>
      <div class="sp-otp">
        <small><?= t('Tell this to the delivery partner', 'डिलीवरी वाले को यही बताइए') ?></small>
        <b><?= h($o['pick_otp']) ?></b>
        <small><?= t('Without it they cannot mark the goods as picked up.', 'इसके बिना वो सामान उठा हुआ दर्ज नहीं कर पाएगा।') ?></small>
      </div>
    <?php endif; ?>

    <footer>
      <?php if ($o['shop_status'] === 'naya'): ?>
        <form method="post"><input type="hidden" name="csrf" value="<?= $csrf ?>"><input type="hidden" name="do" value="order_do"><input type="hidden" name="id" value="<?= (int)$o['id'] ?>"><input type="hidden" name="kya" value="manzoor">
          <button class="sp-btn sp-btn-go"><?= sp_icon('check', 15) ?> <?= t('Accept', 'मंज़ूर है') ?></button></form>
        <form method="post" onsubmit="return confirm(<?= h(json_encode(t('Decline this order? Maakit will be told.', 'मना कर दें? Maakit को पता चल जाएगा।'))) ?>)">
          <input type="hidden" name="csrf" value="<?= $csrf ?>"><input type="hidden" name="do" value="order_do"><input type="hidden" name="id" value="<?= (int)$o['id'] ?>"><input type="hidden" name="kya" value="mana">
          <button class="sp-btn sp-btn-soft"><?= t("Can't do it", 'नहीं कर पाऊँगा') ?></button></form>
      <?php elseif ($o['shop_status'] === 'manzoor'): ?>
        <form method="post"><input type="hidden" name="csrf" value="<?= $csrf ?>"><input type="hidden" name="do" value="order_do"><input type="hidden" name="id" value="<?= (int)$o['id'] ?>"><input type="hidden" name="kya" value="taiyaar">
          <button class="sp-btn sp-btn-gold"><?= t('Ready — come and take it', 'तैयार है — ले जाइए') ?></button></form>
      <?php elseif ($o['shop_status'] === 'taiyaar'): ?>
        <form method="post"><input type="hidden" name="csrf" value="<?= $csrf ?>"><input type="hidden" name="do" value="order_do"><input type="hidden" name="id" value="<?= (int)$o['id'] ?>"><input type="hidden" name="kya" value="diya">
          <button class="sp-btn sp-btn-go"><?= t('Handed over', 'दे दिया') ?></button></form>
        <small class="sp-muted"><?= t('Pressing “Handed over” adds it to your account.', '“दे दिया” दबाने पर यह आपके हिसाब में चढ़ जाएगा।') ?></small>
      <?php endif; ?>
      <span class="sp-grow"></span>
      <?php if ($upi && in_array($o['shop_status'], ['manzoor', 'taiyaar'], true)): ?>
        <a class="sp-icon-btn" href="<?= h($upi) ?>" title="<?= h(t('Collect by UPI', 'UPI से पैसा लीजिए')) ?>"><?= sp_icon('rupee', 17) ?><span class="sr"><?= t('Collect by UPI', 'UPI से पैसा लीजिए') ?></span></a>
      <?php endif; ?>
      <?php if ($o['mobile']): ?>
        <a class="sp-icon-btn" href="<?= h(wa_link('91' . $o['mobile'], t('Hello, about your Maakit order ', 'नमस्ते, आपके Maakit ऑर्डर ') . $o['order_no'])) ?>" title="WhatsApp"><?= sp_icon('wa', 17) ?><span class="sr">WhatsApp</span></a>
        <a class="sp-icon-btn" href="tel:+91<?= h($o['mobile']) ?>" title="<?= h(t('Call', 'फ़ोन कीजिए')) ?>"><?= sp_icon('phone', 17) ?><span class="sr"><?= t('Call', 'फ़ोन कीजिए') ?></span></a>
      <?php endif; ?>
    </footer>
  </article>
<?php endforeach; ?>
</div>
<script>setTimeout(function () { if (!document.querySelector('.sp-main input:focus')) location.reload(); }, 90000);</script>
