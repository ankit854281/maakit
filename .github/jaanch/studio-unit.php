<?php
require __DIR__.'/../../school/learning_lib.php';require __DIR__.'/../../school/studio_lib.php';
function su($ok,$why){if(!$ok)throw new RuntimeException($why);}
foreach(['2026-10-16 07:59:59'=>true,'2026-10-16 08:00:00'=>false,'2026-10-16 16:29:59'=>false,'2026-10-16 16:30:00'=>true,'2026-10-16 23:59:59'=>true] as $time=>$expected){$at=new DateTimeImmutable($time,new DateTimeZone('Asia/Kolkata'));$r=studio_quiet($at);su($r['quiet']===$expected,'Quiet-hours boundary wrong: '.$time);if($expected)su((new DateTimeImmutable('@'.intdiv($r['deliverAt'],1000)))->setTimezone(new DateTimeZone('Asia/Kolkata'))->format('H:i')==='08:00','Queued message not released at 08:00');}
su(studio_marks('23',25)===23.0,'Valid partial marks rejected');
foreach([-1,26,'NaN','1e9999'] as $bad){try{studio_marks($bad,25);throw new RuntimeException('Invalid marks accepted');}catch(InvalidArgumentException $e){}}
try{studio_date('2026-02-30');throw new RuntimeException('Invalid date accepted');}catch(InvalidArgumentException $e){}
$a=['page'=>0,'x'=>.5,'y'=>.4,'type'=>'error','text'=>'Check the minus sign'];su(count(studio_annotations([$a],1))===1,'Valid annotation rejected');
foreach([['page'=>1],['x'=>-1],['y'=>1.01],['type'=>'script']] as $change){try{studio_annotations([array_merge($a,$change)],1);throw new RuntimeException('Invalid annotation accepted');}catch(InvalidArgumentException $e){}}
su(studio_visible_submission(['role'=>'parent'],['sid'=>'own'],['own']),'Parent cannot read own copy');su(!studio_visible_submission(['role'=>'parent'],['sid'=>'other'],['own']),'Parent can read another child copy');
su(array_sum(array_column(STUDIO_BADGES,1))===14,'Badge points changed');echo "Studio unit checks passed\n";
