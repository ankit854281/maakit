<?php
require_once __DIR__ . '/../inc/fn.php';
$me = need_role('admin');
$page_title = 'टीम — Maakit';
$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_ok()) {
    if (post('do') === 'add') {
        $un = preg_replace('/[^a-z0-9_]/', '', strtolower(post('username')));
        if (mb_strlen(post('name')) < 2 || strlen($un) < 3 || strlen(post('password')) < 6) {
            $err = 'नाम, यूज़रनेम (कम से कम 3 अक्षर) और पासवर्ड (कम से कम 6 अक्षर) ज़रूरी हैं।';
        } else {
            try {
                $pdo->prepare("INSERT INTO users (name, username, password, role, mobile) VALUES (?,?,?,?,?)")
                    ->execute([post('name'), $un, password_hash(post('password'), PASSWORD_DEFAULT), post('role'), preg_replace('/\D/', '', post('mobile'))]);
                flash('नया लॉगिन बन गया।'); redirect('/admin/users.php');
            } catch (PDOException $e) { $err = 'यह यूज़रनेम पहले से है।'; }
        }
    } elseif (post('do') === 'pass') {
        if (strlen(post('password')) < 6) { $err = 'पासवर्ड कम से कम 6 अक्षर का रखिए।'; }
        else { $pdo->prepare("UPDATE users SET password=? WHERE id=?")->execute([password_hash(post('password'), PASSWORD_DEFAULT), (int)post('id')]); flash('पासवर्ड बदल दिया गया।'); redirect('/admin/users.php'); }
    } elseif (post('do') === 'active') {
        $pdo->prepare("UPDATE users SET active=? WHERE id=? AND id<>?")->execute([(int)post('active'), (int)post('id'), $me['id']]);
        flash('बदल दिया गया।'); redirect('/admin/users.php');
    }
}
$rows = $pdo->query("SELECT * FROM users ORDER BY role, name")->fetchAll();
include __DIR__ . '/../inc/panel.php';
?>
<section><div class="wrap">
  <h2>टीम के लॉगिन</h2>
  <?php if ($err): ?><div class="err"><?= h($err) ?></div><?php endif; ?>
  <div class="tablewrap"><table>
    <tr><th>नाम</th><th>यूज़रनेम</th><th>काम</th><th>मोबाइल</th><th>चालू</th><th>नया पासवर्ड</th></tr>
    <?php foreach ($rows as $r): ?>
    <tr>
      <td><?= h($r['name']) ?></td><td><?= h($r['username']) ?></td>
      <td><?= (['admin'=>'मालिक','bpo'=>'BPO','delivery'=>'डिलीवरी','designer'=>'डिज़ाइनर'][$r['role']] ?? $r['role']) ?></td>
      <td><?= h($r['mobile']) ?></td>
      <td>
        <?php if ((int)$r['id'] !== (int)$me['id']): ?>
        <form method="post"><input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="do" value="active"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="active" value="<?= $r['active'] ? 0 : 1 ?>"><button class="btn btn-sm <?= $r['active'] ? 'btn-green' : '' ?>" style="<?= $r['active'] ? '' : 'background:#EFEAE0' ?>"><?= $r['active'] ? 'चालू' : 'बंद' ?></button></form>
        <?php else: ?>चालू<?php endif; ?>
      </td>
      <td>
        <form method="post" style="display:flex;gap:6px"><input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="do" value="pass"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
          <input type="text" name="password" placeholder="नया पासवर्ड" style="max-width:150px"><button class="btn btn-brand btn-sm">बदलिए</button></form>
      </td>
    </tr>
    <?php endforeach; ?>
  </table></div>

  <div class="box" style="margin-top:20px">
    <h3 style="margin-top:0">नया लॉगिन बनाइए</h3>
    <form method="post" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">
      <input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="do" value="add">
      <div style="min-width:160px"><label>नाम</label><input type="text" name="name" required></div>
      <div style="min-width:150px"><label>यूज़रनेम</label><input type="text" name="username" required></div>
      <div style="min-width:150px"><label>पासवर्ड</label><input type="text" name="password" required></div>
      <div style="min-width:150px"><label>काम</label><select name="role"><option value="bpo">BPO</option><option value="delivery">डिलीवरी पार्टनर</option><option value="designer">डिज़ाइनर (ऑफ़र और फ़ोटो)</option><option value="admin">मालिक</option></select></div>
      <div style="min-width:140px"><label>मोबाइल</label><input type="tel" name="mobile"></div>
      <button class="btn btn-brand btn-sm">बनाइए</button>
    </form>
  </div>
</div></section>
<?php include __DIR__ . '/../inc/foot.php'; ?>
