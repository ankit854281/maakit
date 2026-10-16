<?php
require __DIR__.'/../../school/learning_lib.php';
function lc($ok,$msg){if(!$ok)throw new RuntimeException($msg);}
$questions=learn_questions([['prompt'=>'7 + 5','answer'=>'12','mode'=>'numeric'],['prompt'=>'Explain a sentence','answer'=>'Teacher guide','mode'=>'manual']]);
$r=learn_grade($questions,['12','A complete sentence']);lc($r['correct']===1&&$r['checked']===1&&$r['pending']===1,'Written answer was auto-graded');
$r=learn_grade($questions,['13','A complete sentence']);lc($r['correct']===0,'Wrong numeric answer accepted');
foreach(['0','1','2','3'] as $n)lc(strlen(learn_next('Reading',(int)$n))>20,'Missing support suggestion');
foreach(['NaN','INF','1e9999'] as $bad){try{learn_questions([['prompt'=>'test','answer'=>$bad,'mode'=>'numeric']]);throw new RuntimeException('Invalid key accepted');}catch(InvalidArgumentException $e){}}
try{learn_grade($questions,['12']);throw new RuntimeException('Missing answer accepted');}catch(InvalidArgumentException $e){}
lc(str_contains(learn_template('Fractions','Maths','4th'),'Teacher')||str_contains(learn_template('Fractions','Maths','4th'),'teacher'),'Draft review instruction absent');
echo "Learning: numeric keys, manual review, missing evidence and support checks passed\n";
