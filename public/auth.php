<?php
/** Role gateway: preserves the existing authenticated login mechanisms. */
require_once __DIR__.'/../inc/fn.php';
$no_tabbar=true;
$page_title='Join Maakit — Login & Registration';
$role=isset($_GET['role'])&&is_string($_GET['role'])?$_GET['role']:'customer';
if(!in_array($role,['customer','vendor','rider','admin'],true))$role='customer';
$links=[
 'customer'=>['login'=>'/customer-login.php','join'=>'/customer-register.php','label'=>'Customer'],
 'vendor'=>['login'=>'/login.php','join'=>'/register-business.php','label'=>'Vendor / Seller'],
 'rider'=>['login'=>'/login.php?as=team','join'=>'/rider-apply.php','label'=>'Rider'],
 'admin'=>['login'=>'/login.php?as=team','join'=>null,'label'=>'Admin']
];
include __DIR__.'/../inc/head.php';
?>
<section><div class="wrap" style="max-width:720px">
<h1>Welcome to Maakit</h1><p>Choose how you want to use Maakit.</p>
<nav aria-label="Account role" style="display:flex;gap:10px;flex-wrap:wrap;margin:16px 0">
<?php foreach($links as $key=>$item): ?><a class="btn <?= $key===$role?'btn-brand':'btn-line' ?>" href="?role=<?= h($key) ?>" <?= $key===$role?'aria-current="page"':'' ?>><?= h($item['label']) ?></a><?php endforeach; ?>
</nav>
<div class="box">
<h2><?= h($links[$role]['label']) ?> account</h2>
<?php if($role==='admin'): ?><p>Admin accounts are created by the Maakit team. Public admin registration is disabled.</p><?php endif; ?>
<?php if($role==='rider'): ?><p>Rider applications require verification and approval before dispatch access.</p><?php endif; ?>
<p><a class="btn btn-brand" href="<?= h($links[$role]['login']) ?>">Sign in securely</a>
<?php if($links[$role]['join']): ?><a class="btn btn-line" href="<?= h($links[$role]['join']) ?>">Apply / Register</a><?php endif; ?></p>
<p class="help">Authentication is handled by Maakit's existing server-side login. This page does not store JWTs in browser storage or bypass approval.</p>
</div></div></section>
<?php include __DIR__.'/../inc/foot.php';
