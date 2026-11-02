<?php
// Fee Control Room — fees at a glance for the Chairman, and the fee-calling list for the office (feedash.js / feedash_api.php).
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; connect-src 'self'; frame-ancestors 'self'; base-uri 'none'; object-src 'none'");
$v = (string)@filemtime(__DIR__ . '/feedash.js') . (string)@filemtime(__DIR__ . '/feedash.css');
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Fee Control Room · SN Mishra SIS</title><link rel="stylesheet" href="feedash.css?v=<?= htmlspecialchars($v) ?>"></head><body><main id="fdRoot"><div class="fd">Opening Fee Control Room…</div></main><div id="fdToast" role="status" aria-live="polite"></div><script src="feedash.js?v=<?= htmlspecialchars($v) ?>"></script></body></html>
