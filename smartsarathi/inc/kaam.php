<?php
// ============================================================
//  सारथी — धंधे की सारी logic
//
//  Teen hisse:
//    1. सारथी   — login, haazri, kaam, OTP, chakkar, khata
//    2. ग्राहक कंपनी — chaabi se kaam bhejna
//    3. मालिक   — rate, bill, samasya
//
//  Paise ka niyam poore file me ek hi hai: Sarathi kisi ka
//  paisa apne paas NAHI rakhta. Khata sirf ginti hai.
// ============================================================

// ------------------------------------------------------------
//  सारथी का लॉगिन
// ------------------------------------------------------------
function rider_login(PDO $pdo, $mobile, $code) {
    $mob = preg_replace('/\D/', '', (string)$mobile);
    if ($mob === '' || $code === '') return null;
    $st = $pdo->prepare("SELECT * FROM riders WHERE mobile=? AND active=1");
    $st->execute([$mob]);
    $r = $st->fetch();
    return ($r && password_verify((string)$code, $r['pass_hash'])) ? $r : null;
}

function rider_start_session($r) {
    session_regenerate_id(true);           // purani id dobara kaam na kare
    $_SESSION['sr_rider'] = (int)$r['id'];
}

/** abhi kaun andar hai — har panne par database se taaza lete hain */
function rider_me(PDO $pdo) {
    $id = (int)($_SESSION['sr_rider'] ?? 0);
    if ($id <= 0) return null;
    $st = $pdo->prepare("SELECT * FROM riders WHERE id=? AND active=1");
    $st->execute([$id]);
    $r = $st->fetch();
    if (!$r) { unset($_SESSION['sr_rider']); return null; }   // khata band ho gaya
    return $r;
}

function rider_logout() {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 42000, 'path' => $p['path'] ?: '/',
            'httponly' => true, 'samesite' => 'Lax',
            'secure' => !empty($_SERVER['HTTPS']),
        ]);
    }
    if (session_status() === PHP_SESSION_ACTIVE) { session_regenerate_id(true); session_destroy(); }
}

// ------------------------------------------------------------
//  हाज़िरी — "मैं अभी तैयार हूँ"
// ------------------------------------------------------------
function duty_on(PDO $pdo, $rid, $minutes = 90) {
    $pdo->prepare("UPDATE riders SET ready_until = NOW() + INTERVAL ? MINUTE WHERE id=?")
        ->execute([(int)$minutes, (int)$rid]);
}
function duty_off(PDO $pdo, $rid) {
    $pdo->prepare("UPDATE riders SET ready_until = NULL WHERE id=?")->execute([(int)$rid]);
}
function duty_until(PDO $pdo, $rid) {
    $st = $pdo->prepare("SELECT ready_until FROM riders WHERE id=? AND ready_until > NOW()");
    $st->execute([(int)$rid]);
    return $st->fetchColumn() ?: null;
}

// ------------------------------------------------------------
//  चक्कर — एक बार निकलना, कई जगह
// ------------------------------------------------------------
function trip_open(PDO $pdo, $rid) {
    $st = $pdo->prepare("SELECT * FROM trips WHERE rider_id=? AND status='open' ORDER BY id DESC LIMIT 1");
    $st->execute([(int)$rid]);
    return $st->fetch() ?: null;
}
function trip_start(PDO $pdo, $rid) {
    if ($t = trip_open($pdo, $rid)) return (int)$t['id'];
    $pdo->prepare("INSERT INTO trips (rider_id) VALUES (?)")->execute([(int)$rid]);
    return (int)$pdo->lastInsertId();
}
/** chakkar tabhi band hota hai jab usme koi kaam baaki na ho */
function trip_close(PDO $pdo, $rid) {
    $st = $pdo->prepare("SELECT COUNT(*) FROM jobs j JOIN trips t ON t.id=j.trip_id
                          WHERE t.rider_id=? AND t.status='open'
                            AND j.status NOT IN ('delivered','cancelled')");
    $st->execute([(int)$rid]);
    if ((int)$st->fetchColumn() > 0) return false;
    $pdo->prepare("UPDATE trips SET status='closed', closed_at=NOW() WHERE rider_id=? AND status='open'")
        ->execute([(int)$rid]);
    return true;
}
function trip_add(PDO $pdo, $rid, $jid) {
    $tid = trip_start($pdo, $rid);
    $st = $pdo->prepare("SELECT COALESCE(MAX(trip_seq),0)+1 FROM jobs WHERE trip_id=?");
    $st->execute([$tid]);
    $seq = (int)$st->fetchColumn();
    $pdo->prepare("UPDATE jobs SET trip_id=?, trip_seq=? WHERE id=? AND rider_id=? AND trip_id IS NULL")
        ->execute([$tid, $seq, (int)$jid, (int)$rid]);
    return $tid;
}

// ------------------------------------------------------------
//  आज का काम
// ------------------------------------------------------------
function rider_jobs(PDO $pdo, $rid) {
    $st = $pdo->prepare(
        "SELECT j.*, c.name AS client_name
           FROM jobs j JOIN clients c ON c.id = j.client_id
          WHERE j.rider_id = ? AND j.status IN ('assigned','picked')
       ORDER BY (j.status='picked') DESC, j.trip_seq, j.id");
    $st->execute([(int)$rid]);
    return $st->fetchAll();
}

function rider_done_today(PDO $pdo, $rid) {
    $st = $pdo->prepare("SELECT COUNT(*) FROM jobs
                          WHERE rider_id=? AND status='delivered' AND DATE(delivered_at)=CURDATE()");
    $st->execute([(int)$rid]);
    return (int)$st->fetchColumn();
}

function rider_past(PDO $pdo, $rid, $limit = 40) {
    $st = $pdo->prepare(
        "SELECT j.*, c.name AS client_name FROM jobs j JOIN clients c ON c.id=j.client_id
          WHERE j.rider_id=? AND j.status='delivered'
       ORDER BY j.delivered_at DESC, j.id DESC LIMIT " . (int)$limit);
    $st->execute([(int)$rid]);
    return $st->fetchAll();
}

// ------------------------------------------------------------
//  दुकान से उठाया — Pick OTP
// ------------------------------------------------------------
/** lautata hai [theek hua?, sandesh] */
function job_pick(PDO $pdo, $rid, $jid, $otp) {
    $st = $pdo->prepare("SELECT * FROM jobs WHERE id=? AND rider_id=?");
    $st->execute([(int)$jid, (int)$rid]);
    $j = $st->fetch();
    if (!$j) return [false, 'ये काम आपके नाम पर नहीं है।'];
    if ($j['status'] === 'picked')    return [true,  'पहले ही उठा लिया गया था।'];
    if ($j['status'] === 'delivered') return [true,  'ये काम पूरा हो चुका है।'];
    if ($j['status'] === 'cancelled') return [false, 'ये काम रद्द हो चुका है।'];

    $diya = preg_replace('/\D/', '', (string)$otp);
    if ($diya === '' || !hash_equals($j['pick_otp'], $diya)) {
        return [false, 'OTP सही नहीं है। भेजने वाले से दोबारा पूछिए।'];
    }

    $pdo->beginTransaction();
    try {
        $up = $pdo->prepare("UPDATE jobs SET status='picked', picked_at=NOW()
                              WHERE id=? AND rider_id=? AND status='assigned'");
        $up->execute([(int)$jid, (int)$rid]);
        if ($up->rowCount() !== 1) { $pdo->rollBack(); return [false, 'काम की स्थिति बदल गई। सूची दोबारा देखिए।']; }
        trip_add($pdo, $rid, $jid);
        $pdo->commit();
    } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
    return [true, 'उठा लिया — दर्ज हो गया।'];
}

// ------------------------------------------------------------
//  ग्राहक को दे दिया — Drop code + फ़ोटो
// ------------------------------------------------------------
function job_deliver(PDO $pdo, $rid, $jid, $code, $photo = null) {
    $st = $pdo->prepare("SELECT * FROM jobs WHERE id=? AND rider_id=?");
    $st->execute([(int)$jid, (int)$rid]);
    $j = $st->fetch();
    if (!$j) return [false, 'ये काम आपके नाम पर नहीं है।'];
    if ($j['status'] === 'delivered') return [true,  'पहले ही पहुँचा दिया गया था।'];
    if ($j['status'] === 'cancelled') return [false, 'ये काम रद्द हो चुका है।'];
    if ($j['status'] !== 'picked')    return [false, 'पहले उठाना दर्ज कीजिए।'];

    $diya = preg_replace('/\D/', '', (string)$code);
    if ($diya === '' || !hash_equals($j['drop_code'], $diya)) {
        return [false, 'ग्राहक का कोड सही नहीं है। ग्राहक से दोबारा पूछिए।'];
    }

    $up = $pdo->prepare("UPDATE jobs SET status='delivered', delivered_at=NOW(),
                                pod_photo = COALESCE(?, pod_photo)
                          WHERE id=? AND rider_id=? AND status='picked'");
    $up->execute([$photo, (int)$jid, (int)$rid]);
    if ($up->rowCount() !== 1) return [false, 'काम की स्थिति बदल गई। सूची दोबारा देखिए।'];
    return [true, 'पहुँचा दिया — दर्ज हो गया।'];
}

// ------------------------------------------------------------
//  समस्या — "ये काम नहीं हो पा रहा"
// ------------------------------------------------------------
function problem_kinds() {
    return [
        'no_customer' => 'ग्राहक घर पर नहीं मिले',
        'no_phone'    => 'ग्राहक का फ़ोन नहीं लग रहा',
        'bad_address' => 'पता ग़लत है / जगह नहीं मिली',
        'shop_closed' => 'दुकान बंद है',
        'not_ready'   => 'सामान तैयार नहीं',
        'vehicle'     => 'गाड़ी ख़राब हो गई',
        'other'       => 'कुछ और',
    ];
}

/**
 * Kaam RADD nahi hota — radd karna maalik ka faisla hai.
 * Rider ke haath me wo taakat dena theek nahi.
 */
function problem_add(PDO $pdo, $rid, $jid, $kind, $note = '') {
    if (!isset(problem_kinds()[$kind])) return [false, 'समस्या चुनिए।'];
    $st = $pdo->prepare("SELECT id FROM jobs WHERE id=? AND rider_id=?");
    $st->execute([(int)$jid, (int)$rid]);
    if (!$st->fetch()) return [false, 'ये काम आपके नाम पर नहीं है।'];

    $pdo->prepare("INSERT INTO problems (job_id, rider_id, kind, note) VALUES (?,?,?,?)")
        ->execute([(int)$jid, (int)$rid, $kind, mb_substr(trim((string)$note), 0, 300) ?: null]);
    return [true, 'बता दिया गया। वो आपसे बात करेंगे।'];
}

function problems_open(PDO $pdo, $limit = 50) {
    $st = $pdo->prepare(
        "SELECT p.*, j.job_no, j.drop_name, j.drop_village, r.name AS rider_name
           FROM problems p JOIN jobs j ON j.id=p.job_id JOIN riders r ON r.id=p.rider_id
          WHERE p.settled = 0 ORDER BY p.id DESC LIMIT " . (int)$limit);
    $st->execute();
    return $st->fetchAll();
}

// ------------------------------------------------------------
//  खाता — कितना बना, कितना मिला, कितना बाकी
// ------------------------------------------------------------
/**
 * Kamai ginne ka tareeka:
 *   - chakkar ka pehla drop   -> poora rate
 *   - usi chakkar ka agla drop -> extra rate (kam mehnat)
 *   - chakkar ke bahar ka drop -> poora rate
 *
 * Isi se ek kaam ka kharch girta hai — aur client ko sasta
 * dene ki gunjaish banti hai, rider ka nuksan kiye bina.
 */
function rider_khata(PDO $pdo, $rid) {
    $st = $pdo->prepare("SELECT rate, extra_rate FROM riders WHERE id=?");
    $st->execute([(int)$rid]);
    $r = $st->fetch() ?: ['rate' => 0, 'extra_rate' => 0];
    $rate  = (int)$r['rate'];
    $extra = (int)$r['extra_rate'];

    $st = $pdo->prepare("SELECT DATE(delivered_at) din, trip_id, COUNT(*) n
                           FROM jobs WHERE rider_id=? AND status='delivered'
                       GROUP BY din, trip_id");
    $st->execute([(int)$rid]);

    $today = date('Y-m-d');
    $week  = date('Y-m-d', strtotime('-6 days'));
    $aaj = $aaj_n = $hafta = $hafta_n = $kul = $kul_n = 0;

    foreach ($st->fetchAll() as $g) {
        $n = (int)$g['n'];
        $paisa = $g['trip_id'] === null ? $n * $rate : $rate + max(0, $n - 1) * $extra;
        $kul += $paisa; $kul_n += $n;
        if ($g['din'] >= $week)   { $hafta += $paisa; $hafta_n += $n; }
        if ($g['din'] === $today) { $aaj   += $paisa; $aaj_n   += $n; }
    }

    $st = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM khata WHERE rider_id=?");
    $st->execute([(int)$rid]);
    $diya = (int)$st->fetchColumn();

    return ['rate' => $rate, 'extra' => $extra,
            'aaj' => $aaj, 'aaj_n' => $aaj_n,
            'hafta' => $hafta, 'hafta_n' => $hafta_n,
            'kul' => $kul, 'kul_n' => $kul_n,
            'diya' => $diya, 'baaki' => $kul - $diya];
}

/** maalik ne sarathi ko paisa diya — khate me likh dijiye */
function khata_add(PDO $pdo, $rid, $amount, $how = null, $note = null) {
    $amount = (int)$amount;
    if ($amount <= 0) return false;
    $pdo->prepare("INSERT INTO khata (rider_id, amount, paid_on, how, note) VALUES (?,?,CURDATE(),?,?)")
        ->execute([(int)$rid, $amount, $how ?: null, $note ?: null]);
    return true;
}

// ------------------------------------------------------------
//  ग्राहक कंपनी का बिल — यही "अलग धंधा" बनाता है
// ------------------------------------------------------------
/**
 * Maakit ab ek naam hai is list me — pehla client, par akela
 * nahi. Mahine ke aakhir me har client ko uska bill jaata hai.
 */
function client_bill(PDO $pdo, $cid, $from, $to) {
    $st = $pdo->prepare("SELECT fee, extra_fee FROM clients WHERE id=?");
    $st->execute([(int)$cid]);
    $c = $st->fetch() ?: ['fee' => 0, 'extra_fee' => 0];

    $st = $pdo->prepare("SELECT trip_id, COUNT(*) n FROM jobs
                          WHERE client_id=? AND status='delivered'
                            AND DATE(delivered_at) BETWEEN ? AND ?
                       GROUP BY trip_id");
    $st->execute([(int)$cid, $from, $to]);

    $kul = 0; $n_kul = 0;
    foreach ($st->fetchAll() as $g) {
        $n = (int)$g['n'];
        $kul += $g['trip_id'] === null
              ? $n * (int)$c['fee']
              : (int)$c['fee'] + max(0, $n - 1) * (int)$c['extra_fee'];
        $n_kul += $n;
    }
    return ['delivery' => $n_kul, 'rupay' => $kul,
            'fee' => (int)$c['fee'], 'extra_fee' => (int)$c['extra_fee']];
}

// ------------------------------------------------------------
//  दूरी के हिसाब से किराया — Google का बिल नहीं
// ------------------------------------------------------------
function fare_for(PDO $pdo, $km) {
    $slabs = [];
    foreach (explode(',', (string)setting($pdo, 'fare_slab', '')) as $part) {
        if (preg_match('/^\s*(\d+)\s*-\s*(\d+)\s*:\s*(\d+)\s*$/', $part, $m)) {
            $slabs[] = ['se' => (int)$m[1], 'tak' => (int)$m[2], 'daam' => (int)$m[3]];
        }
    }
    if (!$slabs) return null;
    usort($slabs, fn($a, $b) => $a['se'] <=> $b['se']);
    $km = (float)$km;
    foreach ($slabs as $s) if ($km > $s['se'] && $km <= $s['tak']) return $s['daam'];
    if ($km <= $slabs[0]['tak']) return $slabs[0]['daam'];
    return end($slabs)['daam'];
}

/** phone ka apna naksha khol deta hai — koi API chaabi nahi, koi bill nahi */
function maps_link($j) {
    $parts = array_filter([$j['drop_address'] ?? '', $j['drop_village'] ?? '', 'Uttar Pradesh'],
                          fn($x) => trim((string)$x) !== '');
    if (!$parts) return null;
    return 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode(implode(', ', $parts));
}

// ------------------------------------------------------------
//  छोटी चीज़ें
// ------------------------------------------------------------
/** चार अंक — गाँव में चार अंक बोलकर बताना आसान है */
function otp4() { return str_pad((string)random_int(0, 9999), 4, '0', STR_PAD_LEFT); }

function job_no(PDO $pdo) {
    for ($i = 0; $i < 20; $i++) {
        $no = 'SR' . date('ymd') . strtoupper(bin2hex(random_bytes(2)));
        $st = $pdo->prepare("SELECT 1 FROM jobs WHERE job_no=?");
        $st->execute([$no]);
        if (!$st->fetch()) return $no;
    }
    throw new RuntimeException('job number nahi ban paya');
}
