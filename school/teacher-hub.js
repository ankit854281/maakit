'use strict';
// Teacher App — Planner becomes a "Syllabus Hub" (owner, 24 Nov 2026: "syllabus me hi sab option add karna galat ho jayega —
// syllabus add karna, examination allocation alag, complete chapters alag, remaining alag, test paper alag … revision kaise
// hoga"; approved mockup: one screen of tiles, each tile ONE job). Loaded after teacher-sylgrid.js.
//  Planner (P.m): hub (default) · syl (Syllabus entry — chapters, topics, pages, table, submit) · exam (Exam allocation grid:
//  chapter × exam + month, one Save, WhatsApp the exam syllabus) · done (Completed, with date, un-tick) · left (Remaining, by
//  month, next exam countdown, behind alert, periods left) · rev (Revision due: 1 week / 1 month after finishing and the week
//  before its exam; ✓ marks `rv:[...]` on the chapter) · paper (Test paper) · lp (Lesson plan) · notes (Notes tab).
//  Data stays in the same `syl` record (chapters[].ex / plan / done / rv) — no new kinds, no new permissions.
const HUB_ITEMS = [['syl', '📚', 'Syllabus entry', 'Chapters, topics, pages'], ['exam', '🗓', 'Exam allocation', 'Chapter → exam, month'],
  ['done', '✅', 'Completed', 'Done, with date'], ['left', '⏳', 'Remaining', 'Left, behind alert'], ['rev', '🔁', 'Revision', 'Due revisions'],
  ['paper', '📄', 'Test paper', 'Auto paper, answer key'], ['lp', '🗒', 'Lesson plan', 'Week plan from syllabus'], ['notes', '📝', 'Notes', 'Chapter notes']];
function hubKey(list) { return list.some(a => sylKey(a.c, a.s) === P.key) ? P.key : sylKey(list[0].c, list[0].s); }
function hubNextExam(c, y) {
  const t = todayISO(), tags = examTags(c).filter(x => x.date && (x.to || x.date) >= t).sort((a, b) => a.date.localeCompare(b.date));
  const e = tags.find(x => y.chapters.some(ch => (ch.ex || []).includes(x.id))) || tags[0]; if (!e) return null;
  const days = Math.max(0, Math.round((new Date(e.date + 'T00:00:00') - new Date(t + 'T00:00:00')) / 864e5));
  const its = y.chapters.filter(ch => (ch.ex || []).includes(e.id)), left = its.filter(ch => !ch.done);
  return { e, days, total: its.length, left };
}
function hubRevDue(key) {   // revisions due now: 1 week and 1 month after a chapter was finished, and the week before its exam
  const y = S.syl[key] || { chapters: [] }, [c] = key.split('|'), t = todayISO(), tags = examTags(c), out = [];
  y.chapters.forEach((ch, i) => { if (!ch.done) return; const rv = ch.rv || [];
    if (!rv.includes('w1') && addDays(ch.done, 7) <= t) out.push({ i, ch, id: 'w1', why: '1 week after finishing' });
    if (!rv.includes('m1') && addDays(ch.done, 30) <= t) out.push({ i, ch, id: 'm1', why: '1 month after finishing' });
    (ch.ex || []).forEach(x => { const e = tags.find(z => z.id === x); if (e && e.date && !rv.includes('ex:' + x) && addDays(e.date, -7) <= t && t <= (e.to || e.date)) out.push({ i, ch, id: 'ex:' + x, why: 'before ' + e.l + ' (' + fmtDate(e.date) + ')' }); });
  });
  return out;
}
function hubStats(key) {
  const y = S.syl[key] || { chapters: [] }, n = y.chapters.length, done = y.chapters.filter(c => c.done).length, [c] = key.split('|'), ym = todayISO().slice(0, 7);
  return { n, done, behind: y.chapters.filter(ch => !ch.done && ch.plan && ch.plan < ym).length, noEx: y.chapters.filter(ch => !(ch.ex || []).length).length, rev: hubRevDue(key).length, next: hubNextExam(c, y) };
}
planSeg = function (m) {
  const it = HUB_ITEMS.find(x => x[0] === m);
  return `<div class="hubnav"><button class="btn l s" onclick="go('plan',{m:'hub',key:${jsq(P.key || '')}})">← Planner</button><b>${it ? it[1] + ' ' + it[2] : ''}</b></div>
    <div class="seg hubseg">${HUB_ITEMS.map(([k, , l]) => `<button class="${k === m ? 'on' : ''}" onclick="${k === 'notes' ? `go('notes',{key:${jsq(P.key || '')}})` : `go('plan',{m:'${k}',key:${jsq(P.key || '')}})`}">${l.replace('Syllabus entry', 'Syllabus').replace('Test paper', 'Paper').replace('Exam allocation', 'Exams')}</button>`).join('')}</div>`;
};
function hubView(list) {
  const key = hubKey(list), st = hubStats(key), pct = st.n ? Math.round(st.done * 100 / st.n) : 0;
  const badge = { syl: st.n ? st.n + ' ch' : 'add', exam: st.noEx ? st.noEx + ' not set' : '✓', done: String(st.done), left: String(st.n - st.done), rev: st.rev ? st.rev + ' due' : '', paper: '', lp: '', notes: '' };
  const tone = { exam: st.noEx ? 'w' : 'g', done: 'g', left: st.behind ? 'r' : 'w', rev: st.rev ? 'w' : '' };
  return `${keyPick(list, key, "go('plan',{m:'hub',key:this.value})")}
    <div class="hubhero"><div class="row"><span>Syllabus ${st.done}/${st.n} chapters done</span><b>${pct}%</b></div><div class="hubbar"><i style="width:${pct}%"></i></div>
      <small>${st.next ? `${esc(st.next.e.l)} in ${st.next.days} day${st.next.days === 1 ? '' : 's'} · ${st.next.left.length} of ${st.next.total} exam chapters left` : 'No exam date yet'}${st.behind ? ` · <b>${st.behind} chapter(s) behind the month plan</b>` : ''}</small></div>
    <div class="hubgrid">${HUB_ITEMS.map(([k, ic2, l, sub]) => `<button class="hubt ${tone[k] || ''}" onclick="${k === 'notes' ? `go('notes',{key:${jsq(key)}})` : `go('plan',{m:'${k}',key:${jsq(key)}})`}"><span class="hi">${ic2}</span><b>${l}${badge[k] ? ` <em>${esc(badge[k])}</em>` : ''}</b><small>${sub}</small></button>`).join('')}</div>`;
}
// ---------- Exam allocation ----------
function vHubExam(list) {
  const key = hubKey(list), [c, s] = key.split('|'), y = S.syl[key] || { chapters: [] }, M = tgMonths();
  if (!DRAFT.he || DRAFT.he.key !== key) DRAFT.he = { key, ex: y.chapters.map(ch => (ch.ex || []).slice()), plan: y.chapters.map(ch => ch.plan || ''), dirty: 0 };
  const H = DRAFT.he, tags = examTags(c).filter(t => !examPast(t) || H.ex.some(e => e.includes(t.id)));
  if (!y.chapters.length) return keyPick(list, key, "DRAFT.he=null;go('plan',{m:'exam',key:this.value})") + `<div class="card empty">No chapters yet. Add them in <button class="btn s l" onclick="go('plan',{m:'syl',key:${jsq(key)}})">📚 Syllabus entry</button></div>`;
  const shares = tags.map(t => ({ t, txt: H.dirty ? '' : sylShareText(key, t.id, null) })).filter(x => x.txt);
  return `${keyPick(list, key, "DRAFT.he=null;go('plan',{m:'exam',key:this.value})")}
    <div class="card"><h3>Which chapter in which exam <small>tap to tick · then Save</small></h3>
      <div class="hxw"><table class="hext"><tr><th>Chapter</th><th>Month</th>${tags.map(t => `<th>${esc(t.l)}${t.date ? `<small>${esc(fmtDate(t.date).replace(/^\w+, /, ''))}</small>` : ''}</th>`).join('')}</tr>
        ${y.chapters.map((ch, i) => `<tr class="${ch.done ? 'dn' : ''}"><td><b>${i + 1}. ${esc(ch.n)}</b>${ch.done ? '<small>✓ done</small>' : ''}</td>
          <td><select class="inp" onchange="DRAFT.he.plan[${i}]=this.value;DRAFT.he.dirty++;render()"><option value="">—</option>${M.map(([v, l]) => `<option value="${v}" ${H.plan[i] === v ? 'selected' : ''}>${l}</option>`).join('')}</select></td>
          ${tags.map(t => `<td class="c"><label class="hexc"><input type="checkbox" ${H.ex[i].includes(t.id) ? 'checked' : ''} onchange="hubExTog(${i},'${esc(t.id)}',this.checked)"></label></td>`).join('')}</tr>`).join('')}
        <tr class="tot"><td>Chapters</td><td></td>${tags.map(t => `<td class="c">${H.ex.filter(e => e.includes(t.id)).length}</td>`).join('')}</tr></table></div>
      ${H.dirty ? `<button class="btn go full" style="margin-top:10px;position:relative;z-index:2" onclick="hubExSave(${jsq(key)})">Save exam allocation · ${H.dirty} change(s)</button>` : '<p class="mut hint">Saved. The test paper, revision and the parents\' exam syllabus use this list.</p>'}</div>
    ${shares.length ? `<div class="card"><h3>Send exam syllabus to parents</h3>${shares.map(x => `<a class="btn wa full" style="margin-bottom:6px" target="_blank" rel="noopener" href="${wa('', x.txt)}">${ic('wa')} ${esc(x.t.l)} syllabus — Class ${esc(c)} ${esc(s)}</a>`).join('')}</div>` : ''}`;
}
function hubExTog(i, id, on) { const e = DRAFT.he.ex[i], j = e.indexOf(id); if (on && j < 0) e.push(id); if (!on && j >= 0) e.splice(j, 1); DRAFT.he.dirty++; render(); }
function hubExSave(key) {
  const H = DRAFT.he, y = sylCopy(key); let n = 0;
  y.chapters.forEach((ch, i) => { if (H.ex[i] === undefined) return; if (H.ex[i].length) ch.ex = H.ex[i].slice(); else delete ch.ex; if (H.plan[i]) ch.plan = H.plan[i]; else delete ch.plan; n++; });
  put('syl', key, y, 'Syllabus', `${key.replace('|', ' ')}: exam allocation saved (${y.chapters.filter(c => (c.ex || []).length).length}/${y.chapters.length} chapters with an exam, ${y.chapters.filter(c => c.plan).length} with a month) — Teacher App`);
  DRAFT.he = null; DRAFT.sx = null; toast('Exam allocation saved ✓'); render();
}
// ---------- Completed ----------
function vHubDone(list) {
  const key = hubKey(list), y = S.syl[key] || { chapters: [] }, D = y.chapters.map((ch, i) => [ch, i]).filter(([ch]) => ch.done).sort((a, b) => String(b[0].done).localeCompare(String(a[0].done)));
  const open = y.chapters.map((ch, i) => [ch, i]).filter(([ch]) => !ch.done);
  return `${keyPick(list, key, "go('plan',{m:'done',key:this.value})")}
    <div class="card"><h3>✅ Completed <small>${D.length} of ${y.chapters.length}</small></h3>
      ${D.length ? D.map(([ch, i]) => `<div class="hrow"><div><b>${esc(ch.n)}</b><small>Finished ${esc(fmtDate(ch.done))}${(ch.ex || []).length ? ' · ' + (ch.ex || []).map(examLabel).join(', ') : ''}${(ch.rv || []).length ? ' · revised ' + ch.rv.length + '×' : ''}</small></div><button class="btn s l" onclick="if(confirm('Mark &quot;${esc(ch.n).replace(/'/g, '')}&quot; as NOT finished?'))sylTick(${jsq(key)},${i},false)">Undo</button></div>`).join('') : '<div class="empty">No chapter finished yet.</div>'}</div>
    ${open.length ? `<div class="card"><h3>Finished a chapter today?</h3><p class="mut" style="margin:0 0 6px">Tick it here (writing it in the diary also ticks it by itself).</p>${open.slice(0, 6).map(([ch, i]) => `<div class="hrow"><div><b>${esc(ch.n)}</b><small>${ch.plan ? '📅 ' + esc(tgMonLabel(ch.plan)) : ''}</small></div><button class="btn s go" onclick="sylTick(${jsq(key)},${i},true)">✓ Finished</button></div>`).join('')}</div>` : ''}`;
}
// ---------- Remaining ----------
function vHubLeft(list) {
  const key = hubKey(list), [c, s] = key.split('|'), y = S.syl[key] || { chapters: [] }, ym = todayISO().slice(0, 7), st = hubStats(key);
  const a = list.find(z => sylKey(z.c, z.s) === key), wk = (a && +a.n) || 0, L = y.chapters.map((ch, i) => [ch, i]).filter(([ch]) => !ch.done);
  const nx = st.next, periods = nx && wk ? Math.floor(nx.days / 7 * wk) : null;
  const tight = nx && periods !== null && nx.left.length && periods < nx.left.length * 4;
  const byM = {}; L.forEach(([ch, i]) => { const m = ch.plan || ''; (byM[m] = byM[m] || []).push([ch, i]); });
  const ms = Object.keys(byM).sort((p, q) => (p || '9999').localeCompare(q || '9999'));
  return `${keyPick(list, key, "go('plan',{m:'left',key:this.value})")}
    ${nx ? `<div class="card hleft ${tight ? 'bad' : ''}"><b>${esc(nx.e.l)} in ${nx.days} day${nx.days === 1 ? '' : 's'}</b> (${esc(fmtDate(nx.e.date))})<p style="margin:4px 0 0">${nx.left.length ? `${nx.left.length} of ${nx.total} exam chapters left: ${nx.left.map(ch => esc(ch.n)).join(', ')}.` : nx.total ? 'All exam chapters done ✓ — time for revision.' : 'No chapter is allocated to this exam yet — set it in 🗓 Exam allocation.'}${periods !== null && nx.left.length ? ` About <b>${periods}</b> periods left (${wk} a week).${tight ? ' <b>Tight — ask the Principal for extra periods or speed up.</b>' : ''}` : ''}</p></div>` : ''}
    ${st.behind ? `<div class="ban bad"><div><b>${st.behind} chapter(s) are behind the month plan.</b> Finish them first or change their month in 🗓 Exam allocation.</div></div>` : ''}
    <div class="card"><h3>⏳ Remaining <small>${L.length} of ${y.chapters.length}</small></h3>
      ${L.length ? ms.map(m => `<h4 class="hmh">${m ? '📅 ' + esc(tgMonLabel(m)) + (m < ym ? ' <span class="pill bad">behind</span>' : m === ym ? ' <span class="pill">this month</span>' : '') : 'No month set'}</h4>${byM[m].map(([ch, i]) => `<div class="hrow"><div><b>${esc(ch.n)}</b><small>${(ch.ex || []).length ? (ch.ex || []).map(examLabel).join(', ') : 'no exam set'}${(ch.topics || []).length ? ' · ' + ch.topics.length + ' topics' : ''}${ch.pf || ch.pt ? ' · page ' + esc(ch.pf || '?') + (ch.pt ? '–' + esc(ch.pt) : '') : ''}</small></div><button class="btn s go" onclick="sylTick(${jsq(key)},${i},true)">✓</button></div>`).join('')}`).join('') : '<div class="empty">Nothing left — syllabus complete 🎉</div>'}</div>`;
}
// ---------- Revision ----------
function vHubRev(list) {
  const key = hubKey(list), [c, s] = key.split('|'), R = hubRevDue(key);
  const txt = R.length ? `*Class ${c} — ${s} — Revision*\n` + [...new Set(R.map(r => r.ch.n))].map((n, j) => `${j + 1}. ${n}`).join('\n') + '\nKripya ghar par in chapters ko dohraayein. 🙏' : '';
  return `${keyPick(list, key, "go('plan',{m:'rev',key:this.value})")}
    <div class="card"><h3>🔁 Revision due <small>${R.length}</small></h3><p class="mut" style="margin:0 0 6px">A finished chapter comes back 1 week and 1 month later, and in the week before its exam. Revise it in class (or give it as homework), then tick.</p>
      ${R.length ? R.map(r => `<div class="hrow"><div><b>${esc(r.ch.n)}</b><small>${esc(r.why)} · finished ${esc(fmtDate(r.ch.done))}</small></div><div class="row" style="gap:4px"><button class="btn s l" onclick="hubRevHw(${jsq(key)},${r.i})">As HW</button><button class="btn s go" onclick="hubRevMark(${jsq(key)},${r.i},'${esc(r.id)}')">✓ Revised</button></div></div>`).join('') : '<div class="empty">No revision due today ✓</div>'}</div>
    ${txt ? `<a class="btn wa full" target="_blank" rel="noopener" href="${wa('', txt)}">${ic('wa')} Send revision list to the class parents' group</a>` : ''}`;
}
function hubRevMark(key, i, id) { const y = sylCopy(key), ch = y.chapters[i]; if (!ch) return; ch.rv = [...new Set([...(ch.rv || []), id])]; put('syl', key, y, 'Syllabus', `${key.replace('|', ' ')}: "${ch.n}" revised (${id}) — Teacher App`); toast('Revision marked ✓'); render(); }
function hubRevHw(key, i) {
  const ch = (S.syl[key] || { chapters: [] }).chapters[i]; if (!ch) return; const t = 'Revision: ' + ch.n + ' — read the chapter again and do its question-answers in the copy.';
  (navigator.clipboard ? navigator.clipboard.writeText(t) : Promise.reject()).then(() => toast('Copied — paste it as homework in today\'s diary.')).catch(() => toast(t));
}
// ---------- wire into the Planner ----------
if (typeof vPlan === 'function') {
  const _vplan = vPlan;
  vPlan = function () {
    const list = allocList(), m = P.m || 'hub';
    if (!list.length) return _vplan.apply(this, arguments);
    if (m === 'hub') return hubView(list);
    const V = { exam: vHubExam, done: vHubDone, left: vHubLeft, rev: vHubRev }[m];
    return V ? planSeg(m) + V(list) : _vplan.apply(this, arguments);
  };
}
(function () { const st = document.createElement('style'); st.textContent = `.hubnav{display:flex;align-items:center;gap:10px;margin:0 0 8px}.hubnav b{font-size:16px;color:#4C1D95}
  .hubseg{overflow-x:auto;flex-wrap:nowrap;white-space:nowrap;scrollbar-width:none}.hubseg button{flex:0 0 auto;padding-left:12px;padding-right:12px}
  .hubhero{background:#EEEDFE;border-radius:14px;padding:11px 13px;margin:0 0 10px;color:#3C3489;font-size:13.5px}.hubhero b{font-size:16px}.hubhero small{display:block;margin-top:6px;color:#534AB7}
  .hubbar{height:7px;background:#CECBF6;border-radius:4px;margin-top:6px;overflow:hidden}.hubbar i{display:block;height:7px;background:#534AB7}
  .hubgrid{display:grid;grid-template-columns:1fr 1fr;gap:8px}.hubt{text-align:left;border:1px solid #e3def2;background:#fff;border-radius:14px;padding:11px;font:inherit;cursor:pointer;min-height:86px}
  .hubt .hi{font-size:20px}.hubt b{display:block;font-size:14.5px;color:#1f2937;margin-top:2px}.hubt b em{font-style:normal;font-size:11.5px;font-weight:700;padding:1px 7px;border-radius:99px;background:#EEF1F5;color:#445}.hubt small{color:#667;font-size:12px}
  .hubt.g{border-color:#BBE5C8}.hubt.g b em{background:#E3F6E9;color:#1D6B2F}.hubt.w{border-color:#F3D9A4}.hubt.w b em{background:#FDF1D8;color:#8A5A00}.hubt.r{border-color:#F5C2C2}.hubt.r b em{background:#FDE7E7;color:#B30000}
  .hxw{overflow-x:auto;margin:0 -4px}.hext{width:100%;border-collapse:collapse;font-size:13px}.hext th{background:#F3EEFD;color:#4C1D95;padding:7px 5px;white-space:nowrap;text-align:left;font-size:12px}.hext td{padding:6px 5px;border-top:1px solid #eef1f4}.hext td.c,.hext th:nth-child(n+3){text-align:center}.hext tr.tot td{font-weight:700;background:#FAF8FF}
  .hext select{min-width:64px;padding:5px;font-size:12.5px}.hext td,.hext th{vertical-align:middle}.hext th small{display:block;font-weight:500;opacity:.8;font-size:10.5px}.hext tr.dn td{background:#F2FBF5}.hext td small{display:block;color:#1D6B2F;font-size:11px}.hexc input{width:22px;height:22px}
  .hrow{display:flex;justify-content:space-between;align-items:center;gap:8px;padding:9px 0;border-top:1px solid #eef1f4}.hrow small{display:block;color:#667;font-size:12px}.hmh{margin:12px 0 2px;font-size:13.5px;color:#4C1D95}
  .hleft{border:2px solid #F3D9A4;background:#FFFBEF}.hleft.bad{border-color:#F5C2C2;background:#FFF5F5}`; document.head.appendChild(st); })();
