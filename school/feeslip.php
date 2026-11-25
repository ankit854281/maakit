<?php
// Fee Reminder Slip — printable reminder slips for every family with fee due, and the list of slips given (feeslip.js / feeslip_api.php).
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; connect-src 'self'; frame-ancestors 'self'; base-uri 'none'; object-src 'none'");
$v = (string)@filemtime(__DIR__ . '/feeslip.js');
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Fee Reminder Slip · SN Mishra SIS</title>
<style>
*{box-sizing:border-box}body{margin:0;font-family:Arial,Helvetica,sans-serif;font-size:14px;color:#17202a;background:#EEF2F5}
.top{background:#17324D;color:#fff;padding:12px 14px;display:flex;flex-wrap:wrap;gap:8px;align-items:center;justify-content:space-between}.top b{font-size:17px}.top small{display:block;opacity:.8;font-size:12px}
.top button{color:#fff;background:rgba(255,255,255,.15);border:0;border-radius:8px;padding:7px 11px;font-size:13px;cursor:pointer}
.bar{display:flex;flex-wrap:wrap;gap:6px;align-items:center;padding:10px 12px;background:#fff;border-bottom:1px solid #dde3ea}
.bar input,.bar select{padding:7px 8px;border:1px solid #c9d2dc;border-radius:8px;font-size:13px;background:#fff;max-width:100%}.bar label{font-size:12px;color:#5b6b7b}
.q{border:1px solid #c9d2dc;background:#fff;border-radius:999px;padding:6px 11px;font-size:12.5px;cursor:pointer}.q.on{background:#17324D;color:#fff;border-color:#17324D}
.btn{border:0;border-radius:8px;padding:9px 13px;font-size:13px;font-weight:600;cursor:pointer;background:#E2E8F0;color:#17202a}.btn.go{background:#E8A317}.btn.gr{background:#166534;color:#fff}.btn.wa{background:#25D366;color:#fff}
.kp{display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:8px;padding:12px}
.kp div{background:#fff;border-radius:10px;padding:10px 12px;box-shadow:0 1px 2px rgba(0,0,0,.06)}.kp span{display:block;font-size:12px;color:#5b6b7b}.kp b{font-size:19px}
.tabs{display:flex;gap:6px;padding:0 12px 8px;flex-wrap:wrap}.act{display:flex;flex-wrap:wrap;gap:8px;padding:0 12px 10px}
.wrap{padding:0 12px 20px}.card{background:#fff;border-radius:10px;box-shadow:0 1px 2px rgba(0,0,0,.06);overflow:auto}
table{border-collapse:collapse;width:100%;min-width:760px}th,td{padding:7px 8px;border-bottom:1px solid #e6ebf0;text-align:left;vertical-align:top;font-size:13px}
th{background:#F4F6F8;font-size:12px;color:#3d4b59;white-space:nowrap}td.n{white-space:nowrap}.mut{color:#6b7a89;font-size:12px}
.pill{display:inline-block;padding:2px 8px;border-radius:999px;font-size:11.5px;font-weight:600;white-space:nowrap}.g{background:#DCFCE7;color:#166534}.s{background:#E5E7EB;color:#374151}
.empty{padding:24px;text-align:center;color:#6b7a89}.msg{padding:30px 16px;text-align:center}
#toast{position:fixed;left:50%;bottom:18px;transform:translateX(-50%);background:#17202a;color:#fff;padding:10px 14px;border-radius:10px;font-size:13px;display:none;z-index:9}
/* slips (print) */
#print{display:none}
.slip{border:1.5px solid #17324D;border-radius:6px;padding:10px 14px;margin:0 0 6mm;page-break-inside:avoid;font-size:12.5px;line-height:1.45;color:#000;background:#fff}
.slip h2{margin:0;font-size:16px;text-align:center;color:#17324D}.slip .sub{text-align:center;font-size:11px;margin-bottom:4px}
.slip h3{margin:4px 0 6px;text-align:center;font-size:13px;background:#17324D;color:#fff;padding:3px;border-radius:3px;letter-spacing:.5px}
.slip table{min-width:0;margin:4px 0}.slip td,.slip th{font-size:12px;padding:3px 5px;border:1px solid #999}.slip th{background:#eee;color:#000}
.slip .tot{font-size:14px;font-weight:bold}.slip .row{display:flex;justify-content:space-between;gap:10px}
.slip .cut{border-top:1.5px dashed #555;margin-top:8px;padding-top:6px;font-size:11.5px}
@media print{body{background:#fff}#screen{display:none!important}#print{display:block}@page{size:A4;margin:8mm}}
</style></head><body>
<div id="screen"><main id="app"><div class="msg">Opening Fee Reminder Slip…</div></main></div>
<div id="print"></div><div id="toast"></div>
<script src="feeslip.js?v=<?= htmlspecialchars($v) ?>"></script>
</body></html>
