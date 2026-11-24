'use strict';
// Teacher App — Syllabus table (owner, 24 Nov 2026: "syllabus ka main role hai — syllabus se hi lesson plan, homework, class work,
// test paper banega … syllabus entry aur edit kaise improve ho"; approved plan 1+2+3: topics inside each chapter, month + exam at
// entry time, edit everything in one table). Loaded after teacher-sylsub.js.
//  • Planner → Syllabus → "📋 Edit syllabus table": every chapter in one place — ✓ finished, chapter name, book, pages, MONTH
//    (`plan: 'YYYY-MM'`, the field the ERP progress check and lesson-plan auto fill already read), EXAMS (`ex[]`), TOPICS
//    (`topics[]`, one per line — lesson plan "Auto fill from syllabus" makes one period per topic; homework/class-work ideas read
//    them too). Add / remove / move up-down, then ONE "Save" (asks "Save in Class X — Subject?" so nothing lands in a wrong class).
//  • "↩ Undo last save" restores the list as it was before the last table save (kept on this phone, localStorage `syl_undo|key`).
//  • A renamed chapter keeps its saved chapter text (same as chapEditSave).
//  • The list view shows month and topic count on each chapter.
const TG_MON = ['Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec', 'Jan', 'Feb', 'Mar'];
function tgMonths() {
  const t = todayISO(), y = +t.slice(5, 7) >= 4 ? +t.slice(0, 4) : +t.slice(0, 4) - 1;
  return TG_MON.map((m, i) => [`${i < 9 ? y : y + 1}-${String(i < 9 ? i + 4 : i - 8).padStart(2, '0')}`, m]);
}
function tgMonLabel(ym) { const f = tgMonths().find(x => x[0] === ym); return f ? f[1] : (ym ? String(ym) : ''); }
function tgStart(key) {
  const y = S.syl[key] || { chapters: [] };
  DRAFT.tg = { key, dirty: 0, open: {}, rows: y.chapters.map(ch => ({ n: ch.n, _o: ch.n, book: ch.book || '', pf: ch.pf || '', pt: ch.pt || '', plan: ch.plan || '', ex: (ch.ex || []).slice(), done: ch.done || null, topics: (ch.topics || []).filter(t => typeof t === 'string').join('\n'), _src: ch })) };
}
function tgSet(i, f, v) { const r = DRAFT.tg.rows[i]; if (!r) return; r[f] = v; DRAFT.tg.dirty++; const b = $('tgSave'); if (b) { b.disabled = false; b.textContent = 'Save all changes'; } }
function tgEx(i, id) { const r = DRAFT.tg.rows[i], j = r.ex.indexOf(id); if (j >= 0) r.ex.splice(j, 1); else r.ex.push(id); DRAFT.tg.dirty++; render(); }
function tgDone(i, on) { DRAFT.tg.rows[i].done = on ? (DRAFT.tg.rows[i]._src?.done || todayISO()) : null; DRAFT.tg.dirty++; render(); }
function tgAdd() { const R = DRAFT.tg.rows, last = R[R.length - 1]; R.push({ n: '', book: last ? last.book : '', pf: '', pt: '', plan: last ? last.plan : '', ex: [], done: null, topics: '' }); DRAFT.tg.open[R.length - 1] = 1; DRAFT.tg.dirty++; render();
  setTimeout(() => { const f = document.querySelectorAll('.tgr input.tgn'); if (f.length) f[f.length - 1].focus(); }, 50); }
function tgDel(i) { const r = DRAFT.tg.rows[i]; if (r.n && !confirm(`Remove "${r.n}" from the list? (It goes only when you press Save.)`)) return; DRAFT.tg.rows.splice(i, 1); DRAFT.tg.open = {}; DRAFT.tg.dirty++; render(); }
function tgMove(i, d) { const R = DRAFT.tg.rows, j = i + d; if (j < 0 || j >= R.length) return; [R[i], R[j]] = [R[j], R[i]]; DRAFT.tg.open = {}; DRAFT.tg.dirty++; render(); }
function tgToggle(i) { DRAFT.tg.open[i] = !DRAFT.tg.open[i]; render(); }
function tgAutoTopics(i) {
  const r = DRAFT.tg.rows[i], key = DRAFT.tg.key, t = chapTopics(key, r._o || r.n).filter(x => !/^exercise|^revision/i.test(x));
  if (!t.length) return toast('First add this chapter\'s text (📄 + in the list), then topics come from it.');
  r.topics = t.join('\n'); DRAFT.tg.dirty++; DRAFT.tg.open[i] = 1; render();
}
function tgMonthAll(i) { const R = DRAFT.tg.rows, m = R[i].plan; if (!m) return; R.forEach((r, j) => { if (j > i && !r.plan) r.plan = m; }); DRAFT.tg.dirty++; render(); }
async function tgSave() {
  const G = DRAFT.tg, key = G.key, [c, s] = key.split('|');
  const rows = G.rows.map(r => Object.assign(r, { n: String(r.n || '').trim().slice(0, 120) })).filter(r => r.n.length >= 2);
  const names = rows.map(r => (r.book + '|' + r.n).toLowerCase()), dup = names.find((x, i) => names.indexOf(x) !== i);
  if (dup) return toast('Two chapters have the same name in the same book: ' + dup.split('|')[1]);
  if (!confirm(`Save the syllabus in Class ${c} — ${s}?\n${rows.length} chapter(s).`)) return;
  const old = sylCopy(key); try { localStorage.setItem('syl_undo|' + key, JSON.stringify({ at: Date.now(), y: old })); } catch (e) {}
  const y = sylCopy(key);
  y.chapters = rows.map(r => {
    const ch = Object.assign({}, r._src || {}, { n: r.n, done: r.done || null });
    ['book', 'pf', 'pt', 'plan'].forEach(f => { const v = String(r[f] || '').trim(); if (v) ch[f] = f === 'pf' || f === 'pt' ? v.replace(/[^\dA-Za-z]/g, '').slice(0, 5) : v.slice(0, 80); else delete ch[f]; });
    if (r.ex.length) ch.ex = r.ex.slice(); else delete ch.ex;
    ch.topics = String(r.topics || '').split('\n').map(x => x.replace(/^\s*[-•\d.)]+\s*/, '').trim().slice(0, 120)).filter(x => x.length >= 2).slice(0, 30);
    return ch; });
  put('syl', key, y, 'Syllabus', `${key.replace('|', ' ')}: syllabus table saved — ${y.chapters.length} chapter(s), ${y.chapters.filter(x => x.plan).length} with month, ${y.chapters.reduce((a, x) => a + x.topics.length, 0)} topic(s) — Teacher App`);
  for (const r of rows) if (r._o && r._o.toLowerCase() !== r.n.toLowerCase()) { const tx = chapRec(key, r._o);   // keep the chapter text with the new name
    if (tx && tx.text) { try { await api('teacher_api.php?a=chaptext', { cls: c, subj: s, chapter: r.n, book: r.book, text: tx.text }); await api('teacher_api.php?a=chaptext', { cls: c, subj: s, chapter: r._o, text: '' }); delete CHT[key]; } catch (e) { toast(e.message); } } }
  DRAFT.tg = null; DRAFT.sx = null; DRAFT.sylSelect = null; toast('Syllabus saved ✓'); go('plan', { m: 'syl', key });
}
function tgUndo(key) {
  let u = null; try { u = JSON.parse(localStorage.getItem('syl_undo|' + key) || 'null'); } catch (e) {}
  if (!u || !u.y) return toast('Nothing to undo on this phone.');
  if (!confirm('Bring back the syllabus as it was before the last table save (' + new Date(u.at).toLocaleString('en-IN', { day: 'numeric', month: 'short', hour: 'numeric', minute: '2-digit' }) + ')?')) return;
  put('syl', key, u.y, 'Syllabus', `${key.replace('|', ' ')}: last syllabus table save undone — Teacher App`);
  try { localStorage.removeItem('syl_undo|' + key); } catch (e) {}
  DRAFT.tg = null; toast('Undone ✓'); render();
}
function tgView(list, key) {
  if (!DRAFT.tg || DRAFT.tg.key !== key) tgStart(key);
  const G = DRAFT.tg, [c, s] = key.split('|'), M = tgMonths(), tags = examTags(c).filter(t => !examPast(t) || G.rows.some(r => r.ex.includes(t.id)));
  const books = [...new Set(G.rows.map(r => r.book).filter(Boolean))];
  let undo = null; try { undo = JSON.parse(localStorage.getItem('syl_undo|' + key) || 'null'); } catch (e) {}
  const row = (r, i) => { const nt = String(r.topics || '').split('\n').filter(x => x.trim()).length, op = !!G.open[i];
    return `<div class="tgr ${r.done ? 'dn' : ''}">
      <div class="tg1"><span class="tgi">${i + 1}</span><input class="inp tgn" maxlength="120" value="${esc(r.n)}" placeholder="Chapter name" oninput="tgSet(${i},'n',this.value)">
        <label class="tgd" title="Finished"><input type="checkbox" ${r.done ? 'checked' : ''} onchange="tgDone(${i},this.checked)"><span>Done</span></label></div>
      <div class="tg2"><input class="inp" list="tgBk" maxlength="80" value="${esc(r.book)}" placeholder="Book" oninput="tgSet(${i},'book',this.value)">
        <input class="inp" inputmode="numeric" maxlength="5" value="${esc(r.pf)}" placeholder="pg" aria-label="From page" oninput="tgSet(${i},'pf',this.value)"><input class="inp" inputmode="numeric" maxlength="5" value="${esc(r.pt)}" placeholder="pg" aria-label="To page" oninput="tgSet(${i},'pt',this.value)">
        <select class="inp" aria-label="Month" onchange="tgSet(${i},'plan',this.value);render()"><option value="">Month</option>${M.map(([v, l]) => `<option value="${v}" ${r.plan === v ? 'selected' : ''}>${l}</option>`).join('')}</select></div>
      <div class="tg3">${tags.map(t => `<button type="button" class="exb ${r.ex.includes(t.id) ? 'on' : ''}" onclick="tgEx(${i},'${esc(t.id)}')">${esc(t.l)}</button>`).join('')}
        <button type="button" class="tgtb ${nt ? 'on' : ''}" onclick="tgToggle(${i})">${op ? '▲' : '▼'} Topics ${nt || ''}</button></div>
      ${op ? `<div class="tg4"><textarea class="inp" rows="${Math.min(10, Math.max(3, nt + 1))}" placeholder="One topic per line — e.g.\nFrench society in the 18th century\nOutbreak of the Revolution\nAbolition of slavery" oninput="tgSet(${i},'topics',this.value)">${esc(r.topics)}</textarea>
        <div class="btns"><button type="button" class="btn s l" onclick="tgAutoTopics(${i})">✨ Topics from chapter text</button>${r.plan ? `<button type="button" class="btn s l" onclick="tgMonthAll(${i})">Month ${esc(tgMonLabel(r.plan))} → empty rows below</button>` : ''}</div></div>` : ''}
      <div class="tg5"><button type="button" class="btn s w" ${i ? '' : 'disabled'} onclick="tgMove(${i},-1)" aria-label="Move up">↑</button><button type="button" class="btn s w" ${i < G.rows.length - 1 ? '' : 'disabled'} onclick="tgMove(${i},1)" aria-label="Move down">↓</button><button type="button" class="btn s bad" onclick="tgDel(${i})">✕ Remove</button></div></div>`; };
  return `${keyPick(list, key, "DRAFT.tg=null;go('plan',{m:'syl',tbl:1,key:this.value})")}
    <div class="tgban">📋 Syllabus table · <b>Class ${esc(c)} — ${esc(s)}</b><small>Everything for this class + subject in one place. Topics and month make the lesson plan, class work, homework and test paper.</small></div>
    <div class="btns" style="margin:0 0 8px"><button class="btn l s" onclick="if(!DRAFT.tg||!DRAFT.tg.dirty||confirm('Leave without saving?')){DRAFT.tg=null;go('plan',{m:'syl',key:${jsq(key)}})}">← Back to list</button>${undo ? `<button class="btn l s" onclick="tgUndo(${jsq(key)})">↩ Undo last save</button>` : ''}</div>
    <datalist id="tgBk">${books.map(b => `<option value="${esc(b)}">`).join('')}</datalist>
    ${G.rows.length ? G.rows.map(row).join('') : '<div class="card empty">No chapters yet — press "+ Add chapter".</div>'}
    <button class="btn l full" style="margin:8px 0" onclick="tgAdd()">+ Add chapter</button>
    <div class="sticky"><button id="tgSave" class="btn go full" ${G.dirty ? '' : 'disabled'} onclick="tgSave()">${G.dirty ? 'Save all changes' : 'No changes yet'}</button></div>`;
}
if (typeof vSyl === 'function') {
  const _vsyl2 = vSyl;
  vSyl = function (list) {
    const key = list.some(a => sylKey(a.c, a.s) === P.key) ? P.key : sylKey(list[0].c, list[0].s);
    if (P.tbl) return tgView(list, key);
    let html = _vsyl2.apply(this, arguments);
    const y = S.syl[key] || { chapters: [] }, withM = y.chapters.filter(ch => ch.plan).length, withT = y.chapters.filter(ch => (ch.topics || []).length).length;
    const btn = `<div class="card tgcta"><div class="row"><div><b>📋 Edit syllabus table</b><small>Chapter · topics · month · exam · pages — change anything in one place, one Save.${y.chapters.length ? ` ${withT}/${y.chapters.length} chapters have topics, ${withM}/${y.chapters.length} have a month.` : ''}</small></div><button class="btn go" onclick="DRAFT.tg=null;go('plan',{m:'syl',tbl:1,key:${jsq(key)}})">Open</button></div></div>`;
    const at = html.indexOf('<div class="card"><h3>Chapters');
    html = at >= 0 ? html.slice(0, at) + btn + html.slice(at) : btn + html;
    // month + topics on each chapter of the list
    y.chapters.forEach(ch => { const extra = [ch.plan ? '📅 ' + tgMonLabel(ch.plan) : '', (ch.topics || []).length ? (ch.topics.length + ' topics') : ''].filter(Boolean).join(' · ');
      if (!extra) return; const pg = ch.pf || ch.pt ? 'Page ' + esc(ch.pf || '?') + (ch.pt ? '–' + esc(ch.pt) : '') : 'pages —';
      const needle = `${esc(ch.n)} <span style="font-size:11px;font-weight:600;color:#4F46E5;white-space:nowrap">✏️ Edit</span></b><small>${pg}`, j = html.indexOf(needle);
      if (j >= 0) html = html.slice(0, j + needle.length) + ' · ' + extra + html.slice(j + needle.length); });
    return html;
  };
}
(function () { const st = document.createElement('style'); st.textContent = `.tgban{background:linear-gradient(135deg,#6D28D9,#4F46E5);color:#fff;border-radius:16px;padding:12px 14px;margin:0 0 10px;font-size:15px}.tgban b{font-size:17px}.tgban small{display:block;opacity:.85;font-size:12px;margin-top:3px}
  .tgcta{border:2px solid #7C3AED33;background:#F6F0FE}
  .tgr{background:#fff;border:1px solid #e6e1f3;border-radius:14px;padding:9px;margin:0 0 8px}.tgr.dn{background:#F2FBF5;border-color:#CFE8D6}
  .tg1{display:flex;gap:6px;align-items:center}.tgi{min-width:24px;height:24px;border-radius:50%;background:#EADCFD;color:#5B21B6;font-weight:700;font-size:12px;display:grid;place-items:center}.tg1 .tgn{flex:1;font-weight:600}
  .tgd{display:flex;flex-direction:column;align-items:center;font-size:10px;color:#556}.tgd input{width:20px;height:20px}
  .tg2{display:grid;grid-template-columns:1.6fr .55fr .55fr 1fr;gap:5px;margin-top:6px}.tg2 .inp{padding:8px 6px;font-size:13px;min-width:0}
  .tg3{display:flex;flex-wrap:wrap;gap:5px;margin-top:6px;align-items:center}.tgtb{margin-left:auto;border:1px solid #d9d2ef;background:#fff;border-radius:99px;padding:5px 10px;font:inherit;font-size:12px;font-weight:600;color:#5B21B6}.tgtb.on{background:#EADCFD}
  .tg4{margin-top:6px}.tg4 textarea{width:100%;font-size:13px}.tg5{display:flex;gap:6px;justify-content:flex-end;margin-top:6px}`; document.head.appendChild(st); })();
