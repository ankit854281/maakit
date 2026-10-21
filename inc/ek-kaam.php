<?php
// ============================================================
//  एक काम — रोज़ का एक ही काम
//
//  Ankit ke paas jaankari ki kami nahi hai. Super panel sab
//  dikhata hai, muneem sab ginta hai, chowkidar khabar deta hai.
//
//  Jo cheez nahi thi wo ye hai: har subah ek SAAF jawab ki
//  "aaj mujhe kaun sa EK kaam karna hai".
//
//  Kyunki hafton se wahi cheezein ruki padi hain -- Fast2SMS ka
//  account, Sarathi ka setup, pehli dukaan -- aur har baar koi
//  NAYI cheez ban jaati hai jabki purani wahin ruki rehti hai.
//
//  IS FILE KE TEEN NIYAM
//  ---------------------
//  1. EK HI KAAM LAUTEGA. Doosra nahi, teesra nahi. Teen kaam
//     dikhane ka matlab hai koi bhi na hona -- aadmi sabse aasan
//     wala uthata hai, zaroori wala nahi.
//
//  2. KRAM MAYNE RAKHTA HAI. Soochi uss kram me hai jisme ek ke
//     ruke rehne se BAAKI SAB ruka hua hai. OTP nahi jata to
//     pehla order nahi hoga, chahe sau dukaanein jud jayein.
//
//  3. JAB TAK HO NA JAYE, WAHI DIKHEGA. Kal bhi wahi, parson
//     bhi wahi. Badalne ka ek hi tareeka hai -- kar dena.
//
//  Ye file kuch BADALTI NAHI -- sirf dekh kar batati hai.
// ============================================================

/**
 * Aaj ka ek kaam.
 *
 * Lautata hai: ['kya','kyun','kahan','kitna'] ya null (sab theek).
 *   kya   — ek line me kaam
 *   kyun  — ye kyun ruka hua sabse zaroori hai
 *   kahan — link (ya khali, agar kaam Maakit ke bahar ka hai)
 *   kitna — 'bada' | 'roz'   (bada = launch ruka hai)
 */
function ek_kaam(PDO $pdo) {
    $ginti = function (string $sql) use ($pdo) {
        try { return (int)$pdo->query($sql)->fetchColumn(); }
        catch (Throwable $e) { error_log('ek-kaam: ' . $e->getMessage()); return 0; }
    };

    // ---------- 1. OTP ----------
    // Sabse upar isliye: iske bina graahak login hi nahi kar
    // payega, aur sarathi ko pakka code nahi milega. Sau dukaanein
    // jud jayein, pehla order phir bhi nahi hoga.
    $otp = defined('FAST2SMS_ENABLED') && FAST2SMS_ENABLED
        && defined('FAST2SMS_API_KEY') && trim((string)FAST2SMS_API_KEY) !== '';
    if (!$otp) {
        return ['kya'   => 'Fast2SMS पर मुफ़्त account बनाइए और उसकी API key config.php में डालिए',
                'kyun'  => 'इसके बिना OTP नहीं जाएगा — और OTP के बिना पहला ऑर्डर नहीं होगा। '
                         . 'DLT की ज़रूरत नहीं है, सिर्फ़ account चाहिए।',
                'kahan' => 'https://www.fast2sms.com',
                'kitna' => 'bada'];
    }

    // ---------- 2. ilaaka ----------
    // Bina chalu ilaake ke graahak ka pata hi nahi liya jata.
    if ($ginti("SELECT COUNT(*) FROM villages v
                  JOIN service_area_meta m ON m.village_id = v.id
                 WHERE v.live = 1 AND m.delivery_on = 1") === 0) {
        return ['kya'   => 'कम से कम एक इलाक़ा चालू कीजिए',
                'kyun'  => 'जब तक कोई इलाक़ा चालू नहीं, ग्राहक अपना पता चुन ही नहीं सकता।',
                'kahan' => '/admin/areas.php',
                'kitna' => 'bada'];
    }

    // ---------- 3. dukaan ----------
    if ($ginti("SELECT COUNT(*) FROM businesses WHERE status='approved'") === 0) {
        return ['kya'   => 'पहली दुकान जोड़िए और मंज़ूर कीजिए',
                'kyun'  => 'दुकान नहीं तो सामान नहीं, और सामान नहीं तो ऑर्डर नहीं।',
                'kahan' => '/admin/businesses.php',
                'kitna' => 'bada'];
    }

    // ---------- 4. daam ----------
    // Bina daam ka saamaan graahak ke liye hai hi nahi.
    if ($ginti("SELECT COUNT(*) FROM shop_items i JOIN businesses b ON b.id = i.business_id
                 WHERE b.status='approved' AND i.active=1 AND i.price > 0") === 0) {
        return ['kya'   => 'कम से कम 10 सामान के दाम भरवाइए',
                'kyun'  => 'बिना दाम का सामान ग्राहक मँगा ही नहीं सकता — वो दिखता ही नहीं।',
                'kahan' => '/admin/daam.php',
                'kitna' => 'bada'];
    }

    // ---------- 5. sarathi ----------
    if ($ginti("SELECT COUNT(*) FROM users WHERE role IN ('delivery','rider') AND active=1") === 0) {
        return ['kya'   => 'एक सारथी जोड़िए',
                'kyun'  => 'ऑर्डर आ गया और पहुँचाने वाला नहीं — ये सबसे बुरी शुरुआत होगी।',
                'kahan' => '/admin/users.php',
                'kitna' => 'bada'];
    }

    // ---------- 6. pehla order ----------
    // Yahan tak sab taiyar hai. Ab ek baar KHUD karke dekhna
    // zaroori hai -- graahak par pehli baar aazmana sabse mehnga
    // tareeka hai kami dhoondhne ka.
    if ($ginti("SELECT COUNT(*) FROM orders WHERE status='Delivered'") === 0) {
        return ['kya'   => 'अपने फ़ोन से एक ऑर्डर ख़ुद करके, पूरा पहुँचा कर देखिए',
                'kyun'  => 'सब तैयार है। पहली बार ग्राहक पर आज़माना सबसे महँगा तरीक़ा होता है — '
                         . 'जो कमी निकलेगी वो आज निकल जाए।',
                'kahan' => '/order.php',
                'kitna' => 'bada'];
    }

    // ================= ab dhandha chalu hai =================
    // Yahan se "bada" kaam khatam. Ab roz ka kaam -- jo ruka hai
    // use aage badhana. Yahan bhi EK hi lautega, kram se.

    $ruke = [];
    $jodo = function ($n, $kya, $kyun, $kahan) use (&$ruke) {
        if ((int)$n > 0) $ruke[] = ['n' => (int)$n, 'kya' => $kya, 'kyun' => $kyun,
                                    'kahan' => $kahan, 'kitna' => 'roz'];
    };

    // Graahak intezaar me hai -- sabse upar
    $jodo($ginti("SELECT COUNT(*) FROM orders WHERE status='Pickup'
                    AND picked_at < NOW() - INTERVAL 3 HOUR"),
          'तीन घंटे से उठा हुआ ऑर्डर पहुँचा नहीं — सारथी को फ़ोन कीजिए',
          'ग्राहक इंतज़ार में है और उसे कुछ पता नहीं।', '/admin/super.php');

    $jodo($ginti("SELECT COUNT(*) FROM orders WHERE status='Confirm' AND delivery_user IS NULL"),
          'पक्के ऑर्डर हैं जिन्हें कोई सारथी नहीं मिला',
          'या कोई हाज़िर नहीं है, या उस इलाक़े में कोई जुड़ा नहीं।', '/admin/sarathi.php');

    $jodo($ginti("SELECT COUNT(*) FROM orders WHERE status='Naya'"),
          'नए ऑर्डर पड़े हैं जिन्हें किसी ने छुआ नहीं — दाम पूछकर पक्का कीजिए',
          'जितनी देर, उतना ज़्यादा मौक़ा कि ग्राहक दुकान चला जाए।', '/bpo/');

    $jodo($ginti("SELECT COUNT(*) FROM transports WHERE verified=0 AND status='approved'"),
          'गाड़ी के काग़ज़ अपनी आँख से देखकर verified कीजिए',
          'बिना देखे मंज़ूरी देना सबसे बड़ा ख़तरा है — ज़िम्मेदारी आप पर आएगी।',
          '/admin/transport.php');

    $jodo($ginti("SELECT COUNT(*) FROM sarathi_samasya WHERE nipta=0"),
          'सारथी ने रास्ते की दिक्कत बताई है — उसे निपटाइए',
          'जवाब न मिले तो सारथी अगली बार बताना बंद कर देता है।', '/admin/sarathi.php');

    $jodo($ginti("SELECT COUNT(*) FROM support_tickets WHERE status IN ('new','reviewing')"),
          'ग्राहक की शिकायत जवाब की राह देख रही है',
          'गाँव में एक नाराज़ ग्राहक चार और लोगों को बता देता है।', '/admin/support.php');

    $jodo($ginti("SELECT COUNT(*) FROM businesses WHERE status='pending'"),
          'दुकानें आपकी मंज़ूरी का इंतज़ार कर रही हैं',
          'वो जुड़ने आई थीं — इंतज़ार करवाने से वापस चली जाएँगी।',
          '/admin/businesses.php');

    $jodo($ginti("SELECT COUNT(*) FROM shop_items i JOIN businesses b ON b.id=i.business_id
                   WHERE b.status='approved' AND i.active=1 AND (i.price IS NULL OR i.price<=0)"),
          'बिना दाम का सामान पड़ा है — दुकानदार से दाम भरवाइए',
          'ग्राहक उसे मँगा ही नहीं सकता, वो सूची में दिखता तक नहीं।', '/admin/daam.php');

    if ($ruke) {
        $p = $ruke[0];
        $p['kya'] = $p['n'] . ' — ' . $p['kya'];
        return $p;
    }

    return null;    // kuch ruka nahi hai
}

/** chhota roop — bot aur panne, dono ke liye ek hi sach */
function ek_kaam_line(PDO $pdo) {
    $k = ek_kaam($pdo);
    return $k ? $k['kya'] : null;
}
