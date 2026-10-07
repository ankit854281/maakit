<?php
require_once __DIR__ . '/inc/fn.php';
require_once __DIR__ . '/inc/items.php';
require_once __DIR__ . '/inc/dakiya.php';
require_once __DIR__ . '/inc/daam.php';
require_once __DIR__ . '/inc/icons.php';
require_once __DIR__ . '/inc/submit-once.php';

$page_title = t('Order — Maakit', 'ऑर्डर कीजिए — Maakit');
$tab = 'order';
$villages = village_list($pdo);
$items = items_all($pdo);
$groups = item_groups();
$err = '';
$done = null;
[$submit_key,$done,$err] = submit_once_form('goods');

// ---------- ऑर्डर सेव ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_ok() && !$done && !$err) {
    $name    = post('name');
    $mobile  = preg_replace('/\D/', '', post('mobile'));
    $village = post('village');
    $landmark= post('landmark');
    $note    = post('note');                 // "aur kuchh" — khula likha hua
    $shop    = post('shop');
    $market  = post('market', 'kapsethi');
    $pay     = post('payment');
    $cart    = cart_parse($pdo, post('cart_json'));
    $photo   = save_photo('photo', 'list');
    $lat = post('lat'); $lng = post('lng');
    $lat = ($lat !== '' && is_numeric($lat) && abs((float)$lat) <= 90)  ? round((float)$lat, 7) : null;
    $lng = ($lng !== '' && is_numeric($lng) && abs((float)$lng) <= 180) ? round((float)$lng, 7) : null;
    if ($lat === null || $lng === null) { $lat = $lng = null; }

    $items_text = trim($cart['text'] . ($note ? ($cart['text'] ? "\n" : '') . $note : ''));
    if ($items_text === '' && $photo) { $items_text = '[फ़ोटो भेजी है — पैनल में देखिए]'; }

    // wazan: user ne chuna to wahi, warna cart se andaaza
    $w  = post('weight', '');
    if ($w === '' || !array_key_exists($w, weight_extras())) { $w = kg_band($cart['kg']); }
    $sz = post('size', '0');
    if (!array_key_exists($sz, size_extras())) { $sz = '0'; }

    if (mb_strlen($name) < 2)          { $err = t('Please write your name.', 'कृपया अपना नाम लिखिए।'); }
    elseif (strlen($mobile) !== 10)    { $err = t('Mobile number must be 10 digits.', 'मोबाइल नंबर 10 अंकों का लिखिए।'); }
    elseif (!coverage_enabled(coverage_area($pdo,$village))) { $err=coverage_error(); }
    elseif (!array_key_exists($market,markets())) { $err=t('Choose a valid pickup area.', 'सही pickup क्षेत्र चुनिए।'); }
    elseif (mb_strlen($items_text) < 3 && !$photo){ $err = t('Pick at least one item, write it, or send a photo.', 'कम से कम एक सामान चुनिए, लिख दीजिए या फ़ोटो भेजिए।'); }
    else {
        // ---- ek hi number se bahut saare order na aayein ----
        // Launch ke din koi mazaak me 50 order daal de to BPO ka poora
        // din kharab ho jata hai. Asli grahak ek ghante me 5 se zyada
        // kabhi nahi karta.
        $tz = $pdo->prepare("SELECT COUNT(*) c FROM orders
                              WHERE mobile=? AND created_at > NOW() - INTERVAL 1 HOUR");
        $tz->execute([$mobile]);
        $tez = (int)($tz->fetch()['c'] ?? 0);
    }

    if (!$err && isset($tez) && $tez >= 5) {
        $err = t('Too many orders from this number just now. Please call us instead.',
                 'इस नंबर से अभी बहुत ऑर्डर आ चुके हैं। एक घंटे बाद कोशिश कीजिए, या हमें कॉल कर लीजिए — ' . MAAKIT_NUMBER_SHOW);
    }

    if (!$err) {
        $st = $pdo->prepare("SELECT COUNT(*) c FROM orders WHERE mobile=?");
        $st->execute([$mobile]);
        $first = ((int)$st->fetch()['c'] === 0 && (coverage_area($pdo,$village)['first_free'] ?? 1)) ? 1 : 0;
        $calc  = calc_charge($village, $market, $w, $sz, $pdo, (bool)$first);
        $order_no = new_order_no($pdo);
        $code = new_code();

        $ins = $pdo->prepare("INSERT INTO orders
            (order_no, code, source, customer_id, customer_name, mobile, village, landmark, items, items_json, goods_note, photo,
             shop, market, weight_extra, size_extra, first_order, delivery_charge, payment, lat, lng, status)
            VALUES (?,?,'website',?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?, 'Naya')");
        $ins->execute([$order_no, $code, cust()['id'] ?? null, $name, $mobile, $village, $landmark, $items_text,
                       json_encode($cart['lines'], JSON_UNESCAPED_UNICODE), $note, $photo,
                       $shop, $market, $w, $sz, $first, $calc['total'], $pay, $lat, $lng]);

        $charge_line = ($calc['van'] || $calc['total'] === null)
            ? "डिलीवरी चार्ज: कॉल पर बताया जाएगा"
            : "डिलीवरी चार्ज: ₹" . (int)$calc['total'] . ($first ? " (पहली डिलीवरी फ़्री)" : "");

        $lines = t("Hello Maakit, I placed an order on the website.", "नमस्ते Maakit, मैंने वेबसाइट से ऑर्डर किया है।") . "\n"
            . "ऑर्डर नंबर: $order_no\n"
            . "नाम: $name\n"
            . "गाँव: $village" . ($landmark ? " ($landmark)" : "") . "\n"
            . "--- सामान ---\n" . $items_text . "\n"
            . ($shop ? "दुकान: $shop\n" : "")
            . "पेमेंट: $pay\n" . $charge_line;

        $done = ['no'=>$order_no, 'code'=>$code, 'calc'=>$calc, 'first'=>$first,
                 'wa'=>wa_link(MAAKIT_WA, $lines), 'mobile'=>$mobile, 'cart'=>$cart, 'note'=>$note];
        submit_once_complete($submit_key,$done);
    }
}

// JS ke liye gaanv ke rate
$vjs = [];
foreach ($villages as $v) {
    $vjs[$v['name']] = ['kapsethi'=>(int)($v['base_fee'] ?? $v['rate_kapsethi']), 'chauri'=>(int)($v['base_fee'] ?? $v['rate_chauri']), 'kachhawa'=>(int)($v['base_fee'] ?? $v['rate_kachhawa']), 'local'=>(int)($v['base_fee'] ?? 0),'known'=>$v['base_fee'] !== null];
}
// JS ke liye saaman
$DAAM = daam_sab($pdo);          // seekha hua daam — ek hi query
$IBR  = item_brands_all($pdo);   // kis saaman me kaun se brand

$ijs = []; $ikeys = [];
foreach ($items as $i) {
    $ic  = prod_icon_key($i['name'], $i['grp']);
    $ikeys[] = $ic;
    $id  = (int)$i['id'];
    $d   = $DAAM[$id] ?? null;
    $row = ['i'=>$id, 'n'=>$i['name'], 'u'=>$i['unit'], 'g'=>$i['grp'],
            's'=>($i['section'] ?? 'saaman'),
            'c'=>$ic, 'k'=>round(unit_kg($i['unit']), 3),
            'p'=>$i['photo'] ? '/uploads/' . $i['photo'] : ''];
    if ($d) { $row['d'] = daam_likhawat($d); $row['dw'] = daam_kab($d); }
    if (!empty($IBR[$id])) {
        $row['b'] = [];
        foreach ($IBR[$id] as $b) {
            $bd = daam_ek($pdo, $id, (int)$b['id']);     // us brand ka apna daam
            $row['b'][] = ['i'=>(int)$b['id'], 'n'=>brand_naam($b)]
                        + ($bd ? ['d'=>daam_likhawat($bd), 'dw'=>daam_kab($bd)] : []);
        }
    }
    $ijs[] = $row;
}
$ipaths = prod_icon_paths($ikeys);
include __DIR__ . '/inc/head.php';
?>

<?php if ($done): ?>
<!-- ================= ऑर्डर हो गया ================= -->
<section>
<div class="wrap" style="max-width:640px">
  <div class="box" style="text-align:center">
    <div style="font-size:52px;line-height:1">🙏</div>
    <h2 style="margin:6px 0 4px"><?= t('Order received', 'ऑर्डर मिल गया') ?></h2>
    <p class="lead" style="margin-bottom:0"><?= t('Our team will call or WhatsApp you shortly to confirm.', 'हमारी टीम कुछ ही देर में फ़ोन या WhatsApp पर कन्फ़र्म करेगी।') ?></p>

    <div class="codebox">
      <div class="l"><?= t('DELIVERY CODE', 'डिलीवरी कोड') ?></div>
      <div class="c"><?php foreach (str_split($done['code']) as $d): ?><span><?= h($d) ?></span><?php endforeach; ?></div>
      <div class="h"><?= t('Say this code to the delivery partner when you take the goods.<br>Do not share it with anyone else.', 'सामान लेते समय यही कोड डिलीवरी पार्टनर को बताइए।<br>किसी और को मत बताइए।') ?></div>
    </div>

    <div class="note" style="text-align:left">
      <div style="display:flex;justify-content:space-between;gap:10px"><span><?= t('Order number', 'ऑर्डर नंबर') ?></span><b><?= h($done['no']) ?></b></div>
      <div style="display:flex;justify-content:space-between;gap:10px;margin-top:5px"><span><?= t('Delivery charge', 'डिलीवरी चार्ज') ?></span><b>
        <?php if ($done['calc']['van'] || $done['calc']['total'] === null): ?><?= t('On call', 'कॉल पर') ?>
        <?php elseif ($done['first']): ?><span class="freebadge"><?= t('First delivery FREE', 'पहली डिलीवरी फ़्री') ?></span><?= $done['calc']['extra'] ? ' + ₹'.$done['calc']['extra'] : '' ?>
        <?php else: ?>₹<?= (int)$done['calc']['total'] ?><?php endif; ?>
      </b></div>
      <div style="display:flex;justify-content:space-between;gap:10px;margin-top:5px"><span><?= t('Items', 'सामान') ?></span><b><?= (int)$done['cart']['count'] ?: '—' ?></b></div>
    </div>

    <div style="display:grid;gap:9px;margin-top:14px">
      <a class="btn btn-green" href="<?= h($done['wa']) ?>" target="_blank" rel="noopener"><?= t('Send a copy on WhatsApp', 'WhatsApp पर कॉपी भेजिए') ?></a>
      <a class="btn btn-brand" href="/track.php?no=<?= h($done['no']) ?>&amp;m=<?= h($done['mobile']) ?>"><?= t('Track this order', 'ऑर्डर का सफ़र देखिए') ?></a>
      <!-- Gaon me khabar WhatsApp group se phailti hai, vigyapan se nahi -->
      <button type="button" class="btn btn-ghost" id="dostBtn"><?= t('Tell a friend', 'दोस्त को भेजिए') ?></button>
    </div>
    <p class="help" style="margin-top:10px"><?= t('First you get a call with the price, then the goods arrive.', 'पहले आपको कॉल आएगा, दाम बताए जाएँगे, फिर सामान आएगा।') ?></p>
  </div>
</div>
</section>
<script>
/* ---------- "दोस्त को भेजिए" ----------
   Phone ka apna share sheet khulta hai, taaki grahak seedhe apne
   gaon ke WhatsApp group me daal sake. Jahan wo na ho (computer),
   wahan WhatsApp khul jata hai. */
(function(){
  var b = document.getElementById('dostBtn');
  if (!b) return;
  var msg = <?= json_encode(dak_dost(), JSON_UNESCAPED_UNICODE) ?>;
  b.addEventListener('click', function(){
    if (navigator.share) {
      navigator.share({ text: msg }).catch(function(){});
      return;
    }
    window.open('https://wa.me/?text=' + encodeURIComponent(msg), '_blank', 'noopener');
  });
})();
try{
  localStorage.removeItem('mk_cart');
  localStorage.setItem('mk_me', JSON.stringify({n:<?= json_encode($_POST['name'] ?? '') ?>,m:<?= json_encode($done['mobile']) ?>,v:<?= json_encode($_POST['village'] ?? '') ?>,l:<?= json_encode($_POST['landmark'] ?? '') ?>}));
  var o=JSON.parse(localStorage.getItem('mk_orders')||'[]');
  o.unshift({no:<?= json_encode($done['no']) ?>,m:<?= json_encode($done['mobile']) ?>,t:Date.now(),
             c:<?= json_encode(array_map(fn($l)=>['id'=>$l['id'],'q'=>$l['q']], $done['cart']['lines'])) ?>});
  localStorage.setItem('mk_orders', JSON.stringify(o.slice(0,20)));
}catch(e){}
</script>

<?php else: ?>
<!-- ================= कैटलॉग + कार्ट ================= -->
<div class="wrap" style="max-width:1080px">
  <?php if ($err): ?><div class="err" style="margin-top:14px"><?= h($err) ?></div><?php endif; ?>
</div>

<!-- ---------- पर्दा 1: सामान चुनिए ---------- -->
<div id="paneCat">
  <div class="wrap">
    <div class="appsearch">
      <div class="in">
        <span class="ic"><?= svc_icon('search', 20) ?></span>
        <input type="search" id="q" placeholder="<?= h(t('What do you need? Atta, oil, medicine…', 'क्या चाहिए? आटा, तेल, दवा…')) ?>" autocomplete="off" aria-label="<?= h(t('Search items', 'सामान खोजिए')) ?>">
        <button type="button" class="clr" id="qc" style="display:none" aria-label="<?= h(t('Clear', 'मिटाइए')) ?>">×</button>
        <button type="button" class="mic" id="mic" title="<?= h(t('Speak it', 'बोलकर बताइए')) ?>" aria-label="<?= h(t('Speak it', 'बोलकर बताइए')) ?>" style="display:none"><?= svc_icon('mic', 19) ?></button>
      </div>
    </div>
    <!-- do bade hisse — raashan aur bana khana alag -->
    <div class="secs" id="secs" role="tablist">
      <button type="button" data-s="saaman" class="on">
        <?= svc_icon('grocery', 20) ?> <span><?= t('Groceries & things', 'राशन और सामान') ?></span></button>
      <button type="button" data-s="khana">
        <?= svc_icon('food', 20) ?> <span><?= t('Cooked food & sweets', 'बना खाना, मिठाई') ?></span></button>
    </div>

    <div class="rail" id="rail" role="tablist"></div>

    <div id="reorder"></div>
    <h3 class="ghead" id="ghead"><?= t('Everyday essentials', 'रोज़ का सामान') ?></h3>
    <div class="items" id="grid"></div>
    <div class="empty" id="nores" style="display:none">
      <div class="e"><?= svc_icon('search', 40) ?></div>
      <b><?= t('Not in our list', 'यह सामान लिस्ट में नहीं है') ?></b>
      <p style="margin:6px 0 0"><?= t('No problem — write it below, speak it, or send a photo. We will fetch it.', 'कोई बात नहीं — नीचे लिख दीजिए, बोल दीजिए या फ़ोटो भेज दीजिए। हम ले आएँगे।') ?></p>
    </div>

    <div class="box" style="margin:22px 0 10px">
      <h3 style="margin:0 0 4px;font-size:18px"><?= t('Not on the list? No problem', 'लिस्ट में नहीं है? कोई बात नहीं') ?></h3>
      <p class="help" style="margin:0 0 12px"><?= t('Medicine, pipe, cloth — from any shop. Pick whichever way is easiest.', 'दवाई, पाइप, कपड़ा — किसी भी दुकान से ला सकते हैं। तीन में से कोई भी तरीक़ा चुनिए।') ?></p>

      <div class="ways">
        <button type="button" class="way" id="wayVoice"><?= svc_icon('mic', 22) ?><b><?= t('Speak it', 'बोलकर बताइए') ?></b><i><?= t('Tap the mic and talk', 'माइक दबाकर बोलिए') ?></i></button>
        <label class="way" for="photo"><?= svc_icon('camera', 22) ?><b><?= t('Send a photo', 'फ़ोटो भेजिए') ?></b><i><?= t('Doctor’s slip or medicine strip', 'पर्ची या दवा की पत्ती') ?></i></label>
      </div>
      <input type="file" id="photo" name="photo" accept="image/*" style="display:none">
      <div id="photoShow" style="display:none;margin-top:10px"></div>

      <label for="note" style="margin-top:14px"><?= t('Or write it in your own words', 'या अपने शब्दों में लिख दीजिए') ?></label>
      <textarea id="note" name="note" placeholder="<?= h(t('e.g. fever medicine 1 strip, 2-inch pipe, a notebook…', 'जैसे: बुख़ार की दवा 1 पत्ता, 2 इंच का पाइप, बच्चे की कॉपी…')) ?>" style="min-height:82px"></textarea>
      <div id="voiceHint" class="help" style="display:none"></div>
    </div>

    <p class="help" style="margin:0 0 26px"><?= t('Form feels hard? Just', 'फ़ॉर्म भरना मुश्किल लगे तो सीधे') ?>
      <a href="tel:<?= MAAKIT_PHONE ?>"><?= t('call us', 'कॉल कीजिए') ?></a> <?= t('or', 'या') ?>
      <a href="<?= h(wa_link(MAAKIT_WA, t('Hello Maakit, I want to order some things.', 'नमस्ते Maakit, मुझे सामान मँगाना है।'))) ?>" target="_blank" rel="noopener">WhatsApp</a>.</p>
  </div>
</div>

<!-- ---------- पर्दा 2: पता और पेमेंट ---------- -->
<div id="paneOut" style="display:none">
  <div class="wrap" style="max-width:680px">
    <div class="stepsdot" style="margin-top:16px"><i></i><i class="on"></i><em><?= t('2 / 2 — Address & payment', '2 / 2 — पता और पेमेंट') ?></em></div>

    <form method="post" id="ofrm" enctype="multipart/form-data">
      <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
      <input type="hidden" name="submit_key" value="<?= h($submit_key) ?>">
      <input type="hidden" name="cart_json" id="cartJson">
      <input type="hidden" name="note" id="noteHid">

      <div class="box" style="margin-bottom:14px">
        <div style="display:flex;align-items:center;gap:10px;margin-bottom:4px">
          <h3 style="margin:0;font-size:18px"><?= t('Your items', 'आपका सामान') ?></h3>
          <button type="button" class="btn btn-sm" id="backBtn" style="margin-left:auto;background:var(--soft);color:var(--brand)">+ <?= t('Add more', 'और जोड़िए') ?></button>
        </div>
        <div id="cartList"></div>
        <div id="noteShow" style="display:none;margin-top:10px" class="note"></div>
      </div>

      <div class="box" style="margin-bottom:14px">
        <h3 style="margin:0 0 12px;font-size:18px"><?= t('Where to deliver', 'कहाँ पहुँचाना है') ?></h3>
        <div class="field"><label for="name"><?= t('Your name', 'आपका नाम') ?></label>
          <input type="text" id="name" name="name" autocomplete="name" required></div>
        <div class="field"><label for="mobile"><?= t('Mobile number (10 digits)', 'मोबाइल नंबर (10 अंक)') ?></label>
          <input type="tel" id="mobile" name="mobile" inputmode="numeric" maxlength="10" autocomplete="tel-national" required></div>
        <div class="field"><label for="village"><?= t('Delivery area', 'डिलीवरी का इलाका') ?></label>
          <select id="village" name="village" required>
            <option value="">— <?= t('Choose', 'चुनिए') ?> —</option>
            <?php foreach ($villages as $v): ?><option value="<?= h($v['name']) ?>" <?= (coverage_selected($pdo)['name'] ?? '') === $v['name'] ? 'selected' : '' ?>><?= h(coverage_label($v)) ?></option><?php endforeach; ?>
          </select></div>
        <div class="field"><label for="landmark"><?= t('Landmark', 'घर की पहचान') ?></label>
          <input type="text" id="landmark" name="landmark" placeholder="<?= h(t('opposite the temple, near the school…', 'मंदिर के सामने, स्कूल के पास…')) ?>"></div>

        <input type="hidden" name="lat" id="lat"><input type="hidden" name="lng" id="lng">
        <button type="button" class="locbtn" id="locBtn">
          <?= svc_icon('box', 24) ?>
          <span><b id="locT"><?= t('Pin my location', 'अपनी जगह बता दीजिए') ?></b>
            <i id="locS"><?= t('One tap — the rider reaches you without asking around', 'एक बार दबाइए — डिलीवरी वाले को पूछना नहीं पड़ेगा') ?></i></span>
        </button>
        <p class="help"><?= t('Optional. We use it only to find your door.', 'ज़रूरी नहीं। हम इसे सिर्फ़ आपका घर ढूंढने के लिए रखते हैं।') ?></p>
      </div>

      <div class="box" style="margin-bottom:14px">
        <h3 style="margin:0 0 12px;font-size:18px"><?= t('Shop', 'दुकान') ?></h3>
        <div class="field"><label for="shop"><?= t('Shop name (if you know it)', 'दुकान का नाम (पता हो तो)') ?></label>
          <input type="text" id="shop" name="shop" placeholder="<?= h(t('Don’t know? Leave blank — we’ll pick a good shop', 'नहीं पता? खाली छोड़िए — हम अच्छी दुकान से लाएँगे')) ?>"></div>
        <div class="field" style="margin-bottom:0"><label for="market"><?= t('Which market', 'कौन सा बाज़ार') ?></label>
          <select id="market" name="market"><?php foreach (markets() as $k => $m): ?><option value="<?= h($k) ?>"><?= h($m) ?></option><?php endforeach; ?></select></div>
      </div>

      <div class="box" style="margin-bottom:14px">
        <h3 style="margin:0 0 12px;font-size:18px"><?= t('Weight & size', 'वज़न और आकार') ?></h3>
        <div class="field"><label for="weight"><?= t('Total weight', 'पूरे सामान का वज़न') ?> <span class="help" style="display:inline" id="wauto"></span></label>
          <select id="weight" name="weight"><?php foreach (weight_extras() as $k => $t): ?><option value="<?= h($k) ?>"><?= h($t) ?></option><?php endforeach; ?></select></div>
        <div class="field" style="margin-bottom:0"><label for="size"><?= t('Size of goods', 'सामान का आकार') ?></label>
          <select id="size" name="size"><?php foreach (size_extras() as $k => $t): ?><option value="<?= h($k) ?>"><?= h($t) ?></option><?php endforeach; ?></select></div>
      </div>

      <div class="box" style="margin-bottom:14px">
        <h3 style="margin:0 0 12px;font-size:18px"><?= t('Payment', 'पेमेंट') ?></h3>
        <div class="field" style="margin-bottom:12px">
          <select id="payment" name="payment">
            <option>डिलीवरी पर कैश</option>
            <option>डिलीवरी पर UPI</option>
            <option>मैं खुद दुकान को UPI करूँगा</option>
          </select>
        </div>
        <div class="sticky-tot" id="tot"></div>
        <p class="help"><?= t('Goods are charged exactly as on the shop’s bill — Maakit adds nothing.', 'सामान का दाम दुकान की पर्ची के हिसाब से — Maakit उसमें कुछ नहीं जोड़ता।') ?></p>
      </div>

      <button class="btn btn-brand" type="submit" style="width:100%;font-size:17px"><?= t('Place order', 'ऑर्डर पक्का कीजिए') ?></button>
      <p class="help" style="text-align:center;margin:10px 0 30px"><?= t('You’ll get an order number and delivery code right away.', 'भेजते ही ऑर्डर नंबर और डिलीवरी कोड मिलेगा।') ?></p>
    </form>
  </div>
</div>

<!-- सबसे नीचे चिपका हुआ कार्ट -->
<div class="cartbar" id="cartbar">
  <div class="inner">
    <div>
      <div class="n" id="cbN">0</div>
      <div class="s" id="cbS"><?= t('Shop price + delivery', 'दुकान का दाम + डिलीवरी') ?></div>
    </div>
    <button type="button" class="go" id="cbGo"><?= t('Continue', 'आगे बढ़िए') ?> →</button>
  </div>
</div>
<div class="toast" id="toast"></div>

<script>
(function(){
"use strict";
var L = <?= json_encode([
  'items'       => t('items', 'सामान'),
  'about'       => t('about', 'करीब'),
  'kg'          => t('kg', 'किलो'),
  'shopprice'   => t('Shop price + delivery', 'दुकान का दाम + डिलीवरी'),
  'add'         => t('ADD', 'जोड़िए'),
  'added'       => t('added', 'जुड़ गया'),
  'resultsfor'  => t('results for', 'सामान मिले —'),
  'everyday'    => t('Everyday essentials', 'रोज़ का सामान'),
  'andaza'      => t('rough idea', 'अंदाज़ा'),
  'daamcall'    => t('price on the bill', 'दाम बिल का'),
  'anybrand'    => t('Any brand', 'कोई भी ब्रांड'),
  'again'       => t('Order the same again', 'फिर से वही ऑर्डर'),
  'addall'      => t('Add all', 'सब जोड़ दीजिए'),
  'reorderdone' => t('Last order added', 'पिछला ऑर्डर जुड़ गया'),
  'pickfirst'   => t('Pick something first', 'पहले कुछ सामान चुनिए'),
  'nothingpicked'=> t('Nothing picked — we will go by what you wrote below.', 'कोई सामान नहीं चुना — नीचे लिखी बात के हिसाब से लाएँगे।'),
  'youwrote'    => t('You wrote:', 'आपने लिखा:'),
  'cartempty'   => t('Cart is empty', 'कार्ट खाली हो गया'),
  'delcharge'   => t('Delivery charge', 'डिलीवरी चार्ज'),
  'delivery'    => t('Delivery', 'डिलीवरी'),
  'choosevill'  => t('choose village', 'गाँव चुनिए'),
  'byvan'       => t('by van', 'वैन से'),
  'vannote'     => t('Starts at ₹150 — confirmed on call.', 'चार्ज ₹150 से शुरू — कॉल पर पक्का होगा।'),
  'oncall'      => t('on call', 'कॉल पर'),
  'oncallnote'  => t('We will tell you this village’s charge on the phone.', 'इस गाँव का चार्ज फ़ोन पर बताया जाएगा।'),
  'goodsprice'  => t('Goods', 'सामान का दाम'),
  'asperbill'   => t('as per shop bill', 'दुकान की पर्ची से'),
  'firstfree'   => t('first one FREE', 'पहली बार फ़्री'),
  'wsize'       => t('Weight / size', 'वज़न / आकार'),
  'youpay'      => t('You pay (delivery)', 'आप देंगे (डिलीवरी)'),
  'novoice'     => t('This phone does not support voice input', 'इस फ़ोन में बोलने वाली सुविधा नहीं है'),
  'listening'   => t('Listening… speak now', 'सुन रहे हैं… बोलिए'),
  'sr'          => t('en-IN', 'hi-IN'),
  'micperm'     => t('Please allow the microphone', 'माइक की इजाज़त दीजिए'),
  'nothear'     => t('Did not catch that, say it again', 'सुनाई नहीं दिया, फिर बोलिए'),
  'written'     => t('Written down', 'लिख लिया'),
  'tryagain'    => t('Try again', 'फिर कोशिश कीजिए'),
  'toobig'      => t('Photo is too big (4 MB max)', 'फ़ोटो बहुत बड़ी है (4 MB तक)'),
  'photoon'     => t('Photo attached', 'फ़ोटो लग गई'),
  'photosub'    => t('It will go with your order', 'ऑर्डर के साथ चली जाएगी'),
  'remove'      => t('Remove', 'हटाइए'),
  'nogps'       => t('This phone cannot share location', 'इस फ़ोन में जगह बताने की सुविधा नहीं है'),
  'locfinding'  => t('Finding you…', 'आपकी जगह ढूंढ रहे हैं…'),
  'locdone'     => t('Location added', 'जगह जुड़ गई'),
  'locacc'      => t('accurate to about', 'करीब'),
  'locdeny'     => t('You said no. Tap again to allow.', 'आपने मना कर दिया। दोबारा दबाकर हाँ कीजिए।'),
  'locfail'     => t('Could not find you. Try outdoors.', 'जगह नहीं मिली। खुली जगह में कोशिश कीजिए।'),
  'locsaved'    => t('Location saved', 'जगह पहले से है'),
  'loctap'      => t('Tap to update it', 'बदलने के लिए दबाइए'),
], JSON_UNESCAPED_UNICODE) ?>;
var ITEMS = <?= json_encode($ijs, JSON_UNESCAPED_UNICODE) ?>;
var IPATH = <?= json_encode($ipaths, JSON_UNESCAPED_UNICODE) ?>;
function ic(k, sz){
  return '<svg width="'+sz+'" height="'+sz+'" viewBox="0 0 24 24" fill="none" stroke="currentColor"'
    + ' stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
    + (IPATH[k] || IPATH.bag || '') + '</svg>';
}
/* photo lagi ho to photo, warna icon */
function thumb(x, sz){
  return x.p ? '<img src="'+x.p+'" alt="" loading="lazy" class="pimg">' : ic(x.c, sz);
}
var VILL  = <?= json_encode($vjs, JSON_UNESCAPED_UNICODE) ?>;
var GRPS  = <?= json_encode($groups, JSON_UNESCAPED_UNICODE) ?>;
var BY = {}; ITEMS.forEach(function(x){ BY[x.i] = x; });

var $ = function(id){ return document.getElementById(id); };
var cart = {};
try { cart = JSON.parse(localStorage.getItem('mk_cart')||'{}') || {}; } catch(e){ cart = {}; }
// purane/hataye gaye saaman saaf
for (var k in cart) { if (!BY[k]) delete cart[k]; }

var curS = 'saaman', curG = 'daily', curQ = '', lastCount = 0;

/* kis hisse me kaun se group hain — jo khaali ho wo chip dikhao hi mat */
function groupsOf(sec){
  var seen = {}, out = [];
  ITEMS.forEach(function(x){ if ((x.s||'saaman') === sec && !seen[x.g]) { seen[x.g]=1; out.push(x.g); } });
  return out;
}
function drawRail(){
  var h = '<button type="button" data-g="daily"' + (curG==='daily'?' class="on"':'') + '>'
        + L.everyday + '</button>';
  groupsOf(curS).forEach(function(g){
    h += '<button type="button" data-g="'+g+'"'+(curG===g?' class="on"':'')+'>'+esc(GRPS[g]||g)+'</button>';
  });
  $('rail').innerHTML = h;
}

function save(){ try{ localStorage.setItem('mk_cart', JSON.stringify(cart)); }catch(e){} }
function count(){ var n=0; for(var k in cart) n += cart[k].q; return n; }
function kgTotal(){ var s=0; for(var k in cart) s += (BY[k]?BY[k].k:0) * cart[k].q; return s; }

var tmr;
function toast(t){ var e=$('toast'); e.textContent=t; e.classList.add('show'); clearTimeout(tmr); tmr=setTimeout(function(){e.classList.remove('show');},1500); }

/* ---------- saaman ki list dikhana ---------- */
function esc(s){ return String(s).replace(/[&<>"]/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c];}); }

function cardHtml(x){
  var c = cart[x.i], q = c ? c.q : 0;
  var h = '<div class="it'+(q?' in':'')+'" data-i="'+x.i+'">'
    + '<div class="thumb">'+thumb(x,30)+'</div>'
    + '<div class="nm">'+esc(x.n)+'</div>'
    + '<div class="un">'+esc(x.u)+'</div>';

  /* seekha hua daam — asli bill se. Tay daam nahi hai, isliye saaf likha hai. */
  var dd = x.d, dw = x.dw;
  if (c && c.b && x.b) {                       // brand chuna hua hai to usi ka daam
    for (var bi = 0; bi < x.b.length; bi++) {
      if (x.b[bi].i == c.b) { dd = x.b[bi].d || null; dw = x.b[bi].dw || ''; break; }
    }
  }
  if (dd) {
    h += '<div class="dm">'+esc(dd)+'</div>'
       + '<div class="dmw">'+L.andaza+(dw ? ' · '+esc(dw) : '')+'</div>';
  } else {
    h += '<div class="dmw dmn">'+L.daamcall+'</div>';
  }

  /* brand — sirf jahan hai wahan. "koi bhi" pehle se chuna rehta hai. */
  if (x.b && x.b.length) {
    var sel = c && c.b ? c.b : 0;
    h += '<select class="bsel" data-i="'+x.i+'"><option value="0">'+L.anybrand+'</option>';
    x.b.forEach(function(b){
      h += '<option value="'+b.i+'"'+(sel==b.i?' selected':'')+'>'+esc(b.n)+'</option>';
    });
    h += '</select>';
  }

  return h + '<div class="act">'+ (q
        ? '<div class="step"><button type="button" data-a="-" aria-label="−">−</button><span class="q">'+q+'</span><button type="button" data-a="+" aria-label="+">+</button></div>'
        : '<button type="button" class="addbtn" data-a="+">'+L.add+'</button>')
    + '</div></div>';
}

function match(x, q){
  if (!q) return true;
  var s = (x.n + ' ' + x.u + ' ' + (x.w||'')).toLowerCase();
  return s.indexOf(q) !== -1;
}
var WORDS = {}; <?php foreach ($items as $i): ?>WORDS[<?= (int)$i['id'] ?>] = <?= json_encode(mb_strtolower($i['words'] ?? ''), JSON_UNESCAPED_UNICODE) ?>;<?php endforeach; ?>

function draw(){
  var q = curQ.trim().toLowerCase(), list;
  if (q) {
    list = ITEMS.filter(function(x){
      return (x.n.toLowerCase().indexOf(q) !== -1) || ((WORDS[x.i]||'').indexOf(q) !== -1);
    });
    $('ghead').textContent = list.length ? (list.length + ' ' + L.resultsfor + ' “' + curQ + '”') : '';
  } else if (curG === 'daily') {
    list = ITEMS.filter(function(x){ return POP[x.i] && (x.s||'saaman') === curS; });
    $('ghead').textContent = L.everyday;
  } else {
    list = ITEMS.filter(function(x){ return x.g === curG && (x.s||'saaman') === curS; });
    $('ghead').textContent = GRPS[curG] || '';
  }
  lastCount = list.length;
  $('grid').innerHTML = list.map(cardHtml).join('');
  $('nores').style.display = list.length ? 'none' : 'block';
  $('ghead').style.display = list.length ? 'block' : 'none';
}
var POP = {}; <?php foreach ($items as $i): if ($i['popular']): ?>POP[<?= (int)$i['id'] ?>]=1;<?php endif; endforeach; ?>

/* ---------- cart bar ---------- */
function bar(){
  var n = count();
  $('cartbar').classList.toggle('show', n > 0);
  document.body.classList.toggle('cart-on', n > 0);
  $('cbN').textContent = n + ' ' + L.items;
  var kg = kgTotal();
  $('cbS').textContent = kg >= 1 ? (L.about + ' ' + (kg < 10 ? kg.toFixed(1) : Math.round(kg)) + ' ' + L.kg) : L.shopprice;
  var d = $('tabDot');
  if (d) { if (n) { d.textContent = n; d.style.display='grid'; } else { d.style.display='none'; } }
}

function bump(id, dir){
  var x = BY[id]; if (!x) return;
  var q = cart[id] ? cart[id].q : 0;
  q += (dir === '+' ? 1 : -1);
  if (q <= 0) { delete cart[id]; } else if (q <= 99) { cart[id] = {id:+id, q:q}; }
  save(); bar();
  // sirf us card ko badlo — poori list dobara mat banao
  var card = document.querySelector('.it[data-i="'+id+'"]');
  if (card) {
    var nq = cart[id] ? cart[id].q : 0;
    card.classList.toggle('in', !!nq);
    card.querySelector('.act').innerHTML = nq
      ? '<div class="step"><button type="button" data-a="-" aria-label="−">−</button><span class="q">'+nq+'</span><button type="button" data-a="+" aria-label="+">+</button></div>'
      : '<button type="button" class="addbtn" data-a="+">'+L.add+'</button>';
  }
  if (dir === '+' && (!cart[id] || cart[id].q === 1)) { toast(x.n + ' ' + L.added); }
}

$('grid').addEventListener('click', function(e){
  var b = e.target.closest('[data-a]'); if (!b) return;
  var c = e.target.closest('.it'); if (!c) return;
  bump(c.getAttribute('data-i'), b.getAttribute('data-a'));
});

/* ---------- search + rail ---------- */
var st;
var lg;
function logSearch(q, hits){
  if (!q || q.length < 2) return;
  try {
    var d = new FormData(); d.append('a','srch'); d.append('q',q); d.append('hits',hits);
    if (navigator.sendBeacon) navigator.sendBeacon('/api.php', d);
    else fetch('/api.php', {method:'POST', body:d, keepalive:true}).catch(function(){});
  } catch(e){}
}
$('q').addEventListener('input', function(){
  curQ = this.value;
  $('qc').style.display = curQ ? 'block' : 'none';
  clearTimeout(st); st = setTimeout(draw, 120);
  // do second tak rukne par hi log — har akshar par nahi
  clearTimeout(lg); lg = setTimeout(function(){ logSearch(curQ.trim(), lastCount); }, 2000);
});
$('qc').addEventListener('click', function(){ $('q').value=''; curQ=''; this.style.display='none'; draw(); });
/* ---------- do bade hisse: raashan / bana khana ---------- */
$('secs').addEventListener('click', function(e){
  var b = e.target.closest('button[data-s]'); if (!b) return;
  [].forEach.call(this.querySelectorAll('button'), function(x){ x.classList.remove('on'); });
  b.classList.add('on');
  curS = b.getAttribute('data-s');
  curG = 'daily';
  $('q').value=''; curQ=''; $('qc').style.display='none';
  drawRail(); draw();
  window.scrollTo({top: 0, behavior:'smooth'});
});

/* ---------- brand chunna (jahan hai wahan) ---------- */
$('grid').addEventListener('change', function(e){
  var sel = e.target.closest('select.bsel'); if (!sel) return;
  var id = sel.getAttribute('data-i');
  if (!cart[id]) return;                       // abhi jod hi nahi rakha
  var v = parseInt(sel.value, 10) || 0;
  if (v) { cart[id].b = v; cart[id].bn = sel.options[sel.selectedIndex].text; }
  else   { delete cart[id].b; delete cart[id].bn; }
  save();
  showDaam(sel.closest('.it'), BY[id], v);
});

/* chune hue brand ka daam dikhao — "sab milakar" wali chaudi range ke bajaye */
function showDaam(card, x, bid){
  if (!card || !x) return;
  var dm = card.querySelector('.dm'), dw = card.querySelector('.dmw');
  var d = x.d, w = x.dw;
  if (bid && x.b) {
    for (var i = 0; i < x.b.length; i++) {
      if (x.b[i].i == bid) { d = x.b[i].d || null; w = x.b[i].dw || ''; break; }
    }
  }
  if (d) {
    if (dm) { dm.textContent = d; }
    else if (dw) { var e = document.createElement('div'); e.className='dm'; e.textContent=d;
                   dw.parentNode.insertBefore(e, dw); }
    var dw2 = card.querySelector('.dmw');
    if (dw2) { dw2.textContent = L.andaza + (w ? ' · ' + w : ''); dw2.classList.remove('dmn'); }
  } else {
    if (dm) dm.remove();
    var dw3 = card.querySelector('.dmw');
    if (dw3) { dw3.textContent = L.daamcall; dw3.classList.add('dmn'); }
  }
}

$('rail').addEventListener('click', function(e){
  var b = e.target.closest('button[data-g]'); if (!b) return;
  [].forEach.call(this.querySelectorAll('button'), function(x){ x.classList.remove('on'); });
  b.classList.add('on'); curG = b.getAttribute('data-g');
  $('q').value=''; curQ=''; $('qc').style.display='none';
  draw();
  window.scrollTo({top: $('ghead').offsetTop - 190, behavior:'smooth'});
});

/* ---------- phir se wahi order (Amazon "buy again") ---------- */
(function(){
  var o = []; try { o = JSON.parse(localStorage.getItem('mk_orders')||'[]'); } catch(e){}
  if (!o.length || !o[0].c || !o[0].c.length) return;
  var last = o[0], good = last.c.filter(function(l){ return BY[l.id]; });
  if (!good.length) return;
  var names = good.slice(0,4).map(function(l){ return BY[l.id].n; }).join(', ');
  $('reorder').innerHTML =
    '<div class="box" style="margin-top:14px;display:flex;gap:12px;align-items:center;flex-wrap:wrap">'
    + '<div style="flex:1;min-width:180px"><b style="font-size:16px">'+L.again+'</b>'
    + '<div class="help" style="margin-top:2px">' + esc(names) + (good.length>4 ? ' +'+(good.length-4) : '') + '</div></div>'
    + '<button type="button" class="btn btn-sm btn-brand" id="reBtn">'+L.addall+'</button></div>';
  $('reBtn').addEventListener('click', function(){
    good.forEach(function(l){
      var q = (cart[l.id] ? cart[l.id].q : 0) + l.q;
      cart[l.id] = {id:l.id, q: Math.min(q, 99)};
    });
    save(); bar(); draw(); toast(L.reorderdone);
  });
})();

/* ---------- pardon ke beech aana-jaana ----------
   Kaun sa parda khula hai, ye sirf pate (#pata) se tay hota hai.
   Isse phone ka "back" button bhi theek chalta hai. */
function syncPane(){
  var out = (location.hash === '#pata');
  if (out && !count() && !$('note').value.trim()) { out = false; history.replaceState(null, '', location.pathname); }
  $('paneOut').style.display = out ? 'block' : 'none';
  $('paneCat').style.display = out ? 'none' : 'block';
  if (out) {
    $('cartbar').classList.remove('show');
    drawCart(); fillMe(); calc();
  } else {
    drawRail(); draw(); bar();
  }
}
$('cbGo').addEventListener('click', function(){
  if (!count() && !$('note').value.trim()) { toast(L.pickfirst); return; }
  if (location.hash === '#pata') { syncPane(); } else { location.hash = 'pata'; }
  window.scrollTo(0, 0);
});
$('backBtn').addEventListener('click', function(){
  if (location.hash === '#pata') { history.back(); }
  else { syncPane(); }
  window.scrollTo(0, 0);
});
window.addEventListener('hashchange', function(){
  if (jaoHash(false)) { window.scrollTo(0,0); return; }   // shreni badli
  syncPane(); window.scrollTo(0,0);
});
function showCat(){
  if (location.hash === '#pata') { history.back(); } else { syncPane(); }
}

/* ---------- checkout ka cart ---------- */
function drawCart(){
  var h = '', n = 0;
  for (var k in cart) {
    var x = BY[k]; if (!x) continue; n++;
    h += '<div class="crow" data-i="'+x.i+'"><div class="ci">'+thumb(x,22)+'</div>'
      + '<div class="cn"><b>'+esc(x.n)+'</b><span>'+esc(x.u)+'</span></div>'
      + '<div class="step"><button type="button" data-a="-">−</button><span class="q">'+cart[k].q+'</span><button type="button" data-a="+">+</button></div></div>';
  }
  $('cartList').innerHTML = n ? h : '<p class="help" style="margin:0">'+L.nothingpicked+'</p>';
  var nt = $('note').value.trim();
  $('noteHid').value = nt;
  $('noteShow').style.display = nt ? 'block' : 'none';
  $('noteShow').innerHTML = '<b>'+L.youwrote+'</b><br>' + esc(nt).replace(/\n/g,'<br>');
  $('cartJson').value = JSON.stringify(cart);
  autoWeight();
}
$('cartList').addEventListener('click', function(e){
  var b = e.target.closest('[data-a]'); if (!b) return;
  var r = e.target.closest('.crow'); if (!r) return;
  var id = r.getAttribute('data-i'), q = cart[id] ? cart[id].q : 0;
  q += (b.getAttribute('data-a') === '+' ? 1 : -1);
  if (q <= 0) delete cart[id]; else if (q <= 99) cart[id] = {id:+id, q:q};
  save(); drawCart(); calc();
  if (!count() && !$('note').value.trim()) { showCat(); toast(L.cartempty); }
});

/* ---------- wazan khud chun jaaye ---------- */
function autoWeight(){
  var kg = kgTotal(), b = '0';
  if (kg > 50) b='van'; else if (kg > 30) b='40'; else if (kg > 15) b='20'; else if (kg > 5) b='10';
  var w = $('weight');
  if (!w.dataset.touched) { w.value = b; }
  $('wauto').textContent = kg >= 0.5 ? ('(' + L.about + ' ' + (kg<10?kg.toFixed(1):Math.round(kg)) + ' ' + L.kg + ')') : '';
}
$('weight').addEventListener('change', function(){ this.dataset.touched='1'; calc(); });
$('size').addEventListener('change', calc);
$('village').addEventListener('change', calc);
$('market').addEventListener('change', calc);
$('note').addEventListener('input', function(){ $('noteHid').value = this.value.trim(); });

/* ---------- delivery charge ka andaaza ---------- */
function isFirst(){
  try { return (JSON.parse(localStorage.getItem('mk_orders')||'[]')).length === 0; } catch(e){ return true; }
}
function calc(){
  var v = $('village').value, m = $('market').value,
      w = $('weight').value, s = $('size').value, h = '';
  if (!v) { $('tot').innerHTML = '<div class="r"><span>'+L.delcharge+'</span><b>'+L.choosevill+'</b></div>'; return; }
  if (w === 'van' || s === 'van') {
    $('tot').innerHTML = '<div class="r"><span>'+L.delivery+'</span><b>'+L.byvan+'</b></div>'
      + '<div class="help" style="margin-top:4px">'+L.vannote+'</div>';
    return;
  }
  var base = (VILL[v] && VILL[v][m]) ? VILL[v][m] : 0;
  var extra = (parseInt(w,10)||0) + (parseInt(s,10)||0);
  if (!base && !(VILL[v] && VILL[v].known)) {
    $('tot').innerHTML = '<div class="r"><span>'+L.delcharge+'</span><b>'+L.oncall+'</b></div>'
      + '<div class="help" style="margin-top:4px">'+L.oncallnote+'</div>';
    return;
  }
  var first = false; // Server verifies first-order eligibility on submission.
  h += '<div class="r"><span>'+L.goodsprice+'</span><b>'+L.asperbill+'</b></div>';
  h += '<div class="r"><span>'+L.delivery + (first ? ' <span class="freebadge">'+L.firstfree+'</span>' : '') + '</span><b>'
     + (first ? '<s style="opacity:.55">₹'+base+'</s> ₹0' : '₹'+base) + '</b></div>';
  if (extra) h += '<div class="r"><span>'+L.wsize+'</span><b>₹'+extra+'</b></div>';
  h += '<div class="r big"><span>'+L.youpay+'</span><span>₹' + ((first?0:base) + extra) + '</span></div>';
  $('tot').innerHTML = h;
}

/* ---------- pata yaad rakhna ---------- */
function fillMe(){
  var me = {}; try { me = JSON.parse(localStorage.getItem('mk_me')||'{}')||{}; } catch(e){}
  if (me.n && !$('name').value) $('name').value = me.n;
  if (me.m && !$('mobile').value) $('mobile').value = me.m;
  if (me.v && !$('village').value) $('village').value = me.v;
  if (me.l && !$('landmark').value) $('landmark').value = me.l;
}
$('mobile').addEventListener('input', function(){ this.value = this.value.replace(/\D/g,'').slice(0,10); });

/* ---------- apni jagah bata dijiye ---------- */
$('locBtn').addEventListener('click', function(){
  var b = this;
  if (!navigator.geolocation) { toast(L.nogps); return; }
  $('locS').textContent = L.locfinding;
  b.classList.remove('done');
  navigator.geolocation.getCurrentPosition(function(pos){
    $('lat').value = pos.coords.latitude.toFixed(7);
    $('lng').value = pos.coords.longitude.toFixed(7);
    b.classList.add('done');
    $('locT').textContent = L.locdone;
    $('locS').textContent = L.locacc + ' ' + Math.round(pos.coords.accuracy) + ' m';
    try { localStorage.setItem('mk_loc', JSON.stringify({a:$('lat').value,o:$('lng').value,t:Date.now()})); } catch(e){}
  }, function(err){
    b.classList.remove('done');
    $('locS').textContent = err.code === 1 ? L.locdeny : L.locfail;
  }, { enableHighAccuracy: true, timeout: 12000, maximumAge: 300000 });
});
/* pehle di hui jagah (7 din tak) apne aap bhar do */
(function(){
  try {
    var l = JSON.parse(localStorage.getItem('mk_loc') || 'null');
    if (l && l.a && (Date.now() - (l.t||0)) < 7*864e5) {
      $('lat').value = l.a; $('lng').value = l.o;
      $('locBtn').classList.add('done');
      $('locT').textContent = L.locsaved;
      $('locS').textContent = L.loctap;
    }
  } catch(e){}
})();
$('ofrm').addEventListener('submit', function(){
  $('cartJson').value = JSON.stringify(cart);
  $('noteHid').value = $('note').value.trim();
  // photo ka dabba form ke bahar hai — bhejte waqt andar le aate hain
  var ph = $('photo');
  if (ph && ph.files && ph.files.length) { this.appendChild(ph); }
});

/* ---------- bolkar bataiye (Hindi) ---------- */
var SR = window.SpeechRecognition || window.webkitSpeechRecognition;
function listen(target, hintEl){
  if (!SR) { toast(L.novoice); return; }
  var r = new SR();
  r.lang = L.sr; r.interimResults = true; r.continuous = false; r.maxAlternatives = 1;
  var got = '';
  if (hintEl) { hintEl.style.display='block'; hintEl.textContent = L.listening; }
  document.body.classList.add('listening');
  r.onresult = function(e){
    got = '';
    for (var i = 0; i < e.results.length; i++) { got += e.results[i][0].transcript; }
    if (hintEl) hintEl.textContent = '“' + got + '”';
    if (target === $('q')) { target.value = got; curQ = got; $('qc').style.display = got ? 'block' : 'none'; draw(); }
  };
  r.onerror = function(ev){
    document.body.classList.remove('listening');
    if (hintEl) hintEl.style.display = 'none';
    toast(ev.error === 'not-allowed' ? L.micperm : L.nothear);
  };
  r.onend = function(){
    document.body.classList.remove('listening');
    if (target === $('note') && got) {
      target.value = (target.value.trim() ? target.value.replace(/\s+$/,'') + '\n' : '') + got;
      $('noteHid').value = target.value.trim();
      toast(L.written);
    }
    if (hintEl) setTimeout(function(){ hintEl.style.display='none'; }, 2200);
  };
  try { r.start(); } catch(e) { toast(L.tryagain); }
}
if (SR) {
  $('mic').style.display = 'grid';
  $('mic').addEventListener('click', function(){ listen($('q'), null); });
}
$('wayVoice').addEventListener('click', function(){ listen($('note'), $('voiceHint')); });

/* ---------- photo bhejiye ---------- */
$('photo').addEventListener('change', function(){
  var f = this.files && this.files[0], box = $('photoShow');
  if (!f) { box.style.display = 'none'; box.innerHTML = ''; return; }
  if (f.size > 4 * 1024 * 1024) { toast(L.toobig); this.value = ''; return; }
  var u = URL.createObjectURL(f);
  box.style.display = 'block';
  box.innerHTML = '<div class="phot"><img src="'+u+'" alt="">'
    + '<div><b>'+L.photoon+'</b><i>'+L.photosub+'</i></div>'
    + '<button type="button" class="btn btn-sm" id="phDel" style="background:var(--soft);color:var(--bad)">'+L.remove+'</button></div>';
  $('phDel').addEventListener('click', function(){
    $('photo').value = ''; box.style.display='none'; box.innerHTML='';
  });
});

/* ---------- home page ke dibbe se aana ----------
   /order.php#chaat jaise pate se seedhe sahi hisse aur sahi group par
   pahunch jaiye — do dabane ka kaam ek me.
   Ye sirf pehli baar nahi, hash badalne par bhi chalta hai: wapas
   jane ka button dabane par bhi sahi jagah dikhe. */
var HASHMAP = {
  chaat:  {s:'khana',  g:'chaat'},
  khana:  {s:'khana',  g:'khana'},
  mithai: {s:'khana',  g:'mithai'},
  peene:  {s:'khana',  g:'peene'},
  sabzi:  {s:'saaman', g:'sabzi'},
  dawa:   {s:'saaman', g:'sabun'},
  pooja:  {s:'saaman', g:'pooja'},
  khad:   {s:'saaman', g:'khad'}
};
function jaoHash(pehliBaar){
  var hs = (location.hash || '').replace('#','');
  var m  = HASHMAP[hs];
  if (!m) return false;
  curS = m.s;
  curG = m.g;
  var sb = document.querySelector('#secs button[data-s="'+curS+'"]');
  if (sb) {
    [].forEach.call(document.querySelectorAll('#secs button'), function(x){ x.classList.remove('on'); });
    sb.classList.add('on');
  }
  $('q').value = ''; curQ = ''; $('qc').style.display = 'none';
  drawRail();
  if (!pehliBaar) draw();
  if (hs === 'dawa') { window.setTimeout(function(){ $('note').focus(); }, 300); }
  return true;
}

/* ---------- shuruaat ---------- */
(function(){
  var qs = new URLSearchParams(location.search).get('q');
  if (qs) { $('q').value = qs; curQ = qs; $('qc').style.display = 'block'; }
  jaoHash(true);
})();
syncPane();
if (curQ) { setTimeout(function(){ logSearch(curQ.trim(), lastCount); }, 1500); }
})();
</script>
<?php endif; ?>
<?php include __DIR__ . '/inc/foot.php'; ?>
