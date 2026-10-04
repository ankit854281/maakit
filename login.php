<?php
require_once __DIR__ . '/inc/fn.php';
$no_tabbar = true;
$page_title = 'टीम लॉगिन — Maakit';
$err = '';
if (user()) {
    $r = user()['role'];
    redirect($r === 'admin' ? '/admin/' : ($r === 'bpo' ? '/bpo/' : '/delivery/'));
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_ok()) {
    $st = $pdo->prepare("SELECT * FROM users WHERE username=? AND active=1");
    $st->execute([post('username')]);
    $u = $st->fetch();
    if ($u && password_verify(post('password'), $u['password'])) {
        $_SESSION['user'] = ['id' => $u['id'], 'name' => $u['name'], 'role' => $u['role']];
        redirect($u['role'] === 'admin' ? '/admin/' : ($u['role'] === 'bpo' ? '/bpo/' : '/delivery/'));
    }
    $err = 'यूज़रनेम या पासवर्ड ग़लत है।';
}
include __DIR__ . '/inc/head.php';
?>
<section>
<div class="wrap" style="max-width:420px">
  <h2>टीम लॉगिन</h2>
  <p class="lead">यह पेज सिर्फ़ Maakit टीम के लिए है।</p>
  <?php if ($err): ?><div class="err"><?= h($err) ?></div><?php endif; ?>
  <form method="post" class="box">
    <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
    <div class="field"><label>यूज़रनेम</label><input type="text" name="username" required autofocus></div>
    <div class="field"><label>पासवर्ड</label><input type="password" name="password" required></div>
    <button class="btn btn-brand" type="submit">लॉगिन</button>
  </form>
</div>
</section>
<?php include __DIR__ . '/inc/foot.php'; ?>
