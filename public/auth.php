<?php
/** Role gateway: preserves the existing authenticated login mechanisms. */
require_once __DIR__.'/../inc/fn.php';
require_once __DIR__.'/../inc/salon.php';
header('Cache-Control: no-store');
$no_tabbar=true;
$page_title='Join Maakit — Login & Registration';
$role=isset($_GET['role'])&&is_string($_GET['role'])?$_GET['role']:'customer';
if(!in_array($role,['customer','vendor','rider','admin'],true))$role='customer';
$links=[
 'customer'=>['login'=>'/account.php','join'=>'/account.php','label'=>t('Customer','ग्राहक')],
 'vendor'=>['login'=>'/login.php','join'=>'/public/partner-apply.php?kind=vendor','label'=>t('Vendor / Seller','दुकानदार')],
 'rider'=>['login'=>'/login.php?as=team','join'=>'/public/partner-apply.php?kind=rider','label'=>t('Rider','राइडर')],
 'admin'=>['login'=>'/login.php?as=team','join'=>null,'label'=>t('Admin','एडमिन')]
];
$context=null;$dashboard=$links[$role]['login'];
if($role==='customer' && cust()){$context='customer';$dashboard='/account.php';}
elseif($role==='vendor' && shop_login_business($pdo)){$context='shop';$dashboard='/shop.php';}
elseif(user()){
 $u=Maakit\Api\query($pdo,'SELECT role FROM users WHERE id=? AND active=1',[(int)user()['id']])->fetch();
 if($u && (($role==='admin' && $u['role']==='admin') || ($role==='vendor' && $u['role']==='vendor') || ($role==='rider' && in_array($u['role'],['rider','delivery'],true)))){$context='staff';$dashboard=panel_home($u['role']);}
}
include __DIR__.'/../inc/head.php';
?>
<section><div class="wrap" style="max-width:720px">
<h1><?= t('Welcome to Maakit','Maakit में स्वागत है') ?></h1><p><?= t('Choose how you want to use Maakit.','आप किस रूप में जुड़ना चाहते हैं?') ?></p>
<nav aria-label="Account role" style="display:flex;gap:10px;flex-wrap:wrap;margin:16px 0">
<?php foreach($links as $key=>$item): ?><a class="btn <?= $key===$role?'btn-brand':'btn-line' ?>" href="?role=<?= h($key) ?>" <?= $key===$role?'aria-current="page"':'' ?>><?= h($item['label']) ?></a><?php endforeach; ?>
</nav>
<div class="box" data-auth-role="<?= h($role) ?>" data-csrf="<?= h(csrf()) ?>" <?= $context?'data-auth-context="'.h($context).'"':'' ?>>
<h2><?= h($links[$role]['label']) ?> <?= t('account','खाता') ?></h2>
<?php if($role==='admin'): ?><p><?= t('Admin accounts are created by the Maakit team. Public admin registration is disabled.','एडमिन खाते Maakit टीम बनाती है।') ?></p><?php endif; ?>
<?php if($role==='rider'): ?><p><?= t('Rider applications require verification and approval before dispatch access.','डिलीवरी के लिए राइडर आवेदन की जाँच और मंजूरी जरूरी है।') ?></p><?php endif; ?>
<?php if(!$context): ?>
<form method="post" action="<?= $role==='customer'?'/account.php':'/login.php' ?>" data-role-login>
<input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="do" value="<?= $role==='customer'?'login':($role==='vendor'?'dukan':'team') ?>">
<label><?= $role==='admin'?t('Username','यूज़रनेम'):t('Mobile number','मोबाइल नंबर') ?><input name="<?= in_array($role,['admin','rider'],true)?'username':'mobile' ?>" autocomplete="username" maxlength="40" required></label>
<label><?= $role==='vendor'?t('Login code','लॉगिन कोड'):t('Password','पासवर्ड') ?><input name="<?= $role==='vendor'?'code':'password' ?>" type="password" autocomplete="current-password" maxlength="72" required></label>
<button class="btn btn-brand" type="submit"><?= t('Sign in','लॉगिन करें') ?></button>
<p role="status" data-login-status aria-live="polite"></p></form>
<?php endif; ?>
<p><a class="btn btn-brand" href="<?= h($links[$role]['login']) ?>"><?= t('Sign in securely','लॉगिन कीजिए') ?></a>
<?php if($links[$role]['join']): ?><a class="btn btn-line" href="<?= h($links[$role]['join']) ?>"><?= t('Apply / Register','आवेदन / रजिस्ट्रेशन') ?></a><?php endif; ?></p>
<?php if($context): ?><p role="status" data-auth-status><?= t('Connecting your secure session…','सुरक्षित लॉगिन जुड़ रहा है…') ?></p><a class="btn btn-brand" href="<?= h($dashboard) ?>"><?= t('Open my dashboard','मेरा पैनल खोलिए') ?></a><?php endif; ?>
<p class="help"><?= t('Sign in with your existing account. Approved sellers use mobile + code; riders use mobile as username and password.','अपने खाते से लॉगिन करें। मंजूर दुकानदार मोबाइल और कोड; राइडर मोबाइल यूज़रनेम और पासवर्ड डालें।') ?></p>
</div></div></section>
<script src="/assets/maakit-api.js" defer></script><script src="/assets/auth-gateway.js" defer></script>
<?php include __DIR__.'/../inc/foot.php';
