<?php
// ============================================================
//  सारथी — बुनियाद
//
//  Database, session, suraksha. Ye file Maakit se kuch bhi
//  nahi lleti — Sarathi apne paun par khada hai.
// ============================================================

// ---------- PHP me jo chahiye ----------
//
// mbstring ke bina Hindi ke akshar theek se ginte nahi, aur
// panna safed ho jata hai — bina ye bataye ki hua kya. Isliye
// pehle hi saaf-saaf keh dete hain ki kya kam hai.
//
// Zyadatar cPanel par ye pehle se laga hota hai. Na ho to
// cPanel → "Select PHP Version" → Extensions → mbstring par
// tick laga dijiye.
foreach (['mbstring' => 'mb_substr', 'pdo_mysql' => 'PDO'] as $naam => $jaanch) {
    if (!function_exists($jaanch) && !class_exists($jaanch)) {
        http_response_code(503);
        header('Content-Type: text/html; charset=utf-8');
        exit('<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
           . '<div style="font-family:system-ui,sans-serif;max-width:470px;margin:60px auto;padding:0 18px;line-height:1.7">'
           . '<h2 style="color:#7A1F1F">PHP में एक हिस्सा कम है</h2>'
           . '<p>इस server के PHP में <b>' . htmlspecialchars($naam, ENT_QUOTES, 'UTF-8')
           . '</b> नहीं लगा है, इसलिए सारथी नहीं चल सकता।</p>'
           . '<p style="color:#6B5D55;font-size:14px">cPanel → <b>Select PHP Version</b> → '
           . '<b>Extensions</b> → उसमें <b>' . htmlspecialchars($naam, ENT_QUOTES, 'UTF-8')
           . '</b> पर tick लगाइए और Save कीजिए।</p></div>');
    }
}

// ---------- config ----------
$sr_cfg = __DIR__ . '/config.php';
if (!is_file($sr_cfg)) {
    http_response_code(503);
    header('Content-Type: text/html; charset=utf-8');
    exit('<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
       . '<div style="font-family:system-ui,sans-serif;max-width:460px;margin:60px auto;padding:0 18px;line-height:1.7;color:#F3EDE2;background:#1C1A17">'
       . '<h2 style="color:#E0A526">सारथी — तैयारी बाकी है</h2>'
       . '<p>यहाँ <b>inc/config.php</b> अभी नहीं बनी है।</p>'
       . '<p style="color:#8C8276;font-size:14px">cPanel → File Manager → '
       . 'smartsarathi/inc/ → <b>config.sample.php</b> की नकल बनाइए, नाम रखिए '
       . '<b>config.php</b>, और उसमें database की जानकारी भर दीजिए।</p></div>');
}
require_once $sr_cfg;

// ---------- database ----------
try {
    $pdo = new PDO(
        'mysql:host=' . SR_DB_HOST . ';dbname=' . SR_DB_NAME . ';charset=utf8mb4',
        SR_DB_USER, SR_DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (Throwable $e) {
    // Asli galti kabhi graahak ko mat dikhaiye — usme server ka
    // naam aur user ka naam hota hai.
    error_log('Sarathi DB: ' . $e->getMessage());
    http_response_code(503);
    header('Retry-After: 120');
    header('Content-Type: text/html; charset=utf-8');
    exit('<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
       . '<div style="font-family:system-ui,sans-serif;max-width:440px;margin:60px auto;padding:0 18px;line-height:1.7">'
       . '<h2 style="color:#7A1F1F">अभी नहीं खुल पा रहा</h2>'
       . '<p>थोड़ी देर में दोबारा कोशिश कीजिए। जल्दी हो तो फ़ोन कर दीजिए — '
       . '<a href="tel:' . SR_PHONE . '" style="color:#7A1F1F">' . SR_PHONE_SHOW . '</a></p></div>');
}

// ---------- panne ka pata ----------
/**
 * Sarathi do raaston se khulta hai:
 *
 *   smartsarathi.in/admin/           -> apne ghar me, aage kuch nahi
 *   maakit.in/smartsarathi/admin/    -> Maakit ke andar ek folder me
 *
 * Pehle sab link "/admin/" jaise likhe the. Wo doosre raaste par
 * Maakit ke admin par le jaate the, aur /assets/app.css Maakit ki
 * CSS uthata tha -- isliye panna bina rang-roop ke khulta tha aur
 * khane dikhte hi nahi the.
 *
 * Ab har andar ka link u() se hokar jata hai. Jis raaste se panna
 * khula hai, u() usi ke hisaab se aage ka hissa jod deta hai.
 */
$sr_dir = '/' . basename(dirname(__DIR__));              // "/smartsarathi"
$sr_uri = (string)($_SERVER['REQUEST_URI'] ?? '/');
$sr_uri = (($p = strpos($sr_uri, '?')) !== false) ? substr($sr_uri, 0, $p) : $sr_uri;
define('SR_BASE', ($sr_uri === $sr_dir || strpos($sr_uri, $sr_dir . '/') === 0) ? $sr_dir : '');

/** andar ka pata — hamesha isse hokar */
function u($path = '/') {
    return SR_BASE . $path;
}

// ---------- session ----------
if (session_status() !== PHP_SESSION_ACTIVE) {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
          || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    ini_set('session.use_strict_mode', '1');
    session_name('SRSESS');
    session_set_cookie_params([
        'lifetime' => 0, 'path' => '/',
        'httponly' => true, 'samesite' => 'Lax', 'secure' => $https,
    ]);
    session_start();
}

// ---------- chhoti madad ----------
/** har cheez jo panne par chhapti hai, isse hokar jaati hai */
function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function post($k, $d = '') { return $_POST[$k] ?? $d; }
function get($k, $d = '')  { return $_GET[$k]  ?? $d; }

/**
 * Andar ka pata ho to u() apne aap lag jata hai, taaki har
 * redirect('/kaam.php') likhne wale ko yaad na rakhna pade.
 * "//" se shuru hone wala pata bahar ka hai — usse chhedte nahi.
 */
function redirect($to) {
    if (isset($to[0]) && $to[0] === '/' && substr($to, 0, 2) !== '//') $to = u($to);
    header('Location: ' . $to);
    exit;
}

function flash($msg = null) {
    if ($msg !== null) { $_SESSION['sr_flash'] = $msg; return null; }
    $m = $_SESSION['sr_flash'] ?? null; unset($_SESSION['sr_flash']); return $m;
}

function csrf() {
    if (empty($_SESSION['sr_csrf'])) $_SESSION['sr_csrf'] = bin2hex(random_bytes(16));
    return $_SESSION['sr_csrf'];
}

/**
 * Form sach me hamare hi panne se aaya hai?
 *
 * Dhyaan: hash_equals('', '') TRUE deta hai. Isliye pehle jaanch
 * lete hain ki dono taraf kuch hai bhi — warna bina session wala
 * koi bhi POST paas ho jata.
 */
function csrf_ok() {
    $mera = $_SESSION['sr_csrf'] ?? '';
    $diya = $_POST['csrf'] ?? '';
    if (!is_string($diya) || $mera === '' || $diya === '') return false;
    return hash_equals($mera, $diya);
}

function setting(PDO $pdo, $k, $d = null) {
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        foreach ($pdo->query("SELECT k, v FROM settings")->fetchAll() as $r) $cache[$r['k']] = $r['v'];
    }
    return $cache[$k] ?? $d;
}

// ---------- login ki koshishon par hadd ----------
/**
 * Lautata hai TRUE agar hadd paar ho gayi (yaani rok dijiye).
 *
 * Pehle daalte hain, phir ginte hain — ulta karne par ek saath
 * aayi das koshishein sab ko shunya dikhti hain aur sab paas ho
 * jaati hain.
 */
function try_blocked(PDO $pdo, $who) {
    $hash = hash('sha256', (string)$who);
    $ip   = substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
    $pdo->prepare("INSERT INTO login_tries (who, ip) VALUES (?,?)")->execute([$hash, $ip]);

    $st = $pdo->prepare("SELECT COUNT(*) FROM login_tries
                          WHERE who=? AND ok=0 AND tried_at > (NOW() - INTERVAL 15 MINUTE)");
    $st->execute([$hash]);
    if ((int)$st->fetchColumn() > 6) return true;

    if ($ip !== '') {
        $st = $pdo->prepare("SELECT COUNT(*) FROM login_tries
                              WHERE ip=? AND ok=0 AND tried_at > (NOW() - INTERVAL 15 MINUTE)");
        $st->execute([$ip]);
        if ((int)$st->fetchColumn() > 30) return true;
    }
    return false;
}

function try_ok(PDO $pdo, $who) {
    $pdo->prepare("UPDATE login_tries SET ok=1
                    WHERE who=? AND tried_at > (NOW() - INTERVAL 15 MINUTE)")
        ->execute([hash('sha256', (string)$who)]);
    // purani ginti kabhi-kabhi saaf kar dijiye, table badhti rahe to kya fayda
    if (random_int(1, 50) === 1) {
        try { $pdo->query("DELETE FROM login_tries WHERE tried_at < (NOW() - INTERVAL 2 DAY)"); }
        catch (Throwable $e) {}
    }
}

function try_error() {
    return 'बहुत बार कोशिश हो गई। 15 मिनट बाद दोबारा कीजिए, या Maakit को फ़ोन कीजिए।';
}

// ---------- फ़ोटो ----------
/**
 * Delivery ki photo — chhoti karke rakhte hain.
 *
 * Gaon me net dheema hai aur rider ka data mehnga. 12 MB ki
 * photo seedhi rakhne ka koi matlab nahi — 900px kaafi hai
 * ye dekhne ke liye ki saaman kiske haath me gaya.
 */
function save_photo($field, $prefix = 'pod', $max = 900) {
    if (empty($_FILES[$field]) || $_FILES[$field]['error'] !== UPLOAD_ERR_OK) return null;
    $f = $_FILES[$field];
    if ($f['size'] > 12 * 1024 * 1024) return null;

    $info = @getimagesize($f['tmp_name']);
    if (!$info) return null;                                   // tasveer hai hi nahi
    [$w, $h] = $info;
    $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$info['mime']] ?? null;
    if (!$ext) return null;

    $dir = __DIR__ . '/../uploads';
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    $name = $prefix . '-' . date('ymd') . '-' . bin2hex(random_bytes(5)) . '.jpg';

    // GD ho to chhoti kar dijiye, na ho to jaisi hai waisi rakh lijiye
    if (function_exists('imagecreatetruecolor') && max($w, $h) > $max) {
        $src = match ($info['mime']) {
            'image/jpeg' => @imagecreatefromjpeg($f['tmp_name']),
            'image/png'  => @imagecreatefrompng($f['tmp_name']),
            'image/webp' => @imagecreatefromwebp($f['tmp_name']),
            default      => null,
        };
        if ($src) {
            $r  = $max / max($w, $h);
            $nw = max(1, (int)round($w * $r));
            $nh = max(1, (int)round($h * $r));
            $dst = imagecreatetruecolor($nw, $nh);
            imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
            imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
            $ok = imagejpeg($dst, $dir . '/' . $name, 78);
            imagedestroy($src); imagedestroy($dst);
            if ($ok) { @chmod($dir . '/' . $name, 0644); return $name; }
        }
    }

    $name = $prefix . '-' . date('ymd') . '-' . bin2hex(random_bytes(5)) . '.' . $ext;
    if (!@move_uploaded_file($f['tmp_name'], $dir . '/' . $name)) return null;
    @chmod($dir . '/' . $name, 0644);
    return $name;
}

function drop_photo($name) {
    if (!$name || !preg_match('/^[a-z]{2,6}-\d{6}-[a-f0-9]{8,12}\.(jpg|png|webp)$/', $name)) return;
    @unlink(__DIR__ . '/../uploads/' . $name);
}
