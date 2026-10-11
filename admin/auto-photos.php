<?php
// ============================================================
//  अपने आप फ़ोटो — Auto photos (Oct 2026)
//
//  Bina photo wale saaman ke liye MUFT aur KANOONI photo library
//  me khoj:
//    - Wikimedia Commons  (har tarah ka saaman — chawal, kurti, clutch plate…)
//    - Open Food Facts    (pack wala khana — atta, biscuit, namkeen…)
//  Google ya brand ki website se photo NAHI li jati (copyright).
//
//  Khoj admin ke browser se hoti hai (dono library CORS dete hain).
//  "Le lo" dabane par SERVER photo utaarta hai, 900px chaukor karke
//  uploads me rakhta hai, aur photo kheenchne wale ka naam + licence
//  catalog_photos me likhta hai (/photo-credits.php par dikhta hai).
//
//  Do tareeke:
//   1. Dekh kar lagaiye — 12-12 saaman, har ek ke 4 sujhav; pakka
//      mel wala pehle se chuna hota hai → "Chune hue sab lagaiye".
//   2. Apne aap — sirf pakka mel (naam ke saare shabd photo ke naam
//      me) apne aap lagta hai; baaki "dekhna hai" me rehta hai.
// ============================================================
require_once __DIR__ . '/../inc/fn.php';
require_once __DIR__ . '/../inc/catalog.php';
require_once __DIR__ . '/../inc/catalog-photos.php';
require_once __DIR__ . '/../inc/photo-fetch.php';
$u = need_role(['admin', 'designer']);
$page_title = t('Auto photos', 'अपने आप फ़ोटो') . ' — Maakit Admin';

$ready = true;
try { $pdo->query("SELECT source FROM catalog_photos LIMIT 1"); $pdo->query("SELECT 1 FROM catalog_photo_skip LIMIT 1"); }
catch (Throwable $e) { $ready = false; }

function ap_json($a) { header('Content-Type: application/json; charset=utf-8'); header('Cache-Control: no-store'); echo json_encode($a, JSON_UNESCAPED_UNICODE); exit; }

/** bina photo wale (aur jinhe chhoda nahi) — WHERE ka hissa */
function ap_where($type, &$args) {
    $w = "NOT EXISTS (SELECT 1 FROM catalog_photos p WHERE p.cat_id=ci.id)
          AND NOT EXISTS (SELECT 1 FROM catalog_photo_skip s WHERE s.cat_id=ci.id) AND ci.is_sewa=0";
    if ($type !== '') { $w .= " AND ci.shop_type=?"; $args[] = $type; }
    return $w;
}

// ---------------- AJAX ----------------
if ($ready && get('do') === 'list') {
    $type = (string)get('type'); $after = max(0, (int)get('after')); $n = min(40, max(1, (int)get('n', 12)));
    $args = [];
    $w = ap_where($type, $args);
    $args[] = $after;
    $st = $pdo->prepare("SELECT ci.id, ci.name_en, ci.name_hi, ci.shop_type, ci.sub_cat FROM catalog_items ci WHERE $w AND ci.id > ? ORDER BY ci.id LIMIT $n");
    $st->execute($args);
    $a2 = []; $w2 = ap_where($type, $a2);
    $c = $pdo->prepare("SELECT COUNT(*) FROM catalog_items ci WHERE $w2"); $c->execute($a2);
    ap_json(['ok' => true, 'items' => $st->fetchAll(), 'left' => (int)$c->fetchColumn()]);
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('ajax') === '1') {
    if (!csrf_ok()) ap_json(['ok' => false, 'msg' => t('Page expired — please reload', 'पेज पुराना हो गया — दोबारा खोलिए')]);
    if (!$ready) ap_json(['ok' => false, 'msg' => t('Press Update website first', 'पहले Update website दबाइए')]);
    $id = (int)post('cat_id');
    if (post('do') === 'take') {
        list($ok, $msg) = pf_take($pdo, $id, [
            'url' => post('url'), 'source' => post('source'), 'credit' => post('credit'),
            'license' => post('license'), 'page' => post('page'),
        ], $u['id'] ?? 0);
        ap_json(['ok' => $ok, 'msg' => $ok ? t('Photo added', 'फ़ोटो लग गई') : $msg, 'file' => $ok ? $msg : null]);
    }
    if (post('do') === 'skip') {
        $pdo->prepare("INSERT IGNORE INTO catalog_photo_skip (cat_id, skipped_by) VALUES (?,?)")->execute([$id, (int)($u['id'] ?? 0)]);
        ap_json(['ok' => true]);
    }
    ap_json(['ok' => false, 'msg' => 'unknown']);
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('do') === 'unskip' && csrf_ok() && $ready) {
    $pdo->exec("DELETE FROM catalog_photo_skip");
    flash(t('Skipped products are back in the list.', 'छोड़े हुए सामान वापस सूची में आ गए।'));
    redirect('/admin/auto-photos.php');
}

// ---------------- page ke number ----------------
$total = (int)$pdo->query("SELECT COUNT(*) FROM catalog_items WHERE is_sewa=0")->fetchColumn();
$with = $ready ? (int)$pdo->query("SELECT COUNT(DISTINCT p.cat_id) FROM catalog_photos p JOIN catalog_items ci ON ci.id=p.cat_id WHERE ci.is_sewa=0")->fetchColumn() : 0;
$lib  = $ready ? (int)$pdo->query("SELECT COUNT(*) FROM catalog_photos WHERE source IS NOT NULL")->fetchColumn() : 0;
$skipped = $ready ? (int)$pdo->query("SELECT COUNT(*) FROM catalog_photo_skip")->fetchColumn() : 0;
$types = $ready ? $pdo->query("SELECT ci.shop_type, COUNT(*) c FROM catalog_items ci
            WHERE ci.is_sewa=0 AND NOT EXISTS (SELECT 1 FROM catalog_photos p WHERE p.cat_id=ci.id)
              AND NOT EXISTS (SELECT 1 FROM catalog_photo_skip s WHERE s.cat_id=ci.id)
            GROUP BY ci.shop_type ORDER BY c DESC")->fetchAll() : [];
$left = array_sum(array_column($types, 'c'));
// khane-peene wali dukaan — inke liye Open Food Facts bhi dekha jata hai
$food = ['Grocery / Kirana Store', 'Dairy Shop', 'Bakery', 'Sweet Shop', 'Baby Store', 'Dry Fruits Shop', 'Beverage Shop', 'Namkeen Shop', 'Supermarket'];

include __DIR__ . '/../inc/panel.php';
?>
<section class="ph-page ap">
<div class="mk-wrap">

  <div class="ph-top">
    <div>
      <h1><?= t('Auto photos', 'अपने आप फ़ोटो') ?></h1>
      <p><?= t('Finds real photos for products without one — only from free, legal libraries: Wikimedia Commons and Open Food Facts. The photographer’s name and licence are saved and shown on the', 'बिना फ़ोटो वाले सामान की असली फ़ोटो ढूँढता है — सिर्फ़ मुफ़्त और क़ानूनी लाइब्रेरी से: Wikimedia Commons और Open Food Facts। फ़ोटो खींचने वाले का नाम और लाइसेंस सेव होता है और दिखता है —') ?> <a href="/photo-credits.php" target="_blank"><?= t('credits page', 'क्रेडिट पेज') ?></a>.</p>
    </div>
    <div class="ph-stats">
      <span><b><?= num($total) ?></b><?= t('Products', 'कुल सामान') ?></span>
      <span class="ok"><b data-ap-with><?= num($with) ?></b><?= t('With photos', 'फ़ोटो वाले') ?></span>
      <span class="warn"><b data-ap-left><?= num($left) ?></b><?= t('To find', 'ढूँढने बाकी') ?></span>
    </div>
  </div>
  <div class="ph-bar" aria-hidden="true"><i data-ap-bar style="width:<?= $total ? round($with * 100 / $total, 1) : 0 ?>%"></i></div>

  <?php if (!$ready): ?>
    <div class="err"><?= t('Press Update website on the dashboard first, then open this page again.', 'पहले Dashboard पर Update website दबाइए, फिर यह page दोबारा खोलिए।') ?></div>
  <?php else: ?>

  <div class="ap-bar">
    <label class="ap-sel"><span><?= t('Shop type', 'दुकान की किस्म') ?></span>
      <select data-ap-type>
        <option value=""><?= t('All types', 'सभी') ?> (<?= num($left) ?>)</option>
        <?php foreach ($types as $tp): ?><option value="<?= h($tp['shop_type']) ?>"><?= h($tp['shop_type']) ?> (<?= (int)$tp['c'] ?>)</option><?php endforeach; ?>
      </select>
    </label>
    <div class="ap-modes" role="tablist">
      <button class="on" data-mode="review" role="tab"><?= t('Check & add', 'देखकर लगाइए') ?></button>
      <button data-mode="auto" role="tab"><?= t('Fully automatic', 'पूरा अपने आप') ?></button>
    </div>
    <span class="ap-grow"></span>
    <?php if ($skipped): ?>
      <form method="post" onsubmit="return confirm(<?= h(json_encode(t('Bring all skipped products back?', 'छोड़े हुए सारे सामान वापस लाएँ?'))) ?>)">
        <input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="do" value="unskip">
        <button class="mk-btn mk-btn-sm mk-btn-line"><?= t('Skipped', 'छोड़े हुए') ?>: <?= num($skipped) ?> · <?= t('bring back', 'वापस लाइए') ?></button>
      </form>
    <?php endif; ?>
    <a class="mk-btn mk-btn-sm mk-btn-line" href="/admin/product-photos.php?f=missing"><?= t('Upload my own photos', 'अपनी फ़ोटो लगाइए') ?></a>
  </div>

  <!-- 1. dekh kar lagaiye -->
  <div data-panel="review">
    <div class="ap-help"><?= t('Each product shows up to 4 photos. A green tick means the photo name matches the product — it is pre-selected. Tap a photo to choose or un-choose it, then press “Add all chosen”. Wrong product? Press Skip.', 'हर सामान की 4 तक फ़ोटो दिखेंगी। हरा ✓ = फ़ोटो का नाम सामान से मिलता है, वह पहले से चुनी है। फ़ोटो दबाकर चुनिए या हटाइए, फिर “चुनी हुई सब लगाइए” दबाइए। ग़लत सामान हो तो “छोड़िए”।') ?></div>
    <div class="ap-grid" data-ap-grid></div>
    <div class="ap-foot">
      <span data-ap-sel-count></span>
      <button class="mk-btn mk-btn-line" data-ap-next><?= t('Next 12 →', 'अगले 12 →') ?></button>
      <button class="mk-btn" data-ap-addall><?= t('Add all chosen', 'चुनी हुई सब लगाइए') ?></button>
    </div>
  </div>

  <!-- 2. pura apne aap -->
  <div data-panel="auto" hidden>
    <div class="ap-auto">
      <div class="ap-auto-h">
        <div>
          <b><?= t('Add sure matches by themselves', 'पक्के मेल वाली फ़ोटो अपने आप लगाइए') ?></b>
          <small><?= t('Only a photo whose name contains every word of the product name is added. The rest wait for “Check & add”. Keep this tab open — about one product per second.', 'सिर्फ़ वही फ़ोटो लगती है जिसके नाम में सामान के नाम के सारे शब्द हों। बाकी “देखकर लगाइए” में रहते हैं। यह टैब खुला रखिए — लगभग एक सामान प्रति सेकंड।') ?></small>
        </div>
        <button class="mk-btn" data-ap-run><?= t('Start', 'शुरू कीजिए') ?></button>
      </div>
      <div class="ap-counts">
        <span class="ok"><b data-c="added">0</b><?= t('Added', 'लगीं') ?></span>
        <span><b data-c="review">0</b><?= t('Need a look', 'देखना है') ?></span>
        <span><b data-c="none">0</b><?= t('Nothing found', 'कुछ नहीं मिला') ?></span>
        <span><b data-c="fail">0</b><?= t('Failed', 'नहीं लगीं') ?></span>
      </div>
      <div class="ap-prog"><i data-ap-prog></i></div>
      <div class="ap-log" data-ap-log aria-live="polite"></div>
    </div>
  </div>
  <?php endif; ?>
</div>
</section>

<script>
(function () {
  var CSRF = <?= json_encode(csrf()) ?>, FOOD = <?= json_encode($food) ?>;
  var L = {
    pick: <?= json_encode(t('Sure match', 'पक्का मेल')) ?>, skip: <?= json_encode(t('Skip', 'छोड़िए')) ?>,
    none: <?= json_encode(t('No free photo found. Edit the words and search again, or skip.', 'कोई मुफ़्त फ़ोटो नहीं मिली। शब्द बदलकर दोबारा खोजिए या छोड़िए।')) ?>,
    search: <?= json_encode(t('Search', 'खोजिए')) ?>, added: <?= json_encode(t('Added ✓', 'लग गई ✓')) ?>,
    chosen: <?= json_encode(t('{n} chosen', '{n} चुनी हुई')) ?>, done: <?= json_encode(t('All done — no product left in this list 🎉', 'सब हो गया — इस सूची में कोई सामान बाकी नहीं 🎉')) ?>,
    stop: <?= json_encode(t('Stop', 'रोकिए')) ?>, start: <?= json_encode(t('Start', 'शुरू कीजिए')) ?>,
    finished: <?= json_encode(t('Finished.', 'पूरा हुआ।')) ?>, loading: <?= json_encode(t('Searching…', 'खोज रहे हैं…')) ?>,
    offline: <?= json_encode(t('Photo libraries are not reachable — check the internet.', 'फ़ोटो लाइब्रेरी नहीं खुल रही — इंटरनेट देखिए।')) ?>
  };
  var $ = function (s, r) { return (r || document).querySelector(s); };
  var grid = $('[data-ap-grid]'), typeSel = $('[data-ap-type]');
  if (!grid) return;

  // ---------- khoj ----------
  var STOP = ['and','with','for','the','of','pack','set','box','piece','pcs','pc','big','small','new','loose','fresh'];
  function clean(name) {
    return String(name).replace(/\(.*?\)/g, ' ').replace(/\b\d+(\.\d+)?\s*(kg|g|gm|gram|ml|l|ltr|litre|liter|m|cm|mm|inch|pcs|pc|x)\b/gi, ' ')
      .replace(/[^A-Za-z0-9 &'-]+/g, ' ').replace(/\s+/g, ' ').trim();
  }
  function words(q) { return q.toLowerCase().split(/[\s&'-]+/).filter(function (w) { return w.length > 2 && STOP.indexOf(w) < 0 && !/^\d+$/.test(w); }); }
  var BAD = /\b(logo|map|flag|diagram|chart|icon|coat of arms|seal|stamp|poster|cover|screenshot|graph|svg|drawing|illustration|sign|label|advert|banner|text|plate \d|portrait)\b/i;
  function score(q, title) {
    var ws = words(q); if (!ws.length) return 0;
    var t = ' ' + title.toLowerCase().replace(/[_\-.,()]/g, ' ') + ' ', hit = 0;
    ws.forEach(function (w) { var stem = w.replace(/(es|s)$/, ''); if (t.indexOf(' ' + stem) >= 0) hit++; });
    var s = hit / ws.length;
    if (BAD.test(title)) s -= 0.6;
    return s;
  }
  function licOk(l) {
    l = String(l || '').toLowerCase().trim();
    if (!l || /\bnc\b|-nc-|-nc$|\bnd\b|-nd-|-nd$/.test(l)) return false;
    return /^(cc0|cc-zero|pd|public domain|cc-by(-sa)?(-\d|$|\s)|cc by(-sa)?(\s|$)|attribution)/.test(l);
  }
  function strip(html) { var d = document.createElement('div'); d.innerHTML = html || ''; return (d.textContent || '').replace(/\s+/g, ' ').trim().slice(0, 100); }

  function commons(q) {
    var u = 'https://commons.wikimedia.org/w/api.php?action=query&format=json&origin=*&generator=search&gsrnamespace=6&gsrlimit=15'
      + '&gsrsearch=' + encodeURIComponent('filetype:bitmap ' + q)
      + '&prop=imageinfo&iiprop=url|extmetadata|mime|size&iiurlwidth=330&iiextmetadatafilter=License|LicenseShortName|Artist';
    return fetch(u).then(function (r) { return r.json(); }).then(function (j) {
      var pages = (j && j.query && j.query.pages) ? Object.keys(j.query.pages).map(function (k) { return j.query.pages[k]; }) : [];
      pages.sort(function (a, b) { return (a.index || 0) - (b.index || 0); });
      var out = [];
      pages.forEach(function (p) {
        var ii = p.imageinfo && p.imageinfo[0]; if (!ii) return;
        if (!/^image\/(jpeg|png|webp)$/.test(ii.mime || '')) return;
        if ((ii.width || 0) < 300 || (ii.height || 0) < 300) return;
        var r = ii.width / ii.height; if (r > 2.2 || r < 0.45) return;
        var em = ii.extmetadata || {}, lic = (em.License && em.License.value) || (em.LicenseShortName && em.LicenseShortName.value) || '';
        if (!licOk(lic) && !licOk(em.LicenseShortName && em.LicenseShortName.value)) return;
        var title = (p.title || '').replace(/^File:/, '').replace(/\.[a-z]+$/i, '');
        var big = ii.thumburl && ii.thumburl.indexOf('/330px-') > 0 ? ii.thumburl.replace('/330px-', '/960px-') : ii.url;
        if ((ii.width || 0) <= 960) big = ii.url;
        out.push({ source: 'commons', thumb: ii.thumburl || ii.url, url: big, page: ii.descriptionurl, title: title,
          credit: strip(em.Artist && em.Artist.value) || 'Wikimedia Commons',
          license: (em.LicenseShortName && em.LicenseShortName.value) || lic, s: score(q, title) });
      });
      return out;
    });
  }
  function off(q) {
    var u = 'https://world.openfoodfacts.org/cgi/search.pl?action=process&json=1&search_simple=1&page_size=8'
      + '&fields=code,product_name,brands,image_front_url,image_front_small_url&tagtype_0=countries&tag_contains_0=contains&tag_0=india'
      + '&search_terms=' + encodeURIComponent(q);
    return fetch(u).then(function (r) { return r.json(); }).then(function (j) {
      return (j.products || []).filter(function (p) { return p.image_front_url; }).map(function (p) {
        var title = [p.brands, p.product_name].filter(Boolean).join(' ');
        return { source: 'off', thumb: p.image_front_small_url || p.image_front_url, url: p.image_front_url,
          page: 'https://world.openfoodfacts.org/product/' + encodeURIComponent(p.code), title: title || q,
          credit: 'Open Food Facts contributors', license: 'CC BY-SA 3.0', s: score(q, title) - 0.05 };
      });
    }).catch(function () { return []; });
  }
  function find(item, q) {
    q = q || clean(item.name_en);
    var jobs = [commons(q).catch(function () { return null; })];
    if (FOOD.indexOf(item.shop_type) >= 0) jobs.push(off(q));
    return Promise.all(jobs).then(function (rs) {
      if (rs[0] === null && !(rs[1] && rs[1].length)) throw new Error('offline');
      var all = [].concat(rs[0] || [], rs[1] || []);
      all.sort(function (a, b) { return b.s - a.s; });
      return all.slice(0, 4);
    });
  }
  function sure(c) { return c && c.s >= 0.999; }

  function post(data) {
    var fd = new FormData(); fd.append('csrf', CSRF); fd.append('ajax', '1');
    Object.keys(data).forEach(function (k) { fd.append(k, data[k]); });
    return fetch(location.pathname, { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.json(); });
  }
  function take(item, c) {
    return post({ do: 'take', cat_id: item.id, url: c.url, source: c.source, credit: c.credit, license: c.license, page: c.page || '' });
  }
  function list(after, n) {
    return fetch(location.pathname + '?do=list&n=' + n + '&after=' + after + '&type=' + encodeURIComponent(typeSel.value), { credentials: 'same-origin' })
      .then(function (r) { return r.json(); });
  }
  function bump(added) {
    var w = $('[data-ap-with]'), l = $('[data-ap-left]');
    if (w) w.textContent = (parseInt(w.textContent.replace(/\D/g, ''), 10) || 0) + added;
    if (l) l.textContent = Math.max(0, (parseInt(l.textContent.replace(/\D/g, ''), 10) || 0) - 1);
  }
  function esc(s) { var d = document.createElement('div'); d.textContent = s == null ? '' : s; return d.innerHTML; }

  // ---------- 1. dekh kar lagaiye ----------
  var lastId = 0, cards = [];
  function selCount() {
    var n = grid.querySelectorAll('.ap-c.sel').length;
    $('[data-ap-sel-count]').textContent = L.chosen.replace('{n}', n);
    $('[data-ap-addall]').disabled = !n;
  }
  function render(item) {
    var el = document.createElement('article'); el.className = 'ap-card loading';
    el.innerHTML = '<header><div><b>' + esc(item.name_en) + '</b>' + (item.name_hi ? ' <span>· ' + esc(item.name_hi) + '</span>' : '') +
      '<small>' + esc(item.shop_type) + (item.sub_cat && item.sub_cat !== item.shop_type ? ' · ' + esc(item.sub_cat) : '') + ' · #' + item.id + '</small></div>' +
      '<button type="button" class="ap-skip">' + L.skip + '</button></header>' +
      '<div class="ap-cands"><i class="ap-spin"></i></div>' +
      '<form class="ap-q"><input type="search" value="' + esc(clean(item.name_en)) + '" aria-label="' + L.search + '"><button>' + L.search + '</button></form>';
    grid.appendChild(el);
    var box = el.querySelector('.ap-cands');
    function load(q) {
      el.classList.add('loading'); box.innerHTML = '<i class="ap-spin"></i>';
      find(item, q).then(function (cs) {
        el.classList.remove('loading'); el.cands = cs; box.innerHTML = '';
        if (!cs.length) { box.innerHTML = '<p class="ap-none">' + L.none + '</p>'; selCount(); return; }
        cs.forEach(function (c, i) {
          var b = document.createElement('button'); b.type = 'button';
          b.className = 'ap-c' + (i === 0 && sure(c) ? ' sel sure' : (sure(c) ? ' sure' : ''));
          b.innerHTML = '<img src="' + esc(c.thumb) + '" alt="" loading="lazy" referrerpolicy="no-referrer"><span class="ap-tag">' + (c.source === 'off' ? 'OFF' : 'Commons') + ' · ' + esc(c.license) + '</span>' +
            (sure(c) ? '<span class="ap-ok" title="' + L.pick + '">✓</span>' : '') + '<span class="ap-t">' + esc(c.title) + '</span>';
          b.addEventListener('click', function () {
            if (el.classList.contains('done')) return;
            var on = b.classList.contains('sel');
            box.querySelectorAll('.ap-c').forEach(function (x) { x.classList.remove('sel'); });
            if (!on) b.classList.add('sel');
            selCount();
          });
          b.cand = c; box.appendChild(b);
        });
        selCount();
      }).catch(function () { el.classList.remove('loading'); box.innerHTML = '<p class="ap-none">' + L.offline + '</p>'; });
    }
    el.querySelector('.ap-q').addEventListener('submit', function (e) { e.preventDefault(); load(this.querySelector('input').value.trim()); });
    el.querySelector('.ap-skip').addEventListener('click', function () {
      post({ do: 'skip', cat_id: item.id }).then(function () { el.classList.add('gone'); setTimeout(function () { el.remove(); selCount(); }, 250); bump(0); });
    });
    el.item = item; load();
    return el;
  }
  function next() {
    grid.innerHTML = ''; selCount();
    list(lastId, 12).then(function (j) {
      if (!j.items || !j.items.length) { grid.innerHTML = '<p class="ap-empty">' + L.done + '</p>'; return; }
      j.items.forEach(function (it) { lastId = Math.max(lastId, +it.id); render(it); });
    });
  }
  $('[data-ap-next]').addEventListener('click', function () { next(); window.scrollTo({ top: grid.offsetTop - 120, behavior: 'smooth' }); });
  $('[data-ap-addall]').addEventListener('click', function () {
    var btn = this, sel = Array.prototype.slice.call(grid.querySelectorAll('.ap-c.sel'));
    btn.disabled = true;
    (function go() {
      var b = sel.shift(); if (!b) { selCount(); return; }
      var card = b.closest('.ap-card'); card.classList.add('saving');
      take(card.item, b.cand).then(function (j) {
        card.classList.remove('saving');
        if (j.ok) { card.classList.add('done'); b.classList.remove('sel'); card.querySelector('header small').insertAdjacentHTML('beforeend', ' · <em>' + L.added + '</em>'); bump(1); }
        else { card.classList.add('err'); card.querySelector('header small').insertAdjacentHTML('beforeend', ' · <em class="bad">' + esc(j.msg) + '</em>'); }
        go();
      }).catch(function () { card.classList.remove('saving'); go(); });
    })();
  });
  typeSel.addEventListener('change', function () { lastId = 0; next(); });

  // ---------- 2. pura apne aap ----------
  var running = false, cnt = { added: 0, review: 0, none: 0, fail: 0 }, autoAfter = 0, doneN = 0, total = 0;
  var runBtn = $('[data-ap-run]'), logBox = $('[data-ap-log]');
  function log(html) { var p = document.createElement('div'); p.innerHTML = html; logBox.insertBefore(p, logBox.firstChild); while (logBox.children.length > 60) logBox.lastChild.remove(); }
  function count(k) { cnt[k]++; document.querySelector('[data-c="' + k + '"]').textContent = cnt[k]; }
  function auto() {
    if (!running) return;
    list(autoAfter, 20).then(function (j) {
      if (!total) total = j.left || 1;
      var items = j.items || [];
      if (!items.length) { running = false; runBtn.textContent = L.start; log('<b>' + L.finished + '</b>'); return; }
      autoAfter = +items[items.length - 1].id;
      var q = items.slice();
      function worker() {
        if (!running) return Promise.resolve();
        var it = q.shift(); if (!it) return Promise.resolve();
        return find(it).then(function (cs) {
          var c = cs[0];
          if (!c) { count('none'); log('· ' + esc(it.name_en) + ' — ' + L.none.split('.')[0]); return; }
          if (!sure(c)) { count('review'); return; }
          return take(it, c).then(function (r) {
            if (r.ok) { count('added'); bump(1); log('<img src="' + esc(c.thumb) + '" alt=""> <b>' + esc(it.name_en) + '</b> ✓ <small>' + esc(c.credit) + ' · ' + esc(c.license) + '</small>'); }
            else { count('fail'); log('✗ ' + esc(it.name_en) + ' — ' + esc(r.msg)); }
          });
        }).catch(function () { count('fail'); }).then(function () {
          doneN++; $('[data-ap-prog]').style.width = Math.min(100, doneN * 100 / total) + '%';
          return new Promise(function (ok) { setTimeout(ok, 350); });
        }).then(worker);
      }
      Promise.all([worker(), worker()]).then(auto);
    }).catch(function () { running = false; runBtn.textContent = L.start; log(L.offline); });
  }
  runBtn.addEventListener('click', function () {
    running = !running; runBtn.textContent = running ? L.stop : L.start;
    if (running) { autoAfter = 0; doneN = 0; total = 0; auto(); }
  });

  // modes
  document.querySelectorAll('[data-mode]').forEach(function (b) {
    b.addEventListener('click', function () {
      document.querySelectorAll('[data-mode]').forEach(function (x) { x.classList.toggle('on', x === b); });
      document.querySelectorAll('[data-panel]').forEach(function (p) { p.hidden = p.dataset.panel !== b.dataset.mode; });
    });
  });
  next();
})();
</script>
<?php include __DIR__ . '/../inc/foot.php'; ?>
