<?php
// ============================================================
//  जाँच — सारथी के लिए एक ही दरवाज़ा
//
//  Pehle rider ke liye TEEN darwaze khule the: /delivery/,
//  /sarathi/ aur /smartsarathi/. Teen darwaze matlab teen jagah
//  bug, aur rider ko ye socha ki "aaj kaun sa kholun".
//
//  Ab /delivery/ rider ke liye band hai — wo /sarathi/ par mod
//  diya jata hai. Par usse PEHLE wo do kaam /sarathi/ me daale
//  gaye jo sirf /delivery/ me the:
//
//     1. dukaan ka bill + saamaan ka daam
//     2. "aa raha hoon" wala WhatsApp sandesh
//
//  Aur /delivery/ mitaya NAHI gaya — admin ke liye khula hai,
//  kyunki puri Maakit me wahi akeli jagah hai jahan se daftar
//  rider ki jagah se kuch darj kar sakta hai.
//
//  Ye jaanch teeno baaton ki rakhwali karti hai. Koi ise dobara
//  tode — main ya koi aur AI — to yahin pakda jayega.
// ============================================================
require_once __DIR__.'/../../config.php';
if(DB_NAME!=='maakit_jaanch')throw new RuntimeException('CI database only');
require_once __DIR__.'/../../inc/db.php';

function ed_check($ok,$message){if(!$ok)throw new RuntimeException('Ek darwaza: '.$message);}
$jar=tempnam(sys_get_temp_dir(),'mk-ekd-');$rider=0;$boss=0;$oid=0;$bill=null;

function ed_http($path,$post=null,$expected=200,$follow=false){
    global $jar;
    $c=curl_init('http://127.0.0.1:8099'.$path);
    curl_setopt_array($c,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_COOKIEFILE=>$jar,CURLOPT_COOKIEJAR=>$jar,
                          CURLOPT_TIMEOUT=>20,CURLOPT_PROXY=>'',CURLOPT_FOLLOWLOCATION=>$follow]);
    if($post!==null)curl_setopt_array($c,[CURLOPT_POST=>true,
        CURLOPT_POSTFIELDS=>is_array($post)&&!isset($post['__raw'])?http_build_query($post):$post]);
    $html=curl_exec($c);$status=curl_getinfo($c,CURLINFO_HTTP_CODE);
    $to=curl_getinfo($c,CURLINFO_REDIRECT_URL);curl_close($c);
    ed_check($status===$expected&&is_string($html)&&!preg_match('/Fatal error|Warning:|Uncaught/',$html),
             "HTTP $path gave $status");
    return ['html'=>$html,'to'=>(string)$to];
}
function ed_post_file($path,$fields){           // multipart — bill ki photo ke liye
    global $jar;
    $c=curl_init('http://127.0.0.1:8099'.$path);
    curl_setopt_array($c,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_COOKIEFILE=>$jar,CURLOPT_COOKIEJAR=>$jar,
                          CURLOPT_TIMEOUT=>20,CURLOPT_PROXY=>'',CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$fields]);
    $html=curl_exec($c);$status=curl_getinfo($c,CURLINFO_HTTP_CODE);curl_close($c);
    ed_check($status===302,"bill post gave $status");
    return $html;
}
function ed_csrf($html){ed_check(preg_match('/name="csrf" value="([^"]+)"/',$html,$m)===1,'csrf missing');return $m[1];}
function ed_row($id){global $pdo;$q=$pdo->prepare('SELECT * FROM orders WHERE id=?');$q->execute([$id]);return $q->fetch();}

try{
    // ---- ek manzoor-shuda rider ----
    $mobile='9000000779';$code='CI'.bin2hex(random_bytes(4));
    $pdo->prepare("INSERT INTO users(name,username,password,role,mobile,active)
                   VALUES ('CI Ek Darwaza Rider',?,?,'rider',?,1)")
        ->execute([$mobile,password_hash($code,PASSWORD_DEFAULT),$mobile]);
    $rider=(int)$pdo->lastInsertId();

    $no='CI-ED-'.bin2hex(random_bytes(4));
    $pdo->prepare("INSERT INTO orders(order_no,code,customer_name,mobile,village,items,status,delivery_user)
                   VALUES (?,'4321','CI Graahak','9000000088','CI ED Area','test saaman','Assign',?)")
        ->execute([$no,$rider]);
    $oid=(int)$pdo->lastInsertId();

    // ================= 1. rider ko ek hi darwaza =================
    $html=ed_http('/sarathi/?purana=1')['html'];
    ed_http('/sarathi/',['csrf'=>ed_csrf($html),'who'=>$mobile,'pass'=>$code],302);

    $r=ed_http('/delivery/',null,302);
    ed_check(strpos($r['to'],'/sarathi/kaam.php')!==false,
             'rider ko /delivery/ se /sarathi/ par jana chahiye, gaya: '.$r['to']);

    // purana bookmark toota nahi — mod kar sahi jagah pahunchta hai
    $r=ed_http('/delivery/',null,200,true);
    ed_check(strpos($r['html'],'कोई काम नहीं')!==false||strpos($r['html'],'CI Graahak')!==false,
             'purana link mod kar sarathi ka kaam dikhana chahiye');

    // saaman ki photo wala panna rider ke liye khula rehna chahiye --
    // Maakit ke paas saaman rehta hi nahi, soochi isi se bharti hai
    ed_http('/delivery/photo.php',null,200);

    $html=ed_http('/sarathi/kaam.php')['html'];$csrf=ed_csrf($html);
    ed_check(strpos($html,'बिल')!==false,'bill ka khaana /sarathi/ par dikhna chahiye');

    // ================= 2. दुकान का बिल — /sarathi/ से =================
    // Panna khulte hi dukaan ka Pick OTP apne aap bhar jata hai, isliye
    // use yahan database se padhte hain -- dukaandar ko bhi wahi dikhta hai.
    $otp=(string)ed_row($oid)['pick_otp'];
    ed_check($otp!=='','panna khulte hi Pick OTP bhar jana chahiye');
    ed_http('/sarathi/kaam.php',['csrf'=>$csrf,'do'=>'uthaya','oid'=>$oid,'otp'=>'0000'],302);
    ed_check(ed_row($oid)['status']!=='Pickup','galat dukaan OTP par uthana darj NAHI hona chahiye');
    ed_http('/sarathi/kaam.php',['csrf'=>$csrf,'do'=>'uthaya','oid'=>$oid,'otp'=>$otp],302);
    ed_check(ed_row($oid)['status']==='Pickup','sahi dukaan OTP par uthaya darj hona chahiye');

    $bill=tempnam(sys_get_temp_dir(),'ed-bill').'.png';
    $im=imagecreatetruecolor(2,2);imagepng($im,$bill);imagedestroy($im);
    $mk=fn($amt)=>['csrf'=>$csrf,'do'=>'bill','oid'=>$oid,'amount'=>$amt,
                   'bill'=>new CURLFile($bill,'image/png','bill.png')];

    // galat rakam -> na photo rukni chahiye, na daam
    foreach(['-1','1.5','1e3','1000001'] as $bad){
        ed_post_file('/sarathi/kaam.php',$mk($bad));
        $row=ed_row($oid);
        ed_check(empty($row['bill_photo']),"galat rakam '$bad' par bill nahi lagna chahiye");
    }

    // sahi rakam -> dono rukne chahiye
    ed_post_file('/sarathi/kaam.php',$mk('250'));
    $row=ed_row($oid);$saved=$row['bill_photo'];
    ed_check(!empty($saved)&&(int)$row['goods_amount']===250,'sahi bill daam aur photo dono rakhta hai');

    // ================= 3. "आ रहा हूँ" wala sandesh =================
    $html=ed_http('/sarathi/kaam.php')['html'];
    ed_check(strpos($html,'आ रहा हूँ')!==false,'"aa raha hoon" ka button /sarathi/ par hona chahiye');
    ed_check(strpos($html,'wa.me')!==false||strpos($html,'whatsapp')!==false,
             'wo button WhatsApp par jana chahiye');

    // ================= 4. graahak ka code abhi bhi zaroori =================
    $csrf=ed_csrf($html);
    ed_http('/sarathi/kaam.php',['csrf'=>$csrf,'do'=>'de_diya','oid'=>$oid,'otp'=>'9999'],302);
    ed_check(ed_row($oid)['status']==='Pickup','galat code par delivery darj NAHI honi chahiye');
    ed_http('/sarathi/kaam.php',['csrf'=>$csrf,'do'=>'de_diya','oid'=>$oid,'otp'=>'4321'],302);
    ed_check(ed_row($oid)['status']==='Delivered','sahi code par delivery darj honi chahiye');

    // nipat chuke order ka bill badla na ja sake
    ed_post_file('/sarathi/kaam.php',$mk('999'));
    $row=ed_row($oid);
    ed_check((int)$row['goods_amount']===250&&$row['bill_photo']===$saved,
             'nipte hue order ka bill badalna nahi chahiye');

    // ================= 5. daftar ka haath bacha hua hai =================
    // /delivery/ mitaya nahi gaya. Puri Maakit me yahi akeli jagah hai
    // jahan se admin rider ki jagah se kuch darj kar sakta hai.
    @unlink($jar);$jar=tempnam(sys_get_temp_dir(),'mk-ekd-');
    $bosspass=bin2hex(random_bytes(12));
    $pdo->prepare("INSERT INTO users(name,username,password,role,active)
                   VALUES ('CI ED Boss','jaanch-ed-boss',?,'admin',1)")
        ->execute([password_hash($bosspass,PASSWORD_DEFAULT)]);
    $boss=(int)$pdo->lastInsertId();

    $html=ed_http('/login.php?as=team')['html'];
    ed_http('/login.php?as=team',['csrf'=>ed_csrf($html),'do'=>'team',
            'username'=>'jaanch-ed-boss','password'=>$bosspass],302);
    $r=ed_http('/delivery/',null,200);
    ed_check(strpos($r['html'],'दफ़्तर')!==false,'admin ke liye /delivery/ khula rehna chahiye');

    echo "Ek darwaza: rider /sarathi/ par, bill aur sandesh saath aaye, code zaroori, daftar ka haath bacha\n";
}finally{
    if($oid){
        foreach(['live_tracks','sarathi_samasya'] as $t)
            $pdo->prepare("DELETE FROM $t WHERE order_id=?")->execute([$oid]);
        $pdo->prepare('DELETE FROM orders WHERE id=?')->execute([$oid]);
    }
    if($rider){
        foreach(['sarathi_trips'=>'rider_user','driver_availability'=>'user_id'] as $t=>$c)
            $pdo->prepare("DELETE FROM $t WHERE $c=?")->execute([$rider]);
        $pdo->prepare('DELETE FROM users WHERE id=?')->execute([$rider]);
    }
    if($boss)$pdo->prepare('DELETE FROM users WHERE id=?')->execute([$boss]);
    if($bill)@unlink($bill);
    @unlink($jar);
}
