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
<button class="btn btn-brand"><?=t('Save coverage','सेवा क्षेत्र सेव करें')?></button>
</form></div></section>
<?php include __DIR__.'/../inc/foot.php';?>
