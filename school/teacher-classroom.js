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
// ---- shared look (27 Nov 2026, owner: "make more improve in design and format") ----
// tabs = one segmented bar in the tab colour; stats = coloured count cards (red / amber / violet / green);
// lists = avatar, name, coloured reason pill, round WhatsApp button, a sent-progress bar and "Send next" at the bottom.
function crTabs(items, cur, js) {
  return `<div class="cr-tabs" role="tablist">${items.map(([k, i, l, badge]) => `<button role="tab" aria-selected="${k === cur}" class="${k === cur ? 'on' : ''}" onclick="${js(k)}">${ic(i)}<span>${l}</span>${badge ? `<em>${badge}</em>` : ''}</button>`).join('')}</div>`;
}
function crStats(items, cur, js) {
  return `<div class="cr-sum">${items.map(([k, l, n, tone, sub]) => `<button class="t-${tone} ${k === cur ? 'on' : ''}" onclick="${js(k)}"><b>${n}</b><span>${l}</span>${sub ? `<small>${sub}</small>` : ''}</button>`).join('')}</div>`;
}
function crEmpty(icon, title, sub) { return `<div class="card cr-empty"><div class="ei">${icon}</div><b>${title}</b>${sub ? `<small>${sub}</small>` : ''}</div>`; }
function crHead(title, sub) { return `<div class="cr-head"><b>${title}</b>${sub ? `<small>${sub}</small>` : ''}</div>`; }
function crAva(s) { const ph = needPhotos(s.cls || ''), pic = ph && ph[s.id]; return `<span class="cr-av">${pic ? `<img src="${esc(pic.src)}" alt="" loading="lazy">` : esc(initials(s.name))}</span>`; }
// One row = child + why + WhatsApp + ✓ sent. items: [{s, c, why, tone, text, subj}]
function crMsgList(date, type, items, emptyTitle, emptySub) {
  if (!items.length) return crEmpty('🎉', emptyTitle, emptySub);
  const withPhone = items.filter(x => x.s.phone), sent = withPhone.filter(x => crSent(date, type, x.s.id, x.subj)), unsent = withPhone.filter(x => !crSent(date, type, x.s.id, x.subj));
  const nx = unsent[0], pct = withPhone.length ? Math.round(sent.length / withPhone.length * 100) : 100;
  return `<div class="card cr-list">
    <div class="cr-prog"><div><b>${sent.length}/${withPhone.length}</b> parents told</div><span class="pill ${unsent.length ? 'warn' : 'ok'}">${unsent.length ? unsent.length + ' left' : '✓ all sent'}</span></div>
    <div class="cr-bar"><i style="width:${pct}%"></i></div>
    ${items.map(x => { const ok = crSent(date, type, x.s.id, x.subj);
      return `<div class="cr-row ${ok ? 'sent' : ''}">${crAva(x.s)}<div class="n"><b>${esc(x.s.name)}</b><small>Class ${esc(x.c)} · Roll ${esc(crRoll(x.c, x.s))}${x.subj ? ' · ' + esc(x.subj) : ''}</small><span class="cr-why t-${x.tone || 'red'}">${x.why}</span></div>
        ${x.s.phone ? (ok ? `<a class="cr-sentb" target="_blank" rel="noopener" href="${wa(x.s.phone, x.text)}" onclick="crMark('${date}','${type}',${jsq(x.s.id)},${jsq(x.subj || '')})" aria-label="Send again">✓ Sent</a>` : `<a class="cr-wab" target="_blank" rel="noopener" href="${wa(x.s.phone, x.text)}" onclick="crMark('${date}','${type}',${jsq(x.s.id)},${jsq(x.subj || '')})" aria-label="WhatsApp ${esc(x.s.name)}'s parent">${ic('wa')}</a>`) : '<small class="mut">No mobile</small>'}</div>`; }).join('')}
    ${nx ? `<div class="cr-next"><a class="btn wa full" target="_blank" rel="noopener" href="${wa(nx.s.phone, nx.text)}" onclick="crMark('${date}','${type}',${jsq(nx.s.id)},${jsq(nx.subj || '')})">${ic('wa')} Send next · ${esc(nx.s.name)} <small>(${sent.length + 1}/${withPhone.length})</small></a></div>` : ''}
    <p class="cr-foot">🔒 Each message goes to the parent alone — never to the class group.</p></div>`;
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
  const head = crHead('Class ' + esc(c) + ' · ' + D.list.length + ' students', esc(fmtLong(date))) + crStats([['today', 'Absent today', D.today ? D.today.length : '—', 'red'], ['abs3', '3 days in a row', D.d3.length === 3 ? D.three.length : '—', 'amber'], ['low', 'Below 75%', D.low.length, 'violet']], f, k => `go('att',{c:${jsq(c)},m:'rep',f:'${k}'})`);
  if (f === 'today') {
    if (!D.today) return head + `<div class="card cr-empty"><div class="ei">🕘</div><b>Today's attendance is not marked yet</b><small>Mark it first — this list fills by itself.</small><button class="btn go" style="margin-top:10px" onclick="go('att',{c:${jsq(c)},m:'day'})">Mark attendance</button></div>`;
    return head + crMsgList(date, 'abs', D.today.map(s => { const lv = (D.a.st || {})[s.id] === 'L'; return { s, c, why: lv ? 'On leave today' : 'Absent today', tone: lv ? 'violet' : 'red', text: crAttText('abs', s, c, {}) }; }), `Everyone is present in Class ${esc(c)} today`, 'Nothing to send.');
  }
  if (f === 'abs3') {
    if (D.d3.length < 3) return head + crEmpty('📅', 'Need 3 days of attendance', `Found ${D.d3.length} marked day(s) so far.`);
    const days = crDays(D.d3);
    return head + `<p class="cr-note">Last 3 marked days: <b>${esc(days)}</b></p>` + crMsgList(date, 'abs3', D.three.map(s => ({ s, c, why: 'Absent all 3 days', tone: 'amber', text: crAttText('abs3', s, c, { days }) })), 'No child missed all of the last 3 days', 'Nothing to send.');
  }
  const st = STATS[c] || {};
  if (st.loading && !st.days) return head + '<div class="load"><i></i></div>';
  return head + `<p class="cr-note">Session attendance over <b>${st.days || 0}</b> school day(s).${/^(9th|10th)$/i.test(c) ? ' <b class="cbse">CBSE counts attendance up to 1 January for the board exam.</b>' : ''}</p>` +
    crMsgList(date, 'low', D.low.map(([s, p]) => ({ s, c, why: `${p}% attendance`, tone: 'violet', text: crAttText('low', s, c, { p }) })), 'Every child is at 75% or above', 'Nothing to send.');
}
function vAttMore(c) {
  const T2 = [['kit', 'bill', 'Daily check', 'Diary · ID · book · dress', 'blue'], ['stu', 'list', 'Students', "Each child's full report", 'green'], ['bulk', 'check', 'Levels', 'Reading, writing — whole class', 'amber'], ['cal', 'note', 'Calendar', 'Month view, back days', 'violet'], ['reg', 'print', 'Register', 'Monthly attendance register', 'red']];
  return `<div class="cr-more">${T2.map(([m, i, l, x, tone]) => `<button onclick="DRAFT.att=null;DRAFT.reg=null;DRAFT.kit=null;go('att',{c:${jsq(c)},m:'${m}'})"><span class="mi t-${tone}">${ic(i)}</span><b>${l}</b><small>${x}</small></button>`).join('')}</div>`;
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
      const D0 = crAttData(c), todo = (D0.today ? D0.today.filter(s => s.phone && !crSent(D0.t, 'abs', s.id, '')).length : 0);
      const tabs = crTabs([['day', 'check', 'Entry'], ['rep', 'wa', 'Report & Share', todo || ''], ['more', 'list', 'More']], cur, k => `DRAFT.att=null;DRAFT.kit=null;go('att',{c:${jsq(c)},m:'${k}'})`);
      // the attendance report card stays on top of Entry and the More screens, as before (not on Levels or one child's page)
      const rep = (cur === 'day' || (CR_MORE.includes(m) && m !== 'bulk' && !(m === 'stu' && P.sid))) ? attReport(c) : '';
      const body = m === 'rep' ? vAttRep(c) : m === 'more' ? vAttMore(c) : rep + (m === 'bulk' ? vBulk(c) : m === 'reg' ? vRegister(c) : m === 'pending' ? vPending(c) : m === 'cal' ? vAttCal(c) : m === 'kit' ? vKit(c) : m === 'stu' ? (P.sid ? vStuReport(c, P.sid) : vStudents(c)) : vAttDay(c));
      const back = CR_MORE.includes(m) ? `<button class="cr-back" onclick="go('att',{c:${jsq(c)},m:'more'})">${ic('back')} More</button>` : '';
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
  const top = crHead(pend.length ? `📚 ${pend.length} homework to check` : '✅ All homework checked', 'Tap a class to check its last homework') +
    `<div class="cr-cs">${list.map(x => `<button class="${x.k === key ? 'on' : ''} ${x.pending ? 'p' : x.checkedToday ? 'd' : 'n'}" onclick="DRAFT.hc=null;go('diary',{m:'check',key:${jsq(x.k)}})"><i></i><b>${esc(x.c)} · ${esc(x.s)}</b><small>${x.pending ? 'Given ' + esc(fmtDate(x.L.date).replace(/^\w+,?\s*/, '')) : x.checkedToday ? 'Checked today' : x.L ? 'Checked' : 'No homework'}</small></button>`).join('')}</div>`;
  const c = cur.c, sb = cur.s, kids = studentsOf(c), att = attOf(t, c), ab = new Set((att && att.absent) || []);
  if (!cur.L && !cur.checkedToday) return top + crEmpty('📝', `No homework given in Class ${esc(c)} ${esc(sb)}`, 'Write it in the Diary — it comes here for the next class.');
  const prev = ((H.checks || {})[key] || {});
  if (!DRAFT.hc || DRAFT.hc.k !== key) { const rows = {}; kids.forEach(s => { const o = (prev.rows || {})[s.id]; rows[s.id] = ab.has(s.id) ? '' : (o && o.hw) || 'done'; }); DRAFT.hc = { k: key, rows, changed: false }; }
  const R = DRAFT.hc.rows, n = h => kids.filter(s => !ab.has(s.id) && R[s.id] === h).length, ph = needPhotos(c), miss = (H.miss || {})[key] || {};
  const hwTxt = cur.L ? cur.L.hw : prev.hw || '';
  return top + `<div class="card cr-check">
    <div class="cr-hw"><div class="tag">Class ${esc(c)} · ${esc(sb)}</div><small>${cur.L ? 'Homework of the last class · ' + esc(fmtLong(cur.L.date)) + (cur.L.tid && cur.L.tid !== me() ? ' · by ' + esc(T(cur.L.tid).name) : '') : 'Homework'}</small><b>${esc(hwTxt || '—')}</b></div>
    ${cur.checkedToday && !cur.pending ? `<div class="ban inf" style="margin:8px 0"><div>Checked today${prev.by ? ' by ' + esc(prev.by) : ''}. You can correct it and Submit again.</div></div>` : ''}
    <div class="cr-cnt"><div class="t-green"><b>${n('done')}</b>Done</div><div class="t-red"><b>${n('not')}</b>Not done</div><div class="t-amber"><b>${n('half')}</b>Half</div><div class="t-grey"><b>${kids.filter(s => ab.has(s.id)).length}</b>Absent</div></div>
    <div class="quick"><input id="hcQ" class="inp" inputmode="numeric" placeholder="Not done — roll no. e.g. 3, 7, 12" onkeydown="if(event.key==='Enter')crHcQuick()"><button class="btn s" onclick="crHcQuick()">Mark</button></div>
    <p class="cr-hint">👆 Everyone starts <b>✓ Done</b>. Tap a child who did <b>not</b> do it · tap again = half.</p>
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
  const H = crNeedHw(), t = todayISO(), mine = crMyCS();
  if (!P.f) { const G0 = crGiven(t); P.f = G0.pending.length ? 'diary' : 'today'; }
  const f = ['today', 'x3', 'pct'].includes(P.f) ? P.f : 'today';
  if (H.loading && !H.last) return '<div class="load"><i></i></div>';
  const today = [], x3 = [];
  mine.forEach(x => { const ch = (H.checks || {})[x.k]; if (ch) Object.entries(ch.rows || {}).forEach(([sid, r]) => { const s = studentById(sid); if (s && (r.hw === 'not' || r.hw === 'half')) today.push({ s, c: x.c, subj: x.s, hw: r.hw, task: ch.hw }); });
    Object.entries((H.miss || {})[x.k] || {}).forEach(([sid, n]) => { const s = studentById(sid); if (s && n >= 3) x3.push({ s, c: x.c, subj: x.s, n }); }); });
  const pct = mine.map(x => { const ch = (H.checks || {})[x.k], last = (H.last || {})[x.k]; return { x, ch, last }; });
  const G = crGiven(t), fG = ['diary', 'share', 'catch'].includes(P.f);
  if (fG) return crHead('Homework given today', esc(fmtLong(t))) + crGivenStats(G, P.f) + (P.f === 'share' ? crShareView(G) : P.f === 'catch' ? crCatchView(G) : crDiaryView(G)) + crCheckedStats(today, x3, mine, '');
  const head = crHead('Homework checked', esc(fmtLong(t))) + crGivenStats(G, '') + crCheckedStats(today, x3, mine, f);
  if (f === 'today') return head + crMsgList(t, 'hw', today.map(y => ({ s: y.s, c: y.c, subj: y.subj, tone: y.hw === 'half' ? 'amber' : 'red', why: (y.hw === 'half' ? 'Half done' : 'Not done') + (y.task ? ' · ' + esc(y.task.slice(0, 50)) : ''), text: msgText('hw', y.s, y.c, { subj: y.subj, note: y.task, date: t }) })), 'Nobody is marked “not done” today', 'Check homework first — this list fills from it.');
  if (f === 'x3') return head + crMsgList(t, 'hw3', x3.sort((a, b) => b.n - a.n).map(y => ({ s: y.s, c: y.c, subj: y.subj, tone: 'amber', why: `${y.n} times not done in 30 days`,
    text: `Dear Parent,\n${y.s.name} (Class ${y.c}) did not complete the ${y.subj} homework ${y.n} times in the last 30 days. Kindly check the homework at home every day and meet the teacher if needed.\n${y.s.name} ने पिछले 30 दिनों में ${y.n} बार ${y.subj} का गृहकार्य पूरा नहीं किया। कृपया रोज़ घर पर गृहकार्य देखें और ज़रूरत हो तो शिक्षक से मिलें।\n${crSign()}` })), 'No child missed homework 3+ times', 'Nothing to send.');
  return head + `<div class="card">${pct.map(({ x, ch, last }) => { const rows = ch ? Object.values(ch.rows || {}) : [], d = rows.filter(r => r.hw === 'done').length, tot = rows.filter(r => r.hw).length, p = tot ? Math.round(d / tot * 100) : null;
    return `<div class="cr-row"><div class="n"><b>Class ${esc(x.c)} · ${esc(x.s)}</b><small>${ch ? `Today: ${d} of ${tot} did it` : last ? 'Last checked ' + esc(fmtDate(last)) : 'Not checked in 30 days'}</small>${p !== null ? `<div class="cr-bar sm"><i class="${p < 60 ? 'lo' : p < 85 ? 'mid' : ''}" style="width:${p}%"></i></div>` : ''}</div>${p !== null ? `<b class="cr-pct ${p < 60 ? 'lo' : p < 85 ? 'mid' : ''}">${p}%</b>` : `<button class="btn s go" onclick="DRAFT.hc=null;go('diary',{m:'check',key:${jsq(x.k)}})">Check</button>`}</div>`; }).join('')}</div>`;
}

// ---------------------------------------------------------------- HOMEWORK GIVEN: Entry → Report → Share (27 Nov 2026)
// owner: "class work homework assign karne ka bhi features improve karna chahiye — entry, report, share jaisa".
// Report & Share, first group: Diary (my bells today: written / not, with the homework given) · Class group (one message per
// class with every subject's CW + HW of today — the class teacher gets the whole class, others their own subjects) ·
// Absent catch-up (children absent today in a class I wrote a diary for → parent gets today's CW + HW; sent mark 'catch').
function crGiven(date) {
  const bells = myBells(date), written = [], pending = [];
  bells.forEach(b => { const e = diaryEntry(date, b.bell); (e ? written : pending).push({ b, e }); });
  const cls = [...new Set([...myCT().filter(c => isWorkDay(date, c)), ...written.map(x => x.e.cls)])].filter(c => className().includes(c));
  const share = cls.map(c => { const ct = myCT().includes(c), items = [];
    Object.entries(S.diary[date] || {}).forEach(([tid, byB]) => Object.values(byB || {}).forEach(e => { if (e && e.cls === c && (e.hw || e.classwork) && (ct || tid === me())) items.push(e); }));
    return { c, ct, items }; }).filter(x => x.items.length);
  const catchL = []; share.forEach(x => { const a = attOf(date, x.c); ((a && a.absent) || []).forEach(id => { const s = studentById(id); if (s) catchL.push({ s, c: x.c, items: x.items }); }); });
  return { date, bells, written, pending, share, catchL };
}
function crGivenStats(G, cur) {
  return `<p class="cr-grp">✍️ Homework given</p>` + crStats([['diary', 'Bells written', `${G.written.length}/${G.bells.length}`, G.pending.length ? 'red' : 'green'], ['share', 'Class groups', G.share.length, 'blue'], ['catch', 'Absent catch-up', G.catchL.length, 'violet']], cur, k => `go('diary',{m:'rep',f:'${k}'})`);
}
function crCheckedStats(today, x3, mine, cur) {
  return `<p class="cr-grp">✅ Homework checked</p>` + crStats([['today', 'Not done today', today.length, 'red'], ['x3', '3+ times in 30 days', x3.length, 'amber'], ['pct', 'Class-wise', mine.length, 'green']], cur, k => `go('diary',{m:'rep',f:'${k}'})`);
}
function crDiaryView(G) {
  if (!G.bells.length) return crEmpty('🌤️', 'No bells for you today', 'Nothing to write.');
  const old = (typeof diaryPending === 'function' ? diaryPending(6) : []).filter(x => x.d !== G.date);
  return `<div class="card cr-list">${G.bells.map(b => { const e = diaryEntry(G.date, b.bell), B = BELLS[b.bell] || {};
    return `<div class="cr-row">${`<span class="cr-bell ${e ? 'ok' : ''}"><b>${esc(B.n.replace(/\D/g, ''))}</b>${esc(t12(B.s).replace(' ', ''))}</span>`}<div class="n"><b>Class ${esc(b.cls)} · ${esc(b.subj)}${b.arr ? ' <small class="arrp">arrangement</small>' : ''}</b>${e ? `<small>📖 ${esc(e.topic || '')}</small><span class="cr-why t-${e.hw ? 'green' : 'grey'}">${e.hw ? 'HW: ' + esc(e.hw.slice(0, 48)) : 'No homework'}</span>` : `<span class="cr-why t-red">Not written</span>`}</div>
      <button class="btn s ${e ? 'l' : 'go'}" onclick="go('diary',{date:'${G.date}',bell:${b.bell}})">${e ? 'Edit' : 'Write'}</button></div>`; }).join('')}
    ${old.length ? `<p class="cr-foot" style="text-align:left">Older days still empty: ${old.map(x => `<button class="chip warn" onclick="go('diary',{date:'${x.d}'})">${esc(fmtDate(x.d))} · ${x.n}</button>`).join(' ')}</p>` : ''}</div>`;
}
function crClassText(c, date, items, ct) {
  return `*Class ${c} — ${fmtLong(date)}*\n` + items.map(e => `• *${e.subj}*${e.classwork ? '\n   CW: ' + e.classwork : ''}${e.hw ? '\n   HW: ' + e.hw : ''}${e.books ? '\n   Bring: ' + e.books : ''}`).join('\n') + `\n${crSign()}`;
}
function crShareView(G) {
  if (!G.share.length) return crEmpty('📝', 'No class work or homework written today', 'Write the diary first — the class message builds itself.');
  return G.share.map(x => `<div class="card cr-share"><div class="cr-prog"><div><b>Class ${esc(x.c)}</b> · ${x.items.length} subject${x.items.length > 1 ? 's' : ''}</div>${x.ct ? '<span class="pill">Class teacher · whole class</span>' : '<span class="pill">Your subjects</span>'}</div>
    ${x.items.map(e => `<div class="cr-sh"><b>${esc(e.subj)}</b>${e.classwork ? `<small><i>CW</i>${esc(e.classwork)}</small>` : ''}${e.hw ? `<small><i class="h">HW</i>${esc(e.hw)}</small>` : ''}</div>`).join('')}
    <a class="btn wa full cr-gb" target="_blank" rel="noopener" href="${wa('', crClassText(x.c, G.date, x.items, x.ct))}">${ic('wa')} Send to Class ${esc(x.c)} parents' group</a></div>`).join('') + `<p class="cr-foot">Only class work and homework go to the group — no child's name.</p>`;
}
function crCatchView(G) {
  return crMsgList(G.date, 'catch', G.catchL.map(y => ({ s: y.s, c: y.c, tone: 'violet', why: 'Absent today · ' + y.items.length + ' subject' + (y.items.length > 1 ? 's' : ''),
    text: `Dear Parent,\n${y.s.name} was absent today. Here is today's work of Class ${y.c} so that nothing is missed:\n` + y.items.map(e => `• ${e.subj}${e.classwork ? ' — CW: ' + e.classwork : ''}${e.hw ? ' — HW: ' + e.hw : ''}`).join('\n') + `\nआज ${y.s.name} अनुपस्थित था/थी। ऊपर आज का कक्षा कार्य और गृहकार्य है, कृपया घर पर पूरा करवाएँ।\n${crSign()}` })), 'No absent child in the classes you wrote today', 'Nothing to send.');
}

if (typeof vDiary === 'function') {
  vDiary = function () {
    const m = ['check', 'rep'].includes(P.m) ? P.m : 'write', d = P.date || todayISO();
    const n = (() => { try { return crHwState().filter(x => x.pending).length; } catch (e) { return 0; } })();
    const tabs = crTabs([['write', 'book', 'Diary'], ['check', 'check', 'Check HW', n || ''], ['rep', 'wa', 'Report & Share']], m, k => `DRAFT.hc=null;go('diary',{m:'${k}',date:'${d}'})`);
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
  st.textContent = `
  .cr-tabs{display:flex;gap:3px;background:#fff;border-radius:14px;padding:3px;margin:0 0 10px;box-shadow:0 2px 12px rgba(16,40,36,.06)}
  .cr-tabs button{flex:1;position:relative;border:0;background:transparent;border-radius:11px;min-height:40px;padding:5px 4px;font:600 11.5px/1.1 inherit;color:var(--muted);display:flex;align-items:center;justify-content:center;gap:4px;cursor:pointer;transition:background .18s,color .18s;white-space:nowrap}
  .cr-tabs button svg.i{width:15px;height:15px;flex-shrink:0}.cr-tabs button.on{background:var(--a);color:#fff;box-shadow:0 4px 12px var(--hshadow)}
  .cr-tabs button em{position:static;margin-left:2px;min-width:17px;height:17px;border-radius:9px;background:var(--bad);color:#fff;font:700 10px/17px inherit;font-style:normal;padding:0 4px}
  .cr-head{display:flex;flex-direction:column;margin:2px 2px 8px}.cr-head b{font-size:15px;color:var(--ink)}.cr-head small{font-size:11.5px;color:var(--muted)}
  .cr-sum{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin:0 0 10px}
  .cr-sum button{position:relative;border:2px solid transparent;border-radius:14px;padding:8px 4px 7px;cursor:pointer;display:flex;flex-direction:column;align-items:center;gap:2px;transition:transform .12s}
  .cr-sum button:active{transform:scale(.97)}.cr-sum b{font-size:20px;line-height:1.05;font-weight:800}.cr-sum span{font-size:10.5px;font-weight:600;text-align:center;line-height:1.2}
  .cr-sum button.on{box-shadow:0 6px 16px rgba(0,0,0,.08)}.cr-sum button.on::after{content:'';position:absolute;bottom:-7px;left:50%;margin-left:-6px;border:6px solid transparent;border-top-color:currentColor;border-bottom:0}
  .t-red{background:#FDE8EF;color:#B4234F}.t-red.on{border-color:#E8799B}.t-amber{background:#FFF4E2;color:#A15C07}.t-amber.on{border-color:#F2B45A}
  .t-violet{background:#F1EAFE;color:#6D28D9}.t-violet.on{border-color:#B79AF5}.t-green{background:#E7F7EC;color:#15803D}.t-green.on{border-color:#7BCB97}
  .t-blue{background:#E6F1FD;color:#1D5FB8}.t-grey{background:#F1F4F3;color:#5B6763}
  .cr-note{font-size:12px;color:var(--muted);margin:0 2px 8px}.cr-note .cbse{display:block;color:#6D28D9;margin-top:2px}
  .cr-list{padding:12px 14px}.cr-prog{display:flex;justify-content:space-between;align-items:center;font-size:12.5px;color:var(--muted)}.cr-prog b{font-size:16px;color:var(--ink)}
  .cr-bar{height:6px;border-radius:3px;background:#EEF2F1;overflow:hidden;margin:6px 0 4px}.cr-bar i{display:block;height:100%;border-radius:3px;background:var(--ok);transition:width .3s}
  .cr-bar.sm{height:5px;margin:5px 0 0;max-width:180px}.cr-bar i.mid{background:#E59A1A}.cr-bar i.lo{background:var(--bad)}
  .cr-row{display:flex;align-items:center;gap:10px;padding:10px 0;border-top:1px solid var(--line)}.cr-row:first-of-type{border-top:0}
  .cr-row .n{flex:1;min-width:0}.cr-row .n b{display:block;font-size:13.5px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.cr-row .n small{display:block;color:var(--faint);font-size:11px;margin-top:1px}
  .cr-why{display:inline-block;margin-top:4px;font-size:11px;font-weight:600;padding:2px 8px;border-radius:999px;max-width:100%;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
  .cr-row.sent .n,.cr-row.sent .cr-av{opacity:.55}
  .cr-av{width:36px;height:36px;flex-shrink:0;border-radius:50%;background:var(--tint);color:var(--a);font-weight:700;font-size:14px;display:flex;align-items:center;justify-content:center;overflow:hidden}.cr-av img{width:100%;height:100%;object-fit:cover}
  .cr-wab{width:36px;height:36px;flex-shrink:0;border-radius:50%;background:#25D366;color:#fff;display:flex;align-items:center;justify-content:center;box-shadow:0 4px 10px rgba(37,211,102,.35)}.cr-wab svg.i{width:18px;height:18px}
  .cr-sentb{flex-shrink:0;font-size:11.5px;font-weight:700;color:var(--ok);background:var(--okbg);padding:6px 10px;border-radius:999px;text-decoration:none}
  .cr-next{position:sticky;bottom:8px;margin-top:10px}.cr-next .btn{box-shadow:0 6px 14px rgba(37,211,102,.3);min-height:40px;font-size:13px}.cr-next small{opacity:.85;font-weight:600}
  .cr-foot{font-size:11px;color:var(--faint);margin:10px 0 0;text-align:center}
  .cr-empty{text-align:center;padding:26px 16px}.cr-empty .ei{font-size:34px;margin-bottom:6px}.cr-empty b{display:block;font-size:14.5px}.cr-empty small{display:block;color:var(--muted);font-size:12px;margin-top:3px}
  .cr-more{display:grid;grid-template-columns:1fr 1fr;gap:10px}.cr-more button{border:0;background:#fff;border-radius:14px;padding:11px 11px;text-align:left;cursor:pointer;display:flex;flex-direction:column;gap:4px;box-shadow:0 2px 12px rgba(16,40,36,.06)}
  .cr-more .mi{width:32px;height:32px;border-radius:12px;display:flex;align-items:center;justify-content:center;margin-bottom:4px}.cr-more .mi svg.i{width:20px;height:20px}.cr-more b{font-size:14px;color:var(--ink)}.cr-more small{color:var(--muted);font-size:11.5px;line-height:1.3}
  .cr-back{display:inline-flex;align-items:center;gap:4px;border:0;background:#fff;color:var(--a);font-weight:600;font-size:12.5px;border-radius:999px;padding:6px 12px 6px 8px;margin:0 0 10px;box-shadow:0 2px 8px rgba(16,40,36,.06);cursor:pointer}.cr-back svg.i{width:15px;height:15px}
  .cr-cs{display:flex;gap:8px;overflow-x:auto;padding:2px 2px 10px;margin:0 -2px 4px;scrollbar-width:none}.cr-cs::-webkit-scrollbar{display:none}
  .cr-cs button{flex:0 0 auto;min-width:132px;border:2px solid transparent;background:#fff;border-radius:14px;padding:9px 12px 9px 26px;text-align:left;cursor:pointer;position:relative;box-shadow:0 2px 10px rgba(16,40,36,.06)}
  .cr-cs button i{position:absolute;left:11px;top:14px;width:8px;height:8px;border-radius:50%;background:var(--faint)}.cr-cs button.p i{background:#F59E0B;box-shadow:0 0 0 3px #FFF1D6}.cr-cs button.d i{background:var(--ok)}
  .cr-cs button.on{border-color:var(--a)}.cr-cs b{display:block;font-size:13px;color:var(--ink);white-space:nowrap}.cr-cs small{color:var(--muted);font-size:11px}.cr-cs button.p small{color:#A15C07;font-weight:600}.cr-cs button.d small{color:var(--ok)}
  .cr-check{padding:12px}.cr-hw{position:relative;background:linear-gradient(#FFFCF2,#FFFCF2) padding-box;border:1.5px solid #F2D58A;border-left:6px solid #F2B230;border-radius:14px;padding:12px 12px 12px 14px}
  .cr-hw .tag{display:inline-block;font-size:10.5px;font-weight:700;color:#7A5200;background:#FCE7B2;border-radius:999px;padding:2px 8px;margin-bottom:6px}.cr-hw small{display:block;color:#8A6A1F;font-size:11.5px;margin-bottom:4px}.cr-hw b{display:block;font-size:15px;line-height:1.35;color:#2B2410}
  .cr-cnt{display:grid;grid-template-columns:repeat(4,1fr);gap:6px;margin:10px 0}.cr-cnt div{border-radius:10px;padding:5px 2px;text-align:center;font-size:10.5px;font-weight:600}.cr-cnt b{display:block;font-size:17px;font-weight:800;line-height:1.1}
  .cr-hint{font-size:12px;color:var(--muted);margin:6px 2px 10px}
  .tile.cr-ab{opacity:.4}.tile.cr-t .badge{background:var(--ok)}.tile.cr-t.sA .badge{background:var(--bad)}.tile.cr-t.sL{background:#FFF4E2;border-color:#F2B45A}.tile.cr-t.sL .badge{background:#D97706}
  .cr-pct{font-size:18px;font-weight:800;color:var(--ok)}.cr-pct.mid{color:#C27803}.cr-pct.lo{color:var(--bad)}
  .cr-check .tiles{grid-template-columns:repeat(4,minmax(0,1fr));gap:6px}.cr-check .tile{padding:7px 2px 6px;border-radius:13px}.cr-check .tile .ph{width:42px;height:42px}.cr-check .tile b{font-size:11px}.cr-check .tile small{font-size:9.5px}
  .cr-check .sticky .btn,.cr-check .btn.submit{min-height:42px;font-size:13.5px}.cr-check .quick .inp{min-height:38px}
  .auto-fill{min-height:36px!important;font-size:12.5px!important}.dform .btn.go.full{min-height:42px}
  .cr-grp{font-size:11.5px;font-weight:700;color:var(--muted);margin:4px 2px 6px;letter-spacing:.2px}
  .cr-bell{width:42px;flex-shrink:0;text-align:center;border-radius:10px;background:var(--badbg);color:var(--bad);padding:4px 0;font-size:9.5px;line-height:1.2}.cr-bell b{display:block;font-size:14px}.cr-bell.ok{background:var(--okbg);color:var(--ok)}
  .cr-row .n small.arrp{display:inline-block;margin:0 0 0 4px;vertical-align:1px}.arrp{font-size:10px;font-weight:600;color:#6D28D9;background:#F1EAFE;border-radius:999px;padding:1px 6px}
  .cr-share{padding:12px 14px}.cr-sh{padding:8px 0;border-top:1px solid var(--line)}.cr-sh b{display:block;font-size:13px}.cr-sh small{display:flex;gap:6px;font-size:12px;color:#445;margin-top:3px}.cr-sh small i{font-style:normal;font-weight:700;font-size:10px;color:#1D5FB8;background:#E6F1FD;border-radius:6px;padding:1px 5px;height:16px}.cr-sh small i.h{color:#A15C07;background:#FFF4E2}
  .cr-gb{min-height:40px;margin-top:8px;font-size:13px}
  .cr-quick{display:flex;justify-content:space-between;align-items:center;gap:8px;margin-top:8px;padding-top:8px;border-top:1px dashed var(--line)}.cr-quick small{color:var(--muted);font-size:12px;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}`;
  document.head.appendChild(st);
})();
