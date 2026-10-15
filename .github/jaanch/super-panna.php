<?php
// ============================================================
//  जाँच — मालिक का एक पन्ना (/admin/super.php)
//
//  Ye panna 9 ginti dikhata hai aur har ginti par Ankit faisla
//  leta hai. Galat ginti dikhana kuch na dikhane se bura hai --
//  "kuch nahi ruka" dikha kar das order roke rakhna sabse bura.
//
//  Isliye yahan har ginti ko database se mila kar dekha jata hai,
//  aur ye bhi ki:
//
//    - khali haal me "kuch nahi ruka" dikhta hai
//    - jiski ginti shunya hai wo line dikhti hi nahi
//    - panna sirf admin ke liye khulta hai
//    - Smart Sarathi ka database yahan se CHHUA tak nahi jata
//      (wo alag dhandha hai -- ye jaanbujh kar hai, bhool nahi)
// ============================================================
require_once __DIR__.'/../../config.php';
if(DB_NAME!=='maakit_jaanch')throw new RuntimeException('CI database only');
require_once __DIR__.'/../../inc/db.php';

function sp_check($ok,$m){if(!$ok)throw new RuntimeException('Super panna: '.$m);}
$jar=tempnam(sys_get_temp_dir(),'mk-sp-');$boss=0;$rider=0;$made=[];

function sp_http($path,$post=null,$expected=200){
    global $jar;
    $c=curl_init('http://127.0.0.1:8099'.$path);
    curl_setopt_array($c,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_COOKIEFILE=>$jar,CURLOPT_COOKIEJAR=>$jar,
                          CURLOPT_TIMEOUT=>20,CURLOPT_PROXY=>'']);
    if($post!==null)curl_setopt_array($c,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>http_build_query($post)]);
    $html=curl_exec($c);$st=curl_getinfo($c,CURLINFO_HTTP_CODE);curl_close($c);
    sp_check($st===$expected&&is_string($html)&&!preg_match('/Fatal error|Warning:|Uncaught/',$html),
             "HTTP $path gave $st");
    return $html;
}
function sp_csrf($h){sp_check(preg_match('/name="csrf" value="([^"]+)"/',$h,$m)===1,'csrf missing');return $m[1];}
/** panne par dikhi hui ek ginti — na dikhe to null */
function sp_line($html,$needle){
    if(!preg_match_all('#<a class="sp-ruk"[^>]*>\s*<b>(\d+)</b>\s*<span>([^<]*)#',$html,$m,PREG_SET_ORDER))return null;
    foreach($m as $row)if(strpos($row[2],$needle)!==false)return (int)$row[1];
    return null;
}

try{
    // ---- ek admin ----
    $pass=bin2hex(random_bytes(12));
    $pdo->prepare("INSERT INTO users(name,username,password,role,active)
                   VALUES ('CI SP Boss','jaanch-sp-boss',?,'admin',1)")
        ->execute([password_hash($pass,PASSWORD_DEFAULT)]);
    $boss=(int)$pdo->lastInsertId();

    // ---- panna bina login ke na khule ----
    sp_http('/admin/super.php',null,302);

    $html=sp_http('/login.php?as=team');
    sp_http('/login.php?as=team',['csrf'=>sp_csrf($html),'do'=>'team',
            'username'=>'jaanch-sp-boss','password'=>$pass],302);

    // ---- 1. khali haal ----
    // CI database me pehle se kuch ruka ho sakta hai, isliye pehle
    // ginti le lete hain -- baad me usi se tulna hogi.
    $html=sp_http('/admin/super.php?lang=en');
    $naye_pehle=(int)sp_line($html,'new orders nobody has touched');
    sp_check(strpos($html,'Every door')!==false,'saare darwaze ka hissa hona chahiye');
    sp_check(substr_count($html,'class="sp-link"')>=18,'har panel ka link hona chahiye');

    // ---- 2. ginti sach bole ----
    foreach(['Naya','Naya','Confirm'] as $i=>$st){
        $no='CI-SP-'.bin2hex(random_bytes(4));
        $pdo->prepare("INSERT INTO orders(order_no,code,customer_name,mobile,village,items,status)
                       VALUES (?,'1234','CI SP','9000000099','CI SP Area','g',?)")->execute([$no,$st]);
        $made[]=(int)$pdo->lastInsertId();
    }
    $html=sp_http('/admin/super.php?lang=en');
    sp_check(sp_line($html,'new orders nobody has touched')===$naye_pehle+2,
             'do naye order judne par ginti do badhni chahiye');

    $db=(int)$pdo->query("SELECT COUNT(*) FROM orders WHERE status='Confirm' AND delivery_user IS NULL")->fetchColumn();
    sp_check(sp_line($html,'confirmed orders with no rider')===$db,
             'bina sarathi wale order ki ginti database se mel khani chahiye');

    // teen ghante se utha hua -- ye wo line hai jo sach me phone
    // karwati hai, isliye iska samay bhi jaancha jata hai
    $no='CI-SP-'.bin2hex(random_bytes(4));
    $pdo->prepare("INSERT INTO orders(order_no,code,customer_name,mobile,village,items,status,picked_at)
                   VALUES (?,'1234','CI SP','9000000099','CI SP Area','g','Pickup',NOW()-INTERVAL 5 HOUR)")
        ->execute([$no]);
    $made[]=$old=(int)$pdo->lastInsertId();
    $html=sp_http('/admin/super.php?lang=en');
    sp_check(sp_line($html,'picked up over 3 hours ago')>=1,'paanch ghante purana utha hua dikhna chahiye');

    // abhi-abhi utha hua order is line me NAHI aana chahiye
    // Dhyan: ginti shunya hone par line GAYAB ho jati hai, isliye
    // null ko bhi shunya hi maana jata hai.
    $before=(int)sp_line($html,'picked up over 3 hours ago');
    $pdo->prepare("UPDATE orders SET picked_at=NOW() WHERE id=?")->execute([$old]);
    $html=sp_http('/admin/super.php?lang=en');
    sp_check((int)sp_line($html,'picked up over 3 hours ago')===$before-1,
             'abhi uthaya hua order "teen ghante se ruka" me nahi aana chahiye');
    if($before===1)sp_check(sp_line($html,'picked up over 3 hours ago')===null,
             'shunya hote hi wo line poori gayab honi chahiye');

    // ---- 3. shunya wali line dikhni hi nahi chahiye ----
    foreach($made as $id)$pdo->prepare('DELETE FROM orders WHERE id=?')->execute([$id]);
    $made=[];
    $html=sp_http('/admin/super.php?lang=en');
    sp_check((int)sp_line($html,'new orders nobody has touched')===$naye_pehle,
             'order hat jane par ginti wapas pehle jaisi honi chahiye');

    // ---- 4. Smart Sarathi ka database chhua tak na jaye ----
    // Wo alag dhandha hai. Ye panna sirf link deta hai.
    $src=file_get_contents(__DIR__.'/../../admin/super.php');
    foreach(['SR_DB_','smartsarathi/inc/config.php\';','FROM jobs','FROM riders','FROM clients'] as $bura){
        if($bura==='smartsarathi/inc/config.php\';')continue;  // is_file() theek hai, require nahi
        sp_check(strpos($src,$bura)===false,
                 "super.php Sarathi ke database me jhaank raha hai: $bura");
    }
    sp_check(strpos($src,"require")===false||!preg_match('#require[^;\n]*smartsarathi#',$src),
             'super.php ko Sarathi ki koi file kheenchni nahi chahiye');
    sp_check(strpos($src,'/smartsarathi/admin/')!==false,'Sarathi ka link hona chahiye');

    // ---- 5. sirf admin ----
    @unlink($jar);$jar=tempnam(sys_get_temp_dir(),'mk-sp-');
    $mobile='9000000778';$code='CI'.bin2hex(random_bytes(4));
    $pdo->prepare("INSERT INTO users(name,username,password,role,mobile,active)
                   VALUES ('CI SP Rider',?,?,'rider',?,1)")
        ->execute([$mobile,password_hash($code,PASSWORD_DEFAULT),$mobile]);
    $rider=(int)$pdo->lastInsertId();
    $html=sp_http('/sarathi/?purana=1');
    sp_http('/sarathi/',['csrf'=>sp_csrf($html),'who'=>$mobile,'pass'=>$code],302);
    sp_http('/admin/super.php',null,302);   // rider ko andar nahi ghusna chahiye

    echo "Super panna: ginti sach bolti hai, shunya chhupti hai, Sarathi ka DB chhua nahi, sirf admin ke liye\n";
}finally{
    foreach($made as $id)$pdo->prepare('DELETE FROM orders WHERE id=?')->execute([$id]);
    foreach([$boss,$rider] as $id)if($id)$pdo->prepare('DELETE FROM users WHERE id=?')->execute([$id]);
    @unlink($jar);
}
