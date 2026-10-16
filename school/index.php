<?php
// ============================================================
// School ERP — maakit.in/school
// SN Mishra Singapore International School, Bhadohi
//
// Ye sirf panna kholta hai. Saara kaam school/app.js karta hai,
// aur data school/api.php ke zariye database (sch_ tables) me
// jaata hai. CSS pehle se bana hua hai (school/app.css) — server
// par koi build nahi chahiye.
// ============================================================
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-cache');
$v = static fn(string $f): string => (string)(@filemtime(__DIR__ . '/' . $f) ?: '1');
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<meta name="theme-color" content="#312e81">
<title>School ERP – SN Mishra SIS</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="app.css?v=<?= $v('app.css') ?>">
</head>
<body class="bg-slate-50 text-slate-900 min-h-screen flex flex-col">
<div id="toasts" class="fixed top-3 right-3 z-50 flex flex-col gap-2 pointer-events-none max-w-xs"></div>
<div id="root" class="flex-1 flex flex-col"><div class="flex-1 flex items-center justify-center p-6 text-slate-500">Loading School ERP…</div></div>
<footer class="noprint text-center text-xs text-slate-500 py-4 border-t border-slate-200 bg-white">
  SN Mishra Singapore International School, Bhadohi &bull; Academic ERP &bull; Powered by <a href="https://maakit.in" class="font-bold text-indigo-700">Maakit</a>
</footer>
<noscript><p style="padding:16px">Please turn on JavaScript to use the School ERP.</p></noscript>
<script src="app.js?v=<?= $v('app.js') ?>"></script>
</body>
</html>
