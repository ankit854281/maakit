<?php
// Disposable CI database only: expanded discovery must not invent shop offers.
require_once __DIR__.'/../../config.php';
if (DB_NAME !== 'maakit_jaanch') throw new RuntimeException('CI database required');
require_once __DIR__.'/../../inc/fn.php';
require_once __DIR__.'/../../inc/catalog.php';
function expansion_check($ok,$message) { if (!$ok) throw new RuntimeException($message); }
$all=catalog_load($pdo);
$new=array_values(array_filter($all,fn($r)=>(int)$r['id']>=1517 && (int)$r['id']<=1658));
expansion_check(count($new)===142,'Expansion must survive repeated migration without losing or duplicating entries');
expansion_check(count(array_unique(array_column($new,'shop_type')))===25,'Original expansion retains 25 shop types');
$missing=array_values(array_filter($all,fn($r)=>(int)$r['id']>=1659 && (int)$r['id']<=1794));
expansion_check(count($missing)===136,'Missing essentials migration retains 136 entries');
expansion_check(count(array_unique(array_column($missing,'shop_type')))===11,'Missing products cover 11 existing shop types');
$new=array_merge($new,$missing);
$types=array_unique(array_column($new,'shop_type'));
$seen=[];
foreach ($all as $row) {
    $key=$row['shop_type'].'|'.mb_strtolower($row['name_en']);
    if ((int)$row['id']>=1517 && (int)$row['id']<=1794) {
        expansion_check(!isset($seen[$key]),'Duplicate catalogue product: '.$key);
        expansion_check(!empty($row['type_hi']) && isset(catalog_meta()[$row['shop_type']]),'Known category');
        expansion_check(trim($row['name_hi'] ?? '')!=='' && trim($row['unit_hint'])!=='','Hindi discovery and editable pack hint');
        expansion_check((int)$row['is_sewa']===0,'Products cannot become service bookings');
    }
    $seen[$key]=true;
}
foreach (['Aashirvaad','आशीर्वाद','Amul','Surf Excel','Dove','Classmate','boAt','Havells','Fevicol','Prestige','Pedigree','Mangaldeep','Sugar','चीनी','Tata Salt','Fortune','Parle','Britannia','Maggi','Haldiram','Colgate','Dettol','Pampers','Huggies','Samsung','Redmi','iPhone','9W','1.5 sq mm','25 mm','Tomato Seeds','Brake Shoe'] as $query) {
    expansion_check(count(catalog_filter($new,'','','',$query))>0,'Brand discovery: '.$query);
}
foreach (['dawai'=>'Medical / Pharmacy','bartan'=>'Kitchenware / Utensils Shop','kapda'=>'Clothing / Garments Shop','beej'=>'Agriculture Store','puja'=>'Pooja Samagri Shop','joota'=>'Footwear Shop'] as $query=>$type) {
    expansion_check(count(catalog_filter($new,'',$type,'',$query))>0,'Roman category search: '.$query);
}
$bid=0;
try {
    foreach ($types as $type) {
        $pdo->prepare("INSERT INTO businesses(name,category,mobile,status,shop_type,items_on) VALUES ('Jaanch expansion','dukan','9000000029','pending',?,1)")->execute([$type]);
        $bid=(int)$pdo->lastInsertId();
        $expected=count(catalog_filter($all,'',$type,'',''));
        expansion_check(dukan_kism_bharo($pdo,$bid,$type)===$expected,'Complete category seeding: '.$type);
        expansion_check(dukan_items($pdo,$bid,true)===[],'Templates do not publish prices: '.$type);
        $own=array_values(array_filter(dukan_items($pdo,$bid),fn($r)=>(int)$r['cat_id']>=1517 && (int)$r['cat_id']<=1794));
        expansion_check((bool)$own,'New entries reach the shop: '.$type);
        $pdo->prepare("UPDATE shop_items SET price=123,unit='my pack',stock='khatam',name='Owner product name' WHERE id=?")->execute([$own[0]['id']]);
        expansion_check(dukan_kism_bharo($pdo,$bid,$type)===0,'No duplicates on re-selection: '.$type);
        $saved=dukan_item($pdo,$bid,$own[0]['id']);
        expansion_check((int)$saved['price']===123 && $saved['unit']==='my pack' && $saved['stock']==='khatam' && $saved['name']==='Owner product name','Keep owner price, pack and stock');
        expansion_check(catalog_offers($pdo,array_column($new,'id'))===[],'Pending shops do not publish offers');
        $pdo->prepare('DELETE FROM shop_items WHERE business_id=?')->execute([$bid]);
        $pdo->prepare('DELETE FROM businesses WHERE id=?')->execute([$bid]);
        $bid=0;
    }
    foreach (['Aashirvaad','आशीर्वाद','Surf Excel','Prestige','Pedigree','चीनी','Tata Salt','Pampers','iPhone','25 mm'] as $query) {
        $curl=curl_init('http://127.0.0.1:8099/search.php?lang=en&q='.rawurlencode($query));
        curl_setopt_array($curl,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>20,CURLOPT_PROXY=>'']);
        $html=curl_exec($curl);$code=curl_getinfo($curl,CURLINFO_HTTP_CODE);curl_close($curl);
        expansion_check($code===200 && strpos($html,'discovery-product')!==false,'Search product cards: '.$query);
    }
    foreach (['Mobile Store','Clothing / Garments Shop','Footwear Shop','Baby Store','Electrical Store','Hardware Shop','Plumbing Store','Agriculture Store','Auto Spare Parts Shop'] as $type) {
        $sample=catalog_filter($missing,'',$type,'','')[0];
        expansion_check(catalog_variant_hint($sample)!=='','Variant confirmation prompt: '.$type);
        expansion_check(catalog_variant_hint(array_merge($sample,['is_sewa'=>1]))==='','Services must not receive goods specification prompts');
    }
    $curl=curl_init('http://127.0.0.1:8099/bazaar.php?lang=en&type=Mobile%20Store&q=iPhone');
    curl_setopt_array($curl,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>20,CURLOPT_PROXY=>'']);
    $html=curl_exec($curl);curl_close($curl);
    expansion_check(strpos($html,'catalogue-variant')!==false && strpos($html,'exact phone model')!==false,'PLP renders model confirmation');
    expansion_check(strpos(rawurldecode(html_entity_decode($html)),"\nConfirm exact phone model")!==false,'WhatsApp request carries model confirmation');
    echo "278 expanded products: category seeding, Hindi/brand search, price preservation and approval checks passed\n";
} finally {
    if ($bid) {
        $pdo->prepare('DELETE FROM shop_items WHERE business_id=?')->execute([$bid]);
        $pdo->prepare('DELETE FROM businesses WHERE id=?')->execute([$bid]);
    }
}
