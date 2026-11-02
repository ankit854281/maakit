<?php
declare(strict_types=1);
// Fee-follow-up records are private acct* kinds, excluded from legacy load/sync.
// A promise is not a receipt and never changes a fee ledger.
require_once __DIR__ . '/office_lib.php';
require_once __DIR__ . '/registers_lib.php';
require_once __DIR__ . '/feecollect_lib.php';
require_once __DIR__ . '/feereports_lib.php';
require_once __DIR__ . '/finance_lib.php';

function ff_session(): string {
    $s = ofc_ses((string)(sch_rec_get('settings', 'main')['session'] ?? ''));
    return $s ?: (date('n') >= 4 ? date('Y') . '-' . date('y', strtotime('+1 year')) : (date('Y') - 1) . '-' . date('y'));
}
function ff_rows(string $kind): array {
    $q = sch_db()->prepare('SELECT r,d FROM sch_records WHERE k=? AND deleted=0');
    $q->execute([$kind]); $out = [];
    foreach ($q as $r) { $d = json_decode((string)$r['d'], true); if (is_array($d)) $out[(string)$r['r']] = $d; }
    return $out;
}
function ff_owned(array $u): array {
    if ($u['role'] === 'student') return [(string)$u['tid']];
    if ($u['role'] !== 'parent') return [];
    return array_values(array_map(fn($x) => (string)$x['id'], sch_children((string)$u['tid'])));
}
function ff_tag(string $raw): string {
    preg_match_all('/([^()]+)\(\s*\)/', $raw, $m);
    $parts = array_values(array_filter(array_map('trim', $m[1] ?: [trim($raw)])));
    $parts = array_values(array_filter($parts, fn($t) => !preg_match('/\bPEN\b|^Generated$/i', $t)));
    $order = ['Company','FD','Plan','One Time Payment','Transport + Fee','Fee'];
    usort($parts, fn($a,$b) => (array_search($a,$order,true) === false ? 99 : array_search($a,$order,true)) <=> (array_search($b,$order,true) === false ? 99 : array_search($b,$order,true)));
    return $parts[0] ?? '';
}
/** Same month/head and fixed-plan arithmetic as Chairman; all amounts below in paise. */
function ff_snapshot(PDO $db, string $session): array {
    $dues = fr_dues($db, $session); $tags = ff_rows('fdTag');
    $months = [4,5,6,7,8,9,10,11,12,1,2,3]; $year = (int)substr($session,0,4); $today = date('Y-m-d');
    $idx = $today < "$year-04-01" ? -1 : ($today > ($year+1).'-03-31' ? 11 : array_search((int)date('n'),$months,true));
    $out = [];
    foreach ($dues['rows'] as $r) {
        $sid = (string)$r['sid']; $tag = $tags[$sid] ?? null; $fixed = (int)($tag['fixed'] ?? 0);
        $old = max(0,(int)round(($r['oldDue']-$r['oldPaid'])*100));
        $due = $old; $annualDue = $old; $feePaid = 0; $mon = [];
        foreach ($months as $m) {
            $fd=0;$fp=0;$td=0;$tp=0;
            foreach ($r['heads'] as $h) if ((int)$h['m'] === $m) {
                if (preg_match('/transport/i',$h['name'])) { $td+=(int)round($h['amt']*100);$tp+=(int)round($h['paid']*100); }
                else { $fd+=(int)round($h['amt']*100);$fp+=(int)round($h['paid']*100); }
            }
            $feePaid += $fp; $mon[] = [$fd,$fp,$td,$tp];
        }
        foreach ($mon as $i => [$fd,$fp,$td,$tp]) {
            if ($fixed > 0) { $fd=$fixed;$fp=min($fixed,max(0,$feePaid));$feePaid-=$fp; }
            $owe=max(0,$fd-$fp)+max(0,$td-$tp);$annualDue+=$owe;if($i<=$idx)$due+=$owe;
        }
        $receipts=$r['receipts'];
        $source = $dues['live'] ? 'live' : 'copied';
        $account = sch_rec_get('finAccount',$session.'|'.$sid);
        if (!empty($account['active'])) {
            $ledger=fin_ledger(fin_rows('finBill',$session),fin_rows('finEntry',$session),$sid,$session);
            $due=max(0,(int)$ledger['outstanding']-(int)$ledger['advance']);$annualDue=$due;$source='detailed';
            $receipts=[];foreach($ledger['receipts'] as $rc)if($rc['kind']==='Receipt'&&$rc['currentStatus']==='Cleared')$receipts[]=['no'=>$rc['number']??$rc['id'],'date'=>$rc['clearedDate'],'received'=>max(0,$rc['amount']-$rc['refunded'])/100];
        }
        $out[$sid]=['sid'=>$sid,'name'=>$r['name'],'cls'=>$r['cls'],'section'=>$r['section'],'father'=>$r['father'],
            'phone'=>sch_phone10((string)($r['fatherMobile'] ?: $r['phone'])),'tag'=>$tag ? (string)$tag['tag'] : ff_tag((string)$r['tag']),
            'due'=>$due,'overallDue'=>$annualDue,'oldDue'=>$old,'source'=>$source,'asOn'=>$source==='copied'?$dues['asOn']:'',
            'receipts'=>$receipts];
    }
    // An active detailed account can exist before an old fee card is imported.
    foreach(reg_students() as $sid=>$s)if(empty($s['inactive'])&&!isset($out[$sid])&&!empty(sch_rec_get('finAccount',$session.'|'.$sid)['active'])){
        $ledger=fin_ledger(fin_rows('finBill',$session),fin_rows('finEntry',$session),(string)$sid,$session);
        $receipts=[];foreach($ledger['receipts'] as $rc)if($rc['kind']==='Receipt'&&$rc['currentStatus']==='Cleared')$receipts[]=['no'=>$rc['number']??$rc['id'],'date'=>$rc['clearedDate'],'received'=>max(0,$rc['amount']-$rc['refunded'])/100];
        $due=max(0,(int)$ledger['outstanding']-(int)$ledger['advance']);
        $out[$sid]=['sid'=>(string)$sid,'name'=>$s['name'],'cls'=>$s['cls'],'section'=>$s['section']??'','father'=>$s['father']??'','phone'=>sch_phone10((string)($s['phone']??'')),'tag'=>(string)($tags[$sid]['tag']??''),'due'=>$due,'overallDue'=>$due,'oldDue'=>0,'source'=>'detailed','asOn'=>'','receipts'=>$receipts];
    }
    // Preserve the existing Fee Desk projection for children without a card.
    $students=reg_students();foreach($students as $sid=>&$s)$s['km']=sch_rec_get('student',(string)$sid)['km']??null;unset($s);$families=[];foreach($students as $s)if(empty($s['inactive']))$families[of_phone_key($s)][]=$s;
    $fees=sch_rec_get('fees','main');$sets=ff_rows('acctStu');$pays=array_values(ff_rows('acctPay'));$ss=of_session();
    foreach($students as $sid=>$s)if(empty($s['inactive'])&&!isset($out[$sid])){
        $s['id']=(string)$sid;$balance=of_dues($s,$families[of_phone_key($s)]??[$s],$fees,$sets[$sid]??[],$pays,$ss['start'],$today);
        if($balance['missing'])continue;$receipts=[];foreach($pays as $p)if(($p['sid']??'')===$sid&&empty($p['cancelled'])&&(string)$p['date']>=$ss['start'])$receipts[]=['no'=>(string)$p['no'],'date'=>$p['date'],'received'=>$p['amt']];
        $out[$sid]=['sid'=>(string)$sid,'name'=>$s['name'],'cls'=>$s['cls'],'section'=>$s['section']??'','father'=>$s['father']??'','phone'=>sch_phone10((string)($s['phone']??'')),'tag'=>(string)($tags[$sid]['tag']??''),'due'=>max(0,(int)round($balance['due']*100)),'overallDue'=>null,'oldDue'=>max(0,(int)$balance['opening']*100),'source'=>'legacy','asOn'=>'','receipts'=>$receipts];
    }
    return $out;
}
function ff_receipt_total(array $child,string $from): int {
    // Exclude earlier-day receipts imported after a promise. Capture same-day receipts
    // at submission so an existing receipt cannot fulfil a new promise.
    $total=0;foreach($child['receipts'] as $r)if((string)$r['date'] >= $from)$total+=(int)round($r['received']*100);
    return $total;
}
function ff_public_child(array $r): array { unset($r['receipts']);return $r; }
function ff_promise(array $p,?array $child): array {
    $sameLedger=!$child || !isset($p['ledgerFamily']) || $p['ledgerFamily']===($child['source']==='detailed'?'detailed':($child['source']==='legacy'?'legacy':'fee-card'));
    $received=$child && $sameLedger ? max(0,ff_receipt_total($child,(string)$p['date'])-(int)$p['baseline']) : 0;
    $p['received']=min((int)$p['amount'],$received);
    $p['remaining']=max(0,(int)$p['amount']-$received);
    $p['status']=$p['remaining']===0?'fulfilled':($p['promise']<date('Y-m-d')?'overdue':($p['received']>0?'partial':'pending'));
    unset($p['baseline'],$p['uid']);return $p;
}
function ff_date($value): string {
    if(!is_string($value))throw new DomainException('Choose a valid date.');
    $v=$value;$d=DateTimeImmutable::createFromFormat('!Y-m-d',$v);$today=new DateTimeImmutable('today');
    if(!$d || $d->format('Y-m-d')!==$v || $d<$today || $d>$today->modify('+7 days'))throw new DomainException('Choose a date from today to the next 7 days.');
    return $v;
}
function ff_amount($value,int $max): int {
    if(!is_string($value)&&!is_int($value)&&!is_float($value))throw new DomainException('Enter a valid amount.');
    $s=trim((string)$value);
    if(!preg_match('/^\d{1,7}(?:\.\d{1,2})?$/D',$s))throw new DomainException('Enter a valid amount with at most 2 decimal places.');
    $n=(int)round((float)$s*100);
    if($n<1 || $n>$max)throw new DomainException('The amount must be more than zero and cannot exceed the current due.');
    return $n;
}
function ff_text($v,int $max=300):string{if(!is_scalar($v)&&$v!==null)throw new DomainException('Invalid text.');return mb_substr(trim(preg_replace('/[\x00-\x1f\x7f]/u',' ',(string)$v)),0,$max);}
function ff_nonce($v):string{if(!is_string($v))throw new DomainException('Please refresh and try again.');$s=$v;if(!preg_match('/^[a-f0-9]{24,40}$/D',$s))throw new DomainException('Please refresh and try again.');return $s;}
