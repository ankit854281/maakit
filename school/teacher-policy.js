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
    d.innerHTML = `<div class="lp-box"><div class="lp-head"><b>Staff leave rules</b><span>Leave niyam · Version ${p.policy.ver}</span></div>
      <p class="lp-hi">Namaste ${esc(myName())} — app istemal karne se pehle ye niyam padhiye aur sehmati dijiye.</p>
      <ol class="lp-list">${p.rules.map(r => `<li>${esc(r)}</li>`).join('')}</ol>
      <label class="lp-ok"><input type="checkbox" id="lpTick" onchange="document.getElementById('lpBtn').disabled=!this.checked"> Maine sab niyam padh liye hain aur main inse sehmat hoon.</label>
      <button class="btn go full" id="lpBtn" disabled onclick="lpAccept()">Main sehmat hoon — app kholiye</button>
      <p class="mut" style="margin:6px 0 0;text-align:center">Aapki sehmati samay ke saath school record me rakhi jayegi.</p></div>`;
    document.body.appendChild(d);
  }
  window.lpAccept = async function () {
    const b = document.getElementById('lpBtn'); if (b) { b.disabled = true; b.textContent = 'Saving…'; }
    try { await api('teacher_api.php?a=accept', { ver: LP.policy.ver }); LP.accepted = true; gateDone = ME.tid; const g = document.getElementById('lpGate'); if (g) g.remove(); toast('Dhanyavaad — niyam save ho gaye.'); }
    catch (e) { toast(e.message); if (b) { b.disabled = false; b.textContent = 'Main sehmat hoon — app kholiye'; } if (/changed/.test(e.message)) { const g = document.getElementById('lpGate'); if (g) g.remove(); LP = null; gate(); } }
  };
  setInterval(gate, 3000);

  // ---------- 2. rules sheet (anyone) ----------
  window.lpRules = async function () {
    try { await lpLoad(); } catch (e) { return toast(e.message); }
    SHEET = () => `<div class="row"><h2>Staff leave rules</h2><button class="btn s l" onclick="closeSheet()">Close</button></div>
      <ol class="lp-list">${LP.rules.map(r => `<li>${esc(r)}</li>`).join('')}</ol>
      ${LP.acceptedAt ? `<p class="mut">You accepted these rules on ${esc(new Date(LP.acceptedAt).toLocaleString('en-IN'))}.</p>` : ''}`;
    render();
  };

  // My account → Apply for leave: one line of the rules + "Read rules"
  const _openMe = openMe;
  openMe = function () {
    _openMe.apply(this, arguments);
    const f = SHEET; if (typeof f !== 'function') return;
    const h = (LP && LP.policy) ? LP.policy.noticeHour : 7, n = (LP && LP.policy) ? LP.policy.autoDays : 2;
    SHEET = () => f().replace('onclick="leaveApply()">Send to Principal</button>', `onclick="leaveApply()">Send leave request</button>
      <p class="mut lp-note">Kal ya aage ki chhutti, ya aaj ki ${h}:00 baje se pehle — ${n ? n + ' din tak apne aap approve.' : 'time par.'} ${h}:00 ke baad aaj ki chhutti = late (Principal decide karenge). <a href="#" onclick="lpRules();return false">Read rules</a></p>`);
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
    return `<div class="card lp-rep"><h3>Leave report <small>${esc(ymName(ym))} · ${R.daysSoFar} of ${R.workDays} school days so far</small></h3>
      <div class="lp-seg">${[ymNow(), ymPrev(ymNow()), ymPrev(ymPrev(ymNow()))].map(m => `<button class="${m === ym ? 'on' : ''}" onclick="lpYm('${m}')">${esc(ymName(m))}</button>`).join('')}</div>
      <div class="lp-kpi"><span class="g"><b>${bonus}</b>Full attendance (bonus ₹${p.bonus})</span><span class="r"><b>${bad}</b>Absent without permission</span><span class="${notOk.length ? 'r' : 'g'}"><b>${R.rows.length - notOk.length}/${R.rows.length}</b>Rules accepted</span></div>
      <div class="lp-tw"><table class="lp-t"><thead><tr><th>Teacher</th><th>Leave</th><th>Half</th><th>No permission</th><th>Late asks</th><th>Bonus</th></tr></thead><tbody>
      ${R.rows.map(r => `<tr class="${r.warn ? 'warn' : ''}"><td><b>${esc(r.name)}</b>${r.accepted ? '' : '<small class="no">rules not accepted</small>'}${r.warn ? '<small class="no">give written warning</small>' : ''}</td>${cell(r.leave, 'lv')}${cell(r.half, 'lv')}${cell(r.unapproved, 'no')}${cell(r.late, 'no')}${cell(r.bonus ? '₹' + r.bonus : 0, 'ok')}</tr>`).join('')}</tbody></table></div>
      <p class="mut" style="margin:6px 0 0">Salary uses the same numbers by itself: paid leave covers leave + half days; "No permission" days are always cut; full attendance adds the bonus.</p></div>
      ${ME.role === 'principal' ? setCard(p) : ''}`;
  }
  function setCard(p) {
    return `<div class="card"><h3>Leave rules <small>version ${p.ver}</small></h3>
      <div class="two"><div class="fld">On-time before (hour)<input id="lpH" type="number" min="0" max="23" class="inp" value="${p.noticeHour}"></div><div class="fld">Auto-approve up to (days)<input id="lpD" type="number" min="0" max="10" class="inp" value="${p.autoDays}"></div></div>
      <div class="two" style="margin-top:8px"><div class="fld">Full-attendance bonus (₹)<input id="lpB" type="number" min="0" max="5000" class="inp" value="${p.bonus}"></div><div class="fld">Warning after (days without permission)<input id="lpW" type="number" min="1" max="10" class="inp" value="${p.warnAfter}"></div></div>
      <div class="two" style="margin-top:8px"><button class="btn go" onclick="lpSave()">Save rules</button><button class="btn l" onclick="lpPrint()">Print rules</button></div>
      <button class="btn l full" style="margin-top:8px" onclick="lpRules()">See the rules teachers read</button>
      <p class="mut" style="margin:6px 0 0">Changing a rule makes every teacher read and accept the rules again in the app.</p></div>`;
  }
  window.lpSave = async function () {
    const g = id => document.getElementById(id).value;
    if (!confirm('Save the new leave rules? Every teacher will be asked to accept them again.')) return;
    try { await api('teacher_api.php?a=policyset', { noticeHour: +g('lpH'), autoDays: +g('lpD'), bonus: +g('lpB'), warnAfter: +g('lpW') }); LP = null; LPREP = {}; toast('Rules saved.'); render(); } catch (e) { toast(e.message); }
  };
  window.lpPrint = async function () {
    try { await lpLoad(true); } catch (e) { return toast(e.message); }
    const school = (S.settings && S.settings.school) || 'S.N. Mishra Singapore International School';
    const names = Object.entries(S.teachers || {}).filter(([k]) => !((S.settings || {}).leftTeachers || {})[k]).map(([, t]) => t.name).sort();
    const w = window.open('', '_blank'); if (!w) return toast('Allow pop-ups to print.');
    w.document.write(`<!doctype html><html><head><meta charset="utf-8"><title>Staff leave rules</title><style>body{font-family:Arial,sans-serif;margin:24px;color:#111}h1{font-size:18px;text-align:center;margin:0}h2{font-size:15px;text-align:center;margin:4px 0 14px}ol li{margin:6px 0;line-height:1.45}table{width:100%;border-collapse:collapse;margin-top:16px}td,th{border:1px solid #888;padding:7px;font-size:13px;text-align:left}.s{display:flex;justify-content:space-between;margin-top:40px}</style></head><body>
      <h1>${esc(school)}, Bhadohi</h1><h2>STAFF LEAVE RULES (Version ${LP.policy.ver}) — ${esc(new Date().toLocaleDateString('en-IN'))}</h2>
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
