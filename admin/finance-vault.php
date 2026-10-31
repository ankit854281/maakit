<?php
require_once __DIR__ . '/../inc/fn.php';
$u = need_role('admin');
$page_title = 'Finance Vault — MAAKIT';
$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
 if (!csrf_ok()) { $err = 'Invalid CSRF token'; }
 else {
  $date = post('expense_date'); $vendor=trim(post('vendor')); $desc=trim(post('description'));
  $category=post('category'); $allocation=post('allocation'); $status=post('payment_status');
  $amount=trim(post('amount')); $ref=trim(post('source_reference'));
  if (!preg_match('/^\\d{4}-\\d{2}-\\d{2}$/',$date) || !checkdate((int)substr($date,5,2),(int)substr($date,8,2),(int)substr($date,0,4)) || $date>date('Y-m-d')
   || $vendor==='' || mb_strlen($vendor)>160 || $desc==='' || mb_strlen($desc)>500
   || !in_array($category,['domain','hosting','ai_subscription','development','other'],true)
   || !in_array($allocation,['maakit','smart_sarathi','shared','unallocated'],true)
   || $status !== 'receipt_found'
   || !preg_match('/^\\d{1,10}(?:\\.\\d{1,2})?$/',$amount) || (float)$amount<=0 || mb_strlen($ref)>180) $err='Invalid expense details';
  else {
   $paise=(int)round((float)$amount*100);
   try {
    $pdo->beginTransaction();
    $voucher='MK-FY'.(date('y',strtotime($date))+(date('n',strtotime($date))>=4?1:0)).'-'.bin2hex(random_bytes(6));
    $pdo->prepare('INSERT INTO finance_entries(voucher_no,expense_date,vendor,description,category,allocation,amount_paise,payment_status,source_reference,created_by) VALUES (?,?,?,?,?,?,?,?,?,?)')
      ->execute([$voucher,$date,$vendor,$desc,$category,$allocation,$paise,$status,$ref,$u['id']]);
    $pdo->commit();
    flash('Expense saved: '.$voucher);
    redirect('/admin/finance-vault.php');
   } catch(Throwable $e) {if($pdo->inTransaction())$pdo->rollBack(); $err='Unable to save entry. Check database migration.';}
  }
 }
}
$rows=[];$sum=0;
try {
 $rows=$pdo->query('SELECT voucher_no,expense_date,vendor,description,category,allocation,amount_paise,payment_status,source_reference FROM finance_entries ORDER BY expense_date DESC,id DESC LIMIT 500')->fetchAll();
 $sum=(int)$pdo->query("SELECT COALESCE(SUM(amount_paise),0) FROM finance_entries WHERE payment_status != 'refunded'")->fetchColumn();
} catch(Throwable $e) {$err='Finance database tables are not installed yet.';}
include __DIR__ . '/../inc/panel.php';
?>
<section><div class="wrap">
<h2>MAAKIT Finance Vault (private admin)</h2>
<p>Documented expenses ₹<?= number_format($sum/100,2) ?>. Receipt found is NOT proof of bank settlement. Founder time is excluded from this total.</p>
<?php if($err): ?><p class="err"><?= h($err) ?></p><?php endif; ?>
<form method="post" class="box">
<input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
<label>Date <input type="date" name="expense_date" max="<?= date('Y-m-d') ?>" required></label>
<label>Vendor <input name="vendor" maxlength="160" required></label>
<label>Description <input name="description" maxlength="500" required></label>
<label>Amount (₹) <input name="amount" inputmode="decimal" pattern="[0-9]+(\\.[0-9]{1,2})?" required></label>
<label>Category <select name="category"><option value="domain">Domain</option><option value="hosting">Hosting</option><option value="ai_subscription">AI subscription</option><option value="development">Development</option><option value="other">Other</option></select></label>
<label>Allocation <select name="allocation"><option value="unallocated">Pending allocation</option><option value="maakit">MAAKIT</option><option value="smart_sarathi">Smart Sarathi</option><option value="shared">Shared</option></select></label>
<label>Verification <select name="payment_status"><option value="receipt_found">Receipt found</option></select></label>
<label>Receipt reference (NO public URLs or passwords) <input name="source_reference" maxlength="180"></label>
<button type="submit">Save voucher</button>
</form>
<div class="box" style="overflow:auto"><table><thead><tr><th>Voucher</th><th>Date</th><th>Vendor</th><th>Description</th><th>Amount</th><th>Allocation</th><th>Status</th></tr></thead><tbody>
<?php foreach($rows as $r): ?><tr><td><?= h($r['voucher_no']) ?></td><td><?= h($r['expense_date']) ?></td><td><?= h($r['vendor']) ?></td><td><?= h($r['description']) ?></td><td>₹<?= number_format($r['amount_paise']/100,2) ?></td><td><?= h($r['allocation']) ?></td><td><?= h($r['payment_status']) ?></td></tr><?php endforeach; ?>
</tbody></table></div></div></section>
