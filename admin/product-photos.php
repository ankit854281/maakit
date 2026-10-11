<?php
// ============================================================
// सामान की फ़ोटो (स्लाइड) — har saaman ki 7 tak photo.
// Pehli photo home page ke card par, sab photo product page par
// slide hoti hain. Admin aur designer dono laga sakte hain.
// ============================================================
require_once __DIR__ . '/../inc/fn.php';
require_once __DIR__ . '/../inc/catalog.php';
require_once __DIR__ . '/../inc/catalog-photos.php';
$u = need_role(['admin', 'designer']);
$page_title = 'सामान की फ़ोटो (स्लाइड) — Maakit';

$id = (int)($_GET['id'] ?? 0);
$q  = trim((string)($_GET['q'] ?? ''));

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

if ($table_ok && $_SERVER['REQUEST_METHOD'] === 'POST' && csrf_ok()) {
    $id = (int)post('cat_id');
    $do = post('do');
    $chk = $pdo->prepare("SELECT id FROM catalog_items WHERE id=?"); $chk->execute([$id]);
    if (!$chk->fetch()) { flash('सामान नहीं मिला।'); redirect('/admin/product-photos.php'); }

    if ($do === 'upload') {
        $have = count(cp_list($pdo, $id));
        $files = $_FILES['photos'] ?? null;
        $added = 0; $bad = 0; $over = 0;
        if ($files && is_array($files['name'])) {
            foreach ($files['name'] as $i => $nm) {
                if (($files['error'][$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) { if (($files['error'][$i] ?? 4) !== UPLOAD_ERR_NO_FILE) $bad++; continue; }
                if ($have + $added >= CATALOG_PHOTO_MAX) { $over++; continue; }
                $saved = save_photo_from($files['tmp_name'][$i], $files['size'][$i], 'cph', 900);
                if (!$saved) { $bad++; continue; }
                $pdo->prepare("INSERT INTO catalog_photos (cat_id, file, sort_no, added_by) VALUES (?,?,?,?)")
                    ->execute([$id, $saved, 1000 + $added, (int)$u['id']]);
                $added++;
            }
        }
        cp_renumber($pdo, $id);
        $msg = $added ? "$added फ़ोटो लग गई।" : 'कोई फ़ोटो नहीं लगी।';
        if ($bad)  $msg .= " $bad फ़ाइल फ़ोटो नहीं थी या 8 MB से बड़ी थी।";
        if ($over) $msg .= ' एक सामान में ' . CATALOG_PHOTO_MAX . ' से ज़्यादा फ़ोटो नहीं लगती।';
        flash($msg);
    } elseif ($do === 'del') {
        $st = $pdo->prepare("SELECT file FROM catalog_photos WHERE id=? AND cat_id=?");
        $st->execute([(int)post('pid'), $id]);
        if ($row = $st->fetch()) {
            $pdo->prepare("DELETE FROM catalog_photos WHERE id=?")->execute([(int)post('pid')]);
            drop_photo($row['file']);
            cp_renumber($pdo, $id);
            flash('फ़ोटो हटा दी।');
        }
    } elseif ($do === 'move') {
        $list = cp_list($pdo, $id);
        $pid = (int)post('pid'); $dir = post('dir') === 'left' ? -1 : 1;
        foreach ($list as $i => $r) {
            if ((int)$r['id'] === $pid && isset($list[$i + $dir])) {
                $o = $list[$i + $dir];
                $pdo->prepare("UPDATE catalog_photos SET sort_no=? WHERE id=?")->execute([(int)$o['sort_no'], $pid]);
                $pdo->prepare("UPDATE catalog_photos SET sort_no=? WHERE id=?")->execute([(int)$r['sort_no'], (int)$o['id']]);
                break;
            }
        }
    }
    redirect('/admin/product-photos.php?id=' . $id);
}

$item = null; $photos = [];
if ($id > 0) {
    $st = $pdo->prepare("SELECT * FROM catalog_items WHERE id=?"); $st->execute([$id]);
    $item = $st->fetch() ?: null;
    if ($item && $table_ok) $photos = cp_list($pdo, $id);
}

$results = [];
if ($q !== '') {
    $like = '%' . $q . '%';
    $st = $pdo->prepare("SELECT id, name_en, name_hi, shop_type, unit_hint FROM catalog_items
                         WHERE name_en LIKE ? OR name_hi LIKE ? OR id=? ORDER BY sort_no, id LIMIT 40");
    $st->execute([$like, $like, (int)$q]);
    $results = $st->fetchAll();
} elseif (!$item) {
    // home page par jo saaman dikhta hai, wahi pehle
    $results = $pdo->query("SELECT id, name_en, name_hi, shop_type, unit_hint FROM catalog_items
        WHERE shop_type IN ('Grocery / Kirana Store','Sweet Shop')
        ORDER BY CASE WHEN id IN (1517,1659,1666,1669,1671,1678) THEN 0 ELSE 1 END, sort_no, id LIMIT 12")->fetchAll();
}
$counts = $table_ok ? catalog_photos_for($pdo, array_column($results, 'id')) : [];

include __DIR__ . '/../inc/panel.php';
?>
<section>
<div class="wrap" style="max-width:820px">
  <h2>सामान की फ़ोटो (स्लाइड)</h2>
  <p class="lead">हर सामान की <?= CATALOG_PHOTO_MAX ?> तक फ़ोटो लगाइए। पहली फ़ोटो home page के card पर दिखेगी,
    सारी फ़ोटो सामान के page पर स्लाइड होंगी।</p>

  <?php if (!$table_ok): ?>
    <div class="err">फ़ोटो की table अभी database में नहीं है। पहले update link एक बार खोलिए
      (वो <code>sql/031-catalog-photos.sql</code> चला देगा), फिर यह page दोबारा खोलिए।</div>
  <?php endif; ?>

  <form method="get" class="pp-search">
    <input type="search" name="q" value="<?= h($q) ?>" placeholder="सामान खोजिए: atta, चीनी, maggi…" aria-label="सामान खोजिए">
    <button class="btn btn-brand">खोजिए</button>
  </form>

  <?php if ($item): ?>
    <div class="box pp-item">
      <div class="pp-head">
        <div>
          <b><?= h($item['name_en']) ?></b><?php if ($item['name_hi']): ?> <span class="meta">· <?= h($item['name_hi']) ?></span><?php endif; ?>
          <div class="meta"><?= h($item['shop_type']) ?> · <?= h($item['unit_hint']) ?> · #<?= (int)$item['id'] ?></div>
        </div>
        <a class="btn btn-sm btn-line" target="_blank" href="<?= h(catalog_url(['product' => (int)$item['id']])) ?>">ग्राहक का page देखिए ↗</a>
      </div>

      <p class="pp-count"><b><?= count($photos) ?> / <?= CATALOG_PHOTO_MAX ?></b> फ़ोटो लगी हैं
        <?= count($photos) < 4 ? '— कम से कम 4 लगाइए: सामने, पीछे, सारे पैक, दुकान की शेल्फ़।' : '' ?></p>

      <?php if ($photos): ?>
      <div class="pp-grid">
        <?php foreach ($photos as $i => $p): ?>
          <div class="pp-ph">
            <span class="pp-no"><?= $i + 1 ?><?= $i === 0 ? ' · कवर' : '' ?></span>
            <img src="/uploads/<?= h($p['file']) ?>" alt="फ़ोटो <?= $i + 1 ?>" loading="lazy">
            <div class="pp-act">
              <?php foreach ([['left', '←', $i > 0], ['right', '→', $i < count($photos) - 1]] as [$dir, $lbl, $show]): if (!$show) continue; ?>
                <form method="post"><input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="do" value="move">
                  <input type="hidden" name="cat_id" value="<?= (int)$item['id'] ?>"><input type="hidden" name="pid" value="<?= (int)$p['id'] ?>">
                  <input type="hidden" name="dir" value="<?= $dir ?>"><button class="pp-b" aria-label="<?= $dir === 'left' ? 'आगे लाइए' : 'पीछे भेजिए' ?>"><?= $lbl ?></button></form>
              <?php endforeach; ?>
              <form method="post" onsubmit="return confirm('यह फ़ोटो हटा दें?')"><input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="do" value="del">
                <input type="hidden" name="cat_id" value="<?= (int)$item['id'] ?>"><input type="hidden" name="pid" value="<?= (int)$p['id'] ?>">
                <button class="pp-b pp-del" aria-label="फ़ोटो हटाइए">✕</button></form>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <?php if ($table_ok && count($photos) < CATALOG_PHOTO_MAX): ?>
      <form method="post" enctype="multipart/form-data" class="pp-up">
        <input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="do" value="upload">
        <input type="hidden" name="cat_id" value="<?= (int)$item['id'] ?>">
        <label class="pp-drop">
          <span class="pp-plus">+</span>
          <b>फ़ोटो चुनिए (एक साथ कई)</b>
          <span class="meta">JPG / PNG / WebP · 8 MB तक · अभी <?= CATALOG_PHOTO_MAX - count($photos) ?> और लग सकती हैं</span>
          <input type="file" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple required>
        </label>
        <button class="btn btn-brand" style="width:100%">फ़ोटो लगाइए</button>
      </form>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <?php if ($results): ?>
    <h3 class="ghead"><?= $q !== '' ? 'खोज के नतीजे' : 'Home page का सामान' ?></h3>
    <div class="pp-list">
      <?php foreach ($results as $r): $c = count($counts[(int)$r['id']] ?? []); ?>
        <a class="pp-row<?= $item && (int)$item['id'] === (int)$r['id'] ? ' on' : '' ?>" href="/admin/product-photos.php?id=<?= (int)$r['id'] ?><?= $q !== '' ? '&q=' . h(urlencode($q)) : '' ?>">
          <span><b><?= h($r['name_en']) ?></b><?php if ($r['name_hi']): ?> <span class="meta">· <?= h($r['name_hi']) ?></span><?php endif; ?></span>
          <span class="pp-tag<?= $c >= 4 ? ' ok' : '' ?>"><?= $c ?> फ़ोटो</span>
        </a>
      <?php endforeach; ?>
    </div>
  <?php elseif ($q !== ''): ?>
    <div class="box">"<?= h($q) ?>" नहीं मिला। दूसरा नाम लिखिए।</div>
  <?php endif; ?>
</div>
</section>
<?php include __DIR__ . '/../inc/foot.php'; ?>
