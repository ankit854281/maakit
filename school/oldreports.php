<?php
// Old ERP Reports — every old ERP list/report page, read-only (see oldreports_api.php / oldreports.js).
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; connect-src 'self'; frame-ancestors 'self'; base-uri 'none'; object-src 'none'");
$v = (string)@filemtime(__DIR__ . '/oldreports.js');
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Old ERP Reports · SN Mishra SIS</title><link rel="stylesheet" href="oldreports.css?v=<?= htmlspecialchars($v) ?>"><link rel="stylesheet" href="classic.css?v=<?= htmlspecialchars($v) ?>"></head><body><main id="orRoot"><div class="or">Opening Old ERP Reports…</div></main><div id="orToast" role="status" aria-live="polite"></div><script src="oldreports.js?v=<?= htmlspecialchars($v) ?>"></script></body></html>
