<?php
// Fee Calling Report — every saved fee call with date, result, promise date, remark and caller (callreport.js / callreport_api.php).
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; connect-src 'self'; frame-ancestors 'self'; base-uri 'none'; object-src 'none'");
$v = (string)@filemtime(__DIR__ . '/callreport.js');
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Fee Calling Report · SN Mishra SIS</title>
<style>
*{box-sizing:border-box}body{margin:0;font-family:Arial,Helvetica,sans-serif;font-size:14px;color:#17202a;background:#EEF2F5}
.top{background:#17324D;color:#fff;padding:12px 14px;display:flex;flex-wrap:wrap;gap:8px;align-items:center;justify-content:space-between}.top b{font-size:17px}.top small{display:block;opacity:.8;font-size:12px}
.top a,.top button{color:#fff;background:rgba(255,255,255,.15);border:0;border-radius:8px;padding:7px 11px;font-size:13px;text-decoration:none;cursor:pointer}
.emb .top{display:none}
.bar{display:flex;flex-wrap:wrap;gap:6px;align-items:center;padding:10px 12px;background:#fff;border-bottom:1px solid #dde3ea}
.bar input,.bar select{padding:7px 8px;border:1px solid #c9d2dc;border-radius:8px;font-size:13px;background:#fff;max-width:100%}
.bar label{font-size:12px;color:#5b6b7b}
.q{border:1px solid #c9d2dc;background:#fff;border-radius:999px;padding:6px 11px;font-size:12.5px;cursor:pointer}.q.on{background:#17324D;color:#fff;border-color:#17324D}
.btn{border:0;border-radius:8px;padding:8px 12px;font-size:13px;font-weight:600;cursor:pointer;background:#E2E8F0;color:#17202a}.btn.go{background:#E8A317;color:#17202a}
.kp{display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:8px;padding:12px}
.kp div{background:#fff;border-radius:10px;padding:10px 12px;box-shadow:0 1px 2px rgba(0,0,0,.06)}.kp span{display:block;font-size:12px;color:#5b6b7b}.kp b{font-size:19px}
.tabs{display:flex;gap:6px;padding:0 12px 8px;flex-wrap:wrap}
.wrap{padding:0 12px 20px}.card{background:#fff;border-radius:10px;box-shadow:0 1px 2px rgba(0,0,0,.06);overflow:auto}
table{border-collapse:collapse;width:100%;min-width:900px}th,td{padding:7px 8px;border-bottom:1px solid #e6ebf0;text-align:left;vertical-align:top;font-size:13px}
th{background:#F4F6F8;position:sticky;top:0;font-size:12px;color:#3d4b59;white-space:nowrap}
td.n{white-space:nowrap}.mut{color:#6b7a89;font-size:12px}.note{max-width:280px;white-space:pre-wrap}
.pill{display:inline-block;padding:2px 8px;border-radius:999px;font-size:11.5px;font-weight:600;white-space:nowrap}
.g{background:#DCFCE7;color:#166534}.r{background:#FEE2E2;color:#991B1B}.y{background:#FEF3C7;color:#92400E}.b{background:#DBEAFE;color:#1E40AF}.s{background:#E5E7EB;color:#374151}
.empty{padding:24px;text-align:center;color:#6b7a89}.msg{padding:30px 16px;text-align:center}
@media print{.top,.bar,.tabs,.noprint{display:none!important}body{background:#fff}.card{box-shadow:none}table{min-width:0}th{position:static}}
</style></head><body>
<main id="app"><div class="msg">Opening Fee Calling Report…</div></main>
<script src="callreport.js?v=<?= htmlspecialchars($v) ?>"></script>
</body></html>
