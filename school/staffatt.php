<?php
// Staff attendance — all staff, one month, many days at once or a CSV file (staffatt_api.php, staffatt.js).
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; connect-src 'self'; frame-ancestors 'self'; base-uri 'none'; object-src 'none'");
$v = (string)@filemtime(__DIR__ . '/staffatt.js');
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Staff Attendance · SN Mishra SIS</title>
<style>
*{box-sizing:border-box}body{margin:0;font-family:Arial,Helvetica,sans-serif;font-size:14px;color:#17202a;background:#EEF2F5}
.top{background:#17324D;color:#fff;padding:12px 14px;display:flex;flex-wrap:wrap;gap:8px;align-items:center;justify-content:space-between}.top b{font-size:17px}.top small{display:block;opacity:.8;font-size:12px}
.emb .top{display:none}
.bar{display:flex;flex-wrap:wrap;gap:6px;align-items:center;padding:10px 12px;background:#fff;border-bottom:1px solid #dde3ea;position:sticky;top:0;z-index:5}
.bar select,.bar button,.bar label.btn{font:inherit;font-size:13px;border:1px solid #c7d0da;background:#fff;border-radius:99px;padding:7px 12px;cursor:pointer}
.bar .go{background:#F2A93B;border-color:#F2A93B;color:#1b1b1b;font-weight:bold}.bar .go:disabled{opacity:.45}.bar input[type=file]{display:none}
.leg{display:flex;flex-wrap:wrap;gap:10px;padding:8px 12px;font-size:12px;color:#445}.leg i{display:inline-block;width:18px;height:18px;border-radius:5px;vertical-align:-4px;margin-right:4px;text-align:center;font-style:normal;font-weight:bold;line-height:18px;font-size:11px}
.wrap{overflow:auto;max-height:calc(100vh - 150px);margin:0 8px 10px;background:#fff;border-radius:14px;box-shadow:0 1px 3px rgba(0,0,0,.06)}
table{border-collapse:separate;border-spacing:0;font-size:12px}th,td{border-bottom:1px solid #eef1f4;border-right:1px solid #f1f3f6;text-align:center;padding:0}
thead th{position:sticky;top:0;background:#17324D;color:#fff;z-index:3;padding:5px 2px;min-width:30px;font-weight:normal;cursor:pointer}thead th small{display:block;font-size:9px;opacity:.75}
thead th.sun,thead th.hol{background:#5b6b7c;cursor:default}
th.nm,td.nm{position:sticky;left:0;background:#fff;z-index:2;text-align:left;padding:5px 8px;min-width:150px;max-width:190px}thead th.nm{z-index:4;background:#17324D}
td.nm b{display:block;font-size:13px}td.nm small{color:#667;font-size:11px}tr.grp td{background:#F6F0E4;font-weight:bold;text-align:left;padding:5px 8px;position:sticky;left:0}
td.c{width:30px;height:34px;cursor:pointer;font-weight:bold;user-select:none}td.c.P{color:#1d8f4e}td.c.A{background:#FDE2E1;color:#b42318}td.c.H{background:#FFF1CC;color:#8a5a00}td.c.L{background:#E3EEFF;color:#1d4ed8;cursor:not-allowed}
td.c.off{background:#f2f4f6;color:#b8c0c8;cursor:default}td.c.fut{background:#fafbfc;color:#d0d5da;cursor:default}td.c.ch{outline:2px solid #F2A93B;outline-offset:-2px}
td.tot{font-weight:bold;min-width:44px;padding:0 6px}td.tot.bad{color:#b42318}
.msg{padding:14px;text-align:center;color:#445}#toast{position:fixed;left:50%;bottom:18px;transform:translateX(-50%);background:#17202a;color:#fff;padding:10px 16px;border-radius:12px;font-size:13px;display:none;z-index:20;max-width:90vw}
.help{margin:0 12px 14px;font-size:12px;color:#556;line-height:1.5}
</style></head><body>
<div class="top"><div><b>Staff Attendance</b><small>All staff — teachers, drivers, Dai, watchman, office · tap to mark</small></div><a href="index.php" style="color:#fff">← ERP</a></div>
<div id="app"><div class="msg">Opening…</div></div><div id="toast"></div>
<script src="staffatt.js?v=<?= htmlspecialchars($v) ?>"></script></body></html>
