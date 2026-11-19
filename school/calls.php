<?php
// Parent calls — PTM / event / attendance calls with feedback (calls_api.php, calls.js). Owner, 19 Nov 2026.
// Opens by itself (ERP → Parent Calls, link) and inside the Teacher App (Connect → Calls, ?embed=1).
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; connect-src 'self'; frame-ancestors 'self'; base-uri 'none'; object-src 'none'");
$v = (string)@filemtime(__DIR__ . '/calls.js') . (string)@filemtime(__DIR__ . '/calls.css');
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow">
<title>Parent Calls · SN Mishra SIS</title><link rel="stylesheet" href="calls.css?v=<?= htmlspecialchars($v) ?>"></head><body>
<div class="top"><div><b>📞 Parent Calls</b><small>PTM · events · attendance — call, mark what the parent said</small></div><a id="pcBack" href="index.php" target="_top">← ERP</a></div>
<div class="pg" id="app"><div class="card">Opening…</div></div>
<div id="sheet" class="sheet" hidden><div class="sh-bg" data-close></div><div class="sh-box" role="dialog" aria-modal="true"><button class="sh-x" data-close aria-label="Close">✕</button><div id="shBody"></div></div></div>
<div id="toast" role="status" aria-live="polite"></div>
<script src="calls.js?v=<?= htmlspecialchars($v) ?>"></script></body></html>
