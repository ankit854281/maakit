<?php
// ============================================================
//  स्मार्ट सारथी — ऐप के अंदर बुकिंग (29 Oct 2026)
//
//  Customer app (ghar.php) se booking seedhe Sarathi ke "kaam"
//  (jobs) me banti hai — job_create() se, "सीधे ग्राहक" client ke
//  naam par. Kiraya aur doori SERVER par ginte hain; browser ka
//  bheja hua kiraya kabhi nahi maana jaata.
//
//  Turant wali sevayein (bike, auto, e-rickshaw, cab, parcel,
//  loader) apne aap hazir partner ko jaati hain. Baad ke samay
//  wali booking, साथ सफ़र aur केयर सेवाएँ office/admin assign
//  karta hai (no_auto).
// ============================================================
// Pages (ghar.php, book-api.php) load inc/public.php first; tests load only client.php.
require_once __DIR__ . '/client.php';
require_once __DIR__ . '/identity.php';
if (!function_exists('sr_direct_client')) {
    function sr_direct_client(PDO $pdo) {
        $st = $pdo->query("SELECT * FROM clients WHERE name='सीधे ग्राहक (वेबसाइट)' LIMIT 1");
        if ($c = $st->fetch()) return $c;
        $pdo->prepare("INSERT INTO clients (name, contact, fee, extra_fee, active) VALUES ('सीधे ग्राहक (वेबसाइट)', 'smartsarathi app', 0, 0, 1)")->execute();
        return $pdo->query("SELECT * FROM clients WHERE id=" . (int)$pdo->lastInsertId())->fetch();
    }
}

/** Sevayein: [kind, vehicle, base ₹, per km ₹, minimum ₹, auto-assign?] */
function sr_services(): array {
    $s = [
        'bike'   => ['ride',     'bike',   20, 7,  30,  true,  t('Bike', 'बाइक'),           t('1 person, fastest', '1 सवारी, सबसे तेज़')],
        'auto'   => ['ride',     'auto',   30, 10, 40,  true,  t('Auto', 'ऑटो'),            t('Up to 3 people', '3 सवारी तक')],
        'erick'  => ['ride',     'erick',  15, 6,  25,  true,  t('E-rickshaw', 'ई-रिक्शा'), t('Up to 4, cheapest', '4 सवारी तक, सबसे सस्ता')],
        'cab'    => ['ride',     'car',    60, 14, 150, true,  t('Cab / Bolero', 'कैब / बोलेरो'), t('Car or Bolero, local or outstation', 'कार या बोलेरो, शहर या बाहर')],
        'parcel' => ['delivery', null,     25, 6,  30,  true,  t('Parcel', 'पार्सल'),       t('Pick up & drop anything', 'कुछ भी उठवाइए, पहुँचवाइए')],
        'loader' => ['ride',     'loader', 150, 20, 250, true, t('Loader / Magic', 'लोडर / मैजिक'), t('Goods, shifting, shop stock', 'माल, घर शिफ़्ट, दुकान का सामान')],
        'saath'  => ['ride',     'car',    0,  5,  50,  false, t('Saath Safar', 'साथ सफ़र'), t('Shared seat on the same route', 'एक रास्ता, साझा सीट')],
        'care'   => ['ride',     'car',    300, 14, 600, false, t('Hospital Saathi', 'अस्पताल साथी'), t('Vehicle + companion for the whole visit', 'गाड़ी + पूरे समय साथी')],
        'seva'   => ['delivery', null,     40, 6,  60,  false, t('Door se Seva', 'दूर से सेवा'), t('Medicine, gas, ration for your family', 'घर वालों के लिए दवाई, गैस, राशन')],
    ];
    $out = [];
    foreach ($s as $k => [$kind, $veh, $base, $km, $min, $auto, $name, $tag]) {
        $out[$k] = ['key' => $k, 'kind' => $kind, 'vehicle' => $veh, 'name' => $name, 'tag' => $tag, 'auto' => $auto,
            // Admin "settings" table me fare_<key>_base / _km / _min daal kar badal sakta hai
            'base' => sr_setting_int("fare_{$k}_base", $base), 'km' => sr_setting_int("fare_{$k}_km", $km), 'min' => sr_setting_int("fare_{$k}_min", $min)];
    }
    return $out;
}
function sr_setting_int(string $k, int $d): int {
    global $pdo;
    try { return max(0, (int)setting($pdo, $k, $d)); } catch (Throwable $e) { return $d; }
}
function sr_geo_ok($g): bool { return is_string($g) && (bool)preg_match('/^-?\d{1,2}\.\d{3,8},-?\d{1,3}\.\d{3,8}$/D', $g); }
/** Sadak ki lagbhag doori (km) — seedhi doori × 1.3, ya null */
function sr_km($a, $b): ?float {
    if (!sr_geo_ok($a) || !sr_geo_ok($b)) return null;
    [$la1, $lo1] = array_map('floatval', explode(',', $a));
    [$la2, $lo2] = array_map('floatval', explode(',', $b));
    $dl = deg2rad($la2 - $la1); $dn = deg2rad($lo2 - $lo1);
    $h = sin($dl / 2) ** 2 + cos(deg2rad($la1)) * cos(deg2rad($la2)) * sin($dn / 2) ** 2;
    return round(max(0.5, 2 * 6371 * asin(min(1, sqrt($h))) * 1.3), 1);
}
function sr_fare(array $svc, float $km, int $seats = 1): int {
    $f = $svc['base'] + $svc['km'] * $km;
    if ($svc['key'] === 'saath') $f = $svc['km'] * $km * max(1, $seats);
    return (int)(ceil(max($svc['min'], $f) / 5) * 5);
}
/** Pahunchne ka andaza (minute): 22 km/h + 5 min pickup */
function sr_eta(float $km): int { return (int)ceil($km / 22 * 60) + 5; }
function sr_max_km(): int { return sr_setting_int('app_max_km', 150); }

/** Kiraya-suchi — dono jagah ke GPS se */
function sr_quote(string $pick, string $drop): array {
    $km = sr_km($pick, $drop);
    if ($km === null) return [false, t('Choose pickup and drop on the map.', 'नक्शे पर दोनों जगह चुनिए।')];
    if ($km > sr_max_km()) return [false, t('This is too far right now.', 'यह दूरी अभी बहुत ज़्यादा है।')];
    $list = [];
    foreach (sr_services() as $k => $s) $list[$k] = ['fare' => sr_fare($s, $km), 'name' => $s['name'], 'tag' => $s['tag']];
    return [true, ['km' => $km, 'eta' => sr_eta($km), 'fares' => $list]];
}

/** Booking banaiye. $acc = logged-in sr_accounts row. */
function sr_book(PDO $pdo, array $acc, array $in): array {
    $svcs = sr_services();
    $key = (string)($in['service'] ?? '');
    if (!isset($svcs[$key])) return [false, t('Choose a service.', 'सेवा चुनिए।')];
    $s = $svcs[$key];
    $name = trim((string)($in['name'] ?? ''));
    if (mb_strlen($name) < 2 || mb_strlen($name) > 80) return [false, t('Write your name.', 'अपना नाम लिखिए।')];
    $pa = trim((string)($in['pick_address'] ?? '')); $da = trim((string)($in['drop_address'] ?? ''));
    $pg = (string)($in['pick_geo'] ?? ''); $dg = (string)($in['drop_geo'] ?? '');
    if (mb_strlen($pa) < 3 || !sr_geo_ok($pg)) return [false, t('Set the pickup place.', 'पिकअप की जगह चुनिए।')];
    if (mb_strlen($da) < 3 || !sr_geo_ok($dg)) return [false, t('Set the drop place.', 'ड्रॉप की जगह चुनिए।')];
    $km = sr_km($pg, $dg);
    if ($km === null || $km > sr_max_km()) return [false, t('This is too far right now.', 'यह दूरी अभी बहुत ज़्यादा है।')];
    $seats = max(1, min(12, (int)($in['seats'] ?? 1)));
    $fare = sr_fare($s, $km, $seats);

    // Samay: abhi, ya aage ka (aaj se 30 din tak)
    $when = null;
    if (($in['when'] ?? '') === 'later') {
        $ts = strtotime((string)($in['at'] ?? ''));
        if (!$ts || $ts < time() + 15 * 60 || $ts > time() + 30 * 86400) return [false, t('Choose a time at least 15 minutes ahead.', 'कम से कम 15 मिनट आगे का समय चुनिए।')];
        $when = date('d M, h:i A', $ts);
    }

    // Parcel / seva: paane wala (alag ho sakta hai)
    $dn = $name; $dm = $acc['mobile'];
    if (in_array($key, ['parcel', 'seva'], true) && ($in['self'] ?? '1') !== '1') {
        $dn = trim((string)($in['drop_name'] ?? ''));
        $dm = preg_replace('/\D/', '', (string)($in['drop_mobile'] ?? ''));
        if (mb_strlen($dn) < 2) return [false, t('Write the receiver’s name.', 'पाने वाले का नाम लिखिए।')];
        if (!preg_match('/^[6-9]\d{9}$/D', $dm)) return [false, t('Receiver’s mobile must be 10 digits.', 'पाने वाले का मोबाइल 10 अंक का लिखिए।')];
    }

    // Ek number se ek saath bahut saari khuli booking nahi
    $c = sr_direct_client($pdo);
    $st = $pdo->prepare("SELECT COUNT(*) FROM jobs WHERE client_id=? AND pick_mobile=? AND status IN ('new','assigned','picked')");
    $st->execute([(int)$c['id'], $acc['mobile']]);
    if ((int)$st->fetchColumn() >= 3) return [false, t('You already have 3 open bookings.', 'आपकी 3 बुकिंग पहले से चल रही हैं।')];

    $items = mb_substr(trim((string)($in['items'] ?? '')), 0, 500);
    $note = array_filter([
        $s['name'] . ' · ' . $km . ' km · ₹' . $fare,
        $when ? t('Scheduled: ', 'समय: ') . $when : t('Now', 'अभी'),
        $key === 'saath' ? t('Seats: ', 'सीट: ') . $seats : null,
        mb_substr(trim((string)($in['note'] ?? '')), 0, 300) ?: null,
    ]);
    [$ok, $out] = job_create($pdo, $c, [
        'ref' => 'app-' . bin2hex(random_bytes(8)),
        'kind' => $s['kind'], 'vehicle' => $s['vehicle'],
        'pick_name' => $name, 'pick_mobile' => $acc['mobile'], 'pick_address' => $pa, 'pick_geo' => $pg,
        'drop_name' => $dn, 'drop_mobile' => $dm, 'drop_address' => $da, 'drop_geo' => $dg,
        'items' => $items ?: null, 'note' => implode("\n", $note), 'fee' => $fare, 'cod_amount' => $fare,
        'no_auto' => !$s['auto'] || $when !== null,
    ]);
    if (!$ok) return [false, t('Booking could not be made. Please call us.', 'बुकिंग नहीं हो पाई। फ़ोन कीजिए।')];
    return [true, ['job_no' => $out['job_no'], 'fare' => $fare, 'km' => $km]];
}

/** Customer ki apni booking (sirf uske number wali) */
function sr_my_job(PDO $pdo, array $acc, string $no): ?array {
    sr_jobs_upgrade($pdo);
    $c = sr_direct_client($pdo);
    $st = $pdo->prepare("SELECT j.*, r.name rider_name, r.mobile rider_mobile, r.vehicle rider_vehicle, r.rc_number rider_rc,
            IF(j.status IN ('assigned','picked') AND r.last_location_at >= UTC_TIMESTAMP() - INTERVAL 2 MINUTE, r.last_lat, NULL) rlat,
            IF(j.status IN ('assigned','picked') AND r.last_location_at >= UTC_TIMESTAMP() - INTERVAL 2 MINUTE, r.last_lng, NULL) rlng
        FROM jobs j LEFT JOIN riders r ON r.id=j.rider_id
        WHERE j.client_id=? AND j.job_no=? AND (j.pick_mobile=? OR j.drop_mobile=?) LIMIT 1");
    $st->execute([(int)$c['id'], $no, $acc['mobile'], $acc['mobile']]);
    return $st->fetch() ?: null;
}
function sr_job_view(array $j, array $acc): array {
    $mine = $j['pick_mobile'] === $acc['mobile'];
    return [
        'job_no' => $j['job_no'], 'status' => $j['status'], 'kind' => $j['kind'], 'vehicle' => $j['vehicle'],
        'fare' => (int)$j['fee'], 'pick' => $j['pick_address'], 'drop' => $j['drop_address'],
        'pick_geo' => $j['pick_geo'], 'drop_geo' => $j['drop_geo'], 'note' => $j['note'],
        // Booker ko pickup ka code; paane wale ko drop ka code (ride me dono booker hi hai)
        'start_otp' => $mine ? $j['pick_otp'] : null,
        'end_otp' => $j['drop_mobile'] === $acc['mobile'] ? $j['drop_code'] : null,
        'rider' => $j['rider_name'] ? ['name' => $j['rider_name'], 'mobile' => $j['rider_mobile'], 'vehicle' => $j['rider_vehicle'], 'number' => $j['rider_rc']] : null,
        'rider_at' => $j['rlat'] !== null ? [(float)$j['rlat'], (float)$j['rlng']] : null,
        'created' => $j['created_at'] ?? null,
    ];
}
function sr_my_jobs(PDO $pdo, array $acc, int $limit = 10): array {
    sr_jobs_upgrade($pdo);
    $c = sr_direct_client($pdo);
    $st = $pdo->prepare("SELECT job_no, status, kind, vehicle, fee, drop_address, created_at FROM jobs
        WHERE client_id=? AND (pick_mobile=? OR drop_mobile=?) ORDER BY id DESC LIMIT " . max(1, min(50, $limit)));
    $st->execute([(int)$c['id'], $acc['mobile'], $acc['mobile']]);
    return $st->fetchAll();
}
function sr_cancel_mine(PDO $pdo, array $acc, string $no): bool {
    $c = sr_direct_client($pdo);
    $j = sr_my_job($pdo, $acc, $no);
    if (!$j || $j['pick_mobile'] !== $acc['mobile']) return false;
    [$ok] = job_cancel($pdo, $c, $j['client_ref']);
    return (bool)$ok;
}
