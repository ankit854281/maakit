<?php
// ============================================================
// Maakit — group ka home page (landing page)
//
// Owner ka faisla, 1 November 2026:
//   maakit.in ka home page yahi landing page hai (grahak, ad aur
//   investor ke liye). Website par saaman ka order NAHI lagta —
//   order Maakit app se hoga; app aane tak phone / WhatsApp.
//   Purana catalogue wala home purana-ghar.php me hai — waapas
//   laana ho to wahi file index.php bana dijiye.
//
// Dusra round (owner, 1 Nov dopahar, furtilo.com dekh kar):
//   - rang badlo: Rani magenta + kesariya, gehra plum hero/footer
//   - intro: poori screen rangeen, naam bhashaon me tezi se badle,
//     phir haath se likha "Maakit"
//   - har service card me TASVEER aur animation
//   - neeche ke hisson ke kone gol (rounded)
//   Hero aur intro me halki "glow" (radial) owner ki maang par hai;
//   baaki site ka "no gradient" niyam isse nahi badalta.
//
// Tasveerein: sab inline SVG, Maakit ki apni banayi — web se koi
// photo nahi (AGENTS.md §4.1). Fonts self-hosted (Lexend, Kalam,
// Noto Sans Devanagari), Google Fonts link nahi.
//
// Sach ka niyam (§4): koi nakli ginti, offer ya daam nahi.
// ============================================================
require_once __DIR__ . '/inc/fn.php';

$wa   = defined('MAAKIT_WA') ? MAAKIT_WA : '918429393903';
$tel  = defined('MAAKIT_PHONE') ? MAAKIT_PHONE : '+918429393903';
$show = defined('MAAKIT_NUMBER_SHOW') ? MAAKIT_NUMBER_SHOW : '84293 93903';
$T    = function ($en, $hi) { return h(t($en, $hi)); };

// ---------- apni banayi tasveerein (320 x 180) ----------
function mk_art($k) {
  $B = '#D6246E'; $S = '#FFB020'; $P = '#2A1530'; $W = '#FFFFFF'; $K = '#F2C29B';
  switch ($k) {
  case 'shop': return '<circle class="sun" cx="44" cy="40" r="16" fill="'.$S.'"/><rect x="0" y="152" width="320" height="28" fill="#F6C9B0"/>'
    .'<rect x="92" y="62" width="140" height="92" rx="6" fill="'.$W.'"/><rect x="124" y="30" width="76" height="22" rx="7" fill="'.$P.'"/><rect x="138" y="38" width="48" height="6" rx="3" fill="'.$S.'"/>'
    .'<g><rect x="82" y="50" width="20" height="26" fill="'.$B.'"/><rect x="102" y="50" width="20" height="26" fill="'.$W.'"/><rect x="122" y="50" width="20" height="26" fill="'.$B.'"/><rect x="142" y="50" width="20" height="26" fill="'.$W.'"/><rect x="162" y="50" width="20" height="26" fill="'.$B.'"/><rect x="182" y="50" width="20" height="26" fill="'.$W.'"/><rect x="202" y="50" width="20" height="26" fill="'.$B.'"/><rect x="222" y="50" width="20" height="26" fill="'.$W.'"/></g>'
    .'<path d="M82 76q10 10 20 0q10 10 20 0q10 10 20 0q10 10 20 0q10 10 20 0q10 10 20 0q10 10 20 0q10 10 20 0" fill="'.$B.'"/>'
    .'<rect x="106" y="94" width="52" height="34" rx="4" fill="#DCEBFF"/><rect x="176" y="98" width="38" height="56" rx="4" fill="#8A3B12"/><circle cx="206" cy="128" r="2.5" fill="'.$S.'"/>'
    .'<g class="bob"><rect x="248" y="116" width="36" height="38" rx="5" fill="'.$S.'"/><path d="M256 118v-6a10 10 0 0 1 20 0v6" fill="none" stroke="'.$P.'" stroke-width="3"/></g>';
  case 'ride': return '<rect x="0" y="140" width="320" height="40" fill="#BFD6F5"/><path d="M0 160h320" stroke="'.$W.'" stroke-width="4" stroke-dasharray="18 14"/>'
    .'<path d="M36 100h40M24 114h44M40 128h34" stroke="#8DB3E8" stroke-width="4" stroke-linecap="round"/>'
    .'<g class="drive"><circle class="spin" cx="112" cy="136" r="17" fill="'.$P.'"/><circle cx="112" cy="136" r="6" fill="'.$W.'"/><circle class="spin" cx="204" cy="136" r="17" fill="'.$P.'"/><circle cx="204" cy="136" r="6" fill="'.$W.'"/>'
    .'<path d="M96 132h58l22-26h30l12 26h-12" fill="'.$B.'"/><rect x="128" y="112" width="30" height="8" rx="4" fill="'.$P.'"/>'
    .'<rect x="136" y="76" width="28" height="40" rx="11" fill="'.$S.'"/><circle cx="150" cy="62" r="13" fill="'.$K.'"/><path d="M136 60a14 14 0 0 1 28 0z" fill="'.$P.'"/><path d="M160 92l24 14" stroke="'.$S.'" stroke-width="8" stroke-linecap="round"/></g>';
  case 'parcel': return '<rect x="0" y="150" width="320" height="30" fill="#F3DFA0"/>'
    .'<path d="M70 150 C110 120 190 170 250 92" fill="none" stroke="'.$B.'" stroke-width="3" stroke-dasharray="7 7"/>'
    .'<g class="bob"><rect x="96" y="96" width="70" height="56" rx="4" fill="#E9A85A"/><rect x="126" y="96" width="10" height="56" fill="#F7D79F"/><rect x="160" y="110" width="58" height="42" rx="4" fill="#D98E3F"/><rect x="184" y="110" width="9" height="42" fill="#F7D79F"/><rect x="112" y="60" width="50" height="38" rx="4" fill="#F0BE7A"/><rect x="132" y="60" width="9" height="38" fill="#FBE3B8"/></g>'
    .'<g class="pin"><path d="M252 92c-14-16-22-26-22-38a22 22 0 0 1 44 0c0 12-8 22-22 38z" fill="'.$B.'"/><circle cx="252" cy="54" r="8" fill="'.$W.'"/></g>';
  case 'car': return '<rect x="0" y="146" width="320" height="34" fill="#B9E3C9"/><path d="M0 164h320" stroke="'.$W.'" stroke-width="4" stroke-dasharray="18 14"/>'
    .'<g class="drive"><path d="M64 138v-28l20-30h120l30 30h22v28z" fill="'.$W.'"/><path d="M92 86h50v28H74zM150 86h50l24 28h-74z" fill="#DCEBFF"/><rect x="64" y="120" width="192" height="8" fill="'.$B.'"/>'
    .'<circle class="spin" cx="102" cy="140" r="17" fill="'.$P.'"/><circle cx="102" cy="140" r="6" fill="'.$W.'"/><circle class="spin" cx="222" cy="140" r="17" fill="'.$P.'"/><circle cx="222" cy="140" r="6" fill="'.$W.'"/><rect x="248" y="112" width="10" height="6" rx="3" fill="'.$S.'"/></g>';
  case 'truck': return '<rect x="0" y="146" width="320" height="34" fill="#D7C8F5"/>'
    .'<g class="drive"><rect x="52" y="70" width="140" height="70" rx="6" fill="'.$W.'"/><rect x="66" y="84" width="34" height="30" rx="3" fill="#E9A85A"/><rect x="106" y="90" width="34" height="24" rx="3" fill="#D98E3F"/><rect x="146" y="80" width="34" height="34" rx="3" fill="#F0BE7A"/>'
    .'<path d="M192 92h44l26 26v22h-70z" fill="'.$B.'"/><path d="M202 100h30l16 16h-46z" fill="#DCEBFF"/>'
    .'<circle class="spin" cx="92" cy="142" r="16" fill="'.$P.'"/><circle cx="92" cy="142" r="6" fill="'.$W.'"/><circle class="spin" cx="228" cy="142" r="16" fill="'.$P.'"/><circle cx="228" cy="142" r="6" fill="'.$W.'"/></g>';
  case 'share': return '<path d="M40 140 C100 40 220 160 282 52" fill="none" stroke="#F3A9C6" stroke-width="10" stroke-linecap="round"/><path d="M40 140 C100 40 220 160 282 52" fill="none" stroke="'.$W.'" stroke-width="3" stroke-dasharray="8 9"/>'
    .'<circle cx="40" cy="140" r="12" fill="'.$P.'"/><circle cx="40" cy="140" r="5" fill="'.$W.'"/><g class="pin"><path d="M282 62c-12-14-18-22-18-32a18 18 0 0 1 36 0c0 10-6 18-18 32z" fill="'.$B.'"/><circle cx="282" cy="30" r="7" fill="'.$W.'"/></g>'
    .'<g class="bob"><rect x="128" y="84" width="74" height="36" rx="12" fill="'.$B.'"/><rect x="138" y="70" width="54" height="22" rx="9" fill="#DCEBFF"/><circle cx="154" cy="80" r="7" fill="'.$K.'"/><circle cx="176" cy="80" r="7" fill="#C98E62"/><circle cx="146" cy="122" r="8" fill="'.$P.'"/><circle cx="186" cy="122" r="8" fill="'.$P.'"/></g>';
  case 'tools': return '<rect x="0" y="152" width="320" height="28" fill="#F6C9B0"/><path d="M30 34 Q160 70 290 34" fill="none" stroke="#8A3B12" stroke-width="2"/>'
    .'<g class="twinkle"><circle cx="66" cy="44" r="6" fill="'.$S.'"/><circle cx="112" cy="54" r="6" fill="'.$B.'"/><circle cx="160" cy="57" r="6" fill="'.$S.'"/><circle cx="208" cy="54" r="6" fill="'.$B.'"/><circle cx="254" cy="44" r="6" fill="'.$S.'"/></g>'
    .'<path d="M90 152 L160 74 L230 152 Z" fill="'.$B.'"/><path d="M160 74 L134 152 H186 Z" fill="'.$S.'"/><path d="M160 74 L148 152 H172 Z" fill="'.$B.'"/><rect x="152" y="120" width="16" height="32" fill="'.$P.'"/>'
    .'<g class="bob"><rect x="248" y="104" width="12" height="48" rx="4" fill="#8A3B12"/><rect x="236" y="92" width="36" height="16" rx="5" fill="'.$P.'"/></g>';
  case 'book': return '<rect x="0" y="152" width="320" height="28" fill="#BFD6F5"/><rect x="58" y="36" width="160" height="96" rx="8" fill="#1E5B45"/><rect x="58" y="36" width="160" height="96" rx="8" fill="none" stroke="#8A5A2B" stroke-width="6"/>'
    .'<path d="M80 62h70M80 80h100M80 98h56" stroke="'.$W.'" stroke-width="4" stroke-linecap="round" class="chalk"/><path d="M168 98l10 10 20-24" stroke="'.$S.'" stroke-width="4" fill="none" stroke-linecap="round"/>'
    .'<g class="bob"><rect x="226" y="124" width="66" height="14" rx="3" fill="'.$B.'"/><rect x="232" y="110" width="58" height="14" rx="3" fill="'.$S.'"/><rect x="228" y="96" width="62" height="14" rx="3" fill="#5B8DEF"/><rect x="246" y="62" width="8" height="32" rx="2" fill="'.$S.'" transform="rotate(20 250 78)"/></g>';
  case 'school': return '<rect x="0" y="152" width="320" height="28" fill="#B9E3C9"/>'
    .'<rect x="76" y="76" width="168" height="78" fill="'.$W.'"/><path d="M66 80 L160 36 L254 80 Z" fill="'.$B.'"/><circle cx="160" cy="62" r="10" fill="'.$S.'"/>'
    .'<rect x="96" y="94" width="26" height="22" rx="3" fill="#DCEBFF"/><rect x="198" y="94" width="26" height="22" rx="3" fill="#DCEBFF"/><rect x="144" y="108" width="32" height="46" rx="4" fill="'.$P.'"/>'
    .'<rect x="250" y="40" width="4" height="114" fill="'.$P.'"/><path class="flag" d="M254 42h34l-8 10 8 10h-34z" fill="'.$S.'"/>';
  case 'star': return '<rect width="320" height="180" fill="'.$P.'"/><g class="twinkle"><circle cx="40" cy="30" r="2.5" fill="'.$W.'"/><circle cx="96" cy="58" r="2" fill="'.$W.'"/><circle cx="280" cy="40" r="2.5" fill="'.$W.'"/><circle cx="250" cy="150" r="2" fill="'.$W.'"/><circle cx="60" cy="140" r="2" fill="'.$W.'"/><circle cx="292" cy="104" r="2" fill="'.$W.'"/></g>'
    .'<path d="M226 34a26 26 0 1 0 22 40a22 22 0 1 1 -22 -40z" fill="'.$S.'"/>'
    .'<g class="slow"><rect x="110" y="40" width="100" height="100" fill="none" stroke="'.$S.'" stroke-width="3"/><path d="M110 40L210 140M210 40L110 140M160 40L110 90L160 140L210 90Z" fill="none" stroke="'.$S.'" stroke-width="3"/></g>';
  }
  return '';
}

// [art, tint class, status, title EN, title HI, line EN, line HI]
$services = [
  ['shop',   'c-peach',  'live',  'Shopping from local shops', 'स्थानीय दुकानों से खरीदारी', 'Groceries, medicines, sweets and more from shops near you, at the shop’s own price.', 'किराना, दवा, मिठाई और बहुत कुछ — पास की दुकानों से, दुकान के ही दाम पर।'],
  ['ride',   'c-sky',    'soon',  'Rides', 'सवारी', 'Bike, auto and e-rickshaw, with the fare shown before you book.', 'बाइक, ऑटो और ई-रिक्शा — बुकिंग से पहले किराया दिखता है।'],
  ['parcel', 'c-butter', 'soon',  'Parcel delivery', 'पार्सल डिलीवरी', 'Pick up and drop anything across town, confirmed by a code at both ends.', 'शहर में कहीं भी सामान उठवाइए और पहुँचवाइए — दोनों ओर कोड से पुष्टि।'],
  ['car',    'c-mint',   'live',  'Vehicle booking', 'गाड़ी बुकिंग', 'Book a Bolero, bus or tour vehicle for trips, weddings and functions.', 'यात्रा, शादी और कार्यक्रम के लिए बोलेरो, बस या टूर गाड़ी बुक कीजिए।'],
  ['truck',  'c-lilac',  'soon',  'Goods transport', 'माल ढुलाई', 'Send goods by loader, and fill vehicles that would return empty.', 'लोडर से सामान भेजिए, और खाली लौटने वाली गाड़ियाँ भरिए।'],
  ['share',  'c-pink',   'soon',  'Saath Safar — shared travel', 'साथ सफ़र', 'Share a trip with someone going the same way and pay per seat.', 'उसी रास्ते जा रहे व्यक्ति के साथ सफ़र कीजिए और प्रति सीट भुगतान कीजिए।'],
  ['tools',  'c-peach',  'live',  'Local services', 'स्थानीय सेवाएँ', 'Book a pandit, tent, halwai, mistri or a salon seat.', 'पंडित जी, टेंट, हलवाई, मिस्त्री या सैलून की सीट बुक कीजिए।'],
  ['book',   'c-sky',    'pilot', 'Coaching Saathi', 'कोचिंग साथी', 'Free software for coaching centres; student app at ₹54 for 6 months.', 'कोचिंग संस्थानों के लिए मुफ़्त सॉफ़्टवेयर; छात्र ऐप ₹54 में 6 महीने।'],
  ['school', 'c-mint',   'pilot', 'School ERP', 'स्कूल ERP', 'Fees, attendance, results and a parent app for schools.', 'स्कूलों के लिए फ़ीस, हाज़िरी, परिणाम और अभिभावक ऐप।'],
  ['star',   'c-night',  'soon',  'Maakit Jyotish', 'Maakit ज्योतिष', 'Kundli, matching and daily horoscope with an AI astrologer.', 'कुंडली, गुण मिलान और दैनिक राशिफल, AI ज्योतिषी के साथ।'],
];
$status = ['live' => t('Available', 'उपलब्ध'), 'pilot' => t('Pilot', 'पायलट'), 'soon' => t('Coming soon', 'जल्द आ रहा है')];
$cats = [
  ['shop', 'Food & Grocery', 'खाद्य एवं किराना'], ['plus', 'Medicines & Health', 'दवा एवं स्वास्थ्य'], ['home', 'Home Essentials', 'घरेलू सामान'],
  ['shirt', 'Fashion', 'कपड़े एवं फ़ैशन'], ['phone', 'Electronics', 'इलेक्ट्रॉनिक्स'], ['spark', 'Beauty', 'सौंदर्य'],
  ['bookc', 'Books & Education', 'किताबें एवं शिक्षा'], ['leaf', 'Farming & Pets', 'खेती एवं पशुपालन'], ['brick', 'Construction', 'निर्माण सामग्री'],
  ['carc', 'Vehicles', 'वाहन'], ['gift', 'Weddings & Events', 'शादी एवं आयोजन'], ['tool', 'Services', 'सेवाएँ'],
];
$ci = [
  'shop' => 'M3 10l1.5-5h15L21 10M4 10v10h16V10M9 20v-6h6v6', 'plus' => 'M10 4h4v6h6v4h-6v6h-4v-6H4v-4h6z',
  'home' => 'M3 11l9-7 9 7v9H3z', 'shirt' => 'M8 3l-5 4 3 4 2-1v11h8V10l2 1 3-4-5-4c0 2-2 3-4 3s-4-1-4-3z',
  'phone' => 'M7 2h10v20H7zM11 18h2', 'spark' => 'M12 3l2 6 6 2-6 2-2 6-2-6-6-2 6-2z',
  'bookc' => 'M4 5a2 2 0 0 1 2-2h13v16H6a2 2 0 0 0-2 2zM4 21V5', 'leaf' => 'M5 19C5 9 11 4 20 4c0 9-5 15-15 15zM5 19l8-8',
  'brick' => 'M3 6h18v12H3zM3 12h18M9 6v6M15 12v6', 'carc' => 'M3 13l2-6h14l2 6v5H3zM3 13h18',
  'gift' => 'M3 9h18v4H3zM5 13h14v8H5zM12 9v12M12 9c-2-4-6-4-6-1s6 1 6 1c2-4 6-4 6-1s-6 1-6 1', 'tool' => 'M14 6a4 4 0 0 0 5 5l-9 9a2 2 0 0 1-3-3l9-9a4 4 0 0 0-2-2z',
];
$tints = ['c-peach', 'c-mint', 'c-butter', 'c-sky', 'c-lilac', 'c-pink'];
$steps = [
  ['bag',  'Order', 'ऑर्डर कीजिए', 'Tell us the shop and items — in the app, or by phone and WhatsApp until the app is out.', 'दुकान और सामान बताइए — ऐप से, या ऐप आने तक फ़ोन और WhatsApp से।'],
  ['ok',   'Shop accepts and packs', 'दुकान मानती है, पैक करती है', 'The shop confirms the items and its own price, then packs your order.', 'दुकान सामान और अपना दाम पक्का करती है, फिर ऑर्डर पैक करती है।'],
  ['bike', 'Sarathi picks up', 'सारथी सामान उठाता है', 'A nearby Sarathi collects it, confirmed with a pickup code.', 'पास का सारथी पिकअप कोड से पुष्टि करके सामान उठाता है।'],
  ['door', 'Delivered to your door', 'आपके दरवाज़े तक', 'Follow the order and share the delivery code when it arrives.', 'ऑर्डर देखते रहिए, पहुँचने पर डिलीवरी कोड बताइए।'],
];
$si = ['bag' => 'M5 8h14l-1 13H6zM9 8V6a3 3 0 0 1 6 0v2', 'ok' => 'M5 12l5 5L20 7', 'bike' => 'M3 17a3 3 0 1 0 6 0a3 3 0 1 0 -6 0M15 17a3 3 0 1 0 6 0a3 3 0 1 0 -6 0M6 17l4-8h5l3 8', 'door' => 'M6 3h12v18H6zM14 12h1'];
$why = [
  ['An underserved market', 'अनछुआ बाज़ार', 'Large delivery apps focus on big cities. Towns and villages still run on phone calls and word of mouth.', 'बड़े डिलीवरी ऐप बड़े शहरों पर ध्यान देते हैं। कस्बे और गाँव अब भी फ़ोन कॉल और मुँह-ज़बानी पर चलते हैं।'],
  ['Many services, one platform', 'एक मंच, कई सेवाएँ', 'Shopping, rides, parcels, vehicle and service bookings, coaching and school software share one base.', 'खरीदारी, सवारी, पार्सल, गाड़ी और सेवा बुकिंग, कोचिंग और स्कूल सॉफ़्टवेयर — सब एक ही आधार पर।'],
  ['Technology built in-house', 'अपनी तकनीक', 'Customer, shop, rider and admin software are built and run by Maakit itself.', 'ग्राहक, दुकान, सारथी और एडमिन सॉफ़्टवेयर — Maakit ने स्वयं बनाए और चलाता है।'],
  ['Honest money model', 'साफ़ पैसे का नियम', 'Customers pay the shop directly in cash or UPI. Maakit earns from delivery fees and services, never by holding customer money.', 'ग्राहक नकद या UPI से सीधे दुकान को देते हैं। Maakit डिलीवरी शुल्क और सेवाओं से कमाता है, ग्राहक का पैसा कभी नहीं रखता।'],
];
$faq = [
  ['Can I order on this website?', 'क्या मैं इस वेबसाइट पर ऑर्डर कर सकता हूँ?', 'No. Orders will be placed in the Maakit app. Until the app is on Google Play, order by phone or WhatsApp on ' . $show . '.', 'नहीं। ऑर्डर Maakit ऐप से होंगे। जब तक ऐप Google Play पर नहीं आता, ' . $show . ' पर फ़ोन या WhatsApp से ऑर्डर कीजिए।'],
  ['Are prices higher than in the shop?', 'क्या दाम दुकान से ज़्यादा हैं?', 'No. The price of the goods belongs to the shop. Maakit charges only a delivery fee.', 'नहीं। सामान का दाम दुकान का है। Maakit केवल डिलीवरी शुल्क लेता है।'],
  ['How do I pay?', 'भुगतान कैसे करें?', 'Cash on delivery, or UPI straight to the shop’s own UPI ID.', 'सामान मिलने पर नकद, या UPI से सीधे दुकान की अपनी UPI ID पर।'],
  ['How can my shop join?', 'मेरी दुकान कैसे जुड़ेगी?', 'Choose “Join as a shop”, fill in your details, and our team will call you.', '“दुकान जोड़िए” चुनिए, अपनी जानकारी भरिए — हमारी टीम आपको फ़ोन करेगी।'],
  ['How do I become a Sarathi?', 'सारथी कैसे बनें?', 'Open the Sarathi Partner app, add your Aadhaar and PAN, and choose your vehicle and area.', 'सारथी पार्टनर ऐप खोलिए, आधार और PAN जोड़िए, और अपनी गाड़ी तथा इलाक़ा चुनिए।'],
];
$scripts = ['माकिट', 'মাকিট', 'மாகிட்', 'మాకిట్', 'ಮಾಕಿಟ್', 'മാകിറ്റ്', 'માકિટ', 'ਮਾਕਿਟ', 'ମାକିଟ୍', 'ماکٹ'];
$words = is_hi() ? ['अब घर तक।', 'एक ऐप में।', 'दुकान के दाम पर।'] : ['delivered.', 'in one app.', 'at shop prices.'];
$ico = function ($d, $s = 22) { return '<svg width="'.$s.'" height="'.$s.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="'.h($d).'"/></svg>'; };
?><!doctype html>
<html lang="<?= html_lang() ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= $T('Maakit — shops, rides, parcels and services in one app', 'Maakit — दुकान, सवारी, पार्सल और सेवाएँ, एक ऐप में') ?></title>
<meta name="description" content="<?= $T('Maakit brings local shops, rides, parcels, vehicle and service bookings onto one platform for the towns and villages of Uttar Pradesh. Shop price, only a delivery fee.', 'Maakit उत्तर प्रदेश के कस्बों और गाँवों के लिए स्थानीय दुकानें, सवारी, पार्सल, गाड़ी और सेवा बुकिंग एक मंच पर लाता है। दाम दुकान का, सिर्फ़ डिलीवरी शुल्क।') ?>">
<link rel="canonical" href="https://maakit.in/">
<meta name="theme-color" content="#D6246E">
<link rel="manifest" href="/manifest.json">
<link rel="apple-touch-icon" href="/assets/icon-180.png">
<meta property="og:title" content="Maakit">
<meta property="og:type" content="website">
<meta property="og:site_name" content="Maakit">
<meta property="og:url" content="https://maakit.in/">
<meta property="og:description" content="<?= $T('Shops, rides, parcels and services — all in Maakit. Call or WhatsApp ' . $show . '.', 'दुकान, सवारी, पार्सल और सेवाएँ — सब कुछ Maakit में। फ़ोन या WhatsApp — ' . $show . '।') ?>">
<meta property="og:image" content="https://maakit.in/assets/icon-512.png">
<meta property="og:locale" content="<?= is_hi() ? 'hi_IN' : 'en_IN' ?>">
<script type="application/ld+json">{"@context":"https://schema.org","@type":"WebSite","@id":"https://maakit.in/#website","name":"Maakit","alternateName":"maakit.in","url":"https://maakit.in/"}</script>
<link rel="preload" href="/assets/fonts/lexend-latin-700.woff2" as="font" type="font/woff2" crossorigin>
<script>document.documentElement.className+=' js';</script>
<style>
@font-face{font-family:'Lexend';font-weight:400;font-display:swap;src:url(/assets/fonts/lexend-latin-400.woff2) format('woff2')}
@font-face{font-family:'Lexend';font-weight:600;font-display:swap;src:url(/assets/fonts/lexend-latin-600.woff2) format('woff2')}
@font-face{font-family:'Lexend';font-weight:700;font-display:swap;src:url(/assets/fonts/lexend-latin-700.woff2) format('woff2')}
@font-face{font-family:'Lexend';font-weight:800;font-display:swap;src:url(/assets/fonts/lexend-latin-800.woff2) format('woff2')}
@font-face{font-family:'Noto Sans Devanagari';font-weight:400;font-display:swap;src:url(/smartsarathi/assets/fonts/noto-deva-400.woff2) format('woff2');unicode-range:U+0900-097F,U+1CD0-1CF9,U+200C-200D,U+20A8,U+20B9,U+20F0,U+25CC,U+A830-A839,U+A8E0-A8FF}
@font-face{font-family:'Noto Sans Devanagari';font-weight:600;font-display:swap;src:url(/smartsarathi/assets/fonts/noto-deva-600.woff2) format('woff2');unicode-range:U+0900-097F,U+1CD0-1CF9,U+200C-200D,U+20A8,U+20B9,U+20F0,U+25CC,U+A830-A839,U+A8E0-A8FF}
@font-face{font-family:'Noto Sans Devanagari';font-weight:700;font-display:swap;src:url(/smartsarathi/assets/fonts/noto-deva-700.woff2) format('woff2');unicode-range:U+0900-097F,U+1CD0-1CF9,U+200C-200D,U+20A8,U+20B9,U+20F0,U+25CC,U+A830-A839,U+A8E0-A8FF}
@font-face{font-family:'Kalam';font-weight:700;font-display:swap;src:url(/assets/fonts/kalam-latin-700.woff2) format('woff2');unicode-range:U+0000-00FF}
:root{--brand:#D6246E;--brand-d:#A3164F;--brand-s:#FFE0EC;--saffron:#FFB020;--plum:#1C0D1F;--plum-2:#2A1530;--cream:#FFF6EF;--line:#F1DFD6;--ink:#22141F;--muted:#5B4A55;--on-dark:#E9D9E4;--green:#25A55F;
--peach:#FFE3D3;--peach-t:#8A3B12;--mint:#DDF3E6;--mint-t:#1E6B40;--butter:#FFF0C2;--butter-t:#7A5600;--sky:#DCEBFF;--sky-t:#1D4E89;--lilac:#EDE2FF;--lilac-t:#4B2D8A;--pink:#FFE0EC;--pink-t:#9A1B4E;
--f:'Lexend','Noto Sans Devanagari','Mukta','Hind',sans-serif;--hand:'Kalam',cursive;--r:28px}
*{box-sizing:border-box}
html{scroll-behavior:smooth}
body{margin:0;background:var(--cream);color:var(--ink);font-family:var(--f);line-height:1.6;font-size:16px}
a{color:var(--brand-d)}
.wrap{max-width:1180px;margin:0 auto;padding:0 20px}
.btn{display:inline-flex;align-items:center;justify-content:center;gap:9px;min-height:52px;padding:12px 22px;border-radius:999px;font-weight:700;text-decoration:none;font-size:16px;transition:transform .2s,box-shadow .2s}
.btn:hover{transform:translateY(-2px)}
.btn-brand{background:var(--brand);color:#fff;box-shadow:0 8px 22px rgba(214,36,110,.35)}
.btn-saffron{background:var(--saffron);color:#3A2400}
.btn-ghost{background:rgba(255,255,255,.1);color:#fff;border:1.5px solid rgba(255,255,255,.35)}
.btn-white{background:#fff;color:var(--brand-d)}
a:focus-visible,button:focus-visible,summary:focus-visible{outline:3px solid var(--saffron);outline-offset:3px}
.c-peach{background:var(--peach);color:var(--peach-t)}.c-mint{background:var(--mint);color:var(--mint-t)}.c-butter{background:var(--butter);color:var(--butter-t)}.c-sky{background:var(--sky);color:var(--sky-t)}.c-lilac{background:var(--lilac);color:var(--lilac-t)}.c-pink{background:var(--pink);color:var(--pink-t)}.c-night{background:var(--plum-2);color:#fff}
/* ---- intro ---- */
#mk-intro{position:fixed;inset:0;z-index:60;background:var(--brand);color:#fff;display:flex;align-items:center;justify-content:center;flex-direction:column;transition:transform .7s cubic-bezier(.7,0,.3,1)}
#mk-intro.out{transform:translateY(-100%)}
#mk-intro .nm{font-size:clamp(48px,12vw,104px);font-weight:800;line-height:1.2;min-height:1.3em;text-align:center;text-shadow:0 6px 30px rgba(0,0,0,.18)}
#mk-intro svg{width:min(560px,86vw);height:auto;display:none}
#mk-intro.final .nm{display:none}#mk-intro.final svg{display:block}
#mk-intro .draw{font-family:var(--hand);font-weight:700;font-size:168px;fill:#fff;stroke:#fff;stroke-width:2.5;stroke-dasharray:900;stroke-dashoffset:900;fill-opacity:0}
#mk-intro.final .draw{animation:mkDraw 1.5s ease-out forwards}
#mk-intro .tag{opacity:0;margin-top:8px;font-size:clamp(16px,3.6vw,20px);color:#FFE3EE;transition:opacity .5s}
#mk-intro.final .tag{opacity:1}
#mk-intro button{position:absolute;bottom:28px;background:rgba(255,255,255,.16);color:#fff;border:1.5px solid rgba(255,255,255,.5);border-radius:999px;min-height:44px;padding:8px 20px;font:inherit;font-weight:600;cursor:pointer}
@keyframes mkDraw{0%{stroke-dashoffset:900;fill-opacity:0}70%{stroke-dashoffset:0;fill-opacity:0}100%{stroke-dashoffset:0;fill-opacity:1}}
/* ---- header + hero (dark) ---- */
.dark{background:var(--plum);color:#fff;position:relative;overflow:hidden}
.dark .glow{position:absolute;width:900px;height:900px;left:50%;top:-380px;transform:translateX(-50%);background:radial-gradient(closest-side,rgba(214,36,110,.55),rgba(214,36,110,0));pointer-events:none}
.bar{position:relative;background:var(--brand-d);color:#fff;text-align:center;font-size:14px;padding:9px 16px}
.bar b{color:var(--saffron)}
header.top{position:relative}
header.top .wrap{display:flex;align-items:center;gap:12px;flex-wrap:wrap;padding-top:16px;padding-bottom:16px}
.logo{display:flex;align-items:center;gap:10px;color:#fff;text-decoration:none;margin-right:auto}
.logo b{width:44px;height:44px;border-radius:14px;background:var(--brand);color:#fff;display:flex;align-items:center;justify-content:center;font-family:var(--hand);font-size:28px;line-height:1}
.logo span{font-family:var(--hand);font-size:32px;font-weight:700;line-height:1}
.chip{display:inline-flex;align-items:center;gap:7px;min-height:40px;padding:6px 14px;border-radius:999px;background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.18);color:#fff;text-decoration:none;font-size:14px;font-weight:600}
.lang{display:flex;border-radius:999px;background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.18);overflow:hidden}
.lang a{padding:8px 13px;font-size:14px;font-weight:700;color:#fff;text-decoration:none}
.lang a.on{background:#fff;color:var(--plum)}
.hero{position:relative;text-align:center;padding:48px 0 64px}
.hero .bar-line{width:3px;height:44px;margin:0 auto 18px;background:var(--brand);border-radius:2px;animation:blink 1s steps(1) infinite}
@keyframes blink{50%{opacity:0}}
.hero h1{margin:0 auto;max-width:900px;font-size:clamp(30px,5.4vw,58px);line-height:1.18;font-weight:800}
.hero .rot{display:block;color:#FF5C9D;font-size:clamp(46px,9vw,96px);line-height:1.15;position:relative;min-height:1.2em}
.hero .rot u{text-decoration:none;position:relative;display:inline-block}
.hero .rot u::after{content:"";position:absolute;left:4%;right:4%;bottom:-6px;height:10px;border-bottom:4px solid #FF5C9D;border-radius:0 0 50% 50%}
.hero .caret{display:inline-block;width:4px;height:.85em;background:#FF5C9D;margin-left:6px;vertical-align:-0.08em;animation:blink 1s steps(1) infinite}
.hero p.sub{margin:22px auto 0;max-width:640px;font-size:clamp(16px,2.2vw,19px);color:var(--on-dark)}
.hero .ctas{margin-top:28px;display:flex;flex-wrap:wrap;justify-content:center;gap:12px}
.hero .pills{margin-top:18px;display:flex;flex-wrap:wrap;justify-content:center;gap:10px}
.hero .pills span{display:inline-flex;align-items:center;gap:8px;padding:9px 15px;border-radius:999px;background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.16);font-size:14px;font-weight:600}
.hero .pills svg{color:var(--saffron)}
.scroll{display:inline-flex;flex-direction:column;align-items:center;gap:2px;margin-top:26px;color:var(--on-dark);font-size:13px;text-decoration:none;animation:bob 2.2s ease-in-out infinite}
/* ---- marquee ---- */
.strip{background:#fff;border-bottom:1px solid var(--line);overflow:hidden;padding:14px 0}
.track{display:flex;width:max-content;animation:marq 42s linear infinite}
.strip:hover .track{animation-play-state:paused}
.track span{display:flex;align-items:center;gap:10px;margin-right:16px;font-size:14px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--ink);white-space:nowrap}
:lang(hi) .track span{letter-spacing:0;text-transform:none;font-size:15px}
.track em{width:42px;height:42px;border-radius:50%;display:flex;align-items:center;justify-content:center}
.track i{width:7px;height:7px;border-radius:50%;background:var(--brand);margin-left:6px}
@keyframes marq{from{transform:translateX(0)}to{transform:translateX(-50%)}}
/* ---- sections ---- */
section.s{padding:84px 0}
.eyebrow{display:inline-flex;align-items:center;gap:10px;font-size:13px;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:var(--brand)}
:lang(hi) .eyebrow{letter-spacing:0;font-size:15px}
.eyebrow::after{content:"";width:34px;height:2px;background:var(--brand)}
h2.big{margin:10px 0 0;font-size:clamp(30px,5vw,50px);line-height:1.15;font-weight:800;color:var(--ink)}
h2.big em{font-style:normal;color:var(--brand)}
.lead{margin:14px 0 0;font-size:18px;color:var(--muted);max-width:640px}
.intro-row{display:flex;flex-wrap:wrap;align-items:center;gap:30px;margin-bottom:40px}
.intro-row>div{flex:1 1 440px}
.intro-art{flex:0 1 360px;margin:0 auto;border-radius:var(--r);overflow:hidden;background:var(--brand-s);aspect-ratio:16/9}
.intro-art svg{width:100%;height:100%;display:block}
.svc-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(270px,1fr));gap:22px}
.svc{border-radius:var(--r);overflow:hidden;background:#fff;border:1px solid var(--line);transition:transform .3s,box-shadow .3s;display:flex;flex-direction:column}
.svc:hover{transform:translateY(-6px);box-shadow:0 22px 44px rgba(34,20,31,.12)}
.svc .art{position:relative;aspect-ratio:16/9}
.svc .art svg{width:100%;height:100%;display:block}
.svc .badge{position:absolute;left:14px;top:14px;white-space:nowrap;padding:6px 14px;border-radius:999px;font-size:12px;font-weight:800;letter-spacing:.08em;text-transform:uppercase;box-shadow:0 6px 16px rgba(0,0,0,.18)}
:lang(hi) .svc .badge{letter-spacing:0;font-size:13px}
.b-soon{background:var(--saffron);color:#3A2400}.b-pilot{background:#5B8DEF;color:#fff}
.svc .body{padding:22px 22px 24px;display:flex;flex-direction:column;gap:8px;flex:1}
.svc .body.c-peach,.svc .body.c-mint,.svc .body.c-butter,.svc .body.c-sky,.svc .body.c-lilac,.svc .body.c-pink{color:inherit}
.svc h3{margin:0;font-size:21px;font-weight:800;color:var(--ink);display:flex;align-items:center;gap:10px}
.svc h3 .dot{width:10px;height:10px;border-radius:50%;background:var(--green);flex-shrink:0}
.svc p{margin:0;color:var(--muted);font-size:15px}
/* svg motion */
.spin{transform-box:fill-box;transform-origin:center;animation:spin 1.2s linear infinite}
.drive{animation:drive 3s ease-in-out infinite}
.bob{animation:bob 2.6s ease-in-out infinite}
.pin{animation:bob 1.8s ease-in-out infinite}
.twinkle circle{animation:tw 1.8s ease-in-out infinite}.twinkle circle:nth-child(2n){animation-delay:.6s}.twinkle circle:nth-child(3n){animation-delay:1.1s}
.flag{transform-box:fill-box;transform-origin:left center;animation:flag 1.4s ease-in-out infinite}
.slow{transform-box:fill-box;transform-origin:center;animation:spin 30s linear infinite}
.sun{animation:tw 3s ease-in-out infinite}
@keyframes spin{to{transform:rotate(360deg)}}
@keyframes drive{0%,100%{transform:translateX(-6px)}50%{transform:translateX(8px)}}
@keyframes bob{0%,100%{transform:translateY(0)}50%{transform:translateY(-6px)}}
@keyframes tw{0%,100%{opacity:1}50%{opacity:.35}}
@keyframes flag{0%,100%{transform:skewY(0)}50%{transform:skewY(-8deg)}}
/* how */
.how{background:#fff;border-radius:var(--r) var(--r) 0 0}
.steps{list-style:none;margin:0;padding:0;display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:20px;counter-reset:s}
.steps li{position:relative;border-radius:24px;padding:26px;background:var(--cream);border:1px solid var(--line)}
.steps .ic{width:56px;height:56px;border-radius:18px;display:flex;align-items:center;justify-content:center;margin-bottom:14px}
.steps small{display:inline-block;font-size:12px;font-weight:800;letter-spacing:.1em;text-transform:uppercase;color:var(--brand);background:var(--brand-s);padding:4px 10px;border-radius:999px}
:lang(hi) .steps small{letter-spacing:0;font-size:13px}
.steps h3{margin:10px 0 6px;font-size:19px;font-weight:800}
.steps p{margin:0;color:var(--muted);font-size:15px}
.promise{margin-top:34px;display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:20px}
.promise .card{border-radius:var(--r);padding:32px;position:relative;overflow:hidden}
.promise .p1{background:var(--brand);color:#fff}
.promise .p1 .coin{width:84px;height:84px;border-radius:50%;background:var(--saffron);color:#3A2400;display:flex;align-items:center;justify-content:center;font-size:40px;font-weight:800;margin-bottom:16px;animation:bob 2.6s ease-in-out infinite}
.promise .p1 b{display:block;font-size:clamp(24px,3.4vw,32px);line-height:1.2}
.promise .p1 p{margin:10px 0 0;color:#FFE3EE}
.promise .p2{background:var(--peach);display:flex;flex-direction:column;justify-content:center;gap:14px}
.promise .p2 b{font-size:24px}
/* apps */
.apps{background:#fff}
.app-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(290px,1fr));gap:22px}
.app{border-radius:var(--r);padding:30px;display:flex;flex-direction:column;gap:10px}
.app .ic{width:62px;height:62px;border-radius:20px;background:rgba(255,255,255,.75);display:flex;align-items:center;justify-content:center;margin-bottom:6px}
.app small{font-weight:800;font-size:13px;letter-spacing:.08em;text-transform:uppercase}
:lang(hi) .app small{letter-spacing:0;font-size:14px}
.app h3{margin:0;font-size:24px;font-weight:800;color:var(--ink)}
.app p{margin:0;color:var(--muted)}
.app ul{margin:4px 0 8px;padding:0;list-style:none;display:flex;flex-direction:column;gap:6px;color:var(--ink);font-size:15px}
.app li{display:flex;gap:8px;align-items:flex-start}
.app li svg{flex-shrink:0;margin-top:3px;color:var(--green)}
.app .btn{margin-top:auto;align-self:flex-start}
.app.dk{background:var(--plum-2);color:#fff}.app.dk h3,.app.dk ul{color:#fff}.app.dk p{color:var(--on-dark)}.app.dk small{color:var(--saffron)}
/* investors */
.inv-box{position:relative;overflow:hidden;background:var(--plum);color:#fff;border-radius:40px;padding:clamp(30px,6vw,64px)}
.inv-box .glow{position:absolute;width:640px;height:640px;right:-200px;top:-260px;background:radial-gradient(closest-side,rgba(214,36,110,.5),rgba(214,36,110,0))}
.inv-box>*{position:relative}
.inv-box h2.big{color:#fff}.inv-box .lead{color:var(--on-dark)}
.inv-box .eyebrow{color:var(--saffron)}.inv-box .eyebrow::after{background:var(--saffron)}
.why-grid{margin-top:34px;display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:16px}
.why{background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.12);border-radius:24px;padding:24px}
.why .n{font-family:var(--hand);font-size:34px;color:var(--saffron);line-height:1}
.why h3{margin:8px 0 6px;font-size:19px;font-weight:800}
.why p{margin:0;color:var(--on-dark);font-size:15px}
.inv-box .ctas{margin-top:30px;display:flex;flex-wrap:wrap;gap:12px}
/* where */
.where{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:20px;align-items:stretch}
.where .box{border-radius:var(--r);padding:30px}
.where .box b{display:block;font-size:24px;font-weight:800;margin:6px 0;color:var(--ink)}
.where .box small{font-weight:800;font-size:13px}
/* faq */
.faq{max-width:860px;margin:0 auto}
.faq details{background:#fff;border:1px solid var(--line);border-radius:22px;padding:4px 24px;margin-bottom:12px;transition:box-shadow .2s}
.faq details[open]{box-shadow:0 12px 30px rgba(34,20,31,.08)}
.faq summary{list-style:none;cursor:pointer;display:flex;justify-content:space-between;align-items:center;gap:16px;min-height:62px;font-size:17px;font-weight:700}
.faq summary::-webkit-details-marker{display:none}
.faq summary span{width:34px;height:34px;border-radius:50%;background:var(--brand-s);color:var(--brand);display:flex;align-items:center;justify-content:center;font-size:22px;flex-shrink:0;transition:transform .3s}
.faq details[open] summary span{transform:rotate(45deg)}
.faq p{margin:0 0 18px;color:var(--muted)}
/* footer */
footer{background:var(--plum);color:#D9C9D4;border-radius:44px 44px 0 0;margin-top:40px;padding:56px 0 30px;font-size:14px}
footer .cols{display:flex;flex-wrap:wrap;gap:34px;justify-content:space-between}
footer .brand{flex:1 1 280px;display:flex;flex-direction:column;gap:10px}
footer .brand .logo{margin:0}
footer .col{flex:0 1 170px;display:flex;flex-direction:column;gap:10px}
footer .col b{color:#fff;font-size:12px;letter-spacing:.12em;text-transform:uppercase}
:lang(hi) footer .col b{letter-spacing:0;font-size:14px}
footer a{color:#D9C9D4;text-decoration:none}footer a:hover{color:#fff}
footer .contact{display:flex;flex-wrap:wrap;gap:10px;margin-top:6px}
footer .low{border-top:1px solid rgba(255,255,255,.1);margin-top:30px;padding-top:18px;display:flex;flex-wrap:wrap;justify-content:space-between;gap:10px;font-size:13px}
footer .low a{color:var(--saffron);font-weight:700}
.wa-fab{position:fixed;right:18px;bottom:18px;z-index:40;display:inline-flex;align-items:center;gap:8px;background:var(--green);color:#fff;text-decoration:none;font-weight:700;border-radius:999px;padding:12px 18px;box-shadow:0 10px 26px rgba(0,0,0,.22)}
/* scroll reveal */
.js .rv{opacity:0;transform:translateY(28px);transition:opacity .7s ease,transform .7s ease}
.js .rv.in{opacity:1;transform:none}
@media (max-width:640px){section.s{padding:60px 0}.hero{padding:30px 0 48px}header.top .chip{display:none}.wa-fab span{display:none}.wa-fab{padding:14px}}
@media (prefers-reduced-motion:reduce){*,*::before,*::after{animation:none!important;transition:none!important}.js .rv{opacity:1;transform:none}#mk-intro{display:none}}
</style>
</head>
<body>

<div id="mk-intro" hidden>
  <div class="nm" aria-hidden="true">Maakit</div>
  <svg viewBox="0 0 640 220" role="img" aria-label="Maakit"><text class="draw" x="320" y="160" text-anchor="middle">Maakit</text></svg>
  <div class="tag"><?= $T('Your town’s own app', 'आपके शहर का अपना ऐप') ?></div>
  <button type="button" id="mk-skip"><?= $T('Skip', 'छोड़िए') ?></button>
</div>

<div class="dark">
  <span class="glow" aria-hidden="true"></span>
  <div class="bar"><?= $T('Maakit is growing across Bhadohi and the Varanasi region —', 'Maakit भदोही और वाराणसी क्षेत्र में बढ़ रहा है —') ?> <b><?= $T('shops, Sarathis and investors welcome', 'दुकानदार, सारथी और निवेशक जुड़ सकते हैं') ?></b></div>
  <header class="top">
    <div class="wrap">
      <a class="logo" href="/"><b>M</b><span>Maakit</span></a>
      <a class="chip" href="/register-business.php"><?= $ico('M3 10l1.5-5h15L21 10M4 10v10h16V10', 16) ?><?= $T('Join as a shop', 'दुकान जोड़िए') ?></a>
      <a class="chip" href="https://smartsarathi.in/sarathi.php"><?= $ico($si['bike'], 16) ?><?= $T('Join as Sarathi', 'सारथी बनिए') ?></a>
      <a class="chip" href="#investors"><?= $ico('M3 17l6-6 4 4 8-8M15 7h6v6', 16) ?><?= $T('Investors', 'निवेशक') ?></a>
      <div class="lang" role="group" aria-label="Language">
        <a href="<?= h(lang_url('en')) ?>" class="<?= is_hi() ? '' : 'on' ?>" lang="en">EN</a>
        <a href="<?= h(lang_url('hi')) ?>" class="<?= is_hi() ? 'on' : '' ?>" lang="hi">हिंदी</a>
      </div>
    </div>
  </header>
  <section class="hero" id="top">
    <div class="wrap">
      <span class="bar-line" aria-hidden="true"></span>
      <h1><?= $T('Your town’s shops and services,', 'शहर की दुकानें और सेवाएँ,') ?>
        <span class="rot"><u id="mk-rot"><?= h($words[0]) ?></u><span class="caret" aria-hidden="true"></span></span>
      </h1>
      <p class="sub"><?= $T('Groceries, medicines and anything from shops near you — plus vehicle and service bookings. A Maakit Sarathi brings it to your door, at the shop’s own price.', 'किराना, दवा और पास की दुकानों से कुछ भी — साथ में गाड़ी और सेवा बुकिंग। Maakit का सारथी दुकान के ही दाम पर सामान आपके दरवाज़े तक लाता है।') ?></p>
      <div class="ctas">
        <a class="btn btn-brand" href="https://wa.me/<?= h($wa) ?>"><?= $ico('M4 20l1.5-4A8 8 0 1 1 8 19z', 20) ?><?= $T('Order on WhatsApp', 'WhatsApp पर ऑर्डर') ?></a>
        <a class="btn btn-ghost" href="tel:<?= h($tel) ?>"><?= $ico('M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2', 20) ?><?= $T('Call', 'फ़ोन') ?> <?= h($show) ?></a>
      </div>
      <div class="pills">
        <span><?= $ico('M5 12l5 5L20 7', 16) ?><?= $T('Shop’s own price', 'दुकान का ही दाम') ?></span>
        <span><?= $ico('M5 12l5 5L20 7', 16) ?><?= $T('Cash or UPI to the shop', 'नकद या UPI सीधे दुकान को') ?></span>
        <span><?= $ico('M5 12l5 5L20 7', 16) ?><?= $T('App coming soon on Google Play', 'ऐप जल्द Google Play पर') ?></span>
      </div>
      <a class="scroll" href="#services"><?= $T('Scroll down', 'नीचे देखिए') ?><?= $ico('M6 9l6 6 6-6', 18) ?></a>
    </div>
  </section>
</div>

<div class="strip" aria-hidden="true">
  <div class="track">
    <?php for ($k = 0; $k < 2; $k++) foreach ($cats as $i => $c): ?><span><em class="<?= $tints[$i % 6] ?>"><?= $ico($ci[$c[0]], 20) ?></em><?= $T($c[1], $c[2]) ?><i></i></span><?php endforeach; ?>
  </div>
</div>

<main>
<section class="s" id="services" aria-labelledby="svc-h">
  <div class="wrap">
    <div class="intro-row rv">
      <div>
        <span class="eyebrow"><?= $T('Built for small towns', 'छोटे शहरों के लिए बना') ?></span>
        <h2 class="big" id="svc-h"><?= $T('One app.', 'एक ऐप।') ?><br><em><?= $T('For your whole town.', 'पूरे शहर के लिए।') ?></em></h2>
        <p class="lead"><?= $T('Maakit brings local shops, Sarathis, vehicle owners and service providers onto one platform — so whatever you need from your town, one app brings it home.', 'Maakit स्थानीय दुकानों, सारथियों, गाड़ी वालों और सेवा देने वालों को एक मंच पर लाता है — शहर से जो भी चाहिए, एक ऐप घर ले आता है।') ?></p>
      </div>
      <div class="intro-art c-pink" aria-hidden="true"><svg viewBox="0 0 320 180"><?= mk_art('ride') ?></svg></div>
    </div>
    <div class="svc-grid">
      <?php foreach ($services as $s): ?>
      <article class="svc rv">
        <div class="art <?= $s[1] ?>">
          <svg viewBox="0 0 320 180" aria-hidden="true"><?= mk_art($s[0]) ?></svg>
          <?php if ($s[2] !== 'live'): ?><span class="badge <?= $s[2] === 'pilot' ? 'b-pilot' : 'b-soon' ?>"><?= h($status[$s[2]]) ?></span><?php endif; ?>
        </div>
        <div class="body">
          <h3><?php if ($s[2] === 'live'): ?><span class="dot" title="<?= h($status['live']) ?>"></span><?php endif; ?><?= $T($s[3], $s[4]) ?></h3>
          <p><?= $T($s[5], $s[6]) ?></p>
        </div>
      </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="s how" id="how" aria-labelledby="how-h">
  <div class="wrap">
    <div class="rv">
      <span class="eyebrow"><?= $T('How it works', 'कैसे काम करता है') ?></span>
      <h2 class="big" id="how-h"><?= $T('From the shop,', 'दुकान से,') ?> <em><?= $T('to your door.', 'आपके दरवाज़े तक।') ?></em></h2>
    </div>
    <ol class="steps" style="margin-top:36px">
      <?php foreach ($steps as $i => $st): ?>
      <li class="rv" style="transition-delay:<?= $i * 0.08 ?>s">
        <span class="ic <?= $tints[$i] ?>"><?= $ico($si[$st[0]], 26) ?></span>
        <small><?= $T('Step', 'कदम') ?> <?= $i + 1 ?></small>
        <h3><?= $T($st[1], $st[2]) ?></h3>
        <p><?= $T($st[3], $st[4]) ?></p>
      </li>
      <?php endforeach; ?>
    </ol>
    <div class="promise">
      <div class="card p1 rv">
        <div class="coin">₹</div>
        <b><?= $T('Shop price. Not a rupee more.', 'दुकान का दाम। एक रुपया ज़्यादा नहीं।') ?></b>
        <p><?= $T('The price of the goods belongs to the shop. Maakit charges only a delivery fee — and you pay the shop directly, in cash or UPI.', 'सामान का दाम दुकान का है। Maakit केवल डिलीवरी शुल्क लेता है — और भुगतान आप सीधे दुकान को करते हैं, नकद या UPI से।') ?></p>
      </div>
      <div class="card p2 rv">
        <b><?= $T('Order today', 'आज ही ऑर्डर कीजिए') ?></b>
        <span style="color:var(--peach-t)"><?= $T('The app is coming soon. Until then, just call or WhatsApp us.', 'ऐप जल्द आ रहा है। तब तक बस फ़ोन या WhatsApp कीजिए।') ?></span>
        <a class="btn btn-brand" style="align-self:flex-start" href="https://wa.me/<?= h($wa) ?>"><?= $T('WhatsApp', 'WhatsApp') ?> <?= h($show) ?></a>
      </div>
    </div>
  </div>
</section>

<section class="s apps" id="apps" aria-labelledby="apps-h">
  <div class="wrap">
    <div class="rv" style="margin-bottom:36px">
      <span class="eyebrow"><?= $T('Three apps. One platform.', 'तीन ऐप। एक मंच।') ?></span>
      <h2 class="big" id="apps-h"><?= $T('Everyone on the same', 'सब एक ही') ?> <em><?= $T('order.', 'ऑर्डर पर।') ?></em></h2>
    </div>
    <div class="app-grid">
      <article class="app c-pink rv">
        <span class="ic"><?= $ico('M7 2h10v20H7zM11 18h2', 30) ?></span>
        <small><?= $T('For customers', 'ग्राहकों के लिए') ?></small>
        <h3><?= $T('Maakit app', 'Maakit ऐप') ?></h3>
        <p><?= $T('Shop, ride, send and book from one app.', 'एक ही ऐप से खरीदिए, सवारी लीजिए, भेजिए और बुक कीजिए।') ?></p>
        <ul><li><?= $ico('M5 12l5 5L20 7', 16) ?><?= $T('Hindi and English', 'हिंदी और English') ?></li><li><?= $ico('M5 12l5 5L20 7', 16) ?><?= $T('Cash or UPI to the shop', 'नकद या UPI सीधे दुकान को') ?></li><li><?= $ico('M5 12l5 5L20 7', 16) ?><?= $T('Order tracking', 'ऑर्डर ट्रैकिंग') ?></li></ul>
        <span class="btn btn-white" style="cursor:default"><?= $T('Coming soon on Google Play', 'जल्द ही Google Play पर') ?></span>
      </article>
      <article class="app c-butter rv">
        <span class="ic"><?= $ico('M3 10l1.5-5h15L21 10M4 10v10h16V10M9 20v-6h6v6', 30) ?></span>
        <small><?= $T('For shopkeepers', 'दुकानदारों के लिए') ?></small>
        <h3><?= $T('Maakit for shops', 'Maakit दुकान') ?></h3>
        <p><?= $T('Get orders on your phone and run your shop online.', 'फ़ोन पर ऑर्डर पाइए और दुकान ऑनलाइन चलाइए।') ?></p>
        <ul><li><?= $ico('M5 12l5 5L20 7', 16) ?><?= $T('Alert for every new order', 'हर नए ऑर्डर की सूचना') ?></li><li><?= $ico('M5 12l5 5L20 7', 16) ?><?= $T('Set your own prices and stock', 'अपना दाम और स्टॉक ख़ुद तय कीजिए') ?></li><li><?= $ico('M5 12l5 5L20 7', 16) ?><?= $T('Your own daily account', 'अपना रोज़ का हिसाब') ?></li></ul>
        <a class="btn btn-brand" href="/register-business.php"><?= $T('Join as a shop', 'दुकान जोड़िए') ?></a>
      </article>
      <article class="app dk rv">
        <span class="ic" style="background:rgba(255,255,255,.1);color:var(--saffron)"><?= $ico($si['bike'], 30) ?></span>
        <small><?= $T('For riders and vehicle owners', 'सारथी और गाड़ी वालों के लिए') ?></small>
        <h3><?= $T('Sarathi Partner', 'सारथी पार्टनर') ?></h3>
        <p><?= $T('Earn with your bike, auto, car or bus in your own area.', 'अपने इलाक़े में बाइक, ऑटो, कार या बस से कमाइए।') ?></p>
        <ul><li><?= $ico('M5 12l5 5L20 7', 16) ?><?= $T('Join with Aadhaar and PAN', 'आधार और PAN से जुड़िए') ?></li><li><?= $ico('M5 12l5 5L20 7', 16) ?><?= $T('Choose your area and distance', 'अपना इलाक़ा और दूरी चुनिए') ?></li><li><?= $ico('M5 12l5 5L20 7', 16) ?><?= $T('Work goes to the nearest Sarathi', 'काम सबसे पास के सारथी को') ?></li></ul>
        <a class="btn btn-saffron" href="https://smartsarathi.in/sarathi.php"><?= $T('Join as Sarathi', 'सारथी बनिए') ?></a>
      </article>
    </div>
  </div>
</section>

<section class="s" id="investors" aria-labelledby="inv-h" style="padding-top:20px">
  <div class="wrap">
    <div class="inv-box rv">
      <span class="glow" aria-hidden="true"></span>
      <span class="eyebrow"><?= $T('For investors', 'निवेशकों के लिए') ?></span>
      <h2 class="big" id="inv-h"><?= $T('Why Maakit', 'Maakit क्यों') ?></h2>
      <p class="lead"><?= $T('Small towns have shops, vehicles and skilled people, but no single app that connects them. Maakit is building that platform, starting in eastern Uttar Pradesh.', 'छोटे शहरों में दुकानें, गाड़ियाँ और हुनरमंद लोग हैं, पर उन्हें जोड़ने वाला कोई एक ऐप नहीं। Maakit यही मंच बना रहा है, शुरुआत पूर्वी उत्तर प्रदेश से।') ?></p>
      <div class="why-grid">
        <?php foreach ($why as $i => $w): ?><div class="why"><span class="n">0<?= $i + 1 ?></span><h3><?= $T($w[0], $w[1]) ?></h3><p><?= $T($w[2], $w[3]) ?></p></div><?php endforeach; ?>
      </div>
      <div class="ctas">
        <a class="btn btn-saffron" href="tel:<?= h($tel) ?>"><?= $T('Call us', 'फ़ोन कीजिए') ?> · <?= h($show) ?></a>
        <a class="btn btn-ghost" href="https://wa.me/<?= h($wa) ?>"><?= $T('Message on WhatsApp', 'WhatsApp पर संदेश') ?></a>
      </div>
    </div>
  </div>
</section>

<section class="s" style="padding-top:20px" aria-labelledby="where-h">
  <div class="wrap">
    <div class="rv" style="margin-bottom:30px">
      <span class="eyebrow"><?= $T('Growing village by village', 'गाँव-दर-गाँव बढ़ते हुए') ?></span>
      <h2 class="big" id="where-h"><?= $T('Where we', 'हम कहाँ') ?> <em><?= $T('serve', 'सेवा देते हैं') ?></em></h2>
    </div>
    <div class="where">
      <div class="box c-mint rv"><small><?= $T('Serving now', 'अभी सेवा') ?></small><b><?= $T('Kapsethi, Chauri, Kachhwa', 'कपसेठी, चौरी, कछवा') ?></b><span><?= $T('and nearby villages', 'और आस-पास के गाँव') ?></span></div>
      <div class="box c-peach rv"><small><?= $T('Coming next', 'आगे') ?></small><b><?= $T('More of Bhadohi and Varanasi', 'भदोही और वाराणसी के और इलाक़े') ?></b><span><?= $T('A new area opens only when it has enough shops and Sarathis.', 'नया इलाक़ा तभी खुलता है जब वहाँ पर्याप्त दुकानें और सारथी हों।') ?></span></div>
    </div>
  </div>
</section>

<section class="s" style="padding-top:10px" aria-labelledby="faq-h">
  <div class="wrap">
    <div class="rv" style="text-align:center;margin-bottom:30px">
      <span class="eyebrow"><?= $T('Questions', 'सवाल') ?></span>
      <h2 class="big" id="faq-h"><?= $T('Good to', 'जान लीजिए') ?> <em><?= $T('know', '') ?></em></h2>
    </div>
    <div class="faq">
      <?php foreach ($faq as $q): ?>
      <details class="rv"><summary><?= $T($q[0], $q[1]) ?><span aria-hidden="true">+</span></summary><p><?= $T($q[2], $q[3]) ?></p></details>
      <?php endforeach; ?>
    </div>
  </div>
</section>
</main>

<footer>
  <div class="wrap">
    <div class="cols">
      <div class="brand">
        <a class="logo" href="/"><b>M</b><span>Maakit</span></a>
        <span><?= $T('Local shopping, vehicle and service bookings for the towns and villages of Uttar Pradesh.', 'उत्तर प्रदेश के कस्बों और गाँवों के लिए स्थानीय खरीदारी, गाड़ी और सेवा बुकिंग।') ?></span>
        <span>Jagarnathpur, Bhadohi, Uttar Pradesh 221403</span>
        <div class="contact"><a class="chip" href="tel:<?= h($tel) ?>"><?= h($show) ?></a><a class="chip" href="https://wa.me/<?= h($wa) ?>">WhatsApp</a></div>
      </div>
      <div class="col"><b><?= $T('For partners', 'साझेदारों के लिए') ?></b><a href="/register-business.php"><?= $T('Join as a shop', 'दुकान जोड़िए') ?></a><a href="https://smartsarathi.in/sarathi.php"><?= $T('Join as Sarathi', 'सारथी बनिए') ?></a><a href="#investors"><?= $T('For investors', 'निवेशकों के लिए') ?></a></div>
      <div class="col"><b><?= $T('Help', 'मदद') ?></b><a href="/track.php"><?= $T('Track an order', 'ऑर्डर ट्रैक कीजिए') ?></a><a href="/support.php"><?= $T('Help and complaints', 'मदद और शिकायत') ?></a><a href="/login.php"><?= $T('Shop and team login', 'दुकान और टीम लॉगिन') ?></a></div>
      <div class="col"><b><?= $T('Rules', 'नियम') ?></b><a href="/niyam.php"><?= $T('Terms', 'नियम और शर्तें') ?></a><a href="/photo-credits.php"><?= $T('Photo credits', 'फ़ोटो श्रेय') ?></a></div>
    </div>
    <div class="low">
      <span>© <?= date('Y') ?> Maakit · <?= $T('Udyam-registered micro enterprise (MSME)', 'उद्यम-पंजीकृत सूक्ष्म उद्यम (MSME)') ?></span>
      <a href="/?intro=1"><?= $T('Replay intro', 'इंट्रो फिर से देखिए') ?></a>
    </div>
  </div>
</footer>

<a class="wa-fab" href="https://wa.me/<?= h($wa) ?>" aria-label="<?= $T('Chat with us on WhatsApp', 'WhatsApp पर बात कीजिए') ?>"><?= $ico('M4 20l1.5-4A8 8 0 1 1 8 19z', 22) ?><span><?= $T('Chat with us', 'बात कीजिए') ?></span></a>

<script>
(function () {
  var reduce = window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches;
  // ---- intro ----
  var box = document.getElementById('mk-intro');
  var force = /[?&]intro=1/.test(location.search), seen = false;
  try { seen = sessionStorage.getItem('mk_intro') === '1'; } catch (e) {}
  if (box && !reduce && (force || !seen)) {
    var names = <?= json_encode($scripts, JSON_UNESCAPED_UNICODE) ?>, nm = box.querySelector('.nm'), i = 0, done = false, timer;
    box.hidden = false; document.body.style.overflow = 'hidden';
    var close = function () {
      if (done) return; done = true; clearInterval(timer);
      box.classList.add('out'); document.body.style.overflow = '';
      try { sessionStorage.setItem('mk_intro', '1'); } catch (e) {}
      setTimeout(function () { box.hidden = true; }, 750);
    };
    timer = setInterval(function () {
      if (i < names.length) { nm.textContent = names[i++]; return; }
      clearInterval(timer); box.classList.add('final'); setTimeout(close, 2300);
    }, 230);
    document.getElementById('mk-skip').addEventListener('click', close);
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') close(); });
  }
  // ---- hero: likhte hue badalta shabd ----
  var rot = document.getElementById('mk-rot'), words = <?= json_encode($words, JSON_UNESCAPED_UNICODE) ?>;
  if (rot && !reduce && words.length > 1) {
    var w = 0, c = words[0].length, del = false;
    setTimeout(function tick() {
      var full = words[w];
      if (!del) { c++; if (c > full.length) { del = true; return setTimeout(tick, 1600); } }
      else { c--; if (c < 0) { del = false; c = 0; w = (w + 1) % words.length; full = words[w]; } }
      rot.textContent = Array.from(full).slice(0, Math.max(c, 0)).join('') || ' ';
      setTimeout(tick, del ? 45 : 90);
    }, 2600);
  }
  // ---- scroll par cheezein aati hain ----
  var els = document.querySelectorAll('.rv');
  if ('IntersectionObserver' in window && !reduce) {
    var io = new IntersectionObserver(function (es) { es.forEach(function (e) { if (e.isIntersecting) { e.target.classList.add('in'); io.unobserve(e.target); } }); }, { threshold: .12 });
    els.forEach(function (el) { io.observe(el); });
  } else { els.forEach(function (el) { el.classList.add('in'); }); }
})();
if ('serviceWorker' in navigator) {
  window.addEventListener('load', function () { navigator.serviceWorker.register('/sw.js').catch(function () {}); });
}
</script>
</body>
</html>
