// ============================================================================
//  Teacher App — Coordinator (owner, 6 Nov 2026). The Principal names a teacher coordinator for some classes.
//  The app gives her the work — she does not pick whom to check:
//   · Copy check by rotation (teacher × subject least checked first), 5 children chosen by the app, 6 points each,
//     a photo of every copy, "kya achha tha" required → report to that teacher (👍 / ✋ Sahmat nahi → Principal decides)
//   · a blind re-check of one copy by another teacher (match out of 6)
//   · Diary check of the last school day (✓/✕ per entry + reminders to teachers with no diary)
//   · Reading levels (3 classes a week by rotation), Worksheets, Thought of the day (shown on everyone's Home)
//   · Weekly report card (targets vs done, re-check match, disputes) for her and the Principal
//  Data: teacher_api.php?a=coord (kind tchCoord). Loaded after teacher-plan.js.
// ============================================================================
'use strict';
let CO = null;
const CO_PTS = [['d', 'Taarikh + heading'], ['cw', 'Class work poora'], ['hw', 'Homework poora'], ['sg', 'Teacher ne check / sign kiya'], ['cr', 'Galti sahi ki + remark'], ['nt', 'Copy saaf, cover par naam']];
const CO_LV = [[1, 'Akshar'], [2, 'Shabd'], [3, 'Vaakya'], [4, 'Kahani'], [5, 'Dhaarapravaah']];
const CO_TGT = { cc: 5, dc: 6, sv: 6, ws: 3, rd: 3 };
const CO_SV = [['Mehnat ka phal meetha hota hai.', 'Effort — koshish'], ['Seekhna kabhi band mat karo.', 'Learn — seekhna'], ['Achhi aadatein hi asli daulat hain.', 'Habit — aadat'], ['Galti se seekho, haar mat maano.', 'Mistake — galti'],
  ['Saaf-safai mein hi sehat hai.', 'Clean — saaf'], ['Bado ka aadar, chhoton se pyaar.', 'Respect — aadar'], ['Kitaab sabse achhi dost hai.', 'Friend — dost'], ['Samay ki keemat pehchano.', 'Time — samay'],
  ['Sach bolna sabse badi himmat hai.', 'Truth — sach'], ['Har din ek nayi shuruaat hai.', 'Begin — shuru'], ['Sawaal poochhna hi gyaan ki pehli seedhi hai.', 'Question — sawaal'], ['Milkar kaam karo, aage badho.', 'Together — saath'],
  ['Paani bachao, kal bachao.', 'Save — bachana'], ['Ped lagao, dharti sajao.', 'Tree — ped'], ['Khush raho, khushi baanto.', 'Happy — khush'], ['Dhanyavaad kehna kabhi mat bhoolo.', 'Thank you — dhanyavaad'],
  ['Chhota kaam bhi poore mann se karo.', 'Care — dhyan'], ['Himmat karne walon ki haar nahi hoti.', 'Brave — bahadur'], ['Apna kaam khud karo.', 'Self — khud'], ['Achha socho, achha karo.', 'Kind — dayalu'],
  ['Padhai roz thodi, par roz.', 'Daily — roz'], ['Jo baante, wahi badhe.', 'Share — baantna'], ['Desh hamara, garv hamara.', 'Proud — garv'], ['Maa-baap ki seva sabse badi pooja.', 'Parents — mata-pita'],
  ['Khelo bhi, padho bhi.', 'Play — khelna'], ['Vinamrata insaan ka gehna hai.', 'Polite — vinamra'], ['Aaj ka kaam aaj karo.', 'Today — aaj'], ['Sapne dekho aur unhe poora karo.', 'Dream — sapna'], ['Dhairya rakho, safalta milegi.', 'Patience — dhairya'], ['Ek achha dost bano.', 'Help — madad']];
function needCo(force) {
  if (CO && !force && (CO.loading || CO.ok) && Date.now() - (CO.at || 0) < 90000) return CO;
  CO = Object.assign({ rows: {} }, CO || {}, { loading: true });
  api('teacher_api.php?a=coord').then(r => { CO = { ok: true, rows: r.rows || {}, at: Date.now() }; softRender(); }).catch(e => { CO = { err: e.message, rows: {}, at: Date.now() }; });
  return CO;
}
const coRows = (t) => Object.entries((CO && CO.rows) || {}).filter(([k]) => k.startsWith(t + '|')).map(([k, v]) => Object.assign({ _k: k }, v));
function coMe() { return ME && ME.role === 'teacher' ? (CO && CO.rows['c|' + me()]) || null : null; }
function coWeek() { const d = new Date(todayISO() + 'T00:00:00'), w = (d.getDay() + 6) % 7; return addDays(todayISO(), -w); }
function coStats(tid) {
  const ws = coWeek(), A = coRows('c').find(c => c.tid === tid) || { classes: [] };
  const cc = coRows('cc').filter(c => c.by === tid), ccW = cc.filter(c => c.date >= ws), vf = coRows('vf').filter(v => cc.some(c => c.id === v.cc));
  const dc = coRows('dc').filter(d => d.by === tid && d.date >= addDays(ws, -1)).length, sv = coRows('sv').filter(s => s.by === tid && s.date >= ws).length;
  const wsh = coRows('ws').filter(w => w.by === tid && w.date >= ws).length, rd = coRows('rd').filter(r => r.by === tid && r.date >= ws).length;
  const done = { cc: ccW.length, dc, sv, ws: wsh, rd }, pct = Math.round(100 * Object.keys(CO_TGT).reduce((a, k) => a + Math.min(1, done[k] / CO_TGT[k]), 0) / Object.keys(CO_TGT).length);
  const vfD = vf.filter(v => v.res), match = vfD.length ? Math.round(100 * vfD.reduce((a, v) => a + v.match, 0) / (6 * vfD.length)) : null;
  const disp = cc.filter(c => c.resp && c.resp.ans === 'no'), lost = disp.filter(c => c.resp.decision === 'teacher').length;
  return { A, done, pct, match, vfDone: vfD.length, vfAll: vf.length, disp: disp.length, lost, pending: disp.filter(c => !c.resp.decision) };
}
// rotation: teacher × subject pairs of her classes, least checked in the last 30 days first (no choosing whom to check)
function coPairs(A) {
  const from = addDays(todayISO(), -30), cc = coRows('cc'), seen = {};
  const L = (S.alloc || []).filter(a => a && (+a.n || 0) > 0 && A.classes.includes(a.c) && a.t && a.t !== me()).filter(a => { const k = a.c + '|' + a.s + '|' + a.t; if (seen[k]) return false; seen[k] = 1; return true; });
  return L.map(a => { const done = cc.filter(c => c.cls === a.c && c.subj === a.s && c.tid === a.t && c.date >= from); return { cls: a.c, subj: a.s, tid: a.t, n: done.length, last: done.map(c => c.date).sort().pop() || '' }; })
    .sort((x, y) => x.n - y.n || x.last.localeCompare(y.last) || x.cls.localeCompare(y.cls));
}
function coKids(cls) { const h = s => { let x = 0; for (const ch of s + todayISO()) x = (x * 31 + ch.charCodeAt(0)) >>> 0; return x; }; return studentsOf(cls).slice().sort((a, b) => h(a.id) - h(b.id)).slice(0, 5); }

// ---------------------------------------------------------------- Home / dashboard cards
function coCards() {
  const C = needCo(); if (!C.ok) return ''; let h = '';
  const sv = (C.rows['sv|' + todayISO()]); if (sv) h += `<div class="card co-sv"><small>💡 Aaj ka suvichar</small><b>${esc(sv.text)}</b>${sv.word ? `<span>Aaj ka shabd: ${esc(sv.word)}</span>` : ''}</div>`;
  if (ME.role === 'teacher') {
    coRows('vf').filter(v => v.to === me() && !v.res).slice(0, 1).forEach(v => { const s = studentById(v.sid); h += `<div class="card co-t" onclick="coVfOpen('${v.id}')"><b>🔍 Dobara jaanch (2 minute)</b><small>Class ${esc(v.cls)} · ${esc(v.subj)} · ${esc(s ? s.name : '')} ki copy dekh kar 6 baatein tick kijiye.</small></div>`; });
    coRows('cc').filter(c => c.tid === me() && !c.resp).slice(0, 2).forEach(c => { h += `<div class="card co-t" onclick="coRepOpen('${c.id}')"><b>👩‍💼 Copy check report · Class ${esc(c.cls)} ${esc(c.subj)}</b><small>${c.score}/6 · ${esc(T(c.by).name)} · Dekhiye aur jawab dijiye</small></div>`; });
    const myC = myClasses(); coRows('ws').filter(w => myC.includes(w.cls) && w.date >= addDays(todayISO(), -7) && w.by !== me()).slice(0, 1).forEach(w => { h += `<div class="card co-t" onclick="go('coord',{m:'ws'})"><b>📝 Nayi worksheet · Class ${esc(w.cls)} ${esc(w.subj)}</b><small>${esc(w.ch)} — print ya WhatsApp ke liye kholiye</small></div>`; });
    const A = coMe(); if (A) { const st = coStats(me()); h = `<div class="card co-home" onclick="go('coord')"><div class="cp-dh"><span>👩‍💼</span><div><small>Coordinator · ${esc(A.classes.join(', '))}</small><b>Is hafte ka kaam ${st.pct}%</b></div></div>
      <div class="co-bar"><i style="width:${st.pct}%"></i></div><div class="co-chips">${Object.keys(CO_TGT).map(k => `<span class="${st.done[k] >= CO_TGT[k] ? 'ok' : ''}">${({ cc: '📒 Copy', dc: '📔 Diary', sv: '💡 Suvichar', ws: '📝 Worksheet', rd: '📖 Reading' })[k]} ${st.done[k]}/${CO_TGT[k]}</span>`).join('')}</div>
      <button class="btn go full" onclick="event.stopPropagation();go('coord')">Aaj ka kaam kholo</button></div>` + h; }
  }
  if (isP() && pMode()) {
    const L = coRows('c'); h += `<div class="card co-p"><div class="cp-dh"><span>👩‍💼</span><div><small>Coordinator</small><b>${L.length ? L.map(c => esc(T(c.tid).name)).join(', ') : 'Abhi koi coordinator nahi'}</b></div></div>
      ${L.map(c => { const st = coStats(c.tid); return `<div class="co-pr"><b>${esc(T(c.tid).name)}</b><span>Kaam ${st.pct}%</span><span>Sahi jaanch ${st.match === null ? '—' : st.match + '%'}</span>${st.pending.length ? `<span class="bad">✋ ${st.pending.length} faisla baaki</span>` : ''}</div>`; }).join('')}
      <div class="btns"><button class="btn go" onclick="go('coord',{m:'rep'})">Report card</button><button class="btn l" onclick="coAssignOpen()">${L.length ? 'Badlo' : '+ Coordinator banao'}</button></div></div>`;
  }
  return h;
}

// ---------------------------------------------------------------- the Coordinator tab
function vCoord() {
  const C = needCo(); if (C.err) return `<div class="card">${esc(C.err)}</div>`; if (!C.ok) return '<div class="load"><i></i></div>';
  const A = coMe(), P0 = isP() && pMode();
  if (!A && !P0) return `<div class="card empty">Aap coordinator nahi hain. Principal app mein coordinator chunte hain.</div>`;
  const m = P.m || (P0 ? 'rep' : 'home');
  const tabs = P0 ? [['rep', 'Report'], ['cc', 'Copy check'], ['ws', 'Worksheet'], ['sv', 'Suvichar']] : [['home', 'Aaj'], ['cc', 'Copy'], ['dc', 'Diary'], ['rd', 'Reading'], ['ws', 'Worksheet'], ['sv', 'Suvichar'], ['rep', 'Report']];
  const body = { home: coHome, cc: coCC, dc: coDC, rd: coRD, ws: coWS, sv: coSV, rep: coRep }[m] || coHome;
  return `${seg(tabs, m, "(k=>go('coord',{m:k}))")}${body(A, P0)}`;
}
function coHome(A) {
  const st = coStats(me()), pr = coPairs(A)[0], t = todayISO();
  const tasks = [['cc', '📒', 'Copy check', pr ? `Class ${pr.cls} · ${pr.subj} · ${T(pr.tid).name} — 5 copy` : 'Koi class nahi', st.done.cc, CO_TGT.cc],
    ['dc', '📔', 'Kal ki diary jaanch', 'Har entry par ✓ / ✕ aur reminder (ghar se bhi)', st.done.dc, CO_TGT.dc],
    ['sv', '💡', 'Kal ka suvichar', CO.rows['sv|' + addDays(t, 1)] ? 'Kal ka ho gaya ✓' : 'Ek chuniye — 1 minute (ghar se bhi)', st.done.sv, CO_TGT.sv],
    ['rd', '📖', 'Reading test', 'Is hafte 3 class — har bachche ka level', st.done.rd, CO_TGT.rd],
    ['ws', '📝', 'Worksheet', 'Hafte mein 3 — taiyaar dhaancha (ghar se bhi)', st.done.ws, CO_TGT.ws]];
  return `<div class="card co-home"><div class="cp-dh"><span>👩‍💼</span><div><small>${esc(A.classes.join(', '))} · hafta ${esc(fmtDate(coWeek()))} se</small><b>Is hafte ka kaam ${st.pct}%</b></div></div><div class="co-bar"><i style="width:${st.pct}%"></i></div></div>
    ${tasks.map(([k, e, tl, x, d, g]) => `<div class="card co-task ${d >= g ? 'done' : ''}" onclick="go('coord',{m:'${k}'})"><span>${e}</span><div><b>${tl}</b><small>${esc(x)}</small></div><em>${d}/${g}</em></div>`).join('')}
    <button class="btn l full" onclick="coMitra('home')">🤖 Mitra: mera kaam kya hai?</button>`;
}

// ---- copy check
function coCC(A, P0) {
  if (P0) return coRows('cc').sort((a, b) => b.at - a.at).slice(0, 30).map(coCCRow).join('') || '<div class="card empty">Abhi koi copy check nahi hua.</div>';
  const pairs = coPairs(A); if (!pairs.length) return '<div class="card empty">In classes ka timetable khaali hai.</div>';
  if (!DRAFT.cc || DRAFT.cc.day !== todayISO()) DRAFT.cc = { day: todayISO(), skip: 0, kids: {}, good: '', remark: '' };
  const D = DRAFT.cc, pr = pairs[D.skip % pairs.length], kids = coKids(pr.cls);
  return `<div class="card"><h3>📒 Aaj ki copy check</h3><p class="mut" style="margin:0">App ne baari se chuna: <b>Class ${esc(pr.cls)} · ${esc(pr.subj)} · ${esc(T(pr.tid).name)}</b>${pr.last ? ` (pichhli baar ${esc(fmtDate(pr.last))})` : ' (pehli baar)'}</p>
      <button class="btn s l" style="margin-top:6px" onclick="DRAFT.cc.skip++;DRAFT.cc.kids={};render()">Yeh class abhi nahi mil rahi → agli</button>
      <button class="btn s l" style="margin-top:6px" onclick="coMitra('cc')">🤖 Kaise karein?</button></div>
    ${kids.map((s, i) => { const k = D.kids[s.id] || (D.kids[s.id] = { p: {} }); return `<div class="card co-kid"><div class="co-kh"><b>${i + 1}. ${esc(s.name)}</b><button class="btn s ${k.photo ? 'l' : 'w'}" onclick="coShot('${s.id}')">${k.photo ? '📷 Photo ✓' : '📷 Photo (zaroori)'}</button></div>
      ${k.photo ? `<img class="co-ph" src="${k.photo}" alt="">` : ''}<div class="co-pts">${CO_PTS.map(([pk, pl]) => `<button class="${k.p[pk] === 1 ? 'y' : k.p[pk] === 0 ? 'n' : ''}" onclick="coTap('${s.id}','${pk}')">${k.p[pk] === 1 ? '✓' : k.p[pk] === 0 ? '✕' : '○'} ${pl}</button>`).join('')}</div></div>`; }).join('')}
    <div class="card"><div class="fld">⭐ Kya achha tha (zaroori)<input id="ccG" class="inp" maxlength="200" value="${esc(D.good)}" oninput="DRAFT.cc.good=this.value" placeholder="jaise: likhai saaf hai, sab copy check hui hain"></div>
      <div class="fld">Sudhaar (ek line)<input id="ccR" class="inp" maxlength="300" value="${esc(D.remark)}" oninput="DRAFT.cc.remark=this.value" placeholder="jaise: galti ke neeche sahi uttar likhwayein"></div>
      <button class="btn go full" onclick="coCCSave('${pr.cls}',${jsq(pr.subj)},'${pr.tid}')">Report bhejo</button></div>
    <h3 class="co-h3">Pichhli jaanch</h3>${coRows('cc').filter(c => c.by === me()).sort((a, b) => b.at - a.at).slice(0, 8).map(coCCRow).join('')}`;
}
function coCCRow(c) { const r = c.resp; return `<div class="card co-row" onclick="coRepOpen('${c.id}')"><b>Class ${esc(c.cls)} · ${esc(c.subj)} · ${esc(T(c.tid).name)}</b><small>${esc(fmtDate(c.date))} · ${c.score}/6 · ${r ? (r.ans === 'ok' ? '👍 Teacher ne dekh liya' : '✋ Sahmat nahi' + (r.decision ? ' → ' + (r.decision === 'teacher' ? 'teacher sahi' : 'coordinator sahi') : ' — faisla baaki')) : 'Jawab baaki'}</small></div>`; }
function coTap(sid, k) { const p = DRAFT.cc.kids[sid].p; p[k] = p[k] === 1 ? 0 : 1; render(); }
let CO_SHOT = null;
function coShot(sid) { CO_SHOT = sid; let i = $('coIn'); if (!i) { i = document.createElement('input'); i.type = 'file'; i.accept = 'image/*'; i.capture = 'environment'; i.id = 'coIn'; i.hidden = true; i.onchange = () => coShotGot(i); document.body.appendChild(i); } i.value = ''; i.click(); }
function coShrink(f, m, lim) { return new Promise((ok, bad) => { const img = new Image(), u = URL.createObjectURL(f); img.onload = () => { const r = Math.min(1, m / Math.max(img.width, img.height)), cv = document.createElement('canvas'); cv.width = Math.round(img.width * r); cv.height = Math.round(img.height * r); cv.getContext('2d').drawImage(img, 0, 0, cv.width, cv.height); URL.revokeObjectURL(u); let q = .75, o = cv.toDataURL('image/jpeg', q); while (o.length > lim && q > .3) { q -= .1; o = cv.toDataURL('image/jpeg', q); } ok(o); }; img.onerror = () => bad(new Error('Photo nahi khuli.')); img.src = u; }); }
async function coShotGot(i) { const f = i.files && i.files[0]; if (!f || !CO_SHOT) return; try { DRAFT.cc.kids[CO_SHOT].photo = await coShrink(f, 900, 360000); render(); } catch (e) { toast(e.message); } }
async function coCCSave(cls, subj, tid) {
  const D = DRAFT.cc, kids = coKids(cls).map(s => ({ sid: s.id, k: D.kids[s.id] || { p: {} } }));
  const miss = kids.find(x => CO_PTS.some(([k]) => x.k.p[k] === undefined)); if (miss) return toast('Har copy ki 6 baaton par ✓ ya ✕ lagaiye.');
  const noPh = kids.find(x => !x.k.photo); if (noPh) return toast('Har copy ki photo zaroori hai — saboot ke liye.');
  if (!String(D.good).trim()) return toast('“Kya achha tha” likhiye.');
  try { const r = await api('teacher_api.php?a=coord', { op: 'cc', cls, subj, tid, kids: kids.map(x => ({ sid: x.sid, p: x.k.p })), good: D.good, remark: D.remark });
    toast('Photo bhej rahe hain…'); for (let i = 0; i < kids.length; i++) await api('teacher_api.php?a=coord', { op: 'ccphoto', id: r.cc.id, i, data: kids[i].k.photo });
    DRAFT.cc = null; toast('Report teacher ko chali gayi ✓'); needCo(true); render(); } catch (e) { toast(e.message); }
}
async function coRepOpen(id) {
  const c = CO.rows['cc|' + id]; if (!c) return; const mine = c.tid === me(), r = c.resp;
  SHEET = () => `<h2>📒 Copy check · Class ${esc(c.cls)} ${esc(c.subj)}</h2><p class="mut" style="margin:0">${esc(fmtLong(c.date))} · jaancha: ${esc(T(c.by).name)} · teacher: ${esc(T(c.tid).name)} · <b>${c.score}/6</b></p>
    <p class="co-good">⭐ ${esc(c.good)}</p>${c.remark ? `<p class="co-fix">🔧 ${esc(c.remark)}</p>` : ''}
    ${c.kids.map((k, i) => { const s = studentById(k.sid); return `<div class="co-kr"><b>${esc(s ? s.name : '')}</b><span>${CO_PTS.map(([pk, pl]) => `<i class="${k.p[pk] ? 'y' : 'n'}" title="${esc(pl)}">${k.p[pk] ? '✓' : '✕'}</i>`).join('')}</span>${c.photos & (1 << i) ? `<img data-cp="${c.id}|${i}" alt="">` : ''}</div>`; }).join('')}
    <p class="mut" style="font-size:11px">Kram: ${CO_PTS.map(p => p[1]).join(' · ')}</p>
    ${r ? `<p class="co-fix">${r.ans === 'ok' ? '👍 Teacher ne dekh liya' : '✋ Teacher sahmat nahi: ' + esc(r.note || '')}${r.decision ? ' → Faisla: ' + (r.decision === 'teacher' ? 'teacher sahi' : 'coordinator sahi') : ''}</p>` : ''}
    ${mine && !r ? `<div class="fld">Aapki baat (agar sahmat nahi)<input id="rpN" class="inp" maxlength="300"></div><div class="btns"><button class="btn go" onclick="coResp('${id}','ok')">👍 Dekh liya, sudhaar karungi</button><button class="btn w" onclick="coResp('${id}','no')">✋ Sahmat nahi</button></div>` : ''}
    ${isP() && r && r.ans === 'no' && !r.decision ? `<div class="btns"><button class="btn l" onclick="coDecide('${id}','teacher')">Teacher sahi</button><button class="btn l" onclick="coDecide('${id}','coord')">Coordinator sahi</button></div>` : ''}`;
  render(); document.querySelectorAll('img[data-cp]').forEach(async im => { try { const d = (await api('teacher_api.php?a=coord&cp=' + encodeURIComponent(im.dataset.cp))).data; if (d) im.src = d; } catch (e) {} });
}
async function coResp(id, ans) { const note = ($('rpN') || {}).value || ''; if (ans === 'no' && !note.trim()) return toast('Kya galat hai, ek line likhiye.'); try { await api('teacher_api.php?a=coord', { op: 'resp', id, ans, note }); SHEET = null; toast('Jawab chala gaya ✓'); needCo(true); render(); } catch (e) { toast(e.message); } }
async function coDecide(id, who) { try { await api('teacher_api.php?a=coord', { op: 'decide', id, who }); SHEET = null; toast('Faisla save ✓'); needCo(true); render(); } catch (e) { toast(e.message); } }
function coVfOpen(id) {
  const v = CO.rows['vf|' + id]; if (!v) return; const s = studentById(v.sid); DRAFT.vf = DRAFT.vf && DRAFT.vf.id === id ? DRAFT.vf : { id, p: {} };
  SHEET = () => `<h2>🔍 Dobara jaanch</h2><p class="mut" style="margin:0">Class ${esc(v.cls)} · ${esc(v.subj)} · <b>${esc(s ? s.name : '')}</b> ki copy lijiye aur 6 baatein dekhiye. Kisi aur ke number aapko nahi dikhenge.</p>
    <div class="co-pts">${CO_PTS.map(([pk, pl]) => `<button class="${DRAFT.vf.p[pk] === 1 ? 'y' : DRAFT.vf.p[pk] === 0 ? 'n' : ''}" onclick="DRAFT.vf.p['${pk}']=DRAFT.vf.p['${pk}']===1?0:1;coVfOpen('${id}')">${DRAFT.vf.p[pk] === 1 ? '✓' : DRAFT.vf.p[pk] === 0 ? '✕' : '○'} ${pl}</button>`).join('')}</div>
    <button class="btn go full" onclick="coVfSave('${id}')">Save</button>`;
  render();
}
async function coVfSave(id) { const p = DRAFT.vf.p; if (CO_PTS.some(([k]) => p[k] === undefined)) return toast('Sab 6 par ✓ ya ✕.'); try { await api('teacher_api.php?a=coord', { op: 'vf', id, p }); DRAFT.vf = null; SHEET = null; toast('Dhanyavaad ✓'); needCo(true); render(); } catch (e) { toast(e.message); } }

// ---- diary check (last school day)
function coLastDay() { let d = addDays(todayISO(), -1); for (let i = 0; i < 7; i++) { if (new Date(d + 'T00:00:00').getDay() !== 0 && Object.keys(S.diary[d] || {}).length) return d; d = addDays(d, -1); } return addDays(todayISO(), -1); }
function coDC(A) {
  const d = P.date || coLastDay(), byT = S.diary[d] || {}, rows = [];
  Object.entries(byT).forEach(([t, bells]) => Object.entries(bells || {}).forEach(([b, e]) => { if (e && A.classes.includes(e.cls)) rows.push({ t, b, e }); }));
  rows.sort((x, y) => x.e.cls.localeCompare(y.e.cls) || +x.b - +y.b);
  const saved = (CO.rows['dc|' + d + '|' + me()] || {}).marks || {}; if (!DRAFT.dc || DRAFT.dc.d !== d) DRAFT.dc = { d, m: Object.assign({}, saved) };
  const teach = [...new Set((S.alloc || []).filter(a => a && (+a.n || 0) > 0 && A.classes.includes(a.c) && a.t).map(a => a.t))], none = teach.filter(t => !rows.some(r => r.t === t));
  return `<div class="card"><h3>📔 Diary jaanch · ${esc(fmtLong(d))}</h3><p class="mut" style="margin:0">Har entry padhiye: topic saaf hai, class work / homework likha hai, syllabus se milta hai → ✓, warna ✕.</p><button class="btn s l" style="margin-top:6px" onclick="coMitra('dc')">🤖 Kaise karein?</button></div>
    ${rows.map(r => { const k = r.t + '|' + r.b, v = DRAFT.dc.m[k]; return `<div class="card co-dr"><div><b>Class ${esc(r.e.cls)} · ${esc(r.e.subj || '')}</b><small>${esc(T(r.t).name)}</small><p>${esc(r.e.topic || '— topic nahi —')}${r.e.classwork ? '<br>CW: ' + esc(r.e.classwork) : ''}${r.e.hw ? '<br>HW: ' + esc(r.e.hw) : ''}</p></div>
      <div class="co-yn"><button class="${v === 1 ? 'y' : ''}" onclick="DRAFT.dc.m['${k}']=1;render()">✓</button><button class="${v === 0 ? 'n' : ''}" onclick="DRAFT.dc.m['${k}']=0;render()">✕</button></div></div>`; }).join('') || '<div class="card empty">Us din in classes ki diary nahi likhi gayi.</div>'}
    ${none.length ? `<div class="card"><b>Diary nahi likhi:</b> ${none.map(t => `<button class="chip" onclick="coRemind('${t}','${d}')">🔔 ${esc(T(t).name)}</button>`).join('')}</div>` : ''}
    ${rows.length ? `<button class="btn go full" onclick="coDCSave()">Save (${Object.keys(DRAFT.dc.m).length}/${rows.length})</button>` : ''}`;
}
async function coDCSave() { try { await api('teacher_api.php?a=coord', { op: 'dc', date: DRAFT.dc.d, marks: DRAFT.dc.m }); toast('Diary jaanch save ✓'); needCo(true); render(); } catch (e) { toast(e.message); } }
async function coRemind(t, d) { try { await api('teacher_api.php?a=staffchat', { room: scDm(me(), t), text: `Namaste ${T(t).name}, ${fmtDate(d)} ki diary app mein nahi likhi gayi. Kripya aaj likh dein. — ${myName()} (Coordinator)` }); toast('Reminder chala gaya ✓'); } catch (e) { toast(e.message); } }

// ---- reading levels
function coRD(A) {
  const m = todayISO().slice(0, 7), pm = (() => { const d = new Date(m + '-01T00:00:00'); d.setMonth(d.getMonth() - 1); return d.getFullYear() + '-' + pad(d.getMonth() + 1); })();
  const order = A.classes.slice().sort((a, b) => (CO.rows['rd|' + m + '|' + a] ? 1 : 0) - (CO.rows['rd|' + m + '|' + b] ? 1 : 0)), c = order.includes(P.c) ? P.c : order[0];
  const cur = (CO.rows['rd|' + m + '|' + c] || {}).levels || {}, prev = (CO.rows['rd|' + pm + '|' + c] || {}).levels || {};
  if (!DRAFT.rd || DRAFT.rd.c !== c) DRAFT.rd = { c, lv: Object.assign({}, cur) };
  return `<div class="card"><h3>📖 Reading test · Class ${esc(c)}</h3><p class="mut" style="margin:0">Har bachche se 1 minute class ki kitaab padhwaiye aur level tap kijiye. App ne pehle woh class rakhi jiska is mahine test nahi hua.</p>
      <div class="chips">${order.map(x => `<button class="chip ${x === c ? 'on' : ''}" onclick="go('coord',{m:'rd',c:${jsq(x)}})">${esc(x)}${CO.rows['rd|' + m + '|' + x] ? ' ✓' : ''}</button>`).join('')}</div><button class="btn s l" onclick="coMitra('rd')">🤖 Kaise karein?</button></div>
    ${studentsOf(c).map(s => { const v = DRAFT.rd.lv[s.id], p0 = prev[s.id]; return `<div class="card co-rd"><b>${esc(s.name)}${p0 ? `<small> · pichhla: ${esc(CO_LV[p0 - 1][1])}${v ? (v > p0 ? ' ⬆️' : v < p0 ? ' ⬇️' : ' ➡️') : ''}</small>` : ''}</b><div class="co-lv">${CO_LV.map(([n, l]) => `<button class="${v === n ? 'on' : ''}" onclick="DRAFT.rd.lv['${s.id}']=${n};render()">${l}</button>`).join('')}</div></div>`; }).join('')}
    <button class="btn go full" onclick="coRDSave()">Save (${Object.keys(DRAFT.rd.lv).length}/${studentsOf(c).length})</button>`;
}
async function coRDSave() { try { await api('teacher_api.php?a=coord', { op: 'rd', cls: DRAFT.rd.c, levels: DRAFT.rd.lv }); toast('Reading level save ✓'); DRAFT.rd = null; needCo(true); render(); } catch (e) { toast(e.message); } }

// ---- worksheets
function coWS(A, P0) {
  const cls = A ? A.classes : className(); if (!DRAFT.ws) DRAFT.ws = { cls: cls[0], subj: '', ch: '', qs: ['', '', '', '', ''] };
  const D = DRAFT.ws, subs = [...new Set((S.alloc || []).filter(a => a && a.c === D.cls).map(a => a.s))];
  const tip = ['Khaali jagah bharo: ____', 'Sahi (✓) ya galat (✕):', 'Ek shabd mein uttar do:', 'Chitra banao / rang bharo:', 'Sawaal (2–3 line):'];
  const L = coRows('ws').filter(w => P0 || !A || w.by === me() || myClasses().includes(w.cls)).sort((a, b) => b.at - a.at).slice(0, 12);
  return `${A || P0 ? `<div class="card"><h3>📝 Nayi worksheet</h3><div class="chips">${cls.map(c => `<button class="chip ${c === D.cls ? 'on' : ''}" onclick="DRAFT.ws.cls=${jsq(c)};DRAFT.ws.subj='';render()">${esc(c)}</button>`).join('')}</div>
      <div class="chips">${subs.map(x => `<button class="chip ${x === D.subj ? 'on' : ''}" onclick="DRAFT.ws.subj=${jsq(x)};DRAFT.ws.ch=(actTopic(DRAFT.ws.cls,${jsq(x)})||'').replace(/^[^:]*:\\s*/,'');render()">${esc(x)}</button>`).join('')}</div>
      <div class="fld">Chapter / topic<input class="inp" maxlength="80" value="${esc(D.ch)}" oninput="DRAFT.ws.ch=this.value" placeholder="Subject chunte hi diary ka chapter aa jayega"></div>
      ${D.qs.map((q, i) => `<div class="fld">${i + 1}. ${tip[i] || 'Sawaal'}<input class="inp" maxlength="300" value="${esc(q)}" oninput="DRAFT.ws.qs[${i}]=this.value"></div>`).join('')}
      <div class="btns"><button class="btn go" onclick="coWSSave()">Save</button><button class="btn l" onclick="coMitra('ws')">🤖 Kaise karein?</button></div></div>` : ''}
    ${L.map(w => `<div class="card co-row"><b>Class ${esc(w.cls)} · ${esc(w.subj)} · ${esc(w.ch)}</b><small>${esc(fmtDate(w.date))} · ${esc(T(w.by).name)}</small><div class="btns"><button class="btn s l" onclick="coWSCopy('${w.id}')">Copy (WhatsApp)</button><button class="btn s l" onclick="coWSPrint('${w.id}')">${ic('print')} Print</button></div></div>`).join('')}`;
}
async function coWSSave() { const D = DRAFT.ws; if (!D.subj) return toast('Subject chuniye.'); try { await api('teacher_api.php?a=coord', { op: 'ws', cls: D.cls, subj: D.subj, ch: D.ch, qs: D.qs }); DRAFT.ws = null; toast('Worksheet save ✓ — class ke teachers ko dikhegi'); needCo(true); render(); } catch (e) { toast(e.message); } }
function coWSText(w) { return `📝 *Worksheet — Class ${w.cls} · ${w.subj}*\n${w.ch}\nNaam: __________  Roll: ____\n\n${w.qs.map((q, i) => `${i + 1}. ${q}`).join('\n\n')}\n\n— ${S.settings.school || ''}`; }
function coWSCopy(id) { const w = CO.rows['ws|' + id]; if (!w) return; window.__CT = [coWSText(w)]; certCopy(0); }
function coWSPrint(id) { const w = CO.rows['ws|' + id]; if (!w) return; let p = document.querySelector('.prt'); if (!p) { p = document.createElement('div'); p.className = 'prt'; document.body.appendChild(p); }
  p.innerHTML = `<h1>${esc(S.settings.school || '')}</h1><h2>Worksheet — Class ${esc(w.cls)} · ${esc(w.subj)} · ${esc(w.ch)}</h2><p>Naam: ____________________ &nbsp; Roll: ______ &nbsp; Taarikh: __________</p>${w.qs.map((q, i) => `<p style="margin:18px 0 40px"><b>${i + 1}.</b> ${esc(q)}</p>`).join('')}`;
  if (window.SNMSISFiles) return toast('App mein print nahi hota — Copy karke bhejiye, ya computer ke Chrome se print kijiye.'); setTimeout(() => window.print(), 200); }

// ---- thought of the day
function coSV() {
  const t = todayISO(), tm = addDays(t, 1), d = P.d === t ? t : tm, cur = CO.rows['sv|' + d];
  return `<div class="card"><h3>💡 Suvichar aur aaj ka shabd</h3><p class="mut" style="margin:0">Jo chunenge woh sabke app ke Home par us din dikhega — assembly mein bhi bol sakte hain.</p>
      <div class="chips"><button class="chip ${d === t ? 'on' : ''}" onclick="go('coord',{m:'sv',d:'${t}'})">Aaj${CO.rows['sv|' + t] ? ' ✓' : ''}</button><button class="chip ${d === tm ? 'on' : ''}" onclick="go('coord',{m:'sv',d:'${tm}'})">Kal${CO.rows['sv|' + tm] ? ' ✓' : ''}</button></div>
      ${cur ? `<p class="co-good">✓ ${esc(cur.text)}${cur.word ? ' · ' + esc(cur.word) : ''}</p>` : ''}</div>
    ${CO_SV.map(([x, w], i) => `<div class="card co-svp" onclick="coSVSave('${d}',${i})"><b>${esc(x)}</b><small>Shabd: ${esc(w)}</small></div>`).join('')}`;
}
async function coSVSave(d, i) { const [text, word] = CO_SV[i]; try { await api('teacher_api.php?a=coord', { op: 'sv', date: d, text, word }); toast('Suvichar save ✓'); needCo(true); render(); } catch (e) { toast(e.message); } }

// ---- weekly report card
function coRep(A, P0) {
  const L = P0 ? coRows('c') : [coMe()].filter(Boolean);
  if (!L.length) return `<div class="card empty">Abhi koi coordinator nahi. <button class="btn s go" onclick="coAssignOpen()">+ Coordinator banao</button></div>`;
  return L.map(c => { const st = coStats(c.tid); return `<div class="card"><h3>📊 ${esc(T(c.tid).name)} · hafta ${esc(fmtDate(coWeek()))} se</h3><small class="mut">${esc(c.classes.join(', '))}</small>
    <table class="co-tb"><tr><th>Kaam</th><th>Target</th><th>Hua</th></tr>${Object.keys(CO_TGT).map(k => `<tr><td>${({ cc: '📒 Copy check', dc: '📔 Diary jaanch (din)', sv: '💡 Suvichar', ws: '📝 Worksheet', rd: '📖 Reading (class)' })[k]}</td><td>${CO_TGT[k]}</td><td class="${st.done[k] >= CO_TGT[k] ? 'ok' : 'bad'}">${st.done[k]}</td></tr>`).join('')}</table>
    <div class="co-kpi"><div><b>${st.pct}%</b><small>kaam pura</small></div><div><b>${st.match === null ? '—' : st.match + '%'}</b><small>sahi jaanch (${st.vfDone}/${st.vfAll} dobara jaanch)</small></div><div><b>${st.disp}</b><small>teacher sahmat nahi${st.lost ? ' · ' + st.lost + ' mein teacher sahi' : ''}</small></div></div>
    ${st.pending.map(x => `<div class="co-row" onclick="coRepOpen('${x.id}')">✋ Faisla baaki: Class ${esc(x.cls)} ${esc(x.subj)} · ${esc(T(x.tid).name)}</div>`).join('')}
    ${P0 ? `<button class="btn s l" onclick="coAssignOpen('${c.tid}')">Badlo / hatao</button>` : ''}</div>`; }).join('') + (P0 ? `<button class="btn l full" onclick="coAssignOpen()">+ Aur coordinator</button>` : '');
}
function coAssignOpen(t0) {
  const T0 = t0 || '', cur = T0 ? (CO.rows['c|' + T0] || {}).classes || [] : []; DRAFT.ca = { t: T0, cls: cur.slice() };
  SHEET = () => `<h2>👩‍💼 Coordinator</h2><div class="fld">Teacher<select id="caT" class="inp" onchange="DRAFT.ca.t=this.value"><option value="">— chuniye —</option>${Object.keys(S.teachers).filter(t => t !== 'P').map(t => `<option value="${esc(t)}" ${t === DRAFT.ca.t ? 'selected' : ''}>${esc(T(t).name)}</option>`).join('')}</select></div>
    <p class="mut" style="margin:6px 0">Classes:</p><div class="chips">${className().map(c => `<button class="chip ${DRAFT.ca.cls.includes(c) ? 'on' : ''}" onclick="const a=DRAFT.ca.cls,i=a.indexOf(${jsq(c)});i<0?a.push(${jsq(c)}):a.splice(i,1);this.classList.toggle('on')">${esc(c)}</button>`).join('')}</div>
    <div class="btns"><button class="btn go" onclick="coAssignSave()">Save</button>${T0 ? `<button class="btn w" onclick="DRAFT.ca.cls=[];coAssignSave()">Coordinator se hatao</button>` : ''}</div>`;
  render();
}
async function coAssignSave() { const D = DRAFT.ca; if (!D.t) return toast('Teacher chuniye.'); try { await api('teacher_api.php?a=coord', { op: 'assign', tid: D.t, classes: D.cls }); SHEET = null; toast(D.cls.length ? 'Coordinator ban gayi ✓' : 'Hata diya'); needCo(true); render(); } catch (e) { toast(e.message); } }

// ---- Mitra for every task
const CO_HELP = {
  home: ['Mera kaam kya hai?', [['📋', 'App kaam deta hai', 'Har din yahan list dikhegi. Kis teacher ki copy, kaun si class — app baari se tay karta hai.'], ['🏫', 'School mein', 'Copy check aur reading test school mein.'], ['🏠', 'Ghar se', 'Diary jaanch, suvichar aur worksheet ghar se phone par.'], ['📊', 'Hisaab', 'Har hafte ka target hai. Kitna hua, Principal ko report card mein dikhta hai.'], ['🔍', 'Dobara jaanch', 'Aapki jaanchi copy kabhi-kabhi kisi aur teacher se dobara jaanchi jaati hai — isliye har copy dhyan se dekhiye.']]],
  cc: ['Copy check kaise karein', [['🏫', 'Class mein jaiye', 'Upar likhi class aur subject — app ne baari se chuna. Class na mile to “agli” dabaiye.'], ['🧒', '5 copy lijiye', 'Sirf in 5 bachchon ki copy — app ne chuni hain.'], ['📷', 'Photo', 'Har copy ka aaj ka class work wala page — photo zaroori hai.'], ['✓', '6 baatein', 'Har baat par ek baar tap = ✓, do baar = ✕. Sab 6 bharni hain.'], ['⭐', 'Achhi baat', 'Ek achhi baat zaroor likhiye, phir ek line sudhaar.'], ['📤', 'Report bhejo', 'Report teacher aur Principal tak jaati hai. Teacher jawab de sakti hai.']]],
  dc: ['Diary jaanch kaise karein', [['📔', 'Har entry padhiye', 'Topic saaf hai? Class work / homework likha hai? Syllabus se milta hai?'], ['✓', 'Tick', 'Sab theek → ✓, kuch kami → ✕.'], ['🔔', 'Reminder', 'Jinhone diary nahi likhi unke naam par tap — unhe message chala jayega.'], ['💾', 'Save', 'Neeche Save dabaiye. Yeh ghar se bhi ho jaata hai.']]],
  rd: ['Reading test kaise karein', [['📖', 'Kitaab', 'Usi class ki Hindi / English kitaab ka koi page.'], ['⏱️', '1 minute', 'Har bachcha 1 minute padhe.'], ['🎚️', 'Level', 'Sirf akshar pehchane = Akshar; shabd padhe = Shabd; vaakya = Vaakya; kahani samajh kar = Kahani; bina ruke = Dhaarapravaah.'], ['📈', 'Pragati', 'Pichhle mahine ka level saath mein dikhta hai — ⬆️ matlab sudhaar.']]],
  ws: ['Worksheet kaise banayein', [['🏷️', 'Class aur subject', 'Chuniye — diary se chapter apne aap aa jayega.'], ['✍️', '5 sawaal', 'Har line par udaharan likha hai: khaali jagah, sahi-galat, ek shabd, chitra, chhota sawaal.'], ['💾', 'Save', 'Worksheet us class ke teachers ko dikhegi — woh print ya WhatsApp kar sakte hain.']]]
};
function coMitra(k) {
  const [title, steps] = CO_HELP[k] || CO_HELP.home; let o = $('nwBox'); if (o) o.remove(); o = document.createElement('div'); o.id = 'nwBox'; o.className = 'nw';
  o.innerHTML = `<div class="nw-in"><div class="nw-bot"><span class="nw-face">🤖</span><div class="nw-bub"><b>${esc(title)}</b><br>Bas yeh steps — kuch likhna kam, tap zyada.</div></div>
    <ol class="nw-l">${steps.map(([e, t, d]) => `<li><span>${e}</span><div><b>${esc(t)}</b><small>${esc(d)}</small></div></li>`).join('')}</ol><button class="btn go full" onclick="this.closest('.nw').remove()">Samajh gaya 👍</button></div>`;
  document.body.appendChild(o);
}
(function () {
  const _vHome = vHome; vHome = function () { return coCards() + _vHome(); };
  const _vPdash = vPdash; vPdash = function () { return coCards() + _vPdash(); };
  if (typeof MENU !== 'undefined') MENU.forEach(g => { if (g[0] === 'Principal') g[1].splice(2, 0, ['coord', {}, 'check', 'Coordinator', 'Copy check, report card, kaam']); });
})();
