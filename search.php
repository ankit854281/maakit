<?php
require_once __DIR__ . '/inc/fn.php';
require_once __DIR__ . '/inc/items.php';
require_once __DIR__ . '/inc/services.php';
require_once __DIR__ . '/inc/search.php';

$q = isset($_GET['q']) && is_string($_GET['q']) ? mb_substr(trim($_GET['q']), 0, 100) : '';
$page_title = t('Search — Maakit', 'खोजिए — Maakit');
$tab = 'kaam';
$products = $bookings = $shops = $matched_categories = [];
if ($q !== '') {
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
    $st = $pdo->prepare($sql . ') ORDER BY id DESC LIMIT 12');
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
                    ->execute([$sq, count($products) + count($bookings) + count($shops)]);
            } catch (Throwable $e) { /* bahi na likhe to khoj rukni nahi chahiye */ }
        }
    }
}
include __DIR__ . '/inc/head.php';
?>
<section><div class="wrap">
  <h1><?= t('What do you need?', 'क्या चाहिए आपको?') ?></h1>
  <form class="searchbox" action="/search.php" method="get">
    <input name="q" maxlength="100" value="<?= h($q) ?>" placeholder="<?= h(t('Atta, Bolero, plumber, tent…', 'आटा, बोलेरो, मिस्त्री, टेंट…')) ?>" aria-label="<?= h(t('Search products, services and shops', 'सामान, सेवाएँ और दुकानें खोजिए')) ?>">
    <button class="btn btn-brand btn-sm" type="submit"><?= t('Search', 'खोजिए') ?></button>
  </form>
  <?php if ($q === ''): ?>
    <p class="lead"><?= t('Search in Hindi or English for goods, a booking or a local shop.', 'सामान, बुकिंग या स्थानीय दुकान के लिए हिंदी या English में खोजिए।') ?></p>
  <?php else: ?>
    <p class="lead"><?= t('Results for: ', 'खोज: ') . h($q) ?></p>
    <?php if ($products): ?>
      <h2><?= t('Goods delivered from a shop', 'दुकान से सामान मँगाइए') ?></h2>
      <p class="help"><?= t('Goods are charged at the shop’s price; delivery is separate.', 'सामान का दाम दुकान वाला होगा; डिलीवरी चार्ज अलग है।') ?></p>
      <div class="grid g2">
      <?php foreach (array_slice($products, 0, 12) as $item): ?>
        <a class="card biz" href="/order.php?q=<?= h(rawurlencode($item['name'])) ?>">
          <span class="ph"><?= !empty($item['photo']) ? '<img src="/uploads/' . h($item['photo']) . '" alt="" loading="lazy">' : prod_icon($item['name'], $item['grp'], 30) ?></span>
          <span><b><?= h($item['name']) ?></b><span class="meta"><?= h($item['unit']) ?></span></span>
        </a>
      <?php endforeach; ?>
      </div>
      <a class="btn btn-brand btn-sm" href="/order.php?q=<?= h(rawurlencode($q)) ?>"><?= t('See items and order', 'सामान देखिए और ऑर्डर कीजिए') ?></a>
    <?php endif; ?>
    <?php if ($bookings): ?>
      <h2><?= t('Book a vehicle or service', 'गाड़ी या सेवा बुक कीजिए') ?></h2>
      <p class="help"><?= t('Send your requirement; Maakit confirms the rate and availability.', 'अपनी जरूरत भेजिए; Maakit रेट और उपलब्धता पक्का करके बताएगा।') ?></p>
      <div class="grid g2">
      <?php foreach ($bookings as $slug => $svc): ?>
        <a class="card biz" href="/sewa.php?s=<?= h($slug) ?>"><span class="ph"><?= svc_icon($svc['icon'], 30) ?></span><span><b><?= h(t($svc['en'], $svc['name'])) ?></b><span class="meta"><?= h($svc['tag']) ?></span></span></a>
      <?php endforeach; ?>
      </div>
    <?php endif; ?>
    <?php if ($shops || $matched_categories): ?>
      <h2><?= t('Local shops and workers', 'स्थानीय दुकानें और कारीगर') ?></h2>
      <div class="grid g2">
      <?php foreach ($shops as $shop): ?>
        <a class="card biz" href="/business.php?id=<?= (int)$shop['id'] ?>"><span><b><?= h($shop['name']) ?></b><span class="meta"><?= h($shop['village']) ?></span><span class="meta"><?= h($shop['work']) ?></span></span></a>
      <?php endforeach; ?>
      </div>
      <a class="btn btn-sm" href="/directory.php?q=<?= h(rawurlencode($q)) ?>"><?= t('Browse the local directory', 'स्थानीय डायरेक्टरी देखिए') ?></a>
    <?php endif; ?>
    <?php if (!$products && !$bookings && !$shops && !$matched_categories): ?>
      <div class="box"><p><?= t('Nothing listed yet. You can still send your requirement.', 'अभी लिस्ट में नहीं मिला। फिर भी अपनी जरूरत भेज सकते हैं।') ?></p></div>
    <?php endif; ?>
  <?php endif; ?>
  <div class="chips" style="margin-top:20px">
    <a class="chip" href="/order.php"><?= t('Send a list or photo', 'लिस्ट या फोटो भेजिए') ?></a>
    <a class="chip" href="/sewa.php"><?= t('All bookings', 'सभी बुकिंग') ?></a>
    <a class="chip" href="/directory.php"><?= t('All local shops', 'सभी स्थानीय दुकानें') ?></a>
    <a class="chip" href="/area.php"><?= t('Request Maakit in your village', 'अपने गाँव में Maakit माँगिए') ?></a>
  </div>
</div></section>
<?php include __DIR__ . '/inc/foot.php'; ?>
