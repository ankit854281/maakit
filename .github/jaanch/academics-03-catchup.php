<?php
require __DIR__.'/../../school/academics_lib.php';
$date=date('Y-m-d');$year=(int)date('Y')-((int)date('n')<4?1:0);$session=$year.'-'.substr((string)($year+1),2);
$kids=[['id'=>'synthetic-absent','cls'=>'UKG']];$att=[$date.'|UKG'=>['absent'=>['synthetic-absent'],'st'=>['synthetic-absent'=>'L']]];
$diary=[$date.'|teacher|0'=>['cls'=>'UKG','subj'=>'English','topic'=>'Letter A','hw'=>'Repeat A']];
$rows=ac_catchup($att,$diary,[],$kids,$session);if(count($rows)!==1||$rows[0]['done'])throw new RuntimeException('Absent/leave work not pending');
$id=$rows[0]['id'];if(!ac_catchup($att,$diary,[$id=>['date'=>$date]],$kids,$session)[0]['done'])throw new RuntimeException('Catch-up not resolved');
$diary[$date.'|teacher|0']['topic']='Corrected topic';if(ac_catchup($att,$diary,[$id=>['date'=>$date]],$kids,$session)[0]['done'])throw new RuntimeException('Changed teaching incorrectly resolved');
if(ac_catchup($att,$diary,[],[['id'=>'other','cls'=>'UKG']],$session)!==[])throw new RuntimeException('Another child leaked');
echo "Academics catch-up: leave/absence, corrected work, session and child isolation passed\n";
