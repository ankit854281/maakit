<?php
// ============================================================
//  पैकेट — बना हुआ सेट
//
//  Gaon me graahak 40 cheezon ki soochi me se nahi chunta. Wo
//  kehta hai "sau rupaye ki sabzi bhej do". Packet wahi hai.
//
//  IS FILE KA SABSE ZAROORI HISSA: pack_daam()
//  -------------------------------------------
//  Packet ka daam KABHI likha hua nahi aata. Har baar dukaan ke
//  apne shop_items se joda jata hai.
//
//  Kyun: agar daam likh kar rakh lein, to dukandaar jis din daam
//  badlega, Maakit us din bhi PURANA daam dikhayega. Graahak
//  purane daam par order karega, dukaan naya daam maangegi, aur
//  beech me Maakit khada hoga -- ya graahak se jhooth bola jayega
//  ya dukaan ka nuksaan hoga. Dono me bharosa Maakit ka tootega.
//
//  Isliye: daam ka ek hi malik hai -- DUKAAN.
//
//  Aur packet kabhi aadha nahi dikhaya jata. Agar ek cheez bhi
//  khatam hai ya uska daam nahi bhara, to poora packet chhup
//  jata hai. "Sau rupaye ka packet" dikhakar beech me se do
//  cheezein nikal dena graahak ko dhokha hai.
// ============================================================

/** एक पैकेट — अंदर का सामान, और दुकान के दाम से जोड़ा हुआ दाम */
function pack_daam(PDO $pdo, $pack_id) {
    $st = $pdo->prepare(
        "SELECT pi.qty, i.id, i.name, i.unit, i.price, i.active, i.stock
           FROM pack_items pi JOIN shop_items i ON i.id = pi.shop_item_id
          WHERE pi.pack_id = ?
          ORDER BY i.sort_no, i.name");
    $st->execute([(int)$pack_id]);
    $rows = $st->fetchAll();

    $daam = 0; $poora = $rows ? true : false;
    foreach ($rows as $r) {
        // Ek bhi cheez tayyar na ho to packet poora nahi hai.
        if (!$r['active'] || $r['stock'] !== 'hai' || (int)$r['price'] <= 0) { $poora = false; }
        $daam += (int)$r['price'] * max(1, (int)$r['qty']);
    }
    return ['saaman' => $rows, 'daam' => $daam, 'poora' => $poora];
}

/**
 * दुकान के पैकेट।
 *   $live = true  -> sirf wahi jo graahak ko dikhne chahiye
 * Adhoore packet (jisme kuch khatam hai) live soochi me nahi aate.
 */
function pack_list(PDO $pdo, $bid, $live = false) {
    $sql = "SELECT * FROM packs WHERE business_id = ?";
    if ($live) $sql .= " AND status = 'live'";
    $sql .= " ORDER BY sort_no, mange DESC, id";
    $st = $pdo->prepare($sql);
    $st->execute([(int)$bid]);

    $out = [];
    foreach ($st->fetchAll() as $p) {
        $d = pack_daam($pdo, $p['id']);
        if ($live && !$d['poora']) continue;       // aadha packet nahi dikhega
        $out[] = $p + $d;
    }
    return $out;
}

/**
 * पैकेट बनाइए। Saamaan USI dukaan ka hona chahiye.
 *
 * Ye jaanch yahan hai, panne par nahi -- kyunki packet admin se
 * bhi banta hai aur bot se bhi, aur paisa ka niyam dono jagah ek
 * hi hona chahiye: ek packet = ek dukaan = ek jeb.
 *
 * $saaman: [shop_item_id => qty]
 */
function pack_banao(PDO $pdo, $bid, $naam, array $saaman, $note = null, $kyun = null, $by = null) {
    $bid = (int)$bid;
    $naam = trim((string)$naam);
    if ($naam === '')  throw new InvalidArgumentException('packet ka naam chahiye');
    if (!$saaman)      throw new InvalidArgumentException('packet khali nahi ho sakta');

    // Saara saamaan isi dukaan ka hai?
    $ids = array_map('intval', array_keys($saaman));
    $in  = implode(',', array_fill(0, count($ids), '?'));
    $st  = $pdo->prepare("SELECT id FROM shop_items WHERE business_id = ? AND id IN ($in)");
    $st->execute(array_merge([$bid], $ids));
    $apne = $st->fetchAll(PDO::FETCH_COLUMN);
    if (count($apne) !== count($ids)) {
        // Do dukaanon ka packet banate hi paisa baantne ka sawaal
        // aa jata -- aur Maakit kisi ka paisa nahi rakhta.
        throw new InvalidArgumentException('packet me doosri dukaan ka saamaan nahi daal sakte');
    }

    $pdo->beginTransaction();
    try {
        $pdo->prepare("INSERT INTO packs(business_id,name,note,kyun,banaya_by) VALUES(?,?,?,?,?)")
            ->execute([$bid, mb_substr($naam, 0, 80), $note !== null ? mb_substr($note, 0, 160) : null,
                       $kyun !== null ? mb_substr($kyun, 0, 80) : null, $by ? (int)$by : null]);
        $pid = (int)$pdo->lastInsertId();
        $add = $pdo->prepare("INSERT INTO pack_items(pack_id,shop_item_id,qty) VALUES(?,?,?)");
        foreach ($saaman as $iid => $q) $add->execute([$pid, (int)$iid, max(1, min(50, (int)$q))]);
        $pdo->commit();
        return $pid;
    } catch (Throwable $e) { $pdo->rollBack(); throw $e; }
}

/** मालिक की मंज़ूरी — बॉट का बनाया पैकेट तभी ग्राहक को दिखेगा */
function pack_manzoor(PDO $pdo, $pack_id, $by) {
    $pdo->prepare("UPDATE packs SET status='live', manzoor_by=?, manzoor_at=NOW()
                    WHERE id=? AND status<>'live'")
        ->execute([(int)$by, (int)$pack_id]);
}

/**
 * बॉट का काम — दुकान के अपने सामान से बजट पैकेट बना कर रख दीजिए।
 *
 * Ankit ne kaha tha: "100 rupaye ka ya 200 rupaye ka". Isliye bot
 * dukaan ke saamaan se utne ke aas-paas ke set jodta hai.
 *
 * Jo bot NAHI karta:
 *   - daam nahi badalta (sirf dukaan ke apne daam jodta hai)
 *   - khud live nahi karta (maalik manzoori dega)
 *   - jo packet pehle se us naam se hai, dobara nahi banata
 *
 * Lautata hai: kitne naye packet bane.
 */
function pack_bot(PDO $pdo, $bid, array $budgets = [100, 200]) {
    $bid = (int)$bid;
    $st = $pdo->prepare("SELECT id, name, price FROM shop_items
                          WHERE business_id=? AND active=1 AND stock='hai' AND price>0
                          ORDER BY sold DESC, sort_no, name");
    $st->execute([$bid]);
    $saaman = $st->fetchAll();
    if (count($saaman) < 2) return 0;              // do cheez se kam ka set set nahi hai

    $naam_hain = $pdo->prepare("SELECT name FROM packs WHERE business_id=?");
    $naam_hain->execute([$bid]);
    $pehle = array_map('mb_strtolower', $naam_hain->fetchAll(PDO::FETCH_COLUMN));

    $bane = 0;
    foreach ($budgets as $b) {
        $b = (int)$b;
        $naam = $b . ' रुपये का पैकेट';
        if (in_array(mb_strtolower($naam), $pehle, true)) continue;

        // Sabse zyada bikne wale se shuru kijiye, aur budget ke
        // andar rehte hue bharte jaiye. Budget se ZYADA kabhi
        // nahi -- "sau rupaye ka packet" 130 ka nikle to wo
        // graahak ke saath dhokha hai.
        $chune = []; $jod = 0;
        foreach ($saaman as $it) {
            $p = (int)$it['price'];
            if ($p <= 0 || $jod + $p > $b) continue;
            $chune[(int)$it['id']] = 1; $jod += $p;
            if (count($chune) >= 6) break;
        }
        if (count($chune) < 2 || $jod < (int)($b * 0.7)) continue;   // adhoora set na banaiye

        try {
            pack_banao($pdo, $bid, $naam, $chune,
                'इस दुकान के अपने दामों से जोड़ा हुआ — ' . $jod . ' रुपये का सामान',
                'budget');
            $bane++;
        } catch (Throwable $e) { error_log('pack_bot: ' . $e->getMessage()); }
    }
    return $bane;
}

/** ग्राहक ने पैकेट से मँगाया — गिनती, ताकि चलने वाले पैकेट ऊपर आएँ */
function pack_manga(PDO $pdo, $pack_id) {
    try { $pdo->prepare("UPDATE packs SET mange = mange + 1 WHERE id=?")->execute([(int)$pack_id]); }
    catch (Throwable $e) { error_log('pack_manga: ' . $e->getMessage()); }
}
