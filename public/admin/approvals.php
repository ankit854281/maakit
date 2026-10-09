<?php
require_once __DIR__.'/../../inc/fn.php';
need_role('admin');$page_title='Partner approvals — Maakit';$message='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 if(!csrf_ok()){http_response_code(403);$message='Invalid CSRF token.';}
 else{
 $id=filter_var($_POST['id']??null,FILTER_VALIDATE_INT);
 $decision=post('decision');
 if(!$id||!in_array($decision,['APPROVED','REJECTED'],true)){$message='Invalid request.';}
 else {
 $st=$pdo->prepare("UPDATE mk_partner_applications SET status=?,reviewed_by=?,reviewed_at=NOW() WHERE id=? AND status='PENDING'");
 $st->execute([$decision,(int)user()['id'],$id]);$message=$st->rowCount()?'Application updated.':'Already reviewed or missing.';
 }
 }
}
$rows=$pdo->query("SELECT * FROM mk_partner_applications WHERE status='PENDING' ORDER BY created_at ASC LIMIT 200")->fetchAll();
include __DIR__.'/../../inc/head.php';
?>
<section><div class="wrap"><h1>Vendor & Rider approvals</h1><p>Verify documents independently before approving. Approval here only updates application status; it does not create login credentials or dispatch eligibility.</p>
<?php if($message): ?><p role="status"><?= h($message) ?></p><?php endif; ?>
<div class="tablewrap"><table><thead><tr><th>Applicant</th><th>Details</th><th>Submitted</th><th>Decision</th></tr></thead><tbody>
<?php foreach($rows as $a): ?><tr><td><?= h($a['kind']) ?><p><?= h($a['applicant_name']) ?></p><p><?= h($a['mobile']) ?></p></td>
<td><?php if($a['kind']==='VENDOR'): ?><?= h($a['business_name']) ?> · <?= h($a['market']) ?> · <?= h($a['shop_category']) ?><p>GSTIN: <?= h($a['gstin']?:'—') ?> | PAN: <?= h($a['pan']?:'—') ?></p><?php else: ?><?= h($a['vehicle_type']) ?><p>DL: <?= h($a['dl_number']) ?> | RC: <?= h($a['rc_number']) ?></p><?php endif; ?></td>
<td><?= h($a['created_at']) ?></td><td><form method="post"><input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="id" value="<?= (int)$a['id'] ?>"><button class="btn btn-brand btn-sm" name="decision" value="APPROVED">Approve</button> <button class="btn btn-line btn-sm" name="decision" value="REJECTED">Reject</button></form></td></tr><?php endforeach; ?>
</tbody></table></div><?php if(!$rows): ?><p>No pending applications.</p><?php endif; ?></div></section>
<?php include __DIR__.'/../../inc/foot.php'; ?>
