<?php
require_once __DIR__ . '/inc/fn.php';
require_once __DIR__ . '/inc/catalog.php';

function bazaar_get($key, $max = 100) {
    return isset($_GET[$key]) && is_string($_GET[$key]) ? mb_substr(trim($_GET[$key]), 0, $max) : '';
}
$group = bazaar_get('group');
$type = bazaar_get('type');
$sub = bazaar_get('sub');
$q = bazaar_get('q');
$groups = catalog_groups();
if ($group !== '' && !isset($groups[$group])) $group = '';
$ready = true;
try { $all = catalog_load($pdo); } catch (PDOException $e) { $all = []; $ready = false; error_log('Maakit catalog: ' . $e->getMessage()); }
$types = [];
foreach (catalog_filter($all, $group, '', '', '') as $item) {
    $key = $item['shop_type'];
    if (!isset($types[$key])) $types[$key] = ['count' => 0, 'hi' => $item['type_hi']];
    $types[$key]['count']++;
}
if ($type !== '' && !isset($types[$type])) $type = '';
if ($type === '') $sub = '';
$subs = [];
if ($type !== '') foreach (catalog_filter($all, $group, $type, '', '') as $item) {
    if (!empty($item['sub_cat'])) $subs[$item['sub_cat']] = ($subs[$item['sub_cat']] ?? 0) + 1;
}
if ($sub !== '' && !isset($subs[$sub])) $sub = '';
$matches = catalog_filter($all, $group, $type, $sub, $q);
$show_items = $type !== '' || $q !== '';
$per = 30;
$pages = max(1, (int)ceil(count($matches) / $per));
$page = min($pages, max(1, (int)bazaar_get('p', 8)));
$items = $show_items ? array_slice($matches, ($page - 1) * $per, $per) : [];
$offers = [];
$prices_ready = true;
try { $offers = catalog_offers($pdo, array_column($items, 'id')); }
catch (PDOException $e) { $prices_ready = false; error_log('Maakit catalog prices: ' . $e->getMessage()); }
$params = ['group' => $group, 'type' => $type, 'sub' => $sub, 'q' => $q];
$page_title = t('Shop categories & prices — Maakit', 'दुकान की categories और दाम — Maakit');
$tab = 'order';
include __DIR__ . '/inc/head.php';
?>
<style>
.bazaar-cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:14px;margin:16px 0}
.bazaar-card{padding:18px;background:var(--cream,var(--soft));border:1px solid var(--line);border-radius:16px;min-width:0;color:inherit;text-decoration:none}
.bazaar-card h3{margin:0 0 8px;overflow-wrap:anywhere}.bazaar-card .meta{display:block;margin:4px 0}
.bazaar-photo{width:76px;height:76px;object-fit:contain;border-radius:10px;float:right;margin:0 0 10px 12px}
.bazaar-offer{border-top:1px solid var(--line);padding:10px 0;margin-top:10px}.bazaar-price{font-weight:700;font-size:20px}
.bazaar-chips{display:flex;gap:8px;overflow:auto;padding:8px 0}.bazaar-chips .chip{flex-shrink:0}
/* .searchbox ko hero ke neeche ghusne ke liye -28px upar khincha gaya
   hai. Yahan hero nahi hai, isliye wo upar wale likhe par chadh jata
   tha. Is panne par usko seedha rakhiye. */
.bazaar-find{margin-top:14px}
</style>
<section><div class="wrap">
  <h1><?= t('Shop → products → prices', 'दुकान → सामान → दाम') ?></h1>
  <p class="lead"><?= t('Choose a shop type, then see its products and local shop prices.', 'दुकान का प्रकार चुनिए, फिर उसका सामान और स्थानीय दुकान के दाम देखिए।') ?></p>
  <p class="help"><?= t('This is a catalogue of possible products and services. Stock, pack sizes and prices depend on each shop. Delivery is charged separately.', 'यह सामान और सेवाओं की master list है। उपलब्धता, पैक का नाप और दाम हर दुकान के अपने हैं। डिलीवरी चार्ज अलग है।') ?></p>
  <form class="searchbox bazaar-find" action="/bazaar.php" method="get">
    <?php foreach (['group' => $group, 'type' => $type] as $k => $v): if ($v !== ''): ?>
      <input type="hidden" name="<?= h($k) ?>" value="<?= h($v) ?>">
    <?php endif; endforeach; ?>
    <input name="q" value="<?= h($q) ?>" maxlength="100" aria-label="<?= h(t('Search catalogue', 'सामान और दुकान खोजिए')) ?>" placeholder="<?= h(t('Shop or product: kirana, atta, hardware…', 'दुकान या सामान: किराना, आटा, हार्डवेयर…')) ?>">
    <button class="btn btn-brand btn-sm"><?= t('Search', 'खोजिए') ?></button>
  </form>
  <nav class="bazaar-chips" aria-label="<?= h(t('Main categories', 'मुख्य categories')) ?>">
    <a class="chip <?= $group === '' ? 'on' : '' ?>" href="/bazaar.php"><?= t('All', 'सभी') ?></a>
    <?php foreach ($groups as $slug => $label): ?>
      <a class="chip <?= $slug === $group ? 'on' : '' ?>" href="<?= h(catalog_url(['group' => $slug])) ?>"><?= h(t($label[0], $label[1])) ?></a>
    <?php endforeach; ?>
  </nav>
  <?php if (!$ready): ?>
    <div class="box"><?= t('The shop catalogue is being set up. You can send your list meanwhile.', 'दुकानों की सूची अभी तैयार हो रही है। तब तक अपनी लिस्ट भेज सकते हैं।') ?></div>
  <?php elseif (!$show_items): ?>
    <p class="meta"><?= count($types) ?> <?= t('shop types', 'तरह की दुकानें') ?> · <?= count($matches) ?> <?= t('catalogue entries', 'सामान/सेवा entries') ?></p>
    <div class="bazaar-cards">
      <?php foreach ($types as $slug => $info): ?>
        <a class="bazaar-card" href="<?= h(catalog_url(['group' => $group, 'type' => $slug])) ?>">
          <h3><?= h($slug) ?></h3>
          <span class="meta"><?= h(catalog_meta()[$slug]['hi'] ?? $info['hi']) ?></span>
          <span class="meta"><?= $info['count'] ?> <?= t('products / services', 'सामान / सेवाएँ') ?></span>
          <b><?= t('View products and prices →', 'सामान और दाम देखिए →') ?></b>
        </a>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <a class="chip" href="<?= h(catalog_url(['group' => $group])) ?>">← <?= t('Shop types', 'दुकान के प्रकार') ?></a>
    <h2><?= $type !== '' ? h(catalog_label($type, $types[$type]['hi'])) : h(t('Catalogue results', 'सामान की खोज')) ?></h2>
    <?php if ($subs): ?>
      <nav class="bazaar-chips" aria-label="<?= h(t('Product subcategories', 'सामान की subcategories')) ?>">
        <a class="chip <?= $sub === '' ? 'on' : '' ?>" href="<?= h(catalog_url(array_merge($params, ['sub' => '']))) ?>"><?= t('All products', 'सभी सामान') ?></a>
        <?php foreach ($subs as $name => $count): ?>
          <a class="chip <?= $sub === $name ? 'on' : '' ?>" href="<?= h(catalog_url(array_merge($params, ['sub' => $name]))) ?>"><?= h(catalog_sub_label($name)) ?> (<?= $count ?>)</a>
        <?php endforeach; ?>
      </nav>
    <?php endif; ?>
    <p class="meta"><?= count($matches) ?> <?= t('results', 'नतीजे') ?></p>
    <?php if (!$prices_ready): ?><p class="help"><?= t('Shop prices are temporarily unavailable. Please ask Maakit.', 'दुकान के दाम अभी नहीं दिख पा रहे हैं। Maakit से पूछिए।') ?></p><?php endif; ?>
    <?php if (!$matches): ?><div class="box"><?= t('No match. Try another name or send your requirement.', 'नहीं मिला। दूसरा नाम खोजिए या अपनी जरूरत भेजिए।') ?></div><?php endif; ?>
    <div class="bazaar-cards">
    <?php foreach ($items as $item): $list = $offers[(int)$item['id']] ?? []; ?>
      <article class="bazaar-card">
        <h3><?= h(t($item['name_en'], $item['name_hi'] ?: $item['name_en'])) ?></h3>
        <span class="meta"><?= h(catalog_label($item['shop_type'], $item['type_hi'])) ?></span>
        <span class="meta"><?= h(catalog_sub_label($item['sub_cat'])) ?> · <?= h($item['unit_hint']) ?><?= !empty($item['is_sewa']) ? ' · ' . t('Service', 'सेवा') : '' ?></span>
        <?php foreach ($list as $offer): ?>
          <div class="bazaar-offer">
            <?php if ($offer['photo']): ?><img class="bazaar-photo" src="/uploads/<?= h($offer['photo']) ?>" alt="<?= h($offer['name']) ?>" loading="lazy"><?php endif; ?>
            <span class="bazaar-price">₹<?= (int)$offer['price'] ?></span> / <?= h($offer['unit']) ?>
            <span class="meta"><?= h($offer['shop_name']) ?> · <?= h($offer['village']) ?></span>
            <?php if ($offer['stock'] !== 'hai'): ?><span class="meta"><?= t('Out of stock', 'अभी स्टॉक नहीं है') ?></span>
            <?php elseif (!$offer['open_now']): ?><span class="meta"><?= t('Shop closed', 'दुकान अभी बंद है') ?></span>
            <?php endif; ?>
            <a class="btn btn-sm" href="/business.php?id=<?= (int)$offer['business_id'] ?>"><?= t('View shop', 'दुकान देखिए') ?></a>
            <?php if ($offer['stock'] === 'hai' && $offer['open_now'] && empty($item['is_sewa'])): ?>
              <a class="btn btn-brand btn-sm" href="/dukan-se.php?id=<?= (int)$offer['business_id'] ?>"><?= t('Order from shop', 'दुकान से मँगाइए') ?></a>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
        <?php if (!$list): ?><p class="help"><?= t('Shop price not added yet — ask for price and availability.', 'दुकान का दाम अभी नहीं जुड़ा — दाम और उपलब्धता पूछिए।') ?></p><?php endif; ?>
        <a class="btn btn-green btn-sm" href="<?= h(wa_link(MAAKIT_WA, 'Maakit: ' . $item['name_en'] . ' / ' . ($item['name_hi'] ?? '') . ' (' . $item['shop_type'] . ') — दाम और उपलब्धता बताइए।')) ?>"><?= t('Ask Maakit', 'Maakit से पूछिए') ?></a>
      </article>
    <?php endforeach; ?>
    </div>
    <?php if ($pages > 1): ?>
      <nav class="chips" aria-label="<?= h(t('Pages', 'पन्ने')) ?>">
        <?php if ($page > 1): ?><a class="chip" href="<?= h(catalog_url(array_merge($params, ['p' => $page - 1]))) ?>">← <?= t('Previous', 'पिछला') ?></a><?php endif; ?>
        <span class="chip"><?= $page ?> / <?= $pages ?></span>
        <?php if ($page < $pages): ?><a class="chip" href="<?= h(catalog_url(array_merge($params, ['p' => $page + 1]))) ?>"><?= t('Next', 'अगला') ?> →</a><?php endif; ?>
      </nav>
    <?php endif; ?>
  <?php endif; ?>
  <div class="chips" style="margin-top:20px">
    <a class="chip" href="/order.php"><?= t('Send a list / photo', 'लिस्ट / फोटो भेजिए') ?></a>
    <a class="chip" href="/sewa.php"><?= t('Vehicle & service booking', 'गाड़ी और सेवा booking') ?></a>
    <a class="chip" href="/shop.php?tab=saaman"><?= t('Shopkeeper: add your prices', 'दुकानदार: अपने दाम भरिए') ?></a>
  </div>
</div></section>
<?php include __DIR__ . '/inc/foot.php'; ?>
