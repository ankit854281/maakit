<?php
require_once __DIR__.'/inc/public.php';
require_once __DIR__.'/inc/identity.php';
header('Cache-Control: private, no-store');
$a=sr_account_require($pdo); $err='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    if (!csrf_ok()) $err=t('Page expired. Try again.','पेज पुराना हो गया। दोबारा कोशिश कीजिए।');
    elseif (post('do')==='logout') { rider_logout(); redirect('/'); }
    elseif (post('do')==='mode' && in_array(post('mode'),['customer','rider'],true)) {
        if(post('mode')==='rider') sr_identity_autolink($pdo,$a);
        $p=sr_profile($pdo,(int)$a['id']);
        if(post('mode')==='customer' && !empty($p['rider_id'])) duty_off($pdo,(int)$p['rider_id']);
        sr_set_role($pdo,$a,post('mode')==='rider'?'partner':'customer'); // pakka role badla
        if(post('mode')==='rider') redirect(sr_identity_rider($pdo)?'/kaam.php':'/apply.php');
        redirect('/ghar.php');
    }
}
$p=sr_profile($pdo,(int)$a['id']);
sr_customer_head(t('My account','मेरा खाता'),t('One mobile number. Switch between Customer and Partner any time.','एक ही मोबाइल नंबर। ग्राहक और पार्टनर के बीच कभी भी बदलिए।'));
?>
<section class="sx-card sx-form">
<h2>+91 <?= h($a['mobile']) ?></h2>
<?php if($err): ?><p role="alert"><?= h($err) ?></p><?php endif; ?>
<p><?= h(t('Current role: ','अभी का रोल: ').(($_SESSION['sr_mode'] ?? '')==='rider'?t('Partner','पार्टनर'):t('Customer','ग्राहक'))) ?></p>
<h3 style="margin:4px 0 0"><?= h(t('Switch role','रोल बदलिए')) ?></h3>
<form method="post"><input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="do" value="mode">
<button class="sx-btn sx-btn-navy" name="mode" value="customer"><?= h(t('Customer','ग्राहक')) ?></button>
<button class="sx-btn sx-btn-mint" name="mode" value="rider"><?= h(t('Partner / Driver','पार्टनर / ड्राइवर')) ?></button>
</form>
<?php if(($_SESSION['sr_mode'] ?? '')==='customer'): ?>
<p><?= h(t('Your account is ready.','आपका खाता तैयार है।')) ?></p>
<a class="sx-btn sx-btn-mint" href="<?= h(u('/ghar.php')) ?>"><?= h(t('Open home — book a service','होम खोलिए — सेवा बुक कीजिए')) ?></a>
<?php endif; ?>
<?php if($p): ?><p><?= h(t('Rider profile: ','राइडर प्रोफ़ाइल: ').sr_profile_status($p['status'])) ?></p>
<?php if(sr_profile_docs_missing($p)): ?><p><a class="sx-pill" href="<?= h(u('/apply.php')) ?>"><?= h(t('Complete profile: add photo & licence','प्रोफ़ाइल पूरी कीजिए: फ़ोटो और लाइसेंस लगाइए')) ?></a></p><?php endif; ?><?php endif; ?>
<?php if(sr_identity_admin($pdo)): ?><p><a class="sx-pill" href="<?= h(u('/admin/')) ?>"><?= h(t('Admin panel','एडमिन पैनल')) ?></a> <a class="sx-pill" href="<?= h(u('/admin/profiles.php')) ?>"><?= h(t('Review riders','राइडर मंज़ूरी')) ?></a></p><?php endif; ?>
<form method="post"><input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><button name="do" value="logout" class="sx-pill"><?= h(t('Sign out','लॉगआउट')) ?></button></form>
</section>
<?php sr_customer_foot(); ?>
