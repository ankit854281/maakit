<?php
require_once __DIR__ . '/inc/fn.php';
require_once __DIR__ . '/inc/salon.php';
require_once __DIR__ . '/inc/dukan.php';
$tab = 'kaam';
$id = (int)get('id');
$st = $pdo->prepare("SELECT *, (queue_updated IS NOT NULL AND queue_updated > (NOW() - INTERVAL 60 MINUTE)) AS is_live FROM businesses WHERE id=? AND status='approved'");
$st->execute([$id]);
$b = $st->fetch();
if (!$b) { http_response_code(404);
$page_title = 'नहीं मिला'; include __DIR__ . '/inc/head.php'; echo '<section><div class="wrap"><h2>यह पेज नहीं मिला</h2><a class="btn btn-brand" href="/">होम</a></div></section>'; include __DIR__ . '/inc/foot.php'; exit; }

$c = cat_by_slug($b['category']);
$page_title = $b['name'] . ' — Maakit';
$msg = ''; $err = '';

// feedback
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_ok() && post('do') === 'feedback') {
    $fn = post('fname'); $fr = (int)post('rating', 5); $fc = post('comment'); $fv = post('fvillage');
    if (mb_strlen($fn) < 2) { $err = 'अपना नाम लिखिए।'; }
    else {
        $pdo->prepare("INSERT INTO feedback (business_id, name, village, rating, comment) VALUES (?,?,?,?,?)")
            ->execute([$id, $fn, $fv, max(1, min(5, $fr)), $fc]);
        $msg = 'धन्यवाद! आपकी राय भेज दी गई है, जाँच के बाद यहाँ दिखेगी।';
    }
}

$fb = $pdo->prepare("SELECT * FROM feedback WHERE business_id=? AND status='approved' ORDER BY id DESC LIMIT 20");
$fb->execute([$id]); $reviews = $fb->fetchAll();
$avg = $pdo->prepare("SELECT ROUND(AVG(rating),1) a, COUNT(*) c FROM feedback WHERE business_id=? AND status='approved'");
$avg->execute([$id]); $ag = $avg->fetch();

include __DIR__ . '/inc/head.php';
?>
<section>
<div class="wrap" style="max-width:820px">
  <?php if ($msg): ?><div class="ok"><?= h($msg) ?></div><?php endif; ?>
  <?php if ($err): ?><div class="err"><?= h($err) ?></div><?php endif; ?>

  <div class="box">
    <div class="biz">
      <span class="ph" style="width:96px;height:96px"><?= $b['photo'] ? '<img src="/uploads/' . h($b['photo']) . '" alt="">' : cat_icon($c['icon'] ?? 'anya', 40) ?></span>
      <div style="flex:1;min-width:0">
        <h2 style="margin:0"><?= h($b['name']) ?></h2>
        <div class="meta"><?= h($c['name'] ?? '') ?><?= $b['village'] ? ' · ' . h($b['village']) : '' ?></div>
        <?php if ($ag['c']): ?><div class="meta"><span class="stars"><?= str_repeat('★', (int)round($ag['a'])) ?></span> <?= h($ag['a']) ?> · <?= (int)$ag['c'] ?> लोगों की राय</div><?php endif; ?>
        <?php if ($b['work']): ?><p style="margin:8px 0 0"><?= h($b['work']) ?></p><?php endif; ?>
      </div>
    </div>
    <?php if ($b['about']): ?><p style="margin-top:14px"><?= nl2br(h($b['about'])) ?></p><?php endif; ?>
    <?php if ($b['address']): ?><p class="meta">पता: <?= h($b['address']) ?></p><?php endif; ?>
    <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:14px">
      <a class="btn btn-green" href="tel:+91<?= h($b['mobile']) ?>">📞 <?= h($b['mobile']) ?></a>
      <a class="btn btn-brand" href="<?= h(wa_link($b['mobile'], 'नमस्ते, मुझे Maakit पर आपका नंबर मिला।')) ?>" target="_blank" rel="noopener">💬 WhatsApp</a>
    </div>
  </div>

  <?php
  // ---- इस दुकान का अपना सामान ----
  // दाम दुकान के अपने हैं, दुकानदार ने ख़ुद चढ़ाए हैं।
  // "ख़त्म" लगा सामान और बंद दुकान यहाँ नहीं दिखती।
  $mera  = dukan_items($pdo, $id, true);
  $khuli = dukan_khuli($b);
  if ($mera): ?>
  <div class="box" style="margin-top:18px;border-color:var(--gold)">
    <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
      <h2 style="margin:0;flex:1">इस दुकान का सामान</h2>
      <span class="tag <?= $khuli ? 'tag-live' : 'tag-off' ?>"><?= $khuli ? 'अभी खुली है' : 'अभी बंद है' ?></span>
    </div>
    <p class="help" style="margin-top:6px">दाम इसी दुकान के हैं — दुकानदार ने ख़ुद डाले हैं।</p>

    <?php foreach (array_slice($mera, 0, 8) as $it): ?>
      <div style="border-top:1px solid var(--line);padding:9px 0;display:flex;gap:10px;align-items:center">
        <?php if ($it['photo']): ?>
          <img src="/uploads/<?= h($it['photo']) ?>" alt="" width="44" height="44"
               style="border-radius:8px;object-fit:cover;flex:none" loading="lazy">
        <?php endif; ?>
        <div style="flex:1;min-width:0">
          <b><?= h($it['name']) ?></b>
          <div class="meta"><?= h($it['unit']) ?></div>
        </div>
        <div style="font-weight:800">₹<?= (int)$it['price'] ?></div>
      </div>
    <?php endforeach; ?>

    <?php if (count($mera) > 8): ?>
      <p class="meta" style="margin-top:8px">और <?= count($mera) - 8 ?> चीज़ें…</p>
    <?php endif; ?>

    <?php if ($khuli): ?>
      <a class="btn btn-brand" style="margin-top:12px;width:100%;text-align:center"
         href="/dukan-se.php?id=<?= $id ?>">इस दुकान से मँगाइए</a>
    <?php else: ?>
      <p class="help" style="margin-top:12px">दुकान खुलने पर यहीं से मँगा सकते हैं।
        समय: <?= h(salon_hm($b['open_time'])) ?> से <?= h(salon_hm($b['close_time'])) ?>।</p>
    <?php endif; ?>
    <p class="help" style="margin-top:8px">सामान का पैसा दुकान का, डिलीवरी का पैसा Maakit का।</p>
  </div>
  <?php endif; ?>

  <?php if (!empty($b['salon_on'])):
      list($dot,$lbl,$cls) = salon_status_label($b);
      $isl = salon_live($b); $board = $isl ? salon_board($pdo, $b) : null; ?>
  <div class="box" style="margin-top:18px">
    <h2 style="margin-top:0">सीट बुकिंग</h2>
    <p><span class="tag <?= h($cls) ?>"><?= $dot ?> <?= h($lbl) ?></span>
       <?= $isl ? '<span class="tag tag-off">' . h(salon_wait_text($board)) . '</span>' : '' ?></p>
    <p class="help">समय: <?= h(salon_hm($b['open_time'])) ?> से <?= h(salon_hm($b['close_time'])) ?></p>
    <a class="btn btn-gold" href="/salon.php?id=<?= (int)$b['id'] ?>">सीट बुक कीजिए</a>
  </div>
  <?php endif; ?>

  <div class="box" style="margin-top:18px">
    <h2 style="margin-top:0">लोगों की राय</h2>
    <?php if (!$reviews): ?><p class="help">अभी कोई राय नहीं आई है। आपने काम करवाया हो तो सबसे पहले बताइए।</p><?php endif; ?>
    <?php foreach ($reviews as $r): ?>
      <div style="border-top:1px solid var(--line);padding:10px 0">
        <div><span class="stars"><?= str_repeat('★', (int)$r['rating']) ?></span> <b><?= h($r['name']) ?></b><?= $r['village'] ? ' <span class="meta">· ' . h($r['village']) . '</span>' : '' ?></div>
        <?php if ($r['comment']): ?><div><?= h($r['comment']) ?></div><?php endif; ?>
      </div>
    <?php endforeach; ?>

    <form method="post" style="margin-top:16px;border-top:2px solid var(--line);padding-top:16px">
      <input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="do" value="feedback">
      <h3 style="margin:0 0 10px">अपनी राय दीजिए</h3>
      <div class="field"><label>आपका नाम</label><input type="text" name="fname" required></div>
      <div class="field"><label>गाँव</label><input type="text" name="fvillage"></div>
      <div class="field"><label>काम कैसा रहा?</label>
        <select name="rating">
          <option value="5">★★★★★ बहुत अच्छा</option><option value="4">★★★★ अच्छा</option>
          <option value="3">★★★ ठीक-ठाक</option><option value="2">★★ कमज़ोर</option><option value="1">★ ख़राब</option>
        </select>
      </div>
      <div class="field"><label>कुछ कहना चाहें (वैकल्पिक)</label><input type="text" name="comment" maxlength="300"></div>
      <button class="btn btn-brand" type="submit">राय भेजिए</button>
      <p class="help">राय जाँच के बाद ही दिखाई जाती है, ताकि कोई झूठी राय न डाल सके।</p>
    </form>
  </div>

  <p class="help" style="margin-top:16px">Maakit सिर्फ़ जानकारी देता है। काम, दाम और लेन-देन आपका अपना है।</p>
</div>
</section>
<?php include __DIR__ . '/inc/foot.php'; ?>
