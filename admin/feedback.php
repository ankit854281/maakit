<?php
require_once __DIR__ . '/../inc/fn.php';
need_role('admin');
$page_title = 'लोगों की राय — Maakit';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_ok()) {
    $id = (int)post('id');
    if (post('do') === 'ok') { $pdo->prepare("UPDATE feedback SET status='approved' WHERE id=?")->execute([$id]); flash('राय दिखने लगी।'); }
    if (post('do') === 'del') { $pdo->prepare("DELETE FROM feedback WHERE id=?")->execute([$id]); flash('हटा दी गई।'); }
    redirect('/admin/feedback.php');
}
$rows = $pdo->query("SELECT f.*, b.name bname FROM feedback f JOIN businesses b ON b.id=f.business_id ORDER BY f.status='approved', f.id DESC LIMIT 200")->fetchAll();
include __DIR__ . '/../inc/panel.php';
?>
<section><div class="wrap">
  <h2>लोगों की राय</h2>
  <p class="lead">जाँच के बाद ही राय वेबसाइट पर दिखती है, ताकि कोई झूठी राय न डाल सके।</p>
  <?php foreach ($rows as $r): ?>
    <div class="box" style="margin-bottom:10px">
      <div><b><?= h($r['bname']) ?></b> <span class="tag tag-off"><?= $r['status']==='approved' ? 'दिख रही है' : 'जाँच बाकी' ?></span></div>
      <div><span class="stars"><?= str_repeat('★', (int)$r['rating']) ?></span> <?= h($r['name']) ?><?= $r['village'] ? ' · ' . h($r['village']) : '' ?></div>
      <?php if ($r['comment']): ?><div><?= h($r['comment']) ?></div><?php endif; ?>
      <div style="display:flex;gap:8px;margin-top:8px">
        <?php if ($r['status'] !== 'approved'): ?>
        <form method="post"><input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="do" value="ok"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn btn-green btn-sm">दिखाइए</button></form>
        <?php endif; ?>
        <form method="post"><input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="do" value="del"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn btn-sm" style="background:#EFEAE0">हटाइए</button></form>
      </div>
    </div>
  <?php endforeach; ?>
</div></section>
<?php include __DIR__ . '/../inc/foot.php'; ?>
