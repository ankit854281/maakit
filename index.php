<?php
require_once __DIR__ . '/inc/fn.php';
require_once __DIR__ . '/inc/story.php';
require_once __DIR__ . '/inc/salon.php';
require_once __DIR__ . '/inc/items.php';
require_once __DIR__ . '/inc/icons.php';
require_once __DIR__ . '/inc/services.php';
require_once __DIR__ . '/inc/customer.php';
require_once __DIR__ . '/inc/books.php';
require_once __DIR__ . '/inc/catalog.php';
require_once __DIR__.'/inc/catalog-photos.php';
require_once __DIR__.'/inc/category-cards.php';

$page_title = t('Maakit — shopping, local delivery & bookings in India',
                'Maakit — भारत में शॉपिंग, स्थानीय डिलीवरी और बुकिंग');
$tab = 'ghar';
$me  = cust();

$live = $pdo->query("SELECT *, (salon_updated IS NOT NULL AND salon_updated > (NOW() - INTERVAL 180 MINUTE)) AS fresh
                     FROM businesses WHERE status='approved' AND salon_on=1 AND mode<>'off'
                     ORDER BY salon_updated DESC LIMIT 4")->fetchAll();

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

try {
    $banners = $pdo->query("SELECT * FROM banners WHERE active=1
        AND (starts IS NULL OR starts <= CURDATE()) AND (ends IS NULL OR ends >= CURDATE())
        ORDER BY sort_no, id LIMIT 6")->fetchAll();
} catch (Throwable $e) { $banners = []; }

// Discovery templates stay visible even before a shop publishes a price.
$discovery = []; $discovery_offers = []; $discovery_photos = [];
try {
    $cs = $pdo->prepare('SELECT * FROM catalog_items WHERE shop_type=? ORDER BY CASE WHEN id IN (1517,1659,1666,1669,1671,1678) THEN 0 ELSE 1 END,sort_no,id LIMIT 6');
    foreach (['Grocery / Kirana Store','Sweet Shop'] as $kind) {
        $cs->execute([$kind]);
        $rows = $cs->fetchAll();
        if ($rows) $discovery[$kind] = $rows;
    }
    $ids=[];
    foreach ($discovery as $rows) foreach ($rows as $row) $ids[]=(int)$row['id'];
    $discovery_offers=catalog_offers($pdo,$ids,coverage_selected($pdo));
    $discovery_photos=catalog_photos_for($pdo,$ids);
} catch (PDOException $e) { error_log('Maakit home catalogue: '.$e->getMessage()); }

require_once __DIR__ . '/inc/festival.php';
$fest = maakit_festival_now($pdo);
function mk_unit($u) { return is_hi() ? $u : strtr((string)$u, ['किलो' => 'kg', 'ग्राम' => 'g', 'लीटर' => 'litre', 'पीस' => 'piece', 'पैकेट' => 'packet', 'दर्जन' => 'dozen', 'बोतल' => 'bottle', 'डिब्बा' => 'box', 'जोड़ा' => 'pair', 'मीटर' => 'metre']); }
function mk_price($n) { return rtrim(rtrim(number_format((float)$n, 2, '.', ''), '0'), '.'); }
// paas ki dukanen (asli, manzoor)
$stores = [];
try {
    if (!function_exists('dukan_khuli')) require_once __DIR__ . '/inc/dukan.php';
    $stores = $pdo->query("SELECT id, name, village, photo, shop_type, category, shop_open, open_time, close_time, items_on,
        (SELECT COUNT(*) FROM shop_items s WHERE s.business_id=b.id AND s.active=1 AND s.price>0) AS n_items
        FROM businesses b WHERE status='approved' AND (items_on=1 OR salon_on=1 OR photo IS NOT NULL)
        ORDER BY (photo IS NOT NULL AND photo<>'') DESC, n_items DESC, id DESC LIMIT 8")->fetchAll();
} catch (Throwable $e) { $stores = []; }
// grahak ka pichhla pura hua order — "phir se mangaiye"
$last_order = null;
if ($me) {
    try {
        $st = $pdo->prepare("SELECT order_no, items, created_at FROM orders WHERE mobile=? AND status IN ('Delivered','Paisa jama') ORDER BY id DESC LIMIT 1");
        $st->execute([$me['mobile'] ?? '']); $last_order = $st->fetch() ?: null;
    } catch (Throwable $e) {}
}
$home_page = true;
include __DIR__ . '/inc/head.php';
?>
<main class="mk-home">

  <?php if ($fest): ?>
  <section class="mk-top mk-top-fest" style="--fbg:<?= h($fest['bg']) ?>;--fdeep:<?= h($fest['deep']) ?>;--facc:<?= h($fest['accent']) ?>">
   <div class="mk-wrap mk-top-in">
    <div class="mk-hero-text">
      <span class="mk-kicker"><?= t('Festival special', 'त्योहार विशेष') ?></span>
      <h1><?= h(t($fest['en'], $fest['hi'])) ?></h1>
      <p><?= h(t($fest['sub_en'], $fest['sub_hi'])) ?></p>
      <div class="mk-hero-cta">
        <a class="mk-btn" href="/search.php?q=<?= h(urlencode($fest['q'])) ?>"><?= h(t($fest['cta_en'], $fest['cta_hi'])) ?></a>
        <a class="mk-btn mk-btn-line" href="/order.php#pata"><?= t('Send a list', 'लिस्ट भेजिए') ?></a>
      </div>
    </div>
    <?= maakit_festival_art($fest) ?>
   </div>
  </section>
  <?php else: ?>
  <section class="mk-top">
   <div class="mk-wrap mk-top-in">
    <div class="mk-hero-text">
      <span class="mk-kicker"><span class="mk-dot <?= $is_open ? 'on' : '' ?>"></span><?= h($open_short) ?></span>
      <h1><?= $me ? t('Hello ', 'नमस्ते ') . h(mb_substr(explode(' ', trim($me['name']))[0], 0, 12)) . ', ' . t('what do you need?', 'आज क्या चाहिए?') : t('Your local shops, delivered.', 'आपकी दुकानें, आपके घर।') ?></h1>
      <p><?= t('Groceries, medicines and daily needs from shops near you.', 'राशन, दवाई और रोज़ का सामान — पास की दुकानों से।') ?></p>
      <div class="mk-hero-cta">
        <a class="mk-btn" href="/bazaar.php"><?= t('Start shopping', 'सामान चुनिए') ?></a>
        <a class="mk-btn mk-btn-line" href="/order.php#pata"><?= t('Send a list', 'लिस्ट भेजिए') ?></a>
      </div>
    </div>
   </div>
  </section>
  <?php endif; ?>
<div class="mk-wrap">
    <nav class="mk-segs" aria-label="<?= h(t('Choose how to use Maakit','Maakit में अपना काम चुनिए')) ?>">
      <?php foreach (['LOCAL_SHOPPING'=>['Shopping','शॉपिंग'],'HOME_SERVICES'=>['Home services','घरेलू सेवाएँ'],'B2B'=>['Wholesale','थोक']] as $key=>$label): ?>
        <a href="/public/index.php?segment=<?= h($key) ?>" data-segment="<?= h($key) ?>"<?= $key==='LOCAL_SHOPPING' ? ' aria-current="page"' : '' ?>><?= h(t($label[0],$label[1])) ?></a>
      <?php endforeach; ?>
    </nav>

  <ul class="mk-trust" aria-label="<?= h(t('Why Maakit', 'Maakit क्यों')) ?>">
    <li><?= svc_icon('shops', 18) ?><?= t('Shop price, no markup', 'दुकान का ही दाम') ?></li>
    <li><?= svc_icon('box', 18) ?><?= t('Delivery to your door', 'घर तक डिलीवरी') ?></li>
    <li><?= svc_icon('user', 18) ?><?= t('Local team on call', 'फ़ोन पर मदद') ?></li>
  </ul>
  <!-- sawari / parcel — Smart Sarathi ke rider (ride.php) -->
  <nav class="mk-go" aria-label="<?= h(t('Ride and parcel', 'सवारी और पार्सल')) ?>">
    <a class="mk-go-ride" href="/ride.php">
      <span class="mk-go-t"><small><?= t('Bike · Auto · E-rickshaw', 'बाइक · ऑटो · ई-रिक्शा') ?></small><b><?= t('Book a ride', 'सवारी बुक कीजिए') ?></b><em><?= t('Fare before you book', 'पहले किराया देखिए') ?> →</em></span>
      <span class="mk-go-em" aria-hidden="true"><?= svc_icon('ride', 34) ?></span>
    </a>
    <a class="mk-go-parcel" href="/ride.php?t=parcel">
      <span class="mk-go-t"><small><?= t('Pick up & drop', 'उठाना · पहुँचाना') ?></small><b><?= t('Send a parcel', 'पार्सल भेजिए') ?></b><em><?= t('Tiffin, papers, medicine', 'टिफ़िन, काग़ज़, दवा') ?> →</em></span>
      <span class="mk-go-em" aria-hidden="true"><?= svc_icon('box', 34) ?></span>
    </a>
  </nav>
  <?php if (!empty($tick_hul) && !empty($tick)): ?>
  <div class="mk-live" aria-label="<?= h(t('Recent activity', 'अभी-अभी')) ?>">
    <span class="mk-live-l"><i></i><?= t('Live', 'अभी-अभी') ?></span>
    <div class="mk-live-run"><div><?php for ($p = 0; $p < 2; $p++): foreach ($tick as $v): ?><span><?= h($v) ?></span><?php endforeach; endfor; ?></div></div>
  </div>
  <?php endif; ?>

  <?php
    // ---- आज दुकानों पर क्या आया ----
    //
    // Gaon me khabar vigyapan se nahi, dekhne se phailti hai. Yahi
    // wo photo hai jo dukaandaar apne WhatsApp Status par daalta
    // hai -- par us tak bhi pahunchti hai jiske paas uska number
    // nahi hai.
    //
    // Sabse upar isliye hai ki ye panne ki SABSE TAAZA cheez hai.
    // Neeche ki har cheez kal bhi wahi thi; ye sirf aaj ki hai.
    // Koi story na ho to poora hissa gayab -- khali patti panne
    // ko sookha dikhati hai.
    $kahaniyan = [];
    try { $kahaniyan = story_taaza($pdo, 12); } catch (Throwable $e) { $kahaniyan = []; }
  ?>
  <?php if ($kahaniyan): ?>
  <section class="mk-sec">
    <div class="mk-sec-head"><h2><?= t('Today at the shops', 'आज दुकानों पर') ?></h2></div>
    <nav class="mk-kah" aria-label="<?= h(t('Shop stories', 'दुकान की कहानियाँ')) ?>">
      <?php foreach ($kahaniyan as $kh): ?>
        <a class="mk-kah-c" href="/business.php?id=<?= (int)$kh['bid'] ?>&amp;kh=<?= (int)$kh['id'] ?>">
          <span class="mk-kah-p">
            <?php if ($kh['photo']): ?>
              <img src="/uploads/<?= h($kh['photo']) ?>" alt="" loading="lazy">
            <?php else: ?>
              <i><?= h(mb_substr(trim((string)$kh['dukaan']), 0, 1)) ?></i>
            <?php endif; ?>
          </span>
          <b><?= h($kh['dukaan']) ?></b>
          <?php if ($kh['baat']): ?><small><?= h($kh['baat']) ?></small><?php endif; ?>
        </a>
      <?php endforeach; ?>
    </nav>
  </section>
  <?php endif; ?>

  <section class="mk-sec">
    <div class="mk-sec-head"><h2><?= t('Shop by category', 'श्रेणी चुनिए') ?></h2><a href="/bazaar.php"><?= t('See all', 'सब देखिए') ?></a></div>
    <nav class="mk-ccards" id="shop-category-rail" aria-label="<?= h(t('Categories', 'श्रेणियाँ')) ?>">
      <?php foreach (maakit_category_cards() as $cc): $ph = maakit_category_card_photos($pdo, $cc); ?>
        <a class="mk-cc<?= !empty($cc['wide']) ? ' wide' : '' ?>" href="<?= h(catalog_url(['type' => $cc['type'], 'sub' => count($cc['subs']) === 1 ? $cc['subs'][0] : ''])) ?>">
          <b><?= h(t($cc['en'], $cc['hi'])) ?></b>
          <span class="mk-cc-pics n<?= count($ph) ?>">
            <?php if ($ph): foreach ($ph as $f): ?><img src="/uploads/<?= h($f) ?>" alt="" loading="lazy"><?php endforeach; else: ?><?= maakit_category_art($cc['key']) ?><?php endif; ?>
          </span>
        </a>
      <?php endforeach; ?>
    </nav>
  </section>

  <?php if ($last_order): ?>
  <section class="mk-sec">
    <div class="mk-again">
      <span class="mk-cat-ic"><?= svc_icon('box', 24) ?></span>
      <span class="mk-again-t"><b><?= t('Order again', 'फिर से मँगाइए') ?></b><small><?= h(mb_strimwidth((string)$last_order['items'], 0, 80, '…')) ?></small></span>
      <a class="mk-btn mk-btn-sm" href="<?= h(wa_link(MAAKIT_WA, t('Please repeat my order ', 'मेरा यह ऑर्डर फिर से भेज दीजिए ') . $last_order['order_no'] . ":\n" . $last_order['items'])) ?>" target="_blank" rel="noopener"><?= t('Repeat', 'दोबारा') ?></a>
    </div>
  </section>
  <?php endif; ?>

  <?php foreach ($discovery as $kind=>$products): ?>
  <section class="mk-sec">
    <div class="mk-sec-head"><h2><?= h(catalog_label($kind)) ?></h2><a href="<?= h(catalog_url(['type'=>$kind])) ?>"><?= t('See all', 'सब देखिए') ?></a></div>
    <div class="mk-rail product-discovery-rail">
      <?php foreach ($products as $product):
        $offers=array_values(array_filter($discovery_offers[(int)$product['id']] ?? [],fn($o)=>$o['stock']==='hai'));
        $offer=$offers[0] ?? null;
        $pics=$discovery_photos[(int)$product['id']] ?? [];
        $cover=$pics[0] ?? ($offer['photo'] ?? null);
        $ref=$offer ? null : catalog_reference_price($product);
        $pname=t($product['name_en'],$product['name_hi'] ?: $product['name_en']);
        $unit=$offer['unit'] ?? ($ref['packs'][0]['unit'] ?? $product['unit_hint']); ?>
        <a class="mk-pc discovery-product" href="<?= h(catalog_url(['product'=>(int)$product['id']])) ?>">
          <span class="mk-pc-img"><?php if ($cover): ?><img src="/uploads/<?= h($cover) ?>" alt="" loading="lazy"><?php else: ?><?= catalog_product_icon($product,34) ?><?php endif; ?></span>
          <?php if ($offer): ?><span class="mk-tag ok"><?= t('In stock','उपलब्ध') ?></span>
          <?php elseif ($ref): ?><span class="mk-tag"><?= t('Market price','बाज़ार दाम') ?></span>
          <?php else: ?><span class="mk-tag"><?= t('Ask price','दाम पूछिए') ?></span><?php endif; ?>
          <b class="mk-pc-name"><?= h($pname) ?></b>
          <span class="mk-pc-unit"><?= h(mk_unit($unit)) ?></span>
          <span class="mk-pc-foot">
            <span class="mk-pc-price"><?php if ($offer): ?>₹<?= h(mk_price($offer['price'])) ?><?php elseif ($ref && !empty($ref['packs'][0])): ?>₹<?= h(mk_price($ref['packs'][0]['price'])) ?><?php else: ?>—<?php endif; ?></span>
            <span class="mk-add"><?= t('ADD', 'जोड़ें') ?></span>
          </span>
        </a>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endforeach; ?>

  <?php if ($stores): ?>
  <section class="mk-sec">
    <div class="mk-sec-head"><h2><?= t('Shops near you', 'आपके पास की दुकानें') ?></h2><a href="/directory.php"><?= t('See all', 'सब देखिए') ?></a></div>
    <div class="mk-stores">
      <?php foreach ($stores as $b): $open = function_exists('dukan_khuli') ? dukan_khuli($b) : true; ?>
        <a class="mk-store" href="/business.php?id=<?= (int)$b['id'] ?>">
          <span class="mk-store-img"><?php if ($b['photo']): ?><img src="/uploads/<?= h($b['photo']) ?>" alt="" loading="lazy"><?php else: ?><?= svc_icon('shops', 30) ?><?php endif; ?>
            <em class="<?= $open ? 'on' : '' ?>"><?= $open ? t('Open', 'खुली') : t('Closed', 'बंद') ?></em></span>
          <b><?= h($b['name']) ?></b>
          <small><?= h($b['village']) ?><?= $b['shop_type'] ? ' · ' . h(catalog_label($b['shop_type'])) : '' ?></small>
          <?php if ((int)$b['n_items']): ?><small class="mk-store-n"><?= (int)$b['n_items'] ?> <?= t('products with price', 'सामान दाम सहित') ?></small><?php endif; ?>
        </a>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>

  <section class="mk-sec mk-services" aria-labelledby="mk-services-title">
    <div class="mk-services-head">
      <div><span class="mk-services-kicker"><?= t('MAAKIT SERVICES', 'Maakit की सेवाएँ') ?></span>
        <h2 id="mk-services-title"><?= t('Everyday needs, all in one place', 'रोज़ की ज़रूरतें, एक ही जगह') ?></h2>
        <p><?= t('Shop nearby, book local help, or arrange a ride. Choose what you need.', 'पास की दुकान से सामान लें, स्थानीय सेवा बुक करें या सवारी मँगाएँ।') ?></p>
      </div>
      <a href="/public/index.php"><?= t('Explore all', 'सब देखें') ?> <span aria-hidden="true">→</span></a>
    </div>
    <div class="mk-svcs mk-service-grid">
      <a href="/bazaar.php" class="mk-service-card">
        <span class="mk-cat-ic"><?= svc_icon('grocery', 25) ?></span>
        <span class="mk-service-copy"><b><?= t('Shop nearby', 'पास की दुकानों से') ?></b><small><?= t('Groceries, medicine & daily needs', 'राशन, दवाई और रोज़ का सामान') ?></small><em><?= t('Browse shops', 'दुकानें देखें') ?> <i aria-hidden="true">→</i></em></span>
      </a>
      <a href="/ride.php" class="mk-service-card">
        <span class="mk-cat-ic"><?= svc_icon('ride', 25) ?></span>
        <span class="mk-service-copy"><b><?= t('Book a ride', 'सवारी बुक करें') ?></b><small><?= t('Bike, auto & e-rickshaw', 'बाइक, ऑटो और ई-रिक्शा') ?></small><em><?= t('See fare first', 'पहले किराया देखें') ?> <i aria-hidden="true">→</i></em></span>
      </a>
      <a href="/ride.php?t=parcel" class="mk-service-card">
        <span class="mk-cat-ic"><?= svc_icon('box', 25) ?></span>
        <span class="mk-service-copy"><b><?= t('Send a parcel', 'पार्सल भेजें') ?></b><small><?= t('Local pick-up and drop', 'आस-पास से उठवाएँ, पहुँचवाएँ') ?></small><em><?= t('Arrange a drop', 'पिक-अप बुक करें') ?> <i aria-hidden="true">→</i></em></span>
      </a>
      <a href="/sewa.php" class="mk-service-card">
        <span class="mk-cat-ic"><?= svc_icon('all', 25) ?></span>
        <span class="mk-service-copy"><b><?= t('Book local services', 'स्थानीय सेवा बुक करें') ?></b><small><?= t('Vehicles, events & home help', 'गाड़ी, कार्यक्रम और घर के काम') ?></small><em><?= t('See bookings', 'बुकिंग देखें') ?> <i aria-hidden="true">→</i></em></span>
      </a>
      <a href="/directory.php" class="mk-service-card">
        <span class="mk-cat-ic"><?= svc_icon('shops', 25) ?></span>
        <span class="mk-service-copy"><b><?= t('Find local businesses', 'स्थानीय दुकानें खोजें') ?></b><small><?= t('Shops and nearby professionals', 'दुकानें और आस-पास के कारीगर') ?></small><em><?= t('Open directory', 'सूची देखें') ?> <i aria-hidden="true">→</i></em></span>
      </a>
      <a href="/track.php" class="mk-service-card">
        <span class="mk-cat-ic"><?= svc_icon('box', 25) ?></span>
        <span class="mk-service-copy"><b><?= t('Track an order', 'ऑर्डर की स्थिति देखें') ?></b><small><?= t('Check your order updates', 'अपने ऑर्डर की ताज़ा जानकारी') ?></small><em><?= t('View orders', 'ऑर्डर देखें') ?> <i aria-hidden="true">→</i></em></span>
      </a>
      <a href="/books.php" class="mk-service-card">
        <span class="mk-cat-ic"><?= svc_icon('book', 25) ?></span>
        <span class="mk-service-copy"><b><?= t('Second-hand books', 'पुरानी किताबें') ?></b><small><?= t('Find, buy or list used books', 'पुरानी किताबें खोजें या बेचें') ?></small><em><?= t('Browse books', 'किताबें देखें') ?> <i aria-hidden="true">→</i></em></span>
      </a>
      <?php if ($live): ?>
      <a href="/salon.php?id=<?= (int)$live[0]['id'] ?>" class="mk-service-card">
        <span class="mk-cat-ic"><?= svc_icon('salon', 25) ?></span>
        <span class="mk-service-copy"><b><?= t('Salon seat booking', 'नाई / पार्लर की सीट') ?></b><small><?= t('See live local salon availability', 'पास के सैलून की उपलब्धता देखें') ?></small><em><?= t('Open salon', 'सैलून खोलें') ?> <i aria-hidden="true">→</i></em></span>
      </a>
      <?php endif; ?>
    </div>
    <p class="mk-services-note"><?= t('Service availability depends on your selected area.', 'सेवा की उपलब्धता चुने हुए इलाके पर निर्भर है।') ?></p>
  </section>

  <?php if ($banners): ?>
  <section class="mk-sec">
    <div class="mk-sec-head"><h2><?= t('Offers', 'ऑफ़र') ?></h2></div>
    <div class="mk-offers" id="bann">
      <?php foreach ($banners as $b): $ti = is_hi() ? $b['title_hi'] : $b['title_en']; $su = is_hi() ? $b['sub_hi'] : $b['sub_en']; ?>
        <a class="mk-offer tone-<?= h($b['tone']) ?>" href="<?= h($b['link'] === '/book.php?s=gaadi' ? '/sewa.php?s=safar' : ($b['link'] ?: '/order.php')) ?>" data-bid="<?= (int)$b['id'] ?>">
          <span><b><?= h($ti) ?></b><?php if ($su): ?><small><?= h($su) ?></small><?php endif; ?></span>
          <?php if ($b['photo']): ?><img src="/uploads/<?= h($b['photo']) ?>" alt="" loading="lazy"><?php endif; ?>
        </a>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>

  <?php if ($live): ?>
  <section class="mk-sec">
    <div class="mk-sec-head"><h2><?= t('Salon — book a seat', 'नाई / पार्लर — सीट बुक करें') ?></h2></div>
    <div class="mk-list">
      <?php foreach ($live as $b): $isl = salon_live($b); list($dot, $lbl, $cls) = salon_status_label($b); $board = $isl ? salon_board($pdo, $b) : null; ?>
        <a href="/salon.php?id=<?= (int)$b['id'] ?>"><span class="mk-cat-ic"><?= $b['photo'] ? '<img src="/uploads/' . h($b['photo']) . '" alt="">' : svc_icon('salon', 24) ?></span>
          <span><b><?= h($b['name']) ?></b><small><?= h($b['village']) ?> · <?= h($lbl) ?><?= $isl ? ' · ' . h(salon_wait_text($board)) : '' ?></small></span></a>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>

  <?php if ($bks): ?>
  <section class="mk-sec">
    <div class="mk-sec-head"><h2><?= t('Old books', 'पुरानी किताबें') ?></h2><a href="/books.php"><?= t('See all', 'सब देखिए') ?></a></div>
    <div class="mk-rail">
      <?php foreach ($bks as $b): ?>
        <a class="mk-pc" href="/book.php?id=<?= (int)$b['id'] ?>"><span class="mk-pc-img"><?= book_thumb($b, 34) ?></span>
          <b class="mk-pc-name"><?= h($b['title']) ?></b><span class="mk-pc-unit"><?= h(book_price_line($b)) ?></span></a>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>

  <?php if ($says): ?>
  <section class="mk-sec">
    <div class="mk-sec-head"><h2><?= t('Customer reviews', 'ग्राहकों की राय') ?></h2><?php if ($avg && $avg['c']): ?><span class="mk-muted">★ <?= num($avg['a']) ?> · <?= num($avg['c']) ?></span><?php endif; ?></div>
    <div class="mk-reviews">
      <?php foreach ($says as $sy): ?>
        <figure><span class="mk-stars"><?= str_repeat('★', max(1, min(5, (int)$sy['rating']))) ?></span>
          <blockquote><?= h(mb_strimwidth($sy['comment'], 0, 140, '…')) ?></blockquote>
          <figcaption><?= h($sy['name']) ?><?= $sy['village'] ? ' · ' . h($sy['village']) : '' ?></figcaption></figure>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>

  <a class="mk-partner" href="/register-business.php">
    <span class="mk-cat-ic"><?= svc_icon('shops', 24) ?></span>
    <span><b><?= t('Own a shop? Sell on Maakit', 'दुकानदार हैं? Maakit पर बेचिए') ?></b><small><?= t('Free listing · orders on your phone', 'मुफ़्त · ऑर्डर आपके फ़ोन पर') ?></small></span>
    <span class="mk-btn mk-btn-sm"><?= t('Register', 'रजिस्टर करें') ?></span>
  </a>

  <div class="install mk-install" id="installBox">
    <span><b><?= t('Add Maakit to your phone', 'Maakit फ़ोन में रखिए') ?></b><small><?= t('Works like an app', 'ऐप की तरह चलता है') ?></small></span>
    <button class="mk-btn mk-btn-sm" id="installYes"><?= t('Add', 'रखिए') ?></button>
    <button class="mk-link" id="installNo"><?= t('Not now', 'अभी नहीं') ?></button>
  </div>
</div>
</main>

<form class="mk-float-search" id="floatSearch" action="/search.php" method="get" role="search">
  <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
  <input type="search" name="q" id="q" placeholder="<?= h(t('Search atta, medicine, Bolero…', 'खोजिए: आटा, दवाई, बोलेरो…')) ?>" aria-label="<?= h(t('Search', 'खोजिए')) ?>">
  <button type="button" class="mk-fs-mic" id="fsMic" hidden aria-label="<?= h(t('Search by voice', 'बोलकर खोजिए')) ?>"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><rect x="9" y="3" width="6" height="11" rx="3"/><path d="M5 11a7 7 0 0 0 14 0M12 18v3"/></svg></button>
  <button type="submit" class="mk-fs-go"><?= t('Search', 'खोजिए') ?></button>
</form>
<script>
/* neeche wala khoj-dabba: shuru me bada, scroll karte hi chhota */
(function(){
  var f=document.getElementById('floatSearch'); if(!f) return;
  var small=false, tick=false;
  function upd(){ tick=false; var s=window.scrollY>60; if(s!==small){small=s;f.classList.toggle('is-small',s);} }
  window.addEventListener('scroll',function(){ if(!tick){tick=true;requestAnimationFrame(upd);} },{passive:true});
  f.addEventListener('focusin',function(){f.classList.remove('is-small');small=false;});
  upd();
  var el=document.getElementById('q'), list=<?= json_encode(is_hi()
      ? ['आटा, चावल, दाल…','बुख़ार की दवा','सरसों तेल','बोलेरो बुक कीजिए','पूजा सामग्री']
      : ['Atta, rice, dal…','Fever medicine','Mustard oil','Book a Bolero','Puja samagri'], JSON_UNESCAPED_UNICODE) ?>, i=0;
  setInterval(function(){ if(document.activeElement===el||el.value) return; i=(i+1)%list.length; el.setAttribute('placeholder',list[i]); },2600);
  /* bolkar khojiye — Chrome/Android me chalta hai, baaki me button chhupa rehta hai */
  var SR = window.SpeechRecognition || window.webkitSpeechRecognition, mic = document.getElementById('fsMic');
  if (SR && mic) {
    mic.hidden = false;
    mic.addEventListener('click', function(){
      var r = new SR(); r.lang = <?= json_encode(is_hi() ? 'hi-IN' : 'en-IN') ?>; r.interimResults = false; r.maxAlternatives = 1;
      f.classList.remove('is-small'); mic.classList.add('on'); el.setAttribute('placeholder', <?= json_encode(t('Listening…', 'सुन रहे हैं…'), JSON_UNESCAPED_UNICODE) ?>);
      r.onresult = function(ev){ el.value = ev.results[0][0].transcript; f.submit(); };
      r.onend = function(){ mic.classList.remove('on'); };
      r.onerror = function(){ mic.classList.remove('on'); };
      try { r.start(); } catch (_) { mic.classList.remove('on'); }
    });
  }
})();
</script>
<script>
document.addEventListener('click', function(e){
  var a = e.target.closest('[data-bid]'); if (!a) return;
  try { var d = new FormData(); d.append('a','bclick'); d.append('id', a.getAttribute('data-bid'));
        if (navigator.sendBeacon) navigator.sendBeacon('/api.php', d); } catch(_){}
});
</script>
<?php include __DIR__ . '/inc/foot.php'; ?>
