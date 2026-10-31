<?php
// Full HTTP authorization + persistence checks, synthetic children only. Restores all fixtures.
require __DIR__.'/../../config.php';if(DB_NAME!=='maakit_jaanch')throw new RuntimeException('CI database only');
require __DIR__.'/../../school/inc.php';require __DIR__.'/../../school/academics_lib.php';
require __DIR__.'/../../school/academics_rounds.php';
$db=sch_db();$tables=['sch_sessions','sch_users','sch_records','sch_log','sch_login_fail'];$before=[];foreach($tables as $table)$before[$table]=$db->query('SELECT * FROM '.$table)->fetchAll();
function af_check($yes,$why){if(!$yes)throw new RuntimeException('Academics flow: '.$why);}
function af_token(string $uid,string $role,string $tid): string {$db=sch_db();$db->prepare('INSERT INTO sch_users(uid,role,tid,must_change) VALUES(?,?,?,0)')->execute([$uid,$role,$tid]);$token=bin2hex(random_bytes(32));$db->prepare('INSERT INTO sch_sessions(token_hash,uid,expires_at) VALUES(?,?,DATE_ADD(NOW(),INTERVAL 1 DAY))')->execute([hash('sha256',$token),$uid]);return $token;}
function af_http(string $action,string $token,$body=null,string $file='academics_api'): array {
    $c=curl_init('http://127.0.0.1:8099/school/'.$file.'.php?a='.$action);$headers=['X-School-Token: '.$token];$opts=[CURLOPT_RETURNTRANSFER=>true,CURLOPT_PROXY=>'',CURLOPT_TIMEOUT=>30];if($body!==null){$headers[]='Content-Type: application/json';$opts[CURLOPT_POST]=true;$opts[CURLOPT_POSTFIELDS]=json_encode($body);}$opts[CURLOPT_HTTPHEADER]=$headers;curl_setopt_array($c,$opts);$raw=(string)curl_exec($c);$status=curl_getinfo($c,CURLINFO_HTTP_CODE);curl_close($c);af_check(!preg_match('/Fatal error|Warning:|Uncaught|Deprecated/',$raw),'PHP warning at '.$action.': '.substr($raw,0,350));return [$status,json_decode($raw,true),$raw];
}
try{
    foreach($tables as $table)$db->exec('DELETE FROM '.$table);
    $today=new DateTimeImmutable('today');$date=$today->modify('-'.((int)$today->format('N')-1).' days')->format('Y-m-d');$year=(int)$today->format('Y')-((int)$today->format('n')<4?1:0);$session=$year.'-'.substr((string)($year+1),2);
    sch_rec_put('settings','main',['session'=>$session,'school'=>'Synthetic Academics School'],'principal');
    sch_rec_put('alloc','main',[['c'=>'UKG','s'=>'English','t'=>'AC1'],['c'=>'1st','s'=>'Maths','t'=>'AC2']],'principal');sch_rec_put('classes','main',[['c'=>'UKG','ct'=>'AC1'],['c'=>'1st','ct'=>'AC2']],'principal');
    sch_rec_put('grid','main',['UKG'=>array_fill(0,6,array_fill(0,7,0)),'1st'=>array_fill(0,6,array_fill(0,7,1))],'principal');sch_rec_put('exams','main',[],'principal');
    foreach(['AC1','AC2'] as $tid)sch_rec_put('teacher',$tid,['name'=>'Synthetic '.$tid],'principal');
    foreach([['ac-one','UKG','9111111111'],['ac-absent','UKG','9111111111'],['ac-other','UKG','9222222222'],['ac-foreign','1st','9333333333']] as [$id,$cls,$phone])sch_rec_put('student',$id,['id'=>$id,'name'=>'Synthetic '.$id,'cls'=>$cls,'phone'=>$phone,'adm'=>$id],'principal');
    $P=af_token('principal','principal','P');$T=af_token('t:AC1','teacher','AC1');$X=af_token('t:AC2','teacher','AC2');$A=af_token('p:9111111111','parent','9111111111');$B=af_token('p:9222222222','parent','9222222222');$C=af_token('chairman','chairman','');
    [$code]=af_http('state','bad');af_check($code===401,'Missing auth accepted');
    $good=['hw'=>'done','copy'=>'checked','oral'=>'done','book'=>'brought'];$bad=$good;$bad['hw']='not done';$track=['cls'=>'UKG','subj'=>'English','date'=>$date,'task'=>'Synthetic task','confirmed'=>true,'rev'=>0,'students'=>['ac-one'=>$bad,'ac-other'=>$good]];
    [$code]=af_http('track',$T,$track);af_check($code===400,'Missing attendance accepted');
    sch_rec_put('studAtt',$date.'|UKG',['absent'=>['ac-absent']],'principal');
    [$code]=af_http('track',$X,$track);af_check($code===403,'Other subject teacher wrote checks');[$code]=af_http('track',$A,$track);af_check($code===403,'Parent wrote checks');[$code]=af_http('track',$C,$track);af_check($code===403,'Chairman wrote checks');
    $withAbsent=$track;$withAbsent['students']['ac-absent']=$good;[$code]=af_http('track',$T,$withAbsent);af_check($code===400,'Absent child marked done');
    $foreign=$track;$foreign['students']['ac-foreign']=$good;[$code]=af_http('track',$T,$foreign);af_check($code===400,'Foreign class accepted');
    [$code,$result]=af_http('track',$T,$track);af_check($code===200,'Valid tracking save: '.json_encode($result));$rev=$result['record']['rev'];
    [$code]=af_http('track',$T,$track);af_check($code===400,'Stale revision overwrote checks');
    [$code,$result,$raw]=af_http('state',$A);af_check($code===200&&count($result['tracking'])===1&&$result['tracking'][0]['sid']==='ac-one'&&!str_contains($raw,'ac-other'),'Parent received another child');
    [$code,$result,$raw]=af_http('state',$B);af_check($code===200&&count($result['tracking'])===1&&$result['tracking'][0]['sid']==='ac-other'&&!str_contains($raw,'ac-one'),'Same-class sibling isolation failed');
    [$code,$result,$raw]=af_http('pdata',$A,null,'api');af_check($code===200&&count($result['academics']['pending'])===1&&!str_contains(json_encode($result['academics']),'ac-other'),'Parent pdata not scoped');
    [$code,,$raw]=af_http('load',$T,null,'api');af_check($code===200&&!str_contains($raw,'Synthetic task'),'Legacy load leaked ac records');
    [$code]=af_http('resolve',$T,['cls'=>'UKG','subj'=>'English','origin'=>$date.'|UKG|English','sid'=>'ac-one']);af_check($code===200,'Homework resolution failed');[$code,$result]=af_http('state',$A);af_check($result['pending']===[],'Resolved homework still pending');
    [$code]=af_http('oral',$T,['cls'=>'UKG','subj'=>'English','rev'=>0,'cells'=>['ac-one'=>['letter-A'=>'done']]]);af_check($code===200,'Oral board save');
    sch_rec_put('diary',$date.'|AC1|0',['cls'=>'UKG','subj'=>'English','topic'=>'Synthetic taught page','hw'=>'Synthetic revision','hwMinutes'=>5,'hwDate'=>$date],'principal');
    [$code,$result]=af_http('state',$A);af_check(count($result['catchup'])===1&&$result['catchup'][0]['sid']==='ac-absent','Absent catch-up not derived');$catch=$result['catchup'][0];
    [$code]=af_http('catchup',$X,['cls'=>'UKG','subj'=>'English','id'=>$catch['id']]);af_check($code===403,'Other teacher resolved catch-up');[$code]=af_http('catchup',$T,['cls'=>'UKG','subj'=>'English','id'=>$catch['id']]);af_check($code===200,'Catch-up save');
    $day=['date'=>$date,'topic'=>'Synthetic planned page','classwork'=>'Synthetic classwork','hw'=>'Synthetic homework','hwMinutes'=>5,'books'=>'Synthetic book','revision'=>'Letter A'];$body=['cls'=>'UKG','subj'=>'English','days'=>[$day],'reviewed'=>false,'expected'=>[]];
    [$code]=af_http('plan',$T,$body);af_check($code===400,'Unreviewed draft saved');$body['reviewed']=true;[$code]=af_http('plan',$T,$body);af_check($code===200,'Reviewed plan not saved');$lpKey=ac_week($date).'|AC1|UKG|English';$plan=sch_rec_get('lplan',$lpKey);af_check($plan['status']==='Submitted','AI plan auto-approved');$legacy=$plan;unset($legacy['days']);$legacy['topics']='Stale weekly overwrite';
    foreach([$T,$P] as $token){[$code,$result]=af_http('sync',$token,['put'=>[['k'=>'lplan','r'=>$lpKey,'d'=>$legacy]]],'api');af_check($code===200&&count($result['rejected'])===1&&sch_rec_get('lplan',$lpKey)===$plan,'Legacy sync overwrote daily details');}
    $plan['status']='Approved';[$code,$result]=af_http('sync',$P,['put'=>[['k'=>'lplan','r'=>$lpKey,'d'=>$plan]]],'api');af_check($code===200&&count($result['done'])===1&&sch_rec_get('lplan',$lpKey)['days']===$plan['days'],'Principal approval lost days');
    [$code,$result]=af_http('state&date='.$date,$T);af_check($code===200&&($result['bells'][0]['plan']['topic']??'')==='Synthetic planned page','Approved bell plan unavailable');
    [$code]=af_http('duty',$T,['date'=>$date,'tids'=>['AC1'],'target'=>1,'rev'=>0]);af_check($code===403,'Teacher assigned duty');[$code]=af_http('duty',$P,['date'=>$date,'tids'=>['AC1'],'target'=>1,'rev'=>0]);af_check($code===200,'Principal duty assignment');
    $img=imagecreatetruecolor(4,4);ob_start();imagejpeg($img);$photo='data:image/jpeg;base64,'.base64_encode(ob_get_clean());imagedestroy($img);
    $round=['date'=>$date,'id'=>'00000000-0000-4000-8000-000000000001','checks'=>array_fill_keys(['gate','corridors','toilets','classes','discipline'],'ok'),'time'=>'00:00','remarks'=>'Synthetic round','rev'=>0,'photo'=>$photo];$round['checks']['gate']='issue';
    [$code]=af_http('round',$X,$round);af_check($code===400,'Unassigned teacher round accepted');[$code]=af_http('round',$T,$round);af_check($code===200,'Assigned round save');[$code,$result]=af_http('round',$T,$round);af_check($code===200&&!empty($result['duplicate']),'Round retry duplicated');
    $storedPhoto=sch_rec_get('acRound',$date.'|AC1')['rounds'][0]['photo'];[$code]=af_http('roundPhoto&id='.$storedPhoto['id'],$A);af_check($code===403,'Parent saw private duty photo');[$code]=af_http('roundPhoto&id='.$storedPhoto['id'],$X);af_check($code===404,'Another teacher saw private duty photo');[$code]=af_http('roundPhoto&id='.$storedPhoto['id'],$T);af_check($code===200,'Duty teacher cannot read private photo');
    $fix=['date'=>$date,'tid'=>'AC1','id'=>$round['id'],'note'=>'Synthetic issue resolved'];[$code]=af_http('roundFix',$T,$fix);af_check($code===403,'Teacher closed Principal duty issue');[$code]=af_http('roundFix',$P,$fix);af_check($code===200,'Principal issue resolution failed');
    [$code]=af_http('report&date='.$date,$A);af_check($code===403,'Parent saw staff report');[$code,$result,$raw]=af_http('report&date='.$date,$T);af_check($code===200&&!str_contains($raw,'Synthetic AC2'),'Teacher saw another teacher report');
    [$code,$result]=af_http('report&date='.$date,$P);af_check($code===200&&count($result['report'])>=1,'Principal report unavailable');
    echo "Academics full HTTP flow: scoped roles, attendance, conflicts, parents, legacy isolation, homework, oral, catch-up, reviewed plans, duty and daily reports passed\n";
}finally{
    if(isset($storedPhoto['file']))@unlink(ac_media_dir().'/'.$storedPhoto['file']);
    foreach($tables as $table){$db->exec('DELETE FROM '.$table);foreach($before[$table] as $row){$columns=array_keys($row);$sql='INSERT INTO '.$table.' (`'.implode('`,`',$columns).'`) VALUES ('.implode(',',array_fill(0,count($columns),'?')).')';$db->prepare($sql)->execute(array_values($row));}}
}
