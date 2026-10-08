<?php
// ============================================================
//  Galat password/code ki koshishon ki hadd
//
//  Ginti DATABASE me rakhi jati hai, session me nahi — isliye
//  cookie mita dene se hadd phir se shuru nahi hoti.
//
//  Log me na username rakha jata hai na IP — sirf unka hash.
//
//  ---- do sudhaar (jaanch me pakde gaye) ----
//
//  1. Pehle pehle GINTI hoti thi, phir koshish darj hoti thi.
//     Ek saath 100 request bhej do to sabhi ginti ke waqt khali
//     dikhte the aur hadd bekaar ho jaati thi. Ab PEHLE darj
//     hota hai, PHIR ginti — isliye ek saath bheji gayi sari
//     koshishein bhi gini jaati hain.
//
//  2. Pehle naam ko PHP se chhota karke hash banta tha, par
//     database naam milate waqt matra/accent bhi barabar maanta
//     hai. Yani "admin" aur "ädmin" ek hi khata kholte the par
//     unki alag-alag ginti banti thi — hadd bekaar. Ab jab khata
//     mil jata hai to uska ASLI naam ginti ki chaabi banta hai.
// ============================================================

function auth_attempt_keys($identity) {
    return [
        hash('sha256', mb_strtolower(trim((string)$identity))),
        hash('sha256', (string)($_SERVER['REMOTE_ADDR'] ?? '')),
    ];
}

/** Ek number/naam par 15 minute me 6, aur ek IP par 30 */
function auth_attempt_blocked(PDO $pdo, $scope, $identity) {
    [$who, $ip] = auth_attempt_keys($identity);
    $since = gmdate('Y-m-d H:i:s', time() - 900);

    $q = $pdo->prepare('SELECT COUNT(*) FROM auth_failures WHERE scope=? AND identity_hash=? AND created_at>?');
    $q->execute([$scope, $who, $since]);
    if ((int)$q->fetchColumn() >= 6) return true;

    if (empty($_SERVER['REMOTE_ADDR'])) return false;
    $q = $pdo->prepare('SELECT COUNT(*) FROM auth_failures WHERE scope=? AND ip_hash=? AND created_at>?');
    $q->execute([$scope, $ip, $since]);
    return (int)$q->fetchColumn() >= 30;
}

/**
 * Koshish se PEHLE chalaiye. Ye koshish ko turant darj karta hai
 * aur phir batata hai ki hadd par hain ya nahi. Isi kram se ek
 * saath bheji gayi request hadd nahi tod paatin.
 *
 * Password sahi nikle to auth_attempt_ok() chala dijiye — wo is
 * koshish ko hata deta hai, taaki sahi login ginti me na rahe.
 */
function auth_attempt_try(PDO $pdo, $scope, $identity) {
    auth_attempt_failed($pdo, $scope, $identity);
    return auth_attempt_blocked($pdo, $scope, $identity);
}

function auth_attempt_failed(PDO $pdo, $scope, $identity) {
    [$who, $ip] = auth_attempt_keys($identity);
    $pdo->prepare('INSERT INTO auth_failures(scope,identity_hash,ip_hash,created_at) VALUES (?,?,?,?)')
        ->execute([$scope, $who, $ip, gmdate('Y-m-d H:i:s')]);
    // log ko bandha rakhiye; koi password yahan nahi likha jata
    $pdo->prepare('DELETE FROM auth_failures WHERE created_at<?')
        ->execute([gmdate('Y-m-d H:i:s', time() - 86400)]);
}

/** Sahi login — is number/naam ki pichhli koshishein saaf kar dijiye */
function auth_attempt_ok(PDO $pdo, $scope, $identity) {
    [$who, ] = auth_attempt_keys($identity);
    $pdo->prepare('DELETE FROM auth_failures WHERE scope=? AND identity_hash=?')->execute([$scope, $who]);
}

function auth_attempt_error() {
    return t('Too many wrong attempts. Try again in 15 minutes, or call us.',
             'बहुत बार ग़लत पासवर्ड। 15 मिनट बाद कोशिश कीजिए, या हमें कॉल कीजिए।');
}
