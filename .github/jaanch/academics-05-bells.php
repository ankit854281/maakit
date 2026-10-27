<?php
require __DIR__.'/../../school/academics_lib.php';require __DIR__.'/../../school/academics_schedule.php';
$date='2026-10-26';$data=['grid'=>['1st'=>[[0,0],[],[],[],[],[]]],'alloc'=>[['c'=>'1st','s'=>'English','t'=>'absent']],'teachers'=>['absent'=>[],'sub'=>[]],'staff'=>[$date=>['absent'=>['absent'],'from'=>['absent'=>1],'subs'=>['absent|1'=>'sub']]],'leave'=>[],'calendar'=>[],'exams'=>[]];
$bells=ac_bells($date,$data);if(count($bells)!==2||$bells[0]['tid']!=='absent'||$bells[1]['tid']!=='sub'||!$bells[1]['arr'])throw new RuntimeException('Half day / substitute mismatch');
$p=['status'=>'Approved','week'=>ac_week($date),'tid'=>'absent','cls'=>'1st','subj'=>'English','days'=>[['date'=>$date,'topic'=>'Synthetic approved topic']]];
if(ac_bell_plan($bells[1],$date,[$p])['topic']!=='Synthetic approved topic')throw new RuntimeException('Substitute cannot see absent teacher plan');$p['status']='Submitted';if(ac_bell_plan($bells[1],$date,[$p])!==null)throw new RuntimeException('Unapproved plan exposed as approved');
$data['calendar']=[['type'=>'Holiday','date'=>$date]];if(ac_bells($date,$data)!==[])throw new RuntimeException('Holiday bells expected');
echo "Academics bells: half-day, substitute topic, approval and holiday rules passed\n";
