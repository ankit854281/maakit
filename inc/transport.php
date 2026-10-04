<?php
// ============================================================
// Maakit — Transport (gaadi wale)
//
// Gaadi wala khud apni gaadi darj karta hai. Admin dekh kar
// manzoori deta hai. Uske baad gaadi booking page par dikhne
// lagti hai, rate ke saath.
//
// Gaadi wala apne panel se roz bata sakta hai ki aaj gaadi
// khaali hai ya nahi.
// ============================================================

/** gaadi ke prakaar — seat aur salah ke saath */
function vtypes() {
    // kind: 'p' = sawari, 'g' = maal, 'b' = dono
    return [
        // ---- sawari ----
        'bolero'  => ['en'=>'Bolero',             'hi'=>'बोलेरो',               'seats'=>7,  'kind'=>'p', 'icon'=>'ride'],
        'suv'     => ['en'=>'Scorpio / XUV',      'hi'=>'स्कॉर्पियो / XUV',      'seats'=>7,  'kind'=>'p', 'icon'=>'ride'],
        'car'     => ['en'=>'Car (4 seat)',       'hi'=>'कार (4 सीट)',          'seats'=>4,  'kind'=>'p', 'icon'=>'ride'],
        'magic'   => ['en'=>'Tata Magic',         'hi'=>'टाटा मैजिक',            'seats'=>8,  'kind'=>'p', 'icon'=>'ride'],
        'tempo'   => ['en'=>'Tempo Traveller',    'hi'=>'टेम्पो ट्रैवलर',         'seats'=>12, 'kind'=>'p', 'icon'=>'ride'],
        'auto'    => ['en'=>'Auto',               'hi'=>'ऑटो',                  'seats'=>4,  'kind'=>'p', 'icon'=>'ride'],
        'bus'     => ['en'=>'Bus',                'hi'=>'बस',                   'seats'=>32, 'kind'=>'p', 'icon'=>'ride'],
        'ambul'   => ['en'=>'Ambulance',          'hi'=>'एम्बुलेंस',             'seats'=>4,  'kind'=>'p', 'icon'=>'medicine'],
        // ---- maal ----
        'ace'     => ['en'=>'Tata Ace / Chhota Hathi', 'hi'=>'छोटा हाथी (टाटा ऐस)', 'seats'=>2, 'kind'=>'g', 'cap'=>0.75, 'icon'=>'truck'],
        'pickup'  => ['en'=>'Pickup (Bolero/407)','hi'=>'पिकअप (बोलेरो/407)',    'seats'=>3,  'kind'=>'g', 'cap'=>1.5,  'icon'=>'truck'],
        'dala'    => ['en'=>'Dala / DCM',         'hi'=>'डाला / DCM',            'seats'=>3,  'kind'=>'g', 'cap'=>3,    'icon'=>'truck'],
        'truck6'  => ['en'=>'Truck (6 wheel)',    'hi'=>'ट्रक (6 चक्का)',        'seats'=>2,  'kind'=>'g', 'cap'=>9,    'icon'=>'truck'],
        'truck10' => ['en'=>'Truck (10 wheel)',   'hi'=>'ट्रक (10 चक्का)',       'seats'=>2,  'kind'=>'g', 'cap'=>16,   'icon'=>'truck'],
        'tractor' => ['en'=>'Tractor-trolley',    'hi'=>'ट्रैक्टर-ट्रॉली',        'seats'=>0,  'kind'=>'g', 'cap'=>4,    'icon'=>'tractor'],
        'tanker'  => ['en'=>'Water tanker',       'hi'=>'पानी का टैंकर',         'seats'=>2,  'kind'=>'g', 'cap'=>5,    'icon'=>'truck'],
        'jcb'     => ['en'=>'JCB / Tractor work', 'hi'=>'जेसीबी / ट्रैक्टर काम',  'seats'=>1,  'kind'=>'g', 'cap'=>0,    'icon'=>'tools'],
    ];
}
/** sirf sawari wali ya sirf maal wali */
function vtypes_of($kind) {
    $o = [];
    foreach (vtypes() as $k => $v) { if ($v['kind'] === $kind || $v['kind'] === 'b') $o[$k] = $v; }
    return $o;
}
function vtype_is_goods($k) { return (vtypes()[$k]['kind'] ?? 'p') === 'g'; }

function vtype_label($k) {
    $v = vtypes()[$k] ?? null;
    if (!$v) return $k;
    return is_hi() ? $v['hi'] : $v['en'];
}
function vtype_icon($k) { return vtypes()[$k]['icon'] ?? 'ride'; }

/** maal wali gaadi ki dhulai kitni (ton) */
function vtype_cap($k) { return vtypes()[$k]['cap'] ?? null; }

/** dikhane layak gaadi */
function transports_live(PDO $pdo, $only_available = true, $kind = null) {
    $sql = "SELECT tr.*, b.name AS biz_name, b.photo AS biz_photo
            FROM transports tr JOIN businesses b ON b.id = tr.business_id
            WHERE tr.status='approved' AND b.status='approved'";
    if ($only_available) $sql .= " AND tr.available=1";
    if ($kind === 'g')      $sql .= " AND tr.goods=1";
    elseif ($kind === 'p')  $sql .= " AND tr.passenger=1";
    $sql .= " ORDER BY tr.verified DESC, tr.trips DESC, tr.id DESC";
    try { return $pdo->query($sql)->fetchAll(); } catch (Throwable $e) { return []; }
}

function transport_get(PDO $pdo, $id) {
    $s = $pdo->prepare("SELECT tr.*, b.name AS biz_name, b.photo AS biz_photo
                        FROM transports tr JOIN businesses b ON b.id = tr.business_id WHERE tr.id=?");
    $s->execute([(int)$id]);
    return $s->fetch() ?: null;
}
function transport_by_business(PDO $pdo, $bid) {
    $s = $pdo->prepare("SELECT * FROM transports WHERE business_id=?");
    $s->execute([(int)$bid]);
    return $s->fetch() ?: null;
}

/** rate ek line me */
function rate_line($tr) {
    $p = [];
    if (!empty($tr['rate_km']))   $p[] = '₹' . (int)$tr['rate_km'] . t('/km', '/किमी');
    if (!empty($tr['rate_trip'])) $p[] = t('per trip ₹', 'एक फेरा ₹') . (int)$tr['rate_trip'];
    if (!empty($tr['rate_min']))  $p[] = t('min ₹', 'कम से कम ₹') . (int)$tr['rate_min'];
    if (!empty($tr['rate_day']))  $p[] = t('full day ₹', 'पूरा दिन ₹') . (int)$tr['rate_day'];
    return $p ? implode(' · ', $p) : t('Rate on call', 'रेट कॉल पर');
}

/** kitna maal dhone ki jagah */
function cap_line($tr) {
    if (empty($tr['capacity'])) return '';
    $c = (float)$tr['capacity'];
    return ($c == floor($c) ? (int)$c : $c) . ' ' . t('tonne', 'टन');
}

/** papers ka haal — [class, label] */
function papers_badge($tr) {
    if ((int)$tr['verified'] === 1) return ['tag-live', t('Papers checked', 'कागज़ देखे गए')];
    $n = (int)$tr['dl_ok'] + (int)$tr['rc_ok'] + (int)$tr['ins_ok'];
    if ($n >= 3) return ['tag-off', t('Papers declared', 'कागज़ बताए गए')];
    return ['tag-off', t('Papers not confirmed', 'कागज़ की पुष्टि नहीं')];
}

/** kaunse kaagaz jald khatam ho rahe hain (admin ke liye) */
function papers_expiring(PDO $pdo, $days = 30) {
    try {
        $s = $pdo->prepare("SELECT tr.*, b.name AS biz_name FROM transports tr
                            JOIN businesses b ON b.id=tr.business_id
                            WHERE tr.status='approved'
                              AND ((tr.ins_exp IS NOT NULL AND tr.ins_exp <= CURDATE() + INTERVAL ? DAY)
                                OR (tr.fit_exp IS NOT NULL AND tr.fit_exp <= CURDATE() + INTERVAL ? DAY))
                            ORDER BY LEAST(COALESCE(tr.ins_exp,'2099-01-01'), COALESCE(tr.fit_exp,'2099-01-01'))");
        $s->execute([$days, $days]);
        return $s->fetchAll();
    } catch (Throwable $e) { return []; }
}

/** "aaj khaali hai" kitna purana hai */
function avail_fresh($tr) {
    if (empty($tr['avail_updated'])) return false;
    return (time() - strtotime($tr['avail_updated'])) < 36 * 3600;
}

/**
 * Gaadi wale ka login.
 * (salon wala shop_login_business() sirf salon ke liye hai —
 *  usme salon_on=1 ki shart hai, isliye gaadi ke liye alag.)
 */
function transport_login_business(PDO $pdo) {
    if (empty($_COOKIE['mk_shop'])) return null;
    $st = $pdo->prepare("SELECT b.* FROM shop_tokens t
                         JOIN businesses b ON b.id = t.business_id
                         JOIN transports tr ON tr.business_id = b.id
                         WHERE t.token = ? AND t.expires > NOW() AND tr.status <> 'hidden'");
    $st->execute([$_COOKIE['mk_shop']]);
    return $st->fetch() ?: null;
}
