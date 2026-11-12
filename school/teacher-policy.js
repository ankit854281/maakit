/* ============================================================================
   Leave policy in the Teacher App (12 Nov 2026, owner: "long term solution —
   sab automated karo, kisi ki jarurat na pade"). Server rules: leavepolicy_lib.php.

   • Teacher: on opening the app the rules appear ONCE (and again when the
     Principal changes them). The app is blocked until "Main sehmat hoon"
     (teacher_api.php?a=accept → kind tchAccept, kept with the time).
   • My account → Apply for leave shows the rules in one line + "Read rules".
     On-time short leave is approved by the server itself; late leave waits.
   • Principal mode → Staff → Leave: month report per teacher (leave, half day,
     absent without permission, late leave, bonus, rules accepted), rule settings
     and a print copy of the rules with signature lines.
   ============================================================================ */
(function () {
  let LP = null, LPREP = {}, LPYM = null, gateBusy = false, gateDone = '';
  const ymNow = () => todayISO().slice(0, 7);
  const ymPrev = ym => { const [y, m] = ym.split('-').map(Number); return m === 1 ? (y - 1) + '-12' : y + '-' + String(m - 1).padStart(2, '0'); };
  const ymName = ym => new Date(ym + '-01T00:00:00').toLocaleDateString('en-IN', { month: 'long', year: 'numeric' });

  async function lpLoad(force) { if (LP && !force) return LP; LP = await api('teacher_api.php?a=policy'); window.LPPOL = LP.policy; return LP; }
  window.lpLoad = lpLoad;

  // ---------- 1. one-time "I agree" for teachers ----------
  async function gate() {
    if (S && ME && !LP && ME.role !== 'teacher') lpLoad().catch(() => {});
    if (gateBusy || !S || !ME || ME.role !== 'teacher' || gateDone === ME.tid) return;
    gateBusy = true;
    try {
      const p = await lpLoad(true);
      if (p.accepted) { gateDone = ME.tid; return; }
      showGate(p);
    } catch (e) { /* offline: ask next time */ } finally { gateBusy = false; }
  }
  function showGate(p) {
    if (document.getElementById('lpGate')) return;
    const d = document.createElement('div'); d.id = 'lpGate';
    d.innerHTML = `<div class="lp-box"><div class="lp-head"><b>Staff Rules</b><span>Leave · Salary · Conduct · Results — Version ${p.policy.ver}</span></div>
      <p class="lp-hi">${esc(myName())}, read every rule below and accept it to continue using the Teacher App.</p>
      <ol class="lp-list">${p.rules.map(r => `<li>${esc(r)}</li>`).join('')}</ol>
      <label class="lp-ok"><input type="checkbox" id="lpTick" onchange="document.getElementById('lpBtn').disabled=!this.checked"> I have read all the rules above and I agree to follow them.</label>
      <button class="btn go full" id="lpBtn" disabled onclick="lpAccept()">I agree — open the app</button>
      <p class="mut" style="margin:6px 0 0;text-align:center">Your acceptance is saved in the school record with the date and time.</p></div>`;
    document.body.appendChild(d);
  }
  window.lpAccept = async function () {
    const b = document.getElementById('lpBtn'); if (b) { b.disabled = true; b.textContent = 'Saving…'; }
    try { await api('teacher_api.php?a=accept', { ver: LP.policy.ver }); LP.accepted = true; gateDone = ME.tid; const g = document.getElementById('lpGate'); if (g) g.remove(); toast('Thank you — your acceptance is saved.'); }
    catch (e) { toast(e.message); if (b) { b.disabled = false; b.textContent = 'I agree — open the app'; } if (/changed/.test(e.message)) { const g = document.getElementById('lpGate'); if (g) g.remove(); LP = null; gate(); } }
  };
  setInterval(gate, 3000);

  // ---------- 2. rules sheet (anyone) ----------
  window.lpRules = async function () {
    try { await lpLoad(); } catch (e) { return toast(e.message); }
    SHEET = () => `<div class="row"><h2>Staff Rules</h2><button class="btn s l" onclick="closeSheet()">Close</button></div>
      <ol class="lp-list">${LP.rules.map(r => `<li>${esc(r)}</li>`).join('')}</ol>
      ${LP.acceptedAt ? `<p class="mut">You accepted these rules on ${esc(new Date(LP.acceptedAt).toLocaleString('en-IN'))}.</p>` : ''}`;
    render();
  };

  // My account → Apply for leave: one line of the rules + "Read rules"
  const _openMe = openMe;
  openMe = function () {
    _openMe.apply(this, arguments);
    const f = SHEET; if (typeof f !== 'function') return;
    const q = (LP && LP.policy) || { noticeHour: 7, noticeDays: 2, emergency: 1, maxLeave: 4 };
    SHEET = () => f().replace('onclick="leaveApply()">Send to Principal</button>', `onclick="leaveApply()">Send leave request</button>
      <p class="mut lp-note">Staff rules: apply ${q.noticeDays} days in advance · ${q.emergency} emergency leave a month, before ${q.noticeHour}:00 a.m. · no leave on Monday, Saturday or programme days · more than ${q.maxLeave} leave in a month = salary only for days worked. Valid only after the Principal approves. <a href="#" onclick="lpRules();return false">Read all rules</a></p>`);
    render();
  };

  // ---------- 3. Principal / Chairman: month report + settings ----------
  async function repLoad(ym) {
    if (LPREP[ym] === 'busy') return; LPREP[ym] = 'busy';
    try { LPREP[ym] = await api('teacher_api.php?a=leavereport&ym=' + ym); } catch (e) { LPREP[ym] = { err: e.message }; }
    if (typeof TAB !== 'undefined' && TAB === 'staffhub') render();
  }
  window.lpYm = function (ym) { LPYM = ym; if (!LPREP[ym] || LPREP[ym].err) repLoad(ym); render(); };
  function repCard() {
    const ym = LPYM || ymNow(), R = LPREP[ym];
    if (!R) { repLoad(ym); return '<div class="card"><h3>Leave report</h3><p class="mut">Opening…</p></div>'; }
    if (R === 'busy') return '<div class="card"><h3>Leave report</h3><p class="mut">Opening…</p></div>';
    if (R.err) return `<div class="card"><h3>Leave report</h3><p class="mut">${esc(R.err)}</p></div>`;
    const p = R.policy, notOk = R.rows.filter(r => !r.accepted), bonus = R.rows.filter(r => r.bonus).length, bad = R.rows.filter(r => r.unapproved).length;
    const cell = (v, cls) => `<td class="${v ? cls : 'z'}">${v || '–'}</td>`;
    let W = {}; try { if (typeof teacherRanks === 'function') teacherRanks().forEach(w => { W[w.tid] = w.p; }); } catch (e) {}   // app work, last 7 days (same as Staff → Work)
    const wk = tid => W[tid] === undefined ? '<td class="z">–</td>' : `<td class="${W[tid] >= 90 ? 'ok' : W[tid] >= 70 ? 'lv' : 'no'}">${W[tid]}%</td>`;
    return `<div class="card lp-rep"><h3>Leave report <small>${esc(ymName(ym))} · ${R.daysSoFar} of ${R.workDays} school days so far</small></h3>
      <div class="lp-seg">${[ymNow(), ymPrev(ymNow()), ymPrev(ymPrev(ymNow()))].map(m => `<button class="${m === ym ? 'on' : ''}" onclick="lpYm('${m}')">${esc(ymName(m))}</button>`).join('')}</div>
      <div class="lp-kpi"><span class="g"><b>${bonus}</b>Full attendance (bonus ₹${p.bonus})</span><span class="r"><b>${bad}</b>Absent without permission</span><span class="${notOk.length ? 'r' : 'g'}"><b>${R.rows.length - notOk.length}/${R.rows.length}</b>Rules accepted</span></div>
      <div class="lp-tw"><table class="lp-t"><thead><tr><th>Teacher</th><th>App work 7d</th><th>Leave</th><th>Half</th><th>No permission</th><th>Late asks</th><th>Bonus</th></tr></thead><tbody>
      ${R.rows.map(r => `<tr class="${r.warn ? 'warn' : ''}"><td><b>${esc(r.name)}</b>${r.accepted ? '' : '<small class="no">rules not accepted</small>'}${r.warn ? '<small class="no">give written warning</small>' : ''}</td>${wk(r.tid)}${cell(r.leave, 'lv')}${cell(r.half, 'lv')}${cell(r.unapproved, 'no')}${cell(r.late, 'no')}${cell(r.bonus ? '₹' + r.bonus : 0, 'ok')}</tr>`).join('')}</tbody></table></div>
      <p class="mut" style="margin:6px 0 0">App work = attendance, diary, daily check and homework check done in the app in the last 7 days (below 70% in red). Salary uses the same leave numbers by itself: paid leave covers leave + half days; "No permission" days are always cut; full attendance adds the bonus.</p></div>
      ${ME.role === 'principal' ? setCard(p) : ''}`;
  }
  function setCard(p) {
    return `<div class="card"><h3>Leave rules <small>version ${p.ver}</small></h3>
      <div class="two"><div class="fld">On-time before (hour)<input id="lpH" type="number" min="0" max="23" class="inp" value="${p.noticeHour}"></div><div class="fld">Auto-approve up to (days)<input id="lpD" type="number" min="0" max="10" class="inp" value="${p.autoDays}"></div></div>
      <div class="two" style="margin-top:8px"><div class="fld">Full-attendance bonus (₹)<input id="lpB" type="number" min="0" max="5000" class="inp" value="${p.bonus}"></div><div class="fld">Warning after (days without permission)<input id="lpW" type="number" min="1" max="10" class="inp" value="${p.warnAfter}"></div></div>
      <div class="two" style="margin-top:8px"><div class="fld">Apply how many days ahead<input id="lpN" type="number" min="1" max="15" class="inp" value="${p.noticeDays}"></div><div class="fld">Emergency leave a month<input id="lpE" type="number" min="0" max="5" class="inp" value="${p.emergency}"></div></div>
      <div class="fld" style="margin-top:8px">Leave in a month above which salary = days worked only<input id="lpM" type="number" min="1" max="15" class="inp" value="${p.maxLeave}"></div>
      <div class="two" style="margin-top:8px"><button class="btn go" onclick="lpSave()">Save rules</button><button class="btn l" onclick="lpPrint()">Print rules</button></div>
      <button class="btn l full" style="margin-top:8px" onclick="lpRules()">See the rules teachers read</button>
      <p class="mut" style="margin:6px 0 0">Changing a rule makes every teacher read and accept the rules again in the app.</p></div>`;
  }
  window.lpSave = async function () {
    const g = id => document.getElementById(id).value;
    if (!confirm('Save the new leave rules? Every teacher will be asked to accept them again.')) return;
    try { await api('teacher_api.php?a=policyset', { noticeHour: +g('lpH'), autoDays: +g('lpD'), bonus: +g('lpB'), warnAfter: +g('lpW'), noticeDays: +g('lpN'), emergency: +g('lpE'), maxLeave: +g('lpM') }); LP = null; LPREP = {}; toast('Rules saved.'); render(); } catch (e) { toast(e.message); }
  };
  window.lpPrint = async function () {
    try { await lpLoad(true); } catch (e) { return toast(e.message); }
    const school = (S.settings && S.settings.school) || 'S.N. Mishra Singapore International School';
    const names = Object.entries(S.teachers || {}).filter(([k]) => !((S.settings || {}).leftTeachers || {})[k]).map(([, t]) => t.name).sort();
    const w = window.open('', '_blank'); if (!w) return toast('Allow pop-ups to print.');
    w.document.write(`<!doctype html><html><head><meta charset="utf-8"><title>Staff leave rules</title><style>body{font-family:Arial,sans-serif;margin:24px;color:#111}h1{font-size:18px;text-align:center;margin:0}h2{font-size:15px;text-align:center;margin:4px 0 14px}ol li{margin:6px 0;line-height:1.45}table{width:100%;border-collapse:collapse;margin-top:16px}td,th{border:1px solid #888;padding:7px;font-size:13px;text-align:left}.s{display:flex;justify-content:space-between;margin-top:40px}</style></head><body>
      <h1>${esc(school)}, Bhadohi</h1><h2>STAFF RULES (Version ${LP.policy.ver}) — ${esc(new Date().toLocaleDateString('en-IN'))}</h2>
      <ol>${LP.rules.map(r => `<li>${esc(r)}</li>`).join('')}</ol>
      <table><thead><tr><th style="width:40px">S.No</th><th>Teacher name</th><th style="width:120px">Date</th><th style="width:170px">Signature</th></tr></thead><tbody>${names.map((n, i) => `<tr><td>${i + 1}</td><td>${esc(n)}</td><td></td><td></td></tr>`).join('')}</tbody></table>
      <div class="s"><span>Principal ______________</span><span>Chairman ______________</span></div><p style="font-size:10px;color:#666;text-align:center;margin-top:20px">Powered by Maakit</p>
      <script>window.onload=()=>window.print()<\/script></body></html>`);
    w.document.close();
  };

  if (typeof vStaffHub === 'function') {
    const _vsh = vStaffHub;
    vStaffHub = function () { const html = _vsh.apply(this, arguments); return (P && P.m === 'leave') ? html + repCard() : html; };
  }

  // styles (kept here so no other CSS file changes)
  const st = document.createElement('style');
  st.textContent = `#lpGate{position:fixed;inset:0;z-index:9999;background:rgba(10,20,35,.72);display:flex;align-items:flex-end;justify-content:center}
  #lpGate .lp-box{background:#fff;width:100%;max-width:560px;max-height:92vh;overflow:auto;border-radius:22px 22px 0 0;padding:18px 16px 20px}
  .lp-head{display:flex;flex-direction:column;gap:2px;border-bottom:2px solid #F2A93B;padding-bottom:8px}.lp-head b{font-size:19px;color:#17324D}.lp-head span{font-size:13px;color:#667}
  .lp-hi{font-size:14px;margin:10px 0 4px}.lp-list{padding-left:20px;margin:6px 0 10px}.lp-list li{font-size:14px;line-height:1.5;margin:6px 0}
  .lp-ok{display:flex;gap:8px;align-items:flex-start;font-size:14px;font-weight:600;background:#FFF6E5;border-radius:12px;padding:10px;margin:6px 0 10px}.lp-ok input{width:20px;height:20px;margin-top:1px}
  .lp-note{font-size:13px;margin:8px 0 0}.lp-seg{display:flex;gap:6px;margin:4px 0 10px;flex-wrap:wrap}.lp-seg button{border:1px solid #cfd6de;background:#fff;border-radius:99px;padding:5px 11px;font:inherit;font-size:13px}.lp-seg button.on{background:#17324D;color:#fff;border-color:#17324D}
  .lp-kpi{display:grid;grid-template-columns:repeat(3,1fr);gap:6px;margin-bottom:10px}.lp-kpi span{border-radius:12px;padding:8px;font-size:12px;line-height:1.3}.lp-kpi b{display:block;font-size:20px}.lp-kpi .g{background:#E6F6EC;color:#14532d}.lp-kpi .r{background:#FDECEC;color:#8a1c1c}
  .lp-tw{overflow-x:auto}.lp-t{width:100%;border-collapse:collapse;font-size:13px}.lp-t th{background:#17324D;color:#fff;padding:6px;text-align:left;font-weight:600}.lp-t td{border-bottom:1px solid #e5e9ee;padding:6px;vertical-align:top}.lp-t td small{display:block;font-size:11px}
  .lp-t td.no,.lp-t small.no{color:#b42318;font-weight:700}.lp-t td.ok{color:#15803d;font-weight:700}.lp-t td.lv{color:#9a6700;font-weight:700}.lp-t td.z{color:#aab}.lp-t tr.warn td{background:#FFF1F0}`;
  document.head.appendChild(st);
})();

/* ============================================================================
   Leave assistant (12 Nov 2026, owner: "bot add kardo — vo reason de, bot leave
   likh de, uske according class arrangement ho jaye").
   Today tab → "Apply for leave": dates + reason in a few words (Hindi / Hinglish /
   English) → the app writes a formal application, checks the staff rules before
   sending and shows how many periods will need an arrangement. The Principal reads
   the full application under Staff → Leave. Once approved, the arrangement is made
   by itself (staffAbsent counts approved leave).
   + Principal: Staff → "Staff attendance" opens staffatt.php (all staff, bulk / CSV).
   ============================================================================ */
(function () {
  const REASONS = [['ill', 'Not well / fever'], ['func', 'Family function / wedding'], ['med', 'Doctor / hospital'], ['emer', 'Family emergency'], ['death', 'Death in family'], ['exam', 'Exam / interview'], ['pers', 'Urgent personal work']];
  const KW = [['death', /death|died|nidhan|dehant|maut|shraddh|antim|funeral|गुज़र|निधन/i], ['ill', /fever|bukhar|bimar|beemar|sick|tabiyat|tabiat|cold|cough|pet dard|headache|dard|बीमार|बुखार|तबीयत/i],
    ['med', /doctor|hospital|checkup|check-up|operation|clinic|dawai|medical|डॉक्टर|अस्पताल/i], ['func', /shadi|shaadi|wedding|marriage|function|tilak|mundan|engagement|sagai|birthday|puja|pooja|शादी|समारोह/i],
    ['exam', /exam|interview|pariksha|test|admit|परीक्षा/i], ['emer', /emergency|urgent|accident|achanak|turant|ज़रूरी|दुर्घटना/i]];
  const SENT = { ill: 'as I am not keeping well and the doctor has advised me rest', func: 'as I have to attend an important family function', med: 'as I have to visit the doctor / hospital for a medical check-up', emer: 'due to a sudden family emergency',
    death: 'due to the sad demise of a close family member', exam: 'as I have to appear in an examination / interview', pers: 'due to some urgent personal work that needs my presence' };
  let BOT = { from: '', to: '', kind: '', words: '', letter: '' };
  const days = (a, b) => Math.round((new Date(b + 'T00:00:00') - new Date(a + 'T00:00:00')) / 864e5) + 1;
  const dLong = iso => new Date(iso + 'T00:00:00').toLocaleDateString('en-IN', { day: '2-digit', month: 'long', year: 'numeric' });
  function guessKind(t) { for (const [k, re] of KW) if (re.test(t)) return k; return t.trim() ? 'own' : 'pers'; }
  function writeLetter() {
    const f = BOT.from, t = BOT.to, n = days(f, t), me_ = T(me()), kind = BOT.kind || guessKind(BOT.words);
    const why = kind === 'own' ? 'due to ' + BOT.words.trim().replace(/[.\s]+$/, '') : SENT[kind];
    const ct = (typeof myCT === 'function' ? myCT() : []), school = (S.settings && S.settings.school) || 'S.N. Mishra Singapore International School';
    return `To,\nThe Principal,\n${school},\nBhadohi (U.P.)\n\nDate: ${dLong(todayISO())}\n\nSubject: Application for leave ${n > 1 ? 'from ' + dLong(f) + ' to ' + dLong(t) + ' (' + n + ' days)' : 'on ' + dLong(f)}\n\nRespected Sir / Madam,\n\nWith due respect, I, ${myName()}, ${me_.role ? me_.role + ' teacher' : 'teacher'}${ct.length ? ' and class teacher of Class ' + ct.join(', ') : ''}, request you to kindly grant me leave ${n > 1 ? 'for ' + n + ' days from ' + dLong(f) + ' to ' + dLong(t) : 'for one day on ' + dLong(f)} ${why}.${BOT.words.trim() && kind !== 'own' ? '\n(Details: ' + BOT.words.trim() + ')' : ''}\n\nI shall complete my pending work in the Teacher App before the leave and after I return. My periods may kindly be given as arrangement to other teachers.\n\nKindly grant me leave for the above period.\n\nThanking you,\n\nYours obediently,\n${myName()}`;
  }
  function checks() {
    const out = [], f = BOT.from, t = BOT.to, q = (window.LPPOL || { noticeHour: 7, noticeDays: 2, emergency: 1 });
    if (!f || !t || t < f) return { bad: 'Choose the From and To dates.', out };
    for (let d = f, i = 0; d <= t && i < 62; i++, d = addDays(d, 1)) { const w = new Date(d + 'T00:00:00').getDay(); if (w === 1 || w === 6) return { bad: 'Staff rules: no leave on a ' + (w === 1 ? 'Monday' : 'Saturday') + ' (' + fmtDate(d) + '). Change the dates.', out };
      const p = (S.cal || []).find(e => ['Exam', 'Event', 'PTM', 'Activity'].includes(e.type) && e.date <= d && (e.to || e.date) >= d); if (p) return { bad: 'Staff rules: no leave on a school programme day — ' + p.title + ' (' + fmtDate(d) + ').', out }; }
    const ahead = Math.round((new Date(f + 'T00:00:00') - new Date(todayISO() + 'T00:00:00')) / 864e5);
    if (ahead < q.noticeDays) { if (f < todayISO() || (f === todayISO() && new Date().getHours() >= q.noticeHour)) return { bad: 'Emergency leave must be asked before ' + q.noticeHour + ':00 a.m. on the same day.', out };
      const used = S.leaves.filter(l => l.tid === me() && l.emergency && l.status !== 'Rejected' && (l.from || '').slice(0, 7) === f.slice(0, 7)).length;
      if (used >= q.emergency) return { bad: 'You have already used your ' + q.emergency + ' emergency leave this month. Planned leave must be asked ' + q.noticeDays + ' days in advance.', out };
      out.push(['warn', 'Emergency leave (less than ' + q.noticeDays + ' days ahead) — only ' + q.emergency + ' a month.']); }
    else out.push(['ok', 'Planned leave — asked ' + ahead + ' days in advance.']);
    let per = 0; for (let d = f, i = 0; d <= t && i < 62; i++, d = addDays(d, 1)) { try { per += bellsOf(me(), d).length; } catch (e) {} }
    out.push(['ok', per ? per + ' of your periods on these days will be given to other teachers by itself once the Principal approves.' : 'No periods of yours on these days.']);
    const n = days(f, t), monthUsed = S.leaves.filter(l => l.tid === me() && l.status === 'Approved' && (l.from || '').slice(0, 7) === f.slice(0, 7)).reduce((a, l) => a + days(l.from > f.slice(0, 7) + '-01' ? l.from : f.slice(0, 7) + '-01', l.to), 0);
    if (monthUsed + n > ((window.LPPOL || {}).maxLeave || 4)) out.push(['warn', 'With this leave you cross ' + ((window.LPPOL || {}).maxLeave || 4) + ' days this month — salary will be paid only for the days worked.']);
    return { bad: '', out };
  }
  window.lpBot = function (keep) {
    if (!keep) BOT = { from: addDays(todayISO(), 2), to: addDays(todayISO(), 2), kind: '', words: '', letter: '' };
    const c = checks();
    SHEET = () => `<div class="row"><h2>Apply for leave</h2><button class="btn s l" onclick="closeSheet()">Close</button></div>
      <div class="two"><div class="fld">From<input id="bF" type="date" class="inp" value="${BOT.from}" onchange="lpBotSet()"></div><div class="fld">To<input id="bT" type="date" class="inp" value="${BOT.to}" onchange="lpBotSet()"></div></div>
      <div class="fld" style="margin-top:8px">Reason — tap one or write in your own words (Hindi / Hinglish / English)</div>
      <div class="lpb-chips">${REASONS.map(([k, l]) => `<button class="${BOT.kind === k ? 'on' : ''}" onclick="lpBotKind('${k}')">${esc(l)}</button>`).join('')}</div>
      <input id="bW" class="inp" maxlength="200" placeholder="e.g. bhai ki shaadi hai / bukhar hai" value="${esc(BOT.words)}" oninput="BOTW(this.value)">
      ${c.bad ? `<div class="lpb-msg bad">${esc(c.bad)}</div>` : c.out.map(([k, m]) => `<div class="lpb-msg ${k}">${esc(m)}</div>`).join('')}
      <button class="btn l full" style="margin-top:8px" ${c.bad ? 'disabled' : ''} onclick="lpBotWrite()">✍ Write my application</button>
      ${BOT.letter ? `<div class="fld" style="margin-top:10px">Your application (you can change it)<textarea id="bL" class="inp lpb-letter" rows="14" oninput="BOTL(this.value)">${esc(BOT.letter)}</textarea></div>
        <button class="btn go full" style="margin-top:8px" ${c.bad ? 'disabled' : ''} onclick="lpBotSend()">Send to Principal</button>` : ''}
      <p class="mut" style="margin:8px 0 0">Leave is valid only after the Principal approves it. <a href="#" onclick="lpRules();return false">Staff rules</a></p>`;
    render();
  };
  window.BOTW = v => { BOT.words = v; };
  window.BOTL = v => { BOT.letter = v; };
  window.lpBotSet = () => { BOT.from = $('bF').value; BOT.to = $('bT').value; if (BOT.to < BOT.from) BOT.to = BOT.from; if (BOT.letter) BOT.letter = writeLetter(); lpBot(true); };
  window.lpBotKind = k => { BOT.kind = BOT.kind === k ? '' : k; const w = $('bW'); if (w) BOT.words = w.value; if (BOT.letter) BOT.letter = writeLetter(); lpBot(true); };
  window.lpBotWrite = () => { const w = $('bW'); if (w) BOT.words = w.value; if (!BOT.kind && !BOT.words.trim()) return toast('Tap a reason or write it in a few words.'); BOT.letter = writeLetter(); lpBot(true); };
  window.lpBotSend = () => {
    const c = checks(); if (c.bad) return toast(c.bad);
    const L = $('bL'); if (L) BOT.letter = L.value; const short = (BOT.kind && BOT.kind !== 'own' ? (REASONS.find(r => r[0] === BOT.kind) || ['', ''])[1] : '') + (BOT.words.trim() ? (BOT.kind ? ' — ' : '') + BOT.words.trim() : '');
    const id = uid(); put('leave', id, { id, tid: me(), from: BOT.from, to: BOT.to, reason: (short || 'Leave').slice(0, 200), letter: BOT.letter.slice(0, 3000), status: 'Pending', at: Date.now() }, 'Leave', `${myName()} applied for leave ${BOT.from} to ${BOT.to}: ${short} — Teacher App (application)`);
    S.leaves.sort((a, b) => (b.at || 0) - (a.at || 0)); SHEET = null; toast('Application sent. You will see Approved / Rejected in My account.'); render();
  };
  // Today tab: a clear "Apply for leave" button for teachers (+ last request status)
  if (typeof vHome === 'function') {
    const _vh = vHome;
    vHome = function () { const html = _vh.apply(this, arguments);
      if (!S || !ME || (typeof pMode === 'function' && pMode())) return html;
      const last = S.leaves.filter(l => l.tid === me())[0];
      const card = `<div class="card lpb-home"><div class="row"><div><b>Need leave?</b><small>${last ? 'Last request ' + esc(fmtDate(last.from)) + ': ' + esc(last.status) + (last.rejectReason ? ' — ' + esc(last.rejectReason) : '') : 'Write the reason in a few words — the app writes the application.'}</small></div><button class="btn go" onclick="lpBot()">Apply for leave</button></div></div>`;
      return card + html; };
  }
  // Principal Staff hub: + "Staff attendance" (all staff, bulk / CSV) and the full applications
  if (typeof vStaffHub === 'function') {
    const _v = vStaffHub;
    vStaffHub = function () {
      if (P && P.m === 'satt') return seg([['arr', 'Arrangement'], ['tt', 'Timetable'], ['team', 'Teachers'], ['work', 'Work'], ['leave', 'Leave'], ['satt', 'Staff attendance']], 'satt', `(k=>go('staffhub',{m:k}))`) +
        `<div class="card" style="padding:0;overflow:hidden"><iframe src="staffatt.php?embed=1" style="width:100%;height:78vh;border:0;display:block" title="Staff attendance"></iframe></div><p class="mut">Teachers, drivers, Dai, watchman, office — tap boxes, press Save. CSV download / upload for many days at once. <a href="entrylog.php" target="_blank">Who entered what →</a></p>`;
      let html = _v.apply(this, arguments).replace(/(<div class="seg">[\s\S]*?Leave<\/button>)/, `$1<button onclick="go('staffhub',{m:'satt'})">Staff attendance</button>`);
      if (P && P.m === 'leave') { const pend = S.leaves.filter(l => l.status === 'Pending' && l.letter);
        if (pend.length) html += `<div class="card"><h3>Applications <small>${pend.length}</small></h3>${pend.map(l => `<details class="lpb-app"><summary><b>${esc(T(l.tid).name)}</b> · ${esc(fmtDate(l.from))}${l.to !== l.from ? ' – ' + esc(fmtDate(l.to)) : ''}${l.emergency ? ' · emergency' : ''}</summary><pre>${esc(l.letter)}</pre></details>`).join('')}</div>`; }
      return html; };
  }
  const st = document.createElement('style');
  st.textContent = `.lpb-chips{display:flex;flex-wrap:wrap;gap:6px;margin:6px 0 8px}.lpb-chips button{border:1px solid #cfd6de;background:#fff;border-radius:99px;padding:6px 11px;font:inherit;font-size:13px}.lpb-chips button.on{background:#17324D;color:#fff;border-color:#17324D}
  .lpb-msg{margin-top:8px;border-radius:10px;padding:8px 10px;font-size:13px}.lpb-msg.ok{background:#E6F6EC;color:#14532d}.lpb-msg.warn{background:#FFF4D6;color:#7a4d00}.lpb-msg.bad{background:#FDECEC;color:#8a1c1c;font-weight:600}
  .lpb-letter{font-family:inherit;font-size:13px;line-height:1.45;white-space:pre-wrap}.lpb-home .row{display:flex;align-items:center;justify-content:space-between;gap:10px}.lpb-home small{display:block;color:#667;font-size:12px;margin-top:2px}
  .lpb-app{border-top:1px solid #eef1f4;padding:8px 0}.lpb-app summary{cursor:pointer;font-size:14px}.lpb-app pre{white-space:pre-wrap;font-family:inherit;font-size:13px;background:#f7f8fa;border-radius:10px;padding:10px;margin:8px 0 0}`;
  document.head.appendChild(st);
})();
