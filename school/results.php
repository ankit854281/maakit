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
@font-face{font-family:'NotoDeva';src:url('../smartsarathi/assets/fonts/noto-deva-400.woff2') format('woff2');font-weight:400;unicode-range:U+0900-097F,U+1CD0-1CFF,U+200C-200D,U+20B9,U+25CC,U+A830-A839,U+A8E0-A8FF}
@font-face{font-family:'NotoDeva';src:url('../smartsarathi/assets/fonts/noto-deva-700.woff2') format('woff2');font-weight:700;unicode-range:U+0900-097F,U+1CD0-1CFF,U+200C-200D,U+20B9,U+25CC,U+A830-A839,U+A8E0-A8FF}
@font-face{font-family:'Poppins';src:url('fonts/poppins-latin-400-normal.woff2') format('woff2');font-weight:400}
@font-face{font-family:'Poppins';src:url('fonts/poppins-latin-500-normal.woff2') format('woff2');font-weight:500}
@font-face{font-family:'Poppins';src:url('fonts/poppins-latin-600-normal.woff2') format('woff2');font-weight:600}
@font-face{font-family:'Poppins';src:url('fonts/poppins-latin-700-normal.woff2') format('woff2');font-weight:700}
body{margin:0;font-family:Arial,'NotoDeva',Helvetica,sans-serif;background:#f4f6f9;color:#1d2733;font-size:14px}
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
.shd{display:flex;align-items:center;gap:12px;background:#14294A;color:#fff;margin:-14px -16px 10px;padding:10px 16px;border-bottom:3px solid #D9B45A}.shd img{width:52px;height:52px;border-radius:50%;background:#fff;padding:2px}
.shd div{flex:1}.shd div b{display:block;font-size:18px;letter-spacing:.5px}.shd div span{font-size:11.5px;color:#DDE6F0}.shd i{font-style:normal;text-align:right;font-size:11px;color:#F6E3A1;font-weight:bold;line-height:1.4}.shd i em{font-style:normal;font-size:15px;color:#fff;letter-spacing:2px}
.stu{display:flex;align-items:center;gap:14px;margin-bottom:8px}.ph{flex:0 0 74px;height:74px;border-radius:50%;overflow:hidden;border:3px solid #D9B45A;background:#E9EEF5;display:flex;align-items:center;justify-content:center}.ph img{width:100%;height:100%;object-fit:cover}.ph span{font-weight:bold;font-size:24px;color:#14294A}
.who{flex:1;display:flex;flex-direction:column;gap:2px;font-size:12.5px}.who>b{font-size:19px;color:#14294A}
.ring{flex:0 0 74px;height:74px;border-radius:50%;display:flex;align-items:center;justify-content:center}.ring div{width:58px;height:58px;border-radius:50%;background:#fff;display:flex;flex-direction:column;align-items:center;justify-content:center}.ring b{font-size:17px;color:#14294A}.ring small{font-size:10px;font-weight:bold;color:#555}
.rk{flex:0 0 80px;text-align:center;border:2px solid #14294A;border-radius:10px;padding:4px 0}.rk b{display:block;font-size:24px;color:#7A1F2B;line-height:1.1}.rk small{font-size:10px;color:#555}
.gb{display:inline-block;min-width:26px;padding:1px 5px;border-radius:9px;color:#fff;font-weight:bold;font-size:11px}
td.bars{padding:4px 8px}.bar{position:relative;height:10px;background:#EEF1F5;border-radius:5px;overflow:visible}.bar i{position:absolute;left:0;top:0;bottom:0;border-radius:5px}.bar u{position:absolute;top:-3px;width:2px;height:16px;background:#14294A;text-decoration:none}
.low{display:flex;gap:12px;margin-top:8px}.rmk{flex:1;background:#FBF7EC;border-left:4px solid #D9B45A;padding:6px 10px;font-size:12.5px}.rmk p{margin:3px 0 4px}
.sw{display:flex;gap:10px;flex-wrap:wrap;font-size:11.5px}.sw .ok{color:#1E7A43;font-weight:bold}.sw .wk{color:#B36B00;font-weight:bold}
.side{flex:0 0 190px;font-size:11.5px;display:flex;flex-direction:column;gap:6px}.side div{border:1px solid #dfe4ea;border-radius:6px;padding:5px 8px}.side b{display:block;margin-bottom:2px}.side span{display:inline-block;margin-right:8px}.side em{font-style:normal;font-weight:bold;color:#14294A}
.mut{color:#888}
.letter{font-family:'NotoDeva',Arial,sans-serif;background:linear-gradient(90deg,#FFF8E6,#FFFDF6);border:1px dashed #D9B45A;border-radius:8px;padding:7px 12px;margin:0 0 8px;font-size:13px;line-height:1.6;color:#3d352a}.letter b{color:#14294A}.letter em{display:block;text-align:right;font-style:normal;font-weight:700;color:#7A1F2B}
.badges{display:flex;gap:6px;flex-wrap:wrap;margin:0 0 8px}.badges span{display:inline-flex;align-items:center;gap:4px;padding:3px 10px 3px 6px;border-radius:14px;font-size:11.5px;font-weight:bold;color:#14294A;background:#FFF4CC;border:1.5px solid #E0A100}.badges span i{font-style:normal;font-size:14px}
.pos{margin:6px 0 4px;font-size:11.5px}.pos .ln{position:relative;height:28px;margin:4px 10px 0;border-bottom:2px solid #C9D2DC}.pos .ln i{position:absolute;bottom:-5px;width:8px;height:8px;margin-left:-4px;border-radius:50%;background:#AEB8C4}.pos .ln b{position:absolute;bottom:-9px;width:16px;height:16px;margin-left:-8px;border-radius:50%;background:#E0A100;border:2px solid #14294A}.pos .ln u{position:absolute;bottom:10px;transform:translateX(-50%);text-decoration:none;font-weight:bold;color:#7A1F2B;white-space:nowrap}
.pos .sc{display:flex;justify-content:space-between;margin:2px 4px 0;color:#888;font-size:10px}

/* ---- slip v3 colours & design (owner, 20 Nov 2026: "color and design pe bhi jao") ---- */
.slip{position:relative;overflow:hidden;border:1.5px solid #D9B45A;border-radius:14px;padding:0 18px 14px;box-shadow:0 3px 14px rgba(20,41,74,.12);background:#fff}
.slip:before{content:"";position:absolute;inset:150px 0 0;background:url(img/snmsis-logo.png) no-repeat center 45%/330px;opacity:.045;pointer-events:none}
.slip>*{position:relative}
.shd{margin:0 -18px 12px;padding:12px 18px 14px;border-bottom:0;background:radial-gradient(circle at 92% -20%,rgba(246,227,161,.28) 0 90px,transparent 91px),radial-gradient(circle at 78% 130%,rgba(255,255,255,.08) 0 70px,transparent 71px),linear-gradient(120deg,#0E1F3D,#1F4A8A 68%,#14294A)}
.shd:after{content:"";position:absolute;left:0;right:0;bottom:0;height:4px;background:linear-gradient(90deg,#8C6A1E,#F6E3A1,#D9B45A,#8C6A1E)}
.shd img{width:58px;height:58px;box-shadow:0 0 0 3px #D9B45A}.shd div b{font-size:19px;letter-spacing:.8px}
.shd i em{display:inline-block;margin-top:3px;background:linear-gradient(90deg,#D9B45A,#F6E3A1);color:#14294A;padding:3px 12px;border-radius:12px;font-size:13px;letter-spacing:2.5px}
.stu{background:linear-gradient(95deg,#F2F6FC 0%,#FFF9EA 100%);border:1px solid #E8DFC6;border-radius:12px;padding:10px 12px}
.ph{flex-basis:84px;height:84px;border:3px solid #fff;box-shadow:0 0 0 3px #D9B45A,0 3px 8px rgba(0,0,0,.15)}.who>b{font-size:21px;letter-spacing:.2px}
.ring{box-shadow:0 2px 6px rgba(0,0,0,.08)}.rk{background:#fff;border-color:#D9B45A;box-shadow:0 2px 6px rgba(0,0,0,.08)}
.letter{border:0;border-left:5px solid #7A1F2B;border-radius:10px;background:linear-gradient(90deg,#FFF6E0,#FFFDF7);box-shadow:inset 0 0 0 1px #F0E2BC;padding:8px 14px 6px 40px}
.letter:before{content:"✒";position:absolute;left:12px;top:7px;font-size:18px;color:#7A1F2B}
.badges span{background:linear-gradient(180deg,#FFF8DC,#FBE7A6);border-color:#D9A520;box-shadow:0 1px 2px rgba(140,106,30,.25)}
table.m{border-collapse:separate;border-spacing:0;border:1px solid #C9D3E0;border-radius:10px;overflow:hidden;background:rgba(255,255,255,.85)}
table.m th,table.m td{border:0;border-bottom:1px solid #E4EAF2;border-right:1px solid #EEF2F7}table.m th{background:#14294A;color:#fff;font-size:11.5px;font-weight:bold}table.m th small{color:#F6E3A1}
table.m tr:nth-child(odd) td{background:#F7F9FC}table.m td.s{font-weight:bold;color:#14294A}table.m td.s:before{content:"";display:inline-block;width:7px;height:7px;border-radius:50%;background:#D9B45A;margin-right:6px;vertical-align:1px}
table.m tr.t td.s:before{display:none}table.m tr.t td{background:linear-gradient(180deg,#FFF6DA,#FCEBB6)!important;border-bottom:0;color:#14294A}
.tgt{margin:8px 0 2px;border-radius:10px;background:linear-gradient(95deg,#EAF3FF,#F5FAFF);border:1px solid #C8DCF5;padding:7px 12px}
.tgt>b{display:block;font-size:12.5px;color:#14294A;margin-bottom:5px}.tgt>b small{font-weight:normal;color:#5a6b80;font-family:'NotoDeva',Arial,sans-serif}
.tgt div{display:flex;flex-wrap:wrap;gap:6px}.tgt span{background:#fff;border:1px solid #BFD3EE;border-radius:8px;padding:3px 9px;font-size:11.5px;color:#1d2733}.tgt span b{color:#1F4A8A}.tgt span em{font-style:normal;font-weight:bold;color:#1E7A43}
.rmk{border-radius:0 10px 10px 0}.side div{background:#fff}
.foot div i{border-top:1.5px solid #14294A;color:#14294A}

/* ---- slip v4 — professional finish (owner, 20 Nov 2026: "more attractive best professional look") ---- */
.slip{font-family:Poppins,Arial,'NotoDeva',sans-serif;font-size:12.5px;color:#1f2a37}
.shd div b{font-family:Georgia,'Times New Roman',serif;font-weight:700;font-size:20px;letter-spacing:1px}.shd div span{letter-spacing:.3px}
.who>b{font-family:Georgia,serif;font-size:23px}.who span{color:#4a5565}
.letter{font-family:Georgia,'Times New Roman',serif;font-size:13.5px;line-height:1.6;color:#3a3226}.letter b{font-family:Georgia,serif}.letter em{font-family:Georgia,serif;font-style:italic}
.badges span{font-weight:600;font-size:11px;letter-spacing:.2px}
table.m{font-size:12px}table.m th{font-weight:600;letter-spacing:.3px;text-transform:uppercase;font-size:10.5px;padding:6px 4px}table.m td{padding:5px}
table.m td.s{font-weight:600}
.tgt{background:linear-gradient(95deg,#EEF4FD,#F8FBFF)}.tgt>b{font-weight:600}
.pos b{font-weight:600}
.low3{display:grid;grid-template-columns:1.35fr 1fr 1.25fr;gap:10px;margin-top:10px}
.card{background:#fff;border:1px solid #E1E6EE;border-radius:12px;padding:9px 12px;box-shadow:0 1px 3px rgba(20,41,74,.06)}
.card h5{margin:0 0 7px;font-size:10.5px;font-weight:700;letter-spacing:1.6px;text-transform:uppercase;color:#14294A;padding-bottom:4px;border-bottom:2px solid;border-image:linear-gradient(90deg,#D9B45A,transparent) 1}
.card.rmk{background:linear-gradient(180deg,#FFFCF3,#fff);border-left:4px solid #D9B45A;border-radius:12px}.card.rmk p{margin:0;font-size:12.5px;line-height:1.55;color:#333}
.attw{display:flex;align-items:center;gap:12px}.ring2{flex:0 0 74px;height:74px;border-radius:50%;display:flex;align-items:center;justify-content:center}.ring2 div{width:56px;height:56px;border-radius:50%;background:#fff;display:flex;align-items:center;justify-content:center}.ring2 b{font-size:16px;color:#14294A}
.attx{display:flex;flex-direction:column;gap:2px;font-size:11.5px;color:#4a5565}.attx b{color:#14294A}
.cr{display:flex;align-items:center;gap:7px;margin:3px 0;font-size:11px}.cr span{flex:0 0 92px;color:#4a5565}.cb{flex:1;height:8px;background:#EEF1F5;border-radius:4px;overflow:hidden}.cb i{display:block;height:100%;border-radius:4px}
.cr em{flex:0 0 20px;height:18px;border-radius:5px;color:#fff;font-style:normal;font-weight:700;font-size:11px;display:flex;align-items:center;justify-content:center}
.cos small{display:block;margin-top:4px;font-size:9.5px;color:#8a94a3}
.gn{font-size:10px;color:#7b8594}
.foot{margin-top:18px}.foot div i{font-weight:500;font-size:11.5px;letter-spacing:.3px}

.foot{display:flex;justify-content:space-between;align-items:flex-end;margin-top:10px;font-size:12px}.foot div{text-align:center;min-width:140px}.foot div i{display:block;border-top:1px solid #333;margin-top:28px;font-style:normal;padding-top:2px}
.sum{display:flex;gap:18px;flex-wrap:wrap;margin-top:8px;font-size:13px}.sum b{font-size:15px}
.gn{font-size:11px;color:#555;margin-top:6px}
.slipbar{display:flex;gap:6px;margin:-6px 0 14px}.slipbar .btn{padding:6px 10px;font-size:12.5px}
.certs{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:12px}.cert{background:#fff;border:1px solid #dfe4ea;border-radius:6px;padding:10px}.cert img{width:100%;display:block;border:1px solid #e3e7ee;cursor:zoom-in}.cert h4{margin:0 0 6px}
@media print{.slip{page-break-after:always;box-shadow:none}*{-webkit-print-color-adjust:exact;print-color-adjust:exact}body{background:#fff}.np,.slipbar{display:none!important}.wrap{max-width:none;padding:0}.slip{margin:0 0 6mm;border-width:1.5px}@page{size:A4;margin:8mm}}
</style></head><body><div class="wrap">
<div class="top np"><h1>Half-Yearly · Marks Slip &amp; Rankers</h1><a href="index.php" target="_top">← ERP</a></div>
<div class="pan np" id="bar"><div class="msg">Opening…</div></div>
<div id="out"></div>
</div>
<script src="vendor/qrcode.js"></script>
<script src="results.js?v=<?= htmlspecialchars($v) ?>"></script>
</body></html>
