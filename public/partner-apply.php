<?php
require_once __DIR__.'/../inc/fn.php';
$no_tabbar=true;$page_title='Partner application — Maakit';
$kind=($_GET['kind']??'vendor')==='rider'?'RIDER':'VENDOR';$error='';$done=false;
if($_SERVER['REQUEST_METHOD']==='POST'){
 if(!csrf_ok()){http_response_code(403);$error='Invalid form session. Reload and retry.';}
 else {
 $name=post('applicant_name');$mobile=preg_replace('/\D/','',post('mobile'));
 $business=post('business_name');$market=post('market');$category=post('shop_category');
 $gst=strtoupper(preg_replace('/\s+/','',post('gstin')));$pan=strtoupper(post('pan'));
 $vehicle=post('vehicle_type');$dl=strtoupper(post('dl_number'));$rc=strtoupper(post('rc_number'));
 if(mb_strlen($name)<2||mb_strlen($name)>150||!preg_match('/^[6-9][0-9]{9}$/D',$mobile))$error='Enter a valid name and Indian mobile number.';
 elseif($kind==='VENDOR' && (mb_strlen($business)<2||mb_strlen($business)>200||!in_array($market,['B2B','B2C','BOTH'],true)||!in_array($category,['Grocery','Food','Clothing','Electronics','Hardware','Medical','Home','Other'],true)))$error='Enter your business, market and category.';
 elseif($kind==='VENDOR' && $market!=='B2C' && !$gst && !$pan)$error='B2B sellers must provide GSTIN or PAN for manual verification.';
 elseif($gst && !preg_match('/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/D',$gst))$error='GSTIN format is invalid.';
 elseif($pan && !preg_match('/^[A-Z]{5}[0-9]{4}[A-Z]$/D',$pan))$error='PAN format is invalid.';
 elseif($kind==='RIDER' && (!in_array($vehicle,['Bicycle','Bike','Scooter','Auto','Car','Van'],true)||!preg_match('/^[A-Z0-9 -]{6,25}$/D',$dl)||!preg_match('/^[A-Z0-9 -]{6,30}$/D',$rc)))$error='Provide vehicle type, driving licence and RC details.';
 else {
 try {
 $st=$pdo->prepare("INSERT INTO mk_partner_applications(kind,applicant_name,mobile,business_name,market,shop_category,gstin,pan,vehicle_type,dl_number,rc_number) VALUES(?,?,?,?,?,?,?,?,?,?,?)");
 $st->execute([$kind,$name,$mobile,$kind==='VENDOR'?$business:null,$kind==='VENDOR'?$market:null,$kind==='VENDOR'?$category:null,$kind==='VENDOR'?($gst?:null):null,$kind==='VENDOR'?($pan?:null):null,$kind==='RIDER'?$vehicle:null,$kind==='RIDER'?$dl:null,$kind==='RIDER'?$rc:null]);$done=true;
 }catch(PDOException $e){error_log('Partner application save failed');http_response_code(503);$error='Application unavailable. Please try later.';}
 }
 }
}
include __DIR__.'/../inc/head.php';
?>
<section><div class="wrap" style="max-width:620px"><h1><?= $kind==='VENDOR'?'Seller onboarding':'Rider onboarding' ?></h1>
<?php if($done): ?><div class="box"><h2>Application submitted</h2><p>Your details are pending manual verification. Approval does not happen automatically.</p><a href="/">Home</a></div>
<?php else: ?>
<?php if($error): ?><p class="err" role="alert"><?= h($error) ?></p><?php endif; ?>
<form class="box" method="post"><input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
<div class="field"><label>Full name<input name="applicant_name" maxlength="150" required value="<?= h(post('applicant_name')) ?>"></label></div>
<div class="field"><label>Mobile number<input name="mobile" type="tel" pattern="[6-9][0-9]{9}" maxlength="10" required value="<?= h(post('mobile')) ?>"></label></div>
<?php if($kind==='VENDOR'): ?>
<div class="field"><label>Business name<input name="business_name" maxlength="200" required value="<?= h(post('business_name')) ?>"></label></div>
<div class="field"><label>Seller type<select name="market" required><option value="">Select</option><?php foreach(['B2C','B2B','BOTH'] as $x): ?><option <?= post('market')===$x?'selected':'' ?>><?= h($x) ?></option><?php endforeach; ?></select></label></div>
<div class="field"><label>Shop category<select name="shop_category" required><option value="">Select</option><?php foreach(['Grocery','Food','Clothing','Electronics','Hardware','Medical','Home','Other'] as $x): ?><option <?= post('shop_category')===$x?'selected':'' ?>><?= h($x) ?></option><?php endforeach; ?></select></label></div>
<div class="field"><label>GSTIN (if applicable)<input name="gstin" maxlength="15" value="<?= h(post('gstin')) ?>"></label></div>
<div class="field"><label>PAN (B2B alternative)<input name="pan" maxlength="10" value="<?= h(post('pan')) ?>"></label></div>
<?php else: ?>
<div class="field"><label>Vehicle type<select name="vehicle_type" required><option value="">Select</option><?php foreach(['Bicycle','Bike','Scooter','Auto','Car','Van'] as $x): ?><option <?= post('vehicle_type')===$x?'selected':'' ?>><?= h($x) ?></option><?php endforeach; ?></select></label></div>
<div class="field"><label>Driving licence number<input name="dl_number" maxlength="25" required value="<?= h(post('dl_number')) ?>"></label></div>
<div class="field"><label>RC registration number<input name="rc_number" maxlength="30" required value="<?= h(post('rc_number')) ?>"></label></div>
<?php endif; ?>
<p class="help">Submitted identifiers are for private manual review. Do not upload sensitive documents to public uploads.</p>
<button class="btn btn-brand" type="submit">Submit for approval</button></form><?php endif; ?></div></section>
<?php include __DIR__.'/../inc/foot.php'; ?>
