<?php
// Finance read-only CSV export. Admin authentication required.
require_once __DIR__ . '/../inc/fn.php';
need_role('admin');
$from = get('from', date('Y-04-01'));
$to = get('to', date('Y-m-d'));
function valid_finance_date($s) {
 return preg_match('/^\d{4}-\d{2}-\d{2}$/', $s) &&
 checkdate((int)substr($s,5,2),(int)substr($s,8,2),(int)substr($s,0,4));
}
if (!valid_finance_date($from) || !valid_finance_date($to) || $from>$to) {
 http_response_code(400); exit('Invalid date range');
}
$stmt=$pdo->prepare('SELECT voucher_no,expense_date,vendor,description,category,allocation,amount_paise,payment_status,source_reference FROM finance_entries WHERE expense_date BETWEEN ? AND ? ORDER BY expense_date,id');
$stmt->execute([$from,$to]);
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="maakit-finance-'.$from.'-'.$to.'.csv"');
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
$out=fopen('php://output','w');
fputcsv($out,['Voucher','Date','Vendor','Description','Category','Allocation','Amount INR','Status','Receipt Reference']);
function safe_finance_csv($v) {
 $v=(string)$v;
 // Avoid formula execution in spreadsheet programs.
 if (preg_match('/^[\s\x00-\x1F]*[=+@\-]/u',$v)) return "'".$v;
 return $v;
}
while($r=$stmt->fetch(PDO::FETCH_ASSOC)) {
 fputcsv($out,[
  safe_finance_csv($r['voucher_no']),$r['expense_date'],
  safe_finance_csv($r['vendor']),safe_finance_csv($r['description']),
  $r['category'],$r['allocation'],
  number_format($r['amount_paise']/100,2,'.',''),$r['payment_status'],
  safe_finance_csv($r['source_reference'])
 ]);
}
fclose($out);
