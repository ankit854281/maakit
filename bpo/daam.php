<?php
require_once __DIR__ . '/../inc/fn.php';
require_once __DIR__ . '/../inc/icons.php';
require_once __DIR__ . '/../inc/items.php';
require_once __DIR__ . '/../inc/daam.php';
$u = need_role(['bpo', 'admin']);
$page_title = 'दाम लिखिए — Maakit';

$id = (int)get('id', post('id'));
$st = $pdo->prepare("SELECT * FROM orders WHERE id=?");
$st->execute([$id]);
$o = $st->fetch();

if (!$o) {
    include __DIR__ . '/../inc/panel.php'; ?>
    <section><div class="wrap"><div class="empty"><b>यह ऑर्डर नहीं मिला।</b>
      <p><a class="btn btn-brand btn-sm" style="margin-top:10px" href="/bpo/">वापस</a></p></div></div></section>
    <?php include __DIR__ . '/../inc/foot.php'; exit;
}

$cart   = cart_parse($pdo, $o['items_json']);
$brands = brands_all($pdo);
$ib     = item_brands_all($pdo);
$saved  = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_ok() && post('do') === 'daam') {
    foreach (($_POST['p'] ?? []) as $iid => $val) {
        $val = (int)$val;
        if ($val <= 0) continue;                       // khaali chhoda — koi baat nahi
        $bid = (int)($_POST['b'][$iid] ?? 0);
        $qty = 1;
        foreach ($cart['lines'] as $l) if ((int)$l['id'] === (int)$iid) $qty = (int)$l['q'];
        if (daam_likho($pdo, (int)$iid, $val, [
                'brand_id' => $bid ?: null,
                'market'   => $o['market'] ?: null,
                'shop'     => $o['shop'] ?: null,
                'qty'      => $qty,
                'order_id' => (int)$o['id'],
                'by_user'  => (int)$u['id'],
            ])) $saved++;
    }
    $pdo->prepare("UPDATE orders SET priced_at = NOW() WHERE id=?")->execute([$o['id']]);
    flash($saved ? "$saved सामान के दाम लिख दिए गए। अब ग्राहकों को अंदाज़ा दिखेगा।"
                 : 'कोई दाम नहीं लिखा गया।');
    redirect('/bpo/daam.php?id=' . $o['id']);
}

$fl = flash();
include __DIR__ . '/../inc/panel.php';
?>
<section>
<div class="wrap" style="max-width:720px">

  <p style="margin:0 0 12px"><a class="help" href="/bpo/">&larr; आज के ऑर्डर</a></p>

  <h2>दाम लिखिए</h2>
  <p class="lead">
    बिल में जो दाम लगा, वही लिख दीजिए। इससे अगली बार ग्राहक को अंदाज़ा दिखेगा —
    <b>Maakit खुद सीखता जाएगा।</b>
  </p>

  <?php if ($fl): ?><div class="ok"><?= h($fl) ?></div><?php endif; ?>

  <div class="box" style="margin-bottom:16px">
    <div style="display:flex;justify-content:space-between;flex-wrap:wrap;gap:8px">
      <b style="font-size:17px"><?= h($o['order_no']) ?> · <?= h($o['customer_name']) ?></b>
      <span class="help"><?= h($o['village']) ?> · <?= h(dt_fmt($o['created_at'])) ?></span>
    </div>
    <?php if ($o['shop']): ?><div class="help" style="margin-top:4px">दुकान: <?= h($o['shop']) ?></div><?php endif; ?>
    <?php if ($o['goods_amount']): ?>
      <div style="margin-top:8px;font-size:16px">बिल का कुल: <b>₹<?= (int)$o['goods_amount'] ?></b></div>
    <?php endif; ?>
    <?php if ($o['bill_photo']): ?>
      <a href="/uploads/<?= h($o['bill_photo']) ?>" target="_blank" rel="noopener"
         style="display:inline-block;margin-top:10px">
        <img src="/uploads/<?= h($o['bill_photo']) ?>" alt="बिल"
             style="max-width:180px;border-radius:10px;border:1.5px solid var(--line)">
        <div class="help">बिल की फ़ोटो — खोलकर देखिए</div>
      </a>
    <?php endif; ?>
  </div>

  <?php if (!$cart['lines']): ?>
    <div class="empty">
      <div class="e"><?= svc_icon('bill', 44) ?></div>
      <b>इस ऑर्डर में लिस्ट वाला सामान नहीं है।</b>
      <p class="help" style="margin-top:6px">
        ग्राहक ने बोलकर, लिखकर या फ़ोटो से मँगाया था — इसलिए यहाँ दाम नहीं लिखे जा सकते।
      </p>
      <?php if ($o['items']): ?>
        <div class="note" style="margin-top:12px;text-align:left"><?= nl2br(h($o['items'])) ?></div>
      <?php endif; ?>
    </div>
  <?php else: ?>

    <form method="post">
      <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
      <input type="hidden" name="do" value="daam">
      <input type="hidden" name="id" value="<?= (int)$o['id'] ?>">

      <?php foreach ($cart['lines'] as $l):
        $iid  = (int)$l['id'];
        $old  = daam_ek($pdo, $iid);                   // pichhla daam — pehle se bhar do
        $bs   = $ib[$iid] ?? [];
      ?>
        <div class="drow">
          <div class="dnm">
            <b><?= h($l['name']) ?></b>
            <span class="help"><?= $l['q'] > 1 ? num($l['q']) . ' × ' : '' ?><?= h($l['unit']) ?></span>
            <?php if ($old): ?>
              <span class="dold">पिछली बार <?= h(daam_likhawat($old)) ?> · <?= h(daam_kab($old)) ?></span>
            <?php endif; ?>
          </div>

          <?php if ($bs): ?>
            <select name="b[<?= $iid ?>]" class="dbr">
              <option value="0">कोई भी ब्रांड</option>
              <?php foreach ($bs as $b): ?>
                <option value="<?= (int)$b['id'] ?>"><?= h($b['name']) ?></option>
              <?php endforeach; ?>
            </select>
          <?php endif; ?>

          <div class="dpr">
            <span>₹</span>
            <input type="number" name="p[<?= $iid ?>]" min="1" max="100000" inputmode="numeric"
                   value="<?= $old ? (int)$old['price'] : '' ?>"
                   placeholder="<?= $old ? '' : 'दाम' ?>">
          </div>
        </div>
      <?php endforeach; ?>

      <div class="box" style="margin-top:14px;display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap">
        <div>
          <b style="font-size:17px">जोड़: ₹<span id="jod">0</span></b>
          <?php if ($o['goods_amount']): ?>
            <div class="help" id="milan">बिल ₹<?= (int)$o['goods_amount'] ?> से मिलाइए</div>
          <?php endif; ?>
        </div>
        <button class="btn btn-brand" type="submit">दाम सेव कीजिए</button>
      </div>

      <p class="help" style="margin-top:10px">
        जो याद न हो उसे <b>खाली छोड़ दीजिए</b> — ज़रूरी नहीं है कि सब भरें।
        एक सामान के दो दाम आने पर Maakit बीच वाला लेता है, इसलिए एक-आध ग़लती से कुछ नहीं बिगड़ता।
      </p>
    </form>
  <?php endif; ?>

</div>
</section>

<style>
.drow{display:flex;align-items:center;gap:10px;background:var(--surface);
  border:1.5px solid var(--line);border-radius:13px;padding:11px 14px;margin-bottom:8px;flex-wrap:wrap}
.dnm{flex:1;min-width:150px;line-height:1.35}
.dnm b{display:block;font-size:15.5px}
.dold{display:block;font-size:12.5px;color:var(--ok);font-weight:600;margin-top:2px}
.dbr{max-width:150px;font-size:14px;padding:7px 9px}
.dpr{display:flex;align-items:center;gap:5px;background:var(--soft);border-radius:10px;padding:4px 10px}
.dpr span{font-weight:700;color:var(--muted)}
.dpr input{width:82px;border:0;background:transparent;font:inherit;font-size:17px;font-weight:700;padding:6px 0}
.dpr input:focus{outline:none}
@media(max-width:520px){.dnm{min-width:100%}.dbr{flex:1}}
</style>
<script>
(function(){
  var f = document.querySelector('form'); if (!f) return;
  var jod = document.getElementById('jod'), milan = document.getElementById('milan');
  var bill = <?= (int)($o['goods_amount'] ?: 0) ?>;
  function gino(){
    var t = 0;
    f.querySelectorAll('.dpr input').forEach(function(i){ t += (parseInt(i.value,10) || 0); });
    jod.textContent = t;
    if (milan && bill) {
      var farq = t - bill;
      milan.textContent = (t === 0) ? ('बिल ₹' + bill + ' से मिलाइए')
        : (Math.abs(farq) <= 5 ? '✓ बिल से मिल गया'
          : (farq > 0 ? ('बिल से ₹' + farq + ' ज़्यादा') : ('बिल से ₹' + (-farq) + ' कम')));
      milan.style.color = (t && Math.abs(farq) <= 5) ? 'var(--ok)' : 'var(--muted)';
    }
  }
  f.addEventListener('input', gino);
  gino();
})();
</script>
<?php include __DIR__ . '/../inc/foot.php'; ?>
