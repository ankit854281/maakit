<?php
// Run with PHP 8 + PDO SQLite: php tests/catalog.php
function t($en, $hi) { return $en; }
require_once __DIR__ . '/../inc/catalog.php';
function check($condition, $message) { if (!$condition) throw new RuntimeException($message); }
$rows = [
    ['id'=>1,'shop_type'=>'Grocery / Kirana Store','name_en'=>'Wheat Atta','name_hi'=>'आटा','type_hi'=>'किराना दुकान','sub_cat'=>'Atta & Flour'],
    ['id'=>2,'shop_type'=>'Grocery / Kirana Store','name_en'=>'Basmati Rice','name_hi'=>'चावल','type_hi'=>'किराना दुकान','sub_cat'=>'Rice'],
    ['id'=>3,'shop_type'=>'Hardware Shop','name_en'=>'Hammer','name_hi'=>'हथौड़ा','type_hi'=>'हार्डवेयर दुकान','sub_cat'=>'Tools & Fasteners'],
];
check(count(catalog_meta()) === 196, 'All 196 source shop types must have groups');
foreach (catalog_meta() as $type => $meta) check(isset(catalog_groups()[$meta['group']]), 'Unknown main group: ' . $type);
check(count(catalog_filter($rows, 'food', '', '', '')) === 2, 'Food grouping');
check(count(catalog_filter($rows, '', 'Grocery / Kirana Store', 'Rice', '')) === 1, 'Type and subcategory');
check(catalog_filter($rows, '', '', '', 'आटा')[0]['id'] === 1, 'Hindi product search');
check(count(catalog_filter($rows, '', '', '', 'किराना')) === 2, 'Hindi shop search');
check(count(catalog_filter($rows, '', '', '', 'rashan')) === 2, 'Roman shop aliases');
check(count(catalog_filter($rows, '', '', '', 'zznotfound')) === 0, 'Unknown query');
check(catalog_url(['type'=>'Grocery / Kirana Store','sub'=>'Atta & Flour']) === '/bazaar.php?type=Grocery+%2F+Kirana+Store&sub=Atta+%26+Flour', 'Filter URL encoding');
$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$pdo->exec('CREATE TABLE businesses(id INTEGER,name TEXT,village TEXT,shop_open INTEGER,status TEXT,items_on INTEGER,open_time TEXT,close_time TEXT)');
$pdo->exec('CREATE TABLE shop_items(id INTEGER,cat_id INTEGER,name TEXT,unit TEXT,price INTEGER,photo TEXT,stock TEXT,active INTEGER,business_id INTEGER)');
$pdo->exec("INSERT INTO businesses VALUES (1,'Approved','Village',1,'approved',1,'00:00','00:00'),(2,'Pending','Village',1,'pending',1,'00:00','00:00'),(3,'Disabled','Village',1,'approved',0,'00:00','00:00')");
$pdo->exec("INSERT INTO shop_items VALUES (1,1,'Atta','5 kg',200,NULL,'hai',1,1),(2,1,'Atta','1 kg',40,NULL,'hai',1,2),(3,1,'Atta','1 kg',0,NULL,'hai',1,1),(4,1,'Atta','1 kg',50,NULL,'khatam',1,1),(5,1,'Atta','2 kg',80,NULL,'hai',0,1),(6,1,'Atta','1 kg',60,NULL,'hai',1,3)");
$offers = catalog_offers($pdo, [1]);
check(count($offers[1]) === 2, 'Only approved, enabled, active, positive-price offers');
check($offers[1][0]['price'] === 50 && $offers[1][0]['stock'] === 'khatam', 'Sold-out offers keep their real price and stock');
check($offers[1][1]['unit'] === '5 kg', 'Keep shop pack size alongside price');
check(catalog_offers($pdo, []) === [], 'Empty page has no offers');
check($offers[1][1]['open_now'] === true, 'Use shop opening schedule');
check(dukan_khuli(['shop_open'=>0,'open_time'=>'00:00','close_time'=>'00:00']) === false, 'Shop switch overrides schedule');
$pdo->exec('ALTER TABLE businesses ADD COLUMN shop_type TEXT');
$pdo->exec('ALTER TABLE businesses ADD COLUMN address TEXT');
$pdo->exec('ALTER TABLE businesses ADD COLUMN photo TEXT');
$pdo->exec('CREATE TABLE catalog_items(id INTEGER,shop_type TEXT)');
$pdo->exec("INSERT INTO catalog_items VALUES (1,'Grocery / Kirana Store'),(2,'Hardware Shop')");
$pdo->exec("UPDATE businesses SET shop_type='Hardware Shop' WHERE id=3");
$local = catalog_shops($pdo, 'Grocery / Kirana Store');
check(count($local) === 2, 'Linked catalogue items associate approved shops; pending excluded');
check($local[0]['id'] === 1 && $local[0]['available_count'] === 1, 'Only positive-price available active stock is counted');
check($local[1]['available_count'] === 0, 'Disabled online stock is not advertised');
check(count(catalog_shops($pdo, 'Hardware Shop')) === 1, 'Exact shop type associates a shop without matching inventory');
check(catalog_shops($pdo, 'Unknown') === [], 'Do not guess unrelated shops');
check(catalog_shops($pdo, '') === [], 'Empty category has no shop query');
echo "Catalogue tests passed\n";
