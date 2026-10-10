<?php
// ============================================================
//  सारथी — दरवाज़ा
//
//  Delivery wala bhai apna mobile aur code daal kar andar
//  aata hai. Password nahi — gaon me yaad nahi rehta.
// ============================================================
require_once __DIR__ . '/inc/fn.php';
require_once __DIR__ . '/inc/kaam.php';

if (rider_me($pdo)) { redirect('/kaam.php'); }

$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_ok()) {
    $mob  = preg_replace('/\D/', '', (string)post('mobile'));
    $code = (string)post('code');

    // Hadd: ek number par 6 koshish, ek jagah se 30 — 15 minute me.
    // Ginti database me rehti hai, session me nahi; warna cookie
    // mitate hi ginti shunya ho jaati.
    if (try_blocked($pdo, $mob)) {
        $err = try_error();
    } elseif ($r = rider_login($pdo, $mob, $code)) {
        try_ok($pdo, $mob);
        rider_start_session($r);
        redirect('/kaam.php');
    } else {
        $err = 'नंबर या कोड सही नहीं है। दफ़्तर से अपना कोड पूछ लीजिए।';
    }
}
?><!doctype html>
<html lang="hi">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="theme-color" content="#7A1F1F">
<meta name="robots" content="noindex,nofollow">
<title>सारथी</title>
<link rel="stylesheet" href="/assets/app.css?v=<?= (int)@filemtime(__DIR__ . '/assets/app.css') ?>">
</head>
<body class="sr-login">
<div class="sr-wrap">

  <div class="sr-brand">
    <div class="sr-logo">सारथी</div>
    <p>डिलीवरी साथी</p>
  </div>

  <?php if ($err): ?><div class="sr-err"><?= h($err) ?></div><?php endif; ?>

  <form method="post" class="sr-card" autocomplete="off">
    <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
    <label class="sr-lab" for="m">अपना मोबाइल नंबर</label>
    <input class="sr-in" id="m" name="mobile" type="tel" inputmode="numeric"
           value="<?= h(post('mobile')) ?>" required autofocus>
    <label class="sr-lab" for="c">अपना कोड</label>
    <input class="sr-in" id="c" name="code" type="password" required>
    <button class="sr-btn sr-btn-go" type="submit">काम पर चलिए</button>
  </form>

  <div class="sr-help">
    <b>कोड नहीं मिला?</b>
    <p>दफ़्तर से अपना कोड पूछ लीजिए। एक बार अंदर आने के बाद यही फ़ोन
       आपको सीधा अंदर ले जाएगा।</p>
    <a class="sr-call" href="tel:<?= h(SR_PHONE) ?>">फ़ोन कीजिए — <?= h(SR_PHONE_SHOW) ?></a>
  </div>

</div>
<script>
var m = document.getElementById('m');
if (m) m.addEventListener('input', function(){ this.value = this.value.replace(/\D/g,'').slice(0,10); });
</script>
</body>
</html>
