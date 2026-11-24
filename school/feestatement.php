<?php
// Full fee statement of one child — every session from 2024-25 to the current one, month by month, and the current session up to
// March (owner, 24 Nov 2026: "Astha Tiwari class 10 ka fee pura detail 2024 session se aaj tak, Chairman Sir ko dena hai — ye session
// pura March tak"). Opened from the Chairman app profile ("📄 Full fee statement") and the ERP; ?sid=… opens a child directly,
// without it a search box. Data: feecollect_api.php?a=statement (read only). A4 print / Save as PDF.
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; connect-src 'self'; frame-ancestors 'self'; base-uri 'none'; object-src 'none'");
$v = (string)@filemtime(__DIR__ . '/feestatement.js');
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow">
<title>Fee Statement · SN Mishra SIS</title>
<style>
@page{size:A4 portrait;margin:9mm}*{box-sizing:border-box;-webkit-print-color-adjust:exact;print-color-adjust:exact}
body{margin:0;font-family:Arial,Helvetica,sans-serif;background:#eef1f5;color:#14243a;font-size:12.5px}
.bar{max-width:820px;margin:0 auto;padding:10px;display:flex;gap:8px;flex-wrap:wrap;align-items:center}.bar input{flex:1;min-width:200px;padding:10px;border:1px solid #c9d2dc;border-radius:10px;font-size:14px}
.bar button{border:0;border-radius:99px;padding:10px 16px;font-weight:700;font-size:14px;cursor:pointer;background:#17324D;color:#fff}.bar button.y{background:#F2C14E;color:#1d1d1d}
#list button{display:block;width:100%;text-align:left;border:0;border-bottom:1px solid #e6eaf0;background:#fff;padding:9px 12px;font-size:14px;cursor:pointer}#list small{color:#667}
.pg{max-width:820px;margin:0 auto 12px;background:#fff;padding:14px 16px;box-shadow:0 2px 10px rgba(0,0,0,.08)}
.hd{text-align:center;border-bottom:3px solid #17324D;padding-bottom:6px}.hd h1{margin:0;font-size:19px;color:#17324D;letter-spacing:.4px}.hd p{margin:2px 0 0;color:#455;font-size:12px}.hd h2{margin:6px 0 0;font-size:15px;color:#B8860B}
.who{display:grid;grid-template-columns:1fr 1fr;gap:2px 16px;margin:8px 0;font-size:12.5px}.who b{color:#17324D}
table{width:100%;border-collapse:collapse;margin:4px 0 8px}th{background:#17324D;color:#fff;padding:5px 6px;font-size:11.5px;text-align:left}td{padding:4px 6px;border-bottom:1px solid #e3e8ef;vertical-align:top}
td.n,th.n{text-align:right;white-space:nowrap}tr.tot td{font-weight:700;background:#F5F8FB;border-top:2px solid #17324D}tr.up td{color:#6b7a8c;background:#FBFBFD}tr.sub td{background:#FFF8E6}
h3{margin:12px 0 2px;font-size:14px;color:#17324D;border-left:4px solid #E0A100;padding-left:6px}.note{color:#556;font-size:11.5px;margin:2px 0}
.big{display:grid;grid-template-columns:repeat(4,1fr);gap:6px;margin:8px 0}.big div{border:1px solid #dfe5ec;border-radius:8px;padding:6px 8px}.big span{display:block;font-size:11px;color:#556}.big b{font-size:15px}.red{color:#B30000}.grn{color:#1D6B2F}
.sign{display:flex;justify-content:space-between;margin-top:26px;font-size:12px}.sign span{border-top:1px solid #333;padding-top:3px;min-width:150px;text-align:center}
.msg{max-width:820px;margin:20px auto;text-align:center;color:#455}
@media print{body{background:#fff}.bar,#list{display:none!important}.pg{box-shadow:none;margin:0;padding:0;max-width:none}h3{break-after:avoid}table{break-inside:auto}tr{break-inside:avoid}}
</style></head><body>
<div class="bar"><input id="q" placeholder="Search child — name, class, father, mobile, adm. no." autocomplete="off"><button class="y" id="pr" style="display:none">🖨 Print / Save PDF</button><button id="sh" style="display:none">📤 Share</button></div>
<div id="list" style="max-width:820px;margin:0 auto"></div>
<div id="out"><div class="msg">Search a child above, or open this page from the child's profile.</div></div>
<script src="feestatement.js?v=<?= htmlspecialchars($v) ?>"></script>
</body></html>
