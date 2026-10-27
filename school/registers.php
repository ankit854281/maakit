<?php
// School Office — the old ERP's remaining menus as registers (see registers_lib.php).
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; connect-src 'self'; frame-ancestors 'self'; base-uri 'none'; object-src 'none'");
$v = (string)@filemtime(__DIR__ . '/registers.js');
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>School Office · SN Mishra SIS</title><link rel="stylesheet" href="registers.css?v=<?= htmlspecialchars($v) ?>"></head><body><main id="regRoot"><section class="rg-panel">Opening School Office…</section></main><div id="regToast" role="status" aria-live="polite"></div><dialog id="regDialog"><div id="regDialogBody"></div></dialog><div id="regPrint"></div><script src="registers.js?v=<?= htmlspecialchars($v) ?>"></script></body></html>
