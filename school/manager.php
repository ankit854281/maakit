<?php
// Salary app — Manager (does everything) and Chairman (sees everything). Nobody else (owner, 12 Nov 2026).
// salary_api.php + manager.js. Installable (manager-manifest.json, manager-sw.js).
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; connect-src 'self'; frame-src 'self'; frame-ancestors 'self'; base-uri 'none'; object-src 'none'");
$v = (string)@filemtime(__DIR__ . '/manager.js');
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><meta name="theme-color" content="#0F5132">
<link rel="manifest" href="manager-manifest.json"><link rel="icon" href="img/manager-192.png"><link rel="apple-touch-icon" href="img/manager-192.png"><title>Salary · SN Mishra SIS</title>
<style>
*{box-sizing:border-box}body{margin:0;font-family:Arial,Helvetica,sans-serif;font-size:15px;color:#13221b;background:#EEF3F0}
.top{background:#0F5132;color:#fff;padding:14px;display:flex;align-items:center;justify-content:space-between;gap:8px;position:sticky;top:0;z-index:6}.top b{font-size:18px}.top small{display:block;opacity:.85;font-size:12px}
.top button,.top select{background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.4);color:#fff;border-radius:99px;padding:6px 12px;font:inherit;font-size:13px}.top select option{color:#000}
.pg{padding:12px;max-width:900px;margin:0 auto}.card{background:#fff;border-radius:18px;padding:14px;margin-bottom:12px;box-shadow:0 1px 3px rgba(0,0,0,.06)}
.kp{display:grid;grid-template-columns:repeat(2,1fr);gap:8px;margin-bottom:12px}@media(min-width:700px){.kp{grid-template-columns:repeat(4,1fr)}}
.k{background:#fff;border-radius:16px;padding:12px;box-shadow:0 1px 3px rgba(0,0,0,.06)}.k span{display:block;font-size:12px;color:#5b6b63}.k b{display:block;font-size:20px;margin-top:2px}.k small{font-size:11px;color:#7a8a82}.k.g{background:#E3F4EA}.k.r{background:#FDECEC}.k.y{background:#FFF4D6}
.seg{display:flex;gap:6px;flex-wrap:wrap;margin-bottom:10px}.seg button{border:1px solid #c9d6cf;background:#fff;border-radius:99px;padding:7px 13px;font:inherit;font-size:13px}.seg button.on{background:#0F5132;color:#fff;border-color:#0F5132}
.st{border-top:1px solid #edf1ef;padding:11px 0}.st:first-of-type{border-top:0}.st .h{display:flex;justify-content:space-between;gap:8px;align-items:flex-start}.st .h b{font-size:15px}.st .h small{display:block;color:#6b7b73;font-size:12px}.st .net{text-align:right}.st .net b{font-size:18px}
.pills{display:flex;flex-wrap:wrap;gap:5px;margin-top:6px}.pl{font-size:11.5px;border-radius:99px;padding:3px 8px;background:#eef2f0;color:#33443c}.pl.r{background:#FDECEC;color:#8a1c1c}.pl.g{background:#E3F4EA;color:#14532d}.pl.y{background:#FFF4D6;color:#7a4d00}
.tag{font-size:11px;border-radius:99px;padding:2px 8px;margin-left:6px;vertical-align:2px}.tag.Paid{background:#E3F4EA;color:#14532d}.tag.Due{background:#FFF4D6;color:#7a4d00}.tag.Not{background:#eef2f0;color:#6b7b73}
.acts{display:flex;flex-wrap:wrap;gap:6px;margin-top:8px}.btn{font:inherit;font-size:13px;border:0;border-radius:99px;padding:8px 14px;background:#0F5132;color:#fff;cursor:pointer}.btn.l{background:#eef2f0;color:#0F5132}.btn.go{background:#F2C14E;color:#1d1d1d;font-weight:bold}.btn.full{width:100%;padding:11px}
.sheet{position:fixed;inset:0;background:rgba(10,25,18,.55);display:flex;align-items:flex-end;justify-content:center;z-index:20}.sheet .in{background:#fff;width:100%;max-width:560px;max-height:92vh;overflow:auto;border-radius:22px 22px 0 0;padding:16px}
.sheet h3{margin:0 0 10px;color:#0F5132}.f{margin-top:10px}.f label{display:block;font-size:12px;color:#5b6b63;margin-bottom:3px}input,select,textarea{font:inherit;border:1px solid #c9d6cf;border-radius:12px;padding:10px;width:100%;background:#fff}.two{display:grid;grid-template-columns:1fr 1fr;gap:8px}
.lg{max-width:380px;margin:40px auto;background:#fff;border-radius:22px;padding:22px}.lg h2{margin:0;color:#0F5132}.mut{color:#6b7b73;font-size:13px}
#toast{position:fixed;left:50%;bottom:18px;transform:translateX(-50%);background:#13221b;color:#fff;padding:10px 16px;border-radius:12px;font-size:14px;display:none;z-index:30;max-width:90vw}
iframe{width:100%;height:78vh;border:0;border-radius:16px;background:#fff}
</style></head><body><div id="app"></div><div id="sh"></div><div id="toast"></div>
<script src="manager.js?v=<?= htmlspecialchars($v) ?>"></script></body></html>
