<?php
// Family App usage report — who has logged in to the Student & Parents app, who uses it, who has not yet (famstats.js / famstats_api.php).
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; connect-src 'self'; frame-ancestors 'self'; base-uri 'none'; object-src 'none'");
$v = (string)@filemtime(__DIR__ . '/famstats.js');
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Family App usage · SN Mishra SIS</title>
<style>
*{box-sizing:border-box}body{margin:0;font-family:Arial,Helvetica,sans-serif;font-size:14px;color:#17202a;background:#EEF2F7}
.top{background:linear-gradient(140deg,#1e1b4b,#4338ca 75%,#0f766e 140%);color:#fff;padding:16px 14px 44px}.top b{font-size:19px;display:block}.top small{opacity:.8;font-size:12.5px}
.top .r{float:right;display:flex;gap:6px}.top a,.top button{color:#fff;background:rgba(255,255,255,.16);border:0;border-radius:8px;padding:7px 11px;font-size:13px;text-decoration:none;cursor:pointer}
.emb .top{padding-top:10px}
.kp{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:8px;padding:0 12px;margin-top:-32px}
.kp>div{background:#fff;border-radius:14px;padding:12px 14px;box-shadow:0 8px 20px -14px #1e1b4b}.kp span{display:block;font-size:12px;color:#5b6b7b}.kp b{font-size:24px;color:#1e1b4b}.kp i{font-style:normal;font-size:12px;color:#0f8a5f;font-weight:700;margin-left:4px}
.kp .bar{height:6px;border-radius:9px;background:#e8ebf3;margin-top:6px;overflow:hidden}.kp .bar em{display:block;height:100%;background:#4338ca;border-radius:9px}
h3{margin:18px 12px 8px;font-size:15px;color:#1e1b4b}
.card{background:#fff;border-radius:14px;margin:0 12px;box-shadow:0 1px 3px rgba(0,0,0,.06);overflow:auto}
.trend{display:grid;grid-template-columns:repeat(14,1fr);gap:4px;align-items:end;height:150px;padding:12px 12px 4px}
.trend div{display:flex;flex-direction:column;justify-content:flex-end;align-items:center;height:100%;gap:2px}.trend i{width:70%;border-radius:4px 4px 0 0;display:block}.trend .p{background:#4338ca}.trend .s{background:#14b8a6;border-radius:0}
.trend small{font-size:10px;color:#6b7a89}.trend em{font-style:normal;font-size:10px;color:#1e1b4b;font-weight:700}
.leg{display:flex;gap:14px;padding:0 12px 10px;font-size:12px;color:#5b6b7b}.leg span:before{content:'';display:inline-block;width:10px;height:10px;border-radius:3px;margin-right:5px;vertical-align:-1px;background:var(--c)}
table{border-collapse:collapse;width:100%;min-width:620px}th,td{padding:8px 10px;border-bottom:1px solid #e6ebf0;text-align:left;font-size:13px}th{background:#F4F6FA;font-size:12px;color:#3d4b59}
td.n,th.n{text-align:right}table.nl{min-width:0}table.nl td{padding:9px 8px}table.nl .btn{white-space:nowrap;padding:7px 10px}.pb{height:7px;border-radius:9px;background:#e8ebf3;overflow:hidden;min-width:90px}.pb em{display:block;height:100%;border-radius:9px}
.bar2{display:flex;flex-wrap:wrap;gap:6px;align-items:center;padding:10px 12px}.bar2 select,.bar2 input{padding:7px 8px;border:1px solid #c9d2dc;border-radius:8px;font-size:13px}
.btn{border:0;border-radius:8px;padding:8px 12px;font-size:13px;font-weight:600;cursor:pointer;background:#E2E8F0;color:#17202a;text-decoration:none;display:inline-block}.btn.wa{background:#25D366;color:#fff}.btn.go{background:#4338ca;color:#fff}
.mut{color:#6b7a89;font-size:12px}.msg{padding:30px 16px;text-align:center}.ok{color:#0f8a5f;font-weight:700}.bad{color:#c0352b;font-weight:700}
@media print{.top .r,.bar2,.btn{display:none!important}body{background:#fff}}
</style></head><body>
<main id="app"><div class="msg">Opening the Family App report…</div></main>
<script src="famstats.js?v=<?= htmlspecialchars($v) ?>"></script>
</body></html>
