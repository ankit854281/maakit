<?php
// ERP → Fee Report → Demand Bill / Demand Bill - II (owner, 20 Nov 2026: "demand bill purane software jaisa — sibling ya single").
// Old-ERP style form (class, section, student, single / sibling, upto month) → the approved 12 Nov bill design (demandbill.js)
// for one child, one family or a whole class at once → print all / WhatsApp / download. Data: demandbill_api.php (read-only).
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; connect-src 'self'; frame-ancestors 'self'; base-uri 'none'; object-src 'none'");
$v = (string)@filemtime(__DIR__ . '/demandbills.js') . (string)@filemtime(__DIR__ . '/demandbill.js');
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow">
<title>Sibling Demand Bill · SN Mishra SIS</title>
<style>
body{margin:0;font-family:Arial,Helvetica,sans-serif;background:#f4f6f9;color:#1d2733;font-size:14px}
.wrap{max-width:1100px;margin:0 auto;padding:14px}
.top{display:flex;justify-content:space-between;align-items:center;margin-bottom:10px}.top h1{font-size:22px;margin:0;color:#17324D;font-weight:600}
.top a{color:#17324D;font-size:13px}
.pan{background:#fff;border:1px solid #dfe4ea;border-radius:6px;padding:14px 16px;margin-bottom:12px}
.grid{display:grid;grid-template-columns:110px 1fr 110px 1fr;gap:10px 12px;align-items:center}
.grid label{font-size:13px;color:#333}
select,input[type=text]{width:100%;box-sizing:border-box;font:inherit;padding:7px 8px;border:1px solid #c7ced6;border-radius:4px;background:#fff}
.rad{display:flex;gap:16px;flex-wrap:wrap}.rad label{display:flex;gap:6px;align-items:center;cursor:pointer}
.act{display:flex;gap:10px;align-items:center;margin-top:14px;flex-wrap:wrap}
.btn{font:inherit;font-size:13.5px;border:0;border-radius:4px;padding:9px 18px;cursor:pointer;background:#17324D;color:#fff;text-decoration:none;display:inline-block}
.btn.y{background:#E0A100;color:#1b1b1b}.btn.g{background:#1E9E5A}.btn.l{background:#e9edf2;color:#17324D}
.note{font-size:12px;color:#667;margin:8px 0 0}
.sr{position:relative}.sug{position:absolute;left:0;right:0;top:100%;z-index:5;background:#fff;border:1px solid #c7ced6;border-top:0;max-height:260px;overflow:auto}
.sug button{display:block;width:100%;text-align:left;border:0;border-bottom:1px solid #eef1f4;background:#fff;padding:8px;font:inherit;cursor:pointer}.sug button:hover{background:#f2f6fb}.sug small{color:#667}
.sumb{display:flex;gap:14px;flex-wrap:wrap;align-items:center}.sumb b{font-size:16px;color:#17324D}
.bills{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:12px}
.bill{background:#fff;border:1px solid #dfe4ea;border-radius:6px;padding:10px}.bill img{width:100%;display:block;border:1px solid #e3e7ee;cursor:zoom-in}
.bill h4{margin:0 0 6px;font-size:14px;display:flex;justify-content:space-between;gap:8px}.bill h4 em{font-style:normal;color:#B30000}
.bill .bb{display:flex;gap:6px;margin-top:8px}.bill .bb .btn{flex:1;padding:7px 6px;font-size:12.5px;text-align:center}
.msg{padding:24px;text-align:center;color:#556}
.zoom{position:fixed;inset:0;background:rgba(10,20,35,.75);z-index:50;overflow:auto;padding:20px;display:flex;justify-content:center;align-items:flex-start}.zoom img{max-width:820px;width:100%}
@media(max-width:700px){.grid{grid-template-columns:1fr}}
</style></head><body><div class="wrap">
<div class="top"><h1>Sibling Demand Bill</h1><a href="index.php" target="_top">← ERP</a></div>
<div class="pan" id="form"><div class="msg">Opening…</div></div>
<div id="out"></div>
</div>
<script src="demandbill.js?v=<?= htmlspecialchars($v) ?>"></script>
<script src="demandbills.js?v=<?= htmlspecialchars($v) ?>"></script>
</body></html>
