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
        $_SESSION['sr_mode']=post('mode');
        if(post('mode')==='rider') redirect(sr_identity_rider($pdo)?'/kaam.php':'/apply.php');
        redirect('/account.php');
    }
}
$p=sr_profile($pdo,(int)$a['id']);
sr_public_head('account',t('My account','मेरा खाता'),t('SMART SARATHI','स्मार्ट सारथी'),t('Your account.','आपका खाता।'),t('Your choice.','आपकी पसंद।'),t('Use the same mobile number for Customer and Rider mode.','ग्राहक और राइडर मोड के लिए एक ही मोबाइल नंबर।'));
?>
<section class="sx-card sx-form">
<h2>+91 <?= h($a['mobile']) ?></h2>
<?php if($err): ?><p role="alert"><?= h($err) ?></p><?php endif; ?>
<p><?= h(($_SESSION['sr_mode'] ?? '')==='rider'?t('Rider mode','राइडर मोड'):t('Customer mode','ग्राहक मोड')) ?></p>
<form method="post"><input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="do" value="mode">
<button class="sx-btn sx-btn-navy" name="mode" value="customer"><?= h(t('Customer mode','ग्राहक मोड')) ?></button>
<button class="sx-btn sx-btn-mint" name="mode" value="rider"><?= h(t('Rider mode — rides & parcels','राइडर मोड — सवारी और पार्सल')) ?></button>
</form>
<?php if(($_SESSION['sr_mode'] ?? '')==='customer'): ?>
<p><?= h(t('Your account is ready. In-app ride and parcel booking will be enabled with the next modules. For now, call us to book.','आपका खाता तैयार है। ऐप में सवारी और पार्सल बुकिंग अगले चरणों में शुरू होगी। अभी बुकिंग के लिए फ़ोन कीजिए।')) ?></p>
<a class="sx-pill" href="tel:<?= h(SR_PHONE) ?>"><?= h(t('Call to book','बुकिंग के लिए फ़ोन')) ?></a>
<?php endif; ?>
<?php if($p): ?><p><?= h(t('Rider profile: ','राइडर प्रोफ़ाइल: ').sr_profile_status($p['status'])) ?></p>
<?php if(sr_profile_docs_missing($p)): ?><p><a class="sx-pill" href="<?= h(u('/apply.php')) ?>"><?= h(t('Complete profile: add photo & licence','प्रोफ़ाइल पूरी कीजिए: फ़ोटो और लाइसेंस लगाइए')) ?></a></p><?php endif; ?><?php endif; ?>
<?php if(sr_identity_admin($pdo)): ?><p><a class="sx-pill" href="<?= h(u('/admin/')) ?>"><?= h(t('Admin panel','एडमिन पैनल')) ?></a> <a class="sx-pill" href="<?= h(u('/admin/profiles.php')) ?>"><?= h(t('Review riders','राइडर मंज़ूरी')) ?></a></p><?php endif; ?>
<form method="post"><input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><button name="do" value="logout" class="sx-pill"><?= h(t('Sign out','लॉगआउट')) ?></button></form>
</section>
<?php sr_public_foot(); ?>
