<?php
// ============================================================
//  जाँच — सारथी का पूरा रास्ता, शुरू से आख़िर तक
//
//  Manzoor-shuda partner rider (role='rider') ke saath ek asli
//  order:
//    /sarathi/ login → haazri → order apne aap mila →
//    galat Pick OTP ruka → sahi Pick OTP se uthaya →
//    live GPS shuru → galat graahak code ruka →
//    sahi code se pahunchaya → agla intezaar wala order mila.
//
//  Pehle rider login to ho jata tha, par order kabhi milta hi nahi
//  tha (dispatch sirf role='delivery' dekhta tha) aur GPS 403 deta
//  tha. Ye jaanch usi ko dobara toot-ne se rokti hai.
// ============================================================
require_once __DIR__.'/../../config.php';
if(DB_NAME!=='maakit_jaanch')throw new RuntimeException('CI database only');
require_once __DIR__.'/../../inc/db.php';
require_once __DIR__.'/../../inc/coverage.php';

function sf_check($ok,$message){if(!$ok)throw new RuntimeException('Sarathi flow: '.$message);}
$jar=tempnam(sys_get_temp_dir(),'mk-sarathi-');$area=0;$rider=0;$orders=[];
function sf_http($path,$post=null,$expected=200){
    global $jar;
    $c=curl_init('http://127.0.0.1:8099'.$path);
    curl_setopt_array($c,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_COOKIEFILE=>$jar,CURLOPT_COOKIEJAR=>$jar,CURLOPT_TIMEOUT=>20,CURLOPT_PROXY=>'']);
    if($post!==null)curl_setopt_array($c,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>http_build_query($post)]);
    $html=curl_exec($c);$status=curl_getinfo($c,CURLINFO_HTTP_CODE);curl_close($c);
    sf_check($status===$expected&&is_string($html)&&!preg_match('/Fatal error|Warning:|Uncaught/',$html),"HTTP $path gave $status");
    return $html;
}
function sf_csrf($html){sf_check(preg_match('/name="csrf" value="([^"]+)"/',$html,$m)===1,'csrf missing');return $m[1];}
function sf_order($code){
    global $pdo,$orders;
    $no='CI-SR-'.bin2hex(random_bytes(4));
    $pdo->prepare("INSERT INTO orders(order_no,code,customer_name,mobile,village,items,status) VALUES (?,?,'CI Graahak','9000000088','CI Sarathi Area','test saaman','Confirm')")->execute([$no,$code]);
    $id=(int)$pdo->lastInsertId();$orders[]=$id;return $id;
}
function sf_row($id){global $pdo;$q=$pdo->prepare('SELECT * FROM orders WHERE id=?');$q->execute([$id]);return $q->fetch();}

try{
    // ---- manzoor-shuda rider: username = mobile, active = 1 ----
    $mobile='9000000777';$code='CI'.bin2hex(random_bytes(4));
    $pdo->prepare("INSERT INTO users(name,username,password,role,mobile,active) VALUES ('CI Sarathi Rider',?,?,'rider',?,1)")->execute([$mobile,password_hash($code,PASSWORD_DEFAULT),$mobile]);
    $rider=(int)$pdo->lastInsertId();

    // ---- ilaka: live, delivery chalu, rider roster me, auto-dispatch chalu ----
    $pdo->exec("INSERT INTO villages(name,live) VALUES ('CI Sarathi Area',1)");$area=(int)$pdo->lastInsertId();
    $pdo->prepare("INSERT INTO service_area_meta(village_id,city,state,pincode,delivery_on) VALUES (?,'CI City','CI State','221401',1)")->execute([$area]);
    $pdo->prepare('INSERT INTO service_area_drivers VALUES (?,?)')->execute([$area,$rider]);
    $pdo->prepare('INSERT INTO service_area_dispatch VALUES (?,1)')->execute([$area]);
    sf_check(in_array($rider,array_map('intval',array_column(coverage_drivers($pdo,'CI Sarathi Area'),'id')),true),'BPO assignment list must include approved rider');

    $first=sf_order('4321');$second=sf_order('8765');

    // ---- login ----
    $html=sf_http('/sarathi/');
    sf_http('/sarathi/',['csrf'=>sf_csrf($html),'who'=>$mobile,'pass'=>$code],302);
    $html=sf_http('/sarathi/kaam.php');$csrf=sf_csrf($html);

    // ---- haazri → pehla order apne aap ----
    sf_http('/sarathi/kaam.php',['csrf'=>$csrf,'do'=>'duty_on'],302);
    $live=(int)$pdo->query("SELECT COUNT(*) FROM driver_availability WHERE user_id=$rider AND available_until>UTC_TIMESTAMP()")->fetchColumn();
    sf_check($live===1,'Duty must be stored in UTC so dispatch sees it');
    $o=sf_row($first);sf_check($o['status']==='Assign'&&(int)$o['delivery_user']===$rider,'Duty must auto-assign the confirmed order to the rider');
    sf_check(sf_row($second)['delivery_user']===null,'Busy rider gets no second order');

    // ---- kaam dikhna, Pick OTP banna ----
    $html=sf_http('/sarathi/kaam.php');
    sf_check(strpos($html,$o['order_no'])!==false||strpos($html,'CI Graahak')!==false,'Assigned order must show on work page');
    $pick=(string)sf_row($first)['pick_otp'];sf_check(preg_match('/^\d{4}$/',$pick)===1,'Pick OTP must be created');

    // ---- galat Pick OTP ----
    $wrong=str_pad((string)(((int)$pick+1)%10000),4,'0',STR_PAD_LEFT);
    sf_http('/sarathi/kaam.php',['csrf'=>$csrf,'do'=>'uthaya','oid'=>$first,'otp'=>$wrong],302);
    sf_check(sf_row($first)['picked_at']===null,'Wrong pick OTP must not record pickup');
    // ---- sahi Pick OTP ----
    sf_http('/sarathi/kaam.php',['csrf'=>$csrf,'do'=>'uthaya','oid'=>$first,'otp'=>$pick],302);
    $o=sf_row($first);sf_check($o['status']==='Pickup'&&$o['picked_at']!==null&&$o['trip_id']!==null,'Correct pick OTP records pickup in a trip');

    // ---- live GPS: rider ko 403 nahi milna chahiye ----
    $json=json_decode(sf_http('/api.php',['a'=>'start_tracking','o'=>$first,'csrf'=>$csrf]),true);
    sf_check(($json['ok']??0)===1&&preg_match('/^[a-f0-9]{32}$/',$json['token']??''),'Rider must be able to start live GPS');
    sf_http('/api.php',['a'=>'ping','o'=>$first,'csrf'=>$csrf,'token'=>$json['token'],'lat'=>'25.3956','lng'=>'82.5683','acc'=>'20','at'=>(string)time()]);

    // ---- galat graahak code ----
    sf_http('/sarathi/kaam.php',['csrf'=>$csrf,'do'=>'de_diya','oid'=>$first,'otp'=>'0000'],302);
    sf_check(sf_row($first)['status']==='Pickup','Wrong customer code must not deliver');
    // ---- sahi graahak code ----
    sf_http('/sarathi/kaam.php',['csrf'=>$csrf,'do'=>'de_diya','oid'=>$first,'otp'=>'4321'],302);
    $o=sf_row($first);sf_check($o['status']==='Delivered'&&$o['delivered_at']!==null,'Correct customer code delivers');
    sf_check((int)$pdo->query("SELECT COUNT(*) FROM live_tracks WHERE order_id=$first")->fetchColumn()===0,'Live location stops after delivery');

    // ---- agla order apne aap ----
    $o=sf_row($second);sf_check($o['status']==='Assign'&&(int)$o['delivery_user']===$rider,'Next waiting order goes to the now-free rider');

    // ---- khata me ginti ----
    sf_check(strpos(sf_http('/sarathi/khata.php'),'Fatal')===false,'Ledger page opens');

    echo "Sarathi rider login, duty, auto-assign, pick OTP, GPS, drop code and next-order checks passed\n";
}finally{
    foreach($orders as $id){
        foreach(['live_tracks','sarathi_samasya'] as $t)$pdo->prepare("DELETE FROM $t WHERE order_id=?")->execute([$id]);
        $pdo->prepare('DELETE FROM orders WHERE id=?')->execute([$id]);
    }
    if($rider){foreach(['sarathi_trips'=>'rider_user','driver_availability'=>'user_id'] as $t=>$c)$pdo->prepare("DELETE FROM $t WHERE $c=?")->execute([$rider]);$pdo->prepare('DELETE FROM users WHERE id=?')->execute([$rider]);}
    if($area){foreach(['service_area_drivers','service_area_meta','service_area_dispatch'] as $t)$pdo->prepare("DELETE FROM $t WHERE village_id=?")->execute([$area]);$pdo->prepare('DELETE FROM villages WHERE id=?')->execute([$area]);}
    @unlink($jar);
}
