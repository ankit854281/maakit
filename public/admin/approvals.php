<?php
require_once __DIR__.'/../../inc/fn.php';
require_once __DIR__.'/../../controllers/partners.php';
header('Cache-Control: no-store');
need_role('admin');
if(!Maakit\Api\query($pdo,"SELECT id FROM users WHERE id=? AND role='admin' AND active=1",[(int)user()['id']])->fetch()){http_response_code(403);exit;}$page_title='Partner approvals — Maakit';$message='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 if(!csrf_ok()){http_response_code(403);$message=t('Reload the form and retry.','पन्ना फिर खोलकर कोशिश करें।');}
 else{
 $id=filter_var($_POST['id']??null,FILTER_VALIDATE_INT);
 $decision=is_string($_POST['decision']??null)?post('decision'):'';
 if(!$id||!in_array($decision,['APPROVED','REJECTED'],true)){$message=t('Choose a valid application and decision.','सही आवेदन और निर्णय चुनिए।');}
 else {
 try {
 $updated=Maakit\Api\review_partner($pdo,(int)$id,$decision,(int)user()['id']);
 $message=$updated?t('Application reviewed; approved accounts can sign in now.','आवेदन की जाँच पूरी; मंजूर खाते अब लॉगिन कर सकते हैं।'):t('Already reviewed or missing.','आवेदन की जाँच हो चुकी है या आवेदन नहीं मिला।');
 } catch(InvalidArgumentException $e) {$message=t('Check the required documents and linked login account. Older applications must be submitted again with login credentials.','जरूरी दस्तावेज़ और जुड़े लॉगिन खाते की जाँच करें। पुराने आवेदन लॉगिन जानकारी के साथ दोबारा भरने होंगे।');}
 catch(Throwable $e){error_log('Partner approval failed class='.get_class($e));http_response_code(503);$message=t('Could not save the decision. Retry shortly.','मंजूरी सेव नहीं हुई। थोड़ी देर बाद फिर कोशिश करें।');}

 }
 }
}
$rows=$pdo->query("SELECT * FROM mk_partner_applications WHERE status='PENDING' ORDER BY created_at ASC LIMIT 200")->fetchAll();
include __DIR__.'/../../inc/head.php';
?>
<section><div class="wrap"><h1><?= t('Vendor & Rider approvals','दुकानदार और राइडर आवेदन') ?></h1><p><?= t('Verify documents independently before approving. Approval activates the linked login and creates the vendor or rider profile. Service areas still need separate setup.','मंजूरी से पहले दस्तावेज़ देखें। मंजूरी मिलने पर लॉगिन और प्रोफ़ाइल खुलेंगे। सेवा क्षेत्र अलग से तय करने होंगे।') ?></p>
<?php if($message): ?><p role="status"><?= h($message) ?></p><?php endif; ?>
<div class="tablewrap"><table><thead><tr><th><?= t('Applicant','आवेदक') ?></th><th><?= t('Details','जानकारी') ?></th><th><?= t('Documents','दस्तावेज़') ?></th><th><?= t('Submitted','भेजा गया') ?></th><th><?= t('Decision','निर्णय') ?></th></tr></thead><tbody>
<?php foreach($rows as $a): ?><tr><td><?= h($a['kind']) ?><p><?= h($a['applicant_name']) ?></p><p><?= h($a['mobile']) ?></p></td>
<td><?php if($a['kind']==='VENDOR'): ?><?= h($a['business_name']) ?> · <?= h($a['market']) ?> · <?= h($a['shop_category']) ?><p>GSTIN: <?= h($a['gstin']?:'—') ?> | PAN: <?= h($a['pan']?:'—') ?></p><?php else: ?><?= h($a['vehicle_type']) ?><p>DL: <?= h($a['dl_number']) ?> | RC: <?= h($a['rc_number']) ?></p><?php endif; ?></td>
<td><?php $docs=Maakit\Api\query($pdo,'SELECT id,kind FROM mk_partner_documents WHERE application_id=?',[$a['id']])->fetchAll();foreach($docs as $doc): ?><a class="btn btn-line btn-sm" href="/public/admin/document.php?id=<?= (int)$doc['id'] ?>"><?= h($doc['kind']) ?></a> <?php endforeach; ?></td>
<td><?= h($a['created_at']) ?></td><td><form method="post"><input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="id" value="<?= (int)$a['id'] ?>"><button class="btn btn-brand btn-sm" name="decision" value="APPROVED"><?= t('Approve','मंजूर करें') ?></button> <button class="btn btn-line btn-sm" name="decision" value="REJECTED"><?= t('Reject','नामंजूर करें') ?></button></form></td></tr><?php endforeach; ?>
</tbody></table></div><?php if(!$rows): ?><p><?= t('No pending applications.','कोई आवेदन बाकी नहीं है।') ?></p><?php endif; ?></div></section>
<?php include __DIR__.'/../../inc/foot.php'; ?>
