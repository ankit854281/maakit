// ============================================================================
//  Teacher App — owner request, 5 Nov 2026 (loaded BEFORE teacher.js; only functions + its own state here)
//
//  1. Classroom: the class's attendance in small letters on every diary card; question-number chips
//     (tap 6 7 8 9 → "Q. 6–9"); as soon as class work is written, homework ideas that follow from it.
//  2. Messages: "Attendance" instead of "Absent" — the children come from the attendance register.
//  3. Syllabus: book name + total chapters → rows (Sr no · chapter · from page · to page) like a contents page;
//     tick each chapter for UT-3 / UT-4 / Annual …; parents see which chapters come in which exam (api.php pdata.examSyl).
//     Each chapter can get its full text pasted (kind tchChap, teacher_api.php?a=chaptext) — it feeds the homework
//     ideas, the lesson-plan auto fill and the question-paper maker.
//  4. Paper: question paper from the chapters of an exam (fill in the blanks, true/false, questions, meanings) —
//     made on the phone from the chapter text and the chapter notes; the teacher edits and prints. Nothing is invented
//     beyond the chapter's own sentences.
//  5. My work check (Today) — the teacher's own cross-check, same list the Principal sees (workOf / setupOf).
//  6. Students: profile card (admission no., DOB, parents, mobile, address) from the school record.
//  Data formats of existing records are unchanged; syl chapters only gain optional fields: book, pf, pt (pages), ex [exam ids].
// ============================================================================

// ---------------------------------------------------------------- 1. Classroom
function attLine(date, cls) {
  if (!cls || !S || !isWorkDay(date, cls)) return '';
  const a = attOf(date, cls), n = studentsOf(cls).length;
  if (!n) return '';
  if (!a) return `<div class="attl no">Class ${esc(cls)} · attendance not marked yet</div>`;
  const ab = (a.absent || []).map(studentById).filter(Boolean), lv = ab.filter(s => (a.st || {})[s.id] === 'L');
  const names = ab.slice(0, 6).map(s => esc(String(s.name).split(' ')[0]) + ((a.st || {})[s.id] === 'L' ? ' (L)' : '')).join(', ');
  return `<div class="attl"><b>Class ${esc(cls)}</b> · ${n - ab.length}/${n} present${ab.length ? ` · ${ab.length - lv.length} absent${lv.length ? ', ' + lv.length + ' leave' : ''}: ${names}${ab.length > 6 ? ' +' + (ab.length - 6) : ''}` : ' · nobody absent'}</div>`;
}

// Question numbers: tap 6, 7, 8, 9 → "Q. 6–9" goes into the box (and is updated on every tap).
const QP = {};
function qpKey(id, kind) { return id + '-' + kind + '|' + (P.date || todayISO()); }
function qFmt(nums) {
  const a = [...new Set(nums.map(Number))].filter(n => n > 0).sort((x, y) => x - y), out = [];
  for (let i = 0; i < a.length; i++) { let j = i; while (j + 1 < a.length && a[j + 1] === a[j] + 1) j++; out.push(j - i >= 2 ? a[i] + '–' + a[j] : j > i ? a[i] + ', ' + a[j] : String(a[i])); i = j; }
  return out.length ? 'Q. ' + out.join(', ') : '';
}
function qPick(id, kind) {
  const q = QP[qpKey(id, kind)] || { sel: [], ex: '' }, max = Math.max(20, ...q.sel.map(Number));
  return `<div class="qpk"><span class="ql">Q no.</span><div class="qn">${Array.from({ length: Math.min(40, max) }, (_, i) => i + 1).map(n => `<button type="button" class="${q.sel.includes(n) ? 'on' : ''}" onclick="qTap('${id}','${kind}',${n},this)">${n}</button>`).join('')}</div>
    <input class="inp mini qex" maxlength="30" value="${esc(q.ex)}" placeholder="Ex 4.1 / Page 34" oninput="qEx('${id}','${kind}',this.value)" aria-label="Exercise or page"></div>`;
}
function qTap(id, kind, n, btn) {
  const k = qpKey(id, kind), q = QP[k] = QP[k] || { sel: [], ex: '', last: '' };
  q.sel = q.sel.includes(n) ? q.sel.filter(x => x !== n) : [...q.sel, n]; if (btn) btn.classList.toggle('on', q.sel.includes(n));
  qApply(id, kind);
}
function qEx(id, kind, v) { const k = qpKey(id, kind), q = QP[k] = QP[k] || { sel: [], ex: '', last: '' }; q.ex = v.trim(); if (q.sel.length) qApply(id, kind); }
function qApply(id, kind) {
  const q = QP[qpKey(id, kind)], el = $(id + '-' + kind); if (!el || !q) return;
  const core = q.sel.length ? (q.ex ? q.ex + ' ' : '') + qFmt(q.sel) + (kind === 'cw' ? ' done in class.' : '') : '';
  let v = el.value;
  if (q.last && v.includes(q.last)) v = core ? v.replace(q.last, core) : v.replace(q.last, '').replace(/\s{2,}/g, ' ').trim();
  else if (core) v = (v.trim() ? v.trim() + ' ' : '') + core;
  q.last = core; el.value = v; keepNote(el);
  if (kind === 'h' && core && !$(id + '-min').value) { $(id + '-min').value = 15; keepNote($(id + '-min')); }
  if (kind === 'cw') cwIdeas(id);
}

// Homework ideas from the class work just written (owner: "jaise hi CW fill karen usse related suggestion aane lage").
function qNums(text) {
  const out = []; const re = /\b(?:Q|Que|Ques|Question|Questions|प्रश्न)\.?\s*((?:\d{1,3}\s*(?:[-–—]|to|,|&|and)?\s*)+)/gi; let m;
  while ((m = re.exec(String(text)))) { const part = m[1]; const rr = /(\d{1,3})\s*(?:[-–—]|to)\s*(\d{1,3})|(\d{1,3})/g; let x;
    while ((x = rr.exec(part))) { if (x[1]) { const a = +x[1], b = +x[2]; if (b >= a && b - a < 40) for (let i = a; i <= b; i++) out.push(i); } else out.push(+x[3]); } }
  return [...new Set(out)].filter(n => n > 0 && n < 200);
}
function cwHwIdeas(cw, topic, key) {
  const out = [], ch = topic || 'the chapter', t = String(cw || '');
  const exM = t.match(/\b(Ex(?:ercise)?\.?\s*\d+(?:\.\d+)?|Page\s*\d+|पृष्ठ\s*\d+)/i), ex = exM ? exM[1] : '';
  const qs = qNums(t);
  if (qs.length) { const mx = Math.max(...qs);
    out.push(`Write ${qFmt(qs)}${ex ? ' of ' + ex : ''} (done in class) neatly in the homework copy.`);
    out.push(`Do the next questions: ${qFmt([mx + 1, mx + 2, mx + 3, mx + 4])}${ex ? ' of ' + ex : ''}.`);
    out.push(`Learn the answers of ${qFmt(qs)}.`); }
  if (/\bread|पढ़|पढा|पढ़ा/i.test(t)) out.push(`Read ${ch} again and write 5 difficult words with their meanings.`);
  if (/diagram|map|draw|figure|चित्र|नक्शा|मानचित्र/i.test(t)) out.push('Draw the diagram / map done in class two times.');
  if (/example|उदाहरण/i.test(t)) out.push('Write 3 more examples like the ones done in class.');
  if (/\btest\b|परीक्षा/i.test(t)) out.push("Correct the mistakes of today's test and learn them.");
  if (/poem|कविता/i.test(t)) out.push('Learn the poem by heart and write it once.');
  if (/grammar|tense|noun|verb|pronoun|adjective|व्याकरण|संज्ञा|क्रिया/i.test(t)) out.push("Make 5 sentences of your own on today's grammar topic.");
  if (/sum|solve|equation|theorem|हल|सवाल/i.test(t) && !qs.length) out.push('Solve 5 more sums like the ones done in class.');
  const txt = chapterText(key, topic);
  if (txt) { const w = keyWords(txt).slice(0, 6); if (w.length >= 3) out.push('Learn the meanings and spellings of: ' + w.join(', ') + '.');
    textQuestions(txt).slice(0, 2).forEach(q => out.push('Answer in the homework copy: ' + q)); }
  if (t.trim() && t.trim().length > 6) out.push(`Revise today's class work: “${t.trim().replace(/\s+/g, ' ').slice(0, 80).replace(/[\s.।,;:]+$/, '')}”.`);
  return [...new Set(out)].slice(0, 7);
}
function cwIdeas(id) {
  const box = $(id + '-idea'), cw = $(id + '-cw'); if (!box || !cw) return;
  const topic = (($(id + '-t') || {}).value || '').trim() || box.dataset.nx || '';
  if (box.dataset.key) needChapTexts(box.dataset.key);
  const ideas = cw.value.trim() ? cwHwIdeas(cw.value, topic, box.dataset.key) : [];
  if (!ideas.length) { box.replaceChildren(); return; }
  box.innerHTML = `<small>From today's class work · tap to add</small>${ideas.map(x => `<button type="button" class="chip">${esc(x)}</button>`).join('')}`;
  box.querySelectorAll('button').forEach((b, i) => { b.onmousedown = e => e.preventDefault(); b.onclick = () => {
    const el = $(id + '-h'); if (!el || el.value.includes(ideas[i])) return;
    el.value = (el.value.trim() ? el.value.trim() + ' ' : '') + ideas[i]; el.classList.remove('auto-filled'); void el.offsetWidth; el.classList.add('auto-filled'); keepNote(el);
    if (!$(id + '-min').value) { $(id + '-min').value = 15; keepNote($(id + '-min')); toast('Homework time set to 15 minutes. Change it if needed.'); } }; });
}
document.addEventListener('input', e => { const f = e.target && e.target.dataset && e.target.dataset.cwfor; if (f) cwIdeas(f); });
function afterRender() { document.querySelectorAll('[data-cwfor]').forEach(el => { if (el.value.trim()) cwIdeas(el.dataset.cwfor); }); }

// ---------------------------------------------------------------- 2. Messages → Attendance (from the register)
function msgAttKids(c, date) { const a = attOf(date, c); return a ? (a.absent || []).map(studentById).filter(Boolean) : []; }
function msgAttPick(c, D) {
  const date = D.o.date || todayISO();
  if (date < (loadedFrom || '') && !DAYS_LOADED[date]) { ensureDate(date).then(() => { D.attK = null; render(); }); return; }
  const k = date + '|' + (attOf(date, c) ? (attOf(date, c).at || 1) : 0);
  if (D.attK !== k) { D.attK = k; D.sel = {}; msgAttKids(c, date).forEach(s => D.sel[s.id] = true); }
}
function msgAttBox(c, D) {
  const date = D.o.date || todayISO(), a = attOf(date, c), kids = msgAttKids(c, date), nL = a ? kids.filter(s => (a.st || {})[s.id] === 'L').length : 0;
  return `<div class="fld">Day<input type="date" class="inp" value="${esc(date)}" max="${todayISO()}" onchange="DRAFT.msg.o.date=this.value;DRAFT.msg.attK=null;render()"></div>
    ${a ? `<p class="mut" style="margin:6px 0 0">From the attendance register of Class ${esc(c)}: <b>${kids.length - nL} absent</b>${nL ? ` · <b>${nL} on leave</b>` : ''}. They are already chosen below — the right message (absent / leave) goes to each.</p>`
      : !isWorkDay(date, c) ? `<p class="mut" style="margin:6px 0 0">No school on this day.</p>`
      : `<div class="ban warn" style="margin-top:8px"><div>Attendance of Class ${esc(c)} is not marked for this day.${myCT().includes(c) ? `<br><button class="btn s go" style="margin-top:6px" onclick="go('att',{c:${jsq(c)},date:'${date}'})">Mark attendance</button>` : ''}</div></div>`}`;
}

// ---------------------------------------------------------------- 6. Student profile
function stuProfile(s) {
  const dob = /^\d{4}-\d{2}-\d{2}$/.test(String(s.dob || '')) ? s.dob.split('-').reverse().join('-') : s.dob;
  const f = [['Admission no.', s.adm], ['Roll no.', s.rollno], ['Date of birth', dob], ['Father', s.father], ['Mother', s.mother], ['Mobile', s.phone], ['Address', s.address]].filter(x => x[1]);
  if (!f.length) return '';
  return `<div class="card"><h3>Profile <small>school record</small></h3><div class="prof">${f.map(([l, v]) => `<div class="${l === 'Address' ? 'wide' : ''}"><span>${l}</span><b>${l === 'Mobile' ? `<a href="tel:${esc(String(v).replace(/[^\d+]/g, ''))}">${esc(v)}</a>` : esc(v)}</b></div>`).join('')}</div>
    <p class="mut" style="margin:6px 0 0">Something wrong here? Tell the school office — they correct it in Student Master.</p></div>`;
}

// ---------------------------------------------------------------- 3. Exams, books, chapter text
const EXAM_STD = [['ut1', 'UT-1', 7], ['ut2', 'UT-2', 8], ['hy', 'Half-Yearly', 10], ['ut3', 'UT-3', 12], ['ut4', 'UT-4', 13], ['ann', 'Annual', 15]];   // month no. from April = 4
function examStdId(name) {
  const n = String(name || '').toLowerCase().replace(/[^a-z0-9]/g, '');
  const m = n.match(/^(?:ut|unittest|periodictest|pt)([1-4])$/) || n.match(/^(?:ut|unittest|periodictest)([1-4])/); if (m) return 'ut' + m[1];
  if (/halfyearly|^hy$|midterm|^term1$|^term1exam/.test(n)) return 'hy';
  if (/annual|final|^ann$|^term2$/.test(n)) return 'ann';
  return null;
}
// Exams for one class: the standard UT-1 … Annual list + any other exam of the school's exam list that is for this class
// (e.g. Pre-Board for 9th–10th), in date order. `mo` = month counted from April (= 4) so past / coming can be told apart.
function sesMonth(date) { const m = +String(date).slice(5, 7); return m >= 4 ? m : m + 12; }
function examTags(cls) {
  const c = cls || (String(P.key || '').split('|')[0]) || '';
  const out = EXAM_STD.map(([id, l, mo]) => ({ id, l, mo, date: '' }));
  (S.exams || []).forEach(e => { if (c && e.classes && e.classes.length && !e.classes.includes(c)) return;
    const sid = examStdId(e.name) || examStdId(e.id), hit = sid && out.find(x => x.id === sid);
    if (hit) { if (!hit.date) { hit.date = e.date || ''; hit.to = examEnd(e); } }
    else if (e.id && !out.some(x => x.id === e.id)) out.push({ id: e.id, l: String(e.name || e.id).replace(/\s*\((?:classes?|class)\s[^)]*\)\s*$/i, ''), mo: e.date ? sesMonth(e.date) + 0.5 : 99, date: e.date || '', to: examEnd(e) }); });
  return out.sort((a, b) => a.mo - b.mo);
}
function examPast(t) {
  const today = todayISO(); if (t.date) return (t.to || t.date) < today;
  const m = +today.slice(5, 7), idx = m >= 4 ? m : m + 12;      // session month (April = 4 … March = 15)
  return t.mo < 99 && t.mo < idx;
}
function examLabel(id) { const t = examTags().find(x => x.id === id); return t ? t.l : id; }
function upcomingTags() { return examTags().filter(t => !examPast(t)); }

const CHT = {};      // 'cls|subj' → { loading, list:[{chapter, book, words, text}] }
function needChapTexts(key) {
  if (!key || CHT[key]) return CHT[key] || {};
  const [c, s] = key.split('|'); CHT[key] = { loading: true, list: [] };
  api(`teacher_api.php?a=chaptext&full=1&cls=${encodeURIComponent(c)}&subj=${encodeURIComponent(s)}`).then(r => { CHT[key] = { list: r.chapters || [] }; softRender(); })
    .catch(e => { CHT[key] = { err: e.message, list: [] }; });
  return CHT[key];
}
function chapRec(key, name) {
  const L = (CHT[key] || {}).list || [], n = String(name || '').trim().toLowerCase(); if (!n) return null;
  return L.find(x => String(x.chapter).toLowerCase() === n) || L.find(x => n.includes(String(x.chapter).toLowerCase()) && String(x.chapter).length > 3)
    || L.find(x => String(x.chapter).toLowerCase().includes(n) && n.length > 3) || null;
}
function chapterText(key, name) { const r = chapRec(key, name); return r ? r.text || '' : ''; }
function chapHint(key, nx, purpose) {
  const y = S.syl[key]; if (!y || !(y.chapters || []).length || !nx) return '';
  const C = CHT[key]; if (!C || C.loading || chapRec(key, nx)) return '';
  return `<div class="hint-t">💡 Add the full text of “${esc(nx)}” (Planner → Syllabus → 📄). Then the ${esc(purpose)} is made from the chapter itself.
    <button class="chip" onclick="go('plan',{m:'syl',key:${jsq(key)}})">Open syllabus</button></div>`;
}
// Simple reading of a chapter text — headings, sentences, key words, questions.
const STOP = new Set(('about above after again against because before being below between could during every first from have having into itself might other their there these they this those through under until very were what when where which while whom with would your yours shall should also only than then them some such each more most many much make made like just over upon onto ours your will been does done here said says page chapter lesson exercise answer question questions write read following given below class ' +
  'में की के का को से और है हैं था थे थी भी एक यह वह इस उस लिए कर करना करते किया होता होती होते गया गई अपने अपनी नहीं तो पर ही जो कि जब तक साथ बहुत वाले वाली रहा रही रहे').split(/\s+/));
function textSentences(txt) {
  return String(txt || '').replace(/\r/g, '').replace(/\n(?=[a-zऀ-ॿ])/g, ' ').split(/(?<=[.!।])\s+|\n+/).map(x => x.replace(/\s+/g, ' ').trim())
    .filter(x => x.length >= 35 && x.length <= 190 && !/\?\s*$/.test(x) && /[A-Za-zऀ-ॿ]{3}/.test(x) && !/^(fig|figure|table|activity|exercise|chapter)\b/i.test(x));
}
function textQuestions(txt) {
  const q = String(txt || '').split(/\n+|(?<=\?)\s+/).map(x => x.replace(/^\s*(?:Q\.?\s*)?\(?\d{1,3}[.)\]]?\s*|^\s*[a-z][.)]\s*/i, '').replace(/\s+/g, ' ').trim()).filter(x => /\?\s*$/.test(x) && x.length >= 12 && x.length <= 220);
  return [...new Set(q)];
}
function keyWords(txt) {
  const f = {}, first = {};
  (String(txt || '').match(/[A-Za-z][a-z]{4,}|[ऀ-ॿ]{4,}/g) || []).forEach((w, i) => { const k = w.toLowerCase(); if (STOP.has(k)) return; f[k] = (f[k] || 0) + 1; if (!(k in first)) first[k] = w; });
  return Object.keys(f).filter(k => f[k] >= 2).sort((a, b) => f[b] - f[a] || b.length - a.length).slice(0, 40).map(k => first[k]);
}
function chapTopics(key, name) {
  const t = chapterText(key, name); if (!t) return [];
  const heads = [...new Set(t.split('\n').map(x => x.trim()).filter(x => x.length >= 4 && x.length <= 60 && !/[.,;:?!।]$/.test(x) && /[A-Za-zऀ-ॿ]{3}/.test(x) && !/^\d+$/.test(x) && x.split(/\s+/).length <= 8))];
  if (heads.length >= 3) return heads.slice(0, 10).concat(['exercise / question-answer', 'revision and test']);
  const words = t.split(/\s+/).filter(Boolean), parts = [];
  for (let i = 0; i < words.length && parts.length < 6; i += 160) parts.push('reading: “' + words.slice(i, i + 5).join(' ') + '…”');
  return parts.concat(['exercise / question-answer', 'revision and test']);
}

// ---------------------------------------------------------------- Syllabus screen
function sylBooks(y) { const b = []; (y.chapters || []).forEach(ch => { const k = ch.book || ''; if (!b.includes(k)) b.push(k); }); return b; }
function vSyl(list) {
  const key = list.some(a => sylKey(a.c, a.s) === P.key) ? P.key : sylKey(list[0].c, list[0].s), [c, s] = key.split('|'), y = S.syl[key] || { chapters: [] };
  const C = needChapTexts(key), done = y.chapters.filter(x => x.done).length, sc = DRAFT.sc && DRAFT.sc.key === key ? DRAFT.sc : null;
  const showPast = !!P.past, tags = examTags(c).filter(t => showPast || !examPast(t) || y.chapters.some(ch => (ch.ex || []).includes(t.id)));
  if (!DRAFT.sx || DRAFT.sx.key !== key) DRAFT.sx = { key, ex: y.chapters.map(ch => (ch.ex || []).slice()), changed: 0 };
  const X = DRAFT.sx, f = P.f || '', books = sylBooks(y), withText = y.chapters.filter(ch => chapRec(key, ch.n)).length;
  const cnt = id => X.ex.filter(e => (e || []).includes(id)).length;
  const row = (ch, i, sr) => { if (f && !(X.ex[i] || []).includes(f)) return ''; const tx = chapRec(key, ch.n);
    return `<div class="syr ${ch.done ? 'dn' : ''}"><label class="syd" aria-label="Finished"><input type="checkbox" ${ch.done ? 'checked' : ''} onchange="sylTick(${jsq(key)},${i},this.checked)"></label>
      <button type="button" class="syn" onclick="chapEdit(${jsq(key)},${i})"><b>${sr}. ${esc(ch.n)}</b><small>${ch.pf || ch.pt ? 'Page ' + esc(ch.pf || '?') + (ch.pt ? '–' + esc(ch.pt) : '') : 'pages —'}${ch.done ? ' · finished ' + esc(fmtDate(ch.done)) : ''}</small></button>
      <button type="button" class="txb ${tx ? 'on' : ''}" onclick="chapOpen(${jsq(key)},${i})" aria-label="Chapter text">${tx ? '📄 ' + (tx.words > 999 ? Math.round(tx.words / 100) / 10 + 'k' : tx.words) : '📄 +'}</button>
      <div class="syx">${tags.map(t => `<button type="button" class="exb ${(X.ex[i] || []).includes(t.id) ? 'on' : ''}" onclick="sylExTap(${i},'${esc(t.id)}')">${esc(t.l)}</button>`).join('')}</div></div>`; };
  const groups = books.map(b => { const idx = y.chapters.map((ch, i) => [ch, i]).filter(([ch]) => (ch.book || '') === b); let sr = 0;
    const rows = idx.map(([ch, i]) => row(ch, i, ++sr)).join(''); return rows ? `<div class="sybk"><h4>📘 ${b ? esc(b) : 'Book name not written'} <small>${idx.length} chapter(s)</small></h4>${rows}</div>` : ''; }).join('');
  const share = f ? sylShareText(key, f, X) : '';
  return `${keyPick(list, key, "DRAFT.sc=null;DRAFT.sb=null;DRAFT.sx=null;go('plan',{m:'syl',key:this.value})")}
    ${DRAFT.sylHint === key ? `<div class="ban ok"><div><b>Chapters saved.</b> Next step: tap <b>📄 +</b> on a chapter and paste its full text (copy it with Google Lens from the book page, or from the e-book / PDF). Homework ideas, the lesson plan and the question paper are then made from the chapter itself.<br><button class="btn s w" style="margin-top:6px" onclick="DRAFT.sylHint=null;render()">OK</button></div></div>` : ''}
    <div class="card"><h3>Chapters <small>${done}/${y.chapters.length} finished · ${withText} with text</small></h3>
      ${y.chapters.length ? `<div class="chips exf"><button class="chip ${!f ? 'on' : ''}" onclick="go('plan',{m:'syl',key:${jsq(key)},past:${showPast ? 1 : 0}})">All <b>${y.chapters.length}</b></button>${tags.map(t => `<button class="chip ${f === t.id ? 'on' : ''}" onclick="go('plan',{m:'syl',key:${jsq(key)},f:'${esc(t.id)}',past:${showPast ? 1 : 0}})">${esc(t.l)} <b>${cnt(t.id)}</b></button>`).join('')}
        <button class="chip ghost" onclick="go('plan',{m:'syl',key:${jsq(key)},f:${jsq(f)},past:${showPast ? 0 : 1}})">${showPast ? 'Hide past exams' : 'Past exams'}</button></div>
        <p class="mut hint">Tick ✓ when a chapter is finished. Tap an exam (${esc(tags.slice(0, 3).map(t => t.l).join(', '))}) on a chapter if it comes in that exam, then press Save. Tap a chapter name to change it or its pages.</p>` : ''}
      ${groups || (y.chapters.length ? '<div class="empty">No chapter is ticked for this exam yet.</div>' : '<div class="empty">No chapters yet. Add the book below.</div>')}
      ${X.changed ? `<div class="sticky"><button class="btn go full" onclick="sylExSave(${jsq(key)})">Save exam chapters · ${X.changed} change(s)</button></div>` : ''}
      ${share && !X.changed ? `<a class="btn wa full" style="margin-top:8px" target="_blank" rel="noopener" href="${wa('', share)}">${ic('wa')} Send ${esc(examLabel(f))} syllabus to the class parents' group</a>` : ''}</div>
    ${sylAddCard(key, y)}
    <div class="card"><h3>Or: photo of the contents page</h3><p class="mut" style="margin:0 0 8px">Photo of the book’s Contents / विषय-सूची page — the chapters are read for you (for the book written above). Check them, then save.</p>
      <label class="btn l full filebtn">${ic('cam')} Take / choose photo<input type="file" accept="image/*" capture="environment" multiple onchange="sylPhoto(this.files,${jsq(key)})"></label>
      ${DRAFT.busy && DRAFT.busyK === key ? `<p class="mut">${esc(DRAFT.busy)}</p>` : ''}
      ${sc ? `<div class="fld" style="margin-top:10px">Chapters found${sc.book ? ' · ' + esc(sc.book) : ''} (${sc.via === 'ai' ? 'read by AI' : 'read by the phone — check carefully'}) — one per line<textarea id="scL" class="inp" rows="8">${esc(sc.list.join('\n'))}</textarea></div>
        <label class="chk" style="margin-top:6px"><input id="scR" type="checkbox" ${y.chapters.length ? '' : 'checked'}> Replace my chapter list (ticks of chapters with the same name are kept)</label>
        <button class="btn go full" style="margin-top:8px" onclick="sylSave(${jsq(key)})">Save chapters</button>` : ''}</div>
    ${C.err ? `<p class="mut">Chapter texts could not load: ${esc(C.err)}</p>` : ''}`;
}
function sylAddCard(key, y) {
  if (!DRAFT.sb || DRAFT.sb.key !== key) DRAFT.sb = { key, book: '', rows: [] };
  const B = DRAFT.sb, books = sylBooks(y).filter(Boolean), have = y.chapters.filter(ch => (ch.book || '') === B.book).length;
  return `<div class="card"><h3>Add chapters — like the contents page</h3>
    <div class="two"><div class="fld">Book name<input id="sylBk" class="inp" maxlength="80" list="sylBkL" value="${esc(B.book)}" placeholder="e.g. Beehive / Grammar" oninput="DRAFT.sb.book=this.value.trim()"><datalist id="sylBkL">${books.map(b => `<option value="${esc(b)}">`).join('')}</datalist></div>
      <div class="fld">Total chapters<div class="row" style="gap:6px"><input id="sylNc" class="inp" type="number" inputmode="numeric" min="1" max="40" value="${B.rows.length || ''}" placeholder="e.g. 12"><button class="btn s" style="white-space:nowrap" onclick="sylMake()">Make rows</button></div></div></div>
    ${books.length > 1 || (books.length && !B.book) ? `<div class="chips" style="margin-top:4px">${books.map(b => `<button type="button" class="chip ${B.book === b ? 'on' : ''}" onclick="DRAFT.sb.book=${jsq(b)};render()">${esc(b)}</button>`).join('')}</div>` : ''}
    ${B.rows.length ? `<div class="syin"><div class="syh"><span>Sr</span><span>Chapter name</span><span>From</span><span>To</span></div>${B.rows.map((r, i) => `<div class="syi"><span>${have + i + 1}</span>
        <input class="inp" maxlength="120" value="${esc(r.n)}" placeholder="Chapter ${have + i + 1} name" oninput="DRAFT.sb.rows[${i}].n=this.value">
        <input class="inp" inputmode="numeric" maxlength="5" value="${esc(r.pf)}" placeholder="pg" oninput="DRAFT.sb.rows[${i}].pf=this.value;sylNextPage(${i},this.value)">
        <input class="inp" inputmode="numeric" maxlength="5" value="${esc(r.pt)}" placeholder="pg" oninput="DRAFT.sb.rows[${i}].pt=this.value"></div>`).join('')}</div>
      <p class="mut hint">Write each chapter's name and its pages, like the book's contents. Empty rows are left out.</p>
      <button class="btn go full" onclick="sylRowsSave(${jsq(key)})">Save chapters${B.book ? ' of ' + esc(B.book) : ''}</button>`
    : `<p class="mut" style="margin:6px 0 0">A subject can have two or more books (e.g. English: Reader + Grammar) — add each book one by one.</p>`}</div>`;
}
function sylMake() {
  const n = Math.max(1, Math.min(40, parseInt(($('sylNc') || {}).value, 10) || 0)); if (!n) return toast('Write how many chapters the book has.');
  const B = DRAFT.sb; B.book = (($('sylBk') || {}).value || B.book || '').trim().slice(0, 80);
  while (B.rows.length < n) B.rows.push({ n: '', pf: '', pt: '' }); B.rows.length = n; render();
  const f = document.querySelector('.syi input'); if (f) f.focus();
}
function sylNextPage(i, v) {          // "to" page of the previous row = this row's "from" − 1 (only if still empty)
  const B = DRAFT.sb, p = parseInt(v, 10); if (!p || i < 1) return; const prev = B.rows[i - 1];
  if (prev && !prev.pt) { prev.pt = String(p - 1); const el = document.querySelectorAll('.syi')[i - 1]; if (el) el.querySelectorAll('input')[2].value = prev.pt; }
}
function sylRowsSave(key) {
  const B = DRAFT.sb, book = (($('sylBk') || {}).value || B.book || '').trim().slice(0, 80);
  const rows = B.rows.map(r => ({ n: String(r.n || '').trim().slice(0, 120), pf: String(r.pf || '').replace(/[^\dA-Za-z]/g, '').slice(0, 5), pt: String(r.pt || '').replace(/[^\dA-Za-z]/g, '').slice(0, 5) })).filter(r => r.n.length >= 2);
  if (!rows.length) return toast('Write at least one chapter name.');
  const y = sylCopy(key); let added = 0, upd = 0;
  rows.forEach(r => { const o = y.chapters.find(ch => ch.n.toLowerCase() === r.n.toLowerCase() && (ch.book || '') === book);
    if (o) { Object.assign(o, { pf: r.pf || o.pf || '', pt: r.pt || o.pt || '' }); upd++; }
    else { y.chapters.push(Object.assign({ n: r.n, done: null, topics: [] }, book ? { book } : {}, r.pf ? { pf: r.pf } : {}, r.pt ? { pt: r.pt } : {})); added++; } });
  put('syl', key, y, 'Syllabus', `${key.replace('|', ' ')}: ${added} chapter(s) added${upd ? ', ' + upd + ' updated' : ''}${book ? ' — book "' + book + '"' : ''} (with pages) — Teacher App`);
  DRAFT.sb = null; DRAFT.sx = null; DRAFT.sylHint = key; toast(added + ' chapter(s) saved.'); render();
}
function sylExTap(i, id) { const X = DRAFT.sx, e = X.ex[i] = X.ex[i] || []; const j = e.indexOf(id); if (j >= 0) e.splice(j, 1); else e.push(id); X.changed++; render(); }
function sylExSave(key) {
  const y = sylCopy(key), X = DRAFT.sx; if (!X || X.key !== key) return;
  y.chapters.forEach((ch, i) => { const e = (X.ex[i] || []).filter(Boolean); if (e.length) ch.ex = e; else delete ch.ex; });
  const sum = examTags().map(t => [t.l, y.chapters.filter(ch => (ch.ex || []).includes(t.id)).length]).filter(x => x[1]).map(x => x[0] + ': ' + x[1]).join(', ');
  put('syl', key, y, 'Syllabus', `${key.replace('|', ' ')}: exam chapters saved (${sum || 'none'}) — Teacher App`);
  DRAFT.sx = null; toast('Saved. Parents can now see which chapters come in each exam.'); render();
}
function sylShareText(key, id, X) {
  const [c, s] = key.split('|'), y = S.syl[key] || { chapters: [] }, rows = y.chapters.map((ch, i) => [ch, i]).filter(([, i]) => ((X ? X.ex[i] : y.chapters[i].ex) || []).includes(id));
  if (!rows.length) return '';
  const byB = {}; rows.forEach(([ch]) => (byB[ch.book || ''] = byB[ch.book || ''] || []).push(ch));
  return `*Class ${c} — ${s} — ${examLabel(id)} syllabus*\n` + Object.entries(byB).map(([b, l]) => (b ? `\n📘 ${b}\n` : '\n') + l.map((ch, j) => `${j + 1}. ${ch.n}${ch.pf || ch.pt ? ' (page ' + (ch.pf || '?') + (ch.pt ? '–' + ch.pt : '') + ')' : ''}`).join('\n')).join('\n') + `\n\n— ${myName()}, ${S.settings.school || ''}`;
}
function chapEdit(key, i) {
  const y = S.syl[key], ch = y && y.chapters[i]; if (!ch) return;
  SHEET = () => `<h2>Chapter ${i + 1}</h2>
    <div class="fld">Chapter name<input id="ceN" class="inp" maxlength="120" value="${esc(ch.n)}"></div>
    <div class="fld">Book<input id="ceB" class="inp" maxlength="80" value="${esc(ch.book || '')}" list="sylBkL2"><datalist id="sylBkL2">${sylBooks(y).filter(Boolean).map(b => `<option value="${esc(b)}">`).join('')}</datalist></div>
    <div class="two"><div class="fld">From page<input id="ceF" class="inp" inputmode="numeric" maxlength="5" value="${esc(ch.pf || '')}"></div><div class="fld">To page<input id="ceT" class="inp" inputmode="numeric" maxlength="5" value="${esc(ch.pt || '')}"></div></div>
    <button class="btn go full" onclick="chapEditSave(${jsq(key)},${i})">Save</button>
    <div class="btns"><button class="btn s w" ${i ? '' : 'disabled'} onclick="chapMove(${jsq(key)},${i},-1)">↑ Move up</button><button class="btn s w" ${i < y.chapters.length - 1 ? '' : 'disabled'} onclick="chapMove(${jsq(key)},${i},1)">↓ Move down</button><button class="btn s bad" onclick="chapDel(${jsq(key)},${i})">Remove chapter</button></div>`;
  render();
}
async function chapEditSave(key, i) {
  const y = sylCopy(key), ch = y.chapters[i], n = $('ceN').value.trim().slice(0, 120); if (n.length < 2) return toast('Write the chapter name.');
  const old = ch.n, tx = chapRec(key, old), [c, s] = key.split('|');
  ch.n = n; const b = $('ceB').value.trim().slice(0, 80); if (b) ch.book = b; else delete ch.book;
  ch.pf = $('ceF').value.replace(/[^\dA-Za-z]/g, '').slice(0, 5); ch.pt = $('ceT').value.replace(/[^\dA-Za-z]/g, '').slice(0, 5);
  put('syl', key, y, 'Syllabus', `${key.replace('|', ' ')}: chapter "${old}"${old !== n ? ' renamed to "' + n + '"' : ''} changed (book / pages) — Teacher App`);
  if (tx && tx.text && old.toLowerCase() !== n.toLowerCase()) {        // the text is kept under the new name
    try { await api('teacher_api.php?a=chaptext', { cls: c, subj: s, chapter: n, book: b, text: tx.text }); await api('teacher_api.php?a=chaptext', { cls: c, subj: s, chapter: old, text: '' }); delete CHT[key]; } catch (e) { toast(e.message); }
  }
  DRAFT.sx = null; SHEET = null; toast('Chapter saved.'); render();
}
function chapMove(key, i, d) { const y = sylCopy(key), j = i + d; if (j < 0 || j >= y.chapters.length) return; [y.chapters[i], y.chapters[j]] = [y.chapters[j], y.chapters[i]]; put('syl', key, y, 'Syllabus', `${key.replace('|', ' ')}: chapter "${y.chapters[j].n}" moved`); DRAFT.sx = null; SHEET = null; render(); }
function chapDel(key, i) { const y = sylCopy(key), ch = y.chapters[i]; if (!ch || !confirm('Remove chapter "' + ch.n + '" from the syllabus?')) return; y.chapters.splice(i, 1); put('syl', key, y, 'Syllabus', `${key.replace('|', ' ')}: removed chapter "${ch.n}" — Teacher App`); DRAFT.sx = null; SHEET = null; render(); }
// Chapter text sheet
function chapOpen(key, i) {
  const y = S.syl[key], ch = y && y.chapters[i]; if (!ch) return; const C = needChapTexts(key);
  if (!DRAFT.cht || DRAFT.cht.key !== key || DRAFT.cht.i !== i) DRAFT.cht = { key, i, text: C.loading ? null : (chapRec(key, ch.n) || {}).text || '' };
  SHEET = () => { const D = DRAFT.cht; if (D.text === null && !(CHT[key] || {}).loading) D.text = (chapRec(key, ch.n) || {}).text || '';
    const w = D.text ? D.text.split(/\s+/).filter(Boolean).length : 0;
    return `<h2>📄 ${esc(ch.n)}</h2><p class="mut" style="margin:0">${esc(ch.book || '')}${ch.pf ? ' · page ' + esc(ch.pf) + (ch.pt ? '–' + esc(ch.pt) : '') : ''}</p>
    <div class="ban inf"><div><b>Paste the whole chapter as text.</b><br><span class="sm">1) Phone camera / Google Lens on the book page → “Select all / Copy text” → paste here (page by page).<br>2) Or from the e-book / NCERT PDF: copy the chapter → paste.<br>3) Or “Read from photos” below — the phone reads up to 4 pages at a time. Check the text.</span><br><span class="sm">Only teachers see this text. It is used for homework ideas, the lesson plan and the question paper.</span></div></div>
    ${D.text === null ? '<div class="load"><i></i></div>' : `<textarea id="chT" class="inp" rows="11" maxlength="60000" placeholder="Paste the chapter text here…" oninput="DRAFT.cht.text=this.value;$('chW').textContent=this.value.split(/\\s+/).filter(Boolean).length+' words'">${esc(D.text)}</textarea>
    <div class="row" style="justify-content:space-between"><small id="chW" class="mut">${w} words</small><small class="mut">${DRAFT.busy && DRAFT.busyK === 'cht' ? esc(DRAFT.busy) : ''}</small></div>
    <label class="btn l full filebtn">${ic('cam')} Read from photos (adds to the text)<input type="file" accept="image/*" capture="environment" multiple onchange="chapPhotos(this.files)"></label>
    <div class="btns"><button class="btn go" onclick="chapSave()">Save text</button>${chapRec(key, ch.n) ? `<button class="btn s w" onclick="if(confirm('Remove the text of this chapter?')){DRAFT.cht.text='';chapSave()}">Remove text</button>` : ''}</div>`}`; };
  render();
}
async function chapPhotos(files) {
  files = [...(files || [])].slice(0, 4); if (!files.length || !DRAFT.cht) return;
  const busy = m => { DRAFT.busy = m; DRAFT.busyK = 'cht'; render(); };
  try { busy('Getting the phone reader ready… (the first time takes about a minute)'); await tessLoad(); let out = '';
    for (let i = 0; i < files.length; i++) { busy(`Reading page ${i + 1} of ${files.length}…`); const img = await shrink(files[i]); const r = await window.Tesseract.recognize(img, 'eng+hin'); out += '\n' + ((r && r.data && r.data.text) || '').trim(); }
    DRAFT.cht.text = ((DRAFT.cht.text || '').trim() + '\n' + out.trim()).trim(); toast('Pages read. Please check the text, then Save.');
  } catch (e) { toast(e.message || 'The photo could not be read.'); } finally { DRAFT.busy = null; render(); }
}
async function chapSave() {
  const D = DRAFT.cht; if (!D) return; const y = S.syl[D.key], ch = y && y.chapters[D.i]; if (!ch) return; const [c, s] = D.key.split('|');
  try { const r = await api('teacher_api.php?a=chaptext', { cls: c, subj: s, chapter: ch.n, book: ch.book || '', text: D.text || '' });
    const L = (CHT[D.key] = CHT[D.key] || { list: [] }).list = ((CHT[D.key] || {}).list || []).filter(x => String(x.chapter).toLowerCase() !== ch.n.toLowerCase());
    if (!r.removed) L.push(Object.assign({}, r.chapter, { text: D.text }));
    DRAFT.cht = null; SHEET = null; toast(r.removed ? 'Text removed.' : 'Chapter text saved — homework ideas, lesson plan and paper now use it.'); render();
  } catch (e) { toast(e.message); }
}

// ---------------------------------------------------------------- 4. Question paper
const PNOTES = {};
function needPNotes(key) {
  if (PNOTES[key]) return PNOTES[key]; const [c, s] = key.split('|'); PNOTES[key] = [];
  api(`teacher_api.php?a=notes&cls=${encodeURIComponent(c)}&subj=${encodeURIComponent(s)}`).then(r => { PNOTES[key] = r.notes || []; }).catch(() => {});
  return PNOTES[key];
}
const PP_SEC = [['fb', 'Fill in the blanks', 1, 5], ['tf', 'True or False', 1, 5], ['wm', 'Word meanings', 1, 5], ['sq', 'Short answer questions', 2, 5], ['lq', 'Long answer questions', 5, 2]];
function vPaper(list) {
  const key = list.some(a => sylKey(a.c, a.s) === P.key) ? P.key : sylKey(list[0].c, list[0].s), [c, s] = key.split('|'), y = S.syl[key] || { chapters: [] };
  const tags = examTags(c), up = tags.filter(t => !examPast(t)), ex = tags.some(t => t.id === P.ex) ? P.ex : (up.find(t => y.chapters.some(ch => (ch.ex || []).includes(t.id))) || up[0] || tags[0]).id;
  const C = needChapTexts(key); needPNotes(key);
  const k2 = key + '|' + ex;
  if (!DRAFT.pp || DRAFT.pp.k !== k2) DRAFT.pp = { k: k2, sel: y.chapters.map(ch => (ch.ex || []).includes(ex)), n: Object.fromEntries(PP_SEC.map(x => [x[0], x[3]])), time: '', seed: 1, out: '', ans: '' };
  const D = DRAFT.pp, nSel = D.sel.filter(Boolean).length, nTxt = y.chapters.filter((ch, i) => D.sel[i] && chapRec(key, ch.n)).length;
  return `${keyPick(list, key, `DRAFT.pp=null;go('plan',{m:'paper',key:this.value,ex:${jsq(ex)}})`)}
    <div class="card"><h3>1 · Exam</h3><div class="chips">${tags.filter(t => !examPast(t) || t.id === ex).map(t => `<button class="chip ${t.id === ex ? 'on' : ''}" onclick="DRAFT.pp=null;go('plan',{m:'paper',key:${jsq(key)},ex:'${esc(t.id)}'})">${esc(t.l)}</button>`).join('')}</div></div>
    <div class="card"><h3>2 · Chapters <small>${nSel} chosen · ${nTxt} with text</small></h3>
      ${y.chapters.length ? y.chapters.map((ch, i) => `<label class="li chk"><input type="checkbox" ${D.sel[i] ? 'checked' : ''} onchange="DRAFT.pp.sel[${i}]=this.checked;render()"><div class="t"><b>${esc(ch.n)}</b><span>${ch.book ? esc(ch.book) + ' · ' : ''}${chapRec(key, ch.n) ? '📄 text added' : 'no text yet'}${(ch.ex || []).length ? ' · ' + ch.ex.map(examLabel).map(esc).join(', ') : ''}</span></div></label>`).join('')
        : `<div class="empty">Add the chapters first (Planner → Syllabus).</div>`}
      ${C.loading ? '<p class="mut">Loading chapter texts…</p>' : nSel && !nTxt ? `<div class="hint-t">💡 None of these chapters has its text yet. Add it in Syllabus (📄 +) — the questions are taken from the chapter’s own sentences and its notes.</div>` : ''}</div>
    <div class="card"><h3>3 · Questions</h3>${PP_SEC.map(([k, l, m]) => `<div class="pps"><span>${l} <small>${m} mark${m > 1 ? 's' : ''} each</small></span><input class="inp mini" type="number" inputmode="numeric" min="0" max="20" value="${D.n[k]}" oninput="DRAFT.pp.n['${k}']=Math.max(0,Math.min(20,+this.value||0))"></div>`).join('')}
      <div class="fld">Time<input class="inp" maxlength="20" value="${esc(D.time)}" placeholder="e.g. 1 hour" oninput="DRAFT.pp.time=this.value"></div>
      <button class="btn go full" style="margin-top:8px" ${nSel ? '' : 'disabled'} onclick="paperMake(${jsq(key)},${jsq(ex)})">${D.out ? 'Make again (new questions)' : 'Make the paper'}</button></div>
    ${D.out ? `<div class="card"><h3>Question paper <small>edit anything before printing</small></h3><textarea id="ppOut" class="inp" rows="18" oninput="DRAFT.pp.out=this.value">${esc(D.out)}</textarea>
      <details class="more"><summary>Answer key (for the teacher)</summary><textarea id="ppAns" class="inp" rows="8" oninput="DRAFT.pp.ans=this.value">${esc(D.ans)}</textarea></details>
      <div class="btns"><button class="btn go" onclick="paperPrint(false)">${ic('print')} Print paper</button><button class="btn w" onclick="paperPrint(true)">${ic('print')} Print with answers</button></div>
      <p class="mut" style="margin:6px 0 0">Questions come only from the chapter text and the chapter notes. Please read the paper once — fix any wrong line before printing.</p></div>` : ''}`;
}
function rng(seed) { let a = seed >>> 0; return () => { a = (a + 0x6D2B79F5) >>> 0; let t = a; t = Math.imul(t ^ (t >>> 15), t | 1); t ^= t + Math.imul(t ^ (t >>> 7), t | 61); return ((t ^ (t >>> 14)) >>> 0) / 4294967296; }; }
function shuffle(a, r) { a = a.slice(); for (let i = a.length - 1; i > 0; i--) { const j = Math.floor(r() * (i + 1)); [a[i], a[j]] = [a[j], a[i]]; } return a; }
function paperMake(key, ex) {
  const D = DRAFT.pp, y = S.syl[key] || { chapters: [] }, [c, s] = key.split('|'), r = rng(D.seed++ * 7919 + key.length);
  const chs = y.chapters.filter((ch, i) => D.sel[i]); if (!chs.length) return toast('Choose at least one chapter.');
  const texts = chs.map(ch => chapterText(key, ch.n)).filter(Boolean), all = texts.join('\n');
  const notes = (PNOTES[key] || []).filter(n => chs.some(ch => String(n.chapter || n.title || '').toLowerCase().includes(ch.n.toLowerCase()) || ch.n.toLowerCase().includes(String(n.chapter || '').toLowerCase())));
  const sents = shuffle([...new Set(texts.flatMap(textSentences))], r), kw = keyWords(all), used = new Set();
  const take = (list, n, f) => { const out = []; for (const x of list) { if (out.length >= n) break; if (used.has(x)) continue; const y2 = f(x); if (y2) { used.add(x); out.push(y2); } } return out; };
  const kwSet = kw.slice(0, 30);
  const wre = k => new RegExp('(^|[^\\p{L}\\p{M}])' + k.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '(?=$|[^\\p{L}\\p{M}])', 'iu');
  const fb = take(sents, D.n.fb, x => { const w = kwSet.find(k => wre(k).test(x)); if (!w) return null;
    return { q: x.replace(wre(w), '$1__________'), a: w }; });
  const tf = take(sents, D.n.tf, x => { const flip = r() < 0.5, hi = /[ऀ-ॿ]/.test(x);
    if (!flip) return { q: x, a: hi ? 'सही' : 'True' };
    const y2 = hi ? x.replace(/\s(है|हैं|था|थे|थी)([\s।.]*)$/, ' नहीं $1$2') : x.replace(/\b(is|are|was|were|can|will|has|have|does|do|did)\b/i, m => m + ' not');
    return y2 !== x ? { q: y2, a: hi ? 'गलत' : 'False' } : { q: x, a: hi ? 'सही' : 'True' }; });
  const nWords = notes.flatMap(n => n.words || []).filter(w => w.w && w.m);
  const wm = (nWords.length ? shuffle(nWords, r).slice(0, D.n.wm).map(w => ({ q: w.w, a: w.m })) : shuffle(kw.slice(0, 20), r).slice(0, D.n.wm).map(w => ({ q: w, a: '' })));
  const qText = texts.flatMap(textQuestions), qNote = notes.flatMap(n => n.qa || []).filter(x => x.q);
  const qs = shuffle([...new Set([...qText, ...qNote.map(x => x.q)])], r), ansOf = q => (qNote.find(x => x.q === q) || {}).a || '';
  const sq = qs.filter(q => q.length <= 110).slice(0, D.n.sq).map(q => ({ q, a: ansOf(q) }));
  const heads = shuffle(chs.flatMap(ch => chapTopics(key, ch.n).filter(t => !/^reading:|exercise|revision/.test(t)).map(t => t)), r);
  const lqPool = qs.filter(q => !sq.some(x => x.q === q)).map(q => ({ q, a: ansOf(q) }));
  heads.forEach(h => lqPool.push({ q: (/[ऀ-ॿ]/.test(h) ? `${h} पर विस्तार से लिखिए।` : `Explain “${h}” in detail.`), a: '' }));
  if (!lqPool.length) chs.forEach(ch => lqPool.push({ q: /[ऀ-ॿ]/.test(ch.n) ? `“${ch.n}” पाठ का सारांश लिखिए।` : `Write the summary of the chapter “${ch.n}”.`, a: '' }));
  const lq = lqPool.slice(0, D.n.lq);
  const secs = [['fb', fb], ['tf', tf], ['wm', wm], ['sq', sq], ['lq', lq]].filter(([, l]) => l.length);
  const meta = Object.fromEntries(PP_SEC.map(x => [x[0], x])), total = secs.reduce((n, [k, l]) => n + l.length * meta[k][2], 0);
  const L = 'ABCDEFG'; let out = `${S.settings.school || ''}\n${examLabel(ex)} · Class ${c} · ${s}\n${D.time ? 'Time: ' + D.time + '        ' : ''}Max. Marks: ${total}\nChapters: ${chs.map(ch => ch.n).join(', ')}\nName: ____________________   Roll no.: ______\n`, ans = `Answer key — ${examLabel(ex)} · Class ${c} · ${s}\n`, qn = 0;
  secs.forEach(([k, l], si) => { const [, title, m] = meta[k];
    out += `\nSection ${L[si]} — ${title} (${l.length} × ${m} = ${l.length * m})\n`; ans += `\nSection ${L[si]} — ${title}\n`;
    if (k === 'wm') out += 'Write the meaning of:\n';
    l.forEach(x => { qn++; out += `${qn}. ${x.q}${k === 'tf' ? '  (True / False)' : ''}\n`; ans += `${qn}. ${x.a || '—'}\n`; }); });
  if (!qn) return toast('No question could be made. Add the chapter text in Syllabus (📄 +), or the chapter notes.');
  D.out = out.trim(); D.ans = ans.trim(); toast(qn + ' questions made. Read and correct, then print.'); render();
}
function paperPrint(withAns) {
  const D = DRAFT.pp; if (!D) return; const o = (($('ppOut') || {}).value || D.out), a = (($('ppAns') || {}).value || D.ans);
  let p = document.querySelector('.prt'); if (!p) { p = document.createElement('div'); p.className = 'prt'; document.body.appendChild(p); }
  p.innerHTML = `<pre style="font:14px/1.7 Arial,sans-serif;white-space:pre-wrap">${esc(o)}</pre>${withAns ? `<pre style="font:13px/1.6 Arial,sans-serif;white-space:pre-wrap;page-break-before:always">${esc(a)}</pre>` : ''}`;
  window.print();
}

// ---------------------------------------------------------------- 5. Work cross-check (teacher's own + Principal)
function hwDueFor(t, date) {
  const set = new Set(S.alloc.filter(a => a.t === t && (+a.n || 0) > 0).map(a => a.c + '|' + a.s)), out = {};
  for (let i = 1; i <= 10; i++) { const dd = addDays(date, -i); Object.values(S.diary[dd] || {}).forEach(byB => Object.values(byB || {}).forEach(e => { if (e && e.hw && e.hwDate === date && set.has(e.cls + '|' + e.subj)) (out[e.cls + '|' + e.subj] = out[e.cls + '|' + e.subj] || []).push(e.hw); })); }
  return out;
}
// One day's work of teacher t. X = { kit:{cls:…}, chk:[{cls,subj,date?}] } from teacher_api.php?a=crosscheck (null = not loaded:
// the daily check and homework checks are then left out rather than shown as not done).
function workOf(t, date, X) {
  const items = [], add = (kind, txt, done, to) => items.push({ kind, txt, done: !!done, to });
  if (!isWorkDay(date)) return items;
  const cutoff = date < todayISO() || nowMin() >= toMin(S.settings.attendanceCutoff || '09:30');
  (S.classes || []).filter(k => k.ct === t && isWorkDay(date, k.c) && studentsOf(k.c).length).forEach(k => {
    if (cutoff) add('att', 'Class ' + k.c + ' attendance', attOf(date, k.c), ['att', { c: k.c, date }]);
    if (X && cutoff) add('kit', 'Class ' + k.c + ' daily check (diary · ID · book · dress)', (X.kit || {})[k.c], ['att', { c: k.c, m: 'kit', date }]);
  });
  bellsOf(t, date).forEach(b => { if (!bellOver(date, b.bell)) return; const e = ((S.diary[date] || {})[t] || {})[b.bell];
    add('diary', 'Diary · ' + BELLS[b.bell].n + ' bell · Class ' + b.cls + ' ' + String(b.subj).replace(/\s*\(optional\)/i, ''), e, ['diary', { date, bell: b.bell }]); });
  if (X) Object.keys(hwDueFor(t, date)).forEach(k => { const [c, s] = k.split('|'); if (!isWorkDay(date, c)) return;
    if (date === todayISO() && !bellsOf(t, date).some(b => b.cls === c && b.subj === s && bellOver(date, b.bell))) return;
    add('hwchk', 'Homework check · Class ' + c + ' ' + s, (X.chk || []).some(x => x.cls === c && x.subj === s && (!x.date || x.date === date)), ['diary', { m: 'check', date, key: k }]); });
  return items;
}
// Once-a-week / one-time work: this week's lesson plan (from Tuesday), syllabus chapters, exam chapters ticked.
function setupOf(t, date) {
  const items = [], w = weekStart(date);
  S.alloc.filter(a => a.t === t && (+a.n || 0) > 0 && !/\(optional\)/i.test(a.s)).forEach(a => { const key = sylKey(a.c, a.s), y = S.syl[key], has = !!(y && (y.chapters || []).length);
    if (dayIdx(date) >= 1) items.push({ kind: 'lp', txt: 'Lesson plan this week · Class ' + a.c + ' ' + a.s, done: !!S.lplan[[w, t, a.c, a.s].join('|')], to: ['plan', { m: 'lp', w, key }] });
    items.push({ kind: 'syl', txt: 'Syllabus chapters · Class ' + a.c + ' ' + a.s, done: has, to: ['plan', { m: 'syl', key }] });
    if (has) items.push({ kind: 'exam', txt: 'Exam chapters ticked · Class ' + a.c + ' ' + a.s, done: y.chapters.some(ch => (ch.ex || []).length), to: ['plan', { m: 'syl', key }] });
  });
  return items;
}
let MYX = null;
function needMyX() {
  const t = todayISO();
  if (MYX && MYX.date === t && (MYX.loading || Date.now() - (MYX.got || 0) < 120000)) return MYX;
  const old = MYX && MYX.date === t ? MYX : null; MYX = Object.assign({}, old || {}, { date: t, loading: true });
  api('teacher_api.php?a=crosscheck&days=7&date=' + t).then(r => { MYX = Object.assign(r, { date: t, ok: true, got: Date.now() }); softRender(); })
    .catch(() => { MYX = Object.assign({}, old || {}, { date: t, loading: false, got: Date.now() }); });
  return MYX;
}
function xOfDay(X, date) {
  if (!X || !X.ok) return null; const kit = {};
  Object.entries(X.kit || {}).forEach(([k, v]) => { const [d, c] = k.split('|'); if (d === date) kit[c] = v; });
  return { kit, chk: (X.chk || []).filter(x => x.date === date) };
}
function mwBtn(w, d) { return `<button type="button" class="mw ${w.done ? 'ok' : 'no'}" onclick="go(${esc(JSON.stringify(w.to[0]))},${esc(JSON.stringify(w.to[1]))})"><i>${w.done ? '✓' : '✕'}</i><span>${esc(w.txt)}${d ? `<small>${esc(fmtDate(d))}</small>` : ''}</span></button>`; }
function myWorkCard() {
  if (!ME || isP() || !S) return '';
  const t = todayISO(), X = needMyX(), mine = me();
  const today = absentFrom(t, mine) === 0 ? [] : workOf(mine, t, xOfDay(X, t)), earlier = [];
  for (let i = 1; i <= 6; i++) { const d = addDays(t, -i); if (d < (loadedFrom || '')) break; if (absentFrom(d, mine) === 0) continue; workOf(mine, d, xOfDay(X, d)).filter(w => !w.done).forEach(w => earlier.push([w, d])); }
  const setup = setupOf(mine, t).filter(w => !w.done), done = today.filter(w => w.done).length;
  if (!today.length && !earlier.length && !setup.length) return '';
  const pct = today.length ? Math.round(done / today.length * 100) : 100;
  return `<div class="card mywork"><h3>My work check <small>today ${done}/${today.length}</small></h3>
    ${today.length ? `<div class="mw-bar"><i style="--w:${pct}%"></i></div>${today.slice().sort((a, b) => a.done - b.done).map(w => mwBtn(w)).join('')}` : `<p class="mut" style="margin:0">Nothing is due yet today.</p>`}
    ${today.length && done === today.length && !earlier.length ? '<p class="mw-all">✓ Sab kaam poora — all of today\'s work is done.</p>' : ''}
    ${earlier.length ? `<details class="mw-su mw-old" ${earlier.length <= 3 ? 'open' : ''}><summary>Earlier this week — not done <small>${earlier.length}</small></summary>${earlier.slice(0, 20).map(([w, d]) => mwBtn(w, d)).join('')}${earlier.length > 20 ? `<p class="mut">+${earlier.length - 20} more</p>` : ''}</details>` : ''}
    ${setup.length ? `<details class="mw-su"><summary>Weekly / one-time work <small>${setup.length}</small></summary>${setup.map(w => mwBtn(w)).join('')}</details>` : ''}
    ${X && X.loading && !X.ok ? '<p class="mut" style="margin:4px 0 0">Checking daily check and homework checks…</p>' : ''}</div>`;
}
