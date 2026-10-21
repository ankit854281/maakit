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

require_once __DIR__ . '/dhindhora.php';   // aaj ka sandesh taiyar hai ya nahi

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
    //
    // DHYAN: pehle yahan sirf FAST2SMS_ENABLED aur FAST2SMS_API_KEY
    // dekhe jate the. Wo GALAT tha. Asli code (f2s_enabled) ye
    // poochta hai:
    //
    //     (FAST2SMS_ENABLED ya OTP_ENABLED) AUR
    //     (WhatsApp taiyar HO YA Fast2SMS taiyar ho)
    //
    // Yani WhatsApp se bhi kaam chal jata hai. Agar Ankit WhatsApp
    // laga leta aur ye file sirf Fast2SMS poochti rehti, to ye kaam
    // KABHI poora na hota -- roz wahi dikhta, chahe OTP chal raha
    // ho. Aisa kaam poore bot ka bharosa kha jata hai.
    //
    // Isliye ab wahi sawaal poocha jata hai jo code khud poochta
    // hai -- ek hi jagah se jawab, do jagah se nahi.
    require_once __DIR__ . '/../smartsarathi/inc/fast2sms.php';
    if (!f2s_enabled()) {
        // Ek kaam batana kaafi nahi -- wo batana chahiye jo ASLI me
        // ruka hua hai. Teen haalat ho sakti hain, aur teeno ka
        // kaam alag hai. "Fast2SMS account banaiye" un dino bhi
        // dikhta rehta jab account pehle se bana hua hai aur sirf
        // ek line baaki hai -- aur aadmi wahi ek line kabhi nahi
        // dhoondh pata.
        $key  = f2s_config('FAST2SMS_API_KEY') !== '';
        $chalu = f2s_config('FAST2SMS_ENABLED') === '1' || f2s_config('OTP_ENABLED') === '1';

        if ($key && $chalu && f2s_mode() !== 'otp' && f2s_config('FAST2SMS_LOGIN_ID') === '') {
            // Sabse kareeb wali haalat: chaabi lagi hai, par route
            // 'smart' hai jisme DLT template ID chahiye. Ankit ko
            // DLT nahi mil sakta -- use 'otp' route chahiye, jisme
            // nahi lagti. Ye ek line ka kaam hai.
            return ['kya'   => "config.php में एक लाइन जोड़िए: define('FAST2SMS_ROUTE', 'otp');",
                    'kyun'  => 'Fast2SMS की चाबी लगी हुई है, पर रास्ता "smart" है जिसमें DLT template ID '
                             . 'चाहिए। "otp" रास्ते में DLT नहीं लगता और login OTP चल जाता है। '
                             . 'बस यही एक लाइन बाक़ी है।',
                    'kahan' => '',
                    'kitna' => 'bada'];
        }
        if ($key && !$chalu) {
            return ['kya'   => "config.php में एक लाइन जोड़िए: define('FAST2SMS_ENABLED', '1');",
                    'kyun'  => 'चाबी लगी है पर OTP बंद है — इसी एक लाइन से चालू हो जाएगा।',
                    'kahan' => '',
                    'kitna' => 'bada'];
        }
        return ['kya'   => 'Fast2SMS पर मुफ़्त account बनाइए, API key config.php में डालिए, '
                         . "और साथ में define('FAST2SMS_ROUTE', 'otp'); भी लिखिए",
                'kyun'  => 'इसके बिना OTP नहीं जाएगा — और OTP के बिना पहला ऑर्डर नहीं होगा। '
                         . '"otp" रास्ते में DLT की ज़रूरत नहीं पड़ती। (WhatsApp Business से भी '
                         . 'काम चल जाता है, जो चाहें।)',
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

    // ---- सारथी का पैसा ----
    // Ankit ne tay kiya: "order par paisa milega, salary nahi".
    // Per order kamane wala roz ka kamane wala hota hai. Salary
    // wale aadmi ko mahine bhar rok sakte hain; isko nahi -- wo
    // chup-chaap kisi aur ka kaam pakad lega, aur jis din order
    // aayega us din uthane wala koi nahi hoga.
    try {
        require_once __DIR__ . '/ghanti.php';

        // Pehle rate, phir paisa. Bina rate ke "baaki paisa" ki
        // ginti chup-chaap 0 nikalti hai -- aur phir lagta hai sab
        // chukta hai, jabki rider ne bees order pahunchaye hain.
        $jodo(saathi_rate_nahi($pdo),
              'सारथी ने काम किया पर उसका रेट ही तय नहीं है — प्रति ऑर्डर रेट भरिए',
              'बिना रेट के हिसाब चुपचाप ज़ीरो निकलता है। आपको लगेगा सब चुकता है, '
              . 'और सारथी को लगेगा उसे ठगा गया।', '/admin/sarathi.php');

        $baaki = saathi_baaki($pdo);
        if ($baaki) {
            $p = $baaki[0];
            $ruke[] = ['n' => 0, 'kitna' => 'roz',
                       'kya'   => $p['naam'] . ' का ₹' . $p['baaki'] . ' ' . $p['din']
                                  . ' दिन से बाक़ी है — आज दे दीजिए',
                       'kyun'  => 'ऑर्डर पर कमाने वाला रोज़ का कमाने वाला है। पैसा रुका तो '
                                  . 'वो कहीं और काम पकड़ लेगा, और ऑर्डर वाले दिन कोई नहीं होगा।',
                       'kahan' => '/admin/sarathi.php'];
        }
    } catch (Throwable $e) { error_log('ek-kaam saathi: ' . $e->getMessage()); }

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

    // Sabse neeche, kyunki ye graahak ko rok nahi raha -- par bot ne
    // kaam kar diya hai aur wo bekaar pada hai. Packet tabhi dikhta
    // hai jab aap use dekh kar chalu karein.
    $jodo($ginti("SELECT COUNT(*) FROM packs WHERE status='suggested'"),
          'बॉट ने पैकेट बना दिए हैं — देख कर चालू कीजिए',
          'जब तक आप नहीं देखेंगे, ग्राहक को कोई पैकेट नहीं दिखेगा।', '/admin/packet.php');

    // ---- ढिंढोरची का संदेश ----
    // Sabse neeche, kyunki koi graahak iske liye intezaar nahi kar
    // raha. Par ye wo ek kaam hai jo NAYA graahak laata hai -- baaki
    // sab purane graahak ko sambhalta hai.
    //
    // Dhyan: ye kaam tabhi dikhta hai jab aaj ka sandesh liya nahi
    // gaya. Warna ye roz dikhta rehta aur kabhi "ho gaya" na hota --
    // aur aisa kaam poore bot ka bharosa kha jata hai.
    try {
        if (dhindhora_bheja($pdo) !== date('Y-m-d') && dhindhora_aaj($pdo)) {
            // ginti 0 = naam ke aage koi number nahi lagega
            $ruke[] = ['n' => 0, 'kitna' => 'roz',
                       'kya'   => 'आज का संदेश तैयार है — WhatsApp पर भेज दीजिए',
                       'kyun'  => 'यही एक काम नया ग्राहक लाता है; बाक़ी सब पुराने ग्राहक को सँभालता है।',
                       'kahan' => '/admin/dhindhora.php'];
        }
    } catch (Throwable $e) { error_log('ek-kaam dhindhora: ' . $e->getMessage()); }

    if ($ruke) {
        $p = $ruke[0];
        // Ginti naam ke saath likhiye -- "2 dukaanein" aur "20
        // dukaanein" dekh kar aadmi alag faisla leta hai. Jiski
        // ginti ka matlab nahi (jaise aaj ka sandesh), uske aage
        // n=0 hota hai aur number nahi lagta.
        if ((int)$p['n'] > 0) $p['kya'] = $p['n'] . ' — ' . $p['kya'];
        return $p;
    }

    return null;    // kuch ruka nahi hai
}

/** chhota roop — bot aur panne, dono ke liye ek hi sach */
function ek_kaam_line(PDO $pdo) {
    $k = ek_kaam($pdo);
    return $k ? $k['kya'] : null;
}
