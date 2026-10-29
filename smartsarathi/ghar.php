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
$svcs = sr_services();
$cfg = [
    'api' => u('/book-api.php'), 'login' => u('/'), 'account' => u('/account.php'), 'csrf' => csrf(),
    'loggedIn' => (bool)$acc, 'mobile' => $acc['mobile'] ?? null, 'phone' => SR_PHONE, 'lang' => sr_lang(),
    'services' => array_map(fn($s) => ['name' => $s['name'], 'tag' => $s['tag'], 'kind' => $s['kind']], $svcs),
    'L' => [
        'where' => t('Where to?', 'कहाँ जाना है?'), 'pickup' => t('Pickup', 'पिकअप'), 'drop' => t('Drop', 'ड्रॉप'),
        'myLoc' => t('My location', 'मेरी जगह'), 'search' => t('Search', 'खोजिए'), 'tapMap' => t('Or tap on the map', 'या नक्शे पर दबाइए'),
        'searching' => t('Searching…', 'खोज रहे हैं…'), 'noResult' => t('Not found. Tap on the map instead.', 'नहीं मिला। नक्शे पर दबाइए।'),
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
        'none' => t('No bookings yet.', 'अभी कोई बुकिंग नहीं।'), 'officeAssign' => t('Our office will confirm and assign a Sarathi.', 'हमारा दफ़्तर पक्का करके सारथी भेजेगा।'),
        'err' => t('Something went wrong. Please call us.', 'कुछ गड़बड़ हुई। फ़ोन कीजिए।'), 'liveOff' => t('Live location appears when the Sarathi shares it.', 'सारथी जगह साझा करेगा तो यहाँ दिखेगी।'),
        'loginOnly' => t('Log in to see bookings', 'बुकिंग देखने के लिए लॉगिन कीजिए'), 'when' => t('Date & time', 'तारीख़ और समय'), 'back' => t('Back', 'वापस'), 'setBoth' => t('Set pickup and drop to see fares', 'किराया देखने के लिए दोनों जगह चुनिए'),
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
    <div class="ca-promo">
      <b><?= h(t('Fare first. Then book.', 'पहले किराया, फिर बुकिंग।')) ?></b>
      <span><?= h(t('Bike, auto, cab, parcel and loader — verified local Sarathis, live tracking.', 'बाइक, ऑटो, कैब, पार्सल, लोडर — सत्यापित लोकल सारथी, लाइव ट्रैकिंग।')) ?></span>
    </div>
  </header>

  <button class="ca-search" type="button" data-open="bike">
    <span class="dot" aria-hidden="true"></span>
    <span><b><?= h(t('Where to?', 'कहाँ जाना है?')) ?></b><small><?= h(t('Select pickup & drop', 'पिकअप और ड्रॉप चुनिए')) ?></small></span>
    <span class="go" aria-hidden="true"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.4" stroke-linecap="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg></span>
  </button>

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

  <a class="ca-join" href="<?= h(u($acc ? '/account.php' : '/')) ?>"><span><?= h(t('Have a bike, auto or vehicle? Earn with Sarathi.', 'बाइक, ऑटो या गाड़ी है? सारथी के साथ कमाइए।')) ?></span><em><?= h(t('Join', 'जुड़िए')) ?></em></a>
  <p class="ca-foot"><a href="<?= h(u('/privacy.php')) ?>"><?= h(t('Privacy policy', 'प्राइवेसी पॉलिसी')) ?></a></p>
</section>

<!-- ================= BOOK ================= -->
<section class="ca-view" data-view="book" hidden>
  <div class="ca-map" id="map" aria-label="<?= h(t('Map', 'नक्शा')) ?>"></div>
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
<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.js"></script>
<script src="<?= h(u('/assets/customer.js')) ?>?v=<?= (int)@filemtime(__DIR__ . '/assets/customer.js') ?>"></script>
</body>
</html>
