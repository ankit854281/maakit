<?php
// ============================================================
// Site ke rang — Admin > Settings se ek click me badalte hain.
//
// Har rang-set me wahi CSS variables hain jo style.css use karti
// hai, isliye poori site (header, button, card, tag) ek saath
// badal jaati hai. Nayi CSS me hamesha var(--brand) jaise naam
// likhiye, kabhi seedha hex nahi.
// ============================================================

function maakit_themes() {
    return [
        'indigo' => [
            'en' => 'Royal indigo and saffron', 'hi' => 'शाही नीला और केसरिया',
            'brand' => '#3D2C8D', 'brand-deep' => '#241A5C', 'gold' => '#FF9F1C', 'on-gold' => '#3A2200',
            'bg' => '#F7F6FC', 'soft' => '#ECE9FA', 'line' => '#DCD7F2', 'ink' => '#17142E', 'muted' => '#5B5677',
        ],
        'emerald' => [
            'en' => 'Emerald and gold', 'hi' => 'पन्ना हरा और सुनहरा',
            'brand' => '#0B7A55', 'brand-deep' => '#075A40', 'gold' => '#F5B700', 'on-gold' => '#3A2B00',
            'bg' => '#F3FAF6', 'soft' => '#DFF3EA', 'line' => '#C7E6D7', 'ink' => '#0D2A20', 'muted' => '#4B6A5E',
        ],
        'midnight' => [
            'en' => 'Midnight and coral', 'hi' => 'गहरा नीला और मूंगा',
            'brand' => '#14213D', 'brand-deep' => '#0B1426', 'gold' => '#FF7A59', 'on-gold' => '#3B0D02',
            'bg' => '#F6F7FA', 'soft' => '#E8ECF4', 'line' => '#D6DCE8', 'ink' => '#101828', 'muted' => '#556070',
        ],
        'plum' => [
            'en' => 'Plum and peach', 'hi' => 'बैंगनी और आड़ू',
            'brand' => '#6A2C70', 'brand-deep' => '#4A1D4F', 'gold' => '#FFB38A', 'on-gold' => '#3D1405',
            'bg' => '#FBF6FA', 'soft' => '#F3E6F1', 'line' => '#E6D2E3', 'ink' => '#2A1029', 'muted' => '#6E5470',
        ],
        'blue' => [
            'en' => 'Light blue', 'hi' => 'हल्का नीला',
            'brand' => '#2563EB', 'brand-deep' => '#1D4ED8', 'gold' => '#93C5FD', 'on-gold' => '#0B2E66',
            'bg' => '#F5F9FF', 'soft' => '#E6F0FD', 'line' => '#D3E2F5', 'ink' => '#0F1E33', 'muted' => '#4B5D75',
        ],
    ];
}

/** abhi kaunsa rang-set chal raha hai (Admin se chuna hua) */
function maakit_theme_key($pdo = null) {
    static $k = null;
    if ($k !== null) return $k;
    $all = maakit_themes();
    $saved = null;
    if ($pdo instanceof PDO && function_exists('setg')) {
        try { $saved = setg($pdo, 'site_theme', null); } catch (Throwable $e) { $saved = null; }
    }
    return $k = (is_string($saved) && isset($all[$saved])) ? $saved : 'indigo';
}

function maakit_theme($pdo = null) {
    $all = maakit_themes();
    return $all[maakit_theme_key($pdo)];
}

/** <style> jo style.css ke rang badal deta hai */
function maakit_theme_css($pdo = null) {
    $t = maakit_theme($pdo);
    $css = ':root{';
    foreach (['brand', 'brand-deep', 'gold', 'on-gold', 'bg', 'soft', 'line', 'ink', 'muted'] as $v) {
        if (preg_match('/^#[0-9A-Fa-f]{6}$/', $t[$v])) $css .= '--' . $v . ':' . $t[$v] . ';';
    }
    $css .= '--on-brand:#FFFFFF;--cream:' . $t['bg'] . ';}';
    return $css;
}

/** Maakit ka logo: gol badge me ghar ki chhat aur thaila */
function maakit_logo_svg($size = 38) {
    $n = (int)$size;
    return '<svg class="mk-logo" width="' . $n . '" height="' . $n . '" viewBox="0 0 40 40" role="img" aria-label="Maakit">'
        . '<rect x="1" y="1" width="38" height="38" rx="19" style="fill:var(--gold)"/>'
        . '<path d="M9.5 19.5 L20 11 L30.5 19.5" fill="none" style="stroke:var(--brand-deep)" stroke-width="2.8" stroke-linecap="round" stroke-linejoin="round"/>'
        . '<rect x="13.5" y="19" width="13" height="11" rx="3.2" fill="none" style="stroke:var(--brand-deep)" stroke-width="2.6"/>'
        . '<path d="M17 19v-1.4a3 3 0 0 1 6 0V19" fill="none" style="stroke:var(--brand-deep)" stroke-width="2.2" stroke-linecap="round"/>'
        . '<circle cx="20" cy="24.6" r="1.5" style="fill:var(--brand-deep)"/>'
        . '</svg>';
}
