<?php
// ============================================================
//  tab: मेरी दुकान — एक नज़र में आज
//
//  Dukandar subah ise kholega. Isliye sirf wahi cheezein jo
//  usse abhi karni hain: khula/band, naye order, aaj ka paisa.
// ============================================================
$khuli = dukan_khuli($b);

$naye = $pdo->prepare("SELECT COUNT(*) c FROM orders WHERE business_id=? AND shop_status='naya'");
$naye->execute([$bid]);
$naye = (int)$naye->fetch()['c'];

$j      = dukan_jod($pdo, $bid, $aaj, $aaj);
$lena   = dukan_lena_hai($pdo, $bid);
$kulItems = $pdo->prepare("SELECT COUNT(*) c, COALESCE(SUM(stock='khatam'),0) k FROM shop_items WHERE business_id=?");
$kulItems->execute([$bid]);
$ki = $kulItems->fetch();
?>
<section><div class="wrap" style="max-width:820px">

  <div style="display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap;align-items:center">
    <h2 style="margin:0"><?= h($b['name']) ?></h2>
    <span class="tag <?= $khuli ? 'tag-live' : 'tag-off' ?>"><?= $khuli ? 'अभी खुली है' : 'अभी बंद है' ?></span>
  </div>

  <!-- खुली / बंद — सबसे ऊपर, क्योंकि यही रोज़ दबाना है -->
  <div class="box" style="margin-top:12px">
    <label>दुकान की हालत</label>
    <div style="display:flex;gap:8px;margin-top:8px">
      <form method="post" style="flex:1">
        <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
        <input type="hidden" name="do" value="khuli"><input type="hidden" name="open" value="1">
        <button class="btn <?= (int)$b['shop_open'] ? 'btn-green' : '' ?>"
                style="width:100%<?= (int)$b['shop_open'] ? '' : ';background:#EFEAE0' ?>">खुली है</button>
      </form>
      <form method="post" style="flex:1">
        <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
        <input type="hidden" name="do" value="khuli"><input type="hidden" name="open" value="0">
        <button class="btn <?= (int)$b['shop_open'] ? '' : 'btn-brand' ?>"
                style="width:100%<?= (int)$b['shop_open'] ? ';background:#EFEAE0' : '' ?>">बंद है</button>
      </form>
    </div>
    <p class="help" style="margin-top:8px">बंद करने पर आपका सामान ग्राहक को नहीं दिखेगा —
      ऑर्डर आकर बेकार नहीं जाएगा। खोलना याद रखिए।</p>
  </div>

  <?php if ($naye): ?>
    <a class="box" href="/shop.php?tab=order" style="display:block;margin-top:12px;border-color:var(--gold);text-decoration:none;color:inherit">
      <div style="display:flex;align-items:center;gap:12px">
        <div style="font-size:34px;font-weight:800;color:var(--brand)"><?= $naye ?></div>
        <div><b>नया ऑर्डर आया है</b><div class="meta">देखकर “मंज़ूर” दबा दीजिए →</div></div>
      </div>
    </a>
  <?php endif; ?>

  <!-- आज का पैसा -->
  <div class="grid g2" style="margin-top:12px">
    <div class="box">
      <div class="meta">आज की बिक्री</div>
      <div class="big" style="color:var(--brand)">₹<?= (int)$j['bikri'] ?></div>
      <div class="meta"><?= (int)$j['ginti'] ?> बार · नगद ₹<?= (int)$j['nagad'] ?> · UPI ₹<?= (int)$j['upi'] ?></div>
    </div>
    <div class="box">
      <div class="meta">Maakit से लेना है</div>
      <div class="big" style="color:<?= $lena > 0 ? 'var(--brand)' : 'var(--muted)' ?>">₹<?= $lena ?></div>
      <div class="meta"><?= $lena > 0 ? 'नगद वाले ऑर्डर का हिसाब' : 'कुछ बाकी नहीं' ?></div>
    </div>
  </div>

  <!-- सामान की हालत -->
  <div class="box" style="margin-top:12px">
    <?php if (!(int)$ki['c']): ?>
      <b>अभी आपका कोई सामान नहीं चढ़ा है</b>
      <p class="help" style="margin-top:6px">ग्राहक को आपका दाम तब तक नहीं दिखेगा।
        Maakit की सूची से टिक करके सिर्फ़ दाम भर दीजिए — नाम टाइप नहीं करना पड़ेगा।</p>
      <a class="btn btn-brand" href="/shop.php?tab=saaman" style="margin-top:10px">सामान जोड़िए</a>
    <?php else: ?>
      <div style="display:flex;gap:12px;flex-wrap:wrap;align-items:center">
        <div style="flex:1;min-width:150px">
          <b><?= (int)$ki['c'] ?> सामान चढ़ा है</b>
          <?php if ((int)$ki['k']): ?>
            <div class="meta" style="color:var(--brand)"><?= (int)$ki['k'] ?> पर “ख़त्म” लगा है</div>
          <?php else: ?>
            <div class="meta">सब उपलब्ध है</div>
          <?php endif; ?>
        </div>
        <a class="btn btn-sm" style="background:#EFEAE0" href="/shop.php?tab=saaman">देखिए / बदलिए</a>
      </div>
    <?php endif; ?>
  </div>

  <?php if (!$b['upi_id']): ?>
    <div class="box" style="margin-top:12px;border-color:var(--gold)">
      <b>अपना UPI डाल दीजिए</b>
      <p class="help" style="margin-top:6px">तब ग्राहक को <b>आपका ही</b> QR दिखेगा और पैसा सीधा
        आपके खाते में आएगा। Maakit बीच में पैसा नहीं रखता।</p>
      <a class="btn btn-brand btn-sm" href="/shop.php?tab=khata" style="margin-top:8px">UPI डालिए</a>
    </div>
  <?php endif; ?>

</div></section>
