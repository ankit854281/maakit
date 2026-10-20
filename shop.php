<?php
require_once __DIR__ . '/inc/fn.php';
require_once __DIR__ . '/inc/salon.php';
require_once __DIR__ . '/inc/dukan.php';
require_once __DIR__ . '/inc/story.php';
$no_tabbar = true;
$shop_panel = true;            // naya dukaan panel shell (mk.css .sp-*)
$page_title = t('Shop panel — Maakit', 'दुकान पैनल — Maakit');
$err = '';

// ---------------- लॉगिन ----------------
// Login ka ek hi darwaza hai — /login.php. Yahan andar aane ka
// apna form nahi hai, taaki dukandar ko do jagah yaad na rakhni pade.
$b = shop_login_business($pdo);
if (!$b) { redirect('/login.php'); }

// Panel apne aap naye order dekhta rehta hai (har 25 sec) — sirf ginti, kuch badalta nahi.
if (get('poll') === '1') {
    $st = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE business_id=? AND shop_status='naya'");
    $st->execute([(int)$b['id']]);
    header('Content-Type: application/json'); header('Cache-Control: no-store');
    echo json_encode(['naye' => (int)$st->fetchColumn()]);
    exit;
}

// Chhote kaam (daam, है/ख़त्म, खुली/बंद) bina panna badle — JS ajax=1 bhejta hai.
// JS na ho to wahi purana redirect chalta hai.
function sp_reply($msg, $url, array $extra = []) {
    if (post('ajax') === '1') {
        header('Content-Type: application/json'); header('Cache-Control: no-store');
        echo json_encode(['ok' => $extra['ok'] ?? true, 'msg' => $msg] + $extra);
        exit;
    }
    if ($msg !== '') flash($msg);
    redirect($url);
}

// csrf ke saath hi — warna koi doosri website dukandar ko logout kara sakti hai
if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('do') === 'logout' && csrf_ok()) {
    shop_logout($pdo); redirect('/login.php');
}

// ---------------- दुकान पैनल के काम (सामान, ऑर्डर, हिसाब, खाता) ----------------
if ($b && $_SERVER['REQUEST_METHOD'] === 'POST' && csrf_ok()) {
    $do = post('do'); $bid = (int)$b['id'];
    $wapas = '/shop.php?tab=' . urlencode(post('tab') ?: 'kaam');

    // --- आज की कहानी ---
    // Jo dukaandaar roz apne WhatsApp Status par "aaj ka maal aa
    // gaya" daalta hai, uska saamaan bikta hai. Wahi cheez yahan
    // hai -- par us tak bhi pahunchti hai jiske paas dukaan ka
    // number nahi hai.
    if ($do === 'kahani') {
        $photo = save_item_photo('photo', 'st', 900);
        [$ok, $m] = story_daalo($pdo, $bid, post('baat'), $photo);
        if (!$ok && $photo) { drop_photo($photo); }
        sp_reply($m, '/shop.php?tab=kahani', ['ok' => $ok]);
    }
    if ($do === 'kahani_hatao') {
        $ok = story_hatao($pdo, $bid, (int)post('sid'));
        sp_reply($ok ? t('Removed.', 'हटा दी गई।') : t('Not found.', 'नहीं मिली।'),
                 '/shop.php?tab=kahani', ['ok' => $ok]);
    }

    // --- दुकान खुली / बंद ---
    if ($do === 'khuli') {
        $pdo->prepare("UPDATE businesses SET shop_open=?, shop_updated=NOW() WHERE id=?")
            ->execute([post('open') === '1' ? 1 : 0, $bid]);
        sp_reply(post('open') === '1' ? t('Shop is open for customers.', 'दुकान खुली दिखेगी।') : t('Shop is closed for customers.', 'दुकान बंद दिखेगी।'),
                 $wapas, ['open' => post('open') === '1']);
    }

    // --- सामान जोड़ना (अपने हाथ से, या Maakit की सूची से) ---
    if ($do === 'item_add') {
        $photo = save_item_photo('photo', 'sh');
        list($ok, $msg) = dukan_item_save($pdo, $bid, [
            'name' => post('name'), 'unit' => post('unit'), 'price' => post('price'),
            'mrp' => post('mrp'), 'item_id' => (int)post('item_id'), 'photo' => $photo,
        ]);
        if (!$ok && $photo) drop_photo($photo);
        flash($msg);
        redirect('/shop.php?tab=saaman');
    }

    // --- बड़ी सूची से कई सामान एक बार में (सिर्फ़ दाम भरे हुए) ---
    if ($do === 'item_bulk') {
        $daam = (array)($_POST['daam'] ?? []);
        $naap = (array)($_POST['naap'] ?? []);
        $jude = 0;
        foreach ($daam as $cid => $p) {
            $p = (int)$p;
            if ($p <= 0) continue;
            $st = $pdo->prepare("SELECT id, name_en, name_hi, unit_hint FROM catalog_items WHERE id=?");
            $st->execute([(int)$cid]);
            if (!$it = $st->fetch()) continue;
            $nm = ($it['name_hi'] !== '' && $it['name_hi'] !== null) ? $it['name_hi'] : $it['name_en'];
            list($ok, $msg, $sid) = dukan_item_save($pdo, $bid, [
                'name'  => $nm,
                'unit'  => trim((string)($naap[$cid] ?? '')) ?: $it['unit_hint'],
                'price' => $p,
            ]);
            if ($ok) {
                $pdo->prepare("UPDATE shop_items SET cat_id=? WHERE id=?")->execute([(int)$it['id'], $sid]);
                $jude++;
            }
        }
        $pdo->prepare("UPDATE businesses SET items_on=1, shop_updated=NOW() WHERE id=?")->execute([$bid]);
        flash($jude ? t("$jude products added.", "$jude सामान जुड़ गए।") : t('No price was filled.', 'किसी का दाम नहीं भरा था।'));
        redirect('/shop.php?tab=saaman' . (post('q') !== '' ? '&q=' . urlencode(post('q')) : ''));
    }

    // --- दुकान किस किस्म की है ---
    // Kism chunte hi us kism ka SAARA saaman apne aap chadh jata hai.
    // Dukandar ko jodna nahi padta — wo bas daam bharta hai.
    if ($do === 'kism') {
        $k = trim(post('shop_type'));
        $ok = $pdo->prepare("SELECT COUNT(*) c FROM catalog_types WHERE slug=?");
        $ok->execute([$k]);
        if ($k === '' || (int)$ok->fetch()['c']) {
            $pdo->prepare("UPDATE businesses SET shop_type=?, items_on=1, shop_updated=NOW() WHERE id=?")
                ->execute([$k ?: null, $bid]);
            if ($k) {
                $naye = dukan_kism_bharo($pdo, $bid, $k);
                flash($naye
                    ? t("$naye products added to your shop. Now just fill the prices.", "$naye सामान आपकी दुकान में आ गए। अब सिर्फ़ दाम भरना है।")
                    : t('Category saved.', 'किस्म सेव हो गई।'));
            } else {
                flash(t('Category removed.', 'किस्म हटा दी।'));
            }
        }
        redirect('/shop.php?tab=saaman');
    }

    // --- एक साथ कई दाम भरना (सबसे ज़्यादा इस्तेमाल होने वाला काम) ---
    if ($do === 'daam_bharo') {
        $daam  = (array)($_POST['daam'] ?? []);
        $naap  = (array)($_POST['naap'] ?? []);
        $nahi  = (array)($_POST['nahi'] ?? []);     // "नहीं रखता"
        $bhare = 0; $hataye = 0;

        $upd  = $pdo->prepare("UPDATE shop_items SET price=?, unit=? WHERE id=? AND business_id=?");
        $updD = $pdo->prepare("UPDATE shop_items SET price=? WHERE id=? AND business_id=?");
        $off  = $pdo->prepare("UPDATE shop_items SET active=0 WHERE id=? AND business_id=?");

        foreach ($daam as $sid => $p) {
            $sid = (int)$sid;
            if (isset($nahi[$sid])) { $off->execute([$sid, $bid]); $hataye++; continue; }
            $p = (int)$p;
            if ($p <= 0 || $p > 200000) continue;
            // naap tabhi badliye jab bhara hua aaya ho — warna purana hi rehne dijiye
            $u = trim((string)($naap[$sid] ?? ''));
            if ($u !== '') { $upd->execute([$p, $u, $sid, $bid]); $kiya = $upd->rowCount(); }
            else           { $updD->execute([$p, $sid, $bid]);    $kiya = $updD->rowCount(); }
            if ($kiya) $bhare++;
        }
        // jo sirf "nahi rakhta" me the, daam ke bina
        foreach ($nahi as $sid => $_) {
            if (!isset($daam[$sid])) { $off->execute([(int)$sid, $bid]); $hataye++; }
        }
        $pdo->prepare("UPDATE businesses SET shop_updated=NOW() WHERE id=?")->execute([$bid]);

        $m = [];
        if ($bhare)  $m[] = t("$bhare prices saved", "$bhare का दाम भर गया");
        if ($hataye) $m[] = t("$hataye removed", "$hataye हटा दिए");
        flash($m ? implode(', ', $m) . t('.', '।') : t('Nothing was filled.', 'कुछ भरा नहीं गया।'));
        redirect('/shop.php?tab=saaman' . (get('p') ? '&p=' . (int)get('p') : ''));
    }

    // --- हटाया हुआ सामान वापस लाना ---
    if ($do === 'wapas') {
        $pdo->prepare("UPDATE shop_items SET active=1 WHERE id=? AND business_id=?")
            ->execute([(int)post('id'), $bid]);
        flash(t('Product is back in your list.', 'सामान वापस आ गया।'));
        redirect('/shop.php?tab=saaman');
    }

    // --- दाम बदलना / है-ख़त्म / हटाना ---
    if ($do === 'item_daam') {
        $back = '/shop.php?tab=saaman' . (post('back') !== '' ? '&' . preg_replace('/[^A-Za-z0-9_=&%+.-]/', '', post('back')) : '');
        if ($it = dukan_item($pdo, $bid, post('id'))) {
            $p = max(0, (int)post('price'));
            if ($p > 0 && $p <= 200000) {
                $pdo->prepare("UPDATE shop_items SET price=? WHERE id=?")->execute([$p, $it['id']]);
                $pdo->prepare("UPDATE businesses SET shop_updated=NOW() WHERE id=?")->execute([$bid]);
                sp_reply(t('Price saved.', 'दाम बदल दिया।'), $back, ['price' => $p]);
            }
            sp_reply(t('That price does not look right.', 'दाम ठीक नहीं लगा।'), $back, ['ok' => false]);
        }
        sp_reply(t('Product not found.', 'सामान नहीं मिला।'), $back, ['ok' => false]);
    }
    if ($do === 'item_stock') {
        $back = '/shop.php?tab=saaman' . (post('back') !== '' ? '&' . preg_replace('/[^A-Za-z0-9_=&%+.-]/', '', post('back')) : '');
        if ($it = dukan_item($pdo, $bid, post('id'))) {
            $naya = $it['stock'] === 'hai' ? 'khatam' : 'hai';
            $pdo->prepare("UPDATE shop_items SET stock=? WHERE id=?")->execute([$naya, $it['id']]);
            sp_reply($naya === 'hai' ? t('Marked in stock.', 'अब है — ग्राहक को दिखेगा।') : t('Marked out of stock.', 'ख़त्म लगा दिया।'), $back, ['stock' => $naya]);
        }
        sp_reply(t('Product not found.', 'सामान नहीं मिला।'), $back, ['ok' => false]);
    }
    if ($do === 'item_del') {
        if ($it = dukan_item($pdo, $bid, post('id'))) {
            $pdo->prepare("DELETE FROM shop_items WHERE id=?")->execute([$it['id']]);
            if ($it['photo']) drop_photo($it['photo']);
            flash(t('Removed.', 'हटा दिया।'));
        }
        redirect('/shop.php?tab=saaman');
    }
    if ($do === 'item_photo') {
        if ($it = dukan_item($pdo, $bid, post('id'))) {
            if ($nayi = save_item_photo('photo', 'sh')) {
                $pdo->prepare("UPDATE shop_items SET photo=? WHERE id=?")->execute([$nayi, $it['id']]);
                if ($it['photo']) drop_photo($it['photo']);
                flash(t('Photo added.', 'फ़ोटो लग गई।'));
            } else { flash(t('Photo could not be added — please try again.', 'फ़ोटो नहीं लग पाई — दोबारा कीजिए।')); }
        }
        redirect('/shop.php?tab=saaman');
    }

    // --- ऑर्डर पर दुकानदार का जवाब ---
    if ($do === 'order_do') {
        if (!dukan_order_status($pdo, $bid, (int)post('id'), post('kya'))) {
            flash(t('This order cannot be changed now. Refresh the list or contact the team.', 'यह ऑर्डर अब बदला नहीं जा सकता। सूची दोबारा देखें या टीम से संपर्क करें।'));
        }
        redirect('/shop.php?tab=order');
    }

    // --- दुकान पर ही बिका (अपनी बही) ---
    if ($do === 'khata_add') {
        $r = (int)post('amount');
        $kharch = (post('kind') === 'kharch');
        if ($r > 0 && $r <= 500000) {
            // ख़र्च घटाव में जाता है, बिक्री जोड़ में
            dukan_khata_likho($pdo, $bid, $kharch ? -$r : $r, [
                'source'  => 'dukaan',
                'kind'    => $kharch ? 'kharch' : 'bikri',
                'paid_by' => in_array(post('paid_by'), ['nagad','upi','baad'], true) ? post('paid_by') : 'nagad',
                'note'    => post('note'),
            ]);
            flash(t('Written in your ledger.', 'बही में लिख दिया।'));
        } else { flash(t('That amount does not look right.', 'रकम ठीक नहीं लगी।')); }
        redirect(post('tab') === 'kaam' ? '/shop.php?tab=kaam' : '/shop.php?tab=hisab' . (in_array(post('kab'), ['hafta','mahina'], true) ? '&kab=' . post('kab') : ''));
    }

    // --- मेरा खाता (UPI) ---
    if ($do === 'khata_set') {
        $vpa = trim(post('upi_id'));
        if ($vpa !== '' && !preg_match('/^[\w.\-]{2,}@[a-zA-Z]{2,}$/', $vpa)) {
            flash(t('A UPI ID looks like 9876543210@ybl', 'UPI ID ऐसी होती है — जैसे 9876543210@ybl'));
        } else {
            $pdo->prepare("UPDATE businesses SET upi_id=?, upi_name=?, shop_updated=NOW() WHERE id=?")
                ->execute([$vpa ?: null, trim(post('upi_name')) ?: null, $bid]);
            flash(t('Payment details saved.', 'खाता सेव हो गया।'));
        }
        redirect('/shop.php?tab=khata');
    }
    if ($do === 'samay') {
        $pdo->prepare("UPDATE businesses SET open_time=?, close_time=?, shop_updated=NOW() WHERE id=?")
            ->execute([post('open_time') ?: '08:00', post('close_time') ?: '20:00', $bid]);
        flash(t('Shop timings saved.', 'समय सेव हो गया।'));
        redirect('/shop.php?tab=khata');
    }
}

// ---------------- नाई / पार्लर के काम (सीट बुकिंग) ----------------
if ($b && $_SERVER['REQUEST_METHOD'] === 'POST' && csrf_ok()) {
    $do = post('do'); $bid = (int)$b['id'];
    $own = $pdo->prepare("SELECT id FROM bookings WHERE id=? AND business_id=?");

    if ($do === 'mode') {
        $m = in_array(post('mode'), ['auto','busy','off'], true) ? post('mode') : 'auto';
        $pdo->prepare("UPDATE businesses SET mode=?, salon_updated=NOW() WHERE id=?")->execute([$m, $bid]);
    }
    if ($do === 'done') {
        $id = (int)post('id'); $own->execute([$id, $bid]);
        if ($own->fetch()) {
            // जितने मिनट सच में लगे, वही मान लीजिए (अगला अनुमान अपने आप सही होता जाएगा)
            $st = $pdo->prepare("SELECT started_at, minutes FROM bookings WHERE id=?"); $st->execute([$id]); $r = $st->fetch();
            $real = $r['started_at'] ? max(3, (int)round((time() - strtotime($r['started_at'])) / 60)) : (int)$r['minutes'];
            $pdo->prepare("UPDATE bookings SET status='done', minutes=? WHERE id=?")->execute([$real, $id]);
            $pdo->prepare("UPDATE salon_customers sc JOIN bookings bk ON bk.mobile=sc.mobile SET sc.visits=sc.visits+1 WHERE bk.id=?")->execute([$id]);
        }
        shop_touch($pdo, $bid);
    }
    if ($do === 'call_next') {
        $chair = max(1, (int)post('chair'));
        $st = $pdo->prepare("SELECT id FROM bookings WHERE business_id=? AND status='waiting' AND DATE(created_at)=CURDATE() ORDER BY id LIMIT 1");
        $st->execute([$bid]);
        if ($n = $st->fetch()) {
            $pdo->prepare("UPDATE bookings SET status='in_chair', chair_no=?, started_at=NOW() WHERE id=?")->execute([$chair, $n['id']]);
        }
        shop_touch($pdo, $bid);
    }
    if ($do === 'accept' || $do === 'reject') {
        $id = (int)post('id'); $own->execute([$id, $bid]);
        if ($own->fetch()) {
            $pdo->prepare("UPDATE bookings SET status=? WHERE id=?")->execute([$do === 'accept' ? 'waiting' : 'cancelled', $id]);
        }
        shop_touch($pdo, $bid);
    }
    if ($do === 'time') {
        $id = (int)post('id'); $d = (int)post('delta'); $own->execute([$id, $bid]);
        if ($own->fetch()) { $pdo->prepare("UPDATE bookings SET minutes=GREATEST(5, minutes + ?) WHERE id=?")->execute([$d, $id]); }
        shop_touch($pdo, $bid);
    }
    if ($do === 'noshow') {
        $id = (int)post('id'); $own->execute([$id, $bid]);
        if ($own->fetch()) {
            $pdo->prepare("UPDATE bookings SET status='no_show' WHERE id=?")->execute([$id]);
            $pdo->prepare("UPDATE salon_customers sc JOIN bookings bk ON bk.mobile=sc.mobile SET sc.no_shows=sc.no_shows+1 WHERE bk.id=?")->execute([$id]);
        }
        shop_touch($pdo, $bid);
    }
    if ($do === 'walkin') {
        $nm = post('wname') ?: 'दुकान पर आया ग्राहक';
        $mn = max(5, (int)post('wmin', 15));
        $pdo->prepare("INSERT INTO bookings (business_id, ref, customer_name, mobile, service_text, minutes, status, source)
                       VALUES (?,?,?,?,?,?, 'waiting','walkin')")
            ->execute([$bid, salon_ref(), $nm, '', post('wsvc') ?: 'दुकान पर आया', $mn]);
        shop_touch($pdo, $bid);
    }
    if ($do === 'setup') {
        $pdo->prepare("UPDATE businesses SET workers=?, open_time=?, close_time=?, salon_updated=NOW() WHERE id=?")
            ->execute([max(1, min(6, (int)post('workers', 1))), post('open_time') ?: '08:00', post('close_time') ?: '20:00', $bid]);
    }
    if ($do === 'svc_add' && post('sname')) {
        $pdo->prepare("INSERT INTO services (business_id, name, price, minutes) VALUES (?,?,?,?)")
            ->execute([$bid, post('sname'), max(0, (int)post('sprice')), max(3, (int)post('smin', 15))]);
    }
    if ($do === 'svc_del') {
        $pdo->prepare("DELETE FROM services WHERE id=? AND business_id=?")->execute([(int)post('id'), $bid]);
    }
    redirect('/shop.php' . (in_array($do, ['setup','svc_add','svc_del'], true) ? '?tab=setup' : ''));
}
include __DIR__ . '/inc/head.php';

// Yahan tak wahi pahunchta hai jo andar aa chuka hai
// (bina login ke upar hi /login.php bhej diya jata hai).
$bid   = (int)$b['id'];
$nai   = (int)$b['salon_on'] === 1;          // नाई / पार्लर है?

// ---- कौन-कौन से tab दिखेंगे ----
$tabs = ['kaam' => t('My shop', 'मेरी दुकान'), 'kahani' => t("Today's story", 'आज की कहानी'), 'saaman' => t('My products', 'मेरा सामान'), 'order' => t('Orders', 'ऑर्डर'),
         'hisab' => t('My accounts', 'मेरा हिसाब'), 'khata' => t('Payments & settings', 'खाता और सेटिंग')];
if ($nai) { $tabs = ['live' => t("Today's bookings", 'आज की बुकिंग'), 'setup' => t('Rates & settings', 'रेट और सेटिंग')] + $tabs; }
$tab = get('tab');
if (!isset($tabs[$tab])) $tab = $nai ? 'live' : 'kaam';

// ---- नाई वाले tab का सामान ----
if ($nai) {
    $board = salon_board($pdo, $b);
    $req = $pdo->prepare("SELECT * FROM bookings WHERE business_id=? AND status='requested' AND DATE(created_at)=CURDATE() ORDER BY id");
    $req->execute([$bid]); $requests = $req->fetchAll();
    $svc = salon_services($pdo, $bid, false);
    $dsum = $pdo->prepare("SELECT COUNT(*) c, COALESCE(SUM(price),0) p FROM bookings WHERE business_id=? AND status='done' AND DATE(created_at)=CURDATE()");
    $dsum->execute([$bid]); $today = $dsum->fetch();
}
$aaj = date('Y-m-d');

include __DIR__ . '/inc/dukan-shell.php';

if ($tab === 'kaam')   { include __DIR__ . '/inc/dukan-kaam.php'; }
if ($tab === 'kahani') { include __DIR__ . '/inc/dukan-kahani.php'; }
if ($tab === 'saaman') { include __DIR__ . '/inc/dukan-saaman.php'; }
if ($tab === 'order')  { include __DIR__ . '/inc/dukan-order.php'; }
if ($tab === 'hisab')  { include __DIR__ . '/inc/dukan-hisab.php'; }
if ($tab === 'khata')  { include __DIR__ . '/inc/dukan-khata.php'; }
if ($tab === 'live' || $tab === 'setup') { include __DIR__ . '/inc/dukan-salon.php'; }

include __DIR__ . '/inc/dukan-shell-end.php';
include __DIR__ . '/inc/foot.php';
