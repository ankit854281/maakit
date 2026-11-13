<?php
// Cash Book — day book (opening, fee receipts, tour fee, expenses, income, closing) and Tour fee receipts.
// cashdesk_api.php + cashdesk.js. Opened inside the Manager (Salary) app with ?embed=1&tk=sal (its own login),
// or directly with an ERP login (Principal / Accountant / Chairman).
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; connect-src 'self'; frame-ancestors 'self'; base-uri 'none'; object-src 'none'");
$v = (string)@filemtime(__DIR__ . '/cashdesk.js');
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><meta name="theme-color" content="#0F5132"><title>Cash Book · SN Mishra SIS</title>
<style>
*{box-sizing:border-box}body{margin:0;font-family:Arial,Helvetica,sans-serif;font-size:15px;color:#13221b;background:#EEF3F0}
.emb{background:transparent}.top{background:#0F5132;color:#fff;padding:12px 14px}.top b{font-size:17px}.emb .top{display:none}
.tabs{display:flex;gap:6px;flex-wrap:wrap;margin-bottom:10px}.tabs button{border:1px solid #c9d6cf;background:#fff;border-radius:99px;padding:8px 14px;font:inherit;font-size:13px;cursor:pointer}.tabs button.on{background:#0F5132;color:#fff;border-color:#0F5132}
.pg{padding:12px;max-width:900px;margin:0 auto}.emb .pg{padding:4px}.card{background:#fff;border-radius:16px;padding:14px;margin-bottom:12px;box-shadow:0 1px 3px rgba(0,0,0,.06)}
.card h3{margin:0 0 8px;font-size:16px;color:#0F5132}.mut{color:#6b7b73;font-size:13px}.row{display:flex;align-items:center;justify-content:space-between;gap:8px;flex-wrap:wrap}
.kp{display:grid;grid-template-columns:repeat(2,1fr);gap:8px;margin-bottom:12px}@media(min-width:640px){.kp{grid-template-columns:repeat(4,1fr)}}
.k{background:#fff;border-radius:14px;padding:11px;box-shadow:0 1px 3px rgba(0,0,0,.06)}.k span{display:block;font-size:12px;color:#5b6b63}.k b{display:block;font-size:20px;margin-top:2px}.k small{font-size:11px;color:#7a8a82}.k.g{background:#E3F4EA}.k.r{background:#FDECEC}.k.y{background:#FFF4D6}.k.b{background:#E3EEFF}
.acts{display:flex;flex-wrap:wrap;gap:6px}.btn{font:inherit;font-size:13px;border:0;border-radius:99px;padding:9px 14px;background:#0F5132;color:#fff;cursor:pointer;text-decoration:none;display:inline-block}.btn.l{background:#eef2f0;color:#0F5132}.btn.go{background:#F2C14E;color:#1d1d1d;font-weight:bold}.btn.r{background:#FDECEC;color:#8a1c1c}.btn.s{padding:5px 10px;font-size:12px}.btn.full{width:100%;padding:11px}.btn:disabled{opacity:.45}
input,select,textarea{font:inherit;border:1px solid #c9d6cf;border-radius:12px;padding:10px;width:100%;background:#fff}.f{margin-top:10px}.f label{display:block;font-size:12px;color:#5b6b63;margin-bottom:3px}.two{display:grid;grid-template-columns:1fr 1fr;gap:8px}
table.t{width:100%;border-collapse:collapse;font-size:13px}table.t th{background:#0F5132;color:#fff;font-weight:normal;padding:6px;text-align:left}table.t td{border-bottom:1px solid #edf1ef;padding:7px 6px;vertical-align:top}table.t td.n{text-align:right;white-space:nowrap}table.t tr.tot td{font-weight:bold;background:#f5f8f6}
.tg{font-size:11px;border-radius:99px;padding:2px 8px;white-space:nowrap}.tg.fee{background:#E3EEFF;color:#1d4ed8}.tg.tour{background:#FFF1CC;color:#7a4b00}.tg.expense{background:#FDECEC;color:#8a1c1c}.tg.income,.tg.opening{background:#E3F4EA;color:#14532d}.tg.deposit,.tg.withdraw{background:#eef2f0;color:#33443c}
.kid{display:flex;align-items:center;justify-content:space-between;gap:8px;padding:9px 0;border-bottom:1px solid #edf1ef}.kid b{font-size:14px}.kid small{display:block;color:#6b7b73;font-size:12px}.paid{color:#14532d;font-size:12px;text-align:right}.cnl{color:#8a1c1c;font-size:12px}
.chips{display:flex;flex-wrap:wrap;gap:6px}.chips label{display:flex;align-items:center;gap:4px;border:1px solid #c9d6cf;border-radius:99px;padding:5px 10px;font-size:13px}.chips input{width:auto}
.sheet{position:fixed;inset:0;background:rgba(10,25,18,.55);display:flex;align-items:flex-end;justify-content:center;z-index:20}.sheet .in{background:#fff;width:100%;max-width:560px;max-height:92vh;overflow:auto;border-radius:22px 22px 0 0;padding:16px}.sheet h3{margin:0 0 6px;color:#0F5132}
#toast{position:fixed;left:50%;bottom:18px;transform:translateX(-50%);background:#13221b;color:#fff;padding:10px 16px;border-radius:12px;font-size:14px;display:none;z-index:30;max-width:90vw}
</style></head><body><div class="top"><b>Cash Book</b></div><div class="pg" id="app"></div><div id="sh"></div><div id="toast"></div>
<script src="cashdesk.js?v=<?= htmlspecialchars($v) ?>"></script></body></html>
