// ============================================================================
//  Teacher App — easy use (owner, 5 Nov 2026: "back button response nahi, page back slide, smart view, dashboard
//  adhoora; logo ko pata hi nahi chal raha kaise use kare — do all the thing"). Loaded AFTER teacher.js.
//
//  1. Back button (phone / APK / Chrome): every screen change is a history step. Back closes an open sheet first,
//     then goes to the previous screen; on Today it says "press back again" and the next back closes the app.
//  2. Swipe: left / right = next / previous tab; pull down at the top = refresh; drag a sheet down = close.
//  3. "← Back" bar on inner screens (child report, board, boys / girls, days not marked).
//  4. "?" in the header: simple help for the screen that is open; a short guide the first time on a phone.
//  5. Principal mode → Home (vPdash): today's school at a glance, approvals (leave, lesson plan) with buttons,
//     alerts and quick actions.
//  Wraps go() and render() from teacher.js; data and records are unchanged.
// ============================================================================
'use strict';
(function () {
  let POP = false, SHEET_ST = false, GOING = false, REPLACE = false, INIT = false, DIR = '';
  const state = () => ({ ta: 1, t: TAB, p: JSON.parse(JSON.stringify(P || {})) });
  const tabsNow = () => (pMode() ? PTABS : TABS).map(x => x[0]);
  const tabOf = t => t === 'notes' ? 'plan' : (t === 'board' && !pMode()) ? 'home' : t;
  const _go = go, _render = render;
  window.go = go = function (tab, p) {
    const a = tabsNow().indexOf(tabOf(TAB)), b = tabsNow().indexOf(tabOf(tab)); DIR = a >= 0 && b >= 0 && a !== b ? (b > a ? 'l' : 'r') : '';
    GOING = true; REPLACE = false; _go(tab, p); GOING = false;
    if (POP || !INIT) return;
    try { REPLACE ? history.replaceState(state(), '') : history.pushState(state(), ''); } catch (e) {}
    REPLACE = false;
  };
  window.render = render = function () {
    _render();
    if (!S) { INIT = false; return; }
    if (!INIT) { INIT = true; try { history.replaceState({ ta: 1, root: 1 }, ''); history.pushState(state(), ''); } catch (e) {} }
    if (SHEET && !SHEET_ST) { SHEET_ST = true; if (!POP) try { history.pushState(Object.assign(state(), { sheet: 1 }), ''); } catch (e) {} }
    else if (!SHEET && SHEET_ST) { SHEET_ST = false; if (GOING) REPLACE = true; else if (!POP) { SKIP++; history.back(); } }
    uxDecorate();
  };
  let SKIP = 0, EXIT_AT = 0;
  window.addEventListener('popstate', e => {
    if (SKIP) { SKIP--; return; }
    if (!S) return;
    POP = true;
    try {
      if (SHEET || SHEET_ST) { SHEET = null; SHEET_ST = false; render(); return; }
      const st = e.state || {};
      if (st.ta && !st.root && st.t) { DIR = 'r'; TAB = st.t; P = st.p || {}; store.set('ta_tab', TAB); LASTTAB = null; render(); const sc = document.querySelector('.scroll'); if (sc) sc.scrollTop = 0; return; }
      const home = pMode() ? 'pdash' : 'home';
      if (TAB !== home || Object.keys(P || {}).length) { DIR = 'r'; TAB = home; P = {}; LASTTAB = null; render(); POP = false; try { history.pushState(state(), ''); } catch (er) {} return; }
      if (Date.now() - EXIT_AT > 2500) { EXIT_AT = Date.now(); toast('Press back again to close the app.'); }
    } finally { POP = false; }
  });
  // swipe
  let TS = null;
  document.addEventListener('touchstart', e => {
    if (!S || e.touches.length !== 1) { TS = null; return; }
    const t = e.target; if (t.closest && t.closest('input,textarea,select,.qn,.reg,.bd-graph,.chips,.seg,.tiles,.calendar-grid,.hwb,.pick,.res,.weekbar')) { TS = null; return; }
    const sc = document.querySelector('.scroll'), sh = t.closest && t.closest('.sheet');
    TS = { x: e.touches[0].clientX, y: e.touches[0].clientY, at: Date.now(), sheet: !!sh, top: sh ? sh.scrollTop : (sc ? sc.scrollTop : 0) };
  }, { passive: true });
  document.addEventListener('touchend', e => {
    const s = TS; TS = null; if (!s) return; const t = e.changedTouches[0], dx = t.clientX - s.x, dy = t.clientY - s.y;
    if (Date.now() - s.at > 800) return;
    if (s.sheet) { if (dy > 90 && Math.abs(dx) < 70 && s.top <= 0) closeSheet(); return; }
    if (SHEET) return;
    if (Math.abs(dx) > 80 && Math.abs(dy) < 45) { const L = tabsNow(); let i = L.indexOf(tabOf(TAB)); if (i < 0) return; i += dx < 0 ? 1 : -1; if (i >= 0 && i < L.length) go(L[i]); return; }
    if (dy > 120 && Math.abs(dx) < 60 && s.top <= 0) { toast('Getting the latest…'); flush(); refresh(); }
  }, { passive: true });

  // decorations after every draw: slide direction, back bar, help button, first-time guide
  function innerTitle() {
    if (TAB === 'att' && P.sid) return 'Student report';
    if (TAB === 'att' && P.m === 'pending') return 'Days not marked';
    if (TAB === 'prin' && P.screen === 'staff') return 'Staff & arrangement';
    if (TAB === 'prin' && P.screen === 'tt') return 'Timetable';
    if (TAB === 'board' && P.m === 'gen') return 'Boys / girls';
    if (TAB === 'board' && !pMode()) return 'School board';
    return '';
  }
  function uxDecorate() {
    const body = document.querySelector('.body');
    if (body && DIR && body.classList.contains('anim')) { body.classList.add('sl-' + DIR); }
    DIR = '';
    const tt = innerTitle();
    if (body && tt && !body.querySelector('.backbar')) body.insertAdjacentHTML('afterbegin', `<div class="backbar"><button type="button" onclick="history.back()" aria-label="Back">${ic('back')} Back</button><b>${esc(tt)}</b></div>`);
    const loc = document.querySelector('.head .loc'), lang = loc && loc.querySelector('.lang');
    if (lang && !loc.querySelector('.helpb')) lang.insertAdjacentHTML('beforebegin', '<button class="helpb" onclick="openHelp()" aria-label="Help">?</button>');
    if (typeof afterRender === 'function') { /* teacher-work.js already ran through the original render */ }
    maybeGuide();
  }
  function maybeGuide() {
    if (!ME || SHEET || navigator.webdriver || document.getElementById('ta-welcome')) return;
    const k = 'ta_guide_v1_' + ME.role + ME.tid; if (store.get(k)) return;
    store.set(k, '1'); setTimeout(() => { if (!SHEET && !document.getElementById('ta-welcome')) openGuide(); }, 400);
  }
})();

// ---------------------------------------------------------------- help
const HELP = {
  home: ['Today — aaj ka din', [['My work check', 'Sabse upar aaj ke kaam ki list hai: haziri, har bell ki diary, daily check, homework check. ✕ wale kaam par tap karo — seedha wahi screen khulti hai. Sab ✓ ho jaye to aaj ka kaam poora.'], ['Quick buttons', 'Rangeen buttons se seedha Attendance, Diary, Check work, Syllabus, Students, Paper, Board, Parents, Staff chat khulte hain.'], ['Board', 'School mein aapki class kis number par hai, aur star bachche kaun hain.']]],
  att: ['Attendance — haziri', [['Mark', 'Sab bachche pehle se Present hain. Jo absent hai uske photo par ek baar tap = A (absent), do baar = L (leave). Neeche Submit dabao. Galti ho to 10 minute tak Undo.'], ['Daily check', 'Diary, ID card, kitaab, dress — sab pehle se ✓. Jo nahi laaya sirf uska button dabao, phir Save.'], ['Students', 'Har bachche ki report: haziri, homework, marks, record, achchi baat / shikayat, profile.'], ['Calendar', 'Mahine ka calendar. Kisi din par tap karke haziri lagao ya chhutti. "Holiday — dates" se kai din ki chhutti ek saath.'], ['Register', 'Poore mahine ka register — box par tap karke purane din badlo.']]],
  diary: ['Classroom — diary aur check work', [['Diary', 'Har bell ke card mein: topic, class work, homework. Chhote buttons se sentence apne aap judta hai. Q no. 6 7 8 9 dabao to "Q. 6–9" likh jaata hai. Class work likhte hi homework ke sujhav aate hain. Phir "I really taught" tick karke Save.'], ['Check work', 'Aaj jo homework check karna hai: har bachche par ✓ / ½ / ✗ aur copy check. Absent bachche apne aap hat jaate hain.']]],
  plan: ['Planner — lesson plan, syllabus, notes, paper', [['Lesson plan', '"Auto fill from syllabus" dabao — hafte ka plan ban jaata hai. Dekh kar "Send to Principal".'], ['Syllabus', 'Kitaab ka naam + kul chapter → Make rows → chapter ka naam aur page bharo. Ya contents page ki photo lo. Har chapter par UT-3 / UT-4 / Annual tick karke Save. 📄 + par chapter ka poora text paste karo.'], ['Notes', 'Kitaab ke panne ki photo se chapter ke notes.'], ['Paper', 'Exam chuno → chapter apne aap aa jaate hain → Make the paper → padh kar sudhaaro → Print.']]],
  notes: ['Notes', [['Notes', 'Kitaab ke panne ki photo lo — summary, points, shabd-arth, sawal-jawab ban jaate hain. Check karke Save; parents ko bhi dikhte hain.']]],
  res: ['Results — marks', [['Marks', 'Exam aur class chuno, har bachche ke number bharo (AB = absent). Sab subject ke number aate hi result, % aur rank apne aap.']]],
  conn: ['Connect — parents aur staff', [['Fee calls', 'Sirf class teacher: kis bachche ki kitni fee baaki, call karke result likho, demand bill bhejo.'], ['Messages', 'Attendance, homework nahi kiya, fee, PTM, shabashi — bachche chuno, WhatsApp par ek tap.'], ['Parents', 'Parents se chat.'], ['Staff chat', 'Staff room aur teacher se alag baat — sab record rehta hai.']]],
  pdash: ['Principal — Home', [['Aaj ka school', 'Gol nishaan: kitni classes ki haziri lagi, kitni diary bhari, homework check, daily check. Tap karo — Cross-check khulta hai.'], ['Approve karna', 'Teachers ki chhutti aur lesson plan yahin Approve / Wapas bhejo.'], ['Alerts', 'Kin teachers ka kaam baaki hai ("Remind all"), kaunse bachche 3 din se absent, kaun chhutti par.']]],
  prin: ['Cross-check', [['Kaise dekhein', 'Upar din chuno. Har teacher ka baaki kaam laal mein. "Message" se us teacher ko, "Remind all" se sabko unki list app mein chali jaati hai.'], ['Weekly / one-time', 'Lesson plan, syllabus, exam chapter — teacher ke card mein neeche.']]],
  board: ['Board — muqabla', [['Classes', 'Classes ki ranking 🥇🥈🥉 aur haziri ka graph. Score = haziri + homework + daily check + marks.'], ['Students', 'Poore school ke star bachche.'], ['All students', 'Har class mein kitne bachche; naam likh kar koi bhi bachcha dhoondho. Principal yahin boys / girls bharte hain.'], ['Teachers', 'Sirf Principal: kis teacher ne 7 din mein kitna kaam kiya.']]]
};
function openHelp() {
  const h = HELP[TAB] || HELP.home;
  SHEET = () => `<h2 data-notr>❓ ${esc(h[0])}</h2>${h[1].map(([t, x]) => `<div class="help-i" data-notr><b>${esc(t)}</b><p>${esc(x)}</p></div>`).join('')}
    <div class="help-i tip" data-notr><b>👆 Chalane ke tareeke</b><p>Ungli se left / right sarkao — agla / pichhla tab. Upar se neeche kheencho — naya data. Phone ka Back — pichhli screen. Popup ko neeche kheencho — band.</p></div>
    <button class="btn l full" onclick="openGuide()">Poora guide dekhein</button>`;
  render();
}
function openGuide() {
  const p = pMode() || isP();
  SHEET = () => `<h2 data-notr>👋 App kaise chalayein</h2>
    <ol class="guide" data-notr>
      <li><b>Subah:</b> Attendance → bachche pehle se Present → sirf absent par tap → Submit. ${p ? '' : '(class teacher)'}</li>
      <li><b>Har bell ke baad:</b> Classroom → us bell ke card mein topic, class work, homework (buttons se) → Save.</li>
      <li><b>Homework check:</b> Classroom → Check work → ✓ / ½ / ✗ → Save.</li>
      <li><b>Daily check:</b> Attendance → Daily check → jo cheez nahi laaya sirf wo dabao → Save.</li>
      <li><b>Hafte mein ek baar:</b> Planner → Lesson plan → Auto fill → Send. Syllabus mein chapter aur exam tick.</li>
      <li><b>Today page</b> par "My work check" batata hai kya baaki hai — ✕ par tap karo, wahi khulega.</li>
      ${p ? '<li><b>Principal:</b> upar Teacher / Principal switch. Principal → Home par aaj ka school, approvals aur alerts. Cross-check se "Remind all".</li>' : ''}
    </ol>
    <div class="help-i tip" data-notr><b>Har screen par ?</b><p>Upar "?" dabao — us screen ki madad. Language: EN / Hinglish / हिंदी button.</p></div>
    <button class="btn go full" onclick="closeSheet()">Samajh gaya</button>`;
  render();
}

// ---------------------------------------------------------------- Principal Home
function threeDayAbsent(maxN) {
  const t = todayISO(), out = [];
  (S.classes || []).forEach(k => { const days = []; for (let i = 0; i < 20 && days.length < 3; i++) { const d = addDays(t, -i); if (d < (loadedFrom || '')) break; if (isWorkDay(d, k.c) && attOf(d, k.c)) days.push(d); }
    if (days.length < 3) return; const sets = days.map(d => new Set(attOf(d, k.c).absent || []));
    [...sets[0]].filter(id => sets[1].has(id) && sets[2].has(id)).forEach(id => { const s = studentById(id); if (s) out.push({ s, c: k.c }); }); });
  return out.slice(0, maxN || 30);
}
function vPdash() {
  const t = todayISO(), X = needXC(t), D = prinData(t), work = isWorkDay(t);
  const cls = (S.classes || []).filter(k => studentsOf(k.c).length && isWorkDay(t, k.c));
  const attDone = cls.filter(k => attOf(t, k.c)).length, kids = cls.reduce((n, k) => n + studentsOf(k.c).length, 0);
  const present = cls.reduce((n, k) => { const a = attOf(t, k.c); return n + (a ? studentsOf(k.c).length - (a.absent || []).length : 0); }, 0), inMarked = cls.filter(k => attOf(t, k.c)).reduce((n, k) => n + studentsOf(k.c).length, 0);
  let bAll = 0, bDone = 0, hAll = 0, hDone = 0;
  Object.values(D.per).forEach(o => (o.items || []).forEach(w => { if (w.kind === 'diary') { bAll++; if (w.done) bDone++; } if (w.kind === 'hwchk') { hAll++; if (w.done) hDone++; } }));
  const kitDone = D.X ? cls.filter(k => D.X.kit[k.c]).length : null;
  const pendT = Object.values(D.per).filter(o => o.pend.length && o.t !== me());
  const leaves = S.leaves.filter(l => l.status === 'Pending').sort((a, b) => String(a.from).localeCompare(String(b.from)));
  const plans = Object.entries(S.lplan).filter(([, p]) => p.status === 'Submitted' && !Object.prototype.hasOwnProperty.call(p, 'days')).sort((a, b) => String(b[1].week).localeCompare(String(a[1].week)));
  const away = staffAbsent(t), abs3 = threeDayAbsent(12), notMarked = cls.filter(k => !attOf(t, k.c));
  const ring = (l, a, b, sub) => `<button type="button" class="pd-r" onclick="go('prin')"><div class="ring ${b && a === b ? 'okr' : ''}" style="--p:${b ? Math.round(a / b * 100) : 0}"><span>${b === null ? '…' : b ? a + '/' + b : '—'}</span></div><b>${l}</b><small>${sub}</small></button>`;
  return `${typeof actBanner === 'function' ? actBanner(t) : ''}<div class="card pd-hero"><small>${esc(fmtLong(t))}</small><h3>${work ? 'Aaj ka school' : dayIdx(t) < 0 ? 'Sunday' : 'Holiday — ' + esc(holTitle(holidayOn(t)))}</h3>
      ${work ? `<div class="pd-big"><b>${inMarked ? present + '/' + inMarked : '—'}</b><span>students present${attDone < cls.length ? ` · ${cls.length - attDone} class(es) not marked` : ''} · ${kids} in school</span></div>
      <div class="pd-rings">${ring('Attendance', attDone, cls.length, 'classes')}${ring('Diary', bDone, bAll, 'bells over')}${ring('HW check', hDone, hAll, 'due today')}${ring('Daily check', kitDone ?? 0, D.X ? cls.length : null, 'classes')}</div>` : ''}</div>
    ${leaves.length || plans.length ? `<div class="card"><h3>Approve karna hai <small>${leaves.length + plans.length}</small></h3>
      ${leaves.map(l => `<div class="pd-ap"><div class="t"><b>${esc(T(l.tid).name)} — leave</b><span>${esc(fmtDate(l.from))}${l.to !== l.from ? ' – ' + esc(fmtDate(l.to)) : ''} · ${esc(l.reason || '')}</span></div><div class="btns"><button class="btn s go" onclick="pdLeave(${jsq(l.id)},'Approved')">Approve</button><button class="btn s w" onclick="pdLeave(${jsq(l.id)},'Rejected')">Reject</button></div></div>`).join('')}
      ${plans.slice(0, 15).map(([k, p]) => `<div class="pd-ap"><div class="t"><b>${esc(T(p.tid).name)} — lesson plan</b><span>Class ${esc(p.cls)} ${esc(p.subj)} · week of ${esc(fmtDate(p.week))} · ${String(p.topics || '').split('\n').filter(Boolean).length} topic(s)</span></div><button class="btn s l" onclick="pdPlan(${jsq(k)})">Open</button></div>`).join('')}</div>` : ''}
    <div class="card"><h3>Alerts</h3>
      ${work && pendT.length ? `<div class="pd-al warn"><span>⏳</span><div><b>${pendT.length} teacher(s) have work pending</b><small>${pendT.slice(0, 4).map(o => esc(T(o.t).name)).join(', ')}${pendT.length > 4 ? '…' : ''}</small></div><button class="btn s go" onclick="prinRemindAll('${t}')">Remind all</button></div>` : work ? '<div class="pd-al ok"><span>✓</span><div><b>Sab teachers ka aaj ka kaam ab tak poora</b></div></div>' : ''}
      ${work && notMarked.length && nowMin() >= toMin(S.settings.attendanceCutoff || '09:30') ? `<div class="pd-al bad"><span>📋</span><div><b>Attendance not marked</b><small>${notMarked.map(k => 'Class ' + esc(k.c)).join(', ')}</small></div></div>` : ''}
      ${away.length ? `<div class="pd-al"><span>🧑‍🏫</span><div><b>On leave / absent today</b><small>${away.map(x => esc(T(x).name)).join(', ')}</small></div><button class="btn s l" onclick="go('staff')">Arrangement</button></div>` : ''}
      ${abs3.length ? `<div class="pd-al bad"><span>⚠</span><div><b>${abs3.length} child(ren) absent 3 days in a row</b><small>${abs3.map(x => `<a href="#" onclick="go('att',{c:${jsq(x.c)},m:'stu',sid:${jsq(x.s.id)}});return false">${esc(x.s.name)} (${esc(x.c)})</a>`).join(', ')}</small></div></div>` : ''}
      ${!pendT.length && !abs3.length && !away.length && !notMarked.length && !work ? '<p class="mut" style="margin:0">No alerts.</p>' : ''}</div>
    ${boardCard()}
    <div class="card quick-card"><div class="quick-grid">${[['comp', 'Competition', 'chart', {}], ['spday', 'Special day', 'sun', {}], ['staff', 'Arrangement', 'list', {}], ['tt', 'Timetable', 'note', {}], ['prin', 'Cross-check', 'check', {}], ['att', 'Holiday', 'list', { m: 'cal' }], ['board', 'Board', 'chart', {}], ['board', 'Boys / girls', 'list', { m: 'gen' }], ['conn', 'Staff chat', 'chat', { m: 'staff' }], ['conn', 'Messages', 'wa', { m: 'msg' }]].map(([tb, l, i, p]) => `<button type="button" onclick='go(${JSON.stringify(tb)},${JSON.stringify(p)})'><span>${ic(i)}</span>${l}</button>`).join('')}</div></div>
    ${X && X.loading ? '<p class="mut">Loading daily checks and homework checks…</p>' : ''}`;
}
function pdLeave(id, st) {
  const l = S.leaves.find(x => x.id === id); if (!l) return;
  if (!confirm((st === 'Approved' ? 'Approve' : 'Reject') + ' leave of ' + T(l.tid).name + '?')) return;
  put('leave', id, Object.assign({}, l, { status: st, decidedAt: Date.now() }), 'Leave', `${st} leave of ${T(l.tid).name} (${l.from} to ${l.to})` + (st === 'Approved' ? ' — auto-marked absent & arrangement created' : '') + ' — Teacher App');
  toast('Leave ' + st.toLowerCase() + '.'); render();
}
function pdPlan(key) {
  const p = S.lplan[key]; if (!p) return;
  SHEET = () => `<h2>Lesson plan</h2><p class="mut" style="margin:0">${esc(T(p.tid).name)} · Class ${esc(p.cls)} ${esc(p.subj)} · week of ${esc(fmtDate(p.week))}</p>
    <ol class="pd-topics">${String(p.topics || '').split('\n').filter(Boolean).map(x => `<li>${esc(x)}</li>`).join('')}</ol>${p.goal ? `<p><b>Aim / activity:</b> ${esc(p.goal)}</p>` : ''}
    <div class="fld">Feedback (needed only to send back)<input id="pdFb" class="inp" maxlength="300" placeholder="What should the teacher change?"></div>
    <div class="btns"><button class="btn go" onclick="pdPlanDo(${jsq(key)},'Approved')">Approve</button><button class="btn w" onclick="pdPlanDo(${jsq(key)},'Revise')">Send back</button></div>`;
  render();
}
function pdPlanDo(key, st) {
  const p = S.lplan[key]; if (!p) return; const fb = (($('pdFb') || {}).value || '').trim().slice(0, 300);
  if (st === 'Revise' && !fb) return toast('Write what the teacher should change.');
  put('lplan', key, Object.assign({}, p, { status: st, feedback: st === 'Revise' ? fb : '', decidedAt: Date.now() }), 'Lesson Plan', `${st === 'Approved' ? 'Approved' : 'Sent back'}: ${T(p.tid).name}, Class ${p.cls} ${p.subj}, week of ${p.week}${fb && st === 'Revise' ? ' — ' + fb : ''} — Teacher App`);
  SHEET = null; toast(st === 'Approved' ? 'Approved.' : 'Sent back to the teacher.'); render();
}
