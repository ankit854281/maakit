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
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<!-- Fonts load without blocking the page: slow 4G par pehle text dikhe, font baad mein. -->
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Tiro+Devanagari+Hindi&family=Mukta:wght@400;500;700&display=swap" media="print" onload="this.media='all'">
<link rel="stylesheet" href="jyotish.css?v=3">
</head>
<body data-ai="<?= $aiOn ? '1' : '0' ?>">
<div class="wrap">
  <header class="mast">
    <div class="brand"><h1>Maakit Jyotish</h1><span class="dev">माकित ज्योतिष</span></div>
    <label class="langpick"><span id="language-label" class="sr-only">Language</span>
      <select id="lang" aria-label="Language"><option>Hinglish</option><option value="Hindi (Devanagari script)">हिंदी</option><option>English</option></select></label>
    <div class="panch" id="panch">Loading today's panchang…</div>
  </header>

  <div class="card notice" id="notice" hidden>
    <p style="margin:0">Maakit Jyotish saves anonymous usage to learn what people need: which features are used, birth place, rashi, birth year, and the questions you ask the AI. <b>Never your name, exact birth date or time, or your check-in mood.</b> Please don't write names or phone numbers in questions.</p>
    <div class="row"><button class="btn" type="button" id="share-yes">OK</button><button class="btn ghost" type="button" id="share-no">Don't send</button></div>
  </div>

  <!-- AAJ: check-in + daily card -->
  <section class="tab" id="t-aaj">
    <section class="home-jaap-spotlight" aria-labelledby="home-jaap-heading">
      <div class="jaap-head">
        <h2 id="home-jaap-heading">Aaj ka Jaap</h2>
        <p id="home-jaap-description">Mantra chunein aur beech ke gol par tap karein. 108 manke = ek mala.</p>
      </div>
      <div class="mala" id="home-mala">
        <svg class="mala-ring" id="home-ring" viewBox="0 0 300 300" aria-hidden="true"></svg>
        <button class="mala-disk" id="home-jaap-tap" type="button" aria-label="Naam jaap ek baar ginen"><span class="mala-photo" aria-hidden="true"></span><b class="mala-name" id="home-jaap-name">राम</b><strong id="home-jaap-count" aria-live="polite">0</strong><small id="home-jaap-tap-label">Tap karke ginein</small></button>
      </div>
      <div class="mala-meta"><span id="home-mala-line">Mala 1 · 0 / 108</span><span><span id="home-jaap-count-label">Aaj ki ginti</span> <b id="home-jaap-total">0</b></span></div>
      <div class="mantra-chips" id="home-jaap-chips" role="radiogroup" aria-labelledby="home-jaap-mantra-label"></div>
      <label class="sr-only" for="home-jaap-mantra" id="home-jaap-mantra-label">Aaj ka mantra</label>
      <select id="home-jaap-mantra" hidden><option value="राम">राम</option><option value="ॐ नमः शिवाय">ॐ नमः शिवाय</option><option value="हरे कृष्ण">हरे कृष्ण</option><option value="जय माता दी">जय माता दी</option></select>
      <div class="jaap-foot"><small id="home-jaap-private-note">Ginti isi phone par rehti hai.</small><button class="textbtn" id="home-jaap-options" type="button">Jaap ke aur tareeke</button></div>
    </section>

    <section class="welcome-hero" aria-labelledby="welcome-title">
      <h2 id="welcome-title">Namaste! Aaj kis baat par clarity chahiye?</h2>
      <p id="welcome-description">Career, rishton, kundli ya aaj ke din par sawaal chunein.</p>
      <button class="askbar" id="home-primary" type="button" data-home-action="ai"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3l1.8 5.2L19 10l-5.2 1.8L12 17l-1.8-5.2L5 10l5.2-1.8z"/></svg><span id="home-primary-label">Apna sawaal poochhein</span></button>
      <p class="home-trustline" id="home-trustline">Aapki janam details isi phone par rehti hain.</p>
    </section>

    <section class="home-module-section" aria-labelledby="home-module-title">
      <h2 id="home-module-title">Ek feature chunein</h2>
      <div class="home-modules" id="welcome-actions" aria-label="Jyotish features">
        <button class="module-card module-ai" type="button" data-home-action="ai"><span class="module-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M4 5.5A2.5 2.5 0 0 1 6.5 3h11A2.5 2.5 0 0 1 20 5.5v8a2.5 2.5 0 0 1-2.5 2.5H10l-5 4v-4.3A2.5 2.5 0 0 1 4 13.5z"/><path d="M12 6.5l.9 2.3 2.3.9-2.3.9-.9 2.3-.9-2.3-2.3-.9 2.3-.9z"/></svg></span><b id="home-ai-title">AI Jyotishi</b><small id="home-ai-note">Career, rishton ya faisle par apni baat likhein</small></button>
        <button class="module-card module-kundli" type="button" data-home-action="kundli"><span class="module-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="1"/><path d="M3 3l18 18M21 3L3 21M12 3l9 9-9 9-9-9z"/></svg></span><b id="home-kundli-title">Janam Kundli</b><small id="home-kundli-note">Graha, rashi aur dasha samjhein</small></button>
        <button class="module-card module-milan" type="button" data-home-action="milan"><span class="module-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="9" cy="12" r="6"/><circle cx="15" cy="12" r="6"/></svg></span><b id="home-milan-title">Kundli Milan</b><small id="home-milan-note">36-gun milan ko context ke saath dekhein</small></button>
        <button class="module-card module-rashi" type="button" data-home-action="rashi"><span class="module-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="4"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3M4.9 4.9l2.1 2.1M17 17l2.1 2.1M4.9 19.1L7 17M17 7l2.1-2.1"/></svg></span><b id="home-rashi-title">Aaj ka Rashifal</b><small id="home-rashi-note">Aaj ka sanket aur ek chhota upaya</small></button>
      </div>
    </section>

    <section class="jaap-card" id="jaap-card" aria-labelledby="jaap-title" hidden>
      <div class="jaap-card-copy"><h2 id="jaap-title">Naam Jaap</h2><p id="jaap-home-description">Aaram se apna jaap sankalp poora karein. Chahein to baad mein 30-second friendly challenge bhi khel sakte hain.</p><div class="jaap-today-total"><span id="jaap-home-today-label">Aaj ke naam</span> <strong id="jaap-today">0</strong></div><div class="jaap-insights"><div class="jaap-insights-head"><b id="jaap-home-week-label">Pichhle 7 din</b><span><strong id="jaap-week-sessions">0</strong> <span id="jaap-home-sessions-label">sessions</span></span></div><div class="jaap-week-bars" id="jaap-week-bars" aria-hidden="true"></div><p class="note" id="jaap-week-summary" aria-live="polite"></p><p class="note jaap-private-note" id="jaap-home-private">Aapki progress isi phone par private rehti hai.</p></div></div>
      <div class="jaap-setup">
        <label>Kaunsa naam japna hai?
          <select id="jaap-mantra"><option value="राम">राम</option><option value="ॐ नमः शिवाय">ॐ नमः शिवाय</option><option value="हरे कृष्ण">हरे कृष्ण</option><option value="जय माता दी">जय माता दी</option><option value="__custom__">Apna mantra</option></select></label><label id="jaap-custom-mantra-wrap" hidden>Apna mantra likhein<input id="jaap-custom-mantra" type="text" maxlength="60" placeholder="Mantra yahan likhein" autocomplete="off"></label><div class="jaap-guide" id="jaap-guide"><b id="jaap-guide-title">Mantra ka bhaav</b><p id="jaap-guide-copy">Apna mantra chunne par uska chhota sa parichay yahan dikhega.</p><button class="btn ghost" id="jaap-ask-ai" type="button">Is mantra ke baare mein AI se poochhein</button></div>
        <label>Jaap ka tareeka
          <select id="jaap-mode"><option value="mala">Shaant mala · apna sankalp</option><option value="solo">30-second personal record</option><option value="duo">2-player friendly round</option></select>
        </label>
        <label>Aaj kis bhaav se?
          <select id="jaap-bhav"><option value="sahaj">Bas jaap, bina kisi khaas sankalp ke</option><option value="shanti">Shaanti</option><option value="aabhar">Aabhaar</option><option value="sahas">Sahas</option><option value="ekagrata">Ekagrata</option></select>
        </label>
        <label id="jaap-goal-wrap">Aaj ka sankalp
          <select id="jaap-goal"><option value="27">27 jaap</option><option value="54">54 jaap</option><option value="108" selected>108 jaap · 1 mala</option><option value="1008">1008 jaap</option><option value="custom">Apna target</option></select></label><label id="jaap-custom-goal-wrap" hidden>Apni ginti chunein<input id="jaap-custom-goal" type="number" min="1" max="1008" step="1" value="108" inputmode="numeric"></label>
        <div class="jaap-names" id="jaap-names" hidden>
          <label>Player 1<input id="jaap-player1" maxlength="18" placeholder="Pehle khiladi ka naam"></label>
          <label>Player 2<input id="jaap-player2" maxlength="18" placeholder="Doosre khiladi ka naam"></label>
        </div>
        <div class="jaap-photo-control"><div class="jaap-photo-actions"><button class="btn ghost" id="jaap-photo-choose" type="button">Bhagwan ki photo chunein</button><button class="linkbtn" id="jaap-photo-clear" type="button" hidden>Photo hataayein</button></div><input id="jaap-photo-file" type="file" accept="image/*" hidden><p class="note" id="jaap-photo-note">Photo isi phone par rahegi; upload nahi hogi.</p></div>
        <div class="jaap-audio-options">
          <label><input type="checkbox" id="jaap-bell" checked> Har tap par halki ghanti</label>
          <label><input type="checkbox" id="jaap-voice"> Naam ko bolkar bhi sunayein</label>
          <label><input type="checkbox" id="jaap-vibrate" checked> Mala poori hone par vibration</label>
        </div>
        <p class="note jaap-local-note">Koi jaldi ya streak ka pressure nahi. Ginti isi phone par rehti hai; public leaderboard nahi hai.</p>
        <button class="btn" id="jaap-start" type="button">Shaant jaap shuru karein</button>
      </div>
    </section>

    <section class="jaap-arena" id="jaap-arena" hidden aria-label="Naam Jaap game">
      <header class="jaap-topbar"><button class="jaap-exit" type="button" id="jaap-exit" aria-label="Jaap se bahar niklein">✕</button><div><div class="eyebrow" id="jaap-round-label">SHAANT MALA</div><strong id="jaap-current-player">Aapka sankalp</strong></div><button class="jaap-pause" id="jaap-pause" type="button" aria-pressed="false">⏸ Rokein</button><div class="jaap-clock" id="jaap-clock" aria-live="off" hidden>30</div></header>
      <div class="jaap-arena-main">
        <div class="mala mala-lg">
          <svg class="mala-ring" id="jaap-beads" viewBox="0 0 300 300" aria-hidden="true"></svg>
          <button class="jaap-tap mala-disk" id="jaap-tap" type="button" aria-label="Naam jaap count karne ke liye tap karein"><span class="mala-photo" aria-hidden="true"></span><b class="mala-name" id="jaap-tap-name">राम</b><small>Tap karke ginein</small></button>
        </div>
        <div class="jaap-score-panel"><span id="jaap-count-label">Aapka sankalp</span><strong id="jaap-count" aria-live="off">0</strong><small id="jaap-best-line" hidden>Personal best: <b id="jaap-best">0</b></small></div>
        <button class="jaap-undo" id="jaap-undo" type="button" aria-label="Aakhri ginti wapas karein" hidden>↶ Ek ginti hataayein</button>
        <p class="jaap-progress-note" id="jaap-progress-note">Phone ko landscape ghumayein aur anguthe se tap karein. Portrait mein bhi chalega.</p>
      </div>
      <div class="jaap-between" id="jaap-between" hidden aria-live="polite"><div class="jaap-result-card"><div class="eyebrow">ROUND POORA</div><h2 id="jaap-result-title"></h2><p id="jaap-result-copy"></p><button class="btn" id="jaap-next" type="button">Agla khiladi</button></div></div>
      <div class="jaap-between" id="jaap-final" hidden aria-live="polite"><div class="jaap-result-card"><div class="eyebrow" id="jaap-final-label">SANKALP POORA</div><h2 id="jaap-final-title"></h2><div id="jaap-final-scores"></div><p id="jaap-final-copy"></p><div class="row"><a class="btn" id="jaap-share" target="_blank" rel="noopener">WhatsApp par share</a><button class="btn ghost" id="jaap-done" type="button">Ho gaya</button></div></div></div>
    </section>

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
      <div class="helpcall"><div><div class="eyebrow">Tele-MANAS</div><div class="big">14416</div><div class="note" id="help-tm"></div></div><a class="btn" href="tel:14416">Call 14416</a></div>
      <div class="helpcall"><div><div class="eyebrow" id="help-em"></div><div class="big">112</div></div><a class="btn ghost" href="tel:112">Call 112</a></div>
      <p class="note" id="help-friend" style="margin:0"></p>
      <button class="btn ghost" type="button" id="help-ok"></button>
    </div>
  </section>

  <!-- KUNDLI -->
  <section class="tab" id="t-kundli" hidden>
    <form class="card" id="f-k" autocomplete="off">
      <div class="row" style="justify-content:space-between"><h2>Janam Kundli</h2><span class="badge" id="ex-badge">Example chart</span></div>
      <p class="note" id="k-question-hint" hidden>Aapka sawaal surakshit hai. Apni janam jaankari bhariye; kundli banne ke baad sawaal chat mein draft rahega.</p>
      <div class="grid2">
        <label>Name<input id="k-name" required value="Example Person"></label>
        <label>Gender<select id="k-gender"><option>Male</option><option>Female</option><option>Other</option></select></label>
        <label>Date of birth<input id="k-date" type="date" required value="2001-01-15"></label>
        <label>Time of birth<input id="k-time" type="time" required value="08:30"></label>
        <label>Place of birth<select id="k-city"></select></label>
        <label>Time zone<select id="k-tz"><option value="5.5">IST (India, +5:30)</option><option value="5.75">Nepal (+5:45)</option><option value="6">Bangladesh (+6:00)</option><option value="4">UAE (+4:00)</option><option value="0">UK (GMT)</option><option value="-5">US Eastern (-5:00)</option></select></label>
      </div>
      <div class="grid2" id="k-custom" hidden>
        <label>Latitude<input id="k-lat" type="number" step="0.0001" value="25.3953"></label>
        <label>Longitude<input id="k-lon" type="number" step="0.0001" value="82.5683"></label>
      </div>
      <div class="row"><button class="btn" type="submit">Make my Kundli</button><span class="note">Your name and exact birth details stay on this phone.</span></div>
    </form>
    <div id="k-out" class="fade" style="display:flex;flex-direction:column;gap:18px"></div>
    <div class="card">
      <h3>Your data</h3>
      <p class="note" style="margin:0" id="priv-state"></p>
      <div class="row"><button class="btn ghost" type="button" id="priv-toggle"></button><button class="btn ghost" type="button" id="priv-del">Delete my data</button></div>
      <p class="note" id="priv-done" style="margin:0" hidden>Deleted. Everything saved on this phone and the usage log from this phone are gone.</p>
    </div>
  </section>

  <!-- MILAN -->
  <section class="tab" id="t-milan" hidden>
    <form class="card" id="f-m" autocomplete="off">
      <h2>Kundli Milan · 36 Gun</h2>
      <p class="note" style="margin:0">Enter both birth details. The example pair is pre-filled.</p>
      <div class="grid2">
        <div class="card" style="background:var(--sunk);border:0">
          <div class="eyebrow">Boy</div>
          <label>Name<input id="b-name" value="Rahul (example)"></label>
          <label>Date<input id="b-date" type="date" value="1998-06-12"></label>
          <label>Time<input id="b-time" type="time" value="06:45"></label>
          <label>Place<select id="b-city"></select></label>
        </div>
        <div class="card" style="background:var(--sunk);border:0">
          <div class="eyebrow">Girl</div>
          <label>Name<input id="g-name" value="Priya (example)"></label>
          <label>Date<input id="g-date" type="date" value="2000-02-03"></label>
          <label>Time<input id="g-time" type="time" value="14:20"></label>
          <label>Place<select id="g-city"></select></label>
        </div>
      </div>
      <div class="row"><button class="btn" type="submit">Check Gun Milan</button></div>
    </form>
    <div id="m-out" class="fade" style="display:flex;flex-direction:column;gap:18px"></div>
  </section>

  <!-- RASHIFAL -->
  <section class="tab" id="t-rashi" hidden>
    <div id="r-out" style="display:flex;flex-direction:column;gap:18px"></div>
  </section>

  <!-- AI -->
  <section class="tab" id="t-ai" hidden>
    <div class="card">
      <div class="row" style="justify-content:space-between"><h2>Ask AI Jyotishi</h2>
        <span class="note">Replies in the language chosen at the top</span></div>
      <p class="note" style="margin:0" id="ai-who"></p>
      <div class="mode-switch" role="group" aria-label="Choose a reading style">
        <button class="chip" type="button" id="mode-jyotish" aria-pressed="true">Jyotish reading</button>
        <button class="chip" type="button" id="mode-council" aria-pressed="false">Navagrah Council · Rishton ki clarity</button>
      </div>
      <p class="note" id="mode-note" style="margin:0"></p>
      <div id="council-context" hidden>
        <div class="grid2">
          <label>Current situation
            <select id="council-area">
              <option value="">Choose what fits (optional)</option>
              <option value="work or job">Work or job</option>
              <option value="studies">Studies</option>
              <option value="business">Business</option>
              <option value="relationship">Relationship</option>
              <option value="family">Family</option>
              <option value="money">Money</option>
              <option value="health">Health</option>
              <option value="another situation">Another situation</option>
            </select>
          </label>
          <label>What would help most?
            <select id="council-goal">
              <option value="">Choose a kind of help (optional)</option>
              <option value="understanding the situation">Understand the situation</option>
              <option value="one practical next step">One practical next step</option>
              <option value="a safe boundary">A safe boundary</option>
              <option value="calming down before deciding">Calm down before deciding</option>
            </select>
          </label>
        </div>
        <p class="note" style="margin:6px 0 0">Your message is the third part: describe what actually happened, in your own words.</p>
      </div>
      <div class="chips" id="sugg"></div>
      <div class="council-privacy" id="council-privacy" hidden>
        <p class="note" style="margin:0">Council mode sends your message, any context choices and a limited Moon-sign/Nakshatra summary to our AI provider for an answer. Maakit does not save Council question text in its Jyotish usage log. Please leave out names, phone numbers and identifying details.</p>
        <label class="consent"><input type="checkbox" id="council-consent"> <span>I understand and want to send this question for an AI answer.</span></label>
      </div>
    </div>
    <div class="chat-welcome" id="ai-chat-welcome">
      <div class="assistant-mark" aria-hidden="true">✦</div>
      <div><h3>Namaste! Aap apna sawaal bataiye.</h3>
        <p>Apni kundli ko parampara ke nazariye se samjhein aur jawaab ke saath ek practical agla kadam paayein. Pehle se bana example chart personal reading nahi hai.</p></div>
    </div>
    <div class="chat" id="chat"></div>
    <div class="note" id="ai-status"></div>
    <div class="row council-followups" id="council-followups" hidden aria-label="Ask a follow-up question">
      <button class="btn ghost" type="button" data-council-followup="rahu">Rahu · Red flags?</button>
      <button class="btn ghost" type="button" data-council-followup="chandra">Chandrama · Emotions?</button>
      <button class="btn ghost" type="button" data-council-followup="shani">Shani · Next boundary?</button>
    </div>
    <form class="composer" id="f-ai"><textarea id="q" placeholder="Type your question… e.g. Meri naukri kab lagegi?" aria-label="Your question"></textarea><button class="btn" id="send" type="submit">Ask</button></form>
    <p class="note">Readings follow classical Parashari Jyotish. They are guidance for reflection, not guaranteed predictions. For health, legal or money decisions, consult a qualified professional.</p>
    
  </section>

  <div class="foot">Powered by <b>Maakit</b>, Bhadohi<br>Vedic sidereal (Lahiri), whole-sign houses</div>
</div>
<div class="mini-jaap" id="mini-jaap">
      <button class="mini-jaap-toggle" id="mini-jaap-toggle" type="button" aria-expanded="false" aria-controls="mini-jaap-panel">ॐ <span>Naam Jaap</span></button>
      <div class="mini-jaap-panel" id="mini-jaap-panel" hidden>
        <div class="mini-jaap-head"><div><b>Naam Jaap</b><small>Chat ke saath · silent mode</small></div><button id="mini-jaap-close" type="button" aria-label="Jaap popup band karein">×</button></div>
        <label for="mini-jaap-mantra">Mantra</label><select id="mini-jaap-mantra"><option value="राम">राम</option><option value="ॐ नमः शिवाय">ॐ नमः शिवाय</option><option value="हरे कृष्ण">हरे कृष्ण</option><option value="जय माता दी">जय माता दी</option></select>
        <button class="mini-jaap-tap" id="mini-jaap-tap" type="button" aria-label="Naam jaap ek baar ginen"><span id="mini-jaap-count">0</span><small id="mini-jaap-name">राम</small><b>Tap karke ginein</b></button>
        <p class="note">Aaj ka jaap: <strong id="mini-jaap-today">0</strong> · ginti isi phone par</p><button class="btn ghost" id="mini-jaap-photo" type="button">Bhagwan ki photo chunein</button><button class="btn ghost mini-jaap-full" id="mini-jaap-full" type="button">Full Jaap modes kholein</button>
      </div>
    </div>

<nav class="tabs" aria-label="Main navigation" id="main-navigation"><div class="in" role="tablist">
  <button role="tab" data-t="aaj" aria-selected="true"><span class="nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="m3 10 9-7 9 7"/><path d="M5 9v11h14V9M9 20v-6h6v6"/></svg></span><span class="tab-label" id="tab-home-label">Home</span></button>
  <button role="tab" data-t="ai" aria-selected="false"><span class="nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M4 5.5A2.5 2.5 0 0 1 6.5 3h11A2.5 2.5 0 0 1 20 5.5v9a2.5 2.5 0 0 1-2.5 2.5H10l-5 4v-4.5A2.5 2.5 0 0 1 4 14z"/><path d="M8 8h8M8 12h5"/></svg></span><span class="tab-label" id="tab-chat-label">Chats</span></button>
  <button role="tab" data-t="kundli" aria-selected="false"><span class="nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M12 2.5 21 7v10l-9 4.5L3 17V7z"/><path d="m3 7 9 5 9-5M12 12v9.5M8.2 4.4l7.6 15.2"/></svg></span><span class="tab-label" id="tab-kundli-label">Kundli</span></button>
  <button role="tab" data-t="rashi" aria-selected="false"><span class="nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.4 1.4m11.34 11.34 1.4 1.4M2 12h2m16 0h2M4.93 19.07l1.4-1.4M17.67 6.33l1.4-1.4"/></svg></span><span class="tab-label" id="tab-today-label">Today</span></button>
</div></nav>

<script src="astronomy.min.js?v=2.1.19"></script>
<script src="engine.js?v=1"></script>
<script src="daily.js?v=2"></script>
<script src="app.js?v=3"></script>
</body>
</html>
