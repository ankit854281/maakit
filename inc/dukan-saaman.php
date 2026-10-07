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
// ============================================================
require_once __DIR__ . '/catalog.php';
$kism   = (string)($b['shop_type'] ?? '');
$kisme  = dukan_types($pdo);
$kismName = '';
foreach ($kisme as $k) { if ($k['slug'] === $kism) { $kismName = catalog_label($k['slug'], $k['name_hi']); break; } }

$baaki  = dukan_daam_baaki($pdo, $bid);      // जिनका दाम भरना है
$starting_prices = $baaki ? dukan_price_defaults($pdo, $bid) : [];
$chalu  = dukan_items($pdo, $bid, true);     // जो ग्राहक को दिख रहे हैं
$khoj   = get('q');

// hataye hue (नहीं रखता)
$ht = $pdo->prepare("SELECT * FROM shop_items WHERE business_id=? AND active=0 ORDER BY name");
$ht->execute([$bid]);
$hataye = $ht->fetchAll();

// "khatam" lage hue
$kt = $pdo->prepare("SELECT * FROM shop_items WHERE business_id=? AND active=1 AND stock='khatam' ORDER BY name");
$kt->execute([$bid]);
$khatam = $kt->fetchAll();

// daam bharne wale panne — ek baar me 40, taaki phone par bhaari na ho
$PER = 40;
$p   = max(1, (int)get('p', 1));
$kul = (int)ceil(count($baaki) / $PER);
$is_panna = array_slice($baaki, ($p - 1) * $PER, $PER);

// khoj se aur saaman (doosri kism ka)
$sujhav = $khoj !== '' ? dukan_suggest($pdo, $bid, $khoj, 40) : [];
?>
<section><div class="wrap" style="max-width:820px">

  <h2>मेरा सामान</h2>
  <a class="chip" href="/bazaar.php"><?= t('View customer categories & prices', 'ग्राहक की categories और दाम देखिए') ?></a>

<?php if ($kism === ''): ?>

  <!-- ---------- पहली बार: सिर्फ़ किस्म चुननी है ---------- -->
  <form method="post" class="box" style="margin-top:14px;border-color:var(--gold)">
    <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
    <input type="hidden" name="do" value="kism">
    <b style="font-size:18px">आपकी दुकान किस किस्म की है?</b>
    <p class="help" style="margin-top:6px">बस यह बता दीजिए। उस किस्म का <b>सारा सामान</b>
      आपकी दुकान में अपने आप आ जाएगा — एक-एक करके जोड़ना नहीं पड़ेगा।
      <?= t('Recent prices for matching goods and packs will be filled where available. Check or change them, then save your own prices.', 'उसी सामान और पैक के हाल के दाम उपलब्ध हों तो भरकर आएँगे। जाँचिए या बदलिए, फिर अपने दाम सेव कीजिए।') ?></p>
    <select name="shop_type" style="margin-top:12px" required>
      <option value="">— चुनिए —</option>
      <?php foreach ($kisme as $k): ?>
        <option value="<?= h($k['slug']) ?>"><?= h(catalog_label($k['slug'], $k['name_hi'])) ?> (<?= (int)$k['ginti'] ?> सामान)</option>
      <?php endforeach; ?>
    </select>
    <button class="btn btn-brand" style="margin-top:12px;width:100%;font-size:17px">सामान ले आइए</button>
  </form>

<?php else: ?>

  <p class="lead"><?= h($kismName) ?> ·
    <?= count($chalu) ?> सामान ग्राहक को दिख रहा है<?= count($baaki) ? ', ' . count($baaki) . ' का दाम भरना बाकी' : '' ?>
  </p>

  <!-- ---------- 1. दाम भरिए — सबसे ज़रूरी काम ---------- -->
  <?php if ($baaki): ?>
    <div class="box" style="margin-top:14px;border-color:var(--gold)">
      <b style="font-size:18px">दाम भरिए</b>
      <p class="help" style="margin-top:6px">
        जो आपके पास है उसका दाम भर दीजिए — भरते ही वह ग्राहक को दिखने लगेगा।<br>
        जो आप नहीं रखते, उस पर <b>“नहीं रखता”</b> लगा दीजिए — वह हट जाएगा।<br>
        बिना दाम वाला सामान ग्राहक को <b>नहीं</b> दिखता, इसलिए जल्दी करने की ज़रूरत नहीं —
        रोज़ थोड़ा-थोड़ा भरते रहिए।
      </p>

      <p class="help"><?= t('Prefilled prices are references from another approved shop, not your confirmed prices. The shop and date are shown. Change any price or pack before saving. Empty fields still need a price; existing shop prices are never replaced.', 'पहले से भरे दाम दूसरी मंज़ूर दुकान के संदर्भ हैं, आपके पक्के दाम नहीं। दुकान और तारीख नीचे दिखेंगे। दाम या पैक बदलकर सेव कर सकते हैं। खाली जगह का दाम भरिए; आपके पुराने दाम नहीं बदलेंगे।') ?></p>
      <form method="post" style="margin-top:14px">
        <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
        <input type="hidden" name="do" value="daam_bharo">

        <?php foreach ($is_panna as $it): ?>
          <div style="border-top:1px solid var(--line);padding:10px 0;display:flex;gap:8px;align-items:center;flex-wrap:wrap">
            <div style="flex:1;min-width:120px">
              <b><?= h($it['name']) ?></b>
              <?php if (isset($starting_prices[$it['id']])): $ref=$starting_prices[$it['id']]; ?>
                <div class="meta"><?= t('Reference', 'संदर्भ') ?>: <?= h($ref['shop_name']) ?> · <?= h(date('d-m-Y',strtotime($ref['updated_at']))) ?></div>
              <?php endif; ?>
            </div>
            <input type="text" name="naap[<?= (int)$it['id'] ?>]" value="<?= h($it['unit']) ?>"
                   style="width:88px;flex:none" aria-label="नाप">
            <div style="display:flex;align-items:center;gap:4px;flex:none">
              <span style="font-weight:700">₹</span>
              <input type="number" name="daam[<?= (int)$it['id'] ?>]" min="1" max="200000"
                     inputmode="numeric" style="width:80px" placeholder="दाम"
                     value="<?= isset($starting_prices[$it['id']]) ? (int)$starting_prices[$it['id']]['price'] : '' ?>"
                     aria-label="<?= h($it['name']) ?> का दाम">
            </div>
            <label style="display:flex;align-items:center;gap:5px;font-size:14px;color:var(--muted);flex:none;cursor:pointer">
              <input type="checkbox" name="nahi[<?= (int)$it['id'] ?>]" value="1" style="width:auto;margin:0">
              नहीं रखता
            </label>
          </div>
        <?php endforeach; ?>

        <button class="btn btn-brand" style="margin-top:16px;width:100%;font-size:17px"><?= t('Confirm and save my prices', 'मेरे दाम पक्के करके सेव कीजिए') ?></button>
      </form>

      <?php if ($kul > 1): ?>
        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:14px;align-items:center">
          <span class="meta">पन्ना <?= $p ?> / <?= $kul ?></span>
          <?php if ($p > 1): ?>
            <a class="btn btn-sm" style="background:#EFEAE0" href="/shop.php?tab=saaman&amp;p=<?= $p-1 ?>">← पिछला</a>
          <?php endif; ?>
          <?php if ($p < $kul): ?>
            <a class="btn btn-sm" style="background:#EFEAE0" href="/shop.php?tab=saaman&amp;p=<?= $p+1 ?>">अगला →</a>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </div>
  <?php else: ?>
    <div class="box ok" style="margin-top:14px">
      <b>सब सामान का दाम भरा हुआ है ✓</b>
      <p class="help" style="margin-top:4px">कुछ और रखते हैं तो नीचे ढूँढ कर जोड़ लीजिए।</p>
    </div>
  <?php endif; ?>

  <!-- ---------- 2. और सामान — दूसरी किस्म का भी ---------- -->
  <details class="box" style="margin-top:14px">
    <summary style="font-weight:700;cursor:pointer;font-size:17px">
      और सामान जोड़िए <span class="meta" style="font-weight:400">— जो इस सूची में नहीं है</span>
    </summary>

    <form method="get" style="display:flex;gap:8px;margin-top:12px">
      <input type="hidden" name="tab" value="saaman">
      <input type="search" name="q" value="<?= h($khoj) ?>" placeholder="ढूँढिए — जैसे मोबाइल कवर, पेंट" style="flex:1">
      <button class="btn btn-sm" style="background:#EFEAE0">ढूँढिए</button>
    </form>
    <p class="help" style="margin-top:8px">पूरी सूची में <b>1500 से ज़्यादा</b> चीज़ें हैं —
      हर तरह की दुकान की। जो चाहिए, ढूँढ कर दाम भर दीजिए।</p>

    <?php if ($khoj !== ''): ?>
      <?php if (!$sujhav): ?>
        <p class="help" style="margin-top:12px">“<?= h($khoj) ?>” का कुछ नहीं मिला।
          नीचे अपने हाथ से जोड़ लीजिए।</p>
      <?php else: ?>
        <form method="post" style="margin-top:12px">
          <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
          <input type="hidden" name="do" value="item_bulk">
          <input type="hidden" name="q" value="<?= h($khoj) ?>">
          <?php foreach ($sujhav as $s): ?>
            <div style="border-top:1px solid var(--line);padding:9px 0;display:flex;gap:8px;align-items:center;flex-wrap:wrap">
              <div style="flex:1;min-width:120px">
                <b><?= h($s['name']) ?></b>
                <div class="meta"><?= h($s['sub_cat']) ?><?= $s['name'] !== $s['name_en'] ? ' · ' . h($s['name_en']) : '' ?></div>
              </div>
              <input type="text" name="naap[<?= (int)$s['id'] ?>]" value="<?= h($s['unit']) ?>"
                     style="width:88px;flex:none" aria-label="नाप">
              <div style="display:flex;align-items:center;gap:4px;flex:none">
                <span style="font-weight:700">₹</span>
                <input type="number" name="daam[<?= (int)$s['id'] ?>]" min="1" max="200000"
                       inputmode="numeric" style="width:80px" placeholder="दाम">
              </div>
            </div>
          <?php endforeach; ?>
          <button class="btn btn-brand" style="margin-top:14px;width:100%">जिनका दाम भरा है, वे जोड़ दीजिए</button>
        </form>
      <?php endif; ?>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data" style="margin-top:16px;border-top:2px solid var(--line);padding-top:14px">
      <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
      <input type="hidden" name="do" value="item_add">
      <b>अपने हाथ से जोड़िए</b>
      <p class="help" style="margin-bottom:10px">जो पूरी सूची में भी नहीं है — जैसे अपनी बनाई चीज़।</p>
      <div class="field"><label>सामान का नाम</label>
        <input type="text" name="name" required placeholder="जैसे: देसी घी, लोकल मिठाई"></div>
      <div class="grid g2">
        <div class="field"><label>नाप</label>
          <input type="text" name="unit" placeholder="जैसे: 1 किलो / 250 ग्राम"></div>
        <div class="field"><label>दाम ₹</label>
          <input type="number" name="price" min="1" max="200000" inputmode="numeric" required></div>
      </div>
      <div class="field"><label>फ़ोटो (फ़ोन से खींच लीजिए)</label>
        <input type="file" name="photo" accept="image/*" capture="environment"></div>
      <button class="btn btn-brand">जोड़िए</button>
    </form>

    <form method="post" style="margin-top:16px;border-top:1px solid var(--line);padding-top:14px;display:flex;gap:8px;align-items:flex-end">
      <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
      <input type="hidden" name="do" value="kism">
      <div style="flex:1"><label>दुकान की किस्म बदलिए</label>
        <select name="shop_type">
          <?php foreach ($kisme as $k): ?>
            <option value="<?= h($k['slug']) ?>" <?= $k['slug']===$kism?'selected':'' ?>><?= h(catalog_label($k['slug'], $k['name_hi'])) ?> (<?= (int)$k['ginti'] ?>)</option>
          <?php endforeach; ?>
        </select>
        <p class="help">नई किस्म का सामान भी आ जाएगा। पुराना हटेगा नहीं।</p></div>
      <button class="btn btn-sm" style="background:#EFEAE0">सेव</button>
    </form>
  </details>

  <!-- ---------- 3. जो ग्राहक को दिख रहा है ---------- -->
  <div style="margin-top:22px">
    <h3>ग्राहक को दिख रहा है (<?= count($chalu) ?>)</h3>
    <?php if (!$chalu): ?>
      <p class="help">अभी कुछ नहीं। ऊपर दाम भरते ही यहाँ आने लगेगा।</p>
    <?php endif; ?>

    <?php foreach ($chalu as $it): ?>
      <div class="box" style="margin-top:10px">
        <div style="display:flex;gap:10px;align-items:center">
          <?php if ($it['photo']): ?>
            <img src="/uploads/<?= h($it['photo']) ?>" alt="" width="48" height="48"
                 style="border-radius:9px;object-fit:cover;flex:none">
          <?php endif; ?>
          <div style="flex:1;min-width:0">
            <b><?= h($it['name']) ?></b>
            <div class="meta"><?= h($it['unit']) ?><?= (int)$it['sold'] ? ' · ' . (int)$it['sold'] . ' बार बिका' : '' ?></div>
          </div>
          <div style="font-weight:800;font-size:18px">₹<?= (int)$it['price'] ?></div>
        </div>

        <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-top:10px">
          <form method="post" style="display:flex;gap:6px;align-items:center">
            <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
            <input type="hidden" name="do" value="item_daam"><input type="hidden" name="id" value="<?= (int)$it['id'] ?>">
            <span style="font-weight:700">₹</span>
            <input type="number" name="price" value="<?= (int)$it['price'] ?>" min="1" max="200000"
                   inputmode="numeric" style="width:82px">
            <button class="btn btn-sm btn-gold">दाम बदलिए</button>
          </form>
          <form method="post">
            <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
            <input type="hidden" name="do" value="item_stock"><input type="hidden" name="id" value="<?= (int)$it['id'] ?>">
            <button class="btn btn-sm" style="background:#EFEAE0">ख़त्म हो गया</button>
          </form>
          <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
            <input type="hidden" name="do" value="item_photo"><input type="hidden" name="id" value="<?= (int)$it['id'] ?>">
            <label class="btn btn-sm" style="background:#EFEAE0;cursor:pointer;margin:0">
              <?= $it['photo'] ? 'फ़ोटो बदलिए' : 'फ़ोटो लगाइए' ?>
              <input type="file" name="photo" accept="image/*" capture="environment" hidden onchange="this.form.submit()">
            </label>
          </form>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <!-- ---------- 4. ख़त्म / नहीं रखता ---------- -->
  <?php if ($khatam): ?>
    <details class="box" style="margin-top:16px">
      <summary style="font-weight:700;cursor:pointer">अभी ख़त्म है (<?= count($khatam) ?>)</summary>
      <?php foreach ($khatam as $it): ?>
        <div style="border-top:1px solid var(--line);padding:9px 0;display:flex;gap:10px;align-items:center">
          <div style="flex:1"><?= h($it['name']) ?> <span class="meta">· <?= h($it['unit']) ?> · ₹<?= (int)$it['price'] ?></span></div>
          <form method="post"><input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
            <input type="hidden" name="do" value="item_stock"><input type="hidden" name="id" value="<?= (int)$it['id'] ?>">
            <button class="btn btn-sm btn-green">अब है</button></form>
        </div>
      <?php endforeach; ?>
    </details>
  <?php endif; ?>

  <?php if ($hataye): ?>
    <details class="box" style="margin-top:12px">
      <summary style="font-weight:700;cursor:pointer">जो आप नहीं रखते (<?= count($hataye) ?>)</summary>
      <p class="help" style="margin-top:8px">ग़लती से हट गया हो तो वापस ला लीजिए।</p>
      <?php foreach (array_slice($hataye, 0, 100) as $it): ?>
        <div style="border-top:1px solid var(--line);padding:8px 0;display:flex;gap:10px;align-items:center">
          <div style="flex:1;color:var(--muted)"><?= h($it['name']) ?></div>
          <form method="post"><input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
            <input type="hidden" name="do" value="wapas"><input type="hidden" name="id" value="<?= (int)$it['id'] ?>">
            <button class="btn btn-sm" style="background:#EFEAE0">वापस लाइए</button></form>
        </div>
      <?php endforeach; ?>
    </details>
  <?php endif; ?>

<?php endif; ?>

</div></section>
