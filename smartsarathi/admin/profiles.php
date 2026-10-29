<?php
require_once __DIR__.'/../inc/public.php';
require_once __DIR__.'/../inc/identity.php';
header('Cache-Control: private, no-store');
if(!sr_account($pdo)) redirect('/');
if(!sr_identity_admin($pdo)){http_response_code(403);exit;}
sr_docs_upgrade($pdo);
$err='';
if($_SERVER['REQUEST_METHOD']==='POST') {
    try {
        if(!csrf_ok())throw new RuntimeException('CSRF');
        if(post('decision')==='approved' && post('checked')!=='1')throw new RuntimeException('Check documents');
        sr_profile_review($pdo,(int)post('id'),(int)post('revision'),(string)post('decision'),(string)post('note'));
        redirect('/admin/profiles.php');
    }catch(Throwable $e){sr_identity_log('profile_review',$e);$err=t('Review not saved. Check the documents and reason, then reload. A disabled rider must first be resolved in the admin panel.','मंज़ूरी सेव नहीं हुई। काग़ज़ और कारण जाँचकर पेज दोबारा खोलिए। बंद राइडर खाते को पहले एडमिन पैनल में जाँचिए।');}
}
$filter=in_array(get('status'),['pending','approved','rejected','suspended'],true)?get('status'):'pending';
$page=max(1,(int)get('page',1));$offset=($page-1)*20;
$q=$pdo->prepare('SELECT p.*,a.mobile FROM sr_rider_profiles p JOIN sr_accounts a ON a.id=p.account_id WHERE p.status=? ORDER BY p.updated_at DESC,p.account_id DESC LIMIT 21 OFFSET '.(int)$offset);$q->execute([$filter]);$rows=$q->fetchAll();$more=count($rows)>20;$rows=array_slice($rows,0,20);
sr_customer_head(t('Review riders','राइडर मंज़ूरी'),t('Check the documents, then approve.','काग़ज़ जाँचिए, फिर मंज़ूर कीजिए।'));
?>
<section class="sx-card sx-form">
<a href="<?= h(u('/admin/')) ?>"><?= h(t('Admin panel','एडमिन पैनल')) ?></a>
<?php if($err): ?><p role="alert"><?= h($err) ?></p><?php endif; ?>
<nav><?php foreach(['pending','approved','rejected','suspended'] as $s): ?><a class="sx-pill" href="?status=<?= h($s) ?>"><?= h(sr_profile_status($s)) ?></a> <?php endforeach; ?></nav>
<?php if(!$rows): ?><p><?= h(t('No profiles in this list.','इस सूची में कोई प्रोफ़ाइल नहीं है।')) ?></p><?php endif; ?>
<?php foreach($rows as $p): ?>
<article class="sx-card">
<h2><?= h($p['name']) ?></h2><p>+91 <?= h($p['mobile']) ?> · <?= h(sr_profile_vehicles()[$p['vehicle_type']] ?? $p['vehicle_type']) ?></p>
<?php [$dd,$dt]=sr_doc_count($p); $link=fn($kind,$label)=>'<a target="_blank" rel="noopener" href="'.h(u('/identity-photo.php?id='.(int)$p['account_id'].'&kind='.$kind)).'">'.h($label).'</a>'; $miss=h(t('missing','नहीं')); ?>
<p><b><?= h(t('Aadhaar + PAN','आधार + पैन')) ?> <?= (int)$dd ?>/<?= (int)$dt ?></b> · <?= h(t('licence/RC optional','लाइसेंस/RC ज़रूरी नहीं')) ?></p>
<ul style="margin:0 0 8px;padding-left:18px;line-height:1.9">
<li><?= h(t('Selfie','सेल्फ़ी')) ?>: <?= sr_doc_file_ok($p['photo']) ? $link('photo',t('view','देखिए')) : $miss ?></li>
<li><?= h(t('Aadhaar','आधार')) ?> <?= ($p['aadhaar_last4'] ?? '')!=='' ? 'XXXX XXXX '.h($p['aadhaar_last4']) : '' ?>: <?= sr_doc_file_ok($p['aadhaar_front'] ?? '') ? $link('aadhaar_front',t('front','आगे')) : $miss ?> · <?= sr_doc_file_ok($p['aadhaar_back'] ?? '') ? $link('aadhaar_back',t('back','पीछे')) : $miss ?></li>
<li>PAN <?= h($p['pan_number'] ?? '') ?>: <?= sr_doc_file_ok($p['pan_photo'] ?? '') ? $link('pan',t('view','देखिए')) : $miss ?></li>
<?php if($p['vehicle_type']!=='care'): ?>
<li><?= h(t('Licence','लाइसेंस')) ?> <?= h($p['dl_number'] ?? '') ?>: <?= sr_doc_file_ok($p['licence_photo']) ? $link('licence',t('front','आगे')) : $miss ?><?= sr_doc_file_ok($p['dl_back'] ?? '') ? ' · '.$link('dl_back',t('back','पीछे')) : '' ?></li>
<li>RC <?= h($p['vehicle_number']) ?>: <?= sr_doc_file_ok($p['rc_photo'] ?? '') ? $link('rc',t('view','देखिए')) : $miss ?></li>
<?php endif; ?>
</ul>
<p><?= h($p['review_note'] ?? '') ?></p>
<?php if(in_array($p['status'],['pending','approved'],true)): ?>
<form method="post"><input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="id" value="<?= (int)$p['account_id'] ?>"><input type="hidden" name="revision" value="<?= (int)$p['revision'] ?>">
<label class="sx-field"><span><?= h(t('Reason / review note','कारण / जाँच की टिप्पणी')) ?></span><textarea name="note" maxlength="300"></textarea></label>
<?php if($p['status']==='pending'): ?>
<label class="sx-check"><input type="checkbox" name="checked" value="1"><span><?= h(t('I know this person / checked their details.','मैं इस व्यक्ति को जानता हूँ / जानकारी जाँच ली है।')) ?></span></label>
<button class="sx-btn sx-btn-mint" name="decision" value="approved"><?= h(t('Approve','मंज़ूर कीजिए')) ?></button>
<button class="sx-pill" name="decision" value="rejected"><?= h(t('Return for corrections','सुधार के लिए लौटाइए')) ?></button>
<?php else: ?><button class="sx-pill" name="decision" value="suspended"><?= h(t('Suspend rider access','राइडर काम पर रोक लगाइए')) ?></button><?php endif; ?>
</form><?php endif; ?>
</article><?php endforeach; ?>
<?php if($page>1): ?><a class="sx-pill" href="?status=<?= h($filter) ?>&page=<?= $page-1 ?>"><?= h(t('Previous','पिछला')) ?></a><?php endif; ?>
<?php if($more): ?><a class="sx-pill" href="?status=<?= h($filter) ?>&page=<?= $page+1 ?>"><?= h(t('Next','अगला')) ?></a><?php endif; ?>
</section><?php sr_customer_foot(); ?>
