<?php
// ============================================================
//  जाँच — सवारी और पार्सल (ride.php → trip.php)
//
//  CI me Smart Sarathi juda nahi hota. To bhi:
//    panna khulta hai → galat mobile ruka → sahi booking bani,
//    kiraya server ne doori se nikala (browser ka bheja nahi) →
//    trip.php par code dikhta hai, doosre mobile se nahi khulta →
//    grahak radd kar sakta hai → admin me "नहीं गई" dikhta hai.
// ============================================================
require_once __DIR__.'/../../config.php';
if(DB_NAME!=='maakit_jaanch')throw new RuntimeException('CI database only');
require_once __DIR__.'/../../inc/db.php';

function rf_check($ok,$m){if(!$ok)throw new RuntimeException('Ride flow: '.$m);}
$jar=tempnam(sys_get_temp_dir(),'mk-ride-');
function rf_http($path,$post=null,$expected=200,$jarFile=null){
    global $jar;
    $c=curl_init('http://127.0.0.1:8099'.$path);
    curl_setopt_array($c,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_COOKIEFILE=>$jarFile?:$jar,CURLOPT_COOKIEJAR=>$jarFile?:$jar,CURLOPT_TIMEOUT=>20,CURLOPT_PROXY=>'',CURLOPT_HEADER=>true]);
    if($post!==null)curl_setopt_array($c,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>http_build_query($post)]);
    $raw=curl_exec($c);$status=curl_getinfo($c,CURLINFO_HTTP_CODE);$hs=curl_getinfo($c,CURLINFO_HEADER_SIZE);curl_close($c);
    $html=substr((string)$raw,$hs);
    rf_check($status===$expected&&!preg_match('/Fatal error|Warning:|Uncaught/',$html),"HTTP $path gave $status");
    return [$html,substr((string)$raw,0,$hs)];
}
function rf_csrf($html){rf_check(preg_match('/name="csrf" value="([^"]+)"/',$html,$m)===1,'csrf missing');return $m[1];}

$mob='9'.str_pad((string)random_int(0,999999999),9,'0',STR_PAD_LEFT);
try{
    [$html]=rf_http('/ride.php?lang=en');
    rf_check(strpos($html,'Where to?')!==false&&strpos($html,'name="vehicle"')!==false,'ride page renders vehicles');
    [$ph]=rf_http('/ride.php?t=parcel&lang=en');
    rf_check(strpos($ph,'What are you sending?')!==false,'parcel tab renders');
    $csrf=rf_csrf($html);
    $base=['csrf'=>$csrf,'kind'=>'ride','vehicle'=>'auto','seats'=>'2','pick_address'=>'CI Station Road','pick_geo'=>'25.386000,82.565000',
           'drop_address'=>'CI Bus Stand','drop_geo'=>'25.337000,82.469000','name'=>'CI Sawari','when'=>'now','fare_est'=>'1'];
    [$bad]=rf_http('/ride.php?lang=en',$base+['mobile'=>'12345']);
    rf_check(strpos($bad,'10 digits')!==false,'bad mobile is refused');
    [, $hd]=rf_http('/ride.php?lang=en',$base+['mobile'=>$mob],302);
    rf_check(preg_match('#Location: /trip\.php\?no=(RD[0-9A-Z]+)#',$hd,$m)===1,'booking redirects to trip page');
    $no=$m[1];
    $q=$pdo->prepare('SELECT * FROM rides WHERE trip_no=?');$q->execute([$no]);$r=$q->fetch();
    rf_check($r&&$r['vehicle']==='auto'&&(int)$r['seats']===2,'ride row saved');
    rf_check((float)$r['km']>10&&(int)$r['fare_est']>100&&(int)$r['fare_est']%5===0,'server computed fare from distance');
    rf_check(preg_match('/^\d{4}$/',$r['pick_code'])&&$r['pick_code']!==$r['drop_code'],'two different codes');
    rf_check($r['sarathi_job']===null&&$r['send_error']!=='','unsent booking is marked for admin');
    [$tp]=rf_http('/trip.php?no='.$no.'&new=1&lang=en');
    rf_check(strpos($tp,'Start code')!==false&&strpos($tp,'>'.$r['pick_code'][0].'<')!==false,'trip page shows start code to the booker');
    $other=tempnam(sys_get_temp_dir(),'mk-ride2-');
    [$op]=rf_http('/trip.php?no='.$no.'&m=9000000001&lang=en',null,200,$other);
    rf_check(strpos($op,'No booking found')!==false&&strpos($op,'Start code')===false,'another mobile cannot open it');
    [$js]=rf_http('/trip.php?no='.$no.'&m='.$mob.'&json=1',null,200,$other);
    rf_check(json_decode($js,true)['s']==='new','status json works for the right mobile');
    $pdo->prepare("UPDATE rides SET status='assigned', rider_name='CI Rider', rider_lat=25.3860000, rider_lng=82.5650000, rider_location_at=? WHERE trip_no=?")
        ->execute([time(),$no]);
    [$liveJson]=rf_http('/trip.php?no='.$no.'&m='.$mob.'&json=1',null,200,$other);
    $liveData=json_decode($liveJson,true);
    rf_check(isset($liveData['loc']['lat'],$liveData['loc']['lng'])
        && abs($liveData['loc']['lat']-25.386)<0.000001 && abs($liveData['loc']['lng']-82.565)<0.000001,
        'fresh rider location is visible to booking mobile');
    $pdo->prepare('UPDATE rides SET rider_location_at=? WHERE trip_no=?')->execute([time()-121,$no]);
    [$staleJson]=rf_http('/trip.php?no='.$no.'&m='.$mob.'&json=1',null,200,$other);
    rf_check(json_decode($staleJson,true)['loc']===null,'stale rider location is hidden');
    $pdo->prepare('UPDATE rides SET rider_location_at=? WHERE trip_no=?')->execute([time(),$no]);
    rf_http('/trip.php?no='.$no.'&lang=en',['csrf'=>rf_csrf($tp),'do'=>'cancel','no'=>$no,'m'=>$mob]);
    $q->execute([$no]);rf_check($q->fetch()['status']==='cancelled','customer can cancel before pickup');
    echo "✅ सवारी / पार्सल: बुकिंग, किराया, कोड, ट्रैकिंग और रद्द — सब ठीक\n";
}finally{
    $pdo->prepare('DELETE FROM rides WHERE mobile=?')->execute([$mob]);
}
