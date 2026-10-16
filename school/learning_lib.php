<?php
declare(strict_types=1);
function learn_ai_secret(string $name): string {return (string)(getenv($name) ?: (defined($name)?constant($name):''));}
const LEARN_SKILLS=['Reading','Writing','Numeracy','Understanding','Speaking','Creativity','Collaboration'];
function learn_session(): string {return (string)((sch_rec_get('settings','main')??[])['session']??'');}
function learn_rows(string $kind): array {
 $q=sch_db()->prepare('SELECT r,d FROM sch_records WHERE k=? AND deleted=0 ORDER BY ver');$q->execute([$kind]);$out=[];
 foreach($q as $r){$d=json_decode($r['d'],true);if(is_array($d))$out[$r['r']]=$d;}return $out;
}
function learn_allowed(array $u,string $cls,string $subj):bool{return $u['role']==='principal'||($u['role']==='teacher'&&sch_teaches((string)$u['tid'],$cls,$subj));}
function learn_string($v,int $max=500):string {if(!is_string($v)||trim($v)===''||mb_strlen($v)>$max)throw new InvalidArgumentException('Complete the required text fields.');return trim($v);}
function learn_questions($rows):array {
 if(!is_array($rows)||!array_is_list($rows)||count($rows)<1||count($rows)>10)throw new InvalidArgumentException('Add 1 to 10 practice questions.');
 $out=[];foreach($rows as $q){$mode=$q['mode']??'manual';if(!in_array($mode,['numeric','manual'],true))throw new InvalidArgumentException('Choose numeric or teacher-reviewed answers.');
 $answer=learn_string($q['answer']??'',1500);if($mode==='numeric'&&(!preg_match('/^-?\d+(?:\.\d+)?$/',$answer)||!is_finite((float)$answer)))throw new InvalidArgumentException('Numeric keys must contain a finite number.');
 $out[]=['prompt'=>learn_string($q['prompt']??'',1000),'answer'=>$answer,'mode'=>$mode,'hint'=>substr((string)($q['hint']??''),0,500)];}return $out;
}
function learn_grade(array $questions,array $answers):array {
 if(count($questions)!==count($answers)||!array_is_list($answers))throw new InvalidArgumentException('Answer every question.');
 $correct=0;$checked=0;$pending=0;$feedback=[];
 foreach($questions as $i=>$q){$a=learn_string($answers[$i]??'',1500);if($q['mode']==='manual'){$pending++;$feedback[]='Teacher review pending';continue;}
 $checked++;$yes=preg_match('/^-?\d+(?:\.\d+)?$/',$a)&&is_finite((float)$a)&&abs((float)$a-(float)$q['answer'])<=0.000001;
 if($yes)$correct++;$feedback[]=$yes?'Correct':'Try the worked example with your teacher';}
 return ['correct'=>$correct,'checked'=>$checked,'pending'=>$pending,'feedback'=>$feedback];
}
function learn_next(string $skill,int $level):string {
 $tasks=['Reading'=>'Read five familiar words, then one short sentence aloud.','Writing'=>'Write one sentence from a picture; check capitals and a full stop.','Numeracy'=>'Use counters or drawings for three examples before using numbers.','Understanding'=>'Explain one example and one non-example in your own words.','Speaking'=>'Rehearse a 30-second explanation with a partner.','Creativity'=>'Make two different solutions or drawings and explain a choice.','Collaboration'=>'Complete a paired task with clear speaker and checker roles.'];
 return match($level){0=>'Model it slowly; let the child copy one example. '.($tasks[$skill]??''),1=>'Give one hint at a time; remove the hint on the final example. '.($tasks[$skill]??''),2=>'Try a new example independently and explain the steps.',3=>'Apply the skill in a new activity and explain it to a partner.',default=>'Observe a short task before choosing support.'};
}
function learn_template(string $topic,string $subj,string $cls):string {
 return "Class $cls • $subj • $topic\nObjective: Children explain the idea and complete one new example independently. Adapt this objective to your textbook.\n\n5 min — Recall: ask one prerequisite question.\n10 min — Model: teacher demonstrates one example with a concrete aid.\n10 min — Guided practice: pairs try two examples; teacher checks reasoning.\n10 min — Independent task: a different example with no prompt.\n5 min — Exit check: ask the child to explain what changed and why.\n\nSupport: use a picture/object and fewer steps, then reduce hints.\nExtension: ask for a second method or a real-life application.\nEvidence: record a short observation against one named skill.\nAnswer key: add verified textbook/source answers below; do not treat this outline as a textbook answer key.\nReview: check the objective, examples and answers before publishing.";
}
