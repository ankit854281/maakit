<?php
// ============================================================
//  जाँच — संदूक़ (रोज़ की नक़ल)
//
//  Website toot jaye to 2 din me dobara ban jayegi.
//  Graahak ka data gaya to KAHIN SE wapas nahi aayega.
//
//  Isliye is jaanch me ek baat sabse zaroori hai:
//
//      "NAKAL BAN GAYI" KEHNA KAAFI NAHI HAI.
//      JAANCHNA YE HAI KI US NAKAL SE DATABASE
//      SACH ME WAPAS KHADA HO JATA HAI.
//
//  Jhoothi nakal sabse khatarnak cheez hai -- wo 90 din tak
//  sanduk me padi rehti hai, aur jis din zaroorat padti hai
//  USI DIN pata chalta hai ki wo khali thi.
//
//  Ek aisi hi galti yahin pakdi gayi thi: bina chaabi ke nakal
//  utarne par 33 byte ka "403" wala panna utar aata tha, aur
//  naam .sql.gz hone se wo nakal jaisa lagta tha.
// ============================================================
require_once __DIR__.'/../../config.php';
if(DB_NAME!=='maakit_jaanch')throw new RuntimeException('CI database only');
require_once __DIR__.'/../../inc/db.php';

function sd_check($ok,$m){if(!$ok)throw new RuntimeException('Sanduk: '.$m);}
$utri = null;

function sd_get($url,$out=null){
    $c=curl_init($url);
    curl_setopt_array($c,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>300,CURLOPT_PROXY=>'']);
    $body=curl_exec($c);$st=curl_getinfo($c,CURLINFO_HTTP_CODE);curl_close($c);
    if($out!==null&&is_string($body))file_put_contents($out,$body);
    return [$st,(string)$body];
}

try{
    if(!defined('MUNEEM_KEY')||MUNEEM_KEY===''){
        echo "Sanduk: MUNEEM_KEY nahi hai, jaanch chhodi gayi\n"; return;
    }
    $ghar='http://127.0.0.1:8099';
    $key=rawurlencode(MUNEEM_KEY);

    // ---- 1. bina chaabi ke koi nakal na le ----
    // Warna jiske paas link hai wo poora database utaar le jayega.
    [$st,] = sd_get("$ghar/nakal.php");
    sd_check($st===403,"bina chaabi ke 403 aana chahiye, aaya $st");

    // ---- 2. chaabi se nakal bane ----
    [$st,$body] = sd_get("$ghar/nakal.php?key=$key");
    sd_check($st===200,"chaabi se 200 aana chahiye, aaya $st");
    $j=json_decode($body,true);
    sd_check(is_array($j)&&!empty($j['theek']),'nakal banni chahiye: '.substr($body,0,160));
    $naam=(string)($j['nakal']['naam']??'');
    sd_check($naam!=='','nakal ka naam milna chahiye');
    sd_check((int)($j['nakal']['tables']??0)>50,'nakal me 50+ table honi chahiye, mili '.($j['nakal']['tables']??0));

    // ---- 3. UTARNE PAR BHI CHAABI ZAROORI ----
    // Yahi wo galti thi jo pakdi gayi: bot bina chaabi ke utarta
    // tha aur 403 ka panna "nakal" ban jata tha.
    [$st,$body] = sd_get("$ghar/nakal.php?get=".rawurlencode($naam));
    sd_check($st===403,"bina chaabi ke utarne par 403 aana chahiye, aaya $st");

    // ---- 4. chaabi ke saath utre ----
    $utri = sys_get_temp_dir().'/sd-'.bin2hex(random_bytes(4)).'.sql.gz';
    [$st,] = sd_get("$ghar/nakal.php?get=".rawurlencode($naam)."&key=$key",$utri);
    sd_check($st===200,"utarne par 200 aana chahiye, aaya $st");
    sd_check(is_file($utri)&&filesize($utri)>5000,
             'utri hui nakal 5KB se badi honi chahiye, mili '.(is_file($utri)?filesize($utri):0).' byte');

    // ---- 5. file sach me gzip ho ----
    // Galti ka HTML panna bhi .sql.gz naam se aa sakta hai.
    $fh=fopen($utri,'rb');$do=fread($fh,2);fclose($fh);
    sd_check($do==="\x1f\x8b",'utri hui file sach me gzip honi chahiye (galti ka panna nahi)');

    // ---- 6. ASLI JAANCH: nakal se database WAPAS KHADA HO ----
    // Yahi iss poori file ki jaan hai. Baaki sab ginti hai.
    $kitni = (int)$pdo->query("SELECT COUNT(*) FROM information_schema.tables
                                WHERE table_schema = DATABASE()")->fetchColumn();
    $saaman = (int)$pdo->query("SELECT COUNT(*) FROM items")->fetchColumn();

    $naya = 'maakit_sanduk_jaanch';
    $pdo->exec("DROP DATABASE IF EXISTS $naya");
    $pdo->exec("CREATE DATABASE $naya CHARACTER SET utf8mb4");
    try{
        // DB_HOST me "host:port" bhi likha ho sakta hai (CI me
        // "127.0.0.1:3306" hota hai). PDO use samajh leta hai, par
        // mysql ka hukm nahi -- wo "Unknown MySQL server host"
        // kehkar ruk jata hai. Isi wajah se ye jaanch GitHub par
        // laal thi aur local par hari: local config me sirf host
        // likha hai.
        $db_host = DB_HOST; $db_port = '';
        if (strpos($db_host, ':') !== false) {
            [$db_host, $db_port] = explode(':', $db_host, 2);
        }
        $cmd = 'gzip -dc '.escapeshellarg($utri).' | mysql'
             . ' -h '.escapeshellarg($db_host)
             . ($db_port !== '' ? ' -P '.escapeshellarg($db_port) : '')
             . ' -u '.escapeshellarg(DB_USER)
             . (DB_PASS!=='' ? ' -p'.escapeshellarg(DB_PASS) : '')
             . ' '.escapeshellarg($naya).' 2>&1';
        exec($cmd,$out,$rc);
        sd_check($rc===0,'nakal wapas daalte waqt galti: '.implode(' ',array_slice($out,0,2)));

        $ab = (int)$pdo->query("SELECT COUNT(*) FROM information_schema.tables
                                 WHERE table_schema='$naya'")->fetchColumn();
        sd_check($ab===$kitni,"nakal se $kitni table wapas aani chahiye, aayi $ab");

        $ab_s = (int)$pdo->query("SELECT COUNT(*) FROM $naya.items")->fetchColumn();
        sd_check($ab_s===$saaman,
                 "DATA bhi wapas aana chahiye -- $saaman saamaan the, aaye $ab_s");
        // Khali table wapas aa jana kaafi nahi. Table to CREATE se
        // bhi ban jati hain; graahak ka data usme hona chahiye.
    } finally {
        $pdo->exec("DROP DATABASE IF EXISTS $naya");
    }

    echo "Sanduk: nakal bani, chaabi ke bina nahi utri, gzip sahi nikli, aur usse poora database ($kitni table, $saaman saamaan) wapas khada ho gaya\n";
}finally{
    if($utri&&is_file($utri))@unlink($utri);
}
