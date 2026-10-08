<?php
require_once __DIR__.'/auth-attempts.php';
// ============================================================
// Maakit — customer ka apna khata (account)
// Login zaroori NAHI hai. Bina login bhi order ho jata hai.
// Login karne par pata, purane order aur booking sab ek jagah.
// ============================================================

/** abhi kaun logged in hai (ya null) */
function cust() {
    return $_SESSION['cust'] ?? null;
}

/** session me daal do */
function cust_set($c) {
    if (session_status() === PHP_SESSION_ACTIVE) session_regenerate_id(true);
    $_SESSION['cust'] = [
        'id' => (int)$c['id'], 'name' => $c['name'], 'mobile' => $c['mobile'],
        'village' => $c['village'], 'landmark' => $c['landmark'],
    ];
}
function cust_logout() {
    unset($_SESSION['cust']);
    if (session_status() === PHP_SESSION_ACTIVE) session_regenerate_id(true);
}

function cust_password_ok($pass) {
    return is_string($pass) && strlen($pass) >= 8 && strlen($pass) <= 72;
}
function cust_password_error() {
    return t('Use 8–72 English letters, numbers or symbols. Avoid your mobile or vehicle number.', '8–72 अंग्रेज़ी अक्षर, अंक या चिन्ह रखें। मोबाइल या गाड़ी का नंबर पासवर्ड न रखें।');
}

/** naya khata — [ok, msg] */
function cust_register(PDO $pdo, $name, $mobile, $pass, $village, $landmark) {
    $mobile = preg_replace('/\D/', '', $mobile);
    if (mb_strlen(trim($name)) < 2)  return [false, t('Please write your full name.', 'अपना पूरा नाम लिखिए।')];
    if (strlen($mobile) !== 10)      return [false, t('Mobile number must be 10 digits.', 'मोबाइल नंबर 10 अंकों का लिखिए।')];
    if (!cust_password_ok($pass))    return [false, cust_password_error()];

    $st = $pdo->prepare("SELECT id FROM customers WHERE mobile=?");
    $st->execute([$mobile]);
    if ($st->fetch()) return [false, t('This number already has an account. Please sign in below.', 'यह नंबर पहले से जुड़ा है। नीचे लॉगिन कीजिए।')];

    $ins = $pdo->prepare("INSERT INTO customers (name, mobile, password, village, landmark) VALUES (?,?,?,?,?)");
    $ins->execute([trim($name), $mobile, password_hash($pass, PASSWORD_DEFAULT), $village, $landmark]);
    $id = (int)$pdo->lastInsertId();

    // Do not claim guest history from an unverified phone number. Only orders
    // placed while signed in belong to this account. Guest tracking remains available.

    cust_set(['id'=>$id,'name'=>trim($name),'mobile'=>$mobile,'village'=>$village,'landmark'=>$landmark]);
    return [true, ''];
}

/** login — [ok, msg]. 6 galat koshish = 15 minute rok */
function cust_login(PDO $pdo, $mobile, $pass) {
    $mobile = preg_replace('/\D/', '', $mobile);
    // Koshish pehle darj hoti hai, ginti baad me — warna ek saath bheji
    // gayi sau koshishein hadd ko lang jaati hain.
    if (auth_attempt_try($pdo, 'customer', $mobile)) return [false, auth_attempt_error()];

    $st = $pdo->prepare("SELECT * FROM customers WHERE mobile=? AND active=1");
    $st->execute([$mobile]);
    $c = $st->fetch();
    if (!$c || !password_verify($pass, $c['password'])) {
        return [false, t('Number or password is wrong.', 'नंबर या पासवर्ड ग़लत है।')];
    }
    auth_attempt_ok($pdo, 'customer', $mobile);
    unset($_SESSION['cl']);
    $pdo->prepare("UPDATE customers SET last_login=NOW() WHERE id=?")->execute([$c['id']]);
    cust_set($c);
    return [true, ''];
}

/** khate ke order */
function cust_orders(PDO $pdo, $id, $limit = 20) {
    $st = $pdo->prepare("SELECT * FROM orders WHERE customer_id=? ORDER BY id DESC LIMIT " . (int)$limit);
    $st->execute([(int)$id]);
    return $st->fetchAll();
}
function cust_bookings(PDO $pdo, $id, $limit = 20) {
    $st = $pdo->prepare("SELECT * FROM service_bookings WHERE customer_id=? ORDER BY id DESC LIMIT " . (int)$limit);
    $st->execute([(int)$id]);
    return $st->fetchAll();
}

/**
 * Customer ki bheji photo (list / parchi / dawa ki patti) sambhalna.
 * Sirf asli tasveer, 4 MB tak. Naam hum khud banate hain.
 */
function save_photo($field, $prefix = 'p') {
    if (empty($_FILES[$field]) || $_FILES[$field]['error'] !== UPLOAD_ERR_OK) return null;
    $f = $_FILES[$field];
    if ($f['size'] > 4 * 1024 * 1024) return null;
    $info = @getimagesize($f['tmp_name']);
    if (!$info) return null;                              // tasveer hai hi nahi
    $ext = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'][$info['mime']] ?? null;
    if (!$ext) return null;
    $name = $prefix . '-' . date('ymd') . '-' . bin2hex(random_bytes(5)) . '.' . $ext;
    $dir  = __DIR__ . '/../uploads';
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    if (!@move_uploaded_file($f['tmp_name'], $dir . '/' . $name)) return null;
    @chmod($dir . '/' . $name, 0644);
    return $name;
}

/**
 * Saaman ki photo — chhoti karke sambhalna.
 * Gaon me net dheema hai, isliye har photo ko 500px tak chhota
 * karke JPG me badal dete hain (karib 30-60 KB).
 * GD na ho to photo jaisi hai waisi rakh lete hain.
 */
function save_item_photo($field, $prefix = 'it', $max = 500) {
    if (empty($_FILES[$field]) || $_FILES[$field]['error'] !== UPLOAD_ERR_OK) return null;
    return save_photo_from($_FILES[$field]['tmp_name'], $_FILES[$field]['size'], $prefix, $max);
}

/* Ek photo (jo pehle se server par aa chuki hai) ko chaukor kaat kar,
   chhota karke uploads me rakh deta hai. Naam wapas karta hai.
   Bulk upload me ek hi baar me kai photo ke liye yahi chalta hai. */
function save_photo_from($tmp, $size, $prefix = 'it', $max = 500) {
    if (!$tmp || !is_file($tmp)) return null;
    $f = ['tmp_name' => $tmp, 'size' => $size];
    if ($f['size'] > 8 * 1024 * 1024) return null;
    $info = @getimagesize($f['tmp_name']);
    if (!$info) return null;
    list($w, $h) = $info;
    $mime = $info['mime'];
    $dir = __DIR__ . '/../uploads';
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    $base = $prefix . '-' . date('ymd') . '-' . bin2hex(random_bytes(4));

    if (function_exists('imagecreatetruecolor') && $w > 0 && $h > 0) {
        $src = null;
        if ($mime === 'image/jpeg' && function_exists('imagecreatefromjpeg')) $src = @imagecreatefromjpeg($f['tmp_name']);
        elseif ($mime === 'image/png' && function_exists('imagecreatefrompng')) $src = @imagecreatefrompng($f['tmp_name']);
        elseif ($mime === 'image/webp' && function_exists('imagecreatefromwebp')) $src = @imagecreatefromwebp($f['tmp_name']);
        if ($src) {
            // beech ka chaukor hissa lo, taaki sab photo ek naap ki dikhein
            $side = min($w, $h);
            $sx = (int)(($w - $side) / 2);
            $sy = (int)(($h - $side) / 2);
            $out = min($max, $side);
            $dst = imagecreatetruecolor($out, $out);
            $white = imagecolorallocate($dst, 255, 255, 255);
            imagefilledrectangle($dst, 0, 0, $out, $out, $white);
            imagecopyresampled($dst, $src, 0, 0, $sx, $sy, $out, $out, $side, $side);
            $name = $base . '.jpg';
            $ok = @imagejpeg($dst, $dir . '/' . $name, 82);
            imagedestroy($dst); imagedestroy($src);
            if ($ok) { @chmod($dir . '/' . $name, 0644); return $name; }
        }
    }

    // GD na chale to jaisi hai waisi rakh lo
    $ext = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'][$mime] ?? null;
    if (!$ext) return null;
    $name = $base . '.' . $ext;
    $moved = is_uploaded_file($f['tmp_name'])
        ? @move_uploaded_file($f['tmp_name'], $dir . '/' . $name)
        : @copy($f['tmp_name'], $dir . '/' . $name);
    if (!$moved) return null;
    @chmod($dir . '/' . $name, 0644);
    return $name;
}

/** purani photo hatao (sirf uploads folder se, sirf hamari banai file) */
function drop_photo($name) {
    if (!$name || !preg_match('/^[a-z]{2,6}-\d{6}-[a-f0-9]{8,12}\.(jpg|png|webp)$/', $name)) return;
    @unlink(__DIR__ . '/../uploads/' . $name);
}
