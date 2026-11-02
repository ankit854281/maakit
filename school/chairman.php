<?php
// Chairman's app — the Fee Control Room as an installable phone app (Add to Home screen).
// Same page and data as feedash.php; it shows its own login (Chairman / Principal PIN) and opens on Month Collection.
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; connect-src 'self'; manifest-src 'self'; worker-src 'self'; frame-ancestors 'self'; base-uri 'none'; object-src 'none'");
$v = (string)@filemtime(__DIR__ . '/feedash.js') . (string)@filemtime(__DIR__ . '/feedash.css');
?><!doctype html><html lang="hi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><meta name="robots" content="noindex,nofollow">
<title>Chairman Fee Report · SN Mishra SIS</title><meta name="theme-color" content="#2D353C"><link rel="manifest" href="chairman-manifest.json">
<meta name="apple-mobile-web-app-capable" content="yes"><meta name="apple-mobile-web-app-title" content="Fee Report"><link rel="apple-touch-icon" href="img/chairman-192.png"><link rel="icon" href="img/chairman-192.png">
<link rel="stylesheet" href="feedash.css?v=<?= htmlspecialchars($v) ?>"></head><body class="fd-app"><main id="fdRoot"><div class="fd">Opening…</div></main><div id="fdToast" role="status" aria-live="polite"></div>
<script>window.FD_APP = true;</script><script src="feedash.js?v=<?= htmlspecialchars($v) ?>"></script></body></html>
