'use strict';
// ============================================================================
//  Teacher App — Homework share PLUS (owner, 25 Nov 2026: "homework share bhi nahi paa raha … coordinator ma'am class ke
//  according homework show kare, aur jitne teachers lesson plan bana diye hain vo homework ke saath add hokar aaye — 'aapko aaj
//  ye padhkar aana hai, kal padhaya jayega'"). Loaded after teacher-hwshare.js / teacher-sylsub.js. Nothing new is stored.
//   • Each class's message gets "📖 Kal padhaya jayega — aaj ghar par padh kar aayein": for every subject in that class's
//     timetable on the NEXT school day, the lesson-plan topic (daily plan of that date, else this week's plan line not yet
//     taught). Subjects with no lesson plan are listed for the coordinator ("plan nahi bana"). Switch to leave it out.
//   • Loading fix: one date loading no longer blocks another date ("Opening…" stuck), and the list refreshes by itself
//     after 2 minutes so new diary entries come in without pressing Refresh.
//   • Top summary: classes ready / shared / not filled, and a "Share next class" button that walks class by class.
// ============================================================================
const HP = { busy: {} };
const HP_SKIP = /Games|Library|Moral|Rhymes|Play|Craft|Activity|PT\b|Music|Dance|Drawing|Art|Lunch|Break/i;
function hpTaught(c, s, from, before) {   // diary topics of this class + subject (any teacher) from `from` up to the day before `before`
  const out = new Set();
  for (let d = from; d < before; d = addDays(d, 1)) Object.values(S.diary[d] || {}).forEach(byB => Object.values(byB || {}).forEach(e => { if (e && e.cls === c && e.subj === s && e.topic) out.add(String(e.topic).trim().toLowerCase()); }));
  return out;
}
function hpPlanTopic(c, s, d) {           // what the lesson plan says this class + subject learns on day d
  const w = weekStart(d), plans = Object.values(S.lplan || {}).filter(p => p && p.cls === c && p.subj === s && p.week === w);
  for (const p of plans) if (Array.isArray(p.days)) { const x = p.days.find(y => y && y.date === d && y.topic); if (x) return String(x.topic).trim(); }
  const p = plans.find(x => x.status === 'Approved') || plans.find(x => x.status !== 'Revise') || plans[0]; if (!p) return '';
  const taught = hpTaught(c, s, w, d);
  return String(p.topics || '').split('\n').map(x => x.trim()).find(x => x && !taught.has(x.toLowerCase())) || '';
}
function hpPrep(date, c) {               // [{subj, topic}] for the next school day; topic '' = no plan made
  const next = typeof nextSchoolDay === 'function' ? nextSchoolDay(date, c) : null; if (!next) return { next: null, list: [] };
  const d = dayIdx(next), subs = [];
  if (S.grid && S.grid[c] && d >= 0) BELLS.forEach((b, i) => { const a = cell(c, d, i); const sb = a && a.s ? String(a.s).replace(/\(optional\)/i, '').trim() : ''; if (sb && !subs.includes(sb) && !HP_SKIP.test(sb)) subs.push(sb); });
  else Object.values(S.lplan || {}).forEach(p => { if (p && p.cls === c && p.week === weekStart(next) && !subs.includes(p.subj)) subs.push(p.subj); });
  return { next, list: subs.map(s => ({ subj: s, topic: hpPlanTopic(c, s, next) })) };
}
const _hpMsg = hwMsg;
hwMsg = function (c, L, date) {
  const m = _hpMsg(c, L, date); if (DRAFT.hwPrep === false) return m;
  const P2 = hpPrep(date, c), with_ = P2.list.filter(x => x.topic); if (!with_.length) return m;
  const block = `\n\n*📖 Kal (${fmtDate(P2.next)}) padhaya jayega — aaj ghar par ek baar padh kar aayein:*\n` + with_.map(x => `• *${x.subj}*: ${x.topic}`).join('\n');
  const tail = '\n\nKripya bachchon ka homework roz check karein. 🙏';
  return m.endsWith(tail) ? m.slice(0, -tail.length) + block + tail : m + block;
};
hwLoad = async function (date, force) {
  const D = HWSH[date];
  if (D && !force && !D.err && Date.now() - (D._at || 0) < 120000) return;
  if (HP.busy[date]) return; HP.busy[date] = true;
  try { const r = await api('teacher_api.php?a=hwall&date=' + date); r._at = Date.now(); HWSH[date] = r; } catch (e) { if (!HWSH[date] || force) HWSH[date] = { err: e.message }; }
  HP.busy[date] = false; render();
};
function hpNext(date) {                   // open WhatsApp for the first class that is ready and not shared yet
  const el = document.querySelector('.hws:not(.done) a.btn.go'); if (!el) return toast('Sab classes share ho gayi ✓');
  el.scrollIntoView({ behavior: 'smooth', block: 'center' }); el.classList.add('hp-pulse'); setTimeout(() => el.classList.remove('hp-pulse'), 1600);
}
vHwShare = function () {
  const date = P.date || todayISO(), D = HWSH[date];
  const top = `<div class="card"><div class="row"><div><b>📤 Homework share · PG to 10th</b><small>Har class ka ek message — sab subject ka class work, homework aur kal ka lesson-plan topic. Class WhatsApp group me bhejiye.</small></div></div>
    <div class="row" style="margin-top:8px"><input type="date" class="inp" value="${date}" max="${todayISO()}" onchange="go('hwshare',{date:this.value})"><button class="btn l s" onclick="hwLoad('${date}',true)">↻ Refresh</button></div></div>`;
  if (!D || (Date.now() - (D._at || 0) > 120000 && !D.err)) hwLoad(date);
  if (!D) return top + '<div class="card">Opening…</div>';
  if (D.err) return top + `<div class="card">${esc(D.err)} <button class="btn s l" onclick="hwLoad('${date}',true)">Try again</button></div>`;
  const by = {}; D.items.forEach(i => (by[i.cls] = by[i.cls] || []).push(i));
  const auto = DRAFT.hwAuto !== false, prep = DRAFT.hwPrep !== false;
  D.classes.forEach(c => { const miss = hwMissing(c, date, by[c] || []); if (auto && miss.length) by[c] = (by[c] || []).concat(miss); });
  const ready = D.classes.filter(c => by[c]), none = D.classes.filter(c => !by[c]), done = ready.filter(c => D.shared[c]);
  const noPlan = []; if (prep) ready.forEach(c => hpPrep(date, c).list.filter(x => !x.topic).forEach(x => noPlan.push('Class ' + c + ' ' + x.subj)));
  let h = top + `<div class="card"><div class="row"><b>${ready.length} class ready · ${done.length} shared · ${ready.length - done.length} baaki</b><small>${none.length ? none.length + ' class me abhi kuch nahi likha' : 'sab classes bhari hain'}</small></div>
    <div class="hp-bar"><i style="width:${ready.length ? Math.round(done.length / ready.length * 100) : 0}%"></i></div>
    ${ready.length > done.length ? `<button class="btn go full" style="margin-top:8px" onclick="hpNext('${date}')">➡ Agli class share karein</button>` : ready.length ? '<div class="ban ok" style="margin-top:8px">✓ Aaj sab classes ka homework share ho gaya</div>' : ''}
    <label class="chk" style="margin-top:8px"><input type="checkbox" ${prep ? 'checked' : ''} onchange="DRAFT.hwPrep=this.checked;render()"> <span><b>Kal ka lesson-plan topic</b> jodein — "aaj padh kar aayein, kal padhaya jayega"</span></label>
    <label class="chk" style="margin-top:4px"><input type="checkbox" ${auto ? 'checked' : ''} onchange="DRAFT.hwAuto=this.checked;render()"> <span>Jis subject me aaj kisi ne nahi likha (teacher absent / bhool gaye) — <b>auto revision homework</b></span></label></div>`;
  ready.forEach(c => { const L = by[c], m = hwMsg(c, L, date), sh = D.shared[c], pp = prep ? hpPrep(date, c) : { list: [] };
    h += `<div class="card hws${sh ? ' done' : ''}"><div class="row"><b>Class ${esc(c)}</b><small>${L.length} entr${L.length > 1 ? 'ies' : 'y'}${sh ? ' · ✓ Shared ' + new Date(sh.at).toLocaleTimeString('en-IN', { hour: 'numeric', minute: '2-digit' }) + (sh.by ? ' · ' + esc(sh.by) : '') : ''}</small></div>
      ${L.map(i => `<div class="hwi${i.auto ? ' auto' : ''}"><b>${esc(i.subj)}</b> <small>· ${i.auto ? '<span class="pill warn">auto</span>' : esc(i.teacher)}</small>${i.cw ? `<p><span>CW</span> ${esc(i.cw)}</p>` : ''}${i.hw ? `<p><span>HW</span> ${esc(i.hw)}</p>` : '<p><span>HW</span> <i>No homework</i></p>'}</div>`).join('')}
      ${pp.list.length ? `<div class="hp-prep"><b>📖 Kal ${pp.next ? '(' + esc(fmtDate(pp.next)) + ')' : ''} — aaj padh kar aayein</b>${pp.list.map(x => `<p><span>${esc(x.subj)}</span> ${x.topic ? esc(x.topic) : '<i class="mut">lesson plan nahi bana</i>'}</p>`).join('')}</div>` : ''}
      <div class="btns"><a class="btn go" style="text-decoration:none" target="_blank" rel="noopener" href="https://wa.me/?text=${encodeURIComponent(m)}" onclick="hwMark('${date}','${esc(c)}',1)">Share on WhatsApp</a>
        <button class="btn l" onclick="hwCopy(${JSON.stringify(m).replace(/"/g, '&quot;')})">Copy</button>${sh ? `<button class="btn l s" onclick="hwMark('${date}','${esc(c)}',0)">Undo ✓</button>` : ''}</div></div>`; });
  if (none.length) h += `<div class="card"><b>Abhi kuch nahi likha</b><p class="mut" style="margin:6px 0 0">${none.map(esc).join(' · ')}</p><small>Teachers Classroom → Diary me class work / homework likhte hi yahan apne aap aa jayega.</small></div>`;
  if (noPlan.length) h += `<div class="card"><b>Kal ke liye lesson plan nahi bana</b><p class="mut" style="margin:6px 0 0">${noPlan.map(esc).join(' · ')}</p><small>Teacher Planner → Lesson plan me is hafte ke topics bharein — fir yahan apne aap aayega.</small></div>`;
  return h;
};
(function () { const st = document.createElement('style'); st.textContent = `.hp-prep{margin-top:8px;padding:8px 10px;border-radius:10px;background:#EEF6FF;border:1px solid #CFE3FA}.hp-prep b{font-size:13px;color:#1E3A8A}.hp-prep p{margin:4px 0 0;font-size:13.5px}.hp-prep p span{display:inline-block;min-width:70px;font-size:11.5px;font-weight:700;color:#1E40AF}
  .hp-bar{height:8px;border-radius:99px;background:#E5E7EB;margin-top:8px;overflow:hidden}.hp-bar i{display:block;height:100%;background:#22C55E}
  .mode-sw{flex-wrap:wrap;max-width:100%}
  @keyframes hpP{0%,100%{box-shadow:0 0 0 0 rgba(245,158,11,.0)}50%{box-shadow:0 0 0 6px rgba(245,158,11,.45)}}.hp-pulse{animation:hpP .8s 2}`; document.head.appendChild(st); })();
