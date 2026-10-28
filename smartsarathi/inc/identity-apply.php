<?php
require_once __DIR__.'/public.php';
require_once __DIR__.'/identity.php';
header('Cache-Control: private, no-store');
$a=sr_account_require($pdo); $p=sr_profile($pdo,(int)$a['id']); $err='';
if($_SERVER['REQUEST_METHOD']==='POST') {
    $saved=[];
    try {
        if(!csrf_ok() || post('agree')!=='1' || ($p && $p['status']!=='rejected')) throw new RuntimeException('Invalid submission');
        if(try_blocked($pdo,'profile:'.$a['id'])) throw new RuntimeException('Rate limit');
        // Check text fields before storing any photo, so the owner sees the exact problem.
        $vn=strtoupper(preg_replace('/\s+/','',(string)post('vehicle_number')));
        $nm=trim((string)post('name'));
        if(mb_strlen($nm)<2 || mb_strlen($nm)>80) throw new RuntimeException('Invalid name');
        if(!preg_match('/^[A-Z0-9-]{6,30}$/D',$vn)) throw new RuntimeException('Invalid vehicle number');
        sr_identity_dir(); // fails early with a clear message if the private folder is missing
        try { $saved[]=sr_profile_photo('photo'); } catch(RuntimeException $x) { throw new RuntimeException('photo: '.$x->getMessage()); }
        try { $saved[]=sr_profile_photo('licence'); } catch(RuntimeException $x) { throw new RuntimeException('licence: '.$x->getMessage()); }
        sr_profile_submit($pdo,$a,$_POST,$saved[0],$saved[1]);
        try_ok($pdo,'profile:'.$a['id']); redirect('/apply.php');
    }catch(Throwable $e){
        try { foreach($saved as $file) @unlink(sr_identity_dir().'/'.$file); } catch(Throwable $ignored) {}
        $m=$e->getMessage(); $which=str_starts_with($m,'licence: ') ? t('Driving licence photo','ड्राइविंग लाइसेंस फ़ोटो') : t('Your photo','आपकी फ़ोटो');
        if($m==='Invalid name') $err=t('Enter your full name (at least 2 letters).','पूरा नाम लिखिए (कम से कम 2 अक्षर)।');
        elseif($m==='Invalid vehicle number') $err=t('Enter the real vehicle number, at least 6 letters/digits, e.g. UP66AB1234.','गाड़ी का असली नंबर लिखिए, कम से कम 6 अक्षर/अंक, जैसे UP66AB1234।');
        elseif(str_contains($m,'Private directory') || str_contains($m,'outside web root') || str_contains($m,'storage failed')) $err=t('Photo storage is not set up on the server. Please tell the office (private folder missing).','सर्वर पर फ़ोटो रखने की जगह तैयार नहीं है। दफ़्तर को बताइए (private folder नहीं मिला)।');
        elseif(str_contains($m,'Upload failed')) $err=$which.': '.t('not uploaded or larger than 5 MB. Choose a smaller photo.','अपलोड नहीं हुई या 5 MB से बड़ी है। छोटी फ़ोटो चुनिए।');
        elseif(str_contains($m,'Invalid image')) $err=$which.': '.t('use a JPG, PNG or WebP photo.','JPG, PNG या WebP फ़ोटो लगाइए।');
        elseif($m==='Rate limit') $err=t('Too many attempts. Try again after some time.','बहुत कोशिश हुई। कुछ देर बाद कोशिश कीजिए।');
        else $err=t('Could not submit. Try again or call the office.','अर्ज़ी नहीं भेज पाए। दोबारा कोशिश या दफ़्तर को फ़ोन कीजिए।');
        $err.=' '.t('Please choose both photos again.','दोनों फ़ोटो दोबारा चुनिए।');
    }
}
sr_public_head('apply',t('Rider profile','राइडर प्रोफ़ाइल'),t('ONE ACCOUNT','एक खाता'),t('Become a rider.','राइडर बनिए।'),t('Rides and parcels.','सवारी और पार्सल।'),t('Complete your profile once. Our admin checks your documents before you can accept jobs.','एक बार प्रोफ़ाइल भरिए। काम मिलने से पहले एडमिन आपके काग़ज़ जाँचेंगे।'));
?>
<section class="sx-card sx-form">
<a class="sx-pill" href="<?= h(u('/account.php')) ?>"><?= h(t('Account / change mode','खाता / मोड बदलिए')) ?></a>
<?php if($err): ?><p role="alert"><?= h($err) ?></p><?php endif; ?>
<?php if($p): ?><h2><?= h(sr_profile_status($p['status'])) ?></h2><p><?= h($p['review_note'] ?? '') ?></p><?php endif; ?>
<?php if($p && $p['status']==='approved'): ?>
<form method="post" action="<?= h(u('/account.php')) ?>"><input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="do" value="mode"><button class="sx-btn sx-btn-mint" name="mode" value="rider"><?= h(t('Open rider mode','राइडर मोड खोलिए')) ?></button></form>
<?php elseif(!$p || $p['status']==='rejected'): ?>
<form method="post" enctype="multipart/form-data">
<input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
<label class="sx-field"><span><?= h(t('Full name','पूरा नाम')) ?></span><input name="name" required minlength="2" maxlength="80" value="<?= h(post('name',$p['name'] ?? '')) ?>" autocomplete="name"></label>
<label class="sx-field"><span><?= h(t('Vehicle type','गाड़ी का प्रकार')) ?></span><select name="vehicle_type" required><?php foreach(sr_profile_vehicles() as $key=>$label): ?><option value="<?= h($key) ?>" <?= post('vehicle_type',$p['vehicle_type'] ?? '')===$key?'selected':'' ?>><?= h($label) ?></option><?php endforeach; ?></select></label>
<label class="sx-field"><span><?= h(t('Vehicle number','गाड़ी नंबर')) ?></span><input name="vehicle_number" required minlength="6" maxlength="30" pattern="[A-Za-z0-9\- ]{6,30}" title="<?= h(t('Real vehicle number, e.g. UP66AB1234','असली गाड़ी नंबर, जैसे UP66AB1234')) ?>" placeholder="UP66AB1234" value="<?= h(post('vehicle_number',$p['vehicle_number'] ?? '')) ?>" autocapitalize="characters"></label>
<label class="sx-field"><span><?= h(t('Your photo (up to 5 MB)','आपकी फ़ोटो (5 MB तक)')) ?></span><input name="photo" type="file" accept="image/jpeg,image/png,image/webp" required></label>
<label class="sx-field"><span><?= h(t('Driving licence photo (up to 5 MB)','ड्राइविंग लाइसेंस फ़ोटो (5 MB तक)')) ?></span><input name="licence" type="file" accept="image/jpeg,image/png,image/webp" required></label>
<label class="sx-check"><input name="agree" value="1" type="checkbox" required><span><?= h(t('These details are mine and correct. I allow the office to review my photos and licence for rider approval.','यह मेरी सही जानकारी है। राइडर मंज़ूरी के लिए दफ़्तर को मेरी फ़ोटो और लाइसेंस जाँचने की अनुमति है।')) ?></span></label>
<button class="sx-btn sx-btn-mint" type="submit"><?= h(t('Send for approval','मंज़ूरी के लिए भेजिए')) ?></button>
</form>
<?php else: ?><p><?= h(t('You can still use Customer mode. Refresh this page to check your review status.','आप ग्राहक मोड इस्तेमाल कर सकते हैं। मंज़ूरी की स्थिति देखने के लिए पेज दोबारा खोलिए।')) ?></p><?php endif; ?>
</section>
<?php sr_public_foot(); ?>
