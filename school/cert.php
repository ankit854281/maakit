<?php
// Public page: children / parents find and share their competition certificates (owner, 6 Nov 2026). Data: cert_api.php.
header('Content-Type: text/html; charset=utf-8'); header('Cache-Control: no-store');
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data: blob:; font-src 'self'; connect-src 'self' data:; frame-ancestors 'self'; base-uri 'none'");
$v = fn($f) => (string)@filemtime(__DIR__ . '/' . $f);
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title>Certificates · SN Mishra Singapore International School</title><meta name="robots" content="noindex,nofollow"><meta name="theme-color" content="#4C1D95">
<meta property="og:title" content="🏆 Your child's certificate is ready — SN Mishra Singapore International School"><meta property="og:description" content="Diwali activities (Rangoli and Diya Making). Search by your child's name to view, download and share the certificate.">
<meta property="og:type" content="website"><meta property="og:url" content="https://maakit.in/school/cert.php"><meta property="og:image" content="https://maakit.in/school/img/cert-og.jpg"><meta property="og:image:width" content="1200"><meta property="og:image:height" content="630"><meta name="twitter:card" content="summary_large_image">
<link rel="icon" href="img/snmsis-logo.png"><link rel="stylesheet" href="cert.css?v=<?= $v('cert.css') ?>"></head>
<body>
<div id="intro" class="ad" aria-hidden="true"><div class="ad-bars"><i></i><i></i><i></i></div><button class="ad-skip" type="button" id="adSkip">Skip</button>
  <section class="ad-s s1"><div class="sparks"></div><img class="ad-logo" src="img/snmsis-logo.png" alt=""><div class="lang" id="lang"></div><p class="ad-t">SN Mishra Singapore International School · Bhadohi</p></section>
  <section class="ad-s s2"><p class="ad-k">One school · every language</p><h1 class="ad-h xl">SNMSIS</h1><p class="ad-t">A name the whole world can read — and a school the whole town trusts.</p></section>
  <section class="ad-s s3"><div class="sparks"></div><div class="diyas"><i></i><i class="big"></i><i></i></div><p class="ad-k">Diwali Activities 2026</p><h1 class="ad-h">Every child's<br><span class="ad-gold">certificate is ready</span></h1><p class="ad-t">Rangoli · Diya Making — with photos, ready to share.</p><button class="ad-go" type="button" id="adGo">View certificates</button></section>
</div>
<main id="app" class="app">
  <header class="top"><img src="img/snmsis-logo.png" alt=""><div><small>SN Mishra Singapore International School</small><b>🏆 Certificates</b></div></header>
  <section class="card find">
    <h2>🔍 Find your child's certificate</h2>
    <form id="f"><input id="q" type="search" enterkeyhint="search" autocomplete="off" placeholder="Child's name or mobile number" aria-label="Child's name or mobile number"><button type="submit">🔍 Search</button></form>
    <div id="cls" class="chips"></div>
    <p id="msg" class="msg"></p>
  </section>
  <section id="res"></section>
  <section id="gal" class="card gal" hidden><h2>📸 Activity photos</h2><div id="galg" class="galg"></div></section>
  <footer>Can't find your child? Please contact the class teacher or the school office.<br>Powered by Maakit</footer>
</main>
<script src="cert-draw.js?v=<?= $v('cert-draw.js') ?>"></script><script src="cert.js?v=<?= $v('cert.js') ?>"></script>
</body></html>
