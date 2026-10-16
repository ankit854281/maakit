<?php
// ============================================================
//  जाँच — site ke panne kholkar dekhna
//
//  Har panne ke liye teen baatein:
//    code    — kaunsa HTTP code aana chahiye
//    chahiye — ye shabd panne par honi chahiye
//    nahi    — ye shabd panne par NAHI honi chahiye
//
//  "nahi" wali soochi sabse kaam ki hai: panna 200 de kar bhi
//  galat cheez dikha sakta hai. Ek baar aisa ho chuka hai —
//  booking ke 8 dibbe "किताब नहीं मिली" dikhane lage the aur
//  HTTP code 200 hi tha.
//
//  Naya panna banaiye to yahan ek line jod dijiye.
// ============================================================

$BASE = 'http://127.0.0.1:8099';

$PANNE = [
    ['/robots.txt', 'Robots', 'chahiye'=>['Sitemap: https://maakit.in/sitemap.xml']],
    ['/sitemap.xml', 'Sitemap', 'chahiye'=>['urlset','https://maakit.in/bazaar.php']],
    // ---- grahak ke panne ----
    // Bina chune bhasha English rehti hai, isliye Hindi wale shabd
    // ?lang=hi par jaanchne chahiye.
    ['/?lang=hi', 'होम (हिन्दी)',
        'chahiye' => ['Maakit', 'श्रेणी चुनिए', 'सब देखिए', 'लिस्ट भेजिए', 'दुकानदार'],
        'nahi'    => ['Fatal error', 'Warning:', 'Notice:', 'Undefined']],

    ['/?lang=en', 'होम (English)',
        'chahiye' => ['Maakit', 'Shop by category', 'See all', 'Send a list', 'Sell on Maakit'],
        'nahi'    => ['Fatal error', 'Undefined']],

    ['/order.php', 'सामान और ऑर्डर',
        'chahiye' => ['Maakit'], 'nahi' => ['Fatal error', 'Undefined']],

    ['/search.php?lang=hi&q=%E0%A4%86%E0%A4%9F%E0%A4%BE', 'खोज — आटा',
        'chahiye' => ['खोज', 'आटा'], 'nahi' => ['Fatal error', 'Undefined']],

    ['/search.php?lang=hi&q=zzqqxx', 'खोज — जो नहीं है',
        'chahiye' => ['Maakit'], 'nahi' => ['Fatal error', 'Undefined']],

    ['/bazaar.php?lang=hi', 'दुकान categories',
        'chahiye' => ['196', 'किराना'], 'nahi' => ['Fatal error', 'Warning:', 'Undefined']],
    ['/bazaar.php?type=Grocery%20%2F%20Kirana%20Store&lang=hi', 'किराना सामान',
        'chahiye' => ['आटा', 'दाम'], 'nahi' => ['Fatal error', 'Warning:', 'Undefined']],

    // sewa.php booking ka panna hai. book.php KITAAB ka hai.
    // Ye do ek baar aapas me badal gaye the — tabse yahi jaanch.
    ['/sewa.php', 'बुकिंग की सूची',
        'chahiye' => ['Maakit'],
        'nahi'    => ['किताब नहीं मिली', 'Book not found', 'Fatal error']],

    ['/sewa.php?s=safar', 'गाड़ी — सवारी या सामान',
        'chahiye' => ['Maakit'],
        'nahi'    => ['किताब नहीं मिली', 'Fatal error']],

    ['/books.php',     'पुरानी किताबें', 'chahiye' => ['Maakit'], 'nahi' => ['Fatal error']],
    ['/directory.php', 'दुकानें',        'chahiye' => ['Maakit'], 'nahi' => ['Fatal error']],
    ['/transport.php', 'गाड़ी रजिस्टर',  'chahiye' => ['Maakit'], 'nahi' => ['Fatal error']],
    ['/track.php',     'ऑर्डर कहाँ है',  'chahiye' => ['Maakit'], 'nahi' => ['Fatal error']],
    ['/track.php?lang=en&no=BK-TEST-UNKNOWN&m=9000000000', 'पुराने booking link का सही रास्ता',
        'chahiye'=>['That booking number and mobile do not match.'],
        'nahi'=>['That order number and mobile do not match.', 'Fatal error']],
    ['/track.php?lang=en&b=BK-TEST-UNKNOWN&m=9000000000', 'नया booking link',
        'chahiye'=>['That booking number and mobile do not match.'], 'nahi'=>['Fatal error']],
    ['/register-business.php', 'दुकान दर्ज कीजिए',
        'chahiye' => ['Maakit', 'niyam.php?k=dukan'], 'nahi' => ['Fatal error']],

    // ---- नियम और शर्तें ----
    // Paise ka niyam har hisse me saaf likha hona chahiye. Jis din
    // ye line gayab hogi, us din jhagde ke waqt Maakit ke paas kuch
    // likha hua nahi hoga -- aur yahi line Maakit ko RBI ke payment
    // aggregator wale daayre se bahar rakhti hai.
    //
    // Dhyan: panna default ANGREZI me khulta hai, isliye Hindi
    // shabd maangne wali jaanch me ?lang=hi lagana zaroori hai.
    ['/niyam.php?lang=hi', 'नियम — बिना चुने भी खुले',
        'chahiye' => ['नियम और शर्तें', 'Maakit दुकान नहीं है', 'ग्राहक के लिए'],
        'nahi'    => ['Fatal error']],
    ['/niyam.php?lang=hi&k=dukan', 'नियम — दुकानदार',
        'chahiye' => ['दुकानदार के लिए', 'सामान का पूरा पैसा सीधे आपका है', 'दाम आप डालते हैं'],
        'nahi'    => ['Fatal error']],
    ['/niyam.php?lang=hi&k=sarathi', 'नियम — सारथी',
        'chahiye' => ['सारथी के लिए', 'कोई तय shift नहीं',
                      'Maakit आपका पैसा अपने पास नहीं रखता',
                      'बीमा अभी Maakit की तरफ़ से नहीं है', 'लाइव जगह साझा करना'],
        'nahi'    => ['Fatal error']],
    ['/niyam.php?lang=hi&k=graahak', 'नियम — ग्राहक',
        'chahiye' => ['ग्राहक के लिए', 'वो पैसा कभी Maakit के पास नहीं आता', 'राइडर की लाइव जगह'],
        'nahi'    => ['Fatal error']],
    ['/niyam.php?lang=hi&k=sab', 'नियम — सब एक साथ',
        'chahiye' => ['ग्राहक के लिए', 'दुकानदार के लिए', 'सारथी के लिए', 'सबके लिए'],
        'nahi'    => ['Fatal error']],

    // Koi ulta-seedha k bheje to panna tootna nahi chahiye, aur jo
    // bheja wo HTML ban kar panne par nahi aana chahiye.
    //
    // Dhyan: panne par <script> waise bhi hote hain (site ke apne),
    // isliye unhe ginna bekar hai. Asli sawal ye hai ki BHEJI HUI
    // cheez kacchi laut kar aayi ya nahi -- yaani quote tod kar
    // tag khul gaya ya nahi. Wahi jaancha jata hai.
    ['/niyam.php?lang=hi&k=%22%3E%3Cscript%3Ealert(1)%3C/script%3E', 'नियम — ग़लत k भेजने पर',
        'chahiye' => ['ग्राहक के लिए'],
        // Sirf wo nishan jo SIRF tab banta hai jab quote tooti ho.
        // '"><' akela mat jaanchiye -- wo saadharan HTML me bhi hota
        // hai (<span class="x"><b>), aur phir jaanch jhoothi chetavni
        // deti hai.
        'nahi'    => ['Fatal error', '"><script', "'><script", '<script>alert']],
    ['/location.php', 'सेवा क्षेत्र', 'chahiye'=>['Maakit'], 'nahi'=>['Fatal error']],
    ['/support.php', 'सहायता', 'chahiye'=>['Maakit'], 'nahi'=>['Fatal error']],
    ['/admin/shipment.php', 'Courier जानकारी बिना login', 'code'=>302],
    ['/admin/readiness.php', 'Launch तैयारी बिना login', 'code'=>302],
    ['/admin/coverage.php', 'क्षेत्र बिना लॉगिन', 'code'=>302],
    ['/admin/support.php', 'सहायता बिना लॉगिन', 'code'=>302],
    ['/area.php',      'नया गाँव',      'chahiye' => ['Maakit'], 'nahi' => ['Fatal error']],

    // ---- login — ek hi darwaza ----
    // "मेरा हिसाब" login ke BAAD aata hai. Yahan dikh gaya to
    // andar ka panna bina login ke khula pada hai.
    ['/login.php', 'दुकान / टीम लॉगिन',
        'chahiye' => ['name="mobile"', 'name="code"', 'Maakit'],
        'nahi'    => ['मेरा हिसाब', 'Fatal error']],

    ['/login.php?as=team', 'टीम का लॉगिन',
        'chahiye' => ['name="username"', 'name="password"'],
        'nahi'    => ['मेरा हिसाब', 'Fatal error']],

    // bina login ke dukaan ka panel band hona chahiye
    ['/shop.php', 'दुकान पैनल बिना लॉगिन',
        'code' => 302, 'location' => '/login.php'],

    // bina login ke admin band hona chahiye
    ['/admin/', 'एडमिन बिना लॉगिन',
        'code' => 302],
];

// ------------------------------------------------------------

function lo($url) {
    $c = curl_init($url);
    curl_setopt_array($c, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER         => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_PROXY          => '',   // apni hi machine hai — proxy se mat jaiye
    ]);
    $raw  = curl_exec($c);
    $code = (int)curl_getinfo($c, CURLINF