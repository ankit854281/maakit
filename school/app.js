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
    marks: {}, leaves: [], notices: []
  };
}
function emptyState() {
  return { settings: {}, classes: [], teachers: {}, alloc: [], grid: null, students: [], staffAtt: {}, studAtt: {}, diary: {},
           signed: {}, syl: {}, exams: [], marks: {}, leaves: [], notices: [], log: [] };
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
const DELETABLE = ['student', 'notice', 'teacher'];
const SAFE_VIEWS = ['dash', 'staff', 'timetable', 'diaries', 'results', 'log', 'mytoday', 'myweek', 'attendance'];
let syncTimer = null, syncing = false, netState = 'ok';

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
  Object.entries(S.signed).forEach(([d, byT]) => Object.entries(byT).forEach(([t, v]) => put('signed', d + '|' + t, v)));
  Object.entries(S.syl).forEach(([k, v]) => put('syl', k, v));
  Object.entries(S.marks).forEach(([e, byK]) => Object.entries(byK).forEach(([k, v]) => put('marks', e + '|' + k, v)));
  S.leaves.forEach(l => put('leave', l.id, l));
  S.notices.forEach(n => put('notice', n.id, n));
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
    case 'signed': (S.signed[p[0]] = S.signed[p[0]] || {})[p[1]] = d; break;
    case 'syl': S.syl[r] = d; break;
    case 'marks': { const i = r.indexOf('|'); (S.marks[r.slice(0, i)] = S.marks[r.slice(0, i)] || {})[r.slice(i + 1)] = d; break; }
    case 'leave': { const i = S.leaves.findIndex(x => x.id === r); if (i >= 0) S.leaves[i] = d; else S.leaves.push(d); S.leaves.sort((a, b) => (b.at || 0) - (a.at || 0)); break; }
    case 'notice': { const i = S.notices.findIndex(x => x.id === r); if (i >= 0) S.notices[i] = d; else S.notices.push(d); S.notices.sort((a, b) => String(b.date).localeCompare(String(a.date))); break; }
  }
}
function removeRec(k, r) {
  if (k === 'student') S.students = S.students.filter(x => x.id !== r);
  else if (k === 'notice') S.notices = S.notices.filter(x => x.id !== r);
  else if (k === 'teacher') delete S.teachers[r];
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

function save() { clearTimeout(syncTimer); syncTimer = setTimeout(sync, 250); }
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
    if (res.rejected && res.rejected.length) { toast(res.rejected.length + ' change(s) are not allowed for your login and were not saved.', 'a'); setTimeout(async () => { try { await loadAll(); render(); } catch (e) {} }, 800); }
    setNet('ok');
  } catch (e) {
    pendingLog.unshift(...logs);
    setNet('off');
    setTimeout(sync, 15000);
  } finally { syncing = false; }
}
async function poll() {
  if (!TOKEN || !S || document.hidden) return;
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
  netState = s; const el = document.getElementById('net'); if (!el) return;
  el.innerHTML = s === 'saving' ? '<i class="fa-solid fa-rotate fa-spin"></i> Saving…'
    : s === 'off' ? '<i class="fa-solid fa-wifi"></i> Offline — will retry' : '<i class="fa-solid fa-cloud"></i> Saved';
  el.className = 'text-[11px] font-semibold px-2 py-1 rounded-lg ' + (s === 'off' ? 'bg-rose-600' : s === 'saving' ? 'bg-amber-500 text-indigo-950' : 'bg-indigo-800');
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
  const d = dayIdx(date); if (d < 0 || !S.grid) return [];
  const absent = staffAbsent(date), extra = {}, slips = [];
  absent.forEach(t => BELLS.forEach((b, i) => {
    const a = teacherAt(t, d, i); if (!a) return;
    const taken = slips.filter(s => s.bell === i).map(s => s.sub);
    const pool = Object.keys(S.teachers).filter(x => !absent.includes(x) && !teacherAt(x, d, i) && !taken.includes(x)
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
  const d = dayIdx(date); if (d < 0 || !S.grid) return [];
  if (staffAbsent(date).includes(tid)) return [];
  const out = [];
  BELLS.forEach((b, i) => { const a = teacherAt(tid, d, i); if (a) out.push({ bell: i, cls: a.c, subj: a.s, arr: false }); });
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
  if (y.targetCount && y.targetDate) {
    const st = new Date(S.settings.sessionStart + 'T00:00:00'), en = new Date(y.targetDate + 'T00:00:00'), now = new Date(date + 'T00:00:00');
    const f = Math.max(0, Math.min(1, (now - st) / Math.max(1, en - st)));
    expected = Math.round(y.targetCount * f * 10) / 10;
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
  if (!isToday || nowMin() >= toMin(S.settings.attendanceCutoff)) {
    const missing = CLASS_NAMES.filter(c => studentsOf(c).length && !attOf(date, c));
    if (missing.length) A.push({ lv:'r', x:`Student attendance not marked: Class ${missing.join(', ')}`, go:'attendance' });
  }
  const ds = diaryStats(date); const pend = Object.entries(ds.pendingBy);
  if (pend.length) A.push({ lv:'a', x:`Diary pending: ${pend.map(([t, n]) => T(t).name + ' (' + n + ')').join(', ')}`, go:'diaries', wa: pend.map(([t]) => t) });
  const unc = arrangement(date).filter(s => !s.sub);
  if (unc.length) A.push({ lv:'r', x:`${unc.length} bell(s) with no free teacher to cover — see Arrangement`, go:'staff' });
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
              ['results','fa-square-poll-vertical','Results'],['students','fa-children','Students'],['notices','fa-bullhorn','Notices'],
              ['mytoday','fa-chalkboard-user','My Teaching'],['log','fa-clock-rotate-left','Activity Log'],['settings','fa-gear','Settings']],
  teacher:   [['mytoday','fa-sun','Today'],['attendance','fa-clipboard-user','Attendance'],['diary','fa-book','Diary'],['syllabus','fa-list-check','Syllabus'],
              ['marks','fa-pen-to-square','Marks'],['myweek','fa-calendar-week','My Week'],['leave','fa-plane-departure','Leave']],
  chairman:  [['dash','fa-gauge','Dashboard'],['results','fa-square-poll-vertical','Results'],['syllabus','fa-list-check','Syllabus'],['log','fa-clock-rotate-left','Activity Log']]
};

function render() {
  const root = document.getElementById('root');
  if (!U || !S) { root.innerHTML = vLogin(); return; }
  if (!view) view = NAV[U.role][0][0];
  const nav = NAV[U.role].map(([k, ic, lb]) => `<button onclick="go('${k}')" class="navb ${view === k ? 'on' : ''}"><i class="fa-solid ${ic} mr-1.5"></i>${lb}</button>`).join('');
  let body = '';
  try { body = (VIEWS[view] || vDash)(); } catch (e) { console.error(e); body = `<div class="card text-rose-700">Something went wrong: ${esc(e.message)}</div>`; }
  root.innerHTML = `
    <header class="noprint bg-indigo-900 text-white sticky top-0 z-30 shadow">
      <div class="max-w-6xl mx-auto px-4 pt-3 flex items-center justify-between gap-3">
        <div class="flex items-center gap-3 min-w-0">
          <div class="w-10 h-10 rounded-xl bg-amber-400 text-indigo-950 font-black flex items-center justify-center shrink-0">SN</div>
          <div class="min-w-0"><div class="font-bold leading-tight truncate">${esc(S.settings.school)}</div>
          <div class="text-[11px] text-indigo-200">Academic ERP &bull; Session ${esc(S.settings.session)} &bull; <span class="text-amber-300 font-semibold">Powered by Maakit</span></div></div>
        </div>
        <div class="flex items-center gap-2 shrink-0">
          <span id="net" class="text-[11px] font-semibold px-2 py-1 rounded-lg bg-indigo-800"><i class="fa-solid fa-cloud"></i> Saved</span>
          <span class="hidden sm:block text-xs text-right"><span class="block font-bold">${esc(userName())}</span><span class="text-indigo-200 capitalize">${U.role}</span></span>
          <button onclick="logout()" class="btn btn-sm bg-indigo-800 hover:bg-indigo-700"><i class="fa-solid fa-right-from-bracket"></i><span>Logout</span></button>
        </div>
      </div>
      <nav class="max-w-6xl mx-auto px-4 py-2 flex gap-1 overflow-x-auto noscroll">${nav}</nav>
    </header>
    <main class="max-w-6xl w-full mx-auto p-4 space-y-4">${body}</main>`;
}

// ---------------------------------------------------------------- login / setup
let loginRole = 'teacher', busy = false;
function vLogin() {
  if (!STATUS) return `<div class="flex-1 flex items-center justify-center p-6 text-slate-500">Loading…</div>`;
  const card = inner => `<div class="flex-1 flex items-center justify-center p-4 bg-indigo-950">
    <div class="w-full max-w-sm bg-white rounded-3xl shadow-2xl p-6 space-y-4">
      <div class="text-center">
        <div class="w-14 h-14 mx-auto rounded-2xl bg-amber-400 text-indigo-950 font-black text-xl flex items-center justify-center">SN</div>
        <h1 class="font-extrabold text-lg mt-2 leading-tight">${esc(STATUS.school)}</h1>
        <p class="text-xs text-slate-500">Academic ERP</p>
      </div>${inner}<p class="text-[11px] text-slate-400 text-center">Powered by Maakit</p></div></div>`;
  if (STATUS.setup) return card(`
      <div class="p-3 rounded-xl bg-amber-50 border border-amber-200 text-sm">First-time setup. Enter the <b>Maakit website update key</b> (the one in config.php) and choose the Principal's PIN.</div>
      <div><label class="lbl">Website update key</label><input id="su-key" type="password" class="inp" autocomplete="off"></div>
      <div><label class="lbl">New Principal PIN (6–8 digits)</label><input id="su-pin" type="password" inputmode="numeric" maxlength="8" class="inp text-center tracking-[0.4em]"></div>
      <div><label class="lbl">Type the PIN again</label><input id="su-pin2" type="password" inputmode="numeric" maxlength="8" class="inp text-center tracking-[0.4em]"></div>
      <button onclick="doSetup()" class="btn btn-p w-full py-3">Set up school</button>`);
  const tOpts = STATUS.teachers.map(t => `<option value="${esc(t.tid)}">${esc(t.name)}${t.pin ? '' : ' (no PIN yet)'}</option>`).join('');
  return card(`
      <div class="grid grid-cols-3 gap-1 bg-slate-100 p-1 rounded-xl text-sm font-bold">
        ${['teacher','principal','chairman'].map(r => `<button onclick="loginRole='${r}';render()" class="py-2 rounded-lg capitalize ${loginRole === r ? 'bg-white shadow text-indigo-800' : 'text-slate-500'}">${r}</button>`).join('')}
      </div>
      ${loginRole === 'teacher' ? (STATUS.teachers.length ? `<div><label class="lbl">Your name</label><select id="lg-t" class="inp">${tOpts}</select></div>` : '<p class="text-sm text-slate-500">The Principal must log in once first.</p>') : ''}
      <div><label class="lbl">PIN</label><input id="lg-pin" type="password" inputmode="numeric" maxlength="8" class="inp text-center tracking-[0.5em] text-lg" onkeydown="if(event.key==='Enter')login()"></div>
      <button onclick="login()" class="btn btn-p w-full py-3"><i class="fa-solid fa-lock-open"></i> Login</button>
      <p class="text-[11px] text-slate-400 text-center">Forgot your PIN? Ask the Principal to set a new one.</p>`);
}
async function doSetup() {
  if (busy) return;
  const key = document.getElementById('su-key').value.trim(), pin = document.getElementById('su-pin').value.trim();
  if (pin !== document.getElementById('su-pin2').value.trim()) return toast('The two PINs do not match', 'a');
  if (!/^\d{6,8}$/.test(pin)) return toast('PIN must be 6 to 8 digits', 'a');
  busy = true;
  try { await api('setup', { key, pin }); loginRole = 'principal'; await doLogin('principal', null, pin); }
  catch (e) { toast(e.message, 'r'); } finally { busy = false; }
}
async function login() {
  if (busy) return;
  const pin = document.getElementById('lg-pin').value.trim();
  const tid = loginRole === 'teacher' ? (document.getElementById('lg-t') || {}).value : null;
  if (!pin) return toast('Enter your PIN', 'a');
  busy = true;
  try { await doLogin(loginRole, tid, pin); } catch (e) { toast(e.message, 'r'); } finally { busy = false; }
}
async function doLogin(role, tid, pin) {
  const res = await api('login', { role, tid, pin });
  TOKEN = res.token; tokenSet(TOKEN);
  await loadAll();
  view = null; render(); setNet('ok');
}
async function logout() {
  await sync();
  try { await api('logout', {}); } catch (e) {}
  forceLogout();
}
function forceLogout() {
  TOKEN = null; tokenSet(null); U = null; ME = null; S = null; SYNCED = {}; pendingLog = [];
  refreshStatus();
}
async function refreshStatus() {
  try { STATUS = await api('status'); } catch (e) { STATUS = null; document.getElementById('root').innerHTML = `<div class="flex-1 flex items-center justify-center p-6 text-center"><div><p class="font-bold">Cannot reach the server.</p><p class="text-sm text-slate-500">${esc(e.message)}</p><button onclick="refreshStatus()" class="btn btn-p mt-3">Try again</button></div></div>`; return; }
  render();
}

// ---------------------------------------------------------------- shared bits
function head(title, sub, right) {
  return `<div class="flex flex-col md:flex-row md:items-end justify-between gap-3"><div><h2 class="text-xl font-extrabold">${title}</h2>${sub ? `<p class="text-sm text-slate-500">${sub}</p>` : ''}</div>${right ? `<div class="flex flex-wrap gap-2 noprint">${right}</div>` : ''}</div>`;
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
  const tile = (lbl, val, sub, ic, col) => `<div class="card flex items-center justify-between"><div><p class="text-[11px] font-bold uppercase text-slate-500">${lbl}</p><p class="text-2xl font-extrabold mt-1">${val}</p><p class="text-xs text-slate-500 mt-1">${sub}</p></div><div class="w-11 h-11 rounded-xl ${col} flex items-center justify-center text-lg"><i class="fa-solid ${ic}"></i></div></div>`;
  const nT = Object.keys(S.teachers).length;
  const sum = dailySummary(date);
  return head('School Dashboard', fmtDate(date) + (dayIdx(date) >= 0 ? ' • ' + DAYS[dayIdx(date)] + ' timetable' : ''), dateInput('d-date', date, "go('dash',{date:this.value})")) + `
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
      ${tile('Staff Present', (nT - ab.length) + '/' + nT, ab.length ? 'Absent: ' + ab.map(t => T(t).name).join(', ') : 'Everyone present', 'fa-user-check', 'bg-emerald-50 text-emerald-700')}
      ${tile('Students Present', as.total ? Math.round(as.present / as.total * 100) + '%' : '—', as.marked + '/' + CLASS_NAMES.length + ' classes marked', 'fa-children', 'bg-blue-50 text-blue-700')}
      ${tile('Teacher Diary', ds.filled + '/' + ds.due, 'bells filled (finished bells)', 'fa-book-open', 'bg-purple-50 text-purple-700')}
      ${tile('Syllabus Behind', lag, 'of ' + sylKeys.length + ' tracked subjects', 'fa-chart-line', lag ? 'bg-rose-50 text-rose-700' : 'bg-emerald-50 text-emerald-700')}
    </div>
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
        : `<p class="text-sm text-slate-500">${dayIdx(date) < 0 ? 'Sunday — no classes.' : 'No bells assigned.'}</p>`}</div>
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
    ${bells.length ? bells.map(b => { const e = diaryEntry(date, tid, b.bell) || {}; const nx = nextChapter(b.cls, b.subj); const id = 'b' + b.bell;
      return `<div class="card space-y-2 ${P.bell === b.bell ? 'ring-2 ring-indigo-500' : ''}">
        <div class="flex justify-between items-start gap-2"><div><b>${BELLS[b.bell].n} bell • Class ${b.cls} • ${esc(b.subj)}</b> ${b.arr ? '<span class="pill bg-rose-600 text-white">Arrangement</span>' : ''}<br><span class="text-xs text-slate-500">${t12(BELLS[b.bell].s)}–${t12(BELLS[b.bell].e)}</span></div>
        ${e.at ? `<span class="pill bg-emerald-100 text-emerald-800">Saved ${fmtTime(e.at)}</span>` : ''}${S.signed[date] && S.signed[date][tid] ? '<span class="pill bg-indigo-100 text-indigo-800">Signed</span>' : ''}</div>
        <div><label class="lbl">Topic taught</label><input id="${id}-t" class="inp" value="${esc(e.topic || '')}" placeholder="${nx ? 'Next chapter: ' + esc(nx) : 'What did you teach?'}">
          ${nx && !e.topic ? `<button onclick="document.getElementById('${id}-t').value=${esc(JSON.stringify(nx))}" class="text-xs text-indigo-700 font-bold mt-1">Use: ${esc(nx)}</button>` : ''}</div>
        <div><label class="lbl">Homework (max 20 min)</label><input id="${id}-h" class="inp" value="${esc(e.hw || '')}" placeholder="e.g. Ex 4.1 Q1–Q4"></div>
        ${nx && !b.arr ? `<label class="flex items-center gap-2 text-sm"><input id="${id}-c" type="checkbox" class="w-4 h-4"> Chapter "<b>${esc(nx)}</b>" finished today</label>` : ''}
        <button onclick="diarySave('${date}',${b.bell})" class="btn btn-p w-full"><i class="fa-solid fa-floppy-disk"></i> Save</button></div>`; }).join('')
      : `<div class="card text-sm text-slate-500">${dayIdx(date) < 0 ? 'Sunday — no classes.' : 'No bells for this day.'}</div>`}`;
}
function diarySave(date, bell) {
  const tid = U.tid, b = myBells(tid, date).find(x => x.bell === bell); if (!b) return;
  const id = 'b' + bell, topic = document.getElementById(id + '-t').value.trim(), hw = document.getElementById(id + '-h').value.trim();
  if (!topic) return toast('Write the topic taught', 'a');
  S.diary[date] = S.diary[date] || {}; S.diary[date][tid] = S.diary[date][tid] || {};
  S.diary[date][tid][bell] = { cls: b.cls, subj: b.subj, topic, hw, arr: b.arr, at: Date.now() };
  log('Diary', `${BELLS[bell].n} bell, Class ${b.cls} ${b.subj}: "${topic}"` + (hw ? ` • HW: ${hw}` : ''));
  const cb = document.getElementById(id + '-c');
  if (cb && cb.checked) { const y = S.syl[sylKey(b.cls, b.subj)], ch = y && y.chapters.find(x => !x.done);
    if (ch) { ch.done = date; log('Syllabus', `Class ${b.cls} ${b.subj}: chapter "${ch.n}" completed (from diary)`); } }
  save(); toast('Diary saved'); go('diary', { date });
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
  const key = P.key && mine.some(a => sylKey(a.c, a.s) === P.key) ? P.key : sylKey(mine[0].c, mine[0].s);
  const [c, subj] = key.split('|'); const rec = (S.marks[ex.id] || {})[key] || { max: 25, m: {} };
  const list = studentsOf(c);
  const vals = list.map(s => rec.m[s.id]).filter(v => v !== undefined && v !== '' && v !== 'AB').map(Number);
  const avg = vals.length ? Math.round(vals.reduce((a, b) => a + b, 0) / vals.length / rec.max * 100) : null;
  return head('Marks Entry', 'Type marks; write AB for absent. Totals, grades and weak-student alerts are automatic.') + `
    <div class="card grid grid-cols-1 sm:grid-cols-3 gap-3">
      <div><label class="lbl">Exam</label><select class="inp" onchange="go('marks',{ex:this.value,key:${jsq(key)}})">${S.exams.map(e => `<option value="${e.id}" ${e.id === ex.id ? 'selected' : ''}>${esc(e.name)} (${fmtDate(e.date)})</option>`).join('')}</select></div>
      <div><label class="lbl">Class & subject</label><select class="inp" onchange="go('marks',{ex:'${ex.id}',key:this.value})">${mine.map(a => { const k = sylKey(a.c, a.s); return `<option value="${esc(k)}" ${k === key ? 'selected' : ''}>Class ${a.c} — ${esc(a.s)}</option>`; }).join('')}</select></div>
      <div><label class="lbl">Maximum marks</label><input id="mk-max" type="number" min="1" class="inp" value="${rec.max}"></div>
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
function printMarksheets(exId, c) {
  const ex = S.exams.find(e => e.id === exId); const { subs, rows, M } = resultTable(exId, c);
  if (!subs.length) return toast('No marks to print', 'a');
  printHTML('Marksheets', rows.map(r => `<div class="pb">${printHeader('Marksheet — ' + esc(ex.name) + ' • Session ' + esc(S.settings.session))}
    <h2>${esc(r.s.name)} • Class ${c} • Adm No ${esc(r.s.adm)}</h2>
    <table><tr><th>Subject</th><th>Max</th><th>Obtained</th><th>Grade</th></tr>${subs.map((sb, i) => { const v = r.cells[i], mx = M[c + '|' + sb].max; return `<tr><td>${esc(sb)}</td><td>${mx}</td><td>${esc(v)}</td><td>${v !== '' && v !== 'AB' ? grade(v / mx * 100) : '-'}</td></tr>`; }).join('')}
    <tr><th>Total</th><th>${r.max}</th><th>${r.got}</th><th>${r.pct != null ? r.pct + '% (' + grade(r.pct) + ')' : '-'}</th></tr></table>
    <p>Rank in class: ${r.rank || '-'}</p><div class="f"><span>Class Teacher ________</span><span>Principal ________</span><span>Powered by Maakit</span></div></div>`).join(''));
  log('Results', `Printed ${ex.name} marksheets for Class ${c}`);
}

// ---------------------------------------------------------------- STUDENTS (principal)
function vStudents() {
  const c = P.c || 'ALL'; const list = (c === 'ALL' ? S.students : S.students.filter(s => s.cls === c)).sort((a, b) => CLASS_NAMES.indexOf(a.cls) - CLASS_NAMES.indexOf(b.cls) || a.name.localeCompare(b.name));
  const nSample = S.students.filter(s => s.sample).length;
  return head('Students', S.students.length + ' students' + (nSample ? ` • ${nSample} are sample entries` : ''), nSample ? `<button onclick="removeSamples()" class="btn btn-r"><i class="fa-solid fa-broom"></i> Remove sample students</button>` : '') + `
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
      <div class="card lg:col-span-2 overflow-x-auto">
        <div class="flex flex-wrap gap-1 mb-3">${['ALL', ...CLASS_NAMES].map(x => `<button onclick="go('students',{c:'${x}'})" class="btn btn-sm ${x === c ? 'btn-p' : 'btn-l'}">${x === 'ALL' ? 'All' : x}</button>`).join('')}</div>
        <table class="tbl"><tr><th>Adm</th><th>Name</th><th>Class</th><th>Father</th><th>Phone</th><th></th></tr>${list.map(s => `<tr><td>${esc(s.adm)}</td><td class="font-semibold">${esc(s.name)}${s.sample ? ' <span class="pill bg-slate-100 text-slate-500">sample</span>' : ''}</td><td>${s.cls}</td><td>${esc(s.father)}</td><td>${esc(s.phone)}</td>
          <td><button onclick="editStudent('${s.id}')" class="text-indigo-600"><i class="fa-solid fa-pen"></i></button> <button onclick="delStudent('${s.id}')" class="text-rose-500 ml-2"><i class="fa-solid fa-trash"></i></button></td></tr>`).join('')}</table>
      </div>
      <div class="space-y-4">
        <div class="card space-y-2"><h3 class="font-bold">Add student</h3>
          <input id="st-adm" class="inp" placeholder="Admission no."><input id="st-name" class="inp" placeholder="Student name">
          <select id="st-cls" class="inp">${CLASS_NAMES.map(x => `<option>${x}</option>`).join('')}</select>
          <input id="st-fa" class="inp" placeholder="Father's name"><input id="st-ph" class="inp" placeholder="Parent mobile (10 digits)" inputmode="tel">
          <button onclick="addStudent()" class="btn btn-p w-full">Add</button></div>
        <div class="card space-y-2"><h3 class="font-bold">Import from Excel</h3>
          <p class="text-xs text-slate-500">Copy columns from Excel in this order and paste: <b>Adm No, Name, Class, Father, Mobile</b>. Class must be like 10th, 9th … 1st, UKG, LKG, PG.</p>
          <textarea id="st-imp" rows="5" class="inp text-xs" placeholder="92	Yashvi Tiwari	10th	Dilip Kumar Tiwari	9839xxxxxx"></textarea>
          <button onclick="importStudents()" class="btn btn-g w-full"><i class="fa-solid fa-file-import"></i> Import</button></div>
      </div>
    </div>`;
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
  const pin = prompt('New PIN (6 to 8 digits)'); if (pin === null) return;
  if (!/^\d{6,8}$/.test(pin.trim())) return toast('PIN must be 6 to 8 digits', 'a');
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
function pinBadge(uid) { return PINS[uid] ? '<span class="pill bg-emerald-100 text-emerald-800">PIN set</span>' : '<span class="pill bg-rose-100 text-rose-800">No PIN</span>'; }
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
        <p class="text-xs text-slate-500">PINs are 6–8 digits and stored only in coded (hashed) form. Nobody, including you, can see a PIN after it is set — just set a new one if someone forgets.</p></div>
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
  const pin = prompt('New PIN for ' + name + ' (6 to 8 digits). Tell it to them privately.'); if (pin === null) return;
  if (!/^\d{6,8}$/.test(pin.trim())) return toast('PIN must be 6 to 8 digits', 'a');
  await sync();
  try { await api('setpin', { uid: uidx, pin: pin.trim() }); PINS[uidx] = true; toast('PIN set for ' + name); render(); } catch (e) { toast(e.message, 'r'); }
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

// ---------------------------------------------------------------- routes
const VIEWS = { dash: vDash, staff: vStaff, timetable: vTimetable, attendance: vAttendance, diaries: vDiaries, syllabus: vSyllabus,
  results: vResults, students: vStudents, notices: vNotices, mytoday: vMyToday, myweek: vMyWeek, diary: vDiary, marks: vMarks,
  leave: vLeave, log: vLog, settings: vSettings };
// principal also teaches: give the principal access to diary/marks from "My Teaching"
NAV.principal.splice(10, 0, ['diary','fa-book','My Diary'], ['marks','fa-pen-to-square','Marks']);

// ---------------------------------------------------------------- init
(async function init() {
  TOKEN = tokenGet();
  if (TOKEN) {
    try { await loadAll(); render(); return; }
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
