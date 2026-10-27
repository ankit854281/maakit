'use strict';
// ============================================================================
//  Old-ERP layouts for screens of app.js (27 Oct 2026, owner: "bilkul
//  difference na feel ho ke mai purane software pe work kar raha hu ya naye
//  par"). Loaded after app.js and classic.js. Only when the old-ERP look is on
//  (classicOn()) does a view here replace the app.js view; the data, the
//  records and the rules are the app's own (S.studAtt etc.), nothing new is
//  stored except fields the old form had.
//
//  Attendance (old ERP: student_attendance.php, report_absentees.php,
//  report_stuattdet.php, report_stuattdetn.php): record studAtt 'date|class'
//  {absent:[ids], by, at} as before; the old form's L (leave) is also kept in
//  st:{id:'L'} and counted absent, so every other screen stays right.
// ============================================================================
const OL_CLASSES = ['PG', 'LKG', 'UKG', '1st', '2nd', '3rd', '4th', '5th', '6th', '7th', '8th', '9th', '10th'];
const olSes = () => String((S.settings || {}).session || '').replace(/^(20\d\d)-(\d\d)$/, '$1-20$2');
const olDmy = d => d ? d.slice(8, 10) + '/' + d.slice(5, 7) + '/' + d.slice(0, 4) : '';
function olPage(title, inner) { return `<div class="olk"><h3>${esc(title)}</h3>${inner}</div>`; }
function olSel(id, opts, v, on) { return `<select id="${id}"${on ? ` onchange="${on}"` : ''}>${opts.map(o => `<option${String(o) === String(v) ? ' selected' : ''}>${esc(o)}</option>`).join('')}</select>`; }
function olRow(cells) { return `<tr>${cells.map(([l, c, req]) => `<td class="l${req ? ' req' : ''}">${esc(l)}${req ? '*' : ''}</td><td class="c">${c}</td>`).join('')}</tr>`; }
const olStudents = (c, sec) => studentsOf(c).filter(s => !sec || sec === 'All' || (s.section || '') === sec);
let OLA = { cls: '', sec: 'All', date: '', def: 'P', go: false, st: {} };
function vAttOld() {
  const mode = P.am || 'mark';
  if (mode !== 'mark') return vAttReport(mode);
  const allowed = U.role === 'principal' ? OL_CLASSES : CLASSES.filter(k => k.ct === U.tid).map(k => k.c);
  if (!allowed.length) return olPage('Student Attendance', '<p>Only class teachers mark attendance. You are not a class teacher.</p>');
  const date = OLA.date || P.date || todayISO(); if (!OLA.cls || !allowed.includes(OLA.cls)) OLA.cls = allowed[0];
  const form = `<table class="f">${olRow([['School', `<input type="text" value="${esc((S.settings || {}).school || '')}" readonly>`], ['Class', olSel('olaC', allowed, OLA.cls, "OLA.cls=this.value;OLA.go=false;render()"), 1]])}
    ${olRow([['Section', olSel('olaS', ['All', 'A', 'B', 'DIAMOND'], OLA.sec, "OLA.sec=this.value;OLA.go=false;render()")], ['Stream', olSel('olaG', ['NA'], 'NA')]])}
    ${olRow([['Date', `<input type="date" value="${date}" onchange="OLA.date=this.value;OLA.go=false;ensureDate(this.value).then(render)">`, 1], ['Session', `<input type="text" value="${esc(olSes())}" readonly>`]])}
    ${olRow([['Default Value', olSel('olaD', ['P', 'A'], OLA.def, "OLA.def=this.value")], ['', '']])}</table>
    <div class="b"><button class="sub" onclick="olaGo()">Submit</button></div>`;
  if (!OLA.go) return olPage('Student Attendance', form);
  const saved = attOf(date, OLA.cls), list = olStudents(OLA.cls, OLA.sec);
  const st = OLA.st;
  const rows = list.map((s, i) => { const v = st[s.id]; return `<tr><td>${i + 1}</td><td>${esc(s.adm)}</td><td class="t">${esc(s.name)}</td><td class="t">${esc(s.father || '')}</td><td class="att">${['P', 'A', 'L'].map(x => `<label><input type="radio" name="a_${esc(s.id)}" ${v === x ? 'checked' : ''} onchange="OLA.st['${esc(s.id)}']='${x}'"> ${x}</label>`).join('')}</td></tr>`; }).join('');
  const n = k => list.filter(s => st[s.id] === k).length;
  return olPage('Student Attendance', form + `<div class="info">Class ${esc(OLA.cls)}${OLA.sec !== 'All' ? '-' + esc(OLA.sec) : ''} · ${olDmy(date)} · ${saved ? 'Saved by ' + esc(saved.by) : 'Not saved yet'} · P ${n('P')} · A ${n('A')} · L ${n('L')}</div>
    <div class="w"><table class="lst"><tr><th>Sno</th><th>Scholar No</th><th>Student Name</th><th>Father's Name</th><th>Attendance</th></tr>${rows || '<tr><td colspan="5">No students in this class.</td></tr>'}</table></div>
    ${list.length ? `<div class="b"><button class="sub" onclick="olaSave('${date}')">Save</button></div>` : ''}`);
}
function olaGo() {
  const date = OLA.date || P.date || todayISO(), saved = attOf(date, OLA.cls), st = {};
  olStudents(OLA.cls, OLA.sec).forEach(s => { st[s.id] = saved ? ((saved.st || {})[s.id] || (saved.absent.includes(s.id) ? 'A' : 'P')) : OLA.def; });
  OLA.st = st; OLA.go = true; render();
}
function olaSave(date) {
  const c = OLA.cls, all = studentsOf(c), shown = new Set(olStudents(c, OLA.sec).map(s => s.id)), prev = attOf(date, c) || { absent: [], st: {} };
  // Students of another section keep what was saved for them; the shown ones take the form's value.
  const absent = all.filter(s => shown.has(s.id) ? OLA.st[s.id] !== 'P' : prev.absent.includes(s.id)).map(s => s.id);
  const stOut = {}; all.forEach(s => { const v = shown.has(s.id) ? OLA.st[s.id] : (prev.st || {})[s.id]; if (v === 'L') stOut[s.id] = 'L'; });
  S.studAtt[date] = S.studAtt[date] || {};
  S.studAtt[date][c] = { absent, ...(Object.keys(stOut).length ? { st: stOut } : {}), by: userName(), at: Date.now() };
  const names = absent.map(id => (studentById(id) || {}).name).filter(Boolean);
  log('Student Attendance', `Class ${c} (${date}): ${all.length - absent.length}/${all.length} present` + (names.length ? '. Absent: ' + names.join(', ') : ''));
  toast('Attendance saved'); render();
}
// ---------- attendance reports ----------
let OLR = { cls: '', sec: 'All', from: '', to: '', date: '', status: 'All', go: false };
const OLR_T = { mark: 'Student Attendance', abs: 'Absentees Report', status: 'Attendance Status', abs2: 'Absentees Report II', register: 'Attendance Register', summary: 'Attendance Summary' };
function olDays(from, to) { const out = []; for (let d = new Date(from + 'T00:00:00'); d <= new Date(to + 'T00:00:00') && out.length < 400; d.setDate(d.getDate() + 1)) out.push(d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate())); return out; }
function olMark(date, c, sid) { const a = attOf(date, c); if (!a) return ''; return (a.st || {})[sid] || (a.absent.includes(sid) ? 'A' : 'P'); }
function vAttReport(mode) {
  const t = todayISO(); OLR.date = OLR.date || t; OLR.to = OLR.to || t; OLR.from = OLR.from || t.slice(0, 8) + '01';
  const one = mode === 'abs';
  const form = `<table class="f">${olRow([['School', `<input type="text" value="${esc((S.settings || {}).school || '')}" readonly>`], ['Class', olSel('olrC', mode === 'summary' ? ['All', ...OL_CLASSES] : OL_CLASSES, OLR.cls || (mode === 'summary' ? 'All' : OL_CLASSES[0]), 'OLR.cls=this.value'), 1]])}
    ${olRow([['Section', olSel('olrS', ['All', 'A', 'B', 'DIAMOND'], OLR.sec, 'OLR.sec=this.value')], ['Session', `<input type="text" value="${esc(olSes())}" readonly>`]])}
    ${one ? olRow([['Date', `<input type="date" value="${OLR.date}" onchange="OLR.date=this.value">`], ['Status', olSel('olrT', ['All', 'P', 'A', 'L'], OLR.status, 'OLR.status=this.value')]])
      : olRow([['Start Date', `<input type="date" value="${OLR.from}" onchange="OLR.from=this.value">`], ['End Date', `<input type="date" value="${OLR.to}" onchange="OLR.to=this.value">`]])}</table>
    <div class="b"><button class="sub" onclick="olrGo('${mode}')">Submit</button></div>`;
  if (!OLR.go || OLR.mode !== mode) return olPage(OLR_T[mode], form);
  const c = OLR.cls || (mode === 'summary' ? 'All' : OL_CLASSES[0]), list = c === 'All' ? [] : olStudents(c, OLR.sec);
  let out = '';
  if (one) {
    const rows = list.map(s => [s, olMark(OLR.date, c, s.id)]).filter(([, m]) => m && (OLR.status === 'All' || m === OLR.status));
    out = attOf(OLR.date, c) ? `<table class="lst"><tr><th>Sno</th><th>Scholar No</th><th>Student Name</th><th>Father's Name</th><th>Mobile No</th><th>Status</th></tr>${rows.map(([s, m], i) => `<tr><td>${i + 1}</td><td>${esc(s.adm)}</td><td class="t">${esc(s.name)}</td><td class="t">${esc(s.father || '')}</td><td>${esc(s.phone || '')}</td><td>${m}</td></tr>`).join('') || '<tr><td colspan="6">Nobody.</td></tr>'}</table>` : '<p>Attendance is not marked for this class on this date.</p>';
  } else {
    const days = olDays(OLR.from, OLR.to), marked = days.filter(d => c === 'All' ? OL_CLASSES.some(x => attOf(d, x)) : attOf(d, c));
    if (mode === 'summary') {
      out = `<table class="lst"><tr><th>Class</th><th>Total Students</th><th>Working Days</th><th>Present</th><th>Absent</th><th>Leave</th><th>Attendance %</th></tr>${(c === 'All' ? OL_CLASSES : [c]).map(x => { const ss = olStudents(x, OLR.sec), wd = days.filter(d => attOf(d, x)); let p = 0, a = 0, l = 0; wd.forEach(d => ss.forEach(s => { const m = olMark(d, x, s.id); if (m === 'P') p++; else if (m === 'L') l++; else if (m === 'A') a++; })); const tot = p + a + l; return `<tr><td>${x}</td><td>${ss.length}</td><td>${wd.length}</td><td>${p}</td><td>${a}</td><td>${l}</td><td>${tot ? (p * 100 / tot).toFixed(2) : '0.00'}</td></tr>`; }).join('')}</table>`;
    } else if (mode === 'register') {
      out = `<div class="w"><table class="lst"><tr><th>Sno</th><th>Scholar No</th><th>Student Name</th>${days.map(d => `<th>${d.slice(8)}</th>`).join('')}<th>P</th><th>A</th><th>L</th></tr>${list.map((s, i) => { const ms = days.map(d => olMark(d, c, s.id)); return `<tr><td>${i + 1}</td><td>${esc(s.adm)}</td><td class="t">${esc(s.name)}</td>${ms.map(m => `<td>${m}</td>`).join('')}<td>${ms.filter(m => m === 'P').length}</td><td>${ms.filter(m => m === 'A').length}</td><td>${ms.filter(m => m === 'L').length}</td></tr>`; }).join('')}</table></div>`;
    } else {
      out = `<table class="lst"><tr><th>Sno</th><th>Scholar No</th><th>Student Name</th><th>Father's Name</th><th>Working Days</th><th>Present</th><th>Absent</th><th>Leave</th><th>Attendance %</th>${mode === 'abs2' ? '<th>Absent Dates</th>' : ''}</tr>${list.map(s => { const ms = marked.map(d => [d, olMark(d, c, s.id)]), p = ms.filter(x => x[1] === 'P').length, a = ms.filter(x => x[1] === 'A'), l = ms.filter(x => x[1] === 'L').length; return [s, p, a, l]; }).filter(([, , a]) => mode !== 'abs2' || a.length).map(([s, p, a, l], i) => `<tr><td>${i + 1}</td><td>${esc(s.adm)}</td><td class="t">${esc(s.name)}</td><td class="t">${esc(s.father || '')}</td><td>${marked.length}</td><td>${p}</td><td>${a.length}</td><td>${l}</td><td>${marked.length ? (p * 100 / marked.length).toFixed(2) : '0.00'}</td>${mode === 'abs2' ? `<td class="t">${a.map(x => olDmy(x[0])).join(', ')}</td>` : ''}</tr>`).join('')}</table>`;
    }
  }
  return olPage(OLR_T[mode], form + `<div class="sch">${esc((S.settings || {}).school || '')}</div><div class="sub">${esc(OLR_T[mode])} — ${c === 'All' ? 'All classes' : 'Class ' + esc(c)}${OLR.sec !== 'All' ? '-' + esc(OLR.sec) : ''} · ${one ? olDmy(OLR.date) : olDmy(OLR.from) + ' to ' + olDmy(OLR.to)}</div><div class="b np"><button class="sub alt" onclick="window.print()">Print</button></div><div class="w">${out}</div>`);
}
async function olrGo(mode) {
  OLR.go = true; OLR.mode = mode;
  // Older days than the app keeps in memory are fetched once (up to one year).
  const days = mode === 'abs' ? [OLR.date] : olDays(OLR.from, OLR.to);
  if (days.length > 40) toast('Opening ' + days.length + ' days…', 'b');
  for (const d of days) await ensureDate(d);
  render();
}
// ---------- switch the views when the old look is on ----------
(function () {
  const att = VIEWS.attendance;
  VIEWS.attendance = () => (typeof classicOn === 'function' && classicOn()) ? vAttOld() : att();
})();
