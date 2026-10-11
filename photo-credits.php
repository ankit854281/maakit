<?php
// ============================================================
//  फ़ोटो क्रेडिट — मुफ़्त लाइब्रेरी (Wikimedia Commons, Open Food
//  Facts) से आई हर फ़ोटो: किसने खींची, कौन सा लाइसेंस, असली पन्ना।
//  इन लाइसेंस (CC BY / CC BY-SA) की यही शर्त है।
//  फ़ोटो को 900px चौकोर काटा/छोटा किया गया है।
// ============================================================
require_once __DIR__ . '/inc/fn.php';
require_once __DIR__ . '/inc/catalog.php';
$page_title = t('Photo credits — Maakit', 'फ़ोटो क्रेडिट — Maakit');
$PER = 120; $p = max(1, (int)get('p', 1));
$rows = []; $kul = 0;
try {
    $kul = (int)$pdo->query("SELECT COUNT(*) FROM catalog_photos WHERE source IS NOT NULL")->fetchColumn();
    $st = $pdo->query("SELECT p.file, p.credit, p.license, p.source, p.source_url, ci.id, ci.name_en, ci.name_hi
                         FROM catalog_photos p JOIN catalog_items ci ON ci.id=p.cat_id
                        WHERE p.source IS NOT NULL ORDER BY ci.name_en, p.id LIMIT $PER OFFSET " . (($p - 1) * $PER));
    $rows = $st->fetchAll();
} catch (Throwable $e) { $rows = []; }
$pages = max(1, (int)ceil($kul / $PER));
include __DIR__ . '/inc/head.php';
?>
<section class="pfc"><div class="mk-wrap">
  <h1><?= t('Photo credits', 'फ़ोटो क्रेडिट') ?></h1>
  <p class="pfc-lead"><?= t('Many product photos on Maakit come from free photo libraries — Wikimedia Commons and Open Food Facts — under Creative Commons licences. We thank every photographer. Photos are cropped to a square and resized. Click a credit to see the original and its licence. Our own and shop photos are not listed here.', 'Maakit पर कई सामान की फ़ोटो मुफ़्त फ़ोटो लाइब्रेरी — Wikimedia Commons और Open Food Facts — से Creative Commons लाइसेंस के तहत ली गई हैं। हर फ़ोटोग्राफ़र का धन्यवाद। फ़ोटो को चौकोर काटा और छोटा किया गया है। असली फ़ोटो और लाइसेंस देखने के लिए क्रेडिट दबाइए। हमारी और दुकानों की अपनी फ़ोटो यहाँ नहीं हैं।') ?></p>
  <p class="pfc-lead"><?= t('Photo is yours and you want it removed or credited differently?', 'फ़ोटो आपकी है और हटवाना या नाम बदलवाना है?') ?> <a href="/support.php"><?= t('Tell us', 'हमें बताइए') ?></a>.</p>
  <?php if (!$rows): ?><p class="pfc-lead"><?= t('No library photos yet.', 'अभी कोई लाइब्रेरी फ़ोटो नहीं।') ?></p><?php endif; ?>
  <div class="pfc-grid">
    <?php foreach ($rows as $r): ?>
      <figure>
        <a href="<?= h(catalog_url(['product' => (int)$r['id']])) ?>"><img src="/uploads/<?= h($r['file']) ?>" alt="<?= h($r['name_en']) ?>" loading="lazy" width="120" height="120"></a>
        <figcaption><b><?= h(t($r['name_en'], $r['name_hi'] ?: $r['name_en'])) ?></b>
          <?php $txt = h($r['credit']) . ' · ' . h($r['license']) . ' · ' . ($r['source'] === 'off' ? 'Open Food Facts' : 'Wikimedia Commons'); ?>
          <small><?= $r['source_url'] ? '<a href="' . h($r['source_url']) . '" target="_blank" rel="noopener nofollow">' . $txt . '</a>' : $txt ?></small></figcaption>
      </figure>
    <?php endforeach; ?>
  </div>
  <?php if ($pages > 1): ?>
    <p class="pfc-pages"><?php for ($i = 1; $i <= $pages; $i++): ?><a class="<?= $i === $p ? 'on' : '' ?>" href="/photo-credits.php?p=<?= $i ?>"><?= $i ?></a><?php endfor; ?></p>
  <?php endif; ?>
</div></section>
<?php include __DIR__ . '/inc/foot.php'; ?>
