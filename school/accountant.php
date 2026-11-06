<?php
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; connect-src 'self'; frame-src 'self'; frame-ancestors 'self'; base-uri 'none'; object-src 'none'");
$v = (string)filemtime(__DIR__ . '/accountant.js') . (string)filemtime(__DIR__ . '/accountant.css');
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><meta name="robots" content="noindex,nofollow"><meta name="theme-color" content="#134d48"><title>SNMSIS Accountant</title><link rel="manifest" href="accountant-manifest.json"><link rel="icon" href="img/snmsis-logo.png"><link rel="stylesheet" href="accountant.css?v=<?= htmlspecialchars($v) ?>"></head><body><div id="app"><p class="loading">Opening your school office…</p></div><div id="toast" role="status" aria-live="polite"></div><script src="accountant.js?v=<?= htmlspecialchars($v) ?>"></script></body></html>
