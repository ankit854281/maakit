<?php
require_once __DIR__.'/fast2sms.php';
require_once __DIR__.'/identity.php';
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
    if (sr_firebase_enabled()) return null;
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
    $_SESSION['sr_rider_auth'] = hash('sha256', (string)$r['pass_hash']);
}

/** abhi kaun andar hai — har panne par database se taaza lete hain */
function rider_me(PDO $pdo) {
    if (sr_firebase_enabled()) return sr_identity_rider($pdo);
    $id = (int)($_SESSION['sr_rider'] ?? 0);
    if ($id <= 0) return null;
    $st = $pdo->prepare("SELECT * FROM riders WHERE id=? AND active=1");
    $st->execute([$id]);
    $r = $st->fetch();
    if (!$r || !hash_equals(hash('sha256', (string)$r['pass_hash']), (string)($_SESSION['sr_rider_auth'] ?? ''))) {
        unset($_SESSION['sr_rider'], $_SESSION['sr_rider_auth']);
        return null;  // band khata, purana login ya badla hua code
    }
    return $r;
}

/** Admin + CSRF-gated caller only; never return or log the new code. */
function rider_reset_code(PDO $pdo, $rid, $code, $confirm) {
    if (!is_string($code) || !is_string($confirm)
        || strlen($code) < 6 || strlen($code) > 72 || trim($code) === ''
        || !hash_equals($code, $confirm)) {
        return [false, 'दोनों जगह एक ही नया कोड भरिए — 6 से 72 अक्षर में।'];
    }
    $rid = (int)$rid;
    if ($rid <= 0) return [false, 'यह सारथी नहीं मिला।'];
    $st = $pdo->prepare("SELECT id FROM riders WHERE id=?");
    $st->execute([$rid]);
    if (!$st->fetch()) return [false, 'यह सारथी नहीं मिला।'];
    $pdo->prepare("UPDATE riders SET pass_hash=?, ready_until=NULL WHERE id=?")
        ->execute([password_hash($code, PASSWORD_DEFAULT), $rid]);
    return [true, 'नया कोड सेव हो गया। पुराने login बंद हैं; सारथी नए कोड से प्रवेश करे।'];
}

function rider_logout() {
    if (sr_firebase_enabled()) {
        global $pdo;
        $account = sr_account($pdo);
        $profile = $account ? sr_profile($pdo, (int)$account['id']) : null;
        if (!empty($profile['rider_id'])) duty_off($pdo, (int)$profile['rider_id']);
    }
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
    if (!sr_identity_rider_allowed($pdo, (int)$rid)) return;
    $pdo->prepare("UPDATE riders SET ready_until = NOW() + INTERVAL ? MINUTE WHERE id=?")
        ->execute([(int)$minutes, (int)$rid]);
    // Koi hazir na tha tab aaye kaam (sawari/delivery) ab de dijiye — pehle aaye, pehle
    try {
        sr_jobs_upgrade($pdo);
        $ids = $pdo->query("SELECT id FROM jobs WHERE status='new' AND assign_mode='auto' AND created_at > NOW() - INTERVAL 1 DAY ORDER BY id LIMIT 5")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($ids as $jid) job_auto_give($pdo, (int)$jid);
    } catch (Throwable $e) { error_log('Sarathi catch-up: ' . $e->getMessage()); }
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
//  काम किसको दिया जाए
//
//  Ye bich ki kadi pehle chhoot gayi thi. API se kaam aata tha
//  aur 'new' par pada reh jata tha -- kisi sarathi ko dikhta hi
//  nahi tha, kyunki sarathi ko sirf APNE kaam dikhte hain.
//
//  Ab do raaste hain:
//    1. kaam aate hi khud chala jata hai (jo hazir hai, jiske
//       paas sabse kam kaam hai)
//    2. koi hazir na ho to maalik ke panne par ruka dikhta hai,
//       aur wahan se haath se diya ja sakta hai
// ------------------------------------------------------------
/**
 * Ek kaam ek sarathi ko de dijiye.
 *
 * Do log ek saath na de dein -- isliye shart queries me hi hai:
 * badalta sirf tab hai jab kaam abhi tak 'new' ho. Doosre ko
 * 0 rows milti hain aur wo saaf mana ho jata hai.
 *
 * Lautata hai [theek hua?, sandesh]
 */
function job_give(PDO $pdo, $jid, $rid) {
    if (!sr_identity_rider_allowed($pdo, (int)$rid)) return [false, t("Rider approval required.", "राइडर मंज़ूरी बाकी है।")];
    $st = $pdo->prepare("SELECT id, name FROM riders WHERE id=? AND active=1");
    $st->execute([(int)$rid]);
    if (!$r = $st->fetch()) return [false, 'ये सारथी नहीं मिला।'];

    $st = $pdo->prepare("UPDATE jobs SET rider_id=?, status='assigned', offer_rider=NULL, offer_until=NULL
                          WHERE id=? AND status='new'");
    $st->execute([(int)$rid, (int)$jid]);
    if ($st->rowCount() !== 1) return [false, 'ये काम अब बाकी नहीं रहा — शायद किसी और को दे दिया गया।'];

    return [true, $r['name'] . ' को दे दिया गया।'];
}

/**
 * Kaam aate hi khud de dijiye -- us sarathi ko jo abhi hazir hai
 * aur jiske paas sabse kam kaam hai.
 *
 * Koi hazir na ho to kuch mat kijiye. Kaam 'new' par ruka rehta
 * hai aur maalik ke panne par upar dikhta hai. Jabardasti kisi
 * band baithe sarathi ko dena uska matlab hi khatam kar deta.
 *
 * Lautata hai rider_id, ya null.
 */
function job_auto_give(PDO $pdo, $jid) {
    // Ek rider dono kaam karta hai — par sirf wahi jo usne apni profile me chuna hai.
    // Sawari: dafter ki ijaazat (ride_ok) + rider ne "सवारी" chalu ki + gaadi mel khaaye.
    sr_jobs_upgrade($pdo);
    $jq = $pdo->prepare("SELECT kind, vehicle, assign_mode FROM jobs WHERE id=?"); $jq->execute([(int)$jid]);
    $job = $jq->fetch() ?: ['kind' => 'delivery', 'vehicle' => null, 'assign_mode' => 'auto'];
    if (($job['assign_mode'] ?? 'auto') === 'office') return null;   // office wala kaam — sirf admin dega
    $args = [];
    $approval = sr_firebase_enabled() ? " AND EXISTS (SELECT 1 FROM sr_rider_profiles p JOIN sr_accounts a ON a.id=p.account_id WHERE p.rider_id=r.id AND p.status='approved' AND a.active=1)" : "";
    if (($job['kind'] ?? 'delivery') === 'ride') {
        $fit = sr_ride_fits($job['vehicle'] ?? '');
        $cond = "r.do_ride = 1 AND r.ride_ok = 1 AND r.vtype IN (" . implode(',', array_fill(0, count($fit), '?')) . ")";
        $args = $fit;
    } else {
        $cond = "r.do_delivery = 1";
    }
    $st = $pdo->prepare(
        "SELECT r.id, r.last_lat, r.last_lng, r.work_area, r.work_km,
                TIMESTAMPDIFF(SECOND, r.last_location_at, UTC_TIMESTAMP()) AS loc_age,
                (SELECT COUNT(*) FROM jobs j
                  WHERE j.rider_id = r.id AND j.status IN ('assigned','picked')) AS abhi
           FROM riders r
          WHERE r.active = 1 AND r.ready_until > NOW() AND $cond $approval
            AND r.id NOT IN (SELECT s.rider_id FROM sr_job_skips s WHERE s.job_id = ?)
            AND NOT EXISTS (SELECT 1 FROM jobs a WHERE a.rider_id = r.id AND a.status IN ('assigned','picked'))
            AND NOT EXISTS (SELECT 1 FROM jobs o WHERE o.offer_rider = r.id AND o.status = 'new' AND o.offer_until > NOW() AND o.id <> ?)
       ORDER BY abhi ASC, r.id ASC
          LIMIT 200");
    $args[] = (int)$jid; $args[] = (int)$jid;
    $st->execute($args);
    $list = $st->fetchAll();
    if (!$list) return null;

    // Ilaka: pickup se rider ki doori (taaza GPS, warna uske kasbe ka centre) uski chuni hui hadd ke andar.
    // Sabse kam chalu kaam wala, phir sabse paas wala. Jisne ilaka nahi chuna, wo sabse aakhir me.
    $gq = $pdo->prepare("SELECT pick_geo FROM jobs WHERE id=?"); $gq->execute([(int)$jid]);
    $pick = sr_geo_parse((string)$gq->fetchColumn());
    $areas = function_exists('sr_work_areas') ? sr_work_areas() : [];
    $best = null; $bestKey = null;
    foreach ($list as $r) {
        $pt = null;
        if ($r['last_lat'] !== null && $r['loc_age'] !== null && (int)$r['loc_age'] <= 600) $pt = [(float)$r['last_lat'], (float)$r['last_lng']];
        elseif (isset($areas[$r['work_area']])) $pt = [$areas[$r['work_area']][2], $areas[$r['work_area']][3]];
        $d = 9999;
        if ($pick && $pt) {
            $d = sr_geo_km($pick, $pt);
            if ((int)$r['work_km'] > 0 && $d > (int)$r['work_km'] + 1) continue;   // ilake ke bahar
        }
        $key = [(int)$r['abhi'], (int)$r['work_km'] > 0 ? 0 : 1, $d, (int)$r['id']];
        if ($bestKey === null || $key < $bestKey) { $best = $r; $bestKey = $key; }
    }
    if (!$best) return null;

    // Seedha thopna nahi: 20 second ka offer. Accept par hi kaam uska (job_offer_accept).
    $up = $pdo->prepare("UPDATE jobs SET offer_rider=?, offer_until=NOW() + INTERVAL " . SR_OFFER_SECONDS . " SECOND
                          WHERE id=? AND status='new' AND (offer_rider IS NULL OR offer_until < NOW())");
    $up->execute([(int)$best['id'], (int)$jid]);
    return $up->rowCount() === 1 ? (int)$best['id'] : null;
}

const SR_OFFER_SECONDS = 20;

/** Rider ne "स्वीकार" dabaya — tabhi kaam uska (5 sec ki chhoot net ki der ke liye) */
function job_offer_accept(PDO $pdo, $rid, $jid) {
    sr_jobs_upgrade($pdo);
    if (!sr_identity_rider_allowed($pdo, (int)$rid)) return [false, t('Rider approval required.', 'राइडर मंज़ूरी बाकी है।')];
    $up = $pdo->prepare("UPDATE jobs SET rider_id=?, status='assigned', offer_rider=NULL, offer_until=NULL
                          WHERE id=? AND status='new' AND offer_rider=? AND offer_until >= NOW() - INTERVAL 5 SECOND");
    $up->execute([(int)$rid, (int)$jid, (int)$rid]);
    if ($up->rowCount() !== 1) return [false, t('This job is no longer available.', 'यह काम अब उपलब्ध नहीं है — समय निकल गया या रद्द हो गया।')];
    return [true, t('Job accepted. Go to pickup.', 'काम आपका हुआ। पिकअप पर पहुँचिए।')];
}
/** Mana kiya / samay nikal gaya — agle paas wale ko offer */
function job_offer_reject(PDO $pdo, $rid, $jid, $why = 'reject') {
    sr_jobs_upgrade($pdo);
    $pdo->prepare("INSERT IGNORE INTO sr_job_skips (job_id, rider_id, why) VALUES (?,?,?)")->execute([(int)$jid, (int)$rid, $why === 'timeout' ? 'timeout' : 'reject']);
    $up = $pdo->prepare("UPDATE jobs SET offer_rider=NULL, offer_until=NULL WHERE id=? AND offer_rider=? AND status='new'");
    $up->execute([(int)$jid, (int)$rid]);
    if ($up->rowCount() === 1) { try { job_auto_give($pdo, (int)$jid); } catch (Throwable $e) { error_log('Sarathi re-offer: ' . $e->getMessage()); } }
    return [true, t('Skipped.', 'छोड़ दिया।')];
}
/** Bina cron: jab bhi koi panna (rider / grahak / admin) poochhe, purane offer aage badhao aur khaali kaam offer karo */
function sr_offers_sweep(PDO $pdo) {
    try {
        sr_jobs_upgrade($pdo);
        $old = $pdo->query("SELECT id, offer_rider FROM jobs WHERE status='new' AND offer_rider IS NOT NULL AND offer_until < NOW() - INTERVAL 5 SECOND LIMIT 20")->fetchAll();
        foreach ($old as $o) job_offer_reject($pdo, (int)$o['offer_rider'], (int)$o['id'], 'timeout');
        $free = $pdo->query("SELECT id FROM jobs WHERE status='new' AND assign_mode='auto' AND offer_rider IS NULL AND created_at > NOW() - INTERVAL 1 DAY ORDER BY id LIMIT 5")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($free as $jid) job_auto_give($pdo, (int)$jid);
    } catch (Throwable $e) { error_log('Sarathi sweep: ' . $e->getMessage()); }
}
/** Is rider ke liye abhi ka offer (ya null) */
function rider_offer(PDO $pdo, $rid) {
    sr_offers_sweep($pdo);
    $st = $pdo->prepare("SELECT j.id, j.job_no, j.kind, j.vehicle, j.pick_address, j.drop_address, j.pick_geo, j.drop_geo, j.fee, j.cod_amount,
                                TIMESTAMPDIFF(SECOND, NOW(), j.offer_until) AS secs
                           FROM jobs j WHERE j.offer_rider=? AND j.status='new' AND j.offer_until > NOW() ORDER BY j.offer_until LIMIT 1");
    $st->execute([(int)$rid]);
    $o = $st->fetch();
    if (!$o) return null;
    $p = sr_geo_parse((string)$o['pick_geo']); $d = sr_geo_parse((string)$o['drop_geo']);
    $o['trip_km'] = ($p && $d) ? round(sr_geo_km($p, $d) * 1.3, 1) : null;
    $r = $pdo->prepare("SELECT last_lat, last_lng, work_area, TIMESTAMPDIFF(SECOND, last_location_at, UTC_TIMESTAMP()) age FROM riders WHERE id=?"); $r->execute([(int)$rid]); $r = $r->fetch();
    $at = null;
    if ($r && $r['last_lat'] !== null && (int)$r['age'] <= 600) $at = [(float)$r['last_lat'], (float)$r['last_lng']];
    elseif ($r && function_exists('sr_work_areas') && isset(sr_work_areas()[$r['work_area']])) { $w = sr_work_areas()[$r['work_area']]; $at = [$w[2], $w[3]]; }
    $o['to_pick_km'] = ($p && $at) ? round(sr_geo_km($at, $p) * 1.3, 1) : null;
    return $o;
}
function sr_geo_parse($g) {
    return preg_match('/^(-?\d{1,2}(?:\.\d+)?),(-?\d{1,3}(?:\.\d+)?)$/', trim((string)$g), $m) ? [(float)$m[1], (float)$m[2]] : null;
}
function sr_geo_km(array $a, array $b) {
    $r = 6371; $dl = deg2rad($b[0] - $a[0]); $dn = deg2rad($b[1] - $a[1]);
    $x = sin($dl / 2) ** 2 + cos(deg2rad($a[0])) * cos(deg2rad($b[0])) * sin($dn / 2) ** 2;
    return 2 * $r * asin(min(1, sqrt($x)));
}

/** जो काम अभी किसी को नहीं दिया गया */
function jobs_waiting(PDO $pdo, $limit = 30) {
    $st = $pdo->query(
        "SELECT j.*, c.name AS client_name
           FROM jobs j JOIN clients c ON c.id = j.client_id
          WHERE j.status = 'new'
       ORDER BY j.created_at ASC, j.id ASC
          LIMIT " . (int)$limit);
    return $st->fetchAll();
}

/** अभी कौन हाज़िर है (काम देने के लिए) */
function riders_ready(PDO $pdo) {
    return $pdo->query(
        "SELECT r.id, r.name, r.ready_until > NOW() AS hazir,
                (SELECT COUNT(*) FROM jobs j
                  WHERE j.rider_id = r.id AND j.status IN ('assigned','picked')) AS abhi
           FROM riders r WHERE r.active = 1
       ORDER BY hazir DESC, abhi ASC, r.name")->fetchAll();
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
    if (!sr_identity_rider_allowed($pdo, (int)$rid)) return [false, t("Rider approval required.", "राइडर मंज़ूरी बाकी है।")];
    $st = $pdo->prepare("SELECT * FROM jobs WHERE id=? AND rider_id=?");
    $st->execute([(int)$jid, (int)$rid]);
    $j = $st->fetch();
    if (!$j) return [false, 'ये काम आपके नाम पर नहीं है।'];
    if ($j['status'] === 'picked')    return [true,  'पहले ही उठा लिया गया था।'];
    if ($j['status'] === 'delivered') return [true,  'ये काम पूरा हो चुका है।'];
    if ($j['status'] === 'cancelled') return [false, 'ये काम रद्द हो चुका है।'];

    $guard=new Fast2SmsOtp($pdo);$guard->install();
    if (!$guard->take('pick-check:'.$jid.':'.$rid,5,900)) return [false,'बहुत कोशिश हो गई। 15 मिनट बाद कोशिश कीजिए।'];
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
    f2s_notify($pdo,$j['drop_mobile'],'delivery',$j['job_no'],$j['drop_code']);
    return [true, 'उठा लिया — दर्ज हो गया।'];
}

// ------------------------------------------------------------
//  ग्राहक को दे दिया — Drop code + फ़ोटो
// ------------------------------------------------------------
function job_deliver(PDO $pdo, $rid, $jid, $code, $photo = null) {
    if (!sr_identity_rider_allowed($pdo, (int)$rid)) return [false, t("Rider approval required.", "राइडर मंज़ूरी बाकी है।")];
    $st = $pdo->prepare("SELECT * FROM jobs WHERE id=? AND rider_id=?");
    $st->execute([(int)$jid, (int)$rid]);
    $j = $st->fetch();
    if (!$j) return [false, 'ये काम आपके नाम पर नहीं है।'];
    if ($j['status'] === 'delivered') return [true,  'पहले ही पहुँचा दिया गया था।'];
    if ($j['status'] === 'cancelled') return [false, 'ये काम रद्द हो चुका है।'];
    if ($j['status'] !== 'picked')    return [false, 'पहले उठाना दर्ज कीजिए।'];

    $guard=new Fast2SmsOtp($pdo);$guard->install();
    if (!$guard->take('drop-check:'.$jid.':'.$rid,5,900)) return [false,'बहुत कोशिश हो गई। 15 मिनट बाद कोशिश कीजिए।'];
    $diya = preg_replace('/\D/', '', (string)$code);
    if ($diya === '' || !hash_equals($j['drop_code'], $diya)) {
        return [false, 'ग्राहक का कोड सही नहीं है। ग्राहक से दोबारा पूछिए।'];
    }

    $up = $pdo->prepare("UPDATE jobs SET status='delivered', delivered_at=NOW(),
                                pod_photo = COALESCE(?, pod_photo)
                          WHERE id=? AND rider_id=? AND status='picked'");
    $up->execute([$photo, (int)$jid, (int)$rid]);
    if ($up->rowCount() !== 1) return [false, 'काम की स्थिति बदल गई। सूची दोबारा देखिए।'];
    rider_location_clear_if_idle($pdo, $rid);
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

/**
 * jobs table me naye khaane (Oct 2026): kind (delivery / ride), pick_geo,
 * drop_geo ("lat,lng"). Pehli baar zaroorat padte hi apne aap jud jaate
 * hain — maalik ko phpMyAdmin nahi kholna.
 */
function sr_jobs_upgrade(PDO $pdo) {
    static $done = false;
    if ($done) return;
    $done = true;
    try {
        $have = $pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='jobs'")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('kind', $have, true))     $pdo->exec("ALTER TABLE jobs ADD COLUMN kind VARCHAR(10) NOT NULL DEFAULT 'delivery'");
        if (!in_array('pick_geo', $have, true)) $pdo->exec("ALTER TABLE jobs ADD COLUMN pick_geo VARCHAR(40) DEFAULT NULL");
        if (!in_array('drop_geo', $have, true)) $pdo->exec("ALTER TABLE jobs ADD COLUMN drop_geo VARCHAR(40) DEFAULT NULL");
        if (!in_array('vehicle', $have, true))  $pdo->exec("ALTER TABLE jobs ADD COLUMN vehicle VARCHAR(10) DEFAULT NULL");
        // auto = app khud rider ko de; office = baad ka samay / Saath Safar / Hospital Saathi / Door se Seva — sirf admin de
        if (!in_array('assign_mode', $have, true)) $pdo->exec("ALTER TABLE jobs ADD COLUMN assign_mode VARCHAR(8) NOT NULL DEFAULT 'auto'");
        // Offer (Rapido jaisa): kaam pehle ek rider ko 20 sec ke liye "offer" — Accept par hi uska
        if (!in_array('offer_rider', $have, true)) $pdo->exec("ALTER TABLE jobs ADD COLUMN offer_rider INT DEFAULT NULL, ADD COLUMN offer_until DATETIME DEFAULT NULL, ADD INDEX idx_offer (offer_rider, status)");
        $pdo->exec("CREATE TABLE IF NOT EXISTS sr_job_skips (job_id INT NOT NULL, rider_id INT NOT NULL, why VARCHAR(8) NOT NULL DEFAULT 'reject', at DATETIME DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (job_id, rider_id)) ENGINE=InnoDB");
        // Ek hi rider — delivery bhi, sawari bhi (ek login, ek profile)
        $rh = $pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='riders'")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('vtype', $rh, true))       $pdo->exec("ALTER TABLE riders ADD COLUMN vtype VARCHAR(10) DEFAULT NULL");
        if (!in_array('do_delivery', $rh, true)) $pdo->exec("ALTER TABLE riders ADD COLUMN do_delivery TINYINT NOT NULL DEFAULT 1");
        if (!in_array('do_ride', $rh, true))     $pdo->exec("ALTER TABLE riders ADD COLUMN do_ride TINYINT NOT NULL DEFAULT 0");
        if (!in_array('ride_ok', $rh, true))     $pdo->exec("ALTER TABLE riders ADD COLUMN ride_ok TINYINT NOT NULL DEFAULT 0");
        if (!in_array('last_lat', $rh, true))    $pdo->exec("ALTER TABLE riders ADD COLUMN last_lat DECIMAL(10,7) DEFAULT NULL");
        if (!in_array('last_lng', $rh, true))    $pdo->exec("ALTER TABLE riders ADD COLUMN last_lng DECIMAL(10,7) DEFAULT NULL");
        if (!in_array('last_location_at', $rh, true)) $pdo->exec("ALTER TABLE riders ADD COLUMN last_location_at DATETIME DEFAULT NULL");
        // Kaam ka ilaka (owner, 29 Oct 2026): order sirf ilake ke andar wale, sabse paas wale ko
        if (!in_array('work_area', $rh, true)) $pdo->exec("ALTER TABLE riders ADD COLUMN work_area VARCHAR(20) NOT NULL DEFAULT ''");
        if (!in_array('work_km', $rh, true))   $pdo->exec("ALTER TABLE riders ADD COLUMN work_km SMALLINT NOT NULL DEFAULT 0");
    } catch (Throwable $e) { error_log('Sarathi jobs upgrade: ' . $e->getMessage()); }
}

/** Keep one current point, only while this rider has active work. */
function rider_location_update(PDO $pdo, $rid, $lat, $lng) {
    sr_jobs_upgrade($pdo);
    if (!is_numeric($lat) || !is_numeric($lng)) return [false, 'लोकेशन सही नहीं मिली।'];
    $lat = (float)$lat; $lng = (float)$lng;
    if (!is_finite($lat) || !is_finite($lng) || $lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
        return [false, 'लोकेशन सही नहीं मिली।'];
    }
    $st = $pdo->prepare("SELECT 1 FROM jobs WHERE rider_id=? AND status IN ('assigned','picked') LIMIT 1");
    $st->execute([(int)$rid]);
    if (!$st->fetchColumn()) { rider_location_clear($pdo, $rid); return [false, 'अभी कोई चालू काम नहीं है।']; }
    $st = $pdo->prepare('SELECT active FROM riders WHERE id=?');
    $st->execute([(int)$rid]);
    if (!(int)$st->fetchColumn()) { rider_location_clear($pdo, $rid); return [false, 'यह सारथी खाता अभी चालू नहीं है।']; }
    $pdo->prepare("UPDATE riders SET last_lat=?, last_lng=?, last_location_at=UTC_TIMESTAMP() WHERE id=? AND active=1")
        ->execute([number_format($lat, 7, '.', ''), number_format($lng, 7, '.', ''), (int)$rid]);
    return [true, ['lat' => $lat, 'lng' => $lng, 'at' => time()]];
}

function rider_location_clear(PDO $pdo, $rid) {
    sr_jobs_upgrade($pdo);
    $pdo->prepare("UPDATE riders SET last_lat=NULL, last_lng=NULL, last_location_at=NULL WHERE id=?")
        ->execute([(int)$rid]);
}

function rider_location_clear_if_idle(PDO $pdo, $rid) {
    $st = $pdo->prepare("SELECT 1 FROM jobs WHERE rider_id=? AND status IN ('assigned','picked') LIMIT 1");
    $st->execute([(int)$rid]);
    if (!$st->fetchColumn()) rider_location_clear($pdo, $rid);
}

/** Gaadi ke prakaar (rider ka vtype) */
function sr_vtypes() {
    return ['cycle' => t('Bicycle', 'साइकिल'), 'bike' => t('Motorcycle', 'बाइक'), 'ev' => t('Electric scooter', 'बिजली स्कूटी'),
            'auto' => t('Auto', 'ऑटो'), 'erick' => t('E-rickshaw', 'ई-रिक्शा'), 'car' => t('Car / Bolero', 'कार / बोलेरो'), 'loader' => t('Loader / Magic', 'लोडर / मैजिक'), 'bus' => t('Bus', 'बस'), 'care' => t('Care Saathi', 'केयर साथी'), 'none' => t('No vehicle', 'गाड़ी नहीं')];
}
/** Arzi ke likhe hue gaadi naam se vtype */
function sr_vtype_guess($label) {
    $l = mb_strtolower((string)$label);
    foreach (['motorcycle' => 'bike', 'motorbike' => 'bike', 'ई-रिक्शा' => 'erick', 'e-rick' => 'erick', 'ऑटो' => 'auto', 'auto' => 'auto', 'बिजली' => 'ev', 'electric' => 'ev', 'साइकिल' => 'cycle', 'bicycle' => 'cycle', 'cycle' => 'cycle', 'बाइक' => 'bike', 'bike' => 'bike', 'scoot' => 'bike', 'motor' => 'bike', 'नहीं' => 'none', 'no vehicle' => 'none'] as $k => $v) {
        if (mb_strpos($l, mb_strtolower($k)) !== false) return $v;
    }
    return null;
}
/** Sawari ki gaadi maangi gayi ho to kaun-kaun si rider gaadi chalegi */
function sr_ride_fits($want) {
    return ['bike' => ['bike', 'ev'], 'auto' => ['auto'], 'erick' => ['erick', 'auto'], 'car' => ['car'], 'loader' => ['loader'], 'bus' => ['bus']][$want] ?? ['bike', 'ev', 'auto', 'erick'];
}

/** Abhi kahan jaana hai — uthana baaki ho to pickup, warna drop. GPS ho to seedha wahi. */
function maps_next($j) {
    $picked = ($j['status'] ?? '') === 'picked';
    $geo = $picked ? ($j['drop_geo'] ?? '') : ($j['pick_geo'] ?? '');
    // Phone par seedha awaaz wala navigation (dir_action=navigate); bike ke liye do-pahiya raasta
    $mode = in_array($j['vehicle'] ?? '', ['bike', 'ev', 'cycle'], true) || (($j['kind'] ?? '') === 'delivery') ? 'two-wheeler' : 'driving';
    if (preg_match('/^-?\d{1,2}\.\d+,-?\d{1,3}\.\d+$/', (string)$geo)) return 'https://www.google.com/maps/dir/?api=1&destination=' . rawurlencode($geo) . '&travelmode=' . $mode . '&dir_action=navigate';
    if (!$picked && !empty($j['pick_address'])) return 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($j['pick_address'] . ', Uttar Pradesh');
    return maps_link($j);
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
