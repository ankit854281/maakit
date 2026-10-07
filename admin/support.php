<?php
require_once __DIR__.'/../inc/fn.php';$u=need_role('admin');$page_title=t('Support requests — Maakit','सहायता अनुरोध — Maakit');$err='';
$returnStages=['not_required'=>['No return pickup','वापसी pickup नहीं'],'requested'=>['Pickup to be arranged','Pickup तय करना बाकी'],'scheduled'=>['Pickup scheduled','Pickup तय हुआ'],'collected'=>['Item collected','सामान ले लिया गया'],'received'=>['Returned to shop','दुकान को वापस मिला']];
$input=function($key,$default=''){return is_string($_POST[$key]??null)?trim($_POST[$key]):$default;};
if($_SERVER['REQUEST_METHOD']==='POST'){
 $status=$input('status');$reply=$input('reply');$decision=$input('decision','pending');
 $raw=$input('refund_amount');$reference=$input('refund_reference');
 $amount=$raw===''?null:(ctype_digit($raw)&&strlen($raw)<=7&&(int)$raw>0&&(int)$raw<=1000000?(int)$raw:false);
 if(!csrf_ok())$err=t('Reload and try again.','पेज दोबारा खोलें।');
 elseif(!in_array($status,['new','reviewing','resolved','closed'],true)||mb_strlen($reply)>1000||!in_array($decision,['pending','approved','rejected'],true))$err=t('Check the status, decision and reply.','स्थिति, निर्णय और जवाब जाँचें।');
 elseif($decision!=='pending'&&mb_strlen(trim($reply))<10)$err=t('Explain the decision in at least 10 characters.','निर्णय का कारण कम से कम 10 अक्षरों में लिखें।');
 elseif($amount===false||mb_strlen($reference)>100||($amount!==null&&($decision!=='approved'||mb_strlen($reference)<5))||($amount===null&&$reference!==''))$err=t('Record a positive refund only after approval, with a shop refund receipt/reference. Leave both refund fields blank if no refund was paid.','स्वीकृति के बाद ही सकारात्मक refund रकम और दुकान की refund रसीद/reference भरें। पैसा वापस नहीं दिया गया तो दोनों refund fields खाली छोड़ें।');
 else{
  $pdo->beginTransaction();
  try{
   $find=$pdo->prepare('SELECT id,kind FROM support_tickets WHERE id=? FOR UPDATE');$find->execute([(int)$input('id')]);$ticket=$find->fetch();
   $old=$pdo->prepare('SELECT refund_amount,refund_reference,return_stage,return_date FROM support_resolution WHERE ticket_id=?');$old->execute([(int)$input('id')]);$prior=$old->fetch();
   if(!$ticket)$err=t('Request not found.','अनुरोध नहीं मिला।');
   elseif($prior&&$prior['refund_amount']!==null&&($decision!=='approved'||$amount!==(int)$prior['refund_amount']||$reference!==$prior['refund_reference']))$err=t('A recorded paid refund cannot be overwritten. Contact the team to review a correction.','दर्ज किए गए paid refund को बदल नहीं सकते। सुधार की जाँच के लिए टीम से संपर्क करें।');
   $stage=$input('return_stage',$prior['return_stage']??'not_required');$date=$input('return_date',$prior['return_date']??'');
   $parsed=$date===''?false:DateTime::createFromFormat('!Y-m-d',$date);
   if(!$err&&(!isset($returnStages[$stage])||($date!==''&&(!$parsed||$parsed->format('Y-m-d')!==$date))||($stage==='scheduled'&&$date==='')||($stage!=='not_required'&&($decision!=='approved'||$ticket['kind']!=='return'))))$err=t('Approve a return request before recording pickup. Choose a valid date for scheduled pickup.','वापसी अनुरोध स्वीकृत करने के बाद pickup दर्ज करें। तय pickup की सही तारीख चुनें।');
   $ranks=array_flip(array_keys($returnStages));
   if(!$err&&$prior&&$ranks[$stage]<$ranks[$prior['return_stage']])$err=t('Return progress cannot move backwards. Contact the team for a correction.','वापसी की प्रगति पीछे नहीं कर सकते। सुधार के लिए टीम से संपर्क करें।');
   if($stage==='not_required')$date='';
   if($err)$pdo->rollBack();
   else{
    $pdo->prepare('UPDATE support_tickets SET status=?,reply=? WHERE id=?')->execute([$status,$reply,(int)$input('id')]);
    $pdo->prepare('INSERT INTO support_resolution(ticket_id,decision,refund_amount,refund_reference,updated_by,return_stage,return_date) VALUES (?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE decision=VALUES(decision),refund_amount=VALUES(refund_amount),refund_reference=VALUES(refund_reference),updated_by=VALUES(updated_by),return_stage=VALUES(return_stage),return_date=VALUES(return_date)')->execute([(int)$input('id'),$decision,$amount,$reference,$u['id'],$stage,$date===''?null:$date]);
    $pdo->commit();flash(t('Request updated. No money was transferred.','अनुरोध अपडेट हुआ। कोई पैसा transfer नहीं हुआ।'));redirect('/admin/support.php');
   }
  }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
 }

}
$rows=$pdo->query('SELECT t.*,r.decision,r.refund_amount,r.refund_reference,r.return_stage,r.return_date,c.name,c.mobile FROM support_tickets t LEFT JOIN support_resolution r ON r.ticket_id=t.id JOIN customers c ON c.id=t.customer_id ORDER BY FIELD(t.status,"new","reviewing","resolved","closed"),t.id DESC LIMIT 100')->fetchAll();include __DIR__.'/../inc/panel.php';
?>
<section><div class="wrap"><h1><?=t('Support requests','सहायता अनुरोध')?></h1><p class="lead"><?=t('Review the order with the shop/provider before replying. This page updates the request only; it does not move money or cancel fulfilment.','जवाब से पहले दुकान या सेवा देने वाले के साथ ऑर्डर जाँचें। यह पेज केवल अनुरोध बदलता है; भुगतान या ऑर्डर को अपने आप नहीं बदलता।')?></p><?php if($err):?><div class="err"><?=h($err)?></div><?php endif;?><?php if(!$rows):?><div class="box"><?=t('No requests yet.','अभी कोई अनुरोध नहीं।')?></div><?php endif;?>
<?php foreach($rows as $r):?><form method="post" class="box"><input type="hidden" name="csrf" value="<?=h(csrf())?>"><input type="hidden" name="id" value="<?=(int)$r['id']?>"><h2>#<?=(int)$r['id']?> · <?=h($r['reference_no'])?></h2><p><?=h($r['name'])?> · <a href="tel:<?=h($r['mobile'])?>"><?=h($r['mobile'])?></a> · <?=h($r['kind'])?></p><p><?=nl2br(h($r['message']))?></p><div class="field"><label><?=t('Status','स्थिति')?></label><select name="status"><?php foreach(['new'=>['Received','मिल गया'],'reviewing'=>['Reviewing','जाँच में'],'resolved'=>['Resolved','समाधान हुआ'],'closed'=>['Closed','बंद']] as $k=>$label):?><option value="<?=h($k)?>" <?=$r['status']===$k?'selected':''?>><?=h(t($label[0],$label[1]))?></option><?php endforeach;?></select></div><div class="field"><label><?=t('Reply visible to customer','ग्राहक को दिखने वाला जवाब')?></label><textarea name="reply" maxlength="1000"><?=h($r['reply'])?></textarea></div><div class="field"><label><?=t('Decision','निर्णय')?></label><select name="decision"><?php foreach(['pending'=>['Not decided','निर्णय बाकी'],'approved'=>['Approved','स्वीकृत'],'rejected'=>['Rejected','अस्वीकृत']] as $key=>$label):?><option value="<?=h($key)?>" <?=($r['decision']??'pending')===$key?'selected':''?>><?=h(t($label[0],$label[1]))?></option><?php endforeach;?></select></div>
<?php if($r['kind']==='return'):?><div class="field"><label><?=t('Return progress','वापसी की प्रगति')?></label><select name="return_stage"><?php foreach($returnStages as $key=>$label):?><option value="<?=h($key)?>" <?=($r['return_stage']??'not_required')===$key?'selected':''?>><?=h(t($label[0],$label[1]))?></option><?php endforeach;?></select></div><div class="field"><label><?=t('Agreed pickup date','तय pickup तारीख')?></label><input type="date" name="return_date" value="<?=h($r['return_date']??'')?>"></div><p class="help"><?=t('Record actual arrangements with the customer and shop. Saving does not book a driver.','ग्राहक और दुकान से तय हुई जानकारी दर्ज करें। सेव करने से driver बुक नहीं होता।')?></p><?php endif;?>
<div class="field"><label><?=t('Refund already paid directly by the shop (₹)','दुकान ने सीधे दिया हुआ refund (₹)')?></label><input type="number" name="refund_amount" min="1" max="1000000" value="<?=h($r['refund_amount']??'')?>"></div>
<div class="field"><label><?=t('Shop refund receipt / reference (no bank secrets)','दुकान की refund रसीद / reference (बैंक के गुप्त विवरण न भरें)')?></label><input name="refund_reference" maxlength="100" value="<?=h($r['refund_reference']??'')?>"></div><p class="help"><?=t('These fields record a refund already paid by the shop. Approval alone does not refund money or complete the return.','ये fields दुकान द्वारा पहले से दिए refund का रिकॉर्ड हैं। केवल स्वीकृति से पैसा वापस या return पूरा नहीं होता।')?></p><button class="btn btn-brand"><?=t('Save reply','जवाब सेव करें')?></button></form><?php endforeach;?></div></section><?php include __DIR__.'/../inc/foot.php';?>
