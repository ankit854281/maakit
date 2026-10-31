<?php
declare(strict_types=1);
function ac_report(string $date,array $data,array $teachers,array $students,array $diary,array $plans,array $tracks,array $attendance,array $rounds,array $duty,array $catchup,array $homework,array $bellDone,string $session,array $roundFix=[]): array {
    $bells=ac_bells($date,$data);$ends=['09:35','10:10','10:45','11:40','12:15','12:50','13:30'];$out=[];
    foreach($teachers as $tid=>$teacher){$own=array_values(array_filter($bells,fn($b)=>$b['tid']===$tid));$items=[];$pending=[];$pairDue=[];$due=0;$filled=0;$hw=0;
        foreach($own as $b){$isDue=$date<date('Y-m-d')||($date===date('Y-m-d')&&date('H:i')>=($ends[$b['bell']]??'23:59'));$d=$diary[$date.'|'.$tid.'|'.$b['bell']]??null;
            if($d&&(($d['cls']??'')!==$b['cls']||($d['subj']??'')!==$b['subj']))$d=null;
            $plan=ac_bell_plan($b,$date,$plans);$match=!$plan?'No approved plan':(!$d?'Not yet recorded':(mb_strtolower(trim((string)($d['topic']??'')))===mb_strtolower(trim((string)$plan['topic']))?'Matches topic text':'Different topic — review actual teaching'));
            $items[]=$b+['due'=>$isDue,'topic'=>$d['topic']??'','homework'=>$d['hw']??'','plan'=>$plan,'comparison'=>$match];
            if(!$isDue)continue;$due++;if($d&&trim((string)($d['topic']??''))!==''){$filled++;if(trim((string)($d['hw']??''))!=='')$hw++;}else $pending[]=['kind'=>'diary','text'=>'Fill bell '.($b['bell']+1).' diary: '.$b['cls'].' '.$b['subj']];
            if(!$plan)$pending[]=['kind'=>'plan','text'=>'No approved plan: '.$b['cls'].' '.$b['subj']];
            if($plan&&$d&&str_starts_with($match,'Different'))$pending[]=['kind'=>'review','text'=>'Review plan vs taught: '.$b['cls'].' '.$b['subj']];
            if(($b['arr']||preg_match('/Games|Sports|Play|PE\b/i',$b['subj']))&&!isset($bellDone[$date.'|'.$tid.'|'.$b['bell']]))$pending[]=['kind'=>'activity','text'=>'Record substitute/activity outcome for bell '.($b['bell']+1)];
            $pairDue[$b['cls'].'|'.$b['subj']]=$b;
        }
        $checked=0;foreach($pairDue as $pair=>$b){$att=$attendance[$date.'|'.$b['cls']]??null;$roster=array_filter($students,fn($s)=>($s['cls']??'')===$b['cls']);
            if(!$att){$pending[]=['kind'=>'attendance','text'=>'Attendance missing for '.$b['cls'].'; child checks cannot be confirmed'];continue;}
            $present=array_filter($roster,fn($s)=>!in_array((string)$s['id'],array_map('strval',$att['absent']??[]),true));$t=$tracks[$date.'|'.$pair]??[];$missing=[];
            foreach($present as $s){$v=$t['students'][$s['id']]??null;if(!$v||in_array('not observed',$v,true)||($v['copy']??'')==='not checked')$missing[]=$s['id'];}
            if($missing)$pending[]=['kind'=>'tracking','text'=>count($missing).' present children still need checks: '.$pair];else $checked++;
        }
        $pairs=array_values(array_filter($data['alloc'],fn($a)=>$a['t']===$tid));$allowed=fn($c,$s)=>count(array_filter($pairs,fn($a)=>$a['c']===$c&&$a['s']===$s))>0;
        $catch=count(array_filter($catchup,fn($c)=>$c['date']<=$date&&!$c['done']&&$allowed($c['cls'],$c['subj'])));if($catch)$pending[]=['kind'=>'catchup','text'=>$catch.' absent-child work items still need catch-up'];
        $unfinished=count(array_filter($homework,fn($h)=>$h['date']<=$date&&$allowed($h['cls'],$h['subj'])));if($unfinished)$pending[]=['kind'=>'homework','text'=>$unfinished.' homework items still need completion'];
        $rr=$rounds[$date.'|'.$tid]['rounds']??[];$assigned=in_array($tid,$duty['tids']??[],true);$complete=count(array_filter($rr,fn($r)=>!in_array('not checked',$r['checks'],true)));$issues=count(array_filter($rr,fn($r)=>in_array('issue',$r['checks'],true)&&!isset($roundFix[$r['id']])));
        if($assigned&&$complete<(int)($duty['target']??1))$pending[]=['kind'=>'round','text'=>'Complete duty rounds: '.$complete.'/'.(int)($duty['target']??1)];if($issues)$pending[]=['kind'=>'roundIssue','text'=>$issues.' round(s) reported an issue; Principal follow-up needed'];
        if($own||$assigned||$pending)$out[]=['tid'=>$tid,'name'=>$teacher['name']??$tid,'date'=>$date,'dueBells'=>$due,'diaries'=>$filled,'homeworkBells'=>$hw,'checkedPairs'=>$checked,'duePairs'=>count($pairDue),'rounds'=>$complete,'roundTarget'=>$assigned?(int)($duty['target']??1):0,'catchupPending'=>$catch,'homeworkPending'=>$unfinished,'bells'=>$items,'pending'=>$pending];
    }
    return $out;
}
function ac_report_text(array $report,string $date): string {
    $lines=['School daily teaching · '.$date,'आज की शिक्षण स्थिति'];foreach($report as $r){$lines[]=$r['name'].': diary '.$r['diaries'].'/'.$r['dueBells'].', child checks '.$r['checkedPairs'].'/'.$r['duePairs'].', rounds '.$r['rounds'].'/'.$r['roundTarget'];foreach($r['pending'] as $p)$lines[]='• '.$p['text'];}if(!$report)$lines[]='No scheduled teaching or duty found. Check that the timetable is configured.';return implode("\n",$lines);
}
