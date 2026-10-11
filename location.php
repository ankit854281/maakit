<?php
// ============================================================
//  अपना इलाका चुनिए — delivery location
//
//  Teen raaste, sab ASLI ilakon (villages + service_area_meta) se:
//    1. "Use my location" — phone ki GPS → OpenStreetMap se jagah ka
//       naam/PIN → chalu ilake se mel (assets/geo-area.js)
//    2. Likh kar ya bol kar khojiye (gaon, shahar, PIN)
//    3. Neeche ki suchi se chuniye (bina JavaScript bhi chalta hai)
//
//  Chuna hua ilaka $_SESSION['service_area'] me — isi se header ka
//  "Delivering to", daam aur dukaan-suchi chalti hai (inc/coverage.php).
//  (12 Oct 2026: owner ka naya design — GPS, khoj, mic, WhatsApp —
//   isi page me asli data ke saath jod diya.)
// ============================================================
require_once __DIR__ . '/inc/fn.php';
$tab = 'ghar';
$page_title = t('Choose your location — Maakit', 'अपना इलाका चुनिए — Maakit');
$err = '';

$next = (string)($_POST['next'] ?? $_GET['next'] ?? '/');
if (!preg_match('#^/(?!/)[A-Za-z0-9._/?=&%\-]*$#', $next)) $next = '/';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ajax = post('ajax') === '1';
    if (!csrf_ok()) {
        $err = t('Reload and try again.', 'पेज दोबारा खोलिए।');
    } elseif (post('do') === 'clear') {
        unset($_SESSION['service_area']);
        if ($ajax) { header('Content-Type: application/json'); echo json_encode(['ok' => true]); exit; }
        redirect('/location.php');
    } elseif ($area = coverage_area($pdo, post('area'))) {
        $_SESSION['service_area'] = $area['name'];
        if ($ajax) {
            header('Content-Type: application/json');
            echo json_encode(['ok' => true, 'label' => coverage_label($area), 'next' => $next], JSON_UNESCAPED_UNICODE);
            exit;
        }
        redirect($next);
    } else {
        $err = t('This area is not active. Request coverage below.', 'यह इलाका अभी चालू नहीं है। नीचे सेवा के लिए अनुरोध करें।');
    }
    if ($ajax) { header('Content-Type: application/json'); echo json_encode(['ok' => false, 'msg' => $err], JSON_UNESCAPED_UNICODE); exit; }
}

$q = isset($_GET['q']) && is_string($_GET['q']) ? mb_substr(trim($_GET['q']), 0, 80) : '';
$all = coverage_areas($pdo);
$areas = array_values(array_filter($all, fn($a) => $q === '' || stripos(coverage_label($a) . ' ' . ($a['name_en'] ?? '') . ' ' . $a['name'] . ' ' . ($a['state'] ?? ''), $q) !== false));
$selected = coverage_selected($pdo);

// JS ke liye (GPS mel + likhte hi khoj)
$js = array_map(fn($a) => [
    'name' => $a['name'], 'name_en' => $a['name_en'] ?? '', 'city' => $a['city'] ?? '', 'state' => $a['state'] ?? '',
    'pincode' => $a['pincode'] ?? '', 'label' => coverage_label($a),
    'del' => coverage_enabled($a) ? 1 : 0, 'book' => coverage_enabled($a, 'booking') ? 1 : 0,
], $all);

// shahar ke hisaab se jhund
$groups = [];
foreach ($areas as $a) $groups[($a['city'] ?? '') !== '' ? $a['city'] : t('Other areas', 'बाकी इलाके')][] = $a;

include __DIR__ . '/inc/head.php';
?>
<section class="loc">
<div class="loc-wrap">

  <div class="loc-card">
    <div class="loc-top">
      <a class="loc-back" href="<?= h($next) ?>" aria-label="<?= h(t('Back', 'वापस')) ?>">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg></a>
      <div>
        <h1><?= t('Choose your location', 'अपना इलाका चुनिए') ?></h1>
        <p><?= t('We show shops, prices and delivery for your area. Orders and bookings are accepted only in active service areas.', 'आपके इलाके की दुकानें, दाम और डिलीवरी दिखाते हैं। ऑर्डर और बुकिंग सिर्फ़ चालू सेवा क्षेत्र में लिए जाते हैं।') ?></p>
      </div>
    </div>

    <?php if ($err): ?><div class="loc-err" role="alert"><?= h($err) ?></div><?php endif; ?>

    <?php if ($selected): ?>
      <div class="loc-now">
        <span class="loc-pin" aria-hidden="true"><svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2a7 7 0 0 0-7 7c0 5 7 13 7 13s7-8 7-13a7 7 0 0 0-7-7zm0 9.5A2.5 2.5 0 1 1 12 6.5a2.5 2.5 0 0 1 0 5z"/></svg></span>
        <div><small><?= t('Delivering to', 'डिलीवरी यहाँ') ?></small><b><?= h(coverage_label($selected)) ?></b>
          <em class="<?= coverage_enabled($selected) ? 'on' : 'off' ?>"><?= coverage_enabled($selected) ? t('Delivery on', 'डिलीवरी चालू') : t('Delivery paused', 'डिलीवरी बंद') ?></em></div>
        <form method="post"><input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="do" value="clear">
          <button class="loc-link"><?= t('Change', 'बदलिए') ?></button></form>
      </div>
    <?php endif; ?>

    <!-- 1. GPS -->
    <button type="button" class="loc-gps" data-gps>
      <span class="loc-gps-ic" aria-hidden="true"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3.5"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3"/><circle cx="12" cy="12" r="8"/></svg></span>
      <span><b><?= t('Use my current location', 'मेरी लोकेशन से चुनिए') ?></b><small data-gps-msg><?= t('Finds your village or area by GPS', 'GPS से आपका गाँव / इलाका ढूँढेगा') ?></small></span>
      <i class="loc-spin" aria-hidden="true"></i>
    </button>
    <div class="loc-result" data-gps-out hidden></div>

    <div class="loc-or"><span><?= t('or', 'या') ?></span></div>

    <!-- 2. Khoj -->
    <form class="loc-search" method="get" action="/location.php" role="search">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
      <input type="search" name="q" value="<?= h($q) ?>" placeholder="<?= h(t('Search village, city or PIN code', 'गाँव, शहर या PIN code खोजिए')) ?>" aria-label="<?= h(t('Search areas', 'इलाका खोजें')) ?>" autocomplete="off" data-q>
      <?php if ($next !== '/'): ?><input type="hidden" name="next" value="<?= h($next) ?>"><?php endif; ?>
      <button type="button" class="loc-mic" data-mic hidden title="<?= h(t('Speak', 'बोलकर खोजिए')) ?>" aria-label="<?= h(t('Speak', 'बोलकर खोजिए')) ?>">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><rect x="9" y="2" width="6" height="12" rx="3"/><path d="M5 11a7 7 0 0 0 14 0M12 18v4"/></svg></button>
    </form>

    <!-- 3. Suchi -->
    <div class="loc-list" data-list>
      <?php foreach ($groups as $city => $list): ?>
        <div class="loc-grp" data-grp>
          <h2><?= h($city) ?></h2>
          <?php foreach ($list as $a): $on = coverage_enabled($a); $bk = coverage_enabled($a, 'booking'); ?>
            <form method="post" class="loc-item<?= $selected && $selected['name'] === $a['name'] ? ' sel' : '' ?>" data-item data-s="<?= h(mb_strtolower(coverage_label($a) . ' ' . ($a['name_en'] ?? '') . ' ' . $a['name'] . ' ' . ($a['state'] ?? ''))) ?>">
              <input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="area" value="<?= h($a['name']) ?>"><input type="hidden" name="next" value="<?= h($next) ?>">
              <button>
                <span class="loc-dot<?= $on ? ' on' : '' ?>" aria-hidden="true"></span>
                <span class="loc-item-t"><b><?= h(vname($a)) ?></b><small><?= h(trim(($a['city'] ?? '') . (!empty($a['pincode']) ? ' · ' . $a['pincode'] : '') . (!empty($a['state']) ? ' · ' . $a['state'] : ''), ' ·')) ?></small></span>
                <span class="loc-tags"><em class="<?= $on ? 'on' : 'off' ?>"><?= $on ? t('Delivery', 'डिलीवरी') : t('Delivery paused', 'डिलीवरी बंद') ?></em><?php if ($bk): ?><em class="on"><?= t('Bookings', 'बुकिंग') ?></em><?php endif; ?></span>
                <svg class="loc-go" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" aria-hidden="true"><path d="M9 6l6 6-6 6"/></svg>
              </button>
            </form>
          <?php endforeach; ?>
        </div>
      <?php endforeach; ?>
      <p class="loc-none" data-none<?= $areas ? ' hidden' : '' ?>><?= t('No active area matched. Register your interest below — this is not an order.', 'कोई चालू इलाका नहीं मिला। नीचे सेवा के लिए अनुरोध भेजिए — यह ऑर्डर नहीं है।') ?></p>
    </div>

    <div class="loc-foot">
      <a class="loc-req" href="/area.php"><b><?= t('Not in the list?', 'सूची में नहीं है?') ?></b> <?= t('Request service in my area →', 'मेरे इलाके में सेवा का अनुरोध →') ?></a>
      <a class="loc-wa" href="<?= h(wa_link(MAAKIT_WA, t('Hello, please help me set my delivery location on Maakit.', 'नमस्ते, मुझे Maakit पर डिलीवरी लोकेशन सेट करने में मदद चाहिए।'))) ?>" target="_blank" rel="noopener">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2zm5.2 14.1c-.2.6-1.3 1.2-1.8 1.2-.5.1-1 .2-3.3-.7-2.8-1.1-4.6-4-4.7-4.2-.1-.2-1.1-1.5-1.1-2.9s.7-2 1-2.3c.2-.3.5-.3.7-.3h.5c.2 0 .4 0 .6.5l.8 2c.1.2.1.4 0 .5l-.3.5-.4.4c-.1.1-.3.3-.1.6.2.3.8 1.3 1.7 2.1 1.2 1 2.1 1.3 2.4 1.5.3.1.5.1.6-.1l.9-1c.2-.3.4-.2.6-.1l1.9.9c.3.1.5.2.5.3.1.2.1.7-.1 1.4z"/></svg>
        <?= t('Share live location on WhatsApp', 'WhatsApp पर लाइव लोकेशन भेजिए') ?></a>
      <small class="loc-osm"><?= t('Place names from', 'जगह के नाम') ?> © <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a></small>
    </div>
  </div>

</div>
</section>

<script src="/assets/geo-area.js?v=<?= (int)filemtime(__DIR__ . '/assets/geo-area.js') ?>"></script>
<script>
(function () {
  var AREAS = <?= json_encode($js, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
  var CSRF = <?= json_encode(csrf()) ?>, NEXT = <?= json_encode($next) ?>;
  var L = {
    finding: <?= json_encode(t('Finding you…', 'आपको ढूँढ रहे हैं…')) ?>,
    denied: <?= json_encode(t('Location permission was blocked. Allow it in the browser, or search below.', 'लोकेशन की इजाज़त नहीं मिली। ब्राउज़र में इजाज़त दीजिए, या नीचे खोजिए।')) ?>,
    fail: <?= json_encode(t('Could not find your location. Please search below.', 'लोकेशन नहीं मिल पाई। नीचे खोजिए।')) ?>,
    youAre: <?= json_encode(t('You are near', 'आप यहाँ हैं')) ?>,
    deliver: <?= json_encode(t('Deliver here', 'यहीं डिलीवरी कीजिए')) ?>,
    paused: <?= json_encode(t('Delivery is paused here right now', 'यहाँ अभी डिलीवरी बंद है')) ?>,
    nearby: <?= json_encode(t('Nearby Maakit areas', 'पास के Maakit इलाके')) ?>,
    notyet: <?= json_encode(t('Maakit is not in your area yet.', 'Maakit अभी आपके इलाके में नहीं है।')) ?>,
    request: <?= json_encode(t('Request service here →', 'यहाँ सेवा का अनुरोध कीजिए →')) ?>,
    saving: <?= json_encode(t('Saving…', 'सेव हो रहा है…')) ?>
  };
  var esc = function (s) { var d = document.createElement('div'); d.textContent = s == null ? '' : s; return d.innerHTML; };

  function choose(name, btn) {
    if (btn) { btn.disabled = true; btn.classList.add('busy'); }
    var fd = new FormData(); fd.append('csrf', CSRF); fd.append('area', name); fd.append('next', NEXT); fd.append('ajax', '1');
    fetch('/location.php', { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.json(); })
      .then(function (j) { if (j.ok) { try { navigator.vibrate && navigator.vibrate(15); } catch (e) {} location.href = j.next || '/'; } else { alert(j.msg); if (btn) { btn.disabled = false; btn.classList.remove('busy'); } } })
      .catch(function () { if (btn) { btn.disabled = false; btn.classList.remove('busy'); } });
  }

  // 1. GPS
  var gps = document.querySelector('[data-gps]'), out = document.querySelector('[data-gps-out]'), msg = document.querySelector('[data-gps-msg]');
  var msg0 = msg.textContent;
  function areaRow(a, primary) {
    return '<button type="button" class="loc-pick' + (primary ? ' main' : '') + '" data-pick="' + esc(a.name) + '">' +
      '<span class="loc-dot' + (a.del ? ' on' : '') + '"></span><span class="loc-item-t"><b>' + esc(a.label) + '</b>' +
      (a.del ? '' : '<small>' + esc(L.paused) + '</small>') + '</span><span class="loc-cta">' + esc(primary ? L.deliver : '→') + '</span></button>';
  }
  gps.addEventListener('click', function () {
    gps.classList.add('busy'); msg.textContent = L.finding; out.hidden = true;
    MkGeo.detect(AREAS).then(function (r) {
      gps.classList.remove('busy'); msg.textContent = msg0;
      var h = '<div class="loc-you">📍 ' + esc(L.youAre) + ': <b>' + esc(r.place || (r.lat.toFixed(4) + ', ' + r.lng.toFixed(4))) + '</b></div>';
      if (r.match) h += areaRow(r.match, true);
      var near = (r.near || []).filter(function (a) { return !r.match || a.name !== r.match.name; });
      if (near.length) h += '<div class="loc-sub">' + esc(L.nearby) + '</div>' + near.map(function (a) { return areaRow(a, false); }).join('');
      if (!r.match && !near.length) h += '<p class="loc-notyet">' + esc(L.notyet) + ' <a href="/area.php?village=' + encodeURIComponent(r.place || '') + '">' + esc(L.request) + '</a></p>';
      out.innerHTML = h; out.hidden = false;
      out.querySelectorAll('[data-pick]').forEach(function (b) { b.addEventListener('click', function () { choose(b.getAttribute('data-pick'), b); }); });
      // ek hi pakka chalu ilaka mila — seedha wahi chun lo
      if (r.match && r.match.del && !near.length) { var m = out.querySelector('.loc-pick.main'); if (m) setTimeout(function () { m.classList.add('pulse'); }, 50); }
    }).catch(function (e) {
      gps.classList.remove('busy');
      msg.textContent = (e && e.code === 1) ? L.denied : L.fail;
      gps.classList.add('bad'); setTimeout(function () { gps.classList.remove('bad'); }, 2500);
    });
  });

  // 2. Likhte hi khoj (suchi chhanti hai, panna nahi badalta)
  var q = document.querySelector('[data-q]'), items = document.querySelectorAll('[data-item]'), none = document.querySelector('[data-none]');
  function filter() {
    var v = q.value.trim().toLowerCase(), shown = 0;
    items.forEach(function (it) { var ok = !v || it.getAttribute('data-s').indexOf(v) >= 0; it.hidden = !ok; if (ok) shown++; });
    document.querySelectorAll('[data-grp]').forEach(function (g) { g.hidden = !g.querySelector('[data-item]:not([hidden])'); });
    none.hidden = shown > 0;
  }
  q.addEventListener('input', filter);
  q.form.addEventListener('submit', function (e) { e.preventDefault(); filter(); var f = document.querySelector('[data-item]:not([hidden]) button'); if (f && document.querySelectorAll('[data-item]:not([hidden])').length === 1) f.focus(); });

  // suchi se chunna — bina panna-reload ke
  items.forEach(function (f) { f.addEventListener('submit', function (e) { e.preventDefault(); choose(f.querySelector('[name=area]').value, f.querySelector('button')); }); });

  // bol kar khojiye
  var SR = window.SpeechRecognition || window.webkitSpeechRecognition, mic = document.querySelector('[data-mic]');
  if (SR && mic) {
    mic.hidden = false;
    mic.addEventListener('click', function () {
      var r = new SR(); r.lang = document.documentElement.lang === 'en' ? 'en-IN' : 'hi-IN'; r.interimResults = false;
      mic.classList.add('on');
      r.onresult = function (ev) { q.value = ev.results[0][0].transcript.replace(/[.।]$/, ''); filter(); };
      r.onend = function () { mic.classList.remove('on'); };
      try { r.start(); } catch (e) { mic.classList.remove('on'); }
    });
  }
})();
</script>
<?php include __DIR__ . '/inc/foot.php'; ?>
