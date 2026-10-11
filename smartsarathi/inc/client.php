<?php
// ============================================================
//  सारथी — ग्राहक कंपनी की चाबी और उसका काम भेजना
//
//  Yahi wo ek taar hai jo Maakit ko Sarathi se jodta hai.
//  Maakit Sarathi ka database nahi dekhta, Sarathi Maakit ka
//  nahi. Maakit sirf itna karta hai:
//
//      "ye saaman yahan pahuncha do"  +  apni chaabi
//
//  Aur Sarathi jawab me kaam ka number aur do code de deta hai.
//  Kal koi aur dukaan bhi yahi karegi — usko bhi apni chaabi
//  milegi. Maakit is list me pehla naam hai, akela nahi.
// ============================================================

// Kaam aate hi usko kisi sarathi ko dena hota hai, aur wo kaam
// kaam.php me hai. Dono jagah se load hone par bhi ek hi baar
// aata hai.
require_once __DIR__ . '/kaam.php';

// ------------------------------------------------------------
//  चाबी बनाना और पहचानना
// ------------------------------------------------------------
/**
 * Nayi chaabi. Ye POORI chaabi sirf EK BAAR dikhti hai —
 * database me uska hash jaata hai, chaabi nahi. Password ki
 * tarah: kho gayi to nayi banegi, purani wapas nahi milegi.
 */
function key_make(PDO $pdo, $cid, $note = null) {
    $prefix = strtolower(bin2hex(random_bytes(4)));      // 8 akshar — pehchanne ke liye
    $secret = rtrim(strtr(base64_encode(random_bytes(30)), '+/', '-_'), '=');
    $key    = 'sk_' . $prefix . '_' . $secret;
    $pdo->prepare("INSERT INTO client_keys (client_id, prefix, key_hash, note) VALUES (?,?,?,?)")
        ->execute([(int)$cid, $prefix, hash('sha256', $key), $note ?: null]);
    return $key;
}

function key_revoke(PDO $pdo, $id) {
    $pdo->prepare("UPDATE client_keys SET revoked=1 WHERE id=?")->execute([(int)$id]);
}

/**
 * Chaabi se client pehchaniye.
 *
 * prefix se seedha wahi ek line uthate hain, phir hash milate
 * hain hash_equals se — taaki jawab dene me lagne wale samay se
 * koi chaabi na taad le.
 */
function key_client(PDO $pdo, $key) {
    if (!is_string($key) || !preg_match('/^sk_([0-9a-f]{8})_[A-Za-z0-9_-]{20,}$/D', $key, $m)) return null;
    $st = $pdo->prepare("SELECT k.id key_id, k.key_hash, c.*
                           FROM client_keys k JOIN clients c ON c.id = k.client_id
                          WHERE k.prefix=? AND k.revoked=0 AND c.active=1");
    $st->execute([$m[1]]);
    $row = $st->fetch();
    if (!$row || !hash_equals($row['key_hash'], hash('sha256', $key))) return null;
    $pdo->prepare("UPDATE client_keys SET last_used=NOW() WHERE id=?")->execute([$row['key_id']]);
    return $row;
}

/** request ke header ya body se chaabi nikaliye */
function key_from_request() {
    $h = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['HTTP_X_SARATHI_KEY'] ?? '';
    if ($h === '' && function_exists('apache_request_headers')) {
        foreach (apache_request_headers() as $k => $v) {
            if (strcasecmp($k, 'Authorization') === 0 || strcasecmp($k, 'X-Sarathi-Key') === 0) { $h = $v; break; }
        }
    }
    $h = trim((string)$h);
    if (stripos($h, 'Bearer ') === 0) $h = trim(substr($h, 7));
    return $h;
}

// ------------------------------------------------------------
//  काम भेजना
// ------------------------------------------------------------
/**
 * Client ne naya kaam bheja.
 *
 * Lautata hai [ok, data-ya-galti].
 *
 * Do baar bhejne se do kaam NAHI bante: (client_id, client_ref)
 * par ek hi ki hadd hai. Net atak jaye aur Maakit dobara bheje,
 * to wahi purana kaam wapas mil jata hai. Ye zaroori hai —
 * warna ek hi saaman do baar bhejne ka hukm ban jata.
 */
function job_create(PDO $pdo, array $client, array $in) {
    $ref = trim((string)($in['ref'] ?? ''));
    if ($ref === '' || mb_strlen($ref) > 40) return [false, 'ref चाहिए (40 अक्षर तक)'];

    // pehle se bheja hua to wahi lauta dijiye
    $st = $pdo->prepare("SELECT * FROM jobs WHERE client_id=? AND client_ref=?");
    $st->execute([(int)$client['id'], $ref]);
    if ($old = $st->fetch()) return [true, job_public($old) + ['repeat' => true]];

    $name = trim((string)($in['drop_name'] ?? ''));
    $mob  = preg_replace('/\D/', '', (string)($in['drop_mobile'] ?? ''));
    $addr = trim((string)($in['drop_address'] ?? ''));
    if (mb_strlen($name) < 2)                     return [false, 'drop_name चाहिए'];
    if (!preg_match('/^[6-9]\d{9}$/D', $mob))     return [false, 'drop_mobile सही नहीं (10 अंक)'];
    if (mb_strlen($addr) < 3)                     return [false, 'drop_address चाहिए'];

    $fee = isset($in['fee']) ? (int)$in['fee'] : (int)$client['fee'];
    if ($fee < 0 || $fee > 100000) return [false, 'fee सही नहीं'];
    $cod = isset($in['cod_amount']) && $in['cod_amount'] !== '' ? (int)$in['cod_amount'] : null;
    if ($cod !== null && ($cod < 0 || $cod > 1000000)) return [false, 'cod_amount सही नहीं'];

    $pdo->beginTransaction();
    try {
        $st = $pdo->prepare(
            "INSERT INTO jobs
             (client_id, client_ref, job_no, pick_name, pick_mobile, pick_address,
              drop_name, drop_mobile, drop_address, drop_village, items, note,
              pick_otp, drop_code, fee, cod_amount, status)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'new')");
        $st->execute([
            (int)$client['id'], $ref, job_no($pdo),
            mb_substr(trim((string)($in['pick_name']    ?? '')), 0, 120) ?: null,
            preg_replace('/\D/', '', (string)($in['pick_mobile'] ?? '')) ?: null,
            mb_substr(trim((string)($in['pick_address'] ?? '')), 0, 250) ?: null,
            mb_substr($name, 0, 80), $mob, mb_substr($addr, 0, 250),
            mb_substr(trim((string)($in['drop_village'] ?? '')), 0, 80) ?: null,
            mb_substr(trim((string)($in['items'] ?? '')), 0, 2000) ?: null,
            mb_substr(trim((string)($in['note']  ?? '')), 0, 2000) ?: null,
            otp4(), otp4(), $fee, $cod,
        ]);
        $id = (int)$pdo->lastInsertId();
        $pdo->commit();
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        // do request ek saath aa gayin — doosri ko wahi purana kaam do
        if ($e->getCode() === '23000') {
            $st = $pdo->prepare("SELECT * FROM jobs WHERE client_id=? AND client_ref=?");
            $st->execute([(int)$client['id'], $ref]);
            if ($old = $st->fetch()) return [true, job_public($old) + ['repeat' => true]];
        }
        throw $e;
    }

    // Kaam aate hi kisi hazir sarathi ko de dijiye. Na de paye to
    // kuch nahi bigadta -- kaam 'new' par rukta hai aur maalik ke
    // panne par sabse upar dikhta hai. Isliye ye kabhi job banne
    // ko fail nahi karta.
    try { job_auto_give($pdo, $id); }
    catch (Throwable $e) { error_log('Sarathi auto-give: ' . $e->getMessage()); }

    $st = $pdo->prepare("SELECT * FROM jobs WHERE id=?");
    $st->execute([$id]);
    return [true, job_public($st->fetch())];
}

/**
 * Client ko kaam ki jo jaankari di jaati hai.
 *
 * Dhyaan: pick_otp aur drop_code bhi jaate hain — jaan-boojh
 * kar. Client ko ye apne dukaandar aur apne graahak ko batane
 * hote hain, warna rider ke paas poochhne ki jagah hi nahi
 * rahegi. Rider ke panne par ye kabhi nahi dikhte.
 *
 * Rider ka naam aur number bhi jaata hai — client ko pata hona
 * chahiye kaun aa raha hai.
 */
function job_public(array $j) {
    return [
        'job_no'       => $j['job_no'],
        'ref'          => $j['client_ref'],
        'status'       => $j['status'],
        'pick_otp'     => $j['pick_otp'],
        'drop_code'    => $j['drop_code'],
        'fee'          => (int)$j['fee'],
        'rider'        => null,
        'picked_at'    => $j['picked_at'],
        'delivered_at' => $j['delivered_at'],
    ];
}

function job_status(PDO $pdo, array $client, $ref) {
    $st = $pdo->prepare("SELECT j.*, r.name rider_name, r.mobile rider_mobile
                           FROM jobs j LEFT JOIN riders r ON r.id = j.rider_id
                          WHERE j.client_id=? AND j.client_ref=?");
    $st->execute([(int)$client['id'], (string)$ref]);
    $j = $st->fetch();
    if (!$j) return [false, 'ये काम नहीं मिला'];
    $out = job_public($j);
    if ($j['rider_name']) $out['rider'] = ['name' => $j['rider_name'], 'mobile' => $j['rider_mobile']];
    if ($j['pod_photo'])  $out['photo'] = '/uploads/' . $j['pod_photo'];
    return [true, $out];
}

/**
 * Client apna kaam radd kar sakta hai — par sirf tab tak jab
 * tak rider ne uthaya na ho. Uthane ke baad saaman rider ke
 * paas hai; use radd karna phone par baat karke hi hoga.
 */
function job_cancel(PDO $pdo, array $client, $ref) {
    $up = $pdo->prepare("UPDATE jobs SET status='cancelled'
                          WHERE client_id=? AND client_ref=? AND status IN ('new','assigned')");
    $up->execute([(int)$client['id'], (string)$ref]);
    if ($up->rowCount() !== 1) {
        return [false, 'ये काम अब यहाँ से रद्द नहीं हो सकता — फ़ोन कीजिए'];
    }
    return [true, ['status' => 'cancelled']];
}

// ------------------------------------------------------------
//  जवाब भेजने का तरीका
// ------------------------------------------------------------
function api_out($code, array $body) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
function api_fail($code, $msg) { api_out($code, ['ok' => false, 'error' => $msg]); }
