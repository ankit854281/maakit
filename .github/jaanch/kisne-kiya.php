<?php
// ============================================================
//  जाँच — किसने क्या किया (/admin/staff.php)
//
//  Is panne par Ankit ye tay karega ki kaun kaam kar raha hai aur
//  kaun nahi. Isliye yahan ek bhi ginti galat hui, to nuksaan
//  paise ka nahi -- KISI AADMI KE SAATH ANYAY ka hoga. Ya mehnat
//  karne wale ko kam dikhayega, ya na karne wale ko zyada.
//
//  Teen cheezein jaanchi jaati hain:
//
//   1. GINTI SACH BOLE -- jitna kaam database me hai, utna hi
//      dikhe. Na kam, na zyada.
//
//   2. "JODI" AUR "CHALI" ALAG RAHEN -- bees adhoori dukaanon se
//      teen chalti dukaanein behtar hain. Agar dono ek ho gaye to
//      aadmi ginti badhane lagega, kaam nahi.
//
//   3. KISI AUR KA KAAM KISI KE KHATE ME NA JAYE -- aur jo order
//      graahak ne khud website se kiya, wo kisi ke khate me na jaye.
//
//  Aur panna sirf maalik ke liye khule.
// ============================================================
require_once __DIR__.'/../../config.php';
if(DB_NAME!=='maakit_jaanch')throw new RuntimeException('CI database only');
require_once __DIR__.'/../../inc/db.php';

function kk_check($ok,$m){if(!$ok)throw new RuntimeException('Kisne kiya: '.$m);}
$jar=tempnam(sys_get_temp_dir(),'mk-kk-');$boss=0;$bpo=0;$rider=0;$biz=[];$ord=[];

function kk_http($path,$post=null,$expected=200){
    global $jar;
    $c=curl_init('http://127.0.0.1:8099'.$path);
    curl_setopt_array($c,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_COOKIEFILE=>$jar,CURLOPT_COOKIEJAR=>$jar,
                          CURLOPT_TIMEOUT=>20,CURLOPT_PROXY=>'']);
    if($post!==null)curl_setopt_array($c,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>http_build_query($post)]);
    $h=curl_exec($c);$st=curl_getinfo($c,CURLINFO_HTTP_CODE);curl_close($c);
    kk_check($st===$expected&&is_string($h)&&!preg_match('/Fatal error|Warning:|Uncaught/',$h),"HTTP $path gave $st");
    return $h;
}
function kk_csrf($h){kk_check(preg_match('/name="csrf" value="([^"]+)"/',$h,$m)===1,'csrf missing');return $m[1];}

/** panne par ek aadmi ki ek ginti — na mile to null */
function kk_num($html,$naam,$label){
    if(!preg_match('#<b>'.preg_quote($naam,'#').'</b>(.*?)(?=<div class="st-p">|<div class="st-note">)#s',$html,$m))return null;
    if(!preg_match('#<b>(\d+)</b><small>'.preg_quote($label,'#').'</small>#u',$m[1],$n))return null;
    return (int)$n[1];
}

try{
    $mk=function($n,$un,$role)use($pdo){
        $pdo->prepare("INSERT INTO users(name,username,password,role,active) VALUES (?,?,?,?,1)")
            ->execute([$n,$un,password_hash('kk-pass-123',PASSWORD_DEFAULT),$role]);
        return (int)$pdo->lastInsertId();};
    $boss=$mk('CI KK Boss','jaanch-kk-boss','admin');
    $bpo =$mk('CI KK Rekha','jaanch-kk-rekha','bpo');
    $rider=$mk('CI KK Ramu','jaanch-kk-ramu','rider');

    // Rekha: 5 order daale -> 3 pahunche, 1 radd, 1 chalu
    foreach([['Delivered',3],['Cancel',1],['Confirm',1]] as [$stt,$n])for($i=0;$i<$n;$i++){
        $pdo->prepare("INSERT INTO orders(order_no,code,customer_name,mobile,village,items,status,staff_user,delivery_user,delivered_at)
                       VALUES (?,'1234','CI KK','9000000077','CI KK Gaon','g',?,?,?,?)")
            ->execute(['CI-KK-'.bin2hex(random_bytes(4)),$stt,$bpo,
                       $stt==='Delivered'?$rider:null,$stt==='Delivered'?date('Y-m-d H:i:s'):null]);
        $ord[]=(int)$pdo->lastInsertId();
    }
    // Graahak ne khud 2 kiye -- kisi ka kaam nahi
    for($i=0;$i<2;$i++){
        $pdo->prepare("INSERT INTO orders(order_no,code,customer_name,mobile,village,items,status)
                       VALUES (?,'5678','CI KK','9000000078','CI KK Gaon','g','Naya')")
            ->execute(['CI-KKW-'.bin2hex(random_bytes(4))]);
        $ord[]=(int)$pdo->lastInsertId();
    }
    // Rekha: 3 dukaan -- manzoor+daam / manzoor bina daam / pending
    foreach([['approved',1],['approved',0],['pending',0]] as $i=>[$stt,$daam]){
        $pdo->prepare("INSERT INTO businesses(added_by,name,village,mobile,category,status,approved_by,approved_at)
                       VALUES (?,?,'CI KK Gaon',?,'kirana',?,?,?)")
            ->execute([$bpo,'CI KK Dukaan '.$i,'90000001'.(10+$i),$stt,
                       $stt==='approved'?$boss:null,$stt==='approved'?date('Y-m-d H:i:s'):null]);
        $bid=(int)$pdo->lastInsertId();$biz[]=$bid;
        if($daam)$pdo->prepare("INSERT INTO shop_items(business_id,name,active,price) VALUES (?,'CI KK Aata',1,50)")->execute([$bid]);
    }

    // ---- sirf maalik ke liye ----
    kk_http('/admin/staff.php',null,302);

    $h=kk_http('/login.php?as=team');
    kk_http('/login.php?as=team',['csrf'=>kk_csrf($h),'do'=>'team',
            'username'=>'jaanch-kk-rekha','password'=>'kk-pass-123'],302);
    kk_http('/admin/staff.php',null,302);      // BPO ko bhi nahi
    @unlink($jar);$jar=tempnam(sys_get_temp_dir(),'mk-kk-');

    $h=kk_http('/login.php?as=team');
    kk_http('/login.php?as=team',['csrf'=>kk_csrf($h),'do'=>'team',
            'username'=>'jaanch-kk-boss','password'=>'kk-pass-123'],302);
    $html=kk_http('/admin/staff.php?din=7&lang=hi');

    // ---- 1. ginti sach bole ----
    kk_check(kk_num($html,'CI KK Rekha','ऑर्डर डाले')===5,'Rekha ke 5 order dikhne chahiye');
    kk_check(kk_num($html,'CI KK Rekha','उनमें से पहुँचे')===3,'unme se 3 pahunche dikhne chahiye');
    kk_check(kk_num($html,'CI KK Rekha','रद्द हुए')===1,'1 radd dikhna chahiye');
    kk_check(kk_num($html,'CI KK Ramu','पहुँचाए')===3,'Ramu ke 3 pahunchaye dikhne chahiye');
    kk_check(kk_num($html,'CI KK Boss','दुकानें मंज़ूर कीं')===2,'Boss ki 2 manzoori dikhni chahiye');

    // ---- 2. "jodi" aur "chali" alag rahen ----
    // Yahi is panne ki jaan hai. Teen jodi, par chalti sirf EK --
    // kyunki baaki do me ya manzoori nahi, ya daam nahi bhare.
    kk_check(kk_num($html,'CI KK Rekha','दुकानें जोड़ीं')===3,'3 dukaan jodi dikhni chahiye');
    kk_check(kk_num($html,'CI KK Rekha','उनमें से चल रहीं')===1,
             'sirf 1 chalti dikhni chahiye -- manzoori AUR daam, dono zaroori');

    // daam bharte hi doosri bhi chalne lage
    $pdo->prepare("INSERT INTO shop_items(business_id,name,active,price) VALUES (?,'CI KK Chawal',1,60)")->execute([$biz[1]]);
    $html=kk_http('/admin/staff.php?din=7&lang=hi');
    kk_check(kk_num($html,'CI KK Rekha','उनमें से चल रहीं')===2,'daam bharte hi chalti ginti badhni chahiye');

    // ---- 3. kisi aur ka kaam kisi ke khate me na jaye ----
    kk_check(kk_num($html,'CI KK Boss','ऑर्डर डाले')===null,'Boss ke khate me koi order nahi hona chahiye');
    kk_check(kk_num($html,'CI KK Ramu','दुकानें जोड़ीं')===null,'Ramu ke khate me koi dukaan nahi honi chahiye');
    kk_check(preg_match('#<b>2</b>/7#',$html)===1,
             'website se aaye 2/7 order kisi ke khate me nahi, alag likhe hone chahiye');

    // ---- "aaj" me sirf aaj ka ----
    $pdo->prepare("UPDATE orders SET created_at = NOW() - INTERVAL 3 DAY WHERE staff_user=?")->execute([$bpo]);
    $html=kk_http('/admin/staff.php?din=1&lang=hi');
    kk_check(kk_num($html,'CI KK Rekha','ऑर्डर डाले')===null,'3 din purane order "aaj" me nahi dikhne chahiye');
    $html=kk_http('/admin/staff.php?din=7&lang=hi');
    kk_check(kk_num($html,'CI KK Rekha','ऑर्डर डाले')===5,'par "7 din" me dikhne chahiye');

    echo "Kisne kiya: ginti sach boli, jodi aur chali alag rahin, kisi aur ka kaam kisi ke khate me nahi gaya\n";
}finally{
    foreach($ord as $id)$pdo->prepare('DELETE FROM orders WHERE id=?')->execute([$id]);
    foreach($biz as $id){
        $pdo->prepare('DELETE FROM shop_items WHERE business_id=?')->execute([$id]);
        $pdo->prepare('DELETE FROM businesses WHERE id=?')->execute([$id]);
    }
    foreach([$boss,$bpo,$rider] as $id)if($id)$pdo->prepare('DELETE FROM users WHERE id=?')->execute([$id]);
    @unlink($jar);
}
