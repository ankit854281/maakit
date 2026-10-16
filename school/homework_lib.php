<?php
declare(strict_types=1);
const TOOL_HW_CAP=45;
function tool_hw_date(string $s):string{$d=DateTimeImmutable::createFromFormat('!Y-m-d',$s);if(!$d||$d->format('Y-m-d')!==$s)throw new InvalidArgumentException('Choose a valid homework date.');return $s;}
function tool_hw_item(string $kind,string $id,array $d,string $session):?array {
 if($kind==='diary'){$text=trim((string)($d['hw']??''));if($text==='')return null;$date=(string)($d['hwDate']??explode('|',$id)[0]);$minutes=$d['hwMinutes']??null;}
 elseif($kind==='studioTask'){if(($d['session']??'')!==$session||($d['work']??'Homework')!=='Homework')return null;$text=(string)($d['title']??'Notebook assignment');$date=(string)($d['workDate']??$d['due']??'');$minutes=$d['minutes']??null;}
 elseif($kind==='learnMission'){if(($d['session']??'')!==$session)return null;$text=(string)($d['topic']??'Practice');$date=(string)($d['workDate']??$d['due']??'');$minutes=$d['minutes']??null;}
 else return null;
 return ['kind'=>$kind,'key'=>$id,'cls'=>(string)($d['cls']??''),'subj'=>(string)($d['subj']??''),'date'=>$date,'minutes'=>is_int($minutes)&&$minutes>=1&&$minutes<=TOOL_HW_CAP?$minutes:null,'title'=>$text];
}
function tool_hw_rows(string $session):array{$q=sch_db()->query("SELECT k,r,d FROM sch_records WHERE k IN ('diary','studioTask','learnMission') AND deleted=0");$out=[];foreach($q as $r){$d=json_decode($r['d']??'',true);if(is_array($d)&&($v=tool_hw_item($r['k'],$r['r'],$d,$session)))$out[]=$v;}return $out;}
function tool_hw_total(array $rows,string $cls,string $date,string $omitKind='',string $omitKey=''):array{$total=0;$unknown=0;$items=[];foreach($rows as $row)if($row['cls']===$cls&&$row['date']===$date&&!($row['kind']===$omitKind&&$row['key']===$omitKey)){$items[]=$row;if($row['minutes']===null)$unknown++;else$total+=$row['minutes'];}return ['total'=>$total,'unknown'=>$unknown,'remaining'=>max(0,TOOL_HW_CAP-$total),'items'=>$items,'cap'=>TOOL_HW_CAP];}
// Caller holds the Principal row lock in the same transaction as the write.
function tool_hw_guard(string $kind,string $id,array $data,string $session,bool $resolving=false):void {
 $item=tool_hw_item($kind,$id,$data,$session);if(!$item)return;tool_hw_date($item['date']);if($item['minutes']===null)throw new InvalidArgumentException('Add an estimated homework time from 1 to 45 minutes.');
 $budget=tool_hw_total(tool_hw_rows($session),$item['cls'],$item['date'],$kind,$id);
 if($budget['unknown']&&!$resolving)throw new InvalidArgumentException('This class has older homework without time estimates. Add those estimates in Teacher Toolkit before assigning more.');
 if($budget['total']+$item['minutes']>TOOL_HW_CAP)throw new InvalidArgumentException('Class '.$item['cls'].' has '.$budget['remaining'].' of 45 homework minutes left on '.$item['date'].'. Shorten this task or choose another day.');
}
