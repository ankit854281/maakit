<?php
// ============================================================
// Maakit — daam aur brand
//
// Niyam: Maakit daam TAY nahi karta. Maakit stock nahi rakhta,
// isliye daam dukaan ka hai. Par har order ka asli bill aata
// hai — to website har bill se SEEKH leti hai.
//
// Customer ko jo dikhta hai wo andaza hai, asli daam bill ka.
// ============================================================

/* ---------------- kitna purana daam maanya hai ----------------
   Sabzi ka daam roz badalta hai, aate ka mahine me ek baar.
   Isliye har tarah ke saaman ki apni ummar hai.            */
function daam_umar($grp) {
    switch ($grp) {
        case 'sabzi': case 'fal':               return 7;    // roz badalta hai
        case 'khana': case 'mithai':            return 21;
        case 'dairy': case 'peene':             return 30;
        default:                                return 75;   // packet wala saaman
    }
}

/* kam se kam itni kharid ke baad hi daam dikhao */
const DAAM_KAM_SE_KAM = 2;

/* ---------------- beech ka daam (median) ----------------
   Aukat (average) se nahi, beech wale se. Ek baar kisi ne
   galti se 2550 likh diya to aukat bigad jati hai, beech
   wala nahi bigadta.                                        */
function daam_beech(array $n) {
    if (!$n) return null;
    sort($n);
    $c = count($n);
    return (int)round($c % 2 ? $n[intdiv($c, 2)] : ($n[$c/2 - 1] + $n[$c/2]) / 2);
}

/* bahut zyada ya bahut kam wale hata do (galat likha hua) */
function daam_saaf(array $n) {
    if (count($n) < 4) return $n;
    $b = daam_beech($n);
    if (!$b) return $n;
    $bache = array_values(array_filter($n, fn($x) => $x >= $b * 0.45 && $x <= $b * 2.2));
    return $bache ?: $n;
}

/* ---------------- ek saaman ka daam dobara nikalo ----------------
   Jab bhi nayi kharid likhi jaye, ye chalta hai.             */
function daam_banao(PDO $pdo, $item_id, $brand_id = 0) {
    $item_id = (int)$item_id; $brand_id = (int)$brand_id;

    $g = $pdo->prepare("SELECT grp FROM items WHERE id=?");
    $g->execute([$item_id]);
    $grp = $g->fetchColumn() ?: 'anya';
    $din = daam_umar($grp);

    $sql = "SELECT price, created_at FROM item_prices
            WHERE item_id=? AND ok=1 AND price > 0
              AND created_at >= (NOW() - INTERVAL ? DAY)";
    $par = [$item_id, $din];
    if ($brand_id > 0) { $sql .= " AND brand_id=?"; $par[] = $brand_id; }
    $sql .= " ORDER BY created_at DESC LIMIT 25";          // sabse nayi 25 kharid

    $st = $pdo->prepare($sql);
    $st->execute($par);
    $rows = $st->fetchAll();

    if (count($rows) < DAAM_KAM_SE_KAM) {
        $pdo->prepare("DELETE FROM item_daam WHERE item_id=? AND brand_id=?")
            ->execute([$item_id, $brand_id]);
        return null;
    }

    $daam = daam_saaf(array_map(fn($r) => (int)$r['price'], $rows));
    $beech = daam_beech($daam);
    $last  = $rows[0]['created_at'];

    $pdo->prepare("INSERT INTO item_daam (item_id, brand_id, price, low, high, n, last_at)
                   VALUES (?,?,?,?,?,?,?)
                   ON DUPLICATE KEY UPDATE price=VALUES(price), low=VALUES(low),
                     high=VALUES(high), n=VALUES(n), last_at=VALUES(last_at)")
        ->execute([$item_id, $brand_id, $beech, min($daam), max($daam), count($daam), $last]);

    return ['price' => $beech, 'low' => min($daam), 'high' => max($daam),
            'n' => count($daam), 'last_at' => $last];
}

/* ---------------- nayi kharid likho ---------------- */
function daam_likho(PDO $pdo, $item_id, $price, $opt = []) {
    $price = (int)$price;
    if ($price <= 0 || $price > 100000) return false;

    $pdo->prepare("INSERT INTO item_prices
        (item_id, brand_id, market, shop, price, qty, order_id, by_user, note)
        VALUES (?,?,?,?,?,?,?,?,?)")
        ->execute([
            (int)$item_id,
            !empty($opt['brand_id']) ? (int)$opt['brand_id'] : null,
            $opt['market'] ?? null, $opt['shop'] ?? null,
            $price, max(1, (int)($opt['qty'] ?? 1)),
            !empty($opt['order_id']) ? (int)$opt['order_id'] : null,
            !empty($opt['by_user']) ? (int)$opt['by_user'] : null,
            $opt['note'] ?? null,
        ]);

    daam_banao($pdo, $item_id, 0);                             // sab brand milakar
    if (!empty($opt['brand_id'])) daam_banao($pdo, $item_id, (int)$opt['brand_id']);
    return true;
}

/* ---------------- dikhane ke liye daam ----------------
   Ek saath saare saaman ka — order page ke liye ek hi query. */
function daam_sab(PDO $pdo) {
    static $cache = null;
    if ($cache !== null) return $cache;
    $cache = [];
    try {
        $q = $pdo->query("SELECT d.item_id, d.price, d.low, d.high, d.n, d.last_at, i.grp
                          FROM item_daam d JOIN items i ON i.id = d.item_id
                          WHERE d.brand_id = 0");
        foreach ($q as $r) {
            $umr = daam_umar($r['grp']);
            if (strtotime($r['last_at']) < strtotime("-{$umr} days")) continue;   // baasi
            $cache[(int)$r['item_id']] = [
                'price' => (int)$r['price'], 'low' => (int)$r['low'],
                'high'  => (int)$r['high'],  'n'   => (int)$r['n'],
                'last_at' => $r['last_at'],
            ];
        }
    } catch (Throwable $e) { $cache = []; }
    return $cache;
}

/* ek saaman ka daam (brand ke saath bhi) */
function daam_ek(PDO $pdo, $item_id, $brand_id = 0) {
    try {
        $st = $pdo->prepare("SELECT d.*, i.grp FROM item_daam d JOIN items i ON i.id=d.item_id
                             WHERE d.item_id=? AND d.brand_id=?");
        $st->execute([(int)$item_id, (int)$brand_id]);
        $r = $st->fetch();
        if (!$r) return null;
        if (strtotime($r['last_at']) < strtotime('-' . daam_umar($r['grp']) . ' days')) return null;
        return ['price' => (int)$r['price'], 'low' => (int)$r['low'], 'high' => (int)$r['high'],
                'n' => (int)$r['n'], 'last_at' => $r['last_at']];
    } catch (Throwable $e) { return null; }
}

/* ---------------- daam ki likhawat ----------------
   Ek hi daam baar-baar aaya ho to "₹255".
   Oopar-neeche hota ho to "₹240 – 265".                     */
function daam_likhawat($d) {
    if (!$d) return null;
    $farq = $d['high'] - $d['low'];
    if ($d['n'] < 3 || $farq <= max(2, (int)round($d['price'] * 0.06))) {
        return '₹' . num($d['price']);
    }
    return '₹' . num($d['low']) . ' – ' . num($d['high']);
}

/* "3 din pehle" jaisi line */
function daam_kab($d) {
    return $d && !empty($d['last_at']) ? ago($d['last_at']) : '';
}

/* ---------------- brand ---------------- */
function brands_all(PDO $pdo, $only_active = true) {
    static $c = [];
    $k = $only_active ? 1 : 0;
    if (isset($c[$k])) return $c[$k];
    try {
        $sql = "SELECT * FROM brands" . ($only_active ? " WHERE active=1" : "") . " ORDER BY sort_no, name";
        $c[$k] = $pdo->query($sql)->fetchAll();
    } catch (Throwable $e) { $c[$k] = []; }
    return $c[$k];
}

function brand_naam($b) {
    return is_hi() ? $b['name'] : ($b['name_en'] ?: $b['name']);
}

/* kis saaman me kaun se brand — ek hi query me sab */
function item_brands_all(PDO $pdo) {
    static $c = null;
    if ($c !== null) return $c;
    $c = [];
    try {
        $q = $pdo->query("SELECT ib.item_id, b.id, b.name, b.name_en, b.kind
                          FROM item_brands ib JOIN brands b ON b.id = ib.brand_id
                          WHERE b.active = 1 ORDER BY ib.sort_no, b.sort_no, b.name");
        foreach ($q as $r) $c[(int)$r['item_id']][] = $r;
    } catch (Throwable $e) { $c = []; }
    return $c;
}

function item_brands_of(PDO $pdo, $item_id) {
    $all = item_brands_all($pdo);
    return $all[(int)$item_id] ?? [];
}

/* ---------------- bade hisse: raashan / bana khana ---------------- */
function sections() {
    return [
        'saaman' => ['Groceries & things', 'राशन और सामान'],
        'khana'  => ['Cooked food & sweets', 'बना खाना और मिठाई'],
    ];
}
function section_naam($s) {
    $x = sections()[$s] ?? null;
    return $x ? t($x[0], $x[1]) : $s;
}
