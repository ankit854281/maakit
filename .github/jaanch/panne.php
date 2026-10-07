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
        'chahiye' => ['Maakit', 'कुछ भी चाहिए', 'कैसे काम करता है', 'क्यों Maakit', 'दुकानदार'],
        'nahi'    => ['Fatal error', 'Warning:', 'Notice:', 'Undefined']],

    ['/?lang=en', 'होम (English)',
        'chahiye' => ['Maakit', 'How does it work', 'Why Maakit', 'shopkeeper'],
        'nahi'    => ['Fatal error', 'Undefined']],

    ['/order.php', 'सामान और ऑर्डर',
        'chahiye' => ['Maakit'], 'nahi' => ['Fatal error', 'Undefined']],

    ['/search.php?lang=hi&q=%E0%A4%86%E0%A4%9F%E0%A4%BE', 'खोज — आटा',
        'chahiye' => ['खोज', 'आटा'], 'nahi' => ['Fatal error', 'Undefined']],

    ['/search.php?lang=hi&q=zzqqxx', 'खोज — जो नहीं है',
        'chahiye' => ['Maakit'], 'nahi' => ['Fatal error', 'Undefined']],

    ['/bazaar.php?lang=hi', 'दुकान categories',
        'chahiye' => ['196', '1510', 'किराना'], 'nahi' => ['Fatal error', 'Warning:', 'Undefined']],
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
    ['/register-business.php', 'दुकान दर्ज कीजिए', 'chahiye' => ['Maakit'], 'nahi' => ['Fatal error']],
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
        'chahiye' => ['मैं दुकानदार हूँ', 'मैं Maakit टीम से हूँ', 'कोड'],
        'nahi'    => ['मेरा हिसाब', 'Fatal error']],

    ['/login.php?as=team', 'टीम का लॉगिन',
        'chahiye' => ['यूज़रनेम', 'पासवर्ड'],
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
    $code = (int)curl_getinfo($c, CURLINFO_HTTP_CODE);
    $hlen = (int)curl_getinfo($c, CURLINFO_HEADER_SIZE);
    $err  = curl_error($c);
    curl_close($c);
    if ($raw === false) return [0, '', '', $err];
    return [$code, substr($raw, 0, $hlen), substr($raw, $hlen), ''];
}

$kharab = [];
$theek  = 0;

foreach ($PANNE as $p) {
    $path = $p[0];
    $naam = $p[1];
    $chahiyeCode = $p['code'] ?? 200;

    list($code, $head, $body, $err) = lo($BASE . $path);

    $gadbad = [];
    if ($err !== '')             $gadbad[] = "खुला ही नहीं — $err";
    if ($code !== $chahiyeCode)  $gadbad[] = "code $code आया, $chahiyeCode आना चाहिए था";

    if (!empty($p['location']) && !preg_match('~^location:\s*' . preg_quote($p['location'], '~') . '~mi', $head)) {
        $gadbad[] = "'{$p['location']}' पर भेजना चाहिए था";
    }
    foreach ($p['chahiye'] ?? [] as $shabd) {
        if (mb_strpos($body, $shabd) === false) $gadbad[] = "'$shabd' नहीं मिला";
    }
    foreach ($p['nahi'] ?? [] as $shabd) {
        if (mb_strpos($body, $shabd) !== false) $gadbad[] = "'$shabd' दिख रहा है — नहीं दिखना चाहिए";
    }

    if ($gadbad) {
        $kharab[] = [$path, $naam, $gadbad];
        echo "❌ $naam  ($path)\n";
        foreach ($gadbad as $g) echo "      · $g\n";
    } else {
        $theek++;
        echo "✅ $naam\n";
    }
}

echo "\n----------------------------------------\n";
echo count($PANNE) . " पन्ने जाँचे · $theek ठीक · " . count($kharab) . " ख़राब\n";

if ($kharab) {
    foreach ($kharab as list($path, $naam, $g)) {
        echo "::error title=जाँच — $naam::$path — " . implode('; ', $g) . "\n";
    }
    exit(1);
}
echo "सब ठीक है ✓\n";
