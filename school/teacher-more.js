// ============================================================================
//  Teacher App — owner, 6 Nov 2026 ("aaj 3 bell ke baad competition hoga, timetable kaise; Ritu Ma'am ka sara bell
//  Rati Ma'am le rahi hain, EVS Palak Srivastava; aapne jo bataya sab kar dijiye"). Loaded after teacher-nav.js.
//
//  1. Special day: from bell N no classes today (competition, function, sports …) for all or chosen classes —
//     cal {type:'Activity', date, fromBell, classes, title}. Diary / cross-check / arrangement skip those bells
//     (actBlocks in teacher.js). Banner on Today and Principal Home.
//  2. Staff → Teachers: replace a teacher (all bells or chosen subjects, also class teacher) by an existing or a NEW
//     teacher; add a teacher; mark that a teacher has left. Same records as the web ERP: alloc / classes / teacher /
//     settings.leftTeachers. The timetable follows by itself (grid points to the allocation).
//  3. Student log (teacher_api.php log / logbulk / dellog, kind tchLog): parent contact, PTM attendance, activity /
//     competition (participation + 1st / 2nd / 3rd) — shown in the child's report; filled class-wise in Levels.
// ============================================================================
'use strict';

// ---------------------------------------------------------------- 1. Special day
function actBanner(d) {
  const L = S.cal.filter(e => e.type === 'Activity' && e.date === d); if (!L.length) return '';
  return L.map(e => `<div class="ban act"><div><b>🎉 ${esc(e.title || 'Special day')}</b><br><span class="sm">${+e.fromBell ? 'Classes till the ' + esc(BELLS[+e.fromBell - 1].n) + ' bell; from the ' + esc(BELLS[+e.fromBell].n) + ' bell (' + t12(BELLS[+e.fromBell].s) + ')' : 'The whole day'} — ${(e.classes || []).length ? 'Class ' + e.classes.map(esc).join(', ') : 'all classes'}. No diary or arrangement for those bells.</span>
    ${isP() ? `<br><button class="btn s w" style="margin-top:6px" onclick="spDay(${jsq(e.id)})">Change</button>` : `<br><button class="btn s l" style="margin-top:6px" onclick="go('att',{m:'bulk',bm:'act'})">Fill participants / winners</button>`}</div></div>`).join('');
}
function spDay(id) {
  const e = id ? S.cal.find(x => x.id === id) : null, t = todayISO();
  if (!DRAFT.sp || DRAFT.sp.id !== (id || '')) DRAFT.sp = { id: id || '', classes: e ? (e.classes || []).slice() : [] };
  SHEET = () => `<h2>🎉 Special day</h2><p class="mut" style="margin:0">Competition, function, sports day, PTM… From the chosen bell there are no classes — no diary, no arrangement, cross-check skips them.</p>
    <div class="two"><div class="fld">Date<input id="spD" type="date" class="inp" value="${esc(e ? e.date : t)}"></div><div class="fld">No classes from<select id="spB" class="inp">${BELLS.map((b, i) => `<option value="${i}" ${(e ? +e.fromBell : 3) === i ? 'selected' : ''}>${i ? b.n + ' bell (' + t12(b.s) + ')' : 'Whole day'}</option>`).join('')}</select></div></div>
    <div class="fld">Name<input id="spT" class="inp" maxlength="120" value="${esc(e ? e.title : '')}" placeholder="e.g. Rangoli & Diya making competition"></div>
    <div class="chips">${['Rangoli & Diya making competition', 'Annual function practice', 'Sports day', 'PTM', 'Drawing competition', 'Quiz competition', 'Celebration'].map(x => `<button class="chip" onclick="$('spT').value=${jsq(x)}">${esc(x)}</button>`).join('')}</div>
    <div class="fld">Classes <small class="mut">(none chosen = all classes)</small><div class="chips">${className().map(c => `<button type="button" class="chip ${DRAFT.sp.classes.includes(c) ? 'on' : ''}" onclick="spCls(${jsq(c)},this)">${esc(c)}</button>`).join('')}</div></div>
    <button class="btn go full" onclick="spSave()">Save special day</button>
    ${e ? `<button class="btn w full" onclick="spDel(${jsq(e.id)})">Remove</button>` : ''}`;
  render();
}
function spCls(c, el) { const L = DRAFT.sp.classes, i = L.indexOf(c); if (i >= 0) L.splice(i, 1); else L.push(c); el.classList.toggle('on', i < 0); }
function spSave() {
  const date = $('spD').value, fromBell = +$('spB').value, title = ($('spT').value || '').trim().slice(0, 120) || 'Special day';
  if (!date) return toast('Choose the date.');
  const id = DRAFT.sp.id || 'act-' + uid(), rec = { id, date, to: '', title, type: 'Activity', fromBell, classes: DRAFT.sp.classes.slice(), src: 'Teacher App', by: myName() };
  put('cal', id, rec, 'Calendar', `Special day ${date}: ${title} — no classes from ${fromBell ? BELLS[fromBell].n + ' bell' : 'the start'} (${rec.classes.length ? rec.classes.join(', ') : 'all classes'}) — Teacher App`);
  Object.keys(LOADC).forEach(k => delete LOADC[k]); DRAFT.sp = null; SHEET = null; toast('Saved. Teachers see it on their Today page.'); render();
}
function spDel(id) { const e = S.cal.find(x => x.id === id); if (!e || !confirm('Remove “' + e.title + '”?')) return; putDel('cal', id, 'Calendar', 'Special day removed: ' + e.title + ' (' + e.date + ') — Teacher App'); Object.keys(LOADC).forEach(k => delete LOADC[k]); SHEET = null; render(); }
(function () { const _go = go; go = function (tab, p) { if (tab === 'spday') { const e = S.cal.find(x => x.type === 'Activity' && x.date === todayISO()); return spDay(e ? e.id : ''); } return _go(tab, p); }; })();

// ---------------------------------------------------------------- 2. Staff → Teachers (replace / add / left)
function newTid(name) {
  const base = (String(name).replace(/\b(ma'?am|sir|mrs?|miss|ms)\b\.?/gi, '').trim().split(/\s+/).map(w => w[0] || '').join('') || 'T').toUpperCase().replace(/[^A-Z]/g, '').slice(0, 3) || 'T';
  let id = base, n = 2; while (S.teachers[id] || ((S.settings || {}).leftTeachers || {})[id]) id = base + n++; return id;
}
function vTeam() {
  const ts = Object.keys(S.teachers).sort((a, b) => T(a).name.localeCompare(T(b).name));
  const subj = t => S.alloc.filter(a => a.t === t && (+a.n || 0) > 0).map(a => a.c + ' ' + a.s);
  return `<div class="card"><h3>Change who teaches <small>Ritu → Rati, EVS → Palak …</small></h3><p class="mut" style="margin:0 0 8px">All bells (or only chosen subjects) of one teacher go to another teacher. The timetable, diary, arrangement and cross-check follow at once. Old diaries stay as they were.</p>
      <button class="btn go full" onclick="repOpen()">↔ Replace a teacher</button><button class="btn l full" style="margin-top:6px" onclick="addTeacherOpen()">+ Add a new teacher</button></div>
    <div class="card"><h3>Teachers <small>${ts.length}</small></h3>${ts.map(t => { const L = subj(t), ct = (S.classes || []).filter(k => k.ct === t).map(k => k.c);
      return `<div class="li"><div class="t"><b>${esc(T(t).name)}</b><span>${L.length ? esc(L.join(' · ')) : 'No subject in the timetable'}${ct.length ? ' · Class teacher ' + esc(ct.join(', ')) : ''}</span></div><button class="btn s l" onclick="repOpen(${jsq(t)})">Replace</button></div>`; }).join('')}</div>`;
}
function repOpen(from) {
  const ts = Object.keys(S.teachers).sort((a, b) => T(a).name.localeCompare(T(b).name));
  if (!DRAFT.rep || (from && DRAFT.rep.from !== from)) DRAFT.rep = { from: from || ts[0], to: '', newName: '', subs: null, ct: true, left: false };
  const R = DRAFT.rep, mine = S.alloc.map((a, i) => [a, i]).filter(([a]) => a.t === R.from);
  if (!R.subs) R.subs = Object.fromEntries(mine.map(([, i]) => [i, true]));
  const ctOf = (S.classes || []).filter(k => k.ct === R.from).map(k => k.c);
  SHEET = () => `<h2>↔ Replace a teacher</h2>
    <div class="fld">From (who is not teaching now)<select class="inp" onchange="DRAFT.rep={from:this.value,to:DRAFT.rep.to,newName:DRAFT.rep.newName,subs:null,ct:true,left:false};repOpen()">${ts.map(t => `<option value="${esc(t)}" ${t === R.from ? 'selected' : ''}>${esc(T(t).name)}</option>`).join('')}</select></div>
    <div class="fld">To (who will teach)<select class="inp" onchange="DRAFT.rep.to=this.value;repOpen()"><option value="">— choose —</option>${ts.filter(t => t !== R.from).map(t => `<option value="${esc(t)}" ${t === R.to ? 'selected' : ''}>${esc(T(t).name)}</option>`).join('')}<option value="+new" ${R.to === '+new' ? 'selected' : ''}>+ New teacher (not in the list)</option></select></div>
    ${R.to === '+new' ? `<div class="fld">New teacher's name<input class="inp" maxlength="60" value="${esc(R.newName)}" placeholder="e.g. Palak Srivastava Ma'am" oninput="DRAFT.rep.newName=this.value"></div><p class="mut" style="margin:0">They log in with PIN 1234 the first time and choose their own PIN.</p>` : ''}
    <div class="fld">Which subjects<div class="rep-s">${mine.map(([a, i]) => `<label class="chk"><input type="checkbox" ${R.subs[i] ? 'checked' : ''} onchange="DRAFT.rep.subs[${i}]=this.checked"> Class ${esc(a.c)} · ${esc(a.s)} <small class="mut">${+a.n || 0}/week</small></label>`).join('') || '<p class="mut">This teacher has no subject in the timetable.</p>'}</div></div>
    ${ctOf.length ? `<label class="chk"><input type="checkbox" ${R.ct ? 'checked' : ''} onchange="DRAFT.rep.ct=this.checked"> Also make them class teacher of ${esc(ctOf.join(', '))}</label>` : ''}
    <label class="chk"><input type="checkbox" ${R.left ? 'checked' : ''} onchange="DRAFT.rep.left=this.checked"> ${esc(T(R.from).name)} has left the school (remove the login; old records keep the name)</label>
    <button class="btn go full" onclick="repApply()">Apply</button>`;
  render();
}
function repApply() {
  const R = DRAFT.rep; if (!R) return; const from = R.from; let to = R.to;
  if (!to) return toast('Choose who will teach.');
  const name = String(R.newName || '').trim().slice(0, 60); if (to === '+new' && name.length < 3) return toast('Write the new teacher\'s name.');
  const idx = Object.keys(R.subs || {}).filter(i => R.subs[i]).map(Number);
  const ctOf = (S.classes || []).filter(k => k.ct === from).map(k => k.c);
  if (!idx.length && !(R.ct && ctOf.length) && !R.left) return toast('Choose at least one subject.');
  const toId = to === '+new' ? newTid(name) : to, toName = to === '+new' ? name : T(to).name;
  const clash = []; if (to !== '+new' && S.grid) idx.forEach(i => className().forEach(c => (S.grid[c] || []).forEach((row, d) => row.forEach((j, b) => { if (j === i) { const q = teacherAt(toId, d, b); if (q) clash.push(DAYS[d].slice(0, 3) + ' ' + BELLS[b].n + ' (' + q.c + ')'); } }))));
  const msg = `${toName} will teach:\n${idx.map(i => '• Class ' + S.alloc[i].c + ' ' + S.alloc[i].s).join('\n') || '—'}${R.ct && ctOf.length ? '\n• Class teacher of ' + ctOf.join(', ') : ''}${R.left ? '\n\n' + T(from).name + ' will be removed (left the school).' : ''}${clash.length ? '\n\n⚠ ' + toName + ' already has a class at: ' + clash.slice(0, 8).join(', ') + (clash.length > 8 ? '…' : '') + '\nThose bells will show as a clash in the timetable.' : ''}\n\nApply?`;
  if (!confirm(msg)) return;
  if (to === '+new') put('teacher', toId, { name: toName, role: 'Teacher' }, 'Teachers', `New teacher added: ${toName} (${toId}) — Teacher App`);
  if (idx.length) { const al = JSON.parse(JSON.stringify(S.alloc)); idx.forEach(i => { if (al[i]) al[i].t = toId; }); put('alloc', 'main', al, 'Timetable', `${T(from).name} → ${toName}: ${idx.map(i => S.alloc[i].c + ' ' + S.alloc[i].s).join(', ')} — Teacher App`); }
  if (R.ct && ctOf.length) { const cl = JSON.parse(JSON.stringify(S.classes)); cl.forEach(k => { if (k.ct === from) k.ct = toId; }); put('classes', 'main', cl, 'Classes', `Class teacher of ${ctOf.join(', ')}: ${T(from).name} → ${toName} — Teacher App`); }
  if (R.left) { const st = JSON.parse(JSON.stringify(S.settings || {})); st.leftTeachers = Object.assign({}, st.leftTeachers || {}, { [from]: T(from).name }); put('settings', 'main', st, 'Teachers', `${T(from).name} has left the school — Teacher App`); putDel('teacher', from, 'Teachers', `Removed teacher login: ${T(from).name}`); }
  Object.keys(LOADC).forEach(k => delete LOADC[k]); DRAFT.rep = null; SHEET = null; toast('Done. ' + toName + ' now has these bells.'); render();
}
function addTeacherOpen() {
  SHEET = () => `<h2>+ Add a new teacher</h2><div class="fld">Name<input id="atN" class="inp" maxlength="60" placeholder="e.g. Palak Srivastava Ma'am"></div><div class="fld">WhatsApp number (optional)<input id="atP" class="inp" inputmode="numeric" maxlength="14"></div>
    <p class="mut" style="margin:0">First login with PIN 1234. Then give subjects with "Replace a teacher" or in the web ERP → Subjects &amp; Teachers.</p><button class="btn go full" onclick="addTeacherSave()">Add teacher</button>`;
  render();
}
function addTeacherSave() {
  const name = ($('atN').value || '').trim().slice(0, 60), ph = ($('atP').value || '').replace(/\D/g, '').slice(-10); if (name.length < 3) return toast('Write the name.');
  const id = newTid(name); put('teacher', id, Object.assign({ name, role: 'Teacher' }, ph.length === 10 ? { phone: ph } : {}), 'Teachers', `New teacher added: ${name} (${id}) — Teacher App`);
  SHEET = null; toast(name + ' added. First PIN 1234.'); render();
}

// ---------------------------------------------------------------- 3. Student log — child report + class-wise entry
function stuMore(c, s, R) {
  const L = (R && R.logs || []).filter(l => l.sid === s.id), calls = L.filter(l => l.kind === 'call'), ptm = L.filter(l => l.kind === 'ptm'), act = L.filter(l => l.kind === 'act');
  const med = p => p === '1' ? '🥇 1st' : p === '2' ? '🥈 2nd' : p === '3' ? '🥉 3rd' : 'Took part';
  return `<div class="card"><h3>Parent contact <small>${calls.length}</small></h3>
      <div class="chips" id="lgM">${['Call', 'Visit', 'WhatsApp', 'Meeting'].map((m, i) => `<button type="button" class="chip ${i ? '' : 'on'}" data-m="${m}" onclick="this.parentNode.querySelectorAll('.chip').forEach(x=>x.classList.toggle('on',x===this))">${m}</button>`).join('')}</div>
      <div class="chips phr" style="margin-top:6px">${['Talked about homework', 'Talked about attendance', 'Fee reminder', 'Shared good progress', 'Behaviour concern', 'Parent asked for a meeting'].map(x => `<button type="button" class="chip" onclick="$('lgT').value=${jsq(x)}">${esc(x)}</button>`).join('')}</div>
      <div class="send"><textarea id="lgT" class="inp" rows="2" maxlength="500" placeholder="What was talked about?"></textarea><button class="btn go" onclick="logSave(${jsq(c)},${jsq(s.id)})">Add</button></div>
      <div class="tl">${calls.map(l => `<div class="tli good"><i>📞</i><div><b>${esc(l.text)}</b><small>${esc(l.mode)} · ${esc(fmtDate(l.date))} · ${esc(l.by || '')}</small></div>${l.tid === R.me || isP() ? `<button class="x" aria-label="Delete" onclick="logDel(${jsq(c)},${jsq(l.id)})">✕</button>` : ''}</div>`).join('') || '<div class="empty">No contact written yet.</div>'}</div></div>
    <div class="card"><h3>PTM & activities</h3>
      <p style="margin:0 0 6px"><b>PTM:</b> ${ptm.length ? ptm.filter(p => p.present).length + ' of ' + ptm.length + ' attended — ' + ptm.map(p => (p.present ? '✓ ' : '✕ ') + esc(fmtDate(p.date))).join(', ') : '<span class="mut">not filled yet</span>'}</p>
      ${act.length ? act.map(a => `<div class="li"><div class="t"><b>${esc(a.title)}</b><span>${esc(fmtDate(a.date))}</span></div><span class="pill ${a.pos === 'part' ? '' : 'ok'}">${med(a.pos)}</span></div>`).join('') : '<p class="mut" style="margin:0">No activity / competition yet.</p>'}
      <p class="mut" style="margin:6px 0 0">Filled for the whole class in Attendance → Levels → PTM / Competition.</p></div>`;
}
async function logSave(c, sid) {
  const text = (($('lgT') || {}).value || '').trim(), m = document.querySelector('#lgM .chip.on'); if (!text) return toast('Write what was talked about.');
  try { const r = await api('teacher_api.php?a=log', { sid, text, mode: m ? m.dataset.m : 'Call' }); (REP[c].logs = REP[c].logs || []).unshift(r.log); toast('Saved.'); render(); } catch (e) { toast(e.message); }
}
async function logDel(c, id) { if (!confirm('Delete this note?')) return; try { await api('teacher_api.php?a=dellog', { id }); REP[c].logs = (REP[c].logs || []).filter(l => l.id !== id); render(); } catch (e) { toast(e.message); } }
function vBulkLog(c, kind) {
  const R = needRep(c), kids = studentsOf(c), ph = needPhotos(c), t = todayISO(), act = actOn(t, c);
  if (R.loading) return '<div class="load"><i></i></div>';
  if (!DRAFT.lb || DRAFT.lb.c !== c || DRAFT.lb.kind !== kind) DRAFT.lb = { c, kind, date: t, title: kind === 'act' ? (act ? act.title : '') : 'PTM', rows: {}, n: 0 };
  const D = DRAFT.lb, logs = (R.logs || []).filter(l => l.kind === kind && l.date === D.date && (kind === 'ptm' || String(l.title).toLowerCase() === String(D.title).toLowerCase()));
  const cur = sid => sid in D.rows ? D.rows[sid] : (l => l ? (kind === 'ptm' ? l.present : l.pos) : '')(logs.find(l => l.sid === sid));
  const opts = kind === 'ptm' ? [[1, 'Came'], [0, 'Did not come']] : [['part', 'Took part'], ['1', '🥇 1st'], ['2', '🥈 2nd'], ['3', '🥉 3rd']];
  const filled = kids.filter(s => cur(s.id) !== '' && cur(s.id) !== undefined).length;
  return `<div class="card"><div class="two"><div class="fld">Date<input type="date" class="inp" max="${t}" value="${esc(D.date)}" onchange="DRAFT.lb.date=this.value;DRAFT.lb.rows={};render()"></div>
      <div class="fld">${kind === 'ptm' ? 'Name' : 'Competition / activity'}<input class="inp" maxlength="120" value="${esc(D.title)}" placeholder="${kind === 'ptm' ? 'PTM' : 'e.g. Rangoli competition'}" onchange="DRAFT.lb.title=this.value;DRAFT.lb.rows={};render()"></div></div>
      ${kind === 'act' ? `<div class="chips" style="margin-top:6px">${[...new Set([...(act ? [act.title] : []), 'Rangoli competition', 'Diya making competition', 'Drawing competition', 'Sports', 'Quiz'])].map(x => `<button class="chip ${x === D.title ? 'on' : ''}" onclick="DRAFT.lb.title=${jsq(x)};DRAFT.lb.rows={};render()">${esc(x)}</button>`).join('')}</div>` : ''}</div>
    <div class="card bulk"><div class="roll-head"><b>${kind === 'ptm' ? 'Did the parent come?' : 'Who took part / won?'}</b><span class="pill">${filled}/${kids.length}</span></div>
      ${kind === 'ptm' ? `<div class="btns" style="margin:6px 0"><button class="btn s l" onclick="studentsOf(DRAFT.lb.c).forEach(s=>DRAFT.lb.rows[s.id]=1);DRAFT.lb.n++;render()">All came</button></div>` : '<p class="mut hint">Tap again to clear a child.</p>'}
      ${kids.map(s => { const v = cur(s.id);
        return `<div class="bk-r"><span class="ph sm">${ph[s.id] ? `<img src="${esc(ph[s.id].src)}" alt="" loading="lazy">` : `<i>${esc(initials(s.name))}</i>`}</span><b>${esc(s.name)}</b>
          <div class="bk-o ${kind === 'ptm' ? 'rec' : ''}">${opts.map(([o, l]) => `<button type="button" class="${kind === 'ptm' ? (o ? 'y' : 'x') : o === 'part' ? 'l3' : 'l4'} ${String(v) === String(o) ? 'on' : ''}" onclick="lbSet(${jsq(s.id)},${jsq(String(o))})">${l}</button>`).join('')}</div></div>`; }).join('')}
      <div class="sticky"><button class="btn go full" ${D.n ? '' : 'disabled'} onclick="lbSave()">Save · ${Object.keys(D.rows).length} child(ren)</button></div></div>`;
}
function lbSet(sid, v) { const D = DRAFT.lb, cur = D.rows[sid]; const val = D.kind === 'ptm' ? +v : v; D.rows[sid] = String(cur) === String(val) && D.kind === 'act' ? '' : val; D.n++; render(); }
async function lbSave() {
  const D = DRAFT.lb; if (!D) return; if (D.kind === 'act' && !String(D.title).trim()) return toast('Write the name of the competition.');
  try { const r = await api('teacher_api.php?a=logbulk', { cls: D.c, kind: D.kind, date: D.date, title: D.title, rows: D.rows }); delete REP[D.c]; DRAFT.lb = null; toast(r.saved + ' saved.'); render(); }
  catch (e) { toast(e.message); }
}
