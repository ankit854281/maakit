<?php
// sab.sh discovers this wrapper; the companion PHP file is a stdin fixture driver only.
$command='node '.escapeshellarg(__DIR__.'/academics-arrangement.cjs').' '.escapeshellarg(PHP_BINARY);
passthru($command,$status);
if($status!==0)throw new RuntimeException('Academics arrangement / legacy-plan regression failed');
require __DIR__.'/../../school/academics_schedule.php';
$data=['classes'=>[['c'=>'UKG']],'alloc'=>[['c'=>'UKG','s'=>'English','t'=>'RI']],'teachers'=>['RI'=>[]],'grid'=>['UKG'=>array_fill(0,6,array_fill(0,7,0))]];
if(!ac_timetable_ready($data))throw new RuntimeException('Complete timetable rejected');
unset($data['grid']['UKG'][5][6]);if(ac_timetable_ready($data))throw new RuntimeException('Partial timetable accepted');
$data['grid']['UKG'][5][6]=0;$data['teachers']['RI']['bells']=[];if(ac_timetable_ready($data))throw new RuntimeException('Restricted teacher accepted');
echo "Timetable readiness: complete, missing slot and restricted teacher checked\n";
