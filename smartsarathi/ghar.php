<?php
// ============================================================
//  स्मार्ट सारथी — ग्राहक ऐप (single page)
//
//  Owner master prompt, 29 Oct 2026: Uber/Rapido jaisa dashboard,
//  booking neeche se khulne wali sheet me (naksha, doori, pakka
//  kiraya, ETA), tracking aur "मेरी बुकिंग" isi panne par —
//  bina page reload. Booking seedhe Sarathi ke kaam (jobs) me
//  banti hai: book-api.php → inc/booking.php → job_create().
// ============================================================
require_once __DIR__ . '/inc/public.php';
require_once __DIR__ . '/inc/booking.php';
header('Cache-Control: private, no-store');
// Maakit ke purane panne bhi kabhi khulein to app ke design me (inc/sr-app-head.php)
if (!headers_sent() && ($_COOKIE['sr_app'] ?? '') !== '1') {
    setcookie('sr_app', '1', ['expires' => time() + 31536000, 'path' => '/', 'secure' => !empty($_SERVER['HTTPS']), 'httponly' => true, 'samesite' => 'Lax']);
}
$acc = sr_account($pdo);
if ($acc && sr_account_role($pdo,$acc)==='partner') redirect(sr_home_path($pdo));
$svcs = sr_services();
$cfg = [
    'api' => u('/book-api.php'), 'login' => u('/customer-login.php'), 'account' => u('/account.php'), 'csrf' => csrf(),
    'loggedIn' => (bool)$acc, 'mobile' => $acc['mobile'] ?? null, 'phone' => SR_PHONE, 'lang' => sr_lang(),
    'services' => array_map(fn($s) => ['name' => $s['name'], 'tag' => $s['tag'], 'kind' => $s['kind']], $svcs),
    'L' => [
        'where' => t('Where to?', 'कहाँ जाना है?'), 'pickup' => t('Pickup', 'पिकअप'), 'drop' => t('Drop', 'ड्रॉप'),
        'myLoc' => t('My location', 'मेरी जगह'), 'search' => t('Search', 'खोजिए'), 'tapMap' => t('Or tap on the map', 'या नक्शे पर दबाइए'),
        'searching' => t('Searching…', 'खोज रहे हैं…'),
        'notHere' => t('Not in the list?', 'सूची में नहीं है?'), 'noResultPin' => t('Not found — set it on the map', 'नहीं मिला — नक़्शे पर पिन लगाइए'),
        'pinIt' => t('Keep the name “%s” and move the map to the exact spot', '“%s” नाम रखिए और नक़्शा खिसकाकर सही जगह पर पिन लगाइए'),
        'pinNow' => t('Move the map so the pin is on the exact spot', 'नक़्शा खिसकाइए ताकि पिन ठीक जगह पर आ जाए'), 'noResult' => t('Not found. Tap on the map instead.', 'नहीं मिला। नक्शे पर दबाइए।'),
        'gpsFail' => t('Could not get your location. Allow location or tap on the map.', 'आपकी जगह नहीं मिली। लोकेशन चालू कीजिए या नक्शे पर दबाइए।'),
        'choose' => t('Choose a ride', 'गाड़ी चुनिए'), 'min' => t('min', 'मिनट'), 'km' => t('km', 'किमी'),
        'now' => t('Now', 'अभी'), 'later' => t('Later', 'बाद में'), 'name' => t('Your name', 'आपका नाम'),
        'self' => t('I will receive it', 'मैं ही लूँगा'), 'other' => t('Someone else receives', 'कोई और लेगा'),
        'rName' => t('Receiver name', 'पाने वाले का नाम'), 'rMob' => t('Receiver mobile', 'पाने वाले का मोबाइल'),
        'items' => t('What is it? (optional)', 'क्या है? (ज़रूरी नहीं)'), 'note' => t('Note for Sarathi (optional)', 'सारथी के लिए बात (ज़रूरी नहीं)'),
        'seats' => t('Seats', 'सीट'), 'book' => t('Book', 'बुक करें'), 'loginBook' => t('Log in & book', 'लॉगिन करके बुक करें'),
        'booking' => t('Booking…', 'बुक हो रहा है…'), 'fare' => t('Fare', 'किराया'), 'pay' => t('Pay the Sarathi by cash or UPI at the end', 'आख़िर में सारथी को नकद या UPI से दीजिए'),
        'st_new' => t('Finding a Sarathi…', 'सारथी ढूँढ रहे हैं…'), 'st_assigned' => t('Sarathi is coming', 'सारथी आ रहा है'),
        'st_picked' => t('On the way', 'रास्ते में'), 'st_delivered' => t('Completed', 'पूरा हो गया'), 'st_cancelled' => t('Cancelled', 'रद्द'),
        'startOtp' => t('Start OTP — tell the Sarathi', 'शुरू करने का OTP — सारथी को बताइए'), 'endOtp' => t('End OTP', 'पूरा करने का OTP'),
        'call' => t('Call', 'कॉल'), 'share' => t('Share trip', 'सफ़र शेयर'), 'cancel' => t('Cancel booking', 'बुकिंग रद्द करें'),
        'cancelQ' => t('Cancel this booking?', 'यह बुकिंग रद्द करें?'), 'mine' => t('My bookings', 'मेरी बुकिंग'),
        'none' => t('No bookings yet.', 'अभी कोई बुकिंग नहीं।'), 'officeAssign' => t('Our office will confirm and assign a Sarathi.', 'हमारा दफ़्तर पक्का करके सारथी भेजेगा।'), 'finding' => t('Finding the nearest Sarathi for you…', 'आपके पास का सारथी ढूँढ रहे हैं…'),
        'err' => t('Something went wrong. Please call us.', 'कुछ गड़बड़ हुई। फ़ोन कीजिए।'), 'liveOff' => t('Live location appears when the Sarathi shares it.', 'सारथी जगह साझा करेगा तो यहाँ दिखेगी।'),
        'listen' => t('Listening… say the place', 'सुन रहे हैं… जगह बोलिए'), 'noMic' => t('Voice search does not work in this browser. Please type.', 'इस ब्राउज़र में बोलकर खोज नहीं चलती। लिखकर खोजिए।'),
        'here' => t('You are here', 'आप यहाँ हैं'), 'fav' => t('Save place', 'जगह सेव करें'), 'loginOnly' => t('Log in to see bookings', 'बुकिंग देखने के लिए लॉगिन कीजिए'),
        'dragMap' => t('Move the map to set', 'नक्शा खिसकाकर लगाइए'), 'setPick' => t('Pickup here', 'पिकअप यहाँ'), 'setDrop' => t('Drop here', 'ड्रॉप यहाँ'),
        'clue' => t('Help the Sarathi find you', 'सारथी को पहचान बताइए'),
        'clues' => [t('Near the temple', 'मंदिर के पास'), t('Near the school', 'स्कूल के पास'), t('At the crossing', 'चौराहे पर'), t('On the main road', 'मेन रोड पर'), t('Inside the lane — please call', 'गली के अंदर — फ़ोन कर लीजिए')],
        'waTry' => t('Booking did not go through? Call the office', 'बुकिंग नहीं हुई? दफ़्तर को फ़ोन कीजिए'),
        'waHead' => t('Smart Sarathi booking', 'स्मार्ट सारथी बुकिंग'), 'when' => t('Date & time', 'तारीख़ और समय'), 'back' => t('Back', 'वापस'), 'setBoth' => t('Set pickup and drop to see fares', 'किराया देखने के लिए दोनों जगह चुनिए'),
    ],
];
$grid = [
    ['bike',   t('Bike', 'बाइक'),        '<circle cx="11" cy="32" r="7"/><circle cx="37" cy="32" r="7"/><path d="M11 32l9-12h9l8 12M24 20l-3-6h-5M29 20h6"/>'],
    ['auto',   t('Auto', 'ऑटो'),         '<path d="M8 32V20c0-5 4-9 9-9h12l7 9h4v12H8z"/><path d="M20 11v21M8 24h28"/><circle cx="14" cy="34" r="4"/><circle cx="34" cy="34" r="4"/>'],
    ['parcel', t('Parcel', 'पार्सल'),    '<path d="M8 15l16-8 16 8v18l-16 8-16-8z"/><path d="M8 15l16 8 16-8M24 23v18"/>'],
    ['cab',    t('Cab / Bolero', 'कैब / बोलेरो'), '<path d="M6 28l4-10h28l4 10v8H6z"/><circle cx="14" cy="36" r="3.5"/><circle cx="34" cy="36" r="3.5"/><path d="M6 28h36M14 18l2-5h16l2 5"/>'],
    ['loader', t('Loader', 'लोडर'),      '<path d="M4 12h22v20H4zM26 20h8l8 8v4H26z"/><circle cx="12" cy="35" r="4"/><circle cx="34" cy="35" r="4"/>'],
    ['saath',  t('Saath Safar', 'साथ सफ़र'), '<circle cx="16" cy="16" r="6"/><circle cx="34" cy="18" r="5"/><path d="M4 40c0-7 5-12 12-12s12 5 12 12M28 31c2-1 4-2 6-2 6 0 10 4 10 10"/>'],
];
?><!doctype html>
<html lang="<?= h(sr_lang()) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="theme-color" content="#0B1F3A">
<title><?= h(t('Smart Sarathi — rides, parcels, vehicles', 'स्मार्ट सारथी — सवारी, पार्सल, गाड़ी')) ?></title>
<meta name="description" content="<?= h(t('Book bike, auto, e-rickshaw, cab, parcel and loader in Bhadohi. Fare shown before you book.', 'भदोही में बाइक, ऑटो, ई-रिक्शा, कैब, पार्सल और लोडर बुक कीजिए। बुक करने से पहले किराया।')) ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Devanagari:wght@400;600;700;800&family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.css">
<?php if (!function_exists('sr_app_head')) require_once __DIR__ . '/inc/ui.php'; sr_app_head(); ?>
<link rel="stylesheet" href="<?= h(u('/assets/customer.css')) ?>?v=<?= (int)@filemtime(__DIR__ . '/assets/customer.css') ?>">
</head>
<body>
<div class="ca" id="app">
<?php if (!sr_lang_chosen()): ?>
<!-- ====== पहली बार: भाषा चुनिए ====== -->
<form class="ob ob-lang" method="get" action="">
  <div class="ob-hero" aria-hidden="true"><span class="b1">आ</span><span class="b2">A</span><i class="s1">✦</i><i class="s2">✦</i></div>
  <div class="ob-sheet">
    <h1>भाषा चुनिए · Select language</h1>
    <div class="ob-langs">
      <label><input type="radio" name="lang" value="hi" checked><span><b>हिन्दी</b><small>नमस्ते! सवारी और पार्सल बुक कीजिए</small></span></label>
      <label><input type="radio" name="lang" value="en"><span><b>English</b><small>Hello! Book rides and parcels</small></span></label>
    </div>
    <button type="submit" class="ca-cta">आगे बढ़ें · Continue</button>
  </div>
</form>
<?php endif; ?>
<!-- ====== पहली बार: 10 सेकंड का विज्ञापन जैसा स्वागत (owner, 30 Oct 2026) — सिर्फ़ ग्राहक, फिर ऐप अपने आप ====== -->
<div class="ob ob-welcome ad" id="welcome" hidden>
  <svg width="0" height="0" style="position:absolute" aria-hidden="true"><defs>
    <symbol id="v-bike" viewBox="0 0 120 80"><circle cx="24" cy="58" r="16" fill="none" stroke="currentColor" stroke-width="7"/><circle cx="96" cy="58" r="16" fill="none" stroke="currentColor" stroke-width="7"/><path d="M24 58l22-26h28l22 26M46 32l-8-14H26M74 32h14" fill="none" stroke="currentColor" stroke-width="7" stroke-linecap="round" stroke-linejoin="round"/><circle cx="56" cy="10" r="8" fill="#FFC83D"/><path d="M56 18l-4 18h16" fill="none" stroke="#FFC83D" stroke-width="8" stroke-linecap="round"/></symbol>
    <symbol id="v-auto" viewBox="0 0 120 80"><path d="M14 60V34c0-12 10-22 22-22h34l26 24v24z" fill="#FFC83D" stroke="currentColor" stroke-width="5" stroke-linejoin="round"/><path d="M14 36h82M50 12v24" stroke="currentColor" stroke-width="5"/><rect x="58" y="18" width="22" height="14" rx="3" fill="#BFE8FF"/><circle cx="30" cy="62" r="11" fill="#0B1F3A" stroke="currentColor" stroke-width="5"/><circle cx="90" cy="62" r="11" fill="#0B1F3A" stroke="currentColor" stroke-width="5"/></symbol>
    <symbol id="v-erick" viewBox="0 0 120 80"><path d="M10 58V22h70l22 22v14z" fill="#10C98F" stroke="currentColor" stroke-width="5" stroke-linejoin="round"/><path d="M8 22h76" stroke="currentColor" stroke-width="7" stroke-linecap="round"/><path d="M58 30l-8 12h10l-8 12" fill="none" stroke="#FFC83D" stroke-width="5" stroke-linejoin="round"/><circle cx="28" cy="62" r="11" fill="#0B1F3A" stroke="currentColor" stroke-width="5"/><circle cx="92" cy="62" r="11" fill="#0B1F3A" stroke="currentColor" stroke-width="5"/></symbol>
    <symbol id="v-car" viewBox="0 0 120 80"><path d="M8 56V40l14-20h62l20 20h8v16z" fill="#FFFFFF" stroke="currentColor" stroke-width="5" stroke-linejoin="round"/><path d="M28 24h22v16H18zM58 24h22l14 16H58z" fill="#BFE8FF"/><circle cx="32" cy="60" r="11" fill="#0B1F3A" stroke="currentColor" stroke-width="5"/><circle cx="90" cy="60" r="11" fill="#0B1F3A" stroke="currentColor" stroke-width="5"/></symbol>
    <symbol id="v-box" viewBox="0 0 120 80"><path d="M30 26l30-14 30 14v34L60 74 30 60z" fill="#E8A75A" stroke="currentColor" stroke-width="5" stroke-linejoin="round"/><path d="M30 26l30 14 30-14M60 40v34" stroke="currentColor" stroke-width="5" fill="none"/><path d="M44 19l30 14" stroke="#FFF3D6" stroke-width="6"/></symbol>
    <symbol id="v-truck" viewBox="0 0 120 80"><rect x="6" y="18" width="66" height="38" rx="4" fill="#FFC83D" stroke="currentColor" stroke-width="5"/><path d="M72 30h24l16 16v10H72z" fill="#FFFFFF" stroke="currentColor" stroke-width="5" stroke-linejoin="round"/><rect x="80" y="34" width="14" height="10" rx="2" fill="#BFE8FF"/><circle cx="26" cy="62" r="10" fill="#0B1F3A" stroke="currentColor" stroke-width="5"/><circle cx="92" cy="62" r="10" fill="#0B1F3A" stroke="currentColor" stroke-width="5"/></symbol>
    <symbol id="v-care" viewBox="0 0 120 80"><path d="M60 72S20 50 14 30A22 22 0 0 1 60 18a22 22 0 0 1 46 12C100 50 60 72 60 72z" fill="#FF6B6B" stroke="currentColor" stroke-width="5"/><path d="M60 30v24M48 42h24" stroke="#fff" stroke-width="8" stroke-linecap="round"/></symbol>
    <symbol id="v-bag" viewBox="0 0 120 80"><path d="M30 28h60l-6 46H36z" fill="#10C98F" stroke="currentColor" stroke-width="5" stroke-linejoin="round"/><path d="M46 28v-6a14 14 0 0 1 28 0v6" fill="none" stroke="currentColor" stroke-width="5"/><path d="M50 46h20M60 36v20" stroke="#fff" stroke-width="6" stroke-linecap="round"/></symbol>
    <symbol id="v-seat" viewBox="0 0 120 80"><circle cx="40" cy="22" r="12" fill="#BFE8FF" stroke="currentColor" stroke-width="5"/><circle cx="80" cy="22" r="12" fill="#FFC83D" stroke="currentColor" stroke-width="5"/><path d="M18 72c0-16 10-28 22-28s22 12 22 28M58 72c0-16 10-28 22-28s22 12 22 28" fill="none" stroke="currentColor" stroke-width="6" stroke-linecap="round"/></symbol>
  </defs></svg>
  <div class="ad-bars" aria-hidden="true"><i></i><i></i><i></i><i></i></div>
  <button type="button" class="ob-skip" data-seen data-close><?= h(t('Skip', 'छोड़ें')) ?></button>

  <section class="ad-s s1 on">
    <div class="ad-logo"><svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="#0B1F3A" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M13 2 4 14h7l-1 8 9-12h-7z"/></svg></div>
    <p class="ad-k"><?= h(t('Hello! I am', 'नमस्ते! मैं हूँ')) ?></p>
    <h1 class="ad-h">Smart Sarathi</h1>
    <p class="ad-t"><?= h(t('Your very own Sarathi', 'आपका अपना सारथी')) ?></p>
    <div class="ad-road"><svg class="ad-ride" viewBox="0 0 120 80"><use href="#v-bike"/></svg></div>
  </section>

  <section class="ad-s s2">
    <p class="ad-k"><?= h(t('Need to go somewhere?', 'कहीं जाना है?')) ?></p>
    <h1 class="ad-h"><?= h(t('You choose, I take you', 'आप चुनिए, मैं ले चलूँगा')) ?></h1>
    <div class="ad-grid">
      <?php foreach ([['v-bike', t('Bike', 'बाइक')], ['v-auto', t('Auto', 'ऑटो')], ['v-erick', t('E-rickshaw', 'ई-रिक्शा')], ['v-car', t('Cab / Bolero', 'कैब / बोलेरो')]] as $k => [$v, $n]): ?>
        <div class="ad-card" style="--d:<?= $k * 0.25 ?>s"><svg viewBox="0 0 120 80"><use href="#<?= $v ?>"/></svg><b><?= h($n) ?></b></div>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="ad-s s3">
    <p class="ad-k"><?= h(t('Things to send or bring?', 'सामान लाना या भेजना है?')) ?></p>
    <h1 class="ad-h"><?= h(t('I will bring it', 'मैं ले आऊँगा')) ?></h1>
    <div class="ad-grid">
      <?php foreach ([['v-box', t('Parcel', 'पार्सल')], ['v-truck', t('Loader / goods', 'लोडर / सामान')], ['v-bag', t('Medicine, ration', 'दवाई, राशन')], ['v-care', t('Hospital Saathi', 'अस्पताल साथी')], ['v-seat', t('Saath Safar', 'साथ सफ़र')], ['v-car', t('Outstation', 'शहर से बाहर')]] as $k => [$v, $n]): ?>
        <div class="ad-card sm" style="--d:<?= $k * 0.18 ?>s"><svg viewBox="0 0 120 80"><use href="#<?= $v ?>"/></svg><b><?= h($n) ?></b></div>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="ad-s s4">
    <p class="ad-k"><?= h(t('Smart Sarathi, Bhadohi', 'स्मार्ट सारथी, भदोही')) ?></p><h1 class="ad-h"><?= h(t('My promise to you', 'आपसे मेरा वादा')) ?></h1>
    <ul class="ad-ticks">
      <li style="--d:0s">₹ <?= h(t('Fixed fare before you book', 'बुकिंग से पहले पक्का किराया')) ?></li>
      <li style="--d:.3s">🔑 <?= h(t('Trip starts only with your OTP', 'आपके OTP से ही सफ़र शुरू')) ?></li>
      <li style="--d:.6s">📍 <?= h(t('With you on the map, every moment', 'हर पल नक़्शे पर आपके साथ')) ?></li>
    </ul>
    <button type="button" class="ca-cta ad-go" data-seen data-close><?= h(t('Let’s go →', 'चलिए, शुरू करें →')) ?></button>
  </section>
</div>

<!-- ================= HOME ================= -->
<section class="ca-view" data-view="home">
  <header class="ca-top">
    <div class="ca-row">
      <a class="ca-logo" href="<?= h(u('/ghar.php')) ?>"><span class="ca-mark"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#0B1F3A" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M13 2 4 14h7l-1 8 9-12h-7z"/></svg></span><span>Smart Sarathi<small><svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true"><path d="M12 21s-7-6.2-7-11a7 7 0 0 1 14 0c0 4.8-7 11-7 11z"/><circle cx="12" cy="10" r="2.5"/></svg> <?= h(t('Bhadohi', 'भदोही')) ?></small></span></a>
      <div class="ca-row" style="gap:8px">
        <nav class="ca-lang" aria-label="<?= h(t('Language', 'भाषा')) ?>"><a href="?lang=hi"<?= sr_lang() === 'hi' ? ' aria-current="true"' : '' ?>>हिं</a><a href="?lang=en"<?= sr_lang() === 'en' ? ' aria-current="true"' : '' ?>>EN</a></nav>
        <a class="ca-sos" href="tel:<?= h(SR_PHONE) ?>" aria-label="<?= h(t('Emergency call', 'आपातकाल कॉल')) ?>">SOS</a>
      </div>
    </div>
    <div class="ca-here" id="here">
      <div class="ca-here-map" id="hmap" aria-hidden="true"></div>
      <div class="ca-here-ask" id="here-ask">
        <svg width="46" height="46" viewBox="0 0 48 48" fill="none" aria-hidden="true"><circle cx="24" cy="24" r="22" fill="#10C98F" opacity=".18"/><path d="M24 40s-11-9.6-11-17a11 11 0 0 1 22 0c0 7.4-11 17-11 17z" fill="#10C98F"/><circle cx="24" cy="23" r="4" fill="#0B1F3A"/></svg>
        <span><b><?= h(t('Turn on your location', 'अपनी जगह चालू कीजिए')) ?></b><small><?= h(t('Pickup will be set automatically. Used only while you book.', 'पिकअप अपने आप लगेगा। सिर्फ़ बुकिंग के समय इस्तेमाल होगी।')) ?></small></span>
        <button type="button" id="here-on"><?= h(t('Turn on', 'चालू करें')) ?></button>
      </div>
      <p class="ca-here-pill" id="here-pill" hidden><span class="dot"></span><span id="here-addr"></span></p>
    </div>
  </header>

  <div class="ca-search">
    <button type="button" class="ca-search-go" data-open="bike">
      <span class="dot" aria-hidden="true"></span>
      <span><b><?= h(t('Where to?', 'कहाँ जाना है?')) ?></b><small><?= h(t('Select pickup & drop', 'पिकअप और ड्रॉप चुनिए')) ?></small></span>
    </button>
    <button type="button" class="ca-mic" id="mic" aria-label="<?= h(t('Speak the place', 'जगह बोलकर बताइए')) ?>"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#0B1F3A" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><rect x="9" y="3" width="6" height="11" rx="3"/><path d="M5 11a7 7 0 0 0 14 0M12 18v3"/></svg><span><?= h(t('Speak', 'बोलें')) ?></span></button>
  </div>
  <p class="ca-listen" id="listen" role="status" aria-live="polite" hidden></p>

  <button type="button" class="ca-install" id="install" hidden>
    <span class="ca-install-ic" aria-hidden="true"><img src="<?= h(u('/assets/icon-192.png')) ?>" alt="" width="40" height="40"></span>
    <span class="ca-install-t"><b><?= h(t('Get the Smart Sarathi app', 'स्मार्ट सारथी ऐप फ़ोन में डालिए')) ?></b><small><?= h(t('One tap · no Play Store · opens like an app', 'एक टैप · Play Store की ज़रूरत नहीं · ऐप जैसा खुलेगा')) ?></small></span>
    <span class="ca-install-go"><?= h(t('Install', 'इंस्टॉल')) ?></span>
  </button>
  <div class="ca-ihelp" id="install-help" hidden>
    <b><?= h(t('How to install', 'ऐसे इंस्टॉल कीजिए')) ?></b>
    <ol data-os="android">
      <li><?= h(t('If this opened inside WhatsApp, tap ⋮ at the top → "Open in Chrome".', 'अगर WhatsApp के अंदर खुला है तो ऊपर ⋮ दबाइए → "Chrome में खोलें"।')) ?></li>
      <li><?= h(t('In Chrome tap ⋮ (top right) → "Install app" or "Add to Home screen".', 'Chrome में ऊपर दाएँ ⋮ दबाइए → "ऐप इंस्टॉल करें" या "होम स्क्रीन पर जोड़ें"।')) ?></li>
      <li><?= h(t('The Smart Sarathi icon will appear on your phone.', 'फ़ोन पर स्मार्ट सारथी का आइकन आ जाएगा।')) ?></li>
    </ol>
    <ol data-os="ios" hidden>
      <li><?= h(t('Open this page in Safari.', 'यह पेज Safari में खोलिए।')) ?></li>
      <li><?= h(t('Tap Share (square with arrow) → "Add to Home Screen".', 'शेयर (तीर वाला डिब्बा) दबाइए → "Add to Home Screen"।')) ?></li>
    </ol>
    <button type="button" class="ca-ihelp-x" id="install-x"><?= h(t('Got it', 'समझ गया')) ?></button>
  </div>
  <section class="ca-sec ca-recent" id="recent" aria-labelledby="h-recent" hidden>
    <h2 id="h-recent"><?= h(t('Recent places', 'पिछली जगहें')) ?></h2>
    <ul id="recentlist"></ul>
  </section>

  <section class="ca-sec" aria-labelledby="h-svc">
    <h2 id="h-svc"><?= h(t('Services', 'सेवाएँ')) ?></h2>
    <div class="ca-grid">
      <?php foreach ($grid as [$k, $label, $svg]): ?>
      <button type="button" class="ca-tile" data-open="<?= h($k) ?>"><svg width="38" height="38" viewBox="0 0 48 48" fill="none" stroke="#0B1F3A" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><?= $svg ?></svg><span><?= h($label) ?></span></button>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="ca-sec" aria-labelledby="h-care">
    <h2 id="h-care"><?= h(t('Care services', 'केयर सेवाएँ')) ?></h2>
    <button type="button" class="ca-care" data-open="care"><span class="ic" aria-hidden="true">+</span><span><b><?= h(t('Hospital Saathi', 'अस्पताल साथी')) ?></b><small><?= h(t('Vehicle + a companion for the whole hospital visit', 'गाड़ी + अस्पताल में पूरे समय साथ रहने वाला साथी')) ?></small></span></button>
    <button type="button" class="ca-care" data-open="seva"><span class="ic" aria-hidden="true">♥</span><span><b><?= h(t('Door se Seva', 'दूर से सेवा')) ?></b><small><?= h(t('Away from home? Send medicine, gas, ration to your family', 'घर से दूर हैं? घर वालों को दवाई, गैस, राशन भेजिए')) ?></small></span></button>
  </section>

  <button type="button" class="ca-howto" data-guide-open="cust-book"><span aria-hidden="true">❓</span><span><b><?= h(t('First time? How to book', 'पहली बार? ऐसे बुक कीजिए')) ?></b><small><?= h(t('4 easy steps, with voice', '4 आसान कदम, आवाज़ के साथ')) ?></small></span><em>›</em></button>
  <p class="ca-foot"><a href="<?= h(u('/privacy.php')) ?>"><?= h(t('Privacy policy', 'प्राइवेसी पॉलिसी')) ?></a></p>
</section>

<!-- ================= BOOK ================= -->
<section class="ca-view" data-view="book" hidden>
  <div class="ca-mapwrap">
    <div class="ca-map" id="map" aria-label="<?= h(t('Map', 'नक्शा')) ?>"></div>
    <!-- Uber/Rapido jaisa beech ka pin: naksha khiskao, jagah lag jaye (customer.js pinShow) -->
    <div class="ca-cpin p" id="cpin" hidden aria-hidden="true"><span class="ca-cpin-l" id="cpin-l"></span><span class="ca-cpin-h"></span><span class="ca-cpin-s"></span></div>
  </div>
  <button type="button" class="ca-back" data-go="home" aria-label="<?= h(t('Back', 'वापस')) ?>"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#0B1F3A" stroke-width="2.6" stroke-linecap="round" aria-hidden="true"><path d="M15 5l-7 7 7 7"/></svg></button>
  <div class="ca-sheet" id="sheet">
    <div class="ca-grab" aria-hidden="true"></div>
    <h2 id="b-title"></h2>
    <div class="ca-places">
      <div class="ca-place" data-field="pick"><span class="pin p" aria-hidden="true"></span>
        <label for="pick-q" class="sr"><?= h(t('Pickup', 'पिकअप')) ?></label>
        <input id="pick-q" autocomplete="off" placeholder="<?= h(t('Pickup — search or tap map', 'पिकअप — खोजिए या नक्शे पर दबाइए')) ?>">
        <button type="button" class="mini" data-gps><?= h(t('My location', 'मेरी जगह')) ?></button></div>
      <div class="ca-place" data-field="drop"><span class="pin d" aria-hidden="true"></span>
        <label for="drop-q" class="sr"><?= h(t('Drop', 'ड्रॉप')) ?></label>
        <input id="drop-q" autocomplete="off" placeholder="<?= h(t('Drop — search or tap map', 'ड्रॉप — खोजिए या नक्शे पर दबाइए')) ?>">
        <button type="button" class="mini" data-search="drop"><?= h(t('Search', 'खोजिए')) ?></button></div>
      <button type="button" class="ca-swap" id="swap" aria-label="<?= h(t('Swap pickup and drop', 'पिकअप और ड्रॉप बदलिए')) ?>"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#0B1F3A" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7 4v16M3 8l4-4 4 4M17 20V4M13 16l4 4 4-4"/></svg></button>
      <ul class="ca-results" id="results" hidden></ul>
      <p class="ca-hint" id="hint"></p>
    </div>
    <div id="opts" hidden>
      <p class="ca-route" id="route"></p>
      <div class="ca-opts" id="optlist" role="radiogroup"></div>
      <div class="ca-form" id="extra"></div>
      <p class="ca-err" id="berr" role="alert"></p>
      <button type="button" class="ca-cta" id="bookbtn"></button>
      <p class="ca-fine" id="payline"></p>
    </div>
  </div>
</section>

<!-- ================= TRACK ================= -->
<section class="ca-view" data-view="track" hidden>
  <div class="ca-map" id="tmap"></div>
  <button type="button" class="ca-back" data-go="home" aria-label="<?= h(t('Back', 'वापस')) ?>"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#0B1F3A" stroke-width="2.6" stroke-linecap="round" aria-hidden="true"><path d="M15 5l-7 7 7 7"/></svg></button>
  <div class="ca-sheet" id="tsheet"></div>
</section>

<!-- ================= MINE ================= -->
<section class="ca-view" data-view="mine" hidden>
  <header class="ca-top ca-top-s"><h1><?= h(t('My bookings', 'मेरी बुकिंग')) ?></h1></header>
  <div class="ca-list" id="minelist"></div>
</section>

<nav class="ca-nav" id="nav" aria-label="<?= h(t('Main', 'मुख्य')) ?>"><div>
  <button type="button" data-go="home" aria-current="page"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linejoin="round" aria-hidden="true"><path d="M3 11l9-7 9 7v9H3z"/></svg><?= h(t('Home', 'होम')) ?></button>
  <button type="button" data-go="mine"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="4" y="4" width="16" height="16" rx="3"/><path d="M8 9h8M8 13h8M8 17h5"/></svg><?= h(t('My bookings', 'मेरी बुकिंग')) ?></button>
  <a href="<?= h(u($acc ? '/account.php' : '/')) ?>"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4.4 3.6-8 8-8s8 3.6 8 8"/></svg><?= h($acc ? t('Account', 'खाता') : t('Login', 'लॉगिन')) ?></a>
</div></nav>
</div>
<p class="ca-osm">© <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a></p>
<script id="ca-config" type="application/json"><?= json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<?php require_once __DIR__.'/inc/companion.php'; sr_companion(); ?>
<?php require_once __DIR__.'/inc/tour.php';
sr_guide('cust-book', t('How to book — 4 steps', 'ऐसे बुक कीजिए — 4 कदम'), [
  ['📍', t('Where to?', 'कहाँ जाना है?'), t('Tap “Where to?”, type the place or say it with 🎤. Not in the list? Keep the name and move the map pin to the spot.', '“कहाँ जाना है?” दबाइए, जगह लिखिए या 🎤 से बोलिए। सूची में न हो तो नाम रखिए और नक़्शे का पिन सही जगह लगाइए।')],
  ['🛵', t('Choose vehicle and fare', 'गाड़ी और किराया चुनिए'), t('Every vehicle shows its fixed fare and time. Choose one and tap Book.', 'हर गाड़ी का पक्का किराया और समय दिखता है। एक चुनिए और “बुक करें” दबाइए।')],
  ['👤', t('Your Sarathi comes', 'सारथी आपके पास आएगा'), t('You will see the Sarathi’s name, vehicle and number plate. Call or chat from the app.', 'सारथी का नाम, गाड़ी और नंबर प्लेट दिखेगी। ऐप से फ़ोन या चैट कीजिए।')],
  ['🔑', t('Give OTP, pay the shown fare', 'OTP बताइए, दिखा किराया दीजिए'), t('Tell your 4-digit OTP only when the right Sarathi arrives. Pay only the fare shown in the app.', 'सही सारथी आने पर ही अपना 4 अंक का OTP बताइए। सिर्फ़ ऐप में दिखा किराया दीजिए।')],
], 'mint', (bool)$acc); ?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.js"></script>
<script src="<?= h(u('/assets/customer.js')) ?>?v=<?= (int)@filemtime(__DIR__ . '/assets/customer.js') ?>"></script>
</body>
</html>
