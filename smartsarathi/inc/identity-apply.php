<?php
// ============================================================
//  सारथी बनिए — Rapido Captain jaisa (owner request, 29 Oct 2026)
//
//  Step 1: gaadi chuniye + naam  →  Step 2: kaagaz, ek-ek karke
//  (selfie, Aadhaar, PAN, driving licence, RC + gaadi number),
//  har kaagaz par 🔊 Hindi awaaz  →  Step 3: daftar ki manzoori.
//  Aadhaar ka poora number kabhi save nahi hota — sirf aakhiri 4 ank.
//  Photos phone par hi chhoti hoti hain (canvas), phir upload.
// ============================================================
require_once __DIR__.'/public.php';
require_once __DIR__.'/identity.php';
header('Cache-Control: private, no-store');
$a=sr_account_require($pdo);
sr_docs_upgrade($pdo);
sr_identity_autolink($pdo,$a);
$p=sr_profile($pdo,(int)$a['id']); $err=''; $errKind='';

function sr_apply_optional_photo(string $field): ?string {
    if(($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE)===UPLOAD_ERR_NO_FILE) return null;
    try { return sr_profile_photo($field); } catch(RuntimeException $x) { throw new RuntimeException('photo: '.$x->getMessage()); }
}
function sr_apply_error(string $m): string {
    $map=[
        'Invalid submission'=>t('Page expired. Reload and try again.','पेज पुराना हो गया। दोबारा खोलकर कोशिश कीजिए।'),
        'Invalid name'=>t('Enter your full name (at least 2 letters).','पूरा नाम लिखिए (कम से कम 2 अक्षर)।'),
        'Invalid vehicle type'=>t('Choose your vehicle.','अपनी गाड़ी चुनिए।'),
        'Invalid vehicle number'=>t('Vehicle number looks wrong. Write it like UP66AB1234.','गाड़ी नंबर सही नहीं लगा। ऐसे लिखिए: UP66AB1234'),
        'Invalid aadhaar'=>t('Write the last 4 digits of your Aadhaar.','आधार के आख़िरी 4 अंक लिखिए।'),
        'Invalid pan'=>t('PAN looks wrong. It has 10 letters/digits like ABCDE1234F.','PAN सही नहीं लगा। इसमें 10 अक्षर-अंक होते हैं, जैसे ABCDE1234F'),
        'Invalid dl'=>t('Licence number looks wrong. Write it like UP6620190012345.','लाइसेंस नंबर सही नहीं लगा। ऐसे लिखिए: UP6620190012345'),
        'Need front'=>t('Add the photo of the front side.','आगे वाले हिस्से की फ़ोटो लगाइए।'),
        'Need back'=>t('Add the photo of the back side too.','पीछे वाले हिस्से की फ़ोटो भी लगाइए।'),
        'Need photo'=>t('Add the photo.','फ़ोटो लगाइए।'),
        'Rate limit'=>t('Too many attempts. Try again after some time.','बहुत कोशिश हुई। कुछ देर बाद कोशिश कीजिए।'),
    ];
    if(isset($map[$m])) return $map[$m];
    if(str_contains($m,'Private directory') || str_contains($m,'outside web root') || str_contains($m,'storage failed')) return t('Photo storage is not set up on the server. Please tell the office.','सर्वर पर फ़ोटो रखने की जगह तैयार नहीं है। दफ़्तर को बताइए।');
    if(str_contains($m,'Upload failed')) return t('Photo did not upload (maybe too large). Try again with the camera.','फ़ोटो अपलोड नहीं हुई (शायद बहुत बड़ी)। कैमरे से दोबारा लीजिए।');
    if(str_contains($m,'Invalid image')) return t('Use a JPG, PNG or WebP photo.','JPG, PNG या WebP फ़ोटो लगाइए।');
    return t('Could not save. Try again or call the office.','सेव नहीं हुआ। दोबारा कोशिश या दफ़्तर को फ़ोन कीजिए।');
}
/** Wizard ke sawaal: naya / lautaya profile → gaadi + naam; jo zaroori kaagaz baaki hai → Aadhaar, PAN */
function sr_wizard_steps(?array $p): array {
    if($p && $p['status']==='suspended') return [];
    $s=[]; if(!$p || $p['status']==='rejected') { $s[]='vehicle'; $s[]='name'; }
    if(!$p || !sr_doc_done($p,'aadhaar')) $s[]='aadhaar';
    if(!$p || !sr_doc_done($p,'pan')) $s[]='pan';
    return $s;
}
function sr_clean_code(string $v): string { return strtoupper(preg_replace('/[^A-Za-z0-9]/','',$v)); }

if($_SERVER['REQUEST_METHOD']==='POST') {
    $saved=[];
    try {
        if(!csrf_ok()) throw new RuntimeException('Invalid submission');
        if(try_blocked($pdo,'profile:'.$a['id'])) throw new RuntimeException('Rate limit');
        if(post('do')==='doc') {
            $kind=(string)post('kind'); $errKind=$kind;
            if(!$p || !isset(sr_doc_kinds()[$kind]) || $p['status']==='suspended') throw new RuntimeException('Invalid submission');
            $files=[]; $fields=[];
            $take=function(string $input,string $col,bool $need,string $msg) use (&$files,&$saved,$p) {
                $f=sr_apply_optional_photo($input); if($f) $saved[]=$f;
                if(!$f && $need && !sr_doc_file_ok($p[$col] ?? '')) throw new RuntimeException($msg);
                $files[$col]=$f;
            };
            if($kind==='photo') { $take('f1','photo',true,'Need photo'); }
            elseif($kind==='aadhaar') {
                $d=preg_replace('/\D/','',(string)post('aadhaar'));
                if(strlen($d)!==4 && strlen($d)!==12) throw new RuntimeException('Invalid aadhaar');
                $fields['aadhaar_last4']=substr($d,-4);           // poora number kabhi save nahi
                $take('f1','aadhaar_front',true,'Need front'); $take('f2','aadhaar_back',true,'Need back');
            } elseif($kind==='pan') {
                $v=sr_clean_code((string)post('pan')); if(!preg_match('/^[A-Z]{5}[0-9]{4}[A-Z]$/D',$v)) throw new RuntimeException('Invalid pan');
                $fields['pan_number']=$v; $take('f1','pan_photo',true,'Need photo');
            } elseif($kind==='licence') {
                $v=sr_clean_code((string)post('dl')); if(!preg_match('/^[A-Z]{2}[0-9]{2}[0-9A-Z]{7,14}$/D',$v)) throw new RuntimeException('Invalid dl');
                $fields['dl_number']=$v; $take('f1','licence_photo',true,'Need front'); $take('f2','dl_back',false,'');
            } elseif($kind==='rc') {
                $v=sr_clean_code((string)post('vehicle_number'));
                if(!preg_match('/^([A-Z]{2}[0-9]{1,2}[A-Z]{0,3}[0-9]{1,4}|[0-9]{2}BH[0-9]{4}[A-Z]{1,2})$/D',$v)) throw new RuntimeException('Invalid vehicle number');
                $fields['vehicle_number']=$v; $take('f1','rc_photo',true,'Need photo');
            }
            sr_profile_set_doc($pdo,$a,$kind,$files,$fields);
            try_ok($pdo,'profile:'.$a['id']); redirect('/apply.php?ok='.$kind.'#docs');
        }
        // Ek-ek sawaal wala wizard (owner: "step wise, easy"): gaadi → naam → Aadhaar → PAN, ek baar me bhejo
        if(post('do')!=='wizard') throw new RuntimeException('Invalid submission');
        $steps=sr_wizard_steps($p);
        if(!$steps) throw new RuntimeException('Invalid submission');
        $errKind='vehicle';
        $nm=trim((string)post('name')); $vt=(string)post('vehicle_type');
        if(in_array('vehicle',$steps,true) && !isset(sr_profile_vehicles()[$vt])) throw new RuntimeException('Invalid vehicle type');
        $errKind='name';
        if(in_array('name',$steps,true) && (mb_strlen($nm)<2 || mb_strlen($nm)>80)) throw new RuntimeException('Invalid name');
        $docsIn=[];
        if(in_array('aadhaar',$steps,true)) {
            $errKind='aadhaar';
            $d=preg_replace('/\D/','',(string)post('aadhaar'));
            if(strlen($d)!==4 && strlen($d)!==12) throw new RuntimeException('Invalid aadhaar');
            $docsIn['aadhaar']=[['aadhaar_front'=>null,'aadhaar_back'=>null],['aadhaar_last4'=>substr($d,-4)]];
        }
        if(in_array('pan',$steps,true)) {
            $errKind='pan';
            $v=sr_clean_code((string)post('pan')); if(!preg_match('/^[A-Z]{5}[0-9]{4}[A-Z]$/D',$v)) throw new RuntimeException('Invalid pan');
            $docsIn['pan']=[['pan_photo'=>null],['pan_number'=>$v]];
        }
        // Photos — sirf tab zaroori jab pehle se na ho
        $slot=['aadhaar_front'=>['a1','Need front'],'aadhaar_back'=>['a2','Need back'],'pan_photo'=>['p1','Need photo']];
        foreach($docsIn as $kind=>&$pair) foreach($pair[0] as $col=>&$file) {
            $errKind=$kind; $file=sr_apply_optional_photo($slot[$col][0]); if($file) $saved[]=$file;
            if(!$file && !sr_doc_file_ok($p[$col] ?? '')) throw new RuntimeException($slot[$col][1]);
        }
        unset($pair,$file);
        $errKind='vehicle';
        if(!$p) sr_profile_submit($pdo,$a,['name'=>$nm,'vehicle_type'=>$vt,'vehicle_number'=>''],'','');
        elseif(in_array('name',$steps,true)) sr_profile_resubmit($pdo,$a,$nm,$vt);
        foreach($docsIn as $kind=>[$files,$fields]) { $errKind=$kind; sr_profile_set_doc($pdo,$a,$kind,$files,$fields); }
        $saved=[];
        try_ok($pdo,'profile:'.$a['id']); redirect('/apply.php?ok=sent');
    } catch(Throwable $e) {
        sr_identity_log('profile_save',$e);
        try { foreach($saved as $file) @unlink(sr_identity_dir().'/'.$file); } catch(Throwable $ignored) {}
        $err=sr_apply_error(preg_replace('/^photo: /','',$e->getMessage()));
    }
}

$vehicles=['bike'=>['बाइक','Bike','<circle cx="5.5" cy="17" r="3.5"/><circle cx="18.5" cy="17" r="3.5"/><path d="M5.5 17l4-8h5l4 8M12 9l-2-4H7"/>'],
    'auto'=>['ऑटो','Auto','<path d="M4 17V9a4 4 0 0 1 4-4h7l5 6v6z"/><path d="M4 11h16M10 5v6"/><circle cx="7.5" cy="17.5" r="2"/><circle cx="17" cy="17.5" r="2"/>'],
    'erick'=>['ई-रिक्शा','E-rickshaw','<path d="M3 16V8h10l4 4h4v4"/><path d="M3 8l2-3h8M13 8v4h8"/><circle cx="7" cy="17" r="2"/><circle cx="18" cy="17" r="2"/><path d="M15 3l-2 3h3l-2 3"/>'],
    'car'=>['कार / बोलेरो','Car / Bolero','<path d="M3 16v-4l2-5h14l2 5v4z"/><path d="M3 12h18"/><circle cx="7" cy="16.5" r="2"/><circle cx="17" cy="16.5" r="2"/>'],
    'loader'=>['लोडर / मैजिक','Loader / Magic','<path d="M2 6h11v10H2zM13 9h5l3 4v3h-8"/><circle cx="6" cy="17.5" r="2"/><circle cx="17" cy="17.5" r="2"/>'],
    'care'=>['अस्पताल साथी','Hospital Saathi','<path d="M12 21s-7-4.6-9.2-9A5 5 0 0 1 12 6a5 5 0 0 1 9.2 6c-2.2 4.4-9.2 9-9.2 9z"/><path d="M12 10v5M9.5 12.5h5"/>']];
$vname=fn($k)=>isset($vehicles[$k]) ? t($vehicles[$k][1],$vehicles[$k][0]) : (sr_profile_vehicles()[$k] ?? $k);

$wz = sr_wizard_steps($p);
sr_customer_head(t('Become a Sarathi','सारथी बनिए'), t('Choose your vehicle, add documents, start earning.','गाड़ी चुनिए, काग़ज़ लगाइए, कमाई शुरू कीजिए।'));
$step = !$p ? 1 : ($p['status']==='approved' ? 3 : 2);
?>
<style>
.ap{display:grid;gap:14px}
.ap-steps{display:grid;grid-template-columns:repeat(3,1fr);gap:6px;margin:0;padding:0;list-style:none}
.ap-steps li{display:flex;flex-direction:column;gap:6px;font:700 12px 'Noto Sans Devanagari',sans-serif;color:#7A8899}
.ap-steps li::before{content:"";height:5px;border-radius:9px;background:#E3E8EF}
.ap-steps li.on{color:#0B1F3A}.ap-steps li.on::before{background:#FFC83D}.ap-steps li.ok::before{background:#10C98F}
.ap h2{margin:4px 0 0;font:800 20px 'Noto Sans Devanagari','Plus Jakarta Sans',sans-serif;color:#0B1F3A}
.ap-msg{margin:0;padding:12px 14px;border-radius:14px;font:700 14px/1.45 'Noto Sans Devanagari',sans-serif}
.ap-msg.err{background:#FDECEA;color:#A3170E}.ap-msg.ok{background:#E7F8F1;color:#0B6B4A}.ap-msg.wait{background:#FFF6DD;color:#7A5200}
.ap-veh{border:0;margin:0;padding:0;display:grid;grid-template-columns:1fr 1fr;gap:10px}
.ap-veh legend{font:800 16px 'Noto Sans Devanagari',sans-serif;color:#0B1F3A;margin-bottom:8px}
.ap-v{position:relative;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:8px;min-height:104px;border:2px solid #E3E8EF;border-radius:18px;background:#fff;cursor:pointer;font:800 15px 'Noto Sans Devanagari',sans-serif;color:#0B1F3A;text-align:center;padding:10px}
.ap-v input{position:absolute;opacity:0;pointer-events:none}
.ap-v svg{width:40px;height:40px}
.ap-v:has(input:checked){border-color:#0B1F3A;background:#FFF8E1;box-shadow:0 0 0 3px rgba(255,200,61,.45)}
.ap-v:has(input:focus-visible){outline:3px solid #1D6FD8}
.ap-field{display:grid;gap:6px;font:700 14px 'Noto Sans Devanagari',sans-serif;color:#0B1F3A}
.ap-field input[type=text]{min-height:52px;border:2px solid #E3E8EF;border-radius:14px;padding:0 14px;font:700 17px 'Plus Jakarta Sans','Noto Sans Devanagari',sans-serif;letter-spacing:.5px;text-transform:uppercase;background:#fff;color:#0B1F3A}
.ap-field input.name{text-transform:none;letter-spacing:0}
.ap-field small{font-weight:500;color:#5A6878}
.ap-go{display:grid;place-items:center;width:100%;min-height:54px;border:0;border-radius:16px;background:#FFC83D;color:#0B1F3A;font:800 16px 'Noto Sans Devanagari',sans-serif;cursor:pointer;text-decoration:none}
.ap-go.navy{background:#0B1F3A;color:#fff}
.ap-prog{display:flex;align-items:center;gap:12px;background:#0B1F3A;color:#fff;border-radius:18px;padding:14px 16px}
.ap-prog b{font:800 26px 'Plus Jakarta Sans',sans-serif;color:#FFC83D}
.ap-prog span{flex:1;font:700 14px/1.4 'Noto Sans Devanagari',sans-serif}
.ap-bar{height:8px;border-radius:9px;background:rgba(255,255,255,.18);margin-top:6px;overflow:hidden}.ap-bar i{display:block;height:100%;background:#FFC83D;border-radius:9px}
.ap-say-all{display:flex;align-items:center;justify-content:center;gap:8px;min-height:50px;border:0;border-radius:14px;background:#5B4BDB;color:#fff;font:800 15px 'Noto Sans Devanagari',sans-serif;cursor:pointer}
.dc{background:#fff;border:1px solid #E3E8EF;border-radius:18px;overflow:hidden}
.dc[open]{border-color:#0B1F3A;box-shadow:0 6px 18px rgba(11,31,58,.08)}
.dc summary{display:flex;align-items:center;gap:12px;padding:12px 10px 12px 14px;min-height:68px;cursor:pointer;list-style:none}
.dc summary::-webkit-details-marker{display:none}
.dc-ic{width:40px;height:40px;border-radius:12px;display:grid;place-items:center;flex:none;background:#F1F4F8;color:#0B1F3A;font-weight:900}
.dc.done .dc-ic{background:#10C98F;color:#fff}
.dc-t{flex:1;min-width:0}.dc-t b{display:block;font:800 15px 'Noto Sans Devanagari',sans-serif;color:#0B1F3A}
.dc-t small{display:block;font:600 12.5px 'Noto Sans Devanagari',sans-serif;color:#C8261B}
.dc.done .dc-t small{color:#0B6B4A}.dc.opt .dc-t small{color:#5A6878}
.dc-say{width:44px;height:44px;border-radius:12px;border:1px solid #E3E8EF;background:#fff;font-size:18px;cursor:pointer;flex:none}
.dc-arrow{color:#7A8899;font-weight:800;flex:none}
.dc form{display:grid;gap:12px;padding:0 14px 16px;border-top:1px dashed #E3E8EF;padding-top:14px}
.dc-hint{margin:0;font:500 13px/1.5 'Noto Sans Devanagari',sans-serif;color:#41505F}
.dc-pics{display:grid;grid-template-columns:1fr 1fr;gap:10px}
.dc-pic{position:relative;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:6px;min-height:118px;border:2px dashed #B9C4D0;border-radius:14px;background:#F7F9FB;cursor:pointer;font:700 13px 'Noto Sans Devanagari',sans-serif;color:#0B1F3A;text-align:center;padding:8px;overflow:hidden}
.dc-pic.full{grid-column:1/-1}
.dc-pic input{position:absolute;inset:0;opacity:0;cursor:pointer;z-index:2}
.dc-pic img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover}
.dc-pic.has span{position:relative;z-index:1;background:rgba(11,31,58,.78);color:#fff;border-radius:8px;padding:4px 8px;margin-top:auto}
.dc-pic.has{border-style:solid;border-color:#10C98F}
.dc-pic em{font-style:normal;font-size:26px}
.dc-view{font:700 13px 'Noto Sans Devanagari',sans-serif;color:#1D6FD8;min-height:44px;display:inline-flex;align-items:center}
.wz{display:grid;gap:14px}
.wz-top{display:flex;align-items:center;gap:10px}
.wz-back{width:44px;height:44px;border-radius:12px;border:1px solid #E3E8EF;background:#fff;font:800 24px/1 sans-serif;color:#0B1F3A;cursor:pointer;flex:none}
.wz-back[disabled]{visibility:hidden}
.wz-count{font:800 13px 'Noto Sans Devanagari',sans-serif;color:#5A6878;white-space:nowrap}
.wz-bar{flex:1;height:8px;border-radius:9px;background:#E3E8EF;overflow:hidden}.wz-bar i{display:block;height:100%;width:0;background:#FFC83D;border-radius:9px;transition:width .25s}
.wz-s{display:grid;gap:14px}
.wz.js .wz-s{display:none}.wz.js .wz-s.on{display:grid}
.wz-h{display:flex;align-items:center;gap:10px}.wz-h h2{flex:1;margin:0;font:800 22px/1.3 'Noto Sans Devanagari','Plus Jakarta Sans',sans-serif;color:#0B1F3A}
.ap-field input.big{min-height:60px;font-size:22px;text-align:center}
.ap-field input.digits{letter-spacing:10px;font-size:28px}
.sr-hide{position:absolute;left:-9999px}
.wz-need{margin:0;color:#A3170E;font:700 14px 'Noto Sans Devanagari',sans-serif}
.dc-pic.need{border-color:#C8261B;background:#FDECEA}
.wz-done{display:grid;justify-items:center;text-align:center;gap:6px;padding:18px 10px;background:#E7F8F1;border-radius:20px}
.wz-done span{width:64px;height:64px;border-radius:50%;background:#10C98F;color:#fff;display:grid;place-items:center;font:900 32px sans-serif}
.wz-done h2{margin:6px 0 0;font:800 20px 'Noto Sans Devanagari',sans-serif;color:#0B1F3A}.wz-done p{margin:0;font:600 14px/1.5 'Noto Sans Devanagari',sans-serif;color:#2F4A3F}
</style>

<section class="sx-card sx-form ap">
<?php if(!$wz): ?><ol class="ap-steps" aria-label="<?= h(t('Steps','चरण')) ?>">
  <li class="<?= $step>1?'ok':'on' ?>"><?= h(t('1. Vehicle','1. गाड़ी')) ?></li>
  <li class="<?= $step>2?'ok':($step===2?'on':'') ?>"><?= h(t('2. Documents','2. काग़ज़')) ?></li>
  <li class="<?= $step===3?'ok':'' ?>"><?= h(t('3. Approval','3. मंज़ूरी')) ?></li>
</ol><?php endif; ?>
<?php if($err): ?><p class="ap-msg err" role="alert"><?= h($err) ?></p><?php endif; ?>
<?php if(get('ok')==='sent' && $p && !$wz): ?>
  <div class="wz-done" role="status"><span aria-hidden="true">✓</span><h2><?= h(t('Done! Your application is sent.','हो गया! आपकी अर्ज़ी भेज दी गई।')) ?></h2>
  <p><?= h($p['status']==='approved' ? t('You can start work now.','अब आप काम शुरू कर सकते हैं।') : t('The office will check and approve you. You will be able to start work after that.','दफ़्तर जाँचकर मंज़ूर करेगा। उसके बाद आप काम शुरू कर पाएँगे।')) ?></p></div>
<?php elseif(get('ok')): ?><p class="ap-msg ok" role="status"><?= h(t('Saved ✓','सेव हो गया ✓')) ?></p><?php endif; ?>

<?php if($p && $p['status']==='suspended'): ?>
  <p class="ap-msg err"><?= h(sr_profile_status('suspended')) ?><?= ($p['review_note'] ?? '')!=='' ? ' — '.h($p['review_note']) : '' ?></p>
  <a class="ap-go navy" href="tel:<?= h(SR_PHONE) ?>"><?= h(t('Call office','दफ़्तर को फ़ोन')) ?></a>
<?php else: ?>

<?php if($p && $p['status']==='rejected'): ?>
  <p class="ap-msg err"><?= h(t('Correction needed','सुधार चाहिए')) ?><?= ($p['review_note'] ?? '')!=='' ? ': '.h($p['review_note']) : '' ?></p>
<?php endif; ?>

<?php if($wz): $wzN=count($wz); $start=in_array($errKind,$wz,true)?$errKind:$wz[0];
  $q=[
    'vehicle'=>[t('Which vehicle will you drive?','आप कौन-सी गाड़ी चलाएँगे?'),t('Tap your vehicle.','अपनी गाड़ी पर दबाइए।'),t('Which vehicle will you drive? Tap on it.','आप कौन-सी गाड़ी चलाएँगे? उस पर दबाइए।')],
    'name'=>[t('What is your name?','आपका नाम क्या है?'),t('Full name, as on Aadhaar.','पूरा नाम, जैसा आधार पर लिखा है।'),t('Write your full name as written on your Aadhaar.','अपना पूरा नाम लिखिए, जैसा आधार पर लिखा है।')],
    'aadhaar'=>[t('Aadhaar card','आधार कार्ड'),t('Last 4 digits and photos of both sides. We never save the full number.','आख़िरी 4 अंक और दोनों तरफ़ की फ़ोटो। पूरा नंबर हम कभी नहीं रखते।'),t('Write the last four digits of your Aadhaar. Then take a photo of the front and the back.','आधार के आख़िरी चार अंक लिखिए। फिर आधार के आगे और पीछे की फ़ोटो लीजिए।')],
    'pan'=>[t('PAN card','पैन कार्ड'),t('PAN number and a photo of the card.','पैन नंबर और कार्ड की फ़ोटो।'),t('Write your PAN number and take a photo of the PAN card. Then tap send.','अपना पैन नंबर लिखिए और पैन कार्ड की फ़ोटो लीजिए। फिर भेज दीजिए दबाइए।')],
  ];
  $tile=function(string $in,string $col,string $label,string $cap='') use ($p,$a) {
      $has=$p && sr_doc_file_ok($p[$col] ?? ''); $kind=['aadhaar_front'=>'aadhaar_front','aadhaar_back'=>'aadhaar_back','pan_photo'=>'pan'][$col]; ?>
      <label class="dc-pic <?= $has?'has':'' ?>" data-need="<?= $has?'0':'1' ?>"><input type="file" name="<?= h($in) ?>" accept="image/*" data-shrink>
        <?php if($has): ?><img src="<?= h(u('/identity-photo.php?id='.(int)$a['id'].'&kind='.$kind)) ?>" alt=""><?php else: ?><em aria-hidden="true">📷</em><?php endif; ?>
        <span><?= h($label) ?></span></label>
  <?php };
?>
<form method="post" enctype="multipart/form-data" class="wz" id="wz" data-start="<?= h($start) ?>" novalidate>
  <input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="do" value="wizard">
  <div class="wz-top"><button type="button" class="wz-back" id="wz-back" aria-label="<?= h(t('Back','वापस')) ?>">‹</button>
    <span class="wz-count" id="wz-count"></span><div class="wz-bar"><i id="wz-bar"></i></div></div>
  <?php foreach($wz as $i=>$k): [$title,$hint,$say]=$q[$k]; ?>
  <section class="wz-s" data-k="<?= h($k) ?>">
    <div class="wz-h"><h2><?= h($title) ?></h2><button type="button" class="dc-say" data-say="<?= h($say) ?>" aria-label="<?= h(t('Listen','सुनिए')) ?>">🔊</button></div>
    <p class="dc-hint"><?= h($hint) ?></p>
    <?php if($k==='vehicle'): $cur=post('vehicle_type',$p['vehicle_type'] ?? ''); ?>
      <div class="ap-veh"><?php foreach($vehicles as $key=>$v): ?>
        <label class="ap-v"><input type="radio" name="vehicle_type" value="<?= h($key) ?>" required <?= $cur===$key?'checked':'' ?>>
        <svg viewBox="0 0 24 24" fill="none" stroke="#0B1F3A" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><?= $v[2] ?></svg><?= h(t($v[1],$v[0])) ?></label>
      <?php endforeach; ?></div>
    <?php elseif($k==='name'): ?>
      <label class="ap-field"><span class="sr-hide"><?= h(t('Full name','पूरा नाम')) ?></span><input class="name big" type="text" name="name" required minlength="2" maxlength="80" autocomplete="name" placeholder="<?= h(t('e.g. Ramesh Kumar','जैसे: रमेश कुमार')) ?>" value="<?= h(post('name',$p['name'] ?? '')) ?>"></label>
    <?php elseif($k==='aadhaar'): ?>
      <label class="ap-field"><?= h(t('Last 4 digits of Aadhaar','आधार के आख़िरी 4 अंक')) ?><input class="big digits" type="text" name="aadhaar" inputmode="numeric" maxlength="4" pattern="[0-9]{4}" required placeholder="• • • •" value="<?= h(post('aadhaar',$p['aadhaar_last4'] ?? '')) ?>"></label>
      <div class="dc-pics"><?php $tile('a1','aadhaar_front',t('Front side','आगे का हिस्सा')); $tile('a2','aadhaar_back',t('Back side','पीछे का हिस्सा')); ?></div>
    <?php elseif($k==='pan'): ?>
      <label class="ap-field"><?= h(t('PAN number','पैन नंबर')) ?><input class="big" type="text" name="pan" maxlength="10" required pattern="[A-Za-z]{5}[0-9]{4}[A-Za-z]" placeholder="ABCDE1234F" autocapitalize="characters" value="<?= h(post('pan',$p['pan_number'] ?? '')) ?>"></label>
      <div class="dc-pics"><?php $tile('p1','pan_photo',t('PAN card photo','पैन कार्ड की फ़ोटो')); ?></div>
    <?php endif; ?>
    <p class="wz-need" hidden><?= h(t('Please fill this step first.','पहले यह भरिए।')) ?></p>
    <?php if($i<$wzN-1): ?>
      <button type="button" class="ap-go wz-next"><?= h(t('Next →','आगे →')) ?></button>
    <?php else: ?>
      <button type="submit" class="ap-go wz-next"><?= h(t('Send ✓','भेज दीजिए ✓')) ?></button>
    <?php endif; ?>
  </section>
  <?php endforeach; ?>
  <?php if($p && $p['status']==='approved'): ?><a class="dc-view" href="<?= h(u('/kaam.php')) ?>"><?= h(t('Later — go to work','बाद में — काम पर जाइए')) ?></a><?php endif; ?>
</form>
<?php endif; ?>

<?php if($p && !$wz):
  [$done,$total]=sr_doc_count($p); $req=sr_doc_required($p);
  $care=$p['vehicle_type']==='care';
  $docs=[
    'photo'=>[t('Profile photo (selfie)','प्रोफ़ाइल फ़ोटो (सेल्फ़ी)'),'🙂',
        t('Take a clear selfie in good light. No cap or dark glasses.','अच्छी रोशनी में साफ़ सेल्फ़ी लीजिए। टोपी या काला चश्मा नहीं।'),
        t('Tap here and take a clear selfie of your face.','यहाँ दबाकर अपने चेहरे की साफ़ सेल्फ़ी लीजिए।')],
    'aadhaar'=>[t('Aadhaar card','आधार कार्ड'),'🪪',
        t('Photos of the front and back. Masked Aadhaar from the UIDAI site is best. We save only the last 4 digits of the number.','आगे और पीछे दोनों की फ़ोटो। UIDAI से मिलने वाला मास्क्ड आधार सबसे अच्छा है। हम नंबर के सिर्फ़ आख़िरी 4 अंक रखते हैं।'),
        t('Write the last 4 digits of your Aadhaar and take photos of the front and back.','आधार के आख़िरी चार अंक लिखिए और आगे-पीछे की फ़ोटो लीजिए।')],
    'pan'=>[t('PAN card','पैन कार्ड'),'💳',
        t('Needed for payments. Write the PAN number and take a photo of the card.','पैसे के लेन-देन के लिए ज़रूरी। PAN नंबर लिखिए और कार्ड की फ़ोटो लीजिए।'),
        t('Write your PAN number and take a photo of the PAN card.','अपना पैन नंबर लिखिए और पैन कार्ड की फ़ोटो लीजिए।')],
    'licence'=>[t('Driving licence','ड्राइविंग लाइसेंस'),'🚦',
        t('Write the licence number and take a photo of the front. Back side if it has details.','लाइसेंस नंबर लिखिए और आगे की फ़ोटो लीजिए। पीछे जानकारी हो तो उसकी भी।'),
        t('Write your driving licence number and take a photo of the licence.','ड्राइविंग लाइसेंस का नंबर लिखिए और लाइसेंस की फ़ोटो लीजिए।')],
    'rc'=>[t('Vehicle RC & number','गाड़ी का RC और नंबर'),'🚘',
        t('Write the vehicle number exactly as on the number plate and take a photo of the RC.','नंबर प्लेट पर जैसा लिखा है वैसा गाड़ी नंबर लिखिए और RC की फ़ोटो लीजिए।'),
        t('Write your vehicle number and take a photo of the R C.','अपनी गाड़ी का नंबर लिखिए और आर सी की फ़ोटो लीजिए।')],
  ];
  $next=''; foreach($req as $k) if(!sr_doc_done($p,$k)) { $next=$docs[$k][3]; break; }
  $statusSay = $p['status']==='approved' ? t('You are approved. Start work.','आप मंज़ूर हैं। काम शुरू कीजिए।')
      : ($done<$total ? t('Next document: ','अगला काग़ज़: ').$next : t('Aadhaar and PAN are submitted. The office is checking.','आधार और पैन जमा हो गए। दफ़्तर जाँच कर रहा है।'));
?>
<div id="docs" class="ap">
  <?php if($p['status']==='approved'): ?>
    <p class="ap-msg ok"><?= h(t('You are approved ✓','आप मंज़ूर हैं ✓')) ?></p>
    <a class="ap-go" href="<?= h(u('/kaam.php')) ?>"><?= h(t('Start work →','काम शुरू कीजिए →')) ?></a>
  <?php elseif($p['status']==='pending'): ?>
    <p class="ap-msg wait"><?= h($done<$total ? t('Add Aadhaar and PAN so the office can approve you.','आधार और पैन लगाइए ताकि दफ़्तर मंज़ूर कर सके।') : t('Aadhaar and PAN received ✓ — the office is checking. Call if urgent.','आधार और पैन मिल गए ✓ — दफ़्तर जाँच कर रहा है। जल्दी हो तो फ़ोन कीजिए।')) ?></p>
  <?php endif; ?>

  <div class="ap-prog"><b><?= (int)$done ?>/<?= (int)$total ?></b><span><?= h(t('required documents (Aadhaar, PAN)','ज़रूरी काग़ज़ (आधार, पैन)')) ?> · <?= h($vname($p['vehicle_type'])) ?><div class="ap-bar"><i style="width:<?= $total ? (int)round($done*100/$total) : 0 ?>%"></i></div></span></div>
  <button type="button" class="ap-say-all" data-say="<?= h($statusSay) ?>"><span aria-hidden="true">🔊</span> <?= h(t('Listen: what to do next','सुनिए: आगे क्या करना है')) ?></button>

  <?php $opened=false; foreach(array_merge($req,sr_doc_optional($p)) as $k): $d=$docs[$k]; $ok=sr_doc_done($p,$k); $must=in_array($k,$req,true);
    $open = ($errKind===$k) || ($must && !$opened && !$ok && !$errKind && $p['status']!=='approved'); if($open) $opened=true;
    $sub = $ok ? t('Submitted ✓','जमा ✓') : ($must ? t('Pending · required','बाकी · ज़रूरी') : t('Optional','ज़रूरी नहीं'));
    if($ok && $k==='rc') $sub.=' · '.$p['vehicle_number'];
    if($ok && $k==='aadhaar') $sub.=' · XXXX '.$p['aadhaar_last4'];
    if($ok && $k==='pan') $sub.=' · '.substr($p['pan_number'],0,2).'XXXXXX'.substr($p['pan_number'],-2);
  ?>
  <details class="dc <?= $ok?'done':($must?'':'opt') ?>" id="doc-<?= h($k) ?>" <?= $open?'open':'' ?>>
    <summary><span class="dc-ic" aria-hidden="true"><?= $ok ? '✓' : $d[1] ?></span><span class="dc-t"><b><?= h($d[0]) ?></b><small><?= h($sub) ?></small></span>
      <button type="button" class="dc-say" data-say="<?= h($d[3]) ?>" aria-label="<?= h(t('Listen','सुनिए')) ?>">🔊</button><span class="dc-arrow" aria-hidden="true">›</span></summary>
    <form method="post" enctype="multipart/form-data">
      <input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="do" value="doc"><input type="hidden" name="kind" value="<?= h($k) ?>">
      <p class="dc-hint"><?= h($d[2]) ?></p>
      <?php if($k==='aadhaar'): ?>
        <label class="ap-field"><?= h(t('Last 4 digits of Aadhaar','आधार के आख़िरी 4 अंक')) ?><input type="text" name="aadhaar" inputmode="numeric" maxlength="4" pattern="[0-9]{4}" required placeholder="1234" value="<?= h($p['aadhaar_last4'] ?? '') ?>"></label>
      <?php elseif($k==='pan'): ?>
        <label class="ap-field"><?= h(t('PAN number','पैन नंबर')) ?><input type="text" name="pan" maxlength="10" required placeholder="ABCDE1234F" autocapitalize="characters" value="<?= h($p['pan_number'] ?? '') ?>"></label>
      <?php elseif($k==='licence'): ?>
        <label class="ap-field"><?= h(t('Driving licence number','ड्राइविंग लाइसेंस नंबर')) ?><input type="text" name="dl" maxlength="20" required placeholder="UP6620190012345" autocapitalize="characters" value="<?= h($p['dl_number'] ?? '') ?>"></label>
      <?php elseif($k==='rc'): ?>
        <label class="ap-field"><?= h(t('Vehicle number','गाड़ी नंबर')) ?><input type="text" name="vehicle_number" maxlength="13" required placeholder="UP66AB1234" autocapitalize="characters" value="<?= h($p['vehicle_number'] ?? '') ?>"><small><?= h(t('As written on the number plate','नंबर प्लेट पर जैसा लिखा है')) ?></small></label>
      <?php endif; ?>
      <div class="dc-pics">
      <?php
        $slots=['photo'=>[['f1','photo',t('Take selfie','सेल्फ़ी लीजिए'),'user',true]],
          'aadhaar'=>[['f1','aadhaar_front',t('Front side','आगे का हिस्सा'),'environment',false],['f2','aadhaar_back',t('Back side','पीछे का हिस्सा'),'environment',false]],
          'pan'=>[['f1','pan_photo',t('PAN card photo','पैन कार्ड की फ़ोटो'),'environment',true]],
          'licence'=>[['f1','licence_photo',t('Front side','आगे का हिस्सा'),'environment',false],['f2','dl_back',t('Back side (optional)','पीछे का हिस्सा (ज़रूरी नहीं)'),'environment',false]],
          'rc'=>[['f1','rc_photo',t('RC photo','RC की फ़ोटो'),'environment',true]]][$k];
        $viewKind=['photo'=>'photo','aadhaar_front'=>'aadhaar_front','aadhaar_back'=>'aadhaar_back','pan_photo'=>'pan','licence_photo'=>'licence','dl_back'=>'dl_back','rc_photo'=>'rc'];
        foreach($slots as [$in,$col,$label,$cap,$full]): $has=sr_doc_file_ok($p[$col] ?? ''); ?>
        <label class="dc-pic <?= $full?'full':'' ?> <?= $has?'has':'' ?>"><input type="file" name="<?= h($in) ?>" accept="image/*"<?= $cap==='user' ? ' capture="user"' : '' ?> data-shrink>
          <?php if($has): ?><img src="<?= h(u('/identity-photo.php?id='.(int)$a['id'].'&kind='.$viewKind[$col])) ?>" alt="" loading="lazy"><?php else: ?><em aria-hidden="true">📷</em><?php endif; ?>
          <span><?= h($has ? $label.' ✓ — '.t('tap to change','बदलने के लिए दबाइए') : $label) ?></span></label>
      <?php endforeach; ?>
      </div>
      <button class="ap-go navy" type="submit"><?= h($ok ? t('Update','बदलकर सेव कीजिए') : t('Save','सेव कीजिए')) ?></button>
    </form>
  </details>
  <?php endforeach; ?>
  <?php if($p['status']!=='rejected'): ?><p class="dc-hint"><?= h(t('Want to change vehicle or name? Call the office.','गाड़ी या नाम बदलना है? दफ़्तर को फ़ोन कीजिए।')) ?> <a class="dc-view" href="tel:<?= h(SR_PHONE) ?>"><?= h(SR_PHONE_SHOW) ?></a></p><?php endif; ?>
</div>
<?php endif; ?>
<?php endif; ?>
</section>
<script>
// 🔊 Hindi awaaz guide
document.addEventListener('click', function (e) {
  var b = e.target.closest('[data-say]'); if (!b) return;
  e.preventDefault(); e.stopPropagation();
  if (!('speechSynthesis' in window)) return;
  var u = new SpeechSynthesisUtterance(b.getAttribute('data-say'));
  u.lang = document.documentElement.lang === 'en' ? 'en-IN' : 'hi-IN'; u.rate = 0.95;
  var v = speechSynthesis.getVoices().filter(function (x) { return x.lang && x.lang.toLowerCase().indexOf(u.lang.toLowerCase().slice(0, 2)) === 0; })[0]; if (v) u.voice = v;
  speechSynthesis.cancel(); speechSynthesis.speak(u);
}, true);
// Ek-ek sawaal wala wizard
(function () {
  var f = document.getElementById('wz'); if (!f) return;
  var steps = Array.prototype.slice.call(f.querySelectorAll('.wz-s')), cur = 0, back = document.getElementById('wz-back');
  var hi = document.documentElement.lang !== 'en';
  f.classList.add('js');
  steps.forEach(function (s, i) { if (s.getAttribute('data-k') === f.getAttribute('data-start')) cur = i; });
  function show(i) {
    cur = i; steps.forEach(function (s, j) { s.classList.toggle('on', j === i); });
    document.getElementById('wz-count').textContent = (hi ? 'चरण ' : 'Step ') + (i + 1) + '/' + steps.length;
    document.getElementById('wz-bar').style.width = Math.round((i + 1) * 100 / steps.length) + '%';
    back.disabled = i === 0; window.scrollTo(0, 0);
    var first = steps[i].querySelector('input[type=text]'); if (first && !first.value) setTimeout(function () { first.focus(); }, 150);
  }
  function ok(s) {
    var good = true, need = s.querySelector('.wz-need');
    s.querySelectorAll('.dc-pic').forEach(function (p) {
      var inp = p.querySelector('input'), missing = p.getAttribute('data-need') === '1' && !(inp.files && inp.files.length);
      p.classList.toggle('need', missing); if (missing) good = false;
    });
    var radios = s.querySelectorAll('input[type=radio]');
    if (radios.length && !s.querySelector('input[type=radio]:checked')) good = false;
    s.querySelectorAll('input[type=text]').forEach(function (t) { if (good && !t.checkValidity()) { t.reportValidity(); good = false; } });
    need.hidden = good; return good;
  }
  f.addEventListener('click', function (e) {
    var b = e.target.closest('.wz-next'); if (!b) return;
    if (!ok(steps[cur])) { e.preventDefault(); return; }
    if (b.type === 'button') { e.preventDefault(); show(cur + 1); }
    else { b.disabled = true; b.textContent = hi ? 'भेज रहे हैं…' : 'Sending…'; f.submit(); }
  });
  f.addEventListener('change', function (e) {
    if (e.target.type === 'radio') setTimeout(function () { if (cur < steps.length - 1) show(cur + 1); }, 250);
    var p = e.target.closest('.dc-pic'); if (p) p.classList.remove('need');
  });
  f.addEventListener('keydown', function (e) { if (e.key === 'Enter' && e.target.type === 'text') { e.preventDefault(); var n = steps[cur].querySelector('.wz-next'); if (n) n.click(); } });
  back.addEventListener('click', function () { if (cur > 0) show(cur - 1); });
  show(cur);
})();
// Photo phone par hi chhoti (1600px JPEG) — bada camera photo bhi aaram se upload ho
document.querySelectorAll('input[data-shrink]').forEach(function (inp) {
  inp.addEventListener('change', function () {
    var f = inp.files && inp.files[0]; if (!f || !/^image\//.test(f.type)) return;
    var box = inp.closest('.dc-pic'), url = URL.createObjectURL(f), img = new Image();
    img.onload = function () {
      var s = Math.min(1, 1600 / Math.max(img.width, img.height)), c = document.createElement('canvas');
      c.width = Math.round(img.width * s); c.height = Math.round(img.height * s);
      c.getContext('2d').drawImage(img, 0, 0, c.width, c.height);
      c.toBlob(function (blob) {
        if (blob && window.DataTransfer) {
          try { var dt = new DataTransfer(); dt.items.add(new File([blob], 'photo.jpg', { type: 'image/jpeg' })); inp.files = dt.files; } catch (x) {}
        }
        var pic = box.querySelector('img') || box.appendChild(document.createElement('img'));
        pic.src = url; box.classList.add('has'); var em = box.querySelector('em'); if (em) em.remove();
      }, 'image/jpeg', 0.85);
    };
    img.src = url;
  });
});
</script>
<?php sr_customer_foot(); ?>
