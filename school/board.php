<?php
// Class 10 Board Prep — students practise chapter by chapter, take timed chapter tests and full mock tests;
// teachers see the class and add questions. board_api.php + board.js + board_bank.json. Installable (board-manifest.json).
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; connect-src 'self'; frame-ancestors 'self'; base-uri 'none'; object-src 'none'");
$v = (string)@filemtime(__DIR__ . '/board.js');
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><meta name="robots" content="noindex,nofollow"><meta name="theme-color" content="#3B1F7A">
<link rel="manifest" href="board-manifest.json"><link rel="icon" href="img/board-192.png"><link rel="apple-touch-icon" href="img/board-192.png"><title>Board Prep · Class 10 · SNMSIS</title>
<style>
*{box-sizing:border-box;-webkit-tap-highlight-color:transparent}body{margin:0;font-family:Arial,Helvetica,sans-serif;font-size:15px;color:#1c1530;background:#F3F0FA}
.top{background:linear-gradient(135deg,#3B1F7A,#6D3FD1);color:#fff;padding:16px 14px 18px;position:sticky;top:0;z-index:6}.top .r{display:flex;align-items:center;justify-content:space-between;gap:8px}.top b{font-size:18px}.top small{display:block;opacity:.85;font-size:12px;margin-top:2px}
.top button{background:rgba(255,255,255,.14);border:1px solid rgba(255,255,255,.35);color:#fff;border-radius:99px;padding:6px 12px;font:inherit;font-size:13px}
.pg{padding:12px;max-width:760px;margin:0 auto}.card{background:#fff;border-radius:18px;padding:14px;margin-bottom:12px;box-shadow:0 1px 3px rgba(40,20,90,.08)}
.card h3{margin:0 0 8px;font-size:16px;color:#3B1F7A}.mut{color:#6e6585;font-size:13px}.row{display:flex;align-items:center;justify-content:space-between;gap:8px;flex-wrap:wrap}
.kp{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-bottom:12px}.k{background:#fff;border-radius:16px;padding:10px;text-align:center;box-shadow:0 1px 3px rgba(40,20,90,.08)}.k b{display:block;font-size:20px;color:#3B1F7A}.k span{font-size:11.5px;color:#6e6585}
.subs{display:grid;grid-template-columns:1fr 1fr;gap:10px}.sub{border:0;border-radius:18px;padding:14px;text-align:left;font:inherit;color:#fff;cursor:pointer;min-height:96px;display:flex;flex-direction:column;justify-content:space-between}
.sub b{font-size:16px}.sub small{opacity:.9;font-size:12px}.s-maths{background:linear-gradient(135deg,#2563EB,#1E40AF)}.s-science{background:linear-gradient(135deg,#059669,#047857)}.s-sst{background:linear-gradient(135deg,#D97706,#B45309)}.s-english{background:linear-gradient(135deg,#DB2777,#9D174D)}.s-hindi{background:linear-gradient(135deg,#7C3AED,#5B21B6)}
.bar{height:7px;border-radius:99px;background:rgba(255,255,255,.3);overflow:hidden;margin-top:6px}.bar i{display:block;height:100%;background:#fff}.bar.d{background:#ece8f6}.bar.d i{background:#6D3FD1}
.btn{font:inherit;font-size:14px;border:0;border-radius:99px;padding:10px 16px;background:#3B1F7A;color:#fff;cursor:pointer;text-decoration:none;display:inline-block}.btn.l{background:#EEE9FA;color:#3B1F7A}.btn.go{background:#FBBF24;color:#1c1530;font-weight:bold}.btn.s{padding:6px 12px;font-size:13px}.btn.full{width:100%;padding:13px}.btn:disabled{opacity:.45}
.acts{display:flex;gap:6px;flex-wrap:wrap}.ch{padding:11px 0;border-top:1px solid #f0edf7}.ch:first-of-type{border-top:0}.ch b{font-size:14.5px}
.q{font-size:16.5px;line-height:1.45;white-space:pre-wrap;margin:6px 0 12px}.opt{display:block;width:100%;text-align:left;border:2px solid #e3dcf3;background:#fff;border-radius:14px;padding:12px 14px;margin-bottom:8px;font:inherit;font-size:15px;cursor:pointer;color:#1c1530}
.opt.sel{border-color:#6D3FD1;background:#F1EBFF}.opt.ok{border-color:#059669;background:#E6F7EF}.opt.bad{border-color:#DC2626;background:#FDECEC}.opt i{font-style:normal;font-weight:bold;color:#6D3FD1;margin-right:8px}
.exp{background:#FFF8E1;border-radius:12px;padding:10px 12px;font-size:14px;margin-top:4px}.pal{display:flex;flex-wrap:wrap;gap:6px;margin-top:10px}.pal button{width:34px;height:34px;border-radius:10px;border:1px solid #d9d1ee;background:#fff;font:inherit;font-size:13px}.pal button.a{background:#6D3FD1;color:#fff;border-color:#6D3FD1}.pal button.cur{outline:2px solid #FBBF24}
.timer{font-weight:bold;font-variant-numeric:tabular-nums}.timer.low{color:#FDE68A}.big{font-size:42px;font-weight:bold;color:#3B1F7A;text-align:center}.tag{font-size:11px;border-radius:99px;padding:2px 8px;background:#EEE9FA;color:#3B1F7A}
.rv{border-top:1px solid #f0edf7;padding:10px 0}.rv .y{color:#047857}.rv .n{color:#B91C1C}
input,select,textarea{font:inherit;border:1px solid #d9d1ee;border-radius:12px;padding:11px;width:100%;background:#fff}.f{margin-top:10px}.f label{display:block;font-size:12px;color:#6e6585;margin-bottom:3px}
.lg{max-width:390px;margin:30px auto;background:#fff;border-radius:22px;padding:22px}.lg h2{margin:0;color:#3B1F7A}
table.t{width:100%;border-collapse:collapse;font-size:13px}table.t th{background:#3B1F7A;color:#fff;font-weight:normal;padding:6px;text-align:left}table.t td{border-bottom:1px solid #f0edf7;padding:7px 6px;vertical-align:top}
.seg{display:flex;gap:6px;flex-wrap:wrap;margin-bottom:10px}.seg button{border:1px solid #d9d1ee;background:#fff;border-radius:99px;padding:8px 14px;font:inherit;font-size:13px}.seg button.on{background:#3B1F7A;color:#fff;border-color:#3B1F7A}
#toast{position:fixed;left:50%;bottom:18px;transform:translateX(-50%);background:#1c1530;color:#fff;padding:10px 16px;border-radius:12px;font-size:14px;display:none;z-index:30;max-width:90vw}
</style></head><body><div id="app"></div><div id="toast"></div>
<script src="board.js?v=<?= htmlspecialchars($v) ?>"></script></body></html>
