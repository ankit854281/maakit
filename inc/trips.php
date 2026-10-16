<?php
// ============================================================
//  सवारी और पार्सल — maakit.in/ride.php (grahak) → Smart Sarathi (rider)
//
//  Grahak Maakit par bookh karta hai; rider Smart Sarathi app me
//  kaam leta hai. Beech ka taar wahi inc/sarathi-bridge.php
//  (sb_call) hai jo dukaan ke orders ke liye bana hai.
//
//  Kiraya: Admin → Sarathi → "सवारी / पार्सल का किराया".
//    kiraya = shuru ka + (km × har km ka), ₹5 me gol
//    km = GPS ki seedhi doori × 1.3 (sadak ghoom kar jaati hai)
//  Ye "लगभग" hai — grahak ko saaf yahi likha dikhta hai.
//
//  Do code (dukaan ke orders jaise):
//    pick_code — sawari: baithte waqt rider ko; parcel: dete waqt
//    drop_code — sawari: utarte waqt; parcel: paane wala batata hai
// ============================================================
require_once __DIR__ . '/sarathi-bridge.php';

/** Current live rider point only; never keep a location history. */
function trip_location_upgrade(PDO $pdo) {
    static $done = false;
    if ($done) return;
    $done = true;
    try {
        $have = $pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='rides'")->fetchAll(PDO::FETCH_COLUMN);
        foreach ([
            'rider_lat' => 'DECIMAL(10,7) DEFAULT NULL',
            'rider_lng' => 'DECIMAL(10,7) DEFAULT NULL',
            'rider_location_at' => 'BIGINT UNSIGNED DEFAULT NULL',
        ] as $name => $type) {
            if (!in_array($name, $have, true)) $pdo->exec("ALTER TABLE rides ADD COLUMN `$name` $type");
        }
    } catch (Throwable $e) { error_log('Ride live-location upgrade: ' . $e->getMessage()); }
}

function trips_table(PDO $pdo) {
    static $ok = null;
    if ($ok !== null) return $ok;
    try {
        $pdo->query("SELECT 1 FROM rides LIMIT 1");
        trip_location_upgrade($pdo);
        return $ok = true;
    } catch (Throwable $e) {
        // update.php abhi nahi chala — table yahin bana do (034 wali hi)
        try {
            $sql = (string)@file_get_contents(__DIR__ . '/../sql/034-rides.sql');
            $sql = preg_replace('/^--.*$/m', '', $sql);
            $pdo->exec($sql);
            trip_location_upgrade($pdo);
            return $ok = true;
        } catch (Throwable $e2) { error_log('trips table: ' . $e2->getMessage()); return $ok = false; }
    }
}

/** Gaadiyan aur kiraya (settings se, warna ye shuru ke daam) */
function trip_vehicles(PDO $pdo) {
    $d = [
        'bike'   => ['Bike', 'बाइक', '🏍️', 1, 20, 7],
        'auto'   => ['Auto', 'ऑटो', '🛺', 3, 30, 10],
        'erick'  => ['E-rickshaw', 'ई-रिक्शा', '🛺', 4, 15, 6],
        'parcel' => ['Parcel', 'पार्सल', '📦', 0, 25, 6],
    ];
    $out = [];
    foreach ($d as $k => [$en, $hi, $em, $seats, $base, $km]) {
        $out[$k] = [
            'key' => $k, 'name' => t($en, $hi), 'emoji' => $em, 'seats' => $seats,
            'base' => max(0, (int)setg($pdo, "fare_{$k}_base", $base)),
            'km'   => max(0, (int)setg($pdo, "fare_{$k}_km", $km)),
            'on'   => setg($pdo, "fare_{$k}_on", '1') === '1',
        ];
    }
    return $out;
}
function trip_max_km(PDO $pdo) { return max(1, (int)setg($pdo, 'ride_max_km', 40)); }

function trip_geo_ok($g) { return is_string($g) && preg_match('/^-?\d{1,2}\.\d{3,8},-?\d{1,3}\.\d{3,8}$/D', $g); }

/** Do GPS ke beech sadak ki lagbhag doori (km), ya null */
function trip_km($a, $b) {
    if (!trip_geo_ok($a) || !trip_geo_ok($b)) return null;
    [$la1, $lo1] = array_map('floatval', explode(',', $a));
    [$la2, $lo2] = array_map('floatval', explode(',', $b));
    $r = 6371; $dl = deg2rad($la2 - $la1); $dn = deg2rad($lo2 - $lo1);
    $h = sin($dl / 2) ** 2 + cos(deg2rad($la1)) * cos(deg2rad($la2)) * sin($dn / 2) ** 2;
    $km = 2 * $r * asin(min(1, sqrt($h))) * 1.3;
    return round(max(0.5, $km), 1);
}

/** Lagbhag kiraya. km na ho to sirf shuru ka (aur rider phone par pakka karta hai) */
function trip_fare(array $v, $km) {
    if ($km === null) return null;
    $f = $v['base'] + $km * $v['km'];
    return (int)max($v['base'], ceil($f / 5) * 5);
}

function trip_code() { return str_pad((string)random_int(0, 9999), 4, '0', STR_PAD_LEFT); }

function trip_no(PDO $pdo, $kind) {
    $abc = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
    for ($i = 0; $i < 8; $i++) {
        $s = ''; for ($j = 0; $j < 4; $j++) $s .= $abc[random_int(0, strlen($abc) - 1)];
        $no = ($kind === 'parcel' ? 'PL' : 'RD') . date('md') . $s;
        $st = $pdo->prepare("SELECT 1 FROM rides WHERE trip_no=?"); $st->execute([$no]);
        if (!$st->fetch()) return $no;
    }
    return ($kind === 'parcel' ? 'PL' : 'RD') . strtoupper(bin2hex(random_bytes(5)));
}

/**
 * Nayi booking. $in = form ke khane. Lautata hai [ok, trip-row ya galti].
 * Booking hamesha Maakit me ban jaati hai; Smart Sarathi tak na pahunche
 * to admin ko "दोबारा भेजिए" dikhta hai — grahak ka kaam nahi rukta.
 */
function trip_create(PDO $pdo, array $in) {
    if (!trips_table($pdo)) return [false, t('Booking is not available right now. Please call us.', 'अभी बुकिंग नहीं हो पा रही। हमें फ़ोन कीजिए।')];
    $kind = ($in['kind'] ?? '') === 'parcel' ? 'parcel' : 'ride';
    $V = trip_vehicles($pdo);
    $vk = $kind === 'parcel' ? 'parcel' : (string)($in['vehicle'] ?? 'bike');
    if (!isset($V[$vk]) || $vk === 'parcel' && $kind !== 'parcel' || !$V[$vk]['on']) return [false, t('Choose a vehicle.', 'गाड़ी चुनिए।')];
    $v = $V[$vk];

    $name = trim((string)($in['name'] ?? ''));
    $mob  = preg_replace('/\D/', '', (string)($in['mobile'] ?? ''));
    if (strlen($mob) === 12 && strpos($mob, '91') === 0) $mob = substr($mob, 2);
    $pa = trim((string)($in['pick_address'] ?? ''));
    $da = trim((string)($in['drop_address'] ?? ''));
    if (mb_strlen($pa) < 3) return [false, t('Set the pickup place.', 'कहाँ से — जगह लिखिए या GPS लगाइए।')];
    if (mb_strlen($da) < 3) return [false, t('Set where to go.', 'कहाँ तक — जगह लिखिए।')];
    if (mb_strlen($name) < 2) return [false, t('Write your name.', 'अपना नाम लिखिए।')];
    if (!preg_match('/^[6-9]\d{9}$/D', $mob)) return [false, t('Mobile number must be 10 digits.', 'मोबाइल नंबर 10 अंकों का लिखिए।')];

    $dn = $name; $dm = $mob; $items = null; $pay = null; $seats = null;
    if ($kind === 'parcel') {
        if (($in['self'] ?? '') !== '1') {
            $dn = trim((string)($in['drop_name'] ?? ''));
            $dm = preg_replace('/\D/', '', (string)($in['drop_mobile'] ?? ''));
            if (strlen($dm) === 12 && strpos($dm, '91') === 0) $dm = substr($dm, 2);
            if (mb_strlen($dn) < 2) return [false, t('Write the receiver’s name.', 'पाने वाले का नाम लिखिए।')];
            if (!preg_match('/^[6-9]\d{9}$/D', $dm)) return [false, t('Receiver’s mobile must be 10 digits.', 'पाने वाले का मोबाइल 10 अंकों का लिखिए।')];
        }
        $types = trip_parcel_types();
        $items = trim(($types[$in['ptype'] ?? ''] ?? '') . (trim((string)($in['items'] ?? '')) !== '' ? ' — ' . trim((string)$in['items']) : ''), ' —');
        if ($items === '') return [false, t('Choose what you are sending.', 'क्या भेजना है, चुनिए।')];
        $pay = ($in['pay_by'] ?? '') === 'receiver' ? 'receiver' : 'sender';
    } else {
        $seats = max(1, min(max(1, $v['seats']), (int)($in['seats'] ?? 1)));
    }

    $pg = trip_geo_ok($in['pick_geo'] ?? null) ? $in['pick_geo'] : null;
    $dg = trip_geo_ok($in['drop_geo'] ?? null) ? $in['drop_geo'] : null;
    $km = trip_km($pg, $dg);
    if ($km !== null && $km > trip_max_km($pdo)) return [false, t('This is too far for us right now (max ', 'यह दूरी अभी हमारे लिए ज़्यादा है (ज़्यादा से ज़्यादा ') . trip_max_km($pdo) . ' km).'];
    $at = preg_match('/^([01]\d|2[0-3]):[0-5]\d$/D', (string)($in['at'] ?? '')) && ($in['when'] ?? '') === 'later' ? $in['at'] : null;

    // ek hi number se bahut saari khuli booking nahi
    $st = $pdo->prepare("SELECT COUNT(*) FROM rides WHERE mobile=? AND status IN ('new','assigned','picked') AND created_at > NOW() - INTERVAL 1 DAY");
    $st->execute([$mob]);
    if ((int)$st->fetchColumn() >= 3) return [false, t('You already have 3 open bookings. Please wait or call us.', 'आपकी 3 बुकिंग पहले से चल रही हैं। थोड़ा रुकिए या हमें फ़ोन कीजिए।')];

    $pc = trip_code(); do { $dc = trip_code(); } while ($dc === $pc);
    $no = trip_no($pdo, $kind);
    $pdo->prepare("INSERT INTO rides (trip_no, kind, vehicle, name, mobile, customer_id, pick_address, pick_geo, drop_name, drop_mobile, drop_address, drop_geo,
                   items, note, seats, pay_by, when_at, km, fare_est, pick_code, drop_code) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
        ->execute([$no, $kind, $vk, mb_substr($name, 0, 80), $mob, ($in['customer_id'] ?? null) ?: null,
                   mb_substr($pa, 0, 250), $pg, mb_substr($dn, 0, 80), $dm, mb_substr($da, 0, 250), $dg,
                   $items !== null ? mb_substr($items, 0, 300) : null, mb_substr(trim((string)($in['note'] ?? '')), 0, 300) ?: null,
                   $seats, $pay, $at, $km, trip_fare($v, $km), $pc, $dc]);
    $id = (int)$pdo->lastInsertId();
    try { trip_send($pdo, $id); } catch (Throwable $e) { error_log('trip_send: ' . $e->getMessage()); }
    return [true, trip_get($pdo, $id)];
}

function trip_parcel_types() {
    return ['tiffin' => t('Tiffin', 'टिफ़िन'), 'docs' => t('Documents', 'काग़ज़ात'), 'medicine' => t('Medicine', 'दवा'),
            'shop' => t('Shop goods', 'दुकान का सामान'), 'clothes' => t('Clothes', 'कपड़े'), 'keys' => t('Keys / small item', 'चाबी / छोटा सामान'), 'other' => t('Other', 'और कुछ')];
}

function trip_get(PDO $pdo, $id) {
    $st = $pdo->prepare("SELECT * FROM rides WHERE id=?"); $st->execute([(int)$id]);
    return $st->fetch() ?: null;
}

/** Smart Sarathi ko bhejiye (rider app me kaam). [ok, msg] */
function trip_send(PDO $pdo, $id) {
    $t = trip_get($pdo, $id);
    if (!$t) return [false, 'नहीं मिला'];
    if ($t['sarathi_job']) return [true, $t['sarathi_job']];
    if ($t['status'] === 'cancelled') return [false, 'रद्द है'];
    if (!sb_ready($pdo)) {
        $pdo->prepare("UPDATE rides SET send_error=? WHERE id=?")->execute(['Smart Sarathi जुड़ा नहीं है', (int)$id]);
        return [false, 'Smart Sarathi जुड़ा नहीं है'];
    }
    $V = trip_vehicles($pdo); $v = $V[$t['vehicle']] ?? $V['bike'];
    $ride = $t['kind'] === 'ride';
    $note = array_filter([
        $t['when_at'] ? 'समय: आज ' . $t['when_at'] : 'अभी',
        $t['km'] !== null ? 'लगभग ' . rtrim(rtrim((string)$t['km'], '0'), '.') . ' km' : '',
        !$ride && $t['fare_est'] !== null && $t['pay_by'] === 'sender' ? 'किराया ₹' . $t['fare_est'] . ' भेजने वाले से पिकअप पर लें' : '',
        $t['pick_geo'] ? 'उठाने की जगह: https://maps.google.com/?q=' . $t['pick_geo'] : '',
        (string)$t['note'],
    ]);
    $items = $ride ? $v['name'] . ' · ' . (int)$t['seats'] . ' ' . t('passenger(s)', 'सवारी') : (string)$t['items'];
    $cod = $t['fare_est'] !== null && ($ride || $t['pay_by'] === 'receiver') ? (int)$t['fare_est'] : null;
    [$ok, $r] = sb_call($pdo, 'create', [
        'ref' => $t['trip_no'], 'kind' => $ride ? 'ride' : 'delivery',
        'pick_name' => $t['name'], 'pick_mobile' => $t['mobile'], 'pick_address' => $t['pick_address'],
        'drop_name' => $t['drop_name'] ?: $t['name'], 'drop_mobile' => $t['drop_mobile'] ?: $t['mobile'], 'drop_address' => $t['drop_address'],
        'items' => $items, 'note' => implode(' · ', $note), 'cod_amount' => $cod,
        'pick_otp' => $t['pick_code'], 'drop_code' => $t['drop_code'],
        'pick_geo' => $t['pick_geo'], 'drop_geo' => $t['drop_geo'], 'vehicle' => $ride ? $t['vehicle'] : null,
    ]);
    if (!$ok) {
        $pdo->prepare("UPDATE rides SET send_error=? WHERE id=?")->execute([mb_substr((string)$r, 0, 200), (int)$id]);
        return [false, $r];
    }
    $job = $r['job'] ?? [];
    $pdo->prepare("UPDATE rides SET sarathi_job=?, send_error=NULL, checked_at=NOW() WHERE id=?")->execute([(string)($job['job_no'] ?? ''), (int)$id]);
    $t = trip_apply($pdo, $t, $job);
    // rider turant mil gaya ho to uska naam/number bhi abhi le aaiye
    if (($job['status'] ?? '') === 'assigned' && empty($job['rider'])) { try { trip_sync($pdo, $t, true); } catch (Throwable $e) {} }
    return [true, (string)($job['job_no'] ?? '')];
}

/** Smart Sarathi ka jawab booking par likhiye */
function trip_apply(PDO $pdo, array $t, array $job) {
    $s = (string)($job['status'] ?? '');
    if (!in_array($s, ['new', 'assigned', 'picked', 'delivered', 'cancelled'], true)) return $t;
    $rn = $job['rider']['name'] ?? null; $rm = $job['rider']['mobile'] ?? null;
    $loc = $job['rider_location'] ?? null;
    $lat = $lng = $at = null;
    if (in_array($s, ['assigned', 'picked'], true) && is_array($loc)
        && isset($loc['lat'], $loc['lng'], $loc['at'])
        && is_numeric($loc['lat']) && is_numeric($loc['lng']) && is_numeric($loc['at'])) {
        $la = (float)$loc['lat']; $lo = (float)$loc['lng']; $ts = (int)$loc['at'];
        if ($la >= -90 && $la <= 90 && $lo >= -180 && $lo <= 180 && $ts <= time() + 30 && $ts >= time() - 120) {
            $lat = $la; $lng = $lo; $at = $ts;
        }
    }
    $pdo->prepare("UPDATE rides SET status=?, rider_name=COALESCE(?, rider_name), rider_mobile=COALESCE(?, rider_mobile),
                   rider_lat=?, rider_lng=?, rider_location_at=?,
                   picked_at=IF(? IN ('picked','delivered'), COALESCE(picked_a