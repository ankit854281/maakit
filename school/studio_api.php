<?php
declare(strict_types=1);
require __DIR__.'/inc.php';require __DIR__.'/learning_lib.php';require __DIR__.'/studio_lib.php';
try {
 $db=sch_db();$u=sch_auth();$action=(string)($_GET['a']??'state');$session=learn_session();$role=$u['role'];$uid=(string)$u['uid'];$now=sch_now_ms();
 if(!in_array($role,['principal','teacher','parent','chairman'],true))sch_fail('Not allowed.',403);
 $students=learn_rows('student');$kids=$role==='parent'?sch_children((string)$u['tid']):array_values(array_filter($students,fn($s)=>studio_staff($u,(string)($s['cls']??''))||$role==='chairman'));$sids=array_column($kids,'id');$classes=array_unique(array_column($kids,'cls'));
 $assignments=array_filter(learn_rows('studioTask'),fn($s)=>($s['session']??'')===$session&&($role==='parent'?in_array($s['cls'],$classes,true):learn_allowed($u,$s['cls'],$s['subj'])));
 $subs=array_filter(learn_rows('studioCopy'),fn($s)=>($s['session']??'')===$session&&studio_visible_submission($u,$s,$sids));
 $posts=array_filter(learn_rows('studioPost'),fn($s)=>($s['session']??'')===$session&&($role==='parent'?in_array($s['cls'],$classes,true):studio_staff($u,$s['cls'])));
 $badges=array_filter(learn_rows('studioBadge'),fn($s)=>($s['session']??'')===$session&&($role==='parent'?in_array($s['sid'],$sids,true):studio_staff($u,$s['cls'])));
 $messages=array_filter(learn_rows('studioMsg'),fn($m)=>($m['session']??'')===$session&&($role==='parent'?($m['parent']??'')===$uid:($role==='principal'||($role==='teacher'&&($m['tid']??'')===$u['tid']&&(($m['sender']??'')===$uid||($m['deliverAt']??0)<=$now)))));
 $houses=learn_rows('studioHouse');$quiet=studio_quiet();$preferences=learn_rows('studioPref');
 if($action==='state'){
  $safeTasks=[];foreach($assignments as $id=>$a){if($role!=='parent')$a['key']=sch_rec_get('studioKey',$id);$safeTasks[]=$a;}
  $safeSubs=[];foreach($subs as $s){if($role==='parent')unset($s['draft'],$s['aiError'],$s['processing'],$s['actor']);$safeSubs[]=$s;}
  $reactions=learn_rows('studioReact');$safePosts=[];foreach($posts as $p){$p['counts']=['applaud'=>0,'love'=>0];$p['mine']='';foreach($reactions as $r)if(($r['post']??'')===$p['id']&&isset($p['counts'][$r['type']??''])){$p['counts'][$r['type']]++;if(($r['actor']??'')===$uid)$p['mine']=$r['type'];}$safePosts[]=$p;}
  $totals=array_fill_keys(STUDIO_HOUSES,0);foreach(learn_rows('studioBadge') as $b)if(($b['session']??'')===$session&&in_array($b['house']??'',STUDIO_HOUSES,true))$totals[$b['house']]+=(int)$b['points'];
  $alloc=(array)sch_rec_get('alloc','main');$contacts=[];foreach($kids as $s){$teachers=[];foreach($alloc as $a)if(($a['c']??'')===$s['cls'])$teachers[$a['t']]=sch_teacher_name($a['t']);$contacts[$s['id']]=$teachers;}
  if($role==='teacher')$alloc=array_values(array_filter($alloc,fn($a)=>($a['t']??'')===$u['tid']));if($role==='parent')$alloc=[];
  sch_json(['ok'=>true,'role'=>$role,'session'=>$session,'quiet'=>$quiet,'waReady'=>(bool)studio_wa_ready(),'imageRendererReady'=>function_exists('imagecreatefromstring'),'preferences'=>array_values(array_filter($preferences,fn($p)=>in_array($p['sid']??'',$sids,true)&&($p['actor']??'')===$uid)),'aiReady'=>(bool)(learn_ai_secret('SCHOOL_AI_API_KEY')&&(learn_ai_secret('SCHOOL_AI_VISION_MODEL')||learn_ai_secret('SCHOOL_AI_MODEL'))),'tasks'=>$safeTasks,'copies'=>$safeSubs,'posts'=>array_values($safePosts),'badges'=>array_values($badges),'messages'=>array_values($messages),'students'=>array_map(fn($s)=>['id'=>$s['id'],'name'=>$s['name'],'cls'=>$s['cls'],'adm'=>$s['adm']??'','house'=>$houses[$s['id']]['house']??''],$kids),'alloc'=>$alloc,'contacts'=>$contacts,'houses'=>$totals,'badgeTypes'=>STUDIO_BADGES]);
 }
 if($action==='media'){
  $id=(string)($_GET['id']??'');$m=sch_rec_get('studioMedia',$id);if(!$m||($m['session']??'')!==$session)sch_fail('Media not found.',404);$allow=($m['actor']??'')===$uid;
  foreach($posts as $p)if(in_array($id,$p['media']??[],true))$allow=true;
  foreach($subs as $s)if(in_array($id,array_merge($s['pages']??[],$s['prepared']??[],$s['checkedPages']??[]),true))$allow=true;
  if(!$allow)sch_fail('This media belongs to another class or child.',403);
  $file=studio_media_dir().'/'.$m['file'];if(!is_file($file))sch_fail('Media is unavailable.',404);header('Content-Type: '.$m['mime']);header('X-Content-Type-Options: nosniff');header('Cache-Control: private, no-store');header("Content-Security-Policy: default-src 'none'; sandbox");header('Content-Length: '.filesize($file));readfile($file);exit;
 }
 if(($_SERVER['REQUEST_METHOD']??'')!=='POST')sch_fail('POST only',405);
 if($action==='upload'){
  if(!in_array($role,['parent','teacher','principal'],true))sch_fail('Upload not allowed.',403);$f=$_FILES['file']??null;if(!$f||$f['error']!==UPLOAD_ERR_OK||!is_uploaded_file($f['tmp_name']))sch_fail('Choose an image or a short video.');
  $mime=(new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);$types=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','video/mp4'=>'mp4','video/webm'=>'webm'];if(!isset($types[$mime])||($role==='parent'&&!str_starts_with($mime,'image/')))sch_fail('Use JPG, PNG or WebP for copies. Teachers can also post MP4/WebM.');
  $limit=str_starts_with($mime,'image/')?8*1024*1024:25*1024*1024;if($f['size']<1||$f['size']>$limit)sch_fail('Image limit: 8 MB. Video limit: 25 MB.');if(str_starts_with($mime,'image/')){$size=getimagesize($f['tmp_name']);if(!$size||$size[0]*$size[1]>25000000)sch_fail('Choose an image below 25 megapixels.');}
  $db->beginTransaction();$db->query("SELECT uid FROM sch_users WHERE uid='principal' FOR UPDATE")->fetch();$bytes=0;foreach(learn_rows('studioMedia') as $v)if(($v['actor']??'')===$uid&&($v['at']??0)>$now-86400000)$bytes+=(int)($v['bytes']??0);if($bytes+$f['size']>150*1024*1024)sch_fail('Daily upload limit reached. Ask the office for help.',429);
  $id=bin2hex(random_bytes(16));$name=$id.'.'.$types[$mime];if(!move_uploaded_file($f['tmp_name'],studio_media_dir().'/'.$name))throw new RuntimeException('Upload failed');chmod(studio_media_dir().'/'.$name,0600);sch_rec_put('studioMedia',$id,['id'=>$id,'file'=>$name,'mime'=>$mime,'bytes'=>$f['size'],'actor'=>$uid,'session'=>$session,'at'=>$now],$uid);$db->commit();sch_json(['ok'=>true,'id'=>$id,'mime'=>$mime]);
 }
 $in=json_decode(file_get_contents('php://input',false,null,0,250000)?:'',true);if(!is_array($in))sch_fail('Invalid form.');
 $id=(string)($in['id']??'');if(!preg_match('/^[a-f0-9-]{36}$/',$id))sch_fail('Refresh the form and try again.');
 $media=function($ids,bool $imageOnly=true)use($uid,$session){if(!is_array($ids)||!array_is_list($ids)||count($ids)>5)throw new InvalidArgumentException('Choose up to five media files.');foreach($ids as $i){$m=is_string($i)?sch_rec_get('studioMedia',$i):null;if(!$m||$m['actor']!==$uid||$m['session']!==$session||($imageOnly&&!str_starts_with($m['mime'],'image/')))throw new InvalidArgumentException('Choose your own uploaded images.');}return $ids;};
 $db->beginTransaction();$db->query("SELECT uid FROM sch_users WHERE uid='principal' FOR UPDATE")->fetch();
 if($action==='task'){
  $cls=learn_string($in['cls']??'',20);$subj=learn_string($in['subj']??'',100);if(!learn_allowed($u,$cls,$subj))sch_fail('Choose your assigned subject.',403);if(($in['reviewed']??false)!==true)sch_fail('Check the answer key and rubric first.');$max=studio_marks($in['max']??null,1000);if($max<=0)sch_fail('Maximum marks must be positive.');
  $kind='studioTask';$d=['id'=>$id,'cls'=>$cls,'subj'=>$subj,'title'=>learn_string($in['title']??'',200),'instructions'=>learn_string($in['instructions']??'',3000),'due'=>studio_date((string)($in['due']??'')),'max'=>$max,'work'=>in_array($in['work']??'',['Homework','Classwork'],true)?$in['work']:'Homework','teacher'=>$u['label'],'tid'=>$u['tid']];$private=['answer'=>learn_string($in['answer']??'',14000),'rubric'=>learn_string($in['rubric']??'',4000)];
 }elseif($action==='submit'){
  $task=$assignments[(string)($in['task']??'')]??null;$sid=(string)($in['sid']??'');$s=$students[$sid]??null;if(!$task||!$s||$s['cls']!==$task['cls']||($role==='parent'?!in_array($sid,$sids,true):!learn_allowed($u,$task['cls'],$task['subj'])))sch_fail('Choose work assigned to this child.',403);
  $pages=$media($in['pages']??[]);if(!$pages)sch_fail('Add a notebook image.');$prepared=$media($in['prepared']??$pages);if(count($prepared)!==count($pages))sch_fail('Save each prepared page.');$kind='studioCopy';$d=['id'=>$id,'task'=>$task['id'],'sid'=>$sid,'cls'=>$task['cls'],'subj'=>$task['subj'],'pages'=>$pages,'prepared'=>$prepared,'status'=>'Submitted'];
 }elseif($action==='post'){
  $cls=learn_string($in['cls']??'',20);if(!studio_staff($u,$cls))sch_fail('Choose your own class.',403);if(($in['permission']??false)!==true)sch_fail('Confirm school permission for the people in this post.');$kind='studioPost';$d=['id'=>$id,'cls'=>$cls,'caption'=>learn_string($in['caption']??'',2000),'media'=>$media($in['media']??[],false),'teacher'=>$u['label']];
 }elseif($action==='react'){
  if($role!=='parent')sch_fail('Parent login required.',403);$post=(string)($in['post']??'');if(!isset($posts[$post])||!in_array($in['type']??'',['applaud','love'],true))sch_fail('Choose a post in your child’s class.',403);$id=hash('sha256',$uid.'|'.$post);$kind='studioReact';$d=['id'=>$id,'post'=>$post,'type'=>$in['type']];
 }elseif($action==='preference'){
  $sid=(string)($in['sid']??'');if($role!=='parent'||!in_array($sid,$sids,true)||!is_bool($in['whatsapp']??null))sch_fail('Only the linked parent can change WhatsApp permission.',403);$id=hash('sha256',$uid.'|'.$sid);$kind='studioPref';$d=['id'=>$id,'sid'=>$sid,'whatsapp'=>$in['whatsapp']];
 }elseif($action==='house'){
  if($role!=='principal')sch_fail('Only the Principal assigns houses.',403);$sid=(string)($in['sid']??'');if(!isset($students[$sid])||!in_array($in['house']??'',STUDIO_HOUSES,true))sch_fail('Choose a student and house.');$id=$sid;$kind='studioHouse';$d=['id'=>$id,'house'=>$in['house']];
 }elseif($action==='badge'){
  $sid=(string)($in['sid']??'');$s=$students[$sid]??null;$badge=(string)($in['badge']??'');if(!$s||!studio_staff($u,$s['cls'])||!isset(STUDIO_BADGES[$badge]))sch_fail('Choose a child in your class and a badge.',403);
  foreach(learn_rows('studioBadge') as $b)if(($b['session']??'')===$session&&$b['id']!==$id&&$b['sid']===$sid&&$b['badge']===$badge&&substr($b['date'],0,10)===date('Y-m-d'))sch_fail('This child already received this badge today.',409);
  $kind='studioBadge';$d=['id'=>$id,'sid'=>$sid,'cls'=>$s['cls'],'badge'=>$badge,'title'=>STUDIO_BADGES[$badge][0],'points'=>STUDIO_BADGES[$badge][1],'house'=>sch_rec_get('studioHouse',$sid)['house']??'','reason'=>learn_string($in['reason']??'',500),'teacher'=>$u['label'],'date'=>date('Y-m-d')];
 }elseif($action==='message'){
  $sid=(string)($in['sid']??'');$s=$students[$sid]??null;$tid=(string)($in['tid']??'');if(!$s||!sch_in_class($tid,$s['cls']))sch_fail('Choose a teacher assigned to this child.',403);$parent='p:'.sch_phone10((string)($s['phone']??''));if(strlen($parent)!==12)sch_fail('The child needs a verified parent mobile in Students.');
  if($role==='parent'&&!in_array($sid,$sids,true))sch_fail('Choose your own child.',403);if(!in_array($role,['principal','parent','teacher'],true)||($role==='teacher'&&$tid!==$u['tid']))sch_fail('This conversation is private.',403);
  $kind='studioMsg';$d=['id'=>$id,'sid'=>$sid,'cls'=>$s['cls'],'tid'=>$tid,'parent'=>$parent,'sender'=>$uid,'from'=>$role==='parent'?'Parent':$u['label'],'text'=>learn_string($in['text']??'',2000),'deliverAt'=>$role==='parent'?$quiet['deliverAt']:$now];
 }elseif(in_array($action,['ai','review'],true)){
  $copy=$subs[(string)($in['copy']??'')]??null;if(!$copy||!learn_allowed($u,$copy['cls'],$copy['subj']))sch_fail('Choose a submission from your subject.',403);$copy=sch_rec_get('studioCopy',$copy['id']);$task=sch_rec_get('studioTask',$copy['task']);if(($copy['status']??'')==='Approved')sch_fail('This copy is already approved. Submit a new attempt for further checking.',409);
  if($action==='ai'){
   if(($in['consent']??false)!==true)sch_fail('Confirm sending the prepared notebook images and answer key to OpenAI.');if(!learn_ai_secret('SCHOOL_AI_API_KEY')||!(learn_ai_secret('SCHOOL_AI_VISION_MODEL')||learn_ai_secret('SCHOOL_AI_MODEL')))sch_fail('AI Vision is not connected. Use teacher review.',503);
   if(($copy['processing']??0)>$now-120000)sch_fail('This copy is already being checked.',409);$q=$db->prepare("SELECT COUNT(*) FROM sch_log WHERE k='Studio AI' AND uname=? AND ts>?");$q->execute([$u['label'],$now-3600000]);if((int)$q->fetchColumn()>=10)sch_fail('Hourly AI check limit reached.',429);
   $copy['processing']=$now;sch_rec_put('studioCopy',$copy['id'],$copy,$uid);sch_log($u,'Studio AI','Requested draft for '.$copy['id']);$db->commit();
   try{$draft=studio_ai($task,sch_rec_get('studioKey',$task['id'])??[],array_map(fn($i)=>sch_rec_get('studioMedia',$i),$copy['prepared']));}catch(Throwable $e){$db->beginTransaction();$db->query("SELECT uid FROM sch_users WHERE uid='principal' FOR UPDATE")->fetch();$latest=sch_rec_get('studioCopy',$copy['id']);unset($latest['processing']);sch_rec_put('studioCopy',$copy['id'],$latest,$uid);$db->commit();sch_fail('AI could not finish this draft. No marks were published. Use teacher review or retry.',503);}
   $db->beginTransaction();$db->query("SELECT uid FROM sch_users WHERE uid='principal' FOR UPDATE")->fetch();$copy=sch_rec_get('studioCopy',$copy['id']);unset($copy['processing']);if($copy['status']!=='Approved'){$copy['draft']=$draft;$copy['status']='AI draft';sch_rec_put('studioCopy',$copy['id'],$copy,$uid);}$db->commit();sch_json(['ok'=>true]);
  }
  if(($in['approved']??false)!==true)sch_fail('Review every mark and annotation before approval.');$copy['review']=['marks'=>studio_marks($in['marks']??null,(float)$task['max']),'max'=>$task['max'],'feedback'=>learn_string($in['feedback']??'',6000),'annotations'=>studio_annotations($in['annotations']??[],count($copy['prepared'])),'teacher'=>$u['label'],'at'=>$now];$copy['checkedPages']=studio_checked_images($copy,$copy['review'],$uid,$session);$copy['status']='Approved';unset($copy['processing']);sch_rec_put('studioCopy',$copy['id'],$copy,$uid);sch_log($u,'Studio Review','Approved notebook '.$copy['id']);$db->commit();
  $student=$students[$copy['sid']]??[];$parent='p:'.sch_phone10((string)($student['phone']??''));$pref=sch_rec_get('studioPref',hash('sha256',$parent.'|'.$copy['sid']));
  if(($in['whatsapp']??false)===true&&($pref['whatsapp']??false)===true){
   // Approval is committed first. A timeout must not trigger a second message on retry.
   $copy['whatsapp']=['status'=>'Sending — outcome not confirmed'];sch_rec_put('studioCopy',$copy['id'],$copy,$uid);
   try{$copy['whatsapp']=studio_whatsapp($copy,$student,$task);}catch(Throwable $e){$copy['whatsapp']=['status'=>'Not confirmed — do not automatically retry'];}
   sch_rec_put('studioCopy',$copy['id'],$copy,$uid);
  }else{$copy['whatsapp']=['status'=>($in['whatsapp']??false)?'Parent has not opted in':'Not requested'];sch_rec_put('studioCopy',$copy['id'],$copy,$uid);}
  sch_json(['ok'=>true,'whatsapp'=>$copy['whatsapp']['status']]);
 }elseif($action==='removePost'){
  $p=$posts[(string)($in['post']??'')]??null;if(!$p||!($role==='principal'||($role==='teacher'&&($p['actor']??'')===$uid)))sch_fail('Only the author or Principal can remove a post.',403);sch_rec_del('studioPost',$p['id'],$uid);sch_log($u,'Studio','Removed classroom post '.$p['id']);$db->commit();sch_json(['ok'=>true]);
 }else sch_fail('Unknown action.',404);
 $old=sch_rec_get($kind,$id);if($old&&!in_array($kind,['studioReact','studioHouse','studioPref'],true)){$db->rollBack();if(($old['actor']??'')!==$uid)sch_fail('Record conflict.',409);sch_json(['ok'=>true,'duplicate'=>true]);}
 $d['session']=$session;$d['actor']=$uid;$d['at']=$now;if($kind==='studioTask')sch_rec_put('studioKey',$id,$private,$uid);sch_rec_put($kind,$id,$d,$uid);sch_log($u,'Studio',$kind.' saved '.$id);$db->commit();sch_json(['ok'=>true]);
}catch(InvalidArgumentException $e){if(isset($db)&&$db->inTransaction())$db->rollBack();sch_fail($e->getMessage());}catch(Throwable $e){if(isset($db)&&$db->inTransaction())$db->rollBack();error_log('school studio: '.$e->getMessage());sch_fail('Could not confirm saving. Keep this form and try again.',500);}
