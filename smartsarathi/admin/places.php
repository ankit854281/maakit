<?php
// ============================================================
//  भदोही की जगहें — खोज में सबसे ऊपर (★) दिखती हैं
//  Dafter wahan khade hokar "मेरी अभी की जगह" se jagah jodta hai,
//  ya Google Maps ka link chipka deta hai. Google se chuni gayi
//  saarvajanik jagahein bhi yahan khud aa jaati hain (src=google).
// ============================================================
require_once __DIR__ . '/../inc/public.php';
require_once __DIR__ . '/../inc/identity.php';
require_once __DIR__ . '/../inc/places.php';
header('Cache-Control: private, no-store');
if (!sr_account($pdo)) redirect('/');
if (!sr_identity_admin($pdo)) { http_response_code(403); exit; }
sr_places_upgrade($pdo);
$err = ''; $ok = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_ok()) $err = t('Page expired. Reload and try again.', 'पेज पुराना हो गया। दोबारा खोलकर कोशिश कीजिए।');
    elseif (post('do') === 'add') {
        $name = trim((string)post('name')); $hi = trim((string)post('name_hi'));
        $ll = sr_parse_latlng((string)post('where'));
        if (mb_strlen($name) < 2 && mb_strlen($hi) < 2) $err = t('Write the place name.', 'जगह का नाम लिखिए।');
        elseif (!$ll) $err = t('Location missing. Tap “My current location” or paste a Google Maps link.', 'जगह नहीं मिली। “मेरी अभी की जगह” दबाइए या Google Maps का लिंक चिपकाइए।');
        else {
            $pdo->prepare("INSERT INTO sr_places (name,name_hi,alias,area,lat,lng,src,uses) VALUES (?,?,?,?,?,?,'office',5)")
                ->execute([mb_substr($name !== '' ? $name : $hi, 0, 120), mb_substr($hi, 0, 120), mb_substr(trim((string)post('alias')), 0, 255), mb_substr(trim((string)post('area')), 0, 120), $ll[0], $ll[1]]);
            flash(t('Place added ✓', 'जगह जुड़ गई ✓')); redirect('/admin/places.php');
        }
    } elseif (post('do') === 'toggle') {
        $pdo->prepare('UPDATE sr_places SET active=1-active WHERE id=?')->execute([(int)post('id')]);
        redirect('/admin/places.php?q=' . rawurlencode((string)post('q')));
    }
}
$ok = flash();
$q = trim((string)get('q'));
if ($q !== '') { $st = $pdo->prepare("SELECT * FROM sr_places WHERE name LIKE ? OR name_hi LIKE ? OR alias LIKE ? ORDER BY active DESC, uses DESC LIMIT 60"); $like = '%' . $q . '%'; $st->execute([$like, $like, $like]); }
else $st = $pdo->query("SELECT * FROM sr_places ORDER BY created_at DESC LIMIT 60");
$rows = $st->fetchAll();
$total = (int)$pdo->query("SELECT COUNT(*) FROM sr_places WHERE active=1")->fetchColumn();
sr_customer_head(t('Bhadohi places', 'भदोही की जगहें'), t('These show first (★) when customers search.', 'ग्राहक खोजे तो ये सबसे ऊपर (★) दिखती हैं।'));
?>
<section class="sx-card sx-form">
<a href="<?= h(u('/admin/')) ?>"><?= h(t('Admin panel', 'एडमिन पैनल')) ?></a>
<?php if ($err): ?><p role="alert"><?= h($err) ?></p><?php endif; ?>
<?php if ($ok): ?><p role="status"><?= h($ok) ?></p><?php endif; ?>
<h2><?= h(t('Add a place', 'नई जगह जोड़िए')) ?></h2>
<p style="color:#5A6878;margin:0"><?= h(t('Best: stand at the place and tap “My current location”.', 'सबसे अच्छा: उस जगह पर खड़े होकर “मेरी अभी की जगह” दबाइए।')) ?></p>
<form method="post">
<input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="do" value="add">
<label class="sx-field"><span><?= h(t('Name in Hindi', 'हिंदी में नाम')) ?></span><input name="name_hi" maxlength="120" placeholder="राम जानकी मंदिर"></label>
<label class="sx-field"><span><?= h(t('Name in English', 'अंग्रेज़ी में नाम')) ?></span><input name="name" maxlength="120" placeholder="Ram Janki Mandir"></label>
<label class="sx-field"><span><?= h(t('Other names people say (comma)', 'लोग और किस नाम से बोलते हैं (कॉमा से)')) ?></span><input name="alias" maxlength="255" placeholder="mandir, ram janki, रामजानकी"></label>
<label class="sx-field"><span><?= h(t('Area / mohalla', 'इलाका / मोहल्ला')) ?></span><input name="area" maxlength="120" placeholder="स्टेशन रोड, भदोही"></label>
<label class="sx-field"><span><?= h(t('Location (lat,lng or Google Maps link)', 'जगह (lat,lng या Google Maps लिंक)')) ?></span><input name="where" id="where" required placeholder="25.3951, 82.5682"></label>
<button type="button" class="sx-pill" id="here"><?= h(t('📍 My current location', '📍 मेरी अभी की जगह')) ?></button>
<p id="here-msg" style="color:#5A6878;margin:4px 0 0"></p>
<button class="sx-btn sx-btn-mint" type="submit"><?= h(t('Add place', 'जगह जोड़िए')) ?></button>
</form>

<h2><?= h(t('Places', 'जगहें')) ?> (<?= $total ?>)</h2>
<form method="get"><label class="sx-field"><span><?= h(t('Search', 'खोजिए')) ?></span><input name="q" value="<?= h($q) ?>"></label></form>
<?php foreach ($rows as $r): ?>
<article class="sx-card" style="<?= $r['active'] ? '' : 'opacity:.55' ?>">
  <b><?= h($r['name_hi'] !== '' ? $r['name_hi'] : $r['name']) ?></b><?= $r['name_hi'] !== '' && $r['name'] !== $r['name_hi'] ? ' · ' . h($r['name']) : '' ?>
  <small style="display:block;color:#5A6878"><?= h($r['area']) ?> · <?= h($r['src'] === 'google' ? t('from customer search', 'ग्राहक की खोज से') : t('office', 'दफ़्तर')) ?> · <?= (int)$r['uses'] ?> <?= h(t('uses', 'बार')) ?> ·
    <a target="_blank" rel="noopener" href="https://www.google.com/maps?q=<?= h($r['lat'] . ',' . $r['lng']) ?>"><?= h(t('see on map', 'नक़्शे पर देखिए')) ?></a></small>
  <form method="post" style="margin-top:6px"><input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="do" value="toggle"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="q" value="<?= h($q) ?>">
  <button class="sx-pill"><?= h($r['active'] ? t('Hide from search', 'खोज से हटाइए') : t('Show again', 'फिर दिखाइए')) ?></button></form>
</article>
<?php endforeach; ?>
</section>
<script>
document.getElementById('here').addEventListener('click', function () {
  var m = document.getElementById('here-msg');
  if (!navigator.geolocation) { m.textContent = 'GPS नहीं मिला'; return; }
  m.textContent = '…';
  navigator.geolocation.getCurrentPosition(function (p) {
    document.getElementById('where').value = p.coords.latitude.toFixed(6) + ', ' + p.coords.longitude.toFixed(6);
    m.textContent = '± ' + Math.round(p.coords.accuracy) + ' m';
  }, function () { m.textContent = 'GPS चालू कीजिए और अनुमति दीजिए'; }, { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 });
});
</script>
<?php sr_customer_foot(); ?>
