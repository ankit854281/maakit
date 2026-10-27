<?php
declare(strict_types=1);
// ac* records never enter legacy sync. Every write is serialized and scoped here.
function ac_rows(string $kind): array {
    $q=sch_db()->prepare('SELECT r,d FROM sch_records WHERE k=? AND deleted=0 ORDER BY r');$q->execute([$kind]);$out=[];
    foreach($q as $r){$d=json_decode($r['d'],true);if(is_array($d))$out[$r['r']]=$d;}return $out;
}
function ac_session(): string { $s=trim((string)(sch_rec_get('settings','main')['session']??''));if($s==='')throw new InvalidArgumentException('Ask the Principal to set the school session first.');return $s; }
function ac_stage(string $cls): string {if(preg_match('/^(PG|Nursery|LKG|UKG)$/i',$cls))return 'foundation';return (int)$cls<=5?'primary':'secondary';}
function ac_week(string $date): string {$d=new DateTimeImmutable(ac_date($date));return $d->modify('-'.((int)$d->format('N')-1).' days')->format('Y-m-d');}
function ac_day_plan($input,string $session): array {
    if(!is_array($input)||!array_is_list($input)||!$input||count($input)>6)throw new InvalidArgumentException('Prepare one to six teaching days.');$out=[];$seen=[];
    foreach($input as $row){$date=ac_date($row['date']??'');if(!ac_in_session($date,$session)||isset($seen[$date])||(int)(new DateTimeImmutable($date))->format('N')===7)throw new InvalidArgumentException('Choose distinct school days in this session.');$seen[$date]=true;
        $v=['date'=>$date];foreach(['topic'=>200,'classwork'=>500,'hw'=>500,'books'=>300,'revision'=>400] as $f=>$max)$v[$f]=ac_text($row[$f]??'',$max,$f==='topic');
        $m=$row['hwMinutes']??0;if(!is_int($m)||$m<0||$m>45||($v['hw']!==''&&$m<1))throw new InvalidArgumentException('Estimate homework time from 1 to 45 minutes, or leave homework empty.');$v['hwMinutes']=$v['hw']===''?0:$m;$out[]=$v;
    }return $out;
}
function ac_template(string $stage,string $unit,array $dates): array {
    $cw=match($stage){'foundation'=>'Model with objects, practise aloud, then hear each child.','primary'=>'Read together, model one example, then check independent practice.',default=>'Explain the concept, work a textbook example, then check an exercise.'};
    $hw=match($stage){'foundation'=>'Practise the selected oral item with an adult.','primary'=>'Revise the selected textbook passage or example.',default=>'Revise the selected example; teacher to choose exercise questions.'};
    return array_map(fn($d)=>['date'=>$d,'topic'=>$unit,'classwork'=>$cw,'hw'=>$hw,'hwMinutes'=>$stage==='foundation'?5:10,'books'=>'Teacher to confirm book and copy','revision'=>'Recheck previous learning and pending oral items'],$dates);
}
function ac_units(string $cls,string $subj,string $session): array {
    $r=sch_rec_get('acUnit',$session.'|'.$cls.'|'.$subj);$units=$r['units']??[];
    if(!$units&&ac_stage($cls)==='foundation'&&preg_match('/English|Phonics|Letters/i',$subj))$units=array_map(fn($l)=>['id'=>'letter-'.$l,'name'=>$l,'type'=>'letter'],range('A','Z'));
    if(!$units&&ac_stage($cls)==='foundation'&&preg_match('/Math|Numbers/i',$subj))$units=array_map(fn($n)=>['id'=>'number-'.$n,'name'=>(string)$n,'type'=>'number'],range(1,100));
    if(ac_stage($cls)!=='foundation')foreach(sch_rec_get('syl',$cls.'|'.$subj)['chapters']??[] as $i=>$c)$units[]=['id'=>'chapter-'.$i,'name'=>(string)($c['n']??''),'type'=>'chapter','done'=>$c['done']??false];
    return $units;
}
function ac_unit_list($input): array {
    if(!is_array($input)||!array_is_list($input)||!$input||count($input)>150)throw new InvalidArgumentException('Add 1 to 150 curriculum items.');$out=[];$ids=[];
    foreach($input as $v){$id=ac_text($v['id']??'',70);if(isset($ids[$id]))throw new InvalidArgumentException('Each item needs its own ID.');$ids[$id]=true;$type=$v['type']??'';if(!in_array($type,['letter','rhyme','number','colour','shape','story','oral'],true))throw new InvalidArgumentException('Choose a foundation unit type.');$out[]=['id'=>$id,'name'=>ac_text($v['name']??'',200),'type'=>$type];}return $out;
}
function ac_oral_cells($input,array $kids,array $units): array {
    if(!is_array($input))throw new InvalidArgumentException('Check the oral board.');$sids=array_column($kids,'id');$ids=array_column($units,'id');$out=[];
    foreach($input as $sid=>$cells){if(!in_array((string)$sid,$sids,true)||!is_array($cells))throw new InvalidArgumentException('Choose children in this class.');foreach($cells as $id=>$status){if(!in_array((string)$id,$ids,true)||!in_array($status,['done','needs repeat'],true))throw new InvalidArgumentException('Choose an item from this oral board.');$out[$sid][$id]=$status;}}return $out;
}
function ac_text($v,int $max=200,bool $required=true): string {
    if(!is_string($v)||mb_strlen($v)>$max||str_contains($v,'|'))throw new InvalidArgumentException('Check the text and remove any | character.');
    $v=trim($v);if($required&&$v==='')throw new InvalidArgumentException('Complete the required fields.');return $v;
}
function ac_date($v): string {if(!is_string($v))throw new InvalidArgumentException('Choose a date.');$d=DateTimeImmutable::createFromFormat('!Y-m-d',$v);if(!$d||$d->format('Y-m-d')!==$v)throw new InvalidArgumentException('Choose a valid date.');return $v;}
function ac_allowed(array $u,string $cls,string $subj): bool {return $u['role']==='principal'||($u['role']==='teacher'&&sch_teaches((string)$u['tid'],$cls,$subj));}
function ac_children(array $u): array {
    if($u['role']==='parent')return sch_children((string)$u['tid']);
    if($u['role']==='student'){$s=sch_rec_get('student',(string)$u['tid']);return $s?[$s]:[];}
    return array_values(array_filter(ac_rows('student'),fn($s)=>in_array($u['role'],['principal','chairman'],true)||sch_in_class((string)$u['tid'],(string)($s['cls']??''))));
}
function ac_projection(array $tracks,array $kids,string $session): array {
    $out=[];foreach($tracks as $key=>$t){if(($t['session']??'')!==$session)continue;foreach($kids as $s){$sid=(string)$s['id'];if(($s['cls']??'')!==($t['cls']??'')||!isset($t['students'][$sid]))continue;
        $out[]=['key'=>$key,'sid'=>$sid,'date'=>$t['date'],'cls'=>$t['cls'],'subj'=>$t['subj'],'task'=>$t['task']??'','status'=>$t['students'][$sid]];
    }}return $out;
}
function ac_pending(array $tracks,array $resolutions,array $kids,string $session): array {
    return array_values(array_filter(ac_projection($tracks,$kids,$session),function($t)use($resolutions,$session){$resolved=$resolutions[hash('sha256',$session.'|'.$t['key'].'|'.$t['sid'])]??null;return in_array($t['status']['hw']??'',['not done','incomplete'],true)&&(!$resolved||($resolved['source']??'')!==ac_work_source($t));}));
}
function ac_work_source(array $t): string {return hash('sha256',json_encode([$t['task']??'',$t['status']['hw']??'']));}
function ac_parent(array $kids,string $session): array {
    $oral=[];foreach(ac_rows('acOral') as $o)if(($o['session']??'')===$session)foreach($kids as $kid)if(($kid['cls']??'')===$o['cls']&&isset($o['cells'][$kid['id']]))$oral[]=['sid'=>$kid['id'],'subj'=>$o['subj'],'date'=>$o['date'],'cells'=>$o['cells'][$kid['id']]];
    return ['tracking'=>ac_projection(ac_rows('acTrack'),$kids,$session),'pending'=>ac_pending(ac_rows('acTrack'),ac_rows('acResolve'),$kids,$session),'oral'=>$oral,'catchup'=>ac_catchup(ac_rows('studAtt'),ac_rows('diary'),ac_rows('acCatch'),$kids,$session)];
}
function ac_in_session(string $date,string $session): bool {
    if(!preg_match('/^(20\d{2})\s*[-–]\s*(?:20)?(\d{2})$/u',$session,$m))return false;
    return $date>=$m[1].'-04-01'&&$date<=((int)$m[1]+1).'-03-31';
}
function ac_catchup(array $attendance,array $diaries,array $done,array $kids,string $session): array {
    $byDay=[];$byClass=[];foreach($kids as $kid)$byClass[$kid['cls']??''][]=$kid;
    foreach($diaries as $r=>$d){$day=explode('|',(string)$r)[0];$byDay[$day.'|'.($d['cls']??'')][$r]=$d;}
    $out=[];foreach($attendance as $key=>$att){[$date,$cls]=array_pad(explode('|',$key),2,'');if(!ac_in_session($date,$session)||$date>date('Y-m-d'))continue;
        $absent=array_fill_keys(array_map('strval',$att['absent']??[]),true);foreach($byClass[$cls]??[] as $kid){if(!isset($absent[(string)$kid['id']]))continue;
            foreach($byDay[$date.'|'.$cls]??[] as $r=>$d){$subject=(string)($d['subj']??'');$topic=(string)($d['topic']??'');$hw=(string)($d['hw']??'');
                $extra=['classwork'=>(string)($d['classwork']??''),'books'=>(string)($d['books']??''),'revision'=>(string)($d['revision']??'')];
                $id=hash('sha256',json_encode([$session,$r,$kid['id'],$topic,$hw,$extra]));$out[]=['id'=>$id,'sid'=>$kid['id'],'cls'=>$cls,'subj'=>$subject,'date'=>$date,'topic'=>$topic,'hw'=>$hw,'done'=>isset($done[$id]),'caughtDate'=>$done[$id]['date']??null]+$extra;
            }
        }
    }return $out;
}
function ac_track_students($input,array $roster,array $absent): array {
    if(!is_array($input)||array_is_list($input))throw new InvalidArgumentException('Check the class list before saving.');
    $allowed=array_column($roster,null,'id');$out=[];
    foreach($input as $sid=>$v){if(!isset($allowed[$sid])||in_array((string)$sid,$absent,true))throw new InvalidArgumentException('Only present children in this class can be checked.');
        if(!is_array($v))throw new InvalidArgumentException('Check each child status.');$clean=[];
        foreach(['hw'=>['done','not done','incomplete','not observed'],'copy'=>['checked','not checked','not observed'],'oral'=>['done','needs repeat','not observed'],'book'=>['brought','not brought','not observed']] as $field=>$values){if(!in_array($v[$field]??'', $values,true))throw new InvalidArgumentException('Choose a valid '.$field.' status.');$clean[$field]=$v[$field];}
        $out[(string)$sid]=$clean;
    }
    if(!$out)throw new InvalidArgumentException('There are no present children to check.');return $out;
}
function ac_save(string $kind,string $key,array $data,array $u,$expected=null): array {
    $old=sch_rec_get($kind,$key);if($expected!==null&&(!is_int($expected)||$expected!==(int)($old['rev']??0)))throw new InvalidArgumentException('Another teacher saved this record. Reload before changing it.');
    if($old)sch_rec_put('acHistory',hash('sha256',$kind.'|'.$key.'|'.$old['rev']),['kind'=>$kind,'key'=>$key,'record'=>$old],$u['uid']);
    $data['rev']=max(sch_now_ms(),(int)($old['rev']??0)+1);$data['actor']=$u['uid'];$data['at']=sch_now_ms();sch_rec_put($kind,$key,$data,$u['uid']);return $data;
}
