<?php
require __DIR__.'/../../school/academics_lib.php';
function ac_check($ok,$why){if(!$ok)throw new RuntimeException($why);}
$status=['hw'=>'not done','copy'=>'not checked','oral'=>'needs repeat','book'=>'not brought'];
$kids=[['id'=>'synthetic-1','cls'=>'1st'],['id'=>'synthetic-2','cls'=>'1st']];
$rows=['2026-10-27|1st|English'=>['session'=>'test','date'=>'2026-10-27','cls'=>'1st','subj'=>'English','task'=>'Synthetic task','students'=>['synthetic-1'=>$status,'synthetic-2'=>$status]]];
ac_check(count(ac_projection($rows,[$kids[0]],'test'))===1,'Another child leaked to parent');
ac_check(ac_projection($rows,$kids,'other')===[],'Another session leaked');
$p=ac_pending($rows,[],[$kids[0]],'test');ac_check(count($p)===1,'Pending homework lost');
$key=hash('sha256','test|2026-10-27|1st|English|synthetic-1');$resolution=[$key=>['date'=>'2026-10-28','source'=>ac_work_source($p[0])]];ac_check(ac_pending($rows,$resolution,[$kids[0]],'test')===[],'Resolved homework still pending');
$changed=$rows;$changed['2026-10-27|1st|English']['task']='Corrected task';ac_check(count(ac_pending($changed,$resolution,[$kids[0]],'test'))===1,'Changed task incorrectly stayed resolved');
try{ac_track_students(['synthetic-1'=>$status],$kids,['synthetic-1']);throw new RuntimeException('Absent child accepted');}catch(InvalidArgumentException $e){}
try{ac_track_students(['foreign-child'=>$status],$kids,[]);throw new RuntimeException('Foreign child accepted');}catch(InvalidArgumentException $e){}
ac_check(ac_track_students(['synthetic-1'=>$status],$kids,[])['synthetic-1']===$status,'Status changed');
echo "Academics tracking: child/session isolation, absent exclusion and explicit resolution passed\n";
