<?php
// Chairman's app — the Fee Control Room as an installable phone app (Add to Home screen).
// Data from feedash_api.php; its own login (Chairman / Principal PIN), Home / Month / Dues / More, charts by Chart.js (vendor/).
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; connect-src 'self'; manifest-src 'self'; worker-src 'self'; frame-ancestors 'self'; base-uri 'none'; object-src 'none'");
$v = (string)@filemtime(__DIR__ . '/chairman.js') . (string)@filemtime(__DIR__ . '/chairman.css');
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><meta name="robots" content="noindex,nofollow">
<title>SNMSIS Chairman · SN Mishra SIS</title><meta name="theme-color" content="#4338CA"><link rel="manifest" href="chairman-manifest.json">
<meta name="apple-mobile-web-app-capable" content="yes"><meta name="apple-mobile-web-app-title" content="SNMSIS"><link rel="apple-touch-icon" href="img/chairman-192.png"><link rel="icon" href="img/chairman-192.png">
<link rel="stylesheet" href="chairman.css?v=<?= htmlspecialchars($v) ?>"><link rel="stylesheet" href="followup.css?v=<?php echo (string)filemtime(__DIR__.'/followup.js'); ?>"></head><body><div id="phone"><div id="app"></div></div><div id="toast" role="status" aria-live="polite"></div>
<script src="language.js?v=<?php echo (string)filemtime(__DIR__.'/followup.js'); ?>"></script><script src="followup.js?v=<?php echo (string)filemtime(__DIR__.'/followup.js'); ?>"></script><script src="vendor/chart.umd.min.js"></script><script src="demandbill.js?v=<?= (string)filemtime(__DIR__ . '/demandbill.js') ?>"></script><script src="chairman.js?v=<?= htmlspecialchars($v) ?>"></script><script src="followup-app.js?v=<?php echo (string)filemtime(__DIR__.'/followup-app.js'); ?>"></script></body></html>
