<?php
// ============================================================
//  जाँच — पैकेट (बना हुआ सेट)
//
//  Packet graahak ko DAAM dikhata hai, aur daam par bharosa tikta
//  hai. Isliye is file me sabse zyada jagah ek hi baat ko di gayi
//  hai:
//
//      PACKET KA DAAM DUKAAN KA APNA DAAM HO. HAR BAAR.
//
//  Maakit ka sabse bada niyam ye hai ki wo kisi ka paisa nahi
//  rakhta -- saamaan ka paisa graahak se SEEDHA dukaan ko jata
//  hai (AGENTS.md). Packet ke saath do tarah se ye niyam toot
//  sakta tha, aur dono yahan jaanche jaate hain:
//
//   1. DAAM LIKHA HUA PADA REH JAYE. Dukandaar daam badle aur
//      packet purana daam dikhata rahe. Phir graahak purane daam
//      par manga lega, dukaan naya daam maangegi, aur beech me
//      Maakit khada hoga -- ya graahak se jhooth, ya dukaan ka
//      nuksaan. Isliye packet ka daam KAHIN LIKHA HI NAHI JATA.
//
//   2. EK PACKET ME DO DUKAANON KA SAAMAAN. Tab paisa do jagah
//      jata, aur baantne ke liye Maakit ko beech me aana padta --
//      yani doosre ka paisa rakhna. Ye kabhi nahi hoga.
//
//  Teesri baat bharose ki: AADHA PACKET KABHI NA DIKHE. "Sau
//  rupaye ka packet" dikhakar beech me se do cheezein nikal dena
//  dhokha hai -- graahak ne wo set dekh kar manga tha.
// ============================================================
require_once __DIR__.'/../../config.php';
if(DB_NAME!=='maakit_jaanch')throw new RuntimeException('CI database only');
require_once __DIR__.'/../../inc/db.php';
require_once __DIR__.'/../../inc/packet.php';

function pk_check($ok,$m){if(!$ok)throw new RuntimeException('Packet: '.$m);}
$biz=[];$pack=[];

function pk_get($path){
    $c=curl_init('http://127.0.0.1:8099'.$path);
    curl_setopt_array($c,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>20,CURLOPT_PROXY=>'']);
    $b=curl_exec($c);$st=curl_getinfo($c,CURLINFO_HTTP_CODE);curl_close($c);
    return [$st,(string)$b];
}

try{
    // ---- do dukaanein, taaki "doosri dukaan ka saamaan" jaancha ja sake ----
    $mk=function($naam,$mob)use($pdo){
        $pdo->prepare("INSERT INTO businesses(name,village,mobile,category,status,items_on,packs_on)
                       VALUES (?,'CI Packet Gaon',?,'kirana','approved',1,1)")->execute([$naam,$mob]);
        return (int)$pdo->lastInsertId();};
    $a=$mk('CI Packet Kirana','9000000051');  $biz[]=$a;
    $d=$mk('CI Packet Doosri','9000000052');  $biz[]=$d;

    $it=function($bid,$naam,$daam)use($pdo){
        $pdo->prepare("INSERT INTO shop_items(business_id,name,unit,price,active,stock)
                       VALUES (?,?,'1 किलो',?,1,'hai')")->execute([$bid,$naam,$daam]);
        return (int)$pdo->lastInsertId();};
    $aloo   = $it($a,'CI Aloo',30);
    $pyaaz  = $it($a,'CI Pyaaz',40);
    $dudh   = $it($a,'CI Dudh',30);
    $paraya = $it($d,'CI Paraya Saaman',25);

    // ---- 1. दाम दुकान का अपना हो ----
    $p1 = pack_banao($pdo,$a,'CI सौ का पैकेट',[$aloo=>1,$pyaaz=>1,$dudh=>1]); $pack[]=$p1;
    $k = pack_daam($pdo,$p1);
    pk_check($k['daam']===100,'daam dukaan ke daam ka jod hona chahiye (100), mila '.$k['daam']);
    pk_check($k['poora']===true,'saara saamaan tayyar hai, packet poora hona chahiye');

    // ---- 2. ginti se guna ho ----
    $p2 = pack_banao($pdo,$a,'CI दो दोस्त',[$aloo=>2,$dudh=>1]); $pack[]=$p2;
    pk_check(pack_daam($pdo,$p2)['daam']===90,'2 aloo + 1 dudh = 90 hona chahiye');

    // ---- 3. दुकानदार दाम बदले तो पैकेट का दाम भी बदले ----
    // YAHI IS POORI FILE KI JAAN HAI. Agar daam kahin likh kar
    // rakh liya gaya hota, to ye jaanch yahin pakad leti.
    $pdo->prepare("UPDATE shop_items SET price=50 WHERE id=?")->execute([$aloo]);
    pk_check(pack_daam($pdo,$p1)['daam']===120,
             'dukaan ne daam badla to packet ka daam bhi badalna chahiye (120)');
    $pdo->prepare("UPDATE shop_items SET price=30 WHERE id=?")->execute([$aloo]);

    // ---- 4. एक पैकेट = एक दुकान = एक जेब ----
    try{
        pack_banao($pdo,$a,'CI Galat Packet',[$aloo=>1,$paraya=>1]);
        pk_check(false,'doosri dukaan ka saamaan packet me nahi jana chahiye');
    }catch(InvalidArgumentException $e){}
    try{ pack_banao($pdo,$a,'CI Khali',[]); pk_check(false,'khali packet nahi banna chahiye'); }
    catch(InvalidArgumentException $e){}
    try{ pack_banao($pdo,$a,'   ',[$aloo=>1]); pk_check(false,'bina naam ka packet nahi banna chahiye'); }
    catch(InvalidArgumentException $e){}

    // ---- 5. बिना मंज़ूरी ग्राहक को कुछ न दिखे ----
    pk_check(count(pack_list($pdo,$a,true))===0,'bina manzoori packet graahak ko nahi dikhna chahiye');
    pk_check(count(pack_list($pdo,$a,false))===2,'maalik ko dono packet dikhne chahiye');
    pack_manzoor($pdo,$p1,1);
    pack_manzoor($pdo,$p2,1);
    pk_check(count(pack_list($pdo,$a,true))===2,'manzoori ke baad dono dikhne chahiye');

    // ---- 6. आधा पैकेट कभी न दिखे ----
    // Ek cheez khatam hui -> poora packet chhup jaye. Graahak ne
    // SET dekh kar manga tha; beech me se nikal dena dhokha hai.
    $pdo->prepare("UPDATE shop_items SET stock='khatam' WHERE id=?")->execute([$dudh]);
    $dikhe = array_column(pack_list($pdo,$a,true),'id');
    pk_check(!in_array($p1,$dikhe),'jisme saamaan khatam hai wo packet nahi dikhna chahiye');
    pk_check(!in_array($p2,$dikhe),'dudh dono packet me hai, dono chhupne chahiye');
    // par maalik ko dikhe, warna wo theek kaise karega
    $m = pack_list($pdo,$a,false);
    pk_check(count($m)===2,'maalik ko adhoora packet bhi dikhna chahiye');
    foreach($m as $x) pk_check($x['poora']===false,'maalik ko saaf pata chale ki packet adhoora hai');
    $pdo->prepare("UPDATE shop_items SET stock='hai' WHERE id=?")->execute([$dudh]);

    // bina daam ka saamaan bhi packet ko adhoora karta hai
    $pdo->prepare("UPDATE shop_items SET price=0 WHERE id=?")->execute([$pyaaz]);
    pk_check(!in_array($p1,array_column(pack_list($pdo,$a,true),'id')),
             'bina daam ke saamaan wala packet nahi dikhna chahiye');
    $pdo->prepare("UPDATE shop_items SET price=40 WHERE id=?")->execute([$pyaaz]);

    // ---- 7. बॉट बजट से ज़्यादा का पैकेट न बनाए ----
    // "Sau rupaye ka packet" 130 ka nikle to wo dhokha hai.
    $bane = pack_bot($pdo,$a,[100,200]);
    pk_check($bane>0,'bot ko packet banana chahiye tha');
    $bot_ke = array_filter(pack_list($pdo,$a,false), fn($p)=>$p['kyun']==='budget');
    pk_check(count($bot_ke)>0,'bot ke packet milne chahiye');
    foreach($bot_ke as $p){
        $pack[]=$p['id'];
        preg_match('/(\d+)/',$p['name'],$mm);
        $budget=(int)($mm[1]??0);
        pk_check($budget>0,'bot ke packet ke naam me budget hona chahiye: '.$p['name']);
        pk_check($p['daam']<=$budget,
                 "bot ka packet budget se zyada ka nahi hona chahiye -- $budget ka naam, {$p['daam']} ka daam");
        pk_check(count($p['saaman'])>=2,'ek cheez ka "set" set nahi hai');
        // ---- 8. बॉट ख़ुद चालू न करे ----
        pk_check($p['status']==='suggested','bot ka packet khud live nahi hona chahiye');
        pk_check($p['banaya_by']===null,'bot ke packet par kisi aadmi ka naam nahi hona chahiye');
    }
    // dobara chalane par wahi packet phir se na bane
    $pehle=count(pack_list($pdo,$a,false));
    pack_bot($pdo,$a,[100,200]);
    pk_check(count(pack_list($pdo,$a,false))===$pehle,'bot wahi packet dobara nahi banana chahiye');

    // ---- 9. ग्राहक के पन्ने पर पैकेट दिखे, और वही पुराना फ़ॉर्म भरे ----
    // Packet koi NAYA order ka rasta nahi hai. Agar wo apna rasta
    // bana leta, to daam ki jaanch, ilaake ki jaanch aur dukaan
    // khuli hai ya nahi -- sab dobara likhna padta, aur paisa wale
    // raste me dohri jagah sabse khatarnak hoti hai.
    [$st,$html] = pk_get('/dukan-se.php?id='.$a);
    pk_check($st===200,"dukan-se.php ne $st diya");
    pk_check(strpos($html,'CI सौ का पैकेट')!==false,'graahak ko manzoor packet dikhna chahiye');
    pk_check(preg_match('/data-bharo="[^"]*'.$aloo.'/',$html)===1,
             'packet ke button me saamaan ki id honi chahiye (wahi form bharta hai)');
    pk_check(strpos($html,'name="pack_id"')!==false,'pack_id ka khana hona chahiye');
    pk_check(preg_match('#name="q\['.$aloo.'\]"#',$html)===1,
             'wahi purana ginti wala form maujood rehna chahiye');

    // hataye hue packet graahak ko na dikhein
    $pdo->prepare("UPDATE packs SET status='hidden' WHERE id=?")->execute([$p1]);
    [,$html2] = pk_get('/dukan-se.php?id='.$a);
    pk_check(strpos($html2,'CI सौ का पैकेट')===false,'hataya hua packet nahi dikhna chahiye');

    // dukaan ne packet band kiye -> ek bhi na dikhe
    $pdo->prepare("UPDATE businesses SET packs_on=0 WHERE id=?")->execute([$a]);
    [,$html3] = pk_get('/dukan-se.php?id='.$a);
    pk_check(strpos($html3,'बने हुए पैकेट')===false,'packs_on=0 par packet ka hissa nahi dikhna chahiye');
    $pdo->prepare("UPDATE businesses SET packs_on=1 WHERE id=?")->execute([$a]);

    // ---- 10. गिनती सच बोले ----
    pack_manga($pdo,$p2);
    $g=$pdo->prepare("SELECT mange FROM packs WHERE id=?");$g->execute([$p2]);
    pk_check((int)$g->fetchColumn()===1,'packet se order aane par ginti badhni chahiye');

    echo "Packet: daam dukaan ka hi raha (badalne par badla), do dukaanon ka packet nahi bana, adhoora packet graahak ko nahi dikha, bot budget ke andar raha aur khud live nahi kiya, aur graahak ka wahi purana form bharta hai\n";
}finally{
    foreach($pack as $id){
        $pdo->prepare('DELETE FROM pack_items WHERE pack_id=?')->execute([$id]);
        $pdo->prepare('DELETE FROM packs WHERE id=?')->execute([$id]);
    }
    foreach($biz as $id){
        $pdo->prepare('DELETE pi FROM pack_items pi JOIN packs p ON p.id=pi.pack_id WHERE p.business_id=?')->execute([$id]);
        $pdo->prepare('DELETE FROM packs WHERE business_id=?')->execute([$id]);
        $pdo->prepare('DELETE FROM shop_items WHERE business_id=?')->execute([$id]);
        $pdo->prepare('DELETE FROM businesses WHERE id=?')->execute([$id]);
    }
}
