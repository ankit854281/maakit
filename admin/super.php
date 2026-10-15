<?php
// ============================================================
//  मालिक का एक पन्ना — सब कुछ यहीं से
//
//  Ankit ke paas 80 panne hain. Wo 80 nahi kholega — ek kholega.
//  Ye wahi ek hai.
//
//  TEEN SAWAALON KA JAWAB, USI KRAM ME:
//
//    1. "abhi mera intezaar kaun kar raha hai?"  -> RUKA HUA KAAM
//    2. "aaj kaisa gaya?"                        -> AAJ KA HISAAB
//    3. "wo panna kahan tha?"                    -> SAARE DARWAZE
//
//  JO YAHAN JAAN-BOOJH KAR NAHI HAI:
//
//  - dash.php ke chart aur hafte ki tulna. Wo wahin theek hain;
//    yahan unka link hai. Dobara likhne ka matlab hota do jagah
//    sudharna -- aur wahi galti abhi-abhi theek ki gayi hai.
//  - readiness.php ki ilaaka-dar-ilaaka jaanch. Wo setup ki kami
//    batati hai; ye panna AAJ ke ruke hue kaam ka hai. Dono alag
//    sawaal hain.
//
//  SMART SARATHI KA DATABASE YAHAN SE NAHI PADHA JAATA.
//
//  Ye jaan-boojh kar hai. Sarathi apna alag dhandha hai; Maakit
//  uski ek graahak company hai, bas. Agar ye panna Sarathi ke
//  database me haath daale, to dono dobara ulajh jayenge -- aur
//  jis din Sarathi doosre server par jayega, ya Maakit kisi aur
//  delivery company se kaam karayega, us din ye panna toot jayega.
//
//  Isliye yahan sirf itna dekha jaata hai ki Sarathi ki config
//  FILE bani hai ya nahi (file hai ya nahi -- uske andar kya hai,
//  usse koi matlab nahi), aur uska link de diya jaata hai. Aage
//  kabhi Sarathi ke aankde yahan chahiye, to sahi raasta uski API
//  hai -- seedhe database me jhaankna nahi.
// ============================================================
require_once __DIR__ . '/../inc/fn.php';
need_role('admin');
$page_title = t('Everything in one place — Maakit', 'सब एक जगह — Maakit');

// ---------- 1. अभी क्या रुका है ----------
//
// Har line ka ek hi matlab: "itne kaam aapka intezaar kar rahe
// hain, aur unka darwaza ye raha". Jiski ginti shunya hai wo
// dikhti hi nahi -- khali line bhi utni hi jagah khaati hai aur
// dhyaan bhatkati hai.
$one = function (string $sql) use ($pdo) {
    try { return (int)$pdo->query($sql)->fetchColumn(); }
    catch (Throwable $e) { error_log('super.php: ' . $e->getMessage()); return 0; }
};

$ruke = [];
$add = function ($n, $kya, $link, $kyun = '') use (&$ruke) {
    if ((int)$n > 0) $ruke[] = ['n' => (int)$n, 'kya' => $kya, 'link' => $link, 'kyun' => $kyun];
};

$add($one("SELECT COUNT(*) FROM orders WHERE status='Naya'"),
     t('new orders nobody has touched', 'नए ऑर्डर, अभी कोई हाथ नहीं लगा'),
     '/bpo/', t('Price has to be asked from the shop', 'दुकान से दाम पूछना है'));

$add($one("SELECT COUNT(*) FROM orders WHERE status='Confirm' AND delivery_user IS NULL"),
     t('confirmed orders with no rider', 'पक्के ऑर्डर, सारथी नहीं मिला'),
     '/admin/sarathi.php', t('Nobody is on duty, or nobody is linked to that area', 'कोई हाज़िर नहीं, या उस इलाके में कोई जुड़ा नहीं'));

$add($one("SELECT COUNT(*) FROM orders WHERE status='Pickup' AND picked_at < NOW() - INTERVAL 3 HOUR"),
     t('picked up over 3 hours ago, still not delivered', 'तीन घंटे से उठा हुआ, पहुँचा नहीं'),
     '/delivery/', t('Call the rider', 'सारथी को फ़ोन कीजिए'));

$add($one("SELECT COUNT(*) FROM sarathi_samasya WHERE nipta=0"),
     t('problems riders reported from the road', 'रास्ते की दिक्कतें जो सारथी ने बताईं'),
     '/admin/sarathi.php');

$add($one("SELECT COUNT(*) FROM businesses WHERE status='pending'"),
     t('shops waiting for your approval', 'दुकानें जो आपकी मंज़ूरी का इंतज़ार कर रही हैं'),
     '/admin/businesses.php');

$add($one("SELECT COUNT(*) FROM transports WHERE verified=0 AND status='approved'"),
     t('vehicles whose papers you have not seen yet', 'गाड़ियाँ जिनके काग़ज़ आपने अभी देखे नहीं'),
     '/admin/transport.php', t('Look at them yourself before ticking verified', 'अपनी आँख से देखकर ही verified कीजिए'));

$add($one("SELECT COUNT(*) FROM mk_partner_applications WHERE status='PENDING'"),
     t('people who want to join', 'लोग जो जुड़ना चाहते हैं'),
     '/public/partner-dashboard.php');

$add($one("SELECT COUNT(*) FROM support_tickets WHERE status IN ('new','reviewing')"),
     t('complaints waiting for an answer', 'शिकायतें जो जवाब की राह देख रही हैं'),
     '/admin/support.php');

$add($one("SELECT COUNT(*) FROM shop_items i JOIN businesses b ON b.id=i.business_id
            WHERE b.status='approved' AND i.active=1 AND (i.price IS NULL OR i.price<=0)"),
     t('items with no price — a customer cannot order these', 'बिना दाम का सामान — ग्राहक इसे मँगा ही नहीं सकता'),
     '/admin/daam.php');

// ---------- 2. आज का हिसाब ----------
$aaj = ['aaye' => 0, 'pahunche' => 0, 'raaste' => 0, 'kamai' => 0, 'hazir' => 0];
try {
    $r = $pdo->query("SELECT
            COALESCE(SUM(DATE(created_at)=CURDATE()),0) aaye,
            COALESCE(SUM(status='Delivered' AND DATE(delivered_at)=CURDATE()),0) pahunche,
            COALESCE(SUM(status IN ('Assign','Pickup')),0) raaste,
            COALESCE(SUM(CASE WHEN status='Delivered' AND DATE(delivered_at)=CURDATE()
                         THEN delivery_charge ELSE 0 END),0) kamai
          FROM orders")->fetch();
    if ($r) $aaj = array_merge($aaj, array_map('intval', $r));
    $aaj['hazir'] = (int)$pdo->query(
        "SELECT COUNT(*) FROM driver_availability d JOIN users u ON u.id=d.user_id
          WHERE u.active=1 AND d.available_until > UTC_TIMESTAMP()")->fetchColumn();
} catch (Throwable $e) { error_log('super.php aaj: ' . $e->getMessage()); }

// ---------- 3. Smart Sarathi — sirf link, database nahi ----------
// Upar likhi wajah se: yahan sirf ye dekha jaata hai ki uski
// config file bani hai ya nahi. Uske andar jhaanka nahi jaata.
$sarathi_taiyar = is_file(__DIR__ . '/../smartsarathi/inc/config.php');

// ---------- 4. सारे दरवाज़े ----------
$darwaze = [
    t('For you', 'आपके लिए') => [
        ['/admin/super.php',    t('Everything in one place', 'सब एक जगह'),      t('this page', 'यही पन्ना')],
        ['/admin/dash.php',     t('Numbers & charts', 'आँकड़े और चार्ट'),        t('week-on-week', 'हफ़्ते की तुलना')],
        ['/admin/summary.php',  t('Monthly accounts', 'महीने का हिसाब'),        ''],
        ['/admin/readiness.php',t('Launch readiness', 'Launch की तैयारी'),      t('what is still missing in setup', 'setup में क्या बाकी है')],
        ['/delivery/',          t("Office's hand", 'दफ़्तर का हाथ'),            t("record an order in the rider's place", 'सारथी की जगह ऑर्डर दर्ज करना')],
    ],
    t('Day-to-day', 'रोज़ का काम') => [
        ['/admin/areas.php',      t('Areas', 'इलाके'),                 t('a new town starts here', 'नया शहर यहीं से')],
        ['/admin/rates.php',      t('Delivery rates', 'भाड़े का रेट'),   ''],
        ['/admin/businesses.php', t('Shops', 'दुकानें'),                ''],
        ['/admin/transport.php',  t('Vehicles', 'गाड़ियाँ'),             t('see the papers yourself', 'काग़ज़ ख़ुद देखिए')],
        ['/admin/sarathi.php',    t('Riders', 'सारथी'),                 ''],
        ['/admin/users.php',      t('Team', 'टीम'),                     ''],
        ['/admin/daam.php',       t('Prices', 'दाम'),                   ''],
        ['/admin/support.php',    t('Complaints', 'शिकायत'),            ''],
        ['/admin/settings.php',   t('Settings', 'सेटिंग'),               ''],
    ],
    t('Your people log in here', 'आपके लोग यहाँ घुसते हैं') => [
        ['/bpo/',               t('BPO staff', 'BPO स्टाफ़'),          t('takes calls, enters orders', 'कॉल सुनकर ऑर्डर डालता है')],
        ['/sarathi/',           t('Sarathi (rider)', 'सारथी'),        t('one door only', 'अब एक ही दरवाज़ा')],
        ['/delivery/photo.php', t('Product photos', 'सामान की फ़ोटो'),  t('the rider fills the catalogue', 'सारथी से सूची भरती है')],
        ['/shop.php',           t('Shopkeeper', 'दुकानदार'),           t('puts prices, sees orders', 'दाम डालता है, ऑर्डर देखता है')],
        ['/login.php',          t('Everyone else', 'बाकी सब'),         t('one login for all', 'सबका एक ही login')],
    ],
    t('What the customer sees', 'ग्राहक क्या देखता है') => [
        ['/',            t('Home', 'घर का पन्ना'),      ''],
        ['/bazaar.php',  t('Shops & goods', 'बाज़ार'),   ''],
        ['/order.php',   t('Place an order', 'ऑर्डर'),  ''],
        ['/track.php',   t('Where is my order', 'कहाँ पहुँचा'), ''],
        ['/ride.php',    t('Book a ride', 'सवारी'),     t('new, not tested yet', 'नया, अभी जाँचा नहीं')],
    ],
];

include __DIR__ . '/../inc/panel.php';
?>
<style>
  .sp-h{margin:0 0 4px}
  .sp-lead{color:var(--mut,#6B5D55);margin:0 0 20px}
  .sp-sec{margin:26px 0 10px;font-size:20px}
  .sp-ok{background:#E8F3EA;border:1px solid #BFDCC6;color:#1C5130;
         border-radius:14px;padding:18px;font-weight:700}
  .sp-ruk{display:flex;align-items:center;gap:14px;background:#fff;
          border:1px solid var(--line,#E3D8C6);border-left:4px solid var(--gold,#E0A526);
          border-radius:13px;padding:13px 15px;margin-bottom:9px;
          text-decoration:none;color:inherit}
  .sp-ruk b{font-size:27px;line-height:1;color:var(--brand,#7A1F1F);
            min-width:48px;text-align:center;flex:none}
  .sp-ruk span{display:flex;flex-direction:column;min-width:0}
  .sp-ruk small{color:var(--mut,#6B5D55);font-size:13.5px;margin-top:2px}
  .sp-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(135px,1fr));gap:10px}
  .sp-num{background:#fff;border:1px solid var(--line,#E3D8C6);
          border-radius:13px;padding:14px;text-align:center}
  .sp-num b{display:block;font-size:30px;line-height:1.1;color:var(--brand,#7A1F1F)}
  .sp-num small{color:var(--mut,#6B5D55);font-size:13.5px}
  .sp-grp{margin-top:18px}
  .sp-grp h3{font-size:15px;color:var(--mut,#6B5D55);margin:0 0 8px;font-weight:700}
  .sp-link{display:flex;align-items:baseline;gap:9px;padding:11px 13px;
           background:#fff;border:1px solid var(--line,#E3D8C6);border-radius:11px;
           margin-bottom:7px;text-decoration:none;color:inherit}
  .sp-link b{font-weight:700}
  .sp-link small{color:var(--mut,#6B5D55);font-size:13px}
  .sp-link code{margin-left:auto;font-size:12.5px;color:var(--mut,#6B5D55);
                white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:42%}
  @media(max-width:430px){.sp-link code{display:none}}
</style>

<section><div class="wrap">

  <h1 class="sp-h"><?= h(t('Everything in one place', 'सब एक जगह')) ?></h1>
  <p class="sp-lead"><?= h(t('Open this page first. Whatever needs you is at the top.',
     'सुबह यही पन्ना खोलिए। जो आपका इंतज़ार कर रहा है वो सबसे ऊपर है।')) ?></p>

  <!-- ===== 1. अभी क्या रुका है ===== -->
  <h2 class="sp-sec"><?= h(t('Waiting for you', 'अभी क्या रुका है')) ?><?php
    if ($ruke): ?> <span class="tag tag-off"><?= count($ruke) ?></span><?php endif; ?></h2>

  <?php if (!$ruke): ?>
    <div class="sp-ok"><?= h(t('Nothing is stuck. Everything is moving.',
        'कुछ नहीं रुका है। सब चल रहा है।')) ?></div>
  <?php else: foreach ($ruke as $r): ?>
    <a class="sp-ruk" href="<?= h($r['link']) ?>">
      <b><?= $r['n'] ?></b>
      <span><?= h($r['kya']) ?><?php if ($r['kyun']): ?><small><?= h($r['kyun']) ?></small><?php endif; ?></span>
    </a>
  <?php endforeach; endif; ?>

  <!-- ===== 2. आज का हिसाब ===== -->
  <h2 class="sp-sec"><?= h(t("Today", 'आज का हिसाब')) ?></h2>
  <div class="sp-grid">
    <div class="sp-num"><b><?= $aaj['aaye'] ?></b><small><?= h(t('came in', 'ऑर्डर आए')) ?></small></div>
    <div class="sp-num"><b><?= $aaj['pahunche'] ?></b><small><?= h(t('delivered', 'पहुँचे')) ?></small></div>
    <div class="sp-num"><b><?= $aaj['raaste'] ?></b><small><?= h(t('on the way', 'रास्ते में')) ?></small></div>
    <div class="sp-num"><b>₹<?= $aaj['kamai'] ?></b><small><?= h(t('delivery earned', 'डिलीवरी से')) ?></small></div>
    <div class="sp-num"><b><?= $aaj['hazir'] ?></b><small><?= h(t('riders on duty', 'सारथी हाज़िर')) ?></small></div>
  </div>
  <p style="margin-top:10px"><a class="btn btn-line" href="/admin/dash.php"><?=
     h(t('Week-on-week and charts', 'हफ़्ते की तुलना और चार्ट')) ?></a></p>

  <!-- ===== 3. Smart Sarathi ===== -->
  <h2 class="sp-sec">Smart Sarathi</h2>
  <div class="box">
    <?php if ($sarathi_taiyar): ?>
      <b><?= h(t('Set up', 'तैयार है')) ?></b>
      <p class="help" style="margin:6px 0 10px"><?= h(t(
        'Its accounts live in its own panel, because it is a separate business. Maakit is just one of its client companies.',
        'उसका हिसाब उसके अपने पन्ने में है, क्योंकि वो अलग धंधा है। Maakit उसकी एक ग्राहक कंपनी भर है।')) ?></p>
      <a class="btn btn-brand" href="/smartsarathi/admin/"><?= h(t("Open Sarathi's panel", 'सारथी का पन्ना खोलिए')) ?></a>
    <?php else: ?>
      <b><?= h(t('Not set up yet', 'अभी चालू नहीं हुआ')) ?></b>
      <p class="help" style="margin:6px 0 10px"><?= h(t(
        'Fill the one-time setup form once, and it will build its own tables.',
        'एक बार तैयारी का फ़ॉर्म भर दीजिए, वो अपनी table ख़ुद बना लेगा।')) ?></p>
      <a class="btn btn-brand" href="/smartsarathi/setup.php"><?= h(t('Finish setup', 'तैयारी पूरी कीजिए')) ?></a>
    <?php endif; ?>
  </div>

  <!-- ===== 4. सारे दरवाज़े ===== -->
  <h2 class="sp-sec"><?= h(t('Every door', 'सारे दरवाज़े')) ?></h2>
  <?php foreach ($darwaze as $grp => $rows): ?>
    <div class="sp-grp">
      <h3><?= h($grp) ?></h3>
      <?php foreach ($rows as [$url, $naam, $kyun]): ?>
        <a class="sp-link" href="<?= h($url) ?>">
          <b><?= h($naam) ?></b>
          <?php if ($kyun): ?><small><?= h($kyun) ?></small><?php endif; ?>
          <code><?= h($url) ?></code>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endforeach; ?>

</div></section>
<?php include __DIR__ . '/../inc/foot.php'; ?>
