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
           signed: {}, syl: {}, exams: [], marks: {}, leaves: [], notices: [], behav: {}, speak: [], lplan: {}, cal: [], fees: null, log: [] };
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
  if (S.fees) put('fees', 'main', S.fees);
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
    case 'fees': S.fees = d; break;
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
function save() { if (isFam() || isOffice()) return; clearTimeout(syncTimer); syncTimer = setTimeout(sync, 250); }
async function sync() {
  if (!TOKEN || !S || isFam() || isOffice()) return;
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
  if (!TOKEN || !S || document.hidden || isFam() || isOffice()) return;
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
window.addEventListener('beforeunload', e => { if (isFam() || isOffice()) return; if (syncing || pendingLog.length || (S && Object.entries(toRecords()).some(([k, v]) => SYNCED[k] !== v))) { e.preventDefault(); e.returnValue = ''; } });
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
  if (U.role === 'principal') { applyBoardPlan(); applyFeeSheet(); }
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
  if (isOffice()) return ME ? ME.label : U.role;
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
  // "Fixed bells": cnt[l][b] = on how many days lesson l sits in bell b. Rewarding cnt² pulls each
  // subject into the same bell Monday–Saturday; it only moves where a clash or rule forces it.
  const cnt = new Int16Array(alloc.length * Bn), W = 1.0;
  const cMove = (l, a, b) => { if (l < 0 || a === b) return 0; const d = -2 * (cnt[l * Bn + b] - cnt[l * Bn + a] + 1); cnt[l * Bn + a]--; cnt[l * Bn + b]++; return W * d; };
  const cls = CLASS_NAMES.map(c => {
    const arr = []; alloc.forEach((a, i) => { if (a.c === c) for (let n = 0; n < (+a.n || 0); n++) arr.push(i); });
    while (arr.length < N) arr.push(-1);
    for (let i = arr.length - 1; i > 0; i--) { const j = Math.floor(rand() * (i + 1)); [arr[i], arr[j]] = [arr[j], arr[i]]; }
    arr.forEach((l, s) => { if (l >= 0) { occ[aT[l] * N + s]++; pd[l * D + ((s / Bn) | 0)]++; cnt[l * Bn + s % Bn]++; } });
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
  const IT = 450000; let temp = 4; const cool = Math.pow(0.01 / temp, 1 / IT);
  for (let it = 0; it < IT; it++, temp *= cool) {
    const arr = cls[(rand() * cls.length) | 0], p = (rand() * N) | 0, q = (rand() * N) | 0, x = arr[p], y = arr[q];
    if (p === q || x === y) continue;
    const ks = keys(x, y, p, q), before = part(arr, ks, p, q), bp = p % Bn, bq = q % Bn;
    mv(x, p, q); mv(y, q, p); arr[p] = y; arr[q] = x;
    const d = part(arr, ks, p, q) - before + cMove(x, bp, bq) + cMove(y, bq, bp);
    if (d > 0 && rand() >= Math.exp(-d / temp)) { mv(x, q, p); mv(y, p, q); arr[p] = x; arr[q] = y; cMove(y, bp, bq); cMove(x, bq, bp); }
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
// Absence can be the whole day or "from bell N" (late arrival / leaving early = half day).
function absentFrom(date, t) {           // null = present, 0 = whole day, n = absent from bell n onwards
  if (S.leaves.some(l => l.status === 'Approved' && l.tid === t && l.from <= date && l.to >= date)) return 0;
  const a = S.staffAtt[date]; if (!a || !(a.absent || []).includes(t)) return null;
  return +((a.from || {})[t] || 0);
}
function absentAt(date, t, b) { const f = absentFrom(date, t); return f !== null && b >= f; }
function busyAt(date, x, b) { const d = dayIdx(date); const q = d < 0 ? null : teacherAt(x, d, b); return !!q && !examBlocks(date, q.c); }
// One day's arrangement. load = arrangement bells each teacher already did this month (fair sharing).
// The Principal can fix a substitute for a bell: staffAtt[date].subs['absentTid|bell'] = tid, or '-' for "nobody".
function arrangementBase(date, load) {
  const d = dayIdx(date); if (d < 0 || !S.grid || holidayOn(date)) return [];
  const absent = staffAbsent(date), ov = (S.staffAtt[date] && S.staffAtt[date].subs) || {}, extra = {}, need = [];
  absent.forEach(t => BELLS.forEach((b, i) => {
    if (!absentAt(date, t, i)) return;
    const a = teacherAt(t, d, i); if (!a || examBlocks(date, a.c)) return;
    need.push({ bell: i, cls: a.c, subj: a.s, absent: t });
  }));
  const taken = i => need.filter(n => n.bell === i && n.sub).map(n => n.sub);
  const canTake = (x, n) => x !== n.absent && !absentAt(date, x, n.bell) && !busyAt(date, x, n.bell) && !taken(n.bell).includes(x)
    && (!S.teachers[x].bells || S.teachers[x].bells.includes(n.bell));
  const dayLoad = x => BELLS.filter((_, j) => busyAt(date, x, j)).length;
  const score = (x, n) => { let s = 0;
    if (S.alloc.some(q => q.t === x && q.s === n.subj)) s += 5;          // same subject
    if (S.alloc.some(q => q.t === x && q.c === n.cls)) s += 2;           // knows the class
    s -= (extra[x] || 0) * 3 + (load[x] || 0) * 0.8 + dayLoad(x) * 0.3;   // share the work fairly
    if (x === 'P') s -= 4; if (['RI', 'SH', 'MO'].includes(x)) s -= 10;   // Principal last; pre-primary stays with toddlers
    return s; };
  need.forEach(n => { const w = ov[n.absent + '|' + n.bell]; n.sub = null; n.manual = false;     // 1st: the Principal's own choices
    if (w === '-') n.manual = true; else if (w && S.teachers[w] && canTake(w, n)) { n.sub = w; n.manual = true; extra[w] = (extra[w] || 0) + 1; } });
  need.forEach(n => { if (n.manual) return;                                                   // 2nd: everyone else, fairly
    const pool = Object.keys(S.teachers).filter(x => canTake(x, n)).sort((x, y) => score(y, n) - score(x, n));
    n.sub = pool[0] || null; if (n.sub) extra[n.sub] = (extra[n.sub] || 0) + 1; });
  need.forEach(n => { n.options = Object.keys(S.teachers).filter(x => x === n.sub || canTake(x, n)); });
  return need.sort((x, y) => x.bell - y.bell);
}
function monthLoad(date, includeDay) {
  const out = {}; let d = date.slice(0, 8) + '01';
  while (d < date || (includeDay && d === date)) {
    if (staffAbsent(d).length) arrangementBase(d, {}).forEach(s => { if (s.sub) out[s.sub] = (out[s.sub] || 0) + 1; });
    d = addDays(d, 1);
  }
  return out;
}
function arrangement(date) { return arrangementBase(date, monthLoad(date)); }
function freeAt(date, b) {
  const subs = arrangement(date).filter(s => s.bell === b && s.sub).map(s => s.sub);
  return Object.keys(S.teachers).filter(x => !absentAt(date, x, b) && !busyAt(date, x, b) && !subs.includes(x) && (!S.teachers[x].bells || S.teachers[x].bells.includes(b)));
}
function myBells(tid, date) {
  const d = dayIdx(date); if (d < 0 || !S.grid || holidayOn(date)) return [];
  const from = absentFrom(date, tid);
  const out = [];
  BELLS.forEach((b, i) => { if (from !== null && i >= from) return; const a = teacherAt(tid, d, i); if (a && !examBlocks(date, a.c)) out.push({ bell: i, cls: a.c, subj: a.s, arr: false }); });
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
  principal: [['dash','fa-gauge','Dashboard'],['admin','fa-chart-pie','Admin Dashboard'],['rules','fa-scale-balanced','Rules & Policies'],['staff','fa-user-check','Staff & Arrangement'],['salary','fa-money-check-dollar','Salary'],['timetable','fa-calendar-week','Timetable'],
              ['attendance','fa-clipboard-user','Student Attendance'],['diaries','fa-book-open-reader','Diaries'],['syllabus','fa-list-check','Syllabus'],
              ['lplans','fa-clipboard-check','Lesson Plans'],['exams','fa-file-pen','Exams & Date Sheets'],['quizt','fa-bolt','Quiz Duel'],['fees','fa-indian-rupee-sign','Fee Structure'],['fdesk','fa-cash-register','Fee Desk'],['fcalls','fa-phone','Dues Calling'],['freceipts','fa-receipt','Receipts'],['results','fa-square-poll-vertical','Report Cards & Results'],['behaviour','fa-hand-holding-heart','Behaviour'],
              ['speaking','fa-microphone','Speaking'],['fame','fa-trophy','Hall of Fame'],['calendar','fa-calendar-days','Calendar'],
              ['students','fa-children','Students'],['notices','fa-bullhorn','Notices'],
              ['mytoday','fa-chalkboard-user','My Teaching'],['log','fa-clock-rotate-left','Activity Log'],['settings','fa-gear','Settings'],['office','fa-folder-tree','School Office']],
  teacher:   [['mytoday','fa-sun','Today'],['quizt','fa-bolt','Quiz Duel'],['attendance','fa-clipboard-user','Attendance'],['diary','fa-book','Diary'],['lplan','fa-clipboard-check','Lesson Plan'],
              ['speaking','fa-microphone','Speaking'],['behaviour','fa-hand-holding-heart','Behaviour'],['syllabus','fa-list-check','Syllabus'],
              ['marks','fa-pen-to-square','Marks Entry'],['myweek','fa-calendar-week','My Week'],['fame','fa-trophy','Hall of Fame'],['calendar','fa-calendar-days','Calendar'],['leave','fa-plane-departure','Leave'],['salary','fa-money-check-dollar','My Salary'],['rules','fa-scale-balanced','School Rules'],['office','fa-folder-tree','School Office']],
  student:   [['phome','fa-house','Home'],['game','fa-gamepad','Game Zone'],['quiz','fa-bolt','Quiz Duel'],['phw','fa-book-open','Homework'],['presults','fa-chart-simple','Progress'],['pnotices','fa-bullhorn','Notices']],
  parent:    [['phome','fa-house','Home'],['game','fa-gamepad','Game Zone'],['quiz','fa-bolt','Quiz Duel'],['pfees','fa-indian-rupee-sign','Fees'],['phw','fa-book-open','Homework'],['presults','fa-chart-simple','Progress'],['pnotices','fa-bullhorn','Notices']],
  accountant:[['salary','fa-money-check-dollar','Staff Salary'],['fdesk','fa-cash-register','Fee Desk'],['fdues','fa-list-ol','Dues'],['fcalls','fa-phone','Calls'],['freceipts','fa-receipt','Receipts'],['office','fa-folder-tree','School Office']],
  reception: [['fcalls','fa-phone','Calls'],['fdues','fa-list-ol','Dues'],['freceipts','fa-receipt','Receipts'],['office','fa-folder-tree','School Office']],
  chairman:  [['dash','fa-gauge','Dashboard'],['admin','fa-chart-pie','Admin Dashboard'],['rules','fa-scale-balanced','Rules & Policies'],['fdues','fa-list-ol','Fee Dues'],['exams','fa-file-pen','Exams'],['fees','fa-indian-rupee-sign','Fee Structure'],['results','fa-square-poll-vertical','Report Cards & Results'],['fame','fa-trophy','Hall of Fame'],['syllabus','fa-list-check','Syllabus'],['calendar','fa-calendar-days','Calendar'],['log','fa-clock-rotate-left','Activity Log'],['office','fa-folder-tree','School Office']]
};

const PRIMARY = { student: ['phome','game','phw','presults'], principal: ['dash','staff','timetable','lplans'], teacher: ['mytoday','attendance','diary','speaking'],
                  chairman: ['dash','results','fame','calendar'], parent: ['phome','game','phw','presults'],
                  accountant: ['fdesk','fdues','fcalls','freceipts'], reception: ['fcalls','fdues','freceipts'] };
// Top navigation: header with the school name, then capsule groups (Dashboard · Staff · Students · Admin),
// then the pages of the open group as smaller capsules. Any page not listed here falls into the last group,
// so pages added later (by either AI) never disappear from the menu.
const GROUPS = {
  principal: [['Dashboard', 'fa-gauge', ['dash']],
              ['Staff', 'fa-user-tie', ['staff', 'salary', 'timetable', 'lplans', 'diaries', 'mytoday', 'diary', 'lplan', 'toolkit', 'profile', 'saathi']],
              ['Students', 'fa-children', ['students', 'attendance', 'syllabus', 'exams', 'behaviour', 'speaking', 'fame', 'quizt', 'studio', 'learning']],
              ['Admin', 'fa-building-columns', ['admin', 'office', 'fdesk', 'finance', 'fdues', 'fcalls', 'freceipts', 'fees', 'marks', 'results', 'notices', 'calendar', 'rules', 'log']]],
  chairman:  [['Dashboard', 'fa-gauge', ['dash']], ['Students', 'fa-children', ['exams', 'results', 'fame', 'syllabus']],
              ['Admin', 'fa-building-columns', ['admin', 'office', 'finance', 'fdues', 'fees', 'calendar', 'rules', 'log']]],
  teacher:   [['Today', 'fa-sun', ['mytoday', 'myweek', 'calendar']], ['My Classes', 'fa-chalkboard', ['attendance', 'diary', 'marks', 'behaviour', 'speaking', 'quizt', 'studio', 'learning']],
              ['Planning', 'fa-clipboard-check', ['lplan', 'syllabus', 'toolkit', 'fame', 'office']], ['Me', 'fa-user', ['leave', 'salary', 'rules', 'profile', 'saathi', 'finance']]],
  parent:    [['Home', 'fa-house', ['phome', 'pnotices', 'faculty']], ['Learning', 'fa-book-open', ['phw', 'game', 'quiz', 'learning', 'studio']],
              ['Progress & Fees', 'fa-chart-simple', ['presults', 'pfees', 'finance']]],
  student:   [['Home', 'fa-house', ['phome', 'pnotices']], ['Learning', 'fa-book-open', ['phw', 'game', 'quiz', 'learning']], ['Progress', 'fa-chart-simple', ['presults', 'finance']]],
  accountant:[['Fees', 'fa-wallet', ['finance', 'fdesk', 'fdues', 'fcalls', 'freceipts']], ['Staff', 'fa-user-tie', ['salary']], ['Office', 'fa-folder-tree', ['office']]],
  reception: [['Fees', 'fa-wallet', ['fcalls', 'fdues', 'freceipts', 'finance']], ['Office', 'fa-folder-tree', ['office']]]
};
const NAV_HIDDEN = { principal: ['settings'] };      // reached from the gear button in the header
function navGroups() {
  const items = NAV[U.role], seen = new Set(NAV_HIDDEN[U.role] || []);
  const groups = (GROUPS[U.role] || [['Menu', 'fa-bars', []]]).map(([name, ic, keys]) => ({ name, ic,
    items: keys.map(k => items.find(x => x[0] === k)).filter(x => x && !seen.has(x[0]) && seen.add(x[0])) }));
  const rest = items.filter(x => !seen.has(x[0])); if (rest.length) groups[groups.length - 1].items.push(...rest);
  return groups.filter(g => g.items.length);
}
let moreOpen = false;
function navLabel(k) { const n = NAV[U.role].find(x => x[0] === k); return n ? n[2] : ''; }
function render() {
  const root = document.getElementById('root');
  if (!U || !S) { root.innerHTML = vLogin(); return; }
  if (!view || !NAV[U.role].some(x => x[0] === view)) view = NAV[U.role][0][0];
  let body = '';
  try { body = (VIEWS[view] || vDash)(); } catch (e) { console.error(e); body = `<div class="card text-rose">This screen could not open: ${esc(e.message)}. Please go back and try again.</div>`; }
  const groups = navGroups(), cur = groups.find(g => g.items.some(x => x[0] === view)) || groups[0];
  const name = (userName() || '?').replace(/\s*\(.*\)/, '').trim();
  const initials = U.role === 'parent' ? 'P' : name.split(/\s+/).map(x => x[0]).slice(0, 2).join('').toUpperCase();
  const roleName = { principal: 'Principal', teacher: 'Teacher', chairman: 'Chairman', parent: 'Parent', student: 'Student', accountant: 'Accountant', reception: 'Reception' }[U.role] || U.role;
  const page = NAV[U.role].find(x => x[0] === view);
  // Old-ERP look for office roles (school/classic.js): same menu, names and colours as the old ERP.
  const classic = typeof classicOn === 'function' && classicOn();
  document.documentElement.classList.toggle('classic', classic);
  if (classic) { root.innerHTML = classicShell(body, name, roleName) + (PROF && canProfile() ? vProfileWindow() : ''); setNet(netState); return; }
  const menu = g => g.items.length === 1
    ? `<button onclick="go('${g.items[0][0]}')" class="mn ${g === cur ? 'on' : ''}"><i class="fa-solid ${g.ic}"></i>${esc(g.name)}</button>`
    : `<div class="dd"><button onclick="ddToggle(this)" class="mn ${g === cur ? 'on' : ''}" aria-haspopup="true"><i class="fa-solid ${g.ic}"></i>${esc(g.name)}<i class="fa-solid fa-chevron-down mn-cv"></i></button>
        <div class="dd-panel ${g.items.length > 7 ? 'dd-2' : ''}">${g.items.map(([k, ic, lb]) => `<button onclick="ddClose();go('${k}')" class="dd-item ${view === k ? 'on' : ''}"><i class="fa-solid ${ic}"></i><span>${esc(lb)}</span></button>`).join('')}</div></div>`;
  root.innerHTML = `
  <div class="min-h-screen flex flex-col">
    <header class="erp-bar noprint"><div class="erp-wrap erp-bar-in">
      <button onclick="go('${NAV[U.role][0][0]}')" class="erp-brand"><span class="erp-logo">SN</span><span class="min-w-0 text-left"><b>${esc(S.settings.school)}</b><small>${esc(S.settings.place || 'Bhadohi')} · Session ${esc(S.settings.session || '')}</small></span></button>
      <nav class="erp-menu" aria-label="Main menu">${groups.map(menu).join('')}</nav>
      <div class="erp-right">
        ${isFam() || isOffice() ? '' : '<span id="net" class="net" title="Changes are saved to the school server"></span>'}
        ${canProfile() ? `<div class="srch"><div class="srch-box" onclick="this.parentElement.classList.add('open');this.querySelector('input').focus()"><i class="fa-solid fa-magnifying-glass"></i>
          <input id="g-q" placeholder="Search any teacher or student…" autocomplete="off" oninput="gsearch(this.value)" onkeydown="if(event.key==='Enter')openProfile(null,null,this.value);if(event.key==='Escape'){this.value='';gsearch('');this.blur();this.closest('.srch').classList.remove('open')}" onblur="setTimeout(()=>{if(!this.value){this.closest('.srch').classList.remove('open');}const r=document.getElementById('g-res');if(r)r.classList.remove('show')},200)"></div>
          <div id="g-res" class="srch-res"></div></div>` : ''}
        ${canViewSession() ? `<div class="dd dd-r"><button onclick="ddToggle(this)" class="mn" title="Session shown in School Office and Fee Department" aria-haspopup="true"><i class="fa-solid fa-calendar"></i>${esc(viewSession())}<i class="fa-solid fa-chevron-down mn-cv"></i></button>
          <div class="dd-panel">${sessionList().map(x => `<button onclick="ddClose();setViewSession('${x}')" class="dd-item ${x === viewSession() ? 'on' : ''}"><i class="fa-solid fa-calendar-day"></i><span>${x}${x === curSession() ? ' (current)' : ''}</span></button>`).join('')}</div></div>` : ''}
        ${U.role === 'principal' ? `<button onclick="go('settings')" class="erp-ic ${view === 'settings' ? 'on' : ''}" title="Settings"><i class="fa-solid fa-gear"></i></button>` : ''}
        <div class="dd dd-r"><button onclick="ddToggle(this)" class="erp-av-btn" title="${esc(name)} · ${esc(roleName)}"><span class="erp-avatar">${esc(initials)}</span></button>
          <div class="dd-panel"><div class="px-3 py-2"><b class="block text-sm">${esc(name)}</b><small class="text-slate-500">${esc(roleName)}</small></div>
            ${U.role === 'principal' ? `<button onclick="ddClose();go('settings')" class="dd-item"><i class="fa-solid fa-gear"></i><span>Settings & PINs</span></button>` : ''}
            ${['principal','chairman','accountant','reception'].includes(U.role) ? `<button onclick="ddClose();setLook('old')" class="dd-item"><i class="fa-solid fa-table-columns"></i><span>Old ERP look</span></button>` : ''}
            <button onclick="ddClose();logout()" class="dd-item"><i class="fa-solid fa-arrow-right-from-bracket"></i><span>Log out</span></button></div></div>
      </div></div></header>
    <div class="erp-crumb noprint"><div class="erp-wrap">${page && page[2] !== cur.name ? `${esc(cur.name)} <i class="fa-solid fa-chevron-right"></i> <b>${esc(page[2])}</b>` : `<b>${esc(cur.name)}</b>`}</div></div>
    <main class="erp-wrap w-full flex-1 pb-8 space-y-4">${body}</main>
    <footer class="erp-wrap w-full py-6 text-center text-xs text-slate-400 noprint">Powered by <b class="text-slate-500">Maakit</b></footer>
  </div>${PROF && canProfile() ? vProfileWindow() : ''}`;
  setNet(netState);
  if(view==='faculty') loadParentFaculty();
}
function ddClose() { document.querySelectorAll('.dd.open').forEach(d => d.classList.remove('open')); }
function ddToggle(btn) { const d = btn.parentElement, was = d.classList.contains('open'); ddClose(); if (!was) d.classList.add('open'); }
document.addEventListener('click', e => { if (!e.target.closest || !e.target.closest('.dd')) ddClose(); });
document.addEventListener('keydown', e => { if (e.key === 'Escape') ddClose(); });

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
  const STAFF = ['principal', 'chairman', 'accountant', 'reception'], isStaff = STAFF.includes(loginRole), off = STATUS.office || { accountant: 'Akash Tiwari', reception: 'Shiva Rai' };
  const roles = [['teacher', 'Teacher'], ['student', 'Student'], ['parent', 'Parent'], ['principal', 'Staff']];
  const staffSel = `<div><label class="lbl">Who are you?</label><select id="lg-staff" class="inp" onchange="loginRole=this.value;render()">${[['principal', 'Principal'], ['chairman', 'Chairman'], ['accountant', esc(off.accountant) + ' — Accountant'], ['reception', esc(off.reception) + ' — Reception']]
    .map(([r, l]) => `<option value="${r}" ${loginRole === r ? 'selected' : ''}>${l}</option>`).join('')}</select></div>`;
  return shell(`
      <h2 class="text-xl font-semibold">Log in</h2>
      <div class="seg">${roles.map(([r, l]) => `<button onclick="loginRole='${r}';render()" class="${loginRole === r || (r === 'principal' && isStaff) ? 'on' : ''}">${l}</button>`).join('')}</div>
      ${isStaff ? staffSel : ''}
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
  if (role === 'parent' || role === 'student') await loadParent(); else if (role === 'accountant' || role === 'reception') await loadOffice(); else await loadAll();
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
  QZ = null; QT = null; QPLAY = null; qzStopTimer(); GZ = null; GP = null; GT = null; gStop();
  MUST = null; PD = null; PC_LAST = null; PROF = null; PROFDATA = {}; SALSUM = null; HIST_FROM = null; SAL = null; SALOPEN = null; SCAN = null; LPDRAFT = {}; OF = null; OFSEL = null; OFOPEN = null; OFQ = ''; PFD = null; moreOpen = false; try { localStorage.removeItem('sch_role'); } catch (e) {}
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
function lastSchoolDay(d) { for (let i = 0; i < 20 && (dayIdx(d) < 0 || holidayOn(d)); i++) d = addDays(d, -1); return d; }
function vDash() {
  const date = P.date || lastSchoolDay(todayISO()), ro = U.role === 'chairman', isToday = date === todayISO();
  const ab = staffAbsent(date), as = attStats(date), ds = diaryStats(date), arr = arrangement(date), al = alerts(date);
  const nT = Object.keys(S.teachers).length, onLeave = ab.filter(t => S.leaves.some(l => l.status === 'Approved' && l.tid === t && l.from <= date && l.to >= date)).length;
  const withKids = CLASS_NAMES.filter(c => studentsOf(c).length), pending = dayIdx(date) >= 0 && !holidayOn(date) ? withKids.filter(c => !attOf(date, c)) : [];
  const hw = {}; Object.values(S.diary[date] || {}).forEach(byB => Object.values(byB).forEach(e => { if (e && e.hw && String(e.hw).trim()) hw[e.cls] = (hw[e.cls] || 0) + 1; }));
  const hwN = Object.values(hw).reduce((a, b) => a + b, 0), pct = as.total ? Math.round(as.present / as.total * 100) : null;
  const h = new Date().getHours(), hello = h < 12 ? 'Good morning' : h < 17 ? 'Good afternoon' : 'Good evening';
  const kpi = (ic, tint, lbl, val, sub, warn, go) => `<button ${go ? `onclick="go('${go}',{date:'${date}'})"` : 'disabled'} class="kpi ${warn ? 'kpi-warn' : tint}"><span class="kpi-ic"><i class="fa-solid ${ic}"></i></span><span class="kpi-val">${val}</span><span class="kpi-lbl">${lbl}</span><span class="kpi-sub">${sub}</span></button>`;
  const tile = c => { const n = studentsOf(c).length, a = attOf(date, c);
    if (!n) return `<div class="att att-empty"><b>${esc(c)}</b><span>No students</span></div>`;
    if (dayIdx(date) < 0 || holidayOn(date)) return `<div class="att att-empty"><b>${esc(c)}</b><span>No school</span><em>${n} students</em></div>`;
    if (!a) return `<button onclick="go('attendance',{date:'${date}',c:'${c}'})" class="att att-none"><b>${esc(c)}</b><span>Not marked</span><em>${n} students</em></button>`;
    const p = Math.round((n - a.absent.length) / n * 100);
    return `<button onclick="go('attendance',{date:'${date}',c:'${c}'})" class="att ${p >= 95 ? 'att-hi' : p >= 90 ? 'att-mid' : 'att-lo'}" title="Class ${esc(c)}: ${n - a.absent.length} present, ${a.absent.length} absent, ${n} on roll"><b>${esc(c)}</b><span>${p}%</span><em>${n - a.absent.length}/${n} present</em><em class="${a.absent.length ? 'ab' : ''}">${a.absent.length ? a.absent.length + ' absent' : 'none absent'}</em></button>`; };
  const trendDays = []; for (let d = date, i = 0; trendDays.length < 12 && i < 40; i++, d = addDays(d, -1)) if (dayIdx(d) >= 0 && !holidayOn(d)) trendDays.unshift(d);
  const trend = trendDays.map(d => { const st = attStats(d); return st.total ? Math.round(st.present / st.total * 100) : null; });
  if (!ro) ofFresh();
  const ofOk = OF && !OF.error && OF.payments, feeM = ofOk ? OF.payments.filter(p => !p.cancelled && p.date.slice(0, 7) === date.slice(0, 7)).reduce((a, p) => a + p.amt, 0) : null;
  const feeDue = ofOk ? OF.students.reduce((a, s) => a + Math.max(0, s.dues.due), 0) : null;
  const upcoming = [...S.cal.map(e => ({ ...e, kind: e.type === 'Holiday' ? 'hol' : '' })), ...examItems().map(e => ({ ...e, kind: 'exam' }))]
    .filter(e => (e.to || e.date) >= date).sort((a, b) => a.date.localeCompare(b.date)).slice(0, 5);
  const shortD = d => new Date(d + 'T00:00:00').toLocaleDateString('en-IN', { day: 'numeric', month: 'short' });
  const sum = dailySummary(date);
  return `<div class="flex flex-col md:flex-row md:items-end justify-between gap-3">
      <div><h2 class="text-2xl font-bold text-ink">${P.date ? 'Report for this day' : 'Welcome'}, ${esc(ro ? 'Chairman' : (T(U.tid).name || 'Principal').replace(/\s+(Sir|Ma'am)$/, '').split(' ')[0])} ${P.date ? '' : '🎉'}</h2>
        <p class="dash-eyebrow mt-1">${P.date ? '' : isToday ? `${hello}! Here's what's happening in your school today — ` : `${hello}! Today is ${dayIdx(todayISO()) < 0 ? 'Sunday' : 'a holiday'}, so this shows the last school day — `}${new Date(date + 'T00:00:00').toLocaleDateString('en-IN', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })}</p></div>
      <div class="flex flex-wrap items-end gap-6 noprint"><div class="hero-n"><b>${S.students.length}</b><small>Students</small></div><div class="hero-n"><b>${nT}</b><small>Staff</small></div><div class="hero-n"><b>${CLASS_NAMES.length}</b><small>Classes</small></div></div>
      <div class="flex flex-wrap gap-2 noprint">${dateInput('d-date', date, "go('dash',{date:this.value})")}
        <button onclick="printHTML('Day report',printHeader('Daily report — ${fmtDate(date)}')+'<pre style=&quot;white-space:pre-wrap;font-family:inherit&quot;>'+esc(dailySummary('${date}'))+'</pre>'+PRINT_FOOT)" class="btn btn-l"><i class="fa-solid fa-download"></i> Day report</button>
        ${ro ? '' : `<button onclick="go('notices')" class="btn btn-p"><i class="fa-solid fa-plus"></i> New notice</button>`}</div></div>
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
      ${kpi('fa-children', 'kpi-peach', 'Students present', as.total ? `${as.present} <small>/ ${as.total}</small>` : '—', `${as.marked} of ${withKids.length} classes marked`, false, 'attendance')}
      ${kpi('fa-user-check', 'kpi-lav', 'Staff present', `${nT - ab.length} <small>/ ${nT}</small>`, ab.length ? `${onLeave} on leave · ${ab.length - onLeave} absent` : 'Everyone present', false, ro ? '' : 'staff')}
      ${kpi('fa-clock', 'kpi-mint', 'Attendance pending', pending.length ? `${pending.length} <small>class${pending.length > 1 ? 'es' : ''}</small>` : '0', pending.length ? 'Class ' + pending.slice(0, 4).join(', ') + (pending.length > 4 ? '…' : '') : 'All classes marked', pending.length > 0, 'attendance')}
      ${kpi('fa-book', 'kpi-lime', 'Homework set', hwN, `across ${Object.keys(hw).length} class${Object.keys(hw).length === 1 ? '' : 'es'} · diary ${ds.filled}/${ds.due} bells`, false, ro ? '' : 'diaries')}
    </div>
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 items-start">
      <div class="lg:col-span-2 space-y-4">
        <div class="card"><div class="flex flex-wrap items-center justify-between gap-2 mb-4"><h3 class="h3">Attendance by class</h3>
          <div class="flex flex-wrap gap-3"><span class="legend"><i style="background:#C5EEF2"></i>95%+</span><span class="legend"><i style="background:#E3D8FF"></i>90–94%</span><span class="legend"><i style="background:#FDE4C3"></i>Below 90%</span><span class="legend"><i style="border:1.5px dashed #F2A7AA"></i>Not marked</span></div></div>
        <div class="att-grid">${CLASS_NAMES.slice().reverse().map(tile).join('')}</div></div>
        <div class="card"><div class="flex justify-between items-center"><h3 class="h3">Student attendance — last ${trendDays.length} school days</h3><span class="text-xs text-slate-500">present ÷ marked</span></div>
          ${trend.some(v => v != null) ? svgLine(trendDays.map(d => new Date(d + 'T00:00:00').toLocaleDateString('en-IN', { day: 'numeric', month: 'short' })), trend, { unit: '%', min: Math.min(70, ...trend.filter(v => v != null)), max: 100, color: CH.teal, h: 170, title: 'Attendance trend' }) : '<p class="text-sm text-slate-500 py-6">Attendance not marked on these days yet.</p>'}</div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 items-start">
          <div class="card"><div class="flex justify-between items-center mb-2"><h3 class="h3">Coming up</h3><button onclick="go('calendar')" class="link">Calendar</button></div>
        ${upcoming.length ? upcoming.map(e => `<div class="att-row justify-start"><span class="date-cap ${e.kind}">${shortD(e.date)}${e.to && e.to !== e.date ? '–' + shortD(e.to).split(' ')[0] : ''}</span><span class="flex-1">${esc(e.title)}</span></div>`).join('') : '<p class="text-sm text-slate-500">Nothing on the calendar.</p>'}</div>
          <div class="card"><div class="flex justify-between items-center mb-2"><h3 class="h3">Recent notices</h3>${ro ? '' : '<button onclick="go(\'notices\')" class="link">All notices</button>'}</div>
        ${S.notices.slice(0, 3).map(n => `<div class="att-row block"><p class="font-semibold">${esc(n.title)}</p><p class="text-xs text-slate-500 mt-0.5">Sent to ${esc(n.to || 'everyone')} · ${fmtDate(n.date)}</p></div>`).join('') || '<p class="text-sm text-slate-500">No notices yet.</p>'}</div>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 items-start">
          <div class="card"><h3 class="h3 mb-2">Arrangement today</h3>
        ${arr.length ? arr.map(s => `<div class="att-row"><span><b>${BELLS[s.bell].n}</b> · ${esc(s.cls)} ${esc(s.subj)}<br><span class="text-xs text-slate-500">for ${esc(T(s.absent).name)}</span></span><span class="pill ${s.sub ? 'bg-brand-soft text-brand' : 'bg-rose-100 text-rose-700'}">${s.sub ? esc(T(s.sub).name) : 'Not covered'}</span></div>`).join('') : '<p class="text-sm text-slate-500">No arrangement needed.</p>'}</div>
          <div class="card space-y-3"><h3 class="h3">Daily summary</h3>
        <pre id="dash-sum" class="whitespace-pre-wrap text-xs bg-slate-50 border border-slate-200 rounded-2xl p-3 font-sans">${esc(sum)}</pre>
        <div class="flex flex-wrap gap-2">${!ro ? `<a target="_blank" onclick="log('Summary','Daily summary sent to Chairman for ${date}')" href="${waLink(S.settings.chairmanPhone, sum)}" class="btn btn-sm btn-wa"><i class="fa-brands fa-whatsapp"></i> Send to Chairman</a>` : ''}
          <button onclick="navigator.clipboard&&navigator.clipboard.writeText(document.getElementById('dash-sum').innerText).then(()=>toast('Copied'))" class="btn btn-sm btn-l"><i class="fa-regular fa-copy"></i> Copy</button></div>
        ${!ro && !S.settings.chairmanPhone ? '<p class="text-[11px] text-slate-500">Add the Chairman\'s number in Settings to send directly.</p>' : ''}</div>
        </div>
      </div>
      <div class="space-y-4">
        <div class="today-card"><div class="ring" style="--p:${pct || 0}"><b>${pct == null ? '—' : pct + '%'}</b></div>
          <div><p class="dash-eyebrow">School today</p><p class="text-lg font-bold mt-1">${as.total ? `${as.present} of ${as.total} present` : 'Attendance not marked yet'}</p>
            <p class="text-xs text-white/60 mt-1">${as.total ? `${as.total - as.present} absent` : ''}${pending.length ? ` · ${pending.length} class${pending.length > 1 ? 'es' : ''} still to mark` : ''}</p></div></div>
        ${ro ? '' : `<div class="grid grid-cols-2 gap-3"><button onclick="go('admin')" class="kpi kpi-peach"><span class="kpi-val text-[22px]">${feeM == null ? '…' : rs(feeM)}</span><span class="kpi-lbl">Fee this month</span></button>
          <button onclick="go('fdues')" class="kpi ${feeDue ? 'kpi-warn' : 'kpi-lime'}"><span class="kpi-val text-[22px]">${feeDue == null ? '…' : rs(feeDue)}</span><span class="kpi-lbl">Fee still due</span></button></div>`}
        <div class="card"><h3 class="h3 mb-1">Needs your attention</h3>
          ${al.length ? al.slice(0, 6).map(a => `<div class="att-row"><span class="flex items-start min-w-0"><span class="dot-${a.lv} mt-1.5"></span><span class="clamp2" title="${esc(a.x)}">${esc(a.x)}</span></span>
            ${a.go && !ro ? `<button onclick="go('${a.go}',{date:'${date}'})" class="link">Open</button>` : ''}</div>`).join('') + (al.length > 6 ? `<p class="text-xs text-slate-500 pt-2">+ ${al.length - 6} more</p>` : '')
            : '<p class="text-sm text-emerald-700 font-semibold py-2"><i class="fa-solid fa-circle-check"></i> All clear. Nothing needs attention.</p>'}</div>
        <div class="card"><div class="flex justify-between items-center mb-2"><h3 class="h3">Recent activity</h3><button onclick="go('log')" class="link">Full log</button></div>
        ${S.log.slice(0, 6).map(l => `<div class="att-row block text-sm"><p><b>${esc(l.u)}</b> — ${esc(l.x)}</p><p class="text-xs text-slate-400">${fmtTime(l.t)}</p></div>`).join('') || '<p class="text-sm text-slate-500">No activity yet.</p>'}</div>
      </div>
    </div>
    ${wordCard(date, true)}`;
}

// ---------------------------------------------------------------- STAFF ATTENDANCE + ARRANGEMENT + LEAVE APPROVAL
function vStaff() {
  const date = P.date || todayISO(); const ab = staffAbsent(date), from = (S.staffAtt[date] && S.staffAtt[date].from) || {};
  const leaveFor = t => S.leaves.find(l => l.status === 'Approved' && l.tid === t && l.from <= date && l.to >= date);
  const fromSel = k => `<select onclick="event.stopPropagation()" onchange="staffFrom('${date}','${k}',this.value)" class="inp py-1 px-1 text-[11px] w-auto mt-1">
      <option value="0">Full day</option>${BELLS.map((b, i) => i ? `<option value="${i}" ${+from[k] === i ? 'selected' : ''}>Leaves before ${b.n} bell</option>` : '').join('')}</select>`;
  const cards = Object.entries(S.teachers).map(([k, t]) => { const isAb = ab.includes(k), lv = leaveFor(k);
    return `<div onclick="${lv ? "toast('On approved leave — cancel the leave to mark present','a')" : `toggleStaff('${date}','${k}')`}" class="cursor-pointer text-left p-3 rounded-xl border flex items-center justify-between gap-2 ${isAb ? 'bg-rose-50 border-rose-300' : 'bg-emerald-50/60 border-emerald-200'}">
      <span><span class="block font-bold text-sm">${esc(t.name)}</span><span class="text-[11px] text-slate-500">${esc(t.role)}${lv ? ' • On leave' : ''}</span>${isAb && !lv ? '<br>' + fromSel(k) : ''}</span>
      <span class="pill ${isAb ? 'bg-rose-600 text-white' : 'bg-emerald-600 text-white'}">${isAb ? (+from[k] && !lv ? 'HALF' : 'ABSENT') : 'P'}</span></div>`; }).join('');
  const arr = arrangement(date), month = monthLoad(date, true);
  const pend = S.leaves.filter(l => l.status === 'Pending');
  const optList = s => `<select onchange="setSub('${date}','${s.absent}',${s.bell},this.value)" class="inp py-1 px-2 text-xs w-auto">
      <option value="">Auto (best free teacher)</option>${s.options.map(x => `<option value="${x}" ${s.manual && s.sub === x ? 'selected' : ''}>${esc(T(x).name)} • ${month[x] || 0} this month</option>`).join('')}
      <option value="-" ${s.manual && !s.sub ? 'selected' : ''}>Nobody — Principal supervises</option></select>`;
  const freeRows = dayIdx(date) < 0 || holidayOn(date) ? '' : BELLS.map((b, i) => { const f = freeAt(date, i);
    return `<tr><td class="font-bold whitespace-nowrap">${b.n} <span class="text-xs text-slate-500">${t12(b.s)}</span></td><td>${f.length ? f.map(x => `<span class="pill bg-emerald-100 text-emerald-800 mr-1 mb-1">${esc(T(x).name)}</span>`).join('') : '<span class="text-rose-700 text-xs font-bold">Nobody free</span>'}</td></tr>`; }).join('');
  const duty = Object.entries(month).sort((a, b) => b[1] - a[1]);
  return head('Staff Attendance & Arrangement', 'Everyone is Present by default. Tap only the absent teachers. For a half day, pick the bell they leave before.',
      dateInput('s-date', date, "go('staff',{date:this.value})") + `<button onclick="printSlips('${date}')" class="btn btn-l"><i class="fa-solid fa-print"></i> Print Slips</button>`
      + (arr.some(s => s.sub) ? `<a target="_blank" href="${waLink('', slipsText(date, arr))}" class="btn btn-wa"><i class="fa-brands fa-whatsapp"></i> Send all to staff group</a>` : '')) + `
    ${pend.length ? `<div class="card space-y-2"><h3 class="font-bold">Leave Requests</h3>${pend.map(l => `<div class="p-3 rounded-xl bg-amber-50 border border-amber-200 flex flex-col sm:flex-row sm:items-center justify-between gap-2">
      <span class="text-sm"><b>${esc(T(l.tid).name)}</b> • ${fmtDate(l.from)}${l.to !== l.from ? ' to ' + fmtDate(l.to) : ''} • ${esc(l.reason)}</span>
      <span class="flex gap-2"><button onclick="decideLeave('${l.id}','Approved')" class="btn btn-sm btn-g">Approve</button><button onclick="decideLeave('${l.id}','Rejected')" class="btn btn-sm btn-r">Reject</button></span></div>`).join('')}</div>` : ''}
    <div class="card"><div class="flex justify-between mb-3"><h3 class="font-bold">${fmtDate(date)}</h3><span class="text-sm"><span class="pill bg-emerald-100 text-emerald-800">${Object.keys(S.teachers).length - ab.length} present</span> <span class="pill bg-rose-100 text-rose-800">${ab.length} absent</span></span></div>
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-2">${cards}</div></div>
    <div class="card"><h3 class="font-bold mb-1">Arrangement Slips</h3><p class="text-xs text-slate-500 mb-2">Auto picks the same-subject teacher first and shares duty fairly across the month. Change any slip from its list.</p>
      ${dayIdx(date) < 0 ? '<p class="text-sm text-slate-500">Sunday.</p>' : arr.length ? `<div class="space-y-2">${arr.map((s, n) => `<div class="p-3 rounded-xl border ${s.sub ? 'bg-slate-50 border-slate-200' : 'bg-rose-50 border-rose-300'} flex flex-col gap-2">
        <span class="text-sm"><span class="pill bg-rose-600 text-white mr-1">#${n + 1}</span><b>${BELLS[s.bell].n} bell (${t12(BELLS[s.bell].s)})</b> • Class ${s.cls} • ${esc(s.subj)} • for ${esc(T(s.absent).name)}<br>
        <span class="text-slate-600">${s.sub ? 'Substitute: <b>' + esc(T(s.sub).name) + '</b>' + (s.manual ? ' <span class="pill bg-indigo-100 text-indigo-800">chosen</span>' : '') : '<b class="text-rose-700">No free teacher — Principal to supervise</b>'}</span></span>
        <span class="flex flex-wrap gap-2 items-center">${optList(s)}
        ${s.sub && S.teachers[s.sub].phone ? `<a target="_blank" href="${waLink(S.teachers[s.sub].phone, `Arrangement: ${BELLS[s.bell].n} bell (${t12(BELLS[s.bell].s)}), Class ${s.cls} ${s.subj}, in place of ${T(s.absent).name}. — Principal`)}" class="btn btn-sm btn-wa"><i class="fa-brands fa-whatsapp"></i> Inform</a>` : ''}</span></div>`).join('')}</div>`
      : '<p class="text-sm text-slate-500">No arrangement needed.</p>'}</div>
    ${freeRows ? `<div class="card"><h3 class="font-bold mb-1">Who is free — ${DAYS[dayIdx(date)]}</h3><p class="text-xs text-slate-500 mb-2">Absent teachers and teachers already on arrangement are left out.</p>
      <div class="overflow-x-auto"><table class="tbl"><tr><th>Bell</th><th>Free teachers</th></tr>${freeRows}</table></div></div>` : ''}
    <div class="card"><h3 class="font-bold mb-2">Arrangement duty this month</h3>${duty.length ? `<div class="flex flex-wrap gap-2 text-xs">${duty.map(([k, n]) => `<span class="pill bg-slate-100 text-slate-700">${esc(T(k).name)}: ${n}</span>`).join('')}</div>` : '<p class="text-sm text-slate-500">None yet this month.</p>'}</div>`;
}
function slipsText(date, arr) {
  return `${S.settings.school} — Arrangement ${fmtDate(date)}\n` + arr.map(s => `${BELLS[s.bell].n} bell ${t12(BELLS[s.bell].s)} • Class ${s.cls} ${s.subj} → ${s.sub ? T(s.sub).name : 'Principal'} (for ${T(s.absent).name})`).join('\n') + '\n— Principal';
}
function toggleStaff(date, t) {
  S.staffAtt[date] = S.staffAtt[date] || { absent: [] };
  const a = S.staffAtt[date].absent, i = a.indexOf(t);
  if (i >= 0) { a.splice(i, 1); if (S.staffAtt[date].from) delete S.staffAtt[date].from[t]; } else a.push(t);
  log('Staff Attendance', `${T(t).name} marked ${i >= 0 ? 'PRESENT' : 'ABSENT'} for ${date}`);
  const slips = arrangement(date).filter(s => s.absent === t);
  if (i < 0 && slips.length) log('Arrangement', `Auto-arranged ${slips.length} bell(s) for ${T(t).name}: ` + slips.map(s => `${BELLS[s.bell].n} ${s.cls}→${s.sub ? T(s.sub).name : 'uncovered'}`).join('; '));
  render();
}
function staffFrom(date, t, v) {
  const a = S.staffAtt[date]; if (!a) return; a.from = a.from || {};
  if (+v) a.from[t] = +v; else delete a.from[t];
  log('Staff Attendance', `${T(t).name}: ${+v ? 'half day — leaves before ' + BELLS[+v].n + ' bell' : 'full day absent'} on ${date}`); render();
}
function setSub(date, absent, bell, v) {
  S.staffAtt[date] = S.staffAtt[date] || { absent: [] }; const a = S.staffAtt[date]; a.subs = a.subs || {};
  const k = absent + '|' + bell; if (v) a.subs[k] = v; else delete a.subs[k];
  log('Arrangement', `${BELLS[bell].n} bell for ${T(absent).name} on ${date}: ${v === '-' ? 'Principal supervises' : v ? T(v).name + ' (chosen)' : 'back to auto'}`); render();
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
// Share of class-bells that keep the same subject Monday to Saturday (the rest are where it could not fit).
function ttFixedInfo() {
  if (!S.grid) return { pct: 0, odd: {} };
  let same = 0, tot = 0; const odd = {};
  CLASS_NAMES.forEach(c => BELLS.forEach((_, b) => { const cnt = {};
    DAYS.forEach((_, d) => { const a = cell(c, d, b); const k = a ? a.s + '|' + a.t : '-'; cnt[k] = (cnt[k] || 0) + 1; });
    const top = Object.entries(cnt).sort((x, y) => y[1] - x[1])[0]; same += top[1]; tot += DAYS.length;
    DAYS.forEach((_, d) => { const a = cell(c, d, b); if ((a ? a.s + '|' + a.t : '-') !== top[0]) odd[c + '|' + d + '|' + b] = 1; }); }));
  return { pct: Math.round(same / tot * 100), odd };
}
function vTimetable() {
  const mode = P.mode || 'day', d = P.d != null ? P.d : Math.max(0, dayIdx(todayISO())), c = P.c || '10th';
  const tabs = [['day','All Classes (Day)'],['class','One Class (Week)'],['teacher','Teacher-Wise'],['free','Free Bells'],['setup','Setup Periods']]
    .map(([k, l]) => `<button onclick="go('timetable',{mode:'${k}',d:${d},c:'${c}'})" class="btn btn-sm ${mode === k ? 'btn-p' : 'btn-l'}">${l}</button>`).join('');
  const dayBtns = DAYS.map((x, i) => `<button onclick="go('timetable',{mode:'${mode}',d:${i},c:'${c}'})" class="btn btn-sm ${i === d ? 'bg-slate-900 text-white' : 'btn-l'}">${x.slice(0, 3)}</button>`).join('');
  const { errors, warns } = ttCheck(S.alloc), fx = ttFixedInfo();
  const oddCls = (cl, di, i) => fx.odd[cl + '|' + di + '|' + i] ? ' class="bg-amber-50"' : '';
  let body = '';
  if (mode === 'setup') body = ttSetup();
  else if (!S.grid) body = '<p class="text-sm">No timetable yet. Press <b>Generate New</b>.</p>';
  else if (mode === 'class') {
    body = `<div class="mb-2 flex items-center gap-2"><span class="lbl mb-0">Class</span><select class="inp w-auto" onchange="go('timetable',{mode:'class',d:${d},c:this.value})">${CLASS_NAMES.map(x => `<option ${x === c ? 'selected' : ''}>${x}</option>`).join('')}</select></div>
      <h3 class="font-bold mb-2">Class ${c} — Class Teacher: ${esc(T(CLASSES.find(k => k.c === c).ct).name)}</h3>
      <div class="overflow-x-auto"><table class="tbl"><tr><th>Day</th>${bellHead()}</tr>${DAYS.map((day, di) => `<tr><td class="font-bold">${day}</td>${BELLS.map((b, i) => { const a = cell(c, di, i); return (a ? `<td${oddCls(c, di, i)}><b>${esc(a.s)}</b><br><span class="text-xs text-slate-500">${esc(T(a.t).name)}</span></td>` : '<td class="text-slate-400 italic">Self Study</td>') + (i === RECESS_AFTER ? RECESS_TD : ''); }).join('')}</tr>`).join('')}</table></div>`;
  } else if (mode === 'teacher') {
    body = `<div class="flex flex-wrap gap-1 mb-2">${dayBtns}</div><h3 class="font-bold mb-2">${DAYS[d]} — Teacher-wise <span class="pill bg-emerald-100 text-emerald-800">Green = free bell</span></h3>
      <div class="overflow-x-auto"><table class="tbl"><tr><th>Teacher</th><th>Per week</th>${bellHead()}</tr>${Object.entries(S.teachers).map(([k, t]) => `<tr><td class="font-bold whitespace-nowrap">${esc(t.name)}</td><td class="text-center">${loadOf(k)}</td>${BELLS.map((b, i) => { const a = teacherAt(k, d, i);
        return (a ? `<td><b>${a.c}</b><br><span class="text-xs">${esc(a.s)}</span></td>` : (t.bells && !t.bells.includes(i)) ? '<td class="text-slate-300 text-xs">Not in school</td>' : '<td class="bg-emerald-100 text-emerald-800 font-bold text-center">FREE</td>') + (i === RECESS_AFTER ? RECESS_TD : ''); }).join('')}</tr>`).join('')}</table></div>`;
  } else if (mode === 'free') {
    body = `<h3 class="font-bold mb-2">Free teachers in each bell (weekly timetable)</h3><p class="text-xs text-slate-500 mb-2">For a real date with absences, use Staff &amp; Arrangement — it also leaves out absent and arranged teachers.</p>
      <div class="overflow-x-auto"><table class="tbl"><tr><th>Bell</th>${DAYS.map(x => `<th>${x.slice(0, 3)}</th>`).join('')}</tr>${BELLS.map((b, i) => `<tr><td class="font-bold whitespace-nowrap">${b.n}<br><span class="text-xs text-slate-500">${t12(b.s)}</span></td>${DAYS.map((_, di) => {
        const f = Object.keys(S.teachers).filter(k => !teacherAt(k, di, i) && (!S.teachers[k].bells || S.teachers[k].bells.includes(i)));
        return `<td class="text-xs">${f.map(k => esc(T(k).name)).join(', ') || '<span class="text-rose-700 font-bold">None</span>'}</td>`; }).join('')}</tr>`).join('')}</table></div>`;
  } else {
    body = `<div class="flex flex-wrap gap-1 mb-2">${dayBtns}</div><h3 class="font-bold mb-2">${DAYS[d]} — All Classes</h3>
      <div class="overflow-x-auto"><table class="tbl"><tr><th>Class</th>${bellHead()}</tr>${CLASS_NAMES.map(cl => `<tr><td class="font-bold">${cl}</td>${BELLS.map((b, i) => { const a = cell(cl, d, i); return (a ? `<td${oddCls(cl, d, i)}><b>${esc(a.s)}</b><br><span class="text-xs text-slate-500">${esc(T(a.t).name)}</span></td>` : '<td class="text-slate-400 italic">Self Study</td>') + (i === RECESS_AFTER ? RECESS_TD : ''); }).join('')}</tr>`).join('')}</table></div>`;
  }
  return head('Weekly Timetable', 'Assembly 08:40–09:00 • 7 bells • Recess 10:45–11:05 • Mon–Sat • Brijesh Sir: 1st & 2nd bell only',
      `<button onclick="genTT()" class="btn btn-g"><i class="fa-solid fa-wand-magic-sparkles"></i> Generate New</button><button onclick="printTT()" class="btn btn-l"><i class="fa-solid fa-print"></i> Print</button>`) + `
    <div class="flex flex-wrap gap-2">${tabs}</div>
    ${S.grid ? `<div class="card text-sm flex flex-wrap items-center gap-2"><span class="pill bg-emerald-100 text-emerald-800">${fx.pct}% fixed</span> of bells keep the same subject Monday to Saturday. <span class="pill bg-amber-50 border border-amber-200 text-amber-800">Shaded</span> = changed only where it could not fit.</div>` : ''}
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
    ${absent ? `<div class="card bg-rose-50 border-rose-200 text-rose-800 font-semibold">${absentFrom(date, tid) ? 'Half day — you leave before the ' + BELLS[absentFrom(date, tid)].n + ' bell.' : 'You are marked absent / on leave for this day.'}</div>` : ''}
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

// ---------------------------------------------------------------- SYLLABUS (photo of the contents page → chapters → month plan → tick when done)
// A chapter: { n, done, plan:'YYYY-MM' (month to finish it), topics:[...] (from the contents page, optional) }.
function sylEndMonth(key) {
  const y = S.syl[key] || {}, dl = sylDeadline(key.split('|')[0]) || y.targetDate;
  if (dl) return dl.slice(0, 7);
  const st = S.settings.sessionStart || todayISO(); return (+st.slice(0, 4) + 1) + '-02';   // annual exams in March
}
function ymAdd(ym, n) { const [y, m] = ym.split('-').map(Number), d = new Date(y, m - 1 + n, 1); return d.getFullYear() + '-' + pad(d.getMonth() + 1); }
function ymName(ym) { const [y, m] = ym.split('-').map(Number); return new Date(y, m - 1, 1).toLocaleString('en-IN', { month: 'short', year: '2-digit' }); }
function sylMonths(key) { const out = []; let m = (S.settings.sessionStart || todayISO()).slice(0, 7); const end = sylEndMonth(key); while (m <= end && out.length < 14) { out.push(m); m = ymAdd(m, 1); } return out; }
function sylStatus(key, date) {
  const y = S.syl[key]; if (!y || !y.chapters.length) return null;
  const done = y.chapters.filter(ch => ch.done).length, total = y.chapters.length, ym = date.slice(0, 7);
  let expected = null, lag = false;
  if (y.chapters.some(ch => ch.plan)) {                      // month plan: chapters planned for months already over must be done
    expected = y.chapters.filter(ch => ch.plan && ch.plan < ym).length;
    lag = done < expected;
  } else {
    const dl = sylDeadline(key.split('|')[0]);
    const tCount = dl ? total : y.targetCount, tDate = dl || y.targetDate;
    if (tCount && tDate) {
      const st = new Date(S.settings.sessionStart + 'T00:00:00'), en = new Date(tDate + 'T00:00:00'), now = new Date(date + 'T00:00:00');
      const f = Math.max(0, Math.min(1, (now - st) / Math.max(1, en - st)));
      expected = Math.round(tCount * f * 10) / 10; lag = expected - done >= 1;
    }
  }
  const thisMonth = y.chapters.filter(ch => ch.plan === ym && !ch.done).map(ch => ch.n);
  return { done, total, expected, lag, pct: Math.round(done / total * 100), thisMonth };
}
function nextChapter(c, s) {
  const y = S.syl[sylKey(c, s)]; if (!y) return null;
  const ym = todayISO().slice(0, 7), open = y.chapters.filter(x => !x.done);
  const ch = open.find(x => x.plan && x.plan <= ym) || open[0]; return ch ? ch.n : null;
}
function vSyllabus() {
  const date = todayISO();
  if (U.role !== 'teacher' && !P.key) {
    const rows = S.alloc.filter(a => a.n > 0).map(a => { const k = sylKey(a.c, a.s), st = sylStatus(k, date);
      return `<tr><td class="font-bold">${a.c}</td><td>${esc(a.s)}</td><td>${esc(T(a.t).name)}</td>
        <td class="w-48">${st ? bar(st.pct, st.lag ? 'bg-rose-500' : 'bg-emerald-500') + `<span class="text-xs">${st.done}/${st.total} chapters</span>` : '<span class="text-xs text-slate-400">Not set up</span>'}</td>
        <td>${st && st.expected != null ? `<span class="pill ${st.lag ? 'bg-rose-100 text-rose-800' : 'bg-emerald-100 text-emerald-800'}">${st.lag ? 'Behind' : 'On track'} (should be ${st.expected})</span>` : '—'}${st && st.thisMonth.length ? `<br><span class="text-xs text-slate-500">This month: ${st.thisMonth.map(esc).join(', ')}</span>` : ''}</td>
        <td class="noprint">${U.role === 'principal' ? `<button onclick="go('syllabus',{key:${jsq(k)}})" class="btn btn-sm btn-l">Open</button>` : ''}</td></tr>`; }).join('');
    const pending = {};
    S.alloc.filter(a => a.n > 0 && !/Games|Library|Moral|Rhymes|Play|Craft|Activity|PT\b/.test(a.s)).forEach(a => { const y = S.syl[sylKey(a.c, a.s)];
      if (!y || !y.chapters.length || !y.chapters.some(c => c.plan)) (pending[a.t] = pending[a.t] || []).push(`${a.c} ${a.s}${y && y.chapters.length ? ' (month plan)' : ''}`); });
    const msg = list => `Namaste. Please update your syllabus in the School ERP (Syllabus → Add chapters from a photo of the book's contents page, then "Spread month-wise"): ${list.join(', ')}. Then make this week's lesson plan from it (Lesson Plan → Fill from syllabus). — Principal`;
    return head('Syllabus Progress', 'Teachers add chapters from a photo of the contents page, spread them month-wise, then tick each chapter when finished.') +
      (Object.keys(pending).length && U.role === 'principal' ? `<div class="card space-y-2"><h3 class="font-bold">Still to update (${Object.keys(pending).length} teachers)</h3>${Object.entries(pending).map(([t, list]) => `<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 p-2 rounded-lg bg-amber-50 border border-amber-200">
        <span class="text-sm"><b>${esc(T(t).name)}</b> — ${list.map(esc).join(', ')}</span>${S.teachers[t] && S.teachers[t].phone ? `<a target="_blank" href="${waLink(S.teachers[t].phone, msg(list))}" class="btn btn-sm btn-wa"><i class="fa-brands fa-whatsapp"></i> Remind</a>` : '<span class="text-xs text-slate-500">Add WhatsApp no. in Settings</span>'}</div>`).join('')}</div>` : '') +
      `<div class="card overflow-x-auto"><table class="tbl"><tr><th>Class</th><th>Subject</th><th>Teacher</th><th>Progress</th><th>Status</th><th class="noprint"></th></tr>${rows}</table></div>`;
  }
  const mine = U.role === 'teacher' ? myAllocs(U.tid) : S.alloc.filter(a => a.n > 0);
  if (!mine.length) return head('Syllabus') + '<div class="card text-sm">No subjects assigned.</div>';
  const key = P.key && mine.some(a => sylKey(a.c, a.s) === P.key) ? P.key : sylKey(mine[0].c, mine[0].s);
  const y = S.syl[key] || { chapters: [], targetCount: '', targetDate: '' }, st = sylStatus(key, date), months = sylMonths(key), ym = date.slice(0, 7);
  const [c, s] = key.split('|');
  const monthSel = (ch, i) => `<select onchange="sylPlan(${jsq(key)},${i},this.value)" class="inp py-1 px-1 text-xs w-24 ${ch.plan && ch.plan < ym && !ch.done ? 'border-rose-300 text-rose-700' : ''}"><option value="">Month…</option>${months.map(m => `<option value="${m}" ${ch.plan === m ? 'selected' : ''}>${ymName(m)}</option>`).join('')}</select>`;
  return head('Syllabus', 'Step 1: photo of the book\'s contents page → chapters. Step 2: spread them month-wise. Step 3: tick each chapter when finished.', U.role === 'principal' ? `<button onclick="go('syllabus')" class="btn btn-l">← All subjects</button>` : '') + `
    <div class="card"><label class="lbl">Class & subject</label><select class="inp" onchange="SCAN=null;go('syllabus',{key:this.value})">${mine.map(a => { const k = sylKey(a.c, a.s); return `<option value="${esc(k)}" ${k === key ? 'selected' : ''}>Class ${a.c} — ${esc(a.s)}</option>`; }).join('')}</select></div>
    ${scanCard('chapters', c, s, y.chapters.length ? 'Add or replace chapters from a photo' : 'Step 1 — add chapters from a photo of the contents page')}
    ${st ? `<div class="card space-y-2">${bar(st.pct, st.lag ? 'bg-rose-500' : 'bg-emerald-500')}<p class="text-sm"><b>${st.done}/${st.total}</b> chapters done${st.expected != null ? ` • By now about <b>${st.expected}</b> should be finished — <b class="${st.lag ? 'text-rose-700' : 'text-emerald-700'}">${st.lag ? 'Behind: plan extra periods' : 'On track'}</b>` : ''}</p>
      ${st.thisMonth.length ? `<p class="text-sm">This month (${ymName(ym)}): ${st.thisMonth.map(n => `<span class="pill bg-marigold/20 text-ink mr-1">${esc(n)}</span>`).join('')}</p>` : ''}</div>` : ''}
    <div class="card space-y-2"><div class="flex flex-wrap justify-between items-center gap-2"><h3 class="font-bold">Chapters</h3>
        ${y.chapters.length ? `<button onclick="sylSpread(${jsq(key)})" class="btn btn-sm btn-p"><i class="fa-solid fa-calendar-days"></i> ${y.chapters.some(ch => ch.plan) ? 'Spread again' : 'Step 2 — spread month-wise'}</button>` : ''}</div>
      ${y.chapters.length ? `<div class="flex flex-wrap items-center gap-2 text-xs text-slate-500">${sylDeadline(c) ? `Whole syllabus by ${fmtDate(sylDeadline(c))} (school plan for Class ${esc(c)}).` : `Finish all by <input type="date" class="inp py-1 px-2 text-xs w-auto" value="${y.targetDate || ''}" onchange="sylFinish(${jsq(key)},this.value)"> ${y.targetDate ? '' : '(blank = ' + ymName(sylEndMonth(key)) + ')'}`} Change any chapter's month from its list.</div>` + y.chapters.map((ch, i) => `<div class="flex items-center gap-2 p-2 rounded-lg hover:bg-slate-50"><input type="checkbox" class="w-5 h-5 shrink-0" ${ch.done ? 'checked' : ''} onchange="sylTick(${jsq(key)},${i},this.checked)">
        <span class="flex-1 min-w-0 ${ch.done ? 'line-through text-slate-400' : ''}">${i + 1}. ${esc(ch.n)}${(ch.topics || []).length ? `<span class="block text-[11px] text-slate-500 truncate">${ch.topics.map(esc).join(' · ')}</span>` : ''}</span>
        ${ch.done ? `<span class="text-xs text-slate-400">${fmtDate(ch.done)}</span>` : monthSel(ch, i)}
        <button onclick="sylRename(${jsq(key)},${i})" class="text-slate-300 hover:text-ink" title="Edit"><i class="fa-solid fa-pen"></i></button>
        <button onclick="sylMove(${jsq(key)},${i},-1)" class="text-slate-300 hover:text-ink" title="Move up"><i class="fa-solid fa-arrow-up"></i></button>
        <button onclick="sylDel(${jsq(key)},${i})" class="text-slate-300 hover:text-rose-500" title="Remove"><i class="fa-solid fa-trash"></i></button></div>`).join('') : '<p class="text-sm text-slate-500">No chapters yet. Take a photo of the contents page above, or type them below.</p>'}
    </div>
    <div class="card"><label class="lbl">Or type chapters (one per line)</label><textarea id="sy-add" rows="4" class="inp" placeholder="Chapter 1 name&#10;Chapter 2 name"></textarea><button onclick="sylAdd(${jsq(key)})" class="btn btn-l btn-sm mt-2"><i class="fa-solid fa-plus"></i> Add</button></div>`;
}
function sylFinish(key, v) { const y = sylEnsure(key); y.targetDate = v || ''; y.targetCount = v ? y.chapters.length : ''; log('Syllabus', `${key.replace('|', ' ')}: finish by ${v || 'end of session'}`); render(); }
function sylEnsure(key) { return S.syl[key] = S.syl[key] || { chapters: [], targetCount: '', targetDate: '' }; }
function sylTick(key, i, on) { const y = sylEnsure(key); y.chapters[i].done = on ? todayISO() : null; log('Syllabus', `${key.replace('|', ' ')}: "${y.chapters[i].n}" ${on ? 'completed' : 'un-ticked'}`); render(); }
function sylDel(key, i) { const y = sylEnsure(key); const n = y.chapters[i].n; if (!confirm('Remove chapter "' + n + '"?')) return; y.chapters.splice(i, 1); log('Syllabus', `${key.replace('|', ' ')}: removed chapter "${n}"`); render(); }
function sylRename(key, i) { const y = sylEnsure(key), ch = y.chapters[i]; const v = prompt('Chapter name', ch.n); if (v === null || !v.trim()) return; const old = ch.n; ch.n = v.trim().slice(0, 120); log('Syllabus', `${key.replace('|', ' ')}: renamed "${old}" → "${ch.n}"`); render(); }
function sylMove(key, i, d) { const y = sylEnsure(key), j = i + d; if (j < 0 || j >= y.chapters.length) return; [y.chapters[i], y.chapters[j]] = [y.chapters[j], y.chapters[i]]; save(); render(); }
function sylPlan(key, i, v) { const y = sylEnsure(key); y.chapters[i].plan = v || null; log('Syllabus', `${key.replace('|', ' ')}: "${y.chapters[i].n}" planned for ${v ? ymName(v) : 'no month'}`); render(); }
function sylAdd(key) { const lines = document.getElementById('sy-add').value.split('\n').map(s => s.trim()).filter(Boolean); if (!lines.length) return;
  const y = sylEnsure(key); lines.forEach(n => y.chapters.push({ n: n.slice(0, 120), done: null })); log('Syllabus', `${key.replace('|', ' ')}: added ${lines.length} chapter(s)`); render(); }
// Spread the chapters not yet done over the months left (bigger chapters — more topics — get more room).
function sylSpread(key) {
  const y = sylEnsure(key), ym = todayISO().slice(0, 7), months = sylMonths(key).filter(m => m >= ym);
  const open = y.chapters.filter(ch => !ch.done);
  if (!open.length) return toast('All chapters are done', 'b');
  if (!months.length) return toast('The finish month is already over — set a later target date', 'a');
  if (y.chapters.some(ch => ch.plan) && !confirm('Plan the remaining chapters again from this month?')) return;
  const w = open.map(ch => Math.max(1, (ch.topics || []).length || 1)), tot = w.reduce((a, b) => a + b, 0);
  let acc = 0; open.forEach((ch, i) => { const mid = acc + w[i] / 2; acc += w[i]; ch.plan = months[Math.min(months.length - 1, Math.floor(mid / tot * months.length))]; });
  log('Syllabus', `${key.replace('|', ' ')}: ${open.length} chapter(s) spread over ${ymName(months[0])}–${ymName(months[months.length - 1])}`); toast('Month plan ready — change any month if needed'); render();
}

// ---------------------------------------------------------------- PHOTO → TEXT (contents page / lesson plan)
// The server's AI reads the photo when it is switched on (scan_api.php); otherwise the phone reads it (Tesseract.js, English + Hindi).
// Nothing is saved until the teacher has checked and pressed Save.
let SCAN = null, SCAN_AI = null;
function scanCard(mode, c, s, title) {
  const k = mode + '|' + c + '|' + s, cur = SCAN && SCAN.k === k ? SCAN : null, id = 'sc-' + mode;
  const pick = `<input id="${id}" type="file" accept="image/*" capture="environment" multiple class="hidden" onchange="scanFiles('${mode}',${jsq(c)},${jsq(s)},this.files)">`;
  if (!cur || (!cur.busy && !cur.chapters && !cur.topics)) return `<div class="card flex flex-col sm:flex-row sm:items-center justify-between gap-3">
      <div><h3 class="font-bold">${title}</h3><p class="text-xs text-slate-500">${mode === 'chapters' ? 'Hold the phone straight over the book\'s contents page. Up to 4 photos (if it runs over pages).' : 'A photo of your written plan or of the book page you will teach. Up to 4 photos.'} You check everything before it is saved.</p>
        ${cur && cur.msg ? `<p class="text-xs text-rose-700 mt-1">${esc(cur.msg)}</p>` : ''}</div>
      <label for="${id}" class="btn btn-p cursor-pointer shrink-0"><i class="fa-solid fa-camera"></i> Take photo</label>${pick}</div>`;
  if (cur.busy) return `<div class="card"><p class="font-semibold"><i class="fa-solid fa-spinner fa-spin"></i> ${esc(cur.busy)}</p><p class="text-xs text-slate-500 mt-1">Please keep this page open.</p></div>`;
  const warn = cur.uncertain ? '<p class="text-sm text-amber-700">Some parts were hard to read — please check carefully.</p>' : '';
  if (mode === 'chapters') {
    const y = S.syl[sylKey(c, s)], has = y && y.chapters.length;
    return `<div class="card space-y-2 ring-2 ring-marigold"><div class="flex justify-between gap-2"><h3 class="font-bold">Check the chapters (${cur.chapters.length})</h3><span class="pill bg-slate-100 text-slate-600">${cur.via === 'ai' ? 'Read by AI' : 'Read by phone'}</span></div>${warn}
      <p class="text-xs text-slate-500">Correct any spelling, remove wrong lines, then save. Topics are optional (separate with ;).</p>
      ${cur.chapters.map((ch, i) => `<div class="flex gap-2 items-start"><span class="text-xs text-slate-400 pt-2 w-5">${i + 1}</span><div class="flex-1 space-y-1"><input class="inp py-1" value="${esc(ch.name)}" oninput="SCAN.chapters[${i}].name=this.value"><input class="inp py-1 text-xs" placeholder="Topics (optional)" value="${esc((ch.topics || []).join('; '))}" oninput="SCAN.chapters[${i}].topics=this.value.split(';').map(x=>x.trim()).filter(Boolean)"></div>
        <button onclick="SCAN.chapters.splice(${i},1);render()" class="text-slate-400 hover:text-rose-500 pt-2"><i class="fa-solid fa-xmark"></i></button></div>`).join('')}
      <button onclick="SCAN.chapters.push({name:'',topics:[]});render()" class="text-indigo-700 text-xs font-bold"><i class="fa-solid fa-plus"></i> Add a line</button>
      ${has ? `<label class="flex items-center gap-2 text-sm"><input id="sc-replace" type="checkbox"> Replace the ${y.chapters.length} chapters already saved (ticked ones keep their tick)</label>` : ''}
      <div class="flex flex-wrap gap-2"><button onclick="scanSaveChapters(${jsq(c)},${jsq(s)})" class="btn btn-g"><i class="fa-solid fa-check"></i> Save chapters</button>
        <label for="${id}" class="btn btn-l cursor-pointer">Take again</label>${pick}<button onclick="SCAN=null;render()" class="btn btn-l">Cancel</button></div></div>`;
  }
  return `<div class="card space-y-1 ring-2 ring-marigold"><b>Read from the photo — check the plan above, then submit.</b> <span class="pill bg-slate-100 text-slate-600">${cur.via === 'ai' ? 'Read by AI' : 'Read by phone'}</span>${warn}
    <div class="flex gap-2 pt-1"><label for="${id}" class="btn btn-sm btn-l cursor-pointer">Take again</label>${pick}</div></div>`;
}
function scanShrink(file) {
  return new Promise((ok, bad) => { const img = new Image(), url = URL.createObjectURL(file);
    img.onload = () => { const m = 1600, r = Math.min(1, m / Math.max(img.width, img.height)), cv = document.createElement('canvas');
      cv.width = Math.round(img.width * r); cv.height = Math.round(img.height * r); cv.getContext('2d').drawImage(img, 0, 0, cv.width, cv.height);
      URL.revokeObjectURL(url); ok(cv.toDataURL('image/jpeg', 0.82)); };
    img.onerror = () => { URL.revokeObjectURL(url); bad(new Error('This photo could not be opened. Please take it again.')); }; img.src = url; });
}
async function sapi(a, body) {
  const opt = { method: body ? 'POST' : 'GET', headers: { 'X-School-Token': TOKEN || '' } };
  if (body) { opt.headers['Content-Type'] = 'application/json'; opt.body = JSON.stringify(body); }
  let r, j = null;
  try { r = await fetch('scan_api.php?a=' + a, opt); } catch (e) { throw new Error('No internet connection. Please try again.'); }
  try { j = await r.json(); } catch (e) {}
  if (r.status === 401) { forceLogout(); throw new Error('Please log in again.'); }
  if (!r.ok || !j || !j.ok) { const er = new Error((j && j.error) || 'Server problem. Please try again.'); er.noAi = !!(j && j.noAi); throw er; }
  return j;
}
let TESS = null;
function tessLoad() {
  if (window.Tesseract) return Promise.resolve();
  return TESS = TESS || new Promise((ok, bad) => { const sc = document.createElement('script'); sc.src = 'https://cdn.jsdelivr.net/npm/tesseract.js@5.1.1/dist/tesseract.min.js';
    sc.onload = ok; sc.onerror = () => { TESS = null; bad(new Error('The phone reader could not load. Check the internet, or type the chapters.')); }; document.head.appendChild(sc); });
}
function scanLine(s) {
  return String(s).replace(/\s+/g, ' ').trim()
    .replace(/^(?:(?:chapter|ch\.?|lesson|unit|पाठ|अध्याय|इकाई)\s*)?[(\[]?(?:\d{1,3}|[ivxlc]{1,6})[)\].:\-–—]*\s+/i, '')
    .replace(/[\s.·…_\-–—]*\d{1,4}(?:\s*[-–]\s*\d{1,4})?\s*$/, '').replace(/^[\s.\-–—:|•*]+|[\s.\-–—:|•*]+$/g, '').slice(0, 120);
}
function scanParseText(text, mode) {
  const skip = /^(contents?|index|page|pages|s\.? ?no\.?|sr\.? ?no\.?|विषय[ -]?सूची|अनुक्रमणिका|पृष्ठ|क्रम)\b/i;
  const lines = String(text).split('\n').map(scanLine).filter(l => l.length >= 3 && /[A-Za-zऀ-ॿ]{2}/.test(l) && !skip.test(l));
  const uniq = [...new Set(lines)];
  return mode === 'chapters' ? { chapters: uniq.slice(0, 60).map(name => ({ name, topics: [] })) } : { topics: uniq.slice(0, 15), goal: '', homework: '' };
}
async function scanFiles(mode, c, s, files) {
  files = [...(files || [])].slice(0, 4); if (!files.length) return;
  const k = mode + '|' + c + '|' + s;
  SCAN = { k, busy: 'Preparing the photo…' }; render();
  try {
    const images = []; for (const f of files) images.push(await scanShrink(f));
    let res = null;
    if (SCAN_AI !== false) {
      SCAN.busy = 'Reading the photo with AI… (10–30 seconds)'; render();
      try { res = await sapi('read', { mode, cls: c, subj: s, images }); }
      catch (e) { if (!e.noAi) throw e; SCAN_AI = false; }
    }
    if (!res) {
      SCAN.busy = 'Your phone is reading the photo… (the first time takes about a minute)'; render();
      await tessLoad(); let text = '';
      for (let i = 0; i < images.length; i++) { SCAN.busy = `Your phone is reading photo ${i + 1} of ${images.length}…`; render();
        const r = await window.Tesseract.recognize(images[i], 'eng+hin'); text += '\n' + ((r && r.data && r.data.text) || ''); }
      res = { via: 'phone', uncertain: true, ...scanParseText(text, mode) };
    }
    const got = mode === 'chapters' ? res.chapters.length : res.topics.length;
    if (!got) { SCAN = { k, msg: 'Nothing readable was found. Try a clearer, straighter photo in good light.' }; render(); return; }
    SCAN = { k, via: res.via, uncertain: !!res.uncertain, chapters: res.chapters, topics: res.topics };
    if (mode === 'lplan') {
      const key = sylKey(c, s);
      LPDRAFT[key] = { topics: res.topics.join('\n'), goal: [res.goal, res.homework ? 'Homework: ' + res.homework : ''].filter(Boolean).join(' • ') };
    }
    render();
  } catch (e) { SCAN = { k, msg: e.message }; render(); }
}
function scanSaveChapters(c, s) {
  const list = SCAN.chapters.map(ch => ({ name: String(ch.name || '').trim().slice(0, 120), topics: (ch.topics || []).map(x => String(x).trim().slice(0, 120)).filter(Boolean).slice(0, 15) })).filter(ch => ch.name.length >= 2);
  if (!list.length) return toast('No chapter to save', 'a');
  const key = sylKey(c, s), y = sylEnsure(key), rep = (document.getElementById('sc-replace') || {}).checked;
  if (rep || !y.chapters.length) {
    const old = {}; y.chapters.forEach(ch => old[ch.n.toLowerCase()] = ch);
    y.chapters = list.map(ch => { const o = old[ch.name.toLowerCase()]; return { n: ch.name, done: o ? o.done : null, plan: o ? o.plan || null : null, topics: ch.topics }; });
  } else {
    const have = new Set(y.chapters.map(ch => ch.n.toLowerCase()));
    list.filter(ch => !have.has(ch.name.toLowerCase())).forEach(ch => y.chapters.push({ n: ch.name, done: null, topics: ch.topics }));
  }
  log('Syllabus', `${c} ${s}: ${list.length} chapter(s) saved from a photo of the contents page (checked by ${userName()})`);
  SCAN = null; toast('Chapters saved. Now press "Spread month-wise".'); render();
}

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
        <table class="tbl"><tr><th>Adm</th><th>Name</th><th>Class</th><th>Father</th><th>Phone</th><th></th></tr>${list.map(s => `<tr><td>${esc(s.adm)}</td><td class="font-semibold"><button onclick="openProfile('s',${jsq(s.id)},'')" class="hover:underline text-left" title="Open full profile">${esc(s.name)}</button>${s.sample ? ' <span class="pill bg-slate-100 text-slate-500">sample</span>' : ''}</td><td>${s.cls}</td><td>${esc(s.father)}</td><td>${esc(s.phone)}</td>
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
          <p class="text-xs text-slate-500">Copy columns from Excel in this order and paste: <b>Adm No, Name, Class, Father, Mobile, Bus km</b> (bus km is optional). Class must be like 10th, 9th … 1st, UKG, LKG, PG.</p>
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
  const km = prompt('School bus distance in km (leave blank if the child does not take the bus)', s.km ?? ''); if (km === null) return;
  if (km.trim() !== '' && !(+km.trim() > 0)) return toast('Bus distance must be a number of km', 'r');
  Object.assign(s, { name: name.trim(), cls: cls.trim(), father: father.trim(), phone: phone.trim(), km: km.trim() === '' ? null : +km.trim(), sample: false });
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
  lines.forEach((l, i) => { const p = l.split(/\t|,/).map(x => x.trim()); const [adm, name, cls, father, phone, kmRaw] = p;
    const km = kmRaw && +kmRaw > 0 ? +kmRaw : null;
    if (!adm || !name || !CLASS_NAMES.includes(cls)) { skipped.push(i + 1); return; }
    const ex = S.students.find(s => s.adm === adm);
    if (ex) Object.assign(ex, { name, cls, father: father || ex.father, phone: phone || ex.phone, km: km ?? ex.km ?? null, sample: false });
    else S.students.push({ id: 's' + uid(), adm, name, cls, father: father || '', phone: phone || '', km });
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
        <div class="flex items-center justify-between gap-2"><span class="text-sm"><b>Accountant</b> ${esc(S.settings.accountantName || 'Akash Tiwari')} ${pinBadge('accountant')}</span><button onclick="setPin('accountant','the Accountant')" class="btn btn-sm btn-l">Set PIN</button></div>
        <div class="flex items-center justify-between gap-2"><span class="text-sm"><b>Reception</b> ${esc(S.settings.receptionName || 'Shiva Rai')} ${pinBadge('reception')}</span><button onclick="setPin('reception','Reception')" class="btn btn-sm btn-l">Set PIN</button></div>
        <div class="grid grid-cols-2 gap-2"><div><label class="lbl">Accountant's name</label><input id="se-acct" class="inp" value="${esc(S.settings.accountantName || 'Akash Tiwari')}"></div>
          <div><label class="lbl">Reception's name</label><input id="se-recp" class="inp" value="${esc(S.settings.receptionName || 'Shiva Rai')}"></div></div>
        <button onclick="saveOfficeNames()" class="btn btn-sm btn-l">Save names</button>
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
function saveOfficeNames() {
  const a = document.getElementById('se-acct').value.trim() || 'Akash Tiwari', r = document.getElementById('se-recp').value.trim() || 'Shiva Rai';
  S.settings.accountantName = a; S.settings.receptionName = r; log('Settings', `Office staff: Accountant ${a}, Reception ${r}`); toast('Saved'); render();
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
// Fill a week's plan from the syllabus: chapters planned for this month first, one line per period.
let LPDRAFT = {};
function lpFromSyllabus(w, key) {
  const [c, s] = key.split('|'), y = S.syl[key], a = S.alloc.find(x => x.c === c && x.s === s && x.t === U.tid) || S.alloc.find(x => x.c === c && x.s === s);
  if (!y || !y.chapters.length) return toast('Add the chapters in Syllabus first (photo of the contents page).', 'a');
  const n = Math.max(1, Math.min(12, (a && +a.n) || 5)), ym = w.slice(0, 7);
  const earlier = new Set(Object.values(S.lplan).filter(p => p.tid === U.tid && p.cls === c && p.subj === s && p.week < w).flatMap(p => String(p.topics || '').split('\n').map(x => x.trim().toLowerCase())));
  const open = y.chapters.filter(ch => !ch.done).sort((x, z) => (x.plan || '9999') < (z.plan || '9999') ? -1 : (x.plan || '9999') > (z.plan || '9999') ? 1 : 0);
  const lines = [];
  for (const ch of open) {
    const parts = (ch.topics || []).length ? ch.topics.map(t => `${ch.n} — ${t}`) : [`${ch.n} — introduction`, `${ch.n} — explanation`, `${ch.n} — examples and practice`, `${ch.n} — exercise / question-answer`, `${ch.n} — revision and test`];
    for (const l of parts) { if (!earlier.has(l.toLowerCase())) lines.push(l); if (lines.length >= n) break; }
    if (lines.length >= n) break;
  }
  if (!lines.length) return toast('Nothing left to plan — all chapters are done.', 'b');
  const cur = LPDRAFT[key] || {};
  LPDRAFT[key] = { topics: lines.join('\n'), goal: cur.goal || ((document.getElementById('lp-goal') || {}).value || '') };
  toast(`${lines.length} period(s) filled from the syllabus${open[0] && open[0].plan && open[0].plan < ym ? ' — this chapter was planned for an earlier month' : ''}. Change anything, then submit.`); render();
}
function vLessonPlan() {
  const mine = myAllocs(U.tid); if (!mine.length) return head('Lesson Plan') + '<div class="card text-sm">No subjects assigned.</div>';
  const thisW = weekStart(todayISO()), w = P.w || thisW, key0 = P.key && mine.some(a => sylKey(a.c, a.s) === P.key) ? P.key : sylKey(mine[0].c, mine[0].s);
  const [c, s] = key0.split('|'), k = lpKey(w, U.tid, c, s), p = S.lplan[k], dr = LPDRAFT[key0];
  const nx = nextChapter(c, s);
  const myPlans = Object.entries(S.lplan).filter(([kk, v]) => v.tid === U.tid && v.week === w);
  return head('Weekly Lesson Plan', 'Write the topics you plan to teach this week. The ERP ticks each one automatically when it appears in your diary.',
      `<select class="inp w-auto" onchange="go('lplan',{w:this.value,key:${jsq(key0)}})">${[addDays(thisW, -7), thisW, addDays(thisW, 7)].map(x => `<option value="${x}" ${x === w ? 'selected' : ''}>Week of ${fmtDate(x)}</option>`).join('')}</select>`) + `
    <div class="card space-y-2"><label class="lbl">Class & subject</label><select class="inp" onchange="SCAN=null;go('lplan',{w:'${w}',key:this.value})">${mine.map(a => { const kk = sylKey(a.c, a.s); return `<option value="${esc(kk)}" ${kk === key0 ? 'selected' : ''}>Class ${a.c} — ${esc(a.s)}</option>`; }).join('')}</select>
      ${p && p.status === 'Approved' ? '<p class="text-sm text-emerald-700 font-semibold">Approved by the Principal — locked.</p>' : `
      <div class="flex flex-wrap gap-2 pt-1"><button onclick="lpFromSyllabus('${w}',${jsq(key0)})" class="btn btn-sm btn-p"><i class="fa-solid fa-wand-magic-sparkles"></i> Fill from syllabus</button>
        ${dr ? `<button onclick="delete LPDRAFT[${jsq(key0)}];SCAN=null;render()" class="btn btn-sm btn-l">Undo fill</button>` : ''}</div>
      <label class="lbl">Topics, one per line (one line ≈ one period)${nx ? ` — next chapter: <b>${esc(nx)}</b>` : ''}</label><textarea id="lp-top" rows="7" class="inp" placeholder="${nx ? esc(nx) + ' — introduction' : 'Topic 1\nTopic 2'}" oninput="LPDRAFT[${jsq(key0)}]={topics:this.value,goal:document.getElementById('lp-goal').value}">${esc(dr ? dr.topics : p ? p.topics : '')}</textarea>
      <label class="lbl">Aim / activity / teaching aid / homework (optional)</label><input id="lp-goal" class="inp" value="${esc(dr ? dr.goal || '' : p ? p.goal || '' : '')}" oninput="LPDRAFT[${jsq(key0)}]={topics:document.getElementById('lp-top').value,goal:this.value}">
      <button onclick="lpSubmit('${w}',${jsq(key0)})" class="btn btn-p w-full">${p ? 'Update and resubmit' : 'Submit to Principal'}</button>`}</div>
    ${p && p.status === 'Approved' ? '' : scanCard('lplan', c, s, 'Or make it from a photo')}
    ${myPlans.map(([kk, v]) => planCard(v, kk, false)).join('')}`;
}
function lpSubmit(w, key) {
  const [c, s] = key.split('|'), topics = document.getElementById('lp-top').value.split('\n').map(x => x.trim()).filter(Boolean).join('\n').slice(0, 3000), goal = document.getElementById('lp-goal').value.trim().slice(0, 400);
  if (!topics) return toast('Write at least one topic', 'a');
  S.lplan[lpKey(w, U.tid, c, s)] = { week: w, tid: U.tid, cls: c, subj: s, topics, goal, status: 'Submitted', feedback: '', at: Date.now() };
  delete LPDRAFT[key]; SCAN = null;
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
  PD.fees = res.fees || null;
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
const BOARD_PLAN_ID = 'board-2026-27-v2';
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
  // v1 (Class 10 only, Pre-Board 2 in February) was replaced the same night by v2.
  // Each version runs once (settings.plans); v2 also corrects data written by v1.
  const set = S.settings; set.plans = set.plans || {};
  if (set.plans[BOARD_PLAN_ID]) return;
  const P = (date, title, subjects, max) => ({ date, title, subjects, max });
  const sheet = d => [P(d[0], 'Mathematics (Standard / Basic)', ['Mathematics'], 80), P(d[1], 'Hindi', ['Hindi'], 80),
    P(d[2], 'English Language & Literature', ['English'], 80), P(d[3], 'Science (Physics, Chemistry, Biology)', ['Physics/Chemistry', 'Biology'], 80),
    P(d[4], 'Social Science', ['Social Science'], 80), P(d[5], 'Computer / IT', ['Computer'], 50)];
  const upsert = e => { const i = S.exams.findIndex(x => x.id === e.id); if (i >= 0) S.exams[i] = { ...S.exams[i], ...e }; else S.exams.push(e); };
  const both = ['9th', '10th'];
  upsert({ id: 'pb1', name: 'Pre-Board 1 (Classes 9–10)', date: '2026-12-07', to: '2026-12-12', classes: both, noClasses: true, time: '09:00–12:00', reportDate: '2026-12-15',
    sheet: sheet(['2026-12-07', '2026-12-08', '2026-12-09', '2026-12-10', '2026-12-11', '2026-12-12']) });
  upsert({ id: 'pb2', name: 'Pre-Board 2 (Classes 9–10)', date: '2026-12-21', to: '2026-12-28', classes: both, noClasses: true, time: '09:00–12:00', reportDate: '2026-12-30',
    sheet: sheet(['2026-12-21', '2026-12-22', '2026-12-23', '2026-12-24', '2026-12-26', '2026-12-28']) });
  upsert({ id: 'ann', name: 'Annual Exam (PG–9th)', date: '2027-02-23', to: '2027-03-11', classes: CLASS_NAMES.filter(c => c !== '10th'), noClasses: true });
  S.exams.sort((a, b) => a.date.localeCompare(b.date));
  S.cal = S.cal.filter(e => !['bp-syl', 'bp-eval', 'bp-ptm', 'bp-doubt'].includes(e.id));      // v1 entries that changed
  const cal = [['bp2-syl', '2026-12-05', '', 'Classes 9 & 10: full syllabus must be complete', 'Event'],
               ['bp2-res1', '2026-12-15', '', 'Pre-Board 1 results to parents (Classes 9 & 10)', 'Event'],
               ['bp-xmas', '2026-12-25', '', 'Christmas (BSA holiday list)', 'Holiday'],
               ['bp2-eval', '2026-12-29', '', 'Pre-Board 2: checking and marks entry', 'Event'],
               ['bp2-ptm', '2026-12-30', '', 'Pre-Board 2 PTM (Classes 9 & 10) and winter worksheets', 'PTM'],
               ['bp-winter', '2026-12-31', '2027-01-14', 'Winter vacation (BSA)', 'Holiday'],
               ['bp2-leave10', '2027-01-16', '', 'Class 10 board study leave begins', 'Event'],
               ['bp2-rev9', '2027-01-16', '2027-02-20', 'Class 9 revision', 'Event']];
  cal.forEach(([id, date, to, title, type]) => { if (!S.cal.some(e => e.id === id || (e.date === date && e.title === title))) S.cal.push({ id, date, to, title, type }); });
  S.cal.sort((a, b) => a.date.localeCompare(b.date));
  set.sylDeadlines = { ...(set.sylDeadlines || {}), '9th': '2026-12-05', '10th': '2026-12-05' };
  set.plans[BOARD_PLAN_ID] = Date.now();
  log('Exam Plan', 'Board plan updated for Classes 9 & 10: full syllabus by 5 Dec; Pre-Board 1 7–12 Dec (results 15 Dec); Pre-Board 2 21–28 Dec; PTM 30 Dec; winter vacation 31 Dec–14 Jan; Class 10 study leave from 16 Jan; Class 9 revision 16 Jan–20 Feb; Annual exam (PG–9th) 23 Feb–11 Mar');
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
  const cls = examClasses(e), few = cls.length <= 3 ? cls : null, fewTxt = few ? few.join(' & ') : '';
  const sheet = (e.sheet || []).slice().sort((a, b) => a.date.localeCompare(b.date));
  const marksState = p => few ? few.map(c => p.subjects.map(sb => { const r = (S.marks[e.id] || {})[c + '|' + sb]; return `${few.length > 1 ? c + ' ' : ''}${esc(sb)}: ${r ? '<span class="text-leaf font-semibold">entered</span>' : '<span class="text-slate-400">waiting</span>'}`; }).join('<br>')).join('<br>') : '';
  const teachersOf = p => few ? [...new Set(few.flatMap(c => paperTeachers(c, p)))].join(', ') : '';
  const rep = examReport(e).filter(r => r.any);
  const subsOf = few ? [...new Set(S.alloc.filter(a => few.includes(a.c) && a.n > 0).map(a => a.s))] : [];
  return head('Exams & date sheets', 'Normal classes stop automatically for the classes sitting an exam. Results go to parents on WhatsApp from the report date.',
      `<select class="inp w-auto" onchange="go('exams',{ex:this.value})">${S.exams.map(x => `<option value="${esc(x.id)}" ${x.id === e.id ? 'selected' : ''}>${esc(x.name)}</option>`).join('')}</select>`) + `
    <div class="card space-y-1"><h3 class="h3">${esc(e.name)}</h3>
      <p class="text-sm text-slate-600">${fmtDate(e.date)}${examEnd(e) !== e.date ? ' to ' + fmtDate(examEnd(e)) : ''} · ${cls.length === CLASS_NAMES.length ? 'All classes' : 'Class ' + cls.join(', ')}${e.time ? ' · ' + esc(e.time) : ''}${e.noClasses ? ' · no normal classes for these students in this period' : ''}</p>
      ${few ? few.filter(c => sylDeadline(c)).map(c => `<p class="text-sm"><b>Class ${c} syllabus deadline:</b> 100% by ${fmtDate(sylDeadline(c))}</p>`).join('') : ''}</div>
    <div class="card overflow-x-auto"><div class="flex flex-wrap justify-between gap-2 mb-2"><h3 class="h3">Date sheet</h3>
      ${sheet.length && few ? `<span class="flex gap-2 noprint"><a target="_blank" href="${waLink('', dateSheetText({ ...e, sheet }, fewTxt))}" onclick="log('Exam Plan',${jsq('Date sheet shared: ' + e.name)})" class="btn btn-sm btn-wa"><i class="fa-brands fa-whatsapp"></i> Share</a>
        <button onclick="printHTML('Date sheet',printHeader(${jsq(e.name + ' — date sheet, Class ' + fewTxt)})+document.getElementById('dsheet').outerHTML+PRINT_FOOT)" class="btn btn-sm btn-l"><i class="fa-solid fa-print"></i> Print</button></span>` : ''}</div>
      ${sheet.length ? `<table class="tbl" id="dsheet"><tr><th>Date</th><th>Paper</th><th>Marks</th><th>Teacher</th>${few ? '<th class="noprint">Marks entry</th>' : ''}${ro ? '' : '<th class="noprint"></th>'}</tr>
        ${sheet.map(p => { const i = e.sheet.indexOf(p); return `<tr><td class="whitespace-nowrap">${fmtDate(p.date)}</td><td class="font-semibold">${esc(p.title)}</td>
          <td>${ro ? p.max : `<input type="number" min="1" class="inp py-1 w-20" value="${p.max}" onchange="examPaperMax('${esc(e.id)}',${i},this.value)">`}</td>
          <td>${esc(teachersOf(p))}</td>${few ? `<td class="text-xs noprint">${marksState(p)}</td>` : ''}
          ${ro ? '' : `<td class="noprint"><button onclick="examPaperDel('${esc(e.id)}',${i})" class="text-slate-300 hover:text-rose"><i class="fa-solid fa-trash"></i></button></td>`}</tr>`; }).join('')}</table>`
      : '<p class="text-sm text-slate-500">No papers added yet.</p>'}
      ${!ro && few ? `<div class="mt-4 pt-4 border-t border-line grid grid-cols-1 md:grid-cols-4 gap-2 items-end noprint">
        <div><label class="lbl">Date</label><input id="pp-date" type="date" class="inp" value="${e.date}"></div>
        <div class="md:col-span-2"><label class="lbl">Subjects in this paper</label><div class="flex flex-wrap gap-2">${subsOf.map(sb => `<label class="chip"><input type="checkbox" class="pp-sub mr-1" value="${esc(sb)}">${esc(sb)}</label>`).join('')}</div></div>
        <div><label class="lbl">Marks</label><div class="flex gap-2"><input id="pp-max" type="number" class="inp" value="80"><button onclick="examPaperAdd('${esc(e.id)}')" class="btn btn-p">Add</button></div></div></div>` : ''}
    </div>
    ${sheet.length ? `<div class="card overflow-x-auto"><div class="flex flex-wrap justify-between gap-2 mb-2"><h3 class="h3">Results for parents</h3>
        <span class="text-sm text-slate-500">${rep.filter(r => (e.sent || {})[r.s.id]).length} of ${rep.length} sent${e.reportDate ? ' · send from ' + fmtDate(e.reportDate) : ''}</span></div>
      ${rep.length ? `<table class="tbl"><tr><th>Student</th>${cls.length > 1 ? '<th>Class</th>' : ''}${sheet.map(p => `<th>${esc(p.title.replace(/\s*\(.*\)/, ''))}</th>`).join('')}<th>%</th><th>Needs practice</th>${ro ? '' : '<th class="noprint"></th>'}</tr>
        ${rep.map(r => `<tr><td class="font-semibold whitespace-nowrap">${esc(r.s.name)}</td>${cls.length > 1 ? `<td>${esc(r.cls)}</td>` : ''}${sheet.map(p => { const x = r.papers.find(y => y.p === p) || r.papers[e.sheet.indexOf(p)];
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
  return head('Quiz Duel & Game Zone', 'Approve students\' questions, add your own, see which topics need reteaching, and print worksheets.') + gameTeacherCard() + `
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

// ---------------------------------------------------------------- FEE STRUCTURE (record fees/main; Principal edits, parents see their own child's fee)
// Starting values are copied from the school's printed "Fee Structure 2024-2025" sheet.
// Class 10 monthly fee and the "above 30 km" bus charge are blank on that sheet.
function feeSheet2024() {
  const m = {}; ['PG', 'LKG', 'UKG'].forEach(c => m[c] = 600); ['1st', '2nd', '3rd'].forEach(c => m[c] = 790); ['4th', '5th'].forEach(c => m[c] = 850);
  ['6th', '7th'].forEach(c => m[c] = 990); m['8th'] = 1200; m['9th'] = 1500; m['10th'] = null;
  return { session: '2024–25', source: 'Printed fee structure 2024–25', monthly: m,
    yearly: [{ name: 'Exam fee', amt: 1800, skip: ['PG', 'LKG', 'UKG'] }, { name: 'Digital classroom', amt: 1200 }, { name: 'Sports', amt: 1000 }, { name: 'Health and safety', amt: 500 }],
    admission: [{ name: 'Processing fee', amt: 0, was: 1200 }, { name: 'Admission fee', amt: 0, was: 6000 }],
    transport: [{ upto: 6, amt: 600 }, { upto: 10, amt: 800 }, { upto: 18, amt: 1200 }, { upto: 25, amt: 1500 }, { upto: 30, amt: 2000 }, { upto: null, amt: null }],
    thirdChildPct: 50,
    notes: ['Fees can be paid online, or by cash, cheque or demand draft at the school campus only.', 'Make all payments in favour of "S.N Mishra Singapore International School", payable at Bhadohi.',
      'Always take a proper receipt for every payment.', 'Fees once paid are not refundable.', 'Fees are paid every month.', 'The exam fee is paid once a year.',
      'If a parent has three children in the school, the third child gets 50% off the composite fee. Transport and all other charges are paid in full.',
      'Books and the school diary are sold only at the school counter.', 'The tie and belt are also sold at the counter.',
      'For the ID card, give two passport-size photos at admission or at the start of the session. ID cards are ready by the end of June.',
      'Transport charges may change if fuel prices rise.'] };
}
function applyFeeSheet() { if (S.fees) return; S.fees = feeSheet2024(); log('Fees', 'Fee structure added from the printed 2024–25 sheet (Class 10 monthly fee and above-30 km bus charge left blank)'); }
const rs = n => n == null || n === '' ? '—' : '₹' + Number(n).toLocaleString('en-IN');
function familyOf(s, all) { const p = String(s.phone || '').replace(/\D/g, '').slice(-10); return p.length === 10 ? all.filter(x => String(x.phone || '').replace(/\D/g, '').slice(-10) === p) : [s]; }
function busFee(F, km) {
  if (km == null || km === '' || !(+km > 0)) return { amt: 0, label: 'No school bus' };
  const slab = F.transport.find(t => t.upto == null || +km <= +t.upto);
  const prev = F.transport[F.transport.indexOf(slab) - 1];
  return { amt: slab ? slab.amt : null, label: slab ? (slab.upto == null ? `above ${prev ? prev.upto : 0} km` : `${prev ? prev.upto : 0}–${slab.upto} km`) : '' };
}
function feeFor(s, family, F) {
  F = F || S.fees; if (!F) return null;
  const comp = F.monthly[s.cls] ?? null;
  // Third and later children: the ones with the lowest composite fee get the discount.
  const order = family.slice().sort((a, b) => (F.monthly[b.cls] || 0) - (F.monthly[a.cls] || 0) || String(a.adm).localeCompare(String(b.adm)));
  const third = family.length >= 3 && order.indexOf(order.find(x => x.id === s.id)) >= 2;
  const disc = third && comp != null ? Math.round(comp * (+F.thirdChildPct || 0) / 100) : 0;
  const bus = busFee(F, s.km);
  const yearly = F.yearly.filter(y => !(y.skip || []).includes(s.cls)).reduce((a, y) => a + (+y.amt || 0), 0);
  const month = comp == null || bus.amt == null ? null : comp - disc + bus.amt;
  return { comp, disc, third, bus, month, yearly };
}
function feeBreak(s, f) {
  return `<table class="tbl mt-2"><tr><td>Composite fee, Class ${esc(s.cls)}</td><td class="text-right">${f.comp == null ? 'Ask the office' : rs(f.comp)}</td></tr>
    ${f.third ? `<tr><td>Third-child discount (${S.fees.thirdChildPct}%)</td><td class="text-right text-leaf">− ${rs(f.disc)}</td></tr>` : ''}
    <tr><td>School bus${f.bus.amt ? ' (' + esc(f.bus.label) + ')' : ''}</td><td class="text-right">${f.bus.amt === 0 ? 'None' : f.bus.amt == null ? 'Ask the office' : rs(f.bus.amt)}</td></tr>
    <tr><th>Every month</th><th class="text-right">${f.month == null ? 'Ask the office' : rs(f.month)}</th></tr>
    <tr><td>Once a year (${S.fees.yearly.filter(y => !(y.skip || []).includes(s.cls)).map(y => esc(y.name.toLowerCase())).join(', ')})</td><td class="text-right">${rs(f.yearly)}</td></tr></table>`;
}
function vFees() {
  const F = S.fees; const ro = U.role !== 'principal';
  if (!F) return head('Fee structure') + '<div class="card text-sm">Not set up yet. The Principal\'s login adds it.</div>';
  const inp = (path, v, w) => ro ? rs(v) : `<input class="inp py-1 ${w || 'w-28'}" type="number" min="0" value="${v ?? ''}" onchange="feeSet(${jsq(path)},this.value)">`;
  const groups = [['PG', 'LKG', 'UKG'], ['1st', '2nd', '3rd'], ['4th', '5th'], ['6th', '7th'], ['8th'], ['9th'], ['10th']];
  const rows = S.students.slice().sort((a, b) => CLASS_NAMES.indexOf(a.cls) - CLASS_NAMES.indexOf(b.cls) || a.name.localeCompare(b.name));
  const blank = CLASS_NAMES.filter(c => F.monthly[c] == null);
  return head('Fee structure', `Session label: ${esc(F.session)} · ${esc(F.source || '')}`, ro ? '' : `<button onclick="feeSession()" class="btn btn-l">Change session label</button>`) + `
    ${blank.length ? `<div class="card bg-marigold/10 border-marigold/40 text-sm">No monthly fee yet for Class ${blank.join(', ')}. Parents of these classes see "Ask the office" until you fill it in.</div>` : ''}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
      <div class="card"><h3 class="h3">Composite fee (every month)</h3><table class="tbl mt-2"><tr><th>Class</th><th class="text-right">Per month</th></tr>
        ${ro ? groups.map(g => `<tr><td>${g.join(', ')}</td><td class="text-right">${rs(F.monthly[g[0]])}</td></tr>`).join('') : CLASS_NAMES.map(c => `<tr><td>${c}</td><td class="text-right">${inp('monthly.' + c, F.monthly[c])}</td></tr>`).join('')}</table></div>
      <div class="space-y-4">
        <div class="card"><h3 class="h3">Once a year</h3><table class="tbl mt-2">${F.yearly.map((y, i) => `<tr><td>${esc(y.name)}${(y.skip || []).length ? ` <span class="text-xs text-slate-500">(not for ${y.skip.join(', ')})</span>` : ''}</td><td class="text-right">${inp('yearly.' + i, y.amt)}</td></tr>`).join('')}
          <tr><th>Total</th><th class="text-right">${rs(F.yearly.reduce((a, y) => a + (+y.amt || 0), 0))}</th></tr></table></div>
        <div class="card"><h3 class="h3">New admission</h3><table class="tbl mt-2">${F.admission.map((y, i) => `<tr><td>${esc(y.name)}${y.was ? ` <span class="text-xs text-slate-500">(was ${rs(y.was)})</span>` : ''}</td><td class="text-right">${ro ? (y.amt ? rs(y.amt) : 'Nil') : inp('admission.' + i, y.amt)}</td></tr>`).join('')}</table></div>
        <div class="card"><h3 class="h3">School bus (every month, optional)</h3><table class="tbl mt-2">${F.transport.map((t, i) => { const p = F.transport[i - 1];
          return `<tr><td>${t.upto == null ? `Above ${p ? p.upto : 0} km` : `${p ? p.upto : 0}–${t.upto} km`}</td><td class="text-right">${t.amt == null && ro ? 'Ask the office' : inp('transport.' + i, t.amt)}</td></tr>`; }).join('')}</table>
          <p class="text-xs text-slate-500 mt-2">Set each student's bus distance in Students (edit). Blank = no bus.</p></div>
        <div class="card"><h3 class="h3">Third child</h3><p class="text-sm mt-1">${ro ? F.thirdChildPct + '%' : inp('thirdChildPct', F.thirdChildPct, 'w-20') + ' %'} off the composite fee for the third (and any further) child with the same parent mobile number. Bus and other charges in full.</p></div>
      </div></div>
    <div class="card"><h3 class="h3">Notes printed on the fee sheet</h3>${ro ? `<ol class="list-decimal pl-5 text-sm space-y-1 mt-2">${F.notes.map(n => `<li>${esc(n)}</li>`).join('')}</ol>`
      : `<textarea id="fee-notes" rows="8" class="inp mt-2 text-sm">${esc(F.notes.join('\n'))}</textarea><button onclick="feeNotes()" class="btn btn-sm btn-l mt-2">Save notes</button>`}
      <p class="text-xs text-slate-500 mt-2">Bank account details are not shown here; parents get them from the office.</p></div>
    <div class="card overflow-x-auto"><div class="flex flex-wrap justify-between gap-2"><h3 class="h3">Each student's fee</h3><button onclick="printHTML('Fees',printHeader('Monthly fee by student — '+${jsq(F.session)})+document.getElementById('feetab').outerHTML+PRINT_FOOT)" class="btn btn-sm btn-l"><i class="fa-solid fa-print"></i> Print</button></div>
      ${rows.length ? `<table class="tbl mt-2" id="feetab"><tr><th>Student</th><th>Class</th><th>Bus</th><th class="text-right">Composite</th><th class="text-right">Bus</th><th class="text-right">Every month</th><th class="text-right">Once a year</th></tr>
        ${rows.map(s => { const f = feeFor(s, familyOf(s, S.students)); return `<tr><td class="font-semibold">${esc(s.name)}${f.third ? ' <span class="pill bg-leaf/10 text-leaf">3rd child</span>' : ''}</td><td>${s.cls}</td><td>${s.km ? esc(s.km) + ' km' : '—'}</td>
          <td class="text-right">${f.comp == null ? '—' : rs(f.comp - f.disc)}</td><td class="text-right">${f.bus.amt === 0 ? '—' : rs(f.bus.amt)}</td><td class="text-right font-semibold">${f.month == null ? 'Not set' : rs(f.month)}</td><td class="text-right">${rs(f.yearly)}</td></tr>`; }).join('')}</table>`
      : '<p class="text-sm text-slate-500 mt-1">Add students first.</p>'}</div>`;
}
function feeSet(path, v) {
  const F = S.fees, [k, key] = path.split('.'), n = v === '' ? null : Math.max(0, Math.round(+v));
  if (k === 'monthly') F.monthly[key] = n; else if (k === 'thirdChildPct') F.thirdChildPct = n ?? 0; else F[k][+key].amt = n;
  log('Fees', `Fee structure changed: ${k}${key ? ' ' + (k === 'monthly' ? key : (F[k][+key].name || (F[k][+key].upto == null ? 'above last slab' : 'up to ' + F[k][+key].upto + ' km'))) : ''} → ${n == null ? 'blank' : '₹' + n}`); render();
}
function feeNotes() { S.fees.notes = document.getElementById('fee-notes').value.split('\n').map(x => x.trim()).filter(Boolean); log('Fees', 'Fee notes updated'); toast('Saved'); render(); }
function feeSession() { const v = prompt('Session these fees are for (e.g. 2026–27)', S.fees.session); if (v === null || !v.trim()) return; S.fees.session = v.trim(); S.fees.source = 'Set by the Principal'; log('Fees', 'Fee structure session set to ' + v.trim()); render(); }
function vPFees() {
  const F = PD.fees;
  if (!F) return head('Fees') + pFeeDues() + '<div class="card text-sm">The school has not published the fee structure yet.</div>';
  const fam = PD.kids.map(k => ({ ...k, phone: 'fam' }));
  const sv = S.fees; S.fees = F;
  const cards = PD.kids.map(k => { const f = feeFor(k, fam, F); return `<div class="card"><h3 class="h3">${esc(k.name)} · Class ${esc(k.cls)}</h3>${feeBreak(k, f)}</div>`; }).join('');
  const total = PD.kids.map(k => feeFor(k, fam, F).month);
  S.fees = sv;
  return head('Fees', `Fee structure ${esc(F.session)} · paid every month`) + pFeeDues() + cards +
    (PD.kids.length > 1 ? `<div class="card flex justify-between"><b>All children, every month</b><b>${total.some(x => x == null) ? 'Ask the office' : rs(total.reduce((a, b) => a + b, 0))}</b></div>` : '') +
    `<div class="card"><h3 class="h3">Please note</h3><ol class="list-decimal pl-5 text-sm space-y-1 mt-2">${F.notes.map(n => `<li>${esc(n)}</li>`).join('')}</ol>
     <p class="text-xs text-slate-500 mt-2">For bank details and payment, contact the school office. Always take a receipt — it then shows in the fee account above.</p></div>`;
}

// ---------------------------------------------------------------- SCHOOL OFFICE: FEE DESK (Accountant) + DUES CALLING (Reception) — school/office_api.php
// Dues are worked out on the server (office_lib.php): old due + yearly fees + monthly fee × months so far − concession − receipts.
// Office logins never get the whole school's data; only students, fees, receipts and calls.
let OF = null, OFBUSY = false, OFSEL = null, OFOPEN = null, OFNONCE = null, OFQ = '';
function isOffice() { return !!U && (U.role === 'accountant' || U.role === 'reception'); }
async function oapi(a, body, q) {
  const url = 'office_api.php?a=' + a + (q ? '&' + new URLSearchParams(q) : '');
  const opt = { method: body ? 'POST' : 'GET', headers: { 'X-School-Token': TOKEN || '' } };
  if (body) { opt.headers['Content-Type'] = 'application/json'; opt.body = JSON.stringify(body); }
  let r, j = null;
  try { r = await fetch(url, opt); } catch (e) { throw new Error('No internet connection. Please try again.'); }
  try { j = await r.json(); } catch (e) {}
  if (r.status === 401) { forceLogout(); throw new Error('Please log in again.'); }
  if (!r.ok || !j || !j.ok) throw new Error((j && j.error) || 'Server problem. Please try again.');
  return j;
}
async function loadOffice() {       // office logins: this is their whole data
  const res = await oapi('data');
  OF = res; ME = res.me; U = { role: ME.role, tid: ME.tid };
  S = emptyState(); S.settings = { school: res.school, session: res.session, place: res.place || 'Bhadohi', sessionStart: res.sessionStart }; S.fees = res.fees;
}
async function ofLoad(force) {
  if (OFBUSY || (OF && !force)) return; OFBUSY = true;
  try { const res = await oapi('data'); OF = res; OF._at = Date.now(); } catch (e) { OF = { error: e.message, _at: Date.now() }; } finally { OFBUSY = false; }
  if (['fdesk', 'fdues', 'fcalls', 'freceipts', 'admin', 'dash'].includes(view) || PROF) { const y = window.scrollY; render(); window.scrollTo(0, y); }
}
function ofFresh() { if (!OF || (OF._at && Date.now() - OF._at > 120000)) ofLoad(!!OF); }   // dashboards: refresh fee data every 2 minutes
function ofReady(title) {
  if (OF && !OF.error) return null;
  if (!OF) { ofLoad(); return head(title) + '<div class="card text-sm text-slate-500">Loading fee records…</div>'; }
  return head(title) + `<div class="card text-sm text-rose-700">${esc(OF.error)} <button onclick="OF=null;render()" class="btn btn-sm btn-l ml-2">Try again</button></div>`;
}
function ofFamilies() {
  const by = {};
  OF.students.forEach(s => { (by[s.fam] = by[s.fam] || { key: s.fam, phone: s.phone, father: s.father, kids: [], due: 0, missing: false }).kids.push(s); });
  return Object.values(by).map(f => { f.due = f.kids.reduce((a, k) => a + k.dues.due, 0); f.missing = f.kids.some(k => k.dues.missing);
    f.calls = OF.calls.filter(c => c.fam === f.key); f.last = f.calls[0] || null; return f; });
}
function ofKidLine(f) { return f.kids.map(k => `${esc(k.name)} <span class="text-slate-500">(${esc(k.cls)})</span>`).join(', '); }
function ofSummary() {
  const today = OF.today, live = OF.payments.filter(p => !p.cancelled);
  const tday = live.filter(p => p.date === today), mon = live.filter(p => p.date.slice(0, 7) === today.slice(0, 7));
  const dueFam = ofFamilies().filter(f => f.due > 0), totalDue = dueFam.reduce((a, f) => a + f.due, 0);
  const modes = {}; tday.forEach(p => modes[p.mode] = (modes[p.mode] || 0) + p.amt);
  const missing = OF.students.filter(s => s.dues.missing).length;
  const k = (ic, t, lbl, val, sub) => `<div class="kpi ${t}"><span class="kpi-ic"><i class="fa-solid ${ic}"></i></span><span class="kpi-val">${val}</span><span class="kpi-lbl">${lbl}</span><span class="kpi-sub">${sub}</span></div>`;
  return `<div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
    ${k('fa-indian-rupee-sign', 'kpi-peach', 'Collected today', rs(tday.reduce((a, p) => a + p.amt, 0)), `${tday.length} receipt(s)${Object.keys(modes).length ? ' · ' + Object.entries(modes).map(([m, v]) => esc(m) + ' ' + rs(v)).join(', ') : ''}`)}
    ${k('fa-calendar-check', 'kpi-lav', 'This month', rs(mon.reduce((a, p) => a + p.amt, 0)), `${mon.length} receipt(s)`)}
    ${k('fa-hourglass-half', 'kpi-mint', 'Still due', rs(totalDue), `${dueFam.length} famil${dueFam.length === 1 ? 'y' : 'ies'}`)}
    ${k('fa-circle-exclamation', missing ? 'kpi-warn' : 'kpi-lime', 'Fee not set', missing, 'student(s) — set in Fee Structure or here')}</div>`;
}
function ofBreak(s) {
  if(s.dues.detailed)return '<p class="text-sm">This child uses the detailed fee ledger. Current recorded balance: <b>'+rs(s.dues.due)+'</b>.</p><button class="btn btn-l" onclick="go(\'finance\')">Open Fee Department</button>';
  const d = s.dues;
  return `<table class="tbl mt-2 text-sm">
    ${d.opening ? `<tr><td>Old due (before this session)</td><td class="text-right">${rs(d.opening)}</td></tr>` : ''}
    ${d.free ? `<tr><td colspan="2"><span class="pill bg-indigo-100 text-indigo-800">Free scheme${d.scheme ? ': ' + esc(d.scheme) : ''}</span> — no monthly or yearly fee</td></tr>` : `
    <tr><td>Yearly fees</td><td class="text-right">${rs(d.yearly)}</td></tr>
    <tr><td>Monthly ${d.monthly == null ? '<b class="text-amber-700">not set</b>' : rs(d.monthly) + (d.custom ? ' <span class="pill bg-slate-100 text-slate-600">own fee</span>' : '')} × ${d.months} month(s)</td><td class="text-right">${d.monthly == null ? '—' : rs(d.monthly * d.months)}</td></tr>`}
    ${d.concession ? `<tr><td>Concession</td><td class="text-right text-leaf">− ${rs(d.concession)}</td></tr>` : ''}
    <tr><td>Paid this session</td><td class="text-right text-leaf">− ${rs(d.paid)}</td></tr>
    <tr><th>${d.due < 0 ? 'Advance' : 'Due now'}</th><th class="text-right ${d.due > 0 ? 'text-rose-700' : 'text-leaf'}">${rs(Math.abs(d.due))}</th></tr></table>`;
}

// ---- Fee Desk (Accountant / Principal)
function vFDesk() {
  const w = ofReady('Fee Desk'); if (w) return w;
  const sel = OFSEL && OF.students.find(s => s.id === OFSEL);
  return head('Fee Desk', `Session ${esc(OF.session)} · from ${fmtDate(OF.sessionStart)} · ${esc(OF.names.accountant)} (Accountant)`, `<button onclick="ofLoad(true)" class="btn btn-l"><i class="fa-solid fa-rotate"></i> Refresh</button>`) + ofSummary() + `
    <div class="card"><label class="lbl">Find a student — name, admission no., class or mobile</label>
      <input id="of-q" class="inp" value="${esc(OFQ)}" oninput="OFQ=this.value;document.getElementById('of-res').innerHTML=ofResults()" placeholder="e.g. Rahul, 92, 5th, 98390…" autocomplete="off">
      <div id="of-res" class="mt-2">${ofResults()}</div></div>
    ${sel ? ofStudentPanel(sel) : ''}`;
}
function ofResults() {
  const q = OFQ.trim().toLowerCase(); if (q.length < 1) return '<p class="text-xs text-slate-500">Type to search.</p>';
  const digits = q.replace(/\D/g, '');
  const hit = OF.students.filter(s => s.name.toLowerCase().includes(q) || String(s.adm).toLowerCase() === q || s.cls.toLowerCase() === q || (digits.length >= 4 && String(s.phone).replace(/\D/g, '').includes(digits)) || (s.father || '').toLowerCase().includes(q)).slice(0, 25);
  if (!hit.length) return '<p class="text-sm text-slate-500">No student found.</p>';
  return `<div class="divide-y divide-slate-100">${hit.map(s => `<button onclick="OFSEL=${jsq(s.id)};OFNONCE=null;render();setTimeout(()=>{const e=document.getElementById('of-panel');e&&e.scrollIntoView({behavior:'smooth'})},50)" class="w-full text-left py-2 flex justify-between gap-2 hover:bg-slate-50">
    <span><b>${esc(s.name)}</b> <span class="text-xs text-slate-500">Class ${esc(s.cls)} · Adm ${esc(s.adm)}${s.father ? ' · ' + esc(s.father) : ''}</span></span>
    <span class="font-semibold ${s.dues.due > 0 ? 'text-rose-700' : 'text-leaf'}">${s.dues.missing ? 'Fee not set' : s.dues.due > 0 ? rs(s.dues.due) : 'Clear'}</span></button>`).join('')}</div>`;
}
function ofStudentPanel(s) {
  const fam = OF.students.filter(x => x.fam === s.fam), ids = fam.map(x => x.id), can = s.detailed ? {...OF.can,pay:false,set:false} : OF.can, st = s.set || {};
  if (!OFNONCE) OFNONCE = Array.from(crypto.getRandomValues(new Uint8Array(10)), b => b.toString(16).padStart(2, '0')).join('');
  const hist = OF.payments.filter(p => ids.includes(p.sid));
  return `<div id="of-panel" class="grid grid-cols-1 lg:grid-cols-2 gap-4">
    <div class="space-y-4">${fam.map(k => `<div class="card ${k.id === s.id ? 'ring-2 ring-marigold' : ''}"><div class="flex justify-between gap-2"><h3 class="h3">${esc(k.name)} · Class ${esc(k.cls)}</h3>
        ${k.id !== s.id ? `<button onclick="OFSEL=${jsq(k.id)};OFNONCE=null;render()" class="btn btn-sm btn-l">Select</button>` : '<span class="pill bg-marigold/20 text-ink">Selected</span>'}</div>
        <p class="text-xs text-slate-500">Adm ${esc(k.adm)}${k.father ? ' · Father ' + esc(k.father) : ''}${k.phone ? ' · ' + esc(k.phone) : ''}${k.dues.third ? ' · 3rd child discount' : ''}${k.dues.lastPaid ? ' · last paid ' + fmtDate(k.dues.lastPaid) : ''}</p>${ofBreak(k)}</div>`).join('')}
      ${fam.length > 1 ? `<div class="card flex justify-between"><b>Whole family due</b><b class="text-rose-700">${rs(fam.reduce((a, k) => a + k.dues.due, 0))}</b></div>` : ''}</div>
    <div class="space-y-4">
      ${can.pay ? `<div class="card space-y-2"><h3 class="h3">Take fee — ${esc(s.name)}</h3>
        <div class="grid grid-cols-2 gap-2"><div><label class="lbl">Amount (₹)</label><input id="of-amt" type="number" min="1" inputmode="numeric" class="inp" value="${s.dues.due > 0 ? s.dues.due : ''}"></div>
          <div><label class="lbl">Date</label><input id="of-date" type="date" class="inp" value="${OF.today}" max="${OF.today}"></div>
          <div><label class="lbl">Paid by</label><select id="of-mode" class="inp">${OF.modes.map(m => `<option>${esc(m)}</option>`).join('')}</select></div>
          <div><label class="lbl">Cheque / UPI ref.</label><input id="of-ref" class="inp" maxlength="60"></div></div>
        <div><label class="lbl">Note (optional)</label><input id="of-note" class="inp" maxlength="200" placeholder="e.g. April–June fee"></div>
        <button onclick="ofPay(${jsq(s.id)})" class="btn btn-g w-full py-3"><i class="fa-solid fa-receipt"></i> Save & print receipt</button></div>` : ''}
      ${can.set ? `<div class="card space-y-2"><h3 class="h3">Fee settings for ${esc(s.name)}</h3>
        <div class="grid grid-cols-2 gap-2"><div><label class="lbl">Old due before ${fmtDate(OF.sessionStart)} (₹)</label><input id="os-open" type="number" class="inp" value="${st.opening || ''}" placeholder="0"></div>
          <div><label class="lbl">Own monthly fee (₹)</label><input id="os-cust" type="number" min="0" class="inp" value="${st.custom ?? ''}" placeholder="Blank = as per structure"></div>
          <div><label class="lbl">Concession this year (₹)</label><input id="os-conc" type="number" min="0" class="inp" value="${st.concession || ''}" placeholder="0"></div>
          <div><label class="lbl">Scheme name</label><input id="os-sch" class="inp" maxlength="60" value="${esc(st.scheme || '')}" placeholder="e.g. FD company"></div></div>
        <label class="flex items-center gap-2 text-sm"><input id="os-free" type="checkbox" ${st.free ? 'checked' : ''}> Free scheme — no monthly or yearly fee</label>
        <div><label class="lbl">Note</label><input id="os-note" class="inp" maxlength="200" value="${esc(st.note || '')}"></div>
        <button onclick="ofSetStu(${jsq(s.id)})" class="btn btn-l w-full">Save fee settings</button>
        ${st.by ? `<p class="text-[11px] text-slate-500">Last changed by ${esc(st.by)}</p>` : ''}</div>` : ''}
      <div class="card"><h3 class="h3">Receipts</h3>${hist.length ? `<table class="tbl mt-2 text-sm"><tr><th>No.</th><th>Date</th><th>Child</th><th class="text-right">Amount</th><th></th></tr>${hist.map(p => `<tr class="${p.cancelled ? 'line-through text-slate-400' : ''}"><td>${p.no}</td><td>${fmtDate(p.date)}</td><td>${esc(p.name)}</td><td class="text-right">${rs(p.amt)}</td>
          <td class="text-right"><button onclick="ofPrint(${p.no})" class="text-indigo-700"><i class="fa-solid fa-print"></i></button></td></tr>`).join('')}</table>` : '<p class="text-sm text-slate-500 mt-1">No receipt this session.</p>'}</div>
    </div></div>`;
}
async function ofPay(sid) {
  if (OFBUSY) return;
  const g = id => document.getElementById(id).value.trim(), amt = g('of-amt');
  if (!(+amt > 0)) return toast('Enter the amount', 'a');
  const s = OF.students.find(x => x.id === sid);
  if (!confirm(`Take ${rs(+amt)} from ${s.name} (Class ${s.cls}) by ${g('of-mode')}?`)) return;
  OFBUSY = true;
  try {
    const res = await oapi('pay', { sid, amt: +amt, mode: g('of-mode'), ref: g('of-ref'), date: g('of-date'), note: g('of-note'), nonce: OFNONCE });
    OFNONCE = null; toast(res.again ? 'Already saved — receipt #' + res.receipt.no : 'Receipt #' + res.receipt.no + ' saved');
    printReceipt(res.receipt);
  } catch (e) { toast(e.message, 'r'); } finally { OFBUSY = false; }
  ofLoad(true);
}
async function ofSetStu(sid) {
  if (OFBUSY) return; OFBUSY = true;
  const g = id => document.getElementById(id).value.trim();
  try { await oapi('setstu', { sid, opening: g('os-open'), custom: g('os-cust'), concession: g('os-conc'), scheme: g('os-sch'), free: document.getElementById('os-free').checked, note: g('os-note') }); toast('Saved'); }
  catch (e) { toast(e.message, 'r'); } finally { OFBUSY = false; }
  ofLoad(true);
}
function ofPrint(no) { const p = OF.payments.find(x => x.no === no); if (p) printReceipt(p); }
function printReceipt(p) {
  const copy = who => `<div style="border:1.5px solid #17324D;padding:14px;margin-bottom:14px;page-break-inside:avoid">
    <div style="display:flex;justify-content:space-between"><b style="font-size:15px">${esc(S.settings.school)}, ${esc(S.settings.place || 'Bhadohi')}</b><span style="font-size:11px;color:#666">${who}</span></div>
    <div style="display:flex;justify-content:space-between;margin:6px 0"><b>FEE RECEIPT No. ${p.no}</b><span>Date: ${fmtDate(p.date)}</span></div>
    ${p.cancelled ? `<div style="color:#b91c1c;font-weight:bold">CANCELLED — ${esc(p.cancelReason || '')}</div>` : ''}
    <table style="width:100%;font-size:13px"><tr><td>Student</td><td><b>${esc(p.name)}</b></td><td>Class</td><td>${esc(p.cls)}</td></tr>
      <tr><td>Adm. no.</td><td>${esc(p.adm || '')}</td><td>Father</td><td>${esc(p.father || '')}</td></tr>
      <tr><td>Amount</td><td colspan="3"><b style="font-size:16px">${rs(p.amt)}</b> &nbsp; (${esc(p.words || '')})</td></tr>
      <tr><td>Paid by</td><td>${esc(p.mode)}${p.ref ? ' — ' + esc(p.ref) : ''}</td><td>Session</td><td>${esc(p.session || '')}</td></tr>
      ${p.note ? `<tr><td>Note</td><td colspan="3">${esc(p.note)}</td></tr>` : ''}</table>
    <div style="display:flex;justify-content:space-between;margin-top:22px;font-size:12px"><span>Received by: ${esc(p.by || '')}</span><span>Signature ______________</span></div>
    <div style="font-size:10px;color:#666;margin-top:6px">Fees once paid are not refundable. Please keep this receipt. · Powered by Maakit</div></div>`;
  printHTML('Receipt ' + p.no, copy('Parent copy') + copy('Office copy'));
}

// ---- Dues list (everyone in the office; Chairman can only look)
function vFDues() {
  const w = ofReady('Fee Dues'); if (w) return w;
  const c = P.c || '', rows = OF.students.filter(s => (!c || s.cls === c) && (s.dues.due > 0 || s.dues.missing)).sort((a, b) => b.dues.due - a.dues.due);
  const total = rows.reduce((a, s) => a + Math.max(0, s.dues.due), 0);
  return head('Fee Dues', `As on ${fmtDate(OF.today)} · monthly fee counted up to this month`, `<button onclick="printHTML('Dues',printHeader('Fee dues as on ${fmtDate(OF.today)}')+document.getElementById('of-dues').outerHTML+PRINT_FOOT)" class="btn btn-l"><i class="fa-solid fa-print"></i> Print</button><button onclick="ofLoad(true)" class="btn btn-l"><i class="fa-solid fa-rotate"></i></button>`) + ofSummary() + `
    <div class="card overflow-x-auto"><div class="flex flex-wrap items-center gap-2 mb-2"><span class="lbl mb-0">Class</span><select class="inp w-auto" onchange="go('fdues',{c:this.value})"><option value="">All</option>${CLASS_NAMES.map(x => `<option ${x === c ? 'selected' : ''}>${x}</option>`).join('')}</select>
      <span class="text-sm text-slate-600">${rows.length} student(s) · ${rs(total)}</span></div>
      <table class="tbl" id="of-dues"><tr><th>Student</th><th>Class</th><th>Mobile</th><th class="text-right">Monthly</th><th class="text-right">Paid</th><th class="text-right">Due</th></tr>
      ${rows.map(s => `<tr><td class="font-semibold">${esc(s.name)}${s.father ? `<br><span class="text-xs text-slate-500 font-normal">${esc(s.father)}</span>` : ''}</td><td>${esc(s.cls)}</td><td>${esc(s.phone)}</td>
        <td class="text-right">${s.dues.missing ? '<span class="text-amber-700">Not set</span>' : s.dues.free ? 'Free' : rs(s.dues.monthly)}</td><td class="text-right">${rs(s.dues.paid)}</td><td class="text-right font-semibold text-rose-700">${rs(Math.max(0, s.dues.due))}</td></tr>`).join('') || '<tr><td colspan="6" class="text-slate-500">Nothing due.</td></tr>'}</table></div>`;
}

// ---- Dues calling (Reception: Shiva Rai)
function vFCalls() {
  const w = ofReady('Dues Calling'); if (w) return w;
  const tab = P.tab || 'today', today = OF.today, c = P.c || '';
  const fams = ofFamilies().filter(f => f.due > 0 && (!c || f.kids.some(k => k.cls === c)));
  const promised = f => f.last && f.last.promise && f.last.outcome !== 'Wrong number' ? f.last.promise : null;
  const due = fams.filter(f => promised(f) && promised(f) <= today);
  const fresh = fams.filter(f => !f.last || (!promised(f) && f.last.date < addDays(today, -6)));
  const lists = { today: due.sort((a, b) => b.due - a.due), new: fresh.sort((a, b) => b.due - a.due), all: fams.sort((a, b) => b.due - a.due) };
  const tabs = [['today', `Follow-up today (${due.length})`], ['new', `Not called this week (${fresh.length})`], ['all', `All dues (${fams.length})`], ['log', 'Call log']]
    .map(([k, l]) => `<button onclick="go('fcalls',{tab:'${k}',c:${jsq(c)}})" class="btn btn-sm ${tab === k ? 'btn-p' : 'btn-l'}">${l}</button>`).join('');
  const body = tab === 'log' ? ofCallLog() : (lists[tab].length ? lists[tab].map(ofFamCard).join('') : '<div class="card text-sm text-slate-500">Nobody in this list. 🎉</div>');
  return head('Dues Calling', `${esc(OF.names.reception)} (Reception) · call, then save what they said`, `<button onclick="ofLoad(true)" class="btn btn-l"><i class="fa-solid fa-rotate"></i> Refresh</button>`) + `
    <div class="flex flex-wrap gap-2 items-center">${tabs}<select class="inp w-auto py-1 text-sm" onchange="go('fcalls',{tab:'${tab}',c:this.value})"><option value="">All classes</option>${CLASS_NAMES.map(x => `<option ${x === c ? 'selected' : ''}>${x}</option>`).join('')}</select></div>
    <div class="space-y-3">${body}</div>`;
}
function ofReminder(f) {
  return `नमस्ते 🙏 ${OF.school} से ${OF.names.reception} बोल रही हूँ।\n` + f.kids.filter(k => k.dues.due > 0).map(k => `${k.name} (Class ${k.cls}) की फीस ₹${k.dues.due.toLocaleString('en-IN')} बाकी है।`).join('\n')
    + `\nकृपया जल्द से जल्द विद्यालय कार्यालय में जमा करें। रसीद ज़रूर लें। धन्यवाद।`;
}
function ofFamCard(f) {
  const open = OFOPEN === f.key, ph = String(f.phone || '').replace(/\D/g, '').slice(-10), id = 'oc-' + f.key.replace(/[^a-z0-9]/gi, '');
  const L = f.last;
  return `<div class="card"><div class="flex flex-col sm:flex-row sm:items-start justify-between gap-2">
      <div><p class="font-semibold">${ofKidLine(f)}</p><p class="text-xs text-slate-500">${f.father ? esc(f.father) + ' · ' : ''}${ph ? esc(f.phone) : '<span class="text-rose-700">No mobile number</span>'}</p>
        ${L ? `<p class="text-xs mt-1"><span class="pill ${L.outcome === 'Promised to pay' ? 'bg-emerald-100 text-emerald-800' : L.outcome === 'Wrong number' ? 'bg-rose-100 text-rose-800' : 'bg-slate-100 text-slate-700'}">${esc(L.outcome)}${L.promise ? ' · ' + fmtDate(L.promise) : ''}</span> <span class="text-slate-500">${fmtDate(L.date)} by ${esc(L.by)}${L.note ? ' — ' + esc(L.note) : ''}</span></p>` : '<p class="text-xs text-slate-500 mt-1">Not called yet</p>'}</div>
      <p class="text-xl font-semibold text-rose-700 whitespace-nowrap">${rs(f.due)}</p></div>
    <div class="flex flex-wrap gap-2 mt-3">${ph ? `<a href="tel:+91${ph}" class="btn btn-sm btn-p"><i class="fa-solid fa-phone"></i> Call</a><a target="_blank" href="${waLink(ph, ofReminder(f))}" class="btn btn-sm btn-wa"><i class="fa-brands fa-whatsapp"></i> Reminder</a>` : ''}
      ${OF.can.call ? `<button onclick="OFOPEN=${open ? 'null' : jsq(f.key)};render()" class="btn btn-sm btn-l">${open ? 'Close' : 'Save call result'}</button>` : ''}</div>
    ${open ? `<div class="mt-3 p-3 rounded-xl bg-slate-50 border border-slate-200 grid grid-cols-1 sm:grid-cols-3 gap-2">
      <div><label class="lbl">What happened</label><select id="${id}-o" class="inp">${OF.outcomes.map(o => `<option>${esc(o)}</option>`).join('')}</select></div>
      <div><label class="lbl">Date they gave</label><input id="${id}-d" type="date" class="inp" min="${OF.today}" value="${addDays(OF.today, 3)}"></div>
      <div><label class="lbl">Note</label><input id="${id}-n" class="inp" maxlength="200" placeholder="optional"></div>
      <button onclick="ofCall(${jsq(f.kids[0].id)},'${id}')" class="btn btn-g sm:col-span-3">Save</button></div>` : ''}
    ${f.calls.length > 1 ? `<details class="mt-2 text-xs"><summary class="cursor-pointer text-slate-500">Earlier calls (${f.calls.length - 1})</summary>${f.calls.slice(1, 8).map(c => `<p>${fmtDate(c.date)} · ${esc(c.outcome)}${c.promise ? ' · ' + fmtDate(c.promise) : ''} · ${esc(c.by)}${c.note ? ' — ' + esc(c.note) : ''}</p>`).join('')}</details>` : ''}</div>`;
}
function ofCallLog() {
  if (!OF.calls.length) return '<div class="card text-sm text-slate-500">No calls saved yet.</div>';
  const name = sids => sids.map(i => { const s = OF.students.find(x => x.id === i); return s ? esc(s.name) + ' (' + esc(s.cls) + ')' : ''; }).join(', ');
  return `<div class="card overflow-x-auto"><table class="tbl"><tr><th>Date</th><th>Family</th><th>Result</th><th>By</th></tr>${OF.calls.slice(0, 150).map(c => `<tr><td class="whitespace-nowrap">${fmtDate(c.date)}</td><td>${name(c.sids)}</td><td>${esc(c.outcome)}${c.promise ? ' · ' + fmtDate(c.promise) : ''}${c.note ? `<br><span class="text-xs text-slate-500">${esc(c.note)}</span>` : ''}</td><td class="text-xs">${esc(c.by)}</td></tr>`).join('')}</table></div>`;
}
async function ofCall(sid, id) {
  if (OFBUSY) return;
  const o = document.getElementById(id + '-o').value, needDate = ['Promised to pay', 'Call back later', 'Will visit school'].includes(o);
  OFBUSY = true;
  try { await oapi('call', { sid, outcome: o, promise: needDate ? document.getElementById(id + '-d').value : '', note: document.getElementById(id + '-n').value.trim() }); OFOPEN = null; toast('Saved'); }
  catch (e) { toast(e.message, 'r'); } finally { OFBUSY = false; }
  ofLoad(true);
}

// ---- Receipts (day book)
function vFReceipts() {
  const w = ofReady('Receipts'); if (w) return w;
  const date = P.date || OF.today, month = !!P.month;
  const rows = OF.payments.filter(p => month ? p.date.slice(0, 7) === date.slice(0, 7) : p.date === date);
  const live = rows.filter(p => !p.cancelled), modes = {}; live.forEach(p => modes[p.mode] = (modes[p.mode] || 0) + p.amt);
  return head('Receipts', month ? 'Whole month' : fmtDate(date), dateInput('of-rd', date, `go('freceipts',{date:this.value${month ? ',month:1' : ''}})`)
      + `<button onclick="go('freceipts',{date:'${date}'${month ? '' : ',month:1'}})" class="btn btn-l">${month ? 'One day' : 'Whole month'}</button><button onclick="printHTML('Receipts',printHeader('Fee collection — ${month ? date.slice(0, 7) : fmtDate(date)}')+document.getElementById('of-rc').outerHTML+PRINT_FOOT)" class="btn btn-l"><i class="fa-solid fa-print"></i> Print</button>`) + `
    <div class="card flex flex-wrap gap-3 items-center"><b class="text-lg">${rs(live.reduce((a, p) => a + p.amt, 0))}</b><span class="text-sm text-slate-600">${live.length} receipt(s)${Object.keys(modes).length ? ' · ' + Object.entries(modes).map(([m, v]) => esc(m) + ' ' + rs(v)).join(' · ') : ''}</span></div>
    <div class="card overflow-x-auto"><table class="tbl" id="of-rc"><tr><th>No.</th><th>Date</th><th>Student</th><th class="text-right">Amount</th><th>Mode</th><th>By</th><th class="noprint"></th></tr>
      ${rows.map(p => `<tr class="${p.cancelled ? 'text-slate-400' : ''}"><td>${p.no}</td><td class="whitespace-nowrap">${fmtDate(p.date)}</td><td>${esc(p.name)} <span class="text-xs">(${esc(p.cls)})</span>${p.cancelled ? `<br><span class="text-xs text-rose-700">Cancelled: ${esc(p.cancelReason || '')}</span>` : ''}</td>
        <td class="text-right ${p.cancelled ? 'line-through' : 'font-semibold'}">${rs(p.amt)}</td><td>${esc(p.mode)}${p.ref ? `<br><span class="text-xs">${esc(p.ref)}</span>` : ''}</td><td class="text-xs">${esc(p.by)}</td>
        <td class="noprint whitespace-nowrap"><button onclick="ofPrint(${p.no})" class="btn btn-sm btn-l"><i class="fa-solid fa-print"></i></button>${OF.can.cancel && !p.cancelled ? ` <button onclick="ofCancel(${p.no})" class="btn btn-sm btn-r">Cancel</button>` : ''}</td></tr>`).join('') || '<tr><td colspan="7" class="text-slate-500">No receipts.</td></tr>'}</table></div>`;
}
async function ofCancel(no) {
  const reason = prompt('Why cancel receipt #' + no + '? (this is kept in the activity log)'); if (reason === null) return;
  if (!reason.trim()) return toast('Write a reason', 'a');
  try { await oapi('cancel', { no, reason: reason.trim() }); toast('Receipt cancelled'); } catch (e) { toast(e.message, 'r'); }
  ofLoad(true);
}

// ---- Parent / student: dues and receipts for their own children
let PFD = null;
async function loadPFees() { try { PFD = await oapi('mine'); } catch (e) { PFD = { error: e.message }; } if (view === 'pfees') render(); }
function pFeeDues() {
  if (!PFD) { loadPFees(); return '<div class="card text-sm text-slate-500">Loading your fee account…</div>'; }
  if (PFD.error) return `<div class="card text-sm">${esc(PFD.error)}</div>`;
  const kids = PFD.kids, tot = kids.reduce((a, k) => a + k.dues.due, 0);
  return `<div class="card"><button class="btn btn-l" onclick="go('finance')">Open detailed fee ledger & receipts</button></div><div class="card"><div class="flex justify-between items-center"><h3 class="h3">Fee account</h3><b class="text-xl ${tot > 0 ? 'text-rose-700' : 'text-leaf'}">${tot > 0 ? rs(tot) + ' due' : 'All clear'}</b></div>
      ${kids.map(k => `<div class="mt-3"><p class="font-semibold">${esc(k.name)} · Class ${esc(k.cls)}</p>${ofBreak(k)}</div>`).join('')}
      <p class="text-xs text-slate-500 mt-2">Monthly fee counted from ${fmtDate(PFD.sessionStart)} up to this month. If something looks wrong, please talk to the school office.</p></div>
    ${PFD.receipts.length ? `<div class="card"><h3 class="h3">Receipts</h3><table class="tbl mt-2 text-sm"><tr><th>No.</th><th>Date</th><th>Child</th><th class="text-right">Amount</th><th>Mode</th></tr>${PFD.receipts.map(p => `<tr><td>${p.no}</td><td>${fmtDate(p.date)}</td><td>${esc(p.name)}</td><td class="text-right">${rs(p.amt)}</td><td>${esc(p.mode)}</td></tr>`).join('')}</table></div>` : ''}`;
}

// ---------------------------------------------------------------- STAFF SALARY (school/salary_api.php — Principal + Accountant; teachers see their own paid slips)
let SAL = null, SALBUSY = false, SALOPEN = null;
async function salapi(a, body, q) {
  const url = 'salary_api.php?a=' + a + (q ? '&' + new URLSearchParams(q) : '');
  const opt = { method: body ? 'POST' : 'GET', headers: { 'X-School-Token': TOKEN || '' } };
  if (body) { opt.headers['Content-Type'] = 'application/json'; opt.body = JSON.stringify(body); }
  let r, j = null;
  try { r = await fetch(url, opt); } catch (e) { throw new Error('No internet connection. Please try again.'); }
  try { j = await r.json(); } catch (e) {}
  if (r.status === 401) { forceLogout(); throw new Error('Please log in again.'); }
  if (!r.ok || !j || !j.ok) throw new Error((j && j.error) || 'Server problem. Please try again.');
  return j;
}
async function salLoad(ym) {
  if (SALBUSY) return; SALBUSY = true;
  try { SAL = U.role === 'teacher' ? await salapi('mine') : await salapi('sheet', null, { ym }); } catch (e) { SAL = { error: e.message, ym }; } finally { SALBUSY = false; }
  if (view === 'salary') { const y = window.scrollY; render(); window.scrollTo(0, y); }
}
const ymLong = ym => { const [y, m] = ym.split('-').map(Number); return new Date(y, m - 1, 1).toLocaleString('en-IN', { month: 'long', year: 'numeric' }); };
const dd = n => (Math.round(n * 10) / 10).toString();
function vSalary() {
  if (U.role === 'teacher') return vMySalary();
  const ym = P.ym || todayISO().slice(0, 7);
  if (!SAL || (SAL.ym !== ym && !SAL.error)) { salLoad(ym); return head('Staff Salary') + '<div class="card text-sm text-slate-500">Loading salary sheet…</div>'; }
  if (SAL.error) return head('Staff Salary') + `<div class="card text-sm text-rose-700">${esc(SAL.error)} <button onclick="SAL=null;render()" class="btn btn-sm btn-l ml-2">Try again</button></div>`;
  const rows = SAL.rows, set = rows.filter(r => r.status !== 'Not set'), paid = rows.filter(r => r.status === 'Paid');
  const tot = k => set.reduce((a, r) => a + (+r.calc[k] || 0), 0);
  const months = []; for (let i = -3; i <= 1; i++) months.push(ymAdd(todayISO().slice(0, 7), i));
  const statusPill = r => r.status === 'Paid' ? '<span class="pill bg-emerald-100 text-emerald-800">Paid</span>' : r.status === 'Due' ? '<span class="pill bg-amber-100 text-amber-800">Due</span>' : '<span class="pill bg-slate-100 text-slate-600">Salary not set</span>';
  const card = r => { const s = r.staff, c = r.calc, open = SALOPEN === s.id, id = s.id.replace(/[^a-z0-9]/gi, '');
    const why = c.manual ? 'entered by office' : c.auto ? `${dd(c.auto.absent)} absent${c.auto.half ? ', ' + c.auto.half + ' half day' : ''}${c.auto.leave ? ', ' + c.auto.leave + ' leave' : ''}` : 'enter days';
    return `<div class="card ${s.active ? '' : 'opacity-60'}"><div class="flex flex-wrap items-start justify-between gap-2">
        <div class="min-w-0"><p class="font-bold text-ink">${esc(s.name)} ${statusPill(r)}</p><p class="text-xs text-slate-500">${esc(s.role)}${s.teaching ? '' : ' · non-teaching'}${s.active ? '' : ' · inactive'}</p></div>
        <div class="text-right"><p class="text-xs text-slate-500">Net pay</p><p class="text-xl font-extrabold text-ink">${r.status === 'Not set' ? '—' : rs(c.net)}</p></div></div>
      ${r.status === 'Not set' ? '' : `<div class="flex flex-wrap gap-2 mt-3 text-xs">
        <span class="pill bg-slate-100 text-slate-700">Salary ${rs(c.gross)}</span><span class="pill bg-slate-100 text-slate-700">Absent ${dd(c.absent)} (${why})</span>
        <span class="pill bg-slate-100 text-slate-700">Paid leave ${dd(c.paidLeave)}</span>${c.deduction ? `<span class="pill bg-rose-50 text-rose-700">Cut ${rs(c.deduction)} for ${dd(c.unpaid)} day(s)</span>` : ''}
        ${c.advance ? `<span class="pill bg-rose-50 text-rose-700">Advance −${rs(c.advance)}</span>` : ''}${c.bonus ? `<span class="pill bg-emerald-50 text-emerald-700">Bonus +${rs(c.bonus)}</span>` : ''}${c.note ? `<span class="pill bg-slate-100 text-slate-600">${esc(c.note)}</span>` : ''}</div>`}
      ${r.pay ? `<p class="text-xs text-slate-500 mt-2">Paid ${fmtDate(r.pay.paidOn)} by ${esc(r.pay.mode)}${r.pay.ref ? ' (' + esc(r.pay.ref) + ')' : ''} · ${esc(r.pay.by)}</p>` : ''}
      <div class="flex flex-wrap gap-2 mt-3">
        ${r.status === 'Due' ? `<button onclick="SALOPEN=${open ? 'null' : jsq(s.id)};render()" class="btn btn-sm ${open ? 'btn-l' : 'btn-p'}">${open ? 'Close' : 'Adjust & pay'}</button>` : ''}
        ${r.status === 'Paid' ? `<button onclick="salSlip(${jsq(s.id)})" class="btn btn-sm btn-l"><i class="fa-solid fa-print"></i> Salary slip</button>${SAL.can.unpay ? `<button onclick="salUnpay(${jsq(s.id)})" class="btn btn-sm btn-l">Reopen</button>` : ''}` : ''}
        ${SAL.can.staff && r.status !== 'Paid' ? `<button onclick="salStaffEdit(${jsq(s.id)})" class="btn btn-sm btn-l">${r.status === 'Not set' ? 'Set salary' : 'Edit salary'}</button>` : ''}</div>
      ${open ? `<div class="mt-3 p-3 rounded-2xl bg-slate-50 border border-slate-200 space-y-3"><div class="grid grid-cols-2 gap-2">
        <div><label class="lbl">Absent days</label><input id="sa-${id}-ab" type="number" step="0.5" min="0" class="inp" value="${c.manual ? c.absent : ''}" placeholder="${s.teaching ? 'Auto: ' + dd(c.auto ? c.auto.total : 0) : 'Days'}"></div>
        <div><label class="lbl">Advance (₹)</label><input id="sa-${id}-adv" type="number" min="0" class="inp" value="${c.advance || ''}"></div>
        <div><label class="lbl">Bonus / other (₹)</label><input id="sa-${id}-bon" type="number" min="0" class="inp" value="${c.bonus || ''}"></div>
        <div><label class="lbl">Note</label><input id="sa-${id}-note" class="inp" maxlength="200" value="${esc(c.note || '')}"></div>
        <button onclick="salAdjust(${jsq(s.id)},'${id}')" class="btn btn-l col-span-2">Save changes</button></div>
        <div class="grid grid-cols-2 gap-2 pt-3 border-t border-slate-200"><div><label class="lbl">Paid by</label><select id="sa-${id}-mode" class="inp">${SAL.modes.map(m => `<option>${esc(m)}</option>`).join('')}</select></div>
        <div><label class="lbl">Ref. / cheque no.</label><input id="sa-${id}-ref" class="inp" maxlength="60"></div>
        <div><label class="lbl">Paid on</label><input id="sa-${id}-date" type="date" class="inp" value="${SAL.today}" max="${SAL.today}"></div>
        <button onclick="salPay(${jsq(s.id)},'${id}')" class="btn btn-g self-end"><i class="fa-solid fa-check"></i> Mark paid ${rs(c.net)}</button></div></div>` : ''}</div>`; };
  return head('Staff Salary', `${ymLong(ym)} · ${SAL.workDays} working days (Sundays and holidays left out). Absences come from Staff Attendance and approved leave.`,
      `<select class="inp w-auto" onchange="SAL=null;SALOPEN=null;go('salary',{ym:this.value})">${months.map(m => `<option value="${m}" ${m === ym ? 'selected' : ''}>${ymLong(m)}</option>`).join('')}</select>
       <button onclick="salRegister()" class="btn btn-l"><i class="fa-solid fa-print"></i> Register</button>${SAL.can.staff ? '<button onclick="salStaffEdit(\'\')" class="btn btn-p"><i class="fa-solid fa-plus"></i> Add staff</button>' : ''}`) + `
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
      <div class="kpi kpi-peach"><span class="kpi-ic"><i class="fa-solid fa-wallet"></i></span><span class="kpi-val">${rs(tot('gross'))}</span><span class="kpi-lbl">Total salary</span><span class="kpi-sub">${set.length} staff with salary set</span></div>
      <div class="kpi kpi-lav"><span class="kpi-ic"><i class="fa-solid fa-user-minus"></i></span><span class="kpi-val">${rs(tot('deduction'))}</span><span class="kpi-lbl">Cut for absence</span><span class="kpi-sub">advance ${rs(tot('advance'))} · bonus ${rs(tot('bonus'))}</span></div>
      <div class="kpi kpi-mint"><span class="kpi-ic"><i class="fa-solid fa-money-bill-wave"></i></span><span class="kpi-val">${rs(tot('net'))}</span><span class="kpi-lbl">Net to pay</span><span class="kpi-sub">for ${ymLong(ym)}</span></div>
      <div class="kpi ${paid.length < set.length ? 'kpi-warn' : 'kpi-lime'}"><span class="kpi-ic"><i class="fa-solid fa-check-double"></i></span><span class="kpi-val">${paid.length} <small>/ ${set.length}</small></span><span class="kpi-lbl">Paid</span><span class="kpi-sub">${rs(paid.reduce((a, r) => a + r.calc.net, 0))} paid so far</span></div></div>
    ${rows.some(r => r.status === 'Not set') ? `<div class="card text-sm bg-amber-50 border-amber-200">${rows.filter(r => r.status === 'Not set').length} staff member(s) have no salary set yet${SAL.can.staff ? ' — press <b>Set salary</b> on their card.' : '. Ask the Principal to set it.'}</div>` : ''}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-3 items-start">${rows.map(card).join('')}</div>
    <p class="text-xs text-slate-500">Only the Principal and the Accountant can see this page. Teachers see only their own paid salary slips.</p>`;
}
async function salStaffEdit(id) {
  const s = id ? SAL.rows.find(r => r.staff.id === id).staff : { name: '', role: '', monthly: null, paidLeave: 1, active: true, teaching: false };
  let name = s.name, role = s.role;
  if (!s.teaching) { name = prompt('Name (e.g. Ramesh — driver)', s.name); if (name === null) return; role = prompt('Job (Driver, Peon, Guard, Accountant…)', s.role || ''); if (role === null) return; }
  const m = prompt(`Monthly salary of ${name} (₹, blank = not set)`, s.monthly ?? ''); if (m === null) return;
  const pl = prompt('Paid leave days per month (absences up to this are not cut)', s.paidLeave ?? 1); if (pl === null) return;
  const active = id ? confirm(`Keep ${name} active?\nOK = active, Cancel = left the school (inactive)`) : true;
  try { await salapi('staff', { id, name, role, monthly: m.trim() === '' ? null : +m, paidLeave: +pl, active }); toast('Saved'); } catch (e) { toast(e.message, 'r'); }
  SAL = null; render();
}
async function salAdjust(id, k) {
  const g = x => document.getElementById(`sa-${k}-${x}`).value.trim();
  try { await salapi('adjust', { ym: SAL.ym, id, manualAbsent: g('ab'), advance: g('adv'), bonus: g('bon'), note: g('note') }); toast('Saved'); } catch (e) { return toast(e.message, 'r'); }
  SAL = null; render();
}
async function salPay(id, k) {
  const g = x => document.getElementById(`sa-${k}-${x}`).value.trim(), r = SAL.rows.find(x => x.staff.id === id);
  if (g('ab') !== (r.calc.manual ? String(r.calc.absent) : '') || +g('adv') !== r.calc.advance || +g('bon') !== r.calc.bonus) return toast('Press "Save changes" first, then mark paid.', 'a');
  if (!confirm(`Mark ${r.staff.name}'s salary for ${ymLong(SAL.ym)} as PAID: ${rs(r.calc.net)} by ${g('mode')}?`)) return;
  try { await salapi('pay', { ym: SAL.ym, id, mode: g('mode'), ref: g('ref'), date: g('date') }); toast('Marked paid'); SALOPEN = null; } catch (e) { toast(e.message, 'r'); }
  const ym = SAL.ym; SAL = null; await salLoad(ym); const p = SAL.rows && SAL.rows.find(x => x.staff.id === id); if (p && p.status === 'Paid') salSlip(id);
}
async function salUnpay(id) {
  const reason = prompt('Why reopen this paid salary? (kept in the activity log)'); if (reason === null) return;
  try { await salapi('unpay', { ym: SAL.ym, id, reason }); toast('Reopened'); } catch (e) { toast(e.message, 'r'); }
  SAL = null; render();
}
function salSlipHTML(name, role, ym, c, pay) {
  const row = (a, b) => `<tr><td style="padding:6px 8px;border-bottom:1px solid #e5e7eb">${a}</td><td style="padding:6px 8px;border-bottom:1px solid #e5e7eb;text-align:right">${b}</td></tr>`;
  return `<div style="border:1.5px solid #0B1F3A;border-radius:14px;padding:16px;margin-bottom:16px;page-break-inside:avoid">
    <div style="display:flex;justify-content:space-between;align-items:baseline"><b style="font-size:16px">${esc(S.settings.school)}, ${esc(S.settings.place || 'Bhadohi')}</b><span>SALARY SLIP — ${ymLong(ym)}</span></div>
    <p style="margin:8px 0"><b>${esc(name)}</b> · ${esc(role)}</p>
    <table style="width:100%;border-collapse:collapse;font-size:13px">${row('Monthly salary', rs(c.gross))}${row('Working days', c.workDays)}${row('Absent days', dd(c.absent))}${row('Paid leave allowed', dd(c.paidLeave))}
      ${row(`Cut for ${dd(c.unpaid)} unpaid day(s)`, '− ' + rs(c.deduction))}${c.advance ? row('Advance recovered', '− ' + rs(c.advance)) : ''}${c.bonus ? row('Bonus / other', '+ ' + rs(c.bonus)) : ''}
      <tr><td style="padding:8px"><b>Net paid</b></td><td style="padding:8px;text-align:right"><b style="font-size:16px">${rs(c.net)}</b></td></tr></table>
    ${pay ? `<p style="font-size:12px;margin-top:6px">Paid on ${fmtDate(pay.paidOn)} by ${esc(pay.mode)}${pay.ref ? ' (' + esc(pay.ref) + ')' : ''}</p>` : ''}
    <div style="display:flex;justify-content:space-between;margin-top:26px;font-size:12px"><span>Received by ______________</span><span>Principal ______________</span></div>
    <div style="font-size:10px;color:#666;margin-top:6px">Powered by Maakit</div></div>`;
}
function salSlip(id) { const r = SAL.rows.find(x => x.staff.id === id); if (!r || r.status !== 'Paid') return; printHTML('Salary slip', salSlipHTML(r.staff.name, r.staff.role, SAL.ym, r.calc, r.pay)); }
function salRegister() {
  const rows = SAL.rows.filter(r => r.status !== 'Not set');
  printHTML('Salary register', printHeader('Salary register — ' + ymLong(SAL.ym) + ' · ' + SAL.workDays + ' working days') + `<table class="t"><tr><th>Name</th><th>Job</th><th>Salary</th><th>Absent</th><th>Cut</th><th>Advance</th><th>Bonus</th><th>Net</th><th>Paid on</th><th>Sign</th></tr>
    ${rows.map(r => `<tr><td>${esc(r.staff.name)}</td><td>${esc(r.staff.role)}</td><td>${rs(r.calc.gross)}</td><td>${dd(r.calc.absent)}</td><td>${rs(r.calc.deduction)}</td><td>${rs(r.calc.advance)}</td><td>${rs(r.calc.bonus)}</td><td><b>${rs(r.calc.net)}</b></td><td>${r.pay ? fmtDate(r.pay.paidOn) + ' ' + esc(r.pay.mode) : 'Due'}</td><td></td></tr>`).join('')}
    <tr><th colspan="7">Total</th><th>${rs(rows.reduce((a, r) => a + r.calc.net, 0))}</th><th colspan="2"></th></tr></table>` + PRINT_FOOT);
  log('Salary', 'Printed salary register for ' + SAL.ym);
}
function vMySalary() {
  if (!SAL || SAL.error && !SAL.retry) { if (!SAL) { salLoad(); return head('My Salary') + '<div class="card text-sm text-slate-500">Loading…</div>'; } }
  if (SAL.error) return head('My Salary') + `<div class="card text-sm">${esc(SAL.error)}</div>`;
  return head('My Salary', 'Your salary slips appear here once the office marks the month as paid.') +
    (SAL.slips && SAL.slips.length ? SAL.slips.map(s => `<div class="card flex flex-wrap items-center justify-between gap-3"><div><p class="font-bold text-ink">${ymLong(s.ym)}</p><p class="text-xs text-slate-500">Paid ${fmtDate(s.paidOn)} · ${esc(s.mode)} · ${dd(s.calc.absent)} absent day(s)</p></div>
      <div class="flex items-center gap-3"><b class="text-xl">${rs(s.calc.net)}</b><button onclick="printHTML('Salary slip',salSlipHTML(${jsq(s.name)},${jsq(s.role)},'${s.ym}',SAL.slips.find(x=>x.ym==='${s.ym}').calc,SAL.slips.find(x=>x.ym==='${s.ym}')))" class="btn btn-sm btn-l"><i class="fa-solid fa-print"></i> Slip</button></div></div>`).join('')
      : '<div class="card text-sm text-slate-500">No paid salary slips yet.</div>');
}

// ---------------------------------------------------------------- CHARTS (small SVG, no library)
// One hue per job; values written on the marks (so colour is never the only carrier); <title> = hover tooltip.
const CH = { violet: '#7C5CE6', orange: '#E8920E', teal: '#14A9BC', grid: '#ECECF1', ink: '#1C1C1E', muted: '#6E6E78' };
function svgBars(items, o = {}) {
  const W = 560, H = o.h || 200, padB = 28, padT = 22, n = Math.max(1, items.length), max = Math.max(1, ...items.map(i => +i.value || 0)) * 1.12;
  const bw = Math.min(42, (W / n) * .55), gap = W / n;
  const grid = [0, .5, 1].map(f => { const y = padT + (H - padT - padB) * (1 - f); return `<line x1="0" x2="${W}" y1="${y}" y2="${y}" stroke="${CH.grid}" stroke-width="1"/>`; }).join('');
  const bars = items.map((it, i) => { const v = +it.value || 0, h = Math.max(v ? 3 : 0, (H - padT - padB) * v / max), x = gap * i + (gap - bw) / 2, y = H - padB - h;
    return `<g><title>${esc(it.label)}: ${esc(it.display ?? v)}</title><rect x="${x - 6}" y="${padT}" width="${bw + 12}" height="${H - padT - padB}" fill="transparent"/>
      <path d="M${x},${H - padB} V${y + Math.min(4, h)} q0,-4 4,-4 h${bw - 8} q4,0 4,4 V${H - padB} Z" fill="${it.color || o.color || CH.violet}"/>
      <text x="${x + bw / 2}" y="${y - 6}" text-anchor="middle" font-size="11" font-weight="600" fill="${CH.ink}">${esc(it.display ?? v)}</text>
      <text x="${x + bw / 2}" y="${H - 9}" text-anchor="middle" font-size="11" fill="${CH.muted}">${esc(it.label)}</text></g>`; }).join('');
  return `<svg viewBox="0 0 ${W} ${H}" class="w-full h-auto" style="max-height:${Math.round(H * 1.3)}px" role="img" aria-label="${esc(o.title || 'chart')}">${grid}${bars}</svg>`;
}
function svgGroupBars(labels, series, o = {}) {
  const W = 560, H = o.h || 210, padB = 28, padT = 22, n = Math.max(1, labels.length), gap = W / n, k = series.length;
  const max = Math.max(1, ...series.flatMap(s => s.values.map(v => +v || 0))) * 1.15, bw = Math.min(22, gap * .7 / k);
  const grid = [0, .5, 1].map(f => { const y = padT + (H - padT - padB) * (1 - f); return `<line x1="0" x2="${W}" y1="${y}" y2="${y}" stroke="${CH.grid}"/>`; }).join('');
  const g = labels.map((lb, i) => { const x0 = gap * i + (gap - (bw * k + 2 * (k - 1))) / 2;
    return series.map((s, j) => { const v = +s.values[i] || 0, h = Math.max(v ? 3 : 0, (H - padT - padB) * v / max), x = x0 + j * (bw + 2), y = H - padB - h;
      return `<g><title>${esc(lb)} · ${esc(s.name)}: ${esc(s.fmt ? s.fmt(v) : v)}</title><path d="M${x},${H - padB} V${y + Math.min(4, h)} q0,-4 4,-4 h${bw - 8} q4,0 4,4 V${H - padB} Z" fill="${s.color}"/></g>`; }).join('')
      + `<text x="${gap * i + gap / 2}" y="${H - 9}" text-anchor="middle" font-size="11" fill="${CH.muted}">${esc(lb)}</text>`; }).join('');
  return `<div class="flex flex-wrap gap-4 mb-2">${series.map(s => `<span class="legend"><i style="background:${s.color}"></i>${esc(s.name)}</span>`).join('')}</div>
    <svg viewBox="0 0 ${W} ${H}" class="w-full h-auto" style="max-height:${Math.round(H * 1.3)}px" role="img" aria-label="${esc(o.title || 'chart')}">${grid}${g}</svg>`;
}
function svgLine(labels, values, o = {}) {
  const W = 560, H = o.h || 180, padB = 26, padT = 20, padX = 24, n = labels.length, lo = o.min ?? 0, hi = o.max ?? Math.max(1, ...values.filter(v => v != null));
  const X = i => padX + (n < 2 ? (W - 2 * padX) / 2 : i * (W - 2 * padX) / (n - 1)), Y = v => padT + (H - padT - padB) * (1 - (v - lo) / Math.max(1e-9, hi - lo));
  const pts = values.map((v, i) => v == null ? null : [X(i), Y(v)]), seg = []; let cur = [];
  pts.forEach(p => { if (p) cur.push(p); else if (cur.length) { seg.push(cur); cur = []; } }); if (cur.length) seg.push(cur);
  const path = seg.map(s => 'M' + s.map(p => p[0].toFixed(1) + ',' + p[1].toFixed(1)).join(' L')).join(' ');
  const grid = [lo, (lo + hi) / 2, hi].map(v => `<line x1="0" x2="${W}" y1="${Y(v)}" y2="${Y(v)}" stroke="${CH.grid}"/><text x="${W}" y="${Y(v) - 4}" text-anchor="end" font-size="10" fill="${CH.muted}">${Math.round(v)}${o.unit || ''}</text>`).join('');
  const dots = pts.map((p, i) => p ? `<g><title>${esc(labels[i])}: ${values[i]}${o.unit || ''}</title><circle cx="${p[0]}" cy="${p[1]}" r="12" fill="transparent"/><circle cx="${p[0]}" cy="${p[1]}" r="4" fill="#fff" stroke="${o.color || CH.violet}" stroke-width="2"/></g>` : '').join('');
  const xl = labels.map((l, i) => (n <= 7 || (n - 1 - i) % Math.ceil(n / 6) === 0) ? `<text x="${X(i)}" y="${H - 7}" text-anchor="${i === 0 && n > 1 ? 'start' : i === n - 1 && n > 1 ? 'end' : 'middle'}" font-size="10.5" fill="${CH.muted}">${esc(l)}</text>` : '').join('');
  const last = pts.map((p, i) => [p, i]).filter(x => x[0]).pop();
  return `<svg viewBox="0 0 ${W} ${H}" class="w-full h-auto" style="max-height:${Math.round(H * 1.3)}px" role="img" aria-label="${esc(o.title || 'chart')}">${grid}<path d="${path}" fill="none" stroke="${o.color || CH.violet}" stroke-width="2" stroke-linejoin="round" stroke-linecap="round"/>${dots}
    ${last ? `<text x="${Math.min(W - 20, last[0][0])}" y="${last[0][1] - 10}" text-anchor="middle" font-size="11" font-weight="700" fill="${CH.ink}">${values[last[1]]}${o.unit || ''}</text>` : ''}${xl}</svg>`;
}

// ---------------------------------------------------------------- ATTENDANCE HISTORY (for profiles, admin dashboard) — profile_api.php?a=att
let HIST_FROM = null, HISTBUSY = false;
async function pfapi(a, body, q) {
  const url = 'profile_api.php?a=' + a + (q ? '&' + new URLSearchParams(q) : '');
  const opt = { method: body ? 'POST' : 'GET', headers: { 'X-School-Token': TOKEN || '' } };
  if (body) { opt.headers['Content-Type'] = 'application/json'; opt.body = JSON.stringify(body); }
  let r, j = null;
  try { r = await fetch(url, opt); } catch (e) { throw new Error('No internet connection. Please try again.'); }
  try { j = await r.json(); } catch (e) {}
  if (r.status === 401) { forceLogout(); throw new Error('Please log in again.'); }
  if (!r.ok || !j || !j.ok) throw new Error((j && j.error) || 'Server problem. Please try again.');
  return j;
}
function histStart() { const d = new Date(); d.setMonth(d.getMonth() - 5, 1); return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-01'; }
async function loadHistory() {
  const from = histStart();
  if (HISTBUSY || (HIST_FROM && HIST_FROM <= from) || (loadedFrom && loadedFrom <= from)) return;
  HISTBUSY = true;
  try { const res = await pfapi('att', null, { from, to: todayISO() }); res.records.forEach(rec => { if (!SYNCED[rec.k + SEP + rec.r]) { setRec(rec.k, rec.r, rec.d); SYNCED[rec.k + SEP + rec.r] = JSON.stringify(rec.d); } }); HIST_FROM = from; }
  catch (e) { toast(e.message, 'r'); } finally { HISTBUSY = false; }
  if (PROF || view === 'admin') { const y = window.scrollY; render(); window.scrollTo(0, y); }
}
function lastMonths(n) { const out = [], d = new Date(); d.setDate(1); for (let i = n - 1; i >= 0; i--) { const x = new Date(d.getFullYear(), d.getMonth() - i, 1); out.push(x.getFullYear() + '-' + pad(x.getMonth() + 1)); } return out; }
function schoolDays(ym) { const out = [], [y, m] = ym.split('-').map(Number), n = new Date(y, m, 0).getDate(), t = todayISO();
  for (let i = 1; i <= n; i++) { const d = ym + '-' + pad(i); if (d > t) break; if (dayIdx(d) >= 0 && !holidayOn(d)) out.push(d); } return out; }
function studentMonth(s, ym) { let marked = 0, absent = 0; schoolDays(ym).forEach(d => { const a = attOf(d, s.cls); if (!a) return; marked++; if (a.absent.includes(s.id)) absent++; }); return { marked, absent, present: marked - absent, pct: marked ? Math.round((marked - absent) / marked * 100) : null }; }
function teacherMonth(t, ym) { let abs = 0, half = 0, leave = 0; const days = schoolDays(ym);
  days.forEach(d => { const f = absentFrom(d, t); if (f === null) return; if (S.leaves.some(l => l.status === 'Approved' && l.tid === t && l.from <= d && l.to >= d)) leave++; else if (f > 0) half++; else abs++; });
  const off = abs + leave + half * .5; return { days: days.length, abs, half, leave, present: days.length - off, pct: days.length ? Math.round((days.length - off) / days.length * 100) : null }; }

// ---------------------------------------------------------------- HEADER SEARCH → PROFILE WINDOW
let PROF = null, PROFDATA = {};
function canProfile() { return U && ['principal', 'chairman'].includes(U.role); }
function people(q) {
  q = String(q || '').trim().toLowerCase(); const dig = q.replace(/\D/g, '');
  const tch = Object.entries(S.teachers).filter(([k, t]) => !q || t.name.toLowerCase().includes(q) || (t.role || '').toLowerCase().includes(q) || (dig.length >= 4 && String(t.phone || '').includes(dig))).map(([k, t]) => ({ kind: 't', id: k, name: t.name, sub: t.role || 'Teacher' }));
  const stu = S.students.filter(s => !q || s.name.toLowerCase().includes(q) || String(s.adm).toLowerCase() === q || s.cls.toLowerCase() === q || (s.father || '').toLowerCase().includes(q) || (dig.length >= 4 && String(s.phone || '').replace(/\D/g, '').includes(dig)))
    .map(s => ({ kind: 's', id: s.id, name: s.name, sub: `Class ${s.cls} · Adm ${s.adm}` }));
  return { tch, stu };
}
function gsearch(v) {
  const box = document.getElementById('g-res'); if (!box) return;
  if (!v.trim()) { box.innerHTML = ''; box.classList.remove('show'); return; }
  const { tch, stu } = people(v), row = p => `<button onclick="openProfile('${p.kind}',${jsq(p.id)},${jsq(v)})" class="srch-item"><span class="erp-avatar">${esc(initialsOf(p.name))}</span><span class="min-w-0"><b>${esc(p.name)}</b><small>${esc(p.sub)}</small></span></button>`;
  box.innerHTML = (tch.length ? `<p class="srch-h">Teachers (${tch.length})</p>${tch.slice(0, 6).map(row).join('')}` : '') + (stu.length ? `<p class="srch-h">Students (${stu.length})</p>${stu.slice(0, 8).map(row).join('')}` : '')
    + (!tch.length && !stu.length ? '<p class="srch-h">Nobody found</p>' : `<button onclick="openProfile(null,null,${jsq(v)})" class="srch-all">See all results</button>`);
  box.classList.add('show');
}
function initialsOf(n) { return String(n || '?').replace(/\s*\(.*\)/, '').replace(/\b(Sir|Ma'am|Mr|Mrs|Ms)\b\.?/g, '').trim().split(/\s+/).map(x => x[0]).slice(0, 2).join('').toUpperCase(); }
function openProfile(kind, id, q) {
  const r = people(q || ''), first = r.tch[0] ? { kind: 't', id: r.tch[0].id } : r.stu[0] ? { kind: 's', id: r.stu[0].id } : null;
  PROF = { kind: kind || (first && first.kind), id: id || (first && first.id), q: q || '', tab: 'all' };
  loadHistory(); profFetch(); render();
}
function closeProfile() { PROF = null; render(); }
async function profFetch() {
  if (!PROF || !PROF.id) return; const key = PROF.kind + ':' + PROF.id; if (PROFDATA[key]) return;
  PROFDATA[key] = { busy: true };
  try {
    const notes = await pfapi('notes', null, { who: key });
    const d = { notes: notes.notes, types: notes.types };
    if (PROF.kind === 't' && U.role === 'principal') { try { d.salary = (await pfapi('teacher', null, { tid: PROF.id })).salary; } catch (e) { d.salaryErr = e.message; } }
    PROFDATA[key] = d;
  } catch (e) { PROFDATA[key] = { error: e.message }; }
  if (PROF && PROF.kind === 's') ofFresh();
  if (PROF) render();
}
function profPick(kind, id) { PROF.kind = kind; PROF.id = id; profFetch(); render(); }
function vProfileWindow() {
  const { tch, stu } = people(PROF.q), sel = PROF.kind + ':' + PROF.id;
  const item = p => `<button onclick="profPick('${p.kind}',${jsq(p.id)})" class="pw-item ${p.kind + ':' + p.id === sel ? 'on' : ''}"><span class="erp-avatar">${esc(initialsOf(p.name))}</span><span class="min-w-0 text-left"><b>${esc(p.name)}</b><small>${esc(p.sub)}</small></span></button>`;
  let body = '<div class="p-10 text-center text-slate-500">Choose a teacher or student on the left.</div>';
  try { if (PROF.kind === 't' && S.teachers[PROF.id]) body = profTeacher(PROF.id); else if (PROF.kind === 's' && studentById(PROF.id)) body = profStudent(studentById(PROF.id)); }
  catch (e) { console.error(e); body = `<div class="card text-rose-700">${esc(e.message)}</div>`; }
  return `<div class="pw-back noprint" onclick="closeProfile()"><div class="pw" onclick="event.stopPropagation()" role="dialog" aria-label="Profile">
    <aside class="pw-side"><div class="flex items-center gap-2 mb-3"><input class="inp" value="${esc(PROF.q)}" placeholder="Search teacher or student…" oninput="PROF.q=this.value;clearTimeout(window.__pq);window.__pq=setTimeout(()=>{const el=document.getElementById('pw-list');if(el)el.innerHTML=pwList()},120)" autofocus></div>
      <div id="pw-list" class="pw-list">${pwList()}</div></aside>
    <section class="pw-main"><button onclick="closeProfile()" class="pw-close" title="Close"><i class="fa-solid fa-xmark"></i> Close</button>${body}</section></div></div>`;
}
function pwList() {
  const { tch, stu } = people(PROF.q), sel = PROF.kind + ':' + PROF.id;
  const item = p => `<button onclick="profPick('${p.kind}',${jsq(p.id)})" class="pw-item ${p.kind + ':' + p.id === sel ? 'on' : ''}"><span class="erp-avatar">${esc(initialsOf(p.name))}</span><span class="min-w-0 text-left"><b>${esc(p.name)}</b><small>${esc(p.sub)}</small></span></button>`;
  return (tch.length ? `<p class="srch-h">Teachers · ${tch.length}</p>${tch.slice(0, 30).map(item).join('')}` : '') + (stu.length ? `<p class="srch-h">Students · ${stu.length}</p>${stu.slice(0, 80).map(item).join('')}${stu.length > 80 ? '<p class="text-xs text-slate-500 px-3 py-2">Type more to narrow the list.</p>' : ''}` : '')
    || '<p class="text-sm text-slate-500 p-3">Nobody found.</p>';
}
const cap = (lbl, val, t) => `<div class="cap-t ${t || ''}"><small>${lbl}</small><b>${val}</b></div>`;
function since(s) {
  if (!s.joinDate) return null;
  const a = new Date(s.joinDate + 'T00:00:00'), b = new Date(), m = (b.getFullYear() - a.getFullYear()) * 12 + b.getMonth() - a.getMonth();
  return { years: Math.floor(m / 12), months: m % 12 };
}
function profNotes(who) {
  const d = PROFDATA[who] || {}; if (d.busy) return '<p class="text-sm text-slate-500">Loading…</p>'; if (d.error) return `<p class="text-sm text-rose-700">${esc(d.error)}</p>`;
  const col = { Complaint: 'bg-rose-50 text-rose-700', 'Warning' : 'bg-amber-50 text-amber-800', Appreciation: 'bg-emerald-50 text-emerald-700' };
  return (d.notes || []).map(n => `<div class="att-row"><span class="min-w-0"><span class="pill ${col[n.type] || 'bg-slate-100 text-slate-600'} mr-2">${esc(n.type)}</span>${esc(n.text)}<br><small class="text-slate-500">${fmtDate(n.date)} · ${esc(n.by)}</small></span>${U.role === 'principal' ? `<button onclick="profDelNote(${jsq(who)},'${n.id}')" class="text-slate-400 hover:text-rose-500" title="Remove"><i class="fa-solid fa-trash"></i></button>` : ''}</div>`).join('') || '<p class="text-sm text-slate-500">No complaints or notes yet.</p>'
    + (U.role === 'principal' ? `<div class="grid grid-cols-1 md:grid-cols-[160px_1fr_auto] gap-2 mt-3"><select id="pn-type" class="inp">${(d.types || ['Complaint', 'Appreciation', 'Note']).map(t => `<option>${t}</option>`).join('')}</select>
      <input id="pn-text" class="inp" maxlength="1000" placeholder="What happened? (only the Principal sees this)"><button onclick="profAddNote(${jsq(who)})" class="btn btn-p">Add</button></div>` : '');
}
async function profAddNote(who) {
  const text = document.getElementById('pn-text').value.trim(); if (text.length < 3) return toast('Write the note', 'a');
  try { const r = await pfapi('note', { who, type: document.getElementById('pn-type').value, text }); PROFDATA[who].notes.unshift(r.note); toast('Saved'); render(); } catch (e) { toast(e.message, 'r'); }
}
async function profDelNote(who, id) { if (!confirm('Remove this note?')) return; try { await pfapi('delnote', { id }); PROFDATA[who].notes = PROFDATA[who].notes.filter(n => n.id !== id); render(); } catch (e) { toast(e.message, 'r'); } }

function profTeacher(tid) {
  const t = S.teachers[tid], who = 't:' + tid, d = PROFDATA[who] || {}, months = lastMonths(5), ym = todayISO().slice(0, 7);
  const allocs = myAllocs(tid), ct = CLASSES.filter(k => k.ct === tid).map(k => k.c), tm = months.map(m => ({ m, ...teacherMonth(tid, m) }));
  const absent5 = tm.reduce((a, x) => a + x.abs + x.half * .5, 0), leave5 = tm.reduce((a, x) => a + x.leave, 0);
  const lpW = Object.values(S.lplan).filter(p => p.tid === tid && p.week.slice(0, 7) === ym), duty = monthLoad(todayISO(), true)[tid] || 0;
  const syl = [...new Set(allocs.map(a => sylKey(a.c, a.s)))].map(k => ({ k, st: sylStatus(k, todayISO()) })).filter(x => x.st);
  const sal = d.salary;
  return `<div class="pw-head"><span class="pw-av">${esc(initialsOf(t.name))}</span><div class="min-w-0"><h2 class="text-2xl font-semibold">${esc(t.name)}</h2>
      <p class="text-slate-500">${esc(t.role || 'Teacher')}${ct.length ? ' · Class teacher of ' + ct.map(esc).join(', ') : ''}${t.phone ? ' · ' + esc(t.phone) : ''}</p>
      <div class="flex flex-wrap gap-2 mt-2">${t.phone ? `<a target="_blank" href="${waLink(t.phone, 'Namaste ' + t.name + ', ')}" class="btn btn-sm btn-wa"><i class="fa-brands fa-whatsapp"></i> WhatsApp</a><a href="tel:+91${String(t.phone).replace(/\D/g, '').slice(-10)}" class="btn btn-sm btn-l"><i class="fa-solid fa-phone"></i> Call</a>` : ''}
        <span class="pill ${PINS['t:' + tid] ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-800'}">${PINS['t:' + tid] ? 'Own PIN' : 'Uses 1234'}</span></div></div></div>
    <div class="cap-row">${cap('Periods / week', loadOf(tid), 'c-lav')}${cap('Subjects', allocs.length, 'c-peach')}${cap('Present this month', tm[4].pct == null ? '—' : tm[4].pct + '%', 'c-mint')}
      ${cap('Absent (5 months)', dd(absent5) + ' days', absent5 > 6 ? 'c-red' : 'c-lime')}${cap('Leave (5 months)', leave5 + ' days', 'c-lav')}${cap('Arrangements this month', duty, 'c-peach')}
      ${cap('Lesson plans this month', lpW.length, 'c-mint')}${cap('Monthly salary', sal ? (sal.monthly == null ? 'Not set' : rs(sal.monthly)) : U.role === 'principal' ? '…' : '—', 'c-lime')}</div>
    <div class="grid grid-cols-1 xl:grid-cols-2 gap-4">
      <div class="card"><h3 class="h3 mb-2">Attendance — last 5 months</h3>${HISTBUSY ? '<p class="text-xs text-slate-500">Loading older months…</p>' : ''}
        <table class="tbl"><tr><th>Month</th><th class="text-right">School days</th><th class="text-right">Absent</th><th class="text-right">Half day</th><th class="text-right">Leave</th><th class="text-right">Present</th></tr>
        ${tm.slice().reverse().map(x => `<tr><td>${ymLong(x.m)}</td><td class="text-right">${x.days}</td><td class="text-right ${x.abs ? 'text-rose-700 font-semibold' : ''}">${x.abs}</td><td class="text-right">${x.half}</td><td class="text-right">${x.leave}</td><td class="text-right font-semibold">${x.pct == null ? '—' : x.pct + '%'}</td></tr>`).join('')}</table></div>
      <div class="card"><h3 class="h3 mb-2">Salary</h3>${U.role !== 'principal' ? '<p class="text-sm text-slate-500">Only the Principal sees salary.</p>' : d.busy ? '<p class="text-sm text-slate-500">Loading…</p>' : d.salaryErr ? `<p class="text-sm text-rose-700">${esc(d.salaryErr)}</p>` : !sal ? '' : `
        <p class="text-sm mb-2">Monthly <b>${sal.monthly == null ? 'not set' : rs(sal.monthly)}</b> · paid leave ${dd(sal.paidLeave)} day(s) a month</p>
        ${sal.months.length ? `<table class="tbl"><tr><th>Month</th><th class="text-right">Absent</th><th class="text-right">Net</th><th>Status</th></tr>${sal.months.map(m => `<tr><td>${ymLong(m.ym)}</td><td class="text-right">${m.absent == null ? '—' : dd(m.absent)}</td><td class="text-right font-semibold">${m.net == null ? '—' : rs(m.net)}</td><td>${m.status === 'Paid' ? `<span class="pill bg-emerald-50 text-emerald-700">Paid ${m.paidOn ? fmtDate(m.paidOn) : ''}</span>` : '<span class="pill bg-amber-50 text-amber-800">Not paid</span>'}</td></tr>`).join('')}</table>` : '<p class="text-sm text-slate-500">No salary month saved yet.</p>'}
        <button onclick="closeProfile();go('salary')" class="btn btn-sm btn-l mt-3">Open Salary</button>`}</div>
      <div class="card"><h3 class="h3 mb-2">Classes &amp; subjects</h3><div class="flex flex-wrap gap-2">${allocs.map(a => `<span class="cap-t c-lav sm"><small>Class ${esc(a.c)}</small><b>${esc(a.s)} · ${a.n}/wk</b></span>`).join('') || '<p class="text-sm text-slate-500">No periods.</p>'}</div>
        ${syl.length ? `<h4 class="font-semibold mt-4 mb-1 text-sm">Syllabus</h4>${syl.map(x => `<div class="att-row"><span>${esc(x.k.replace('|', ' · '))}</span><span class="text-sm ${x.st.lag ? 'text-rose-700 font-semibold' : 'text-slate-600'}">${x.st.done}/${x.st.total} chapters${x.st.lag ? ' · behind' : ''}</span></div>`).join('')}` : ''}</div>
      <div class="card"><h3 class="h3 mb-2">This week's timetable</h3>${teacherWeek(tid)}</div>
    </div>
    <div class="card"><h3 class="h3 mb-2">Complaints, appreciation &amp; notes</h3>${profNotes(who)}</div>`;
}
function teacherWeek(tid) {
  if (!S.grid) return '<p class="text-sm text-slate-500">No timetable yet.</p>';
  return `<div class="overflow-x-auto"><table class="tbl text-xs"><tr><th>Day</th>${BELLS.map(b => `<th class="text-center">${b.n}</th>`).join('')}</tr>${DAYS.map((day, d) => `<tr><td class="font-semibold">${day.slice(0, 3)}</td>${BELLS.map((b, i) => { const a = teacherAt(tid, d, i); return a ? `<td class="text-center"><b>${esc(a.c)}</b><br><span class="text-slate-500">${esc(a.s.split(/[ /]/)[0])}</span></td>` : '<td class="text-center text-emerald-700">Free</td>'; }).join('')}</tr>`).join('')}</table></div>`;
}
function examResult(s, e) {
  const M = S.marks[e.id] || {}, rows = [];
  Object.entries(M).forEach(([k, r]) => { const [c, subj] = k.split('|'); if (c !== s.cls) return; const v = r.m ? r.m[s.id] : undefined; if (v === undefined || v === '') return; rows.push({ subj, v, max: +r.max || 0 }); });
  const got = rows.filter(r => r.v !== 'AB').reduce((a, r) => a + (+r.v || 0), 0), max = rows.filter(r => r.v !== 'AB').reduce((a, r) => a + r.max, 0);
  return { rows, got, max, pct: max ? Math.round(got / max * 100) : null };
}
// Student photo — one stored copy (progress_api.php, imported from the old ERP), shown wherever the child appears.
const STU_PH = {};
function stuPhoto(sid) {
  if (STU_PH[sid] !== undefined) return STU_PH[sid] || '';
  STU_PH[sid] = null;
  fetch('progress_api.php?a=photos&sid=' + encodeURIComponent(sid), { headers: { 'X-School-Token': TOKEN || '' } }).then(r => r.json()).then(d => {
    const u = (d.photos || {})[sid] || ''; STU_PH[sid] = /^data:image\/(jpeg|png|webp);base64,/.test(u) ? u : '';
    if (STU_PH[sid]) document.querySelectorAll('[data-stuphoto="' + CSS.escape(sid) + '"]').forEach(el => { el.innerHTML = `<img src="${STU_PH[sid]}" alt="" style="width:100%;height:100%;object-fit:cover;border-radius:inherit">`; });
  }).catch(() => { STU_PH[sid] = ''; });
  return '';
}
function profStudent(s) {
  const who = 's:' + s.id, months = lastMonths(6), am = months.map(m => ({ m, ...studentMonth(s, m) })), sn = since(s);
  const totM = am.reduce((a, x) => a + x.marked, 0), totA = am.reduce((a, x) => a + x.absent, 0);
  const exams = S.exams.slice().sort((a, b) => a.date.localeCompare(b.date)).map(e => ({ e, r: examResult(s, e) })).filter(x => x.r.rows.length);
  const subs = [...new Set(exams.flatMap(x => x.r.rows.map(r => r.subj)))];
  const beh = Object.values(S.behav).flatMap(o => Object.values(o)).filter(b => b.sid === s.id).sort((a, b) => b.date.localeCompare(a.date));
  const pts = beh.reduce((a, b) => a + (+b.pts || 0), 0), spk = S.speak.filter(x => (x.sids || []).includes(s.id) || x.sid === s.id).length;
  const ct = (CLASSES.find(k => k.c === s.cls) || {}).ct, fam = familyOf(s, S.students).filter(x => x.id !== s.id);
  const of = OF && !OF.error ? OF.students.find(x => x.id === s.id) : null, rec = OF && OF.payments ? OF.payments.filter(p => p.sid === s.id) : [];
  const last = exams[exams.length - 1];
  return `<div class="pw-head"><span class="pw-av" data-stuphoto="${esc(s.id)}">${stuPhoto(s.id) ? `<img src="${stuPhoto(s.id)}" alt="" style="width:100%;height:100%;object-fit:cover;border-radius:inherit">` : esc(initialsOf(s.name))}</span><div class="min-w-0"><h2 class="text-2xl font-semibold">${esc(s.name)}</h2>
      <p class="text-slate-500">Class ${esc(s.cls)}${s.section ? '-' + esc(s.section) : ''} · Adm ${esc(s.adm)}${ct ? ' · Class teacher ' + esc(T(ct).name) : ''}</p>
      <div class="flex flex-wrap gap-2 mt-2">${s.phone ? `<a target="_blank" href="${waLink(s.phone, 'Namaste, this is from ' + S.settings.school + '. ')}" class="btn btn-sm btn-wa"><i class="fa-brands fa-whatsapp"></i> Parent</a><a href="tel:+91${String(s.phone).replace(/\D/g, '').slice(-10)}" class="btn btn-sm btn-l"><i class="fa-solid fa-phone"></i> Call</a>` : ''}
        <button onclick="printStudentReport(${jsq(s.id)})" class="btn btn-sm btn-l"><i class="fa-solid fa-print"></i> Report card</button>${U.role === 'principal' ? `<button onclick="profJoin(${jsq(s.id)})" class="btn btn-sm btn-l">Edit joining details</button>` : ''}</div></div></div>
    <div class="cap-row">${cap('In school since', sn ? `${sn.years} yr ${sn.months} mo` : 'Not entered', 'c-lav')}${cap('Joined in', s.joinClass ? 'Class ' + esc(s.joinClass) + (s.joinDate ? ' · ' + new Date(s.joinDate + 'T00:00:00').getFullYear() : '') : '—', 'c-peach')}
      ${cap('Attendance (6 months)', totM ? Math.round((totM - totA) / totM * 100) + '%' : '—', 'c-mint')}${cap('Days absent', totA, totA > 10 ? 'c-red' : 'c-lime')}
      ${cap('Last exam', last ? (last.r.pct == null ? '—' : last.r.pct + '% · ' + grade(last.r.pct)) : '—', 'c-lav')}${cap('Behaviour points', pts, pts < 0 ? 'c-red' : 'c-peach')}
      ${cap('Speaking activities', spk, 'c-mint')}${cap('Fee due', of ? (of.dues.missing ? 'Not set' : rs(Math.max(0, of.dues.due))) : '…', of && of.dues.due > 0 ? 'c-red' : 'c-lime')}</div>
    <div class="grid grid-cols-1 xl:grid-cols-2 gap-4">
      <div class="card"><h3 class="h3 mb-2">Personal details</h3><div class="grid grid-cols-2 gap-x-6 text-sm">${[['Father', s.father], ['Mother', s.mother], ['Mobile', s.phone], ['Date of birth', s.dob ? fmtDate(s.dob) : ''], ['Address', s.address], ['School bus', s.km ? s.km + ' km' : 'No'], ['Brothers / sisters here', fam.map(x => x.name + ' (' + x.cls + ')').join(', ')], ['Joining date', s.joinDate ? fmtDate(s.joinDate) : '']]
        .map(([k, v]) => `<div class="py-2 border-b border-slate-100"><small class="text-slate-500 block">${k}</small><span>${v ? esc(v) : '—'}</span></div>`).join('')}</div></div>
      <div class="card"><h3 class="h3 mb-2">Attendance month by month</h3>${HISTBUSY ? '<p class="text-xs text-slate-500">Loading older months…</p>' : ''}
        <table class="tbl"><tr><th>Month</th><th class="text-right">Marked days</th><th class="text-right">Present</th><th class="text-right">Absent</th><th class="w-32">%</th></tr>
        ${am.slice().reverse().map(x => `<tr><td>${ymLong(x.m)}</td><td class="text-right">${x.marked}</td><td class="text-right">${x.present}</td><td class="text-right ${x.absent ? 'text-rose-700 font-semibold' : ''}">${x.absent}</td><td>${x.pct == null ? '—' : `<div class="flex items-center gap-2"><span class="w-10 text-right font-semibold">${x.pct}%</span><span class="flex-1">${bar(x.pct, x.pct >= 90 ? 'bg-emerald-500' : x.pct >= 75 ? 'bg-amber-400' : 'bg-rose-500')}</span></div>`}</td></tr>`).join('')}</table></div>
    </div>
    <div class="card"><div class="flex justify-between items-center mb-2"><h3 class="h3">Marksheet</h3>${last ? `<span class="text-sm text-slate-500">Last exam: ${esc(last.e.name)}</span>` : ''}</div>
      ${exams.length ? `<div class="overflow-x-auto"><table class="tbl"><tr><th>Subject</th>${exams.map(x => `<th class="text-right">${esc(x.e.name)}</th>`).join('')}</tr>
        ${subs.map(sb => `<tr><td>${esc(sb)}</td>${exams.map(x => { const r = x.r.rows.find(y => y.subj === sb); return `<td class="text-right">${r ? (r.v === 'AB' ? '<span class="text-rose-700">AB</span>' : `${r.v}<span class="text-slate-400">/${r.max}</span>`) : '—'}</td>`; }).join('')}</tr>`).join('')}
        <tr><th>Total</th>${exams.map(x => `<th class="text-right">${x.r.got}/${x.r.max} · ${x.r.pct == null ? '—' : x.r.pct + '% ' + grade(x.r.pct)}</th>`).join('')}</tr></table></div>
        ${exams.length > 1 ? `<div class="mt-3">${svgLine(exams.map(x => examShort(x.e)), exams.map(x => x.r.pct), { unit: '%', max: 100, h: 150, title: 'Exam percentage' })}</div>` : ''}` : '<p class="text-sm text-slate-500">No marks entered yet.</p>'}</div>
    <div class="grid grid-cols-1 xl:grid-cols-2 gap-4">
      <div class="card"><h3 class="h3 mb-2">Fees</h3>${!OF ? '<p class="text-sm text-slate-500">Loading fee account…</p>' : OF.error ? `<p class="text-sm text-rose-700">${esc(OF.error)}</p>` : of ? ofBreak(of) + `
        <h4 class="font-semibold text-sm mt-4 mb-1">Receipts</h4>${rec.length ? rec.map(p => `<div class="att-row"><span>#${p.no} · ${fmtDate(p.date)} · ${esc(p.mode)}${p.cancelled ? ' <span class="pill bg-rose-50 text-rose-700">cancelled</span>' : ''}</span><b>${rs(p.amt)}</b></div>`).join('') : '<p class="text-sm text-slate-500">No receipt this session.</p>'}
        ${U.role === 'principal' ? `<button onclick="OFSEL=${jsq(s.id)};OFQ=${jsq(s.name)};OFNONCE=null;closeProfile();go('fdesk')" class="btn btn-sm btn-p mt-3"><i class="fa-solid fa-receipt"></i> Take fee</button>` : ''}` : '<p class="text-sm text-slate-500">No fee account.</p>'}</div>
      <div class="card"><h3 class="h3 mb-2">Behaviour</h3>${beh.slice(0, 8).map(b => `<div class="att-row"><span>${esc(b.reason)}${b.note ? ' — ' + esc(b.note) : ''}<br><small class="text-slate-500">${fmtDate(b.date)} · ${esc(T(b.tid).name)}</small></span><span class="pill ${b.pts > 0 ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700'}">${b.pts > 0 ? '+' : ''}${b.pts}</span></div>`).join('') || '<p class="text-sm text-slate-500">Nothing recorded.</p>'}</div>
    </div>
    <div class="card"><h3 class="h3 mb-2">Complaints, parent meetings &amp; notes</h3>${profNotes(who)}</div>`;
}
function profJoin(id) {
  const s = studentById(id); if (!s) return;
  const d = prompt('Date of joining the school (YYYY-MM-DD)', s.joinDate || ''); if (d === null) return;
  if (d && !/^\d{4}-\d{2}-\d{2}$/.test(d.trim())) return toast('Write the date like 2023-04-01', 'a');
  const c = prompt('Class at joining (e.g. 6th)', s.joinClass || ''); if (c === null) return;
  if (c && !CLASS_NAMES.includes(c.trim())) return toast('Class must be one of: ' + CLASS_NAMES.slice().reverse().join(', '), 'a');
  s.joinDate = d.trim(); s.joinClass = c.trim(); log('Students', `${s.name}: joined ${s.joinClass ? 'Class ' + s.joinClass : ''} ${s.joinDate}`); render();
}
function printStudentReport(id) {
  const s = studentById(id); if (!s) return;
  const exams = S.exams.slice().sort((a, b) => a.date.localeCompare(b.date)).map(e => ({ e, r: examResult(s, e) })).filter(x => x.r.rows.length);
  const subs = [...new Set(exams.flatMap(x => x.r.rows.map(r => r.subj)))], am = lastMonths(6).map(m => ({ m, ...studentMonth(s, m) }));
  printHTML('Report card', printHeader(`Progress Report · Session ${esc(S.settings.session)}`) + `<p><b>${esc(s.name)}</b> · Class ${esc(s.cls)} · Adm ${esc(s.adm)} · Father ${esc(s.father || '')}</p>
    <table><tr><th>Subject</th>${exams.map(x => `<th>${esc(x.e.name)}</th>`).join('')}</tr>${subs.map(sb => `<tr><td>${esc(sb)}</td>${exams.map(x => { const r = x.r.rows.find(y => y.subj === sb); return `<td>${r ? (r.v === 'AB' ? 'AB' : r.v + '/' + r.max) : '—'}</td>`; }).join('')}</tr>`).join('')}
    <tr><th>Total</th>${exams.map(x => `<th>${x.r.got}/${x.r.max} (${x.r.pct ?? '—'}% ${x.r.pct != null ? grade(x.r.pct) : ''})</th>`).join('')}</tr></table>
    <h2>Attendance</h2><table><tr>${am.map(x => `<th>${ymLong(x.m)}</th>`).join('')}</tr><tr>${am.map(x => `<td>${x.marked ? x.present + '/' + x.marked : '—'}</td>`).join('')}</tr></table>
    <p style="margin-top:30px">Class teacher ____________ &nbsp;&nbsp; Parent ____________</p>` + PRINT_FOOT);
  log('Report', 'Report card printed for ' + s.name);
}

// ---------------------------------------------------------------- ADMIN DASHBOARD (reports with graphs)
let SALSUM = null;
async function loadSalSum() { if (SALSUM) return; SALSUM = { busy: true }; try { SALSUM = (await salapi('summary')).months; } catch (e) { SALSUM = { error: e.message }; } if (view === 'admin') render(); }
function vAdmin() {
  ofFresh(); if (!SALSUM) loadSalSum(); loadHistory();
  const ym = todayISO().slice(0, 7), months = lastMonths(6), shortM = m => new Date(m + '-01T00:00:00').toLocaleString('en-IN', { month: 'short' });
  const ofOk = OF && !OF.error && OF.payments, live = ofOk ? OF.payments.filter(p => !p.cancelled) : [];
  const feeBy = months.map(m => live.filter(p => p.date.slice(0, 7) === m).reduce((a, p) => a + p.amt, 0));
  const dueBy = CLASS_NAMES.slice().reverse().map(c => ({ c, v: ofOk ? OF.students.filter(s => s.cls === c).reduce((a, s) => a + Math.max(0, s.dues.due), 0) : 0 }));
  const totalDue = dueBy.reduce((a, x) => a + x.v, 0), salOk = Array.isArray(SALSUM), salNow = salOk ? SALSUM[SALSUM.length - 1] : null;
  const attBy = months.map(m => { let mk = 0, ab = 0; schoolDays(m).forEach(d => CLASS_NAMES.forEach(c => { const a = attOf(d, c), n = studentsOf(c).length; if (!a || !n) return; mk += n; ab += a.absent.length; })); return mk ? Math.round((mk - ab) / mk * 100) : null; });
  const staffBy = months.map(m => { const ts = Object.keys(S.teachers); const v = ts.map(t => teacherMonth(t, m).pct).filter(x => x != null); return v.length ? Math.round(v.reduce((a, b) => a + b, 0) / v.length) : null; });
  const k = (ic, t, lbl, val, sub, go) => `<button ${go ? `onclick="go('${go}')"` : 'disabled'} class="kpi ${t}"><span class="kpi-ic"><i class="fa-solid ${ic}"></i></span><span class="kpi-val">${val}</span><span class="kpi-lbl">${lbl}</span><span class="kpi-sub">${sub}</span></button>`;
  const act = (ic, lbl, sub, goTo, t) => `<button onclick="go('${goTo}')" class="act-t ${t}"><span class="kpi-ic"><i class="fa-solid ${ic}"></i></span><b>${lbl}</b><small>${sub}</small></button>`;
  const strength = CLASS_NAMES.slice().reverse().map(c => ({ label: c, value: studentsOf(c).length }));
  return head('Admin Dashboard', 'Money, staff and student reports for the whole school — every figure opens its page.', `<button onclick="OF=null;SALSUM=null;render()" class="btn btn-l"><i class="fa-solid fa-rotate"></i> Refresh</button>`) + `
    <div class="grid grid-cols-2 xl:grid-cols-4 gap-3">
      ${k('fa-indian-rupee-sign', 'kpi-peach', 'Fee collected this month', ofOk ? rs(feeBy[5]) : '…', ofOk ? `${live.filter(p => p.date.slice(0, 7) === ym).length} receipts · last month ${rs(feeBy[4])}` : 'Loading', 'freceipts')}
      ${k('fa-hourglass-half', totalDue ? 'kpi-warn' : 'kpi-lime', 'Fee still due', ofOk ? rs(totalDue) : '…', ofOk ? `${new Set(OF.students.filter(s => s.dues.due > 0).map(s => s.fam)).size} families to call` : '', 'fdues')}
      ${k('fa-wallet', 'kpi-lav', 'Salary this month', salNow ? rs(salNow.net) : '…', salNow ? `${salNow.paid}/${salNow.staff} paid · cut ${rs(salNow.cut)}` : (SALSUM && SALSUM.error) || 'Loading', 'salary')}
      ${k('fa-user-check', 'kpi-mint', 'Attendance this month', attBy[5] == null ? '—' : attBy[5] + '%', `students · staff ${staffBy[5] == null ? '—' : staffBy[5] + '%'}`, 'attendance')}
    </div>
    <div class="grid grid-cols-1 xl:grid-cols-2 gap-4">
      <div class="card"><div class="flex justify-between items-center"><h3 class="h3">Fee collected by month</h3><button onclick="go('freceipts')" class="link">Receipts</button></div>
        <p class="text-xs text-slate-500 mb-2">Live receipts (cancelled ones left out)</p>${ofOk ? svgBars(months.map((m, i) => ({ label: shortM(m), value: feeBy[i], display: feeBy[i] ? '₹' + (feeBy[i] >= 100000 ? (feeBy[i] / 100000).toFixed(1) + 'L' : Math.round(feeBy[i] / 1000) + 'k') : '0' })), { color: CH.orange, title: 'Fee collected by month' }) : '<p class="text-sm text-slate-500">Loading…</p>'}</div>
      <div class="card"><div class="flex justify-between items-center"><h3 class="h3">Fee due by class</h3><button onclick="go('fcalls')" class="link">Dues calling</button></div>
        <p class="text-xs text-slate-500 mb-2">Old due + yearly + monthly up to this month, less payments</p>${ofOk ? svgBars(dueBy.map(x => ({ label: x.c, value: x.v, display: x.v ? (x.v >= 100000 ? (x.v / 100000).toFixed(1) + 'L' : Math.round(x.v / 1000) + 'k') : '0' })), { color: CH.violet, title: 'Fee due by class' }) : '<p class="text-sm text-slate-500">Loading…</p>'}</div>
      <div class="card"><div class="flex justify-between items-center"><h3 class="h3">Salary bill vs paid</h3><button onclick="go('salary')" class="link">Salary</button></div>
        <p class="text-xs text-slate-500 mb-2">Net salary for staff with a salary set</p>${salOk ? svgGroupBars(SALSUM.map(x => shortM(x.ym)), [{ name: 'Salary bill', color: CH.violet, values: SALSUM.map(x => x.net), fmt: rs }, { name: 'Paid', color: CH.teal, values: SALSUM.map(x => x.paidAmt), fmt: rs }], { title: 'Salary by month' }) : `<p class="text-sm text-slate-500">${SALSUM && SALSUM.error ? esc(SALSUM.error) : 'Loading…'}</p>`}</div>
      <div class="card"><div class="flex justify-between items-center"><h3 class="h3">Student attendance by month</h3><button onclick="go('attendance')" class="link">Attendance</button></div>
        <p class="text-xs text-slate-500 mb-2">Present ÷ marked, all classes${HISTBUSY ? ' · loading older months…' : ''}</p>${svgLine(months.map(shortM), attBy, { unit: '%', min: Math.min(60, ...attBy.filter(x => x != null)), max: 100, color: CH.teal, title: 'Attendance by month' })}</div>
      <div class="card"><h3 class="h3">Students in each class</h3><p class="text-xs text-slate-500 mb-2">${S.students.length} students in all</p>${svgBars(strength, { color: CH.teal, title: 'Students per class' })}</div>
      <div class="card"><h3 class="h3">Staff attendance by month</h3><p class="text-xs text-slate-500 mb-2">Average present days, including approved leave as absent</p>${svgLine(months.map(shortM), staffBy, { unit: '%', min: Math.min(60, ...staffBy.filter(x => x != null)), max: 100, color: CH.violet, title: 'Staff attendance by month' })}</div>
    </div>
    <div class="card"><h3 class="h3 mb-3">Quick actions</h3><div class="grid grid-cols-2 md:grid-cols-4 gap-3">
      ${act('fa-receipt', 'Take a fee', 'Fee Desk · receipt', 'fdesk', 'kpi-peach')}${act('fa-money-check-dollar', 'Pay salary', 'Staff Salary', 'salary', 'kpi-lav')}
      ${act('fa-pen-to-square', 'Enter marks', 'Marks entry', 'marks', 'kpi-mint')}${act('fa-file-lines', 'Report cards', 'Results & print', 'results', 'kpi-lime')}
      ${act('fa-phone', 'Call dues', 'Reception list', 'fcalls', 'kpi-mint')}${act('fa-bullhorn', 'Send a notice', 'Parents & staff', 'notices', 'kpi-peach')}
      ${act('fa-scale-balanced', 'Rules & policies', 'School rules', 'rules', 'kpi-lav')}${act('fa-clock-rotate-left', 'Activity log', 'Who did what', 'log', 'kpi-lime')}</div></div>`;
}

// ---------------------------------------------------------------- RULES & POLICIES (settings.rules; Principal edits, staff read)
function vRules() {
  const R = S.settings.rules || [], ed = U.role === 'principal', groups = ['Students', 'Staff', 'Parents', 'Fees', 'Exams', 'Transport'];
  return head('Rules & Policies', 'The school\'s written rules in one place. Teachers can read them on their phones.', ed ? `<button onclick="ruleAdd()" class="btn btn-p"><i class="fa-solid fa-plus"></i> Add a rule</button>${R.length ? '' : '<button onclick="ruleStarter()" class="btn btn-l">Add starter rules</button>'}` : '') +
    (R.length ? groups.filter(g => R.some(r => r.for === g)).map(g => `<div class="card"><h3 class="h3 mb-2">${g}</h3>${R.filter(r => r.for === g).map(r => `<div class="att-row items-start"><span><b>${esc(r.title)}</b><br><span class="text-sm text-slate-600 whitespace-pre-line">${esc(r.text)}</span></span>
      ${ed ? `<span class="flex gap-2 shrink-0"><button onclick="ruleEdit('${r.id}')" class="link">Edit</button><button onclick="ruleDel('${r.id}')" class="link">Remove</button></span>` : ''}</div>`).join('')}</div>`).join('')
      : '<div class="card text-sm text-slate-500">No rules written yet.</div>');
}
function ruleForm(r) {
  const title = prompt('Rule title', r.title || ''); if (title === null || !title.trim()) return null;
  const text = prompt('What the rule says', r.text || ''); if (text === null) return null;
  const f = prompt('Who is it for? Students, Staff, Parents, Fees, Exams or Transport', r.for || 'Students'); if (f === null) return null;
  const fr = ['Students', 'Staff', 'Parents', 'Fees', 'Exams', 'Transport'].find(x => x.toLowerCase() === f.trim().toLowerCase()) || 'Students';
  return { title: title.trim().slice(0, 120), text: text.trim().slice(0, 1500), for: fr };
}
function ruleAdd() { const r = ruleForm({}); if (!r) return; (S.settings.rules = S.settings.rules || []).push({ id: uid(), ...r }); log('Rules', 'Added rule: ' + r.title); render(); }
function ruleEdit(id) { const x = (S.settings.rules || []).find(r => r.id === id); if (!x) return; const r = ruleForm(x); if (!r) return; Object.assign(x, r); log('Rules', 'Edited rule: ' + r.title); render(); }
function ruleDel(id) { const x = (S.settings.rules || []).find(r => r.id === id); if (!x || !confirm('Remove "' + x.title + '"?')) return; S.settings.rules = S.settings.rules.filter(r => r.id !== id); log('Rules', 'Removed rule: ' + x.title); render(); }
function ruleStarter() {
  const R = [['Students', 'School timing', 'Assembly starts at 8:40 AM. Students must reach before 8:35 AM.'], ['Students', 'Uniform', 'Full school uniform with ID card every day; winter uniform as announced.'],
    ['Students', 'Attendance', 'At least 75% attendance is needed to sit the annual exam. Absence must be explained by a parent.'], ['Students', 'Mobile phones', 'Phones are not allowed in class.'],
    ['Staff', 'Diary', 'Fill the teacher diary for every bell on the same day.'], ['Staff', 'Leave', 'Apply for leave in the ERP at least one day before, except emergencies.'],
    ['Parents', 'Meetings', 'Parents may meet teachers on PTM days or by appointment through the office.'], ['Fees', 'Fee date', 'Monthly fee is paid by the 10th of each month at the school office. Always take a receipt.'],
    ['Exams', 'Unfair means', 'Any unfair means in an exam cancels that paper.'], ['Transport', 'Bus', 'Students must be at the bus stop five minutes before the bus time.']];
  S.settings.rules = R.map(([f, t, x]) => ({ id: uid(), for: f, title: t, text: x })); log('Rules', 'Added starter school rules (edit them to match the school)'); render();
}

// ---------------------------------------------------------------- GAME ZONE (students + parents) — school/game_api.php
// Short rounds, instant funny feedback, a rule and an English word after every answer,
// combos, streaks, chests, spaced "revenge" rounds and a whole-class weekly mission.
let GZ = null, GZBUSY = false, GP = null, GTIMER = null, GT = null;
async function gapi(a, body, q) {
  const url = 'game_api.php?a=' + a + (q ? '&' + new URLSearchParams(q) : '');
  const opt = { method: body ? 'POST' : 'GET', headers: { 'X-School-Token': TOKEN || '' } };
  if (body) { opt.headers['Content-Type'] = 'application/json'; opt.body = JSON.stringify(body); }
  let r, j = null;
  try { r = await fetch(url, opt); } catch (e) { throw new Error('No internet connection. Please try again.'); }
  try { j = await r.json(); } catch (e) {}
  if (r.status === 401) { forceLogout(); throw new Error('Please log in again.'); }
  if (!r.ok || !j || !j.ok) throw new Error((j && j.error) || 'Server problem. Please try again.');
  return j;
}
async function loadGame() {
  if (GZBUSY) return; GZBUSY = true;
  try { GZ = await gapi('state', null, { sid: pKid().id }); } catch (e) { GZ = { error: e.message }; } finally { GZBUSY = false; }
  if (view === 'game') render();
}
function gStop() { if (GTIMER) { clearInterval(GTIMER); GTIMER = null; } }
function gTimer() {
  gStop();
  GTIMER = setInterval(() => {
    if (!GP || GP.res) return gStop();
    const left = Math.max(0, Math.ceil((GP.deadline - Date.now()) / 1000)), t = document.getElementById('g-left'), b = document.getElementById('g-bar');
    if (t) t.textContent = left; if (b) b.style.width = (left / GZ.seconds * 100) + '%';
    if (left <= 0) { gStop(); gAnswer(-1); }
  }, 200);
}
async function gStart(mode) {
  try { const r = await gapi('start', { sid: GZ.kid.id, mode }); GP = { rid: r.rid, q: r.q, i: r.i, n: r.n, deadline: Date.now() + r.left * 1000, combo: 0, right: 0, xp: 0 }; render(); window.scrollTo(0, 0); gTimer(); }
  catch (e) { toast(e.message, 'a'); }
}
async function gContinue() {
  try { const r = await gapi('next', { sid: GZ.kid.id, rid: GZ.open.rid });
    if (!r.q) { GZ = null; return loadGame(); }
    GP = { rid: GZ.open.rid, q: r.q, i: r.i, n: r.n, deadline: Date.now() + r.left * 1000, combo: 0, right: 0, xp: 0 }; render(); window.scrollTo(0, 0); gTimer(); }
  catch (e) { toast(e.message, 'r'); }
}
async function gAnswer(choice) {
  if (!GP || GP.res || GP.busy) return; GP.busy = true; gStop();
  try {
    const r = await gapi('answer', { sid: GZ.kid.id, rid: GP.rid, choice });
    GP.res = r; GP.chosen = choice; GP.combo = r.combo; GP.xp += r.xp; if (r.correct) GP.right++;
    render();
    if (r.levelUp) setTimeout(() => toast('Level up! You are now a ' + r.levelUp + ' 🎉'), 400);
  } catch (e) { toast(e.message, 'r'); GP = null; GZ = null; loadGame(); }
  finally { if (GP) GP.busy = false; }
}
async function gNext() {
  if (!GP || !GP.res) return;
  if (!GP.res.more) { GP = { done: true, ...GP }; GP.res = null; render(); return; }
  try { const r = await gapi('next', { sid: GZ.kid.id, rid: GP.rid });
    if (!r.q) { GP.done = true; GP.res = null; return render(); }
    Object.assign(GP, { q: r.q, i: r.i, res: null, chosen: null, deadline: Date.now() + r.left * 1000 }); render(); gTimer(); }
  catch (e) { toast(e.message, 'r'); }
}
async function gFlag() {
  try { const r = await gapi('flag', { sid: GZ.kid.id, rid: GP.rid, i: GP.res.i }); GP.res.flagged = true; render(); toast(r.hidden ? 'Thanks! This question is hidden until a teacher checks it.' : 'Thanks! Your teacher will check it.'); }
  catch (e) { toast(e.message, 'a'); }
}
function gPlayCard() {
  const p = GP;
  if (p.done) {
    const great = p.right >= p.n - 1;
    return `<section class="game-card text-center space-y-3"><div class="text-6xl pop">${great ? '🏆' : p.right >= p.n / 2 ? '💪' : '🌱'}</div>
      <h3 class="text-2xl font-semibold">${p.right} of ${p.n} right</h3><p class="text-white/80">+${p.xp} XP${great ? ' · Superb!' : p.right ? ' · Good going — the ones you missed come back in a revenge round.' : ' · Every wrong answer comes back later, and next time you will know it.'}</p>
      ${p.chest ? `<p class="text-lg">🎁 Chest opened: <span class="text-3xl">${p.chest[0]}</span> ${esc(p.chest[1])}</p>` : ''}
      <button onclick="GP=null;GZ=null;loadGame()" class="btn bg-marigold text-ink w-full py-3">Back to Game Zone</button></section>`;
  }
  const r = p.res, left = Math.max(0, Math.ceil((p.deadline - Date.now()) / 1000));
  if (r && r.chest) p.chest = r.chest;
  return `<section class="game-card space-y-4">
    <div class="flex items-center justify-between gap-2 text-sm"><span class="text-white/70">${p.i + 1} / ${p.n} · ${esc(p.q.subj)}${p.q.topic ? ' · ' + esc(p.q.topic) : ''}</span>
      ${p.combo >= 2 ? `<span class="pill bg-marigold text-ink pop">🔥 Combo ×${p.combo}${p.combo >= 3 ? ' +5' : ''}</span>` : ''}</div>
    ${r ? '' : `<div class="flex items-center gap-3"><div class="flex-1 h-2 rounded-full bg-white/15 overflow-hidden"><div id="g-bar" class="h-2 bg-marigold" style="width:${left / GZ.seconds * 100}%"></div></div><b id="g-left" class="w-6 text-right">${left}</b></div>`}
    <h3 class="text-2xl font-semibold leading-snug">${esc(p.q.q)}</h3>
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">${p.q.opts.map((o, i) => {
      const c = !r ? 'bg-white/10 border-white/20 hover:bg-white/20' : i === r.ans ? 'bg-leaf border-leaf' : i === p.chosen ? 'bg-rose border-rose' : 'bg-white/5 border-white/10 opacity-50';
      return `<button ${r ? 'disabled' : ''} onclick="gAnswer(${i})" class="w-full text-left p-4 rounded-2xl border-2 ${c} text-lg font-medium">${esc(o)}</button>`; }).join('')}</div>
    ${r ? `<div class="space-y-3 pop">
      <p class="text-xl font-semibold">${r.correct ? ['Boom! ✅', 'Correct! 🎯', 'Sahi pakde! ✅', 'Wah! 🌟'][p.i % 4] : r.late ? 'Time up! ⏰' : 'Oops! 😅'} ${r.xp ? `<span class="text-marigold">+${r.xp} XP</span>` : ''}</p>
      ${r.fun ? `<div class="rounded-2xl bg-white/10 p-3"><p class="text-sm text-marigold font-semibold">Yaad rakh</p><p>${esc(r.fun)}</p></div>` : ''}
      ${r.rule ? `<div class="rounded-2xl bg-white p-3 text-ink"><p class="text-sm font-semibold">Rule</p><p>${esc(r.rule)}</p></div>` : ''}
      ${r.word && r.word[0] ? `<p class="text-sm">📖 New English word: <b>${esc(r.word[0])}</b> = ${esc(r.word[1] || '')}</p>` : ''}
      <div class="flex gap-2"><button onclick="gNext()" class="btn bg-marigold text-ink flex-1 py-3 text-base">${r.more ? 'Next question' : 'See my score'}</button>
        ${r.flaggable && !r.flagged ? '<button onclick="gFlag()" class="btn bg-white/10 text-white text-xs">This looks wrong</button>' : ''}</div></div>` : ''}
  </section>`;
}
function vGame() {
  if (!PD || !PD.kids.length) return vPHome();
  const kid = pKid();
  if (!GZ || (GZ.kid && GZ.kid.id !== kid.id)) { if (!GZ || !GZ.error) { GZ = null; loadGame(); } }
  if (!GZ) return `${kidSwitch()}<div class="card text-slate-500">Loading Game Zone…</div>`;
  if (GZ.error) return `${kidSwitch()}<div class="card">${esc(GZ.error)} <button onclick="GZ=null;loadGame()" class="btn btn-sm btn-l ml-2">Try again</button></div>`;
  if (GP) return gPlayCard();
  const st = GZ.stats, m = GZ.mission, pct = Math.min(100, Math.round(m.right / m.target * 100));
  const tile = (mode, emoji, title, sub, on, cls) => `<button ${on ? '' : 'disabled'} onclick="${mode === 'duel' ? "go('quiz')" : `gStart('${mode}')`}" class="game-tile ${cls || ''} ${on ? '' : 'opacity-40'}"><span class="text-4xl">${emoji}</span><span class="font-semibold text-lg leading-tight">${title}</span><span class="text-sm opacity-80 leading-snug">${sub}</span></button>`;
  const noRounds = GZ.roundsLeft <= 0;
  return `${kidSwitch()}
    <section class="game-card"><div class="flex items-center gap-4">
      <div class="avatar text-2xl">${st.level}</div>
      <div class="flex-1 min-w-0"><p class="text-white/70 text-sm">${esc(GZ.kid.name)} · Class ${esc(GZ.kid.cls)}</p><h2 class="text-2xl font-semibold leading-tight">${esc(st.levelName)}</h2>
        <div class="h-2 rounded-full bg-white/15 mt-2 overflow-hidden"><div class="h-2 bg-marigold" style="width:${Math.round(st.levelXp / st.levelNeed * 100)}%"></div></div>
        <p class="text-xs text-white/70 mt-1">${st.levelXp} / ${st.levelNeed} XP to level ${st.level + 1}</p></div></div>
      <div class="grid grid-cols-3 gap-2 mt-4 text-center">
        <div class="kstat"><b>${st.streak ? '🔥 ' + st.streak : '—'}</b><span>${st.streak === 1 ? 'day streak' : 'day streak'}${!st.playedToday && st.streak ? ' · play today to keep it!' : ''}</span></div>
        <div class="kstat"><b>🎁 ${st.toChest}</b><span>right answers to the next chest</span></div>
        <div class="kstat"><b>${st.xp}</b><span>total XP</span></div></div></section>
    ${GZ.open ? `<button onclick="gContinue()" class="btn btn-p w-full py-3">Continue your round (${GZ.open.done} of ${GZ.open.n} done)</button>` : ''}
    <div class="grid grid-cols-2 gap-3">
      ${tile('daily', '⚡', "Today's Round", GZ.topicPacks ? 'What your class studied, plus words and maths' : 'Maths and English words — topics from class come soon', !noRounds && !GZ.open, 'game-tile-main col-span-2')}
      ${tile('revenge', '😤', 'Revenge Round', GZ.revengeDue ? `${GZ.revengeDue} question${GZ.revengeDue > 1 ? 's' : ''} that beat you are back` : 'Nothing to avenge today', GZ.revengeDue > 0 && !noRounds && !GZ.open)}
      ${tile('words', '💣', 'Word Bomb', GZ.words ? 'English words in 20 seconds' : 'Starts from Class 3', GZ.words && !noRounds && !GZ.open)}
      ${tile('math', '🧮', 'Maths Sprint', 'Fast maths, your level', !noRounds && !GZ.open)}
      ${tile('duel', '⚔️', 'Duel a Friend', 'Make a question, challenge a classmate', true)}
    </div>
    <p class="text-sm text-center text-slate-500">${noRounds ? 'That is all the rounds for today — your brain stores it while you sleep. 😴 Come back tomorrow!' : `${GZ.roundsLeft} round${GZ.roundsLeft > 1 ? 's' : ''} left today · ${GZ.seconds} seconds a question`}</p>
    <div class="card"><div class="flex justify-between items-baseline gap-2"><h3 class="h3">Class ${esc(GZ.kid.cls)} mission this week</h3><span class="text-sm text-slate-500">${m.right} / ${m.target}</span></div>
      <div class="h-3 rounded-full bg-paper mt-3 overflow-hidden"><div class="h-3 bg-leaf" style="width:${pct}%"></div></div>
      <p class="text-sm text-slate-600 mt-2">${pct >= 100 ? '🎉 Mission complete! Your whole class did it together.' : 'Every right answer from anyone in your class counts. Do it together!'}</p></div>
    ${GZ.league.length ? `<div class="card"><h3 class="h3">This week's top players</h3><ol class="mt-2 space-y-1">${GZ.league.map((b, i) => `<li class="flex justify-between gap-2 ${b.me ? 'font-semibold text-ink' : ''}"><span>${['🥇', '🥈', '🥉'][i] || (i + 1) + '.'} ${esc(b.name)}${b.me ? ' (you)' : ''}</span><span>${b.xp} XP</span></li>`).join('')}</ol>
      <p class="text-xs text-slate-500 mt-2">A new week starts every Monday — anyone can top it.</p></div>` : ''}
    <div class="card"><h3 class="h3">My sticker book</h3>${GZ.stickers.length ? `<div class="flex flex-wrap gap-2 mt-2">${GZ.stickers.map(x => `<span title="${esc(x[1])}" class="text-3xl">${x[0]}</span>`).join('')}</div>` : `<p class="text-sm text-slate-500 mt-1">Get ${st.toChest} more right answers to open your first chest.</p>`}</div>`;
}

// ---------------------------------------------------------------- GAME ZONE — teacher / principal card (inside Quiz Duel)
async function loadGameT() { try { GT = await gapi('tpacks'); } catch (e) { GT = { error: e.message }; } if (view === 'quizt') render(); }
function gameTeacherCard() {
  if (!GT) { loadGameT(); return '<div class="card text-slate-500">Loading Game Zone…</div>'; }
  if (GT.error) return `<div class="card">${esc(GT.error)}</div>`;
  const flagged = GT.packs.flatMap(p => p.flagged.filter(f => f.q).map(f => ({ ...f, p })));
  return `<div class="card space-y-3"><div class="flex flex-wrap justify-between gap-2"><h3 class="h3">Game Zone question packs</h3>
      <span class="pill ${GT.aiReady ? 'bg-leaf/10 text-leaf' : 'bg-marigold/20 text-ink'}">${GT.aiReady ? 'AI connected' : 'AI not connected'}</span></div>
    <p class="text-sm text-slate-600">Maths and English games run by themselves. ${GT.aiReady ? 'For school subjects, packs are made automatically from your diary topics and finished chapters, and every answer is checked a second time before students see it.' : 'To get school-subject questions made automatically from your diary topics, the office has to add the AI key (SCHOOL_AI_API_KEY and SCHOOL_AI_MODEL in config.php).'}</p>
    <div class="flex flex-wrap gap-2 text-sm">${Object.entries(GT.taught).map(([c, n]) => `<span class="pill bg-paper text-ink">Class ${esc(c)}: ${GT.packs.filter(p => p.cls === c && p.qs.length).length} pack(s) · ${n} topic(s) taught</span>`).join('')}</div>
    ${GT.aiReady ? '<button onclick="gMakePacks()" class="btn btn-l">Make packs now for topics without one</button>' : ''}
    ${flagged.length ? `<div><h4 class="font-semibold mt-2">Reported by students</h4>${flagged.map(f => `<div class="border border-line rounded-2xl p-3 mt-2 text-sm"><b>Class ${esc(f.p.cls)} · ${esc(f.p.subj)} · ${esc(f.p.topic)}</b> — reported ${f.n}× ${f.hidden ? '<span class="pill bg-rose/10 text-rose">hidden</span>' : ''}
      <p class="mt-1">${esc(f.q.q)}</p><p class="text-slate-600">Answer given: ${esc(f.q.opts[f.q.ans])}</p>
      <div class="flex gap-2 mt-2"><button onclick="gFix(${jsq(f.p.id)},${f.i},'restore')" class="btn btn-sm btn-l">It is correct — show again</button><button onclick="gFix(${jsq(f.p.id)},${f.i},'remove')" class="btn btn-sm btn-r">Remove it</button></div></div>`).join('')}</div>` : ''}
  </div>`;
}
async function gMakePacks() { toast('Making packs… this can take a minute', 'b'); try { const r = await gapi('makepacks', {}); toast(`${r.made} pack(s) made${r.failed ? ', ' + r.failed + ' could not be made' : ''}`); GT = null; render(); } catch (e) { toast(e.message, 'r'); } }
async function gFix(pid, i, action) { try { await gapi('packfix', { pid, i, action }); toast(action === 'restore' ? 'Shown again' : 'Removed'); GT = null; render(); } catch (e) { toast(e.message, 'r'); } }

// ---------------------------------------------------------------- routes
const VIEWS = { dash: vDash, staff: vStaff, timetable: vTimetable, attendance: vAttendance, diaries: vDiaries, syllabus: vSyllabus,
  results: vResults, students: vStudents, notices: vNotices, mytoday: vMyToday, myweek: vMyWeek, diary: vDiary, marks: vMarks,
  leave: vLeave, log: vLog, settings: vSettings, calendar: vCalendar, behaviour: vBehaviour, speaking: vSpeaking,
  lplan: vLessonPlan, lplans: vLessonPlans, fame: vFame, exams: vExams, quiz: vQuiz, quizt: vQuizT, game: vGame, fees: vFees, pfees: vPFees, fdesk: vFDesk, salary: vSalary, admin: vAdmin, rules: vRules, fdues: vFDues, fcalls: vFCalls, freceipts: vFReceipts, phome: vPHome, phw: vPHw, presults: vPResults, pnotices: vPNotices };
// principal also teaches: give the principal access to diary/marks from "My Teaching"
NAV.principal.splice(NAV.principal.findIndex(x => x[0] === 'mytoday') + 1, 0, ['diary','fa-book','My Diary'], ['lplan','fa-clipboard-check','My Lesson Plan'], ['marks','fa-pen-to-square','Marks Entry']);

// Learning Lab uses a separate subject/child-scoped PHP API, sharing the existing login.
NAV.principal.push(['learning','fa-seedling','Learning Lab']);
NAV.teacher.push(['learning','fa-seedling','Learning Lab']);
NAV.parent.push(['learning','fa-seedling','Learning Adventure']);
VIEWS.learning=()=>'<iframe title="Learning Lab" src="learning.php?embed=1" style="width:100%;min-height:85vh;border:0;border-radius:16px"></iframe>';

// ---------------------------------------------------------------- init
(async function init() {
  TOKEN = tokenGet();
  if (TOKEN) {
    try { if (tokenRole() === 'parent' || tokenRole() === 'student') await loadParent(); else if (tokenRole() === 'accountant' || tokenRole() === 'reception') await loadOffice(); else await loadAll(); render(); return; }
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
VIEWS.studio=()=>'<iframe title="Teacher Smart Studio" src="studio.php?embed=1" style="width:100%;min-height:90vh;border:0;border-radius:16px"></iframe>';

for(const role of ['principal','teacher']) NAV[role].push(['toolkit','fa-wand-magic-sparkles','Teacher Toolkit']);
VIEWS.toolkit=()=>'<iframe title="Teacher Smart Toolkit" src="toolkit.php?embed=1" style="width:100%;min-height:90vh;border:0;border-radius:16px"></iframe>';
function teacherCorner(date){const p=TeacherEnglish.today(date);return `<div class="modern-bento"><section class="modern-welcome"><span class="eyebrow">YOUR TEACHING COMPANION</span><h2>A little less admin.<br>More room to teach.</h2><p>Your lesson tools, classroom moments and professional identity, in one place.</p><div class="modern-links"><button class="btn btn-p" onclick="go('toolkit')">Open Teacher Toolkit</button><button class="btn btn-l" onclick="go('profile')">My Profile Studio →</button></div></section><section class="modern-mini"><small>A phrase for your classroom</small><b>${esc(p[0])}</b><details><summary>Hindi meaning</summary>${esc(p[1])}</details><button class="btn btn-sm btn-l" onclick="TeacherEnglish.say(TeacherEnglish.today('${date}')[0])">Listen & try</button></section></div>`;}

function prepareDiaryDraft(date){if(S.signed[date]?.[U.tid])return toast('Signed diary is locked.','a');let n=0;for(const b of myBells(U.tid,date)){const field=document.getElementById('b'+b.bell+'-t');if(!field||field.value.trim()||diaryEntry(date,U.tid,b.bell))continue;const plan=Object.values(S.lplan).find(p=>p.tid===U.tid&&p.week===weekStart(date)&&String(p.cls)===String(b.cls)&&p.subj===b.subj&&p.status==='Approved');if(plan){field.value=plan.topics.split('\n').map(x=>x.trim()).filter(Boolean)[0]||'';if(field.value)n++;}}toast(n?'Prepared '+n+' topic drafts. Edit and confirm each bell.':'No empty bells with approved plans. Enter the actual topics.');}

for(const role of ['teacher','principal']) NAV[role].push(['profile','fa-id-card','My Profile'],['saathi','fa-feather','Secret Saathi']);
VIEWS.profile=()=>'<iframe class="edu-iframe" title="Teacher Profile Studio" src="faculty.php?embed=1"></iframe>';
VIEWS.saathi=()=>'<iframe class="edu-iframe" title="Secret Saathi" src="faculty.php?embed=1&tab=saathi"></iframe>';
NAV.parent.push(['faculty','fa-chalkboard-user','Our Teachers']);
VIEWS.faculty=()=>head('Our Teachers','School-reviewed professional profiles shared with your class.')+'<div id="parentFaculty" class="modern-bento">Loading teacher profiles…</div>';
async function loadParentFaculty(){try{const r=await fetch('faculty_api.php?a=directory',{headers:{'X-School-Token':TOKEN}});const d=await r.json();if(!r.ok||!d.ok)throw Error(d.error||'Profiles unavailable');const node=document.getElementById('parentFaculty');if(node)node.innerHTML=d.profiles.map(p=>`<article class="card">${p.photoData?`<img src="${p.photoData}" alt="Teacher portrait" style="width:72px;height:72px;object-fit:cover;border-radius:22px;margin-bottom:12px">`:''}<h3 class="h3">${esc(p.fullName)}</h3><p class="text-sm text-slate-500">${esc(p.designation)}</p><p class="text-sm mt-3">${esc(p.bio)}</p><p class="text-sm mt-2">${esc(p.quote)}</p><p class="text-xs text-slate-500 mt-3">${esc((p.subjects||[]).join(' · '))}</p></article>`).join('')||'<div class="card text-sm">Your teachers’ profiles will appear here after verification and sharing permission.</div>';}catch(e){const node=document.getElementById('parentFaculty');if(node)node.textContent=e.message;}}

// Detailed Fee Department: shared token, scoped API, immutable financial entries.
for (const role of ['principal','accountant','reception','chairman','teacher']) NAV[role].push(['finance','fa-wallet','Fee Department']);
for (const role of ['parent','student']) NAV[role].push(['finance','fa-wallet','Fee Ledger']);
for (const role of ['principal','accountant']) { const old=NAV[role].find(n=>n[0]==='fdesk');if(old)old[2]='Legacy Fee Desk'; }
NAV.accountant.unshift(NAV.accountant.pop());PRIMARY.accountant=['finance','fdues','fcalls','freceipts'];
// Session switcher (22 Oct 2026, owner: "old ERP had a session button"): like the old ERP's 2023-2024…2026-2027 menu.
// It only changes which session THIS login looks at in School Office and Fee Department (kept in this browser);
// the school's current session is still set by the Principal in Settings, so nobody else's screen changes.
function curSession() { const m = String(S && S.settings && S.settings.session || '').replace(/[–—]/g, '-').match(/^(20\d{2})-(?:20)?(\d{2})$/); return m ? m[1] + '-' + m[2] : ''; }
function sessionList() { const c = curSession(); const y = c ? +c.slice(0, 4) : new Date().getFullYear(); const out = []; for (let k = 2023; k <= y + 1; k++) out.push(k + '-' + String((k + 1) % 100).padStart(2, '0')); return out.reverse(); }
function viewSession() { let v = ''; try { v = localStorage.getItem('sch_view_session') || ''; } catch (e) {} return sessionList().includes(v) ? v : curSession(); }
function setViewSession(x) { try { if (x === curSession()) localStorage.removeItem('sch_view_session'); else localStorage.setItem('sch_view_session', x); } catch (e) {} toast('Showing session ' + x); render(); }
function canViewSession() { return !!U && ['principal', 'chairman', 'accountant', 'reception'].includes(U.role); }
VIEWS.finance=()=>'<iframe class="edu-iframe" title="Fee Department" src="finance.php?embed=1&session='+encodeURIComponent(viewSession())+(P&&P.tab?'&tab='+encodeURIComponent(P.tab):'')+(typeof classicOn==='function'&&classicOn()?'&look=classic':'')+'"></iframe>';
// School Office (18 Oct 2026+): the old ERP's remaining menus as registers — registers.php / registers_api.php / registers_lib.php
VIEWS.office=()=>'<iframe class="edu-iframe" title="School Office" src="registers.php?embed=1&session='+encodeURIComponent(viewSession())+(P&&P.m?'&m='+encodeURIComponent(P.m):'')+(P&&P.t?'&t='+encodeURIComponent(P.t):'')+(typeof classicOn==='function'&&classicOn()?'&look=classic':'')+'"></iframe>';
// Fee Receipt — old-ERP style fee collection (school/feecollect.php), the one place fees are taken once a session is started there.
for (const role of ['principal','chairman','accountant','reception']) NAV[role].push(['feereceipt','fa-receipt','Fee Receipt']);
VIEWS.feereceipt=()=>'<iframe class="edu-iframe" title="Fee Receipt" src="feecollect.php?embed=1&session='+encodeURIComponent(viewSession())+(typeof classicOn==='function'&&classicOn()?'&look=classic':'')+'"></iframe>';
// Progress Report — old ERP Examination-I cards + marks entry in the same components (school/progress.php).
for (const role of ['principal','chairman','teacher']) NAV[role].push(['progress','fa-file-lines','Progress Report']);
// Student Master — the old ERP's Add Student / Edit-Update Student / Student Search form, field for field (school/stumaster.php).
for (const role of ['principal','chairman']) NAV[role].push(['stumaster','fa-user-graduate','Student Master']);
VIEWS.stumaster=()=>'<iframe class="edu-iframe" title="Student Master" src="stumaster.php?embed=1&m='+encodeURIComponent(P&&P.m?P.m:'list')+(P&&P.id?'&id='+encodeURIComponent(P.id):'')+(typeof classicOn==='function'&&classicOn()?'&look=classic':'')+'"></iframe>';
// Fee Reports — the old ERP's fee reports in its own layout (school/feereports.php), old ERP and new receipts together.
for (const role of ['principal','chairman','accountant']) NAV[role].push(['feereports','fa-file-invoice','Fee Reports']);
VIEWS.feereports=()=>'<iframe class="edu-iframe" title="Fee Reports" src="feereports.php?embed=1&r='+encodeURIComponent(P&&P.r?P.r:'fee1')+(typeof classicOn==='function'&&classicOn()?'&look=classic':'')+'"></iframe>';
// Old ERP Reports — every list/report page copied from the old ERP, read-only (school/oldreports.php).
for (const role of ['principal','chairman','accountant','reception']) NAV[role].push(['oldreports','fa-box-archive','Old ERP Reports']);
VIEWS.oldreports=()=>'<iframe class="edu-iframe" title="Old ERP Reports" src="oldreports.php?embed=1&session='+encodeURIComponent(typeof viewSession==='function'?viewSession():'')+(P&&P.page?'&page='+encodeURIComponent(P.page):'')+(typeof classicOn==='function'&&classicOn()?'&look=classic':'')+'"></iframe>';
VIEWS.progress=()=>'<iframe class="edu-iframe" title="Progress Report" src="progress.php?embed=1&session='+encodeURIComponent(typeof viewSession==='function'?viewSession():'')+(typeof classicOn==='function'&&classicOn()?'&look=classic':'')+'"></iframe>';
