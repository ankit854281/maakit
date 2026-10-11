<?php
// ============================================================
// Tyohaar ki patti — home page ke upar apne aap badalti hai.
//
// Tareekhein 2026 ke panchang se (rajya ke hisaab se ek din aage
// peeche ho sakti hain). Admin > समय/छुट्टी se patti band ki ja
// sakti hai (setting: festival_off = 1).
// Har saal is list me agle saal ki tareekh jod dijiye.
// ============================================================

function maakit_festivals() {
    return [
        ['key' => 'navratri', 'from' => '2026-10-11', 'to' => '2026-10-19',
         'en' => 'Shubh Navratri', 'hi' => 'शुभ नवरात्रि',
         'sub_en' => 'Puja samagri, fruits, sabudana and vrat food from your local shops.',
         'sub_hi' => 'पूजा सामग्री, फल, साबूदाना और व्रत का सामान — आपकी पास की दुकानों से।',
         'cta_en' => 'Shop Navratri essentials', 'cta_hi' => 'नवरात्रि का सामान', 'q' => 'pooja',
         'bg' => '#9D174D', 'deep' => '#6B0F35', 'accent' => '#FBBF24'],
        ['key' => 'dussehra', 'from' => '2026-10-20', 'to' => '2026-10-20',
         'en' => 'Happy Dussehra', 'hi' => 'विजयादशमी की शुभकामनाएँ',
         'sub_en' => 'Sweets and puja items delivered today.', 'sub_hi' => 'मिठाई और पूजा का सामान आज ही।',
         'cta_en' => 'Order sweets', 'cta_hi' => 'मिठाई मँगाइए', 'q' => 'mithai',
         'bg' => '#B45309', 'deep' => '#7C2D12', 'accent' => '#FDE68A'],
        ['key' => 'karwa', 'from' => '2026-10-27', 'to' => '2026-10-29',
         'en' => 'Karwa Chauth', 'hi' => 'करवा चौथ',
         'sub_en' => 'Karwa, puja thali, sweets and fruits.', 'sub_hi' => 'करवा, पूजा थाली, मिठाई और फल।',
         'cta_en' => 'Shop now', 'cta_hi' => 'सामान चुनिए', 'q' => 'pooja',
         'bg' => '#9F1239', 'deep' => '#4C0519', 'accent' => '#FBBF24'],
        ['key' => 'diwali', 'from' => '2026-11-04', 'to' => '2026-11-11',
         'en' => 'Shubh Deepawali', 'hi' => 'शुभ दीपावली',
         'sub_en' => 'Diyas, lights, sweets, dry fruits and puja items.', 'sub_hi' => 'दीये, लाइट, मिठाई, मेवा और पूजा का सामान।',
         'cta_en' => 'Shop Diwali', 'cta_hi' => 'दीवाली का सामान', 'q' => 'diya',
         'bg' => '#7C2D12', 'deep' => '#431407', 'accent' => '#FCD34D'],
        ['key' => 'chhath', 'from' => '2026-11-12', 'to' => '2026-11-16',
         'en' => 'Chhath Puja', 'hi' => 'छठ पूजा',
         'sub_en' => 'Soop, fruits, sugarcane and thekua items.', 'sub_hi' => 'सूप, फल, गन्ना और ठेकुआ का सामान।',
         'cta_en' => 'Shop Chhath items', 'cta_hi' => 'छठ का सामान', 'q' => 'fal',
         'bg' => '#C2410C', 'deep' => '#7C2D12', 'accent' => '#FDE68A'],
    ];
}

/** aaj kaunsa tyohaar chal raha hai (ya null) */
function maakit_festival_now($pdo = null, $today = null) {
    if ($pdo instanceof PDO && function_exists('setg')) {
        try { if (setg($pdo, 'festival_off', '0') === '1') return null; } catch (Throwable $e) {}
    }
    $d = $today ?: date('Y-m-d');
    foreach (maakit_festivals() as $f) if ($d >= $f['from'] && $d <= $f['to']) return $f;
    return null;
}

/** festival ki tasveer — sirf SVG, hamari apni banai hui */
function maakit_festival_art($f) {
    $a = $f['accent'];
    // genda phool ki toran (upar latakti)
    $toran = '';
    for ($i = 0; $i < 9; $i++) {
        $x = 20 + $i * 32; $len = 26 + (($i % 3) * 10);
        $toran .= '<line x1="' . $x . '" y1="0" x2="' . $x . '" y2="' . $len . '" stroke="' . $f['bg'] . '" stroke-opacity=".35" stroke-width="1.5"/>'
                . '<circle cx="' . $x . '" cy="' . ($len + 6) . '" r="7" fill="#FB923C"/>'
                . '<circle cx="' . $x . '" cy="' . ($len + 6) . '" r="3" fill="#F97316"/>';
    }
    // diya
    $diya = '<g transform="translate(205 150)"><path d="M-46 0 Q0 40 46 0 Z" fill="' . $f['bg'] . '"/>'
          . '<path d="M-46 0 H46" stroke="#fff" stroke-opacity=".6" stroke-width="2"/>'
          . '<path d="M0 -6 C -12 -24 -2 -40 0 -52 C 2 -40 12 -24 0 -6 Z" fill="#FDE68A"/>'
          . '<path d="M0 -12 C -6 -22 -1 -30 0 -36 C 1 -30 6 -22 0 -12 Z" fill="#F97316"/></g>';
    // dandiya (sirf navratri)
    $dandiya = $f['key'] === 'navratri'
        ? '<g stroke-linecap="round"><line x1="70" y1="190" x2="140" y2="90" stroke="' . $f['bg'] . '" stroke-width="7"/>'
          . '<line x1="140" y1="190" x2="70" y2="90" stroke="#F97316" stroke-width="7"/>'
          . '<circle cx="140" cy="90" r="6" fill="#F97316"/><circle cx="70" cy="90" r="6" fill="' . $f['bg'] . '"/></g>'
        : '';
    return '<svg class="fest-art" viewBox="0 0 300 220" aria-hidden="true" focusable="false">'
         . '<circle cx="205" cy="120" r="88" fill="#fff" fill-opacity=".45"/>'
         . '<circle cx="205" cy="120" r="62" fill="#fff" fill-opacity=".45"/>'
         . $toran . $dandiya . $diya . '</svg>';
}
