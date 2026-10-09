<?php
// ============================================================
//  लॉगआउट
//
//  Pehle ye sirf session_destroy() karta tha. Teen dikkatein thin:
//
//   1. Browser wahi purani session-id bhejta rehta tha aur PHP use
//      dobara apna leta tha. Gaon me ek hi phone ghar bhar me chalta
//      hai aur dukaan par ek hi computer — wahan "logout" ke baad
//      bhi wahi id chalti rehna theek nahi.
//   2. Dukaan ka 90 din wala login (mk_shop) zinda bacha rehta tha,
//      yani team se logout karne par bhi /shop.php khula rehta tha.
//   3. Ye GET par chal jata tha, to koi doosri website link dabwa kar
//      aapko logout kar sakti thi.
//
//  Ab: sirf POST, csrf ke saath, dono login band, cookie mitai gayi,
//  aur nayi session-id.
// ============================================================
require_once __DIR__ . '/inc/fn.php';
require_once __DIR__ . '/inc/salon.php';

function maakit_sab_band(PDO $pdo) {
    Maakit\Api\revoke_legacy_sessions($pdo);
    // dukaan ka 90 din wala login bhi band kijiye
    if (function_exists('shop_logout')) { try { shop_logout($pdo); } catch (Throwable $e) {} }

    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires'  => time() - 42000,
            'path'     => $p['path'] ?: '/',
            'domain'   => $p['domain'] ?? '',
            'secure'   => !empty($_SERVER['HTTPS']),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);   // purani id dobara kaam na kare
        session_destroy();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_ok()) {
    maakit_sab_band($pdo);
    redirect('/login.php');
}

// POST nahi aaya — poochh lijiye. Isse koi doosri website link dabwa
// kar aapko logout nahi kara sakti.
$no_tabbar = true;
$page_title = t('Log out — Maakit', 'लॉगआउट — Maakit');
include __DIR__ . '/inc/head.php';
?>
<section><div class="wrap" style="max-width:420px">
  <h2><?= t('Log out?', 'लॉगआउट करें?') ?></h2>
  <p class="lead"><?= t('You will have to log in again to open your panel.',
                        'अपना पैनल खोलने के लिए दोबारा लॉगिन करना पड़ेगा।') ?></p>
  <form method="post" class="box">
    <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
    <button class="btn btn-brand" style="width:100%;font-size:17px" type="submit"><?= t('Yes, log out', 'हाँ, लॉगआउट कीजिए') ?></button>
    <a class="btn btn-sm" style="background:#EFEAE0;width:100%;margin-top:10px;text-align:center" href="/"><?= t('No, go back', 'नहीं, वापस चलिए') ?></a>
  </form>
</div></section>
<?php include __DIR__ . '/inc/foot.php'; ?>

