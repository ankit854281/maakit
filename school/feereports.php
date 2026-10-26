<?php
// Old ERP Fee Reports — the old Fee Report menu pages, same columns (see feereports_lib.php / feereports.js).
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; connect-src 'self'; frame-ancestors 'self'; base-uri 'none'; object-src 'none'");
$v = (string)@filemtime(__DIR__ . '/feereports.js');
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Fee Report · SN Mishra SIS</title><link rel="stylesheet" href="feereports.css?v=<?= htmlspecialchars($v) ?>"></head><body><main id="rpRoot"><div class="rp">Opening Fee Report…</div></main><div id="rpToast" role="status" aria-live="polite"></div><script src="feereports.js?v=<?= htmlspecialchars($v) ?>"></script></body></html>
