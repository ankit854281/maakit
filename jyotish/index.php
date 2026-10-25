<?php
// ============================================================
// Maakit Jyotish — kundli, gun milan, rashifal aur AI Jyotishi
//
// Alag product, Maakit ke hosting par (jaise /school). Maakit ki
// kisi table ko nahi chhoota. Saari ginti (grah, lagna, dasha,
// milan) phone par hi hoti hai: engine.js + astronomy.min.js
// (astronomy-engine 2.1.19, MIT). Server sirf AI chat (ai.php)
// ke liye hai.
// ============================================================
declare(strict_types=1);
require_once __DIR__ . '/ai_lib.php';
$aiOn = jy_ai_ready();
header('Content-Type: text/html; charset=utf-8');
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title>Maakit Jyotish — Kundli, Milan, Rashifal</title>
<meta name="description" content="Free Janam Kundli, 36 gun Kundli Milan, daily rashifal and AI Jyotishi in Hinglish, Hindi and English. Powered by Maakit, Bhadohi.">
<meta name="theme-color" content="#15153A">
<script>/* rang pehle hi laga do, taaki page jhapke nahi */try{var t=JSON.parse(localStorage.getItem("mj-theme"));if(t==="kesar"||t==="mor")document.documentElement.setAttribute("data-theme",t)}catch(e){}</script>
<link rel="manifest" href="manifest.json">
<link rel="apple-touch-icon" href="icons/apple-180.png">
<link rel="icon" type="image/png" sizes="192x192" href="icons/icon-192.png">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="Maakit Jyotish">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<!-- Fonts load without blocking the page: slow 4G par pehle text dikhe, font baad mein. -->
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Tiro+Devanagari+Hindi&family=Mukta:wght@400;500;700&display=swap" media="print" onload="this.media='all'">
<link rel="stylesheet" href="jyotish.css?v=20">
</head>
<body data-ai="<?= $aiOn ? '1' : '0' ?>">
<svg width="0" height="0" style="position:absolute" aria-hidden="true" focusable="false"><defs>
  <radialGradient id="jg-dim" cx="35%" cy="32%" r="70%"><stop offset="0" style="stop-color:var(--bead-hi)"/><stop offset="1" style="stop-color:var(--bead)"/></radialGradient>
  <radialGradient id="jg-lit" cx="35%" cy="30%" r="72%"><stop offset="0" style="stop-color:var(--lit-hi)"/><stop offset=".55" style="stop-color:var(--accent)"/><stop offset="1" style="stop-color:var(--lit-lo)"/></radialGradient>
  <radialGradient id="jg-now" cx="40%" cy="35%" r="70%"><stop offset="0" style="stop-color:var(--now-hi)"/><stop offset=".6" style="stop-color:var(--lit-hi)"/><stop offset="1" style="stop-color:var(--accent)"/></radialGradient>
  <radialGradient id="jg-sum" cx="35%" cy="30%" r="72%"><stop offset="0" style="stop-color:var(--sum-hi)"/><stop offset="1" style="stop-color:var(--kumkum)"/></radialGradient>
</defs></svg>
<div class="wrap">
  <header class="mast">
    <button class="iconbtn menu-btn" id="menu-open" type="button" aria-controls="menu" aria-expanded="false" data-i18n-aria="menu" aria-label="Menu"><svg viewBox="0 0 24 24"><path d="M4 7h16M4 12h16M4 17h16"/></svg></button>
    <div class="brand"><h1>Maakit Jyotish</h1><span class="dev">माकित ज्योतिष</span></div>
    <button class="install-pill" id="install-top" type="button" hidden><svg viewBox="0 0 24 24"><path d="M12 4v11M7 10l5 5 5-5M5 20h14"/></svg><span data-i18n="installShort">App</span></button>
    <div class="panch" id="panch" data-i18n="panchLoading">Loading today's panchang…</div>
  </header>

  <div class="card notice" id="notice" hidden>
    <p style="margin:0"><span data-i18n="noticeBody">Maakit Jyotish saves anonymous usage to learn what people need: which features are used, birth place, rashi, birth year, and the questions you ask the AI.</span> <b data-i18n="noticeStrong">Never your name, exact birth date or time, or your check-in mood.</b> <span data-i18n="noticeTail">Please don't write names or phone numbers in questions.</span></p>
    <div class="row"><button class="btn" type="button" id="share-yes" data-i18n="ok">OK</button><button class="btn ghost" type="button" id="share-no" data-i18n="dontSend">Don't send</button></div>
  </div>

  <!-- AI -->
  <section class="tab" id="t-ai" hidden>
    <h2 class="chat-top-title" data-i18n="chatFirst">AI Jyotishi se baat karein</h2>

    <div class="home-chat-scroll" id="home-chat-scroll">
    <div class="chat-welcome" id="ai-chat-welcome">
      <div class="assistant-mark" aria-hidden="true">✦</div>
      <div><h3 data-i18n="cwTitle">Namaste! Apna sawaal likhiye.</h3>
        <p data-i18n="cwBody">Jawaab aapki kundli aur Parashari jyotish par aadharit hoga, saath mein ek vyavaharik agla kadam bhi. Personal jawaab ke liye pehle apni kundli banaiye.</p></div>
    </div>
    <div class="chat" id="chat"></div>
<details class="home-chat-options"><summary data-i18n="chatOptions">Chat settings</summary>    <div class="card">
      <div class="row" style="justify-content:space-between"><h2 data-i18n="aiTitle" id="chat-title">Ask AI Jyotishi</h2>
        <span class="note" data-i18n="aiLangNote">Replies in the language chosen at the top</span></div>
      <p class="note" style="margin:0" id="ai-who"></p>
      <div class="mode-switch" role="group" aria-label="Choose a reading style">
        <button class="chip" type="button" id="mode-jyotish" aria-pressed="true" data-i18n="modeJy">Jyotish reading</button>
        <button class="chip" type="button" id="mode-council" aria-pressed="false" data-i18n="modeCouncil">Navagrah Council · Rishton ki clarity</button>
      </div>
      <p class="note" id="mode-note" style="margin:0"></p>
      <div id="council-context" hidden>
        <div class="grid2">
          <label><span data-i18n="lArea">Current situation</span>
            <select id="council-area">
              <option value="" data-i18n="area0">Choose what fits (optional)</option>
              <option value="work or job" data-i18n="aWork">Work or job</option>
              <option value="studies" data-i18n="aStudy">Studies</option>
              <option value="business" data-i18n="aBiz">Business</option>
              <option value="relationship" data-i18n="aRel">Relationship</option>
              <option value="family" data-i18n="aFam">Family</option>
              <option value="money" data-i18n="aMoney">Money</option>
              <option value="health" data-i18n="aHealth">Health</option>
              <option value="another situation" data-i18n="aOther">Another situation</option>
            </select>
          </label>
          <label><span data-i18n="lHelp">What would help most?</span>
            <select id="council-goal">
              <option value="" data-i18n="help0">Choose a kind of help (optional)</option>
              <option value="understanding the situation" data-i18n="hUnderstand">Understand the situation</option>
              <option value="one practical next step" data-i18n="hStep">One practical next step</option>
              <option value="a safe boundary" data-i18n="hBoundary">A safe boundary</option>
              <option value="calming down before deciding" data-i18n="hCalm">Calm down before deciding</option>
            </select>
          </label>
        </div>
        <p class="note" style="margin:6px 0 0" data-i18n="councilThird">Your message is the third part: describe what actually happened, in your own words.</p>
      </div>
      <div class="chips" id="sugg"></div>
      <div class="council-privacy" id="council-privacy" hidden>
        <p class="note" style="margin:0" data-i18n="councilPriv">Council mode sends your message, any context choices and a limited Moon-sign/Nakshatra summary to our AI provider for an answer. Maakit does not save Council question text in its Jyotish usage log. Please leave out names, phone numbers and identifying details.</p>
        <label class="consent"><input type="checkbox" id="council-consent"> <span data-i18n="consent">I understand and want to send this question for an AI answer.</span></label>
      </div>
    </div>
</details>
    <div class="note" id="ai-status"></div>
    <div class="row council-followups" id="council-followups" hidden aria-label="Ask a follow-up question">
      <button class="btn ghost" type="button" data-council-followup="rahu" data-i18n="fuRahu">Rahu · Red flags?</button>
      <button class="btn ghost" type="button" data-council-followup="chandra" data-i18n="fuChandra">Chandrama · Emotions?</button>
      <button class="btn ghost" type="button" data-council-followup="shani" data-i18n="fuShani">Shani · Next boundary?</button>
    </div>
    <p class="note" data-i18n="aiDisc">Readings follow classical Parashari Jyotish. They are guidance for reflection, not guaranteed predictions. For health, legal or money decisions, consult a qualified professional.</p>
    
    </div>
    <form class="composer" id="f-ai"><textarea id="q" data-i18n-ph="phQ" placeholder="Type your question… e.g. Meri naukri kab lagegi?" aria-label="Your question"></textarea><button class="btn" id="send" type="submit" data-i18n="askBtn">Ask</button></form>
  </section>


  <!-- AAJ: check-in + daily card -->
  <section class="tab" id="t-aaj">
    <div class="card invite" id="jc-invite" hidden role="region" aria-label="Jaap Challenge">
      <p id="jc-invite-text"></p>
      <div class="row"><button class="btn" id="jc-accept" type="button" data-i18n="jcAccept">Challenge shuru karein</button><button class="btn ghost" id="jc-decline" type="button" data-i18n="notNow">Abhi nahi</button></div>
    </div>

    <section class="home-jaap-spotlight" aria-labelledby="home-jaap-heading">
      <div class="jaap-head">
        <div class="jaap-title-row"><h2 id="home-jaap-heading">Naam Jaap Mala</h2><span class="update-tag" data-i18n="updateTag">NEW</span>
        <p id="home-jaap-description">Apna mantra chunein aur mala chakra par tap karke jaap shuru karein.</p></div>
        <button class="iconbtn" id="home-jaap-settings" type="button" aria-label="Jaap settings"><svg viewBox="0 0 24 24"><path d="M4 7h10M18 7h2M4 17h4M12 17h8"/><circle cx="16" cy="7" r="2.2"/><circle cx="10" cy="17" r="2.2"/></svg></button>
      </div>
      <div class="mantra-chips" id="home-jaap-chips" role="radiogroup" aria-labelledby="home-jaap-mantra-label"></div>
      <label class="sr-only" for="home-jaap-mantra" id="home-jaap-mantra-label">Aaj ka mantra</label>
      <select id="home-jaap-mantra" hidden><option value="राधा">राधा</option><option value="राम">राम</option><option value="ॐ नमः शिवाय">ॐ नमः शिवाय</option><option value="हरे कृष्ण">हरे कृष्ण</option><option value="जय माता दी">जय माता दी</option></select>
      <button class="btn book-launch" id="book-launch" type="button" data-i18n="bookLaunch">Naam-jap pustak kholein</button>
      <div class="home-counter-row">
      <div class="mala" id="home-mala">
        <svg class="mala-ring" id="home-ring" viewBox="0 0 300 300" aria-hidden="true"></svg>
        <span class="ripple" aria-hidden="true"></span>
        <button class="mala-disk" id="home-jaap-tap" type="button" aria-label="Ek jaap ginein"><span class="mala-photo" aria-hidden="true"></span><span class="mala-art" aria-hidden="true"></span><b class="mala-name" id="home-jaap-name">राधा</b><strong id="home-jaap-count" aria-live="polite">0</strong><small id="home-jaap-tap-label">Tap karein · Pushp chadhayein</small></button>
        <div class="petals" id="home-petals" aria-hidden="true"></div>
      </div>
      <div class="jaap-batch-tools"><button class="jaap-batch-button" id="jt-batch" type="button" aria-pressed="false"><strong id="jt-batch-value">+1</strong><span id="jt-batch-label">per tap</span></button><span id="jaap-batch-hint">Har mantra ke baad ek tap</span><button class="jaap-undo-inline" id="home-jaap-undo" type="button" disabled data-i18n="gameUndo" data-i18n-aria="jtUndo">↶ Undo</button></div><p class="sr-only" id="jaap-batch-status" aria-live="polite" aria-atomic="true"></p>
      </div>
      <div class="mala-meta">
        <span class="mbadge"><span id="home-mala-label">Mala</span>: <b id="home-mala-num">1</b> (<b id="home-mala-in">0</b>/108)</span>
        <span class="mbadge"><span id="home-jaap-count-label">Aaj ka Kul Jaap</span>: <b id="home-jaap-total">0</b></span>
      </div>
      <div class="jaap-slider-nav" role="group" data-i18n-aria="sliderLabel" aria-label="Jaap options">
        <button type="button" data-jaap-slide="0" aria-pressed="true" data-i18n="slideControls">Controls</button>
        <button type="button" data-jaap-slide="1" aria-pressed="false" data-i18n="slideDesign">Design · 3D</button>
        <button type="button" data-jaap-slide="2" aria-pressed="false" data-i18n="slideJourney">Yatra</button>
        <button type="button" id="jaap-slide-next" data-i18n-aria="slideNext" aria-label="Next options">›</button>
      </div>
      <div class="jaap-slider" id="jaap-slider" tabindex="0" data-i18n-aria="sliderLabel" aria-label="Swipe for Jaap options">
        <div class="jaap-slide">      <div class="jaap-toggles" role="group" aria-label="Jaap settings">
        <button class="tpill" id="jt-bell" type="button" aria-pressed="true"><svg viewBox="0 0 24 24"><path d="M6 16V11a6 6 0 0 1 12 0v5l1.5 2h-15z"/><path d="M10 20.5a2 2 0 0 0 4 0"/></svg><span id="jt-bell-label">Ghanti</span></button>
        <button class="tpill" id="jt-vibe" type="button" aria-pressed="true"><svg viewBox="0 0 24 24"><rect x="8" y="3" width="8" height="18" rx="2"/><path d="M4 8v8M20 8v8M1.5 10v4M22.5 10v4"/></svg><span id="jt-vibe-label">Vibration</span></button>
        <button class="tpill" id="jt-auto" type="button" aria-pressed="false"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="8.5"/><path d="M10 8.5v7l5.5-3.5z"/></svg><span id="jt-auto-label">Auto Jaap</span></button>
        <label class="tpill select"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="8.5"/><circle cx="12" cy="12" r="4.5"/><circle cx="12" cy="12" r="1"/></svg><span class="sr-only" id="jt-target-label">Lakshya</span><select id="jt-target"><option value="1">1 Mala</option><option value="3">3 Mala</option><option value="5">5 Mala</option><option value="11">11 Mala</option></select></label>
      </div>
<div class="jaap-slide-actions">      <button class="jaap-mini-launch" id="jaap-mini-launch" type="button" data-i18n="miniLaunch">Chhota counter</button>
      <button class="game-btn" id="js-game" type="button"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="2.5" y="6" width="19" height="12" rx="3"/><path d="M7 10v4M5 12h4M15.5 11h.01M18 13h.01"/></svg><span><b data-i18n="gameBtn">Game Mode</b><small data-i18n="gameBtnSub">Phone tircha karein ya yahan dabaiye</small></span></button>
</div></div>
        <div class="jaap-slide">      <div class="jaap-designs" role="group" data-i18n-aria="lookTitle" aria-label="Design">
        <button type="button" data-jaap-look="silver" data-i18n="lookSilver">Silver</button>
        <button type="button" data-jaap-look="forest" data-i18n="lookForest">Vriksh</button>
        <button type="button" data-jaap-look="temple" data-i18n="lookTemple">Mandir</button>
      </div>
      <div class="jaap-actions">
        <button class="abtn" id="js-share" type="button"><svg viewBox="0 0 24 24"><circle cx="18" cy="5.5" r="2.5"/><circle cx="6" cy="12" r="2.5"/><circle cx="18" cy="18.5" r="2.5"/><path d="M8.2 10.8l7.6-4.1M8.2 13.2l7.6 4.1"/></svg><span id="js-share-label">Share Jaap</span></button>
        <button class="abtn" id="js-copy" type="button"><svg viewBox="0 0 24 24"><path d="M10 14a4 4 0 0 0 5.7 0l3.6-3.6a4 4 0 0 0-5.7-5.7L12 6.3"/><path d="M14 10a4 4 0 0 0-5.7 0l-3.6 3.6a4 4 0 0 0 5.7 5.7l1.6-1.6"/></svg><span id="js-copy-label">Copy Link</span></button>
        <button class="abtn" id="js-challenge" type="button"><svg viewBox="0 0 24 24"><path d="M8 4h8v5a4 4 0 0 1-8 0z"/><path d="M8 6H4.5a3 3 0 0 0 3.5 4M16 6h3.5a3 3 0 0 1-3.5 4M12 13v4M8.5 20h7M10 17h4"/></svg><span id="js-challenge-label">Jaap Challenge</span></button>
      </div>
</div>
        <div class="jaap-slide">      <p class="goalbar" id="jt-progress" aria-live="polite"></p>
      <div class="jaap-evolution"><span class="jaap-evo-art" id="jaap-evo-art" aria-hidden="true"></span><div><b id="jaap-evolution-title">Beej se shuruaat</b><small id="jaap-evolution-next">Agla padaav: Vriksh</small></div><div class="jaap-evolution-track" id="jaap-evolution-bar" role="progressbar" aria-label="Sadhna ki pragati" data-i18n-aria="jtEvolutionLabel" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0"><i id="jaap-evolution-fill"></i></div></div>
</div>
      </div>
    </section>

    <section class="jaap-card" id="jaap-card" aria-labelledby="jaap-title" hidden>
      <div class="jaap-card-copy"><h2 id="jaap-title" data-i18n="jsTitle">Jaap settings</h2><p id="jaap-home-description" data-i18n="jsDesc">Mantra, jaap ka tareeka, lakshya aur photo yahan badlein.</p><div class="jaap-today-total"><span id="jaap-home-today-label" data-i18n="todayTotal">Aaj ka kul jaap</span> <strong id="jaap-today">0</strong></div><div class="jaap-insights"><div class="jaap-insights-head"><b id="jaap-home-week-label" data-i18n="last7">Pichhle 7 din</b><span><strong id="jaap-week-sessions">0</strong> <span id="jaap-home-sessions-label" data-i18n="sittings">baar jaap kiya</span></span></div><div class="jaap-week-bars" id="jaap-week-bars" aria-hidden="true"></div><p class="note" id="jaap-week-summary" aria-live="polite"></p></div></div>
      <div class="jaap-month-history"><div class="jaap-month-head"><b id="jaap-month-title" data-i18n="jtMonth">Mahine ka jaap</b><div><button type="button" id="jaap-month-prev" aria-label="Pichhla mahina" data-i18n-aria="jtPrev">‹</button><span id="jaap-month-label"></span><button type="button" id="jaap-month-next" aria-label="Agla mahina" data-i18n-aria="jtNext">›</button></div></div><div class="jaap-calendar-weekdays" id="jaap-calendar-weekdays"></div><div class="jaap-calendar" id="jaap-calendar"></div><p id="jaap-month-summary" class="note"></p></div>
      <div class="jaap-setup">
        <label><span data-i18n="lMantra">Mantra</span>
          <select id="jaap-mantra"><option value="राधा">राधा</option><option value="राम">राम</option><option value="ॐ नमः शिवाय">ॐ नमः शिवाय</option><option value="हरे कृष्ण">हरे कृष्ण</option><option value="जय माता दी">जय माता दी</option><option value="__custom__" data-i18n="optOwn">Apna mantra</option></select></label><label id="jaap-custom-mantra-wrap" hidden><span data-i18n="lOwn">Apna mantra likhein</span><input id="jaap-custom-mantra" type="text" maxlength="60" data-i18n-ph="phOwn" placeholder="Jaise: ॐ गं गणपतये नमः" autocomplete="off"></label><div class="jaap-guide" id="jaap-guide"><b id="jaap-guide-title">Mantra ka arth</b><p id="jaap-guide-copy">Mantra chunne par uska arth yahan dikhega.</p><button class="btn ghost" id="jaap-ask-ai" type="button" data-i18n="askMantra">Is mantra ka arth AI se poochhein</button></div>
        <label><span data-i18n="lMode">Jaap ka tareeka</span>
          <select id="jaap-mode"><option value="mala" data-i18n="modeMala">Mala jaap · apne lakshya tak</option><option value="solo" data-i18n="modeSolo">Speed round · 30 second</option><option value="duo" data-i18n="modeDuo">Do logon ka mukabla</option></select>
        </label>
        <label><span data-i18n="lBhav">Jaap ka bhaav</span>
          <select id="jaap-bhav"><option value="sahaj" data-i18n="bhavSahaj">Koi vishesh bhaav nahi</option><option value="shanti" data-i18n="bhavShanti">Shaanti</option><option value="aabhar" data-i18n="bhavAabhar">Aabhaar</option><option value="sahas" data-i18n="bhavSahas">Saahas</option><option value="ekagrata" data-i18n="bhavEk">Ekagrata</option></select>
        </label>
        <label id="jaap-goal-wrap"><span data-i18n="lGoal">Lakshya</span>
          <select id="jaap-goal"><option value="27" data-i18n="g27">27 jaap</option><option value="54" data-i18n="g54">54 jaap</option><option value="108" selected data-i18n="g108">108 jaap · 1 mala</option><option value="1008" data-i18n="g1008">1008 jaap</option><option value="custom" data-i18n="gCustom">Apna target</option></select></label><label id="jaap-custom-goal-wrap" hidden><span data-i18n="lCustomGoal">Kitne jaap</span><input id="jaap-custom-goal" type="number" min="1" max="1008" step="1" value="108" inputmode="numeric"></label>
        <div class="jaap-names" id="jaap-names" hidden>
          <label><span data-i18n="p1">Player 1</span><input id="jaap-player1" maxlength="18" data-i18n-ph="ph1" placeholder="Pehla naam"></label>
          <label><span data-i18n="p2">Player 2</span><input id="jaap-player2" maxlength="18" data-i18n-ph="ph2" placeholder="Doosra naam"></label>
        </div>
        <div class="jaap-photo-control"><div class="jaap-photo-actions"><button class="btn ghost" id="jaap-photo-choose" type="button" data-i18n="photoChoose">Is mantra ke liye photo lagayein</button><button class="linkbtn" id="jaap-photo-clear" type="button" hidden data-i18n="photoClear">Photo hatayein</button></div><input id="jaap-photo-file" type="file" accept="image/*" hidden><div class="jaap-photo-position"><label><span data-i18n="jtPhotoX">Photo left/right</span><input id="jaap-photo-x" type="range" min="0" max="100" value="50"></label><label><span data-i18n="jtPhotoY">Photo up/down</span><input id="jaap-photo-y" type="range" min="0" max="100" value="50"></label><label><span data-i18n="jtPhotoZoom">Photo zoom</span><input id="jaap-photo-zoom" type="range" min="100" max="180" value="100"></label><button id="jaap-photo-position-reset" type="button" data-i18n="jtPhotoReset">Reset photo position</button></div><p class="note" id="jaap-photo-note"></p></div>
        <div class="jaap-audio-options">
          <label><input type="checkbox" id="jaap-bell" checked> <span data-i18n="bellOpt">Har jaap par ghanti ki awaaz</span></label>
          <label><input type="checkbox" id="jaap-voice"> <span data-i18n="voiceOpt">Mantra bolkar sunayein</span></label>
          <label><input type="checkbox" id="jaap-vibrate" checked> <span data-i18n="vibeOpt">Vibration (har jaap par halka, mala poori hone par lamba)</span></label><label><input type="checkbox" id="jaap-motion" checked> <span data-i18n="jtMotion">Jaap animations dikhayein</span></label>
        </div>
        <p class="note jaap-local-note" data-i18n="localNote">Ginti, mantra aur photo sirf is phone par save hote hain.</p>
        <button class="btn" id="jaap-start" type="button">Jaap shuru karein</button>
      </div>
    </section>

    <section class="jaap-arena" id="jaap-arena" hidden aria-label="Naam Jaap game">
      <header class="jaap-topbar"><button class="jaap-exit" type="button" id="jaap-exit" data-i18n-aria="closeJaap" aria-label="Jaap band karein">✕</button><div><div class="eyebrow" id="jaap-round-label">Mala jaap</div><strong id="jaap-current-player">Aapka jaap</strong></div><button class="jaap-pause" id="jaap-pause" type="button" aria-pressed="false">⏸ Rokein</button><div class="jaap-clock" id="jaap-clock" aria-live="off" hidden>30</div></header>
      <div class="jaap-arena-main">
        <div class="mala mala-lg">
          <svg class="mala-ring" id="jaap-beads" viewBox="0 0 300 300" aria-hidden="true"></svg>
          <span class="ripple" aria-hidden="true"></span>
          <button class="jaap-tap mala-disk" id="jaap-tap" type="button" aria-label="Ek jaap ginein"><span class="mala-photo" aria-hidden="true"></span><span class="mala-art" aria-hidden="true"></span><b class="mala-name" id="jaap-tap-name">राधा</b><small>Tap karein · Pushp chadhayein</small></button>
          <div class="petals" id="arena-petals" aria-hidden="true"></div>
        </div>
        <div class="jaap-score-panel"><span id="jaap-count-label" data-i18n="count">Ginti</span><strong id="jaap-count" aria-live="off">0</strong><small id="jaap-best-line" hidden><span data-i18n="best">Personal best:</span> <b id="jaap-best">0</b></small></div>
        <button class="jaap-undo" id="jaap-undo" type="button" aria-label="Aakhri ginti wapas karein" hidden data-i18n="undo">↶ Pichhli ginti hatayein</button><button id="jaap-arena-batch" type="button" aria-pressed="false">+1 per tap</button>
        <p class="jaap-progress-note" id="jaap-progress-note">Beech ke gol par tap karein. Har tap ek jaap hai.</p>
      </div>
      <div class="jaap-between" id="jaap-between" hidden aria-live="polite"><div class="jaap-result-card"><div class="eyebrow" data-i18n="roundDone">Round poora</div><h2 id="jaap-result-title"></h2><p id="jaap-result-copy"></p><button class="btn" id="jaap-next" type="button" data-i18n="nextPlayer">Agle ki baari</button></div></div>
      <div class="jaap-between" id="jaap-final" hidden aria-live="polite"><div class="jaap-result-card"><div class="eyebrow" id="jaap-final-label">Lakshya poora</div><h2 id="jaap-final-title"></h2><div id="jaap-final-scores"></div><p id="jaap-final-copy"></p><div class="row"><a class="btn" id="jaap-share" target="_blank" rel="noopener" data-i18n="shareWA">WhatsApp par share karein</a><button class="btn ghost" id="jaap-done" type="button" data-i18n="done">Ho gaya</button></div><button class="linkbtn" id="jaap-final-undo" type="button" hidden data-i18n="jtUndo">Pichhli ginti wapas</button></div></div>
    </section>

  <section class="card pj-card" id="pandit-calendar" aria-label="Pandit Ji calendar"></section>
  </section>

  <!-- AAJ KA MARGDARSHAN: mood check-in + roz ka card -->
  <section class="tab" id="t-card" hidden>
    <div class="card" id="checkin" hidden>
      <div class="eyebrow" id="ci-eyebrow"></div>
      <h2 id="ci-title"></h2>
      <div class="ci-q"><div class="ci-l" id="ci-l-mood"></div><div class="chips" id="ci-mood"></div></div>
      <div class="ci-q"><div class="ci-l" id="ci-l-energy"></div><div class="chips" id="ci-energy"></div></div>
      <div class="ci-q"><div class="ci-l" id="ci-l-concern"></div><div class="chips" id="ci-concern"></div></div>
      <label id="ci-l-text"><span></span><input id="ci-text" maxlength="300" autocomplete="off"></label>
      <label class="consent"><input type="checkbox" id="ci-share"> <span id="ci-l-share"></span></label>
      <p class="note" id="ci-private" style="margin:0"></p>
      <div class="row"><button class="btn" type="button" id="ci-go"></button><button class="btn ghost" type="button" id="ci-skip"></button></div>
      <button class="linkbtn" type="button" id="ci-help"></button>
    </div>
    <div id="card-out" style="display:flex;flex-direction:column;gap:18px"></div>
  </section>

  <!-- HELP: pareshani me — sirf madad, koi jyotish nahi -->
  <section class="help" id="help" hidden aria-live="assertive">
    <div class="card helpcard">
      <h2 id="help-title"></h2>
      <p id="help-body" style="margin:0"></p>
      <div class="helpcall"><div><div class="eyebrow">Tele-MANAS</div><div class="big">14416</div><div class="note" id="help-tm"></div></div><a class="btn" href="tel:14416" data-i18n="callNow">Call 14416</a></div>
      <div class="helpcall"><div><div class="eyebrow" id="help-em"></div><div class="big">112</div></div><a class="btn ghost" href="tel:112" data-i18n="callNow">Call 112</a></div>
      <p class="note" id="help-friend" style="margin:0"></p>
      <button class="btn ghost" type="button" id="help-ok"></button>
    </div>
  </section>

  <!-- KUNDLI -->
  <section class="tab" id="t-kundli" hidden>
    <form class="card" id="f-k" autocomplete="off">
      <div class="row" style="justify-content:space-between"><h2 data-i18n="kTitle">Janam Kundli</h2><span class="badge" id="ex-badge" data-i18n="sample">Example chart</span></div>
      <p class="note" id="k-question-hint" hidden data-i18n="kHint">Aapka sawaal surakshit hai. Apni janam jaankari bhariye; kundli banne ke baad sawaal chat mein draft rahega.</p>
      <div class="grid2">
        <label><span data-i18n="name">Name</span><input id="k-name" required value="Example Person"></label>
        <label><span data-i18n="gender">Gender</span><select id="k-gender"><option value="Male" data-i18n="male">Male</option><option value="Female" data-i18n="female">Female</option><option value="Other" data-i18n="other">Other</option></select></label>
        <label><span data-i18n="dob">Date of birth</span><input id="k-date" type="date" required value="2001-01-15"></label>
        <label><span data-i18n="tob">Time of birth</span><input id="k-time" type="time" required value="08:30"></label>
        <label><span data-i18n="pob">Place of birth</span><select id="k-city"></select></label>
        <label><span data-i18n="tz">Time zone</span><select id="k-tz"><option value="5.5" data-i18n="tzIST">IST (India, +5:30)</option><option value="5.75">Nepal (+5:45)</option><option value="6">Bangladesh (+6:00)</option><option value="4">UAE (+4:00)</option><option value="0">UK (GMT)</option><option value="-5">US Eastern (-5:00)</option></select></label>
      </div>
      <div class="grid2" id="k-custom" hidden>
        <label><span data-i18n="lat">Latitude</span><input id="k-lat" type="number" step="0.0001" value="25.3953"></label>
        <label><span data-i18n="lon">Longitude</span><input id="k-lon" type="number" step="0.0001" value="82.5683"></label>
      </div>
      <div class="row"><button class="btn" type="submit" data-i18n="makeK">Make my Kundli</button><span class="note" data-i18n="kPrivate">Your name and exact birth details stay on this phone.</span></div>
    </form>
    <div id="k-out" class="fade" style="display:flex;flex-direction:column;gap:18px"></div>
    <div class="card">
      <h3 data-i18n="yourData">Your data</h3>
      <p class="note" style="margin:0" id="priv-state"></p>
      <div class="row"><button class="btn ghost" type="button" id="priv-toggle"></button><button class="btn ghost" type="button" id="priv-del" data-i18n="delData">Delete my data</button></div>
      <p class="note" id="priv-done" style="margin:0" hidden data-i18n="delDone">Deleted. Everything saved on this phone and the usage log from this phone are gone.</p>
    </div>
  </section>

  <!-- MILAN -->
  <section class="tab" id="t-milan" hidden>
    <form class="card" id="f-m" autocomplete="off">
      <h2 data-i18n="mTitle">Kundli Milan · 36 Gun</h2>
      <p class="note" style="margin:0" data-i18n="mIntro">Enter both birth details. The example pair is pre-filled.</p>
      <div class="grid2">
        <div class="card" style="background:var(--sunk);border:0">
          <div class="eyebrow" data-i18n="boy">Boy</div>
          <label><span data-i18n="name">Name</span><input id="b-name" value="Rahul (example)"></label>
          <label><span data-i18n="date">Date</span><input id="b-date" type="date" value="1998-06-12"></label>
          <label><span data-i18n="time">Time</span><input id="b-time" type="time" value="06:45"></label>
          <label><span data-i18n="place">Place</span><select id="b-city"></select></label>
        </div>
        <div class="card" style="background:var(--sunk);border:0">
          <div class="eyebrow" data-i18n="girl">Girl</div>
          <label><span data-i18n="name">Name</span><input id="g-name" value="Priya (example)"></label>
          <label><span data-i18n="date">Date</span><input id="g-date" type="date" value="2000-02-03"></label>
          <label><span data-i18n="time">Time</span><input id="g-time" type="time" value="14:20"></label>
          <label><span data-i18n="place">Place</span><select id="g-city"></select></label>
        </div>
      </div>
      <div class="row"><button class="btn" type="submit" data-i18n="checkMilan">Check Gun Milan</button></div>
    </form>
    <div id="m-out" class="fade" style="display:flex;flex-direction:column;gap:18px"></div>
  </section>

  <!-- RASHIFAL -->
  <section class="tab" id="t-rashi" hidden>
    <div id="r-out" style="display:flex;flex-direction:column;gap:18px"></div>
  </section>

  <div class="foot">Powered by <b>Maakit</b>, Bhadohi<br><span data-i18n="foot">Vedic sidereal (Lahiri), whole-sign houses</span></div>
</div>
<div class="mini-jaap" id="mini-jaap" hidden>
      <div class="mini-jaap-dock"><button class="mini-jaap-open" id="mini-jaap-open" type="button" aria-expanded="false" aria-controls="mini-jaap-panel" data-i18n-aria="miniFabOpen" aria-label="Jaap ke controls kholen">⋯</button><button class="mini-jaap-toggle" id="mini-jaap-toggle" type="button" aria-label="Ek tap mein 1 jaap ginein"><i aria-hidden="true">ॐ</i><span class="mini-jaap-fab-count" id="mini-jaap-bubble-count">0</span><small id="mini-jaap-amount">+1</small></button></div>
      <div class="mini-jaap-panel" id="mini-jaap-panel" hidden>
        <div class="mini-jaap-head"><div><b data-i18n="miniTitle">Jaap</b><small data-i18n="miniSub">Chalte-chalte jaap karein</small></div><button id="mini-jaap-close" type="button" data-i18n-aria="closeBtn" aria-label="Band karein">×</button></div>
        <label for="mini-jaap-mantra" data-i18n="lMantra">Mantra</label><select id="mini-jaap-mantra"><option value="राधा">राधा</option><option value="राम">राम</option><option value="ॐ नमः शिवाय">ॐ नमः शिवाय</option><option value="हरे कृष्ण">हरे कृष्ण</option><option value="जय माता दी">जय माता दी</option></select>
        <button class="mini-jaap-tap" id="mini-jaap-tap" type="button" aria-label="Naam jaap ek baar ginen"><span id="mini-jaap-count">0</span><small id="mini-jaap-name">राम</small><b data-i18n="miniTap">Tap karein</b></button>
        <p class="note" data-i18n="miniScope">Is app ke doosre pannon par bhi jaap karein.</p><div class="mini-jaap-controls"><button class="mini-jaap-batch" id="mini-jaap-batch" type="button" aria-pressed="false"><strong id="mini-jaap-batch-value">+1</strong><span data-i18n="jtBatchOne">per tap</span></button><button class="mini-jaap-undo" id="mini-jaap-undo" type="button" disabled data-i18n="jtUndo" aria-label="Pichhli ginti wapas">↶ Undo</button></div>
        <button class="btn ghost" id="mini-jaap-share" type="button" data-i18n="gameShare">Share</button><p class="note"><span data-i18n="miniToday">Aaj ka kul jaap:</span> <strong id="mini-jaap-today">0</strong></p><button class="btn ghost" id="mini-jaap-photo" type="button">Bhagwan ki photo lagayein</button><button class="btn ghost mini-jaap-full" id="mini-jaap-full" type="button">Jaap settings kholein</button>
      </div>
    </div>

<div class="drawer" id="menu" hidden>
  <div class="drawer-panel" role="dialog" aria-modal="true" aria-labelledby="menu-title">
    <div class="drawer-head"><div><b id="menu-title">Maakit Jyotish</b><small data-i18n="menuSub">Kundli · Rashifal · Naam Jaap</small></div><button class="iconbtn" id="menu-close" type="button" data-i18n-aria="closeBtn" aria-label="Band karein">×</button></div>
    <nav class="menu-list" id="main-navigation" aria-label="Main navigation">
      <button class="menu-item" type="button" data-t="aaj" aria-current="page"><span class="mi-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="m3 10 9-7 9 7"/><path d="M5 9v11h14V9M9 20v-6h6v6"/></svg></span><span data-i18n="mHome">mHome</span></button>
      <button class="menu-item" type="button" data-t="ai"><span class="mi-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M4 5.5A2.5 2.5 0 0 1 6.5 3h11A2.5 2.5 0 0 1 20 5.5v8a2.5 2.5 0 0 1-2.5 2.5H10l-5 4v-4.3A2.5 2.5 0 0 1 4 13.5z"/></svg></span><span data-i18n="mChat">mChat</span></button>
      <button class="menu-item" type="button" data-t="card"><span class="mi-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M12 3c3 4 4.5 6.5 4.5 8.5a4.5 4.5 0 0 1-9 0C7.5 9.5 9 7 12 3z"/><path d="M3 17h18c-1 4-4.6 5-9 5s-8-1-9-5z"/></svg></span><span data-i18n="mCard">mCard</span></button>
      <button class="menu-item" type="button" data-t="kundli"><span class="mi-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="1"/><path d="M3 3l18 18M21 3L3 21M12 3l9 9-9 9-9-9z"/></svg></span><span data-i18n="mKundli">mKundli</span></button>
      <button class="menu-item" type="button" data-t="rashi"><span class="mi-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="4"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3M4.9 4.9l2.1 2.1M17 17l2.1 2.1M4.9 19.1L7 17M17 7l2.1-2.1"/></svg></span><span data-i18n="mRashi">mRashi</span></button>
      <button class="menu-item" type="button" data-t="milan"><span class="mi-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="9" cy="12" r="6"/><circle cx="15" cy="12" r="6"/></svg></span><span data-i18n="mMilan">mMilan</span></button>
      <div class="menu-sep"></div>
      <button class="menu-item" type="button" data-go="settings"><span class="mi-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M4 7h10M18 7h2M4 17h4M12 17h8"/><circle cx="16" cy="7" r="2.2"/><circle cx="10" cy="17" r="2.2"/></svg></span><span data-i18n="jsTitle">Mala ki Settings</span></button>
      <button class="menu-item" type="button" id="theme-open"><span class="mi-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 3a9 9 0 0 0 0 18z" fill="currentColor"/></svg></span><span data-i18n="themeTitle">Apna Rang Chunein</span><span class="theme-dot" aria-hidden="true"></span></button>
      <label class="menu-item langpick"><span class="mi-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M4 5h9M8.5 3v2M6 5c1 4 4 7 7 8M11 5c-1 4-4 7-7 8M13 21l4-9 4 9M14.5 18h5"/></svg></span><span id="language-label" data-i18n="mLang">Bhasha</span>
        <select id="lang" aria-label="Language"><option>Hinglish</option><option value="Hindi (Devanagari script)">हिंदी</option><option>English</option></select></label>
      <button class="menu-item" type="button" data-go="data"><span class="mi-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6z"/></svg></span><span data-i18n="mData">Data aur Privacy</span></button>
      <button class="menu-item install" type="button" id="install-menu" hidden><span class="mi-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M12 4v11M7 10l5 5 5-5M5 20h14"/></svg></span><span><span data-i18n="installTitle">App install karein</span><small data-i18n="installSub">Home screen par Maakit Jyotish, bina Play Store</small></span></button>
    </nav>
    <p class="drawer-foot">Powered by <b>Maakit</b>, Bhadohi</p>
  </div>
</div>
<div class="sheet" id="ios-sheet" hidden role="dialog" aria-modal="true" aria-labelledby="ios-title">
  <div class="sheet-body">
    <div class="sheet-head"><h2 id="ios-title" data-i18n="installTitle">App install karein</h2><button class="iconbtn" type="button" data-close-sheet="ios-sheet" data-i18n-aria="closeBtn" aria-label="Band karein">×</button></div>
    <p data-i18n="iosSteps">iPhone par: neeche Share button (⬆) dabaiye, phir "Add to Home Screen" chuniye.</p>
    <p class="note" data-i18n="iosNote">Android par Chrome ke menu (⋮) mein "Install app" ya "Add to Home screen" dabaiye.</p>
  </div>
</div>
<div class="sheet" id="jm-sheet" hidden role="dialog" aria-modal="true" aria-labelledby="jm-title">
  <div class="sheet-body">
    <div class="sheet-head"><h2 id="jm-title" data-i18n="jmTitle">Apna mantra jodein</h2><button class="iconbtn" type="button" data-close-sheet="jm-sheet" data-i18n-aria="closeBtn" aria-label="Band karein">×</button></div>
    <label><span data-i18n="jmLabel">Mantra ya naam</span><input id="jm-input" maxlength="40" data-i18n-ph="phOwn" placeholder="Jaise: ॐ गं गणपतये नमः" autocomplete="off"></label>
    <p class="note err" id="jm-error" hidden data-i18n="jmErr">Mantra likhiye (40 akshar tak).</p>
    <button class="btn" id="jm-save" type="button" data-i18n="jmSave">Mantra jodein</button>
    <div id="jm-own-wrap" hidden><h3 class="sheet-sub" data-i18n="jmOwn">Aapke jode hue mantra</h3><div id="jm-own"></div></div>
  </div>
</div>
<div class="sheet" id="jc-sheet" hidden role="dialog" aria-modal="true" aria-labelledby="jc-title">
  <div class="sheet-body">
    <div class="sheet-head"><h2 id="jc-title">Jaap Challenge</h2><button class="iconbtn" type="button" data-close-sheet="jc-sheet" data-i18n-aria="closeBtn" aria-label="Band karein">×</button></div>
    <p class="note"><span data-i18n="jcIntro">Doston ke saath aaj ka sankalp lein. Mantra:</span> <b id="jc-mantra"></b></p>
    <button class="jc-opt" type="button" data-jc="108"><b data-i18n="jc1">1 Mala</b><span data-i18n="jc1s">108 jaap · roz ke liye</span></button>
    <button class="jc-opt" type="button" data-jc="1188"><b data-i18n="jc11">11 Mala</b><span data-i18n="jc11s">1188 jaap · vishesh sankalp</span></button>
    <p class="note" data-i18n="jcNote">Link WhatsApp par jaayega. Dost link kholenge to wahi mantra aur lakshya ke saath unka jaap shuru hoga.</p>
  </div>
</div>
<div class="sheet" id="th-sheet" hidden role="dialog" aria-modal="true" aria-labelledby="th-title">
  <div class="sheet-body">
    <div class="sheet-head"><h2 id="th-title" data-i18n="themeTitle">App ka rang</h2><button class="iconbtn" type="button" data-close-sheet="th-sheet" data-i18n-aria="closeBtn" aria-label="Band karein">×</button></div>
    <div role="radiogroup" aria-labelledby="th-title" style="display:flex;flex-direction:column;gap:10px">
      <button class="th-opt" type="button" role="radio" data-theme-pick="neel"><span class="th-prev" style="background:radial-gradient(circle at 30% 0,#4B3FC0,#15153A 70%);--p-acc:#F2A93B"></span><span><b data-i18n="thNeel">Neela Aasmaan</b><small data-i18n="thNeelS">Raat ka aasmaan aur sunehri mala</small></span><span class="th-check"></span></button>
      <button class="th-opt" type="button" role="radio" data-theme-pick="kesar"><span class="th-prev" style="background:radial-gradient(circle at 30% 0,#B4401A,#2A0E12 70%);--p-acc:#FF9F1C"></span><span><b data-i18n="thKesar">Kesariya</b><small data-i18n="thKesarS">Mandir jaisa sindoori aur kesariya rang</small></span><span class="th-check"></span></button>
      <button class="th-opt" type="button" role="radio" data-theme-pick="mor"><span class="th-prev" style="background:radial-gradient(circle at 30% 0,#1C8F86,#05252A 70%);--p-acc:#E9B949"></span><span><b data-i18n="thMor">Mor Pankh</b><small data-i18n="thMorS">Mor pankh jaisa hara-neela aur sona</small></span><span class="th-check"></span></button>
    </div>
  </div>
</div>
<section class="jgame" id="jgame" hidden role="dialog" aria-modal="true" aria-label="Jaap Game Mode">
  <div class="jg-burst" id="jg-burst" aria-hidden="true"></div>
  <div class="jg-hud jg-left">
    <button class="jg-x" id="jg-exit" type="button" data-i18n-aria="gameExit" aria-label="Game band karein"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg></button>
    <div class="jg-mantra" id="jg-mantra">राधा</div>
    <div class="jg-count"><strong id="jg-count">0</strong><span data-i18n="todayTotal">Aaj ka kul jaap</span></div>
    <div class="jg-stats"><span><span data-i18n="malaWord">Mala</span> <b id="jg-mala">1</b> · <b id="jg-in">0</b>/108</span><span><b id="jg-speed">0</b> <span data-i18n="perMin">jaap/min</span></span></div>
    <div class="jg-goal"><i id="jg-goal-bar"></i></div>
    <div class="jg-goal-row"><span id="jg-goal-text"></span><span class="jg-diyas" id="jg-diyas" aria-hidden="true"></span></div>
  </div>
  <div class="mala mala-game" id="jg-mala-wrap">
    <svg class="mala-ring" id="jg-ring" viewBox="0 0 300 300" aria-hidden="true"></svg>
    <span class="ripple" aria-hidden="true"></span>
    <button class="mala-disk" id="jg-tap" type="button" data-i18n-aria="tapOne" aria-label="Ek jaap ginein"><span class="mala-photo" aria-hidden="true"></span><span class="mala-art" aria-hidden="true"></span><b class="mala-name" id="jg-name">राधा</b><small data-i18n="tapLabel">Tap karein · Pushp chadhayein</small></button>
    <div class="petals" id="jg-petals" aria-hidden="true"></div>
  </div>
  <div class="jg-hud jg-right">
    <button class="jg-btn main" id="jg-auto" type="button" aria-pressed="false"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M10 8.5v7l5.5-3.5z"/></svg><span id="jg-auto-label">Auto Jaap</span></button>
    <button class="jg-btn" id="jg-batch" type="button" aria-pressed="false"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="8" cy="8" r="2.5"/><circle cx="16" cy="8" r="2.5"/><circle cx="8" cy="16" r="2.5"/><circle cx="16" cy="16" r="2.5"/></svg><span id="jg-batch-label">Har tap: +1</span></button>
    <button class="jg-btn" id="jg-undo" type="button" disabled><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 14L4 9l5-5"/><path d="M4 9h10a6 6 0 0 1 0 12h-3"/></svg><span data-i18n="gameUndo">Undo</span></button>
    <button class="jg-btn" id="jg-pace" type="button"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="13" r="8"/><path d="M12 13l4-3M9 2h6"/></svg><span id="jg-pace-label">Madhyam</span></button>
    <button class="jg-btn" id="jg-voice" type="button" aria-pressed="false"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 9v6h4l5 4V5L8 9z"/><path d="M16.5 8.5a5 5 0 0 1 0 7M19 6a8.5 8.5 0 0 1 0 12"/></svg><span id="jg-voice-label" data-i18n="voiceOff">Awaaz band</span></button>
    <button class="jg-btn" id="jg-bell" type="button" aria-pressed="true"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 16V11a6 6 0 0 1 12 0v5l1.5 2h-15z"/><path d="M10 20.5a2 2 0 0 0 4 0"/></svg><span data-i18n="bellShort">Ghanti</span></button>
    <button class="jg-btn" id="jg-popup" type="button" data-i18n="gamePopup">Popup</button>
    <button class="jg-btn" id="jg-share" type="button" data-i18n="gameShare">Share</button>
  </div>
  <button class="jg-thumb jg-thumb-left" id="jg-thumb-left" type="button" data-i18n-aria="thumbCount" aria-label="Tap to count"><span class="jg-thumb-amount">+1</span></button>
  <button class="jg-thumb jg-thumb-right" id="jg-thumb-right" type="button" data-i18n-aria="thumbCount" aria-label="Tap to count"><span class="jg-thumb-amount">+1</span></button>
  <div class="jg-combo" id="jg-combo" hidden aria-live="polite"></div>
  <div class="jg-banner" id="jg-banner" hidden aria-live="polite"><b></b><small></small></div>
  <div class="jg-floats" id="jg-floats" aria-hidden="true"></div>
  <p class="jg-hint" data-i18n="gameHint">Screen par kahin bhi tap karein · har tap ek jaap</p>
</section>
<div class="petal-rain" id="petal-rain" aria-hidden="true"></div>
<div class="toast" id="toast" role="status" aria-live="polite" hidden></div>


<div class="sheet" id="jaap-share-sheet" role="dialog" aria-modal="true" aria-labelledby="jaap-share-title" hidden>
 <div class="sheet-body">
  <div class="sheet-head"><h2 id="jaap-share-title" data-i18n="cardTitle">Jaap ka card</h2><button class="iconbtn" type="button" data-close-sheet="jaap-share-sheet" data-i18n-aria="closeBtn">×</button></div>
  <canvas id="jaap-share-canvas" width="1080" height="1080" role="img" data-i18n-aria="cardPreview" aria-label="Jaap card preview"></canvas>
  <div class="card-options">
   <label><span data-i18n="lookTitle">Design</span><select id="jaap-card-look"><option value="silver" data-i18n="lookSilver">Silver</option><option value="forest" data-i18n="lookForest">Vriksh</option><option value="temple" data-i18n="lookTemple">Mandir</option></select></label>
   <label><span data-i18n="cardSize">Size</span><select id="jaap-card-size"><option value="square" data-i18n="cardSquare">Square</option><option value="story" data-i18n="cardStory">Status</option></select></label>
  </div>
  <p class="note" data-i18n="cardPrivacy">Sirf mantra aur aaj ki ginti share hogi.</p>
  <div class="card-options"><button class="btn" id="jaap-card-share" type="button" data-i18n="cardSend">Card share karein</button><button class="btn ghost" id="jaap-card-save" type="button" data-i18n="cardSave">Image save karein</button></div>
  <a class="btn ghost" id="jaap-card-whatsapp" href="https://wa.me/" target="_blank" rel="noopener" data-i18n="whatsappJaap">WhatsApp par jaap share karein</a>
  <p class="note" data-i18n="whatsappImage">Photo bhejne ke liye Share card mein WhatsApp chunein.</p>
  <p id="jaap-card-status" role="status" class="note"></p>
 </div>
</div>
<script src="astronomy.min.js?v=2.1.19"></script>
<script src="engine.js?v=1"></script>
<script src="i18n.js?v=13"></script>
<script src="daily.js?v=2"></script>
<script src="app.js?v=18"></script>
<script src="pandit.js?v=1"></script>
<dialog id="writing-book" aria-labelledby="book-title">
 <header class="book-toolbar"><h2 id="book-title" data-i18n="bookTitle">Naam-jap pustak</h2><button type="button" id="book-close" data-i18n="bookClose">Wapas</button></header>
 <div class="focus-row"><button id="book-focus" type="button" aria-pressed="false" data-i18n="bookFocus">Focus mode</button></div><div class="book-modes"><button type="button" id="book-tap-mode" data-i18n="bookTap">Tap se likhein</button><button type="button" id="book-write-mode" data-i18n="bookWrite">Khud likhein</button></div>
 <p id="book-count"></p>
 <div class="mala-panel"><div id="mala-beads" aria-hidden="true"></div><p id="mala-progress" role="status"></p></div><div class="book-paper"><div id="book-sheet" role="button" tabindex="0" aria-label="Tap the page to write one name"><div class="page-heading" data-i18n="bookInvocation">Naam smaran</div><div id="book-entries"></div><p class="page-hint" data-i18n="bookPageHint">Tap anywhere on the page</p></div><nav class="book-pages" aria-label="Pages"><button type="button" id="book-prev" data-i18n="bookPrev">Pichhla</button><span id="book-page-label"></span><button type="button" id="book-next" data-i18n="bookNext">Agla</button></nav></div>
 <button type="button" id="book-tap-area" data-i18n="bookTapHint">Yahan kahin bhi tap karein · ek naam likhega</button>
 <section id="book-handwriting" hidden><p data-i18n="bookWriteHint">Ungli se naam likhein, phir Agla naam dabayein.</p><canvas id="book-canvas" width="900" height="270" data-i18n-aria="bookCanvas" aria-label="Write a name"></canvas><div class="book-actions"><button type="button" id="book-clear" data-i18n="bookClear">Mitaayein</button><button type="button" id="book-confirm" data-i18n="bookConfirm">Agla naam · +1</button></div></section>
 <details class="book-tools"><summary data-i18n="bookTools">Writing options</summary><div class="book-actions"><button type="button" id="book-undo" data-i18n="bookUndo">Undo</button><button type="button" id="book-export" data-i18n="bookExport">Backup download</button></div>
 </details><p id="book-status" role="status" aria-live="polite"></p><p class="book-note" data-i18n="bookNote">Pustak ki ginti alag hai. Isi phone par save hoti hai.</p>
</dialog>
<script src="book.js?v=2"></script>
</body>
</html>

