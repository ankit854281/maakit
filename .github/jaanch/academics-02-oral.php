<?php
require __DIR__.'/../../school/academics_lib.php';
$kids=[['id'=>'synthetic-oral','cls'=>'UKG']];$units=ac_unit_list([['id'=>'letter-A','name'=>'A','type'=>'letter'],['id'=>'rhyme-1','name'=>'Teacher selected rhyme','type'=>'rhyme']]);
if(ac_stage('UKG')!=='foundation'||ac_stage('5th')!=='primary'||ac_stage('6th')!=='secondary')throw new RuntimeException('Wrong stage');
$cells=ac_oral_cells(['synthetic-oral'=>['letter-A'=>'done']],$kids,$units);
if(isset($cells['synthetic-oral']['rhyme-1']))throw new RuntimeException('Untouched oral item marked done');
foreach([['foreign'=>['letter-A'=>'done']],['synthetic-oral'=>['unknown'=>'done']],['synthetic-oral'=>['letter-A'=>'mastered']]] as $bad){try{ac_oral_cells($bad,$kids,$units);throw new RuntimeException('Invalid oral check accepted');}catch(InvalidArgumentException $e){}}
echo "Academics oral: stage mapping, untouched revision and child/unit validation passed\n";
