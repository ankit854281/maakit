<?php
// ============================================================
//  जाँच — एक काम
//
//  Ye bot Ankit ko roz EK kaam batata hai. Uski poori kimat ek
//  hi baat par tiki hai:
//
//      JO WO BATAYE, WAHI SACH ME SABSE ZAROORI KAAM HO.
//
//  Agar ye galat kaam bata de, to nuksaan "ek galat line" ka
//  nahi hota. Ankit uss par POORA DIN laga dega, aur jo cheez
//  sach me sab kuch roke hui thi wo ek din aur ruki rahegi.
//
//  Isliye yahan teen cheezein jaanchi jaati hain:
//
//   1. EK HI LAUTE. Do nahi. Agar kabhi list laut aayi, to panna
//      aur bot dono soochi dikhane lagenge -- aur aadmi soochi me
//      se sabse AASAN kaam uthata hai, sabse zaroori nahi. Bot ka
//      poora matlab yahin khatam ho jata hai.
//
//   2. KRAM NA TOOTE. Jo cheez baaki SAB ko roke hui hai wo upar
//      rahe. Dukaanein jodna bekaar hai agar ilaaka hi chalu nahi
//      -- graahak pata hi nahi chun sakta. Isliye "bada" kaam
//      "roz" ke kaam ko HARA de, chahe roz wale ki ginti kitni
//      bhi ho.
//
//   3. KUCH NA RUKA HO TO CHUP RAHE. Jhootha kaam bata dena
//      sabse bura hai -- do din me aadmi bot ko dekhna hi band
//      kar deta hai, aur jis din ASLI kaam aata hai us din bhi
//      nahi dekhta.
//
//  Tareeka: data ko ek lenden (transaction) me badal kar dekha
//  jata hai aur phir wapas palat diya jata hai -- jaanch ka
//  database jaisa tha waisa hi rehta hai.
// ============================================================
require_once __DIR__.'/../../config.php';
if(DB_NAME!=='maakit_jaanch')throw new RuntimeException('CI database only');
require_once __DIR__.'/../../inc/db.php';
require_once __DIR__.'/../../inc/ek-kaam.php';

function ek_check($ok,$m){if(!$ok)throw new RuntimeException('Ek kaam: '.$m);}

// ---- बही और पन्ना एक ही बात कहें ----
// Ye jaanch SABSE PEHLE hoti hai -- neeche OTP ko is process me
// nakli taur par chalu kar diya jata hai, aur tab website wala
// process aur ye process alag jawab denge. Us farq ka matlab kuch
// nahi hota, isliye asli milan pehle hi kar liya jata hai.
// Bot muneem.php se padhta hai, panna seedha function se. Dono
// alag jawab dein to Ankit ke phone par ek kaam aayega aur panne
// par doosra -- aur phir wo dono par bharosa karna chhod dega.
if(defined('MUNEEM_KEY') && MUNEEM_KEY!==''){
    $c=curl_init('http://127.0.0.1:8099/muneem.php?f=json&key='.rawurlencode(MUNEEM_KEY));
    curl_setopt_array($c,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>25,CURLOPT_PROXY=>'']);
    $raw=curl_exec($c);$st=curl_getinfo($c,CURLINFO_HTTP_CODE);curl_close($c);
    ek_check($st===200,"muneem ne $st diya");
    $j=json_decode((string)$raw,true);
    ek_check(is_array($j) && array_key_exists('ek_kaam',$j), 'bahi me ek_kaam honi chahiye');
    $bahi = $j['ek_kaam']['kya'] ?? null;
    $panna = ek_kaam_line($pdo);
    ek_check($bahi===$panna, "bahi aur panna ek hi kaam bataye -- bahi: ".var_export($bahi,true)
                             ." panna: ".var_export($panna,true));
}

// ---- OTP: is jaanch ke liye (aur SIRF is jaanch ke liye) chalu ----
//
// Poori seedhi ka pehla kadam OTP hai. Agar wo band ho, to har
// jaanch me wahi ek jawab aata hai aur neeche ke kadam kabhi
// jaanche hi nahi jate -- aur CI ke config me chaabi kabhi nahi
// hoti.
//
// f2s_config() pehle constant dekhta hai, na mile to ENVIRONMENT
// dekhta hai. Isliye yahan environment me nakli maan daal kar OTP
// "chalu" kar liya jata hai. Ye SIRF is jaanch ke apne process me
// hai -- na config.php chhua jata hai, na website ka vyavhaar
// badalta hai, aur yahan se koi SMS bhejne ki koshish bhi nahi
// hoti (ye file sirf ek_kaam() ko bulati hai).
require_once __DIR__.'/../../smartsarathi/inc/fast2sms.php';
if(!f2s_enabled()){
    putenv('FAST2SMS_ENABLED=1');
    putenv('FAST2SMS_API_KEY=ci-jaanch-nakli-chaabi');
    putenv('FAST2SMS_ROUTE=otp');          // DLT ki zaroorat nahi
}
$otp_hai = f2s_enabled();

/**
 * पूरी चलती हुई दुनिया बना दीजिए — फिर उसमें एक चीज़ तोड़ कर देखिए।
 *
 * Pehle ye jaanch maan baithi thi ki database me ek chalu ilaaka
 * aur ek manzoor dukaan PEHLE SE hai. Jaanch ke database me wo
 * tha, par CI me database har baar KHALI banta hai -- sirf
 * migration se. Isliye wahan "dukaan jodiye" ki jagah "ilaaka
 * chalu kijiye" aata tha, aur jaanch laal ho jaati thi jabki kuch
 * toota nahi tha.
 *
 * Isliye ab jaanch apni duniya KHUD banati hai. Jo jaanch kisi
 * aur ke chhode hue data par tiki ho, wo kisi din bina wajah laal
 * hoti hai -- aur bina wajah ka laal rang poori jaanch ka bharosa
 * kha jata hai.
 */
function ek_duniya(PDO $pdo){
    $pdo->exec("INSERT INTO villages(name,live) VALUES ('CI EK Gaon',1)");
    $g = (int)$pdo->lastInsertId();
    $pdo->exec("INSERT INTO service_area_meta(village_id,delivery_on) VALUES ($g,1)");

    $pdo->exec("INSERT INTO businesses(name,village,mobile,category,status,items_on,approved_at)
                VALUES ('CI EK Dukaan','CI EK Gaon','9000000081','kirana','approved',1,NOW())");
    $b = (int)$pdo->lastInsertId();
    $pdo->exec("INSERT INTO shop_items(business_id,name,unit,price,active,stock)
                VALUES ($b,'CI EK Aata','1 किलो',40,1,'hai')");

    $pdo->exec("INSERT INTO users(name,username,password,role,active,rider_rate)
                VALUES ('CI EK Ramu','jaanch-ek-ramu','x','rider',1,25)");
    $r = (int)$pdo->lastInsertId();

    // Pehla pahunchaya hua order -- aur uska paisa de diya gaya,
    // warna "sarathi ka paisa baaki hai" wala kaam aa jata.
    $pdo->exec("INSERT INTO orders(order_no,code,customer_name,mobile,village,items,status,
                 delivery_user,delivered_at,created_at,updated_at)
                VALUES ('CI-EK-1','1234','CI EK','9000000082','CI EK Gaon','g','Delivered',
                 $r,NOW(),NOW(),NOW())");
    $pdo->exec("INSERT INTO sarathi_khata(rider_user,amount,paid_on,how)
                VALUES ($r,100000,CURDATE(),'nagad')");

    // Aaj ka sandesh le liya -- wo bhi ek kaam hai
    dhindhora_le_liya($pdo);

    return ['gaon'=>$g,'dukaan'=>$b,'rider'=>$r];
}

/** duniya banaiye, usme kuch badliye, dekhiye, aur sab wapas palat dijiye */
function ek_jaanch(PDO $pdo, array $badlav, callable $dekho){
    $pdo->beginTransaction();
    try{
        ek_duniya($pdo);
        foreach($badlav as $sql) $pdo->exec($sql);
        $dekho(ek_kaam($pdo));
    }
    finally{ $pdo->rollBack(); }
}

// ---- 1. EK HI LAUTE, aur uska roop pakka ho ----
// Panna aur bot dono isi roop par tike hain. 'kahan' khali ho
// sakta hai (kaam Maakit ke bahar ka, jaise Fast2SMS ka account).
$ab = ek_kaam($pdo);
ek_check($ab===null || is_array($ab), 'array ya null laut na chahiye');
if($ab!==null){
    foreach(['kya','kyun','kitna'] as $k)
        ek_check(isset($ab[$k]) && trim((string)$ab[$k])!=='', "'$k' bharna chahiye");
    ek_check(array_key_exists('kahan',$ab), "'kahan' ki kunji honi chahiye");
    ek_check(in_array($ab['kitna'],['bada','roz'],true), "kitna 'bada' ya 'roz' ho");
    // Yahi wo jaanch hai jo soochi ko rokti hai: kunjiyan ginti se
    // 5 se zyada hui, matlab andar ek aur kaam ghus aaya.
    ek_check(count($ab)<=5, 'ek hi kaam laute -- milin '.count($ab).' kunjiyan');
    ek_check(!isset($ab[0]), 'soochi nahi, EK kaam laute');
}

// ---- 1क. OTP बंद हो तो वही पहले, चाहे बाक़ी सब तैयार हो ----
// Ye is poori seedhi ki sabse zaroori baat hai: ilaaka, dukaan,
// daam, sarathi aur pehla order -- sab taiyar ho, phir bhi OTP
// pehle aayega. Kyunki uske bina graahak login hi nahi kar sakta,
// aur sau dukaanein judne par bhi pehla order nahi hoga.
if(!$otp_hai){
    $pdo->beginTransaction();
    try{
        ek_duniya($pdo);
        $k = ek_kaam($pdo);
        ek_check($k!==null,'OTP band hai -- kaam aana chahiye tha');
        ek_check($k['kitna']==='bada',"OTP ka kaam 'bada' hona chahiye, mila '{$k['kitna']}'");
        ek_check(mb_strpos($k['kya'],'OTP')!==false,
                 'poori duniya taiyar hone par bhi OTP pehle aana chahiye, aaya: '.$k['kya']);
    } finally { $pdo->rollBack(); }
}

// ---- 2. KRAM: बड़ा काम रोज़ के काम को हराए ----
// Neeche ki jaanch tabhi matlab rakhti hai jab OTP chalu ho --
// warna har jagah wahi OTP ka kaam laut aayega (aur wo sahi hai,
// upar 1क me wahi jaancha gaya). Isliye yahan se aage ki jaanch
// OTP chalu hone par hi chalti hai, aur chhodne par SAAF likha
// jata hai -- chup-chaap chhooti hui jaanch sabse khatarnak hai.
if($otp_hai){
// Dukaan ki manjoori (roz) intezaar me rehne par bhi, agar koi
// dukaan MANJOOR hi nahi hai to pehle wahi kaam aana chahiye --
// kyunki bina dukaan ke saamaan nahi, aur bina saamaan ke order
// nahi. Yahan roz wala kaam ginti me 2 ho jata hai, aur phir bhi
// haar jata hai. Isi ko toot jana sabse mehenga hota hai.
ek_jaanch($pdo, ["UPDATE businesses SET status='pending'"],
    function($k){
        ek_check($k!==null, 'koi dukaan manjoor nahi -- kaam aana chahiye tha');
        ek_check($k['kitna']==='bada', "ye 'bada' kaam hai, mila '{$k['kitna']}'");
        ek_check(strpos($k['kya'],'दुकान')!==false, 'dukaan wala kaam aana chahiye, aaya: '.$k['kya']);
    });

// Ilaaka band ho to dukaan se bhi PEHLE wahi -- kyunki graahak
// apna pata hi nahi chun sakta, to manjoor dukaan bhi bekaar hai.
ek_jaanch($pdo, ['UPDATE villages SET live=0'],
    function($k){
        ek_check($k!==null, 'koi ilaaka chalu nahi -- kaam aana chahiye tha');
        ek_check(strpos($k['kya'],'इलाक़ा')!==false,
                 'ilaake wala kaam dukaan se pehle aana chahiye, aaya: '.$k['kya']);
    });

// ---- 3. रोज़ के कामों में भी क्रम ----
// Graahak ka chhua-ansa order dukaan ki manjoori se upar hai:
// udhar aadmi aaj paisa dene khada hai, idhar dukaan kal bhi
// jud sakti hai.
// Duniya me ek pending dukaan (roz ka kaam) aur ek chhua-ansa
// naya order (uske upar wala roz ka kaam) -- dono daal kar dekha
// jata hai ki upar wala jeeta ya nahi.
ek_jaanch($pdo, [
    "INSERT INTO businesses(name,village,mobile,category,status)
      VALUES ('CI EK Nayi Dukaan','CI EK Gaon','9000000083','kirana','pending')",
    "INSERT INTO orders(order_no,code,customer_name,mobile,village,items,status)
      VALUES ('CI-EK-2','1234','CI EK','9000000082','CI EK Gaon','g','Naya')",
  ],
    function($k){
        ek_check($k!==null, 'naya order pada hai -- kaam aana chahiye tha');
        ek_check($k['kitna']==='roz', "roz ka kaam hona chahiye, mila '{$k['kitna']}'");
        ek_check(strpos($k['kya'],'नए ऑर्डर')!==false,
                 'naye order wala kaam pehle aana chahiye, aaya: '.$k['kya']);
        // Ginti saath me likhi ho -- "2 dukaanein" aur "20 dukaanein"
        // dekh kar aadmi alag faisla leta hai.
        ek_check(preg_match('/^\d+ — /u',$k['kya'])===1, 'ginti saath likhi ho: '.$k['kya']);
    });

// ---- 4. कुछ न रुका हो तो चुप ----
// Jhootha kaam bata dena sabse bura hai -- do din me aadmi bot ko
// dekhna hi band kar deta hai, aur jis din ASLI kaam aata hai us
// din bhi nahi dekhta.
//
// Aaj ka sandesh bhi ek kaam hai, isliye use bhi "le liya" karna
// padta hai -- warna ye jaanch us baat par laal hoti jo sach me
// baaki hai.
require_once __DIR__.'/../../inc/ghanti.php';
require_once __DIR__.'/../../inc/sarathi.php';
$pdo->beginTransaction();
try{
    ek_duniya($pdo);
    // Jaanch ke database me pehle se kuch pada ho to use bhi nipta
    // dijiye -- warna ye jaanch us baat par laal hogi jo SACH ME
    // baaki hai, aur wo jaanch ki galti nahi hogi.
    $pdo->exec("UPDATE businesses SET status='approved' WHERE status='pending'");
    $pdo->exec("UPDATE orders SET status='Delivered' WHERE status IN ('Naya','Confirm','Pickup')");
    $pdo->exec("UPDATE transports SET verified=1 WHERE verified=0");
    $pdo->exec("UPDATE sarathi_samasya SET nipta=1 WHERE nipta=0");
    $pdo->exec("UPDATE support_tickets SET status='closed' WHERE status IN ('new','reviewing')");
    $pdo->exec("UPDATE shop_items SET active=0 WHERE price IS NULL OR price<=0");
    $pdo->exec("UPDATE packs SET status='live' WHERE status='suggested'");
    $pdo->exec("UPDATE users SET rider_rate=25
                 WHERE active=1 AND role IN ('delivery','rider') AND (rider_rate IS NULL OR rider_rate=0)");
    foreach(saathi_baaki($pdo) as $r) sarathi_diya_likho($pdo,$r['id'],$r['baaki'],'nagad','CI jaanch');
    $k = ek_kaam($pdo);
    ek_check($k===null, 'kuch nahi ruka to null laute, aaya: '.json_encode($k,256));
} finally { $pdo->rollBack(); }

} else {
    echo "Ek kaam: OTP band hai -- seedhi ke aage ki jaanch chhodi gayi (wo tabhi matlab rakhti hai jab OTP chalu ho). Asli config me chaabi lagi hai, wahan poori chalti hai.\n";
}

// ---- 5. OTP सबसे ऊपर रहे ----
// Ise chala kar nahi jaancha ja sakta -- CI me chaabi lagi hui
// hai. Par kram khud file me likha hua hai, aur agar kabhi koi
// use neeche sarka de to sabse bada gaddha ban jayega: sau
// dukaanein jud jayein, OTP ke bina pehla order phir bhi nahi
// hoga. Isliye kram ki jagah yahin baandh di gayi hai.
$src = (string)file_get_contents(__DIR__.'/../../inc/ek-kaam.php');
$p_otp = strpos($src,'f2s_enabled');
$p_gao = strpos($src,'service_area_meta');
ek_check($p_otp!==false && $p_gao!==false && $p_otp < $p_gao,
         'OTP ki jaanch ilaake se PEHLE rehni chahiye');
// Aur wo wahi sawaal poochhe jo code khud poochta hai. Pehle yahan
// sirf FAST2SMS_ENABLED dekha jata tha -- yani WhatsApp laga lene
// par bhi ye kaam kabhi poora na hota.
ek_check(strpos($src,'f2s_enabled()')!==false,
         'OTP ka sawaal f2s_enabled() se poocha jaye, apne alag niyam se nahi');

$kya = $ab===null ? 'kuch nahi ruka' : $ab['kya'];
echo $otp_hai
  ? "Ek kaam: ek hi kaam laut raha hai, bada roz ko hara raha hai, kuch na ruke to chup rehta hai, aur bahi-panna ek hi baat kehte hain (abhi: $kya)\n"
  : "Ek kaam: ek hi kaam laut raha hai, OTP poori duniya taiyar hone par bhi pehle aa raha hai, aur bahi-panna ek hi baat kehte hain (abhi: $kya)\n";
