<?php
// ============================================================
// Maakit ka DAKIYA — grahak ko bhejne wale sandesh
//
// Sare WhatsApp sandesh yahin ek jagah hain. Panel me sirf ek
// button hota hai; shabd yahan se aate hain. Isse:
//   - har grahak ko ek jaisi baat jati hai
//   - shabd badalne ho to ek hi jagah badalna padta hai
//   - har sandesh me "apna order dekhiye" ka link apne aap judta hai
//
// Dakiya khud kuchh nahi bhejta. Wo sirf sandesh likh kar deta
// hai — bhejne ka button staff dabata hai, apne hi phone se.
// (Apne aap bhejne ke liye WhatsApp Business API chahiye, jo
// paid hai. Wo baad me juda ja sakta hai — shabd wahi rahenge.)
// ============================================================

/** website ka pata — sandesh me link ke liye */
function dak_ghar() {
    $h = $_SERVER['HTTP_HOST'] ?? 'maakit.in';
    if (!preg_match('/^[a-z0-9.\-]+$/i', $h)) $h = 'maakit.in';
    return 'https://' . $h;
}

/** "apna order dekhiye" wala link */
function dak_link($no, $mobile, $booking = false) {
    return dak_ghar() . '/track.php?' . ($booking ? 'b=' : 'no=') . rawurlencode($no)
         . '&m=' . rawurlencode(preg_replace('/\D/', '', $mobile));
}

/** paisa likhne ka ek hi tareeka */
function dak_rs($n) { return '₹' . number_format((int)$n); }

/**
 * Order ka sandesh.
 * $kaun: 'confirm' | 'nikla' | 'pahuncha' | 'der'
 */
function dak_order($o, $kaun = 'confirm') {
    $no   = $o['order_no'] ?? '';
    $code = $o['code'] ?? '';
    $link = dak_link($no, $o['mobile'] ?? '');
    $chrg = !empty($o['first_order']) ? 'पहली डिलीवरी फ़्री' : dak_rs($o['delivery_charge'] ?? 0);

    switch ($kaun) {

        case 'nikla':
            // Yahi wo pal hai jab grahak sabse zyada soch me hota hai
            return "*Maakit* — सामान निकल चुका है\n\n"
                 . "ऑर्डर: {$no}\n"
                 . ($o['shop'] ? "दुकान: {$o['shop']}\n" : "")
                 . "डिलीवरी पार्टनर रास्ते में है।\n\n"
                 . "कहाँ तक पहुँचा, यहाँ देखिए:\n{$link}\n\n"
                 . "सामान लेते समय कोड *{$code}* बताइए।";

        case 'pahuncha':
            return "*Maakit* — पहुँचा दिया ✅\n\n"
                 . "ऑर्डर: {$no}\n"
                 . (!empty($o['goods_amount']) ? "सामान: " . dak_rs($o['goods_amount']) . "\n" : "")
                 . "डिलीवरी: {$chrg}\n\n"
                 . "दुकान का ही दाम लिया गया है, बिल के साथ।\n"
                 . "कोई दिक़्क़त हो तो आज ही बता दीजिए — हम ठीक कर देंगे।\n\n"
                 . "धन्यवाद 🙏";

        case 'der':
            // Der ho to chhupane se bharosa tootta hai. Pehle bata dena behtar hai.
            return "*Maakit* — थोड़ी देर हो रही है\n\n"
                 . "ऑर्डर: {$no}\n"
                 . "माफ़ कीजिए, आज थोड़ा समय लग रहा है। आपका सामान छूटा नहीं है।\n\n"
                 . "कहाँ तक पहुँचा: {$link}\n\n"
                 . "ज़्यादा ज़रूरी हो तो फ़ोन कर लीजिए — " . MAAKIT_NUMBER_SHOW;

        case 'confirm':
        default:
            return "*Maakit* — ऑर्डर मिल गया\n\n"
                 . "ऑर्डर: {$no}\n"
                 . (!empty($o['items']) ? "सामान: {$o['items']}\n" : "")
                 . ($o['shop'] ? "दुकान: {$o['shop']}\n" : "")
                 . "डिलीवरी: {$chrg}\n\n"
                 . "*कोड: {$code}*\n"
                 . "यह कोड सिर्फ़ सामान लेते समय, डिलीवरी पार्टनर को बताइए। किसी और को नहीं।\n\n"
                 . "अपना ऑर्डर यहाँ देखिए:\n{$link}";
    }
}

/**
 * Booking ka sandesh.
 * $kaun: 'mili' | 'rate' | 'pakki'
 */
function dak_booking($b, $kaun = 'mili', $rate = null) {
    $no   = $b['booking_no'] ?? '';
    $link = dak_link($no, $b['mobile'] ?? '', true);
    $sewa = '';
    if (function_exists('service_get')) {
        $s = service_get($b['service'] ?? '');
        if ($s) $sewa = is_hi() ? $s['name'] : $s['en'];
    }

    switch ($kaun) {

        case 'rate':
            return "*Maakit* — आपकी बुकिंग का रेट\n\n"
                 . "बुकिंग: {$no}\n"
                 . ($sewa ? "सेवा: {$sewa}\n" : "")
                 . ($rate !== null ? "रेट: " . dak_rs($rate) . "\n" : "")
                 . "\nठीक लगे तो बता दीजिए, हम पक्की कर देंगे।\n"
                 . "कोई सवाल हो तो फ़ोन कर लीजिए — " . MAAKIT_NUMBER_SHOW;

        case 'pakki':
            return "*Maakit* — बुकिंग पक्की हो गई ✅\n\n"
                 . "बुकिंग: {$no}\n"
                 . ($sewa ? "सेवा: {$sewa}\n" : "")
                 . ($rate !== null ? "रेट: " . dak_rs($rate) . "\n" : "")
                 . "\nकोड: *" . ($b['code'] ?? '') . "*\n"
                 . "काम हो जाने पर ही यह कोड बताइए।\n\n"
                 . "अपनी बुकिंग यहाँ देखिए:\n{$link}";

        case 'mili':
        default:
            return "*Maakit* — बुकिंग मिल गई\n\n"
                 . "बुकिंग: {$no}\n"
                 . ($sewa ? "सेवा: {$sewa}\n" : "")
                 . "\nहम पता करके रेट बता देंगे। तब तक कोई पैसा नहीं लगता।\n\n"
                 . "अपनी बुकिंग यहाँ देखिए:\n{$link}";
    }
}

/** WhatsApp ka button — ek hi jagah se, taaki sab jagah ek jaisa dikhe */
function dak_btn($mobile, $text, $label, $cls = 'btn-green') {
    return '<a class="btn ' . h($cls) . ' btn-sm" target="_blank" rel="noopener" href="'
         . h(wa_link($mobile, $text)) . '">' . h($label) . '</a>';
}
