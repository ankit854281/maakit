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

.foot{display:flex;justify-content:space-between;align-items:flex-end;margin-top:10px;font-size:12px}.foot div{text-align:center;min-width:140px}.foot div i{display:block;border-top:1px solid #333;margin-top:28px;font-style:normal;padding-top:2px}
.sum{display:flex;gap:18px;flex-wrap:wrap;margin-top:8px;font-size:13px}.sum b{font-size:15px}
.gn{font-size:11px;color:#555;margin-top:6px}
.slipbar{display:flex;gap:6px;margin:-6px 0 14px}.slipbar .btn{padding:6px 10px;font-size:12.5px}
.certs{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:12px}.cert{background:#fff;border:1px solid #dfe4ea;border-radius:6px;padding:10px}.cert img{width:100%;display:block;border:1px solid #e3e7ee;cursor:zoom-in}.cert h4{margin:0 0 6px}
@media print{*{-webkit-print-color-adjust:exact;print-color-adjust:exact}body{background:#fff}.np,.slipbar{display:none!important}.wrap{max-width:none;padding:0}.slip{margin:0 0 6mm;border-width:1.5px}@page{size:A4;margin:8mm}}
</style></head><body><div class="wrap">
<div class="top np"><h1>Half-Yearly · Marks Slip &amp; Rankers</h1><a href="index.php" target="_top">← ERP</a></div>
<div class="pan np" id="bar"><div class="msg">Opening…</div></div>
<div id="out"></div>
</div>
<script src="results.js?v=<?= htmlspecialchars($v) ?>"></script>
</body></html>
