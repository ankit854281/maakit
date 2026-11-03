/* =====================================================================
   SNMSIS TEACHER APP  (maakit.in/school/teacher.php) — owner, 3 Nov 2026
   One phone app for the teacher's whole day, on the SAME records as the web ERP:
     Aaj      today's bells, what is pending, notices
     Haziri   attendance (P / A / Leave), class holiday, month register with bulk
              update of back days, list of days not marked yet
     Diary    topic, class work, homework (minutes + day), books, revision per bell
     Plan     weekly lesson plan (from syllabus or a photo) + syllabus chapters
     Notes    chapter-wise notes from photos of the book (AI, or the phone reads it)
     Result   Term-1 / Term-2 marks (Pre-Test 10 · Notebook 5 · SEA 5 · Term 80),
              instant result with grade and rank, Final = Term-1 + Term-2
   Saving: api.php?a=sync (same permissions as the web ERP — the server decides),
   progress_api.php (marks), scan_api.php (photos), teacher_api.php (register,
   class holidays, notes). Changes that could not be sent wait on this phone and
   go as soon as the internet is back.
   Powered by Maakit.
   ===================================================================== */
'use strict';
const $ = id => document.getElementById(id);
const store = { get(k) { try { return localStorage.getItem(k); } catch (e) { return null; } }, set(k, v) { try { v == null ? localStorage.removeItem(k) : localStorage.setItem(k, v); } catch (e) {} } };
const LOGO = 'img/snmsis-logo.png';
const DAYS = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
const BELLS = [{ n: '1st', s: '09:00', e: '09:35' }, { n: '2nd', s: '09:35', e: '10:10' }, { n: '3rd', s: '10:10', e: '10:45' },
  { n: '4th', s: '11:05', e: '11:40' }, { n: '5th', s: '11:40', e: '12:15' }, { n: '6th', s: '12:15', e: '12:50' }, { n: '7th', s: '12:50', e: '13:30' }];
const PARTS = [['pt', 'Pre-Test', 10], ['nb', 'Notebook', 5], ['sea', 'SEA', 5], ['te', 'Term', 80]];
const TABS = [['home', 'Aaj', 'sun'], ['att', 'Haziri', 'check'], ['diary', 'Diary', 'book'], ['plan', 'Plan', 'list'], ['notes', 'Notes', 'note'], ['res', 'Result', 'chart']];
const ICONS = {
  sun: '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>',
  check: '<rect x="4" y="4" width="16" height="17" rx="3"/><path d="M9 2v4M15 2v4M8.5 13.5l2.5 2.5 4.5-5"/>',
  book: '<path d="M4 5.5A2.5 2.5 0 0 1 6.5 3H20v15H6.5A2.5 2.5 0 0 0 4 20.5z"/><path d="M4 20.5A2.5 2.5 0 0 0 6.5 23H20v-5M8 7h8M8 11h6"/>',
  list: '<path d="M9 6h11M9 12h11M9 18h11"/><path d="M4 6l1 1 2-2M4 12l1 1 2-2M4 18l1 1 2-2"/>',
  note: '<path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/><path d="M14 3v6h6M8 13h8M8 17h5"/>',
  chart: '<path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>',
  cam: '<path d="M4 8h3l2-3h6l2 3h3a1 1 0 0 1 1 1v10a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V9a1 1 0 0 1 1-1z"/><circle cx="12" cy="13.5" r="3.5"/>',
  wa: '<path d="M4 20l1.3-4A8 8 0 1 1 8 18.7z"/><path d="M9 9.5c.5 2 2 3.6 4 4.5l1.2-1.2 2 .8v1.6c-4 .5-8.5-3.9-8-8h1.6l.8 2z"/>',
  print: '<path d="M7 9V3h10v6M7 18H5a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="7" y="14" width="10" height="7"/>',
  back: '<path d="M15 5l-7 7 7 7"/>', next: '<path d="M9 5l7 7-7 7"/>'
};
const ic = (n, cls) => `<svg class="i ${cls || ''}" viewBox="0 0 24 24" aria-hidden="true">${ICONS[n] || ''}</svg>`;

// ---------------------------------------------------------------- small helpers
const pad = n => String(n).padStart(2, '0');
function todayISO() { const d = new Date(); return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()); }
function addDays(iso, n) { const d = new Date(iso + 'T00:00:00'); d.setDate(d.getDate() + n); return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()); }
function dayIdx(iso) { const w = new Date(iso + 'T00:00:00').getDay(); return w === 0 ? -1 : w - 1; }
function fmtDate(iso) { if (!iso) return ''; return new Date(iso + 'T00:00:00').toLocaleDateString('en-IN', { weekday: 'short', day: '2-digit', month: 'short' }); }
function fmtLong(iso) { if (!iso) return ''; return new Date(iso + 'T00:00:00').toLocaleDateString('en-IN', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }); }
function fmtTime(ts) { return new Date(ts).toLocaleString('en-IN', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' }); }
function t12(hhmm) { let [h, m] = hhmm.split(':').map(Number); const ap = h >= 12 ? 'PM' : 'AM'; h = h % 12 || 12; return h + ':' + pad(m) + ' ' + ap; }
function nowMin() { const d = new Date(); return d.getHours() * 60 + d.getMinutes(); }
function toMin(hhmm) { const [h, m] = hhmm.split(':').map(Number); return h * 60 + m; }
function esc(s) { return String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c])); }
function jsq(s) { return esc(JSON.stringify(String(s ?? ''))); }
function uid() { return Date.now().toString(36) + Math.random().toString(36).slice(2, 6); }
function ymOf(iso) { return iso.slice(0, 7); }
function ymAdd(ym, n) { const [y, m] = ym.split('-').map(Number), d = new Date(y, m - 1 + n, 1); return d.getFullYear() + '-' + pad(d.getMonth() + 1); }
function ymName(ym) { const [y, m] = ym.split('-').map(Number); return new Date(y, m - 1, 1).toLocaleString('en-IN', { month: 'long', year: 'numeric' }); }
function daysIn(ym) { const [y, m] = ym.split('-').map(Number); return new Date(y, m, 0).getDate(); }
function phone91(p) { const d = String(p || '').replace(/\D/g, ''); return d.length >= 10 ? '91' + d.slice(-10) : ''; }
function wa(phone, text) { return 'https://wa.me/' + phone91(phone) + '?text=' + encodeURIComponent(text); }
function grade(p) { return p >= 91 ? 'A1' : p >= 81 ? 'A2' : p >= 71 ? 'B1' : p >= 61 ? 'B2' : p >= 51 ? 'C1' : p >= 41 ? 'C2' : p >= 33 ? 'D' : p >= 21 ? 'E1' : 'E2'; }
let toastT = null;
function toast(msg) { const t = $('toast'); t.textContent = msg; t.classList.add('on'); clearTimeout(toastT); toastT = setTimeout(() => t.classList.remove('on'), 3600); }

// ---------------------------------------------------------------- state
let S = null, ME = null, AI = null, TAB = store.get('ta_tab') || 'home', P = {}, LOGIN = {}, SHEET = null, STATUS = null;
let lastV = 0, loadedFrom = null, NET = 'ok', flushing = false;
const DAYS_LOADED = {}, MONTHS = {};
let QUEUE = [], LOGQ = [];
function qKey() { return 'ta_q_' + (ME ? ME.role + ME.tid : ''); }
function saveQueue() { store.set(qKey(), JSON.stringify({ q: QUEUE, l: LOGQ })); }
function loadQueue() { try { const o = JSON.parse(store.get(qKey()) || '{}'); QUEUE = o.q || []; LOGQ = o.l || []; } catch (e) { QUEUE = []; LOGQ = []; } }

function emptyState() {
  return { settings: {}, classes: [], teachers: {}, alloc: [], grid: null, students: [], staffAtt: {}, studAtt: {}, diary: {}, signed: {},
    syl: {}, exams: [], leaves: [], notices: [], lplan: {}, cal: [], clsHol: {} };
}
function setRec(k, r, d) {
  const p = r.split('|');
  switch (k) {
    case 'settings': S.settings = d || {}; break;
    case 'classes': S.classes = d || []; break;
    case 'teacher': S.teachers[r] = d; break;
    case 'alloc': S.alloc = d || []; break;
    case 'grid': S.grid = d; break;
    case 'exams': S.exams = d || []; break;
    case 'student': { const i = S.students.findIndex(x => x.id === r); if (i >= 0) S.students[i] = d; else S.students.push(d); break; }
    case 'staffAtt': S.staffAtt[r] = d; break;
    case 'studAtt': (S.studAtt[p[0]] = S.studAtt[p[0]] || {})[p[1]] = d; break;
    case 'diary': { const a = S.diary[p[0]] = S.diary[p[0]] || {}; (a[p[1]] = a[p[1]] || {})[p[2]] = d; break; }
    case 'signed': (S.signed[p[0]] = S.signed[p[0]] || {})[p[1]] = d; break;
    case 'syl': S.syl[r] = d; break;
    case 'leave': { const i = S.leaves.findIndex(x => x.id === r); if (i >= 0) S.leaves[i] = d; else S.leaves.push(d); break; }
    case 'notice': { const i = S.notices.findIndex(x => x.id === r); if (i >= 0) S.notices[i] = d; else S.notices.push(d); break; }
    case 'lplan': S.lplan[r] = d; break;
    case 'cal': { const i = S.cal.findIndex(x => x.id === r); if (i >= 0) S.cal[i] = d; else S.cal.push(d); break; }
    case 'clsHol': (S.clsHol[p[0]] = S.clsHol[p[0]] || {})[p[1]] = d; break;
  }
}
function delRec(k, r) {
  const p = r.split('|');
  if (k === 'student') S.students = S.students.filter(x => x.id !== r);
  else if (k === 'notice') S.notices = S.notices.filter(x => x.id !== r);
  else if (k === 'cal') S.cal = S.cal.filter(x => x.id !== r);
  else if (k === 'teacher') delete S.teachers[r];
  else if (k === 'clsHol') { if (S.clsHol[p[0]]) delete S.clsHol[p[0]][p[1]]; }
  else if (k === 'studAtt') { if (S.studAtt[p[0]]) delete S.studAtt[p[0]][p[1]]; }
}
function applyRecs(recs) {
  for (const k in LOADC) delete LOADC[k];
  const pending = new Set(QUEUE.map(x => x.k + '|' + x.r));
  recs.forEach(rec => { if (pending.has(rec.k + '|' + rec.r)) return; if (rec.x) delRec(rec.k, rec.r); else setRec(rec.k, rec.r, rec.d); });
  S.leaves.sort((a, b) => (b.at || 0) - (a.at || 0)); S.notices.sort((a, b) => String(b.date).localeCompare(String(a.date))); S.cal.sort((a, b) => String(a.date).localeCompare(String(b.date)));
}

// ---------------------------------------------------------------- server
async function api(path, body) {
  let r, j = null;
  try { r = await fetch(path, { method: body ? 'POST' : 'GET', cache: 'no-store', headers: { 'Content-Type': 'application/json', 'X-School-Token': store.get('sch_token') || '' }, ...(body ? { body: JSON.stringify(body) } : {}) }); }
  catch (e) { const er = new Error('No internet. Please check the connection and try again.'); er.net = true; throw er; }
  try { j = await r.json(); } catch (e) {}
  if (r.status === 401 && !/a=login/.test(path)) { store.set('sch_token', null); S = null; LOGIN = { msg: 'Please log in again.' }; render(); }
  if (!r.ok || !j || !j.ok) { const er = new Error((j && j.error) || 'Server problem. Please try again.'); er.code = r.status; er.noAi = !!(j && j.noAi); throw er; }
  return j;
}
async function boot() {
  if ('serviceWorker' in navigator) navigator.serviceWorker.register('teacher-sw.js').catch(() => {});
  if (!store.get('sch_token')) return render();
  await load();
}
async function load() {
  $('app').innerHTML = '<div class="load"><i></i>Opening your classes…</div>';
  try {
    const st = await api('teacher_api.php?a=state');
    AI = st.ai;
    const res = await api('api.php?a=load&days=70');
    if (!['teacher', 'principal'].includes(res.me.role)) { LOGIN = { msg: 'This app is for teachers. Please log in with a teacher PIN.' }; store.set('sch_token', null); S = null; return render(); }
    ME = res.me; S = emptyState(); applyRecs(res.records); lastV = res.now; loadedFrom = res.from;
    loadQueue(); QUEUE.forEach(x => setRec(x.k, x.r, x.d));
    render(); flush();
  } catch (e) {
    if (e.code === 428) { LOGIN = { step: 'new' }; S = null; return render(); }
    if (e.code === 401) return;
    $('app').innerHTML = `<div class="load">${esc(e.message)}<button class="btn" onclick="load()">Try again</button></div>`;
  }
}
// Every change: shown at once on this phone, queued, sent. If the internet is off it waits (also across a restart).
function put(k, r, d, logK, logX) {
  setRec(k, r, d);
  QUEUE = QUEUE.filter(x => !(x.k === k && x.r === r)); QUEUE.push({ k, r, d });
  if (logK) LOGQ.push({ k: logK, x: logX });
  saveQueue(); flush();
}
async function flush() {
  if (flushing || (!QUEUE.length && !LOGQ.length) || !store.get('sch_token')) return setNet();
  flushing = true; NET = 'saving'; setNet();
  const batch = QUEUE.slice(0, 300), logs = LOGQ.slice(0, 60);
  try {
    const res = await api('api.php?a=sync', { put: batch, del: [], log: logs });
    const sent = new Set(batch.map(x => x.k + '|' + x.r + '|' + JSON.stringify(x.d)));
    QUEUE = QUEUE.filter(x => !sent.has(x.k + '|' + x.r + '|' + JSON.stringify(x.d))); LOGQ = LOGQ.slice(logs.length); saveQueue();
    NET = 'ok';
    if (res.rejected && res.rejected.length) {
      toast(res.rejected.map(x => x.error || 'Not allowed for your login: ' + x.k).filter((v, i, a) => a.indexOf(v) === i).join(' '));
      setTimeout(load, 1200);              // show the server's copy again, not the refused change
    }
  } catch (e) {
    if (e.net || e.code >= 500 || e.code === 429) { NET = 'off'; setTimeout(flush, 15000); }   // keep it and try again
    else if (e.code === 401 || e.code === 428) { /* login screen is shown; the changes wait on this phone */ }
    else { toast(e.message); QUEUE = QUEUE.slice(batch.length); LOGQ = LOGQ.slice(logs.length); saveQueue(); NET = 'ok'; setTimeout(load, 1200); }
  } finally { flushing = false; setNet(); if (NET === 'ok' && (QUEUE.length || LOGQ.length)) setTimeout(flush, 50); }
}
async function refresh() {
  if (!S || !store.get('sch_token')) return;
  try { const res = await api('api.php?a=since&v=' + lastV + '&l=999999999999'); lastV = res.now; if (res.records.length) { applyRecs(res.records); softRender(); } if (NET === 'off') flush(); }
  catch (e) { if (e.net) { NET = 'off'; setNet(); } }
}
setInterval(refresh, 45000);
document.addEventListener('visibilitychange', () => { if (!document.hidden) refresh(); });
addEventListener('online', () => flush());
function setNet() {
  const el = $('net'); if (!el) return;
  const n = QUEUE.length;
  el.className = 'net' + (NET === 'off' ? ' off' : NET === 'saving' ? ' sv' : '');
  el.textContent = NET === 'off' ? (n ? n + ' waiting · offline' : 'Offline') : NET === 'saving' ? 'Saving…' : 'Saved';
}
async function ensureDate(d) {
  if (!d || !loadedFrom || d >= loadedFrom || DAYS_LOADED[d]) return;
  try { const res = await api('api.php?a=day&date=' + d); applyRecs(res.records); DAYS_LOADED[d] = true; } catch (e) { toast(e.message); }
}
// One class-month of attendance + class holidays (teacher_api.php) — for the register and back days older than 70 days.
async function ensureMonth(c, ym, force) {
  const key = c + '|' + ym; if (MONTHS[key] && !force) return;
  const res = await api('teacher_api.php?a=month&cls=' + encodeURIComponent(c) + '&ym=' + ym);
  const pending = new Set(QUEUE.map(x => x.k + '|' + x.r));
  for (let i = 1; i <= daysIn(ym); i++) { const d = ym + '-' + pad(i);
    if (!pending.has('studAtt|' + d + '|' + c)) { if (res.att[d]) setRec('studAtt', d + '|' + c, res.att[d]); else if (S.studAtt[d]) delete S.studAtt[d][c]; }
    if (res.hol[d]) setRec('clsHol', d + '|' + c, res.hol[d]); else if (S.clsHol[d]) delete S.clsHol[d][c]; }
  MONTHS[key] = true;
}

// ---------------------------------------------------------------- school data helpers (same rules as app.js)
const me = () => ME ? ME.tid : '';
const isP = () => ME && ME.role === 'principal';
function T(tid) { return S.teachers[tid] || { name: tid }; }
function className() { return (S.classes || []).map(k => k.c); }
function myName() { const n = (T(me()).name || ME.label || '').replace(/\s*\(.*\)/, ''); return n; }
function myCT() { return isP() ? className() : (S.classes || []).filter(k => k.ct === me()).map(k => k.c); }
function myAllocs() { return S.alloc.filter(a => a.t === me() && (+a.n || 0) > 0); }
function allocList() { return isP() ? S.alloc.filter(a => (+a.n || 0) > 0) : myAllocs(); }
function studentsOf(c) { return S.students.filter(s => s && s.cls === c).sort((a, b) => String(a.name).localeCompare(String(b.name))); }
function studentById(id) { return S.students.find(s => s.id === id); }
function sesKey() { const s = String(S.settings.session || '').replace(/[–—]/g, '-').trim(); const m = s.match(/^(20\d\d)-(?:20)?(\d\d)$/); return m ? m[1] + '-' + m[2] : ''; }
function holidayOn(date) { return S.cal.find(e => e.type === 'Holiday' && e.date <= date && (e.to || e.date) >= date) || null; }
function clsHolOn(date, c) { return (S.clsHol[date] || {})[c] || null; }
function attOf(date, c) { return (S.studAtt[date] || {})[c] || null; }
function examEnd(e) { return e.to || (e.sheet && e.sheet.length ? e.sheet.map(x => x.date).sort().pop() : e.date); }
function examClasses(e) { return e && e.classes && e.classes.length ? e.classes : className(); }
function examBlocks(date, cls) { return S.exams.some(e => e.noClasses && e.date <= date && examEnd(e) >= date && examClasses(e).includes(cls)); }
function cell(c, d, b) { const i = S.grid && S.grid[c] ? S.grid[c][d][b] : -1; return i >= 0 ? S.alloc[i] : null; }
function teacherAt(t, d, b) { for (const c of className()) { const a = cell(c, d, b); if (a && a.t === t) return a; } return null; }
function staffAbsent(date) {
  const manual = (S.staffAtt[date] && S.staffAtt[date].absent) || [];
  const onLeave = S.leaves.filter(l => l.status === 'Approved' && l.from <= date && l.to >= date).map(l => l.tid);
  return [...new Set([...manual, ...onLeave])].sort();
}
function absentFrom(date, t) {
  if (S.leaves.some(l => l.status === 'Approved' && l.tid === t && l.from <= date && l.to >= date)) return 0;
  const a = S.staffAtt[date]; if (!a || !(a.absent || []).includes(t)) return null;
  return +((a.from || {})[t] || 0);
}
function absentAt(date, t, b) { const f = absentFrom(date, t); return f !== null && b >= f; }
function busyAt(date, x, b) { const d = dayIdx(date); const q = d < 0 ? null : teacherAt(x, d, b); return !!q && !examBlocks(date, q.c); }
// Arrangement: copied from app.js (arrangementBase / monthLoad) so a substitute sees the bells given to them. Change both together.
function arrangementBase(date, load) {
  const d = dayIdx(date); if (d < 0 || !S.grid || holidayOn(date)) return [];
  const absent = staffAbsent(date), ov = (S.staffAtt[date] && S.staffAtt[date].subs) || {}, extra = {}, need = [];
  absent.forEach(t => BELLS.forEach((b, i) => { if (!absentAt(date, t, i)) return; const a = teacherAt(t, d, i); if (!a || examBlocks(date, a.c)) return; need.push({ bell: i, cls: a.c, subj: a.s, absent: t }); }));
  const taken = i => need.filter(n => n.bell === i && n.sub).map(n => n.sub);
  const canTake = (x, n) => x !== n.absent && !absentAt(date, x, n.bell) && !busyAt(date, x, n.bell) && !taken(n.bell).includes(x) && (!S.teachers[x].bells || S.teachers[x].bells.includes(n.bell));
  const dayLoad = x => BELLS.filter((_, j) => busyAt(date, x, j)).length;
  const score = (x, n) => { let s = 0; if (S.alloc.some(q => q.t === x && q.s === n.subj)) s += 5; if (S.alloc.some(q => q.t === x && q.c === n.cls)) s += 2;
    s -= (extra[x] || 0) * 3 + (load[x] || 0) * 0.8 + dayLoad(x) * 0.3; if (x === 'P') s -= 4; if (['RI', 'SH', 'MO'].includes(x)) s -= 10; return s; };
  need.forEach(n => { const w = ov[n.absent + '|' + n.bell]; n.sub = null; n.manual = false; if (w === '-') n.manual = true; else if (w && S.teachers[w] && canTake(w, n)) { n.sub = w; n.manual = true; extra[w] = (extra[w] || 0) + 1; } });
  need.forEach(n => { if (n.manual) return; const pool = Object.keys(S.teachers).filter(x => canTake(x, n)).sort((x, y) => score(y, n) - score(x, n) || (x < y ? -1 : x > y ? 1 : 0)); n.sub = pool[0] || null; if (n.sub) extra[n.sub] = (extra[n.sub] || 0) + 1; });
  return need.sort((x, y) => x.bell - y.bell);
}
const LOADC = {};
function monthLoad(date) {
  if (LOADC[date]) return LOADC[date];
  const out = {}; let d = date.slice(0, 8) + '01';
  while (d < date) { if (staffAbsent(d).length) arrangementBase(d, {}).forEach(s => { if (s.sub) out[s.sub] = (out[s.sub] || 0) + 1; }); d = addDays(d, 1); }
  return LOADC[date] = out;
}
function myBells(date) {
  const tid = me(), d = dayIdx(date); if (d < 0 || !S.grid || holidayOn(date)) return [];
  const from = absentFrom(date, tid), out = [];
  BELLS.forEach((b, i) => { if (from !== null && i >= from) return; const a = teacherAt(tid, d, i); if (a && !examBlocks(date, a.c) && !clsHolOn(date, a.c)) out.push({ bell: i, cls: a.c, subj: a.s, arr: false }); });
  if (staffAbsent(date).length) arrangementBase(date, monthLoad(date)).filter(s => s.sub === tid && !clsHolOn(date, s.cls)).forEach(s => out.push({ bell: s.bell, cls: s.cls, subj: s.subj, arr: true, for: s.absent }));
  // periods the teacher added by hand on that day (no timetable bell): kept with the bell number they chose
  Object.entries((S.diary[date] || {})[tid] || {}).forEach(([b, e]) => { if (e && e.extra && !out.some(x => x.bell === +b)) out.push({ bell: +b, cls: e.cls, subj: e.subj, arr: !!e.arr, extra: true }); });
  return out.sort((x, y) => x.bell - y.bell);
}
function diaryEntry(date, bell) { return ((S.diary[date] || {})[me()] || {})[bell] || null; }
function bellOver(date, bell) { const t = todayISO(); if (date < t) return true; if (date > t) return false; return nowMin() >= toMin(BELLS[bell].e); }
function sylKey(c, s) { return c + '|' + s; }
function nextChapter(c, s) { const y = S.syl[sylKey(c, s)]; if (!y || !y.chapters) return null; const ym = ymOf(todayISO()), open = y.chapters.filter(x => !x.done); const ch = open.find(x => x.plan && x.plan <= ym) || open[0]; return ch ? ch.n : null; }
function isWorkDay(date, c) { return dayIdx(date) >= 0 && !holidayOn(date) && !(c && clsHolOn(date, c)); }
function sessionStart() { return S.settings.sessionStart || (todayISO().slice(0, 4) - (todayISO().slice(5, 7) < '04' ? 1 : 0)) + '-04-01'; }
// days this class should have attendance but does not (Sunday, school holidays and class holidays left out)
function pendingDays(c, from) {
  const out = [], t = todayISO(); let d = from;
  while (d <= t) { if (isWorkDay(d, c) && !attOf(d, c) && (d < t || nowMin() >= toMin(S.settings.attendanceCutoff || '09:30'))) out.push(d); d = addDays(d, 1); }
  return out;
}
function diaryPending(days) {
  const out = []; for (let i = 1; i <= days; i++) { const d = addDays(todayISO(), -i); const bl = myBells(d).filter(b => !diaryEntry(d, b.bell)); if (bl.length) out.push({ d, n: bl.length }); }
  return out;
}

// ---------------------------------------------------------------- shell
function go(tab, p) { TAB = tab; P = p || {}; store.set('ta_tab', tab); SHEET = null; LASTTAB = null; render(); const sc = document.querySelector('.scroll'); if (sc) sc.scrollTop = 0; }
function render() {
  if (!S) return loginView();
  const views = { home: vHome, att: vAtt, diary: vDiary, plan: vPlan, notes: vNotes, res: vResult };
  if (!views[TAB]) TAB = 'home';
  let body = ''; try { body = views[TAB](); } catch (e) { console.error(e); body = `<div class="card">This screen could not open: ${esc(e.message)}</div>`; }
  const initials = myName().split(/\s+/).filter(Boolean).map(x => x[0]).slice(0, 2).join('').toUpperCase() || 'T';
  const dueAtt = myCT().reduce((n, c) => n + (attOf(todayISO(), c) || clsHolOn(todayISO(), c) || !isWorkDay(todayISO(), c) ? 0 : 1), 0);
  const dueDiary = myBells(todayISO()).filter(b => bellOver(todayISO(), b.bell) && !diaryEntry(todayISO(), b.bell)).length;
  const dot = { att: dueAtt, diary: dueDiary }, sc0 = document.querySelector('.scroll'), y0 = sc0 ? sc0.scrollTop : 0, rg0 = document.querySelector('.reg'), rx0 = rg0 ? rg0.scrollLeft : 0, anim = TAB !== LASTTAB; LASTTAB = TAB;
  $('app').innerHTML = `<div class="head"><div class="loc"><div class="w"><small>${esc(S.settings.school || 'SN Mishra Singapore International School')}</small><b>${esc(greet())}, ${esc(myName())}</b></div>
      <button id="net" class="net" onclick="flush();refresh()"></button><button class="av" onclick="openMe()" aria-label="My account">${esc(initials)}</button></div>
    <div class="tabs">${TABS.map(([k, l, i]) => `<button class="tab ${TAB === k ? 'on' : ''}" onclick="go('${k}')">${dot[k] ? `<span class="dot">${dot[k]}</span>` : ''}${ic(i)}${l}</button>`).join('')}</div></div>
    <div class="scroll"><div class="body${anim ? ' anim' : ''}">${body}</div></div>${SHEET ? `<div class="shade" onclick="closeSheet()"></div><div class="sheet"><div class="grab"></div>${SHEET()}</div>` : ''}`;
  if (!anim) { const sc = document.querySelector('.scroll'); if (sc) sc.scrollTop = y0; const rg = document.querySelector('.reg'); if (rg) rg.scrollLeft = rx0; }
  setNet();
}
let LASTTAB = null;
function softRender() { const a = document.activeElement; if (a && /INPUT|TEXTAREA|SELECT/.test(a.tagName)) return; if ((DRAFT.att && DRAFT.att.changed) || (DRAFT.reg && Object.keys(DRAFT.reg.days).length) || DRAFT.note || SHEET) return; render(); }
function greet() { const h = new Date().getHours(); return h < 12 ? 'Good morning' : h < 17 ? 'Good afternoon' : 'Good evening'; }
function closeSheet() { SHEET = null; render(); }
function seg(items, cur, fn) { return `<div class="seg">${items.map(([k, l]) => `<button class="${k === cur ? 'on' : ''}" onclick="${fn}(${jsq(k)})">${l}</button>`).join('')}</div>`; }
function sel(id, items, cur, onch) { return `<select id="${id}" class="inp" onchange="${onch}">${items.map(([v, l]) => `<option value="${esc(v)}" ${v === cur ? 'selected' : ''}>${esc(l)}</option>`).join('')}</select>`; }
const DRAFT = {};

// ---------------------------------------------------------------- AAJ (today)
function vHome() {
  const d = todayISO(), bells = myBells(d), hol = holidayOn(d), ct = myCT();
  const filled = bells.filter(b => diaryEntry(d, b.bell)).length;
  const back = ct.flatMap(c => pendingDays(c, addDays(d, -30)).filter(x => x < d).map(x => ({ c, x })));
  const dp = diaryPending(6);
  const upcoming = [...S.cal.filter(e => e.type === 'Holiday' && (e.to || e.date) >= d).map(e => ({ date: e.date, to: e.to, t: e.title })),
    ...Object.entries(S.clsHol).flatMap(([dt, byC]) => ct.filter(c => byC[c] && dt >= d).map(c => ({ date: dt, t: 'Class ' + c + ': ' + byC[c].title })))]
    .sort((a, b) => a.date.localeCompare(b.date)).slice(0, 4);
  const mine = S.leaves.filter(l => l.tid === me()).slice(0, 2);
  const notices = S.notices.filter(n => n.to !== 'Parents').slice(0, 3);
  return `<div class="row"><h2>${fmtLong(d)}</h2></div>
    ${dayIdx(d) < 0 ? '<div class="ban hol">Sunday — no school today.</div>' : hol ? `<div class="ban hol"><b>Holiday:</b> ${esc(hol.title)}</div>` : ''}
    ${absentFrom(d, me()) !== null ? `<div class="ban warn">${absentFrom(d, me()) ? 'Half day today — you leave before the ' + BELLS[absentFrom(d, me())].n + ' bell.' : 'You are on leave / marked absent today.'}</div>` : ''}
    <div class="kp">
      <button class="k ac" onclick="go('diary')"><span>My bells today</span><b>${bells.length}</b><small>${filled} diary filled</small></button>
      ${ct.length ? ct.slice(0, isP() ? 1 : 3).map(c => { const a = attOf(d, c), h = clsHolOn(d, c), n = studentsOf(c).length;
        return `<button class="k ${a ? 'ok' : h || !isWorkDay(d) ? '' : 'go'}" onclick="go('att',{c:'${esc(c)}'})"><span>Class ${esc(c)} attendance</span><b>${a ? (n - (a.absent || []).length) + '/' + n : h ? 'Holiday' : !isWorkDay(d) ? '—' : 'Mark'}</b><small>${a ? 'Saved ' + fmtTime(a.at || Date.now()) : h ? esc(h.title) : !isWorkDay(d) ? 'No school' : 'Not marked yet'}</small></button>`; }).join('')
        : `<button class="k" onclick="go('notes')"><span>Chapter notes</span><b>${ic('cam')}</b><small>Make notes from a book photo</small></button>`}
    </div>
    ${back.length ? `<div class="ban warn"><div><b>${back.length} school day(s) without attendance</b> in the last 30 days (${[...new Set(back.map(x => 'Class ' + x.c))].join(', ')}).<br><button class="btn s go" style="margin-top:8px" onclick="go('att',{c:'${esc(back[0].c)}',m:'pending'})">Update them together</button></div></div>` : ''}
    ${dp.length ? `<div class="ban warn"><div><b>Diary not filled:</b> ${dp.map(x => `<a href="#" onclick="go('diary',{date:'${x.d}'});return false" style="text-decoration:underline">${fmtDate(x.d)} (${x.n})</a>`).join(', ')}</div></div>` : ''}
    <div class="card"><h3>My bells <small>${bells.length ? filled + ' of ' + bells.length + ' filled' : 'none'}</small></h3>
      ${bells.length ? bells.map(b => { const e = diaryEntry(d, b.bell);
        return `<div class="li" onclick="go('diary',{date:'${d}',bell:${b.bell}})"><div class="bell ${b.arr ? 'arr' : ''}"><b>${BELLS[b.bell].n.replace(/\D/g, '')}</b>${t12(BELLS[b.bell].s).replace(' ', '')}</div>
          <div class="t"><b>Class ${esc(b.cls)} · ${esc(b.subj)}</b><span>${b.arr ? 'Arrangement' + (b.for ? ' for ' + esc(T(b.for).name) : '') + ' · ' : ''}${e ? 'Topic: ' + esc(e.topic) : bellOver(d, b.bell) ? 'Diary not filled' : 'Upcoming'}</span></div>
          ${e ? '<span class="pill ok">Done</span>' : bellOver(d, b.bell) ? '<span class="pill warn">Fill</span>' : ''}</div>`; }).join('')
        : `<div class="empty">${dayIdx(d) < 0 ? 'Sunday — no classes.' : hol ? 'Holiday — no classes.' : S.grid ? 'No bells for you today.' : 'The timetable is not set yet. Use Diary → "Add a period".'}</div>`}</div>
    ${notices.length ? `<div class="card"><h3>Notices</h3>${notices.map(n => `<div class="li"><div class="t"><b>${esc(n.title)}</b><span>${fmtDate(n.date)}</span><p class="sm" style="margin:4px 0 0;white-space:pre-wrap">${esc(n.body)}</p></div></div>`).join('')}</div>` : ''}
    ${upcoming.length ? `<div class="card"><h3>Coming holidays</h3>${upcoming.map(h => `<div class="li"><div class="t"><b>${esc(h.t)}</b><span>${fmtDate(h.date)}${h.to && h.to !== h.date ? ' – ' + fmtDate(h.to) : ''}</span></div><span class="pill hol">Holiday</span></div>`).join('')}</div>` : ''}
    ${mine.length ? `<div class="card"><h3>My leave <small onclick="openMe()">Apply</small></h3>${mine.map(l => `<div class="li"><div class="t"><b>${fmtDate(l.from)}${l.to !== l.from ? ' – ' + fmtDate(l.to) : ''}</b><span>${esc(l.reason)}</span></div><span class="pill ${l.status === 'Approved' ? 'ok' : l.status === 'Rejected' ? 'bad' : 'warn'}">${esc(l.status)}</span></div>`).join('')}</div>` : ''}`;
}

// ---------------------------------------------------------------- HAZIRI (attendance)
function vAtt() {
  const allowed = myCT();
  if (!allowed.length) return `<div class="card"><h3>Attendance</h3><p class="mut">Only the class teacher marks attendance. You are not the class teacher of any class.</p></div>`;
  const c = allowed.includes(P.c) ? P.c : allowed[0], m = P.m || 'day';
  return `${allowed.length > 1 ? `<div class="fld">Class${sel('attC', allowed.map(x => [x, 'Class ' + x]), c, "DRAFT.att=null;DRAFT.reg=null;go('att',{c:this.value,m:'" + m + "'})")}</div>` : ''}
    ${seg([['day', 'Day'], ['reg', 'Register'], ['pending', 'Not marked']], m, `(k=>{DRAFT.att=null;DRAFT.reg=null;go('att',{c:${jsq(c)},m:k})})`)}
    ${m === 'reg' ? vRegister(c) : m === 'pending' ? vPending(c) : vAttDay(c)}`;
}
function vAttDay(c) {
  const t = todayISO(), date = P.date && P.date <= t ? P.date : t;
  if (date < (loadedFrom || '') && !DAYS_LOADED[date]) { ensureDate(date).then(() => render()); return '<div class="load"><i></i></div>'; }
  const saved = attOf(date, c), list = studentsOf(c), hol = holidayOn(date), ch = clsHolOn(date, c);
  const nav = `<div class="row"><button class="btn s w" onclick="DRAFT.att=null;go('att',{c:${jsq(c)},date:'${addDays(date, -1)}'})">${ic('back')}</button>
    <input type="date" class="inp" style="flex:1" max="${t}" value="${date}" onchange="DRAFT.att=null;go('att',{c:${jsq(c)},date:this.value})">
    <button class="btn s w" ${date >= t ? 'disabled' : ''} onclick="DRAFT.att=null;go('att',{c:${jsq(c)},date:'${addDays(date, 1)}'})">${ic('next')}</button></div>`;
  if (dayIdx(date) < 0) return nav + '<div class="ban hol">Sunday — no attendance.</div>';
  if (hol) return nav + `<div class="ban hol"><div><b>School holiday:</b> ${esc(hol.title)}<br><span class="sm">No attendance is taken on a holiday.</span></div></div>`;
  if (ch) return nav + `<div class="ban hol"><div><b>Class ${esc(c)} holiday:</b> ${esc(ch.title)}<br><span class="sm">Marked by ${esc(ch.by || '')}. This day is not counted as present or absent.</span><br><button class="btn s w" style="margin-top:8px" onclick="setClsHol(${jsq(c)},'${date}',false)">Remove holiday — take attendance</button></div></div>`;
  if (!DRAFT.att || DRAFT.att.key !== date + c) DRAFT.att = { key: date + c, st: stOf(saved, list), changed: false };
  const st = DRAFT.att.st, nA = list.filter(s => st[s.id] === 'A').length, nL = list.filter(s => st[s.id] === 'L').length;
  const absent = saved ? (saved.absent || []).filter(id => studentById(id)) : [];
  return nav + `<div class="card">
      <div class="row" style="margin-bottom:6px"><div>${saved ? `<span class="pill ok">Saved ${esc(fmtTime(saved.at || Date.now()))}</span>` : '<span class="pill warn">Not saved yet</span>'}</div>
        <div class="cnt"><span class="pill ok">${list.length - nA - nL} P</span><span class="pill bad">${nA} A</span><span class="pill lv">${nL} L</span></div></div>
      <p class="mut" style="margin:0 0 6px">Everyone is Present. Tap A for absent, L for leave.</p>
      ${list.length ? list.map((s, i) => `<div class="stu"><div class="n"><b>${i + 1}. ${esc(s.name)}</b><span>Adm ${esc(s.adm || '')}${s.father ? ' · ' + esc(s.father) : ''}</span></div>
        <div class="pal">${['P', 'A', 'L'].map(k => `<button class="${k} ${st[s.id] === k ? 'on' : ''}" onclick="attSet('${esc(s.id)}','${k}')">${k}</button>`).join('')}</div></div>`).join('')
        : '<div class="empty">No students in this class yet.</div>'}
      ${list.length ? `<div class="sticky"><div class="btns"><button class="btn w s" onclick="attAll('P')">All present</button><button class="btn w s" onclick="askHoliday(${jsq(c)},'${date}')">Mark holiday</button></div>
        <button class="btn go full" style="margin-top:8px" onclick="attSave(${jsq(c)},'${date}')">${saved ? 'Update attendance' : 'Save attendance'}</button></div>` : ''}
    </div>
    ${absent.length ? `<div class="card"><h3>Tell the parents <small>${absent.length} absent</small></h3>${absent.map(id => { const s = studentById(id), lv = (saved.st || {})[id] === 'L';
      const msg = `Dear Parent,\n${s.name} (Class ${c}) is ${lv ? 'on leave' : 'absent from school'} today, ${fmtLong(date)}.\nआज ${s.name} ${lv ? 'छुट्टी पर है' : 'विद्यालय में अनुपस्थित है। कृपया कारण बताएं'}।\n— ${S.settings.school || ''}`;
      return `<div class="li"><div class="t"><b>${esc(s.name)}</b><span>${lv ? 'Leave' : 'Absent'}${s.phone ? '' : ' · no phone saved'}</span></div>${s.phone ? `<a class="btn wa s" target="_blank" rel="noopener" href="${wa(s.phone, msg)}">${ic('wa')} Send</a>` : ''}</div>`; }).join('')}</div>` : ''}
    ${threeDays(c, date)}`;
}
function stOf(saved, list) { const st = {}; list.forEach(s => st[s.id] = 'P'); if (saved) (saved.absent || []).forEach(id => st[id] = ((saved.st || {})[id] === 'L') ? 'L' : 'A'); return st; }
function attSet(id, k) { DRAFT.att.st[id] = k; DRAFT.att.changed = true; render(); }
function attAll(k) { Object.keys(DRAFT.att.st).forEach(id => DRAFT.att.st[id] = k); DRAFT.att.changed = true; render(); }
function attRecord(st) {
  const absent = Object.keys(st).filter(id => st[id] === 'A' || st[id] === 'L'), L = {};
  Object.keys(st).forEach(id => { if (st[id] === 'L') L[id] = 'L'; });
  return { absent, ...(Object.keys(L).length ? { st: L } : {}), by: myName(), at: Date.now() };
}
function attSave(c, date) {
  const rec = attRecord(DRAFT.att.st), list = studentsOf(c);
  const names = rec.absent.map(id => (studentById(id) || {}).name).filter(Boolean);
  put('studAtt', date + '|' + c, rec, 'Student Attendance', `Class ${c} (${date}): ${list.length - names.length}/${list.length} present` + (names.length ? '. Absent/leave: ' + names.join(', ') : '') + ' — Teacher App');
  DRAFT.att = null; toast('Attendance saved.'); render();
}
function threeDays(c, date) {
  const days = Object.keys(S.studAtt).filter(d => d <= date && S.studAtt[d][c]).sort().slice(-3); if (days.length < 3) return '';
  const out = studentsOf(c).filter(s => days.every(d => (S.studAtt[d][c].absent || []).includes(s.id) && (S.studAtt[d][c].st || {})[s.id] !== 'L'));
  return out.length ? `<div class="ban bad"><div><b>Absent 3 school days in a row:</b> ${out.map(s => esc(s.name)).join(', ')} — please call the parents.</div></div>` : '';
}
function askHoliday(c, date) {
  SHEET = () => `<h2>Holiday for Class ${esc(c)}</h2><p class="mut" style="margin:0">${fmtLong(date)} — only this class. The day will not count as present or absent.${attOf(date, c) ? ' <b>The attendance saved for this day will be removed.</b>' : ''}</p>
    <div class="fld">Reason<input id="hT" class="inp" maxlength="120" placeholder="e.g. Class picnic, local holiday, exam day off"></div>
    <div class="btns">${['Local holiday', 'Exam day off', 'Class trip', 'Rain / weather'].map(x => `<button class="btn s l" onclick="$('hT').value=${jsq(x)}">${x}</button>`).join('')}</div>
    <button class="btn go full" onclick="setClsHol(${jsq(c)},'${date}',true,$('hT').value)">Mark holiday</button>`;
  render();
}
async function setClsHol(c, date, on, title) {
  try {
    await api('teacher_api.php?a=holiday', { cls: c, date, on, title: title || '' });
    if (on) { setRec('clsHol', date + '|' + c, { cls: c, date, title: (title || '').trim() || 'Holiday', by: myName(), at: Date.now() }); if (S.studAtt[date]) delete S.studAtt[date][c]; }
    else if (S.clsHol[date]) delete S.clsHol[date][c];
    DRAFT.att = null; SHEET = null; toast(on ? 'Holiday marked for Class ' + c + '.' : 'Holiday removed.'); render();
  } catch (e) { toast(e.message); }
}

// Month register: students × days. Tap a box: P → A → L. Tap a date: whole day present / holiday. Save sends every changed day.
function vRegister(c) {
  const t = todayISO(), ym = P.ym || ymOf(t), list = studentsOf(c), n = daysIn(ym);
  if (!MONTHS[c + '|' + ym]) { ensureMonth(c, ym).then(render).catch(e => { MONTHS[c + '|' + ym] = true; toast(e.message); render(); }); return `${monthNav(c, ym)}<div class="load"><i></i>Loading ${ymName(ym)}…</div>`; }
  if (!DRAFT.reg || DRAFT.reg.key !== c + ym) DRAFT.reg = { key: c + ym, days: {} };
  const R = DRAFT.reg.days;
  const dates = []; for (let i = 1; i <= n; i++) dates.push(ym + '-' + pad(i));
  const colState = d => { if (d > t) return 'F'; if (dayIdx(d) < 0) return 'S'; if (holidayOn(d) || clsHolOn(d, c)) return 'H'; return R[d] ? 'D' : attOf(d, c) ? 'M' : 'U'; };
  const val = (d, id) => { const cs = colState(d); if (cs === 'F') return ''; if (cs === 'S') return '–'; if (cs === 'H') return 'H'; const st = R[d] || (attOf(d, c) ? stOf(attOf(d, c), list) : null); return st ? st[id] || 'P' : '·'; };
  const work = dates.filter(d => ['D', 'M'].includes(colState(d)));
  const unmarked = dates.filter(d => colState(d) === 'U');
  const changed = Object.keys(R).length;
  return `${monthNav(c, ym)}
    ${unmarked.length ? `<div class="ban warn"><div><b>${unmarked.length} day(s) not marked</b> in ${ymName(ym)}.<br><button class="btn s go" style="margin-top:8px" onclick="regFillUnmarked(${jsq(c)})">Fill them all as Present</button> <span class="sm">then tap the absent children and Save.</span></div></div>` : ''}
    <div class="reg"><table><thead><tr><th class="nm">Student</th>${dates.map(d => { const cs = colState(d); return `<th class="${cs === 'S' ? 'sun' : cs === 'H' ? 'hol' : cs === 'U' ? 'un' : ''}">${cs === 'F' || cs === 'S' ? `${+d.slice(8)}<br>${'SMTWTFS'[new Date(d + 'T00:00:00').getDay()]}` : `<button onclick="regDay(${jsq(c)},'${d}')">${+d.slice(8)}<br>${'SMTWTFS'[new Date(d + 'T00:00:00').getDay()]}</button>`}</th>`; }).join('')}<th>P%</th></tr></thead>
      <tbody>${list.map(s => { let p = 0, w = 0; const cells = dates.map(d => { const v = val(d, s.id), cs = colState(d); if (['D', 'M'].includes(cs)) { w++; if (v === 'P') p++; }
          const cls = v === 'P' ? 'cP' : v === 'A' ? 'cA' : v === 'L' ? 'cL' : v === 'H' ? 'cH' : v === '–' ? 'cS' : v === '·' ? 'cU' : 'cF';
          return `<td class="${cls} ${R[d] ? 'chg' : ''}">${['D', 'M', 'U'].includes(cs) ? `<button onclick="regTap(${jsq(c)},'${d}','${esc(s.id)}')">${v}</button>` : v}</td>`; }).join('');
        return `<tr><td class="nm">${esc(s.name)}</td>${cells}<td class="pc">${w ? Math.round(p / w * 100) + '%' : '—'}</td></tr>`; }).join('')}</tbody></table></div>
    <div class="legend"><span><i style="background:var(--okbg)"></i>P present</span><span><i style="background:var(--badbg)"></i>A absent</span><span><i style="background:var(--lvbg)"></i>L leave</span><span><i style="background:var(--holbg)"></i>H holiday</span><span><i style="border:1px solid #E0A100"></i>· not marked</span></div>
    <p class="mut" style="margin:0">${work.length} school day(s) marked. Tap a box to change P → A → L. Tap a date for the whole day (all present / holiday).</p>
    ${changed ? `<div class="sticky"><button class="btn go full" onclick="regSave(${jsq(c)})">Save ${changed} changed day(s)</button><button class="btn w full" style="margin-top:6px" onclick="DRAFT.reg=null;render()">Cancel changes</button></div>` : ''}`;
}
function monthNav(c, ym) {
  const t = ymOf(todayISO()), st = ymOf(sessionStart());
  return `<div class="row"><button class="btn s w" ${ym <= st ? 'disabled' : ''} onclick="regMonth(${jsq(c)},'${ymAdd(ym, -1)}')">${ic('back')}</button><b>${ymName(ym)}</b><button class="btn s w" ${ym >= t ? 'disabled' : ''} onclick="regMonth(${jsq(c)},'${ymAdd(ym, 1)}')">${ic('next')}</button></div>`;
}
function regMonth(c, ym) { if (DRAFT.reg && Object.keys(DRAFT.reg.days).length && !confirm('You have changes that are not saved. Leave them?')) return; DRAFT.reg = null; go('att', { c, m: 'reg', ym }); }
function regEnsure(c, d) { const R = DRAFT.reg.days; if (!R[d]) R[d] = stOf(attOf(d, c), studentsOf(c)); return R[d]; }
function regTap(c, d, id) { const st = regEnsure(c, d); st[id] = st[id] === 'P' ? 'A' : st[id] === 'A' ? 'L' : 'P'; render(); }
function regFillUnmarked(c) { const ym = P.ym || ymOf(todayISO()), t = todayISO(); for (let i = 1; i <= daysIn(ym); i++) { const d = ym + '-' + pad(i); if (d <= t && isWorkDay(d, c) && !attOf(d, c)) regEnsure(c, d); } toast('Filled as Present. Now tap the absent children, then Save.'); render(); }
function regDay(c, d) {
  const marked = !!attOf(d, c) || !!(DRAFT.reg.days[d]);
  SHEET = () => `<h2>${fmtLong(d)} · Class ${esc(c)}</h2>
    <button class="btn full l" onclick="regEnsure(${jsq(c)},'${d}');Object.keys(DRAFT.reg.days['${d}']).forEach(k=>DRAFT.reg.days['${d}'][k]='P');closeSheet()">Everyone present this day</button>
    <button class="btn full w" onclick="DRAFT.att=null;SHEET=null;go('att',{c:${jsq(c)},date:'${d}'})">Open this day (with names)</button>
    ${clsHolOn(d, c) ? `<button class="btn full w" onclick="setClsHol(${jsq(c)},'${d}',false)">Remove class holiday</button>` : `<button class="btn full w" onclick="SHEET=null;askHoliday(${jsq(c)},'${d}')">Mark as holiday for this class</button>`}
    ${DRAFT.reg.days[d] ? `<button class="btn full bad" onclick="delete DRAFT.reg.days['${d}'];closeSheet()">Undo my changes for this day</button>` : ''}
    <p class="mut" style="margin:0">${marked ? 'Attendance is saved for this day.' : 'Not marked yet.'}</p>`;
  render();
}
function regSave(c) {
  const R = DRAFT.reg.days, list = studentsOf(c), days = Object.keys(R).sort();
  days.forEach(d => { const rec = attRecord(R[d]); setRec('studAtt', d + '|' + c, rec); QUEUE = QUEUE.filter(x => !(x.k === 'studAtt' && x.r === d + '|' + c)); QUEUE.push({ k: 'studAtt', r: d + '|' + c, d: rec }); });
  LOGQ.push({ k: 'Student Attendance', x: `Class ${c}: attendance of ${days.length} day(s) saved together from the register (${days[0]} to ${days[days.length - 1]}) — Teacher App` });
  saveQueue(); flush(); DRAFT.reg = null; toast(days.length + ' day(s) saved.'); render();
}
// Every school day from the start of the session that still has no attendance — select and save together.
function vPending(c) {
  const t = todayISO(), base = P.from || addDays(t, -60), from = base < sessionStart() ? sessionStart() : base;
  const months = []; for (let ym = ymOf(from); ym <= ymOf(t); ym = ymAdd(ym, 1)) months.push(ym);
  const need = months.filter(ym => !MONTHS[c + '|' + ym]);
  if (need.length) { Promise.all(need.map(ym => ensureMonth(c, ym))).then(render).catch(e => { need.forEach(ym => MONTHS[c + '|' + ym] = true); toast(e.message); render(); }); return '<div class="load"><i></i>Checking every school day…</div>'; }
  const days = pendingDays(c, from);
  if (!DRAFT.pend || DRAFT.pend.c !== c) DRAFT.pend = { c, sel: {} };
  const selN = days.filter(d => DRAFT.pend.sel[d]).length;
  return `<div class="ban inf"><div>School days from <b>${fmtDate(from)}</b> with no attendance for Class ${esc(c)}. Sundays and holidays are left out.
      ${from > sessionStart() ? `<br><a href="#" style="text-decoration:underline" onclick="go('att',{c:${jsq(c)},m:'pending',from:'${sessionStart()}'});return false">Check from the start of the session</a>` : ''}</div></div>
    <div class="card">${days.length ? `<label class="chk" style="padding-bottom:8px"><input type="checkbox" ${selN === days.length ? 'checked' : ''} onchange="${days.map(d => `DRAFT.pend.sel['${d}']=this.checked`).join(';')};render()"> <b>Select all ${days.length} day(s)</b></label>
      ${days.slice().reverse().map(d => `<div class="li"><input type="checkbox" style="width:19px;height:19px;accent-color:var(--a)" ${DRAFT.pend.sel[d] ? 'checked' : ''} onchange="DRAFT.pend.sel['${d}']=this.checked;render()"><div class="t"><b>${fmtLong(d)}</b></div><button class="btn s l" onclick="go('att',{c:${jsq(c)},date:'${d}'})">Open</button></div>`).join('')}`
      : '<div class="empty">Every school day is marked. Well done!</div>'}</div>
    ${selN ? `<div class="sticky"><button class="btn go full" onclick="pendAll(${jsq(c)},'P')">Mark ${selN} day(s) — everyone present</button>
      <button class="btn w full" style="margin-top:6px" onclick="pendToRegister(${jsq(c)})">Open them in the register to mark absentees</button>
      <button class="btn w full" style="margin-top:6px" onclick="pendHoliday(${jsq(c)})">Mark ${selN} day(s) as holiday</button></div>` : ''}`;
}
function pendAll(c) {
  const days = Object.keys(DRAFT.pend.sel).filter(d => DRAFT.pend.sel[d]).sort(); if (!days.length) return;
  if (!confirm(`Save ${days.length} day(s) with every child of Class ${c} present?`)) return;
  const list = studentsOf(c);
  days.forEach(d => { const st = {}; list.forEach(s => st[s.id] = 'P'); const rec = attRecord(st); setRec('studAtt', d + '|' + c, rec); QUEUE = QUEUE.filter(x => !(x.k === 'studAtt' && x.r === d + '|' + c)); QUEUE.push({ k: 'studAtt', r: d + '|' + c, d: rec }); });
  LOGQ.push({ k: 'Student Attendance', x: `Class ${c}: ${days.length} back day(s) marked all present together (${days[0]} to ${days[days.length - 1]}) — Teacher App` });
  saveQueue(); flush(); DRAFT.pend = null; toast(days.length + ' day(s) saved.'); render();
}
function pendToRegister(c) { const days = Object.keys(DRAFT.pend.sel).filter(d => DRAFT.pend.sel[d]).sort(); const ym = ymOf(days[0]); DRAFT.reg = { key: c + ym, days: {} }; days.filter(d => ymOf(d) === ym).forEach(d => regEnsure(c, d)); P = { c, m: 'reg', ym }; DRAFT.pend = null; toast('Opened ' + ymName(ym) + '. Tap the absent children, then Save.'); render(); }
function pendHoliday(c) {
  const days = Object.keys(DRAFT.pend.sel).filter(d => DRAFT.pend.sel[d]).sort();
  SHEET = () => `<h2>${days.length} day(s) as holiday · Class ${esc(c)}</h2><div class="fld">Reason<input id="hT" class="inp" maxlength="120" placeholder="e.g. Local holiday"></div>
    <button class="btn go full" onclick="pendHolSave(${jsq(c)},$('hT').value)">Mark holiday</button>`;
  render();
}
async function pendHolSave(c, title) {
  const days = Object.keys(DRAFT.pend.sel).filter(d => DRAFT.pend.sel[d]).sort(); let n = 0;
  for (const d of days) { try { await api('teacher_api.php?a=holiday', { cls: c, date: d, on: true, title }); setRec('clsHol', d + '|' + c, { cls: c, date: d, title: title || 'Holiday', by: myName(), at: Date.now() }); n++; } catch (e) { toast(e.message); break; } }
  DRAFT.pend = null; SHEET = null; toast(n + ' day(s) marked as holiday.'); render();
}

// ---------------------------------------------------------------- DIARY (homework + class work)
function vDiary() {
  const t = todayISO(), date = P.date || t, bells = myBells(date), signed = (S.signed[date] || {})[me()];
  if (date < (loadedFrom || '') && !DAYS_LOADED[date]) { ensureDate(date).then(render); return '<div class="load"><i></i></div>'; }
  const ct = myCT().filter(c => isWorkDay(date, c));
  if (P.add && P.add.date === date && !bells.some(b => b.bell === P.add.bell)) bells.unshift(P.add);
  return `<div class="row"><button class="btn s w" onclick="go('diary',{date:'${addDays(date, -1)}'})">${ic('back')}</button><input type="date" class="inp" style="flex:1" value="${date}" max="${addDays(t, 7)}" onchange="go('diary',{date:this.value})"><button class="btn s w" onclick="go('diary',{date:'${addDays(date, 1)}'})">${ic('next')}</button></div>
    ${signed ? '<div class="ban inf">The Principal has signed this diary. Ask the Principal before changing it.</div>' : ''}
    ${bells.length ? bells.map(b => diaryCard(date, b, signed)).join('') : `<div class="card empty">${dayIdx(date) < 0 ? 'Sunday — no classes.' : holidayOn(date) ? 'Holiday: ' + esc(holidayOn(date).title) : 'No bells for you on this day.'}</div>`}
    ${dayIdx(date) >= 0 && !holidayOn(date) && !signed ? `<button class="btn w full" onclick="addPeriod('${date}')">+ Add a period (substitution / not in timetable)</button>` : ''}
    ${ct.map(c => hwShare(date, c)).join('')}`;
}
function diaryCard(date, b, signed) {
  const e = diaryEntry(date, b.bell) || {}, id = 'b' + b.bell, nx = nextChapter(b.cls, b.subj), open = P.bell === b.bell || !e.at;
  if (!open) return `<div class="card" onclick="go('diary',{date:'${date}',bell:${b.bell}})"><div class="li" style="padding:0"><div class="bell ${b.arr ? 'arr' : ''}"><b>${BELLS[b.bell].n.replace(/\D/g, '')}</b>${t12(BELLS[b.bell].s).replace(' ', '')}</div>
    <div class="t"><b>Class ${esc(b.cls)} · ${esc(b.subj)}</b><span>Topic: ${esc(e.topic)}${e.hw ? ' · HW: ' + esc(e.hw) : ''}</span></div><span class="pill ok">Saved</span></div></div>`;
  return `<div class="card"><div class="li" style="padding:0 0 10px"><div class="bell ${b.arr ? 'arr' : ''}"><b>${BELLS[b.bell].n.replace(/\D/g, '')}</b>${t12(BELLS[b.bell].s).replace(' ', '')}</div>
      <div class="t"><b>Class ${esc(b.cls)} · ${esc(b.subj)}</b><span>${b.arr ? 'Arrangement' + (b.for ? ' for ' + esc(T(b.for).name) : '') : b.extra ? 'Added period' : BELLS[b.bell].n + ' bell'}</span></div>${e.at ? `<span class="pill ok">Saved ${fmtTime(e.at).split(', ').pop()}</span>` : ''}</div>
    <div style="display:flex;flex-direction:column;gap:9px">
      <div class="fld">Topic taught *<input id="${id}-t" class="inp" maxlength="200" value="${esc(e.topic || '')}" placeholder="${nx ? 'Next chapter: ' + esc(nx) : 'What did you teach?'}">
        ${nx && !e.topic ? `<button class="btn s l" style="align-self:flex-start" onclick="$('${id}-t').value=${jsq(nx)}">Use: ${esc(nx.slice(0, 40))}</button>` : ''}</div>
      <div class="fld">Class work<textarea id="${id}-cw" class="inp" rows="2" maxlength="500" placeholder="e.g. Q1–Q5 done in class copy">${esc(e.classwork || '')}</textarea></div>
      <div class="fld">Homework<textarea id="${id}-h" class="inp" rows="2" maxlength="500" placeholder="e.g. Ex 4.1 Q6–Q10">${esc(e.hw || '')}</textarea></div>
      <div class="two"><div class="fld">Minutes (whole class ≤ 45)<input id="${id}-min" class="inp" type="number" inputmode="numeric" min="1" max="45" value="${e.hwMinutes || ''}" placeholder="15"></div>
        <div class="fld">Homework for<input id="${id}-date" class="inp" type="date" value="${esc(e.hwDate || date)}"></div></div>
      <div class="fld">Books / copies to bring<input id="${id}-books" class="inp" maxlength="300" value="${esc(e.books || '')}"></div>
      <div class="fld">Revision<input id="${id}-rev" class="inp" maxlength="400" value="${esc(e.revision || '')}"></div>
      ${nx && !b.arr ? `<label class="chk"><input id="${id}-c" type="checkbox"> Chapter “${esc(nx)}” finished today (ticks it in the syllabus)</label>` : ''}
      <label class="chk"><input id="${id}-v" type="checkbox" ${e.at ? 'checked' : ''}> I really taught this and checked the homework.</label>
      <button class="btn go full" ${signed ? 'disabled' : ''} onclick="diarySave('${date}',${b.bell})">Save</button>
    </div></div>`;
}
function diarySave(date, bell) {
  const b = myBells(date).find(x => x.bell === bell) || (P.add && P.add.bell === bell ? P.add : null); if (!b) return;
  const id = 'b' + bell, v = k => ($(id + '-' + k) || {}).value || '', topic = v('t').trim(), hw = v('h').trim(), min = Number(v('min')), hwDate = v('date');
  if (!topic) return toast('Write the topic taught.');
  if (!$(id + '-v').checked) return toast('Tick “I really taught this” first.');
  if (hw && (!Number.isInteger(min) || min < 1 || min > 45)) return toast('Write how many minutes the homework takes (1 to 45).');
  const d = { cls: b.cls, subj: b.subj, topic: topic.slice(0, 200), hw: hw.slice(0, 500), classwork: v('cw').trim().slice(0, 500), books: v('books').trim().slice(0, 300), revision: v('rev').trim().slice(0, 400),
    hwMinutes: hw ? min : null, hwDate: hwDate || date, arr: !!b.arr, at: Date.now(), ...(b.extra ? { extra: true } : {}) };
  put('diary', date + '|' + me() + '|' + bell, d, 'Diary', `${BELLS[bell].n} bell, Class ${b.cls} ${b.subj}: "${topic}"` + (hw ? ` • HW: ${hw}` : '') + ' — Teacher App');
  const cb = $(id + '-c');
  if (cb && cb.checked) { const key = sylKey(b.cls, b.subj), y = JSON.parse(JSON.stringify(S.syl[key] || { chapters: [] })), ch = y.chapters.find(x => !x.done && x.n === nextChapter(b.cls, b.subj));
    if (ch) { ch.done = date; put('syl', key, y, 'Syllabus', `Class ${b.cls} ${b.subj}: chapter "${ch.n}" completed (from diary)`); } }
  P = { date }; toast('Diary saved.'); render();
}
function addPeriod(date) {
  const used = new Set(myBells(date).map(b => b.bell)), list = allocList();
  if (!list.length) return toast('No subjects are given to you yet.');
  SHEET = () => `<h2>Add a period · ${fmtDate(date)}</h2><p class="mut" style="margin:0">For a substitution or a period that is not in your timetable.</p>
    <div class="fld">Class & subject${sel('apK', list.map(a => [a.c + '|' + a.s, 'Class ' + a.c + ' — ' + a.s]), '', '')}</div>
    <div class="fld">Bell${sel('apB', BELLS.map((b, i) => [String(i), b.n + ' (' + t12(b.s) + ')' + (used.has(i) ? ' — already used' : '')]).filter(([i]) => !used.has(+i)), '', '')}</div>
    <label class="chk"><input id="apA" type="checkbox"> This is an arrangement (I took someone else's class)</label>
    <button class="btn go full" onclick="addPeriodGo('${date}')">Continue</button>`;
  render();
}
function addPeriodGo(date) {
  const [c, s] = $('apK').value.split('|'), bell = +$('apB').value; if (isNaN(bell)) return toast('Choose the bell.');
  go('diary', { date, bell, add: { date, bell, cls: c, subj: s, arr: $('apA').checked, extra: true } });
}
function hwShare(date, c) {
  const hw = []; Object.values(S.diary[date] || {}).forEach(byB => Object.values(byB).forEach(e => { if (e && e.cls === c && (e.hw || e.classwork)) hw.push(e); }));
  if (!hw.length) return '';
  const text = `*Class ${c} — ${fmtLong(date)}*\n` + hw.map(e => `• *${e.subj}*: ${e.hw ? 'HW: ' + e.hw + (e.hwMinutes ? ` (${e.hwMinutes} min)` : '') : ''}${e.classwork ? (e.hw ? ' | ' : '') + 'CW: ' + e.classwork : ''}${e.books ? ' | Bring: ' + e.books : ''}`).join('\n') + `\n— ${S.settings.school || ''}`;
  return `<div class="card"><h3>Class ${esc(c)} homework today <small>${hw.length} subject(s)</small></h3>${hw.map(e => `<div class="li"><div class="t"><b>${esc(e.subj)}</b><span>${e.hw ? 'HW: ' + esc(e.hw) : ''}${e.classwork ? (e.hw ? ' · ' : '') + 'CW: ' + esc(e.classwork) : ''}</span></div></div>`).join('')}
    <a class="btn wa full" target="_blank" rel="noopener" href="${wa('', text)}">${ic('wa')} Send to the class parents' group</a></div>`;
}

// ---------------------------------------------------------------- PLAN (weekly lesson plan + syllabus)
function weekStart(iso) { const d = new Date(iso + 'T00:00:00'); return addDays(iso, -((d.getDay() + 6) % 7)); }
function vPlan() {
  const m = P.m || 'lp', list = allocList();
  if (!list.length) return '<div class="card empty">No subjects are given to you yet. The Principal sets them in the timetable.</div>';
  return seg([['lp', 'Lesson plan'], ['syl', 'Syllabus']], m, `(k=>go('plan',{m:k,key:${jsq(P.key || '')}}))`) + (m === 'syl' ? vSyl(list) : vLp(list));
}
function keyPick(list, key, onch) { return `<div class="fld">Class & subject${sel('kp', list.map(a => [sylKey(a.c, a.s), 'Class ' + a.c + ' — ' + a.s]), key, onch)}</div>`; }
function vLp(list) {
  const thisW = weekStart(todayISO()), w = P.w || thisW, key = list.some(a => sylKey(a.c, a.s) === P.key) ? P.key : sylKey(list[0].c, list[0].s), [c, s] = key.split('|');
  const k = [w, me(), c, s].join('|'), p = S.lplan[k], dr = DRAFT.lp && DRAFT.lp.k === k ? DRAFT.lp : null, nx = nextChapter(c, s);
  const weeks = [addDays(thisW, -7), thisW, addDays(thisW, 7), addDays(thisW, 14)];
  const mine = Object.entries(S.lplan).filter(([kk, v]) => v.tid === me() && v.week === w);
  const daily = p && Object.prototype.hasOwnProperty.call(p, 'days');
  return `<div class="fld">Week${sel('lpW', weeks.map(x => [x, 'Week of ' + fmtDate(x)]), w, `go('plan',{m:'lp',w:this.value,key:${jsq(key)}})`)}</div>
    ${keyPick(list, key, `DRAFT.lp=null;go('plan',{m:'lp',w:'${w}',key:this.value})`)}
    <div class="card">${daily ? '<div class="ban inf">This week has a daily teaching plan. Open the web ERP → Daily Teaching to change it.</div>' : p && p.status === 'Approved' ? '<div class="ban ok">Approved by the Principal — locked.</div>' : `
      <div class="btns" style="margin-bottom:8px"><button class="btn s l" onclick="lpFill('${w}',${jsq(key)})">Fill from syllabus</button>
        <label class="btn s l filebtn">${ic('cam')} From a photo<input type="file" accept="image/*" capture="environment" multiple onchange="lpPhoto(this.files,${jsq(key)},'${k}')"></label></div>
      ${DRAFT.busy ? `<div class="ban inf"><span class="load" style="padding:0;flex-direction:row;gap:8px"><i style="width:18px;height:18px"></i></span>${esc(DRAFT.busy)}</div>` : ''}
      <div class="fld">Topics — one line = one period${nx ? ` · next chapter: <b>${esc(nx)}</b>` : ''}<textarea id="lpT" class="inp" rows="7" oninput="DRAFT.lp={k:'${k}',topics:this.value,goal:$('lpG').value}">${esc(dr ? dr.topics : p ? p.topics : '')}</textarea></div>
      <div class="fld">Aim / activity / teaching aid / homework<input id="lpG" class="inp" maxlength="400" value="${esc(dr ? dr.goal : p ? p.goal || '' : '')}" oninput="DRAFT.lp={k:'${k}',topics:$('lpT').value,goal:this.value}"></div>
      <button class="btn go full" style="margin-top:10px" onclick="lpSubmit('${w}',${jsq(key)})">${p ? 'Update and send again' : 'Send to Principal'}</button>`}
      ${p ? `<p class="mut" style="margin:8px 0 0">Status: <span class="pill ${p.status === 'Approved' ? 'ok' : p.status === 'Revise' ? 'bad' : 'warn'}">${esc(p.status)}</span>${p.feedback ? ' · Principal: ' + esc(p.feedback) : ''}</p>` : ''}</div>
    ${mine.length ? `<div class="card"><h3>My plans this week</h3>${mine.map(([kk, v]) => `<div class="li" onclick="DRAFT.lp=null;go('plan',{m:'lp',w:'${w}',key:${jsq(sylKey(v.cls, v.subj))}})"><div class="t"><b>Class ${esc(v.cls)} · ${esc(v.subj)}</b><span>${String(v.topics || '').split('\n').filter(Boolean).length} topic(s)${(v.days || []).length ? ' · ' + v.days.length + ' day(s)' : ''}</span></div><span class="pill ${v.status === 'Approved' ? 'ok' : v.status === 'Revise' ? 'bad' : 'warn'}">${esc(v.status)}</span></div>`).join('')}</div>` : ''}`;
}
function lpFill(w, key) {
  const [c, s] = key.split('|'), y = S.syl[key], a = S.alloc.find(x => x.c === c && x.s === s && x.t === me()) || S.alloc.find(x => x.c === c && x.s === s);
  if (!y || !(y.chapters || []).length) return toast('Add the chapters first: Plan → Syllabus (a photo of the contents page works).');
  const n = Math.max(1, Math.min(12, (a && +a.n) || 5));
  const earlier = new Set(Object.values(S.lplan).filter(p => p.tid === me() && p.cls === c && p.subj === s && p.week < w).flatMap(p => String(p.topics || '').split('\n').map(x => x.trim().toLowerCase())));
  const open = y.chapters.filter(ch => !ch.done).sort((x, z) => String(x.plan || '9999').localeCompare(String(z.plan || '9999'))), lines = [];
  for (const ch of open) { const parts = (ch.topics || []).length ? ch.topics.map(t => `${ch.n} — ${t}`) : [`${ch.n} — introduction`, `${ch.n} — explanation`, `${ch.n} — examples and practice`, `${ch.n} — exercise / question-answer`, `${ch.n} — revision and test`];
    for (const l of parts) { if (!earlier.has(l.toLowerCase())) lines.push(l); if (lines.length >= n) break; } if (lines.length >= n) break; }
  if (!lines.length) return toast('Nothing left to plan — all chapters are done.');
  DRAFT.lp = { k: [w, me(), c, s].join('|'), topics: lines.join('\n'), goal: ($('lpG') || {}).value || '' }; toast(lines.length + ' period(s) filled. Change anything, then send.'); render();
}
function lpSubmit(w, key) {
  const [c, s] = key.split('|'), k = [w, me(), c, s].join('|'), old = S.lplan[k];
  if (old && (old.status === 'Approved' || Object.prototype.hasOwnProperty.call(old, 'days'))) return toast('This plan is locked.');
  const topics = $('lpT').value.split('\n').map(x => x.trim()).filter(Boolean).join('\n').slice(0, 3000), goal = $('lpG').value.trim().slice(0, 400);
  if (!topics) return toast('Write at least one topic.');
  put('lplan', k, { week: w, tid: me(), cls: c, subj: s, topics, goal, status: 'Submitted', feedback: '', at: Date.now() }, 'Lesson Plan', `Submitted: Class ${c} ${s}, week of ${w} (${topics.split('\n').length} topics) — Teacher App`);
  DRAFT.lp = null; toast('Sent to the Principal.'); render();
}
function vSyl(list) {
  const key = list.some(a => sylKey(a.c, a.s) === P.key) ? P.key : sylKey(list[0].c, list[0].s), [c, s] = key.split('|'), y = S.syl[key] || { chapters: [] };
  const done = y.chapters.filter(x => x.done).length, sc = DRAFT.sc && DRAFT.sc.key === key ? DRAFT.sc : null;
  return `${keyPick(list, key, "DRAFT.sc=null;go('plan',{m:'syl',key:this.value})")}
    <div class="card"><h3>Chapters <small>${done}/${y.chapters.length} done</small></h3>
      ${y.chapters.length ? y.chapters.map((ch, i) => `<label class="li chk"><input type="checkbox" ${ch.done ? 'checked' : ''} onchange="sylTick(${jsq(key)},${i},this.checked)"><div class="t"><b>${i + 1}. ${esc(ch.n)}</b><span>${ch.done ? 'Finished ' + fmtDate(ch.done) : ch.plan ? 'Planned for ' + ymName(ch.plan) : ''}${(ch.topics || []).length ? ' · ' + ch.topics.length + ' topics' : ''}</span></div></label>`).join('')
        : '<div class="empty">No chapters yet. Take a photo of the book’s contents page.</div>'}
      <div class="row" style="margin-top:10px;gap:6px"><input id="sylN" class="inp" maxlength="120" placeholder="Add a chapter by name"><button class="btn s" onclick="sylAdd(${jsq(key)})">Add</button></div></div>
    <div class="card"><h3>From the contents page</h3><p class="mut" style="margin:0 0 8px">Photo of the book’s Contents / विषय-सूची page — the chapters are read for you. Check them, then save.</p>
      <label class="btn l full filebtn">${ic('cam')} Take / choose photo<input type="file" accept="image/*" capture="environment" multiple onchange="sylPhoto(this.files,${jsq(key)})"></label>
      ${DRAFT.busy && DRAFT.busyK === key ? `<p class="mut">${esc(DRAFT.busy)}</p>` : ''}
      ${sc ? `<div class="fld" style="margin-top:10px">Chapters found (${sc.via === 'ai' ? 'read by AI' : 'read by the phone — check carefully'}) — one per line<textarea id="scL" class="inp" rows="8">${esc(sc.list.join('\n'))}</textarea></div>
        <label class="chk" style="margin-top:6px"><input id="scR" type="checkbox" ${y.chapters.length ? '' : 'checked'}> Replace my chapter list (ticks of chapters with the same name are kept)</label>
        <button class="btn go full" style="margin-top:8px" onclick="sylSave(${jsq(key)})">Save chapters</button>` : ''}</div>`;
}
function sylCopy(key) { return JSON.parse(JSON.stringify(S.syl[key] || { chapters: [], targetCount: '', targetDate: '' })); }
function sylTick(key, i, on) { const y = sylCopy(key); y.chapters[i].done = on ? todayISO() : null; put('syl', key, y, 'Syllabus', `${key.replace('|', ' ')}: "${y.chapters[i].n}" ${on ? 'completed' : 'un-ticked'} — Teacher App`); render(); }
function sylAdd(key) { const n = $('sylN').value.trim().slice(0, 120); if (n.length < 2) return toast('Write the chapter name.'); const y = sylCopy(key); y.chapters.push({ n, done: null, topics: [] }); put('syl', key, y, 'Syllabus', `${key.replace('|', ' ')}: chapter "${n}" added`); render(); }
function sylSave(key) {
  const list = $('scL').value.split('\n').map(x => x.trim().slice(0, 120)).filter(x => x.length >= 2); if (!list.length) return toast('No chapter to save.');
  const y = sylCopy(key), topics = Object.fromEntries((DRAFT.sc.chapters || []).map(ch => [String(ch.name).toLowerCase(), ch.topics || []]));
  if ($('scR').checked || !y.chapters.length) { const old = Object.fromEntries(y.chapters.map(ch => [ch.n.toLowerCase(), ch])); y.chapters = list.map(n => { const o = old[n.toLowerCase()]; return { n, done: o ? o.done : null, plan: o ? o.plan || null : null, topics: topics[n.toLowerCase()] || (o ? o.topics || [] : []) }; }); }
  else { const have = new Set(y.chapters.map(ch => ch.n.toLowerCase())); list.filter(n => !have.has(n.toLowerCase())).forEach(n => y.chapters.push({ n, done: null, topics: topics[n.toLowerCase()] || [] })); }
  put('syl', key, y, 'Syllabus', `${key.replace('|', ' ')}: ${list.length} chapter(s) saved from a photo of the contents page (checked by ${myName()})`);
  DRAFT.sc = null; toast('Chapters saved.'); render();
}

// ---------------------------------------------------------------- photos (AI first; the phone reads it if the school AI is off)
function shrink(file) {
  return new Promise((ok, bad) => { const img = new Image(), url = URL.createObjectURL(file);
    img.onload = () => { const m = 1600, r = Math.min(1, m / Math.max(img.width, img.height)), cv = document.createElement('canvas'); cv.width = Math.round(img.width * r); cv.height = Math.round(img.height * r);
      cv.getContext('2d').drawImage(img, 0, 0, cv.width, cv.height); URL.revokeObjectURL(url); let q = 0.82, out = cv.toDataURL('image/jpeg', q); while (out.length > 1900000 && q > 0.4) { q -= 0.12; out = cv.toDataURL('image/jpeg', q); } ok(out); };
    img.onerror = () => { URL.revokeObjectURL(url); bad(new Error('This photo could not be opened. Please take it again.')); }; img.src = url; });
}
let TESS = null;
function tessLoad() { if (window.Tesseract) return Promise.resolve(); return TESS = TESS || new Promise((ok, bad) => { const sc = document.createElement('script'); sc.src = 'https://cdn.jsdelivr.net/npm/tesseract.js@5.1.1/dist/tesseract.min.js'; sc.onload = ok; sc.onerror = () => { TESS = null; bad(new Error('The phone reader could not load. Check the internet, or type it.')); }; document.head.appendChild(sc); }); }
async function readPhotos(mode, c, s, files, busyK) {
  files = [...(files || [])].slice(0, 4); if (!files.length) return null;
  const busy = m => { DRAFT.busy = m; DRAFT.busyK = busyK; render(); };
  try {
    busy('Preparing the photo…'); const images = []; for (const f of files) images.push(await shrink(f));
    let res = null;
    if (AI !== false) { busy('Reading the photo with AI… (10–40 seconds)'); try { res = await api('scan_api.php?a=read', { mode, cls: c, subj: s, images }); res.via = 'ai'; } catch (e) { if (!e.noAi) throw e; AI = false; } }
    if (!res) { busy('Your phone is reading the photo… (the first time takes about a minute)'); await tessLoad(); let text = '';
      for (let i = 0; i < images.length; i++) { busy(`Your phone is reading photo ${i + 1} of ${images.length}…`); const r = await window.Tesseract.recognize(images[i], 'eng+hin'); text += '\n' + ((r && r.data && r.data.text) || ''); }
      res = { via: 'phone', text }; }
    return res;
  } catch (e) { toast(e.message); return null; } finally { DRAFT.busy = null; render(); }
}
function lines(text) { return [...new Set(String(text || '').split('\n').map(l => l.replace(/\s+/g, ' ').replace(/^(?:(?:chapter|ch\.?|lesson|unit|पाठ|अध्याय)\s*)?[(\[]?(?:\d{1,3}|[ivxlc]{1,6})[)\].:\-–—]*\s+/i, '').replace(/[\s.·…_\-–—]*\d{1,4}\s*$/, '').trim()).filter(l => l.length >= 3 && /[A-Za-zऀ-ॿ]{2}/.test(l) && !/^(contents?|index|page|विषय[ -]?सूची|अनुक्रमणिका|पृष्ठ)\b/i.test(l)))]; }
async function sylPhoto(files, key) { const [c, s] = key.split('|'); const res = await readPhotos('chapters', c, s, files, key); if (!res) return;
  const list = res.via === 'ai' ? res.chapters.map(x => x.name) : lines(res.text).slice(0, 60); if (!list.length) return toast('Nothing readable was found. Try a clearer, straighter photo in good light.');
  DRAFT.sc = { key, via: res.via, list, chapters: res.chapters || [] }; render(); }
async function lpPhoto(files, key, k) { const [c, s] = key.split('|'); const res = await readPhotos('lplan', c, s, files, k); if (!res) return;
  const topics = res.via === 'ai' ? res.topics : lines(res.text).slice(0, 12); if (!topics.length) return toast('Nothing readable was found. Try a clearer photo.');
  DRAFT.lp = { k, topics: topics.join('\n'), goal: res.via === 'ai' ? [res.goal, res.homework ? 'Homework: ' + res.homework : ''].filter(Boolean).join(' • ') : '' }; toast('Read from the photo. Check it, then send.'); render(); }

// ---------------------------------------------------------------- NOTES (chapter-wise, from photos of the book)
const NOTES = {};
function vNotes() {
  const list = allocList(); if (!list.length) return '<div class="card empty">No subjects are given to you yet.</div>';
  const key = list.some(a => sylKey(a.c, a.s) === P.key) ? P.key : sylKey(list[0].c, list[0].s), [c, s] = key.split('|');
  if (P.edit) return noteEditor(c, s);
  if (!NOTES[key]) { api('teacher_api.php?a=notes&cls=' + encodeURIComponent(c) + '&subj=' + encodeURIComponent(s)).then(r => { NOTES[key] = r.notes; render(); }).catch(e => { NOTES[key] = []; toast(e.message); }); return keyPick(list, key, "go('notes',{key:this.value})") + '<div class="load"><i></i></div>'; }
  const notes = NOTES[key], open = notes.find(n => n.id === P.id);
  if (open) return noteView(open, key);
  const chapters = ((S.syl[key] || {}).chapters || []).map(x => x.n), have = new Set(notes.map(n => n.chapter.toLowerCase()));
  return `${keyPick(list, key, "go('notes',{key:this.value})")}
    <button class="btn go full" onclick="go('notes',{key:${jsq(key)},edit:'new'})">${ic('cam')} New notes from book photos</button>
    <div class="card"><h3>Notes <small>${notes.length} chapter(s)</small></h3>${notes.length ? notes.map(n => `<div class="li" onclick="go('notes',{key:${jsq(key)},id:'${n.id}'})"><div class="t"><b>${esc(n.chapter)}</b><span>${(n.points || []).length} points · ${(n.qa || []).length} Q&A · ${fmtTime(n.at)}</span></div><span class="pill ${n.share ? 'ok' : 'warn'}">${n.share ? 'Shared' : 'Draft'}</span></div>`).join('')
      : '<div class="empty">No notes yet for this class and subject.</div>'}</div>
    ${chapters.length ? `<div class="card"><h3>Chapters without notes</h3>${chapters.filter(n => !have.has(n.toLowerCase())).slice(0, 30).map(n => `<div class="li"><div class="t"><b>${esc(n)}</b></div><button class="btn s l" onclick="go('notes',{key:${jsq(key)},edit:'new',ch:${jsq(n)}})">Make</button></div>`).join('') || '<div class="empty">Every chapter has notes.</div>'}</div>` : ''}`;
}
function noteText(n) { return `*${n.chapter}* — Class ${n.cls} ${n.subj}\n\n` + (n.body ? n.body + '\n\n' : '') + ((n.points || []).length ? '*Key points*\n' + n.points.map(x => '• ' + x).join('\n') + '\n\n' : '') + ((n.words || []).length ? '*Words*\n' + n.words.map(w => `${w.w} — ${w.m}`).join('\n') + '\n\n' : '') + ((n.qa || []).length ? '*Questions*\n' + n.qa.map((x, i) => `Q${i + 1}. ${x.q}\nAns. ${x.a}`).join('\n\n') + '\n\n' : '') + `— ${S.settings.school || ''}`; }
function noteBody(n) { return `${n.body ? `<p>${esc(n.body)}</p>` : ''}${(n.points || []).length ? `<h4>Key points</h4><ul>${n.points.map(x => `<li>${esc(x)}</li>`).join('')}</ul>` : ''}${(n.words || []).length ? `<h4>Words & meanings</h4><ul>${n.words.map(w => `<li><b>${esc(w.w)}</b> — ${esc(w.m)}</li>`).join('')}</ul>` : ''}${(n.qa || []).length ? `<h4>Questions & answers</h4>${n.qa.map((x, i) => `<div class="qa"><b>Q${i + 1}. ${esc(x.q)}</b><p>${esc(x.a)}</p></div>`).join('')}` : ''}`; }
function noteView(n, key) {
  return `<button class="btn s w" style="align-self:flex-start" onclick="go('notes',{key:${jsq(key)}})">${ic('back')} All notes</button>
    <div class="card note"><h3>${esc(n.chapter)} <small>Class ${esc(n.cls)} · ${esc(n.subj)}</small></h3>${noteBody(n)}<p class="mut" style="margin-top:10px">${esc(n.by || '')} · ${fmtTime(n.at)} · ${n.share ? 'Students and parents can see this in their app (Homework page).' : 'Draft — not shared yet.'}</p></div>
    <div class="btns"><a class="btn wa" target="_blank" rel="noopener" href="${wa('', noteText(n))}">${ic('wa')} WhatsApp</a><button class="btn w" onclick="notePrint('${n.id}',${jsq(key)})">${ic('print')} Print</button>
      <button class="btn l" onclick="go('notes',{key:${jsq(key)},edit:'${n.id}'})">Edit</button>${n.tid === me() || isP() ? `<button class="btn bad" onclick="noteDel('${n.id}',${jsq(key)})">Delete</button>` : ''}</div>`;
}
function notePrint(id, key) { const n = NOTES[key].find(x => x.id === id); let p = document.querySelector('.prt'); if (!p) { p = document.createElement('div'); p.className = 'prt'; document.body.appendChild(p); }
  p.innerHTML = `<h1>${esc(S.settings.school || '')}</h1><h2>Class ${esc(n.cls)} · ${esc(n.subj)} · ${esc(n.chapter)}</h2><div class="note">${noteBody(n)}</div>`; window.print(); }
async function noteDel(id, key) { if (!confirm('Delete these notes?')) return; try { await api('teacher_api.php?a=delnote', { id }); NOTES[key] = NOTES[key].filter(n => n.id !== id); toast('Deleted.'); go('notes', { key }); } catch (e) { toast(e.message); } }
function noteEditor(c, s) {
  const key = sylKey(c, s), old = (NOTES[key] || []).find(n => n.id === P.edit);
  if (!DRAFT.note || DRAFT.note.key !== key + P.edit) DRAFT.note = { key: key + P.edit, n: old ? JSON.parse(JSON.stringify(old)) : { chapter: P.ch || '', body: '', points: [], words: [], qa: [], share: true, via: 'typed' }, photos: [] };
  const n = DRAFT.note.n, chapters = ((S.syl[key] || {}).chapters || []).map(x => x.n);
  return `<button class="btn s w" style="align-self:flex-start" onclick="DRAFT.note=null;go('notes',{key:${jsq(key)}})">${ic('back')} Back</button>
    <div class="card"><h3>${old ? 'Edit notes' : 'New notes'} <small>Class ${esc(c)} · ${esc(s)}</small></h3>
      <div class="fld">Chapter<input id="nC" class="inp" list="nCl" maxlength="160" value="${esc(n.chapter)}" placeholder="Chapter name" oninput="DRAFT.note.n.chapter=this.value"><datalist id="nCl">${chapters.map(x => `<option value="${esc(x)}">`).join('')}</datalist></div>
      <div style="margin-top:10px"><label class="btn l full filebtn">${ic('cam')} ${DRAFT.note.photos.length ? 'Add more pages' : 'Take photos of the chapter pages (up to 4)'}<input type="file" accept="image/*" capture="environment" multiple onchange="notePick(this.files)"></label>
        ${DRAFT.note.photos.length ? `<div class="photos" style="margin-top:8px">${DRAFT.note.photos.map(u => `<img src="${u}" alt="">`).join('')}</div>
          <button class="btn go full" style="margin-top:8px" onclick="noteRead(${jsq(c)},${jsq(s)})">Make notes from ${DRAFT.note.photos.length} photo(s)</button>
          <button class="btn w s" style="margin-top:6px" onclick="DRAFT.note.photos=[];render()">Clear photos</button>` : ''}
        ${DRAFT.busy && DRAFT.busyK === 'note' ? `<div class="ban inf" style="margin-top:8px">${esc(DRAFT.busy)}</div>` : ''}</div></div>
    ${n.via === 'ai' ? '<div class="ban warn">Made by AI from the photos — read it once and correct anything before sharing.</div>' : n.via === 'phone' ? '<div class="ban warn">The phone read the text of the pages — tidy it into notes before sharing.</div>' : ''}
    <div class="card" style="display:flex;flex-direction:column;gap:10px">
      <div class="fld">Summary / notes<textarea id="nB" class="inp" rows="6" oninput="DRAFT.note.n.body=this.value">${esc(n.body)}</textarea></div>
      <div class="fld">Key points — one per line<textarea id="nP" class="inp" rows="7" oninput="DRAFT.note.n.points=this.value.split('\\n')">${esc((n.points || []).join('\n'))}</textarea></div>
      <div class="fld">Words — one per line: word = meaning<textarea id="nW" class="inp" rows="4" oninput="DRAFT.note.n.words=this.value.split('\\n').map(l=>{const i=l.indexOf('=');return i<0?{w:l,m:''}:{w:l.slice(0,i).trim(),m:l.slice(i+1).trim()}})">${esc((n.words || []).map(w => w.w + (w.m ? ' = ' + w.m : '')).join('\n'))}</textarea></div>
      <div class="fld">Questions & answers — “Q:” line, then “A:” line<textarea id="nQ" class="inp" rows="7" oninput="DRAFT.note.n.qa=qaParse(this.value)">${esc((n.qa || []).map(x => 'Q: ' + x.q + '\nA: ' + x.a).join('\n\n'))}</textarea></div>
      <label class="chk"><input type="checkbox" ${n.share ? 'checked' : ''} onchange="DRAFT.note.n.share=this.checked"> Share with students and parents (their app → Homework page)</label>
      <button class="btn go full" onclick="noteSave(${jsq(c)},${jsq(s)})">Save notes</button></div>`;
}
function qaParse(t) { const out = []; let cur = null; String(t).split('\n').forEach(l => { const m = l.match(/^\s*(Q|प्र|प्रश्न)\s*[\d.]*\s*[:.\-]\s*(.*)$/i), a = l.match(/^\s*(A|Ans|उ|उत्तर)\s*[:.\-]\s*(.*)$/i);
  if (m) { cur = { q: m[2].trim(), a: '' }; out.push(cur); } else if (a && cur) cur.a = (cur.a ? cur.a + '\n' : '') + a[2].trim(); else if (cur && l.trim()) cur.a = (cur.a ? cur.a + '\n' : '') + l.trim(); }); return out; }
async function notePick(files) { for (const f of [...files].slice(0, 4 - DRAFT.note.photos.length)) { try { DRAFT.note.photos.push(await shrink(f)); } catch (e) { toast(e.message); } } render(); }
async function noteRead(c, s) {
  const imgs = DRAFT.note.photos; if (!imgs.length) return;
  const busy = m => { DRAFT.busy = m; DRAFT.busyK = 'note'; render(); }, n = DRAFT.note.n;
  try {
    let res = null;
    if (AI !== false) { busy('AI is reading the pages and writing notes… (20–60 seconds)'); try { res = await api('scan_api.php?a=read', { mode: 'notes', cls: c, subj: s, images: imgs }); } catch (e) { if (!e.noAi) throw e; AI = false; } }
    if (res) { if (res.chapter && !n.chapter) n.chapter = res.chapter; n.body = res.summary || ''; n.points = res.points || []; n.words = res.words || []; n.qa = res.qa || []; n.via = 'ai'; }
    else { busy('The school AI is off — your phone is reading the text (about a minute)…'); await tessLoad(); let text = '';
      for (let i = 0; i < imgs.length; i++) { busy(`Your phone is reading page ${i + 1} of ${imgs.length}…`); const r = await window.Tesseract.recognize(imgs[i], 'eng+hin'); text += ((r && r.data && r.data.text) || '') + '\n'; }
      n.body = text.replace(/\n{3,}/g, '\n\n').trim().slice(0, 18000); n.via = 'phone'; }
    DRAFT.note.photos = []; toast('Draft ready. Read it once, correct it, then Save.');
  } catch (e) { toast(e.message); } finally { DRAFT.busy = null; render(); }
}
async function noteSave(c, s) {
  const n = DRAFT.note.n, key = sylKey(c, s);
  n.chapter = ($('nC').value || '').trim(); n.body = $('nB').value; n.points = $('nP').value.split('\n').map(x => x.trim()).filter(Boolean);
  n.words = $('nW').value.split('\n').map(l => { const i = l.indexOf('='); return i < 0 ? { w: l.trim(), m: '' } : { w: l.slice(0, i).trim(), m: l.slice(i + 1).trim() }; }).filter(w => w.w);
  n.qa = qaParse($('nQ').value);
  if (!n.chapter) return toast('Write the chapter name.');
  try { const r = await api('teacher_api.php?a=note', { id: n.id || '', cls: c, subj: s, chapter: n.chapter, title: n.chapter, body: n.body, points: n.points, words: n.words, qa: n.qa, share: n.share, via: n.via,
      order: Math.max(0, ((S.syl[key] || {}).chapters || []).findIndex(x => x.n.toLowerCase() === n.chapter.toLowerCase())) });
    NOTES[key] = (NOTES[key] || []).filter(x => x.id !== r.note.id).concat([r.note]); DRAFT.note = null; toast('Notes saved.'); go('notes', { key, id: r.note.id });
  } catch (e) { toast(e.message); }
}

// ---------------------------------------------------------------- RESULT (Term marks → instant result)
const MK = {};
function resClasses() { const set = new Set([...allocList().map(a => a.c), ...myCT()]); return className().filter(c => set.has(c)); }
function vResult() {
  const classes = resClasses(); if (!classes.length) return '<div class="card empty">No classes are given to you yet.</div>';
  const ses = sesKey(); if (!ses) return '<div class="card empty">The session is not set in the ERP settings yet.</div>';
  const exam = ['Term-1', 'Term-2'].includes(P.exam) ? P.exam : (store.get('ta_exam') || 'Term-1'), c = classes.includes(P.c) ? P.c : classes[0], m = P.m || 'marks';
  const k = ses + '|' + exam + '|' + c;
  const head = `<div class="two"><div class="fld">Exam${sel('rE', [['Term-1', 'Term-1'], ['Term-2', 'Term-2']], exam, `store.set('ta_exam',this.value);go('res',{exam:this.value,c:${jsq(c)},m:'${m}'})`)}</div><div class="fld">Class${sel('rC', classes.map(x => [x, 'Class ' + x]), c, `go('res',{exam:'${exam}',c:this.value,m:'${m}'})`)}</div></div>
    ${seg([['marks', 'Marks entry'], ['sheet', 'Result'], ['final', 'Final (T1+T2)']], m, `(x=>go('res',{exam:x==='final'?'Term-2':'${exam}',c:${jsq(c)},m:x}))`)}`;
  if (!MK[k]) { api(`progress_api.php?a=marks&ses=${ses}&exam=${exam}&cls=${encodeURIComponent(c)}`).then(d => { MK[k] = d; render(); }).catch(e => { MK[k] = { err: e.message }; render(); }); return head + '<div class="load"><i></i></div>'; }
  const d = MK[k]; if (d.err) return head + `<div class="card">${esc(d.err)} <button class="btn s l" onclick="delete MK['${k}'];render()">Try again</button></div>`;
  if (!d.students.length) return head + '<div class="card empty">No students in this class.</div>';
  return head + (m === 'sheet' ? resSheet(d, ses, exam, c, false) : m === 'final' ? resSheet(d, ses, exam, c, true) : marksEntry(d, ses, exam, c, k));
}
function marksEntry(d, ses, exam, c, k) {
  const subs = d.mySubjects.length ? d.mySubjects : [];
  if (!subs.length) return `<div class="card empty">You do not teach a subject in Class ${esc(c)}. Open “Result” to see the class result.</div>`;
  const subj = subs.includes(P.s) ? P.s : subs[0], rows = ((d.marks[`${ses}|${exam}|${c}|${subj}`] || {}).rows) || {}, saved = d.marks[`${ses}|${exam}|${c}|${subj}`];
  return `${subs.length > 1 ? `<div class="fld">Subject${sel('rS', subs.map(x => [x, x]), subj, `go('res',{exam:'${exam}',c:${jsq(c)},m:'marks',s:this.value})`)}</div>` : `<p class="mut" style="margin:0">Subject: <b>${esc(subj)}</b></p>`}
    <div class="card"><h3>${esc(subj)} · ${esc(exam)} <small>${saved ? 'Saved ' + fmtTime(saved.at) : 'Not saved yet'}</small></h3>
      <p class="mut" style="margin:0 0 6px">Write AB for absent. Total and grade come by themselves.</p>
      <div style="overflow-x:auto"><table class="mk"><tr><th>Student</th>${PARTS.map(([p, l, mx]) => `<th>${l}<br>${mx}</th>`).join('')}<th>Total</th></tr>
        ${d.students.map(s => { const r = rows[s.id] || {}; return `<tr><td>${esc(s.name)}</td>${PARTS.map(([p, , mx]) => `<td><input inputmode="decimal" data-s="${esc(s.id)}" data-p="${p}" data-max="${mx}" value="${esc(r[p] ?? '')}" oninput="mkTot(this)"></td>`).join('')}<td class="gd" id="tot-${esc(s.id)}">${mkSum(r)}</td></tr>`; }).join('')}</table></div>
      <div class="sticky"><button class="btn go full" onclick="mkSave('${ses}','${exam}',${jsq(c)},${jsq(subj)},'${k}')">Save ${esc(subj)} marks</button></div></div>
    <a class="btn w full" target="_blank" rel="noopener" href="progress.php?tab=card&exam=${encodeURIComponent(exam)}">${ic('print')} Printed report cards (web ERP)</a>`;
}
function num(v) { return v === '' || v == null || String(v).toUpperCase() === 'AB' ? 0 : Number(v); }
function mkSum(r) { const any = PARTS.some(([p]) => String(r[p] ?? '') !== ''); if (!any) return ''; const t = PARTS.reduce((a, [p]) => a + num(r[p]), 0); return `${Math.round(t * 100) / 100} <span class="g${grade(t)}">${grade(t)}</span>`; }
function mkTot(inp) { const v = inp.value.trim().toUpperCase(); inp.classList.toggle('bad', v !== '' && v !== 'AB' && !(/^\d{1,3}(\.\d{1,2})?$/.test(v) && +v <= +inp.dataset.max));
  const r = {}; document.querySelectorAll(`.mk input[data-s="${CSS.escape(inp.dataset.s)}"]`).forEach(x => r[x.dataset.p] = x.value.trim()); $('tot-' + inp.dataset.s).innerHTML = mkSum(r); }
async function mkSave(ses, exam, c, subj, k) {
  const rows = {}; let bad = 0;
  document.querySelectorAll('.mk input').forEach(x => { const v = x.value.trim().toUpperCase(); (rows[x.dataset.s] = rows[x.dataset.s] || {})[x.dataset.p] = v; if (x.classList.contains('bad')) bad++; });
  if (bad) return toast('Some marks are more than the maximum. Check the red boxes.');
  try { await api('progress_api.php?a=marks', { ses, exam, cls: c, subject: subj, rows }); delete MK[k]; toast(subj + ' marks saved. The result is updated.'); render(); } catch (e) { toast(e.message); }
}
function termOf(marks, sid, subjects) {
  const by = Object.fromEntries(Object.values(marks || {}).map(x => [x.subject, x]));
  const rows = subjects.map(s => { const m = ((by[s] || {}).rows || {})[sid] || {}; const t = PARTS.reduce((a, [p]) => a + num(m[p]), 0); const has = PARTS.some(([p]) => String(m[p] ?? '') !== ''); return { s, t, has }; });
  const sum = rows.reduce((a, r) => a + r.t, 0), max = rows.length * 100, p = max ? Math.round(sum / max * 10000) / 100 : 0;
  return { rows, sum, max, p, g: grade(p), any: rows.some(r => r.has) };
}
function resSheet(d, ses, exam, c, final) {
  const subs = d.subjects; if (!subs.length) return '<div class="card empty">No subjects are set for this class.</div>';
  if (final && !d.other) return '<div class="empty">Choose Term-2 to see the final result.</div>';
  const entered = subs.filter(s => d.marks[`${ses}|${exam}|${c}|${s}`]);
  const R = d.students.map(s => { const a = termOf(final ? d.other.marks : d.marks, s.id, subs), b = final ? termOf(d.marks, s.id, subs) : null;
    const sum = a.sum + (b ? b.sum : 0), max = a.max + (b ? b.max : 0), p = max ? Math.round(sum / max * 10000) / 100 : 0;
    return { s, a, b, sum, max, p, g: grade(p), any: a.any || (b && b.any) }; });
  const ranked = R.filter(r => r.any).sort((x, y) => y.sum - x.sum); ranked.forEach((r, i) => r.rank = i + 1);
  const pass = ranked.filter(r => r.p >= 33).length;
  RESULT = { R, subs, ses, exam, c, final };
  return `${!final && entered.length < subs.length ? `<div class="ban warn">Marks entered for ${entered.length} of ${subs.length} subjects. Waiting: ${subs.filter(s => !entered.includes(s)).map(esc).join(', ')}.</div>` : ''}
    <div class="kp"><div class="k ac"><span>Class average</span><b>${ranked.length ? Math.round(ranked.reduce((a, r) => a + r.p, 0) / ranked.length * 10) / 10 + '%' : '—'}</b><small>${ranked.length} student(s) with marks</small></div>
      <div class="k ${pass === ranked.length ? 'ok' : 'bad'}"><span>Passed (33%+)</span><b>${pass}/${ranked.length}</b><small>${ranked[0] ? 'Topper: ' + esc(ranked[0].s.name) : ''}</small></div></div>
    <div class="res"><table><tr><th class="l">#</th><th class="l">Student</th>${subs.map(s => `<th>${esc(s.slice(0, 10))}</th>`).join('')}<th>Total</th><th>%</th><th>Grade</th><th></th></tr>
      ${(ranked.length ? ranked.concat(R.filter(r => !r.any)) : R).map(r => `<tr><td class="l">${r.rank || '—'}</td><td class="l">${esc(r.s.name)}</td>${subs.map((s, i) => { const t = r.a.rows[i].t + (r.b ? r.b.rows[i].t : 0), has = r.a.rows[i].has || (r.b && r.b.rows[i].has); return `<td>${has ? Math.round(t * 100) / 100 : '–'}</td>`; }).join('')}
        <td><b>${Math.round(r.sum * 100) / 100}</b>/${r.max}</td><td>${r.any ? r.p : '–'}</td><td class="gd g${r.g}">${r.any ? r.g : ''}</td><td>${r.any && r.s.phone ? `<a class="btn wa s" target="_blank" rel="noopener" href="${wa(r.s.phone, resultText(r))}">${ic('wa')}</a>` : ''}</td></tr>`).join('')}</table></div>
    <div class="btns"><button class="btn w" onclick="resPrint()">${ic('print')} Print result sheet</button><a class="btn w" target="_blank" rel="noopener" href="progress.php?tab=${final ? 'card&exam=Final' : 'card&exam=' + encodeURIComponent(exam)}">Report cards</a></div>
    ${!final && myCT().includes(c) ? attToCard(d, ses, exam, c) : ''}`;
}
let RESULT = null;
function resultText(r) {
  const { subs, exam, c, final } = RESULT;
  return `Dear Parent,\n*${r.s.name}* — Class ${c}, ${final ? 'Final result (Term-1 + Term-2)' : exam}\n` + subs.map((s, i) => `${s}: ${Math.round((r.a.rows[i].t + (r.b ? r.b.rows[i].t : 0)) * 100) / 100}/${final ? 200 : 100}`).join('\n') +
    `\n*Total ${Math.round(r.sum * 100) / 100}/${r.max} · ${r.p}% · Grade ${r.g}${r.rank ? ' · Rank ' + r.rank : ''}*\n— ${S.settings.school || ''}`;
}
function resPrint() {
  const { R, subs, exam, c, final } = RESULT; let p = document.querySelector('.prt'); if (!p) { p = document.createElement('div'); p.className = 'prt'; document.body.appendChild(p); }
  const rows = R.filter(r => r.any).sort((a, b) => (a.rank || 999) - (b.rank || 999));
  p.innerHTML = `<h1>${esc(S.settings.school || '')}</h1><h2>Result Sheet — Class ${esc(c)} — ${final ? 'Final (Term-1 + Term-2)' : esc(exam)} — Session ${esc(S.settings.session || '')}</h2>
    <table><tr><th>Rank</th><th>Name</th><th>Adm</th>${subs.map(s => `<th>${esc(s)}</th>`).join('')}<th>Total</th><th>%</th><th>Grade</th></tr>${rows.map(r => `<tr><td>${r.rank}</td><td>${esc(r.s.name)}</td><td>${esc(r.s.adm || '')}</td>${subs.map((s, i) => `<td>${Math.round((r.a.rows[i].t + (r.b ? r.b.rows[i].t : 0)) * 100) / 100}</td>`).join('')}<td>${Math.round(r.sum * 100) / 100}/${r.max}</td><td>${r.p}</td><td>${r.g}</td></tr>`).join('')}</table>
    <p style="margin-top:30px">Class Teacher ____________________ &nbsp;&nbsp;&nbsp; Principal ____________________</p>`;
  window.print();
}
// Class teacher: working days and presents for the report card, counted from the attendance register.
function attToCard(d, ses, exam, c) {
  const from = P.af || sessionStart(), to = P.at || todayISO();
  return `<div class="card"><h3>Attendance on the report card</h3><p class="mut" style="margin:0 0 8px">Counted from the attendance register (holidays and Sundays left out). Remarks and co-scholastic grades already entered are kept.</p>
    <div class="two"><div class="fld">From<input id="afF" type="date" class="inp" value="${from}"></div><div class="fld">To<input id="afT" type="date" class="inp" value="${to}"></div></div>
    <button class="btn l full" style="margin-top:8px" onclick="attCard('${ses}','${exam}',${jsq(c)})">Fill attendance for every child</button></div>`;
}
async function attCard(ses, exam, c) {
  const from = $('afF').value, to = $('afT').value; if (!from || !to || to < from) return toast('Check the dates.');
  try {
    toast('Counting school days…');
    for (let ym = ymOf(from); ym <= ymOf(to); ym = ymAdd(ym, 1)) await ensureMonth(c, ym);
    let days = 0; const pres = {}; const kids = MK[ses + '|' + exam + '|' + c].students;
    for (let d = from; d <= to; d = addDays(d, 1)) { const a = attOf(d, c); if (!a || !isWorkDay(d, c)) continue; days++; kids.forEach(s => { if (!(a.absent || []).includes(s.id)) pres[s.id] = (pres[s.id] || 0) + 1; }); }
    if (!days) return toast('No attendance is saved between these dates.');
    if (!confirm(`${days} school days with attendance. Put days and presents on the ${exam} report card of all ${kids.length} children?`)) return;
    const old = (MK[ses + '|' + exam + '|' + c].extra || {}).rows || {}, rows = {};
    kids.forEach(s => { const o = old[s.id] || {}; rows[s.id] = { art: o.art || '', work: o.work || '', health: o.health || '', disc: o.disc || '', remark: o.remark || '', result: o.result || '', promoted: o.promoted || '', days: String(days), present: String(pres[s.id] || 0) }; });
    await api('progress_api.php?a=extra', { ses, exam, cls: c, rows }); delete MK[ses + '|' + exam + '|' + c]; toast('Attendance filled on the report cards.'); render();
  } catch (e) { toast(e.message); }
}

// ---------------------------------------------------------------- ME (leave, holidays, PIN)
function openMe() {
  const mine = S.leaves.filter(l => l.tid === me()), t = todayISO();
  const hol = S.cal.filter(e => e.type === 'Holiday' && (e.to || e.date) >= t).slice(0, 12);
  SHEET = () => `<div class="row"><h2>${esc(myName())}</h2><span class="pill">${isP() ? 'Principal' : 'Teacher'}</span></div>
    <p class="mut" style="margin:0">${myCT().length && !isP() ? 'Class teacher of ' + myCT().map(esc).join(', ') + ' · ' : ''}${[...new Set(myAllocs().map(a => a.s))].slice(0, 5).map(esc).join(', ')}</p>
    <div class="card"><h3>Apply for leave</h3><div class="two"><div class="fld">From<input id="lvF" type="date" class="inp" value="${t}"></div><div class="fld">To<input id="lvT" type="date" class="inp" value="${t}"></div></div>
      <div class="fld" style="margin-top:8px">Reason<input id="lvR" class="inp" maxlength="200" placeholder="e.g. Family function"></div>
      <button class="btn go full" style="margin-top:8px" onclick="leaveApply()">Send to Principal</button>
      ${mine.length ? mine.slice(0, 6).map(l => `<div class="li"><div class="t"><b>${fmtDate(l.from)}${l.to !== l.from ? ' – ' + fmtDate(l.to) : ''}</b><span>${esc(l.reason)}</span></div><span class="pill ${l.status === 'Approved' ? 'ok' : l.status === 'Rejected' ? 'bad' : 'warn'}">${esc(l.status)}</span></div>`).join('') : ''}</div>
    <div class="card"><h3>School holidays ahead</h3>${hol.length ? hol.map(h => `<div class="li"><div class="t"><b>${esc(h.title)}</b><span>${fmtDate(h.date)}${h.to && h.to !== h.date ? ' – ' + fmtDate(h.to) : ''}</span></div></div>`).join('') : '<div class="empty">None in the calendar.</div>'}
      <p class="mut" style="margin:6px 0 0">The Basic Shiksha Parishad / BSA Bhadohi holiday list is added to the school calendar by itself. The Principal can remove any day in the web ERP → Calendar.</p></div>
    <div class="card"><h3>Change my PIN</h3><div class="two"><input id="pO" class="inp pin" type="password" inputmode="numeric" placeholder="Old"><input id="pN" class="inp pin" type="password" inputmode="numeric" placeholder="New"></div>
      <button class="btn l full" style="margin-top:8px" onclick="pinChange()">Change PIN</button></div>
    ${installBox()}
    <button class="btn bad full" onclick="logout()">Log out</button>
    <p class="mut" style="text-align:center;margin:0">SNMSIS Teacher · Powered by Maakit</p>`;
  render();
}
function leaveApply() {
  const from = $('lvF').value, to = $('lvT').value, reason = $('lvR').value.trim();
  if (!from || !to || to < from) return toast('Check the dates.'); if (!reason) return toast('Write a reason.');
  const id = uid(); put('leave', id, { id, tid: me(), from, to, reason: reason.slice(0, 200), status: 'Pending', at: Date.now() }, 'Leave', `${myName()} requested leave ${from} to ${to}: ${reason} — Teacher App`);
  S.leaves.sort((a, b) => (b.at || 0) - (a.at || 0)); toast('Leave request sent to the Principal.'); openMe();
}
async function pinChange() { const o = $('pO').value.trim(), n = $('pN').value.trim(); if (!/^\d{4,8}$/.test(n)) return toast('New PIN must be 4 to 8 digits.'); if (n === '1234') return toast('Choose a PIN other than 1234.');
  try { await api('api.php?a=setpin', { old: o, pin: n }); toast('PIN changed.'); closeSheet(); } catch (e) { toast(e.message); } }
async function logout() {
  if (QUEUE.length && !confirm(QUEUE.length + ' change(s) are not sent yet (no internet). Log out anyway? They will be lost.')) return;
  try { await api('api.php?a=logout', {}); } catch (e) {}
  store.set(qKey(), null); store.set('sch_token', null); S = null; ME = null; SHEET = null; LOGIN = {}; QUEUE = []; LOGQ = []; render();
}

// ---------------------------------------------------------------- install
let INST = null;
const STANDALONE = () => matchMedia('(display-mode: standalone)').matches || navigator.standalone === true;
addEventListener('beforeinstallprompt', e => { e.preventDefault(); INST = e; });
addEventListener('appinstalled', () => { INST = null; toast('Installed. Open “Teacher” from your home screen.'); });
function installBox() {
  if (STANDALONE()) return '';
  if (INST) return '<button class="btn l full" onclick="doInstall()">Install the Teacher app on this phone</button>';
  return `<div class="ban inf"><div><b>Put this app on your home screen</b><br>${/iphone|ipad|ipod/i.test(navigator.userAgent) ? 'In Safari tap Share → Add to Home Screen.' : 'In Chrome tap ⋮ → Install app (or Add to Home screen).'}</div></div>`;
}
async function doInstall() { if (!INST) return; INST.prompt(); await INST.userChoice; INST = null; render(); }

// ---------------------------------------------------------------- login (teacher name + PIN; the first PIN is 1234)
async function loginView() {
  const L = LOGIN || {};
  if (!STATUS && !L.step) { $('app').innerHTML = '<div class="load"><i></i></div>'; try { STATUS = await api('api.php?a=status'); } catch (e) { STATUS = { teachers: [], err: e.message }; } }
  const tid = store.get('ta_tid') || '', who = store.get('ta_who') || 'teacher';
  $('app').innerHTML = `<div class="band"><img src="${LOGO}" alt=""><div class="wm">SNMSIS Teacher</div><small>${esc((STATUS && STATUS.school) || 'SN Mishra Singapore International School')}</small></div>
    <div class="lg">${L.step === 'new' ? `<h1>Choose your own PIN</h1><p>First login: choose a new PIN (4–8 digits, not 1234). Keep it secret.</p>
        <input id="lgOld" class="inp pin" type="password" inputmode="numeric" placeholder="Current PIN (1234)"><input id="lgNew" class="inp pin" type="password" inputmode="numeric" placeholder="New PIN"><button class="btn go full" onclick="setPin()">Save PIN</button>`
      : `<h1>Namaste!</h1><p>Choose your name and enter your PIN. First time? Your PIN is 1234.</p>
        ${seg([['teacher', 'Teacher'], ['principal', 'Principal']], who, "(k=>{store.set('ta_who',k);loginView()})")}
        ${who === 'teacher' ? `<select id="lgT" class="inp"><option value="">— Your name —</option>${((STATUS && STATUS.teachers) || []).filter(t => t.tid !== 'P').map(t => `<option value="${esc(t.tid)}" ${t.tid === tid ? 'selected' : ''}>${esc(t.name)}</option>`).join('')}</select>` : ''}
        <input id="lgPin" class="inp pin" type="password" inputmode="numeric" placeholder="PIN" autocomplete="current-password" onkeydown="if(event.key==='Enter')login()">
        <button class="btn go full" onclick="login()">Open</button>`}
      <p class="err">${esc(L.msg || (STATUS && STATUS.err) || '')}</p>${installBox()}<p class="mut" style="text-align:center">Powered by Maakit</p></div>`;
}
async function login() {
  const who = store.get('ta_who') || 'teacher', tid = who === 'teacher' ? ($('lgT') || {}).value : '';
  if (who === 'teacher' && !tid) { LOGIN = { msg: 'Choose your name.' }; return loginView(); }
  try { const d = await api('api.php?a=login', { role: who, tid, pin: $('lgPin').value }); store.set('sch_token', d.token); if (tid) store.set('ta_tid', tid);
    if (d.mustChange) { LOGIN = { step: 'new' }; return loginView(); } LOGIN = {}; load(); }
  catch (e) { LOGIN = { msg: e.message }; loginView(); }
}
async function setPin() { try { await api('api.php?a=setpin', { old: $('lgOld').value, pin: $('lgNew').value }); toast('PIN saved.'); LOGIN = {}; load(); } catch (e) { LOGIN = { step: 'new', msg: e.message }; loginView(); } }

boot();
