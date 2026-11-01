<?php
// ============================================================
// Maakit — group ka home page (landing page)
//
// Owner ka faisla, 1 November 2026:
//   "maakit.in ka home page" yahi naya page ho. Website par ab
//   saaman ka order NAHI lagta — order Maakit app se hoga, aur
//   jab tak app Play Store par nahi aata, phone / WhatsApp se.
//   Investor aur ad ke liye sab sewayein ek jagah dikhni chahiye.
//
// Purana home page (catalogue, banner, dukaanein) hata nahi hai —
// wo purana-ghar.php me hai. Waapas laana ho to bas wahi file
// index.php bana dijiye.
//
// Rang (owner: "khud ka sundar rang lo"): peacock teal + marigold
// + coral, cream background. Sab rang neeche :root me variables
// hain — naye code me hex mat likhiye, variable lijiye.
//
// Intro: "Maakit" haath ki likhawat me banta hai, phir 11
// bhashaon me naam badalta hai. ~8 second ya tap par band.
// Ek session me ek hi baar (sessionStorage), ?intro=1 se dobara.
//
// Sach ka niyam (AGENTS.md §4): koi nakli ginti, offer ya daam
// nahi. Jo abhi bana nahi (Bhojpuri, merchant plan ka daam,
// app store) wo "jald" likha hai ya likha hi nahi.
// ============================================================
require_once __DIR__ . '/inc/fn.php';

$wa    = defined('MAAKIT_WA') ? MAAKIT_WA : '918429393903';
$tel   = defined('MAAKIT_PHONE') ? MAAKIT_PHONE : '+918429393903';
$show  = defined('MAAKIT_NUMBER_SHOW') ? MAAKIT_NUMBER_SHOW : '84293 93903';
$T     = function ($en, $hi) { return h(t($en, $hi)); };

$icons = [
  'shop'   => 'M3 10l1.5-5h15L21 10M4 10v10h16V10M9 20v-6h6v6',
  'ride'   => 'M3 17a3 3 0 1 0 6 0a3 3 0 1 0 -6 0M15 17a3 3 0 1 0 6 0a3 3 0 1 0 -6 0M6 17l4-8h5l3 8M10 9H7',
  'parcel' => 'M3 7l9-4 9 4v10l-9 4-9-4zM3 7l9 4 9-4M12 11v10',
  'car'    => 'M3 13l2-6h14l2 6v5H3zM3 13h18M7 18v2M17 18v2',
  'truck'  => 'M2 6h12v10H2zM14 9h4l3 4v3h-7M5 19h2M16 19h2',
  'share'  => 'M9 11a4 4 0 1 0 0-8a4 4 0 1 0 0 8M2 21c1-4 4-6 7-6s6 2 7 6M16 4a3 3 0 0 1 0 6M22 21c-.5-3-2-5-4-5.5',
  'tools'  => 'M14 6a4 4 0 0 0 5 5l-9 9a2 2 0 0 1-3-3l9-9a4 4 0 0 0-2-2z',
  'book'   => 'M4 5a2 2 0 0 1 2-2h13v16H6a2 2 0 0 0-2 2zM4 21V5',
  'school' => 'M2 9l10-5 10 5-10 5zM6 11v5c3 2 9 2 12 0v-5',
  'star'   => 'M12 3l2.6 5.6 6.1.7-4.5 4.2 1.2 6L12 16.6 6.6 19.5l1.2-6L3.3 9.3l6.1-.7z',
];
$tones = ['t1', 't2', 't3', 't4', 't5'];

// [icon, status, English title, Hindi title, English line, Hindi line]
$services = [
  ['shop',   'live',  'Shopping from local shops', 'स्थानीय दुकानों से खरीदारी', 'Groceries, medicines, sweets and more from shops near you, at the shop’s own price.', 'किराना, दवा, मिठाई और बहुत कुछ — पास की दुकानों से, दुकान के ही दाम पर।'],
  ['ride',   'soon',  'Rides', 'सवारी', 'Bike, auto and e-rickshaw, with the fare shown before you book.', 'बाइक, ऑटो और ई-रिक्शा — बुकिंग से पहले किराया दिखता है।'],
  ['parcel', 'soon',  'Parcel delivery', 'पार्सल डिलीवरी', 'Pick up and drop anything across town, confirmed by OTP at both ends.', 'शहर में कहीं भी सामान उठवाएँ और पहुँचवाएँ — दोनों ओर OTP से पुष्टि।'],
  ['car',    'live',  'Vehicle booking', 'गाड़ी बुकिंग', 'Book a Bolero, bus or tour vehicle for trips, weddings and functions.', 'यात्रा, शादी और कार्यक्रम के लिए बोलेरो, बस या टूर गाड़ी बुक करें।'],
  ['truck',  'soon',  'Goods transport', 'माल ढुलाई', 'Send goods by loader, and fill vehicles that would return empty.', 'लोडर से सामान भेजें, और खाली लौटने वाली गाड़ियों को भरें।'],
  ['share',  'soon',  'Shared travel — Saath Safar', 'साथ सफ़र', 'Share a trip with someone going the same way and pay per seat.', 'उसी रास्ते जा रहे व्यक्ति के साथ सफ़र करें और प्रति सीट भुगतान करें।'],
  ['tools',  'live',  'Local services', 'स्थानीय सेवाएँ', 'Book a pandit, tent, halwai, mistri or a salon seat.', 'पंडित जी, टेंट, हलवाई, मिस्त्री या सैलून की सीट बुक करें।'],
  ['book',   'pilot', 'Coaching Saathi', 'कोचिंग साथी', 'Free software for coaching centres; student app at ₹54 for 6 months.', 'कोचिंग संस्थानों के लिए मुफ़्त सॉफ़्टवेयर; छात्र ऐप ₹54 में 6 महीने।'],
  ['school', 'pilot', 'School ERP', 'स्कूल ERP', 'Fees, attendance, results and a parent app for schools.', 'स्कूलों के लिए फ़ीस, हाज़िरी, परिणाम और अभिभावक ऐप।'],
  ['star',   'soon',  'Maakit Jyotish', 'Maakit ज्योतिष', 'Kundli, matching and daily horoscope with an AI astrologer.', 'कुंडली, गुण मिलान और दैनिक राशिफल, AI ज्योतिषी के साथ।'],
];
$status = [
  'live'  => [t('Available', 'उपलब्ध'), 'st-live'],
  'pilot' => [t('Pilot', 'पायलट'), 'st-pilot'],
  'soon'  => [t('Coming soon', 'जल्द आ रहा है'), 'st-soon'],
];
$cats = [
  ['Food & Grocery', 'खाद्य एवं किराना'], ['Medicines & Health', 'दवा एवं स्वास्थ्य'], ['Home Essentials', 'घरेलू सामान'],
  ['Fashion', 'कपड़े एवं फ़ैशन'], ['Electronics', 'इलेक्ट्रॉनिक्स'], ['Beauty', 'सौंदर्य'],
  ['Books & Education', 'किताबें एवं शिक्षा'], ['Farming & Pets', 'खेती एवं पशुपालन'], ['Construction', 'निर्माण सामग्री'],
  ['Vehicles', 'वाहन'], ['Weddings & Events', 'शादी एवं आयोजन'], ['Services', 'सेवाएँ'],
];
$steps = [
  ['Order', 'ऑर्डर कीजिए', 'Tell us the shop and items — in the app, or by phone and WhatsApp until the app is out.', 'दुकान और सामान बताइए — ऐप से, या ऐप आने तक फ़ोन और WhatsApp से।'],
  ['Shop accepts and packs', 'दुकान मानती है, पैक करती है', 'The shop confirms the items and its own price, then packs your order.', 'दुकान सामान और अपना दाम पक्का करती है, फिर ऑर्डर पैक करती है।'],
  ['Sarathi picks up', 'सारथी सामान उठाता है', 'A nearby Sarathi collects it, confirmed with a pickup code.', 'पास का सारथी पिकअप कोड से पुष्टि करके सामान उठाता है।'],
  ['Delivered to your door', 'आपके दरवाज़े तक', 'Follow the order live and share the delivery code when it arrives.', 'ऑर्डर लाइव देखिए, पहुँचने पर डिलीवरी कोड बताइए।'],
];
$why = [
  ['An underserved market', 'अनछुआ बाज़ार', 'Large delivery apps focus on big cities. Towns and villages still run on phone calls and word of mouth.', 'बड़े डिलीवरी ऐप बड़े शहरों पर ध्यान देते हैं। कस्बे और गाँव अब भी फ़ोन कॉल और मुँह-ज़बानी पर चलते हैं।'],
  ['Many services, one platform', 'एक मंच, कई सेवाएँ', 'Shopping, rides, parcels, vehicle and service bookings, coaching and school software share one base.', 'खरीदारी, सवारी, पार्सल, गाड़ी और सेवा बुकिंग, कोचिंग और स्कूल सॉफ़्टवेयर — सब एक ही आधार पर।'],
  ['Technology built in-house', 'अपनी तकनीक', 'Customer, shop, rider and admin software are built and run by Maakit itself.', 'ग्राहक, दुकान, सारथी और एडमिन सॉफ़्टवेयर — Maakit ने स्वयं बनाए और चलाता है।'],
  ['Honest money model', 'साफ़ पैसे का नियम', 'Customers pay the shop directly in cash or UPI. Maakit earns from delivery fees and services, never by holding customer money.', 'ग्राहक नकद या UPI से सीधे दुकान को देते हैं। Maakit डिलीवरी शुल्क और सेवाओं से कमाता है, ग्राहक का पैसा कभी नहीं रखता।'],
];
$faq = [
  ['Can I order on this website?', 'क्या मैं इस वेबसाइट पर ऑर्डर कर सकता हूँ?',
   'No. Orders will be placed in the Maakit app. Until the app is on Google Play, order by phone or WhatsApp on ' . $show . '.',
   'नहीं। ऑर्डर Maakit ऐप से होंगे। जब तक ऐप Google Play पर नहीं आता, ' . $show . ' पर फ़ोन या WhatsApp से ऑर्डर कीजिए।'],
  ['Are prices higher than in the shop?', 'क्या दाम दुकान से ज़्यादा हैं?',
   'No. The price of the goods belongs to the shop. Maakit charges only a delivery fee.',
   'नहीं। सामान का दाम दुकान का है। Maakit केवल डिलीवरी शुल्क लेता है।'],
  ['How do I pay?', 'भुगतान कैसे करें?',
   'Cash on delivery, or UPI straight to the shop’s own UPI ID.',
   'सामान मिलने पर नकद, या UPI से सीधे दुकान की अपनी UPI ID पर।'],
  ['How can my shop join?', 'मेरी दुकान कैसे जुड़ेगी?',
   'Choose “Join as a shop”, fill in your details, and our team will call you.',
   '“दुकान जोड़िए” चुनिए, अपनी जानकारी भरिए — हमारी टीम आपको फ़ोन करेगी।'],
  ['How do I become a Sarathi?', 'सारथी कैसे बनें?',
   'Open the Sarathi Partner app, add your Aadhaar and PAN, and choose your vehicle and area.',
   'सारथी पार्टनर ऐप खोलिए, आधार और PAN जोड़िए, और अपनी गाड़ी तथा इलाक़ा चुनिए।'],
];
$scripts = ['माकिट', 'মাকিট', 'மாகிட்', 'మాకిట్', 'ಮಾಕಿಟ್', 'മാകിറ്റ്', 'માકિટ', 'ਮਾਕਿਟ', 'ମାକିଟ୍', 'ماکٹ', 'Maakit'];
$svg = function ($d, $size = 26) {
  return '<svg width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="' . h($d) . '"/></svg>';
};
?><!doctype html>
<html lang="<?= html_lang() ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= $T('Maakit — shops, rides, parcels and services in one app', 'Maakit — दुकान, सवारी, पार्सल और सेवाएँ, एक ऐप में') ?></title>
<meta name="description" content="<?= $T('Maakit brings local shops, rides, parcels, vehicle and service bookings onto one platform for the towns and villages of Uttar Pradesh. Shop price, only a delivery fee.', 'Maakit उत्तर प्रदेश के कस्बों और गाँवों के लिए स्थानीय दुकानें, सवारी, पार्सल, गाड़ी और सेवा बुकिंग एक मंच पर लाता है। दाम दुकान का, सिर्फ़ डिलीवरी शुल्क।') ?>">
<link rel="canonical" href="https://maakit.in/">
<meta name="theme-color" content="#0E5C63">
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
<link rel="preload" href="/smartsarathi/assets/fonts/jakarta-700.woff2" as="font" type="font/woff2" crossorigin>
<style>
@font-face{font-family:'Plus Jakarta Sans';font-weight:400;font-display:swap;src:url(/smartsarathi/assets/fonts/jakarta-400.woff2) format('woff2')}
@font-face{font-family:'Plus Jakarta Sans';font-weight:600;font-display:swap;src:url(/smartsarathi/assets/fonts/jakarta-600.woff2) format('woff2')}
@font-face{font-family:'Plus Jakarta Sans';font-weight:700;font-display:swap;src:url(/smartsarathi/assets/fonts/jakarta-700.woff2) format('woff2')}
@font-face{font-family:'Plus Jakarta Sans';font-weight:800;font-display:swap;src:url(/smartsarathi/assets/fonts/jakarta-800.woff2) format('woff2')}
@font-face{font-family:'Noto Sans Devanagari';font-weight:400;font-display:swap;src:url(/smartsarathi/assets/fonts/noto-deva-400.woff2) format('woff2');unicode-range:U+0900-097F,U+1CD0-1CF9,U+200C-200D,U+20A8,U+20B9,U+20F0,U+25CC,U+A830-A839,U+A8E0-A8FF}
@font-face{font-family:'Noto Sans Devanagari';font-weight:600;font-display:swap;src:url(/smartsarathi/assets/fonts/noto-deva-600.woff2) format('woff2');unicode-range:U+0900-097F,U+1CD0-1CF9,U+200C-200D,U+20A8,U+20B9,U+20F0,U+25CC,U+A830-A839,U+A8E0-A8FF}
@font-face{font-family:'Noto Sans Devanagari';font-weight:700;font-display:swap;src:url(/smartsarathi/assets/fonts/noto-deva-700.woff2) format('woff2');unicode-range:U+0900-097F,U+1CD0-1CF9,U+200C-200D,U+20A8,U+20B9,U+20F0,U+25CC,U+A830-A839,U+A8E0-A8FF}
@font-face{font-family:'Kalam';font-weight:700;font-display:swap;src:url(/assets/fonts/kalam-latin-700.woff2) format('woff2');unicode-range:U+0000-00FF}
:root{--teal:#0E5C63;--teal-d:#0A3D42;--teal-x:#10292B;--teal-s:#E0F2F1;--gold:#F9B233;--gold-s:#FEF0D2;--gold-t:#8A5300;--coral:#E5533D;--coral-s:#FDE5E0;--coral-t:#A83322;--leaf-s:#E5F3E0;--leaf-t:#2F6B22;--sky-s:#E1EEFA;--sky-t:#1E4F86;--cream:#FFF8EE;--line:#ECE3D3;--ink:#1B2626;--muted:#3E4B4B;--on-dark:#CDE7E6;--f:'Noto Sans Devanagari','Plus Jakarta Sans','Mukta','Hind',sans-serif;--hand:'Kalam',cursive}
*{box-sizing:border-box}
html{scroll-behavior:smooth}
body{margin:0;background:var(--cream);color:var(--ink);font-family:var(--f);line-height:1.6;font-size:16px}
button,input{font:inherit}
a{color:var(--teal)}
.wrap{max-width:1200px;margin:0 auto;padding:0 20px}
.hand{font-family:var(--hand);font-weight:700}
.btn{display:inline-flex;align-items:center;gap:8px;min-height:50px;padding:12px 22px;border-radius:14px;font-weight:700;text-decoration:none;font-size:16px}
.btn-teal{background:var(--teal);color:#fff}.btn-gold{background:var(--gold);color:#3B2500}.btn-line{background:#fff;color:var(--teal);border:1.5px solid var(--teal)}
.btn:focus-visible,.lang a:focus-visible,#mk-intro button:focus-visible{outline:3px solid var(--coral);outline-offset:2px}
/* intro */
#mk-intro{position:fixed;inset:0;z-index:50;background:var(--cream);display:flex;flex-direction:column;align-items:center;justify-content:center;gap:6px;overflow:hidden;transition:opacity .6s}
#mk-intro.out{opacity:0;pointer-events:none}
#mk-intro .blob{position:absolute;border-radius:50%}
#mk-intro svg.word{width:min(640px,92vw);height:auto;position:relative}
#mk-intro .draw{font-family:var(--hand);font-weight:700;font-size:168px;fill:var(--teal);stroke:var(--teal);stroke-width:2.5;stroke-linejoin:round;stroke-dasharray:900;animation:mkDraw 2.6s ease-out forwards}
#mk-intro .scripts{position:relative;height:60px;width:100%;text-align:center;font-size:clamp(30px,7vw,44px);font-weight:700;color:var(--coral)}
#mk-intro .scripts span{position:absolute;left:0;right:0;opacity:0;animation:mkWord 6.6s linear infinite}
#mk-intro p{position:relative;margin:6px 20px 0;font-size:clamp(16px,4vw,20px);color:var(--muted);text-align:center;animation:mkRise .9s ease-out 1.8s both}
#mk-intro button{position:relative;margin-top:24px;min-height:48px;background:var(--teal);color:#fff;border:0;border-radius:26px;padding:12px 26px;font-weight:700;cursor:pointer}
@keyframes mkDraw{0%{stroke-dashoffset:900;fill-opacity:0}70%{stroke-dashoffset:0;fill-opacity:0}100%{stroke-dashoffset:0;fill-opacity:1}}
@keyframes mkWord{0%{opacity:0;transform:translateY(14px)}3%{opacity:1;transform:none}8%{opacity:1;transform:none}10%{opacity:0;transform:translateY(-14px)}100%{opacity:0}}
@keyframes mkRise{from{opacity:0;transform:translateY(16px)}to{opacity:1;transform:none}}
@keyframes mkMarquee{from{transform:translateX(0)}to{transform:translateX(-50%)}}
@keyframes mkFloat{0%,100%{transform:translateY(0)}50%{transform:translateY(-10px)}}
/* header */
.bar{background:var(--teal-d);color:#FCE3B0;font-size:14px;text-align:center;padding:9px 16px}
header.top{background:var(--cream);border-bottom:1px solid var(--line);position:sticky;top:0;z-index:20}
header.top .wrap{display:flex;flex-wrap:wrap;align-items:center;gap:10px 24px;padding-top:12px;padding-bottom:12px}
.logo{display:flex;align-items:center;gap:10px;text-decoration:none;color:var(--teal);margin-right:auto}
.logo b{width:42px;height:42px;border-radius:13px;background:var(--teal);color:var(--gold);display:flex;align-items:center;justify-content:center;font-family:var(--hand);font-size:26px;line-height:1}
.logo span{font-family:var(--hand);font-size:30px;font-weight:700;line-height:1}
nav.menu{display:flex;flex-wrap:wrap;gap:2px 20px;font-size:15px;font-weight:600}
nav.menu a{color:var(--ink);text-decoration:none;padding:8px 0}
nav.menu a.inv{color:#B23B12}
.lang{display:flex;border:1.5px solid var(--teal);border-radius:20px;overflow:hidden}
.lang a{padding:8px 14px;font-size:14px;font-weight:700;text-decoration:none;color:var(--teal)}
.lang a.on{background:var(--teal);color:#fff}
/* hero */
.hero{position:relative;overflow:hidden;padding:48px 0 72px}
.hero .blob{position:absolute;border-radius:50%}
.hero .wrap{position:relative;display:flex;flex-wrap:wrap;align-items:center;gap:44px}
.hero-text{flex:1 1 480px;min-width:0;display:flex;flex-direction:column;gap:18px;animation:mkRise .9s ease-out both}
.pill{align-self:flex-start;background:var(--gold-s);color:var(--gold-t);font-size:14px;font-weight:700;padding:7px 14px;border-radius:20px;border:1px solid #F6D58E}
.hero h1{margin:0;font-size:clamp(34px,6vw,56px);line-height:1.15;font-weight:800;color:var(--teal-d)}
.hero h1 .hand{color:var(--coral)}
.hero p.sub{margin:0;font-size:clamp(17px,2.2vw,19px);color:var(--muted);max-width:600px}
.ctas{display:flex;flex-wrap:wrap;gap:12px}
.ticks{display:flex;flex-wrap:wrap;gap:6px 20px;font-size:14px;color:var(--muted)}
.ticks span{display:flex;align-items:center;gap:6px}
.ticks svg{color:#2F8F5B}
.phone-wrap{flex:0 0 300px;margin:0 auto;position:relative;animation:mkFloat 5s ease-in-out infinite}
.phone{width:300px;height:600px;border-radius:42px;background:var(--teal-x);padding:12px}
.screen{width:100%;height:100%;border-radius:32px;background:#fff;overflow:hidden;display:flex;flex-direction:column}
.screen .sh{background:var(--teal);color:#fff;padding:26px 16px 14px;display:flex;flex-direction:column;gap:8px}
.screen .sh small{color:var(--gold);font-weight:700;font-size:12px}
.screen .sh b{font-size:15px}
.screen .sh i{font-style:normal;background:#fff;color:#5A6666;border-radius:10px;padding:9px 10px;font-size:12px}
.tiles{padding:14px;display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px}
.tiles span{display:flex;flex-direction:column;align-items:center;gap:6px;font-size:11px;font-weight:600;text-align:center;line-height:1.25}
.tiles em{width:64px;height:56px;border-radius:16px;display:flex;align-items:center;justify-content:center}
.screen .card{margin:6px 14px;border-radius:14px;padding:12px;font-size:12px}
.float-chip{position:absolute;border-radius:14px;padding:10px 14px;font-size:13px;font-weight:700}
.t1{background:var(--teal-s);color:var(--teal)}.t2{background:var(--gold-s);color:var(--gold-t)}.t3{background:var(--coral-s);color:var(--coral-t)}.t4{background:var(--leaf-s);color:var(--leaf-t)}.t5{background:var(--sky-s);color:var(--sky-t)}
/* marquee */
.cats{background:var(--teal);color:#fff;padding:24px 0;overflow:hidden}
.cats h2{margin:0 0 14px;font-size:15px;font-weight:700;text-align:center;color:#FCE3B0}
.track{display:flex;width:max-content;animation:mkMarquee 40s linear infinite}
.track span{display:flex;align-items:center;gap:10px;margin-right:12px;background:#0A4A50;border-radius:26px;padding:10px 18px;font-size:16px;font-weight:600;white-space:nowrap}
.track i{width:10px;height:10px;border-radius:5px;background:var(--gold)}
.track span:nth-child(3n+2) i{background:var(--coral)}.track span:nth-child(3n) i{background:#7FD1C7}
/* sections */
section.s{padding:72px 0}
.head{text-align:center;display:flex;flex-direction:column;gap:8px;align-items:center;margin-bottom:30px}
.head .hand{font-size:24px;color:var(--coral)}
.head h2{margin:0;font-size:clamp(28px,4.5vw,42px);font-weight:800;line-height:1.2;color:var(--teal-d)}
.head p{margin:0;font-size:18px;color:var(--muted);max-width:720px}
.grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:18px}
.svc{background:#fff;border:1px solid var(--line);border-radius:22px;padding:24px;display:flex;flex-direction:column;gap:12px}
.svc .row{display:flex;justify-content:space-between;align-items:flex-start;gap:8px}
.svc .ic{width:58px;height:58px;border-radius:18px;display:flex;align-items:center;justify-content:center}
.svc h3{margin:0;font-size:20px;font-weight:800;color:var(--teal-d)}
.svc p{margin:0;font-size:15px;color:var(--muted)}
.st{font-size:12px;font-weight:700;padding:5px 10px;border-radius:12px;white-space:nowrap}
.st-live{background:var(--leaf-s);color:var(--leaf-t)}.st-pilot{background:var(--gold-s);color:var(--gold-t)}.st-soon{background:#EEF0F0;color:var(--muted)}
.how{background:var(--gold-s)}
.how .head .hand{color:#B23B12}
.steps{margin:0;padding:0;list-style:none;display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:18px}
.steps li{background:#fff;border-radius:22px;padding:26px;display:flex;flex-direction:column;gap:8px}
.steps .n{font-family:var(--hand);font-size:46px;line-height:1;font-weight:700}
.steps li:nth-child(1) .n{color:var(--teal)}.steps li:nth-child(2) .n{color:#B23B12}.steps li:nth-child(3) .n{color:var(--leaf-t)}.steps li:nth-child(4) .n{color:var(--coral)}
.steps h3{margin:0;font-size:19px;font-weight:800;color:var(--teal-d)}
.steps p{margin:0;font-size:15px;color:var(--muted)}
.promise{margin-top:22px;background:var(--teal);color:#fff;border-radius:24px;padding:28px;display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:16px}
.promise b{font-size:clamp(22px,3vw,28px);font-weight:800;display:block}
.promise span{color:var(--on-dark);font-size:17px}
.apps .grid{grid-template-columns:repeat(auto-fill,minmax(290px,1fr))}
.app{border-radius:24px;padding:28px;display:flex;flex-direction:column;gap:10px}
.app small{font-size:13px;font-weight:700}
.app h3{margin:0;font-size:24px;font-weight:800}
.app p{margin:0;font-size:15px}
.app ul{margin:0;padding:0 0 0 18px;font-size:14px;line-height:1.8}
.app .go{margin-top:auto;align-self:flex-start}
.a1{background:var(--teal);color:#fff}.a1 small{color:var(--gold)}.a1 p,.a1 ul{color:var(--on-dark)}
.a2{background:var(--gold-s);color:var(--teal-d)}.a2 small{color:var(--gold-t)}.a2 p,.a2 ul{color:#4A3A1C}
.a3{background:var(--coral-s);color:var(--teal-d)}.a3 small{color:var(--coral-t)}.a3 p,.a3 ul{color:#4A2A22}
.inv{position:relative;overflow:hidden;background:var(--teal-d);color:#fff;padding:84px 0}
.inv .blob{position:absolute;width:380px;height:380px;border-radius:50%;background:var(--teal);right:-120px;top:-120px}
.inv .wrap{position:relative}
.inv .hand{font-size:26px;color:var(--gold)}
.inv h2{margin:6px 0 10px;font-size:clamp(30px,4.5vw,44px);font-weight:800;line-height:1.2}
.inv .lead{margin:0 0 30px;font-size:18px;color:var(--on-dark);max-width:780px}
.why{background:#0F4A50;border-radius:22px;padding:26px;border-top:4px solid var(--gold)}
.why:nth-child(2){border-top-color:var(--coral)}.why:nth-child(3){border-top-color:#7FD1C7}.why:nth-child(4){border-top-color:#A7D88F}
.why h3{margin:0 0 8px;font-size:19px;font-weight:800}
.why p{margin:0;font-size:15px;color:var(--on-dark)}
.inv .ctas{margin-top:30px}
.inv .btn-line{background:transparent;color:#fff;border-color:#5FA3A8}
.where{display:flex;flex-wrap:wrap;gap:24px}
.where>div:first-child{flex:1 1 340px}
.where .hand{font-size:24px;color:var(--coral)}
.where h2{margin:4px 0 8px;font-size:clamp(28px,4vw,36px);font-weight:800;color:var(--teal-d)}
.where .boxes{flex:1 1 520px;display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:14px}
.where .box{border-radius:22px;padding:24px}
.where .box small{font-size:13px;font-weight:700}
.where .box b{display:block;font-size:22px;font-weight:800;color:var(--teal-d);margin:4px 0}
.faq{max-width:900px}
.faq details{background:#fff;border:1px solid var(--line);border-radius:18px;padding:18px 22px;margin-bottom:12px}
.faq summary{cursor:pointer;font-size:17px;font-weight:800;color:var(--teal-d);min-height:28px}
.faq p{margin:10px 0 0;color:var(--muted)}
footer{background:var(--teal-x);color:#C9D6D6;padding:48px 0 30px;font-size:14px}
footer .cols{display:flex;flex-wrap:wrap;gap:30px;justify-content:space-between}
footer .brand{flex:1 1 280px;display:flex;flex-direction:column;gap:8px}
footer .brand .hand{font-size:34px;color:var(--gold);line-height:1}
footer .col{flex:0 1 180px;display:flex;flex-direction:column;gap:8px}
footer .col b{color:#fff}
footer a{color:#C9D6D6;text-decoration:none}
footer .low{border-top:1px solid #2A4446;margin-top:26px;padding-top:16px;display:flex;flex-wrap:wrap;justify-content:space-between;gap:8px;font-size:13px}
footer .low a{color:var(--gold);font-weight:700}
@media (max-width:640px){.float-chip{display:none}section.s{padding:56px 0}.hero{padding:32px 0 56px}}
@media (prefers-reduced-motion:reduce){*{animation:none!important;transition:none!important}#mk-intro .draw{stroke-dasharray:none;fill-opacity:1}#mk-intro .scripts span{position:static;opacity:1;display:none}#mk-intro .scripts span:first-child{display:inline}}
</style>
</head>
<body>

<div id="mk-intro" role="dialog" aria-label="Maakit" hidden>
  <span class="blob" style="width:420px;height:420px;background:var(--gold-s);left:-120px;top:-140px"></span>
  <span class="blob" style="width:300px;height:300px;background:var(--teal-s);right:-80px;bottom:-90px"></span>
  <span class="blob" style="width:90px;height:90px;background:var(--coral-s);right:18%;top:14%"></span>
  <svg class="word" viewBox="0 0 640 220" role="img" aria-label="Maakit"><text class="draw" x="320" y="160" text-anchor="middle">Maakit</text></svg>
  <div class="scripts" aria-hidden="true">
    <?php foreach ($scripts as $i => $w): ?><span style="animation-delay:<?= number_format(2.4 + $i * 0.6, 1) ?>s"><?= h($w) ?></span><?php endforeach; ?>
  </div>
  <p><?= $T('Your town’s own app — shops, rides and services', 'आपके शहर का अपना ऐप — दुकानें, सवारी और सेवाएँ') ?></p>
  <button type="button" id="mk-enter"><?= $T('Enter the site', 'वेबसाइट देखिए') ?></button>
</div>

<div class="bar"><?= $T('Maakit is growing across Bhadohi and the Varanasi region — shops, Sarathis and investors are welcome to join.', 'Maakit भदोही और वाराणसी क्षेत्र में बढ़ रहा है — दुकानदार, सारथी और निवेशक जुड़ सकते हैं।') ?></div>

<header class="top">
  <div class="wrap">
    <a class="logo" href="/"><b>M</b><span>Maakit</span></a>
    <nav class="menu" aria-label="<?= $T('Main', 'मुख्य') ?>">
      <a href="#services"><?= $T('Services', 'सेवाएँ') ?></a>
      <a href="#how"><?= $T('How it works', 'कैसे काम करता है') ?></a>
      <a href="#apps"><?= $T('Our apps', 'हमारे ऐप') ?></a>
      <a class="inv" href="#investors"><?= $T('For investors', 'निवेशकों के लिए') ?></a>
    </nav>
    <div class="lang" role="group" aria-label="Language">
      <a href="<?= h(lang_url('en')) ?>" class="<?= is_hi() ? '' : 'on' ?>" lang="en">EN</a>
      <a href="<?= h(lang_url('hi')) ?>" class="<?= is_hi() ? 'on' : '' ?>" lang="hi">हिंदी</a>
    </div>
  </div>
</header>

<main>
<section class="hero" id="top">
  <span class="blob" style="width:520px;height:520px;background:var(--gold-s);right:-140px;top:-120px"></span>
  <span class="blob" style="width:140px;height:140px;background:var(--teal-s);left:46%;bottom:30px"></span>
  <div class="wrap">
    <div class="hero-text">
      <span class="pill"><?= $T('One app for your whole town', 'पूरे शहर के लिए एक ऐप') ?></span>
      <h1><?= $T('Shops, rides, parcels and services —', 'दुकान, सवारी, पार्सल और सेवाएँ —') ?> <span class="hand"><?= $T('all in Maakit.', 'सब Maakit में।') ?></span></h1>
      <p class="sub"><?= $T('Get things from real local shops at the shop’s own price, book a vehicle or a service, and follow every step. Built for the towns and villages of Uttar Pradesh.', 'अपने आस-पास की असली दुकानों से दुकान के ही दाम पर सामान मँगाइए, गाड़ी या सेवा बुक कीजिए और हर कदम देखिए। उत्तर प्रदेश के कस्बों और गाँवों के लिए बना।') ?></p>
      <div class="ctas">
        <a class="btn btn-teal" href="https://wa.me/<?= h($wa) ?>"><?= $T('Order on WhatsApp', 'WhatsApp पर ऑर्डर') ?></a>
        <a class="btn btn-gold" href="/register-business.php"><?= $T('Join as a shop', 'दुकान जोड़िए') ?></a>
        <a class="btn btn-line" href="https://smartsarathi.in/sarathi.php"><?= $T('Join as Sarathi', 'सारथी बनिए') ?></a>
      </div>
      <div class="ticks">
        <span><?= $svg('M5 12l5 5L20 7', 16) ?><?= $T('Hindi and English', 'हिंदी और English') ?></span>
        <span><?= $svg('M5 12l5 5L20 7', 16) ?><?= $T('Cash or UPI to the shop', 'नकद या UPI सीधे दुकान को') ?></span>
        <span><?= $svg('M5 12l5 5L20 7', 16) ?><?= $T('Order tracking', 'ऑर्डर ट्रैकिंग') ?></span>
      </div>
    </div>
    <div class="phone-wrap" aria-hidden="true">
      <div class="phone"><div class="screen">
        <div class="sh"><small><?= $T('Maakit app', 'Maakit ऐप') ?></small><b><?= $T('Home', 'घर') ?></b><i><?= $T('Search flour, medicines, sweets…', 'आटा, दवा, मिठाई खोजिए…') ?></i></div>
        <div class="tiles">
          <?php foreach ([['shop', 'Shopping', 'खरीदारी'], ['ride', 'Rides', 'सवारी'], ['parcel', 'Parcel', 'पार्सल'], ['car', 'Vehicle', 'गाड़ी'], ['truck', 'Goods', 'माल'], ['tools', 'Services', 'सेवाएँ']] as $i => $p): ?>
          <span><em class="<?= $tones[$i % 5] ?>"><?= $svg($icons[$p[0]]) ?></em><?= $T($p[1], $p[2]) ?></span>
          <?php endforeach; ?>
        </div>
        <div class="card" style="background:var(--gold);color:#3B2500"><b style="font-size:14px"><?= $T('Same price as the shop', 'दाम वही, जो दुकान पर') ?></b></div>
        <div class="card" style="border:1px solid var(--line);display:flex;gap:10px;align-items:center"><span style="width:34px;height:34px;border-radius:17px;background:var(--coral);flex-shrink:0"></span><span><b><?= $T('Sarathi is on the way', 'सारथी रास्ते में है') ?></b><br><?= $T('Live order status', 'ऑर्डर की लाइव जानकारी') ?></span></div>
      </div></div>
      <span class="float-chip" style="left:-46px;top:120px;background:#fff;border:1px solid var(--line);color:var(--teal)"><?= $T('Shop’s own price', 'दुकान का ही दाम') ?></span>
      <span class="float-chip" style="right:-40px;bottom:140px;background:var(--coral);color:#fff"><?= $T('Pickup & delivery code', 'पिकअप और डिलीवरी कोड') ?></span>
    </div>
  </div>
</section>

<section class="cats" aria-labelledby="cats-h">
  <h2 id="cats-h"><?= $T('From every kind of shop in your area', 'आपके इलाक़े की हर तरह की दुकान से') ?></h2>
  <div class="track" aria-hidden="true">
    <?php for ($k = 0; $k < 2; $k++) foreach ($cats as $c): ?><span><i></i><?= $T($c[0], $c[1]) ?></span><?php endforeach; ?>
  </div>
</section>

<section class="s" id="services" aria-labelledby="svc-h">
  <div class="wrap">
    <div class="head">
      <span class="hand"><?= $T('Everything in one place', 'सब कुछ एक जगह') ?></span>
      <h2 id="svc-h"><?= $T('One group. Every everyday service.', 'एक समूह। रोज़ की हर सेवा।') ?></h2>
      <p><?= $T('Maakit brings local shops, Sarathis, vehicle owners and service providers onto one platform.', 'Maakit स्थानीय दुकानों, सारथियों, वाहन मालिकों और सेवा देने वालों को एक मंच पर लाता है।') ?></p>
    </div>
    <div class="grid">
      <?php foreach ($services as $i => $s): ?>
      <article class="svc">
        <div class="row"><span class="ic <?= $tones[$i % 5] ?>"><?= $svg($icons[$s[0]], 30) ?></span><span class="st <?= $status[$s[1]][1] ?>"><?= h($status[$s[1]][0]) ?></span></div>
        <h3><?= $T($s[2], $s[3]) ?></h3>
        <p><?= $T($s[4], $s[5]) ?></p>
      </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="s how" id="how" aria-labelledby="how-h">
  <div class="wrap">
    <div class="head">
      <span class="hand"><?= $T('Simple and honest', 'आसान और साफ़') ?></span>
      <h2 id="how-h"><?= $T('How an order reaches you', 'ऑर्डर आप तक कैसे पहुँचता है') ?></h2>
      <p><?= $T('The customer, the shop and the Sarathi all see each step of the same order.', 'ग्राहक, दुकान और सारथी — तीनों एक ही ऑर्डर का हर कदम देखते हैं।') ?></p>
    </div>
    <ol class="steps">
      <?php foreach ($steps as $i => $st): ?>
      <li><span class="n"><?= $i + 1 ?></span><h3><?= $T($st[0], $st[1]) ?></h3><p><?= $T($st[2], $st[3]) ?></p></li>
      <?php endforeach; ?>
    </ol>
    <div class="promise">
      <div><b><?= $T('Same price as the shop.', 'दाम वही, जो दुकान पर।') ?></b><span><?= $T('The price of the goods belongs to the shop. Maakit charges only a delivery fee.', 'सामान का दाम दुकान का है। Maakit केवल डिलीवरी शुल्क लेता है।') ?></span></div>
      <a class="btn btn-gold" href="tel:<?= h($tel) ?>"><?= $T('Call', 'फ़ोन') ?> <?= h($show) ?></a>
    </div>
  </div>
</section>

<section class="s apps" id="apps" aria-labelledby="apps-h">
  <div class="wrap">
    <div class="head">
      <span class="hand"><?= $T('Built for everyone', 'सबके लिए बना') ?></span>
      <h2 id="apps-h"><?= $T('Three apps. One platform.', 'तीन ऐप। एक मंच।') ?></h2>
      <p><?= $T('Every order connects the customer, the shop and the Sarathi.', 'हर ऑर्डर ग्राहक, दुकान और सारथी को जोड़ता है।') ?></p>
    </div>
    <div class="grid">
      <article class="app a1">
        <small><?= $T('For everyone', 'सभी के लिए') ?></small>
        <h3><?= $T('Maakit — Customer app', 'Maakit — ग्राहक ऐप') ?></h3>
        <p><?= $T('Shop, ride, send and book from one app.', 'एक ही ऐप से खरीदिए, सवारी लीजिए, भेजिए और बुक कीजिए।') ?></p>
        <ul><li><?= $T('Hindi and English', 'हिंदी और English') ?></li><li><?= $T('Cash or UPI to the shop', 'नकद या UPI सीधे दुकान को') ?></li><li><?= $T('Order tracking', 'ऑर्डर ट्रैकिंग') ?></li></ul>
        <span class="btn btn-gold go"><?= $T('Coming soon on Google Play', 'जल्द ही Google Play पर') ?></span>
      </article>
      <article class="app a2">
        <small><?= $T('For shopkeepers', 'दुकानदारों के लिए') ?></small>
        <h3><?= $T('Maakit for shops', 'Maakit दुकान') ?></h3>
        <p><?= $T('Get orders on your phone and run your shop online.', 'फ़ोन पर ऑर्डर पाइए और दुकान ऑनलाइन चलाइए।') ?></p>
        <ul><li><?= $T('Alert for every new order', 'हर नए ऑर्डर की सूचना') ?></li><li><?= $T('Set your own prices and stock', 'अपना दाम और स्टॉक ख़ुद तय कीजिए') ?></li><li><?= $T('Your own daily account', 'अपना रोज़ का हिसाब') ?></li></ul>
        <a class="btn btn-teal go" href="/register-business.php"><?= $T('Join as a shop', 'दुकान जोड़िए') ?></a>
      </article>
      <article class="app a3">
        <small><?= $T('For riders and vehicle owners', 'सारथी और गाड़ी वालों के लिए') ?></small>
        <h3><?= $T('Sarathi Partner', 'सारथी पार्टनर') ?></h3>
        <p><?= $T('Earn with your bike, auto, car or bus in your own area.', 'अपने इलाक़े में बाइक, ऑटो, कार या बस से कमाइए।') ?></p>
        <ul><li><?= $T('Join with Aadhaar and PAN', 'आधार और PAN से जुड़िए') ?></li><li><?= $T('Choose your area and distance', 'अपना इलाक़ा और दूरी चुनिए') ?></li><li><?= $T('Work goes to the nearest Sarathi', 'काम सबसे पास के सारथी को') ?></li></ul>
        <a class="btn btn-teal go" style="background:var(--coral)" href="https://smartsarathi.in/sarathi.php"><?= $T('Join as Sarathi', 'सारथी बनिए') ?></a>
      </article>
    </div>
  </div>
</section>

<section class="inv" id="investors" aria-labelledby="inv-h">
  <span class="blob"></span>
  <div class="wrap">
    <span class="hand"><?= $T('For investors', 'निवेशकों के लिए') ?></span>
    <h2 id="inv-h"><?= $T('Why Maakit', 'Maakit क्यों') ?></h2>
    <p class="lead"><?= $T('Small towns have shops, vehicles and skilled people, but no single app that connects them. Maakit is building that platform, starting in eastern Uttar Pradesh.', 'छोटे शहरों में दुकानें, गाड़ियाँ और हुनरमंद लोग हैं, पर उन्हें जोड़ने वाला कोई एक ऐप नहीं। Maakit यही मंच बना रहा है, शुरुआत पूर्वी उत्तर प्रदेश से।') ?></p>
    <div class="grid">
      <?php foreach ($why as $w): ?><div class="why"><h3><?= $T($w[0], $w[1]) ?></h3><p><?= $T($w[2], $w[3]) ?></p></div><?php endforeach; ?>
    </div>
    <div class="ctas">
      <a class="btn btn-gold" href="tel:<?= h($tel) ?>"><?= $T('Call us', 'फ़ोन कीजिए') ?> · <?= h($show) ?></a>
      <a class="btn btn-line" href="https://wa.me/<?= h($wa) ?>"><?= $T('Message on WhatsApp', 'WhatsApp पर संदेश') ?></a>
    </div>
  </div>
</section>

<section class="s" aria-labelledby="where-h">
  <div class="wrap where">
    <div>
      <span class="hand"><?= $T('Growing village by village', 'गाँव-दर-गाँव बढ़ते हुए') ?></span>
      <h2 id="where-h"><?= $T('Where we serve', 'हम कहाँ सेवा देते हैं') ?></h2>
      <p style="margin:0;color:var(--muted)"><?= $T('A new area opens only when it has enough shops and Sarathis.', 'नया इलाक़ा तभी खुलता है जब वहाँ पर्याप्त दुकानें और सारथी हों।') ?></p>
    </div>
    <div class="boxes">
      <div class="box t1"><small><?= $T('Serving now', 'अभी सेवा') ?></small><b><?= $T('Kapsethi, Chauri, Kachhwa', 'कपसेठी, चौरी, कछवा') ?></b><span><?= $T('and nearby villages', 'और आस-पास के गाँव') ?></span></div>
      <div class="box t3"><small><?= $T('Coming next', 'आगे') ?></small><b><?= $T('More of Bhadohi and Varanasi', 'भदोही और वाराणसी के और इलाक़े') ?></b><span><?= $T('area by area', 'इलाक़ा-दर-इलाक़ा') ?></span></div>
    </div>
  </div>
</section>

<section class="s" style="padding-top:20px" aria-labelledby="faq-h">
  <div class="wrap faq">
    <div class="head"><h2 id="faq-h"><?= $T('Questions', 'सवाल-जवाब') ?></h2></div>
    <?php foreach ($faq as $q): ?>
    <details><summary><?= $T($q[0], $q[1]) ?></summary><p><?= $T($q[2], $q[3]) ?></p></details>
    <?php endforeach; ?>
  </div>
</section>
</main>

<footer>
  <div class="wrap">
    <div class="cols">
      <div class="brand">
        <span class="hand">Maakit</span>
        <span><?= $T('Local shopping, vehicle and service bookings for the towns and villages of Uttar Pradesh.', 'उत्तर प्रदेश के कस्बों और गाँवों के लिए स्थानीय खरीदारी, गाड़ी और सेवा बुकिंग।') ?></span>
        <span>Jagarnathpur, Bhadohi, Uttar Pradesh 221403</span>
        <a href="tel:<?= h($tel) ?>" style="color:var(--gold)"><?= h($show) ?></a>
      </div>
      <div class="col"><b><?= $T('Join us', 'जुड़िए') ?></b><a href="/register-business.php"><?= $T('Join as a shop', 'दुकान जोड़िए') ?></a><a href="https://smartsarathi.in/sarathi.php"><?= $T('Join as Sarathi', 'सारथी बनिए') ?></a><a href="#investors"><?= $T('For investors', 'निवेशकों के लिए') ?></a></div>
      <div class="col"><b><?= $T('Help', 'मदद') ?></b><a href="/track.php"><?= $T('Track an order', 'ऑर्डर ट्रैक कीजिए') ?></a><a href="/support.php"><?= $T('Help and complaints', 'मदद और शिकायत') ?></a><a href="/login.php"><?= $T('Shop and team login', 'दुकान और टीम लॉगिन') ?></a></div>
      <div class="col"><b><?= $T('Rules', 'नियम') ?></b><a href="/niyam.php"><?= $T('Terms', 'नियम और शर्तें') ?></a><a href="/photo-credits.php"><?= $T('Photo credits', 'फ़ोटो श्रेय') ?></a></div>
    </div>
    <div class="low">
      <span>© <?= date('Y') ?> Maakit · <?= $T('Udyam-registered micro enterprise (MSME)', 'उद्यम-पंजीकृत सूक्ष्म उद्यम (MSME)') ?></span>
      <a href="/?intro=1"><?= $T('Replay intro', 'इंट्रो फिर से देखिए') ?></a>
    </div>
  </div>
</footer>

<script>
(function () {
  var box = document.getElementById('mk-intro');
  if (!box) return;
  var force = /[?&]intro=1/.test(location.search), seen = false;
  try { seen = sessionStorage.getItem('mk_intro') === '1'; } catch (e) {}
  if (seen && !force) return;
  box.hidden = false;
  document.body.style.overflow = 'hidden';
  var done = false;
  function close() {
    if (done) return; done = true;
    box.classList.add('out');
    document.body.style.overflow = '';
    try { sessionStorage.setItem('mk_intro', '1'); } catch (e) {}
    setTimeout(function () { box.hidden = true; }, 650);
  }
  document.getElementById('mk-enter').addEventListener('click', close);
  box.addEventListener('click', function (e) { if (e.target === box) close(); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') close(); });
  setTimeout(close, 8500);
})();
if ('serviceWorker' in navigator) {
  window.addEventListener('load', function () { navigator.serviceWorker.register('/sw.js').catch(function () {}); });
}
</script>
</body>
</html>
