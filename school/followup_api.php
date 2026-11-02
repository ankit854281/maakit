<?php
declare(strict_types=1);
require __DIR__.'/inc.php';
require __DIR__.'/followup_lib.php';
header('X-Frame-Options: SAMEORIGIN');
$u=sch_auth();$role=(string)$u['role'];$staff=in_array($role,['principal','chairman','accountant','reception'],true);
$a=(string)($_GET['a']??'mine');$method=$_SERVER['REQUEST_METHOD']??'GET';
$db=sch_db();$session=ff_session();$uid=sch_uid_of($u);$in=[];
try {
    if($method==='POST'){
        $raw=file_get_contents('php://input',false,null,0,12000);$in=json_decode((string)$raw,true);
        if(!is_array($in))throw new DomainException('Bad request.');
    }
    if($a==='mine' || $a==='list'){
        if($method!=='GET')sch_fail('GET only.',405);
        if($a==='list'&&!$staff)sch_fail('Not allowed.',403);
        if($a==='mine'&&!in_array($role,['parent','student'],true))sch_fail('Not allowed.',403);
        $snapshot=ff_snapshot($db,$session);$ids=$staff?array_keys($snapshot):ff_owned($u);
        $children=[];foreach($ids as $sid){
            if(isset($snapshot[$sid]))$children[]=ff_public_child($snapshot[$sid]);
            elseif(!$staff && ($s=sch_rec_get('student',$sid)))$children[]=['sid'=>$sid,'name'=>(string)$s['name'],'cls'=>(string)$s['cls'],'tag'=>'','due'=>null,'overallDue'=>null,'source'=>'unavailable','asOn'=>''];
        }
        $promises=[];foreach(ff_rows('acctPromise') as $p)if(($p['session']??'')===$session && in_array((string)$p['sid'],$ids,true))$promises[]=ff_promise($p,$snapshot[$p['sid']]??null);
        $history=ff_rows('acctPromiseHist');
        foreach($promises as &$p){$prior=array_values(array_filter($history,fn($h)=>($h['session']??'')===$session && ($h['sid']??'')===$p['sid'] && $h['id']!==$p['id']));usort($prior,fn($x,$y)=>$y['at']<=>$x['at']);$p['history']=array_map(fn($h)=>array_intersect_key($h,array_flip(['promise','amount','note','at'])),array_slice($prior,0,10));}unset($p);
        usort($promises,fn($x,$y)=>[$x['promise'],-$x['at']]<=>[$y['promise'],-$y['at']]);
        $receipts=[];$calls=[];
        if(!$staff)foreach($ids as $sid)foreach($snapshot[$sid]['receipts']??[] as $r)$receipts[]=['sid'=>$sid,'name'=>$snapshot[$sid]['name'],'no'=>(string)$r['no'],'date'=>(string)$r['date'],'amount'=>(int)round($r['received']*100)];
        if($staff){
            $calls=array_values(array_filter(ff_rows('acctCall'),fn($c)=>($c['session']??$session)===$session));
            usort($calls,fn($x,$y)=>($y['at']??0)<=>($x['at']??0));$calls=array_slice($calls,0,1000);
        }
        sch_json(['ok'=>true,'role'=>$role,'session'=>$session,'today'=>date('Y-m-d'),'maxDate'=>date('Y-m-d',strtotime('+7 days')),
            'school'=>(string)(sch_rec_get('settings','main')['school']??'SNMSIS'),'children'=>$children,'promises'=>$promises,'calls'=>$calls,'receipts'=>$receipts,
            'canPromise'=>$role==='parent','canCall'=>$staff]);
    }
    if(!in_array($a,['promise','attempt','result'],true))sch_fail('Unknown action.',404);
    if($method!=='POST')sch_fail('POST only.',405);
    if($a==='promise'&&$role!=='parent')sch_fail('Only a parent can submit a payment date for their own child.',403);
    if($a!=='promise'&&!$staff)sch_fail('Not allowed.',403);
    $nonce=ff_nonce($in['nonce']??'');$nonceKey=hash('sha256',$uid.'|'.$a.'|'.$nonce);
    $db->beginTransaction();
    $db->query("SELECT uid FROM sch_users WHERE uid='principal' FOR UPDATE")->fetch();
    if($again=sch_rec_get('acctFollowNonce',$nonceKey)){
        $db->commit();sch_json(['ok'=>true,'again'=>true]+$again['response']);
    }
    $snapshot=ff_snapshot($db,$session);
    if(!is_string($in['sid']??null))throw new DomainException('Choose a student.');
    $sid=$in['sid'];
    if($a==='promise'&&!in_array($sid,ff_owned($u),true)){$db->rollBack();sch_fail('This child is not linked to your parent login.',403);}
    if(!isset($snapshot[$sid]))throw new DomainException('A fee card is not available. Please contact the school office.');
    $child=$snapshot[$sid];$now=sch_now_ms();$today=date('Y-m-d');$id=bin2hex(random_bytes(12));$response=[];
    if($a==='promise'){
        $date=ff_date($in['promise']??'');$amount=ff_amount($in['amount']??'',$child['due']);$key=$session.'|'.$sid;
        $old=sch_rec_get('acctPromise',$key);
        $p=['id'=>$id,'sid'=>$sid,'session'=>$session,'promise'=>$date,'amount'=>$amount,'note'=>ff_text($in['note']??''),
            'date'=>$today,'at'=>$now,'uid'=>$uid,'by'=>$u['label'],'baseline'=>ff_receipt_total($child,$today),
            'revision'=>(int)($old['revision']??0)+1,'previousDate'=>$old['promise']??'','source'=>'parent','ledgerFamily'=>$child['source']==='detailed'?'detailed':'fee-card'];
        sch_rec_put('acctPromiseHist',$id,$p,$uid);sch_rec_put('acctPromise',$key,$p,$uid);
        sch_log($u,'Fee Promise','Parent payment date for '.$child['name'].': '.$date.', amount '.number_format($amount/100,2));
        $response=['promise'=>ff_promise($p,$child)];
    } elseif($a==='attempt'){
        if(!$child['phone'])throw new DomainException('There is no valid mobile number for this child.');
        $family=sch_phone10((string)(sch_rec_get('student',$sid)['phone']??''));
        $sids=[$sid];if($family!=='')foreach($snapshot as $x)if($x['sid']!==$sid && sch_phone10((string)(sch_rec_get('student',$x['sid'])['phone']??''))===$family)$sids[]=$x['sid'];
        $remark='Dial requested on '.date('d-m-Y H:i').' IST; due '.number_format($child['due']/100,2).'. Connection not confirmed.';
        $c=['id'=>$id,'sid'=>$sid,'sids'=>$sids,'fam'=>$family?:'sid:'.$sid,'session'=>$session,'outcome'=>'Call initiated','promise'=>'',
            'amount'=>0,'note'=>$remark,'autoRemark'=>$remark,'by'=>$u['label'],'uid'=>$uid,'at'=>$now,'date'=>$today,'initiated'=>true,'startedAt'=>$now,'startedDate'=>$today,'autoRemarkHi'=>date('d-m-Y H:i').' IST par call ki koshish; baaki fees '.number_format($child['due']/100,2).'. Baat hui ya nahi, confirm nahi hai.','tag'=>$child['tag']];
        sch_rec_put('acctCall',$id,$c,$uid);sch_log($u,'Fee Call','Dial requested for '.$child['name'].'; connection not confirmed.');
        $response=['call'=>$c];
    } else {
        if(!is_string($in['id']??null))throw new DomainException('Choose a call.');
        $callId=$in['id'];$c=sch_rec_get('acctCall',$callId);
        if(!$c || ($c['uid']??'')!==$uid || ($c['sid']??'')!==$sid || ($c['session']??'')!==$session){$db->rollBack();sch_fail('This call attempt is not yours.',403);}
        if(!empty($c['completedAt'])){$db->rollBack();sch_fail('This result was already saved. Start another call to add a new result.',409);}
        if(!is_string($in['outcome']??null))throw new DomainException('Choose the call result.');
        $out=$in['outcome'];if(!in_array($out,OF_OUTCOMES,true))throw new DomainException('Choose the call result.');
        $need=in_array($out,['Promised to pay','Call back later','Will visit school'],true);
        $date=$need?ff_date($in['promise']??''):'';
        $amount=$out==='Promised to pay'?ff_amount($in['amount']??'',$child['due']):0;
        $c=array_replace($c,['outcome'=>$out,'promise'=>$date,'amount'=>$amount,'note'=>ff_text($in['note']??''),'completedAt'=>$now,'completedDate'=>$today,'initiated'=>false,'at'=>$now,'date'=>$today]);
        sch_rec_put('acctCall',$callId,$c,$uid);sch_log($u,'Fee Call','Call result for '.$child['name'].': '.$out.($date?' — '.$date:''));
        $response=['call'=>$c];
    }
    sch_rec_put('acctFollowNonce',$nonceKey,['response'=>$response],$uid);$db->commit();sch_json(['ok'=>true]+$response);
} catch(DomainException $e){if($db->inTransaction())$db->rollBack();sch_fail($e->getMessage(),422);}
catch(Throwable $e){if($db->inTransaction())$db->rollBack();error_log('followup_api: '.$e->getMessage());sch_fail('Could not save or load the follow-up. Please try again.',500);}
