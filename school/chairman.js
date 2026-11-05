'use strict';
// SNMSIS Chairman App (school/chairman.php) — built in the same design language as the Maakit Trial App:
// a coloured header whose colour belongs to the chosen tab (Overview, Month, Dues, Calling), quiet white body,
// soft promo cards, round chips, bottom sheets. Opens with a 20-second story-style welcome (Skip any time).
// Data: feedash_api.php — the school's fee cards, receipts of both ERPs and the fee call log. Every figure
// follows the filters (tag, class). Calls are saved with office_api.php?a=call; tags with feedash_api.php?a=setTag.
const $ = id => document.getElementById(id);
const esc = v => String(v ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
const MORDER = [4, 5, 6, 7, 8, 9, 10, 11, 12, 1, 2, 3];
const MN = ['', 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
const M3 = m => MN[m].slice(0, 3);
const CLASSES = ['PG', 'LKG', 'UKG', '1st', '2nd', '3rd', '4th', '5th', '6th', '7th', '8th', '9th', '10th', '11th', '12th'];
const NOTAG = 'No tag';
const isTr = name => /transport/i.test(name);
const LOGO = 'img/snmsis-logo.png';
const THOUGHTS = [
  'A school is built twice — once in brick, and every day in the lives of its children.',
  'Every rupee invested in a child returns as a future.',
  'Great institutions are not built in a day; they are built every day.',
  'Your vision is the roof under which every child learns to dream.',
  'Discipline in small things builds trust in big things.',
  'Numbers tell us where we stand; vision tells us where we go.',
  'The best investment is the one that teaches.',
  'A teacher shapes a class; a leader shapes the whole school.',
  'Consistency is the quiet strength behind every success.',
  'Today’s effort is tomorrow’s reputation.',
  'When the foundation is strong, the building can rise without fear.',
  'Children remember how a school made them feel — and so do their parents.',
  'Growth is not luck; it is care, repeated.',
  'Trust is the real fee a parent pays — and it is earned every day.',
  'Lead with patience, measure with honesty, grow with purpose.',
  'Every child who walks in is a promise you have chosen to keep.',
  'Small improvements every month make a big school every year.',
  'The work you do today echoes in a generation.',
  'Clarity in accounts brings calm in decisions.',
  'An institution is the shadow of its founder’s commitment.',
  'Quality is never an accident; it is the result of intention.',
  'Where there is order, there is room to dream.',
  'Investing in education is investing in the town’s tomorrow.',
  'A good system lets good people do great work.',
  'Strong schools are built by people who refuse to settle.',
  'What gets measured gets improved.',
  'Every bright result started with someone who believed early.',
  'Leadership is turning effort into direction.',
  'The goal is not only to run a school, but to raise a standard.',
  'Progress, not perfection — every single month.'
];
// "SNMSIS" in many languages for the welcome (letters spelt in each script)
const LANGS = [['SNMSIS', 'English'], ['एस एन एम एस आई एस', 'हिन्दी'], ['এস এন এম এস আই এস', 'বাংলা'], ['எஸ் என் எம் எஸ் ஐ எஸ்', 'தமிழ்'], ['ఎస్ ఎన్ ఎమ్ ఎస్ ఐ ఎస్', 'తెలుగు'],
  ['એસ એન એમ એસ આઈ એસ', 'ગુજરાતી'], ['ਐਸ ਐਨ ਐਮ ਐਸ ਆਈ ਐਸ', 'ਪੰਜਾਬੀ'], ['ಎಸ್ ಎನ್ ಎಂ ಎಸ್ ಐ ಎಸ್', 'ಕನ್ನಡ'], ['എസ് എൻ എം എസ് ഐ എസ്', 'മലയാളം'], ['ایس این ایم ایس آئی ایس', 'اردو'],
  ['エス・エヌ・エム・エス・アイ・エス', '日本語'], ['ЭС ЭН ЭМ ЭС АЙ ЭС', 'Русский'], ['إس إن إم إس آي إس', 'العربية'], ['SNMSIS', 'English']];
// Tab colours, as in the Trial App: each tab owns its header colour and accent.
const TH = [
  { name: 'Home', head: 'linear-gradient(180deg,#6366F1 0%,#4338CA 100%)', a: '#4338CA', a2: '#6366F1', soft: '#E0E2FF', tint: '#F1F2FF', sh: 'rgba(67,56,202,.28)', page: 'linear-gradient(180deg,#E6E7FF 0%,rgba(247,247,249,0) 100%)', icon: 't_over' },
  { name: 'Students', head: 'linear-gradient(180deg,#E3417F 0%,#C81F64 100%)', a: '#C81F64', a2: '#E3417F', soft: '#FADBE7', tint: '#FDF0F5', sh: 'rgba(200,31,100,.26)', page: 'linear-gradient(180deg,#FBE1EB 0%,rgba(247,247,249,0) 100%)', icon: 'stu' },
  { name: 'Classes', head: 'linear-gradient(180deg,#FF7D32 0%,#F2600F 100%)', a: '#E8590C', a2: '#FF7D32', soft: '#FFE6D6', tint: '#FFF4EC', sh: 'rgba(242,96,15,.28)', page: 'linear-gradient(180deg,#FFEBDD 0%,rgba(247,247,249,0) 100%)', icon: 'school' },
  { name: 'Fees', head: 'linear-gradient(180deg,#22B573 0%,#14915A 100%)', a: '#14915A', a2: '#22B573', soft: '#D5F2E3', tint: '#EEFAF3', sh: 'rgba(20,145,90,.26)', page: 'linear-gradient(180deg,#DDF4E8 0%,rgba(247,247,249,0) 100%)', icon: 't_dues' },
  { name: 'Staff', head: 'linear-gradient(180deg,#A66CF0 0%,#7C3AED 100%)', a: '#6D28D9', a2: '#A66CF0', soft: '#EADCFD', tint: '#F6F0FE', sh: 'rgba(109,40,217,.26)', page: 'linear-gradient(180deg,#EFE4FE 0%,rgba(247,247,249,0) 100%)', icon: 'staff' },
  { name: 'Reports', head: 'linear-gradient(180deg,#14B8C4 0%,#0B8A99 100%)', a: '#0B8A99', a2: '#14B8C4', soft: '#CFF3F6', tint: '#ECFAFB', sh: 'rgba(11,138,153,.26)', page: 'linear-gradient(180deg,#D9F5F7 0%,rgba(247,247,249,0) 100%)', icon: 't_rep' }
];
// Small drawings in the Trial App's flat style
const ILL = {
  stu: '<circle cx="24" cy="16" r="9" fill="#fff"/><path d="M8 42c1-9 8-14 16-14s15 5 16 14z" fill="#fff"/><path d="M14 13l10-6 10 6-10 5z" fill="#1F1F24"/><path d="M33 13v6" stroke="#F5C842" stroke-width="2"/><circle cx="20.5" cy="17" r="1.3" fill="#1F1F24"/><circle cx="27.5" cy="17" r="1.3" fill="#1F1F24"/>',
  staff: '<circle cx="24" cy="15" r="8" fill="#fff"/><path d="M9 42c1-9 7-14 15-14s14 5 15 14z" fill="#fff"/><path d="M24 28l-3 4 3 9 3-9z" fill="#7C3AED"/><rect x="30" y="31" width="8" height="6" rx="1.5" fill="#F5C842"/>',
  t_over: '<rect x="6" y="10" width="36" height="30" rx="5" fill="#fff"/><rect x="11" y="27" width="5" height="8" rx="1.5" fill="#6366F1"/><rect x="19" y="21" width="5" height="14" rx="1.5" fill="#F5C842"/><rect x="27" y="16" width="5" height="19" rx="1.5" fill="#22B573"/><rect x="35" y="24" width="3" height="11" rx="1.5" fill="#E3417F"/><path d="M11 18l8-4 8 3 9-7" stroke="#1F1F24" stroke-width="1.6" fill="none" stroke-linecap="round"/>',
  t_month: '<rect x="7" y="11" width="34" height="30" rx="5" fill="#fff"/><rect x="7" y="11" width="34" height="9" rx="4" fill="#FFB347"/><rect x="14" y="7" width="3" height="8" rx="1.5" fill="#1F1F24"/><rect x="31" y="7" width="3" height="8" rx="1.5" fill="#1F1F24"/><circle cx="24" cy="30" r="7.5" fill="#F5C842"/><text x="24" y="33.6" text-anchor="middle" font-size="10" font-weight="700" fill="#7A4B00" font-family="Poppins,sans-serif">₹</text>',
  t_dues: '<circle cx="24" cy="25" r="16" fill="#fff"/><circle cx="24" cy="25" r="12" fill="none" stroke="#E3417F" stroke-width="3" stroke-dasharray="52 80" transform="rotate(-90 24 25)"/><path d="M24 17v8l5 4" stroke="#1F1F24" stroke-width="2.2" fill="none" stroke-linecap="round"/><circle cx="37" cy="12" r="6" fill="#F5C842"/><text x="37" y="15" text-anchor="middle" font-size="8" font-weight="700" fill="#7A4B00" font-family="Poppins,sans-serif">₹</text>',
  t_call: '<rect x="14" y="6" width="20" height="36" rx="5" fill="#fff"/><rect x="17" y="11" width="14" height="22" rx="2" fill="#BFF0D6"/><circle cx="24" cy="37.5" r="2" fill="#1F1F24"/><path d="M36 14c3 2 3 8 0 10M39 11c5 4 5 12 0 16" stroke="#fff" stroke-width="2" fill="none" stroke-linecap="round"/><path d="M20 19c0 4 4 8 8 8l1.5-2-2.5-2-1.5 1c-1-.5-2.5-2-3-3l1-1.5-2-2.5z" fill="#14915A"/>',
  t_rep: '<rect x="9" y="6" width="28" height="36" rx="4" fill="#fff"/><rect x="14" y="12" width="14" height="3" rx="1.5" fill="#14B8C4"/><rect x="14" y="19" width="18" height="2.5" rx="1.25" fill="#D9D9E1"/><rect x="14" y="24" width="18" height="2.5" rx="1.25" fill="#D9D9E1"/><rect x="14" y="29" width="12" height="2.5" rx="1.25" fill="#D9D9E1"/><circle cx="35" cy="34" r="7" fill="#F5C842"/><path d="M38 37l4 4" stroke="#1F1F24" stroke-width="2.4" stroke-linecap="round"/><circle cx="35" cy="34" r="4" fill="#fff"/>',
  bulb: '<circle cx="24" cy="20" r="12" fill="#F5C842"/><path d="M19 31h10v5a3 3 0 0 1-3 3h-4a3 3 0 0 1-3-3z" fill="#7A7A85"/><path d="M24 26v-6l-3-3M24 20l3-3" stroke="#7A4B00" stroke-width="1.6" fill="none" stroke-linecap="round"/><path d="M6 20h3M39 20h3M11 7l2 2M37 7l-2 2" stroke="#F5C842" stroke-width="2.2" stroke-linecap="round"/>',
  school: '<path d="M6 22L24 10l18 12" fill="#E3417F"/><rect x="10" y="22" width="28" height="18" fill="#fff"/><rect x="21" y="30" width="6" height="10" fill="#6366F1"/><rect x="13" y="26" width="5" height="5" fill="#BFD7FF"/><rect x="30" y="26" width="5" height="5" fill="#BFD7FF"/><rect x="23" y="4" width="2" height="8" fill="#1F1F24"/><path d="M25 4h7l-2 2.5 2 2.5h-7z" fill="#F2600F"/>',
  phone: '<rect x="14" y="6" width="20" height="36" rx="5" fill="#fff" stroke="#14915A" stroke-width="2"/><path d="M20 19c0 4 4 8 8 8l1.5-2-2.5-2-1.5 1c-1-.5-2.5-2-3-3l1-1.5-2-2.5z" fill="#14915A"/>'
};
const ill = (k, cls) => `<svg class="ill ${cls || ''}" viewBox="0 0 48 48" aria-hidden="true">${ILL[k] || ''}</svg>`;
const IC = {
  search: '<circle cx="11" cy="11" r="7"/><path d="M20 20l-4-4"/>', menu: '<path d="M4 7h16M4 12h16M4 17h16"/>', back: '<path d="M15 6l-6 6 6 6"/>', mail: '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/>', down: '<path d="M6 9l6 6 6-6"/>', phone: '<path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2"/>',
  wa: '<path d="M4 20l1.3-4A8 8 0 1 1 8 18.7z"/>', note: '<path d="M4 20h4l10-10-4-4L4 16z"/>', share: '<path d="M4 12v7a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-7M12 3v12M7 8l5-5 5 5"/>', x: '<path d="M6 6l12 12M18 6L6 18"/>'
};
const ic = (k, s) => `<svg class="i" width="${s || 18}" height="${s || 18}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${IC[k]}</svg>`;

let D = null, CH = [], PLAN = [], BUSY = false, INTRO = null, INST = null, LOGIN = null;
const S = { tab: 0, tag: null, cls: '', m: 0, q: '', sort: 'due', list: 'today', sheet: null, open: '', share: '', cmode: 'calls', aud: 'zero', tpl: 'zero', lang: 'hi', skipSent: true, mtext: null };
let MQ = null;   // WhatsApp sending queue {list:[sid], i, kind, sent:n}
const rs = n => '₹' + Math.round(n || 0).toLocaleString('en-IN');
const rsS = n => { n = Math.round(n || 0); const a = Math.abs(n); return a >= 1e7 ? '₹' + (n / 1e7).toFixed(2) + ' Cr' : a >= 1e5 ? '₹' + (n / 1e5).toFixed(a >= 1e6 ? 1 : 2) + ' L' : a >= 1e3 ? '₹' + (n / 1e3).toFixed(1) + 'k' : '₹' + n; };
const pct = (a, b) => b > 0 ? Math.round(a * 1000 / b) / 10 : 0;
const dmy = d => d ? d.slice(8, 10) + '-' + d.slice(5, 7) + '-' + d.slice(0, 4) : '';
const addDays = (d, n) => { const x = new Date(d + 'T00:00:00'); x.setDate(x.getDate() + n); return x.toISOString().slice(0, 10); };
const longSes = s => String(s || '').replace(/^(20\d\d)-(\d\d)$/, '$1-20$2');
const prevSes = s => { const y = +s.slice(0, 4) - 1; return y + '-' + String(y + 1).slice(2); };
const sum = (a, f) => a.reduce((t, x) => t + (f(x) || 0), 0);
const store = { get: k => { try { return localStorage.getItem(k); } catch (e) { return null; } }, set: (k, v) => { try { localStorage.setItem(k, v); } catch (e) {} } };
function toast(t) { const el = $('toast'); el.textContent = t; clearTimeout(toast.t); toast.t = setTimeout(() => { el.textContent = ''; }, 4500); }
async function api(path, body) {
  const r = await fetch(path, { method: body ? 'POST' : 'GET', headers: { 'Content-Type': 'application/json', 'X-School-Token': store.get('sch_token') || '' }, ...(body ? { body: JSON.stringify(body) } : {}) });
  let d; try { d = await r.json(); } catch (e) { throw Object.assign(Error('The server did not answer properly. Please try again.'), { code: r.status }); }
  if (!r.ok || !d.ok) throw Object.assign(Error(d.error || 'Could not complete this.'), { code: r.status });
  return d;
}
async function load(ses) {
  try {
    const d = await api('feedash_api.php?a=data' + (ses ? '&ses=' + encodeURIComponent(ses) : ''));
    if (!d.money) throw Object.assign(Error('This app is for the Chairman and the Principal.'), { code: 403 });
    D = d; LOGIN = null; calc(); fillIntro(); if (!INTRO) render();
  } catch (e) {
    D = null; LOGIN = { step: e.code === 428 ? 'new' : '', msg: e.code === 401 ? '' : e.message, net: !(e.code === 401 || e.code === 428 || e.code === 403) };
    fillIntro(); if (!INTRO) render();
  }
}

// ---------------------------------------------------------------- figures (same rules as the office Fee Control Room)
function monthIdx() { const y = +D.ses.slice(0, 4), t = D.today; if (t < y + '-04-01') return -1; if (t > (y + 1) + '-03-31') return 11; return MORDER.indexOf(+t.slice(5, 7)); }
const ymOf = (m, ses) => { const y = +(ses || D.ses).slice(0, 4); return (m >= 4 ? y : y + 1) + '-' + String(m).padStart(2, '0'); };
// 12 month rows per child {m, fd, fp, td, tp}: fee demand/paid and transport demand/paid. Fixed plan (a tag with a fixed amount
// a month): the fee demand is that amount every month and the fee paid fills the months in order; transport as on the card.
function kidMonths(r) {
  const mon = MORDER.map(m => { const H = r.heads.filter(h => h.m === m), tr = H.filter(h => isTr(h.name)), fe = H.filter(h => !isTr(h.name));
    return { m, fd: sum(fe, h => h.amt), fp: sum(fe, h => h.paid), td: sum(tr, h => h.amt), tp: sum(tr, h => h.paid) }; });
  if (r.fixed > 0) { let left = sum(mon, x => x.fp); mon.forEach(x => { x.fd = r.fixed; x.fp = Math.min(r.fixed, Math.max(0, left)); left -= x.fp; }); }
  return mon;
}
function calc() {
  const idx = monthIdx(), today = D.today; D.idx = idx;
  if (!S.m) S.m = idx < 0 ? 4 : MORDER[Math.min(idx, 11)];
  const callsBy = {}; D.calls.forEach(c => (c.sids || []).forEach(s => (callsBy[s] = callsBy[s] || []).push(c)));
  const lastRc = {}, rcBy = {}; D.receipts.forEach(r => { if (r.status !== 'Cancelled' && r.sid) { (rcBy[r.sid] = rcBy[r.sid] || []).push(r); if (!lastRc[r.sid] || r.date > lastRc[r.sid].date) lastRc[r.sid] = r; } });
  D.msgs = D.msgs || []; const msgBy = {}; D.msgs.forEach(m => (m.sids || []).forEach(s => (msgBy[s] = msgBy[s] || []).push(m)));
  const y0 = +D.ses.slice(0, 4), inSes = r => r.session === D.ses || (r.date >= y0 + '-04-01' && r.date <= (y0 + 1) + '-03-31');
  D.all = D.dues.map(r => {
    const old = Math.max(0, r.oldDue - (r.oldPaid || 0)), mon = kidMonths(r), owe = x => Math.max(0, x.fd - x.fp) + Math.max(0, x.td - x.tp);
    const unpaid = mon.filter((x, i) => i <= idx && owe(x) > 0), calls = callsBy[r.sid] || [], last = calls[0] || null;
    const lr = lastRc[r.sid], lastPaid = lr ? { date: lr.date, amt: lr.received } : r.lastDate ? { date: r.lastDate, amt: r.lastAmt } : null;
    let promise = '', broken = false;
    if (last && last.promise && last.outcome !== 'Wrong number') { promise = last.promise; broken = promise < today && !(lastPaid && lastPaid.date >= last.date); }
    const rcs = (rcBy[r.sid] || []).slice().sort((a, b) => b.date.localeCompare(a.date)), sesRc = rcs.filter(inSes), paid = sum(mon, x => x.fp + x.tp);
    const since = unpaid.length ? MN[unpaid[0].m] + ' ' + ymOf(unpaid[0].m).slice(0, 4) : old > 0 ? 'last session' : '';
    return { ...r, tagName: r.tag || NOTAG, old, mon, annual: sum(mon, x => x.fd + x.td), paid, dueNow: sum(mon.filter((x, i) => i <= idx), owe) + old,
      yearBal: sum(mon, owe) + old, unpaid: unpaid.map(x => M3(x.m)), ageing: unpaid.length, calls, last, lastPaid, promise, broken, phoneNo: String(r.fatherMobile || r.phone || '').replace(/\D/g, '').slice(-10),
      rcs, sesPaid: sum(sesRc, x => x.received), zero: paid === 0 && !sesRc.length, since, msgs: msgBy[r.sid] || [], lastMsg: (msgBy[r.sid] || [])[0] || null };
  });
  D.tags = [...new Set(D.all.map(k => k.tagName))].sort((a, b) => (a === NOTAG) - (b === NOTAG) || a.localeCompare(b));
  if (S.tag !== null && !D.tags.includes(S.tag)) S.tag = null;
  D.kids = D.all.filter(k => (S.tag === null || k.tagName === S.tag) && (!S.cls || k.cls === S.cls));
  const set = new Set(D.kids.map(k => k.sid)), f = S.tag !== null || S.cls;
  D.rc = D.receipts.filter(r => r.status !== 'Cancelled' && (!f || set.has(r.sid)));
  D.cx = D.receipts.filter(r => r.status === 'Cancelled' && (!f || set.has(r.sid)));
  const K = D.kids;
  D.t = { annual: sum(K, k => k.annual), paid: sum(K, k => k.paid), dueNow: sum(K, k => k.dueNow), yearBal: sum(K, k => k.yearBal), old: sum(K, k => k.old), def: K.filter(k => k.dueNow > 0) };
  D.t.notYet = Math.max(0, D.t.yearBal - D.t.dueNow);
  D.lastRcDate = D.receipts.reduce((m, r) => r.date > m ? r.date : m, '');
}
const cashIn = rows => { const sp = rows.filter(r => r.split), ns = rows.filter(r => !r.split); return { n: rows.length, tr: sum(sp, r => r.tr), fee: sum(sp, r => r.received - r.tr), mixed: sum(ns, r => r.received), total: sum(rows, r => r.received) }; };
const inMonth = (rows, ym) => rows.filter(r => r.date.slice(0, 7) === ym);
function monthPos(kids, m) {
  const i = MORDER.indexOf(m), X = kids.map(k => ({ k, x: k.mon[i] }));
  const fd = sum(X, o => o.x.fd), fp = sum(X, o => o.x.fp), td = sum(X, o => o.x.td), tp = sum(X, o => o.x.tp);
  return { fd, fp, td, tp, exp: fd + td, got: fp + tp, due: fd + td - fp - tp, pend: X.filter(o => o.x.fd + o.x.td - o.x.fp - o.x.tp > 0).map(o => ({ ...o.k, monDue: o.x.fd + o.x.td - o.x.fp - o.x.tp })) };
}
function callLists(rows) {
  const t = D.today;
  return { today: rows.filter(x => x.promise && x.promise <= t && !(x.lastPaid && x.last && x.lastPaid.date >= x.last.date)), fresh: rows.filter(x => !x.last || x.last.date < addDays(t, -6)), broken: rows.filter(x => x.broken), all: rows };
}

// ---------------------------------------------------------------- frame
function theme() { const th = TH[S.tab], r = document.documentElement.style; r.setProperty('--a', th.a); r.setProperty('--a2', th.a2); r.setProperty('--soft', th.soft); r.setProperty('--tint', th.tint); r.setProperty('--hshadow', th.sh); document.querySelector('meta[name=theme-color]').setAttribute('content', th.a); }
function render() {
  CH.forEach(c => c.destroy()); CH = []; PLAN = [];
  if (!D) return loginView();
  theme();
  const hr = new Date().getHours(), greet = hr < 12 ? 'Good morning' : hr < 17 ? 'Good afternoon' : 'Good evening';
  const layers = TH.map((th, i) => `<div class="hb" style="background:${th.head};opacity:${i === S.tab ? 1 : 0}"></div>`).join('');
  const tabs = TH.map((th, i) => `<button class="tab${i === S.tab ? ' on' : ''}" role="tab" aria-selected="${i === S.tab}" onclick="setTab(${i})">${ill(th.icon)}<span>${th.name}</span></button>`).join('');
  const body = pageBody();
  $('app').innerHTML = `<div class="head">${layers}
      <div class="loc"><img class="logo" src="${LOGO}" alt=""><div class="w"><small>${greet}</small><b>${D.role === 'principal' ? 'Principal Sir' : 'Chairman Sir'}</b><button class="sesb" onclick="sheet('ses')">Session ${esc(longSes(D.ses))} ${ic('down', 11)}</button></div><button class="av mn" aria-label="Menu" onclick="sheet('me')">${ic('menu', 18)}</button></div>
      <div class="tabs" role="tablist"><span class="fold" style="transform:translateX(${S.tab * 100}%)"></span>${tabs}</div>
      <div class="strip"><label class="sbox">${ic('search', 16)}<input id="q" value="${esc(S.q)}" placeholder="Search any student — name, father, mobile, adm. no" oninput="S.q=this.value;render();refocus('q')"></label>
        ${S.tab === 3 || S.tab === 5 ? `<button class="tagb" onclick="sheet('filter')">${esc(S.tag === null ? 'All tags' : S.tag)}${S.cls ? ' · ' + esc(S.cls) : ''} ${ic('down', 12)}</button>` : ''}</div></div>
    <div class="scroll" id="sc"><div class="pagebg" style="background:${TH[S.tab].page}"></div><div class="body">${body}</div></div>
    ${S.tab === 0 && !S.q ? `<button class="cartbar" onclick="share()">${ic('share', 18)}<b>Share today's summary</b><em>WhatsApp</em></button>` : ''}
    ${S.sheet ? `<div class="ov" onclick="sheet(null)"></div><div class="sheet" role="dialog"><div class="grab"></div>${sheetBody()}</div>` : ''}`;
  requestAnimationFrame(drawCharts);
}
function refocus(id) { const e = $(id); if (e) { e.focus(); e.setSelectionRange(e.value.length, e.value.length); } }
function setTab(i) { S.tab = i; S.q = ''; S.prof = null; S.room = null; render(); const sc = $('sc'); if (sc) sc.scrollTop = 0; }
function setF(k, v) { S[k] = v; calc(); render(); }
function sheet(k) { S.sheet = k; render(); }
const card = (t, b, x) => `<section class="card"><h3>${esc(t)}${x || ''}</h3>${b}</section>`;
const k = (label, val, sub, tone, onclick) => `<button class="k ${tone || ''}" ${onclick ? `onclick="${onclick}"` : 'tabindex="-1"'}><i></i><span>${esc(label)}</span><b>${val}</b>${sub ? `<small>${sub}</small>` : ''}</button>`;
const canvas = (id, h) => `<div class="cv" style="height:${h || 210}px"><canvas id="${id}"></canvas></div>`;
const chart = (id, cfg) => PLAN.push([id, cfg]);
function drawCharts() {
  if (!window.Chart) return;
  Chart.defaults.font.family = 'Poppins, system-ui, sans-serif'; Chart.defaults.font.size = 11; Chart.defaults.color = '#7A7A85';
  Chart.defaults.plugins.legend.labels.boxWidth = 10; Chart.defaults.plugins.legend.labels.boxHeight = 10; Chart.defaults.animation.duration = 600; Chart.defaults.transitions.resize = { animation: { duration: 0 } };
  PLAN.forEach(([id, cfg]) => { const el = $(id); if (el) CH.push(new Chart(el, cfg)); }); PLAN = [];
}
const money = { callback: v => rsS(v) };
const tipRs = { callbacks: { label: c => ` ${c.dataset.label ? c.dataset.label + ': ' : ''}${rs(c.parsed.y ?? c.parsed.x ?? c.parsed)}` } };
const tipPie = { callbacks: { label: c => ` ${c.label}: ${rs(c.parsed)}` } };
const bars = (stacked, horiz) => ({ responsive: true, maintainAspectRatio: false, indexAxis: horiz ? 'y' : 'x', plugins: { legend: { position: 'bottom' }, tooltip: tipRs },
  scales: { x: { stacked, grid: { display: !!horiz, color: '#F1F1F4' }, ticks: horiz ? money : {} }, y: { stacked, beginAtZero: true, grid: { display: !horiz, color: '#F1F1F4' }, ticks: horiz ? {} : money } } });
const P = { indigo: '#4338CA', indigo2: '#A5A8FF', orange: '#F2600F', amber: '#F5C842', pink: '#E3417F', green: '#22B573', grey: '#D9D9E1', blue: '#3C8DF6', plum: '#9B5DE5' };
const PAL = [P.indigo, P.green, P.amber, P.pink, P.blue, P.orange, P.plum, '#14B8A6'];
function people(rows, opt) {
  opt = opt || {};
  if (!rows.length) return '<p class="empty">Nobody here.</p>';
  return `<div class="ppl">${rows.map(x => `<div class="p"><div class="ava" onclick="openStu('${esc(x.sid)}')">${esc((x.name || '?').split(/\s+/).map(w => w[0]).slice(0, 2).join('').toUpperCase())}</div>
    <div class="t" onclick="openStu('${esc(x.sid)}')" style="cursor:pointer"><b>${esc(x.name)}${x.tag ? `<span class="tag">${esc(x.tag)}</span>` : ''}${x.broken ? '<span class="tag brk">Promise broken</span>' : ''}${x.zero ? '<span class="tag brk">₹0 paid</span>' : ''}</b>
      <small>${esc(x.cls)}${x.section ? '-' + esc(x.section) : ''} · ${esc(x.father || '')}</small>
      <small>${x.since ? 'Due since ' + esc(x.since) + ' · ' : ''}${x.unpaid.length ? 'Unpaid: ' + esc(x.unpaid.join(', ')) : x.old ? 'Old dues only' : 'No dues'}${x.old && x.unpaid.length ? ' · old ' + rs(x.old) : ''}</small>
      ${x.lastMsg ? `<small>Message sent ${dmy(x.lastMsg.date)} · ${esc(x.lastMsg.kind)}</small>` : ''}
      ${x.last ? `<small>Last call ${dmy(x.last.date)}: ${esc(x.last.outcome)}${x.promise ? ' · date ' + dmy(x.promise) : ''}</small>` : opt.calls ? '<small>Not called yet</small>' : ''}</div>
    <div class="r"><b>${rs(opt.month ? x.monDue : x.dueNow)}</b><div class="acts">${x.phoneNo ? `<a class="c" href="tel:+91${x.phoneNo}" onclick="callNow('${esc(x.sid)}')" aria-label="Call">${ic('phone', 15)}</a><a class="w" target="_blank" rel="noopener" href="https://wa.me/91${x.phoneNo}?text=${encodeURIComponent(fillMsg(TPL.reminder[S.lang], x))}" onclick="logMsg(['${esc(x.sid)}'],'Fee reminder',fillMsg(TPL.reminder[S.lang], D.all.find(z=>z.sid==='${esc(x.sid)}')))" aria-label="WhatsApp reminder">${ic('wa', 15)}</a>` : ''}${opt.calls ? `<button class="s" onclick="noteCall('${esc(x.sid)}')" aria-label="Write what was discussed">${ic('note', 15)}</button>` : ''}</div></div></div>`).join('')}</div>`;
}
// ---- fee messages to parents (WhatsApp). {name} {class} {due} {months} {since} {old} {month} {monthdue} {paid} {school}
const TPL = {
  reminder: { label: 'Fee reminder', hi: 'नमस्ते 🙏 {school} से।\n{name} (कक्षा {class}) की फीस ₹{due} बाकी है ({months})।\nकृपया जल्द विद्यालय कार्यालय में जमा करें और रसीद ज़रूर लें। धन्यवाद।',
    en: 'Namaste 🙏 from {school}.\nThe fee of {name} (Class {class}) of ₹{due} is due ({months}).\nKindly deposit it at the school office soon and collect the receipt. Thank you.' },
  zero: { label: 'Nothing paid yet', hi: 'नमस्ते 🙏 {school} से।\nइस सत्र में {name} (कक्षा {class}) की अभी तक कोई फीस जमा नहीं हुई है। अभी तक का बकाया ₹{due} है।\nकृपया जल्द से जल्द विद्यालय आकर फीस जमा करें। धन्यवाद।',
    en: 'Namaste 🙏 from {school}.\nNo fee has been deposited this session for {name} (Class {class}). The amount due so far is ₹{due}.\nKindly visit the school and deposit the fee at the earliest. Thank you.' },
  month: { label: 'This month\'s fee', hi: 'नमस्ते 🙏 {school} से।\n{name} (कक्षा {class}) की {month} माह की फीस ₹{monthdue} जमा करनी है।\nकृपया समय पर जमा करें। धन्यवाद।',
    en: 'Namaste 🙏 from {school}.\nThe {month} fee of {name} (Class {class}), ₹{monthdue}, is now due.\nKindly deposit it on time. Thank you.' },
  old: { label: 'Old dues', hi: 'नमस्ते 🙏 {school} से।\n{name} (कक्षा {class}) का पिछले सत्र का बकाया ₹{old} अभी जमा नहीं हुआ है। कुल बकाया ₹{due} है।\nकृपया जल्द जमा करें। धन्यवाद।',
    en: 'Namaste 🙏 from {school}.\nThe previous session\'s dues of {name} (Class {class}), ₹{old}, are still pending. Total due is ₹{due}.\nKindly deposit soon. Thank you.' },
  final: { label: 'Final reminder', hi: 'आदरणीय अभिभावक 🙏\n{name} (कक्षा {class}) की फीस {since} से बाकी है। कुल बकाया ₹{due} है।\nकृपया 3 दिन के अंदर विद्यालय कार्यालय में जमा करें या प्रधानाचार्य से मिलें। धन्यवाद — {school}',
    en: 'Dear Parent 🙏\nThe fee of {name} (Class {class}) is due since {since}. Total due is ₹{due}.\nKindly deposit it at the school office within 3 days or meet the Principal. Thank you — {school}' },
  thanks: { label: 'Thank you (paid)', hi: 'धन्यवाद 🙏\n{name} (कक्षा {class}) की फीस समय पर जमा करने के लिए {school} आपका आभार प्रकट करता है।',
    en: 'Thank you 🙏\n{school} thanks you for depositing the fee of {name} (Class {class}) on time.' }
};
const AUD = [['zero', 'Paid ₹0 this session', x => x.zero && x.dueNow > 0], ['due', 'Everyone with dues', x => x.dueNow > 0], ['month', 'This month unpaid', x => { const i = Math.max(0, D.idx), y = x.mon[i]; return y && y.fd + y.td - y.fp - y.tp > 0; }],
  ['m2', '2+ months unpaid', x => x.ageing >= 2], ['m4', '4+ months unpaid', x => x.ageing >= 4], ['old', 'Old dues', x => x.old > 0], ['broken', 'Promise broken', x => x.broken], ['paid', 'Fully paid (thank you)', x => x.dueNow === 0 && x.paid > 0]];
const AUD_TPL = { zero: 'zero', due: 'reminder', month: 'month', m2: 'reminder', m4: 'final', old: 'old', broken: 'final', paid: 'thanks' };
function fillMsg(t, x) {
  if (!x) return t; const i = Math.max(0, D.idx), y = x.mon[i] || { fd: 0, td: 0, fp: 0, tp: 0 }, m = MORDER[Math.min(Math.max(D.idx, 0), 11)];
  const n = v => Math.round(v || 0).toLocaleString('en-IN');
  return t.replace(/\{school\}/g, D.school || 'SN Mishra Singapore International School').replace(/\{name\}/g, x.name).replace(/\{class\}/g, x.cls).replace(/\{due\}/g, n(x.dueNow)).replace(/\{months\}/g, x.unpaid.join(', ') || (x.old ? (S.lang === 'hi' ? 'पिछला बकाया' : 'old dues') : ''))
    .replace(/\{since\}/g, x.since || '').replace(/\{old\}/g, n(x.old)).replace(/\{month\}/g, MN[m]).replace(/\{monthdue\}/g, n(y.fd + y.td - y.fp - y.tp)).replace(/\{paid\}/g, n(x.paid));
}
function remind(x) { return fillMsg(TPL.reminder[S.lang], x); }
async function logMsg(sids, kind, text) {
  try { const d = await api('feedash_api.php?a=msgLog', { sids, kind, text: String(text || '').slice(0, 1000) }); D.msgs.unshift(d.msg); D.all.forEach(x => { if (sids.includes(x.sid)) { x.msgs.unshift(d.msg); x.lastMsg = d.msg; } }); } catch (e) {}
}

// ---------------------------------------------------------------- Overview
function overview() {
  const t = D.t, mon = cashIn(inMonth(D.rc, D.today.slice(0, 7))), today = cashIn(D.rc.filter(r => r.date === D.today)), y0 = +D.ses.slice(0, 4), ses = cashIn(D.rc.filter(r => r.date >= y0 + '-04-01' && r.date <= (y0 + 1) + '-03-31'));
  const thought = THOUGHTS[Math.floor(new Date(D.today + 'T00:00:00').getTime() / 864e5) % THOUGHTS.length];
  const target = t.paid + t.dueNow, coll = pct(t.paid, target);
  // money that came in each month = every receipt dated in it (this session's fee, old dues, advance), as in the cash book
  const months = MORDER.map(m => cashIn(inMonth(D.rc, ymOf(m))));
  const prev = MORDER.map(m => cashIn(inMonth(D.rc, ymOf(m, prevSes(D.ses)))).total), hasPrev = prev.some(v => v > 0);
  let ce = 0, cc = 0; const cumE = [], cumC = [];
  MORDER.forEach((m, i) => { ce += sum(D.kids, x => x.mon[i].fd + x.mon[i].td); cumE.push(ce); cc += months[i].total; cumC.push(i <= Math.max(D.idx, 0) ? cc : null); });
  const cls = CLASSES.filter(c => D.all.some(x => x.cls === c)).map(c => { const K = D.all.filter(x => x.cls === c && (S.tag === null || x.tagName === S.tag)); return { c, paid: sum(K, x => x.paid), due: sum(K, x => x.dueNow) }; });
  const modes = {}; D.rc.filter(r => r.session === D.ses).forEach(r => { modes[r.mode || 'Cash'] = (modes[r.mode || 'Cash'] || 0) + r.received; });
  const tagRows = D.tags.map(tg => { const K = D.all.filter(x => x.tagName === tg && (!S.cls || x.cls === S.cls)); return { tg, n: K.length, paid: sum(K, x => x.paid), due: sum(K, x => x.dueNow) }; }).filter(x => x.n);
  const yoyNow = sum(prev.slice(0, Math.max(D.idx, 0) + 1), v => v), thisNow = sum(months.slice(0, Math.max(D.idx, 0) + 1), x => x.total);
  const L = callLists(t.def), callsToday = D.calls.filter(c => c.date === D.today).length;
  const ym = D.today.slice(0, 7), disc = inMonth(D.rc.filter(r => r.discount > 0), ym), cx = inMonth(D.cx, ym);

  chart('cDonut', { type: 'doughnut', data: { labels: ['Collected', 'Due till now', 'Not due yet'], datasets: [{ data: [t.paid, t.dueNow, t.notYet], backgroundColor: [P.green, P.pink, P.grey], borderWidth: 0 }] }, options: { responsive: true, maintainAspectRatio: false, cutout: '72%', plugins: { legend: { position: 'bottom' }, tooltip: tipPie } } });
  chart('cMonths', { type: 'bar', data: { labels: MORDER.map(M3), datasets: [{ label: 'Fee', data: months.map(x => x.fee), backgroundColor: P.indigo, borderRadius: 5 }, { label: 'Transport', data: months.map(x => x.tr), backgroundColor: P.amber, borderRadius: 5 }, ...(months.some(x => x.mixed) ? [{ label: 'Old receipts (no head split)', data: months.map(x => x.mixed), backgroundColor: P.grey, borderRadius: 5 }] : [])] }, options: bars(true) });
  chart('cCum', { type: 'line', data: { labels: MORDER.map(M3), datasets: [{ label: 'Expected', data: cumE, borderColor: P.amber, backgroundColor: 'rgba(245,200,66,.15)', fill: true, tension: .35, pointRadius: 2 }, { label: 'Collected', data: cumC, borderColor: P.indigo, backgroundColor: 'rgba(67,56,202,.12)', fill: true, tension: .35, pointRadius: 3 }] },
    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' }, tooltip: tipRs }, scales: { y: { ticks: money, beginAtZero: true, grid: { color: '#F1F1F4' } }, x: { grid: { display: false } } } } });
  if (hasPrev) chart('cYoY', { type: 'bar', data: { labels: MORDER.map(M3), datasets: [{ label: longSes(prevSes(D.ses)), data: prev, backgroundColor: P.indigo2, borderRadius: 5 }, { label: longSes(D.ses), data: months.map(x => x.total), backgroundColor: P.indigo, borderRadius: 5 }] }, options: bars(false) });
  chart('cCls', { type: 'bar', data: { labels: cls.map(x => x.c), datasets: [{ label: 'Collected', data: cls.map(x => x.paid), backgroundColor: P.green, borderRadius: 4 }, { label: 'Due till now', data: cls.map(x => x.due), backgroundColor: P.pink, borderRadius: 4 }] },
    options: { ...bars(true, true), onClick: (e, el) => { if (el[0]) { S.cls = cls[el[0].index].c; calc(); goTo('dues'); } } } });
  if (tagRows.length > 1) chart('cTag', { type: 'bar', data: { labels: tagRows.map(x => x.tg), datasets: [{ label: 'Collected', data: tagRows.map(x => x.paid), backgroundColor: P.green, borderRadius: 5 }, { label: 'Due till now', data: tagRows.map(x => x.due), backgroundColor: P.pink, borderRadius: 5 }] }, options: bars(false) });
  chart('cMode', { type: 'doughnut', data: { labels: Object.keys(modes), datasets: [{ data: Object.values(modes), backgroundColor: PAL, borderWidth: 0 }] }, options: { responsive: true, maintainAspectRatio: false, cutout: '62%', plugins: { legend: { position: 'bottom' }, tooltip: tipPie } } });

  const top = t.def.slice().sort((a, b) => b.dueNow - a.dueNow).slice(0, 10);
  return `${installBox()}
    <div class="kp">
      <div class="k big"><span>Fee cleared this session (paid + discount)${S.tag !== null || S.cls ? ' · filtered' : ''}</span><b>${rs(t.paid)}</b><div class="meter"><i style="width:${Math.min(100, coll)}%"></i></div><small>${coll}% of the fee due till now · Total fee ${rsS(t.annual)} · Money received on receipts this session ${rsS(ses.total)}</small></div>
      ${k('Today', rsS(today.total), `${today.n} receipt(s)`, 'ok')}
      ${k('This month', rsS(mon.total), `Fee ${rsS(mon.fee + mon.mixed)} · Transport ${rsS(mon.tr)}`, 'ac', "goTo(\'month\')")}
      ${k('Due till now', rsS(t.dueNow), `${t.def.length} students · old ${rsS(t.old)}`, 'bad', "goTo(\'dues\')")}
      ${k('Overall dues', rsS(t.yearBal), `${rsS(t.notYet)} not due yet`, 'warn', "goTo(\'dues\')")}
    </div>
    <div class="row"><h2>Classes</h2><span class="mut">tap to filter</span></div>
    <div class="hscroll"><button class="cat${!S.cls ? ' on' : ''}" onclick="setF('cls','')"><span>All</span>Classes</button>${cls.map(x => `<button class="cat${S.cls === x.c ? ' on' : ''}" onclick="setF('cls','${x.c}')"><span>${esc(x.c)}<em>${pct(x.paid, x.paid + x.due)}%</em></span>${rsS(x.due)} due</button>`).join('')}</div>
    ${card('Fee Calling today', `<div class="stat"><button class="st" onclick="S.cmode='diary';S.df='today';goTo('calls')"><b>${callsToday}</b><span>Calls made today · see what was discussed</span></button><button class="st" onclick="S.list='today';goTo('calls')"><b>${L.today.length}</b><span>Follow-ups due</span></button>
      <button class="st bad" onclick="S.list='broken';goTo('calls')"><b>${L.broken.length}</b><span>Promises broken · ${rsS(sum(L.broken, x => x.dueNow))}</span></button><button class="st" onclick="S.list='fresh';goTo('calls')"><b>${L.fresh.length}</b><span>Not called in 7 days</span></button>
      <button class="st bad" onclick="goTo('dues')"><b>${D.kids.filter(x => x.zero && x.dueNow > 0).length}</b><span>Paid ₹0 this session</span></button><button class="st ok" onclick="S.cmode='log';goTo('calls')"><b>${sum(D.msgs.filter(m => m.date === D.today), m => (m.sids || []).length)}</b><span>Messages sent today</span></button></div>
      <div style="height:10px"></div><button class="cta" onclick="msgTo('zero')">${ic('wa', 18)} Message parents who paid ₹0</button>`)}
    ${card('Collection progress', `<div class="donut">${canvas('cDonut', 220)}<div class="c"><b>${coll}%</b><span>collected</span></div></div>`)}
    ${card('Money received month by month', canvas('cMonths', 230), `<small>${rsS(ses.total)} this session</small>`)}
    ${card('Monthly collection report', (() => { const R = MORDER.map(m => { const X = inMonth(D.rc, ymOf(m)); return [M3(m), sum(X, r => r.total != null ? r.total : r.received + r.discount), sum(X, r => r.discount), sum(X, r => r.received)]; }), tt = i => sum(R, r => r[i]);
      return `<div class="tw"><table class="tbl"><tr><th>Month</th><th>Total Fee</th><th>Discount</th><th>Recv Amount</th></tr>${R.map(r => `<tr><td>${r[0]}</td><td>${rs(r[1])}</td><td>${rs(r[2])}</td><td class="grn">${rs(r[3])}</td></tr>`).join('')}<tr class="tot"><td>Total</td><td>${rs(tt(1))}</td><td>${rs(tt(2))}</td><td>${rs(tt(3))}</td></tr></table></div><p class="note">Same as the old ERP's Monthly Collection Report: every receipt dated in the month.</p>`; })(), '<small>as in the old ERP</small>')}
    ${card('Expected vs collected', canvas('cCum', 210), '<small>cumulative</small>')}
    ${hasPrev ? card('This year vs last year', canvas('cYoY', 220), yoyNow ? `<small class="${thisNow >= yoyNow ? 'up' : 'down'}">${thisNow >= yoyNow ? '▲' : '▼'} ${Math.abs(pct(thisNow - yoyNow, yoyNow))}%</small>` : '') : ''}
    ${card('Class-wise position', canvas('cCls', Math.max(220, cls.length * 30)), '<small>tap a bar</small>')}
    ${tagRows.length > 1 ? card('By tag', canvas('cTag', 220)) : ''}
    ${card('Payment mode', canvas('cMode', 210), '<small>this session</small>')}
    ${card('Top 10 dues', people(top))}
    ${card('Control · this month', `<div class="stat"><div class="st"><b>${rsS(sum(disc, r => r.discount))}</b><span>Discounts given · ${disc.length} receipt(s)</span></div><div class="st ${cx.length ? 'bad' : 'ok'}"><b>${cx.length}</b><span>Receipts cancelled · ${rsS(sum(cx, r => r.received))}</span></div></div>
      ${disc.length || cx.length ? `<div class="tw" style="margin-top:10px"><table class="tbl"><tr><th>Date</th><th>Receipt</th><th>Student</th><th>Amount</th><th>By</th></tr>${disc.map(r => `<tr><td>${dmy(r.date)}</td><td>${esc(r.no)}</td><td>${esc(r.name)}</td><td>Discount ${rs(r.discount)}</td><td>${esc(r.user)}</td></tr>`).join('')}${cx.map(r => `<tr class="red"><td>${dmy(r.date)}</td><td>${esc(r.no)}</td><td>${esc(r.name)}</td><td>Cancelled ${rs(r.received)}</td><td>${esc(r.user)}</td></tr>`).join('')}</table></div>` : '<p class="note">No discount or cancelled receipt this month.</p>'}`)}
    ${card('Class-wise table', clsTable())}
    <p class="foot">${D.live ? 'Live figures.' : `Fee cards as copied from the old ERP on ${esc(D.asOn || '—')}.`} Last receipt ${dmy(D.lastRcDate)}.<br>SN Mishra Singapore International School</p>`;
}
function clsTable() {
  const R = CLASSES.map(c => { const K = D.kids.filter(x => x.cls === c); return { c, n: K.length, annual: sum(K, x => x.annual), paid: sum(K, x => x.paid), due: sum(K, x => x.dueNow) }; }).filter(x => x.n);
  return `<div class="tw"><table class="tbl"><tr><th>Class</th><th>Students</th><th>Session fee</th><th>Collected</th><th>Due now</th><th>%</th></tr>${R.map(x => `<tr><td>${esc(x.c)}</td><td>${x.n}</td><td>${rs(x.annual)}</td><td>${rs(x.paid)}</td><td class="red">${rs(x.due)}</td><td>${pct(x.paid, x.paid + x.due)}%</td></tr>`).join('')}
    <tr class="tot"><td>Total</td><td>${sum(R, x => x.n)}</td><td>${rs(sum(R, x => x.annual))}</td><td>${rs(sum(R, x => x.paid))}</td><td class="red">${rs(sum(R, x => x.due))}</td><td>${pct(sum(R, x => x.paid), sum(R, x => x.paid + x.due))}%</td></tr></table></div>`;
}

// ---------------------------------------------------------------- Month
function monthBody() {
  const m = S.m, ym = ymOf(m), rows = inMonth(D.rc, ym), cin = cashIn(rows), Pm = monthPos(D.kids, m);
  const days = new Date(+ym.slice(0, 4), +ym.slice(5, 7), 0).getDate(), byDay = [...Array(days)].map((_, i) => sum(rows.filter(r => +r.date.slice(8, 10) === i + 1), r => r.received));
  const cls = CLASSES.filter(c => D.kids.some(x => x.cls === c)).map(c => ({ c, p: monthPos(D.kids.filter(x => x.cls === c), m) }));
  const tagRows = D.tags.map(tg => { const K = D.all.filter(x => x.tagName === tg && (!S.cls || x.cls === S.cls)), set = new Set(K.map(x => x.sid)); return { tg, n: K.length, q: monthPos(K, m), c: cashIn(D.receipts.filter(r => r.status !== 'Cancelled' && set.has(r.sid) && r.date.slice(0, 7) === ym)) }; }).filter(x => x.n);
  chart('mSplit', { type: 'doughnut', data: { labels: ['Fee', 'Transport', ...(cin.mixed ? ['Old receipts (no head split)'] : [])], datasets: [{ data: [cin.fee, cin.tr, ...(cin.mixed ? [cin.mixed] : [])], backgroundColor: [P.orange, P.amber, P.grey], borderWidth: 0 }] }, options: { responsive: true, maintainAspectRatio: false, cutout: '64%', plugins: { legend: { position: 'bottom' }, tooltip: tipPie } } });
  chart('mDay', { type: 'bar', data: { labels: byDay.map((_, i) => i + 1), datasets: [{ label: 'Collected', data: byDay, backgroundColor: P.orange, borderRadius: 4 }] }, options: { ...bars(false), plugins: { legend: { display: false }, tooltip: tipRs } } });
  chart('mCls', { type: 'bar', data: { labels: cls.map(x => x.c), datasets: [{ label: 'Expected', data: cls.map(x => x.p.exp), backgroundColor: '#FFD2B3', borderRadius: 4 }, { label: 'Received', data: cls.map(x => x.p.got), backgroundColor: P.orange, borderRadius: 4 }] }, options: bars(false) });
  const pend = Pm.pend.sort((a, b) => b.monDue - a.monDue);
  return `<div class="chips months">${MORDER.map((x, i) => `<button class="chip${x === m ? ' on' : ''}${i > D.idx ? ' fut' : ''}" onclick="S.m=${x};render()"><b>${M3(x)}</b><small>${ymOf(x).slice(0, 4)}</small></button>`).join('')}</div>
    <div class="kp">
      <div class="k big"><span>${MN[m]} ${ym.slice(0, 4)} · money received</span><b>${rs(cin.total)}</b><small>${cin.n} receipt(s) · Fee ${rs(cin.fee + cin.mixed)} · Transport ${rs(cin.tr)}</small></div>
      ${k('Fee collected', rsS(cin.fee), cin.mixed ? `+ ${rsS(cin.mixed)} old receipts with no head split` : 'school fee heads', 'ok')}
      ${k('Transport collected', rsS(cin.tr), 'transport heads', 'ac')}
      ${k('Expected this month', rsS(Pm.exp), `Fee ${rsS(Pm.fd)} · Transport ${rsS(Pm.td)}`)}
      ${k('Received against it', rsS(Pm.got), `${pct(Pm.got, Pm.exp)}% of the month`, 'ok')}
      ${k('Pending for the month', rsS(Pm.due), `${pend.length} students to follow up`, 'bad')}
      ${k('Students to work on', String(pend.length), 'tap to call them', 'warn', `S.list='all';goTo('calls')`)}
    </div>
    ${card('Fee and transport · ' + MN[m], `<div class="tw"><table class="tbl"><tr><th></th><th>Expected</th><th>Received</th><th>Pending</th></tr>
      <tr><td>School fee</td><td>${rs(Pm.fd)}</td><td>${rs(Pm.fp)}</td><td class="red">${rs(Pm.fd - Pm.fp)}</td></tr><tr><td>Transport</td><td>${rs(Pm.td)}</td><td>${rs(Pm.tp)}</td><td class="red">${rs(Pm.td - Pm.tp)}</td></tr>
      <tr class="tot"><td>Total</td><td>${rs(Pm.exp)}</td><td>${rs(Pm.got)}</td><td class="red">${rs(Pm.due)}</td></tr></table></div>
      <p class="note">Expected and received are ${MN[m]}'s fee-card amounts (fixed-plan students at their fixed amount). Money received is what came in on receipts dated in ${MN[m]}.</p>`)}
    ${card('Received: fee vs transport', canvas('mSplit', 200))}
    ${card('Day-wise collection', canvas('mDay', 190))}
    ${card('Expected vs received by class', canvas('mCls', 230))}
    ${tagRows.length > 1 || (tagRows[0] && tagRows[0].tg !== NOTAG) ? card('By tag · ' + MN[m], `<div class="tw"><table class="tbl"><tr><th>Tag</th><th>Students</th><th>Fee exp.</th><th>Transport exp.</th><th>Received</th><th>Pending</th><th>Students due</th><th>Came in</th></tr>
      ${tagRows.map(x => `<tr><td><a href="#" onclick="setF('tag',${esc(JSON.stringify(x.tg))});return false">${esc(x.tg)}</a></td><td>${x.n}</td><td>${rs(x.q.fd)}</td><td>${rs(x.q.td)}</td><td>${rs(x.q.got)}</td><td class="red">${rs(x.q.due)}</td><td>${x.q.pend.length}</td><td>${rs(x.c.total)}</td></tr>`).join('')}
      <tr class="tot"><td>Total</td><td>${sum(tagRows, x => x.n)}</td><td>${rs(sum(tagRows, x => x.q.fd))}</td><td>${rs(sum(tagRows, x => x.q.td))}</td><td>${rs(sum(tagRows, x => x.q.got))}</td><td class="red">${rs(sum(tagRows, x => x.q.due))}</td><td>${sum(tagRows, x => x.q.pend.length)}</td><td>${rs(sum(tagRows, x => x.c.total))}</td></tr></table></div>`) : ''}
    ${card(`Pending for ${MN[m]} · ${pend.length}`, people(pend.slice(0, 150), { month: true, calls: true }))}`;
}

// ---------------------------------------------------------------- Dues
function duesBody() {
  const t = D.t;
  const B = [['1 month', x => x.ageing === 1], ['2–3 months', x => x.ageing >= 2 && x.ageing <= 3], ['4+ months', x => x.ageing >= 4], ['Old dues only', x => x.ageing === 0 && x.old > 0]].map(([l, f]) => { const K = t.def.filter(f); return { l, n: K.length, v: sum(K, x => x.dueNow) }; });
  chart('dAge', { type: 'doughnut', data: { labels: B.map(b => `${b.l} (${b.n})`), datasets: [{ data: B.map(b => b.v), backgroundColor: ['#FFC2D6', P.pink, '#9D174D', P.plum], borderWidth: 0 }] }, options: { responsive: true, maintainAspectRatio: false, cutout: '60%', plugins: { legend: { position: 'bottom' }, tooltip: tipPie } } });
  const cls = CLASSES.filter(c => D.kids.some(x => x.cls === c)).map(c => ({ c, v: sum(D.kids.filter(x => x.cls === c), x => x.dueNow) }));
  chart('dCls', { type: 'bar', data: { labels: cls.map(x => x.c), datasets: [{ label: 'Due till now', data: cls.map(x => x.v), backgroundColor: P.pink, borderRadius: 4 }] }, options: { ...bars(false, true), plugins: { legend: { display: false }, tooltip: tipRs } } });
  let list = t.def.slice().sort(S.sort === 'name' ? (a, b) => a.name.localeCompare(b.name) : S.sort === 'months' ? (a, b) => b.ageing - a.ageing || b.dueNow - a.dueNow : (a, b) => b.dueNow - a.dueNow);
  return `<div class="kp">
      <div class="k big"><span>Due till now${S.cls ? ' · ' + esc(S.cls) : ''}</span><b>${rs(t.dueNow)}</b><small>${t.def.length} students · this includes old dues of ${rs(t.old)}</small></div>
      ${k('Overall dues', rsS(t.yearBal), 'whole session', 'warn')}${k('Not due yet', rsS(t.notYet), 'coming months')}
      ${k('Old dues', rsS(t.old), 'from earlier sessions', 'bad')}${k('4+ months unpaid', String(B[2].n), rsS(B[2].v), 'bad')}
    </div>
    ${zeroCard()}
    ${card('How old are the dues', canvas('dAge', 210))}
    ${card('Due by class', canvas('dCls', Math.max(200, cls.length * 26)))}
    ${card(`Students with dues · ${list.length}`, `<div class="srch"><select aria-label="Sort" onchange="S.sort=this.value;render()"><option value="due" ${S.sort === 'due' ? 'selected' : ''}>Highest due</option><option value="months" ${S.sort === 'months' ? 'selected' : ''}>Most months</option><option value="name" ${S.sort === 'name' ? 'selected' : ''}>Name</option></select><span class="mut" style="align-self:center">${rs(sum(list, x => x.dueNow))}</span></div>
      ${people(list.slice(0, 200), { calls: true })}${list.length > 200 ? '<p class="note">Showing the first 200 — search or pick a class.</p>' : ''}`)}`;
}

function zeroCard() {
  const Z = D.kids.filter(x => x.zero && x.dueNow > 0), tags = [...new Set(Z.map(x => x.tagName))].sort();
  if (!Z.length) return card('Paid ₹0 this session', '<p class="empty">Every student has paid something this session.</p>');
  return card(`Paid ₹0 this session · ${Z.length} students · ${rsS(sum(Z, x => x.dueNow))}`, `<div class="tw"><table class="tbl"><tr><th>Tag</th><th>Students</th><th>Due</th><th></th></tr>${tags.map(t => { const G = Z.filter(x => x.tagName === t);
      return `<tr><td>${esc(t)}</td><td>${G.length}</td><td class="red">${rs(sum(G, x => x.dueNow))}</td><td><button class="chip" style="height:30px" onclick="msgTo('zero',${esc(JSON.stringify(t === NOTAG && S.tag === null ? NOTAG : t))})">${ic('wa', 14)} Message</button></td></tr>`; }).join('')}
    <tr class="tot"><td>Total</td><td>${Z.length}</td><td>${rs(sum(Z, x => x.dueNow))}</td><td><button class="chip on" style="height:30px" onclick="msgTo('zero',null)">Message all</button></td></tr></table></div>
    <p class="note">Tap Message to send these parents the "Nothing paid yet" message on WhatsApp.</p>${people(Z.slice().sort((a, b) => b.dueNow - a.dueNow).slice(0, 50), { calls: true })}`);
}
function msgTo(aud, tag) { S.aud = aud; S.tpl = AUD_TPL[aud]; S.mtext = null; MQ = null; S.cmode = 'msg'; if (tag !== undefined) S.tag = tag; calc(); goTo('calls'); }
// ---------------------------------------------------------------- Calling
function callBody() {
  const seg = `<div class="seg">${[['calls', 'Calls'], ['diary', 'Call diary'], ['msg', 'Messages'], ['log', 'Sent']].map(([k2, l]) => `<button class="${S.cmode === k2 ? 'on' : ''}" onclick="S.cmode='${k2}';MQ=null;render()">${l}</button>`).join('')}</div>`;
  if (S.cmode === 'msg') return seg + msgBody();
  if (S.cmode === 'log') return seg + msgLogBody();
  if (S.cmode === 'diary') return seg + diaryBody();
  return seg + callBodyCalls();
}
function msgAudience() { const f = (AUD.find(a => a[0] === S.aud) || AUD[0])[2]; let L = D.kids.filter(f); if (S.skipSent) L = L.filter(x => !(x.lastMsg && x.lastMsg.date === D.today)); return L.sort((a, b) => CLASSES.indexOf(a.cls) - CLASSES.indexOf(b.cls) || a.name.localeCompare(b.name)); }
function msgText() { return S.mtext !== null ? S.mtext : TPL[S.tpl][S.lang]; }
function msgBody() {
  if (MQ) return queueBody();
  const L = msgAudience(), withPh = L.filter(x => x.phoneNo), noPh = L.filter(x => !x.phoneNo), t = msgText(), ex = withPh[0] || L[0];
  const byTag = {}; L.forEach(x => { byTag[x.tagName] = (byTag[x.tagName] || 0) + 1; });
  return `${card('1 · Who should get it', `<div class="chips" style="flex-wrap:wrap;margin:0;padding:0">${AUD.map(([k2, l, f]) => `<button class="chip${S.aud === k2 ? ' on' : ''}" onclick="S.aud='${k2}';S.tpl=AUD_TPL['${k2}'];S.mtext=null;render()">${esc(l)} <b>${D.kids.filter(f).length}</b></button>`).join('')}</div>
      <p class="note">Tag and class from the filter at the top (${esc(S.tag === null ? 'all tags' : S.tag)}${S.cls ? ' · ' + esc(S.cls) : ''}). ${Object.entries(byTag).map(([t2, n]) => `${esc(t2)} ${n}`).join(' · ')}</p>
      <label class="chk" style="display:flex;gap:8px;align-items:center;margin-top:8px;font-size:12px"><input type="checkbox" ${S.skipSent ? 'checked' : ''} onchange="S.skipSent=this.checked;render()"> Leave out parents already messaged today</label>`)}
    ${card('2 · Message', `<div class="chips" style="flex-wrap:wrap;margin:0 0 10px;padding:0">${Object.entries(TPL).map(([k2, v]) => `<button class="chip${S.tpl === k2 ? ' on' : ''}" onclick="S.tpl='${k2}';S.mtext=null;render()">${esc(v.label)}</button>`).join('')}</div>
      <div class="seg" style="margin-bottom:10px"><button class="${S.lang === 'hi' ? 'on' : ''}" onclick="S.lang='hi';S.mtext=null;render()">हिन्दी</button><button class="${S.lang === 'en' ? 'on' : ''}" onclick="S.lang='en';S.mtext=null;render()">English</button></div>
      <textarea id="mtx" rows="6" style="width:100%;border:1px solid #E7E7EE;border-radius:12px;padding:10px;font:inherit;font-size:13px" oninput="S.mtext=this.value">${esc(t)}</textarea>
      <p class="note">Words in { } fill in for each child: {name} {class} {due} {months} {since} {old} {month} {monthdue}.</p>
      ${ex ? `<label class="lbl" style="margin-top:10px">PREVIEW · ${esc(ex.name)}</label><div class="share">${esc(fillMsg(t, ex))}</div>` : ''}`)}
    ${card(`3 · Send · ${withPh.length} parents`, `<div class="stat"><div class="st"><b>${withPh.length}</b><span>with a mobile number</span></div><div class="st ${noPh.length ? 'bad' : 'ok'}"><b>${noPh.length}</b><span>no mobile number</span></div></div>
      <div style="height:10px"></div><button class="cta" ${withPh.length ? '' : 'disabled'} onclick="startQueue()">${ic('wa', 18)} Send one by one on WhatsApp</button>
      <div style="height:8px"></div><button class="cta lt" onclick="copyNums()">Copy all ${withPh.length} numbers (for a WhatsApp broadcast)</button>
      <p class="note">Each tap opens WhatsApp with this child's own message ready; send it, come back and tap Next. Every message sent is saved in "Sent messages" and shows on the student. Sending to everyone automatically needs the paid WhatsApp Business API, which is not set up yet.</p>
      ${noPh.length ? `<p class="note"><b>No mobile:</b> ${noPh.slice(0, 30).map(x => esc(x.name + ' (' + x.cls + ')')).join(', ')}${noPh.length > 30 ? '…' : ''}</p>` : ''}`)}`;
}
function startQueue() { const L = msgAudience().filter(x => x.phoneNo); if (!L.length) return; MQ = { list: L.map(x => x.sid), i: 0, kind: TPL[S.tpl].label, text: msgText(), sent: 0 }; render(); }
function queueBody() {
  const sid = MQ.list[MQ.i], x = D.all.find(z => z.sid === sid);
  if (!x) return card('Done', `<p>All ${MQ.list.length} messages are done. ${MQ.sent} were sent.</p><button class="cta" onclick="MQ=null;S.cmode='log';render()">See sent messages</button>`);
  const t = fillMsg(MQ.text, x);
  return `${card(`Sending ${MQ.i + 1} of ${MQ.list.length} · ${MQ.kind}`, `<div class="meter" style="background:var(--soft)"><i style="width:${Math.round(MQ.i * 100 / MQ.list.length)}%;background:var(--a)"></i></div>
      <div class="p" style="border:0"><div class="ava">${esc(x.name.split(/\s+/).map(w => w[0]).slice(0, 2).join(''))}</div><div class="t"><b>${esc(x.name)}${x.tag ? `<span class="tag">${esc(x.tag)}</span>` : ''}</b><small>${esc(x.cls)} · ${esc(x.father || '')} · ${esc(x.phoneNo)}</small><small>Due ${rs(x.dueNow)}${x.since ? ' since ' + esc(x.since) : ''}</small></div></div>
      <div class="share">${esc(t)}</div>
      <a class="cta" target="_blank" rel="noopener" href="https://wa.me/91${x.phoneNo}?text=${encodeURIComponent(t)}" onclick="queueSent()">${ic('wa', 18)} Open WhatsApp and send</a>
      <div style="height:8px"></div><div class="rbtns"><button class="cta lt" onclick="MQ.i++;render()">Skip</button><button class="cta lt" onclick="MQ.i++;render()">Next</button><button class="cta dn" onclick="MQ=null;render()">Stop</button></div>`)}`;
}
function queueSent() { const sid = MQ.list[MQ.i], x = D.all.find(z => z.sid === sid); logMsg([sid], MQ.kind, fillMsg(MQ.text, x)); MQ.sent++; setTimeout(() => { if (MQ) { MQ.i++; render(); } }, 900); }
function copyNums() { const t = msgAudience().filter(x => x.phoneNo).map(x => '+91' + x.phoneNo).join(', '); (navigator.clipboard ? navigator.clipboard.writeText(t) : Promise.reject()).then(() => toast('Numbers copied.')).catch(() => toast('Copy did not work on this phone.')); }
function msgLogBody() {
  const M = D.msgs.slice(0, 300), today = D.msgs.filter(m => m.date === D.today), kinds = {}; D.msgs.forEach(m => { kinds[m.kind] = (kinds[m.kind] || 0) + (m.sids || []).length; });
  const name = sids => (sids || []).map(s2 => { const x = D.all.find(z => z.sid === s2); return x ? x.name + ' (' + x.cls + ')' : ''; }).join(', ');
  return `<div class="kp">${k('Sent today', String(sum(today, m => (m.sids || []).length)), 'parents', 'ok')}${k('Sent in all', String(sum(D.msgs, m => (m.sids || []).length)), Object.entries(kinds).map(([a, b]) => a + ' ' + b).slice(0, 3).join(' · '))}</div>
    ${card('Sent messages', M.length ? `<div class="tw"><table class="tbl"><tr><th>Date</th><th>Student</th><th>Message</th><th>By</th></tr>${M.map(m => `<tr><td>${dmy(m.date)}</td><td class="l">${esc(name(m.sids))}</td><td>${esc(m.kind)}</td><td>${esc(m.by || '')}</td></tr>`).join('')}</table></div>` : '<p class="empty">No message sent yet.</p>')}`;
}
function callBodyCalls() {
  const L = callLists(D.t.def), rows = (L[S.list] || L.all).slice().sort((a, b) => b.dueNow - a.dueNow), callsToday = D.calls.filter(c => c.date === D.today);
  const out = {}; callsToday.forEach(c => { out[c.outcome] = (out[c.outcome] || 0) + 1; });
  const lists = [['today', 'Follow-up today', L.today.length], ['fresh', 'Not called 7 days', L.fresh.length], ['broken', 'Promise broken', L.broken.length], ['all', 'All with dues', L.all.length]];
  return `<div class="kp">
      <div class="k big"><span>Calls made today</span><b>${callsToday.length}</b><small>${Object.entries(out).map(([o, n]) => `${esc(o)} ${n}`).join(' · ') || 'No call saved yet today'}</small></div>
      ${k('Promised today', String(L.today.filter(x => x.promise === D.today).length), rsS(sum(L.today.filter(x => x.promise === D.today), x => x.dueNow)), 'ok')}
      ${k('Promises broken', String(L.broken.length), rsS(sum(L.broken, x => x.dueNow)), 'bad', `S.list='broken';render()`)}
    </div>
    <div class="chips">${lists.map(([key, l, n]) => `<button class="chip${S.list === key ? ' on' : ''}" onclick="S.list='${key}';render()">${esc(l)} <b>${n}</b></button>`).join('')}</div>
    ${card(`${lists.find(x => x[0] === S.list)[1]} · ${rows.length} · ${rsS(sum(rows, x => x.dueNow))}`, people(rows.slice(0, 200), { calls: true }))}
    ${D.calls.length ? card('Recent calls', `<div class="tw"><table class="tbl"><tr><th>Date</th><th>Student</th><th>Result</th><th>Date given</th><th>By</th></tr>${D.calls.slice(0, 15).map(c => { const x = D.all.find(z => (c.sids || []).includes(z.sid)); return `<tr><td>${dmy(c.date)}</td><td>${esc(x ? x.name : '')}</td><td>${esc(c.outcome)}</td><td>${dmy(c.promise)}</td><td>${esc(c.by || '')}</td></tr>`; }).join('')}</table></div>`) : ''}`;
}
// ---------------------------------------------------------------- Reports: many report types, many filters
const RT = [['mcoll', '💰', 'Monthly collection'], ['students', '👨‍🎓', 'Student-wise dues'], ['zero', '🚫', 'Paid ₹0'], ['ledger', '📒', 'Full record (all students)'], ['class', '🏫', 'Class-wise'], ['tag', '🏷️', 'Tag-wise'], ['head', '📚', 'Fee head-wise'], ['month', '📅', 'Month-wise'],
  ['receipts', '🧾', 'Receipt register'], ['ageing', '⏳', 'Dues by months'], ['old', '📜', 'Old dues'], ['discount', '✂️', 'Discounts & cancels'],
  ['calls', '📞', 'Call diary'], ['attend', '🗓️', 'Attendance'], ['result', '🎓', 'Results / report cards']];
const RF = { type: 'mcoll', sec: '', fac: '', scheme: '', dmin: '', dmax: '', months: '', old: '', paid: '', upto: '', from: '', to: '', mode: '', user: '', sort: 'due' };
const hasTr = x => x.heads.some(h => isTr(h.name));
function repKids() {
  const upto = RF.upto ? MORDER.indexOf(+RF.upto) : D.idx, min = parseFloat(RF.dmin) || 0, max = parseFloat(RF.dmax) || Infinity, mo = parseInt(RF.months, 10) || 0;
  return D.kids.map(x => {
    const owe = (y) => Math.max(0, y.fd - y.fp) + Math.max(0, y.td - y.tp), M = x.mon.filter((y, i) => i <= upto);
    return { ...x, rDue: sum(M, owe) + x.old, rTr: sum(M, y => Math.max(0, y.td - y.tp)), rFee: sum(M, y => Math.max(0, y.fd - y.fp)), rMonths: M.filter(y => owe(y) > 0).length };
  }).filter(x => (!RF.sec || x.section === RF.sec) && (!RF.fac || (RF.fac === 'tr' ? hasTr(x) : !hasTr(x))) && (!RF.scheme || x.scheme === RF.scheme)
    && x.rDue >= min && x.rDue <= max && x.rMonths >= mo && (!RF.old || (RF.old === 'yes' ? x.old > 0 : x.old === 0)) && (!RF.paid || (RF.paid === 'none' ? x.paid === 0 : RF.paid === 'full' ? x.yearBal === 0 : x.paid > 0 && x.yearBal > 0)));
}
function repRcpts(cancelled) {
  return (cancelled ? D.cx : D.rc).filter(r => (!RF.from || r.date >= RF.from) && (!RF.to || r.date <= RF.to) && (!RF.mode || (r.mode || 'Cash') === RF.mode) && (!RF.user || r.user === RF.user) && (!RF.sec || r.section === RF.sec));
}
function fchips() {
  const L = [];
  if (S.tag !== null) L.push(['tag', 'Tag: ' + S.tag]); if (S.cls) L.push(['cls', 'Class ' + S.cls]); if (RF.sec) L.push(['sec', 'Section ' + RF.sec]);
  if (RF.fac) L.push(['fac', RF.fac === 'tr' ? 'With transport' : 'Without transport']); if (RF.scheme) L.push(['scheme', RF.scheme]);
  if (RF.dmin) L.push(['dmin', 'Due ≥ ' + rs(RF.dmin)]); if (RF.dmax) L.push(['dmax', 'Due ≤ ' + rs(RF.dmax)]); if (RF.months) L.push(['months', RF.months + '+ months unpaid']);
  if (RF.old) L.push(['old', RF.old === 'yes' ? 'Has old dues' : 'No old dues']); if (RF.paid) L.push(['paid', { none: 'Paid nothing', part: 'Paid part', full: 'Fully paid' }[RF.paid]]);
  if (RF.upto) L.push(['upto', 'Up to ' + MN[+RF.upto]]); if (RF.from) L.push(['from', 'From ' + dmy(RF.from)]); if (RF.to) L.push(['to', 'To ' + dmy(RF.to)]);
  if (RF.mode) L.push(['mode', RF.mode]); if (RF.user) L.push(['user', 'By ' + RF.user]);
  return `<div class="fbar"><button class="fchip add" onclick="sheet('rfilter')">＋ Filters${L.length ? ' (' + L.length + ')' : ''}</button>${L.map(([k, l]) => `<button class="fchip" onclick="clearRF('${k}')">${esc(l)} ✕</button>`).join('')}${L.length ? `<button class="fchip add" onclick="clearRF('*')">Clear all</button>` : ''}</div>`;
}
function clearRF(k) { if (k === '*') { Object.keys(RF).forEach(x => { if (x !== 'type' && x !== 'sort') RF[x] = ''; }); S.tag = null; S.cls = ''; calc(); } else if (k === 'tag') { S.tag = null; calc(); } else if (k === 'cls') { S.cls = ''; calc(); } else RF[k] = ''; render(); }
function setRF(k, v) { RF[k] = v; render(); }
let REP = null;   // the table now on screen, for Copy / Download
function repTable(head, rows, foot, cls) {
  REP = { head, rows: rows.map(r => r.map(c => typeof c === 'object' && c ? c.t : c)), foot };
  return `<div class="tw"><table class="tbl"><tr>${head.map((h, i) => `<th class="${cls && cls[i] || ''}">${esc(h)}</th>`).join('')}</tr>${rows.slice(0, 400).map(r => `<tr>${r.map((c, i) => `<td class="${(cls && cls[i]) || ''} ${typeof c === 'object' && c ? c.c || '' : ''}">${esc(typeof c === 'object' && c ? c.v : c)}</td>`).join('')}</tr>`).join('')}${foot ? `<tr class="tot">${foot.map(c => `<td>${esc(c)}</td>`).join('')}</tr>` : ''}</table></div>${rows.length > 400 ? `<p class="note">Showing 400 of ${rows.length} rows. Download for all of them.</p>` : ''}`;
}
const money2 = v => ({ v: rs(v), t: Math.round(v) });
const red = v => ({ v: rs(v), t: Math.round(v), c: 'red' });
function repBody() {
  const T = RF.type, K = repKids();
  let sumv = '', body = '';
  if (T === 'mcoll') {
    // The old ERP's Monthly Collection Report: every receipt dated in the month, whatever session it paid.
    // Total Fee = what the receipts settled, Discount on them, Recv Amount = money received.
    const R0 = repRcpts().filter(r => !S.cls || r.cls === S.cls);
    const R = MORDER.map(m => { const X = inMonth(R0, ymOf(m)), c = cashIn(X); return [MN[m], sum(X, r => r.total != null ? r.total : r.received + r.discount), sum(X, r => r.discount), sum(X, r => r.received), X.length, c.fee + c.mixed, c.tr]; });
    const tot = i => sum(R, r => r[i]);
    sumv = [['Total fee', rsS(tot(1))], ['Discount', rsS(tot(2))], ['Received', rsS(tot(3))], ['Receipts', tot(4)]];
    body = repTable(['Month', 'Total Fee', 'Discount', 'Recv Amount', 'Receipts', 'of which fee', 'of which transport'],
      R.map(r => [r[0], money2(r[1]), money2(r[2]), { v: rs(r[3]), t: Math.round(r[3]), c: 'grn' }, r[4], money2(r[5]), money2(r[6])]),
      ['Total', rs(tot(1)), rs(tot(2)), rs(tot(3)), tot(4), rs(tot(5)), rs(tot(6))]);
  } else if (T === 'students') {
    const L = K.filter(x => RF.dmin || RF.months || RF.old || RF.paid ? true : x.rDue > 0).sort(RF.sort === 'name' ? (a, b) => a.name.localeCompare(b.name) : RF.sort === 'class' ? (a, b) => CLASSES.indexOf(a.cls) - CLASSES.indexOf(b.cls) || a.name.localeCompare(b.name) : (a, b) => b.rDue - a.rDue);
    sumv = [['Students', L.length], ['Session fee', rsS(sum(L, x => x.annual))], ['Paid + disc', rsS(sum(L, x => x.paid))], ['Due (to month)', rsS(sum(L, x => x.rDue))], ['Overall due', rsS(sum(L, x => x.yearBal))], ['Transport due', rsS(sum(L, x => x.rTr))]];
    body = repTable(['#', 'Student', 'Class', 'Tag', 'Father', 'Mobile', 'Session fee', 'Paid', 'Fee due', 'Transport due', 'Old due', 'Due (to month)', 'Overall due', 'Due since', 'Unpaid months', 'Last paid', 'Last message'],
      L.map((x, i) => [i + 1, x.name, x.cls + (x.section ? '-' + x.section : ''), x.tag, x.father, x.phoneNo, money2(x.annual), money2(x.paid), money2(x.rFee), money2(x.rTr), money2(x.old), red(x.rDue), money2(x.yearBal), x.since, x.unpaid.join(' '), x.lastPaid ? dmy(x.lastPaid.date) : '', x.lastMsg ? dmy(x.lastMsg.date) : '']),
      ['', 'Total ' + L.length, '', '', '', '', rs(sum(L, x => x.annual)), rs(sum(L, x => x.paid)), rs(sum(L, x => x.rFee)), rs(sum(L, x => x.rTr)), rs(sum(L, x => x.old)), rs(sum(L, x => x.rDue)), rs(sum(L, x => x.yearBal)), '', '', '', ''], ['', 'l']);
  } else if (T === 'zero') {
    const L = K.filter(x => x.zero && x.rDue > 0).sort((a, b) => a.tagName.localeCompare(b.tagName) || CLASSES.indexOf(a.cls) - CLASSES.indexOf(b.cls));
    sumv = [['Students', L.length], ['Due (to month)', rsS(sum(L, x => x.rDue))], ['Overall due', rsS(sum(L, x => x.yearBal))], ['Messaged', L.filter(x => x.lastMsg).length]];
    body = repTable(['#', 'Student', 'Class', 'Tag', 'Father', 'Mobile', 'Due (to month)', 'Overall due', 'Old due', 'Last call', 'Last message'],
      L.map((x, i) => [i + 1, x.name, x.cls, x.tagName, x.father, x.phoneNo, red(x.rDue), money2(x.yearBal), money2(x.old), x.last ? dmy(x.last.date) + ' ' + x.last.outcome : '', x.lastMsg ? dmy(x.lastMsg.date) + ' ' + x.lastMsg.kind : '']),
      ['', 'Total ' + L.length, '', '', '', '', rs(sum(L, x => x.rDue)), rs(sum(L, x => x.yearBal)), rs(sum(L, x => x.old)), '', ''], ['', 'l']) + `<div style="height:10px"></div><button class="cta" onclick="msgTo('zero')">${ic('wa', 18)} Message these parents</button>`;
  } else if (T === 'ledger') {
    // every student, every month: paid / due — the whole record on one sheet
    const L = K.slice().sort((a, b) => CLASSES.indexOf(a.cls) - CLASSES.indexOf(b.cls) || a.name.localeCompare(b.name));
    sumv = [['Students', L.length], ['Session fee', rsS(sum(L, x => x.annual))], ['Paid + disc', rsS(sum(L, x => x.paid))], ['Overall due', rsS(sum(L, x => x.yearBal))]];
    const cell = (y, i) => { const due = Math.max(0, y.fd - y.fp) + Math.max(0, y.td - y.tp); return y.fd + y.td === 0 ? '' : due === 0 ? { v: '✓', t: 'Paid', c: 'grn' } : i > D.idx ? { v: '·' + Math.round(due), t: 'Due later ' + Math.round(due) } : { v: Math.round(due), t: Math.round(due), c: 'red' }; };
    body = repTable(['Student', 'Class', 'Tag', 'Old due', ...MORDER.map(M3), 'Paid', 'Overall due', 'Receipts'],
      L.map(x => [x.name, x.cls, x.tag, x.old ? money2(x.old) : '', ...x.mon.map(cell), money2(x.paid), red(x.yearBal), x.rcs.map(r => dmy(r.date) + ' ₹' + Math.round(r.received)).slice(0, 6).join(', ')]),
      ['Total ' + L.length, '', '', rs(sum(L, x => x.old)), ...MORDER.map((m, i) => rs(sum(L, x => Math.max(0, x.mon[i].fd - x.mon[i].fp) + Math.max(0, x.mon[i].td - x.mon[i].tp)))), rs(sum(L, x => x.paid)), rs(sum(L, x => x.yearBal)), ''], ['l']) + '<p class="note">✓ paid · red = still due · grey dot = not due yet. Tap a student anywhere in the app for the full statement.</p>';
  } else if (T === 'class' || T === 'tag') {
    const keys = T === 'class' ? CLASSES.filter(c => K.some(x => x.cls === c)) : [...new Set(K.map(x => x.tagName))];
    const R = keys.map(key => { const G = K.filter(x => (T === 'class' ? x.cls : x.tagName) === key); return [key, G.length, G.filter(x => x.rDue > 0).length, sum(G, x => x.annual), sum(G, x => x.paid), sum(G, x => x.rFee), sum(G, x => x.rTr), sum(G, x => x.old), sum(G, x => x.rDue), sum(G, x => x.yearBal)]; });
    const tot = i => sum(R, r => r[i]);
    sumv = [['Groups', R.length], ['Students', tot(1)], ['With dues', tot(2)], ['Paid + disc', rsS(tot(4))], ['Due (to month)', rsS(tot(8))], ['Overall due', rsS(tot(9))]];
    body = repTable([T === 'class' ? 'Class' : 'Tag', 'Students', 'With dues', 'Session fee', 'Paid', 'Fee due', 'Transport due', 'Old due', 'Due (to month)', 'Overall due', 'Collected %'],
      R.map(r => [r[0], r[1], r[2], money2(r[3]), money2(r[4]), money2(r[5]), money2(r[6]), money2(r[7]), red(r[8]), money2(r[9]), pct(r[4], r[4] + r[8]) + '%']),
      ['Total', tot(1), tot(2), rs(tot(3)), rs(tot(4)), rs(tot(5)), rs(tot(6)), rs(tot(7)), rs(tot(8)), rs(tot(9)), pct(tot(4), tot(4) + tot(8)) + '%']);
  } else if (T === 'head') {
    const upto = RF.upto ? MORDER.indexOf(+RF.upto) : D.idx, H = {};
    K.forEach(x => x.heads.forEach(h => { const o = H[h.name] = H[h.name] || { n: 0, amt: 0, paid: 0, now: 0 }; o.amt += h.amt; o.paid += h.paid; if (MORDER.indexOf(h.m) <= upto) o.now += Math.max(0, h.amt - h.paid); }));
    const R = Object.entries(H).sort((a, b) => b[1].amt - a[1].amt);
    sumv = [['Fee heads', R.length], ['Expected', rsS(sum(R, r => r[1].amt))], ['Paid + disc', rsS(sum(R, r => r[1].paid))], ['Due (to month)', rsS(sum(R, r => r[1].now))]];
    body = repTable(['Fee head', 'Expected (session)', 'Paid + disc', 'Due (to month)', 'Overall due', 'Collected %'],
      R.map(([n, o]) => [n, money2(o.amt), money2(o.paid), red(o.now), money2(o.amt - o.paid), pct(o.paid, o.amt) + '%']),
      ['Total', rs(sum(R, r => r[1].amt)), rs(sum(R, r => r[1].paid)), rs(sum(R, r => r[1].now)), rs(sum(R, r => r[1].amt - r[1].paid)), pct(sum(R, r => r[1].paid), sum(R, r => r[1].amt)) + '%']);
  } else if (T === 'month') {
    const R = MORDER.map(m => { const p = monthPos(K, m), c = cashIn(inMonth(repRcpts(), ymOf(m))); return [MN[m], p.fd, p.td, p.got, p.due, p.pend.length, c.fee + c.mixed, c.tr, c.total]; });
    const tot = i => sum(R, r => r[i]);
    sumv = [['Expected', rsS(tot(1) + tot(2))], ['Received against', rsS(tot(3))], ['Pending', rsS(tot(4))], ['Money in', rsS(tot(8))]];
    body = repTable(['Month', 'Fee expected', 'Transport expected', 'Received against', 'Pending', 'Students pending', 'Fee came in', 'Transport came in', 'Total came in'],
      R.map(r => [r[0], money2(r[1]), money2(r[2]), money2(r[3]), red(r[4]), r[5], money2(r[6]), money2(r[7]), money2(r[8])]), ['Total', rs(tot(1)), rs(tot(2)), rs(tot(3)), rs(tot(4)), '', rs(tot(6)), rs(tot(7)), rs(tot(8))]);
  } else if (T === 'receipts') {
    const R = repRcpts().filter(r => !RF.from && !RF.to ? r.session === D.ses : true).filter(r => !S.cls || r.cls === S.cls).sort((a, b) => b.date.localeCompare(a.date) || b.no.localeCompare(a.no));
    const byMode = {}; R.forEach(r => { byMode[r.mode || 'Cash'] = (byMode[r.mode || 'Cash'] || 0) + r.received; });
    sumv = [['Receipts', R.length], ['Total', rsS(sum(R, r => r.received))], ['Fee', rsS(sum(R.filter(r => r.split), r => r.received - r.tr) + sum(R.filter(r => !r.split), r => r.received))], ['Transport', rsS(sum(R, r => r.tr))], ...Object.entries(byMode).slice(0, 2).map(([m, v]) => [m, rsS(v)])];
    body = repTable(['Date', 'Receipt', 'Student', 'Class', 'Amount', 'Transport part', 'Discount', 'Late fee', 'Mode', 'By'],
      R.map(r => [dmy(r.date), r.no, r.name, r.cls + (r.section ? '-' + r.section : ''), money2(r.received), r.split ? money2(r.tr) : '—', money2(r.discount), money2(r.late), r.mode, r.user]),
      ['Total ' + R.length, '', '', '', rs(sum(R, r => r.received)), rs(sum(R, r => r.tr)), rs(sum(R, r => r.discount)), rs(sum(R, r => r.late)), '', ''], [, , 'l']);
  } else if (T === 'ageing') {
    const B = [['1 month', x => x.rMonths === 1], ['2 months', x => x.rMonths === 2], ['3 months', x => x.rMonths === 3], ['4–5 months', x => x.rMonths >= 4 && x.rMonths <= 5], ['6+ months', x => x.rMonths >= 6], ['Old dues only', x => x.rMonths === 0 && x.old > 0]];
    const R = B.map(([l, f]) => { const G = K.filter(x => x.rDue > 0 && f(x)); return [l, G.length, sum(G, x => x.rDue), sum(G, x => x.old)]; });
    sumv = [['Students with dues', sum(R, r => r[1])], ['Due (to month)', rsS(sum(R, r => r[2]))], ['6+ months', R[4][1] + ' students']];
    body = repTable(['Unpaid for', 'Students', 'Due (to month)', 'of which old dues'], R.map(r => [r[0], r[1], red(r[2]), money2(r[3])]), ['Total', sum(R, r => r[1]), rs(sum(R, r => r[2])), rs(sum(R, r => r[3]))]);
  } else if (T === 'old') {
    const L = K.filter(x => x.old > 0).sort((a, b) => b.old - a.old);
    sumv = [['Students', L.length], ['Old dues', rsS(sum(L, x => x.old))]];
    body = repTable(['#', 'Student', 'Class', 'Tag', 'Father', 'Old due', 'This session due'], L.map((x, i) => [i + 1, x.name, x.cls, x.tag, x.father, red(x.old), money2(x.rDue - x.old)]), ['', 'Total ' + L.length, '', '', '', rs(sum(L, x => x.old)), rs(sum(L, x => x.rDue - x.old))], ['', 'l']);
  } else if (T === 'discount') {
    const Dd = repRcpts().filter(r => r.discount > 0 && (RF.from || RF.to || r.session === D.ses)), Cx = repRcpts(true).filter(r => RF.from || RF.to || r.session === D.ses);
    sumv = [['Discount receipts', Dd.length], ['Discount given', rsS(sum(Dd, r => r.discount))], ['Cancelled', Cx.length], ['Cancelled amount', rsS(sum(Cx, r => r.received))]];
    body = repTable(['Date', 'Receipt', 'Student', 'Class', 'Type', 'Amount', 'By'], [...Dd.map(r => [dmy(r.date), r.no, r.name, r.cls, 'Discount', money2(r.discount), r.user]), ...Cx.map(r => [dmy(r.date), r.no, r.name, r.cls, { v: 'Cancelled', t: 'Cancelled', c: 'red' }, red(r.received), r.user])], null, [, , 'l']);
  }
  else if (T === 'calls' || T === 'attend' || T === 'result') { const o = repSchool(T, K); sumv = o.sumv; body = o.body; }
  const sortSel = T === 'students' ? `<select onchange="setRF('sort',this.value)" style="border:1px solid #E7E7EE;border-radius:10px;height:34px;background:#fff;font-size:12px"><option value="due" ${RF.sort === 'due' ? 'selected' : ''}>Highest due</option><option value="class" ${RF.sort === 'class' ? 'selected' : ''}>Class</option><option value="name" ${RF.sort === 'name' ? 'selected' : ''}>Name</option></select>` : '';
  return `<div class="rtypes">${RT.map(([key, icon, l]) => `<button class="rt${T === key ? ' on' : ''}" onclick="setRF('type','${key}')"><i>${icon}</i>${esc(l)}</button>`).join('')}</div>
    ${fchips()}
    <div class="kp">${sumv.map(([l, v]) => k(l, String(v), '', '')).join('')}</div>
    <section class="card"><h3>${esc(RT.find(x => x[0] === T)[2])}${sortSel}</h3>${body}
      <div class="rbtns" style="margin-top:12px"><button class="cta" onclick="downloadRep()">Download (Excel)</button><button class="cta lt" onclick="copyRep()">Copy</button></div>
      ${['calls', 'attend', 'result'].includes(T) ? '' : `<p class="note">Session ${esc(longSes(D.ses))}. "Due (to month)" counts fee months up to ${MN[RF.upto ? +RF.upto : (D.idx < 0 ? 4 : MORDER[Math.min(D.idx, 11)])]} plus old dues.</p>`}</section>`;
}
function repText(sep) { if (!REP) return ''; const q = v => { v = String(v ?? ''); return sep === ',' && /[",\n]/.test(v) ? '"' + v.replace(/"/g, '""') + '"' : v; }; return [REP.head, ...REP.rows, ...(REP.foot ? [REP.foot] : [])].map(r => r.map(q).join(sep)).join('\n'); }
function downloadRep() {
  try { const blob = new Blob(['﻿' + repText(',')], { type: 'text/csv;charset=utf-8' }), a = document.createElement('a'); a.href = URL.createObjectURL(blob); a.download = 'SNMSIS-' + RF.type + '-' + D.today + '.csv'; document.body.appendChild(a); a.click(); a.remove(); toast('Downloaded. Open it in Excel.'); }
  catch (e) { copyRep(); }
}
function copyRep() { (navigator.clipboard ? navigator.clipboard.writeText(repText('\t')) : Promise.reject()).then(() => toast('Copied. Paste it in Excel or WhatsApp.')).catch(() => toast('Copy did not work on this phone.')); }
function rfilterSheet() {
  const sel = (key, opts, label, full) => `<div class="${full ? 'full' : ''}"><label class="lbl">${label}</label><div class="inp"><select onchange="RF['${key}']=this.value;render()">${opts.map(([v, t]) => `<option value="${esc(v)}" ${String(RF[key]) === String(v) ? 'selected' : ''}>${esc(t)}</option>`).join('')}</select></div></div>`;
  const inp = (key, label, type, ph) => `<div><label class="lbl">${label}</label><div class="inp"><input type="${type}" value="${esc(RF[key])}" placeholder="${ph || ''}" onchange="RF['${key}']=this.value;render()" ${type === 'number' ? 'inputmode="numeric"' : ''}></div></div>`;
  const secs = [...new Set(D.all.filter(x => !S.cls || x.cls === S.cls).map(x => x.section).filter(Boolean))].sort();
  const schemes = [...new Set(D.all.map(x => x.scheme).filter(Boolean))].sort(), users = [...new Set(D.receipts.map(r => r.user).filter(Boolean))].sort(), modes = [...new Set(D.receipts.map(r => r.mode || 'Cash'))].sort();
  return `<h2>Filters</h2><div class="fgrid">
    <div><label class="lbl">TAG</label><div class="inp"><select onchange="setF('tag',this.value==='*'?null:this.value);S.sheet='rfilter';render()"><option value="*">All tags</option>${D.tags.map(t => `<option ${S.tag === t ? 'selected' : ''}>${esc(t)}</option>`).join('')}</select></div></div>
    <div><label class="lbl">CLASS</label><div class="inp"><select onchange="setF('cls',this.value);S.sheet='rfilter';render()"><option value="">All classes</option>${CLASSES.filter(c => D.all.some(x => x.cls === c)).map(c => `<option ${S.cls === c ? 'selected' : ''}>${c}</option>`).join('')}</select></div></div>
    ${sel('sec', [['', 'All sections'], ...secs.map(x => [x, x])], 'SECTION')}
    ${sel('fac', [['', 'All'], ['tr', 'With transport'], ['no', 'Without transport']], 'TRANSPORT')}
    ${sel('scheme', [['', 'All schemes'], ...schemes.map(x => [x, x])], 'SCHEME')}
    ${sel('upto', [['', 'This month'], ...MORDER.map(m => [m, MN[m]])], 'DUE COUNTED UP TO')}
    ${inp('dmin', 'DUE AT LEAST ₹', 'number', '0')}${inp('dmax', 'DUE AT MOST ₹', 'number', 'any')}
    ${sel('months', [['', 'Any'], ['1', '1+ months'], ['2', '2+ months'], ['3', '3+ months'], ['4', '4+ months'], ['6', '6+ months']], 'UNPAID MONTHS')}
    ${sel('old', [['', 'All'], ['yes', 'Has old dues'], ['no', 'No old dues']], 'OLD DUES')}
    ${sel('paid', [['', 'All'], ['none', 'Paid nothing'], ['part', 'Paid part'], ['full', 'Fully paid']], 'PAYMENT STATUS', true)}
    ${inp('from', 'RECEIPTS FROM', 'date')}${inp('to', 'RECEIPTS TO', 'date')}
    ${sel('mode', [['', 'All modes'], ...modes.map(x => [x, x])], 'PAYMENT MODE')}
    ${sel('user', [['', 'Everyone'], ...users.map(x => [x, x])], 'COLLECTED BY')}
    </div><div class="rbtns"><button class="cta lt" onclick="clearRF('*');S.sheet='rfilter';render()">Clear all</button><button class="cta" onclick="sheet(null)">Show report</button></div>`;
}

// ---------------------------------------------------------------- sheets
// ---- one student's full record: since when what is due, what was paid, every receipt, calls and messages
function openStu(sid) { S.prof = sid; S.ptab = S.ptab && S.prof === sid ? S.ptab : 'over'; S.open = sid; S.sheet = null; render(); const sc = $('sc'); if (sc) sc.scrollTop = 0; loadProf(sid); }
function stuSheet() {
  const x = D.all.find(z => z.sid === S.open); if (!x) return stuSheetLite(S.open);
  const rows = x.mon.map((y, i) => { const due = Math.max(0, y.fd - y.fp) + Math.max(0, y.td - y.tp), st = y.fd + y.td === 0 ? '—' : due === 0 ? 'Paid' : i > D.idx ? 'Not due yet' : y.fp + y.tp > 0 ? 'Part paid' : 'Unpaid';
    return `<tr class="${st === 'Unpaid' || st === 'Part paid' ? 'hl' : ''}"><td>${MN[y.m].slice(0, 3)} ${ymOf(y.m).slice(2, 4)}</td><td>${rs(y.fd)}</td><td>${rs(y.td)}</td><td>${rs(y.fp + y.tp)}</td><td class="${due && i <= D.idx ? 'red' : ''}">${rs(due)}</td><td>${st}</td></tr>`; }).join('');
  return `<h2>${esc(x.name)}</h2><p class="mut" style="margin:-6px 0 10px">${esc(x.cls)}${x.section ? '-' + esc(x.section) : ''} · ${esc(x.father || '')}${x.adm ? ' · Adm ' + esc(x.adm) : ''}${x.tag ? ` · <span class="tag">${esc(x.tag)}</span>` : ''}</p>
    <div class="kp">${k('Due till now', rs(x.dueNow), x.since ? 'since ' + esc(x.since) : 'nothing due', x.dueNow ? 'bad' : 'ok')}${k('Paid + discount', rs(x.paid), 'this session on the fee card', 'ok')}
      ${k('Session fee', rs(x.annual), `Overall due ${rs(x.yearBal)}`)}${k('Old dues', rs(x.old), 'earlier sessions', x.old ? 'warn' : '')}</div>
    <div class="acts" style="margin:12px 0;gap:10px">${x.phoneNo ? `<a class="cta" style="flex:1;height:42px" href="tel:+91${x.phoneNo}" onclick="callNow('${esc(x.sid)}')">${ic('phone', 16)} Call</a><a class="cta" style="flex:1;height:42px;background:#25D366" target="_blank" rel="noopener" href="https://wa.me/91${x.phoneNo}?text=${encodeURIComponent(remind(x))}" onclick="logMsg(['${esc(x.sid)}'],'Fee reminder',remind(D.all.find(z=>z.sid==='${esc(x.sid)}')))">${ic('wa', 16)} Reminder</a>` : '<p class="note">No mobile number on record.</p>'}</div>
    <label class="lbl">MONTH BY MONTH · ${esc(longSes(D.ses))}</label><div class="tw"><table class="tbl"><tr><th>Month</th><th>Fee</th><th>Transport</th><th>Paid</th><th>Due</th><th>Status</th></tr>${rows}
      <tr class="tot"><td>Total</td><td>${rs(sum(x.mon, y => y.fd))}</td><td>${rs(sum(x.mon, y => y.td))}</td><td>${rs(x.paid)}</td><td>${rs(x.yearBal - x.old)}</td><td></td></tr></table></div>
    <label class="lbl" style="margin-top:14px">RECEIPTS · ${x.rcs.length}</label>${x.rcs.length ? `<div class="tw"><table class="tbl"><tr><th>Date</th><th>Receipt</th><th>Session</th><th>Months</th><th>Amount</th><th>Discount</th><th>Mode</th></tr>${x.rcs.map(r => `<tr><td>${dmy(r.date)}</td><td>${esc(r.no)}</td><td>${esc(longSes(r.session))}</td><td>${esc(r.months || '')}</td><td>${rs(r.received)}</td><td>${rs(r.discount)}</td><td>${esc(r.mode)}</td></tr>`).join('')}
      <tr class="tot"><td>Total</td><td></td><td></td><td></td><td>${rs(sum(x.rcs, r => r.received))}</td><td>${rs(sum(x.rcs, r => r.discount))}</td><td></td></tr></table></div>` : '<p class="note">No receipt on record.</p>'}
    <label class="lbl" style="margin-top:14px;display:flex;align-items:center;justify-content:space-between">CALLS — WHEN AND WHAT WAS DISCUSSED <button class="fchip add" onclick="noteCall('${esc(x.sid)}')">＋ Write a call</button></label>
    ${x.calls.length ? diaryList(x.calls.slice(0, 20)) : '<p class="note">No call yet. After talking to the parent, tap “Write a call” and note what was discussed.</p>'}
    <label class="lbl" style="margin-top:14px">MESSAGES · ${x.msgs.length}</label>${x.msgs.length ? `<p class="note">${x.msgs.slice(0, 12).map(m => dmy(m.date) + ' · ' + esc(m.kind) + ' · ' + esc(m.by || '')).join('<br>')}</p>` : '<p class="note">No message sent yet.</p>'}
    ${stuSchool(x.sid)}`;
}
function sheetBody() {
  if (S.sheet === 'stu') return stuSheet();
  if (S.sheet === 'ses') return `<h2>Session</h2>${D.sessions.map(s => `<button class="opt${s === D.ses ? ' on' : ''}" onclick="S.sheet=null;S.m=0;load('${esc(s)}')">${esc(longSes(s))}</button>`).join('')}`;
  if (S.sheet === 'filter') return `<h2>Filter</h2><label class="lbl">TAG</label>${[null, ...D.tags].map(t => `<button class="opt${S.tag === t ? ' on' : ''}" onclick="S.sheet=null;setF('tag',${esc(JSON.stringify(t))})">${esc(t === null ? 'All tags' : t)} <small>${(t === null ? D.all : D.all.filter(x => x.tagName === t)).length} students</small></button>`).join('')}
    <label class="lbl" style="margin-top:14px">CLASS</label><div class="inp"><select onchange="S.sheet=null;setF('cls',this.value)"><option value="">All classes</option>${CLASSES.filter(c => D.all.some(x => x.cls === c)).map(c => `<option ${S.cls === c ? 'selected' : ''}>${c}</option>`).join('')}</select></div>`;
  if (S.sheet === 'call') return callSheet();
  if (S.sheet === 'scls') return clsSheet();
  if (S.sheet === 'share') return `<h2>Today's summary</h2><div class="share" id="shareT">${esc(S.share)}</div><a class="cta" target="_blank" rel="noopener" href="https://wa.me/?text=${encodeURIComponent(S.share)}">${ic('wa', 18)} Send on WhatsApp</a><div style="height:8px"></div><button class="cta lt" onclick="copyShare()">Copy text</button>`;
  if (S.sheet === 'tags') return tagSheet();
  if (S.sheet === 'rfilter') return rfilterSheet();
  if (S.sheet === 'me') return `<h2>SNMSIS · ${esc(D.role === 'principal' ? 'Principal' : 'Chairman')}</h2>
    ${D.role === 'principal' ? `<button class="opt" onclick="sheet('tags')">Set tags and fixed plans <small>Company, FD, Plan…</small></button>` : `<button class="opt" onclick="sheet('tags')">Tags <small>see each tag</small></button>`}
    <button class="opt" onclick="S.sheet=null;share()">Share today's summary <small>WhatsApp</small></button>
    <button class="opt" onclick="S.sheet=null;playIntro(true)">Play the welcome again</button>
    <button class="opt" onclick="toggleIntro()">Welcome on every open <small>${store.get('ch_intro') === 'day' ? 'Once a day' : 'Every time'}</small></button>
    <a class="opt" href="feedash.php" target="_blank" rel="noopener">Office Fee Control Room <small>full tables</small></a>
    <div style="height:12px"></div><button class="cta dn" onclick="logout()">Log out</button>`;
  return '';
}
function tagSheet() {
  const Pp = D.role === 'principal';
  const R = D.tags.map(tg => { const K = D.all.filter(x => x.tagName === tg); return { tg, n: K.length, fx: K.filter(x => x.fixed > 0).length, paid: sum(K, x => x.paid), due: sum(K, x => x.dueNow) }; });
  const q = (S.tq || '').toLowerCase(), list = D.all.filter(x => !q || [x.name, x.father, x.cls, x.tagName].some(v => String(v || '').toLowerCase().includes(q))).slice(0, 80);
  return `<h2>Tags</h2><div class="tw"><table class="tbl"><tr><th>Tag</th><th>Students</th><th>Fixed plan</th><th>Collected</th><th>Due now</th></tr>${R.map(x => `<tr><td>${esc(x.tg)}</td><td>${x.n}</td><td>${x.fx || ''}</td><td>${rs(x.paid)}</td><td class="red">${rs(x.due)}</td></tr>`).join('')}</table></div>
    <p class="note">Tags come from the old ERP (Tag Mgmt); PEN tags are left out. ${Pp ? 'Set a tag or a fixed amount a month (FD / plan: only this much fee a month, transport extra) below.' : 'Only the Principal can change tags.'}</p>
    ${Pp ? `<div class="inp" style="margin-top:12px">${ic('search', 16)}<input id="tq" value="${esc(S.tq || '')}" placeholder="Search a student" oninput="S.tq=this.value;render();refocus('tq')"></div>
      <datalist id="tagl">${[...new Set(['Company', 'FD', 'Plan', 'One Time Payment', 'Transport + Fee', 'Fee', ...D.tags.filter(t => t !== NOTAG)])].map(t => `<option value="${esc(t)}">`).join('')}</datalist>
      <div class="ppl">${list.map(x => S.tedit === x.sid ? `<div class="p" style="flex-wrap:wrap"><div class="t"><b>${esc(x.name)}</b><small>${esc(x.cls)} · ${esc(x.father || '')}</small></div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;width:100%"><div class="inp" style="margin:0"><input id="tT" list="tagl" value="${esc(x.tag)}" placeholder="Tag"></div><div class="inp" style="margin:0"><input id="tF" inputmode="decimal" value="${x.fixed || ''}" placeholder="Fixed ₹/month"></div></div>
          <div style="display:flex;gap:8px;width:100%"><button class="cta" style="height:40px" onclick="saveTag('${esc(x.sid)}')">Save</button><button class="cta lt" style="height:40px" onclick="S.tedit='';render()">Cancel</button></div></div>`
        : `<div class="p"><div class="t"><b>${esc(x.name)}${x.tag ? `<span class="tag">${esc(x.tag)}</span>` : ''}</b><small>${esc(x.cls)} · ${esc(x.father || '')}${x.fixed ? ' · fixed ' + rs(x.fixed) + '/month' : ''}</small></div><div class="r"><button class="chip" onclick="S.tedit='${esc(x.sid)}';render()">Edit</button></div></div>`).join('')}</div>` : ''}`;
}
async function saveTag(sid) {
  try { const d = await api('feedash_api.php?a=setTag', { sid, tag: $('tT').value, fixed: $('tF').value, note: '' }); const r = D.dues.find(x => x.sid === sid); if (r) { r.tag = d.tag.tag; r.fixed = d.tag.fixed / 100; } S.tedit = ''; calc(); toast('Tag saved.'); render(); }
  catch (e) { toast(e.message); }
}
function share() {
  const t = D.t, m = +D.today.slice(5, 7), c = cashIn(inMonth(D.rc, D.today.slice(0, 7))), p = monthPos(D.kids, m), td = cashIn(D.rc.filter(r => r.date === D.today));
  S.share = `*SN Mishra Singapore International School*\nFee summary · ${dmy(D.today)}${S.tag !== null ? '\nTag: ' + S.tag : ''}${S.cls ? '\nClass: ' + S.cls : ''}\n\n` +
    `Today: ${rs(td.total)} (${td.n} receipts)\n${MN[m]} received: ${rs(c.total)} (Fee ${rs(c.fee + c.mixed)} + Transport ${rs(c.tr)})\n${MN[m]} expected: ${rs(p.exp)} · received ${rs(p.got)} · pending ${rs(p.due)} (${p.pend.length} students)\n\n` +
    `Session fee: ${rs(t.annual)}\nCollected: ${rs(t.paid)} (${pct(t.paid, t.paid + t.dueNow)}%)\nDue till now: ${rs(t.dueNow)} (${t.def.length} students)\nOverall dues: ${rs(t.yearBal)}`;
  sheet('share');
}
function copyShare() { (navigator.clipboard ? navigator.clipboard.writeText(S.share) : Promise.reject()).then(() => toast('Copied.')).catch(() => { const r = document.createRange(); r.selectNodeContents($('shareT')); const s = getSelection(); s.removeAllRanges(); s.addRange(r); toast('Text selected — copy it.'); }); }
function toggleIntro() { store.set('ch_intro', store.get('ch_intro') === 'day' ? 'every' : 'day'); render(); }

// ---------------------------------------------------------------- install
const STANDALONE = () => matchMedia('(display-mode: standalone)').matches || navigator.standalone === true;
addEventListener('beforeinstallprompt', e => { e.preventDefault(); INST = e; if (D && !INTRO) render(); });
addEventListener('appinstalled', () => { INST = null; toast('Installed. Open SNMSIS from your home screen.'); if (D && !INTRO) render(); });
function installBox() {
  if (STANDALONE()) return '';
  if (INST) return `<button class="cta" onclick="doInstall()">Install the SNMSIS app</button>`;
  return `<div class="inst">${ill('school')}<div><b>Install this app</b>${/iphone|ipad|ipod/i.test(navigator.userAgent) ? 'In Safari tap Share → Add to Home Screen.' : 'In Chrome tap ⋮ → Install app (or Add to Home screen).'}</div></div>`;
}
async function doInstall() { if (!INST) return; INST.prompt(); await INST.userChoice; INST = null; render(); }

// ---------------------------------------------------------------- login (the school's Chairman / Principal PIN, kept on this phone for 30 days)
function loginView() {
  theme();
  const L = LOGIN || {}, who = store.get('ch_role') || 'chairman';
  $('app').innerHTML = L.net ? `<div class="load">${esc(L.msg)}<button class="cta" style="width:auto;padding:0 24px" onclick="load()">Try again</button></div>` : `<div class="band"><span class="orb" style="width:220px;height:220px;left:-60px;top:-80px"></span><span class="orb" style="width:140px;height:140px;right:-40px;bottom:-30px"></span>
      <img src="${LOGO}" alt=""><div class="wm">SNMSIS</div><small>SN Mishra Singapore International School</small></div>
    <div class="sheetw">${L.step === 'new' ? `<h1>Choose your own PIN</h1><p>First login: please choose a new PIN (4–8 digits, not 1234).</p>
        <input id="lgOld" class="pin" type="password" inputmode="numeric" placeholder="Current PIN" autocomplete="current-password"><input id="lgNew" class="pin" type="password" inputmode="numeric" placeholder="New PIN" autocomplete="new-password"><button class="cta" onclick="setPin()">Save PIN</button>`
      : `<h1>Welcome, Chairman Sir</h1><p>Enter your PIN to open today's figures.</p>
        <div class="seg"><button class="${who === 'chairman' ? 'on' : ''}" onclick="store.set('ch_role','chairman');loginView()">Chairman</button><button class="${who === 'principal' ? 'on' : ''}" onclick="store.set('ch_role','principal');loginView()">Principal</button></div>
        <input id="lgPin" class="pin" type="password" inputmode="numeric" placeholder="PIN" autocomplete="current-password" onkeydown="if(event.key==='Enter')login()"><button class="cta" onclick="login()">Open</button>`}
      <p class="err">${esc(L.msg || '')}</p>${installBox()}</div>`;
}
async function login() {
  try { const d = await api('api.php?a=login', { role: store.get('ch_role') || 'chairman', pin: $('lgPin').value }); store.set('sch_token', d.token); if (d.mustChange) { LOGIN = { step: 'new' }; return loginView(); } $('app').innerHTML = '<div class="load"><i></i>Opening…</div>'; load(); }
  catch (e) { LOGIN = { msg: e.message }; loginView(); }
}
async function setPin() { try { await api('api.php?a=setpin', { old: $('lgOld').value, pin: $('lgNew').value }); toast('PIN saved.'); load(); } catch (e) { LOGIN = { step: 'new', msg: e.message }; loginView(); } }
async function logout() { try { await api('api.php?a=logout', {}); } catch (e) {} try { localStorage.removeItem('sch_token'); } catch (e) {} D = null; LOGIN = {}; S.sheet = null; render(); }

// ---------------------------------------------------------------- the 20-second welcome (story style, like the Smart Sarathi customer welcome)
// 1 logo · 2 SNMSIS in many languages · 3 Welcome, Chairman Sir + thought · 4 the school today, counting up · 5 the promise.
const SCENES = [7000, 2600, 4200, 4200, 2600];
function introHtml() {
  const thought = THOUGHTS[Math.floor(Date.now() / 864e5) % THOUGHTS.length];
  return `<div class="ad" id="ad"><div class="ad-bars">${SCENES.map(() => '<i></i>').join('')}</div><button class="ad-skip" onclick="endIntro()">Skip</button>
    <section class="ad-s s1"><img class="ad-logo" src="${LOGO}" alt=""><div class="lang" id="lang"></div><p class="ad-t">SN Mishra Singapore International School · Bhadohi</p></section>
    <section class="ad-s s2"><p class="ad-k">One school · every language</p><h1 class="ad-h xl">SNMSIS</h1><p class="ad-t">A name the whole world can read — and a school the whole town trusts.</p></section>
    <section class="ad-s s3"><p class="ad-k">Welcome</p><h1 class="ad-h">Welcome,<br><span class="ad-gold">Chairman Sir</span></h1><p class="ad-t">“${esc(thought)}”</p></section>
    <section class="ad-s s4"><p class="ad-k">Your school today</p><h1 class="ad-h">Built by your vision</h1><div class="ad-grid" id="adNums"></div></section>
    <section class="ad-s s5"><p class="ad-k">Every rupee · every child</p><h1 class="ad-h">Clear, honest,<br>under your control</h1>
      <ul class="ad-ticks"><li style="--d:.1s">Every receipt recorded</li><li style="--d:.4s">Every due followed up</li><li style="--d:.7s">Every month, side by side</li></ul><button class="ad-go" onclick="endIntro()">Open the dashboard</button></section></div>`;
}
function playIntro(force) {
  const mode = store.get('ch_intro'), seen = store.get('ch_intro_day') === new Date().toISOString().slice(0, 10);
  if (!force && mode === 'day' && seen) return false;
  store.set('ch_intro_day', new Date().toISOString().slice(0, 10));
  $('phone').insertAdjacentHTML('beforeend', introHtml());
  INTRO = { i: -1, timers: [] }; nextScene(); fillIntro(); return true;
}
function nextScene() {
  if (!INTRO) return; const ad = $('ad'); if (!ad) return;
  INTRO.i++; if (INTRO.i >= SCENES.length) return endIntro();
  const bars = ad.querySelectorAll('.ad-bars i'), sc = ad.querySelectorAll('.ad-s');
  bars.forEach((b, j) => { b.className = j < INTRO.i ? 'done' : ''; b.style.removeProperty('--ms'); });
  bars[INTRO.i].style.setProperty('--ms', SCENES[INTRO.i] + 'ms'); void bars[INTRO.i].offsetWidth; bars[INTRO.i].className = 'run';
  sc.forEach((s, j) => s.classList.toggle('on', j === INTRO.i));
  if (INTRO.i === 0) { let n = 0; const el = $('lang'); const step = () => { if (!INTRO || INTRO.i !== 0) return; const [w, l] = LANGS[n % LANGS.length]; el.innerHTML = `<b dir="auto">${esc(w)}</b><small>${esc(l)}</small>`; n++; if (n < LANGS.length) INTRO.timers.push(setTimeout(step, (SCENES[0] - 600) / LANGS.length)); }; INTRO.timers.push(setTimeout(step, 600)); }
  if (INTRO.i === 3) countUp();
  INTRO.timers.push(setTimeout(nextScene, SCENES[INTRO.i]));
}
function fillIntro() {
  const g = $('adNums'); if (!g) return;
  if (!D) { g.innerHTML = `<div class="ad-card" style="--d:.1s;grid-column:1/-1"><b>${esc(LOGIN && LOGIN.msg ? LOGIN.msg : 'Sign in after this')}</b><span>to see today's figures</span></div>`; return; }
  const t = D.t, m = +D.today.slice(5, 7), mon = cashIn(inMonth(D.rc, D.today.slice(0, 7)));
  g.innerHTML = [[D.all.length, 'students', 0], [t.paid, 'fee cleared this session', 1], [mon.total, MN[m] + ' so far', 1], [pct(t.paid, t.paid + t.dueNow), '% collected of fee due', 2]]
    .map(([v, l, kind], i) => `<div class="ad-card" style="--d:${.15 + i * .2}s"><b data-v="${v}" data-k="${kind}">0</b><span>${esc(l)}</span></div>`).join('');
  if (INTRO && INTRO.i === 3) countUp();
}
function countUp() {
  document.querySelectorAll('#adNums b[data-v]').forEach(b => { const v = +b.dataset.v, kind = +b.dataset.k, t0 = performance.now();
    const f = now => { const p = Math.min(1, (now - t0) / 1600), e = 1 - Math.pow(1 - p, 3), x = v * e; b.textContent = kind === 1 ? rsS(x) : kind === 2 ? x.toFixed(1) + '%' : Math.round(x); if (p < 1 && INTRO) requestAnimationFrame(f); };
    requestAnimationFrame(f); });
}
function endIntro() { if (!INTRO) return; INTRO.timers.forEach(clearTimeout); INTRO = null; const ad = $('ad'); if (ad) ad.remove(); if (D || LOGIN) render(); else $('app').innerHTML = '<div class="load"><i></i>Opening…</div>'; }

// ================================================================ Call diary: when each parent was called, who spoke, what was discussed
const OUTS = ['Talked to parent', 'Promised to pay', 'Will visit school', 'Call back later', 'Already paid', 'Not answered', 'Switched off', 'Wrong number'];
const NEED_DATE = ['Promised to pay', 'Call back later', 'Will visit school'];
const NOT_REACHED = ['Not answered', 'Switched off', 'Wrong number'];
const PHRASES = ['Will pay this week', 'Will pay next month', 'Salary not received yet', 'Asked for a discount', 'Will come to school', 'Transport problem', 'Happy with the school', 'Complaint about studies', 'Will pay in parts'];
const hm = ms => ms ? new Date(ms).toLocaleTimeString('en-IN', { hour: '2-digit', minute: '2-digit', hour12: true }) : '';
const stuName = sid => { const x = D.all.find(z => z.sid === sid); if (x) return { name: x.name, cls: x.cls }; const s = SC && SC.stu && SC.stu[sid]; return s ? { name: s.name, cls: s.cls } : { name: '', cls: '' }; };
function callNow(sid) { S.callAt = { sid, at: Date.now() }; setTimeout(() => noteCall(sid), 700); }
function noteCall(sid) {
  if (S.open !== sid || S.sheet !== 'call') { S.cw = 'Father'; S.cO = 'Talked to parent'; S.cn = ''; S.cD = addDays(D.today, 3); S.cA = ''; }
  S.open = sid; sheet('call');
}
function addPhrase(i) { const p = PHRASES[i]; S.cn = (S.cn ? S.cn.replace(/[\s.]*$/, '') + '. ' : '') + p; render(); refocus('cN'); }
function callSheet() {
  const x = D.all.find(z => z.sid === S.open), s = x || (SC && SC.stu && SC.stu[S.open]); if (!s) return '';
  const at = S.callAt && S.callAt.sid === S.open ? S.callAt.at : 0, calls = x ? x.calls : D.calls.filter(c => (c.sids || []).includes(S.open));
  return `<h2>Call · ${esc(s.name)}</h2><p class="mut" style="margin:-4px 0 12px">${esc(s.cls)} · ${esc(s.father || '')}${x ? ' · due ' + rs(x.dueNow) : ''}${at ? ' · call started ' + hm(at) : ' · time is saved automatically'}</p>
    <label class="lbl">SPOKE WITH</label><div class="wch">${['Father', 'Mother', 'Guardian', 'Student', 'Other'].map(w => `<button class="chip${S.cw === w ? ' on' : ''}" onclick="S.cw='${w}';render()">${w}</button>`).join('')}</div>
    <label class="lbl">WHAT HAPPENED</label><div class="inp"><select id="cO" onchange="S.cO=this.value;render()">${OUTS.map(o => `<option ${S.cO === o ? 'selected' : ''}>${esc(o)}</option>`).join('')}</select></div>
    ${NEED_DATE.includes(S.cO) ? `<div style="display:grid;grid-template-columns:1fr 1fr;gap:8px"><div><label class="lbl">DATE THEY GAVE</label><div class="inp"><input id="cD" type="date" min="${D.today}" value="${esc(S.cD)}" onchange="S.cD=this.value"></div></div>
      <div><label class="lbl">AMOUNT (₹)</label><div class="inp"><input id="cA" inputmode="decimal" value="${esc(S.cA)}" placeholder="${S.cO === 'Promised to pay' && x ? Math.round(x.dueNow) : 'optional'}" oninput="S.cA=this.value"></div></div></div>` : ''}
    <label class="lbl">WHAT WAS DISCUSSED${NOT_REACHED.includes(S.cO) ? ' (optional)' : ''}</label><div class="inp"><textarea id="cN" maxlength="500" placeholder="e.g. Father said salary comes on the 10th, will pay October and November together." oninput="S.cn=this.value">${esc(S.cn)}</textarea></div>
    <div class="phr">${PHRASES.map((p, i) => `<button onclick="addPhrase(${i})">＋ ${esc(p)}</button>`).join('')}</div>
    <button class="cta" onclick="saveCall()" ${BUSY ? 'disabled' : ''}>Save call</button>
    ${calls.length ? `<label class="lbl" style="margin-top:14px">EARLIER CALLS</label>${diaryList(calls.slice(0, 5))}` : ''}`;
}
async function saveCall() {
  if (BUSY) return;
  const body = { sid: S.open, outcome: S.cO, with: S.cw, note: S.cn || '', promise: NEED_DATE.includes(S.cO) ? S.cD : '', amount: NEED_DATE.includes(S.cO) ? S.cA : '', startedAt: S.callAt && S.callAt.sid === S.open ? S.callAt.at : 0 };
  BUSY = true;
  try { const d = await api('feedash_api.php?a=callNote', body); D.calls.unshift(d.call); S.sheet = null; S.callAt = null; S.cn = ''; calc(); toast('Call saved with what was discussed.'); } catch (e) { toast(e.message); }
  BUSY = false; render();
}
function diaryList(calls) {
  return `<div class="dl">${calls.map(c => {
    const kids = (c.sids || [c.sid]).filter(Boolean), first = kids.includes(c.sid) ? c.sid : kids[0], nm = stuName(first), more = kids.length - 1;
    const tone = NOT_REACHED.includes(c.outcome) ? 'bad' : ['Promised to pay', 'Already paid', 'Talked to parent'].includes(c.outcome) ? 'ok' : '';
    const auto = c.initiated || (!c.note && c.autoRemark);
    return `<div class="de"><div class="h"><span><b>${dmy(c.date)}</b> · ${hm(c.startedAt || c.at)}</span><span>by ${esc(c.by || '')}</span></div>
      <div class="s" onclick="openStu('${esc(first)}')">${esc(nm.name || 'Student')} <small>${esc(nm.cls)}${more > 0 ? ' · +' + more + ' sibling' + (more > 1 ? 's' : '') : ''}${c.with ? ' · spoke with ' + esc(c.with) : ''}</small></div>
      <span class="o ${tone}">${esc(c.initiated ? 'Call tried — result not saved' : c.outcome)}</span>${c.promise ? `<span class="o">Date given ${dmy(c.promise)}</span>` : ''}${c.amount ? `<span class="o">${rs(c.amount / 100)}</span>` : ''}
      ${c.note && c.note !== c.autoRemark ? `<q>${esc(c.note)}</q>` : auto ? `<q class="auto">${esc(c.autoRemark || c.note || '')}</q>` : '<q class="auto">Nothing written about this call.</q>'}</div>`; }).join('')}</div>`;
}
function diaryCalls() {
  const R = S.df || '7', from = R === 'today' ? D.today : R === 'all' ? '' : addDays(D.today, -(+R - 1));
  const set = (S.tag !== null || S.cls) ? new Set(D.kids.map(x => x.sid)) : null;
  return D.calls.filter(c => (!from || c.date >= from) && (!S.do || (S.do === 'none' ? !c.note || c.note === c.autoRemark : S.do === 'reached' ? !NOT_REACHED.includes(c.outcome) && !c.initiated : c.outcome === S.do)) && (!set || (c.sids || [c.sid]).some(z => set.has(z))));
}
function diaryBody() {
  const L = diaryCalls(), by = {}; L.forEach(c => { by[c.by || '—'] = (by[c.by || '—'] || 0) + 1; });
  const talked = L.filter(c => !NOT_REACHED.includes(c.outcome) && !c.initiated).length, prom = L.filter(c => c.outcome === 'Promised to pay');
  const rng = [['today', 'Today'], ['7', '7 days'], ['30', '30 days'], ['all', 'All']];
  return `<div class="chips" style="margin:12px -14px">${rng.map(([v, l]) => `<button class="chip${(S.df || '7') === v ? ' on' : ''}" onclick="S.df='${v}';render()">${l}</button>`).join('')}</div>
    <div class="kp">${k('Calls', String(L.length), Object.entries(by).map(([a, b]) => esc(a.replace(/\s*\(.*\)/, '')) + ' ' + b).slice(0, 3).join(' · ') || 'none in this time')}${k('Spoke to parents', String(talked), pct(talked, L.length) + '% of calls', 'ok')}
      ${k('Promised to pay', String(prom.length), rsS(sum(prom, c => (c.amount || 0) / 100)) + ' promised', 'ac')}${k('Not reached', String(L.filter(c => NOT_REACHED.includes(c.outcome)).length), 'not answered / switched off / wrong number', 'bad')}</div>
    <select class="selx" style="margin-top:12px" onchange="S.do=this.value;render()"><option value="">Every result</option><option value="reached" ${S.do === 'reached' ? 'selected' : ''}>Only where we spoke</option><option value="none" ${S.do === 'none' ? 'selected' : ''}>Nothing written yet</option>${OUTS.map(o => `<option ${S.do === o ? 'selected' : ''}>${esc(o)}</option>`).join('')}</select>
    ${card(`Call diary · ${L.length}`, L.length ? diaryList(L.slice(0, 150)) + (L.length > 150 ? `<p class="note">Showing 150 of ${L.length}. Reports → Call diary has all of them.</p>` : '') : '<p class="empty">No call in this time. Tap a student → Call; when you come back the note form is open.</p>')}`;
}

// ================================================================ School tab: classes, attendance, results, syllabus, teachers, exams
let SC = null, SCL = false;
async function loadSchool() {
  if (SC || SCL) return; SCL = true;
  try { SC = await api('feedash_api.php?a=school'); prepSchool(); } catch (e) { SC = { err: e.message }; }
  SCL = false; render();
}
function needSchool() {
  if (!SC) { loadSchool(); return card('School', '<p class="empty">Loading attendance, results and teachers…</p>'); }
  if (SC.err) return card('School', `<p class="empty">${esc(SC.err)}</p><button class="cta" onclick="SC=null;render()">Try again</button>`);
  return '';
}
const grade = p => p >= 91 ? 'A1' : p >= 81 ? 'A2' : p >= 71 ? 'B1' : p >= 61 ? 'B2' : p >= 51 ? 'C1' : p >= 41 ? 'C2' : p >= 33 ? 'D' : 'E';
const exOrder = e => /final|annual/i.test(e) ? 9 : /term-2|half|ut2|unit test 2/i.test(e) ? 5 : /pre-board 2/i.test(e) ? 7 : /pre-board/i.test(e) ? 6 : 1;
const exSort = (a, b) => b.ses.localeCompare(a.ses) || exOrder(b.exam) - exOrder(a.exam) || b.exam.localeCompare(a.exam);
const tone = p => p == null ? '' : p >= 90 ? 'ok' : p >= 75 ? '' : 'bad';
const pc = p => p == null ? '—' : p + '%';
const ratio = (a, b) => b ? Math.round(a * 1000 / b) / 10 : null;
function prepSchool() {
  const X = SC; X.byCls = {}; X.stu = {}; X.ct = {};
  X.students.forEach(s => { (X.byCls[s.cls] = X.byCls[s.cls] || []).push(s); X.stu[s.id] = s; });
  Object.values(X.byCls).forEach(L => L.sort((a, b) => a.name.localeCompare(b.name)));
  X.classes.forEach(c => { X.ct[c.c] = c.ct; });
  X.cls = CLASSES.filter(c => X.byCls[c] || X.classes.some(z => z.c === c));
  X.dates = Object.keys(X.att).sort(); X.sdates = Object.keys(X.staffAtt).sort();
  const R = X.old.map(r => ({ ...r, src: 'old' }));
  Object.entries(X.marks).forEach(([ex, byK]) => {
    const e = X.exams.find(z => z.id === ex), per = {};
    Object.entries(byK).forEach(([key, rec]) => { const i = key.indexOf('|'), c = key.slice(0, i), sb = key.slice(i + 1);
      Object.entries(rec.m || {}).forEach(([sid, v]) => { const o = per[sid] = per[sid] || { sid, cls: c, got: 0, max: 0, subs: {} };
        o.subs[sb] = { Marks: v === 'AB' ? 'Absent' : v + '/' + rec.max }; if (v !== 'AB' && v !== '' && v != null) { o.got += +v; o.max += +rec.max; } }); });
    const L = Object.values(per).map(o => { const p = o.max ? Math.round(o.got / o.max * 10000) / 100 : null;
      return { sid: o.sid, name: (X.stu[o.sid] || {}).name || '', ses: D.ses, exam: e ? e.name : ex, cls: o.cls, total: o.max ? o.got + '/' + o.max : '', pct: p, grade: p == null ? '' : grade(p), rank: '', att: '', subs: o.subs, src: 'new' }; });
    const byC = {}; L.forEach(r => (byC[r.cls] = byC[r.cls] || []).push(r));
    Object.values(byC).forEach(G => G.filter(r => r.pct != null).sort((a, b) => b.pct - a.pct).forEach((r, i) => { r.rank = String(i + 1); }));
    R.push(...L);
  });
  R.forEach(r => { if (!r.name && X.stu[r.sid]) r.name = X.stu[r.sid].name; });
  X.res = R; X.resBy = {}; R.forEach(r => (X.resBy[r.sid] = X.resBy[r.sid] || []).push(r));
  Object.values(X.resBy).forEach(L => L.sort(exSort));
  const ex = {}; R.forEach(r => { ex[r.ses + '|' + r.exam] = { ses: r.ses, exam: r.exam }; });
  X.exs = Object.values(ex).sort(exSort).map(o => o.ses + '|' + o.exam);
}
const monthEnd = ym => ym + '-31';
function clsAtt(c, from, to) {
  let days = 0, pres = 0, tot = 0; const n = (SC.byCls[c] || []).length;
  SC.dates.forEach(d => { if (d < from || d > to) return; const a = SC.att[d][c]; if (!a) return; days++; tot += n; pres += n - a.length; });
  return { days, pres, tot, pct: ratio(pres, tot) };
}
function stuAtt(s, from, to) {
  let days = 0, ab = 0; const absent = [];
  SC.dates.forEach(d => { if (d < from || d > to) return; const a = SC.att[d][s.cls]; if (!a) return; days++; if (a.includes(s.id)) { ab++; absent.push(d); } });
  return { days, ab, pres: days - ab, pct: ratio(days - ab, days), absent };
}
const lastRes = sid => (SC.resBy[sid] || []).find(r => r.pct != null) || null;
const oldAtt = r => { const m = /^(\d+)\s*\/\s*(\d+)$/.exec(r && r.att || ''); return m && +m[2] > 0 ? { p: +m[1], t: +m[2], pct: ratio(+m[1], +m[2]) } : null; };
const tName = t => (SC.teachers[t] || {}).name || t || '';
const sesMonths = () => { const out = []; let d = SC.start.slice(0, 7); const end = SC.today.slice(0, 7); while (d <= end && out.length < 13) { out.push(d); const y = +d.slice(0, 4), m = +d.slice(5, 7); d = m === 12 ? (y + 1) + '-01' : y + '-' + String(m + 1).padStart(2, '0'); } return out; };
const SV = [['classes', 'Classes'], ['att', 'Attendance'], ['res', 'Results'], ['syl', 'Syllabus'], ['teach', 'Teachers & staff'], ['exams', 'Exams']];
function schoolBody() {
  const nav = `<div class="chips" style="margin:0 -14px 12px">${SV.map(([v, l]) => `<button class="chip${(S.sv || 'classes') === v ? ' on' : ''}" onclick="S.sv='${v}';render()">${l}</button>`).join('')}</div>`;
  const w = needSchool(); if (w) return nav + w;
  return nav + ({ classes: schClasses, att: schAtt, res: schRes, syl: schSyl, teach: schTeach, exams: schExams }[S.sv || 'classes'])();
}
function clsRow(c) {
  const L = SC.byCls[c] || [], n = L.length, t = SC.att[SC.today] && SC.att[SC.today][c], ym = SC.today.slice(0, 7), m = clsAtt(c, ym + '-01', monthEnd(ym));
  const kids = D.all.filter(x => x.cls === c), lr = L.map(s => lastRes(s.id)).filter(Boolean);
  return { c, n, ct: tName(SC.ct[c]), today: t ? ratio(n - t.length, n) : null, todayAb: t ? t.length : null, mon: m.pct, days: m.days,
    avg: lr.length ? Math.round(sum(lr, r => r.pct) * 10 / lr.length) / 10 : null, due: sum(kids, x => x.dueNow), zero: kids.filter(x => x.zero && x.dueNow > 0).length };
}
function schClasses() {
  const R = SC.cls.map(clsRow), marked = R.filter(r => r.today != null), n = sum(R, r => r.n);
  const tp = sum(marked, r => r.n - r.todayAb), tt = sum(marked, r => r.n), sa = SC.staffAtt[SC.today] || [];
  return `<div class="kp">${k('Students', String(n), R.length + ' classes')}${k('Today present', marked.length ? pc(ratio(tp, tt)) : '—', marked.length ? `${tp} of ${tt} · ${marked.length}/${R.filter(r => r.n).length} classes marked` : 'attendance not marked yet today', marked.length ? tone(ratio(tp, tt)) : 'warn', `S.sv='att';render()`)}
      ${k('Teachers', String(Object.keys(SC.teachers).length), SC.staffAtt[SC.today] ? sa.length + ' absent today' : 'staff attendance not marked today', sa.length ? 'warn' : '', `setTab(4)`)}${k('Fee due now', rsS(sum(R, r => r.due)), sum(R, r => r.zero) + ' paid ₹0 this session', 'bad', "goTo(\'dues\')")}</div>
    <div class="cgrid" style="margin-top:12px">${R.map(r => `<button class="cc" onclick="openRoom('${esc(r.c)}')"><i></i><b>${esc(r.c)}</b><small><em>${r.n}</em> students · ${esc(r.ct || 'no class teacher')}</small>
      <small>Today: ${r.today == null ? 'not marked' : `<em class="${r.today < 75 ? 'red' : ''}">${pc(r.today)}</em> (${r.todayAb} absent)`}</small><small>This month: <em>${pc(r.mon)}</em>${r.days ? ' · ' + r.days + ' days' : ''}</small>
      <small>Last result avg: <em>${pc(r.avg)}</em></small><small>Fee due: <em class="red">${rsS(r.due)}</em></small></button>`).join('')}</div>
    <p class="note">Tap a class for every student in it: attendance, last result and fee — in one place.</p>`;
}
function clsSheet() {
  const c = S.scls, r = clsRow(c), L = SC.byCls[c] || [], ym = SC.today.slice(0, 7);
  const subs = SC.alloc.filter(a => a.c === c && a.n > 0);
  const rows = L.map(s => { const m = stuAtt(s, ym + '-01', monthEnd(ym)), a = stuAtt(s, SC.start, SC.today), lr = lastRes(s.id), f = D.all.find(x => x.sid === s.id); return { s, m, a, lr, f }; })
    .sort((x, y) => S.cs === 'att' ? (x.a.pct ?? 101) - (y.a.pct ?? 101) : S.cs === 'res' ? (y.lr ? y.lr.pct : -1) - (x.lr ? x.lr.pct : -1) : S.cs === 'due' ? (y.f ? y.f.dueNow : 0) - (x.f ? x.f.dueNow : 0) : x.s.name.localeCompare(y.s.name));
  return `<h2>Class ${esc(c)}</h2><p class="mut" style="margin:-4px 0 10px">Class teacher: ${esc(r.ct || '—')} · ${r.n} students</p>
    <div class="kp">${k('Today', pc(r.today), r.today == null ? 'not marked' : r.todayAb + ' absent', tone(r.today))}${k('This month', pc(r.mon), r.days + ' days marked', tone(r.mon))}${k('Last result avg', pc(r.avg), 'latest exam of each child')}${k('Fee due now', rsS(r.due), r.zero + ' paid ₹0', 'bad')}</div>
    ${subs.length ? `<label class="lbl" style="margin-top:14px">SUBJECTS AND TEACHERS</label><p class="note" style="margin-top:0">${subs.map(a => `${esc(a.s)} — ${esc(tName(a.t))}${a.n ? ' (' + a.n + '/week)' : ''}`).join('<br>')}</p>` : ''}
    <label class="lbl" style="margin-top:14px">STUDENTS</label><select class="selx" onchange="S.cs=this.value;render()">${[['name', 'Sort by name'], ['att', 'Lowest attendance first'], ['res', 'Best result first'], ['due', 'Highest fee due first']].map(([v, l]) => `<option value="${v}" ${(S.cs || 'name') === v ? 'selected' : ''}>${l}</option>`).join('')}</select>
    <div class="tw"><table class="tbl"><tr><th>Student</th><th>Att. month</th><th>Att. session</th><th>Last result</th><th>Fee due</th></tr>${rows.map(o => `<tr onclick="openStu('${esc(o.s.id)}')" style="cursor:pointer"><td>${esc(o.s.name)}</td><td class="${o.m.pct != null && o.m.pct < 75 ? 'red' : ''}">${pc(o.m.pct)}</td><td class="${o.a.pct != null && o.a.pct < 75 ? 'red' : ''}">${pc(o.a.pct)}</td><td>${o.lr ? pc(o.lr.pct) + ' ' + esc(o.lr.grade) : '—'}</td><td class="red">${o.f ? rs(o.f.dueNow) : '—'}</td></tr>`).join('')}</table></div>`;
}
function schAtt() {
  const months = sesMonths(), ym = S.am && months.includes(S.am) ? S.am : months[months.length - 1] || SC.today.slice(0, 7), from = ym + '-01', to = monthEnd(ym);
  const R = SC.cls.filter(c => (SC.byCls[c] || []).length).map(c => ({ c, n: SC.byCls[c].length, ...clsAtt(c, from, to), ct: tName(SC.ct[c]) }));
  const days = SC.dates.filter(d => d >= from && d <= to), tot = sum(R, r => r.tot), pres = sum(R, r => r.pres);
  const tdy = SC.att[SC.today] || {}, tR = R.filter(r => tdy[r.c]), tP = sum(tR, r => r.n - tdy[r.c].length), tT = sum(tR, r => r.n);
  const low = SC.students.map(s => ({ s, a: stuAtt(s, from, to) })).filter(o => o.a.days >= 3 && o.a.pct < 75).sort((a, b) => a.a.pct - b.a.pct);
  const three = []; SC.cls.forEach(c => { const dd = SC.dates.filter(d => d <= SC.today && SC.att[d][c]).slice(-3); if (dd.length < 3) return; (SC.byCls[c] || []).forEach(s => { if (dd.every(d => SC.att[d][c].includes(s.id))) three.push(s); }); });
  if (days.length) chart('attDay', { type: 'line', data: { labels: days.map(d => d.slice(8, 10)), datasets: [{ label: 'Present %', data: days.map(d => { let p = 0, t = 0; Object.entries(SC.att[d]).forEach(([c, a]) => { const n = (SC.byCls[c] || []).length; t += n; p += n - a.length; }); return ratio(p, t); }), borderColor: P.plum, backgroundColor: 'rgba(155,93,229,.12)', fill: true, tension: .35, pointRadius: 3 }] },
    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { min: 0, max: 100, ticks: { callback: v => v + '%' } }, x: { grid: { display: false } } } } });
  const RR = R.filter(r => r.days);
  if (RR.length) chart('attCls', { type: 'bar', data: { labels: RR.map(r => r.c), datasets: [{ label: 'Present %', data: RR.map(r => r.pct), backgroundColor: RR.map(r => r.pct < 75 ? P.pink : r.pct < 90 ? P.amber : P.green), borderRadius: 6 }] },
    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { min: 0, max: 100, ticks: { callback: v => v + '%' } }, x: { grid: { display: false } } } } });
  // last session from the old ERP report cards (Final): days present / working days
  const os = [...new Set(SC.old.filter(r => /final/i.test(r.exam) && oldAtt(r)).map(r => r.ses))].sort().pop();
  const O = os ? SC.old.filter(r => r.ses === os && /final/i.test(r.exam)).map(r => ({ r, a: oldAtt(r) })).filter(o => o.a) : [];
  const oc = {}; O.forEach(o => { const z = oc[o.r.cls] = oc[o.r.cls] || { p: 0, t: 0, n: 0 }; z.p += o.a.p; z.t += o.a.t; z.n++; });
  const ocR = CLASSES.filter(c => oc[c]).map(c => [c, oc[c].n, ratio(oc[c].p, oc[c].t)]), oLow = O.filter(o => o.a.pct < 75).sort((a, b) => a.a.pct - b.a.pct);
  return `<div class="chips months" style="margin:0 -14px 12px">${months.map(m => `<button class="chip${m === ym ? ' on' : ''}" onclick="S.am='${m}';render()">${M3(+m.slice(5, 7))}<small>${m.slice(0, 4)}</small></button>`).join('')}</div>
    <div class="kp"><div class="k big"><span>Present today</span><b>${tR.length ? pc(ratio(tP, tT)) : '—'}</b><small>${tR.length ? `${tP} of ${tT} students · ${tR.length} of ${R.length} classes marked` : 'No class has marked attendance today yet'}</small></div>
      ${k(MN[+ym.slice(5, 7)] + ' average', pc(ratio(pres, tot)), days.length + ' school days marked', tone(ratio(pres, tot)))}${k('Below 75%', String(low.length), 'students this month', low.length ? 'bad' : 'ok')}</div>
    ${days.length ? card('Daily attendance · ' + MN[+ym.slice(5, 7)], canvas('attDay', 190)) + card('Class average · ' + MN[+ym.slice(5, 7)], canvas('attCls', 210)) : card('Attendance · ' + MN[+ym.slice(5, 7)], `<p class="empty">No attendance marked in ${MN[+ym.slice(5, 7)]} yet.</p><p class="note">Class teachers mark it in the school app → Attendance (one tap per absent child). It shows here the same minute. So far ${SC.dates.length} day${SC.dates.length === 1 ? '' : 's'} marked this session.</p>`)}
    ${card('Class-wise · ' + MN[+ym.slice(5, 7)], `<div class="tw"><table class="tbl"><tr><th>Class</th><th>Students</th><th>Days marked</th><th>Average</th><th>Today</th><th>Class teacher</th></tr>${R.map(r => `<tr onclick="openRoom('${esc(r.c)}')" style="cursor:pointer"><td>${esc(r.c)}</td><td>${r.n}</td><td>${r.days}</td><td class="${r.pct != null && r.pct < 75 ? 'red' : ''}">${pc(r.pct)}</td><td>${tdy[r.c] ? pc(ratio(r.n - tdy[r.c].length, r.n)) : '—'}</td><td>${esc(r.ct)}</td></tr>`).join('')}
      <tr class="tot"><td>School</td><td>${sum(R, r => r.n)}</td><td>${days.length}</td><td>${pc(ratio(pres, tot))}</td><td>${tR.length ? pc(ratio(tP, tT)) : '—'}</td><td></td></tr></table></div>`)}
    ${three.length ? card('Absent 3 days in a row · ' + three.length, `<div class="tw"><table class="tbl"><tr><th>Student</th><th>Class</th><th>Father</th><th>Mobile</th></tr>${three.map(s => `<tr onclick="openStu('${esc(s.id)}')" style="cursor:pointer"><td>${esc(s.name)}</td><td>${esc(s.cls)}</td><td>${esc(s.father)}</td><td>${esc(s.phone)}</td></tr>`).join('')}</table></div>`) : ''}
    ${low.length ? card('Below 75% in ' + MN[+ym.slice(5, 7)] + ' · ' + low.length, `<div class="tw"><table class="tbl"><tr><th>Student</th><th>Class</th><th>Present</th><th>Absent</th><th>%</th></tr>${low.map(o => `<tr onclick="openStu('${esc(o.s.id)}')" style="cursor:pointer"><td>${esc(o.s.name)}</td><td>${esc(o.s.cls)}</td><td>${o.a.pres}</td><td>${o.a.ab}</td><td class="red">${pc(o.a.pct)}</td></tr>`).join('')}</table></div>`) : ''}
    ${ocR.length ? card('Last session ' + longSes(os) + ' · from old ERP report cards', `<div class="tw"><table class="tbl"><tr><th>Class (then)</th><th>Students</th><th>Attendance</th></tr>${ocR.map(r => `<tr><td>${esc(r[0])}</td><td>${r[1]}</td><td class="${r[2] < 75 ? 'red' : ''}">${pc(r[2])}</td></tr>`).join('')}<tr class="tot"><td>School</td><td>${O.length}</td><td>${pc(ratio(sum(O, o => o.a.p), sum(O, o => o.a.t)))}</td></tr></table></div>
      ${oLow.length ? `<p class="note">${oLow.length} children were below 75% last session${oLow.length ? ': ' + oLow.slice(0, 12).map(o => esc(o.r.name) + ' ' + o.a.pct + '%').join(', ') + (oLow.length > 12 ? '…' : '') : ''}.</p>` : ''}`) : ''}`;
}
function resPick() {
  const key = S.rx && SC.exs.includes(S.rx) ? S.rx : (SC.exs.find(x => /final|annual/i.test(x)) || SC.exs[0] || '');
  const [ses, exam] = key.split('|'); return { key, ses, exam, rows: SC.res.filter(r => r.ses === ses && r.exam === exam && r.pct != null) };
}
function schRes() {
  if (!SC.exs.length) return card('Results', '<p class="empty">No marks yet.</p><p class="note">Teachers enter marks in the school app → Marks Entry; the old ERP result sheets can be copied in Office → Old ERP Archive.</p>');
  const { key, ses, exam, rows } = resPick(), byC = {}; rows.forEach(r => (byC[r.cls] = byC[r.cls] || []).push(r));
  const C = CLASSES.filter(c => byC[c]).concat(Object.keys(byC).filter(c => !CLASSES.includes(c))).map(c => { const G = byC[c].slice().sort((a, b) => b.pct - a.pct); return { c, n: G.length, avg: Math.round(sum(G, r => r.pct) * 10 / G.length) / 10, hi: G[0], pass: ratio(G.filter(r => r.pct >= 33).length, G.length), top: G.slice(0, 3) }; });
  const avg = rows.length ? Math.round(sum(rows, r => r.pct) * 10 / rows.length) / 10 : null, G = {}; rows.forEach(r => { const g = r.grade || grade(r.pct); G[g] = (G[g] || 0) + 1; });
  const gk = ['A1', 'A2', 'B1', 'B2', 'C1', 'C2', 'D', 'E'].filter(g => G[g]).concat(Object.keys(G).filter(g => !['A1', 'A2', 'B1', 'B2', 'C1', 'C2', 'D', 'E'].includes(g)));
  const weak = rows.filter(r => r.pct < 40).sort((a, b) => a.pct - b.pct);
  // school average of the Final exam, session by session
  const fin = [...new Set(SC.res.filter(r => /final|annual/i.test(r.exam) && r.pct != null).map(r => r.ses))].sort().map(s2 => { const X = SC.res.filter(r => r.ses === s2 && /final|annual/i.test(r.exam) && r.pct != null); return [s2, X.length, Math.round(sum(X, r => r.pct) * 10 / X.length) / 10, ratio(X.filter(r => r.pct >= 33).length, X.length)]; });
  if (C.length) chart('resCls', { type: 'bar', data: { labels: C.map(r => r.c), datasets: [{ label: 'Class average %', data: C.map(r => r.avg), backgroundColor: P.plum, borderRadius: 6 }] }, options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { min: 0, max: 100, ticks: { callback: v => v + '%' } }, x: { grid: { display: false } } } } });
  if (gk.length) chart('resGr', { type: 'doughnut', data: { labels: gk, datasets: [{ data: gk.map(g => G[g]), backgroundColor: [P.green, '#14B8A6', P.blue, P.indigo, P.plum, P.amber, P.orange, P.pink], borderWidth: 0 }] }, options: { responsive: true, maintainAspectRatio: false, cutout: '62%', plugins: { legend: { position: 'right' } } } });
  return `<select class="selx" onchange="S.rx=this.value;render()">${SC.exs.map(x => `<option value="${esc(x)}" ${x === key ? 'selected' : ''}>${esc(longSes(x.split('|')[0]))} · ${esc(x.split('|')[1])}</option>`).join('')}</select>
    <div class="kp"><div class="k big"><span>School average · ${esc(exam)}</span><b>${pc(avg)}</b><small>${rows.length} students · ${esc(longSes(ses))}</small></div>
      ${k('Passed (33%+)', pc(ratio(rows.filter(r => r.pct >= 33).length, rows.length)), rows.filter(r => r.pct < 33).length + ' below 33%', 'ok')}${k('A1 / A2', String((G.A1 || 0) + (G.A2 || 0)), '81% and above', 'ac')}</div>
    ${card('Class average', canvas('resCls', 210))}${card('Grades', canvas('resGr', 190))}
    ${card('Class-wise result', `<div class="tw"><table class="tbl"><tr><th>Class</th><th>Students</th><th>Average</th><th>Pass %</th><th>Topper</th></tr>${C.map(r => `<tr><td>${esc(r.c)}</td><td>${r.n}</td><td>${pc(r.avg)}</td><td>${pc(r.pass)}</td><td onclick="openStu('${esc(r.hi.sid)}')" style="cursor:pointer">${esc(r.hi.name)} · ${pc(r.hi.pct)}</td></tr>`).join('')}</table></div>`)}
    ${card('Toppers · top 3 of each class', `<div class="tw"><table class="tbl"><tr><th>Class</th><th>Rank</th><th>Student</th><th>%</th><th>Grade</th></tr>${C.map(r => r.top.map((t, i) => `<tr onclick="openStu('${esc(t.sid)}')" style="cursor:pointer"><td>${i ? '' : esc(r.c)}</td><td>${['🥇', '🥈', '🥉'][i]}</td><td>${esc(t.name)}</td><td>${pc(t.pct)}</td><td>${esc(t.grade)}</td></tr>`).join('')).join('')}</table></div>`)}
    ${weak.length ? card('Need attention · below 40% · ' + weak.length, `<div class="tw"><table class="tbl"><tr><th>Student</th><th>Class</th><th>%</th><th>Attendance</th></tr>${weak.map(r => `<tr onclick="openStu('${esc(r.sid)}')" style="cursor:pointer"><td>${esc(r.name)}</td><td>${esc(r.cls)}</td><td class="red">${pc(r.pct)}</td><td>${esc(r.att || '')}</td></tr>`).join('')}</table></div>`) : ''}
    ${fin.length > 1 ? card('Year by year · Final exam', `<div class="tw"><table class="tbl"><tr><th>Session</th><th>Students</th><th>Average</th><th>Pass %</th></tr>${fin.map(f => `<tr><td>${esc(longSes(f[0]))}</td><td>${f[1]}</td><td>${pc(f[2])}</td><td>${pc(f[3])}</td></tr>`).join('')}</table></div>`) : ''}
    <p class="note">Old sessions come from the old ERP result sheets; this session from the marks teachers enter. Tap any student for the full report card.</p>`;
}
function schSyl() {
  const keys = Object.keys(SC.syl), R = keys.map(key => { const i = key.indexOf('|'), c = key.slice(0, i), sb = key.slice(i + 1), y = SC.syl[key], done = y.chapters.filter(ch => ch.done), a = SC.alloc.find(z => z.c === c && z.s === sb);
    return { c, sb, done: done.length, total: y.chapters.length, last: done.map(ch => String(ch.done)).sort().pop() || '', lastN: (done.slice().sort((p, q) => String(q.done).localeCompare(String(p.done)))[0] || {}).n || '', t: a ? tName(a.t) : '', target: y.targetDate }; })
    .filter(r => r.total).sort((a, b) => CLASSES.indexOf(a.c) - CLASSES.indexOf(b.c) || a.sb.localeCompare(b.sb));
  const td = sum(R, r => r.done), tt = sum(R, r => r.total), ym = SC.today.slice(0, 7), mon = sum(keys, key => SC.syl[key].chapters.filter(ch => String(ch.done || '').slice(0, 7) === ym).length);
  const missing = SC.alloc.filter(a => a.n > 0 && !/Games|Library|Moral|Rhymes|Play|Craft|Activity|PT\b/.test(a.s) && !(SC.syl[a.c + '|' + a.s] || { chapters: [] }).chapters.length).length;
  return `<div class="kp"><div class="k big"><span>Syllabus completed</span><b>${pc(ratio(td, tt))}</b><small>${td} of ${tt} chapters · ${R.length} class-subjects</small></div>${k('This month', String(mon), 'chapters finished', 'ok')}${k('No chapter list yet', String(missing), 'class-subjects', missing ? 'warn' : '')}</div>
    ${card('Class and subject', R.length ? `<div class="sl">${R.map(r => { const p = ratio(r.done, r.total); return `<div><div class="row"><b>${esc(r.c)} · ${esc(r.sb)}</b><b>${p}%</b></div><div class="bar ${p < 30 ? 'bad' : p >= 80 ? 'ok' : ''}"><i style="width:${p}%"></i></div><div class="row"><span>${r.done}/${r.total} chapters${r.t ? ' · ' + esc(r.t) : ''}</span><span>${r.last ? 'last: ' + esc(r.lastN) + ' ' + dmy(r.last) : 'not started'}</span></div></div>`; }).join('')}</div>` : '<p class="empty">No chapter list yet.</p>')}
    <p class="note">Teachers add the chapter list and tick each chapter in the school app → Syllabus (or from the daily diary). ${missing ? missing + ' class-subjects still need their chapter list.' : ''}</p>`;
}
function schTeach() {
  const T = Object.entries(SC.teachers), ab = SC.staffAtt[SC.today], ym = SC.today.slice(0, 7), md = SC.sdates.filter(d => d.slice(0, 7) === ym);
  const R = T.map(([t, v]) => ({ t, ...v, ct: SC.classes.filter(c => c.ct === t).map(c => c.c), subs: SC.alloc.filter(a => a.t === t && a.n > 0), per: sum(SC.alloc.filter(a => a.t === t), a => a.n || 0), abM: md.filter(d => (SC.staffAtt[d] || []).includes(t)).length, abT: ab ? ab.includes(t) : null }))
    .sort((a, b) => (b.ct.length - a.ct.length) || a.name.localeCompare(b.name));
  const pend = SC.leaves.filter(l => l.status === 'Pending'), E = SC.employees, act = E.filter(e => !/left/i.test(e.status)), cat = {}; act.forEach(e => { cat[e.category || 'Other'] = (cat[e.category || 'Other'] || 0) + 1; });
  return `<div class="kp">${k('Teachers', String(T.length), SC.classes.filter(c => c.ct).length + ' class teachers')}${k('Absent today', ab ? String(ab.length) : '—', ab ? esc(ab.map(tName).join(', ')) || 'everyone present' : 'not marked today', ab && ab.length ? 'warn' : 'ok')}
      ${k('Leave requests', String(pend.length), 'waiting for the Principal', pend.length ? 'warn' : '')}${k('Staff (register)', String(act.length), esc(Object.entries(cat).map(([a, b]) => a + ' ' + b).slice(0, 3).join(' · ')))}</div>
    ${card('Teachers', `<div class="ppl">${R.map(r => { const ph = String(r.phone || '').replace(/\D/g, '').slice(-10);
      return `<div class="p"><div class="ava">${esc(r.name.replace(/^(Mr|Mrs|Ms|Dr)\.?\s+/i, '').split(/\s+/).map(w => w[0]).slice(0, 2).join('').toUpperCase())}</div><div class="t"><b>${esc(r.name)}${r.abT ? '<span class="tag brk">Absent today</span>' : ''}</b><small>${esc(r.role)}</small>
        <small>${r.ct.length ? 'Class teacher ' + esc(r.ct.join(', ')) + ' · ' : ''}${r.per} periods a week</small><small>${esc(r.subs.slice(0, 5).map(a => a.c + ' ' + a.s).join(', '))}${r.subs.length > 5 ? '…' : ''}</small>
        <small>Absent this month: ${r.abM} day${r.abM === 1 ? '' : 's'}</small></div><div class="r">${ph ? `<div class="acts"><a class="c" href="tel:+91${ph}" aria-label="Call">${ic('phone', 15)}</a></div>` : ''}</div></div>`; }).join('')}</div>`)}
    ${md.length ? card('Staff attendance · ' + MN[+ym.slice(5, 7)], `<div class="tw"><table class="tbl"><tr><th>Date</th><th>Absent</th></tr>${md.slice().reverse().map(d => `<tr><td>${dmy(d)}</td><td style="white-space:normal">${esc((SC.staffAtt[d] || []).map(tName).join(', ') || 'All present')}</td></tr>`).join('')}</table></div>`) : ''}
    ${SC.leaves.length ? card('Leave · ' + SC.leaves.length, `<div class="tw"><table class="tbl"><tr><th>Teacher</th><th>From</th><th>To</th><th>Status</th><th>Reason</th></tr>${SC.leaves.slice().sort((a, b) => b.from.localeCompare(a.from)).slice(0, 40).map(l => `<tr><td>${esc(tName(l.tid))}</td><td>${dmy(l.from)}</td><td>${dmy(l.to)}</td><td class="${l.status === 'Pending' ? 'red' : ''}">${esc(l.status)}</td><td>${esc(l.reason)}</td></tr>`).join('')}</table></div>`) : ''}
    ${E.length ? card('Staff register · ' + E.length, `<div class="tw"><table class="tbl"><tr><th>Name</th><th>Designation</th><th>Category</th><th>Mobile</th><th>Joined</th><th>Qualification</th><th>Status</th></tr>${E.slice().sort((a, b) => (/left/i.test(a.status) - /left/i.test(b.status)) || a.name.localeCompare(b.name)).map(e => `<tr><td>${esc(e.name)}</td><td>${esc(e.designation)}</td><td>${esc(e.category)}</td><td>${esc(e.mobile)}</td><td>${esc(e.doj)}</td><td>${esc(e.qualification)}</td><td class="${/left/i.test(e.status) ? 'red' : ''}">${esc(e.status)}</td></tr>`).join('')}</table></div>`) : ''}`;
}
function schExams() {
  const E = SC.exams.slice().sort((a, b) => String(a.date).localeCompare(String(b.date))), t = SC.today;
  const st = e => { const to = e.to || e.date; return t < e.date ? 'upcoming' : t <= to ? 'running' : 'done'; };
  const next = E.find(e => st(e) !== 'done');
  const entry = e => { const cls = e.classes && e.classes.length ? e.classes : SC.cls.filter(c => (SC.byCls[c] || []).length), M = SC.marks[e.id] || {};
    if (e.sheet && e.sheet.length) { let need = 0, have = 0; cls.forEach(c => e.sheet.forEach(p => p.subjects.forEach(sb => { need++; if (M[c + '|' + sb]) have++; }))); return { need, have }; }
    return { need: 0, have: Object.keys(M).length }; };
  return `<div class="kp">${next ? `<div class="k big"><span>${st(next) === 'running' ? 'Exam running' : 'Next exam'}</span><b>${esc(next.name)}</b><small>${dmy(next.date)}${next.to ? ' to ' + dmy(next.to) : ''}${st(next) === 'upcoming' ? ' · in ' + Math.round((new Date(next.date) - new Date(t)) / 864e5) + ' days' : ''}</small></div>` : ''}
      ${k('Exams this session', String(E.length), E.filter(e => st(e) === 'done').length + ' done')}${k('Results entered', String(Object.keys(SC.marks).length), 'exams with marks')}</div>
    ${card('Exam calendar', `<div class="tw"><table class="tbl"><tr><th>Exam</th><th>Dates</th><th>Classes</th><th>Status</th><th>Marks entered</th></tr>${E.map(e => { const m = entry(e), s2 = st(e); return `<tr><td>${esc(e.name)}</td><td>${dmy(e.date)}${e.to ? ' – ' + dmy(e.to) : ''}</td><td style="white-space:normal">${esc(e.classes && e.classes.length ? e.classes.join(', ') : 'All')}</td><td class="${s2 === 'running' ? 'grn' : ''}">${s2 === 'upcoming' ? 'Upcoming' : s2 === 'running' ? 'Running' : 'Done'}</td><td class="${s2 === 'done' && m.need && m.have < m.need ? 'red' : ''}">${m.need ? m.have + ' of ' + m.need : m.have ? m.have + ' papers' : '—'}</td></tr>`; }).join('')}</table></div>`)}
    ${next && next.sheet && next.sheet.length ? card('Date sheet · ' + next.name, `<div class="tw"><table class="tbl"><tr><th>Date</th><th>Paper</th><th>Max marks</th></tr>${next.sheet.map(p => `<tr><td>${dmy(p.date)}</td><td>${esc(p.title)}</td><td>${esc(p.max)}</td></tr>`).join('')}</table></div>${next.time ? `<p class="note">Time: ${esc(next.time)}${next.reportDate ? ' · report cards on ' + dmy(next.reportDate) : ''}</p>` : ''}`) : ''}
`;
}
function schoolToday() {
  if (!SC) { loadSchool(); return ''; } if (SC.err) return '';
  const R = SC.cls.filter(c => (SC.byCls[c] || []).length), tdy = SC.att[SC.today] || {}, M = R.filter(c => tdy[c]), n = sum(M, c => SC.byCls[c].length), p = n - sum(M, c => tdy[c].length);
  const ab = SC.staffAtt[SC.today], nx = SC.exams.slice().sort((a, b) => String(a.date).localeCompare(String(b.date))).find(e => (e.to || e.date) >= SC.today), { exam, rows } = resPick();
  const go = v => v === 'teach' ? 'setTab(4)' : `goTo('school','${v}')`;
  return card('School today', `<div class="stat"><button class="st ${M.length && ratio(p, n) < 75 ? 'bad' : ''}" onclick="${go('att')}"><b>${M.length ? pc(ratio(p, n)) : '—'}</b><span>${M.length ? `Students present · ${M.length}/${R.length} classes marked` : 'Attendance not marked yet'}</span></button>
    <button class="st ${ab && ab.length ? 'bad' : ''}" onclick="${go('teach')}"><b>${ab ? ab.length : '—'}</b><span>${ab ? 'Teachers absent' : 'Staff attendance not marked'}</span></button>
    <button class="st" onclick="${go('exams')}"><b style="font-size:13px">${nx ? esc(nx.name) : '—'}</b><span>${nx ? 'Next exam · ' + dmy(nx.date) : 'No exam coming'}</span></button>
    <button class="st ok" onclick="${go('res')}"><b>${rows.length ? pc(Math.round(sum(rows, r => r.pct) * 10 / rows.length) / 10) : '—'}</b><span>${rows.length ? 'School average · ' + esc(exam) : 'No result yet'}</span></button></div>`, `<small onclick="goTo('school','classes')" style="cursor:pointer">open School ›</small>`);
}
// ---- the School part of a student's full record: attendance and every report card
function stuSchool(sid) {
  if (!SC) { loadSchool(); return '<p class="note" style="margin-top:14px">Loading attendance and results…</p>'; }
  if (SC.err) return '';
  const s = SC.stu[sid]; let h = '';
  if (s) {
    const ym = SC.today.slice(0, 7), m = stuAtt(s, ym + '-01', monthEnd(ym)), a = stuAtt(s, SC.start, SC.today);
    h += `<label class="lbl" style="margin-top:14px">ATTENDANCE · ${esc(longSes(D.ses))}</label><div class="kp">${k('This month', pc(m.pct), m.days ? `${m.pres} of ${m.days} days` : 'not marked yet', tone(m.pct))}${k('This session', pc(a.pct), a.days ? `${a.pres} of ${a.days} days · absent ${a.ab}` : 'not marked yet', tone(a.pct))}</div>
      ${a.absent.length ? `<p class="note">Absent on: ${a.absent.slice(-15).reverse().map(dmy).join(', ')}${a.absent.length > 15 ? '…' : ''}</p>` : ''}`;
  }
  const R = SC.resBy[sid] || [];
  h += `<label class="lbl" style="margin-top:14px">REPORT CARDS · ${R.length}</label>`;
  if (!R.length) return h + '<p class="note">No marks on record yet.</p>';
  h += `<div class="tw"><table class="tbl"><tr><th>Session</th><th>Exam</th><th>Class</th><th>Total</th><th>%</th><th>Grade</th><th>Rank</th><th>Attendance</th></tr>${R.map(r => `<tr><td>${esc(longSes(r.ses))}</td><td>${esc(r.exam)}</td><td>${esc(r.cls)}</td><td>${esc(r.total)}</td><td>${pc(r.pct)}</td><td>${esc(r.grade)}</td><td>${esc(r.rank)}</td><td>${esc(r.att)}</td></tr>`).join('')}</table></div>`;
  h += R.slice(0, 6).map((r, i) => `<details class="rc" ${i ? '' : 'open'}><summary>${esc(longSes(r.ses))} · ${esc(r.exam)} <small>${pc(r.pct)} ${esc(r.grade)}</small></summary><div class="tw"><table class="tbl"><tr><th>Subject</th><th>Marks</th></tr>${Object.entries(r.subs || {}).map(([sb, cols]) => `<tr><td>${esc(sb)}</td><td style="white-space:normal">${Object.entries(cols).map(([c, v]) => `${esc(c.replace(/\s*Total/i, '').trim() || 'Total')}: <b>${esc(v)}</b>`).join(' · ')}</td></tr>`).join('')}</table></div></details>`).join('');
  return h;
}
function stuSheetLite(sid) {
  const s = SC && SC.stu && SC.stu[sid], r = SC && SC.resBy && (SC.resBy[sid] || [])[0], calls = D.calls.filter(c => (c.sids || []).includes(sid));
  return `<h2>${esc(s ? s.name : r ? r.name : 'Student')}</h2><p class="mut" style="margin:-6px 0 10px">${esc(s ? s.cls + (s.section ? '-' + s.section : '') : r ? 'Class ' + r.cls + ' in ' + longSes(r.ses) : '')}${s && s.father ? ' · ' + esc(s.father) : ''}</p>
    <p class="note">No fee card for ${esc(longSes(D.ses))}.</p>
    ${s ? `<label class="lbl" style="margin-top:14px;display:flex;align-items:center;justify-content:space-between">CALLS <button class="fchip add" onclick="noteCall('${esc(sid)}')">＋ Write a call</button></label>${calls.length ? diaryList(calls) : '<p class="note">No call yet.</p>'}` : ''}
    ${stuSchool(sid)}`;
}
// ---- Reports: Call diary, Attendance, Results
function repSchool(T, K) {
  const set = new Set(K.map(x => x.sid)), f = S.tag !== null || S.cls;
  if (T === 'calls') {
    const L = D.calls.filter(c => (!RF.from || c.date >= RF.from) && (!RF.to || c.date <= RF.to) && (!f || (c.sids || [c.sid]).some(z => set.has(z))));
    return { sumv: [['Calls', L.length], ['Spoke', L.filter(c => !NOT_REACHED.includes(c.outcome) && !c.initiated).length], ['Promised', L.filter(c => c.outcome === 'Promised to pay').length], ['Not reached', L.filter(c => NOT_REACHED.includes(c.outcome)).length]],
      body: repTable(['Date', 'Time', 'Student', 'Class', 'Spoke with', 'Result', 'Date given', 'Amount', 'What was discussed', 'By'],
        L.map(c => { const sid = c.sid || (c.sids || [])[0], n = stuName(sid); return [dmy(c.date), hm(c.startedAt || c.at), n.name, n.cls, c.with || '', c.initiated ? 'Call tried' : c.outcome, dmy(c.promise), c.amount ? money2(c.amount / 100) : '', c.note || c.autoRemark || '', c.by || '']; }), null, [, , 'l', , , , , , 'l']) + '<p class="note">Use ＋ Filters → From / To for dates. Tap a student anywhere to write a new call.</p>' };
  }
  const w = needSchool(); if (w) return { sumv: [], body: w };
  const tagOf = {}; D.all.forEach(x => { tagOf[x.sid] = x.tagName; });
  const Ss = SC.students.filter(s => (!S.cls || s.cls === S.cls) && (S.tag === null || tagOf[s.id] === S.tag));
  if (T === 'attend') {
    const months = sesMonths(), ym = S.am && months.includes(S.am) ? S.am : months[months.length - 1];
    const L = Ss.map(s => ({ s, m: stuAtt(s, ym + '-01', monthEnd(ym)), a: stuAtt(s, SC.start, SC.today), o: oldAtt((SC.resBy[s.id] || []).find(r => /final/i.test(r.exam) && oldAtt(r))) })).sort((a, b) => CLASSES.indexOf(a.s.cls) - CLASSES.indexOf(b.s.cls) || a.s.name.localeCompare(b.s.name));
    return { sumv: [['Students', L.length], [MN[+ym.slice(5, 7)] + ' avg', pc(ratio(sum(L, o => o.m.pres), sum(L, o => o.m.days)))], ['Session avg', pc(ratio(sum(L, o => o.a.pres), sum(L, o => o.a.days)))], ['Below 75%', L.filter(o => o.a.days && o.a.pct < 75).length]],
      body: `<select class="selx" onchange="S.am=this.value;render()">${months.map(m => `<option value="${m}" ${m === ym ? 'selected' : ''}>${MN[+m.slice(5, 7)]} ${m.slice(0, 4)}</option>`).join('')}</select>` +
        repTable(['Student', 'Class', 'Days (month)', 'Present', 'Absent', 'Month %', 'Session %', 'Absent (session)', 'Last session %'],
          L.map(o => [o.s.name, o.s.cls, o.m.days, o.m.pres, o.m.ab, o.m.pct == null ? '' : { v: o.m.pct + '%', t: o.m.pct, c: o.m.pct < 75 ? 'red' : '' }, o.a.pct == null ? '' : { v: o.a.pct + '%', t: o.a.pct, c: o.a.pct < 75 ? 'red' : '' }, o.a.ab, o.o ? o.o.pct + '%' : '']), null, ['l']) };
  }
  const { key, rows } = resPick(), sid = new Set(Ss.map(s => s.id)), L = rows.filter(r => (!S.cls && S.tag === null) || sid.has(r.sid)).sort((a, b) => CLASSES.indexOf(a.cls) - CLASSES.indexOf(b.cls) || (+a.rank || 999) - (+b.rank || 999));
  return { sumv: [['Students', L.length], ['Average', pc(L.length ? Math.round(sum(L, r => r.pct) * 10 / L.length) / 10 : null)], ['Passed', L.filter(r => r.pct >= 33).length], ['Below 40%', L.filter(r => r.pct < 40).length]],
    body: `<select class="selx" onchange="S.rx=this.value;render()">${SC.exs.map(x => `<option value="${esc(x)}" ${x === key ? 'selected' : ''}>${esc(longSes(x.split('|')[0]))} · ${esc(x.split('|')[1])}</option>`).join('')}</select>` +
      repTable(['Class', 'Rank', 'Student', 'Total', '%', 'Grade', 'Attendance', 'Subjects'], L.map(r => [r.cls, r.rank, r.name, r.total, { v: pc(r.pct), t: r.pct, c: r.pct < 40 ? 'red' : '' }, r.grade, r.att, Object.entries(r.subs || {}).map(([sb, cols]) => sb + ' ' + Object.values(cols).join('/')).join(', ')]), null, [, , 'l']) };
}

// ================================================================ App structure (Nov 2026): Home · Students · Classes · Fees · Staff · Reports
function pageBody() {
  if (S.prof) return profileBody();
  if (S.q.trim()) return searchBody();
  if (S.tab === 2 && S.room) return roomBody();
  return [homeBody, studentsBody, classesBody, feesBody, staffBody, repBody][S.tab]();
}
function goTo(n, sub) {
  S.prof = null; S.room = null; S.q = ''; S.sheet = null;
  if (n === 'school') { S.tab = sub === 'teach' ? 4 : 2; S.sv = sub || 'classes'; }
  else if (n === 'students') S.tab = 1;
  else { S.tab = 3; S.fs = { month: 'month', dues: 'dues', calls: 'calls' }[n] || 'sum'; }
  render(); const sc = $('sc'); if (sc) sc.scrollTop = 0;
}
const segs = (key, list, cls) => `<div class="seg ${cls || ''}">${list.map(([v, l]) => `<button class="${(S[key] || list[0][0]) === v ? 'on' : ''}" onclick="S.${key}='${v}';MQ=null;render()">${l}</button>`).join('')}</div>`;
// ---- a doughnut with the figure in the middle
function pie(id, labels, data, colors, mid, sub, h) {
  const has = data.some(v => v > 0);
  if (has) chart(id, { type: 'doughnut', data: { labels, datasets: [{ data, backgroundColor: colors, borderWidth: 0, hoverOffset: 4 }] },
    options: { responsive: true, maintainAspectRatio: false, cutout: '70%', plugins: { legend: { display: false }, tooltip: { callbacks: { label: c => ` ${c.label}: ${c.dataset.tip ? c.dataset.tip(c.parsed) : c.parsed.toLocaleString('en-IN')}` } } } } });
  return `<div class="pie" style="height:${h || 132}px">${has ? `<canvas id="${id}"></canvas>` : '<div class="pie0"></div>'}<div class="c"><b>${mid}</b><span>${sub || ''}</span></div></div>`;
}
const legend = (labels, data, colors, fmt) => `<div class="lg">${labels.map((l, i) => `<span><i style="background:${colors[i]}"></i>${esc(l)} <b>${fmt ? fmt(data[i]) : data[i]}</b></span>`).join('')}</div>`;
const pieCard = (title, pieHtml, leg, onclick) => `<button class="pc" ${onclick ? `onclick="${onclick}"` : 'tabindex="-1"'}><h4>${esc(title)}</h4>${pieHtml}${leg}</button>`;

// ---------------------------------------------------------------- Home
function homeBody() {
  const t = D.t, thought = THOUGHTS[Math.floor(new Date(D.today + 'T00:00:00').getTime() / 864e5) % THOUGHTS.length];
  const mon = cashIn(inMonth(D.rc, D.today.slice(0, 7))), today = cashIn(D.rc.filter(r => r.date === D.today));
  const feeC = [P.green, P.pink, P.grey], feeL = ['Collected', 'Due now', 'Not due yet'], feeD = [t.paid, t.dueNow, t.notYet];
  let html = `<div class="hero"><div><small>${new Date(D.today + 'T00:00:00').toLocaleDateString('en-IN', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })}</small><b>${esc(D.school || 'SN Mishra Singapore International School')}</b><span>Session ${esc(longSes(D.ses))}</span></div>
      <div class="hs">${[[SC && !SC.err ? SC.students.length : D.all.length, 'Students'], [SC && !SC.err ? SC.cls.filter(c => (SC.byCls[c] || []).length).length : new Set(D.all.map(x => x.cls)).size, 'Classes'], [SC && !SC.err ? Object.keys(SC.teachers).length : '—', 'Teachers'], [rsS(today.total), 'Fee today']].map(([v, l]) => `<div><b>${v}</b><span>${l}</span></div>`).join('')}</div></div>`;
  const pies = [pieCard('Fee collection', pie('hFee', feeL, feeD, feeC, pct(t.paid, t.paid + t.dueNow) + '%', 'collected'), legend(feeL, feeD, feeC, rsS), "goTo('sum')")];
  if (!SC) loadSchool();
  if (SC && !SC.err) {
    const R = SC.cls.filter(c => (SC.byCls[c] || []).length), tdy = SC.att[SC.today] || {}, M = R.filter(c => tdy[c]);
    const n = sum(M, c => SC.byCls[c].length), ab = sum(M, c => tdy[c].length), unm = sum(R.filter(c => !tdy[c]), c => SC.byCls[c].length);
    const aL = ['Present', 'Absent', 'Not marked'], aD = [n - ab, ab, unm], aC = [P.green, P.pink, P.grey];
    pies.push(pieCard('Attendance today', pie('hAtt', aL, aD, aC, M.length ? pc(ratio(n - ab, n)) : '—', M.length ? 'present' : 'not marked yet'), legend(aL, aD, aC), "goTo('school','att')"));
    const { exam, rows } = resPick(), band = g => /^A/.test(g) ? 'A' : /^B/.test(g) ? 'B' : /^C/.test(g) ? 'C' : 'D / E';
    const gL = ['A', 'B', 'C', 'D / E'], gD = gL.map(b => rows.filter(r => band(r.grade || grade(r.pct)) === b).length), gC = [P.green, P.blue, P.amber, P.pink];
    pies.push(pieCard('Results · ' + (exam || '').replace(/\s*\(.*\)/, ''), pie('hRes', gL.map(g => 'Grade ' + g), gD, gC, rows.length ? pc(Math.round(sum(rows, r => r.pct) * 10 / rows.length) / 10) : '—', 'average'), legend(gL.map(g => 'Grade ' + g), gD, gC), "goTo('school','res')"));
    const sy = Object.values(SC.syl), sd = sum(sy, y => y.chapters.filter(c => c.done).length), st = sum(sy, y => y.chapters.length);
    pies.push(pieCard('Syllabus', pie('hSyl', ['Done', 'Left'], [sd, st - sd], [P.indigo, P.grey], pc(ratio(sd, st)), 'completed'), legend(['Chapters done', 'Left'], [sd, st - sd], [P.indigo, P.grey]), "goTo('school','syl')"));
  } else pies.push(`<div class="pc"><h4>School</h4><p class="empty">${SC && SC.err ? esc(SC.err) : 'Loading attendance, results and syllabus…'}</p></div>`);
  html += `<h5 class="sh">At a glance</h5><div class="pg">${pies.join('')}</div>`;
  const QA = [['stu', 'Students', 'setTab(1)'], ['school', 'Attendance', "goTo('school','att')"], ['t_rep', 'Results', "goTo('school','res')"], ['t_month', 'Exams & events', "goTo('school','exams')"],
    ['t_over', 'Syllabus', "goTo('school','syl')"], ['t_dues', 'Fee collection', "goTo('sum')"], ['t_call', 'Call diary', "S.cmode='diary';goTo('calls')"], ['phone', 'Messages', "S.cmode='msg';goTo('calls')"],
    ['staff', 'Teachers & staff', 'setTab(4)'], ['t_rep', 'Reports', 'setTab(5)']];
  html += `<h5 class="sh">Quick open</h5><div class="qa">${QA.map(([i, l, f]) => `<button onclick="${f}">${ill(i)}<span>${l}</span></button>`).join('')}</div>`;
  // what needs attention today
  const A = [];
  if (SC && !SC.err) {
    const R = SC.cls.filter(c => (SC.byCls[c] || []).length), tdy = SC.att[SC.today] || {}, miss = R.filter(c => !tdy[c]), dow = new Date(SC.today + 'T00:00:00').getDay();
    if (dow !== 0 && miss.length) A.push(['warn', `Attendance not marked: ${miss.length} class${miss.length > 1 ? 'es' : ''}`, miss.slice(0, 8).join(', ') + (miss.length > 8 ? '…' : ''), "goTo('school','att')"]);
    const three = []; SC.cls.forEach(c => { const dd = SC.dates.filter(d => d <= SC.today && SC.att[d][c]).slice(-3); if (dd.length < 3) return; (SC.byCls[c] || []).forEach(s2 => { if (dd.every(d => SC.att[d][c].includes(s2.id))) three.push(s2); }); });
    if (three.length) A.push(['bad', `${three.length} student${three.length > 1 ? 's' : ''} absent 3 days in a row`, three.slice(0, 4).map(x => x.name).join(', '), "goTo('school','att')"]);
    const pend = SC.leaves.filter(l => l.status === 'Pending'); if (pend.length) A.push(['warn', `${pend.length} leave request${pend.length > 1 ? 's' : ''} waiting`, pend.map(l => tName(l.tid)).join(', '), 'setTab(4)']);
    const ab = SC.staffAtt[SC.today]; if (ab && ab.length) A.push(['warn', `${ab.length} teacher${ab.length > 1 ? 's' : ''} absent today`, ab.map(tName).join(', '), 'setTab(4)']);
    const nx = SC.exams.slice().sort((a, b) => String(a.date).localeCompare(String(b.date))).find(e => (e.to || e.date) >= SC.today);
    if (nx) A.push(['', `${nx.name} ${nx.date <= SC.today ? 'is running' : 'in ' + Math.round((new Date(nx.date) - new Date(SC.today)) / 864e5) + ' days'}`, dmy(nx.date) + (nx.to ? ' to ' + dmy(nx.to) : ''), "goTo('school','exams')"]);
  }
  const L = callLists(t.def);
  if (L.broken.length) A.push(['bad', `${L.broken.length} fee promises broken`, rsS(sum(L.broken, x => x.dueNow)) + ' due', "S.list='broken';S.cmode='calls';goTo('calls')"]);
  if (L.today.length) A.push(['', `${L.today.length} parents to call today`, 'they gave today’s date', "S.list='today';S.cmode='calls';goTo('calls')"]);
  const zero = D.all.filter(x => x.zero && x.dueNow > 0); if (zero.length) A.push(['bad', `${zero.length} students paid ₹0 this session`, rsS(sum(zero, x => x.dueNow)) + ' due', "goTo('dues')"]);
  html += `<h5 class="sh">Needs attention</h5>${card('', A.length ? `<div class="al">${A.map(([tn, a, b, f]) => `<button class="${tn}" onclick="${f}"><i></i><span><b>${esc(a)}</b><small>${esc(b)}</small></span>›</button>`).join('')}</div>` : '<p class="empty">All clear today.</p>')}`;
  html += `<div class="kp" style="margin-top:12px">${k('Fee today', rsS(today.total), today.n + ' receipts', 'ok', "goTo('sum')")}${k(MN[+D.today.slice(5, 7)] + ' so far', rsS(mon.total), `Fee ${rsS(mon.fee + mon.mixed)} · Transport ${rsS(mon.tr)}`, 'ac', "goTo('month')")}</div>`;
  if (SC && !SC.err) {
    const R = SC.cls.filter(c => (SC.byCls[c] || []).length), g = {}; SC.students.forEach(x => { const k2 = /^f/i.test(x.gender) ? 'Girls' : /^m/i.test(x.gender) ? 'Boys' : 'Not filled'; g[k2] = (g[k2] || 0) + 1; });
    chart('hStr', { type: 'bar', data: { labels: R, datasets: [{ label: 'Students', data: R.map(c => SC.byCls[c].length), backgroundColor: P.indigo, borderRadius: 6 }] }, options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, grid: { color: '#F1F1F4' } }, x: { grid: { display: false } } }, onClick: (e, el) => { if (el[0]) openRoom(R[el[0].index]); } } });
    const gl = Object.keys(g), gc = gl.map(x => x === 'Girls' ? P.pink : x === 'Boys' ? P.blue : P.grey);
    html += card('Students in each class', canvas('hStr', 200), '<small>tap a bar</small>') + `<div style="height:12px"></div>` + card('Boys and girls', `<div class="pr">${pie('hGen', gl, gl.map(x => g[x]), gc, SC.students.length, 'students', 140)}${legend(gl, gl.map(x => g[x]), gc)}</div>`);
    const ev = [...SC.cal.filter(e => (e.to || e.date) >= SC.today).map(e => ({ d: e.date, to: e.to, t: e.title, k: e.type || 'Event' })), ...SC.exams.filter(e => (e.to || e.date) >= SC.today).map(e => ({ d: e.date, to: e.to, t: e.name, k: 'Exam' }))].sort((a, b) => a.d.localeCompare(b.d)).slice(0, 6);
    if (ev.length) html += `<div style="height:12px"></div>` + card('Coming up', `<div class="ev">${ev.map(e => `<div><b>${e.d.slice(8, 10)}<small>${M3(+e.d.slice(5, 7))}</small></b><span>${esc(e.t)}<small>${esc(e.k)}${e.to && e.to !== e.d ? ' · till ' + dmy(e.to) : ''}</small></span></div>`).join('')}</div>`);
  }
  html += `<div style="height:12px"></div><div class="promo">${ill('bulb')}<div><b>THOUGHT OF THE DAY</b><span>${esc(thought)}</span></div></div>`;
  return html;
}

// ---------------------------------------------------------------- Students: search, filters, profile
const PH = {}, PHQ = new Set(); let PROF = {};
function avatar(sid, name, big) {
  const s = SC && SC.stu && SC.stu[sid], ini = esc(String(name || '?').split(/\s+/).map(w => w[0]).slice(0, 2).join('').toUpperCase());
  return `<div class="ava${big ? ' big' : ''}">${ini}${s && s.photo ? `<img data-ph="${esc(sid)}" ${PH[sid] ? `src="${PH[sid]}"` : ''} alt="">` : ''}</div>`;
}
async function loadPhotos() {
  const want = [...document.querySelectorAll('img[data-ph]:not([src])')].map(i => i.dataset.ph).filter(id => !PHQ.has(id)).slice(0, 40);
  if (!want.length) return; want.forEach(id => PHQ.add(id));
  try { const d = await api('stumaster_api.php?a=photos&ids=' + encodeURIComponent(want.join(',')));
    Object.entries(d.photos || {}).forEach(([id, src]) => { PH[id] = src; document.querySelectorAll(`img[data-ph="${CSS.escape(id)}"]`).forEach(im => { im.src = src; }); });
    loadPhotos(); } catch (e) {}
}
function allStudents() {
  const fee = {}; D.all.forEach(x => { fee[x.sid] = x; });
  const base = SC && !SC.err ? SC.students.map(s2 => ({ sid: s2.id, name: s2.name, cls: s2.cls, section: s2.section, father: s2.father, phone: s2.phone, adm: s2.adm, gender: s2.gender, f: fee[s2.id] || null }))
    : D.all.map(x => ({ sid: x.sid, name: x.name, cls: x.cls, section: x.section, father: x.father, phone: x.phoneNo, adm: x.adm, gender: '', f: x }));
  return base;
}
function stuStats(o) {
  const s2 = SC && !SC.err && SC.stu[o.sid], a = s2 ? stuAtt(s2, SC.start, SC.today) : null, r = SC && !SC.err ? lastRes(o.sid) : null;
  return { att: a && a.days ? a.pct : null, res: r ? r.pct : null, grade: r ? r.grade : '', feeP: o.f ? pct(o.f.paid, o.f.paid + o.f.yearBal) : null, due: o.f ? o.f.dueNow : 0 };
}
function stuCards(list, limit) {
  if (!list.length) return '<p class="empty">No student here.</p>';
  const L = list.slice(0, limit || 120);
  setTimeout(loadPhotos, 50);
  return `<div class="sc">${L.map(o => { const st = stuStats(o);
    return `<button class="sr" onclick="openStu('${esc(o.sid)}')">${avatar(o.sid, o.name)}<span class="t"><b>${esc(o.name)}</b><small>Class ${esc(o.cls)}${o.section ? '-' + esc(o.section) : ''}${o.adm ? ' · Adm ' + esc(o.adm) : ''}</small><small>${esc(o.father || '')}</small>
      <span class="mp"><em class="${st.feeP == null ? '' : st.due > 0 ? 'bad' : 'ok'}">Fee ${st.feeP == null ? '—' : st.due > 0 ? rsS(st.due) + ' due' : 'clear'}</em><em class="${tone(st.att)}">Att ${pc(st.att)}</em><em class="${st.res == null ? '' : st.res < 40 ? 'bad' : st.res >= 80 ? 'ok' : ''}">Result ${pc(st.res)}</em></span></span>›</button>`; }).join('')}</div>${list.length > L.length ? `<p class="note">Showing ${L.length} of ${list.length}. Search or filter to narrow.</p>` : ''}`;
}
function searchBody() {
  const q = S.q.trim().toLowerCase(), d = q.replace(/\D/g, '');
  // Inactive / left children are found by search too (owner, 5 Nov 2026), marked "Inactive"; they stay out of every count.
  const ina = SC && !SC.err ? (SC.inactive || []).map(s2 => ({ sid: s2.id, name: s2.name + ' (Inactive)', cls: s2.cls, section: s2.section, father: s2.father, phone: s2.phone, adm: s2.adm, gender: '', f: null })) : [];
  const rows = allStudents().concat(ina).filter(o => [o.name, o.father, o.adm, o.cls, o.f && o.f.tagName].some(v => String(v || '').toLowerCase().includes(q)) || (d.length >= 4 && String(o.phone || '').includes(d)) || (o.f && d.length >= 4 && o.f.phoneNo.includes(d)));
  return card(`Search · ${rows.length} found`, stuCards(rows, 60));
}
const SF = [['all', 'All'], ['due', 'Fee due'], ['zero', 'Paid ₹0'], ['lowatt', 'Attendance < 75%'], ['weak', 'Result < 40%'], ['new', 'New admissions'], ['girls', 'Girls'], ['boys', 'Boys']];
function studentsBody() {
  if (!SC) loadSchool();
  let L = allStudents(); const f = S.sf || 'all';
  if (S.scl) L = L.filter(o => o.cls === S.scl);
  const st = o => (o._s = o._s || stuStats(o));
  L = L.filter(o => f === 'all' || (f === 'due' ? o.f && o.f.dueNow > 0 : f === 'zero' ? o.f && o.f.zero && o.f.dueNow > 0 : f === 'lowatt' ? st(o).att != null && st(o).att < 75 : f === 'weak' ? st(o).res != null && st(o).res < 40
    : f === 'new' ? SC && SC.stu[o.sid] && /new/i.test(SC.stu[o.sid].stuType || '') : f === 'girls' ? /^f/i.test(o.gender) : /^m/i.test(o.gender)));
  L.sort((a, b) => S.ss === 'due' ? (b.f ? b.f.dueNow : 0) - (a.f ? a.f.dueNow : 0) : S.ss === 'name' ? a.name.localeCompare(b.name) : CLASSES.indexOf(a.cls) - CLASSES.indexOf(b.cls) || a.name.localeCompare(b.name));
  const cl = CLASSES.filter(c => allStudents().some(o => o.cls === c));
  return `<div class="chips" style="margin:0 -14px 10px">${SF.map(([v, l]) => `<button class="chip${f === v ? ' on' : ''}" onclick="S.sf='${v}';render()">${l}</button>`).join('')}</div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px"><select class="selx" onchange="S.scl=this.value;render()"><option value="">All classes</option>${cl.map(c => `<option ${S.scl === c ? 'selected' : ''}>${c}</option>`).join('')}</select>
      <select class="selx" onchange="S.ss=this.value;render()">${[['cls', 'Sort: class'], ['name', 'Sort: name'], ['due', 'Sort: fee due']].map(([v, l]) => `<option value="${v}" ${(S.ss || 'cls') === v ? 'selected' : ''}>${l}</option>`).join('')}</select></div>
    ${card(`${L.length} student${L.length === 1 ? '' : 's'}`, stuCards(L, 150))}
    <p class="note">Tap any student for the full profile: details, parents, fees, attendance, report cards and calls. Use the search bar at the top for a name, father, mobile or admission number.</p>`;
}
async function loadProf(sid) {
  if (PROF[sid]) return;
  PROF[sid] = { loading: true };
  try { const d = await api('stumaster_api.php?a=get&id=' + encodeURIComponent(sid)); PROF[sid] = { f: d.f, photo: d.photo }; if (d.photo) PH[sid] = d.photo; }
  catch (e) { PROF[sid] = { err: e.message }; }
  if (S.prof === sid) render();
}
const mask = v => { v = String(v || '').replace(/\s/g, ''); return v.length >= 8 ? 'XXXX XXXX ' + v.slice(-4) : v; };
function dl(rows) { const R = rows.filter(([, v]) => String(v ?? '').trim() !== ''); return R.length ? `<div class="dlx">${R.map(([l, v]) => `<div><span>${esc(l)}</span><b>${esc(v)}</b></div>`).join('')}</div>` : '<p class="note">Not filled in the Student Master yet.</p>'; }
function profileBody() {
  const sid = S.prof, x = D.all.find(z => z.sid === sid), s2 = SC && !SC.err ? SC.stu[sid] : null, P0 = PROF[sid] || {}, f = P0.f || {};
  const name = (x && x.name) || (s2 && s2.name) || f.name || ((SC && SC.resBy && (SC.resBy[sid] || [])[0]) || {}).name || 'Student';
  const cls = (x && x.cls) || (s2 && s2.cls) || f.clsid || '', sec = (x && x.section) || (s2 && s2.section) || f.secid || '';
  const phone = (x && x.phoneNo) || String(f.fmobno || f.telphno || (s2 && s2.phone) || '').replace(/\D/g, '').slice(-10);
  const att = s2 ? stuAtt(s2, SC.start, SC.today) : null, lr = SC && !SC.err ? lastRes(sid) : null;
  const feeP = x ? pct(x.paid, x.paid + x.yearBal) : null;
  const sib = SC && !SC.err && phone ? SC.students.filter(o => o.id !== sid && String(o.phone || '').replace(/\D/g, '').slice(-10) === phone) : [];
  const tab = S.ptab || 'over';
  let body = '';
  if (tab === 'over') {
    body = `${card('Personal details', dl([['Date of birth', f.dob || (s2 && s2.dob)], ['Gender', f.gender || (s2 && s2.gender)], ['Blood group', f.blood_group], ['Category', f.cast], ['Religion', f.religion], ['Mother tongue', f.mother_tongue], ['Nationality', f.snationality], ['Name in Hindi', f.hname], ['Aadhaar', mask(f.adharcard)], ['Birth place', [f.dob_city, f.dob_state].filter(Boolean).join(', ')]]))}
      ${card('School details', dl([['Admission no', f.rollno || (x && x.adm)], ['Class', cls + (sec ? '-' + sec : '')], ['Roll no', f.crollno], ['Admission date', f.cdate], ['Admitted in class', f.aclsid], ['Admission as', f.admtype], ['Student type', f.stutype], ['House', f.houseid], ['Medium', f.smedium], ['PEN no', f.hsrollno], ['Enrollment no', f.enrollno], ['Status', f.cstatus || f.scount_status], ['Fee tag', x && x.tag]]))}
      ${card('Parents', dl([['Father', f.fname || (x && x.father)], ['Father’s mobile', f.fmobno || phone], ['Father’s occupation', f.fathoccu], ['Father’s qualification', f.fqualif], ['Mother', f.mname || (s2 && s2.mother)], ['Mother’s mobile', f.mothmobno], ['Mother’s occupation', f.mothoccu], ['Mother’s qualification', f.mqualif], ['Email', f.email], ['Local guardian', [f.lg_name, f.lg_relation].filter(Boolean).join(' · ')], ['Guardian phone', f.lg_contact]]))}
      ${card('Address & transport', dl([['Address', f.paddr], ['Landmark', f.mcgram], ['Village / city', f.croad], ['District', f.ctygram], ['State', f.cstate], ['PIN', f.cpincode], ['Facility', f.faclty], ['Route', f.routeid || (s2 && s2.route)], ['Vehicle', f.vehicle_id], ['Fee scheme', f.feeapply]]))}
      ${f.pschname || f.schlstclass ? card('Previous school', dl([['School', f.pschname], ['City', f.pscity], ['Last class', f.schlstclass]])) : ''}
      ${sib.length ? card('Brothers / sisters in school', `<div class="sc">${sib.map(o => `<button class="sr" onclick="openStu('${esc(o.id)}')">${avatar(o.id, o.name)}<span class="t"><b>${esc(o.name)}</b><small>Class ${esc(o.cls)}</small></span>›</button>`).join('')}</div>`) : ''}
      ${f.remarks ? card('Remarks', `<p class="note" style="margin:0;white-space:pre-wrap">${esc(f.remarks)}</p>`) : ''}
      ${P0.loading ? '<p class="note">Loading the full Student Master record…</p>' : P0.err ? `<p class="note">${esc(P0.err)}</p>` : ''}`;
  } else if (tab === 'fee') {
    if (!x) body = card('Fees', `<p class="empty">No fee card for ${esc(longSes(D.ses))}.</p>`);
    else { const rows = x.mon.map((y, i) => { const due = Math.max(0, y.fd - y.fp) + Math.max(0, y.td - y.tp), st = y.fd + y.td === 0 ? '—' : due === 0 ? 'Paid' : i > D.idx ? 'Not due yet' : y.fp + y.tp > 0 ? 'Part paid' : 'Unpaid';
        return `<tr class="${st === 'Unpaid' || st === 'Part paid' ? 'hl' : ''}"><td>${M3(y.m)} ${ymOf(y.m).slice(2, 4)}</td><td>${rs(y.fd)}</td><td>${rs(y.td)}</td><td>${rs(y.fp + y.tp)}</td><td class="${due && i <= D.idx ? 'red' : ''}">${rs(due)}</td><td><span class="stt ${st === 'Paid' ? 'ok' : st === 'Unpaid' ? 'bad' : st === 'Part paid' ? 'warn' : ''}">${st}</span></td></tr>`; }).join('');
      body = `<div class="kp">${k('Due till now', rs(x.dueNow), x.since ? 'since ' + esc(x.since) : 'nothing due', x.dueNow ? 'bad' : 'ok')}${k('Paid + discount', rs(x.paid), 'this session', 'ok')}${k('Session fee', rs(x.annual), 'overall due ' + rs(x.yearBal))}${k('Old dues', rs(x.old), 'earlier sessions', x.old ? 'warn' : '')}</div>
        <div style="height:12px"></div>${card('Month by month · ' + longSes(D.ses), `<div class="tw"><table class="tbl"><tr><th>Month</th><th>Fee</th><th>Transport</th><th>Paid</th><th>Due</th><th>Status</th></tr>${rows}<tr class="tot"><td>Total</td><td>${rs(sum(x.mon, y => y.fd))}</td><td>${rs(sum(x.mon, y => y.td))}</td><td>${rs(x.paid)}</td><td>${rs(x.yearBal - x.old)}</td><td></td></tr></table></div>`)}
        <div style="height:12px"></div>${card('Receipts · ' + x.rcs.length, x.rcs.length ? `<div class="tw"><table class="tbl"><tr><th>Date</th><th>Receipt</th><th>Session</th><th>Months</th><th>Amount</th><th>Discount</th><th>Mode</th></tr>${x.rcs.map(r => `<tr><td>${dmy(r.date)}</td><td>${esc(r.no)}</td><td>${esc(longSes(r.session))}</td><td>${esc(r.months || '')}</td><td>${rs(r.received)}</td><td>${rs(r.discount)}</td><td>${esc(r.mode)}</td></tr>`).join('')}</table></div>` : '<p class="empty">No receipt yet.</p>')}`; }
  } else if (tab === 'att') body = profAtt(sid);
  else if (tab === 'res') body = card('Report cards', stuSchool(sid).replace(/<label class="lbl" style="margin-top:14px">ATTENDANCE[\s\S]*?(?=<label class="lbl" style="margin-top:14px">REPORT)/, ''));
  else if (tab === 'calls') { const calls = x ? x.calls : D.calls.filter(c => (c.sids || []).includes(sid)), msgs = x ? x.msgs : [];
    body = `<button class="cta" onclick="noteCall('${esc(sid)}')">${ic('note', 16)} Write a call — what was discussed</button><div style="height:12px"></div>${card('Calls · ' + calls.length, calls.length ? diaryList(calls) : '<p class="empty">No call yet.</p>')}<div style="height:12px"></div>${card('Messages · ' + msgs.length, msgs.length ? `<p class="note" style="margin:0">${msgs.slice(0, 30).map(m => dmy(m.date) + ' · ' + esc(m.kind) + ' · ' + esc(m.by || '')).join('<br>')}</p>` : '<p class="empty">No message sent yet.</p>')}`; }
  const pies = [pie('pFee', ['Paid', 'Due'], x ? [x.paid, x.yearBal] : [0, 0], [P.green, P.pink], feeP == null ? '—' : feeP + '%', 'fee paid', 104),
    pie('pAtt', ['Present', 'Absent'], att && att.days ? [att.pres, att.ab] : [0, 0], [P.green, P.pink], att && att.days ? pc(att.pct) : '—', 'attendance', 104),
    pie('pRes', ['Scored', 'Lost'], lr ? [lr.pct, 100 - lr.pct] : [0, 0], [P.indigo, P.grey], lr ? pc(lr.pct) : '—', lr ? 'last result' : 'no result', 104)];
  setTimeout(loadPhotos, 50);
  return `<button class="back" onclick="S.prof=null;render()">${ic('back', 16)} Back</button>
    <section class="pf"><div class="pfh">${avatar(sid, name, true)}<div><h2>${esc(name)}</h2><p>Class ${esc(cls)}${sec ? '-' + esc(sec) : ''}${(f.rollno || (x && x.adm)) ? ' · Adm ' + esc(f.rollno || x.adm) : ''}</p><p>${esc(f.fname || (x && x.father) || (s2 && s2.father) || '')}</p>
      <div>${x && x.tag ? `<span class="tag">${esc(x.tag)}</span>` : ''}${x && x.zero && x.dueNow > 0 ? '<span class="tag brk">₹0 paid</span>' : ''}${x && x.broken ? '<span class="tag brk">Promise broken</span>' : ''}${lr ? `<span class="tag">${esc(lr.grade)}</span>` : ''}</div></div></div>
      <div class="pp">${pies.join('')}</div>
      <div class="pa">${phone ? `<a href="tel:+91${phone}" onclick="callNow('${esc(sid)}')">${ic('phone', 18)}<span>Call</span></a><a target="_blank" rel="noopener" href="https://wa.me/91${phone}${x ? '?text=' + encodeURIComponent(remind(x)) : ''}" ${x ? `onclick="logMsg(['${esc(sid)}'],'Fee reminder',remind(D.all.find(z=>z.sid==='${esc(sid)}')))"` : ''}>${ic('wa', 18)}<span>WhatsApp</span></a>` : ''}<button onclick="noteCall('${esc(sid)}')">${ic('note', 18)}<span>Write call</span></button>${D.role === 'principal' ? `<a href="stumaster.php" target="_blank" rel="noopener">${ic('share', 18)}<span>Edit</span></a>` : ''}</div></section>
    ${segs('ptab', [['over', 'Profile'], ['fee', 'Fees'], ['att', 'Attendance'], ['res', 'Results'], ['calls', 'Calls']], 'sticky')}
    <div style="height:12px"></div>${body}`;
}
// ---- attendance calendar of one student
function profAtt(sid) {
  if (!SC || SC.err) return needSchool() || '';
  const s2 = SC.stu[sid]; if (!s2) return card('Attendance', '<p class="empty">This student is not on a class list.</p>');
  const months = sesMonths(), ym = S.pm && months.includes(S.pm) ? S.pm : months[months.length - 1], a = stuAtt(s2, ym + '-01', monthEnd(ym)), all = stuAtt(s2, SC.start, SC.today);
  const y = +ym.slice(0, 4), m = +ym.slice(5, 7), first = new Date(y, m - 1, 1).getDay(), days = new Date(y, m, 0).getDate(), hol = d => SC.cal.find(e => /holiday/i.test(e.type) && e.date <= d && (e.to || e.date) >= d);
  let cells = ''; for (let i = 0; i < (first + 6) % 7; i++) cells += '<i></i>';
  for (let dd = 1; dd <= days; dd++) { const d = ym + '-' + String(dd).padStart(2, '0'), at = SC.att[d] && SC.att[d][s2.cls], sun = new Date(y, m - 1, dd).getDay() === 0, h = hol(d);
    const c = at ? (at.includes(sid) ? 'ca' : 'cp') : sun ? 'sun' : h ? 'hol' : d > SC.today ? 'fut' : 'nm';
    cells += `<i class="${c}" title="${dmy(d)}${h ? ' · ' + esc(h.title) : ''}">${dd}</i>`; }
  return `<div class="kp">${k(MN[m], pc(a.pct), a.days ? `${a.pres} present · ${a.ab} absent of ${a.days} days` : 'not marked yet', tone(a.pct))}${k('Whole session', pc(all.pct), all.days ? `${all.pres} of ${all.days} days · absent ${all.ab}` : 'not marked yet', tone(all.pct))}</div>
    <div class="chips months" style="margin:12px -14px">${months.map(mm => `<button class="chip${mm === ym ? ' on' : ''}" onclick="S.pm='${mm}';render()">${M3(+mm.slice(5, 7))}<small>${mm.slice(0, 4)}</small></button>`).join('')}</div>
    ${card(MN[m] + ' ' + y, `<div class="calg"><b>Mon</b><b>Tue</b><b>Wed</b><b>Thu</b><b>Fri</b><b>Sat</b><b>Sun</b>${cells}</div><div class="lg" style="margin-top:10px"><span><i style="background:#22B573"></i>Present</span><span><i style="background:#E3417F"></i>Absent</span><span><i style="background:#F5C842"></i>Holiday</span><span><i style="background:#E7E7EE"></i>Not marked</span></div>`)}
    ${all.absent.length ? `<div style="height:12px"></div>` + card('Absent days this session · ' + all.absent.length, `<p class="note" style="margin:0">${all.absent.slice().reverse().map(dmy).join(', ')}</p>`) : ''}
    ${(() => { const o = (SC.resBy[sid] || []).filter(r => oldAtt(r)); return o.length ? `<div style="height:12px"></div>` + card('Earlier sessions (report cards)', `<div class="tw"><table class="tbl"><tr><th>Session</th><th>Exam</th><th>Days</th><th>%</th></tr>${o.map(r => { const q = oldAtt(r); return `<tr><td>${esc(longSes(r.ses))}</td><td>${esc(r.exam)}</td><td>${esc(r.att)}</td><td class="${q.pct < 75 ? 'red' : ''}">${pc(q.pct)}</td></tr>`; }).join('')}</table></div>`) : ''; })()}`;
}

// ---------------------------------------------------------------- Classes (academics) and one class room
function classesBody() {
  const nav = segs('sv', [['classes', 'Classes'], ['att', 'Attendance'], ['res', 'Results'], ['syl', 'Syllabus'], ['exams', 'Exams']], 'sm');
  const w = needSchool(); if (w) return nav + '<div style="height:12px"></div>' + w;
  return nav + '<div style="height:12px"></div>' + ({ classes: schClasses, att: schAtt, res: schRes, syl: schSyl, exams: schExams, teach: schClasses }[S.sv || 'classes'])();
}
function openRoom(c) { S.tab = 2; S.room = c; S.rtab = S.rtab || 'over'; S.sheet = null; S.prof = null; S.q = ''; render(); const sc = $('sc'); if (sc) sc.scrollTop = 0; }
function roomBody() {
  const w = needSchool(); if (w) return w;
  const c = S.room, r = clsRow(c), L = SC.byCls[c] || [], tab = S.rtab || 'over', kids = D.all.filter(x => x.cls === c);
  let body = '';
  if (tab === 'over') {
    const paid = sum(kids, x => x.paid), due = sum(kids, x => x.dueNow), later = sum(kids, x => x.yearBal) - due;
    const ym = SC.today.slice(0, 7), m = clsAtt(c, ym + '-01', monthEnd(ym));
    const lr = L.map(s2 => lastRes(s2.id)).filter(Boolean), band = g => /^A/.test(g) ? 'A' : /^B/.test(g) ? 'B' : /^C/.test(g) ? 'C' : 'D / E', gL = ['A', 'B', 'C', 'D / E'], gD = gL.map(b => lr.filter(x => band(x.grade || grade(x.pct)) === b).length);
    const subs = SC.alloc.filter(a => a.c === c && a.n > 0), sy = sylRows(c);
    body = `<div class="pg">${pieCard('Fees', pie('rFee', ['Collected', 'Due now', 'Not due yet'], [paid, due, Math.max(0, later)], [P.green, P.pink, P.grey], pct(paid, paid + due) + '%', 'collected'), legend(['Collected', 'Due now'], [paid, due], [P.green, P.pink], rsS), "S.rtab='fee';render()")}
      ${pieCard('Attendance · ' + M3(+ym.slice(5, 7)), pie('rAtt', ['Present', 'Absent'], [m.pres, m.tot - m.pres], [P.green, P.pink], pc(m.pct), m.days + ' days'), legend(['Present', 'Absent'], [m.pres, m.tot - m.pres], [P.green, P.pink]), "S.rtab='att';render()")}
      ${pieCard('Results', pie('rRes', gL, gD, [P.green, P.blue, P.amber, P.pink], pc(r.avg), 'average'), legend(gL.map(g => 'Grade ' + g), gD, [P.green, P.blue, P.amber, P.pink]), "S.rtab='res';render()")}
      ${pieCard('Syllabus', pie('rSyl', ['Done', 'Left'], [sum(sy, x => x.done), sum(sy, x => x.total - x.done)], [P.indigo, P.grey], pc(ratio(sum(sy, x => x.done), sum(sy, x => x.total))), 'completed'), legend(['Done', 'Left'], [sum(sy, x => x.done), sum(sy, x => x.total - x.done)], [P.indigo, P.grey]), "S.rtab='syl';render()")}</div>
      <div style="height:12px"></div>${card('Subjects and teachers', subs.length ? `<div class="tw"><table class="tbl"><tr><th>Subject</th><th>Teacher</th><th>Periods / week</th></tr>${subs.map(a => `<tr><td>${esc(a.s)}</td><td>${esc(tName(a.t))}</td><td>${a.n}</td></tr>`).join('')}</table></div>` : '<p class="empty">No subjects assigned yet.</p>')}`;
  } else if (tab === 'stu') {
    body = card(`${L.length} students`, stuCards(L.map(s2 => ({ sid: s2.id, name: s2.name, cls: s2.cls, section: s2.section, father: s2.father, adm: s2.adm, f: D.all.find(x => x.sid === s2.id) || null })), 200));
  } else if (tab === 'att') body = attRegister(c);
  else if (tab === 'fee') body = feeRegister(c);
  else if (tab === 'res') body = classResults(c);
  else if (tab === 'syl') { const sy = sylRows(c); body = card('Syllabus · Class ' + c, sy.length ? sylList(sy) : '<p class="empty">No chapter list for this class yet.</p>'); }
  return `<button class="back" onclick="S.room=null;render()">${ic('back', 16)} All classes</button>
    <section class="rh"><div><h2>Class ${esc(c)}</h2><p>Class teacher: ${esc(r.ct || '—')} · ${r.n} students</p></div><div class="rk"><span><b>${pc(r.today)}</b>today</span><span><b>${pc(r.mon)}</b>month</span><span><b>${rsS(r.due)}</b>fee due</span></div></section>
    ${segs('rtab', [['over', 'Overview'], ['stu', 'Students'], ['att', 'Attendance register'], ['fee', 'Fee register'], ['res', 'Results'], ['syl', 'Syllabus']], 'sm sticky')}<div style="height:12px"></div>${body}`;
}
// ---- the class attendance register: every child, every school day of the month
function attRegister(c) {
  const L = SC.byCls[c] || [], months = sesMonths(), ym = S.am && months.includes(S.am) ? S.am : months[months.length - 1], y = +ym.slice(0, 4), m = +ym.slice(5, 7), n = new Date(y, m, 0).getDate();
  const days = []; for (let d = 1; d <= n; d++) { const iso = ym + '-' + String(d).padStart(2, '0'); if (iso > SC.today) break; if (new Date(y, m - 1, d).getDay() !== 0) days.push(iso); }
  const marked = days.filter(d => SC.att[d] && SC.att[d][c]);
  REP = { head: ['Student', ...days.map(d => d.slice(8, 10)), 'P', 'A', '%'], rows: L.map(s2 => { const a = stuAtt(s2, ym + '-01', monthEnd(ym)); return [s2.name, ...days.map(d => { const at = SC.att[d] && SC.att[d][c]; return at ? (at.includes(s2.id) ? 'A' : 'P') : ''; }), a.pres, a.ab, a.pct == null ? '' : a.pct]; }), foot: null };
  return `<select class="selx" onchange="S.am=this.value;render()">${months.map(mm => `<option value="${mm}" ${mm === ym ? 'selected' : ''}>${MN[+mm.slice(5, 7)]} ${mm.slice(0, 4)}</option>`).join('')}</select>
    ${card(`Attendance register · ${MN[m]} ${y}`, `<div class="tw reg"><table class="tbl ar"><tr><th class="nm">Student</th>${days.map(d => `<th class="${SC.att[d] && SC.att[d][c] ? '' : 'nm0'}">${d.slice(8, 10)}</th>`).join('')}<th>P</th><th>A</th><th>%</th></tr>
      ${L.map(s2 => { const a = stuAtt(s2, ym + '-01', monthEnd(ym)); return `<tr><td class="nm" onclick="openStu('${esc(s2.id)}')">${esc(s2.name)}</td>${days.map(d => { const at = SC.att[d] && SC.att[d][c]; return at ? (at.includes(s2.id) ? '<td class="ca">A</td>' : '<td class="cp">P</td>') : '<td class="cx">·</td>'; }).join('')}<td>${a.pres}</td><td>${a.ab}</td><td class="${a.pct != null && a.pct < 75 ? 'red' : ''}">${pc(a.pct)}</td></tr>`; }).join('')}
      <tr class="tot"><td class="nm">Present</td>${days.map(d => { const at = SC.att[d] && SC.att[d][c]; return `<td>${at ? L.length - at.length : ''}</td>`; }).join('')}<td></td><td></td><td>${pc(clsAtt(c, ym + '-01', monthEnd(ym)).pct)}</td></tr></table></div>
      <p class="note">${marked.length} of ${days.length} school days marked. P present · A absent · dot not marked. Tap a name for the child's profile.</p>
      <div class="rbtns" style="margin-top:10px"><button class="cta" onclick="downloadRep()">Download (Excel)</button><button class="cta lt" onclick="copyRep()">Copy</button></div>`)}`;
}
// ---- the class fee register: every child, every month of the session
function feeRegister(c) {
  const L = D.all.filter(x => x.cls === c).sort((a, b) => a.name.localeCompare(b.name));
  const cell = (y, i) => { const due = Math.max(0, y.fd - y.fp) + Math.max(0, y.td - y.tp); return y.fd + y.td === 0 ? ['', ''] : due === 0 ? ['✓', 'cp'] : i > D.idx ? ['·', 'cx'] : [Math.round(due).toLocaleString('en-IN'), 'ca']; };
  REP = { head: ['Student', 'Tag', 'Old due', ...MORDER.map(M3), 'Paid', 'Due now', 'Overall due'], rows: L.map(x => [x.name, x.tag, Math.round(x.old), ...x.mon.map((y, i) => cell(y, i)[0]), Math.round(x.paid), Math.round(x.dueNow), Math.round(x.yearBal)]), foot: null };
  return card(`Fee register · Class ${c} · ${longSes(D.ses)}`, L.length ? `<div class="tw reg"><table class="tbl ar fr"><tr><th class="nm">Student</th><th>Old</th>${MORDER.map(m => `<th>${M3(m)}</th>`).join('')}<th>Paid</th><th>Due now</th></tr>
    ${L.map(x => `<tr><td class="nm" onclick="openStu('${esc(x.sid)}')">${esc(x.name)}${x.tag ? `<small>${esc(x.tag)}</small>` : ''}</td><td class="${x.old ? 'ca' : ''}">${x.old ? Math.round(x.old) : ''}</td>${x.mon.map((y, i) => { const [v, k2] = cell(y, i); return `<td class="${k2}">${v}</td>`; }).join('')}<td>${rs(x.paid)}</td><td class="red">${rs(x.dueNow)}</td></tr>`).join('')}
    <tr class="tot"><td class="nm">Total</td><td>${rs(sum(L, x => x.old))}</td>${MORDER.map((m, i) => `<td>${Math.round(sum(L, x => Math.max(0, x.mon[i].fd - x.mon[i].fp) + Math.max(0, x.mon[i].td - x.mon[i].tp))).toLocaleString('en-IN')}</td>`).join('')}<td>${rs(sum(L, x => x.paid))}</td><td>${rs(sum(L, x => x.dueNow))}</td></tr></table></div>
    <p class="note">✓ paid · red figure = still due · dot = not due yet. Totals row = still due in that month.</p><div class="rbtns" style="margin-top:10px"><button class="cta" onclick="downloadRep()">Download (Excel)</button><button class="cta lt" onclick="copyRep()">Copy</button></div>` : '<p class="empty">No fee card in this class.</p>');
}
function classResults(c) {
  const ids = new Set((SC.byCls[c] || []).map(s2 => s2.id)), exs = SC.exs.filter(k2 => SC.res.some(r => ids.has(r.sid) && r.ses + '|' + r.exam === k2));
  if (!exs.length) return card('Results', '<p class="empty">No marks for this class yet.</p>');
  const key = S.rcx && exs.includes(S.rcx) ? S.rcx : exs[0], [ses, exam] = key.split('|'), R = SC.res.filter(r => ids.has(r.sid) && r.ses === ses && r.exam === exam).sort((a, b) => (b.pct ?? -1) - (a.pct ?? -1));
  const subs = [...new Set(R.flatMap(r => Object.keys(r.subs || {})))], val = (r, sb) => { const v = (r.subs || {})[sb]; if (!v) return ''; const vals = Object.values(v); return vals[vals.length - 1]; };
  REP = { head: ['Rank', 'Student', 'Class then', ...subs, 'Total', '%', 'Grade', 'Attendance'], rows: R.map((r, i) => [i + 1, r.name, r.cls, ...subs.map(sb => val(r, sb)), r.total, r.pct, r.grade, r.att]), foot: null };
  return `<select class="selx" onchange="S.rcx=this.value;render()">${exs.map(x => `<option value="${esc(x)}" ${x === key ? 'selected' : ''}>${esc(longSes(x.split('|')[0]))} · ${esc(x.split('|')[1])}</option>`).join('')}</select>
    ${card(`${exam} · ${longSes(ses)} · ${R.length} students`, `<div class="tw reg"><table class="tbl ar"><tr><th>#</th><th class="nm">Student</th>${subs.map(sb => `<th>${esc(sb)}</th>`).join('')}<th>Total</th><th>%</th><th>Grade</th><th>Att.</th></tr>${R.map((r, i) => `<tr><td>${i + 1}</td><td class="nm" onclick="openStu('${esc(r.sid)}')">${esc(r.name)}</td>${subs.map(sb => `<td>${esc(val(r, sb))}</td>`).join('')}<td>${esc(r.total)}</td><td class="${r.pct < 40 ? 'red' : ''}">${pc(r.pct)}</td><td>${esc(r.grade)}</td><td>${esc(r.att)}</td></tr>`).join('')}</table></div>
      <p class="note">Old sessions show the class the child was in then. Subject figure = total in that subject.</p><div class="rbtns" style="margin-top:10px"><button class="cta" onclick="downloadRep()">Download (Excel)</button><button class="cta lt" onclick="copyRep()">Copy</button></div>`)}`;
}
function sylRows(only) {
  return Object.keys(SC.syl).map(key => { const i = key.indexOf('|'), c = key.slice(0, i), sb = key.slice(i + 1), y = SC.syl[key], done = y.chapters.filter(ch => ch.done), a = SC.alloc.find(z => z.c === c && z.s === sb);
    return { c, sb, done: done.length, total: y.chapters.length, last: done.map(ch => String(ch.done)).sort().pop() || '', lastN: (done.slice().sort((p, q) => String(q.done).localeCompare(String(p.done)))[0] || {}).n || '', t: a ? tName(a.t) : '' }; })
    .filter(r => r.total && (!only || r.c === only)).sort((a, b) => CLASSES.indexOf(a.c) - CLASSES.indexOf(b.c) || a.sb.localeCompare(b.sb));
}
const sylList = R => `<div class="sl">${R.map(r => { const p = ratio(r.done, r.total); return `<div><div class="row"><b>${esc(r.c)} · ${esc(r.sb)}</b><b>${p}%</b></div><div class="bar ${p < 30 ? 'bad' : p >= 80 ? 'ok' : ''}"><i style="width:${p}%"></i></div><div class="row"><span>${r.done}/${r.total} chapters${r.t ? ' · ' + esc(r.t) : ''}</span><span>${r.last ? 'last: ' + esc(r.lastN) + ' ' + dmy(r.last) : 'not started'}</span></div></div>`; }).join('')}</div>`;

// ---------------------------------------------------------------- Fees and Staff tabs
function feesBody() {
  const fs = S.fs || 'sum', nav = segs('fs', [['sum', 'Summary'], ['month', 'Month'], ['dues', 'Dues'], ['calls', 'Calls & messages']], 'sm');
  return nav + '<div style="height:12px"></div>' + ({ sum: overview, month: monthBody, dues: duesBody, calls: callBody }[fs])();
}
function staffBody() {
  const w = needSchool(); if (w) return w;
  const T = Object.keys(SC.teachers), ab = SC.staffAtt[SC.today], ym = SC.today.slice(0, 7), md = SC.sdates.filter(d => d.slice(0, 7) === ym);
  const absD = sum(md, d => (SC.staffAtt[d] || []).length), possible = md.length * T.length;
  const aL = ['Present', 'Absent'], E = SC.employees.filter(e => !/left/i.test(e.status)), cat = {}; E.forEach(e => { cat[e.category || 'Other'] = (cat[e.category || 'Other'] || 0) + 1; });
  const cl = Object.keys(cat);
  return `<div class="pg">${pieCard('Teachers today', pie('sAtt', aL, ab ? [T.length - ab.length, ab.length] : [0, 0], [P.green, P.pink], ab ? pc(ratio(T.length - ab.length, T.length)) : '—', ab ? 'present' : 'not marked'), legend(aL, ab ? [T.length - ab.length, ab.length] : [0, 0], [P.green, P.pink]))}
      ${pieCard('This month', pie('sMon', aL, md.length ? [possible - absD, absD] : [0, 0], [P.green, P.pink], md.length ? pc(ratio(possible - absD, possible)) : '—', md.length + ' days'), legend(['Present days', 'Absent days'], md.length ? [possible - absD, absD] : [0, 0], [P.green, P.pink]))}
      ${cl.length ? pieCard('Staff by type', pie('sCat', cl, cl.map(x => cat[x]), PAL, E.length, 'active staff'), legend(cl, cl.map(x => cat[x]), PAL)) : ''}</div><div style="height:12px"></div>${schTeach()}${md.length ? '<div style="height:12px"></div>' + staffRegister(ym) : ''}`;
}
function staffRegister(ym) {
  const T = Object.entries(SC.teachers), days = SC.sdates.filter(d => d.slice(0, 7) === ym);
  return card('Staff attendance register · ' + MN[+ym.slice(5, 7)], `<div class="tw reg"><table class="tbl ar"><tr><th class="nm">Teacher</th>${days.map(d => `<th>${d.slice(8, 10)}</th>`).join('')}<th>A</th></tr>${T.map(([t, v]) => `<tr><td class="nm">${esc(v.name)}</td>${days.map(d => (SC.staffAtt[d] || []).includes(t) ? '<td class="ca">A</td>' : '<td class="cp">P</td>').join('')}<td>${days.filter(d => (SC.staffAtt[d] || []).includes(t)).length}</td></tr>`).join('')}</table></div>`);
}

if ('serviceWorker' in navigator) navigator.serviceWorker.register('chairman-sw.js').catch(() => {});
$('app').innerHTML = '<div class="load"><i></i>Opening…</div>';
playIntro(false);
load();
