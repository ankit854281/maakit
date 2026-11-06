<?php
// Public page: children / parents find and share their competition certificates (owner, 6 Nov 2026). Data: cert_api.php.
header('Content-Type: text/html; charset=utf-8'); header('Cache-Control: no-store');
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data: blob:; font-src 'self'; connect-src 'self' data:; frame-ancestors 'self'; base-uri 'none'");
$v = fn($f) => (string)@filemtime(__DIR__ . '/' . $f);
?><!doctype html><html lang="hi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title>Mera Certificate · SN Mishra Singapore International School</title><meta name="robots" content="noindex,nofollow"><meta name="theme-color" content="#4C1D95">
<meta property="og:title" content="🏆 Apna certificate dekhiye — SN Mishra Singapore International School"><meta property="og:description" content="Diwali Competition ke certificate — mobile number daaliye, certificate download / share kijiye.">
<link rel="icon" href="img/snmsis-logo.png"><link rel="stylesheet" href="cert.css?v=<?= $v('cert.css') ?>"></head>
<body>
<div id="intro" class="intro" aria-hidden="true">
  <div class="sparks"></div>
  <div class="ring"><img src="img/snmsis-logo.png" alt=""></div>
  <div class="diyas"><i></i><i class="big"></i><i></i></div>
  <h1>SN Mishra Singapore<br>International School</h1>
  <p>🎉 Competition Certificates</p>
</div>
<main id="app" class="app">
  <header class="top"><img src="img/snmsis-logo.png" alt=""><div><small>SN Mishra Singapore International School</small><b>🏆 Mera Certificate</b></div></header>
  <section class="card find">
    <h2>🔍 Apna certificate dhoondhiye</h2>
    <form id="f"><input id="q" type="search" autocomplete="off" placeholder="Bachche ka naam ya mobile number" aria-label="Naam ya mobile number"></form>
    <div id="cls" class="chips"></div>
    <p id="msg" class="msg"></p>
  </section>
  <section id="gal" class="card gal" hidden><h2>📸 Competition ki photos</h2><div id="galg" class="galg"></div></section>
  <section id="res"></section>
  <footer>Number nahi mila? Class teacher ya school office se sampark karein.<br>Powered by Maakit</footer>
</main>
<script src="cert-draw.js?v=<?= $v('cert-draw.js') ?>"></script><script src="cert.js?v=<?= $v('cert.js') ?>"></script>
</body></html>
