'use strict';
// Teacher App — Syllabus "Submit to Principal" + Home cards (owner, 24 Nov 2026: "teachers syllabus kaise dekhenge or submit karne
// ka option nahi hai … jo homework update kar rahen hain vo show kaise hoga"). Loaded after teacher-hwshare.js.
//  • Planner → Syllabus: a card "Submit syllabus to Principal" — saves `sub: {at, by, n, done}` inside the same `syl` record
//    (the teacher may already write `syl` for a class + subject they teach; no new permission). Shows "✓ Submitted <date>",
//    or "changed after submitting — submit again" when chapters were added / ticked later.
//  • Teacher Home: "📘 My syllabus" — each class + subject: done / total and submitted or not, Open → Planner → Syllabus.
//  • Principal Home (vPdash): "📤 Homework of all classes" → the same homework-share screen Rati Ma'am uses (read + share).
//  • Principal Cross-check → Syllabus shows "✓ submitted <date>" on each tile (teacher-ops.js xSyl).
function sylSubState(y) {
  const n = y.chapters.length, done = y.chapters.filter(c => c.done).length, s = y.sub;
  return { n, done, s, stale: !!s && (s.n !== n || s.done !== done) };
}
function sylSubmit(key) {
  const y = sylCopy(key); if (!y.chapters.length) return toast('Add the chapters first, then submit.');
  const done = y.chapters.filter(c => c.done).length;
  y.sub = { at: Date.now(), by: me(), n: y.chapters.length, done };
  put('syl', key, y, 'Syllabus', `${key.replace('|', ' ')}: syllabus submitted to the Principal (${done}/${y.chapters.length} chapters finished) — Teacher App`);
  toast('Syllabus submitted to the Principal ✓'); render();
}
function sylSubCard(key) {
  const y = S.syl[key] || { chapters: [] }, st = sylSubState(y), [c, s] = key.split('|');
  const when = st.s ? new Date(st.s.at).toLocaleString('en-IN', { day: 'numeric', month: 'short', hour: 'numeric', minute: '2-digit' }) : '';
  return `<div class="card sylsub ${st.s && !st.stale ? 'ok' : ''}"><h3>📤 Submit to Principal <small>Class ${esc(c)} · ${esc(s)}</small></h3>
    ${!st.n ? '<p class="mut" style="margin:0">First add the chapters below (book name + chapters, or a photo of the contents page). Then press Submit.</p>'
      : st.s && !st.stale ? `<p style="margin:0 0 8px"><b style="color:#1D6B2F">✓ Submitted ${esc(when)}</b> · ${st.done}/${st.n} chapters finished. The Principal sees it in Cross-check → Syllabus.</p>
        <button class="btn l full" onclick="sylSubmit(${jsq(key)})">Submit again</button>`
      : `<p class="mut" style="margin:0 0 8px">${st.stale ? `<b style="color:#B45309">Changed after submitting (${esc(when)}).</b> ` : ''}${st.n} chapters · ${st.done} finished. When the list is right, submit it to the Principal.</p>
        <button class="btn go full" onclick="sylSubmit(${jsq(key)})">📤 Submit syllabus to Principal</button>`}</div>`;
}
if (typeof vSyl === 'function') {
  const _vsyl = vSyl;
  vSyl = function (list) {
    const html = _vsyl.apply(this, arguments), key = list.some(a => sylKey(a.c, a.s) === P.key) ? P.key : sylKey(list[0].c, list[0].s);
    let out = html; const sel = DRAFT.sylSelect, others = list.filter(a => sylKey(a.c, a.s) !== key);
    // chapters added under the wrong class / subject → move them (owner, 24 Nov 2026: "galat class me entry ho jaa raha hai, edit ka option do")
    if (sel && sel.key === key && others.length) {
      const mark = 'Remove all chapters of this book</button></div>', i = out.indexOf(mark);
      if (i > 0) out = out.slice(0, i + mark.length) + `<div class="sylmv"><b>Wrong class or subject? Move these chapters to:</b>
        <select id="sylMvTo" class="inp">${others.map(a => `<option value="${esc(sylKey(a.c, a.s))}">Class ${esc(a.c)} — ${esc(a.s)}</option>`).join('')}</select>
        <div class="btns"><button type="button" class="btn s go" onclick="sylMove(${jsq(key)},false)">Move selected</button><button type="button" class="btn s l" onclick="sylMove(${jsq(key)},true)">Move whole book</button></div></div>` + out.slice(i + mark.length);
    }
    const at = out.indexOf('<div class="card"><h3>Photo of the contents page');
    return at > 0 ? out.slice(0, at) + sylSubCard(key) + out.slice(at) : out + sylSubCard(key);
  };
}
function sylMove(key, all) {
  const sel = DRAFT.sylSelect, to = ($('sylMvTo') || {}).value; if (!sel || sel.key !== key || !to || to === key) return;
  const src = sylCopy(key), dst = sylCopy(to);
  const ids = src.chapters.flatMap((ch, i) => (ch.book || '') === sel.book && (all || sel.ids.has(i)) ? [i] : []);
  if (!ids.length) return toast('Tick the chapters to move first.');
  const [tc, ts] = to.split('|');
  if (!confirm(`Move ${ids.length} chapter(s) to Class ${tc} — ${ts}?`)) return;
  let moved = 0, same = 0;
  ids.forEach(i => { const ch = src.chapters[i]; if (dst.chapters.some(x => x.n.toLowerCase() === ch.n.toLowerCase() && (x.book || '') === (ch.book || ''))) { same++; return; } const c2 = Object.assign({}, ch); delete c2.ex; dst.chapters.push(c2); moved++; });
  src.chapters = src.chapters.filter((_, i) => !ids.includes(i));
  delete src.sub; delete dst.sub;   // both lists changed → the teacher submits them again
  put('syl', to, dst, 'Syllabus', `${to.replace('|', ' ')}: ${moved} chapter(s) moved in from ${key.replace('|', ' ')} — Teacher App`);
  put('syl', key, src, 'Syllabus', `${key.replace('|', ' ')}: ${ids.length} chapter(s) moved to ${to.replace('|', ' ')} (wrong class / subject) — Teacher App`);
  DRAFT.sylSelect = null; DRAFT.sx = null; DRAFT.sb = null;
  toast(`${moved} chapter(s) moved to Class ${tc} — ${ts}${same ? ` (${same} were already there)` : ''}. Tick their exams there.`);
  go('plan', { m: 'syl', key: to });
}
if (typeof vHome === 'function') {
  const _vhs = vHome;
  vHome = function () {
    const html = _vhs.apply(this, arguments);
    if (!S || !ME || isP() || (typeof pMode === 'function' && pMode())) return html;
    const A = myAllocs().filter(a => !/\(optional\)|Games|Library|Moral|Rhymes|Play|Craft|Activity|PT\b/i.test(a.s)); if (!A.length) return html;
    const R = A.map(a => { const k = sylKey(a.c, a.s), st = sylSubState(S.syl[k] || { chapters: [] }); return { a, k, st }; });
    const sub = R.filter(r => r.st.s && !r.st.stale).length;
    return html + `<div class="card"><div class="row"><div><b>📘 My syllabus</b><small>${sub} of ${R.length} submitted to the Principal</small></div><button class="btn ${sub < R.length ? 'go' : 'l'} s" onclick="go('plan',{m:'syl'})">Open</button></div>
      <div style="margin-top:6px">${R.map(r => `<button type="button" class="sylrow" onclick="go('plan',{m:'syl',key:${jsq(r.k)}})"><span>Class ${esc(r.a.c)} · ${esc(r.a.s)}</span><small>${!r.st.n ? '<i style="color:#B30000">chapters not added</i>' : r.st.done + '/' + r.st.n + ' done'} · ${r.st.s && !r.st.stale ? '<b style="color:#1D6B2F">✓ submitted</b>' : '<b style="color:#B45309">not submitted</b>'}</small></button>`).join('')}</div></div>`;
  };
}
if (typeof vPdash === 'function') {
  const _vpd = vPdash;
  vPdash = function () {
    const html = _vpd.apply(this, arguments);
    return `<div class="card lpb-home"><div class="row"><div><b>📤 Homework of all classes</b><small>Class work + homework the teachers wrote today, class by class — read it or send it to the class WhatsApp groups.</small></div><button class="btn go" onclick="go('hwshare')">Open</button></div></div>` + html;
  };
}
(function () { const st = document.createElement('style'); st.textContent = `.sylmv{margin-top:10px;padding:10px;border:1px dashed #c9d2dc;border-radius:12px;background:#F8FAFC}.sylmv b{display:block;font-size:13px;margin-bottom:6px}.sylmv .btns{margin-top:8px}
  .sylsub.ok{border:2px solid #22C55E33;background:#F2FBF5}
  .sylrow{display:flex;justify-content:space-between;gap:8px;width:100%;border:0;border-top:1px solid #eef1f4;background:none;padding:8px 2px;font:inherit;text-align:left;cursor:pointer}.sylrow span{font-weight:600;font-size:13.5px}.sylrow small{font-size:12px;color:#556;white-space:nowrap}`; document.head.appendChild(st); })();
