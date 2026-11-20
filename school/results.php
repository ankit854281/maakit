<?php
// Half-Yearly (Term-1) marks slips + rankers' certificates (owner, 20 Nov 2026: "result final me diya jayega, abhi bas ek slip
// dena hai marks ka; fail ko pass tak grace (khule me dikhe); rankers ka certificate 1st 2nd 3rd with image").
// Data: progress_api.php (marks of a class, student photos). Read only. Principal, Chairman, teachers.
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; connect-src 'self'; frame-ancestors 'self'; base-uri 'none'; object-src 'none'");
$v = (string)@filemtime(__DIR__ . '/results.js');
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow">
<title>Marks Slip & Rankers · SN Mishra SIS</title>
<style>
body{margin:0;font-family:Arial,Helvetica,sans-serif;background:#f4f6f9;color:#1d2733;font-size:14px}
.wrap{max-width:1000px;margin:0 auto;padding:14px}.np{}
.top{display:flex;justify-content:space-between;align-items:center;margin-bottom:10px}.top h1{font-size:21px;margin:0;color:#17324D}.top a{color:#17324D;font-size:13px}
.pan{background:#fff;border:1px solid #dfe4ea;border-radius:6px;padding:12px 14px;margin-bottom:12px}
.row{display:flex;gap:10px;align-items:center;flex-wrap:wrap}
select{font:inherit;padding:7px 8px;border:1px solid #c7ced6;border-radius:4px;background:#fff}
.btn{font:inherit;font-size:13.5px;border:0;border-radius:4px;padding:9px 16px;cursor:pointer;background:#17324D;color:#fff;text-decoration:none;display:inline-block}
.btn.y{background:#E0A100;color:#1b1b1b}.btn.g{background:#1E9E5A}.btn.l{background:#e9edf2;color:#17324D}
.tabs{display:flex;gap:6px}.tabs button{font:inherit;border:1px solid #c7ced6;background:#fff;border-radius:4px;padding:8px 14px;cursor:pointer}.tabs button.on{background:#17324D;color:#fff;border-color:#17324D}
.note{font-size:12px;color:#667;margin:6px 0 0}.msg{padding:20px;text-align:center;color:#556}
.slip{background:#fff;border:2px solid #17324D;padding:14px 16px;margin:0 0 12px;break-inside:avoid;page-break-inside:avoid}
.sh{text-align:center;border-bottom:2px solid #17324D;padding-bottom:6px;margin-bottom:8px}.sh b{display:block;font-size:19px;color:#17324D}.sh span{font-size:12px;color:#444}.sh i{display:inline-block;margin-top:5px;background:#17324D;color:#fff;font-style:normal;font-weight:bold;padding:3px 14px;border-radius:12px;font-size:12.5px}
.info{display:grid;grid-template-columns:1fr 1fr;gap:2px 16px;font-size:13px;margin-bottom:8px}.info b{font-weight:bold}
table.m{width:100%;border-collapse:collapse;font-size:12.5px}table.m th,table.m td{border:1px solid #9aa5b1;padding:4px 5px;text-align:center}table.m th{background:#EEF2F6}table.m td.s{text-align:left}
table.m tr.t td{font-weight:bold;background:#FFF8E6}.gr{color:#1E7A43;font-weight:bold}.red{color:#B30000}
.foot{display:flex;justify-content:space-between;align-items:flex-end;margin-top:10px;font-size:12px}.foot div{text-align:center;min-width:140px}.foot div i{display:block;border-top:1px solid #333;margin-top:28px;font-style:normal;padding-top:2px}
.sum{display:flex;gap:18px;flex-wrap:wrap;margin-top:8px;font-size:13px}.sum b{font-size:15px}
.gn{font-size:11px;color:#555;margin-top:6px}
.slipbar{display:flex;gap:6px;margin:-6px 0 14px}.slipbar .btn{padding:6px 10px;font-size:12.5px}
.certs{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:12px}.cert{background:#fff;border:1px solid #dfe4ea;border-radius:6px;padding:10px}.cert img{width:100%;display:block;border:1px solid #e3e7ee;cursor:zoom-in}.cert h4{margin:0 0 6px}
@media print{body{background:#fff}.np,.slipbar{display:none!important}.wrap{max-width:none;padding:0}.slip{margin:0 0 6mm;border-width:1.5px}@page{size:A4;margin:8mm}}
</style></head><body><div class="wrap">
<div class="top np"><h1>Half-Yearly · Marks Slip &amp; Rankers</h1><a href="index.php" target="_top">← ERP</a></div>
<div class="pan np" id="bar"><div class="msg">Opening…</div></div>
<div id="out"></div>
</div>
<script src="results.js?v=<?= htmlspecialchars($v) ?>"></script>
</body></html>
