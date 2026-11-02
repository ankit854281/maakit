'use strict';
// Chairman App — the school's fee position as a phone app for the Chairman (school/chairman.php).
// Home: welcome + thought of the day, key figures and charts. Month: one month's fee and transport collection,
// what was expected and who is still to pay. Dues: overall dues, ageing and the student list. More: tags, classes,
// control (discounts and cancelled receipts), daily collection, WhatsApp summary.
// Data: feedash_api.php (fee cards + receipts of both ERPs + call log). Every figure follows the filters (tag, class).
const $ = id => document.getElementById(id);
const esc = v => String(v ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
const MORDER = [4, 5, 6, 7, 8, 9, 10, 11, 12, 1, 2, 3];
const MN = ['', 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
const M3 = m => MN[m].slice(0, 3);
const CLASSES = ['PG', 'LKG', 'UKG', '1st', '2nd', '3rd', '4th', '5th', '6th', '7th', '8th', '9th', '10th', '11th', '12th'];
const NOTAG = 'No tag';
const isTr = name => /transport/i.test(name);
const C = { navy: '#14213D', gold: '#C9A227', teal: '#2A9D8F', coral: '#E76F51', sky: '#4C8EDA', sand: '#E9C46A', grey: '#C9CED6', plum: '#7B5EA7' };
const PAL = [C.teal, C.sky, C.gold, C.coral, C.plum, '#3D5A80', '#8AB17D', '#B56576'];
// Short original thoughts for the welcome card, one a day.
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
let D = null, V = 'home', F = { tag: null, cls: '', m: 0, q: '', sort: 'due' }, CH = [], BUSY = false;

const rs = n => '₹' + Math.round(n || 0).toLocaleString('en-IN');
const rsShort = n => { n = Math.round(n || 0); const a = Math.abs(n); return a >= 1e7 ? '₹' + (n / 1e7).toFixed(2) + ' Cr' : a >= 1e5 ? '₹' + (n / 1e5).toFixed(a >= 1e6 ? 1 : 2) + ' L' : a >= 1e3 ? '₹' + (n / 1e3).toFixed(1) + 'k' : '₹' + n; };
const pct = (a, b) => b > 0 ? Math.round(a * 1000 / b) / 10 : 0;
const dmy = d => d ? d.slice(8, 10) + '-' + d.slice(5, 7) + '-' + d.slice(0, 4) : '';
const addDays = (d, n) => { const x = new Date(d + 'T00:00:00'); x.setDate(x.getDate() + n); return x.toISOString().slice(0, 10); };
const longSes = s => String(s || '').replace(/^(20\d\d)-(\d\d)$/, '$1-20$2');
const prevSes = s => { const y = +s.slice(0, 4) - 1; return y + '-' + String(y + 1).slice(2); };
const sum = (a, f) => a.reduce((t, x) => t + (f(x) || 0), 0);
function toast(t) { const el = $('chToast'); el.textContent = t; clearTimeout(toast.t); toast.t = setTimeout(() => { el.textContent = ''; }, 5000); }
async function api(path, body) {
  const r = await fetch(path, { method: body ? 'POST' : 'GET', headers: { 'Content-Type': 'application/json', 'X-School-Token': localStorage.getItem('sch_token') || '' }, ...(body ? { body: JSON.stringify(body) } : {}) });
  let d; try { d = await r.json(); } catch (e) { throw Error('The server did not answer properly. Please try again.'); }
  if (!r.ok || !d.ok) { const e = Error(d.error || 'Could not complete this.'); e.code = r.status; throw e; }
  return d;
}
async function load(ses) {
  $('chMain').innerHTML = '<div class="ch-load"><i></i>Loading the latest figures…</div>';
  try { D = await api('feedash_api.php?a=data' + (ses ? '&ses=' + encodeURIComponent(ses) : '')); if (!D.money) throw Object.assign(Error('This app is for the Chairman and the Principal.'), { code: 403 }); calc(); render(); }
  catch (e) { if (e.code === 401 || e.code === 428 || e.code === 403) return loginView(e.code === 428 ? 'new' : '', e.code === 403 ? e.message : ''); $('chMain').innerHTML = `<div class="ch-card"><p>${esc(e.message)}</p><button class="ch-btn" onclick="load()">Try again</button></div>`; }
}

// ---------------------------------------------------------------- figures
function monthIdx() { const y = +D.ses.slice(0, 4), t = D.today; if (t < y + '-04-01') return -1; if (t > (y + 1) + '-03-31') return 11; return MORDER.indexOf(+t.slice(5, 7)); }
const ymOf = (m, ses) => { const y = +(ses || D.ses).slice(0, 4); return (m >= 4 ? y : y + 1) + '-' + String(m).padStart(2, '0'); };
// 12 month rows per child {m, fd, fp, td, tp}: fee demand/paid and transport demand/paid. Fixed plan (tag with a fixed
// amount a month): the fee demand is that amount every month and the fee paid fills the months in order; transport as on the card.
function kidMonths(r) {
  const mon = MORDER.map(m => { const H = r.heads.filter(h => h.m === m), tr = H.filter(h => isTr(h.name)), fe = H.filter(h => !isTr(h.name));
    return { m, fd: sum(fe, h => h.amt), fp: sum(fe, h => h.paid), td: sum(tr, h => h.amt), tp: sum(tr, h => h.paid) }; });
  if (r.fixed > 0) { let left = sum(mon, x => x.fp); mon.forEach(x => { x.fd = r.fixed; x.fp = Math.min(r.fixed, Math.max(0, left)); left -= x.fp; }); }
  return mon;
}
function calc() {
  const idx = monthIdx(); D.idx = idx;
  if (!F.m) F.m = idx < 0 ? 4 : MORDER[Math.min(idx, 11)];
  const callsBy = {}; D.calls.forEach(c => (c.sids || []).forEach(s => (callsBy[s] = callsBy[s] || []).push(c)));
  D.all = D.dues.map(r => {
    const old = Math.max(0, r.oldDue - (r.oldPaid || 0)), mon = kidMonths(r), owe = x => Math.max(0, x.fd - x.fp) + Math.max(0, x.td - x.tp);
    const unpaid = mon.filter((x, i) => i <= idx && owe(x) > 0);
    return { ...r, tagName: r.tag || NOTAG, old, mon, annual: sum(mon, x => x.fd + x.td), paid: sum(mon, x => x.fp + x.tp), dueNow: sum(mon.filter((x, i) => i <= idx), owe) + old,
      yearBal: sum(mon, owe) + old, unpaid: unpaid.map(x => M3(x.m)), ageing: unpaid.length, last: (callsBy[r.sid] || [])[0] || null, phoneNo: String(r.fatherMobile || r.phone || '').replace(/\D/g, '').slice(-10) };
  });
  D.tags = [...new Set(D.all.map(k => k.tagName))].sort((a, b) => (a === NOTAG) - (b === NOTAG) || a.localeCompare(b));
  if (F.tag !== null && !D.tags.includes(F.tag)) F.tag = null;
  D.kids = D.all.filter(k => (F.tag === null || k.tagName === F.tag) && (!F.cls || k.cls === F.cls));
  const set = new Set(D.kids.map(k => k.sid)), filtered = F.tag !== null || F.cls;
  D.rc = D.receipts.filter(r => r.status !== 'Cancelled' && (!filtered || set.has(r.sid)));
  D.cx = D.receipts.filter(r => r.status === 'Cancelled' && (!filtered || set.has(r.sid)));
  const K = D.kids;
  D.t = { annual: sum(K, k => k.annual), paid: sum(K, k => k.paid), dueNow: sum(K, k => k.dueNow), yearBal: sum(K, k => k.yearBal), old: sum(K, k => k.old), def: K.filter(k => k.dueNow > 0) };
  D.t.notYet = Math.max(0, D.t.yearBal - D.t.dueNow);
}
const cashIn = rows => { const sp = rows.filter(r => r.split), ns = rows.filter(r => !r.split); return { n: rows.length, tr: sum(sp, r => r.tr), fee: sum(sp, r => r.received - r.tr), mixed: sum(ns, r => r.received), total: sum(rows, r => r.received) }; };
const inMonth = (rows, ym) => rows.filter(r => r.date.slice(0, 7) === ym);
function monthPos(kids, m) {
  const i = MORDER.indexOf(m), X = kids.map(k => ({ k, x: k.mon[i] }));
  const fd = sum(X, o => o.x.fd), fp = sum(X, o => o.x.fp), td = sum(X, o => o.x.td), tp = sum(X, o => o.x.tp);
  return { fd, fp, td, tp, exp: fd + td, got: fp + tp, due: fd + td - fp - tp, pend: X.filter(o => o.x.fd + o.x.td - o.x.fp - o.x.tp > 0).map(o => ({ ...o.k, monDue: o.x.fd + o.x.td - o.x.fp - o.x.tp })) };
}

// ---------------------------------------------------------------- frame
const ICON = {
  home: '<path d="M3 11l9-7 9 7v9a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z"/>',
  month: '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/>',
  dues: '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 3"/>',
  more: '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
  refresh: '<path d="M20 12a8 8 0 1 1-2.3-5.6M20 4v5h-5"/>',
  phone: '<path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2"/>'
};
const svg = (k, s = 22) => `<svg width="${s}" height="${s}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${ICON[k]}</svg>`;
function render() {
  CH.forEach(c => c.destroy()); CH = [];
  $('chTop').innerHTML = `<div class="ch-brand"><img src="img/snmsis-logo.png" alt="" width="34" height="34"><div><b>SNMSIS</b><small>Session ${esc(longSes(D.ses))}</small></div></div>
    <div class="ch-topr"><select aria-label="Session" onchange="F.m=0;load(this.value)">${D.sessions.map(s => `<option value="${esc(s)}" ${s === D.ses ? 'selected' : ''}>${esc(longSes(s))}</option>`).join('')}</select>
    <button class="ch-ic" aria-label="Refresh" onclick="load(D.ses)">${svg('refresh', 20)}</button></div>`;
  $('chNav').innerHTML = [['home', 'Home'], ['month', 'Month'], ['dues', 'Dues'], ['more', 'More']].map(([k, l]) => `<button class="${V === k ? 'on' : ''}" onclick="go('${k}')">${svg(k)}<span>${l}</span></button>`).join('');
  $('chMain').innerHTML = (V === 'home' ? homeView() : V === 'month' ? monthView() : V === 'dues' ? duesView() : moreView());
  requestAnimationFrame(drawCharts);
}
function go(v) { V = v; render(); window.scrollTo(0, 0); }
function filters() {
  const tagChips = D.tags.length > 1 || (D.tags[0] && D.tags[0] !== NOTAG);
  return `<div class="ch-filters">${tagChips ? `<div class="ch-chips"><button class="${F.tag === null ? 'on' : ''}" onclick="setF('tag',null)">All tags</button>${D.tags.map(t => `<button class="${F.tag === t ? 'on' : ''}" onclick='setF("tag",${JSON.stringify(t)})'>${esc(t)}</button>`).join('')}</div>` : ''}
    <select aria-label="Class" onchange="setF('cls',this.value)"><option value="">All classes</option>${CLASSES.filter(c => D.all.some(k => k.cls === c)).map(c => `<option ${F.cls === c ? 'selected' : ''}>${c}</option>`).join('')}</select></div>`;
}
function setF(k, v) { F[k] = v; calc(); render(); }
const kpi = (label, value, sub, tone) => `<div class="ch-kpi ${tone || ''}"><span>${esc(label)}</span><b>${value}</b>${sub ? `<small>${sub}</small>` : ''}</div>`;
const card = (title, body, extra) => `<section class="ch-card"><div class="ch-ch"><h3>${esc(title)}</h3>${extra || ''}</div>${body}</section>`;
const canvas = (id, h) => `<div class="ch-cv" style="height:${h || 220}px"><canvas id="${id}"></canvas></div>`;
let PLAN = [];
const chart = (id, cfg) => PLAN.push([id, cfg]);
function drawCharts() {
  if (!window.Chart) return;
  Chart.defaults.font.family = 'Inter, "Segoe UI", Roboto, Arial, sans-serif'; Chart.defaults.color = '#5b6576'; Chart.defaults.plugins.legend.labels.boxWidth = 12; Chart.defaults.animation.duration = 500; Chart.defaults.transitions.resize = { animation: { duration: 0 } };
  PLAN.forEach(([id, cfg]) => { const el = $(id); if (el) CH.push(new Chart(el, cfg)); }); PLAN = [];
}
const money = { callback: v => rsShort(v) };
const tipRs = { callbacks: { label: c => ` ${c.dataset.label ? c.dataset.label + ': ' : ''}${rs(c.parsed.y ?? c.parsed.x ?? c.parsed)}` } };
function barOpts(stacked, horizontal) {
  return { responsive: true, maintainAspectRatio: false, indexAxis: horizontal ? 'y' : 'x', plugins: { legend: { position: 'bottom' }, tooltip: tipRs },
    scales: { x: { stacked, grid: { display: !!horizontal }, ticks: horizontal ? money : {} }, y: { stacked, grid: { display: !horizontal }, ticks: horizontal ? {} : money, beginAtZero: true } } };
}

// ---------------------------------------------------------------- Home
function homeView() {
  PLAN = [];
  const t = D.t, ym = D.today.slice(0, 7), mon = cashIn(inMonth(D.rc, ym)), today = cashIn(D.rc.filter(r => r.date === D.today)), ses = cashIn(D.rc.filter(r => r.session === D.ses));
  const thought = THOUGHTS[Math.floor(new Date(D.today + 'T00:00:00').getTime() / 864e5) % THOUGHTS.length];
  const hr = new Date().getHours(), greet = hr < 12 ? 'Good morning' : hr < 17 ? 'Good afternoon' : 'Good evening';
  const target = t.paid + t.dueNow, coll = pct(t.paid, target);
  // month-wise collection this session (by receipt date), fee / transport / old receipts without a head split
  const months = MORDER.map(m => cashIn(inMonth(D.rc.filter(r => r.session === D.ses), ymOf(m))));
  const prev = MORDER.map(m => cashIn(inMonth(D.rc.filter(r => r.session === prevSes(D.ses)), ymOf(m, prevSes(D.ses)))).total);
  const hasPrev = prev.some(v => v > 0);
  // cumulative expected (fee card months) vs cumulative collected
  let ce = 0, cc = 0; const cumE = [], cumC = [];
  MORDER.forEach((m, i) => { ce += sum(D.kids, k => k.mon[i].fd + k.mon[i].td); cumE.push(ce); cc += months[i].total; cumC.push(i <= Math.max(D.idx, 0) ? cc : null); });
  const cls = CLASSES.map(c => ({ c, v: sum(D.kids.filter(k => k.cls === c), k => k.dueNow) })).filter(x => D.kids.some(k => k.cls === x.c));
  const modes = {}; D.rc.filter(r => r.session === D.ses).forEach(r => { modes[r.mode || 'Cash'] = (modes[r.mode || 'Cash'] || 0) + r.received; });
  const tagRows = D.tags.map(tg => { const K = D.all.filter(k => k.tagName === tg && (!F.cls || k.cls === F.cls)); return { tg, paid: sum(K, k => k.paid), due: sum(K, k => k.dueNow) }; }).filter(x => x.paid + x.due > 0);

  chart('cDonut', { type: 'doughnut', data: { labels: ['Collected', 'Due till now', 'Not due yet'], datasets: [{ data: [t.paid, t.dueNow, t.notYet], backgroundColor: [C.teal, C.coral, C.grey], borderWidth: 0 }] },
    options: { responsive: true, maintainAspectRatio: false, cutout: '72%', plugins: { legend: { position: 'bottom' }, tooltip: { callbacks: { label: c => ` ${c.label}: ${rs(c.parsed)}` } } } } });
  chart('cMonths', { type: 'bar', data: { labels: MORDER.map(M3), datasets: [
    { label: 'Fee', data: months.map(x => x.fee), backgroundColor: C.navy, borderRadius: 4 },
    { label: 'Transport', data: months.map(x => x.tr), backgroundColor: C.gold, borderRadius: 4 },
    ...(months.some(x => x.mixed) ? [{ label: 'Old receipts (not split)', data: months.map(x => x.mixed), backgroundColor: C.grey, borderRadius: 4 }] : [])] }, options: barOpts(true) });
  chart('cCum', { type: 'line', data: { labels: MORDER.map(M3), datasets: [
    { label: 'Expected', data: cumE, borderColor: C.gold, backgroundColor: 'rgba(201,162,39,.12)', fill: true, tension: .3, pointRadius: 2 },
    { label: 'Collected', data: cumC, borderColor: C.teal, backgroundColor: 'rgba(42,157,143,.15)', fill: true, tension: .3, pointRadius: 3 }] },
    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' }, tooltip: tipRs }, scales: { y: { ticks: money, beginAtZero: true }, x: { grid: { display: false } } } } });
  if (hasPrev) chart('cYoY', { type: 'bar', data: { labels: MORDER.map(M3), datasets: [
    { label: longSes(prevSes(D.ses)), data: prev, backgroundColor: C.grey, borderRadius: 4 }, { label: longSes(D.ses), data: months.map(x => x.total), backgroundColor: C.sky, borderRadius: 4 }] }, options: barOpts(false) });
  chart('cCls', { type: 'bar', data: { labels: cls.map(x => x.c), datasets: [{ label: 'Due till now', data: cls.map(x => x.v), backgroundColor: C.coral, borderRadius: 4 }] },
    options: { ...barOpts(false, true), onClick: (e, el) => { if (el[0]) { F.cls = cls[el[0].index].c; calc(); go('dues'); } }, plugins: { legend: { display: false }, tooltip: tipRs } } });
  chart('cMode', { type: 'doughnut', data: { labels: Object.keys(modes), datasets: [{ data: Object.values(modes), backgroundColor: PAL, borderWidth: 0 }] },
    options: { responsive: true, maintainAspectRatio: false, cutout: '60%', plugins: { legend: { position: 'bottom' }, tooltip: { callbacks: { label: c => ` ${c.label}: ${rs(c.parsed)}` } } } } });
  if (tagRows.length > 1) chart('cTag', { type: 'bar', data: { labels: tagRows.map(x => x.tg), datasets: [{ label: 'Collected', data: tagRows.map(x => x.paid), backgroundColor: C.teal, borderRadius: 4 }, { label: 'Due till now', data: tagRows.map(x => x.due), backgroundColor: C.coral, borderRadius: 4 }] }, options: barOpts(false) });

  const yoyNow = sum(prev.slice(0, Math.max(D.idx, 0) + 1), v => v), thisNow = sum(months.slice(0, Math.max(D.idx, 0) + 1), x => x.total);
  return `<section class="ch-hero"><p>${greet}</p><h1>Welcome, Chairman Sir</h1><blockquote>${esc(thought)}</blockquote></section>
    ${D.live ? '' : `<p class="ch-note">Fees for this session are still taken in the old ERP. Figures are as copied from it${D.asOn ? ' on ' + esc(D.asOn) : ''}.</p>`}
    ${filters()}
    <div class="ch-kpis">
      ${kpi('Total fee (session)', rsShort(t.annual), `${D.kids.length} students`)}
      ${kpi('Collected', rsShort(t.paid), `${coll}% of fee due till now`, 'good')}
      ${kpi('Overall dues', rsShort(t.yearBal), `${rsShort(t.notYet)} not due yet`, 'warn')}
      ${kpi('Due till now', rsShort(t.dueNow), `${t.def.length} students · old ${rsShort(t.old)}`, 'bad')}
      ${kpi('This month', rsShort(mon.total), `Fee ${rsShort(mon.fee + mon.mixed)} · Transport ${rsShort(mon.tr)}`)}
      ${kpi('Today', rsShort(today.total), `${today.n} receipt(s)`)}
    </div>
    ${card('Collection progress', `<div class="ch-donut">${canvas('cDonut', 230)}<div class="ch-dc"><b>${coll}%</b><span>collected</span></div></div>`)}
    ${card('Month-wise collection', canvas('cMonths', 240), `<span class="ch-pill">${rsShort(ses.total)} this session</span>`)}
    ${card('Expected vs collected', canvas('cCum', 230), '<span class="ch-pill">cumulative</span>')}
    ${hasPrev ? card('This year vs last year', canvas('cYoY', 240), `<span class="ch-pill ${thisNow >= yoyNow ? 'up' : 'down'}">${yoyNow ? (thisNow >= yoyNow ? '▲ ' : '▼ ') + Math.abs(pct(thisNow - yoyNow, yoyNow)) + '%' : ''}</span>`) : ''}
    ${card('Dues by class', canvas('cCls', Math.max(200, cls.length * 26)), '<span class="ch-hint">tap a bar</span>')}
    ${tagRows.length > 1 ? card('By tag', canvas('cTag', 230)) : ''}
    ${card('Payment mode (session)', canvas('cMode', 220))}
    <p class="ch-foot">Updated ${dmy(D.today)} · SN Mishra Singapore International School</p>`;
}

// ---------------------------------------------------------------- Month
function monthView() {
  PLAN = [];
  const m = F.m, ym = ymOf(m), rows = inMonth(D.rc, ym), cin = cashIn(rows), P = monthPos(D.kids, m);
  const days = new Date(+ym.slice(0, 4), +ym.slice(5, 7), 0).getDate(), byDay = [...Array(days)].map((_, i) => sum(rows.filter(r => +r.date.slice(8, 10) === i + 1), r => r.received));
  const cls = CLASSES.filter(c => D.kids.some(k => k.cls === c)).map(c => ({ c, p: monthPos(D.kids.filter(k => k.cls === c), m) }));
  chart('mDay', { type: 'bar', data: { labels: byDay.map((_, i) => i + 1), datasets: [{ label: 'Collected', data: byDay, backgroundColor: C.navy, borderRadius: 3 }] }, options: { ...barOpts(false), plugins: { legend: { display: false }, tooltip: tipRs } } });
  chart('mSplit', { type: 'doughnut', data: { labels: ['Fee', 'Transport', ...(cin.mixed ? ['Old receipts (not split)'] : [])], datasets: [{ data: [cin.fee, cin.tr, ...(cin.mixed ? [cin.mixed] : [])], backgroundColor: [C.navy, C.gold, C.grey], borderWidth: 0 }] },
    options: { responsive: true, maintainAspectRatio: false, cutout: '62%', plugins: { legend: { position: 'bottom' }, tooltip: { callbacks: { label: c => ` ${c.label}: ${rs(c.parsed)}` } } } } });
  chart('mCls', { type: 'bar', data: { labels: cls.map(x => x.c), datasets: [{ label: 'Expected', data: cls.map(x => x.p.exp), backgroundColor: C.sand, borderRadius: 4 }, { label: 'Received', data: cls.map(x => x.p.got), backgroundColor: C.teal, borderRadius: 4 }] }, options: barOpts(false) });
  const pend = P.pend.sort((a, b) => b.monDue - a.monDue);
  return `<div class="ch-mscroll">${MORDER.map(x => `<button class="${x === m ? 'on' : ''} ${MORDER.indexOf(x) > D.idx ? 'future' : ''}" onclick="F.m=${x};render()">${M3(x)}<small>${ymOf(x).slice(2, 4)}</small></button>`).join('')}</div>
    ${filters()}
    <h2 class="ch-h2">${MN[m]} ${ym.slice(0, 4)}</h2>
    <div class="ch-kpis">
      ${kpi('Fee collected', rsShort(cin.fee), cin.mixed ? `+ ${rsShort(cin.mixed)} old receipts not split` : 'school fee heads', 'good')}
      ${kpi('Transport collected', rsShort(cin.tr), 'transport heads', 'gold')}
      ${kpi('Total collected', rsShort(cin.total), `${cin.n} receipt(s)`)}
      ${kpi('Expected this month', rsShort(P.exp), `Fee ${rsShort(P.fd)} · Transport ${rsShort(P.td)}`)}
      ${kpi('Received against it', rsShort(P.got), `${pct(P.got, P.exp)}% of the month`, 'good')}
      ${kpi('Students to follow up', String(pend.length), `${rsShort(P.due)} pending`, 'bad')}
    </div>
    ${card('Fee and transport for ' + MN[m], `<table class="ch-tbl"><tr><th></th><th>Expected</th><th>Received</th><th>Pending</th></tr>
      <tr><td>School fee</td><td>${rs(P.fd)}</td><td>${rs(P.fp)}</td><td class="bad">${rs(P.fd - P.fp)}</td></tr>
      <tr><td>Transport</td><td>${rs(P.td)}</td><td>${rs(P.tp)}</td><td class="bad">${rs(P.td - P.tp)}</td></tr>
      <tr class="tot"><td>Total</td><td>${rs(P.exp)}</td><td>${rs(P.got)}</td><td class="bad">${rs(P.due)}</td></tr></table>
      <p class="ch-small">Expected and received are the fee-card amounts of ${MN[m]} (fixed-plan students at their fixed amount). Collected above is the money received on receipts dated in ${MN[m]}.</p>`)}
    ${card('Collected: fee vs transport', canvas('mSplit', 210))}
    ${card('Day-wise collection', canvas('mDay', 200))}
    ${card('Expected vs received by class', canvas('mCls', 240))}
    ${card(`Pending for ${MN[m]} · ${pend.length} students`, people(pend, true))}`;
}

// ---------------------------------------------------------------- Dues
function duesView() {
  PLAN = [];
  const t = D.t, q = F.q.trim().toLowerCase();
  const buckets = [['1 month', k => k.ageing === 1], ['2–3 months', k => k.ageing >= 2 && k.ageing <= 3], ['4+ months', k => k.ageing >= 4], ['Old due only', k => k.ageing === 0 && k.old > 0]];
  const B = buckets.map(([l, f]) => { const K = t.def.filter(f); return { l, n: K.length, v: sum(K, k => k.dueNow) }; });
  chart('dAge', { type: 'doughnut', data: { labels: B.map(b => `${b.l} (${b.n})`), datasets: [{ data: B.map(b => b.v), backgroundColor: [C.sand, C.coral, '#B23A48', C.plum], borderWidth: 0 }] },
    options: { responsive: true, maintainAspectRatio: false, cutout: '60%', plugins: { legend: { position: 'bottom' }, tooltip: { callbacks: { label: c => ` ${c.label}: ${rs(c.parsed)}` } } } } });
  let list = t.def.filter(k => !q || [k.name, k.father, k.adm, k.phoneNo, k.tagName].some(v => String(v || '').toLowerCase().includes(q)));
  list = list.sort(F.sort === 'name' ? (a, b) => a.name.localeCompare(b.name) : F.sort === 'months' ? (a, b) => b.ageing - a.ageing || b.dueNow - a.dueNow : (a, b) => b.dueNow - a.dueNow);
  return `${filters()}
    <div class="ch-kpis">
      ${kpi('Overall dues', rsShort(t.yearBal), 'whole session', 'warn')}
      ${kpi('Due till now', rsShort(t.dueNow), `${t.def.length} students`, 'bad')}
      ${kpi('Old dues', rsShort(t.old), 'from earlier sessions')}
      ${kpi('Not due yet', rsShort(t.notYet), 'coming months')}
    </div>
    ${card('How old are the dues', canvas('dAge', 220))}
    <section class="ch-card"><div class="ch-search"><input id="chQ" value="${esc(F.q)}" placeholder="Search name, father, mobile, tag" oninput="F.q=this.value;render();const q=$('chQ');q.focus();q.setSelectionRange(q.value.length,q.value.length)">
      <select aria-label="Sort" onchange="F.sort=this.value;render()"><option value="due" ${F.sort === 'due' ? 'selected' : ''}>Highest due</option><option value="months" ${F.sort === 'months' ? 'selected' : ''}>Most months</option><option value="name" ${F.sort === 'name' ? 'selected' : ''}>Name</option></select></div>
      <p class="ch-small">${list.length} students · ${rs(sum(list, k => k.dueNow))}</p>${people(list.slice(0, 200), false)}${list.length > 200 ? '<p class="ch-small">Showing the first 200 — use search or a class filter.</p>' : ''}</section>`;
}
function people(rows, month) {
  if (!rows.length) return '<p class="ch-empty">Nobody here. Well done.</p>';
  return `<div class="ch-people">${rows.map(k => `<div class="ch-person"><div class="ch-av">${esc((k.name || '?').split(/\s+/).map(w => w[0]).slice(0, 2).join('').toUpperCase())}</div>
    <div class="ch-pi"><b>${esc(k.name)}</b><small>${esc(k.cls)}${k.section ? '-' + esc(k.section) : ''} · ${esc(k.father || '')}${k.tag ? ` · <em>${esc(k.tag)}</em>` : ''}</small>
      <small>${k.unpaid.length ? 'Unpaid: ' + esc(k.unpaid.join(', ')) : ''}${k.old ? ' · old ' + rs(k.old) : ''}${k.last ? ' · last call: ' + esc(k.last.outcome) : ''}</small></div>
    <div class="ch-pr"><b>${rs(month ? k.monDue : k.dueNow)}</b>${k.phoneNo ? `<a class="ch-call" href="tel:+91${k.phoneNo}" aria-label="Call">${svg('phone', 16)}</a>` : ''}</div></div>`).join('')}</div>`;
}

// ---------------------------------------------------------------- More
function moreView() {
  PLAN = [];
  const ym = D.today.slice(0, 7), disc = D.rc.filter(r => r.discount > 0 && r.session === D.ses), cx = D.cx.filter(r => r.session === D.ses);
  const discM = inMonth(disc, ym), cxM = inMonth(cx, ym);
  const tags = D.tags.map(tg => { const K = D.all.filter(k => k.tagName === tg && (!F.cls || k.cls === F.cls)); return { tg, n: K.length, annual: sum(K, k => k.annual), paid: sum(K, k => k.paid), due: sum(K, k => k.dueNow) }; }).filter(x => x.n);
  const cls = CLASSES.map(c => { const K = D.kids.filter(k => k.cls === c); return { c, n: K.length, annual: sum(K, k => k.annual), paid: sum(K, k => k.paid), due: sum(K, k => k.dueNow) }; }).filter(x => x.n);
  const days = []; for (let i = 29; i >= 0; i--) { const d = addDays(D.today, -i); days.push({ d, v: sum(D.rc.filter(r => r.date === d), r => r.received) }); }
  chart('oDays', { type: 'line', data: { labels: days.map(x => x.d.slice(8) + ' ' + M3(+x.d.slice(5, 7))), datasets: [{ label: 'Collected', data: days.map(x => x.v), borderColor: C.navy, backgroundColor: 'rgba(20,33,61,.08)', fill: true, tension: .3, pointRadius: 2 }] },
    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false }, tooltip: tipRs }, scales: { y: { ticks: money, beginAtZero: true }, x: { grid: { display: false }, ticks: { maxTicksLimit: 8 } } } } });
  const T = (head, rows, foot) => `<div class="ch-tw"><table class="ch-tbl"><tr>${head.map(h => `<th>${h}</th>`).join('')}</tr>${rows.join('')}${foot ? `<tr class="tot">${foot.map(c => `<td>${c}</td>`).join('')}</tr>` : ''}</table></div>`;
  return `${filters()}
    <div class="ch-actions"><button class="ch-btn gold" onclick="share()">Share summary on WhatsApp</button><button class="ch-btn" onclick="window.print()">Print</button></div>
    ${card('Control · this month', `<div class="ch-kpis two">${kpi('Discounts given', rsShort(sum(discM, r => r.discount)), `${discM.length} receipt(s)`)}${kpi('Receipts cancelled', String(cxM.length), rsShort(sum(cxM, r => r.received)), cxM.length ? 'bad' : 'good')}</div>
      ${discM.length || cxM.length ? T(['Date', 'Receipt', 'Student', 'Amount', 'By'], [...discM.map(r => `<tr><td>${dmy(r.date)}</td><td>${esc(r.no)}</td><td>${esc(r.name)}</td><td>Discount ${rs(r.discount)}</td><td>${esc(r.user)}</td></tr>`), ...cxM.map(r => `<tr class="bad"><td>${dmy(r.date)}</td><td>${esc(r.no)}</td><td>${esc(r.name)}</td><td>Cancelled ${rs(r.received)}</td><td>${esc(r.user)}</td></tr>`)]) : '<p class="ch-empty">No discount or cancelled receipt this month.</p>'}`)}
    ${card('Last 30 days', canvas('oDays', 200))}
    ${tags.length > 1 || (tags[0] && tags[0].tg !== NOTAG) ? card('Tag-wise', T(['Tag', 'Students', 'Session fee', 'Collected', 'Due till now'], tags.map(x => `<tr><td><a href="#" onclick='setF("tag",${JSON.stringify(x.tg)});go("home");return false'>${esc(x.tg)}</a></td><td>${x.n}</td><td>${rs(x.annual)}</td><td>${rs(x.paid)}</td><td class="bad">${rs(x.due)}</td></tr>`),
      ['Total', sum(tags, x => x.n), rs(sum(tags, x => x.annual)), rs(sum(tags, x => x.paid)), rs(sum(tags, x => x.due))])) : ''}
    ${card('Class-wise', T(['Class', 'Students', 'Session fee', 'Collected', 'Due till now', '%'], cls.map(x => `<tr><td>${esc(x.c)}</td><td>${x.n}</td><td>${rs(x.annual)}</td><td>${rs(x.paid)}</td><td class="bad">${rs(x.due)}</td><td>${pct(x.paid, x.paid + x.due)}%</td></tr>`),
      ['Total', sum(cls, x => x.n), rs(sum(cls, x => x.annual)), rs(sum(cls, x => x.paid)), rs(sum(cls, x => x.due)), pct(sum(cls, x => x.paid), sum(cls, x => x.paid + x.due)) + '%']))}
    <div class="ch-actions"><button class="ch-btn ghost" onclick="logout()">Log out</button></div>`;
}
function share() {
  const t = D.t, m = +D.today.slice(5, 7), c = cashIn(inMonth(D.rc, D.today.slice(0, 7))), p = monthPos(D.kids, m), td = cashIn(D.rc.filter(r => r.date === D.today));
  const txt = `*SN Mishra Singapore International School*\nFee summary — ${dmy(D.today)}${F.tag !== null ? '\nTag: ' + F.tag : ''}${F.cls ? '\nClass: ' + F.cls : ''}\n\n` +
    `Today: ${rs(td.total)} (${td.n} receipts)\n${MN[m]} collected: ${rs(c.total)} (Fee ${rs(c.fee + c.mixed)} + Transport ${rs(c.tr)})\n` +
    `${MN[m]} expected: ${rs(p.exp)} · received ${rs(p.got)} · pending ${rs(p.due)} (${p.pend.length} students)\n\n` +
    `Session fee: ${rs(t.annual)}\nCollected: ${rs(t.paid)} (${pct(t.paid, t.paid + t.dueNow)}%)\nDue till now: ${rs(t.dueNow)} (${t.def.length} students)\nOverall dues: ${rs(t.yearBal)}`;
  window.open('https://wa.me/?text=' + encodeURIComponent(txt), '_blank', 'noopener');
}

// ---------------------------------------------------------------- login (the school's own Chairman / Principal PIN, kept for 30 days)
function loginView(step, msg) {
  $('chTop').innerHTML = ''; $('chNav').innerHTML = '';
  let who = 'chairman'; try { who = localStorage.getItem('ch_role') || 'chairman'; } catch (e) {}
  $('chMain').innerHTML = `<div class="ch-login"><img src="img/snmsis-logo.png" alt="" width="84" height="84"><h1>Welcome, Chairman Sir</h1><p>SN Mishra Singapore International School</p>
    ${step === 'new' ? `<p class="ch-small">First login: please choose your own PIN (4–8 digits, not 1234).</p><input id="lgOld" type="password" inputmode="numeric" placeholder="Current PIN" autocomplete="current-password"><input id="lgNew" type="password" inputmode="numeric" placeholder="New PIN" autocomplete="new-password"><button class="ch-btn gold" onclick="setPin()">Save PIN</button>`
    : `<div class="ch-seg"><button class="${who === 'chairman' ? 'on' : ''}" onclick="try{localStorage.setItem('ch_role','chairman')}catch(e){};loginView()">Chairman</button><button class="${who === 'principal' ? 'on' : ''}" onclick="try{localStorage.setItem('ch_role','principal')}catch(e){};loginView()">Principal</button></div>
      <input id="lgPin" type="password" inputmode="numeric" placeholder="Enter PIN" autocomplete="current-password" onkeydown="if(event.key==='Enter')login()"><button class="ch-btn gold" onclick="login()">Open</button>`}
    <p class="ch-err">${esc(msg || '')}</p></div>`;
  const f = $('lgPin') || $('lgOld'); if (f) f.focus();
}
async function login() {
  let role = 'chairman'; try { role = localStorage.getItem('ch_role') || 'chairman'; } catch (e) {}
  try { const d = await api('api.php?a=login', { role, pin: $('lgPin').value }); localStorage.setItem('sch_token', d.token); if (d.mustChange) return loginView('new'); load(); }
  catch (e) { loginView('', e.message); }
}
async function setPin() { try { await api('api.php?a=setpin', { old: $('lgOld').value, pin: $('lgNew').value }); toast('PIN saved.'); load(); } catch (e) { loginView('new', e.message); } }
async function logout() { try { await api('api.php?a=logout', {}); } catch (e) {} try { localStorage.removeItem('sch_token'); } catch (e) {} D = null; loginView(); }
if ('serviceWorker' in navigator) navigator.serviceWorker.register('chairman-sw.js').catch(() => {});
load();
