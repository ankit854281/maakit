<?php
require_once __DIR__.'/inc/public.php';
require_once __DIR__.'/inc/identity.php';
require_once __DIR__.'/inc/vehicles.php';
header('Cache-Control: private, no-store');
$a=sr_account_require($pdo); $err='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    if (!csrf_ok()) $err=t('Page expired. Try again.','पेज पुराना हो गया। दोबारा कोशिश कीजिए।');
    elseif (post('do')==='logout') { rider_logout(); redirect('/'); }
    elseif (post('do')==='password') {
        try { sr_password_save($pdo,$a,(string)post('current'),(string)post('password'),(string)post('confirm')); flash(t('Password saved. Other devices have been signed out.','पासवर्ड सेव हुआ। दूसरे फ़ोन के लॉगिन बंद कर दिए गए।')); redirect('/account.php'); }
        catch(Throwable $e) { $err=t('Use 8–72 characters and match both passwords. Enter your current password, or log out and verify with OTP to reset.','8–72 अक्षर का पासवर्ड दोनों जगह एक जैसा भरें। अभी का पासवर्ड लिखें, या लॉगआउट करके OTP से दोबारा प्रवेश करें।'); }
    }
    elseif(in_array(post('do'),['add_vehicle','switch_vehicle'],true)) {
        try {
            if(!sr_profile($pdo,(int)$a['id'])) throw new RuntimeException('No profile');
            sr_vehicles_upgrade($pdo);
            if(post('do')==='add_vehicle') {
                $type=(string)post('vehicle_type'); $model=trim((string)post('model')); $rc=strtoupper(preg_replace('/\s/','',(string)post('rc')));
                if(!in_array($type,['bike','auto','car','erick'],true) || mb_strlen($model)<2 || mb_strlen($model)>60 || !preg_match('/^([A-Z]{2}[0-9]{1,2}[A-Z]{0,3}[0-9]{1,4}|[0-9]{2}BH[0-9]{4}[A-Z]{1,2})$/D',$rc)) throw new RuntimeException('Invalid vehicle');
                $pdo->prepare('INSERT INTO sr_vehicles(account_id,vehicle_type,model,rc_number) VALUES(?,?,?,?)')->execute([$a['id'],$type,$model,$rc]);
            } else sr_vehicle_change($pdo,$a,(int)post('vehicle_id'));
            flash(t('Vehicle saved. Go online again when ready.','गाड़ी सेव हुई। तैयार होने पर फिर हाज़िरी लगाएँ।')); redirect('/account.php');
        } catch(Throwable $e) { $err=t('Check the model and RC number. Finish any active job or offer before switching vehicles.','मॉडल और RC नंबर जाँचें। गाड़ी बदलने से पहले चालू काम या ऑफ़र पूरा करें।'); }
    }
    elseif (post('do')==='mode' && in_array(post('mode'),['customer','rider'],true)) {
        if(post('mode')==='rider') sr_identity_autolink($pdo,$a);
        $p=sr_profile($pdo,(int)$a['id']);
        if(post('mode')==='customer' && !empty($p['rider_id'])) duty_off($pdo,(int)$p['rider_id']);
        sr_set_role($pdo,$a,post('mode')==='rider'?'partner':'customer'); // pakka role badla
        if(post('mode')==='rider') redirect(sr_identity_rider($pdo)?'/kaam.php':'/apply.php');
        redirect(sr_home_path($pdo));
    }
}
$p=sr_profile($pdo,(int)$a['id']);
$_SESSION['sr_mode']=sr_account_role($pdo,$a)==='partner' ? 'rider' : 'customer';
sr_customer_head(t('Settings','सेटिंग्स'),t('Your login, language and vehicle.','आपका लॉगिन, भाषा और गाड़ी।'));
?>
<section class="sx-card sx-form">
<h2>+91 <?= h($a['mobile']) ?></h2>
<?php if($notice=flash()): ?><p role="status"><?= h($notice) ?></p><?php endif; ?>
<h3><?= h(t('Password','पासवर्ड')) ?></h3>
<form method="post" class="sx-form"><input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="do" value="password">
<label class="sx-field"><span><?= h(t('Current password (if set)','अभी का पासवर्ड (अगर बनाया है)')) ?></span><input name="current" type="password" maxlength="72" autocomplete="current-password"></label>
<label class="sx-field"><span><?= h(t('New password — at least 8 characters','नया पासवर्ड — कम से कम 8 अक्षर')) ?></span><input name="password" type="password" minlength="8" maxlength="72" autocomplete="new-password" required></label>
<label class="sx-field"><span><?= h(t('Repeat new password','नया पासवर्ड फिर लिखें')) ?></span><input name="confirm" type="password" minlength="8" maxlength="72" autocomplete="new-password" required></label>
<button class="sx-btn sx-btn-mint" type="submit"><?= h(t('Save password','पासवर्ड सेव करें')) ?></button></form>
<h3><?= h(t('Language','भाषा')) ?></h3><?php sr_language_switch(true); ?>
<?php if($err): ?><p role="alert"><?= h($err) ?></p><?php endif; ?>
<p><?= h(t('Current role: ','अभी का रोल: ').(($_SESSION['sr_mode'] ?? '')==='rider'?t('Partner','पार्टनर'):t('Customer','ग्राहक'))) ?></p>
<h3 style="margin:4px 0 0"><?= h(t('Switch role','रोल बदलिए')) ?></h3>
<form method="post"><input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="do" value="mode">
<?php if(!$p): ?><button class="sx-btn sx-btn-navy" name="mode" value="customer"><?= h(t('Customer','ग्राहक')) ?></button><?php endif; ?>
<button class="sx-btn sx-btn-mint" name="mode" value="rider"><?= h(t('Partner / Driver','पार्टनर / ड्राइवर')) ?></button>
</form>
<?php if(($_SESSION['sr_mode'] ?? '')==='customer'): ?>
<p><?= h(t('Your account is ready.','आपका खाता तैयार है।')) ?></p>
<a class="sx-btn sx-btn-mint" href="<?= h(u('/ghar.php')) ?>"><?= h(t('Open home — book a service','होम खोलिए — सेवा बुक कीजिए')) ?></a>
<?php endif; ?>
<?php if($p): ?><p><?= h(t('Rider profile: ','राइडर प्रोफ़ाइल: ').sr_profile_status($p['status'])) ?></p>
<h3><?= h(t('Your vehicles','आपकी गाड़ियाँ')) ?></h3>
<?php foreach(sr_vehicles($pdo,$a) as $vehicle): ?>
<form method="post"><input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="do" value="switch_vehicle"><input type="hidden" name="vehicle_id" value="<?= (int)$vehicle['id'] ?>">
<p><?= h($vehicle['model'].' · '.$vehicle['rc_number']) ?></p><button class="sx-pill" type="submit" <?= $vehicle['rc_number']===$p['vehicle_number'] ? 'disabled' : '' ?>><?= h($vehicle['rc_number']===$p['vehicle_number'] ? t('Active vehicle','अभी की गाड़ी') : t('Use this vehicle','यह गाड़ी चुनें')) ?></button></form>
<?php endforeach; ?>
<details><summary><?= h(t('Add new vehicle','नई गाड़ी जोड़ें')) ?></summary><form method="post" class="sx-form"><input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="do" value="add_vehicle">
<label class="sx-field"><span><?= h(t('Vehicle type','गाड़ी का प्रकार')) ?></span><select name="vehicle_type"><?php foreach(['bike','auto','car','erick'] as $type): ?><option value="<?= h($type) ?>"><?= h(sr_profile_vehicles()[$type]) ?></option><?php endforeach; ?></select></label>
<label class="sx-field"><span><?= h(t('Company and model','कंपनी और मॉडल')) ?></span><input name="model" maxlength="60" required></label>
<label class="sx-field"><span><?= h(t('Registration / RC number','रजिस्ट्रेशन / RC नंबर')) ?></span><input name="rc" maxlength="30" required></label>
<button class="sx-btn sx-btn-mint" type="submit"><?= h(t('Add vehicle','गाड़ी जोड़ें')) ?></button></form></details>
<?php if(sr_profile_docs_missing($p)): ?><p><a class="sx-pill" href="<?= h(u('/apply.php')) ?>"><?= h(t('Complete profile: add your documents','प्रोफ़ाइल पूरी कीजिए: काग़ज़ लगाइए')) ?></a></p><?php endif; ?><?php endif; ?>
<?php if(sr_identity_admin($pdo)): ?><p><a class="sx-pill" href="<?= h(u('/admin/')) ?>"><?= h(t('Admin panel','एडमिन पैनल')) ?></a> <a class="sx-pill" href="<?= h(u('/admin/profiles.php')) ?>"><?= h(t('Review riders','राइडर मंज़ूरी')) ?></a></p><?php endif; ?>
<form method="post"><input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><button name="do" value="logout" class="sx-pill"><?= h(t('Sign out','लॉगआउट')) ?></button></form>
</section>
<?php sr_customer_foot(); ?>
