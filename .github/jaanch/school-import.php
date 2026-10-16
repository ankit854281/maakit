<?php
require __DIR__.'/../../school/student_import.php';
function si_check($ok,$msg){if(!$ok)throw new RuntimeException($msg);}
$input=['format'=>'snmishra-students-v1','session'=>'2026-27','students'=>[
 ['erpId'=>'901','adm'=>'DUPLICATE-ADM','name'=>'First child','cls'=>'1st','father'=>'Parent A','phone'=>'0000000000','password'=>'must not be copied','teacher'=>'must not be copied'],
 ['erpId'=>'902','adm'=>'DUPLICATE-ADM','name'=>'Second child','cls'=>'2nd','father'=>'Parent B']]];
$rows=sch_student_import_rows($input,'2026–27');
si_check(count($rows)===2&&$rows[0]['id']!==$rows[1]['id'],'Different source students merged');
si_check($rows[0]['adm']===$rows[1]['adm'],'Admission number was changed');
si_check(!isset($rows[0]['password'],$rows[0]['teacher']),'Unexpected fields copied');
$p=sch_student_import_plan($rows,[]);si_check(count($p['new'])===2&&count($p['duplicateAdmissions'])===1,'Duplicate admission preview failed');
$old=array_map(fn($s)=>['r'=>$s['id'],'d'=>json_encode($s),'deleted'=>0],$rows);
$p=sch_student_import_plan($rows,$old);si_check(count($p['new'])===0&&count($p['unchanged'])===2,'Import is not idempotent');
$old[0]['d']=json_encode(array_merge($rows[0],['name'=>'Changed by school']));
$p=sch_student_import_plan($rows,$old);si_check(count($p['conflicts'])===1,'Modified row was overwritten');
$p=sch_student_import_plan($rows,[['r'=>'manual-1','d'=>json_encode(['adm'=>'DUPLICATE-ADM']),'deleted'=>0]]);si_check(count($p['conflicts'])===1,'Manual admission collision ignored');
foreach(['session','duplicate','class'] as $case){$bad=$input;if($case==='session')$bad['session']='2025-26';if($case==='duplicate')$bad['students'][1]['erpId']='901';if($case==='class')$bad['students'][1]['cls']='unknown';try{sch_student_import_rows($bad,'2026–27');throw new RuntimeException('Invalid import accepted: '.$case);}catch(InvalidArgumentException $e){}}
echo "School student import checks passed\n";
