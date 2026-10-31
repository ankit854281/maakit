<?php
declare(strict_types=1);
// Same timetable/arrangement rules as app.js. No new assignment store.
function ac_schedule_data(): array {return ['classes'=>sch_rec_get('classes','main')??[],'grid'=>sch_rec_get('grid','main')??[],'alloc'=>sch_rec_get('alloc','main')??[],'teachers'=>ac_rows('teacher'),'staff'=>ac_rows('staffAtt'),'leave'=>ac_rows('leave'),'calendar'=>ac_rows('cal'),'exams'=>sch_rec_get('exams','main')??[]];}
// A nonempty grid can still be partial. Explicit -1 is the timetable's Self Study slot.
function ac_timetable_ready(array $data): bool {
    $classes=array_unique(array_merge(array_column($data['classes']??[],'c'),array_column($data['alloc'],'c')));
    if(!$classes)return false;
    $busy=[];
    foreach($classes as $cls)foreach(range(0,5) as $day)foreach(range(0,6) as $bell){
        $idx=$data['grid'][$cls][$day][$bell]??null;
        if(!is_int($idx)||$idx < -1)return false;
        if($idx===-1)continue;
        $a=$data['alloc'][$idx]??null;
        if(!$a||($a['c']??'')!==$cls||!isset($data['teachers'][$a['t']]))return false;
        $key=$day.'|'.$bell.'|'.$a['t'];if(isset($busy[$key]))return false;$busy[$key]=true;
        $allowed=$data['teachers'][$a['t']]['bells']??null;if($allowed!==null&&!in_array($bell,$allowed,true))return false;
    }
    return true;
}
function ac_school_day(string $date,array $data,?string $cls=null): bool {
    if((int)(new DateTimeImmutable($date))->format('N')===7)return false;
    foreach($data['calendar'] as $e)if(($e['type']??'')==='Holiday'&&($e['date']??'')<=$date&&(($e['to']??'')?:($e['date']??''))>=$date)return false;
    if($cls!==null)foreach($data['exams'] as $e){$dates=array_column($e['sheet']??[],'date');sort($dates);$end=($e['to']??'')?:($dates?end($dates):($e['date']??''));if(!empty($e['noClasses'])&&($e['date']??'')<=$date&&$end>=$date&&(empty($e['classes'])||in_array($cls,$e['classes'],true)))return false;}
    return true;
}
function ac_absent_from(string $date,string $tid,array $data): ?int {
    foreach($data['leave'] as $l)if(($l['tid']??'')===$tid&&($l['status']??'')==='Approved'&&($l['from']??'')<=$date&&($l['to']??'')>=$date)return 0;
    $a=$data['staff'][$date]??[];return in_array($tid,$a['absent']??[],true)?(int)($a['from'][$tid]??0):null;
}
function ac_regular(string $date,array $data): array {
    if(!ac_school_day($date,$data))return [];$dow=(int)(new DateTimeImmutable($date))->format('N')-1;$out=[];
    foreach($data['grid'] as $cls=>$week){if(!ac_school_day($date,$data,(string)$cls))continue;foreach($week[$dow]??[] as $bell=>$idx)if(is_numeric($idx)&&(int)$idx>=0&&isset($data['alloc'][(int)$idx])){$a=$data['alloc'][(int)$idx];$out[]=['bell'=>(int)$bell,'cls'=>(string)$a['c'],'subj'=>(string)$a['s'],'tid'=>(string)$a['t'],'arr'=>false];}}return $out;
}
function ac_arrange(string $date,array $data,array $load=[]): array {
    $regular=ac_regular($date,$data);$staff=$data['staff'][$date]??[];$absent=$staff['absent']??[];
    foreach($data['leave'] as $l)if(($l['status']??'')==='Approved'&&($l['from']??'')<=$date&&($l['to']??'')>=$date&&!in_array($l['tid'],$absent,true))$absent[]=$l['tid'];
    $absent=array_values(array_unique($absent));sort($absent,SORT_STRING);
    $need=[];foreach($absent as $tid)foreach(range(0,6) as $bell)foreach($regular as $b)if($b['bell']===$bell&&($b['tid']===$tid&&($from=ac_absent_from($date,$tid,$data))!==null&&$b['bell']>=$from))$need[]=$b+['absent'=>$tid,'sub'=>null,'manual'=>false];
    $extra=[];
    $busy=function(string $tid,int $bell)use($regular){foreach($regular as $b)if($b['tid']===$tid&&$b['bell']===$bell)return true;return false;};
    $can=function(string $tid,array $n)use($date,$data,$busy,&$need){$from=ac_absent_from($date,$tid,$data);if($tid===$n['absent']||($from!==null&&$n['bell']>=$from)||$busy($tid,$n['bell']))return false;foreach($need as $other)if($other['bell']===$n['bell']&&$other['sub']===$tid)return false;$bells=$data['teachers'][$tid]['bells']??null;return $bells===null||in_array($n['bell'],$bells,true);};
    foreach($need as &$n){$override=$staff['subs'][$n['absent'].'|'.$n['bell']]??null;if($override==='-')$n['manual']=true;elseif($override&&isset($data['teachers'][$override])&&$can($override,$n)){$n['sub']=$override;$n['manual']=true;$extra[$override]=($extra[$override]??0)+1;}}unset($n);
    foreach($need as &$n){if($n['manual'])continue;$pool=[];foreach(array_keys($data['teachers']) as $tid){if(!$can((string)$tid,$n))continue;$score=0;$same=false;$knows=false;foreach($data['alloc'] as $a)if($a['t']===$tid){if($a['s']===$n['subj'])$same=true;if($a['c']===$n['cls'])$knows=true;}$score+=($same?5:0)+($knows?2:0);$dayLoad=count(array_filter($regular,fn($b)=>$b['tid']===$tid));$score-=($extra[$tid]??0)*3+($load[$tid]??0)*.8+$dayLoad*.3;if($tid==='P')$score-=4;if(in_array($tid,['RI','SH','MO'],true))$score-=10;$pool[]=['tid'=>$tid,'score'=>$score];}usort($pool,fn($a,$b)=>($b['score']<=>$a['score'])?:strcmp((string)$a['tid'],(string)$b['tid']));if($pool){$n['sub']=$pool[0]['tid'];$extra[$n['sub']]=($extra[$n['sub']]??0)+1;}}unset($n);
    return $need;
}
function ac_bells(string $date,array $data): array {
    $load=[];$d=substr($date,0,8).'01';while($d<$date){foreach(ac_arrange($d,$data) as $s)if($s['sub'])$load[$s['sub']]=($load[$s['sub']]??0)+1;$d=(new DateTimeImmutable($d))->modify('+1 day')->format('Y-m-d');}
    $out=array_values(array_filter(ac_regular($date,$data),fn($b)=>($f=ac_absent_from($date,$b['tid'],$data))===null||$b['bell']<$f));
    foreach(ac_arrange($date,$data,$load) as $s)if($s['sub'])$out[]=['bell'=>$s['bell'],'cls'=>$s['cls'],'subj'=>$s['subj'],'tid'=>$s['sub'],'arr'=>true,'for'=>$s['absent']];
    usort($out,fn($a,$b)=>$a['bell']<=>$b['bell']);return $out;
}
function ac_activity(string $cls): string {return match(ac_stage($cls)){'foundation'=>'Teacher-led movement: copy a slow action, walk on a marked line, then name a shape. Use a clear safe space and adapt for each child.','primary'=>'Warm up gently, practise a balance or soft-ball pass in pairs, then cool down. Adapt for disability and available safe space.',default=>'Warm up, practise controlled passing or footwork in small groups, then cool down and reflect on teamwork. Teacher checks equipment and safe space first.'};}
function ac_bell_plan(array $bell,string $date,array $plans): ?array {
    foreach($plans as $p)if(($p['status']??'')==='Approved'&&($p['week']??'')===ac_week($date)&&($p['tid']??'')===($bell['for']??$bell['tid'])&&($p['cls']??'')===$bell['cls']&&($p['subj']??'')===$bell['subj']){foreach($p['days']??[] as $day)if($day['date']===$date)return $day;if(!isset($p['days']))return ['topic'=>(string)($p['topics']??''),'revision'=>(string)($p['goal']??'')];}return null;
}
