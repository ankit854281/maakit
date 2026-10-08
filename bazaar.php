<?php
require_once __DIR__ . '/inc/fn.php';
require_once __DIR__ . '/inc/catalog.php';
require_once __DIR__ . '/inc/catalog-request.php';

function bazaar_get($key, $max = 100) {
    return isset($_GET[$key]) && is_string($_GET[$key]) ? mb_substr(trim($_GET[$key]), 0, $max) : '';
}
if ($_SERVER['REQUEST_METHOD']==='POST' && csrf_ok()) {
    foreach(['request_action','catalog_id','qty','pack','urgent','request_key','checkout'] as $field){if(isset($_POST[$field]) && !is_string($_POST[$field]))redirect('/bazaar.php');}
    $action=post('request_action');
    $added=false;
    $cart=$_SESSION['catalogue_request_cart']??[];
    if($action==='add') {
        $id=(int)post('catalog_id');$pack=mb_substr(post('pack'),0,120);
        $entry=['id'=>$id,'qty'=>(int)post('qty','1'),'pack'=>$pack,'urgent'=>post('urgent')==='1'];
        $valid=request_cart_rows($pdo,[$entry]);
        if($valid && ctype_digit(post('qty','1')) && $entry['qty']>=1 && $entry['qty']<=99) {
            $key=hash('sha256',$id.'|'.$pack.'|'.($entry['urgent']?'1':'0'));
            if(count($cart)<50 || isset($cart[$key])) {
                $entry['qty']=min(99,$entry['qty']+(int)($cart[$key]['qty']??0));
                if($pack==='')$entry['pack']=$valid[0]['pack']?:t('piece','पीस');
                $cart[$key]=$entry;
                $added=true;
            }
        }
    } elseif($action==='remove') {unset($cart[post('request_key')]);}
    elseif($action==='update') {
        $key=post('request_key');$qty=post('qty');
        if(isset($cart[$key]) && is_array($cart[$key]) && ctype_digit($qty) && (int)$qty>=1 && (int)$qty<=99) {
            $cart[$key]['qty']=(int)$qty;
            $_SESSION['request_cart_notice']=t('Quantity saved.','मात्रा बदल गई।');
        } else $_SESSION['request_cart_notice']=t('Enter a quantity from 1 to 99 and try again.','मात्रा 1 से 99 के बीच भरकर फिर कोशिश कीजिए।');
    }
    elseif($action==='clear') {$cart=[];}
    $_SESSION['catalogue_request_cart']=$cart;
    if($added && post('checkout')==='1') redirect('/order.php?request_cart=1&lang='.lang().'#pata');
    if($added && (int)bazaar_get('product',10)>0) redirect('/bazaar.php');
    $filter=[];foreach(['group','type','sub','q','p','product'] as $key) $filter[$key]=bazaar_get($key);
    redirect(catalog_url($filter));
}
$request_cart=request_cart_rows($pdo,$_SESSION['catalogue_request_cart']??[]);
$area=coverage_selected($pdo);
$group = bazaar_get('group');
$type = bazaar_get('type');
$sub = bazaar_get('sub');
$q = bazaar_get('q');
$groups = catalog_groups();
if ($group !== '' && !isset($groups[$group])) $group = '';
$ready = true;
try { $all = catalog_load($pdo); } catch (PDOException $e) { $all = []; $ready = false; error_log('Maakit catalog: ' . $e->getMessage()); }
$product_id=(int)bazaar_get('product',10);
$focused=null;
if($product_id>0) foreach($all as $candidate) if((int)$candidate['id']===$product_id){$focused=$candidate;break;}
if($focused){$type=$focused['shop_type'];$group='';$sub='';$q='';}
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
$matches = $product_id>0 ? ($focused?[$focused]:[]) : catalog_filter($all, $group, $type, $sub, $q);
$show_items = $product_id>0 || $type !== '' || $q !== '';
$per = 30;
$pages = max(1, (int)ceil(count($matches) / $per));
$page = min($pages, max(1, (int)bazaar_get('p', 8)));
$items = $show_items ? array_slice($matches, ($page - 1) * $per, $per) : [];
$offers = [];
$prices_ready = true;
try { $offers = catalog_offers($pdo, array_column($items, 'id'),$area); }
catch (PDOException $e) { $prices_ready = false; error_log('Maakit catalog prices: ' . $e->getMessage()); }
$params = ['group' => $group, 'type' => $type, 'sub' => $sub, 'q' => $q];
$local_shops = [];
$shops_ready = true;
if ($type !== '') {
    try { $local_shops = catalog_shops($pdo, $type,$area); }
    catch (PDOException $e) { $shops_ready = false; error_log('Maakit category shops: ' . $e->getMessage()); }
}
$request_design=true;
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
<section class="<?= $focused ? 'focused-product' : '' ?>"><div class="wrap">
  <h1><?= $focused ? (!empty($focused['is_sewa']) ? t('Service details','सेवा की जानकारी') : t('Choose your pack','अपना पैक चुनिए')) : t('Find products', 'सामान खोजिए') ?></h1>
  <?php if(!$focused): ?><p class="lead"><?= t('Choose a shop type, then see its products and local shop prices.', 'दुकान का प्रकार चुनिए, फिर उसका सामान और स्थानीय दुकान के दाम देखिए।') ?></p>
  <p class="help"><?= t('Choose a product and send a request. Maakit checks suitable shops and confirms price and delivery time with you. Delivery is charged separately.', 'सामान चुनकर माँग भेजिए। Maakit उपयुक्त दुकान से पता करके दाम और डिलीवरी समय आपसे पक्का करेगा। डिलीवरी चार्ज अलग है।') ?></p>
  <div class="request-steps" aria-label="<?= h(t('How requests work','माँग कैसे पूरी होती है')) ?>"><span><b>1</b><?= t('Choose products','सामान चुनिए') ?></span><span><b>2</b><?= t('Send request','माँग भेजिए') ?></span><span><b>3</b><?= t('Confirm prices','दाम पक्का कीजिए') ?></span></div>
  <?php endif; ?>
  <?php if($request_cart): ?><div class="box catalogue-request-cart">
    <?php if(isset($_SESSION['request_cart_notice'])): ?><p role="status"><?= h($_SESSION['request_cart_notice']) ?></p><?php endif; ?>
    <details<?= isset($_SESSION['request_cart_notice']) ? ' open' : '' ?>><summary><?= t('Your request cart','आपकी माँग की लिस्ट') ?> · <?= count($request_cart) ?></summary>
    <?php foreach($request_cart as $key=>$row): ?>
      <div class="request-cart-row">
        <span><?= h($row['name']) ?> · <?= (int)$row['qty'] ?> × <?= h($row['pack']) ?><?= $row['urgent']?' · '.t('Urgent','जल्दी चाहिए'):'' ?></span>
        <form method="post" class="request-cart-edit">
          <input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="request_key" value="<?= h($key) ?>">
          <label for="cart-qty-<?= h($key) ?>"><?= t('Quantity','मात्रा') ?></label>
          <input id="cart-qty-<?= h($key) ?>" type="number" inputmode="numeric" name="qty" value="<?= (int)$row['qty'] ?>" min="1" max="99" required>
          <button class="btn btn-sm" type="submit" name="request_action" value="update"><?= t('Save quantity','मात्रा बदलिए') ?></button>
          <button class="btn btn-sm" type="submit" name="request_action" value="remove" formnovalidate><?= t('Remove','हटाइए') ?></button>
        </form>

      </div>
    <?php endforeach; ?>
    </details>
    <p class="help"><?= t('You can add products from other categories. Final prices come from the team after checking shops.','दूसरी categories से भी सामान जोड़ सकते हैं। टीम दुकानों से पता करके अंतिम दाम बताएगी।') ?></p>
    <div class="request-cart-actions"><a class="btn btn-brand" href="/order.php?request_cart=1&amp;lang=<?= h(t('en','hi')) ?>#pata"><?= t('Send this request','यह माँग भेजिए') ?></a>
    <form method="post"><input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="request_action" value="clear"><button class="btn btn-sm" type="submit"><?= t('Clear list','लिस्ट खाली कीजिए') ?></button></form></div>
  </div><?php endif; unset($_SESSION['request_cart_notice']); ?>
  <?php if(!$focused): ?><form class="searchbox bazaar-find" action="/bazaar.php" method="get">
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
  <?php endif; ?>
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
          <b><?= t('Choose a shop & see products →', 'दुकान चुनिए और सामान देखिए →') ?></b>
        </a>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <a class="chip" href="<?= h(catalog_url(['group' => $group])) ?>">← <?= t('Shop types', 'दुकान के प्रकार') ?></a>
    <?php if(!$focused): ?><h2><?= $type !== '' ? h(catalog_label($type, $types[$type]['hi'])) : h(t('Catalogue results', 'सामान की खोज')) ?></h2><?php endif; ?>
    <?php if ($type !== '' && !$focused && $local_shops): ?>
      <details class="shop-options"><summary><?= t('Prefer a particular shop?','किसी खास दुकान से मँगाना है?') ?></summary>
      <p class="help"><?= t('Choose a shop to see its own products, pack sizes and prices.', 'दुकान चुनकर उसी दुकान का सामान, नाप और दाम देखिए।') ?></p>
      <?php if ($local_shops): ?>
        <div class="bazaar-cards">
          <?php foreach ($local_shops as $shop): ?>
            <article class="bazaar-card">
              <?php if ($shop['photo']): ?><img class="bazaar-photo" src="/uploads/<?= h($shop['photo']) ?>" alt="<?= h($shop['name']) ?>" loading="lazy"><?php endif; ?>
              <h3><?= h($shop['name']) ?></h3>
              <span class="meta"><?= h($shop['village']) ?></span>
              <?php if ($shop['address']): ?><span class="meta"><?= h($shop['address']) ?></span><?php endif; ?>
              <span class="tag <?= $shop['open_now'] ? 'tag-live' : 'tag-off' ?>"><?= $shop['open_now'] ? t('Open now', 'अभी खुली है') : t('Closed now', 'अभी बंद है') ?></span>
              <p class="help"><?= (int)$shop['available_count'] ? (int)$shop['available_count'] . ' ' . t('items with shop prices', 'सामान के दुकान वाले दाम जुड़े हैं') : t('Online prices are not ready yet. You can view the shop or ask Maakit.', 'ऑनलाइन सामान के दाम अभी तैयार नहीं हैं। दुकान देखिए या Maakit से पूछिए।') ?></p>
              <a class="btn btn-brand btn-sm" href="/business.php?id=<?= (int)$shop['id'] ?>"><?= t('View shop & products', 'दुकान और सामान देखिए') ?></a>
            </article>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <div class="box"><p><?= $shops_ready ? t('No local shop is listed in this category yet. Check another category or request service coverage.', 'इस category में स्थानीय दुकान अभी नहीं जुड़ी है। दूसरी category देखिए या सेवा के लिए अनुरोध भेजिए।') : t('The shop list is temporarily unavailable. Please ask Maakit.', 'दुकान की सूची अभी नहीं खुल पा रही है। Maakit से पूछिए।') ?></p></div>
      <?php endif; ?>
      <a class="btn btn-green btn-sm" href="<?= h(wa_link(MAAKIT_WA, 'Maakit: ' . catalog_label($type) . ' — ' . t('Please arrange what I need from a local shop.', 'मुझे स्थानीय दुकान से सामान मँगाना है।'))) ?>"><?= t('Arrange through Maakit', 'Maakit से मँगाइए') ?></a>
      </details><h3 style="margin-top:24px"><?= t('Products usually found in this shop type', 'इस तरह की दुकान में मिलने वाला सामान') ?></h3>
    <?php endif; ?>
    <?php if ($subs && !$focused): ?>
      <nav class="bazaar-chips" aria-label="<?= h(t('Product subcategories', 'सामान की subcategories')) ?>">
        <a class="chip <?= $sub === '' ? 'on' : '' ?>" href="<?= h(catalog_url(array_merge($params, ['sub' => '']))) ?>"><?= t('All products', 'सभी सामान') ?></a>
        <?php foreach ($subs as $name => $count): ?>
          <a class="chip <?= $sub === $name ? 'on' : '' ?>" href="<?= h(catalog_url(array_merge($params, ['sub' => $name]))) ?>"><?= h(catalog_sub_label($name)) ?> (<?= $count ?>)</a>
        <?php endforeach; ?>
      </nav>
    <?php endif; ?>
    <?php if(!$focused): ?><p class="meta"><?= count($matches) ?> <?= t('results', 'नतीजे') ?></p><?php endif; ?>
    <?php if (!$prices_ready): ?><p class="help"><?= t('Shop prices are temporarily unavailable. Please ask Maakit.', 'दुकान के दाम अभी नहीं दिख पा रहे हैं। Maakit से पूछिए।') ?></p><?php endif; ?>
    <?php if (!$matches): ?><div class="box"><?= t('No match. Try another name or send your requirement.', 'नहीं मिला। दूसरा नाम खोजिए या अपनी जरूरत भेजिए।') ?></div><?php endif; ?>
    <div class="bazaar-cards">
    <?php foreach ($items as $item): $list = $offers[(int)$item['id']] ?? []; $variant_hint = catalog_variant_hint($item); $reference = !$list ? catalog_reference_price($item) : null; ?>
      <article class="bazaar-card">
        <h3><?= h(t($item['name_en'], $item['name_hi'] ?: $item['name_en'])) ?></h3>
        <span class="meta"><?= h(catalog_label($item['shop_type'], $item['type_hi'])) ?></span>
        <span class="meta"><?= h(catalog_sub_label($item['sub_cat'])) ?> · <?= h($item['unit_hint']) ?><?= !empty($item['is_sewa']) ? ' · ' . t('Service', 'सेवा') : '' ?></span>
        <?php if ($variant_hint): ?><p class="help catalogue-variant"><?= h($variant_hint) ?></p><?php endif; ?>
        <?php foreach ($list as $offer): ?>
          <div class="bazaar-offer">
            <?php if ($offer['photo']): ?><img class="bazaar-photo" src="/uploads/<?= h($offer['photo']) ?>" alt="<?= h($offer['name']) ?>" loading="lazy"><?php endif; ?>
            <span class="bazaar-price">₹<?= (int)$offer['price'] ?></span> / <?= h($offer['unit']) ?>
            <span class="meta"><?= h($offer['shop_name']) ?> · <?= h($offer['village']) ?></span>
            <?php if (!$offer['open_now']): ?><span class="meta"><?= t('Shop closed', 'दुकान अभी बंद है') ?></span>
            <?php endif; ?>
            <a class="btn btn-sm" href="/business.php?id=<?= (int)$offer['business_id'] ?>"><?= t('View shop', 'दुकान देखिए') ?></a>
            <?php if ($offer['stock'] === 'hai' && $offer['open_now'] && empty($item['is_sewa'])): ?>
              <a class="btn btn-brand btn-sm" href="/dukan-se.php?id=<?= (int)$offer['business_id'] ?>"><?= t('Order from shop', 'दुकान से मँगाइए') ?></a>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
        <?php if ($reference): ?>
          <div class="bazaar-offer market-reference">
            <b><?= t('Indicative market price', 'अनुमानित बाज़ार दाम') ?></b>
            <span class="meta"><?= h(t($reference['name_en'],$reference['name_hi'])) ?></span>
            <?php foreach ($reference['packs'] as $pack): ?>
              <span class="meta"><strong>₹<?= h(rtrim(rtrim(number_format((float)$pack['price'],2,'.',''),'0'),'.')) ?></strong> / <?= h($pack['unit']) ?></span>
            <?php endforeach; ?>
            <span class="meta"><?= t('Source: ', 'स्रोत: ') ?><a href="<?= h($reference['url']) ?>" target="_blank" rel="noopener noreferrer"><?= h($reference['source']) ?></a> · <?= h($reference['checked_on']) ?></span>
            <p class="help"><?= t('Online reference, not a confirmed local shop price. The final price is confirmed before purchase; delivery is extra.', 'यह ऑनलाइन संदर्भ है। खरीदने से पहले अंतिम दाम पक्का होगा; डिलीवरी अलग है।') ?></p>
          </div>
        <?php endif; ?>
        <?php if (!$list && !$reference): ?><p class="help"><?= t('Shop price not added yet — ask for price and availability.', 'दुकान का दाम अभी नहीं जुड़ा — दाम और उपलब्धता पूछिए।') ?></p><?php endif; ?>
        <?php if (empty($item['is_sewa'])): ?>
          <form class="catalogue-request" method="post">
            <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
            <input type="hidden" name="request_action" value="add">
            <input type="hidden" name="lang" value="<?= h(t('en','hi')) ?>">
            <input type="hidden" name="catalog_id" value="<?= (int)$item['id'] ?>">
            <label for="rq-qty-<?= (int)$item['id'] ?>"><?= t('Quantity', 'मात्रा') ?></label>
            <input id="rq-qty-<?= (int)$item['id'] ?>" name="qty" type="number" min="1" max="99" value="1" required>
            <label for="rq-pack-<?= (int)$item['id'] ?>"><?= $focused && $reference ? t('Pack & reference price','पैक और संदर्भ दाम') : t('Pack / size / model (optional)', 'पैक / नाप / मॉडल (चाहें तो)') ?></label>
            <?php if($focused && $reference): ?>
            <select id="rq-pack-<?= (int)$item['id'] ?>" name="pack" required>
              <?php foreach($reference['packs'] as $pack): ?><option value="<?= h(mb_substr($reference['name_en'].' · '.$pack['unit'],0,120)) ?>"><?= h($pack['unit']) ?> — ₹<?= h($pack['price']) ?> <?= t('(reference)','(संदर्भ)') ?></option><?php endforeach; ?>
            </select>
            <?php else: ?><input id="rq-pack-<?= (int)$item['id'] ?>" name="pack" type="text" maxlength="120" placeholder="<?= h($item['unit_hint']) ?>"><?php endif; ?>
            <label><input type="checkbox" name="urgent" value="1"> <?= t('Needed urgently', 'जल्दी चाहिए') ?></label>
            <p class="help"><?= t('Maakit will check suitable shops and confirm the final price and possible delivery time with you.', 'Maakit उपयुक्त दुकानों से पता करके अंतिम दाम और सम्भव डिलीवरी समय आपसे पक्का करेगा।') ?></p>
            <?php if($focused): ?><button class="btn btn-brand" type="submit" name="checkout" value="1"><?= t('Continue to address →','पता भरने के लिए आगे बढ़िए →') ?></button><button class="btn btn-line" type="submit"><?= t('Add & keep shopping','जोड़कर और सामान चुनिए') ?></button><?php else: ?><button class="btn btn-brand" type="submit"><?= t('Add to request cart', 'माँग की लिस्ट में जोड़िए') ?></button><?php endif; ?>
          </form>
        <?php endif; ?>
        <?php if(!$focused || !empty($item['is_sewa'])): ?><a class="btn btn-green btn-sm" href="<?= h(wa_link(MAAKIT_WA, 'Maakit: ' . $item['name_en'] . ' / ' . ($item['name_hi'] ?? '') . ' (' . $item['shop_type'] . ') — दाम और उपलब्धता बताइए।' . ($variant_hint ? "\n" . $variant_hint : ''))) ?>"><?= t('Ask Maakit', 'Maakit से पूछिए') ?></a><?php endif; ?>
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
