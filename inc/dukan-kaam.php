<?php
// ============================================================
//  tab: मेरी दुकान — एक नज़र में आज
//
//  Dukandar subah ise kholega. Isliye sirf wahi cheezein jo
//  usse abhi karni hain: khula/band, naye order, aaj ka paisa,
//  aur "dukaan poori karne" ki checklist (apne aap tick hoti hai).
//  Upar ke number shell (dukan-shell.php) dikhata hai.
// ============================================================
$lena = dukan_lena_hai($pdo, $bid);
$ghanta = (int)date('G');
$salaam = $ghanta < 12 ? t('Good morning', 'सुप्रभात') : ($ghanta < 17 ? t('Good afternoon', 'नमस्ते') : t('Good evening', 'शुभ संध्या'));

// checklist — sab asli halat se
$kaam = [
    ['done' => !empty($b['shop_type']), 'href' => '/shop.php?tab=saaman',
     'en' => 'Choose your shop category', 'hi' => 'दुकान की किस्म चुनिए',
     'sub_en' => 'All its products come in by themselves', 'sub_hi' => 'उसका सारा सामान अपने आप आ जाएगा'],
    ['done' => $sp_it['dikh'] > 0 && $sp_it['baaki'] === 0, 'href' => '/shop.php?tab=saaman#fill',
     'en' => 'Fill all prices', 'hi' => 'सबके दाम भरिए',
     'sub_en' => $sp_it['baaki'] . ' left — customers only see priced items', 'sub_hi' => $sp_it['baaki'] . ' बाकी — दाम वाला सामान ही ग्राहक को दिखता है'],
    ['done' => !empty($b['upi_id']), 'href' => '/shop.php?tab=khata',
     'en' => 'Add your UPI ID', 'hi' => 'अपना UPI डालिए',
     'sub_en' => 'Money goes straight to your account', 'sub_hi' => 'पैसा सीधा आपके खाते में आएगा'],
    ['done' => $sp_it['dikh'] > 0 && $sp_it['bina_photo'] <= $sp_it['dikh'] / 2, 'href' => '/shop.php?tab=saaman&f=nophoto',
     'en' => 'Add photos to products', 'hi' => 'सामान की फ़ोटो लगाइए',
     'sub_en' => $sp_it['bina_photo'] . ' products have no photo', 'sub_hi' => $sp_it['bina_photo'] . ' सामान बिना फ़ोटो के हैं'],
];
$hue = count(array_filter($kaam, function ($k) { return $k['done']; }));
$pct = (int)round($hue / count($kaam) * 100);
?>
<div class="sp-head">
  <div>
    <h1><?= $salaam ?> 👋</h1>
    <p><?= t('Here is your shop today.', 'आज आपकी दुकान का हाल।') ?> <?= h(date('l, d M')) ?></p>
  </div>
</div>

<?php if ($sp_naye): ?>
  <a class="sp-card sp-card-hot sp-new" href="/shop.php?tab=order">
    <span class="sp-new-n"><?= $sp_naye ?></span>
    <span><b><?= $sp_naye === 1 ? t('New order waiting', 'नया ऑर्डर आया है') : t('New orders waiting', 'नए ऑर्डर आए हैं') ?></b>
      <small><?= t('Open and press “Accept” so the delivery partner can come.', 'खोलकर “मंज़ूर” दबाइए, ताकि डिलीवरी वाला आ सके।') ?></small></span>
    <em>→</em>
  </a>
<?php endif; ?>

<div class="sp-grid2">
  <!-- खुली / बंद -->
  <div class="sp-card">
    <div class="sp-card-h"><b><?= t('Shop status', 'दुकान की हालत') ?></b>
      <span class="sp-pill <?= $sp_khuli ? 'ok' : 'off' ?>"><?= $sp_khuli ? t('Open now', 'अभी खुली है') : t('Closed now', 'अभी बंद है') ?></span></div>
    <div class="sp-seg big">
      <?= sp_form_open('khuli', ['open' => '1', 'tab' => 'kaam'], 'data-sp-open') ?><button class="<?= (int)$b['shop_open'] ? 'on' : '' ?>" data-open-seg="1"><?= t('Open', 'खुली है') ?></button></form>
      <?= sp_form_open('khuli', ['open' => '0', 'tab' => 'kaam'], 'data-sp-open') ?><button class="<?= (int)$b['shop_open'] ? '' : 'on' ?>" data-open-seg="0"><?= t('Closed', 'बंद है') ?></button></form>
    </div>
    <p class="sp-note"><?= t('When closed, customers don’t see your products, so no order is wasted. Timings', 'बंद करने पर आपका सामान ग्राहक को नहीं दिखेगा — ऑर्डर बेकार नहीं जाएगा। समय') ?>:
      <b><?= h($sp_samay) ?></b> · <a href="/shop.php?tab=khata#samay"><?= t('change', 'बदलिए') ?></a></p>
  </div>

  <!-- आज का पैसा -->
  <div class="sp-card">
    <div class="sp-card-h"><b><?= t('Money today', 'आज का पैसा') ?></b><a class="sp-link" href="/shop.php?tab=hisab"><?= t('Full account', 'पूरा हिसाब') ?> →</a></div>
    <div class="sp-money">
      <div><small><?= t('Sales', 'बिक्री') ?></small><b>₹<?= number_format((int)$sp_aaj['bikri']) ?></b></div>
      <div><small><?= t('Cash', 'नगद') ?></small><b>₹<?= number_format((int)$sp_aaj['nagad']) ?></b></div>
      <div><small>UPI</small><b>₹<?= number_format((int)$sp_aaj['upi']) ?></b></div>
      <div><small><?= t('Due from Maakit', 'Maakit से लेना') ?></small><b class="<?= $lena > 0 ? 'sp-pos' : 'sp-muted' ?>">₹<?= number_format($lena) ?></b></div>
    </div>
    <?= sp_form_open('khata_add', ['kind' => 'bikri', 'paid_by' => 'nagad', 'tab' => 'kaam'], 'class="sp-quick-sale"') ?>
      <label class="sp-rs"><span>₹</span><input class="sp-in" type="number" name="amount" min="1" max="500000" inputmode="numeric" required placeholder="<?= h(t('Counter sale', 'काउंटर बिक्री')) ?>"></label>
      <button class="sp-btn sp-btn-go"><?= sp_icon('plus', 15) ?> <?= t('Add', 'जोड़िए') ?></button>
    </form>
  </div>
</div>

<!-- दुकान पूरी कीजिए — checklist -->
<div class="sp-card">
  <div class="sp-card-h"><b><?= $pct === 100 ? t('Your shop is fully set up ✓', 'आपकी दुकान पूरी तैयार है ✓') : t('Finish setting up your shop', 'दुकान पूरी तैयार कीजिए') ?></b>
    <span class="sp-muted"><?= $hue ?>/<?= count($kaam) ?></span></div>
  <div class="sp-prog"><i style="width:<?= $pct ?>%"></i></div>
  <div class="sp-checks">
    <?php foreach ($kaam as $k): ?>
      <a class="sp-check<?= $k['done'] ? ' done' : '' ?>" href="<?= h($k['href']) ?>">
        <span class="sp-tick"><?= $k['done'] ? sp_icon('check', 14) : '' ?></span>
        <span><b><?= h(t($k['en'], $k['hi'])) ?></b><?php if (!$k['done']): ?><small><?= h(t($k['sub_en'], $k['sub_hi'])) ?></small><?php endif; ?></span>
      </a>
    <?php endforeach; ?>
  </div>
</div>

<!-- जल्दी के काम -->
<div class="sp-quick">
  <a href="/shop.php?tab=saaman#fill"><?= sp_icon('tag', 20) ?><b><?= t('Fill prices', 'दाम भरिए') ?></b><small><?= $sp_it['baaki'] ?> <?= t('left', 'बाकी') ?></small></a>
  <a href="/shop.php?tab=saaman#add"><?= sp_icon('plus', 20) ?><b><?= t('Add product', 'सामान जोड़िए') ?></b><small><?= t('With a photo', 'फ़ोटो के साथ') ?></small></a>
  <a href="/shop.php?tab=order"><?= sp_icon('bag', 20) ?><b><?= t('Orders', 'ऑर्डर') ?></b><small><?= $sp_chalte ?> <?= t('in progress', 'चल रहे') ?></small></a>
  <a href="/public/dispatch.php?context=shop"><?= sp_icon('truck', 20) ?><b><?= t('Delivery queue', 'डिलीवरी सूची') ?></b><small><?= t('& wholesale enquiries', 'और थोक भाव की माँग') ?></small></a>
  <a href="<?= h($sp_view) ?>" target="_blank" rel="noopener"><?= sp_icon('eye', 20) ?><b><?= t('Customer view', 'ग्राहक का दृश्य') ?></b><small><?= t('See your shop page', 'अपना पन्ना देखिए') ?></small></a>
  <a href="<?= h(wa_link(MAAKIT_WA, 'नमस्ते, मैं ' . $b['name'] . ' से बोल रहा हूँ। मदद चाहिए।')) ?>"><?= sp_icon('phone', 20) ?><b><?= t('Help', 'मदद') ?></b><small><?= t('WhatsApp Maakit', 'Maakit को WhatsApp') ?></small></a>
</div>
