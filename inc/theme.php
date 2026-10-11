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
    // Ek hi rang — malik ki pasand (11 Oct 2026): peacock teal.
    return [
        'teal' => [
            'en' => 'Peacock teal', 'hi' => 'मोर नीला-हरा',
            'brand' => '#0F766E', 'brand-deep' => '#115E59', 'gold' => '#F59E0B', 'on-gold' => '#3A2200',
            'bg' => '#F7F8F8', 'soft' => '#E6F4F2', 'line' => '#E3E7EA', 'ink' => '#111827', 'muted' => '#5B6472',
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
    return $k = (is_string($saved) && isset($all[$saved])) ? $saved : 'teal';
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
