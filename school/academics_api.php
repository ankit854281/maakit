<?php
declare(strict_types=1);
require __DIR__.'/inc.php';require __DIR__.'/academics_lib.php';
require __DIR__.'/homework_lib.php';
require __DIR__.'/academics_schedule.php';
require __DIR__.'/academics_rounds.php';
require __DIR__.'/academics_report.php';
try {
    $db=sch_db();$u=sch_auth();$session=ac_session();$a=(string)($_GET['a']??'state');
    if(!in_array($u['role'],['principal','teacher','chairman','parent','student'],true))sch_fail('This login cannot open teaching records.',403);
    $kids=ac_children($u);$family=in_array($u['role'],['parent','student'],true);
    if($a==='report'){
        if($family)sch_fail('Daily staff reports are private.',403);$date=ac_date($_GET['date']??date('Y-m-d'));if(!ac_in_session($date,$session)||$date>date('Y-m-d'))sch_fail('Choose a current or past day in this session.');
        $teachers=ac_rows('teacher');if($u['role']==='teacher')$teachers=array_intersect_key($teachers,[(string)$u['tid']=>true]);$students=ac_rows('student');$tracks=ac_rows('acTrack');$attendance=ac_rows('studAtt');$diaries=ac_rows('diary');
        $catch=ac_catchup($attendance,$diaries,ac_rows('acCatch'),array_values($students),$session);$homework=ac_pending($tracks,ac_rows('acResolve'),array_values($students),$session);
        $report=ac_report($date,ac_schedule_data(),$teachers,$students,$diaries,ac_rows('lplan'),$tracks,$attendance,ac_rows('acRound'),sch_rec_get('acDuty',$date)??[],$catch,$homework,ac_rows('acBell'),$session,ac_rows('acRoundFix'));
        sch_json(['ok'=>true,'date'=>$date,'report'=>$report,'text'=>ac_report_text($report,$date),'generatedAt'=>sch_now_ms(),'configured'=>(bool)(sch_rec_get('grid','main')??[])]);
    }
    if($a==='roundPhoto'){
        if($family)sch_fail('Not allowed.',403);$id=ac_text($_GET['id']??'',32);$photo=null;
        foreach(ac_rows('acRound') as $round)if(($round['session']??'')===$session&&(in_array($u['role'],['principal','chairman'],true)||($round['tid']??'')===$u['tid']))foreach($round['rounds']??[] as $r)if(($r['photo']['id']??'')===$id)$photo=$r['photo'];
        if(!$photo||!preg_match('/^[a-f0-9]{32}\.jpg$/',$photo['file']))sch_fail('Photo not found.',404);$path=ac_media_dir().'/'.$photo['file'];if(!is_file($path))sch_fail('Photo not available. Ask the office to restore the private backup.',404);
        header('Content-Type: image/jpeg');header('Cache-Control: no-store');header('X-Content-Type-Options: nosniff');readfile($path);exit;
    }
    if($a==='attendance'){
        $cls=ac_text($_GET['cls']??'',30);$subj=ac_text($_GET['subj']??'',100);$date=ac_date($_GET['date']??'');
        if($family||!($u['role']==='chairman'||ac_allowed($u,$cls,$subj)))sch_fail('This class is not assigned to you.',403);
        $att=sch_rec_get('studAtt',$date.'|'.$cls);sch_json(['ok'=>true,'marked'=>$att!==null,'absent'=>array_map('strval',$att['absent']??[])]);
    }
    if($a==='state'){
        if($family)sch_json(['ok'=>true,'role'=>$u['role'],'session'=>$session,'kids'=>array_map(fn($s)=>array_intersect_key($s,array_flip(['id','name','cls'])),$kids)]+ac_parent($kids,$session));
        $pairs=array_values(array_filter((array)sch_rec_get('alloc','main'),fn($p)=>in_array($u['role'],['principal','chairman'],true)||($p['t']??'')===$u['tid']));
        $tracks=array_filter(ac_rows('acTrack'),fn($t)=>($t['session']??'')===$session&&($u['role']==='chairman'||ac_allowed($u,$t['cls'],$t['subj'])));
        $units=[];$unitRevs=[];foreach($pairs as $p){$pair=$p['c'].'|'.$p['s'];$units[$pair]=ac_units($p['c'],$p['s'],$session);$unitRevs[$pair]=(sch_rec_get('acUnit',$session.'|'.$pair)['rev']??0);}
        $oral=array_filter(ac_rows('acOral'),fn($t)=>($t['session']??'')===$session&&($u['role']==='chairman'||ac_allowed($u,$t['cls'],$t['subj'])));
        $catchup=array_values(array_filter(ac_catchup(ac_rows('studAtt'),ac_rows('diary'),ac_rows('acCatch'),$kids,$session),fn($t)=>$u['role']==='chairman'||ac_allowed($u,$t['cls'],$t['subj'])));
        $viewDate=ac_date($_GET['date']??date('Y-m-d'));$bells=[];$plans=ac_rows('lplan');foreach(ac_bells($viewDate,ac_schedule_data()) as $bell)if(in_array($u['role'],['principal','chairman'],true)||$bell['tid']===$u['tid']){$bell['plan']=ac_bell_plan($bell,$viewDate,$plans);$bell['activity']=preg_match('/Games|Sports|Play|PE\b/i',$bell['subj'])?ac_activity($bell['cls']):null;$bell['done']=sch_rec_get('acBell',$viewDate.'|'.$bell['tid'].'|'.$bell['bell'])!==null;$bells[]=$bell;}
        $duty=sch_rec_get('acDuty',$viewDate)??['tids'=>[],'target'=>1];$rounds=array_values(array_filter(ac_rows('acRound'),fn($r)=>($r['session']??'')===$session&&($r['date']??'')===$viewDate&&(in_array($u['role'],['principal','chairman'],true)||($r['tid']??'')===$u['tid'])));
        foreach($rounds as &$round)foreach($round['rounds'] as &$r)$r['resolution']=sch_rec_get('acRoundFix',$r['id']);unset($r,$round);
        $teachers=[];if($u['role']==='principal')foreach(ac_rows('teacher') as $id=>$t)$teachers[]=['id'=>$id,'name'=>$t['name']??$id];
        $planVersions=[];foreach($plans as $key=>$p)if($u['role']!=='teacher'||($p['tid']??'')===$u['tid'])$planVersions[$key]=hash('sha256',json_encode($p));
        sch_json(['ok'=>true,'role'=>$u['role'],'tid'=>$u['tid'],'session'=>$session,'pairs'=>$pairs,'kids'=>array_map(fn($s)=>array_intersect_key($s,array_flip(['id','name','cls','adm'])),$kids),'teachers'=>$teachers,'duty'=>$duty,'rounds'=>$rounds,'bells'=>$bells,'viewDate'=>$viewDate,'planVersions'=>$planVersions,'catchup'=>$catchup,'units'=>$units,'unitRevs'=>$unitRevs,'oral'=>$oral,'tracks'=>$tracks,'pending'=>ac_pending($tracks,ac_rows('acResolve'),$kids,$session)]);
    }
    if($family||!in_array($u['role'],['principal','teacher'],true))sch_fail('Teacher login required.',403);
    if(($_SERVER['REQUEST_METHOD']??'GET')!=='POST')sch_fail('Use the save button.',405);
    $limit=$a==='round'?2100000:450000;$raw=file_get_contents('php://input',false,null,0,$limit+1);if(strlen($raw)>$limit)sch_fail('The form is too large. Use a smaller photo or one class at a time.');$in=json_decode($raw,true);if(!is_array($in))sch_fail('Reload the form and try again.');
    $cls=ac_text($in['cls']??'',30,!in_array($a,['round','duty','roundFix'],true));$subj=ac_text($in['subj']??'',100,!in_array($a,['round','duty','roundFix'],true));
    $db->beginTransaction();$db->query("SELECT uid FROM sch_users WHERE uid='principal' FOR UPDATE")->fetch();
    if(ac_session()!==$session)throw new InvalidArgumentException('The school session changed. Reload before saving.');
    if($a==='roundFix'){
        if($u['role']!=='principal')sch_fail('Only the Principal closes duty issues.',403);$date=ac_date($in['date']??'');$tid=ac_text($in['tid']??'',20);$id=ac_text($in['id']??'',36);$round=sch_rec_get('acRound',$date.'|'.$tid);$found=false;
        if(($round['session']??'')===$session)foreach($round['rounds']??[] as $r)if($r['id']===$id&&in_array('issue',$r['checks'],true))$found=true;
        if(!$found)throw new InvalidArgumentException('Choose a recorded duty issue.');$record=ac_save('acRoundFix',$id,['session'=>$session,'date'=>$date,'tid'=>$tid,'note'=>ac_text($in['note']??'',800)],$u);sch_log($u,'Academics','Duty issue closed');$db->commit();sch_json(['ok'=>true,'record'=>$record]);
    }
    if(in_array($a,['round','duty'],true)){
        $date=ac_date($in['date']??'');if(!ac_in_session($date,$session))throw new InvalidArgumentException('Choose a date in this session.');
        if($a==='duty'){
            if($u['role']!=='principal')sch_fail('Only the Principal assigns duty rounds.',403);$tids=$in['tids']??[];$target=$in['target']??0;$all=ac_rows('teacher');if(!is_array($tids)||!array_is_list($tids)||count($tids)>20||!is_int($target)||$target<1||$target>6)throw new InvalidArgumentException('Choose duty teachers and 1 to 6 rounds.');foreach($tids as $tid)if(!is_string($tid)||!isset($all[$tid]))throw new InvalidArgumentException('Choose existing teachers.');
            $data=ac_save('acDuty',$date,['session'=>$session,'date'=>$date,'tids'=>array_values(array_unique($tids)),'target'=>$target],$u,$in['rev']??-1);
        }else{
            $tid=(string)$u['tid'];$duty=sch_rec_get('acDuty',$date);if(!in_array($tid,$duty['tids']??[],true)||($duty['session']??'')!==$session||$date>date('Y-m-d'))throw new InvalidArgumentException('The Principal must assign you a duty round for this day first.');
            $id=ac_text($in['id']??'',36);if(!preg_match('/^[a-f0-9-]{36}$/',$id))throw new InvalidArgumentException('Reload the round form.');$key=$date.'|'.$tid;$old=sch_rec_get('acRound',$key);$rounds=$old['rounds']??[];
            foreach($rounds as $r)if($r['id']===$id){$db->rollBack();sch_json(['ok'=>true,'duplicate'=>true]);}
            if(count($rounds)>=12)throw new InvalidArgumentException('Twelve rounds are already recorded for this day.');$checks=ac_round_checks($in['checks']??null);$remarks=ac_text($in['remarks']??'',1200,false);if(in_array('issue',$checks,true)&&$remarks==='')throw new InvalidArgumentException('Describe the issue and the next action.');
            $time=ac_text($in['time']??'',5);if(!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/',$time)||($date===date('Y-m-d')&&$time>date('H:i')))throw new InvalidArgumentException('Choose the time you actually made the round.');
            $photo=ac_round_photo($in['photo']??null);$rounds[]=['id'=>$id,'checks'=>$checks,'remarks'=>$remarks,'time'=>$time,'at'=>sch_now_ms(),'photo'=>$photo];
            $data=ac_save('acRound',$key,['session'=>$session,'date'=>$date,'tid'=>$tid,'rounds'=>$rounds],$u,$in['rev']??-1);
        }
        sch_log($u,'Academics',$a.' '.$date);$db->commit();sch_json(['ok'=>true,'record'=>$data]);
    }
    if($a==='bellDone'){
        $date=ac_date($in['date']??'');$tid=$u['role']==='principal'?ac_text($in['tid']??'',100):(string)$u['tid'];$bell=$in['bell']??null;$found=null;
        foreach(ac_bells($date,ac_schedule_data()) as $b)if($b['tid']===$tid&&$b['bell']===$bell&&$b['cls']===$cls&&$b['subj']===$subj)$found=$b;
        $ends=['09:35','10:10','10:45','11:40','12:15','12:50','13:30'];
        if(!$found||$date>date('Y-m-d')||!ac_in_session($date,$session)||($date===date('Y-m-d')&&date('H:i')<($ends[$bell]??'23:59')))throw new InvalidArgumentException('Choose one of your completed teaching bells in this session.');
        $record=ac_save('acBell',$date.'|'.$tid.'|'.$bell,['session'=>$session,'date'=>$date,'tid'=>$tid,'bell'=>$bell,'cls'=>$cls,'subj'=>$subj,'note'=>ac_text($in['note']??'',500,false)],$u);sch_log($u,'Academics','Activity/substitute bell checked');$db->commit();sch_json(['ok'=>true,'record'=>$record]);
    }
    if(!ac_allowed($u,$cls,$subj))sch_fail('This class and subject are not assigned to you.',403);
    if($a==='draft'){
        $dates=$in['dates']??[];if(!is_array($dates)||count($dates)<1||count($dates)>6)throw new InvalidArgumentException('Choose one to six teaching days.');
        $data=ac_day_plan(ac_template(ac_stage($cls),ac_text($in['topic']??'',200),$dates),$session);
        if(ac_stage($cls)==='foundation'){$oral=sch_rec_get('acOral',$session.'|'.$cls.'|'.$subj)['cells']??[];$units=ac_units($cls,$subj,$session);$roster=array_filter(ac_rows('student'),fn($s)=>($s['cls']??'')===$cls);$revision=[];foreach($units as $unit)foreach($roster as $kid)if(($oral[$kid['id']][$unit['id']]??'')!=='done'){$revision[]=$unit['name'];break;}if($revision)foreach($data as &$day)$day['revision']=mb_substr('Oral revision: '.implode(', ',$revision),0,400);unset($day);}
    }elseif($a==='plan'){
        if(($in['reviewed']??false)!==true)throw new InvalidArgumentException('Check the lesson, homework and book names before submitting.');
        if(!is_array($in['expected']??null))throw new InvalidArgumentException('Reload the lesson form before submitting.');
        $days=ac_day_plan($in['days']??null,$session);$tid=$u['role']==='principal'?ac_text($in['tid']??'',100):(string)$u['tid'];
        if(!sch_teaches($tid,$cls,$subj))throw new InvalidArgumentException('Choose the assigned subject teacher.');
        $weeks=[];foreach($days as $day){if(!ac_school_day($day['date'],ac_schedule_data(),$cls))throw new InvalidArgumentException('This class has no normal teaching on '.$day['date'].'. Choose another day.');$budget=tool_hw_total(tool_hw_rows($session),$cls,$day['date']);if($day['hw']!==''&&($budget['unknown']||$day['hwMinutes']>$budget['remaining']))throw new InvalidArgumentException('Homework time is not available for '.$day['date'].'. Check Teacher Toolkit.');$weeks[ac_week($day['date'])][]=$day;}
        $data=[];foreach($weeks as $week=>$list){$key=$week.'|'.$tid.'|'.$cls.'|'.$subj;$old=sch_rec_get('lplan',$key);
            if(($in['expected'][$key]??null)!==($old?hash('sha256',json_encode($old)):null))throw new InvalidArgumentException('The weekly plan changed. Reload before submitting.');
            if($old&&(($old['status']??'')==='Approved'||!isset($old['days'])))throw new InvalidArgumentException('An approved or older weekly plan exists. Ask the Principal to send it back in Lesson Plans before editing.');
            $merged=[];foreach($old['days']??[] as $day)$merged[$day['date']]=$day;foreach($list as $day)$merged[$day['date']]=$day;ksort($merged);$list=array_values($merged);
            $data[]=ac_save('lplan',$key,['session'=>$session,'week'=>$week,'tid'=>$tid,'cls'=>$cls,'subj'=>$subj,'topics'=>implode("\n",array_column($list,'topic')),'goal'=>'Reviewed daily teaching draft','days'=>$list,'status'=>'Submitted','feedback'=>'','source'=>'academics','reviewedBy'=>$u['label']],$u);
        }
    }elseif($a==='track'){
        $date=ac_date($in['date']??'');if($date>date('Y-m-d')||!ac_in_session($date,$session))throw new InvalidArgumentException('Choose a past or current teaching day in this school session.');
        if(($in['confirmed']??false)!==true)throw new InvalidArgumentException('Confirm that you checked the present children.');
        $att=sch_rec_get('studAtt',$date.'|'.$cls);if(!$att)throw new InvalidArgumentException('Mark class attendance first so absent children are not marked done.');
        $roster=array_values(array_filter(ac_rows('student'),fn($s)=>($s['cls']??'')===$cls));
        $students=ac_track_students($in['students']??null,$roster,array_map('strval',$att['absent']??[]));
        $key=$date.'|'.$cls.'|'.$subj;$old=sch_rec_get('acTrack',$key);if($old&&($old['session']??'')!==$session)throw new InvalidArgumentException('This date belongs to another school session.');
        $data=ac_save('acTrack',$key,['session'=>$session,'date'=>$date,'cls'=>$cls,'subj'=>$subj,'task'=>ac_text($in['task']??'',500,false),'students'=>$students,'tid'=>$u['tid']],$u,$in['rev']??-1);
    }elseif($a==='catchup'){
        $id=ac_text($in['id']??'',64);$items=ac_catchup(ac_rows('studAtt'),ac_rows('diary'),ac_rows('acCatch'),ac_children($u),$session);$found=null;
        foreach($items as $item)if($item['id']===$id&&$item['cls']===$cls&&$item['subj']===$subj)$found=$item;
        if(!$found)throw new InvalidArgumentException('This catch-up item is no longer available. Reload the list.');
        $data=ac_save('acCatch',$id,['session'=>$session,'sid'=>$found['sid'],'cls'=>$cls,'subj'=>$subj,'date'=>date('Y-m-d')],$u);
    }elseif($a==='units'){
        if(ac_stage($cls)!=='foundation')throw new InvalidArgumentException('Manage book chapters from Syllabus.');
        $key=$session.'|'.$cls.'|'.$subj;$data=ac_save('acUnit',$key,['session'=>$session,'cls'=>$cls,'subj'=>$subj,'units'=>ac_unit_list($in['units']??null)],$u,$in['rev']??-1);
    }elseif($a==='oral'){
        if(ac_stage($cls)!=='foundation')throw new InvalidArgumentException('The oral board is for PG–UKG.');
        $roster=array_values(array_filter(ac_rows('student'),fn($s)=>($s['cls']??'')===$cls));$units=ac_units($cls,$subj,$session);
        $key=$session.'|'.$cls.'|'.$subj;$data=ac_save('acOral',$key,['session'=>$session,'cls'=>$cls,'subj'=>$subj,'cells'=>ac_oral_cells($in['cells']??null,$roster,$units),'date'=>date('Y-m-d')],$u,$in['rev']??-1);
    }elseif($a==='resolve'){
        $origin=ac_origin($in['origin']??'');$sid=ac_text($in['sid']??'',100);$track=sch_rec_get('acTrack',$origin);$student=sch_rec_get('student',$sid);
        if(!$track||($track['session']??'')!==$session||$track['cls']!==$cls||$track['subj']!==$subj||($student['cls']??'')!==$cls||!in_array($track['students'][$sid]['hw']??'',['not done','incomplete'],true))throw new InvalidArgumentException('Choose a pending homework item from this class.');
        $source=ac_work_source(['task'=>$track['task']??'','status'=>$track['students'][$sid]]);
        $key=hash('sha256',$session.'|'.$origin.'|'.$sid);$data=ac_save('acResolve',$key,['session'=>$session,'origin'=>$origin,'sid'=>$sid,'cls'=>$cls,'subj'=>$subj,'source'=>$source,'date'=>date('Y-m-d')],$u);
    }else sch_fail('Unknown action.',404);
    sch_log($u,'Academics',$a.' '.$cls.' '.$subj);$db->commit();sch_json(['ok'=>true,'record'=>$data]);
}catch(InvalidArgumentException $e){if(isset($db)&&$db->inTransaction())$db->rollBack();if(isset($photo['file']))@unlink(ac_media_dir().'/'.$photo['file']);sch_fail($e->getMessage());}
catch(Throwable $e){if(isset($db)&&$db->inTransaction())$db->rollBack();if(isset($photo['file']))@unlink(ac_media_dir().'/'.$photo['file']);error_log('school academics: '.$e->getMessage());sch_fail('Saving could not be confirmed. Keep this form and try again.',500);}
