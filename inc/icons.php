<?php
// ============================================================
// Maakit — icon system
// Ek hi tarah ki line, ek hi grid (24x24). Emoji nahi.
// Sab currentColor lete hain, isliye kahin bhi rang badal sakta hai.
// ============================================================

/** andar ka kaam: svg ka khol */
function _svg($body, $size, $sw = 1.6, $extra = '') {
    return '<svg width="' . (int)$size . '" height="' . (int)$size . '" viewBox="0 0 24 24" fill="none"'
        . ' stroke="currentColor" stroke-width="' . $sw . '" stroke-linecap="round" stroke-linejoin="round"'
        . ' aria-hidden="true" focusable="false"' . ($extra ? ' ' . $extra : '') . '>' . $body . '</svg>';
}

/** ---------- saaman ke icon ---------- */
function _prod_paths() {
    return [
        // anaj
        'sack'     => '<path d="M8.4 7.5h7.2c1.7 2 2.6 4.3 2.6 6.8 0 3.4-2.4 5.7-6.2 5.7s-6.2-2.3-6.2-5.7c0-2.5.9-4.8 2.6-6.8z"/><path d="M8.4 7.5c.7-1.4 1.3-2.4 1.3-3.1 0-.6.7-1 2.3-1s2.3.4 2.3 1c0 .7.6 1.7 1.3 3.1"/><path d="M9.6 13.2h4.8"/>',
        'rice'     => '<path d="M6.5 8.2h11l-1 10a1.8 1.8 0 0 1-1.8 1.6H9.3a1.8 1.8 0 0 1-1.8-1.6z"/><path d="M5.6 8.2 7.4 4.6h9.2l1.8 3.6"/><path d="M10.6 12.2v3.4M13.4 12.2v3.4"/>',
        'dal'      => '<path d="M3.8 11.5h16.4a8.2 8.2 0 0 1-16.4 0z"/><path d="M3 20.2h18"/><circle cx="9.5" cy="7.8" r="1.1"/><circle cx="13" cy="5.6" r="1.1"/><circle cx="15.2" cy="8.6" r="1.1"/>',
        // tel / peene ki botal
        'bottle'   => '<path d="M10 2.8h4v2.6l1.6 2a3 3 0 0 1 .7 1.9v9.6a2.3 2.3 0 0 1-2.3 2.3H10a2.3 2.3 0 0 1-2.3-2.3V9.3a3 3 0 0 1 .7-1.9l1.6-2z"/><path d="M7.7 12.6h8.6"/>',
        'tin'      => '<rect x="5.6" y="7" width="12.8" height="13.2" rx="1.6"/><path d="M8.4 7V4.8a1 1 0 0 1 1-1h5.2a1 1 0 0 1 1 1V7"/><path d="M9 11.4h6"/>',
        'sugar'    => '<rect x="4.8" y="8.6" width="14.4" height="11.6" rx="1.8"/><path d="M4.8 12.2h14.4"/><path d="M8.6 8.6V6a2 2 0 0 1 2-2h2.8a2 2 0 0 1 2 2v2.6"/><path d="M10.4 15.8h3.2"/>',
        'salt'     => '<path d="M7.6 9.4h8.8l1 9.2a1.6 1.6 0 0 1-1.6 1.8H8.2a1.6 1.6 0 0 1-1.6-1.8z"/><path d="M9 9.4V6.6a3 3 0 0 1 6 0v2.8"/><path d="M11 4.6v-1M13.4 5.2l.7-.8M10.6 5.2l-.7-.8"/>',
        'tea'      => '<path d="M4.6 8h12v5.6a5 5 0 0 1-5 5H9.6a5 5 0 0 1-5-5z"/><path d="M16.6 9.6h1.6a2.4 2.4 0 0 1 0 4.8h-1.6"/><path d="M3.6 21.2h14"/><path d="M8.4 5.2c0-1 .8-1.2.8-2.2M12 5.2c0-1 .8-1.2.8-2.2"/>',
        'spice'    => '<rect x="7" y="8.6" width="10" height="11.6" rx="1.8"/><path d="M8.8 8.6V6.4a1.4 1.4 0 0 1 1.4-1.4h3.6a1.4 1.4 0 0 1 1.4 1.4v2.2"/><path d="M9.4 5V3.6h5.2V5"/><path d="M10.4 13h3.2M10.4 16h3.2"/>',
        // dairy
        'milk'     => '<path d="M8.6 3.6h6.8v3.2l1.8 2.6v9.2a1.8 1.8 0 0 1-1.8 1.8H8.6a1.8 1.8 0 0 1-1.8-1.8V9.4l1.8-2.6z"/><path d="M6.8 12.4h10.4"/><path d="M8.6 6.8h6.8"/>',
        'curd'     => '<path d="M5.4 9h13.2l-1 9.6a2 2 0 0 1-2 1.8H8.4a2 2 0 0 1-2-1.8z"/><ellipse cx="12" cy="9" rx="6.6" ry="2.2"/><path d="M9.6 13.4c1.6.8 3.2.8 4.8 0"/>',
        'paneer'   => '<path d="M4.6 10.6 12 6.8l7.4 3.8-7.4 3.8z"/><path d="M4.6 10.6v5.6L12 20v-5.6"/><path d="M19.4 10.6v5.6L12 20"/>',
        'egg'      => '<path d="M12 3.4c3 0 5.4 4.3 5.4 8.4a5.4 5.4 0 0 1-10.8 0c0-4.1 2.4-8.4 5.4-8.4z"/><path d="M9.6 12.6a2.6 2.6 0 0 0 2.4 2.6"/>',
        // nashta
        'bread'    => '<path d="M4.4 9.8a3.4 3.4 0 0 1 3.4-3.4h8.4a3.4 3.4 0 0 1 3.4 3.4v.6h-2v8.2a1.4 1.4 0 0 1-1.4 1.4H7.8a1.4 1.4 0 0 1-1.4-1.4v-8.2h-2z"/><path d="M6.4 10.4h11.2"/>',
        'biscuit'  => '<rect x="3.6" y="7.6" width="16.8" height="8.8" rx="2.4"/><path d="M8 7.6v8.8M16 7.6v8.8"/><circle cx="12" cy="12" r="1"/>',
        'packet'   => '<path d="M6.2 7.6h11.6v11a1.6 1.6 0 0 1-1.6 1.6H7.8a1.6 1.6 0 0 1-1.6-1.6z"/><path d="M6.2 7.6 8 4.4h8l1.8 3.2"/><path d="M12 4.4v3.2"/><path d="M9.2 12h5.6M9.2 15.4h3.6"/>',
        'noodle'   => '<path d="M5 9.4h14l-1.2 8.8a2.2 2.2 0 0 1-2.2 1.9H8.4a2.2 2.2 0 0 1-2.2-1.9z"/><path d="M8.2 9.4c0-2.2 1.7-3.6 3.8-3.6s3.8 1.4 3.8 3.6"/><path d="M10 6.2c0-1 .6-1.6 1.4-2.4M13.4 6.4c.2-1 .8-1.6 1.6-2.2"/>',
        // sabzi / fal
        'onion'    => '<path d="M12 7.4c3.4 0 5.8 2.7 5.8 6.1A5.8 5.8 0 0 1 12 20.4a5.8 5.8 0 0 1-5.8-6.9c0-3.4 2.4-6.1 5.8-6.1z"/><path d="M12 7.4V20.4"/><path d="M9.4 8.2c-.8 3.4-.8 8.2 0 11.2M14.6 8.2c.8 3.4.8 8.2 0 11.2"/><path d="M12 7.4c-.6-1.6-1.6-2.6-2.6-3.2M12 7.4c.6-1.6 1.6-2.6 2.6-3.2"/>',
        'tomato'   => '<circle cx="12" cy="13.8" r="6.4"/><path d="M12 7.4V5.2"/><path d="M8.8 6.4c1 .4 1.8 1 2.4 1.9M15.2 6.4c-1 .4-1.8 1-2.4 1.9"/>',
        'leaf'     => '<path d="M4.4 19.6c0-6.6 4.6-11.2 11.2-11.2 1.6 0 3 .2 4 .6-.6 6.8-5.2 11.2-11.4 11.2-1.4 0-2.6-.2-3.8-.6z"/><path d="M4.4 19.6 13.8 10.2"/><path d="M15.6 8.4c.4-1.6.2-3-.6-4.4"/>',
        'citrus'   => '<circle cx="12" cy="13.4" r="6.6"/><circle cx="12" cy="13.4" r="4.4"/><path d="M12 9v1.6M12 16.2v1.6M7.6 13.4h1.6M14.8 13.4h1.6M9 10.4l1.1 1.1M14 16.4l1 1M15 10.4l-1.1 1.1M9 16.4l1.1-1.1"/><path d="M13.6 7.2c.4-1.8 1.4-2.8 3-3.2"/>',
        'apple'    => '<path d="M12 8.2c-1-1-2.2-1.4-3.4-1.4C6.4 6.8 5 9 5 12.2c0 4 2.4 7.4 4.6 7.4 1 0 1.6-.5 2.4-.5s1.4.5 2.4.5c2.2 0 4.6-3.4 4.6-7.4 0-3.2-1.4-5.4-3.6-5.4-1.2 0-2.4.4-3.4 1.4z"/><path d="M12 8.2V5.6a2.6 2.6 0 0 1 2.6-2.6"/>',
        'banana'   => '<path d="M5.4 5.2c0 7.4 4.6 12.4 11.4 12.4 1.5 0 2.6-.5 3.4-1.4-1 3-3.6 4.6-7.4 4.6C6.6 20.8 2.8 16.4 2.8 10.2c0-2.4.8-4.1 2.6-5z"/><path d="M5.4 5.2c0-1 .5-1.7 1.4-2M20.2 16.2c.9-.4 1.4-1 1.6-1.9"/>',
        // safai / sabun
        'soap'     => '<rect x="3.6" y="9.4" width="16.8" height="9.6" rx="3"/><path d="M7.4 9.4c0-2.6 2-4.4 4.6-4.4s4.6 1.8 4.6 4.4"/><path d="M6.8 13.8h4"/>',
        'detergent'=> '<path d="M6.6 8.6h10.8v10a1.6 1.6 0 0 1-1.6 1.6H8.2a1.6 1.6 0 0 1-1.6-1.6z"/><path d="M8.4 8.6V6a2 2 0 0 1 2-2h3.2a2 2 0 0 1 2 2v2.6"/><path d="M10.2 12.4c.8.8 2.8.8 3.6 0"/><path d="M12 15.6v1.8"/>',
        'broom'    => '<path d="M12 2.8v8.4"/><path d="M7.6 11.2h8.8l1.8 3.2H5.8z"/><path d="M5.8 14.4h12.4l-1.4 6.8H7.2z"/><path d="M10 14.4l-.8 6.8M14 14.4l.8 6.8"/>',
        'match'    => '<rect x="3.4" y="10.6" width="12" height="9.4" rx="1.4"/><path d="M6.4 10.6v9.4"/><path d="M18.6 11.6 17 20"/><path d="M18.6 11.6c1.4-1 1.9-2.1 1.5-3.2-.2-.7-.7-1.2-1.4-1.7-.6.6-1 1.2-1.1 1.9-.2 1.2.3 2.2 1 3z"/>',
        'incense'  => '<path d="M9 20.2h6"/><path d="M10.4 20.2 12 8.6l1.6 11.6"/><path d="M12 8.6c0-2 1.6-2.6 1.6-4.2 0-.8-.5-1.4-1.6-1.8-1.1.4-1.6 1-1.6 1.8 0 1.6 1.6 2.2 1.6 4.2z"/>',
        'brush'    => '<path d="M16.6 3.6a2 2 0 0 1 2.8 2.8L9 16.8l-3.6.8.8-3.6z"/><path d="M14.4 5.8 17.2 8.6"/>',
        'tube'     => '<path d="M8 8.4h8v10.4a1.6 1.6 0 0 1-1.6 1.6H9.6A1.6 1.6 0 0 1 8 18.8z"/><path d="M9.6 8.4V6.2h4.8v2.2"/><path d="M11 6.2V4h2v2.2"/><path d="M9.8 12h4.4"/>',
        'pad'      => '<rect x="5.4" y="5.6" width="13.2" height="12.8" rx="4"/><path d="M8.8 5.6v12.8M15.2 5.6v12.8"/>',
        'razor'    => '<path d="M6.6 3.6h10.8v4.8H6.6z"/><path d="M9.6 8.4v10.2a1.8 1.8 0 0 0 3.6 0V8.4"/><path d="M8.4 5.8h7.2"/>',
        // khana
        'thali'    => '<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="7"/><circle cx="9.2" cy="9.2" r="2.1"/><circle cx="14.8" cy="9.2" r="2.1"/><circle cx="12" cy="14.8" r="2.4"/>',
        'roti'     => '<ellipse cx="12" cy="12" rx="8.6" ry="7.4"/><path d="M8.2 10c.8.9 2 1.4 3.2 1.4M13.6 14.6c1-.2 2-.8 2.6-1.6"/><circle cx="14.6" cy="9.4" r=".9"/><circle cx="9.4" cy="14.2" r=".9"/>',
        'samosa'   => '<path d="M11.4 4.2 19 16.8a1.3 1.3 0 0 1-1.1 2H4.9a1.3 1.3 0 0 1-1.1-2z"/><path d="M11.4 7.6 16.4 16H6.4z"/><path d="M2.6 21.4h18.8"/>',
        'momo'     => '<circle cx="12" cy="13.6" r="5.6"/><path d="M8.2 11.4c1.6-2.1 6-2.1 7.6 0M9.4 8.8c1.2-1.4 4-1.4 5.2 0"/><path d="M3.4 21.4h17.2"/><path d="M9 4.4c0 1.4 1.3 1.4 1.3 2.8M13.7 3.8c0 1.4 1.3 1.4 1.3 2.8"/>',
        'sweet'    => '<circle cx="8.4" cy="14.6" r="3.6"/><circle cx="15.6" cy="14.6" r="3.6"/><circle cx="12" cy="8.6" r="3.6"/>',
        'cake'     => '<path d="M4.4 13.6c1.3 0 1.3 1.4 2.5 1.4s1.3-1.4 2.5-1.4 1.3 1.4 2.6 1.4 1.3-1.4 2.5-1.4 1.3 1.4 2.5 1.4 1.3-1.4 2.6-1.4"/><path d="M4.4 13.6v-1.4a2 2 0 0 1 2-2h11.2a2 2 0 0 1 2 2v1.4"/><path d="M4.4 15v3.8a1.6 1.6 0 0 0 1.6 1.6h12a1.6 1.6 0 0 0 1.6-1.6V15"/><path d="M12 10.2V7.4"/><path d="M12 7.4c.9-.7 1.2-1.4.9-2.1-.2-.5-.5-.8-.9-1-.4.2-.7.5-.9 1-.3.7 0 1.4.9 2.1z"/>',
        // peene ka
        'glass'    => '<path d="M6.6 5h10.8l-1.5 13.6a2 2 0 0 1-2 1.8h-3.8a2 2 0 0 1-2-1.8z"/><path d="M7 9h10"/>',
        'water'    => '<path d="M9.4 6.4h5.2v2l1.4 1.8v8.4a1.8 1.8 0 0 1-1.8 1.8H9.8A1.8 1.8 0 0 1 8 18.6v-8.4l1.4-1.8z"/><path d="M9.8 3.6h4.4v2.8H9.8z"/><path d="M8 13.2h8"/>',
        'cup'      => '<path d="M5 8.8h11v6a4.4 4.4 0 0 1-4.4 4.4H9.4A4.4 4.4 0 0 1 5 14.8z"/><path d="M16 10.4h1.6a2.2 2.2 0 0 1 0 4.4H16"/><path d="M4 21h13"/>',
        // anya
        'cylinder' => '<path d="M7.2 8.8h9.6v9.6a2 2 0 0 1-2 2H9.2a2 2 0 0 1-2-2z"/><path d="M9.4 8.8V6.6h5.2v2.2"/><path d="M10.6 6.6V4.8h2.8v1.8"/><path d="M14.6 5.4h2.2"/>',
        'candle'   => '<path d="M8.8 9.4h6.4v9.2a1.8 1.8 0 0 1-1.8 1.8h-2.8a1.8 1.8 0 0 1-1.8-1.8z"/><path d="M12 9.4V6.6"/><path d="M12 6.6c1-.9 1.4-1.8 1-2.6-.2-.5-.6-.9-1-1.2-.4.3-.8.7-1 1.2-.4.8 0 1.7 1 2.6z"/>',
        'battery'  => '<rect x="8.2" y="6.4" width="7.6" height="14" rx="1.6"/><path d="M10.2 6.4V4.4h3.6v2"/><path d="M12 10.2v6M9.8 13.2h4.4"/>',
        'bulb'     => '<path d="M12 3.4a5.8 5.8 0 0 1 3.6 10.4c-.7.6-1.1 1.3-1.1 2.1H9.5c0-.8-.4-1.5-1.1-2.1A5.8 5.8 0 0 1 12 3.4z"/><path d="M9.8 18.4h4.4M10.4 21h3.2"/>',
        'book'     => '<path d="M4.6 5.2A1.6 1.6 0 0 1 6.2 3.6h11.6a1.6 1.6 0 0 1 1.6 1.6v13.6a1.6 1.6 0 0 1-1.6 1.6H6.2a1.6 1.6 0 0 1-1.6-1.6z"/><path d="M8.2 3.6v16.8"/><path d="M11.4 8h4.6M11.4 11.4h4.6"/>',
        'pen'      => '<path d="M15.8 3.6 20.4 8.2 9.6 19H5v-4.6z"/><path d="M13.6 5.8 18.2 10.4"/>',
        'diya'     => '<path d="M4.6 15.4h14.8a5.6 5.6 0 0 1-5.6 4.4h-3.6a5.6 5.6 0 0 1-5.6-4.4z"/><path d="M12 15.4V11"/><path d="M12 11c1.6-1.4 2.2-2.9 1.6-4.2-.3-.8-.9-1.4-1.6-2-.7.6-1.3 1.2-1.6 2-.6 1.3 0 2.8 1.6 4.2z"/>',
        'flower'   => '<circle cx="12" cy="9.6" r="2.2"/><path d="M12 7.4c0-2 .9-3 2.8-3 .6 2-.3 3-2.8 3zM12 7.4c0-2-.9-3-2.8-3-.6 2 .3 3 2.8 3z"/><path d="M14.2 9.6c2 0 3 .9 3 2.8-2 .6-3-.3-3-2.8zM9.8 9.6c-2 0-3 .9-3 2.8 2 .6 3-.3 3-2.8z"/><path d="M12 11.8v8.6"/>',
        'bag'      => '<path d="M5.4 7.8h13.2l-1 11.2a1.8 1.8 0 0 1-1.8 1.6H8.2a1.8 1.8 0 0 1-1.8-1.6z"/><path d="M8.8 7.8V6.2a3.2 3.2 0 0 1 6.4 0v1.6"/>',
        'pill'     => '<rect x="2.8" y="9" width="18.4" height="6" rx="3" transform="rotate(-45 12 12)"/><path d="M9.2 9.2 14.8 14.8"/>',
        'tools'    => '<path d="M14.8 6.4a3.6 3.6 0 0 0 4.8 4.8l-7.6 7.6a2.4 2.4 0 0 1-3.4-3.4z"/><path d="M14.8 6.4 17.8 3.4"/><path d="M5.2 19.4l1.4 1.4"/>',
    ];
}

/**
 * Saaman ka icon — naam dekh kar chuna jata hai.
 * Koi mel na mile to group ka icon.
 */
function _prod_map() {
    static $map = null;
    if ($map === null) {
        $map = [
            'आटा'=>'sack','मैदा'=>'sack','सूजी'=>'sack','बेसन'=>'sack','सत्तू'=>'sack','मक्के'=>'sack',
            'चावल'=>'rice','पोहा'=>'rice','दलिया'=>'rice',
            'दाल'=>'dal','चना'=>'dal','राजमा'=>'dal',
            'तेल'=>'bottle','घी'=>'tin','वनस्पति'=>'tin',
            'चीनी'=>'sugar','गुड़'=>'sugar','नमक'=>'salt',
            'चाय'=>'tea','कॉफ़ी'=>'cup',
            'हल्दी'=>'spice','मिर्च'=>'spice','धनिया पाउडर'=>'spice','मसाला'=>'spice','जीरा'=>'spice',
            'राई'=>'spice','अजवाइन'=>'spice','हींग'=>'spice','तेज पत्ता'=>'spice','मेथी'=>'spice','सौंफ'=>'spice',
            'दूध'=>'milk','दही'=>'curd','पनीर'=>'paneer','मक्खन'=>'tin','अंडे'=>'egg','खोया'=>'paneer',
            'ब्रेड'=>'bread','बिस्कुट'=>'biscuit','रस्क'=>'bread','नमकीन'=>'packet','मैगी'=>'noodle',
            'कॉर्नफ्लेक्स'=>'packet','चिप्स'=>'packet','मूँगफली'=>'packet',
            'आलू'=>'onion','प्याज़'=>'onion','टमाटर'=>'tomato','लहसुन'=>'onion','अदरक'=>'leaf',
            'हरी मिर्च'=>'leaf','धनिया पत्ती'=>'leaf','भिंडी'=>'leaf','लौकी'=>'leaf','बैंगन'=>'leaf',
            'गोभी'=>'leaf','पालक'=>'leaf','नींबू'=>'citrus','कद्दू'=>'leaf','परवल'=>'leaf',
            'मटर'=>'leaf','गाजर'=>'leaf','शिमला'=>'leaf',
            'केला'=>'banana','सेब'=>'apple','संतरा'=>'citrus','पपीता'=>'apple','अंगूर'=>'apple',
            'अनार'=>'apple','अमरूद'=>'apple','तरबूज़'=>'apple','नारियल तेल'=>'bottle','नारियल'=>'apple',
            'पाउडर'=>'detergent','टिकिया'=>'soap','लिक्विड'=>'detergent','फ़िनाइल'=>'bottle','क्लीनर'=>'bottle',
            'झाड़ू'=>'broom','माचिस'=>'match','अगरबत्ती'=>'incense','कॉइल'=>'packet','कचरा'=>'packet',
            'साबुन'=>'soap','शैम्पू'=>'bottle','टूथपेस्ट'=>'tube','टूथब्रश'=>'brush','पैड'=>'pad',
            'डायपर'=>'pad','शेविंग'=>'tube','रेज़र'=>'razor','कंघी'=>'brush','क्रीम'=>'tube',
            'समोसा'=>'samosa','कचौड़ी'=>'samosa','पकौड़ी'=>'samosa','पूरी'=>'roti','भटूरे'=>'roti',
            'पराठा'=>'roti','रोटी'=>'roti','थाली'=>'thali','चाट'=>'thali','गोलगप्पे'=>'thali',
            'बिरयानी'=>'rice','राइस'=>'rice','चाउमीन'=>'noodle','मोमोज़'=>'momo','डिम सम'=>'momo','बर्गर'=>'bread',
            'पिज़्ज़ा'=>'thali','चिकन'=>'thali','मटन'=>'thali','करी'=>'thali','वेज'=>'thali','दाल-चावल'=>'rice',
            'जलेबी'=>'sweet','रसगुल्ला'=>'sweet','जामुन'=>'sweet','लड्डू'=>'sweet','बर्फ़ी'=>'sweet',
            'पेड़ा'=>'sweet','इमरती'=>'sweet','रसमलाई'=>'sweet','केक'=>'cake','पेस्ट्री'=>'cake',
            'पैटीज़'=>'bread','रोल'=>'roti',
            'कोल्ड'=>'bottle','पानी'=>'water','लस्सी'=>'glass','मट्ठा'=>'glass','जूस'=>'glass','शेक'=>'glass',
            'सिलेंडर'=>'cylinder','मोमबत्ती'=>'candle','बैटरी'=>'battery','बल्ब'=>'bulb','कॉपी'=>'book',
            'पेन'=>'pen','रीचार्ज'=>'book','रस्सी'=>'tools','तार'=>'tools','पूजा'=>'diya','फूल'=>'flower',
            'चारा'=>'sack','खाद'=>'sack','दवा'=>'pill',
        ];
    }
    return $map;
}

/** saaman ka icon konsa hai — sirf naam (JS ko yahi bhejte hain) */
function prod_icon_key($name, $grp = '') {
    $p = _prod_paths();
    foreach (_prod_map() as $k => $v) {
        if (mb_strpos($name, $k) !== false && isset($p[$v])) { return $v; }
    }
    $g = ['anaj'=>'sack','tel'=>'bottle','masala'=>'spice','dairy'=>'milk','nashta'=>'biscuit',
          'sabzi'=>'leaf','fal'=>'apple','safai'=>'soap','sabun'=>'tube','khana'=>'thali','chaat'=>'noodle',
          'mithai'=>'sweet','peene'=>'glass','pooja'=>'diya','khad'=>'leaf','anya'=>'bag'];
    return $g[$grp] ?? 'bag';
}

function prod_icon($name, $grp = '', $size = 30) {
    $p = _prod_paths();
    return _svg($p[prod_icon_key($name, $grp)], $size);
}

/** sidha key se icon */
function prod_icon_by_key($key, $size = 26) {
    $p = _prod_paths();
    return _svg($p[$key] ?? $p['bag'], $size);
}

/** JS ke liye: sirf wahi raaste jo is page par chahiye */
function prod_icon_paths($keys) {
    $p = _prod_paths(); $out = [];
    foreach (array_unique($keys) as $k) { if (isset($p[$k])) $out[$k] = $p[$k]; }
    return $out;
}

/**
 * Sewa (service) ka bada icon — home page ki tiles ke liye.
 * Do rang: bhari hui chhaya + line, taaki app jaisa lage.
 */
function svc_icon($key, $size = 40) {
    $sh = 'fill="currentColor" opacity=".13" stroke="none"';   // peeche ki chhaya
    $I = [
      'grocery' => '<path '.$sh.' d="M4 8h16l-1.4 12H5.4z"/><path d="M5.4 8h13.2l-1.2 11.4a1.8 1.8 0 0 1-1.8 1.6H8.4a1.8 1.8 0 0 1-1.8-1.6z"/><path d="M8.8 8V6.4a3.2 3.2 0 0 1 6.4 0V8"/><path d="M9.4 12.4h5.2"/>',
      'food'    => '<path '.$sh.' d="M3.5 17h17v2h-17z"/><path d="M4.4 16.8a7.6 7.6 0 0 1 15.2 0z"/><path d="M2.8 19.8h18.4"/><path d="M12 9.2V7.4"/><circle cx="12" cy="6.2" r="1.2"/>',
      'medicine'=> '<path '.$sh.' d="M7 3.5h10v17H7z"/><rect x="6.2" y="3.4" width="11.6" height="17.2" rx="2.4"/><path d="M12 8.4v7.2M8.4 12h7.2"/>',
      'ride'    => '<path '.$sh.' d="M3 13h18v4H3z"/><path d="M3.4 16.6v-3.2l2-4.4A2.4 2.4 0 0 1 7.6 7.6h8.8a2.4 2.4 0 0 1 2.2 1.4l2 4.4v3.2"/><path d="M3.4 13.4h17.2"/><circle cx="7.2" cy="17.4" r="1.8"/><circle cx="16.8" cy="17.4" r="1.8"/><path d="M9 17.4h6"/>',
      'lawn'    => '<path '.$sh.' d="M5 10h14v10H5z"/><path d="M4.2 20.4V11a7.8 7.8 0 0 1 15.6 0v9.4"/><path d="M2.8 20.4h18.4"/><path d="M12 20.4v-5a2.6 2.6 0 0 1 5.2 0v5"/><path d="M12 3.2V5"/><path d="M8.6 12.4h3.4"/>',
      'tent'    => '<path '.$sh.' d="M12 5 21 20H3z"/><path d="M12 4.4 21.2 20.4H2.8z"/><path d="M12 10.4v10"/><path d="M7.4 20.4 12 13.4l4.6 7"/>',
      'halwai'  => '<path '.$sh.' d="M3.5 12h17l-1.5 7h-14z"/><path d="M3.4 12.4h17.2a8.6 8.6 0 0 1-17.2 0z"/><path d="M2.4 20.6h19.2"/><path d="M8.6 9.2c0-1.8 1.2-2 1.2-3.6M13.2 9.2c0-1.8 1.2-2 1.2-3.6"/>',
      'pandit'  => '<path '.$sh.' d="M4.5 15h15l-2 5h-11z"/><path d="M4.4 15.2h15.2a5.8 5.8 0 0 1-5.8 4.6h-3.6a5.8 5.8 0 0 1-5.8-4.6z"/><path d="M12 15.2v-4.6"/><path d="M12 10.6c1.7-1.5 2.3-3 1.7-4.4-.3-.8-.9-1.5-1.7-2.1-.8.6-1.4 1.3-1.7 2.1-.6 1.4 0 2.9 1.7 4.4z"/>',
      'salon'   => '<path '.$sh.' d="M5 4.5h14v15H5z"/><circle cx="6.2" cy="6.2" r="2.4"/><circle cx="6.2" cy="17.8" r="2.4"/><path d="M8.4 7.8 19.4 17.4M8.4 16.2 19.4 6.6"/>',
      'home'    => '<path '.$sh.' d="M14 6.5a3.6 3.6 0 0 0 4.8 4.8L11 19a2.4 2.4 0 0 1-3.4-3.4z"/><path d="M14.6 6.2a3.7 3.7 0 0 0 4.9 4.9l-7.8 7.8a2.5 2.5 0 0 1-3.5-3.5z"/><path d="M14.6 6.2 17.7 3.1"/><path d="M4.8 19.2l1.4 1.4"/>',
      'photo'   => '<path '.$sh.' d="M3 7.5h18v12H3z"/><rect x="3" y="7" width="18" height="13" rx="2.4"/><path d="M8 7l1.5-3h5L16 7"/><circle cx="12" cy="13.5" r="3.4"/>',
      'shops'   => '<path '.$sh.' d="M3 9h18v11H3z"/><path d="M3 9l1.6-5h14.8L21 9"/><path d="M3 9a2.2 2.2 0 0 0 4.5 0 2.2 2.2 0 0 0 4.5 0 2.2 2.2 0 0 0 4.5 0 2.2 2.2 0 0 0 4.5 0"/><path d="M4.6 11v9.4h14.8V11"/><path d="M10 20.4V15h4v5.4"/>',
      'truck'   => '<path '.$sh.' d="M2 13h12v4H2zM14 11h4l3 3v3h-7z"/><path d="M2.4 16.6V7.8a1.4 1.4 0 0 1 1.4-1.4h9.4a1.4 1.4 0 0 1 1.4 1.4v8.8"/><path d="M14.6 10h3.2l2.8 3.4v3.2"/><circle cx="7" cy="17.4" r="1.9"/><circle cx="17.4" cy="17.4" r="1.9"/><path d="M8.9 17.4h6.6M2.4 13.2h12.2"/>',
      'tractor' => '<path '.$sh.' d="M3 12h9v6H3z"/><circle cx="7" cy="16.4" r="4"/><circle cx="18.2" cy="17.4" r="2.6"/><path d="M3.4 12.6V8.4a1.4 1.4 0 0 1 1.4-1.4h3.4l1.6 4.2"/><path d="M11 12.6h7.2"/><path d="M14.6 12.6V9.4h4.2"/>',
      'all'     => '<path '.$sh.' d="M4 4h7v7H4zM13 4h7v7h-7zM4 13h7v7H4zM13 13h7v7h-7z"/><rect x="3.6" y="3.6" width="7" height="7" rx="1.8"/><rect x="13.4" y="3.6" width="7" height="7" rx="1.8"/><rect x="3.6" y="13.4" width="7" height="7" rx="1.8"/><rect x="13.4" y="13.4" width="7" height="7" rx="1.8"/>',
      'mic'     => '<rect x="9" y="2.6" width="6" height="11.4" rx="3"/><path d="M5.4 11.6a6.6 6.6 0 0 0 13.2 0"/><path d="M12 18.2v3.2M8.8 21.4h6.4"/>',
      'camera'  => '<rect x="2.8" y="6.8" width="18.4" height="13.4" rx="2.6"/><path d="M8.2 6.8 9.6 3.8h4.8l1.4 3"/><circle cx="12" cy="13.5" r="3.6"/>',
      'bill'    => '<path d="M5.6 3.4h12.8v17.2l-2.1-1.6-2.1 1.6-2.2-1.6-2.1 1.6-2.2-1.6-2.1 1.6z"/><path d="M8.8 8h6.4M8.8 11.6h6.4M8.8 15.2h3.6"/>',
      'wifi'    => '<path d="M2.8 9.4a14 14 0 0 1 18.4 0"/><path d="M6.2 13a9 9 0 0 1 11.6 0"/><path d="M9.6 16.6a4 4 0 0 1 4.8 0"/><circle cx="12" cy="20" r="1.1"/>',
      'shield'  => '<path d="M12 3.2 20 6v6.2c0 4.6-3.2 7.5-8 8.6-4.8-1.1-8-4-8-8.6V6z"/><path d="M8.8 12.2l2.2 2.2 4.2-4.4"/>',
      'clock'   => '<circle cx="12" cy="12" r="8.6"/><path d="M12 7v5.3l3.4 2"/>',
      'rupee'   => '<circle cx="12" cy="12" r="8.6"/><path d="M9.2 7.6h5.6M9.2 10.4h5.6M13 7.6c1.5 0 2.4 1 2.4 2.4s-.9 2.4-2.4 2.4H9.2l5.2 4.4"/>',
      'user'    => '<circle cx="12" cy="8.2" r="3.8"/><path d="M4.8 20.4a7.2 7.2 0 0 1 14.4 0"/>',
      'box'     => '<path d="M3.6 7.6 12 3.4l8.4 4.2v8.8L12 20.6l-8.4-4.2z"/><path d="M3.6 7.6 12 11.8l8.4-4.2M12 11.8v8.8"/>',
      'search'  => '<circle cx="11" cy="11" r="7"/><path d="M16.2 16.2 21 21"/>',
      'plus'    => '<path d="M12 5v14M5 12h14"/>',
      /* ---- purani kitaab wale ---- */
      'book'    => '<path '.$sh.' d="M5 4h13v16H5z"/><path d="M4.6 5.2A1.8 1.8 0 0 1 6.4 3.4H19v15.2H6.4a1.8 1.8 0 0 0-1.8 1.8z"/><path d="M4.6 18.6V5.2"/><path d="M8.4 7.4h7M8.4 10.6h4.6"/>',
      'books'   => '<path '.$sh.' d="M4 18h16v2.6H4z"/><path d="M4.2 20.4V6a1.6 1.6 0 0 1 1.6-1.6h2.4A1.6 1.6 0 0 1 9.8 6v14.4z"/><path d="M9.8 20.4V7.6A1.6 1.6 0 0 1 11.4 6h2.2a1.6 1.6 0 0 1 1.6 1.6v12.8z"/><path d="M15.2 20.4V9.8l3.4-.6 1.2 11z"/>',
      'school'  => '<path '.$sh.' d="M3 9.6 12 5l9 4.6-9 4.6z"/><path d="M2.6 9.6 12 4.8l9.4 4.8L12 14.4z"/><path d="M6.6 11.6v4.2c0 1.6 2.4 2.8 5.4 2.8s5.4-1.2 5.4-2.8v-4.2"/><path d="M21.4 9.6v4.6"/>',
      'swap'    => '<path d="M4 8.4h12.4"/><path d="m13.4 5.2 3.2 3.2-3.2 3.2"/><path d="M20 15.6H7.6"/><path d="m10.6 12.4-3.2 3.2 3.2 3.2"/>',
      'gift'    => '<path '.$sh.' d="M4 11h16v9.4H4z"/><rect x="3.4" y="10.6" width="17.2" height="9.8" rx="1.6"/><path d="M2.6 7.4h18.8v3.2H2.6zM12 7.4v13"/><path d="M12 7.4C10.6 4.4 9.4 3.4 8 3.4a2 2 0 0 0 0 4zM12 7.4c1.4-3 2.6-4 4-4a2 2 0 0 1 0 4z"/>',
      'tag'     => '<path d="M11.2 3.4H20v8.8l-8.6 8.6a1.6 1.6 0 0 1-2.3 0l-6.5-6.5a1.6 1.6 0 0 1 0-2.3z"/><circle cx="16.2" cy="7.8" r="1.5"/>',

      // ---- naye: home page ki shreniyon ke liye ----
      // samosa + ek momo — 20 minute wala nashta
      'chaat'   => '<path '.$sh.' d="m8.6 5.4 6.2 12.4H2.4z"/><path d="M8.6 4.8 15 17.6a.8.8 0 0 1-.7 1.2H2.9a.8.8 0 0 1-.7-1.2z"/><path d="M8.6 8.2 12.6 16H4.6z"/><circle cx="18.6" cy="12.4" r="3.8"/><path d="M16 11.4c1.1-1.4 4.1-1.4 5.2 0"/><path d="M2 20.6h20"/>',
      // diya — pooja ka saaman
      'pooja'   => '<path '.$sh.' d="M4.6 13.6h14.8a7.4 7.4 0 0 1-14.8 0z"/><path d="M12 11.8c-1.8-1.6-1.4-4 0-5.8 1.4 1.8 2 4.2 0 5.8z"/><path d="M4.4 13.6h15.2a7.6 7.6 0 0 1-15.2 0z"/><path d="M2.6 20.4h18.8"/>',
      // podha — khad, beej, chara
      'khad'    => '<path '.$sh.' d="M6 19.2h12v1.4H6z"/><path d="M12 20.4v-9.6"/><path d="M12 14.6c-3.2 0-5.6-2.2-5.6-5 3.2 0 5.6 2.1 5.6 5z"/><path d="M12 12.6c2.6 0 4.6-2 4.6-4.4-2.4 0-4.6 1.8-4.6 4.4z"/><path d="M5.6 20.4h12.8"/>',
      // pana aur pechkas — bijli, nal, mistri
      'tools'   => '<path d="M15.6 4.2a4 4 0 0 0-5.2 4.9l-5.8 5.8a2 2 0 0 0 2.8 2.8l5.8-5.8a4 4 0 0 0 4.9-5.2l-2.4 2.4-2.1-.4-.4-2.1z"/><path d="M5.2 19.6a1.1 1.1 0 1 1-1.6-1.6"/>',
      // mobile aur pechkas — phone, pankha, fridge ki marammat
      'mobile'  => '<path '.$sh.' d="M6.6 3.4h8v13h-8z"/><rect x="6" y="2.8" width="8.6" height="14.6" rx="1.8"/><path d="M9.4 5.4h1.8"/><path d="m15.4 15.6 3.2 3.2"/><path d="m17.8 13.8 3.2 3.2-1.8 1.8-3.2-3.2z"/>',
    ];
    $sw = ($key === 'plus') ? 2.2 : 1.6;
    return _svg($I[$key] ?? $I['all'], $size, $sw);
}
