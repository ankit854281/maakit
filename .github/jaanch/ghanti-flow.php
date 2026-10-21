<?php
// ============================================================
//  जाँच — घंटी (जो अभी रुका है) और सारथी का साथी (किसका पैसा बाक़ी)
//
//  Ghanti ka poora matlab ek hi baat par tika hai:
//
//      WO SIRF TAB BAJE JAB AADMI KO ABHI UTHNA CHAHIYE.
//
//  Agar wo har choti baat par bajegi, to do din me Ankit use
//  anna-suna karna seekh lega -- aur jis din graahak sach me
//  intezaar me baitha hoga, us din bhi nahi dekhega. Ek bekar
//  ghanti poore bot ko bekar kar deti hai.
//
//  Isliye yahan ye jaancha jata hai:
//
//   1. TAAZA ORDER PAR GHANTI NA BAJE. Abhi aaye order par
//      bajna sabse aasan galti hai, aur sabse mehengi -- phir
//      ghanti HAR order par bajegi.
//
//   2. PURANE RUKE ORDER PAR ZAROOR BAJE.
//
//   3. KAAM KE WAQT KE BAHAR CHUP RAHE. Raat 2 baje ki ghanti
//      par koi kuch nahi kar sakta.
//
//   4. SARATHI KA PAISA GINTI SE NIKLE, KEHNE SE NAHI -- aur
//      paisa dene ke baad wo baat khatam ho jaye. "Ho gaya" na
//      hone wala kaam poore bot ka bharosa kha jata hai.
// ============================================================
require_once __DIR__.'/../../config.php';
if(DB_NAME!=='maakit_jaanch')throw new RuntimeException('CI database only');
require_once __DIR__.'/../../inc/db.php';
require_once __DIR__.'/../../inc/ghanti.php';

function gh_check($ok,$m){if(!$ok)throw new RuntimeException('Ghanti: '.$m);}

/** ghanti me is baat ki line hai ya nahi */
function gh_hai(array $bajao, $shabd){
    foreach($bajao as $b) if(mb_strpos($b['kya'],$shabd)!==false) return $b;
    return null;
}

// ---- 1. काम के वक़्त के बाहर चुप ----
gh_check(ghanti_waqt(2)===false,'raat 2 baje ghanti nahi bajni chahiye');
gh_check(ghanti_waqt(5)===false,'subah 5 baje ghanti nahi bajni chahiye');
gh_check(ghanti_waqt(23)===false,'raat 11 baje ghanti nahi bajni chahiye');
gh_check(ghanti_waqt(10)===true,'din me 10 baje ghanti bajni chahiye');
gh_check(ghanti_waqt(21)===true,'raat 9 baje tak ghanti bajni chahiye');

$ord=[];$usr=[];
try{
    $pehle = count(ghanti_dekho($pdo));
    // Jaanch ke database me pehle se kuch pada ho sakta hai, isliye
    // pakka number kahin nahi likha jata -- har jagah FARQ dekha
    // jata hai. (Pakka number likhne wali galti ek baar ho chuki
    // hai; AGENTS.md me likhi hui hai.)
    $baaki_pehle = count(saathi_baaki($pdo));
    $rate_pehle  = saathi_rate_nahi($pdo);

    // ---- 2. ताज़ा ऑर्डर पर घंटी न बजे ----
    // Ye sabse zaroori jaanch hai. Abhi aaye order par bajna sabse
    // aasan galti hai -- aur phir ghanti HAR order par bajegi, aur
    // do din me bekar ho jayegi.
    $mk=function($status,$kab,$extra='')use($pdo,&$ord){
        $pdo->prepare("INSERT INTO orders(order_no,code,customer_name,mobile,village,items,status,created_at,updated_at"
                      .($extra?",$extra":"").")
                       VALUES (?,'4321','CI Ghanti','9000000071','CI Gh Gaon','g',?,?,?"
                      .($extra?",?":"").")")
            ->execute($extra
                ? ['CI-GH-'.bin2hex(random_bytes(4)),$status,$kab,$kab,$kab]
                : ['CI-GH-'.bin2hex(random_bytes(4)),$status,$kab,$kab]);
        $id=(int)$pdo->lastInsertId(); $ord[]=$id; return $id;};

    $mk('Naya', date('Y-m-d H:i:s'));                               // abhi aaya
    $b = ghanti_dekho($pdo);
    gh_check(gh_hai($b,'नया ऑर्डर')===null,
             'abhi aaye order par ghanti nahi bajni chahiye -- warna wo har order par bajegi');
    // Ginti badh gayi, par sirf ek wajah se: order khula hai aur
    // koi sarathi duty par nahi. Wo thik hai -- usi ki jaanch neeche
    // (5) hai. "Der se pada order" ki line nahi aani chahiye, aur
    // yahi upar jaancha gaya hai.
    gh_check(count($b) <= $pehle + 1,'taaza order se ghanti me ek se zyada nayi baat nahi aani chahiye');

    // ---- 3. पुराने रुके ऑर्डर पर ज़रूर बजे ----
    $mk('Naya', date('Y-m-d H:i:s', time()-40*60));                 // 40 minute purana
    $b = ghanti_dekho($pdo);
    $x = gh_hai($b,'नया ऑर्डर');
    gh_check($x!==null,'40 minute se pada order par ghanti bajni chahiye');
    gh_check((int)$x['ginti']===1,'sirf wahi order ginna chahiye jo der se pada hai, mili '.$x['ginti']);
    gh_check(!empty($x['kahan']),'ghanti ke saath jaane ka rasta hona chahiye');

    // ---- 4. उठा हुआ पर पहुँचा नहीं ----
    $p = $mk('Pickup', date('Y-m-d H:i:s', time()-3*3600), 'picked_at');
    $b = ghanti_dekho($pdo);
    gh_check(gh_hai($b,'सामान उठा हुआ')!==null,'3 ghante se utha hua order par ghanti bajni chahiye');
    // do ghante se kam par na baje
    $pdo->prepare("UPDATE orders SET picked_at=? WHERE id=?")
        ->execute([date('Y-m-d H:i:s', time()-30*60), $p]);
    gh_check(gh_hai(ghanti_dekho($pdo),'सामान उठा हुआ')===null,
             'aadhe ghante pehle uthe order par ghanti nahi bajni chahiye');

    // ---- 5. ऑर्डर खुले हैं और कोई सारथी ड्यूटी पर नहीं ----
    // Ye ginti nahi, haalat hai -- aur dono shart sach honi chahiye.
    $b = ghanti_dekho($pdo);
    gh_check(gh_hai($b,'कोई सारथी ड्यूटी पर नहीं')!==null,
             'order khule hain aur koi duty par nahi -- ghanti bajni chahiye');

    $pdo->prepare("INSERT INTO users(name,username,password,role,active) VALUES('CI Gh Ramu',?,?,'rider',1)")
        ->execute(['jaanch-gh-ramu', password_hash('gh-pass-123',PASSWORD_DEFAULT)]);
    $rider=(int)$pdo->lastInsertId(); $usr[]=$rider;
    // Rate tay kijiye, warna "baaki paisa" chup-chaap 0 nikalta hai.
    // Usi khatre ki apni jaanch neeche (6क) hai.
    $pdo->prepare("UPDATE users SET rider_rate=25 WHERE id=?")->execute([$rider]);
    // duty UTC me likhi jati hai -- NOW() likhne par ginti saadhe
    // paanch ghante galat ho jati
    $pdo->prepare("INSERT INTO driver_availability(user_id,available_until)
                   VALUES(?,UTC_TIMESTAMP() + INTERVAL 2 HOUR)")->execute([$rider]);
    gh_check(gh_hai(ghanti_dekho($pdo),'कोई सारथी ड्यूटी पर नहीं')===null,
             'sarathi duty par aa gaya -- ye ghanti band honi chahiye');

    // ---- 6. सारथी का पैसा — गिनती से, कहने से नहीं ----
    gh_check(count(saathi_baaki($pdo))===$baaki_pehle,
             'bina pahunchaye order ke naya baaki paisa nahi aana chahiye');
    gh_check(saathi_rate_nahi($pdo)===$rate_pehle,
             'jisne kaam nahi kiya uska rate maangne ki zaroorat nahi');

    // 6 din pehle 5 order pahunchaye -- paisa bana, diya nahi gaya
    for($i=0;$i<5;$i++){
        $kab=date('Y-m-d H:i:s', time()-6*86400);
        $pdo->prepare("INSERT INTO orders(order_no,code,customer_name,mobile,village,items,status,
                        delivery_user,delivered_at,created_at,updated_at)
                       VALUES (?,'4321','CI Ghanti','9000000071','CI Gh Gaon','g','Delivered',?,?,?,?)")
            ->execute(['CI-GHD-'.bin2hex(random_bytes(4)),$rider,$kab,$kab,$kab]);
        $ord[]=(int)$pdo->lastInsertId();
    }
    $baaki = saathi_baaki($pdo);
    gh_check(count($baaki)===$baaki_pehle+1,
             'ek sarathi ka paisa baaki dikhna chahiye, mile '.count($baaki));
    $mera = null; foreach($baaki as $x) if($x['id']===$rider) $mera = $x;
    gh_check($mera!==null,'isi sarathi ka naam aana chahiye');
    $baaki = [$mera];
    gh_check($baaki[0]['baaki']>0,'baaki paisa 0 se zyada hona chahiye');
    gh_check($baaki[0]['din']>=5,'kitne din se baaki hai ye sach bolna chahiye, mila '.$baaki[0]['din']);

    // ---- 6क. रेट ही तय न हो तो वो पहले माँगा जाए ----
    // Bina rate ke hisab CHUP-CHAAP 0 nikalta hai. Ankit ko lagta
    // hai sab chukta hai, aur sarathi ko lagta hai usay thaga gaya.
    // Chup-chaap galat ginti sabse khatarnak hoti hai.
    $pdo->prepare("UPDATE users SET rider_rate=0 WHERE id=?")->execute([$rider]);
    gh_check(saathi_rate_nahi($pdo)===$rate_pehle+1,'kaam kiya par rate nahi -- ye batana chahiye');
    gh_check(count(saathi_baaki($pdo))===$baaki_pehle,'bina rate ke paisa ginna hi nahi chahiye');
    $pdo->prepare("UPDATE users SET rider_rate=25 WHERE id=?")->execute([$rider]);
    gh_check(count(saathi_baaki($pdo))===$baaki_pehle+1,'rate wapas aane par hisab phir dikhna chahiye');

    // ---- 7. पैसा देने के बाद बात ख़त्म ----
    // Warna ye kaam kabhi "ho gaya" na hota, aur aisa kaam poore
    // bot ka bharosa kha jata hai.
    require_once __DIR__.'/../../inc/sarathi.php';
    sarathi_diya_likho($pdo,$rider,$baaki[0]['baaki'],'nagad','CI jaanch');
    foreach(saathi_baaki($pdo) as $x)
        gh_check($x['id']!==$rider,'paisa dene ke baad is sarathi ki baat khatam honi chahiye');

    // ---- 8. छोटा बाक़ी और ताज़ा बाक़ी पर घंटी न बजे ----
    // 20 rupaye ke liye aadmi ko uthana shor hai.
    $kab=date('Y-m-d H:i:s');
    $pdo->prepare("INSERT INTO orders(order_no,code,customer_name,mobile,village,items,status,
                    delivery_user,delivered_at,created_at,updated_at)
                   VALUES (?,'4321','CI Ghanti','9000000071','CI Gh Gaon','g','Delivered',?,?,?,?)")
        ->execute(['CI-GHD-'.bin2hex(random_bytes(4)),$rider,$kab,$kab,$kab]);
    $ord[]=(int)$pdo->lastInsertId();
    foreach(saathi_baaki($pdo) as $x)
        gh_check($x['id']!==$rider,'aaj ka baaki paisa turant nahi maangna chahiye');

    echo "Ghanti: taaza order par chup rahi, der se ruke order par baji, aadhe ghante pehle uthe par chup, duty wala sarathi aane par band hui, kaam ke waqt ke bahar bajti hi nahi, aur sarathi ka baaki paisa ginti se nikla aur dene ke baad khatam ho gaya\n";
}finally{
    foreach($ord as $id)$pdo->prepare('DELETE FROM orders WHERE id=?')->execute([$id]);
    foreach($usr as $id){
        $pdo->prepare('DELETE FROM sarathi_khata WHERE rider_user=?')->execute([$id]);
        $pdo->prepare('DELETE FROM driver_availability WHERE user_id=?')->execute([$id]);
        $pdo->prepare('DELETE FROM users WHERE id=?')->execute([$id]);
    }
}
