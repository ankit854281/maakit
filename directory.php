<?php
require_once __DIR__ . '/inc/fn.php';
require_once __DIR__ . '/inc/salon.php';
$q = get('q'); $cat = get('cat');
$tab = 'kaam';
$page_title = 'खोजिए — Maakit';

$sql = "SELECT b.*, (b.salon_updated IS NOT NULL AND b.salon_updated > (NOW() - INTERVAL 180 MINUTE)) AS fresh,
        (SELECT ROUND(AVG(rating),1) FROM feedback f WHERE f.business_id=b.id AND f.status='approved') AS avg_rating,
        (SELECT COUNT(*) FROM feedback f WHERE f.business_id=b.id AND f.status='approved') AS rc
        FROM businesses b WHERE b.status='approved'";
$args = [];
$area=coverage_selected($pdo);
if ($area) { $sql.=' AND EXISTS (SELECT 1 FROM service_area_shops a WHERE a.business_id=b.id AND a.village_id=?)';$args[]=(int)$area['id']; }

if ($cat && cat_by_slug($cat)) { $sql .= " AND b.category=?"; $args[] = $cat; }

if ($q !== '') {
    // shabd se category bhi dhoondhiye (hindi, english, purane shabd)
    $matched = [];
    foreach (categories() as $c) {
        foreach (preg_split('/\s+/u', mb_strtolower($q)) as $word) {
            if (mb_strlen($word) < 3) continue;
            if (mb_strpos(mb_strtolower($c['words'] . ' ' . $c['name']), $word) !== false) { $matched[] = $c['slug']; break; }
        }
    }
    $like = '%' . $q . '%';
    if ($matched) {
        $in = implode(',', array_fill(0, count($matched), '?'));
        $sql .= " AND (b.name LIKE ? OR b.work LIKE ? OR b.about LIKE ? OR b.village LIKE ? OR b.category IN ($in))";
        array_push($args, $like, $like, $like, $like, ...$matched);
    } else {
        $sql .= " AND (b.name LIKE ? OR b.work LIKE ? OR b.about LIKE ? OR b.village LIKE ?)";
        array_push($args, $like, $like, $like, $like);
    }
}
$sql .= " ORDER BY avg_rating DESC, rc DESC, b.id DESC LIMIT 100";
$st = $pdo->prepare($sql); $st->execute($args);
$rows = $st->fetchAll();
include __DIR__ . '/inc/head.php';
?>
<section>
  <div class="wrap">
    <form class="searchbox" action="/directory.php" method="get" style="margin-top:0">
      <input type="text" name="q" value="<?= h($q) ?>" placeholder="क्या ढूंढ रहे हैं? जैसे नलका वाला, plumber, पंडित जी">
      <button class="btn btn-brand btn-sm" type="submit">खोजिए</button>
    </form>
    <div class="chips" style="margin-bottom:18px">
      <a class="chip <?= $cat ? '' : 'on' ?>" href="/directory.php">सब</a>
      <?php foreach (categories() as $c): ?>
        <a class="chip <?= $cat === $c['slug'] ? 'on' : '' ?>" href="/directory.php?cat=<?= h($c['slug']) ?>"><?= h($c['name']) ?></a>
      <?php endforeach; ?>
    </div>

    <h2><?= $cat ? h(cat_by_slug($cat)['name']) : ($q !== '' ? 'खोज: ' . h($q) : 'अपने इलाके के लोग') ?></h2>
    <p class="lead"><?= count($rows) ?> नतीजे</p>

    <?php if (!$rows): ?>
      <div class="box">
        <p>इस खोज में अभी कोई नहीं मिला।</p>
        <p class="help">आप इस काम को करते हैं? अपना नाम मुफ़्त में जोड़िए, लोग आपको ढूंढ पाएँगे।</p>
        <a class="btn btn-brand btn-sm" href="/register-business.php">अपना काम जोड़िए</a>
      </div>
    <?php else: ?>
      <div class="grid g2">
        <?php foreach ($rows as $b):
          $c = cat_by_slug($b['category']);
          $salon = !empty($b['salon_on']);
          $live = $salon && salon_live($b);
          $board = $live ? salon_board($pdo, $b) : null; ?>
          <div class="card biz">
            <span class="ph"><?= $b['photo'] ? '<img src="/uploads/' . h($b['photo']) . '" alt="">' : cat_icon($c['icon'] ?? 'anya', 30) ?></span>
            <div style="min-width:0;flex:1">
              <h3><a href="/business.php?id=<?= (int)$b['id'] ?>" style="text-decoration:none;color:inherit"><?= h($b['name']) ?></a></h3>
              <div class="meta"><?= h($c['name'] ?? '') ?><?= $b['village'] ? ' · ' . h($b['village']) : '' ?></div>
              <?php if ($b['work']): ?><div class="meta" style="color:var(--ink)"><?= h($b['work']) ?></div><?php endif; ?>
              <?php if ($b['avg_rating']): ?>
                <div class="meta"><span class="stars"><?= str_repeat('★', (int)round($b['avg_rating'])) ?></span> <?= h($b['avg_rating']) ?> (<?= (int)$b['rc'] ?>)</div>
              <?php endif; ?>
              <?php if ($salon): list($dot,$lbl,$cls) = salon_status_label($b); ?>
                <div style="margin:6px 0"><span class="tag <?= h($cls) ?>"><?= $dot ?> <?= h($lbl) ?></span>
                <?= $live ? '<span class="tag tag-off">' . h(salon_wait_text($board)) . '</span>' : '' ?></div>
              <?php endif; ?>
              <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:8px">
                <a class="btn btn-green btn-sm" href="tel:+91<?= h($b['mobile']) ?>">📞 कॉल</a>
                <?php if ($salon): ?><a class="btn btn-gold btn-sm" href="/salon.php?id=<?= (int)$b['id'] ?>">सीट बुक कीजिए</a><?php endif; ?>
                <a class="btn btn-brand btn-sm" href="/business.php?id=<?= (int)$b['id'] ?>">देखिए</a>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>
<?php include __DIR__ . '/inc/foot.php'; ?>
