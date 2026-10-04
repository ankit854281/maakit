<?php
require_once __DIR__ . '/../inc/fn.php';
$u = need_role(['bpo', 'admin']);
$page_title = 'आज के ऑर्डर — Maakit';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_ok()) {
    $id = (int)post('id');
    if (post('do') === 'status') {
        $st2 = post('status');
        $stamp = ['Confirm'=>'confirmed_at','Assign'=>'assigned_at','Pickup'=>'picked_at','Delivered'=>'delivered_at'][$st2] ?? null;
        if ($stamp) {
            $pdo->prepare("UPDATE orders SET status=?, `$stamp`=COALESCE(`$stamp`, NOW()) WHERE id=?")->execute([$st2, $id]);
        } else {
            $pdo->prepare("UPDATE orders SET status=? WHERE id=?")->execute([$st2, $id]);
        }
        flash('स्टेटस बदल दिया गया।');
    } elseif (post('do') === 'assign') {
        $pdo->prepare("UPDATE orders SET delivery_user=?, status=IF(status='Naya' OR status='Confirm','Assign',status),
                       assigned_at=COALESCE(assigned_at, NOW()) WHERE id=?")
            ->execute([(int)post('delivery_user') ?: null, $id]);
        flash('डिलीवरी पार्टनर को दे दिया गया।');
    } elseif (post('do') === 'amount') {
        $pdo->prepare("UPDATE orders SET goods_amount=?, payment=?, delivery_charge=? WHERE id=?")
            ->execute([(int)post('goods_amount') ?: null, post('payment'), (int)post('delivery_charge') ?: null, $id]);
        flash('रक़म सेव कर दी गई।');
    }
    redirect('/bpo/');
}

$day = get('day', date('Y-m-d'));
// naye (bina chhue) order sabse upar — website se aaya order chhoot na jaaye
$st = $pdo->prepare("SELECT o.*, u.name AS dname FROM orders o LEFT JOIN users u ON u.id=o.delivery_user
                     WHERE DATE(o.created_at)=?
                     ORDER BY (o.status='Naya') DESC, o.id DESC");
$st->execute([$day]);
$orders = $st->fetchAll();
$naye = 0; foreach ($orders as $o) { if ($o['status'] === 'Naya') $naye++; }
$boys = $pdo->query("SELECT id,name FROM users WHERE role='delivery' AND active=1 ORDER BY name")->fetchAll();
include __DIR__ . '/../inc/panel.php';
?>
<section>
<div class="wrap">
  <h2>ऑर्डर — <?= h(date('d-m-Y', strtotime($day))) ?></h2>
  <?php if ($naye): ?>
    <div class="ok" style="background:#FFF0D6;color:#8A5B00;font-weight:700">
      🔔 <?= $naye ?> नया ऑर्डर देखना बाकी है — पहले इन्हें कॉल करके कन्फ़र्म कीजिए।
    </div>
  <?php endif; ?>
  <form method="get" style="margin-bottom:14px;display:flex;gap:8px;align-items:center;flex-wrap:wrap">
    <input type="date" name="day" value="<?= h($day) ?>" style="max-width:200px">
    <button class="btn btn-brand btn-sm">देखिए</button>
    <a class="btn btn-gold btn-sm" href="/bpo/new.php">+ नया ऑर्डर</a>
    <label style="display:flex;align-items:center;gap:6px;margin:0;font-weight:600;font-size:14px">
      <input type="checkbox" id="autoref" style="width:auto;min-height:0" checked> हर मिनट ख़ुद देखे
    </label>
  </form>

  <?php if (!$orders): ?><div class="box">इस दिन का कोई ऑर्डर नहीं है।</div><?php endif; ?>

  <?php foreach ($orders as $o):
    $conf = "Maakit - ऑर्डर कन्फर्म\nऑर्डर नंबर: {$o['order_no']}\nसामान: {$o['items']}\n"
      . ($o['shop'] ? "दुकान: {$o['shop']}\n" : "")
      . "डिलीवरी चार्ज: " . ($o['first_order'] ? "पहली डिलीवरी फ़्री" : "₹" . (int)$o['delivery_charge']) . "\n"
      . "डिलीवरी कोड: {$o['code']}\nसामान लेते समय यह कोड डिलीवरी पार्टनर को बताइए। किसी और को न बताएँ।";
    $del = "Maakit - आपका ऑर्डर {$o['order_no']} पहुँचा दिया गया है।\n"
      . ($o['goods_amount'] ? "सामान: ₹{$o['goods_amount']}\n" : "")
      . "डिलीवरी चार्ज: " . ($o['first_order'] ? "फ़्री" : "₹" . (int)$o['delivery_charge']) . "\n"
      . "Maakit चुनने के लिए धन्यवाद। कोई दिक्कत हो तो आज ही बताइए।"; ?>
    <div class="box" style="margin-bottom:14px">
      <div style="display:flex;justify-content:space-between;flex-wrap:wrap;gap:8px">
        <div>
          <b style="font-size:19px"><?= h($o['order_no']) ?></b>
          <span class="tag tag-gold">कोड <?= h($o['code']) ?></span>
          <span class="tag tag-off"><?= h(status_hi($o['status'])) ?></span>
          <span class="tag tag-off"><?= $o['source'] === 'website' ? 'वेबसाइट से' : ($o['source'] === 'call' ? 'कॉल से' : 'WhatsApp से') ?></span>
          <?php if ($o['first_order']): ?><span class="tag tag-live">पहला ऑर्डर — फ़्री</span><?php endif; ?>
        </div>
        <div class="meta"><?= h(date('h:i A', strtotime($o['created_at']))) ?></div>
      </div>
      <div style="margin-top:8px">
        <div><b><?= h($o['customer_name']) ?></b> · <a href="tel:+91<?= h($o['mobile']) ?>"><?= h($o['mobile']) ?></a></div>
        <div class="meta"><?= h($o['village']) ?><?= $o['landmark'] ? ' · ' . h($o['landmark']) : '' ?>
          <?php if ($o['lat'] !== null): ?> · <a href="https://www.google.com/maps/dir/?api=1&destination=<?= h($o['lat']) ?>,<?= h($o['lng']) ?>" target="_blank" rel="noopener">📍 नक्शा</a><?php endif; ?>
          <?php if ($o['photo']): ?> · <a href="/uploads/<?= h($o['photo']) ?>" target="_blank" rel="noopener">📷 ग्राहक की फ़ोटो</a><?php endif; ?>
        </div>
        <div style="margin-top:6px;background:var(--soft);border-radius:10px;padding:9px 11px;font-size:15.5px;line-height:1.55">
          <?= nl2br(h($o['items'])) ?>
        </div>
        <div class="meta"><?= $o['shop'] ? 'दुकान: ' . h($o['shop']) : 'दुकान: हमारी पसंद' ?> · <?= h(markets()[$o['market']] ?? '') ?>
          · चार्ज: <?= $o['first_order'] ? 'फ़्री' : '₹' . (int)$o['delivery_charge'] ?>
          <?= $o['dname'] ? ' · पार्टनर: ' . h($o['dname']) : '' ?></div>
      </div>

      <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:12px">
        <a class="btn btn-green btn-sm" href="<?= h(wa_link($o['mobile'], $conf)) ?>" target="_blank" rel="noopener">कन्फर्म + कोड भेजिए</a>
        <a class="btn btn-brand btn-sm" href="<?= h(wa_link($o['mobile'], $del)) ?>" target="_blank" rel="noopener">डिलीवर मैसेज</a>
        <a class="btn btn-brand btn-sm" href="tel:+91<?= h($o['mobile']) ?>">कॉल</a>
      </div>

      <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:12px;align-items:flex-end">
        <form method="post" style="display:flex;gap:6px;align-items:flex-end">
          <input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="do" value="status"><input type="hidden" name="id" value="<?= (int)$o['id'] ?>">
          <div><label>स्टेटस</label>
            <select name="status" style="min-width:150px"><?php foreach (status_list() as $k => $v): ?><option value="<?= h($k) ?>" <?= $o['status'] === $k ? 'selected' : '' ?>><?= h($v) ?></option><?php endforeach; ?></select>
          </div>
          <button class="btn btn-brand btn-sm">सेव</button>
        </form>
        <form method="post" style="display:flex;gap:6px;align-items:flex-end">
          <input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="do" value="assign"><input type="hidden" name="id" value="<?= (int)$o['id'] ?>">
          <div><label>डिलीवरी पार्टनर</label>
            <select name="delivery_user" style="min-width:150px"><option value="">— चुनिए —</option>
              <?php foreach ($boys as $b): ?><option value="<?= (int)$b['id'] ?>" <?= (int)$o['delivery_user'] === (int)$b['id'] ? 'selected' : '' ?>><?= h($b['name']) ?></option><?php endforeach; ?>
            </select>
          </div>
          <button class="btn btn-brand btn-sm">दीजिए</button>
        </form>
        <form method="post" style="display:flex;gap:6px;align-items:flex-end;flex-wrap:wrap">
          <input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="do" value="amount"><input type="hidden" name="id" value="<?= (int)$o['id'] ?>">
          <div style="max-width:120px"><label>सामान ₹</label><input type="number" name="goods_amount" value="<?= h($o['goods_amount']) ?>"></div>
          <div style="max-width:120px"><label>डिलीवरी ₹</label><input type="number" name="delivery_charge" value="<?= h($o['delivery_charge']) ?>"></div>
          <div style="max-width:170px"><label>पेमेंट</label>
            <select name="payment">
              <?php foreach (['डिलीवरी पर कैश','डिलीवरी पर UPI','मैं खुद दुकान को UPI करूँगा','एडवांस लिया'] as $p): ?>
                <option <?= $o['payment'] === $p ? 'selected' : '' ?>><?= h($p) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <button class="btn btn-brand btn-sm">सेव</button>
        </form>
      </div>
    </div>
  <?php endforeach; ?>
</div>
</section>
<script>
/* naya order chhoot na jaaye — har 60 second page khud dekh le.
   Jab aap kuchh likh rahe ho ya dropdown khula ho, tab refresh nahi hoga. */
(function(){
  var cb = document.getElementById('autoref');
  var typing = false;
  document.addEventListener('focusin', function(e){
    var t = e.target.tagName; if (t==='INPUT'||t==='SELECT'||t==='TEXTAREA') typing = true;
  });
  document.addEventListener('focusout', function(){ setTimeout(function(){ typing = !!document.querySelector('input:focus,select:focus,textarea:focus'); }, 50); });
  setInterval(function(){
    if (cb && cb.checked && !typing && !document.hidden) { location.reload(); }
  }, 60000);
})();
</script>
<?php include __DIR__ . '/../inc/foot.php'; ?>
