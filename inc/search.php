<?php
// Shared search for delivery items, booking services and local businesses.
function market_matches($query, $text) {
    $words = preg_split('/\s+/u', mb_strtolower(trim($query)), -1, PREG_SPLIT_NO_EMPTY);
    if (!$words) return false;
    $text = mb_strtolower($text);
    foreach ($words as $word) {
        if (mb_strpos($text, $word) === false) return false;
    }
    return true;
}

function market_service_text($slug, $svc, $categories) {
    $text = $svc['name'] . ' ' . $svc['en'] . ' ' . $svc['tag'];
    foreach ($categories as $cat) {
        if ($cat['slug'] === $svc['cat']) $text .= ' ' . $cat['words'];
    }
    // Passenger and goods transport must not share each other's aliases.
    if ($slug === 'gaadi') $text = $svc['name'] . ' ' . $svc['en'] . ' taxi auto bolero scorpio bus ambulance sawari गाड़ी बोलेरो ऑटो बस सवारी एम्बुलेंस';
    if ($slug === 'maal') $text = $svc['name'] . ' ' . $svc['en'] . ' truck tempo pickup tractor trolley shifting dala maal chhota hathi ट्रक टेम्पो पिकअप ट्रैक्टर ट्रॉली ढुलाई छोटा हाथी';
    foreach ($svc['fields'] as $field) {
        foreach ($field['o'] ?? [] as $option) $text .= ' ' . $option;
    }
    return $text;
}
