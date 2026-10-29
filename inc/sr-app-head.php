<?php
// ============================================================
//  Smart Sarathi app mode — Maakit ke booking panne (ride.php,
//  trip.php, sewa.php, bazaar.php…) jab Sarathi app se khulein,
//  to Maakit ki website wali patti ki jagah app jaisa header.
//  Chalu: cookie sr_app=1 (smartsarathi/ghar.php lagata hai)
//  ya ?app=1. Band: ?app=0.
// ============================================================
$_live_order = null;
$_sr_title = trim(preg_replace('/\s*[—-]\s*Maakit\s*$/u', '', (string)$page_title));
?>
<style>
:root{--sr-navy:#0B1F3A;--sr-mint:#1FCB97;--sr-line:#E3E8EF;--sr-muted:#4B5A6B}
body.sr-app{background:#F4F6F9;padding-bottom:calc(76px + env(safe-area-inset-bottom))}
body.sr-app,body.sr-app input,body.sr-app select,body.sr-app textarea,body.sr-app button{font-family:'Noto Sans Devanagari','Plus Jakarta Sans',system-ui,sans-serif}
.sra-top{position:sticky;top:0;z-index:40;background:var(--sr-navy);color:#fff;display:flex;align-items:center;gap:10px;padding:10px 12px calc(10px);min-height:60px}
.sra-top a{color:#fff;text-decoration:none}
.sra-back{width:44px;height:44px;border-radius:50%;background:rgba(255,255,255,.12);display:grid;place-items:center;flex:none}
.sra-title{flex:1;min-width:0;font:800 18px/1.25 'Noto Sans Devanagari',sans-serif;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.sra-lang{display:flex;gap:2px;background:rgba(255,255,255,.12);border-radius:999px;padding:3px;flex:none}
.sra-lang a{padding:6px 10px;border-radius:999px;font:700 12px sans-serif;min-height:32px;display:inline-flex;align-items:center}
.sra-lang a[aria-current]{background:#fff;color:var(--sr-navy)}
body.sr-app .btn-brand,body.sr-app .btn-green,body.sr-app .mk-btn,body.sr-app button[type=submit]:not(.sra-plain){background:var(--sr-mint)!important;color:var(--sr-navy)!important;border-color:var(--sr-mint)!important}
body.sr-app a{color:var(--sr-navy)}
body.sr-app .footer,body.sr-app .tabbar,body.sr-app .mk-ann,body.sr-app .ticker,body.sr-app .mk-fest-strip{display:none!important}
.sra-nav{position:fixed;left:0;right:0;bottom:0;z-index:50;background:#fff;border-top:1px solid var(--sr-line);display:flex;justify-content:center;padding:6px 0 calc(8px + env(safe-area-inset-bottom))}
.sra-nav div{width:100%;max-width:520px;display:grid;grid-template-columns:repeat(3,minmax(0,1fr))}
.sra-nav a{display:flex;flex-direction:column;align-items:center;justify-content:center;gap:3px;min-height:48px;font:600 12px 'Noto Sans Devanagari',sans-serif;color:var(--sr-muted);text-decoration:none}
</style>
<header class="sra-top">
  <a class="sra-back" href="/smartsarathi/ghar.php" aria-label="<?= h(t('Back to home', 'होम पर वापस')) ?>"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.4" stroke-linecap="round" aria-hidden="true"><path d="M15 5l-7 7 7 7"/></svg></a>
  <span class="sra-title"><?= h($_sr_title !== '' ? $_sr_title : 'Smart Sarathi') ?></span>
  <span class="sra-lang" role="group" aria-label="Language / भाषा">
    <a href="<?= h(lang_url('hi')) ?>" rel="nofollow"<?= is_hi() ? ' aria-current="true"' : '' ?>>हिं</a>
    <a href="<?= h(lang_url('en')) ?>" rel="nofollow"<?= is_hi() ? '' : ' aria-current="true"' ?>>EN</a>
  </span>
</header>
