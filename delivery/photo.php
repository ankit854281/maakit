<?php
// ============================================================
// Delivery wale ke liye — SAAMAN KI PHOTO
//
// Ankit ke paas koi designer nahi hai, aur 165 saaman ki photo
// kheenchna bhi mumkin nahi — kyonki Maakit dukaan nahi hai,
// saaman uske paas rehta hi nahi.
//
// Lekin jo saaman grahak ne mangaya hai, wo delivery wale ke
// HAATH ME hota hai — har roz, har order me. Bas us waqt ek
// photo kheench li jaye, to soochi apne aap bharti jayegi, aur
// photo bhi asli hogi — wahi saaman, wahi dukaan.
//
// Isliye yahan sirf wahi saaman dikhta hai jiski photo nahi hai,
// aur jo abhi-abhi mangaya gaya hai wo sabse upar.
// ============================================================
require_once __DIR__ . '/../inc/fn.php';
require_once __DIR__ . '/../inc/items.php';

$u = need_role(['delivery', 'admin']);
$page_title = 'सामान की फ़ोटो — Maakit';

// ---------- photo aayi ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_ok()) {
    $id = (int)post('item_id');

    $it = $pdo->prepare("SELECT id, name, photo FROM items WHERE id=? AND active=1");
    $it->execute([$id]);
    $row = $it->fetch();

    if (!$row) {
        flash('यह सामान सूची में नहीं है।');
    } elseif (!empty($row['photo'])) {
        flash('इसकी फ़ोटो पहले से लग चुकी है।');
    } else {
        $ph = save_item_photo('photo', 'it', 700);
        if (!$ph) {
            flash('फ़ोटो नहीं लग पाई। दोबारा कोशिश कीजिए (साफ़ तस्वीर, 4 MB तक)।');
        } else {
            $pdo->prepare("UPDATE items SET photo=? WHERE id=?")->execute([$ph, $id]);
            flash('लग गई — ' . $row['name'] . '. शुक्रिया!');
        }
    }
    redirect('/delivery/photo.php');
}

// ---------- kaun sa saaman dikhana hai ----------
// Pehle wo jo pichhle 3 din me mangaya gaya (yani abhi haath me
// hone ki sabse zyada sambhavna), phir ★ wale, phir baaki.
$taaza = [];
try {
    $rows = $pdo->query(
        "SELECT DISTINCT p.item_id FROM item_prices p
          WHERE p.created_at >= NOW() - INTERVAL 3 DAY"
    )->fetchAll();
    $taaza = array_map(fn($r) => (int)$r['item_id'], $rows);
} catch (Throwable $e) { $taaza = []; }

$sab = array_values(array_filter(items_all($pdo), fn($i) => empty($i['photo'])));

usort($sab, function ($a, $b) use ($taaza) {
    $ta = in_array((int)$a['id'], $taaza, true) ? 0 : 1;
    $tb = in_array((int)$b['id'], $taaza, true) ? 0 : 1;
    if ($ta !== $tb) return $ta <=> $tb;
    $pa = (int)$b['popular'] <=> (int)$a['popular'];
    if ($pa !== 0) return $pa;
    return ((int)($a['sort_no'] ?? 0)) <=> ((int)($b['sort_no'] ?? 0));
});

$kul   = count($sab);
$dikha = array_slice($sab, 0, 40);
$ho_gaya = (int)($pdo->query("SELECT COUNT(*) c FROM items WHERE active=1 AND photo IS NOT NULL AND photo<>''")->fetch()['c'] ?? 0);

include __DIR__ . '/../inc/panel.php';
?>
<style>
.ph-lead{background:var(--surface);border:1.5px solid var(--line);border-radius:14px;padding:15px;margin-bottom:14px}
.ph-lead b{display:block;font-size:16px;margin-bottom:5px}
.ph-lead p{margin:0;font-size:14px;color:var(--muted);line-height:1.55}
.bar{height:9px;background:var(--soft);border-radius:5px;overflow:hidden;margin:11px 0 6px}
.bar i{display:block;height:100%;background:var(--brand);border-radius:5px}
.ph-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:10px}
.pit{background:var(--surface);border:1.5px solid var(--line);border-radius:14px;padding:12px;text-align:center}
.pit .ic{display:grid;place-items:center;height:54px;color:var(--brand);margin-bottom:7px}
.pit b{display:block;font-size:14px;line-height:1.25}
.pit i{font-style:normal;display:block;font-size:12px;color:var(--muted);margin-top:2px}
.pit .star{color:var(--gold);font-weight:800}
.pit label{
  display:block;margin-top:10px;background:var(--brand);color:#fff;border-radius:10px;
  padding:9px 10px;font-weight:700;font-size:13.5px;cursor:pointer;
}
.pit input[type=file]{display:none}
.tip{font-size:13.5px;color:var(--muted);margin:16px 0 0;line-height:1.6}
</style>

<section><div class="wrap" style="max-width:820px">

  <div class="ph-lead">
    <b>जो सामान आपके हाथ में है, उसकी एक फ़ोटो खींच दीजिए</b>
    <p>सिर्फ़ वही सामान यहाँ है जिसकी फ़ोटो अब तक नहीं लगी। ऊपर वो हैं जो
       पिछले तीन दिन में मँगाए गए — यानी शायद अभी आपके पास ही हों।
       दुकान पर, थैले में, कहीं भी — बस साफ़ दिखे।</p>
    <?php $kul_sab = $kul + $ho_gaya; $pc = $kul_sab ? round(($ho_gaya / $kul_sab) * 100) : 0; ?>
    <div class="bar"><i style="width:<?= (int)$pc ?>%"></i></div>
    <p><b style="display:inline"><?= (int)$ho_gaya ?></b> की फ़ोटो लग चुकी ·
       <?= (int)$kul ?> बाक़ी</p>
  </div>

  <?php if (!$dikha): ?>
    <div class="ph-lead" style="text-align:center">
      <b>सबकी फ़ोटो लग गई!</b>
      <p>अब कोई सामान बिना फ़ोटो के नहीं बचा। शुक्रिया।</p>
    </div>
  <?php else: ?>
    <div class="ph-grid">
      <?php foreach ($dikha as $i): $naya = in_array((int)$i['id'], $taaza, true); ?>
        <form class="pit" method="post" enctype="multipart/form-data">
          <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
          <input type="hidden" name="item_id" value="<?= (int)$i['id'] ?>">
          <span class="ic"><?= prod_icon($i['name'], $i['grp'] ?? '', 40) ?></span>
          <b><?= h($i['name']) ?> <?= (int)$i['popular'] === 1 ? '<span class="star">★</span>' : '' ?></b>
          <i><?= h($i['unit']) ?><?= $naya ? ' · अभी मँगाया गया' : '' ?></i>
          <label>
            फ़ोटो खींचिए
            <input type="file" accept="image/*" onchange="this.form.submit()">
          </label>
        </form>
      <?php endforeach; ?>
    </div>

    <?php if ($kul > count($dikha)): ?>
      <p class="tip">बाक़ी <?= (int)($kul - count($dikha)) ?> सामान अगली बार दिखेंगे —
         एक साथ सब नहीं, वरना थक जाएँगे।</p>
    <?php endif; ?>
  <?php endif; ?>

  <p class="tip">
    <b>कैसी फ़ोटो अच्छी रहती है:</b> पास से, सिर्फ़ वही एक चीज़, और पीछे कुछ सादा हो —
    दुकान का काउंटर, ज़मीन, या थैला। अँधेरे में मत खींचिए।<br>
    फ़ोटो अपने आप छोटी हो जाती है, इसलिए नेट कम लगता है।
  </p>

</div></section>
<?php include __DIR__ . '/../inc/foot.php'; ?>
