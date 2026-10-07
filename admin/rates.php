<?php
require_once __DIR__ . '/../inc/fn.php';
need_role('admin');
$page_title = 'गाँव और रेट — Maakit';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_ok()) {
    if (post('do') === 'save') {
        $q = $pdo->prepare("UPDATE villages SET rate_kapsethi=?, rate_chauri=?, rate_kachhawa=? WHERE id=?");
        foreach ($_POST['k'] ?? [] as $id => $v) {
            $q->execute([(int)$v, (int)($_POST['c'][$id] ?? 0), (int)($_POST['w'][$id] ?? 0), (int)$id]);
        }
        flash('रेट सेव हो गए।');
    } elseif (post('do') === 'add' && post('name')) {
        $pdo->prepare("INSERT IGNORE INTO villages (name, rate_kapsethi, rate_chauri, rate_kachhawa) VALUES (?,?,?,?)")
            ->execute([post('name'), (int)post('k'), (int)post('c'), (int)post('w')]);
        flash('गाँव जोड़ दिया गया।');
    } elseif (post('do') === 'del') {
        $pdo->prepare("DELETE FROM villages WHERE id=?")->execute([(int)post('id')]);
        flash('गाँव हटा दिया गया।');
    }
    redirect('/admin/rates.php');
}
$rows = $pdo->query("SELECT * FROM villages ORDER BY name")->fetchAll();
include __DIR__ . '/../inc/panel.php';
?>
<section><div class="wrap">
  <h2>गाँव और डिलीवरी रेट</h2>
  <p class="lead">यहाँ बदलेंगे तो वेबसाइट और ऑर्डर फ़ॉर्म, दोनों में अपने आप लग जाएगा। 0 का मतलब “कॉल करके पूछिए”।</p>
  <form method="post">
    <input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="do" value="save">
    <div class="tablewrap"><table>
      <tr><th>गाँव</th><th>कपसेठी ₹</th><th>चौरी ₹</th><th>कछवा ₹</th><th></th></tr>
      <?php foreach ($rows as $v): ?>
      <tr>
        <td><?= h($v['name']) ?></td>
        <td><input type="number" name="k[<?= (int)$v['id'] ?>]" value="<?= (int)$v['rate_kapsethi'] ?>" style="max-width:110px"></td>
        <td><input type="number" name="c[<?= (int)$v['id'] ?>]" value="<?= (int)$v['rate_chauri'] ?>" style="max-width:110px"></td>
        <td><input type="number" name="w[<?= (int)$v['id'] ?>]" value="<?= (int)$v['rate_kachhawa'] ?>" style="max-width:110px"></td>
        <td></td>
      </tr>
      <?php endforeach; ?>
    </table></div>
    <button class="btn btn-brand" style="margin-top:12px">सब सेव कीजिए</button>
  </form>

  <div class="box" style="margin-top:20px">
    <h3 style="margin-top:0">नया गाँव जोड़िए</h3>
    <form method="post" style="display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end">
      <input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="do" value="add">
      <div style="min-width:180px"><label>गाँव का नाम</label><input type="text" name="name" required></div>
      <div style="max-width:120px"><label>कपसेठी ₹</label><input type="number" name="k" value="0"></div>
      <div style="max-width:120px"><label>चौरी ₹</label><input type="number" name="c" value="0"></div>
      <div style="max-width:120px"><label>कछवा ₹</label><input type="number" name="w" value="0"></div>
      <button class="btn btn-brand btn-sm">जोड़िए</button>
    </form>
  </div>
</div></section>
<?php include __DIR__ . '/../inc/foot.php'; ?>
