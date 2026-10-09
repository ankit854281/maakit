<?php
require_once __DIR__.'/../inc/fn.php';
require_once __DIR__.'/../inc/catalog.php';
require_once __DIR__.'/../inc/services.php';
require_once __DIR__.'/../inc/segments.php';
$segment=isset($_GET['segment'])&&is_string($_GET['segment'])?$_GET['segment']:'LOCAL_SHOPPING';
if (!in_array($segment,['LOCAL_SHOPPING','HOME_SERVICES','B2B'],true)) $segment='LOCAL_SHOPPING';
$q=isset($_GET['q'])&&is_string($_GET['q'])?mb_substr(trim($_GET['q']),0,100):'';
$type=isset($_GET['type'])&&is_string($_GET['type'])?mb_substr($_GET['type'],0,150):'';
$titles=['LOCAL_SHOPPING'=>['Choose products from local shops','स्थानीय दुकानों से सामान चुनिए'],
'HOME_SERVICES'=>['Book the right professional','घर के काम के लिए सही कारीगर'],
'B2B'=>['Find wholesale suppliers','थोक सामान के सप्लायर खोजिए']];
$hints=['LOCAL_SHOPPING'=>['Atta, paint, charger…','आटा, पेंट, चार्जर…'],'HOME_SERVICES'=>['Plumber, electrician, painting…','नल मिस्त्री, बिजली, पुताई…'],'B2B'=>['Bulk goods, brand or supplier…','थोक सामान, ब्रांड या सप्लायर…']];
$page_title=t($titles[$segment][0],$titles[$segment][1]).' — Maakit';$tab='ghar';$request_design=true;$hub_design=true;
$area=coverage_selected($pdo);$cards=[];$categories=[];$offers=[];$setupPending=false;
if ($segment==='LOCAL_SHOPPING') {
    $all=array_values(array_filter(catalog_load($pdo),static fn($p)=>empty($p['is_sewa'])));
    foreach ($all as $item) $categories[$item['shop_type']]=catalog_label($item['shop_type']);
    if ($type!==''&&!isset($categories[$type])) $type='';
    $cards=array_slice(catalog_filter($all,'',$type,'',$q),0,24);$offers=catalog_offers($pdo,array_column($cards,'id'),$area);
} elseif ($segment==='HOME_SERVICES') {
    $service=service_get('mistri');
    foreach ($service['fields'] as $field) if ($field['k']==='event') foreach ($field['o'] as $work) {
        [$en,$hi]=array_pad(explode('|',$work,2),2,'');
        if ($q===''||market_matches($q,$en.' '.$hi)) $cards[]=['work'=>$work,'en'=>$en,'hi'=>$hi,'icon'=>'home'];
    }
    $categories=[];foreach ($service['fields'] as $field) if ($field['k']==='event') foreach ($field['o'] as $work) $categories[$work]=opt_label($work);
    if ($type!==''&&!isset($categories[$type])) $type='';
    if ($type!=='') $cards=array_values(array_filter($cards,static fn($card)=>$card['work']===$type));
} else {
    try {
        $categories=$pdo->query("SELECT DISTINCT c.id,c.name FROM mk_categories c JOIN mk_products p ON p.category_id=c.id JOIN mk_offers o ON o.product_id=p.id JOIN mk_stores s ON s.id=o.store_id JOIN mk_vendors v ON v.id=s.vendor_id WHERE o.is_b2b=1 AND o.active=1 AND p.active=1 AND s.active=1 AND v.status='ACTIVE' ORDER BY c.name")->fetchAll(PDO::FETCH_KEY_PAIR);
        if ($type!==''&&!isset($categories[$type])) $type='';
        $sql="SELECT o.id,p.name,p.brand,c.name AS category,s.name AS store_name FROM mk_offers o JOIN mk_products p ON p.id=o.product_id JOIN mk_categories c ON c.id=p.category_id JOIN mk_stores s ON s.id=o.store_id JOIN mk_vendors v ON v.id=s.vendor_id WHERE o.is_b2b=1 AND o.active=1 AND p.active=1 AND s.active=1 AND v.status='ACTIVE'";$params=[];
        if ($type!=='') { $sql.=' AND c.id=?';$params[]=$type; }
        if ($q!=='') { $like='%'.str_replace(['!','%','_'],['!!','!%','!_'],$q).'%';$sql.=" AND (p.name LIKE ? ESCAPE '!' OR p.brand LIKE ? ESCAPE '!' OR s.name LIKE ? ESCAPE '!')";$params=[$like,$like,$like,...$params];if ($type!=='') $params=[$type,$like,$like,$like]; }
        $st=$pdo->prepare($sql.' ORDER BY p.name LIMIT 24');$st->execute($params);$cards=$st->fetchAll();
    } catch (PDOException $e) { $setupPending=true;error_log('Maakit wholesale catalogue unavailable'); }
}
if (mb_strlen($q)>=2&&mb_strlen($q)<=60&&(int)($_SESSION['sl']??0)<40) {
    $_SESSION['sl']=(int)($_SESSION['sl']??0)+1;
    try { $pdo->prepare('INSERT INTO search_log(q,hits,times) VALUES(?,?,1) ON DUPLICATE KEY UPDATE times=times+1,hits=VALUES(hits)')->execute([mb_strtolower($q),count($cards)]); } catch (Throwable $e) {}
}
// A database/schema failure is not an empty wholesale catalogue. Keep errors private.
if ($setupPending) { http_response_code(503);header('Retry-After: 120'); }
include __DIR__.'/../inc/head.php';
?>
<main class="maakit-hub" data-context="<?= h($segment) ?>" data-csrf="<?= h(csrf()) ?>">
<div class="wrap">
<?php maakit_segments($segment); ?>
<header class="hub-intro">
<a class="hub-location" href="/location.php"><?= svc_icon('shops',18) ?> <?= h($area?coverage_label($area):t('Choose your service area','अपना सेवा क्षेत्र चुनिए')) ?> →</a>
<h1><?= h(t($titles[$segment][0],$titles[$segment][1])) ?></h1>
<p><?= h($segment==='LOCAL_SHOPPING'?t('Shop prices, clear pack sizes, delivery in active areas.','दुकान का दाम, सही पैक और चालू क्षेत्रों में डिलीवरी.'):
($segment==='HOME_SERVICES'?t('Choose the work and a preferred time. Availability and charges are confirmed before the visit.','काम और पसंद का समय चुनिए। आने से पहले उपलब्धता और शुल्क पक्के होंगे।'):
t('Send a requirement directly to a wholesale supplier. This is a quotation, not a retail cart.','अपनी जरूरत थोक सप्लायर को भेजिए। यहाँ थोक भाव पूछा जाएगा।'))) ?></p>
<form class="hub-search" method="get" action="/public/index.php" role="search">
<input type="hidden" name="segment" value="<?= h($segment) ?>"><input type="hidden" name="type" value="<?= h($type) ?>"><input type="hidden" name="lang" value="<?= h(lang()) ?>">
<label class="sr-only" for="hub-q"><?= h(t('Search this section','इस हिस्से में खोजिए')) ?></label>
<input id="hub-q" type="search" name="q" maxlength="100" value="<?= h($q) ?>" placeholder="<?= h(t($hints[$segment][0],$hints[$segment][1])) ?>">
<button class="btn btn-brand" type="submit"><?= t('Search','खोजिए') ?></button>
</form>
</header>
<?php if ($categories): ?>
<div class="hub-section-heading"><h2><?= t('Categories','Categories') ?></h2><span><?= t('Swipe to explore','आगे सरकाकर देखिए') ?></span></div>
<nav class="hub-category-slider" aria-label="<?= h(t('Subcategories','उपश्रेणियाँ')) ?>" tabindex="0">
<a class="hub-category <?= $type===''?'selected':'' ?>" href="/public/index.php?segment=<?= h($segment) ?>"><?= svc_icon('all',24) ?><span><?= t('All','सभी') ?></span></a>
<?php foreach ($categories as $key=>$label): ?><a class="hub-category <?= $type===$key?'selected':'' ?>" href="/public/index.php?<?= h(http_build_query(['segment'=>$segment,'type'=>$key])) ?>"><?= svc_icon($segment==='HOME_SERVICES'?'home':'shops',24) ?><span><?= h($label) ?></span></a><?php endforeach; ?>
</nav>
<?php endif; ?>
<div class="hub-section-heading"><h2><?= h($q!==''?t('Search results','खोज के नतीजे'):t('Explore this section','अपनी जरूरत चुनिए')) ?></h2><span><?= count($cards) ?> <?= t('shown','दिखाए गए') ?></span></div>
<?php if ($setupPending): ?><section class="hub-empty" role="status"><h2><?= t('Wholesale catalogue temporarily unavailable','थोक सामान की सूची अभी नहीं खुल पा रही') ?></h2><p><?= t('Please try again shortly. Your existing shopping and service requests are still available.','थोड़ी देर में दोबारा कोशिश कीजिए। सामान और सेवाओं की अपनी माँग दूसरे हिस्सों से भेज सकते हैं।') ?></p></section><?php endif; ?>
<?php if (!$cards&&!$setupPending): ?><section class="hub-empty"><h2><?= t('Nothing available here yet','यहाँ अभी उपलब्ध listing नहीं है') ?></h2><p><?= h($segment==='B2B'?t('Wholesale suppliers appear after they publish their own offers.','थोक सप्लायर अपना सामान दर्ज करेंगे, तब यहाँ दिखेंगे।'):t('Try another category or tell us what you need.','दूसरी category चुनिए या अपनी जरूरत बताइए।')) ?></p><a class="btn btn-gold" href="<?= $segment==='B2B'?'/register-business.php':'/order.php#pata' ?>"><?= t('Tell us your requirement','अपनी जरूरत बताइए') ?></a></section><?php endif; ?>
<div class="hub-product-grid">
<?php foreach ($cards as $card): ?>
<?php if ($segment==='LOCAL_SHOPPING'):
    $available=array_values(array_filter($offers[(int)$card['id']]??[],static fn($o)=>$o['stock']!=='khatam'));$offer=$available[0]??null; ?>
<a class="hub-product" href="<?= h(catalog_url(['product'=>(int)$card['id']])) ?>">
<span class="hub-product-picture"><?php if ($offer&&!empty($offer['photo'])): ?><img src="/uploads/<?= h(basename($offer['photo'])) ?>" alt="<?= h($offer['name']) ?>" loading="lazy" width="160" height="130"><?php else: ?><?= catalog_product_icon($card,42) ?><?php endif; ?></span>
<span class="hub-category-label"><?= h(catalog_label($card['shop_type'])) ?></span>
<h3><?= h($offer?$offer['name']:t($card['name_en'],$card['name_hi']?:$card['name_en'])) ?></h3>
<?php if ($offer): ?><p class="hub-price">₹<?= h(rtrim(rtrim(number_format((float)$offer['price'],2,'.',''),'0'),'.')) ?> <small>/ <?= h($offer['unit']) ?></small></p><p class="hub-seller"><?= h($offer['shop_name']) ?></p>
<?php else: ?><p class="hub-price pending"><?= t('Confirm shop price','दुकान से दाम पक्का करें') ?></p><p class="hub-seller"><?= t('Pack and availability confirmed by shop','पैक और उपलब्धता दुकान से पक्के होंगे') ?></p><?php endif; ?>
<span class="hub-card-action"><?= t('Choose pack & request →','पैक चुनिए और मँगाइए →') ?></span>
</a>
<?php elseif ($segment==='HOME_SERVICES'): ?>
<a class="hub-product hub-service" href="/sewa.php?<?= h(http_build_query(['s'=>'mistri','work'=>$card['work']])) ?>">
<span class="hub-product-picture"><?= svc_icon($card['icon'],42) ?></span><h3><?= h(t($card['en'],$card['hi'])) ?></h3><p class="hub-seller"><?= t('Choose your preferred day and time','अपनी पसंद का दिन और समय चुनिए') ?></p><span class="hub-card-action"><?= t('Request a visit →','कारीगर बुलाने की माँग →') ?></span>
</a>
<?php else: ?>
<article class="hub-product"><span class="hub-product-picture"><?= svc_icon('shops',42) ?></span><span class="hub-category-label"><?= h($card['category']) ?></span><h3><?= h($card['name']) ?></h3><p class="hub-seller"><?= h($card['store_name']) ?></p><p class="hub-price pending"><?= t('Wholesale quote','थोक भाव पूछिए') ?></p>
<form class="hub-rfq" data-offer="<?= h($card['id']) ?>"><label><?= t('Quantity','कितनी मात्रा') ?><input name="quantity" type="number" min="1" max="1000000" value="1" required></label><label><?= t('Requirement','अपनी जरूरत') ?><input name="message" maxlength="500" placeholder="<?= h(t('Pack, brand, delivery location','पैक, ब्रांड, डिलीवरी स्थान')) ?>"></label><button class="btn btn-brand" type="submit"><?= t('Request wholesale quote','थोक भाव पूछिए') ?></button><p class="hub-rfq-result" role="status" aria-live="polite"></p></form>
</article>
<?php endif; ?>
<?php endforeach; ?>
</div>
<section class="hub-help"><h2><?= t('Your order, one place','आपका ऑर्डर, एक जगह') ?></h2><p><?= t('Goods payment goes directly to the shop. Track your request and confirmed delivery from your account.','सामान का भुगतान सीधे दुकान को होगा। माँग और पक्की डिलीवरी अपने खाते में देखिए।') ?></p><div class="hub-help-actions"><a class="btn btn-line" href="/account.php"><?= t('My orders','मेरे ऑर्डर') ?></a><a class="btn btn-line" href="/track.php"><?= t('Track an order','ऑर्डर की स्थिति देखिए') ?></a><?php if (user()&&in_array(user()['role'],['admin','delivery'],true)): ?><a class="btn btn-line" href="/public/dispatch.php"><?= t('Dispatch dashboard','डिलीवरी पैनल') ?></a><?php endif; ?></div></section>
</div>
</main>
<script src="/assets/maakit-api.js" defer></script>
<script src="/assets/hub.js" defer></script>
<?php include __DIR__.'/../inc/foot.php'; ?>
