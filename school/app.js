/* =====================================================================
   SN MISHRA SIS — ACADEMIC ERP  (maakit.in/school)
   Data lives on the maakit.in server (school/api.php, tables sch_*).
   Every action is written to the Activity Log automatically.
   Powered by Maakit.
   ===================================================================== */

// ---------------------------------------------------------------- constants
const DAYS = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
const BELLS = [
  { n:'1st', s:'09:00', e:'09:35' }, { n:'2nd', s:'09:35', e:'10:10' }, { n:'3rd', s:'10:10', e:'10:45' },
  { n:'4th', s:'11:05', e:'11:40' }, { n:'5th', s:'11:40', e:'12:15' }, { n:'6th', s:'12:15', e:'12:50' },
  { n:'7th', s:'12:50', e:'13:30' }
];
const RECESS_AFTER = 2;
const SLOTS = DAYS.length * BELLS.length; // 42

const DEFAULT_TEACHERS = {
  P:  { name:"Prabhakar Dubey Sir",  role:"Principal • Maths 9–10" },
  B:  { name:"Brijesh Yadav Sir",    role:"Physics/Chemistry 9–10", bells:[0,1] },
  MA: { name:"Mamta Ma'am",          role:"Hindi & Sanskrit" },
  K:  { name:"Kartikey Sir",         role:"Social Science & Games" },
  AM: { name:"Amisha Pandey Ma'am",  role:"Science & Biology" },
  PW: { name:"Pawan Bhalla Sir",     role:"English" },
  AS: { name:"Astha Pandey Ma'am",   role:"Maths & Computer" },
  DV: { name:"Devvrat Sir",          role:"Computer & GK" },
  DG: { name:"Durgesh Sir",          role:"Primary Maths" },
  PR: { name:"Priyanka Ma'am",       role:"Primary Hindi" },
  SM: { name:"Seema Ma'am",          role:"Primary English" },
  SK: { name:"Seekha Pandey Ma'am",  role:"Class 1st & Drawing" },
  RT: { name:"Rati Ma'am",           role:"EVS & Drawing" },
  RI: { name:"Ritu Ma'am",           role:"UKG" },
  SH: { name:"Shraddha Ma'am",       role:"LKG" },
  MO: { name:"Monika Ma'am",         role:"Playgroup" }
};
const DEFAULT_CLASSES = [
  { c:'10th', ct:'MA' }, { c:'9th', ct:'K' }, { c:'8th', ct:'AM' }, { c:'7th', ct:'PW' }, { c:'6th', ct:'AS' },
  { c:'5th', ct:'DV' }, { c:'4th', ct:'DG' }, { c:'3rd', ct:'PR' }, { c:'2nd', ct:'SM' }, { c:'1st', ct:'SK' },
  { c:'UKG', ct:'RI' }, { c:'LKG', ct:'SH' }, { c:'PG', ct:'MO' }
];
let CLASSES = DEFAULT_CLASSES.map(k => ({ ...k }));
let CLASS_NAMES = CLASSES.map(k => k.c);
function refreshClasses() { CLASSES = (S && S.classes && S.classes.length) ? S.classes : DEFAULT_CLASSES.map(k => ({ ...k })); CLASS_NAMES = CLASSES.map(k => k.c); }

function defaultAlloc() {
  const A = []; const add = (c, s, t, n) => A.push({ c, s, t, n });
  ['10th','9th'].forEach(c => { add(c,'Mathematics','P',8); add(c,'Physics/Chemistry','B',6); add(c,'Biology','AM',5);
    add(c,'English','PW',7); add(c,'Hindi','MA',6); add(c,'Social Science','K',7); add(c,'Computer','AS',3); });
  ['8th','7th','6th'].forEach(c => { add(c,'Mathematics','AS',8); add(c,'Science','AM',8); add(c,'English','PW',7); add(c,'Hindi','MA',5);
    add(c,'Sanskrit','MA',3); add(c,'Social Science','K',6); add(c,'Computer/GK','DV',4); add(c,'Games/PT','K',1); });
  const ct = { '5th':'DV', '4th':'DG', '3rd':'PR', '2nd':'SM' }, dr = { '5th':'RT', '4th':'RT', '3rd':'SK', '2nd':'SK' };
  ['5th','4th','3rd','2nd'].forEach(c => { add(c,'English','SM',7); add(c,'Hindi','PR',6); add(c,'Mathematics','DG',8); add(c,'EVS','RT',7);
    add(c,'Computer/GK','DV',4); add(c,'Drawing',dr[c],3); add(c,'Moral Sc./Games',ct[c],3); add(c,'Library/Activity',ct[c],4); });
  add('1st','English','SK',8); add('1st','Hindi','PR',6); add('1st','Mathematics','SK',8); add('1st','EVS','SK',7);
  add('1st','Computer','DV',3); add('1st','Drawing','SK',3); add('1st','Rhymes & Activity','SK',4); add('1st','Moral Sc./Games','RT',3);
  [['UKG','RI'],['LKG','SH'],['PG','MO']].forEach(([c,t]) => { add(c,'English/Phonics',t,8); add(c,'Hindi/Akshar',t,8); add(c,'Numbers',t,8);
    add(c,'EVS/Discovery',t,6); add(c,'Drawing & Craft',t,6); add(c,'Rhymes & Play',t,6); });
  return A;
}

function seedSyllabus() {
  const mk = list => list.map(n => ({ n, done: null }));
  return {
    '10th|Mathematics': { chapters: mk(['Real Numbers','Polynomials','Pair of Linear Equations in Two Variables','Quadratic Equations','Arithmetic Progressions','Triangles','Coordinate Geometry','Introduction to Trigonometry','Some Applications of Trigonometry','Circles','Areas Related to Circles','Surface Areas and Volumes','Statistics','Probability']), targetCount: '', targetDate: '' },
    '9th|Mathematics': { chapters: mk(['Number Systems','Polynomials','Coordinate Geometry','Linear Equations in Two Variables',"Introduction to Euclid's Geometry",'Lines and Angles','Triangles','Quadrilaterals','Circles',"Heron's Formula",'Surface Areas and Volumes','Statistics']), targetCount: '', targetDate: '' }
  };
}

function freshState() {
  const teachers = {};
  Object.entries(DEFAULT_TEACHERS).forEach(([k, v]) => teachers[k] = { ...v, phone:'' });
  return {
    settings: { school:'SN Mishra Singapore International School', place:'Bhadohi', session:'2026–27', sessionStart:'2026-04-01',
                chairmanPhone:'', attendanceCutoff:'09:30' },
    classes: DEFAULT_CLASSES.map(k => ({ ...k })),
    teachers, alloc: defaultAlloc(), grid: null, students: [],
    staffAtt: {}, studAtt: {}, diary: {}, signed: {}, syl: seedSyllabus(),
    exams: [ { id:'ut1', name:'Unit Test 1', date:'2026-07-20' }, { id:'hy', name:'Half-Yearly', date:'2026-09-25' },
             { id:'ut2', name:'Unit Test 2', date:'2026-11-20' }, { id:'ann', name:'Annual Exam', date:'2027-03-10' } ],
    marks: {}, leaves: [], notices: [], behav: {}, speak: [], lplan: {}, cal: []
  };
}
function emptyState() {
  return { settings: {}, classes: [], teachers: {}, alloc: [], grid: null, students: [], staffAtt: {}, studAtt: {}, diary: {},
           signed: {}, syl: {}, exams: [], marks: {}, leaves: [], notices: [], behav: {}, speak: [], lplan: {}, cal: [], log: [] };
}

// ---------------------------------------------------------------- server sync
// Every change is sent to maakit.in/school/api.php. Each record is one row
// on the server, so two teachers saving at the same time never overwrite
// each other. Changes made by others arrive every 45 seconds.
let S = null;                    // all school data in memory
let ME = null;                   // { role, tid, label }
let TOKEN = null;
let STATUS = null;               // login screen data
let PINS = {};                   // uid -> true when a PIN is set
let SYNCED = {};                 // record key -> JSON last confirmed by server
let pendingLog = [];
let lastV = 0, lastLogId = 0, loadedFrom = null;
const DAYS_LOADED = {};
const DELETABLE = ['student', 'notice', 'teacher', 'behav', 'speak', 'cal'];
const SAFE_VIEWS = ['dash', 'staff', 'timetable', 'diaries', 'results', 'log', 'mytoday', 'myweek', 'attendance'];
let syncTimer = null, syncing = false, netState = 'ok';

function tokenRole() { try { return localStorage.getItem('sch_role'); } catch (e) { return null; } }
function tokenGet() { try { return localStorage.getItem('sch_token'); } catch (e) { return null; } }
function tokenSet(t) { try { t ? localStorage.setItem('sch_token', t) : localStorage.removeItem('sch_token'); } catch (e) {} }

async function api(a, body, q) {
  const url = 'api.php?a=' + a + (q ? '&' + new URLSearchParams(q) : '');
  const opt = { method: body ? 'POST' : 'GET', headers: { 'X-School-Token': TOKEN || '' } };
  if (body) { opt.headers['Content-Type'] = 'application/json'; opt.body = JSON.stringify(body); }
  let r, j = null;
  try { r = await fetch(url, opt); } catch (e) { const er = new Error('No internet connection. Please try again.'); er.net = true; throw er; }
  try { j = await r.json(); } catch (e) {}
  if (r.status === 401 && a !== 'login' && a !== 'setup') { forceLogout(); throw new Error('Please log in again.'); }
  if (!r.ok || !j || !j.ok) throw new Error((j && j.error) || 'Server problem. Please try again.');
  return j;
}

const SEP = '\u0001';
function toRecords() {
  const R = {}, put = (k, r, d) => { R[k + SEP + r] = JSON.stringify(d); };
  if (!S) return R;
  if (S.settings && Object.keys(S.settings).length) put('settings', 'main', S.settings);
  if (S.classes.length) put('classes', 'main', S.classes);
  Object.entries(S.teachers).forEach(([t, v]) => put('teacher', t, v));
  if (S.alloc.length) put('alloc', 'main', S.alloc);
  if (S.grid) put('grid', 'main', S.grid);
  if (S.exams.length) put('exams', 'main', S.exams);
  S.students.forEach(s => put('student', s.id, s));
  Object.entries(S.staffAtt).forEach(([d, v]) => put('staffAtt', d, v));
  Object.entries(S.studAtt).forEach(([d, byC]) => Object.entries(byC).forEach(([c, v]) => put('studAtt', d + '|' + c, v)));
  Object.entries(S.diary).forEach(([d, byT]) => Object.entries(byT).forEach(([t, byB]) => Object.entries(byB).forEach(([b, v]) => put('diary', d + '|' + t + '|' + b, v))));
  Object.entries(S.signed).forEach(([d, byT]) => Object.entries(byT).forEach(([t, v]) => put('signed', d + '|' + t, typeof v === 'object' ? v : {at:v})));
  Object.entries(S.syl).forEach(([k, v]) => put('syl', k, v));
  Object.entries(S.marks).forEach(([e, byK]) => Object.entries(byK).forEach(([k, v]) => put('marks', e + '|' + k, v)));
  S.leaves.forEach(l => put('leave', l.id, l));
  S.notices.forEach(n => put('notice', n.id, n));
  Object.entries(S.behav).forEach(([d, byId]) => Object.entries(byId).forEach(([id, v]) => put('behav', d + '|' + id, v)));
  S.speak.forEach(x => put('speak', x.id, x));
  Object.entries(S.lplan).forEach(([k, v]) => put('lplan', k, v));
  S.cal.forEach(e => put('cal', e.id, e));
  return R;
}
function setRec(k, r, d) {
  const p = r.split('|');
  switch (k) {
    case 'settings': S.settings = d; break;
    case 'classes': S.classes = d; refreshClasses(); break;
    case 'teacher': S.teachers[r] = d; break;
    case 'alloc': S.alloc = d; break;
    case 'grid': S.grid = d; break;
    case 'exams': S.exams = d; break;
    case 'student': { const i = S.students.findIndex(x => x.id === r); if (i >= 0) S.students[i] = d; else S.students.push(d); break; }
    case 'staffAtt': S.staffAtt[r] = d; break;
    case 'studAtt': (S.studAtt[p[0]] = S.studAtt[p[0]] || {})[p[1]] = d; break;
    case 'diary': { const a = S.diary[p[0]] = S.diary[p[0]] || {}; (a[p[1]] = a[p[1]] || {})[p[2]] = d; break; }
    case 'signed': (S.signed[p[0]] = S.signed[p[0]] || {})[p[1]] = d.at || d; break;
    case 'syl': S.syl[r] = d; break;
    case 'marks': { const i = r.indexOf('|'); (S.marks[r.slice(0, i)] = S.marks[r.slice(0, i)] || {})[r.slice(i + 1)] = d; break; }
    case 'leave': { const i = S.leaves.findIndex(x => x.id === r); if (i >= 0) S.leaves[i] = d; else S.leaves.push(d); S.leaves.sort((a, b) => (b.at || 0) - (a.at || 0)); break; }
    case 'notice': { const i = S.notices.findIndex(x => x.id === r); if (i >= 0) S.notices[i] = d; else S.notices.push(d); S.notices.sort((a, b) => String(b.date).localeCompare(String(a.date))); break; }
    case 'behav': (S.behav[p[0]] = S.behav[p[0]] || {})[p[1]] = d; break;
    case 'speak': { const i = S.speak.findIndex(x => x.id === r); if (i >= 0) S.speak[i] = d; else S.speak.push(d); break; }
    case 'lplan': S.lplan[r] = d; break;
    case 'cal': { const i = S.cal.findIndex(x => x.id === r); if (i >= 0) S.cal[i] = d; else S.cal.push(d); S.cal.sort((a, b) => a.date.localeCompare(b.date)); break; }
  }
}
function removeRec(k, r) {
  if (k === 'student') S.students = S.students.filter(x => x.id !== r);
  else if (k === 'notice') S.notices = S.notices.filter(x => x.id !== r);
  else if (k === 'teacher') delete S.teachers[r];
  else if (k === 'behav') { const p = r.split('|'); if (S.behav[p[0]]) delete S.behav[p[0]][p[1]]; }
  else if (k === 'speak') S.speak = S.speak.filter(x => x.id !== r);
  else if (k === 'cal') S.cal = S.cal.filter(x => x.id !== r);
}
function applyRemote(recs) {
  let changed = false; const local = toRecords();
  recs.forEach(rec => {
    const key = rec.k + SEP + rec.r;
    if ((key in local) && local[key] !== SYNCED[key]) return;           // my own unsaved change wins until it is sent
    if (rec.x) { if (key in SYNCED || key in local) { removeRec(rec.k, rec.r); delete SYNCED[key]; changed = true; } return; }
    const js = JSON.stringify(rec.d);
    if (SYNCED[key] === js && local[key] === js) return;
    setRec(rec.k, rec.r, rec.d); SYNCED[key] = js; changed = true;
  });
  return changed;
}
function mergeLog(rows) {
  if (!rows || !rows.length) return;
  const have = new Set(S.log.filter(l => l.id).map(l => l.id));
  rows.forEach(row => {
    lastLogId = Math.max(lastLogId, +row.id);
    if (have.has(+row.id)) return;
    const i = S.log.findIndex(l => l.local && l.k === row.k && l.x === row.x);
    if (i >= 0) S.log.splice(i, 1);
    S.log.push({ id: +row.id, t: +row.ts, u: row.uname, r: row.role, k: row.k, x: row.x });
  });
  S.log.sort((a, b) => b.t - a.t);
  if (S.log.length > 1000) S.log.length = 1000;
}

function isFam() { return !!U && (U.role === 'parent' || U.role === 'student'); }
function save() { if (isFam()) return; clearTimeout(syncTimer); syncTimer = setTimeout(sync, 250); }
async function sync() {
  if (!TOKEN || !S) return;
  if (syncing) { save(); return; }
  const R = toRecords(), put = [], del = [];
  Object.entries(R).forEach(([key, js]) => { if (SYNCED[key] !== js) { const [k, r] = key.split(SEP); put.push({ k, r, d: JSON.parse(js), js }); } });
  Object.keys(SYNCED).forEach(key => { if (!(key in R)) { const [k, r] = key.split(SEP); if (DELETABLE.includes(k)) del.push({ k, r }); else delete SYNCED[key]; } });
  const logs = pendingLog.splice(0);
  if (!put.length && !del.length && !logs.length) return;
  syncing = true; setNet('saving');
  try {
    const res = await api('sync', { put: put.map(({ k, r, d }) => ({ k, r, d })), del, log: logs });
    put.forEach(p => SYNCED[p.k + SEP + p.r] = p.js);
    del.forEach(p => delete SYNCED[p.k + SEP + p.r]);
    if (res.rejected && res.rejected.length) { toast(res.rejected.map(x=>x.error||'Change not allowed for this login.').join(' '), 'a'); setTimeout(async () => { try { await loadAll(); render(); } catch (e) {} }, 800); }
    setNet('ok');
  } catch (e) {
    pendingLog.unshift(...logs);
    setNet('off');
    setTimeout(sync, 15000);
  } finally { syncing = false; }
}
async function poll() {
  if (!TOKEN || !S || document.hidden || isFam()) return;
  try {
    const res = await api('since', null, { v: lastV, l: lastLogId });
    lastV = res.now;
    const ch = applyRemote(res.records);
    const nl = res.log && res.log.length; mergeLog(res.log);
    if (ch || nl) softRender();
    if (netState === 'off') sync(); else setNet('ok');
  } catch (e) { setNet('off'); }
}
function softRender() {
  const a = document.activeElement;
  if (!SAFE_VIEWS.includes(view) || (a && /INPUT|TEXTAREA|SELECT/.test(a.tagName))) return;
  const y = window.scrollY; render(); window.scrollTo(0, y);
}
function setNet(s) {
  netState = s;
  const html = s === 'saving' ? '<span class="dot bg-marigold"></span>Saving' : s === 'off' ? '<span class="dot bg-rose"></span>Offline' : '<span class="dot bg-leaf"></span>Saved';
  ['net', 'net-m'].forEach(id => { const el = document.getElementById(id); if (el) el.innerHTML = html; });
}
window.addEventListener('beforeunload', e => { if (syncing || pendingLog.length || (S && Object.entries(toRecords()).some(([k, v]) => SYNCED[k] !== v))) { e.preventDefault(); e.returnValue = ''; } });
setInterval(poll, 45000);
document.addEventListener('visibilitychange', () => { if (!document.hidden) poll(); });

async function loadAll() {
  const res = await api('load', null, { days: 60 });
  ME = res.me; U = { role: ME.role, tid: ME.tid }; PINS = res.pins || {}; loadedFrom = res.from; lastV = res.now;
  S = emptyState(); SYNCED = {}; lastLogId = 0;
  res.records.forEach(rec => { setRec(rec.k, rec.r, rec.d); SYNCED[rec.k + SEP + rec.r] = JSON.stringify(rec.d); });
  refreshClasses();
  mergeLog(res.log);
  if (!S.settings || !S.settings.school) {
    if (U.role !== 'principal') { S = null; throw new Error('The Principal must log in once to set up the school first.'); }
    const keepLog = S.log; Object.assign(S, freshState()); S.log = keepLog; refreshClasses();
    S.grid = ttGenerate(2026);
    log('Setup', 'School data created: 16 teachers, 13 classes, periods per week and first weekly timetable');
  }
  if (U.role === 'principal') applyBoardPlan();
}
async function ensureDate(d) {
  if (!d || !loadedFrom || d >= loadedFrom || DAYS_LOADED[d]) return;
  try { const res = await api('day', null, { date: d }); res.records.forEach(rec => { setRec(rec.k, rec.r, rec.d); SYNCED[rec.k + SEP + rec.r] = JSON.stringify(rec.d); }); DAYS_LOADED[d] = true; }
  catch (e) { toast(e.message, 'r'); }
}

// ---------------------------------------------------------------- helpers
const pad = n => String(n).padStart(2, '0');
function todayISO() { const d = new Date(); return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()); }
function addDays(iso, n) { const d = new Date(iso + 'T00:00:00'); d.setDate(d.getDate() + n); return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()); }
function dayIdx(iso) { const w = new Date(iso + 'T00:00:00').getDay(); return w === 0 ? -1 : w - 1; } // Sunday -1
function fmtDate(iso) { if (!iso) return ''; const d = new Date(iso + 'T00:00:00'); return d.toLocaleDateString('en-IN', { weekday:'short', day:'2-digit', month:'short', year:'numeric' }); }
function fmtTime(ts) { return new Date(ts).toLocaleString('en-IN', { day:'2-digit', month:'short', hour:'2-digit', minute:'2-digit' }); }
function t12(hhmm) { let [h, m] = hhmm.split(':').map(Number); const ap = h >= 12 ? 'PM' : 'AM'; h = h % 12 || 12; return h + ':' + pad(m) + ' ' + ap; }
function nowMin() { const d = new Date(); return d.getHours() * 60 + d.getMinutes(); }
function toMin(hhmm) { const [h, m] = hhmm.split(':').map(Number); return h * 60 + m; }
function esc(s) { return String(s ?? '').replace(/[&<>"']/g, c => ({ '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;' }[c])); }
function jsq(s) { return esc(JSON.stringify(String(s ?? ''))); }
function uid() { return Date.now().toString(36) + Math.random().toString(36).slice(2, 6); }
function T(tid) { return S.teachers[tid] || { name: tid }; }
function phoneDigits(p) { const d = String(p || '').replace(/\D/g, ''); return d.length === 10 ? '91' + d : d; }
function waLink(phone, text) { const p = phoneDigits(phone); return 'https://wa.me/' + (p || '') + '?text=' + encodeURIComponent(text); }
function toast(msg, kind) {
  const c = { g:'bg-emerald-600', r:'bg-rose-600', a:'bg-amber-500', b:'bg-indigo-700' }[kind || 'g'];
  const el = document.createElement('div');
  el.className = c + ' text-white px-4 py-3 rounded-xl shadow-xl text-sm font-semibold pointer-events-auto';
  el.textContent = msg; document.getElementById('toasts').appendChild(el);
  setTimeout(() => el.remove(), 3500);
}
function grade(p) { return p >= 91 ? 'A1' : p >= 81 ? 'A2' : p >= 71 ? 'B1' : p >= 61 ? 'B2' : p >= 51 ? 'C1' : p >= 41 ? 'C2' : p >= 33 ? 'D' : 'E'; }
function bar(p, color) { return `<div class="w-full bg-slate-200 h-2 rounded-full overflow-hidden"><div class="h-2 ${color || 'bg-indigo-600'}" style="width:${Math.max(0, Math.min(100, p))}%"></div></div>`; }

// ---------------------------------------------------------------- activity log
let U = null; // { role:'principal'|'teacher'|'chairman', tid }
function userName() {
  if (!U) return 'System';
  if (U.role === 'chairman') return 'Chairman';
  const n = S && S.teachers[U.tid] ? S.teachers[U.tid].name : (ME ? ME.label : U.tid);
  return U.role === 'principal' ? n + ' (Principal)' : n;
}
function log(kind, text) {
  pendingLog.push({ k: kind, x: text });
  if (S) { S.log.unshift({ t: Date.now(), u: userName(), r: U ? U.role : 'system', k: kind, x: text, local: true }); if (S.log.length > 1000) S.log.length = 1000; }
  save();
}

// ---------------------------------------------------------------- timetable engine
function rng(seed) { let a = seed >>> 0; return () => { a |= 0; a = a + 0x6D2B79F5 | 0; let t = Math.imul(a ^ a >>> 15, 1 | a); t = t + Math.imul(t ^ t >>> 7, 61 | t) ^ t; return ((t ^ t >>> 14) >>> 0) / 4294967296; }; }
function ttCheck(alloc) {
  const errors = [], warns = [];
  CLASS_NAMES.forEach(c => { const sum = alloc.filter(a => a.c === c).reduce((s, a) => s + (+a.n || 0), 0);
    if (sum > SLOTS) errors.push(`Class ${c}: ${sum} periods set, only ${SLOTS} bells in a week.`);
    else if (sum < SLOTS) warns.push(`Class ${c}: ${SLOTS - sum} bells will be Self Study.`); });
  Object.keys(S.teachers).forEach(t => { const load = alloc.filter(a => a.t === t).reduce((s, a) => s + (+a.n || 0), 0);
    const cap = (S.teachers[t].bells ? S.teachers[t].bells.length : BELLS.length) * DAYS.length;
    if (load > cap) errors.push(`${T(t).name}: ${load} periods but only ${cap} possible.`); });
  alloc.forEach(a => { if (!S.teachers[a.t]) errors.push(`${a.c} ${a.s}: teacher missing.`); });
  return { errors, warns };
}
function ttTry(alloc, rand) {
  const D = DAYS.length, Bn = BELLS.length, N = D * Bn;
  const tk = Object.keys(S.teachers), tI = {}; tk.forEach((t, i) => tI[t] = i);
  const aT = alloc.map(a => tI[a.t]), maxPD = alloc.map(a => Math.ceil((+a.n || 0) / D));
  const okB = alloc.map(a => S.teachers[a.t] && S.teachers[a.t].bells ? S.teachers[a.t].bells : null);
  const heavy = alloc.map(a => /Math|Physics|Science|English|Biology/.test(a.s));
  const light = alloc.map(a => /Games|Drawing|Activity|Library|Rhymes|Craft|Play|Moral/.test(a.s));
  const occ = new Int16Array(tk.length * N), pd = new Int16Array(alloc.length * D);
  const cls = CLASS_NAMES.map(c => {
    const arr = []; alloc.forEach((a, i) => { if (a.c === c) for (let n = 0; n < (+a.n || 0); n++) arr.push(i); });
    while (arr.length < N) arr.push(-1);
    for (let i = arr.length - 1; i > 0; i--) { const j = Math.floor(rand() * (i + 1)); [arr[i], arr[j]] = [arr[j], arr[i]]; }
    arr.forEach((l, s) => { if (l >= 0) { occ[aT[l] * N + s]++; pd[l * D + ((s / Bn) | 0)]++; } });
    return arr;
  });
  const H = 10;
  const tv = k => k < 1e6 ? Math.max(0, occ[k] - 1) : Math.max(0, pd[k - 1e6] - maxPD[((k - 1e6) / D) | 0]);
  const loc = (arr, s) => { const l = arr[s]; if (l < 0) return 0; const b = s % Bn; let v = 0;
    if (okB[l] && !okB[l].includes(b)) v += H;
    if (b > 0 && b !== RECESS_AFTER + 1 && arr[s - 1] === l) v += 1;
    if (b < Bn - 1 && b !== RECESS_AFTER && arr[s + 1] === l) v += 1;
    if (heavy[l] && b >= 5) v += 0.15; if (light[l] && b <= 1) v += 0.15; return v; };
  const keys = (x, y, p, q) => { const ks = new Set(); [x, y].forEach(l => { if (l < 0) return; [p, q].forEach(s => { ks.add(aT[l] * N + s); ks.add(1e6 + l * D + ((s / Bn) | 0)); }); }); return ks; };
  const part = (arr, ks, p, q) => { let v = loc(arr, p) + loc(arr, q); ks.forEach(k => v += H * tv(k)); return v; };
  const mv = (l, f, t) => { if (l < 0) return; occ[aT[l] * N + f]--; pd[l * D + ((f / Bn) | 0)]--; occ[aT[l] * N + t]++; pd[l * D + ((t / Bn) | 0)]++; };
  const hard = () => { let h = 0; for (let k = 0; k < occ.length; k++) if (occ[k] > 1) h += occ[k] - 1;
    for (let k = 0; k < pd.length; k++) h += Math.max(0, pd[k] - maxPD[(k / D) | 0]);
    cls.forEach(arr => arr.forEach((l, s) => { if (l >= 0 && okB[l] && !okB[l].includes(s % Bn)) h++; })); return h; };
  const IT = 300000; let temp = 4; const cool = Math.pow(0.01 / temp, 1 / IT);
  for (let it = 0; it < IT; it++, temp *= cool) {
    const arr = cls[(rand() * cls.length) | 0], p = (rand() * N) | 0, q = (rand() * N) | 0, x = arr[p], y = arr[q];
    if (p === q || x === y) continue;
    const ks = keys(x, y, p, q), before = part(arr, ks, p, q);
    mv(x, p, q); mv(y, q, p); arr[p] = y; arr[q] = x;
    const d = part(arr, ks, p, q) - before;
    if (d > 0 && rand() >= Math.exp(-d / temp)) { mv(x, q, p); mv(y, p, q); arr[p] = x; arr[q] = y; }
  }
  if (hard() > 0) return null;
  const grid = {}; CLASS_NAMES.forEach((c, ci) => grid[c] = Array.from({ length: D }, (_, d) => cls[ci].slice(d * Bn, d * Bn + Bn)));
  return grid;
}
function ttGenerate(seed) { for (let a = 0; a < 6; a++) { const g = ttTry(S.alloc, rng(seed + a * 7919)); if (g) return g; } return null; }
function cell(c, d, b) { const i = S.grid && S.grid[c] ? S.grid[c][d][b] : -1; return i >= 0 ? S.alloc[i] : null; }
function teacherAt(t, d, b) { for (const c of CLASS_NAMES) { const a = cell(c, d, b); if (a && a.t === t) return a; } return null; }
function loadOf(t) { return S.alloc.filter(a => a.t === t).reduce((s, a) => s + (+a.n || 0), 0); }
function myAllocs(t) { return S.alloc.filter(a => a.t === t && a.n > 0); }

// ---------------------------------------------------------------- domain logic
function staffAbsent(date) {
  const manual = (S.staffAtt[date] && S.staffAtt[date].absent) || [];
  const onLeave = S.leaves.filter(l => l.status === 'Approved' && l.from <= date && l.to >= date).map(l => l.tid);
  return [...new Set([...manual, ...onLeave])];
}
function arrangement(date) {
  const d = dayIdx(date); if (d < 0 || !S.grid || holidayOn(date)) return [];
  const absent = staffAbsent(date), extra = {}, slips = [];
  absent.forEach(t => BELLS.forEach((b, i) => {
    const a = teacherAt(t, d, i); if (!a || examBlocks(date, a.c)) return;
    const taken = slips.filter(s => s.bell === i).map(s => s.sub);
    const busy = x => { const q = teacherAt(x, d, i); return q && !examBlocks(date, q.c); };
    const pool = Object.keys(S.teachers).filter(x => !absent.includes(x) && !busy(x) && !taken.includes(x)
      && (!S.teachers[x].bells || S.teachers[x].bells.includes(i)));
    const score = x => { let s = 0;
      if (S.alloc.some(q => q.t === x && q.s === a.s)) s += 5;
      if (S.alloc.some(q => q.t === x && q.c === a.c)) s += 2;
      s -= (extra[x] || 0) * 3; s -= BELLS.filter((_, j) => teacherAt(x, d, j)).length * 0.3;
      if (x === 'P') s -= 4; if (['RI','SH','MO'].includes(x)) s -= 10; return s; };
    pool.sort((x, y) => score(y) - score(x));
    const sub = pool[0] || null; if (sub) extra[sub] = (extra[sub] || 0) + 1;
    slips.push({ bell: i, cls: a.c, subj: a.s, absent: t, sub });
  }));
  return slips.sort((x, y) => x.bell - y.bell);
}
function myBells(tid, date) {
  const d = dayIdx(date); if (d < 0 || !S.grid || holidayOn(date)) return [];
  if (staffAbsent(date).includes(tid)) return [];
  const out = [];
  BELLS.forEach((b, i) => { const a = teacherAt(tid, d, i); if (a && !examBlocks(date, a.c)) out.push({ bell: i, cls: a.c, subj: a.s, arr: false }); });
  arrangement(date).filter(s => s.sub === tid).forEach(s => out.push({ bell: s.bell, cls: s.cls, subj: s.subj, arr: true, for: s.absent }));
  return out.sort((x, y) => x.bell - y.bell);
}
function diaryEntry(date, tid, bell) { return S.diary[date] && S.diary[date][tid] ? S.diary[date][tid][bell] : null; }
function bellOver(date, bell) { const t = todayISO(); if (date < t) return true; if (date > t) return false; return nowMin() >= toMin(BELLS[bell].e); }
function diaryStats(date) {
  let due = 0, filled = 0; const pendingBy = {};
  Object.keys(S.teachers).forEach(t => myBells(t, date).forEach(b => {
    if (!bellOver(date, b.bell)) return; due++;
    if (diaryEntry(date, t, b.bell)) filled++; else pendingBy[t] = (pendingBy[t] || 0) + 1;
  }));
  return { due, filled, pendingBy };
}
function studentsOf(c) { return S.students.filter(s => s.cls === c).sort((a, b) => a.name.localeCompare(b.name)); }
function attOf(date, c) { return S.studAtt[date] ? S.studAtt[date][c] : null; }
function attStats(date) {
  let marked = 0, total = 0, present = 0;
  CLASS_NAMES.forEach(c => { const a = attOf(date, c); const n = studentsOf(c).length;
    if (a) { marked++; total += n; present += n - a.absent.length; } });
  return { marked, total, present };
}
function threeDayAbsentees(date) {
  const out = [];
  CLASS_NAMES.forEach(c => {
    const days = Object.keys(S.studAtt).filter(d => d <= date && S.studAtt[d][c]).sort().slice(-3);
    if (days.length < 3) return;
    studentsOf(c).forEach(s => { if (days.every(d => S.studAtt[d][c].absent.includes(s.id))) out.push(s); });
  });
  return out;
}
function sylKey(c, s) { return c + '|' + s; }
function sylStatus(key, date) {
  const y = S.syl[key]; if (!y || !y.chapters.length) return null;
  const done = y.chapters.filter(ch => ch.done).length, total = y.chapters.length;
  let expected = null, lag = false;
  const dl = sylDeadline(key.split('|')[0]);          // class-wide hard deadline (e.g. Class 10: 100% by 19 Dec)
  const tCount = dl ? total : y.targetCount, tDate = dl || y.targetDate;
  if (tCount && tDate) {
    const st = new Date(S.settings.sessionStart + 'T00:00:00'), en = new Date(tDate + 'T00:00:00'), now = new Date(date + 'T00:00:00');
    const f = Math.max(0, Math.min(1, (now - st) / Math.max(1, en - st)));
    expected = Math.round(tCount * f * 10) / 10;
    lag = expected - done >= 1;
  }
  return { done, total, expected, lag, pct: Math.round(done / total * 100) };
}
function nextChapter(c, s) { const y = S.syl[sylKey(c, s)]; if (!y) return null; const ch = y.chapters.find(x => !x.done); return ch ? ch.n : null; }
function latestExamWithMarks(date) {
  return [...S.exams].filter(e => S.marks[e.id] && Object.keys(S.marks[e.id]).length && e.date <= addDays(date, 30)).sort((a, b) => b.date.localeCompare(a.date))[0] || null;
}
function weakStudents(examId) {
  const out = {}; const M = S.marks[examId] || {};
  Object.entries(M).forEach(([key, rec]) => { const [c, subj] = key.split('|');
    Object.entries(rec.m || {}).forEach(([sid, v]) => { if (v === 'AB' || v === '' || v == null) return;
      const p = (+v) / (+rec.max) * 100; if (p < 40) { (out[sid] = out[sid] || { sid, cls: c, subs: [] }).subs.push(subj + ' ' + Math.round(p) + '%'); } }); });
  return Object.values(out);
}
function studentById(id) { return S.students.find(s => s.id === id); }

function alerts(date) {
  const A = [], isToday = date === todayISO();
  if (dayIdx(date) < 0) return [{ lv:'b', x:'Sunday — no classes.' }];
  const hol = holidayOn(date); if (hol) return [{ lv:'b', x:'Holiday: ' + hol.title + ' — no classes.' }, ...boardAlerts(date)];
  if (!isToday || nowMin() >= toMin(S.settings.attendanceCutoff)) {
    const missing = CLASS_NAMES.filter(c => studentsOf(c).length && !attOf(date, c));
    if (missing.length) A.push({ lv:'r', x:`Student attendance not marked: Class ${missing.join(', ')}`, go:'attendance' });
  }
  const ds = diaryStats(date); const pend = Object.entries(ds.pendingBy);
  if (pend.length) A.push({ lv:'a', x:`Diary pending: ${pend.map(([t, n]) => T(t).name + ' (' + n + ')').join(', ')}`, go:'diaries', wa: pend.map(([t]) => t) });
  const unc = arrangement(date).filter(s => !s.sub);
  if (unc.length) A.push({ lv:'r', x:`${unc.length} bell(s) with no free teacher to cover — see Arrangement`, go:'staff' });
  A.push(...boardAlerts(date));
  const lpw = Object.values(S.lplan).filter(p => p.status === 'Submitted');
  if (lpw.length) A.push({ lv:'a', x:`${lpw.length} lesson plan(s) waiting for your approval`, go:'lplans' });
  const pl = S.leaves.filter(l => l.status === 'Pending');
  if (pl.length) A.push({ lv:'a', x:`${pl.length} leave request(s) waiting for approval`, go:'staff' });
  const ab3 = threeDayAbsentees(date);
  if (ab3.length) A.push({ lv:'r', x:`Absent 3 days in a row: ${ab3.map(s => s.name + ' (' + s.cls + ')').join(', ')}`, go:'attendance' });
  const lag = Object.keys(S.syl).map(k => ({ k, st: sylStatus(k, date) })).filter(x => x.st && x.st.lag);
  if (lag.length) A.push({ lv:'a', x:`Syllabus behind target: ${lag.map(x => x.k.replace('|', ' ')).join(', ')}`, go:'syllabus' });
  const ex = latestExamWithMarks(date);
  if (ex) { const w = weakStudents(ex.id); if (w.length) A.push({ lv:'a', x:`${w.length} student(s) below 40% in ${ex.name} — plan remedial`, go:'results' }); }
  return A;
}
function dailySummary(date) {
  const ab = staffAbsent(date), arr = arrangement(date), as = attStats(date), ds = diaryStats(date);
  const lag = Object.keys(S.syl).filter(k => { const s = sylStatus(k, date); return s && s.lag; }).length;
  return [
    `*${S.settings.school}*`, `Daily Academic Report — ${fmtDate(date)}`, '',
    `Staff present: ${Object.keys(S.teachers).length - ab.length}/${Object.keys(S.teachers).length}${ab.length ? ' (Absent: ' + ab.map(t => T(t).name).join(', ') + ')' : ''}`,
    `Arrangement: ${arr.filter(s => s.sub).length}/${arr.length} bells covered`,
    `Students: ${as.present}/${as.total} present (${as.marked}/${CLASS_NAMES.length} classes marked)`,
    `Teacher diary: ${ds.filled}/${ds.due} bells filled`,
    `Syllabus behind target: ${lag} subject(s)`,
    `3-day absentees: ${threeDayAbsentees(date).length}`,
    `Pending leave requests: ${S.leaves.filter(l => l.status === 'Pending').length}`,
    '', '— Powered by Maakit'
  ].join('\n');
}

// ---------------------------------------------------------------- app shell
let view = null, P = {}; // P = per-view params
async function go(v, params) { view = v; P = params || {}; if (P.date) await ensureDate(P.date); render(); window.scrollTo(0, 0); }

const NAV = {
  principal: [['dash','fa-gauge','Dashboard'],['staff','fa-user-check','Staff & Arrangement'],['timetable','fa-calendar-week','Timetable'],
              ['attendance','fa-clipboard-user','Student Attendance'],['diaries','fa-book-open-reader','Diaries'],['syllabus','fa-list-check','Syllabus'],
              ['lplans','fa-clipboard-check','Lesson Plans'],['exams','fa-file-pen','Exams & Date Sheets'],['quizt','fa-bolt','Quiz Duel'],['results','fa-square-poll-vertical','Results'],['behaviour','fa-hand-holding-heart','Behaviour'],
              ['speaking','fa-microphone','Speaking'],['fame','fa-trophy','Hall of Fame'],['calendar','fa-calendar-days','Calendar'],
              ['students','fa-children','Students'],['notices','fa-bullhorn','Notices'],
              ['mytoday','fa-chalkboard-user','My Teaching'],['log','fa-clock-rotate-left','Activity Log'],['settings','fa-gear','Settings']],
  teacher:   [['mytoday','fa-sun','Today'],['quizt','fa-bolt','Quiz Duel'],['attendance','fa-clipboard-user','Attendance'],['diary','fa-book','Diary'],['lplan','fa-clipboard-check','Lesson Plan'],
              ['speaking','fa-microphone','Speaking'],['behaviour','fa-hand-holding-heart','Behaviour'],['syllabus','fa-list-check','Syllabus'],
              ['marks','fa-pen-to-square','Marks'],['myweek','fa-calendar-week','My Week'],['fame','fa-trophy','Hall of Fame'],['calendar','fa-calendar-days','Calendar'],['leave','fa-plane-departure','Leave']],
  student:   [['phome','fa-house','Home'],['quiz','fa-bolt','Quiz Duel'],['phw','fa-book-open','Homework'],['presults','fa-chart-simple','Progress'],['pnotices','fa-bullhorn','Notices']],
  parent:    [['phome','fa-house','Home'],['quiz','fa-bolt','Quiz Duel'],['phw','fa-book-open','Homework'],['presults','fa-chart-simple','Progress'],['pnotices','fa-bullhorn','Notices']],
  chairman:  [['dash','fa-gauge','Dashboard'],['exams','fa-file-pen','Exams'],['results','fa-square-poll-vertical','Results'],['fame','fa-trophy','Hall of Fame'],['syllabus','fa-list-check','Syllabus'],['calendar','fa-calendar-days','Calendar'],['log','fa-clock-rotate-left','Activity Log']]
};

const PRIMARY = { student: ['phome','quiz','phw','presults'], principal: ['dash','staff','timetable','lplans'], teacher: ['mytoday','attendance','diary','speaking'],
                  chairman: ['dash','results','fame','calendar'], parent: ['phome','quiz','phw','presults'] };
let moreOpen = false;
function navLabel(k) { const n = NAV[U.role].find(x => x[0] === k); return n ? n[2] : ''; }
function render() {
  const root = document.getElementById('root');
  if (!U || !S) { root.innerHTML = vLogin(); return; }
  if (!view || !NAV[U.role].some(x => x[0] === view)) view = NAV[U.role][0][0];
  let body = '';
  try { body = (VIEWS[view] || vDash)(); } catch (e) { console.error(e); body = `<div class="card text-rose">This screen could not open: ${esc(e.message)}. Please go back and try again.</div>`; }
  const side = NAV[U.role].map(([k, ic, lb]) => `<button onclick="moreOpen=false;go('${k}')" class="side-link ${view === k ? 'on' : ''}"><i class="fa-solid ${ic} w-5 text-center"></i><span>${lb}</span></button>`).join('');
  const prim = PRIMARY[U.role], rest = NAV[U.role].filter(x => ['teacher','principal'].includes(U.role) ? !['studio','attendance','saathi','profile'].includes(x[0]) : !prim.includes(x[0]));
  const tab = k => { const n = NAV[U.role].find(x => x[0] === k); return n ? `<button onclick="moreOpen=false;go('${k}')" class="tab ${view === k && !moreOpen ? 'on' : ''}"><i class="fa-solid ${n[1]}"></i><span>${n[2].split(' ')[0]}</span></button>` : ''; };
  const initials = U.role === 'parent' ? 'P' : U.role === 'student' ? (userName() || '?').replace(/\(.*\)/, '').trim().split(/\s+/).map(x => x[0]).slice(0, 2).join('') : (userName() || '?').replace(/\(.*\)/, '').trim().split(/\s+/).map(x => x[0]).slice(0, 2).join('');
  root.innerHTML = `
  <div class="lg:flex min-h-screen">
    <aside class="noprint hidden lg:flex lg:flex-col w-64 shrink-0 bg-ink text-white sticky top-0 h-screen">
      <div class="carpet"></div>
      <div class="px-5 pt-5 pb-4 flex items-center gap-3"><div class="mark">SN</div>
        <div class="min-w-0"><p class="font-semibold leading-tight">${esc(S.settings.school)}</p><p class="text-xs text-white/60">Session ${esc(S.settings.session || '')}</p></div></div>
      <nav class="flex-1 overflow-y-auto px-3 space-y-0.5">${side}</nav>
      <div class="p-4 border-t border-white/10">
        <div class="flex items-center gap-3"><div class="avatar-sm">${esc(initials)}</div><div class="min-w-0 flex-1"><p class="text-sm font-medium truncate">${esc(userName())}</p><p class="text-xs text-white/60 capitalize">${U.role}</p></div></div>
        <div class="flex items-center justify-between mt-3"><span id="net" class="net"></span><button onclick="logout()" class="text-sm text-white/80 hover:text-white">Log out</button></div>
        <p class="text-[11px] text-white/40 mt-3">Powered by Maakit</p>
      </div>
    </aside>
    <div class="flex-1 min-w-0 flex flex-col">
      <header class="noprint lg:hidden sticky top-0 z-30 bg-ink text-white">
        <div class="carpet"></div>
        <div class="px-4 py-3 flex items-center gap-3"><div class="mark mark-sm">SN</div>
          <p class="font-semibold leading-tight truncate flex-1">${esc(S.settings.school)}</p>
          ${isFam() ? '' : '<span id="net-m" class="net"></span>'}
          <button onclick="logout()" class="avatar-sm" title="Log out">${esc(initials)}</button></div>
      </header>
      <main class="w-full max-w-5xl mx-auto p-4 lg:p-8 space-y-4 pb-28 lg:pb-10">${body}
        <p class="text-center text-xs text-slate-400 pt-6 lg:hidden">Powered by Maakit</p></main>
    </div>
  </div>
  ${['teacher','principal'].includes(U.role)?`<nav class="modern-dock noprint" aria-label="Teacher quick actions"><button onclick="go('studio')" aria-label="Camera scan"><i class="fa-solid fa-camera"></i><span>Camera</span></button><button class="${view==='attendance'?'on':''}" onclick="go('attendance')"><i class="fa-solid fa-clipboard-user"></i><span>Roll call</span></button><button onclick="go('saathi')" class="${view==='saathi'?'on':''}"><i class="fa-solid fa-feather"></i><span>Saathi</span></button><button onclick="go('profile')" class="${view==='profile'?'on':''}"><i class="fa-solid fa-user"></i><span>My profile</span></button><button onclick="moreOpen=!moreOpen;render()"><i class="fa-solid fa-grip"></i><span>More</span></button></nav>`:`<nav class="noprint lg:hidden fixed bottom-0 inset-x-0 z-40 bg-white border-t border-line grid ${rest.length ? 'grid-cols-5' : 'grid-cols-4'} pb-[env(safe-area-inset-bottom)]">
    ${prim.map(tab).join('')}${rest.length ? `<button onclick="moreOpen=!moreOpen;render()" class="tab ${moreOpen || !prim.includes(view) ? 'on' : ''}"><i class="fa-solid fa-grip"></i><span>More</span></button>` : ''}
  </nav>`}
  ${moreOpen ? `<div class="noprint lg:hidden fixed inset-0 z-30 bg-ink/40" onclick="moreOpen=false;render()"></div>
    <div class="noprint lg:hidden fixed inset-x-0 bottom-16 z-40 bg-white rounded-t-3xl p-4 pb-6 shadow-2xl">
      <div class="w-10 h-1 rounded-full bg-slate-300 mx-auto mb-4"></div>
      <div class="grid grid-cols-4 gap-2">${rest.map(([k, ic, lb]) => `<button onclick="moreOpen=false;go('${k}')" class="more-item ${view === k ? 'on' : ''}"><i class="fa-solid ${ic}"></i><span>${lb}</span></button>`).join('')}</div>
    </div>` : ''}`;
  setNet(netState);
  if(view==='faculty') loadParentFaculty();
}

// ---------------------------------------------------------------- login / setup
let loginRole = 'teacher', busy = false;
function vLogin() {
  if (!STATUS) return `<div class="flex-1 flex items-center justify-center p-6 text-slate-500">Loading…</div>`;
  const shell = inner => `<div class="min-h-screen lg:grid lg:grid-cols-2">
    <section class="bg-ink text-white relative overflow-hidden flex flex-col">
      <div class="carpet"></div>
      <div class="px-6 py-8 lg:p-14 flex-1 flex flex-col justify-center">
        <div class="mark mark-lg">SN</div>
        <h1 class="text-3xl lg:text-5xl font-semibold leading-tight mt-6 max-w-md">${esc(STATUS.school)}</h1>
        <p class="text-white/70 mt-3 max-w-sm lg:text-lg">Attendance, diaries, lessons and results for every class — on every teacher's phone.</p>
      </div>
      <div class="carpet carpet-wide hidden lg:block"></div>
    </section>
    <section class="flex items-start lg:items-center justify-center p-5 lg:p-10 -mt-6 lg:mt-0">
      <div class="w-full max-w-sm bg-white rounded-3xl shadow-xl lg:shadow-none p-6 space-y-4">${inner}
        <p class="text-xs text-slate-400 text-center pt-2">Powered by Maakit</p></div>
    </section></div>`;
  if (STATUS.setup || STATUS.reset) return shell(`
      <h2 class="text-xl font-semibold">${STATUS.setup ? 'Set up the school' : 'Reset the Principal PIN'}</h2>
      <p class="text-sm text-slate-600">Enter the Maakit website update key (the part after <code>key=</code> in your update link) and choose the Principal's PIN.</p>
      <div><label class="lbl">Website update key</label><input id="su-key" type="password" class="inp" autocomplete="off"></div>
      <div><label class="lbl">New Principal PIN (4–8 digits)</label><input id="su-pin" type="password" inputmode="numeric" maxlength="8" class="inp pin"></div>
      <div><label class="lbl">Type the PIN again</label><input id="su-pin2" type="password" inputmode="numeric" maxlength="8" class="inp pin"></div>
      <button onclick="doSetup()" class="btn btn-p w-full py-3">${STATUS.setup ? 'Set up school' : 'Save new PIN'}</button>
      ${STATUS.reset ? '<button onclick="STATUS.reset=false;render()" class="btn btn-l w-full">Back to log in</button>' : ''}`);
  if (MUST) return shell(`
      <h2 class="text-xl font-semibold">Choose your own PIN</h2>
      <p class="text-sm text-slate-600">You logged in with the first PIN, 1234. Choose your own 4-digit PIN now. Keep it private; you will use it every time.</p>
      <div><label class="lbl">New PIN (4–8 digits)</label><input id="mc-pin" type="password" inputmode="numeric" maxlength="8" class="inp pin"></div>
      <div><label class="lbl">Type the new PIN again</label><input id="mc-pin2" type="password" inputmode="numeric" maxlength="8" class="inp pin" onkeydown="if(event.key==='Enter')saveFirstPin()"></div>
      <button onclick="saveFirstPin()" class="btn btn-p w-full py-3">Save my PIN</button>
      <button onclick="forceLogout()" class="btn btn-l w-full">Cancel</button>`);
  const tOpts = STATUS.teachers.map(t => `<option value="${esc(t.tid)}">${esc(t.name)}</option>`).join('');
  const roles = [['teacher', 'Teacher'], ['student', 'Student'], ['parent', 'Parent'], ['principal', 'Principal'], ['chairman', 'Chairman']];
  return shell(`
      <h2 class="text-xl font-semibold">Log in</h2>
      <div class="seg">${roles.map(([r, l]) => `<button onclick="loginRole='${r}';render()" class="${loginRole === r ? 'on' : ''}">${l}</button>`).join('')}</div>
      ${loginRole === 'teacher' ? (STATUS.teachers.length ? `<div><label class="lbl">Your name</label><select id="lg-t" class="inp">${tOpts}</select></div>` : '<p class="text-sm text-slate-500">The Principal must log in once first.</p>') : ''}
      ${loginRole === 'student' ? `<div><label class="lbl">Admission number</label><input id="lg-adm" class="inp" autocomplete="off" placeholder="e.g. 92"></div>` : ''}
      ${loginRole === 'parent' ? `<div><label class="lbl">Mobile number given to the school</label><input id="lg-ph" type="tel" inputmode="numeric" maxlength="13" class="inp" placeholder="98390 00000"></div>` : ''}
      <div><label class="lbl">PIN</label><input id="lg-pin" type="password" inputmode="numeric" maxlength="8" class="inp pin" onkeydown="if(event.key==='Enter')login()"></div>
      <button onclick="login()" class="btn btn-p w-full py-3">Log in</button>
      <p class="text-xs text-slate-500 text-center">${loginRole === 'principal' ? 'Forgot your PIN? <button onclick="STATUS.reset=true;render()" class="underline text-ink">Reset it with the update key</button>'
        : 'First time? Your PIN is <b>1234</b> — you will then choose your own. Forgot it? Ask the school office to reset it.'}</p>`);
}
async function doSetup() {
  if (busy) return;
  const key = document.getElementById('su-key').value.trim(), pin = document.getElementById('su-pin').value.trim();
  if (pin !== document.getElementById('su-pin2').value.trim()) return toast('The two PINs do not match', 'a');
  if (!/^\d{4,8}$/.test(pin)) return toast('PIN must be 4 to 8 digits', 'a');
  busy = true;
  try { await api('setup', { key, pin }); loginRole = 'principal'; STATUS.reset = false; STATUS.setup = false; await doLogin('principal', null, pin); }
  catch (e) { toast(e.message, 'r'); } finally { busy = false; }
}
async function login() {
  if (busy) return;
  const pin = document.getElementById('lg-pin').value.trim();
  const tid = loginRole === 'teacher' ? (document.getElementById('lg-t') || {}).value : null;
  const phone = loginRole === 'parent' ? (document.getElementById('lg-ph') || {}).value || '' : '';
  const adm = loginRole === 'student' ? ((document.getElementById('lg-adm') || {}).value || '').trim() : '';
  if (loginRole === 'parent' && phone.replace(/\D/g, '').length < 10) return toast('Enter the 10-digit mobile number', 'a');
  if (loginRole === 'student' && !adm) return toast('Enter your admission number', 'a');
  if (!pin) return toast('Enter your PIN', 'a');
  busy = true;
  try { await doLogin(loginRole, tid, pin, phone, adm); } catch (e) { toast(e.message, 'r'); } finally { busy = false; }
}
let MUST = null;   // { role, old } while a 1234 user chooses their own PIN
async function doLogin(role, tid, pin, phone, adm) {
  const res = await api('login', { role, tid, pin, phone, adm });
  TOKEN = res.token; tokenSet(TOKEN); try { localStorage.setItem('sch_role', res.me ? res.me.role : ''); } catch (e) {}
  if (res.mustChange) { MUST = { role: res.me.role, old: pin }; render(); return; }
  await finishLogin(res.me.role);
}
async function finishLogin(role) {
  MUST = null;
  if (role === 'parent' || role === 'student') await loadParent(); else await loadAll();
  view = null; render(); setNet('ok');
}
async function saveFirstPin() {
  if (busy || !MUST) return;
  const pin = document.getElementById('mc-pin').value.trim();
  if (pin !== document.getElementById('mc-pin2').value.trim()) return toast('The two PINs do not match', 'a');
  if (!/^\d{4,8}$/.test(pin)) return toast('PIN must be 4 to 8 digits', 'a');
  if (pin === '1234') return toast('Choose a PIN other than 1234', 'a');
  busy = true;
  try { await api('setpin', { old: MUST.old, pin }); toast('Your PIN is saved'); await finishLogin(MUST.role); }
  catch (e) { toast(e.message, 'r'); } finally { busy = false; }
}
async function logout() {
  if (U && !isFam()) await sync();
  try { await api('logout', {}); } catch (e) {}
  forceLogout();
}
function forceLogout() {
  QZ = null; QT = null; QPLAY = null; qzStopTimer();
  MUST = null; PD = null; PC_LAST = null; moreOpen = false; try { localStorage.removeItem('sch_role'); } catch (e) {}
  TOKEN = null; tokenSet(null); U = null; ME = null; S = null; SYNCED = {}; pendingLog = [];
  refreshStatus();
}
async function refreshStatus() {
  try { STATUS = await api('status'); } catch (e) { STATUS = null; document.getElementById('root').innerHTML = `<div class="flex-1 flex items-center justify-center p-6 text-center"><div><p class="font-bold">Cannot reach the server.</p><p class="text-sm text-slate-500">${esc(e.message)}</p><button onclick="refreshStatus()" class="btn btn-p mt-3">Try again</button></div></div>`; return; }
  render();
}

// ---------------------------------------------------------------- shared bits
function head(title, sub, right) {
  return `<div class="flex flex-col md:flex-row md:items-end justify-between gap-3 pt-1"><div><h2 class="text-2xl lg:text-3xl font-semibold tracking-tight text-ink">${title}</h2>${sub ? `<p class="text-slate-500 mt-1 max-w-2xl">${sub}</p>` : ''}</div>${right ? `<div class="flex flex-wrap gap-2 noprint">${right}</div>` : ''}</div>`;
}
function dateInput(id, val, onchange) { return `<input type="date" id="${id}" value="${val}" onchange="${onchange}" class="inp w-auto">`; }
function bellHead() { let h = ''; BELLS.forEach((b, i) => { h += `<th class="text-center">${b.n}<br><span class="font-mono font-normal normal-case">${t12(b.s)}</span></th>`; if (i === RECESS_AFTER) h += `<th class="text-center bg-slate-200">Recess</th>`; }); return h; }
const RECESS_TD = `<td class="bg-slate-100 text-center text-slate-400">•</td>`;
function printHTML(title, inner) {
  const w = window.open('', '_blank'); if (!w) return toast('Allow pop-ups to print', 'a');
  w.document.write(`<!doctype html><html><head><meta charset="utf-8"><title>${esc(title)}</title><style>
    body{font-family:Arial,sans-serif;margin:14px;font-size:12px;color:#111}h1{font-size:17px;margin:0}h2{font-size:14px;margin:6px 0}
    table{border-collapse:collapse;width:100%;margin-top:6px}th,td{border:1px solid #888;padding:4px;text-align:left;vertical-align:top}th{background:#eee}
    .pb{page-break-after:always}.f{margin-top:14px;font-size:10px;color:#555;display:flex;justify-content:space-between}button,select,input{display:none}@page{margin:10mm}</style></head>
    <body>${inner}</body></html>`);
  w.document.close(); w.focus(); setTimeout(() => w.print(), 300);
}
function printHeader(sub) { return `<h1>${esc(S.settings.school)}, ${esc(S.settings.place)}</h1><div>${sub}</div>`; }
const PRINT_FOOT = `<div class="f"><span>Principal's Signature ______________</span><span>Powered by Maakit</span></div>`;

// ---------------------------------------------------------------- PRINCIPAL / CHAIRMAN DASHBOARD
function vDash() {
  const date = P.date || todayISO(), ro = U.role === 'chairman';
  const ab = staffAbsent(date), as = attStats(date), ds = diaryStats(date), arr = arrangement(date);
  const sylKeys = Object.keys(S.syl).filter(k => S.syl[k].chapters.length);
  const lag = sylKeys.filter(k => sylStatus(k, date).lag).length;
  const al = alerts(date);
  const tile = (lbl, val, sub, ic, col) => `<div class="card tile"><div class="flex items-center gap-2 text-sm text-slate-600"><span class="w-8 h-8 rounded-full ${col} flex items-center justify-center text-sm"><i class="fa-solid ${ic}"></i></span>${lbl}</div><p class="text-3xl font-semibold mt-3 text-ink">${val}</p><p class="text-xs text-slate-500 mt-1">${sub}</p></div>`;
  const nT = Object.keys(S.teachers).length;
  const sum = dailySummary(date);
  return head('School Dashboard', fmtDate(date) + (dayIdx(date) >= 0 ? ' • ' + DAYS[dayIdx(date)] + ' timetable' : ''), dateInput('d-date', date, "go('dash',{date:this.value})")) + `
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
      ${tile('Staff Present', (nT - ab.length) + '/' + nT, ab.length ? 'Absent: ' + ab.map(t => T(t).name).join(', ') : 'Everyone present', 'fa-user-check', 'bg-emerald-50 text-emerald-700')}
      ${tile('Students Present', as.total ? Math.round(as.present / as.total * 100) + '%' : '—', as.marked + '/' + CLASS_NAMES.length + ' classes marked', 'fa-children', 'bg-blue-50 text-blue-700')}
      ${tile('Teacher Diary', ds.filled + '/' + ds.due, 'bells filled (finished bells)', 'fa-book-open', 'bg-purple-50 text-purple-700')}
      ${tile('Syllabus Behind', lag, 'of ' + sylKeys.length + ' tracked subjects', 'fa-chart-line', lag ? 'bg-rose-50 text-rose-700' : 'bg-emerald-50 text-emerald-700')}
    </div>
    ${wordCard(date, true)}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
      <div class="card lg:col-span-2 space-y-2">
        <h3 class="font-bold"><i class="fa-solid fa-bell text-amber-500 mr-1"></i> Automatic Alerts</h3>
        ${al.length ? al.map(a => `<div class="p-3 rounded-xl border flex flex-col sm:flex-row sm:items-center justify-between gap-2 ${a.lv === 'r' ? 'bg-rose-50 border-rose-200' : a.lv === 'a' ? 'bg-amber-50 border-amber-200' : 'bg-slate-50 border-slate-200'}">
          <span class="text-sm">${esc(a.x)}</span>
          <span class="flex gap-1 shrink-0">${a.wa && !ro ? a.wa.filter(t => S.teachers[t].phone).map(t => `<a target="_blank" href="${waLink(S.teachers[t].phone, 'Namaste ' + T(t).name + ', please fill today\'s diary in the school ERP. — Principal')}" class="btn btn-sm btn-wa"><i class="fa-brands fa-whatsapp"></i>${esc(T(t).name.split(' ')[0])}</a>`).join('') : ''}
          ${a.go && !ro ? `<button onclick="go('${a.go}',{date:'${date}'})" class="btn btn-sm btn-l">Open</button>` : ''}</span></div>`).join('')
          : '<p class="text-sm text-emerald-700 font-semibold"><i class="fa-solid fa-circle-check"></i> All clear. Nothing needs attention.</p>'}
      </div>
      <div class="card space-y-3">
        <h3 class="font-bold"><i class="fa-solid fa-file-lines text-indigo-600 mr-1"></i> Daily Summary (auto)</h3>
        <pre class="whitespace-pre-wrap text-xs bg-slate-50 border border-slate-200 rounded-xl p-3 font-sans">${esc(sum)}</pre>
        <div class="flex flex-wrap gap-2">
          ${!ro ? `<a target="_blank" onclick="log('Summary','Daily summary sent to Chairman for ${date}')" href="${waLink(S.settings.chairmanPhone, sum)}" class="btn btn-sm btn-wa"><i class="fa-brands fa-whatsapp"></i> Send to Chairman</a>` : ''}
          <button onclick="navigator.clipboard&&navigator.clipboard.writeText(document.querySelector('pre').innerText).then(()=>toast('Copied'))" class="btn btn-sm btn-l"><i class="fa-regular fa-copy"></i> Copy</button>
        </div>
        ${!ro && !S.settings.chairmanPhone ? '<p class="text-[11px] text-slate-500">Add the Chairman\'s number in Settings to send directly.</p>' : ''}
      </div>
    </div>
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
      <div class="card"><h3 class="font-bold mb-2">Arrangement Today</h3>
        ${arr.length ? `<table class="tbl"><tr><th>Bell</th><th>Class</th><th>Absent</th><th>Substitute</th></tr>${arr.map(s => `<tr><td>${BELLS[s.bell].n}</td><td>${s.cls} ${esc(s.subj)}</td><td>${esc(T(s.absent).name)}</td><td class="${s.sub ? 'font-semibold' : 'text-rose-600 font-bold'}">${s.sub ? esc(T(s.sub).name) : 'Not covered'}</td></tr>`).join('')}</table>` : '<p class="text-sm text-slate-500">No arrangement needed.</p>'}
      </div>
      <div class="card"><h3 class="font-bold mb-2">Recent Activity</h3>
        <div class="space-y-1.5 text-sm">${S.log.slice(0, 8).map(l => `<div class="flex gap-2"><span class="text-slate-400 text-xs w-28 shrink-0">${fmtTime(l.t)}</span><span><b>${esc(l.u)}</b> — ${esc(l.x)}</span></div>`).join('') || '<p class="text-slate-500">No activity yet.</p>'}</div>
        <button onclick="go('log')" class="btn btn-sm btn-l mt-3">Full Activity Log</button>
      </div>
    </div>`;
}

// ---------------------------------------------------------------- STAFF ATTENDANCE + ARRANGEMENT + LEAVE APPROVAL
function vStaff() {
  const date = P.date || todayISO(); const ab = staffAbsent(date), manual = (S.staffAtt[date] && S.staffAtt[date].absent) || [];
  const leaveFor = t => S.leaves.find(l => l.status === 'Approved' && l.tid === t && l.from <= date && l.to >= date);
  const cards = Object.entries(S.teachers).map(([k, t]) => { const isAb = ab.includes(k), lv = leaveFor(k);
    return `<button onclick="${lv ? "toast('On approved leave — cancel the leave to mark present','a')" : `toggleStaff('${date}','${k}')`}" class="text-left p-3 rounded-xl border flex items-center justify-between gap-2 ${isAb ? 'bg-rose-50 border-rose-300' : 'bg-emerald-50/60 border-emerald-200'}">
      <span><span class="block font-bold text-sm">${esc(t.name)}</span><span class="text-[11px] text-slate-500">${esc(t.role)}${lv ? ' • On leave' : ''}</span></span>
      <span class="pill ${isAb ? 'bg-rose-600 text-white' : 'bg-emerald-600 text-white'}">${isAb ? 'ABSENT' : 'P'}</span></button>`; }).join('');
  const arr = arrangement(date);
  const pend = S.leaves.filter(l => l.status === 'Pending');
  return head('Staff Attendance & Arrangement', 'Everyone is Present by default. Tap only the absent teachers — arrangement slips update instantly.',
      dateInput('s-date', date, "go('staff',{date:this.value})") + `<button onclick="printSlips('${date}')" class="btn btn-l"><i class="fa-solid fa-print"></i> Print Slips</button>`) + `
    ${pend.length ? `<div class="card space-y-2"><h3 class="font-bold">Leave Requests</h3>${pend.map(l => `<div class="p-3 rounded-xl bg-amber-50 border border-amber-200 flex flex-col sm:flex-row sm:items-center justify-between gap-2">
      <span class="text-sm"><b>${esc(T(l.tid).name)}</b> • ${fmtDate(l.from)}${l.to !== l.from ? ' to ' + fmtDate(l.to) : ''} • ${esc(l.reason)}</span>
      <span class="flex gap-2"><button onclick="decideLeave('${l.id}','Approved')" class="btn btn-sm btn-g">Approve</button><button onclick="decideLeave('${l.id}','Rejected')" class="btn btn-sm btn-r">Reject</button></span></div>`).join('')}</div>` : ''}
    <div class="card"><div class="flex justify-between mb-3"><h3 class="font-bold">${fmtDate(date)}</h3><span class="text-sm"><span class="pill bg-emerald-100 text-emerald-800">${Object.keys(S.teachers).length - ab.length} present</span> <span class="pill bg-rose-100 text-rose-800">${ab.length} absent</span></span></div>
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-2">${cards}</div></div>
    <div class="card"><h3 class="font-bold mb-2">Arrangement Slips</h3>
      ${dayIdx(date) < 0 ? '<p class="text-sm text-slate-500">Sunday.</p>' : arr.length ? `<div class="space-y-2">${arr.map((s, n) => `<div class="p-3 rounded-xl border ${s.sub ? 'bg-slate-50 border-slate-200' : 'bg-rose-50 border-rose-300'} flex flex-col sm:flex-row sm:items-center justify-between gap-2">
        <span class="text-sm"><span class="pill bg-rose-600 text-white mr-1">#${n + 1}</span><b>${BELLS[s.bell].n} bell (${t12(BELLS[s.bell].s)})</b> • Class ${s.cls} • ${esc(s.subj)}<br>
        <span class="text-slate-600">${s.sub ? 'Substitute: <b>' + esc(T(s.sub).name) + '</b>' : '<b class="text-rose-700">No free teacher — Principal to supervise</b>'} (for ${esc(T(s.absent).name)})</span></span>
        ${s.sub && S.teachers[s.sub].phone ? `<a target="_blank" href="${waLink(S.teachers[s.sub].phone, `Arrangement: ${BELLS[s.bell].n} bell (${t12(BELLS[s.bell].s)}), Class ${s.cls} ${s.subj}, in place of ${T(s.absent).name}. — Principal`)}" class="btn btn-sm btn-wa"><i class="fa-brands fa-whatsapp"></i> Inform</a>` : ''}</div>`).join('')}</div>`
      : '<p class="text-sm text-slate-500">No arrangement needed.</p>'}</div>`;
}
function toggleStaff(date, t) {
  S.staffAtt[date] = S.staffAtt[date] || { absent: [] };
  const a = S.staffAtt[date].absent, i = a.indexOf(t);
  if (i >= 0) a.splice(i, 1); else a.push(t);
  log('Staff Attendance', `${T(t).name} marked ${i >= 0 ? 'PRESENT' : 'ABSENT'} for ${date}`);
  const slips = arrangement(date).filter(s => s.absent === t);
  if (i < 0 && slips.length) log('Arrangement', `Auto-arranged ${slips.length} bell(s) for ${T(t).name}: ` + slips.map(s => `${BELLS[s.bell].n} ${s.cls}→${s.sub ? T(s.sub).name : 'uncovered'}`).join('; '));
  render();
}
function decideLeave(id, st) {
  const l = S.leaves.find(x => x.id === id); if (!l) return; l.status = st; l.decidedAt = Date.now();
  log('Leave', `${st} leave of ${T(l.tid).name} (${l.from} to ${l.to})` + (st === 'Approved' ? ' — auto-marked absent & arrangement created' : ''));
  toast('Leave ' + st.toLowerCase()); render();
}
function printSlips(date) {
  const arr = arrangement(date); if (!arr.length) return toast('No slips for this date', 'b');
  printHTML('Arrangement Slips', arr.map((s, n) => `<div style="border:1.5px dashed #333;padding:10px;margin-bottom:10px;page-break-inside:avoid">
    <b>${esc(S.settings.school)} — Arrangement Slip #${n + 1}</b><br>${fmtDate(date)} • ${BELLS[s.bell].n} bell (${t12(BELLS[s.bell].s)}–${t12(BELLS[s.bell].e)}) • Class ${s.cls} • ${esc(s.subj)}<br>
    Substitute: <b>${s.sub ? esc(T(s.sub).name) : '__________'}</b> &nbsp; In place of: ${esc(T(s.absent).name)}<br><br>Received: ________ &nbsp;&nbsp; Principal: ________ <span style="float:right;font-size:10px;color:#666">Powered by Maakit</span></div>`).join(''));
  log('Arrangement', `Printed ${arr.length} arrangement slip(s) for ${date}`);
}

// ---------------------------------------------------------------- TIMETABLE (principal)
function vTimetable() {
  const mode = P.mode || 'day', d = P.d != null ? P.d : Math.max(0, dayIdx(todayISO())), c = P.c || '10th';
  const tabs = [['day','All Classes (Day)'],['class','One Class (Week)'],['teacher','Teacher-Wise'],['setup','Setup Periods']]
    .map(([k, l]) => `<button onclick="go('timetable',{mode:'${k}',d:${d},c:'${c}'})" class="btn btn-sm ${mode === k ? 'btn-p' : 'btn-l'}">${l}</button>`).join('');
  const dayBtns = DAYS.map((x, i) => `<button onclick="go('timetable',{mode:'${mode}',d:${i},c:'${c}'})" class="btn btn-sm ${i === d ? 'bg-slate-900 text-white' : 'btn-l'}">${x.slice(0, 3)}</button>`).join('');
  const { errors, warns } = ttCheck(S.alloc);
  let body = '';
  if (mode === 'setup') body = ttSetup();
  else if (!S.grid) body = '<p class="text-sm">No timetable yet. Press <b>Generate New</b>.</p>';
  else if (mode === 'class') {
    body = `<div class="mb-2 flex items-center gap-2"><span class="lbl mb-0">Class</span><select class="inp w-auto" onchange="go('timetable',{mode:'class',d:${d},c:this.value})">${CLASS_NAMES.map(x => `<option ${x === c ? 'selected' : ''}>${x}</option>`).join('')}</select></div>
      <h3 class="font-bold mb-2">Class ${c} — Class Teacher: ${esc(T(CLASSES.find(k => k.c === c).ct).name)}</h3>
      <div class="overflow-x-auto"><table class="tbl"><tr><th>Day</th>${bellHead()}</tr>${DAYS.map((day, di) => `<tr><td class="font-bold">${day}</td>${BELLS.map((b, i) => { const a = cell(c, di, i); return (a ? `<td><b>${esc(a.s)}</b><br><span class="text-xs text-slate-500">${esc(T(a.t).name)}</span></td>` : '<td class="text-slate-400 italic">Self Study</td>') + (i === RECESS_AFTER ? RECESS_TD : ''); }).join('')}</tr>`).join('')}</table></div>`;
  } else if (mode === 'teacher') {
    body = `<div class="flex flex-wrap gap-1 mb-2">${dayBtns}</div><h3 class="font-bold mb-2">${DAYS[d]} — Teacher-wise <span class="pill bg-emerald-100 text-emerald-800">Green = free bell</span></h3>
      <div class="overflow-x-auto"><table class="tbl"><tr><th>Teacher</th><th>Per week</th>${bellHead()}</tr>${Object.entries(S.teachers).map(([k, t]) => `<tr><td class="font-bold whitespace-nowrap">${esc(t.name)}</td><td class="text-center">${loadOf(k)}</td>${BELLS.map((b, i) => { const a = teacherAt(k, d, i);
        return (a ? `<td><b>${a.c}</b><br><span class="text-xs">${esc(a.s)}</span></td>` : (t.bells && !t.bells.includes(i)) ? '<td class="text-slate-300 text-xs">Not in school</td>' : '<td class="bg-emerald-100 text-emerald-800 font-bold text-center">FREE</td>') + (i === RECESS_AFTER ? RECESS_TD : ''); }).join('')}</tr>`).join('')}</table></div>`;
  } else {
    body = `<div class="flex flex-wrap gap-1 mb-2">${dayBtns}</div><h3 class="font-bold mb-2">${DAYS[d]} — All Classes</h3>
      <div class="overflow-x-auto"><table class="tbl"><tr><th>Class</th>${bellHead()}</tr>${CLASS_NAMES.map(cl => `<tr><td class="font-bold">${cl}</td>${BELLS.map((b, i) => { const a = cell(cl, d, i); return (a ? `<td><b>${esc(a.s)}</b><br><span class="text-xs text-slate-500">${esc(T(a.t).name)}</span></td>` : '<td class="text-slate-400 italic">Self Study</td>') + (i === RECESS_AFTER ? RECESS_TD : ''); }).join('')}</tr>`).join('')}</table></div>`;
  }
  return head('Weekly Timetable', 'Assembly 08:40–09:00 • 7 bells • Recess 10:45–11:05 • Mon–Sat • Brijesh Sir: 1st & 2nd bell only',
      `<button onclick="genTT()" class="btn btn-g"><i class="fa-solid fa-wand-magic-sparkles"></i> Generate New</button><button onclick="printTT()" class="btn btn-l"><i class="fa-solid fa-print"></i> Print</button>`) + `
    <div class="flex flex-wrap gap-2">${tabs}</div>
    ${errors.length ? `<div class="card bg-rose-50 border-rose-200 text-sm text-rose-800">${errors.map(esc).join('<br>')}</div>` : ''}
    ${warns.length && mode === 'setup' ? `<div class="card bg-amber-50 border-amber-200 text-sm text-amber-800">${warns.map(esc).join('<br>')}</div>` : ''}
    <div class="card" id="tt-body">${body}</div>`;
}
function ttSetup() {
  const tOpts = sel => Object.entries(S.teachers).map(([k, t]) => `<option value="${k}" ${k === sel ? 'selected' : ''}>${esc(t.name)}</option>`).join('');
  return `<p class="text-sm text-slate-600 mb-3">Each class must total ${SLOTS} periods a week (6 days × 7 bells). Change teacher or count, then press <b>Generate New</b>.</p>
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-3">${CLASS_NAMES.map(c => { const items = S.alloc.map((a, i) => ({ a, i })).filter(x => x.a.c === c); const sum = items.reduce((s, x) => s + (+x.a.n || 0), 0);
      return `<div class="border border-slate-200 rounded-xl p-3"><div class="flex justify-between mb-2"><b>Class ${c}</b><span class="pill ${sum === SLOTS ? 'bg-emerald-100 text-emerald-800' : sum > SLOTS ? 'bg-rose-100 text-rose-800' : 'bg-amber-100 text-amber-800'}">${sum}/${SLOTS}</span></div>
      ${items.map(({ a, i }) => `<div class="flex gap-1 mb-1"><input value="${esc(a.s)}" onchange="allocEdit(${i},'s',this.value)" class="inp py-1 px-2 text-xs flex-1 min-w-0"><select onchange="allocEdit(${i},'t',this.value)" class="inp py-1 px-1 text-xs w-32">${tOpts(a.t)}</select><input type="number" min="0" max="14" value="${a.n}" onchange="allocEdit(${i},'n',this.value)" class="inp py-1 px-1 text-xs w-12 text-center"><button onclick="allocDel(${i})" class="text-rose-500 px-1"><i class="fa-solid fa-xmark"></i></button></div>`).join('')}
      <button onclick="allocAdd('${c}')" class="text-indigo-700 text-xs font-bold mt-1"><i class="fa-solid fa-plus"></i> Add subject</button></div>`; }).join('')}</div>
    <div class="mt-4"><h4 class="font-bold mb-2">Teacher load (periods / week)</h4><div class="flex flex-wrap gap-2 text-xs">${Object.entries(S.teachers).map(([k, t]) => `<span class="pill ${loadOf(k) > 38 ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-700'}">${esc(t.name)}: ${loadOf(k)}</span>`).join('')}</div></div>
    <button onclick="if(confirm('Reset all periods to default?')){S.alloc=defaultAlloc();log('Timetable','Periods reset to default');render()}" class="btn btn-sm btn-l mt-3">Reset to default</button>`;
}
function allocEdit(i, f, v) { if (f === 's') v = String(v).replace(/\|/g, '/').trim() || 'Subject'; const old = S.alloc[i][f]; S.alloc[i][f] = f === 'n' ? Math.max(0, parseInt(v) || 0) : v; log('Timetable Setup', `Class ${S.alloc[i].c}: ${f === 'n' ? 'periods' : f === 't' ? 'teacher' : 'subject'} changed ${f === 't' ? T(old).name + ' → ' + T(v).name : old + ' → ' + S.alloc[i][f]} (${S.alloc[i].s})`); render(); }
function allocDel(i) { const a = S.alloc[i]; S.alloc.splice(i, 1); S.grid = null; log('Timetable Setup', `Removed ${a.s} from Class ${a.c}`); render(); }
function allocAdd(c) { let at = -1; S.alloc.forEach((a, i) => { if (a.c === c) at = i; }); S.alloc.splice(at + 1, 0, { c, s:'New Subject', t: CLASSES.find(k => k.c === c).ct, n: 1 }); S.grid = null; save(); render(); }
function genTT() {
  const { errors } = ttCheck(S.alloc); if (errors.length) return toast('Fix the red items first (Setup Periods).', 'r');
  toast('Generating… please wait', 'b');
  setTimeout(() => { const g = ttGenerate(Math.floor(Math.random() * 1e9));
    if (!g) return toast('Could not fit this load — reduce a busy teacher\'s periods.', 'r');
    S.grid = g; log('Timetable', 'New weekly timetable generated (0 clashes)'); toast('Timetable ready — 0 clashes'); go('timetable', { ...P, mode: P.mode === 'setup' ? 'day' : (P.mode || 'day') }); }, 50);
}
function printTT() { const el = document.getElementById('tt-body'); if (!el) return; printHTML('Timetable', printHeader('Weekly Timetable • Session ' + esc(S.settings.session)) + el.innerHTML + PRINT_FOOT); log('Timetable', 'Timetable printed'); }

// ---------------------------------------------------------------- TEACHER: TODAY
function vMyToday() {
  const tid = U.tid, date = P.date || todayISO(), bells = myBells(tid, date), absent = staffAbsent(date).includes(tid);
  const ctOf = CLASSES.filter(k => k.ct === tid).map(k => k.c);
  const notices = S.notices.filter(n => n.to !== 'Parents').slice(0, 3);
  const myLeaves = S.leaves.filter(l => l.tid === tid).slice(0, 2);
  return head(`Namaste, ${esc(T(tid).name)}`, fmtDate(date) + (dayIdx(date) >= 0 ? '' : ' • Sunday'), dateInput('m-date', date, "go('mytoday',{date:this.value})")) + `
    ${absent ? '<div class="card bg-rose-50 border-rose-200 text-rose-800 font-semibold">You are marked absent / on leave for this day.</div>' : ''}
    ${teacherCorner(date)}
    ${ctOf.map(c => { const a = attOf(date, c); return `<div class="card flex flex-col sm:flex-row sm:items-center justify-between gap-2 ${a ? '' : 'border-amber-300 bg-amber-50'}">
      <span><b>Class ${c} attendance</b> (you are Class Teacher): ${a ? `<span class="text-emerald-700 font-semibold">Done — ${studentsOf(c).length - a.absent.length}/${studentsOf(c).length} present</span>` : '<span class="text-amber-700 font-semibold">Not marked yet</span>'}</span>
      <button onclick="go('attendance',{c:'${c}',date:'${date}'})" class="btn btn-sm ${a ? 'btn-l' : 'btn-p'}">${a ? 'View' : 'Mark now'}</button></div>`; }).join('')}
    <div class="card"><h3 class="font-bold mb-3">My Bells</h3>
      ${bells.length ? `<div class="space-y-2">${bells.map(b => { const e = diaryEntry(date, tid, b.bell);
        return `<div class="p-3 rounded-xl border flex items-center justify-between gap-2 ${b.arr ? 'bg-rose-50 border-rose-200' : 'bg-slate-50 border-slate-200'}">
          <div><span class="font-mono text-xs text-slate-500">${t12(BELLS[b.bell].s)}–${t12(BELLS[b.bell].e)}</span><br><b>${BELLS[b.bell].n} • Class ${b.cls} • ${esc(b.subj)}</b>
          ${b.arr ? `<span class="pill bg-rose-600 text-white ml-1">ARRANGEMENT for ${esc(T(b.for).name)}</span>` : ''}
          ${e ? `<p class="text-xs text-slate-600 mt-1">Topic: ${esc(e.topic)}</p>` : ''}</div>
          <button onclick="go('diary',{date:'${date}',bell:${b.bell}})" class="btn btn-sm ${e ? 'btn-l' : 'btn-p'}">${e ? '<i class="fa-solid fa-check text-emerald-600"></i> Diary' : 'Fill diary'}</button></div>`; }).join('')}</div>`
        : `<p class="text-sm text-slate-500">${dayIdx(date) < 0 ? 'Sunday — no classes.' : holidayOn(date) ? 'Holiday: ' + esc(holidayOn(date).title) : 'No bells assigned.'}</p>`}</div>
    ${notices.length ? `<div class="card"><h3 class="font-bold mb-2">Notices</h3>${notices.map(n => `<div class="border-l-4 border-indigo-500 pl-3 mb-2"><b>${esc(n.title)}</b> <span class="text-xs text-slate-400">${fmtDate(n.date)}</span><p class="text-sm text-slate-600 whitespace-pre-wrap">${esc(n.body)}</p></div>`).join('')}</div>` : ''}
    ${myLeaves.length ? `<div class="card"><h3 class="font-bold mb-2">My Leave Requests</h3>${myLeaves.map(l => `<p class="text-sm">${fmtDate(l.from)} • ${esc(l.reason)} — <b class="${l.status === 'Approved' ? 'text-emerald-700' : l.status === 'Rejected' ? 'text-rose-700' : 'text-amber-700'}">${l.status}</b></p>`).join('')}</div>` : ''}`;
}
function vMyWeek() {
  const tid = U.tid;
  return head('My Weekly Timetable', esc(T(tid).name) + ' • ' + loadOf(tid) + ' periods / week') + `<div class="card overflow-x-auto"><table class="tbl"><tr><th>Day</th>${bellHead()}</tr>
    ${DAYS.map((day, d) => `<tr><td class="font-bold">${day}</td>${BELLS.map((b, i) => { const a = teacherAt(tid, d, i); return (a ? `<td><b>${a.c}</b><br><span class="text-xs">${esc(a.s)}</span></td>` : '<td class="bg-emerald-50 text-emerald-700 text-xs font-bold text-center">Free</td>') + (i === RECESS_AFTER ? RECESS_TD : ''); }).join('')}</tr>`).join('')}</table></div>`;
}

// ---------------------------------------------------------------- STUDENT ATTENDANCE
let attDraft = null;
function vAttendance() {
  const date = P.date || todayISO();
  const allowed = U.role === 'principal' ? CLASS_NAMES : CLASSES.filter(k => k.ct === U.tid).map(k => k.c);
  if (!allowed.length) return head('Student Attendance') + '<div class="card text-sm text-slate-600">Only class teachers mark attendance. You are not a class teacher.</div>';
  const c = allowed.includes(P.c) ? P.c : allowed[0];
  const saved = attOf(date, c), list = studentsOf(c);
  if (!attDraft || attDraft.key !== date + c) attDraft = { key: date + c, absent: saved ? [...saved.absent] : [] };
  const hw = homeworkFor(date, c);
  const ab3 = threeDayAbsentees(date).filter(s => s.cls === c);
  const classBtns = allowed.length > 1 ? `<select class="inp w-auto" onchange="go('attendance',{c:this.value,date:'${date}'})">${allowed.map(x => `<option ${x === c ? 'selected' : ''}>${x}</option>`).join('')}</select>` : '';
  const overview = U.role === 'principal' ? `<div class="card"><h3 class="font-bold mb-2">All classes — ${fmtDate(date)}</h3><div class="flex flex-wrap gap-2">${CLASS_NAMES.map(x => { const a = attOf(date, x), n = studentsOf(x).length;
      return `<button onclick="go('attendance',{c:'${x}',date:'${date}'})" class="pill ${a ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800'}">${x}: ${a ? (n - a.absent.length) + '/' + n : 'not marked'}</button>`; }).join('')}</div></div>` : '';
  return head(`Attendance — Class ${c}`, 'Everyone is Present by default. Tap only the absent students, then Save.', classBtns + dateInput('a-date', date, `go('attendance',{c:'${c}',date:this.value})`)) + overview + `
    <div class="card">
      <div class="flex justify-between items-center mb-3"><span class="text-sm">${saved ? `<span class="pill bg-emerald-100 text-emerald-800">Saved ${fmtTime(saved.at)} by ${esc(saved.by)}</span>` : '<span class="pill bg-amber-100 text-amber-800">Not saved yet</span>'}</span>
      <span class="text-sm font-bold"><span class="text-emerald-700">${list.length - attDraft.absent.length} P</span> / <span class="text-rose-700">${attDraft.absent.length} A</span></span></div>
      ${list.length ? `<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2">${list.map(s => { const a = attDraft.absent.includes(s.id);
        return `<button onclick="attToggle('${s.id}')" class="text-left p-3 rounded-xl border flex justify-between items-center ${a ? 'bg-rose-50 border-rose-300' : 'bg-white border-slate-200'}"><span><b class="text-sm">${esc(s.name)}</b><br><span class="text-[11px] text-slate-500">Adm ${esc(s.adm)}${s.sample ? ' • sample' : ''}</span></span><span class="pill ${a ? 'bg-rose-600 text-white' : 'bg-emerald-600 text-white'}">${a ? 'A' : 'P'}</span></button>`; }).join('')}</div>
        <button onclick="attSave('${date}','${c}')" class="btn btn-g w-full mt-4 py-3"><i class="fa-solid fa-floppy-disk"></i> Save Attendance</button>` : '<p class="text-sm text-slate-500">No students in this class yet. Principal can add them in Students.</p>'}
    </div>
    ${saved && saved.absent.length ? `<div class="card"><h3 class="font-bold mb-2">Inform parents of absent students</h3><div class="space-y-2">${saved.absent.map(id => { const s = studentById(id); if (!s) return '';
      const msg = `Dear Parent,\n${s.name} (Class ${c}) is absent from school today, ${fmtDate(date)}.\nआज ${s.name} विद्यालय में अनुपस्थित है। कृपया कारण बताएं।\n— ${S.settings.school}`;
      return `<div class="flex items-center justify-between p-2 bg-slate-50 rounded-xl"><span class="text-sm"><b>${esc(s.name)}</b> ${s.phone ? '' : '<span class="text-xs text-slate-400">(no phone saved)</span>'}</span><a target="_blank" onclick="log('Parent Message',${jsq('Absence message opened for ' + s.name + ' (' + c + ')')})" href="${waLink(s.phone, msg)}" class="btn btn-sm btn-wa"><i class="fa-brands fa-whatsapp"></i> Send</a></div>`; }).join('')}</div></div>` : ''}
    ${ab3.length ? `<div class="card bg-rose-50 border-rose-200"><h3 class="font-bold text-rose-800 mb-1">Absent 3 days in a row</h3><p class="text-sm">${ab3.map(s => esc(s.name)).join(', ')} — call the parents.</p></div>` : ''}
    <div class="card"><h3 class="font-bold mb-2">Today's homework for Class ${c} (from teacher diaries)</h3>
      ${hw.length ? `<ul class="text-sm space-y-1 mb-3">${hw.map(h => `<li><b>${esc(h.subj)}:</b> ${esc(h.hw)}</li>`).join('')}</ul>
        <a target="_blank" onclick="log('Homework','Homework for Class ${c} shared to parents')" href="${waLink('', homeworkText(date, c, hw))}" class="btn btn-sm btn-wa"><i class="fa-brands fa-whatsapp"></i> Share to class parents group</a>` : '<p class="text-sm text-slate-500">No homework written in diaries yet.</p>'}</div>`;
}
function attToggle(id) { const a = attDraft.absent, i = a.indexOf(id); if (i >= 0) a.splice(i, 1); else a.push(id); render(); }
function attSave(date, c) {
  S.studAtt[date] = S.studAtt[date] || {};
  S.studAtt[date][c] = { absent: [...attDraft.absent], by: userName(), at: Date.now() };
  const names = attDraft.absent.map(id => (studentById(id) || {}).name).filter(Boolean);
  log('Student Attendance', `Class ${c} (${date}): ${studentsOf(c).length - names.length}/${studentsOf(c).length} present` + (names.length ? '. Absent: ' + names.join(', ') : ''));
  toast('Attendance saved'); render();
}
function homeworkFor(date, c) {
  const out = []; const D = S.diary[date] || {};
  Object.values(D).forEach(byBell => Object.values(byBell).forEach(e => { if (e.cls === c && e.hw && e.hw.trim()) out.push({ subj: e.subj, hw: e.hw.trim() }); }));
  return out;
}
function homeworkText(date, c, hw) { return `*Class ${c} Homework — ${fmtDate(date)}*\n` + hw.map(h => `• ${h.subj}: ${h.hw}`).join('\n') + `\n— ${S.settings.school}`; }

// ---------------------------------------------------------------- TEACHER DIARY
function vDiary() {
  const tid = U.tid, date = P.date || todayISO(), bells = myBells(tid, date);
  return head('My Diary (शिक्षक दैनंदिनी)', 'Topic and homework for each bell. Ticking "chapter finished" updates the syllabus automatically.', dateInput('dy-date', date, "go('diary',{date:this.value})")) + `
    <div class="card"><button class="btn btn-p" onclick="prepareDiaryDraft('${date}')">Prepare today's draft</button><p class="text-sm mt-2">Uses approved weekly plans. Check what you actually taught before saving each bell.</p></div>
    ${bells.length ? bells.map(b => { const e = diaryEntry(date, tid, b.bell) || {}; const nx = nextChapter(b.cls, b.subj); const id = 'b' + b.bell;
      return `<div class="card space-y-2 ${P.bell === b.bell ? 'ring-2 ring-indigo-500' : ''}">
        <div class="flex justify-between items-start gap-2"><div><b>${BELLS[b.bell].n} bell • Class ${b.cls} • ${esc(b.subj)}</b> ${b.arr ? '<span class="pill bg-rose-600 text-white">Arrangement</span>' : ''}<br><span class="text-xs text-slate-500">${t12(BELLS[b.bell].s)}–${t12(BELLS[b.bell].e)}</span></div>
        ${e.at ? `<span class="pill bg-emerald-100 text-emerald-800">Saved ${fmtTime(e.at)}</span>` : ''}${S.signed[date] && S.signed[date][tid] ? '<span class="pill bg-indigo-100 text-indigo-800">Signed</span>' : ''}</div>
        <div><label class="lbl">Topic taught</label><input id="${id}-t" class="inp" value="${esc(e.topic || '')}" placeholder="${nx ? 'Next chapter: ' + esc(nx) : 'What did you teach?'}">
          ${nx && !e.topic ? `<button onclick="document.getElementById('${id}-t').value=${esc(JSON.stringify(nx))}" class="text-xs text-indigo-700 font-bold mt-1">Use: ${esc(nx)}</button>` : ''}</div>
        <div><label class="lbl">Homework · class total limited to 45 minutes</label><input id="${id}-h" class="inp" value="${esc(e.hw || '')}" placeholder="e.g. Ex 4.1 Q1–Q4"></div><div class="flex gap-2"><label class="lbl">Estimated minutes<input id="${id}-min" class="inp" type="number" min="1" max="45" value="${e.hwMinutes || ''}"></label><label class="lbl">Homework day<input id="${id}-date" class="inp" type="date" value="${esc(e.hwDate || date)}"></label></div><label class="text-sm"><input id="${id}-verify" type="checkbox"> I confirm this was actually taught and I checked the homework.</label>
        ${nx && !b.arr ? `<label class="flex items-center gap-2 text-sm"><input id="${id}-c" type="checkbox" class="w-4 h-4"> Chapter "<b>${esc(nx)}</b>" finished today</label>` : ''}
        <button onclick="diarySave('${date}',${b.bell})" class="btn btn-p w-full"><i class="fa-solid fa-floppy-disk"></i> Save</button></div>`; }).join('')
      : `<div class="card text-sm text-slate-500">${dayIdx(date) < 0 ? 'Sunday — no classes.' : 'No bells for this day.'}</div>`}`;
}
function diarySave(date, bell) {
  const tid = U.tid, b = myBells(tid, date).find(x => x.bell === bell); if (!b) return;
  const id = 'b' + bell, topic = document.getElementById(id + '-t').value.trim(), hw = document.getElementById(id + '-h').value.trim();
  if (!topic) return toast('Write the topic taught', 'a');
  if (S.signed[date]?.[tid]) return toast('This diary is signed. Ask the Principal before changing it.', 'a');
  if (!document.getElementById(id+'-verify').checked) return toast('Confirm the actual teaching first.', 'a');
  const hwMinutes=Number(document.getElementById(id+'-min').value), hwDate=document.getElementById(id+'-date').value;
  if(hw && (!Number.isInteger(hwMinutes)||hwMinutes<1||hwMinutes>45||!hwDate)) return toast('Add homework minutes and date.', 'a');
  S.diary[date] = S.diary[date] || {}; S.diary[date][tid] = S.diary[date][tid] || {};
  S.diary[date][tid][bell] = { cls: b.cls, subj: b.subj, topic, hw, hwMinutes:hw?hwMinutes:null, hwDate, arr: b.arr, at: Date.now() };
  log('Diary', `${BELLS[bell].n} bell, Class ${b.cls} ${b.subj}: "${topic}"` + (hw ? ` • HW: ${hw}` : ''));
  const cb = document.getElementById(id + '-c');
  if (cb && cb.checked) { const y = S.syl[sylKey(b.cls, b.subj)], ch = y && y.chapters.find(x => !x.done);
    if (ch) { ch.done = date; log('Syllabus', `Class ${b.cls} ${b.subj}: chapter "${ch.n}" completed (from diary)`); } }
  save(); toast('Diary queued for saving'); go('diary', { date });
}

// ---------------------------------------------------------------- PRINCIPAL: DIARIES REVIEW
function vDiaries() {
  const date = P.date || todayISO();
  const rows = Object.entries(S.teachers).map(([k, t]) => { const bells = myBells(k, date); if (!bells.length) return '';
    const filled = bells.filter(b => diaryEntry(date, k, b.bell)).length, signed = S.signed[date] && S.signed[date][k];
    return `<tr><td class="font-bold whitespace-nowrap">${esc(t.name)}<br><span class="text-xs font-normal ${filled === bells.length ? 'text-emerald-700' : 'text-amber-700'}">${filled}/${bells.length} filled</span> ${signed ? '<span class="pill bg-indigo-100 text-indigo-800">Signed</span>' : ''}</td>
      <td>${bells.map(b => { const e = diaryEntry(date, k, b.bell); return `<div class="text-sm mb-1"><b>${BELLS[b.bell].n} • ${b.cls} ${esc(b.subj)}${b.arr ? ' (arr.)' : ''}:</b> ${e ? esc(e.topic) + (e.hw ? ' <span class="text-slate-500">| HW: ' + esc(e.hw) + '</span>' : '') : `<span class="${bellOver(date, b.bell) ? 'text-rose-600 font-semibold' : 'text-slate-400'}">${bellOver(date, b.bell) ? 'Not filled' : 'Upcoming'}</span>`}</div>`; }).join('')}</td>
      <td class="noprint">${filled && !signed ? `<button onclick="signDiary('${date}','${k}')" class="btn btn-sm btn-g">Sign</button>` : ''}</td></tr>`; }).join('');
  return head('Teacher Diaries', fmtDate(date), dateInput('dd-date', date, "go('diaries',{date:this.value})") +
    `<button onclick="signAll('${date}')" class="btn btn-g"><i class="fa-solid fa-stamp"></i> Sign all filled</button><button onclick="printHTML('Diaries',printHeader('Teacher Diaries — ${fmtDate(date)}')+document.getElementById('dtab').outerHTML+PRINT_FOOT)" class="btn btn-l"><i class="fa-solid fa-print"></i> Print</button>`) +
    `<div class="card overflow-x-auto">${rows ? `<table class="tbl" id="dtab"><tr><th>Teacher</th><th>Entries</th><th class="noprint"></th></tr>${rows}</table>` : '<p class="text-sm text-slate-500">No classes on this day.</p>'}</div>`;
}
function signDiary(date, t) { S.signed[date] = S.signed[date] || {}; S.signed[date][t] = Date.now(); log('Diary Sign-off', `Principal signed diary of ${T(t).name} for ${date}`); render(); }
function signAll(date) { let n = 0; Object.keys(S.teachers).forEach(t => { if (myBells(t, date).some(b => diaryEntry(date, t, b.bell)) && !(S.signed[date] && S.signed[date][t])) { S.signed[date] = S.signed[date] || {}; S.signed[date][t] = Date.now(); n++; } });
  log('Diary Sign-off', `Principal signed ${n} diaries for ${date}`); toast(n + ' diaries signed'); render(); }

// ---------------------------------------------------------------- SYLLABUS
function vSyllabus() {
  const date = todayISO();
  if (U.role !== 'teacher' && !P.key) {
    const rows = S.alloc.filter(a => a.n > 0).map(a => { const k = sylKey(a.c, a.s), st = sylStatus(k, date);
      return `<tr><td class="font-bold">${a.c}</td><td>${esc(a.s)}</td><td>${esc(T(a.t).name)}</td>
        <td class="w-48">${st ? bar(st.pct, st.lag ? 'bg-rose-500' : 'bg-emerald-500') + `<span class="text-xs">${st.done}/${st.total} chapters</span>` : '<span class="text-xs text-slate-400">Not set up</span>'}</td>
        <td>${st && st.expected != null ? `<span class="pill ${st.lag ? 'bg-rose-100 text-rose-800' : 'bg-emerald-100 text-emerald-800'}">${st.lag ? 'Behind' : 'On track'} (should be ${st.expected})</span>` : '—'}</td>
        <td class="noprint">${U.role === 'principal' ? `<button onclick="go('syllabus',{key:${jsq(k)}})" class="btn btn-sm btn-l">Open</button>` : ''}</td></tr>`; }).join('');
    return head('Syllabus Progress', 'Teachers tick chapters (or from their diary). Behind = fewer chapters done than the target date requires.') +
      `<div class="card overflow-x-auto"><table class="tbl"><tr><th>Class</th><th>Subject</th><th>Teacher</th><th>Progress</th><th>Status</th><th class="noprint"></th></tr>${rows}</table></div>`;
  }
  const mine = U.role === 'teacher' ? myAllocs(U.tid) : S.alloc.filter(a => a.n > 0);
  if (!mine.length) return head('Syllabus') + '<div class="card text-sm">No subjects assigned.</div>';
  const key = P.key && mine.some(a => sylKey(a.c, a.s) === P.key) ? P.key : sylKey(mine[0].c, mine[0].s);
  const y = S.syl[key] || { chapters: [], targetCount: '', targetDate: '' }, st = sylStatus(key, date);
  return head('Syllabus Tracker', 'Add chapters once, then tick each when finished.', U.role === 'principal' ? `<button onclick="go('syllabus')" class="btn btn-l">← All subjects</button>` : '') + `
    <div class="card"><label class="lbl">Class & subject</label><select class="inp" onchange="go('syllabus',{key:this.value})">${mine.map(a => { const k = sylKey(a.c, a.s); return `<option value="${esc(k)}" ${k === key ? 'selected' : ''}>Class ${a.c} — ${esc(a.s)}</option>`; }).join('')}</select></div>
    ${st ? `<div class="card space-y-2">${bar(st.pct, st.lag ? 'bg-rose-500' : 'bg-emerald-500')}<p class="text-sm"><b>${st.done}/${st.total}</b> chapters done${st.expected != null ? ` • By today you should have finished about <b>${st.expected}</b> — <b class="${st.lag ? 'text-rose-700' : 'text-emerald-700'}">${st.lag ? 'Behind: plan extra periods' : 'On track'}</b>` : ''}</p></div>` : ''}
    <div class="card space-y-2"><h3 class="font-bold">Chapters</h3>
      ${y.chapters.length ? y.chapters.map((ch, i) => `<label class="flex items-center gap-3 p-2 rounded-lg hover:bg-slate-50"><input type="checkbox" class="w-5 h-5" ${ch.done ? 'checked' : ''} onchange="sylTick(${jsq(key)},${i},this.checked)">
        <span class="flex-1 ${ch.done ? 'line-through text-slate-400' : ''}">${i + 1}. ${esc(ch.n)}</span>${ch.done ? `<span class="text-xs text-slate-400">${fmtDate(ch.done)}</span>` : ''}
        <button onclick="sylDel(${jsq(key)},${i})" class="text-slate-300 hover:text-rose-500"><i class="fa-solid fa-trash"></i></button></label>`).join('') : '<p class="text-sm text-slate-500">No chapters yet. Add them below.</p>'}
    </div>
    <div class="card grid grid-cols-1 md:grid-cols-2 gap-4">
      <div><label class="lbl">Add chapters (one per line)</label><textarea id="sy-add" rows="5" class="inp" placeholder="Chapter 1 name&#10;Chapter 2 name"></textarea><button onclick="sylAdd(${jsq(key)})" class="btn btn-p btn-sm mt-2"><i class="fa-solid fa-plus"></i> Add</button></div>
      <div class="space-y-2"><label class="lbl">Target: finish this many chapters…</label><input id="sy-tc" type="number" min="0" class="inp" value="${y.targetCount || ''}">
        <label class="lbl">…by this date (e.g. before next exam)</label><input id="sy-td" type="date" class="inp" value="${y.targetDate || ''}">
        <button onclick="sylTarget(${jsq(key)})" class="btn btn-l btn-sm">Save target</button></div>
    </div>`;
}
function sylEnsure(key) { return S.syl[key] = S.syl[key] || { chapters: [], targetCount: '', targetDate: '' }; }
function sylTick(key, i, on) { const y = sylEnsure(key); y.chapters[i].done = on ? todayISO() : null; log('Syllabus', `${key.replace('|', ' ')}: "${y.chapters[i].n}" ${on ? 'completed' : 'un-ticked'}`); render(); }
function sylDel(key, i) { const y = sylEnsure(key); const n = y.chapters[i].n; if (!confirm('Remove chapter "' + n + '"?')) return; y.chapters.splice(i, 1); log('Syllabus', `${key.replace('|', ' ')}: removed chapter "${n}"`); render(); }
function sylAdd(key) { const lines = document.getElementById('sy-add').value.split('\n').map(s => s.trim()).filter(Boolean); if (!lines.length) return;
  const y = sylEnsure(key); lines.forEach(n => y.chapters.push({ n, done: null })); log('Syllabus', `${key.replace('|', ' ')}: added ${lines.length} chapter(s)`); render(); }
function sylTarget(key) { const y = sylEnsure(key); y.targetCount = parseInt(document.getElementById('sy-tc').value) || ''; y.targetDate = document.getElementById('sy-td').value;
  log('Syllabus', `${key.replace('|', ' ')}: target ${y.targetCount} chapters by ${y.targetDate}`); toast('Target saved'); render(); }

// ---------------------------------------------------------------- MARKS ENTRY (teacher / principal)
function vMarks() {
  const mine = myAllocs(U.tid);
  if (!mine.length) return head('Marks Entry') + '<div class="card text-sm">No subjects assigned.</div>';
  const ex = S.exams.find(e => e.id === P.ex) || S.exams.find(e => e.date >= todayISO()) || S.exams[0];
  const inEx = mine.filter(a => examClasses(ex).includes(a.c)), pick = inEx.length ? inEx : mine;
  const key = P.key && pick.some(a => sylKey(a.c, a.s) === P.key) ? P.key : sylKey(pick[0].c, pick[0].s);
  const [c, subj] = key.split('|');
  const paper = (ex.sheet || []).find(p => p.subjects.includes(subj));
  const rec = (S.marks[ex.id] || {})[key] || { max: paper ? (paper.subjects.length === 1 ? paper.max : '') : 25, m: {} };
  const list = studentsOf(c);
  const vals = list.map(s => rec.m[s.id]).filter(v => v !== undefined && v !== '' && v !== 'AB').map(Number);
  const avg = vals.length ? Math.round(vals.reduce((a, b) => a + b, 0) / vals.length / rec.max * 100) : null;
  return head('Marks Entry', 'Type marks; write AB for absent. Totals, grades and weak-student alerts are automatic.') + `
    <div class="card grid grid-cols-1 sm:grid-cols-3 gap-3">
      <div><label class="lbl">Exam</label><select class="inp" onchange="go('marks',{ex:this.value,key:${jsq(key)}})">${S.exams.map(e => `<option value="${e.id}" ${e.id === ex.id ? 'selected' : ''}>${esc(e.name)} (${fmtDate(e.date)})</option>`).join('')}</select></div>
      <div><label class="lbl">Class & subject</label><select class="inp" onchange="go('marks',{ex:'${ex.id}',key:this.value})">${pick.map(a => { const k = sylKey(a.c, a.s); return `<option value="${esc(k)}" ${k === key ? 'selected' : ''}>Class ${a.c} — ${esc(a.s)}</option>`; }).join('')}</select></div>
      <div><label class="lbl">Maximum marks${paper && paper.subjects.length > 1 ? ` (your part of ${esc(paper.title)}, ${paper.max} in all)` : ''}</label><input id="mk-max" type="number" min="1" class="inp" value="${rec.max}"></div>
    </div>
    <div class="card">
      ${list.length ? `<div class="space-y-1">${list.map((s, i) => `<div class="flex items-center gap-3 py-1 border-b border-slate-100"><span class="w-6 text-xs text-slate-400">${i + 1}</span><span class="flex-1 text-sm font-semibold">${esc(s.name)}</span>
        <input data-sid="${s.id}" class="mk inp w-20 text-center py-1" value="${rec.m[s.id] ?? ''}" inputmode="decimal"></div>`).join('')}</div>
        <div class="flex flex-wrap items-center justify-between gap-2 mt-3"><span class="text-sm">${avg != null ? 'Class average: <b>' + avg + '%</b>' : ''} ${rec.at ? '<span class="text-xs text-slate-400">• saved ' + fmtTime(rec.at) + '</span>' : ''}</span>
        <button onclick="marksSave('${ex.id}',${jsq(key)})" class="btn btn-g"><i class="fa-solid fa-floppy-disk"></i> Save Marks</button></div>` : '<p class="text-sm text-slate-500">No students in this class.</p>'}
    </div>`;
}
function marksSave(exId, key) {
  const max = parseFloat(document.getElementById('mk-max').value) || 0; if (max <= 0) return toast('Enter maximum marks', 'a');
  const m = {}; let bad = false;
  document.querySelectorAll('.mk').forEach(inp => { const v = inp.value.trim().toUpperCase(); if (v === '') return;
    if (v === 'AB') { m[inp.dataset.sid] = 'AB'; return; } const n = parseFloat(v); if (isNaN(n) || n < 0 || n > max) { bad = true; inp.classList.add('ring-2', 'ring-rose-500'); return; } m[inp.dataset.sid] = n; });
  if (bad) return toast('Some marks are invalid or above maximum', 'r');
  S.marks[exId] = S.marks[exId] || {}; S.marks[exId][key] = { max, m, by: userName(), at: Date.now() };
  const ex = S.exams.find(e => e.id === exId);
  log('Marks', `${ex ? ex.name : exId} • Class ${key.replace('|', ' ')}: marks saved for ${Object.keys(m).length} students (max ${max})`);
  toast('Marks saved'); render();
}

// ---------------------------------------------------------------- RESULTS (principal / chairman)
function resultTable(exId, c) {
  const M = S.marks[exId] || {}; const subs = Object.keys(M).filter(k => k.startsWith(c + '|')).map(k => k.split('|')[1]);
  const rows = studentsOf(c).map(s => { let got = 0, max = 0; const cells = subs.map(sb => { const r = M[c + '|' + sb], v = r.m[s.id];
      if (v !== undefined && v !== 'AB' && v !== '') { got += +v; max += +r.max; } return v === undefined ? '' : v; });
    return { s, cells, got, max, pct: max ? Math.round(got / max * 1000) / 10 : null }; });
  const ranked = rows.filter(r => r.pct != null).sort((a, b) => b.pct - a.pct); ranked.forEach((r, i) => r.rank = i + 1);
  return { subs, rows, M };
}
function vResults() {
  const ex = S.exams.find(e => e.id === P.ex) || latestExamWithMarks(todayISO()) || S.exams[0], c = P.c || '10th';
  const { subs, rows, M } = resultTable(ex.id, c);
  const weak = weakStudents(ex.id).filter(w => w.cls === c);
  return head('Results', 'Built automatically from marks entered by teachers.', `<button onclick="printMarksheets('${ex.id}','${c}')" class="btn btn-l"><i class="fa-solid fa-print"></i> Print Marksheets</button>`) + `
    <div class="card grid grid-cols-1 sm:grid-cols-2 gap-3">
      <div><label class="lbl">Exam</label><select class="inp" onchange="go('results',{ex:this.value,c:'${c}'})">${S.exams.map(e => `<option value="${e.id}" ${e.id === ex.id ? 'selected' : ''}>${esc(e.name)}</option>`).join('')}</select></div>
      <div><label class="lbl">Class</label><select class="inp" onchange="go('results',{ex:'${ex.id}',c:this.value})">${CLASS_NAMES.map(x => `<option ${x === c ? 'selected' : ''}>${x}</option>`).join('')}</select></div>
    </div>
    <div class="card overflow-x-auto">${subs.length ? `<table class="tbl"><tr><th>Rank</th><th>Student</th>${subs.map(s => `<th>${esc(s)}<br><span class="font-normal">/${M[c + '|' + s].max}</span></th>`).join('')}<th>Total</th><th>%</th><th>Grade</th></tr>
      ${rows.sort((a, b) => (a.rank || 999) - (b.rank || 999)).map(r => `<tr class="${r.pct != null && r.pct < 40 ? 'bg-rose-50' : ''}"><td class="font-bold">${r.rank || '—'}</td><td class="font-semibold">${esc(r.s.name)}</td>${r.cells.map(v => `<td class="text-center">${esc(v)}</td>`).join('')}<td class="text-center">${r.got}/${r.max}</td><td class="text-center font-bold">${r.pct ?? '—'}</td><td class="text-center">${r.pct != null ? grade(r.pct) : '—'}</td></tr>`).join('')}</table>`
      : '<p class="text-sm text-slate-500">No marks entered for this class and exam yet.</p>'}</div>
    ${weak.length ? `<div class="card bg-rose-50 border-rose-200"><h3 class="font-bold text-rose-800 mb-1">Below 40% — needs remedial classes</h3>${weak.map(w => `<p class="text-sm"><b>${esc((studentById(w.sid) || {}).name)}</b>: ${esc(w.subs.join(', '))}</p>`).join('')}</div>` : ''}`;
}
async function printMarksheets(exId, c) {
  let remarks=[];try{const response=await fetch('toolkit_api.php?a=remarks&exam='+encodeURIComponent(exId)+'&cls='+encodeURIComponent(c),{headers:{'X-School-Token':TOKEN}});const data=await response.json();if(!response.ok||!data.ok)throw Error(data.error||'Could not load report remarks');remarks=data.remarks||[];}catch(e){return toast(e.message,'a');}
  const ex = S.exams.find(e => e.id === exId); const { subs, rows, M } = resultTable(exId, c);
  if (!subs.length) return toast('No marks to print', 'a');
  printHTML('Marksheets', rows.map(r => `<div class="pb">${printHeader('Marksheet — ' + esc(ex.name) + ' • Session ' + esc(S.settings.session))}
    <h2>${esc(r.s.name)} • Class ${c} • Adm No ${esc(r.s.adm)}</h2>
    <table><tr><th>Subject</th><th>Max</th><th>Obtained</th><th>Grade</th></tr>${subs.map((sb, i) => { const v = r.cells[i], mx = M[c + '|' + sb].max; return `<tr><td>${esc(sb)}</td><td>${mx}</td><td>${esc(v)}</td><td>${v !== '' && v !== 'AB' ? grade(v / mx * 100) : '-'}</td></tr>`; }).join('')}
    <tr><th>Total</th><th>${r.max}</th><th>${r.got}</th><th>${r.pct != null ? r.pct + '% (' + grade(r.pct) + ')' : '-'}</th></tr></table>
    ${remarks.filter(m=>m.sid===r.s.id).map(m=>`<p><b>${esc(m.subj)}:</b> ${esc(m.en)}<br>${esc(m.hi)}</p>`).join('')}<p>Rank in class: ${r.rank || '-'}</p><div class="f"><span>Class Teacher ________</span><span>Principal ________</span><span>Powered by Maakit</span></div></div>`).join(''));
  log('Results', `Printed ${ex.name} marksheets for Class ${c}`);
}

// ---------------------------------------------------------------- STUDENTS (principal)
function vStudents() {
  const c = P.c || 'ALL'; const list = (c === 'ALL' ? S.students : S.students.filter(s => s.cls === c)).sort((a, b) => CLASS_NAMES.indexOf(a.cls) - CLASS_NAMES.indexOf(b.cls) || a.name.localeCompare(b.name));
  const nSample = S.students.filter(s => s.sample).length;
  return head('Students', S.students.length + ' students' + (nSample ? ` • ${nSample} are sample entries` : ''), nSample ? `<button onclick="removeSamples()" class="btn btn-r"><i class="fa-solid fa-broom"></i> Remove sample students</button>` : '') + `
    ${lastCodeCard()}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
      <div class="card lg:col-span-2 overflow-x-auto">
        <div class="flex flex-wrap gap-1 mb-3">${['ALL', ...CLASS_NAMES].map(x => `<button onclick="go('students',{c:'${x}'})" class="btn btn-sm ${x === c ? 'btn-p' : 'btn-l'}">${x === 'ALL' ? 'All' : x}</button>`).join('')}</div>
        <table class="tbl"><tr><th>Adm</th><th>Name</th><th>Class</th><th>Father</th><th>Phone</th><th></th></tr>${list.map(s => `<tr><td>${esc(s.adm)}</td><td class="font-semibold">${esc(s.name)}${s.sample ? ' <span class="pill bg-slate-100 text-slate-500">sample</span>' : ''}</td><td>${s.cls}</td><td>${esc(s.father)}</td><td>${esc(s.phone)}</td>
          <td class="whitespace-nowrap"><button onclick="setPin('s:' + ${jsq(s.id)}, ${jsq(s.name)})" class="text-slate-500 mr-2" title="Set or reset student PIN"><i class="fa-solid fa-key"></i></button><button onclick="editStudent('${s.id}')" class="text-indigo-600"><i class="fa-solid fa-pen"></i></button> <button onclick="delStudent('${s.id}')" class="text-rose-500 ml-2"><i class="fa-solid fa-trash"></i></button></td></tr>`).join('')}</table>
      </div>
      <div class="space-y-4">
        <div class="card space-y-2"><h3 class="font-bold">Add student</h3>
          <input id="st-adm" class="inp" placeholder="Admission no."><input id="st-name" class="inp" placeholder="Student name">
          <select id="st-cls" class="inp">${CLASS_NAMES.map(x => `<option>${x}</option>`).join('')}</select>
          <input id="st-fa" class="inp" placeholder="Father's name"><input id="st-ph" class="inp" placeholder="Parent mobile (10 digits)" inputmode="tel">
          <button onclick="addStudent()" class="btn btn-p w-full">Add</button></div>
        <div class="card space-y-2"><h3 class="font-bold">Import verified ERP students</h3>
          <p class="text-xs text-slate-500">Select the verified student JSON file. ERP IDs, admission numbers and session are preserved. Existing rows are never overwritten. Teachers are not changed.</p>
          <input type="file" id="st-erp-file" class="inp" accept=".json,application/json" aria-label="Verified ERP student JSON" onchange="previewErpStudents(this)">
          <div id="st-erp-preview" class="text-sm"></div>
          <button id="st-erp-apply" class="btn btn-g w-full" style="display:none" onclick="applyErpStudents()">Import verified students</button></div>
        <div class="card space-y-2"><h3 class="font-bold">Import from Excel</h3>
          <p class="text-xs text-slate-500">Copy columns from Excel in this order and paste: <b>Adm No, Name, Class, Father, Mobile</b>. Class must be like 10th, 9th … 1st, UKG, LKG, PG.</p>
          <textarea id="st-imp" rows="5" class="inp text-xs" placeholder="92	Yashvi Tiwari	10th	Dilip Kumar Tiwari	9839xxxxxx"></textarea>
          <button onclick="importStudents()" class="btn btn-g w-full"><i class="fa-solid fa-file-import"></i> Import</button></div>
      </div>
    </div>
    ${parentCodesCard()}`;
}
function addStudent() {
  const adm = document.getElementById('st-adm').value.trim(), name = document.getElementById('st-name').value.trim();
  if (!adm || !name) return toast('Admission no. and name are required', 'a');
  if (S.students.some(s => s.adm === adm)) return toast('Admission no. already exists', 'r');
  const s = { id: 's' + uid(), adm, name, cls: document.getElementById('st-cls').value, father: document.getElementById('st-fa').value.trim(), phone: document.getElementById('st-ph').value.trim() };
  S.students.push(s); log('Students', `Added ${s.name} (Class ${s.cls}, Adm ${s.adm})`); toast('Student added'); render();
}
function editStudent(id) {
  const s = studentById(id); if (!s) return;
  const name = prompt('Name', s.name); if (name === null) return;
  const cls = prompt('Class (' + CLASS_NAMES.join(', ') + ')', s.cls); if (cls === null) return;
  if (!CLASS_NAMES.includes(cls.trim())) return toast('Unknown class', 'r');
  const father = prompt("Father's name", s.father); if (father === null) return;
  const phone = prompt('Parent mobile', s.phone); if (phone === null) return;
  Object.assign(s, { name: name.trim(), cls: cls.trim(), father: father.trim(), phone: phone.trim(), sample: false });
  log('Students', `Updated ${s.name} (Adm ${s.adm})`); render();
}
function delStudent(id) { const s = studentById(id); if (!s || !confirm('Remove ' + s.name + '?')) return; S.students = S.students.filter(x => x.id !== id); log('Students', `Removed ${s.name} (Adm ${s.adm})`); render(); }
function removeSamples() { if (!confirm('Remove all sample students? Do this after importing your real list.')) return; const n = S.students.filter(s => s.sample).length; S.students = S.students.filter(s => !s.sample); log('Students', `Removed ${n} sample students`); render(); }
let erpStudentImport=null;
async function previewErpStudents(input) {
  erpStudentImport=null;
  const box=document.getElementById('st-erp-preview'),button=document.getElementById('st-erp-apply');
  button.style.display='none';box.textContent='Checking file…';
  try {
    const file=input.files[0];if(!file){box.textContent='';return;}
    if(file.size>2500000)throw new Error('File is too large. Use a student-only JSON file.');
    const payload=JSON.parse(await file.text());
    const result=await api('importstudents',{...payload,apply:false});
    box.textContent=`Session ${result.session}: ${result.total} students; ${result.toAdd} to add; ${result.unchanged} already present; ${result.conflicts.length} conflicts. Repeated admission numbers are kept as separate ERP IDs.`;
    if(result.conflicts.length)throw new Error('Existing student records need review. Nothing has been imported.');
    erpStudentImport=payload;button.style.display='';
  }catch(e){box.textContent=e.message;toast(e.message,'r');}
}
async function applyErpStudents() {
  if(!erpStudentImport)return;
  const button=document.getElementById('st-erp-apply');button.disabled=true;
  try {
    await sync();
    if(syncing || netState!=='ok' || pendingLog.length || Object.entries(toRecords()).some(([k,v])=>SYNCED[k]!==v))throw new Error('Wait for pending changes to save, then try the import again.');
    const result=await api('importstudents',{...erpStudentImport,apply:true});
    erpStudentImport=null;
    await loadAll();
    go('students');toast(`${result.added} students imported; ${result.unchanged} already present. Teachers unchanged.`);
  }catch(e){toast(e.message,'r');button.disabled=false;}
}
function importStudents() {
  const lines = document.getElementById('st-imp').value.split('\n').map(l => l.trim()).filter(Boolean); let added = 0, skipped = [];
  lines.forEach((l, i) => { const p = l.split(/\t|,/).map(x => x.trim()); const [adm, name, cls, father, phone] = p;
    if (!adm || !name || !CLASS_NAMES.includes(cls)) { skipped.push(i + 1); return; }
    const ex = S.students.find(s => s.adm === adm);
    if (ex) Object.assign(ex, { name, cls, father: father || ex.father, phone: phone || ex.phone, sample: false });
    else S.students.push({ id: 's' + uid(), adm, name, cls, father: father || '', phone: phone || '' });
    added++; });
  log('Students', `Imported ${added} student(s) from Excel` + (skipped.length ? `; skipped line(s) ${skipped.join(', ')}` : ''));
  toast(`${added} imported` + (skipped.length ? `, ${skipped.length} skipped (check class name)` : ''), skipped.length ? 'a' : 'g'); render();
}

// ---------------------------------------------------------------- NOTICES (principal)
function vNotices() {
  return head('Notices & Circulars', 'Write once — teachers see it on their Today screen; share to parents on WhatsApp.') + `
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
      <div class="card space-y-2"><h3 class="font-bold">New notice</h3>
        <input id="nt-title" class="inp" placeholder="Title (e.g. PTM on 5 Nov)"><textarea id="nt-body" rows="5" class="inp" placeholder="Message"></textarea>
        <select id="nt-to" class="inp"><option>Everyone</option><option>Teachers</option><option>Parents</option></select>
        <button onclick="addNotice()" class="btn btn-p w-full">Publish</button></div>
      <div class="card lg:col-span-2 space-y-3">${S.notices.length ? S.notices.map(n => `<div class="border-l-4 border-indigo-500 pl-3"><div class="flex justify-between gap-2"><b>${esc(n.title)}</b><span class="text-xs text-slate-400">${fmtDate(n.date)} • ${esc(n.to)}</span></div>
        <p class="text-sm whitespace-pre-wrap text-slate-700">${esc(n.body)}</p>
        <div class="flex gap-2 mt-1"><a target="_blank" href="${waLink('', `*${n.title}*\n${n.body}\n— ${S.settings.school}`)}" class="btn btn-sm btn-wa"><i class="fa-brands fa-whatsapp"></i> Share</a><button onclick="delNotice('${n.id}')" class="btn btn-sm btn-l">Delete</button></div></div>`).join('') : '<p class="text-sm text-slate-500">No notices yet.</p>'}</div>
    </div>`;
}
function addNotice() { const title = document.getElementById('nt-title').value.trim(), body = document.getElementById('nt-body').value.trim(); if (!title) return toast('Write a title', 'a');
  const n = { id: uid(), date: todayISO(), title, body, to: document.getElementById('nt-to').value, by: userName() }; S.notices.unshift(n); log('Notice', `Published "${title}" to ${n.to}`); toast('Notice published'); render(); }
function delNotice(id) { const n = S.notices.find(x => x.id === id); S.notices = S.notices.filter(x => x.id !== id); log('Notice', `Deleted "${n ? n.title : id}"`); render(); }

// ---------------------------------------------------------------- LEAVE (teacher)
function vLeave() {
  const mine = S.leaves.filter(l => l.tid === U.tid);
  return head('Leave Request', 'Once the Principal approves, you are marked absent automatically and your classes are arranged.') + `
    <div class="card grid grid-cols-1 sm:grid-cols-2 gap-3">
      <div><label class="lbl">From</label><input id="lv-from" type="date" class="inp" value="${todayISO()}"></div>
      <div><label class="lbl">To</label><input id="lv-to" type="date" class="inp" value="${todayISO()}"></div>
      <div class="sm:col-span-2"><label class="lbl">Reason</label><input id="lv-r" class="inp" placeholder="e.g. Family function"></div>
      <button onclick="applyLeave()" class="btn btn-p sm:col-span-2">Send Request</button></div>
    <div class="card flex items-center justify-between gap-2"><span class="text-sm">Change your login PIN</span><button onclick="changeMyPin()" class="btn btn-sm btn-l"><i class="fa-solid fa-key"></i> Change PIN</button></div>
    <div class="card"><h3 class="font-bold mb-2">My requests</h3>${mine.length ? `<table class="tbl"><tr><th>Dates</th><th>Reason</th><th>Status</th></tr>${mine.map(l => `<tr><td>${fmtDate(l.from)}${l.to !== l.from ? ' – ' + fmtDate(l.to) : ''}</td><td>${esc(l.reason)}</td><td class="font-bold ${l.status === 'Approved' ? 'text-emerald-700' : l.status === 'Rejected' ? 'text-rose-700' : 'text-amber-700'}">${l.status}</td></tr>`).join('')}</table>` : '<p class="text-sm text-slate-500">None yet.</p>'}</div>`;
}
function applyLeave() {
  const from = document.getElementById('lv-from').value, to = document.getElementById('lv-to').value, reason = document.getElementById('lv-r').value.trim();
  if (!from || !to || to < from) return toast('Check the dates', 'a'); if (!reason) return toast('Write a reason', 'a');
  S.leaves.unshift({ id: uid(), tid: U.tid, from, to, reason, status: 'Pending', at: Date.now() });
  log('Leave', `${T(U.tid).name} requested leave ${from} to ${to}: ${reason}`); toast('Request sent to Principal'); render();
}

async function changeMyPin() {
  const old = prompt('Your current PIN'); if (old === null) return;
  const pin = prompt('New PIN (4 to 8 digits)'); if (pin === null) return;
  if (!/^\d{4,8}$/.test(pin.trim())) return toast('PIN must be 4 to 8 digits', 'a');
  if (pin.trim() === '1234') return toast('Choose a PIN other than 1234', 'a');
  try { await api('setpin', { old: old.trim(), pin: pin.trim() }); toast('PIN changed'); } catch (e) { toast(e.message, 'r'); }
}

// ---------------------------------------------------------------- ACTIVITY LOG
function vLog() {
  const q = (P.q || '').toLowerCase(), k = P.k || '';
  const kinds = [...new Set(S.log.map(l => l.k))].sort();
  const list = S.log.filter(l => (!k || l.k === k) && (!q || (l.u + ' ' + l.x).toLowerCase().includes(q)));
  return head('Activity Log', 'Every action is noted automatically — who, what, when. Showing the latest 1,000; Export CSV gives the full log.', `<button onclick="exportLog()" class="btn btn-l"><i class="fa-solid fa-file-csv"></i> Export CSV</button>`) + `
    <div class="card flex flex-col sm:flex-row gap-2"><input class="inp" placeholder="Search name or text…" value="${esc(P.q || '')}" onchange="go('log',{q:this.value,k:${jsq(k)}})">
      <select class="inp sm:w-56" onchange="go('log',{q:${jsq(P.q || '')},k:this.value})"><option value="">All activity types</option>${kinds.map(x => `<option ${x === k ? 'selected' : ''}>${esc(x)}</option>`).join('')}</select></div>
    <div class="card overflow-x-auto"><table class="tbl"><tr><th>When</th><th>Who</th><th>Type</th><th>Activity</th></tr>${list.slice(0, 500).map(l => `<tr><td class="whitespace-nowrap text-xs">${fmtTime(l.t)}</td><td class="font-semibold whitespace-nowrap">${esc(l.u)}</td><td><span class="pill bg-indigo-50 text-indigo-800">${esc(l.k)}</span></td><td class="text-sm">${esc(l.x)}</td></tr>`).join('')}</table>
      ${list.length > 500 ? '<p class="text-xs text-slate-500 mt-2">Showing latest 500 — export CSV for all.</p>' : ''}${!list.length ? '<p class="text-sm text-slate-500">Nothing found.</p>' : ''}</div>`;
}
async function exportLog() {
  try {
    const r = await fetch('api.php?a=logcsv', { headers: { 'X-School-Token': TOKEN || '' } });
    if (!r.ok) throw 0;
    const a = document.createElement('a'); a.href = URL.createObjectURL(await r.blob()); a.download = 'school-activity-log-' + todayISO() + '.csv';
    document.body.appendChild(a); a.click(); a.remove();
  } catch (e) { toast('Could not download the log. Check internet.', 'r'); }
}
function download(name, text, type) { const a = document.createElement('a'); a.href = URL.createObjectURL(new Blob([text], { type })); a.download = name; document.body.appendChild(a); a.click(); a.remove(); }

// ---------------------------------------------------------------- SETTINGS (principal)
function pinBadge(uid) { return PINS[uid] ? '<span class="pill bg-emerald-100 text-emerald-800">Own PIN</span>' : '<span class="pill bg-amber-100 text-amber-800">Uses 1234</span>'; }
function vSettings() {
  const tOpts = sel => Object.entries(S.teachers).map(([k, t]) => `<option value="${esc(k)}" ${k === sel ? 'selected' : ''}>${esc(t.name)}</option>`).join('');
  return head('Settings', 'Logins, phone numbers, class teachers, exams and backup.') + `
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
      <div class="card space-y-2"><h3 class="font-bold">School</h3>
        <label class="lbl">School name</label><input id="se-school" class="inp" value="${esc(S.settings.school)}">
        <div class="grid grid-cols-2 gap-2"><div><label class="lbl">Session</label><input id="se-session" class="inp" value="${esc(S.settings.session)}"></div>
        <div><label class="lbl">Session start</label><input id="se-start" type="date" class="inp" value="${S.settings.sessionStart}"></div></div>
        <div class="grid grid-cols-2 gap-2"><div><label class="lbl">Attendance must be marked by</label><input id="se-cut" type="time" class="inp" value="${S.settings.attendanceCutoff}"></div>
        <div><label class="lbl">Chairman WhatsApp no.</label><input id="se-chph" class="inp" value="${esc(S.settings.chairmanPhone)}" inputmode="tel"></div></div>
        <button onclick="saveSchool()" class="btn btn-p btn-sm">Save</button></div>
      <div class="card space-y-3"><h3 class="font-bold">Logins</h3>
        <div class="flex items-center justify-between gap-2"><span class="text-sm"><b>Principal</b> (you) ${pinBadge('principal')}</span><button onclick="changeMyPin()" class="btn btn-sm btn-l">Change my PIN</button></div>
        <div class="flex items-center justify-between gap-2"><span class="text-sm"><b>Chairman</b> ${pinBadge('chairman')}</span><button onclick="setPin('chairman','Chairman')" class="btn btn-sm btn-l">Set PIN</button></div>
        <p class="text-xs text-slate-500">PINs are 4–8 digits and stored only in coded (hashed) form. Everyone except the Principal starts with PIN 1234 and must choose their own at first login. Nobody can see a PIN after it is set; if someone forgets, press Set PIN and type 1234 to reset it.</p></div>
      <div class="card lg:col-span-2 overflow-x-auto"><h3 class="font-bold mb-2">Teachers — login PIN & WhatsApp number</h3>
        <table class="tbl"><tr><th>Teacher</th><th>Role</th><th>Login</th><th>WhatsApp no.</th><th></th></tr>${Object.entries(S.teachers).map(([k, t]) => `<tr><td class="font-semibold">${esc(t.name)}</td><td class="text-xs">${esc(t.role)}</td>
          <td class="whitespace-nowrap">${pinBadge('t:' + k)} <button onclick="setPin('t:${esc(k)}',${jsq(t.name)})" class="btn btn-sm btn-l ml-1">Set PIN</button></td>
          <td><input class="inp py-1" value="${esc(t.phone || '')}" inputmode="tel" onchange="S.teachers['${esc(k)}'].phone=this.value.trim();log('Settings',${jsq('Phone updated for ' + t.name)})"></td>
          <td>${k !== 'P' ? `<button onclick="removeTeacher('${esc(k)}')" class="text-slate-300 hover:text-rose-500" title="Remove"><i class="fa-solid fa-trash"></i></button>` : ''}</td></tr>`).join('')}</table>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 mt-3"><input id="nt-name" class="inp" placeholder="New teacher's name (e.g. Neha Ma'am)"><input id="nt-role" class="inp" placeholder="Subject / role"><button onclick="addTeacher()" class="btn btn-p">Add teacher</button></div></div>
      <div class="card overflow-x-auto"><h3 class="font-bold mb-2">Class teachers</h3>
        <table class="tbl"><tr><th>Class</th><th>Class Teacher</th></tr>${CLASSES.map((k, i) => `<tr><td class="font-bold">${k.c}</td><td><select class="inp py-1" onchange="setCT(${i},this.value)">${tOpts(k.ct)}</select></td></tr>`).join('')}</table></div>
      <div class="card space-y-2"><h3 class="font-bold">Exams</h3>
        ${S.exams.map((e, i) => `<div class="flex gap-2"><input class="inp" value="${esc(e.name)}" onchange="S.exams[${i}].name=this.value;log('Settings','Exam renamed to '+this.value)"><input type="date" class="inp w-44" value="${e.date}" onchange="S.exams[${i}].date=this.value;log('Settings',${jsq(e.name + ' date set to ')}+this.value)"></div>`).join('')}
        <button onclick="S.exams.push({id:uid(),name:'New Exam',date:todayISO()});log('Settings','Exam added');render()" class="btn btn-l btn-sm">+ Add exam</button></div>
      <div class="card space-y-2 lg:col-span-2"><h3 class="font-bold">Backup</h3>
        <p class="text-sm text-slate-600">All data is saved on the maakit.in server and included in Maakit's automatic database backup. You can also download your own copy any time.</p>
        <button onclick="backup()" class="btn btn-g"><i class="fa-solid fa-download"></i> Download a copy</button></div>
    </div>`;
}
function saveSchool() {
  const g = id => document.getElementById(id).value.trim();
  Object.assign(S.settings, { school: g('se-school'), session: g('se-session'), sessionStart: g('se-start'), attendanceCutoff: g('se-cut'), chairmanPhone: g('se-chph') });
  log('Settings', 'School settings updated'); toast('Saved'); render();
}
async function setPin(uidx, name) {
  const pin = prompt('New PIN for ' + name + ' (4 to 8 digits). Type 1234 to reset — they will then choose their own.'); if (pin === null) return;
  if (!/^\d{4,8}$/.test(pin.trim())) return toast('PIN must be 4 to 8 digits', 'a');
  await sync();
  try { await api('setpin', { uid: uidx, pin: pin.trim() }); PINS[uidx] = pin.trim() !== '1234'; toast((pin.trim() === '1234' ? 'Reset to 1234 for ' : 'PIN set for ') + name); render(); } catch (e) { toast(e.message, 'r'); }
}
function addTeacher() {
  const name = document.getElementById('nt-name').value.trim(), role = document.getElementById('nt-role').value.trim();
  if (!name) return toast("Write the teacher's name", 'a');
  let n = 1; while (S.teachers['T' + n]) n++;
  S.teachers['T' + n] = { name, role: role || 'Teacher', phone: '' };
  log('Settings', `Added teacher ${name}`); toast(name + ' added — now set a PIN and assign periods in Timetable › Setup'); render();
}
function removeTeacher(k) {
  if (S.alloc.some(a => a.t === k) || CLASSES.some(c => c.ct === k)) return toast('This teacher still has periods or a class. Move them in Timetable › Setup and Class teachers first.', 'a');
  if (!confirm('Remove ' + T(k).name + '?')) return;
  const n = T(k).name; delete S.teachers[k]; log('Settings', `Removed teacher ${n}`); render();
}
function setCT(i, t) { const old = CLASSES[i].ct; S.classes = CLASSES.map(x => ({ ...x })); S.classes[i].ct = t; refreshClasses(); log('Settings', `Class ${CLASSES[i].c} class teacher: ${T(old).name} → ${T(t).name}`); render(); }
function backup() { download('school-erp-copy-' + todayISO() + '.json', JSON.stringify({ ...S, log: undefined }), 'application/json'); log('Backup', 'Copy of data downloaded'); }

// ---------------------------------------------------------------- WORD OF THE DAY + CONVERSATION STARTERS (built in)
// word | meaning | हिंदी | example. One word per day of the year (day 1 = 1 Jan).
const WORD_BANK = `abundant|more than enough|प्रचुर|Water is abundant in our village after the rains.
accurate|correct in every detail|सटीक|Please give an accurate answer.
achieve|to succeed in doing something|हासिल करना|She worked hard to achieve good marks.
admire|to respect and like someone|प्रशंसा करना|I admire my grandfather's honesty.
adventure|an exciting experience|साहसिक कार्य|The trip to the hills was a great adventure.
advice|a suggestion about what to do|सलाह|My teacher gave me good advice.
ancient|very old|प्राचीन|Varanasi is an ancient city.
anxious|worried|चिंतित|He was anxious before the exam.
appreciate|to value something|सराहना करना|We appreciate your help.
approach|to come near|पास आना|The bus is approaching the stop.
arrange|to put in order|व्यवस्थित करना|Arrange the books on the shelf.
assist|to help|सहायता करना|Can you assist me with this bag?
attentive|paying careful attention|ध्यान देने वाला|An attentive student learns quickly.
available|ready to be used|उपलब्ध|Is the library available after school?
aware|knowing about something|जागरूक|We must be aware of road safety.
balance|an equal amount on both sides|संतुलन|Keep a balance between study and play.
beneficial|helpful, good for you|लाभदायक|Exercise is beneficial for health.
boast|to talk too proudly about yourself|डींग मारना|He likes to boast about his new bike.
brave|ready to face danger|बहादुर|The brave soldier saved the child.
brief|short|संक्षिप्त|Give a brief answer.
brilliant|very clever or bright|शानदार|That was a brilliant idea.
calm|peaceful, not excited|शांत|Stay calm during the fire drill.
capable|able to do something|सक्षम|You are capable of doing better.
careless|not paying attention|लापरवाह|A careless mistake cost him two marks.
celebrate|to do something special for a happy event|उत्सव मनाना|We celebrate Diwali with our family.
challenge|a difficult task|चुनौती|Learning a new language is a challenge.
cheerful|happy and positive|प्रसन्नचित्त|Our class teacher is always cheerful.
clever|quick to learn and understand|चतुर|The clever fox found a way out.
collect|to bring things together|इकट्ठा करना|We collect old newspapers for recycling.
comfortable|giving a pleasant, relaxed feeling|आरामदायक|This chair is very comfortable.
compare|to see how things are alike or different|तुलना करना|Compare the two pictures.
complete|to finish|पूरा करना|Complete your homework before playing.
confident|sure of yourself|आत्मविश्वासी|Speak in a confident voice.
confuse|to mix up|उलझन में डालना|These two words often confuse students.
connect|to join together|जोड़ना|Connect the wires carefully.
consider|to think carefully about|विचार करना|Consider all options before deciding.
courage|bravery|साहस|It takes courage to tell the truth.
courteous|polite and respectful|विनम्र|Be courteous to every visitor.
create|to make something new|बनाना|Let us create a poster for Earth Day.
curious|wanting to know more|जिज्ञासु|Curious children ask many questions.
damage|harm|नुकसान|The storm caused damage to the crops.
decide|to make a choice|निर्णय लेना|I decided to join the science club.
decrease|to become less|घटना|Prices decrease after the festival.
delicious|very tasty|स्वादिष्ट|Mother made delicious kheer.
depend|to rely on|निर्भर होना|Farmers depend on the monsoon.
describe|to tell what something is like|वर्णन करना|Describe your favourite place.
deserve|to earn something by your actions|हकदार होना|You deserve the prize.
determined|having a firm decision|दृढ़ निश्चयी|She is determined to become a doctor.
develop|to grow or improve|विकसित करना|Reading develops your vocabulary.
difference|the way things are not the same|अंतर|What is the difference between a frog and a toad?
diligent|hard-working and careful|परिश्रमी|A diligent student never misses homework.
disappear|to go out of sight|गायब होना|The sun disappeared behind the clouds.
discover|to find something new|खोजना|Scientists discover new medicines.
discuss|to talk about something with others|चर्चा करना|Let us discuss the story in groups.
eager|very keen|उत्सुक|The children were eager to see the circus.
effort|hard work|प्रयास|Your effort will bring success.
elderly|old (polite word)|बुज़ुर्ग|Help elderly people cross the road.
encourage|to give support and hope|प्रोत्साहित करना|Teachers encourage us to read.
enormous|very big|विशाल|An elephant is an enormous animal.
environment|the natural world around us|पर्यावरण|Keep the environment clean.
essential|very important, necessary|आवश्यक|Water is essential for life.
estimate|to guess the amount|अनुमान लगाना|Estimate the length of the room.
examine|to look at closely|जांचना|The doctor examined the patient.
excellent|very good|उत्कृष्ट|You did an excellent job.
explain|to make something clear|समझाना|Can you explain this sum?
explore|to travel to learn about a place|खोज करना|We explored the old fort.
fair|treating everyone equally|निष्पक्ष|The umpire was fair to both teams.
familiar|well known|परिचित|This song sounds familiar.
famous|known by many people|प्रसिद्ध|Bhadohi is famous for its carpets.
fascinate|to interest greatly|मोहित करना|Stars fascinate me.
fierce|wild and violent|भयंकर|A fierce wind blew all night.
flexible|able to bend easily|लचीला|Rubber is flexible.
fortunate|lucky|भाग्यशाली|We are fortunate to have good teachers.
fragile|easily broken|नाज़ुक|Glass is fragile; handle it with care.
frequent|happening often|बार-बार होने वाला|We have frequent power cuts in summer.
generous|happy to give to others|उदार|He is generous with his time.
gentle|kind and soft|कोमल|Be gentle with the puppy.
genuine|real, true|असली|This is a genuine leather bag.
gigantic|extremely large|विशालकाय|The ship was gigantic.
glance|a quick look|झलक|She took a quick glance at the clock.
gradual|slow and steady|क्रमिक|There was a gradual rise in temperature.
grateful|thankful|आभारी|I am grateful for your help.
guide|to show the way|मार्गदर्शन करना|The teacher guided us through the museum.
habit|something you do regularly|आदत|Reading daily is a good habit.
harmful|causing harm|हानिकारक|Smoking is harmful to health.
harvest|the gathering of crops|फसल कटाई|The wheat harvest is in April.
hesitate|to pause before doing something|हिचकिचाना|Do not hesitate to ask questions.
honest|truthful|ईमानदार|An honest boy returned the lost wallet.
humble|not proud|विनम्र|Great people are humble.
identify|to recognise|पहचानना|Can you identify this bird?
imagine|to form a picture in the mind|कल्पना करना|Imagine a world without trees.
immediate|happening at once|तुरंत|The injury needs immediate care.
important|of great value|महत्वपूर्ण|Sleep is important for growth.
improve|to make better|सुधारना|Practice will improve your handwriting.
include|to make part of a group|शामिल करना|Include everyone in the game.
increase|to become more|बढ़ना|The number of students has increased.
independent|not needing help from others|स्वतंत्र|India became independent in 1947.
inform|to tell|सूचित करना|Please inform the office if you are absent.
innocent|not guilty|निर्दोष|The innocent man was set free.
inspire|to fill with the wish to do something|प्रेरित करना|Kalam's life inspires students.
intelligent|able to learn and understand well|बुद्धिमान|Dolphins are intelligent animals.
interrupt|to stop someone while they speak|बीच में टोकना|Do not interrupt when others are speaking.
invent|to make something for the first time|आविष्कार करना|Who invented the telephone?
investigate|to find out the facts|जांच करना|The police investigated the theft.
journey|travel from one place to another|यात्रा|The journey to Delhi took ten hours.
judge|to form an opinion|आंकना|Do not judge a book by its cover.
kind|caring and helpful|दयालु|Be kind to animals.
knowledge|what you know|ज्ञान|Books give us knowledge.
labour|hard physical work|श्रम|The house was built with great labour.
lazy|not wanting to work|आलसी|The lazy cat slept all day.
legend|a famous old story|किंवदंती|There is a legend about this temple.
liberty|freedom|स्वतंत्रता|Liberty is a basic right.
limit|the greatest amount allowed|सीमा|There is a speed limit near schools.
locate|to find the place of|स्थान पता करना|Locate Bhadohi on the map.
loyal|faithful|वफ़ादार|A dog is a loyal friend.
magnificent|very beautiful and grand|भव्य|The Taj Mahal is magnificent.
maintain|to keep in good condition|बनाए रखना|Maintain silence in the library.
measure|to find the size or amount|मापना|Measure the table with a scale.
memory|the power to remember|स्मृति|She has a sharp memory.
method|a way of doing something|तरीका|Use the correct method to solve the sum.
mischief|naughty behaviour|शरारत|The twins are full of mischief.
modern|new, of the present time|आधुनिक|Our school has a modern computer lab.
modest|not boasting|सरल स्वभाव का|He is modest about his success.
motivate|to give a reason to act|प्रेरित करना|Prizes motivate students.
mysterious|hard to explain|रहस्यमय|We heard a mysterious sound at night.
narrow|not wide|संकरा|The lane is too narrow for a bus.
necessary|needed|ज़रूरी|An admit card is necessary for the exam.
neglect|to not take care of|उपेक्षा करना|Do not neglect your health.
nervous|worried and afraid|घबराया हुआ|I felt nervous on stage.
noble|good and honest in character|महान|Helping the poor is a noble act.
notice|to see or become aware of|ध्यान देना|Did you notice the new board?
nourish|to give food for growth|पोषण देना|Milk nourishes the body.
numerous|many|अनेक|There are numerous stars in the sky.
obey|to do what you are told|आज्ञा मानना|Obey the traffic rules.
observe|to watch carefully|अवलोकन करना|Observe the plant for a week.
obstacle|something in the way|बाधा|Hard work removes every obstacle.
obtain|to get|प्राप्त करना|You can obtain the form from the office.
occasion|a special event|अवसर|A birthday is a happy occasion.
occupy|to fill or take up|घेरना|The books occupy the whole shelf.
opinion|what you think about something|राय|What is your opinion about the film?
opportunity|a good chance|मौका|This trip is a great opportunity to learn.
opposite|completely different|विपरीत|Hot is the opposite of cold.
ordinary|normal, not special|साधारण|It was an ordinary day.
organise|to plan and arrange|आयोजित करना|We will organise a sports day.
original|first, not copied|मौलिक|Write an original story.
patient|able to wait calmly|धैर्यवान|Be patient; your turn will come.
peculiar|strange|अजीब|There is a peculiar smell in the kitchen.
perform|to do or act|प्रदर्शन करना|Our class will perform a skit.
permanent|lasting for ever|स्थायी|Use a permanent marker on the label.
permit|to allow|अनुमति देना|Mobile phones are not permitted in class.
persuade|to make someone agree|मनाना|She persuaded her father to buy a cycle.
pleasant|nice, enjoyable|सुहावना|The weather is pleasant today.
polite|having good manners|शिष्ट|Always be polite to elders.
popular|liked by many people|लोकप्रिय|Cricket is popular in India.
positive|hopeful and confident|सकारात्मक|Keep a positive attitude.
precious|very valuable|कीमती|Time is precious.
predict|to say what will happen|भविष्यवाणी करना|Can you predict the weather?
prefer|to like one thing more than another|पसंद करना|I prefer tea to coffee.
prepare|to get ready|तैयार करना|Prepare well for the test.
preserve|to keep safe|संरक्षित करना|We must preserve our forests.
prevent|to stop something from happening|रोकना|Washing hands prevents disease.
previous|coming before|पिछला|Revise the previous chapter.
pride|a feeling of satisfaction|गर्व|We take pride in our school.
probable|likely to happen|संभावित|Rain is probable this evening.
produce|to make or grow|उत्पादन करना|Bhadohi produces fine carpets.
progress|forward movement, improvement|प्रगति|Your progress in English is good.
promise|to say you will surely do something|वादा करना|I promise to be on time.
protect|to keep safe from harm|रक्षा करना|Helmets protect our heads.
proud|feeling pleased about something|गर्वित|Parents are proud of their children.
provide|to give|प्रदान करना|Trees provide shade.
punctual|on time|समय का पाबंद|A punctual student is never late.
purpose|the reason for something|उद्देश्य|What is the purpose of this meeting?
puzzle|a problem to solve|पहेली|This maths puzzle is fun.
quarrel|an angry argument|झगड़ा|The brothers had a quarrel over the remote.
quality|how good something is|गुणवत्ता|This shop sells good quality cloth.
quantity|amount|मात्रा|Add a small quantity of salt.
quiet|making little noise|शांत|Please be quiet in the hall.
rapid|very fast|तेज़|There was rapid growth in the town.
rare|not common|दुर्लभ|The tiger is a rare animal now.
reason|why something happens|कारण|What is the reason for your delay?
recall|to remember|याद करना|Can you recall the poem?
receive|to get something given|प्राप्त करना|I received a letter today.
recognise|to know again|पहचानना|I did not recognise you in uniform.
recommend|to suggest as good|सिफ़ारिश करना|I recommend this book.
reduce|to make smaller|कम करना|Reduce the use of plastic.
refuse|to say no|मना करना|He refused to give up.
regular|happening at fixed times|नियमित|Regular practice brings success.
relax|to rest|आराम करना|Relax after the exam.
reliable|can be trusted|भरोसेमंद|He is a reliable friend.
remarkable|worth noticing|उल्लेखनीय|She made remarkable progress.
remind|to help someone remember|याद दिलाना|Remind me to bring the map.
remove|to take away|हटाना|Remove your shoes before entering.
repair|to fix|मरम्मत करना|The mechanic repaired the bus.
replace|to put something new instead|बदलना|Replace the old bulb.
request|to ask politely|अनुरोध करना|I request you to grant me leave.
rescue|to save from danger|बचाना|The boatman rescued the child.
respect|to admire and honour|सम्मान करना|Show respect to everyone.
responsible|having a duty to look after|ज़िम्मेदार|The monitor is responsible for the class.
result|what happens because of something|परिणाम|The result of hard work is success.
reveal|to show something hidden|प्रकट करना|The magician revealed his trick.
reward|a prize for good work|पुरस्कार|She got a reward for honesty.
rough|not smooth|खुरदरा|The road is rough after the rain.
rural|of the village|ग्रामीण|Most of India is rural.
sacred|holy|पवित्र|The Ganga is a sacred river.
satisfy|to make happy by giving what is needed|संतुष्ट करना|The answer satisfied the teacher.
scarce|not enough|कम|Water is scarce in summer.
scatter|to throw in different directions|बिखेरना|The wind scattered the papers.
schedule|a plan of times|समय-सारणी|Check the exam schedule.
select|to choose|चुनना|Select the correct answer.
sensible|wise, having good sense|समझदार|That is a sensible decision.
serious|important, not funny|गंभीर|Pollution is a serious problem.
shelter|a place for safety|आश्रय|The tree gave us shelter from the rain.
shiver|to shake from cold or fear|काँपना|We shivered in the cold wind.
signal|a sign that gives a message|संकेत|Wait for the green signal.
similar|almost the same|समान|The twins look similar.
sincere|honest and true|सच्चा|Please accept my sincere thanks.
skill|the ability to do something well|कौशल|Typing is a useful skill.
solution|the answer to a problem|हल|We found a solution to the problem.
spacious|having a lot of space|खुला-बड़ा|Our new classroom is spacious.
splendid|very good, grand|शानदार|We had a splendid holiday.
spread|to open out over an area|फैलाना|Spread the mat on the floor.
steady|firm and not shaking|स्थिर|Hold the ladder steady.
strength|power|शक्ति|Unity is strength.
struggle|to try hard against difficulty|संघर्ष करना|The farmer struggled through the drought.
succeed|to do what you tried to do|सफल होना|If you try hard, you will succeed.
sudden|happening quickly without warning|अचानक|There was a sudden storm.
suggest|to put forward an idea|सुझाव देना|I suggest we start early.
suitable|right for the purpose|उपयुक्त|Wear suitable clothes for the trip.
supply|to give what is needed|आपूर्ति करना|The well supplies water to the village.
support|to help and encourage|समर्थन करना|Friends support each other.
surface|the outside layer|सतह|The surface of the table is smooth.
surround|to be all around|घेरना|Trees surround our school.
survive|to stay alive|जीवित रहना|Camels survive in the desert.
suspect|to think something may be true|संदेह करना|I suspect he forgot his book.
sympathy|feeling sorry for someone|सहानुभूति|We felt sympathy for the injured dog.
talent|a natural ability|प्रतिभा|She has a talent for singing.
tasty|having a good flavour|स्वादिष्ट|The samosas were very tasty.
temporary|lasting a short time|अस्थायी|This is a temporary classroom.
tender|soft and gentle|कोमल|Cook the vegetables till they are tender.
thorough|complete and careful|पूरी तरह|Do a thorough revision.
thrill|a feeling of great excitement|रोमांच|Winning the match was a thrill.
tidy|neat and in order|साफ़-सुथरा|Keep your desk tidy.
tiny|very small|बहुत छोटा|An ant is a tiny insect.
tradition|a custom passed down|परंपरा|Lighting diyas is a Diwali tradition.
transport|to carry from one place to another|ढोना|The bus transports students to school.
treasure|valuable things|ख़ज़ाना|Books are a treasure.
tremble|to shake|काँपना|His hands trembled with fear.
trust|to believe someone is honest|भरोसा करना|I trust my best friend.
typical|usual|सामान्य|Rice and dal is a typical lunch.
unique|one of a kind|अनोखा|Every fingerprint is unique.
unite|to join together|एक होना|Let us unite to clean our town.
urgent|needing quick action|अत्यावश्यक|This is an urgent message.
useful|helpful|उपयोगी|A dictionary is useful.
vacant|empty|खाली|The seat next to me is vacant.
valuable|worth a lot|मूल्यवान|Your advice is valuable.
variety|many different kinds|विविधता|The market has a variety of fruits.
vast|very large|विशाल|The ocean is vast.
victory|winning|जीत|Our team celebrated the victory.
visible|can be seen|दिखाई देने वाला|The moon is visible tonight.
vital|very important|अत्यंत आवश्यक|Oxygen is vital for life.
volunteer|to offer to help|स्वेच्छा से काम करना|I volunteered to clean the garden.
wander|to walk without a fixed aim|भटकना|Do not wander alone at night.
wealthy|rich|धनी|The wealthy man built a school.
weary|very tired|थका हुआ|The weary travellers rested under a tree.
wisdom|good judgment|बुद्धिमत्ता|Grandparents share their wisdom.
witness|a person who sees an event|गवाह|He was a witness to the accident.
wonder|to think with curiosity|आश्चर्य करना|I wonder how birds fly.
worry|to feel anxious|चिंता करना|Do not worry; you will do well.
worthy|deserving|योग्य|She is a worthy winner.
yield|to produce (crops)|उपज देना|This field yields good wheat.
zeal|great energy and interest|जोश|He works with zeal.
absorb|to soak up|सोखना|A sponge absorbs water.
accept|to agree to take|स्वीकार करना|Please accept this gift.
accompany|to go with|साथ जाना|I will accompany you to the market.
acquire|to gain|प्राप्त करना|We acquire knowledge by reading.
adapt|to change to fit|ढल जाना|Animals adapt to their surroundings.
adequate|enough|पर्याप्त|Drink adequate water every day.
admit|to agree that something is true|मान लेना|He admitted his mistake.
affect|to have an effect on|प्रभावित करना|Noise affects our concentration.
alert|watchful|सतर्क|Stay alert while crossing the road.
ambition|a strong wish to achieve something|महत्वाकांक्षा|Her ambition is to become a pilot.
amuse|to make someone laugh|मनोरंजन करना|The clown amused the children.
analyse|to study in detail|विश्लेषण करना|Analyse the graph carefully.
announce|to tell publicly|घोषणा करना|The principal announced the results.
annual|happening every year|वार्षिक|Our annual function is in December.
apologise|to say sorry|माफ़ी माँगना|He apologised for being late.
applause|clapping to show approval|तालियाँ|The singer got loud applause.
attempt|to try|प्रयास करना|Attempt all the questions.
attract|to pull towards|आकर्षित करना|Flowers attract bees.
authority|the power to give orders|अधिकार|The principal has authority in school.
avoid|to keep away from|बचना|Avoid junk food.
behave|to act in a certain way|व्यवहार करना|Behave well in the assembly.
belief|something you think is true|विश्वास|It is my belief that hard work pays.
border|the line between two places|सीमा|Nepal shares a border with India.
bother|to disturb|परेशान करना|Do not bother him while he studies.
bright|full of light; clever|उज्ज्वल|She has a bright future.
budget|a plan for spending money|बजट|Make a budget for the class party.
burden|a heavy load|बोझ|Do not make homework a burden.
capture|to catch|पकड़ना|The camera captured the moment.
career|the work you do for life|करियर|Choose a career you enjoy.
caution|great care|सावधानी|Handle the chemicals with caution.
certain|sure|निश्चित|I am certain about the answer.
character|the qualities of a person|चरित्र|Honesty builds good character.
citizen|a member of a country|नागरिक|Every citizen should vote.
claim|to say something is true|दावा करना|He claims he saw a tiger.
climate|the usual weather of a place|जलवायु|India has a tropical climate.
combine|to join together|मिलाना|Combine the flour and water.
comfort|to make someone feel better|सांत्वना देना|She comforted her crying friend.
community|people living in one area|समुदाय|Our community planted 100 trees.
compete|to try to win|प्रतिस्पर्धा करना|Five schools will compete in the quiz.
concentrate|to give full attention|ध्यान केंद्रित करना|Concentrate on your studies.
conclude|to end|समाप्त करना|Conclude your essay with a summary.
conduct|to organise and carry out|संचालित करना|The teacher conducted a test.
conserve|to save from waste|संरक्षण करना|Conserve electricity at home.
contribute|to give for a common purpose|योगदान देना|Everyone contributed to the class library.
convince|to make someone believe|विश्वास दिलाना|He convinced me to join the team.
cooperate|to work together|सहयोग करना|Cooperate with your group members.
crowd|a large number of people|भीड़|A crowd gathered at the fair.
culture|the way of life of a people|संस्कृति|India has a rich culture.
custom|a usual way of doing things|रिवाज|Touching elders' feet is our custom.
dazzle|to shine very brightly|चकाचौंध करना|The lights dazzled our eyes.
debate|a formal discussion with opposite views|वाद-विवाद|We had a debate on mobile phones.
declare|to announce officially|घोषित करना|The judge declared the winner.
defeat|to win against|हराना|Our team defeated the visitors.
delay|to make late|देरी करना|The rain delayed the match.
demand|to ask firmly|माँग करना|The workers demanded fair pay.
destroy|to ruin completely|नष्ट करना|The flood destroyed many houses.
detail|a small fact|विवरण|Read every detail of the notice.
devote|to give time or effort|समर्पित करना|She devotes an hour to reading.
dignity|self-respect|गरिमा|Treat every worker with dignity.
disaster|a sudden great misfortune|आपदा|An earthquake is a natural disaster.
display|to show|प्रदर्शित करना|Display your project on the board.
distant|far away|दूर|We could see the distant hills.
distribute|to give out|बाँटना|The teacher distributed the question papers.
donate|to give for a good cause|दान करना|We donated clothes to the needy.
doubt|a feeling of not being sure|संदेह|If you have a doubt, ask.
dull|not bright; boring|नीरस|It was a dull, cloudy day.
duty|what you must do|कर्तव्य|It is our duty to keep the school clean.
economy|the money and trade of a country|अर्थव्यवस्था|Farming is important to our economy.
educate|to teach|शिक्षित करना|Schools educate children.
efficient|working well without waste|कुशल|An efficient worker saves time.
elect|to choose by voting|चुनना|We will elect a class monitor.
emergency|a sudden dangerous situation|आपातकाल|Call 112 in an emergency.
enemy|someone who is against you|शत्रु|Laziness is our enemy.
energy|the power to work|ऊर्जा|Solar energy comes from the sun.
enjoy|to like doing something|आनंद लेना|We enjoy the sports period.
entertain|to amuse|मनोरंजन करना|The magician entertained the guests.
equal|the same in size or value|बराबर|All citizens are equal before law.
escape|to get away|बचकर निकलना|The bird escaped from the cage.
event|something that happens|घटना|Sports day is a big event.
evidence|proof|प्रमाण|The police found evidence.
exact|completely correct|ठीक-ठीक|Tell me the exact time.
exchange|to give and receive|आदान-प्रदान करना|Let us exchange books.
exhausted|very tired|थककर चूर|We were exhausted after the race.
expand|to become bigger|फैलना|Metals expand when heated.
expect|to think something will happen|उम्मीद करना|I expect good results.
experience|knowledge gained by doing|अनुभव|Travel gives us experience.
expert|a person with special knowledge|विशेषज्ञ|He is an expert in computers.
express|to show feelings or ideas|व्यक्त करना|Express your ideas clearly.
extraordinary|very unusual|असाधारण|She has an extraordinary memory.
faithful|loyal|वफ़ादार|The dog was faithful to its master.
feast|a large special meal|दावत|We had a feast at the wedding.
fetch|to go and bring back|लाना|Please fetch some water.
flourish|to grow well|फलना-फूलना|Plants flourish in sunlight.
forgive|to stop being angry with someone|माफ़ करना|Forgive your friend's small mistakes.
gather|to come together|इकट्ठा होना|Students gathered in the hall.`.split('\n').map(l => { const [w, m, h, e] = l.split('|'); return { w, m, h, e }; });

const STARTERS = `What did you do last Sunday?
What is your favourite festival, and why?
Describe your best friend in three sentences.
If you could visit any place in India, where would you go?
What do you want to become when you grow up?
What is the best thing about our school?
Tell us about a book or story you liked.
What is your favourite food, and how is it made?
If you were the Principal for one day, what would you change?
What makes a good friend?
Describe your village or mohalla.
What did you learn from your grandparents?
Which game do you like most, and how is it played?
What would you do if you found a lost wallet?
Talk about a time you helped someone.
What is the most interesting thing you learnt this week?
If you had a magic power, what would it be?
Describe the weather today and how it makes you feel.
What is your morning routine?
Which animal do you like most, and why?
Talk about a festival fair (mela) you visited.
How can we keep our school clean?
What would you do on a rainy holiday?
Describe your favourite teacher without saying the name.
Why is it important to save water?
What is one thing you are good at?
What is one thing you want to get better at?
Tell us about a funny moment in your family.
What is the best gift you ever received?
Should students have homework every day? Why or why not?
How do you help at home?
Describe a place in Bhadohi that visitors should see.
How is a carpet made? Tell us what you know.
What is your favourite season, and why?
Talk about a journey you remember.
What would you like to invent?
If you could meet any famous person, who would it be?
What does honesty mean to you?
What do you do when you feel sad?
Tell us about your favourite cartoon or TV show.
What makes you laugh?
How can we be kind to animals?
What is the most important rule in our classroom?
Describe your dream house.
What did you eat for breakfast today?
If you had ₹100, what would you buy?
Why should we plant trees?
What is your favourite subject, and why?
Tell us about a time you were brave.
How do you prepare for an exam?
What would you take to a picnic?
Describe a market you visit with your family.
What does a farmer do in a day?
Which sport would you like to learn?
How can we use less plastic?
What is your favourite song or poem?
What will you do in the summer holidays?
How do you feel before a test, and what helps you?
Describe your school uniform.
What is one good habit everyone should have?
Talk about your favourite place at home.
What would you say to a new student in our class?
How can mobile phones be useful, and when are they harmful?
Who is your hero, and why?
What did you do yesterday after school?
Describe a dream you remember.
What would you cook for your family?
Why is it important to be on time?
What makes our country special?
Tell us about a game you invented or changed.
What is the hardest thing you have learnt?
How do you say sorry when you make a mistake?
What would you do if you were lost in a market?
Describe the view from your house.
What do you like about the rainy season?
Why should we respect elders?
If you could change one thing in your town, what would it be?
Tell us about your favourite day of the week.
What do you want to learn on the computer?
How can we help people who are poor?
What is your favourite fruit, and when does it grow?
Describe a picture in our English book.
What did you learn from a mistake?
What would you do if you won a prize?
How do festivals bring people together?
Tell us about a wedding or function you attended.
What do you think school will be like in 20 years?
Why is reading important?
What would you write in a letter to your future self?
How do you spend time with your family on holidays?
What makes you proud?
If you were a bird, where would you fly?
What is the best advice you have been given?
How do you keep yourself healthy?
Describe your favourite toy from childhood.
What does a good leader do?
Tell us about a time you worked in a team.
What would you show a foreigner in India?
What do you like to do in the evening?
Describe your school in five sentences.
What will you do to make tomorrow a good day?
What is something new you tried recently?
If you could talk to an animal, which one and what would you ask?
What do you think is the most useful invention?
How can we save electricity at school?
Tell us about your favourite place in nature.
What would you do if you saw someone being bullied?
What do you enjoy most about weekends?
Describe the sounds you hear in the morning.
What would you like to say to our school's bus driver?
What do you know about the Ganga river?
What is one thing you are thankful for today?
What is a good way to make a new friend?
If you made a film, what would it be about?
How would you explain your favourite game to someone new?
What do you think about wearing helmets?
Tell us about a teacher who helped you.
What should every child learn before Class 10?
What makes a day perfect for you?`.split('\n');

const SPEAK_TYPES = ['Conversation', 'Speech', 'Reading Aloud', 'Storytelling', 'Debate', 'Role-play / Skit', 'Show & Tell'];
const BADGES = [[20, 'Gold Speaker', 'bg-amber-400 text-amber-950'], [10, 'Silver Speaker', 'bg-slate-300 text-slate-900'], [5, 'Bronze Speaker', 'bg-orange-300 text-orange-950']];
const GOOD_REASONS = ['Helpful', 'Homework done well', 'Class participation', 'Discipline', 'Cleanliness', 'Leadership', 'Kindness', 'Neat work'];
const BAD_REASONS = ['Late', 'No homework', 'Indiscipline', 'Uniform', 'Fighting', 'Untidy work', 'Mobile phone'];

function dayOfYear(iso) { const d = new Date(iso + 'T00:00:00'), s = new Date(d.getFullYear(), 0, 1); return Math.floor((d - s) / 86400000); }
function wordOf(iso) { return WORD_BANK[dayOfYear(iso) % WORD_BANK.length]; }
function starterOf(iso) { const d = new Date(iso + 'T00:00:00'); return STARTERS[Math.floor(d / 86400000) % STARTERS.length]; }
function wordShareText(iso) {
  const w = wordOf(iso);
  return `*Word of the Day — ${fmtDate(iso)}*\n\n*${w.w}* — ${w.m}\n(${w.h})\nExample: ${w.e}\n\n*Talk at home:* ${starterOf(iso)}\n\n— ${S.settings.school}`;
}
function wordCard(iso, compact) {
  const w = wordOf(iso);
  return `<div class="card word-card">
    <div class="flex items-start justify-between gap-2"><div>
      <p class="text-sm font-semibold text-ink">Word of the Day</p>
      <p class="text-2xl font-extrabold mt-1">${esc(w.w)} <span class="text-base font-semibold text-slate-500">(${esc(w.h)})</span></p>
      <p class="text-sm text-slate-700">${esc(w.m)}</p>
      <p class="text-sm text-slate-600 italic mt-1">"${esc(w.e)}"</p></div>
      <a target="_blank" href="${waLink('', wordShareText(iso))}" class="btn btn-sm btn-wa noprint shrink-0"><i class="fa-brands fa-whatsapp"></i> Share</a></div>
    ${compact ? '' : `<div class="mt-3 pt-3 border-t border-indigo-200"><p class="text-sm font-semibold text-ink">Conversation Starter</p><p class="text-sm font-semibold mt-1">${esc(starterOf(iso))}</p>
    <p class="text-xs text-slate-500 mt-1">${isFam() ? 'Ask your child this at home and let them answer in English.' : 'Ask 3–4 students to answer in full English sentences, then log it in Speaking.'}</p></div>`}
  </div>`;
}

// ---------------------------------------------------------------- SCHOOL CALENDAR (holidays, exams, events)
const CAL_TYPES = ['Holiday', 'Exam', 'Event', 'PTM'];
function holidayOn(date) { return S.cal.find(e => e.type === 'Holiday' && e.date <= date && (e.to || e.date) >= date) || null; }
function calOn(date) { return S.cal.filter(e => e.date <= date && (e.to || e.date) >= date); }
function vCalendar() {
  const ym = P.ym || todayISO().slice(0, 7), [y, m] = ym.split('-').map(Number);
  const first = new Date(y, m - 1, 1), days = new Date(y, m, 0).getDate(), lead = (first.getDay() + 6) % 7;
  const prev = new Date(y, m - 2, 1), next = new Date(y, m, 1);
  const ymOf = d => d.getFullYear() + '-' + pad(d.getMonth() + 1);
  const exams = examItems();
  const items = d => [...calOn(d), ...exams.filter(e => e.date <= d && (e.to || e.date) >= d)];
  const colour = t => t === 'Holiday' ? 'bg-rose-100 text-rose-800' : t === 'Exam' ? 'bg-purple-100 text-purple-800' : t === 'PTM' ? 'bg-emerald-100 text-emerald-800' : 'bg-blue-100 text-blue-800';
  let cells = '';
  for (let i = 0; i < lead; i++) cells += '<div></div>';
  for (let d = 1; d <= days; d++) {
    const iso = y + '-' + pad(m) + '-' + pad(d), it = items(iso), sun = new Date(iso + 'T00:00:00').getDay() === 0;
    cells += `<div class="min-h-[64px] rounded-lg border p-1 ${iso === todayISO() ? 'border-indigo-500 ring-1 ring-indigo-500' : 'border-slate-200'} ${sun || it.some(e => e.type === 'Holiday') ? 'bg-rose-50/60' : 'bg-white'}">
      <div class="text-xs font-bold ${sun ? 'text-rose-600' : 'text-slate-600'}">${d}</div>${it.map(e => `<div class="text-[10px] leading-tight mt-0.5 px-1 rounded ${colour(e.type)}">${esc(e.title)}</div>`).join('')}</div>`;
  }
  const list = [...S.cal.filter(e => (e.to || e.date).slice(0, 7) >= ym && e.date.slice(0, 7) <= ym), ...exams.filter(e => e.date.slice(0, 7) <= ym && (e.to || e.date).slice(0, 7) >= ym)].sort((a, b) => a.date.localeCompare(b.date));
  const canEdit = U.role === 'principal';
  return head('School Calendar', 'Holidays here are automatically treated as no-class days everywhere in the ERP.',
      `<button onclick="go('calendar',{ym:'${ymOf(prev)}'})" class="btn btn-l">‹</button><span class="btn btn-l pointer-events-none">${first.toLocaleDateString('en-IN', { month: 'long', year: 'numeric' })}</span><button onclick="go('calendar',{ym:'${ymOf(next)}'})" class="btn btn-l">›</button>`) + `
    <div class="card"><div class="grid grid-cols-7 gap-1 text-center text-[11px] font-bold text-slate-500 mb-1">${['Mon','Tue','Wed','Thu','Fri','Sat','Sun'].map(x => `<div>${x}</div>`).join('')}</div>
      <div class="grid grid-cols-7 gap-1">${cells}</div></div>
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
      <div class="card"><h3 class="font-bold mb-2">This month</h3>${list.length ? list.map(e => `<div class="flex items-center justify-between gap-2 py-1.5 border-b border-slate-100 text-sm">
        <span><span class="pill ${colour(e.type)}">${e.type}</span> <b>${esc(e.title)}</b> <span class="text-slate-500">${fmtDate(e.date)}${e.to && e.to !== e.date ? ' – ' + fmtDate(e.to) : ''}</span></span>
        ${canEdit && !e.exam ? `<button onclick="calDel('${esc(e.id)}')" class="text-slate-300 hover:text-rose-500"><i class="fa-solid fa-trash"></i></button>` : ''}</div>`).join('') : '<p class="text-sm text-slate-500">Nothing this month.</p>'}
        <p class="text-xs text-slate-400 mt-2">Exam dates come from Settings › Exams.</p></div>
      ${canEdit ? `<div class="card space-y-2"><h3 class="font-bold">Add to calendar</h3>
        <div class="grid grid-cols-2 gap-2"><div><label class="lbl">From</label><input id="cl-d" type="date" class="inp" value="${todayISO()}"></div><div><label class="lbl">To (optional)</label><input id="cl-t" type="date" class="inp"></div></div>
        <input id="cl-title" class="inp" placeholder="e.g. Diwali holidays, Sports Day">
        <select id="cl-type" class="inp">${CAL_TYPES.map(t => `<option>${t}</option>`).join('')}</select>
        <button onclick="calAdd()" class="btn btn-p w-full">Add</button>
        <button onclick="calFixed()" class="btn btn-l w-full btn-sm">Add fixed national holidays (26 Jan, 15 Aug, 2 Oct)</button>
        <p class="text-xs text-slate-500">Festival holidays change every year — copy them from the UP Government / DM holiday list.</p></div>` : ''}
    </div>`;
}
function calAdd() {
  const date = document.getElementById('cl-d').value, to = document.getElementById('cl-t').value, title = document.getElementById('cl-title').value.trim(), type = document.getElementById('cl-type').value;
  if (!date || !title) return toast('Write the date and title', 'a'); if (to && to < date) return toast('"To" date is before "From"', 'a');
  S.cal.push({ id: uid(), date, to: to || '', title, type }); S.cal.sort((a, b) => a.date.localeCompare(b.date));
  log('Calendar', `${type}: ${title} (${date}${to ? ' to ' + to : ''})`); toast('Added'); go('calendar', { ym: date.slice(0, 7) });
}
function calFixed() {
  const st = new Date(S.settings.sessionStart + 'T00:00:00'), y = st.getFullYear();
  const want = [[y + '-08-15', 'Independence Day'], [y + '-10-02', 'Gandhi Jayanti'], [(y + 1) + '-01-26', 'Republic Day']];
  let n = 0; want.forEach(([date, title]) => { if (!S.cal.some(e => e.date === date && e.title === title)) { S.cal.push({ id: uid(), date, to: '', title, type: 'Holiday' }); n++; } });
  S.cal.sort((a, b) => a.date.localeCompare(b.date)); log('Calendar', `Added ${n} national holiday(s)`); toast(n + ' added'); render();
}
function calDel(id) { const e = S.cal.find(x => x.id === id); if (!e || !confirm('Remove "' + e.title + '"?')) return; S.cal = S.cal.filter(x => x.id !== id); log('Calendar', `Removed ${e.title} (${e.date})`); render(); }

// ---------------------------------------------------------------- BEHAVIOUR & DISCIPLINE
function myClasses() { if (U.role === 'principal') return CLASS_NAMES; const s = new Set(myAllocs(U.tid).map(a => a.c)); CLASSES.filter(k => k.ct === U.tid).forEach(k => s.add(k.c)); return CLASS_NAMES.filter(c => s.has(c)); }
function behavAll() { const out = []; Object.values(S.behav).forEach(byId => Object.values(byId).forEach(b => out.push(b))); return out; }
function behavMonth(ym) { return behavAll().filter(b => b.date.slice(0, 7) === ym); }
function vBehaviour() {
  const cls = myClasses(); if (!cls.length) return head('Behaviour') + '<div class="card text-sm">No classes assigned.</div>';
  const c = cls.includes(P.c) ? P.c : cls[0], date = todayISO(), ym = date.slice(0, 7);
  const mon = behavMonth(ym).filter(b => b.cls === c), pts = sid => mon.filter(b => b.sid === sid).reduce((s, b) => s + b.pts, 0);
  const todays = behavAll().filter(b => b.cls === c && b.date === date).sort((a, b) => b.at - a.at);
  return head(`Behaviour — Class ${c}`, 'Pick a reason, then tap + or − next to the student. Points add up into the monthly Hall of Fame.',
      cls.length > 1 ? `<select class="inp w-auto" onchange="go('behaviour',{c:this.value})">${cls.map(x => `<option ${x === c ? 'selected' : ''}>${x}</option>`).join('')}</select>` : '') + `
    <div class="card grid grid-cols-1 sm:grid-cols-2 gap-3">
      <div><label class="lbl text-emerald-700">Good reason (+)</label><select id="bh-good" class="inp">${GOOD_REASONS.map(r => `<option>${r}</option>`).join('')}</select></div>
      <div><label class="lbl text-rose-700">Discipline reason (−)</label><select id="bh-bad" class="inp">${BAD_REASONS.map(r => `<option>${r}</option>`).join('')}</select></div></div>
    <div class="card">${studentsOf(c).length ? `<div class="divide-y divide-slate-100">${studentsOf(c).map(s => { const p = pts(s.id);
      return `<div class="flex items-center justify-between gap-2 py-2"><span><b class="text-sm">${esc(s.name)}</b> <span class="pill ${p > 0 ? 'bg-emerald-100 text-emerald-800' : p < 0 ? 'bg-rose-100 text-rose-800' : 'bg-slate-100 text-slate-500'}">${p > 0 ? '+' : ''}${p} this month</span></span>
        <span class="flex gap-1"><button onclick="behavAdd('${s.id}',1)" class="btn btn-sm btn-g">+1</button><button onclick="behavAdd('${s.id}',2)" class="btn btn-sm btn-g">+2</button><button onclick="behavAdd('${s.id}',-1)" class="btn btn-sm btn-r">−1</button></span></div>`; }).join('')}</div>`
      : '<p class="text-sm text-slate-500">No students in this class yet.</p>'}</div>
    <div class="card"><h3 class="font-bold mb-2">Today's entries</h3>${todays.length ? todays.map(b => { const s = studentById(b.sid) || { name: '?' };
      const msg = `Dear Parent,\n${s.name} (Class ${b.cls}) — note from school today (${fmtDate(b.date)}): ${b.reason}${b.note ? ' — ' + b.note : ''}.\nकृपया ध्यान दें। — ${S.settings.school}`;
      return `<div class="flex items-center justify-between gap-2 py-1.5 border-b border-slate-100 text-sm"><span><span class="pill ${b.pts > 0 ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800'}">${b.pts > 0 ? '+' : ''}${b.pts}</span> <b>${esc(s.name)}</b> — ${esc(b.reason)}${b.note ? ' (' + esc(b.note) + ')' : ''} <span class="text-xs text-slate-400">by ${esc(T(b.tid).name)}</span></span>
        <span class="flex gap-1 shrink-0">${b.pts < 0 ? `<a target="_blank" href="${waLink(s.phone, msg)}" class="btn btn-sm btn-wa"><i class="fa-brands fa-whatsapp"></i></a>` : ''}${b.tid === U.tid || U.role === 'principal' ? `<button onclick="behavDel('${b.date}','${b.id}')" class="text-slate-300 hover:text-rose-500 px-1"><i class="fa-solid fa-trash"></i></button>` : ''}</span></div>`; }).join('') : '<p class="text-sm text-slate-500">Nothing yet today.</p>'}</div>`;
}
function behavAdd(sid, pts) {
  const s = studentById(sid); if (!s) return;
  const reason = document.getElementById(pts > 0 ? 'bh-good' : 'bh-bad').value;
  let note = '';
  if (pts < 0) { note = prompt(`Short note about ${s.name} (optional)`, '') ; if (note === null) return; note = note.trim().slice(0, 120); }
  const date = todayISO(), id = uid();
  (S.behav[date] = S.behav[date] || {})[id] = { id, date, sid, cls: s.cls, pts, reason, note, tid: U.tid, at: Date.now() };
  log('Behaviour', `${pts > 0 ? '+' : ''}${pts} ${s.name} (${s.cls}): ${reason}${note ? ' — ' + note : ''}`);
  toast(`${pts > 0 ? '+' : ''}${pts} for ${s.name}`, pts > 0 ? 'g' : 'a'); render();
}
function behavDel(date, id) { const b = S.behav[date] && S.behav[date][id]; if (!b || !confirm('Remove this entry?')) return; delete S.behav[date][id]; log('Behaviour', `Removed entry for ${(studentById(b.sid) || {}).name}: ${b.reason}`); render(); }

// ---------------------------------------------------------------- SPEAKING ACTIVITIES + BADGES
let spDraft = null;
function speakCount(sid) { return S.speak.filter(x => x.students.includes(sid)).length; }
function badgeOf(n) { const b = BADGES.find(([min]) => n >= min); return b ? `<span class="pill ${b[2]}">${b[1]}</span>` : ''; }
function vSpeaking() {
  const cls = myClasses(); if (!cls.length) return head('Speaking') + '<div class="card text-sm">No classes assigned.</div>';
  const c = cls.includes(P.c) ? P.c : cls[0], date = todayISO();
  if (!spDraft || spDraft.c !== c) spDraft = { c, students: [] };
  const list = studentsOf(c);
  const recent = S.speak.filter(x => x.cls === c).sort((a, b) => b.at - a.at).slice(0, 8);
  return head(`Speaking — Class ${c}`, 'Every 5 activities earns a badge: Bronze (5), Silver (10), Gold (20).',
      cls.length > 1 ? `<select class="inp w-auto" onchange="spDraft=null;go('speaking',{c:this.value})">${cls.map(x => `<option ${x === c ? 'selected' : ''}>${x}</option>`).join('')}</select>` : '') + `
    ${wordCard(date)}
    <div class="card space-y-3">
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <div><label class="lbl">Activity</label><select id="sp-type" class="inp">${SPEAK_TYPES.map(t => `<option>${t}</option>`).join('')}</select></div>
        <div><label class="lbl">Topic</label><input id="sp-topic" class="inp" value="${esc(starterOf(date))}"></div></div>
      <p class="lbl">Tap the students who spoke (${spDraft.students.length} selected)</p>
      ${list.length ? `<div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-2">${list.map(s => { const on = spDraft.students.includes(s.id), n = speakCount(s.id);
        return `<button onclick="spToggle('${s.id}')" class="text-left p-2 rounded-xl border ${on ? 'bg-indigo-50 border-indigo-400' : 'bg-white border-slate-200'}"><b class="text-sm">${esc(s.name)}</b><br><span class="text-[11px] text-slate-500">${n} activities</span> ${badgeOf(n)}</button>`; }).join('')}</div>
        <button onclick="spSave('${c}')" class="btn btn-g w-full py-3"><i class="fa-solid fa-microphone"></i> Save activity</button>` : '<p class="text-sm text-slate-500">No students in this class yet.</p>'}
    </div>
    <div class="card"><h3 class="font-bold mb-2">Recent activities</h3>${recent.length ? recent.map(x => `<div class="flex justify-between gap-2 py-1.5 border-b border-slate-100 text-sm"><span><b>${esc(x.type)}</b> — ${esc(x.topic)} <span class="text-slate-500">• ${x.students.length} student${x.students.length === 1 ? '' : 's'} • ${fmtDate(x.date)} • ${esc(T(x.tid).name)}</span></span>
      ${x.tid === U.tid || U.role === 'principal' ? `<button onclick="spDel('${x.id}')" class="text-slate-300 hover:text-rose-500"><i class="fa-solid fa-trash"></i></button>` : ''}</div>`).join('') : '<p class="text-sm text-slate-500">None yet.</p>'}</div>`;
}
function spToggle(id) { const a = spDraft.students, i = a.indexOf(id); if (i >= 0) a.splice(i, 1); else a.push(id); render(); }
function spSave(c) {
  if (!spDraft.students.length) return toast('Tap at least one student', 'a');
  const type = document.getElementById('sp-type').value, topic = document.getElementById('sp-topic').value.trim().slice(0, 200);
  const before = spDraft.students.map(id => speakCount(id));
  const rec = { id: uid(), date: todayISO(), cls: c, type, topic, students: [...spDraft.students], tid: U.tid, at: Date.now() };
  S.speak.push(rec);
  const newBadges = rec.students.filter((id, i) => BADGES.some(([min]) => before[i] < min && before[i] + 1 >= min)).map(id => (studentById(id) || {}).name);
  log('Speaking', `Class ${c} ${type}: "${topic}" — ${rec.students.length} students` + (newBadges.length ? `. New badge: ${newBadges.join(', ')}` : ''));
  toast(newBadges.length ? 'Saved — new badge for ' + newBadges.join(', ') : 'Activity saved');
  spDraft = { c, students: [] }; render();
}
function spDel(id) { const x = S.speak.find(r => r.id === id); if (!x || !confirm('Remove this activity?')) return; S.speak = S.speak.filter(r => r.id !== id); log('Speaking', `Removed ${x.type} of Class ${x.cls} (${x.date})`); render(); }

// ---------------------------------------------------------------- LESSON PLANS (teacher submits, ERP checks against diary, principal approves)
function weekStart(iso) { const d = new Date(iso + 'T00:00:00'); const off = (d.getDay() + 6) % 7; return addDays(iso, -off); }
function lpKey(w, t, c, s) { return [w, t, c, s].join('|'); }
function words(s) { return String(s).toLowerCase().replace(/[^a-z0-9ऀ-ॿ ]/g, ' ').split(/\s+/).filter(x => x.length >= 4); }
function planCoverage(p) {
  const topics = [];
  for (let i = 0; i < 6; i++) { const d = addDays(p.week, i), byT = (S.diary[d] || {})[p.tid] || {};
    Object.values(byT).forEach(e => { if (e.cls === p.cls && e.subj === p.subj) topics.push({ d, t: e.topic }); }); }
  const lines = String(p.topics || '').split('\n').map(x => x.trim()).filter(Boolean);
  const res = lines.map(line => { const w = words(line); const hit = topics.find(tp => { const tw = new Set(words(tp.t));
      return w.length ? w.filter(x => tw.has(x)).length >= Math.ceil(w.length / 2) : tp.t.toLowerCase().includes(line.toLowerCase()); }); return { line, hit }; });
  return { res, topics, covered: res.filter(r => r.hit).length };
}
function planCard(p, key, principal) {
  const cv = planCoverage(p), past = addDays(p.week, 5) < todayISO();
  const stc = p.status === 'Approved' ? 'bg-emerald-100 text-emerald-800' : p.status === 'Revise' ? 'bg-rose-100 text-rose-800' : 'bg-amber-100 text-amber-800';
  return `<div class="card space-y-2"><div class="flex flex-wrap justify-between gap-2"><b>${principal ? esc(T(p.tid).name) + ' • ' : ''}Class ${p.cls} • ${esc(p.subj)}</b><span class="pill ${stc}">${p.status}</span></div>
    ${p.goal ? `<p class="text-sm text-slate-600"><b>Aim / activity:</b> ${esc(p.goal)}</p>` : ''}
    <div class="text-sm">${cv.res.map(r => `<div class="flex gap-2"><span class="${r.hit ? 'text-emerald-600' : 'text-slate-300'}"><i class="fa-solid ${r.hit ? 'fa-circle-check' : 'fa-circle'}"></i></span><span>${esc(r.line)}${r.hit ? ` <span class="text-xs text-slate-400">— taught ${fmtDate(r.hit.d)}</span>` : ''}</span></div>`).join('')}</div>
    <p class="text-xs ${cv.covered === cv.res.length ? 'text-emerald-700' : past ? 'text-rose-700' : 'text-slate-500'} font-semibold">Diary check: ${cv.covered} of ${cv.res.length} planned topics found in the diary this week${past && cv.covered < cv.res.length ? ' — some were not taught' : ''}.</p>
    ${p.feedback ? `<p class="text-sm bg-slate-50 rounded-lg p-2"><b>Principal:</b> ${esc(p.feedback)}</p>` : ''}
    ${principal ? `<div class="flex gap-2"><button onclick="lpDecide(${jsq(key)},'Approved')" class="btn btn-sm btn-g">Approve</button><button onclick="lpDecide(${jsq(key)},'Revise')" class="btn btn-sm btn-l">Send back with feedback</button></div>` : ''}</div>`;
}
function vLessonPlan() {
  const mine = myAllocs(U.tid); if (!mine.length) return head('Lesson Plan') + '<div class="card text-sm">No subjects assigned.</div>';
  const thisW = weekStart(todayISO()), w = P.w || thisW, key0 = P.key && mine.some(a => sylKey(a.c, a.s) === P.key) ? P.key : sylKey(mine[0].c, mine[0].s);
  const [c, s] = key0.split('|'), k = lpKey(w, U.tid, c, s), p = S.lplan[k];
  const nx = nextChapter(c, s);
  const myPlans = Object.entries(S.lplan).filter(([kk, v]) => v.tid === U.tid && v.week === w);
  return head('Weekly Lesson Plan', 'Write the topics you plan to teach this week. The ERP ticks each one automatically when it appears in your diary.',
      `<select class="inp w-auto" onchange="go('lplan',{w:this.value,key:${jsq(key0)}})">${[addDays(thisW, -7), thisW, addDays(thisW, 7)].map(x => `<option value="${x}" ${x === w ? 'selected' : ''}>Week of ${fmtDate(x)}</option>`).join('')}</select>`) + `
    <div class="card space-y-2"><label class="lbl">Class & subject</label><select class="inp" onchange="go('lplan',{w:'${w}',key:this.value})">${mine.map(a => { const kk = sylKey(a.c, a.s); return `<option value="${esc(kk)}" ${kk === key0 ? 'selected' : ''}>Class ${a.c} — ${esc(a.s)}</option>`; }).join('')}</select>
      ${p && p.status === 'Approved' ? '<p class="text-sm text-emerald-700 font-semibold">Approved by the Principal — locked.</p>' : `
      <label class="lbl">Topics, one per line (one line ≈ one period)</label><textarea id="lp-top" rows="6" class="inp" placeholder="${nx ? esc(nx) + ' — introduction' : 'Topic 1\nTopic 2'}">${esc(p ? p.topics : '')}</textarea>
      <label class="lbl">Aim / activity / teaching aid (optional)</label><input id="lp-goal" class="inp" value="${esc(p ? p.goal || '' : '')}">
      <button onclick="lpSubmit('${w}',${jsq(key0)})" class="btn btn-p w-full">${p ? 'Update and resubmit' : 'Submit to Principal'}</button>`}</div>
    ${myPlans.map(([kk, v]) => planCard(v, kk, false)).join('')}`;
}
function lpSubmit(w, key) {
  const [c, s] = key.split('|'), topics = document.getElementById('lp-top').value.trim(), goal = document.getElementById('lp-goal').value.trim();
  if (!topics) return toast('Write at least one topic', 'a');
  S.lplan[lpKey(w, U.tid, c, s)] = { week: w, tid: U.tid, cls: c, subj: s, topics, goal, status: 'Submitted', feedback: '', at: Date.now() };
  log('Lesson Plan', `Submitted: Class ${c} ${s}, week of ${w} (${topics.split('\n').filter(Boolean).length} topics)`); toast('Sent to Principal'); render();
}
function vLessonPlans() {
  const thisW = weekStart(todayISO()), w = P.w || thisW;
  const plans = Object.entries(S.lplan).filter(([k, v]) => v.week === w).sort((a, b) => (a[1].status === 'Submitted' ? 0 : 1) - (b[1].status === 'Submitted' ? 0 : 1));
  const teaching = Object.keys(S.teachers).filter(t => myAllocs(t).some(a => !/Games|Library|Moral|Rhymes|Play|Craft|Phonics|Akshar|Numbers|Discovery/.test(a.s)));
  const missing = teaching.filter(t => !plans.some(([k, v]) => v.tid === t));
  return head('Lesson Plans', 'Each plan shows how much of it really appeared in the teacher\'s diary that week.',
      `<select class="inp w-auto" onchange="go('lplans',{w:this.value})">${[addDays(thisW, -14), addDays(thisW, -7), thisW, addDays(thisW, 7)].map(x => `<option value="${x}" ${x === w ? 'selected' : ''}>Week of ${fmtDate(x)}</option>`).join('')}</select>`) + `
    ${missing.length ? `<div class="card bg-amber-50 border-amber-200 text-sm"><b>No plan yet:</b> ${missing.map(t => esc(T(t).name)).join(', ')}</div>` : ''}
    ${plans.length ? plans.map(([k, v]) => planCard(v, k, true)).join('') : '<div class="card text-sm text-slate-500">No plans submitted for this week.</div>'}`;
}
function lpDecide(key, st) {
  const p = S.lplan[key]; if (!p) return;
  let fb = '';
  if (st === 'Revise') { fb = prompt('What should the teacher change?', ''); if (fb === null) return; fb = fb.trim(); }
  p.status = st; p.feedback = fb; p.decidedAt = Date.now();
  log('Lesson Plan', `${st === 'Approved' ? 'Approved' : 'Sent back'}: ${T(p.tid).name}, Class ${p.cls} ${p.subj}, week of ${p.week}${fb ? ' — ' + fb : ''}`); render();
}

// ---------------------------------------------------------------- HALL OF FAME (automatic, monthly)
function vFame() {
  const ym = P.ym || todayISO().slice(0, 7), [y, m] = ym.split('-').map(Number);
  const prev = new Date(y, m - 2, 1), next = new Date(y, m, 1), ymOf = d => d.getFullYear() + '-' + pad(d.getMonth() + 1);
  const label = new Date(y, m - 1, 1).toLocaleDateString('en-IN', { month: 'long', year: 'numeric' });
  const pts = {}; behavMonth(ym).forEach(b => pts[b.sid] = (pts[b.sid] || 0) + b.pts);
  const spk = {}; S.speak.filter(x => x.date.slice(0, 7) === ym).forEach(x => x.students.forEach(id => spk[id] = (spk[id] || 0) + 1));
  const stars = CLASS_NAMES.map(c => { const best = studentsOf(c).map(s => ({ s, p: pts[s.id] || 0 })).filter(x => x.p > 0).sort((a, b) => b.p - a.p)[0]; return best ? { c, ...best } : null; }).filter(Boolean);
  const speakers = Object.entries(spk).map(([id, n]) => ({ s: studentById(id), n })).filter(x => x.s).sort((a, b) => b.n - a.n).slice(0, 10);
  const ex = latestExamWithMarks(todayISO());
  const toppers = ex ? CLASS_NAMES.map(c => { const { rows } = resultTable(ex.id, c); const r = rows.filter(r => r.pct != null).sort((a, b) => b.pct - a.pct)[0]; return r ? { c, s: r.s, pct: r.pct } : null; }).filter(Boolean) : [];
  const certText = `*${S.settings.school} — Hall of Fame, ${label}*\n\n` + (stars.length ? '*Star of the Month*\n' + stars.map(x => `Class ${x.c}: ${x.s.name}`).join('\n') + '\n\n' : '') + (speakers.length ? '*Top Speakers*\n' + speakers.slice(0, 5).map(x => `${x.s.name} (${x.s.cls})`).join('\n') + '\n\n' : '') + (toppers.length ? `*Toppers — ${ex.name}*\n` + toppers.map(x => `Class ${x.c}: ${x.s.name} (${x.pct}%)`).join('\n') + '\n\n' : '') + 'Congratulations! — Powered by Maakit';
  return head('Hall of Fame', 'Made automatically from behaviour points, speaking activities and exam marks.',
      `<button onclick="go('fame',{ym:'${ymOf(prev)}'})" class="btn btn-l">‹</button><span class="btn btn-l pointer-events-none">${label}</span><button onclick="go('fame',{ym:'${ymOf(next)}'})" class="btn btn-l">›</button>
       <a target="_blank" href="${waLink('', certText)}" class="btn btn-wa"><i class="fa-brands fa-whatsapp"></i> Share</a><button onclick="printHTML('Hall of Fame',printHeader('Hall of Fame — ${label}')+document.getElementById('fame').innerHTML+PRINT_FOOT)" class="btn btn-l"><i class="fa-solid fa-print"></i> Print</button>`) + `
    <div id="fame" class="grid grid-cols-1 lg:grid-cols-3 gap-4">
      <div class="card"><h3 class="font-bold mb-2"><i class="fa-solid fa-star text-amber-500"></i> Star of the Month</h3>${stars.length ? `<table class="tbl"><tr><th>Class</th><th>Student</th><th>Points</th></tr>${stars.map(x => `<tr><td>${x.c}</td><td class="font-semibold">${esc(x.s.name)}</td><td>+${x.p}</td></tr>`).join('')}</table>` : '<p class="text-sm text-slate-500">No behaviour points this month yet.</p>'}</div>
      <div class="card"><h3 class="font-bold mb-2"><i class="fa-solid fa-microphone text-indigo-600"></i> Top Speakers</h3>${speakers.length ? `<table class="tbl"><tr><th>Student</th><th>Class</th><th>Activities</th></tr>${speakers.map(x => `<tr><td class="font-semibold">${esc(x.s.name)} ${badgeOf(speakCount(x.s.id))}</td><td>${x.s.cls}</td><td>${x.n}</td></tr>`).join('')}</table>` : '<p class="text-sm text-slate-500">No speaking activities this month yet.</p>'}</div>
      <div class="card"><h3 class="font-bold mb-2"><i class="fa-solid fa-trophy text-emerald-600"></i> Class Toppers${ex ? ' — ' + esc(ex.name) : ''}</h3>${toppers.length ? `<table class="tbl"><tr><th>Class</th><th>Student</th><th>%</th></tr>${toppers.map(x => `<tr><td>${x.c}</td><td class="font-semibold">${esc(x.s.name)}</td><td>${x.pct}</td></tr>`).join('')}</table>` : '<p class="text-sm text-slate-500">No exam marks entered yet.</p>'}</div>
    </div>`;
}

// ---------------------------------------------------------------- PARENT / STUDENT PANEL
// Parents log in with their mobile number + a 4-digit code from the school.
// The server sends only their own children's data (api.php?a=pdata).
let PD = null, PKID = null;
async function loadParent() {
  const res = await api('pdata');
  PD = res; ME = res.me; U = { role: ME.role, tid: ME.tid };
  S = emptyState(); S.settings = { school: res.school, session: res.session }; S.exams = res.exams || []; S.cal = res.cal || [];
  if (!PKID || !PD.kids.some(k => k.id === PKID)) PKID = PD.kids.length ? PD.kids[0].id : null;
}
function pKid() { return PD.kids.find(k => k.id === PKID) || PD.kids[0]; }
function kidSwitch() {
  if (PD.kids.length < 2) return '';
  return `<div class="flex gap-2 overflow-x-auto noscroll">${PD.kids.map(k => `<button onclick="PKID='${esc(k.id)}';render()" class="chip ${k.id === PKID ? 'chip-on' : ''}">${esc(k.name.split(' ')[0])} · ${esc(k.cls)}</button>`).join('')}</div>`;
}
function pAtt(kid) {
  const rows = PD.att.filter(a => a.sid === kid.id).sort((a, b) => b.date.localeCompare(a.date));
  const present = rows.filter(r => r.present).length;
  return { rows, present, pct: rows.length ? Math.round(present / rows.length * 100) : null, today: rows.find(r => r.date === todayISO()) };
}
function vPHome() {
  if (!PD.kids.length) return `<div class="card">No student is linked to this mobile number yet. Please contact the school office.</div>`;
  const kid = pKid(), at = pAtt(kid), date = todayISO();
  const hwToday = PD.hw.filter(h => h.cls === kid.cls && h.date === date && h.hw);
  const pts = PD.behav.filter(b => b.sid === kid.id && b.date.slice(0, 7) === date.slice(0, 7)).reduce((s, b) => s + (+b.pts || 0), 0);
  const spk = PD.speak.filter(x => x.sid === kid.id).length;
  const badge = BADGES.find(([min]) => spk >= min);
  const next = [...S.cal, ...examItems().filter(e => e.cls.includes(kid.cls))].filter(e => (e.to || e.date) >= date).sort((a, b) => a.date.localeCompare(b.date)).slice(0, 3);
  const todayBox = at.today ? (at.today.present ? '<span class="text-leaf font-semibold">Present today</span>' : '<span class="text-rose font-semibold">Absent today</span>') : '<span class="text-slate-500">Today\'s attendance not marked yet</span>';
  return `${kidSwitch()}
    <section class="kid-hero">
      <div class="flex items-center gap-4">
        <div class="avatar">${esc((kid.name || '?').split(' ').map(x => x[0]).slice(0, 2).join(''))}</div>
        <div class="min-w-0"><h2 class="text-2xl font-semibold leading-tight">${esc(kid.name)}</h2>
          <p class="text-white/75 text-sm">Class ${esc(kid.cls)}${kid.ct ? ' · Class teacher ' + esc(kid.ct) : ''}</p></div>
      </div>
      <div class="grid grid-cols-3 gap-2 mt-5">
        <div class="kstat"><b>${at.pct == null ? '—' : at.pct + '%'}</b><span>Attendance</span></div>
        <div class="kstat"><b>${pts > 0 ? '+' : ''}${pts}</b><span>Good points this month</span></div>
        <div class="kstat"><b>${spk}</b><span>${badge ? badge[1] : 'Speaking activities'}</span></div>
      </div>
    </section>
    <div class="card flex items-center justify-between gap-3"><span>${todayBox}</span><span class="text-sm text-slate-500">${fmtDate(date)}</span></div>
    <div class="card"><h3 class="h3">Homework today</h3>${hwToday.length ? `<ul class="space-y-2 mt-2">${hwToday.map(h => `<li class="flex gap-3"><span class="subj">${esc(h.subj)}</span><span>${esc(h.hw)}</span></li>`).join('')}</ul>` : '<p class="text-sm text-slate-500 mt-1">No homework written yet today.</p>'}
      <button onclick="go('phw')" class="btn btn-l btn-sm mt-3">All homework</button></div>
    ${parentDateSheets(kid)}
    ${wordCard(date)}
    ${next.length ? `<div class="card"><h3 class="h3">Coming up</h3>${next.map(e => `<div class="flex justify-between gap-2 py-2 border-b border-slate-100 last:border-0 text-sm"><span><b>${esc(e.title)}</b> <span class="text-slate-500">${esc(e.type)}</span></span><span class="text-slate-500">${fmtDate(e.date)}</span></div>`).join('')}</div>` : ''}`;
}
function vPHw() {
  const kid = pKid(); if (!kid) return vPHome();
  const rows = PD.hw.filter(h => h.cls === kid.cls).sort((a, b) => b.date.localeCompare(a.date));
  const byDate = {}; rows.forEach(h => (byDate[h.date] = byDate[h.date] || []).push(h));
  return `${kidSwitch()}${head('Homework & class work', `Class ${esc(kid.cls)} · last 10 days, from the teachers' diaries`)}
    ${Object.keys(byDate).length ? Object.entries(byDate).map(([d, list]) => `<div class="card"><h3 class="h3">${fmtDate(d)}</h3><div class="mt-2 divide-y divide-slate-100">${list.map(h => `<div class="py-2"><span class="subj">${esc(h.subj)}</span>
      <p class="text-sm mt-1"><span class="text-slate-500">Taught:</span> ${esc(h.topic)}</p>${h.hw ? `<p class="text-sm"><span class="text-slate-500">Homework:</span> <b>${esc(h.hw)}</b></p>` : ''}</div>`).join('')}</div></div>`).join('')
      : '<div class="card text-sm text-slate-500">Nothing written in the last 10 days.</div>'}`;
}
function vPResults() {
  const kid = pKid(); if (!kid) return vPHome();
  const at = pAtt(kid);
  const exams = S.exams.filter(e => PD.marks.some(m => m.sid === kid.id && m.exam === e.id));
  const beh = PD.behav.filter(b => b.sid === kid.id).sort((a, b) => b.date.localeCompare(a.date)).slice(0, 10);
  return `${kidSwitch()}${head('Progress', esc(kid.name) + ' · Class ' + esc(kid.cls))}
    ${exams.length ? exams.map(e => { const ms = PD.marks.filter(m => m.sid === kid.id && m.exam === e.id); let got = 0, max = 0;
      ms.forEach(m => { if (m.mark !== 'AB' && m.mark !== '' && m.max) { got += +m.mark; max += +m.max; } }); const pct = max ? Math.round(got / max * 1000) / 10 : null;
      return `<div class="card"><div class="flex justify-between items-baseline gap-2"><h3 class="h3">${esc(e.name)}</h3>${pct != null ? `<span class="text-lg font-semibold">${pct}% <span class="text-sm text-slate-500">${grade(pct)}</span></span>` : ''}</div>
        <table class="tbl mt-2"><tr><th>Subject</th><th class="text-right">Marks</th></tr>${ms.map(m => `<tr><td>${esc(m.subj)}</td><td class="text-right">${esc(m.mark)} / ${esc(m.max)}</td></tr>`).join('')}</table></div>`; }).join('')
      : '<div class="card text-sm text-slate-500">No exam marks published yet.</div>'}
    ${(PD.remarks||[]).filter(r=>r.sid===kid.id).map(r=>`<div class="card"><h3 class="h3">Teacher remark · ${esc(r.subj)}</h3><p>${esc(r.en)}</p><p class="mt-2">${esc(r.hi)}</p></div>`).join('')}
    <div class="card"><h3 class="h3">Attendance, last ${at.rows.length} school day${at.rows.length === 1 ? '' : 's'}</h3>
      <div class="flex flex-wrap gap-1 mt-3">${at.rows.slice(0, 60).reverse().map(r => `<span title="${fmtDate(r.date)}" class="w-4 h-4 rounded ${r.present ? 'bg-leaf' : 'bg-rose'}"></span>`).join('')}</div>
      <p class="text-sm text-slate-500 mt-2">Present on ${at.present} of ${at.rows.length} day${at.rows.length === 1 ? '' : 's'} marked.</p></div>
    <div class="card"><h3 class="h3">Teacher notes</h3>${beh.length ? beh.map(b => `<div class="flex gap-3 py-2 border-b border-slate-100 last:border-0 text-sm"><span class="pill ${b.pts > 0 ? 'bg-leaf/10 text-leaf' : 'bg-rose/10 text-rose'}">${b.pts > 0 ? '+' : ''}${b.pts}</span><span>${esc(b.reason)}${b.note ? ' — ' + esc(b.note) : ''} <span class="text-slate-400">${fmtDate(b.date)}</span></span></div>`).join('') : '<p class="text-sm text-slate-500 mt-1">No notes yet.</p>'}</div>`;
}
function vPNotices() {
  const date = todayISO();
  const pinCard = `<div class="card flex items-center justify-between gap-3"><span class="text-sm">Change your login PIN</span><button onclick="changeMyPin()" class="btn btn-sm btn-l">Change PIN</button></div>`;
  const kidCls = PD.kids.map(k => k.cls);
  const up = [...S.cal, ...examItems().filter(e => e.cls.some(c => kidCls.includes(c)))].filter(e => (e.to || e.date) >= date).sort((a, b) => a.date.localeCompare(b.date)).slice(0, 12);
  return `${head('Notices & calendar')}
    ${PD.notices.length ? PD.notices.sort((a, b) => String(b.date).localeCompare(String(a.date))).map(n => `<div class="card"><div class="flex justify-between gap-2"><h3 class="h3">${esc(n.title)}</h3><span class="text-sm text-slate-500 shrink-0">${fmtDate(n.date)}</span></div><p class="text-sm whitespace-pre-wrap mt-1">${esc(n.body)}</p></div>`).join('') : '<div class="card text-sm text-slate-500">No notices.</div>'}
    <div class="card"><h3 class="h3">Holidays, exams and events</h3>${up.length ? up.map(e => `<div class="flex justify-between gap-2 py-2 border-b border-slate-100 last:border-0 text-sm"><span><b>${esc(e.title)}</b> <span class="text-slate-500">${esc(e.type)}</span></span><span class="text-slate-500">${fmtDate(e.date)}${e.to && e.to !== e.date ? ' – ' + fmtDate(e.to) : ''}</span></div>`).join('') : '<p class="text-sm text-slate-500 mt-1">Nothing scheduled.</p>'}</div>
    ${pinCard}`;
}

// ---------------------------------------------------------------- PARENT CODES (principal, in Students)
function parentFamilies() {
  const fam = {};
  S.students.forEach(s => { const p = String(s.phone || '').replace(/\D/g, '').slice(-10); if (p.length === 10) (fam[p] = fam[p] || []).push(s); });
  return Object.entries(fam).sort((a, b) => a[1][0].name.localeCompare(b[1][0].name));
}
function parentCodesCard() {
  const fams = parentFamilies(), noPhone = S.students.filter(s => String(s.phone || '').replace(/\D/g, '').length < 10).length;
  return `<div class="card space-y-3"><div class="flex flex-wrap justify-between gap-2"><h3 class="h3">Parent logins</h3><span class="text-sm text-slate-500">${fams.filter(([p]) => PINS['p:' + p]).length} of ${fams.length} families have their own PIN</span></div>
    <p class="text-sm text-slate-600">Parents open <b>${esc(location.origin + location.pathname)}</b>, choose Parent, and enter their mobile number. The first PIN is <b>1234</b>; they then choose their own. "Make code" gives a family a private code instead. Students can log in with their admission number in the same way. Brothers and sisters with the same mobile number appear together.</p>
    ${noPhone ? `<p class="text-sm text-amber-700">${noPhone} student(s) have no 10-digit mobile number — add it to give their parents a login.</p>` : ''}
    <div class="max-h-96 overflow-y-auto divide-y divide-slate-100">${fams.map(([p, kids]) => `<div class="flex items-center justify-between gap-2 py-2"><span class="text-sm"><b>${kids.map(k => esc(k.name) + ' (' + k.cls + ')').join(', ')}</b><br><span class="text-slate-500">${p.slice(0, 5)} ${p.slice(5)}</span> ${PINS['p:' + p] ? '<span class="pill bg-leaf/10 text-leaf">Own PIN</span>' : '<span class="pill bg-amber-100 text-amber-800">Uses 1234</span>'}</span>
      <button onclick="makeParentCode('${p}')" class="btn btn-sm ${PINS['p:' + p] ? 'btn-l' : 'btn-p'}">${PINS['p:' + p] ? 'New code' : 'Make code'}</button></div>`).join('') || '<p class="text-sm text-slate-500 py-2">Add students with a parent mobile number first.</p>'}</div></div>`;
}
let PC_LAST = null;
function lastCodeCard() {
  if (!PC_LAST) return '';
  return `<div class="card border-leaf/40 bg-leaf/5 space-y-2"><p>Code for <b>${esc(PC_LAST.names)}</b>: <span class="text-2xl font-semibold tracking-widest">${PC_LAST.code}</span></p>
    <p class="text-sm text-slate-600">Send it now — it is shown only once. Making a new code later replaces this one.</p>
    <a target="_blank" href="${waLink(PC_LAST.p, PC_LAST.msg)}" class="btn btn-wa"><i class="fa-brands fa-whatsapp"></i> Send on WhatsApp</a></div>`;
}
async function makeParentCode(p) {
  try {
    const res = await api('parentcode', { phone: p }); PINS['p:' + p] = true;
    const kids = parentFamilies().find(([x]) => x === p)[1];
    const msg = `Namaste,\nYou can now see ${kids.map(k => k.name).join(', ')}'s attendance, homework, marks and school notices on your phone.\n\nOpen: ${location.origin + location.pathname}\nChoose: Parent\nMobile: ${p}\nCode: ${res.code}\n\nPlease keep this code private.\n— ${S.settings.school}`;
    PC_LAST = { p, code: res.code, names: kids.map(k => k.name).join(', '), msg }; render();
    window.scrollTo(0, 0);
  } catch (e) { toast(e.message, 'r'); }
}

// ---------------------------------------------------------------- BOARD EXAMS (Class 10 plan 2026–27) + DATE SHEETS
// An exam may carry: classes (which classes sit it), to (last day), noClasses (normal
// bells stop for those classes during the exam), sheet (date sheet: one paper per day,
// a paper can combine several timetable subjects, e.g. Science = Physics/Chemistry + Biology),
// time, reportDate (results go to parents from this day) and sent {studentId: time}.
const BOARD_PLAN_ID = 'board-2026-27';
function examClasses(e) { return e && e.classes && e.classes.length ? e.classes : CLASS_NAMES; }
function examEnd(e) { return e.to || (e.sheet && e.sheet.length ? e.sheet.map(x => x.date).sort().pop() : e.date); }
function examBlocks(date, cls) { return S.exams.some(e => e.noClasses && e.date <= date && examEnd(e) >= date && examClasses(e).includes(cls)); }
function examShort(e) { return String(e.name || '').replace(/\s*\(.*\)\s*$/, ''); }
function examItems() {
  const out = [];
  S.exams.forEach(e => {
    if (e.sheet && e.sheet.length) e.sheet.forEach(p => out.push({ date: p.date, to: '', title: examShort(e) + ': ' + p.title, type: 'Exam', exam: true, cls: examClasses(e) }));
    else out.push({ date: e.date, to: e.to || '', title: e.name, type: 'Exam', exam: true, cls: examClasses(e) });
  });
  return out;
}
function paperTeachers(cls, p) { return [...new Set(p.subjects.map(s => (S.alloc.find(a => a.c === cls && a.s === s) || {}).t).filter(Boolean))].map(t => T(t).name); }
function sylDeadline(cls) { return ((S.settings || {}).sylDeadlines || {})[cls] || null; }

function applyBoardPlan() {
  const set = S.settings; set.plans = set.plans || {};
  if (set.plans[BOARD_PLAN_ID]) return;
  const P10 = (date, title, subjects, max) => ({ date, title, subjects, max });
  const upsert = e => { const i = S.exams.findIndex(x => x.id === e.id); if (i >= 0) S.exams[i] = { ...S.exams[i], ...e }; else S.exams.push(e); };
  upsert({ id: 'pb1', name: 'Pre-Board 1 (Class 10)', date: '2026-12-21', to: '2026-12-28', classes: ['10th'], noClasses: true, time: '09:00–12:00', reportDate: '2026-12-30',
    sheet: [P10('2026-12-21', 'Mathematics (Standard / Basic)', ['Mathematics'], 80), P10('2026-12-22', 'Hindi', ['Hindi'], 80),
            P10('2026-12-23', 'English Language & Literature', ['English'], 80), P10('2026-12-24', 'Science (Physics, Chemistry, Biology)', ['Physics/Chemistry', 'Biology'], 80),
            P10('2026-12-26', 'Social Science', ['Social Science'], 80), P10('2026-12-28', 'Computer / IT', ['Computer'], 50)] });
  upsert({ id: 'pb2', name: 'Pre-Board 2 (Class 10)', date: '2027-02-01', to: '2027-02-08', classes: ['10th'], noClasses: true, time: '09:00–12:00', sheet: [] });
  upsert({ id: 'ann', name: 'Annual Exam (PG–9th)', date: '2027-02-23', to: '2027-03-11', classes: CLASS_NAMES.filter(c => c !== '10th'), noClasses: true });
  S.exams.sort((a, b) => a.date.localeCompare(b.date));
  const cal = [['bp-syl', '2026-12-19', '', 'Class 10: full syllabus must be complete', 'Event'], ['bp-xmas', '2026-12-25', '', 'Christmas (BSA holiday list)', 'Holiday'],
               ['bp-eval', '2026-12-29', '', 'Pre-Board 1: checking and marks entry', 'Event'], ['bp-ptm', '2026-12-30', '', 'Pre-Board 1 PTM (Class 10) and winter worksheets', 'PTM'],
               ['bp-winter', '2026-12-31', '2027-01-14', 'Winter vacation (BSA)', 'Holiday'], ['bp-doubt', '2027-01-16', '2027-01-24', 'Class 10 doubt-clearing workshops', 'Event']];
  cal.forEach(([id, date, to, title, type]) => { if (!S.cal.some(e => e.id === id || (e.date === date && e.title === title))) S.cal.push({ id, date, to, title, type }); });
  S.cal.sort((a, b) => a.date.localeCompare(b.date));
  set.sylDeadlines = { ...(set.sylDeadlines || {}), '10th': '2026-12-19' };
  set.plans[BOARD_PLAN_ID] = Date.now();
  log('Exam Plan', 'Class 10 board plan 2026–27 added: syllabus deadline 19 Dec; Pre-Board 1 21–28 Dec; PTM 30 Dec; winter vacation 31 Dec–14 Jan; doubt workshops 16–24 Jan; Pre-Board 2 1–8 Feb; Annual exam (PG–9th) 23 Feb–11 Mar');
}

function boardAlerts(date) {
  const A = [];
  Object.entries((S.settings || {}).sylDeadlines || {}).forEach(([cls, dl]) => {
    const subs = S.alloc.filter(a => a.c === cls && a.n > 0 && !/Games|Library|Moral|Activity|PT/.test(a.s));
    if (!subs.length || date > addDays(dl, 30)) return;
    const left = Math.round((new Date(dl + 'T00:00:00') - new Date(date + 'T00:00:00')) / 86400000);
    const st = subs.map(a => ({ a, s: sylStatus(sylKey(a.c, a.s), date) }));
    const none = st.filter(x => !x.s).map(x => x.a.s), behind = st.filter(x => x.s && x.s.pct < 100 && (left < 0 || x.s.lag));
    if (!none.length && !behind.length) return;
    A.push({ lv: left <= 30 || left < 0 ? 'r' : 'a', go: 'syllabus',
      x: `Class ${cls}: full syllabus due ${fmtDate(dl)} (${left < 0 ? 'deadline passed' : left + ' days left'}).` +
         (behind.length ? ' Behind: ' + behind.map(x => `${x.a.s} ${x.s.done}/${x.s.total}` + (x.s.expected != null && left >= 0 ? ` (should be ${x.s.expected})` : '')).join(', ') + '.' : '') +
         (none.length ? ' Chapters not entered yet: ' + none.join(', ') + '.' : '') });
  });
  S.exams.filter(e => e.sheet && e.sheet.length && examEnd(e) < date && date <= addDays(examEnd(e), 10)).forEach(e => examClasses(e).forEach(cls => {
    const miss = e.sheet.filter(p => p.subjects.some(s => !((S.marks[e.id] || {})[cls + '|' + s]))).map(p => p.title.replace(/\s*\(.*\)/, '') + ' (' + paperTeachers(cls, p).join(', ') + ')');
    if (miss.length) A.push({ lv: 'r', go: 'exams', x: `${examShort(e)} marks not entered for Class ${cls}: ${miss.join('; ')}` });
  }));
  S.exams.filter(e => e.reportDate && e.reportDate <= date && date <= addDays(e.reportDate, 7)).forEach(e => {
    const rows = examReport(e).filter(r => r.complete), sent = rows.filter(r => (e.sent || {})[r.s.id]).length;
    if (rows.length && sent < rows.length) A.push({ lv: 'a', go: 'exams', x: `Send ${examShort(e)} results and focus areas to parents: ${sent} of ${rows.length} ready results sent` });
  });
  return A;
}

function examReport(e) {
  const out = [];
  examClasses(e).forEach(cls => studentsOf(cls).forEach(s => {
    const papers = (e.sheet || []).map(p => { let got = 0, max = 0, ab = false, have = 0;
      p.subjects.forEach(sb => { const r = (S.marks[e.id] || {})[cls + '|' + sb]; if (!r) return; const v = r.m[s.id];
        if (v === 'AB') ab = true; else if (v !== undefined && v !== '') { got += +v; max += +r.max; have++; } });
      const done = have === p.subjects.length; return { p, got, max, ab, done, pct: done && max ? Math.round(got / max * 1000) / 10 : null }; });
    const scored = papers.filter(x => x.pct != null), got = scored.reduce((a, x) => a + x.got, 0), max = scored.reduce((a, x) => a + x.max, 0);
    out.push({ s, cls, papers, got, max, pct: max ? Math.round(got / max * 1000) / 10 : null, any: scored.length > 0 || papers.some(x => x.ab),
               complete: papers.length > 0 && papers.every(x => x.done || x.ab),
               weak: papers.filter(x => x.ab || (x.pct != null && x.pct < 60)) });
  }));
  return out;
}
function reportText(e, r) {
  const L = [`*${S.settings.school}*`, `${e.name} — result`, `${r.s.name} (Class ${r.cls})`, ''];
  r.papers.forEach(x => L.push(`${x.p.title.replace(/\s*\(.*\)/, '')}: ` + (x.ab ? 'Absent' : x.pct != null ? `${x.got}/${x.max} (${x.pct}%)` : 'not ready')));
  if (r.max) L.push('', `Total: ${r.got}/${r.max} (${r.pct}%)`);
  if (r.weak.length) L.push('', 'Needs practice in the winter vacation: ' + r.weak.map(x => x.p.title.replace(/\s*\(.*\)/, '') + (x.ab ? ' (absent)' : x.pct < 33 ? ' (urgent)' : '')).join(', ') + '.',
                          'Subject teachers will give practice worksheets for these at the PTM' + (e.reportDate ? ' on ' + fmtDate(e.reportDate) : '') + '.');
  else L.push('', 'Well done — keep revising every day.');
  L.push('', '— Principal');
  return L.join('\n');
}
function dateSheetText(e, cls) {
  return `*${S.settings.school}*\n${e.name} — date sheet (Class ${cls})\nTime: ${e.time || 'see school notice'}\n\n` +
    e.sheet.map(p => `${fmtDate(p.date)}: ${p.title} (${p.max} marks)`).join('\n') + '\n\nPlease make sure your child sleeps well and reaches school on time.';
}

function vExams() {
  const ro = U.role !== 'principal';
  const e = S.exams.find(x => x.id === P.ex) || S.exams.filter(x => examEnd(x) >= todayISO()).sort((a, b) => a.date.localeCompare(b.date))[0] || S.exams[0];
  if (!e) return head('Exams') + '<div class="card text-sm">No exams yet. Add them in Settings › Exams.</div>';
  const cls = examClasses(e), one = cls.length === 1 ? cls[0] : null;
  const sheet = (e.sheet || []).slice().sort((a, b) => a.date.localeCompare(b.date));
  const marksState = p => one ? p.subjects.map(sb => { const r = (S.marks[e.id] || {})[one + '|' + sb]; return `${esc(sb)}: ${r ? '<span class="text-leaf font-semibold">entered</span>' : '<span class="text-slate-400">waiting</span>'}`; }).join('<br>') : '';
  const rep = examReport(e).filter(r => r.any);
  const subsOf = one ? S.alloc.filter(a => a.c === one && a.n > 0).map(a => a.s) : [];
  return head('Exams & date sheets', 'Normal classes stop automatically for the classes sitting an exam. Results go to parents on WhatsApp from the report date.',
      `<select class="inp w-auto" onchange="go('exams',{ex:this.value})">${S.exams.map(x => `<option value="${esc(x.id)}" ${x.id === e.id ? 'selected' : ''}>${esc(x.name)}</option>`).join('')}</select>`) + `
    <div class="card space-y-1"><h3 class="h3">${esc(e.name)}</h3>
      <p class="text-sm text-slate-600">${fmtDate(e.date)}${examEnd(e) !== e.date ? ' to ' + fmtDate(examEnd(e)) : ''} · ${cls.length === CLASS_NAMES.length ? 'All classes' : 'Class ' + cls.join(', ')}${e.time ? ' · ' + esc(e.time) : ''}${e.noClasses ? ' · no normal classes for these students in this period' : ''}</p>
      ${one && sylDeadline(one) ? `<p class="text-sm"><b>Class ${one} syllabus deadline:</b> 100% by ${fmtDate(sylDeadline(one))}</p>` : ''}</div>
    <div class="card overflow-x-auto"><div class="flex flex-wrap justify-between gap-2 mb-2"><h3 class="h3">Date sheet</h3>
      ${sheet.length && one ? `<span class="flex gap-2 noprint"><a target="_blank" href="${waLink('', dateSheetText({ ...e, sheet }, one))}" onclick="log('Exam Plan',${jsq('Date sheet shared: ' + e.name)})" class="btn btn-sm btn-wa"><i class="fa-brands fa-whatsapp"></i> Share</a>
        <button onclick="printHTML('Date sheet',printHeader(${jsq(e.name + ' — date sheet, Class ' + one)})+document.getElementById('dsheet').outerHTML+PRINT_FOOT)" class="btn btn-sm btn-l"><i class="fa-solid fa-print"></i> Print</button></span>` : ''}</div>
      ${sheet.length ? `<table class="tbl" id="dsheet"><tr><th>Date</th><th>Paper</th><th>Marks</th><th>Teacher</th>${one ? '<th class="noprint">Marks entry</th>' : ''}${ro ? '' : '<th class="noprint"></th>'}</tr>
        ${sheet.map(p => { const i = e.sheet.indexOf(p); return `<tr><td class="whitespace-nowrap">${fmtDate(p.date)}</td><td class="font-semibold">${esc(p.title)}</td>
          <td>${ro ? p.max : `<input type="number" min="1" class="inp py-1 w-20" value="${p.max}" onchange="examPaperMax('${esc(e.id)}',${i},this.value)">`}</td>
          <td>${one ? esc(paperTeachers(one, p).join(', ')) : ''}</td>${one ? `<td class="text-xs noprint">${marksState(p)}</td>` : ''}
          ${ro ? '' : `<td class="noprint"><button onclick="examPaperDel('${esc(e.id)}',${i})" class="text-slate-300 hover:text-rose"><i class="fa-solid fa-trash"></i></button></td>`}</tr>`; }).join('')}</table>`
      : '<p class="text-sm text-slate-500">No papers added yet.</p>'}
      ${!ro && one ? `<div class="mt-4 pt-4 border-t border-line grid grid-cols-1 md:grid-cols-4 gap-2 items-end noprint">
        <div><label class="lbl">Date</label><input id="pp-date" type="date" class="inp" value="${e.date}"></div>
        <div class="md:col-span-2"><label class="lbl">Subjects in this paper</label><div class="flex flex-wrap gap-2">${subsOf.map(sb => `<label class="chip"><input type="checkbox" class="pp-sub mr-1" value="${esc(sb)}">${esc(sb)}</label>`).join('')}</div></div>
        <div><label class="lbl">Marks</label><div class="flex gap-2"><input id="pp-max" type="number" class="inp" value="80"><button onclick="examPaperAdd('${esc(e.id)}')" class="btn btn-p">Add</button></div></div></div>` : ''}
    </div>
    ${sheet.length ? `<div class="card overflow-x-auto"><div class="flex flex-wrap justify-between gap-2 mb-2"><h3 class="h3">Results for parents</h3>
        <span class="text-sm text-slate-500">${rep.filter(r => (e.sent || {})[r.s.id]).length} of ${rep.length} sent${e.reportDate ? ' · send from ' + fmtDate(e.reportDate) : ''}</span></div>
      ${rep.length ? `<table class="tbl"><tr><th>Student</th>${sheet.map(p => `<th>${esc(p.title.replace(/\s*\(.*\)/, ''))}</th>`).join('')}<th>%</th><th>Needs practice</th>${ro ? '' : '<th class="noprint"></th>'}</tr>
        ${rep.map(r => `<tr><td class="font-semibold whitespace-nowrap">${esc(r.s.name)}</td>${sheet.map(p => { const x = r.papers.find(y => y.p === p) || r.papers[e.sheet.indexOf(p)];
          return `<td class="${x && x.pct != null && x.pct < 33 ? 'text-rose font-semibold' : ''}">${x ? (x.ab ? 'AB' : x.pct != null ? x.got + '/' + x.max : '—') : '—'}</td>`; }).join('')}
          <td class="font-semibold">${r.pct ?? '—'}</td><td class="text-sm">${r.weak.map(x => esc(x.p.title.replace(/\s*\(.*\)/, ''))).join(', ') || '—'}</td>
          ${ro ? '' : `<td class="noprint whitespace-nowrap">${!r.complete ? '<span class="text-xs text-slate-500">Marks pending</span>' : `${(e.sent || {})[r.s.id] ? '<span class="pill bg-leaf/10 text-leaf">Sent</span> ' : ''}<a target="_blank" href="${waLink(r.s.phone, reportText(e, r))}" onclick="examSent('${esc(e.id)}','${esc(r.s.id)}')" class="btn btn-sm btn-wa"><i class="fa-brands fa-whatsapp"></i> Send</a>`}</td>`}</tr>`).join('')}</table>
        <p class="text-xs text-slate-500 mt-2">Each message lists every paper, the total, and subjects below 60% to practise in the vacation. Parents need a mobile number on the student record.</p>`
      : '<p class="text-sm text-slate-500">Results appear here as teachers enter marks.</p>'}</div>` : ''}`;
}
function examPaperMax(id, i, v) { const e = S.exams.find(x => x.id === id); const n = parseInt(v) || 0; if (!e || n < 1) return; const old = e.sheet[i].max; e.sheet[i].max = n; log('Exam Plan', `${e.name}: ${e.sheet[i].title} marks ${old} → ${n}`); render(); }
function examPaperDel(id, i) { const e = S.exams.find(x => x.id === id); if (!e || !confirm('Remove ' + e.sheet[i].title + ' from the date sheet?')) return; const p = e.sheet.splice(i, 1)[0]; log('Exam Plan', `${e.name}: removed ${p.title} (${p.date})`); render(); }
function examPaperAdd(id) {
  const e = S.exams.find(x => x.id === id); if (!e) return;
  const date = document.getElementById('pp-date').value, max = parseInt(document.getElementById('pp-max').value) || 0;
  const subjects = [...document.querySelectorAll('.pp-sub:checked')].map(x => x.value);
  if (!date || !subjects.length || max < 1) return toast('Choose the date, at least one subject and the marks', 'a');
  if (e.sheet.some(p => p.date === date)) return toast('There is already a paper on that date', 'a');
  e.sheet = e.sheet || []; e.sheet.push({ date, title: subjects.join(' + '), subjects, max }); e.sheet.sort((a, b) => a.date.localeCompare(b.date));
  if (date < e.date) e.date = date; if (!e.to || date > e.to) e.to = date;
  log('Exam Plan', `${e.name}: added ${subjects.join(' + ')} on ${date} (${max} marks)`); render();
}
function examSent(id, sid) { const e = S.exams.find(x => x.id === id); if (!e) return; e.sent = e.sent || {}; e.sent[sid] = Date.now(); log('Exam Report', `${e.name} result sent to parent of ${(studentById(sid) || {}).name}`); setTimeout(render, 300); }
function parentDateSheets(kid) {
  const today = todayISO();
  return S.exams.filter(e => e.sheet && e.sheet.length && examClasses(e).includes(kid.cls) && examEnd(e) >= today && e.date <= addDays(today, 90)).map(e =>
    `<div class="card"><h3 class="h3">${esc(e.name)} — date sheet</h3>${e.time ? `<p class="text-sm text-slate-500">Time: ${esc(e.time)}</p>` : ''}
      <table class="tbl mt-2"><tr><th>Date</th><th>Paper</th><th class="text-right">Marks</th></tr>${e.sheet.slice().sort((a, b) => a.date.localeCompare(b.date)).map(p => `<tr><td class="whitespace-nowrap">${fmtDate(p.date)}</td><td>${esc(p.title)}</td><td class="text-right">${p.max}</td></tr>`).join('')}</table></div>`).join('');
}

// ---------------------------------------------------------------- QUIZ DUEL (students + parents) — school/quiz_api.php
// Students make questions only on topics their class was taught (teacher diaries,
// last 14 days). A teacher approves each question. Approved questions can be sent
// to classmates as timed challenges and feed a 5-question daily quiz.
let QZ = null, QZBUSY = false, QPLAY = null, QTIMER = null, QT = null;
async function qapi(a, body, q) {
  const url = 'quiz_api.php?a=' + a + (q ? '&' + new URLSearchParams(q) : '');
  const opt = { method: body ? 'POST' : 'GET', headers: { 'X-School-Token': TOKEN || '' } };
  if (body) { opt.headers['Content-Type'] = 'application/json'; opt.body = JSON.stringify(body); }
  let r, j = null;
  try { r = await fetch(url, opt); } catch (e) { throw new Error('No internet connection. Please try again.'); }
  try { j = await r.json(); } catch (e) {}
  if (r.status === 401) { forceLogout(); throw new Error('Please log in again.'); }
  if (!r.ok || !j || !j.ok) throw new Error((j && j.error) || 'Server problem. Please try again.');
  return j;
}
async function loadQuiz() {
  if (QZBUSY) return; QZBUSY = true;
  try { QZ = await qapi('state', null, { sid: pKid().id }); } catch (e) { QZ = { error: e.message }; } finally { QZBUSY = false; }
  if (view === 'quiz') render();
}
function qzStopTimer() { if (QTIMER) { clearInterval(QTIMER); QTIMER = null; } }
function qzStartTimer(total) {
  qzStopTimer();
  QTIMER = setInterval(() => {
    if (!QPLAY || QPLAY.result) return qzStopTimer();
    const left = Math.max(0, Math.ceil((QPLAY.deadline - Date.now()) / 1000));
    const t = document.getElementById('qz-left'), b = document.getElementById('qz-bar');
    if (t) t.textContent = left + 's';
    if (b) b.style.width = Math.max(0, left / total * 100) + '%';
    if (left <= 0) { qzStopTimer(); qzChoose(-1); }
  }, 250);
}
function qzPlayCard() {
  const p = QPLAY, total = p.kind === 'c' ? QZ.rules.challengeSeconds : QZ.rules.quizSeconds;
  const left = Math.max(0, Math.ceil((p.deadline - Date.now()) / 1000));
  return `<section class="card space-y-4 border-2 border-ink">
    <div class="flex items-center justify-between gap-2"><span class="pill bg-marigold/25 text-ink">${p.kind === 'c' ? 'Challenge from ' + esc(p.from || 'a classmate') : `Today's quiz · question ${p.no} of ${p.n}`}</span>
      <span class="text-sm text-slate-500">${esc(p.q.subj)} · ${esc(p.q.topic)}</span></div>
    <h3 class="text-xl font-semibold text-ink leading-snug">${esc(p.q.text)}</h3>
    ${p.result ? '' : `<div class="flex items-center gap-3"><div class="flex-1 h-2 rounded-full bg-paper overflow-hidden"><div id="qz-bar" class="h-2 bg-marigold" style="width:${left / total * 100}%"></div></div><b id="qz-left" class="w-10 text-right">${left}s</b></div>`}
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">${p.q.opts.map((o, i) => {
      const r = p.result, cls = !r ? 'border-line bg-white hover:border-ink' : i === r.ans ? 'border-leaf bg-leaf/10' : i === p.chosen ? 'border-rose bg-rose/10' : 'border-line bg-white opacity-60';
      return `<button ${r ? 'disabled' : ''} onclick="qzChoose(${i})" class="w-full text-left p-4 rounded-2xl border-2 ${cls} text-base font-medium"><span class="text-slate-400 mr-2">${'ABCD'[i]}</span>${esc(o)}</button>`; }).join('')}</div>
    ${p.result ? `<p class="text-lg font-semibold ${p.result.correct ? 'text-leaf' : 'text-rose'}">${p.result.correct ? 'Correct!' : p.result.late || p.chosen < 0 ? 'Time is up.' : 'Not this time.'} ${p.kind === 'c' ? (p.result.correct ? 'You earn 2 points.' : 'Your classmate may earn a point.') : (p.result.correct ? '+1 point' : '')}</p>` : ''}
  </section>`;
}
async function qzChoose(i) {
  const p = QPLAY; if (!p || p.result || p.sending) return;
  p.sending = true; qzStopTimer();
  try {
    if (p.kind === 'c') {
      const res = await qapi('answer', { sid: QZ.kid.id, cid: p.cid, choice: i });
      p.chosen = i; p.result = res; render();
      setTimeout(() => { QPLAY = null; loadQuiz(); }, 2200);
    } else {
      const res = await qapi('quiz', { sid: QZ.kid.id, choice: i });
      p.chosen = i; p.result = res.last || { correct: false, ans: -1 }; render();
      setTimeout(() => {
        if (res.q) { QPLAY = { kind: 'd', q: res.q, deadline: Date.now() + res.left * 1000, no: res.done + 1, n: res.n }; render(); qzStartTimer(QZ.rules.quizSeconds); }
        else { QPLAY = null; toast(`Quiz finished: ${res.right} of ${res.n} correct`); loadQuiz(); }
      }, 1600);
    }
  } catch (e) { p.sending = false; toast(e.message, 'r'); QPLAY = null; loadQuiz(); }
}
async function qzOpen(cid) {
  try {
    const res = await qapi('open', { sid: QZ.kid.id, cid });
    const c = QZ.incoming.find(x => x.id === cid) || {};
    if (res.done) { toast(res.correct ? 'You already answered this one correctly.' : 'This challenge is over.', 'b'); return loadQuiz(); }
    QPLAY = { kind: 'c', cid, q: res.q, from: c.from, deadline: Date.now() + res.left * 1000 }; render(); window.scrollTo(0, 0); qzStartTimer(QZ.rules.challengeSeconds);
  } catch (e) { toast(e.message, 'r'); }
}
async function qzDaily() {
  try {
    const res = await qapi('quiz', { sid: QZ.kid.id });
    if (!res.q) { toast(`Today's quiz is done: ${res.right} of ${res.n}`, 'b'); return loadQuiz(); }
    QPLAY = { kind: 'd', q: res.q, deadline: Date.now() + res.left * 1000, no: res.done + 1, n: res.n }; render(); window.scrollTo(0, 0); qzStartTimer(QZ.rules.quizSeconds);
  } catch (e) { toast(e.message, 'r'); }
}
async function qzAsk() {
  const ti = document.getElementById('qa-topic').value, text = document.getElementById('qa-text').value.trim();
  const opts = [0, 1, 2, 3].map(i => document.getElementById('qa-o' + i).value.trim());
  const pick = document.querySelector('input[name="qa-ans"]:checked');
  if (ti === '') return toast('Choose the topic', 'a');
  if (!pick) return toast('Tick the correct answer', 'a');
  try { await qapi('ask', { sid: QZ.kid.id, topic: QZ.topics[+ti], text, opts, ans: +pick.value }); toast('Sent to your teacher to check'); QZ = null; loadQuiz(); }
  catch (e) { toast(e.message, 'r'); }
}
async function qzSend() {
  const qid = document.getElementById('qs-q').value, to = document.getElementById('qs-to').value;
  if (!qid || !to) return toast('Choose a question and a classmate', 'a');
  try { await qapi('challenge', { sid: QZ.kid.id, qid, to }); toast('Challenge sent'); loadQuiz(); } catch (e) { toast(e.message, 'r'); }
}
function vQuiz() {
  if (!PD || !PD.kids.length) return vPHome();
  const kid = pKid();
  if (!QZ || (QZ.kid && QZ.kid.id !== kid.id)) { if (!QZ || !QZ.error) { QZ = null; loadQuiz(); } }
  if (!QZ) return `${kidSwitch()}${head('Quiz Duel')}<div class="card text-slate-500">Loading…</div>`;
  if (QZ.error) return `${kidSwitch()}${head('Quiz Duel')}<div class="card">${esc(QZ.error)} <button onclick="QZ=null;loadQuiz()" class="btn btn-sm btn-l ml-2">Try again</button></div>`;
  const R = QZ.rules, pts = QZ.points, mine = QZ.mine, approved = mine.filter(q => q.status === 'approved' && q.sent < R.sendsPerQ);
  const res = r => r === true ? '<span class="pill bg-leaf/10 text-leaf">Right</span>' : r === false ? '<span class="pill bg-rose/10 text-rose">Wrong</span>' : r === 'expired' ? '<span class="pill bg-slate-100 text-slate-500">Expired</span>' : '<span class="pill bg-marigold/20 text-ink">Waiting</span>';
  const pendingIn = QZ.incoming.filter(c => c.result === null);
  const st = { pending: '<span class="pill bg-marigold/20 text-ink">Teacher checking</span>', approved: '<span class="pill bg-leaf/10 text-leaf">Approved</span>', rejected: '<span class="pill bg-rose/10 text-rose">Not approved</span>' };
  return `${kidSwitch()}${head('Quiz Duel', `Class ${esc(QZ.kid.cls)} · questions only from what your class studied in the last two weeks`)}
    ${QPLAY ? qzPlayCard() : ''}
    <section class="kid-hero"><div class="grid grid-cols-3 gap-2">
      <div class="kstat"><b>${pts.total}</b><span>Points this month</span></div>
      <div class="kstat"><b>${QZ.rank ? '#' + QZ.rank : '—'}</b><span>Rank in class</span></div>
      <div class="kstat"><b>${pts.pending}</b><span>Points waiting for a fair-play check</span></div></div></section>
    ${!QPLAY ? `<div class="card space-y-3"><h3 class="h3">Today's quick quiz</h3>
      ${QZ.quiz.state === 'done' ? `<p>Done for today: <b>${QZ.quiz.right} of ${QZ.quiz.n}</b> correct. A new quiz comes tomorrow.</p>`
        : QZ.quiz.state === 'open' ? `<p>You have a quiz in progress (${QZ.quiz.done} of ${QZ.quiz.n} answered). The clock keeps running.</p><button onclick="qzDaily()" class="btn btn-p">Continue</button>`
        : QZ.quiz.available >= 3 ? `<p class="text-sm text-slate-600">${Math.min(QZ.quiz.available, 5)} questions, ${R.quizSeconds} seconds each, once a day. +1 for each right answer, +2 extra for all 5.</p><button onclick="qzDaily()" class="btn btn-p">Start today's quiz</button>`
        : '<p class="text-sm text-slate-500">Not enough approved questions on recent topics yet. Make one below, or wait for your teachers to add some.</p>'}</div>` : ''}
    <div class="card space-y-2"><h3 class="h3">Challenges for you</h3>
      ${pendingIn.length ? pendingIn.map(c => `<div class="flex items-center justify-between gap-2 py-2 border-b border-line last:border-0"><span><b>${esc(c.from)}</b> <span class="text-sm text-slate-500">${esc(c.subj)} · ${esc(c.topic)}</span></span>
        <button onclick="qzOpen('${esc(c.id)}')" class="btn btn-sm btn-p">${c.opened ? 'Continue' : 'Answer'} (${R.challengeSeconds}s)</button></div>`).join('') : '<p class="text-sm text-slate-500">No challenges right now.</p>'}
      ${QZ.incoming.filter(c => c.result !== null).slice(0, 5).map(c => `<div class="flex justify-between gap-2 text-sm text-slate-600"><span>${esc(c.from)} · ${esc(c.subj)}</span>${res(c.result)}</div>`).join('')}</div>
    <div class="card space-y-3"><h3 class="h3">Challenge a classmate</h3>
      ${approved.length ? `<div class="grid grid-cols-1 sm:grid-cols-2 gap-2"><select id="qs-q" class="inp">${approved.map(q => `<option value="${esc(q.id)}">${esc(q.subj)}: ${esc(q.text.slice(0, 60))}</option>`).join('')}</select>
        <select id="qs-to" class="inp"><option value="">Choose a classmate</option>${QZ.mates.map(m => `<option value="${esc(m.id)}">${esc(m.name)}</option>`).join('')}</select></div>
        <button onclick="qzSend()" class="btn btn-p" ${QZ.sentToday >= R.sendsPerDay ? 'disabled' : ''}>Send challenge (${R.sendsPerDay - QZ.sentToday} left today)</button>`
        : '<p class="text-sm text-slate-500">You need an approved question first. Make one below — your teacher checks it.</p>'}
      ${QZ.outgoing.slice(0, 6).map(c => `<div class="flex justify-between gap-2 text-sm text-slate-600"><span>To ${esc(c.to)} · ${esc(c.subj)}</span><span>${res(c.result)}${c.result === false && !c.fair ? ' <span class="text-xs">point waits until someone else answers it right</span>' : ''}</span></div>`).join('')}</div>
    <div class="card space-y-3"><h3 class="h3">Make a question</h3>
      ${QZ.topics.length ? `<select id="qa-topic" class="inp"><option value="">Topic your class studied</option>${QZ.topics.map((t, i) => `<option value="${i}">${esc(t.subj)} — ${esc(t.topic)} (${fmtDate(t.date)})</option>`).join('')}</select>
      <textarea id="qa-text" rows="2" class="inp" maxlength="300" placeholder="Your question"></textarea>
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">${[0, 1, 2, 3].map(i => `<label class="flex items-center gap-2"><input type="radio" name="qa-ans" value="${i}" class="w-5 h-5" title="Correct answer"><input id="qa-o${i}" class="inp" maxlength="120" placeholder="Choice ${'ABCD'[i]}"></label>`).join('')}</div>
      <p class="text-xs text-slate-500">Tick the circle next to the correct answer. Your teacher checks every question before it can be used.</p>
      <button onclick="qzAsk()" class="btn btn-p">Send to my teacher</button>` : '<p class="text-sm text-slate-500">Topics appear here after your teachers fill in their diary.</p>'}</div>
    ${mine.length ? `<div class="card space-y-2"><h3 class="h3">My questions</h3>${mine.slice(0, 10).map(q => `<div class="py-2 border-b border-line last:border-0"><div class="flex justify-between gap-2"><span class="text-sm"><b>${esc(q.subj)}</b> · ${esc(q.topic)}</span>${st[q.status] || ''}</div>
      <p class="text-sm mt-1">${esc(q.text)}</p>${q.reason ? `<p class="text-xs text-rose mt-1">Teacher: ${esc(q.reason)}</p>` : ''}${q.status === 'approved' ? `<p class="text-xs text-slate-500 mt-1">Sent to ${q.sent} of ${R.sendsPerQ} classmates</p>` : ''}</div>`).join('')}</div>` : ''}
    <div class="card"><h3 class="h3">Class leaderboard this month</h3>${QZ.board.length ? `<ol class="mt-2 space-y-1">${QZ.board.map((b, i) => `<li class="flex justify-between gap-2 ${b.me ? 'font-semibold text-ink' : ''}"><span>${i + 1}. ${esc(b.name)}${b.me ? ' (you)' : ''}</span><span>${b.pts}</span></li>`).join('')}</ol>` : '<p class="text-sm text-slate-500 mt-1">No points yet this month.</p>'}</div>
    <details class="card"><summary class="h3 cursor-pointer">How points work</summary><ul class="text-sm text-slate-600 list-disc pl-5 mt-2 space-y-1">
      <li>Daily quiz: +1 for each right answer, +2 extra if all 5 are right. One quiz a day, ${R.quizSeconds} seconds a question.</li>
      <li>Answer a classmate's challenge right within ${R.challengeSeconds} seconds: +2 for you.</li>
      <li>If your classmate gets your question wrong, you get +1 — but only after at least one other classmate has answered that question right. Trick questions earn nothing.</li>
      <li>Each question can go to ${R.sendsPerQ} classmates; you can send ${R.sendsPerDay} challenges a day, only one a day to the same classmate; points between the same two friends are limited each week.</li>
      <li>Once a question opens, the clock does not stop. Closing the page counts as a wrong answer.</li></ul></details>`;
}

// ---------------------------------------------------------------- QUIZ DUEL — teacher / principal
async function loadQuizT() { try { QT = await qapi('tstate'); } catch (e) { QT = { error: e.message }; } if (view === 'quizt') render(); }
function recentTopics(cls, subj) {
  const out = new Set(); Object.entries(S.diary).forEach(([d, byT]) => Object.values(byT).forEach(byB => Object.values(byB).forEach(e => { if (e.cls === cls && (!subj || e.subj === subj) && e.topic) out.add(e.topic); })));
  return [...out].slice(0, 30);
}
function vQuizT() {
  if (!QT) { loadQuizT(); return head('Quiz Duel') + '<div class="card text-slate-500">Loading…</div>'; }
  if (QT.error) return head('Quiz Duel') + `<div class="card">${esc(QT.error)} <button onclick="QT=null;render()" class="btn btn-sm btn-l ml-2">Try again</button></div>`;
  const allocs = U.role === 'principal' ? S.alloc.filter(a => a.n > 0) : myAllocs(U.tid);
  const k0 = P.key && allocs.some(a => sylKey(a.c, a.s) === P.key) ? P.key : (allocs[0] ? sylKey(allocs[0].c, allocs[0].s) : '');
  const [c0, s0] = k0.split('|');
  const pend = QT.questions.filter(q => q.status === 'pending');
  const appr = QT.questions.filter(q => q.status === 'approved');
  const weak = appr.filter(q => q.n >= 3).map(q => ({ ...q, pct: Math.round(q.ok / q.n * 100) })).sort((a, b) => a.pct - b.pct).slice(0, 8);
  const ws = appr.filter(q => q.cls === c0 && q.subj === s0);
  return head('Quiz Duel', 'Approve students\' questions, add your own, see which topics need reteaching, and print worksheets.') + `
    <div class="card space-y-3"><h3 class="h3">Waiting for you (${pend.length})</h3>
      ${pend.length ? pend.map(q => `<div class="border border-line rounded-2xl p-4 space-y-2"><div class="flex flex-wrap justify-between gap-2 text-sm"><span><b>Class ${esc(q.cls)} · ${esc(q.subj)}</b> · ${esc(q.topic)}</span><span class="text-slate-500">by ${esc(q.author)}</span></div>
        <p class="font-medium">${esc(q.text)}</p>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-1">${q.opts.map((o, i) => `<label class="flex items-center gap-2 text-sm"><input type="radio" name="qd-${esc(q.id)}" value="${i}" ${i === q.ans ? 'checked' : ''}> ${esc(o)}</label>`).join('')}</div>
        <p class="text-xs text-slate-500">The ticked choice is the correct answer. Change it before approving if the student got it wrong.</p>
        <div class="flex gap-2"><button onclick="qtDecide('${esc(q.id)}','approved')" class="btn btn-sm btn-g">Approve</button><button onclick="qtDecide('${esc(q.id)}','rejected')" class="btn btn-sm btn-l">Not approved</button></div></div>`).join('')
        : '<p class="text-sm text-slate-500">Nothing to check.</p>'}</div>
    ${allocs.length ? `<div class="card space-y-3"><div class="flex flex-wrap justify-between gap-2"><h3 class="h3">Add a question</h3>
      <select class="inp w-auto" onchange="go('quizt',{key:this.value})">${allocs.map(a => { const k = sylKey(a.c, a.s); return `<option value="${esc(k)}" ${k === k0 ? 'selected' : ''}>Class ${a.c} — ${esc(a.s)}</option>`; }).join('')}</select></div>
      <input id="qt-topic" class="inp" list="qt-topics" placeholder="Topic (from your diary)"><datalist id="qt-topics">${recentTopics(c0, s0).map(t => `<option value="${esc(t)}">`).join('')}</datalist>
      <textarea id="qt-text" rows="2" class="inp" maxlength="300" placeholder="Question"></textarea>
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">${[0, 1, 2, 3].map(i => `<label class="flex items-center gap-2"><input type="radio" name="qt-ans" value="${i}" class="w-5 h-5"><input id="qt-o${i}" class="inp" maxlength="120" placeholder="Choice ${'ABCD'[i]}"></label>`).join('')}</div>
      <button onclick="qtAdd(${jsq(c0)},${jsq(s0)})" class="btn btn-p">Add approved question</button></div>` : ''}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
      <div class="card"><h3 class="h3">Topics students find hard</h3>${weak.length ? `<table class="tbl mt-2"><tr><th>Class · subject · topic</th><th class="text-right">Right</th></tr>${weak.map(q => `<tr><td>${esc(q.cls)} · ${esc(q.subj)} · ${esc(q.topic)}<br><span class="text-xs text-slate-500">${esc(q.text.slice(0, 80))}</span></td><td class="text-right font-semibold ${q.pct < 50 ? 'text-rose' : ''}">${q.pct}% <span class="text-xs text-slate-400">of ${q.n}</span></td></tr>`).join('')}</table>
        <p class="text-xs text-slate-500 mt-2">Below 50% usually means the topic needs a short reteach.</p>` : '<p class="text-sm text-slate-500 mt-1">Shows up once questions have 3 or more answers.</p>'}</div>
      <div class="card space-y-2"><h3 class="h3">Worksheet</h3><p class="text-sm text-slate-600">${ws.length} approved question(s) for Class ${esc(c0 || '')} ${esc(s0 || '')}. Prints as a worksheet, with the answer key on a separate page.</p>
        <button onclick="qtWorksheet(${jsq(c0)},${jsq(s0)})" class="btn btn-l" ${ws.length ? '' : 'disabled'}><i class="fa-solid fa-print"></i> Print worksheet</button></div>
    </div>
    <div class="card"><h3 class="h3">Leaderboards this month</h3><div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-2">${Object.entries(QT.boards).filter(([c, r]) => r.length).map(([c, rows]) => `<div><b>Class ${esc(c)}</b><ol class="text-sm mt-1">${rows.slice(0, 5).map((r, i) => `<li>${i + 1}. ${esc(r.name)} — ${r.pts}</li>`).join('')}</ol></div>`).join('') || '<p class="text-sm text-slate-500">No points yet.</p>'}</div></div>`;
}
async function qtDecide(id, status) {
  const q = QT.questions.find(x => x.id === id); if (!q) return;
  const body = { qid: id, status };
  if (status === 'rejected') { const r = prompt('Tell the student why (optional)', ''); if (r === null) return; body.reason = r; }
  else { const pick = document.querySelector(`input[name="qd-${id}"]:checked`); if (pick && +pick.value !== q.ans) body.ans = +pick.value; }
  try { await qapi('decide', body); toast(status === 'approved' ? 'Approved' : 'Sent back'); QT = null; render(); } catch (e) { toast(e.message, 'r'); }
}
async function qtAdd(cls, subj) {
  const topic = document.getElementById('qt-topic').value.trim(), text = document.getElementById('qt-text').value.trim();
  const opts = [0, 1, 2, 3].map(i => document.getElementById('qt-o' + i).value.trim()), pick = document.querySelector('input[name="qt-ans"]:checked');
  if (!pick) return toast('Tick the correct answer', 'a');
  try { await qapi('add', { cls, subj, topic, text, opts, ans: +pick.value }); toast('Question added'); QT = null; render(); } catch (e) { toast(e.message, 'r'); }
}
function qtWorksheet(cls, subj) {
  const qs = QT.questions.filter(q => q.status === 'approved' && q.cls === cls && q.subj === subj).sort((a, b) => String(a.topic).localeCompare(String(b.topic)));
  printHTML('Worksheet', printHeader(`Worksheet — Class ${esc(cls)} ${esc(subj)}`) + `<p>Name ____________________ &nbsp; Roll no. _____ &nbsp; Date __________</p>` +
    qs.map((q, i) => `<div style="margin:10px 0;page-break-inside:avoid"><b>${i + 1}. ${esc(q.text)}</b> <span style="color:#666;font-size:10px">(${esc(q.topic)})</span><br>${q.opts.map((o, j) => `&nbsp;&nbsp;(${'abcd'[j]}) ${esc(o)}`).join('<br>')}</div>`).join('') +
    `<div class="pb"></div><h2>Answer key</h2><p>${qs.map((q, i) => `${i + 1}. (${'abcd'[q.ans]})`).join(' &nbsp; ')}</p>` + PRINT_FOOT);
  log('Quiz', `Printed a ${qs.length}-question worksheet for Class ${cls} ${subj}`);
}

// ---------------------------------------------------------------- routes
const VIEWS = { dash: vDash, staff: vStaff, timetable: vTimetable, attendance: vAttendance, diaries: vDiaries, syllabus: vSyllabus,
  results: vResults, students: vStudents, notices: vNotices, mytoday: vMyToday, myweek: vMyWeek, diary: vDiary, marks: vMarks,
  leave: vLeave, log: vLog, settings: vSettings, calendar: vCalendar, behaviour: vBehaviour, speaking: vSpeaking,
  lplan: vLessonPlan, lplans: vLessonPlans, fame: vFame, exams: vExams, quiz: vQuiz, quizt: vQuizT, phome: vPHome, phw: vPHw, presults: vPResults, pnotices: vPNotices };
// principal also teaches: give the principal access to diary/marks from "My Teaching"
NAV.principal.splice(NAV.principal.findIndex(x => x[0] === 'mytoday') + 1, 0, ['diary','fa-book','My Diary'], ['lplan','fa-clipboard-check','My Lesson Plan'], ['marks','fa-pen-to-square','Marks']);

// Learning Lab uses a separate subject/child-scoped PHP API, sharing the existing login.
NAV.principal.push(['learning','fa-seedling','Learning Lab']);
NAV.teacher.push(['learning','fa-seedling','Learning Lab']);
NAV.parent.push(['learning','fa-seedling','Learning Adventure']);
VIEWS.learning=()=>head(U.role==='parent'?'Learning Adventure':'Learning Lab','Practice, skill evidence and teacher-reviewed learning missions.')+'<iframe title="Learning Lab" src="learning.php?embed=1" style="width:100%;min-height:85vh;border:0;border-radius:16px"></iframe>';

// ---------------------------------------------------------------- init
(async function init() {
  TOKEN = tokenGet();
  if (TOKEN) {
    try { if (tokenRole() === 'parent' || tokenRole() === 'student') await loadParent(); else await loadAll(); render(); return; }
    catch (e) {
      if (/internet|Server problem|reachable/i.test(e.message)) {
        document.getElementById('root').innerHTML = `<div class="flex-1 flex items-center justify-center p-6 text-center"><div><p class="font-bold">No connection to the school server.</p><p class="text-sm text-slate-500">${esc(e.message)}</p><button onclick="location.reload()" class="btn btn-p mt-3">Try again</button></div></div>`;
        return;
      }
      toast(e.message, 'a'); TOKEN = null; tokenSet(null); S = null; U = null;
    }
  }
  refreshStatus();
})();

// Smart Studio has its own class/child-scoped API; media never enters legacy sync.
for (const role of ['principal','teacher','parent']) NAV[role].push(['studio','fa-camera','Smart Studio']);
VIEWS.studio=()=>head(U.role==='parent'?'Classroom & Copies':'Teacher Smart Studio','Private notebook checking, classroom moments, badges and conversations.')+'<iframe title="Teacher Smart Studio" src="studio.php?embed=1" style="width:100%;min-height:90vh;border:0;border-radius:16px"></iframe>';

for(const role of ['principal','teacher']) NAV[role].push(['toolkit','fa-wand-magic-sparkles','Teacher Toolkit']);
VIEWS.toolkit=()=>head('Teacher Smart Toolkit','Less paperwork. More room to teach.')+'<iframe title="Teacher Smart Toolkit" src="toolkit.php?embed=1" style="width:100%;min-height:90vh;border:0;border-radius:16px"></iframe>';
function teacherCorner(date){const p=TeacherEnglish.today(date);return `<div class="modern-bento"><section class="modern-welcome"><span class="eyebrow">YOUR TEACHING COMPANION</span><h2>A little less admin.<br>More room to teach.</h2><p>Your lesson tools, classroom moments and professional identity, in one place.</p><div class="modern-links"><button class="btn btn-p" onclick="go('toolkit')">Open Teacher Toolkit</button><button class="btn btn-l" onclick="go('profile')">My Profile Studio →</button></div></section><section class="modern-mini"><small>A phrase for your classroom</small><b>${esc(p[0])}</b><details><summary>Hindi meaning</summary>${esc(p[1])}</details><button class="btn btn-sm btn-l" onclick="TeacherEnglish.say(TeacherEnglish.today('${date}')[0])">Listen & try</button></section></div>`;}

function prepareDiaryDraft(date){if(S.signed[date]?.[U.tid])return toast('Signed diary is locked.','a');let n=0;for(const b of myBells(U.tid,date)){const field=document.getElementById('b'+b.bell+'-t');if(!field||field.value.trim()||diaryEntry(date,U.tid,b.bell))continue;const plan=Object.values(S.lplan).find(p=>p.tid===U.tid&&p.week===weekStart(date)&&String(p.cls)===String(b.cls)&&p.subj===b.subj&&p.status==='Approved');if(plan){field.value=plan.topics.split('\n').map(x=>x.trim()).filter(Boolean)[0]||'';if(field.value)n++;}}toast(n?'Prepared '+n+' topic drafts. Edit and confirm each bell.':'No empty bells with approved plans. Enter the actual topics.');}

for(const role of ['teacher','principal']) NAV[role].push(['profile','fa-id-card','My Profile'],['saathi','fa-feather','Secret Saathi']);
VIEWS.profile=()=>head('Teacher Profile Studio','Your identity, credentials and growth, together.')+'<iframe class="edu-iframe" title="Teacher Profile Studio" src="faculty.php?embed=1"></iframe>';
VIEWS.saathi=()=>head('Secret Saathi','A private reflection space for your own account.')+'<iframe class="edu-iframe" title="Secret Saathi" src="faculty.php?embed=1&tab=saathi"></iframe>';
NAV.parent.push(['faculty','fa-chalkboard-user','Our Teachers']);
VIEWS.faculty=()=>head('Our Teachers','School-reviewed professional profiles shared with your class.')+'<div id="parentFaculty" class="modern-bento">Loading teacher profiles…</div>';
async function loadParentFaculty(){try{const r=await fetch('faculty_api.php?a=directory',{headers:{'X-School-Token':TOKEN}});const d=await r.json();if(!r.ok||!d.ok)throw Error(d.error||'Profiles unavailable');const node=document.getElementById('parentFaculty');if(node)node.innerHTML=d.profiles.map(p=>`<article class="card">${p.photoData?`<img src="${p.photoData}" alt="Teacher portrait" style="width:72px;height:72px;object-fit:cover;border-radius:22px;margin-bottom:12px">`:''}<h3 class="h3">${esc(p.fullName)}</h3><p class="text-sm text-slate-500">${esc(p.designation)}</p><p class="text-sm mt-3">${esc(p.bio)}</p><p class="text-sm mt-2">${esc(p.quote)}</p><p class="text-xs text-slate-500 mt-3">${esc((p.subjects||[]).join(' · '))}</p></article>`).join('')||'<div class="card text-sm">Your teachers’ profiles will appear here after verification and sharing permission.</div>';}catch(e){const node=document.getElementById('parentFaculty');if(node)node.textContent=e.message;}}
