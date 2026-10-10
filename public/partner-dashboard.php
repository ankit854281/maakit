<?php
require_once __DIR__.'/../inc/fn.php';
header('Cache-Control: no-store');need_role(['vendor','rider']);
$u=Maakit\Api\query($pdo,"SELECT id,name,role FROM users WHERE id=? AND active=1 AND role IN ('vendor','rider')",[(int)user()['id']])->fetch();
if(!$u)redirect('/logout.php');
$a=Maakit\Api\query($pdo,"SELECT * FROM mk_partner_applications WHERE login_user_id=? AND status='APPROVED'",[$u['id']])->fetch();
if(!$a){http_response_code(403);exit;}
$page_title=t('Your partner dashboard — Maakit','आपका पार्टनर पैनल — Maakit');include __DIR__.'/../inc/head.php';
?>
<section><div class="wrap" data-csrf="<?= h(csrf()) ?>" data-auth-context="staff">
<h1><?= $u['role']==='vendor'?t('Seller dashboard','दुकानदार पैनल'):t('Rider dashboard','राइडर पैनल') ?></h1>
<p><?= h($u['name']) ?> — <?= t('Your application is approved.','आपका आवेदन मंजूर है।') ?></p>
<div class="box"><?php if($u['role']==='vendor'): ?><h2><?= h($a['business_name']) ?></h2><p><?= h($a['market']) ?> · <?= h($a['shop_category']) ?></p><?php else: ?><p><?= h($a['vehicle_type']) ?> · <?= h($a['rc_number']) ?></p><?php endif; ?>
<p><?= t('Contact Maakit to configure your store or assigned service area.','दुकान या सेवा क्षेत्र तय करने के लिए Maakit से संपर्क करें।') ?></p>
<?php if($u['role']==='rider'): /* Maakit ke apne (gaon ke) order /sarathi/ me aate hain: haazri, Pick OTP, photo, graahak ka code. */ ?><a class="btn btn-brand" href="/sarathi/kaam.php"><?= t('Open Sarathi — today\'s deliveries','सारथी खोलिए — आज के काम') ?></a> <?php endif; ?><a class="btn <?= $u['role']==='rider'?'btn-gold':'btn-brand' ?>" href="/public/dispatch.php"><?= t('Open delivery dashboard','डिलीवरी पैनल खोलिए') ?></a></div>
<p role="status" data-auth-status><?= t('Connecting your secure session…','सुरक्षित लॉगिन जुड़ रहा है…') ?></p>
<a href="/logout.php"><?= t('Log out','लॉगआउट') ?></a></div></section>
<script src="/assets/maakit-api.js" defer></script><script src="/assets/auth-gateway.js" defer></script>
<?php include __DIR__.'/../inc/foot.php'; ?>
