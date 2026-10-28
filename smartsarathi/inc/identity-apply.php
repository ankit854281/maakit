<?php
// Rider joining — simple first (owner rule, 29 Oct 2026):
// step 1 = name + vehicle only; photo and licence can be added later.
require_once __DIR__.'/public.php';
require_once __DIR__.'/identity.php';
header('Cache-Control: private, no-store');
$a=sr_account_require($pdo);
sr_identity_autolink($pdo,$a);
$p=sr_profile($pdo,(int)$a['id']); $err='';

function sr_apply_optional_photo(string $field): ?string {
    if(($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE)===UPLOAD_ERR_NO_FILE) return null;
    try { return sr_profile_photo($field); } catch(RuntimeException $x) { throw new RuntimeException($field.': '.$x->getMessage()); }
}
function sr_apply_error(string $m): string {
    $which=str_starts_with($m,'licence: ') ? t('Driving licence photo','ड्राइविंग लाइसेंस फ़ोटो') : t('Your photo','आपकी फ़ोटो');
    if($m==='Invalid name') return t('Enter your full name (at least 2 letters).','पूरा नाम लिखिए (कम से कम 2 अक्षर)।');
    if($m==='Invalid vehicle number') return t('Vehicle number: use only letters and digits, e.g. UP66AB1234.','गाड़ी नंबर में सिर्फ़ अक्षर और अंक लिखिए, जैसे UP66AB1234।');
    if(str_contains($m,'Private directory') || str_contains($m,'outside web root') || str_contains($m,'storage failed')) return t('Photo storage is not set up on the server. Please tell the office.','सर्वर पर फ़ोटो रखने की जगह तैयार नहीं है। दफ़्तर को बताइए।');
    if(str_contains($m,'Upload failed')) return $which.': '.t('not uploaded or larger than 5 MB. Choose a smaller photo.','अपलोड नहीं हुई या 5 MB से बड़ी है। छोटी फ़ोटो चुनिए।');
    if(str_contains($m,'Invalid image')) return $which.': '.t('use a JPG, PNG or WebP photo.','JPG, PNG या WebP फ़ोटो लगाइए।');
    if($m==='Rate limit') return t('Too many attempts. Try again after some time.','बहुत कोशिश हुई। कुछ देर बाद कोशिश कीजिए।');
    return t('Could not save. Try again or call the office.','सेव नहीं हुआ। दोबारा कोशिश या दफ़्तर को फ़ोन कीजिए।');
}

if($_SERVER['REQUEST_METHOD']==='POST') {
    $saved=[];
    try {
        if(!csrf_ok()) throw new RuntimeException('Invalid submission');
        if(try_blocked($pdo,'profile:'.$a['id'])) throw new RuntimeException('Rate limit');
        if(post('do')==='docs') {
            if(!$p) throw new RuntimeException('Invalid submission');
            $ph=sr_apply_optional_photo('photo'); if($ph) $saved[]=$ph;
            $li=sr_apply_optional_photo('licence'); if($li) $saved[]=$li;
            sr_profile_add_docs($pdo,$a,$ph,$li);
        } else {
            if($p && $p['status']!=='rejected') throw new RuntimeException('Invalid submission');
            $nm=trim((string)post('name'));
            $vn=strtoupper(preg_replace('/[\s-]+/','',(string)post('vehicle_number')));
            if(mb_strlen($nm)<2 || mb_strlen($nm)>80) throw new RuntimeException('Invalid name');
            if(!preg_match('/^[A-Z0-9]{0,30}$/D',$vn)) throw new RuntimeException('Invalid vehicle number');
            $ph=sr_apply_optional_photo('photo'); if($ph) $saved[]=$ph;
            $li=sr_apply_optional_photo('licence'); if($li) $saved[]=$li;
            $in=$_POST; $in['vehicle_number']=$vn;
            sr_profile_submit($pdo,$a,$in,(string)$ph,(string)$li);
        }
        try_ok($pdo,'profile:'.$a['id']); redirect('/apply.php?ok=1');
    } catch(Throwable $e) {
        try { foreach($saved as $file) @unlink(sr_identity_dir().'/'.$file); } catch(Throwable $ignored) {}
        $err=sr_apply_error($e->getMessage());
    }
}
sr_public_head('apply',t('Rider profile','राइडर प्रोफ़ाइल'),t('ONE ACCOUNT','एक खाता'),t('Become a rider.','राइडर बनिए।'),t('Rides and parcels.','सवारी और पार्सल।'),t('Just your name and vehicle. Photo and licence can be added later.','बस नाम और गाड़ी। फ़ोटो और लाइसेंस बाद में भी लगा सकते हैं।'));
$docsForm=function() { ?>
<form method="post" enctype="multipart/form-data">
<input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="do" value="docs">
<label class="sx-field"><span><?= h(t('Your photo (up to 5 MB)','आपकी फ़ोटो (5 MB तक)')) ?></span><input name="photo" type="file" accept="image/jpeg,image/png,image/webp"></label>
<label class="sx-field"><span><?= h(t('Driving licence photo (up to 5 MB)','ड्राइविंग लाइसेंस फ़ोटो (5 MB तक)')) ?></span><input name="licence" type="file" accept="image/jpeg,image/png,image/webp"></label>
<button class="sx-btn sx-btn-navy" type="submit"><?= h(t('Save photos','फ़ोटो सेव कीजिए')) ?></button>
</form>
<?php };
?>
<section class="sx-card sx-form">
<a class="sx-pill" href="<?= h(u('/account.php')) ?>"><?= h(t('Account / change mode','खाता / मोड बदलिए')) ?></a>
<?php if($err): ?><p role="alert"><?= h($err) ?></p><?php endif; ?>
<?php if(get('ok')): ?><p role="status"><?= h(t('Saved.','सेव हो गया।')) ?></p><?php endif; ?>

<?php if($p && $p['status']==='approved'): ?>
<h2><?= h(t('You are approved ✓','आप मंज़ूर हैं ✓')) ?></h2>
<form method="post" action="<?= h(u('/account.php')) ?>"><input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="do" value="mode"><button class="sx-btn sx-btn-mint" name="mode" value="rider"><?= h(t('Start work — open rider mode','काम शुरू कीजिए — राइडर मोड खोलिए')) ?></button></form>
<?php if(sr_profile_docs_missing($p)): ?><h3><?= h(t('Complete your profile (when convenient)','प्रोफ़ाइल पूरी कीजिए (जब समय मिले)')) ?></h3><?php $docsForm(); endif; ?>

<?php elseif($p && $p['status']==='pending'): ?>
<h2><?= h(t('Received ✓ — waiting for office approval','मिल गया ✓ — दफ़्तर की मंज़ूरी बाकी')) ?></h2>
<p><?= h(t('The office will approve you soon. Call if urgent. Meanwhile you can add your photo and licence.','दफ़्तर जल्दी मंज़ूर करेगा। जल्दी हो तो फ़ोन कीजिए। तब तक फ़ोटो और लाइसेंस लगा सकते हैं।')) ?></p>
<a class="sx-pill" href="tel:<?= h(SR_PHONE) ?>"><?= h(t('Call office','दफ़्तर को फ़ोन')) ?></a>
<?php if(sr_profile_docs_missing($p)) $docsForm(); ?>

<?php elseif($p && $p['status']==='suspended'): ?>
<h2><?= h(sr_profile_status('suspended')) ?></h2><p><?= h($p['review_note'] ?? '') ?></p>

<?php else: ?>
<?php if($p): ?><h2><?= h(sr_profile_status($p['status'])) ?></h2><p><?= h($p['review_note'] ?? '') ?></p><?php endif; ?>
<form method="post" enctype="multipart/form-data">
<input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
<label class="sx-field"><span><?= h(t('Full name','पूरा नाम')) ?></span><input name="name" required minlength="2" maxlength="80" value="<?= h(post('name',$p['name'] ?? '')) ?>" autocomplete="name"></label>
<label class="sx-field"><span><?= h(t('Vehicle type','गाड़ी का प्रकार')) ?></span><select name="vehicle_type" required><?php foreach(sr_profile_vehicles() as $key=>$label): ?><option value="<?= h($key) ?>" <?= post('vehicle_type',$p['vehicle_type'] ?? '')===$key?'selected':'' ?>><?= h($label) ?></option><?php endforeach; ?></select></label>
<label class="sx-field"><span><?= h(t('Vehicle number (optional)','गाड़ी नंबर (ज़रूरी नहीं)')) ?></span><input name="vehicle_number" maxlength="30" placeholder="UP66AB1234" value="<?= h(post('vehicle_number',$p['vehicle_number'] ?? '')) ?>" autocapitalize="characters"></label>
<details><summary><?= h(t('Add photo & licence now (optional)','फ़ोटो और लाइसेंस अभी लगाइए (ज़रूरी नहीं)')) ?></summary>
<label class="sx-field"><span><?= h(t('Your photo (up to 5 MB)','आपकी फ़ोटो (5 MB तक)')) ?></span><input name="photo" type="file" accept="image/jpeg,image/png,image/webp"></label>
<label class="sx-field"><span><?= h(t('Driving licence photo (up to 5 MB)','ड्राइविंग लाइसेंस फ़ोटो (5 MB तक)')) ?></span><input name="licence" type="file" accept="image/jpeg,image/png,image/webp"></label>
</details>
<button class="sx-btn sx-btn-mint" type="submit"><?= h(t('Join as rider','राइडर बनिए')) ?></button>
</form>
<?php endif; ?>
</section>
<?php sr_public_foot(); ?>
