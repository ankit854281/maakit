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
  { name: 'Overview', head: 'linear-gradient(180deg,#6366F1 0%,#4338CA 100%)', a: '#4338CA', a2: '#6366F1', soft: '#E0E2FF', tint: '#F1F2FF', sh: 'rgba(67,56,202,.28)', page: 'linear-gradient(180deg,#E6E7FF 0%,rgba(247,247,249,0) 100%)', icon: 't_over' },
  { name: 'Month', head: 'linear-gradient(180deg,#FF7D32 0%,#F2600F 100%)', a: '#E8590C', a2: '#FF7D32', soft: '#FFE6D6', tint: '#FFF4EC', sh: 'rgba(242,96,15,.28)', page: 'linear-gradient(180deg,#FFEBDD 0%,rgba(247,247,249,0) 100%)', icon: 't_month' },
  { name: 'Dues', head: 'linear-gradient(180deg,#E3417F 0%,#C81F64 100%)', a: '#C81F64', a2: '#E3417F', soft: '#FADBE7', tint: '#FDF0F5', sh: 'rgba(200,31,100,.26)', page: 'linear-gradient(180deg,#FBE1EB 0%,rgba(247,247,249,0) 100%)', icon: 't_dues' },
  { name: 'Calling', head: 'linear-gradient(180deg,#22B573 0%,#14915A 100%)', a: '#14915A', a2: '#22B573', soft: '#D5F2E3', tint: '#EEFAF3', sh: 'rgba(20,145,90,.26)', page: 'linear-gradient(180deg,#DDF4E8 0%,rgba(247,247,249,0) 100%)', icon: 't_call' },
  { name: 'Reports', head: 'linear-gradient(180deg,#14B8C4 0%,#0B8A99 100%)', a: '#0B8A99', a2: '#14B8C4', soft: '#CFF3F6', tint: '#ECFAFB', sh: 'rgba(11,138,153,.26)', page: 'linear-gradient(180deg,#D9F5F7 0%,rgba(247,247,249,0) 100%)', icon: 't_rep' }
];
// Small drawings in the Trial App's flat style
const ILL = {
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
  search: '<circle cx="11" cy="11" r="7"/><path d="M20 20l-4-4"/>', down: '<path d="M6 9l6 6 6-6"/>', phone: '<path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2"/>',
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
  const body = S.q.trim() ? searchBody() : [overview, monthBody, duesBody, callBody, repBody][S.tab]();
  $('app').innerHTML = `<div class="head">${layers}
      <div class="loc"><div class="w"><small>${greet}</small><b>Welcome, Chairman Sir</b></div><button class="sesb" onclick="sheet('ses')">${esc(longSes(D.ses))} ${ic('down', 12)}</button><button class="av" aria-label="Menu" onclick="sheet('me')"><img src="${LOGO}" alt=""></button></div>
      <div class="tabs" role="tablist"><span class="fold" style="transform:translateX(${S.tab * 100}%)"></span>${tabs}</div>
      <div class="strip"><label class="sbox">${ic('search', 16)}<input id="q" value="${esc(S.q)}" placeholder="Search a student, father or mobile" oninput="S.q=this.value;render();refocus('q')"></label>
        <button class="tagb" onclick="sheet('filter')">${esc(S.tag === null ? 'All tags' : S.tag)}${S.cls ? ' · ' + esc(S.cls) : ''} ${ic('down', 12)}</button></div></div>
    <div class="scroll" id="sc"><div class="pagebg" style="background:${TH[S.tab].page}"></div><div class="body">${body}</div></div>
    ${S.tab === 0 && !S.q ? `<button class="cartbar" onclick="share()">${ic('share', 18)}<b>Share today's summary</b><em>WhatsApp</em></button>` : ''}
    ${S.sheet ? `<div class="ov" onclick="sheet(null)"></div><div class="sheet" role="dialog"><div class="grab"></div>${sheetBody()}</div>` : ''}`;
  requestAnimationFrame(drawCharts);
}
function refocus(id) { const e = $(id); if (e) { e.focus(); e.setSelectionRange(e.value.length, e.value.length); } }
function setTab(i) { S.tab = i; S.q = ''; render(); const sc = $('sc'); if (sc) sc.scrollTop = 0; }
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
    <div class="r"><b>${rs(opt.month ? x.monDue : x.dueNow)}</b><div class="acts">${x.phoneNo ? `<a class="c" href="tel:+91${x.phoneNo}" aria-label="Call">${ic('phone', 15)}</a><a class="w" target="_blank" rel="noopener" href="https://wa.me/91${x.phoneNo}?text=${encodeURIComponent(fillMsg(TPL.reminder[S.lang], x))}" onclick="logMsg(['${esc(x.sid)}'],'Fee reminder',fillMsg(TPL.reminder[S.lang], D.all.find(z=>z.sid==='${esc(x.sid)}')))" aria-label="WhatsApp reminder">${ic('wa', 15)}</a>` : ''}${opt.calls && D.canCall ? `<button class="s" onclick="S.open='${esc(x.sid)}';sheet('call')" aria-label="Save call result">${ic('note', 15)}</button>` : ''}</div></div></div>`).join('')}</div>`;
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
    options: { ...bars(true, true), onClick: (e, el) => { if (el[0]) { S.cls = cls[el[0].index].c; calc(); setTab(2); } } } });
  if (tagRows.length > 1) chart('cTag', { type: 'bar', data: { labels: tagRows.map(x => x.tg), datasets: [{ label: 'Collected', data: tagRows.map(x => x.paid), backgroundColor: P.green, borderRadius: 5 }, { label: 'Due till now', data: tagRows.map(x => x.due), backgroundColor: P.pink, borderRadius: 5 }] }, options: bars(false) });
  chart('cMode', { type: 'doughnut', data: { labels: Object.keys(modes), datasets: [{ data: Object.values(modes), backgroundColor: PAL, borderWidth: 0 }] }, options: { responsive: true, maintainAspectRatio: false, cutout: '62%', plugins: { legend: { position: 'bottom' }, tooltip: tipPie } } });

  const top = t.def.slice().sort((a, b) => b.dueNow - a.dueNow).slice(0, 10);
  return `<div class="promo">${ill('bulb')}<div><b>THOUGHT OF THE DAY</b><span>${esc(thought)}</span></div></div>
    ${installBox()}
    <div class="kp">
      <div class="k big"><span>Fee cleared this session (paid + discount)${S.tag !== null || S.cls ? ' · filtered' : ''}</span><b>${rs(t.paid)}</b><div class="meter"><i style="width:${Math.min(100, coll)}%"></i></div><small>${coll}% of the fee due till now · Total fee ${rsS(t.annual)} · Money received on receipts this session ${rsS(ses.total)}</small></div>
      ${k('Today', rsS(today.total), `${today.n} receipt(s)`, 'ok')}
      ${k('This month', rsS(mon.total), `Fee ${rsS(mon.fee + mon.mixed)} · Transport ${rsS(mon.tr)}`, 'ac', 'setTab(1)')}
      ${k('Due till now', rsS(t.dueNow), `${t.def.length} students · old ${rsS(t.old)}`, 'bad', 'setTab(2)')}
      ${k('Overall dues', rsS(t.yearBal), `${rsS(t.notYet)} not due yet`, 'warn', 'setTab(2)')}
    </div>
    <div class="row"><h2>Classes</h2><span class="mut">tap to filter</span></div>
    <div class="hscroll"><button class="cat${!S.cls ? ' on' : ''}" onclick="setF('cls','')"><span>All</span>Classes</button>${cls.map(x => `<button class="cat${S.cls === x.c ? ' on' : ''}" onclick="setF('cls','${x.c}')"><span>${esc(x.c)}<em>${pct(x.paid, x.paid + x.due)}%</em></span>${rsS(x.due)} due</button>`).join('')}</div>
    ${card('Fee Calling today', `<div class="stat"><button class="st" onclick="S.list='today';setTab(3)"><b>${callsToday}</b><span>Calls made today</span></button><button class="st" onclick="S.list='today';setTab(3)"><b>${L.today.length}</b><span>Follow-ups due</span></button>
      <button class="st bad" onclick="S.list='broken';setTab(3)"><b>${L.broken.length}</b><span>Promises broken · ${rsS(sum(L.broken, x => x.dueNow))}</span></button><button class="st" onclick="S.list='fresh';setTab(3)"><b>${L.fresh.length}</b><span>Not called in 7 days</span></button>
      <button class="st bad" onclick="setTab(2)"><b>${D.kids.filter(x => x.zero && x.dueNow > 0).length}</b><span>Paid ₹0 this session</span></button><button class="st ok" onclick="S.cmode='log';setTab(3)"><b>${sum(D.msgs.filter(m => m.date === D.today), m => (m.sids || []).length)}</b><span>Messages sent today</span></button></div>
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
      ${k('Students to work on', String(pend.length), 'tap to call them', 'warn', `S.list='all';setTab(3)`)}
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
function msgTo(aud, tag) { S.aud = aud; S.tpl = AUD_TPL[aud]; S.mtext = null; MQ = null; S.cmode = 'msg'; if (tag !== undefined) S.tag = tag; calc(); setTab(3); }
// ---------------------------------------------------------------- Calling
function callBody() {
  const seg = `<div class="seg">${[['calls', 'Calls'], ['msg', 'Send messages'], ['log', 'Sent messages']].map(([k2, l]) => `<button class="${S.cmode === k2 ? 'on' : ''}" onclick="S.cmode='${k2}';MQ=null;render()">${l}</button>`).join('')}</div>`;
  if (S.cmode === 'msg') return seg + msgBody();
  if (S.cmode === 'log') return seg + msgLogBody();
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
  ['receipts', '🧾', 'Receipt register'], ['ageing', '⏳', 'Dues by months'], ['old', '📜', 'Old dues'], ['discount', '✂️', 'Discounts & cancels']];
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
  const sortSel = T === 'students' ? `<select onchange="setRF('sort',this.value)" style="border:1px solid #E7E7EE;border-radius:10px;height:34px;background:#fff;font-size:12px"><option value="due" ${RF.sort === 'due' ? 'selected' : ''}>Highest due</option><option value="class" ${RF.sort === 'class' ? 'selected' : ''}>Class</option><option value="name" ${RF.sort === 'name' ? 'selected' : ''}>Name</option></select>` : '';
  return `<div class="rtypes">${RT.map(([key, icon, l]) => `<button class="rt${T === key ? ' on' : ''}" onclick="setRF('type','${key}')"><i>${icon}</i>${esc(l)}</button>`).join('')}</div>
    ${fchips()}
    <div class="kp">${sumv.map(([l, v]) => k(l, String(v), '', '')).join('')}</div>
    <section class="card"><h3>${esc(RT.find(x => x[0] === T)[2])}${sortSel}</h3>${body}
      <div class="rbtns" style="margin-top:12px"><button class="cta" onclick="downloadRep()">Download (Excel)</button><button class="cta lt" onclick="copyRep()">Copy</button></div>
      <p class="note">Session ${esc(longSes(D.ses))}. "Due (to month)" counts fee months up to ${MN[RF.upto ? +RF.upto : (D.idx < 0 ? 4 : MORDER[Math.min(D.idx, 11)])]} plus old dues.</p></section>`;
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
function searchBody() {
  const q = S.q.trim().toLowerCase(), d = q.replace(/\D/g, '');
  const rows = D.all.filter(x => [x.name, x.father, x.adm, x.tagName].some(v => String(v || '').toLowerCase().includes(q)) || (d.length >= 4 && x.phoneNo.includes(d))).slice(0, 60);
  return card(`Search · ${rows.length} found`, rows.length ? `${people(rows, { calls: true })}<p class="note">Paid this session: ${rows.length === 1 ? rs(rows[0].paid) + ' of ' + rs(rows[0].annual) : 'tap a student’s call or WhatsApp button'}.</p>` : '<p class="empty">No student matches.</p>');
}

// ---------------------------------------------------------------- sheets
// ---- one student's full record: since when what is due, what was paid, every receipt, calls and messages
function openStu(sid) { S.open = sid; sheet('stu'); }
function stuSheet() {
  const x = D.all.find(z => z.sid === S.open); if (!x) return '';
  const rows = x.mon.map((y, i) => { const due = Math.max(0, y.fd - y.fp) + Math.max(0, y.td - y.tp), st = y.fd + y.td === 0 ? '—' : due === 0 ? 'Paid' : i > D.idx ? 'Not due yet' : y.fp + y.tp > 0 ? 'Part paid' : 'Unpaid';
    return `<tr class="${st === 'Unpaid' || st === 'Part paid' ? 'hl' : ''}"><td>${MN[y.m].slice(0, 3)} ${ymOf(y.m).slice(2, 4)}</td><td>${rs(y.fd)}</td><td>${rs(y.td)}</td><td>${rs(y.fp + y.tp)}</td><td class="${due && i <= D.idx ? 'red' : ''}">${rs(due)}</td><td>${st}</td></tr>`; }).join('');
  return `<h2>${esc(x.name)}</h2><p class="mut" style="margin:-6px 0 10px">${esc(x.cls)}${x.section ? '-' + esc(x.section) : ''} · ${esc(x.father || '')}${x.adm ? ' · Adm ' + esc(x.adm) : ''}${x.tag ? ` · <span class="tag">${esc(x.tag)}</span>` : ''}</p>
    <div class="kp">${k('Due till now', rs(x.dueNow), x.since ? 'since ' + esc(x.since) : 'nothing due', x.dueNow ? 'bad' : 'ok')}${k('Paid + discount', rs(x.paid), 'this session on the fee card', 'ok')}
      ${k('Session fee', rs(x.annual), `Overall due ${rs(x.yearBal)}`)}${k('Old dues', rs(x.old), 'earlier sessions', x.old ? 'warn' : '')}</div>
    <div class="acts" style="margin:12px 0;gap:10px">${x.phoneNo ? `<a class="cta" style="flex:1;height:42px" href="tel:+91${x.phoneNo}">${ic('phone', 16)} Call</a><a class="cta" style="flex:1;height:42px;background:#25D366" target="_blank" rel="noopener" href="https://wa.me/91${x.phoneNo}?text=${encodeURIComponent(remind(x))}" onclick="logMsg(['${esc(x.sid)}'],'Fee reminder',remind(D.all.find(z=>z.sid==='${esc(x.sid)}')))">${ic('wa', 16)} Reminder</a>` : '<p class="note">No mobile number on record.</p>'}</div>
    <label class="lbl">MONTH BY MONTH · ${esc(longSes(D.ses))}</label><div class="tw"><table class="tbl"><tr><th>Month</th><th>Fee</th><th>Transport</th><th>Paid</th><th>Due</th><th>Status</th></tr>${rows}
      <tr class="tot"><td>Total</td><td>${rs(sum(x.mon, y => y.fd))}</td><td>${rs(sum(x.mon, y => y.td))}</td><td>${rs(x.paid)}</td><td>${rs(x.yearBal - x.old)}</td><td></td></tr></table></div>
    <label class="lbl" style="margin-top:14px">RECEIPTS · ${x.rcs.length}</label>${x.rcs.length ? `<div class="tw"><table class="tbl"><tr><th>Date</th><th>Receipt</th><th>Session</th><th>Months</th><th>Amount</th><th>Discount</th><th>Mode</th></tr>${x.rcs.map(r => `<tr><td>${dmy(r.date)}</td><td>${esc(r.no)}</td><td>${esc(longSes(r.session))}</td><td>${esc(r.months || '')}</td><td>${rs(r.received)}</td><td>${rs(r.discount)}</td><td>${esc(r.mode)}</td></tr>`).join('')}
      <tr class="tot"><td>Total</td><td></td><td></td><td></td><td>${rs(sum(x.rcs, r => r.received))}</td><td>${rs(sum(x.rcs, r => r.discount))}</td><td></td></tr></table></div>` : '<p class="note">No receipt on record.</p>'}
    <label class="lbl" style="margin-top:14px">CALLS AND MESSAGES</label>${x.calls.length || x.msgs.length ? `<p class="note">${[...x.calls.map(c => ({ d: c.date, t: 'Call: ' + c.outcome + (c.promise ? ' · date ' + dmy(c.promise) : '') + ' · ' + (c.by || '') })), ...x.msgs.map(m => ({ d: m.date, t: 'Message: ' + m.kind + ' · ' + (m.by || '') }))].sort((a, b) => b.d.localeCompare(a.d)).slice(0, 12).map(e => dmy(e.d) + ' · ' + esc(e.t)).join('<br>')}</p>` : '<p class="note">No call or message yet.</p>'}`;
}
function sheetBody() {
  if (S.sheet === 'stu') return stuSheet();
  if (S.sheet === 'ses') return `<h2>Session</h2>${D.sessions.map(s => `<button class="opt${s === D.ses ? ' on' : ''}" onclick="S.sheet=null;S.m=0;load('${esc(s)}')">${esc(longSes(s))}</button>`).join('')}`;
  if (S.sheet === 'filter') return `<h2>Filter</h2><label class="lbl">TAG</label>${[null, ...D.tags].map(t => `<button class="opt${S.tag === t ? ' on' : ''}" onclick="S.sheet=null;setF('tag',${esc(JSON.stringify(t))})">${esc(t === null ? 'All tags' : t)} <small>${(t === null ? D.all : D.all.filter(x => x.tagName === t)).length} students</small></button>`).join('')}
    <label class="lbl" style="margin-top:14px">CLASS</label><div class="inp"><select onchange="S.sheet=null;setF('cls',this.value)"><option value="">All classes</option>${CLASSES.filter(c => D.all.some(x => x.cls === c)).map(c => `<option ${S.cls === c ? 'selected' : ''}>${c}</option>`).join('')}</select></div>`;
  if (S.sheet === 'call') {
    const x = D.all.find(z => z.sid === S.open); if (!x) return '';
    return `<h2>Call result · ${esc(x.name)}</h2><p class="mut" style="margin:-4px 0 12px">${esc(x.cls)} · ${esc(x.father || '')} · due ${rs(x.dueNow)}</p>
      <label class="lbl">WHAT HAPPENED</label><div class="inp"><select id="cO">${D.outcomes.map(o => `<option>${esc(o)}</option>`).join('')}</select></div>
      <label class="lbl">DATE THEY GAVE</label><div class="inp"><input id="cD" type="date" min="${D.today}" value="${addDays(D.today, 3)}"></div>
      <label class="lbl">NOTE</label><div class="inp"><input id="cN" maxlength="200" placeholder="Optional"></div>
      <button class="cta" onclick="saveCall()" ${BUSY ? 'disabled' : ''}>Save call result</button>
      ${x.calls.length ? `<p class="note">${x.calls.slice(0, 5).map(c => `${dmy(c.date)} · ${esc(c.outcome)}${c.promise ? ' · ' + dmy(c.promise) : ''} · ${esc(c.by || '')}`).join('<br>')}</p>` : ''}`;
  }
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
async function saveCall() {
  if (BUSY) return; BUSY = true;
  const body = { sid: S.open, outcome: $('cO').value, promise: $('cD').value, note: $('cN').value };
  if (!['Promised to pay', 'Call back later', 'Will visit school'].includes(body.outcome)) body.promise = '';
  try { const d = await api('office_api.php?a=call', body); D.calls.unshift(d.call); S.sheet = null; calc(); toast('Call saved.'); } catch (e) { toast(e.message); }
  BUSY = false; render();
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

if ('serviceWorker' in navigator) navigator.serviceWorker.register('chairman-sw.js').catch(() => {});
$('app').innerHTML = '<div class="load"><i></i>Opening…</div>';
playIntro(false);
load();
