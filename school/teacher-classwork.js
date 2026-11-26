'use strict';
// Teacher App — class work / homework fixes (owner, 24 Nov 2026: "teachers absent hote hain to syllabus bell vacant jata hai,
// homework allocate nahi ho pata"; "homework cross check ka easy pattern"). Loaded after teacher-hub.js.
//  1. Check work: when homework was due and nothing is saved yet, every present child starts as ✓ Done — the teacher taps
//     only the defaulters (like attendance), then Save. Children with 3+ "not done" in 30 days get a "Repeat" card with a
//     ready parent message.
//  2. Today → "Aaj ke kaam" card: (a) ARRANGEMENT periods — the class, the absent teacher, and WHAT TO TEACH (the absent
//     teacher's lesson-plan line for this week not taught yet, else the next syllabus chapter + topic), suggested class work /
//     homework, one tap to the diary; (b) homework to CHECK today (class + subject → Check work); (c) periods already over
//     whose diary has no homework written.
//  Everything reads data the app already has (grid, staffAtt, lplan, syl, diary); nothing new is stored.
function cwTaught(c, s, days) {   // topics already written in diaries of this class + subject (any teacher), last `days` days
  const out = new Set(), t = todayISO();
  for (let i = 0; i <= days; i++) { const d = addDays(t, -i); Object.values(S.diary[d] || {}).forEach(byB => Object.values(byB || {}).forEach(e => { if (e && e.cls === c && e.subj === s && e.topic) out.add(String(e.topic).trim().toLowerCase()); })); }
  return out;
}
function cwWhatToTeach(c, s, absentTid) {
  const taught = cwTaught(c, s, 21), w = weekStart(todayISO());
  const p = Object.values(S.lplan || {}).find(x => x && x.cls === c && x.subj === s && x.week === w && (!absentTid || x.tid === absentTid)) || Object.values(S.lplan || {}).find(x => x && x.cls === c && x.subj === s && x.week === w);
  const fromPlan = p ? String(p.topics || '').split('\n').map(x => x.trim()).find(x => x && !taught.has(x.toLowerCase())) : '';
  if (fromPlan) return { topic: fromPlan, src: 'lesson plan' };
  const y = S.syl[sylKey(c, s)] || { chapters: [] }, ym = todayISO().slice(0, 7), open = y.chapters.filter(x => !x.done), ch = open.find(x => x.plan && x.plan <= ym) || open[0];
  if (!ch) { const last = [...taught][0]; return last ? { topic: 'Revision: ' + last, src: 'last topic' } : null; }
  const tp = (ch.topics || []).find(t => !taught.has((ch.n + ' — ' + t).toLowerCase()) && !taught.has(String(t).toLowerCase()));
  return { topic: ch.n + (tp ? ' — ' + tp : ''), src: 'syllabus' };
}
function cwTodayCard() {
  const d = todayISO(); if (dayIdx(d) < 0 || holidayOn(d)) return '';
  const bells = myBells(d), arr = bells.filter(b => b.arr), parts = [];
  if (arr.length) parts.push(`<h4 class="cwh">🔁 Arrangement today <small>${arr.length} period${arr.length > 1 ? 's' : ''}</small></h4>` + arr.map(b => { const w = cwWhatToTeach(b.cls, b.subj, b.for), e = diaryEntry(d, b.bell);
    // Owner, 27 Nov 2026 ("arrangement bell na samajh aane ke wajah se random class chal raha hai"): the slip says WHEN (bell time),
    // whether it is running now, and has "✅ I am in the class" (duty_api.php ?a=reach) so the Duty teacher sees who reached.
    const B = BELLS[b.bell] || {}, nm = nowMin(), st = B.s ? toMin(B.s) : 0, en = B.e ? toMin(B.e) : 0, now = nm >= st && nm < en, soon = nm >= st - 10 && nm < st, rc = typeof dutyReached === 'function' ? dutyReached(d, b.bell, b.cls) : null;
    return `<div class="cwr${now ? ' cw-now' : soon ? ' cw-soon' : ''}"><div><b>🔔 ${esc(B.n || '')} bell · ${B.s ? esc(t12(B.s)) + '–' + esc(t12(B.e)) : ''}</b><b>Class ${esc(b.cls)} · ${esc(b.subj)}</b>${now ? '<span class="pill bad">Now — go to Class ' + esc(b.cls) + '</span>' : soon ? '<span class="pill warn">Starts soon</span>' : ''}<small>In place of ${esc((S.teachers[b.for] || {}).name || b.for || 'absent teacher')}${e ? ' · ✓ diary written' : ''}${rc ? ' · ✅ reached class' : ''}</small>
      ${typeof dutyReachBtn === 'function' && nm >= st - 10 && nm < en + 30 ? dutyReachBtn(d, b.bell, b.cls, rc) : ''}
      ${w ? `<p>📖 <b>Teach:</b> ${esc(w.topic)} <span class="mut">(${esc(w.src)})</span><br>✍️ <b>Class work:</b> Notes and questions on ${esc(w.topic.replace(/^Revision: /, ''))}<br>🏠 <b>Homework:</b> Read it again and write 5 question-answers</p>` : '<p class="mut">No syllabus yet for this class + subject — revise the last lesson.</p>'}</div>
      <button class="btn s ${e ? 'l' : 'go'}" onclick="go('diary',{date:'${d}'})">${e ? 'Open' : 'Write diary'}</button></div>`; }).join(''));
  const chk = []; allocList().forEach(a => { const due = dueHomework(d, a.c, a.s); if (due.length) chk.push({ a, n: due.length }); });
  if (chk.length) parts.push(`<h4 class="cwh">📕 Homework to check today</h4>` + chk.map(x => `<div class="cwr"><div><b>Class ${esc(x.a.c)} · ${esc(x.a.s)}</b><small>${x.n} homework due today — tap only the children who did not do it</small></div><button class="btn s go" onclick="DRAFT.chk=null;go('diary',{m:'check',date:'${d}',key:${jsq(sylKey(x.a.c, x.a.s))}})">Check</button></div>`).join(''));
  const noHw = bells.filter(b => bellOver(d, b.bell) && !b.arr).filter(b => { const e = diaryEntry(d, b.bell); return !e || !String(e.hw || '').trim(); });
  if (noHw.length) parts.push(`<h4 class="cwh">✍️ Homework not written yet</h4><p class="mut" style="margin:0 0 4px">These periods are over. Write the homework (or "No homework") so it reaches the class group today.</p>` + noHw.map(b => `<div class="cwr"><div><b>${esc(BELLS[b.bell].n)} bell · Class ${esc(b.cls)} · ${esc(b.subj)}</b><small>${diaryEntry(d, b.bell) ? 'diary written, homework empty' : 'diary not written'}</small></div><button class="btn s go" onclick="go('diary',{date:'${d}'})">Write</button></div>`).join(''));
  return parts.length ? `<div class="card cwcard"><h3>📋 Aaj ke kaam</h3>${parts.join('')}</div>` : '';
}
if (typeof vHome === 'function') {
  const _vhcw = vHome;
  vHome = function () { const html = _vhcw.apply(this, arguments); if (!S || !ME || isP() || (typeof pMode === 'function' && pMode())) return html;
    const card = cwTodayCard(); if (!card) return html; const i = html.indexOf('</h2></div>'); return i > 0 ? html.slice(0, i + 11) + card + html.slice(i + 11) : card + html; };
}
// ---------- Check work: defaulters-only + repeat defaulters ----------
if (typeof vCheck === 'function') {
  const _vchk = vCheck;
  vCheck = function () {
    try {
      const list = allocList(); if (list.length) {
        const t = todayISO(), date = P.date && P.date <= t ? P.date : t, key = list.some(a => sylKey(a.c, a.s) === P.key) ? P.key : sylKey(list[0].c, list[0].s), [c, sb] = key.split('|'), k = chkKey(date, c, sb), R = CHK[k];
        if (R && !R.loading && !R.err && !R.rec && (!DRAFT.chk || DRAFT.chk.k !== k) && dueHomework(date, c, sb).length) {
          const att = attOf(date, c), ab = new Set((att && att.absent) || []), rows = {};
          studentsOf(c).forEach(x => { rows[x.id] = { hw: ab.has(x.id) ? '' : 'done', cp: '' }; });
          DRAFT.chk = { k, rows, changed: false, preset: true };
        }
      }
    } catch (e) {}
    let html = _vchk.apply(this, arguments);
    if (DRAFT.chk && DRAFT.chk.preset) html = html.replace('<p class="mut hint">Homework: tap Done / Half / Not done.', '<p class="ban inf" style="margin:6px 0"><b>Everyone is ✓ Done to start.</b> Tap only the children who did not do it (✗) or did half (½), then Save.</p><p class="mut hint">Homework: tap Done / Half / Not done.');
    // repeat defaulters (3+ not done in 30 days) — ready parent message
    try {
      const list = allocList(), key = list.some(a => sylKey(a.c, a.s) === P.key) ? P.key : sylKey(list[0].c, list[0].s), [c, sb] = key.split('|'), t = todayISO(), date = P.date && P.date <= t ? P.date : t, R = CHK[chkKey(date, c, sb)];
      const rep = R && R.miss ? studentsOf(c).filter(x => (R.miss[x.id] || 0) >= 3) : [];
      if (rep.length) html += `<div class="card"><h3>⚠️ Repeat — 3 or more times not done <small>last 30 days</small></h3>${rep.map(x => { const m = R.miss[x.id];
        const msg = `Dear Parent,\n${x.name} (Class ${c}) has not done the ${sb} homework ${m} times in the last 30 days. Please check the homework every day and sign the diary.\n${x.name} ने पिछले 30 दिनों में ${m} बार ${sb} का गृहकार्य नहीं किया। कृपया रोज़ गृहकार्य देखें।\n— ${(S.teachers[me()] || {}).name || 'Class teacher'}`;
        return `<div class="cwr"><div><b>${esc(x.name)}</b><small>${m} times not done</small></div>${x.phone ? `<a class="btn wa s" target="_blank" rel="noopener" href="${wa(x.phone, msg)}">${ic('wa')} Parent</a>` : '<small class="mut">no phone</small>'}</div>`; }).join('')}</div>`;
    } catch (e) {}
    return html;
  };
}
(function () { const st = document.createElement('style'); st.textContent = `.cwcard{border:2px solid #F2C14E66}.cwh{margin:10px 0 2px;font-size:14px;color:#17324D}.cwh small{font-weight:500;color:#667}
  .cwr{display:flex;justify-content:space-between;gap:8px;align-items:flex-start;padding:8px 0;border-top:1px solid #eef1f4}.cwr small{display:block;color:#667;font-size:12px}.cwr p{margin:5px 0 0;font-size:13px;line-height:1.45}`; document.head.appendChild(st); })();
