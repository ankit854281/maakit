<?php
require_once __DIR__ . '/inc/fn.php';
require_once __DIR__ . '/inc/salon.php';
require_once __DIR__ . '/inc/items.php';
require_once __DIR__ . '/inc/icons.php';
require_once __DIR__ . '/inc/services.php';
require_once __DIR__ . '/inc/customer.php';
require_once __DIR__ . '/inc/books.php';
require_once __DIR__ . '/inc/catalog.php';
require_once __DIR__.'/inc/segments.php';
$hub_design=true;

$page_title = t('Maakit — shopping, local delivery & bookings in India',
                'Maakit — भारत में शॉपिंग, स्थानीय डिलीवरी और बुकिंग');
$tab = 'ghar';
$request_design = true;
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
$discovery = []; $discovery_offers = [];
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
} catch (PDOException $e) { error_log('Maakit home catalogue: '.$e->getMessage()); }

include __DIR__ . '/inc/head.php';
?>

<main class="search-page home-shopping" data-previous="<?= h(t('Previous cards','पिछले कार्ड')) ?>" data-next="<?= h(t('Next cards','अगले कार्ड')) ?>">
<!-- ============ hero ============ -->
<section class="hero2">
  <div class="wrap">
    <?php maakit_segments(); ?>
    <div class="hbar">
      <span class="loc"><?= svc_icon('box', 15) ?> <?= t('Building across India · check your service area', 'भारत में विस्तार · अपने इलाके में सेवा जाँचें') ?></span>
      <span class="opn <?= $is_open ? 'yes' : 'no' ?>"><i></i> <?= h($open_short) ?></span>
    </div>
    <h1><?= $me
        ? t('Hello, ', 'नमस्ते, ') . h(mb_substr(explode(' ', trim($me['name']))[0], 0, 12)) . '.<br>' . t('What do you need?', 'आज क्या चाहिए?')
        : t('Your local shops.<br>Delivered to your door.', 'दुकानों का सामान।<br>सीधे आपके घर।') ?></h1>
    <p class="sub"><?= t(
      'Choose products, send your request, then approve the final price. Delivery in active areas.',
      'सामान चुनिए, माँग भेजिए, फिर अंतिम दाम पक्का कीजिए। डिलीवरी चालू सेवा क्षेत्रों में।') ?></p>

    <form class="hsearch" action="/search.php" method="get">
      <span class="ic"><?= svc_icon('search', 20) ?></span>
      <input type="text" name="q" id="q" placeholder="<?= h(t('What do you need? Atta, medicine, Bolero…', 'क्या चाहिए? आटा, दवाई, बोलेरो…')) ?>" aria-label="<?= h(t('Search', 'खोजिए')) ?>">
      <button class="go" type="submit"><?= t('Search', 'खोजिए') ?></button>
    </form>

    <div class="home-main-actions"><a class="btn btn-gold" href="/bazaar.php"><?= t('Start shopping','सामान चुनिए') ?></a><a class="btn btn-line" href="/order.php#pata"><?= t('Send a list / photo','लिस्ट / फोटो भेजिए') ?></a></div>

  </div>
</section>

<div class="wrap">
    <div class="discovery-controls">
      <span><?= t('Shop by category', 'क्या मँगाना है?') ?></span>
      <button type="button" class="rail-toggle" data-rail-toggle="shop-category-rail" aria-pressed="false" data-paused="<?= h(t('Play', 'चलाएँ')) ?>" data-playing="<?= h(t('Pause', 'रोकें')) ?>"><?= t('Play', 'चलाएँ') ?></button>
    </div>
    <nav class="shop-category-rail" id="shop-category-rail" aria-label="<?= h(t('Shop categories', 'दुकान की categories')) ?>">
      <?php foreach ([['Grocery / Kirana Store','grocery','Grocery','राशन'],['Sweet Shop','food','Sweets & snacks','मिठाई और नाश्ता'],['Medical / Pharmacy','medicine','Medicines','दवाइयाँ'],['Clothing / Garments Shop','shops','Clothing','कपड़े'],['Mobile Store','mobile','Mobiles','मोबाइल'],['Hardware Shop','tools','Hardware','हार्डवेयर'],['Kitchenware / Utensils Shop','shops','Kitchen','रसोई'],['Paint Store','home','Paint','पेंट']] as [$kind,$icon,$en,$hi]): ?>
        <a href="<?= h(catalog_url(['type'=>$kind])) ?>"><?= svc_icon($icon,26) ?><span><?= h($en) ?></span><?php if(is_hi()): ?><small><?= h($hi) ?></small><?php endif; ?></a>
      <?php endforeach; ?>
      <a href="/bazaar.php"><?= svc_icon('all',24) ?><span><?= t('All categories', 'सभी categories') ?></span></a>
    </nav>

  <div class="home-order-steps"><span>1 · <?= t('Choose products','सामान चुनिए') ?></span><span>2 · <?= t('Send request','माँग भेजिए') ?></span><span>3 · <?= t('Approve final price','अंतिम दाम पक्का कीजिए') ?></span></div>
  <?php foreach ($discovery as $kind=>$products): ?>
    <div class="discovery-section">
      <div class="discovery-heading">
        <h2><?= h(catalog_label($kind)) ?></h2>
        <a href="<?= h(catalog_url(['type'=>$kind])) ?>"><?= t('See all →', 'सब देखिए →') ?></a>
      </div>
      <p class="help"><?= t('Explore products. Each shop confirms its price, pack and availability.', 'सामान देखिए। दाम, पैक और उपलब्धता दुकान से पक्के होंगे।') ?></p>
      <div class="product-discovery-rail search-rail" tabindex="0" role="region" aria-label="<?= h(catalog_label($kind)) ?>">
        <?php foreach ($products as $product):
          $offers=array_values(array_filter($discovery_offers[(int)$product['id']] ?? [],fn($o)=>$o['stock']==='hai'));
          $offer=$offers[0] ?? null; ?>
          <a class="discovery-product" href="<?= h(catalog_url(['product'=>(int)$product['id']])) ?>">
            <span class="discovery-picture"><?php if ($offer && $offer['photo']): ?><img src="/uploads/<?= h($offer['photo']) ?>" alt="<?= h($offer['name']) ?>" loading="lazy"><?php else: ?><?= catalog_product_icon($product,38) ?><?php endif; ?></span>
            <b><?= h(t($product['name_en'],$product['name_hi'] ?: $product['name_en'])) ?></b>
            <?php if ($offer): ?>
              <span class="meta">₹<?= h(rtrim(rtrim(number_format((float)$offer['price'],2,'.',''),'0'),'.')) ?> · <?= h($offer['unit']) ?><br><?= h($offer['shop_name']) ?></span>
            <?php else: ?>
              <span class="meta"><?= h(catalog_reference_summary($product) ?: t('Price to be confirmed', 'दाम पूछकर पक्के होंगे')) ?></span>
            <?php endif; ?>
            <span class="discovery-action"><?= t('Choose pack →', 'पैक चुनिए →') ?></span>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endforeach; ?>
  <a class="allsvc" href="/bazaar.php"><?= svc_icon('all',20) ?><span><?= t('Browse all shop categories & products', 'सभी दुकान categories और सामान देखिए') ?></span> →</a>

  <nav class="journeys" aria-label="<?= h(t('Other services','दूसरी सेवाएँ')) ?>">
    <a href="/sewa.php"><?= svc_icon('ride',28) ?><b><?= t('Vehicles & services','गाड़ी और सेवाएँ') ?></b><span><?= t('Book your requirement','अपनी जरूरत बुक कीजिए') ?></span></a>
    <a href="/directory.php"><?= svc_icon('shops',28) ?><b><?= t('Find a shop','दुकान खोजिए') ?></b><span><?= t('Shops and local professionals','दुकानें और कारीगर') ?></span></a>
    <a href="/books.php"><?= svc_icon('book',28) ?><b><?= t('Old books','पुरानी किताबें') ?></b><span><?= t('Buy, sell, exchange','खरीदिए, बेचिए, बदलिए') ?></span></a>
  </nav>
  <div class="install" id="installBox">
    <span class="ic"><?= svc_icon('box', 30) ?></span>
    <span><b><?= t('Keep Maakit on your phone', 'Maakit को फ़ोन में रख लीजिए') ?></b>
      <i><?= t('Adds to your home screen — like an app, no Play Store', 'होम स्क्रीन पर आ जाएगा — ऐप की तरह, बिना Play Store') ?></i></span>
    <button class="btn btn-sm btn-brand" id="installYes"><?= t('Add', 'रख लीजिए') ?></button>
    <button class="btn btn-sm" id="installNo" style="background:transparent;color:var(--muted);padding:8px"><?= t('Not now', 'अभी नहीं') ?></button>
  </div>


  <!-- ============ dukandar ke liye ============ -->
  <a class="dknyota" href="/register-business.php">
    <span class="ic"><?= svc_icon('shops', 26) ?></span>
    <span class="tx">
      <b><?= t('Are you a shopkeeper?', 'आप दुकानदार हैं?') ?></b>
      <i><?= t('Put your shop on Maakit — your items, your prices, your orders and your daily accounts, all on your phone. Free.',
               'अपनी दुकान Maakit पर रखिए — अपना सामान, अपना दाम, अपने ऑर्डर और अपना हिसाब, सब अपने फ़ोन में। मुफ़्त।') ?></i>
    </span>
    <span class="go"><?= t('Register your shop', 'दुकान रजिस्टर कीजिए') ?> <?= svc_icon('plus', 14) ?></span>
  </a>

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

<section class="home-help"><div class="wrap">
  <h2><?= t('Clear prices. One request.','साफ़ दाम। एक माँग।') ?></h2>
  <p><?= t('Available prices show the matching pack size. Maakit confirms the final goods and delivery total before purchase. Goods payment goes directly to the shop.','जहाँ दाम उपलब्ध हैं, साथ में उसी पैक का नाप देखें। खरीदने से पहले सामान और डिलीवरी का अंतिम कुल आपसे पक्का होगा। सामान का भुगतान सीधे दुकान को होगा।') ?></p>
  <div class="chips"><a class="chip" href="/sewa.php"><?= t('Vehicles & services','गाड़ी और सेवाएँ') ?></a><a class="chip" href="/directory.php"><?= t('Find a shop','दुकान खोजिए') ?></a><a class="chip" href="/area.php"><?= t('Check delivery area','डिलीवरी क्षेत्र जाँचिए') ?></a><a class="chip" href="tel:<?= h(MAAKIT_PHONE) ?>"><?= t('Need help? Call','मदद चाहिए? कॉल कीजिए') ?></a></div>
</div></section>
</main>

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
<script src="/assets/catalogue-discovery.js" defer></script>
<script src="/assets/search-sliders.js?v=1" defer></script>
<?php include __DIR__ . '/inc/foot.php'; ?>

