'use strict';
// Teacher App — 🌱 Learning Support for weak children (owner, 24 Nov 2026: "jo bachche weak hain unke field me record kaise
// track ho aur uspe work ho — book reading, learning, maths, English grammar"). Data: support_api.php. TAB 'support' (P.m):
//  home  — weak children of my classes (any ladder at level 1–2), my groups, buttons
//  levels — class + skill → every child 0–5 in one screen, one Save (term check, 3 times a year)
//  kid   — one child: 4 ladders (tap to change), history, pahade 2–20, reading log, parent message
//  group — one support group's day: what was done + ✓ / ✗ per child, Save; earlier days with % got
//  gedit — make / change a group (name, skill, time, children from any class; weak children of that skill suggested)
const SUP_SK = { readH: ['Hindi reading', ['Not checked', 'Akshar', 'Matra / shabd', 'Vakya', 'Paragraph', 'Kahani (fluent)']],
  readE: ['English reading', ['Not checked', 'Letters', 'Words', 'Sentences', 'Paragraph', 'Story (fluent)']],
  maths: ['Maths', ['Not checked', 'Ginti (numbers)', 'Jod (+)', 'Ghatav (−)', 'Pahade / guna (×)', 'Bhag (÷)']],
  gram: ['English grammar', ['Not checked', 'Noun / pronoun', 'Verb', 'Tense', 'Sentence making', 'Paragraph writing']] };
let SUP = null, SUPB = false;
async function supLoad(force) { if ((SUP && !force) || SUPB) return; SUPB = true; try { SUP = await api('support_api.php?a=state'); } catch (e) { SUP = { err: e.message }; } SUPB = false; render(); }
function supRec(sid) { const r = ((SUP && SUP.skills) || {})[sid] || {}; return { lv: r.lv || {}, hist: r.hist || [], tables: r.tables || [], books: r.books || [] }; }
function supClasses() { return isP() ? className() : myClasses(); }
function supWeak(cls) { return studentsOf(cls).map(s => ({ s, r: supRec(s.id) })).map(x => ({ ...x, low: Object.keys(SUP_SK).filter(k => (x.r.lv[k] || 0) > 0 && x.r.lv[k] <= 2) })).filter(x => x.low.length); }
function supStu(sid) { return S.students.find(s => s && s.id === sid) || { id: sid, name: sid, cls: '' }; }
function supBar(v) { return `<span class="supbar">${[1, 2, 3, 4, 5].map(i => `<i class="${i <= v ? (v <= 2 ? 'r' : v <= 3 ? 'y' : 'g') : ''}"></i>`).join('')}</span>`; }
function vSupport() {
  if (!SUP) { supLoad(); return '<div class="card">Opening Learning Support…</div>'; }
  if (SUP.err) return `<div class="card">${esc(SUP.err)} <button class="btn s l" onclick="SUP=null;render()">Try again</button></div>`;
  const m = P.m || 'home', nav = m === 'home' ? '' : `<div class="hubnav"><button class="btn l s" onclick="go('support',{c:${jsq(P.c || '')}})">← Learning Support</button></div>`;
  return nav + ({ home: supHome, levels: supLevels, kid: supKid, group: supGroup, gedit: supGEdit }[m] || supHome)();
}
function supHome() {
  const C = supClasses(), c = C.includes(P.c) ? P.c : C[0]; if (!c) return '<div class="card empty">No class is given to you yet.</div>';
  const W = supWeak(c), kids = studentsOf(c), checked = kids.filter(s => Object.values(supRec(s.id).lv).some(v => v > 0)).length;
  const G = Object.entries(SUP.groups || {}).filter(([, g]) => isP() || g.owner === me()), t = todayISO();
  const cnt = k => kids.filter(s => { const v = supRec(s.id).lv[k] || 0; return v > 0 && v <= 2; }).length;
  return `<div class="tgban" style="background:linear-gradient(135deg,#0F766E,#15803D)">🌱 Learning Support<small>Find the children who need help, work with them in small groups, and see them improve.</small></div>
    <div class="fld">Class${sel('supC', C.map(x => [x, 'Class ' + x]), c, "go('support',{c:this.value})")}</div>
    <div class="kp">${Object.entries(SUP_SK).map(([k, [l]]) => `<div class="k ${cnt(k) ? 'bad' : ''}"><span>${esc(l)}</span><b>${cnt(k)}</b><small>at level 1–2</small></div>`).join('')}</div>
    <div class="btns" style="margin:8px 0"><button class="btn go" onclick="go('support',{m:'levels',c:${jsq(c)}})">📏 Level check — Class ${esc(c)}</button><button class="btn l" onclick="go('support',{m:'gedit',c:${jsq(c)}})">+ New group</button></div>
    <p class="mut hint">${checked}/${kids.length} children of Class ${esc(c)} have a level. Do the level check 3 times a year (start, after Half-Yearly, before Annual).</p>
    <div class="card"><h3>Needs help <small>Class ${esc(c)} · ${W.length}</small></h3>${W.length ? W.map(x => `<button type="button" class="sylrow" onclick="go('support',{m:'kid',sid:${jsq(x.s.id)},c:${jsq(c)}})"><span>${esc(x.s.name)}</span><small>${x.low.map(k => esc(SUP_SK[k][0]) + ' ' + x.r.lv[k]).join(' · ')}</small></button>`).join('') : '<div class="empty">Nobody at level 1–2 yet (or levels not checked).</div>'}</div>
    <div class="card"><h3>${isP() ? 'All groups' : 'My groups'} <small>${G.length}</small></h3>${G.length ? G.map(([id, g]) => { const L = (SUP.logs || []).filter(l => l.gid === id), today = L.find(l => l.date === t);
      return `<div class="hrow"><div><b>${esc(g.name)}</b><small>${esc((SUP_SK[g.skill] || ['Other'])[0])} · ${g.kids.length} children${g.slot ? ' · ' + esc(g.slot) : ''}${isP() ? ' · ' + esc((S.teachers[g.owner] || {}).name || g.owner || '') : ''} · ${L.length} day(s) worked${today ? ' · ✓ today' : ''}</small></div><button class="btn s ${today ? 'l' : 'go'}" onclick="go('support',{m:'group',gid:${jsq(id)},c:${jsq(c)}})">${today ? 'Open' : 'Today'}</button></div>`; }).join('') : '<div class="empty">No group yet. Make one: e.g. "Pahada group", "Hindi reading group" — 15–20 minutes a day.</div>'}</div>
    <div class="card"><h3>All children of Class ${esc(c)}</h3>${kids.map(s => { const r = supRec(s.id); return `<button type="button" class="sylrow" onclick="go('support',{m:'kid',sid:${jsq(s.id)},c:${jsq(c)}})"><span>${esc(s.name)}</span><small>${Object.keys(SUP_SK).map(k => supBar(r.lv[k] || 0)).join(' ')}</small></button>`; }).join('')}<p class="mut hint">Bars: Hindi reading · English reading · Maths · Grammar.</p></div>`;
}
function supLevels() {
  const C = supClasses(), c = C.includes(P.c) ? P.c : C[0], k = SUP_SK[P.k] ? P.k : 'readH', kids = studentsOf(c);
  if (!DRAFT.supL || DRAFT.supL.c !== c || DRAFT.supL.k !== k) DRAFT.supL = { c, k, v: Object.fromEntries(kids.map(s => [s.id, supRec(s.id).lv[k] || 0])), n: 0 };
  const L = DRAFT.supL, labels = SUP_SK[k][1];
  return `<div class="fld">Class${sel('supLC', C.map(x => [x, 'Class ' + x]), c, "DRAFT.supL=null;go('support',{m:'levels',c:this.value,k:" + jsq(k) + "})")}</div>
    <div class="seg hubseg">${Object.entries(SUP_SK).map(([kk, [l]]) => `<button class="${kk === k ? 'on' : ''}" onclick="DRAFT.supL=null;go('support',{m:'levels',c:${jsq(c)},k:'${kk}'})">${esc(l)}</button>`).join('')}</div>
    <div class="card"><h3>${esc(SUP_SK[k][0])} <small>tap the level each child can do</small></h3>
      <p class="mut hint">${labels.slice(1).map((l, i) => `<b>${i + 1}</b> ${esc(l)}`).join(' · ')}</p>
      ${kids.map(s => `<div class="suplv"><b>${esc(s.name)}</b><div class="supbtn">${[1, 2, 3, 4, 5].map(i => `<button type="button" class="${L.v[s.id] === i ? 'on l' + i : ''}" onclick="DRAFT.supL.v[${jsq(s.id)}]=${i};DRAFT.supL.n++;render()" title="${esc(labels[i])}">${i}</button>`).join('')}</div></div>`).join('')}
      <button class="btn go full" style="margin-top:10px" ${L.n ? '' : 'disabled'} onclick="supLevelsSave()">${L.n ? 'Save levels' : 'Tap levels first'}</button></div>`;
}
async function supLevelsSave() {
  const L = DRAFT.supL; try { const r = await api('support_api.php?a=levels', { cls: L.c, skill: L.k, vals: L.v }); Object.assign(SUP.skills, r.recs || {}); DRAFT.supL = null; toast(r.saved + ' levels saved ✓'); render(); } catch (e) { toast(e.message); }
}
function supKid() {
  const s = supStu(P.sid), r = supRec(s.id), t = todayISO();
  const histOf = k => r.hist.filter(h => h.k === k), first = k => { const h = histOf(k); return h.length ? h[0].v : 0; };
  const parentMsg = `Namaste,\n${s.name} (Class ${s.cls}) — school mein ${Object.entries(SUP_SK).filter(([k]) => r.lv[k]).map(([k, [l, L]]) => `${l}: ${L[r.lv[k]]}`).join(', ') || 'learning check'}.\nGhar par roz 15 minute: ${(r.lv.readH || 9) <= 3 || (r.lv.readE || 9) <= 3 ? '2 page padhwaiye, ' : ''}${(r.lv.maths || 9) <= 4 ? 'pahade yaad karwaiye, ' : ''}aur school ka homework dekhiye.\nAapke sahyog se bachcha zaroor aage badhega. 🙏\n— ${(S.teachers[me()] || {}).name || 'Teacher'}`;
  return `<div class="tgban" style="background:linear-gradient(135deg,#0F766E,#15803D)">${esc(s.name)}<small>Class ${esc(s.cls)}${s.father ? ' · ' + esc(s.father) : ''}</small></div>
    <div class="card"><h3>Levels <small>tap to change</small></h3>${Object.entries(SUP_SK).map(([k, [l, L]]) => { const v = r.lv[k] || 0, f = first(k);
      return `<div class="suplv"><div><b>${esc(l)}</b><small>${esc(L[v])}${f && v > f ? ` · <span style="color:#15803D">↑ from ${f}</span>` : ''}${histOf(k).length ? ' · ' + esc(fmtDate(histOf(k).slice(-1)[0].d)) : ''}</small></div><div class="supbtn">${[1, 2, 3, 4, 5].map(i => `<button type="button" class="${v === i ? 'on l' + i : ''}" title="${esc(L[i])}" onclick="supSetLevel(${jsq(s.id)},'${k}',${i})">${i}</button>`).join('')}</div></div>`; }).join('')}</div>
    <div class="card"><h3>Pahade <small>${r.tables.length}/19 known</small></h3><div class="suptab">${Array.from({ length: 19 }, (_, j) => j + 2).map(n => `<button type="button" class="${r.tables.includes(n) ? 'on' : ''}" onclick="supTable(${jsq(s.id)},${n},${r.tables.includes(n) ? 0 : 1})">${n}</button>`).join('')}</div></div>
    <div class="card"><h3>Reading log <small>${r.books.length} entr${r.books.length === 1 ? 'y' : 'ies'} · ${r.books.reduce((a, b) => a + (+b.pages || 0), 0)} pages</small></h3>
      <div class="two"><input id="supBk" class="inp" maxlength="80" placeholder="Book / story name"><input id="supPg" class="inp" type="number" inputmode="numeric" min="0" max="999" placeholder="Pages"></div>
      <button class="btn l full" style="margin-top:6px" onclick="supBook(${jsq(s.id)})">+ Add reading</button>
      ${r.books.slice().reverse().slice(0, 8).map(b => `<div class="hrow"><div><b>${esc(b.title)}</b><small>${esc(fmtDate(b.d))}</small></div><span>${+b.pages || 0} pages</span></div>`).join('')}</div>
    ${s.phone ? `<a class="btn wa full" target="_blank" rel="noopener" href="${wa(s.phone, parentMsg)}">${ic('wa')} Tell the parent (progress + 15 min home practice)</a>` : ''}`;
}
async function supSetLevel(sid, skill, v) { try { const r = await api('support_api.php?a=level', { sid, skill, v }); SUP.skills[sid] = r.rec; toast('Level saved ✓'); render(); } catch (e) { toast(e.message); } }
async function supTable(sid, n, on) { try { const r = await api('support_api.php?a=tables', { sid, n, on }); SUP.skills[sid] = r.rec; render(); } catch (e) { toast(e.message); } }
async function supBook(sid) { const title = ($('supBk') || {}).value || '', pages = +(($('supPg') || {}).value || 0); if (!title.trim()) return toast('Write the book name.');
  try { const r = await api('support_api.php?a=book', { sid, title, pages }); SUP.skills[sid] = r.rec; toast('Reading added ✓'); render(); } catch (e) { toast(e.message); } }
function supGroup() {
  const g = (SUP.groups || {})[P.gid]; if (!g) return '<div class="card empty">Group not found.</div>';
  const t = todayISO(), date = P.date && P.date <= t ? P.date : t, L = (SUP.logs || []).filter(l => l.gid === P.gid), today = L.find(l => l.date === date);
  if (!DRAFT.supG || DRAFT.supG.k !== P.gid + date) DRAFT.supG = { k: P.gid + date, did: today ? today.did : '', res: Object.fromEntries(g.kids.map(id => [id, today && today.res[id] !== undefined ? today.res[id] : 1])) };
  const G = DRAFT.supG, mine = isP() || g.owner === me();
  const pct = id => { const D = L.filter(l => l.res[id] !== undefined); return D.length ? Math.round(D.filter(l => l.res[id]).length * 100 / D.length) : null; };
  return `<div class="tgban" style="background:linear-gradient(135deg,#0F766E,#15803D)">${esc(g.name)}<small>${esc((SUP_SK[g.skill] || ['Other'])[0])} · ${g.kids.length} children${g.slot ? ' · ' + esc(g.slot) : ''} · ${L.length} day(s) worked</small></div>
    <div class="card"><div class="row"><input type="date" class="inp" value="${date}" max="${t}" onchange="DRAFT.supG=null;go('support',{m:'group',gid:${jsq(P.gid)},date:this.value})"></div>
      <div class="fld" style="margin-top:8px">What did you do today?<input id="supDid" class="inp" maxlength="200" value="${esc(G.did)}" placeholder="e.g. Pahada 7 · Story page 4 · Tense: past" oninput="DRAFT.supG.did=this.value"></div>
      <p class="mut hint">Everyone starts ✓ — tap the children who did not get it yet (✗).</p>
      ${g.kids.map(id => { const s = supStu(id), on = G.res[id], p = pct(id); return `<div class="suplv"><div><b>${esc(s.name)}</b><small>Class ${esc(s.cls)}${p !== null ? ' · got it ' + p + '% of days' : ''}</small></div><button type="button" class="supok ${on ? 'y' : 'n'}" ${mine ? '' : 'disabled'} onclick="DRAFT.supG.res[${jsq(id)}]=${on ? 0 : 1};render()">${on ? '✓ Got it' : '✗ Not yet'}</button></div>`; }).join('')}
      ${mine ? `<button class="btn go full" style="margin-top:10px" onclick="supGSave(${jsq(P.gid)},'${date}')">${today ? 'Update' : 'Save'} today's work</button><div class="btns" style="margin-top:6px"><button class="btn s l" onclick="go('support',{m:'gedit',gid:${jsq(P.gid)}})">✏️ Change group</button></div>` : '<p class="mut">Only the group\'s teacher writes its day.</p>'}</div>
    ${L.length ? `<div class="card"><h3>Earlier days</h3>${L.slice(0, 15).map(l => { const v = Object.values(l.res), n = v.filter(Boolean).length; return `<div class="hrow"><div><b>${esc(l.did)}</b><small>${esc(fmtDate(l.date))}</small></div><span>${n}/${v.length} got it</span></div>`; }).join('')}</div>` : ''}`;
}
async function supGSave(gid, date) { const G = DRAFT.supG; if (!String(G.did || '').trim()) return toast('Write what you did today.');
  try { await api('support_api.php?a=glog', { gid, date, did: G.did, res: G.res }); DRAFT.supG = null; await supLoad(true); toast('Saved ✓'); } catch (e) { toast(e.message); } }
function supGEdit() {
  const old = (SUP.groups || {})[P.gid] || null, C = className(), c = C.includes(P.c) ? P.c : (supClasses()[0] || C[0]);
  if (!DRAFT.supE || DRAFT.supE.id !== (P.gid || '')) DRAFT.supE = { id: P.gid || '', name: old ? old.name : '', skill: old ? old.skill : 'readH', slot: old ? old.slot || '' : '', kids: new Set(old ? old.kids : []) };
  const E = DRAFT.supE, weak = new Set(studentsOf(c).filter(s => { const v = supRec(s.id).lv[E.skill] || 0; return v > 0 && v <= 2; }).map(s => s.id));
  return `<div class="card"><h3>${old ? 'Change group' : 'New support group'}</h3>
      <div class="fld">Group name<input class="inp" maxlength="60" value="${esc(E.name)}" placeholder="e.g. Pahada group 6th–8th" oninput="DRAFT.supE.name=this.value"></div>
      <div class="fld">Skill${sel('supSk', [...Object.entries(SUP_SK).map(([k, [l]]) => [k, l]), ['other', 'Other']], E.skill, 'DRAFT.supE.skill=this.value;render()')}</div>
      <div class="fld">When (optional)<input class="inp" maxlength="40" value="${esc(E.slot)}" placeholder="e.g. 8th bell, 20 minutes" oninput="DRAFT.supE.slot=this.value"></div>
      <p class="mut hint">${E.kids.size} children chosen. Choose from any class.</p>
      <div class="fld">Class${sel('supEC', C.map(x => [x, 'Class ' + x]), c, `go('support',{m:'gedit',gid:${jsq(P.gid || '')},c:this.value})`)}</div>
      ${weak.size ? `<button class="btn s l" onclick="studentsOf(${jsq(c)}).forEach(s=>{const v=supRec(s.id).lv[DRAFT.supE.skill]||0;if(v>0&&v<=2)DRAFT.supE.kids.add(s.id)});render()">+ Add the ${weak.size} weak children of this class</button>` : ''}
      ${studentsOf(c).map(s => `<label class="chk syl-choice"><input type="checkbox" ${E.kids.has(s.id) ? 'checked' : ''} onchange="this.checked?DRAFT.supE.kids.add(${jsq(s.id)}):DRAFT.supE.kids.delete(${jsq(s.id)});render()"><span>${esc(s.name)}${weak.has(s.id) ? ' <small class="pill bad">level ' + supRec(s.id).lv[E.skill] + '</small>' : ''}</span></label>`).join('')}
      <button class="btn go full" style="margin-top:10px" onclick="supGEditSave()">Save group</button>
      ${old ? `<button class="btn s bad" style="margin-top:8px" onclick="if(confirm('Remove this group? Its days stay in the record.'))supGDel(${jsq(P.gid)})">Remove group</button>` : ''}</div>`;
}
async function supGEditSave() { const E = DRAFT.supE; if (!E.name.trim()) return toast('Write the group name.'); if (!E.kids.size) return toast('Choose the children.');
  try { const r = await api('support_api.php?a=group', { id: E.id, name: E.name, skill: E.skill, slot: E.slot, kids: [...E.kids] }); DRAFT.supE = null; await supLoad(true); toast('Group saved ✓'); go('support', { m: 'group', gid: r.id }); } catch (e) { toast(e.message); } }
async function supGDel(id) { try { await api('support_api.php?a=delgroup', { id }); DRAFT.supE = null; await supLoad(true); go('support'); } catch (e) { toast(e.message); } }
// Home cards: teacher Home and Principal Home
if (typeof vHome === 'function') {
  const _vhsp = vHome;
  vHome = function () { const html = _vhsp.apply(this, arguments); if (!S || !ME || (typeof pMode === 'function' && pMode())) return html;
    return html + `<div class="card"><div class="row"><div><b>🌱 Learning Support</b><small>Weak children: reading, maths, grammar — levels, small groups, progress.</small></div><button class="btn go s" onclick="go('support')">Open</button></div></div>`; };
}
if (typeof vPdash === 'function') {
  const _vpdsp = vPdash;
  vPdash = function () { const html = _vpdsp.apply(this, arguments);
    return `<div class="card lpb-home"><div class="row"><div><b>🌱 Learning Support</b><small>Weak children of every class, support groups and their progress.</small></div><button class="btn go" onclick="go('support')">Open</button></div></div>` + html; };
}
(function () { const st = document.createElement('style'); st.textContent = `.supbar{display:inline-flex;gap:2px;vertical-align:middle}.supbar i{width:7px;height:10px;border-radius:2px;background:#E5E7EB}.supbar i.r{background:#EF4444}.supbar i.y{background:#F59E0B}.supbar i.g{background:#22C55E}
  .suplv{display:flex;justify-content:space-between;align-items:center;gap:8px;padding:8px 0;border-top:1px solid #eef1f4}.suplv small{display:block;color:#667;font-size:12px}
  .supbtn{display:flex;gap:4px}.supbtn button{width:34px;height:34px;border-radius:10px;border:1px solid #d6dbe3;background:#fff;font:inherit;font-weight:700}.supbtn button.on.l1,.supbtn button.on.l2{background:#FEE2E2;border-color:#EF4444;color:#B91C1C}.supbtn button.on.l3{background:#FEF3C7;border-color:#F59E0B;color:#92400E}.supbtn button.on.l4,.supbtn button.on.l5{background:#DCFCE7;border-color:#22C55E;color:#166534}
  .suptab{display:grid;grid-template-columns:repeat(6,1fr);gap:6px}.suptab button{height:38px;border-radius:10px;border:1px solid #d6dbe3;background:#fff;font:inherit;font-weight:700}.suptab button.on{background:#DCFCE7;border-color:#22C55E;color:#166534}
  .supok{border:0;border-radius:99px;padding:8px 12px;font:inherit;font-weight:700;min-width:96px}.supok.y{background:#DCFCE7;color:#166534}.supok.n{background:#FEE2E2;color:#B91C1C}`; document.head.appendChild(st); })();
