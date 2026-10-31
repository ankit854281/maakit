<?php
require __DIR__.'/../../school/academics_lib.php';require __DIR__.'/../../school/scan_lib.php';
$year=(int)date('Y')-((int)date('n')<4?1:0);$session=$year.'-'.substr((string)($year+1),2);$date=$year.'-04-06';
$days=ac_template('foundation','A',[$date]);$out=ac_day_plan($days,$session);if($out[0]['hwMinutes']!==5||$out[0]['topic']!=='A')throw new RuntimeException('Fallback draft changed');
$days[0]['hwMinutes']=46;try{ac_day_plan($days,$session);throw new RuntimeException('Excess homework accepted');}catch(InvalidArgumentException $e){}
$schema=sc_schema('page');if(!isset($schema['properties']['days']))throw new RuntimeException('Page mode missing');
$clean=sc_clean_page(['days'=>[['topic'=>'Synthetic page','hwMinutes'=>99]]]);if($clean['days'][0]['hwMinutes']!==15)throw new RuntimeException('AI time not bounded');
echo "Academics planning: stage fallback, reviewable page schema, dates and homework limits passed\n";
