<?php
require_once __DIR__.'/../inc/fn.php';
$u=need_role(['admin','bpo']);
$id=(int)get('id',post('id'));
$st=$pdo->prepare('SELECT * FROM orders WHERE id=?');$st->execute([$id]);$o=$st->fetch();
if(!$o)redirect($u['role']==='admin'?'/admin/':'/bpo/');
$err='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 $carrier=post('carrier');$number=post('tracking_no');
 if(!csrf_ok())$err=t('Reload and try again.','पेज दोबारा खोलकर कोशिश करें।');
 elseif(mb_strlen($carrier)<2||mb_strlen($carrier)>80||!preg_match('/^[A-Za-z0-9][A-Za-z0-9 ._\/-]{2,99}$/',$number))$err=t('Enter the courier name and a valid tracking number (3–100 letters/numbers).','Courier का नाम और सही tracking number भरें (3–100 अक्षर/अंक)।');
 else{
  $save=$pdo->prepare("INSERT INTO courier_tracking(order_id,carrier,tracking_no,updated_by) SELECT id,?,?,? FROM orders WHERE id=? AND status<>'Cancel' ON DUPLICATE KEY UPDATE carrier=VALUES(carrier),tracking_no=VALUES(tracking_no),updated_by=VALUES(updated_by)");
  $save->execute([$carrier,$number,$u['id'],$id]);
  if($o['status']==='Cancel'||$save->rowCount()===0){$fresh=$pdo->prepare('SELECT status FROM orders WHERE id=?');$fresh->execute([$id]);if($fresh->fetchColumn()==='Cancel')$err=t('Cancelled orders cannot receive courier details.','Cancelled order में courier जानकारी नहीं जोड़ सकते।');}
  if(!$err){flash(t('Courier details saved. This does not book a shipment.','Courier जानकारी सेव हुई। इससे shipment की booking नहीं होती।'));redirect('/admin/shipment.php?id='.$id);}
 }
}
$st=$pdo->prepare('SELECT * FROM courier_tracking WHERE order_id=?');$st->execute([$id]);$shipment=$st->fetch()?:[];
$page_title=t('Courier details — Maakit','Courier जानकारी — Maakit');include __DIR__.'/../inc/panel.php';
?>
<section><div class="wrap" style="max-width:700px"><h1><?=t('Courier details','Courier जानकारी')?></h1><b><?=h($o['order_no'])?></b>
<p class="lead"><?=t('Enter details only after booking with the courier separately. This form does not create a courier booking, label, charge or automatic tracking update. Goods payment still goes directly to the shop.','Courier से अलग booking करने के बाद ही जानकारी भरें। इस form से booking, label, शुल्क या automatic tracking update नहीं बनता। सामान का पैसा सीधे दुकान को ही जाता है।')?></p>
<?php if($err):?><div class="err"><?=h($err)?></div><?php endif;?>
<form method="post" class="box"><input type="hidden" name="csrf" value="<?=h(csrf())?>"><input type="hidden" name="id" value="<?=$id?>">
<div class="field"><label><?=t('Courier name','Courier का नाम')?></label><input name="carrier" minlength="2" maxlength="80" required value="<?=h(post('carrier',$shipment['carrier']??''))?>"></div>
<div class="field"><label><?=t('Tracking / AWB number','Tracking / AWB नंबर')?></label><input name="tracking_no" minlength="3" maxlength="100" required value="<?=h(post('tracking_no',$shipment['tracking_no']??''))?>"></div>
<button class="btn btn-brand"><?=t('Save courier details','Courier जानकारी सेव करें')?></button></form>
</div></section><?php include __DIR__.'/../inc/foot.php'; ?>
