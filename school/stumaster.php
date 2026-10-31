<?php
// Student Master — the old ERP's Add Student / Edit-Update Student / Student Search / Admission Form (see stumaster_api.php, stumaster.js).
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; connect-src 'self'; frame-ancestors 'self'; base-uri 'none'; object-src 'none'");
$v = (string)@filemtime(__DIR__ . '/stumaster.js') . (string)@filemtime(__DIR__ . '/stumaster.css');
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Student Master · SN Mishra SIS</title><link rel="stylesheet" href="stumaster.css?v=<?= htmlspecialchars($v) ?>"></head><body><main id="smRoot"><div class="sm">Opening Student Master…</div></main><div id="smToast" role="status" aria-live="polite"></div><script src="stumaster.js?v=<?= htmlspecialchars($v) ?>"></script></body></html>
