<?php
// ============================================================
//  जाँच — ढिंढोरची (आज का संदेश) और पंडित जी (आगे क्या आ रहा है)
//
//  Ad banane wale bot ki sabse aasan galti ye hoti hai: "Maakit --
//  sabse tez delivery, sabse sasta daam!" Aisa sandesh roz ban
//  jata hai, bina kisi record ke.
//
//  Par gaon me ye ULTA padta hai. Jo aadmi padhega wo WAHI gaon ka
//  hai -- use pata hai kitni dukaanein judi hain aur kitni der me
//  saamaan aata hai. Ek jhootha daawa ek hi baar chalta hai, aur
//  uske baad wo aadmi dobara nahi padhta. Jhooth se khoya hua
//  graahak wapas nahi aata.
//
//  Isliye is file me teen cheezein jaanchi jaati hain:
//
//   1. KEHNE LAYAK KUCH NA HO TO CHUP RAHE. Khali "hum hain" wala
//      sandesh bhejna bhejne layak nahi hai.
//
//   2. JO GINTI SANDESH ME HAI, WO DATABASE SE AAYE. Packet ka
//      daam dukaan ka apna daam ho -- aur dukaan daam badle to
//      sandesh ka daam bhi usi waqt badal jaye.
//
//   3. BOT KHUD KISI KO NA BHEJE. Sandesh banana aur bhejna do
//      alag kaam hain. Apne aap bhejne ke do nuksaan hain: ek
//      galti sau logon tak pahunch jati hai, aur WhatsApp apne aap
//      bhejne wale number band kar deta hai.
// ============================================================
require_once __DIR__.'/../../config.php';
if(DB_NAME!=='maakit_jaanch')throw new RuntimeException('CI database only');
require_once __DIR__.'/../../inc/db.php';
require_once __DIR__.'/../../inc/dhindhora.php';
require_once __DIR__.'/../../inc/packet.php';

function dh_check($ok,$m){if(!$ok)throw new RuntimeException('Dhindhora: '.$m);}

// ---- 1. पंडित जी — त्योहार से PEHLE बताए ----
// Tyohar ke DIN "shubhkamnayein" bhejne se dhandha nahi hota.
// Saaman 4-5 din pehle mangaya jata hai. Isliye pehle batana hi
// iska poora matlab hai.
$ty = [];
foreach (maakit_festivals() as $f) $ty[$f['key']] = $f;
foreach (['karwa','diwali','chhath'] as $k) dh_check(isset($ty[$k]),"$k panchang me hona chahiye");

$paanch_din_pehle = date('Y-m-d', strtotime($ty['karwa']['from'].' -5 day'));
$a = pandit_aage(8, $paanch_din_pehle);
dh_check($a && $a['tyohar']['key']==='karwa','5 din pehle karwa chauth pata hona chahiye');
dh_check($a['din']===5 && $a['aaj']===false,"5 din baaki hona chahiye, mila ".($a['din']??'-'));

$us_din = pandit_aage(8, $ty['karwa']['from']);
dh_check($us_din && $us_din['aaj']===true,'tyohar ke din "aaj" hona chahiye');

// Bahut door ka tyohar na bataye -- mahina pehle batana shor hai
$bahut_pehle = date('Y-m-d', strtotime($ty['karwa']['from'].' -40 day'));
$door = pandit_aage(8, $bahut_pehle);
dh_check(!$door || $door['tyohar']['key']!=='karwa','40 din pehle karwa nahi batana chahiye');

// Jo tyohar AAJ chal raha hai wo aage wale se jeete. Diwali ke beech
// me "chhath me 5 din baaki" kehna galat hoga -- aaj Diwali hai, aur
// aaj ka saamaan aaj bikta hai.
$diwali_me = date('Y-m-d', strtotime($ty['chhath']['from'].' -5 day'));
dh_check($diwali_me >= $ty['diwali']['from'] && $diwali_me <= $ty['diwali']['to'],
         'is din diwali chal rahi honi chahiye (panchang badla ho to ye line badal dijiye)');
$beech = pandit_aage(8, $diwali_me);
dh_check($beech && $beech['tyohar']['key']==='diwali' && $beech['aaj']===true,
         'jo tyohar aaj chal raha hai wohi batana chahiye, aage wala nahi');

// ---- 2. मौसम — महीने से, भविष्यवाणी से नहीं ----
// Maakit ke paas mausam ka koi record nahi hai. "Aaj 8 degree hai"
// kehna jhooth hota; "sardi aa rahi hai" sach hai.
dh_check(pandit_mausam('2026-12-15')['kya']==='सर्दी','december me sardi honi chahiye');
dh_check(pandit_mausam('2026-05-15')['kya']==='गर्मी','may me garmi honi chahiye');
dh_check(pandit_mausam('2026-08-15')['kya']==='बरसात','august me barsaat honi chahiye');

$biz=[];$pack=[];
try{
    // ---- 3. कहने लायक़ कुछ न हो तो चुप ----
    // Aisa din chuniye jisme na tyohar paas hai na mausam ki baat
    // (October: panchang me tyohar hai, isliye mausam khali hai).
    $chup = '2026-10-02';
    dh_check(pandit_aage(8,$chup)===null,'is din koi tyohar paas nahi hona chahiye');
    dh_check(pandit_mausam($chup)===null,'is din mausam ki baat nahi honi chahiye');
    dh_check(dhindhora_aaj($pdo,$chup)===null,
             'kehne layak kuch na ho to sandesh nahi banna chahiye -- chup rehna behtar hai');

    // ---- 4. पैकेट का दाम दुकान का अपना दाम हो ----
    $pdo->prepare("INSERT INTO businesses(name,village,mobile,category,status,items_on,packs_on,approved_at)
                   VALUES ('CI Dhindhora Kirana','CI Dh Gaon',?,'kirana','approved',1,1,NOW())")
        ->execute(['9000000061']);
    $b=(int)$pdo->lastInsertId(); $biz[]=$b;

    $mk=function($naam,$daam)use($pdo,$b){
        $pdo->prepare("INSERT INTO shop_items(business_id,name,unit,price,active,stock)
                       VALUES (?,?,'1 किलो',?,1,'hai')")->execute([$b,$naam,$daam]);
        return (int)$pdo->lastInsertId();};
    $aloo=$mk('CI Dh Aloo',30); $dudh=$mk('CI Dh Dudh',30);

    $p=pack_banao($pdo,$b,'CI साठ का पैकेट',[$aloo=>1,$dudh=>1]); $pack[]=$p;
    pack_manzoor($pdo,$p,1);

    $d=dhindhora_aaj($pdo,$chup);
    dh_check($d!==null,'chalu packet hone par sandesh banna chahiye');
    dh_check(strpos($d['sandesh'],'CI साठ का पैकेट')!==false,'packet ka naam sandesh me hona chahiye');
    dh_check(strpos($d['sandesh'],'₹60')!==false,'packet ka asli daam (60) sandesh me hona chahiye');

    // Dukandaar ne daam badla -> sandesh ka daam bhi badalna chahiye.
    // Agar sandesh purana daam dikhata rahe, to graahak purane daam
    // par manga lega aur dukaan naya daam maangegi -- beech me Maakit
    // ka bharosa tootega.
    $pdo->prepare("UPDATE shop_items SET price=50 WHERE id=?")->execute([$aloo]);
    $d2=dhindhora_aaj($pdo,$chup);
    dh_check(strpos($d2['sandesh'],'₹80')!==false,
             'daam badalne par sandesh ka daam bhi badalna chahiye (80)');
    dh_check(strpos($d2['sandesh'],'₹60')===false,'purana daam sandesh me nahi rehna chahiye');
    $pdo->prepare("UPDATE shop_items SET price=30 WHERE id=?")->execute([$aloo]);

    // ---- 5. आधा या बिना मंज़ूरी वाला पैकेट संदेश में न जाए ----
    $pdo->prepare("UPDATE shop_items SET stock='khatam' WHERE id=?")->execute([$dudh]);
    $d3=dhindhora_aaj($pdo,$chup);
    dh_check(!$d3 || strpos($d3['sandesh'],'CI साठ का पैकेट')===false,
             'jisme saamaan khatam hai wo packet sandesh me nahi jana chahiye');
    $pdo->prepare("UPDATE shop_items SET stock='hai' WHERE id=?")->execute([$dudh]);

    $pdo->prepare("UPDATE packs SET status='suggested' WHERE id=?")->execute([$p]);
    $d4=dhindhora_aaj($pdo,$chup);
    dh_check(!$d4 || strpos($d4['sandesh'],'CI साठ का पैकेट')===false,
             'bina manzoori wala packet sandesh me nahi jana chahiye');
    pack_manzoor($pdo,$p,1);

    // ---- 6. हर लाइन के पीछे रिकॉर्ड हो ----
    // Panne par "ye baatein kahan se aayi" dikhta hai, taaki Ankit
    // bhejne se pehle khud dekh le.
    $d5=dhindhora_aaj($pdo,$chup);
    dh_check(!empty($d5['sach']),'har sandesh ke saath uska record hona chahiye');
    dh_check(count($d5['sach'])>0,'sach ki soochi khali nahi honi chahiye');

    // ---- 7. बॉट ख़ुद कुछ न भेजे, और संदेश बनाना कुछ न बदले ----
    // Sandesh banana sirf PADHNE ka kaam hai. Agar wo kuch likh
    // deta, to panna kholne se hi "bhej diya" ban jata.
    $pehle = dhindhora_bheja($pdo);
    dhindhora_aaj($pdo,$chup); dhindhora_aaj($pdo,$chup);
    dh_check(dhindhora_bheja($pdo)===$pehle,'sandesh banane se kuch badalna nahi chahiye');

    // ---- 8. "ले लिया" के बाद एक काम दोबारा न कहे ----
    // Aisa kaam jo kabhi "ho gaya" na ho, poore bot ka bharosa kha
    // jata hai -- isliye ye nishan rakha gaya hai.
    $pdo->beginTransaction();
    try{
        dhindhora_le_liya($pdo);
        dh_check(dhindhora_bheja($pdo)===date('Y-m-d'),'"le liya" ka nishan aaj ka hona chahiye');
    } finally { $pdo->rollBack(); }
    dh_check(dhindhora_bheja($pdo)===$pehle,'palatne par nishan wapas purana hona chahiye');

    echo "Dhindhora: tyohar pehle se bataya, kehne layak kuch na hone par chup raha, packet ka daam dukaan ke daam ke saath badla, adhoora aur bina manzoori wala packet sandesh me nahi gaya, aur bot ne khud kuch nahi bheja\n";
}finally{
    foreach($pack as $id){
        $pdo->prepare('DELETE FROM pack_items WHERE pack_id=?')->execute([$id]);
        $pdo->prepare('DELETE FROM packs WHERE id=?')->execute([$id]);
    }
    foreach($biz as $id){
        $pdo->prepare('DELETE FROM shop_items WHERE business_id=?')->execute([$id]);
        $pdo->prepare('DELETE FROM businesses WHERE id=?')->execute([$id]);
    }
}
