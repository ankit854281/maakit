<?php
// Data Entry — non-teaching staff page (own PIN login): student attendance not marked by class teachers,
// staff attendance, homework to share. dataentry_api.php + staffatt_api.php; dataentry.js.
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; connect-src 'self'; frame-src 'self'; frame-ancestors 'self'; base-uri 'none'; object-src 'none'");
$v = (string)@filemtime(__DIR__ . '/dataentry.js');
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><meta name="theme-color" content="#17324D"><title>Data Entry · SN Mishra SIS</title>
<style>
*{box-sizing:border-box}body{margin:0;font-family:Arial,Helvetica,sans-serif;font-size:15px;color:#17202a;background:#EEF2F5}
.top{background:#17324D;color:#fff;padding:12px 14px;display:flex;align-items:center;justify-content:space-between;gap:8px}.top b{font-size:17px}.top small{display:block;opacity:.8;font-size:12px}.top button{background:transparent;border:1px solid rgba(255,255,255,.5);color:#fff;border-radius:99px;padding:6px 12px;font:inherit;font-size:13px}
.tabs{display:flex;background:#fff;border-bottom:1px solid #dde3ea;position:sticky;top:0;z-index:5}.tabs button{flex:1;border:0;background:#fff;padding:12px 4px;font:inherit;font-size:14px;color:#556;border-bottom:3px solid transparent}.tabs button.on{color:#17324D;font-weight:bold;border-bottom-color:#F2A93B}
.pg{padding:12px;max-width:760px;margin:0 auto}.card{background:#fff;border-radius:16px;padding:14px;margin-bottom:12px;box-shadow:0 1px 3px rgba(0,0,0,.06)}
.card h3{margin:0 0 8px;font-size:16px;color:#17324D}.mut{color:#667;font-size:13px}.row{display:flex;align-items:center;justify-content:space-between;gap:8px}
input,select,textarea{font:inherit;border:1px solid #c7d0da;border-radius:12px;padding:10px;width:100%;background:#fff}.btn{font:inherit;border:0;border-radius:99px;padding:10px 16px;background:#17324D;color:#fff;cursor:pointer}.btn.l{background:#eef1f5;color:#17324D}.btn.go{background:#F2A93B;color:#1b1b1b;font-weight:bold}.btn.s{padding:6px 12px;font-size:13px}.btn:disabled{opacity:.45}
.cls{display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:8px}.cls button{border:0;border-radius:14px;padding:12px;text-align:left;font:inherit;cursor:pointer}.cls b{display:block;font-size:16px}.cls small{display:block;font-size:12px;margin-top:2px}
.c-no{background:#FDECEC;color:#8a1c1c}.c-ok{background:#E6F6EC;color:#14532d}.c-hol{background:#f1f3f6;color:#889;cursor:default}
.kid{display:flex;align-items:center;justify-content:space-between;gap:8px;padding:9px 0;border-bottom:1px solid #eef1f4}.kid b{font-size:14px}.kid small{color:#889;font-size:12px;margin-right:6px}
.pal button{border:1px solid #cfd6de;background:#fff;border-radius:10px;width:42px;height:36px;font:inherit;font-weight:bold;margin-left:4px}.pal button.P.on{background:#1d8f4e;color:#fff;border-color:#1d8f4e}.pal button.A.on{background:#b42318;color:#fff;border-color:#b42318}.pal button.L.on{background:#1d4ed8;color:#fff;border-color:#1d4ed8}
.hw{padding:8px 0;border-bottom:1px solid #eef1f4}.hw b{font-size:14px}.hw p{margin:3px 0 0;font-size:14px}pre.msg{white-space:pre-wrap;font-family:inherit;font-size:13px;background:#f7f8fa;border-radius:12px;padding:10px;margin:8px 0}
.lg{max-width:380px;margin:40px auto;background:#fff;border-radius:20px;padding:20px}.lg h2{margin:0 0 4px;color:#17324D}.lg .f{margin-top:12px}
#toast{position:fixed;left:50%;bottom:18px;transform:translateX(-50%);background:#17202a;color:#fff;padding:10px 16px;border-radius:12px;font-size:14px;display:none;z-index:20;max-width:90vw}
iframe{width:100%;height:80vh;border:0;border-radius:16px;background:#fff}
.sub{display:flex;gap:6px;margin-bottom:10px}.sub button{border:1px solid #c7d0da;background:#fff;border-radius:99px;padding:7px 13px;font:inherit;font-size:13px}.sub button.on{background:#17324D;color:#fff;border-color:#17324D}
.kpis{display:grid;grid-template-columns:repeat(5,1fr);gap:6px;margin:4px 0 10px}.kpis div{background:#f2f4f7;border-radius:12px;padding:8px 4px;text-align:center}.kpis b{display:block;font-size:18px}.kpis span{font-size:11px;color:#556}.kpis .g{background:#E6F6EC}.kpis .r{background:#FDECEC}.kpis .b{background:#E3EEFF}.kpis .y{background:#FFF4D6}
table.rep{width:100%;border-collapse:collapse;font-size:13px}table.rep th{background:#17324D;color:#fff;font-weight:normal;padding:6px;text-align:left}table.rep td{border-bottom:1px solid #eef1f4;padding:7px 6px}table.rep td.bad{color:#b42318;font-weight:bold}table.rep tr.hol td{color:#99a;background:#fafbfc}table.rep tr.tot td{font-weight:bold;background:#f6f7f9}
.kpis.k4{grid-template-columns:repeat(4,1fr)}.kpis span{display:block}table.rep td.okc{color:#14532d}table.rep td small{font-weight:normal;color:#8a1c1c}table.rep.kit td,table.rep.kit th{text-align:center}table.rep.kit td:first-child,table.rep.kit th:first-child{text-align:left}table.rep.kit th .btn{padding:4px 8px;font-size:12px}
.tick{display:inline-flex;flex-direction:column;align-items:center;gap:1px;padding:3px 6px;border-radius:8px}.tick input{width:22px;height:22px;margin:0;accent-color:#1d8f4e}.tick small{font-size:10px;color:#667}.tick.ch{background:#FFF4D6}
.tabs button{font-size:13px}</style></head><body><div id="app"></div><div id="toast"></div>
<script src="dataentry.js?v=<?= htmlspecialchars($v) ?>"></script></body></html>
