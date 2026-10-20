<?php
// ============================================================
//  जाँच — मुनीम का मुनाफ़ा-नुक़सान
//
//  Ye wo ek nambar hai jis par Ankit faisla lega: dhandha badhana
//  hai ya rate badalna hai. Agar ye galat hua, to nuksaan ek
//  hafte ka nahi -- galat faisle ka hoga.
//
//  Teen cheezein jaanchi jaati hain, aur teeno alag-alag tarah se
//  mehengi padti hain:
//
//   1. GINTI SACH BOLE -- aaya, gaya, bacha, aur per order.
//      Ghaata ghaata dikhe, chhupa nahi.
//
//   2. SAAMAAN KA PAISA KABHI NA GINA JAYE. Wo graahak se seedha
//      dukaan ko jata hai; Maakit use chhuta tak nahi. Agar wo
//      kamai me jud gaya, to muneem Maakit ko apne se KAI GUNA
//      BADA dikhayega -- aur usi jhooth par Ankit log rakh lega.
//      Yahi is poori file ki sabse zaroori jaanch hai.
//
//   3. JIS DIN KA KHARCHA DARJ NAHI, WO SAAF LIKHA JAYE. Bina
//      kharche ke din munafa apne aap bada dikhta hai. Muneem ko
//      jhootha bharosa dene ka haq nahi hai.
// ============================================================
require_once __DIR__.'/../../config.php';
if(DB_NAME!=='maakit_jaanch')throw new RuntimeException('CI database only');
require_once __DIR__.'/../../inc/db.php';

function mf_check($ok,$m){if(!$ok)throw new RuntimeException('Munafa: '.$m);}
$ord=[];$kh=[];

function mf_bahi(){
    if(!defined('MUNEEM_KEY')||MUNEEM_KEY==='')return null;   // chaabi na ho to chhod dijiye
    $c=curl_init('http://127.0.0.1:8099/muneem.php?f=json&key='.rawurlencode(MUNEEM_KEY));
    curl_setopt_array($c,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>25,CURLOPT_PROXY=>'']);
    $raw=curl_exec($c);$st=curl_getinfo($c,CURLINFO_HTTP_CODE);curl_close($c);
    mf_check($st===200,"muneem gave $st");
    $j=json_decode((string)$raw,true);
    mf_check(is_array($j),'muneem ne JSON nahi diya');
    return $j;
}

try{
    if(!defined('MUNEEM_KEY')||MUNEEM_KEY===''){
        echo "Munafa: MUNEEM_KEY nahi hai, jaanch chhodi gayi\n"; return;
    }

    // ---- saaf shuruaat: purana CI data hata dijiye ----
    $pdo->exec("DELETE FROM orders WHERE order_no LIKE 'CI-MF-%'");
    $pehle = mf_bahi();
    $p0 = $pehle['paisa'] ?? null;
    mf_check(is_array($p0),'bahi me "paisa" ka hissa hona chahiye');
    foreach(['aaya','gaya','bacha','per_order','din_darj','din_adhoore'] as $k)
        mf_check(array_key_exists($k,$p0),"paisa me '$k' hona chahiye");

    $aaya0 = (int)$p0['aaya'];
    $gaya0 = (int)$p0['gaya'];

    // ---- 10 delivery, har ek ka charge ₹40, aur SAAMAN ₹500 ka ----
    // Saaman ka paisa jaanbujh kar bada rakha gaya hai: agar wo
    // galti se kamai me jud gaya, to ginti saaf phat jayegi.
    for($i=0;$i<10;$i++){
        $pdo->prepare("INSERT INTO orders(order_no,code,customer_name,mobile,village,items,status,delivery_charge,goods_amount,delivered_at)
                       VALUES (?,'1234','CI MF','9000000091','CI MF Gaon','g','Delivered',40,500,NOW())")
            ->execute(['CI-MF-'.bin2hex(random_bytes(4))]);
        $ord[]=(int)$pdo->lastInsertId();
    }

    $b = mf_bahi(); $p = $b['paisa'];

    // ---- 1. aaya theek gina ----
    mf_check((int)$p['aaya'] === $aaya0 + 400,
             "aaya ₹".($aaya0+400)." hona chahiye, mila ₹".$p['aaya']);

    // ---- 2. SAAMAN KA PAISA KABHI NA JUDE ----
    // 10 order par ₹5,000 ka saaman tha. Agar wo kahin bhi jud
    // gaya hota, to aaya ₹5,400 ya usse upar dikhta.
    mf_check((int)$p['aaya'] < $aaya0 + 1000,
             'saaman ka paisa (₹5,000) kamai me NAHI judna chahiye -- mila ₹'.$p['aaya']);

    // ---- 3. kharcha se ghaata banta hai, aur ghaata dikhta hai ----
    // Teen din ka kharcha: 200 + 300 + 50 = ₹550 -> ghaata
    for($d=0;$d<3;$d++){
        $pdo->prepare("INSERT INTO operating_costs(cost_date,fuel,staff,other)
                       VALUES (CURDATE() - INTERVAL ? DAY,?,?,?)
                       ON DUPLICATE KEY UPDATE fuel=VALUES(fuel),staff=VALUES(staff),other=VALUES(other)")
            ->execute([$d, 200, 300, 50]);
        $kh[]=$d;
    }
    $b = mf_bahi(); $p = $b['paisa'];

    mf_check((int)$p['gaya'] >= $gaya0 + 1650,
             'teen din ka ₹1,650 kharcha ginna chahiye, mila ₹'.$p['gaya']);
    mf_check((int)$p['bacha'] === (int)$p['aaya'] - (int)$p['gaya'],
             'bacha = aaya - gaya hona chahiye');
    mf_check((int)$p['bacha'] < 0, 'itne kharche par GHAATA dikhna chahiye, chhupna nahi');
    mf_check((int)$p['per_order'] < 0, 'har order par bhi ghaata dikhna chahiye');

    // ---- 4. adhoore din saaf likhe jayein ----
    mf_check((int)$p['din_darj'] >= 3, 'teen din darj dikhne chahiye');
    mf_check((int)$p['din_darj'] + (int)$p['din_adhoore'] === 7,
             'darj + adhoore milkar 7 hone chahiye');

    // panne par bhi likha ho -- sirf JSON me hona kaafi nahi,
    // Ankit panna padhta hai, JSON nahi
    $c=curl_init('http://127.0.0.1:8099/muneem.php?key='.rawurlencode(MUNEEM_KEY));
    curl_setopt_array($c,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>25,CURLOPT_PROXY=>'']);
    $html=(string)curl_exec($c);curl_close($c);
    mf_check(strpos($html,'मुनाफ़ा या नुक़सान')!==false,'panne par "munafa ya nuksan" likha hona chahiye');
    mf_check(strpos($html,'घाटा')!==false,'ghaate par "ghaata" shabd dikhna chahiye');
    mf_check(strpos($html,'ख़र्चा दर्ज ही नहीं हुआ')!==false,
             'adhoore din ki chetavni panne par dikhni chahiye');
    mf_check(strpos($html,'सामान का पैसा इसमें नहीं गिना गया')!==false,
             'saaman ka paisa na ginne ki baat panne par likhi honi chahiye');
    mf_check(strpos($html,'₹-')===false,'"₹-15" jaisa ulta nishan nahi dikhna chahiye');

    echo "Munafa: ginti sach boli, ghaata chhupa nahi, saaman ka paisa kamai me nahi juda, adhoore din likhe gaye\n";
}finally{
    foreach($ord as $id)$pdo->prepare('DELETE FROM orders WHERE id=?')->execute([$id]);
    foreach($kh as $d)$pdo->prepare('DELETE FROM operating_costs WHERE cost_date = CURDATE() - INTERVAL ? DAY')->execute([$d]);
}
