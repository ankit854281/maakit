<?php
// PTM Desk (owner, 21 Nov 2026) — parent walks in: entry, result talk, fee, feedback, complaint. Data: ptm_api.php.
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; connect-src 'self'; frame-ancestors 'self'; base-uri 'none'; object-src 'none'");
$v = (string)@filemtime(__DIR__ . '/ptm.js');
?><!doctype html><html lang="hi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><meta name="robots" content="noindex,nofollow"><meta name="theme-color" content="#14294A">
<title>PTM Desk · SN Mishra SIS</title>
<style>
@font-face{font-family:'Poppins';src:url('fonts/poppins-latin-400-normal.woff2') format('woff2');font-weight:400}
@font-face{font-family:'Poppins';src:url('fonts/poppins-latin-600-normal.woff2') format('woff2');font-weight:600}
@font-face{font-family:'Poppins';src:url('fonts/poppins-latin-700-normal.woff2') format('woff2');font-weight:700}
*{box-sizing:border-box}body{margin:0;font-family:Poppins,Arial,sans-serif;background:#F3F5F9;color:#1f2a37;font-size:14px}
.top{position:sticky;top:0;z-index:5;background:linear-gradient(120deg,#0E1F3D,#1F4A8A);color:#fff;padding:10px 12px 0;border-bottom:3px solid #D9B45A}
.top .r{display:flex;align-items:center;gap:8px}.top img{width:34px;height:34px;border-radius:50%;background:#fff}.top b{font-size:16px;flex:1}.top a{color:#F6E3A1;font-size:12px;text-decoration:none}
.top input[type=date]{font:inherit;font-size:13px;border:0;border-radius:6px;padding:4px 6px}
.tabs{display:flex;gap:4px;margin-top:8px}.tabs button{flex:1;font:inherit;font-weight:600;border:0;border-radius:8px 8px 0 0;padding:9px 4px;background:rgba(255,255,255,.12);color:#DDE6F0}.tabs button.on{background:#F3F5F9;color:#14294A}
.wrap{max-width:820px;margin:0 auto;padding:10px}
.chips{display:flex;gap:6px;overflow-x:auto;padding:2px 0 8px}.chips button{flex:0 0 auto;font:inherit;font-weight:600;border:1px solid #C9D3E0;background:#fff;border-radius:16px;padding:6px 12px;color:#14294A}.chips button.on{background:#14294A;color:#fff;border-color:#14294A}
.chips button small{font-weight:400;opacity:.8}
.search{width:100%;font:inherit;border:1px solid #C9D3E0;border-radius:10px;padding:9px 12px;margin-bottom:8px;background:#fff}
.kid{display:flex;align-items:center;gap:10px;background:#fff;border:1px solid #E1E6EE;border-radius:12px;padding:10px 12px;margin-bottom:6px;cursor:pointer}
.kid .n{flex:1;min-width:0}.kid .n b{display:block;color:#14294A}.kid .n small{color:#5a6b80}
.kid .st{display:flex;gap:4px;flex-wrap:wrap;justify-content:flex-end}.pill{font-size:11px;font-weight:700;border-radius:10px;padding:2px 8px;white-space:nowrap}
.p-ok{background:#E3F6EA;color:#1E7A43}.p-c{background:#FDE8E8;color:#B3261E}.p-f{background:#FFF4D6;color:#8C6A1E}.p-g{background:#EEF2F6;color:#5a6b80}
.kid.came{border-left:5px solid #1E9E5A}
.panel{background:#fff;border-radius:14px;border:1px solid #E1E6EE;padding:12px;margin-bottom:10px}
.ph{display:flex;align-items:flex-start;gap:10px}.ph h2{margin:0;font-size:18px;color:#14294A}.ph p{margin:2px 0;color:#4a5565;font-size:12.5px}
.stats{display:grid;grid-template-columns:repeat(3,1fr);gap:6px;margin:10px 0}.stats div{background:#F4F7FC;border-radius:10px;padding:8px;text-align:center}.stats b{display:block;font-size:17px;color:#14294A}.stats small{font-size:11px;color:#5a6b80}
.stats .bad b{color:#B3261E}.stats .good b{color:#1E7A43}
.subs{display:flex;flex-wrap:wrap;gap:4px;margin-bottom:8px}.subs span{font-size:11.5px;border-radius:8px;padding:2px 7px;background:#EEF2F6}.subs .w{background:#FDE8E8;color:#B3261E}.subs .s{background:#E3F6EA;color:#1E7A43}
.talk{background:#FFF8E6;border:1px solid #F0E2BC;border-left:4px solid #D9B45A;border-radius:10px;padding:8px 10px;margin-bottom:10px}.talk b{color:#7A1F2B;font-size:12.5px}.talk ul{margin:4px 0 0;padding-left:18px}.talk li{margin:3px 0;font-size:13px}
label.l{display:block;font-size:12px;font-weight:600;color:#14294A;margin:10px 0 4px}
input.i,textarea.i,select.i{width:100%;font:inherit;border:1px solid #C9D3E0;border-radius:9px;padding:9px 10px;background:#fff}textarea.i{min-height:62px;resize:vertical}
.row2{display:grid;grid-template-columns:1fr 1fr;gap:8px}
.tog{display:flex;gap:6px;flex-wrap:wrap}.tog button{font:inherit;font-weight:600;border:1px solid #C9D3E0;background:#fff;border-radius:10px;padding:8px 12px;color:#14294A}.tog button.on{background:#1E9E5A;border-color:#1E9E5A;color:#fff}
.mood{display:flex;gap:6px}.mood button{flex:1;font-size:26px;border:2px solid #E1E6EE;background:#fff;border-radius:12px;padding:6px 0;filter:grayscale(1);opacity:.6}.mood button.on{filter:none;opacity:1;border-color:#D9B45A;background:#FFF8E6}
.tagb button.on{background:#14294A;border-color:#14294A}
.cbox{border:1px solid #F3C2C2;background:#FFF6F6;border-radius:10px;padding:8px 10px;margin-top:10px}.cbox label.l{color:#B3261E;margin-top:0}
.btn{font:inherit;font-weight:700;border:0;border-radius:10px;padding:11px 16px;background:#14294A;color:#fff;cursor:pointer}.btn.g{background:#1E9E5A}.btn.l{background:#E9EDF2;color:#14294A}.btn.s{padding:6px 10px;font-size:12.5px}
.bar{position:sticky;bottom:0;background:#F3F5F9;padding:8px 0 calc(8px + env(safe-area-inset-bottom));display:flex;gap:8px}.bar .btn{flex:1}
.msg{padding:20px;text-align:center;color:#5a6b80}
.toast{position:fixed;left:50%;bottom:24px;transform:translateX(-50%);background:#14294A;color:#fff;padding:10px 16px;border-radius:10px;font-size:13px;z-index:20}
.grid{display:grid;grid-template-columns:repeat(4,1fr);gap:6px}.grid div{background:#fff;border:1px solid #E1E6EE;border-radius:10px;padding:8px;text-align:center}.grid b{display:block;font-size:20px;color:#14294A}.grid small{font-size:11px;color:#5a6b80}
table.t{width:100%;border-collapse:collapse;background:#fff;border-radius:10px;overflow:hidden;font-size:13px}table.t th{background:#14294A;color:#fff;font-weight:600;padding:6px;text-align:left}table.t td{padding:6px;border-bottom:1px solid #EEF1F5}
h3{font-size:14px;color:#14294A;margin:14px 0 6px}
.cm{background:#fff;border:1px solid #F3C2C2;border-left:4px solid #B3261E;border-radius:10px;padding:8px 10px;margin-bottom:6px}.cm.done{border-color:#BFE6CC;border-left-color:#1E9E5A}.cm p{margin:4px 0;font-size:13px}.cm small{color:#5a6b80}
a.ic{display:inline-block;text-decoration:none;font-weight:700;border-radius:8px;padding:3px 8px;font-size:12px;margin-left:4px}.ic.c{background:#E3F0FF;color:#1F4A8A}.ic.w{background:#E3F6EA;color:#1E7A43}
@media(max-width:520px){.grid{grid-template-columns:repeat(2,1fr)}}
</style></head><body>
<div class="top"><div class="r"><img src="img/snmsis-logo.png" alt=""><b>PTM Desk</b><input type="date" id="dt"><a href="index.php" target="_top">ERP</a></div>
<div class="tabs"><button data-t="desk" class="on">👪 Parents</button><button data-t="report">📋 Aaj ki report</button><button data-t="cmp">⚠️ Shikayatein</button></div></div>
<div class="wrap" id="app"><div class="msg">Khul raha hai…</div></div>
<script src="ptm.js?v=<?= htmlspecialchars($v) ?>"></script>
</body></html>
