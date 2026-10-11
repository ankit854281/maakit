<?php
// ============================================================
//  tab: मेरा सामान
//
//  Dukandar ko saaman JODNA nahi padta. Wo ek baar apni dukaan
//  ki kism chun leta hai (किराना दुकान, मेडिकल…) aur us kism ka
//  SAARA saaman apne aap uski dukaan me aa jata hai.
//
//  Uske baad uska ek hi kaam bachta hai: daam bharna.
//  Isliye panne par sabse upar wahi hai.
//
//  Daam 0 wala saaman grahak ko nahi dikhta — isliye adhoori
//  dukaan kabhi nahi dikhti.
//
//  Naya (Oct 2026):
//   - daam bharte waqt ginti + "draft" phone me bacha rehta hai
//   - dikh rahe saaman me khoj/filter (likhte hi), daam likhte hi
//     apne aap sev, है/ख़त्म ek switch — panna nahi badalta
// ============================================================
require_once __DIR__ . '/catalog.php';
require_once __DIR__ . '/category-picker.php';
$kism   = (string)($b['shop_type'] ?? '');
$kisme  = dukan_types($pdo);
$kismName = '';
foreach ($kisme as $k) { if ($k['slug'] === $kism) { $kismName = catalog_label($k['slug'], $k['name_hi']); break; } }

$baaki  = dukan_daam_baaki($pdo, $bid);      // जिनका दाम भरना है
$starting_prices = $baaki ? dukan_price_defaults($pdo, $bid) : [];
$khoj   = get('q');

// hataye hue (नहीं रखता)
$ht = $pdo->prepare("SELECT * FROM shop_items WHERE business_id=? AND active=0 ORDER BY name");
$ht->execute([$bid]);
$hataye = $ht->fetchAll();

// daam bharne wale panne — ek baar me 40, taaki phone par bhaari na ho
$PER = 25;
$p   = max(1, (int)get('p', 1));
$kul = (int)ceil(count($baaki) / $PER);
$is_panna = array_slice($baaki, ($p - 1) * $PER, $PER);

// khoj se aur saaman (doosri kism ka)
$sujhav = $khoj !== '' ? dukan_suggest($pdo, $bid, $khoj, 40) : [];

// ---- दाम वाला सामान: खोज + फ़िल्टर + पन्ने (50-50) ----
$s  = trim((string)get('s'));
$f  = get('f'); if (!in_array($f, ['hai', 'khatam', 'nophoto'], true)) $f = '';
$lp = max(1, (int)get('lp', 1));
$LPER = 30;
$w = "business_id=? AND active=1 AND price>0"; $args = [$bid];
if ($f === 'hai')     $w .= " AND stock='hai'";
if ($f === 'khatam')  $w .= " AND stock='khatam'";
if ($f === 'nophoto') $w .= " AND (photo IS NULL OR photo='')";
if ($s !== '')        { $w .= " AND name LIKE ?"; $args[] = '%' . $s . '%'; }
$cn = $pdo->prepare("SELECT COUNT(*) FROM shop_items WHERE $w"); $cn->execute($args); $list_kul = (int)$cn->fetchColumn();
$lpages = max(1, (int)ceil($list_kul / $LPER)); if ($lp > $lpages) $lp = $lpages;
$ls = $pdo->prepare("SELECT * FROM shop_items WHERE $w ORDER BY stock='hai' DESC, sold DESC, name LIMIT $LPER OFFSET " . (($lp - 1) * $LPER));
$ls->execute($args); $list = $ls->fetchAll();
$back = http_build_query(array_filter(['s' => $s, 'f' => $f, 'lp' => $lp > 1 ? $lp : '']));
$lurl = function ($chg) use ($s, $f) {
    $q = array_filter(array_merge(['tab' => 'saaman', 's' => $s, 'f' => $f], $chg), function ($v) { return $v !== '' && $v !== null; });
    return '/shop.php?' . http_build_query($q) . '#live';
};
$priced = $sp_it['dikh'] + $sp_it['khatam'];
$bharaPct = ($priced + $sp_it['baaki']) ? (int)round($priced / ($priced + $sp_it['baaki']) * 100) : 0;
?>
<div class="sp-head">
  <div>
    <h1><?= t('My products', 'मेरा सामान') ?></h1>
    <p><?= $kismName !== '' ? h($kismName) . ' · ' : '' ?><?= number_format($sp_it['dikh']) ?> <?= t('visible to customers', 'ग्राहक को दिख रहे') ?><?= $sp_it['baaki'] ? ' · ' . number_format($sp_it['baaki']) . ' ' . t('need a price', 'का दाम बाकी') : '' ?></p>
  </div>
  <div class="sp-head-r">
    <a class="sp-btn sp-btn-soft" href="/bazaar.php" target="_blank" rel="noopener"><?= sp_icon('eye', 15) ?> <?= t('Customer prices', 'ग्राहक के दाम') ?></a>
    <?php if ($kism !== ''): ?><a class="sp-btn sp-btn-go" href="#add" data-open-add><?= sp_icon('plus', 15) ?> <?= t('Add product', 'सामान जोड़िए') ?></a><?php endif; ?>
  </div>
</div>

<?php if ($kism === ''): ?>

  <!-- ---------- पहली बार: सिर्फ़ किस्म चुननी है ---------- -->
  <form method="post" class="sp-card sp-card-hot sp-start">
    <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
    <input type="hidden" name="do" value="kism">
    <div class="sp-step"><span>1</span><div>
      <b><?= t('What kind of shop do you have?', 'आपकी दुकान किस किस्म की है?') ?></b>
      <small><?= t('Just choose it once. All products of that kind come into your shop by themselves — no adding one by one.', 'बस यह बता दीजिए। उस किस्म का सारा सामान आपकी दुकान में अपने आप आ जाएगा — एक-एक करके जोड़ना नहीं पड़ेगा।') ?>
        <?= t('Recent prices for matching goods and packs will be filled where available. Check or change them, then save your own prices.', 'उसी सामान और पैक के हाल के दाम उपलब्ध हों तो भरकर आएँगे। जाँचिए या बदलिए, फिर अपने दाम सेव कीजिए।') ?></small>
    </div></div>
    <?php catalog_picker($pdo, '', true); ?>
    <button class="sp-btn sp-btn-go sp-btn-wide"><?= t('Bring in the products', 'सामान ले आइए') ?> →</button>
  </form>

<?php else: ?>

  <!-- ---------- 1. दाम भरिए — सबसे ज़रूरी काम ---------- -->
  <?php if ($baaki): ?>
    <div class="sp-card sp-card-hot" id="fill">
      <div class="sp-card-h">
        <b><?= t('Fill prices', 'दाम भरिए') ?> <span class="sp-muted">· <?= number_format(count($baaki)) ?> <?= t('left', 'बाकी') ?></span></b>
        <span class="sp-muted"><?= $bharaPct ?>% <?= t('done', 'पूरा') ?></span>
      </div>
      <div class="sp-prog"><i style="width:<?= $bharaPct ?>%"></i></div>
      <details class="sp-tip">
        <summary><?= t('How this works', 'यह कैसे काम करता है') ?></summary>
        <p><?= t('Fill the price of what you keep — it starts showing to customers right away. Mark “Don’t keep” on things you don’t sell. Items without a price are hidden, so there is no hurry — fill a few every day.', 'जो आपके पास है उसका दाम भर दीजिए — भरते ही वह ग्राहक को दिखने लगेगा। जो आप नहीं रखते, उस पर “नहीं रखता” लगा दीजिए। बिना दाम वाला सामान ग्राहक को नहीं दिखता, इसलिए जल्दी नहीं — रोज़ थोड़ा-थोड़ा भरिए।') ?></p>
        <p><?= t('Prefilled prices are references from another approved shop, not your confirmed prices. The shop and date are shown. Change any price or pack before saving. Empty fields still need a price; existing shop prices are never replaced.', 'पहले से भरे दाम दूसरी मंज़ूर दुकान के संदर्भ हैं, आपके पक्के दाम नहीं। दुकान और तारीख नीचे दिखेंगे। दाम या पैक बदलकर सेव कर सकते हैं। खाली जगह का दाम भरिए; आपके पुराने दाम नहीं बदलेंगे।') ?></p>
      </details>

      <form method="post" action="/shop.php?tab=saaman&amp;p=<?= $p ?>" class="sp-fill" data-fill data-draft="spDraft<?= (int)$bid ?>p<?= $p ?>">
        <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
        <input type="hidden" name="do" value="daam_bharo">
        <div class="sp-fill-h" aria-hidden="true"><span><?= t('Product', 'सामान') ?></span><span><?= t('Pack', 'नाप') ?></span><span><?= t('Price', 'दाम') ?></span><span></span></div>
        <?php foreach ($is_panna as $it): $ref = $starting_prices[$it['id']] ?? null; ?>
          <div class="sp-fill-r" data-fill-row>
            <div class="sp-fill-n">
              <b><?= h($it['name']) ?></b>
              <?php if ($ref): ?><small><?= t('Reference', 'संदर्भ') ?>: <?= h($ref['shop_name']) ?> · <?= h(date('d-m-Y', strtotime($ref['updated_at']))) ?></small><?php endif; ?>
            </div>
            <input class="sp-in sp-in-unit" type="text" name="naap[<?= (int)$it['id'] ?>]" value="<?= h($it['unit']) ?>" aria-label="<?= h(t('Pack', 'नाप')) ?>">
            <label class="sp-rs"><span>₹</span><input class="sp-in" type="number" name="daam[<?= (int)$it['id'] ?>]" min="1" max="200000" inputmode="numeric"
                   placeholder="<?= h(t('Price', 'दाम')) ?>" value="<?= $ref ? (int)$ref['price'] : '' ?>"<?= $ref ? ' data-ref="1"' : '' ?>
                   aria-label="<?= h(t('Price of', 'दाम —')) ?> <?= h($it['name']) ?>"></label>
            <label class="sp-nahi"><input type="checkbox" name="nahi[<?= (int)$it['id'] ?>]" value="1"><span><?= t("Don't keep", 'नहीं रखता') ?></span></label>
          </div>
        <?php endforeach; ?>

        <div class="sp-savebar">
          <span data-fill-count><?= t('Fill prices above', 'ऊपर दाम भरिए') ?></span>
          <button class="sp-btn sp-btn-go"><?= sp_icon('check', 15) ?> <span class="sp-hide-s"><?= t('Confirm and save my prices', 'मेरे दाम पक्के करके सेव कीजिए') ?></span><span class="sp-show-s"><?= t('Save prices', 'दाम सेव कीजिए') ?></span></button>
        </div>
      </form>

      <?php if ($kul > 1): ?>
        <div class="sp-pager">
          <?php if ($p > 1): ?><a class="sp-btn sp-btn-soft" href="/shop.php?tab=saaman&amp;p=<?= $p - 1 ?>#fill">← <?= t('Previous', 'पिछला') ?></a><?php endif; ?>
          <span><?= t('Page', 'पन्ना') ?> <?= $p ?> / <?= $kul ?></span>
          <?php if ($p < $kul): ?><a class="sp-btn sp-btn-soft" href="/shop.php?tab=saaman&amp;p=<?= $p + 1 ?>#fill"><?= t('Next', 'अगला') ?> →</a><?php endif; ?>
        </div>
      <?php endif; ?>
    </div>
  <?php else: ?>
    <div class="sp-card sp-card-ok">
      <b><?= sp_icon('check', 16) ?> <?= t('Every product has a price', 'सब सामान का दाम भरा हुआ है') ?></b>
      <small><?= t('Keep something else? Add it below.', 'कुछ और रखते हैं तो नीचे जोड़ लीजिए।') ?></small>
    </div>
  <?php endif; ?>

  <!-- ---------- 2. जो ग्राहक को दिख रहा है ---------- -->
  <div class="sp-card" id="live">
    <div class="sp-card-h"><b><?= t('Your price list', 'आपकी दाम-सूची') ?> <span class="sp-muted">· <?= number_format($list_kul) ?></span></b>
      <span class="sp-muted sp-hide-s"><?= t('Type a price — it saves by itself', 'दाम लिखिए — अपने आप सेव होगा') ?></span></div>

    <form class="sp-tools" method="get" action="/shop.php#live" data-live-filter>
      <input type="hidden" name="tab" value="saaman">
      <?php if ($f !== ''): ?><input type="hidden" name="f" value="<?= h($f) ?>"><?php endif; ?>
      <label class="sp-search"><?= sp_icon('search', 16) ?><input type="search" name="s" value="<?= h($s) ?>" placeholder="<?= h(t('Search your products…', 'अपना सामान खोजिए…')) ?>" autocomplete="off"></label>
      <div class="sp-chips">
        <?php foreach (['' => [t('All', 'सब'), $priced], 'hai' => [t('In stock', 'है'), $sp_it['dikh']], 'khatam' => [t('Out of stock', 'ख़त्म'), $sp_it['khatam']], 'nophoto' => [t('No photo', 'बिना फ़ोटो'), $sp_it['bina_photo']]] as $k => [$lbl, $n]): ?>
          <a class="sp-chip<?= $f === $k ? ' on' : '' ?>" href="<?= h($lurl(['f' => $k, 'lp' => ''])) ?>"><?= h($lbl) ?> <i><?= number_format($n) ?></i></a>
        <?php endforeach; ?>
      </div>
    </form>

    <div id="spList" data-live-list>
      <?php if (!$list): ?>
        <p class="sp-empty"><?= $s !== '' || $f !== '' ? t('Nothing matches. Try another word or filter.', 'कुछ नहीं मिला। दूसरा शब्द या फ़िल्टर आज़माइए।') : t('Nothing yet. Fill prices above and products appear here.', 'अभी कुछ नहीं। ऊपर दाम भरते ही यहाँ आने लगेगा।') ?></p>
      <?php endif; ?>
      <?php foreach ($list as $it): $hai = $it['stock'] === 'hai'; ?>
        <div class="sp-item<?= $hai ? '' : ' is-out' ?>" data-row>
          <?php if ($it['photo']): ?>
            <img class="sp-thumb" src="/uploads/<?= h($it['photo']) ?>" alt="" width="44" height="44" loading="lazy">
          <?php else: ?>
            <span class="sp-thumb sp-thumb-0" aria-hidden="true"><?= h(mb_substr($it['name'], 0, 1)) ?></span>
          <?php endif; ?>
          <div class="sp-item-t">
            <b><?= h($it['name']) ?></b>
            <small><?= h($it['unit']) ?><?= (int)$it['sold'] ? ' · ' . (int)$it['sold'] . ' ' . t('sold', 'बार बिका') : '' ?></small>
          </div>
          <?= sp_form_open('item_daam', ['id' => (int)$it['id'], 'back' => $back], 'class="sp-pform" data-ajax="price"') ?>
            <label class="sp-rs"><span>₹</span><input class="sp-in" type="number" name="price" value="<?= (int)$it['price'] ?>" min="1" max="200000" inputmode="numeric" aria-label="<?= h(t('Price of', 'दाम —')) ?> <?= h($it['name']) ?>"></label>
            <button class="sp-btn sp-btn-soft sp-nojs"><?= t('Save', 'सेव') ?></button>
            <i class="sp-state" aria-hidden="true"></i>
          </form>
          <?= sp_form_open('item_stock', ['id' => (int)$it['id'], 'back' => $back], 'data-ajax="stock"') ?>
            <button class="sp-switch sm<?= $hai ? ' on' : '' ?>" role="switch" aria-checked="<?= $hai ? 'true' : 'false' ?>" data-on="<?= h(t('In stock', 'है')) ?>" data-off="<?= h(t('Out', 'ख़त्म')) ?>"
                    aria-label="<?= h(t('In stock?', 'है?')) ?> <?= h($it['name']) ?>"><i aria-hidden="true"></i><span><?= $hai ? t('In stock', 'है') : t('Out', 'ख़त्म') ?></span></button>
          </form>
          <form method="post" enctype="multipart/form-data" class="sp-photo-f">
            <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
            <input type="hidden" name="do" value="item_photo"><input type="hidden" name="id" value="<?= (int)$it['id'] ?>">
            <label class="sp-icon-btn" title="<?= h($it['photo'] ? t('Change photo', 'फ़ोटो बदलिए') : t('Add photo', 'फ़ोटो लगाइए')) ?>">
              <?= sp_icon('cam', 17) ?><span class="sr"><?= $it['photo'] ? t('Change photo', 'फ़ोटो बदलिए') : t('Add photo', 'फ़ोटो लगाइए') ?></span>
              <input type="file" name="photo" accept="image/*" capture="environment" hidden onchange="this.form.submit()">
            </label>
          </form>
        </div>
      <?php endforeach; ?>

      <?php if ($lpages > 1): ?>
        <div class="sp-pager">
          <?php if ($lp > 1): ?><a class="sp-btn sp-btn-soft" href="<?= h($lurl(['lp' => $lp - 1])) ?>">← <?= t('Previous', 'पिछला') ?></a><?php endif; ?>
          <span><?= t('Page', 'पन्ना') ?> <?= $lp ?> / <?= $lpages ?></span>
          <?php if ($lp < $lpages): ?><a class="sp-btn sp-btn-soft" href="<?= h($lurl(['lp' => $lp + 1])) ?>"><?= t('Next', 'अगला') ?> →</a><?php endif; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- ---------- 3. और सामान — दूसरी किस्म का भी ---------- -->
  <details class="sp-card sp-more" id="add"<?= $khoj !== '' ? ' open' : '' ?>>
    <summary><b><?= sp_icon('plus', 16) ?> <?= t('Add more products', 'और सामान जोड़िए') ?></b><small><?= t('From the full list of 1500+ items, or your own', '1500 से ज़्यादा चीज़ों की सूची से, या अपने हाथ से') ?></small></summary>

    <form method="get" action="/shop.php#add" class="sp-tools">
      <input type="hidden" name="tab" value="saaman">
      <label class="sp-search"><?= sp_icon('search', 16) ?><input type="search" name="q" value="<?= h($khoj) ?>" placeholder="<?= h(t('Search — e.g. mobile cover, paint', 'ढूँढिए — जैसे मोबाइल कवर, पेंट')) ?>"></label>
      <button class="sp-btn sp-btn-soft"><?= t('Search', 'ढूँढिए') ?></button>
    </form>

    <?php if ($khoj !== ''): ?>
      <?php if (!$sujhav): ?>
        <p class="sp-empty">“<?= h($khoj) ?>” — <?= t('nothing found. Add it yourself below.', 'का कुछ नहीं मिला। नीचे अपने हाथ से जोड़ लीजिए।') ?></p>
      <?php else: ?>
        <form method="post" class="sp-fill" data-fill>
          <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
          <input type="hidden" name="do" value="item_bulk">
          <input type="hidden" name="q" value="<?= h($khoj) ?>">
          <?php foreach ($sujhav as $sg): ?>
            <div class="sp-fill-r" data-fill-row>
              <div class="sp-fill-n"><b><?= h($sg['name']) ?></b>
                <small><?= h($sg['sub_cat']) ?><?= $sg['name'] !== $sg['name_en'] ? ' · ' . h($sg['name_en']) : '' ?></small></div>
              <input class="sp-in sp-in-unit" type="text" name="naap[<?= (int)$sg['id'] ?>]" value="<?= h($sg['unit']) ?>" aria-label="<?= h(t('Pack', 'नाप')) ?>">
              <label class="sp-rs"><span>₹</span><input class="sp-in" type="number" name="daam[<?= (int)$sg['id'] ?>]" min="1" max="200000" inputmode="numeric" placeholder="<?= h(t('Price', 'दाम')) ?>"></label>
              <span></span>
            </div>
          <?php endforeach; ?>
          <div class="sp-savebar">
            <span data-fill-count><?= t('Fill prices above', 'ऊपर दाम भरिए') ?></span>
            <button class="sp-btn sp-btn-go"><?= t('Add the ones with a price', 'जिनका दाम भरा है, वे जोड़ दीजिए') ?></button>
          </div>
        </form>
      <?php endif; ?>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data" class="sp-own">
      <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
      <input type="hidden" name="do" value="item_add">
      <b><?= t('Add by hand', 'अपने हाथ से जोड़िए') ?></b>
      <small class="sp-muted"><?= t('Not in the list at all — like something you make yourself.', 'जो पूरी सूची में भी नहीं है — जैसे अपनी बनाई चीज़।') ?></small>
      <div class="sp-own-g">
        <label class="sp-field"><span><?= t('Product name', 'सामान का नाम') ?></span><input type="text" name="name" required placeholder="<?= h(t('e.g. Desi ghee, local sweets', 'जैसे: देसी घी, लोकल मिठाई')) ?>"></label>
        <label class="sp-field"><span><?= t('Pack', 'नाप') ?></span><input type="text" name="unit" placeholder="<?= h(t('e.g. 1 kg / 250 g', 'जैसे: 1 किलो / 250 ग्राम')) ?>"></label>
        <label class="sp-field"><span><?= t('Price ₹', 'दाम ₹') ?></span><input type="number" name="price" min="1" max="200000" inputmode="numeric" required></label>
        <label class="sp-field"><span><?= t('Photo (take it with your phone)', 'फ़ोटो (फ़ोन से खींच लीजिए)') ?></span><input type="file" name="photo" accept="image/*" capture="environment"></label>
      </div>
      <button class="sp-btn sp-btn-go"><?= sp_icon('plus', 15) ?> <?= t('Add', 'जोड़िए') ?></button>
    </form>

    <form method="post" class="sp-own">
      <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
      <input type="hidden" name="do" value="kism">
      <b><?= t('Change shop category', 'दुकान की किस्म बदलिए') ?></b>
      <small class="sp-muted"><?= t('Products of the new category are added too. Old ones are not removed.', 'नई किस्म का सामान भी आ जाएगा। पुराना हटेगा नहीं।') ?></small>
      <?php catalog_picker($pdo, $kism, true); ?>
      <button class="sp-btn sp-btn-soft"><?= t('Save', 'सेव') ?></button>
    </form>
  </details>

  <!-- ---------- 4. नहीं रखता ---------- -->
  <?php if ($hataye): ?>
    <details class="sp-card sp-more">
      <summary><b><?= t("Products you don't keep", 'जो आप नहीं रखते') ?> <span class="sp-muted">· <?= count($hataye) ?></span></b><small><?= t('Removed by mistake? Bring it back.', 'ग़लती से हट गया हो तो वापस ला लीजिए।') ?></small></summary>
      <?php foreach (array_slice($hataye, 0, 100) as $it): ?>
        <div class="sp-row">
          <div class="sp-row-t sp-muted"><?= h($it['name']) ?></div>
          <?= sp_form_open('wapas', ['id' => (int)$it['id']]) ?><button class="sp-btn sp-btn-soft"><?= t('Bring back', 'वापस लाइए') ?></button></form>
        </div>
      <?php endforeach; ?>
    </details>
  <?php endif; ?>

<?php endif; ?>

<script>
/* मेरा सामान: दाम भरने की गिनती + draft, और सूची में लिखते-लिखते खोज */
document.addEventListener('DOMContentLoaded', function () {
  var L = {
    filled: <?= json_encode(t('{n} prices filled', '{n} दाम भरे')) ?>,
    skip:   <?= json_encode(t('{n} marked “don’t keep”', '{n} पर “नहीं रखता”')) ?>,
    none:   <?= json_encode(t('Fill prices above', 'ऊपर दाम भरिए')) ?>,
    draft:  <?= json_encode(t('Your unsaved prices were brought back.', 'आपके बिना सेव किए दाम वापस भर दिए।')) ?>
  };
  document.querySelectorAll('form[data-fill]').forEach(function (f) {
    var out = f.querySelector('[data-fill-count]'), key = f.dataset.draft, store = null;
    try { store = key ? window.localStorage : null; } catch (e) {}
    function count() {
      var n = 0, x = 0, d = {};
      f.querySelectorAll('[data-fill-row]').forEach(function (r) {
        var p = r.querySelector('input[name^="daam"]'), u = r.querySelector('input[name^="naap"]'), c = r.querySelector('input[type=checkbox]');
        var off = c && c.checked;
        r.classList.toggle('is-skip', !!off); if (p) p.disabled = !!off;
        if (off) { x++; d['x' + p.name] = 1; }
        else if (p && parseInt(p.value, 10) > 0) { n++; r.classList.add('is-ok'); if (!p.dataset.ref || p.dataset.touched) d[p.name] = p.value; }
        else r.classList.remove('is-ok');
        if (u && u.dataset.touched) d[u.name] = u.value;
      });
      var parts = [];
      if (n) parts.push(L.filled.replace('{n}', n));
      if (x) parts.push(L.skip.replace('{n}', x));
      if (out) out.textContent = parts.length ? parts.join(' · ') : L.none;
      if (store) try { store.setItem(key, JSON.stringify(d)); } catch (e) {}
    }
    // pichhla draft wapas
    if (store) try {
      var d = JSON.parse(store.getItem(key) || '{}'), back = 0;
      Object.keys(d).forEach(function (k) {
        if (k.charAt(0) === 'x') { var c = f.querySelector('input[name="' + k.slice(1).replace('daam', 'nahi') + '"]'); if (c && !c.checked) { c.checked = true; back++; } return; }
        var i = f.querySelector('input[name="' + k + '"]');
        if (i && i.value !== d[k]) { i.value = d[k]; i.dataset.touched = 1; back++; }
      });
      if (back && window.spSay) window.spSay(L.draft);
    } catch (e) {}
    f.addEventListener('input', function (e) { if (e.target.name) e.target.dataset.touched = 1; count(); });
    f.addEventListener('change', count);
    f.addEventListener('submit', function () { f.querySelectorAll('input:disabled').forEach(function (i) { i.disabled = false; i.value = ''; }); if (store) try { store.removeItem(key); } catch (e) {} });
    // Enter dabane par agla daam
    f.addEventListener('keydown', function (e) {
      if (e.key !== 'Enter' || e.target.type !== 'number') return;
      e.preventDefault();
      var all = Array.prototype.slice.call(f.querySelectorAll('input[type=number]:not(:disabled)')), i = all.indexOf(e.target);
      if (all[i + 1]) all[i + 1].focus();
    });
    count();
  });

  // सूची: लिखते ही खोज (panna nahi badalta)
  var ff = document.querySelector('form[data-live-filter]'), box = document.querySelector('[data-live-list]');
  if (ff && box && window.fetch && window.DOMParser) {
    var inp = ff.querySelector('input[name=s]'), tm, last = inp.value;
    function load(url) {
      box.classList.add('loading');
      fetch(url, { credentials: 'same-origin' }).then(function (r) { return r.text(); }).then(function (html) {
        var doc = new DOMParser().parseFromString(html, 'text/html'), nb = doc.querySelector('[data-live-list]');
        if (nb) { box.innerHTML = nb.innerHTML; if (window.spBind) window.spBind(box); }
        var nc = doc.querySelector('.sp-chips'), oc = ff.querySelector('.sp-chips'); if (nc && oc) oc.innerHTML = nc.innerHTML;
        box.classList.remove('loading');
        history.replaceState(null, '', url);
      }).catch(function () { location.href = url; });
    }
    inp.addEventListener('input', function () {
      clearTimeout(tm);
      tm = setTimeout(function () {
        if (inp.value === last) return; last = inp.value;
        var u = new URL(location.href); u.searchParams.set('tab', 'saaman');
        if (inp.value.trim()) u.searchParams.set('s', inp.value.trim()); else u.searchParams.delete('s');
        u.searchParams.delete('lp'); u.hash = 'live';
        load(u.pathname + u.search + '#live');
      }, 350);
    });
    ff.addEventListener('submit', function (e) { e.preventDefault(); });
  }
  // "Add product" button khol de
  document.querySelectorAll('[data-open-add]').forEach(function (a) {
    a.addEventListener('click', function () { var d = document.getElementById('add'); if (d) d.open = true; });
  });
  if (location.hash === '#add') { var d = document.getElementById('add'); if (d) d.open = true; }
});
</script>
