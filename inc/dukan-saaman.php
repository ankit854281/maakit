<?php
// ============================================================
//  tab: मेरा सामान
//
//  Sabse badi dikkat ye hai ki dukandar naam type nahi karna
//  chahta. Isliye do raste rakhe hain:
//
//   1. Maakit ki soochi se — naam pehle se likha hai, dukandar
//      sirf daam bharta hai. Ek baar me kai saaman chadh jate hain.
//   2. Apne haath se — jo cheez Maakit ki soochi me nahi hai.
//
//  Sabse upar wahi rehta hai jo abhi karna hai.
// ============================================================
$items  = dukan_items($pdo, $bid);
$khoj   = get('q');
$sab    = get('sab') === '1';
$kism   = (string)($b['shop_type'] ?? '');
$sujhav = dukan_suggest($pdo, $bid, $khoj, $khoj !== '' ? 60 : 30, $sab);
$kisme  = dukan_types($pdo);
$khul   = get('add') === '1' || !$items;

// kism ka Hindi naam
$kismName = '';
foreach ($kisme as $k) { if ($k['slug'] === $kism) { $kismName = $k['name_hi']; break; } }
?>
<section><div class="wrap" style="max-width:820px">

  <h2>मेरा सामान</h2>
  <p class="lead">जो सामान यहाँ है और “उपलब्ध” लगा है, वही ग्राहक को आपके दाम के साथ दिखता है।</p>

  <!-- ---------- 0. दुकान किस किस्म की है ---------- -->
  <?php if ($kism === ''): ?>
    <form method="post" class="box" style="margin-top:14px;border-color:var(--gold)">
      <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
      <input type="hidden" name="do" value="kism">
      <b style="font-size:17px">पहले बताइए — आपकी दुकान किस किस्म की है?</b>
      <p class="help" style="margin-top:6px">एक बार बता दीजिए, फिर आपके काम का सामान ही
        सबसे पहले दिखेगा — पूरी सूची में ढूँढना नहीं पड़ेगा।</p>
      <select name="shop_type" style="margin-top:10px" required>
        <option value="">— चुनिए —</option>
        <?php foreach ($kisme as $k): ?>
          <option value="<?= h($k['slug']) ?>"><?= h($k['name_hi']) ?> (<?= (int)$k['ginti'] ?>)</option>
        <?php endforeach; ?>
      </select>
      <button class="btn btn-brand" style="margin-top:10px">सेव कीजिए</button>
    </form>
  <?php endif; ?>

  <!-- ---------- 1. सूची से — सिर्फ़ दाम भरिए ---------- -->
  <details class="box" style="margin-top:14px" <?= $khul ? 'open' : '' ?>>
    <summary style="font-weight:700;cursor:pointer;font-size:17px">
      सूची से जोड़िए <span class="meta" style="font-weight:400">— नाम लिखना नहीं पड़ेगा</span>
    </summary>

    <form method="get" style="display:flex;gap:8px;margin-top:12px">
      <input type="hidden" name="tab" value="saaman"><input type="hidden" name="add" value="1">
      <?php if ($sab): ?><input type="hidden" name="sab" value="1"><?php endif; ?>
      <input type="search" name="q" value="<?= h($khoj) ?>" placeholder="ढूँढिए — जैसे आटा, तेल, समोसा" style="flex:1">
      <button class="btn btn-sm" style="background:#EFEAE0">ढूँढिए</button>
    </form>

    <?php if ($kism !== ''): ?>
      <p class="meta" style="margin-top:10px">
        <?php if ($khoj !== ''): ?>
          “<?= h($khoj) ?>” — पूरी सूची में से
        <?php elseif ($sab): ?>
          पूरी सूची · <a href="/shop.php?tab=saaman&amp;add=1">सिर्फ़ <?= h($kismName) ?> दिखाइए</a>
        <?php else: ?>
          <?= h($kismName) ?> का सामान · <a href="/shop.php?tab=saaman&amp;add=1&amp;sab=1">पूरी सूची देखिए</a>
        <?php endif; ?>
        · <a href="/shop.php?tab=saaman&amp;kismbadlo=1">किस्म बदलिए</a>
      </p>
    <?php endif; ?>

    <?php if (get('kismbadlo') === '1'): ?>
      <form method="post" style="display:flex;gap:8px;align-items:flex-end;margin-top:10px">
        <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
        <input type="hidden" name="do" value="kism">
        <div style="flex:1"><label>दुकान की किस्म</label>
          <select name="shop_type">
            <option value="">— कोई नहीं —</option>
            <?php foreach ($kisme as $k): ?>
              <option value="<?= h($k['slug']) ?>" <?= $k['slug']===$kism?'selected':'' ?>><?= h($k['name_hi']) ?> (<?= (int)$k['ginti'] ?>)</option>
            <?php endforeach; ?>
          </select></div>
        <button class="btn btn-sm btn-brand">सेव</button>
      </form>
    <?php endif; ?>

    <?php if (!$sujhav): ?>
      <p class="help" style="margin-top:12px">
        <?= $khoj !== '' ? 'इस नाम का कुछ नहीं मिला।' : 'इस किस्म का सारा सामान आपने चढ़ा लिया है।' ?>
        ऊपर ढूँढिए, या नीचे अपने हाथ से जोड़िए।</p>
    <?php else: ?>
      <form method="post" style="margin-top:12px">
        <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
        <input type="hidden" name="do" value="item_bulk">
        <input type="hidden" name="q" value="<?= h($khoj) ?>">
        <p class="help" style="margin-bottom:8px">जो आपके पास है, बस उसका <b>दाम</b> भर दीजिए।
          बाकी खाली छोड़ दीजिए — वे नहीं जुड़ेंगे।<br>
          नाप अपनी दुकान के हिसाब से बदल लीजिए (जैसे “1 किलो”, “500 ग्राम”)।</p>

        <?php foreach ($sujhav as $s): ?>
          <div style="border-top:1px solid var(--line);padding:9px 0;display:flex;gap:8px;align-items:center;flex-wrap:wrap">
            <div style="flex:1;min-width:130px">
              <b><?= h($s['name']) ?></b>
              <?php if ($s['is_sewa']): ?><span class="tag tag-off">काम</span><?php endif; ?>
              <div class="meta"><?= h($s['sub_cat']) ?><?= $s['name'] !== $s['name_en'] ? ' · ' . h($s['name_en']) : '' ?></div>
            </div>
            <input type="text" name="naap[<?= (int)$s['id'] ?>]" value="<?= h($s['unit']) ?>"
                   style="width:92px;flex:none" aria-label="नाप">
            <div style="display:flex;align-items:center;gap:4px;flex:none">
              <span style="font-weight:700">₹</span>
              <input type="number" name="daam[<?= (int)$s['id'] ?>]" min="1" max="200000"
                     inputmode="numeric" style="width:82px" placeholder="दाम"
                     aria-label="<?= h($s['name']) ?> का दाम">
            </div>
          </div>
        <?php endforeach; ?>

        <button class="btn btn-brand" style="margin-top:14px;width:100%">जिनका दाम भरा है, वे जोड़ दीजिए</button>
      </form>
    <?php endif; ?>
  </details>

  <!-- ---------- 2. अपने हाथ से ---------- -->
  <details class="box" style="margin-top:12px">
    <summary style="font-weight:700;cursor:pointer;font-size:17px">
      अपने हाथ से जोड़िए <span class="meta" style="font-weight:400">— जो Maakit की सूची में नहीं है</span>
    </summary>
    <form method="post" enctype="multipart/form-data" style="margin-top:12px">
      <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
      <input type="hidden" name="do" value="item_add">
      <div class="field"><label>सामान का नाम</label>
        <input type="text" name="name" required placeholder="जैसे: देसी घी, लोकल मिठाई"></div>
      <div class="grid g2">
        <div class="field"><label>कितने का (नाप)</label>
          <input type="text" name="unit" placeholder="जैसे: 1 किलो / 250 ग्राम / 1 पीस"></div>
        <div class="field"><label>दाम ₹</label>
          <input type="number" name="price" min="1" max="200000" inputmode="numeric" required></div>
      </div>
      <div class="field"><label>फ़ोटो (फ़ोन से खींच लीजिए)</label>
        <input type="file" name="photo" accept="image/*" capture="environment">
        <p class="help">फ़ोटो वाला सामान तीन गुना ज़्यादा बिकता है। अपनी ही फ़ोटो लगाइए।</p></div>
      <button class="btn btn-brand">जोड़िए</button>
    </form>
  </details>

  <!-- ---------- 3. जो चढ़ा हुआ है ---------- -->
  <div style="margin-top:22px">
    <h3>चढ़ा हुआ सामान (<?= count($items) ?>)</h3>
    <?php if (!$items): ?>
      <p class="help">अभी कुछ नहीं। ऊपर से जोड़ लीजिए।</p>
    <?php endif; ?>

    <?php foreach ($items as $it): $hai = $it['stock'] === 'hai'; ?>
      <div class="box" style="margin-top:10px;<?= $hai ? '' : 'opacity:.6' ?>">
        <div style="display:flex;gap:10px;align-items:flex-start">
          <?php if ($it['photo']): ?>
            <img src="/uploads/<?= h($it['photo']) ?>" alt="" width="52" height="52"
                 style="border-radius:9px;object-fit:cover;flex:none">
          <?php endif; ?>
          <div style="flex:1;min-width:0">
            <b><?= h($it['name']) ?></b>
            <?php if (!$hai): ?><span class="tag tag-off">ख़त्म</span><?php endif; ?>
            <div class="meta"><?= h($it['unit']) ?><?= (int)$it['sold'] ? ' · ' . (int)$it['sold'] . ' बार बिका' : '' ?></div>
          </div>
        </div>

        <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-top:10px">
          <form method="post" style="display:flex;gap:6px;align-items:center">
            <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
            <input type="hidden" name="do" value="item_daam"><input type="hidden" name="id" value="<?= (int)$it['id'] ?>">
            <span style="font-weight:700">₹</span>
            <input type="number" name="price" value="<?= (int)$it['price'] ?>" min="1" max="200000"
                   inputmode="numeric" style="width:84px">
            <button class="btn btn-sm btn-gold">दाम बदलिए</button>
          </form>

          <form method="post">
            <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
            <input type="hidden" name="do" value="item_stock"><input type="hidden" name="id" value="<?= (int)$it['id'] ?>">
            <button class="btn btn-sm" style="background:#EFEAE0"><?= $hai ? 'ख़त्म हो गया' : 'अब है' ?></button>
          </form>

          <form method="post" enctype="multipart/form-data" style="display:flex;gap:6px;align-items:center">
            <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
            <input type="hidden" name="do" value="item_photo"><input type="hidden" name="id" value="<?= (int)$it['id'] ?>">
            <label class="btn btn-sm" style="background:#EFEAE0;cursor:pointer;margin:0">
              <?= $it['photo'] ? 'फ़ोटो बदलिए' : 'फ़ोटो लगाइए' ?>
              <input type="file" name="photo" accept="image/*" capture="environment" hidden
                     onchange="this.form.submit()">
            </label>
          </form>

          <form method="post" style="margin-left:auto"
                onsubmit="return confirm('<?= h($it['name']) ?> हटा दें?')">
            <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
            <input type="hidden" name="do" value="item_del"><input type="hidden" name="id" value="<?= (int)$it['id'] ?>">
            <button class="btn btn-sm" style="background:#EFEAE0;color:var(--brand)">हटाइए</button>
          </form>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

</div></section>
