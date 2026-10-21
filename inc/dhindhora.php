<?php
// ============================================================
//  ढिंढोरची — आज का संदेश
//  पंडित जी — आगे क्या आ रहा है
//
//  Ankit ne kaha tha: "ad banao wala bot, use share karne wala
//  bot". Aur alag se: "season ke saath business kar sakte hain,
//  jaise winter aa raha hai to uska bhi".
//
//  Do bot alag-alag banane ka matlab nahi tha, kyunki dono ek hi
//  cheez ke do hisse hain:
//     पंडित जी  -- aage kya aa raha hai (tyohar, mausam)
//     ढिंढोरची  -- usi baat ko ek sandesh me badalna
//
//  IS FILE KA SABSE ZAROORI NIYAM
//  ------------------------------
//      JO SACH NAHI HAI, WO SANDESH ME NAHI JAYEGA.
//
//  Ad banane wala bot banane ka aasan tareeka ye hota: "Maakit --
//  sabse tez delivery, sabse sasta daam!" Aisa sandesh roz ban
//  jata, bina kisi data ke.
//
//  Par gaon me ye ulta padta hai. Jo aadmi padhega wo WAHI gaon
//  ka hai -- use pata hai kitni dukaanein judi hain aur kitni der
//  me saamaan aata hai. Ek jhootha daawa ek hi baar chalta hai,
//  aur uske baad wo aadmi dobara nahi padhta.
//
//  Isliye yahan har line kisi record se banti hai: judi hui
//  dukaan, chalu packet ka asli daam, chalu ilaake, aaj ka
//  tyohar. Agar kehne layak kuch nahi hai, to ye bot null lautata
//  hai -- chup rehna jhooth bolne se behtar hai.
//
//  Ye bot KHUD KISI KO NAHI BHEJTA. Sandesh bana kar rakhta hai,
//  bhejna Ankit ke haath me hai. Apne aap bhejne ke do nuksaan
//  hain: ek galti sau logon tak pahunch jati hai, aur WhatsApp
//  apne aap bhejne wale number band kar deta hai.
// ============================================================

require_once __DIR__ . '/festival.php';

/**
 * पंडित जी — आगे क्या आ रहा है।
 *
 * Tyohar se PEHLE batana hi kaam ka hai. Chhath ke din "Chhath ki
 * shubhkamnayein" bhejne se dhandha nahi hota -- saaman 4-5 din
 * pehle mangaya jata hai.
 *
 * Lautata hai: ['tyohar'=>..., 'din'=>kitne din baaki, 'aaj'=>bool] ya null
 */
function pandit_aage($din_tak = 8, $aaj = null) {
    $aaj = $aaj ?: date('Y-m-d');
    $sab = maakit_festivals();
    usort($sab, fn($x, $y) => strcmp($x['from'], $y['from']));

    foreach ($sab as $f) {
        if ($aaj >= $f['from'] && $aaj <= $f['to']) {
            return ['tyohar' => $f, 'din' => 0, 'aaj' => true];
        }
        $farq = (int)floor((strtotime($f['from']) - strtotime($aaj)) / 86400);
        if ($farq > 0 && $farq <= $din_tak) {
            return ['tyohar' => $f, 'din' => $farq, 'aaj' => false];
        }
    }
    return null;
}

/**
 * मौसम की बात — पंडित जी का दूसरा हिस्सा।
 *
 * Mausam ka koi record Maakit ke paas nahi hai, isliye yahan
 * sirf MAHINA dekha jata hai -- jo pakka hai. Jhootha "aaj 8
 * degree hai" kehne se behtar hai "sardi aa rahi hai" kehna.
 */
function pandit_mausam($aaj = null) {
    $m = (int)date('n', strtotime($aaj ?: date('Y-m-d')));
    if ($m === 11 || $m === 12 || $m === 1) {
        return ['kya' => 'सर्दी', 'baat' => 'सर्दी में बाहर निकलना भारी पड़ता है — सामान घर पर मँगा लीजिए।',
                'saaman' => 'रज़ाई, गर्म कपड़े, तेल, गुड़, तिल, मूँगफली'];
    }
    if ($m >= 4 && $m <= 6) {
        return ['kya' => 'गर्मी', 'baat' => 'दोपहर की धूप में बाहर मत निकलिए — सामान घर पर आ जाएगा।',
                'saaman' => 'ठंडा पानी, नींबू, सत्तू, छाता, ORS'];
    }
    if ($m === 7 || $m === 8 || $m === 9) {
        return ['kya' => 'बरसात', 'baat' => 'बरसात में रास्ता ख़राब रहता है — हम ले आएँगे।',
                'saaman' => 'छाता, बरसाती, मच्छर अगरबत्ती, दवा'];
    }
    return null;
}

/**
 * आज का संदेश — सिर्फ़ सच्ची बातों से बना।
 *
 * Lautata hai ['lines'=>[], 'sandesh'=>text, 'kyun'=>..., 'sach'=>[...]]
 * ya null (aaj kehne layak kuch nahi hai).
 */
function dhindhora_aaj(PDO $pdo, $aaj = null) {
    $aaj  = $aaj ?: date('Y-m-d');
    $phone = '84293 93903';
    $sach = [];     // har line ke peeche kaun record hai -- panne par dikhta hai

    $ek = function (string $sql, array $a = []) use ($pdo) {
        try { $st = $pdo->prepare($sql); $st->execute($a); return $st->fetchColumn(); }
        catch (Throwable $e) { error_log('dhindhora: ' . $e->getMessage()); return 0; }
    };

    // ---- asli ginti ----
    $dukaan = (int)$ek("SELECT COUNT(*) FROM businesses WHERE status='approved' AND items_on=1");
    $ilaake = (int)$ek("SELECT COUNT(*) FROM villages v JOIN service_area_meta m ON m.village_id=v.id
                         WHERE v.live=1 AND m.delivery_on=1");
    $naye   = (int)$ek("SELECT COUNT(*) FROM businesses WHERE status='approved'
                         AND approved_at >= ? - INTERVAL 7 DAY", [$aaj]);

    // ---- chalu packet, asli daam ke saath ----
    $packet = [];
    try {
        $st = $pdo->query("SELECT p.id, p.name, p.business_id, b.name dukaan
                             FROM packs p JOIN businesses b ON b.id = p.business_id
                            WHERE p.status='live' AND b.status='approved' AND b.packs_on=1
                            ORDER BY p.mange DESC, p.id LIMIT 3");
        require_once __DIR__ . '/packet.php';
        foreach ($st->fetchAll() as $p) {
            $d = pack_daam($pdo, $p['id']);
            if ($d['poora'] && $d['daam'] > 0) {
                $packet[] = ['naam' => $p['name'], 'daam' => (int)$d['daam'], 'dukaan' => $p['dukaan']];
            }
        }
    } catch (Throwable $e) { error_log('dhindhora packet: ' . $e->getMessage()); }

    $aage = pandit_aage(8, $aaj);
    $mausam = pandit_mausam($aaj);

    // ---- sandesh ki lines -- har ek kisi record se ----
    $lines = [];

    if ($aage) {
        $t = $aage['tyohar'];
        if ($aage['aaj']) {
            $lines[] = $t['hi'] . ' की शुभकामनाएँ! ' . $t['sub_hi'];
        } else {
            $lines[] = $t['hi'] . ' में ' . $aage['din'] . ' दिन बाक़ी हैं। ' . $t['sub_hi']
                     . ' आज ही बोल दीजिए, घर पहुँचा देंगे।';
        }
        $sach[] = 'त्योहार: ' . $t['hi'] . ' (' . $t['from'] . ' से) — inc/festival.php';
    } elseif ($mausam) {
        $lines[] = $mausam['baat'] . ' (' . $mausam['saaman'] . ')';
        $sach[] = 'मौसम: ' . $mausam['kya'] . ' — महीने से, मौसम की भविष्यवाणी नहीं';
    }

    if ($packet) {
        foreach ($packet as $p) {
            $lines[] = '• ' . $p['naam'] . ' — ₹' . $p['daam'] . ' (' . $p['dukaan'] . ')';
        }
        $sach[] = count($packet) . ' चालू पैकेट — दाम दुकान के अपने दाम से जोड़ा हुआ';
    }

    if ($naye > 0) {
        $lines[] = 'इस हफ़्ते ' . $naye . ' नई दुकान जुड़ी' . ($naye > 1 ? 'ं' : '') . '।';
        $sach[] = $naye . ' दुकानें पिछले 7 दिन में मंज़ूर हुईं';
    }

    // ---- kehne layak kuch hai hi nahi? to chup ----
    // Sirf "hum hain" wala sandesh bhejna bhejne layak nahi hai.
    if (!$lines) return null;

    if ($dukaan > 0 && $ilaake > 0) {
        $lines[] = $dukaan . ' दुकान' . ($dukaan > 1 ? 'ें' : '') . ', ' . $ilaake . ' इलाक़ा'
                 . ($ilaake > 1 ? 'के' : '') . ' — किसी भी दुकान का सामान घर पर।';
        $sach[] = $dukaan . ' दुकानें और ' . $ilaake . ' इलाक़े चालू हैं — गिनी हुई संख्या';
    }

    $lines[] = 'फ़ोन/WhatsApp: ' . $phone . ' · maakit.in';

    return ['lines'   => $lines,
            'sandesh' => "*Maakit*\n\n" . implode("\n", $lines),
            'tyohar'  => $aage,
            'mausam'  => $mausam,
            'packet'  => $packet,
            'sach'    => $sach];
}

/** पिछली बार कब भेजा — एक काम वाला बॉट यही देखता है */
function dhindhora_bheja(PDO $pdo) {
    try { $st = $pdo->query("SELECT v FROM settings WHERE k='dhindhora_bheja'");
          return (string)$st->fetchColumn(); }
    catch (Throwable $e) { return ''; }
}

/** आज का संदेश ले लिया — ताकि "एक काम" वाला बॉट दोबारा न कहे */
function dhindhora_le_liya(PDO $pdo) {
    try {
        $pdo->prepare("INSERT INTO settings (k,v) VALUES ('dhindhora_bheja',?)
                        ON DUPLICATE KEY UPDATE v=VALUES(v)")->execute([date('Y-m-d')]);
    } catch (Throwable $e) { error_log('dhindhora_le_liya: ' . $e->getMessage()); }
}
