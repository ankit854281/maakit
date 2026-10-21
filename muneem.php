<?php
// ============================================================
// Maakit ka MUNEEM
//
// Muneem bahi kholta hai aur batata hai ki kya dhyan maang raha
// hai. Wo faisla nahi karta — sirf ginti rakhta hai aur saamne
// rakh deta hai. Faisla Ankit ka.
//
// Ye file kuchh BADALTI NAHI hai — sirf padhti hai.
//
// Bahi do tarah se khulti hai:
//
//   1. Aap Admin me login hain  -> Admin ke menu me "मुनीम" dabaiye.
//      Koi chaabi nahi chahiye. Yahi aasaan raasta hai.
//
//   2. Chaabi se (sirf bot ke liye, jo login nahi kar sakta):
//        maakit.in/muneem.php?key=<CHAABI>&f=json
//      Chaabi config.php ki MUNEEM_KEY hai. Na ho to ye raasta
//      band rehta hai — aur Admin wala raasta phir bhi chalta hai.
//
// Yani GitHub par chaabi daalna ZAROORI NAHI. Wo sirf isliye hai
// ki har somvaar bahi apne aap aa jaye, bina aapke khole.
// ============================================================
require_once __DIR__ . '/inc/fn.php';        // session, $pdo aur user() yahin se
require_once __DIR__ . '/inc/ek-kaam.php';   // आज का एक काम

// ---------- kaun dekh sakta hai ----------
$u      = user();
$admin  = $u && ($u['role'] ?? '') === 'admin';

$chaabi = defined('MUNEEM_KEY') ? (string)MUNEEM_KEY : '';
$diya   = (string)($_GET['key'] ?? '');
$key_ok = $chaabi !== '' && $diya !== '' && hash_equals($chaabi, $diya);

if (!$admin && !$key_ok) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    exit("Muneem band hai. Admin me login kijiye.\n");
}

$json = (($_GET['f'] ?? '') === 'json');

/** chhota helper — galti aaye to kaam ruke nahi, bas khaali laut aaye */
function ek(PDO $p, $sql, $args = [], $d = 0) {
    try { $s = $p->prepare($sql); $s->execute($args); $r = $s->fetch();
          return $r ? (array_values($r)[0] ?? $d) : $d; }
    catch (Throwable $e) { return $d; }
}
function sab(PDO $p, $sql, $args = []) {
    try { $s = $p->prepare($sql); $s->execute($args); return $s->fetchAll(); }
    catch (Throwable $e) { return []; }
}

$B = [];   // muneem ki bahi

// ============================================================
// 1. Is hafte ka hisaab, aur pichhle hafte se tulna
// ============================================================
$ab   = "created_at >= NOW() - INTERVAL 7 DAY";
$tab  = "created_at >= NOW() - INTERVAL 14 DAY AND created_at < NOW() - INTERVAL 7 DAY";

$B['hafta'] = [
    'order'        => (int)ek($pdo, "SELECT COUNT(*) FROM orders WHERE $ab AND status<>'Cancel'"),
    'order_pichhle'=> (int)ek($pdo, "SELECT COUNT(*) FROM orders WHERE $tab AND status<>'Cancel'"),
    'booking'      => (int)ek($pdo, "SELECT COUNT(*) FROM service_bookings WHERE $ab"),
    'booking_pichhle' => (int)ek($pdo, "SELECT COUNT(*) FROM service_bookings WHERE $tab"),
    'naye_grahak'  => (int)ek($pdo, "SELECT COUNT(DISTINCT mobile) FROM orders WHERE $ab"),
    'delivery_paisa'=> (int)ek($pdo, "SELECT COALESCE(SUM(delivery_charge),0) FROM orders WHERE $ab AND status='Delivered'"),
    'radd'         => (int)ek($pdo, "SELECT COUNT(*) FROM orders WHERE $ab AND status='Cancel'"),
];

// ============================================================
// 0. आज का एक काम
//
// Naya panna nahi banaya gaya. Muneem ka darwaza aur chaabi
// pehle se hai, aur roz wala bot yahin se padh lega -- ek aur
// panna matlab ek aur jagah jo toot sakti hai.
// ============================================================
try { $B['ek_kaam'] = ek_kaam($pdo); }
catch (Throwable $e) { error_log('muneem ek_kaam: ' . $e->getMessage()); $B['ek_kaam'] = null; }

// ============================================================
// 0ख. घंटी — जो अभी रुका हुआ है
//
// Ye muneem ki tarah hafte ka hisab nahi hai -- ye "abhi" ki baat
// hai, aur 15 minute wala bot yahin se padhta hai. Panna yahi
// rakha gaya kyunki darwaza aur chaabi pehle se hai.
//
// Saath me: kis sarathi ka paisa baaki hai. Per order kamane wala
// aadmi roz ka kamane wala hota hai -- uska paisa rokne ka matlab
// hai use kho dena.
// ============================================================
require_once __DIR__ . '/inc/ghanti.php';
try { $B['ghanti'] = ghanti_dekho($pdo); }
catch (Throwable $e) { error_log('muneem ghanti: ' . $e->getMessage()); $B['ghanti'] = []; }
try { $B['saathi_baaki'] = saathi_baaki($pdo); }
catch (Throwable $e) { error_log('muneem saathi: ' . $e->getMessage()); $B['saathi_baaki'] = []; }

// ============================================================
// 1क. मुनाफ़ा या नुक़सान — वो एक नंबर जो सबसे ज़्यादा मायने रखता है
//
// Muneem ab tak sirf ye batata tha ki kitna AAYA. Kitna GAYA, ye
// kabhi nahi batata tha -- aur dhandha "kitna aaya" se nahi,
// "kitna bacha" se chalta hai.
//
// Kharche ka hisaab pehle se maujood tha (operating_costs --
// petrol, staff, baaki), par wo sirf admin/summary.php par baithi
// thi, jahan Ankit khud kholne jaata. Muneem use chhuta hi nahi
// tha.
//
// Do baatein jaanbujh kar aisi hain:
//
// 1. SAAMAAN KA PAISA YAHAN GINA HI NAHI JATA. Wo graahak se
//    seedha dukaan ko jaata hai; Maakit use chhuta tak nahi.
//    Use kamai me jodna apne aap ko bada dikhane wala jhooth
//    hota -- aur usi jhooth par Ankit galat faisla le leta.
//    Maakit ki kamai sirf pahunchane ka charge hai.
//
// 2. JIN DINON KA KHARCHA DARJ HI NAHI HUA, WO ALAG GINE JAATE
//    HAIN. Bina kharche ke din munafa apne aap bada dikhta hai.
//    Isliye saaf likha jata hai ki kitne din ka hisaab adhoora
//    hai -- warna muneem jhootha bharosa de dega.
// ============================================================
$kh_ab = "cost_date >= CURDATE() - INTERVAL 7 DAY";

$aaya  = (int)$B['hafta']['delivery_paisa'];
$gaya  = (int)ek($pdo, "SELECT COALESCE(SUM(fuel + staff + other),0) FROM operating_costs WHERE $kh_ab");
$din_darj = (int)ek($pdo, "SELECT COUNT(DISTINCT cost_date) FROM operating_costs WHERE $kh_ab");

$B['paisa'] = [
    'aaya'        => $aaya,            // sirf delivery charge -- saaman ka paisa NAHI
    'gaya'        => $gaya,            // petrol + staff + baaki
    'bacha'       => $aaya - $gaya,    // munafa (ya nuksan, agar minus me ho)
    'din_darj'    => $din_darj,        // 7 me se kitne din ka kharcha likha gaya
    'din_adhoore' => max(0, 7 - $din_darj),
    'petrol'      => (int)ek($pdo, "SELECT COALESCE(SUM(fuel),0)  FROM operating_costs WHERE $kh_ab"),
    'staff'       => (int)ek($pdo, "SELECT COALESCE(SUM(staff),0) FROM operating_costs WHERE $kh_ab"),
    'baaki'       => (int)ek($pdo, "SELECT COALESCE(SUM(other),0) FROM operating_costs WHERE $kh_ab"),
    // Ek delivery par kitna bacha -- yahi wo number hai jisse pata
    // chalta hai ki dhandha badhane layak hai ya nahi.
    'per_order'   => $B['hafta']['order'] > 0
                     ? (int)round(($aaya - $gaya) / $B['hafta']['order']) : 0,
];

// ============================================================
// 2. Logon ne kya dhoondha aur NAHI mila  (sabse keemti)
// ============================================================
$B['nahi_mila'] = sab($pdo,
    "SELECT q, times FROM search_log
      WHERE hits = 0 AND last_at >= NOW() - INTERVAL 30 DAY
      ORDER BY times DESC, last_at DESC LIMIT 15");

// ============================================================
// 3. Jo saaman bina photo ke pada hai (★ wale pehle)
// ============================================================
$B['bina_photo'] = [
    'star' => (int)ek($pdo, "SELECT COUNT(*) FROM items WHERE active=1 AND popular=1 AND (photo IS NULL OR photo='')"),
    'kul'  => (int)ek($pdo, "SELECT COUNT(*) FROM items WHERE active=1 AND (photo IS NULL OR photo='')"),
    'naam' => array_column(sab($pdo,
        "SELECT DISTINCT name FROM items WHERE active=1 AND popular=1 AND (photo IS NULL OR photo='')
          ORDER BY name LIMIT 10"), 'name'),
];

// ============================================================
// 4. Jinka daam abhi tak pata nahi
// ============================================================
$B['bina_daam'] = [
    'kul'  => (int)ek($pdo,
        "SELECT COUNT(*) FROM items i WHERE i.active=1
           AND NOT EXISTS (SELECT 1 FROM item_daam d WHERE d.item_id=i.id)"),
    'naam' => array_column(sab($pdo,
        "SELECT DISTINCT i.name FROM items i WHERE i.active=1 AND i.popular=1
           AND NOT EXISTS (SELECT 1 FROM item_daam d WHERE d.item_id=i.id)
         ORDER BY i.name LIMIT 10"), 'name'),
];

// ============================================================
// 5. Jo saaman kabhi bika hi nahi (60 din se list me hai)
// ============================================================
$B['kabhi_nahi_bika'] = (int)ek($pdo,
    "SELECT COUNT(*) FROM items i WHERE i.active=1
       AND NOT EXISTS (SELECT 1 FROM item_prices p WHERE p.item_id=i.id)");

// ============================================================
// 6. Sabse zyada bikne wala — ye upar rakhne layak hai
// ============================================================
$B['sabse_zyada'] = sab($pdo,
    "SELECT i.name, COUNT(*) baar
       FROM item_prices p JOIN items i ON i.id=p.item_id
      WHERE p.created_at >= NOW() - INTERVAL 30 DAY
      GROUP BY i.id ORDER BY baar DESC LIMIT 8");

// ============================================================
// 7. Jo dukaan ya gaadi mahino se chup hai
// ============================================================
$B['chup'] = [
    'dukaan' => (int)ek($pdo,
        "SELECT COUNT(*) FROM businesses WHERE status='approved'
           AND (salon_updated IS NULL OR salon_updated < NOW() - INTERVAL 30 DAY)"),
    'gaadi'  => (int)ek($pdo,
        "SELECT COUNT(*) FROM transports WHERE status<>'hidden'
           AND (avail_updated IS NULL OR avail_updated < NOW() - INTERVAL 30 DAY)"),
];

// ============================================================
// 8. Jo mez par pada hai aur intezaar kar raha hai
// ============================================================
$B['dekhna_baaki'] = [
    'nayi_booking'  => (int)ek($pdo, "SELECT COUNT(*) FROM service_bookings WHERE status='Naya'"),
    'nayi_dukaan'   => (int)ek($pdo, "SELECT COUNT(*) FROM businesses WHERE status='pending'"),
    'naye_gaon'     => (int)ek($pdo, "SELECT COUNT(*) FROM area_requests WHERE status='new'"),
    'nayi_raay'     => (int)ek($pdo, "SELECT COUNT(*) FROM feedback WHERE status='pending'"),
    'gaadi_kagaz'   => (int)ek($pdo, "SELECT COUNT(*) FROM transports WHERE verified=0 AND status<>'hidden'"),
];

// ============================================================
// 9. Kaun se gaon se kaam aa raha hai
// ============================================================
$B['gaon'] = sab($pdo,
    "SELECT village, COUNT(*) n FROM orders
      WHERE $ab AND status<>'Cancel' AND village<>''
      GROUP BY village ORDER BY n DESC LIMIT 8");

// ============================================================
$B['kab']     = date('Y-m-d H:i');
$B['version'] = defined('MAAKIT_VERSION') ? MAAKIT_VERSION : '?';
$B['theek']   = true;

// ------------------------------------------------------------
if ($json) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($B, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// ------------------------------------------------------------ padhne layak
$f = fn($n) => number_format((int)$n);
$teer = function ($ab, $pehle) {
    if ($pehle == 0) return $ab > 0 ? ' <b style="color:#2F6B45">नया</b>' : '';
    $p = round((($ab - $pehle) / $pehle) * 100);
    if ($p > 4)  return ' <b style="color:#2F6B45">▲ ' . $p . '%</b>';
    if ($p < -4) return ' <b style="color:#A33427">▼ ' . abs($p) . '%</b>';
    return ' <span style="color:#6C5B4D">बराबर</span>';
};
// ------------------------------------------------------------ admin ke andar, naye roop me
if ($admin) {
    $page_title = t('Ledger (Muneem)', 'मुनीम की बही') . ' — Maakit';
    include __DIR__ . '/inc/panel.php';
    $fmt = fn($n) => number_format((int)$n);
    $chg = function ($ab, $pehle) {
        if (!$pehle) return $ab ? ['up', t('new', 'नया')] : ['flat', '—'];
        $p = round(($ab - $pehle) * 100 / $pehle);
        return [$p > 0 ? 'up' : ($p < 0 ? 'down' : 'flat'), ($p > 0 ? '+' : '') . $p . '%'];
    };
    [$c1, $t1] = $chg($B['hafta']['order'], $B['hafta']['order_pichhle']);
    [$c2, $t2] = $chg($B['hafta']['booking'], $B['hafta']['booking_pichhle']);
    $dk = $B['dekhna_baaki'];
    ?>
<section class="dz">
  <div class="dz-head">
    <div><h1><?= t('Ledger (Muneem)', 'मुनीम की बही') ?></h1>
      <p><?= t('Weekly summary · read-only, nothing is changed here', 'हफ़्ते का हिसाब · सिर्फ़ गिनती, यहाँ कुछ नहीं बदलता') ?> · <?= h($B['kab']) ?> · v<?= h($B['version']) ?></p></div>
    <div class="dz-range"><a class="dz-btn" href="/admin/dash.php"><?= t('Open dashboard', 'डैशबोर्ड खोलिए') ?></a><a class="dz-btn" href="?f=json"><?= t('Download JSON', 'JSON उतारिए') ?></a></div>
  </div>

  <div class="dz-kpis">
    <div class="dz-kpi"><div class="dz-k-top"><span class="dz-ic g"><?= adm_icon('box') ?></span><?= t('Orders · 7 days', 'ऑर्डर · 7 दिन') ?><em class="<?= $c1 ?>"><?= $t1 ?></em></div>
      <b><?= $fmt($B['hafta']['order']) ?></b><small><?= t('previous week', 'पिछला हफ़्ता') ?> <?= $fmt($B['hafta']['order_pichhle']) ?><?= $B['hafta']['radd'] ? ' · ' . $fmt($B['hafta']['radd']) . ' ' . t('cancelled', 'रद्द') : '' ?></small><div style="height:12px"></div></div>
    <div class="dz-kpi"><div class="dz-k-top"><span class="dz-ic b"><?= adm_icon('calendar') ?></span><?= t('Bookings · 7 days', 'बुकिंग · 7 दिन') ?><em class="<?= $c2 ?>"><?= $t2 ?></em></div>
      <b><?= $fmt($B['hafta']['booking']) ?></b><small><?= t('previous week', 'पिछला हफ़्ता') ?> <?= $fmt($B['hafta']['booking_pichhle']) ?></small><div style="height:12px"></div></div>
    <div class="dz-kpi"><div class="dz-k-top"><span class="dz-ic p"><?= adm_icon('user') ?></span><?= t('Unique customers', 'अलग-अलग ग्राहक') ?></div>
      <b><?= $fmt($B['hafta']['naye_grahak']) ?></b><small><?= t('distinct phone numbers this week', 'इस हफ़्ते अलग-अलग नंबर') ?></small><div style="height:12px"></div></div>
    <div class="dz-kpi"><div class="dz-k-top"><span class="dz-ic y"><?= adm_icon('rupee') ?></span><?= t('Delivery income · 7 days', 'डिलीवरी कमाई · 7 दिन') ?></div>
      <b>₹<?= $fmt($B['hafta']['delivery_paisa']) ?></b><small><a href="/admin/summary.php"><?= t('Full accounts', 'पूरा हिसाब') ?> →</a></small><div style="height:12px"></div></div>
  </div>

  <div class="dz-row dz-r-21">
    <div class="dz-card">
      <div class="dz-card-h"><b><?= t('Needs attention', 'ध्यान माँग रहा है') ?></b></div>
      <ul class="dz-todo">
        <?php $kuchh = false; ?>
        <?php if ($dk['nayi_booking']): $kuchh = true; ?><li class="warn"><b><?= $fmt($dk['nayi_booking']) ?></b> <?= t('new bookings nobody has looked at yet', 'नई बुकिंग — अभी तक किसी ने नहीं देखी') ?> <a href="/bpo/bookings.php">→</a></li><?php endif; ?>
        <?php if ($dk['gaadi_kagaz']): $kuchh = true; ?><li><b><?= $fmt($dk['gaadi_kagaz']) ?></b> <?= t('vehicle papers to check yourself', 'गाड़ी के काग़ज़ ख़ुद जाँचने बाक़ी') ?> <a href="/admin/transport.php?v=pending">→</a></li><?php endif; ?>
        <?php if ($dk['nayi_dukaan']): $kuchh = true; ?><li><b><?= $fmt($dk['nayi_dukaan']) ?></b> <?= t('new shops awaiting approval', 'नई दुकान मंज़ूरी के इंतज़ार में') ?> <a href="/admin/businesses.php">→</a></li><?php endif; ?>
        <?php if ($dk['naye_gaon']): $kuchh = true; ?><li><b><?= $fmt($dk['naye_gaon']) ?></b> <?= t('villages asking for service', 'गाँव से आने की माँग') ?> <a href="/admin/areas.php">→</a></li><?php endif; ?>
        <?php if ($dk['nayi_raay']): $kuchh = true; ?><li><b><?= $fmt($dk['nayi_raay']) ?></b> <?= t('reviews awaiting approval', 'राय मंज़ूरी के इंतज़ार में') ?> <a href="/admin/feedback.php">→</a></li><?php endif; ?>
        <?php if ($B['bina_photo']['star']): $kuchh = true; ?><li><b><?= $fmt($B['bina_photo']['star']) ?></b> <?= t('★ products without a photo', '★ सामान बिना फ़ोटो के') ?><?= $B['bina_photo']['naam'] ? ' <small class="dz-mut">(' . h(implode(', ', array_slice($B['bina_photo']['naam'], 0, 3))) . '…)</small>' : '' ?> <a href="/admin/product-photos.php?f=missing">→</a></li><?php endif; ?>
        <?php if ($B['bina_daam']['kul']): $kuchh = true; ?><li><b><?= $fmt($B['bina_daam']['kul']) ?></b> <?= t('products with no price recorded yet', 'सामान का दाम अब तक दर्ज नहीं') ?> <a href="/admin/daam.php">→</a></li><?php endif; ?>
        <?php if ($B['chup']['gaadi']): $kuchh = true; ?><li><b><?= $fmt($B['chup']['gaadi']) ?></b> <?= t('vehicle owners silent for a month — give them a call', 'गाड़ी वाले महीने भर से चुप — एक बार फ़ोन कीजिए') ?> <a href="/admin/transport.php">→</a></li><?php endif; ?>
        <?php if ($B['chup']['dukaan']): $kuchh = true; ?><li><b><?= $fmt($B['chup']['dukaan']) ?></b> <?= t('shops silent for a month', 'दुकानें महीने भर से चुप') ?> <a href="/admin/businesses.php">→</a></li><?php endif; ?>
        <?php if (!$kuchh): ?><li class="good"><?= t('Nothing is stuck.', 'कुछ भी अटका हुआ नहीं है।') ?></li><?php endif; ?>
      </ul>
    </div>
    <div class="dz-card">
      <div class="dz-card-h"><b><?= t('Searched but not found', 'खोजा, पर नहीं मिला') ?></b><a href="/admin/items.php#naya"><?= t('Add products', 'सामान जोड़िए') ?> →</a></div>
      <?php if ($B['nahi_mila']): ?>
        <div class="dz-tags miss"><?php foreach ($B['nahi_mila'] as $r): ?><span><?= h($r['q']) ?> <b><?= (int)$r['times'] ?>×</b></span><?php endforeach; ?></div>
        <p class="dz-mut" style="font-size:12px;margin:6px 0 0"><?= t('The most useful list — people from your own villages who left empty-handed.', 'यही सबसे काम की सूची है — आपके गाँव के लोग जो ख़ाली हाथ लौटे।') ?></p>
      <?php else: ?><p class="dz-empty"><?= t('Nothing missed this week', 'इस हफ़्ते कुछ नहीं छूटा') ?></p><?php endif; ?>
    </div>
  </div>

  <div class="dz-row dz-r-3">
    <div class="dz-card">
      <div class="dz-card-h"><b><?= t('Best sellers this month', 'महीने में सबसे ज़्यादा बिका') ?></b></div>
      <?php if ($B['sabse_zyada']): $mx = max(array_map(fn($r) => (int)$r['baar'], $B['sabse_zyada'])) ?: 1; ?>
        <div class="dz-bars"><?php foreach ($B['sabse_zyada'] as $r): ?><div><span><?= h($r['name']) ?></span><i><em style="width:<?= max(4, round($r['baar'] * 100 / $mx)) ?>%"></em></i><b><?= (int)$r['baar'] ?></b></div><?php endforeach; ?></div>
      <?php else: ?><p class="dz-empty"><?= t('No sales yet', 'अभी कुछ नहीं बिका') ?></p><?php endif; ?>
    </div>
    <div class="dz-card">
      <div class="dz-card-h"><b><?= t('Work by village', 'किस गाँव से काम आया') ?></b></div>
      <?php if ($B['gaon']): $mx = max(array_map(fn($r) => (int)$r['n'], $B['gaon'])) ?: 1; ?>
        <div class="dz-bars"><?php foreach ($B['gaon'] as $r): ?><div><span><?= h($r['village']) ?></span><i><em style="width:<?= max(4, round($r['n'] * 100 / $mx)) ?>%"></em></i><b><?= (int)$r['n'] ?></b></div><?php endforeach; ?></div>
      <?php else: ?><p class="dz-empty"><?= t('No data yet', 'अभी आँकड़े नहीं') ?></p><?php endif; ?>
    </div>
    <div class="dz-card">
      <div class="dz-card-h"><b><?= t('Catalogue health', 'सामान की हालत') ?></b></div>
      <div class="dz-mini3" style="grid-template-columns:repeat(2,minmax(0,1fr))">
        <a href="/admin/daam.php"><b><?= $fmt($B['bina_daam']['kul']) ?></b><?= t('without price', 'बिना दाम') ?></a>
        <a href="/admin/product-photos.php?f=missing"><b><?= $fmt($B['bina_photo']['star']) ?></b><?= t('★ without photo', '★ बिना फ़ोटो') ?></a>
        <a href="/admin/items.php"><b><?= $fmt($B['kabhi_nahi_bika']) ?></b><?= t('never sold', 'कभी नहीं बिका') ?></a>
        <a href="/admin/businesses.php"><b><?= $fmt($B['chup']['dukaan']) ?></b><?= t('quiet shops', 'चुप दुकानें') ?></a>
      </div>
      <p class="dz-mut" style="font-size:12px;margin:10px 0 0"><?= t('The ledger only counts — decisions are yours. You can send this page to Claude to plan next steps.', 'मुनीम सिर्फ़ गिनती रखता है — फ़ैसला आपका। इसे Claude को भेजकर आगे का रास्ता सोचा जा सकता है।') ?></p>
    </div>
  </div>
</section>
<?php
    include __DIR__ . '/inc/foot.php';
    exit;
}
?><!doctype html>
<html lang="hi"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>मुनीम — Maakit</title>
<style>
body{background:#FBF7EF;color:#241A14;font-family:system-ui,'Segoe UI',sans-serif;
     margin:0;padding:18px;line-height:1.6;font-size:16px}
.w{max-width:640px;margin:0 auto}
h1{font-size:23px;margin:0 0 4px;color:#2563EB}
.kab{color:#6C5B4D;font-size:13.5px;margin:0 0 20px}
h2{font-size:16px;margin:26px 0 9px;letter-spacing:.04em}
.c{background:#fff;border:1px solid #E7DECD;border-radius:13px;padding:15px;margin-bottom:11px}
.n{display:flex;flex-wrap:wrap;gap:10px}
.n div{flex:1 1 140px;background:#fff;border:1px solid #E7DECD;border-radius:12px;padding:12px 13px}
.n b{display:block;font-size:25px;font-weight:800;line-height:1.15}
.n b b,.n b span{font-size:12.5px;font-weight:700;white-space:nowrap}
.n i{font-style:normal;font-size:12.5px;color:#6C5B4D;display:block;margin-top:3px}
ul{margin:0;padding-left:20px}li{margin-bottom:5px}
.q{font-weight:700}
.tag{font-size:11.5px;background:#F4EDE0;border-radius:6px;padding:2px 7px;color:#6C5B4D;margin-left:5px}
.ok{color:#2F6B45;font-weight:700}
.x{color:#A33427;font-weight:700}
.note{color:#6C5B4D;font-size:13.5px;margin-top:7px}
</style></head><body><div class="w">

<h1>मुनीम की बही</h1>
<p class="kab"><?= htmlspecialchars($B['kab']) ?> · Maakit v<?= htmlspecialchars($B['version']) ?></p>

<?php if (!empty($B['ek_kaam'])): $EK = $B['ek_kaam']; ?>
<h2>आज का एक काम</h2>
<div class="n" style="display:block">
  <p style="font-size:17px;font-weight:700;margin:0 0 6px"><?= htmlspecialchars($EK['kya']) ?></p>
  <p class="note" style="margin:0"><?= htmlspecialchars($EK['kyun']) ?></p>
</div>
<?php endif; ?>

<h2>पिछले 7 दिन</h2>
<div class="n">
  <div><b><?= $f($B['hafta']['order']) ?><?= $teer($B['hafta']['order'], $B['hafta']['order_pichhle']) ?></b><i>ऑर्डर</i></div>
  <div><b><?= $f($B['hafta']['booking']) ?><?= $teer($B['hafta']['booking'], $B['hafta']['booking_pichhle']) ?></b><i>बुकिंग</i></div>
  <div><b><?= $f($B['hafta']['naye_grahak']) ?></b><i>अलग-अलग नंबर</i></div>
  <div><b>₹<?= $f($B['hafta']['delivery_paisa']) ?></b><i>डिलीवरी से</i></div>
</div>
<?php if ($B['hafta']['radd'] > 0): ?>
  <p class="note"><?= $f($B['hafta']['radd']) ?> ऑर्डर रद्द हुए।</p>
<?php endif; ?>

<?php
// ---- मुनाफ़ा या नुक़सान ----
// Ye wo ek nambar hai jiske liye baaki sab ginti hoti hai. Isliye
// ise ginti ke theek baad, sabse upar rakha gaya hai.
$P = $B['paisa'];
?>
<h2>मुनाफ़ा या नुक़सान</h2>
<div class="n">
  <div><b>₹<?= $f($P['aaya']) ?></b><i>आया (डिलीवरी से)</i></div>
  <div><b>₹<?= $f($P['gaya']) ?></b><i>गया (पेट्रोल, स्टाफ़, बाकी)</i></div>
  <div><b style="color:<?= $P['bacha'] >= 0 ? '#1F6B3A' : '#A33427' ?>">₹<?= $f(abs($P['bacha'])) ?></b><i><?=
      $P['bacha'] >= 0 ? 'बचा' : 'घाटा' ?></i></div>
  <?php if ($B['hafta']['order'] > 0): ?>
    <?php // "₹-15" padhne me ajeeb lagta hai. Nishan alag, rakam alag. ?>
    <div><b style="color:<?= $P['per_order'] >= 0 ? '#1F6B3A' : '#A33427' ?>">₹<?= $f(abs($P['per_order'])) ?></b><i>हर ऑर्डर पर <?=
        $P['per_order'] >= 0 ? 'बचा' : 'घाटा' ?></i></div>
  <?php endif; ?>
</div>

<?php if ($P['gaya'] > 0): ?>
  <p class="note">पेट्रोल ₹<?= $f($P['petrol']) ?> · स्टाफ़ ₹<?= $f($P['staff']) ?> · बाकी ₹<?= $f($P['baaki']) ?></p>
<?php endif; ?>

<?php if ($P['din_adhoore'] > 0): ?>
  <p class="note"><b>ध्यान दीजिए:</b> 7 में से <?= (int)$P['din_adhoore'] ?> दिन का ख़र्चा दर्ज ही नहीं हुआ।
    <?php if ($P['din_darj'] === 0): ?>
      यानी ऊपर वाला "बचा" <b>सच नहीं है</b> — ख़र्चा गिना ही नहीं गया।
    <?php else: ?>
      यानी असली बचत इससे कम होगी।
    <?php endif; ?>
    <a href="/admin/summary.php">ख़र्चा यहाँ भरिए</a>।</p>
<?php endif; ?>

<p class="note">सामान का पैसा इसमें नहीं गिना गया — वो ग्राहक सीधे दुकान को देता है,
  Maakit उसे छूता तक नहीं। यहाँ सिर्फ़ Maakit की अपनी कमाई है।</p>

<?php if ($B['nahi_mila']): ?>
<h2>लोगों ने खोजा, पर नहीं मिला</h2>
<div class="c">
  <ul>
    <?php foreach ($B['nahi_mila'] as $r): ?>
      <li><span class="q"><?= htmlspecialchars($r['q']) ?></span><span class="tag"><?= (int)$r['times'] ?> बार</span></li>
    <?php endforeach; ?>
  </ul>
  <p class="note">यही सबसे काम की सूची है — ये आपके अपने गाँव के लोग हैं जो ख़ाली हाथ लौटे।</p>
</div>
<?php endif; ?>

<h2>ध्यान माँग रहा है</h2>
<div class="c"><ul>
  <?php $kuchh = false; ?>
  <?php if ($B['dekhna_baaki']['nayi_booking']): $kuchh=true; ?>
    <li><span class="x"><?= $f($B['dekhna_baaki']['nayi_booking']) ?> नई बुकिंग</span> — अभी तक किसी ने नहीं देखी</li><?php endif; ?>
  <?php if ($B['dekhna_baaki']['gaadi_kagaz']): $kuchh=true; ?>
    <li><?= $f($B['dekhna_baaki']['gaadi_kagaz']) ?> गाड़ी के काग़ज़ जाँचने बाक़ी <span class="tag">ख़ुद आँख से देखिए</span></li><?php endif; ?>
  <?php if ($B['dekhna_baaki']['nayi_dukaan']): $kuchh=true; ?>
    <li><?= $f($B['dekhna_baaki']['nayi_dukaan']) ?> नई दुकान मंज़ूरी के इंतज़ार में</li><?php endif; ?>
  <?php if ($B['dekhna_baaki']['naye_gaon']): $kuchh=true; ?>
    <li><?= $f($B['dekhna_baaki']['naye_gaon']) ?> गाँव से आने की माँग</li><?php endif; ?>
  <?php if ($B['dekhna_baaki']['nayi_raay']): $kuchh=true; ?>
    <li><?= $f($B['dekhna_baaki']['nayi_raay']) ?> राय मंज़ूरी के इंतज़ार में</li><?php endif; ?>
  <?php if ($B['bina_photo']['star']): $kuchh=true; ?>
    <li><?= $f($B['bina_photo']['star']) ?> ★ सामान बिना फ़ोटो के
      <?php if ($B['bina_photo']['naam']): ?>
        <span class="tag"><?= htmlspecialchars(implode(', ', array_slice($B['bina_photo']['naam'], 0, 4))) ?>…</span>
      <?php endif; ?></li><?php endif; ?>
  <?php if ($B['bina_daam']['kul']): $kuchh=true; ?>
    <li><?= $f($B['bina_daam']['kul']) ?> सामान का दाम अब तक दर्ज नहीं</li><?php endif; ?>
  <?php if ($B['chup']['gaadi']): $kuchh=true; ?>
    <li><?= $f($B['chup']['gaadi']) ?> गाड़ी वाले महीने भर से चुप — एक बार फ़ोन कीजिए</li><?php endif; ?>
  <?php if ($B['chup']['dukaan']): $kuchh=true; ?>
    <li><?= $f($B['chup']['dukaan']) ?> दुकानें महीने भर से चुप</li><?php endif; ?>
  <?php if (!$kuchh): ?><li class="ok">कुछ भी अटका हुआ नहीं है।</li><?php endif; ?>
</ul></div>

<?php if ($B['sabse_zyada']): ?>
<h2>महीने में सबसे ज़्यादा बिका</h2>
<div class="c"><ul>
  <?php foreach ($B['sabse_zyada'] as $r): ?>
    <li><?= htmlspecialchars($r['name']) ?><span class="tag"><?= (int)$r['baar'] ?> बार</span></li>
  <?php endforeach; ?>
</ul></div>
<?php endif; ?>

<?php if ($B['gaon']): ?>
<h2>किस गाँव से काम आया</h2>
<div class="c"><ul>
  <?php foreach ($B['gaon'] as $r): ?>
    <li><?= htmlspecialchars($r['village']) ?><span class="tag"><?= (int)$r['n'] ?></span></li>
  <?php endforeach; ?>
</ul></div>
<?php endif; ?>

<p class="note" style="margin-top:24px">
  मुनीम सिर्फ़ गिनती रखता है — कुछ बदलता नहीं। क्या करना है, वह फ़ैसला आपका।<br>
  इस बही को Claude पर भेज दीजिए, वहाँ इस पर सोच कर रास्ते निकाले जा सकते हैं।
</p>

</div></body></html>
