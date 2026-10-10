<?php
// ============================================================
//  सारथी — डिलीवरी वाले के काम की सारी logic
//
//  Sarathi Maakit ke andar hi hai — wahi hosting, wahi PHP,
//  wahi database. Par uska darwaza alag hai (/sarathi/), kyunki
//  delivery wala bhai apne kaam ke liye aata hai, Maakit ki
//  dukaan dekhne ke liye nahi.
//
//  Ek baat jaan-boojh kar aisi hai: yahan WALLET nahi hai.
//  Khata hai. Khata sirf ginti rakhta hai — kitna bana, kitna
//  diya, kitna baaki. Paisa Ankit apne khate se seedhe rider ke
//  khate me bhejte hain. Doosre ka paisa apne paas rakhna
//  (wallet) RBI ke licence ka kaam hai, aur hamare paas wo
//  licence nahi hai.
// ============================================================

// quote_guard() isi me hai — daam ki suraksha. Iske bina status
// badalna daam badalne ka raasta khol deta hai.
require_once __DIR__ . '/order-quotes.php';

/** सारथी में कौन-कौन आ सकता है */
function sarathi_roles() { return ['delivery', 'rider']; }

/**
 * Sarathi ka apna login — mobile + code/password.
 *
 * Do tarah ke log aate hain aur dono chalenge:
 *   - purane delivery staff (role=delivery, username+password)
 *   - naye partner rider   (role=rider,    username=mobile)
 *
 * Isliye username YA mobile, dono se dhoondhte hain.
 */
function sarathi_login(PDO $pdo, $who, $pass) {
    $who = trim((string)$who);
    if ($who === '' || $pass === '') return null;
    $digits = preg_replace('/\D/', '', $who);

    $st = $pdo->prepare(
        "SELECT * FROM users
          WHERE (username = ? OR (mobile IS NOT NULL AND mobile <> '' AND mobile = ?))
            AND role IN ('delivery','rider') AND active = 1
          ORDER BY id LIMIT 1");
    $st->execute([$who, $digits !== '' ? $digits : '~']);
    $u = $st->fetch();
    if (!$u || !$u['password']) return null;
    return password_verify($pass, $u['password']) ? $u : null;
}

/** abhi kaun andar hai (sirf delivery/rider) */
function sarathi_me() {
    $u = user();
    return ($u && in_array($u['role'], sarathi_roles(), true)) ? $u : null;
}

/** ek delivery ka kitna milta hai */
function sarathi_rate(PDO $pdo, $uid) {
    $st = $pdo->prepare("SELECT rider_rate FROM users WHERE id=?");
    $st->execute([(int)$uid]);
    $r = (int)$st->fetchColumn();
    if ($r > 0) return $r;
    $d = $pdo->query("SELECT v FROM settings WHERE k='rider_rate_def'")->fetchColumn();
    return (int)$d;
}

/** ek hi chakkar me doosre-teesre drop ka rate */
function sarathi_extra_rate(PDO $pdo) {
    $v = $pdo->query("SELECT v FROM settings WHERE k='multidrop_rate'")->fetchColumn();
    return (int)$v;
}

/** चार अंकों का OTP — गाँव में चार अंक बोलकर बताना आसान है */
function sarathi_otp() { return str_pad((string)random_int(0, 9999), 4, '0', STR_PAD_LEFT); }

/**
 * Dukaan ka Pick OTP bhar dete hain (agar pehle se nahi hai).
 *
 * Graahak ka code yahan NAHI banate — orders.code pehle se maujood
 * hai, graahak ko order.php par dikhta hai, aur delivery/index.php
 * usi se delivery pakki karta hai. Do code rakhna ek din dukh dega.
 *
 * Aur jaan-boojh kar: agar pick_otp khaali hai to uska milan zaroori
 * NAHI hoga. Isse purane chalte order atakte nahi, aur aap OTP
 * ek-ek order par chalu kar sakte hain.
 */
function sarathi_otp_bharo(PDO $pdo, $oid) {
    $pdo->prepare("UPDATE orders SET pick_otp = COALESCE(pick_otp, ?)
                    WHERE id = ? AND status NOT IN ('Delivered','Cancel')")
        ->execute([sarathi_otp(), (int)$oid]);
}

// ---------------------------------------------------------------
//  हाज़िरी — "मैं अभी काम के लिए तैयार हूँ"
// ---------------------------------------------------------------
//
// Dhyaan: samay UTC me likhte hain (UTC_TIMESTAMP), NOW() me nahi.
// inc/dispatch.php haazri ko UTC_TIMESTAMP() se milata hai aur
// delivery/index.php bhi gmdate() se UTC hi likhta hai. Hosting ka
// MySQL agar UTC par nahi hai to NOW() se likhi haazri dispatch ko
// kabhi "chalu" dikhti hi nahi — rider haazir, par order nahi aata.
function sarathi_duty_on(PDO $pdo, $uid, $minutes = 90) {
    $pdo->prepare("INSERT INTO driver_availability (user_id, available_until)
                   VALUES (?, UTC_TIMESTAMP() + INTERVAL ? MINUTE)
                   ON DUPLICATE KEY UPDATE available_until = VALUES(available_until)")
        ->execute([(int)$uid, (int)$minutes]);
}
function sarathi_duty_off(PDO $pdo, $uid) {
    $pdo->prepare("UPDATE driver_availability SET available_until = NULL WHERE user_id = ?")
        ->execute([(int)$uid]);
}
function sarathi_duty_tak(PDO $pdo, $uid) {
    $st = $pdo->prepare("SELECT available_until FROM driver_availability
                          WHERE user_id = ? AND available_until > UTC_TIMESTAMP()");
    $st->execute([(int)$uid]);
    $utc = $st->fetchColumn();
    if (!$utc) return null;
    // Database me UTC hai; rider ko Bharat ka samay dikhna chahiye.
    try {
        $d = new DateTime($utc, new DateTimeZone('UTC'));
        $d->setTimezone(new DateTimeZone('Asia/Kolkata'));
        return $d->format('Y-m-d H:i:s');
    } catch (Throwable $e) { return $utc; }
}

// ---------------------------------------------------------------
//  चक्कर (trip) — एक बार निकलना, कई जगह निपटाना
// ---------------------------------------------------------------
function sarathi_trip_khula(PDO $pdo, $uid) {
    $st = $pdo->prepare("SELECT * FROM sarathi_trips WHERE rider_user=? AND status='open' ORDER BY id DESC LIMIT 1");
    $st->execute([(int)$uid]);
    return $st->fetch() ?: null;
}
function sarathi_trip_shuru(PDO $pdo, $uid) {
    if ($t = sarathi_trip_khula($pdo, $uid)) return (int)$t['id'];
    $pdo->prepare("INSERT INTO sarathi_trips (rider_user) VALUES (?)")->execute([(int)$uid]);
    return (int)$pdo->lastInsertId();
}
function sarathi_trip_band(PDO $pdo, $uid) {
    // Chakkar band karne se pehle dekh lete hain ki koi drop beech me
    // na ho — warna adhoora order chakkar se bahar reh jata hai.
    $st = $pdo->prepare("SELECT COUNT(*) FROM orders o JOIN sarathi_trips t ON t.id=o.trip_id
                          WHERE t.rider_user=? AND t.status='open'
                            AND o.status NOT IN ('Delivered','Cancel')");
    $st->execute([(int)$uid]);
    if ((int)$st->fetchColumn() > 0) return false;
    $pdo->prepare("UPDATE sarathi_trips SET status='closed', closed_at=NOW()
                    WHERE rider_user=? AND status='open'")->execute([(int)$uid]);
    return true;
}

/** chakkar me ye order bhi daal do, aur uska number (pehla, doosra…) */
function sarathi_trip_me_dalo(PDO $pdo, $uid, $oid) {
    $tid = sarathi_trip_shuru($pdo, $uid);
    $st = $pdo->prepare("SELECT COALESCE(MAX(trip_seq),0)+1 FROM orders WHERE trip_id=?");
    $st->execute([$tid]);
    $seq = (int)$st->fetchColumn();
    $pdo->prepare("UPDATE orders SET trip_id=?, trip_seq=? WHERE id=? AND delivery_user=? AND trip_id IS NULL")
        ->execute([$tid, $seq, (int)$oid, (int)$uid]);
    return $tid;
}

// ---------------------------------------------------------------
//  आज का काम
// ---------------------------------------------------------------
/**
 * Jo order iske naam par hain aur abhi poore nahi hue.
 *
 * Saath hi: jo order abhi uthaya nahi gaya aur jiska Pick OTP nahi
 * bana, uska bana dete hain. Isse kisi doosri file ko chhedna nahi
 * padta, aur dukaandar ko wo OTP apne panel me dikh jata hai.
 */
function sarathi_kaam(PDO $pdo, $uid) {
    // pehle OTP bhar dijiye — dukaandar ko bhi tabhi dikhega
    $st = $pdo->prepare("SELECT id FROM orders
                          WHERE delivery_user=? AND picked_at IS NULL
                            AND (pick_otp IS NULL OR pick_otp='')
                            AND status IN ('Naya','Confirm','Assign')");
    $st->execute([(int)$uid]);
    foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $oid) { sarathi_otp_bharo($pdo, $oid); }

    // Dukaan ka number bhi saath laate hain. Rider ko sabse zyada
    // zaroorat usi ki padti hai — "saaman taiyar hai kya", "main
    // pahunch gaya hoon". Pehle wo sirf graahak ko phone kar sakta tha.
    $st = $pdo->prepare(
        "SELECT o.*, t.id AS tid, b.mobile AS shop_mobile, b.name AS shop_name
           FROM orders o
      LEFT JOIN sarathi_trips t ON t.id = o.trip_id AND t.status='open'
      LEFT JOIN businesses b    ON b.id = o.business_id
          WHERE o.delivery_user = ?
            AND o.status NOT IN ('Delivered','Cancel')
       ORDER BY (o.picked_at IS NOT NULL) DESC, o.trip_seq, o.id");
    $st->execute([(int)$uid]);
    return $st->fetchAll();
}

// ---------------------------------------------------------------
//  रास्ता — बिना किसी बिल के
// ---------------------------------------------------------------
/**
 * Phone ka apna naksha (Maps) khol deta hai.
 *
 * Google Maps API ki chaabi NAHI lagti aur na koi bill aata hai —
 * ye sirf ek link hai. Pata, gaon aur ilaake ko jodkar bhej dete
 * hain; phone apne aap raasta dikha deta hai.
 */
function sarathi_raasta($o) {
    $hisse = array_filter([
        $o['landmark'] ?? '',
        $o['village']  ?? '',
        $o['market']   ?? '',
        'Uttar Pradesh',
    ], fn($x) => trim((string)$x) !== '');
    if (!$hisse) return null;
    return 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode(implode(', ', $hisse));
}

// ---------------------------------------------------------------
//  समस्या — "ये काम नहीं हो पा रहा"
// ---------------------------------------------------------------
/** kaun si samasya ho sakti hai — ek hi jagah tay */
function sarathi_samasya_kism() {
    return [
        'graahak_nahi' => 'ग्राहक घर पर नहीं मिले',
        'phone_band'   => 'ग्राहक का फ़ोन नहीं लग रहा',
        'pata_galat'   => 'पता ग़लत है / जगह नहीं मिली',
        'dukan_band'   => 'दुकान बंद है',
        'saaman_nahi'  => 'दुकान पर सामान तैयार नहीं',
        'gaadi'        => 'गाड़ी ख़राब हो गई',
        'aur'          => 'कुछ और',
    ];
}

/**
 * Rider ne samasya batai.
 *
 * Do jagah likhte hain, jaan-boojh kar:
 *   1. sarathi_samasya me — taaki Maakit ki soochi me aaye
 *   2. order ke note me   — kyunki BPO aur admin note pehle se
 *      dekhte hain. Nayi jagah banane se wo nazar se chhoot jati.
 *
 * Order band NAHI karte. Radd karna BPO ka kaam hai — rider ke
 * haath me wo taakat dena theek nahi.
 */
function sarathi_samasya_likho(PDO $pdo, $uid, $oid, $kism, $note = '') {
    $sab = sarathi_samasya_kism();
    if (!isset($sab[$kism])) return [false, 'समस्या चुनिए।'];

    $st = $pdo->prepare("SELECT id, note FROM orders WHERE id=? AND delivery_user=?");
    $st->execute([(int)$oid, (int)$uid]);
    $o = $st->fetch();
    if (!$o) return [false, 'ये ऑर्डर आपके नाम पर नहीं है।'];

    $note = mb_substr(trim((string)$note), 0, 300);
    $pdo->beginTransaction();
    try {
        $pdo->prepare("INSERT INTO sarathi_samasya (order_id, rider_user, kism, note) VALUES (?,?,?,?)")
            ->execute([(int)$oid, (int)$uid, $kism, $note ?: null]);

        $likha = '[सारथी ' . date('j/n g:i a') . '] ' . $sab[$kism] . ($note ? ' — ' . $note : '');
        $pdo->prepare("UPDATE orders SET note = TRIM(CONCAT(COALESCE(note,''), '\n', ?)) WHERE id=?")
            ->execute([$likha, (int)$oid]);
        $pdo->commit();
    } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }

    return [true, 'Maakit को बता दिया गया। वो आपसे बात करेंगे।'];
}

/** Maakit ke liye — kaun si samasya abhi tak nahi nipti */
function sarathi_samasya_khuli(PDO $pdo, $limit = 50) {
    $st = $pdo->prepare(
        "SELECT s.*, o.order_no, o.customer_name, o.village, u.name AS rider_name
           FROM sarathi_samasya s
           JOIN orders o ON o.id = s.order_id
      LEFT JOIN users  u ON u.id = s.rider_user
          WHERE s.nipta = 0
       ORDER BY s.id DESC LIMIT " . (int)$limit);
    $st->execute();
    return $st->fetchAll();
}

/**
 * बीते काम — jo nipat chuke hain, naye se purane ki taraf.
 *
 * Rider ko ye isliye chahiye ki wo khud dekh sake "kal maine
 * kitne kiye the". Khata sirf jod batata hai; ye ek-ek dikhata
 * hai. Bharosa ginti se nahi, ginti dikhne se banta hai.
 */
function sarathi_beete(PDO $pdo, $uid, $limit = 40) {
    $st = $pdo->prepare(
        "SELECT order_no, customer_name, village, shop, delivery_charge, pod_photo, trip_id,
                COALESCE(delivered_at, updated_at) AS kab
           FROM orders
          WHERE delivery_user = ? AND status = 'Delivered'
       ORDER BY kab DESC, id DESC
          LIMIT " . (int)$limit);
    $st->execute([(int)$uid]);
    return $st->fetchAll();
}

/** aaj kitne nipta diye */
function sarathi_aaj_ginti(PDO $pdo, $uid) {
    $st = $pdo->prepare("SELECT COUNT(*) FROM orders
                          WHERE delivery_user=? AND status='Delivered' AND DATE(COALESCE(delivered_at, updated_at))=CURDATE()");
    $st->execute([(int)$uid]);
    return (int)$st->fetchColumn();
}

// ---------------------------------------------------------------
//  दुकान से उठाया — Pick OTP
// ---------------------------------------------------------------
/**
 * Lautata hai: [theek hua?, sandesh]
 *
 * Dhyaan: hash_equals khaali string par bhi true deta hai, isliye
 * pehle jaanch lete hain ki OTP bana hua hai ya nahi.
 */
function sarathi_uthaya(PDO $pdo, $uid, $oid, $otp) {
    $st = $pdo->prepare("SELECT * FROM orders WHERE id=? AND delivery_user=?");
    $st->execute([(int)$oid, (int)$uid]);
    $o = $st->fetch();
    if (!$o) return [false, 'ये ऑर्डर आपके नाम पर नहीं है।'];
    if ($o['picked_at']) return [true, 'पहले ही उठा लिया गया था।'];
    if (in_array($o['status'], ['Delivered','Cancel'], true)) return [false, 'ये ऑर्डर बंद हो चुका है।'];

    $chahiye = (string)($o['pick_otp'] ?? '');
    if ($chahiye !== '' && !hash_equals($chahiye, trim((string)$otp))) {
        return [false, 'दुकान का OTP सही नहीं है। दुकानदार से दोबारा पूछिए।'];
    }

    $pdo->beginTransaction();
    try {
        // Wahi shart jo delivery/index.php me hai — status ka daayra aur
        // quote_guard(). quote_guard ye pakadta hai ki jo daam graahak ne
        // maana tha aur jo order par likha hai, dono ek hain. Use chhodna
        // ek aisa raasta khol deta hai jisse daam badalkar aage badha ja sake.
        $up = $pdo->prepare("UPDATE orders SET status='Pickup', picked_at=COALESCE(picked_at, NOW())
                              WHERE id=? AND delivery_user=? AND picked_at IS NULL
                                AND status IN ('Naya','Confirm','Assign') AND " . quote_guard());
        $up->execute([(int)$oid, (int)$uid]);
        if ($up->rowCount() !== 1) {
            $pdo->rollBack();
            return [false, 'अभी उठाया नहीं जा सकता — दाम या स्थिति पक्की नहीं है। Maakit से पूछ लीजिए।'];
        }
        sarathi_trip_me_dalo($pdo, $uid, $oid);
        $pdo->commit();
    } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
    return [true, 'दुकान से उठा लिया — दर्ज हो गया।'];
}

// ---------------------------------------------------------------
//  ग्राहक को दे दिया — Drop OTP + फ़ोटो
// ---------------------------------------------------------------
function sarathi_de_diya(PDO $pdo, $uid, $oid, $code, $photo = null) {
    $st = $pdo->prepare("SELECT * FROM orders WHERE id=? AND delivery_user=?");
    $st->execute([(int)$oid, (int)$uid]);
    $o = $st->fetch();
    if (!$o) return [false, 'ये ऑर्डर आपके नाम पर नहीं है।'];
    if ($o['status'] === 'Delivered') return [true, 'पहले ही पहुँचा दिया गया था।'];
    if (in_array($o['status'], ['Cancel'], true)) return [false, 'ये ऑर्डर रद्द हो चुका है।'];
    if (!$o['picked_at'] || $o['status'] !== 'Pickup') return [false, 'पहले दुकान से उठाना दर्ज कीजिए।'];

    // Graahak ka code — orders.code. Ye naya nahi hai; graahak ko
    // order.php par pehle se dikhta hai aur delivery/index.php bhi
    // isi se delivery pakki karta hai.
    // Code sirf anko ka hai, isliye wahi safai jo delivery/index.php
    // karta hai — warna "1234 " aur "1234" alag maan liye jate.
    $chahiye = (string)($o['code'] ?? '');
    $diya    = preg_replace('/\D/', '', (string)$code);
    if ($chahiye === '' || $diya === '' || !hash_equals($chahiye, $diya)) {
        return [false, 'ग्राहक का कोड सही नहीं है। ग्राहक से दोबारा पूछिए।'];
    }

    $up = $pdo->prepare("UPDATE orders SET status='Delivered',
                                delivered_at=COALESCE(delivered_at, NOW()),
                                pod_photo = COALESCE(?, pod_photo)
                          WHERE id=? AND delivery_user=? AND status='Pickup'
                            AND code=? AND " . quote_guard());
    $up->execute([$photo, (int)$oid, (int)$uid, $chahiye]);
    if ($up->rowCount() !== 1) return [false, 'ऑर्डर की स्थिति बदल गई। सूची दोबारा देखिए।'];
    return [true, 'पहुँचा दिया — दर्ज हो गया।'];
}

// ---------------------------------------------------------------
//  खाता — कितना बना, कितना मिला, कितना बाकी
// ---------------------------------------------------------------
/**
 * Kamai ginne ka tareeka:
 *   - chakkar ka pehla drop  → poora rate
 *   - usi chakkar ka agla drop → extra rate (kam mehnat, kam rate)
 *   - chakkar ke bahar wala drop → poora rate
 *
 * Isi se ek order ka kharch girta hai aur dukaan ko sasta pad
 * sakta hai — rider ka nuksan bhi nahi hota.
 */
function sarathi_khata(PDO $pdo, $uid) {
    $rate  = sarathi_rate($pdo, $uid);
    $extra = sarathi_extra_rate($pdo);

    $sql = "SELECT COALESCE(DATE(o.delivered_at), DATE(o.updated_at)) AS din,
                   o.trip_id,
                   COUNT(*) AS kitne
              FROM orders o
             WHERE o.delivery_user = ? AND o.status = 'Delivered'
          GROUP BY din, o.trip_id";
    $st = $pdo->prepare($sql); $st->execute([(int)$uid]);

    $aaj = 0; $aaj_n = 0; $hafta = 0; $hafta_n = 0; $kul = 0; $kul_n = 0;
    $today = date('Y-m-d');
    $week  = date('Y-m-d', strtotime('-6 days'));

    foreach ($st->fetchAll() as $r) {
        $n = (int)$r['kitne'];
        // chakkar ke bahar (trip_id null) ke sab drop poore rate par
        $paisa = $r['trip_id'] === null ? $n * $rate : $rate + max(0, $n - 1) * $extra;
        $kul += $paisa; $kul_n += $n;
        if ($r['din'] >= $week)  { $hafta += $paisa; $hafta_n += $n; }
        if ($r['din'] === $today) { $aaj += $paisa; $aaj_n += $n; }
    }

    $st = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM sarathi_khata WHERE rider_user=?");
    $st->execute([(int)$uid]);
    $diya = (int)$st->fetchColumn();

    return [
        'rate' => $rate, 'extra' => $extra,
        'aaj' => $aaj, 'aaj_n' => $aaj_n,
        'hafta' => $hafta, 'hafta_n' => $hafta_n,
        'kul' => $kul, 'kul_n' => $kul_n,
        'diya' => $diya, 'baaki' => $kul - $diya,
    ];
}

/** Ankit ne rider ko paisa diya — khate me likh dijiye */
function sarathi_diya_likho(PDO $pdo, $uid, $amount, $how = null, $note = null, $by = null) {
    $amount = (int)$amount;
    if ($amount <= 0) return false;
    $pdo->prepare("INSERT INTO sarathi_khata (rider_user, amount, paid_on, how, note, created_by)
                   VALUES (?,?,CURDATE(),?,?,?)")
        ->execute([(int)$uid, $amount, $how ?: null, $note ?: null, $by ? (int)$by : null]);
    return true;
}

// ---------------------------------------------------------------
//  दूरी के हिसाब से किराया — हर शहर में चलता है, Google का बिल नहीं
// ---------------------------------------------------------------
/** settings me "0-3:35,3-7:55,…" likha hai — usse slab banate hain */
function sarathi_slabs(PDO $pdo) {
    $v = (string)$pdo->query("SELECT v FROM settings WHERE k='fare_slab'")->fetchColumn();
    $out = [];
    foreach (explode(',', $v) as $part) {
        if (!preg_match('/^\s*(\d+)\s*-\s*(\d+)\s*:\s*(\d+)\s*$/', $part, $m)) continue;
        $out[] = ['se' => (int)$m[1], 'tak' => (int)$m[2], 'daam' => (int)$m[3]];
    }
    usort($out, fn($a, $b) => $a['se'] <=> $b['se']);
    return $out;
}
/** kitne km par kitna — slab ke bahar ho to aakhri slab ka daam */
function sarathi_kiraya(PDO $pdo, $km) {
    $km = (float)$km;
    $slabs = sarathi_slabs($pdo);
    if (!$slabs) return null;
    foreach ($slabs as $s) { if ($km > $s['se'] && $km <= $s['tak']) return $s['daam']; }
    if ($km <= $slabs[0]['tak']) return $slabs[0]['daam'];
    return end($slabs)['daam'];
}
