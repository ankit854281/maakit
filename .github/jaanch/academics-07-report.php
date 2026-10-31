<?php
require __DIR__.'/../../school/academics_lib.php';require __DIR__.'/../../school/academics_schedule.php';require __DIR__.'/../../school/academics_report.php';
$date='2026-04-06';$data=['grid'=>['1st'=>[[0],[],[],[],[],[]]],'alloc'=>[['t'=>'synthetic-report','c'=>'1st','s'=>'Maths']],'teachers'=>['synthetic-report'=>[]],'staff'=>[],'leave'=>[],'calendar'=>[],'exams'=>[]];$teachers=['synthetic-report'=>['name'=>'Synthetic Teacher']];
$r=ac_report($date,$data,$teachers,[],[],[],[],[],[],[],[],[],[],'2026-27');if($r[0]['dueBells']!==1||$r[0]['diaries']!==0||!count($r[0]['pending']))throw new RuntimeException('Missing diary hidden');
$diary=[$date.'|synthetic-report|0'=>['cls'=>'1st','subj'=>'Maths','topic'=>'Synthetic topic','hw'=>'']];$r=ac_report($date,$data,$teachers,[],$diary,[],[],[$date.'|1st'=>['absent'=>[]]],[],[],[],[],[],'2026-27');if($r[0]['diaries']!==1||$r[0]['homeworkBells']!==0)throw new RuntimeException('Diary report wrong');
$text=ac_report_text($r,$date);if(!str_contains($text,'Synthetic Teacher')||str_contains($text,'parent phone'))throw new RuntimeException('Summary wrong');
echo "Academics report: due bells, missing diary, actual homework and share summary passed\n";
