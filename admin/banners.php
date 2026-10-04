<?php
require_once __DIR__ . '/../inc/fn.php';
$u = need_role('admin');
$page_title = 'ऑफ़र और ऐड — Maakit';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_ok()) {
    $do = post('do'); $id = (int)post('id');
    $tone = in_array(post('tone'), ['brand','gold','dark','cream'], true) ? post('tone') : 'brand';

    if ($do === 'add' || $do === 'edit') {
        $te = trim(post('title_en')); $th = trim(post('title_hi'));
        if ($te === '' && $th !== '') $te = $th;
        if ($th === '' && $te !== '') $th = $te;
        $se = trim(post('sub_en')); $sh = trim(post('sub_hi'));
        if ($se === '' && $sh !== '') $se = $sh;
        if ($sh === '' && $se !== '') $sh = $se;
        $link = trim(post('link'));
        if ($link !== '' && !preg_match('#^/[A-Za-z0-9._/?=&#-]*$#', $link)) { $link = '/order.php'; }
        $st = post('starts') ?: null; $en = post('ends') ?: null;
        $act = post('active') ? 1 : 0;
        $sort = (int)post('sort_no');

        if (mb_strlen($th) < 2) { flash('कम से कम एक भाषा में हेडिंग लिखिए।'); }
        elseif ($do === 'add') {
            $ph = save_item_photo('photo', 'ban', 600);
            $pdo->prepare("INSERT INTO banners (title_en,title_hi,sub_en,sub_hi,link,photo,tone,active,sort_no,starts,ends)
                           VALUES (?,?,?,?,?,?,?,?,?,?,?)")
                ->execute([$te,$th,$se,$sh,$link ?: null,$ph,$tone,$act,$sort ?: 100,$st,$en]);
            flash('नया ऑफ़र जुड़ गया।');
        } else {
            $ph = save_item_photo('photo', 'ban', 600);
            if ($ph) {
                $o = $pdo->prepare("SELECT photo FROM banners WHERE id=?"); $o->execute([$id]);
                $old = $o->fetch()['photo'] ?? null;
                $pdo->prepare("UPDATE banners SET photo=? WHERE id=?")->execute([$ph, $id]);
                if ($old) drop_photo($old);
            }
            $pdo->prepare("UPDATE banners SET title_en=?,title_hi=?,sub_en=?,sub_hi=?,link=?,tone=?,active=?,sort_no=?,starts=?,ends=? WHERE id=?")
                ->execute([$te,$th,$se,$sh,$link ?: null,$tone,$act,$sort ?: 100,$st,$en,$id]);
            flash('सेव हो गया।');
        }
    }
    elseif ($do === 'del') {
        $o = $pdo->prepare("SELECT photo FROM banners WHERE id=?"); $o->execute([$id]);
        $old = $o->fetch()['photo'] ?? null;
        $pdo->prepare("DELETE FROM banners WHERE id=?")->execute([$id]);
        if ($old) drop_photo($old);
        flash('हटा दिया गया।');
    }
    elseif ($do === 'unphoto') {
        $o = $pdo->prepare("SELECT photo FROM banners WHERE id=?"); $o->execute([$id]);
        $old = $o->fetch()['photo'] ?? null;
        $pdo->prepare("UPDATE banners SET photo=NULL WHERE id=?")->execute([$id]);
        if ($old) drop_photo($old);
        flash('फ़ोटो हटा दी।');
    }
    redirect('/admin/banners.php');
}

$rows = $pdo->query("SELECT * FROM banners ORDER BY active DESC, sort_no, id")->fetchAll();
$TONES = ['brand'=>'गहरा लाल','gold'=>'सुनहरा','dark'=>'काला','cream'=>'हल्का'];
$LINKS = [
  '/order.php' => 'ऑर्डर पेज',
  '/order.php#khana' => 'खाना',
  '/book.php' => 'सारी बुकिंग',
  '/book.php?s=gaadi' => 'गाड़ी बुकिंग',
  '/book.php?s=lawn' => 'लॉन बुकिंग',
  '/book.php?s=tent' => 'टेंट बुकिंग',
  '/book.php?s=halwai' => 'हलवाई',
  '/book.php?s=pandit' => 'पंडित जी',
  '/directory.php' => 'काम-धंधा',
  '/area.php' => 'नए गाँव की माँग',
];
include __DIR__ . '/../inc/panel.php';
?>
<section>
<div class="wrap">
  <h2>ऑफ़र और ऐड</h2>
  <p class="lead">होम पेज पर “क्या चल रहा है” वाली पट्टी आप ख़ुद चलाते हैं।
    त्योहार का ऑफ़र, नई सेवा, या कोई ज़रूरी बात — यहाँ से लगाइए, तुरंत दिख जाएगी।</p>

  <h3 class="ghead" style="margin-top:0">अभी क्या दिख रहा है</h3>
  <div class="bann" style="margin:0 0 20px;padding:2px 0 10px">
    <?php foreach ($rows as $b): if (!$b['active']) continue; ?>
      <div class="bn <?= h($b['tone']) ?>" style="cursor:default">
        <b><?= h($b['title_hi']) ?></b>
        <?php if ($b['sub_hi']): ?><i><?= h($b['sub_hi']) ?></i><?php endif; ?>
        <span class="go">देखिए</span>
        <span class="bg"><?= $b['photo'] ? '<img src="/uploads/' . h($b['photo']) . '" alt="">' : svc_icon('box', 96) ?></span>
      </div>
    <?php endforeach; ?>
  </div>

  <?php foreach ($rows as $b): ?>
    <div class="box" style="margin-bottom:12px">
      <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
        <input type="hidden" name="do" value="edit"><input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
        <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:10px">
          <b style="font-size:17px"><?= h($b['title_hi']) ?></b>
          <span>
            <span class="tag <?= $b['active'] ? 'tag-live' : 'tag-off' ?>"><?= $b['active'] ? 'दिख रहा है' : 'बंद' ?></span>
            <?php if ($b['clicks']): ?><span class="tag tag-gold"><?= (int)$b['clicks'] ?> बार दबाया गया</span><?php endif; ?>
          </span>
        </div>
        <div class="grid g2">
          <div class="field"><label>हेडिंग (हिंदी)</label><input type="text" name="title_hi" value="<?= h($b['title_hi']) ?>" required maxlength="80"></div>
          <div class="field"><label>Heading (English)</label><input type="text" name="title_en" value="<?= h($b['title_en']) ?>" maxlength="80"></div>
          <div class="field"><label>छोटी लाइन (हिंदी)</label><input type="text" name="sub_hi" value="<?= h($b['sub_hi']) ?>" maxlength="140"></div>
          <div class="field"><label>Sub-line (English)</label><input type="text" name="sub_en" value="<?= h($b['sub_en']) ?>" maxlength="140"></div>
        </div>
        <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">
          <div style="min-width:170px"><label>दबाने पर कहाँ जाए</label>
            <select name="link"><?php foreach ($LINKS as $k => $v): ?>
              <option value="<?= h($k) ?>" <?= $b['link'] === $k ? 'selected' : '' ?>><?= h($v) ?></option>
            <?php endforeach; ?></select></div>
          <div style="min-width:130px"><label>रंग</label>
            <select name="tone"><?php foreach ($TONES as $k => $v): ?>
              <option value="<?= h($k) ?>" <?= $b['tone'] === $k ? 'selected' : '' ?>><?= h($v) ?></option>
            <?php endforeach; ?></select></div>
          <div style="max-width:150px"><label>कब से</label><input type="date" name="starts" value="<?= h($b['starts']) ?>"></div>
          <div style="max-width:150px"><label>कब तक</label><input type="date" name="ends" value="<?= h($b['ends']) ?>"></div>
          <div style="max-width:90px"><label>क्रम</label><input type="number" name="sort_no" value="<?= (int)$b['sort_no'] ?>"></div>
          <label style="display:flex;gap:7px;align-items:center;font-weight:600;padding-bottom:12px">
            <input type="checkbox" name="active" value="1" <?= $b['active'] ? 'checked' : '' ?> style="width:auto;min-height:0"> दिखाइए</label>
          <div style="max-width:200px"><label>पीछे की फ़ोटो</label><input type="file" name="photo" accept="image/*"></div>
          <button class="btn btn-brand btn-sm">सेव</button>
        </div>
      </form>
      <div style="display:flex;gap:8px;margin-top:8px">
        <?php if ($b['photo']): ?>
          <form method="post"><input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
            <input type="hidden" name="do" value="unphoto"><input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
            <button class="btn btn-sm" style="background:transparent;color:var(--muted);padding:4px 8px;font-size:13px">फ़ोटो हटाइए</button></form>
        <?php endif; ?>
        <form method="post" onsubmit="return confirm('यह ऑफ़र हटा दें?')">
          <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
          <input type="hidden" name="do" value="del"><input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
          <button class="btn btn-sm" style="background:transparent;color:var(--bad);padding:4px 8px;font-size:13px">हटाइए</button></form>
      </div>
    </div>
  <?php endforeach; ?>

  <h3 class="ghead">नया ऑफ़र जोड़िए</h3>
  <form method="post" enctype="multipart/form-data" class="box" style="margin-bottom:30px">
    <input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="do" value="add">
    <div class="grid g2">
      <div class="field"><label>हेडिंग (हिंदी)</label>
        <input type="text" name="title_hi" required maxlength="80" placeholder="जैसे: दिवाली पर मिठाई फ़्री डिलीवरी"></div>
      <div class="field"><label>Heading (English)</label>
        <input type="text" name="title_en" maxlength="80" placeholder="Free delivery on sweets this Diwali"></div>
      <div class="field"><label>छोटी लाइन (हिंदी)</label><input type="text" name="sub_hi" maxlength="140"></div>
      <div class="field"><label>Sub-line (English)</label><input type="text" name="sub_en" maxlength="140"></div>
    </div>
    <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">
      <div style="min-width:170px"><label>दबाने पर कहाँ जाए</label>
        <select name="link"><?php foreach ($LINKS as $k => $v): ?><option value="<?= h($k) ?>"><?= h($v) ?></option><?php endforeach; ?></select></div>
      <div style="min-width:130px"><label>रंग</label>
        <select name="tone"><?php foreach ($TONES as $k => $v): ?><option value="<?= h($k) ?>"><?= h($v) ?></option><?php endforeach; ?></select></div>
      <div style="max-width:150px"><label>कब से</label><input type="date" name="starts"></div>
      <div style="max-width:150px"><label>कब तक</label><input type="date" name="ends"></div>
      <div style="max-width:200px"><label>पीछे की फ़ोटो</label><input type="file" name="photo" accept="image/*"></div>
      <input type="hidden" name="active" value="1">
      <button class="btn btn-brand btn-sm">जोड़िए</button>
    </div>
    <p class="help" style="margin-top:10px">“कब से / कब तक” भर देंगे तो ऑफ़र अपने आप आएगा और अपने आप हट जाएगा —
      त्योहार के लिए पहले से लगा दीजिए।</p>
  </form>
</div>
</section>
<?php include __DIR__ . '/../inc/foot.php'; ?>
