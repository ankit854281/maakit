'use strict';
// ============================================================================
//  Teacher App — one pattern for every daily job: ENTRY → REPORT & MESSAGE (owner, 27 Nov 2026).
//  "attendance entry ho, fir report — 1 day, 3 days, 75% se kam — aur wahi message share karne ka option … homework check me
//   jo homework assign kiya gaya tha vohi show ho … jo homework nahi kiya bas usko tick karo aur submit … aise he sabhi".
//  Loaded after teacher-hwplus.js, before teacher-duty.js. Same records as before — nothing in the data format changes.
//
//  ATTENDANCE (class teacher): tabs  ✅ Entry | 📊 Report & Message | ⋯ More
//    Report & Message: Today absent · Absent 3 days in a row (last 3 marked days) · Below 75% (session, attstats)
//    Each child: reason line + WhatsApp (English + Hindi) + ✓ sent (shared record duty_api.php tchSent: abs | abs3 | low),
//    "Send next" walks the unsent ones. More = Daily check, Students, Levels, Calendar, Register (unchanged screens).
//  CLASSROOM: tabs  ✍️ Diary | ✅ Check homework | 📊 Report & Message
//    Check: for each of my class + subject, the homework given in the LAST class (latest diary with homework, any teacher)
//    that is not checked yet (duty_api.php ?a=hwsum: last check date). Big homework card, roll-number tiles, everyone ✓,
//    tap only who did not (tap again = half), or type roll numbers; Submit → teacher_api.php ?a=check (date = today).
//    Report & Message: Not done today · 3+ times in 30 days · class completion of the last check.
//  DIARY: a bell not written yet shows "✔ Save as planned" — topic from the lesson plan / syllabus, class work and homework
//    filled from it — one tap. The open card's empty class work / homework boxes come pre-filled (editable).
// ============================================================================
const CR = { sent: {}, hw: null };
function crNeedSent(date) {
  const R = CR.sent[date];
  if (R && (R.loading || Date.now() - R.at < 60000)) return R;
  CR.sent[date] = { loading: !R, at: Date.now(), map: R ? R.map : {} };
  api('duty_api.php?a=sentlist&date=' + date).then(r => { CR.sent[date] = { at: Date.now(), map: r.sent || {} }; softRender(); }).catch(() => { CR.sent[date] = { at: Date.now(), map: (R && R.map) || {} }; });
  return CR.sent[date];
}
function crSent(date, type, sid, subj) { return (crNeedSent(date).map || {})[type + '|' + sid + '|' + (subj || '')] || null; }
async function crMark(date, type, sid, subj) {
  const R = crNeedSent(date); R.map = Object.assign({}, R.map, { [type + '|' + sid + '|' + (subj || '')]: { at: Date.now() } });
  try { await api('duty_api.php?a=sent', { date, type, sid, subj: subj || '', on: true }); } catch (e) { toast(e.message); }
  setTimeout(render, 500);
}
function crRoll(c, s) { const i = studentsOf(c).findIndex(x => x.id === s.id); return s.rollno || (i + 1); }
function crSign() { return `— ${myName()}, ${(S.settings && S.settings.school) || ''}`; }
// One row = child + why + WhatsApp + ✓ sent. items: [{s, c, why, text, subj}]
function crMsgList(date, type, items, emptyTxt) {
  if (!items.length) return `<div class="card"><p class="mut" style="margin:0">${emptyTxt}</p></div>`;
  const unsent = items.filter(x => x.s.phone && !crSent(date, type, x.s.id, x.subj));
  const nx = unsent[0];
  return `<div class="card cr-list">
    <div class="cr-lh"><b>${items.length} ${items.length > 1 ? 'children' : 'child'}</b><span class="pill ${unsent.length ? 'warn' : 'ok'}">${unsent.length ? unsent.length + ' message' + (unsent.length > 1 ? 's' : '') + ' to send' : '✓ all sent'}</span></div>
    ${nx ? `<a class="btn go full" target="_blank" rel="noopener" href="${wa(nx.s.phone, nx.text)}" onclick="crMark('${date}','${type}',${jsq(nx.s.id)},${jsq(nx.subj || '')})">${ic('wa')} Send next — ${esc(nx.s.name)}</a>` : ''}
    ${items.map(x => { const ok = crSent(date, type, x.s.id, x.subj);
      return `<div class="cr-row ${ok ? 'sent' : ''}"><div class="n"><b>${esc(x.s.name)}</b><small>Class ${esc(x.c)} · Roll ${esc(crRoll(x.c, x.s))}${x.subj ? ' · ' + esc(x.subj) : ''}</small><small class="why">${x.why}</small></div>
        ${x.s.phone ? `<a class="btn s ${ok ? 'l' : 'wa'}" target="_blank" rel="noopener" href="${wa(x.s.phone, x.text)}" onclick="crMark('${date}','${type}',${jsq(x.s.id)},${jsq(x.subj || '')})">${ok ? '✓ Sent' : ic('wa') + ' Send'}</a>` : '<small class="mut">No mobile</small>'}</div>`; }).join('')}
    <p class="mut" style="font-size:11px;margin:8px 0 0">Each message goes to the parent alone — never to the class group.</p></div>`;
}

// ---------------------------------------------------------------- ATTENDANCE
const CR_MORE = ['kit', 'stu', 'bulk', 'cal', 'reg', 'pending'];
function crAttDays(c, n) {      // the last n days (today included) whose attendance of class c is saved
  const out = [], t = todayISO();
  for (let i = 0; i <= 40 && out.length < n; i++) { const d = addDays(t, -i); if (attOf(d, c)) out.push(d); }
  return out;
}
function crAttData(c) {
  const t = todayISO(), list = studentsOf(c), a = attOf(t, c);
  const today = a ? (a.absent || []).map(id => studentById(id)).filter(Boolean) : null;
  const d3 = crAttDays(c, 3), three = d3.length === 3 ? list.filter(s => d3.every(d => ((attOf(d, c) || {}).absent || []).includes(s.id))) : [];
  const low = list.map(s => [s, pctOf(c, s.id)]).filter(x => x[1] !== null && x[1] < 75).sort((x, y) => x[1] - y[1]);
  return { t, list, a, today, d3, three, low };
}
function crAttText(kind, s, c, x) {
  const sch = (S.settings && S.settings.school) || '';
  if (kind === 'abs3') return `Dear Parent,\n${s.name} (Class ${c}) has been absent for the last 3 school days (${x.days}). Kindly tell us the reason and send ${s.name} to school regularly.\n${s.name} पिछले 3 दिनों (${x.days}) से विद्यालय नहीं आया/आई है। कृपया कारण बताएँ और बच्चे को नियमित भेजें।\n${crSign()}`;
  if (kind === 'low') { const board = /^(9th|10th)$/i.test(c);
    return `Dear Parent,\n${s.name}'s attendance (Class ${c}) this session is ${x.p}% — below the required 75%.${board ? ' As per CBSE rules, a student needs at least 75% attendance to appear in the board exam.' : ''} Kindly make sure ${s.name} comes to school every day.\n${s.name} की इस सत्र की उपस्थिति ${x.p}% है, जो ज़रूरी 75% से कम है।${board ? ' CBSE नियम के अनुसार बोर्ड परीक्षा के लिए कम से कम 75% उपस्थिति ज़रूरी है।' : ''} कृपया बच्चे को रोज़ विद्यालय भेजें।\n${crSign()}`; }
  return msgText('absent', s, c, { date: todayISO() });
}
function crDays(ds) { return ds.slice().reverse().map(d => fmtDate(d).replace(/^\w+,?\s*/, '')).join(', '); }
function vAttRep(c) {
  needStats(c); const D = crAttData(c), f = ['today', 'abs3', 'low'].includes(P.f) ? P.f : 'today', date = D.t;
  const chips = [['today', 'Today absent', D.today ? D.today.length : '—'], ['abs3', '3 days in a row', D.d3.length === 3 ? D.three.length : '—'], ['low', 'Below 75%', D.low.length]];
  const head = `<div class="cr-sum">${chips.map(([k, l, n]) => `<button class="${k === f ? 'on' : ''} ${k}" onclick="go('att',{c:${jsq(c)},m:'rep',f:'${k}'})"><b>${n}</b><span>${l}</span></button>`).join('')}</div>`;
  if (f === 'today') {
    if (!D.today) return head + `<div class="ban warn"><div><b>Today's attendance is not marked yet.</b><br><button class="btn s go" style="margin-top:6px" onclick="go('att',{c:${jsq(c)},m:'day'})">Mark attendance</button></div></div>`;
    return head + crMsgList(date, 'abs', D.today.map(s => ({ s, c, why: ((D.a.st || {})[s.id] === 'L' ? 'On leave today' : 'Absent today'), text: crAttText('abs', s, c, {}) })), `Everyone is present today in Class ${esc(c)} 👍`);
  }
  if (f === 'abs3') {
    if (D.d3.length < 3) return head + `<div class="card"><p class="mut" style="margin:0">Attendance of 3 days is needed for this list (found ${D.d3.length}).</p></div>`;
    const days = crDays(D.d3);
    return head + `<p class="mut" style="margin:0 0 6px">Last 3 marked days: ${esc(days)}</p>` + crMsgList(date, 'abs3', D.three.map(s => ({ s, c, why: 'Absent on all 3 days', text: crAttText('abs3', s, c, { days }) })), 'No child was absent on all of the last 3 days 👍');
  }
  const st = STATS[c] || {};
  if (st.loading && !st.days) return head + '<div class="load"><i></i></div>';
  return head + `<p class="mut" style="margin:0 0 6px">Session attendance over ${st.days || 0} school day(s).${/^(9th|10th)$/i.test(c) ? ' <b>CBSE counts attendance up to 1 January for the board exam.</b>' : ''}</p>` +
    crMsgList(date, 'low', D.low.map(([s, p]) => ({ s, c, why: `<span class="lowp">${p}%</span> attendance`, text: crAttText('low', s, c, { p }) })), 'Every child is at 75% or above 👍');
}
function vAttMore(c) {
  const T2 = [['kit', 'bill', 'Daily check', 'Diary · ID · book · dress'], ['stu', 'list', 'Students', "Each child's full report"], ['bulk', 'check', 'Levels', 'Reading, writing — whole class'], ['cal', 'note', 'Calendar', 'Month view, back days'], ['reg', 'print', 'Register', 'Monthly attendance register']];
  return `<div class="cr-more">${T2.map(([m, i, l, x]) => `<button onclick="DRAFT.att=null;DRAFT.reg=null;DRAFT.kit=null;go('att',{c:${jsq(c)},m:'${m}'})">${ic(i)}<b>${l}</b><small>${x}</small></button>`).join('')}</div>`;
}
if (typeof vAtt === 'function') {
  const _vAttCR = vAtt;
  vAtt = function () {
    try {
      const allowed = myCT(), all = [...new Set([...allowed, ...myClasses()])].filter(x => className().includes(x));
      if (!all.length) return _vAttCR.apply(this, arguments);
      const c = all.includes(P.c) ? P.c : (allowed[0] || all[0]);
      if (!allowed.includes(c)) return _vAttCR.apply(this, arguments);           // subject teacher: unchanged (Students view)
      const m = P.m || 'day', cur = m === 'rep' ? 'rep' : (m === 'more' || CR_MORE.includes(m)) ? 'more' : 'day';
      needStats(c);
      const pick = all.length > 1 ? `<div class="fld">Class${sel('attC', all.map(x => [x, 'Class ' + x + (allowed.includes(x) ? ' · class teacher' : '')]), c, "DRAFT.att=null;DRAFT.reg=null;DRAFT.rec=null;go('att',{c:this.value,m:'" + (cur === 'more' ? 'day' : cur) + "'})")}</div>` : '';
      const tabs = `<div class="cr-tabs">${[['day', '✅', 'Entry'], ['rep', '📊', 'Report & Message'], ['more', '⋯', 'More']].map(([k, i, l]) => `<button class="${k === cur ? 'on' : ''}" onclick="DRAFT.att=null;DRAFT.kit=null;go('att',{c:${jsq(c)},m:'${k}'})"><span>${i}</span>${l}</button>`).join('')}</div>`;
      // the attendance report card stays on top of Entry and the More screens, as before (not on Levels or one child's page)
      const rep = (cur === 'day' || (CR_MORE.includes(m) && m !== 'bulk' && !(m === 'stu' && P.sid))) ? attReport(c) : '';
      const body = m === 'rep' ? vAttRep(c) : m === 'more' ? vAttMore(c) : rep + (m === 'bulk' ? vBulk(c) : m === 'reg' ? vRegister(c) : m === 'pending' ? vPending(c) : m === 'cal' ? vAttCal(c) : m === 'kit' ? vKit(c) : m === 'stu' ? (P.sid ? vStuReport(c, P.sid) : vStudents(c)) : vAttDay(c));
      const back = CR_MORE.includes(m) ? `<button class="chip" style="margin:0 0 8px" onclick="go('att',{c:${jsq(c)},m:'more'})">← More</button>` : '';
      return pick + tabs + back + body;
    } catch (e) { console.error(e); return _vAttCR.apply(this, arguments); }
  };
}

// ---------------------------------------------------------------- HOMEWORK: last class's homework, check, report
function crNeedHw(force) {
  if (CR.hw && !force && (CR.hw.loading || Date.now() - CR.hw.at < 90000)) return CR.hw;
  const old = CR.hw; CR.hw = Object.assign({}, old || { last: {}, miss: {}, checks: {} }, { loading: !old, at: Date.now() });
  api('duty_api.php?a=hwsum').then(r => { CR.hw = Object.assign(r, { at: Date.now() }); softRender(); }).catch(e => { CR.hw = Object.assign({}, old || { last: {}, miss: {}, checks: {} }, { at: Date.now(), err: e.message }); softRender(); });
  return CR.hw;
}
function crMyCS() { const seen = new Set(), out = []; allocList().forEach(a => { const k = sylKey(a.c, a.s); if (!seen.has(k) && className().includes(a.c)) { seen.add(k); out.push({ c: a.c, s: a.s, k }); } }); return out; }
// The homework given in the last class of this class + subject (any teacher's diary), before today.
function crLastHw(c, s) {
  const t = todayISO();
  for (let i = 1; i <= 21; i++) { const d = addDays(t, -i), day = S.diary[d]; if (!day) continue; const hw = [];
    Object.entries(day).forEach(([tid, byB]) => Object.values(byB || {}).forEach(e => { if (e && e.cls === c && e.subj === s && String(e.hw || '').trim()) hw.push({ hw: String(e.hw).trim(), tid }); }));
    if (hw.length) return { date: d, hw: [...new Set(hw.map(x => x.hw))].join(' · '), tid: hw[0].tid };
  }
  return null;
}
function crHwState() {
  const H = crNeedHw(), t = todayISO();
  return crMyCS().map(x => { const L = crLastHw(x.c, x.s), last = (H.last || {})[x.k] || '', checkedToday = !!(H.checks || {})[x.k];
    return Object.assign(x, { L, last, checkedToday, pending: !!L && !(last > L.date) }); });
}
if (typeof sdToCheck === 'function') sdToCheck = function () { try { return crHwState().filter(x => x.pending).map(x => ({ date: todayISO(), c: x.c, s: x.s, hw: [x.L.hw] })); } catch (e) { return []; } };
function vHwCheck() {
  const list = crHwState(), H = CR.hw || {};
  if (!list.length) return '<div class="card empty">No subjects are given to you yet. The Principal sets them in the timetable.</div>';
  if (H.loading && !H.last) return '<div class="load"><i></i></div>';
  const pend = list.filter(x => x.pending), key = list.some(x => x.k === P.key) ? P.key : (pend[0] || list[0]).k, cur = list.find(x => x.k === key), t = todayISO();
  const top = `<div class="card cr-todo"><h3 style="margin:0 0 6px">📚 Homework to check ${pend.length ? `<span class="pill warn">${pend.length}</span>` : '<span class="pill ok">✓ all checked</span>'}</h3>
    <div class="cr-cs">${list.map(x => `<button class="${x.k === key ? 'on' : ''} ${x.pending ? 'p' : x.checkedToday ? 'd' : ''}" onclick="DRAFT.hc=null;go('diary',{m:'check',key:${jsq(x.k)}})"><b>Class ${esc(x.c)} · ${esc(x.s)}</b><small>${x.pending ? 'Given ' + esc(fmtDate(x.L.date)) + ' — to check' : x.checkedToday ? '✓ Checked today' : x.L ? '✓ Checked' : 'No homework given'}</small></button>`).join('')}</div></div>`;
  const c = cur.c, sb = cur.s, kids = studentsOf(c), att = attOf(t, c), ab = new Set((att && att.absent) || []);
  if (!cur.L && !cur.checkedToday) return top + `<div class="card"><p class="mut" style="margin:0">No homework was given in the last classes of Class ${esc(c)} ${esc(sb)} (3 weeks). Write it in the Diary — it comes here for the next class.</p></div>`;
  const prev = ((H.checks || {})[key] || {});
  if (!DRAFT.hc || DRAFT.hc.k !== key) { const rows = {}; kids.forEach(s => { const o = (prev.rows || {})[s.id]; rows[s.id] = ab.has(s.id) ? '' : (o && o.hw) || 'done'; }); DRAFT.hc = { k: key, rows, changed: false }; }
  const R = DRAFT.hc.rows, n = h => kids.filter(s => !ab.has(s.id) && R[s.id] === h).length, ph = needPhotos(c), miss = (H.miss || {})[key] || {};
  const hwTxt = cur.L ? cur.L.hw : prev.hw || '';
  return top + `<div class="card cr-check">
    <div class="cr-hw"><small>${cur.L ? 'Homework given on ' + esc(fmtLong(cur.L.date)) + (cur.L.tid && cur.L.tid !== me() ? ' by ' + esc(T(cur.L.tid).name) : '') : 'Homework'}</small><b>${esc(hwTxt || '—')}</b></div>
    ${cur.checkedToday && !cur.pending ? `<div class="ban inf" style="margin:8px 0"><div>Checked today${prev.by ? ' by ' + esc(prev.by) : ''}. You can correct it and Submit again.</div></div>` : ''}
    <div class="cr-cnt"><span class="pill ok">${n('done')} done</span><span class="pill bad">${n('not')} not done</span><span class="pill warn">${n('half')} half</span>${ab.size ? `<span class="pill">${kids.filter(s => ab.has(s.id)).length} absent</span>` : ''}</div>
    <div class="quick"><input id="hcQ" class="inp" inputmode="numeric" placeholder="Not done — roll no. e.g. 3, 7, 12" onkeydown="if(event.key==='Enter')crHcQuick()"><button class="btn s" onclick="crHcQuick()">Mark</button></div>
    <p class="mut hint">Everyone starts ✓ Done. Tap a child who did NOT do it (tap again = half, again = done).</p>
    <div class="tiles">${kids.map((s, i) => { const v = ab.has(s.id) ? 'ab' : R[s.id], pic = ph[s.id], m = miss[s.id] || 0;
      return `<button type="button" class="tile cr-t ${v === 'not' ? 'sA' : v === 'half' ? 'sL' : v === 'ab' ? 'cr-ab' : ''}" ${v === 'ab' ? 'disabled' : ''} onclick="crHcTap(${jsq(s.id)})"><span class="ph">${pic ? `<img src="${esc(pic.src)}" alt="" loading="lazy">` : `<i>${esc(initials(s.name))}</i>`}<em class="badge">${v === 'not' ? '✗' : v === 'half' ? '½' : v === 'ab' ? 'A' : '✓'}</em></span>
        <b>${esc(s.name)}</b><small>Roll ${esc(s.rollno || i + 1)}${m ? ` · <span class="lowp">${m}× not done</span>` : ''}</small></button>`; }).join('')}</div>
    <div class="sticky"><button class="btn go full submit" onclick="crHcSave(${jsq(c)},${jsq(sb)})">Submit · ${n('done')} done · ${n('not')} not done${n('half') ? ' · ' + n('half') + ' half' : ''}</button></div></div>`;
}
function crHcTap(id) { const R = DRAFT.hc.rows, o = ['done', 'not', 'half']; R[id] = o[(o.indexOf(R[id] || 'done') + 1) % 3]; DRAFT.hc.changed = true; render(); }
function crHcQuick() {
  const el = $('hcQ'); if (!el || !DRAFT.hc) return; const [c] = DRAFT.hc.k.split('|'), kids = studentsOf(c), nums = String(el.value).split(/[^0-9]+/).filter(Boolean).map(Number); let n = 0;
  nums.forEach(r => { const s = kids.find((x, i) => +(x.rollno || i + 1) === r); if (s && DRAFT.hc.rows[s.id] !== '') { DRAFT.hc.rows[s.id] = 'not'; n++; } });
  el.value = ''; DRAFT.hc.changed = true; toast(n ? n + ' marked not done.' : 'No roll number matched.'); render();
}
async function crHcSave(c, sb) {
  const key = sylKey(c, sb), L = crLastHw(c, sb), rows = {};
  Object.entries(DRAFT.hc.rows).forEach(([id, v]) => { if (v) rows[id] = { hw: v, cp: '' }; });
  if (!Object.keys(rows).length) return toast('Nobody to check — all absent?');
  try {
    await api('teacher_api.php?a=check', { date: todayISO(), cls: c, subj: sb, hw: L ? L.hw : '', rows });
    const nn = Object.values(rows).filter(x => x.hw !== 'done').length;
    DRAFT.hc = null; MYX = null; crNeedHw(true); toast(nn ? `Saved ✓ ${nn} did not do it — send the parents a message.` : 'Saved ✓ Everyone did the homework 👍');
    const nxt = crHwState().find(x => x.pending && x.k !== key);
    go('diary', nn ? { m: 'rep', f: 'today' } : nxt ? { m: 'check', key: nxt.k } : { m: 'check', key });
  } catch (e) { toast(e.message); }
}
function vHwRep() {
  const H = crNeedHw(), t = todayISO(), f = ['today', 'x3', 'pct'].includes(P.f) ? P.f : 'today', mine = crMyCS();
  if (H.loading && !H.last) return '<div class="load"><i></i></div>';
  const today = [], x3 = [];
  mine.forEach(x => { const ch = (H.checks || {})[x.k]; if (ch) Object.entries(ch.rows || {}).forEach(([sid, r]) => { const s = studentById(sid); if (s && (r.hw === 'not' || r.hw === 'half')) today.push({ s, c: x.c, subj: x.s, hw: r.hw, task: ch.hw }); });
    Object.entries((H.miss || {})[x.k] || {}).forEach(([sid, n]) => { const s = studentById(sid); if (s && n >= 3) x3.push({ s, c: x.c, subj: x.s, n }); }); });
  const pct = mine.map(x => { const ch = (H.checks || {})[x.k], last = (H.last || {})[x.k]; return { x, ch, last }; });
  const chips = [['today', 'Not done today', today.length], ['x3', '3+ times (30 days)', x3.length], ['pct', 'Class-wise', mine.length]];
  const head = `<div class="cr-sum">${chips.map(([k, l, n]) => `<button class="${k === f ? 'on' : ''}" onclick="go('diary',{m:'rep',f:'${k}'})"><b>${n}</b><span>${l}</span></button>`).join('')}</div>`;
  if (f === 'today') return head + crMsgList(t, 'hw', today.map(y => ({ s: y.s, c: y.c, subj: y.subj, why: (y.hw === 'half' ? 'Half done' : 'Not done') + (y.task ? ': “' + esc(y.task.slice(0, 60)) + '”' : ''), text: msgText('hw', y.s, y.c, { subj: y.subj, note: y.task, date: t }) })), 'Nobody is marked “not done” today. Check homework first — the list fills from it.');
  if (f === 'x3') return head + crMsgList(t, 'hw3', x3.sort((a, b) => b.n - a.n).map(y => ({ s: y.s, c: y.c, subj: y.subj, why: `<span class="lowp">${y.n} times</span> homework not done in 30 days`,
    text: `Dear Parent,\n${y.s.name} (Class ${y.c}) did not complete the ${y.subj} homework ${y.n} times in the last 30 days. Kindly check the homework at home every day and meet the teacher if needed.\n${y.s.name} ने पिछले 30 दिनों में ${y.n} बार ${y.subj} का गृहकार्य पूरा नहीं किया। कृपया रोज़ घर पर गृहकार्य देखें और ज़रूरत हो तो शिक्षक से मिलें।\n${crSign()}` })), 'No child has missed homework 3 or more times in 30 days 👍');
  return head + `<div class="card">${pct.map(({ x, ch, last }) => { const rows = ch ? Object.values(ch.rows || {}) : [], d = rows.filter(r => r.hw === 'done').length, tot = rows.filter(r => r.hw).length, p = tot ? Math.round(d / tot * 100) : null;
    return `<div class="cr-row"><div class="n"><b>Class ${esc(x.c)} · ${esc(x.s)}</b><small>${ch ? `Today: ${d}/${tot} did it` : last ? 'Last checked ' + esc(fmtDate(last)) : 'Not checked in 30 days'}</small></div>${p !== null ? `<div class="ring sm" style="--p:${p}"><span>${p}%</span></div>` : `<button class="btn s go" onclick="DRAFT.hc=null;go('diary',{m:'check',key:${jsq(x.k)}})">Check</button>`}</div>`; }).join('')}</div>`;
}
if (typeof vDiary === 'function') {
  vDiary = function () {
    const m = ['check', 'rep'].includes(P.m) ? P.m : 'write', d = P.date || todayISO();
    const n = (() => { try { return crHwState().filter(x => x.pending).length; } catch (e) { return 0; } })();
    const tabs = `<div class="cr-tabs">${[['write', '✍️', 'Diary'], ['check', '✅', 'Check homework' + (n ? ` <em class="sd-badge">${n}</em>` : '')], ['rep', '📊', 'Report & Message']].map(([k, i, l]) => `<button class="${k === m ? 'on' : ''}" onclick="DRAFT.hc=null;go('diary',{m:'${k}',date:'${d}'})"><span>${i}</span>${l}</button>`).join('')}</div>`;
    return tabs + (m === 'check' ? vHwCheck() : m === 'rep' ? vHwRep() : vDiaryWrite());
  };
}

// ---------------------------------------------------------------- DIARY: pre-filled, one-tap "Save as planned"
function crPlan(date, b) {     // topic from the lesson plan / yesterday / syllabus (same as the open card), CW + HW from it
  let tops = []; try { tops = (sdTopics(date, b) || {}).out || []; } catch (e) {}
  const topic = tops[0] ? String(tops[0][2]).trim() : ''; if (!topic) return null;
  const tp = topic.replace(/^Revision:\s*/i, '');
  return { topic, cw: `Explained “${tp}” with examples; questions done in the class copy.`, hw: `Read “${tp}” again and write the question-answers in the homework copy.` };
}
async function crQuickSave(date, bell) {
  const b = myBells(date).find(x => x.bell === bell); if (!b) return; const pl = crPlan(date, b);
  if (!pl) return go('diary', { date, bell });
  if (optOf(date, b)) return go('diary', { date, bell });
  const d = { cls: b.cls, subj: b.subj, topic: pl.topic.slice(0, 200), hw: pl.hw.slice(0, 500), classwork: pl.cw.slice(0, 500), books: '', revision: '', hwMinutes: 15, hwDate: nextSchoolDay(date, b.cls) || date, arr: !!b.arr, at: Date.now() };
  put('diary', date + '|' + me() + '|' + bell, d, 'Diary', `${BELLS[bell].n} bell, Class ${b.cls} ${b.subj}: "${d.topic}" • HW: ${d.hw} — Teacher App (Save as planned)`);
  toast('Saved ✓ ' + BELLS[bell].n + ' bell · Class ' + b.cls + ' — tap it to change anything.'); render();
}
if (typeof sdLine === 'function') {
  const _sdLineCR = sdLine;
  sdLine = function (date, b) {
    const html = _sdLineCR.apply(this, arguments);
    try {
      if (diaryEntry(date, b.bell) || ((S.signed[date] || {})[me()]) || optOf(date, b)) return html;
      const later = date === todayISO() && !bellOver(date, b.bell) && toMin(BELLS[b.bell].s) > nowMin(); if (later) return html;
      const pl = crPlan(date, b); if (!pl) return html;
      return html.replace(/<\/div><\/div>\s*$/, `</div><div class="cr-quick" onclick="event.stopPropagation()"><small>📖 ${esc(pl.topic.slice(0, 60))}</small><button class="btn s go" onclick="event.stopPropagation();crQuickSave('${date}',${b.bell})">✔ Save as planned</button></div></div>`);
    } catch (e) { return html; }
  };
}
if (typeof sdCard === 'function') {
  const _sdCardCR = sdCard;
  sdCard = function (date, b) {
    let html = _sdCardCR.apply(this, arguments);
    try {
      const e = diaryEntry(date, b.bell); if (e) return html; const pl = crPlan(date, b); if (!pl) return html; const id = 'b' + b.bell;
      html = html.replace(new RegExp(`(id="${id}-cw"[^>]*>)(</textarea>)`), `$1${esc(pl.cw)}$2`).replace(new RegExp(`(id="${id}-h"[^>]*>)(</textarea>)`), `$1${esc(pl.hw)}$2`);
      html = html.replace('<div class="dform">', '<div class="dform"><p class="ban inf" style="margin:0 0 8px">Topic, class work and homework are filled from your plan. Change anything, then Save.</p>');
    } catch (e) {}
    return html;
  };
}

(function () {
  const st = document.createElement('style');
  st.textContent = `.cr-tabs{display:grid;grid-template-columns:repeat(3,1fr);gap:6px;margin:0 0 12px}.cr-tabs button{border:0;border-radius:14px;padding:10px 4px;background:#fff;box-shadow:0 1px 0 #E6EBF3;font:600 12.5px/1.2 inherit;color:#445;display:flex;flex-direction:column;align-items:center;gap:3px;cursor:pointer}
  .cr-tabs button span{font-size:18px}.cr-tabs button.on{background:var(--a,#17324D);color:#fff;box-shadow:0 4px 14px rgba(0,0,0,.15)}.cr-tabs .sd-badge{font-style:normal}
  .cr-sum{display:grid;grid-template-columns:repeat(3,1fr);gap:6px;margin:0 0 10px}.cr-sum button{border:1.5px solid #E6EBF3;background:#fff;border-radius:14px;padding:10px 6px;cursor:pointer;display:flex;flex-direction:column;align-items:center;gap:2px}
  .cr-sum b{font-size:22px;color:#17324D}.cr-sum span{font-size:11.5px;color:#556;text-align:center}.cr-sum button.on{border-color:var(--a,#17324D);background:var(--tint,#EEF2F5)}
  .cr-list .cr-lh{display:flex;justify-content:space-between;align-items:center;margin:0 0 8px}.cr-list .btn.full{margin:0 0 8px}
  .cr-row{display:flex;justify-content:space-between;align-items:center;gap:8px;padding:9px 0;border-top:1px solid #EEF1F4}.cr-row .n{min-width:0}.cr-row .n small{display:block;color:#667;font-size:12px}.cr-row .why{color:#334}.cr-row.sent{opacity:.65}
  .cr-more{display:grid;grid-template-columns:1fr 1fr;gap:8px}.cr-more button{border:1.5px solid #E6EBF3;background:#fff;border-radius:16px;padding:14px 10px;text-align:left;cursor:pointer;display:flex;flex-direction:column;gap:4px}.cr-more b{font-size:14px}.cr-more small{color:#667;font-size:11.5px}
  .cr-cs{display:flex;flex-direction:column;gap:6px}.cr-cs button{border:1.5px solid #E6EBF3;background:#fff;border-radius:12px;padding:8px 10px;text-align:left;cursor:pointer}.cr-cs button.p{border-color:#F2C14E}.cr-cs button.on{border-color:var(--a,#17324D);background:var(--tint,#EEF2F5)}
  .cr-cs b{display:block;font-size:13px}.cr-cs small{color:#667;font-size:11.5px}.cr-cs button.d small{color:#12805C}
  .cr-hw{background:#FFF8E6;border:1.5px solid #F2C14E;border-radius:14px;padding:10px 12px}.cr-hw small{display:block;color:#7A5B00;font-size:11.5px;margin-bottom:3px}.cr-hw b{font-size:15px;line-height:1.35}
  .cr-cnt{display:flex;gap:6px;flex-wrap:wrap;margin:10px 0 8px}.tile.cr-ab{opacity:.45}.tile.cr-t .badge{background:var(--ok)}.tile.cr-t.sA .badge{background:var(--bad)}.tile.cr-t.sL .badge{background:#B45309}
  .cr-quick{display:flex;justify-content:space-between;align-items:center;gap:8px;margin-top:8px;padding-top:8px;border-top:1px dashed #E6EBF3}.cr-quick small{color:#556;font-size:12px;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
  .ring.sm{width:44px;height:44px}`;
  document.head.appendChild(st);
})();
