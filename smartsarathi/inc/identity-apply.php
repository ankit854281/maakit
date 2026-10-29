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
    if($m==='Invalid submission') return t('Page expired. Reload and try again.','पेज पुराना हो गया। दोबारा खोलकर कोशिश कीजिए।');
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
        sr_identity_log('profile_save',$e);
        try { foreach($saved as $file) @unlink(sr_identity_dir().'/'.$file); } catch(Throwable $ignored) {}
        $err=sr_apply_error($e->getMessage());
    }
}
sr_customer_head(t('Rider profile','राइडर प्रोफ़ाइल'),t('Just your name and vehicle. Photos can come later.','बस नाम और गाड़ी। फ़ोटो बाद में भी लगा सकते हैं।'));
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
<?php
// Documents checklist + Hindi awaaz guide (owner request, 29 Oct 2026 — Rapido jaisa)
$hasPhoto=$p && preg_match('/^[a-f0-9]{48}\.jpg$/D',(string)$p['photo']); $hasLic=$p && preg_match('/^[a-f0-9]{48}\.jpg$/D',(string)$p['licence_photo']);
$rows=[];
if($p){
    $rows[]=['ok',t('Work type','काम का प्रकार'),sr_profile_vehicles()[$p['vehicle_type']] ?? $p['vehicle_type'],t('Your work type is selected.','आपका काम का प्रकार चुन लिया गया है।')];
    $rows[]=['ok',t('Name','नाम'),$p['name'],t('Your name is saved.','आपका नाम सेव है।')];
    $rows[]=$hasPhoto?['ok',t('Your photo','आपकी फ़ोटो'),t('Added','लग गई'),t('Your photo is added.','आपकी फ़ोटो लग गई है।')]
        :['todo',t('Your photo (optional)','आपकी फ़ोटो (ज़रूरी नहीं)'),t('Not added yet','अभी नहीं लगी'),t('Tap Choose File below and take a clear photo of your face.','नीचे फ़ाइल चुनिए दबाकर अपने चेहरे की साफ़ फ़ोटो लीजिए।')];
    $rows[]=$hasLic?['ok',t('Driving licence','ड्राइविंग लाइसेंस'),t('Added','लग गया'),t('Your licence photo is added.','आपके लाइसेंस की फ़ोटो लग गई है।')]
        :['todo',t('Driving licence (optional)','ड्राइविंग लाइसेंस (ज़रूरी नहीं)'),t('Not added yet','अभी नहीं लगा'),t('Tap Choose File and take a photo of the front side of your driving licence.','फ़ाइल चुनिए दबाकर ड्राइविंग लाइसेंस के आगे वाले हिस्से की फ़ोटो लीजिए।')];
    $rows[]=match($p['status']){
        'approved'=>['ok',t('Approval','मंज़ूरी'),t('Approved','मंज़ूर'),t('You are approved. Tap start work and go online to get jobs.','आप मंज़ूर हैं। काम शुरू कीजिए दबाइए और हाज़िर होइए, काम आने लगेंगे।')],
        'pending'=>['wait',t('Approval','मंज़ूरी'),t('Under verification…','जाँच हो रही है…'),t('Our office is checking your details. Call us if it is urgent.','हमारा दफ़्तर आपकी जानकारी जाँच रहा है। जल्दी हो तो फ़ोन कीजिए।')],
        'rejected'=>['bad',t('Approval','मंज़ूरी'),t('Correction needed','सुधार चाहिए').(($p['review_note']??'')!==''?': '.$p['review_note']:''),t('Some details need correction. Read the reason and send again.','कुछ जानकारी में सुधार चाहिए। कारण पढ़कर दोबारा भेजिए।')],
        default=>['bad',t('Approval','मंज़ूरी'),sr_profile_status($p['status']),t('Please talk to the office.','कृपया दफ़्तर से बात कीजिए।')],
    };
}
if($rows): $next=''; foreach($rows as $r) if($r[0]!=='ok'){ $next=$r[3]; break; } if($next==='') $next=end($rows)[3]; ?>
<div class="sx-chk">
  <button type="button" class="sx-say-all" data-say="<?= h($next) ?>"><span aria-hidden="true">🔊</span> <?= h(t('Listen: what to do next','सुनिए: आगे क्या करना है')) ?></button>
  <?php foreach($rows as [$state,$label,$val,$say]): ?>
  <div class="sx-chk-row <?= h($state) ?>"><span class="sx-chk-ic" aria-hidden="true"><?= $state==='ok'?'✓':($state==='wait'?'…':($state==='todo'?'+':'!')) ?></span>
    <span class="sx-chk-t"><b><?= h($label) ?></b><small><?= h($val) ?></small></span>
    <button type="button" class="sx-chk-say" data-say="<?= h($say) ?>" aria-label="<?= h(t('Listen','सुनिए')) ?>">🔊</button></div>
  <?php endforeach; ?>
</div>
<style>
.sx-chk{display:grid;gap:8px;margin:4px 0 8px}
.sx-say-all{display:flex;align-items:center;justify-content:center;gap:8px;min-height:50px;border:0;border-radius:14px;background:#5B4BDB;color:#fff;font-weight:800;font-size:15px;cursor:pointer}
.sx-chk-row{display:flex;align-items:center;gap:12px;background:#fff;border:1px solid #E3E8EF;border-radius:16px;padding:10px 10px 10px 12px;min-height:64px}
.sx-chk-ic{width:36px;height:36px;border-radius:50%;display:grid;place-items:center;font-weight:900;flex:none;color:#fff;background:#0E8A5F}
.sx-chk-row.wait .sx-chk-ic{background:#E8A33A}.sx-chk-row.todo .sx-chk-ic{background:#4B5A6B}.sx-chk-row.bad .sx-chk-ic{background:#C8261B}
.sx-chk-t{flex:1;min-width:0}.sx-chk-t b{display:block}.sx-chk-t small{display:block;color:#4B5A6B}
.sx-chk-row.wait small{color:#A8650D;font-weight:700}.sx-chk-row.bad small{color:#C8261B;font-weight:700}
.sx-chk-say{width:44px;height:44px;border-radius:12px;border:1px solid #E3E8EF;background:#fff;font-size:18px;cursor:pointer;flex:none}
</style>
<script>
document.addEventListener('click', function (e) {
  var b = e.target.closest('[data-say]'); if (!b || !('speechSynthesis' in window)) return;
  var u = new SpeechSynthesisUtterance(b.getAttribute('data-say'));
  u.lang = document.documentElement.lang === 'en' ? 'en-IN' : 'hi-IN'; u.rate = 0.95;
  var v = speechSynthesis.getVoices().filter(function (x) { return x.lang && x.lang.toLowerCase().indexOf(u.lang.toLowerCase().slice(0, 2)) === 0; })[0]; if (v) u.voice = v;
  speechSynthesis.cancel(); speechSynthesis.speak(u);
});
</script>
<?php endif; ?>

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
<fieldset class="sx-types"><legend><?= h(t('What will you do?','आप क्या काम करेंगे?')) ?></legend>
<?php $cur=post('vehicle_type',$p['vehicle_type'] ?? 'bike'); foreach(['bike','auto','erick','car','loader','care'] as $key): ?>
<label class="sx-type"><input type="radio" name="vehicle_type" value="<?= h($key) ?>" required <?= $cur===$key?'checked':'' ?>><span><?= h(sr_profile_vehicles()[$key]) ?></span></label>
<?php endforeach; ?></fieldset>
<style>.sx-types{border:0;margin:0;padding:0;display:grid;gap:8px}.sx-types legend{font-weight:800;margin-bottom:6px}.sx-type{display:flex;align-items:center;gap:12px;border:2px solid #E3E8EF;border-radius:16px;padding:12px 14px;min-height:56px;background:#fff;cursor:pointer;font-weight:700}.sx-type:has(input:checked){border-color:#0B1F3A;background:#EFFBF6}.sx-type input{width:22px;height:22px;accent-color:#10C98F}</style>
<label class="sx-field"><span><?= h(t('Vehicle number (optional)','गाड़ी नंबर (ज़रूरी नहीं)')) ?></span><input name="vehicle_number" maxlength="30" placeholder="UP66AB1234" value="<?= h(post('vehicle_number',$p['vehicle_number'] ?? '')) ?>" autocapitalize="characters"></label>
<details><summary><?= h(t('Add photo & licence now (optional)','फ़ोटो और लाइसेंस अभी लगाइए (ज़रूरी नहीं)')) ?></summary>
<label class="sx-field"><span><?= h(t('Your photo (up to 5 MB)','आपकी फ़ोटो (5 MB तक)')) ?></span><input name="photo" type="file" accept="image/jpeg,image/png,image/webp"></label>
<label class="sx-field"><span><?= h(t('Driving licence photo (up to 5 MB)','ड्राइविंग लाइसेंस फ़ोटो (5 MB तक)')) ?></span><input name="licence" type="file" accept="image/jpeg,image/png,image/webp"></label>
</details>
<button class="sx-btn sx-btn-mint" type="submit"><?= h(t('Join as rider','राइडर बनिए')) ?></button>
</form>
<?php endif; ?>
</section>
<?php sr_customer_foot(); ?>
