<?php
// ============================================================
// सामान की फ़ोटो — Admin photo manager (11 Oct 2026)
//
// * Saare saaman ek grid me: photo hai ya nahi, ek nazar me
// * Har card par "Upload" — page reload ke bina photo lag jati hai
// * Card par photo kheench kar chhodiye (drag & drop) — tab bhi lagti hai
// * Bulk: bahut saari photo ek saath; file ka naam = saaman ka naam
//   (jaise "Tata Salt.jpg", "tata-salt-2.jpg") ya saaman ka number ("1666.jpg")
// * Card dabaiye to us saaman ki saari photo — aage/peeche, hatana
// Har saaman me 7 tak photo. Pehli photo = cover (home page par dikhti hai).
// ============================================================
require_once __DIR__ . '/../inc/fn.php';
require_once __DIR__ . '/../inc/catalog.php';
require_once __DIR__ . '/../inc/catalog-photos.php';
$u = need_role(['admin', 'designer']);
$page_title = 'सामान की फ़ोटो — Maakit Admin';

$table_ok = true;
try { $pdo->query("SELECT 1 FROM catalog_photos LIMIT 1"); } catch (Throwable $e) { $table_ok = false; }

function cp_list(PDO $pdo, $id) {
    $st = $pdo->prepare("SELECT id, file, sort_no FROM catalog_photos WHERE cat_id=? ORDER BY sort_no, id");
    $st->execute([$id]);
    return $st->fetchAll();
}
function cp_renumber(PDO $pdo, $id) {
    $n = 0;
    foreach (cp_list($pdo, $id) as $r) {
        $pdo->prepare("UPDATE catalog_photos SET sort_no=? WHERE id=?")->execute([++$n * 10, (int)$r['id']]);
    }
}
function cp_norm($s) {
    $s = mb_strtolower(trim((string)$s), 'UTF-8');
    $s = preg_replace('/[^\p{L}\p{N}]+/u', '', $s);
    return $s ?? '';
}
/** ek file ko ek saaman me jodo; [ok, msg] */
function cp_add_file(PDO $pdo, $catId, $tmp, $size, $uid) {
    $have = count(cp_list($pdo, $catId));
    if ($have >= CATALOG_PHOTO_MAX) return [false, 'पहले से ' . CATALOG_PHOTO_MAX . ' फ़ोटो हैं'];
    $saved = save_photo_from($tmp, $size, 'cph', 900);
    if (!$saved) return [false, 'फ़ोटो नहीं है या 8 MB से बड़ी है'];
    $pdo->prepare("INSERT INTO catalog_photos (cat_id, file, sort_no, added_by) VALUES (?,?,?,?)")
        ->execute([$catId, $saved, 1000 + $have, (int)$uid]);
    cp_renumber($pdo, $catId);
    return [true, $saved];
}
function cp_json($a) { header('Content-Type: application/json; charset=utf-8'); echo json_encode($a, JSON_UNESCAPED_UNICODE); exit; }

// ---------------- AJAX: upload (ek saaman) aur bulk (naam se) ----------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('ajax') === '1') {
    if (!csrf_ok()) cp_json(['ok' => false, 'msg' => 'पेज पुराना हो गया — दोबारा खोलिए']);
    if (!$table_ok) cp_json(['ok' => false, 'msg' => 'पहले Update website चलाइए (फ़ोटो की table नहीं है)']);
    $files = $_FILES['photos'] ?? null;
    if (!$files || !is_array($files['name'])) cp_json(['ok' => false, 'msg' => 'कोई फ़ोटो नहीं आई']);

    if (post('do') === 'upload') {
        $id = (int)post('cat_id');
        $chk = $pdo->prepare("SELECT id FROM catalog_items WHERE id=?"); $chk->execute([$id]);
        if (!$chk->fetch()) cp_json(['ok' => false, 'msg' => 'सामान नहीं मिला']);
        $added = 0; $err = '';
        foreach ($files['name'] as $i => $nm) {
            if (($files['error'][$i] ?? 4) !== UPLOAD_ERR_OK) { $err = 'फ़ाइल नहीं चढ़ी'; continue; }
            [$ok, $m] = cp_add_file($pdo, $id, $files['tmp_name'][$i], $files['size'][$i], $u['id']);
            if ($ok) $added++; else $err = $m;
        }
        $list = cp_list($pdo, $id);
        cp_json(['ok' => $added > 0, 'added' => $added, 'count' => count($list),
                 'cover' => $list ? '/uploads/' . $list[0]['file'] : null, 'msg' => $added ? "$added फ़ोटो लगी" : $err]);
    }

    if (post('do') === 'bulk') {
        $map = []; $byId = [];
        foreach ($pdo->query("SELECT id, name_en, name_hi FROM catalog_items") as $r) {
            $byId[(int)$r['id']] = $r;
            foreach ([$r['name_en'], $r['name_hi']] as $n) { $k = cp_norm($n); if ($k !== '' && !isset($map[$k])) $map[$k] = (int)$r['id']; }
        }
        $out = [];
        foreach ($files['name'] as $i => $nm) {
            $base = pathinfo((string)$nm, PATHINFO_FILENAME);
            $base = preg_replace('/(\s*\(\d+\)|[\s_\-]+\d{1,2})$/u', '', $base);   // "atta (2)", "atta-3"
            $id = 0;
            if (preg_match('/^0*(\d{1,7})$/', trim($base), $m) && isset($byId[(int)$m[1]])) $id = (int)$m[1];
            elseif (isset($map[cp_norm($base)])) $id = $map[cp_norm($base)];
            if (!$id) { $out[] = ['file' => $nm, 'ok' => false, 'msg' => 'नाम से सामान नहीं मिला']; continue; }
            if (($files['error'][$i] ?? 4) !== UPLOAD_ERR_OK) { $out[] = ['file' => $nm, 'ok' => false, 'msg' => 'फ़ाइल नहीं चढ़ी']; continue; }
            [$ok, $m] = cp_add_file($pdo, $id, $files['tmp_name'][$i], $files['size'][$i], $u['id']);
            $out[] = ['file' => $nm, 'ok' => $ok, 'msg' => $ok ? '→ ' . $byId[$id]['name_en'] : $m, 'id' => $id];
        }
        cp_json(['ok' => true, 'results' => $out]);
    }
    cp_json(['ok' => false, 'msg' => 'गलत काम']);
}

// ---------------- detail page: hatana / aage-peeche ----------------
if ($table_ok && $_SERVER['REQUEST_METHOD'] === 'POST' && csrf_ok()) {
    $id = (int)post('cat_id'); $do = post('do');
    if ($do === 'del') {
        $st = $pdo->prepare("SELECT file FROM catalog_photos WHERE id=? AND cat_id=?");
        $st->execute([(int)post('pid'), $id]);
        if ($row = $st->fetch()) {
            $pdo->prepare("DELETE FROM catalog_photos WHERE id=?")->execute([(int)post('pid')]);
            drop_photo($row['file']); cp_renumber($pdo, $id); flash('फ़ोटो हटा दी।');
        }
    } elseif ($do === 'move' || $do === 'cover') {
        $list = cp_list($pdo, $id); $pid = (int)post('pid');
        if ($do === 'cover') {
            foreach ($list as $r) if ((int)$r['id'] === $pid) { $pdo->prepare("UPDATE catalog_photos SET sort_no=0 WHERE id=?")->execute([$pid]); }
            cp_renumber($pdo, $id); flash('कवर फ़ोटो बदल दी।');
        } else {
            $dir = post('dir') === 'left' ? -1 : 1;
            foreach ($list as $i => $r) {
                if ((int)$r['id'] === $pid && isset($list[$i + $dir])) {
                    $o = $list[$i + $dir];
                    $pdo->prepare("UPDATE catalog_photos SET sort_no=? WHERE id=?")->execute([(int)$o['sort_no'], $pid]);
                    $pdo->prepare("UPDATE catalog_photos SET sort_no=? WHERE id=?")->execute([(int)$r['sort_no'], (int)$o['id']]);
                    break;
                }
            }
        }
    }
    redirect('/admin/product-photos.php?id=' . $id);
}

// ---------------- list ----------------
$id   = (int)($_GET['id'] ?? 0);
$q    = trim((string)($_GET['q'] ?? ''));
$cat  = trim((string)($_GET['cat'] ?? ''));
$f    = in_array($_GET['f'] ?? '', ['missing', 'has'], true) ? $_GET['f'] : 'all';
$page = max(1, (int)($_GET['p'] ?? 1)); $per = 48;

$types = $pdo->query("SELECT shop_type, COUNT(*) n FROM catalog_items GROUP BY shop_type ORDER BY n DESC")->fetchAll();
$total = 0; foreach ($types as $t) $total += (int)$t['n'];
$withPhotos = 0;
if ($table_ok) $withPhotos = (int)$pdo->query("SELECT COUNT(DISTINCT cat_id) FROM catalog_photos")->fetchColumn();

$where = []; $args = [];
if ($q !== '') { $where[] = '(c.name_en LIKE ? OR c.name_hi LIKE ? OR c.id=?)'; $args[] = "%$q%"; $args[] = "%$q%"; $args[] = (int)$q; }
if ($cat !== '') { $where[] = 'c.shop_type=?'; $args[] = $cat; }
$pc = $table_ok ? '(SELECT COUNT(*) FROM catalog_photos p WHERE p.cat_id=c.id)' : '0';
$cv = $table_ok ? '(SELECT p.file FROM catalog_photos p WHERE p.cat_id=c.id ORDER BY p.sort_no, p.id LIMIT 1)' : 'NULL';
if ($f === 'missing') $where[] = "$pc=0";
if ($f === 'has') $where[] = "$pc>0";
$w = $where ? 'WHERE ' . implode(' AND ', $where) : '';
$cnt = $pdo->prepare("SELECT COUNT(*) FROM catalog_items c $w"); $cnt->execute($args); $found = (int)$cnt->fetchColumn();
$pages = max(1, (int)ceil($found / $per)); $page = min($page, $pages);
$st = $pdo->prepare("SELECT c.id, c.name_en, c.name_hi, c.shop_type, c.unit_hint, $pc AS n, $cv AS cover FROM catalog_items c $w
                     ORDER BY CASE WHEN c.id IN (1517,1659,1666,1669,1671,1678) THEN 0 ELSE 1 END, c.shop_type, c.sort_no, c.id
                     LIMIT $per OFFSET " . (($page - 1) * $per));
$st->execute($args); $rows = $st->fetchAll();

$item = null; $photos = [];
if ($id > 0) {
    $s2 = $pdo->prepare("SELECT * FROM catalog_items WHERE id=?"); $s2->execute([$id]);
    $item = $s2->fetch() ?: null;
    if ($item && $table_ok) $photos = cp_list($pdo, $id);
}
function pp_url(array $over) {
    $p = array_merge(['q' => $_GET['q'] ?? '', 'cat' => $_GET['cat'] ?? '', 'f' => $_GET['f'] ?? '', 'p' => ''], $over);
    $p = array_filter($p, fn($v) => $v !== '' && $v !== null && $v !== 'all');
    return '/admin/product-photos.php' . ($p ? '?' . http_build_query($p) : '');
}

include __DIR__ . '/../inc/panel.php';
?>
<section class="ph-page">
<div class="mk-wrap">

  <div class="ph-top">
    <div>
      <h1>सामान की फ़ोटो</h1>
      <p>हर सामान की <?= CATALOG_PHOTO_MAX ?> तक फ़ोटो। पहली फ़ोटो कवर है — वही home page पर दिखती है।</p>
    </div>
    <div class="ph-stats">
      <span><b><?= num($total) ?></b>कुल सामान</span>
      <span class="ok"><b><?= num($withPhotos) ?></b>फ़ोटो वाले</span>
      <span class="warn"><b><?= num(max(0, $total - $withPhotos)) ?></b>बिना फ़ोटो</span>
    </div>
  </div>
  <div class="ph-bar" aria-hidden="true"><i style="width:<?= $total ? round($withPhotos * 100 / $total, 1) : 0 ?>%"></i></div>

  <?php if (!$table_ok): ?>
    <div class="err">फ़ोटो की table अभी नहीं बनी। Dashboard पर <b>Update website</b> दबाइए, फिर यह page दोबारा खोलिए।</div>
  <?php endif; ?>

  <?php if ($item): ?>
  <div class="ph-detail">
    <div class="ph-detail-head">
      <a class="ph-back" href="<?= h(pp_url([])) ?>">← सारा सामान</a>
      <div><h2><?= h($item['name_en']) ?><?php if ($item['name_hi']): ?> <span>· <?= h($item['name_hi']) ?></span><?php endif; ?></h2>
        <small><?= h($item['shop_type']) ?> · <?= h($item['unit_hint']) ?> · #<?= (int)$item['id'] ?> · <?= count($photos) ?>/<?= CATALOG_PHOTO_MAX ?> फ़ोटो</small></div>
      <a class="mk-btn mk-btn-sm mk-btn-line" target="_blank" href="<?= h(catalog_url(['product' => (int)$item['id']])) ?>">ग्राहक का page ↗</a>
    </div>
    <div class="ph-detail-grid">
      <?php foreach ($photos as $i => $p): ?>
        <div class="ph-shot">
          <img src="/uploads/<?= h($p['file']) ?>" alt="फ़ोटो <?= $i + 1 ?>">
          <span class="ph-no"><?= $i === 0 ? 'कवर' : $i + 1 ?></span>
          <div class="ph-act">
            <?php if ($i > 0): ?>
              <form method="post"><input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="do" value="cover"><input type="hidden" name="cat_id" value="<?= (int)$item['id'] ?>"><input type="hidden" name="pid" value="<?= (int)$p['id'] ?>"><button title="कवर बनाइए">★</button></form>
              <form method="post"><input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="do" value="move"><input type="hidden" name="dir" value="left"><input type="hidden" name="cat_id" value="<?= (int)$item['id'] ?>"><input type="hidden" name="pid" value="<?= (int)$p['id'] ?>"><button title="आगे लाइए">←</button></form>
            <?php endif; ?>
            <?php if ($i < count($photos) - 1): ?>
              <form method="post"><input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="do" value="move"><input type="hidden" name="dir" value="right"><input type="hidden" name="cat_id" value="<?= (int)$item['id'] ?>"><input type="hidden" name="pid" value="<?= (int)$p['id'] ?>"><button title="पीछे भेजिए">→</button></form>
            <?php endif; ?>
            <form method="post" onsubmit="return confirm('यह फ़ोटो हटा दें?')"><input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="do" value="del"><input type="hidden" name="cat_id" value="<?= (int)$item['id'] ?>"><input type="hidden" name="pid" value="<?= (int)$p['id'] ?>"><button class="del" title="हटाइए">✕</button></form>
          </div>
        </div>
      <?php endforeach; ?>
      <?php if ($table_ok && count($photos) < CATALOG_PHOTO_MAX): ?>
        <label class="ph-shot ph-add" data-drop data-id="<?= (int)$item['id'] ?>" data-reload="1">
          <input type="file" accept="image/jpeg,image/png,image/webp" multiple data-up data-id="<?= (int)$item['id'] ?>" data-reload="1">
          <span class="ph-plus">+</span><b>फ़ोटो जोड़िए</b><small>चुनिए या यहाँ खींचकर छोड़िए</small>
        </label>
      <?php endif; ?>
    </div>
  </div>
  <?php endif; ?>

  <details class="ph-bulk">
    <summary><b>बहुत सारी फ़ोटो एक साथ</b><span>फ़ाइल का नाम = सामान का नाम (जैसे <code>Tata Salt.jpg</code>, <code>tata salt-2.jpg</code>) या सामान का नंबर (<code>1666.jpg</code>)</span></summary>
    <label class="ph-dropzone" data-bulk>
      <input type="file" accept="image/jpeg,image/png,image/webp" multiple data-bulk-input>
      <span class="ph-plus">⇪</span><b>फ़ोटो चुनिए या यहाँ खींचकर छोड़िए</b><small>एक बार में 100 तक · हर फ़ोटो अपने नाम वाले सामान में लग जाएगी</small>
    </label>
    <ol class="ph-log" id="bulkLog"></ol>
  </details>

  <form class="ph-filters" method="get">
    <input type="search" name="q" value="<?= h($q) ?>" placeholder="सामान खोजिए: atta, चीनी, 1666…" aria-label="खोजिए">
    <select name="cat" aria-label="श्रेणी">
      <option value="">सारी श्रेणियाँ</option>
      <?php foreach ($types as $t): ?><option value="<?= h($t['shop_type']) ?>"<?= $cat === $t['shop_type'] ? ' selected' : '' ?>><?= h($t['shop_type']) ?> (<?= (int)$t['n'] ?>)</option><?php endforeach; ?>
    </select>
    <div class="ph-seg" role="group" aria-label="दिखाइए">
      <?php foreach (['all' => 'सब', 'missing' => 'बिना फ़ोटो', 'has' => 'फ़ोटो वाले'] as $k => $l): ?>
        <a href="<?= h(pp_url(['f' => $k, 'p' => ''])) ?>" class="<?= $f === $k ? 'on' : '' ?>"><?= $l ?></a>
      <?php endforeach; ?>
    </div>
    <?php if ($f !== 'all'): ?><input type="hidden" name="f" value="<?= h($f) ?>"><?php endif; ?>
    <button class="mk-btn mk-btn-sm">खोजिए</button>
  </form>
  <p class="ph-count"><?= num($found) ?> सामान<?= $pages > 1 ? ' · पन्ना ' . $page . ' / ' . $pages : '' ?></p>

  <div class="ph-grid">
    <?php foreach ($rows as $r): $n = (int)$r['n']; ?>
      <div class="ph-card<?= $item && (int)$item['id'] === (int)$r['id'] ? ' on' : '' ?>" data-drop data-id="<?= (int)$r['id'] ?>">
        <a class="ph-thumb" href="<?= h(pp_url(['p' => $page > 1 ? $page : '', 'id' => (int)$r['id']])) ?>">
          <?php if ($r['cover']): ?><img src="/uploads/<?= h($r['cover']) ?>" alt="" loading="lazy"><?php else: ?><span class="ph-empty">📷</span><?php endif; ?>
          <span class="ph-badge<?= $n >= 4 ? ' ok' : ($n ? ' mid' : '') ?>" data-count><?= $n ?>/<?= CATALOG_PHOTO_MAX ?></span>
        </a>
        <div class="ph-info">
          <b title="<?= h($r['name_en']) ?>"><?= h($r['name_en']) ?></b>
          <small><?= h($r['name_hi'] ?: $r['shop_type']) ?></small>
        </div>
        <label class="ph-upbtn">
          <input type="file" accept="image/jpeg,image/png,image/webp" multiple data-up data-id="<?= (int)$r['id'] ?>">
          <span data-label>+ फ़ोटो</span>
        </label>
      </div>
    <?php endforeach; ?>
  </div>
  <?php if (!$rows): ?><div class="box">कोई सामान नहीं मिला।</div><?php endif; ?>

  <?php if ($pages > 1): ?>
  <nav class="ph-pages">
    <?php if ($page > 1): ?><a href="<?= h(pp_url(['p' => $page - 1])) ?>">← पिछला</a><?php endif; ?>
    <span><?= $page ?> / <?= $pages ?></span>
    <?php if ($page < $pages): ?><a href="<?= h(pp_url(['p' => $page + 1])) ?>">अगला →</a><?php endif; ?>
  </nav>
  <?php endif; ?>
</div>
</section>

<script>
(function(){
  var CSRF = <?= json_encode(csrf()) ?>, URL_ = '/admin/product-photos.php';
  function send(fd){ fd.append('csrf', CSRF); fd.append('ajax','1');
    return fetch(URL_, {method:'POST', body:fd, credentials:'same-origin'}).then(function(r){ return r.json(); }); }

  function upload(id, files, card, reload){
    if (!files || !files.length) return;
    var lbl = card && card.querySelector('[data-label]');
    if (card) card.classList.add('busy'); if (lbl) lbl.textContent = 'लग रही है…';
    var fd = new FormData(); fd.append('do','upload'); fd.append('cat_id', id);
    for (var i=0;i<files.length;i++) fd.append('photos[]', files[i]);
    send(fd).then(function(d){
      if (reload && d.ok) { location.reload(); return; }
      if (card) {
        card.classList.remove('busy');
        if (d.ok) {
          var th = card.querySelector('.ph-thumb'), img = th.querySelector('img');
          if (!img) { img = document.createElement('img'); var e = th.querySelector('.ph-empty'); if (e) e.remove(); th.insertBefore(img, th.firstChild); }
          if (d.cover) img.src = d.cover;
          var b = card.querySelector('[data-count]'); b.textContent = d.count + '/<?= CATALOG_PHOTO_MAX ?>';
          b.className = 'ph-badge' + (d.count >= 4 ? ' ok' : ' mid');
          card.classList.add('done'); setTimeout(function(){ card.classList.remove('done'); }, 1500);
        }
        if (lbl) lbl.textContent = d.ok ? '✓ ' + d.msg : '✕ ' + d.msg;
        setTimeout(function(){ if (lbl) lbl.textContent = '+ फ़ोटो'; }, 2500);
      } else if (!d.ok) alert(d.msg);
    }).catch(function(){ if (card) card.classList.remove('busy'); if (lbl) lbl.textContent = '✕ नेट की दिक्कत'; });
  }

  document.addEventListener('change', function(e){
    var inp = e.target.closest('[data-up]'); if (!inp) return;
    upload(inp.getAttribute('data-id'), inp.files, inp.closest('.ph-card'), inp.hasAttribute('data-reload'));
    inp.value = '';
  });
  document.querySelectorAll('[data-drop]').forEach(function(el){
    el.addEventListener('dragover', function(e){ e.preventDefault(); el.classList.add('over'); });
    el.addEventListener('dragleave', function(){ el.classList.remove('over'); });
    el.addEventListener('drop', function(e){ e.preventDefault(); el.classList.remove('over');
      upload(el.getAttribute('data-id'), e.dataTransfer.files, el.classList.contains('ph-card') ? el : null, el.hasAttribute('data-reload')); });
  });

  // bulk — ek-ek karke bhejte hain taaki dheeme net par bhi chale
  var log = document.getElementById('bulkLog'), zone = document.querySelector('[data-bulk]');
  function bulk(files){
    var list = Array.prototype.slice.call(files, 0, 100), i = 0, ok = 0;
    log.innerHTML = '';
    (function next(){
      if (i >= list.length) { var li = document.createElement('li'); li.className='is-sum'; li.textContent = ok + ' / ' + list.length + ' फ़ोटो लग गईं'; log.appendChild(li); return; }
      var f = list[i++], li = document.createElement('li'); li.textContent = f.name + ' …'; log.appendChild(li);
      var fd = new FormData(); fd.append('do','bulk'); fd.append('photos[]', f, f.name);
      send(fd).then(function(d){
        var r = (d.results || [])[0] || {ok:false, msg:d.msg};
        li.className = r.ok ? 'is-ok' : 'is-bad'; li.textContent = f.name + '  ' + (r.ok ? '✓ ' : '✕ ') + r.msg; if (r.ok) ok++; next();
      }).catch(function(){ li.className='is-bad'; li.textContent = f.name + '  ✕ नेट की दिक्कत'; next(); });
    })();
  }
  if (zone) {
    zone.querySelector('[data-bulk-input]').addEventListener('change', function(){ bulk(this.files); this.value=''; });
    zone.addEventListener('dragover', function(e){ e.preventDefault(); zone.classList.add('over'); });
    zone.addEventListener('dragleave', function(){ zone.classList.remove('over'); });
    zone.addEventListener('drop', function(e){ e.preventDefault(); zone.classList.remove('over'); bulk(e.dataTransfer.files); });
  }
})();
</script>
<?php include __DIR__ . '/../inc/foot.php'; ?>
