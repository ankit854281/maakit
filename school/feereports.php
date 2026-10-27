<?php
// Fee Reports — the old ERP's fee reports in its own layout (see feereports_api.php, feereports.js).
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; connect-src 'self'; frame-ancestors 'self'; base-uri 'none'; object-src 'none'");
$v = (string)@filemtime(__DIR__ . '/feereports.js') . (string)@filemtime(__DIR__ . '/stumaster.css');
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Fee Reports · SN Mishra SIS</title><link rel="stylesheet" href="stumaster.css?v=<?= htmlspecialchars($v) ?>"><style>.sm table.lst tr.tot td{font-weight:bold;color:#000;background:#f3f3f3}.sm .cols{display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:4px 10px;margin:14px 0 6px;font-weight:bold}.sm .cols input{margin-right:4px}.sm .sub{margin-bottom:0}</style></head><body><main id="frRoot"><div class="sm">Opening Fee Reports…</div></main><div id="smToast" role="status" aria-live="polite"></div><script src="feereports.js?v=<?= htmlspecialchars($v) ?>"></script></body></html>
