<?php
require_once __DIR__.'/../inc/fn.php';
need_role('admin');
$page_title=t('Service areas — Maakit','सेवा क्षेत्र — Maakit');
$err='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
 if (!csrf_ok()) $err=t('Reload the page and try again.','पेज दोबारा खोलकर कोशिश कीजिए।');
 else {
  $id=(int)post('id'); $name=post('name'); $city=post('city'); $state=post('state'); $pin=post('pincode');
  $fee=post('base_fee'); $live=post('live')==='1' ? 1:0;
  $existing=$pdo->prepare('SELECT * FROM villages WHERE id=?'); $existing->execute([$id]); $old=$existing->fetch();
  if ($id && !$old) $err=t('Area not found.','इलाका नहीं मिला।');
  elseif ((!$id && (mb_strlen($name)<2 || mb_strlen($name)>60)) || mb_strlen($city)>80 || mb_strlen($state)>80) $err=t('Check the area, city and state names. Area names must be unique and at most 60 characters.','इलाका, शहर और राज्य सही भरिए। इलाके का अलग पहचान वाला नाम अधिकतम 60 अक्षर का रखें।');
  elseif ($pin!=='' && !preg_match('/^[1-9][0-9]{5}$/',$pin)) $err=t('PIN code must be six digits.','PIN code छह अंकों का भरिए।');
  elseif ($fee!=='' && (!ctype_digit($fee) || (int)$fee>10000)) $err=t('Delivery charge must be between 0 and 10000.','डिलीवरी चार्ज 0 से 10000 के बीच भरिए।');
  elseif ($live && ($city==='' || $state==='' || $pin==='')) $err=t('Fill city, state and PIN before activating this area.','इलाका चालू करने से पहले शहर, राज्य और PIN भरिए।');
  else {
   $shopids=array_values(array_unique(array_filter(array_map('intval',(array)($_POST['shops']??[])),fn($x)=>$x>0)));
   if ($shopids) {
    $marks=implode(',',array_fill(0,count($shopids),'?'));
    $check=$pdo->prepare("SELECT id FROM businesses WHERE status='approved' AND id IN ($marks)");$check->execute($shopids);
    if (count($check->fetchAll())!==count($shopids)) $err=t('Only approved shops can be linked.','केवल स्वीकृत दुकानें जोड़ सकते हैं।');
   }
   $driverids=array_values(array_unique(array_filter(array_map('intval',(array)($_POST['drivers']??[])),fn($x)=>$x>0)));
   if($driverids){
    $marks=implode(',',array_fill(0,count($driverids),'?'));
    $check=$pdo->prepare("SELECT id FROM users WHERE role IN ('delivery','rider') AND active=1 AND id IN ($marks)");$check->execute($driverids);
    if(count($check->fetchAll())!==count($driverids))$err=t('Choose active delivery staff only.','केवल चालू delivery staff चुनें।');
   }
   if(post('auto_dispatch')==='1'&&!$driverids)$err=t('Link delivery staff before enabling automatic assignment.','Automatic assignment चालू करने से पहले delivery staff जोड़ें।');
   if (!$err) {
    try {
     $pdo->beginTransaction();
     if (!$id) { $pdo->prepare('INSERT INTO villages(name,live) VALUES (?,?)')->execute([$name,$live]);$id=(int)$pdo->lastInsertId(); }
     else $pdo->prepare('UPDATE villages SET live=? WHERE id=?')->execute([$live,$id]);
     $pdo->prepare('INSERT INTO service_area_meta(village_id,city,state,pincode,delivery_on,booking_on,base_fee,first_free) VALUES (?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE city=VALUES(city),state=VALUES(state),pincode=VALUES(pincode),delivery_on=VALUES(delivery_on),booking_on=VALUES(booking_on),base_fee=VALUES(base_fee),first_free=VALUES(first_free)')
       ->execute([$id,$city,$state,$pin,post('delivery_on')==='1'?1:0,post('booking_on')==='1'?1:0,$fee===''?null:(int)$fee,post('first_free')==='1'?1:0]);
     $pdo->prepare('DELETE FROM service_area_shops WHERE village_id=?')->execute([$id]);
     $link=$pdo->prepare('INSERT INTO service_area_shops(village_id,business_id) VALUES (?,?)');
     foreach($shopids as $bid) $link->execute([$id,$bid]);
     $pdo->prepare('DELETE FROM service_area_drivers WHERE village_id=?')->execute([$id]);
     $driverlink=$pdo->prepare('INSERT INTO service_area_drivers(village_id,user_id) VALUES (?,?)');
     foreach($driverids as $driverid)$driverlink->execute([$id,$driverid]);
     $pdo->prepare('INSERT INTO service_area_dispatch(village_id,enabled) VALUES (?,?) ON DUPLICATE KEY UPDATE enabled=VALUES(enabled)')->execute([$id,post('auto_dispatch')==='1'?1:0]);
     $pdo->commit();flash(t('Coverage saved.','सेवा क्षेत्र सेव हो गया।'));redirect('/admin/coverage.php?id='.$id);
    } catch(PDOException $e) { if($pdo->inTransaction())$pdo->rollBack();error_log('Coverage save failed: '.$e->getMessage());$err=t('Could not save. Use a unique area name and try again.','सेव नहीं हुआ। इलाके का अलग पहचान वाला नाम रखकर दोबारा कोशिश कीजिए।'); }
   }
  }
 }
}
$areas=coverage_areas($pdo,false);$id=(int)get('id');$edit=null;
foreach($areas as $a)if((int)$a['id']===$id)$edit=$a;
$links=[];if($edit){$s=$pdo->prepare('SELECT business_id FROM service_area_shops WHERE village_id=?');$s->execute([$id]);$links=array_map('intval',$s->fetchAll(PDO::FETCH_COLUMN));}
$shops=$pdo->query("SELECT id,name,village FROM businesses WHERE status='approved' ORDER BY name")->fetchAll();
 $driverlinks=[];if($edit){$s=$pdo->prepare('SELECT user_id FROM service_area_drivers WHERE village_id=?');$s->execute([$id]);$driverlinks=array_map('intval',$s->fetchAll(PDO::FETCH_COLUMN));}
$drivers=$pdo->query("SELECT id,name FROM users WHERE role IN ('delivery','rider') AND active=1 ORDER BY name")->fetchAll();
$auto=false;if($edit){$s=$pdo->prepare('SELECT enabled FROM service_area_dispatch WHERE village_id=?');$s->execute([$id]);$auto=(bool)$s->fetchColumn();}
include __DIR__.'/../inc/panel.php';
?>
<section><div class="wrap" style="max-width:900px">
<h1><?=t('Service areas across India','भारत में सेवा क्षेत्र')?></h1>
<p class="lead"><?=t('Activate an area only after delivery staff, pickup shops and service availability are verified. A PIN identifies a locality; it does not promise coverage of the entire PIN.','डिलीवरी टीम, दुकानें और सेवाएँ तैयार होने की जाँच के बाद ही इलाका चालू करें। PIN पहचान के लिए है; पूरे PIN में सेवा का वादा नहीं है।')?></p>
<div class="chips"><a class="chip" href="/admin/coverage.php"><?=t('Add area','नया इलाका')?></a><?php foreach($areas as $a):?><a class="chip" href="?id=<?=(int)$a['id']?>"><?=h(coverage_label($a))?> · <?= $a['live']?t('Active','चालू'):t('Paused','बंद')?></a><?php endforeach;?></div>
<?php if($err):?><div class="err"><?=h($err)?></div><?php endif;?>
<form method="post" class="box">
<input type="hidden" name="csrf" value="<?=h(csrf())?>"><input type="hidden" name="id" value="<?=(int)($edit['id']??0)?>">
<h2><?= $edit?h($edit['name']):t('New area','नया इलाका')?></h2>
<?php if(!$edit):?><div class="field"><label><?=t('Unique area name: locality + city','अलग पहचान वाला इलाका: मोहल्ला + शहर')?></label><input name="name" required maxlength="60"></div><?php endif;?>
<?php foreach(['city'=>['City / district','शहर / जिला'],'state'=>['State','राज्य'],'pincode'=>['PIN code','PIN code'],'base_fee'=>['Normal local delivery fee ₹ (blank = legacy market rates)','स्थानीय डिलीवरी चार्ज ₹ (खाली = पुराने बाज़ार के रेट)']] as $k=>$label):?>
<div class="field"><label><?=h(t($label[0],$label[1]))?></label><input name="<?=h($k)?>" value="<?=h($_SERVER['REQUEST_METHOD']==='POST'?post($k):($edit[$k]??''))?>" <?=in_array($k,['pincode','base_fee'],true)?'inputmode="numeric"':'maxlength="80"'?>></div>
<?php endforeach;?>
<?php foreach(['live'=>['Area active','इलाका चालू'],'delivery_on'=>['Accept delivery requests','डिलीवरी अनुरोध लें'],'booking_on'=>['Accept booking requests','बुकिंग अनुरोध लें'],'first_free'=>['Waive base fee on first delivery','पहली डिलीवरी का मूल चार्ज माफ करें']] as $k=>$label):?>
<div class="field"><label><input type="checkbox" name="<?=h($k)?>" value="1" <?=($_SERVER['REQUEST_METHOD']==='POST'?post($k)==='1':(bool)($edit[$k]??0))?'checked':''?>> <?=h(t($label[0],$label[1]))?></label></div><?php endforeach;?>
<div class="field"><label><?=t('Approved shops serving this area (hold Ctrl to select multiple)','इस इलाके में सेवा देने वाली दुकानें (कई चुनने के लिए Ctrl दबाएँ)')?></label><select name="shops[]" multiple size="8"><?php foreach($shops as $shop):?><option value="<?=(int)$shop['id']?>" <?=in_array((int)$shop['id'],$links,true)?'selected':''?>><?=h($shop['name'].' · '.$shop['village'])?></option><?php endforeach;?></select></div>
<div class="field"><label><?=t('Delivery staff serving this area','इस इलाके के delivery staff')?></label><?php foreach($drivers as $driver):?><label><input type="checkbox" name="drivers[]" value="<?=(int)$driver['id']?>" <?=in_array((int)$driver['id'],$_SERVER['REQUEST_METHOD']==='POST'?($driverids??[]):$driverlinks,true)?'checked':''?>> <?=h($driver['name'])?></label><?php endforeach;?></div><p class="help"><?=t('Confirm shifts with these people. This roster does not prove current availability.','इन लोगों की shift पक्की करें। इस सूची से अभी उपलब्ध होने की पुष्टि नहीं होती।')?></p>
<div class="field"><label><input type="checkbox" name="auto_dispatch" value="1" <?=($_SERVER['REQUEST_METHOD']==='POST'?post('auto_dispatch')==='1':$auto)?'checked':''?>> <?=t('Automatically assign confirmed orders to an available area driver','Confirmed order अपने आप इलाके के उपलब्ध driver को दें')?></label></div><p class="help"><?=t('Off by default. Driver must mark availability for 15 minutes. One active order per automatic assignment; otherwise the order waits for the team.','यह शुरू में बंद है। Driver को 15 मिनट की उपलब्धता चुननी होगी। Automatic assignment में एक active order मिलेगा; बाकी order टीम के लिए रुकेगा।')?></p>
<button class="btn btn-brand"><?=t('Save coverage','सेवा क्षेत्र सेव करें')?></button>
</form></div></section>
<?php include __DIR__.'/../inc/foot.php';?>
