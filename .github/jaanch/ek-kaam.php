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

/** data badliye, dekhiye, aur sab wapas palat dijiye */
function ek_jaanch(PDO $pdo, array $badlav, callable $dekho){
    $pdo->beginTransaction();
    try{ foreach($badlav as $sql) $pdo->exec($sql); $dekho(ek_kaam($pdo)); }
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

// ---- 2. KRAM: बड़ा काम रोज़ के काम को हराए ----
// Dukaan ki manjoori (roz) intezaar me rehne par bhi, agar koi
// dukaan MANJOOR hi nahi hai to pehle wahi kaam aana chahiye --
// kyunki bina dukaan ke saamaan nahi, aur bina saamaan ke order
// nahi. Yahan roz wala kaam ginti me 2 ho jata hai, aur phir bhi
// haar jata hai. Isi ko toot jana sabse mehenga hota hai.
ek_jaanch($pdo, ["UPDATE businesses SET status='pending' WHERE status='approved'"],
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
ek_jaanch($pdo, ["UPDATE orders SET status='Naya' WHERE status='Pickup'"],
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
$pdo->beginTransaction();
try{
    $pdo->exec("UPDATE businesses SET status='approved' WHERE status='pending'");
    dhindhora_le_liya($pdo);
    // Sarathi ka rate tay kijiye aur uska baaki paisa de dijiye --
    // dono ek kaam hain, aur dono nipta dene par hi "kuch nahi
    // ruka" sach hota hai.
    require_once __DIR__.'/../../inc/ghanti.php';
    require_once __DIR__.'/../../inc/sarathi.php';
    $pdo->exec("UPDATE users SET rider_rate=25
                 WHERE active=1 AND role IN ('delivery','rider') AND (rider_rate IS NULL OR rider_rate=0)");
    foreach(saathi_baaki($pdo) as $r) sarathi_diya_likho($pdo,$r['id'],$r['baaki'],'nagad','CI jaanch');
    $k = ek_kaam($pdo);
    ek_check($k===null, 'kuch nahi ruka to null laute, aaya: '.json_encode($k,256));
} finally { $pdo->rollBack(); }

// ---- 5. OTP सबसे ऊपर रहे ----
// Ise chala kar nahi jaancha ja sakta -- CI me chaabi lagi hui
// hai. Par kram khud file me likha hua hai, aur agar kabhi koi
// use neeche sarka de to sabse bada gaddha ban jayega: sau
// dukaanein jud jayein, OTP ke bina pehla order phir bhi nahi
// hoga. Isliye kram ki jagah yahin baandh di gayi hai.
$src = (string)file_get_contents(__DIR__.'/../../inc/ek-kaam.php');
$p_otp = strpos($src,'FAST2SMS_ENABLED');
$p_gao = strpos($src,'service_area_meta');
ek_check($p_otp!==false && $p_gao!==false && $p_otp < $p_gao,
         'OTP ki jaanch ilaake se PEHLE rehni chahiye');

// ---- 6. बही और पन्ना एक ही बात कहें ----
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

$kya = $ab===null ? 'kuch nahi ruka' : $ab['kya'];
echo "Ek kaam: ek hi kaam laut raha hai, bada roz ko hara raha hai, kuch na ruke to chup rehta hai, aur bahi-panna ek hi baat kehte hain (abhi: $kya)\n";
