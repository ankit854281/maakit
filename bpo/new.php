<?php
require_once __DIR__ . '/../inc/fn.php';
require_once __DIR__ . '/../inc/items.php';
$u = need_role(['bpo', 'admin']);
$page_title = 'नया ऑर्डर — Maakit';
$villages = village_list($pdo);
$err = ''; $done = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_ok()) {
    $name = post('name'); $mobile = preg_replace('/\D/', '', post('mobile'));
    $village = post('village'); $landmark = post('landmark'); $items = post('items');
    $shop = post('shop'); $market = post('market', 'kapsethi'); $w = post('weight', '0'); $sz = post('size', '0');
    $pay = post('payment'); $src = post('source', 'call'); $sector = post('sector');

    if (mb_strlen($name) < 2 || strlen($mobile) !== 10 || !$village || mb_strlen($items) < 3) {
        $err = 'नाम, 10 अंकों का मोबाइल, गाँव और सामान — चारों ज़रूरी हैं।';
    } else {
        $stc = $pdo->prepare("SELECT COUNT(*) c FROM orders WHERE mobile=?"); $stc->execute([$mobile]);
        $first = ((int)$stc->fetch()['c'] === 0) ? 1 : 0;
        $calc = calc_charge($village, $market, $w, $sz, $pdo, (bool)$first);
        $charge = post('delivery_charge') !== '' ? (int)post('delivery_charge') : $calc['total'];
        $order_no = new_order_no($pdo); $code = new_code();
        $pdo->prepare("INSERT INTO orders (order_no, code, source, customer_name, mobile, village, landmark, items, shop, market, sector, weight_extra, size_extra, first_order, delivery_charge, payment, status)
                       VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?, 'Confirm')")
            ->execute([$order_no, $code, $src, $name, $mobile, $village, $landmark, $items, $shop, $market, $sector, $w, $sz, $first, $charge, $pay]);

        $conf = "Maakit - ऑर्डर कन्फर्म\nऑर्डर नंबर: $order_no\nसामान: $items\n"
            . ($shop ? "दुकान: $shop\n" : "")
            . "डिलीवरी चार्ज: " . ($first ? "पहली डिलीवरी फ़्री" : "₹" . (int)$charge) . "\n"
            . "डिलीवरी कोड: $code\nसामान लेते समय यह कोड डिलीवरी पार्टनर को बताइए। किसी और को न बताएँ।";
        $done = ['no' => $order_no, 'code' => $code, 'wa' => wa_link($mobile, $conf), 'charge' => $charge, 'first' => $first];
    }
}
include __DIR__ . '/../inc/panel.php';
?>
<section>
<div class="wrap" style="max-width:720px">
<?php if ($done): ?>
  <div class="box">
    <h2>ऑर्डर बन गया</h2>
    <div class="note" style="margin-bottom:12px">
      <div><b>ऑर्डर नंबर:</b> <?= h($done['no']) ?></div>
      <div><b>कोड:</b> <?= h($done['code']) ?></div>
      <div><b>चार्ज:</b> <?= $done['first'] ? 'पहली डिलीवरी फ़्री' : '₹' . (int)$done['charge'] ?></div>
    </div>
    <a class="btn btn-green" href="<?= h($done['wa']) ?>" target="_blank" rel="noopener">कन्फर्म मैसेज WhatsApp पर भेजिए</a>
    <div style="margin-top:12px;display:flex;gap:8px"><a class="btn btn-brand btn-sm" href="/bpo/new.php">+ एक और ऑर्डर</a><a class="btn btn-brand btn-sm" href="/bpo/">आज के ऑर्डर</a></div>
  </div>
<?php else: ?>
  <h2>नया ऑर्डर लिखिए</h2>
  <p class="lead">कॉल या WhatsApp पर आया ऑर्डर यहाँ लिखिए। ऑर्डर नंबर और कोड अपने आप बनेंगे।</p>
  <?php if ($err): ?><div class="err"><?= h($err) ?></div><?php endif; ?>
  <form method="post" class="box">
    <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
    <div class="field"><label>ऑर्डर कहाँ से आया</label>
      <select name="source"><option value="call">कॉल से</option><option value="whatsapp">WhatsApp से</option></select></div>
    <div class="field"><label>ग्राहक का नाम</label><input type="text" name="name" required></div>
    <div class="field"><label>मोबाइल (10 अंक)</label><input type="tel" name="mobile" required></div>
    <div class="field"><label>गाँव</label>
      <select name="village" required><option value="">— चुनिए —</option>
        <?php foreach ($villages as $v): ?><option><?= h($v['name']) ?></option><?php endforeach; ?></select></div>
    <div class="field"><label>घर की पहचान</label><input type="text" name="landmark"></div>
    <div class="field"><label for="items">सामान (मात्रा के साथ)</label>
      <div class="appsearch" style="position:static;padding:0 0 8px">
        <div class="in">
          <span class="ic">🔎</span>
          <input type="text" id="isrch" placeholder="लिस्ट से ढूंढिए — आटा, तेल, दाल…" autocomplete="off">
        </div>
      </div>
      <div id="ires" class="qchips" style="margin:0 0 8px"></div>
      <textarea id="items" name="items" required placeholder="आटा (गेहूँ) — 5 किलो&#10;सरसों तेल — 1 लीटर"></textarea>
      <p class="help">ऊपर ढूंढकर दबाइए — नीचे अपने आप लिख जाएगा। हाथ से भी लिख सकते हैं।</p>
    </div>
    <div class="field"><label>दुकान</label><input type="text" name="shop" placeholder="ग्राहक की बताई दुकान, या खाली"></div>
    <div class="field"><label>बाज़ार</label>
      <select name="market"><?php foreach (markets() as $k => $m): ?><option value="<?= h($k) ?>"><?= h($m) ?></option><?php endforeach; ?></select></div>
    <div class="field"><label>सेक्टर</label>
      <select name="sector"><?php foreach (['किराना','खाना/नाश्ता','दवाई','सब्ज़ी-फल','अन्य'] as $s): ?><option><?= h($s) ?></option><?php endforeach; ?></select></div>
    <div class="field"><label>वज़न</label>
      <select name="weight"><?php foreach (weight_extras() as $k => $t): ?><option value="<?= h($k) ?>"><?= h($t) ?></option><?php endforeach; ?></select></div>
    <div class="field"><label>आकार</label>
      <select name="size"><?php foreach (size_extras() as $k => $t): ?><option value="<?= h($k) ?>"><?= h($t) ?></option><?php endforeach; ?></select></div>
    <div class="field"><label>डिलीवरी चार्ज ₹ (खाली छोड़ेंगे तो अपने आप लगेगा)</label><input type="number" name="delivery_charge"></div>
    <div class="field"><label>पेमेंट</label>
      <select name="payment"><?php foreach (['डिलीवरी पर कैश','डिलीवरी पर UPI','मैं खुद दुकान को UPI करूँगा','एडवांस लिया'] as $p): ?><option><?= h($p) ?></option><?php endforeach; ?></select></div>
    <button class="btn btn-brand" type="submit">ऑर्डर बनाइए</button>
  </form>
  <script>
  (function(){
    var IT = <?= json_encode(array_map(fn($i) => ['n'=>$i['name'],'u'=>$i['unit'],'w'=>mb_strtolower($i['name'].' '.$i['words'])], items_all($pdo)), JSON_UNESCAPED_UNICODE) ?>;
    var s = document.getElementById('isrch'), r = document.getElementById('ires'), ta = document.getElementById('items');
    if (!s) return;
    function esc(t){ return String(t).replace(/[&<>"]/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c];}); }
    s.addEventListener('input', function(){
      var q = this.value.trim().toLowerCase();
      if (q.length < 2) { r.innerHTML = ''; return; }
      var f = IT.filter(function(x){ return x.w.indexOf(q) !== -1; }).slice(0, 12);
      r.innerHTML = f.map(function(x,i){ return '<button type="button" data-k="'+i+'">'+esc(x.n)+' · '+esc(x.u)+'</button>'; }).join('');
      r._f = f;
    });
    r.addEventListener('click', function(e){
      var b = e.target.closest('button[data-k]'); if (!b || !r._f) return;
      var x = r._f[+b.getAttribute('data-k')];
      ta.value = (ta.value.trim() ? ta.value.replace(/\s+$/,'') + '\n' : '') + x.n + ' — ' + x.u;
      ta.focus(); s.value = ''; r.innerHTML = '';
    });
  })();
  </script>
<?php endif; ?>
</div>
</section>
<?php include __DIR__ . '/../inc/foot.php'; ?>
