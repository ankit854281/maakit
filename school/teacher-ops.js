// ============================================================================
//  Teacher App — Principal operations (owner, 5 Nov 2026: "jaise attendance ka cross-check banaya, vaise sabhi ka,
//  usse best; arrangement feature; timetable edit easy; har feature kyun diya gaya aur kya karna hai bataye;
//  jahan suggestion de sakte ho do"). Loaded AFTER teacher-ux.js.
//
//  1. Cross-check segments: Teachers (the old list) · Attendance · Diary · HW check · Daily check · Lesson plan ·
//     Syllabus — each a school-wide grid like the attendance one; tap a box = see / approve / remind.
//  2. Staff & arrangement (TAB 'staff'): tap absent teachers (half day too), leave requests, substitute slips from
//     the SAME arrangementBase as app.js (Auto / choose / nobody → staffAtt[date].subs), WhatsApp, who is free.
//  3. Timetable (TAB 'tt'): class × day × bell grid; tap a box → choose subject-teacher of that class; clashes shown.
//     Saves the same `grid` record the web ERP uses.
//  4. Help: why each feature exists (HELP_WHY), and quick suggestion chips (leave reason, send-back feedback).
// ============================================================================
'use strict';

// ---------------------------------------------------------------- helpers
async function xRemind(t, text) {
  try { const room = scDm('P', t), r = await api('teacher_api.php?a=staffchat', { room, text }); (SCR[room] = SCR[room] || []).push(r.msg); toast('Sent to ' + T(t).name + ' in Staff chat.'); }
  catch (e) { toast(e.message); }
}
function xRemindBtn(t, text) { return `<button class="btn s go" onclick="event.stopPropagation();xRemind(${jsq(t)},${jsq(text)})">${ic('chat')} Remind</button>`; }
function xDatebar(view, date) {
  const t = todayISO();
  return `<div class="datebar"><button class="btn s w" onclick="go('prin',{v:'${view}',date:'${addDays(date, -1)}'})" aria-label="Previous day">${ic('back')}</button>
    <label class="dpick"><b>${date === t ? 'Today' : fmtDate(date)}</b><small>${esc(fmtLong(date))}</small><input type="date" max="${t}" value="${date}" onchange="go('prin',{v:'${view}',date:this.value})"></label>
    <button class="btn s w" ${date >= t ? 'disabled' : ''} onclick="go('prin',{v:'${view}',date:'${addDays(date, 1)}'})" aria-label="Next day">${ic('next')}</button></div>`;
}
const XV = [['att', 'Attendance'], ['diary', 'Diary'], ['tch', 'Teachers'], ['hw', 'HW check'], ['kit', 'Daily check'], ['lp', 'Lesson plan'], ['syl', 'Syllabus']];
const _vPrinOld = vPrin;
vPrin = function () {
  const v = XV.some(x => x[0] === P.v) ? P.v : 'att', t = todayISO(), date = P.date && P.date <= t ? P.date : t;
  const tabs = `<div class="xv">${XV.map(([k, l]) => `<button class="${k === v ? 'on' : ''}" onclick="go('prin',{v:'${k}',date:'${date}'})">${l}</button>`).join('')}</div>`;
  if (date < (loadedFrom || '') && !DAYS_LOADED[date]) { ensureDate(date).then(render); return tabs + '<div class="load"><i></i></div>'; }
  const body = { tch: xTch, att: xAtt, diary: xDiary, hw: xHw, kit: xKit, lp: xLp, syl: xSyl }[v](date);
  return tabs + body;
};

// ---------------------------------------------------------------- 1. per-feature grids
// Every cross-check page looks like the attendance one (owner, 5 Nov 2026: "attendance cross-check achha hai, baaki samajh
// nahi aa raha — sab usi jaisa"): number boxes on top, then one green / red box per class (or teacher), tap = details.
function xPage(view, date, o) {
  const kp = o.kpis.map(([l, a, b, sub, cls]) => `<div class="k ${cls || (b === null || b === undefined ? '' : b && a === b ? 'ok' : a < b ? 'go' : '')}"><span>${l}</span><b>${b === null || b === undefined ? a : b === 0 ? '—' : a + '/' + b}</b><small>${sub || (b ? (a === b ? 'all done' : (b - a) + ' not done') : 'nothing due')}</small></div>`).join('');
  const pend = o.tiles.filter(t => t.st === 'no');
  return (o.nav === false ? '' : xDatebar(view, date)) + (o.top || '') + `<div class="kp">${kp}</div>
    ${o.remind && pend.length ? `<button class="btn go full" onclick="xRemindAll(${jsq(view)},'${date}')">${ic('chat')} Remind all ${o.remind(pend)} in the app</button>` : ''}
    <div class="card"><h3>${o.title} <small>${o.tiles.filter(t => t.st === 'ok').length}/${o.tiles.filter(t => t.st !== 'hol').length} done</small></h3>
      ${o.tiles.length ? `<div class="xc-cls xc-big">${o.tiles.map(t => `<div class="${t.st}" ${t.tap ? `onclick="${t.tap}"` : ''}><b>${esc(t.b)}</b><small>${esc(t.small || '')}</small><span>${t.line}</span></div>`).join('')}</div>` : `<div class="empty">${o.empty || 'Nothing to check on this day.'}</div>`}
      <p class="mut" style="margin:8px 0 0">${o.note}</p></div>${o.bottom || ''}`;
}
const XR = {};      // view → { teacher: message } for "Remind all"
async function xRemindAll(view, date) {
  const L = Object.entries(XR[view] || {}); if (!L.length || !confirm('Send each of these ' + L.length + ' teacher(s) their own reminder in the app?')) return;
  let n = 0; for (const [t, text] of L) { try { await api('teacher_api.php?a=staffchat', { room: scDm('P', t), text }); n++; } catch (e) { toast(e.message); break; } }
  toast(n + ' reminder(s) sent in the app.'); SC = null;
}
function xAtt(date) {
  const cls = (S.classes || []).filter(k => studentsOf(k.c).length && isWorkDay(date, k.c)), work = isWorkDay(date);
  let tot = 0, pres = 0, abs = 0, lv = 0, mk = 0; XR.att = {};
  const tiles = cls.map(k => { const a = attOf(date, k.c), n = studentsOf(k.c).length; tot += n;
    if (!a) { if (date < todayISO() || nowMin() >= toMin(S.settings.attendanceCutoff || '09:30')) if (k.ct) XR.att[k.ct] = `Namaste ${T(k.ct).name}, Class ${k.c} ki haziri ${date === todayISO() ? 'aaj' : fmtLong(date) + ' ko'} app mein nahi lagi. Kripya laga dein. — Principal`;
      return { st: 'no', b: k.c, small: T(k.ct).name || '', line: `Not marked · ${n} students`, tap: `go('att',{c:${jsq(k.c)},date:'${date}'})` }; }
    const L = Object.values(a.st || {}).filter(x => x === 'L').length, A = (a.absent || []).length - L; mk += n; pres += n - A - L; abs += A; lv += L;
    return { st: 'ok', b: k.c, small: T(k.ct).name || '', line: `✓ ${n - A - L}/${n} present · ${A} A${L ? ' · ' + L + ' L' : ''}`, tap: `go('att',{c:${jsq(k.c)},date:'${date}'})` }; });
  const pct = mk ? Math.round(pres / mk * 100) : null;
  if (!work) return xDatebar('att', date) + `<div class="ban hol">${dayIdx(date) < 0 ? 'Sunday' : 'Holiday — ' + esc(holTitle(holidayOn(date)))}. No attendance.</div>`;
  return xPage('att', date, { title: 'Classes', tiles, kpis: [['Classes marked', tiles.filter(t => t.st === 'ok').length, tiles.length], ['Students present', mk ? pres + '/' + mk : '—', null, pct !== null ? pct + '% present' : 'no class marked yet', 'ok'],
      ['Absent', abs, null, lv ? lv + ' on leave' : 'children', abs ? 'bad' : ''], ['Total students', tot, null, cls.length + ' classes']],
    remind: p => p.length + ' class teacher(s)', note: 'Green = marked (present / absent / leave), red = not marked. Tap a class to open its attendance.' });
}
function xDiary(date) {
  XR.diary = {}; let all = 0, done = 0;
  const tiles = Object.keys(S.teachers).map(t => ({ t, b: bellsOf(t, date).filter(x => bellOver(date, x.bell)) })).filter(x => x.b.length).sort((a, b) => T(a.t).name.localeCompare(T(b.t).name)).map(({ t, b }) => {
    const f = b.filter(x => ((S.diary[date] || {})[t] || {})[x.bell]).length; all += b.length; done += f; const miss = b.filter(x => !((S.diary[date] || {})[t] || {})[x.bell]);
    if (miss.length) XR.diary[t] = `Namaste ${T(t).name}, ${date === todayISO() ? 'aaj' : fmtLong(date) + ' ko'} ki diary app mein baaki hai: ${miss.map(x => BELLS[x.bell].n + ' bell (Class ' + x.cls + ')').join(', ')}. — Principal`;
    return { st: f === b.length ? 'ok' : 'no', b: T(t).name, small: b.map(x => x.cls).filter((v, i, a) => a.indexOf(v) === i).join(', '), line: f === b.length ? `✓ ${f}/${b.length} bells${(S.signed[date] || {})[t] ? ' · ✍' : ''}` : `✕ ${b.length - f} of ${b.length} not filled`, tap: `xDiaryT(${jsq(t)},'${date}')` }; });
  return xPage('diary', date, { title: 'Teachers', tiles, kpis: [['Diary filled', done, all, 'bells that are over'], ['Teachers', tiles.filter(x => x.st === 'ok').length, tiles.length, 'all bells filled']],
    remind: p => p.length + ' teacher(s)', note: 'Only bells that are over are counted. Tap a teacher to read each bell and sign.', empty: 'No bell is over yet.' });
}
function xDiaryT(t, date) {
  const b = bellsOf(t, date), sg = (S.signed[date] || {})[t];
  SHEET = () => `<h2>${esc(T(t).name)} — diary</h2><p class="mut" style="margin:0">${esc(fmtLong(date))}${sg ? ' · ✍ signed' : ''}</p>
    ${b.map(x => { const e = ((S.diary[date] || {})[t] || {})[x.bell], over = bellOver(date, x.bell);
      return `<div class="help-i ${e ? '' : over ? 'miss' : ''}"><b>${esc(BELLS[x.bell].n)} bell · Class ${esc(x.cls)} · ${esc(String(x.subj).replace(/\s*\(optional\)/i, ''))}${x.arr ? ' · arrangement' : ''}</b>
        <p>${e ? `<b>Topic:</b> ${esc(e.topic)}${e.classwork ? '<br><b>CW:</b> ' + esc(e.classwork) : ''}${e.hw ? '<br><b>HW:</b> ' + esc(e.hw) + (e.hwMinutes ? ' (' + e.hwMinutes + ' min)' : '') : '<br>No homework'}` : over ? '✕ Not filled' : 'Bell not over yet'}</p></div>`; }).join('')}
    <div class="btns">${!sg ? `<button class="btn go" onclick="xSign(${jsq(t)},'${date}');closeSheet()">✍ Sign diary</button>` : ''}${XR.diary && XR.diary[t] ? `<button class="btn w" onclick="xRemind(${jsq(t)},${jsq(XR.diary[t])})">${ic('chat')} Remind</button>` : ''}</div>`;
  render();
}
function xHw(date) {
  const X = needXC(date); XR.hw = {}; const tiles = [];
  Object.keys(S.teachers).forEach(t => Object.entries(hwDueFor(t, date)).forEach(([k, hw]) => { const [c, s] = k.split('|'); if (!isWorkDay(date, c)) return;
    const chk = X && X.chk ? X.chk.find(x => x.cls === c && x.subj === s) : null;
    if (!chk && X && !X.loading) XR.hw[t] = (XR.hw[t] ? XR.hw[t].replace(/ — Principal$/, ', ') : `Namaste ${T(t).name}, ${date === todayISO() ? 'aaj' : fmtLong(date) + ' ko'} ye homework check hona tha (Classroom → Check work): `) + `Class ${c} ${s}` + ' — Principal';
    tiles.push({ st: chk ? 'ok' : X && X.loading ? 'hol' : 'no', b: c + ' · ' + s, small: T(t).name, line: chk ? '✓ checked' : X && X.loading ? '…' : '✕ not checked', tap: `toast(${jsq('HW: ' + hw.join(' / '))})` }); }));
  tiles.sort((a, b) => (a.st === 'ok') - (b.st === 'ok'));
  return xPage('hw', date, { title: 'Homework due today', tiles, kpis: [['Homework checked', tiles.filter(t => t.st === 'ok').length, tiles.length, 'class + subject'], ['Teachers', Object.keys(XR.hw).length, null, 'still to check', Object.keys(XR.hw).length ? 'bad' : 'ok']],
    remind: p => Object.keys(XR.hw).length + ' teacher(s)', note: 'Homework that was due on this day (from the diaries). Green = the subject teacher saved Check work. Tap a box to see the homework.', empty: 'No homework was due on this day.' });
}
function xKit(date) {
  const X = needXC(date), cls = (S.classes || []).filter(k => studentsOf(k.c).length && isWorkDay(date, k.c)); XR.kit = {};
  const tiles = cls.map(k => { const kt = X && X.kit && X.kit[k.c];
    if (!kt && X && !X.loading && k.ct) XR.kit[k.ct] = `Namaste ${T(k.ct).name}, Class ${k.c} ka daily check (diary · ID · kitaab · dress) ${date === todayISO() ? 'aaj' : fmtLong(date) + ' ko'} app mein nahi bhara. — Principal`;
    return { st: kt ? 'ok' : X && X.loading ? 'hol' : 'no', b: k.c, small: T(k.ct).name || '', line: kt ? '✓ done' : X && X.loading ? '…' : '✕ not filled', tap: `go('att',{c:${jsq(k.c)},m:'kit',date:'${date}'})` }; });
  return xPage('kit', date, { title: 'Classes', tiles, kpis: [['Daily check done', tiles.filter(t => t.st === 'ok').length, tiles.length, 'classes']], remind: p => p.length + ' class teacher(s)', note: 'Diary · ID card · book · dress — filled by the class teacher. Tap a class to open it.' });
}
function xLp(date) {
  const w = weekStart(date), A = S.alloc.filter(a => (+a.n || 0) > 0 && !/\(optional\)/i.test(a.s) && S.teachers[a.t]); XR.lp = {};
  const st = a => { const p = S.lplan[[w, a.t, a.c, a.s].join('|')]; return p ? (Object.prototype.hasOwnProperty.call(p, 'days') ? 'Approved' : p.status) : 'None'; };
  const byT = {}; A.forEach(a => (byT[a.t] = byT[a.t] || []).push(a));
  const tiles = Object.entries(byT).sort((a, b) => T(a[0]).name.localeCompare(T(b[0]).name)).map(([t, L]) => { const none = L.filter(a => st(a) === 'None'), sub = L.filter(a => st(a) === 'Submitted'), ok = L.filter(a => st(a) === 'Approved');
    if (none.length) XR.lp[t] = `Namaste ${T(t).name}, is hafte (${fmtDate(w)}) ka lesson plan app mein nahi bheja: ${none.map(a => 'Class ' + a.c + ' ' + a.s).join(', ')}. Planner → Lesson plan → Auto fill → Send. — Principal`;
    return { st: none.length ? 'no' : sub.length ? 'warn' : 'ok', b: T(t).name, small: L.map(a => a.c + ' ' + a.s.slice(0, 5)).join(', '), line: none.length ? `✕ ${none.length} of ${L.length} not sent` : sub.length ? `${sub.length} to approve` : `✓ ${ok.length}/${L.length} approved`, tap: `xLpT(${jsq(t)},'${w}')` }; });
  const nav = `<div class="datebar"><button class="btn s w" onclick="go('prin',{v:'lp',date:'${addDays(w, -7)}'})">${ic('back')}</button><label class="dpick"><b>Week of ${esc(fmtDate(w))}</b><small>lesson plans</small></label><button class="btn s w" onclick="go('prin',{v:'lp',date:'${addDays(w, 7)}'})">${ic('next')}</button></div>`;
  const cnt = s => A.filter(a => st(a) === s).length;
  return xPage('lp', date, { nav: false, top: nav, title: 'Teachers', tiles, kpis: [['Plans sent', A.length - cnt('None'), A.length, 'class + subject'], ['To approve', cnt('Submitted'), null, 'waiting for you', cnt('Submitted') ? 'go' : 'ok']],
    remind: p => Object.keys(XR.lp).length + ' teacher(s)', note: 'Green = all approved, yellow = waiting for your approval, red = not sent. Tap a teacher to approve.', empty: 'No subjects in the timetable.' });
}
function xLpT(t, w) {
  const L = S.alloc.filter(a => a.t === t && (+a.n || 0) > 0 && !/\(optional\)/i.test(a.s));
  SHEET = () => `<h2>${esc(T(t).name)} — lesson plans</h2><p class="mut" style="margin:0">Week of ${esc(fmtDate(w))}</p>
    ${L.map(a => { const k = [w, t, a.c, a.s].join('|'), p = S.lplan[k];
      return `<div class="pd-ap"><div class="t"><b>Class ${esc(a.c)} · ${esc(a.s)}</b><span>${p ? esc(p.status === 'Submitted' ? 'Waiting for approval' : p.status) + ' · ' + String(p.topics || '').split('\n').filter(Boolean).length + ' topic(s)' : 'Not sent'}</span></div>${p && !Object.prototype.hasOwnProperty.call(p, 'days') ? `<button class="btn s l" onclick="pdPlan(${jsq(k)})">Open</button>` : ''}</div>`; }).join('')}
    ${XR.lp && XR.lp[t] ? `<button class="btn w full" onclick="xRemind(${jsq(t)},${jsq(XR.lp[t])})">${ic('chat')} Remind</button>` : ''}`;
  render();
}
function xSyl(date) {
  const A = S.alloc.filter(a => (+a.n || 0) > 0 && !/\(optional\)/i.test(a.s) && S.teachers[a.t]), up = upcomingTags()[0]; XR.syl = {};
  const tiles = A.map(a => { const y = S.syl[sylKey(a.c, a.s)] || { chapters: [] }, n = y.chapters.length, d = y.chapters.filter(c => c.done).length, ex = up ? y.chapters.filter(c => (c.ex || []).includes(up.id)).length : 1;
    if (!n || !ex) XR.syl[a.t] = (XR.syl[a.t] ? XR.syl[a.t].replace(/ — Principal$/, ', ') : `Namaste ${T(a.t).name}, Planner → Syllabus mein ye baaki hai: `) + `Class ${a.c} ${a.s} (${!n ? 'chapters' : (up ? up.l : 'exam') + ' ke chapter tick'})` + ' — Principal';
    return { st: !n ? 'no' : !ex ? 'warn' : 'ok', b: a.c + ' · ' + a.s, small: T(a.t).name, line: !n ? '✕ chapters not added' : `${d}/${n} done${up ? ' · ' + (ex ? ex + ' for ' + up.l : up.l + ' not ticked') : ''}` }; })
    .sort((x, y) => ({ no: 0, warn: 1, ok: 2 })[x.st] - ({ no: 0, warn: 1, ok: 2 })[y.st]);
  return xPage('syl', date, { nav: false, title: 'Class + subject', tiles, kpis: [['Syllabus set up', tiles.filter(t => t.st !== 'no').length, tiles.length, 'chapters added'], [up ? up.l + ' chapters' : 'Exam chapters', tiles.filter(t => t.st === 'ok').length, tiles.length, 'ticked']],
    remind: p => Object.keys(XR.syl).length + ' teacher(s)', note: 'Red = chapters not added, yellow = chapters added but the next exam is not ticked, green = ready.', empty: 'No subjects in the timetable.' });
}
// ---------------------------------------------------------------- 2. Staff & arrangement
function freeAtX(date, i) { const ar = arrangementBase(date, monthLoad(date)); return Object.keys(S.teachers).filter(x => !absentAt(date, x, i) && !busyAt(date, x, i) && !ar.some(s => s.bell === i && s.sub === x)); }
function staffSave(date, a) { Object.keys(LOADC).forEach(k => { if (k.slice(0, 7) === date.slice(0, 7)) delete LOADC[k]; }); put('staffAtt', date, a, 'Staff Attendance', a._log || 'Staff attendance changed — Teacher App'); delete a._log; }
function staffCopy(date) { return JSON.parse(JSON.stringify(S.staffAtt[date] || { absent: [] })); }
function staffToggle(date, t) {
  if (S.leaves.some(l => l.status === 'Approved' && l.tid === t && l.from <= date && l.to >= date)) return toast('On approved leave — that is already counted.');
  const a = staffCopy(date); a.absent = a.absent || []; const i = a.absent.indexOf(t);
  if (i >= 0) { a.absent.splice(i, 1); if (a.from) delete a.from[t]; } else a.absent.push(t);
  a._log = `${T(t).name} marked ${i >= 0 ? 'PRESENT' : 'ABSENT'} for ${date} — Teacher App`; staffSave(date, a); render();
}
function staffHalf(date, t, v) { const a = staffCopy(date); a.from = a.from || {}; if (+v) a.from[t] = +v; else delete a.from[t]; a._log = `${T(t).name}: ${+v ? 'half day — leaves before ' + BELLS[+v].n + ' bell' : 'full day absent'} on ${date} — Teacher App`; staffSave(date, a); render(); }
function staffSub(date, absent, bell, v) { const a = staffCopy(date); a.subs = a.subs || {}; const k = absent + '|' + bell; if (v) a.subs[k] = v; else delete a.subs[k];
  a._log = `Arrangement ${BELLS[bell].n} bell for ${T(absent).name} on ${date}: ${v === '-' ? 'Principal supervises' : v ? T(v).name + ' (chosen)' : 'back to auto'} — Teacher App`; staffSave(date, a); render(); }
function vStaffArr() {
  const t = todayISO(), date = P.date || t, ab = staffAbsent(date), from = (S.staffAtt[date] && S.staffAtt[date].from) || {}, work = dayIdx(date) >= 0 && !holidayOn(date);
  const lv = x => S.leaves.find(l => l.status === 'Approved' && l.tid === x && l.from <= date && l.to >= date);
  const arr = work ? arrangementBase(date, monthLoad(date)) : [], month = monthLoad(addDays(date, 1));
  const slips = `${S.settings.school || ''} — Arrangement ${fmtDate(date)}\n` + arr.map(s => `${BELLS[s.bell].n} bell ${t12(BELLS[s.bell].s)} • Class ${s.cls} ${s.subj} → ${s.sub ? T(s.sub).name : 'Principal'} (for ${T(s.absent).name})`).join('\n') + '\n— Principal';
  const pend = S.leaves.filter(l => l.status === 'Pending');
  return `<div class="datebar"><button class="btn s w" onclick="go('staff',{date:'${addDays(date, -1)}'})">${ic('back')}</button><label class="dpick"><b>${date === t ? 'Today' : fmtDate(date)}</b><small>${esc(fmtLong(date))}</small><input type="date" value="${date}" onchange="go('staff',{date:this.value})"></label><button class="btn s w" onclick="go('staff',{date:'${addDays(date, 1)}'})">${ic('next')}</button></div>
    ${pend.length ? `<div class="card"><h3>Leave requests <small>${pend.length}</small></h3>${pend.map(l => `<div class="pd-ap"><div class="t"><b>${esc(T(l.tid).name)}</b><span>${esc(fmtDate(l.from))}${l.to !== l.from ? ' – ' + esc(fmtDate(l.to)) : ''} · ${esc(l.reason || '')}</span></div><div class="btns"><button class="btn s go" onclick="pdLeave(${jsq(l.id)},'Approved')">Approve</button><button class="btn s w" onclick="pdLeave(${jsq(l.id)},'Rejected')">Reject</button></div></div>`).join('')}</div>` : ''}
    <div class="card"><h3>Staff present? <small>${Object.keys(S.teachers).length - ab.length} present · ${ab.length} absent</small></h3><p class="mut hint">Everyone is present. Tap only a teacher who is absent; for a half day choose the bell they leave before.</p>
      <div class="xst">${Object.keys(S.teachers).sort((a, b) => T(a).name.localeCompare(T(b).name)).map(x => { const isAb = ab.includes(x), L = lv(x);
        return `<div class="${isAb ? 'ab' : 'ok'}" onclick="staffToggle('${date}',${jsq(x)})"><b>${esc(T(x).name)}</b><span>${L ? 'On leave' : isAb ? (+from[x] ? 'Half day' : 'Absent') : 'Present'}</span>
          ${isAb && !L ? `<select onclick="event.stopPropagation()" onchange="staffHalf('${date}',${jsq(x)},this.value)"><option value="0">Full day</option>${BELLS.map((b, i) => i ? `<option value="${i}" ${+from[x] === i ? 'selected' : ''}>Leaves before ${b.n}</option>` : '').join('')}</select>` : ''}</div>`; }).join('')}</div></div>
    <div class="card"><h3>Arrangement <small>${arr.length} bell(s)</small></h3>
      ${!work ? '<p class="mut">No school on this day.</p>' : arr.length ? arr.map(s => { const opts = Object.keys(S.teachers).filter(x => x !== s.absent && !absentAt(date, x, s.bell) && !busyAt(date, x, s.bell) && (!arr.some(o => o !== s && o.bell === s.bell && o.sub === x)));
        return `<div class="xa ${s.sub ? '' : 'no'}"><div class="t"><b>${esc(BELLS[s.bell].n)} bell · Class ${esc(s.cls)} · ${esc(s.subj)}</b><span>for ${esc(T(s.absent).name)} → ${s.sub ? '<b>' + esc(T(s.sub).name) + '</b>' + (s.manual ? ' (chosen)' : ' (auto)') : '<b>Principal supervises</b>'}</span>
          <select onchange="staffSub('${date}',${jsq(s.absent)},${s.bell},this.value)"><option value="">Auto (best free teacher)</option>${opts.map(x => `<option value="${esc(x)}" ${s.manual && s.sub === x ? 'selected' : ''}>${esc(T(x).name)} · ${month[x] || 0} this month</option>`).join('')}<option value="-" ${s.manual && !s.sub ? 'selected' : ''}>Nobody — Principal</option></select></div>
          ${s.sub && T(s.sub).phone ? `<a class="btn wa s" target="_blank" rel="noopener" href="${wa(T(s.sub).phone, `Arrangement: ${BELLS[s.bell].n} bell (${t12(BELLS[s.bell].s)}), Class ${s.cls} ${s.subj}, in place of ${T(s.absent).name}. — Principal`)}">${ic('wa')}</a>` : ''}</div>`; }).join('')
        + `<a class="btn wa full" style="margin-top:8px" target="_blank" rel="noopener" href="${wa('', slips)}">${ic('wa')} Send all slips to the staff group</a><p class="mut" style="margin:6px 0 0">Substitutes also see the bell in their own app (Today and Classroom).</p>`
        : '<p class="mut" style="margin:0">No arrangement needed — nobody is absent' + (ab.length ? ' in a teaching bell' : '') + '.</p>'}</div>
    ${work ? `<div class="card"><h3>Who is free — ${esc(DAYS[dayIdx(date)])}</h3>${BELLS.map((b, i) => { const f = freeAtX(date, i); return `<div class="xf"><b>${esc(b.n)}</b><span>${f.length ? f.map(x => `<em>${esc(T(x).name)}</em>`).join('') : '<em class="no">Nobody free</em>'}</span></div>`; }).join('')}</div>` : ''}
    ${Object.keys(month).length ? `<div class="card"><h3>Arrangement duty this month</h3><div class="chips">${Object.entries(month).sort((a, b) => b[1] - a[1]).map(([k, n]) => `<span class="pill">${esc(T(k).name)}: ${n}</span>`).join('')}</div></div>` : ''}`;
}

// ---------------------------------------------------------------- 3. Timetable
function ttGrid() { const g = JSON.parse(JSON.stringify(S.grid || {})); className().forEach(c => { if (!g[c]) g[c] = DAYS.map(() => BELLS.map(() => -1)); }); return g; }
function vTT() {
  const C = className(), c = C.includes(P.c) ? P.c : C[0], g = S.grid && S.grid[c] ? S.grid[c] : null;
  const clash = (d, b, i) => { if (i < 0) return null; const a = S.alloc[i]; if (!a) return null; for (const x of C) { if (x === c) continue; const j = S.grid && S.grid[x] ? S.grid[x][d][b] : -1; if (j >= 0 && S.alloc[j] && S.alloc[j].t === a.t) return x; } return null; };
  const per = {}; if (g) g.forEach(r => r.forEach(i => { if (i >= 0) per[i] = (per[i] || 0) + 1; }));
  const mine = S.alloc.map((a, i) => [a, i]).filter(([a]) => a.c === c);
  return `<div class="chips tt-c">${C.map(x => `<button class="chip ${x === c ? 'on' : ''}" onclick="go('tt',{c:${jsq(x)}})">${esc(x)}</button>`).join('')}</div>
    <div class="card"><h3>Class ${esc(c)} timetable <small>tap a box to change</small></h3><div class="ttw"><table class="tt"><tr><th></th>${BELLS.map((b, i) => `<th>${i + 1}<small>${t12(b.s).replace(' ', '')}</small></th>`).join('')}</tr>
      ${DAYS.map((d, di) => `<tr><th>${d.slice(0, 3)}</th>${BELLS.map((_, bi) => { const i = g ? g[di][bi] : -1, a = i >= 0 ? S.alloc[i] : null, cl = clash(di, bi, i);
        return `<td class="${a ? '' : 'e'}${cl ? ' cl' : ''}" onclick="ttPick(${jsq(c)},${di},${bi})">${a ? `<b>${esc(String(a.s).slice(0, 9))}</b><small>${esc(String(T(a.t).name).split(' ')[0])}</small>` : '+'}${cl ? `<em>also ${esc(cl)}</em>` : ''}</td>`; }).join('')}</tr>`).join('')}</table></div>
      <p class="mut" style="margin:6px 0 0">Red box = the same teacher is also in another class at that time.</p></div>
    <div class="card"><h3>Periods per week <small>planned vs in the timetable</small></h3>${mine.map(([a, i]) => `<div class="xs"><div class="t"><b>${esc(a.s)}</b><span>${esc(T(a.t).name)}</span></div><span class="pill ${(per[i] || 0) === (+a.n || 0) ? 'ok' : 'warn'}">${per[i] || 0} / ${+a.n || 0}</span></div>`).join('') || '<div class="empty">No subjects for this class. Add them in the web ERP → Subjects &amp; Teachers.</div>'}</div>`;
}
function ttPick(c, d, b) {
  const C = className(), cur = S.grid && S.grid[c] ? S.grid[c][d][b] : -1;
  const busy = i => { const a = S.alloc[i]; for (const x of C) { if (x === c) continue; const j = S.grid && S.grid[x] ? S.grid[x][d][b] : -1; if (j >= 0 && S.alloc[j] && S.alloc[j].t === a.t) return x; } return null; };
  const opts = S.alloc.map((a, i) => [a, i]).filter(([a]) => a.c === c);
  SHEET = () => `<h2>Class ${esc(c)} · ${esc(DAYS[d])} · ${esc(BELLS[b].n)} bell</h2><p class="mut" style="margin:0">${t12(BELLS[b].s)} – ${t12(BELLS[b].e)}</p>
    <div class="tt-o">${opts.map(([a, i]) => { const bz = busy(i); return `<button class="${i === cur ? 'on' : ''}${bz ? ' cl' : ''}" onclick="ttSet(${jsq(c)},${d},${b},${i})"><b>${esc(a.s)}</b><span>${esc(T(a.t).name)}</span>${bz ? `<em>busy in ${esc(bz)}</em>` : ''}</button>`; }).join('')}
      <button class="${cur < 0 ? 'on' : ''}" onclick="ttSet(${jsq(c)},${d},${b},-1)"><b>Free / no class</b><span>leave empty</span></button></div>`;
  render();
}
function ttSet(c, d, b, i) {
  const g = ttGrid(), a = i >= 0 ? S.alloc[i] : null;
  if (a) { const bz = className().find(x => x !== c && g[x][d][b] >= 0 && S.alloc[g[x][d][b]] && S.alloc[g[x][d][b]].t === a.t); if (bz && !confirm(T(a.t).name + ' is already in Class ' + bz + ' at this time. Put here anyway?')) return; }
  g[c][d][b] = i; put('grid', 'main', g, 'Timetable', `Class ${c} ${DAYS[d]} ${BELLS[b].n} bell → ${a ? a.s + ' (' + T(a.t).name + ')' : 'free'} — Teacher App`);
  Object.keys(LOADC).forEach(k => delete LOADC[k]); SHEET = null; toast('Timetable saved.'); render();
}

// ---------------------------------------------------------------- wire the two screens through the Principal 'prin' slot
// (teacher.js render() has a fixed list of views): go('staff') / go('tt') = go('prin', {screen}).
const _vPrinOps = vPrin;
vPrin = function () { if (P.screen === 'staff') return vStaffArr(); if (P.screen === 'tt') return vTT(); return _vPrinOps(); };
const _goOps = go;
go = function (tab, p) { if (tab === 'staff' || tab === 'tt') return _goOps('prin', Object.assign({}, p || {}, { screen: tab })); return _goOps(tab, p); };

const HELP_WHY = {
  home: 'Kyun: teacher ko ek nazar mein pata chale aaj kya karna baaki hai — kuch chhoote nahi, Principal ko yaad na dilana pade.',
  att: 'Kyun: roz ki haziri se parents ko turant khabar, % aur 75% se kam wale bachche apne aap; register haath se nahi likhna padta.',
  diary: 'Kyun: har bell ka record — kya padhaya, kya homework diya. Parents ko class ka homework WhatsApp par ek tap mein; Principal ko pata ki class hui.',
  plan: 'Kyun: hafte ka plan pehle se — syllabus samay par poora; chapter ka text daalne se homework, plan aur paper apne aap banne lagte hain.',
  res: 'Kyun: number daalte hi result, grade, rank aur report card — alag se calculation nahi.',
  conn: 'Kyun: parents se baat, fee yaad dilana aur staff se baat — sab record ke saath, ek jagah.',
  pdash: 'Kyun: Principal ko roz 1 minute mein poore school ka haal — kya approve karna hai aur kahan dhyan dena hai.',
  prin: 'Kyun: har kaam (haziri, diary, homework check, daily check, lesson plan, syllabus) ka school-wide hisaab; jiska baaki ho use ek tap mein yaad dilao.',
  board: 'Kyun: classes aur bachchon mein achchha muqabla — sab ko dikhe kaun aage hai, isse mehnat badhti hai.'
};
(function () {
  Object.entries(HELP_WHY).forEach(([k, why]) => { if (HELP[k]) HELP[k][1].unshift(['Ye kyun hai?', why.replace(/^Kyun: /, '')]); });
  if (HELP.prin) HELP.prin[1].push(['Upar ke tab', 'Teachers · Attendance · Diary · HW check · Daily check · Lesson plan · Syllabus — har ek ka poore school ka grid. Laal = baaki. "Remind" se us teacher ko Staff chat mein message.'], ['Arrangement aur timetable', 'Home → Arrangement: absent teacher par tap, substitute apne aap; badalna ho to list se chuno. Home → Timetable: class chuno, box par tap karke subject-teacher badlo.']);
})();
const _openHelpOps = openHelp;
openHelp = function () {
  if (TAB === 'prin' && P.screen === 'staff') { SHEET = () => `<h2 data-notr>❓ Staff &amp; arrangement</h2><div class="help-i" data-notr><b>Ye kyun hai?</b><p>Koi teacher absent ho to uski class khaali na rahe — app sabse sahi free teacher apne aap chunta hai aur mahine bhar duty barabar baantta hai.</p></div><div class="help-i" data-notr><b>Kaise</b><p>Absent teacher ke naam par tap. Half day ho to "Leaves before" chuno. Neeche har bell ka substitute aa jaata hai — badalna ho to list se chuno. WhatsApp se slip bhejo. Substitute ko apne app mein bhi dikhta hai.</p></div>`; return render(); }
  if (TAB === 'prin' && P.screen === 'tt') { SHEET = () => `<h2 data-notr>❓ Timetable</h2><div class="help-i" data-notr><b>Ye kyun hai?</b><p>Timetable se hi har teacher ki bells, diary aur arrangement chalta hai — isliye yahan badalte hi sab jagah badal jaata hai.</p></div><div class="help-i" data-notr><b>Kaise</b><p>Upar class chuno. Kisi box par tap → subject-teacher chuno ya "Free". Laal = wahi teacher usi samay doosri class mein bhi hai. Neeche dekho har subject ke periods poore hue ya nahi.</p></div>`; return render(); }
  return _openHelpOps();
};

// suggestion chips: leave reason, plan send-back feedback
const LEAVE_SUG = ['Family function', 'Not well', 'Doctor visit', 'Personal work', 'Going out of station', 'Exam / training'];
const FB_SUG = ['Add more detail for each period', 'Add the homework for each topic', 'Follow the syllabus order', 'Add an activity / teaching aid', 'Too many topics for one week'];
document.addEventListener('focusin', e => {
  const el = e.target; if (!el || !el.id) return;
  const list = el.id === 'lvR' ? LEAVE_SUG : el.id === 'pdFb' ? FB_SUG : null; if (!list || el.nextElementSibling && el.nextElementSibling.classList.contains('sug-c')) return;
  el.insertAdjacentHTML('afterend', `<div class="chips sug-c">${list.map(x => `<button type="button" class="chip">${esc(x)}</button>`).join('')}</div>`);
  el.nextElementSibling.querySelectorAll('button').forEach(b => { b.onmousedown = ev => ev.preventDefault(); b.onclick = () => { el.value = b.textContent; el.dispatchEvent(new Event('input', { bubbles: true })); }; });
});
function xSign(t, date) { put('signed', date + '|' + t, { at: Date.now(), by: myName() }, 'Diary', `Signed the diary of ${T(t).name} for ${date} — Teacher App`); toast('Diary signed.'); render(); }

// Teachers — one tile per teacher (owner, 5 Nov 2026: the long list was hard to read). Today's work only; weekly / one-time
// work is inside the teacher's sheet. Tap = list + Message (Staff chat) / WhatsApp.
function xTch(date) {
  needXC(date); const D = prinData(date); XR.tch = {};
  const all = Object.keys(S.teachers).filter(x => x !== me()).sort((a, b) => T(a).name.localeCompare(T(b).name));
  const tiles = all.map(t => { const o = D.per[t] || { pend: [], done: 0 }, on = D.away.has(t);
    if (o.pend.length) XR.tch[t] = prinMsg(o, date);
    return { st: on ? 'hol' : o.pend.length ? 'no' : 'ok', b: T(t).name, small: on ? 'On leave / absent' : o.pend.length + o.done ? (o.done + ' of ' + (o.pend.length + o.done) + ' done') : 'nothing due yet',
      line: on ? '—' : o.pend.length ? `✕ ${o.pend.length} pending` : o.done ? '✓ all done' : '✓', tap: `xTchT(${jsq(t)},'${date}')` }; })
    .sort((a, b) => ({ no: 0, ok: 1, hol: 2 })[a.st] - ({ no: 0, ok: 1, hol: 2 })[b.st]);
  if (!D.work) return xDatebar('tch', date) + `<div class="ban hol">${dayIdx(date) < 0 ? 'Sunday' : 'Holiday — ' + esc(holTitle(holidayOn(date)))}. Nothing to check.</div>`;
  return xPage('tch', date, { title: 'Teachers', tiles, kpis: [['Teachers — all done', tiles.filter(t => t.st === 'ok').length, tiles.filter(t => t.st !== 'hol').length, D.away.size ? D.away.size + ' on leave / absent' : 'nobody on leave'], ['Pending work', tiles.filter(t => t.st === 'no').reduce((n, t) => n + (+(t.line.match(/\d+/) || [0])[0]), 0), null, 'things not done today', 'bad']],
    remind: p => p.length + ' teacher(s)', note: 'Today\'s work of each teacher: attendance, daily check, diary of every bell that is over, homework check. Tap a teacher to see the list.' });
}
function xTchT(t, date) {
  const D = prinData(date), o = D.per[t] || { pend: [], done: 0, items: [] }, su = o.setup || [], tt = T(t);
  SHEET = () => `<h2>${esc(tt.name)}</h2><p class="mut" style="margin:0">${esc(fmtLong(date))} · ${o.pend.length ? o.pend.length + ' pending · ' : ''}${o.done} done</p>
    ${o.pend.length ? `<ul class="xc-l">${o.pend.map(p => `<li class="${p.kind}">✕ ${esc(p.txt)}</li>`).join('')}</ul>` : '<div class="ban ok">✓ Today\'s work is done so far.</div>'}
    ${su.length ? `<details class="xc-su"><summary>Weekly / one-time work not done: ${su.length}</summary><ul class="xc-l">${su.map(p => `<li class="${p.kind}">${esc(p.txt)}</li>`).join('')}</ul></details>` : ''}
    ${o.pend.length || su.length ? `<div class="btns"><button class="btn go" onclick="SHEET=null;prinMsgOpen(${jsq(t)},'${date}')">${ic('chat')} Message</button>${tt.phone ? `<a class="btn wa" target="_blank" rel="noopener" href="${wa(tt.phone, prinMsg(o, date))}">${ic('wa')} WhatsApp</a>` : ''}</div>` : ''}`;
  render();
}
