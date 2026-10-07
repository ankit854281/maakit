<?php
// ============================================================
//  दुकान पैनल का दिमाग़
//
//  Dukandar /shop.php par mobile + code se andar aata hai
//  (90 din tak usi phone me khula rehta hai). Wahan wo:
//    - apna saaman aur daam rakhta hai
//    - apne order dekhta hai
//    - apna hisab dekhta hai
//    - apna UPI daalta hai
//
//  ---- Paise ka niyam (ye badalna mat) ----
//  Maakit grahak ka paisa apne paas NAHI rakhta. Do hi raste hain:
//    1. Nagad — delivery boy laata hai
//    2. UPI — seedha DUKAAN ke apne UPI par
//  Isliye Maakit "payment aggregator" nahi banta aur koi licence
//  nahi chahiye. Khata (shop_ledger) sirf hisab likhta hai, paisa
//  nahi rakhta.
// ============================================================

/**
 * Dukaan ka apna saaman.
 *   $live = true  → sirf wo jo grahak ko dikhna chahiye
 *                   (daam bhara hua, chalu, aur khatam nahi)
 *   $live = false → sab, dukandar ke panel ke liye
 *
 * Daam 0 wala grahak ko kabhi nahi dikhta — warna dukaan
 * adhoori lagti hai aur bharosa jaata hai.
 */
function dukan_items(PDO $pdo, $bid, $live = false) {
    $sql = "SELECT * FROM shop_items WHERE business_id=?";
    if ($live) $sql .= " AND active=1 AND stock='hai' AND price > 0";
    $sql .= " ORDER BY sort_no, sold DESC, name";
    $st = $pdo->prepare($sql);
    $st->execute([(int)$bid]);
    return $st->fetchAll();
}

/**
 * Kism chunte hi us kism ka SAARA saaman dukaan me chadha do.
 * Dukandar ko ek-ek karke jodna nahi padta — wo sirf daam bharta hai.
 * Daam 0 rehta hai, isliye grahak ko tab tak kuchh nahi dikhta.
 *
 * Jo naam pehle se chadha hai wo dobara nahi aata.
 * Wapas karta hai: kitne naye chadhe.
 */
function dukan_kism_bharo(PDO $pdo, $bid, $slug) {
    $bid = (int)$bid;
    $st = $pdo->prepare("SELECT id, name_en, name_hi, unit_hint, sort_no
                           FROM catalog_items WHERE shop_type=? ORDER BY sort_no");
    $st->execute([$slug]);
    $saare = $st->fetchAll();
    if (!$saare) return 0;

    // ek dukaan me 400 ki hadd — usse jyada na chadhe
    $cnt = $pdo->prepare("SELECT COUNT(*) c FROM shop_items WHERE business_id=?");
    $cnt->execute([$bid]);
    $jagah = 400 - (int)$cnt->fetch()['c'];
    if ($jagah <= 0) return 0;

    $ins = $pdo->prepare("INSERT IGNORE INTO shop_items
            (business_id, cat_id, name, unit, price, stock, active, sort_no)
            VALUES (?,?,?,?,0,'hai',1,?)");
    $naye = 0;
    foreach ($saare as $c) {
        if ($naye >= $jagah) break;
        $nm = ($c['name_hi'] !== '' && $c['name_hi'] !== null) ? $c['name_hi'] : $c['name_en'];
        $ins->execute([$bid, (int)$c['id'], $nm, $c['unit_hint'], (int)$c['sort_no']]);
        if ($ins->rowCount()) $naye++;
    }
    return $naye;
}

/** Starting price suggestions from recent public shop prices, never published
 * as this shop's price until its owner explicitly saves the editable form.
 * Match catalogue ID, product name AND pack size; don't convert unlike packs.
 */
function dukan_price_defaults(PDO $pdo, $bid) {
    $st=$pdo->prepare("SELECT target.id target_id, source.price, b.name shop_name, source.updated_at
        FROM shop_items target JOIN shop_items source
          ON source.cat_id=target.cat_id AND source.name=target.name AND source.unit=target.unit
        JOIN businesses b ON b.id=source.business_id
        WHERE target.business_id=? AND target.price<=0 AND target.active=1
          AND source.business_id<>? AND source.active=1 AND source.stock='hai'
          AND source.price BETWEEN 1 AND 200000 AND source.updated_at>=?
          AND b.status='approved' AND b.items_on=1
        ORDER BY source.updated_at DESC, source.id DESC");
    $st->execute([(int)$bid,(int)$bid,date('Y-m-d H:i:s',strtotime('-7 days'))]);
    $prices=[];
    foreach ($st as $row) {
        $id=(int)$row['target_id'];
        if (!isset($prices[$id])) $prices[$id]=$row;
    }
    return $prices;
}

/** Jis saaman ka daam abhi nahi bhara (dukandar ko bharna hai) */
function dukan_daam_baaki(PDO $pdo, $bid, $limit = 500) {
    $st = $pdo->prepare("SELECT * FROM shop_items
                          WHERE business_id=? AND price <= 0 AND active=1
                          ORDER BY sort_no, name LIMIT " . (int)$limit);
    $st->execute([(int)$bid]);
    return $st->fetchAll();
}

/** Ek saaman jo isi dukaan ka ho (doosre ki cheez na chhoo sake) */
function dukan_item(PDO $pdo, $bid, $id) {
    $st = $pdo->prepare("SELECT * FROM shop_items WHERE id=? AND business_id=?");
    $st->execute([(int)$id, (int)$bid]);
    return $st->fetch();
}

/**
 * Saaman jodna. Wahi naam-naap dobara aaya to naya nahi banta,
 * purane ka daam badal jata hai — isse dohri entry nahi hoti.
 */
function dukan_item_save(PDO $pdo, $bid, array $d) {
    $name = trim($d['name'] ?? '');
    if (mb_strlen($name) < 2) return [false, 'सामान का नाम लिखिए।'];
    $unit  = trim($d['unit'] ?? '') ?: '1 पीस';
    $price = max(0, (int)($d['price'] ?? 0));
    if ($price <= 0)     return [false, 'दाम भरिए।'];
    if ($price > 200000) return [false, 'दाम बहुत ज़्यादा लग रहा है — एक बार देख लीजिए।'];
    $mrp = (int)($d['mrp'] ?? 0) ?: null;

    $st = $pdo->prepare("SELECT id FROM shop_items WHERE business_id=? AND name=? AND unit=?");
    $st->execute([(int)$bid, $name, $unit]);
    if ($row = $st->fetch()) {
        $pdo->prepare("UPDATE shop_items SET price=?, mrp=?, stock='hai', active=1 WHERE id=?")
            ->execute([$price, $mrp, $row['id']]);
        return [true, 'दाम बदल दिया गया।', (int)$row['id']];
    }

    // ek dukaan me 400 se jyada saaman — jaanch kar lijiye
    $cnt = $pdo->prepare("SELECT COUNT(*) c FROM shop_items WHERE business_id=?");
    $cnt->execute([(int)$bid]);
    if ((int)$cnt->fetch()['c'] >= 400) return [false, '400 से ज़्यादा सामान नहीं जोड़ सकते। पुराने हटाकर जगह बनाइए।'];

    $pdo->prepare("INSERT INTO shop_items (business_id, item_id, name, unit, price, mrp, photo)
                   VALUES (?,?,?,?,?,?,?)")
        ->execute([(int)$bid, ($d['item_id'] ?? null) ?: null, $name, $unit, $price, $mrp, $d['photo'] ?? null]);
    return [true, 'जुड़ गया।', (int)$pdo->lastInsertId()];
}

/**
 * Saaman ki badi soochi (catalog_items) — jisme daam bharna baaki hai.
 * Isse dukandar ko naam type nahi karna padta, sirf daam.
 *
 * - Dukaan ki apni kism (shop_type) ka saaman PEHLE
 * - $q se poori soochi me khoj (Hindi aur angrezi dono me)
 * - jo pehle se chadha hai wo dobara nahi dikhta
 */
function dukan_suggest(PDO $pdo, $bid, $q = '', $limit = 40, $sab = false) {
    $b = $pdo->prepare("SELECT shop_type FROM businesses WHERE id=?");
    $b->execute([(int)$bid]);
    $type = (string)($b->fetch()['shop_type'] ?? '');

    // "naam" wahi hai jo dukandar ko dikhega — Hindi ho to Hindi
    $naam = "IF(c.name_hi <> '' AND c.name_hi IS NOT NULL, c.name_hi, c.name_en)";

    $args = [(int)$bid];
    $sql = "SELECT c.id, c.shop_type, c.sub_cat, c.is_sewa, c.unit_hint AS unit,
                   $naam AS name, c.name_en
              FROM catalog_items c
             WHERE NOT EXISTS (SELECT 1 FROM shop_items s
                                WHERE s.business_id = ?
                                  AND (s.cat_id = c.id OR s.name = $naam))";

    if ($q !== '') {
        $sql .= " AND (c.name_en LIKE ? OR c.name_hi LIKE ? OR c.sub_cat LIKE ? OR c.shop_type LIKE ?)";
        array_push($args, "%$q%", "%$q%", "%$q%", "%$q%");
    } elseif ($type !== '' && !$sab) {
        $sql .= " AND c.shop_type = ?";
        $args[] = $type;
    }

    // apni kism ka saaman upar, phir baaki
    if ($type !== '') {
        $sql .= " ORDER BY (c.shop_type = ?) DESC, c.sort_no";
        $args[] = $type;
    } else {
        $sql .= " ORDER BY c.sort_no";
    }
    $sql .= " LIMIT " . (int)$limit;

    $st = $pdo->prepare($sql);
    $st->execute($args);
    return $st->fetchAll();
}

/** Dukaan ki kismein — ek baar chunne ke liye */
function dukan_types(PDO $pdo) {
    return $pdo->query("SELECT t.slug, t.name_hi, COUNT(c.id) AS ginti
                          FROM catalog_types t
                          LEFT JOIN catalog_items c ON c.shop_type = t.slug
                         GROUP BY t.slug, t.name_hi
                         HAVING ginti > 0
                         ORDER BY ginti DESC, t.slug")->fetchAll();
}

/** Is dukaan ke order (naye pehle) */
function dukan_orders(PDO $pdo, $bid, $din = 7, $limit = 60) {
    $st = $pdo->prepare("SELECT * FROM orders
                          WHERE business_id=? AND created_at > (NOW() - INTERVAL ? DAY)
                          ORDER BY (shop_status='naya') DESC, id DESC LIMIT " . (int)$limit);
    $st->execute([(int)$bid, (int)$din]);
    return $st->fetchAll();
}

/** Dukandar ne order par kya kiya */
function dukan_order_status(PDO $pdo, $bid, $oid, $kya) {
    $ok = ['manzoor', 'taiyaar', 'diya', 'mana'];
    if (!in_array($kya, $ok, true)) return false;
    $own = $pdo->prepare("SELECT id, goods_amount, payment FROM orders WHERE id=? AND business_id=?");
    $own->execute([(int)$oid, (int)$bid]);
    $o = $own->fetch();
    if (!$o) return false;

    $pdo->prepare("UPDATE orders SET shop_status=?, shop_seen_at=NOW() WHERE id=?")
        ->execute([$kya, (int)$oid]);

    // "de diya" par hi hisab me chadhta hai — pehle nahi
    if ($kya === 'diya' && (int)$o['goods_amount'] > 0) {
        $pehle = $pdo->prepare("SELECT COUNT(*) c FROM shop_ledger WHERE order_id=? AND kind='bikri'");
        $pehle->execute([(int)$oid]);
        if ((int)$pehle->fetch()['c'] === 0) {
            dukan_khata_likho($pdo, $bid, (int)$o['goods_amount'], [
                'order_id' => (int)$oid,
                'paid_by'  => (stripos((string)$o['payment'], 'upi') !== false ? 'upi' : 'nagad'),
                'note'     => 'Maakit का ऑर्डर',
            ]);
        }
    }
    return true;
}

/**
 * Khata me ek line likhna.
 * amount jod (+) = dukaan ki kamai, ghatav (−) = dukaan se kata.
 * Commission apne aap kat jata hai (agar admin ne lagaya ho).
 */
function dukan_khata_likho(PDO $pdo, $bid, $amount, array $o = []) {
    $amount = (int)$amount;
    if ($amount === 0) return false;
    $kind    = $o['kind']    ?? 'bikri';
    $source  = $o['source']  ?? (isset($o['order_id']) ? 'maakit' : 'dukaan');
    $paid_by = $o['paid_by'] ?? 'nagad';
    $pdo->prepare("INSERT INTO shop_ledger (business_id, order_id, source, kind, amount, paid_by, note)
                   VALUES (?,?,?,?,?,?,?)")
        ->execute([(int)$bid, ($o['order_id'] ?? null) ?: null, $source, $kind, $amount, $paid_by, $o['note'] ?? null]);

    // Maakit ka hissa — sirf Maakit se aaye order par, aur sirf bikri par
    if ($kind === 'bikri' && $source === 'maakit' && $amount > 0) {
        $b = $pdo->prepare("SELECT commission_pct FROM businesses WHERE id=?");
        $b->execute([(int)$bid]);
        $pct = (float)($b->fetch()['commission_pct'] ?? 0);
        if ($pct > 0) {
            $kat = (int)round($amount * $pct / 100);
            if ($kat > 0) {
                $pdo->prepare("INSERT INTO shop_ledger (business_id, order_id, source, kind, amount, paid_by, note)
                               VALUES (?,?,?,'commission',?,?,?)")
                    ->execute([(int)$bid, ($o['order_id'] ?? null) ?: null, $source, -$kat, $paid_by,
                               'Maakit का हिस्सा ' . rtrim(rtrim(number_format($pct, 1), '0'), '.') . '%']);
            }
        }
    }
    return true;
}

/** Khata ki soochi */
function dukan_khata(PDO $pdo, $bid, $se, $tak, $limit = 300) {
    $st = $pdo->prepare("SELECT * FROM shop_ledger
                          WHERE business_id=? AND DATE(created_at) BETWEEN ? AND ?
                          ORDER BY id DESC LIMIT " . (int)$limit);
    $st->execute([(int)$bid, $se, $tak]);
    return $st->fetchAll();
}

/**
 * Hisab ka jod — ek hi jagah, taaki har tab me ek jaisa dikhe.
 *   bikri      : kul bikri
 *   nagad/upi  : kaise paisa aaya
 *   udhaar     : jo abhi aaya nahi ('baad' me)
 *   commission : Maakit ka hissa (dhanatmak sankhya me)
 *   bacha      : dukaan ke haath me kitna
 */
function dukan_jod(PDO $pdo, $bid, $se, $tak) {
    $st = $pdo->prepare("SELECT
            COALESCE(SUM(CASE WHEN kind='bikri'                       THEN amount END),0) AS bikri,
            COALESCE(SUM(CASE WHEN kind='bikri' AND paid_by='nagad'   THEN amount END),0) AS nagad,
            COALESCE(SUM(CASE WHEN kind='bikri' AND paid_by='upi'     THEN amount END),0) AS upi,
            COALESCE(SUM(CASE WHEN kind='bikri' AND paid_by='baad'    THEN amount END),0) AS udhaar,
            COALESCE(SUM(CASE WHEN kind='bikri' AND source='maakit'   THEN amount END),0) AS maakit_se,
            COALESCE(SUM(CASE WHEN kind='bikri' AND source='dukaan'   THEN amount END),0) AS dukaan_se,
            COALESCE(SUM(CASE WHEN kind='commission'                  THEN -amount END),0) AS commission,
            COALESCE(SUM(CASE WHEN kind='kharch'                      THEN -amount END),0) AS kharch,
            COALESCE(SUM(amount),0) AS bacha,
            COUNT(CASE WHEN kind='bikri' THEN 1 END) AS ginti
          FROM shop_ledger
         WHERE business_id=? AND DATE(created_at) BETWEEN ? AND ?");
    $st->execute([(int)$bid, $se, $tak]);
    return $st->fetch();
}

/** Sabse jyada bikne wala saaman (hisab tab me dikhane ke liye) */
function dukan_top(PDO $pdo, $bid, $limit = 5) {
    $st = $pdo->prepare("SELECT name, unit, price, sold FROM shop_items
                          WHERE business_id=? AND sold > 0 ORDER BY sold DESC LIMIT " . (int)$limit);
    $st->execute([(int)$bid]);
    return $st->fetchAll();
}

/** Jo paisa Maakit se lena baaki hai (nagad delivery boy ke paas gaya) */
function dukan_lena_hai(PDO $pdo, $bid) {
    $st = $pdo->prepare("SELECT COALESCE(SUM(amount),0) s FROM shop_ledger
                          WHERE business_id=? AND settled=0 AND source='maakit'");
    $st->execute([(int)$bid]);
    return (int)$st->fetch()['s'];
}

/** UPI ka link/QR — paisa seedha dukaan ke khate me jata hai */
function dukan_upi_link($b, $rupay = 0, $note = '') {
    $vpa = trim((string)($b['upi_id'] ?? ''));
    if ($vpa === '' || !preg_match('/^[\w.\-]{2,}@[a-zA-Z]{2,}$/', $vpa)) return null;
    $q = ['pa' => $vpa, 'pn' => ($b['upi_name'] ?: $b['name']), 'cu' => 'INR'];
    if ($rupay > 0) $q['am'] = (int)$rupay;
    if ($note !== '') $q['tn'] = mb_substr($note, 0, 40);
    return 'upi://pay?' . http_build_query($q);
}

/** Dukaan abhi khuli hai? (dukandar ka switch + samay) */
function dukan_khuli($b) {
    if (!(int)($b['shop_open'] ?? 1)) return false;
    $o = substr((string)($b['open_time'] ?? '08:00:00'), 0, 5);
    $c = substr((string)($b['close_time'] ?? '20:00:00'), 0, 5);
    $ab = date('H:i');
    if ($o === $c) return true;
    return ($o < $c) ? ($ab >= $o && $ab <= $c) : ($ab >= $o || $ab <= $c);
}
