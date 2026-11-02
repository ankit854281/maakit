<?php
require __DIR__.'/../../config.php';
if(DB_NAME!=='maakit_jaanch')throw new RuntimeException('CI database only');
require __DIR__.'/../../school/inc.php';
require __DIR__.'/../../school/followup_lib.php';
function ffcheck($ok,$why){if(!$ok)throw new RuntimeException('Follow-up flow: '.$why);}
function fftoken($uid,$role,$tid=null,$must=0){
 $db=sch_db();$db->prepare('INSERT INTO sch_users(uid,role,tid,must_change) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE role=VALUES(role),tid=VALUES(tid),must_change=VALUES(must_change)')->execute([$uid,$role,$tid,$must]);
 $tok=bin2hex(random_bytes(32));$db->prepare('INSERT INTO sch_sessions(token_hash,uid,expires_at) VALUES(?,?,DATE_ADD(NOW(),INTERVAL 1 DAY))')->execute([hash('sha256',$tok),$uid]);return $tok;
}
function ffhttp($a,$tok,$body=null){
 $h=['X-School-Token: '.$tok];$opts=[CURLOPT_RETURNTRANSFER=>true,CURLOPT_PROXY=>'',CURLOPT_TIMEOUT=>30];
 if($body!==null){$h[]='Content-Type: application/json';$opts[CURLOPT_POST]=true;$opts[CURLOPT_POSTFIELDS]=json_encode($body);}
 $opts[CURLOPT_HTTPHEADER]=$h;$c=curl_init('http://127.0.0.1:8099/school/followup_api.php?a='.$a);curl_setopt_array($c,$opts);
 $raw=(string)curl_exec($c);$code=curl_getinfo($c,CURLINFO_HTTP_CODE);curl_close($c);
 ffcheck(!preg_match('/Fatal error|Warning:|Notice:|Uncaught|Deprecated:/',$raw),'clean response: '.$raw);
 return [$code,json_decode($raw,true)];
}
$db=sch_db();$old=sch_rec_get('settings','main');$oldFees=sch_rec_get('fees','main');$today=date('Y-m-d');$session=fin_current();$y=(int)substr($session,0,4);
$clean=function()use($db){$db->exec("DELETE FROM sch_records WHERE (k='student' AND r IN ('ff-a','ff-b','ff-c','ff-new','ff-d')) OR (k='regRow' AND r LIKE '%|ff-test-%') OR (k IN ('fdTag') AND r LIKE 'ff-%') OR (k LIKE 'acct%' AND d LIKE '%ff-%') OR (k LIKE 'ofc%' AND d LIKE '%ff-%') OR (k LIKE 'fin%' AND d LIKE '%ff-%')");};
try{
 $clean();sch_rec_put('settings','main',['session'=>$session,'sessionStart'=>$y.'-04-01','school'=>'Synthetic follow-up school'],'principal');
 $P=fftoken('principal','principal');$CH=fftoken('chairman','chairman');$AC=fftoken('accountant','accountant');$RE=fftoken('reception','reception');
 $PA=fftoken('p:9811100011','parent','9811100011');$PB=fftoken('p:9811100022','parent','9811100022');$ST=fftoken('s:ff-a','student','ff-a');$TE=fftoken('ff-teacher','teacher','ff-teacher');$LOCK=fftoken('p:9811100033','parent','9811100033',1);
 foreach([['ff-a','Synthetic Child A','9811100011'],['ff-b','Synthetic Child B','9811100022'],['ff-c','Synthetic Sibling','9811100011']] as [$sid,$name,$phone]){
  sch_rec_put('student',$sid,['id'=>$sid,'adm'=>$sid,'name'=>$name,'cls'=>'2nd','section'=>'A','father'=>'Synthetic Parent','phone'=>$phone],'principal');
  $months=[['','D/A','Fee Name','Amount','Paid/Disc Amount','Balance','N Paid','Status'],[date('F')],['','','Tution Fee','1000','0','1000','','Not Paid']];
  sch_rec_put('regRow','oldfeecard|ff-test-'.$sid,['session'=>$session,'liveId'=>$sid,'oldId'=>$sid,'name'=>$name,'cls'=>'2nd','section'=>'A','months'=>$months,'oldFee'=>'0','transferFee'=>'0','asOn'=>date('d-m-Y'),'nextReceipt'=>'1-901'],'principal');
 }
 sch_rec_put('fdTag','ff-a',['tag'=>'Company','fixed'=>0],'principal');
 [$code]=$q=ffhttp('mine','');ffcheck($code===401,'authentication required');
 [$code]=ffhttp('mine',$LOCK);ffcheck($code===428,'must-change PIN enforced');
 foreach([$PA,$PB,$ST,$TE] as $tok){[$code]=ffhttp('list',$tok);ffcheck($code===403,'only office and chairman see full list');}
 [$code,$mine]=ffhttp('mine',$PA);ffcheck($code===200 && count($mine['children'])===2,'parent sees own children');
 ffcheck(!in_array('ff-b',array_column($mine['children'],'sid'),true),'no other family child');
 ffcheck($mine['children'][0]['due']===100000 && $mine['children'][0]['source']==='copied','dues from fee card in paise');
 [$code,$student]=ffhttp('mine',$ST);ffcheck($code===200 && count($student['children'])===1 && !$student['canPromise'],'student sees only self; parent submits');
 $new=fn($sid='ff-a',$date=null,$amount='500')=>['sid'=>$sid,'promise'=>$date??date('Y-m-d',strtotime('+7 days')),'amount'=>$amount,'note'=>'Synthetic late payment request','nonce'=>bin2hex(random_bytes(16))];
 foreach([$ST,$CH,$AC,$TE] as $tok){[$code]=ffhttp('promise',$tok,$new());ffcheck($code===403,'only parent submits a date');}
 [$code]=ffhttp('promise',$PA,$new('ff-b'));ffcheck($code===403,'cross-family write denied');
 foreach([date('Y-m-d',strtotime('+8 days')),date('Y-m-d',strtotime('-1 day')),'2026-02-30',[],null] as $bad){$v=$new();$v['promise']=$bad;[$code]=ffhttp('promise',$PA,$v);ffcheck($code===422,'invalid or outside-week date rejected');}
 foreach(['0','-1','1000.01','1.001','nan',[],true] as $bad){$v=$new();$v['amount']=$bad;[$code]=ffhttp('promise',$PA,$v);ffcheck($code===422,'invalid or excessive amount rejected');}
 // Existing same-day receipt must not count as payment against a new promise.
 $receipt=function($id,$rupees,$status='Cleared')use($session,$today){sch_rec_put('regRow','oldreceipt|ff-test-'.$id,['session'=>$session,'receiptNo'=>$id,'date'=>date('d-m-Y'),'student'=>'ff-a','name'=>'Synthetic Child A','cls'=>'2nd','total'=>$rupees*100,'discount'=>0,'lateFee'=>0,'received'=>$rupees*100,'mode'=>'Cash','status'=>$status,'user'=>'synthetic'],'principal');};
 $receipt('base',250);
 $request=$new();[$code,$saved]=ffhttp('promise',$PA,$request);ffcheck($code===200 && $saved['promise']['status']==='pending','seven-day inclusive promise saved without fake payment');
 $id=$saved['promise']['id'];[$code,$again]=ffhttp('promise',$PA,$request);ffcheck($code===200 && $again['again'] && $again['promise']['id']===$id,'double submit idempotent');
 foreach([$CH,$AC,$RE] as $tok){[$code,$list]=ffhttp('list',$tok);ffcheck($code===200 && in_array($id,array_column($list['promises'],'id'),true),'staff see submitted date');ffcheck(!isset($list['promises'][0]['uid'],$list['promises'][0]['baseline']),'private baseline not exposed');}
 [$code,$other]=ffhttp('mine',$PB);ffcheck($code===200 && !$other['promises'],'other parent cannot see promises');
 $receipt('part',200);[, $mine]=ffhttp('mine',$PA);ffcheck($mine['promises'][0]['status']==='partial' && $mine['promises'][0]['remaining']===30000,'partial receipts count by amount');
 $receipt('rest',300);[, $mine]=ffhttp('mine',$PA);ffcheck($mine['promises'][0]['status']==='fulfilled','promise fulfilled by recorded amount');
 $receipt('rest',300,'Cancelled');[, $mine]=ffhttp('mine',$PA);ffcheck($mine['promises'][0]['status']==='partial','cancelled receipt no longer fulfils');
 $key=$session.'|ff-a';$record=sch_rec_get('acctPromise',$key);$record['promise']=date('Y-m-d',strtotime('-1 day'));sch_rec_put('acctPromise',$key,$record,'principal');
 [, $mine]=ffhttp('mine',$PA);ffcheck($mine['promises'][0]['status']==='overdue','past incomplete promise overdue');
 [$code,$updated]=ffhttp('promise',$PA,$new('ff-a',$today,'100.25'));ffcheck($code===200&&$updated['promise']['revision']===2,'today allowed, decimal amount exact and revision kept');
 [, $list]=ffhttp('list',$AC);$p=array_values(array_filter($list['promises'],fn($p)=>$p['sid']==='ff-a'))[0];ffcheck(count($p['history'])===1,'old date history visible');
 $attempt=['sid'=>'ff-a','nonce'=>bin2hex(random_bytes(16))];[$code,$started]=ffhttp('attempt',$CH,$attempt);
 ffcheck($code===200 && $started['call']['initiated'] && $started['call']['outcome']==='Call initiated' && !empty($started['call']['autoRemarkHi']),'chairman can record honest dial attempt and Hinglish remark');
 [$code,$again]=ffhttp('attempt',$CH,$attempt);ffcheck($code===200&&$again['call']['id']===$started['call']['id'],'attempt double tap idempotent');
 [$code]=ffhttp('attempt',$PA,$attempt);ffcheck($code===403,'parent cannot log calls');
 $result=['sid'=>'ff-a','id'=>$started['call']['id'],'outcome'=>'Promised to pay','promise'=>$today,'amount'=>'50','note'=>'Synthetic result','nonce'=>bin2hex(random_bytes(16))];
 [$code]=ffhttp('result',$AC,$result);ffcheck($code===403,'another operator cannot alter call result');
 [$code,$done]=ffhttp('result',$CH,$result);ffcheck($code===200&&!$done['call']['initiated']&&$done['call']['amount']===5000&&$done['call']['completedAt'],'actual call result separately saved');
 $result['nonce']=bin2hex(random_bytes(16));[$code]=ffhttp('result',$CH,$result);ffcheck($code===409,'saved result cannot be overwritten');
 $private=[];foreach(['acctPromise','acctPromiseHist','acctCall','acctFollowNonce'] as $k)$private[]=['k'=>$k,'r'=>'test','ver'=>1,'deleted'=>0,'d'=>'{}'];
 ffcheck(sch_rows_to_records($private)===[],'no promises or calls leak through legacy sync');
 $before=sch_rec_get('regRow','oldfeecard|ff-test-ff-a');ffcheck($before['months'][2][4]==='0','promise never changes paid balance or fee card');
 // Same fixed-plan arithmetic as Chairman's kidMonths(): demand every elapsed month.
 sch_rec_put('fdTag','ff-c',['tag'=>'FD','fixed'=>30000],'principal');
 $snapshot=ff_snapshot($db,$session);$monthsElapsed=date('n')>=4?(int)date('n')-3:(int)date('n')+9;
 ffcheck($snapshot['ff-c']['due']===30000*$monthsElapsed,'fixed plan matches chairman');
 // Keep pre-existing Fee Desk accounts usable when there is no imported card.
 sch_rec_put('student','ff-new',['id'=>'ff-new','name'=>'Synthetic New','cls'=>'2nd','phone'=>'9811100022','km'=>5],'principal');
 sch_rec_put('fees','main',['monthly'=>['2nd'=>1000],'yearly'=>[],'transport'=>[['upto'=>10,'amt'=>100]],'thirdChildPct'=>50],'principal');
 sch_rec_put('acctPay','ff-legacy-payment',['sid'=>'ff-new','no'=>1,'date'=>$today,'amt'=>200],'principal');
 $snapshot=ff_snapshot($db,$session);ffcheck($snapshot['ff-new']['source']==='legacy' && $snapshot['ff-new']['due']===($monthsElapsed*1100-200)*100,'legacy fee desk includes bus and existing receipt');
 // An active detailed account without an imported old card is still visible.
 sch_rec_put('student','ff-d',['id'=>'ff-d','name'=>'Synthetic Detailed','cls'=>'2nd','phone'=>'9811100022'],'principal');
 sch_rec_put('finAccount',$session.'|ff-d',['sid'=>'ff-d','session'=>$session,'active'=>true],'principal');
 sch_rec_put('finBill','ff-detail-bill',['id'=>'ff-detail-bill','sid'=>'ff-d','session'=>$session,'date'=>$today,'dueDate'=>$today,'period'=>substr($today,0,7),'amount'=>50000],'principal');
 sch_rec_put('finEntry','ff-detail-receipt',['id'=>'ff-detail-receipt','sid'=>'ff-d','session'=>$session,'date'=>$today,'kind'=>'Receipt','status'=>'Cleared','amount'=>10000,'number'=>'SYNTHETIC','allocations'=>['ff-detail-bill'=>10000],'discounts'=>[]],'principal');
 $snapshot=ff_snapshot($db,$session);ffcheck($snapshot['ff-d']['source']==='detailed' && $snapshot['ff-d']['due']===40000 && count($snapshot['ff-d']['receipts'])===1,'detailed account without card and cleared receipt');
 echo "Follow-ups: own-family access, parent-only writes, 7-day bounds, amount checks, retries, history, partial/cancelled payments, calling permissions and private sync passed\n";
}finally{
 $clean();$db->exec("DELETE FROM sch_sessions WHERE uid IN ('ff-teacher','p:9811100011','p:9811100022','p:9811100033','s:ff-a')");
 if($old)sch_rec_put('settings','main',$old,'principal');
 if($oldFees)sch_rec_put('fees','main',$oldFees,'principal');else $db->exec("DELETE FROM sch_records WHERE k='fees' AND r='main'");
}
