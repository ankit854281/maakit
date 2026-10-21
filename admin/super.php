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
require_once __DIR__ . '/../inc/ek-kaam.php';
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

// ---------- 4. सारे दरवाज़े — देखने के लिए नहीं, भेजने के लिए ----------
//
// Ankit ko ye link DHOONDHNE ke liye nahi chahiye, DENE ke liye
// chahiye -- sarathi ko uska, dukaandaar ko uska. Pehle wo har
// baar address bar se uthata tha aur copy-paste karte-karte thak
// jata tha.
//
// Isliye har link par do button hain: "copy" aur "WhatsApp par
// bhejiye". Sandesh pehle se likha hua hai aur har aadmi ke liye
// alag hai -- sarathi ko "apna kaam yahan dekhiye", dukaandaar ko
// "apne daam yahan daaliye". Usse sirf naam chunna hai.
//
// Pata poora likha jata hai (https://maakit.in/...), adha nahi --
// adha pata WhatsApp me link banta hi nahi.
$ghar = 'https://maakit.in';

// $sandesh khali ho to sirf copy ka button aata hai (apne panne
// kisi ko bhejne ke liye nahi hote).
$darwaze = [
    t('For you', 'आपके लिए') => [
        ['/admin/super.php',    t('Everything in one place', 'सब एक जगह'),      t('this page', 'यही पन्ना'), ''],
        ['/admin/dash.php',     t('Numbers & charts', 'आँकड़े और चार्ट'),        t('week-on-week', 'हफ़्ते की तुलना'), ''],
        ['/admin/staff.php',    t('Who did what', 'किसने क्या किया'),        t('counted, not self-reported', 'गिना हुआ, किसी के बताए बिना'), ''],
        ['/admin/summary.php',  t('Monthly accounts', 'महीने का हिसाब'),        '', ''],
        ['/admin/readiness.php',t('Launch readiness', 'Launch की तैयारी'),      t('what is still missing in setup', 'setup में क्या बाकी है'), ''],
        ['/delivery/',          t("Office's hand", 'दफ़्तर का हाथ'),            t("record an order in the rider's place", 'सारथी की जगह ऑर्डर दर्ज करना'), ''],
    ],
    t('Day-to-day', 'रोज़ का काम') => [
        ['/admin/areas.php',      t('Areas', 'इलाके'),                 t('a new town starts here', 'नया शहर यहीं से'), ''],
        ['/admin/rates.php',      t('Delivery rates', 'भाड़े का रेट'),   '', ''],
        ['/admin/businesses.php', t('Shops', 'दुकानें'),                '', ''],
        ['/admin/transport.php',  t('Vehicles', 'गाड़ियाँ'),             t('see the papers yourself', 'काग़ज़ ख़ुद देखिए'), ''],
        ['/admin/sarathi.php',    t('Riders', 'सारथी'),                 '', ''],
        ['/admin/users.php',      t('Team', 'टीम'),                     '', ''],
        ['/admin/daam.php',       t('Prices', 'दाम'),                   '', ''],
        ['/admin/packet.php',     t('Packs', 'पैकेट'),
         t('ready-made sets — one tap for the customer', 'बने हुए सेट — ग्राहक को एक टैप'), ''],
        ['/admin/support.php',    t('Complaints', 'शिकायत'),            '', ''],
        ['/admin/settings.php',   t('Settings', 'सेटिंग'),               '', ''],
    ],
    t('Send these to your people', 'ये अपने लोगों को भेजिए') => [
        ['/sarathi/', t('Sarathi (rider)', 'सारथी'), t('one door only', 'अब एक ही दरवाज़ा'),
            "नमस्ते 🙏\n\nआप *Maakit* के सारथी हैं। अपना काम यहाँ देखिए:\n\n{link}\n\n"
          . "अपने मोबाइल नंबर और कोड से घुसिए। हाज़िरी लगाइए, काम अपने आप आ जाएगा।\n\n"
          . "कुछ अटके तो फ़ोन कीजिए — " . MAAKIT_NUMBER_SHOW],

        ['/delivery/photo.php', t('Product photos', 'सामान की फ़ोटो'), t('the rider fills the catalogue', 'सारथी से सूची भरती है'),
            "जो सामान आपके हाथ में आए, उसकी एक फ़ोटो यहाँ लगा दीजिए:\n\n{link}\n\n"
          . "इससे सूची भरती जाएगी और ग्राहक को असली सामान दिखेगा।"],

        ['/shop.php', t('Shopkeeper', 'दुकानदार'), t('puts prices, sees orders', 'दाम डालता है, ऑर्डर देखता है'),
            "नमस्ते 🙏\n\nआपकी दुकान अब *Maakit* पर है। अपने दाम डालने और ऑर्डर देखने के लिए:\n\n{link}\n\n"
          . "ग्राहक आपको सीधे पैसे देगा — Maakit सिर्फ़ सामान पहुँचाता है, पैसा अपने पास नहीं रखता।\n\n"
          . "कोई बात हो तो — " . MAAKIT_NUMBER_SHOW],

        ['/bpo/', t('BPO staff', 'BPO स्टाफ़'), t('takes calls, enters orders', 'कॉल सुनकर ऑर्डर डालता है'),
            "यहाँ से ऑर्डर डालिए:\n\n{link}\n\nअपने username और password से घुसिए।"],

        ['/login.php', t('Everyone else', 'बाकी सब'), t('one login for all', 'सबका एक ही login'),
            "Maakit में घुसने का रास्ता:\n\n{link}"],

        // Niyam ka link har aadmi ke apne hisse par khulta hai --
        // dukaandaar ko dukaan wala, sarathi ko sarathi wala. Poora
        // panna bhejne par wo padhega hi nahi.
        ['/niyam.php?k=dukan', t("Terms — shopkeeper", 'नियम — दुकानदार'),
            t('send with the shop link', 'दुकान वाले link के साथ भेजिए'),
            "जुड़ने से पहले ये एक बार पढ़ लीजिए — दो मिनट का है:\n\n{link}\n\n"
          . "सबसे ज़रूरी बात: *सामान का पूरा पैसा सीधे आपका है।* Maakit उसमें से कुछ "
          . "नहीं काटता और उसे अपने पास रखता भी नहीं — हम सिर्फ़ पहुँचाने का चार्ज लेते हैं।"],

        ['/niyam.php?k=sarathi', t('Terms — rider', 'नियम — सारथी'),
            t('send with the rider link', 'सारथी वाले link के साथ भेजिए'),
            "काम शुरू करने से पहले ये एक बार पढ़ लीजिए:\n\n{link}\n\n"
          . "छोटी-छोटी बातें हैं — हाज़िरी अपनी मर्ज़ी से, कमाई का हिसाब, दोनों कोड, "
          . "और गाड़ी के काग़ज़।"],
    ],
    t('Send these to customers', 'ये ग्राहकों को भेजिए') => [
        ['/', t('Home', 'घर का पन्ना'), '',
            "*Maakit* — गाँव में घर तक सामान 🛵\n\n"
          . "राशन, दवाई, खाना — किसी भी दुकान का सामान मँगाइए। दुकान का ही दाम, "
          . "ऊपर सिर्फ़ पहुँचाने का चार्ज।\n\n{link}\n\nया फ़ोन कीजिए — " . MAAKIT_NUMBER_SHOW],

        ['/bazaar.php', t('Shops & goods', 'बाज़ार'), '',
            "गाँव की दुकानें और सामान यहाँ देखिए:\n\n{link}"],

        ['/order.php', t('Place an order', 'ऑर्डर'), '',
            "यहाँ से सामान मँगाइए:\n\n{link}\n\nया फ़ोन कीजिए — " . MAAKIT_NUMBER_SHOW],

        ['/track.php', t('Where is my order', 'कहाँ पहुँचा'), '',
            "अपना सामान कहाँ पहुँचा, यहाँ देखिए:\n\n{link}\n\nऑर्डर नंबर और मोबाइल डालिए।"],

        ['/ride.php', t('Book a ride', 'सवारी'), t('new, not tested yet', 'नया, अभी जाँचा नहीं'),
            "सवारी बुक कीजिए:\n\n{link}"],
    ],
];

include __DIR__ . '/../inc/panel.php';
?>
<style>
  .sp-h{margin:0 0 4px}
  .sp-lead{color:var(--mut,#6B5D55);margin:0 0 20px}
  .sp-sec{margin:26px 0 10px;font-size:20px}
  .sp-ek{display:block;text-decoration:none;color:inherit;margin:0 0 22px;
         background:#fff;border:1px solid var(--line,#E3D8C6);
         border-left:5px solid var(--gold,#E0A526);border-radius:14px;padding:16px 18px}
  .sp-ek-bada{border-left-color:var(--brand,#7A1F1F);background:#FDF6E3}
  .sp-ek-t{display:block;font-size:13px;font-weight:700;letter-spacing:.04em;
           text-transform:uppercase;color:var(--mut,#6B5D55);margin-bottom:6px}
  .sp-ek b{display:block;font-size:19px;line-height:1.45}
  .sp-ek small{display:block;margin-top:7px;color:var(--mut,#6B5D55);font-size:14.5px;line-height:1.6}
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
  .sp-link{display:flex;align-items:center;gap:10px;padding:10px 12px;
           background:#fff;border:1px solid var(--line,#E3D8C6);border-radius:11px;
           margin-bottom:7px;flex-wrap:wrap}
  .sp-link-a{flex:1;min-width:0;display:flex;align-items:baseline;gap:9px;
             flex-wrap:wrap;text-decoration:none;color:inherit}
  .sp-link-a b{font-weight:700}
  .sp-link-a small{color:var(--mut,#6B5D55);font-size:13px}
  .sp-link-a code{width:100%;font-size:12px;color:var(--mut,#6B5D55);
                  white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
  .sp-do{display:flex;gap:7px;flex:none}
  .sp-copy,.sp-wa{font:inherit;font-size:14px;font-weight:700;border-radius:9px;
                  padding:9px 13px;min-height:40px;cursor:pointer;border:0;
                  text-decoration:none;display:inline-flex;align-items:center;white-space:nowrap}
  .sp-copy{background:#EFEAE0;color:var(--ink,#241A16)}
  .sp-copy-ok{background:#E8F3EA;color:#1C5130}
  .sp-wa{background:#1F6B3A;color:#fff}
</style>

<section><div class="wrap">

  <h1 class="sp-h"><?= h(t('Everything in one place', 'सब एक जगह')) ?></h1>
  <p class="sp-lead"><?= h(t('Open this page first. Whatever needs you is at the top.',
     'सुबह यही पन्ना खोलिए। जो आपका इंतज़ार कर रहा है वो सबसे ऊपर है।')) ?></p>

  <?php
    // ---- आज का एक काम — सबसे ऊपर ----
    // Neeche "ruka hua kaam" ki poori soochi hai. Par soochi dekh
    // kar aadmi sabse AASAN wala uthata hai, sabse ZAROORI wala
    // nahi. Isliye upar sirf EK -- wahi jiske ruke rehne se baaki
    // sab ruka hua hai.
    $ekk = null;
    try { $ekk = ek_kaam($pdo); } catch (Throwable $e) { $ekk = null; }
  ?>
  <?php if ($ekk): ?>
    <a class="sp-ek <?= $ekk['kitna'] === 'bada' ? 'sp-ek-bada' : '' ?>"
       href="<?= h($ekk['kahan'] ?: '#') ?>"<?= strpos((string)$ekk['kahan'], 'http') === 0
       ? ' target="_blank" rel="noopener"' : '' ?>>
      <span class="sp-ek-t"><?= h(t('Today’s one task', 'आज का एक काम')) ?></span>
      <b><?= h($ekk['kya']) ?></b>
      <small><?= h($ekk['kyun']) ?></small>
    </a>
  <?php endif; ?>

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
  <p class="sp-lead"><?= h(t(
     'Tap the name to open it. Tap copy or WhatsApp to hand it to someone — the message is already written.',
     'नाम दबाइए तो पन्ना खुलेगा। किसी को देना हो तो "नक़ल" या "WhatsApp" दबाइए — सन्देश पहले से लिखा है।')) ?></p>

  <?php foreach ($darwaze as $grp => $rows): ?>
    <div class="sp-grp">
      <h3><?= h($grp) ?></h3>
      <?php foreach ($rows as [$url, $naam, $kyun, $sandesh]):
        $pura = $ghar . $url;
      ?>
        <div class="sp-link">
          <a class="sp-link-a" href="<?= h($url) ?>">
            <b><?= h($naam) ?></b>
            <?php if ($kyun): ?><small><?= h($kyun) ?></small><?php endif; ?>
            <code><?= h($pura) ?></code>
          </a>
          <div class="sp-do">
            <button type="button" class="sp-copy" data-link="<?= h($pura) ?>"
                    data-done="<?= h(t('Copied', 'नक़ल हो गई')) ?>"><?= h(t('Copy', 'नक़ल')) ?></button>
            <?php if ($sandesh): ?>
              <a class="sp-wa" target="_blank" rel="noopener"
                 href="https://wa.me/?text=<?= h(rawurlencode(str_replace('{link}', $pura, $sandesh))) ?>">WhatsApp</a>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endforeach; ?>

</div></section>

<script>
// नक़ल — एक दबाने में.
//
// navigator.clipboard sirf https par chalta hai. Gaon me koi
// purana phone http par khole to wo chup-chaap fail ho jata, aur
// Ankit ko lagta ki nakal ho gayi. Isliye na chale to purana
// tareeka, aur wo bhi na chale to link chun kar dikha dete hain
// taaki wo khud daba kar copy kar le. Chup-chaap kabhi nahi.
(function () {
  function batao(b, ok) {
    var tha = b.textContent;
    b.textContent = ok ? (b.dataset.done || 'OK') : '<?= h(t('Select and copy', 'चुनकर कॉपी कीजिए')) ?>';
    b.classList.toggle('sp-copy-ok', ok);
    setTimeout(function () { b.textContent = tha; b.classList.remove('sp-copy-ok'); }, 1800);
  }
  function purana(text) {
    var t = document.createElement('textarea');
    t.value = text; t.setAttribute('readonly', '');
    t.style.position = 'fixed'; t.style.top = '-1000px';
    document.body.appendChild(t); t.select();
    var ok = false;
    try { ok = document.execCommand('copy'); } catch (e) { ok = false; }
    document.body.removeChild(t);
    return ok;
  }
  document.addEventListener('click', function (e) {
    var b = e.target.closest('.sp-copy');
    if (!b) return;
    var link = b.dataset.link;
    if (navigator.clipboard && window.isSecureContext) {
      navigator.clipboard.writeText(link).then(function () { batao(b, true); },
                                               function () { batao(b, purana(link)); });
    } else {
      batao(b, purana(link));
    }
  });
})();
</script>
<?php include __DIR__ . '/../inc/foot.php'; ?>
