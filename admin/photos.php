<?php
require_once __DIR__ . '/../inc/fn.php';
require_once __DIR__ . '/../inc/items.php';
require_once __DIR__ . '/../inc/groups.php';
$u = need_role('admin');
$page_title = 'फ़ोटो एक साथ लगाइए — Maakit';

$UP = __DIR__ . '/../uploads';
if (!is_dir($UP)) @mkdir($UP, 0755, true);
$can_write = is_dir($UP) && is_writable($UP);

/* "8M" / "512K" jaisi likhawat ko number me badlo */
function ph_bytes($s) {
    $s = trim((string)$s);
    if ($s === '') return 0;
    $n = (float)$s;
    switch (strtolower(substr($s, -1))) {
        case 'g': $n *= 1024;
        case 'm': $n *= 1024;
        case 'k': $n *= 1024;
    }
    return (int)$n;
}

/* ---------- naam milane ke liye: sirf akshar-ank bache ---------- */
function ph_norm($s) {
    $s = mb_strtolower(trim((string)$s), 'UTF-8');
    $s = preg_replace('/[^\p{L}\p{N}]+/u', '', $s);
    return $s === null ? '' : $s;
}

/* file ke naam se saaman dhoondho:
   1) sirf number ho to wahi id
   2) warna naam / khoj-shabd se milao                         */
function ph_match($file, array $items) {
    $base = pathinfo($file, PATHINFO_FILENAME);
    $base = preg_replace('/[\s_\-]*\(\d+\)$/', '', $base);      // "aata (1)" -> "aata"
    $t = trim($base);

    if (preg_match('/^0*(\d{1,7})$/', $t, $m)) {
        $id = (int)$m[1];
        foreach ($items as $it) if ((int)$it['id'] === $id) return $it;
        return null;
    }
    $n = ph_norm($t);
    if ($n === '') return null;

    foreach ($items as $it) if (ph_norm($it['name']) === $n) return $it;      // poora naam
    foreach ($items as $it) {
        foreach (preg_split('/\s+/', (string)$it['words']) as $w) {
            if ($w !== '' && ph_norm($w) === $n) return $it;                  // khoj-shabd
        }
    }
    return null;
}

/* ---------- ek photo lagao (dono tarike isi ko bulate hain) ---------- */
function ph_put(PDO $pdo, $id, $tmp, $size) {
    $new = save_photo_from($tmp, $size, 'it', 500);
    if (!$new) return false;
    $st = $pdo->prepare("SELECT photo FROM items WHERE id=?"); $st->execute([$id]);
    $row = $st->fetch();
    if (!$row) { drop_photo($new); return false; }
    $pdo->prepare("UPDATE items SET photo=? WHERE id=?")->execute([$new, $id]);
    if (!empty($row['photo'])) drop_photo($row['photo']);
    return $new;
}

/* ============================================================
   1) TEZ TARIKA — ek tile ki photo, bina page badle (AJAX)
   ============================================================ */
if (get('ajax') === '1') {
    while (ob_get_level()) ob_end_clean();          // koi warning JSON se pehle na nikle
    ob_start();
    header('Content-Type: application/json; charset=utf-8');

    $jawab = function ($ok, $msg = '', $photo = null) {
        while (ob_get_level()) ob_end_clean();       // jo kachra jama hua, phenk do
        echo json_encode($ok ? ['ok' => true, 'photo' => $photo] : ['ok' => false, 'msg' => $msg],
                         JSON_UNESCAPED_UNICODE);
        exit;
    };

    // Photo itni badi thi ki PHP ne poora data hi phenk diya — tab $_POST
    // khaali aata hai aur csrf fail ho jata hai. Asli wajah yahi hai,
    // "page purana ho gaya" nahi.
    $bheja = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
    $hadd  = ph_bytes(ini_get('post_max_size'));
    if (!$_POST && $bheja > 0 && $hadd > 0 && $bheja > $hadd) {
        $jawab(false, 'Photo bahut badi hai (' . round($bheja / 1048576, 1) . ' MB). '
                    . 'Is server par ' . round($hadd / 1048576, 1) . ' MB tak hi ja sakti hai.');
    }

    if (!csrf_ok())  $jawab(false, 'Page purana ho gaya. Page refresh karke dobara kijiye.');
    if (!$can_write) $jawab(false, 'uploads folder me likha nahi ja raha. Uski permission 755 kijiye.');

    $svc = preg_replace('/[^a-z]/', '', post('svc'));     // sewa ki photo
    $id  = (int)post('id');
    if (empty($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
        $e = $_FILES['photo']['error'] ?? -1;
        if ($e === UPLOAD_ERR_INI_SIZE || $e === UPLOAD_ERR_FORM_SIZE) {
            $jawab(false, 'Photo bahut badi hai. Is server par '
                        . ini_get('upload_max_filesize') . ' tak hi ja sakti hai.');
        }
        if ($e === UPLOAD_ERR_PARTIAL) $jawab(false, 'Photo poori nahi pahunchi — net beech me kat gaya. Dobara kijiye.');
        if ($e === UPLOAD_ERR_NO_FILE) $jawab(false, 'Koi photo chuni hi nahi gayi.');
        if ($e === UPLOAD_ERR_NO_TMP_DIR || $e === UPLOAD_ERR_CANT_WRITE) {
            $jawab(false, 'Server photo sambhal nahi pa raha. Hosting wale se kahiye.');
        }
        $jawab(false, 'Photo aa nahi payi. Dobara chuniye.');
    }

    if ($svc) {
        // sewa ka tile — photo ka naam tay hai: svc-<key>.jpg
        $tmpn = save_photo_from($_FILES['photo']['tmp_name'], $_FILES['photo']['size'], 'tmpsvc', 700);
        if (!$tmpn) $jawab(false, 'Ye file photo nahi hai (jpg / png / webp chuniye).');
        foreach (['jpg','png','webp'] as $e) @unlink($UP . '/svc-' . $svc . '.' . $e);
        @rename($UP . '/' . $tmpn, $UP . '/svc-' . $svc . '.jpg');
        @chmod($UP . '/svc-' . $svc . '.jpg', 0644);
        $jawab(true, '', 'svc-' . $svc . '.jpg');
    }

    $new = ph_put($pdo, $id, $_FILES['photo']['tmp_name'], $_FILES['photo']['size']);
    $new ? $jawab(true, '', $new)
         : $jawab(false, 'Ye file photo nahi hai (jpg / png / webp chuniye).');
}

/* ============================================================
   2) EK SAATH — kai photo ek hi baar me
   ============================================================ */
$bulk = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_ok() && post('do') === 'bulk') {
    $all   = $pdo->query("SELECT id,name,unit,grp,words,photo FROM items WHERE active=1")->fetchAll();
    $bulk  = ['lagi' => [], 'nahi' => [], 'kharab' => []];
    $files = $_FILES['photos'] ?? null;

    if ($files && is_array($files['name'])) {
        foreach ($files['name'] as $i => $fname) {
            if ($files['error'][$i] !== UPLOAD_ERR_OK) { $bulk['kharab'][] = $fname; continue; }
            $it = ph_match($fname, $all);
            if (!$it) { $bulk['nahi'][] = $fname; continue; }
            $new = ph_put($pdo, (int)$it['id'], $files['tmp_name'][$i], $files['size'][$i]);
            if ($new) $bulk['lagi'][] = ['file' => $fname, 'name' => $it['name'], 'unit' => $it['unit'], 'photo' => $new];
            else      $bulk['kharab'][] = $fname;
        }
    }
}

/* ---------- list ---------- */
$g  = get('g', '');
$sh = get('sh', 'nophoto');                       // nophoto | all
$sql = "SELECT id,name,unit,grp,photo FROM items WHERE active=1";
$par = [];
if ($g !== '' && array_key_exists($g, item_groups())) { $sql .= " AND grp=?"; $par[] = $g; }
if ($sh === 'nophoto') $sql .= " AND (photo IS NULL OR photo='')";
$sql .= " ORDER BY sort_no, id";
$st = $pdo->prepare($sql); $st->execute($par); $rows = $st->fetchAll();

$kul  = (int)$pdo->query("SELECT COUNT(*) c FROM items WHERE active=1")->fetch()['c'];
$done = (int)$pdo->query("SELECT COUNT(*) c FROM items WHERE active=1 AND photo IS NOT NULL AND photo<>''")->fetch()['c'];
$pc   = $kul ? round($done * 100 / $kul) : 0;

/* server ek baar me kitni file leta hai */
$maxf = (int)ini_get('max_file_uploads'); if ($maxf < 1) $maxf = 20;
$post_mb = (int)ini_get('post_max_size');
$one_mb  = (int)ini_get('upload_max_filesize');

include __DIR__ . '/../inc/panel.php';
?>
<style>
.phwrap{max-width:1000px;margin:0 auto;padding:0 14px}
.phbar{background:#fff;border:2px solid #EAD7B5;border-radius:16px;padding:14px 18px;margin:16px 0}
.phbar .big{font-size:26px;font-weight:800;line-height:1.1}
.phbar .track{height:12px;border-radius:9px;background:#F0E3C8;overflow:hidden;margin-top:10px}
.phbar .fill{height:100%;background:#7A1F1F;border-radius:9px;transition:width .35s}
.phtabs{display:flex;gap:8px;margin:16px 0 10px;flex-wrap:wrap}
.phtabs a{padding:9px 16px;border-radius:999px;border:2px solid #EAD7B5;background:#fff;
  text-decoration:none;color:#2B1A12;font-weight:600;font-size:15px}
.phtabs a.on{background:#7A1F1F;border-color:#7A1F1F;color:#fff}
.phgrid{display:grid;grid-template-columns:repeat(auto-fill,minmax(132px,1fr));gap:12px;margin:14px 0 30px}
.pht{background:#fff;border:2px solid #EAD7B5;border-radius:14px;padding:10px;text-align:center;position:relative}
.pht.ok{border-color:#2E7D4F}
.pht .sq{width:100%;aspect-ratio:1/1;border-radius:10px;background:#FBF4E6;display:grid;place-items:center;overflow:hidden}
.pht .sq img{width:100%;height:100%;object-fit:cover}
.pht .nm{font-size:13.5px;font-weight:600;margin-top:7px;line-height:1.3;
  display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
.pht .un{font-size:12px;color:#6B4A2F;margin-top:2px}
.pht .no{position:absolute;top:7px;left:8px;background:#F4E6CB;border-radius:6px;
  padding:1px 6px;font-size:11.5px;font-weight:700;color:#6B4A2F}
.pht label{display:block;margin-top:8px;background:#E0A526;color:#3A1208;border-radius:9px;
  padding:7px 0;font-size:13.5px;font-weight:700;cursor:pointer}
.pht label:active{opacity:.8}
.pht input[type=file]{position:absolute;width:1px;height:1px;opacity:0;overflow:hidden}
.pht .tick{position:absolute;top:6px;right:7px;width:24px;height:24px;border-radius:50%;
  background:#2E7D4F;color:#fff;display:none;place-items:center;font-size:15px;font-weight:800}
.pht.ok .tick{display:grid}
.pht.busy{opacity:.55}
.pht .er{font-size:11.5px;color:#B02A2A;margin-top:5px;line-height:1.35;display:none}
.phbox{background:#fff;border:2px solid #EAD7B5;border-radius:16px;padding:18px;margin-bottom:18px}
.phbox h3{margin:0 0 6px;font-size:20px}
.phbox p{font-size:14.5px;color:#6B4A2F;line-height:1.65;margin:0 0 10px}
.phnums{max-height:330px;overflow:auto;border:1px solid #EAD7B5;border-radius:10px;background:#FBF4E6;padding:10px 12px;
  font-size:14px;line-height:1.9;columns:2;column-gap:26px}
.phnums b{color:#7A1F1F}
.phres td{padding:6px 10px 6px 0;font-size:14.5px;vertical-align:middle}
.phres img{width:38px;height:38px;border-radius:7px;object-fit:cover;display:block}
.phwarn{background:#7A1F1F;color:#FBF4E6;border-radius:14px;padding:14px 18px;margin:14px 0;font-size:15px;line-height:1.6}
@media(max-width:560px){.phnums{columns:1}}
</style>

<div class="phwrap">

<?php if (!$can_write): ?>
  <div class="phwarn"><b>uploads folder me likha nahi ja raha.</b><br>
    cPanel → File Manager → <code>public_html/uploads</code> par right-click → <b>Change Permissions</b> → <b>755</b> → Save.
    Jab tak ye nahi hoga, koi photo nahi lagegi.</div>
<?php endif; ?>

<div class="phbar">
  <div class="big"><span style="color:#6B4A2F;font-weight:600;font-size:19px"><?= $kul ?> में से</span>
    <?= $done ?> <span style="color:#6B4A2F;font-weight:600;font-size:19px">सामान की फ़ोटो लग चुकी</span></div>
  <div class="track"><div class="fill" id="phFill" style="width:<?= $pc ?>%"></div></div>
  <div style="font-size:14px;color:#6B4A2F;margin-top:7px">
    जिनकी फ़ोटो नहीं है, उनकी जगह अपने आप चित्र (icon) दिखता है — इसलिए कोई जल्दी नहीं।
    पहले वही सामान कीजिए जो सबसे ज़्यादा बिकता है।
  </div>
</div>

<!-- =============== TEZ TARIKA =============== -->
<div class="phbox">
  <h3>तेज़ तरीक़ा — दबाइए और फ़ोटो चुनिए</h3>
  <p>नीचे हर सामान का अपना डिब्बा है। <b>“फ़ोटो लगाइए”</b> दबाते ही फ़ोन का कैमरा या गैलरी खुलेगी।
     फ़ोटो चुनते ही वह अपने आप चढ़ जाएगी — पेज दोबारा नहीं खुलेगा, कुछ सेव नहीं करना।
     एक के बाद एक करते जाइए।</p>

  <div class="phtabs">
    <a class="<?= $sh === 'nophoto' ? 'on' : '' ?>" href="?sh=nophoto<?= $g ? '&g=' . h($g) : '' ?>">सिर्फ़ बची हुई</a>
    <a class="<?= $sh === 'all' ? 'on' : '' ?>"     href="?sh=all<?= $g ? '&g=' . h($g) : '' ?>">सारी</a>
  </div>
  <div class="phtabs">
    <a class="<?= $g === '' ? 'on' : '' ?>" href="?sh=<?= h($sh) ?>">सब तरह का</a>
    <?php foreach (item_groups() as $k => $lbl): ?>
      <a class="<?= $g === $k ? 'on' : '' ?>" href="?sh=<?= h($sh) ?>&g=<?= h($k) ?>"><?= h($lbl) ?></a>
    <?php endforeach; ?>
  </div>

  <?php if (!$rows): ?>
    <p style="font-size:16px;color:#2E7D4F;font-weight:600">इस हिस्से का सारा सामान हो चुका। 🎉</p>
  <?php else: ?>
    <div class="phgrid" id="phGrid">
      <?php foreach ($rows as $r): ?>
        <div class="pht <?= $r['photo'] ? 'ok' : '' ?>" data-id="<?= (int)$r['id'] ?>">
          <span class="no"><?= (int)$r['id'] ?></span>
          <span class="tick">&#10004;</span>
          <div class="sq">
            <?php if ($r['photo']): ?>
              <img src="/uploads/<?= h($r['photo']) ?>" alt="">
            <?php else: ?>
              <?= prod_icon($r['name'], $r['grp'], 46) ?>
            <?php endif; ?>
          </div>
          <div class="nm"><?= h($r['name']) ?></div>
          <div class="un"><?= h($r['unit']) ?></div>
          <label>
            <span class="lb"><?= $r['photo'] ? 'बदलिए' : 'फ़ोटो लगाइए' ?></span>
            <input type="file" accept="image/*">
          </label>
          <div class="er"></div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<!-- =============== EK SAATH =============== -->
<div class="phbox" id="eksath">
  <h3>एक साथ — कंप्यूटर से कई फ़ोटो</h3>
  <p>अगर सारी फ़ोटो कंप्यूटर में रखी हैं, तो उन्हें सामान के <b>नंबर</b> के नाम से रख दीजिए —
     जैसे <code>12.jpg</code>, <code>37.jpg</code>। नंबर नीचे की सूची में लिखे हैं (हर डिब्बे के ऊपर बाएँ भी दिखते हैं)।
     फिर नीचे से सारी फ़ोटो एक बार में चुन लीजिए।</p>
  <p>सामान का नाम भी चलेगा — जैसे <code>चीनी.jpg</code> या <code>aata.jpg</code>। नाम न मिला तो वह फ़ोटो छोड़ दी जाएगी,
     कुछ बिगड़ेगा नहीं।</p>

  <form method="post" enctype="multipart/form-data" style="margin:14px 0 6px">
    <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
    <input type="hidden" name="do" value="bulk">
    <input type="file" name="photos[]" multiple accept="image/*"
           style="font-size:15px;padding:10px;background:#FBF4E6;border:2px dashed #EAD7B5;border-radius:10px;width:100%">
    <button class="btn btn-brand" style="margin-top:12px" type="submit">चुनी हुई फ़ोटो चढ़ाइए</button>
  </form>
  <p style="font-size:13.5px">
    यह सर्वर एक बार में <b><?= $maxf ?> फ़ोटो</b> तक लेता है<?= $one_mb ? ', एक फ़ोटो ' . $one_mb . ' MB तक' : '' ?>.
    इससे ज़्यादा चुनेंगे तो बाक़ी छूट जाएँगी — <?= $maxf ?>-<?= $maxf ?> करके भेजिए।
  </p>

  <?php if ($bulk !== null): ?>
    <div style="border-top:2px solid #EAD7B5;margin-top:16px;padding-top:14px">
      <h3 style="font-size:18px"><?= count($bulk['lagi']) ?> फ़ोटो लग गईं</h3>
      <?php if ($bulk['lagi']): ?>
        <table class="phres"><?php foreach ($bulk['lagi'] as $x): ?>
          <tr><td><img src="/uploads/<?= h($x['photo']) ?>" alt=""></td>
              <td><code><?= h($x['file']) ?></code></td>
              <td>→ <b><?= h($x['name']) ?></b> <span style="color:#6B4A2F"><?= h($x['unit']) ?></span></td></tr>
        <?php endforeach; ?></table>
      <?php endif; ?>

      <?php if ($bulk['nahi']): ?>
        <p style="margin-top:14px;color:#B02A2A"><b><?= count($bulk['nahi']) ?> फ़ोटो का सामान नहीं मिला</b> —
          इनका नाम बदलकर नंबर रख दीजिए, फिर दोबारा भेजिए:</p>
        <div style="font-size:14px;line-height:1.8"><?php foreach ($bulk['nahi'] as $f): ?>
          <code><?= h($f) ?></code>&nbsp; <?php endforeach; ?></div>
      <?php endif; ?>

      <?php if ($bulk['kharab']): ?>
        <p style="margin-top:14px;color:#B02A2A"><b><?= count($bulk['kharab']) ?> फ़ोटो चढ़ नहीं पाई</b>
          (बहुत बड़ी है, या तस्वीर नहीं है):</p>
        <div style="font-size:14px;line-height:1.8"><?php foreach ($bulk['kharab'] as $f): ?>
          <code><?= h($f) ?></code>&nbsp; <?php endforeach; ?></div>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</div>

<!-- =============== SEWA / TILE KI PHOTO =============== -->
<div class="phbox" id="sewa">
  <h3>सेवा और हिस्सों की फ़ोटो</h3>
  <p>ये वही डिब्बे हैं जो <b>होम पेज</b> पर दिखते हैं — गाड़ी, लॉन, टेंट, किताबें, दुकानें।
     अभी इनमें चित्र (icon) है। असली फ़ोटो लगाते ही होम पेज बदल जाएगा।</p>
  <p>एक अच्छी फ़ोटो — जैसे अपनी बोलेरो की, या किसी लॉन की जहाँ आपने डिलीवरी की थी।
     <b>Google से उठाई हुई फ़ोटो मत लगाइए।</b></p>

  <div class="phgrid" id="svcGrid">
    <?php
      $SV = [
        'grocery'  => 'राशन और सामान',
        'food'     => 'बना खाना, मिठाई',
        'medicine' => 'दवाई',
        'books'    => 'पुरानी किताबें',
        'ride'     => 'गाड़ी बुकिंग',
        'truck'    => 'माल ढुलाई',
        'lawn'     => 'लॉन / हॉल',
        'tent'     => 'टेंट, साउंड',
        'halwai'   => 'हलवाई',
        'pandit'   => 'पंडित जी',
        'salon'    => 'नाई / पार्लर',
        'home'     => 'घर की मरम्मत',
        'photo'    => 'फोटो / वीडियो',
        'shops'    => 'दुकानें',
      ];
      foreach ($SV as $k => $lbl): $sp = svc_photo($k); ?>
      <div class="pht <?= $sp ? 'ok' : '' ?>" data-svc="<?= h($k) ?>">
        <span class="tick">&#10004;</span>
        <div class="sq">
          <?php if ($sp): ?><img src="/uploads/<?= h($sp) ?>?v=<?= @filemtime($UP . '/' . $sp) ?>" alt="">
          <?php else: ?><?= svc_icon($k, 46) ?><?php endif; ?>
        </div>
        <div class="nm"><?= h($lbl) ?></div>
        <div class="un">होम पेज का डिब्बा</div>
        <label>
          <span class="lb"><?= $sp ? 'बदलिए' : 'फ़ोटो लगाइए' ?></span>
          <input type="file" accept="image/*">
        </label>
        <div class="er"></div>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<!-- =============== NUMBER LIST =============== -->
<div class="phbox">
  <h3>नंबर की सूची</h3>
  <p>फ़ोटो का नाम इनमें से नंबर रख दीजिए। <?= $sh === 'nophoto' ? 'यहाँ सिर्फ़ वही हैं जिनकी फ़ोटो बाक़ी है।' : '' ?></p>
  <div class="phnums">
    <?php foreach ($rows as $r): ?>
      <div><b><?= (int)$r['id'] ?></b> — <?= h($r['name']) ?> <span style="color:#6B4A2F"><?= h($r['unit']) ?></span></div>
    <?php endforeach; ?>
  </div>
</div>

</div>

<script>
(function(){
  var csrf = <?= json_encode(csrf()) ?>;
  var kul  = <?= (int)$kul ?>, done = <?= (int)$done ?>;
  var fill = document.getElementById('phFill');

  function lagao(grid){
  if (!grid) return;
  grid.addEventListener('change', function(e){
    var inp = e.target; if (!inp.matches('input[type=file]')) return;
    var tile = inp.closest('.pht'); if (!tile || !inp.files.length) return;

    var er = tile.querySelector('.er'); er.style.display = 'none'; er.textContent = '';
    var lb = tile.querySelector('.lb'), old = lb.textContent;
    var pehle_se = tile.classList.contains('ok');
    tile.classList.add('busy');

    var file = inp.files[0];
    inp.value = '';                                   // ab file humare paas hai

    // dheeme net par 5 MB ki photo atak jaati hai — pehle chhoti kar lo
    lb.textContent = 'छोटी की जा रही है…';
    var kaam = (window.mkShrink ? window.mkShrink(file) : Promise.resolve(file));

    kaam.then(function(chhoti){
      lb.textContent = 'चढ़ रही है…';
      var fd = new FormData();
      fd.append('csrf', csrf);
      if (tile.dataset.svc) fd.append('svc', tile.dataset.svc);
      else                  fd.append('id',  tile.dataset.id);
      fd.append('photo', chhoti, 'photo.jpg');

      return fetch('/admin/photos.php?ajax=1', { method:'POST', body:fd, credentials:'same-origin' })
        .then(function(r){
          return r.text().then(function(txt){
            try { return JSON.parse(txt); }
            catch(_){ return { ok:false, msg:'सर्वर ने अजीब जवाब दिया (' + r.status + ')। दोबारा कीजिए।' }; }
          });
        });
    }).then(function(j){
      tile.classList.remove('busy');
      if (j && j.ok) {
        var sq = tile.querySelector('.sq');
        sq.innerHTML = '<img alt="">';
        sq.firstChild.src = '/uploads/' + j.photo + '?v=' + Date.now();
        tile.classList.add('ok');
        lb.textContent = 'बदलिए';
        if (!pehle_se && !tile.dataset.svc) { done++; if (fill) fill.style.width = Math.round(done*100/kul) + '%'; }
      } else {
        lb.textContent = old;
        er.textContent = (j && j.msg) ? j.msg : 'नहीं चढ़ी। दोबारा कीजिए।';
        er.style.display = 'block';
      }
    })['catch'](function(){
      tile.classList.remove('busy'); lb.textContent = old;
      er.textContent = 'नेट बीच में कट गया। दोबारा कीजिए।'; er.style.display = 'block';
    });
  });
  }

  lagao(document.getElementById('phGrid'));    // saaman
  lagao(document.getElementById('svcGrid'));   // sewa ke tile
})();
</script>
<?php include __DIR__ . '/../inc/foot.php'; ?>
