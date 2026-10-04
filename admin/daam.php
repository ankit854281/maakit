<?php
require_once __DIR__ . '/../inc/fn.php';
require_once __DIR__ . '/../inc/icons.php';
require_once __DIR__ . '/../inc/items.php';
require_once __DIR__ . '/../inc/groups.php';
require_once __DIR__ . '/../inc/daam.php';
$u = need_role('admin');
$page_title = 'दाम और ब्रांड — Maakit';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_ok()) {
    $do   = post('do');
    $back = '/admin/daam.php?v=' . urlencode(post('v', 'daam'));

    if ($do === 'brandnew') {
        $n = trim(post('name'));
        if (mb_strlen($n) >= 2) {
            $pdo->prepare("INSERT IGNORE INTO brands (name, name_en, kind, sort_no) VALUES (?,?,?,?)")
                ->execute([mb_substr($n, 0, 60), mb_substr(trim(post('name_en')), 0, 60) ?: null,
                           post('kind') === 'local' ? 'local' : 'company', (int)post('sort_no')]);
            flash('“' . $n . '” जुड़ गया।');
        }
    } elseif ($do === 'brandoff') {
        $pdo->prepare("UPDATE brands SET active = 1 - active WHERE id=?")->execute([(int)post('id')]);
        flash('ब्रांड बदल दिया गया।');
    } elseif ($do === 'link') {
        $iid = (int)post('item_id');
        $pdo->prepare("DELETE FROM item_brands WHERE item_id=?")->execute([$iid]);
        $ins = $pdo->prepare("INSERT IGNORE INTO item_brands (item_id, brand_id, sort_no) VALUES (?,?,?)");
        foreach ((array)($_POST['bid'] ?? []) as $k => $bid) $ins->execute([$iid, (int)$bid, (int)$k]);
        flash('इस सामान के ब्रांड सेट हो गए।');
        $back = '/admin/daam.php?v=jod&item=' . $iid;
    } elseif ($do === 'badkill') {
        $pid = (int)post('id');
        $r = $pdo->prepare("SELECT item_id, brand_id FROM item_prices WHERE id=?");
        $r->execute([$pid]); $row = $r->fetch();
        $pdo->prepare("UPDATE item_prices SET ok=0 WHERE id=?")->execute([$pid]);
        if ($row) {
            daam_banao($pdo, (int)$row['item_id'], 0);
            if ($row['brand_id']) daam_banao($pdo, (int)$row['item_id'], (int)$row['brand_id']);
        }
        flash('वह दाम हटा दिया गया और हिसाब दोबारा लगा दिया गया।');
    }
    redirect($back);
}

$v  = get('v', 'daam');
$fl = flash();

$items  = items_all($pdo);
$brands = brands_all($pdo, false);
$IBR    = item_brands_all($pdo);
$DAAM   = daam_sab($pdo);

$kul      = count($items);
$pata     = count($DAAM);
$aaj      = (int)$pdo->query("SELECT COUNT(*) FROM item_prices WHERE DATE(created_at)=CURDATE()")->fetchColumn();
$kul_rec  = (int)$pdo->query("SELECT COUNT(*) FROM item_prices WHERE ok=1")->fetchColumn();

include __DIR__ . '/../inc/panel.php';
?>
<section>
<div class="wrap">
  <h2>दाम और ब्रांड</h2>
  <p class="lead">
    Maakit दाम तय नहीं करता — <b>हर बिल से सीखता है</b>। BPO जब ऑर्डर का दाम लिखता है,
    वही यहाँ जमा होता जाता है और ग्राहक को अंदाज़े के तौर पर दिखता है।
  </p>

  <?php if ($fl): ?><div class="ok"><?= h($fl) ?></div><?php endif; ?>

  <div class="kpis">
    <div class="kpi"><div class="k"><?= svc_icon('rupee', 14) ?> दाम पता है</div>
      <div class="v"><?= $pata ?> <span style="font-size:17px;color:var(--muted)">/ <?= $kul ?></span></div>
      <div class="d">बाक़ी का दाम अभी नहीं पता</div></div>
    <div class="kpi"><div class="k"><?= svc_icon('bill', 14) ?> आज लिखे गए</div>
      <div class="v"><?= $aaj ?></div><div class="d">आज के बिलों से</div></div>
    <div class="kpi"><div class="k"><?= svc_icon('box', 14) ?> कुल दर्ज</div>
      <div class="v"><?= $kul_rec ?></div><div class="d">अब तक की सारी ख़रीद</div></div>
    <div class="kpi"><div class="k"><?= svc_icon('tag', 14) ?> ब्रांड</div>
      <div class="v"><?= count(array_filter($brands, fn($b) => $b['active'])) ?></div>
      <div class="d">चालू ब्रांड</div></div>
  </div>

  <div class="chips" style="margin-bottom:14px">
    <a class="chip <?= $v === 'daam' ? 'on' : '' ?>" href="?v=daam">सीखे हुए दाम</a>
    <a class="chip <?= $v === 'nahi' ? 'on' : '' ?>" href="?v=nahi">जिनका दाम नहीं पता (<?= $kul - $pata ?>)</a>
    <a class="chip <?= $v === 'brand' ? 'on' : '' ?>" href="?v=brand">ब्रांड की लिस्ट</a>
    <a class="chip <?= $v === 'jod' ? 'on' : '' ?>" href="?v=jod">सामान में ब्रांड जोड़िए</a>
  </div>

<?php /* ============ सीखे हुए दाम ============ */ if ($v === 'daam'): ?>

  <?php
    $rows = $pdo->query("SELECT p.*, i.name, i.unit, i.grp, b.name AS bname, uu.name AS uname
                         FROM item_prices p
                         JOIN items i ON i.id = p.item_id
                         LEFT JOIN brands b ON b.id = p.brand_id
                         LEFT JOIN users uu ON uu.id = p.by_user
                         WHERE p.ok = 1 ORDER BY p.id DESC LIMIT 150")->fetchAll();
  ?>
  <?php if (!$rows): ?>
    <div class="empty"><div class="e"><?= svc_icon('rupee', 44) ?></div>
      <b>अभी कोई दाम दर्ज नहीं हुआ।</b>
      <p class="help" style="max-width:420px;margin:8px auto 0">
        BPO पैनल में किसी ऑर्डर पर <b>“दाम लिखिए”</b> दबाइए और बिल के दाम भर दीजिए।
        दो ख़रीद के बाद वह सामान ग्राहकों को दिखने लगेगा।
      </p></div>
  <?php else: ?>
    <div class="tablewrap">
      <table>
        <tr><th>सामान</th><th>ब्रांड</th><th>दाम</th><th>अभी दिख रहा</th><th>कब · किसने</th><th></th></tr>
        <?php foreach ($rows as $r): $d = $DAAM[(int)$r['item_id']] ?? null; ?>
          <tr>
            <td><b><?= h($r['name']) ?></b><div class="help"><?= h($r['unit']) ?></div></td>
            <td><?= $r['bname'] ? h($r['bname']) : '<span class="help">—</span>' ?></td>
            <td style="font-weight:700">₹<?= (int)$r['price'] ?></td>
            <td><?= $d ? h(daam_likhawat($d)) . '<div class="help">' . (int)$d['n'] . ' ख़रीद से</div>'
                       : '<span class="help">अभी नहीं</span>' ?></td>
            <td class="help"><?= h(ago($r['created_at'])) ?><?= $r['uname'] ? '<br>' . h($r['uname']) : '' ?></td>
            <td>
              <form method="post" onsubmit="return confirm('यह दाम ग़लत मानकर हटा दें?')">
                <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
                <input type="hidden" name="do" value="badkill">
                <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                <input type="hidden" name="v" value="daam">
                <button class="btn btn-sm btn-ghost" style="color:#B02A2A">ग़लत है</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </table>
    </div>
  <?php endif; ?>

<?php /* ============ जिनका दाम नहीं पता ============ */ elseif ($v === 'nahi'): ?>

  <p class="help" style="margin-bottom:12px">
    ये सामान ग्राहक को बिना दाम के दिखते हैं। जब इनका ऑर्डर आए, BPO से दाम लिखवा दीजिए —
    दो बार के बाद अपने आप दिखने लगेगा। <b>सबसे ज़्यादा बिकने वाले पहले हैं।</b>
  </p>
  <div class="admgrid">
    <?php
      $bina = array_values(array_filter($items, fn($i) => !isset($DAAM[(int)$i['id']])));
      usort($bina, fn($a, $b) => ((int)$b['popular'] <=> (int)$a['popular']) ?: ((int)$a['id'] <=> (int)$b['id']));
      foreach (array_slice($bina, 0, 120) as $i): ?>
      <div class="admit">
        <div style="display:flex;gap:9px;align-items:center">
          <span style="width:38px;height:38px;border-radius:9px;background:var(--soft);display:grid;place-items:center;color:var(--brand);flex-shrink:0;overflow:hidden">
            <?= item_thumb($i, 22) ?></span>
          <div style="min-width:0">
            <b style="font-size:14.5px;line-height:1.25;display:block"><?= h($i['name']) ?></b>
            <span class="help"><?= h($i['unit']) ?><?= $i['popular'] ? ' · ★' : '' ?></span>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

<?php /* ============ ब्रांड की लिस्ट ============ */ elseif ($v === 'brand'): ?>

  <div class="box" style="margin-bottom:16px">
    <b style="font-size:17px">नया ब्रांड जोड़िए</b>
    <p class="help" style="margin:5px 0 12px">
      एक ही ब्रांड को दो तरह से मत लिखिए — “आशीर्वाद” और “Aashirvaad” अलग गिने जाएँगे
      और दाम का हिसाब बँट जाएगा।
    </p>
    <form method="post" style="display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end">
      <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
      <input type="hidden" name="do" value="brandnew">
      <input type="hidden" name="v" value="brand">
      <div style="min-width:160px"><label>नाम (हिंदी)</label><input name="name" required maxlength="60" placeholder="जैसे: आशीर्वाद"></div>
      <div style="min-width:160px"><label>नाम (English)</label><input name="name_en" maxlength="60" placeholder="Aashirvaad"></div>
      <div style="min-width:130px"><label>किस तरह का</label>
        <select name="kind"><option value="company">कंपनी का</option><option value="local">लोकल / खुला</option></select></div>
      <div style="max-width:90px"><label>क्रम</label><input type="number" name="sort_no" value="50"></div>
      <button class="btn btn-brand btn-sm">जोड़िए</button>
    </form>
  </div>

  <div class="tablewrap">
    <table>
      <tr><th>ब्रांड</th><th>English</th><th>तरह</th><th>कितने सामान में</th><th></th></tr>
      <?php
        $gin = [];
        foreach ($IBR as $iid => $bs) foreach ($bs as $b) $gin[(int)$b['id']] = ($gin[(int)$b['id']] ?? 0) + 1;
        foreach ($brands as $b): ?>
        <tr style="<?= $b['active'] ? '' : 'opacity:.5' ?>">
          <td><b><?= h($b['name']) ?></b></td>
          <td class="help"><?= h($b['name_en'] ?: '—') ?></td>
          <td class="help"><?= $b['kind'] === 'local' ? 'लोकल' : 'कंपनी' ?></td>
          <td><?= (int)($gin[(int)$b['id']] ?? 0) ?></td>
          <td>
            <form method="post">
              <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
              <input type="hidden" name="do" value="brandoff">
              <input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
              <input type="hidden" name="v" value="brand">
              <button class="btn btn-sm btn-ghost"><?= $b['active'] ? 'बंद कीजिए' : 'चालू कीजिए' ?></button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </table>
  </div>

<?php /* ============ सामान में ब्रांड जोड़िए ============ */ else:
    $sel = (int)get('item');
    $cur = array_map(fn($b) => (int)$b['id'], $IBR[$sel] ?? []);
?>

  <div class="box" style="margin-bottom:14px">
    <b style="font-size:17px">किस सामान में कौन से ब्रांड मिलते हैं</b>
    <p class="help" style="margin:5px 0 12px">
      सिर्फ़ उन्हीं सामान में ब्रांड जोड़िए जिनमें दाम का फ़र्क़ पड़ता है —
      आटा, तेल, चाय, साबुन। आलू-प्याज़ में ब्रांड का कोई मतलब नहीं।
    </p>
    <form method="get" style="display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end">
      <input type="hidden" name="v" value="jod">
      <div style="flex:1;min-width:220px"><label>सामान चुनिए</label>
        <select name="item" onchange="this.form.submit()">
          <option value="">— चुनिए —</option>
          <?php foreach (item_groups() as $gk => $gn): ?>
            <optgroup label="<?= h($gn) ?>">
              <?php foreach ($items as $i): if ($i['grp'] !== $gk) continue; ?>
                <option value="<?= (int)$i['id'] ?>" <?= $sel === (int)$i['id'] ? 'selected' : '' ?>>
                  <?= h($i['name']) ?> — <?= h($i['unit']) ?>
                  <?= !empty($IBR[(int)$i['id']]) ? ' (' . count($IBR[(int)$i['id']]) . ')' : '' ?>
                </option>
              <?php endforeach; ?>
            </optgroup>
          <?php endforeach; ?>
        </select></div>
    </form>
  </div>

  <?php if ($sel): ?>
    <form method="post" class="box">
      <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
      <input type="hidden" name="do" value="link">
      <input type="hidden" name="item_id" value="<?= $sel ?>">
      <b style="font-size:16px">जो मिलते हैं उन पर टिक कीजिए</b>
      <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(170px,1fr));gap:9px;margin:12px 0 14px">
        <?php foreach ($brands as $b): if (!$b['active']) continue; ?>
          <label style="display:flex;gap:8px;align-items:center;background:var(--soft);border-radius:10px;padding:9px 12px;cursor:pointer">
            <input type="checkbox" name="bid[]" value="<?= (int)$b['id'] ?>" <?= in_array((int)$b['id'], $cur, true) ? 'checked' : '' ?>>
            <span><?= h($b['name']) ?></span>
          </label>
        <?php endforeach; ?>
      </div>
      <button class="btn btn-brand">सेव कीजिए</button>
      <p class="help" style="margin-top:10px">
        ग्राहक को “कोई भी ब्रांड” पहले से चुना हुआ मिलेगा — उसे कुछ चुनना ज़रूरी नहीं।
      </p>
    </form>
  <?php endif; ?>

<?php endif; ?>

</div>
</section>
<?php include __DIR__ . '/../inc/foot.php'; ?>
