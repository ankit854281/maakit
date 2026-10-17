<?php
// Integer-paise, immutable bill/event ledger. No database or network side effects.
declare(strict_types=1);
const FIN_MODES=['Cash','Cheque','Card','Wallet','NEFT','UPI','Net Banking','Online','Demand Draft'];
function fin_money($v,bool $zero=false):int{
 if(!is_string($v)&&!is_int($v))throw new InvalidArgumentException('Enter a plain rupee amount with at most two decimal places.');
 $s=trim((string)$v);if(!preg_match('/^(0|[1-9][0-9]{0,7})(?:\.([0-9]{1,2}))?$/D',$s,$m))throw new InvalidArgumentException('Enter a positive rupee amount with at most two decimal places.');
 $n=(int)$m[1]*100+(int)str_pad($m[2]??'',2,'0');if($n>1000000000||($n===0&&!$zero))throw new InvalidArgumentException('Amount must be greater than zero and at most ₹1 crore.');return $n;
}
function fin_text($v,int $max=1000):string{if(!is_scalar($v)&&$v!==null)throw new InvalidArgumentException('Invalid text.');return mb_substr(trim(preg_replace('/[\x00-\x1f\x7f]/u',' ',(string)$v)),0,$max);}
function fin_date($v):string{$s=(string)$v;$d=DateTimeImmutable::createFromFormat('!Y-m-d',$s);if(!$d||$d->format('Y-m-d')!==$s)throw new InvalidArgumentException('Choose a valid date.');return $s;}
function fin_session($v):string{$s=str_replace(['–','—'],'-',trim((string)$v));if(!preg_match('/^(20[0-9]{2})-(?:20)?([0-9]{2})$/D',$s,$m)||(int)$m[2]!==((int)$m[1]+1)%100)throw new InvalidArgumentException('Use an April–March session such as 2026-27.');return $m[1].'-'.$m[2];}
function fin_current():string{$y=(int)date('Y')-(date('n')<4?1:0);return $y.'-'.substr((string)($y+1),-2);}
function fin_period($p,string $ss):string{if(!preg_match('/^20[0-9]{2}-(0[1-9]|1[0-2])$/D',(string)$p)||$p<substr($ss,0,4).'-04'||$p>((int)substr($ss,0,4)+1).'-03')throw new InvalidArgumentException('Fee month must be inside the selected April–March session.');return (string)$p;}
function fin_snapshot(array $s):array{return array_intersect_key($s,array_flip(['id','adm','erpId','name','cls','section','father','phone','km']));}
function fin_heads():array{return [['id'=>'tuition','name'=>'Composite / tuition fee','category'=>'School'],['id'=>'transport','name'=>'Transport fee','category'=>'Transport'],['id'=>'opening','name'=>'Reconciled old dues','category'=>'Opening'],['id'=>'other','name'=>'Other fee','category'=>'Other'],['id'=>'late','name'=>'Late fee','category'=>'Other']];}
function fin_rows(string $k,?string $ss=null):array{$q=sch_db()->prepare('SELECT r,d FROM sch_records WHERE k=? AND deleted=0 ORDER BY ver,r');$q->execute([$k]);$out=[];foreach($q as $r){$d=json_decode((string)$r['d'],true);if(is_array($d)&&($ss===null||($d['session']??'')===$ss))$out[(string)$r['r']]=$d;}return $out;}
function fin_ledger(array $bills,array $entries,string $sid,string $ss,string $asOf='9999-12-31'):array{
 $E=array_values(array_filter($entries,fn($e)=>($e['sid']??'')===$sid&&($e['session']??'')===$ss&&$e['date']<=$asOf));
 $rev=[];$settled=[];$cancel=[];$bounce=[];$void=[];
 foreach($E as $e){$k=$e['kind'];$src=$e['source']??'';if(in_array($k,['ReverseCredit','ReverseAdvance'],true))$rev[$src]=true;if($k==='Settle')$settled[$src]=$e['date'];if($k==='Cancel')$cancel[$src]=true;if($k==='Bounce')$bounce[$src]=true;if($k==='VoidCharge')$void[$src]=true;}
 $rows=[];foreach($bills as $b)if(($b['sid']??'')===$sid&&($b['session']??'')===$ss&&$b['date']<=$asOf&&!isset($void[$b['id']]))$rows[$b['id']]=$b+['paid'=>0,'discount'=>0,'pending'=>0];
 $R=[];foreach($E as $e)if(in_array($e['kind'],['Receipt','OpeningCredit','TransferIn'],true)){$id=$e['id'];$status=isset($cancel[$id])?'Cancelled':(isset($bounce[$id])?'Bounced':(($e['status']??'Cleared')==='Pending'&&!isset($settled[$id])?'Pending':'Cleared'));$R[$id]=$e+['allocations'=>[],'discounts'=>[]];$R[$id]['currentStatus']=$status;$R[$id]['clearedDate']=$settled[$id]??$e['date'];$R[$id]['refunded']=0;$R[$id]['refundedAllocations']=[];$R[$id]['advanceRefunded']=0;$R[$id]['advanceSpent']=0;}
 $add=function(array $alloc,string $field,int $sign=1)use(&$rows){foreach($alloc as $id=>$n){if(!isset($rows[$id]))throw new InvalidArgumentException('A financial entry refers to a missing or void bill.');$rows[$id][$field]+=$sign*$n;}};
 foreach($R as $r){if($r['currentStatus']==='Cleared'){$add($r['allocations'],'paid');$add($r['discounts'],'discount');}elseif($r['currentStatus']==='Pending'){$add($r['allocations'],'pending');$add($r['discounts'],'pending');}}
 foreach($E as $e){$k=$e['kind'];if(in_array($k,['Credit','Discount'],true)&&!isset($rev[$e['id']]))$add($e['allocations'],'discount');
 if($k==='ApplyAdvance'&&!isset($rev[$e['id']])){$add($e['allocations'],'paid');foreach($e['sources'] as $src=>$n)if(isset($R[$src]))$R[$src]['advanceSpent']+=$n;}
 if($k==='TransferOut')foreach($e['sources'] as $src=>$n)if(isset($R[$src]))$R[$src]['advanceSpent']+=$n;
 if($k==='Refund'){$src=$e['source'];if(!isset($R[$src]))throw new InvalidArgumentException('Refund source is missing.');$R[$src]['refunded']+=$e['amount'];$R[$src]['advanceRefunded']+=$e['advanceAmount']??0;foreach(($e['allocations']??[]) as $id=>$n)$R[$src]['refundedAllocations'][$id]=($R[$src]['refundedAllocations'][$id]??0)+$n;$add($e['allocations']??[],'paid',-1);}}
 $tot=['billed'=>0,'paid'=>0,'discount'=>0,'outstanding'=>0,'overdue'=>0,'advance'=>0,'pendingCheque'=>0];
 foreach($rows as &$b){$b['outstanding']=$b['amount']-$b['paid']-$b['discount'];$b['status']=$b['outstanding']===0?'Paid':($b['paid']+$b['discount']>0?'Partial':'Unpaid');foreach(['paid','discount','outstanding'] as $k)if($b[$k]<0)throw new InvalidArgumentException('This correction would over-allocate a fee head. Reverse dependent adjustments first.');$tot['billed']+=$b['amount'];foreach(['paid','discount','outstanding'] as $k)$tot[$k]+=$b[$k];if($b['dueDate']<=min($asOf,date('Y-m-d')))$tot['overdue']+=$b['outstanding'];}unset($b);
 foreach($R as &$r){$r['availableAdvance']=$r['currentStatus']==='Cleared'?$r['amount']-array_sum($r['allocations'])-$r['advanceSpent']-$r['advanceRefunded']:0;if($r['availableAdvance']<0||$r['refunded']>$r['amount'])throw new InvalidArgumentException('Advance or refund exceeds its source payment.');$tot['advance']+=$r['availableAdvance'];if($r['currentStatus']==='Pending')$tot['pendingCheque']+=$r['amount'];}unset($r);
 uasort($rows,fn($a,$b)=>[$a['period'],$a['dueDate'],$a['id']]<=>[$b['period'],$b['dueDate'],$b['id']]);
 return $tot+['rows'=>array_values($rows),'receipts'=>$R,'entries'=>$E,'reversed'=>array_keys($rev),'configured'=>count($rows)>0||count($E)>0];
}
function fin_allocate(array $L,int $amount,array $ids,string $date,bool $pendingSafe=false):array{
 if(count($ids)!==count(array_unique($ids)))throw new InvalidArgumentException('Select each fee head only once.');$known=array_column($L['rows'],'id');foreach($ids as $id)if(!is_string($id)||!in_array($id,$known,true))throw new InvalidArgumentException('A selected charge no longer exists. Reload the ledger.');
 $left=$amount;$out=[];foreach($L['rows'] as $b){if(!in_array($b['id'],$ids,true)||$b['date']>$date)continue;$available=$b['outstanding']-($pendingSafe?$b['pending']:0);$n=min($left,max(0,$available));if($n>0){$out[$b['id']]=$n;$left-=$n;}if(!$left)break;}return ['allocations'=>$out,'left'=>$left];
}
function fin_sources(array $L,int $n,string $date):array{$out=[];foreach($L['receipts'] as $r)if($r['clearedDate']<=$date&&$r['availableAdvance']>0){$take=min($n,$r['availableAdvance']);$out[$r['id']]=$take;$n-=$take;if(!$n)break;}if($n)throw new InvalidArgumentException('Not enough cleared unused advance on that posting date.');return $out;}
function fin_cash_rows(array $entries,string $ss):array{
 $E=array_filter($entries,fn($e)=>($e['session']??'')===$ss);$clear=[];$bounce=[];$R=[];foreach($E as $e){if($e['kind']==='Settle')$clear[$e['source']]=$e['date'];if($e['kind']==='Bounce')$bounce[$e['source']]=true;if($e['kind']==='Receipt')$R[$e['id']]=$e;}
 $out=[];foreach($R as $id=>$r)if(!isset($bounce[$id])&&(($r['status']??'')!=='Pending'||isset($clear[$id])))$out[]=$r+['cashDate'=>$clear[$id]??$r['date'],'cashAmount'=>$r['amount'],'cashType'=>'Receipt'];
 foreach($E as $e){if($e['kind']==='Refund')$out[]=$e+['cashDate'=>$e['date'],'cashAmount'=>-$e['amount'],'cashType'=>'Refund'];if($e['kind']==='Cancel'&&isset($R[$e['source']])){$r=$R[$e['source']];if(!isset($bounce[$r['id']])&&(($r['status']??'')!=='Pending'||isset($clear[$r['id']])))$out[]=array_replace($r,$e,['mode'=>$r['mode'],'cashDate'=>$e['date'],'cashAmount'=>-$r['amount'],'cashType'=>'Cancellation']);}}
 return $out;
}
function fin_rate_items(array $s,array $students,array $F,array $o):array{
 $raw=$F['monthly'][$s['cls']??'']??null;if(!array_key_exists('tuition',$o)&&$raw===null)throw new InvalidArgumentException('Monthly school fee is not configured for Class '.($s['cls']??'').'.');$base=$raw===null?0:fin_money((string)$raw,true);$disc=0;
 $phone=preg_replace('/\D/','',(string)($s['phone']??''));$phone=strlen($phone)>=10?substr($phone,-10):'';$family=$phone===''?[$s]:array_values(array_filter($students,fn($x)=>substr(preg_replace('/\D/','',(string)($x['phone']??'')),-10)===$phone));usort($family,fn($a,$b)=>((float)($F['monthly'][$b['cls']??'']??0)<=>(float)($F['monthly'][$a['cls']??'']??0))?:strcmp((string)($a['adm']??$a['id']),(string)($b['adm']??$b['id'])));
 foreach($family as $i=>$x)if($x['id']===$s['id']&&$i>=2){$pct=fin_money((string)($F['thirdChildPct']??0),true);if($pct>10000)throw new InvalidArgumentException('Sibling discount must be at most 100%.');$disc=intdiv($base*$pct+5000,10000);}
 $tuition=$o['tuition']??($base-$disc);$transport=0;if(isset($o['transport']))$transport=$o['transport'];elseif((float)($s['km']??0)>0){$transport=null;foreach($F['transport']??[] as $t)if(($t['upto']??null)===null||(float)$s['km']<=(float)$t['upto']){$transport=isset($t['amt'])?fin_money((string)$t['amt'],true):null;break;}if($transport===null)throw new InvalidArgumentException('Transport fee is not configured for this student. Set a student-specific rate first.');}
 return ['tuition'=>$tuition,'transport'=>$transport,'includedConcession'=>isset($o['tuition'])?0:$disc];
}
function fin_media_dir():string{return dirname(__DIR__,2).'/school-private-finance';}
