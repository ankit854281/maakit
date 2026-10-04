<?php
// ============================================================
// Maakit — purani kitaab ka hissa
// Niyam: Maakit sirf delivery charge leta hai.
// Kitaab ka paisa khareedne wala bechne wale ko seedha deta hai.
// ============================================================

/* bechna / muft / badalna */
function book_kinds() {
    return [
        'bech'  => ['l' => ['For sale', 'बेचनी है'],   'ic' => 'tag',  'col' => '#7A1F1F'],
        'muft'  => ['l' => ['Free',     'मुफ़्त देनी है'], 'ic' => 'gift', 'col' => '#2E7D4F'],
        'badal' => ['l' => ['Exchange', 'बदलनी है'],   'ic' => 'swap', 'col' => '#B3701A'],
    ];
}
function book_kind_label($k) {
    $x = book_kinds()[$k] ?? null;
    return $x ? t($x['l'][0], $x['l'][1]) : $k;
}
function book_kind_icon($k, $size = 18) {
    $x = book_kinds()[$k] ?? null;
    return svc_icon($x['ic'] ?? 'book', $size);
}

/* kis tarah ki kitaab */
function book_groups() {
    return [
        'school'  => ['School book (Class 1-12)', 'स्कूल की किताब (कक्षा 1-12)'],
        'college' => ['College / degree',         'कॉलेज / डिग्री'],
        'compete' => ['Competition (SSC, UP Police…)', 'कॉम्पिटिशन (SSC, UP पुलिस…)'],
        'novel'   => ['Novel / story',            'उपन्यास / कहानी'],
        'dharm'   => ['Religious',                'धार्मिक'],
        'bachche' => ['For children',             'बच्चों की'],
        'anya'    => ['Other',                    'दूसरी'],
    ];
}
function book_group_label($g) {
    $x = book_groups()[$g] ?? null;
    return $x ? t($x[0], $x[1]) : t('Other', 'दूसरी');
}
function book_group_icon($g, $size = 34) {
    $m = ['school' => 'school', 'college' => 'school', 'compete' => 'books',
          'novel' => 'book', 'dharm' => 'book', 'bachche' => 'books', 'anya' => 'book'];
    return svc_icon($m[$g] ?? 'book', $size);
}

/* kitaab ki halat */
function book_halat_list() {
    return [
        'naya'   => ['Like new',   'नई जैसी'],
        'theek'  => ['Good',       'ठीक-ठाक'],
        'purana' => ['Old but readable', 'पुरानी, पर पढ़ने लायक'],
    ];
}
function book_halat_label($h) {
    $x = book_halat_list()[$h] ?? null;
    return $x ? t($x[0], $x[1]) : $h;
}

function book_langs() {
    return ['hi' => ['Hindi', 'हिंदी'], 'en' => ['English', 'अंग्रेज़ी'], 'other' => ['Other', 'दूसरी']];
}
function book_lang_label($l) {
    $x = book_langs()[$l] ?? null;
    return $x ? t($x[0], $x[1]) : $l;
}

/* apni kitaab hataane ka code — ABC123 */
function new_manage_code() {
    $a = 'ABCDEFGHJKLMNPQRSTUVWXYZ';          // I aur O nahi, taaki 1 aur 0 se na uljhe
    $n = '23456789';
    $s = '';
    for ($i = 0; $i < 3; $i++) $s .= $a[random_int(0, strlen($a) - 1)];
    for ($i = 0; $i < 3; $i++) $s .= $n[random_int(0, strlen($n) - 1)];
    return $s;
}

/* daam ki line — bechna/muft/badal teeno ke liye */
function book_price_line($b) {
    if ($b['kind'] === 'muft')  return t('Free', 'मुफ़्त');
    if ($b['kind'] === 'badal') {
        return !empty($b['want'])
            ? t('Wants: ', 'चाहिए: ') . $b['want']
            : t('Exchange', 'बदलनी है');
    }
    $p = (int)($b['price'] ?? 0);
    return $p > 0 ? '₹' . num($p) : t('Price on call', 'दाम कॉल पर');
}

/* photo ho to photo, warna saaf icon */
function book_thumb($b, $size = 44) {
    if (!empty($b['photo'])) {
        return '<img src="/uploads/' . h($b['photo']) . '" alt="' . h($b['title']) . '" loading="lazy">';
    }
    return book_group_icon($b['grp'] ?? 'anya', $size);
}

/* kitaab ki delivery kitne se shuru — admin badal sakta hai */
function book_charge_from(PDO $pdo) {
    $v = (int)setg($pdo, 'book_charge_from', 30);
    return $v > 0 ? $v : 30;
}

/* ek kitaab nikaaliye */
function book_get(PDO $pdo, $id, $any = false) {
    $sql = "SELECT * FROM books WHERE id=?" . ($any ? '' : " AND status='live'");
    $st = $pdo->prepare($sql);
    $st->execute([(int)$id]);
    return $st->fetch() ?: null;
}

/* list — chhante hue */
function books_live(PDO $pdo, array $f = [], $limit = 60, $offset = 0) {
    $sql = "SELECT * FROM books WHERE status='live'";
    $par = [];
    if (!empty($f['kind']) && isset(book_kinds()[$f['kind']]))   { $sql .= " AND kind=?";    $par[] = $f['kind']; }
    if (!empty($f['grp'])  && isset(book_groups()[$f['grp']]))   { $sql .= " AND grp=?";     $par[] = $f['grp']; }
    if (!empty($f['lang']) && isset(book_langs()[$f['lang']]))   { $sql .= " AND lang=?";    $par[] = $f['lang']; }
    if (!empty($f['village']))                                   { $sql .= " AND village=?"; $par[] = $f['village']; }
    if (!empty($f['q'])) {
        $sql .= " AND (title LIKE ? OR author LIKE ? OR class_sub LIKE ? OR note LIKE ?)";
        $like = '%' . $f['q'] . '%';
        array_push($par, $like, $like, $like, $like);
    }
    $sql .= " ORDER BY id DESC LIMIT " . (int)$limit . " OFFSET " . (int)$offset;
    $st = $pdo->prepare($sql);
    $st->execute($par);
    return $st->fetchAll();
}

function books_count(PDO $pdo, $status = 'live') {
    try {
        $st = $pdo->prepare("SELECT COUNT(*) c FROM books WHERE status=?");
        $st->execute([$status]);
        return (int)$st->fetch()['c'];
    } catch (Throwable $e) { return 0; }
}

/* jin gaavon me kitaab padi hai */
function book_villages(PDO $pdo) {
    try {
        return $pdo->query("SELECT village, COUNT(*) c FROM books
                            WHERE status='live' AND village IS NOT NULL AND village<>''
                            GROUP BY village ORDER BY c DESC, village")->fetchAll();
    } catch (Throwable $e) { return []; }
}

/* ek mobile se ek din me kitni kitaab — spam rokne ke liye */
function books_today_by(PDO $pdo, $mobile) {
    $st = $pdo->prepare("SELECT COUNT(*) c FROM books WHERE seller_mobile=? AND created_at >= CURDATE()");
    $st->execute([$mobile]);
    return (int)$st->fetch()['c'];
}
define('BOOKS_PER_DAY', 5);
