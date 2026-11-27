'use strict';
// ============================================================================
//  Teacher App — Duty desk, arrangement "reached", bell alert, 🐞 Problem batao (owner, 27 Nov 2026). Loaded LAST.
//  "arrangement bell na samajh aane ke wajah se random class chal raha hai … sport teacher free rahte hain to unko other
//   work me involve kar sakte hain jaise attendance lagana jo absent hain teacher unke class ka, arrangement bell ka, jo
//   bachche absent hain unko message share karna, homework nahi kar paa rahe unko message share karna";
//  "features add kar diye but vo use nahi kar paa rahe — find na kar pana, bug ana".
//
//  1. Duty teacher = settings.dutyTeachers (not saved → Adarsh Dubey Sir, name "Adarsh"; same as inc.php sch_is_duty and
//     app.js isDutyT). Tab 'duty' (Home card + ☰ menu) with four parts:
//       arr — today's arrangement bells: time, class, in place of whom, who goes, ✅ reached / ⏳ not yet, empty bells in red
//       att — classes whose class teacher is absent today → he marks TODAY's attendance (server: inc.php studAtt rule)
//       abs — absent children of every class (attendance already marked) → parent WhatsApp, ✓ sent is kept
//       hw  — children marked homework Not done / Half by the subject teachers today → parent WhatsApp, ✓ sent is kept
//  2. Arrangement slip (teacher-classwork.js): "✅ I am in Class X" button → duty_api.php ?a=reach. An in-app alert pops
//     5 minutes before an arrangement bell (and vibrates), once per bell.
//  3. 🐞 Problem batao: a small button on every screen; the report carries the screen, the teacher's words and the last
//     errors the app saw. Principal Home shows open reports; ☰ menu → Problem reports. Data: duty_api.php (kinds tchReach,
//     tchSent, tchBug — "tch" keeps them out of the legacy load).
// ============================================================================
const DUTY = { reach: {}, hw: {} };
const LASTERR = [];
function errNote(m) { m = String(m || '').slice(0, 300); if (!m) return; LASTERR.push(new Date().toTimeString().slice(0, 8) + ' ' + m); if (LASTERR.length > 6) LASTERR.shift(); }
window.addEventListener('error', e => errNote((e.message || 'error') + (e.filename ? ' @' + String(e.filename).split('/').pop() + ':' + e.lineno : '')));
window.addEventListener('unhandledrejection', e => errNote('Promise: ' + ((e.reason && e.reason.message) || e.reason)));
(function () { const ce = console.error; console.error = function () { try { errNote([...arguments].map(x => (x && x.message) || String(x)).join(' ')); } catch (e) {} return ce.apply(console, arguments); }; })();
if (typeof toast === 'function') { const _t = toast; toast = function (m) { if (/could not|problem|error|failed|wrong|nahi ho/i.test(String(m || ''))) errNote('Toast: ' + m); return _t.apply(this, arguments); }; }

function isDutyMe() { return !!(S && ME) && (isP() || (ME.role === 'teacher' && isDutyT(me()))); }
// Classes whose class teacher is absent on that date (same rule as inc.php sch_ct_absent).
function dutyAttCls(date) { const ab = new Set(staffAbsent(date)); return (S.classes || []).filter(k => k.ct && ab.has(k.ct) && studentsOf(k.c).length && isWorkDay(date, k.c)).map(k => k.c); }

// ---------- reached the class ----------
function needReach(date) {
  const R = DUTY.reach[date];
  if (R && (R.loading || Date.now() - R.at < 60000)) return R;
  DUTY.reach[date] = { loading: !R, at: Date.now(), map: R ? R.map : {} };
  api('duty_api.php?a=reached&date=' + date).then(r => { DUTY.reach[date] = { at: Date.now(), map: r.reached || {} }; softRender(); }).catch(() => { DUTY.reach[date] = { at: Date.now(), map: (R && R.map) || {}, err: 1 }; });
  return DUTY.reach[date];
}
function dutyReached(date, bell, cls) { return (needReach(date).map || {})[bell + '|' + cls] || null; }
function dutyReachBtn(date, bell, cls, rc) {
  if (date !== todayISO()) return '';
  return rc ? `<button class="btn s l" style="margin-top:6px" onclick="dutyReach('${date}',${bell},${jsq(cls)},false)">✅ In the class · undo</button>`
    : `<button class="btn s go" style="margin-top:6px" onclick="dutyReach('${date}',${bell},${jsq(cls)},true)">✅ I am in Class ${esc(cls)}</button>`;
}
async function dutyReach(date, bell, cls, on) {
  try { const r = await api('duty_api.php?a=reach', { date, bell, cls, on }); const R = needReach(date); R.map = Object.assign({}, R.map); if (on) R.map[bell + '|' + cls] = r.reached; else delete R.map[bell + '|' + cls]; toast(on ? 'Marked — you are in Class ' + cls + '.' : 'Taken back.'); render(); }
  catch (e) { toast(e.message); }
}

// ---------- alert 5 minutes before an arrangement bell (once per bell, this phone) ----------
function dutyBellTick() {
  try {
    if (!S || !ME || isP()) return;
    const d = todayISO(); if (dayIdx(d) < 0 || holidayOn(d)) return;
    const nm = nowMin();
    myBells(d).filter(b => b.arr).forEach(b => {
      const B = BELLS[b.bell]; if (!B || !B.s) return; const st = toMin(B.s); if (nm < st - 5 || nm >= st + 2) return;
      const k = 'ta_bellalert_' + d + '_' + b.bell; if (store.get(k)) return; store.set(k, '1');
      if (navigator.vibrate) try { navigator.vibrate([300, 150, 300]); } catch (e) {}
      let o = document.getElementById('bellAlert'); if (o) o.remove();
      o = document.createElement('div'); o.id = 'bellAlert'; o.className = 'bell-alert';
      o.innerHTML = `<b>🔔 Arrangement — ${esc(B.n)} bell at ${esc(t12(B.s))}</b><span>Go to <b>Class ${esc(b.cls)}</b> · ${esc(b.subj)}<br>In place of ${esc(T(b.for).name || 'absent teacher')}</span><div><button class="btn s go" onclick="this.closest('.bell-alert').remove();go('home')">Open slip</button><button class="btn s l" onclick="this.closest('.bell-alert').remove()">OK</button></div>`;
      document.body.appendChild(o);
    });
  } catch (e) {}
}
setInterval(dutyBellTick, 30000); setTimeout(dutyBellTick, 4000);

// ---------- Attendance: the Duty teacher may mark today's attendance of a class whose class teacher is absent ----------
if (typeof vAtt === 'function') {
  const _vAttD = vAtt;
  vAtt = function () {
    try {
      const t = todayISO();
      if (S && ME && ME.role === 'teacher' && isDutyT(me())) {
        const dc = dutyAttCls(t).filter(c => !myCT().includes(c));
        if (dc.length && (P.duty || !myClasses().length || (dc.includes(P.c) && !myClasses().includes(P.c)))) {
          const c = dc.includes(P.c) ? P.c : dc[0]; P.c = c; P.date = t; P.m = 'day';
          return `<div class="ban warn"><div><b>🛡 Duty — Class ${esc(c)} attendance</b><br><span class="sm">The class teacher (${esc(T((S.classes.find(k => k.c === c) || {}).ct).name)}) is absent today, so you mark today's attendance. Tap only the absent children, then Save.</span></div></div>
            ${dc.length > 1 ? `<div class="fld">Class${sel('dAttC', dc.map(x => [x, 'Class ' + x + (attOf(t, x) ? ' · ✓ marked' : ' · not marked')]), c, "DRAFT.att=null;go('att',{c:this.value,duty:1})")}</div>` : ''}
            ${vAttDay(c)}<button class="btn l full" style="margin-top:10px" onclick="go('duty',{m:'att'})">← Back to Duty</button>`;
        }
      }
    } catch (e) { console.error(e); }
    return _vAttD.apply(this, arguments);
  };
}

// ---------- Duty tab ----------
function needHwNot(date) {
  const R = DUTY.hw[date];
  if (R && (R.loading || Date.now() - R.at < 90000)) return R;
  DUTY.hw[date] = { loading: !R, at: Date.now(), items: R ? R.items : [], sent: R ? R.sent : {} };
  api('duty_api.php?a=hwnot&date=' + date).then(r => { DUTY.hw[date] = { at: Date.now(), items: r.items || [], sent: r.sent || {} }; softRender(); }).catch(e => { DUTY.hw[date] = { at: Date.now(), items: (R && R.items) || [], sent: (R && R.sent) || {}, err: e.message }; softRender(); });
  return DUTY.hw[date];
}
function dutyData(date) {
  const work = isWorkDay(date), arr = work ? arrangementBase(date, monthLoad(date)) : [], R = needReach(date).map || {}, nm = nowMin(), today = date === todayISO();
  const attC = work ? dutyAttCls(date) : [], H = needHwNot(date);
  const abs = []; if (work) className().forEach(c => { const a = attOf(date, c); if (!a) return; (a.absent || []).forEach(id => { const s = studentById(id); if (s) abs.push({ s, c, lv: ((a.st || {})[id]) === 'L' }); }); });
  const over = b => today ? nm >= toMin((BELLS[b] || {}).e || '23:59') : date < todayISO();
  return { work, arr, R, attC, H, abs, over, nm, today,
    nArr: arr.filter(x => !x.sub || !R[x.bell + '|' + x.cls]).length, nAtt: attC.filter(c => !attOf(date, c)).length,
    nAbs: abs.filter(x => !(H.sent || {})['abs|' + x.s.id + '|']).length, nHw: (H.items || []).filter(x => !(H.sent || {})['hw|' + x.sid + '|' + x.subj]).length };
}
function vDuty() {
  if (!isDutyMe()) return `<div class="card"><h3>Duty</h3><p class="mut">This page is for the Duty teacher and the Principal.</p></div>`;
  const t = todayISO(), date = P.date && P.date <= t ? P.date : t, D = dutyData(date), m = ['arr', 'att', 'abs', 'hw', 'chk'].includes(P.m) ? P.m : 'arr';
  const nav = `<div class="datebar"><button class="btn s w" onclick="go('duty',{m:'${m}',date:'${addDays(date, -1)}'})">${ic('back')}</button><label class="dpick"><b>${date === t ? 'Today' : fmtDate(date)}</b><small>${esc(fmtLong(date))}</small><input type="date" max="${t}" value="${date}" onchange="go('duty',{m:'${m}',date:this.value})"></label><button class="btn s w" ${date >= t ? 'disabled' : ''} onclick="go('duty',{m:'${m}',date:'${addDays(date, 1)}'})">${ic('next')}</button></div>`;
  const n = x => x ? ` <span class="dot" style="position:static;display:inline-flex">${x}</span>` : '';
  const head = `<div class="card dutyhead"><h2 style="margin:0">🛡 Duty desk</h2><p class="mut" style="margin:4px 0 0">Arrangement bells, attendance of absent teachers' classes, and parent messages — all in one place.</p></div>` + nav +
    seg([['arr', '🔁 Arrangement' + n(D.nArr)], ['att', '✅ Attendance' + n(D.nAtt)], ['abs', '🙋 Absent' + n(D.nAbs)], ['hw', '📕 Homework' + n(D.nHw)], ['chk', '📚 HW check' + n(dutyHwCount())]], m, "(k=>go('duty',{m:k,date:'" + date + "'}))");
  if (!D.work) return head + '<div class="ban hol">No school on this day.</div>';
  if (m === 'chk') return head + (date === t ? vDutyHw() : '<div class="card"><p class="mut" style="margin:0">The homework check report is for today. Go to Today.</p></div>');
  if (m === 'arr') {
    if (!D.arr.length) return head + `<div class="card"><p class="mut" style="margin:0">No teacher is absent — no arrangement ${date === t ? 'today' : 'on this day'}.</p></div>`;
    return head + `<div class="card">${D.arr.map(x => { const B = BELLS[x.bell] || {}, rc = D.R[x.bell + '|' + x.cls], now = D.today && D.nm >= toMin(B.s || '00:00') && D.nm < toMin(B.e || '00:00');
      return `<div class="cwr${now ? ' cw-now' : ''}"><div><b>🔔 ${esc(B.n || '')} bell · ${B.s ? esc(t12(B.s)) + '–' + esc(t12(B.e)) : ''}${now ? ' · NOW' : ''}</b><b>Class ${esc(x.cls)} · ${esc(x.subj)}</b>
        <small>Absent: ${esc(T(x.absent).name)} → ${x.sub ? 'goes: <b>' + esc(T(x.sub).name) + '</b>' : '<b style="color:#B42318">nobody — empty bell, please go or tell the Principal</b>'}</small>
        <small>${rc ? '✅ Reached the class (' + esc(rc.by || '') + ')' : D.over(x.bell) ? '<span style="color:#B42318">✗ Nobody marked "reached"</span>' : '⏳ Not reached yet'}</small></div>
        <div style="display:flex;flex-direction:column;gap:4px">${x.sub && T(x.sub).phone ? `<a class="btn s l" href="tel:${esc(String(T(x.sub).phone).replace(/\D/g, '').slice(-10))}">📞 Call</a>` : ''}${date === t ? (rc ? `<button class="btn s l" onclick="dutyReach('${date}',${x.bell},${jsq(x.cls)},false)">Undo</button>` : `<button class="btn s go" onclick="dutyReach('${date}',${x.bell},${jsq(x.cls)},true)">✅ Reached</button>`) : ''}</div></div>`; }).join('')}
      <p class="mut" style="margin:8px 0 0;font-size:12px">Teachers mark "I am in the class" from their own slip. You can mark it after you check the class yourself.</p></div>`;
  }
  if (m === 'att') {
    if (!D.attC.length) return head + `<div class="card"><p class="mut" style="margin:0">No class teacher is absent — nothing to mark.</p></div>`;
    return head + `<div class="card">${D.attC.map(c => { const a = attOf(date, c), k = (S.classes.find(z => z.c === c) || {});
      return `<div class="cwr"><div><b>Class ${esc(c)}</b><small>Class teacher absent: ${esc(T(k.ct).name)}${a ? ' · ✓ marked (' + ((a.absent || []).length) + ' absent)' : ' · <b style="color:#B42318">not marked</b>'}</small></div>${date === t ? `<button class="btn s ${a ? 'l' : 'go'}" onclick="DRAFT.att=null;go('att',{c:${jsq(c)},duty:1})">${a ? 'Open' : 'Mark now'}</button>` : ''}</div>`; }).join('')}
      <p class="mut" style="margin:8px 0 0;font-size:12px">Only today's attendance can be marked here.</p></div>`;
  }
  if (m === 'abs') {
    const notMarked = className().filter(c => studentsOf(c).length && isWorkDay(date, c) && !attOf(date, c));
    const sent = D.H.sent || {}, by = {}; D.abs.forEach(x => (by[x.c] = by[x.c] || []).push(x));
    return head + (notMarked.length ? `<div class="ban warn"><div>Attendance not marked yet: ${notMarked.map(c => 'Class ' + esc(c)).join(', ')}</div></div>` : '') +
      (D.abs.length ? Object.keys(by).map(c => `<div class="card"><h3 style="margin:0 0 4px">Class ${esc(c)} · ${by[c].length} absent</h3>${by[c].map(x => { const ok = sent['abs|' + x.s.id + '|'], txt = msgText('absent', x.s, c, { date });
        return `<div class="cwr"><div><b>${esc(x.s.name)}</b><small>${x.lv ? 'Leave' : 'Absent'}${x.s.father ? ' · ' + esc(x.s.father) : ''}${ok ? ' · ✓ sent' : ''}</small></div>${x.s.phone ? `<a class="btn s ${ok ? 'l' : 'wa'}" target="_blank" rel="noopener" href="${wa(x.s.phone, txt)}" onclick="dutySent('${date}','abs',${jsq(x.s.id)},'')">${ok ? 'Again' : 'WhatsApp'}</a>` : '<small>No mobile</small>'}</div>`; }).join('')}</div>`).join('')
        : `<div class="card"><p class="mut" style="margin:0">No absent child in the classes marked so far.</p></div>`);
  }
  const H = D.H, sent = H.sent || {};
  if (H.loading) return head + '<div class="load"><i></i></div>';
  return head + (H.err ? `<div class="ban warn">${esc(H.err)}</div>` : '') + ((H.items || []).length ? `<div class="card">${H.items.map(x => { const s = studentById(x.sid); if (!s) return ''; const ok = sent['hw|' + x.sid + '|' + x.subj], txt = msgText('hw', s, x.cls, { subj: x.subj, date, note: x.task });
      return `<div class="cwr"><div><b>${esc(s.name)} · Class ${esc(x.cls)}</b><small>${esc(x.subj)} · ${x.hw === 'half' ? 'half done' : 'not done'} · checked by ${esc(x.teacher)}${ok ? ' · ✓ sent' : ''}</small></div>${s.phone ? `<a class="btn s ${ok ? 'l' : 'wa'}" target="_blank" rel="noopener" href="${wa(s.phone, txt)}" onclick="dutySent('${date}','hw',${jsq(x.sid)},${jsq(x.subj)})">${ok ? 'Again' : 'WhatsApp'}</a>` : '<small>No mobile</small>'}</div>`; }).join('')}</div>`
    : `<div class="card"><p class="mut" style="margin:0">No child is marked "homework not done" yet ${date === t ? 'today' : 'on this day'}. The list fills when subject teachers save Check work.</p></div>`);
}

// ---------- 📚 Homework check report — whole school, today (owner, 27 Nov 2026: "jo homework cross check ho jaa raha hai usko
// report bhi show kare Adarsh Sir aur Principal Sir par, sabhi jin class ka homework check nahi hua hai vo bhi show kare").
// For every class + subject in the timetable: the homework of its last class (crLastHw, any teacher's diary) and whether it is
// checked (duty_api.php ?a=hwsum — the Duty teacher and the Principal get every class).
//   done  = checked today (done / not done / half, who checked, names of children who did not do it)
//   late  = homework due, its class today is already over, still not checked  → Remind (Staff chat)
//   soon  = homework due, its class is later today
//   next  = homework due, no class of that subject today (checked at the next class)
function dutyHwBoard() {
  if (typeof crNeedHw !== 'function' || typeof crLastHw !== 'function') return [];
  const t = todayISO(), d = dayIdx(t), H = crNeedHw(), seen = new Set(), rows = [], order = className();
  (S.alloc || []).forEach(a => { if (!a || !order.includes(a.c) || !((+a.n || 0) > 0)) return; const k = sylKey(a.c, a.s); if (seen.has(k)) return; seen.add(k);
    const L = crLastHw(a.c, a.s), last = (H.last || {})[k] || '', rec = (H.checks || {})[k];
    const bl = d >= 0 && isWorkDay(t, a.c) ? BELLS.map((_, b) => b).filter(b => { const x = cell(a.c, d, b); return x && x.s === a.s; }) : [];
    const st = rec ? 'done' : (L && !(last > L.date)) ? (bl.length ? (bl.some(b => bellOver(t, b)) ? 'late' : 'soon') : 'next') : 'none';
    rows.push({ c: a.c, s: a.s, t: a.t, k, L, rec, st, bell: bl[0] });
  });
  return rows.sort((x, y) => order.indexOf(x.c) - order.indexOf(y.c) || x.s.localeCompare(y.s));
}
function dutyHwCount() { try { return dutyHwBoard().filter(r => r.st === 'late').length; } catch (e) { return 0; } }
function vDutyHw() {
  const H = crNeedHw(); if (H.loading && !H.last) return '<div class="load"><i></i></div>';
  const R = dutyHwBoard(), cnt = st => R.filter(r => r.st === st).length, f = ['done', 'late', 'wait'].includes(P.f) ? P.f : (cnt('late') ? 'late' : 'done');
  const head = (typeof crStats === 'function' ? crStats([['done', 'Checked today', cnt('done'), 'green'], ['late', 'Not checked', cnt('late'), 'red'], ['wait', 'Class later / next day', cnt('soon') + cnt('next'), 'amber']], f, k => `go('duty',{m:'chk',f:'${k}'})`) : '') +
    `<p class="cr-note">${R.filter(r => r.st !== 'none').length} class-subjects have homework to check · ${cnt('none')} gave no homework.</p>`;
  const list = R.filter(r => f === 'wait' ? (r.st === 'soon' || r.st === 'next') : r.st === f);
  if (!list.length) return head + (typeof crEmpty === 'function' ? crEmpty(f === 'late' ? '🎉' : '📚', f === 'late' ? 'Every due homework is checked' : f === 'done' ? 'No homework checked yet today' : 'Nothing waiting', f === 'done' ? 'Checks appear here as teachers Submit them.' : '') : '');
  const byC = {}; list.forEach(r => (byC[r.c] = byC[r.c] || []).push(r));
  return head + Object.keys(byC).map(c => `<div class="card cr-list"><div class="cr-prog"><div><b>Class ${esc(c)}</b></div><span class="pill">${byC[c].length} subject${byC[c].length > 1 ? 's' : ''}</span></div>${byC[c].map(r => {
    const tn = T(r.t).name || r.t;
    if (r.st === 'done') { const rows = Object.entries(r.rec.rows || {}), dn = rows.filter(([, x]) => x.hw === 'done').length, nt = rows.filter(([, x]) => x.hw === 'not'), hf = rows.filter(([, x]) => x.hw === 'half').length, tot = rows.filter(([, x]) => x.hw).length, p = tot ? Math.round(dn / tot * 100) : 0;
      const names = nt.map(([sid]) => (studentById(sid) || {}).name).filter(Boolean);
      return `<div class="cr-row"><div class="n"><b>${esc(r.s)}</b><small>${esc(tn)} · checked by ${esc(r.rec.by || '')}${r.rec.hw ? ' · “' + esc(r.rec.hw.slice(0, 40)) + '”' : ''}</small>
        <span class="cr-why t-green">✓ ${dn} done</span> ${nt.length ? `<span class="cr-why t-red">✗ ${nt.length} not done</span>` : ''} ${hf ? `<span class="cr-why t-amber">½ ${hf}</span>` : ''}
        ${names.length ? `<small class="why">Not done: ${esc(names.slice(0, 6).join(', '))}${names.length > 6 ? ' +' + (names.length - 6) : ''}</small>` : ''}<div class="cr-bar sm"><i class="${p < 60 ? 'lo' : p < 85 ? 'mid' : ''}" style="width:${p}%"></i></div></div><b class="cr-pct ${p < 60 ? 'lo' : p < 85 ? 'mid' : ''}">${p}%</b></div>`; }
    const B = r.bell !== undefined ? BELLS[r.bell] : null, msg = `Namaste ${tn}, Class ${r.c} ${r.s} ka homework (${fmtDate(r.L.date)}: "${r.L.hw.slice(0, 80)}") abhi check nahi hua. Kripya Teacher App → Classroom → Check HW mein check karke Submit kar dijiye.`;
    return `<div class="cr-row"><div class="n"><b>${esc(r.s)}</b><small>${esc(tn)} · given ${esc(fmtDate(r.L.date))}: “${esc(r.L.hw.slice(0, 40))}”</small>
      <span class="cr-why t-${r.st === 'late' ? 'red' : r.st === 'soon' ? 'amber' : 'grey'}">${r.st === 'late' ? 'Not checked — class was today' : r.st === 'soon' ? 'Class later today' + (B ? ' · ' + esc(B.n) + ' bell ' + esc(t12(B.s)) : '') : 'No class today — next class'}</span></div>
      ${r.st === 'late' ? `<button class="btn s go" onclick="dutyHwRemind(${jsq(r.t)},${jsq(msg)})">${ic('chat')} Remind</button>` : ''}</div>`; }).join('')}</div>`).join('');
}
async function dutyHwRemind(t, text) {
  try { await api('teacher_api.php?a=staffchat', { room: scDm(isP() ? 'P' : me(), t), text }); toast('Reminder sent to ' + (T(t).name || t) + ' in Staff chat.'); }
  catch (e) { toast(e.message); }
}

async function dutySent(date, type, sid, subj) {
  try { await api('duty_api.php?a=sent', { date, type, sid, subj, on: true }); const H = needHwNot(date); H.sent = Object.assign({}, H.sent, { [type + '|' + sid + '|' + subj]: { at: Date.now() } }); setTimeout(render, 600); } catch (e) { toast(e.message); }
}
function dutyCard() {
  if (!S || !ME || !isDutyMe() || isP()) return '';
  const t = todayISO(); if (!isWorkDay(t)) return '';
  const D = dutyData(t), tot = D.nArr + D.nAtt + D.nAbs + D.nHw + dutyHwCount();
  return `<div class="card dutycard" onclick="go('duty')" style="cursor:pointer"><h3 style="margin:0 0 6px">🛡 Today's duty ${tot ? '<span class="pill bad">' + tot + ' to do</span>' : '<span class="pill ok">all done</span>'}</h3>
    <div class="dgrid"><span><b>${D.arr.length}</b>Arrangement bells${D.nArr ? ' · ' + D.nArr + ' not reached' : ''}</span><span><b>${D.attC.length}</b>Classes to mark${D.nAtt ? ' · ' + D.nAtt + ' left' : ''}</span><span><b>${D.abs.length}</b>Absent children${D.nAbs ? ' · ' + D.nAbs + ' to message' : ''}</span><span><b>${(D.H.items || []).length}</b>Homework not done${D.nHw ? ' · ' + D.nHw + ' to message' : ''}</span><span onclick="event.stopPropagation();go('duty',{m:'chk'})"><b>${dutyHwCount()}</b>Homework not checked</span></div>
    <button class="btn go full" style="margin-top:8px">Open Duty desk</button></div>`;
}

// ---------- 🐞 Problem batao ----------
function bugScreen() { return [TAB, P.m, P.c ? 'Class ' + P.c : ''].filter(Boolean).join(' · '); }
function openBug() {
  const scr = bugScreen();
  SHEET = () => `<h2>🐞 Problem batao</h2><p class="mut" style="margin:0 0 8px">Tell us what did not work. The screen name and the app's last errors go with it, so it can be fixed quickly.</p>
    <div class="fld">Screen<input class="inp" id="bgS" value="${esc(scr)}"></div>
    <div class="fld">What happened?<textarea class="inp" id="bgM" rows="4" placeholder="e.g. Save button does nothing / I cannot find homework check / wrong class shows"></textarea></div>
    <div class="chips">${['Button does not work', 'Cannot find the option', 'Wrong data shows', 'App is slow / stuck', 'Message not sending'].map(x => `<button class="chip" onclick="const m=$('bgM');m.value=(m.value?m.value+' — ':'')+${jsq(x)}">${esc(x)}</button>`).join('')}</div>
    ${LASTERR.length ? `<p class="mut" style="font-size:11px;margin:8px 0 0">Errors seen: ${LASTERR.map(esc).join(' | ')}</p>` : ''}
    <button class="btn go full" style="margin-top:10px" onclick="sendBug()">Send to the Principal</button>`;
  render();
}
async function sendBug() {
  const msg = ($('bgM') || {}).value || '', screen = ($('bgS') || {}).value || bugScreen();
  try { await api('duty_api.php?a=bug', { msg, screen, err: LASTERR.join(' | '), ver: (navigator.userAgent || '').slice(0, 60) }); SHEET = null; toast('Thank you — the problem is sent.'); render(); } catch (e) { toast(e.message); }
}
const BUGS = { list: null, loading: false };
function needBugs(force) {
  if (BUGS.loading || (BUGS.list && !force && Date.now() - BUGS.at < 120000)) return BUGS;
  BUGS.loading = true; api('duty_api.php?a=bugs').then(r => { BUGS.list = r.bugs || []; BUGS.at = Date.now(); BUGS.loading = false; softRender(); }).catch(e => { BUGS.loading = false; BUGS.err = e.message; BUGS.at = Date.now(); softRender(); });
  return BUGS;
}
function vBugs() {
  if (!isP()) return `<div class="card"><p class="mut">Problem reports are for the Principal.</p></div>`;
  const B = needBugs(); if (!B.list) return B.err ? `<div class="ban warn">${esc(B.err)}</div>` : '<div class="load"><i></i></div>';
  return `<div class="card"><h2 style="margin:0">🐞 Problem reports</h2><p class="mut" style="margin:4px 0 0">What teachers reported in the last 60 days. Open ones first.</p></div>` +
    (B.list.length ? B.list.map(b => `<div class="card" style="${b.done ? 'opacity:.6' : ''}"><b>${esc(b.by)}</b> <small class="mut">${esc(fmtTime(b.at))}${b.screen ? ' · ' + esc(b.screen) : ''}</small><p style="margin:6px 0">${esc(b.msg || '(no words)')}</p>${b.err ? `<p class="mut" style="font-size:11px;margin:0 0 6px">${esc(b.err)}</p>` : ''}
      <button class="btn s ${b.done ? 'l' : 'go'}" onclick="bugDone(${jsq(b.id)},${b.done ? 'false' : 'true'})">${b.done ? 'Open again' : '✓ Fixed'}</button></div>`).join('') : `<div class="card"><p class="mut" style="margin:0">No problem reported.</p></div>`);
}
async function bugDone(id, on) { try { await api('duty_api.php?a=bugdone', { id, on }); needBugs(true); } catch (e) { toast(e.message); } }

// ---------- wiring: views, Home cards, ☰ menu, the 🐞 button ----------
if (typeof render === 'function') { const _rd = render; render = function () { _rd.apply(this, arguments); dutyAfter(); }; }
function dutyAfter() {
  if (!S || !ME) return;
  const loc = document.querySelector('.head .loc'), av = loc && loc.querySelector('.av');
  if (av && !loc.querySelector('.bugb')) av.insertAdjacentHTML('beforebegin', `<button class="bugb" onclick="openBug()" aria-label="Problem batao" title="Problem batao">🐞</button>`);
}
if (typeof vHome === 'function') { const _vhD = vHome; vHome = function () { return dutyCard() + _vhD.apply(this, arguments); }; }
if (typeof vPdash === 'function') {
  const _vpD = vPdash;
  vPdash = function () {
    const B = needBugs(), open = (B.list || []).filter(b => !b.done).length, t = todayISO(), D = isWorkDay(t) ? dutyData(t) : null;
    const empty = D ? D.arr.filter(x => !x.sub).length : 0;
    return (open ? `<div class="card" onclick="go('bugs')" style="cursor:pointer;border:2px solid #F0443855"><h3 style="margin:0">🐞 ${open} problem${open > 1 ? 's' : ''} reported by teachers</h3><p class="mut" style="margin:4px 0 0">Tap to see what is not working.</p></div>` : '') +
      (D ? (() => { const R = dutyHwBoard(), dn = R.filter(r => r.st === 'done').length, lt = R.filter(r => r.st === 'late').length, wt = R.filter(r => r.st === 'soon' || r.st === 'next').length;
        return R.length ? `<div class="card" onclick="go('duty',{m:'chk'})" style="cursor:pointer;${lt ? 'border:2px solid #F0443855' : ''}"><h3 style="margin:0">📚 Homework check today</h3><p class="mut" style="margin:4px 0 0"><b style="color:#15803D">${dn} checked</b> · <b style="color:#B4234F">${lt} not checked</b> · ${wt} class later / next day</p></div>` : ''; })() : '') +
      (D && D.arr.length ? `<div class="card" onclick="go('duty')" style="cursor:pointer"><h3 style="margin:0">🛡 Duty desk — ${D.arr.length} arrangement bell${D.arr.length > 1 ? 's' : ''} today</h3><p class="mut" style="margin:4px 0 0">${empty ? '<b style="color:#B42318">' + empty + ' empty (nobody free in that wing)</b> · ' : ''}${D.nArr} not reached yet · ${D.nAtt} class attendance left</p></div>` : '') + _vpD.apply(this, arguments);
  };
}
if (typeof MENU !== 'undefined' && Array.isArray(MENU)) {
  const g = MENU.find(x => x[0] === 'Parents & staff');
  if (g) g[1].push(['duty', {}, 'check', 'Duty desk', 'Arrangement, absent class, messages'], ['bugs', {}, 'note', 'Problem reports', 'Teachers ke bheje problem']);
  if (typeof openMenu === 'function') {
    const _om = openMenu;
    openMenu = function () {
      const keep = g ? g[1].slice() : null;
      if (g) g[1] = g[1].filter(x => (x[0] !== 'duty' || isDutyMe()) && (x[0] !== 'bugs' || isP()));
      try { _om.apply(this, arguments); } finally { if (g) g[1] = keep; }
    };
  }
}
(function () {
  const st = document.createElement('style');
  st.textContent = `.cw-now{background:#FEF3F2;border-radius:10px;padding:8px!important;border:2px solid #F04438}.cw-soon{background:#FFFAEB;border-radius:10px;padding:8px!important}
  .cwr b+b{display:block}.bugb{border:0;background:rgba(255,255,255,.18);color:#fff;border-radius:12px;width:36px;height:36px;font-size:17px;margin-right:6px;cursor:pointer}
  .bell-alert{position:fixed;left:12px;right:12px;top:12px;z-index:200;background:#fff;border:3px solid #F04438;border-radius:18px;padding:14px;box-shadow:0 12px 40px rgba(0,0,0,.25);display:flex;flex-direction:column;gap:6px;font-size:15px}
  .bell-alert div{display:flex;gap:8px}.dgrid{display:grid;grid-template-columns:1fr 1fr;gap:6px}.dgrid span{background:#F4F6F8;border-radius:10px;padding:8px;font-size:12px;color:#445}.dgrid b{display:block;font-size:20px;color:#17324D}
  .dutycard{border:2px solid #2E90FA55}`;
  document.head.appendChild(st);
})();
