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
// Kaise khulti hai:
//   maakit.in/muneem.php?key=<CHAABI>          -> padhne layak (browser me)
//   maakit.in/muneem.php?key=<CHAABI>&f=json   -> bot ke liye
//
// Chaabi wahi hai jo maakit-update.php me hai. Dono ek hi file
// (config.php) se aati hain, taaki do jagah yaad na rakhna pade.
//
// Har somvaar subah GitHub se ye file apne aap khulti hai aur
// uska nateeja aapko khabar ban kar milta hai.
// ============================================================
require_once __DIR__ . '/config.php';
@require_once __DIR__ . '/inc/version.php';   // sirf version dikhane ke liye

// ---------- chaabi ----------
// MUNEEM_KEY config.php me daal dijiye. Jab tak na daalein, ye
// file kisi ko kuchh nahi dikhati.
$chaabi = defined('MUNEEM_KEY') ? MUNEEM_KEY : '';
$diya   = (string)($_GET['key'] ?? '');

if ($chaabi === '' || !hash_equals($chaabi, $diya)) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    exit("Muneem band hai.\n");
}

$json = (($_GET['f'] ?? '') === 'json');

// ---------- database ----------
try {
    $pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
                   DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    exit(json_encode(['theek' => false, 'galti' => 'database se baat nahi ho payi']));
}

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
?><!doctype html>
<html lang="hi"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>मुनीम — Maakit</title>
<style>
body{background:#FBF7EF;color:#241A14;font-family:system-ui,'Segoe UI',sans-serif;
     margin:0;padding:18px;line-height:1.6;font-size:16px}
.w{max-width:640px;margin:0 auto}
h1{font-size:23px;margin:0 0 4px;color:#7A1F1F}
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
