<?php
// ============================================================
//  जाँच — दुकान की कहानी
//
//  Ye panne par graahak ko DIKHTI hai, isliye iski har shart
//  bharose ki baat hai. Teen galtiyan sabse mehengi padengi:
//
//    1. Hati hui / bina manzoori wali dukaan ki story dikhti
//       rahe -- graahak dabayega aur order kahin nahi jayega
//    2. Purani story dikhti rahe -- "aaj ka maal" kal jhooth
//       ban jata hai, aur jhooth bharosa kaat deta hai
//    3. Ek dukaan doosri ki story hata de ya uske naam par
//       daal de
//
//  Teeno yahan jaanche jaate hain.
// ============================================================
require_once __DIR__.'/../../config.php';
if(DB_NAME!=='maakit_jaanch')throw new RuntimeException('CI database only');
require_once __DIR__.'/../../inc/db.php';
require_once __DIR__.'/../../inc/story.php';

function kh_check($ok,$m){if(!$ok)throw new RuntimeException('Kahani: '.$m);}
$a=0;$b=0;$made=[];

function kh_dikh(PDO $pdo){           // graahak ko kaun si dikhti hai
    return array_map(fn($s)=>$s['dukaan'].'|'.$s['baat'], story_taaza($pdo, 50));
}

try{
    $pdo->prepare("INSERT INTO businesses(name,village,mobile,category,status)
                   VALUES ('CI Kahani Kirana','CI Kahani Gaon',?,'kirana','approved')")
        ->execute(['9000000031']);
    $a=(int)$pdo->lastInsertId();
    $pdo->prepare("INSERT INTO businesses(name,village,mobile,category,status)
                   VALUES ('CI Kahani Mithai','CI Kahani Gaon',?,'sweets','pending')")
        ->execute(['9000000032']);
    $b=(int)$pdo->lastInsertId();

    // ---- 1. khali story na bane ----
    [$ok,$m]=story_daalo($pdo,$a,'   ');
    kh_check(!$ok,'khali story nahi banni chahiye');
    [$ok,$m]=story_daalo($pdo,$a,'',null);
    kh_check(!$ok,'bina photo aur bina baat ke story nahi banni chahiye');

    // ---- 2. sirf photo se bhi ban jaye ----
    // Gaon me net dheema hai; kabhi aadmi sirf photo khinch kar
    // chhod deta hai. Use rokna nahi chahiye.
    [$ok,$m]=story_daalo($pdo,$a,'','st-250101-abcdef12.jpg');
    kh_check($ok,'sirf photo se story banni chahiye: '.$m);

    // ---- 3. ek din ki hadd ----
    $hadd=story_hadd($pdo);
    for($i=story_aaj_ki_ginti($pdo,$a); $i<$hadd; $i++){
        [$ok,$m]=story_daalo($pdo,$a,'CI maal '.$i);
        kh_check($ok,'hadd ke andar story banni chahiye: '.$m);
    }
    [$ok,$m]=story_daalo($pdo,$a,'CI hadd ke baahar');
    kh_check(!$ok,'hadd ke baad story nahi banni chahiye');
    kh_check(story_aaj_ki_ginti($pdo,$a)===$hadd,'ginti hadd par rukni chahiye');

    // ---- 4. EK dukaan ki EK hi dikhe (sabse nayi) ----
    // Warna ek dukaan teen jagah dikhegi aur panna uska ho jayega.
    $meri=array_filter(kh_dikh($pdo),fn($x)=>strpos($x,'CI Kahani Kirana|')===0);
    kh_check(count($meri)===1,'ek dukaan ki ek hi story dikhni chahiye, dikhi '.count($meri));

    // ---- 5. BINA MANZOORI wali dukaan ki story graahak ko na dikhe ----
    [$ok,]=story_daalo($pdo,$b,'CI pending dukaan ka maal');
    kh_check($ok,'pending dukaan apne panel par daal sakti hai');
    kh_check(!in_array('CI Kahani Mithai|CI pending dukaan ka maal',kh_dikh($pdo),true),
             'bina manzoori wali dukaan ki story graahak ko NAHI dikhni chahiye');
    // manzoor hote hi dikhne lage
    $pdo->prepare("UPDATE businesses SET status='approved' WHERE id=?")->execute([$b]);
    kh_check(in_array('CI Kahani Mithai|CI pending dukaan ka maal',kh_dikh($pdo),true),
             'manzoor hote hi dikhni chahiye');
    // aur hat jane par phir se gayab
    $pdo->prepare("UPDATE businesses SET status='hidden' WHERE id=?")->execute([$b]);
    kh_check(!in_array('CI Kahani Mithai|CI pending dukaan ka maal',kh_dikh($pdo),true),
             'dukaan hat jane par uski story bhi hat jani chahiye');
    $pdo->prepare("UPDATE businesses SET status='approved' WHERE id=?")->execute([$b]);

    // ---- 6. samay poora -> apne aap gayab, aur PICHHLI wapas ----
    $st=$pdo->prepare("SELECT id FROM shop_stories WHERE business_id=? ORDER BY id DESC");
    $st->execute([$a]);
    $ids=$st->fetchAll(PDO::FETCH_COLUMN);
    kh_check(count($ids)>=2,'aage ki jaanch ke liye do story chahiye');
    $nayi=(int)$ids[0];
    $pdo->prepare("UPDATE shop_stories SET dikhe_tak = NOW() - INTERVAL 1 MINUTE WHERE id=?")->execute([$nayi]);
    $ab=array_filter(kh_dikh($pdo),fn($x)=>strpos($x,'CI Kahani Kirana|')===0);
    kh_check(count($ab)===1,'samay poora hone par pichhli story wapas aani chahiye');
    $pdo->prepare("UPDATE shop_stories SET dikhe_tak = NOW() + INTERVAL 24 HOUR WHERE id=?")->execute([$nayi]);

    // ---- 7. maalik chhupa sake ----
    $pdo->prepare("UPDATE shop_stories SET hidden=1 WHERE business_id=?")->execute([$a]);
    kh_check(count(array_filter(kh_dikh($pdo),fn($x)=>strpos($x,'CI Kahani Kirana|')===0))===0,
             'chhupai hui story graahak ko nahi dikhni chahiye');
    $pdo->prepare("UPDATE shop_stories SET hidden=0 WHERE business_id=?")->execute([$a]);

    // ---- 8. ek dukaan DOOSRI ki story na hata sake ----
    kh_check(story_hatao($pdo,$a,$nayi)===true,'apni story hata sakni chahiye');
    $pdo->prepare("UPDATE shop_stories SET hidden=0 WHERE id=?")->execute([$nayi]);
    kh_check(story_hatao($pdo,$b,$nayi)===false,
             'doosri dukaan ki story HATANI NAHI aani chahiye');

    // ---- 9. ginti badhti hai ----
    $pehle=(int)$pdo->query("SELECT orders FROM shop_stories WHERE id=$nayi")->fetchColumn();
    story_se_order($pdo,$nayi);
    story_dekhi($pdo,$nayi);
    $baad=$pdo->query("SELECT orders,views FROM shop_stories WHERE id=$nayi")->fetch();
    kh_check((int)$baad['orders']===$pehle+1,'order ki ginti badhni chahiye');
    kh_check((int)$baad['views']>=1,'dekhne ki ginti badhni chahiye');

    // ---- 10. baat 140 se lambi na rahe ----
    $pdo->prepare("DELETE FROM shop_stories WHERE business_id=?")->execute([$b]);
    [$ok,]=story_daalo($pdo,$b,str_repeat('क',400));
    kh_check($ok,'lambi baat kat kar ban jani chahiye');
    $len=(int)$pdo->query("SELECT CHAR_LENGTH(baat) FROM shop_stories WHERE business_id=$b ORDER BY id DESC LIMIT 1")->fetchColumn();
    kh_check($len<=140,"baat 140 akshar tak katni chahiye, mili $len");

    echo "Kahani: khali ruki, hadd lagi, bina manzoori wali chhupi, samay poora hote hi gayab, doosri dukaan hata nahi saki\n";
}finally{
    foreach([$a,$b] as $id){
        if($id){
            $pdo->prepare('DELETE FROM shop_stories WHERE business_id=?')->execute([$id]);
            $pdo->prepare('DELETE FROM businesses WHERE id=?')->execute([$id]);
        }
    }
}
