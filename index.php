<?php
require_once __DIR__ . '/inc/fn.php';
require_once __DIR__ . '/inc/salon.php';
require_once __DIR__ . '/inc/items.php';
require_once __DIR__ . '/inc/icons.php';
require_once __DIR__ . '/inc/services.php';
require_once __DIR__ . '/inc/customer.php';
require_once __DIR__ . '/inc/books.php';
require_once __DIR__ . '/inc/catalog.php';
require_once __DIR__.'/inc/catalog-photos.php';

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
function mk_price($n) { return rtrim(rtrim(number_format((float)$n, 2, '.', ''), '0'), '.'); }
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

  <ul class="mk-trust" aria-label="<?= h(t('Why Maakit', 'Maakit क्यों')) ?>">
    <li><?= svc_icon('shops', 18) ?><?= t('Shop price, no markup', 'दुकान का ही दाम') ?></li>
    <li><?= svc_icon('box', 18) ?><?= t('Delivery to your door', 'घर तक डिलीवरी') ?></li>
    <li><?= svc_icon('user', 18) ?><?= t('Local team on call', 'फ़ोन पर मदद') ?></li>
  </ul>

  <section class="mk-sec">
    <div class="mk-sec-head"><h2><?= t('Shop by category', 'श्रेणी चुनिए') ?></h2><a href="/bazaar.php"><?= t('See all', 'सब देखिए') ?></a></div>
    <nav class="mk-cats" aria-label="<?= h(t('Categories', 'श्रेणियाँ')) ?>">
      <?php foreach ([['Grocery / Kirana Store','grocery','Grocery','राशन'],['Sweet Shop','food','Sweets','मिठाई'],['Medical / Pharmacy','medicine','Medicines','दवाई'],['Clothing / Garments Shop','shops','Clothing','कपड़े'],['Mobile Store','mobile','Mobiles','मोबाइल'],['Hardware Shop','tools','Hardware','हार्डवेयर'],['Kitchenware / Utensils Shop','thali','Kitchen','रसोई'],['Paint Store','home','Paint','पेंट']] as [$kind,$icon,$en,$hi]): ?>
        <a href="<?= h(catalog_url(['type'=>$kind])) ?>"><span class="mk-cat-ic"><?= svc_icon($icon,30) ?></span><span><?= h(t($en,$hi)) ?></span></a>
      <?php endforeach; ?>
    </nav>
  </section>

  <?php foreach ($discovery as $kind=>$products): ?>
  <section class="mk-sec">
    <div class="mk-sec-head"><h2><?= h(catalog_label($kind)) ?></h2><a href="<?= h(catalog_url(['type'=>$kind])) ?>"><?= t('See all', 'सब देखिए') ?></a></div>
    <div class="mk-rail">
      <?php foreach ($products as $product):
        $offers=array_values(array_filter($discovery_offers[(int)$product['id']] ?? [],fn($o)=>$o['stock']==='hai'));
        $offer=$offers[0] ?? null;
        $pics=$discovery_photos[(int)$product['id']] ?? [];
        $cover=$pics[0] ?? ($offer['photo'] ?? null);
        $ref=$offer ? null : catalog_reference_price($product);
        $pname=t($product['name_en'],$product['name_hi'] ?: $product['name_en']);
        $unit=$offer['unit'] ?? ($ref['packs'][0]['unit'] ?? $product['unit_hint']); ?>
        <a class="mk-pc" href="<?= h(catalog_url(['product'=>(int)$product['id']])) ?>">
          <span class="mk-pc-img"><?php if ($cover): ?><img src="/uploads/<?= h($cover) ?>" alt="" loading="lazy"><?php else: ?><?= catalog_product_icon($product,34) ?><?php endif; ?></span>
          <?php if ($offer): ?><span class="mk-tag ok"><?= t('In stock','उपलब्ध') ?></span>
          <?php elseif ($ref): ?><span class="mk-tag"><?= t('Market price','बाज़ार दाम') ?></span>
          <?php else: ?><span class="mk-tag"><?= t('Ask price','दाम पूछिए') ?></span><?php endif; ?>
          <b class="mk-pc-name"><?= h($pname) ?></b>
          <span class="mk-pc-unit"><?= h($unit) ?></span>
          <span class="mk-pc-foot">
            <span class="mk-pc-price"><?php if ($offer): ?>₹<?= h(mk_price($offer['price'])) ?><?php elseif ($ref && !empty($ref['packs'][0])): ?>₹<?= h(mk_price($ref['packs'][0]['price'])) ?><?php else: ?>—<?php endif; ?></span>
            <span class="mk-add"><?= t('ADD', 'जोड़ें') ?></span>
          </span>
        </a>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endforeach; ?>

  <section class="mk-sec">
    <div class="mk-sec-head"><h2><?= t('More from Maakit', 'Maakit की और सेवाएँ') ?></h2></div>
    <div class="mk-svcs">
      <a href="/sewa.php"><span class="mk-cat-ic"><?= svc_icon('ride',26) ?></span><b><?= t('Book a vehicle','गाड़ी बुक करें') ?></b><small><?= t('Bolero, tempo, tent & more','बोलेरो, टेम्पो, टेंट') ?></small></a>
      <a href="/order.php#pata"><span class="mk-cat-ic"><?= svc_icon('box',26) ?></span><b><?= t('Send a list','लिस्ट भेजें') ?></b><small><?= t('Photo or written list','फ़ोटो या लिखी लिस्ट') ?></small></a>
      <a href="/directory.php"><span class="mk-cat-ic"><?= svc_icon('shops',26) ?></span><b><?= t('Find a shop','दुकान खोजें') ?></b><small><?= t('Shops & local pros','दुकानें और कारीगर') ?></small></a>
      <a href="/books.php"><span class="mk-cat-ic"><?= svc_icon('book',26) ?></span><b><?= t('Old books','पुरानी किताबें') ?></b><small><?= t('Buy, sell, exchange','खरीदें, बेचें') ?></small></a>
    </div>
  </section>

  <?php if ($banners): ?>
  <section class="mk-sec">
    <div class="mk-sec-head"><h2><?= t('Offers', 'ऑफ़र') ?></h2></div>
    <div class="mk-offers" id="bann">
      <?php foreach ($banners as $b): $ti = is_hi() ? $b['title_hi'] : $b['title_en']; $su = is_hi() ? $b['sub_hi'] : $b['sub_en']; ?>
        <a class="mk-offer tone-<?= h($b['tone']) ?>" href="<?= h($b['link'] ?: '/order.php') ?>" data-bid="<?= (int)$b['id'] ?>">
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
