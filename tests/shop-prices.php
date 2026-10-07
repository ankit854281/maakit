<?php
require_once __DIR__ . '/../inc/dukan.php';
function price_check($ok,$message) { if (!$ok) throw new RuntimeException($message); }
$p=new PDO('sqlite::memory:');$p->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE,PDO::FETCH_ASSOC);
$p->exec("CREATE TABLE businesses(id INTEGER,name TEXT,status TEXT,items_on INTEGER);
CREATE TABLE shop_items(id INTEGER,business_id INTEGER,cat_id INTEGER,name TEXT,unit TEXT,price INTEGER,active INTEGER,stock TEXT,updated_at TEXT);
INSERT INTO businesses VALUES(1,'Target','approved',1),(2,'Real shop','approved',1),(3,'Unapproved','pending',1),(4,'Hidden catalogue','approved',0);");
$now=date('Y-m-d H:i:s');$old=date('Y-m-d H:i:s',strtotime('-8 days'));
$insert=$p->prepare('INSERT INTO shop_items VALUES(?,?,?,?,?,?,?,?,?)');
foreach ([[1,1,7,'Atta','5 kg',0,1,'hai',$now],[2,2,7,'Atta','5 kg',250,1,'hai',$now],
[3,3,7,'Atta','5 kg',1,1,'hai',$now],[4,4,7,'Atta','5 kg',1,1,'hai',$now],
[5,2,7,'Atta','10 kg',1,1,'hai',$now],[6,2,7,'Atta brand X','5 kg',1,1,'hai',$now],
[7,2,7,'Atta','5 kg',1,1,'hai',$old],[8,2,7,'Atta','5 kg',1,1,'khatam',$now],
[9,2,7,'Atta','5 kg',1,0,'hai',$now],[10,1,8,'Rice','1 kg',0,1,'hai',$now],
[11,1,7,'Atta','5 kg',300,1,'hai',$now]] as $r) $insert->execute($r);
$prices=dukan_price_defaults($p,1);
price_check(count($prices)===1 && (int)$prices[1]['price']===250,'Use only recent approved public matching pack/name/catalogue');
price_check($prices[1]['shop_name']==='Real shop','Price provenance visible');
price_check(!isset($prices[11]) && !isset($prices[10]),'Existing prices and unknown prices stay untouched');
price_check((int)$p->query('SELECT price FROM shop_items WHERE id=1')->fetchColumn()===0,'Suggestion never publishes itself');
echo "Shop starting price checks passed\n";
