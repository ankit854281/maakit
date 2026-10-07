<?php
// ============================================================
//  इस दुकान से मँगाइए
//
//  Grahak kisi ek dukaan ka saaman, USI dukaan ke daam par
//  chunta hai. Order me business_id chadhta hai, isliye wahi
//  order dukandar ke panel me dikhta hai aur uske hisab me
//  jata hai.
//
//  Paisa: nagad ya seedha dukaan ke UPI par. Maakit sirf
//  delivery ka paisa leta hai — wo alag se dikhta hai.
// ============================================================
require_once __DIR__ . '/inc/fn.php';
require_once __DIR__ . '/inc/dukan.php';
require_once __DIR__ . '/inc/items.php';

// id URL se bhi aa sakti hai aur form se bhi — dono chalein
$id = (int)(get('id') ?: post('id'));
$st = $pdo->prepare("SELECT * FROM businesses WHERE id=? AND status='approved'");
$st->execute([$id]);
$b = $st->fetch();
if (!$b) { redirect('/directory.php'); }
if (empty($b['items_on'])) { redirect('/business.php?id=' . $id); }

$items = dukan_items($pdo, $id, true);
if (!$items) { redirect('/business.php?id=' . $id); }

$page_title = $b['name'] . ' से मँगाइए — Maakit';
$tab = 'order';
$villages = village_list($pdo);
$err = ''; $done = null;
$me = cust();

// PHP's session lock serializes submissions from the same browser. Keep a
// bounded receipt per form so double taps / POST refresh reuse its confirmation.
$attempts = $_SESSION['shop_checkout'][$id] ?? [];
foreach ($attempts as $key => $attempt) {
    if ($attempt['at'] < time()-3600) unset($attempts[$key]);
}
$checkout_key = $_POST['checkout_key'] ?? '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_ok() || !is_string($checkout_key) || !isset($attempts[$checkout_key])) {
        $err = t('This order form has expired. Review the items and send it again.', 'ऑर्डर फॉर्म की अवधि समाप्त हो गई। सामान जाँचकर दोबारा भेजिए।');
    } else {
        $done = $attempts[$checkout_key]['done'] ?? null;
    }
}
if (!is_string($checkout_key) || !isset($attempts[$checkout_key])) {
    $checkout_key = bin2hex(random_bytes(16));
    $attempts[$checkout_key] = ['at'=>time()];
}
$_SESSION['shop_checkout'][$id] = array_slice($attempts, -20, null, true);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_ok() && post('do') === 'mangao' && !$done && !$err) {
    $name    = post('name');
    $mobile  = preg_replace('/\D/', '', post('mobile'));
    $village = post('village');
    $landmark= post('landmark');
    $pay     = post('pay') === 'upi' ? 'UPI (दुकान को सीधा)' : 'नगद — सामान लेते समय';
    $note    = post('note');
    $market = post('market','kapsethi');
    if (!array_key_exists($market,markets())) $market='kapsethi';
    $weight = post('weight','0');
    if (!array_key_exists($weight,weight_extras())) $weight='0';
    $size = post('size','0');
    if (!array_key_exists($size,size_extras())) $size='0';
    $qty     = $_POST['q'] ?? [];
    $shown   = $_POST['shown_price'] ?? [];
    $cart_error = '';
    $current = array_column($items, null, 'id');
    if (!is_array($qty) || !is_array($shown)) {
        $cart_error = t('Check the quantities and try again.', 'गिनती जाँचकर दोबारा कोशिश कीजिए।'); $qty = [];
    }
    foreach ($qty as $item_id => $quantity) {
        if (!is_scalar($quantity) || !preg_match('/^\d{1,2}$/', (string)$quantity) || (int)$quantity > 50) {
            $cart_error = t('Choose a whole quantity from 0 to 50.', 'गिनती 0 से 50 तक पूरी संख्या में भरिए।'); break;
        }
        if ((int)$quantity === 0) continue;
        if (!isset($current[$item_id])) {
            $cart_error = t('A selected item is no longer available. Review the list before ordering again.', 'चुना हुआ सामान अब उपलब्ध नहीं है। सूची जाँचकर दोबारा ऑर्डर कीजिए।'); break;
        }
        if (!isset($shown[$item_id]) || !is_scalar($shown[$item_id]) || (string)$shown[$item_id] !== (string)(int)$current[$item_id]['price']) {
            $cart_error = t('Item prices have changed. Review the updated prices and send the order again.', 'सामान के दाम बदल गए हैं। नए दाम जाँचकर ऑर्डर दोबारा भेजिए।'); break;
        }
    }

    // ---- kya-kya chuna gaya ----
    $lines = []; $maal = 0; $kg = 0;
    foreach ($items as $it) {
        $n = (int)($qty[$it['id']] ?? 0);
        if ($n < 1) continue;
        if ($n > 50) $n = 50;
        $lines[] = ['name' => $it['name'], 'unit' => $it['unit'], 'qty' => $n,
                    'price' => (int)$it['price'], 'id' => (int)$it['id']];
        $maal += (int)$it['price'] * $n;
        $kg += unit_kg($it['unit']) * $n;
    }

    // Never let the declared weight undercut the known packed-product weight.
    $minimum=kg_band($kg);
    if ($minimum === 'van' || ($weight !== 'van' && (int)$weight < (int)$minimum)) $weight=$minimum;

    if ($cart_error)                   { $err = $cart_error; }
    elseif (!$lines)                       { $err = 'कुछ चुना ही नहीं। जो चाहिए उसकी गिनती भर दीजिए।'; }
    elseif (mb_strlen($name) < 2)      { $err = 'अपना नाम लिखिए।'; }
    elseif (strlen($mobile) !== 10)    { $err = 'मोबाइल नंबर 10 अंकों का लिखिए।'; }
    elseif (!coverage_enabled($order_area=coverage_area($pdo,$village))) { $err=coverage_error(); }
    elseif (!coverage_shop_allowed($pdo,$id,$order_area)) { $err=t('This shop does not deliver to the selected area. Choose a shop serving your area.', 'यह दुकान चुने हुए इलाके में डिलीवरी नहीं देती। अपने इलाके की दुकान चुनिए।'); }
    elseif (post('pay') === 'upi' && !dukan_upi_link($b)) { $err = t('This shop has no UPI details yet. Choose cash or contact us.', 'दुकान का UPI अभी नहीं भरा है। नगद चुनिए या हमें कॉल कीजिए।'); }
    elseif (!dukan_khuli($b))          { $err = 'यह दुकान अभी बंद है। खुलने पर मँगा लीजिए।'; }
    else {
        // ek number se ghante me 6 se jyada order nahi
        $tz = $pdo->prepare("SELECT COUNT(*) c FROM orders WHERE mobile=? AND created_at > NOW() - INTERVAL 1 HOUR");
        $tz->execute([$mobile]);
        if ((int)$tz->fetch()['c'] >= 6) {
            $err = 'एक घंटे में बहुत ऑर्डर हो गए। थोड़ी देर बाद कीजिए, या हमें फ़ोन कर दीजिए।';
        } else {
            $text = [];
            foreach ($lines as $l) {
                $text[] = $l['name'] . ' (' . $l['unit'] . ') × ' . $l['qty'] . ' = ₹' . ($l['price'] * $l['qty']);
            }
            $items_text = implode("\n", $text);

            $pehla = $pdo->prepare("SELECT COUNT(*) c FROM orders WHERE mobile=?");
            $pehla->execute([$mobile]);
            $first = ((int)$pehla->fetch()['c'] === 0 && ($order_area['first_free'] ?? 1)) ? 1 : 0;
            $calc  = calc_charge($village, $market, $weight, $size, $pdo, (bool)$first);

            $order_no = new_order_no($pdo);
            $code     = new_code();
            $pdo->prepare("INSERT INTO orders
                (order_no, code, source, customer_id, customer_name, mobile, village, landmark,
                 items, items_json, goods_note, shop, business_id, goods_amount, market,
                 weight_extra, size_extra, first_order, delivery_charge, payment, status, shop_status)
                VALUES (?,?,'website',?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'Naya','naya')")
                ->execute([$order_no, $code, $me['id'] ?? null, $name, $mobile, $village, $landmark,
                           $items_text, json_encode($lines, JSON_UNESCAPED_UNICODE), $note,
                           $b['name'], $id, $maal, $market, $weight, $size, $first, $calc['total'], $pay]);

            // kaun saaman kitna chala — dukandar ko hisab me dikhega
            $up = $pdo->prepare("UPDATE shop_items SET sold = sold + ? WHERE id=? AND business_id=?");
            foreach ($lines as $l) { $up->execute([$l['qty'], $l['id'], $id]); }

            $done = ['no' => $order_no, 'code' => $code, 'maal' => $maal,
                     'charge' => $calc, 'lines' => $lines, 'pay' => $pay, 'mobile' => $mobile];
            $_SESSION['shop_checkout'][$id][$checkout_key]['done'] = $done;
        }
    }
}

include __DIR__ . '/inc/head.php';
?>
<section><div class="wrap" style="max-width:720px">

<?php if ($done):
    $wa = wa_link(MAAKIT_WA, "नमस्ते Maakit, मैंने " . $b['name'] . " से ऑर्डर किया है।\n"
        . "ऑर्डर नंबर: " . $done['no'] . "\n\n" . implode("\n", array_map(
            fn($l) => $l['name'] . ' × ' . $l['qty'], $done['lines']))
        . "\n\nसामान: ₹" . $done['maal']);
    $upi = dukan_upi_link($b, $done['maal'], $done['no']);
?>
  <div class="ok" style="font-size:17px">
    <b>ऑर्डर पहुँच गया ✓</b><br>
    ऑर्डर नंबर <b><?= h($done['no']) ?></b> · कोड <b><?= h($done['code']) ?></b>
  </div>

  <div class="box">
    <h2 style="margin-top:0"><?= h($b['name']) ?></h2>
    <?php foreach ($done['lines'] as $l): ?>
      <div style="border-top:1px solid var(--line);padding:8px 0;display:flex;gap:10px">
        <div style="flex:1"><?= h($l['name']) ?> <span class="meta">(<?= h($l['unit']) ?>) × <?= (int)$l['qty'] ?></span></div>
        <div style="font-weight:700">₹<?= (int)$l['price'] * (int)$l['qty'] ?></div>
      </div>
    <?php endforeach; ?>
    <div style="border-top:2px solid var(--line);margin-top:8px;padding-top:10px;display:flex;gap:10px">
      <div style="flex:1"><b>सामान</b></div><div style="font-weight:800">₹<?= (int)$done['maal'] ?></div>
    </div>
    <div style="display:flex;gap:10px;margin-top:6px">
      <div style="flex:1">डिलीवरी</div>
      <div style="font-weight:700"><?= $done['charge']['total'] === null
          ? h($done['charge']['msg']) : '₹' . (int)$done['charge']['total'] ?></div>
    </div>
    <p class="help" style="margin-top:10px">देना है: <b><?= h($done['pay']) ?></b>।
      सामान का पैसा दुकान का है, डिलीवरी का पैसा Maakit का।</p>
  </div>

  <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:14px">
    <a class="btn btn-brand" href="<?= h($wa) ?>" target="_blank" rel="noopener">WhatsApp पर पक्का कीजिए</a>
    <a class="btn btn-gold" href="/track.php?no=<?= h($done['no']) ?>&amp;m=<?= h($done['mobile']) ?>">कहाँ पहुँचा, देखिए</a>
    <?php if ($upi): ?>
      <a class="btn btn-sm" style="background:#EFEAE0" href="<?= h($upi) ?>">UPI से अभी दे दीजिए</a>
    <?php endif; ?>
  </div>
  <p class="help" style="margin-top:12px">दुकान को ऑर्डर दिख गया है। कुछ बदलना हो तो
    <a href="tel:<?= h(MAAKIT_PHONE) ?>"><?= h(MAAKIT_NUMBER_SHOW) ?></a> पर फ़ोन कर दीजिए।</p>

<?php else: ?>

  <div style="display:flex;gap:12px;align-items:center">
    <?php if ($b['photo']): ?>
      <span class="ph" style="width:56px;height:56px;flex:none">
        <img src="/uploads/<?= h($b['photo']) ?>" alt=""></span>
    <?php endif; ?>
    <div>
      <h2 style="margin:0"><?= h($b['name']) ?></h2>
      <div class="meta"><?= h($b['village']) ?> ·
        <span class="tag <?= dukan_khuli($b) ? 'tag-live' : 'tag-off' ?>">
          <?= dukan_khuli($b) ? 'अभी खुली है' : 'अभी बंद है' ?></span></div>
    </div>
  </div>
  <p class="lead" style="margin-top:10px">दाम इसी दुकान के हैं। जो चाहिए उसकी गिनती भर दीजिए।</p>

  <?php if ($err): ?><div class="err"><?= h($err) ?></div><?php endif; ?>

  <form method="post" id="mf" action="/dukan-se.php?id=<?= $id ?>">
    <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
    <input type="hidden" name="do" value="mangao">
    <input type="hidden" name="checkout_key" value="<?= h($checkout_key) ?>">
    <input type="hidden" name="id" value="<?= $id ?>">

    <div class="box">
      <?php foreach ($items as $it): ?>
        <div style="border-top:1px solid var(--line);padding:10px 0;display:flex;gap:10px;align-items:center">
          <?php if ($it['photo']): ?>
            <img src="/uploads/<?= h($it['photo']) ?>" alt="" width="46" height="46"
                 style="border-radius:8px;object-fit:cover;flex:none">
          <?php endif; ?>
          <div style="flex:1;min-width:0">
            <b><?= h($it['name']) ?></b>
            <div class="meta"><?= h($it['unit']) ?></div>
          </div>
          <div style="font-weight:800;flex:none">₹<?= (int)$it['price'] ?></div>
          <input type="hidden" name="shown_price[<?= (int)$it['id'] ?>]" value="<?= (int)$it['price'] ?>">
          <input class="qn" type="number" name="q[<?= (int)$it['id'] ?>]" value="<?= h(is_scalar($_POST['q'][$it['id']] ?? null) ? min(50, max(0, (int)$_POST['q'][$it['id']])) : 0) ?>" min="0" max="50"
                 inputmode="numeric" data-p="<?= (int)$it['price'] ?>" data-kg="<?= h(unit_kg($it['unit'])) ?>"
                 style="width:62px;flex:none" aria-label="<?= h($it['name']) ?> की गिनती">
        </div>
      <?php endforeach; ?>
      <div style="border-top:2px solid var(--line);margin-top:8px;padding-top:10px;display:flex;gap:10px">
        <div style="flex:1"><b>सामान का जोड़</b></div>
        <div style="font-weight:800;font-size:20px;color:var(--brand)">₹<span id="jod">0</span></div>
      </div>
      <p class="help">डिलीवरी का पैसा अलग है — आपका गाँव चुनने पर नीचे दिख जाएगा।</p>
    </div>

    <div class="box" style="margin-top:14px">
      <h3 style="margin-top:0">कहाँ भेजना है</h3>
      <div class="field"><label>आपका नाम</label>
        <input type="text" name="name" value="<?= h($me['name'] ?? post('name')) ?>" required></div>
      <div class="field"><label>मोबाइल नंबर</label>
        <input type="tel" name="mobile" value="<?= h($me['mobile'] ?? post('mobile')) ?>" required></div>
      <div class="field"><label>गाँव</label>
        <select name="village" required>
          <option value="">— चुनिए —</option>
          <?php foreach ($villages as $v): ?>
            <option value="<?= h($v['name']) ?>" <?= (post('village') ?: (coverage_selected($pdo)['name'] ?? ($me['village'] ?? ''))) === $v['name'] ? 'selected' : '' ?>><?= h(coverage_label($v)) ?></option>
          <?php endforeach; ?>
        </select></div>
      <div class="field"><label>पहचान (किसके घर के पास)</label>
        <input type="text" name="landmark" value="<?= h($me['landmark'] ?? post('landmark')) ?>"></div>
      <div class="field"><label><?= t('Shop market', 'दुकान किस बाज़ार में है') ?></label>
        <select name="market"><?php foreach (markets() as $key=>$label): ?><option value="<?= h($key) ?>"><?= h($label) ?></option><?php endforeach; ?></select></div>
      <div class="field"><label><?= t('Total weight', 'सामान का कुल वजन') ?></label>
        <select name="weight"><?php foreach (weight_extras() as $key=>$label): ?><option value="<?= h($key) ?>"><?= h($label) ?></option><?php endforeach; ?></select></div>
      <div class="field"><label><?= t('Size or fragile goods', 'बड़ा या नाज़ुक सामान') ?></label>
        <select name="size"><?php foreach (size_extras() as $key=>$label): ?><option value="<?= h($key) ?>"><?= h($label) ?></option><?php endforeach; ?></select></div>
      <div class="note" id="deliveryEstimate" aria-live="polite"></div>
      <p class="help"><?= t('Normal delivery fee is shown here. Eligible first orders waive the base fee; weight and size extras remain. Final fee is checked when you submit. Unknown fees are confirmed before dispatch.', 'यहाँ सामान्य delivery charge दिखेगा। योग्य पहले order पर base fee माफ है; वजन और आकार का अतिरिक्त charge रहेगा। भेजते समय final charge जाँचा जाएगा। अनिश्चित charge सामान भेजने से पहले पक्का होगा।') ?></p>
      <div class="field"><label>पैसा कैसे देंगे</label>
        <select name="pay">
          <option value="nagad">नगद — सामान लेते समय</option>
          <?php if (dukan_upi_link($b)): ?>
            <option value="upi">UPI — सीधा दुकान को</option>
          <?php endif; ?>
        </select>
        <p class="help">सामान का पैसा दुकान का, डिलीवरी का पैसा Maakit का।
          Maakit आपका पैसा अपने पास नहीं रखता।</p></div>
      <div class="field"><label>कुछ कहना हो (मर्ज़ी से)</label>
        <input type="text" name="note" maxlength="255" placeholder="जैसे: शाम तक भेज दीजिए"></div>
      <button class="btn btn-brand" style="width:100%;font-size:17px">ऑर्डर कर दीजिए</button>
    </div>
  </form>

  <script>
  (function () {
    var f = document.getElementById('mf'), out = document.getElementById('jod');
    var rates = <?= json_encode(array_column($villages,null,'name'), JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>;
    var wording = <?= json_encode(['choose'=>t('Choose your village to see the delivery fee.','delivery charge देखने के लिए गाँव चुनिए।'),'call'=>t('Delivery fee will be confirmed on call before dispatch.','delivery charge भेजने से पहले कॉल पर पक्का होगा।'),'fee'=>t('Normal delivery fee','सामान्य delivery charge'),'total'=>t('Goods + normal delivery','सामान + सामान्य delivery')], JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>;
    function jodo() {
      var s = 0, kg = 0;
      f.querySelectorAll('.qn').forEach(function (i) {
        var n = parseInt(i.value, 10); if (!n || n < 0) n = 0;
        n = Math.min(50,n);
        s += n * parseInt(i.dataset.p, 10);
        kg += n * parseFloat(i.dataset.kg || 0);
      });
      out.textContent = s;
      var minimum=kg > 50 ? 'van' : kg > 30 ? '40' : kg > 15 ? '20' : kg > 5 ? '10' : '0';
      var weight=f.elements.weight;
      if (minimum==='van' || (weight.value!=='van' && Number(weight.value)<Number(minimum))) weight.value=minimum;
      var village=f.elements.village.value, market=f.elements.market.value;
      var result=document.getElementById('deliveryEstimate');
      if (!village) { result.textContent=wording.choose; return; }
      var base=rates[village] ? Number(rates[village].base_fee !== null ? rates[village].base_fee : rates[village]['rate_'+market]) : 0;
      if ((!base && rates[village].base_fee === null) || weight.value==='van' || f.elements.size.value==='van') { result.textContent=wording.call; return; }
      var charge=base+Number(weight.value)+Number(f.elements.size.value);
      result.textContent=wording.fee+': ₹'+charge+' · '+wording.total+': ₹'+(s+charge);
    }
    f.addEventListener('input', jodo);
    f.addEventListener('change', jodo);
    jodo();
  })();
  </script>

<?php endif; ?>

</div></section>
<?php include __DIR__ . '/inc/foot.php'; ?>
