<?php
// ============================================================
//  दुकान की कहानी — काम करने वाला हिस्सा
//
//  Gaon me khabar vigyapan se nahi, dekhne se phailti hai. Ye
//  wahi hai jo dukaandaar apne WhatsApp Status par daalta hai --
//  "aaj ka maal aa gaya" -- par Maakit khole hue har aadmi tak.
//
//  Panne sirf dikhate hain; soch yahan hai.
// ============================================================

/**
 * settings table se ek line.
 *
 * Maakit me koi aam setting() nahi hai (wo Smart Sarathi ka tha,
 * aur wo alag dhandha hai -- uski file yahan nahi kheenchi jayegi).
 * Isliye yahin ek chhota apna. Ek hi baar padh kar rakh leta hai.
 */
function story_setting(PDO $pdo, $k, $d) {
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        try {
            foreach ($pdo->query("SELECT k, v FROM settings")->fetchAll() as $r) $cache[$r['k']] = $r['v'];
        } catch (Throwable $e) { $cache = []; }
    }
    return array_key_exists($k, $cache) && $cache[$k] !== null && $cache[$k] !== '' ? $cache[$k] : $d;
}

/** ek din me ek dukaan kitni story daal sakti hai */
function story_hadd(PDO $pdo) {
    return max(1, (int)story_setting($pdo, 'story_per_din', 3));
}
/** kitne ghante dikhegi */
function story_ghante(PDO $pdo) {
    return max(1, min(168, (int)story_setting($pdo, 'story_ghante', 24)));
}

/**
 * Aaj is dukaan ne kitni daali.
 *
 * Hadd zaroori hai: bina iske ek dukaan poora panna bhar degi aur
 * baaki dukaanon ki koi nahi dikhegi. Phir wo dukaanein chhod
 * jaayengi -- aur unka chhodna Maakit ka nuksaan hai.
 */
function story_aaj_ki_ginti(PDO $pdo, $bid) {
    $st = $pdo->prepare("SELECT COUNT(*) FROM shop_stories
                          WHERE business_id=? AND DATE(created_at)=CURDATE()");
    $st->execute([(int)$bid]);
    return (int)$st->fetchColumn();
}

/**
 * Nayi story.
 *
 * Lautata hai [theek hua?, sandesh].
 *
 * Photo ke bina bhi chalti hai -- gaon me net dheema hai aur har
 * baar photo khinchna bhaari padta hai. Par dono khali hon to
 * story banti hi nahi; khali dibba kisi kaam ka nahi.
 */
function story_daalo(PDO $pdo, $bid, $baat, $photo = null) {
    $bid  = (int)$bid;
    $baat = trim(preg_replace('/\s+/u', ' ', (string)$baat));
    $baat = mb_substr($baat, 0, 140);

    if ($baat === '' && !$photo) {
        return [false, 'कुछ लिखिए या फ़ोटो लगाइए — दोनों ख़ाली नहीं चलेंगे।'];
    }
    $hadd = story_hadd($pdo);
    if (story_aaj_ki_ginti($pdo, $bid) >= $hadd) {
        return [false, "आज की हद पूरी हो गई ($hadd)। कल फिर डालिए।"];
    }

    $pdo->prepare("INSERT INTO shop_stories (business_id, photo, baat, dikhe_tak)
                   VALUES (?,?,?, NOW() + INTERVAL ? HOUR)")
        ->execute([$bid, $photo ?: null, $baat ?: null, story_ghante($pdo)]);

    return [true, 'लग गई — अब ग्राहकों को दिखेगी।'];
}

/** dukaandaar apni story hata sakta hai (galat photo lag gayi) */
function story_hatao(PDO $pdo, $bid, $sid) {
    $st = $pdo->prepare("UPDATE shop_stories SET hidden=1 WHERE id=? AND business_id=?");
    $st->execute([(int)$sid, (int)$bid]);
    return $st->rowCount() === 1;
}

/** us dukaan ki apni story — panel par, chhupi hui bhi dikhti hai */
function story_dukaan_ki(PDO $pdo, $bid, $limit = 12) {
    $st = $pdo->prepare("SELECT * FROM shop_stories WHERE business_id=?
                          ORDER BY id DESC LIMIT " . (int)$limit);
    $st->execute([(int)$bid]);
    return $st->fetchAll();
}

/**
 * Jo graahak ko dikhni chahiye.
 *
 * Teen shart, teeno zaroori:
 *   - samay baaki ho ("aaj ka maal" kal jhooth ban jata hai)
 *   - maalik ne chhupayi na ho
 *   - DUKAAN KHUD MANZOOR aur chalu ho -- warna hati hui dukaan
 *     ki story panne par rehti aur order kahin nahi jata
 *
 * Ek dukaan ki ek hi story dikhti hai (sabse nayi). Warna ek
 * dukaan teen jagah dikhegi aur panna uska ho jayega.
 */
function story_taaza(PDO $pdo, $limit = 12, $village = null) {
    $sql = "SELECT s.*, b.name AS dukaan, b.village AS dukaan_gaon, b.id AS bid
              FROM shop_stories s
              JOIN businesses b ON b.id = s.business_id
             WHERE s.hidden = 0
               AND s.dikhe_tak > NOW()
               AND b.status = 'approved'
               AND s.id = (SELECT MAX(s2.id) FROM shop_stories s2
                            WHERE s2.business_id = s.business_id
                              AND s2.hidden = 0 AND s2.dikhe_tak > NOW())";
    $args = [];
    if ($village !== null && $village !== '') {
        $sql .= " AND b.village = ?";
        $args[] = $village;
    }
    $sql .= " ORDER BY s.id DESC LIMIT " . (int)$limit;
    $st = $pdo->prepare($sql);
    $st->execute($args);
    return $st->fetchAll();
}

/** ek baar dekhi gayi — ginti ke liye, kisi ki pehchan nahi rakhi jati */
function story_dekhi(PDO $pdo, $sid) {
    try { $pdo->prepare("UPDATE shop_stories SET views=views+1 WHERE id=?")->execute([(int)$sid]); }
    catch (Throwable $e) { /* ginti na bhi bane to panna rukna nahi chahiye */ }
}

/**
 * Is story se ek order aaya.
 *
 * INAAM ISI PAR DENA CHAHIYE -- story daalne par nahi.
 *
 * Story daalne par paisa dene se dukaandaar kachra bhar dega:
 * dhundhli photo, "hello", khali line -- kyunki paisa daalne ka
 * mil raha hai, bikne ka nahi. Order par dene se wo wahi daalega
 * jo sach me bikta hai. Dono ka faayda ek hi taraf jata hai.
 */
function story_se_order(PDO $pdo, $sid) {
    if (!$sid) return;
    try { $pdo->prepare("UPDATE shop_stories SET orders=orders+1 WHERE id=?")->execute([(int)$sid]); }
    catch (Throwable $e) { }
}
