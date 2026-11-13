<?php
// Quick Fee — phone-first fee collection for the Manager app (owner, 13 Nov 2026: "manager app se fee lena thoda smart banaiye").
// Same receipts, same series and same rules as Fee Receipt (feecollect_api.php ?a=state / ledger / collect / today).
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; connect-src 'self'; frame-ancestors 'self'; base-uri 'none'; object-src 'none'");
$v = (string)@filemtime(__DIR__ . '/feequick.js');
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Quick Fee · SN Mishra SIS</title>
<style>
*{box-sizing:border-box}body{margin:0;font:15px/1.4 system-ui,-apple-system,Segoe UI,Roboto,sans-serif;background:#F3F7F4;color:#10261B}
#q{max-width:640px;margin:0 auto;padding:10px}
.card{background:#fff;border-radius:16px;padding:12px;margin-bottom:10px;box-shadow:0 1px 3px rgba(15,81,50,.08)}
h3{margin:0 0 6px;color:#0F5132;font-size:17px}.mut{color:#6b7b73;font-size:13px}.red{color:#b42318}.grn{color:#14532d}.org{color:#b45309}
input,select{font:inherit;padding:11px 12px;border:1px solid #cfdcd4;border-radius:12px;width:100%;background:#fff}
.btn{font:inherit;font-weight:700;border:0;border-radius:99px;padding:11px 16px;cursor:pointer;background:#E8F0EB;color:#0F5132}
.btn.go{background:#0F5132;color:#fff}.btn.y{background:#F5C842;color:#10261B}.btn.s{padding:7px 12px;font-size:13px}.btn.full{width:100%}.btn:disabled{opacity:.5}
.row{display:flex;gap:8px;align-items:center;flex-wrap:wrap}.row>*{flex:1}
.li{display:flex;justify-content:space-between;align-items:center;gap:8px;padding:10px 4px;border-bottom:1px solid #edf2ef;cursor:pointer}.li:last-child{border:0}.li b{font-size:15px}.li small{display:block;color:#6b7b73;font-size:12px}
.tag{border-radius:99px;padding:2px 9px;font-size:12px;font-weight:700;white-space:nowrap}.tag.r{background:#fee4e2;color:#b42318}.tag.g{background:#dcfce7;color:#14532d}.tag.n{background:#eef2f0;color:#6b7b73}
.kp{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin:8px 0}.kp div{background:#F3F7F4;border-radius:12px;padding:8px;text-align:center}.kp span{display:block;font-size:12px;color:#6b7b73}.kp b{font-size:16px}
.months{display:grid;grid-template-columns:repeat(4,1fr);gap:6px}
.mo{border:2px solid #e3ebe6;border-radius:12px;padding:7px 4px;text-align:center;cursor:pointer;background:#fff;font:inherit}.mo b{display:block;font-size:13px}.mo small{display:block;font-size:11px;color:#6b7b73}
.mo.paid{background:#f0fdf4;border-color:#bbf7d0;cursor:default}.mo.paid small{color:#14532d}.mo.due small{color:#b42318;font-weight:700}.mo.sel{background:#0F5132;border-color:#0F5132;color:#fff}.mo.sel small{color:#fff}.mo.now{box-shadow:0 0 0 2px #F5C842 inset}
.pay{position:sticky;bottom:0;background:#fff;border-radius:16px 16px 0 0;padding:12px;box-shadow:0 -4px 14px rgba(15,81,50,.12)}
.big{font-size:26px;font-weight:800;color:#0F5132}.modes{display:flex;gap:6px;flex-wrap:wrap}.modes button{flex:1;min-width:70px}.modes .on{background:#0F5132;color:#fff}
.alloc{font-size:13px;color:#33493d;background:#F3F7F4;border-radius:10px;padding:8px;margin:8px 0}
.ok{text-align:center}.ok .tick{font-size:54px}.hd{display:flex;gap:10px;align-items:center}.hd img{width:54px;height:54px;border-radius:50%;object-fit:cover}
.warn{background:#fff7e6;border:1px solid #fcd9a1;border-radius:12px;padding:10px;font-size:14px;margin:8px 0}
#toast{position:fixed;left:50%;bottom:90px;transform:translateX(-50%);background:#10261B;color:#fff;padding:10px 16px;border-radius:12px;font-size:14px;display:none;z-index:9;max-width:90%}
.sheet{position:fixed;inset:0;background:rgba(10,25,18,.55);display:flex;align-items:flex-end;justify-content:center;z-index:8}.sheet .in{background:#fff;width:100%;max-width:640px;max-height:90vh;overflow:auto;border-radius:20px 20px 0 0;padding:16px}
</style></head><body><main id="q"><div class="card">Opening Quick Fee…</div></main><div id="sh"></div><div id="toast" role="status" aria-live="polite"></div>
<script src="feequick.js?v=<?= htmlspecialchars($v) ?>"></script></body></html>
