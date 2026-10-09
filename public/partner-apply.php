<?php
require_once __DIR__.'/../inc/fn.php';
require_once __DIR__.'/../controllers/partners.php';
header('Cache-Control: no-store');
$no_tabbar=true;$page_title='Partner application — Maakit';
$kind=($_GET['kind']??'vendor')==='rider'?'RIDER':'VENDOR';$error='';$done=false;
if($_SERVER['REQUEST_METHOD']==='POST'){
 foreach($_POST as $value)if(!is_string($value)){http_response_code(400);exit;}
 if((int)($_SERVER['CONTENT_LENGTH']??0)>22000000){http_response_code(413);exit;}
 if(!csrf_ok()){http_response_code(403);$error=t('Invalid form session. Reload and retry.','पन्ना दोबारा खोलकर आवेदन भेजिए।');}
 else {
 $name=post('applicant_name');$mobile=preg_replace('/\D/','',post('mobile'));
 $business=post('business_name');$market=post('market');$category=post('shop_category');
 $gst=strtoupper(preg_replace('/\s+/','',post('gstin')));$pan=strtoupper(post('pan'));
 $vehicle=post('vehicle_type');$dl=strtoupper(post('dl_number'));$rc=strtoupper(post('rc_number'));
 $credential=post('credential');if($kind==='VENDOR')$credential=strtoupper($credential);
 if(!cust_password_ok($credential))$error=cust_password_error();
 elseif(mb_strlen($name)<2||mb_strlen($name)>80||!preg_match('/^[6-9][0-9]{9}$/D',$mobile))$error=t('Enter a valid name and Indian mobile number.','सही नाम और भारतीय मोबाइल नंबर लिखिए।');
 elseif($kind==='VENDOR' && (mb_strlen($business)<2||mb_strlen($business)>200||!in_array($market,['B2B','B2C','BOTH'],true)||!in_array($category,['Grocery','Food','Clothing','Electronics','Hardware','Medical','Home','Other'],true)))$error=t('Enter your business, market and category.','दुकान का नाम, प्रकार और श्रेणी भरिए।');
 elseif($kind==='VENDOR' && $market!=='B2C' && !$gst && !$pan)$error=t('B2B sellers must provide GSTIN or PAN for manual verification.','B2B के लिए GSTIN या PAN नंबर भरिए।');
 elseif($gst && !preg_match('/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/D',$gst))$error=t('GSTIN format is invalid.','GSTIN नंबर जाँचकर सही लिखिए।');
 elseif($pan && !preg_match('/^[A-Z]{5}[0-9]{4}[A-Z]$/D',$pan))$error=t('PAN format is invalid.','PAN नंबर जाँचकर सही लिखिए।');
 elseif($kind==='RIDER' && (!in_array($vehicle,['Bicycle','Bike','Scooter','Auto','Car','Van'],true)||!preg_match('/^[A-Z0-9 -]{6,25}$/D',$dl)||!preg_match('/^[A-Z0-9 -]{6,30}$/D',$rc)))$error=t('Provide vehicle type, driving licence and RC details.','गाड़ी का प्रकार, ड्राइविंग लाइसेंस और RC की जानकारी भरिए।');
 elseif(auth_attempt_try($pdo,'partner_signup',$mobile)){http_response_code(429);$error=t('Too many applications. Try again in 15 minutes.','बहुत बार आवेदन भेजे गए। 15 मिनट बाद कोशिश करें।');}
 else {
 $files=[];
 try {
 $files=Maakit\Api\receive_documents($kind,$_FILES);
 $types=array_column($files,'kind');
 if($kind==='RIDER' && array_diff(['DL','RC'],$types))throw new InvalidArgumentException(t('Upload both driving licence and RC documents.','ड्राइविंग लाइसेंस और RC दोनों लगाइए।'));
 if($kind==='VENDOR' && $market!=='B2C' && !array_intersect(['GST','PAN'],$types))throw new InvalidArgumentException(t('Upload a GST or PAN document for B2B verification.','B2B जाँच के लिए GST या PAN दस्तावेज़ लगाइए।'));
 $pdo->beginTransaction();
 $st=$pdo->prepare("INSERT INTO users(name,username,password,role,mobile,active) VALUES(?,?,?,?,?,0)");
 $st->execute([$name,$mobile,password_hash($credential,PASSWORD_DEFAULT),strtolower($kind),$mobile]);$loginId=(int)$pdo->lastInsertId();
 $st=$pdo->prepare("INSERT INTO mk_partner_applications(kind,applicant_name,mobile,business_name,market,shop_category,gstin,pan,vehicle_type,dl_number,rc_number,login_user_id) VALUES(?,?,?,?,?,?,?,?,?,?,?,?)");
 $st->execute([$kind,$name,$mobile,$kind==='VENDOR'?$business:null,$kind==='VENDOR'?$market:null,$kind==='VENDOR'?$category:null,$kind==='VENDOR'?($gst?:null):null,$kind==='VENDOR'?($pan?:null):null,$kind==='RIDER'?$vehicle:null,$kind==='RIDER'?$dl:null,$kind==='RIDER'?$rc:null,$loginId]);$applicationId=(int)$pdo->lastInsertId();
 $st=$pdo->prepare('INSERT INTO mk_partner_documents(application_id,kind,storage_key,mime,size_bytes) VALUES(?,?,?,?,?)');
 foreach($files as $f)$st->execute([$applicationId,$f['kind'],$f['storage_key'],$f['mime'],$f['size_bytes']]);
 $pdo->commit();redirect('/public/partner-apply.php?kind='.strtolower($kind).'&submitted=1');
 }catch(Throwable $e){
 if($pdo->inTransaction())$pdo->rollBack();Maakit\Api\remove_documents($files);
 if($e instanceof InvalidArgumentException)$error=t('Check the document type, size and required documents; then try again.','दस्तावेज़ का प्रकार, आकार और जरूरी दस्तावेज़ जाँचकर दोबारा भेजिए।');
 elseif($e instanceof PDOException && $e->getCode()==='23000')$error=t('This mobile already has a login. Sign in or contact Maakit to review your application.','इस नंबर का खाता पहले से है। लॉगिन करें या आवेदन के लिए Maakit से संपर्क करें।');
 else{error_log('Partner application save failed class='.get_class($e));http_response_code(503);$error=t('Application unavailable. Please try later.','अभी आवेदन नहीं भेजा जा सका। थोड़ी देर बाद कोशिश करें।');}
 }

 }
 }
}
$done=($_GET['submitted']??'')==='1';
include __DIR__.'/../inc/head.php';
?>
<section><div class="wrap" style="max-width:620px"><h1><?= $kind==='VENDOR'?t('Seller onboarding','दुकानदार आवेदन'):t('Rider onboarding','राइडर आवेदन') ?></h1>
<?php if($done): ?><div class="box"><h2><?= t('Application submitted','आवेदन भेज दिया गया') ?></h2><p><?= t('Your details are pending manual verification. Approval does not happen automatically.','आपका आवेदन जाँच के लिए भेज दिया गया है। टीम की मंजूरी के बाद लॉगिन खुलेगा।') ?></p><a href="/"><?= t('Home','होम') ?></a></div>
<?php else: ?>
<?php if($error): ?><p class="err" role="alert"><?= h($error) ?></p><?php endif; ?>
<form class="box" method="post" enctype="multipart/form-data"><input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
<div class="field"><label><?= t('Full name','पूरा नाम') ?><input name="applicant_name" maxlength="80" required value="<?= h(post('applicant_name')) ?>"></label></div>
<div class="field"><label><?= t('Mobile number','मोबाइल नंबर') ?><input name="mobile" type="tel" pattern="[6-9][0-9]{9}" maxlength="10" required value="<?= h(post('mobile')) ?>"></label></div>
<?php if($kind==='VENDOR'): ?>
<div class="field"><label><?= t('Business name','दुकान का नाम') ?><input name="business_name" maxlength="200" required value="<?= h(post('business_name')) ?>"></label></div>
<div class="field"><label><?= t('Seller type','दुकान का प्रकार') ?><select name="market" required><option value=""><?= t('Select','चुनिए') ?></option><?php foreach(['B2C','B2B','BOTH'] as $x): ?><option <?= post('market')===$x?'selected':'' ?>><?= h($x) ?></option><?php endforeach; ?></select></label></div>
<div class="field"><label><?= t('Shop category','दुकान की श्रेणी') ?><select name="shop_category" required><option value=""><?= t('Select','चुनिए') ?></option><?php foreach(['Grocery','Food','Clothing','Electronics','Hardware','Medical','Home','Other'] as $x): ?><option <?= post('shop_category')===$x?'selected':'' ?>><?= h($x) ?></option><?php endforeach; ?></select></label></div>
<div class="field"><label><?= t('GSTIN (if applicable)','GSTIN (यदि लागू हो)') ?><input name="gstin" maxlength="15" value="<?= h(post('gstin')) ?>"></label></div>
<div class="field"><label><?= t('PAN (B2B alternative)','PAN (B2B के लिए)') ?><input name="pan" maxlength="10" value="<?= h(post('pan')) ?>"></label></div>
<?php else: ?>
<div class="field"><label><?= t('Vehicle type','गाड़ी का प्रकार') ?><select name="vehicle_type" required><option value=""><?= t('Select','चुनिए') ?></option><?php foreach(['Bicycle','Bike','Scooter','Auto','Car','Van'] as $x): ?><option <?= post('vehicle_type')===$x?'selected':'' ?>><?= h($x) ?></option><?php endforeach; ?></select></label></div>
<div class="field"><label><?= t('Driving licence number','ड्राइविंग लाइसेंस नंबर') ?><input name="dl_number" maxlength="25" required value="<?= h(post('dl_number')) ?>"></label></div>
<div class="field"><label><?= t('RC registration number','RC नंबर') ?><input name="rc_number" maxlength="30" required value="<?= h(post('rc_number')) ?>"></label></div>
<?php endif; ?>
<div class="field"><label><?= $kind==='VENDOR'?t('Choose your login code (8–72 characters; letters become uppercase)','अपना लॉगिन कोड चुनिए (8–72 अक्षर; अंग्रेज़ी अक्षर बड़े हो जाएँगे)'):t('Choose your password (8–72 characters)','अपना पासवर्ड चुनिए (8–72 अक्षर)') ?><input type="password" name="credential" minlength="8" maxlength="72" autocomplete="new-password" required></label></div>
<?php foreach($kind==='VENDOR'?['gst_document'=>['GST certificate','GST प्रमाणपत्र'],'pan_document'=>['PAN document','PAN दस्तावेज़']]:['dl_document'=>['Driving licence','ड्राइविंग लाइसेंस'],'rc_document'=>['RC document','RC दस्तावेज़']] as $field=>$label): ?>
<div class="field"><label><?= h(t($label[0],$label[1])) ?><input type="file" name="<?= h($field) ?>" accept="image/jpeg,image/png,application/pdf" <?= $kind==='RIDER'?'required':'' ?>></label></div>
<?php endforeach; ?>
<p class="help"><?= t('JPG, PNG or PDF; up to 5 MB each. B2B needs GST or PAN. Documents stay private for manual review. After approval, vendors sign in with mobile + code; riders use their mobile as username and password.','JPG, PNG या PDF; हर फ़ाइल अधिकतम 5 MB। B2B के लिए GST या PAN जरूरी है। दस्तावेज़ निजी जाँच के लिए सुरक्षित रखे जाएँगे। मंजूरी के बाद दुकानदार मोबाइल और कोड से; राइडर मोबाइल यूज़रनेम और पासवर्ड से लॉगिन करें।') ?></p>
<button class="btn btn-brand" type="submit"><?= t('Submit for approval','मंजूरी के लिए भेजिए') ?></button></form><?php endif; ?></div></section>
<?php include __DIR__.'/../inc/foot.php'; ?>
