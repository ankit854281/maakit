<?php
require_once __DIR__ . '/../inc/fn.php';
$u = need_role('admin');
$page_title = 'नए गाँव की माँग — Maakit';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_ok()) {
    $do = post('do');
    if ($do === 'status') {
        $v = post('village'); $st = post('status');
        if (in_array($st, ['new','planned','live','no'], true) && $v !== '') {
            $pdo->prepare("UPDATE area_requests SET status=? WHERE village=?")->execute([$st, $v]);
            flash('“' . $v . '” का स्टेटस बदल दिया गया।');
        }
    } elseif ($do === 'addvill') {
        $n = trim(post('name'));
        $rk = (int)post('rate_kapsethi'); $rc = (int)post('rate_chauri'); $rw = (int)post('rate_kachhawa');
        if (mb_strlen($n) >= 2) {
            $pdo->prepare("INSERT INTO villages (name, rate_kapsethi, rate_chauri, rate_kachhawa, live)
                           VALUES (?,?,?,?,1) ON DUPLICATE KEY UPDATE rate_kapsethi=VALUES(rate_kapsethi),
                           rate_chauri=VALUES(rate_chauri), rate_kachhawa=VALUES(rate_kachhawa), live=1")
                ->execute([$n, $rk, $rc, $rw]);
            $pdo->prepare("UPDATE area_requests SET status='live' WHERE village=?")->execute([$n]);
            flash('“' . $n . '” अब चालू है। लोगों को फ़ोन कर दीजिए।');
        }
    }
    redirect('/admin/areas.php');
}

$grp = $pdo->query("SELECT village, COUNT(*) c, MAX(created_at) last_at,
                           MAX(status) st, GROUP_CONCAT(DISTINCT block SEPARATOR ', ') blocks
                    FROM area_requests GROUP BY village ORDER BY
                    FIELD(MAX(status),'new','planned','live','no'), c DESC")->fetchAll();
$live_v = $pdo->query("SELECT name FROM villages")->fetchAll(PDO::FETCH_COLUMN);
include __DIR__ . '/../inc/panel.php';
?>
<section>
<div class="wrap">
  <h2>नए गाँव की माँग</h2>
  <p class="lead">जिन गाँवों से लोग माँग रहे हैं वो यहाँ हैं। सबसे ऊपर वाला गाँव सबसे ज़्यादा फ़ायदे का है —
    वहाँ ग्राहक पहले से तैयार बैठे हैं।</p>

  <?php if (!$grp): ?>
    <div class="box">अभी किसी नए गाँव से माँग नहीं आई। <a href="/area.php" target="_blank">माँगने वाला पेज देखिए</a></div>
  <?php endif; ?>

  <?php foreach ($grp as $g):
    $already = in_array($g['village'], $live_v, true);
    $lbl = ['new'=>['pill-new','नया'],'planned'=>['pill-run','योजना में'],'live'=>['pill-done','चालू है'],'no'=>['pill-bad','अभी नहीं']][$g['st']] ?? ['pill-new','नया'];
    $ppl = $pdo->prepare("SELECT name, mobile, note, created_at FROM area_requests WHERE village=? ORDER BY id DESC LIMIT 30");
    $ppl->execute([$g['village']]); $people = $ppl->fetchAll();
    $walist = implode("\n", array_map(fn($p) => $p['mobile'] . ($p['name'] ? ' (' . $p['name'] . ')' : ''), $people)); ?>
    <div class="box" style="margin-bottom:14px">
      <div style="display:flex;justify-content:space-between;flex-wrap:wrap;gap:8px;align-items:center">
        <div>
          <b style="font-size:20px"><?= h($g['village']) ?></b>
          <span class="pill <?= h($lbl[0]) ?>"><?= h($lbl[1]) ?></span>
          <span class="tag tag-gold"><?= (int)$g['c'] ?> लोग माँग रहे हैं</span>
          <?php if ($already): ?><span class="tag tag-live">रेट सेट है</span><?php endif; ?>
        </div>
        <div class="meta">आख़िरी माँग <?= h(date('d M, h:i A', strtotime($g['last_at']))) ?></div>
      </div>
      <?php if ($g['blocks']): ?><div class="meta" style="margin-top:4px">पास का बाज़ार: <?= h($g['blocks']) ?></div><?php endif; ?>

      <details style="margin-top:10px">
        <summary style="cursor:pointer;font-weight:600"><?= (int)$g['c'] ?> लोगों के नंबर देखिए</summary>
        <div class="tablewrap" style="margin-top:10px">
          <table>
            <tr><th>नाम</th><th>नंबर</th><th>क्या कहा</th><th>कब</th></tr>
            <?php foreach ($people as $p): ?>
              <tr>
                <td><?= h($p['name'] ?: '—') ?></td>
                <td><a href="tel:+91<?= h($p['mobile']) ?>"><?= h($p['mobile']) ?></a> ·
                    <a href="<?= h(wa_link($p['mobile'], "नमस्ते! Maakit अब " . $g['village'] . " में भी शुरू हो रहा है। आपने माँगा था — अब ऑर्डर कर सकते हैं। maakit.in")) ?>" target="_blank" rel="noopener">WhatsApp</a></td>
                <td><?= h($p['note'] ?: '—') ?></td>
                <td><?= h(date('d/m', strtotime($p['created_at']))) ?></td>
              </tr>
            <?php endforeach; ?>
          </table>
        </div>
      </details>

      <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;margin-top:12px">
        <form method="post" style="display:flex;gap:6px;align-items:flex-end">
          <input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="do" value="status">
          <input type="hidden" name="village" value="<?= h($g['village']) ?>">
          <div><label>स्टेटस</label>
            <select name="status" style="min-width:140px">
              <option value="new"     <?= $g['st'] === 'new' ? 'selected' : '' ?>>नया</option>
              <option value="planned" <?= $g['st'] === 'planned' ? 'selected' : '' ?>>योजना में</option>
              <option value="live"    <?= $g['st'] === 'live' ? 'selected' : '' ?>>चालू है</option>
              <option value="no"      <?= $g['st'] === 'no' ? 'selected' : '' ?>>अभी नहीं</option>
            </select></div>
          <button class="btn btn-brand btn-sm">सेव</button>
        </form>

        <?php if (!$already): ?>
          <details>
            <summary class="btn btn-gold btn-sm" style="cursor:pointer;display:inline-flex">यहाँ शुरू कीजिए</summary>
            <form method="post" style="display:flex;gap:6px;align-items:flex-end;flex-wrap:wrap;margin-top:10px">
              <input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="do" value="addvill">
              <input type="hidden" name="name" value="<?= h($g['village']) ?>">
              <div style="max-width:120px"><label>कपसेठी ₹</label><input type="number" name="rate_kapsethi" value="50"></div>
              <div style="max-width:120px"><label>चौरी ₹</label><input type="number" name="rate_chauri" value="50"></div>
              <div style="max-width:120px"><label>कछवा ₹</label><input type="number" name="rate_kachhawa" value="75"></div>
              <button class="btn btn-brand btn-sm">गाँव चालू कीजिए</button>
            </form>
            <p class="help" style="margin-top:6px">चालू करते ही यह गाँव ऑर्डर फ़ॉर्म और ऊपर की पट्टी में दिखने लगेगा।</p>
          </details>
        <?php endif; ?>
      </div>
    </div>
  <?php endforeach; ?>
  <div style="height:24px"></div>
</div>
</section>
<?php include __DIR__ . '/../inc/foot.php'; ?>
