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
<meta name="theme-color" content="#B8235A">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Rozha+One&family=Hind:wght@400;500;600&display=swap">
<link rel="stylesheet" href="jyotish.css?v=2">
</head>
<body data-ai="<?= $aiOn ? '1' : '0' ?>" style="margin:0">
<div class="wrap">
  <header class="mast">
    <div class="brand"><h1>Maakit Jyotish</h1><span class="dev">माकित ज्योतिष</span></div>
    <div class="powered">Powered by <b>Maakit</b> · Bhadohi</div>
    <div class="panch" id="panch">Loading today's panchang…</div>
    <label class="langpick">Language
      <select id="lang"><option>Hinglish</option><option value="Hindi (Devanagari script)">हिंदी</option><option>English</option></select></label>
  </header>

  <div class="card notice" id="notice" hidden>
    <p style="margin:0">Maakit Jyotish saves anonymous usage to learn what people need: which features are used, birth place, rashi, birth year, and the questions you ask the AI. <b>Never your name, exact birth date or time, or your check-in mood.</b> Please don't write names or phone numbers in questions.</p>
    <div class="row"><button class="btn" type="button" id="share-yes">OK</button><button class="btn ghost" type="button" id="share-no">Don't send</button></div>
  </div>

  <!-- AAJ: check-in + daily card -->
  <section class="tab" id="t-aaj">
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

  <div class="foot">Maakit Jyotish V2 · Vedic sidereal (Lahiri) · Whole-sign houses · Powered by Maakit</div>
</div>

<nav class="tabs" aria-label="Sections"><div class="in" role="tablist">
  <button role="tab" data-t="aaj" aria-selected="true"><span class="d">आज</span>Today</button>
  <button role="tab" data-t="kundli" aria-selected="false"><span class="d">कुंडली</span>Kundli</button>
  <button role="tab" data-t="milan" aria-selected="false"><span class="d">मिलान</span>Milan</button>
  <button role="tab" data-t="rashi" aria-selected="false"><span class="d">राशिफल</span>Rashifal</button>
  <button role="tab" data-t="ai" aria-selected="false"><span class="d">पूछें</span>Ask AI</button>
</div></nav>

<script src="astronomy.min.js?v=2.1.19"></script>
<script src="engine.js?v=1"></script>
<script src="daily.js?v=2"></script>
<script src="app.js?v=2"></script>
</body>
</html>
