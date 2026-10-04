<?php
require_once __DIR__ . '/../inc/fn.php';
require_once __DIR__ . '/../inc/items.php';
$u = need_role('admin');
$page_title = 'सामान और फ़ोटो — Maakit';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_ok()) {
    $do = post('do');
    $id = (int)post('id');
    $back = '/admin/items.php?g=' . urlencode(get('g', post('g')))
          . (get('q', post('q')) ? '&q=' . urlencode(get('q', post('q'))) : '')
          . ($id ? '#it' . $id : '');

    if ($do === 'photo') {
        $new = save_item_photo('photo', 'it');
        if (!$new) {
            flash('फ़ोटो नहीं लग पाई। साफ़ तस्वीर चुनिए (8 MB तक)।');
        } else {
            $st = $pdo->prepare("SELECT photo FROM items WHERE id=?"); $st->execute([$id]);
            $old = $st->fetch()['photo'] ?? null;
            $pdo->prepare("UPDATE items SET photo=? WHERE id=?")->execute([$new, $id]);
            if ($old) drop_photo($old);
            flash('फ़ोटो लग गई।');
        }
    }
    elseif ($do === 'unphoto') {
        $st = $pdo->prepare("SELECT photo FROM items WHERE id=?"); $st->execute([$id]);
        $old = $st->fetch()['photo'] ?? null;
        $pdo->prepare("UPDATE items SET photo=NULL WHERE id=?")->execute([$id]);
        if ($old) drop_photo($old);
        flash('फ़ोटो हटा दी — अब icon दिखेगा।');
    }
    elseif ($do === 'edit') {
        $n = trim(post('name')); $un = trim(post('unit'));
        $g = post('grp'); $pop = post('popular') ? 1 : 0; $act = post('active') ? 1 : 0;
        if (!array_key_exists($g, item_groups()) || $g === 'daily') { $g = 'anya'; }
        if (mb_strlen($n) >= 2 && mb_strlen($un) >= 1) {
            $pdo->prepare("UPDATE items SET name=?, unit=?, grp=?, popular=?, active=? WHERE id=?")
                ->execute([$n, $un, $g, $pop, $act, $id]);
            flash('सेव हो गया।');
        }
    }
    elseif ($do === 'svcphoto') {
        $k = preg_replace('/[^a-z]/', '', post('key'));
        if ($k) {
            $new = save_item_photo('photo', 'tmpsvc', 700);
            if (!$new) { flash('फ़ोटो नहीं लग पाई।'); }
            else {
                foreach (['jpg','png','webp'] as $e) { @unlink(__DIR__ . '/../uploads/svc-' . $k . '.' . $e); }
                @rename(__DIR__ . '/../uploads/' . $new, __DIR__ . '/../uploads/svc-' . $k . '.jpg');
                flash('सेवा की फ़ोटो लग गई।');
            }
        }
        redirect('/admin/items.php#sewa');
    }
    elseif ($do === 'svcunphoto') {
        $k = preg_replace('/[^a-z]/', '', post('key'));
        if ($k) { foreach (['jpg','png','webp'] as $e) { @unlink(__DIR__ . '/../uploads/svc-' . $k . '.' . $e); } }
        flash('फ़ोटो हटा दी — अब icon दिखेगा।');
        redirect('/admin/items.php#sewa');
    }
    elseif ($do === 'add') {
        $n = trim(post('name')); $un = trim(post('unit'));
        $g = post('grp'); $pop = post('popular') ? 1 : 0;
        if (!array_key_exists($g, item_groups()) || $g === 'daily') { $g = 'anya'; }
        if (mb_strlen($n) < 2 || mb_strlen($un) < 1) { flash('नाम और मात्रा दोनों लिखिए।'); }
        else {
            $mx = (int)$pdo->query("SELECT COALESCE(MAX(sort_no),0) m FROM items")->fetch()['m'];
            try {
                $pdo->prepare("INSERT INTO items (name, unit, grp, words, popular, sort_no) VALUES (?,?,?,?,?,?)")
                    ->execute([$n, $un, $g, mb_strtolower($n . ' ' . post('words')), $pop, $mx + 10]);
                flash('नया सामान जुड़ गया।');
            } catch (Throwable $e) { flash('यह सामान इसी मात्रा में पहले से है।'); }
        }
    }
    redirect($back);
}

$g = get('g', '');
$q = trim(get('q', ''));
$sql = "SELECT * FROM items WHERE 1";
$args = [];
if ($g === 'nophoto')      { $sql .= " AND (photo IS NULL OR photo='')"; }
elseif ($g === 'photo')    { $sql .= " AND photo IS NOT NULL AND photo<>''"; }
elseif ($g === 'daily')    { $sql .= " AND popular=1"; }
elseif ($g !== '')         { $sql .= " AND grp=?"; $args[] = $g; }
if ($q !== '')             { $sql .= " AND (name LIKE ? OR words LIKE ?)"; $args[] = "%$q%"; $args[] = "%$q%"; }
$sql .= " ORDER BY sort_no, id";
$st = $pdo->prepare($sql); $st->execute($args); $rows = $st->fetchAll();

$tot  = (int)$pdo->query("SELECT COUNT(*) c FROM items")->fetch()['c'];
$done = (int)$pdo->query("SELECT COUNT(*) c FROM items WHERE photo IS NOT NULL AND photo<>''")->fetch()['c'];
$pct  = $tot ? round($done * 100 / $tot) : 0;
include __DIR__ . '/../inc/panel.php';
?>
<section>
<div class="wrap">
  <h2>सामान और फ़ोटो</h2>
  <p class="lead">फ़ोटो लगाना ज़रूरी नहीं — जिसकी फ़ोटो नहीं होगी उसका साफ़ icon दिखेगा।
    दुकान पर जाइए, फ़ोन से खींचिए, यहीं से लगा दीजिए।</p>

  <div class="box" style="margin-bottom:16px">
    <div style="display:flex;justify-content:space-between;align-items:baseline;flex-wrap:wrap;gap:6px">
      <b>फ़ोटो लग चुकी: <?= $done ?> / <?= $tot ?></b>
      <span class="help"><?= $pct ?>% — बाक़ी पर icon दिख रहा है</span>
    </div>
    <div style="height:9px;background:var(--soft);border-radius:10px;margin-top:8px;overflow:hidden">
      <div style="height:100%;width:<?= (int)$pct ?>%;background:var(--ok);border-radius:10px"></div>
    </div>
    <a class="btn btn-brand btn-sm" style="margin-top:10px" href="/admin/photos.php">फ़ोटो एक साथ लगाइए →</a>
    <p class="help" style="margin-top:8px">रोज़ 5-10 फ़ोटो भी लगाएँगे तो हफ़्ते भर में पूरी लिस्ट रंगीन हो जाएगी।
      सबसे पहले “रोज़ का सामान” वाले <b><?= (int)$pdo->query("SELECT COUNT(*) c FROM items WHERE popular=1")->fetch()['c'] ?></b> सामान की फ़ोटो लगाइए — वही सबसे ज़्यादा दिखते हैं।</p>
  </div>

  <form method="get" style="display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end;margin-bottom:12px">
    <div style="min-width:180px"><label>कौन सा हिस्सा</label>
      <select name="g" onchange="this.form.submit()">
        <option value="">— सब (<?= $tot ?>) —</option>
        <option value="nophoto" <?= $g === 'nophoto' ? 'selected' : '' ?>>जिनकी फ़ोटो नहीं है</option>
        <option value="photo"   <?= $g === 'photo' ? 'selected' : '' ?>>जिनकी फ़ोटो लग चुकी</option>
        <option value="daily"   <?= $g === 'daily' ? 'selected' : '' ?>>रोज़ का सामान</option>
        <?php foreach (item_groups() as $k => $n): if ($k === 'daily') continue; ?>
          <option value="<?= h($k) ?>" <?= $g === $k ? 'selected' : '' ?>><?= h($n) ?></option>
        <?php endforeach; ?>
      </select></div>
    <div style="flex:1;min-width:170px"><label>नाम से ढूंढिए</label>
      <input type="text" name="q" value="<?= h($q) ?>" placeholder="आटा, तेल…"></div>
    <button class="btn btn-brand btn-sm">देखिए</button>
    <a class="btn btn-gold btn-sm" href="#naya">+ नया सामान</a>
  </form>

  <p class="help" style="margin-bottom:10px"><?= count($rows) ?> सामान दिख रहे हैं</p>

  <div class="admgrid">
    <?php foreach ($rows as $r): ?>
      <div class="admit <?= $r['active'] ? '' : 'off' ?>" id="it<?= (int)$r['id'] ?>">
        <div class="ph">
          <?php if ($r['photo']): ?>
            <img src="/uploads/<?= h($r['photo']) ?>" alt="">
          <?php else: ?>
            <span class="noph"><?= prod_icon($r['name'], $r['grp'], 34) ?></span>
          <?php endif; ?>
        </div>

        <div class="tx">
          <b><?= h($r['name']) ?></b>
          <i><?= h($r['unit']) ?> · <?= h(item_groups()[$r['grp']] ?? $r['grp']) ?></i>
          <div style="margin-top:4px">
            <?php if ($r['popular']): ?><span class="tag tag-gold">रोज़ का</span><?php endif; ?>
            <?php if (!$r['active']): ?><span class="tag tag-off">बंद</span><?php endif; ?>
          </div>
        </div>

        <form method="post" enctype="multipart/form-data" class="up">
          <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
          <input type="hidden" name="do" value="photo">
          <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
          <input type="hidden" name="g" value="<?= h($g) ?>"><input type="hidden" name="q" value="<?= h($q) ?>">
          <label class="pick2">
            <input type="file" name="photo" accept="image/*"
                   onchange="if(this.files.length){this.closest('.admit').classList.add('busy');this.form.submit();}">
            <span><?= $r['photo'] ? 'बदलिए' : 'फ़ोटो लगाइए' ?></span>
          </label>
        </form>

        <?php if ($r['photo']): ?>
          <form method="post" style="display:inline">
            <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
            <input type="hidden" name="do" value="unphoto"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
            <input type="hidden" name="g" value="<?= h($g) ?>"><input type="hidden" name="q" value="<?= h($q) ?>">
            <button class="btn btn-sm" style="background:transparent;color:var(--bad);padding:4px 8px;font-size:13px">हटाइए</button>
          </form>
        <?php endif; ?>

        <details class="ed">
          <summary>नाम / मात्रा बदलिए</summary>
          <form method="post" style="margin-top:8px">
            <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
            <input type="hidden" name="do" value="edit"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
            <input type="hidden" name="g" value="<?= h($g) ?>"><input type="hidden" name="q" value="<?= h($q) ?>">
            <div class="field"><label>नाम</label><input type="text" name="name" value="<?= h($r['name']) ?>" required></div>
            <div class="field"><label>मात्रा</label><input type="text" name="unit" value="<?= h($r['unit']) ?>" required></div>
            <div class="field"><label>हिस्सा</label>
              <select name="grp"><?php foreach (item_groups() as $k => $n): if ($k === 'daily') continue; ?>
                <option value="<?= h($k) ?>" <?= $r['grp'] === $k ? 'selected' : '' ?>><?= h($n) ?></option>
              <?php endforeach; ?></select></div>
            <label style="display:flex;gap:8px;align-items:center;font-weight:500">
              <input type="checkbox" name="popular" value="1" <?= $r['popular'] ? 'checked' : '' ?> style="width:auto;min-height:0"> रोज़ के सामान में दिखाइए</label>
            <label style="display:flex;gap:8px;align-items:center;font-weight:500;margin:6px 0 10px">
              <input type="checkbox" name="active" value="1" <?= $r['active'] ? 'checked' : '' ?> style="width:auto;min-height:0"> वेबसाइट पर दिखाइए</label>
            <button class="btn btn-brand btn-sm">सेव</button>
          </form>
        </details>
      </div>
    <?php endforeach; ?>
  </div>

  <h3 class="ghead" id="sewa">होम पेज की सेवाओं की फ़ोटो</h3>
  <p class="help" style="margin:-4px 0 14px">
    यह हिस्सा अब <b>फ़ोटो</b> पेज पर चला गया है — वहाँ सारी फ़ोटो एक ही जगह हैं,
    और वहाँ दबाते ही फ़ोटो चढ़ जाती है।
  </p>
  <a class="btn btn-brand btn-sm" style="margin-bottom:26px" href="/admin/photos.php#sewa">
    सेवाओं की फ़ोटो लगाइए &rarr;</a>

  <h3 class="ghead" id="naya">नया सामान जोड़िए</h3>
  <form method="post" class="box" style="max-width:520px;margin-bottom:30px">
    <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
    <input type="hidden" name="do" value="add">
    <div class="field"><label>सामान का नाम (हिंदी में)</label><input type="text" name="name" placeholder="जैसे: सरसों का साग" required></div>
    <div class="field"><label>मात्रा</label><input type="text" name="unit" placeholder="जैसे: 1 किलो" required></div>
    <div class="field"><label>हिस्सा</label>
      <select name="grp"><?php foreach (item_groups() as $k => $n): if ($k === 'daily') continue; ?>
        <option value="<?= h($k) ?>"><?= h($n) ?></option>
      <?php endforeach; ?></select></div>
    <div class="field"><label>खोजने के शब्द (चाहें तो)</label>
      <input type="text" name="words" placeholder="saag sarson साग — अंग्रेज़ी में भी लिखिए">
      <p class="help">इससे लोग हिंदी और अंग्रेज़ी दोनों से ढूंढ पाएँगे।</p></div>
    <label style="display:flex;gap:8px;align-items:center;font-weight:500;margin-bottom:12px">
      <input type="checkbox" name="popular" value="1" style="width:auto;min-height:0"> रोज़ के सामान में दिखाइए</label>
    <button class="btn btn-brand" style="width:100%">जोड़िए</button>
    <p class="help" style="margin-top:8px">जोड़ने के बाद ऊपर ढूंढकर फ़ोटो लगा सकते हैं।</p>
  </form>

  <div class="box" style="margin-bottom:30px">
    <b>फ़ोटो कैसी लीजिए</b>
    <ul class="help" style="margin:6px 0 0;padding-left:18px;line-height:1.75">
      <li>सामान को सफ़ेद या सादे कागज़ पर रखकर ऊपर से खींचिए</li>
      <li>दिन की रोशनी में — ट्यूबलाइट में रंग पीला आता है</li>
      <li>पूरा सामान फ़्रेम में आए, हाथ या दुकान का सामान पीछे न दिखे</li>
      <li>सीधी खींचिए — website ख़ुद चौकोर काटकर छोटी कर देती है</li>
      <li><b>Google या किसी और की फ़ोटो मत लगाइए</b> — अपनी खींची हुई ही लगाइए</li>
    </ul>
  </div>
</div>
</section>
<?php include __DIR__ . '/../inc/foot.php'; ?>
