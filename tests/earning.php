<?php
require_once __DIR__ . '/../inc/earning.php';
function verify($ok,$why) { if (!$ok) throw new RuntimeException($why); }
verify(earning_cost('0') === 0 && earning_cost('1000000') === 1000000, 'Explicit zero costs allowed');
foreach (['-1','1.5','1000001','',[],null,'1e3'] as $bad) verify(earning_cost($bad) === null, 'Reject invalid costs');
verify(earning_date('2024-02-29') && !earning_date('2025-02-29'), 'Real calendar dates');
verify(goods_paid_to_shop('UPI (दुकान को सीधा)') && goods_paid_to_shop('मैं खुद दुकान को UPI करूँगा') && !goods_paid_to_shop('नगद — सामान लेते समय'), 'Both UPI order routes stay separate from delivery collection');
$p=new PDO('sqlite::memory:'); $p->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE,PDO::FETCH_ASSOC);
$p->exec("CREATE TABLE orders(id INTEGER,status TEXT,created_at TEXT,delivered_at TEXT,delivery_charge INTEGER,goods_amount INTEGER);
CREATE TABLE shop_ledger(order_id INTEGER,kind TEXT,source TEXT,amount INTEGER);
CREATE TABLE operating_costs(cost_date TEXT,fuel INTEGER,staff INTEGER,other INTEGER);
INSERT INTO orders VALUES(1,'Delivered','2026-10-01','2026-10-02',40,5000),(2,'Naya','2026-10-02',NULL,999,10000),(3,'Cancel','2026-10-02',NULL,999,10000),(4,'Paisa jama','2026-10-02',NULL,0,200);
INSERT INTO shop_ledger VALUES(1,'commission','maakit',-10),(2,'commission','maakit',-999),(3,'commission','maakit',-999),(1,'bikri','maakit',5000);");
$r=earning_days($p,'2026-10-01','2026-10-02');
verify($r[0]['revenue']===50 && $r[0]['delivered']===2 && $r[0]['balance']===null, 'Only completed revenue, missing cost unknown, goods excluded');
verify($r[1]['revenue']===0, 'Completion date is used');
$p->exec("INSERT INTO operating_costs VALUES('2026-10-02',30,25,5)");
$r=earning_days($p,'2026-10-01','2026-10-02'); verify($r[0]['balance']===-10,'Loss stays visible');
$p->exec("INSERT INTO orders VALUES(5,'Delivered','2026-10-02',NULL,NULL,400)");
$r=earning_days($p,'2026-10-01','2026-10-02');verify($r[0]['unpriced']===1 && $r[0]['balance']===null,'Unknown delivery fee cannot be zero revenue or false profit');
echo "Revenue, cost and direct-shop payment checks passed\n";
