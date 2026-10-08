<?php
// Integration fixture for CI only. Never creates orders in the live database.
require_once __DIR__ . '/../../config.php';
if (DB_NAME !== 'maakit_jaanch') throw new RuntimeException('Only the CI database is allowed');
require_once __DIR__ . '/../../inc/fn.php';
require_once __DIR__ . '/../../inc/dukan.php';
require_once __DIR__ . '/../../inc/earning.php';
function flow_check($ok, $message) { if (!$ok) throw new RuntimeException($message); }
$cookie = tempnam(sys_get_temp_dir(), 'mk-flow-');
$bid = 0; $registered = 0;
function flow_request($path, $post = null, $expected = 200) {
    global $cookie;
    $ch = curl_init('http://127.0.0.1:8099' . $path);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_COOKIEJAR=>$cookie,
        CURLOPT_COOKIEFILE=>$cookie, CURLOPT_TIMEOUT=>20, CURLOPT_PROXY=>'']);
    if ($post !== null) curl_setopt_array($ch, [CURLOPT_POST=>true, CURLOPT_POSTFIELDS=>http_build_query($post)]);
    $html = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    flow_check($code === $expected && $html !== false, 'Page did not open: ' . $path);
    flow_check(!preg_match('/Fatal error|Warning:|Uncaught/', $html), 'Page error: ' . $path);
    return $html;
}
try {
    $home = flow_request('/?lang=hi');
    flow_check(strpos($home,'id="shop-category-rail"') !== false && strpos($home,'product-discovery-rail') !== false, 'Home links categories and discovery product rails');
    $html = flow_request('/register-business.php?lang=hi');
    flow_check(strpos($html,'data-category-picker') !== false && strpos($html,'Paint Store') !== false, 'Registration offers searchable shop categories');
    preg_match('/name="csrf" value="([^"]+)"/', $html, $reg);
    $form = ['csrf'=>$reg[1], 'name'=>'Jaanch Paint Registration', 'owner'=>'Anil', 'category'=>'dukan', 'mobile'=>'9000000019', 'shop_type'=>'Not a category'];
    flow_request('/register-business.php', $form);
    flow_check((int)$pdo->query("SELECT COUNT(*) FROM businesses WHERE mobile='9000000019'")->fetchColumn()===0, 'Reject forged shop category');
    $form['shop_type']='Paint Store';
    flow_request('/register-business.php', $form);
    $registered=(int)$pdo->query("SELECT id FROM businesses WHERE mobile='9000000019' ORDER BY id DESC LIMIT 1")->fetchColumn();
    flow_check($registered>0, 'Shop registration succeeds');
    $st=$pdo->prepare('SELECT shop_type,status FROM businesses WHERE id=?'); $st->execute([$registered]); $registeredShop=$st->fetch();
    flow_check($registeredShop['shop_type']==='Paint Store' && $registeredShop['status']==='pending', 'Keep category and approval requirement');
    $st=$pdo->prepare('SELECT COUNT(*) FROM shop_items WHERE business_id=?'); $st->execute([$registered]); $seeded=(int)$st->fetchColumn();
    $expected=(int)$pdo->query("SELECT COUNT(*) FROM catalog_items WHERE shop_type='Paint Store'")->fetchColumn();
    flow_check($seeded===$expected && $seeded>10, 'All matching paint products are seeded');
    flow_check(dukan_kism_bharo($pdo,$registered,'Paint Store')===0, 'Repeated category selection does not duplicate products');
    flow_check(dukan_items($pdo,$registered,true)===[], 'Unconfirmed prices never become shop offers');
    $html=flow_request('/bazaar.php?type=Paint+Store&q=Asian&lang=hi');
    flow_check(strpos($html,'एशियन')!==false && strpos($html,'दाम अभी नहीं जुड़ा')!==false, 'Brand is discoverable without seller price');
    $html=flow_request('/search.php?q=Asian&lang=en');
    flow_check(strpos($html,'Asian Paints')!==false, 'Unified search finds master products');
    $pdo->prepare("INSERT INTO businesses (name,category,mobile,status,shop_type,items_on,shop_open,open_time,close_time)
        VALUES ('Jaanch Kirana','dukan','9000000000','approved','Grocery / Kirana Store',1,1,'00:00','00:00')")->execute();
    $bid = (int)$pdo->lastInsertId();
    $add = $pdo->prepare("INSERT INTO shop_items(business_id,cat_id,name,unit,price,stock) VALUES (?,?,?,?,?,?)");
    $add->execute([$bid,1,'Jaanch Atta','5 kg',200,'hai']); $iid = (int)$pdo->lastInsertId();
    $add->execute([$bid,2,'Jaanch Sold Out','1 kg',50,'khatam']); $sold = (int)$pdo->lastInsertId();
    $html = flow_request('/bazaar.php?type=Grocery%20%2F%20Kirana%20Store&lang=hi');
    flow_check(strpos($html, 'Jaanch Kirana') !== false, 'Category must link to the real shop');
    $html = flow_request('/business.php?id=' . $bid);
    flow_check(strpos($html, 'Jaanch Sold Out') !== false && strpos($html, 'स्टॉक खत्म') !== false, 'Shop shows sold-out stock');
    $pdo->prepare('UPDATE businesses SET items_on=0 WHERE id=?')->execute([$bid]);
    flow_request('/dukan-se.php?id=' . $bid, null, 302);
    $html = flow_request('/business.php?id=' . $bid);
    flow_check(strpos($html, 'Jaanch Atta') === false, 'Disabled shop catalogue must stay hidden');
    $pdo->prepare('UPDATE businesses SET items_on=1 WHERE id=?')->execute([$bid]);
    $html = flow_request('/dukan-se.php?id=' . $bid);
    flow_check(strpos($html, 'Jaanch Sold Out') === false, 'Sold-out item must not be orderable');
    preg_match('/name="csrf" value="([^"]+)"/', $html, $m);
    flow_check(!empty($m[1]), 'Order CSRF token');
    preg_match('/name="checkout_key" value="([^"]+)"/', $html, $key);
    flow_check(!empty($key[1]), 'Checkout form token');
    $village = $pdo->query('SELECT name FROM villages WHERE live=1 LIMIT 1')->fetchColumn();
    flow_check((bool)$village, 'Need a live village fixture');
    $aid=$pdo->prepare('SELECT id FROM villages WHERE name=?');$aid->execute([$village]);$areaid=(int)$aid->fetchColumn();
    $pdo->prepare('INSERT INTO service_area_shops(village_id,business_id) VALUES (?,?)')->execute([$areaid,$bid]);
    $base_post = ['csrf'=>$m[1], 'checkout_key'=>$key[1], 'do'=>'mangao', 'id'=>$bid, 'name'=>'Jaanch Customer',
        'mobile'=>'9000000001', 'village'=>$village, 'pay'=>'nagad'];
    foreach ([['q'=>[$iid=>2], 'shown_price'=>[$iid=>199]],
              ['q'=>[$iid=>2,$sold=>1], 'shown_price'=>[$iid=>200,$sold=>50]],
              ['q'=>[$iid=>51], 'shown_price'=>[$iid=>200]],
              ['q'=>[$iid=>['bad']], 'shown_price'=>[$iid=>200]],
              ['q'=>[$iid=>2]]] as $bad_cart) {
        flow_request('/dukan-se.php?id='.$bid, array_merge($base_post,$bad_cart));
        flow_check(count(dukan_orders($pdo,$bid))===0, 'Stale, unavailable or invalid cart must create no partial order');
    }
    $html = flow_request('/dukan-se.php?id=' . $bid, ['csrf'=>$m[1], 'checkout_key'=>$key[1], 'do'=>'mangao', 'id'=>$bid,
        'name'=>'Jaanch Customer', 'mobile'=>'9000000001', 'village'=>$village, 'pay'=>'nagad',
        'price'=>1, 'market'=>'chauri', 'weight'=>'0', 'size'=>'15', 'q'=>[$iid=>2], 'shown_price'=>[$iid=>200]]);
    $st = $pdo->prepare('SELECT * FROM orders WHERE business_id=? ORDER BY id DESC LIMIT 1');
    $st->execute([$bid]); $order = $st->fetch();
    flow_check($order && (int)$order['goods_amount'] === 400, 'Server price and stock must control the order');
    flow_check($order['market'] === 'chauri' && $order['weight_extra'] === '10' && $order['size_extra'] === '15' && (int)$order['delivery_charge'] === 25, 'Market, minimum weight and fragile surcharge must be honoured even on first order');
    flow_check($order['source'] === 'website' && $order['status'] === 'Naya' && $order['shop_status'] === 'naya', 'New order routing');
    flow_check(count(json_decode($order['items_json'], true)) === 1, 'Only confirmed available items ordered');
    flow_check(count(dukan_orders($pdo, $bid)) === 1, 'Order reaches the correct shop panel');
    $html = flow_request('/dukan-se.php?id='.$bid, array_merge($base_post,
        ['q'=>[$iid=>2], 'shown_price'=>[$iid=>200]]));
    flow_check(count(dukan_orders($pdo,$bid))===1 && strpos($html,$order['order_no'])!==false, 'Repeated submission reuses the original confirmation');
    flow_check((int)$pdo->query('SELECT sold FROM shop_items WHERE id='.(int)$iid)->fetchColumn()===2, 'Repeated submission does not increment sold twice');
    flow_check(strpos($html,'m=9000000001')!==false, 'Confirmation tracking uses mobile, not delivery code');
    $html=flow_request('/dukan-se.php?id='.$bid);
    preg_match('/name="checkout_key" value="([^"]+)"/', $html, $key);
    $html = flow_request('/dukan-se.php?id=' . $bid, ['csrf'=>$m[1], 'checkout_key'=>$key[1], 'do'=>'mangao', 'id'=>$bid,
        'name'=>'Jaanch Customer', 'mobile'=>'9000000002', 'village'=>$village, 'pay'=>'upi', 'q'=>[$iid=>1], 'shown_price'=>[$iid=>200]]);
    flow_check(strpos($html, 'UPI अभी नहीं') !== false && count(dukan_orders($pdo,$bid)) === 1, 'Missing shop UPI cannot be forged in POST');
    $pdo->prepare('UPDATE businesses SET commission_pct=10 WHERE id=?')->execute([$bid]);
    flow_check(!dukan_order_status($pdo,$bid+100000,(int)$order['id'],'diya'),'Another shop cannot fulfil this order');
    flow_check(dukan_order_status($pdo,$bid,(int)$order['id'],'diya'),'Shop fulfilment succeeds');
    flow_check(dukan_order_status($pdo,$bid,(int)$order['id'],'diya'),'Fulfilment replay is safe');
    $ledger=$pdo->prepare('SELECT kind,amount FROM shop_ledger WHERE order_id=? ORDER BY id');$ledger->execute([$order['id']]);$rows=$ledger->fetchAll();
    flow_check(count($rows)===2 && (int)$rows[0]['amount']===400 && (int)$rows[1]['amount']===-40,'One sale and one visible commission on replay');
    flow_check(!dukan_order_status($pdo,$bid,(int)$order['id'],'manzoor'),'Handed-over shop state cannot move backwards');
    $pdo->prepare("UPDATE orders SET status='Cancel' WHERE id=?")->execute([$order['id']]);
    flow_check(!dukan_order_status($pdo,$bid,(int)$order['id'],'diya'),'Cancelled order cannot be fulfilled');
    echo "Category → shop → goods → order integration passed\n";
} finally {
    if ($registered) {
        $pdo->prepare('DELETE FROM shop_items WHERE business_id=?')->execute([$registered]);
        $pdo->prepare('DELETE FROM businesses WHERE id=?')->execute([$registered]);
    }
    if ($bid) {
        $pdo->prepare('DELETE FROM service_area_shops WHERE business_id=?')->execute([$bid]);
        $pdo->prepare('DELETE FROM shop_ledger WHERE business_id=?')->execute([$bid]);
        $pdo->prepare('DELETE FROM orders WHERE business_id=?')->execute([$bid]);
        $pdo->prepare('DELETE FROM shop_items WHERE business_id=?')->execute([$bid]);
        $pdo->prepare('DELETE FROM businesses WHERE id=?')->execute([$bid]);
    }
    unlink($cookie);
}
