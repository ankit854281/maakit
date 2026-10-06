<?php
require_once __DIR__ . '/inc/fn.php';
require_once __DIR__ . '/inc/salon.php';
require_once __DIR__ . '/inc/items.php';
require_once __DIR__ . '/inc/icons.php';
require_once __DIR__ . '/inc/services.php';
require_once __DIR__ . '/inc/customer.php';
require_once __DIR__ . '/inc/books.php';

$page_title = t('Maakit — delivery & booking for your village | Kapsethi, Chauri, Kachhwa',
                'Maakit — गाँव की अपनी डिलीवरी और बुकिंग सेवा | कपसेठी, चौरी, कछवा');
$tab = 'ghar';
$me  = cust();

$live = $pdo->query("SELECT *, (salon_updated IS NOT NULL AND salon_updated > (NOW() - INTERVAL 180 MINUTE)) AS fresh
                     FROM businesses WHERE status='approved' AND salon_on=1 AND mode<>'off'
                     ORDER BY salon_updated DESC LIMIT 4")->fetchAll();
$latest    = $pdo->query("SELECT * FROM businesses WHERE status='approved' ORDER BY id DESC LIMIT 6")->fetchAll();
$total_biz = (int)$pdo->query("SELECT COUNT(*) c FROM businesses WHERE status='approved'")->fetch()['c'];
$n_vill    = (int)$pdo->query("SELECT COUNT(*) c FROM villages WHERE live=1")->fetch()['c'];
$n_ord     = (int)$pdo->query("SELECT COUNT(*) c FROM orders WHERE status<>'Cancel'")->fetch()['c'];
$pop       = array_slice(array_values(array_filter(items_all($pdo), fn($i) => (int)$i['popular'] === 1)), 0, 6);

list($is_open, $open_short, $open_long) = open_now($pdo);

// purani kitaabein — home page par jhalak
try { $bks = books_live($pdo, [], 6); } catch (Throwable $e) { $bks = []; }

// grahakon ki raay (jo manzoor ho chuki)
try {
    $says = $pdo->query("SELECT f.name, f.village, f.rating, f.comment, b.name AS biz
                         FROM feedback f LEFT JOIN businesses b ON b.id=f.business_id
                         WHERE f.status='approved' AND f.comment IS NOT NULL AND f.comment<>''
                         ORDER BY f.id DESC LIMIT 6")->fetchAll();
    $avg = $pdo->query("SELECT ROUND(AVG(rating),1) a, COUNT(*) c FROM feedback WHERE status='approved'")->fetch();
} catch (Throwable $e) { $says = []; $avg = null; }

// abhi-abhi kya hua (sirf gaon aur samay — naam nahi)
try {
    $recent = $pdo->query("SELECT village, created_at, 'o' AS kind FROM orders
                           WHERE status<>'Cancel' AND created_at > NOW() - INTERVAL 3 DAY
                           UNION ALL
                           SELECT village, created_at, 'b' FROM service_bookings
                           WHERE status<>'Cancel' AND created_at > NOW() - INTERVAL 3 DAY
                           ORDER BY created_at DESC LIMIT 8")->fetchAll();
} catch (Throwable $e) { $recent = []; }

try {
    $banners = $pdo->query("SELECT * FROM banners WHERE active=1
        AND (starts IS NULL OR starts <= CURDATE()) AND (ends IS NULL OR ends >= CURDATE())
        ORDER BY sort_no, id LIMIT 6")->fetchAll();
} catch (Throwable $e) { $banners = []; }

// ---------- home page ki sewayein ----------
// ---------------------------------------------------------------
// Home page ki shreniyan.
//
// Char samooh, aur samooh "kaam kaise hota hai" se bane hain —
// "cheez kya hai" se nahi. Grahak ko pehli nazar me pata chal jata
// hai ki paisa kya lagega, kaun chalega, kitni der me hoga.
//
// Naam Roman lipi me hain aur neeche Hindi — yahi tarika Blinkit,
// Zepto, Urban Company, 1mg sab istemal karte hain. Naam me cheezein
// ginayi gayi hain ("Taxi, Auto & Bus"), sirf kaam ka naam nahi
// ("Rides") — taaki grahak ko dikhe ki andar kya milega.
//
// Nayi shreni jodni ho to bas yahan ek line jodiye.
// ---------------------------------------------------------------
$GROUPS = [
  [t('We bring it', 'हम लाते हैं'), [
    ['chaat',    'Samosa &amp; Momos',       t('chowmein, chaat, maggi', 'चाउमीन, चाट, मैगी'),  '/order.php#chaat'],
    ['grocery',  'Kirana &amp; Masala',      t('atta, oil, soap', 'आटा, तेल, साबुन'),          '/order.php'],
    ['food',     'Hotel &amp; Mithai',       t('thali, biryani, sweets', 'थाली, बिरयानी, मिठाई'), '/order.php#khana'],
    ['medicine', 'Medicines',                t('send the prescription', 'पर्ची भेजिए'),         '/order.php#dawa'],
    ['pooja',    'Puja &amp; Agarbatti',     t('diya, roli, coconut', 'दीया, रोली, नारियल'),    '/order.php#pooja'],
    ['khad',     'Khad, Beej &amp; Chara',   t('urea, seed, bran', 'यूरिया, बीज, चोकर'),        '/order.php#khad'],
  ]],
  [t('Book it in advance', 'पहले से बुक कीजिए'), [
    ['ride',     'Taxi, Auto &amp; Bus',     t('for people', 'सवारी के लिए'),                  '/sewa.php?s=safar'],
    ['truck',    'Tempo, Truck &amp; Trolley', t('goods, shifting', 'माल ढुलाई, शिफ़्टिंग'),    '/sewa.php?s=maal'],
    ['tent',     'Lawn, Tent &amp; Catering', t('wedding, tilak, bhandara', 'शादी, तिलक, भंडारा'), '/sewa.php?s=lawn'],
  ]],
  [t('They come to your home', 'कारीगर घर आएगा'), [
    ['tools',    'Electrician &amp; Plumber', t('mistri, painter, mason', 'मिस्त्री, पेंटर, राजगीर'), '/sewa.php?s=mistri'],
    ['mobile',   'Mobile &amp; TV Repair',    t('phone, fan, fridge, cooler', 'फ़ोन, पंखा, फ़्रिज'),  '/directory.php?cat=repair'],
  ]],
  [t('You go there', 'पता कीजिए'), [
    ['salon',    'Salon &amp; Parlour',      t('cutting, mehendi, facial', 'कटिंग, मेहंदी, फेशियल'), '/directory.php?cat=nai'],
    ['shops',    'Shops &amp; Workers',      t('who is near you', 'आपके पास कौन है'),          '/directory.php?cat=dukan'],
    ['books',    'Old Books',                t('buy, sell, exchange', 'बेचिए, लीजिए, बदलिए'),   '/books.php'],
  ]],
];

// "Samosa & Momos" wali patti ke liye — 20 minute me aane wali cheezein.
// ★ wale (popular) pehle, taaki momos aur chowmein jaise naam saamne rahein.
$chaat = array_values(array_filter(items_all($pdo), fn($i) => ($i['grp'] ?? '') === 'chaat'));
usort($chaat, fn($a, $b) => ((int)$b['popular'] <=> (int)$a['popular'])
                         ?: ((int)($a['sort_no'] ?? 0) <=> (int)($b['sort_no'] ?? 0)));
$chaat = array_slice($chaat, 0, 8);
include __DIR__ . '/inc/head.php';
?>

<!-- ============ hero ============ -->
<section class="hero2">
  <div class="wrap">
    <div class="hbar">
      <span class="loc"><?= svc_icon('box', 15) ?> <?= t('Kapsethi · Chauri · Kachhwa + ' . num($n_vill) . ' villages',
          'कपसेठी · चौरी · कछवा और ' . num($n_vill) . ' गाँव') ?></span>
      <span class="opn <?= $is_open ? 'yes' : 'no' ?>"><i></i> <?= h($open_short) ?></span>
    </div>
    <h1><?= $me
        ? t('Hello, ', 'नमस्ते, ') . h(mb_substr(explode(' ', trim($me['name']))[0], 0, 12)) . '.<br>' . t('What do you need?', 'आज क्या चाहिए?')
        : t('Your village.<br>Delivered.', 'गाँव की अपनी<br>डिलीवरी और बुकिंग।') ?></h1>
    <p class="sub"><?= t(
      'Groceries, hot food, medicine, a Bolero, a lawn, tent, halwai, pandit ji — all in one place. You pay the shop’s price, plus delivery.',
      'दुकान का सामान, गरम खाना, दवाई, गाड़ी, लॉन, टेंट, हलवाई, पंडित जी — सब एक जगह। सामान दुकान के दाम पर, आप सिर्फ़ डिलीवरी चार्ज दीजिए।') ?></p>

    <form class="hsearch" action="/order.php" method="get">
      <span class="ic"><?= svc_icon('search', 20) ?></span>
      <input type="text" name="q" id="q" placeholder="<?= h(t('What do you need? Atta, medicine, Bolero…', 'क्या चाहिए? आटा, दवाई, बोलेरो…')) ?>" aria-label="<?= h(t('Search', 'खोजिए')) ?>">
      <button class="go" type="submit"><?= t('Search', 'खोजिए') ?></button>
    </form>

    <?php
    // Ek nazar me daayra — ki yahan sirf kirana nahi, dawa bhi,
    // nashta bhi, mistri bhi, gaadi bhi. Har chip asli jagah par
    // le jaati hai, dikhane bhar ki nahi hai.
    $NAMUNE = [
      [t('Atta 5kg', 'आटा 5 किलो'),        '/order.php#anaj'],
      [t('Paracetamol', 'पैरासिटामोल'),    '/order.php#dawa'],
      [t('Samosa', 'समोसा'),               '/order.php#chaat'],
      [t('Mistri', 'मिस्त्री'),             '/directory.php?cat=bijli'],
      [t('Bolero', 'बोलेरो'),               '/sewa.php?s=safar'],
      [t('Salon seat', 'सैलून'),            '/directory.php?cat=nai'],
    ];
    ?>
    <div class="hnam">
      <span class="hnam-l"><?= t('Like —', 'जैसे —') ?></span>
      <?php foreach ($NAMUNE as list($lbl, $href)): ?>
        <a href="<?= h($href) ?>"><?= h($lbl) ?></a>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<div class="wrap">
  <div class="install" id="installBox">
    <span class="ic"><?= svc_icon('box', 30) ?></span>
    <span><b><?= t('Keep Maakit on your phone', 'Maakit को फ़ोन में रख लीजिए') ?></b>
      <i><?= t('Adds to your home screen — like an app, no Play Store', 'होम स्क्रीन पर आ जाएगा — ऐप की तरह, बिना Play Store') ?></i></span>
    <button class="btn btn-sm btn-brand" id="installYes"><?= t('Add', 'रख लीजिए') ?></button>
    <button class="btn btn-sm" id="installNo" style="background:transparent;color:var(--muted);padding:8px"><?= t('Not now', 'अभी नहीं') ?></button>
  </div>

  <!-- ============ 20 minute wala nashta ============ -->
  <?php if ($chaat): ?>
    <div class="secthead" style="margin-top:4px">
      <h2><?= t('Samosa &amp; Momos', 'समोसा और मोमोज़') ?></h2>
      <p><?= t('Hot, in about 20 minutes', 'गरम, क़रीब 20 मिनट में') ?></p>
      <a class="more" href="/order.php#chaat"><?= t('See all', 'सब देखिए') ?> <?= svc_icon('plus', 13) ?></a>
    </div>
    <div class="nrail">
      <?php foreach ($chaat as $it): ?>
        <a class="nr" href="/order.php#chaat">
          <span class="ph">
            <?php if (!empty($it['photo'])): ?>
              <img src="/uploads/<?= h($it['photo']) ?>" alt="" loading="lazy">
            <?php else: ?>
              <?= prod_icon($it['name'], $it['grp'] ?? '', 30) ?>
            <?php endif; ?>
          </span>
          <b><?= h($it['name']) ?></b>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <!-- ============ shreniyan — char samooh ============ -->
  <?php foreach ($GROUPS as list($gname, $rows)): ?>
    <div class="grphead"><?= h($gname) ?></div>
    <div class="tiles cat">
      <?php foreach ($rows as list($ic, $en, $hi, $href)): $tp = svc_photo($ic); ?>
        <a class="tile" href="<?= h($href) ?>">
          <span class="ic"><?= $tp
              ? '<img src="/uploads/' . h($tp) . '" alt="" loading="lazy">'
              : svc_icon($ic, 32) ?></span>
          <b><?= $en ?></b>
          <i><?= h($hi) ?></i>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endforeach; ?>

  <a class="allsvc" href="/directory.php">
    <?= svc_icon('all', 20) ?>
    <span><?= t('All services and shops', 'सभी सेवाएँ और दुकानें') ?></span>
    <?= svc_icon('plus', 15) ?>
  </a>

  <!-- ============ kaise kaam karta hai ============ -->
  <!-- Maakit ka tareeka aam nahi hai: koi stock nahi, kisi bhi
       dukaan se, sirf delivery ka paisa. Jo pehli baar aata hai
       usse ye samajh nahi aata — isliye teen kadam me saaf. -->
  <div class="kaise">
    <div class="secthead" style="margin-top:0">
      <h2><?= t('How it works', 'कैसे काम करता है') ?></h2>
      <p><?= t('Three steps, nothing else', 'तीन कदम, और कुछ नहीं') ?></p>
    </div>
    <ol class="steps">
      <li>
        <span class="n">1</span>
        <b><?= t('Tell us what you need', 'बताइए क्या चाहिए') ?></b>
        <i><?= t('Call, WhatsApp, or order here. Anything, from any shop — even if it is not on our list.',
                 'फ़ोन कीजिए, WhatsApp कीजिए, या यहीं ऑर्डर कर दीजिए। किसी भी दुकान से कुछ भी — जो लिस्ट में नहीं है वो भी।') ?></i>
      </li>
      <li>
        <span class="n">2</span>
        <b><?= t('We bring it', 'हम ले आते हैं') ?></b>
        <i><?= t('Our delivery boy picks it up from the shop and brings it to your door, with the shop’s bill.',
                 'हमारा डिलीवरी बॉय दुकान से उठाकर आपके घर तक पहुँचाता है — दुकान की पर्ची के साथ।') ?></i>
      </li>
      <li>
        <span class="n">3</span>
        <b><?= t('You pay only the delivery', 'आप सिर्फ़ डिलीवरी का पैसा दीजिए') ?></b>
        <i><?= t('The goods cost what the shop charges — not a rupee more. Our earning is the delivery charge.',
                 'सामान का दाम वही जो दुकान का है — एक रुपया ज़्यादा नहीं। हमारी कमाई डिलीवरी चार्ज है।') ?></i>
      </li>
    </ol>

    <!-- kyun Maakit — jo bade app nahi karte -->
    <div class="kyun">
      <div>
        <span><?= svc_icon('grocery', 20) ?></span>
        <b><?= t('We keep no stock', 'हम कोई स्टॉक नहीं रखते') ?></b>
        <i><?= t('Your things come from the shop you already trust. We only carry them.',
                 'आपका सामान उसी दुकान से आता है जिस पर आपका भरोसा है। हम सिर्फ़ पहुँचाते हैं।') ?></i>
      </div>
      <div>
        <span><?= svc_icon('rupee', 20) ?></span>
        <b><?= t('The shop’s money stays the shop’s', 'दुकान का पैसा दुकान का') ?></b>
        <i><?= t('Cash, or UPI straight to the shop’s own number. Whatever share Maakit takes is shown to the shopkeeper in his own ledger — nothing hidden.',
                 'नगद, या UPI सीधे दुकान के अपने नंबर पर। Maakit का जो भी हिस्सा होगा वह दुकानदार को उसकी बही में साफ़ दिखता है — छिपाकर कुछ नहीं।') ?></i>
      </div>
      <div>
        <span><?= svc_icon('truck', 20) ?></span>
        <b><?= t('The big apps don’t come here', 'बड़े ऐप यहाँ नहीं आते') ?></b>
        <i><?= t('Swiggy and Blinkit will not deliver to our villages. That is exactly why Maakit exists.',
                 'स्विगी और ब्लिंकिट हमारे गाँवों तक नहीं आते। Maakit इसीलिए है।') ?></i>
      </div>
    </div>
  </div>

  <!-- ============ dukandar ke liye ============ -->
  <a class="dknyota" href="/register-business.php">
    <span class="ic"><?= svc_icon('shops', 26) ?></span>
    <span class="tx">
      <b><?= t('Do you run a shop?', 'दुकान आपकी है?') ?></b>
      <i><?= t('Put your shop on Maakit — your items, your prices, your orders and your daily accounts, all on your phone. Free.',
               'अपनी दुकान Maakit पर रखिए — अपना सामान, अपना दाम, अपने ऑर्डर और अपना हिसाब, सब अपने फ़ोन में। मुफ़्त।') ?></i>
    </span>
    <span class="go"><?= t('Add your shop', 'दुकान जोड़िए') ?> <?= svc_icon('plus', 14) ?></span>
  </a>

  <!-- ============ abhi-abhi kya hua ============ -->
  <?php if (count($recent) >= 3): ?>
    <div class="proof" aria-live="off">
      <span class="dot"></span>
      <span id="proofTx"><?= h(t(vname(['name'=>$recent[0]['village']]) . ' · ' . ($recent[0]['kind'] === 'o' ? 'order' : 'booking') . ' ' . ago($recent[0]['created_at']),
          $recent[0]['village'] . ' · ' . ($recent[0]['kind'] === 'o' ? 'ऑर्डर' : 'बुकिंग') . ' ' . ago($recent[0]['created_at']))) ?></span>
    </div>
    <script>
    (function(){
      var L = <?= json_encode(array_map(fn($r) => [
          'v' => $r['village'],
          'k' => $r['kind'] === 'o' ? t('order', 'ऑर्डर') : t('booking', 'बुकिंग'),
          'a' => ago($r['created_at']),
        ], $recent), JSON_UNESCAPED_UNICODE) ?>;
      var e = document.getElementById('proofTx'), i = 0;
      if (L.length < 2) return;
      setInterval(function(){
        i = (i + 1) % L.length;
        e.style.opacity = 0;
        setTimeout(function(){ e.textContent = L[i].v + ' · ' + L[i].k + ' ' + L[i].a; e.style.opacity = 1; }, 220);
      }, 3200);
    })();
    </script>
  <?php endif; ?>

  <!-- ============ offer / ad ============ -->
  <?php if ($banners): ?>
    <div class="secthead" style="margin-top:24px">
      <h2><?= t('What’s on', 'क्या चल रहा है') ?></h2>
      <p><?= t('Offers and new services', 'ऑफ़र और नई सेवाएँ') ?></p>
    </div>
    <div class="bann" id="bann">
      <?php foreach ($banners as $b):
        $ti = is_hi() ? $b['title_hi'] : $b['title_en'];
        $su = is_hi() ? $b['sub_hi'] : $b['sub_en']; ?>
        <a class="bn <?= h($b['tone']) ?>" href="<?= h($b['link'] ?: '/order.php') ?>" data-bid="<?= (int)$b['id'] ?>">
          <b><?= h($ti) ?></b>
          <?php if ($su): ?><i><?= h($su) ?></i><?php endif; ?>
          <span class="go"><?= t('Open', 'देखिए') ?> <?= svc_icon('plus', 13) ?></span>
          <span class="bg"><?= $b['photo']
              ? '<img src="/uploads/' . h($b['photo']) . '" alt="" loading="lazy">'
              : svc_icon('box', 96) ?></span>
        </a>
      <?php endforeach; ?>
    </div>
    <?php if (count($banners) > 1): ?>
      <!-- gol nishaan — kaunsa offer chal raha hai -->
      <div class="banndots" id="banndots" aria-hidden="true">
        <?php for ($i = 0; $i < count($banners); $i++): ?>
          <u<?= $i === 0 ? ' class="on"' : '' ?>></u>
        <?php endfor; ?>
      </div>
    <?php endif; ?>
  <?php endif; ?>

  <!-- ============ purani kitaabein ============ -->
  <?php if ($bks): ?>
    <div class="secthead" style="margin-top:24px">
      <h2><?= t('Old books', 'पुरानी किताबें') ?></h2>
      <p><?= t('Someone’s book, someone else’s need', 'किसी की पढ़ी हुई, किसी के काम की') ?></p>
      <a class="more" href="/books.php"><?= t('See all', 'सारी देखिए') ?> <?= svc_icon('plus', 13) ?></a>
    </div>
    <div class="hbk">
      <?php foreach ($bks as $b): $kd = book_kinds()[$b['kind']] ?? null; ?>
        <a class="hbk-c" href="/book.php?id=<?= (int)$b['id'] ?>">
          <span class="hbk-k" style="background:<?= h($kd['col'] ?? '#7A1F1F') ?>"><?= h(book_kind_label($b['kind'])) ?></span>
          <span class="hbk-i"><?= book_thumb($b, 38) ?></span>
          <b><?= h($b['title']) ?></b>
          <i><?= h(book_price_line($b)) ?></i>
        </a>
      <?php endforeach; ?>
      <a class="hbk-c hbk-add" href="/book-add.php">
        <span class="hbk-i"><?= svc_icon('plus', 30) ?></span>
        <b><?= t('Put up your own book', 'अपनी किताब डालिए') ?></b>
        <i><?= t('Free', 'फ़्री') ?></i>
      </a>
    </div>
  <?php endif; ?>

</div>

<!-- ============ bharose ki baat ============ -->
<section class="trust">
  <div class="wrap">
    <div class="tr">
      <div><span><?= svc_icon('rupee', 22) ?></span><b><?= t('Shop price, always', 'दुकान का ही दाम') ?></b>
        <i><?= t('With the bill. We add nothing to the goods.', 'बिल के साथ। हम सामान पर कुछ नहीं जोड़ते।') ?></i></div>
      <div><span><?= svc_icon('shield', 22) ?></span><b><?= t('4-digit delivery code', '4 अंकों का कोड') ?></b>
        <i><?= t('Goods handed over only when you say the code.', 'कोड बताने पर ही सामान मिलेगा।') ?></i></div>
      <div><span><?= svc_icon('bill', 22) ?></span><b><?= t('Photo of the bill', 'बिल की फ़ोटो') ?></b>
        <i><?= t('The shop’s slip, on your order page.', 'दुकान की पर्ची आपके ऑर्डर पेज पर।') ?></i></div>
      <div><span><?= svc_icon('wifi', 22) ?></span><b><?= t('Works on slow net', 'धीमे नेट पर भी') ?></b>
        <i><?= t('Opens even when the network drops.', 'एक बार खुलने के बाद बिना नेट भी चलेगा।') ?></i></div>
    </div>
  </div>
</section>

<!-- ============ roz ka saaman ============ -->
<?php if ($pop): ?>
<section>
  <div class="wrap">
    <div class="secthead">
      <h2><?= t('Everyday essentials', 'रोज़ का सामान') ?></h2>
      <p><?= t('Tap, pick, order — that’s it', 'दबाइए, चुनिए, ऑर्डर — बस इतना ही') ?></p>
      <a class="more" href="/order.php"><?= t('Full list', 'पूरी लिस्ट') ?> <?= svc_icon('plus', 13) ?></a>
    </div>
    <div class="items">
      <?php foreach ($pop as $i): ?>
        <a class="it" href="/order.php" style="text-decoration:none;color:inherit">
          <div class="thumb"><?= item_thumb($i, 30) ?></div>
          <div class="nm"><?= h($i['name']) ?></div>
          <div class="un"><?= h($i['unit']) ?></div>
          <div class="act"><span class="addbtn" style="display:block;text-align:center"><?= t('ADD', 'जोड़िए') ?></span></div>
        </a>
      <?php endforeach; ?>
    </div>
    <p class="help" style="margin-top:12px"><?= t(
      'Not on the list? Order it anyway — speak it, write it, or send a photo.',
      'लिस्ट में जो नहीं है वो भी मँगा सकते हैं — बोलकर, लिखकर या फ़ोटो भेजकर।') ?></p>
  </div>
</section>
<?php endif; ?>

<!-- ============ salon seat booking ============ -->
<?php if ($live): ?>
<section class="alt">
  <div class="wrap">
    <div class="secthead">
      <h2><?= t('Salon — book your seat', 'नाई और पार्लर — सीट बुक कीजिए') ?></h2>
      <p><?= t('Reserve from home instead of waiting at the shop', 'घर बैठे सीट पक्की कीजिए, दुकान पर इंतज़ार मत कीजिए') ?></p>
    </div>
    <div class="grid g2">
      <?php foreach ($live as $b):
        $isl = salon_live($b);
        list($dot, $lbl, $cls) = salon_status_label($b);
        $board = $isl ? salon_board($pdo, $b) : null; ?>
        <a class="card biz" href="/salon.php?id=<?= (int)$b['id'] ?>">
          <span class="ph"><?= $b['photo'] ? '<img src="/uploads/' . h($b['photo']) . '" alt="">' : svc_icon('salon', 30) ?></span>
          <span>
            <h3><?= h($b['name']) ?></h3>
            <div class="meta"><?= h($b['village']) ?></div>
            <div style="margin-top:6px"><span class="tag <?= h($cls) ?>"><?= $dot ?> <?= h($lbl) ?></span>
              <?= $isl ? '<span class="tag tag-off">' . h(salon_wait_text($board)) . '</span>' : '' ?></div>
          </span>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- ============ grahakon ki raay ============ -->
<?php if ($says): ?>
<section>
  <div class="wrap">
    <div class="secthead">
      <h2><?= t('What people say', 'लोग क्या कहते हैं') ?></h2>
      <p><?= $avg && $avg['c'] ? t(num($avg['a']) . ' out of 5 · ' . num($avg['c']) . ' reviews',
             '5 में से ' . num($avg['a']) . ' · ' . num($avg['c']) . ' लोगों की राय')
           : t('From your own village', 'आपके अपने गाँव से') ?></p>
    </div>
    <div class="says">
      <?php foreach ($says as $sy): ?>
        <div class="say">
          <div class="st"><?= str_repeat('★', max(1, min(5, (int)$sy['rating']))) ?><span><?= str_repeat('★', 5 - max(1, min(5, (int)$sy['rating']))) ?></span></div>
          <p><?= h(mb_strimwidth($sy['comment'], 0, 170, '…')) ?></p>
          <b><?= h($sy['name']) ?><?= $sy['village'] ? ' · ' . h($sy['village']) : '' ?></b>
          <?php if ($sy['biz']): ?><i><?= h($sy['biz']) ?></i><?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- ============ kaise chalta hai ============ -->
<section>
  <div class="wrap">
    <div class="secthead"><h2><?= t('How it works', 'कैसे काम करता है') ?></h2>
      <p><?= t('No app download, no long forms', 'न ऐप डाउनलोड, न लंबा फ़ॉर्म') ?></p></div>
    <div class="steps3">
      <div><span class="n">1</span><b><?= t('Tell us what you need', 'बताइए क्या चाहिए') ?></b>
        <p><?= t('Pick items, speak it out, send a photo — or just call. Name your shop, or we’ll pick a good one.',
                 'सामान चुनिए, बोलकर बताइए, फ़ोटो भेजिए — या सीधे कॉल कीजिए। दुकान आपकी पसंद की, या हम अच्छी दुकान से लाएँगे।') ?></p></div>
      <div><span class="n">2</span><b><?= t('We call with the price', 'दाम और कोड मिलेगा') ?></b>
        <p><?= t('Nothing is bought before you say yes. Your order number and a 4-digit code arrive on WhatsApp.',
                 'हम कॉल करके दाम बताएँगे। आपकी हाँ के बाद ही सामान उठेगा। ऑर्डर नंबर और 4 अंकों का कोड WhatsApp पर आएगा।') ?></p></div>
      <div><span class="n">3</span><b><?= t('Delivered to your door', 'घर पर सामान') ?></b>
        <p><?= t('The rider brings the shop’s bill. Say your code, check the bill, then pay.',
                 'डिलीवरी पार्टनर दुकान का बिल लेकर आएगा। कोड बताइए, बिल देखिए, फिर पैसा दीजिए।') ?></p></div>
    </div>
    <div class="timing">
      <div><b><?= t('Order by 11 AM', 'सुबह 11 बजे तक ऑर्डर') ?></b><i><?= t('At your door by afternoon', 'दोपहर तक घर पर') ?></i></div>
      <div><b><?= t('Order by 3 PM', 'दोपहर 3 बजे तक ऑर्डर') ?></b><i><?= t('At your door by 7 PM', 'शाम 7 बजे तक घर पर') ?></i></div>
    </div>
    <p class="help" style="margin-top:12px"><?= t(
      'For prescription medicine, send a photo of the doctor’s slip. We do not carry liquor, gutkha or anything illegal.',
      'पर्ची वाली दवाई के लिए डॉक्टर की पर्ची की फ़ोटो भेजिए। शराब, गुटखा और ग़ैर-क़ानूनी सामान हम नहीं ले जाते।') ?></p>
  </div>
</section>

<!-- ============ ghar se door ============ -->
<section class="away">
  <div class="wrap">
    <div class="aw">
      <div>
        <p class="kk"><?= t('Living away from home?', 'घर से दूर हैं?') ?></p>
        <h2><?= t('You’re in Mumbai. Your parents are in the village.', 'मुंबई में हैं, माँ-बाप गाँव में।') ?></h2>
        <p class="sub"><?= t(
          'Send them groceries, medicine, anything. We deliver to their door and send you a photo of the shop’s bill. Pay by UPI from wherever you are.',
          'राशन, दवाई या कुछ भी मँगाइए — हम उनके दरवाज़े तक पहुँचाएँगे और दुकान के बिल की फ़ोटो आपको भेजेंगे। पैसा आप वहीं से UPI कर दीजिए।') ?></p>
        <div class="cta">
          <a class="btn btn-gold" href="<?= h(wa_link(MAAKIT_WA, t('Hello Maakit, I live outside. I want to order for my family at home.', 'नमस्ते Maakit, मैं बाहर रहता/रहती हूँ। घर के लिए ऑर्डर करना है।'))) ?>" target="_blank" rel="noopener"><?= t('Talk on WhatsApp', 'WhatsApp पर बात कीजिए') ?></a>
          <a class="btn btn-line" href="/order.php"><?= t('Order yourself', 'ख़ुद ऑर्डर कीजिए') ?></a>
        </div>
      </div>
      <span class="il"><?= svc_icon('box', 90) ?></span>
    </div>
  </div>
</section>

<!-- ============ aam sawaal ============ -->
<section>
  <div class="wrap" style="max-width:820px">
    <div class="secthead"><h2><?= t('Common questions', 'आम सवाल') ?></h2>
      <p><?= t('Straight answers', 'सीधा जवाब') ?></p></div>
    <?php
      $FAQ = [
        [t('Do you charge more than the shop?', 'क्या आप दुकान से ज़्यादा दाम लेते हैं?'),
         t('No. You pay exactly what is on the shop’s bill, and we send you a photo of that bill. On top of that you pay only the delivery charge, which you see before you order.',
           'नहीं। दुकान की पर्ची पर जो लिखा है, वही दाम। उस पर्ची की फ़ोटो भी आपको भेजते हैं। उसके ऊपर सिर्फ़ डिलीवरी चार्ज, जो ऑर्डर से पहले ही दिख जाता है।')],
        [t('What if the goods are wrong or damaged?', 'सामान ग़लत या ख़राब निकला तो?'),
         t('Tell us the same day. We take it back to the shop and get it changed, or return your money. Keep the bill — the rider leaves it with you.',
           'उसी दिन बता दीजिए। हम दुकान ले जाकर बदलवाते हैं, या पैसा वापस करते हैं। पर्ची अपने पास रखिए — डिलीवरी पार्टनर आपको दे जाता है।')],
        [t('Do I have to pay in advance?', 'क्या पहले पैसा देना पड़ता है?'),
         t('No. Pay when the goods reach your door — cash or UPI, as you like. For big bookings we may ask for an advance, and we will tell you on the call.',
           'नहीं। सामान घर पहुँचने पर दीजिए — कैश या UPI, जैसा ठीक लगे। बड़ी बुकिंग में एडवांस लग सकता है, वो कॉल पर बता देंगे।')],
        [t('Can I order medicine?', 'क्या दवाई मँगा सकते हैं?'),
         t('Yes. For prescription medicine send a photo of the doctor’s slip. We do not carry liquor, gutkha or anything illegal.',
           'हाँ। पर्ची वाली दवाई के लिए डॉक्टर की पर्ची की फ़ोटो भेज दीजिए। शराब, गुटखा और ग़ैर-क़ानूनी सामान हम नहीं ले जाते।')],
        [t('I cannot read or type. Can I still order?', 'मुझे पढ़ना-लिखना नहीं आता, तब भी ऑर्डर होगा?'),
         t('Yes. Call ' . MAAKIT_NUMBER_SHOW . ' and just say it. Or tap the mic on the order page and speak in Hindi. Or send a photo of a hand-written list.',
           'हाँ। ' . MAAKIT_NUMBER_SHOW . ' पर कॉल करके बोल दीजिए। या ऑर्डर पेज पर माइक दबाकर हिंदी में बोलिए। या हाथ से लिखी पर्ची की फ़ोटो भेज दीजिए।')],
        [t('My village is not in the list.', 'मेरा गाँव लिस्ट में नहीं है।'),
         t('Tell us on the “Request your village” page. We add the village that the most people ask for.',
           '“अपने गाँव के लिए माँगिए” पेज पर बता दीजिए। जिस गाँव से सबसे ज़्यादा लोग माँगते हैं, अगला वही होता है।')],
        [t('How do I book a Bolero or a goods vehicle?', 'बोलेरो या माल गाड़ी कैसे बुक करें?'),
         t('Open Vehicle or Goods from the home page. You see which vehicles are free today with their rates, pick one, and we call you to fix the fare.',
           'होम पेज से “गाड़ी बुकिंग” या “माल ढुलाई” खोलिए। आज कौन सी गाड़ी खाली है और उसका रेट दिखेगा — चुन लीजिए, किराया हम कॉल पर तय करेंगे।')],
      ];
    ?>
    <div class="faq">
      <?php foreach ($FAQ as $i => $f): ?>
        <details<?= $i === 0 ? ' open' : '' ?>>
          <summary><?= h($f[0]) ?><span class="pm"><?= svc_icon('plus', 15) ?></span></summary>
          <p><?= h($f[1]) ?></p>
        </details>
      <?php endforeach; ?>
    </div>
    <p class="help" style="margin-top:14px;text-align:center"><?= t('Still not clear?', 'फिर भी साफ़ न हो?') ?>
      <a href="tel:<?= MAAKIT_PHONE ?>"><?= t('Call us', 'कॉल कीजिए') ?></a> ·
      <a href="<?= h(wa_link(MAAKIT_WA, t('Hello Maakit, I have a question.', 'नमस्ते Maakit, एक बात पूछनी है।'))) ?>" target="_blank" rel="noopener">WhatsApp</a></p>
  </div>
</section>

<!-- ============ local kaam karne wale ============ -->
<section class="alt">
  <div class="wrap">
    <div class="secthead">
      <h2><?= t('Local people who do the work', 'अपने इलाके के काम करने वाले') ?></h2>
      <p><?= t('Pandit ji to plumber — find the number, call directly', 'पंडित जी से मिस्त्री तक — नंबर ढूंढिए, सीधे बात कीजिए') ?><?= $total_biz ? ' · ' . t(num($total_biz) . ' listed', num($total_biz) . ' लोग जुड़े हैं') : '' ?></p>
    </div>
    <form class="hsearch light" action="/directory.php" method="get">
      <span class="ic"><?= svc_icon('search', 20) ?></span>
      <input type="text" name="q" id="dq" placeholder="<?= h(t('plumber, tent, nalka wala…', 'नलका वाला, plumber, टेंट…')) ?>" aria-label="<?= h(t('Find a service', 'काम करने वाले खोजिए')) ?>">
      <button class="go" type="submit"><?= t('Search', 'खोजिए') ?></button>
    </form>
    <div class="catrow">
      <?php foreach (categories() as $c): ?>
        <a class="cc" href="/directory.php?cat=<?= h($c['slug']) ?>">
          <span><?= cat_icon($c['icon'], 24) ?></span><?= h($c['name']) ?>
        </a>
      <?php endforeach; ?>
    </div>
    <?php if ($latest): ?>
      <div class="grid g3" style="margin-top:18px">
        <?php foreach ($latest as $b): ?>
          <a class="card biz" href="/business.php?id=<?= (int)$b['id'] ?>">
            <span class="ph"><?= $b['photo'] ? '<img src="/uploads/' . h($b['photo']) . '" alt="">' : cat_icon(cat_by_slug($b['category'])['icon'] ?? 'anya', 30) ?></span>
            <span>
              <h3><?= h($b['name']) ?></h3>
              <div class="meta"><?= h(cat_by_slug($b['category'])['name'] ?? '') ?> · <?= h($b['village']) ?></div>
            </span>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
    <div style="margin-top:18px;display:flex;gap:10px;flex-wrap:wrap">
      <a class="btn btn-brand" href="/register-business.php"><?= t('List your shop or service — free', 'अपना काम / दुकान जोड़िए — फ़्री') ?></a>
      <a class="btn btn-gold" href="/transport.php"><?= t('Have a vehicle? Register it', 'गाड़ी है? जोड़िए') ?></a>
      <a class="btn btn-line" href="/directory.php" style="color:var(--brand);border-color:var(--line)"><?= t('See all', 'सब देखिए') ?></a>
    </div>
  </div>
</section>

<script>
/* ---------- offer ki patti khud chalti hai ----------
   Tasveer aur uspar likha hua, dono ek saath badalte hain —
   kyunki har patti apne andar dono rakhti hai.
   Ungli se khiskane par apne aap ruk jati hai, taaki padhne me
   dikkat na ho. Jinhe hilti cheezein pasand nahi, unke liye
   bilkul nahi chalti. */
(function(){
  var box = document.getElementById('bann');
  var dots = document.getElementById('banndots');
  if (!box || !dots) return;
  var sl = box.querySelectorAll('.bn');
  var du = dots.querySelectorAll('u');
  if (sl.length < 2) return;
  if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

  var i = 0, ruka = false, tmr = null;

  function mark(n){
    for (var k = 0; k < du.length; k++) du[k].classList.toggle('on', k === n);
  }
  function kaunsa(){               // abhi kaun si patti saamne hai
    var best = 0, kam = 1e9;
    for (var k = 0; k < sl.length; k++) {
      var d = Math.abs(sl[k].offsetLeft - box.scrollLeft);
      if (d < kam) { kam = d; best = k; }
    }
    return best;
  }
  function aage(){
    if (ruka) return;
    i = (kaunsa() + 1) % sl.length;
    box.scrollTo({ left: sl[i].offsetLeft - box.offsetLeft, behavior: 'smooth' });
    mark(i);
  }

  box.addEventListener('scroll', function(){ mark(kaunsa()); }, { passive: true });
  // ungli rakhte hi ruk jaye
  ['pointerdown','touchstart'].forEach(function(ev){
    box.addEventListener(ev, function(){ ruka = true; }, { passive: true });
  });
  ['pointerup','touchend','mouseleave'].forEach(function(ev){
    box.addEventListener(ev, function(){ ruka = false; }, { passive: true });
  });
  // dusre tab par gaye to chalana band — data aur battery dono bachti hai
  document.addEventListener('visibilitychange', function(){
    if (document.hidden) { clearInterval(tmr); tmr = null; }
    else if (!tmr) { tmr = setInterval(aage, 4200); }
  });
  tmr = setInterval(aage, 4200);
})();

/* banner kitni baar dabaya gaya */
document.addEventListener('click', function(e){
  var a = e.target.closest('.bn[data-bid]'); if (!a) return;
  try { var d = new FormData(); d.append('a','bclick'); d.append('id', a.getAttribute('data-bid'));
        if (navigator.sendBeacon) navigator.sendBeacon('/api.php', d); } catch(_){}
});
(function(){
  var el = document.getElementById('q');
  if (!el) return;
  var list = <?= json_encode(is_hi()
      ? ['आटा, चावल, दाल…','बुख़ार की दवा','सरसों तेल 1 लीटर','गरम खाना मँगाइए','बोलेरो बुक कीजिए','शादी के लिए लॉन','टेंट और डीजे','पंडित जी चाहिए?','नल मिस्त्री बुलाइए']
      : ['Atta, rice, dal…','Fever medicine','Mustard oil 1 litre','Hot food delivered','Book a Bolero','Lawn for a wedding','Tent and DJ','Need a pandit ji?','Call a plumber'], JSON_UNESCAPED_UNICODE) ?>;
  var i = 0;
  setInterval(function(){ if (document.activeElement === el || el.value) return;
    i = (i + 1) % list.length; el.setAttribute('placeholder', list[i]); }, 2400);
})();
</script>
<?php include __DIR__ . '/inc/foot.php'; ?>
