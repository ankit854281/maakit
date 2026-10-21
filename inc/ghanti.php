<?php
// ============================================================
//  घंटी — जो अभी देखना है
//
//  Teen bot pehle se hain, aur teeno ka apna waqt hai:
//     चौकीदार  — website theek chal rahi hai ya nahi (15 minute)
//     एक काम   — aaj ka ek kaam (roz subah)
//     मुनीम     — hafte ka hisab
//
//  Ek cheez ke liye teeno bekaar hain: jo AB ruka hua hai.
//
//  Graahak ne 8 baje order kiya aur 8:25 tak kisi ne chhua nahi --
//  ye kal subah batane ki baat nahi hai. Kal tak wo graahak khud
//  dukaan chala gaya hoga, aur dobara nahi aayega. Gaon me ek
//  naaraz graahak chaar aur logon ko bata deta hai.
//
//  ISME DO CHEEZEIN JAAN-BOOJH KAR HAIN
//  ------------------------------------
//  1. SIRF WAHI JO 15 MINUTE ME BIGAD RAHA HAI. Dukaan ki
//     manzoori, bina daam ka saamaan, bot ke packet -- ye sab
//     zaroori hain par inme se koi bhi aadhe ghante me nahi
//     bigadta. Wo एक काम ka kaam hai. Ghanti me sirf wo hai
//     jiske liye aadmi ko ABHI uthna chahiye.
//
//  2. KAAM KE WAQT KE BAAHAR GHANTI NAHI BAJEGI. Raat 2 baje
//     ki ghanti par koi kuch nahi kar sakta -- wo sirf aadmi ko
//     ye sikhati hai ki ghanti ko anna-suna kar dena theek hai.
//     Aur jis din asli ghanti bajegi, us din bhi wo nahi dekhega.
//
//  Ye file kuch BADALTI NAHI -- sirf dekh kar batati hai.
// ============================================================

/** काम का वक़्त — इसके बाहर घंटी नहीं बजती */
function ghanti_waqt($ghanta = null) {
    $ghanta = $ghanta === null ? (int)date('G') : (int)$ghanta;
    return $ghanta >= 7 && $ghanta <= 21;
}

/**
 * अभी क्या रुका है।
 *
 * Lautata hai: har ruki cheez ek line -- ['kya','ginti','kahan','der']
 * Khali array = sab chal raha hai.
 */
function ghanti_dekho(PDO $pdo) {
    $ek = function (string $sql) use ($pdo) {
        try { return (int)$pdo->query($sql)->fetchColumn(); }
        catch (Throwable $e) { error_log('ghanti: ' . $e->getMessage()); return 0; }
    };
    $bajao = [];
    $jodo = function ($n, $kya, $kahan, $der = null) use (&$bajao) {
        if ((int)$n > 0) $bajao[] = ['ginti' => (int)$n, 'kya' => $kya, 'kahan' => $kahan, 'der' => $der];
    };

    // ---- 1. naya order, kisi ne chhua nahi (20 minute) ----
    // Sabse upar, kyunki yahi wo akela waqt hai jab graahak
    // intezaar me baitha hai aur use kuch pata nahi.
    $jodo($ek("SELECT COUNT(*) FROM orders WHERE status='Naya'
                 AND created_at < NOW() - INTERVAL 20 MINUTE"),
          'नया ऑर्डर 20 मिनट से पड़ा है — किसी ने छुआ नहीं', '/bpo/', '20 मिनट');

    // ---- 2. pakka ho gaya, par sarathi nahi mila (30 minute) ----
    $jodo($ek("SELECT COUNT(*) FROM orders WHERE status='Confirm' AND delivery_user IS NULL
                 AND updated_at < NOW() - INTERVAL 30 MINUTE"),
          'पक्का ऑर्डर 30 मिनट से बिना सारथी पड़ा है', '/admin/sarathi.php', '30 मिनट');

    // ---- 3. utha liya, par pahuncha nahi (2 ghante) ----
    $jodo($ek("SELECT COUNT(*) FROM orders WHERE status='Pickup'
                 AND picked_at < NOW() - INTERVAL 2 HOUR"),
          'सामान उठा हुआ है पर 2 घंटे से पहुँचा नहीं — सारथी को फ़ोन कीजिए',
          '/admin/super.php', '2 घंटे');

    // ---- 4. order khule hain par koi sarathi duty par nahi ----
    // Ye ginti nahi, haalat hai. Isliye tabhi bajti hai jab dono
    // sach hon: kaam pada hai AUR uthane wala koi nahi.
    $khule = $ek("SELECT COUNT(*) FROM orders WHERE status IN ('Naya','Confirm')");
    if ($khule > 0) {
        // duty driver_availability me hai, aur wahan samay UTC me
        // likha jata hai -- isliye UTC_TIMESTAMP(), NOW() nahi.
        // NOW() se ye ginti saadhe paanch ghante galat hoti.
        $hazir = $ek("SELECT COUNT(*) FROM driver_availability d
                        JOIN users u ON u.id = d.user_id
                       WHERE u.active=1 AND u.role IN ('delivery','rider')
                         AND d.available_until > UTC_TIMESTAMP()");
        if ($hazir === 0) {
            $bajao[] = ['ginti' => $khule, 'kya' => 'ऑर्डर खुले हैं और कोई सारथी ड्यूटी पर नहीं है',
                        'kahan' => '/admin/sarathi.php', 'der' => null];
        }
    }

    // ---- 5. graahak ki shikayat (1 ghanta) ----
    $jodo($ek("SELECT COUNT(*) FROM support_tickets WHERE status='new'
                 AND created_at < NOW() - INTERVAL 1 HOUR"),
          'ग्राहक की शिकायत एक घंटे से जवाब की राह देख रही है', '/admin/support.php', '1 घंटा');

    return $bajao;
}

/**
 * किसी सारथी ने काम किया पर उसका रेट ही तय नहीं है।
 *
 * Ye jaanch isliye hai ki bina rate ke "baaki paisa" ki ginti
 * CHUP-CHAAP 0 nikalti hai -- aur phir niche wala saathi_baaki()
 * kehta rehta hai "kisi ka kuch baaki nahi", jabki rider ne 20
 * order pahunchaye hain. Chup-chaap galat ginti sabse khatarnak
 * hoti hai: Ankit ko lagta hai sab chukta hai, aur rider sochta
 * hai usay thaga gaya.
 */
function saathi_rate_nahi(PDO $pdo) {
    require_once __DIR__ . '/sarathi.php';
    $n = 0;
    try {
        $st = $pdo->query("SELECT DISTINCT u.id FROM users u
                             JOIN orders o ON o.delivery_user = u.id AND o.status='Delivered'
                            WHERE u.active=1 AND u.role IN ('delivery','rider')");
        foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $uid) {
            if (sarathi_rate($pdo, (int)$uid) <= 0) $n++;
        }
    } catch (Throwable $e) { error_log('saathi_rate_nahi: ' . $e->getMessage()); }
    return $n;
}

/**
 * सारथी का साथी — किसका पैसा बाक़ी है।
 *
 * Ankit ne tay kiya: "delivery boy ko order par paisa milega,
 * achhi khasi salary nahi". Per order ka matlab ye hai ki rider
 * ka paisa HAR DIN banta hai -- aur agar dene me der hui, to wo
 * agle din nahi aayega. Salary wale aadmi ko mahine bhar rok sakte
 * hain; per order wale ko nahi, wo roz ka kamane wala hai.
 *
 * Ginti self-report se nahi -- pahunchaye hue order se banti hai
 * (sarathi_khata), aur jo diya gaya wo sarathi_khata table se.
 *
 * $din: itne din se purana baaki hi batana hai
 */
function saathi_baaki(PDO $pdo, $din = 3, $kam_se_kam = 100) {
    require_once __DIR__ . '/sarathi.php';
    $out = [];
    try {
        $st = $pdo->query("SELECT id, name FROM users
                            WHERE role IN ('delivery','rider') AND active=1");
        foreach ($st->fetchAll() as $r) {
            $k = sarathi_khata($pdo, (int)$r['id']);
            if ((int)$k['baaki'] < (int)$kam_se_kam) continue;

            // Kab se baaki hai -- aakhiri bhugtan ke baad ka pehla
            // pahunchaya hua order. Kabhi kuch na diya gaya ho to
            // pehla order hi.
            $q = $pdo->prepare(
                "SELECT MIN(COALESCE(o.delivered_at, o.updated_at)) FROM orders o
                  WHERE o.delivery_user = ? AND o.status='Delivered'
                    AND COALESCE(o.delivered_at, o.updated_at) >
                        COALESCE((SELECT MAX(paid_on) FROM sarathi_khata WHERE rider_user = ?),
                                 '2000-01-01')");
            $q->execute([(int)$r['id'], (int)$r['id']]);
            $se = $q->fetchColumn();
            if (!$se) continue;

            $kitne_din = (int)floor((time() - strtotime((string)$se)) / 86400);
            if ($kitne_din < (int)$din) continue;

            $out[] = ['id' => (int)$r['id'], 'naam' => $r['name'],
                      'baaki' => (int)$k['baaki'], 'din' => $kitne_din,
                      'kitne' => (int)$k['kul_n']];
        }
    } catch (Throwable $e) { error_log('saathi_baaki: ' . $e->getMessage()); }

    usort($out, fn($a, $b) => $b['din'] <=> $a['din']);
    return $out;
}
