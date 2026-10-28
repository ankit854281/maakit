<?php
// ============================================================
//  सवारी बुक कीजिए / पार्सल भेजिए  — maakit.in/ride.php
//
//  Grahak ka panna. Rider Smart Sarathi app me kaam lete hain;
//  yahan ki booking inc/trips.php → Smart Sarathi API se wahan
//  "kaam" banti hai (kind = ride / delivery).
//
//  ?t=parcel se parcel wala hissa khulta hai. Bina JavaScript
//  bhi chalta hai (naksha aur kiraya-andaza tab nahi dikhte).
//  Naksha: OpenStreetMap (Leaflet). Jagah khojna: Nominatim —
//  sirf "खोजिए" dabane par, likhte-likhte nahi (unka niyam).
// ============================================================
require_once __DIR__ . '/inc/fn.php';
require_once __DIR__ . '/inc/trips.php';

$tab = 'ghar';
$kind = (post('kind', get('t')) === 'parcel') ? 'parcel' : 'ride';
$page_title = $kind === 'parcel' ? t('Send a parcel — Maakit', 'पार्सल भेजिए — Maakit') : t('Book a ride — Maakit', 'सवारी बुक कीजिए — Maakit');
$page_css = ['https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.css', '/assets/ride.css?v=' . (int)@filemtime(__DIR__ . '/assets/ride.css')];
$err = '';
$me = function_exists('cust') ? cust() : null;
$V = trip_vehicles($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $n = (int)($_SESSION['trip_n'] ?? 0);
    if (!csrf_ok()) {
        $err = t('Page expired — please press Book again.', 'पेज पुराना हो गया — दोबारा "बुक" दबाइए।');
    } elseif (post('website') !== '') {
        $err = t('Please try again.', 'दोबारा कोशिश कीजिए।');
    } elseif ($n >= 10) {
        $err = t('Too many bookings from this phone. Please call us.', 'इस फ़ोन से बहुत बुकिंग हुईं। हमें फ़ोन कीजिए।');
    } else {
        $in = $_POST; $in['kind'] = $kind; $in['customer_id'] = $me['id'] ?? null;
        [$ok, $out] = trip_create($pdo, $in);
        if ($ok) {
            $_SESSION['trip_n'] = $n + 1;
            $_SESSION['my_trips'][] = $out['trip_no'];
            redirect('/trip.php?no=' . urlencode($out['trip_no']) . '&new=1');
        }
        $err = $out;
    }
}

$mine = trip_mine($pdo, $me['mobile'] ?? null, 3);
$val = fn($k, $d = '') => h((string)post($k, $d));
$vehOn = array_filter($V, fn($v) => $v['on'] && $v['key'] !== 'parcel');
// ?v=bike|auto|erick — Smart Sarathi home ke dibbe se wahi gaadi pehle se chuni aaye
$selV = post('vehicle', isset($vehOn[(string)get('v')]) ? (string)get('v') : (array_key_first($vehOn) ?: 'bike'));
$csrf = h(csrf());

include __DIR__ . '/inc/head.php';
?>
<main class="rd" data-kind="<?= h($kind) ?>">
<div class="rd-wrap">

  <div class="rd-top">
    <a class="rd-back" href="/" aria-label="<?= h(t('Back', 'वापस')) ?>"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6"/></svg></a>
    <div>
      <h1 data-h-ride<?= $kind === 'parcel' ? ' hidden' : '' ?>><?= t('Where to?', 'कहाँ जाना है?') ?></h1>
      <h1 data-h-parcel<?= $kind === 'ride' ? ' hidden' : '' ?>><?= t('Send anything nearby', 'आस-पास कुछ भी भेजिए') ?></h1>
      <p><?= t('Local Sarathi riders · fare shown before you book', 'लोकल सारथी राइडर · बुक करने से पहले किराया दिखेगा') ?></p>
    </div>
  </div>

  <nav class="rd-seg" aria-label="<?= h(t('Choose service', 'सेवा चुनिए')) ?>">
    <a href="/ride.php" data-seg="ride"<?= $kind === 'ride' ? ' class="on" aria-current="page"' : '' ?>><span>🏍️</span><?= t('Ride', 'सवारी') ?></a>
    <a href="/ride.php?t=parcel" data-seg="parcel"<?= $kind === 'parcel' ? ' class="on" aria-current="page"' : '' ?>><span>📦</span><?= t('Parcel', 'पार्सल') ?></a>
  </nav>

  <?php if ($err): ?><div class="rd-err" role="alert"><?= h($err) ?></div><?php endif; ?>

  <form method="post" class="rd-form" data-rd novalidate>
    <input type="hidden" name="csrf" value="<?= $csrf ?>">
    <input type="hidden" name="kind" value="<?= h($kind) ?>" data-kind-in>
    <input type="hidden" name="pick_geo" value="<?= $val('pick_geo') ?>" data-geo="a">
    <input type="hidden" name="drop_geo" value="<?= $val('drop_geo') ?>" data-geo="b">
    <input type="text" name="website" value="" tabindex="-1" autocomplete="off" class="rd-hp" aria-hidden="true">

    <!-- naksha + raasta -->
    <section class="rd-card rd-route">
      <div class="rd-map" id="rdMap" hidden>
        <button type="button" class="rd-map-me" data-locate aria-label="<?= h(t('My location', 'मेरी लोकेशन')) ?>"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><circle cx="12" cy="12" r="3.5"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3"/></svg></button>
        <div class="rd-map-tip" data-map-tip hidden><?= t('Tap the map to set the drop point', 'नक्शे पर छूकर उतरने की जगह लगाइए') ?></div>
      </div>
      <div class="rd-pts">
        <div class="rd-pt a">
          <i></i>
          <label><small data-l-ride<?= $kind === 'parcel' ? ' hidden' : '' ?>><?= t('Pickup', 'कहाँ से') ?></small><small data-l-parcel<?= $kind === 'ride' ? ' hidden' : '' ?>><?= t('Pick up parcel from', 'पार्सल कहाँ से उठाना है') ?></small>
            <input name="pick_address" value="<?= $val('pick_address', $me['village'] ?? '') ?>" required placeholder="<?= h(t('Your place, mohalla, landmark', 'आपकी जगह, मोहल्ला, पहचान')) ?>" autocomplete="street-address" data-pa enterkeyhint="search"></label>
          <button type="button" class="rd-gps find" data-find-pick hidden aria-label="<?= h(t('Find pickup','उठाने की जगह खोजिए')) ?>"><span><?= t('Find','खोजिए') ?></span></button>
          <button type="button" class="rd-gps" data-gps title="<?= h(t('Use my current location', 'मेरी अभी की लोकेशन')) ?>"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><circle cx="12" cy="12" r="3.5"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3"/></svg><span><?= t('GPS', 'GPS') ?></span></button>
        </div>
        <div class="rd-pt b">
          <i></i>
          <label><small data-l-ride<?= $kind === 'parcel' ? ' hidden' : '' ?>><?= t('Drop', 'कहाँ तक') ?></small><small data-l-parcel<?= $kind === 'ride' ? ' hidden' : '' ?>><?= t('Deliver to', 'कहाँ पहुँचाना है') ?></small>
            <input name="drop_address" value="<?= $val('drop_address') ?>" required placeholder="<?= h(t('Search a place — e.g. Gyanpur bus stand', 'जगह खोजिए — जैसे ज्ञानपुर बस स्टैंड')) ?>" data-da enterkeyhint="search"></label>
          <button type="button" class="rd-gps find" data-find hidden><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg><span><?= t('Find', 'खोजिए') ?></span></button>
        </div>
        <button type="button" class="rd-swap" data-swap hidden aria-label="<?= h(t('Swap', 'उलटिए')) ?>"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M7 4v16M7 4l-3 3M7 4l3 3M17 20V4M17 20l-3-3M17 20l3-3"/></svg></button>
      </div>
      <div class="rd-results" data-results hidden role="status" aria-live="polite"></div>
      <div class="rd-quick" data-quick hidden></div>
      <button type="button" class="rd-onmap" data-onmap hidden><svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2a7 7 0 0 0-7 7c0 5 7 13 7 13s7-8 7-13a7 7 0 0 0-7-7zm0 9.5A2.5 2.5 0 1 1 12 6.5a2.5 2.5 0 0 1 0 5z"/></svg> <?= t('Set drop on map', 'नक्शे पर जगह लगाइए') ?></button>
    </section>

    <!-- sawari: gaadi -->
    <fieldset class="rd-card rd-only" data-only="ride"<?= $kind === 'parcel' ? ' disabled hidden' : '' ?>>
      <legend class="rd-h"><?= t('Choose a ride', 'गाड़ी चुनिए') ?> <small data-km></small></legend>
      <div class="rd-vs">
        <?php foreach ($vehOn as $k => $v): ?>
          <label class="rd-v">
            <input type="radio" name="vehicle" value="<?= h($k) ?>" <?= $selV === $k ? 'checked' : '' ?> data-v="<?= h($k) ?>">
            <span class="rd-v-in">
              <i class="rd-v-em" aria-hidden="true"><?= $v['emoji'] ?></i>
              <span class="rd-v-t"><b><?= h($v['name']) ?></b><small><?= $v['seats'] > 1 ? t('Up to ', 'ज़्यादा से ज़्यादा ') . (int)$v['seats'] . t(' people', ' लोग') : t('1 person · fastest', '1 सवारी · सबसे तेज़') ?></small></span>
              <span class="rd-v-f"><b data-fare="<?= h($k) ?>"><?= t('from', '') ?> ₹<?= (int)$v['base'] ?></b><small data-fare-l><?= t('approx.', 'लगभग') ?></small></span>
            </span>
          </label>
        <?php endforeach; ?>
      </div>
      <div class="rd-seats" data-seats>
        <span><?= t('People', 'कितने लोग') ?></span>
        <?php for ($i = 1; $i <= 4; $i++): ?><label><input type="radio" name="seats" value="<?= $i ?>" <?= (int)post('seats', 1) === $i ? 'checked' : '' ?>><span><?= $i ?></span></label><?php endfor; ?>
      </div>
    </fieldset>

    <!-- parcel -->
    <fieldset class="rd-card rd-only" data-only="parcel"<?= $kind === 'ride' ? ' disabled hidden' : '' ?>>
      <legend class="rd-h"><?= t('What are you sending?', 'क्या भेजना है?') ?></legend>
      <div class="rd-chips">
        <?php foreach (trip_parcel_types() as $k => $lbl): $em = ['tiffin' => '🍱', 'docs' => '📄', 'medicine' => '💊', 'shop' => '🛍️', 'clothes' => '👕', 'keys' => '🔑', 'other' => '📦'][$k]; ?>
          <label><input type="radio" name="ptype" value="<?= h($k) ?>" <?= post('ptype') === $k ? 'checked' : '' ?>><span><?= $em ?> <?= h($lbl) ?></span></label>
        <?php endforeach; ?>
      </div>
      <label class="rd-f"><span><?= t('Details (optional)', 'और जानकारी (मर्ज़ी से)') ?></span><input name="items" value="<?= $val('items') ?>" maxlength="120" placeholder="<?= h(t('e.g. 2 tiffin boxes, keep upright', 'जैसे 2 टिफ़िन, सीधा रखें')) ?>"></label>
      <label class="rd-sw"><input type="checkbox" name="self" value="1" <?= post('self') === '1' ? 'checked' : '' ?> data-self><span class="rd-sw-k"></span><span><?= t('I will receive it myself', 'मैं ख़ुद लूँगा / लूँगी') ?></span></label>
      <div class="rd-grid2" data-recv>
        <label class="rd-f"><span><?= t('Receiver’s name', 'पाने वाले का नाम') ?></span><input name="drop_name" value="<?= $val('drop_name') ?>" required></label>
        <label class="rd-f"><span><?= t('Receiver’s mobile', 'पाने वाले का मोबाइल') ?></span><input name="drop_mobile" type="tel" inputmode="numeric" value="<?= $val('drop_mobile') ?>" required pattern="[6-9][0-9]{9}"></label>
      </div>
      <div class="rd-seg2">
        <span><?= t('Who pays the rider?', 'राइडर को किराया कौन देगा?') ?></span>
        <label><input type="radio" name="pay_by" value="sender" <?= post('pay_by', 'sender') === 'sender' ? 'checked' : '' ?>><span><?= t('Me, at pickup', 'मैं, देते समय') ?></span></label>
        <label><input type="radio" name="pay_by" value="receiver" <?= post('pay_by') === 'receiver' ? 'checked' : '' ?>><span><?= t('Receiver', 'पाने वाला') ?></span></label>
      </div>
      <div class="rd-pfare"><span><?= t('Parcel charge', 'पार्सल किराया') ?> <small data-km-p></small></span><b data-fare="parcel"><?= t('from', '') ?> ₹<?= (int)$V['parcel']['base'] ?></b></div>
      <p class="rd-fine"><?= t('Not allowed: alcohol, cash, gold, illegal or dangerous items.', 'नहीं भेज सकते: शराब, नगद, सोना, ग़ैरक़ानूनी या ख़तरनाक सामान।') ?></p>
    </fieldset>

    <!-- aap -->
    <section class="rd-card">
      <h2 class="rd-h"><?= t('Your details', 'आपकी जानकारी') ?></h2>
      <div class="rd-grid2">
        <label class="rd-f"><span><?= t('Name', 'नाम') ?></span><input name="name" value="<?= $val('name', $me['name'] ?? '') ?>" required autocomplete="name" data-keep="n"></label>
        <label class="rd-f"><span><?= t('Mobile', 'मोबाइल') ?></span><input name="mobile" type="tel" inputmode="numeric" value="<?= $val('mobile', $me['mobile'] ?? '') ?>" required pattern="[6-9][0-9]{9}" autocomplete="tel-national" data-keep="m"></label>
      </div>
      <div class="rd-seg2 when">
        <label><input type="radio" name="when" value="now" <?= post('when', 'now') === 'now' ? 'checked' : '' ?> data-when><span>⚡ <?= t('Now', 'अभी') ?></span></label>
        <label><input type="radio" name="when" value="later" <?= post('when') === 'later' ? 'checked' : '' ?> data-when><span>🕒 <?= t('Later today', 'आज बाद में') ?></span></label>
      </div>
      <label class="rd-f" data-at<?= post('when') === 'later' ? '' : ' hidden' ?>><span><?= t('Time', 'समय') ?></span><input type="time" name="at" value="<?= $val('at') ?>"></label>
      <label class="rd-f"><span><?= t('Note for rider (optional)', 'राइडर के लिए बात (मर्ज़ी से)') ?></span><input name="note" value="<?= $val('note') ?>" maxlength="200" placeholder="<?= h(t('e.g. near the temple gate, call when close', 'जैसे मंदिर के गेट पर, पास आकर फ़ोन करें')) ?>"></label>
    </section>

    <!-- neeche chipka bar -->
    <div class="rd-bar">
      <div class="rd-bar-t"><small data-bar-l><?= h($kind === 'parcel' ? $V['parcel']['name'] : ($vehOn[$selV]['name'] ?? '')) ?></small><b data-bar-f><?= t('Fare shown after drop', 'जगह डालते ही किराया') ?></b></div>
      <button type="submit" class="rd-go"><span data-go-ride<?= $kind === 'parcel' ? ' hidden' : '' ?>><?= t('Book ride', 'सवारी बुक कीजिए') ?></span><span data-go-parcel<?= $kind === 'ride' ? ' hidden' : '' ?>><?= t('Book pickup', 'पिकअप बुक कीजिए') ?></span>
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg></button>
    </div>
  </form>

  <?php if ($mine): ?>
  <section class="rd-card rd-mine" id="mine">
    <h2 class="rd-h"><?= t('Your recent bookings', 'आपकी पिछली बुकिंग') ?></h2>
    <?php foreach ($mine as $tr): [$k, $nm] = trip_steps($tr); ?>
      <a href="/trip.php?no=<?= h(urlencode($tr['trip_no'])) ?>&amp;m=<?= h(urlencode($tr['mobile'])) ?>">
        <span class="rd-mine-ic"><?= $tr['kind'] === 'parcel' ? '📦' : ($V[$tr['vehicle']]['emoji'] ?? '🏍️') ?></span>
        <span class="rd-mine-t"><b><?= h(mb_strimwidth($tr['drop_address'], 0, 42, '…')) ?></b><small><?= h(date('d M, h:i A', strtotime($tr['created_at']))) ?><?= $tr['fare_est'] ? ' · ₹' . (int)$tr['fare_est'] : '' ?></small></span>
        <em class="st-<?= h($tr['status']) ?>"><?= $tr['status'] === 'cancelled' ? t('Cancelled', 'रद्द') : h($nm[max(0, $k)]) ?></em>
      </a>
    <?php endforeach; ?>
  </section>
  <?php endif; ?>

  <section class="rd-how">
    <div><i>1</i><b><?= t('Book in seconds', 'सेकंडों में बुक') ?></b><small><?= t('Fare is shown before you book', 'बुक से पहले किराया दिखता है') ?></small></div>
    <div><i>2</i><b><?= t('Rider calls you', 'राइडर फ़ोन करेगा') ?></b><small><?= t('Name and number on your screen', 'नाम और नंबर आपकी स्क्रीन पर') ?></small></div>
    <div><i>3</i><b><?= t('Safe with a code', 'कोड से सुरक्षित') ?></b><small><?= t('Trip starts only with your 4-digit code', 'आपके 4 अंक के कोड से ही शुरू') ?></small></div>
  </section>
  <p class="rd-osm"><?= t('Map and place names', 'नक्शा और जगह के नाम') ?> © <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a> · <a href="/smartsarathi/apply.php"><?= t('Have a bike or auto? Become a Sarathi rider →', 'बाइक या ऑटो है? सारथी राइडर बनिए →') ?></a></p>
</div>
</main>
<script>window.RD = <?= json_encode([
    'v' => array_map(fn($v) => ['name' => $v['name'], 'base' => $v['base'], 'km' => $v['km'], 'seats' => $v['seats']], $V),
    'max' => trip_max_km($pdo),
    'lang' => is_hi() ? 'hi' : 'en',
    'center' => [25.395, 82.568],
    'L' => [
        'approx' => t('approx.', 'लगभग'), 'from' => t('from', 'शुरू'), 'km' => t('km', 'km'), 'far' => t('Too far — max ', 'बहुत दूर — ज़्यादा से ज़्यादा '),
        'finding' => t('Finding you…', 'लोकेशन ढूँढ रहे हैं…'), 'nogps' => t('Location not available — please type the place', 'लोकेशन नहीं मिली — जगह लिख दीजिए'),
        'here' => t('Current location', 'अभी की लोकेशन'), 'none' => t('No matching place in the local service range. Add the town name or pin the exact place on the map.', 'इलाके में सही जगह नहीं मिली। कस्बे का नाम जोड़ें या नक्शे पर सही जगह लगाएँ।'),
        'need' => t('Please fill the highlighted fields', 'लाल वाले खाने भरिए'), 'pin' => t('Pinned on map', 'नक्शे पर लगाई जगह'),
        'after' => t('Pin pickup and drop for fare', 'किराए के लिए दोनों जगह लगाइए'), 'searching' => t('Searching…', 'खोज रहे हैं…'),
        'ride' => t('Book ride', 'सवारी बुक कीजिए'), 'busy' => t('Booking…', 'बुक हो रहा है…'),
    ],
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;</script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.js" defer></script>
<script src="/assets/ride.js?v=<?= (int)@filemtime(__DIR__ . '/assets/ride.js') ?>" defer></script>
<?php include __DIR__ . '/inc/foot.php'; ?>
