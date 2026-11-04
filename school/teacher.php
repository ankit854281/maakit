<?php
// Teacher App (3 Nov 2026, owner: "chairman sir ka ho gaya, ab aise teacher ka bhi") — an installable phone app for teachers.
// Same records as the web ERP: api.php (diary = homework / class work, studAtt, lplan, syl, leave), progress_api.php
// (Term marks → result), scan_api.php (photo → chapters / plan / notes), teacher_api.php (register, class holidays, notes).
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
// cdn.jsdelivr.net only for Tesseract.js 5.1.1 (the phone reads a photo when the school AI is switched off) — same as the web ERP.
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; connect-src 'self' https://cdn.jsdelivr.net https://tessdata.projectnaptha.com; worker-src 'self' blob: https://cdn.jsdelivr.net; manifest-src 'self'; frame-ancestors 'self'; base-uri 'none'; object-src 'none'");
$v = (string)@filemtime(__DIR__ . '/teacher.js') . (string)@filemtime(__DIR__ . '/teacher.css');
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><meta name="robots" content="noindex,nofollow">
<title>SNMSIS Teacher · SN Mishra SIS</title><meta name="theme-color" content="#0F766E"><link rel="manifest" href="teacher-manifest.json">
<meta name="apple-mobile-web-app-capable" content="yes"><meta name="apple-mobile-web-app-title" content="SNMSIS Teacher"><link rel="apple-touch-icon" href="img/teacher-192.png"><link rel="icon" href="img/teacher-192.png">
<link rel="stylesheet" href="teacher.css?v=<?= htmlspecialchars($v) ?>"></head><body><div id="phone"><div id="app"><div class="load"><i></i>Opening…</div></div></div><div id="toast" role="status" aria-live="polite"></div>
<script src="login-otp.js?v=<?= (string)filemtime(__DIR__ . '/login-otp.js') ?>"></script><script src="teacher-assist.js?v=<?= (string)filemtime(__DIR__ . '/teacher-assist.js') ?>"></script><script src="teacher.js?v=<?= htmlspecialchars($v) ?>"></script></body></html>
