<?php
require_once __DIR__ . '/inc/fn.php';
require_once __DIR__ . '/inc/items.php';
require_once __DIR__ . '/inc/services.php';
require_once __DIR__ . '/inc/search.php';
require_once __DIR__ . '/inc/catalog.php';

$q = isset($_GET['q']) && is_string($_GET['q']) ? mb_substr(trim($_GET['q']), 0, 100) : '';
$request_design = true;
$page_title = t('Search — Maakit', 'खोजिए — Maakit');
$tab = 'kaam';
$products = $bookings = $shops = $matched_categories = [];
$catalog_matches = [];
$catalog_prices = [];
$catalog_types = [];
foreach (catalog_meta() as $type => $meta) {
    if ($q !== '' && market_matches($q, $type.' '.($meta['hi'] ?? '').' '.catalog_picker_aliases($type))) $catalog_types[] = $type;
}
if ($q !== '') {
    try { $catalog_matches = catalog_filter(catalog_load($pdo), '', '', '', $q); }
    catch (PDOException $e) { error_log('Maakit search catalog: ' . $e->getMessage()); }
    try { $catalog_prices=catalog_offers($pdo,array_column(array_slice($catalog_matches,0,12),'id'),coverage_selected($pdo)); }
    catch (PDOException $e) { error_log('Maakit search prices: '.$e->getMessage()); }
    foreach (items_all($pdo) as $item) {
        if (market_matches($q, $item['name'] . ' ' . $item['words'] . ' ' . $item['unit'])) $products[] = $item;
    }
    foreach (services() as $slug => $svc) {
        if (market_matches($q, market_service_text($slug, $svc, categories()))) $bookings[$slug] = $svc;
    }
    foreach (categories() as $cat) {
        if (market_matches($q, $cat['name'] . ' ' . $cat['words'])) $matched_categories[] = $cat['slug'];
    }
    $like = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $q) . '%';
    $sql = "SELECT id,name,category,village,work FROM businesses WHERE status='approved' AND (name LIKE ? ESCAPE '!' OR work LIKE ? ESCAPE '!' OR about LIKE ? ESCAPE '!' OR village LIKE ? ESCAPE '!'";
    $args = [$like, $like, $like, $like];
    if ($matched_categories) {
        $sql .= ' OR category IN (' . implode(',', array_fill(0, count($matched_categories), '?')) . ')';
        array_push($args, ...$matched_categories);
    }
    $sql.=')';
    if($area=coverage_selected($pdo)){$sql.=' AND EXISTS (SELECT 1 FROM service_area_shops a WHERE a.business_id=businesses.id AND a.village_id=?)';$args[]=(int)$area['id'];}
    $st = $pdo->prepare($sql . ' ORDER BY id DESC LIMIT 12');
    $st->execute($args);
    $shops = $st->fetchAll();

    // ---- kya dhoondha gaya, wo bahi me likh dijiye ----
    // मुनीम isi se batata hai ki log kya maang rahe hain jo hamare
    // paas nahi hai. Pehle ye order.php se likha jata tha; ab hero
    // ki khoj yahan aati hai, isliye yahan bhi likhna zaroori hai.
    $sq = mb_strtolower(trim($q));
    if (mb_strlen($sq) >= 2 && mb_strlen($sq) <= 60) {
        $_SESSION['sl'] = (int)($_SESSION['sl'] ?? 0);
        if ($_SESSION['sl'] < 40) {                 // ek session me 40 se jyada nahi
            $_SESSION['sl']++;
            try {
                $pdo->prepare("INSERT INTO search_log (q, hits, times) VALUES (?,?,1)
                               ON DUPLICATE KEY UPDATE times = times + 1, hits = VALUES(hits)")
                    ->execute([$sq, count($products) + count($bookings) + count($shops) + count($catalog_matches)]);
            } catch (Throwable $e) { /* bahi na likhe to khoj rukni nahi chahiye */ }
        }
    }
}
include __DIR__ . '/inc/head.php';
?>
<section class="search-page" data-previous="<?= h(t('Previous cards','पिछले कार्ड')) ?>" data-next="<?= h(t('Next cards','अगले कार्ड')) ?>"><div class="wrap">
  <h1><?= t('What do you need?', 'क्या चाहिए आपको?') ?></h1>
  <form class="searchbox bazaar-find" action="/search.php" method="get">
    <input type="hidden" name="lang" value="<?= h(lang()) ?>">
    <input name="q" list="search-category-hints" maxlength="100" value="<?= h($q) ?>" placeholder="<?= h(t('Atta, Bolero, plumber, tent…', 'आटा, बोलेरो, मिस्त्री, टेंट…')) ?>" aria-label="<?= h(t('Search products, services and shops', 'सामान, सेवाएँ और दुकानें खोजिए')) ?>">
    <button class="btn btn-brand btn-sm" type="submit"><?= t('Search', 'खोजिए') ?></button>
  </form>
  <datalist id="search-category-hints">
    <?php foreach (catalog_meta() as $type => $meta): ?>
      <option value="<?= h(catalog_label($type)) ?>"><?= h($type) ?></option>
    <?php endforeach; ?>
  </datalist>
  <?php if ($catalog_types): ?>
    <nav class="chips search-category-strip" aria-label="<?= h(t('Matching shop categories','मिलती दुकान categories')) ?>">
      <?php foreach (array_slice($catalog_types,0,12) as $type): ?>
        <a class="chip" href="<?= h(catalog_url(['type'=>$type])) ?>"><?= h(catalog_label($type)) ?></a>
      <?php endforeach; ?>
    </nav>
  <?php endif; ?>
  <?php if ($q === ''): ?>
    <p class="lead"><?= t('Search in Hindi or English for goods, a booking or a local shop.', 'सामान, बुकिंग या स्थानीय दुकान के लिए हिंदी या English में खोजिए।') ?></p>
  <?php else: ?>
    <p class="search-query"><?= t('Results for: ', 'खोज: ') ?><strong><?= h($q) ?></strong></p>
    <?php if ($catalog_matches): ?>
      <div class="search-section">
        <h2><?= t('Products & prices', 'सामान और दाम') ?></h2>
        <p class="help"><?= count($catalog_matches) ?> <?= t('matches · swipe to browse. Final price confirmed by the shop.', 'नतीजे · आगे सरकाकर देखिए। अंतिम दाम दुकान से पक्का होगा।') ?></p>
        <div class="product-discovery-rail search-rail" tabindex="0" role="region" aria-label="<?= h(t('Products and prices','सामान और दाम')) ?>">
          <?php foreach (array_slice($catalog_matches,0,12) as $match): ?>
            <a class="discovery-product" href="<?= h(catalog_url(['product'=>(int)$match['id']])) ?>">
              <span class="discovery-picture"><?= catalog_product_icon($match,34) ?></span>
              <b><?= h(t($match['name_en'],$match['name_hi'] ?: $match['name_en'])) ?></b>
              <span class="meta"><?= h(catalog_label($match['shop_type'])) ?></span>
              <?php if ($shop_price=$catalog_prices[(int)$match['id']][0] ?? null): ?>
                <span class="meta">₹<?= (int)$shop_price['price'] ?> / <?= h($shop_price['unit']) ?> · <?= h($shop_price['shop_name']) ?><?= $shop_price['stock']==='khatam' ? ' · '.t('Out of stock','स्टॉक नहीं है') : '' ?></span>
              <?php elseif ($reference_summary=catalog_reference_summary($match)): ?><span class="meta"><?= h($reference_summary) ?></span><?php endif; ?>
              <span class="discovery-action"><?= t('View & request →', 'देखिए और मँगाइए →') ?></span>
            </a>
          <?php endforeach; ?>
        </div>
        <a class="btn btn-brand btn-sm" href="<?= h(catalog_url(['q' => $q])) ?>"><?= t('See categories, products & prices', 'Categories, सामान और दाम देखिए') ?></a>
      </div>
    <?php endif; ?>
    <?php if ($products): ?><div class="search-section">
      <h2><?= t('More everyday essentials', 'रोज़मर्रा का और सामान') ?></h2>
      <p class="help"><?= t('Goods are charged at the shop’s price; delivery is separate.', 'सामान का दाम दुकान वाला होगा; डिलीवरी चार्ज अलग है।') ?></p>
      <div class="search-rail" tabindex="0" role="region" aria-label="<?= h(t('Everyday essentials','रोज़मर्रा का सामान')) ?>">
      <?php foreach (array_slice($products, 0, 12) as $item): ?>
        <a class="search-result-card" href="/order.php?q=<?= h(rawurlencode($item['name'])) ?>">
          <span class="discovery-picture"><?= !empty($item['photo']) ? '<img src="/uploads/' . h($item['photo']) . '" alt="" loading="lazy">' : prod_icon($item['name'], $item['grp'], 30) ?></span>
          <b><?= h($item['name']) ?></b><span class="meta"><?= h($item['unit']) ?></span><span class="discovery-action"><?= t('View & request →','देखिए और मँगाइए →') ?></span>
        </a>
      <?php endforeach; ?>
      </div>
      <a class="btn btn-brand btn-sm" href="/order.php?q=<?= h(rawurlencode($q)) ?>"><?= t('See items and order', 'सामान देखिए और ऑर्डर कीजिए') ?></a>
      </div>
    <?php endif; ?>
    <?php if ($bookings): ?><div class="search-section">
      <h2><?= t('Book a vehicle or service', 'गाड़ी या सेवा बुक कीजिए') ?></h2>
      <p class="help"><?= t('Send your requirement; Maakit confirms the rate and availability.', 'अपनी जरूरत भेजिए; Maakit रेट और उपलब्धता पक्का करके बताएगा।') ?></p>
      <div class="search-rail" tabindex="0" role="region" aria-label="<?= h(t('Vehicles and services','गाड़ी और सेवाएँ')) ?>">
      <?php foreach ($bookings as $slug => $svc): ?>
        <a class="search-result-card" href="/sewa.php?s=<?= h($slug) ?>"><span class="discovery-picture"><?= svc_icon($svc['icon'], 30) ?></span><b><?= h(t($svc['en'], $svc['name'])) ?></b><span class="meta"><?= h($svc['tag']) ?></span><span class="discovery-action"><?= t('View service →','सेवा देखिए →') ?></span></a>
      <?php endforeach; ?>
      </div></div>
    <?php endif; ?>
    <?php if ($shops || $matched_categories): ?><div class="search-section">
      <h2><?= t('Local shops and workers', 'स्थानीय दुकानें और कारीगर') ?></h2>
      <div class="search-rail" tabindex="0" role="region" aria-label="<?= h(t('Local shops','स्थानीय दुकानें')) ?>">
      <?php foreach ($shops as $shop): ?>
        <a class="search-result-card" href="/business.php?id=<?= (int)$shop['id'] ?>"><b><?= h($shop['name']) ?></b><span class="meta"><?= h($shop['village']) ?></span><span class="meta"><?= h($shop['work']) ?></span><span class="discovery-action"><?= t('View shop →','दुकान देखिए →') ?></span></a>
      <?php endforeach; ?>
      </div>
      <a class="btn btn-sm" href="/directory.php?q=<?= h(rawurlencode($q)) ?>"><?= t('Browse the local directory', 'स्थानीय डायरेक्टरी देखिए') ?></a>
      </div>
    <?php endif; ?>
    <?php if (!$products && !$bookings && !$shops && !$matched_categories && !$catalog_matches): ?>
      <div class="box"><p><?= t('Nothing listed yet. You can still send your requirement.', 'अभी लिस्ट में नहीं मिला। फिर भी अपनी जरूरत भेज सकते हैं।') ?></p></div>
    <?php endif; ?>
  <?php endif; ?>
  <details class="search-more"><summary><?= t('Other ways to order','मँगाने के और तरीके') ?></summary><div class="chips">
    <a class="chip" href="/order.php"><?= t('Send a list or photo', 'लिस्ट या फोटो भेजिए') ?></a>
    <a class="chip" href="/sewa.php"><?= t('All bookings', 'सभी बुकिंग') ?></a>
    <a class="chip" href="/bazaar.php"><?= t('Shop categories & prices', 'दुकान की categories और दाम') ?></a>
    <a class="chip" href="/directory.php"><?= t('All local shops', 'सभी स्थानीय दुकानें') ?></a>
    <a class="chip" href="/area.php"><?= t('Request Maakit in your area', 'अपने इलाके में Maakit माँगिए') ?></a>
  </div>
</details></div></section>
<script src="/assets/search-sliders.js?v=1" defer></script>
<?php include __DIR__ . '/inc/foot.php'; ?>
