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
const XV = [['tch', 'Teachers'], ['att', 'Attendance'], ['diary', 'Diary'], ['hw', 'HW check'], ['kit', 'Daily check'], ['lp', 'Lesson plan'], ['syl', 'Syllabus']];
const _vPrinOld = vPrin;
vPrin = function () {
  const v = XV.some(x => x[0] === P.v) ? P.v : 'tch', t = todayISO(), date = P.date && P.date <= t ? P.date : t;
  const tabs = `<div class="xv">${XV.map(([k, l]) => `<button class="${k === v ? 'on' : ''}" onclick="go('prin',{v:'${k}',date:'${date}'})">${l}</button>`).join('')}</div>`;
  if (v === 'tch') return tabs + _vPrinOld();
  if (date < (loadedFrom || '') && !DAYS_LOADED[date]) { ensureDate(date).then(render); return tabs + '<div class="load"><i></i></div>'; }
  const body = { att: xAtt, diary: xDiary, hw: xHw, kit: xKit, lp: xLp, syl: xSyl }[v](date);
  return tabs + body;
};

// ---------------------------------------------------------------- 1. per-feature grids
function xAtt(date) {
  const cls = (S.classes || []).filter(k => studentsOf(k.c).length);
  const done = cls.filter(k => attOf(date, k.c)).length;
  return xDatebar('att', date) + `<div class="card"><h3>Attendance <small>${done}/${cls.length} classes</small></h3><div class="xg">${cls.map(k => {
    const a = attOf(date, k.c), n = studentsOf(k.c).length, h = holidayOn(date) || clsHolOn(date, k.c), off = !isWorkDay(date, k.c);
    return `<button type="button" class="${a ? 'ok' : h || off ? 'hol' : 'no'}" onclick="go('att',{c:${jsq(k.c)},date:'${date}'})"><b>${esc(k.c)}</b><span>${a ? (n - (a.absent || []).length) + '/' + n : h ? 'Holiday' : off ? '—' : 'Not marked'}</span><small>${esc(T(k.ct).name || '')}</small></button>`; }).join('')}</div>
    <p class="mut" style="margin:8px 0 0">Green = marked, red = not marked. Tap a class to open its attendance.</p></div>`;
}
function xDiary(date) {
  const ts = Object.keys(S.teachers).map(t => ({ t, b: bellsOf(t, date) })).filter(x => x.b.length).sort((a, b) => T(a.t).name.localeCompare(T(b.t).name));
  let all = 0, done = 0;
  const rows = ts.map(({ t, b }) => { const sg = (S.signed[date] || {})[t];
    const cells = BELLS.map((_, i) => { const x = b.find(y => y.bell === i); if (!x) return '<i class="e"></i>'; const e = ((S.diary[date] || {})[t] || {})[i], over = bellOver(date, i);
      if (over || e) { all++; if (e) done++; }
      return `<button type="button" class="${e ? 'ok' : over ? 'no' : 'up'}${x.arr ? ' arr' : ''}" onclick="xDiaryOpen(${jsq(t)},'${date}',${i})" aria-label="${esc(BELLS[i].n)} bell">${i + 1}</button>`; }).join('');
    const miss = b.filter(x => bellOver(date, x.bell) && !((S.diary[date] || {})[t] || {})[x.bell]).length;
    return `<div class="xd"><div class="n"><b>${esc(T(t).name)}</b><small>${miss ? miss + ' not filled' : 'all filled so far'}${sg ? ' · ✍ signed' : ''}</small></div><div class="cells">${cells}</div>
      ${miss ? xRemindBtn(t, `Namaste ${T(t).name}, ${date === todayISO() ? 'aaj' : fmtLong(date) + ' ko'} ki ${miss} bell ki diary app mein baaki hai. Kripya bhar dein. — Principal`) : sg ? '' : `<button class="btn s l" onclick="xSign(${jsq(t)},'${date}')">✍ Sign</button>`}</div>`; }).join('');
  return xDatebar('diary', date) + `<div class="card"><h3>Diary <small>${done}/${all} bells over</small></h3>
    <div class="xd-h"><span></span><div class="cells">${BELLS.map((b, i) => `<i>${i + 1}</i>`).join('')}</div></div>${rows || '<div class="empty">No bells on this day.</div>'}
    <p class="mut" style="margin:8px 0 0"><span class="lvk" style="background:var(--ok)"></span>filled <span class="lvk" style="background:var(--bad)"></span>bell over, not filled <span class="lvk" style="background:var(--line)"></span>coming · dotted = arrangement. Tap a box to read it.</p></div>`;
}
function xDiaryOpen(t, date, bell) {
  const e = ((S.diary[date] || {})[t] || {})[bell], x = bellsOf(t, date).find(y => y.bell === bell) || {};
  SHEET = () => `<h2>${esc(T(t).name)} · ${esc(BELLS[bell].n)} bell</h2><p class="mut" style="margin:0">${esc(fmtLong(date))} · Class ${esc(x.cls || (e || {}).cls || '')} ${esc(x.subj || (e || {}).subj || '')}${x.arr ? ' · arrangement' : ''}</p>
    ${e ? `<div class="help-i"><b>Topic</b><p>${esc(e.topic)}</p></div>${e.classwork ? `<div class="help-i"><b>Class work</b><p>${esc(e.classwork)}</p></div>` : ''}${e.hw ? `<div class="help-i"><b>Homework${e.hwMinutes ? ' · ' + e.hwMinutes + ' min' : ''}</b><p>${esc(e.hw)}${e.hwDate ? ' (for ' + esc(fmtDate(e.hwDate)) + ')' : ''}</p></div>` : '<p class="mut">No homework.</p>'}
      <p class="mut">Saved ${esc(fmtTime(e.at || 0))}</p>`
      : `<div class="ban warn">${bellOver(date, bell) ? 'Diary not filled for this bell.' : 'This bell is not over yet.'}</div>${bellOver(date, bell) ? `<button class="btn go full" onclick="xRemind(${jsq(t)},${jsq('Namaste ' + T(t).name + ', ' + BELLS[bell].n + ' bell (Class ' + (x.cls || '') + ' ' + (x.subj || '') + ') ki diary app mein baaki hai. — Principal')});closeSheet()">${ic('chat')} Remind in Staff chat</button>` : ''}`}`;
  render();
}
function xSign(t, date) { put('signed', date + '|' + t, { at: Date.now(), by: myName() }, 'Diary', `Signed the diary of ${T(t).name} for ${date} — Teacher App`); toast('Diary signed.'); render(); }
function xHw(date) {
  const X = needXC(date), rows = [];
  Object.keys(S.teachers).forEach(t => Object.entries(hwDueFor(t, date)).forEach(([k, hw]) => { const [c, s] = k.split('|');
    if (!isWorkDay(date, c)) return; const chk = X && X.chk ? X.chk.find(x => x.cls === c && x.subj === s) : null; rows.push({ t, c, s, hw, chk }); }));
  rows.sort((a, b) => (!!a.chk - !!b.chk) || a.c.localeCompare(b.c));
  const done = rows.filter(r => r.chk).length;
  return xDatebar('hw', date) + `<div class="card"><h3>Homework check <small>${X && X.loading ? 'loading…' : done + '/' + rows.length + ' checked'}</small></h3>
    ${rows.map(r => `<div class="xh ${r.chk ? 'ok' : 'no'}"><i>${r.chk ? '✓' : '✕'}</i><div class="t"><b>Class ${esc(r.c)} · ${esc(r.s)}</b><span>${esc(T(r.t).name)} · HW: ${esc(r.hw.join(' / '))}</span>${r.chk ? `<small>checked by ${esc(r.chk.by || '')}</small>` : ''}</div>
      ${r.chk ? '' : xRemindBtn(r.t, `Namaste ${T(r.t).name}, Class ${r.c} ${r.s} ka homework (${r.hw.join(' / ')}) ${date === todayISO() ? 'aaj' : fmtLong(date) + ' ko'} check hona tha — app mein Classroom → Check work bhar dein. — Principal`)}</div>`).join('') || '<div class="empty">No homework was due on this day.</div>'}
    <p class="mut" style="margin:8px 0 0">Homework that was due on this day (from the diaries) and whether the subject teacher saved Check work.</p></div>`;
}
function xKit(date) {
  const X = needXC(date), cls = (S.classes || []).filter(k => studentsOf(k.c).length && isWorkDay(date, k.c));
  const done = X && X.kit ? cls.filter(k => X.kit[k.c]).length : 0;
  return xDatebar('kit', date) + `<div class="card"><h3>Daily check <small>${X && X.loading ? 'loading…' : done + '/' + cls.length + ' classes'}</small></h3><div class="xg">${cls.map(k => { const kt = X && X.kit && X.kit[k.c];
    return `<button type="button" class="${kt ? 'ok' : 'no'}" onclick="go('att',{c:${jsq(k.c)},m:'kit',date:'${date}'})"><b>${esc(k.c)}</b><span>${kt ? '✓ done' : 'Not filled'}</span><small>${esc(T(k.ct).name || '')}</small></button>`; }).join('')}</div>
    <p class="mut" style="margin:8px 0 0">Diary · ID card · book · dress — filled by the class teacher.</p></div>`;
}
function xLp(date) {
  const w = weekStart(date), A = S.alloc.filter(a => (+a.n || 0) > 0 && !/\(optional\)/i.test(a.s) && S.teachers[a.t]);
  const st = a => { const p = S.lplan[[w, a.t, a.c, a.s].join('|')]; return p ? (Object.prototype.hasOwnProperty.call(p, 'days') ? 'Daily' : p.status) : 'None'; };
  const cnt = s => A.filter(a => st(a) === s).length, byT = {};
  A.forEach(a => (byT[a.t] = byT[a.t] || []).push(a));
  return `<div class="datebar"><button class="btn s w" onclick="go('prin',{v:'lp',date:'${addDays(w, -7)}'})">${ic('back')}</button><label class="dpick"><b>Week of ${esc(fmtDate(w))}</b><small>lesson plans</small></label><button class="btn s w" onclick="go('prin',{v:'lp',date:'${addDays(w, 7)}'})">${ic('next')}</button></div>
    <div class="cal-sum"><span class="pill ok">${cnt('Approved') + cnt('Daily')} approved</span><span class="pill warn">${cnt('Submitted')} to check</span><span class="pill bad">${cnt('Revise')} sent back</span><span class="pill">${cnt('None')} not sent</span></div>
    ${Object.entries(byT).sort((a, b) => T(a[0]).name.localeCompare(T(b[0]).name)).map(([t, L]) => { const none = L.filter(a => st(a) === 'None');
      return `<div class="card xl"><div class="row"><b>${esc(T(t).name)}</b>${none.length ? xRemindBtn(t, `Namaste ${T(t).name}, is hafte (${fmtDate(w)}) ka lesson plan app mein nahi bheja: ${none.map(a => 'Class ' + a.c + ' ' + a.s).join(', ')}. Planner → Lesson plan → Auto fill → Send. — Principal`) : ''}</div>
        <div class="xl-t">${L.map(a => { const s = st(a), k = [w, a.t, a.c, a.s].join('|');
          return `<button type="button" class="${s === 'Approved' || s === 'Daily' ? 'ok' : s === 'Submitted' ? 'warn' : s === 'Revise' ? 'bad' : 'no'}" ${S.lplan[k] && s !== 'Daily' ? `onclick="pdPlan(${jsq(k)})"` : ''}><b>${esc(a.c)}</b><span>${esc(a.s)}</span><small>${s === 'None' ? 'not sent' : s === 'Submitted' ? 'check' : s.toLowerCase()}</small></button>`; }).join('')}</div></div>`; }).join('') || '<div class="card empty">No subjects in the timetable.</div>'}`;
}
function xSyl() {
  const A = S.alloc.filter(a => (+a.n || 0) > 0 && !/\(optional\)/i.test(a.s) && S.teachers[a.t]), up = upcomingTags()[0];
  const rows = A.map(a => { const y = S.syl[sylKey(a.c, a.s)] || { chapters: [] }, n = y.chapters.length, d = y.chapters.filter(c => c.done).length, ex = up ? y.chapters.filter(c => (c.ex || []).includes(up.id)).length : 0;
    return { a, n, d, ex, p: n ? Math.round(d / n * 100) : null }; }).sort((x, y) => (x.p ?? -1) - (y.p ?? -1));
  const none = rows.filter(r => !r.n);
  return `<div class="card"><h3>Syllabus <small>${rows.length - none.length}/${rows.length} set up${up ? ' · next exam ' + esc(up.l) : ''}</small></h3>
    ${rows.map(r => `<div class="xs"><div class="t"><b>Class ${esc(r.a.c)} · ${esc(r.a.s)}</b><span>${esc(T(r.a.t).name)} · ${r.n ? r.d + '/' + r.n + ' chapters done' + (up ? ' · ' + r.ex + ' marked for ' + esc(up.l) : '') : 'chapters not added'}</span>
      <i class="bar"><em style="--w:${r.p || 0}%"></em></i></div>${!r.n || (up && !r.ex) ? xRemindBtn(r.a.t, `Namaste ${T(r.a.t).name}, Class ${r.a.c} ${r.a.s}: ${!r.n ? 'syllabus ke chapter app mein nahi jude (Planner → Syllabus → kitaab ka naam + kul chapter → Make rows)' : (up ? up.l : 'exam') + ' ke chapter tick nahi hue (Planner → Syllabus)'}. — Principal`) : `<span class="pill ${r.p >= 50 ? 'ok' : 'warn'}">${r.p}%</span>`}</div>`).join('') || '<div class="empty">No subjects in the timetable.</div>'}</div>`;
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
