<?php
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

