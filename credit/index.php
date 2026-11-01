<?php
declare(strict_types=1);
/**
 * MAAKIT Credit — education-first prototype.
 * This page does not offer, originate, approve, collect applications for, or disburse loans.
 * No personal or financial information is requested, transmitted, or stored.
 */
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('X-Frame-Options: DENY');
header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()');
header("Content-Security-Policy: default-src 'none'; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline'; img-src 'self' data:; manifest-src 'self'; worker-src 'self'; base-uri 'none'; form-action 'none'; frame-ancestors 'none'");
header('Cache-Control: no-cache');
?>
<!doctype html>
<html lang="hi">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="theme-color" content="#1c0d1f">
<meta name="description" content="MAAKIT Credit informational preview: calculate example EMI and learn how to check loan costs and safety. No loans or applications are offered.">
<meta name="robots" content="noindex,nofollow">
<link rel="manifest" href="./manifest.webmanifest">
<title>MAAKIT Credit — Smart Borrowing Preview</title>
<style>
:root{--bg:#fff7f1;--white:#fff;--ink:#29152d;--muted:#716576;--plum:#1c0d1f;--pink:#d6246e;--line:#f0e3e8;--gold:#ffb020;--green:#177a5d;--shadow:0 15px 35px rgba(36,11,33,.065);font-family:'Noto Sans Devanagari','Plus Jakarta Sans','Mukta',Arial,sans-serif;color:var(--ink);background:var(--bg)}
*{box-sizing:border-box}
html{scroll-behavior:smooth;scroll-padding-top:25px}
body{margin:0;min-width:300px}
button,input,select{font:inherit}
button,a{-webkit-tap-highlight-color:transparent}
button{cursor:pointer}
a{color:inherit}
.shell{max-width:520px;min-height:100svh;margin:auto;padding:16px 16px calc(108px + env(safe-area-inset-bottom));background:var(--bg);position:relative}
.top{display:flex;align-items:center;justify-content:space-between;gap:10px;margin:4px 0 16px}
.brand{display:flex;align-items:center;gap:10px;text-decoration:none}
.brand-icon{height:43px;width:43px;display:grid;place-items:center;background:var(--plum);color:white;border-radius:15px;font-family:Georgia,serif;font-size:26px;font-weight:bold;box-shadow:inset -6px -6px 0 rgba(214,36,110,.32);position:relative}
.brand-icon:after{content:"";position:absolute;width:8px;height:8px;right:5px;top:5px;border-radius:50%;background:var(--gold)}
.brand strong{display:block;font-size:18px;line-height:1.2;letter-spacing:-.35px}
.brand strong span{color:var(--pink)}
.brand small{display:block;font-size:10px;letter-spacing:.7px;color:var(--muted);font-weight:700}
.lang{min-width:73px;min-height:41px;background:white;border:1px solid var(--line);border-radius:16px;color:var(--ink);font-size:13px;font-weight:750;box-shadow:var(--shadow)}
.notice{display:flex;align-items:center;gap:9px;padding:11px 13px;background:#fff0e3;border:1px solid #f4deca;border-radius:15px;font-size:12px;line-height:1.5;font-weight:650;color:#784c20;margin-bottom:13px}
.notice svg{flex-shrink:0}
.hero{background:var(--plum);border-radius:31px;padding:24px 23px 23px;color:white;overflow:hidden;position:relative}
.hero:after{content:"";position:absolute;right:-67px;top:-65px;border:1px solid rgba(255,176,32,.24);height:248px;width:248px;border-radius:50%;pointer-events:none}
.hero:before{content:"";position:absolute;right:-27px;top:-25px;border:1px solid rgba(255,176,32,.17);height:164px;width:164px;border-radius:50%;pointer-events:none}
.hero-label{display:inline-flex;align-items:center;gap:7px;color:#ffd595;border:1px solid rgba(255,176,32,.32);border-radius:99px;padding:6px 10px;font-size:11px;font-weight:700}
.hero h1{font-size:clamp(26px,7.6vw,35px);letter-spacing:-.6px;line-height:1.35;margin:22px 0 11px;max-width:340px;font-weight:800;position:relative;z-index:1}
.hero p{font-size:14px;line-height:1.7;color:#ebdce9;margin:0;max-width:290px;position:relative;z-index:1}
.hero-actions{display:flex;align-items:center;gap:9px;margin-top:21px;flex-wrap:wrap;position:relative;z-index:1}
.cta{display:inline-flex;align-items:center;justify-content:center;min-height:47px;padding:0 17px;background:var(--pink);color:white;border-radius:14px;text-decoration:none;border:0;font-size:13px;font-weight:750}
.cta.secondary{background:rgba(255,255,255,.09);border:1px solid rgba(255,255,255,.22)}
.hero-art{position:absolute;right:14px;bottom:66px;width:87px;height:87px;border-radius:25px;transform:rotate(12deg);background:#ffb020;color:var(--plum);display:grid;place-items:center;font-size:42px;font-weight:800;opacity:.95;box-shadow:12px 12px 0 rgba(214,36,110,.24);pointer-events:none}
.section-title{display:flex;justify-content:space-between;align-items:center;gap:10px;margin:25px 3px 13px}
.section-title h2{font-size:20px;margin:0;line-height:1.3;letter-spacing:-.3px}
.section-title .eyebrow{font-size:11px;color:var(--pink);font-weight:750}
.section-title p{font-size:12px;color:var(--muted);margin:3px 0 0}
.product-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:11px}
.product-card{border:1px solid var(--line);background:white;border-radius:21px;padding:17px 14px;min-height:145px;box-shadow:var(--shadow)}
.product-icon{height:41px;width:41px;border-radius:15px;display:grid;place-items:center;margin-bottom:13px}
.product-card:nth-child(1) .product-icon{background:#fde6ed;color:#b92264}
.product-card:nth-child(2) .product-icon{background:#e7f4eb;color:#298455}
.product-card:nth-child(3) .product-icon{background:#fff0d7;color:#9d6417}
.product-card:nth-child(4) .product-icon{background:#e9ecff;color:#5658ad}
.product-card h3{font-size:14px;line-height:1.35;margin:0 0 5px;font-weight:750}
.product-card p{font-size:11px;color:var(--muted);line-height:1.5;margin:0}
.card{background:white;border:1px solid var(--line);border-radius:25px;padding:20px 18px;box-shadow:var(--shadow)}
.calc-head{display:flex;align-items:flex-start;justify-content:space-between;gap:8px}
.calc-head h2{margin:0 0 4px;font-size:21px}
.calc-head p{font-size:12px;color:var(--muted);margin:0}
.tag{background:#fff1dd;color:#915b0b;font-size:10px;font-weight:800;padding:7px 9px;border-radius:30px;white-space:nowrap}
.field{margin-top:19px}
.field-top{display:flex;justify-content:space-between;align-items:baseline;gap:8px;margin-bottom:8px}
.field label{font-size:13px;font-weight:750}
.field .sub{font-size:11px;color:var(--muted)}
.amount-wrap{display:flex;align-items:center;gap:5px;border:1.5px solid #efd7e2;background:#fff9fc;border-radius:15px;padding:0 14px}
.amount-wrap:focus-within{border-color:var(--pink);outline:2px solid #f7dae7}
.amount-wrap span{font-size:23px;font-weight:800;color:var(--pink)}
.amount-wrap input{width:100%;font-size:24px;font-weight:800;color:var(--ink);border:0;outline:0;min-height:52px;background:transparent;min-width:0}
input[type=range]{width:100%;accent-color:var(--pink);margin:16px 0 5px;height:20px}
.range-ends{display:flex;justify-content:space-between;font-size:11px;color:var(--muted)}
.chips{display:flex;gap:8px;flex-wrap:wrap;margin-top:13px}
.chip{padding:8px 11px;border-radius:12px;border:1px solid #eed9e3;background:#fff8fa;color:#8c315b;font-size:12px;font-weight:750;min-height:40px}
.chip[aria-pressed=true]{background:var(--pink);color:white;border-color:var(--pink)}
.two-fields{display:grid;grid-template-columns:1fr 1fr;gap:10px}
.small-input{width:100%;border:1px solid #e4d7e0;border-radius:14px;min-height:46px;padding:9px 11px;background:white;color:var(--ink);outline-color:var(--pink);font-size:16px}
.term-grid{display:grid;grid-template-columns:repeat(6,1fr);gap:6px}
.term-grid button{min-height:40px;padding:4px 1px;border-radius:11px;border:1px solid #e8dae2;background:white;color:var(--ink);font-size:12px;font-weight:750}
.term-grid button[aria-pressed=true]{background:var(--plum);color:white;border-color:var(--plum)}
.output{background:#faf1f7;border:1px solid #f0dce8;border-radius:20px;margin-top:20px;padding:18px}
.output .label{font-size:12px;color:#6d4960;font-weight:700}
.output .emi{font-size:36px;font-weight:850;line-height:1.3;color:var(--pink);letter-spacing:-1.1px;margin:4px 0 0}
.output .per{color:var(--muted);font-size:12px}
.split{height:9px;background:#ead7e3;border-radius:30px;display:flex;overflow:hidden;margin-top:17px}
.split .principal{background:#d6246e;height:100%;transition:width .2s}
.split .interest{background:#ffb020;height:100%;flex:1}
.stats{display:grid;grid-template-columns:1fr 1fr;gap:13px;border-top:1px solid #ebdae4;margin-top:16px;padding-top:15px}
.stat small{display:block;color:var(--muted);font-size:11px;line-height:1.6}
.stat strong{display:block;font-size:15px;line-height:1.5;font-weight:800;overflow-wrap:anywhere}
.stat.full{grid-column:span 2;border-top:1px dashed #e7d7e2;padding-top:11px}
.warning{display:flex;align-items:flex-start;gap:8px;margin-top:13px;padding:11px;background:#fff6e9;border-radius:13px;color:#87591e;font-size:11px;line-height:1.6}
.warning svg{flex-shrink:0;margin-top:2px}
.share{width:100%;margin-top:14px;background:var(--plum);color:white;border:0;border-radius:14px;min-height:46px;font-weight:750;font-size:13px}
.status{min-height:20px;font-size:12px;color:var(--green);margin:6px 0 0}
.steps{display:grid;gap:11px}
.step{display:flex;gap:12px;background:white;border:1px solid var(--line);padding:16px;border-radius:18px}
.step .n{display:grid;place-items:center;flex-shrink:0;width:35px;height:35px;background:#fae6ed;color:var(--pink);border-radius:12px;font-size:14px;font-weight:850}
.step h3{font-size:14px;margin:1px 0 5px}
.step p{font-size:12px;color:var(--muted);line-height:1.65;margin:0}
.safe{background:#eaf5ee;border:1px solid #d1e7da;border-radius:24px;padding:19px;margin-top:17px}
.safe h2{margin:0 0 10px;font-size:18px;color:#14593e}
.safe p{margin:0;font-size:12px;line-height:1.7;color:#315b45}
.safe ul{padding-left:18px;margin:11px 0 0;font-size:12px;color:#315b45;line-height:1.8}
.faq{margin-top:12px}
.faq details{border-bottom:1px solid var(--line);padding:13px 0}
.faq details:last-child{border-bottom:0}
.faq summary{cursor:pointer;font-size:13px;font-weight:750;line-height:1.55;padding-right:10px}
.faq details p{font-size:12px;color:var(--muted);line-height:1.7;margin:8px 0 0}
.footer{font-size:11px;color:var(--muted);line-height:1.65;text-align:center;margin:29px 12px 0}
.footer strong{display:block;color:var(--ink);font-size:13px;margin-bottom:5px}
.bottom{position:fixed;z-index:10;left:50%;transform:translateX(-50%);bottom:0;width:min(520px,100%);display:grid;grid-template-columns:repeat(4,minmax(0,1fr));background:rgba(255,255,255,.97);border-top:1px solid #ebdfe7;box-shadow:0 -4px 25px rgba(39,18,42,.07);padding:9px 8px calc(9px + env(safe-area-inset-bottom));border-radius:19px 19px 0 0}
.bottom a{text-align:center;color:#726975;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:3px;min-height:52px;font-size:10px;text-decoration:none;font-weight:700;border-radius:12px}
.bottom a.active{color:var(--pink);background:#fdf0f5}
.bottom a svg{height:21px;width:21px}
@media(min-width:700px){body{background:#f3e9ed}.shell{box-shadow:0 0 60px rgba(39,18,42,.06)}}
@media(max-width:365px){.hero-art{opacity:.28}.hero h1{font-size:26px}.term-grid{gap:4px}.term-grid button{font-size:11px}.two-fields{gap:7px}.card{padding:16px 13px}.shell{padding-left:11px;padding-right:11px}}
@media(prefers-reduced-motion:reduce){html{scroll-behavior:auto}*{transition:none!important}}
</style>
</head>
<body>
<main class="shell" id="home">
<header class="top">
  <a class="brand" href="#home" aria-label="MAAKIT Credit">
    <span class="brand-icon" aria-hidden="true">M</span>
    <span><strong>MAAKIT <span>Credit</span></strong><small data-i18n="tagline">SMART BORROWING</small></span>
  </a>
  <button class="lang" id="language" type="button" aria-label="Switch language to English">EN / हिंदी</button>
</header>
<div class="notice" role="note">
  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 7v5m0 4h.01"/></svg>
  <span data-i18n="notice">यह सिर्फ प्रोडक्ट डेमो है — अभी लोन आवेदन या मंजूरी उपलब्ध नहीं है।</span>
</div>
<section class="hero" aria-labelledby="hero-title">
  <span class="hero-label"><span aria-hidden="true">✦</span> <span data-i18n="heroLabel">पैसों की सही जानकारी, पहले</span></span>
  <h1 id="hero-title" data-i18n="heroTitle">लोन समझिए।<br>फिर फैसला कीजिए।</h1>
  <p data-i18n="heroDesc">EMI, ब्याज और कुल खर्च एक जगह समझें — बिना कोई निजी जानकारी दिए।</p>
  <div class="hero-art" aria-hidden="true">₹</div>
  <div class="hero-actions">
    <a href="#calculator" class="cta" data-i18n="calcNow">EMI निकालें →</a>
    <a href="#guide" class="cta secondary" data-i18n="learnMore">पहले जानें</a>
  </div>
</section>
<section id="products" aria-labelledby="products-title">
  <div class="section-title">
    <div><h2 id="products-title" data-i18n="productsTitle">लोन की जानकारी</h2><p data-i18n="productsDesc">जानने के लिए — आवेदन अभी बंद है</p></div>
    <span class="eyebrow" data-i18n="comingSoon">COMING LATER</span>
  </div>
  <div class="product-grid">
    <article class="product-card"><div class="product-icon" aria-hidden="true"><svg width="23" height="23" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="7" r="4"/><path d="M4 21v-2a8 8 0 0 1 16 0v2"/></svg></div><h3 data-i18n="personal">पर्सनल लोन</h3><p data-i18n="personalDesc">EMI और कुल लागत समझिए</p></article>
    <article class="product-card"><div class="product-icon" aria-hidden="true"><svg width="23" height="23" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 21V8l9-5 9 5v13M3 11h18M8 21v-7h8v7"/></svg></div><h3 data-i18n="business">बिजनेस लोन</h3><p data-i18n="businessDesc">कारोबार के लिए लोन के सवाल</p></article>
    <article class="product-card"><div class="product-icon" aria-hidden="true"><svg width="23" height="23" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="6" cy="18" r="3"/><circle cx="18" cy="18" r="3"/><path d="m9 18 3-8 3 8M12 10h5l2 5M9 10H6"/></svg></div><h3 data-i18n="vehicle">वाहन फाइनेंस</h3><p data-i18n="vehicleDesc">डाउन पेमेंट और EMI के उदाहरण</p></article>
    <article class="product-card"><div class="product-icon" aria-hidden="true"><svg width="23" height="23" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="5" width="18" height="14" rx="3"/><path d="M3 10h18m-14 5h5"/></svg></div><h3 data-i18n="creditLine">क्रेडिट लाइन</h3><p data-i18n="creditLineDesc">ब्याज और शुल्क कैसे देखें</p></article>
  </div>
</section>
<section id="calculator" aria-labelledby="calc-title">
  <div class="section-title"><div><h2 id="calc-title" data-i18n="calcTitle">EMI कैलकुलेटर</h2><p data-i18n="calcDesc">अपनी गणना करें, कोई फॉर्म नहीं</p></div></div>
  <div class="card">
    <div class="calc-head"><div><h2 data-i18n="estimateTitle">कितनी EMI बनेगी?</h2><p data-i18n="estimateDesc">आपके चुने हुए उदाहरण पर आधारित</p></div><span class="tag" data-i18n="demo">DEMO</span></div>
    <div class="field">
      <div class="field-top"><label for="amount" data-i18n="loanAmount">उदाहरण लोन राशि</label><span class="sub" data-i18n="amountHint">₹3,000 – ₹5,00,000</span></div>
      <div class="amount-wrap"><span aria-hidden="true">₹</span><input id="amount" type="number" inputmode="numeric" min="3000" max="500000" step="1000" value="25000" autocomplete="off"></div>
      <input aria-label="Loan amount slider" id="amountRange" type="range" min="3000" max="500000" step="1000" value="25000">
      <div class="range-ends"><span>₹3,000</span><span>₹5,00,000</span></div>
      <div class="chips" role="group" aria-label="Quick example amounts">
        <button class="chip" type="button" data-amount="10000" aria-pressed="false">₹10k</button>
        <button class="chip" type="button" data-amount="25000" aria-pressed="true">₹25k</button>
        <button class="chip" type="button" data-amount="50000" aria-pressed="false">₹50k</button>
        <button class="chip" type="button" data-amount="100000" aria-pressed="false">₹1L</button>
      </div>
    </div>
    <div class="two-fields">
      <div class="field"><div class="field-top"><label for="rate" data-i18n="interest">सालाना ब्याज (%)</label></div><input class="small-input" id="rate" type="number" inputmode="decimal" min="0" max="60" step=".5" value="18" autocomplete="off"></div>
      <div class="field"><div class="field-top"><label for="fee" data-i18n="fee">प्रोसेसिंग शुल्क (%)</label></div><input class="small-input" id="fee" type="number" inputmode="decimal" min="0" max="10" step=".5" value="0" autocomplete="off"></div>
    </div>
    <div class="field"><div class="field-top"><label data-i18n="tenure">अवधि (महीने)</label><span class="sub" id="tenureText">12 months</span></div>
      <div class="term-grid" role="group" aria-label="Loan tenure in months">
        <button type="button" data-months="6" aria-pressed="false">6</button>
        <button type="button" data-months="12" aria-pressed="true">12</button>
        <button type="button" data-months="18" aria-pressed="false">18</button>
        <button type="button" data-months="24" aria-pressed="false">24</button>
        <button type="button" data-months="36" aria-pressed="false">36</button>
        <button type="button" data-months="60" aria-pressed="false">60</button>
      </div>
    </div>
    <div class="output" aria-live="polite" aria-atomic="true">
      <div class="label" data-i18n="monthly">अनुमानित मासिक EMI</div>
      <div class="emi" id="emi">—</div><div class="per" data-i18n="month">हर महीने</div>
      <div class="split" aria-hidden="true"><span id="principalBar" class="principal" style="width:80%"></span><span class="interest"></span></div>
      <div class="stats">
        <div class="stat"><small data-i18n="principal">मूल राशि</small><strong id="principal">—</strong></div>
        <div class="stat"><small data-i18n="totalInterest">कुल ब्याज</small><strong id="totalInterest">—</strong></div>
        <div class="stat"><small data-i18n="processing">उदाहरण प्रोसेसिंग शुल्क</small><strong id="processing">—</strong></div>
        <div class="stat"><small data-i18n="repayment">कुल EMI भुगतान</small><strong id="repayment">—</strong></div>
        <div class="stat full"><small data-i18n="totalCost">कुल उदाहरण खर्च (EMI + शुल्क, टैक्स छोड़कर)</small><strong id="totalCost">—</strong></div>
      </div>
    </div>
    <div class="warning"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 7v5m0 4h.01"/></svg><span data-i18n="calcNote">ये दरें आपकी खुद की उदाहरण दरें हैं, किसी lender का offer या APR नहीं। वास्तविक APR में अन्य लागतें हो सकती हैं; GST/अन्य शुल्क यहाँ नहीं जोड़े गए हैं।</span></div>
    <button type="button" class="share" id="share" data-i18n="share">↗ यह गणना शेयर करें</button><p class="status" id="shareStatus" role="status"></p>
  </div>
</section>
<section id="guide" aria-labelledby="guide-title">
  <div class="section-title"><div><h2 id="guide-title" data-i18n="guideTitle">लोन से पहले 3 बातें</h2><p data-i18n="guideDesc">समझदार फैसले की आसान checklist</p></div></div>
  <div class="steps">
    <article class="step"><span class="n">01</span><div><h3 data-i18n="stepOne">APR और कुल खर्च देखें</h3><p data-i18n="stepOneDesc">कम EMI हमेशा सस्ता लोन नहीं होती। ब्याज, शुल्क और पूरे भुगतान की तुलना करें।</p></div></article>
    <article class="step"><span class="n">02</span><div><h3 data-i18n="stepTwo">KFS ज़रूर पढ़ें</h3><p data-i18n="stepTwoDesc">लेंडर का Key Facts Statement दर, शुल्क और जरूरी शर्तें बताता है।</p></div></article>
    <article class="step"><span class="n">03</span><div><h3 data-i18n="stepThree">लेंडर की पहचान जाँचें</h3><p data-i18n="stepThreeDesc">बैंक/NBFC का नाम, अधिकृत संपर्क और शिकायत व्यवस्था पहचानें।</p></div></article>
  </div>
  <div class="safe" id="safety">
    <h2 data-i18n="safetyTitle">🔐 आपकी सुरक्षा पहले</h2>
    <p data-i18n="safetyDesc">इस डेमो में न OTP है, न KYC, न बैंक जानकारी, न भुगतान। आपकी EMI गणना फोन के ब्राउज़र में होती है; हम इसे सर्वर पर नहीं भेजते।</p>
    <ul><li data-i18n="safetyOne">किसी को अग्रिम loan-approval fee न दें।</li><li data-i18n="safetyTwo">PAN, Aadhaar और बैंक डेटा अनजान ऐप पर न भरें।</li><li data-i18n="safetyThree">ऋणदाता की पहचान और शर्तें जाँचें।</li></ul>
  </div>
</section>
<section id="faq" aria-labelledby="faq-title">
  <div class="section-title"><div><h2 id="faq-title" data-i18n="faqTitle">अक्सर पूछे गए सवाल</h2></div></div>
  <div class="card faq">
    <details><summary data-i18n="q1">क्या MAAKIT Credit अभी लोन देता है?</summary><p data-i18n="a1">नहीं। यह जानकारी और EMI गणना के लिए डेमो है। न आवेदन लिया जाता है, न approval या disbursement होता है।</p></details>
    <details><summary data-i18n="q2">EMI की गणना कैसे होती है?</summary><p data-i18n="a2">यह reducing-balance EMI के गणितीय सूत्र से निकलती है। असली EMI और APR lender की KFS के अनुसार अलग हो सकती है।</p></details>
    <details><summary data-i18n="q3">क्या मुझे अपनी जानकारी देनी होगी?</summary><p data-i18n="a3">नहीं। इस डेमो को चलाने के लिए नंबर, दस्तावेज या login की जरूरत नहीं है।</p></details>
    <details><summary data-i18n="q4">भविष्य में लोन कैसे मिलेगा?</summary><p data-i18n="a4">जब तक regulated बैंक/NBFC के साथ वैध partnership और जरूरी disclosures नहीं बनते, यहाँ आवेदन शुरू नहीं होगा।</p></details>
  </div>
</section>
<footer class="footer"><strong>MAAKIT Credit · <span data-i18n="preview">Product Preview</span></strong><span data-i18n="footer">स्वतंत्र सूचना-आधारित डेमो। MAAKIT खुद को RBI-पंजीकृत ऋणदाता नहीं बताता। यह कोई वास्तविक loan offer नहीं है।</span></footer>
</main>
<nav class="bottom" aria-label="App navigation">
  <a href="#home" class="active"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m3 10 9-7 9 7v11H3z"/><path d="M9 21v-7h6v7"/></svg><span data-i18n="navHome">होम</span></a>
  <a href="#calculator"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="4" y="2" width="16" height="20" rx="3"/><path d="M8 7h8M8 12h2m4 0h2M8 17h2m4 0h2"/></svg><span data-i18n="navCalc">EMI</span></a>
  <a href="#guide"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M12 6c-3-2-6-2-9-1v15c3-1 6-1 9 1 3-2 6-2 9-1V5c-3-1-6-1-9 1zm0 0v15"/></svg><span data-i18n="navGuide">सीखें</span></a>
  <a href="#safety"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M12 2 4 6v6c0 5 3.5 8.3 8 10 4.5-1.7 8-5 8-10V6z"/><path d="m9 12 2 2 4-4"/></svg><span data-i18n="navSafety">सुरक्षा</span></a>
</nav>
<script>
(function(){
'use strict';
const en={
tagline:'SMART BORROWING',notice:'Informational demo only — loan applications and approvals are not available.',
heroLabel:'Clarity before borrowing',heroTitle:'Understand loans.<br>Then decide.',heroDesc:'Explore EMIs, interest and total costs without sharing personal details.',
calcNow:'Calculate EMI →',learnMore:'Learn first',productsTitle:'Explore loan types',productsDesc:'For learning only — applications not open',comingSoon:'NOT AVAILABLE',
personal:'Personal loans',personalDesc:'Understand EMIs and full costs',business:'Business loans',businessDesc:'Know what to compare',vehicle:'Vehicle finance',vehicleDesc:'Learn about down payments & EMI',creditLine:'Credit lines',creditLineDesc:'See how charges can work',
calcTitle:'EMI calculator',calcDesc:'Calculate without signing up',estimateTitle:'What could the EMI be?',estimateDesc:'Based on your example inputs',demo:'EXAMPLE',
loanAmount:'Example loan amount',amountHint:'₹3,000 – ₹5,00,000',interest:'Annual interest (%)',fee:'Processing fee (%)',tenure:'Tenure (months)',
monthly:'Estimated monthly EMI',month:'per month',principal:'Principal',totalInterest:'Total interest',processing:'Example processing fee',repayment:'Total EMI repayments',totalCost:'Example total outflow (EMIs + fee, before taxes)',
calcNote:'These are your own example rates, not a lender offer or the all-inclusive APR. Actual APR may include other costs; GST and other charges are excluded here.',share:'↗ Share this estimate',
guideTitle:'3 things to check',guideDesc:'A simple checklist for safer decisions',stepOne:'Compare APR and overall cost',stepOneDesc:'A low EMI may not mean a cheaper loan. Compare interest, fees and total repayment.',stepTwo:'Read the KFS',stepTwoDesc:'A lender’s Key Facts Statement explains rates, charges and important terms.',stepThree:'Verify the lender',stepThreeDesc:'Check the bank/NBFC name, official contact and grievance process.',
safetyTitle:'🔐 Your safety first',safetyDesc:'No OTP, KYC, bank details or payments in this demo. Your calculation runs in the browser and is not sent to our server.',safetyOne:'Never pay an unknown person an advance loan-approval fee.',safetyTwo:'Do not enter PAN, Aadhaar or bank details in unverified apps.',safetyThree:'Verify the actual lender and loan terms.',
faqTitle:'Frequently asked questions',q1:'Does MAAKIT Credit offer loans today?',a1:'No. This is an informational EMI prototype. We do not take applications, approve or disburse loans.',q2:'How is EMI calculated?',a2:'Using the reducing-balance EMI formula. The final EMI and APR depend on the actual lender’s KFS.',q3:'Do I need to share any personal data?',a3:'No. You do not need a phone number, documents or login to try this demo.',q4:'How would loans be offered in the future?',a4:'Applications will remain unavailable until a valid regulated bank/NBFC arrangement and necessary disclosures are in place.',
preview:'Product Preview',footer:'Independent informational demo. MAAKIT does not claim to be an RBI-registered lender. This is not a loan offer.',navHome:'Home',navCalc:'EMI',navGuide:'Learn',navSafety:'Safety',copied:'Estimate copied',shareFailed:'Sharing unavailable. Please copy from your browser.',invalid:'Please check amount and rates.',months:'months',shareNote:'Illustrative only; not a lender offer or APR.'
};
const hi={tagline:'SMART BORROWING',notice:'यह सिर्फ प्रोडक्ट डेमो है — अभी लोन आवेदन या मंजूरी उपलब्ध नहीं है।',heroLabel:'पैसों की सही जानकारी, पहले',heroTitle:'लोन समझिए।<br>फिर फैसला कीजिए।',heroDesc:'EMI, ब्याज और कुल खर्च एक जगह समझें — बिना कोई निजी जानकारी दिए।',calcNow:'EMI निकालें →',learnMore:'पहले जानें',productsTitle:'लोन की जानकारी',productsDesc:'जानने के लिए — आवेदन अभी बंद है',comingSoon:'अभी उपलब्ध नहीं',personal:'पर्सनल लोन',personalDesc:'EMI और कुल लागत समझिए',business:'बिजनेस लोन',businessDesc:'कारोबार के लिए लोन के सवाल',vehicle:'वाहन फाइनेंस',vehicleDesc:'डाउन पेमेंट और EMI के उदाहरण',creditLine:'क्रेडिट लाइन',creditLineDesc:'ब्याज और शुल्क कैसे देखें',calcTitle:'EMI कैलकुलेटर',calcDesc:'अपनी गणना करें, कोई फॉर्म नहीं',estimateTitle:'कितनी EMI बनेगी?',estimateDesc:'आपके चुने हुए उदाहरण पर आधारित',demo:'उदाहरण',loanAmount:'उदाहरण लोन राशि',amountHint:'₹3,000 – ₹5,00,000',interest:'सालाना ब्याज (%)',fee:'प्रोसेसिंग शुल्क (%)',tenure:'अवधि (महीने)',monthly:'अनुमानित मासिक EMI',month:'हर महीने',principal:'मूल राशि',totalInterest:'कुल ब्याज',processing:'उदाहरण प्रोसेसिंग शुल्क',repayment:'कुल EMI भुगतान',totalCost:'कुल उदाहरण खर्च (EMI + शुल्क, टैक्स छोड़कर)',calcNote:'ये दरें आपकी खुद की उदाहरण दरें हैं, किसी lender का offer या APR नहीं। वास्तविक APR में अन्य लागतें हो सकती हैं; GST/अन्य शुल्क यहाँ नहीं जोड़े गए हैं।',share:'↗ यह गणना शेयर करें',guideTitle:'लोन से पहले 3 बातें',guideDesc:'समझदार फैसले की आसान checklist',stepOne:'APR और कुल खर्च देखें',stepOneDesc:'कम EMI हमेशा सस्ता लोन नहीं होती। ब्याज, शुल्क और पूरे भुगतान की तुलना करें।',stepTwo:'KFS ज़रूर पढ़ें',stepTwoDesc:'लेंडर का Key Facts Statement दर, शुल्क और जरूरी शर्तें बताता है।',stepThree:'लेंडर की पहचान जाँचें',stepThreeDesc:'बैंक/NBFC का नाम, अधिकृत संपर्क और शिकायत व्यवस्था पहचानें।',safetyTitle:'🔐 आपकी सुरक्षा पहले',safetyDesc:'इस डेमो में न OTP है, न KYC, न बैंक जानकारी, न भुगतान। आपकी EMI गणना फोन के ब्राउज़र में होती है; हम इसे सर्वर पर नहीं भेजते।',safetyOne:'किसी को अग्रिम loan-approval fee न दें।',safetyTwo:'PAN, Aadhaar और बैंक डेटा अनजान ऐप पर न भरें।',safetyThree:'ऋणदाता की पहचान और शर्तें जाँचें।',faqTitle:'अक्सर पूछे गए सवाल',q1:'क्या MAAKIT Credit अभी लोन देता है?',a1:'नहीं। यह जानकारी और EMI गणना के लिए डेमो है। न आवेदन लिया जाता है, न approval या disbursement होता है।',q2:'EMI की गणना कैसे होती है?',a2:'यह reducing-balance EMI के गणितीय सूत्र से निकलती है। असली EMI और APR lender की KFS के अनुसार अलग हो सकती है।',q3:'क्या मुझे अपनी जानकारी देनी होगी?',a3:'नहीं। इस डेमो को चलाने के लिए नंबर, दस्तावेज या login की जरूरत नहीं है।',q4:'भविष्य में लोन कैसे मिलेगा?',a4:'जब तक regulated बैंक/NBFC के साथ वैध partnership और जरूरी disclosures नहीं बनते, यहाँ आवेदन शुरू नहीं होगा।',preview:'Product Preview',footer:'स्वतंत्र सूचना-आधारित डेमो। MAAKIT खुद को RBI-पंजीकृत ऋणदाता नहीं बताता। यह कोई वास्तविक loan offer नहीं है।',navHome:'होम',navCalc:'EMI',navGuide:'सीखें',navSafety:'सुरक्षा',copied:'गणना कॉपी हो गई',shareFailed:'शेयर नहीं हुआ। ब्राउज़र से कॉपी करें।',invalid:'राशि और दर जाँचें।',months:'महीने',shareNote:'केवल उदाहरण; lender offer या APR नहीं।'};
let language='hi',term=12,last=null;
const amount=document.getElementById('amount'),range=document.getElementById('amountRange'),rate=document.getElementById('rate'),fee=document.getElementById('fee');
const rupees=v=>'₹'+Math.round(v).toLocaleString('en-IN');
function calculate(){
  const p=Number(amount.value),r=Number(rate.value),f=Number(fee.value),n=term;
  const valid=amount.value.trim()!==''&&rate.value.trim()!==''&&fee.value.trim()!==''&&Number.isFinite(p)&&p>=3000&&p<=500000&&Number.isFinite(r)&&r>=0&&r<=60&&Number.isFinite(f)&&f>=0&&f<=10&&Number.isSafeInteger(n)&&n>0;
  const fields=['emi','principal','totalInterest','processing','repayment','totalCost'];
  if(!valid){fields.forEach(id=>document.getElementById(id).textContent='—');document.getElementById('emi').textContent=(language==='en'?en:hi).invalid;document.getElementById('principalBar').style.width='0%';last=null;return}
  const monthlyRate=r/1200;
  const e=monthlyRate===0?p/n:p*monthlyRate/(1-Math.pow(1+monthlyRate,-n));
  const total=e*n,interest=Math.max(0,total-p),feeTotal=p*f/100;
  last={p,r,f,n,e,total,interest,feeTotal};
  const output={emi:e,principal:p,totalInterest:interest,processing:feeTotal,repayment:total,totalCost:total+feeTotal};
  Object.entries(output).forEach(([id,value])=>document.getElementById(id).textContent=rupees(value));
  document.getElementById('principalBar').style.width=(100*p/(total+feeTotal)).toFixed(1)+'%';
}
function updatePressed(){
  document.querySelectorAll('[data-months]').forEach(b=>b.setAttribute('aria-pressed',String(Number(b.dataset.months)===term)));
  document.querySelectorAll('[data-amount]').forEach(b=>b.setAttribute('aria-pressed',String(Number(b.dataset.amount)===Number(amount.value))));
  document.getElementById('tenureText').textContent=term+' '+(language==='en'?en:hi).months;
}
function setLanguage(l){
  language=l;document.documentElement.lang=l;
  const dict=l==='en'?en:hi;
  document.querySelectorAll('[data-i18n]').forEach(el=>{const v=dict[el.dataset.i18n];if(v!==undefined){if(el.dataset.i18n==='heroTitle')el.innerHTML=v;else el.textContent=v}});
  const button=document.getElementById('language');
  button.textContent=l==='hi'?'EN / हिंदी':'हिंदी / EN';
  button.setAttribute('aria-label',l==='hi'?'Switch language to English':'हिंदी में बदलें');
  updatePressed();calculate();
}
document.getElementById('language').addEventListener('click',()=>setLanguage(language==='hi'?'en':'hi'));
document.querySelectorAll('[data-amount]').forEach(b=>b.addEventListener('click',()=>{amount.value=b.dataset.amount;range.value=b.dataset.amount;updatePressed();calculate()}));
document.querySelectorAll('[data-months]').forEach(b=>b.addEventListener('click',()=>{term=Number(b.dataset.months);updatePressed();calculate()}));
amount.addEventListener('input',()=>{const x=Number(amount.value);if(Number.isFinite(x))range.value=Math.max(3000,Math.min(500000,x));updatePressed();calculate()});
range.addEventListener('input',()=>{amount.value=range.value;updatePressed();calculate()});
rate.addEventListener('input',calculate);
fee.addEventListener('input',calculate);
document.getElementById('share').addEventListener('click',async()=>{
 const status=document.getElementById('shareStatus'),dict=language==='en'?en:hi;
 if(!last){status.textContent=dict.invalid;return}
 const msg=['MAAKIT Credit — Example EMI','Amount: '+rupees(last.p),'Annual interest: '+last.r+'%','Tenure: '+last.n+' months','Fee (before taxes): '+rupees(last.feeTotal),'Monthly EMI: '+rupees(last.e),'Total (before taxes): '+rupees(last.total+last.feeTotal),dict.shareNote].join('\n');
 try{
  if(navigator.share)await navigator.share({text:msg});
  else if(navigator.clipboard&&window.isSecureContext){await navigator.clipboard.writeText(msg);status.textContent=dict.copied;}
  else{status.textContent=dict.shareFailed}
 }catch(err){if(err&&err.name!=='AbortError')status.textContent=dict.shareFailed}
});
const links=[...document.querySelectorAll('.bottom a')];
links.forEach(a=>a.addEventListener('click',()=>{links.forEach(b=>b.classList.remove('active'));a.classList.add('active')}));
setLanguage('hi');
if('serviceWorker' in navigator&&window.isSecureContext){window.addEventListener('load',()=>navigator.serviceWorker.register('./sw.js').catch(()=>{}))}
})();
</script>
</body>
</html>
