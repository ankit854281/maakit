// ============================================================================
//  Teacher App — Smart Classroom (owner, 19 Nov 2026: "homework cross check karna, homework dena, CW dena — ye sab
//  proper kaam nahi kar rahe, aur smart aur smooth banao"). Loaded LAST. Same records as before (diary, tchChk) —
//  only the screens change, nothing in the data format.
//
//  Diary (CW / HW)
//   - Only ONE bell is open at a time: the first bell not written yet. Written bells and later bells are one line each;
//     after Save the next unwritten bell opens by itself (no long scrolling through 6 open forms).
//   - Top card: "Today 2 / 5 bells written" + older days still empty (tap = that day).
//   - Topic is filled by itself (editable): this week's lesson plan topic not yet taught → yesterday's topic of this
//     class + subject ("continue") → next syllabus chapter. The other two are one-tap chips.
//   - Class work / homework: 4 ready sentences + "More"; question numbers open with "🔢 Q no." (hidden until needed);
//     homework ideas from the class work (teacher-work.js) stay. "Same as last time" repeats the last homework pattern.
//   - Homework time is 15 minutes by itself (change with one tap) — saving no longer stops for minutes; the
//     "I really taught this" tick is now the Save button itself ("Save diary ✔ I taught this").
//  Check work
//   - "Homework to check" list on top: every homework given by you that fell due in the last 7 days and is not checked
//     yet (from your own diary + your saved checks). Opens on the first one by itself; tap any to open it.
//   - Tap a child's NAME to go Done → Not done → Half → blank; "All homework done" first, then tap only those who did not.
//   - The Check work switch shows how many are waiting.
// ============================================================================
const SD = { q: {}, more: {} };
function sdLast(date, c, s) {           // the latest diary entry of this class + subject before this day (any teacher)
  for (let i = 1; i <= 21; i++) { const d = addDays(date, -i), day = S.diary[d]; if (!day) continue;
    for (const byB of Object.values(day)) for (const e of Object.values(byB || {})) if (e && e.cls === c && e.subj === s && e.topic) return Object.assign({ d }, e); }
  return null;
}
function sdTopics(date, b) {
  const out = [], seen = new Set(), add = (icon, label, t) => { t = String(t || '').trim(); if (!t || seen.has(t.toLowerCase())) return; seen.add(t.toLowerCase()); out.push([icon, label, t]); };
  const w = weekStart(date), p = S.lplan[[w, b.for || me(), b.cls, b.subj].join('|')] || S.lplan[[w, me(), b.cls, b.subj].join('|')];
  if (p) { const taught = new Set(); for (let i = 0; i < 7; i++) { const d = addDays(w, i); if (d >= date) break; Object.values(S.diary[d] || {}).forEach(byB => Object.values(byB || {}).forEach(e => { if (e && e.cls === b.cls && e.subj === b.subj && e.topic) taught.add(e.topic.trim().toLowerCase()); })); }
    add('📋', 'This week\'s plan', String(p.topics || '').split('\n').map(x => x.trim()).find(x => x && !taught.has(x.toLowerCase()))); }
  const last = sdLast(date, b.cls, b.subj); if (last) add('↻', 'Continue from ' + fmtDate(last.d), last.topic);
  add('📖', 'Next chapter', nextChapter(b.cls, b.subj));
  return { out, last };
}
function sdOpenBell(date, bells) {      // which bell is open: the one asked for, else the first not written whose time has come
  if (P.bell !== undefined && P.bell !== null && bells.some(b => b.bell === P.bell)) return P.bell;
  if (P.bell === -1) return null;
  const t = todayISO(), todo = bells.filter(b => !diaryEntry(date, b.bell));
  const due = todo.find(b => date < t || bellOver(date, b.bell) || toMin(BELLS[b.bell].s) <= nowMin());
  return (due || todo[0] || {}).bell ?? null;
}
vDiaryWrite = function () {
  const t = todayISO(), date = P.date || t, bells = myBells(date), signed = (S.signed[date] || {})[me()];
  if (date < (loadedFrom || '') && !DAYS_LOADED[date]) { ensureDate(date).then(render); return '<div class="load"><i></i></div>'; }
  const ct = myCT().filter(c => isWorkDay(date, c));
  if (P.add && P.add.date === date && !bells.some(b => b.bell === P.add.bell)) bells.unshift(P.add);
  const done = bells.filter(b => diaryEntry(date, b.bell)).length, open = sdOpenBell(date, bells);
  const old = date === t ? diaryPending(6) : [];
  const top = bells.length ? `<div class="card sd-top"><div class="row"><div><b>${date === t ? 'Today' : esc(fmtDate(date))}: ${done} / ${bells.length} bells written</b><small>${done === bells.length ? 'All done for the day 👍' : 'Tap a bell to write. After Save the next one opens.'}</small></div><div class="sd-ring" style="--p:${Math.round(100 * done / bells.length)}"><span>${done}/${bells.length}</span></div></div>
      ${old.length ? `<div class="chips" style="margin-top:8px"><span class="mut" style="align-self:center">Still empty:</span>${old.map(x => `<button class="chip warn" onclick="go('diary',{date:'${x.d}'})">${esc(fmtDate(x.d))} · ${x.n}</button>`).join('')}</div>` : ''}</div>` : '';
  return `<div class="row"><button class="btn s w" onclick="go('diary',{date:'${addDays(date, -1)}'})">${ic('back')}</button><input type="date" class="inp" style="flex:1" value="${date}" max="${addDays(t, 7)}" onchange="go('diary',{date:this.value})"><button class="btn s w" onclick="go('diary',{date:'${addDays(date, 1)}'})">${ic('next')}</button></div>
    ${signed ? '<div class="ban inf">The Principal has signed this day. Bells already written are locked; bells not written yet can still be saved.</div>' : ''}${top}
    ${bells.length ? bells.map(b => b.bell === open ? sdCard(date, b, signed && !!diaryEntry(date, b.bell)) : sdLine(date, b)).join('') : `<div class="card empty">${dayIdx(date) < 0 ? 'Sunday — no classes.' : holidayOn(date) ? 'Holiday: ' + esc(holidayOn(date).title) : 'No bells for you on this day.'}</div>`}
    ${dayIdx(date) >= 0 && !holidayOn(date) ? `<button class="btn w full" onclick="addPeriod('${date}')">+ Add a period (substitution / not in timetable)</button>` : ''}
    ${ct.map(c => hwShare(date, c)).join('')}`;
};
function sdBellBox(b) { return `<div class="bell ${b.arr ? 'arr' : ''}"><b>${BELLS[b.bell].n.replace(/\D/g, '')}</b>${t12(BELLS[b.bell].s).replace(' ', '')}</div>`; }
function sdLine(date, b) {
  const e = diaryEntry(date, b.bell), later = date === todayISO() && !e && !bellOver(date, b.bell) && toMin(BELLS[b.bell].s) > nowMin();
  return `<div class="card sd-line ${e ? 'ok' : later ? '' : 'todo'}" onclick="go('diary',{date:'${date}',bell:${b.bell}})"><div class="li" style="padding:0">${sdBellBox(b)}
    <div class="t"><b>Class ${esc(b.cls)} · ${esc(b.subj)}${b.arr ? ' <small>(arrangement)</small>' : ''}</b><span>${e ? esc(e.topic) + (e.hw ? ' · HW: ' + esc(e.hw) : ' · no HW') : later ? 'Later today' : 'Not written yet'}</span></div>
    <span class="pill ${e ? 'ok' : later ? '' : 'warn'}">${e ? '✓ Saved' : later ? 'Later' : 'Write'}</span></div></div>`;
}
function sdChips(id, kind, nx) {
  const L = PHRASES[kind], k = id + kind, more = SD.more[k];
  return `<div class="chips phr">${L.map(([l], i) => (i < 4 || more) ? `<button type="button" class="chip" onclick="phraseAdd('${id}','${kind}',${i},${jsq(nx || '')})">+ ${l}</button>` : '').join('')}
    ${L.length > 4 ? `<button type="button" class="chip ghost" onclick="SD.more['${k}']=${!more};render()">${more ? 'Less' : 'More…'}</button>` : ''}
    <button type="button" class="chip ghost ${SD.q[id + kind] ? 'on' : ''}" onclick="SD.q['${id + kind}']=${!SD.q[id + kind]};render()">🔢 Q no.</button>
    ${kind === 'h' ? `<button type="button" class="chip ghost" onclick="phraseNoHw('${id}')">No homework</button>` : ''}</div>${SD.q[id + kind] ? qPick(id, kind) : ''}`;
}
function sdCard(date, b, signed) {
  const e = diaryEntry(date, b.bell) || {}, id = 'b' + b.bell, nx = nextChapter(b.cls, b.subj), { out: tops, last } = sdTopics(date, b);
  const auto = !e.topic && tops[0] ? tops[0] : null, hwD = e.hwDate || nextSchoolDay(date, b.cls) || date;
  const lastHw = last && last.hw ? last.hw : '';
  return `<div class="card sd-open" id="sd-${b.bell}"><div class="li" style="padding:0 0 8px">${sdBellBox(b)}
      <div class="t"><b>Class ${esc(b.cls)} · ${esc(b.subj)}</b><span>${b.arr ? 'Arrangement' + (b.for ? ' for ' + esc(T(b.for).name) : '') : b.extra ? 'Added period' : BELLS[b.bell].n + ' bell'}</span></div>${e.at ? `<span class="pill ok">Saved ${fmtTime(e.at).split(', ').pop()}</span>` : ''}
      ${e.at ? `<button class="ib" aria-label="Close" onclick="go('diary',{date:'${date}',bell:-1})">✕</button>` : ''}</div>
    ${optPick(date, b, e, id)}${attLine(date, b.cls)}
    <div class="dform">
      <button type="button" class="btn l full auto-fill" ${signed ? 'disabled' : ''} onclick="diaryAutoFill('${date}',${b.bell},${jsq(b.cls)},${jsq(b.subj)})">✨ Auto fill from syllabus</button>
      <div class="fld">Topic taught *<input id="${id}-t" data-keep class="inp ${auto ? 'auto-filled' : ''}" maxlength="200" value="${esc(e.topic || (auto ? auto[2] : ''))}" placeholder="What did you teach?">
        ${auto ? `<small class="sd-note">${auto[0]} Filled from ${esc(auto[1].toLowerCase())} — change it if you taught something else.</small>` : ''}
        ${tops.length > (auto ? 1 : 0) ? `<div class="chips sd-tops">${tops.map(([icn, l, x]) => `<button type="button" class="chip" title="${esc(l)}" onclick="sdTopic('${id}',${jsq(x)})">${icn} ${esc(x.slice(0, 38))}</button>`).join('')}</div>` : ''}
        ${suggestionBox(id + '-t', sylKey(b.cls, b.subj), 'topic')}</div>
      <div class="fld">Class work ${sdChips(id, 'cw', nx)}<textarea id="${id}-cw" data-keep data-cwfor="${id}" class="inp" rows="2" maxlength="500" placeholder="e.g. Q1–Q5 done in class copy">${esc(e.classwork || '')}</textarea></div>
      <div class="fld">Homework ${sdChips(id, 'h', nx)}<div id="${id}-idea" class="cw-ideas" data-key="${esc(sylKey(b.cls, b.subj))}" data-nx="${esc(nx || '')}"></div>
        ${lastHw && !e.hw ? `<button type="button" class="chip ghost sd-rep" onclick="sdRepeat('${id}',${jsq(lastHw)})">↻ Like last time: “${esc(lastHw.slice(0, 50))}${lastHw.length > 50 ? '…' : ''}”</button>` : ''}
        <textarea id="${id}-h" data-keep class="inp" rows="2" maxlength="500" placeholder="e.g. Ex 4.1 Q6–Q10 (leave empty = no homework)">${esc(e.hw || '')}</textarea>${suggestionBox(id + '-h', sylKey(b.cls, b.subj), 'homework')}</div>
      <div class="sd-meta"><label>⏱ <input id="${id}-min" data-keep class="inp mini" type="number" inputmode="numeric" min="1" max="45" value="${e.hwMinutes || ''}" placeholder="15"> min</label>
        <div class="chips">${[10, 15, 20, 30].map(m => `<button type="button" class="chip" onclick="diaryMin('${id}',${m})">${m}</button>`).join('')}</div></div>
      <div class="sd-meta"><label>📅 Check on <input id="${id}-date" data-keep class="inp mini date" type="date" value="${esc(hwD)}"></label><button type="button" class="chip" onclick="$('${id}-date').value='${date}';keepNote($('${id}-date'))">Same day</button></div>
      <details class="more" ${e.books || e.revision ? 'open' : ''}><summary>More · books to bring, revision</summary>
        <div class="fld">Books / copies to bring<input id="${id}-books" data-keep class="inp" maxlength="300" value="${esc(e.books || '')}"></div>
        <div class="fld">Revision<input id="${id}-rev" data-keep class="inp" maxlength="400" value="${esc(e.revision || '')}"></div></details>
      ${nx && !b.arr ? `<label class="chk"><input id="${id}-c" data-keep type="checkbox"> Chapter “${esc(nx)}” finished today (ticks it in the syllabus)</label>` : ''}
      <button class="btn go full" ${signed ? 'disabled' : ''} onclick="diarySave('${date}',${b.bell})">Save diary ✔ I taught this</button>
      <p class="mut" style="margin:4px 0 0;text-align:center;font-size:11px">Saving means you really taught this period.</p>
    </div></div>`;
}
function sdTopic(id, x) { const el = $(id + '-t'); if (!el) return; el.value = x; el.classList.remove('auto-filled'); keepNote(el); toast('Topic set. You can still edit it.'); }
function sdRepeat(id, hw) { const el = $(id + '-h'); if (!el) return; el.value = hw; keepNote(el); if (!$(id + '-min').value) { $(id + '-min').value = 15; keepNote($(id + '-min')); } toast('Same homework as last time — change the question numbers if needed.'); }

diarySave = function (date, bell) {
  let b = myBells(date).find(x => x.bell === bell) || (P.add && P.add.bell === bell ? P.add : null); if (!b) return;
  const id = 'b' + bell, v = k => ($(id + '-' + k) || {}).value || '', topic = v('t').trim(), hw = v('h').trim(), hwDate = v('date');
  let min = Number(v('min'));
  if (!topic) { const el = $(id + '-t'); if (el) el.focus(); return toast('Write the topic taught.'); }
  if (hw && !(Number.isInteger(min) && min >= 1 && min <= 45)) min = 15;                  // owner: no stopping for minutes
  if (optOf(date, b)) { const oc = v('oc'), os = v('os'); if (!oc || !os) return toast('Choose which class and subject you took in this optional bell.'); b = Object.assign({}, b, { cls: oc, subj: os }); }
  const d = { cls: b.cls, subj: b.subj, topic: topic.slice(0, 200), hw: hw.slice(0, 500), classwork: v('cw').trim().slice(0, 500), books: v('books').trim().slice(0, 300), revision: v('rev').trim().slice(0, 400),
    hwMinutes: hw ? min : null, hwDate: hwDate || date, arr: !!b.arr, at: Date.now(), ...(b.extra ? { extra: true } : {}) };
  put('diary', date + '|' + me() + '|' + bell, d, 'Diary', `${BELLS[bell].n} bell, Class ${b.cls} ${b.subj}: "${topic}"` + (hw ? ` • HW: ${hw}` : '') + ' — Teacher App');
  const cb = $(id + '-c');
  if (cb && cb.checked) { const key = sylKey(b.cls, b.subj), y = JSON.parse(JSON.stringify(S.syl[key] || { chapters: [] })), ch = y.chapters.find(x => !x.done && x.n === nextChapter(b.cls, b.subj));
    if (ch) { ch.done = date; put('syl', key, y, 'Syllabus', `Class ${b.cls} ${b.subj}: chapter "${ch.n}" completed (from diary)`); } }
  keepClear(id + '-'); SD.q[id + 'cw'] = SD.q[id + 'h'] = false;
  const nxt = myBells(date).find(x => x.bell !== bell && !diaryEntry(date, x.bell) && (date < todayISO() || bellOver(date, x.bell) || toMin(BELLS[x.bell].s) <= nowMin()));
  P = { date, bell: nxt ? nxt.bell : -1 };
  toast(nxt ? `Saved ✓ Next: ${BELLS[nxt.bell].n} bell · Class ${nxt.cls}` : hw ? `Saved ✓ Homework will come in “Check work” on ${fmtDate(d.hwDate)}.` : 'Saved ✓');
  render(); if (nxt) setTimeout(() => { const el = $('sd-' + nxt.bell); if (el && el.scrollIntoView) el.scrollIntoView({ behavior: 'smooth', block: 'start' }); }, 60);
};

// ---------------------------------------------------------------- check work
// Homework given by me (any of my diary entries) that fell due in the last 7 days and that I have not checked yet.
function sdToCheck() {
  const t = todayISO(), X = needMyX(), saved = new Set(((X && X.chk) || []).map(x => x.date + '|' + x.cls + '|' + x.subj));
  const mine = new Set(allocList().map(a => sylKey(a.c, a.s))), out = {}, from = addDays(t, -6);
  Object.entries(S.diary).forEach(([d, byT]) => Object.values(byT || {}).forEach(byB => Object.values(byB || {}).forEach(e => {
    if (!e || !e.hw) return; const due = e.hwDate || d; if (due < from || due > t || due < d) return;
    if (!mine.has(sylKey(e.cls, e.subj)) || !isWorkDay(due, e.cls)) return;
    const k = due + '|' + e.cls + '|' + e.subj; if (saved.has(k) || (CHK[k] && CHK[k].rec)) return;
    (out[k] = out[k] || { date: due, c: e.cls, s: e.subj, hw: [] }).hw.push(e.hw);
  })));
  return Object.values(out).sort((a, b) => a.date < b.date ? 1 : a.date > b.date ? -1 : (a.c + a.s).localeCompare(b.c + b.s));
}
(function () {
  const _vCheck = vCheck;
  vCheck = function () {
    const list = allocList(); if (!list.length) return _vCheck();
    const todo = sdToCheck();
    if (!P.key && todo.length) { P.key = sylKey(todo[0].c, todo[0].s); P.date = todo[0].date; }   // open the first one waiting
    const cur = (P.date || todayISO()) + '|' + (P.key || '');
    const box = todo.length ? `<div class="card sd-todo"><h3>📚 Homework to check <span class="pill warn">${todo.length}</span></h3>
        ${todo.slice(0, 8).map(x => { const on = cur === x.date + '|' + sylKey(x.c, x.s); return `<button type="button" class="sd-ti ${on ? 'on' : ''}" onclick="DRAFT.chk=null;go('diary',{m:'check',key:${jsq(sylKey(x.c, x.s))},date:'${x.date}'})"><b>Class ${esc(x.c)} · ${esc(x.s)}</b><span>${x.date === todayISO() ? 'Due today' : 'Due ' + esc(fmtDate(x.date)) + ' — not checked'} · ${esc(x.hw.join(' / ').slice(0, 60))}</span></button>`; }).join('')}
        ${todo.length > 8 ? `<p class="mut">+ ${todo.length - 8} more</p>` : ''}</div>`
      : `<div class="card sd-todo ok"><b>✓ No homework waiting to be checked</b><small class="mut" style="display:block">Homework you give in the diary comes here on the day it is due.</small></div>`;
    return box + _vCheck();
  };
  // tap a child's name: Done → Not done → Half → blank (quicker than aiming at the small buttons)
  window.sdCycle = function (id) { if (!DRAFT.chk) return; const r = DRAFT.chk.rows[id]; if (!r) return; const o = ['', 'done', 'not', 'half']; r.hw = o[(o.indexOf(r.hw || '') + 1) % 4]; DRAFT.chk.changed = true; render(); };
  const _render = render;
  render = function () {
    _render();
    if (TAB !== 'diary') return;
    // Check work switch: how many are waiting
    if (P.m !== 'check') { const n = sdToCheck().length; const b = document.querySelector('.seg button:nth-child(2)'); if (b && n && !b.querySelector('em')) b.insertAdjacentHTML('beforeend', ` <em class="sd-badge">${n}</em>`); }
    else document.querySelectorAll('.chk-row:not(.ab) .n').forEach(n => { const row = n.closest('.chk-row'), btn = row && row.querySelector('.hwb button'); const m = btn && (btn.getAttribute('onclick') || '').match(/chkSet\('([^']+)'/); if (m) { n.style.cursor = 'pointer'; n.onclick = () => sdCycle(m[1]); } });
    if (P.m === 'check') { const b = [...document.querySelectorAll('.btns .btn')].find(x => /All homework done/.test(x.textContent)); if (b) b.classList.add('go'); const h = document.querySelector('.chk-card .hint'); if (h) h.textContent = 'Fastest: tap “All homework done”, then tap the NAME of each child who did not (tap again for Half). Copy: tap the book.'; }
  };
})();
