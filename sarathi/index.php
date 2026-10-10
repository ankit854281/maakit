<?php
// ============================================================
//  सारथी — अपना दरवाज़ा
//
//  Ye Maakit ki website nahi hai. Yahan dukaan, saaman, offer
//  kuch nahi dikhta. Delivery wala bhai sirf apna kaam karne
//  aata hai, isliye poora panna uske kaam ka hai.
//
//  Code Maakit ke andar hi hai (wahi hosting, wahi database),
//  par ye panna apna HTML khud banata hai — Maakit ka tabbar,
//  ticker, locationbar yahan nahi aate.
//
//  Login: mobile (ya username) + code/password.
//  Dono tarah ke log chalte hain — purane delivery staff aur
//  naye partner rider.
// ============================================================
require_once __DIR__ . '/../inc/fn.php';
require_once __DIR__ . '/../inc/sarathi.php';

// pehle se andar hain to seedha kaam par
if (sarathi_me()) { redirect('/sarathi/kaam.php'); }

$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_ok()) {
    $who  = trim((string)post('who'));
    $pass = (string)post('pass');

    // Wahi hadd jo baaki login par hai — ek pehchaan par 6 koshish,
    // ek jagah (IP) se 30. Ginti database me rehti hai, session me
    // nahi, warna cookie mitakar ginti shunya ho jaati.
    $u = sarathi_login($pdo, $who, $pass);
    $kunji = $u ? $u['username'] : $who;

    if (auth_attempt_try($pdo, 'sarathi', $kunji)) {
        $err = auth_attempt_error();
    } elseif ($u) {
        auth_attempt_ok($pdo, 'sarathi', $kunji);
        if (function_exists('Maakit\Api\revoke_legacy_sessions')) {
            try { Maakit\Api\revoke_legacy_sessions($pdo); } catch (Throwable $e) {}
        }
        session_regenerate_id(true);
        $_SESSION['user'] = ['id' => $u['id'], 'name' => $u['name'], 'role' => $u['role']];
        redirect('/sarathi/kaam.php');
    } else {
        $err = 'नंबर या कोड सही नहीं है। Maakit से पूछ लीजिए — ' . MAAKIT_NUMBER_SHOW;
    }
}
?><!doctype html>
<html lang="hi">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="theme-color" content="#7A1F1F">
<meta name="robots" content="noindex,nofollow">
<title>सारथी — Maakit</title>
<link rel="stylesheet" href="/assets/sarathi.css?v=<?= (int)@filemtime(__DIR__.'/../assets/sarathi.css') ?>">
</head>
<body class="sr-login">

<div class="sr-wrap">

  <div class="sr-brand">
    <div class="sr-logo">सारथी</div>
    <p>Maakit का डिलीवरी साथी</p>
  </div>

  <?php if ($err): ?><div class="sr-err"><?= h($err) ?></div><?php endif; ?>

  <form method="post" class="sr-card" autocomplete="off">
    <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">

    <label class="sr-lab" for="who">अपना मोबाइल नंबर</label>
    <input class="sr-in" id="who" name="who" type="tel" inputmode="numeric"
           value="<?= h(post('who')) ?>" required autofocus>

    <label class="sr-lab" for="pass">अपना कोड</label>
    <input class="sr-in" id="pass" name="pass" type="password" required>

    <button class="sr-btn sr-btn-go" type="submit">काम पर चलिए</button>
  </form>

  <div class="sr-help">
    <b>कोड नहीं मिला?</b>
    <p>Maakit से अपना कोड पूछ लीजिए। एक बार अंदर आने के बाद यही फ़ोन
       आपको सीधा अंदर ले जाएगा।</p>
    <a class="sr-call" href="tel:<?= h(MAAKIT_PHONE) ?>">फ़ोन कीजिए — <?= h(MAAKIT_NUMBER_SHOW) ?></a>
  </div>

  <p class="sr-foot"><a href="/">Maakit की वेबसाइट</a></p>

</div>

<script>
// number wale khane me sirf ank — galti se akshar na chale jayein
var w = document.getElementById('who');
if (w) w.addEventListener('input', function(){ this.value = this.value.replace(/\D/g,'').slice(0,10); });
</script>
</body>
</html>
