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
  // ---- v3 tasveerein (owner: "shop, ride, gaadi, parcel up to the mark nahi") ----
  // Ek hi style: plum outline (2.5px), halki parchhai, peeche ki jagah.
  case 'shop': $o = 'stroke="'.$P.'" stroke-width="2.5" stroke-linejoin="round"';
    return '<circle class="sun" cx="290" cy="30" r="14" fill="'.$S.'"/><rect x="0" y="148" width="320" height="32" fill="#F2C1A4"/><path d="M0 152h320" stroke="#E7A985" stroke-width="2"/>'
    .'<ellipse cx="160" cy="152" rx="112" ry="6" fill="#000" opacity=".08"/>'
    .'<rect x="70" y="46" width="180" height="104" fill="#FFF7EE" '.$o.'/>'
    .'<g><rect x="62" y="50" width="28" height="20" fill="'.$B.'"/><rect x="90" y="50" width="28" height="20" fill="'.$W.'"/><rect x="118" y="50" width="28" height="20" fill="'.$B.'"/><rect x="146" y="50" width="28" height="20" fill="'.$W.'"/><rect x="174" y="50" width="28" height="20" fill="'.$B.'"/><rect x="202" y="50" width="28" height="20" fill="'.$W.'"/><rect x="230" y="50" width="28" height="20" fill="'.$B.'"/></g>'
    .'<path d="M62 70q7 9 14 0t14 0q7 9 14 0t14 0q7 9 14 0t14 0q7 9 14 0t14 0q7 9 14 0t14 0q7 9 14 0t14 0q7 9 14 0t14 0" fill="'.$B.'" '.$o.'/><rect x="62" y="50" width="196" height="20" fill="none" '.$o.'/>'
    .'<rect x="98" y="22" width="124" height="26" rx="9" fill="'.$P.'"/><text x="160" y="40" text-anchor="middle" font-family="Lexend,sans-serif" font-size="14" font-weight="800" letter-spacing="3" fill="'.$S.'">KIRANA</text>'
    .'<rect x="84" y="82" width="152" height="3" fill="#8A3B12"/><rect x="88" y="72" width="10" height="10" rx="2" fill="#5B8DEF"/><rect x="102" y="70" width="10" height="12" rx="2" fill="#25A55F"/><rect x="116" y="73" width="12" height="9" rx="2" fill="'.$S.'"/><rect x="132" y="70" width="9" height="12" rx="2" fill="'.$B.'"/><rect x="200" y="71" width="12" height="11" rx="2" fill="#25A55F"/><rect x="216" y="73" width="14" height="9" rx="2" fill="#5B8DEF"/>'
    .'<rect x="84" y="98" width="152" height="3" fill="#8A3B12"/><rect x="88" y="87" width="14" height="11" rx="2" fill="'.$S.'"/><rect x="106" y="86" width="10" height="12" rx="2" fill="'.$B.'"/><rect x="120" y="88" width="12" height="10" rx="2" fill="#5B8DEF"/><rect x="214" y="86" width="16" height="12" rx="2" fill="'.$B.'"/>'
    .'<g class="swing"><path d="M150 72v8M160 72v12M170 72v8" stroke="'.$P.'" stroke-width="1.5"/><rect x="146" y="80" width="8" height="12" rx="1.5" fill="'.$B.'"/><rect x="156" y="84" width="8" height="12" rx="1.5" fill="'.$S.'"/><rect x="166" y="80" width="8" height="12" rx="1.5" fill="#25A55F"/></g>'
    .'<circle cx="196" cy="99" r="10" fill="'.$K.'"/><path d="M186 97a10 10 0 0 1 20 0z" fill="'.$P.'"/><rect x="186" y="106" width="20" height="10" rx="4" fill="#5B8DEF"/>'
    .'<rect x="78" y="108" width="164" height="8" rx="2" fill="#8A3B12" '.$o.'/><rect x="82" y="116" width="156" height="34" fill="'.$S.'" '.$o.'/><path d="M120 116v34M160 116v34M200 116v34" stroke="'.$P.'" stroke-width="1.5" opacity=".35"/>'
    .'<path d="M34 150l4-22h20l4 22z" fill="#C2552B" '.$o.'/><path d="M48 128c-8-10-14-20-6-30 6 8 8 18 6 30zM48 128c4-12 12-20 22-18-4 10-12 16-22 18z" fill="#25A55F"/>'
    .'<g class="bob"><rect x="262" y="116" width="30" height="32" rx="4" fill="'.$B.'" '.$o.'/><path d="M269 118v-6a8 8 0 0 1 16 0v6" fill="none" stroke="'.$P.'" stroke-width="2.5"/><path d="M270 132h14" stroke="'.$W.'" stroke-width="2.5" stroke-linecap="round"/></g>';
  case 'ride': $o = 'stroke="'.$P.'" stroke-width="2.5" stroke-linejoin="round"';
    return '<path d="M0 140V92h22V70h18v24h16V60h24v80zM230 140V80h20V64h22v30h18v-14h30v60z" fill="#C9DBF3"/><rect x="0" y="138" width="320" height="42" fill="#9FB9DE"/><path d="M0 162h320" stroke="'.$W.'" stroke-width="4" stroke-dasharray="20 14"/>'
    .'<ellipse cx="180" cy="156" rx="100" ry="6" fill="#000" opacity=".12"/>'
    .'<path d="M34 96h36M22 110h40M38 124h28" stroke="'.$W.'" stroke-width="4" stroke-linecap="round" opacity=".9"/>'
    .'<g class="drive">'
    .'<path d="M96 80q2-26 30-28h86q22 2 26 26l2 8H94z" fill="'.$P.'" '.$o.'/><path d="M104 60h120" stroke="'.$S.'" stroke-width="3"/>'
    .'<path d="M90 84h150v48a8 8 0 0 1-8 8H98a8 8 0 0 1-8-8z" fill="'.$S.'" '.$o.'/>'
    .'<path d="M240 86l24 10q10 6 8 22l-4 22h-28z" fill="'.$S.'" '.$o.'/><path d="M244 92l16 7v14h-16z" fill="#DCEBFF" '.$o.'/><circle cx="268" cy="106" r="4" fill="'.$W.'" '.$o.'/>'
    .'<rect x="100" y="88" width="62" height="36" rx="7" fill="#FFF1D0" '.$o.'/><circle cx="130" cy="104" r="9" fill="#C98E62"/><path d="M118 124c2-8 6-12 12-12s10 4 12 12z" fill="'.$B.'"/>'
    .'<rect x="172" y="88" width="56" height="36" rx="7" fill="#FFF1D0" '.$o.'/><circle cx="202" cy="102" r="9" fill="'.$K.'"/><path d="M192 99a10 9 0 0 1 20 0z" fill="'.$P.'"/><path d="M190 124c2-8 6-12 12-12s10 4 12 12z" fill="#5B8DEF"/>'
    .'<rect x="90" y="126" width="150" height="6" fill="'.$B.'"/><rect x="250" y="126" width="18" height="8" rx="2" fill="'.$W.'" '.$o.'/>'
    .'<g><circle cx="122" cy="142" r="15" fill="'.$P.'"/><circle cx="122" cy="142" r="7" fill="#D9D2DC"/><g class="spin"><path d="M122 135v14M115 142h14" stroke="'.$P.'" stroke-width="2"/></g></g>'
    .'<g><circle cx="258" cy="142" r="13" fill="'.$P.'"/><circle cx="258" cy="142" r="6" fill="#D9D2DC"/><g class="spin"><path d="M258 136v12M252 142h12" stroke="'.$P.'" stroke-width="2"/></g></g>'
    .'</g>';
  case 'parcel': $o = 'stroke="'.$P.'" stroke-width="2.5" stroke-linejoin="round"';
    return '<rect x="0" y="152" width="320" height="28" fill="#F3DFA0"/>'
    .'<g class="bob"><rect x="36" y="20" width="90" height="148" rx="16" fill="'.$P.'"/><rect x="43" y="32" width="76" height="124" rx="9" fill="'.$W.'"/>'
    .'<path d="M50 60h62M50 84h62M50 108h62M66 36v116M96 36v116" stroke="#EDE6E9" stroke-width="2"/>'
    .'<path d="M58 132C70 112 96 120 88 96S102 66 104 54" fill="none" stroke="'.$B.'" stroke-width="3.5" stroke-linecap="round" stroke-dasharray="6 6" class="dash"/>'
    .'<circle cx="58" cy="132" r="5" fill="'.$P.'"/><path d="M104 56c-8-9-12-14-12-20a12 12 0 0 1 24 0c0 6-4 11-12 20z" fill="'.$B.'"/><circle cx="104" cy="36" r="4.5" fill="'.$W.'"/>'
    .'<rect x="50" y="138" width="62" height="14" rx="7" fill="#FFE0EC"/><text x="81" y="148.5" text-anchor="middle" font-family="Lexend,sans-serif" font-size="8.5" font-weight="800" fill="'.$B.'">CODE 4821</text></g>'
    .'<ellipse cx="218" cy="156" rx="78" ry="6" fill="#000" opacity=".1"/>'
    .'<path d="M162 98l20-20h86l-20 20z" fill="#F0BE7A" '.$o.'/><path d="M248 98l20-20v60l-20 20z" fill="#C98640" '.$o.'/><rect x="162" y="98" width="86" height="58" fill="#E2A35B" '.$o.'/>'
    .'<path d="M198 98l20-20h14l-20 20z" fill="#F7D79F"/><rect x="198" y="98" width="14" height="58" fill="#F7D79F"/>'
    .'<rect x="170" y="126" width="22" height="20" rx="2" fill="'.$W.'" stroke="'.$P.'" stroke-width="1.5"/><path d="M174 132h14M174 137h10M174 142h12" stroke="#BBAEB6" stroke-width="1.5"/>'
    .'<path d="M226 108v12M222 112l4-4 4 4M236 108v12M232 112l4-4 4 4" stroke="'.$P.'" stroke-width="1.6" fill="none" stroke-linecap="round"/>'
    .'<g class="pop"><circle cx="276" cy="56" r="15" fill="#25A55F"/><path d="M269 56l5 5 9-10" stroke="'.$W.'" stroke-width="3" fill="none" stroke-linecap="round" stroke-linejoin="round"/></g>';
  case 'car': $o = 'stroke="'.$P.'" stroke-width="2.5" stroke-linejoin="round"';
    return '<circle class="sun" cx="56" cy="38" r="15" fill="'.$S.'"/><path d="M0 120q60-40 130-14t110-16 80 10v40H0z" fill="#B9E3C9"/>'
    .'<rect x="26" y="96" width="6" height="30" fill="#8A5A2B"/><circle cx="29" cy="92" r="16" fill="#25A55F"/><rect x="286" y="92" width="6" height="34" fill="#8A5A2B"/><circle cx="289" cy="86" r="18" fill="#2F8F5B"/>'
    .'<rect x="0" y="140" width="320" height="40" fill="#8FCBA6"/><path d="M0 164h320" stroke="'.$W.'" stroke-width="4" stroke-dasharray="20 14"/>'
    .'<ellipse cx="166" cy="156" rx="118" ry="6" fill="#000" opacity=".12"/>'
    .'<g class="drive">'
    .'<rect x="112" y="46" width="104" height="6" rx="3" fill="'.$P.'"/><path d="M124 52v6M204 52v6" stroke="'.$P.'" stroke-width="3"/>'
    .'<path d="M52 136v-32q0-8 8-10l26-4 20-28q4-6 12-6h104q8 0 12 6l18 28 18 4q8 2 8 10v32z" fill="'.$W.'" '.$o.'/>'
    .'<path d="M108 66h44v24H92z" fill="#BFD8F5" '.$o.'/><rect x="158" y="66" width="44" height="24" fill="#BFD8F5" '.$o.'/><path d="M208 66h14l18 24h-32z" fill="#BFD8F5" '.$o.'/>'
    .'<path d="M114 70l-8 14M166 70l-6 12M214 70l-4 8" stroke="'.$W.'" stroke-width="3" stroke-linecap="round"/>'
    .'<rect x="52" y="110" width="226" height="8" fill="'.$B.'"/><path d="M155 92v42M205 92v42" stroke="'.$P.'" stroke-width="1.5"/><path d="M190 100h8M242 100h8" stroke="'.$P.'" stroke-width="2.5" stroke-linecap="round"/>'
    .'<rect x="266" y="98" width="12" height="9" rx="3" fill="'.$S.'" '.$o.'/><rect x="52" y="98" width="7" height="11" rx="2" fill="'.$B.'"/>'
    .'<rect x="268" y="120" width="16" height="14" rx="4" fill="'.$P.'"/><rect x="44" y="120" width="12" height="14" rx="4" fill="'.$P.'"/>'
    .'<g><circle cx="98" cy="138" r="18" fill="'.$P.'"/><circle cx="98" cy="138" r="8.5" fill="#D9D2DC"/><g class="spin"><path d="M98 130v16M90 138h16" stroke="'.$P.'" stroke-width="2.2"/></g></g>'
    .'<g><circle cx="232" cy="138" r="18" fill="'.$P.'"/><circle cx="232" cy="138" r="8.5" fill="#D9D2DC"/><g class="spin"><path d="M232 130v16M224 138h16" stroke="'.$P.'" stroke-width="2.2"/></g></g>'
    .'</g>';
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
$scripts = [['माकिट','Hindi'], ['মাকিট','Bangla'], ['மாகிட்','Tamil'], ['మాకిట్','Telugu'], ['ಮಾಕಿಟ್','Kannada'], ['മാകിറ്റ്','Malayalam'], ['માકિટ','Gujarati'], ['ਮਾਕਿਟ','Punjabi'], ['ମାକିଟ୍','Odia'], ['ماکٹ','Urdu'], ['Maakit','English']];
$words = is_hi() ? ['अब घर तक।', 'एक ऐप में।', 'दुकान के दाम पर।'] : ['delivered.', 'in one app.', 'at shop prices.'];
// App store buttons — owner (1 Nov): "coming soon" mat dikhao. Isliye ye
// button TABHI dikhte hain jab config.php me asli link ho:
//   define('APP_PLAY_URL', 'https://play.google.com/store/apps/details?id=...');
//   define('APP_STORE_URL', 'https://apps.apple.com/in/app/...');
// Link nahi to button nahi — jhootha "available" kabhi nahi.
$play_url  = defined('APP_PLAY_URL') ? APP_PLAY_URL : '';
$apple_url = defined('APP_STORE_URL') ? APP_STORE_URL : '';
$has_app   = ($play_url !== '' || $apple_url !== '');
$stores = function ($cls = '') use ($play_url, $apple_url) {
  if ($play_url === '' && $apple_url === '') return '';
  $out = '<div class="stores ' . $cls . '">';
  if ($play_url !== '') $out .= '<a class="store" href="' . h($play_url) . '"><svg width="24" height="24" viewBox="0 0 24 24" aria-hidden="true"><path d="M6 3.5l13 8.5-13 8.5z" fill="currentColor"/></svg><span><small>' . h(t('GET IT ON', 'यहाँ से पाइए')) . '</small><b>Google Play</b></span></a>';
  if ($apple_url !== '') $out .= '<a class="store" href="' . h($apple_url) . '"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><rect x="6" y="2.5" width="12" height="19" rx="3"/><path d="M11 18.5h2"/></svg><span><small>' . h(t('Download on the', 'यहाँ से डाउनलोड')) . '</small><b>App Store</b></span></a>';
  return $out . '</div>';
};
// ---------- Maakit logo (owner, 1 Nov 2026: abhi ke liye ek saaf logo) ----------
// Magenta thaila (shopping bag) jiske andar safed "M", aur kesariya bindu.
// Yahi assets/logo/maakit-mark.svg aur maakit-logo.svg me bhi hai.
function mk_mark($size = 40, $ring = '#FFFFFF') {
  return '<svg class="mk-mark" width="' . $size . '" height="' . $size . '" viewBox="0 0 64 64" aria-hidden="true">'
    . '<path d="M23 19v-4a9 9 0 0 1 18 0v4" fill="none" stroke="#D6246E" stroke-width="5" stroke-linecap="round"/>'
    . '<rect x="6" y="17" width="52" height="43" rx="14" fill="#D6246E"/>'
    . '<path d="M19 49V31l13 11 13-11v18" fill="none" stroke="#fff" stroke-width="6" stroke-linecap="round" stroke-linejoin="round"/>'
    . '<circle cx="51" cy="19" r="8" fill="#FFB020" stroke="' . $ring . '" stroke-width="3"/></svg>';
}
function mk_logo($size = 44, $ring = '#1C0D1F') {
  return mk_mark($size, $ring) . '<span class="word">Maakit</span>';
}
// Service tasveer: assets/home/<naam>.webp|.jpg|.png ho to wahi (owner AI se
// banwa kar bhejega), warna inline SVG.
function mk_pic($key, $alt = '') {
  foreach (['webp', 'jpg', 'png'] as $ext) {
    $f = '/assets/home/' . $key . '.' . $ext;
    if (is_file(__DIR__ . $f)) return '<img src="' . h($f) . '?v=' . filemtime(__DIR__ . $f) . '" alt="' . h($alt) . '" loading="lazy" decoding="async">';
  }
  return '<svg viewBox="0 0 320 180" aria-hidden="true">' . mk_art($key === 'town' ? 'ride' : $key) . '</svg>';
}
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
<link rel="icon" type="image/svg+xml" href="/assets/logo/maakit-mark.svg">
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
#mk-intro{position:fixed;inset:0;z-index:60;background:#24092A;color:#fff;display:flex;align-items:center;justify-content:center;flex-direction:column;overflow:hidden;transition:transform .8s cubic-bezier(.7,0,.3,1),opacity .8s}
#mk-intro[hidden]{display:none}
#mk-intro.out{transform:translateY(-100%);opacity:.6}
#mk-intro .aur span{position:absolute;border-radius:50%;filter:blur(70px);opacity:.8;animation:aur 7s ease-in-out infinite alternate}
#mk-intro .aur .b1{width:560px;height:560px;left:-160px;top:-180px;background:#D6246E}
#mk-intro .aur .b2{width:460px;height:460px;right:-140px;bottom:-160px;background:#FFB020;opacity:.55;animation-duration:9s}
#mk-intro .aur .b3{width:420px;height:420px;left:52%;top:-120px;background:#7B3FE4;opacity:.6;animation-duration:8s}
#mk-intro .aur .b4{width:340px;height:340px;left:4%;bottom:-140px;background:#FF7AB6;opacity:.55;animation-duration:10s}
@keyframes aur{to{transform:translate(60px,40px) scale(1.18)}}
#mk-intro .glass{position:relative;display:flex;flex-direction:column;align-items:center;gap:6px;min-width:min(580px,88vw);padding:34px 44px 30px;border-radius:36px;background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.24);backdrop-filter:blur(22px) saturate(140%);-webkit-backdrop-filter:blur(22px) saturate(140%);box-shadow:0 40px 90px rgba(0,0,0,.35),inset 0 1px 0 rgba(255,255,255,.25)}
#mk-intro .nm{font-size:clamp(46px,11vw,100px);font-weight:800;line-height:1.25;min-height:1.3em;text-align:center;text-shadow:0 8px 40px rgba(214,36,110,.6)}
#mk-intro .nm.swap{animation:swapIn .22s ease-out}
@keyframes swapIn{from{opacity:0;filter:blur(10px);transform:translateY(12px) scale(.95)}to{opacity:1;filter:blur(0);transform:none}}
#mk-intro .prog{width:160px;height:4px;border-radius:2px;background:rgba(255,255,255,.18);overflow:hidden;margin-top:12px}
#mk-intro .prog i{display:block;height:100%;width:0;background:linear-gradient(90deg,#FFB020,#FF7AB6);animation:prog 3.2s linear forwards}
@keyframes prog{to{width:100%}}
#mk-intro svg.word{width:min(500px,78vw);height:auto;display:none}
#mk-intro .lg{display:none}#mk-intro.final .lg{display:block;animation:swapIn .5s ease-out}
#mk-intro.final .nm,#mk-intro.final .prog{display:none}
#mk-intro.final svg.word{margin-top:-14px}#mk-intro.final svg.word{display:block}
#mk-intro .draw{font-family:var(--hand);font-weight:700;font-size:168px;fill:#fff;stroke:#fff;stroke-width:2.5;stroke-dasharray:900;stroke-dashoffset:900;fill-opacity:0}
#mk-intro.final .draw{animation:mkDraw 1.5s ease-out forwards}
#mk-intro .tag{opacity:0;font-size:clamp(16px,3.6vw,20px);color:#FFE3EE;transition:opacity .5s;text-align:center}
#mk-intro.final .tag{opacity:1}
#mk-intro button{position:absolute;bottom:28px;background:rgba(255,255,255,.12);color:#fff;border:1px solid rgba(255,255,255,.35);border-radius:999px;min-height:44px;padding:8px 22px;font:inherit;font-weight:600;cursor:pointer;backdrop-filter:blur(8px)}
@keyframes mkDraw{0%{stroke-dashoffset:900;fill-opacity:0}70%{stroke-dashoffset:0;fill-opacity:0}100%{stroke-dashoffset:0;fill-opacity:1}}
/* ---- header + hero (dark) ---- */
.dark{background:var(--plum);color:#fff;position:relative;overflow:hidden}
.dark .glow{position:absolute;width:900px;height:900px;left:50%;top:-380px;transform:translateX(-50%);background:radial-gradient(closest-side,rgba(214,36,110,.55),rgba(214,36,110,0));pointer-events:none}
.bar{position:relative;background:var(--brand-d);color:#fff;text-align:center;font-size:14px;padding:9px 16px}
.bar b{color:var(--saffron)}
header.top{position:relative}
header.top .wrap{display:flex;align-items:center;gap:12px;flex-wrap:wrap;padding-top:16px;padding-bottom:16px}
.logo{display:flex;align-items:center;gap:10px;color:#fff;text-decoration:none;margin-right:auto}
.logo .word{font-family:'Lexend',sans-serif;font-size:28px;font-weight:800;letter-spacing:-.5px;line-height:1}
.mk-mark{flex-shrink:0;display:block}
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
.stores{display:flex;flex-wrap:wrap;gap:10px}
.hero .stores{justify-content:center;margin-top:16px}
.store{display:inline-flex;align-items:center;gap:10px;min-height:54px;padding:8px 18px 8px 14px;border-radius:14px;background:#0B0710;color:#fff;border:1px solid rgba(255,255,255,.22);text-align:left;line-height:1.15}
.store small{display:block;font-size:11px;opacity:.75}
.store b{display:block;font-size:17px;font-weight:700}
/* ---- marquee ---- */
.cats2{padding:40px 0 6px;overflow:hidden;-webkit-mask-image:linear-gradient(90deg,transparent,#000 7%,#000 93%,transparent);mask-image:linear-gradient(90deg,transparent,#000 7%,#000 93%,transparent)}
.cats2 .row{display:flex;width:max-content;gap:14px;margin-bottom:14px;animation:marq 60s linear infinite}
.cats2 .row.rev{animation-direction:reverse;animation-duration:70s}
.cats2:hover .row{animation-play-state:paused}
.cat{display:inline-flex;align-items:center;gap:12px;background:#fff;border-radius:999px;padding:8px 22px 8px 8px;box-shadow:0 8px 24px rgba(34,20,31,.07);border:1px solid #F4E6DF;font-weight:600;font-size:16px;white-space:nowrap;color:var(--ink)}
.cat em{width:46px;height:46px;border-radius:50%;display:flex;align-items:center;justify-content:center}
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
.intro-art svg,.intro-art img{width:100%;height:100%;display:block;object-fit:cover}
.svc-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(270px,1fr));gap:22px}
.svc{border-radius:var(--r);overflow:hidden;background:#fff;border:1px solid var(--line);transition:transform .3s,box-shadow .3s;display:flex;flex-direction:column}
.svc:hover{transform:translateY(-6px);box-shadow:0 22px 44px rgba(34,20,31,.12)}
.svc .art{position:relative;aspect-ratio:16/9}
.svc .art svg,.svc .art img{width:100%;height:100%;display:block;object-fit:cover}
.svc .badge{position:absolute;left:14px;top:14px;white-space:nowrap;padding:6px 14px;border-radius:999px;font-size:12px;font-weight:800;letter-spacing:.08em;text-transform:uppercase;box-shadow:0 6px 16px rgba(0,0,0,.18)}
:lang(hi) .svc .badge{letter-spacing:0;font-size:13px}
.b-soon{background:var(--saffron);color:#3A2400}.b-pilot{background:#5B8DEF;color:#fff}
.svc .body{padding:22px 22px 24px;display:flex;flex-direction:column;gap:8px;flex:1}
.svc .body.c-peach,.svc .body.c-mint,.svc .body.c-butter,.svc .body.c-sky,.svc .body.c-lilac,.svc .body.c-pink{color:inherit}
.svc h3{margin:0;font-size:21px;font-weight:800;color:var(--ink);display:flex;align-items:flex-start;gap:10px;line-height:1.35}
.svc h3 .dot{width:10px;height:10px;border-radius:50%;background:var(--green);flex-shrink:0;margin-top:.5em}
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
.swing{transform-box:fill-box;transform-origin:top center;animation:swing 2.4s ease-in-out infinite}
.pop{transform-box:fill-box;transform-origin:center;animation:pop 2.4s ease-in-out infinite}
.dash{animation:dash 1.2s linear infinite}
@keyframes swing{0%,100%{transform:rotate(-4deg)}50%{transform:rotate(4deg)}}
@keyframes pop{0%,100%{transform:scale(1)}50%{transform:scale(1.12)}}
@keyframes dash{to{stroke-dashoffset:-24}}
@keyframes spin{to{transform:rotate(360deg)}}
@keyframes drive{0%,100%{transform:translateX(-6px)}50%{transform:translateX(8px)}}
@keyframes bob{0%,100%{transform:translateY(0)}50%{transform:translateY(-6px)}}
@keyframes tw{0%,100%{opacity:1}50%{opacity:.35}}
@keyframes flag{0%,100%{transform:skewY(0)}50%{transform:skewY(-8deg)}}
/* how */

.steps{list-style:none;margin:0;padding:0;display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:22px}
.steps li{position:relative;overflow:hidden;border-radius:32px;padding:30px 26px 28px;background:#fff;box-shadow:0 14px 40px rgba(34,20,31,.08);transition:transform .3s,box-shadow .3s}
.steps li:hover{transform:translateY(-6px);box-shadow:0 24px 50px rgba(34,20,31,.12)}
.steps li .big{position:absolute;right:18px;top:6px;font-size:84px;font-weight:800;line-height:1;color:var(--brand);opacity:.07}
.steps .ic{width:68px;height:68px;border-radius:50%;display:flex;align-items:center;justify-content:center;margin-bottom:16px;box-shadow:0 0 0 8px rgba(255,255,255,.9),0 0 0 9px #F4E6DF}
.steps small{display:inline-block;font-size:12px;font-weight:800;letter-spacing:.1em;text-transform:uppercase;color:var(--brand);background:var(--brand-s);padding:5px 12px;border-radius:999px}
:lang(hi) .steps small{letter-spacing:0;font-size:13px}
.steps h3{margin:12px 0 6px;font-size:20px;font-weight:800}
.steps p{margin:0;color:var(--muted);font-size:15px}
.how{background:var(--cream)}
.deal{margin-top:40px;position:relative;overflow:hidden;border-radius:40px;background:var(--brand);color:#fff;padding:clamp(28px,5vw,56px);display:flex;flex-wrap:wrap;align-items:center;gap:36px}
.deal::before{content:"";position:absolute;inset:0;background-image:radial-gradient(rgba(255,255,255,.14) 1.5px,transparent 1.5px);background-size:22px 22px;-webkit-mask-image:linear-gradient(120deg,#000,transparent 70%);mask-image:linear-gradient(120deg,#000,transparent 70%)}
.deal>*{position:relative}
.deal .txt{flex:1 1 380px}
.deal .eyebrow{color:#FFE3EE}.deal .eyebrow::after{background:#FFE3EE}
.deal h2{margin:10px 0 0;font-size:clamp(32px,5vw,52px);line-height:1.1;font-weight:800}
.deal h2 em{font-style:normal;color:var(--saffron)}
.deal p{margin:16px 0 0;color:#FFE3EE;font-size:17px;max-width:520px}
.deal .btn{margin-top:24px}
.bill{flex:0 1 360px;margin:24px auto 0;background:#fff;color:var(--ink);border-radius:26px;padding:26px 26px 22px;transform:rotate(-3deg);box-shadow:0 30px 60px rgba(60,0,30,.35);position:relative}
.bill h4{margin:0 0 4px;font-size:15px;letter-spacing:.12em;text-transform:uppercase;color:var(--muted)}
:lang(hi) .bill h4{letter-spacing:0}
.bill .ln2{display:flex;justify-content:space-between;gap:12px;padding:12px 0;border-bottom:1.5px dashed #EADAD2;font-size:15px}
.bill .ln2 b{white-space:nowrap}
.bill .ln2 .z{color:var(--green)}
.bill .stamp{position:absolute;right:-22px;top:-34px;width:108px;height:108px;border-radius:50%;border:3px solid var(--saffron);background:var(--plum);color:var(--saffron);display:flex;align-items:center;justify-content:center;text-align:center;font-weight:800;font-size:13px;line-height:1.2;transform:rotate(14deg);box-shadow:0 10px 26px rgba(0,0,0,.25)}
/* apps — teen jude phone (v2: asli phone jaisa size, saaf screen) */
.apps{background:#fff;border-radius:44px;margin:0 12px}
.flow{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:30px;align-items:start;position:relative}
.flow .link{position:absolute;top:300px;height:3px;background:repeating-linear-gradient(90deg,var(--brand) 0 10px,transparent 10px 20px);background-size:40px 3px;animation:flowmv 1s linear infinite;opacity:.7}
.flow .l1{left:calc(33.33% - 34px);width:68px}.flow .l2{left:calc(66.66% - 34px);width:68px}
.flow .link::after{content:"";position:absolute;right:-7px;top:-5px;border-left:10px solid var(--brand);border-top:6.5px solid transparent;border-bottom:6.5px solid transparent}
@keyframes flowmv{to{background-position:40px 0}}
.lane{display:flex;flex-direction:column;align-items:center;text-align:center;gap:10px}
.ph{width:min(286px,80vw);aspect-ratio:9/19;border-radius:46px;background:#120A14;padding:10px;box-shadow:0 40px 70px rgba(34,20,31,.22),inset 0 0 0 2px #3A2A3E;position:relative}
.ph::after{content:"";position:absolute;right:-3px;top:110px;width:3px;height:56px;border-radius:2px;background:#3A2A3E}
.scr{position:relative;width:100%;height:100%;border-radius:37px;overflow:hidden;background:#F7F1F4;display:flex;flex-direction:column;text-align:left;color:var(--ink);font-size:13px;line-height:1.35}
.scr .island{position:absolute;top:9px;left:50%;width:84px;height:24px;margin-left:-42px;border-radius:14px;background:#120A14;z-index:3}
.scr .sb{display:flex;justify-content:space-between;align-items:center;padding:12px 22px 0;height:36px;font-size:12px;font-weight:700;position:relative;z-index:2}
.scr .sb .ic{display:flex;gap:5px;align-items:center}
.scr .hd{display:flex;align-items:center;gap:9px;padding:10px 16px 14px}
.scr .hd .lgt{background:#fff;border-radius:12px;padding:3px;display:flex;box-shadow:0 2px 6px rgba(0,0,0,.15)}
.scr .hd .t{display:flex;flex-direction:column;line-height:1.2}
.scr .hd .t b{font-size:15px;font-weight:800}
.scr .hd .t span{font-size:11.5px;opacity:.8}
.scr .hd .r{margin-left:auto;width:32px;height:32px;border-radius:50%;background:rgba(255,255,255,.18);display:flex;align-items:center;justify-content:center}
.scr .body{flex:1;padding:0 12px;display:flex;flex-direction:column;gap:10px;overflow:hidden}
.scr .cd{background:#fff;border-radius:18px;padding:13px 14px;box-shadow:0 4px 14px rgba(34,20,31,.06)}
.scr .cd h5{margin:0;font-size:15px;font-weight:800}
.scr .mut{color:var(--muted);font-size:12px}
.scr .bar2{height:6px;border-radius:3px;background:#F1E4EA;margin-top:10px;overflow:hidden}
.scr .bar2 i{display:block;height:100%;width:68%;border-radius:3px;background:var(--brand);animation:grow 3s ease-in-out infinite alternate}
@keyframes grow{from{width:55%}to{width:78%}}
.stg{list-style:none;margin:10px 0 0;padding:0;display:flex;flex-direction:column;gap:9px}
.stg li{display:flex;align-items:center;gap:9px;font-size:12.5px;color:var(--muted)}
.stg i{width:16px;height:16px;border-radius:50%;border:2px solid #E5D6DE;flex-shrink:0;display:flex;align-items:center;justify-content:center}
.stg .d{color:var(--ink)}.stg .d i{background:var(--green);border-color:var(--green)}
.stg .d i::after{content:"";width:6px;height:3px;border-left:2px solid #fff;border-bottom:2px solid #fff;transform:rotate(-45deg) translate(1px,-1px)}
.stg .n{color:var(--ink);font-weight:700}.stg .n i{background:var(--saffron);border-color:var(--saffron);animation:ping 1.4s infinite}
@keyframes ping{0%{box-shadow:0 0 0 0 rgba(255,176,32,.6)}100%{box-shadow:0 0 0 9px rgba(255,176,32,0)}}
.mmap{position:relative;border-radius:18px;overflow:hidden;background:#E3EEDF;min-height:92px}
.mmap::before{content:"";position:absolute;inset:0;background:linear-gradient(90deg,transparent 47%,#fff 47%,#fff 53%,transparent 53%),linear-gradient(0deg,transparent 46%,#fff 46%,#fff 54%,transparent 54%);background-size:70px 70px;opacity:.7}
.mmap .rt{position:absolute;left:22px;right:30px;top:50%;height:4px;margin-top:-2px;border-radius:2px;background:repeating-linear-gradient(90deg,var(--brand) 0 8px,transparent 8px 13px)}
.mmap .home{position:absolute;right:16px;top:50%;margin-top:-17px;width:22px;height:22px;border-radius:50% 50% 50% 0;transform:rotate(-45deg);background:var(--brand);box-shadow:0 3px 8px rgba(0,0,0,.2)}
.mmap .shop{position:absolute;left:12px;top:50%;margin-top:-9px;width:18px;height:18px;border-radius:6px;background:var(--plum);border:2px solid #fff}
.mmap .rider{position:absolute;top:50%;margin-top:-13px;left:24px;width:26px;height:26px;border-radius:50%;background:var(--saffron);border:3px solid #fff;box-shadow:0 4px 10px rgba(0,0,0,.2);animation:ride 4s ease-in-out infinite alternate}
@keyframes ride{to{left:calc(100% - 66px)}}
.who{display:flex;align-items:center;gap:10px}
.who .av{width:38px;height:38px;border-radius:50%;background:var(--peach);color:var(--peach-t);display:flex;align-items:center;justify-content:center;font-weight:800;flex-shrink:0}
.who .call{margin-left:auto;width:34px;height:34px;border-radius:50%;background:#E6F6EC;color:var(--green);display:flex;align-items:center;justify-content:center}
.code{display:flex;justify-content:space-between;align-items:center;background:var(--brand-s);border-radius:14px;padding:10px 14px;font-size:12px;font-weight:600;color:var(--brand-d)}
.code b{font-size:17px;letter-spacing:4px}
.alert{display:flex;align-items:center;gap:10px;background:var(--brand);color:#fff;border-radius:18px;padding:13px 14px}
.alert .bl{width:36px;height:36px;border-radius:50%;background:rgba(255,255,255,.2);display:flex;align-items:center;justify-content:center}
.alert .bl svg{animation:ring 1.3s ease-in-out infinite;transform-origin:50% 0}
@keyframes ring{0%,60%,100%{transform:rotate(0)}10%,30%{transform:rotate(16deg)}20%,40%{transform:rotate(-16deg)}}
.alert b{display:block;font-size:15px}.alert span{font-size:12px;opacity:.9}
.items{list-style:none;margin:8px 0 0;padding:0;display:flex;flex-direction:column}
.items li{display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px dashed #EEDFE6;font-size:12.5px}
.items li:last-child{border:0}
.items li b{font-weight:700}
.bigbtn{display:block;text-align:center;border-radius:14px;padding:12px;font-weight:800;font-size:14px}
.bigbtn.g{background:var(--green);color:#fff}.bigbtn.s{background:var(--saffron);color:#3A2400}.bigbtn.l{color:var(--muted);padding:6px;font-weight:600;font-size:12.5px}
.tog{display:flex;align-items:center;justify-content:space-between;font-size:12.5px;font-weight:700}
.tog i{width:40px;height:22px;border-radius:11px;background:var(--green);position:relative}
.tog i::after{content:"";position:absolute;right:3px;top:3px;width:16px;height:16px;border-radius:50%;background:#fff}
.offer{display:flex;align-items:center;gap:12px}
.ring{width:50px;height:50px;flex-shrink:0;position:relative}
.ring svg{width:50px;height:50px;transform:rotate(-90deg)}
.ring circle.f{stroke-dasharray:138.2;animation:count 20s linear infinite}
@keyframes count{from{stroke-dashoffset:0}to{stroke-dashoffset:138.2}}
.ring b{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:800}
.nav{display:flex;justify-content:space-around;padding:10px 6px 16px;background:#fff;border-top:1px solid #F1E4EA;font-size:10.5px;color:var(--muted)}
.nav span{display:flex;flex-direction:column;align-items:center;gap:2px}
.nav .on{color:var(--brand);font-weight:700}
.lane small{font-weight:800;font-size:12px;letter-spacing:.1em;text-transform:uppercase;color:var(--brand);margin-top:22px}
:lang(hi) .lane small{letter-spacing:0;font-size:14px}
.lane h3{margin:0;font-size:22px;font-weight:800}
.lane p{margin:0;color:var(--muted);max-width:300px}
.lane .chips{display:flex;flex-wrap:wrap;justify-content:center;gap:6px;margin:4px 0 6px}
.lane .chips span{font-size:13px;font-weight:600;padding:5px 11px;border-radius:999px}
@media (max-width:980px){.flow{grid-template-columns:1fr;gap:60px}.flow .link{display:none}}
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
/* where — naksha */
.where2{display:grid;grid-template-columns:minmax(0,1.15fr) minmax(0,1fr);gap:24px;align-items:stretch}
.mapbox{position:relative;border-radius:36px;overflow:hidden;background:var(--plum);min-height:360px}
.mapbox svg{width:100%;height:100%;display:block}
.mapbox .note{position:absolute;left:18px;bottom:14px;font-size:12px;color:rgba(255,255,255,.55)}
.pulse{transform-box:fill-box;transform-origin:center;animation:pulse 2s ease-out infinite}
.pulse.p2{animation-delay:.7s}.pulse.p3{animation-delay:1.4s}
@keyframes pulse{0%{transform:scale(.6);opacity:.9}100%{transform:scale(2.6);opacity:0}}
.areas{display:flex;flex-direction:column;gap:18px}
.area{border-radius:30px;padding:26px 26px 24px;position:relative;overflow:hidden}
.area.now{background:var(--brand);color:#fff;box-shadow:0 24px 50px rgba(214,36,110,.3)}
.area.next{background:#fff;border:2px dashed #E9B9CC}
.area .live{display:inline-flex;align-items:center;gap:8px;font-size:12px;font-weight:800;letter-spacing:.12em;text-transform:uppercase;padding:6px 12px;border-radius:999px;background:rgba(255,255,255,.18)}
:lang(hi) .area .live{letter-spacing:0;font-size:13px}
.area .live i{width:9px;height:9px;border-radius:50%;background:#7CFFB2;animation:ping2 1.4s infinite}
@keyframes ping2{0%{box-shadow:0 0 0 0 rgba(124,255,178,.7)}100%{box-shadow:0 0 0 10px rgba(124,255,178,0)}}
.area.next .live{background:var(--butter);color:var(--butter-t)}.area.next .live i{background:var(--saffron);animation:none}
.area h3{margin:14px 0 4px;font-size:clamp(24px,3.2vw,32px);font-weight:800;line-height:1.15}
.area.next h3{color:var(--ink)}
.area p{margin:0;font-size:15px}.area.now p{color:#FFE3EE}.area.next p{color:var(--muted)}
.area .tags{display:flex;flex-wrap:wrap;gap:8px;margin-top:14px}
.area .tags span{padding:7px 14px;border-radius:999px;font-weight:700;font-size:14px}
.area.now .tags span{background:#fff;color:var(--brand-d)}
.area.next .tags span{background:var(--cream);color:var(--ink);border:1px solid var(--line)}
.area .btn{margin-top:16px}
@media (max-width:860px){.where2{grid-template-columns:1fr}.mapbox{min-height:300px}}
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
  <div class="aur" aria-hidden="true"><span class="b1"></span><span class="b2"></span><span class="b3"></span><span class="b4"></span></div>
  <div class="glass">
    <div class="nm" aria-hidden="true">Maakit</div>
    <span class="lg"><?= mk_mark(76, '#7B2E6B') ?></span>
    <svg class="word" viewBox="0 0 640 220" role="img" aria-label="Maakit"><text class="draw" x="320" y="160" text-anchor="middle">Maakit</text></svg>
    <div class="tag"><?= $T('Your town’s own app', 'आपके शहर का अपना ऐप') ?></div>
    <div class="prog" aria-hidden="true"><i></i></div>
  </div>
  <button type="button" id="mk-skip"><?= $T('Skip', 'छोड़िए') ?></button>
</div>

<div class="dark">
  <span class="glow" aria-hidden="true"></span>
  <div class="bar"><?= $T('Maakit is growing across Bhadohi and the Varanasi region —', 'Maakit भदोही और वाराणसी क्षेत्र में बढ़ रहा है —') ?> <b><?= $T('shops, Sarathis and investors welcome', 'दुकानदार, सारथी और निवेशक जुड़ सकते हैं') ?></b></div>
  <header class="top">
    <div class="wrap">
      <a class="logo" href="/" aria-label="Maakit"><?= mk_logo(46) ?></a>
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
      <?= $stores() ?>
      <div class="pills">
        <span><?= $ico('M5 12l5 5L20 7', 16) ?><?= $T('Shop’s own price', 'दुकान का ही दाम') ?></span>
        <span><?= $ico('M5 12l5 5L20 7', 16) ?><?= $T('Cash or UPI to the shop', 'नकद या UPI सीधे दुकान को') ?></span>
        <span><?= $ico('M5 12l5 5L20 7', 16) ?><?= $T('Live order tracking', 'ऑर्डर की पूरी जानकारी') ?></span>
      </div>
      <a class="scroll" href="#services"><?= $T('Scroll down', 'नीचे देखिए') ?><?= $ico('M6 9l6 6 6-6', 18) ?></a>
    </div>
  </section>
</div>

<div class="cats2" aria-label="<?= $T('Shop categories', 'दुकानों की श्रेणियाँ') ?>">
  <?php foreach ([0, 1] as $r): $list = $r ? array_reverse($cats, true) : $cats; ?>
  <div class="row<?= $r ? ' rev' : '' ?>" <?= $r ? 'aria-hidden="true"' : '' ?>>
    <?php for ($k = 0; $k < 2; $k++) foreach ($list as $i => $c): ?><span class="cat" <?= ($k || $r) ? 'aria-hidden="true"' : '' ?>><em class="<?= $tints[$i % 6] ?>"><?= $ico($ci[$c[0]], 22) ?></em><?= $T($c[1], $c[2]) ?></span><?php endforeach; ?>
  </div>
  <?php endforeach; ?>
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
      <div class="intro-art c-pink"><?= mk_pic('town', t('Maakit in your town', 'आपके शहर में Maakit')) ?></div>
    </div>
    <div class="svc-grid">
      <?php foreach ($services as $s): ?>
      <article class="svc rv">
        <div class="art <?= $s[1] ?>">
          <?= mk_pic($s[0], t($s[3], $s[4])) ?>
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
      <li class="rv" style="transition-delay:<?= $i * 0.08 ?>s"><span class="big" aria-hidden="true">0<?= $i + 1 ?></span>
        <span class="ic <?= $tints[$i] ?>"><?= $ico($si[$st[0]], 26) ?></span>
        <small><?= $T('Step', 'कदम') ?> <?= $i + 1 ?></small>
        <h3><?= $T($st[1], $st[2]) ?></h3>
        <p><?= $T($st[3], $st[4]) ?></p>
      </li>
      <?php endforeach; ?>
    </ol>
    <div class="deal rv">
      <div class="txt">
        <span class="eyebrow"><?= $T('Our promise', 'हमारा वादा') ?></span>
        <h2><?= $T('Shop price.', 'दुकान का दाम।') ?><br><em><?= $T('Not a rupee more.', 'एक रुपया ज़्यादा नहीं।') ?></em></h2>
        <p><?= $T('The price of the goods belongs to the shop. Maakit adds nothing to it and charges only a delivery fee, which you see before you order.', 'सामान का दाम दुकान का है। Maakit उसमें कुछ नहीं जोड़ता, सिर्फ़ डिलीवरी शुल्क लेता है — जो ऑर्डर से पहले बता दिया जाता है।') ?></p>
        <a class="btn btn-white" href="https://wa.me/<?= h($wa) ?>"><?= $ico('M4 20l1.5-4A8 8 0 1 1 8 19z', 20) ?><?= $T('Order on WhatsApp', 'WhatsApp पर ऑर्डर') ?></a>
      </div>
      <div class="bill" aria-label="<?= $T('How your bill works', 'आपका बिल कैसे बनता है') ?>">
        <h4><?= $T('Your bill', 'आपका बिल') ?></h4>
        <div class="ln2"><span><?= $T('Goods', 'सामान') ?></span><b><?= $T('Shop’s own price', 'दुकान का दाम') ?></b></div>
        <div class="ln2"><span><?= $T('Added by Maakit on goods', 'सामान पर Maakit ने जोड़ा') ?></span><b class="z">₹0</b></div>
        <div class="ln2"><span><?= $T('Delivery fee', 'डिलीवरी शुल्क') ?></span><b><?= $T('Told before ordering', 'ऑर्डर से पहले') ?></b></div>
        <div class="ln2" style="border:0"><span><?= $T('Pay', 'भुगतान') ?></span><b><?= $T('Cash / UPI to shop', 'नकद / UPI दुकान को') ?></b></div>
        <span class="stamp"><?= $T('SAME PRICE AS SHOP', 'दुकान जैसा दाम') ?></span>
      </div>
    </div>
  </div>
  </div>
</section>

<section class="s apps" id="apps" aria-labelledby="apps-h">
  <div class="wrap">
    <div class="rv" style="text-align:center;margin-bottom:46px">
      <span class="eyebrow"><?= $T('Three apps. One platform.', 'तीन ऐप। एक मंच।') ?></span>
      <h2 class="big" id="apps-h"><?= $T('Everyone on the same', 'सब एक ही') ?> <em><?= $T('order.', 'ऑर्डर पर।') ?></em></h2>
      <p class="lead" style="margin-left:auto;margin-right:auto"><?= $T('The moment a customer orders, the shop’s phone rings. When the shop packs it, the nearest Sarathi gets the job. Everyone sees every step.', 'ग्राहक ऑर्डर करते ही दुकान का फ़ोन बजता है। दुकान पैक करती है तो सबसे पास के सारथी को काम मिलता है। हर कदम सबको दिखता है।') ?></p>
    </div>
    <div class="flow">
      <span class="link l1" aria-hidden="true"></span><span class="link l2" aria-hidden="true"></span>

      <div class="lane rv">
        <div class="ph" aria-hidden="true"><div class="scr">
          <div style="background:var(--brand);color:#fff"><span class="island"></span><div class="sb"><span>9:41</span><span class="ic"><svg width="16" height="11" viewBox="0 0 16 11" aria-hidden="true"><rect x="0" y="7" width="3" height="4" rx="1" fill="currentColor"/><rect x="4.5" y="5" width="3" height="6" rx="1" fill="currentColor"/><rect x="9" y="2.5" width="3" height="8.5" rx="1" fill="currentColor"/><rect x="13.5" y="0" width="2.5" height="11" rx="1" fill="currentColor"/></svg><svg width="22" height="11" viewBox="0 0 22 11" aria-hidden="true"><rect x=".5" y=".5" width="18" height="10" rx="3" fill="none" stroke="currentColor"/><rect x="2" y="2" width="12" height="7" rx="1.5" fill="currentColor"/><rect x="19.5" y="3.5" width="2" height="4" rx="1" fill="currentColor"/></svg></span></div>
            <div class="hd"><span class="lgt"><?= mk_mark(30, '#FFFFFF') ?></span><span class="t"><b>Maakit</b><span><?= $T('Jagarnathpur ▾', 'जगरनाथपुर ▾') ?></span></span><span class="r"><?= $ico('M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9M10 21h4', 16) ?></span></div>
          </div>
          <div class="body" style="padding-top:12px">
            <div class="cd"><span class="mut"><?= $T('Your order', 'आपका ऑर्डर') ?></span><h5><?= $T('Arriving in 12 min', '12 मिनट में पहुँचेगा') ?></h5><div class="bar2"><i></i></div>
              <ul class="stg"><li class="d"><i></i><?= $T('Shop accepted', 'दुकान ने माना') ?></li><li class="d"><i></i><?= $T('Packed', 'पैक हो गया') ?></li><li class="n"><i></i><?= $T('Sarathi on the way', 'सारथी रास्ते में') ?></li></ul></div>
            <div class="mmap"><span class="shop"></span><span class="rt"></span><span class="home"></span><span class="rider"></span></div>
            <div class="cd who"><span class="av">R</span><span><b style="display:block;font-size:13px"><?= $T('Your Sarathi', 'आपका सारथी') ?></b><span class="mut"><?= $T('Bike · on the way', 'बाइक · रास्ते में') ?></span></span><span class="call"><?= $ico('M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2', 16) ?></span></div>
            <div class="code"><span><?= $T('Delivery code', 'डिलीवरी कोड') ?></span><b>4821</b></div>
          </div>
          <div class="nav"><span class="on"><?= $ico('M3 11l9-7 9 7v9H3z', 18) ?><?= $T('Home', 'होम') ?></span><span><?= $ico('M6 3h12v18l-3-2-3 2-3-2-3 2z', 18) ?><?= $T('Orders', 'ऑर्डर') ?></span><span><?= $ico('M12 12a4 4 0 1 0 0-8a4 4 0 1 0 0 8M4 21c1.5-4 4.5-6 8-6s6.5 2 8 6', 18) ?><?= $T('Account', 'खाता') ?></span></div>
        </div></div>
        <small><?= $T('For customers', 'ग्राहकों के लिए') ?></small>
        <h3><?= $T('Maakit app', 'Maakit ऐप') ?></h3>
        <p><?= $T('Order from any shop, book a ride or a vehicle, and follow every step.', 'किसी भी दुकान से मँगाइए, सवारी या गाड़ी बुक कीजिए, हर कदम देखिए।') ?></p>
        <div class="chips"><span class="c-pink"><?= $T('Hindi & English', 'हिंदी और English') ?></span><span class="c-mint"><?= $T('Cash / UPI', 'नकद / UPI') ?></span><span class="c-sky"><?= $T('Live status', 'लाइव जानकारी') ?></span></div>
        <?php if ($has_app): ?><?= $stores() ?><?php else: ?><a class="btn btn-brand" href="https://wa.me/<?= h($wa) ?>"><?= $T('Order on WhatsApp', 'WhatsApp पर ऑर्डर') ?></a><?php endif; ?>
      </div>

      <div class="lane rv" style="transition-delay:.1s">
        <div class="ph" aria-hidden="true"><div class="scr">
          <div style="background:var(--plum);color:#fff"><span class="island"></span><div class="sb"><span>9:41</span><span class="ic"><svg width="16" height="11" viewBox="0 0 16 11" aria-hidden="true"><rect x="0" y="7" width="3" height="4" rx="1" fill="currentColor"/><rect x="4.5" y="5" width="3" height="6" rx="1" fill="currentColor"/><rect x="9" y="2.5" width="3" height="8.5" rx="1" fill="currentColor"/><rect x="13.5" y="0" width="2.5" height="11" rx="1" fill="currentColor"/></svg><svg width="22" height="11" viewBox="0 0 22 11" aria-hidden="true"><rect x=".5" y=".5" width="18" height="10" rx="3" fill="none" stroke="currentColor"/><rect x="2" y="2" width="12" height="7" rx="1.5" fill="currentColor"/><rect x="19.5" y="3.5" width="2" height="4" rx="1" fill="currentColor"/></svg></span></div>
            <div class="hd"><span class="lgt"><?= mk_mark(30, '#FFFFFF') ?></span><span class="t"><b><?= $T('Maakit Shop', 'Maakit दुकान') ?></b><span><?= $T('Kirana shop', 'किराना दुकान') ?></span></span></div>
          </div>
          <div class="body" style="padding-top:12px">
            <div class="cd tog"><span><?= $T('Shop is open', 'दुकान खुली है') ?></span><i></i></div>
            <div class="alert"><span class="bl"><?= $ico('M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9M10 21h4', 18) ?></span><span><b><?= $T('New order!', 'नया ऑर्डर!') ?></b><span><?= $T('3 items · Jagarnathpur', '3 सामान · जगरनाथपुर') ?></span></span></div>
            <div class="cd"><ul class="items">
              <li><span><?= $T('Wheat atta 1 kg', 'गेहूँ आटा 1 किग्रा') ?></span><b>× 2</b></li>
              <li><span><?= $T('Mustard oil 1 L', 'सरसों तेल 1 ली') ?></span><b>× 1</b></li>
              <li><span><?= $T('Haldi 100 g', 'हल्दी 100 ग्रा') ?></span><b>× 1</b></li>
            </ul></div>
            <span class="bigbtn g"><?= $T('Accept order', 'ऑर्डर मंज़ूर कीजिए') ?></span>
            <span class="bigbtn l"><?= $T('Not available', 'सामान नहीं है') ?></span>
          </div>
          <div class="nav"><span class="on"><?= $ico('M6 3h12v18l-3-2-3 2-3-2-3 2z', 18) ?><?= $T('Orders', 'ऑर्डर') ?></span><span><?= $ico('M3 10l1.5-5h15L21 10M4 10v10h16V10', 18) ?><?= $T('Items', 'सामान') ?></span><span><?= $ico('M4 4h16v16H4zM8 9h8M8 13h8M8 17h5', 18) ?><?= $T('Account', 'हिसाब') ?></span></div>
        </div></div>
        <small><?= $T('For shopkeepers', 'दुकानदारों के लिए') ?></small>
        <h3><?= $T('Maakit for shops', 'Maakit दुकान') ?></h3>
        <p><?= $T('Every order rings on your phone. Accept, pack, and Maakit sends a Sarathi.', 'हर ऑर्डर पर आपका फ़ोन बजता है। मंज़ूर कीजिए, पैक कीजिए — Maakit सारथी भेजता है।') ?></p>
        <div class="chips"><span class="c-butter"><?= $T('Your price', 'आपका दाम') ?></span><span class="c-peach"><?= $T('Your stock', 'आपका स्टॉक') ?></span><span class="c-mint"><?= $T('Daily account', 'रोज़ का हिसाब') ?></span></div>
        <a class="btn btn-saffron" href="/register-business.php"><?= $T('Join as a shop', 'दुकान जोड़िए') ?></a>
      </div>

      <div class="lane rv" style="transition-delay:.2s">
        <div class="ph" aria-hidden="true"><div class="scr">
          <div style="background:var(--plum-2);color:#fff"><span class="island"></span><div class="sb"><span>9:41</span><span class="ic"><svg width="16" height="11" viewBox="0 0 16 11" aria-hidden="true"><rect x="0" y="7" width="3" height="4" rx="1" fill="currentColor"/><rect x="4.5" y="5" width="3" height="6" rx="1" fill="currentColor"/><rect x="9" y="2.5" width="3" height="8.5" rx="1" fill="currentColor"/><rect x="13.5" y="0" width="2.5" height="11" rx="1" fill="currentColor"/></svg><svg width="22" height="11" viewBox="0 0 22 11" aria-hidden="true"><rect x=".5" y=".5" width="18" height="10" rx="3" fill="none" stroke="currentColor"/><rect x="2" y="2" width="12" height="7" rx="1.5" fill="currentColor"/><rect x="19.5" y="3.5" width="2" height="4" rx="1" fill="currentColor"/></svg></span></div>
            <div class="hd"><span class="lgt"><?= mk_mark(30, '#FFFFFF') ?></span><span class="t"><b><?= $T('Sarathi Partner', 'सारथी पार्टनर') ?></b><span><?= $T('Bike', 'बाइक') ?></span></span></div>
          </div>
          <div class="body" style="padding-top:12px">
            <div class="cd tog"><span><?= $T('You are online', 'आप ऑनलाइन हैं') ?></span><i></i></div>
            <div class="mmap" style="min-height:150px"><span class="shop"></span><span class="rt"></span><span class="home"></span><span class="rider"></span></div>
            <div class="cd"><div class="offer"><span class="ring"><svg viewBox="0 0 50 50"><circle cx="25" cy="25" r="22" fill="none" stroke="#F4E6DF" stroke-width="5"/><circle class="f" cx="25" cy="25" r="22" fill="none" stroke="#FFB020" stroke-width="5" stroke-linecap="round"/></svg><b>20s</b></span>
              <span><h5><?= $T('New delivery job', 'नया डिलीवरी काम') ?></h5><span class="mut"><?= $T('Pickup 1.2 km · Kirana shop', 'पिकअप 1.2 किमी · किराना दुकान') ?></span></span></div>
              <span class="bigbtn s" style="margin-top:12px"><?= $T('Accept job', 'काम लीजिए') ?></span></div>
          </div>
          <div class="nav"><span class="on"><?= $ico('M3 17a3 3 0 1 0 6 0a3 3 0 1 0 -6 0M15 17a3 3 0 1 0 6 0a3 3 0 1 0 -6 0M6 17l4-8h5l3 8', 18) ?><?= $T('Jobs', 'काम') ?></span><span><?= $ico('M4 4h16v16H4zM8 9h8M8 13h8', 18) ?><?= $T('Earnings', 'कमाई') ?></span><span><?= $ico('M12 12a4 4 0 1 0 0-8a4 4 0 1 0 0 8M4 21c1.5-4 4.5-6 8-6s6.5 2 8 6', 18) ?><?= $T('Profile', 'प्रोफ़ाइल') ?></span></div>
        </div></div>
        <small><?= $T('For riders & vehicle owners', 'सारथी और गाड़ी वालों के लिए') ?></small>
        <h3><?= $T('Sarathi Partner', 'सारथी पार्टनर') ?></h3>
        <p><?= $T('Jobs near you ring on your phone. Pick up with a code, drop with a code, earn per trip.', 'पास का काम आपके फ़ोन पर बजता है। कोड से उठाइए, कोड से पहुँचाइए, हर सफ़र पर कमाइए।') ?></p>
        <div class="chips"><span class="c-lilac"><?= $T('Aadhaar + PAN', 'आधार + PAN') ?></span><span class="c-sky"><?= $T('Your area', 'आपका इलाक़ा') ?></span><span class="c-butter"><?= $T('Bike to bus', 'बाइक से बस तक') ?></span></div>
        <a class="btn btn-brand" style="background:var(--plum-2);box-shadow:none" href="https://smartsarathi.in/sarathi.php"><?= $T('Join as Sarathi', 'सारथी बनिए') ?></a>
      </div>
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

<section class="s" style="padding-top:30px" aria-labelledby="where-h">
  <div class="wrap">
    <div class="rv" style="margin-bottom:30px">
      <span class="eyebrow"><?= $T('Growing village by village', 'गाँव-दर-गाँव बढ़ते हुए') ?></span>
      <h2 class="big" id="where-h"><?= $T('Where we', 'हम कहाँ') ?> <em><?= $T('serve', 'सेवा देते हैं') ?></em></h2>
    </div>
    <div class="where2">
      <div class="mapbox rv"><svg viewBox="0 0 480 380" role="img" aria-label="<?= $T('Schematic map of the Maakit service area', 'Maakit सेवा क्षेत्र का नक्शा') ?>"><defs><pattern id="mkgrid" width="24" height="24" patternUnits="userSpaceOnUse"><path d="M24 0H0V24" fill="none" stroke="rgba(255,255,255,.05)"/></pattern></defs><rect width="480" height="380" fill="url(#mkgrid)"/><path d="M30 80 L150 44 L250 70 L292 128 L262 214 L180 254 L84 232 L40 166 Z" fill="rgba(214,36,110,.14)" stroke="rgba(255,255,255,.22)" stroke-dasharray="6 6"/><path d="M292 128 L250 70 L370 44 L456 112 L446 232 L366 262 L262 214 Z" fill="rgba(255,176,32,.07)" stroke="rgba(255,255,255,.16)" stroke-dasharray="6 6"/><path d="M0 334 C90 304 150 344 232 304 S362 254 480 294" fill="none" stroke="#4FA3E3" stroke-width="10" stroke-linecap="round" opacity=".5"/><text x="392" y="334" font-family="Lexend,sans-serif" font-size="12" fill="#8FC6F0"><?= $T('Ganga', 'गंगा') ?></text><path d="M160 168 L222 142 L204 214 Z" fill="rgba(124,255,178,.08)" stroke="rgba(124,255,178,.5)" stroke-width="2" stroke-dasharray="5 6" class="dash"/><g><circle cx="104" cy="112" r="11" fill="none" stroke="#FFB020" stroke-width="2.5" stroke-dasharray="4 3"/><circle cx="104" cy="112" r="4.5" fill="#FFB020"/><text x="104" y="144" text-anchor="middle" font-family="Lexend,'Noto Sans Devanagari',sans-serif" font-size="15" font-weight="700" fill="#FFD27A"><?= $T('Bhadohi', 'भदोही') ?></text></g><g><circle cx="396" cy="150" r="11" fill="none" stroke="#FFB020" stroke-width="2.5" stroke-dasharray="4 3"/><circle cx="396" cy="150" r="4.5" fill="#FFB020"/><text x="396" y="182" text-anchor="middle" font-family="Lexend,'Noto Sans Devanagari',sans-serif" font-size="15" font-weight="700" fill="#FFD27A"><?= $T('Varanasi', 'वाराणसी') ?></text></g><g><circle class="pulse " cx="160" cy="168" r="9" fill="#7CFFB2"/><circle cx="160" cy="168" r="9" fill="#25A55F" stroke="#fff" stroke-width="3"/><rect x="174" y="155" width="88" height="26" rx="13" fill="#fff"/><text x="184" y="173" font-family="Lexend,'Noto Sans Devanagari',sans-serif" font-size="13" font-weight="700" fill="#22141F"><?= $T('Kapsethi', 'कपसेठी') ?></text></g><g><circle class="pulse p2" cx="222" cy="142" r="9" fill="#7CFFB2"/><circle cx="222" cy="142" r="9" fill="#25A55F" stroke="#fff" stroke-width="3"/><rect x="236" y="129" width="70" height="26" rx="13" fill="#fff"/><text x="246" y="147" font-family="Lexend,'Noto Sans Devanagari',sans-serif" font-size="13" font-weight="700" fill="#22141F"><?= $T('Chauri', 'चौरी') ?></text></g><g><circle class="pulse p3" cx="204" cy="214" r="9" fill="#7CFFB2"/><circle cx="204" cy="214" r="9" fill="#25A55F" stroke="#fff" stroke-width="3"/><rect x="218" y="201" width="82" height="26" rx="13" fill="#fff"/><text x="228" y="219" font-family="Lexend,'Noto Sans Devanagari',sans-serif" font-size="13" font-weight="700" fill="#22141F"><?= $T('Kachhwa', 'कछवा') ?></text></g></svg><span class="note"><?= $T('Schematic map, not to scale', 'सांकेतिक नक्शा, पैमाने पर नहीं') ?></span></div>
      <div class="areas">
        <div class="area now rv">
          <span class="live"><i></i><?= $T('Serving now', 'अभी सेवा चालू') ?></span>
          <h3><?= $T('Kapsethi, Chauri & Kachhwa', 'कपसेठी, चौरी और कछवा') ?></h3>
          <p><?= $T('and the villages around them.', 'और आस-पास के गाँव।') ?></p>
          <div class="tags"><span><?= $T('Kapsethi', 'कपसेठी') ?></span><span><?= $T('Chauri', 'चौरी') ?></span><span><?= $T('Kachhwa', 'कछवा') ?></span><span>+ <?= $T('nearby villages', 'आस-पास के गाँव') ?></span></div>
        </div>
        <div class="area next rv">
          <span class="live"><i></i><?= $T('Coming next', 'आगे') ?></span>
          <h3><?= $T('More of Bhadohi & Varanasi', 'भदोही और वाराणसी के और इलाक़े') ?></h3>
          <p><?= $T('A new area opens only when it has enough shops and Sarathis.', 'नया इलाक़ा तभी खुलता है जब वहाँ पर्याप्त दुकानें और सारथी हों।') ?></p>
          <a class="btn btn-brand" href="https://wa.me/<?= h($wa) ?>"><?= $ico('M12 21s-6-5.3-6-10a6 6 0 0 1 12 0c0 4.7-6 10-6 10z', 20) ?><?= $T('Want Maakit in your village?', 'अपने गाँव में Maakit चाहिए?') ?></a>
        </div>
      </div>
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
        <a class="logo" href="/" aria-label="Maakit"><?= mk_logo(46) ?></a>
        <span><?= $T('Local shopping, vehicle and service bookings for the towns and villages of Uttar Pradesh.', 'उत्तर प्रदेश के कस्बों और गाँवों के लिए स्थानीय खरीदारी, गाड़ी और सेवा बुकिंग।') ?></span>
        <span>Jagarnathpur, Bhadohi, Uttar Pradesh 221403</span>
        <?= $stores() ?>
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
      if (i < names.length) { nm.textContent = names[i][0]; nm.classList.remove('swap'); void nm.offsetWidth; nm.classList.add('swap'); i++; return; }
      clearInterval(timer); box.classList.add('final'); setTimeout(close, 2300);
    }, 290);
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
