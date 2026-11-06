// ============================================================================
//  Teacher App — clear setup like the Chairman app (owner, 6 Nov 2026: "bahut confusion hai, Chairman Sir ke app jaisa
//  proper option setup karo"; "learning / reading ek baar bulk me entry, uske baad daily"). Loaded LAST.
//
//  1. Search any student from the header (name, father, mobile, adm. no) — like the Chairman app.
//  2. ☰ Menu: every feature in one list, grouped (Daily · Weekly · Exams · Students · Parents & staff · Principal),
//     each with one line on what it is for.
//  3. Principal tabs: Home · Cross-check · Students · Staff · Connect. Staff = Arrangement · Timetable · Teacher work · Leave.
//  4. Levels (Attendance → Levels): one class at a time, one thing at a time (Reading, Writing, … / Diary given, ID …),
//     tap per child like attendance, Save → teacher_api.php?a=recbulk. Each child's report still edits one child.
// ============================================================================
'use strict';

// ---------------------------------------------------------------- 3. Staff tab
function vStaffHub() {
  const m = ['arr', 'tt', 'work', 'leave'].includes(P.m) ? P.m : 'arr';
  const segs = seg([['arr', 'Arrangement'], ['tt', 'Timetable'], ['work', 'Teacher work'], ['leave', 'Leave']], m, `(k=>go('staffhub',{m:k}))`);
  if (m === 'tt') return segs + vTT();
  if (m === 'work') { const R = teacherRanks();
    return segs + `<div class="card"><h3>Teacher work <small>last 7 days</small></h3>${R.map((r, i) => `<div class="bd-r">${rankBadge(i)}<span class="nm"><b>${esc(r.name)}</b><small>${r.done} of ${r.all} done — attendance, diary, daily check, homework check</small></span><em class="${r.p >= 90 ? 'ok' : r.p < 60 ? 'bad' : ''}">${r.p}%</em></div>`).join('') || '<div class="empty">Nothing due yet.</div>'}
      <p class="mut" style="margin:6px 0 0">Tap Cross-check → Teachers to see what is pending today.</p></div>`; }
  if (m === 'leave') { const t = todayISO(), pend = S.leaves.filter(l => l.status === 'Pending'), up = S.leaves.filter(l => l.status === 'Approved' && l.to >= t).sort((a, b) => a.from.localeCompare(b.from));
    return segs + `<div class="card"><h3>Waiting for you <small>${pend.length}</small></h3>${pend.map(l => `<div class="pd-ap"><div class="t"><b>${esc(T(l.tid).name)}</b><span>${esc(fmtDate(l.from))}${l.to !== l.from ? ' – ' + esc(fmtDate(l.to)) : ''} · ${esc(l.reason || '')}</span></div><div class="btns"><button class="btn s go" onclick="pdLeave(${jsq(l.id)},'Approved')">Approve</button><button class="btn s w" onclick="pdLeave(${jsq(l.id)},'Rejected')">Reject</button></div></div>`).join('') || '<div class="empty">No leave request.</div>'}</div>
      <div class="card"><h3>Coming leave <small>${up.length}</small></h3>${up.map(l => `<div class="li"><div class="t"><b>${esc(T(l.tid).name)}</b><span>${esc(fmtDate(l.from))}${l.to !== l.from ? ' – ' + esc(fmtDate(l.to)) : ''} · ${esc(l.reason || '')}</span></div><span class="pill ok">Approved</span></div>`).join('') || '<div class="empty">Nobody is on leave ahead.</div>'}
      <p class="mut" style="margin:6px 0 0">An approved leave is counted as absent; the arrangement for those days is made by itself.</p></div>`; }
  return segs + vStaffArr();
}
(function () {
  const _go = go;
  go = function (tab, p) {
    if (tab === 'staff' || tab === 'tt') return _go('staffhub', Object.assign({}, p || {}, { m: tab === 'tt' ? 'tt' : 'arr' }));
    if (tab === 'prin' && p && p.screen) return _go('staffhub', Object.assign({}, p, { m: p.screen === 'tt' ? 'tt' : 'arr', screen: undefined }));
    return _go(tab, p);
  };
})();
// teacher-ops.js maps go('staff') / go('tt') to go('prin', {screen}); the wrapper above turns that into the Staff tab.

// ---------------------------------------------------------------- 4. Levels / record for a whole class
const BULK_LV = [['read', 'Reading'], ['write', 'Writing'], ['learn', 'Understanding'], ['speak', 'Speaking'], ['beh', 'Behaviour']];
const BULK_REC = [['diary', 'Diary given'], ['id', 'ID card given'], ['book', 'Books complete'], ['dress', 'Dress / uniform'], ['wa', 'WhatsApp no. in record'], ['waGroup', 'In class WhatsApp group']];
function vBulk(c) {
  const R = needRep(c), kids = studentsOf(c), ph = needPhotos(c);
  if (R.loading) return '<div class="load"><i></i></div>';
  if (R.err) return `<div class="card">${esc(R.err)} <button class="btn s l" onclick="delete REP['${esc(c)}'];render()">Try again</button></div>`;
  const mode = P.bm === 'rec' ? 'rec' : 'lv', F = mode === 'lv' ? BULK_LV : BULK_REC, f = F.some(x => x[0] === P.bf) ? P.bf : F[0][0];
  if (!DRAFT.bulk || DRAFT.bulk.c !== c) DRAFT.bulk = { c, rows: {}, n: 0 };
  const B = DRAFT.bulk, cur = sid => { const r = B.rows[sid] || {}, o = (R.recs || {})[sid] || {};
    return mode === 'lv' ? ((r.levels && f in r.levels) ? r.levels[f] : ((o.levels || {})[f] || 0)) : (f in r ? r[f] : (o[f] === undefined ? null : o[f])); };
  const filled = kids.filter(s => mode === 'lv' ? cur(s.id) > 0 : cur(s.id) !== null).length;
  const opts = mode === 'lv' ? [[1, 'Needs help'], [2, 'Average'], [3, 'Good'], [4, 'Excellent']] : [[1, 'Yes'], [0, 'No']];
  return `<div class="card"><h3>Fill for the whole class <small>Class ${esc(c)}</small></h3>
      <p class="mut" style="margin:0 0 8px">Do it once for everyone now; later change any child from Students → the child's report. Changes of levels are kept month by month.</p>
      ${seg([['lv', 'Learning levels'], ['rec', 'Student record']], mode, `(k=>{go('att',{c:${jsq(c)},m:'bulk',bm:k})})`)}
      <div class="chips" style="margin-top:8px">${F.map(([k, l]) => `<button class="chip ${k === f ? 'on' : ''}" onclick="go('att',{c:${jsq(c)},m:'bulk',bm:'${mode}',bf:'${k}'})">${l}</button>`).join('')}</div></div>
    <div class="card bulk"><div class="roll-head"><b>${esc((F.find(x => x[0] === f) || [])[1])}</b><span class="pill">${filled}/${kids.length} filled</span></div>
      <div class="btns" style="margin:6px 0">${opts.map(([v, l]) => `<button class="btn s l" onclick="bulkAll('${mode}','${f}',${v})">All: ${l}</button>`).join('')}</div>
      ${kids.map(s => { const v = cur(s.id);
        return `<div class="bk-r"><span class="ph sm">${ph[s.id] ? `<img src="${esc(ph[s.id].src)}" alt="" loading="lazy">` : `<i>${esc(initials(s.name))}</i>`}</span><b>${esc(s.name)}</b>
          <div class="bk-o ${mode}">${opts.map(([o, l]) => `<button type="button" class="${mode === 'lv' ? 'l' + o : o ? 'y' : 'x'} ${v === o ? 'on' : ''}" onclick="bulkSet('${mode}','${f}',${jsq(s.id)},${o})">${mode === 'lv' ? l.split(' ')[0] : l}</button>`).join('')}</div></div>`; }).join('')}
      <div class="sticky"><button class="btn go full" ${B.n ? '' : 'disabled'} onclick="bulkSave(${jsq(c)})">Save · ${Object.keys(B.rows).length} child(ren) changed</button></div></div>`;
}
function bulkSet(mode, f, sid, v) {
  const B = DRAFT.bulk, r = B.rows[sid] = B.rows[sid] || {};
  if (mode === 'lv') { r.levels = r.levels || {}; r.levels[f] = v; } else r[f] = v;
  B.n++; render();
}
function bulkAll(mode, f, v) { studentsOf(DRAFT.bulk.c).forEach(s => { const r = DRAFT.bulk.rows[s.id] = DRAFT.bulk.rows[s.id] || {}; if (mode === 'lv') { r.levels = r.levels || {}; r.levels[f] = v; } else r[f] = v; }); DRAFT.bulk.n++; render(); }
async function bulkSave(c) {
  const B = DRAFT.bulk; if (!B || !Object.keys(B.rows).length) return;
  try { const r = await api('teacher_api.php?a=recbulk', { cls: c, rows: B.rows }); DRAFT.bulk = null; delete REP[c]; BOARD = null; toast(r.saved + ' child(ren) saved.'); render(); }
  catch (e) { toast(e.message); }
}

// ---------------------------------------------------------------- 1. Search  ·  2. Menu
function searchList(q) {
  q = String(q || '').trim().toLowerCase(); if (q.length < 2) return [];
  const allowed = isP() ? null : new Set([...myCT(), ...myClasses()]);
  return S.students.filter(s => s && (!allowed || allowed.has(s.cls)) && [s.name, s.father, s.phone, s.adm].some(v => String(v || '').toLowerCase().includes(q))).slice(0, 12);
}
function searchShow(q) {
  const box = $('srR'); if (!box) return; const L = searchList(q);
  box.innerHTML = String(q || '').trim().length < 2 ? '' : L.length ? L.map(s => `<button type="button" onclick="srOpen(${jsq(s.id)})"><b>${esc(s.name)}</b><span>Class ${esc(s.cls)}${s.father ? ' · ' + esc(s.father) : ''}${s.adm ? ' · Adm ' + esc(s.adm) : ''}</span></button>`).join('') : '<p>No student found.</p>';
}
function srOpen(sid) { const s = studentById(sid); if (!s) return; const q = $('srQ'); if (q) q.value = ''; go('att', { c: s.cls, m: 'stu', sid }); }
const MENU = [
  ['Daily', [['att', {}, 'check', 'Attendance', 'Subah haziri — sirf absent par tap'], ['att', { m: 'kit' }, 'bill', 'Daily check', 'Diary · ID · kitaab · dress'], ['diary', {}, 'book', 'Diary', 'Har bell ka topic, CW, HW'], ['diary', { m: 'check' }, 'list', 'Check work', 'Homework aur copy check']]],
  ['Weekly', [['plan', { m: 'lp' }, 'note', 'Lesson plan', 'Hafte ka plan — Auto fill'], ['plan', { m: 'syl' }, 'list', 'Syllabus', 'Kitaab, chapter, page, exam tick'], ['notes', {}, 'cam', 'Notes', 'Photo se chapter notes']]],
  ['Exams', [['res', {}, 'chart', 'Marks & result', 'Number daalo — result apne aap'], ['plan', { m: 'paper' }, 'print', 'Question paper', 'Chapter text se paper']]],
  ['Students', [['att', { m: 'stu' }, 'list', 'Student reports', 'Har bachche ki poori report'], ['att', { m: 'bulk' }, 'check', 'Levels (whole class)', 'Reading, writing… ek saath'], ['board', {}, 'chart', 'Board', 'Classes aur bachchon ka muqabla']]],
  ['Parents & staff', [['conn', { m: 'msg' }, 'wa', 'Messages to parents', 'Absent, homework, fee, PTM'], ['conn', { m: 'fee' }, 'phone', 'Fee calls', 'Class teacher — fee yaad dilana'], ['conn', { m: 'chat' }, 'chat', 'Parent chat', 'Parents se baat'], ['conn', { m: 'staff' }, 'chat', 'Staff chat', 'Teachers aur Principal']]],
  ['Principal', [['pdash', {}, 'sun', 'Home', 'Aaj ka school, approvals, alerts'], ['prin', {}, 'check', 'Cross-check', 'Har kaam — kisne kiya, kisne nahi'], ['staffhub', { m: 'arr' }, 'list', 'Arrangement', 'Absent teacher ki class'], ['staffhub', { m: 'tt' }, 'note', 'Timetable', 'Class ka timetable badlo'], ['staffhub', { m: 'leave' }, 'bill', 'Leave', 'Chhutti approve'], ['att', { m: 'cal' }, 'list', 'Holidays', 'Sab classes ki chhutti'], ['board', { m: 'gen' }, 'list', 'Boys / girls', 'Ladka / ladki bharna']]]
];
function openMenu() {
  const p = isP(), ct = myCT().length;
  SHEET = () => `<h2>☰ All features</h2>${MENU.filter(([g]) => g !== 'Principal' || p).map(([g, L]) => `<div class="mn-g"><small>${g}</small><div class="mn-l">${L.filter(([t, q]) => p || (t !== 'att' || ct || q.m === 'stu')).map(([t, q, i, l, x]) => `<button type="button" onclick='SHEET=null;${["pdash", "prin", "staffhub"].includes(t) ? 'store.set("ta_mode","principal");' : ''}go(${JSON.stringify(t)},${JSON.stringify(q)})'><span>${ic(i)}</span><div><b>${l}</b><small data-notr>${x}</small></div></button>`).join('')}</div></div>`).join('')}
    <button class="btn l full" onclick="openGuide()">❓ How to use the app</button>`;
  render();
}
(function () {
  const _render = render;
  render = function () {
    _render();
    if (!S) return;
    const loc = document.querySelector('.head .loc'), av = loc && loc.querySelector('.av');
    if (av && !loc.querySelector('.menub')) av.insertAdjacentHTML('beforebegin', `<button class="menub" onclick="openMenu()" aria-label="All features">☰</button>`);
    const head = document.querySelector('.head'), show = ['home', 'pdash', 'board'].includes(TAB) && !SHEET;
    if (head && show && !head.querySelector('.srch')) head.insertAdjacentHTML('beforeend', `<div class="srch"><label>${ic('list')}<input id="srQ" placeholder="Search a student — name, father, mobile, adm. no" oninput="searchShow(this.value)" autocomplete="off"></label><div id="srR" class="sr-r"></div></div>`);
  };
})();
