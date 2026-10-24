<?php
// Progress Report — old ERP Examination-I cards and marks entry (see progress_api.php / progress.js).
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; connect-src 'self'; frame-ancestors 'self'; base-uri 'none'; object-src 'none'");
$v = (string)@filemtime(__DIR__ . '/progress.js');
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Progress Report · SN Mishra SIS</title><link rel="stylesheet" href="progress.css?v=<?= htmlspecialchars($v) ?>"><link rel="stylesheet" href="classic.css?v=<?= htmlspecialchars($v) ?>"></head><body><main id="prRoot"><div class="pr">Opening Progress Report…</div></main><div id="prToast" role="status" aria-live="polite"></div><script src="progress.js?v=<?= htmlspecialchars($v) ?>"></script></body></html>
