<?php
require_once __DIR__ . '/../inc/fn.php';
$u=need_role('admin');
$page_title='Founder Work Ledger — MAAKIT';
$err='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 if(!csrf_ok()) $err='Invalid CSRF token';
 else{
  $day=post('work_date');$desc=trim(post('description'));$hours=trim(post('hours'));$rate=trim(post('rate'));$evidence=trim(post('evidence_reference'));
  if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$day) || !checkdate((int)substr($day,5,2),(int)substr($day,8,2),(int)substr($day,0,4)) || $day>date('Y-m-d')
   || $desc==='' || mb_strlen($desc)>500 || !preg_match('/^\d{1,2}(?:\.\d{1,2})?$/',$hours) || (float)$hours<=0 || (float)$hours>24
   || !preg_match('/^\d{1,6}(?:\.\d{1,2})?$/',$rate) || (float)$rate<=0 || mb_strlen($evidence)>255) $err='Invalid work details';
  else{
   try{
    $pdo->prepare('INSERT INTO finance_founder_work(work_date,description,hours,estimated_rate_paise,evidence_reference,created_by) VALUES (?,?,?,?,?,?)')
      ->execute([$day,$desc,$hours,(int)round((float)$rate*100),$evidence,$u['id']]);
    flash('Founder work recorded as estimated contribution, not cash expense.');
    redirect('/admin/founder-work.php');
   }catch(Throwable $e){$err='Unable to save. Apply migration first.';}
  }
 }
}
$rows=[];$total=0;
try{$rows=$pdo->query('SELECT work_date,description,hours,estimated_rate_paise,evidence_reference FROM finance_founder_work ORDER BY work_date DESC,id DESC LIMIT 300')->fetchAll();
$total=(float)$pdo->query('SELECT COALESCE(SUM(hours*estimated_rate_paise),0) FROM finance_founder_work')->fetchColumn()/100;
}catch(Throwable $e){$err='Founder ledger table missing.';}
include __DIR__.'/../inc/panel.php';
?>
<section><div class="wrap"><h2>Founder Time & Work Ledger</h2>
<p>Estimated work value: ₹<?= number_format($total,2) ?>. Not a cash payment, salary, tax deduction, or booked capital. Avoid counting the same work twice.</p>
<?php if($err): ?><p class="err"><?= h($err) ?></p><?php endif; ?>
<form method="post" class="box"><input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
<label>Work date <input type="date" name="work_date" max="<?= date('Y-m-d') ?>" required></label>
<label>Work description <input name="description" maxlength="500" required></label>
<label>Hours <input name="hours" type="number" min="0.01" max="24" step="0.01" required></label>
<label>Estimated hourly replacement rate (₹) <input name="rate" type="number" min="0.01" max="999999.99" step="0.01" required></label>
<label>Evidence reference (commit/task ID) <input name="evidence_reference" maxlength="255"></label>
<button type="submit">Record work</button></form>
<div class="box" style="overflow:auto"><table><thead><tr><th>Date</th><th>Work</th><th>Hours</th><th>Rate</th><th>Value</th><th>Evidence</th></tr></thead><tbody>
<?php foreach($rows as $r): ?><tr><td><?= h($r['work_date']) ?></td><td><?= h($r['description']) ?></td><td><?= h($r['hours']) ?></td><td>₹<?= number_format($r['estimated_rate_paise']/100,2) ?></td><td>₹<?= number_format($r['hours']*$r['estimated_rate_paise']/100,2) ?></td><td><?= h($r['evidence_reference']) ?></td></tr><?php endforeach; ?>
</tbody></table></div></div></section>
