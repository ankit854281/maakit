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
const LOC = () => (window.taLocale ? window.taLocale() : 'en-IN');   // dates follow the chosen language (teacher-lang.js)
const DAYS = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
const BELLS = [{ n: '1st', s: '09:00', e: '09:35' }, { n: '2nd', s: '09:35', e: '10:10' }, { n: '3rd', s: '10:10', e: '10:45' },
  { n: '4th', s: '11:05', e: '11:40' }, { n: '5th', s: '11:40', e: '12:15' }, { n: '6th', s: '12:15', e: '12:50' }, { n: '7th', s: '12:50', e: '13:30' }];
const PARTS = [['pt', 'Pre-Test', 10], ['nb', 'Notebook', 5], ['sea', 'SEA', 5], ['te', 'Term', 80]];
// Notes live inside Planner (Planner · Lesson plan / Syllabus / Notes) so the phone keeps six tabs (owner, 5 Nov 2026: Connect added).
const PTABS = [['prin', 'Cross-check', 'check'], ['att', 'Classes', 'list'], ['conn', 'Connect', 'chat']];   // Principal mode (owner, 5 Nov 2026)
const TABS = [['home', 'Today', 'sun'], ['att', 'Attendance', 'check'], ['diary', 'Classroom', 'book'], ['plan', 'Planner', 'list'], ['res', 'Results', 'chart'], ['conn', 'Connect', 'chat']];
// Each tab has its own colour, like the Chairman app (owner, 5 Nov 2026): header, buttons and the page's soft wash change
// together when the tab changes. Colours are picked to stay readable with white text and to differ clearly from each other.
const TH = {
  home:  { a: '#3730A3', a2: '#6366F1', soft: '#E0E2FF', tint: '#F1F2FF', sh: 'rgba(55,48,163,.30)', head: 'linear-gradient(150deg,#6366F1 0%,#4338CA 55%,#312E81 100%)', page: '#E9EAFF' },
  att:   { a: '#BE185D', a2: '#E3417F', soft: '#FAD6E5', tint: '#FDF0F5', sh: 'rgba(190,24,93,.28)', head: 'linear-gradient(150deg,#EC4899 0%,#C81F64 55%,#9D174D 100%)', page: '#FCE4EE' },
  diary: { a: '#C2410C', a2: '#F97316', soft: '#FFE2CF', tint: '#FFF4EC', sh: 'rgba(194,65,12,.28)', head: 'linear-gradient(150deg,#FB923C 0%,#EA580C 55%,#B4400B 100%)', page: '#FFEBDD' },
  plan:  { a: '#6D28D9', a2: '#A66CF0', soft: '#EADCFD', tint: '#F6F0FE', sh: 'rgba(109,40,217,.28)', head: 'linear-gradient(150deg,#A78BFA 0%,#7C3AED 55%,#5B21B6 100%)', page: '#EFE4FE' },
  notes: { a: '#0E7490', a2: '#22B3C9', soft: '#CFF1F6', tint: '#ECFAFB', sh: 'rgba(14,116,144,.28)', head: 'linear-gradient(150deg,#22D3EE 0%,#0891B2 55%,#0E6178 100%)', page: '#DDF5F8' },
  conn:  { a: '#1D4ED8', a2: '#3B82F6', soft: '#DBE6FE', tint: '#EFF4FF', sh: 'rgba(29,78,216,.28)', head: 'linear-gradient(150deg,#60A5FA 0%,#2563EB 55%,#1E3A8A 100%)', page: '#E3ECFE' },
  res:   { a: '#047857', a2: '#22B573', soft: '#D1F2E2', tint: '#EEFAF3', sh: 'rgba(4,120,87,.28)', head: 'linear-gradient(150deg,#34D399 0%,#059669 55%,#065F46 100%)', page: '#DDF4E8' }
};
function theme() {
  const th = TH[TAB === 'notes' ? 'plan' : TAB === 'prin' ? 'home' : TAB] || TH.home, r = document.documentElement.style;
  r.setProperty('--a', th.a); r.setProperty('--a2', th.a2); r.setProperty('--soft', th.soft); r.setProperty('--tint', th.tint);
  r.setProperty('--hshadow', th.sh); r.setProperty('--head', th.head); r.setProperty('--page', th.page);
  const m = document.querySelector('meta[name=theme-color]'); if (m) m.setAttribute('content', th.a);
}
const ICONS = {
  sun: '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>',
  check: '<rect x="4" y="4" width="16" height="17" rx="3"/><path d="M9 2v4M15 2v4M8.5 13.5l2.5 2.5 4.5-5"/>',
  book: '<path d="M4 5.5A2.5 2.5 0 0 1 6.5 3H20v15H6.5A2.5 2.5 0 0 0 4 20.5z"/><path d="M4 20.5A2.5 2.5 0 0 0 6.5 23H20v-5M8 7h8M8 11h6"/>',
  list: '<path d="M9 6h11M9 12h11M9 18h11"/><path d="M4 6l1 1 2-2M4 12l1 1 2-2M4 18l1 1 2-2"/>',
  note: '<path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/><path d="M14 3v6h6M8 13h8M8 17h5"/>',
  chart: '<path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>',
  chat: '<path d="M21 12a8 8 0 0 1-11.7 7.1L4 20.5l1.4-4.9A8 8 0 1 1 21 12z"/><path d="M8.5 11h7M8.5 14h4.5"/>',
  phone: '<path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2z"/>',
  bill: '<path d="M6 2h12v20l-3-2-3 2-3-2-3 2z"/><path d="M9 7h6M9 11h6M9 15h4"/>',
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
function fmtDate(iso) { if (!iso) return ''; return new Date(iso + 'T00:00:00').toLocaleDateString(LOC(), { weekday: 'short', day: '2-digit', month: 'short' }); }
function fmtLong(iso) { if (!iso) return ''; return new Date(iso + 'T00:00:00').toLocaleDateString(LOC(), { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }); }
function fmtTime(ts) { return new Date(ts).toLocaleString(LOC(), { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' }); }
function t12(hhmm) { let [h, m] = hhmm.split(':').map(Number); const ap = h >= 12 ? 'PM' : 'AM'; h = h % 12 || 12; return h + ':' + pad(m) + ' ' + ap; }
function nowMin() { const d = new Date(); return d.getHours() * 60 + d.getMinutes(); }
function toMin(hhmm) { const [h, m] = hhmm.split(':').map(Number); return h * 60 + m; }
function esc(s) { return String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c])); }
function jsq(s) { return esc(JSON.stringify(String(s ?? ''))); }
function uid() { return Date.now().toString(36) + Math.random().toString(36).slice(2, 6); }
function ymOf(iso) { return iso.slice(0, 7); }
function ymAdd(ym, n) { const [y, m] = ym.split('-').map(Number), d = new Date(y, m - 1 + n, 1); return d.getFullYear() + '-' + pad(d.getMonth() + 1); }
function ymName(ym) { const [y, m] = ym.split('-').map(Number); return new Date(y, m - 1, 1).toLocaleString(LOC(), { month: 'long', year: 'numeric' }); }
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
// Offline copy (owner, 5 Nov 2026: the app must open without internet). The last data the server sent is kept in this
// phone's IndexedDB for the logged-in teacher only. With no internet the app opens from that copy, says so in a banner,
// and keeps every change in the queue; the moment the internet is back it loads fresh from the server. Log out deletes it.
const IDB = {
  db: null,
  open() { return this.db || (this.db = new Promise((ok, bad) => { try { const r = indexedDB.open('snmsis-teacher', 1); r.onupgradeneeded = () => r.result.createObjectStore('kv'); r.onsuccess = () => ok(r.result); r.onerror = () => bad(r.error); } catch (e) { bad(e); } })); },
  async run(mode, fn) { const db = await this.open(); return new Promise((ok, bad) => { const tx = db.transaction('kv', mode), q = fn(tx.objectStore('kv')); tx.oncomplete = () => ok(q && q.result); tx.onerror = () => bad(tx.error); }); },
  get(k) { return this.run('readonly', s => s.get(k)).catch(() => null); },
  set(k, v) { return this.run('readwrite', s => s.put(v, k)).catch(() => null); },
  del(k) { return this.run('readwrite', s => s.delete(k)).catch(() => null); }
};
let OFFLINE_COPY = null;
function cacheKey() { return 'state'; }
function cacheTok() { return (store.get('sch_token') || '').slice(0, 16); }
async function load() {
  $('app').innerHTML = '<div class="load"><i></i>Opening your classes…</div>';
  let st, res;
  try {
    st = await api('teacher_api.php?a=state');
    res = await api('api.php?a=load&days=70');
    OFFLINE_COPY = null;
    IDB.set(cacheKey(), { tok: cacheTok(), at: Date.now(), ai: st.ai, res });
  } catch (e) {
    if (e.code === 428) { LOGIN = { step: 'new' }; S = null; return render(); }
    if (e.code === 401) return;
    let copy = (e.net || e.code >= 500) ? await IDB.get(cacheKey()) : null;
    if (copy && copy.tok !== cacheTok()) copy = null;                 // only the same login's own copy
    if (!copy) { $('app').innerHTML = `<div class="load">${esc(e.message)}<button class="btn" onclick="load()">Try again</button></div>`; return; }
    st = { ai: copy.ai }; res = copy.res; OFFLINE_COPY = copy.at; NET = 'off';
  }
  AI = st.ai;
  if (!['teacher', 'principal'].includes(res.me.role)) { LOGIN = { msg: 'This app is for teachers. Please log in with a teacher PIN.' }; store.set('sch_token', null); S = null; return render(); }
  ME = res.me; S = emptyState(); applyRecs(res.records); lastV = res.now; loadedFrom = res.from;
  loadQueue(); QUEUE.forEach(x => x.del ? delRec(x.k, x.r) : setRec(x.k, x.r, x.d)); keepLoad();
  render(); if (!OFFLINE_COPY) { flush(); scLoad(null).then(() => softRender()); } showTeacherWelcome();
}
addEventListener('online', () => { if (OFFLINE_COPY && store.get('sch_token')) load(); });
// Every change: shown at once on this phone, queued, sent. If the internet is off it waits (also across a restart).
function putDel(k, r, logK, logX) {
  delRec(k, r);
  QUEUE = QUEUE.filter(x => !(x.k === k && x.r === r)); QUEUE.push({ k, r, del: true });
  if (logK) LOGQ.push({ k: logK, x: logX });
  saveQueue(); flush();
}
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
    const res = await api('api.php?a=sync', { put: batch.filter(x => !x.del).map(x => ({ k: x.k, r: x.r, d: x.d })), del: batch.filter(x => x.del).map(x => ({ k: x.k, r: x.r })), log: logs });
    const sig = x => x.k + '|' + x.r + '|' + (x.del ? '-' : JSON.stringify(x.d)), sent = new Set(batch.map(sig));
    QUEUE = QUEUE.filter(x => !sent.has(sig(x))); LOGQ = LOGQ.slice(logs.length); saveQueue();
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
  if (OFFLINE_COPY) { try { await api('api.php?a=status'); const a = document.activeElement; if (!(a && /INPUT|TEXTAREA|SELECT/.test(a.tagName))) load(); } catch (e) {} return; }
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
function T(tid) { return S.teachers[tid] || { name: ((S.settings || {}).leftTeachers || {})[tid] || tid }; }
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
function myBells(date) { return bellsOf(me(), date); }
function bellsOf(tid, date) {
  const d = dayIdx(date); if (d < 0 || !S.grid || holidayOn(date)) return [];
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
  const views = { home: vHome, att: vAtt, diary: vDiary, plan: vPlan, notes: vNotes, res: vResult, conn: vConn, prin: vPrin };
  const pm = pMode(), tabs = pm ? PTABS : TABS;
  if (!views[TAB] || (pm && !PTABS.some(t => t[0] === TAB)) || (!pm && TAB === 'prin')) TAB = pm ? 'prin' : 'home'; theme();
  let body = ''; try { body = views[TAB](); } catch (e) { console.error(e); body = `<div class="card">This screen could not open: ${esc(e.message)}</div>`; }
  const initials = myName().split(/\s+/).filter(Boolean).map(x => x[0]).slice(0, 2).join('').toUpperCase() || 'T';
  const dueAtt = myCT().reduce((n, c) => n + (attOf(todayISO(), c) || clsHolOn(todayISO(), c) || !isWorkDay(todayISO(), c) || !studentsOf(c).length ? 0 : 1), 0);
  const dueDiary = myBells(todayISO()).filter(b => bellOver(todayISO(), b.bell) && !diaryEntry(todayISO(), b.bell)).length;
  const dot = { att: dueAtt, diary: dueDiary, conn: scUnread(), prin: pm ? prinPendingCount() : 0 }, sc0 = document.querySelector('.scroll'), y0 = sc0 ? sc0.scrollTop : 0, rg0 = document.querySelector('.reg'), rx0 = rg0 ? rg0.scrollLeft : 0, anim = TAB !== LASTTAB; LASTTAB = TAB;
  $('app').innerHTML = `<div class="head"><div class="loc"><div class="w"><small>${esc(S.settings.school || 'SN Mishra Singapore International School')}</small><b>${esc(greet())}, ${esc(myName())}</b>${isP() ? `<div class="mode-sw" role="group" aria-label="Mode"><button class="${pm ? '' : 'on'}" onclick="setMode('teacher')">Teacher</button><button class="${pm ? 'on' : ''}" onclick="setMode('principal')">Principal</button></div>` : ''}</div>
      <button id="net" class="net" onclick="flush();refresh()"></button><button class="lang" onclick="langCycle()" aria-label="Language">${window.langShort ? langShort() : 'EN'}</button><button class="av" onclick="openMe()" aria-label="My account">${teacherPhoto() ? `<img class="teacher-avatar-img" src="${esc(teacherPhoto())}" alt="">` : esc(initials)}</button></div>
    <div class="tabs${pm ? ' t3' : ''}">${tabs.map(([k, l, i]) => `<button class="tab ${TAB === k || (k === 'plan' && TAB === 'notes') ? 'on' : ''}" onclick="go('${k}')">${dot[k] ? `<span class="dot">${dot[k]}</span>` : ''}${ic(i)}${l}</button>`).join('')}</div></div>
    <div class="scroll"><div class="body${anim ? ' anim' : ''}">${OFFLINE_COPY ? `<div class="ban warn offline-copy"><div><b>No internet — showing the copy saved on ${esc(fmtTime(OFFLINE_COPY))}.</b><br>You can mark attendance and write the diary. Everything is sent when the internet is back.</div></div>` : ''}${body}</div></div>${SHEET ? `<div class="shade" onclick="closeSheet()"></div><div class="sheet"><div class="grab"></div>${SHEET()}</div>` : ''}`;
  keepRestore(); scScroll(); if (typeof afterRender === 'function') afterRender();
  if (!anim) { const sc = document.querySelector('.scroll'); if (sc) sc.scrollTop = y0; const rg = document.querySelector('.reg'); if (rg) rg.scrollLeft = rx0; }
  setNet();
}
let LASTTAB = null;
// Unsaved typing must survive every re-draw: the 45-second refresh, saving another bell, opening a sheet, switching
// tabs and even closing the app. Fields marked data-keep are remembered per screen (KEEP[ctx][id]) on this phone and
// put back after each render. A successful save clears only the fields it saved. (Owner: teachers lost typed marks.)
let KEEP = {};
function keepKey() { return 'ta_keep_' + (ME ? ME.role + ME.tid : ''); }
function keepCtx() { return [TAB, P.date || '', P.c || '', P.exam || '', P.s || '', P.m || '', P.key || '', P.w || ''].join('|'); }
function keepLoad() { try { KEEP = JSON.parse(store.get(keepKey()) || '{}') || {}; } catch (e) { KEEP = {}; } }
function keepSave() { const ks = Object.keys(KEEP); ks.slice(0, Math.max(0, ks.length - 25)).forEach(k => delete KEEP[k]); store.set(keepKey(), JSON.stringify(KEEP)); }
function keepNote(el) {
  if (!S || !el || !el.id || !el.hasAttribute || !el.hasAttribute('data-keep')) return;
  const c = keepCtx(), box = KEEP[c] = KEEP[c] || {};
  delete KEEP[c]; KEEP[c] = box;                                  // most recent screen last (oldest are trimmed)
  box[el.id] = el.type === 'checkbox' ? !!el.checked : el.value; keepSave();
}
function keepRestore() {
  const box = KEEP[keepCtx()]; if (!box) { if (document.querySelector('.mk')) mkProgress(); return; }
  Object.entries(box).forEach(([id, v]) => { const el = $(id); if (!el) return;
    if (el.type === 'checkbox') el.checked = !!v; else if (el.value !== v) { el.value = v; el.classList.add('kept'); }
    if (el.dataset.p) mkTot(el); });
  if (document.querySelector('.mk')) mkProgress();
}
function keepClear(prefix) { const c = keepCtx(), box = KEEP[c]; if (!box) return; Object.keys(box).forEach(id => { if (id.startsWith(prefix)) delete box[id]; }); if (!Object.keys(box).length) delete KEEP[c]; keepSave(); }
function keepHas(prefix) { const box = KEEP[keepCtx()] || {}; return Object.keys(box).some(id => id.startsWith(prefix)); }
document.addEventListener('input', e => keepNote(e.target));
document.addEventListener('change', e => keepNote(e.target));
function softRender() { const a = document.activeElement; if (a && /INPUT|TEXTAREA|SELECT/.test(a.tagName)) return; if ((DRAFT.chk && DRAFT.chk.changed) || (DRAFT.att && DRAFT.att.changed) || (DRAFT.kit && DRAFT.kit.changed) || (DRAFT.rec && DRAFT.rec.changed) || (DRAFT.reg && Object.keys(DRAFT.reg.days).length) || DRAFT.note || SHEET) return; render(); }
function greet() { const h = new Date().getHours(); return h < 12 ? 'Good morning' : h < 17 ? 'Good afternoon' : 'Good evening'; }
function closeSheet() { SHEET = null; render(); }
function seg(items, cur, fn) { return `<div class="seg">${items.map(([k, l]) => `<button class="${k === cur ? 'on' : ''}" onclick="${fn}(${jsq(k)})">${l}</button>`).join('')}</div>`; }
function sel(id, items, cur, onch) { return `<select id="${id}" class="inp" onchange="${onch}">${items.map(([v, l]) => `<option value="${esc(v)}" ${v === cur ? 'selected' : ''}>${esc(l)}</option>`).join('')}</select>`; }
const DRAFT = {};


// One gentle welcome per app opening. It never interrupts an attendance edit or the offline queue.
const TEACHER_QUOTES = [
  ['Every child can learn. Your patience helps them believe it.', 'Har bachche ke andar ek nayi shuruaat hai.'],
  ['A small lesson today can become a big dream tomorrow.', 'Aaj ka chhota kadam, kal ka bada sapna.'],
  ['Teach with care. Progress comes one step at a time.', 'Har din ek bachche ka hausla badhaiye.'],
  ['Your kindness is part of what students remember.', 'Padhai ke saath apnapan bhi sikhaiye.'],
  ['One encouraging word can change a student’s day.', 'Aaj kisi bachche ki mehnat ko sarahiye.'],
  ['Great teaching makes room for questions and curiosity.', 'Sawal puchhne ki himmat badhaiye.'],
  ['Each new day is another chance to make learning joyful.', 'Aaj ki class ko yaadgaar banaiye.']
];
function teacherQuote() {
  const date = todayISO().split('-').map(Number);
  const n = Math.floor(Date.UTC(date[0], date[1] - 1, date[2]) / 86400000);
  return TEACHER_QUOTES[n % TEACHER_QUOTES.length];
}
function teacherMotivation() {
  const q = teacherQuote();
  return '<section class="teacher-motivation" aria-label="Daily teacher motivation"><div class="motivation-eyebrow">✦ TODAY’S TEACHER SPARK</div>' +
    '<p class="motivation-quote">' + esc(q[0]) + '</p><small>' + esc(q[1]) + '</small></section>';
}
function teacherPhotoKey() { return 'ta_photo_' + (ME ? ME.role + ME.tid : ''); }
function teacherPhoto() {
  const data = store.get(teacherPhotoKey()) || '';
  return data.length < 175000 && /^data:image\/jpeg;base64,[A-Za-z0-9+/=]+$/.test(data) ? data : '';
}
function teacherInitials() { return myName().split(/\s+/).filter(Boolean).map(x => x[0]).slice(0, 2).join('').toUpperCase() || 'T'; }
// ---------------------------------------------------------------- opening story (owner, 5 Nov 2026: "jaise Chairman Sir ka app")
// Same story style as chairman.js, in 3 layers: 1 logo + SNMSIS changing through many languages on a moving colour
// sweep · 2 Welcome + the teacher's photo + today's thought · 3 "your day" counting up + Start. Plays once a day by itself (teachers open the app many times), any time from
// My account → Replay welcome. Tap the right side = next scene, left side = back, Skip ends it.
const TW_LANGS = [['SNMSIS', 'English'], ['एस एन एम एस आई एस', 'हिन्दी'], ['এস এন এম এস আই এস', 'বাংলা'], ['எஸ் என் எம் எஸ் ஐ எஸ்', 'தமிழ்'], ['ఎస్ ఎన్ ఎమ్ ఎస్ ఐ ఎస్', 'తెలుగు'],
  ['એસ એન એમ એસ આઈ એસ', 'ગુજરાતી'], ['ਐਸ ਐਨ ਐਮ ਐਸ ਆਈ ਐਸ', 'ਪੰਜਾਬੀ'], ['ಎಸ್ ಎನ್ ಎಂ ಎಸ್ ಐ ಎಸ್', 'ಕನ್ನಡ'], ['എസ് എൻ എം എസ് ഐ എസ്', 'മലയാളം'], ['ایس این ایم ایس آئی ایس', 'اردو'],
  ['エス・エヌ・エム・エス・アイ・エス', '日本語'], ['ЭС ЭН ЭМ ЭС АЙ ЭС', 'Русский'], ['إس إن إم إس آي إس', 'العربية'], ['SNMSIS', 'English']];
const TW_SCENES = [4200, 3400, 3800];          // owner: "bahut lamba — 3 layer" (5 Nov 2026), about 11 seconds
let WELCOME_SHOWN = false, TW = null;
function twDayKey() { return 'ta_intro_day'; }
function closeTeacherWelcome() {
  if (TW) { TW.timers.forEach(clearTimeout); TW = null; }
  const el = $('ta-welcome'); if (!el) return;
  el.classList.add('leaving'); setTimeout(() => { if (el.parentNode) el.remove(); }, 260);
}
function twNumbers() {
  const d = todayISO(), bells = myBells(d), ct = myCT(), kids = ct.reduce((n, c) => n + studentsOf(c).length, 0);
  const toMark = ct.filter(c => isWorkDay(d, c) && !attOf(d, c)).length, dp = diaryPending(6).reduce((n, x) => n + x.n, 0);
  return [[bells.length, 'bells today'], [kids, ct.length ? 'children in my class' : 'classes I teach'], [toMark, 'attendance to mark'], [dp, 'diaries pending']]
    .map(([v, l], i) => [i === 1 && !ct.length ? new Set(allocList().map(a => a.c)).size : v, l]);
}
function showTeacherWelcome(force) {
  if (!S || (!force && WELCOME_SHOWN)) return;
  WELCOME_SHOWN = true;
  if (!force && store.get('ta_intro') === 'day' && store.get(twDayKey()) === todayISO()) return;   // owner, 5 Nov 2026: every opening, like Chairman Sir's app; "once a day" is a choice
  store.set(twDayKey(), todayISO());
  closeTeacherWelcome(); const old = $('ta-welcome'); if (old) old.remove();
  const q = teacherQuote(), photo = teacherPhoto(), name = myName();
  const el = document.createElement('div');
  el.id = 'ta-welcome'; el.className = 'tw'; el.setAttribute('role', 'dialog'); el.setAttribute('aria-label', 'Welcome to SNMSIS Teacher');
  el.innerHTML = `<div class="tw-bars">${TW_SCENES.map(() => '<i></i>').join('')}</div><button class="tw-skip" type="button" onclick="closeTeacherWelcome()">Skip</button>
    <div class="tw-tap l" onclick="twStep(-1)"></div><div class="tw-tap r" onclick="twStep(1)"></div>
    <section class="tw-s s1"><div class="tw-glow"></div><img class="tw-logo" src="${LOGO}" alt="School logo"><div class="tw-lang" id="twLang" data-notr></div><p class="tw-t" data-notr>SN Mishra Singapore International School · Bhadohi</p></section>
    <section class="tw-s s3"><div class="tw-photo">${photo ? `<img src="${esc(photo)}" alt="">` : esc(teacherInitials())}</div><p class="tw-k">${esc(greet())}</p><h1 class="tw-h">Welcome,<br><span class="tw-gold" data-notr>${esc(name)}</span></h1><p class="tw-t">“${esc(q[0])}”</p><small class="tw-sm">${esc(q[1])}</small></section>
    <section class="tw-s s4"><p class="tw-k">Your day today</p><h1 class="tw-h">${esc(fmtLong(todayISO()))}</h1><div class="tw-grid">${twNumbers().map(([v, l], i) => `<div class="tw-card" style="--d:${.1 + i * .12}s"><b data-v="${v}">0</b><span>${esc(l)}</span></div>`).join('')}</div>
      <button class="tw-go" type="button" onclick="closeTeacherWelcome()">Start my day →</button></section>`;
  $('phone').appendChild(el);
  TW = { i: -1, timers: [] }; twStep(1);
}
function twStep(dir) {
  if (!TW) return; const el = $('ta-welcome'); if (!el) return;
  TW.timers.forEach(clearTimeout); TW.timers = [];
  TW.i = Math.max(0, TW.i + dir); if (TW.i >= TW_SCENES.length) return closeTeacherWelcome();
  const bars = el.querySelectorAll('.tw-bars i'), sc = el.querySelectorAll('.tw-s'), ms = TW_SCENES[TW.i];
  bars.forEach((b, j) => { b.className = j < TW.i ? 'done' : ''; b.style.removeProperty('--ms'); });
  bars[TW.i].style.setProperty('--ms', ms + 'ms'); void bars[TW.i].offsetWidth; bars[TW.i].className = 'run';
  sc.forEach((x, j) => x.classList.toggle('on', j === TW.i));
  if (TW.i === 0) { let n = 0; const box = $('twLang'); const step = () => { if (!TW || TW.i !== 0) return; const [w, l] = TW_LANGS[n % TW_LANGS.length];
      box.innerHTML = `<b dir="auto">${esc(w)}</b><small>${esc(l)}</small>`; n++; if (n < TW_LANGS.length) TW.timers.push(setTimeout(step, (ms - 500) / TW_LANGS.length)); }; TW.timers.push(setTimeout(step, 400)); }
  if (TW.i === 2) el.querySelectorAll('.tw-card b[data-v]').forEach(b => { const v = +b.dataset.v, t0 = performance.now();
    const f = now => { const p = Math.min(1, (now - t0) / 1000); b.textContent = Math.round(v * (1 - Math.pow(1 - p, 3))); if (p < 1 && TW) requestAnimationFrame(f); }; requestAnimationFrame(f); });
  TW.timers.push(setTimeout(() => twStep(1), ms));
}
async function profilePhotoPick(input) {
  const f = input.files && input.files[0];
  if (!f) return;
  if (!f.type.startsWith('image/') || f.size > 8 * 1024 * 1024) return toast('Choose an image below 8 MB.');
  const url = URL.createObjectURL(f);
  const img = new Image();
  img.onload = () => {
    try {
      const canvas = document.createElement('canvas');
      canvas.width = 240; canvas.height = 240;
      const ctx = canvas.getContext('2d');
      if (!ctx) throw new Error('Photo cannot be read on this phone.');
      const edge = Math.min(img.width, img.height);
      ctx.drawImage(img, (img.width-edge)/2, (img.height-edge)/2, edge, edge, 0, 0, 240, 240);
      const data = canvas.toDataURL('image/jpeg', .72);
      if (data.length >= 175000) throw new Error('This photo is too large.');
      store.set(teacherPhotoKey(), data);
      toast('Profile photo saved on this device.');
      openMe();
    } catch (e) { toast(e.message || 'Could not save photo.'); }
    URL.revokeObjectURL(url);
  };
  img.onerror = () => { URL.revokeObjectURL(url); toast('Could not open this photo.'); };
  img.src = url;
}
function profilePhotoRemove() { store.set(teacherPhotoKey(), null); openMe(); }

// ---------------------------------------------------------------- AAJ (today)
function vHome() {
  const d = todayISO(), bells = myBells(d), hol = holidayOn(d), ct = myCT();
  const filled = bells.filter(b => diaryEntry(d, b.bell)).length;
  const back = ct.flatMap(c => pendingDays(c, addDays(d, -30)).filter(x => x < d).map(x => ({ c, x })));
  const upcoming = [...S.cal.filter(e => e.type === 'Holiday' && (e.to || e.date) >= d).map(e => ({ date: e.date, to: e.to, t: e.title })),
    ...Object.entries(S.clsHol).flatMap(([dt, byC]) => ct.filter(c => byC[c] && dt >= d).map(c => ({ date: dt, t: 'Class ' + c + ': ' + byC[c].title })))]
    .sort((a, b) => a.date.localeCompare(b.date)).slice(0, 4);
  const mine = S.leaves.filter(l => l.tid === me()).slice(0, 2);
  const notices = S.notices.filter(n => n.to !== 'Parents').slice(0, 3);
  // homework I gave today, and homework from earlier days that is due today (to check in class)
  const hwToday = bells.map(b => ({ b, e: diaryEntry(d, b.bell) })).filter(x => x.e && x.e.hw);
  const hwDue = []; for (let i = 1; i <= 7; i++) { const dd = addDays(d, -i); Object.entries((S.diary[dd] || {})[me()] || {}).forEach(([, e]) => { if (e && e.hw && e.hwDate === d) hwDue.push({ dd, e }); }); }
  const week = []; for (let i = 6; i >= 1; i--) { const dd = addDays(d, -i), bl = myBells(dd); if (bl.length) week.push({ dd, n: bl.length, f: bl.filter(b => diaryEntry(dd, b.bell)).length }); }
  const wN = week.reduce((n, x) => n + x.n, 0), wF = week.reduce((n, x) => n + x.f, 0);
  const sc = ct[0] || (allocList()[0] || {}).c, st = sc ? needStars(sc) : null, ph = sc ? needPhotos(sc) : {};
  const quick = [['att', 'Attendance', 'check', ct.length ? { c: ct[0] } : {}], ['att', 'Daily check', 'bill', ct.length ? { c: ct[0], m: 'kit' } : {}], ['diary', 'Diary', 'book', {}], ['diary', 'Check work', 'list', { m: 'check' }],
    ['plan', 'Syllabus', 'note', { m: 'syl' }], ['att', 'Students', 'list', { m: 'stu', c: ct[0] || (myClasses()[0] || '') }], ['plan', 'Paper', 'print', { m: 'paper' }], ['res', 'Marks', 'chart', {}], ['conn', 'Parents', 'wa', { m: 'msg' }], ['conn', 'Staff chat', 'chat', { m: 'staff' }]].filter(q => ct.length || q[0] !== 'att' || q[1] === 'Students');
  const kidCard = (k, why, cls) => `<div class="star ${cls}"><span class="ph">${ph[k.sid] ? `<img src="${esc(ph[k.sid].src)}" alt="" loading="lazy">` : `<i>${esc(initials(k.name))}</i>`}${cls === 'top' ? '<em>★</em>' : ''}</span><b>${esc(k.name.split(' ')[0])}</b><small>${esc(why)}</small></div>`;
  const starsHtml = !st || st.loading ? '' : st.err ? '' : `
    <div class="card stars-card"><h3>⭐ Star children <small>Class ${esc(sc)} · last 30 days</small></h3>
      ${st.stars.length ? `<div class="stars">${st.stars.map(k => kidCard(k, k.why[0], 'top')).join('')}</div>` : `<p class="mut" style="margin:0">Stars appear here from homework checks, the daily check and attendance. ${ct.length ? 'Fill <b>Attendance → Daily check</b> and <b>Classroom → Check work</b>.' : 'Fill <b>Classroom → Check work</b>.'}</p>`}
      ${st.stars.length ? `<button class="btn s l" style="margin-top:8px" onclick="go('conn',{m:'msg',c:${jsq(sc)},t:'praise'})">${ic('wa')} Send appreciation to parents</button>` : ''}</div>
    ${st.needs.length ? `<div class="card"><h3>Needs attention <small>${st.needs.length}</small></h3>${st.needs.map(k => `<div class="li"><span class="ph sm">${ph[k.sid] ? `<img src="${esc(ph[k.sid].src)}" alt="">` : `<i>${esc(initials(k.name))}</i>`}</span><div class="t"><b>${esc(k.name)}</b><span>${esc(k.need.join(' · '))}</span></div></div>`).join('')}</div>` : ''}`;
  return `<div class="row"><h2>${fmtLong(d)}</h2></div>
    ${teacherMotivation()}
    ${dayIdx(d) < 0 ? '<div class="ban hol">Sunday — no school today.</div>' : hol ? `<div class="ban hol"><b>Holiday:</b> ${esc(hol.title)}</div>` : ''}
    ${absentFrom(d, me()) !== null ? `<div class="ban warn">${absentFrom(d, me()) ? 'Half day today — you leave before the ' + BELLS[absentFrom(d, me())].n + ' bell.' : 'You are on leave / marked absent today.'}</div>` : ''}
    <div class="kp">
      <button class="k ac" onclick="go('diary')"><span>My bells today</span><b>${bells.length}</b><small>${filled} diary filled</small></button>
      <button class="k ${hwToday.length ? 'ok' : ''}" onclick="go('diary')"><span>Homework given today</span><b>${hwToday.length}</b><small>${hwDue.length ? hwDue.length + ' due today to check' : 'in my bells'}</small></button>
      ${ct.length ? ct.slice(0, isP() ? 1 : 3).map(c => { const a = attOf(d, c), h = clsHolOn(d, c), n = studentsOf(c).length;
        return `<button class="k ${a ? 'ok' : h || !isWorkDay(d) ? '' : 'go'}" onclick="go('att',{c:'${esc(c)}'})"><span>Class ${esc(c)} attendance</span><b>${a ? (n - (a.absent || []).length) + '/' + n : h ? 'Holiday' : !isWorkDay(d) ? '—' : 'Mark'}</b><small>${a ? 'Saved ' + fmtTime(a.at || Date.now()) : h ? esc(h.title) : !isWorkDay(d) ? 'No school' : 'Not marked yet'}</small></button>`; }).join('')
        : `<button class="k" onclick="go('notes')"><span>Chapter notes</span><b>${ic('cam')}</b><small>Make notes from a book photo</small></button>`}
    </div>
    <div class="card quick-card"><div class="quick-grid">${quick.map(([t, l, i, p]) => `<button type="button" onclick='go(${JSON.stringify(t)},${JSON.stringify(p)})'><span>${ic(i)}</span>${l}${t === 'conn' && p.m === 'staff' && scUnread() ? `<em>${scUnread()}</em>` : ''}</button>`).join('')}</div></div>
    ${myWorkCard()}
    ${starsHtml}
    ${back.length ? `<div class="ban warn"><div><b>${back.length} school day(s) without attendance</b> in the last 30 days (${[...new Set(back.map(x => 'Class ' + x.c))].join(', ')}).<br><button class="btn s go" style="margin-top:8px" onclick="go('att',{c:'${esc(back[0].c)}',m:'pending'})">Update them together</button></div></div>` : ''}
    ${''/* 'Diary not filled' days are now listed in the My work card (myWorkCard, teacher-work.js) */}
    <div class="card"><h3>My bells <small>${bells.length ? filled + ' of ' + bells.length + ' filled' : 'none'}</small></h3>
      ${bells.length ? bells.map(b => { const e = diaryEntry(d, b.bell);
        return `<div class="li" onclick="go('diary',{date:'${d}',bell:${b.bell}})"><div class="bell ${b.arr ? 'arr' : ''}"><b>${BELLS[b.bell].n.replace(/\D/g, '')}</b>${t12(BELLS[b.bell].s).replace(' ', '')}</div>
          <div class="t"><b>Class ${esc(b.cls)} · ${esc(b.subj)}</b><span>${b.arr ? 'Arrangement' + (b.for ? ' for ' + esc(T(b.for).name) : '') + ' · ' : ''}${e ? 'Topic: ' + esc(e.topic) : bellOver(d, b.bell) ? 'Diary not filled' : 'Upcoming'}</span></div>
          ${e ? '<span class="pill ok">Done</span>' : bellOver(d, b.bell) ? '<span class="pill warn">Fill</span>' : ''}</div>`; }).join('')
        : `<div class="empty">${dayIdx(d) < 0 ? 'Sunday — no classes.' : hol ? 'Holiday — no classes.' : S.grid ? 'No bells for you today.' : 'The timetable is not set yet. Use Diary → "Add a period".'}</div>`}</div>
    <div class="card"><h3>Homework <small>${hwToday.length} given · ${hwDue.length} due today</small></h3>
      ${hwToday.map(x => `<div class="li" onclick="go('diary',{date:'${d}',bell:${x.b.bell}})"><div class="bell"><b>${esc(x.b.cls)}</b>${esc(x.b.subj.slice(0, 5))}</div><div class="t"><b>${esc(x.e.hw)}</b><span>Given today${x.e.hwMinutes ? ' · ' + x.e.hwMinutes + ' min' : ''}${x.e.hwDate ? ' · for ' + esc(fmtDate(x.e.hwDate)) : ''}</span></div><span class="pill ok">Given</span></div>`).join('')}
      ${hwDue.map(x => `<div class="li" onclick="go('diary',{date:'${d}',m:'check'})"><div class="bell arr"><b>${esc(x.e.cls || '')}</b>${esc(String(x.e.subj || '').slice(0, 5))}</div><div class="t"><b>${esc(x.e.hw)}</b><span>Given ${esc(fmtDate(x.dd))} · check today</span></div><span class="pill warn">Check</span></div>`).join('')}
      ${!hwToday.length && !hwDue.length ? `<div class="empty">${bells.length ? 'No homework written yet today. Fill it in Classroom after each bell.' : 'No bells today.'}</div>` : ''}</div>
    ${week.length ? `<div class="card"><h3>My week <small>${wF} of ${wN} diaries filled</small></h3><div class="weekbar">${week.map(x => `<div onclick="go('diary',{date:'${x.dd}'})"><i style="--h:${Math.round(x.f / x.n * 100)}%" class="${x.f === x.n ? 'full' : x.f ? '' : 'none'}"></i><small>${esc(new Date(x.dd + 'T00:00:00').toLocaleDateString(LOC(), { weekday: 'short' }))}</small></div>`).join('')}</div></div>` : ''}
    ${notices.length ? `<div class="card"><h3>Notices</h3>${notices.map(n => `<div class="li"><div class="t"><b>${esc(n.title)}</b><span>${fmtDate(n.date)}</span><p class="sm" style="margin:4px 0 0;white-space:pre-wrap">${esc(n.body)}</p></div></div>`).join('')}</div>` : ''}
    ${upcoming.length ? `<div class="card"><h3>Coming holidays</h3>${upcoming.map(h => `<div class="li"><div class="t"><b>${esc(h.t)}</b><span>${fmtDate(h.date)}${h.to && h.to !== h.date ? ' – ' + fmtDate(h.to) : ''}</span></div><span class="pill hol">Holiday</span></div>`).join('')}</div>` : ''}
    ${mine.length ? `<div class="card"><h3>My leave <small onclick="openMe()">Apply</small></h3>${mine.map(l => `<div class="li"><div class="t"><b>${fmtDate(l.from)}${l.to !== l.from ? ' – ' + fmtDate(l.to) : ''}</b><span>${esc(l.reason)}</span></div><span class="pill ${l.status === 'Approved' ? 'ok' : l.status === 'Rejected' ? 'bad' : 'warn'}">${esc(l.status)}</span></div>`).join('')}</div>` : ''}`;
}

// ---------------------------------------------------------------- HAZIRI (attendance)
// ---------------------------------------------------------------- ATTENDANCE v3 (owner, 5 Nov 2026)
// Report at the top; Mark = photo cards (photo, name, roll, session %), one tap Present → Absent → Leave; a calendar
// for every day of the month (tap a day: take/edit attendance or mark a class holiday, or select many days for a
// holiday); Undo / Edit right after Submit. Same records as before (studAtt, clsHol) — nothing in the data changed.
const STATS = {}, PHOTOS = {};
let LAST_SUB = null, PHOTO_SID = null;
function needStats(c, force) {
  if (STATS[c] && !force && !STATS[c].loading) return STATS[c];
  if (STATS[c] && STATS[c].loading) return STATS[c];
  const old = STATS[c]; STATS[c] = Object.assign({}, old || {}, { loading: true });
  api('teacher_api.php?a=attstats&cls=' + encodeURIComponent(c)).then(r => { STATS[c] = r; softRender(); }).catch(() => { STATS[c] = Object.assign({}, old || { days: 0, per: {} }, { loading: false, err: true }); });
  return STATS[c];
}
function needPhotos(c) {
  if (PHOTOS[c]) return PHOTOS[c];
  PHOTOS[c] = {};
  IDB.get('photos:' + c).then(x => { if (x && !Object.keys(PHOTOS[c]).length) { PHOTOS[c] = x; softRender(); } });
  api('teacher_api.php?a=photos&cls=' + encodeURIComponent(c)).then(r => { PHOTOS[c] = r.photos || {}; IDB.set('photos:' + c, PHOTOS[c]); softRender(); }).catch(() => {});
  return PHOTOS[c];
}
const STARS = {};
function needStars(c) {
  if (STARS[c]) return STARS[c];
  STARS[c] = { loading: true };
  api('teacher_api.php?a=stars&cls=' + encodeURIComponent(c)).then(r => { STARS[c] = r; softRender(); }).catch(e => { STARS[c] = { err: e.message }; });
  return STARS[c];
}
function pctOf(c, sid) { const t = STATS[c]; if (!t || !t.days) return null; const x = (t.per || {})[sid] || { a: 0, l: 0 }; return Math.round((t.days - x.a - x.l) / t.days * 100); }
function initials(n) { return String(n || '').split(/\s+/).filter(Boolean).map(x => x[0]).slice(0, 2).join('').toUpperCase(); }
// Owner, 5 Nov 2026 ("student record teachers ke app me nahi hai"): every teacher of a class can open its Students report
// (attendance, homework, record, good things / complaints, profile). Only the class teacher marks attendance or changes the record.
function vAtt() {
  const allowed = myCT(), all = [...new Set([...allowed, ...myClasses()])].filter(x => className().includes(x));
  if (!all.length) return `<div class="card"><h3>Attendance</h3><p class="mut">No class is given to you yet. The Principal sets it in the timetable.</p></div>`;
  const c = all.includes(P.c) ? P.c : (allowed[0] || all[0]), isCT = allowed.includes(c), m = isCT ? (P.m || 'day') : 'stu';
  if (isCT) needStats(c);
  return `${all.length > 1 ? `<div class="fld">Class${sel('attC', all.map(x => [x, 'Class ' + x + (allowed.includes(x) ? ' · class teacher' : '')]), c, "DRAFT.att=null;DRAFT.reg=null;DRAFT.rec=null;go('att',{c:this.value,m:'" + m + "'})")}</div>` : ''}
    ${!isCT ? `<div class="ban inf"><div><b>Class ${esc(c)} — students</b><br><span class="sm">You teach this class. You can see every child's report and add good things or complaints. Attendance and the student record are kept by the class teacher${(S.classes.find(k => k.c === c) || {}).ct ? ' (' + esc(T(S.classes.find(k => k.c === c).ct).name) + ')' : ''}.</span></div></div>` : m === 'stu' && P.sid ? '' : attReport(c)}
    ${isCT ? seg([['day', 'Mark'], ['kit', 'Daily check'], ['stu', 'Students'], ['cal', 'Calendar'], ['reg', 'Register']], m === 'pending' ? 'cal' : m, `(k=>{DRAFT.att=null;DRAFT.reg=null;DRAFT.calSel=null;DRAFT.kit=null;go('att',{c:${jsq(c)},m:k})})`) : ''}
    ${m === 'reg' ? vRegister(c) : m === 'pending' ? vPending(c) : m === 'cal' ? vAttCal(c) : m === 'kit' ? vKit(c) : m === 'stu' ? (P.sid ? vStuReport(c, P.sid) : vStudents(c)) : vAttDay(c)}`;
}
function attReport(c) {
  const t = todayISO(), list = studentsOf(c), a = attOf(t, c), st = STATS[c] || {}, days = st.days || 0;
  const pcts = list.map(s => [s, pctOf(c, s.id)]).filter(x => x[1] !== null);
  const avg = pcts.length ? Math.round(pcts.reduce((n, x) => n + x[1], 0) / pcts.length) : null;
  const md = st.monthDays || 0, mAvg = md && list.length ? Math.round(list.reduce((n, s) => n + (md - (((st.per || {})[s.id] || {}).ma || 0)) / md, 0) / list.length * 100) : null;
  const low = pcts.filter(x => x[1] < 75).sort((x, y) => x[1] - y[1]);
  const todayTxt = a ? (list.length - (a.absent || []).length) + '/' + list.length : clsHolOn(t, c) || !isWorkDay(t, c) ? '—' : 'Not marked';
  return `<div class="card att-report"><div class="ar-top"><div><small>Class ${esc(c)} · ${list.length} students</small><b>Attendance report</b></div>${avg !== null ? `<div class="ring" style="--p:${avg}"><span>${avg}%</span></div>` : ''}</div>
    <div class="ar-grid"><div><span>Today</span><b>${esc(todayTxt)}</b></div><div><span>This month</span><b>${mAvg !== null ? mAvg + '%' : '—'}</b></div><div><span>Session</span><b>${avg !== null ? avg + '%' : '—'}</b><small>${days} school day(s)</small></div></div>
    ${low.length ? `<div class="ar-low"><span>Below 75%:</span>${low.slice(0, 6).map(([s, p]) => `<button class="chip warnc" onclick="go('att',{c:${jsq(c)},m:'day'})">${esc(s.name.split(' ')[0])} <b>${p}%</b></button>`).join('')}${low.length > 6 ? `<span class="mut">+${low.length - 6}</span>` : ''}</div>` : ''}</div>`;
}
function vAttDay(c) {
  const t = todayISO(), date = P.date && P.date <= t ? P.date : t;
  if (date < (loadedFrom || '') && !DAYS_LOADED[date]) { ensureDate(date).then(() => render()); return '<div class="load"><i></i></div>'; }
  const saved = attOf(date, c), list = studentsOf(c), hol = holidayOn(date), ch = clsHolOn(date, c), ph = needPhotos(c);
  const nav = `<div class="datebar"><button class="btn s w" onclick="DRAFT.att=null;go('att',{c:${jsq(c)},date:'${addDays(date, -1)}'})" aria-label="Previous day">${ic('back')}</button>
    <label class="dpick"><b>${date === t ? 'Today' : fmtDate(date)}</b><small>${esc(fmtLong(date))}</small><input type="date" max="${t}" value="${date}" onchange="DRAFT.att=null;go('att',{c:${jsq(c)},date:this.value})"></label>
    <button class="btn s w" ${date >= t ? 'disabled' : ''} onclick="DRAFT.att=null;go('att',{c:${jsq(c)},date:'${addDays(date, 1)}'})" aria-label="Next day">${ic('next')}</button></div>`;
  if (dayIdx(date) < 0) return nav + '<div class="ban hol">Sunday — no attendance.</div>';
  if (hol) return nav + `<div class="ban hol"><div><b>School holiday:</b> ${esc(hol.title)}<br><span class="sm">No attendance is taken on a holiday.</span></div></div>`;
  if (ch) return nav + `<div class="ban hol"><div><b>Class ${esc(c)} holiday:</b> ${esc(ch.title)}<br><span class="sm">Marked by ${esc(ch.by || '')}. This day is not counted as present or absent.</span><br><button class="btn s w" style="margin-top:8px" onclick="setClsHol(${jsq(c)},'${date}',false)">Remove holiday — take attendance</button></div></div>`;
  if (!DRAFT.att || DRAFT.att.key !== date + c) DRAFT.att = { key: date + c, st: stOf(saved, list), changed: false };
  const st = DRAFT.att.st, nA = list.filter(s => st[s.id] === 'A').length, nL = list.filter(s => st[s.id] === 'L').length;
  const absent = saved ? (saved.absent || []).filter(id => studentById(id)) : [];
  const nP = list.length - nA - nL, f = DRAFT.att.f || 'all', photoMode = !!DRAFT.att.photo;
  const shown = list.map((s, i) => [s, i]).filter(([s]) => f === 'all' || st[s.id] === f);
  const STL = { P: 'Present', A: 'Absent', L: 'Leave' };
  const just = LAST_SUB && LAST_SUB.key === date + '|' + c && !DRAFT.att.changed ? LAST_SUB : null;
  return nav + `${just ? `<div class="submitted"><div class="tick">✓</div><div class="t"><b>Attendance submitted</b><span>${nP} present · ${nA} absent · ${nL} leave · ${esc(fmtTime(just.at))}</span></div>
      <div class="sb-btns">${Date.now() - just.at < 10 * 60000 ? `<button class="btn s w" onclick="attUndo()">Undo</button>` : ''}<button class="btn s l" onclick="LAST_SUB=null;render()">Edit</button></div></div>` : ''}
    <div class="card roll-card">
      <div class="roll-head"><div>${saved ? `<span class="pill ok">Saved ${esc(fmtTime(saved.at || Date.now()))}</span>` : '<span class="pill warn">Not saved yet</span>'}</div>
        <div class="cnt"><span class="pill ok">${nP} P</span><span class="pill bad">${nA} A</span><span class="pill lv">${nL} L</span></div></div>
      ${list.length ? `<div class="quick"><input id="rollQ" class="inp" inputmode="numeric" enterkeyhint="done" placeholder="Absent roll no. e.g. 3, 7, 12" onkeydown="if(event.key==='Enter')rollQuick()"><button class="btn s" onclick="rollQuick()">Mark absent</button></div>
      <div class="chips">${[['all', 'All', list.length], ['A', 'Absent', nA], ['L', 'Leave', nL], ['P', 'Present', nP]].map(([k, l, n]) => `<button class="chip ${f === k ? 'on' : ''}" onclick="DRAFT.att.f='${k}';render()">${l} <b>${n}</b></button>`).join('')}
        <button class="chip ${photoMode ? 'on' : ''}" onclick="DRAFT.att.photo=!DRAFT.att.photo;render()">${ic('cam')} Photos</button></div>
      <p class="mut hint">${photoMode ? 'Photo mode: tap a child to take or choose the photo. Tap “Photos” again when done.' : 'Tap a child: Present → Absent → Leave.'}</p>
      <div class="tiles">${shown.map(([s, i]) => { const k = st[s.id], rn = s.rollno || (i + 1), p = pctOf(c, s.id), pic = ph[s.id];
        return `<button type="button" class="tile s${k}${photoMode ? ' pm' : ''}" data-st="${k}" aria-label="${esc(s.name)}: ${STL[k]}" onclick="${photoMode ? `photoPick('${esc(s.id)}')` : `attTap('${esc(s.id)}')`}">
          <span class="ph">${pic ? `<img src="${esc(pic.src)}" alt="" loading="lazy">` : `<i>${esc(initials(s.name))}</i>`}<em class="badge">${photoMode ? ic('cam') : k}</em></span>
          <b>${esc(s.name)}</b><small>Roll ${esc(rn)}${p !== null ? ` · <span class="${p < 75 ? 'lowp' : ''}">${p}%</span>` : ''}</small></button>`; }).join('') || '<div class="empty">Nobody here.</div>'}</div>`
        : '<div class="empty">No students in this class yet.</div>'}
      ${list.length ? `<div class="sticky"><div class="btns"><button class="btn w s" onclick="attAll('P')">All present</button><button class="btn w s" onclick="askHoliday(${jsq(c)},'${date}')">Mark holiday</button></div>
        <button class="btn go full submit" style="margin-top:8px" onclick="attSave(${jsq(c)},'${date}')">${saved ? 'Update attendance' : 'Submit attendance'} · ${nP} P · ${nA} A${nL ? ' · ' + nL + ' L' : ''}</button></div>` : ''}
    </div>
    <input type="file" id="phIn" accept="image/*" capture="environment" hidden onchange="photoGot(this)">
    ${absent.length ? `<div class="card"><h3>Tell the parents <small>${absent.length} absent</small></h3>${absent.map(id => { const s = studentById(id), lv = (saved.st || {})[id] === 'L';
      const today = date === todayISO(), msg = today
        ? `Dear Parent,\n${s.name} (Class ${c}) is ${lv ? 'on leave' : 'absent from school'} today, ${fmtLong(date)}.\nआज ${s.name} ${lv ? 'छुट्टी पर है' : 'विद्यालय में अनुपस्थित है। कृपया कारण बताएं'}।\n— ${S.settings.school || ''}`
        : `Dear Parent,\n${s.name} (Class ${c}) was ${lv ? 'on leave' : 'absent from school'} on ${fmtLong(date)}.\n${fmtLong(date)} को ${s.name} ${lv ? 'छुट्टी पर था/थी' : 'विद्यालय में अनुपस्थित था/थी। कृपया कारण बताएं'}।\n— ${S.settings.school || ''}`;
      return `<div class="li"><div class="t"><b>${esc(s.name)}</b><span>${lv ? 'Leave' : 'Absent'}${s.phone ? '' : ' · no phone saved'}</span></div>${s.phone ? `<a class="btn wa s" target="_blank" rel="noopener" href="${wa(s.phone, msg)}">${ic('wa')} Send</a>` : ''}</div>`; }).join('')}</div>` : ''}
    ${threeDays(c, date)}`;
}
// Photos: class teacher takes / chooses a photo; it is cropped square, made small and saved for the whole school.
function photoPick(sid) { PHOTO_SID = sid; const el = $('phIn'); if (el) { el.value = ''; el.click(); } }
async function photoGot(input) {
  const f = input.files && input.files[0], sid = PHOTO_SID; if (!f || !sid) return;
  try {
    const url = URL.createObjectURL(f), img = await new Promise((ok, bad) => { const i = new Image(); i.onload = () => ok(i); i.onerror = () => bad(new Error('This photo could not be opened. Please take it again.')); i.src = url; });
    const e = Math.min(img.width, img.height), cv = document.createElement('canvas'); cv.width = cv.height = 480;
    cv.getContext('2d').drawImage(img, (img.width - e) / 2, (img.height - e) / 3, e, e, 0, 0, 480, 480); URL.revokeObjectURL(url);
    const data = cv.toDataURL('image/jpeg', .8), s = studentById(sid);
    toast('Saving the photo…');
    const r = await api('teacher_api.php?a=photo', { sid, data });
    const small = document.createElement('canvas'); small.width = small.height = 112; small.getContext('2d').drawImage(cv, 0, 0, 112, 112);
    (PHOTOS[s.cls] = PHOTOS[s.cls] || {})[sid] = { h: r.h, src: small.toDataURL('image/jpeg', .78) }; IDB.set('photos:' + s.cls, PHOTOS[s.cls]);
    toast('Photo saved for ' + s.name + '.'); render();
  } catch (e) { toast(e.message); }
}
// Calendar: the whole month, every day coloured; tap a day for its choices; "Select days" marks many holidays at once.
function vAttCal(c) {
  const t = todayISO(), ym = P.ym || ymOf(t), list = studentsOf(c);
  if (!MONTHS[c + '|' + ym]) { ensureMonth(c, ym).then(render).catch(e => { MONTHS[c + '|' + ym] = true; toast(e.message); render(); }); return '<div class="load"><i></i>Loading ' + esc(ymName(ym)) + '…</div>'; }
  const sel = DRAFT.calSel, first = ym + '-01', off = new Date(first + 'T00:00:00').getDay();
  const cells = Array.from({ length: off }, () => '<span></span>');
  let saved = 0, pend = 0, hols = 0;
  for (let n = 1; n <= daysIn(ym); n++) {
    const d = ym + '-' + pad(n), sun = dayIdx(d) < 0, h = holidayOn(d) || clsHolOn(d, c), a = attOf(d, c), fut = d > t;
    let cls = 'calendar-day', txt = '';
    if (sun) { cls += ' sun'; txt = 'Sun'; } else if (h) { cls += ' holiday'; txt = 'Holiday'; hols++; } else if (a) { cls += ' saved'; txt = Math.round((list.length - (a.absent || []).length) / Math.max(1, list.length) * 100) + '%'; saved++; } else if (fut) { cls += ' upcoming'; txt = ''; } else { cls += ' pending'; txt = 'Mark'; pend++; }
    if (d === t) cls += ' today'; if (sel && sel[d]) cls += ' picked';
    const can = !sun && (sel ? !holidayOn(d) && d <= addDays(t, 90) : !fut || !h);
    cells.push(`<button type="button" class="${cls}" ${can ? '' : 'disabled'} aria-pressed="${d === (P.date || t)}" aria-label="${esc(fmtDate(d) + ' · ' + (h ? h.title : txt || 'Upcoming'))}" onclick="calTap(${jsq(c)},'${d}')"><b>${n}</b><small>${esc(txt)}</small></button>`);
  }
  const nSel = sel ? Object.keys(sel).filter(d => sel[d]).length : 0;
  return `<div class="card attendance-calendar cal-big"><div class="row"><button class="btn s w" onclick="go('att',{c:${jsq(c)},m:'cal',ym:'${ymAdd(ym, -1)}'})" aria-label="Previous month">${ic('back')}</button><b>${ymName(ym)}</b><button class="btn s w" ${ym >= ymOf(addDays(t, 60)) ? 'disabled' : ''} onclick="go('att',{c:${jsq(c)},m:'cal',ym:'${ymAdd(ym, 1)}'})" aria-label="Next month">${ic('next')}</button></div>
      <div class="cal-sum"><span class="pill ok">${saved} saved</span><span class="pill warn">${pend} to mark</span><span class="pill hol">${hols} holiday</span></div>
      <div class="calendar-grid">${['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'].map(d => `<span class="calendar-label">${d}</span>`).join('')}${cells.join('')}</div>
      <p class="mut" style="margin:8px 0 0">${sel ? 'Tap the days to close for this class, then choose the reason.' : 'Tap a day to take attendance or mark a holiday. Sundays and school holidays are automatic.'}</p>
      <div class="btns" style="margin-top:8px">${sel ? `<button class="btn w s" onclick="DRAFT.calSel=null;render()">Cancel</button>` : `<button class="btn l s" onclick="DRAFT.calSel={};render()">Select days for a holiday</button><button class="btn w s" onclick="go('att',{c:${jsq(c)},m:'pending'})">Days not marked</button>`}</div></div>
    ${sel && nSel ? `<div class="card"><h3>Holiday for ${nSel} day(s) <small>Class ${esc(c)}</small></h3>${holReasons()}<button class="btn go full" style="margin-top:8px" onclick="calHolSave(${jsq(c)})">Mark ${nSel} day(s) as holiday</button></div>` : ''}`;
}
function holReasons() {
  return `<div class="fld">Reason<input id="hT" class="inp" maxlength="120" placeholder="e.g. Class picnic, local holiday, exam day off"></div>
    <div class="chips" style="margin-top:6px">${['Local holiday', 'Exam day off', 'Class trip', 'Rain / weather', 'Festival', 'Function in school'].map(x => `<button class="chip" onclick="$('hT').value=${jsq(x)}">${x}</button>`).join('')}</div>`;
}
function calTap(c, d) {
  if (DRAFT.calSel) { DRAFT.calSel[d] = !DRAFT.calSel[d]; return render(); }
  const a = attOf(d, c), h = clsHolOn(d, c), sh = holidayOn(d), fut = d > todayISO(), list = studentsOf(c);
  SHEET = () => `<h2>${esc(fmtLong(d))}</h2><p class="mut" style="margin:0">Class ${esc(c)} · ${sh ? 'School holiday: ' + esc(sh.title) : h ? 'Class holiday: ' + esc(h.title) : a ? 'Attendance saved — ' + (list.length - (a.absent || []).length) + '/' + list.length + ' present' : fut ? 'Upcoming day' : 'Attendance not marked yet'}</p>
    ${!sh && !h && !fut ? `<button class="btn go full" onclick="SHEET=null;DRAFT.att=null;go('att',{c:${jsq(c)},m:'day',date:'${d}'})">${a ? 'Edit attendance' : 'Take attendance'}</button>` : ''}
    ${h ? `<button class="btn w full" onclick="setClsHol(${jsq(c)},'${d}',false)">Remove class holiday</button>` : !sh ? `<div class="card" style="box-shadow:none">${holReasons()}<button class="btn l full" style="margin-top:8px" onclick="setClsHol(${jsq(c)},'${d}',true,$('hT').value)">Mark holiday for this class</button>${a ? '<p class="mut">The attendance saved for this day will be removed.</p>' : ''}</div>` : ''}`;
  render();
}
async function calHolSave(c) {
  const days = Object.keys(DRAFT.calSel || {}).filter(d => DRAFT.calSel[d]).sort(), title = ($('hT') || {}).value || '';
  if (days.some(d => attOf(d, c)) && !confirm('Some of these days already have attendance. It will be removed. Continue?')) return;
  let n = 0;
  for (const d of days) { try { await api('teacher_api.php?a=holiday', { cls: c, date: d, on: true, title }); setRec('clsHol', d + '|' + c, { cls: c, date: d, title: title.trim() || 'Holiday', by: myName(), at: Date.now() }); if (S.studAtt[d]) delete S.studAtt[d][c]; n++; } catch (e) { toast(e.message); break; } }
  DRAFT.calSel = null; needStats(c, true); toast(n + ' day(s) marked as holiday.'); render();
}
function stOf(saved, list) { const st = {}; list.forEach(s => st[s.id] = 'P'); if (saved) (saved.absent || []).forEach(id => st[id] = ((saved.st || {})[id] === 'L') ? 'L' : 'A'); return st; }
function attTap(id) { const o = DRAFT.att.st[id]; attSet(id, o === 'P' ? 'A' : o === 'A' ? 'L' : 'P'); }
function rollQuick() {
  const c = DRAFT.att.key.slice(10), list = studentsOf(c), want = String(($('rollQ') || {}).value || '').split(/[^0-9]+/).filter(Boolean);
  if (!want.length) return toast('Type the roll numbers of the absent children, e.g. 3, 7, 12.');
  let n = 0; const miss = [];
  want.forEach(w => { const i = list.findIndex((s, j) => String(s.rollno || (j + 1)) === String(+w)); if (i < 0) miss.push(w); else { DRAFT.att.st[list[i].id] = 'A'; n++; } });
  DRAFT.att.changed = true; render();
  toast(n + ' marked absent.' + (miss.length ? ' Not found: ' + miss.join(', ') + '.' : '') + ' Check, then Save.');
}
function attSet(id, k) { DRAFT.att.st[id] = k; DRAFT.att.changed = true; render(); }
function attAll(k) { Object.keys(DRAFT.att.st).forEach(id => DRAFT.att.st[id] = k); DRAFT.att.changed = true; render(); }
function attRecord(st) {
  const absent = Object.keys(st).filter(id => st[id] === 'A' || st[id] === 'L'), L = {};
  Object.keys(st).forEach(id => { if (st[id] === 'L') L[id] = 'L'; });
  return { absent, ...(Object.keys(L).length ? { st: L } : {}), by: myName(), at: Date.now() };
}
function attSave(c, date) {
  const rec = attRecord(DRAFT.att.st), list = studentsOf(c), prev = attOf(date, c);
  const names = rec.absent.map(id => (studentById(id) || {}).name).filter(Boolean);
  put('studAtt', date + '|' + c, rec, 'Student Attendance', `Class ${c} (${date}): ${list.length - names.length}/${list.length} present` + (names.length ? '. Absent/leave: ' + names.join(', ') : '') + ' — Teacher App');
  LAST_SUB = { key: date + '|' + c, c, date, prev: prev ? JSON.parse(JSON.stringify(prev)) : null, at: Date.now() };
  DRAFT.att = null; needStats(c, true); toast('Attendance saved.'); render();
  const sc = document.querySelector('.scroll'); if (sc) sc.scrollTop = 0;
}
// Undo a Submit made by mistake (10 minutes): the earlier copy comes back, or the day becomes "not marked" again.
function attUndo() {
  const L = LAST_SUB; if (!L) return;
  if (L.prev) put('studAtt', L.key, L.prev, 'Student Attendance', `Class ${L.c} (${L.date}): last change undone — Teacher App`);
  else putDel('studAtt', L.key, 'Student Attendance', `Class ${L.c} (${L.date}): attendance undone (submitted by mistake) — Teacher App`);
  LAST_SUB = null; DRAFT.att = null; needStats(L.c, true); toast('Undone.'); render();
}
function threeDays(c, date) {
  const days = Object.keys(S.studAtt).filter(d => d <= date && S.studAtt[d][c]).sort().slice(-3); if (days.length < 3) return '';
  const out = studentsOf(c).filter(s => days.every(d => (S.studAtt[d][c].absent || []).includes(s.id) && (S.studAtt[d][c].st || {})[s.id] !== 'L'));
  return out.length ? `<div class="ban bad"><div><b>Absent 3 school days in a row:</b> ${out.map(s => esc(s.name)).join(', ')} — please call the parents.</div></div>` : '';
}

// ---------------------------------------------------------------- DAILY CHECK (owner, 5 Nov 2026)
// "dairy id book dress fill karne ka option — jis din ka data fill ho us din ka data entry ho".
// Class teacher, any day up to today: for each child Diary · ID card · Book · Dress (green = has it, red = missing).
// Saved per day and class (teacher_api.php?a=kit, kind tchKit). Last 30 days' misses show under each name.
const KIT_F = [['d', 'Diary'], ['i', 'ID card'], ['b', 'Book'], ['u', 'Dress']], KIT = {};
function vKit(c) {
  const t = todayISO(), date = P.date && P.date <= t ? P.date : t, key = date + '|' + c, list = studentsOf(c), ph = needPhotos(c);
  const nav = `<div class="datebar"><button class="btn s w" onclick="DRAFT.kit=null;go('att',{c:${jsq(c)},m:'kit',date:'${addDays(date, -1)}'})" aria-label="Previous day">${ic('back')}</button>
    <label class="dpick"><b>${date === t ? 'Today' : fmtDate(date)}</b><small>${esc(fmtLong(date))}</small><input type="date" max="${t}" value="${date}" onchange="DRAFT.kit=null;go('att',{c:${jsq(c)},m:'kit',date:this.value})"></label>
    <button class="btn s w" ${date >= t ? 'disabled' : ''} onclick="DRAFT.kit=null;go('att',{c:${jsq(c)},m:'kit',date:'${addDays(date, 1)}'})" aria-label="Next day">${ic('next')}</button></div>`;
  if (dayIdx(date) < 0) return nav + '<div class="ban hol">Sunday — no school.</div>';
  if (holidayOn(date) || clsHolOn(date, c)) return nav + `<div class="ban hol">Holiday — ${esc((holidayOn(date) || clsHolOn(date, c)).title)}. No daily check.</div>`;
  if (!KIT[key]) { KIT[key] = { loading: true }; api('teacher_api.php?a=kit&cls=' + encodeURIComponent(c) + '&date=' + date).then(r => { KIT[key] = r; render(); }).catch(e => { KIT[key] = { err: e.message }; render(); }); }
  const K = KIT[key];
  if (K.loading) return nav + '<div class="load"><i></i></div>';
  if (K.err) return nav + `<div class="card">${esc(K.err)} <button class="btn s l" onclick="delete KIT['${esc(key)}'];render()">Try again</button></div>`;
  const att = attOf(date, c), away = new Set((att && att.absent) || []);
  if (!DRAFT.kit || DRAFT.kit.key !== key) { const rows = {}; list.forEach(s => { const o = (K.rec && K.rec.rows || {})[s.id]; rows[s.id] = o ? Object.assign({ d: 1, i: 1, b: 1, u: 1 }, o) : { d: 1, i: 1, b: 1, u: 1 }; }); DRAFT.kit = { key, rows, changed: false }; }
  const R = DRAFT.kit.rows, here = list.filter(s => !away.has(s.id)), miss = f => here.filter(s => !R[s.id][f]).length;
  const full = here.filter(s => KIT_F.every(([f]) => R[s.id][f])).length;
  return nav + `<div class="card kit-sum"><div class="row"><div><small>Class ${esc(c)} · ${here.length} present${away.size ? ' · ' + away.size + ' absent (left out)' : ''}</small><b>Daily check</b></div>${K.rec ? `<span class="pill ok">Saved ${esc(fmtTime(K.rec.at))}</span>` : '<span class="pill warn">Not saved yet</span>'}</div>
      <div class="kit-grid">${KIT_F.map(([f, l]) => `<div class="${miss(f) ? 'm' : ''}"><b>${miss(f)}</b><span>${l} missing</span></div>`).join('')}</div>
      <p class="mut" style="margin:6px 0 0">${full} of ${here.length} children have everything today.</p></div>
    <div class="card"><p class="mut hint" style="margin:0 0 8px">Everything is green (has it). Tap only what a child did <b>not</b> bring — it turns red.</p>
      <div class="kit-list">${list.map(s => { const r = R[s.id], m = (K.miss || {})[s.id] || {}, gone = away.has(s.id), pic = ph[s.id], mt = KIT_F.filter(([f]) => m[f]).map(([f, l]) => l + ' ' + m[f]).join(' · ');
        return `<div class="kit-row ${gone ? 'gone' : ''}"><span class="ph sm">${pic ? `<img src="${esc(pic.src)}" alt="" loading="lazy">` : `<i>${esc(initials(s.name))}</i>`}</span>
          <div class="n"><b>${esc(s.name)}</b><small>${gone ? 'Absent today' : mt ? 'Missed in 30 days: ' + esc(mt) : 'Roll ' + esc(s.rollno || '')}</small></div>
          ${gone ? '' : `<div class="kit-btns">${KIT_F.map(([f, l]) => `<button type="button" class="${r[f] ? 'y' : 'x'}" aria-label="${esc(s.name)} ${l}: ${r[f] ? 'has it' : 'missing'}" onclick="kitTap('${esc(s.id)}','${f}')">${r[f] ? '✓' : '✕'}<small>${l.split(' ')[0]}</small></button>`).join('')}</div>`}</div>`; }).join('') || '<div class="empty">No students in this class yet.</div>'}</div>
      ${list.length ? `<div class="sticky"><div class="btns"><button class="btn w s" onclick="kitAll()">Everyone has everything</button></div><button class="btn go full submit" style="margin-top:8px" onclick="kitSave(${jsq(c)},'${date}')">${K.rec ? 'Update' : 'Save'} daily check · ${KIT_F.map(([f, l]) => miss(f) + ' ' + l.split(' ')[0]).join(' · ')}</button></div>` : ''}</div>
    ${K.rec && here.some(s => KIT_F.some(([f]) => !R[s.id][f])) ? `<div class="card"><h3>Tell the parents <small>things missing</small></h3>${here.filter(s => KIT_F.some(([f]) => !R[s.id][f])).map(s => { const lack = KIT_F.filter(([f]) => !R[s.id][f]).map(([, l]) => l), msg = `Dear Parent,\n${s.name} (Class ${c}) did not bring: ${lack.join(', ')} on ${fmtLong(date)}. Kindly make sure it is brought daily.\n${s.name} आज ${lack.map(x => ({ Diary: 'डायरी', 'ID card': 'आई-कार्ड', Book: 'किताब', Dress: 'ड्रेस' }[x])).join(', ')} नहीं लाया/लाई। कृपया रोज़ भेजें।\n— ${myName()}, ${S.settings.school || ''}`;
      return `<div class="li"><div class="t"><b>${esc(s.name)}</b><span>${esc(lack.join(', '))}</span></div>${s.phone ? `<a class="btn wa s" target="_blank" rel="noopener" href="${wa(s.phone, msg)}">${ic('wa')} Send</a>` : '<span class="mut">no phone</span>'}</div>`; }).join('')}</div>` : ''}`;
}
function kitTap(sid, f) { const r = DRAFT.kit.rows[sid]; r[f] = r[f] ? 0 : 1; DRAFT.kit.changed = true; render(); }
function kitAll() { Object.values(DRAFT.kit.rows).forEach(r => KIT_F.forEach(([f]) => r[f] = 1)); DRAFT.kit.changed = true; render(); }
async function kitSave(c, date) {
  const away = new Set(((attOf(date, c) || {}).absent) || []), rows = {};
  Object.entries(DRAFT.kit.rows).forEach(([sid, r]) => { if (!away.has(sid)) rows[sid] = r; });
  try { const r = await api('teacher_api.php?a=kit', { cls: c, date, rows }); const key = date + '|' + c; KIT[key] = Object.assign({}, KIT[key], { rec: r.rec }); DRAFT.kit.changed = false; delete STARS[c]; MYX = null; toast('Daily check saved.'); render(); }
  catch (e) { toast(e.message); }
}

// ---------------------------------------------------------------- STUDENTS: record + full report (owner, 5 Nov 2026)
// "record mention karne ka format — diary nahi hai, ID card nahi liya, WhatsApp student record add nahi kiya" and
// "class teacher ko total student report — complaint se lekar achhi baatein, comparison mein bachcha kya hai, learning, reading".
// One list of the class with each child's numbers next to the class average; tap a child for the full report:
// comparison bars, reading/learning levels, record checklist, complaints & good things (any teacher of the class can add).
const REP = {};
const REC_F = [['diary', 'Diary given'], ['id', 'ID card given'], ['book', 'Books complete'], ['dress', 'Dress / uniform'], ['wa', 'WhatsApp no. in record'], ['waGroup', 'In class WhatsApp group']];
const REC_NO = { diary: 'Diary nahi', id: 'ID card nahi', book: 'Books incomplete', dress: 'No dress', wa: 'WhatsApp missing', waGroup: 'Not in WA group' };
const LV_F = [['read', 'Reading'], ['write', 'Writing'], ['learn', 'Understanding'], ['speak', 'Speaking'], ['beh', 'Behaviour']];
const LV_N = ['', 'Needs help', 'Average', 'Good', 'Excellent'];
const RM_CAT = { bad: ['Homework', 'Diary / ID card', 'Behaviour', 'Discipline', 'Late coming', 'Uniform', 'Reading', 'Class work'], good: ['Class work', 'Reading', 'Writing', 'Helping others', 'Behaviour', 'Sports', 'Test', 'Neat copy'] };
const RM_TXT = { bad: ['Did not bring the diary.', 'ID card not taken yet.', 'Homework not done.', 'Talks in class.', 'Came late.', 'Not in proper uniform.', 'Copy incomplete.', 'Fought with a classmate.'],
  good: ['Read aloud very well.', 'Answered every question.', 'Very neat copy.', 'Helped a classmate.', 'Full marks in the class test.', 'Good behaviour today.', 'Did extra work.', 'Won in sports.'] };
function needRep(c, force) {
  if (REP[c] && !force) return REP[c];
  REP[c] = Object.assign({}, REP[c] || {}, { loading: !REP[c] || !REP[c].per });
  api('teacher_api.php?a=report&cls=' + encodeURIComponent(c)).then(r => { REP[c] = r; render(); }).catch(e => { REP[c] = { err: e.message }; render(); });
  return REP[c];
}
const pc = (a, b) => b ? Math.round(a / b * 100) : null;
function stuNums(R, s) {
  const x = (R.per || {})[s.id] || { a: 0, l: 0, hw: 0, half: 0, hwOf: 0, kit: 0, kitOf: 0, miss: {} };
  const att = R.attDays ? pc(R.attDays - x.a - x.l, R.attDays) : null, hw = x.hwOf ? Math.round((x.hw + x.half / 2) / x.hwOf * 100) : null, kit = pc(x.kit, x.kitOf);
  const mk = (R.marks || {})[s.id] ?? null, rec = (R.recs || {})[s.id] || {}, rm = (R.remarks || []).filter(r => r.sid === s.id);
  const parts = [att, hw, kit, mk].filter(v => v !== null), score = parts.length ? Math.round(parts.reduce((n, v) => n + v, 0) / parts.length) : null;
  return { x, att, hw, kit, mk, rec, rm, good: rm.filter(r => r.type === 'good').length, bad: rm.filter(r => r.type === 'bad').length, score };
}
function avgOf(list, k) { const v = list.map(n => n[k]).filter(x => x !== null && x !== undefined); return v.length ? Math.round(v.reduce((a, b) => a + b, 0) / v.length) : null; }
function vStudents(c) {
  const R = needRep(c), list = studentsOf(c), ph = needPhotos(c);
  if (R.loading) return '<div class="load"><i></i>Making the class report…</div>';
  if (R.err) return `<div class="card">${esc(R.err)} <button class="btn s l" onclick="delete REP['${esc(c)}'];render()">Try again</button></div>`;
  const N = list.map(s => Object.assign({ s }, stuNums(R, s))), f = P.f || 'all', sort = P.sort || 'roll';
  const A = { att: avgOf(N, 'att'), hw: avgOf(N, 'hw'), kit: avgOf(N, 'kit'), mk: avgOf(N, 'mk') };
  const ranked = N.filter(n => n.score !== null).slice().sort((a, b) => b.score - a.score); ranked.forEach((n, i) => n.rank = i + 1);
  const gap = k => N.filter(n => n.rec[k] === 0).length, weak = N.filter(n => LV_F.some(([k]) => ((n.rec.levels || {})[k] || 9) === 1));
  const flt = { all: N, bad: N.filter(n => n.bad), good: N.filter(n => n.good), diary: N.filter(n => n.rec.diary === 0), id: N.filter(n => n.rec.id === 0), wa: N.filter(n => n.rec.wa === 0 || n.rec.waGroup === 0), weak, low: N.filter(n => n.att !== null && n.att < 75) };
  const shown = (flt[f] || N).slice().sort(sort === 'score' ? (a, b) => (b.score ?? -1) - (a.score ?? -1) : sort === 'bad' ? (a, b) => b.bad - a.bad : (a, b) => (+a.s.rollno || 999) - (+b.s.rollno || 999) || a.s.name.localeCompare(b.s.name));
  const bars = (v, a) => v === null ? '<i class="nb">—</i>' : `<i class="mb ${a !== null && v < a - 5 ? 'lo' : a !== null && v > a + 5 ? 'hi' : ''}" style="--w:${v}%"><b>${v}</b></i>`;
  const lvCount = k => [1, 2, 3, 4].map(v => N.filter(n => (n.rec.levels || {})[k] === v).length);
  const totalGood = (R.remarks || []).filter(r => r.type === 'good').length, totalBad = (R.remarks || []).filter(r => r.type === 'bad').length;
  return `<div class="card cls-rep"><h3>Class ${esc(c)} report <small>${list.length} children · since ${esc(fmtDate(R.from))}</small></h3>
      <div class="avg4">${[['Attendance', A.att], ['Homework', A.hw], ['Daily check', A.kit], ['Marks' + (R.exam ? ' · ' + R.exam : ''), A.mk]].map(([l, v]) => `<div><div class="ring sm" style="--p:${v ?? 0}"><span>${v === null ? '—' : v + '%'}</span></div><small>${esc(l)}</small></div>`).join('')}</div>
      <div class="rm-sum"><span class="pill bad">⚠ ${totalBad} complaint(s)</span><span class="pill ok">👍 ${totalGood} good thing(s)</span></div>
      <div class="gaps">${[['diary', 'Diary nahi'], ['id', 'ID card nahi'], ['book', 'Books incomplete'], ['dress', 'No dress'], ['wa', 'WhatsApp missing'], ['waGroup', 'Not in WA group']].map(([k, l]) => `<div class="${gap(k) ? 'm' : ''}"><b>${gap(k)}</b><span>${l}</span></div>`).join('')}</div>
      <div class="lvdist">${LV_F.slice(0, 3).map(([k, l]) => { const ct = lvCount(k), t = ct.reduce((a, b) => a + b, 0); return `<div><small>${l}</small><div class="lvbar">${t ? ct.map((n, i) => n ? `<i class="l${i + 1}" style="flex:${n}" title="${LV_N[i + 1]}: ${n}">${n}</i>` : '').join('') : '<i class="none">not filled yet</i>'}</div></div>`; }).join('')}
        <p class="mut" style="margin:2px 0 0"><span class="lvk l1"></span>Needs help <span class="lvk l2"></span>Average <span class="lvk l3"></span>Good <span class="lvk l4"></span>Excellent</p></div></div>
    <div class="chips">${[['all', 'All', N.length], ['bad', '⚠ Complaints', flt.bad.length], ['good', '👍 Good', flt.good.length], ['diary', 'Diary nahi', flt.diary.length], ['id', 'ID card nahi', flt.id.length], ['wa', 'WhatsApp', flt.wa.length], ['weak', 'Needs help', weak.length], ['low', 'Below 75%', flt.low.length]].map(([k, l, n]) => `<button class="chip ${f === k ? 'on' : ''}" onclick="go('att',{c:${jsq(c)},m:'stu',f:'${k}',sort:'${sort}'})">${l} <b>${n}</b></button>`).join('')}</div>
    <div class="row"><span class="mut">Sort</span>${seg([['roll', 'Roll no.'], ['score', 'Best first'], ['bad', 'Complaints']], sort, `(k=>go('att',{c:${jsq(c)},m:'stu',f:'${f}',sort:k}))`)}</div>
    <div class="card stu-list"><div class="stu-hd"><span></span><span>Att.</span><span>HW</span><span>Check</span><span>Marks</span></div>
      <div class="stu-hd avg"><span>Class average</span>${[A.att, A.hw, A.kit, A.mk].map(v => `<span>${v === null ? '—' : v}</span>`).join('')}</div>
      ${shown.map((n, i) => { const s = n.s, pic = ph[s.id], tags = [...REC_F.filter(([k]) => n.rec[k] === 0).map(([k]) => `<span class="pill bad">${REC_NO[k]}</span>`), n.bad ? `<span class="pill warn">⚠ ${n.bad}</span>` : '', n.good ? `<span class="pill ok">👍 ${n.good}</span>` : ''].join('');
        return `<button type="button" class="stu-r" style="--d:${Math.min(i, 14) * 30}ms" onclick="go('att',{c:${jsq(c)},m:'stu',sid:${jsq(s.id)}})"><span class="ph sm">${pic ? `<img src="${esc(pic.src)}" alt="" loading="lazy">` : `<i>${esc(initials(s.name))}</i>`}${n.rank && n.rank <= 3 ? `<em class="rk">${n.rank}</em>` : ''}</span>
          <span class="nm"><b>${esc(s.name)}</b><small>Roll ${esc(s.rollno || '—')}${n.rank ? ' · Rank ' + n.rank : ''}</small>${tags ? `<span class="tg">${tags}</span>` : ''}</span>
          ${bars(n.att, A.att)}${bars(n.hw, A.hw)}${bars(n.kit, A.kit)}${bars(n.mk, A.mk)}</button>`; }).join('') || '<div class="empty">Nobody in this list.</div>'}</div>
    <p class="mut">Green = above the class average, red = below. Numbers come from attendance, Check work, Daily check and the marks entered. Tap a child for the full report.</p>`;
}
function vStuReport(c, sid) {
  const R = needRep(c), s = studentById(sid), ph = needPhotos(c);
  if (!s) return '<div class="card">Student not found.</div>';
  if (R.loading) return '<div class="load"><i></i></div>';
  if (R.err) return `<div class="card">${esc(R.err)}</div>`;
  const list = studentsOf(c), N = list.map(x => Object.assign({ s: x }, stuNums(R, x))), n = N.find(x => x.s.id === sid);
  const ranked = N.filter(x => x.score !== null).sort((a, b) => b.score - a.score), rank = ranked.findIndex(x => x.s.id === sid) + 1;
  const A = { att: avgOf(N, 'att'), hw: avgOf(N, 'hw'), kit: avgOf(N, 'kit'), mk: avgOf(N, 'mk') };
  if (!DRAFT.rec || DRAFT.rec.sid !== sid) DRAFT.rec = { sid, r: JSON.parse(JSON.stringify(Object.assign({ levels: {} }, n.rec, { waNo: n.rec.waNo || String(s.phone || '').replace(/\D/g, '').slice(-10) }))), changed: false };
  if (!DRAFT.rm || DRAFT.rm.sid !== sid) DRAFT.rm = { sid, type: 'bad', cat: '' };
  const D = DRAFT.rec.r, can = R.canEdit, x = n.x, rm = n.rm, RMv = DRAFT.rm;
  const cmp = (l, v, a, sub) => `<div class="cmp"><div class="cl"><b>${l}</b><small>${sub || ''}</small></div><div class="cb"><div class="cme" style="--w:${v ?? 0}%"><span>${v === null ? 'not yet' : v + '%'}</span></div><div class="cav" style="--w:${a ?? 0}%"><span>Class ${a === null ? '—' : a + '%'}</span></div></div>
    <em class="${v === null || a === null ? '' : v >= a + 3 ? 'up' : v <= a - 3 ? 'dn' : 'eq'}">${v === null || a === null ? '' : v >= a + 3 ? '▲ above' : v <= a - 3 ? '▼ below' : '= same'}</em></div>`;
  const yn = (k, l) => `<div class="yn"><span>${l}</span><div><button type="button" class="${D[k] === 1 ? 'y' : ''}" ${can ? '' : 'disabled'} onclick="recSet('${k}',1)">Yes</button><button type="button" class="${D[k] === 0 ? 'x' : ''}" ${can ? '' : 'disabled'} onclick="recSet('${k}',0)">No</button></div></div>`;
  return `<button class="btn s w" style="align-self:flex-start" onclick="DRAFT.rec=null;go('att',{c:${jsq(c)},m:'stu'})">${ic('back')} All students</button>
    <div class="card sr-head"><span class="ph big">${ph[sid] ? `<img src="${esc(ph[sid].src)}" alt="">` : `<i>${esc(initials(s.name))}</i>`}</span>
      <div><b>${esc(s.name)}</b><small>Class ${esc(c)} · Roll ${esc(s.rollno || '—')}${s.father ? ' · ' + esc(s.father) : ''}</small>
      <div class="chips" style="margin-top:6px">${rank ? `<span class="pill">Rank ${rank} of ${ranked.length}</span>` : ''}${n.score !== null ? `<span class="pill ok">Overall ${n.score}%</span>` : ''}<span class="pill bad">⚠ ${n.bad}</span><span class="pill ok">👍 ${n.good}</span></div></div></div>
    ${stuProfile(s)}
    <div class="card"><h3>Compared with the class <small>since ${esc(fmtDate(R.from))}</small></h3>
      ${cmp('Attendance', n.att, A.att, R.attDays ? (R.attDays - x.a - x.l) + ' of ' + R.attDays + ' days' : '')}
      ${cmp('Homework', n.hw, A.hw, x.hwOf ? x.hw + ' done · ' + (x.not || 0) + ' not done' : 'no check yet')}
      ${cmp('Daily check', n.kit, A.kit, x.kitOf ? ([['d', 'diary'], ['i', 'ID'], ['b', 'book'], ['u', 'dress']].filter(([k]) => x.miss[k]).map(([k, l]) => l + ' ' + x.miss[k]).join(', ').replace(/^(?=.)/, 'missed: ') || 'never missed') : 'not filled yet')}
      ${cmp('Marks', n.mk, A.mk, R.exam || 'no marks yet')}</div>
    <div class="card"><h3>Learning & reading <small>${can ? 'tap to set' : 'class teacher sets'}</small></h3>
      ${LV_F.map(([k, l]) => `<div class="lv"><span>${l}</span><div class="lvb">${[1, 2, 3, 4].map(v => `<button type="button" class="l${v} ${(D.levels || {})[k] === v ? 'on' : ''}" ${can ? '' : 'disabled'} onclick="lvSet('${k}',${v})">${LV_N[v]}</button>`).join('')}</div></div>`).join('')}</div>
    <div class="card"><h3>Student record <small>${REC_F.filter(([k]) => D[k] === 0).length} not done</small></h3>
      ${REC_F.map(([k, l]) => yn(k, l)).join('')}
      <div class="fld" style="margin-top:8px">WhatsApp number<input id="recWa" class="inp" inputmode="numeric" maxlength="14" value="${esc(D.waNo || '')}" ${can ? '' : 'disabled'} oninput="DRAFT.rec.r.waNo=this.value;DRAFT.rec.changed=true" placeholder="10-digit number"></div>
      <div class="fld" style="margin-top:8px">Note<input id="recNote" class="inp" maxlength="500" value="${esc(D.note || '')}" ${can ? '' : 'disabled'} oninput="DRAFT.rec.r.note=this.value;DRAFT.rec.changed=true" placeholder="e.g. ID card photo given on 3 Nov, card pending"></div>
      ${n.rec.at ? `<p class="mut" style="margin:6px 0 0">Last saved by ${esc(n.rec.by || '')} · ${esc(fmtTime(n.rec.at))}</p>` : ''}
      ${can ? `<button class="btn go full" style="margin-top:8px" onclick="recSave(${jsq(c)},${jsq(sid)})">Save record & levels</button>` : ''}</div>
    <div class="card"><h3>Complaints & good things <small>${rm.length}</small></h3>
      <div class="seg rmt"><button class="${RMv.type === 'bad' ? 'on bad' : ''}" onclick="DRAFT.rm.type='bad';DRAFT.rm.cat='';render()">⚠ Complaint</button><button class="${RMv.type === 'good' ? 'on good' : ''}" onclick="DRAFT.rm.type='good';DRAFT.rm.cat='';render()">👍 Good thing</button></div>
      <div class="chips" style="margin-top:8px">${RM_CAT[RMv.type].map(x => `<button type="button" class="chip ${RMv.cat === x ? 'on' : ''}" onclick="DRAFT.rm.cat=${jsq(x)};render()">${esc(x)}</button>`).join('')}</div>
      <div class="chips phr" style="margin-top:6px">${RM_TXT[RMv.type].map(x => `<button type="button" class="chip" onclick="$('rmT').value=${jsq(x)};keepNote($('rmT'))">${esc(x)}</button>`).join('')}</div>
      <div class="send"><textarea id="rmT" data-keep class="inp" rows="2" maxlength="500" placeholder="${RMv.type === 'bad' ? 'What was the problem?' : 'What did the child do well?'}"></textarea><button class="btn ${RMv.type === 'bad' ? 'bad' : 'go'}" onclick="rmSave(${jsq(c)},${jsq(sid)})">Add</button></div>
      <div class="tl">${rm.map(r => `<div class="tli ${r.type}"><i>${r.type === 'good' ? '👍' : '⚠'}</i><div><b>${esc(r.text)}</b><small>${r.cat ? esc(r.cat) + ' · ' : ''}${esc(fmtDate(r.date))} · ${esc(r.by || '')}</small></div>${r.tid === R.me || isP() ? `<button class="x" aria-label="Delete" onclick="rmDel(${jsq(c)},${jsq(r.id)})">✕</button>` : ''}</div>`).join('') || '<div class="empty">Nothing written yet.</div>'}</div></div>
    <div class="btns">${s.phone ? `<a class="btn wa" target="_blank" rel="noopener" href="${wa(s.phone, stuReportText(c, s, n, A, rank, ranked.length))}">${ic('wa')} Send report to parent</a>` : ''}<button class="btn w" onclick="stuPrint(${jsq(c)},${jsq(sid)})">${ic('print')} Print</button></div>`;
}
function recSet(k, v) { DRAFT.rec.r[k] = DRAFT.rec.r[k] === v ? null : v; DRAFT.rec.changed = true; render(); }
function lvSet(k, v) { const l = DRAFT.rec.r.levels = DRAFT.rec.r.levels || {}; l[k] = l[k] === v ? 0 : v; DRAFT.rec.changed = true; render(); }
async function recSave(c, sid) {
  try { const r = await api('teacher_api.php?a=rec', { sid, rec: DRAFT.rec.r }); (REP[c].recs = REP[c].recs || {})[sid] = r.rec; DRAFT.rec = null; toast('Record saved.'); render(); }
  catch (e) { toast(e.message); }
}
async function rmSave(c, sid) {
  const text = (($('rmT') || {}).value || '').trim(); if (!text) return toast('Write what happened.');
  try { const r = await api('teacher_api.php?a=remark', { sid, type: DRAFT.rm.type, cat: DRAFT.rm.cat, text }); REP[c].remarks.unshift(r.remark); keepClear('rmT'); $('rmT').value = ''; delete STARS[c]; toast(DRAFT.rm.type === 'good' ? 'Good thing saved 👍' : 'Complaint saved.'); render(); }
  catch (e) { toast(e.message); }
}
async function rmDel(c, id) {
  if (!confirm('Delete this note?')) return;
  try { await api('teacher_api.php?a=delremark', { id }); REP[c].remarks = REP[c].remarks.filter(r => r.id !== id); render(); } catch (e) { toast(e.message); }
}
function stuReportText(c, s, n, A, rank, of) {
  const line = (l, v, a) => v === null ? '' : `\n${l}: ${v}% (class ${a ?? '—'}%)`;
  const lv = LV_F.filter(([k]) => (n.rec.levels || {})[k]).map(([k, l]) => `${l}: ${LV_N[n.rec.levels[k]]}`).join(', ');
  const good = n.rm.filter(r => r.type === 'good').slice(0, 3).map(r => '👍 ' + r.text).join('\n'), bad = n.rm.filter(r => r.type === 'bad').slice(0, 3).map(r => '• ' + r.text).join('\n');
  return `*Student report — ${s.name}*\nClass ${c}${rank ? ' · Rank ' + rank + ' of ' + of : ''}\n${line('Attendance', n.att, A.att)}${line('Homework', n.hw, A.hw)}${line('Diary/ID/Book/Dress', n.kit, A.kit)}${line('Marks', n.mk, A.mk)}` +
    (lv ? `\n\n${lv}` : '') + (good ? `\n\nGood things:\n${good}` : '') + (bad ? `\n\nPlease help with:\n${bad}` : '') + `\n\n— ${myName()}, Class Teacher, ${S.settings.school || ''}`;
}
function stuPrint(c, sid) {
  const R = REP[c], s = studentById(sid), list = studentsOf(c), N = list.map(x => Object.assign({ s: x }, stuNums(R, x))), n = N.find(x => x.s.id === sid);
  const A = { att: avgOf(N, 'att'), hw: avgOf(N, 'hw'), kit: avgOf(N, 'kit'), mk: avgOf(N, 'mk') };
  let p = document.querySelector('.prt'); if (!p) { p = document.createElement('div'); p.className = 'prt'; document.body.appendChild(p); }
  p.innerHTML = `<pre style="font:13px/1.6 Arial,sans-serif;white-space:pre-wrap">${esc(stuReportText(c, s, n, A, 0, 0).replace(/\*/g, ''))}\n\nRecord: ${REC_F.map(([k, l]) => l + ' ' + (n.rec[k] === 1 ? 'Yes' : n.rec[k] === 0 ? 'No' : '—')).join(' · ')}\n\nAll notes:\n${n.rm.map(r => (r.type === 'good' ? '[Good] ' : '[Complaint] ') + r.date + ' — ' + r.text + ' (' + (r.by || '') + ')').join('\n')}</pre>`;
  window.print();
}
function askHoliday(c, date) {
  SHEET = () => `<h2>Holiday for Class ${esc(c)}</h2><p class="mut" style="margin:0">${fmtLong(date)} — only this class. The day will not count as present or absent.${attOf(date, c) ? ' <b>The attendance saved for this day will be removed.</b>' : ''}</p>
    ${holReasons()}
    <button class="btn go full" onclick="setClsHol(${jsq(c)},'${date}',true,$('hT').value)">Mark holiday</button>`;
  render();
}
async function setClsHol(c, date, on, title) {
  try {
    await api('teacher_api.php?a=holiday', { cls: c, date, on, title: title || '' });
    if (on) { setRec('clsHol', date + '|' + c, { cls: c, date, title: (title || '').trim() || 'Holiday', by: myName(), at: Date.now() }); if (S.studAtt[date]) delete S.studAtt[date][c]; }
    else if (S.clsHol[date]) delete S.clsHol[date][c];
    DRAFT.att = null; SHEET = null; needStats(c, true); toast(on ? 'Holiday marked for Class ' + c + '.' : 'Holiday removed.'); render();
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
  const seg2 = seg([['write', 'Diary'], ['check', 'Check work']], P.m === 'check' ? 'check' : 'write', `(k=>go('diary',{date:${jsq(P.date || todayISO())},m:k}))`);
  return seg2 + (P.m === 'check' ? vCheck() : vDiaryWrite());
}
function vDiaryWrite() {
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
    ${optPick(date, b, e, id)}
    ${attLine(date, b.cls)}
    <div class="dform">
      <button type="button" class="btn l full auto-fill" ${signed ? 'disabled' : ''} onclick="diaryAutoFill('${date}',${b.bell},${jsq(b.cls)},${jsq(b.subj)})">✨ Auto fill from syllabus</button>
      <div class="fld">Topic taught *<input id="${id}-t" data-keep class="inp" maxlength="200" value="${esc(e.topic || '')}" placeholder="${nx ? 'Next chapter: ' + esc(nx) : 'What did you teach?'}">
        ${suggestionBox(id + '-t', sylKey(b.cls, b.subj), 'topic')}
        ${nx && !e.topic ? `<button class="chip" style="align-self:flex-start" onclick="$('${id}-t').value=${jsq(nx)};keepNote($('${id}-t'))">Use: ${esc(nx.slice(0, 40))}</button>` : ''}</div>
      <div class="fld">Class work <span class="tapnote">tap to add · you can still edit</span>${phraseChips(id, 'cw', nx)}${qPick(id, 'cw')}<textarea id="${id}-cw" data-keep data-cwfor="${id}" class="inp" rows="2" maxlength="500" placeholder="e.g. Q1–Q5 done in class copy">${esc(e.classwork || '')}</textarea></div>
      <div class="fld">Homework <span class="tapnote">tap to add · you can still edit</span>${phraseChips(id, 'h', nx)}${qPick(id, 'h')}<div id="${id}-idea" class="cw-ideas" data-key="${esc(sylKey(b.cls, b.subj))}" data-nx="${esc(nx || '')}"></div><textarea id="${id}-h" data-keep class="inp" rows="2" maxlength="500" placeholder="e.g. Ex 4.1 Q6–Q10">${esc(e.hw || '')}</textarea>${suggestionBox(id + '-h', sylKey(b.cls, b.subj), 'homework')}</div>
      <div class="fld">Homework time (minutes, whole class ≤ 45)<div class="chips">${[10, 15, 20, 30, 45].map(m => `<button type="button" class="chip" onclick="diaryMin('${id}',${m})">${m}</button>`).join('')}<input id="${id}-min" data-keep class="inp mini" type="number" inputmode="numeric" min="1" max="45" value="${e.hwMinutes || ''}" placeholder="min"></div></div>
      <div class="fld">Homework for<div class="chips"><input id="${id}-date" data-keep class="inp mini date" type="date" value="${esc(e.hwDate || nextSchoolDay(date, b.cls) || date)}"><button type="button" class="chip" onclick="homeworkNextDay('${id}','${date}',${jsq(b.cls)})">Next school day</button><button type="button" class="chip" onclick="$('${id}-date').value='${date}';keepNote($('${id}-date'))">Same day</button></div></div>
      <details class="more" ${e.books || e.revision ? 'open' : ''}><summary>More · books to bring, revision</summary>
        <div class="fld">Books / copies to bring<input id="${id}-books" data-keep class="inp" maxlength="300" value="${esc(e.books || '')}"></div>
        <div class="fld">Revision<input id="${id}-rev" data-keep class="inp" maxlength="400" value="${esc(e.revision || '')}"></div></details>
      ${nx && !b.arr ? `<label class="chk"><input id="${id}-c" data-keep type="checkbox"> Chapter “${esc(nx)}” finished today (ticks it in the syllabus)</label>` : ''}
      <label class="chk"><input id="${id}-v" data-keep type="checkbox" ${e.at ? 'checked' : ''}> I really taught this and checked the homework.</label>
      <button class="btn go full" ${signed ? 'disabled' : ''} onclick="diarySave('${date}',${b.bell})">Save diary</button>
    </div></div>`;
}
// Ready class-work / homework sentences (owner, 5 Nov 2026: "CW/HW bina suggestion ke mushkil hai").
// The chip shows a short label; tapping adds the full sentence with the chapter (topic box, else the next chapter).
const PHRASES = {
  cw: [['Explained with examples', 'Explained {ch} with examples.'], ['Read in class', 'Read {ch} in class.'], ['Q&A in class copy', 'Questions-answers of {ch} written in the class copy.'],
    ['Exercise on board', 'Exercise questions of {ch} solved on the board.'], ['Oral questions', 'Oral questions on {ch}.'], ['Diagram / map', 'Diagram / map work of {ch}.'], ['Class test', 'Short class test on {ch}.']],
  h: [['Learn Q&A', 'Learn the questions-answers of {ch}.'], ['Write answers', 'Write the answers of {ch} in the homework copy.'], ['Complete exercise', 'Complete the exercise of {ch}.'],
    ['Read again', 'Read {ch} again and underline the key words.'], ['Learn words', 'Learn the new words and spellings of {ch}.'], ['Practise diagram', 'Practise the diagram of {ch}.'], ['Revise for test', 'Revise {ch} for a test.']]
};
function phraseChips(id, kind, nx) {
  return `<div class="chips phr">${PHRASES[kind].map(([l], i) => `<button type="button" class="chip" onclick="phraseAdd('${id}','${kind}',${i},${jsq(nx || '')})">+ ${l}</button>`).join('')}${kind === 'h' ? `<button type="button" class="chip ghost" onclick="phraseNoHw('${id}')">No homework</button>` : ''}</div>`;
}
function phraseAdd(id, kind, i, nx) {
  const el = $(id + '-' + kind); if (!el) return;
  const ch = (($(id + '-t') || {}).value || '').trim() || nx || 'the chapter';
  const text = PHRASES[kind][i][1].replace('{ch}', ch);
  if (el.value.includes(text)) return;
  el.value = (el.value.trim() ? el.value.trim() + ' ' : '') + text; el.classList.remove('auto-filled'); void el.offsetWidth; el.classList.add('auto-filled'); keepNote(el);
  if (kind === 'h' && !$(id + '-min').value) { $(id + '-min').value = 15; keepNote($(id + '-min')); toast('Homework time set to 15 minutes. Change it if needed.'); }
}
function phraseNoHw(id) { ['h', 'min'].forEach(k => { const el = $(id + '-' + k); if (el) { el.value = ''; keepNote(el); } }); toast('No homework for this bell.'); }

// ---------------------------------------------------------------- CHECK WORK (homework + copy checking)
const CHK = {};
const HWS = { done: 'Done', half: 'Half', not: 'Not done' };
function chkKey(date, c, s) { return date + '|' + c + '|' + s; }
function dueHomework(date, c, s) {           // homework of this class+subject that was due on this day (any teacher's diary)
  const out = [];
  Object.entries(S.diary).forEach(([d, byT]) => { if (d >= date) return; Object.values(byT || {}).forEach(byB => Object.values(byB || {}).forEach(e => { if (e && e.cls === c && e.subj === s && e.hw && (e.hwDate || d) === date) out.push({ d, hw: e.hw }); })); });
  return out;
}
function vCheck() {
  const list = allocList(); if (!list.length) return '<div class="card empty">No subjects are given to you yet. The Principal sets them in the timetable.</div>';
  const t = todayISO(), date = P.date && P.date <= t ? P.date : t;
  const key = list.some(a => sylKey(a.c, a.s) === P.key) ? P.key : sylKey(list[0].c, list[0].s), [c, sb] = key.split('|');
  const k = chkKey(date, c, sb);
  const head = `${keyPick(list, key, `DRAFT.chk=null;go('diary',{m:'check',date:'${date}',key:this.value})`)}
    <div class="row"><button class="btn s w" onclick="DRAFT.chk=null;go('diary',{m:'check',key:${jsq(key)},date:'${addDays(date, -1)}'})">${ic('back')}</button>
      <input type="date" class="inp" style="flex:1" max="${t}" value="${date}" onchange="DRAFT.chk=null;go('diary',{m:'check',key:${jsq(key)},date:this.value})">
      <button class="btn s w" ${date >= t ? 'disabled' : ''} onclick="DRAFT.chk=null;go('diary',{m:'check',key:${jsq(key)},date:'${addDays(date, 1)}'})">${ic('next')}</button></div>`;
  if (!CHK[k]) { CHK[k] = { loading: true }; api(`teacher_api.php?a=checks&cls=${encodeURIComponent(c)}&subj=${encodeURIComponent(sb)}&date=${date}`).then(r => { CHK[k] = r; render(); }).catch(e => { CHK[k] = { err: e.message }; render(); }); }
  if (date < (loadedFrom || '') && !DAYS_LOADED[date]) ensureDate(date).then(render);
  const R = CHK[k];
  if (R.loading) return head + '<div class="load"><i></i></div>';
  if (R.err) return head + `<div class="card">${esc(R.err)} <button class="btn s l" onclick="delete CHK['${k}'];render()">Try again</button></div>`;
  const kids = studentsOf(c), ph = needPhotos(c), due = dueHomework(date, c, sb), att = attOf(date, c), absent = new Set((att && att.absent) || []);
  if (!DRAFT.chk || DRAFT.chk.k !== k) { const rows = {}; kids.forEach(x => { const o = ((R.rec || {}).rows || {})[x.id] || {}; rows[x.id] = { hw: o.hw || '', cp: o.cp || '' }; }); DRAFT.chk = { k, rows, changed: false }; }
  const rows = DRAFT.chk.rows, present = kids.filter(x => !absent.has(x.id));
  const n = h => present.filter(x => rows[x.id].hw === h).length, ncp = present.filter(x => rows[x.id].cp === 'ok').length;
  const saved = R.rec;
  return head + `<div class="card chk-card">
      <div class="due">${due.length ? `<b>Homework due today</b>${due.map(x => `<p>${esc(x.hw)} <small>given ${esc(fmtDate(x.d))}</small></p>`).join('')}` : `<b>No homework was due on this day</b><p class="mut">You can still check copies and class work.</p>`}</div>
      <div class="roll-head" style="margin-top:10px">${saved ? `<span class="pill ok">Saved ${esc(fmtTime(saved.at))}</span>` : '<span class="pill warn">Not saved yet</span>'}
        <div class="cnt"><span class="pill ok">${n('done')} done</span><span class="pill warn">${n('half')} half</span><span class="pill bad">${n('not')} not</span><span class="pill">${ncp} copies</span></div></div>
      <div class="btns" style="margin:8px 0"><button class="btn s l" onclick="chkAll('hw','done')">All homework done</button><button class="btn s l" onclick="chkAll('cp','ok')">All copies checked</button></div>
      <p class="mut hint">Homework: tap Done / Half / Not done. Copy: tap the book to mark it checked.</p>
      <div class="chk-list">${kids.map((x, i) => { const r = rows[x.id], ab = absent.has(x.id), pic = ph[x.id], m = (R.miss || {})[x.id] || 0;
        return `<div class="chk-row ${ab ? 'ab' : 'h-' + (r.hw || 'none')}"><span class="ph sm">${pic ? `<img src="${esc(pic.src)}" alt="" loading="lazy">` : `<i>${esc(initials(x.name))}</i>`}</span>
          <div class="n"><b>${esc(x.name)}</b><small>Roll ${esc(x.rollno || i + 1)}${m ? ` · <span class="lowp">${m} not done in 30 days</span>` : ''}</small></div>
          ${ab ? '<span class="pill bad">Absent</span>' : `<div class="hwb">${['done', 'half', 'not'].map(h => `<button type="button" class="${h} ${r.hw === h ? 'on' : ''}" aria-label="${esc(x.name)}: ${HWS[h]}" onclick="chkSet('${esc(x.id)}','hw','${h}')">${h === 'done' ? '✓' : h === 'half' ? '½' : '✗'}</button>`).join('')}
            <button type="button" class="cp ${r.cp === 'ok' ? 'on' : ''}" aria-label="${esc(x.name)}: copy checked" onclick="chkSet('${esc(x.id)}','cp','${r.cp === 'ok' ? 'not' : 'ok'}')">${ic('book')}</button></div>`}</div>`; }).join('')}</div>
      <div class="sticky"><button class="btn go full" onclick="chkSave(${jsq(c)},${jsq(sb)},'${date}')">Save checking · ${n('done')} done · ${n('not')} not done</button></div></div>
    ${chkNotify(c, sb, date, present.filter(x => rows[x.id].hw === 'not' || rows[x.id].hw === 'half'), due)}`;
}
function chkSet(id, f, v) { const r = DRAFT.chk.rows[id]; r[f] = r[f] === v && f === 'hw' ? '' : v; DRAFT.chk.changed = true; render(); }
function chkAll(f, v) { const k = DRAFT.chk.k, [date, c] = k.split('|'), att = attOf(date, c), ab = new Set((att && att.absent) || []); Object.keys(DRAFT.chk.rows).forEach(id => { if (!ab.has(id)) DRAFT.chk.rows[id][f] = v; }); DRAFT.chk.changed = true; render(); }
async function chkSave(c, sb, date) {
  const k = DRAFT.chk.k, rows = {}, due = dueHomework(date, c, sb);
  Object.entries(DRAFT.chk.rows).forEach(([id, r]) => { if (r.hw || r.cp) rows[id] = r; });
  if (!Object.keys(rows).length) return toast('Mark at least one child.');
  try { const r = await api('teacher_api.php?a=check', { date, cls: c, subj: sb, hw: due.map(x => x.hw).join(' | '), rows });
    CHK[k] = Object.assign({}, CHK[k], { rec: r.rec }); DRAFT.chk = null; MYX = null; toast('Checking saved.'); render(); }
  catch (e) { toast(e.message); }
}
function chkNotify(c, sb, date, kids, due) {
  if (!kids.length) return '';
  const hw = due.map(x => x.hw).join(' / ');
  return `<div class="card"><h3>Tell the parents <small>${kids.length} not done</small></h3>${kids.map(x => {
    const msg = `Dear Parent,\n${x.name} (Class ${c}) did not complete the ${sb} homework due on ${fmtLong(date)}${hw ? ':\n“' + hw + '”' : '.'}\nKindly help ${x.name} finish it today.\n${x.name} ने ${sb} का गृहकार्य पूरा नहीं किया। कृपया आज पूरा करवाएँ।\n— ${S.settings.school || ''}`;
    return `<div class="li"><div class="t"><b>${esc(x.name)}</b><span>${DRAFT.chk && DRAFT.chk.rows[x.id] && DRAFT.chk.rows[x.id].hw === 'half' ? 'Half done' : 'Not done'}${x.phone ? '' : ' · no phone saved'}</span></div>${x.phone ? `<a class="btn wa s" target="_blank" rel="noopener" href="${wa(x.phone, msg)}">${ic('wa')} Send</a>` : ''}</div>`; }).join('')}</div>`;
}
function diaryMin(id, m) { const el = $(id + '-min'); el.value = m; keepNote(el); }
// Optional bell (owner, 5 Nov 2026: "us din optional kar do"): 5th bell of 9th/10th is shared by Ankit Tripathi Sir and
// Prabhakar Sir — they decide on the day who takes which class. The teacher chooses the class and subject in the diary;
// the diary entry is saved with that class, so homework and syllabus go to the right class.
function optOf(date, b) {
  if (!b || !/\(optional\)/i.test(b.subj || '')) return null;
  const d = dayIdx(date); if (d < 0) return null;
  const classes = className().filter(c => { const a = cell(c, d, b.bell); return a && /\(optional\)/i.test(a.s); });
  const subs = [...new Set(classes.map(c => cell(c, d, b.bell).s.replace(/\(optional\)/i, '').split('/').map(x => x.trim()).filter(Boolean)).flat())];
  return classes.length ? { classes, subs } : null;
}
function optPick(date, b, e, id) {
  const o = optOf(date, b); if (!o) return '';
  const oc = e.cls && o.classes.includes(e.cls) ? e.cls : '', os = e.subj && o.subs.includes(e.subj) ? e.subj : '';
  const chip = (k, v, cur) => `<button type="button" class="chip ${v === cur ? 'on' : ''}" onclick="$('${id}-${k}').value=${jsq(v)};keepNote($('${id}-${k}'));this.parentNode.querySelectorAll('.chip').forEach(x=>x.classList.toggle('on',x===this))">${esc(k === 'oc' ? 'Class ' + v : v)}</button>`;
  return `<div class="ban inf" style="margin-bottom:10px"><div><b>Optional bell</b> — decide with the other teacher who takes which class today.
    <div class="chips" style="margin-top:6px">${o.classes.map(c => chip('oc', c, oc)).join('')}</div><div class="chips" style="margin-top:6px">${o.subs.map(x => chip('os', x, os)).join('')}</div>
    <input type="hidden" id="${id}-oc" data-keep value="${esc(oc)}"><input type="hidden" id="${id}-os" data-keep value="${esc(os)}"></div></div>`;
}
function diarySave(date, bell) {
  let b = myBells(date).find(x => x.bell === bell) || (P.add && P.add.bell === bell ? P.add : null); if (!b) return;
  const id = 'b' + bell, v = k => ($(id + '-' + k) || {}).value || '', topic = v('t').trim(), hw = v('h').trim(), min = Number(v('min')), hwDate = v('date');
  if (!topic) return toast('Write the topic taught.');
  if (!$(id + '-v').checked) return toast('Tick “I really taught this” first.');
  if (hw && (!Number.isInteger(min) || min < 1 || min > 45)) return toast('Write how many minutes the homework takes (1 to 45).');
  if (optOf(date, b)) { const oc = v('oc'), os = v('os'); if (!oc || !os) return toast('Choose which class and subject you took in this optional bell.'); b = Object.assign({}, b, { cls: oc, subj: os }); }
  const d = { cls: b.cls, subj: b.subj, topic: topic.slice(0, 200), hw: hw.slice(0, 500), classwork: v('cw').trim().slice(0, 500), books: v('books').trim().slice(0, 300), revision: v('rev').trim().slice(0, 400),
    hwMinutes: hw ? min : null, hwDate: hwDate || date, arr: !!b.arr, at: Date.now(), ...(b.extra ? { extra: true } : {}) };
  put('diary', date + '|' + me() + '|' + bell, d, 'Diary', `${BELLS[bell].n} bell, Class ${b.cls} ${b.subj}: "${topic}"` + (hw ? ` • HW: ${hw}` : '') + ' — Teacher App');
  const cb = $(id + '-c');
  if (cb && cb.checked) { const key = sylKey(b.cls, b.subj), y = JSON.parse(JSON.stringify(S.syl[key] || { chapters: [] })), ch = y.chapters.find(x => !x.done && x.n === nextChapter(b.cls, b.subj));
    if (ch) { ch.done = date; put('syl', key, y, 'Syllabus', `Class ${b.cls} ${b.subj}: chapter "${ch.n}" completed (from diary)`); } }
  keepClear(id + '-'); P = { date }; toast('Diary saved.'); render();
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
  return planSeg(m) + (m === 'syl' ? vSyl(list) : m === 'paper' ? vPaper(list) : vLp(list));
}
function planSeg(m) { return seg([['lp', 'Lesson plan'], ['syl', 'Syllabus'], ['notes', 'Notes'], ['paper', 'Paper']], m, `(k=>k==='notes'?go('notes',{key:${jsq(P.key || '')}}):go('plan',{m:k,key:${jsq(P.key || '')}}))`); }
function keyPick(list, key, onch) { return `<div class="fld">Class & subject${sel('kp', list.map(a => [sylKey(a.c, a.s), 'Class ' + a.c + ' — ' + a.s]), key, onch)}</div>`; }
function vLp(list) {
  const thisW = weekStart(todayISO()), w = P.w || thisW, key = list.some(a => sylKey(a.c, a.s) === P.key) ? P.key : sylKey(list[0].c, list[0].s), [c, s] = key.split('|');
  const k = [w, me(), c, s].join('|'), p = S.lplan[k], dr = DRAFT.lp && DRAFT.lp.k === k ? DRAFT.lp : null, nx = nextChapter(c, s);
  const weeks = [addDays(thisW, -7), thisW, addDays(thisW, 7), addDays(thisW, 14)];
  needChapTexts(key);
  const mine = Object.entries(S.lplan).filter(([kk, v]) => v.tid === me() && v.week === w);
  const daily = p && Object.prototype.hasOwnProperty.call(p, 'days');
  return `<div class="fld">Week${sel('lpW', weeks.map(x => [x, 'Week of ' + fmtDate(x)]), w, `go('plan',{m:'lp',w:this.value,key:${jsq(key)}})`)}</div>
    ${keyPick(list, key, `DRAFT.lp=null;go('plan',{m:'lp',w:'${w}',key:this.value})`)}
    <div class="card">${daily ? '<div class="ban inf">This week has a daily teaching plan. Open the web ERP → Daily Teaching to change it.</div>' : p && p.status === 'Approved' ? '<div class="ban ok">Approved by the Principal — locked.</div>' : `
      <div class="btns" style="margin-bottom:8px"><button class="btn s l" onclick="lpFill('${w}',${jsq(key)})">Auto fill from syllabus</button>
        <label class="btn s l filebtn">${ic('cam')} From a photo<input type="file" accept="image/*" capture="environment" multiple onchange="lpPhoto(this.files,${jsq(key)},'${k}')"></label></div>
      ${DRAFT.busy ? `<div class="ban inf"><span class="load" style="padding:0;flex-direction:row;gap:8px"><i style="width:18px;height:18px"></i></span>${esc(DRAFT.busy)}</div>` : ''}
      ${chapHint(key, nx, 'lesson plan')}
      <div class="fld">Topics — one line = one period${nx ? ` · next chapter: <b>${esc(nx)}</b>` : ''}<textarea id="lpT" class="inp" rows="7" oninput="DRAFT.lp={k:'${k}',topics:this.value,goal:$('lpG').value}">${esc(dr ? dr.topics : p ? p.topics : '')}</textarea>${suggestionBox('lpT', key, 'topic')}</div>
      <div class="fld">Aim / activity / teaching aid / homework<input id="lpG" class="inp" maxlength="400" value="${esc(dr ? dr.goal : p ? p.goal || '' : '')}" oninput="DRAFT.lp={k:'${k}',topics:$('lpT').value,goal:this.value}">${suggestionBox('lpG', key, 'goal')}</div>
      <button class="btn go full" style="margin-top:10px" onclick="lpSubmit('${w}',${jsq(key)})">${p ? 'Update and send again' : 'Send to Principal'}</button>`}
      ${p ? `<p class="mut" style="margin:8px 0 0">Status: <span class="pill ${p.status === 'Approved' ? 'ok' : p.status === 'Revise' ? 'bad' : 'warn'}">${esc(p.status)}</span>${p.feedback ? ' · Principal: ' + esc(p.feedback) : ''}</p>` : ''}</div>
    ${mine.length ? `<div class="card"><h3>My plans this week</h3>${mine.map(([kk, v]) => `<div class="li" onclick="DRAFT.lp=null;go('plan',{m:'lp',w:'${w}',key:${jsq(sylKey(v.cls, v.subj))}})"><div class="t"><b>Class ${esc(v.cls)} · ${esc(v.subj)}</b><span>${String(v.topics || '').split('\n').filter(Boolean).length} topic(s)${(v.days || []).length ? ' · ' + v.days.length + ' day(s)' : ''}</span></div><span class="pill ${v.status === 'Approved' ? 'ok' : v.status === 'Revise' ? 'bad' : 'warn'}">${esc(v.status)}</span></div>`).join('')}</div>` : ''}`;
}
function lpFill(w, key) {
  const [c, s] = key.split('|'), y = S.syl[key], a = S.alloc.find(x => x.c === c && x.s === s && x.t === me()) || S.alloc.find(x => x.c === c && x.s === s);
  if (!y || !(y.chapters || []).length) return toast('Add the chapters first: Plan → Syllabus (a photo of the contents page works).');
  const schoolDays = Array.from({length: 6}, (_, i) => addDays(w, i)).filter(d => isWorkDay(d, c));
  const scheduled = schoolDays.flatMap(d => myBells(d).filter(b => b.cls === c && b.subj === s));
  const n = S.grid ? scheduled.length : Math.min(schoolDays.length, Math.max(1, (a && +a.n) || 5));
  if (!n) return toast('No teaching periods this week. Sundays and saved holidays are skipped.');
  if ($('lpT').value.trim() && !confirm('Replace these topics with a syllabus draft?')) return;
  const earlier = new Set(Object.values(S.lplan).filter(p => p.tid === me() && p.cls === c && p.subj === s && p.week < w).flatMap(p => String(p.topics || '').split('\n').map(x => x.trim().toLowerCase())));
  const open = y.chapters.filter(ch => !ch.done).sort((x, z) => String(x.plan || '9999').localeCompare(String(z.plan || '9999'))), lines = [];
  for (const ch of open) { const tx = chapTopics(key, ch.n); const parts = (ch.topics || []).length ? ch.topics.map(t => `${ch.n} — ${t}`) : tx.length ? tx.map(t => `${ch.n} — ${t}`) : [`${ch.n} — introduction`, `${ch.n} — explanation`, `${ch.n} — examples and practice`, `${ch.n} — exercise / question-answer`, `${ch.n} — revision and test`];
    for (const l of parts) { if (!earlier.has(l.toLowerCase())) lines.push(l); if (lines.length >= n) break; } if (lines.length >= n) break; }
  if (!lines.length) return toast('Nothing left to plan — all chapters are done.');
  DRAFT.lp = { k: [w, me(), c, s].join('|'), topics: lines.join('\n'), goal: ($('lpG') || {}).value || '' }; toast(lines.length + ' period(s) filled. Sundays and saved holidays skipped. Review, then send.'); render();
  $('lpT').classList.add('auto-filled');
}
function lpSubmit(w, key) {
  const [c, s] = key.split('|'), k = [w, me(), c, s].join('|'), old = S.lplan[k];
  if (old && (old.status === 'Approved' || Object.prototype.hasOwnProperty.call(old, 'days'))) return toast('This plan is locked.');
  const topics = $('lpT').value.split('\n').map(x => x.trim()).filter(Boolean).join('\n').slice(0, 3000), goal = $('lpG').value.trim().slice(0, 400);
  if (!topics) return toast('Write at least one topic.');
  put('lplan', k, { week: w, tid: me(), cls: c, subj: s, topics, goal, status: 'Submitted', feedback: '', at: Date.now() }, 'Lesson Plan', `Submitted: Class ${c} ${s}, week of ${w} (${topics.split('\n').length} topics) — Teacher App`);
  DRAFT.lp = null; toast('Sent to the Principal.'); render();
}
// vSyl (Syllabus: books, chapter rows with pages, exam ticks, chapter text) lives in teacher-work.js (owner, 5 Nov 2026).
function sylCopy(key) { return JSON.parse(JSON.stringify(S.syl[key] || { chapters: [], targetCount: '', targetDate: '' })); }
function sylTick(key, i, on) { const y = sylCopy(key); y.chapters[i].done = on ? todayISO() : null; put('syl', key, y, 'Syllabus', `${key.replace('|', ' ')}: "${y.chapters[i].n}" ${on ? 'completed' : 'un-ticked'} — Teacher App`); render(); }
// ---------------------------------------------------------------- photos (AI first; the phone reads it if the school AI is off)
function shrink(file) {
  return new Promise((ok, bad) => { const img = new Image(), url = URL.createObjectURL(file);
    img.onload = () => { const m = 1600, r = Math.min(1, m / Math.max(img.width, img.height)), cv = document.createElement('canvas'); cv.width = Math.round(img.width * r); cv.height = Math.round(img.height * r);
      cv.getContext('2d').drawImage(img, 0, 0, cv.width, cv.height); URL.revokeObjectURL(url); let q = 0.82, out = cv.toDataURL('image/jpeg', q); while (out.length > 1900000 && q > 0.4) { q -= 0.12; out = cv.toDataURL('image/jpeg', q); } ok(out); };
    img.onerror = () => { URL.revokeObjectURL(url); bad(new Error('This photo could not be opened. Please take it again.')); }; img.src = url; });
}
const TESS_ROOT = new URL('vendor/tesseract/', document.baseURI).href;
const TESS_OPTIONS = {
  workerPath: TESS_ROOT + 'worker.min.js',
  corePath: TESS_ROOT,
  langPath: TESS_ROOT.slice(0, -1),
  workerBlobURL: false,
  gzip: false, // Apache denies direct requests for .gz files.
};
const TESS_ERROR = 'Phone photo nahi padh paaya — text type karein ya school AI chalu karwayein';
let TESS = null;
function tessLoad() {
  if (typeof window.Tesseract?.recognize === 'function') return Promise.resolve(window.Tesseract);
  if (!TESS) {
    TESS = new Promise((resolve, reject) => {
      const script = document.createElement('script');
      const fail = () => { script.remove(); reject(new Error(TESS_ERROR)); };
      script.src = TESS_ROOT + 'tesseract.min.js';
      script.onerror = fail;
      script.onload = () => {
        if (typeof window.Tesseract?.recognize !== 'function') return fail();
        resolve(window.Tesseract);
      };
      document.head.appendChild(script);
    }).catch(error => { TESS = null; throw error; });
  }
  return TESS;
}
async function readPhotos(mode, c, s, files, busyK) {
  files = [...(files || [])].slice(0, 4); if (!files.length) return null;
  const busy = m => { DRAFT.busy = m; DRAFT.busyK = busyK; render(); };
  try {
    busy('Preparing the photo…'); const images = []; for (const f of files) images.push(await shrink(f));
    let res = null;
    if (AI !== false) { busy('Reading the photo with AI… (10–40 seconds)'); try { res = await api('scan_api.php?a=read', { mode, cls: c, subj: s, images }); res.via = 'ai'; } catch (e) { if (!e.noAi) throw e; AI = false; toast(e.message); } }
    if (!res) { busy('Your phone is reading the photo… (the first time takes about a minute)'); const tess = await tessLoad(); let text = '';
      for (let i = 0; i < images.length; i++) { busy(`Your phone is reading photo ${i + 1} of ${images.length}…`); const r = await tess.recognize(images[i], 'eng+hin', TESS_OPTIONS); text += '\n' + ((r && r.data && r.data.text) || ''); }
      res = { via: 'phone', text }; }
    return res;
  } catch (e) { toast(e.message); return null; } finally { DRAFT.busy = null; render(); }
}
function planLines(text) { return [...new Set(String(text || '').split('\n').map(l => l.replace(/\s+/g, ' ').replace(/^(?:(?:chapter|ch\.?|lesson|unit|पाठ|अध्याय)\s*)?[(\[]?(?:\d{1,3}|[ivxlc]{1,6})[)\].:\-–—]*\s+/i, '').replace(/[\s.·…_\-–—]*\d{1,4}\s*$/, '').trim()).filter(l => l.length >= 3 && /[A-Za-zऀ-ॿ]{2}/.test(l) && !/^(contents?|index|page|विषय[ -]?सूची|अनुक्रमणिका|पृष्ठ)\b/i.test(l)))]; }
// Keep the printed label in the draft, including Roman pages and Hindi digits.
function sylPageLabel(v) {
  const p = String(v ?? '').trim().replace(/[०-९]/g, d => d.charCodeAt(0) - 0x966).toLowerCase();
  return /^(?:\d{1,5}|[ivxlcdm]{1,5})$/.test(p) ? p : '';
}
function sylHeading(n) {
  return /^(?:foreword|preface|about this book|contents?|table of contents|index|answer\s*keys?|glossary|appendix|acknowledg(?:e)?ments?|page(?: no\.?)?)(?=$|[\s:.\-–—])|^(?:introduction|chapter|ch\.?|unit|lesson|प्रस्तावना|आमुख|विषय[\s-]*सूची|उत्तरमाला|अनुक्रमणिका|पृष्ठ|अध्याय|पाठ|इकाई)$/iu.test(n);
}
function lines(text) {
  const rows = [];
  for (const raw of String(text || '').split('\n')) {
    const line = raw.trim().replace(/\s+/g, ' ');
    let n = line.replace(/^[\s&§#*”“¥|~•]+/u, '');
    let uncertain = n !== line;
    n = n.replace(/^(?:chapter|ch\.?|lesson|unit|पाठ|अध्याय|इकाई)\s*[(\[]?(?:[0-9०-९]{1,3}|[ivxlc]{1,5})(?:[\s)\].:\-–—]+|$)/iu, '')
      .replace(/^[(\[]?[0-9०-९]{1,3}[\s)\].:\-–—]+(?=.*\p{L})/u, '')
      .replace(/^[ivxlc]{1,5}[).:\-–—]+\s*(?=.*\p{L})/iu, '');
    const page = n.match(/(?:\s+|[.·…_\-–—]+)\s*([0-9०-९]{1,5}|[ivxlcdm]{1,5})(?:\s*[-–]\s*[0-9०-९]{1,5})?\s*$/iu);
    const pf = page ? sylPageLabel(page[1]) : '';
    if (page) n = n.slice(0, page.index);
    uncertain ||= /^[\s]*[&§#*”“¥|~•]/u.test(n);
    n = n.replace(/^[\s&§#*”“¥|~•]+|[\s.·…_:\-–—]+$/gu, '');
    // Short lowercase OCR fragments often precede a capitalized title (e.g. "f 3", "st", "hj").
    // Keep common opening words and flag every removal for review.
    const clean = n.replace(/^(?:(?!(?:a|an|i|the|in|on|of|to|at|by|for|how|why|my|our|we|you|let|is|are|can|do|as|and|all|one|two|who|he|she|it)\b)[a-z]{1,3}(?:\s+[0-9०-९]{1,3})?\s+)+(?=[\p{Lu}\p{Script=Devanagari}])/u, '');
    uncertain ||= clean !== n;
    n = clean;
    if (!/\p{L}/u.test(n) || sylHeading(n)) continue;
    rows.push({ n, pf, uncertain });
  }
  return rows;
}
function sylEndPage(from, next) {
  if (/^\d+$/.test(next)) {
    const end = Number(next) - 1;
    return end > 0 && (!from || (/^\d+$/.test(from) && end >= Number(from))) ? String(end) : '';
  }
  if (!next || (from && /^\d+$/.test(from))) return '';
  const roman = ['', 'i', 'ii', 'iii', 'iv', 'v', 'vi', 'vii', 'viii', 'ix'];
  const label = (n, one, five, ten) => roman[n].replace(/[ivx]/g, c => ({ i: one, v: five, x: ten })[c]);
  const pages = Array.from({ length: 100 }, (_, n) => label(Math.floor(n / 10), 'x', 'l', 'c') + roman[n % 10]);
  const end = pages.indexOf(next) - 1;
  return end > 0 && (!from || (pages.indexOf(from) > 0 && end >= pages.indexOf(from))) ? pages[end] : '';
}
async function sylPhoto(files, key) {
  const B = DRAFT.sb;
  if (!B || B.key !== key || DRAFT.busy) return;
  const [c, s] = key.split('|');
  const res = await readPhotos('chapters', c, s, files, key);
  if (!res || DRAFT.sb !== B) return;
  const found = res.via === 'ai' ? (res.chapters || []).map(ch => ({
    n: String(ch.name || '').trim(), pf: sylPageLabel(ch.page), topics: ch.topics, uncertain: res.uncertain
  })).filter(ch => ch.n && !sylHeading(ch.n)) : lines(res.text);
  const seen = new Set(B.rows.map(r => r.n.trim().toLowerCase()).filter(Boolean));
  const rows = [];
  for (const r of found.slice(0, 60)) {
    const n = r.n.slice(0, 120), id = n.toLowerCase();
    if (seen.has(id)) continue;
    seen.add(id);
    const short = (n.match(/\p{L}/gu) || []).length <= 3 && !/\p{Script=Devanagari}/u.test(n);
    rows.push({ ...r, n, pt: '', via: res.via, uncertain: r.uncertain || !r.pf || short || /[&§#*¥|~�]|(?:^|\s)[a-z](?=\s|$)/u.test(n) });
  }
  if (!rows.length) return toast('No new chapters found. Try a clearer photo or edit the rows.');
  const named = B.rows.filter(r => r.n.trim() || r.pf || r.pt);
  B.rows = named.concat(rows);
  sylInferPages(B.rows);
  render();
  document.querySelector('.syi.scan input')?.focus();
}
async function lpPhoto(files, key, k) { const [c, s] = key.split('|'); const res = await readPhotos('lplan', c, s, files, k); if (!res) return;
  const topics = res.via === 'ai' ? res.topics : planLines(res.text).slice(0, 12); if (!topics.length) return toast('Nothing readable was found. Try a clearer photo.');
  DRAFT.lp = { k, topics: topics.join('\n'), goal: res.via === 'ai' ? [res.goal, res.homework ? 'Homework: ' + res.homework : ''].filter(Boolean).join(' • ') : '' }; toast('Read from the photo. Check it, then send.'); render(); }

// ---------------------------------------------------------------- NOTES (chapter-wise, from photos of the book)
const NOTES = {};
function vNotes() {
  const list = allocList(); if (!list.length) return '<div class="card empty">No subjects are given to you yet.</div>';
  const key = list.some(a => sylKey(a.c, a.s) === P.key) ? P.key : sylKey(list[0].c, list[0].s), [c, s] = key.split('|');
  if (P.edit) return noteEditor(c, s);
  if (!P.id) { const body = vNotesList(list, key, c, s); return planSeg('notes') + body; }
  return vNotesList(list, key, c, s);
}
function vNotesList(list, key, c, s) {
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
    if (AI !== false) { busy('AI is reading the pages and writing notes… (20–60 seconds)'); try { res = await api('scan_api.php?a=read', { mode: 'notes', cls: c, subj: s, images: imgs }); } catch (e) { if (!e.noAi) throw e; AI = false; toast(e.message); } }
    if (res) { if (res.chapter && !n.chapter) n.chapter = res.chapter; n.body = res.summary || ''; n.points = res.points || []; n.words = res.words || []; n.qa = res.qa || []; n.via = 'ai'; }
    else { busy('The school AI is off — your phone is reading the text (about a minute)…'); const tess = await tessLoad(); let text = '';
      for (let i = 0; i < imgs.length; i++) { busy(`Your phone is reading page ${i + 1} of ${imgs.length}…`); const r = await tess.recognize(imgs[i], 'eng+hin', TESS_OPTIONS); text += ((r && r.data && r.data.text) || '') + '\n'; }
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
      <div class="mk-prog"><div class="bar"><i id="mkBar"></i></div><span id="mkProg"></span></div>
      <p class="mut" style="margin:0 0 8px">Write AB for absent. Total and grade come by themselves. What you type stays on this phone until you save.</p>
      <div class="mk">${d.students.map((s, i) => { const r = rows[s.id] || {}; return `<div class="mkc"><div class="mkh"><span class="rn">${esc(s.rollno || i + 1)}</span><b>${esc(s.name)}</b><span class="tot" id="tot-${esc(s.id)}">${mkSum(r)}</span></div>
        <div class="mkg">${PARTS.map(([p, l, mx]) => `<label><small>${l} <i>/${mx}</i></small><input id="mk-${esc(s.id)}-${p}" data-keep inputmode="decimal" enterkeyhint="next" autocomplete="off" data-s="${esc(s.id)}" data-p="${p}" data-max="${mx}" value="${esc(r[p] ?? '')}" oninput="mkTot(this)" onkeydown="if(event.key==='Enter'){event.preventDefault();mkNext(this)}"></label>`).join('')}</div></div>`; }).join('')}</div>
      <div class="sticky"><button class="btn go full" onclick="mkSave('${ses}','${exam}',${jsq(c)},${jsq(subj)},'${k}')">Save ${esc(subj)} marks</button></div></div>
    <a class="btn w full" target="_blank" rel="noopener" href="progress.php?tab=card&exam=${encodeURIComponent(exam)}">${ic('print')} Printed report cards (web ERP)</a>`;
}
function mkNext(inp) { const all = [...document.querySelectorAll('.mk input')], i = all.indexOf(inp); if (all[i + 1]) { all[i + 1].focus(); all[i + 1].select(); } }
function mkProgress() {
  const by = {}; document.querySelectorAll('.mk input').forEach(x => { (by[x.dataset.s] = by[x.dataset.s] || []).push(x.value.trim() !== ''); });
  const all = Object.keys(by).length, done = Object.values(by).filter(a => a.every(Boolean)).length;
  if ($('mkProg')) $('mkProg').textContent = done + ' of ' + all + ' students filled';
  if ($('mkBar')) $('mkBar').style.width = (all ? Math.round(done / all * 100) : 0) + '%';
}
function num(v) { return v === '' || v == null || String(v).toUpperCase() === 'AB' ? 0 : Number(v); }
function mkSum(r) { const any = PARTS.some(([p]) => String(r[p] ?? '') !== ''); if (!any) return ''; const t = PARTS.reduce((a, [p]) => a + num(r[p]), 0); return `${Math.round(t * 100) / 100} <span class="g${grade(t)}">${grade(t)}</span>`; }
function mkTot(inp) { const v = inp.value.trim().toUpperCase(); inp.classList.toggle('bad', v !== '' && v !== 'AB' && !(/^\d{1,3}(\.\d{1,2})?$/.test(v) && +v <= +inp.dataset.max));
  const r = {}; document.querySelectorAll(`.mk input[data-s="${CSS.escape(inp.dataset.s)}"]`).forEach(x => r[x.dataset.p] = x.value.trim()); const t = $('tot-' + inp.dataset.s); if (t) t.innerHTML = mkSum(r); mkProgress(); }
async function mkSave(ses, exam, c, subj, k) {
  const rows = {}; let bad = 0;
  document.querySelectorAll('.mk input').forEach(x => { const v = x.value.trim().toUpperCase(); (rows[x.dataset.s] = rows[x.dataset.s] || {})[x.dataset.p] = v; if (x.classList.contains('bad')) bad++; });
  if (bad) return toast('Some marks are more than the maximum. Check the red boxes.');
  try { await api('progress_api.php?a=marks', { ses, exam, cls: c, subject: subj, rows }); keepClear('mk-'); delete MK[k]; toast(subj + ' marks saved. The result is updated.'); render(); } catch (e) { toast(e.message); }
}
function termOf(marks, sid, subjects) {
  const by = Object.fromEntries(Object.values(marks || {}).map(x => [x.subject, x]));
  const rows = subjects.map(s => { const m = ((by[s] || {}).rows || {})[sid] || {}; const t = PARTS.reduce((a, [p]) => a + num(m[p]), 0); const has = PARTS.some(([p]) => String(m[p] ?? '') !== ''); return { s, t, has }; });
  const sum = rows.reduce((a, r) => a + r.t, 0), max = rows.length * 100, p = max ? Math.round(sum / max * 10000) / 100 : 0;
  return { rows, sum, max, p, g: grade(p), any: rows.some(r => r.has), full: rows.every(r => r.has), miss: rows.filter(r => !r.has).map(r => r.s) };
}
function resSheet(d, ses, exam, c, final) {
  const subs = d.subjects; if (!subs.length) return '<div class="card empty">No subjects are set for this class.</div>';
  if (final && !d.other) return '<div class="empty">Choose Term-2 to see the final result.</div>';
  const entered = subs.filter(s => d.marks[`${ses}|${exam}|${c}|${s}`]);
  const R = d.students.map(s => { const a = termOf(final ? d.other.marks : d.marks, s.id, subs), b = final ? termOf(d.marks, s.id, subs) : null;
    const sum = a.sum + (b ? b.sum : 0), max = a.max + (b ? b.max : 0), p = max ? Math.round(sum / max * 10000) / 100 : 0;
    // A result is only a result when every subject has marks (AB counts as entered). Until then: no %, grade, rank or WhatsApp.
    const full = a.full && (!b || b.full), miss = [...new Set([...a.miss.map(x => x + (final ? ' (T1)' : '')), ...(b ? b.miss.map(x => x + ' (T2)') : [])])];
    return { s, a, b, sum, max, p, g: grade(p), any: a.any || (b && b.any), full, miss }; });
  const ranked = R.filter(r => r.full).sort((x, y) => y.sum - x.sum); ranked.forEach((r, i) => r.rank = i + 1);
  const pendingN = R.filter(r => !r.full && r.any).length;
  const pass = ranked.filter(r => r.p >= 33).length;
  RESULT = { R, subs, ses, exam, c, final };
  return `${!final && entered.length < subs.length ? `<div class="ban warn">Marks entered for ${entered.length} of ${subs.length} subjects. Waiting: ${subs.filter(s => !entered.includes(s)).map(esc).join(', ')}.</div>` : ''}
    ${pendingN ? `<div class="ban inf">${pendingN} student(s) have marks missing in some subject. Their %, grade and rank show only when every subject is filled.</div>` : ''}
    <div class="kp"><div class="k ac"><span>Class average</span><b>${ranked.length ? Math.round(ranked.reduce((a, r) => a + r.p, 0) / ranked.length * 10) / 10 + '%' : '—'}</b><small>${ranked.length} complete result(s)</small></div>
      <div class="k ${pass === ranked.length ? 'ok' : 'bad'}"><span>Passed (33%+)</span><b>${pass}/${ranked.length}</b><small>${ranked[0] ? 'Topper: ' + esc(ranked[0].s.name) : ''}</small></div></div>
    <div class="res"><table><tr><th class="l">#</th><th class="l">Student</th>${subs.map(s => `<th>${esc(s.slice(0, 10))}</th>`).join('')}<th>Total</th><th>%</th><th>Grade</th><th></th></tr>
      ${ranked.concat(R.filter(r => !r.full && r.any), R.filter(r => !r.any)).map(r => `<tr><td class="l">${r.rank || '—'}</td><td class="l">${esc(r.s.name)}</td>${subs.map((s, i) => { const t = r.a.rows[i].t + (r.b ? r.b.rows[i].t : 0), has = r.a.rows[i].has || (r.b && r.b.rows[i].has); return `<td>${has ? Math.round(t * 100) / 100 : '–'}</td>`; }).join('')}
        <td><b>${Math.round(r.sum * 100) / 100}</b>/${r.max}</td><td>${r.full ? r.p : '–'}</td><td class="gd ${r.full ? 'g' + r.g : ''}">${r.full ? r.g : r.any ? `<span class="pill warn" title="Missing: ${esc(r.miss.join(', '))}">Pending</span>` : ''}</td><td>${r.full && r.s.phone ? `<a class="btn wa s" target="_blank" rel="noopener" href="${wa(r.s.phone, resultText(r))}">${ic('wa')}</a>` : ''}</td></tr>`).join('')}</table></div>
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
  const rows = R.filter(r => r.full).sort((a, b) => (a.rank || 999) - (b.rank || 999));
  p.innerHTML = `<h1>${esc(S.settings.school || '')}</h1><h2>Result Sheet — Class ${esc(c)} — ${final ? 'Final (Term-1 + Term-2)' : esc(exam)} — Session ${esc(S.settings.session || '')}</h2>
    <table><tr><th>Rank</th><th>Name</th><th>Adm</th>${subs.map(s => `<th>${esc(s)}</th>`).join('')}<th>Total</th><th>%</th><th>Grade</th></tr>${rows.map(r => `<tr><td>${r.rank}</td><td>${esc(r.s.name)}</td><td>${esc(r.s.adm || '')}</td>${subs.map((s, i) => `<td>${Math.round((r.a.rows[i].t + (r.b ? r.b.rows[i].t : 0)) * 100) / 100}</td>`).join('')}<td>${Math.round(r.sum * 100) / 100}/${r.max}</td><td>${r.p}</td><td>${r.g}</td></tr>`).join('')}</table>
    ${R.some(r => !r.full && r.any) ? `<p>Not in this sheet (marks missing): ${R.filter(r => !r.full && r.any).map(r => esc(r.s.name) + ' — ' + esc(r.miss.join(', '))).join('; ')}</p>` : ''}
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

// ---------------------------------------------------------------- CONNECT (owner, 5 Nov 2026)
// Fee calls (class teacher only, own class, with amounts — same calling records as the Chairman app),
// fee demand bill, 5 ready messages to parents / students, and the private teacher–parent chat (Smart Studio records).
const FEE = {};
let CHAT = null;
const rs = p => '₹' + (Math.round((p || 0) / 100)).toLocaleString('en-IN');
function uuid4() { if (window.crypto && crypto.randomUUID) return crypto.randomUUID(); const h = [...crypto.getRandomValues(new Uint8Array(16))].map(b => b.toString(16).padStart(2, '0')).join(''); return h.slice(0, 8) + '-' + h.slice(8, 12) + '-4' + h.slice(13, 16) + '-a' + h.slice(17, 20) + '-' + h.slice(20, 32); }
function myClasses() { return [...new Set([...myCT(), ...allocList().map(a => a.c)])].filter(c => className().includes(c)); }
function vConn() {
  const ct = myCT(), m = P.m || (ct.length ? 'fee' : 'msg');
  const items = [...(ct.length ? [['fee', 'Fee calls']] : []), ['msg', 'Messages'], ['chat', 'Parents'], ['staff', 'Staff chat' + (scUnread() ? ' · ' + scUnread() : '')]];
  return seg(items, m, `(k=>go('conn',{m:k}))`) + (m === 'fee' && ct.length ? vFee(ct) : m === 'chat' ? vChat() : m === 'staff' ? vStaff() : vMsg());
}
// ---- fee calls ----
function vFee(ct) {
  const c = ct.includes(P.c) ? P.c : ct[0];
  if (!FEE[c]) { FEE[c] = { loading: true }; api('teacher_api.php?a=fees&cls=' + encodeURIComponent(c)).then(r => { FEE[c] = r; render(); }).catch(e => { FEE[c] = { err: e.message }; render(); }); }
  const F = FEE[c], pick = ct.length > 1 ? `<div class="fld">Class${sel('feeC', ct.map(x => [x, 'Class ' + x]), c, "go('conn',{m:'fee',c:this.value})")}</div>` : '';
  if (F.loading) return pick + '<div class="load"><i></i></div>';
  if (F.err) return pick + `<div class="card">${esc(F.err)} <button class="btn s l" onclick="delete FEE['${esc(c)}'];render()">Try again</button></div>`;
  const kids = F.children, today = F.today, f = P.f || 'due', ph = needPhotos(c);
  const last = sid => F.calls.find(x => x.sid === sid);
  // a payment date = the parent's own date, or the latest call where the parent promised a date that is not past
  const pr = sid => { if (F.promises[sid] && F.promises[sid].date >= today) return F.promises[sid]; const l = last(sid); return l && l.outcome === 'Promised to pay' && l.promise >= today ? { date: l.promise, amount: l.amount } : null; };
  const week = addDays(today, -7);
  const due = kids.filter(k => k.due > 0), total = due.reduce((n, k) => n + k.due, 0);
  const promised = kids.filter(k => pr(k.sid) && k.due > 0), calledToday = F.calls.filter(x => x.date === today).length;
  const notCalled = due.filter(k => { const l = last(k.sid); return !l || l.date < week; });
  const list = (f === 'due' ? due : f === 'prom' ? promised : f === 'new' ? notCalled : kids).slice().sort((a, b) => b.due - a.due);
  return pick + `<div class="card fee-sum"><div class="row"><div><small>Class ${esc(c)} · fee due till today</small><b>${rs(total)}</b></div><span class="pill bad">${due.length} with dues</span></div>
      <div class="kp3"><div><b>${promised.length}</b><span>payment dates</span></div><div><b>${notCalled.length}</b><span>not called this week</span></div><div><b>${calledToday}</b><span>calls today</span></div></div>
      ${kids.some(k => k.asOn) ? `<p class="mut" style="margin:6px 0 0">Fee figures copied from the old ERP on ${esc(kids.find(k => k.asOn).asOn)}.</p>` : ''}</div>
    <div class="chips">${[['due', 'With dues', due.length], ['new', 'Not called', notCalled.length], ['prom', 'Payment date', promised.length], ['all', 'All', kids.length]].map(([k, l, n]) => `<button class="chip ${f === k ? 'on' : ''}" onclick="go('conn',{m:'fee',c:${jsq(c)},f:'${k}'})">${l} <b>${n}</b></button>`).join('')}</div>
    <div class="fee-list">${list.map(k => { const l = last(k.sid), p = pr(k.sid), pic = ph[k.sid];
      return `<div class="fee-row"><span class="ph sm">${pic ? `<img src="${esc(pic.src)}" alt="" loading="lazy">` : `<i>${esc(initials(k.name))}</i>`}</span>
        <div class="n"><b>${esc(k.name)}</b><small>${k.father ? esc(k.father) + ' · ' : ''}${k.tag ? `<span class="pill">${esc(k.tag)}</span> ` : ''}${p ? `Will pay ${esc(fmtDate(p.date))}${p.amount ? ' · ' + rs(p.amount) : ''}` : l ? `${esc(l.outcome)} · ${esc(fmtDate(l.date))}` : 'Not called yet'}</small></div>
        <div class="amt ${k.due > 0 ? 'bad' : 'ok'}">${k.due > 0 ? rs(k.due) : 'Paid'}</div>
        <div class="acts">${k.phone ? `<a class="ib" href="tel:+91${esc(k.phone)}" aria-label="Call ${esc(k.name)}" onclick="feeDialed(${jsq(c)},${jsq(k.sid)})">${ic('phone')}</a>` : ''}<button class="ib" aria-label="Call result for ${esc(k.name)}" onclick="feeResult(${jsq(c)},${jsq(k.sid)})">${ic('check')}</button><button class="ib" aria-label="Demand bill for ${esc(k.name)}" onclick="feeBill(${jsq(c)},${jsq(k.sid)})">${ic('bill')}</button></div></div>`; }).join('') || '<div class="empty">Nobody in this list.</div>'}</div>
    <p class="mut">Calls you save here are seen by the Principal, Chairman Sir and the office in the same calling list.</p>`;
}
function feeDialed(c, sid) { setTimeout(() => feeResult(c, sid), 1200); }
function feeResult(c, sid) {
  const k = FEE[c].children.find(x => x.sid === sid), outs = FEE[c].outcomes, max = addDays(todayISO(), 7);
  DRAFT.fee = { c, sid, out: DRAFT.fee && DRAFT.fee.sid === sid ? DRAFT.fee.out : '' };
  SHEET = () => { const o = DRAFT.fee.out, need = ['Promised to pay', 'Call back later', 'Will visit school'].includes(o);
    return `<h2>Call result · ${esc(k.name)}</h2><p class="mut" style="margin:0">Fee due till today: <b>${rs(k.due)}</b>${k.phone ? ' · ' + esc(k.phone) : ''}</p>
    <div class="chips">${outs.map(x => `<button type="button" class="chip ${o === x ? 'on' : ''}" onclick="DRAFT.fee.out=${jsq(x)};render()">${esc(x)}</button>`).join('')}</div>
    ${need ? `<div class="fld">${o === 'Promised to pay' ? 'Will pay on' : o === 'Will visit school' ? 'Will visit on' : 'Call again on'}<input id="fcD" type="date" class="inp" min="${todayISO()}" max="${max}" value="${addDays(todayISO(), 2)}"></div>` : ''}
    ${o === 'Promised to pay' ? `<div class="fld">Amount (₹)<input id="fcA" class="inp" inputmode="numeric" value="${Math.round(k.due / 100)}"></div>` : ''}
    <div class="fld">What was discussed<input id="fcN" class="inp" maxlength="300" placeholder="e.g. Father will come on Saturday"></div>
    <button class="btn go full" onclick="feeSave()">Save call result</button>`; };
  render();
}
async function feeSave() {
  const { c, sid, out } = DRAFT.fee; if (!out) return toast('Choose the call result.');
  const body = { sid, outcome: out, note: ($('fcN') || {}).value || '' };
  if ($('fcD')) body.promise = $('fcD').value; if ($('fcA')) body.amount = $('fcA').value;
  try { const r = await api('teacher_api.php?a=feecall', body); FEE[c].calls.unshift(r.call); DRAFT.fee = null; SHEET = null; toast('Call saved. The office and Chairman Sir can see it.'); render(); }
  catch (e) { toast(e.message); }
}
function billText(c, k) {
  const sch = FEE[c].school || S.settings.school || '', mon = ymName(ymOf(todayISO()));
  return `*FEE DEMAND BILL*\n${sch}\n\nStudent: *${k.name}*\nClass: ${c}${k.father ? '\nFather: ' + k.father : ''}\nDate: ${fmtLong(todayISO())}\n\n` +
    `Fee due till ${mon}: *${rs(k.due)}*` + (k.oldDue ? `\n(includes old dues ${rs(k.oldDue)})` : '') + (k.overallDue !== null && k.overallDue > k.due ? `\nWhole session balance: ${rs(k.overallDue)}` : '') +
    (k.last ? `\nLast payment: ${rs(k.last.amount)} on ${k.last.date} (receipt ${k.last.no})` : '') +
    `\n\nKindly deposit the fee at the school office at the earliest. Please ignore if already paid.\nकृपया ${mon} तक की बकाया फ़ीस ${rs(k.due)} जल्द से जल्द विद्यालय कार्यालय में जमा करें। यदि जमा कर दी है तो कृपया ध्यान न दें।\n\n— ${myName()}, Class Teacher`;
}
function feeBill(c, sid) {
  const k = FEE[c].children.find(x => x.sid === sid), t = billText(c, k);
  SHEET = () => `<h2>Fee demand bill · ${esc(k.name)}</h2><pre class="bill" data-notr>${esc(t.replace(/\*/g, ''))}</pre>
    <div class="btns">${k.phone ? `<a class="btn wa" target="_blank" rel="noopener" href="${wa(k.phone, t)}">${ic('wa')} Send on WhatsApp</a>` : '<span class="mut">No phone saved.</span>'}<button class="btn w" onclick="feeBillPrint(${jsq(c)},${jsq(sid)})">${ic('print')} Print</button></div>
    <p class="mut" style="margin:0">Amounts are from the school fee records. This is a reminder, not a receipt.</p>`;
  render();
}
function feeBillPrint(c, sid) { const k = FEE[c].children.find(x => x.sid === sid); let p = document.querySelector('.prt'); if (!p) { p = document.createElement('div'); p.className = 'prt'; document.body.appendChild(p); }
  p.innerHTML = `<pre style="font:13px/1.6 Arial,sans-serif;white-space:pre-wrap">${esc(billText(c, k).replace(/\*/g, ''))}</pre>`; window.print(); }
// ---- ready messages ----
const MSGS = [['absent', 'Attendance'], ['hw', 'Homework not done'], ['fee', 'Fee reminder'], ['ptm', 'PTM invitation'], ['praise', 'Appreciation'], ['custom', 'Own message']];
const PRAISE = ['did very well in the test', 'kept a neat and complete notebook', 'helped classmates', 'showed very good behaviour', 'answered well in class'];
function msgText(t, s, c, o) {
  const sch = S.settings.school || '', me2 = myName(), d = fmtLong(o.date || todayISO());
  switch (t) {
    case 'absent': { const lv = (((attOf(o.date || todayISO(), c) || {}).st) || {})[s.id] === 'L', today = (o.date || todayISO()) === todayISO();
      return lv ? `Dear Parent,\n${s.name} (Class ${c}) ${today ? 'is on leave today' : 'was on leave on ' + d}.\n${s.name} ${today ? 'आज छुट्टी पर है' : d + ' को छुट्टी पर था/थी'}।\n— ${me2}, ${sch}`
        : `Dear Parent,\n${s.name} (Class ${c}) ${today ? 'is absent from school today' : 'was absent from school on ' + d}. Kindly tell us the reason.\n${s.name} ${today ? 'आज' : d + ' को'} विद्यालय में अनुपस्थित ${today ? 'है' : 'था/थी'}। कृपया कारण बताएँ।\n— ${me2}, ${sch}`; }
    case 'hw': return `Dear Parent,\n${s.name} (Class ${c}) did not complete the ${o.subj || ''} homework.${o.note ? '\n“' + o.note + '”' : ''}\nKindly help ${s.name} finish it today.\n${s.name} ने ${o.subj || ''} का गृहकार्य पूरा नहीं किया। कृपया आज पूरा करवाएँ।\n— ${me2}, ${sch}`;
    case 'fee': return `Dear Parent,\nThis is a gentle reminder that the school fee of ${s.name} (Class ${c})${o.amt ? ' — ' + o.amt + ' —' : ''} is due. Kindly deposit it at the school office at the earliest.\n${s.name} की विद्यालय फ़ीस${o.amt ? ' (' + o.amt + ')' : ''} बकाया है। कृपया जल्द जमा करें।\n— ${me2}, Class Teacher, ${sch}`;
    case 'ptm': return `Dear Parent,\nYou are invited to the Parent–Teacher Meeting of Class ${c} on ${d}${o.time ? ' at ' + o.time : ''} to discuss ${s ? s.name + '’s' : 'your child’s'} progress.\nकक्षा ${c} की अभिभावक-शिक्षक बैठक ${d}${o.time ? ', ' + o.time : ''} को है। कृपया अवश्य पधारें।\n— ${me2}, ${sch}`;
    case 'praise': return `Dear Parent,\nWe are happy to share that ${s.name} (Class ${c}) ${o.why || PRAISE[0]} today. Well done! 👏\nहमें ख़ुशी है कि ${s.name} ने आज बहुत अच्छा काम किया। शाबाश!\n— ${me2}, ${sch}`;
    default: return `Dear Parent,\n${o.note || ''}\n— ${me2}, ${sch}`;
  }
}
function vMsg() {
  const cls = myClasses(); if (!cls.length) return '<div class="card empty">No classes are given to you yet.</div>';
  const c = cls.includes(P.c) ? P.c : cls[0], t = MSGS.some(x => x[0] === P.t) ? P.t : 'absent', isCT = myCT().includes(c);
  if (!DRAFT.msg || DRAFT.msg.c !== c) DRAFT.msg = { c, sel: {}, o: { date: todayISO(), why: PRAISE[0] } };
  if (t === 'absent') msgAttPick(c, DRAFT.msg);
  const D = DRAFT.msg, kids = t === 'absent' ? msgAttKids(c, D.o.date) : studentsOf(c), ph = needPhotos(c), picked = kids.filter(s => D.sel[s.id]);
  const att = attOf(todayISO(), c), absentToday = new Set((att && att.absent) || []);
  const subs = [...new Set(allocList().filter(a => a.c === c).map(a => a.s))];
  if (t === 'hw' && !D.o.subj) D.o.subj = subs[0] || '';
  const extra = t === 'ptm' ? `<div class="two"><div class="fld">Meeting date<input type="date" class="inp" value="${esc(D.o.date)}" min="${todayISO()}" onchange="DRAFT.msg.o.date=this.value;render()"></div><div class="fld">Time<input class="inp" value="${esc(D.o.time || '')}" placeholder="e.g. 10:00 AM" oninput="DRAFT.msg.o.time=this.value"></div></div>`
    : t === 'absent' ? msgAttBox(c, D)
    : t === 'hw' ? `<div class="two"><div class="fld">Subject${sel('msgS', (subs.length ? subs : ['']).map(x => [x, x || '—']), D.o.subj, 'DRAFT.msg.o.subj=this.value')}</div><div class="fld">Homework<input class="inp" value="${esc(D.o.note || '')}" placeholder="e.g. Ex 4.1" oninput="DRAFT.msg.o.note=this.value"></div></div>`
    : t === 'praise' ? `<div class="chips">${PRAISE.map(x => `<button type="button" class="chip ${D.o.why === x ? 'on' : ''}" onclick="DRAFT.msg.o.why=${jsq(x)};render()">${esc(x)}</button>`).join('')}</div>`
    : t === 'fee' ? (isCT ? '<p class="mut" style="margin:0">The due amount is added for each child from the fee records when you are the class teacher.</p>' : '<p class="mut" style="margin:0">Only the class teacher can send fee reminders.</p>')
    : `<div class="fld">Message<textarea class="inp" rows="3" maxlength="600" oninput="DRAFT.msg.o.note=this.value" placeholder="Write your message">${esc(D.o.note || '')}</textarea></div>`;
  if (t === 'fee' && isCT && !FEE[c]) { FEE[c] = { loading: true }; api('teacher_api.php?a=fees&cls=' + encodeURIComponent(c)).then(r => { FEE[c] = r; render(); }).catch(e => { FEE[c] = { err: e.message }; }); }
  const feeOf = id => { const k = FEE[c] && FEE[c].children && FEE[c].children.find(x => x.sid === id); return k && k.due > 0 ? rs(k.due) : ''; };
  const canSend = !(t === 'fee' && !isCT);
  return `${cls.length > 1 ? `<div class="fld">Class${sel('msgC', cls.map(x => [x, 'Class ' + x]), c, "DRAFT.msg=null;go('conn',{m:'msg',c:this.value,t:" + jsq(t) + "})")}</div>` : ''}
    <div class="card"><h3>1 · Choose the message</h3><div class="chips">${MSGS.map(([k, l]) => `<button type="button" class="chip ${t === k ? 'on' : ''}" onclick="go('conn',{m:'msg',c:${jsq(c)},t:'${k}'})">${l}</button>`).join('')}</div>${extra}</div>
    ${t === 'ptm' ? `<a class="btn wa full" target="_blank" rel="noopener" href="${wa('', msgText('ptm', null, c, D.o))}">${ic('wa')} Send one invitation to the class parents' group</a>` : ''}
    <div class="card"><h3>2 · Choose the children <small>${picked.length} chosen</small></h3>
      <div class="btns" style="margin-bottom:8px"><button class="btn s l" onclick="${kids.map(s => `DRAFT.msg.sel['${esc(s.id)}']=true`).join(';')};render()">Select all</button><button class="btn s w" onclick="DRAFT.msg.sel={};render()">Clear</button></div>
      <div class="pick">${kids.map(s => `<button type="button" class="pk ${D.sel[s.id] ? 'on' : ''}" onclick="DRAFT.msg.sel['${esc(s.id)}']=!DRAFT.msg.sel['${esc(s.id)}'];render()"><span class="ph sm">${ph[s.id] ? `<img src="${esc(ph[s.id].src)}" alt="">` : `<i>${esc(initials(s.name))}</i>`}</span><b>${esc(s.name)}</b>${absentToday.has(s.id) ? '<em>absent</em>' : ''}</button>`).join('')}</div></div>
    ${picked.length && canSend ? `<div class="card"><h3>3 · Send <small>one tap each</small></h3><pre class="bill preview" data-notr>${esc(msgText(t, picked[0], c, Object.assign({}, D.o, { amt: feeOf(picked[0].id) })))}</pre>
      ${picked.map(s => `<div class="li"><div class="t"><b>${esc(s.name)}</b><span>${s.phone ? esc(s.phone) : 'no phone saved'}${t === 'fee' && feeOf(s.id) ? ' · ' + feeOf(s.id) : ''}</span></div>${s.phone ? `<a class="btn wa s" target="_blank" rel="noopener" href="${wa(s.phone, msgText(t, s, c, Object.assign({}, D.o, { amt: feeOf(s.id) })))}" onclick="this.classList.add('sent')">${ic('wa')} Send</a>` : ''}</div>`).join('')}</div>` : ''}`;
}
// ---- chat (Smart Studio's private teacher–parent conversations) ----
function vChat() {
  if (isP()) return '<div class="card"><h3>Chat</h3><p class="mut">The Principal reads every teacher–parent conversation in the web ERP → Smart Studio.</p></div>';
  if (!CHAT) { CHAT = { loading: true }; api('studio_api.php?a=state').then(r => { CHAT = r; render(); }).catch(e => { CHAT = { err: e.message }; render(); }); }
  if (CHAT.loading) return '<div class="load"><i></i></div>';
  if (CHAT.err) return `<div class="card">${esc(CHAT.err)} <button class="btn s l" onclick="CHAT=null;render()">Try again</button></div>`;
  const mine = CHAT.messages.filter(m => m.tid === me()), kids = myClasses().flatMap(c => studentsOf(c));
  const sid = P.sid && kids.some(k => k.id === P.sid) ? P.sid : null;
  const quiet = `<div class="ban inf"><div>Parents' messages after 4:30 PM reach you at 8:00 AM, so your evenings stay free. Your personal number is never shown.</div></div>`;
  if (!sid) {
    const last = {}; mine.forEach(m => { if (!last[m.sid] || m.at > last[m.sid].at) last[m.sid] = m; });
    const withMsg = kids.filter(k => last[k.id]).sort((a, b) => last[b.id].at - last[a.id].at), rest = kids.filter(k => !last[k.id]);
    return quiet + `<div class="card"><h3>Conversations <small>${withMsg.length}</small></h3>${withMsg.map(k => { const m = last[k.id];
        return `<div class="li" onclick="go('conn',{m:'chat',sid:${jsq(k.id)}})"><span class="ph sm"><i>${esc(initials(k.name))}</i></span><div class="t"><b>${esc(k.name)} <small class="mut">· Class ${esc(k.cls)}</small></b><span>${m.sender.startsWith('p:') ? '' : 'You: '}${esc(m.text.slice(0, 60))}</span></div><small class="mut">${esc(fmtTime(m.at))}</small></div>`; }).join('') || '<div class="empty">No conversations yet.</div>'}</div>
      <div class="card"><h3>Start a conversation</h3><div class="fld">Child${sel('chK', [['', '— choose a child —'], ...rest.map(k => [k.id, k.name + ' · Class ' + k.cls])], '', "if(this.value)go('conn',{m:'chat',sid:this.value})")}</div></div>`;
  }
  const k = kids.find(x => x.id === sid), th = mine.filter(m => m.sid === sid).sort((a, b) => a.at - b.at);
  return `<button class="btn s w" style="align-self:flex-start" onclick="go('conn',{m:'chat'})">${ic('back')} All conversations</button>${quiet}
    <div class="card"><h3>${esc(k.name)} <small>Class ${esc(k.cls)}</small></h3><div class="thread">${th.map(m => `<div class="bub ${m.sender.startsWith('p:') ? 'in' : 'out'}"><p>${esc(m.text)}</p><small>${esc(m.from)} · ${esc(fmtTime(m.at))}</small></div>`).join('') || '<div class="empty">No messages yet. Say hello to the parent.</div>'}</div>
      <div class="chips" style="margin-top:8px">${['Your child did well today. 👏', 'Please check the homework diary today.', 'Kindly meet me in school tomorrow.'].map(x => `<button type="button" class="chip" onclick="$('chT').value=${jsq(x)};keepNote($('chT'))">${esc(x)}</button>`).join('')}</div>
      <div class="send"><textarea id="chT" data-keep class="inp" rows="2" maxlength="2000" placeholder="Write to the parent"></textarea><button class="btn go" onclick="chatSend(${jsq(sid)})">Send</button></div></div>`;
}
async function chatSend(sid) {
  const text = ($('chT').value || '').trim(); if (!text) return toast('Write a message.');
  try { await api('studio_api.php?a=message', { id: uuid4(), sid, tid: me(), text }); keepClear('chT'); CHAT = null; toast('Message sent.'); render(); }
  catch (e) { toast(e.message); }
}

// ---- staff chat (owner, 5 Nov 2026: "teachers bhi baat kar paye or chat record rakhiye") ----
// Staff room (all teachers + Principal) and one-to-one chats. Saved on the school server as a record (never deleted);
// the Principal and Chairman Sir can read every chat. New messages are checked every 15 seconds while a chat is open.
let SC = null, SCR = {};
function scSeenKey() { return 'ta_scseen_' + (ME ? ME.role + ME.tid : ''); }
function scSeen() { try { return JSON.parse(store.get(scSeenKey()) || '{}') || {}; } catch (e) { return {}; } }
function scMark(room, at) { const o = scSeen(); if (!o[room] || o[room] < at) { o[room] = at; store.set(scSeenKey(), JSON.stringify(o)); } }
function scUnread() { if (!SC || !SC.rooms) return 0; const seen = scSeen(); return SC.rooms.filter(m => m.from !== SC.me && m.at > (seen[m.room] || 0)).length; }
function scMe() { return isP() ? 'P' : me(); }
function scName(tid) { return tid === 'P' ? 'Principal' : (T(tid).name || tid); }
function scDm(a, b) { return 'dm|' + [a, b].sort().join('|'); }
function scRoomName(room) { if (room === 'all') return 'Staff room'; const [, a, b] = room.split('|'); return a === scMe() ? scName(b) : b === scMe() ? scName(a) : scName(a) + ' ↔ ' + scName(b); }
async function scLoad(room) {
  try {
    const old = room ? SCR[room] : null, since = old && old.length ? old[old.length - 1].at : 0;
    const r = await api('teacher_api.php?a=staffchat' + (room ? '&room=' + encodeURIComponent(room) + '&since=' + since : ''));
    SC = { me: r.me, rooms: r.rooms };
    if (room) { SCR[room] = (old || []).concat(r.messages.filter(m => !(old || []).some(x => x.id === m.id))); if (SCR[room].length) scMark(room, SCR[room][SCR[room].length - 1].at); }
    return r;
  } catch (e) { if (!SC) SC = { err: e.message }; }
}
function vStaff() {
  const room = P.room || null;
  if (!SC || (room && !SCR[room])) { if (!(SC && SC.loading)) { const was = SC; SC = Object.assign({}, was || {}, { loading: true }); scLoad(room).then(() => { if (SC) delete SC.loading; render(); }); } return '<div class="load"><i></i></div>'; }
  if (SC.err) return `<div class="card">${esc(SC.err)} <button class="btn s l" onclick="SC=null;render()">Try again</button></div>`;
  const mine = scMe(), seen = scSeen();
  if (!room) {
    const last = {}; (SC.rooms || []).forEach(m => last[m.room] = m);
    const people = [...(mine !== 'P' ? ['P'] : []), ...Object.keys(S.teachers).filter(t => t !== mine && t !== 'P' && S.teachers[t] && S.teachers[t].name)]
      .map(t => ({ t, room: scDm(mine, t) })).sort((a, b) => ((last[b.room] || {}).at || 0) - ((last[a.room] || {}).at || 0) || scName(a.t).localeCompare(scName(b.t)));
    const others = isP() || (ME && ME.role === 'chairman') ? (SC.rooms || []).filter(m => m.room !== 'all' && !m.room.split('|').includes(mine)).sort((a, b) => b.at - a.at) : [];
    const row = (rm, title, sub) => { const m = last[rm], un = m && m.from !== mine && m.at > (seen[rm] || 0);
      return `<div class="li" onclick="go('conn',{m:'staff',room:${jsq(rm)}})"><span class="ph sm ${rm === 'all' ? 'grp' : ''}"><i>${rm === 'all' ? '👥' : esc(initials(title))}</i></span><div class="t"><b>${esc(title)}${un ? ' <span class="pill bad">new</span>' : ''}</b><span>${m ? (m.from === mine ? 'You: ' : esc(scName(m.from).split(' ')[0]) + ': ') + esc(m.text.slice(0, 50)) : esc(sub)}</span></div>${m ? `<small class="mut">${esc(fmtTime(m.at))}</small>` : ''}</div>`; };
    return `<div class="card"><h3>Staff room <small>everyone</small></h3>${row('all', 'Staff room', 'All teachers and the Principal — say good morning!')}</div>
      <div class="card"><h3>Talk to one person <small>${people.length}</small></h3><input class="inp" placeholder="Search a teacher" oninput="document.querySelectorAll('.sc-p').forEach(x=>x.hidden=!x.dataset.n.includes(this.value.toLowerCase()))" style="margin-bottom:6px">
        ${people.map(p => `<div class="sc-p" data-n="${esc(scName(p.t).toLowerCase())}">${row(p.room, scName(p.t), 'Tap to start a chat')}</div>`).join('') || '<div class="empty">No other teachers yet.</div>'}</div>
      ${others.length ? `<div class="card"><h3>Chat record <small>Principal can read</small></h3>${others.map(m => row(m.room, scRoomName(m.room), '')).join('')}</div>` : ''}
      <p class="mut">Chats are kept as a school record. Please keep them about school work.</p>`;
  }
  const msgs = SCR[room] || [], canWrite = room === 'all' || room.split('|').includes(mine);
  let lastDay = '';
  return `<button class="btn s w" style="align-self:flex-start" onclick="go('conn',{m:'staff'})">${ic('back')} All chats</button>
    <div class="card"><h3>${esc(scRoomName(room))} <small>${msgs.length} message(s)</small></h3>
      <div class="thread" id="scThread">${msgs.map(m => { const day = new Date(m.at).toDateString(), sep = day !== lastDay ? `<div class="day-sep">${esc(new Date(m.at).toLocaleDateString(LOC(), { weekday: 'short', day: 'numeric', month: 'short' }))}</div>` : ''; lastDay = day;
        return sep + `<div class="bub ${m.from === mine ? 'out' : 'in'}">${m.from !== mine && room === 'all' ? `<b class="who">${esc(scName(m.from))}</b>` : ''}<p>${esc(m.text)}</p><small>${esc(new Date(m.at).toLocaleTimeString(LOC(), { hour: '2-digit', minute: '2-digit' }))}</small></div>`; }).join('') || '<div class="empty">No messages yet. Write the first one.</div>'}</div>
      ${canWrite ? `<div class="chips" style="margin-top:8px">${['Good morning 🙏', 'Please take my class today.', 'Noted, thank you.', 'Meeting in the staff room at 1:30.'].map(x => `<button type="button" class="chip" onclick="$('scT').value=${jsq(x)};keepNote($('scT'))">${esc(x)}</button>`).join('')}</div>
      <div class="send"><textarea id="scT" data-keep class="inp" rows="2" maxlength="1500" placeholder="Write a message"></textarea><button class="btn go" onclick="scSend(${jsq(room)})">Send</button></div>` : '<p class="mut">You are reading this chat as a record.</p>'}</div>`;
}
async function scSend(room) {
  const el = $('scT'), text = ((el || {}).value || '').trim(); if (!text) return toast('Write a message.');
  try { const r = await api('teacher_api.php?a=staffchat', { room, text }); (SCR[room] = SCR[room] || []).push(r.msg); scMark(room, r.msg.at); el.value = ''; keepClear('scT'); render(); scScroll(); }
  catch (e) { toast(e.message); }
}
function scScroll() { const t = $('scThread'); if (t) t.scrollTop = t.scrollHeight; }
setInterval(async () => {
  if (!S || !store.get('sch_token') || document.hidden) return;
  if (TAB === 'conn' && P.m === 'staff' && P.room && SCR[P.room]) { const n = SCR[P.room].length; await scLoad(P.room); if (SCR[P.room].length !== n) { softRender(); scScroll(); } }
}, 15000);
setInterval(() => { if (S && store.get('sch_token') && !document.hidden && !(TAB === 'conn' && P.m === 'staff')) scLoad(null).then(() => softRender()); }, 60000);

// ---------------------------------------------------------------- PRINCIPAL MODE (owner, 5 Nov 2026)
// "Principal Sir login karen to upar Teacher / Principal switch, aur jo nahi kiye hain unka cross-check + message".
// Cross-check of one day: attendance of every class, diary of every bell that is over, daily check of every class,
// homework checks. Each teacher with something pending gets a ready message: send it in the app (Staff chat, kept as a
// record) or on WhatsApp. "Remind everyone" sends each pending teacher their own list in the app.
function pMode() { return isP() && store.get('ta_mode') === 'principal'; }
function setMode(m) { store.set('ta_mode', m); go(m === 'principal' ? 'prin' : 'home'); }
const XC = {};
function needXC(date) {
  if (XC[date]) return XC[date];
  XC[date] = { loading: true };
  api('teacher_api.php?a=crosscheck&date=' + date).then(r => { XC[date] = r; softRender(); }).catch(e => { XC[date] = { err: e.message }; softRender(); });
  return XC[date];
}
function prinData(date) {
  const work = isWorkDay(date), X = XC[date] && !XC[date].loading && !XC[date].err ? XC[date] : null, cutoff = date < todayISO() || nowMin() >= toMin(S.settings.attendanceCutoff || '09:30');
  const away = new Set(staffAbsent(date)), per = {};
  const add = (t, kind, txt, done) => { const o = per[t] = per[t] || { t, pend: [], done: 0, items: [] }; (o.items = o.items || []).push({ kind, done }); if (done) o.done++; else o.pend.push({ kind, txt }); };
  // Same list the teacher sees in "My work" (workOf / setupOf in teacher-work.js): attendance, daily check, diary per bell,
  // homework checks; plus once-a-week / one-time work (lesson plan, syllabus chapters, exam chapters) kept apart in `setup`.
  if (work) Object.keys(S.teachers).forEach(t => { if (away.has(t)) return;
    workOf(t, date, X).forEach(w => add(t, w.kind, w.txt, w.done));
    const su = setupOf(t, date).filter(w => !w.done); if (su.length) { const o = per[t] = per[t] || { t, pend: [], done: 0 }; o.setup = su; } });
  void cutoff;
  return { per, away, X, work };
}
function prinPendingCount() { try { const d = prinData(todayISO()); return Object.values(d.per).filter(o => o.pend.length).length; } catch (e) { return 0; } }
function prinMsg(o, date) {
  const n = T(o.t).name;
  return `Namaste ${n},\n${o.pend.length ? `${date === todayISO() ? 'Aaj' : fmtLong(date) + ' ko'} ke yeh kaam app mein baaki hain:\n${o.pend.map(p => '• ' + p.txt).join('\n')}\n` : ''}${(o.setup || []).length ? `${o.pend.length ? '\n' : ''}Yeh bhi app mein bharna hai:\n${o.setup.map(p => '• ' + p.txt).join('\n')}\n` : ''}Kripya jaldi poora kar dein. Dhanyavaad.\n— ${myName()}, Principal`;
}
function vPrin() {
  const t = todayISO(), date = P.date && P.date <= t ? P.date : t, f = P.f || 'pend';
  needXC(date);
  const D = prinData(date), all = Object.keys(S.teachers).filter(x => x !== me()).map(x => D.per[x] || { t: x, pend: [], done: 0 });
  const pendT = all.filter(o => o.pend.length).sort((a, b) => b.pend.length - a.pend.length);
  const cls = (S.classes || []).filter(k => studentsOf(k.c).length && isWorkDay(date, k.c));
  const attDone = cls.filter(k => attOf(date, k.c)).length, kitDone = D.X ? cls.filter(k => D.X.kit[k.c]).length : null;
  let hcAll = 0, hcDone = 0; Object.values(D.per).forEach(o => (o.items || []).forEach(w => { if (w.kind === 'hwchk') { hcAll++; if (w.done) hcDone++; } }));
  let bellsAll = 0, bellsDone = 0; Object.keys(S.teachers).forEach(x => { if (D.away.has(x)) return; bellsOf(x, date).forEach(b => { if (!bellOver(date, b.bell)) return; bellsAll++; if (((S.diary[date] || {})[x] || {})[b.bell]) bellsDone++; }); });
  const shown = f === 'pend' ? pendT : all.slice().sort((a, b) => b.pend.length - a.pend.length || T(a.t).name.localeCompare(T(b.t).name));
  const nav = `<div class="datebar"><button class="btn s w" onclick="go('prin',{date:'${addDays(date, -1)}',f:'${f}'})" aria-label="Previous day">${ic('back')}</button>
    <label class="dpick"><b>${date === t ? 'Today' : fmtDate(date)}</b><small>${esc(fmtLong(date))}</small><input type="date" max="${t}" value="${date}" onchange="go('prin',{date:this.value,f:'${f}'})"></label>
    <button class="btn s w" ${date >= t ? 'disabled' : ''} onclick="go('prin',{date:'${addDays(date, 1)}',f:'${f}'})" aria-label="Next day">${ic('next')}</button></div>`;
  if (!D.work) return nav + `<div class="ban hol">${dayIdx(date) < 0 ? 'Sunday' : 'Holiday — ' + esc((holidayOn(date) || {}).title || '')}. Nothing to check.</div>`;
  const kpi = (l, a, b, go2) => `<button class="k ${b && a === b ? 'ok' : a < b ? 'go' : ''}" ${go2 ? `onclick="${go2}"` : ''}><span>${l}</span><b>${b === null ? '…' : b === 0 ? '—' : a + '/' + b}</b><small>${b === null ? 'loading' : b === 0 ? 'nothing due' : a === b ? 'all done' : (b - a) + ' not done'}</small></button>`;
  return nav + `<div class="kp">${kpi('Attendance', attDone, cls.length)}${kpi('Diary (bells over)', bellsDone, bellsAll)}${kpi('Daily check', kitDone ?? 0, D.X ? cls.length : null)}${D.X ? kpi('Homework checks', hcDone, hcAll) : ''}
      <button class="k ${pendT.length ? 'bad' : 'ok'}"><span>Teachers with pending work</span><b>${pendT.length}</b><small>${D.away.size ? D.away.size + ' on leave / absent' : 'nobody on leave'}</small></button></div>
    ${D.X && D.X.chk.length ? `<div class="ban ok"><div><b>${D.X.chk.length} homework check(s)</b> saved: ${[...new Set(D.X.chk.map(c => c.cls + ' ' + c.subj))].slice(0, 6).map(esc).join(', ')}${D.X.chk.length > 6 ? '…' : ''}</div></div>` : ''}
    <div class="card"><h3>Classes <small>${attDone}/${cls.length} attendance</small></h3><div class="xc-cls">${cls.map(k => { const a = attOf(date, k.c), kt = D.X && D.X.kit[k.c];
      return `<div class="${a ? 'ok' : 'no'}"><b>${esc(k.c)}</b><small>${esc((T(k.ct).name || '').replace(/\s*(Sir|Ma'am)$/, ''))}</small><span>${a ? '✓ Att.' : '✕ Att.'} ${D.X ? (kt ? '· ✓ Check' : '· ✕ Check') : ''}</span></div>`; }).join('')}</div></div>
    ${seg([['pend', 'Not done (' + pendT.length + ')'], ['all', 'All teachers']], f, `(k=>go('prin',{date:'${date}',f:k}))`)}
    ${pendT.length ? `<button class="btn go full" onclick="prinRemindAll('${date}')">${ic('chat')} Remind all ${pendT.length} teacher(s) in the app</button>` : `<div class="ban ok"><div><b>Sab kaam poora hai.</b> Every teacher has done today's work so far.</div></div>`}
    ${shown.map(o => { const tt = T(o.t), on = D.away.has(o.t);
      return `<div class="card xc-t ${o.pend.length ? 'pend' : 'done'}"><div class="row"><span class="ph sm"><i>${esc(initials(tt.name))}</i></span><div style="flex:1;min-width:0"><b>${esc(tt.name)}</b><small class="mut" style="display:block">${on ? 'On leave / absent today' : o.pend.length ? o.pend.length + ' pending · ' + o.done + ' done' : o.done ? 'All ' + o.done + ' done ✓' : 'Nothing due yet'}</small></div>
        ${o.pend.length || (o.setup || []).length ? `<button class="btn s go" onclick="prinMsgOpen(${jsq(o.t)},'${date}')">Message</button>` : ''}</div>
        ${o.pend.length ? `<ul class="xc-l">${o.pend.map(p => `<li class="${p.kind}">${esc(p.txt)}</li>`).join('')}</ul>` : ''}
        ${(o.setup || []).length ? `<details class="xc-su"><summary>Weekly / one-time work not done: ${o.setup.length}</summary><ul class="xc-l">${o.setup.map(p => `<li class="${p.kind}">${esc(p.txt)}</li>`).join('')}</ul></details>` : ''}</div>`; }).join('')}
    <p class="mut">Attendance is checked after ${esc(S.settings.attendanceCutoff || '09:30')}; a diary only after its bell is over; a homework check when homework was due that day and the teacher's bell in that class is over. Teachers on approved leave or marked absent are left out.</p>`;
}
function prinMsgOpen(t, date) {
  const D = prinData(date), o = D.per[t]; if (!o) return; const tt = T(t), msg = prinMsg(o, date);
  SHEET = () => `<h2>Message · ${esc(tt.name)}</h2><p class="mut" style="margin:0">${o.pend.length} thing(s) pending</p>
    <textarea id="pmT" class="inp" rows="8" maxlength="1500">${esc(msg)}</textarea>
    <div class="btns"><button class="btn go" onclick="prinSend(${jsq(t)})">${ic('chat')} Send in app</button>${tt.phone ? `<a class="btn wa" target="_blank" rel="noopener" href="${wa(tt.phone, msg)}" onclick="this.href=wa(${jsq(tt.phone)},$('pmT').value)">${ic('wa')} WhatsApp</a>` : '<span class="mut">No WhatsApp number saved for this teacher (web ERP → Settings).</span>'}</div>
    <p class="mut" style="margin:0">“Send in app” goes to the teacher's Staff chat and stays as a record.</p>`;
  render();
}
async function prinSend(t) {
  const text = (($('pmT') || {}).value || '').trim(); if (!text) return toast('Write the message.');
  try { const room = scDm('P', t), r = await api('teacher_api.php?a=staffchat', { room, text }); (SCR[room] = SCR[room] || []).push(r.msg); SHEET = null; toast('Message sent to ' + T(t).name + '.'); render(); }
  catch (e) { toast(e.message); }
}
async function prinRemindAll(date) {
  const D = prinData(date), list = Object.values(D.per).filter(o => o.pend.length && o.t !== me());
  if (!list.length || !confirm('Send each of these ' + list.length + ' teachers their own pending list in the app?')) return;
  let n = 0;
  for (const o of list) { try { await api('teacher_api.php?a=staffchat', { room: scDm('P', o.t), text: prinMsg(o, date) }); n++; } catch (e) { toast(e.message); break; } }
  toast(n + ' reminder(s) sent in the app.'); SC = null; render();
}

// ---------------------------------------------------------------- ME (leave, holidays, PIN)
function openMe() {
  const mine = S.leaves.filter(l => l.tid === me()), t = todayISO();
  const hol = S.cal.filter(e => e.type === 'Holiday' && (e.to || e.date) >= t).slice(0, 12);
  const staff = T(me()), allocation = myAllocs(), subjects = [...new Set(allocation.map(a => a.s))];
  const myClasses = myCT(), photo = teacherPhoto();
  const initials = teacherInitials();
  const periods = allocation.reduce((sum, a) => sum + (Number(a.n) || 0), 0);
  const schoolSession = (S.settings || {}).session || '';
  const profileCard = '<section class="teacher-profile-card"><div class="profile-top"><div class="profile-photo">' +
    (photo ? '<img src="' + esc(photo) + '" alt="My profile photo">' : esc(initials)) + '</div>' +
    '<div class="profile-identity"><span class="profile-caption">MY TEACHER PROFILE</span><h2>' + esc(myName()) + '</h2>' +
    '<p>' + (isP() ? 'Principal' : 'Teacher') + ' · Session ' + esc(schoolSession) + '</p></div></div>' +
    '<div class="profile-stats"><div><strong>' + esc(myClasses.length) + '</strong><span>Class teacher</span></div><div><strong>' +
    esc(subjects.length) + '</strong><span>Subjects</span></div><div><strong>' + esc(periods) +
    '</strong><span>Weekly periods</span></div></div>' +
    '<div class="profile-section"><b>My class' + (myClasses.length === 1 ? '' : 'es') + '</b><p>' +
    (myClasses.length ? myClasses.map(c => 'Class ' + esc(c)).join(' · ') : 'No class-teacher assignment') + '</p></div>' +
    '<div class="profile-section"><b>My subjects</b><p>' +
    (subjects.length ? subjects.map(esc).join(' · ') : 'No subject assignment yet') + '</p></div>' +
    (staff.phone ? '<div class="profile-section"><b>School-record contact</b><p>' + esc(staff.phone) + '</p></div>' : '') +
    '<div class="profile-photo-actions"><label class="btn l">Change photo<input type="file" accept="image/*" capture="user" onchange="profilePhotoPick(this)"></label>' +
    (photo ? '<button class="btn l" onclick="profilePhotoRemove()">Remove photo</button>' : '') +
    '<button class="btn l" onclick="showTeacherWelcome(true)">Replay welcome</button><button class="btn l" onclick="store.set(\'ta_intro\',store.get(\'ta_intro\')===\'day\'?\'every\':\'day\');openMe()">Welcome: ' + (store.get('ta_intro') === 'day' ? 'once a day' : 'every opening') + '</button></div>' +
    '<small class="profile-local">Your profile photo stays on this phone. Official staff information is managed by the school office.</small></section>';

  SHEET = () => `<div class="row"><h2>My account</h2><button class="btn s l" onclick="closeSheet()" aria-label="Close profile">Close</button></div>
    ${profileCard}
    ${window.langPicker ? langPicker() : ''}
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
  closeTeacherWelcome(); WELCOME_SHOWN = false;
  IDB.del(cacheKey()); store.set(keepKey(), null); KEEP = {};
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
      <p class="err">${esc(L.msg || (STATUS && STATUS.err) || '')}</p>${window.langPicker ? langPicker() : ''}${installBox()}<p class="mut" style="text-align:center">Powered by Maakit</p></div>`;
}
async function login() {
  const who = store.get('ta_who') || 'teacher', tid = who === 'teacher' ? ($('lgT') || {}).value : '';
  if (who === 'teacher' && !tid) { LOGIN = { msg: 'Choose your name.' }; return loginView(); }
  try { const credentials = { role: who, tid, pin: $('lgPin').value };
    const start = () => api('api.php?a=login', credentials);
    const d = await schoolCompleteOtp(await start(), data => api('api.php?a=verify_teacher_otp', data), start); store.set('sch_token', d.token); if (tid) store.set('ta_tid', tid);
    if (d.mustChange) { LOGIN = { step: 'new' }; return loginView(); } LOGIN = {}; load(); }
  catch (e) { LOGIN = { msg: e.message }; loginView(); }
}
async function setPin() { try { await api('api.php?a=setpin', { old: $('lgOld').value, pin: $('lgNew').value }); toast('PIN saved.'); LOGIN = {}; load(); } catch (e) { LOGIN = { step: 'new', msg: e.message }; loginView(); } }

boot();
