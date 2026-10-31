<?php
// Fee Receipt — old-ERP style fee collection (see feecollect_lib.php / feecollect_api.php / feecollect.js).
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; connect-src 'self'; frame-ancestors 'self'; base-uri 'none'; object-src 'none'");
$v = (string)@filemtime(__DIR__ . '/feecollect.js');
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Fee Receipt · SN Mishra SIS</title><link rel="stylesheet" href="feecollect.css?v=<?= htmlspecialchars($v) ?>"><link rel="stylesheet" href="classic.css?v=<?= htmlspecialchars($v) ?>"></head><body><main id="ofcRoot"><div class="fr">Opening Fee Receipt…</div></main><div id="ofcToast" role="status" aria-live="polite"></div><div id="ofcPrint"></div><script src="feecollect.js?v=<?= htmlspecialchars($v) ?>"></script></body></html>
