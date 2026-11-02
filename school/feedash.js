'use strict';
// Fee Control Room — one page that answers the Chairman's questions about fees:
//   How much came in today / this month / this session?  How much is still due, and in which class?
//   Who has to be called today, what did they promise, and did they keep the promise?
// Data: feedash_api.php (fee cards + receipts of both ERPs + the call log). Calls are saved with office_api.php?a=call.
const $ = id => document.getElementById(id);
const esc = v => String(v ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
const QS = new URLSearchParams(location.search);
if (QS.get('look') === 'classic') document.documentElement.classList.add('classic');
const MORDER = [4, 5, 6, 7, 8, 9, 10, 11, 12, 1, 2, 3];
const MN = ['', 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
const CLASSES = ['PG', 'LKG', 'UKG', '1st', '2nd', '3rd', '4th', '5th', '6th', '7th', '8th', '9th', '10th', '11th', '12th'];
let D = null, TAB = QS.get('tab') || 'sum', F = { cls: '', list: 'today', q: '', min: '' }, OPEN = '', BUSY = false;

const rs = n => '₹' + Math.round(n || 0).toLocaleString('en-IN');
const pct = (a, b) => b > 0 ? Math.round(a * 1000 / b) / 10 : 0;
const dmy = d => d ? d.slice(8, 10) + '-' + d.slice(5, 7) + '-' + d.slice(0, 4) : '';
const addDays = (d, n) => { const x = new Date(d + 'T00:00:00'); x.setDate(x.getDate() + n); return x.toISOString().slice(0, 10); };
const longSes = s => String(s || '').replace(/^(20\d\d)-(\d\d)$/, '$1-20$2');
const sum = (a, f) => a.reduce((t, x) => t + (f(x) || 0), 0);
function toast(t) { const el = $('fdToast'); el.textContent = t; clearTimeout(toast.t); toast.t = setTimeout(() => { el.textContent = ''; }, 6000); }
async function api(path, body) {
  const r = await fetch(path, { method: body ? 'POST' : 'GET', headers: { 'Content-Type': 'application/json', 'X-School-Token': localStorage.getItem('sch_token') || '' }, ...(body ? { body: JSON.stringify(body) } : {}) });
  let d; try { d = await r.json(); } catch (e) { throw Error('The server did not answer properly. Please try again.'); }
  if (!r.ok || !d.ok) throw Error(d.error || 'Could not complete this.');
  return d;
}
async function load(ses) {
  try { D = await api('feedash_api.php?a=data' + (ses ? '&ses=' + encodeURIComponent(ses) : '')); calc(); render(); }
  catch (e) { $('fdRoot').innerHTML = `<div class="fd"><h2>Fee Control Room</h2><p class="fd-note">${esc(e.message)}</p></div>`; }
}

// ---------------------------------------------------------------- the numbers
// "Due till now" = every fee head of the months up to this month that is not paid, plus old due.
// Next months' fee is not due yet, so it is shown apart as "rest of the year".
function monthIdx() {
  const y = +D.ses.slice(0, 4), t = D.today;
  if (t < y + '-04-01') return -1; if (t > (y + 1) + '-03-31') return 11;
  return MORDER.indexOf(+t.slice(5, 7));
}
function calc() {
  const idx = monthIdx(), today = D.today;
  const callsBy = {};
  D.calls.forEach(c => (c.sids || []).forEach(s => (callsBy[s] = callsBy[s] || []).push(c)));
  const lastRc = {};
  D.receipts.forEach(r => { if (r.status !== 'Cancelled' && r.sid && (!lastRc[r.sid] || r.date > lastRc[r.sid].date)) lastRc[r.sid] = r; });
  D.kids = D.dues.map(r => {
    const old = Math.max(0, r.oldDue - (r.oldPaid || 0));
    const annual = sum(r.heads, h => h.amt), paid = sum(r.heads, h => h.paid);
    const dueNow = sum(r.heads.filter(h => MORDER.indexOf(h.m) <= idx), h => Math.max(0, h.amt - h.paid)) + old;
    const yearBal = sum(r.heads, h => Math.max(0, h.amt - h.paid)) + old;
    const unpaidMonths = MORDER.filter((m, i) => i <= idx && r.heads.some(h => h.m === m && h.amt - h.paid > 0)).map(m => MN[m].slice(0, 3));
    const calls = callsBy[r.sid] || [], last = calls[0] || null;
    const lr = lastRc[r.sid], lastPaid = lr ? { date: lr.date, amt: lr.received } : r.lastDate ? { date: r.lastDate, amt: r.lastAmt } : null;
    let promise = '', broken = false;
    if (last && last.promise && last.outcome !== 'Wrong number') { promise = last.promise; broken = promise < today && !(lastPaid && lastPaid.date >= last.date); }
    return { ...r, old, annual, paid, dueNow, yearBal, unpaidMonths, calls, last, lastPaid, promise, broken, phoneNo: String(r.fatherMobile || r.phone || '').replace(/\D/g, '').slice(-10) };
  });
  const ok = D.receipts.filter(r => r.status !== 'Cancelled');
  const ym = today.slice(0, 7), yday = addDays(today, -1);
  D.t = {
    today: ok.filter(r => r.date === today), yday: ok.filter(r => r.date === yday), month: ok.filter(r => r.date.slice(0, 7) === ym), ses: ok.filter(r => r.session === D.ses),
    annual: sum(D.kids, k => k.annual), paid: sum(D.kids, k => k.paid), dueNow: sum(D.kids, k => k.dueNow), yearBal: sum(D.kids, k => k.yearBal), old: sum(D.kids, k => k.old),
    defaulters: D.kids.filter(k => k.dueNow > 0)
  };
  D.t.target = D.t.paid + D.t.dueNow;   // what should have come in by now
}

// ---------------------------------------------------------------- drawing
function render() {
  if (!D) return;
  const tabs = [['sum', 'Summary'], ['call', `Fee Calling (${D.t.defaulters.length})`], ['cls', 'Class-wise'], ...(D.money ? [['day', 'Daily Collection']] : [])];
  if (!D.money && (TAB === 'sum' || TAB === 'day')) TAB = 'call';
  const body = TAB === 'call' ? callHtml() : TAB === 'cls' ? clsHtml() : TAB === 'day' ? dayHtml() : sumHtml();
  const keep = document.activeElement && document.activeElement.id === 'fdQ';
  $('fdRoot').innerHTML = `<div class="fd">
    <div class="fd-top"><div><h2>Fee Control Room</h2><p class="fd-sub">${esc(D.school)} · Session ${esc(longSes(D.ses))} · as on ${dmy(D.today)}</p></div>
      <div class="fd-act np"><select onchange="load(this.value)">${D.sessions.map(s => `<option value="${esc(s)}" ${s === D.ses ? 'selected' : ''}>${esc(longSes(s))}</option>`).join('')}</select>
      ${D.money ? `<button onclick="shareSummary()">WhatsApp Summary</button>` : ''}<button onclick="window.print()">Print</button><button onclick="load(D.ses)">Refresh</button></div></div>
    ${D.live ? '' : `<p class="fd-note">Fees for ${esc(longSes(D.ses))} are still taken in the old ERP. Fee cards here are as copied from the old ERP${D.asOn ? ' on <b>' + esc(D.asOn) + '</b>' : ''}; receipts copied after that are counted in collection.</p>`}
    <div class="fd-tabs np">${tabs.map(([k, l]) => `<button class="${TAB === k ? 'on' : ''}" onclick="TAB='${k}';render();scrollTo(0,0)">${esc(l)}</button>`).join('')}</div>
    ${body}</div>`;
  if (keep) { const q = $('fdQ'); q.focus(); q.setSelectionRange(q.value.length, q.value.length); }
}
const tile = (cls, big, label, small) => `<div class="fd-tile ${cls}"><b>${big}</b><span>${esc(label)}</span>${small ? `<small>${small}</small>` : ''}</div>`;
function bars(items, fmt) {
  // Plain HTML bars with the amount on top, so it reads the same on a phone, on screen and on paper.
  const max = Math.max(1, ...items.map(x => x.v)), short = v => v >= 100000 ? (v / 100000).toFixed(v >= 1000000 ? 0 : 1) + 'L' : v >= 1000 ? Math.round(v / 1000) + 'k' : v ? String(Math.round(v)) : '';
  return `<div class="fd-bars">${items.map(x => `<div class="fd-bar" title="${esc(x.l)}: ${esc(fmt(x.v))}"><em>${esc(short(x.v))}</em><i class="${x.c || ''}" style="height:${Math.round(x.v / max * 100)}%"></i><span>${esc(x.s || x.l)}</span></div>`).join('')}</div>`;
}
function sumHtml() {
  const t = D.t, coll = pct(t.paid, t.target);
  const prom = D.kids.filter(k => k.promise === D.today), broken = D.kids.filter(k => k.broken), callsToday = D.calls.filter(c => c.date === D.today);
  const notCalled = t.defaulters.filter(k => !k.last || k.last.date < addDays(D.today, -6));
  const modes = {}; t.month.forEach(r => { modes[r.mode || 'Cash'] = (modes[r.mode || 'Cash'] || 0) + r.received; });
  const users = {}; t.today.forEach(r => { users[r.user || '-'] = (users[r.user || '-'] || 0) + r.received; });
  const y = +D.ses.slice(0, 4), mon = MORDER.map(m => { const k = (m >= 4 ? y : y + 1) + '-' + String(m).padStart(2, '0'); return { l: MN[m] + ' ' + (m >= 4 ? y : y + 1), s: MN[m].slice(0, 3), v: sum(t.ses.filter(r => r.date.slice(0, 7) === k), r => r.received) }; });
  const cls = clsRows().filter(c => c.n);
  return `<div class="fd-tiles">
      ${tile('t1', rs(sum(t.today, r => r.received)), "Today's Collection", `${t.today.length} receipt(s) · yesterday ${rs(sum(t.yday, r => r.received))}`)}
      ${tile('t2', rs(sum(t.month, r => r.received)), 'This Month', `${t.month.length} receipt(s)`)}
      ${tile('t3', rs(sum(t.ses, r => r.received)), 'Collected this Session', `${t.ses.length} receipt(s)`)}
      ${tile('t4', rs(t.dueNow), 'Due till Now', `${t.defaulters.length} student(s) · old due ${rs(t.old)}`)}
    </div>
    <div class="fd-row">
      <div class="fd-card"><h3>Collection vs Due till now</h3><div class="fd-big">${coll}%</div><div class="fd-meter"><i style="width:${Math.min(100, coll)}%"></i></div>
        <table class="fd-kv"><tr><td>Fee of the session (all months)</td><td>${rs(t.annual)}</td></tr><tr><td>Paid + discount so far</td><td>${rs(t.paid)}</td></tr>
        <tr><td>Due till this month</td><td class="red">${rs(t.dueNow)}</td></tr><tr><td>Rest of the year (not due yet)</td><td>${rs(Math.max(0, t.yearBal - t.dueNow))}</td></tr></table></div>
      <div class="fd-card"><h3>Fee Calling today</h3><table class="fd-kv">
        <tr><td>Calls made today</td><td>${callsToday.length}</td></tr>
        <tr><td>Promised to pay today</td><td>${prom.length} · ${rs(sum(prom, k => k.dueNow))}</td></tr>
        <tr><td>Promise broken (date passed, not paid)</td><td class="red">${broken.length} · ${rs(sum(broken, k => k.dueNow))}</td></tr>
        <tr><td>Not called in 7 days</td><td>${notCalled.length}</td></tr></table>
        <button class="np" onclick="TAB='call';F.list='today';render()">Open calling list</button></div>
    </div>
    <div class="fd-card"><h3>Month-wise collection · ${esc(longSes(D.ses))}</h3>${bars(mon, rs)}</div>
    <div class="fd-row">
      <div class="fd-card"><h3>Due till now by class</h3>${bars(cls.map(c => ({ l: c.cls, v: c.dueNow, c: 'red' })), rs)}</div>
      <div class="fd-card"><h3>This month by payment mode</h3><table class="fd-kv">${Object.entries(modes).sort((a, b) => b[1] - a[1]).map(([m, v]) => `<tr><td>${esc(m)}</td><td>${rs(v)}</td></tr>`).join('') || '<tr><td>No receipt this month yet</td><td></td></tr>'}</table>
        <h3 style="margin-top:12px">Today by staff</h3><table class="fd-kv">${Object.entries(users).map(([m, v]) => `<tr><td>${esc(m)}</td><td>${rs(v)}</td></tr>`).join('') || '<tr><td>No receipt today yet</td><td></td></tr>'}</table></div>
    </div>
    <div class="fd-card"><h3>Top 10 dues</h3>${kidTable(t.defaulters.slice().sort((a, b) => b.dueNow - a.dueNow).slice(0, 10), false)}</div>`;
}
function clsRows() {
  return CLASSES.map(c => { const k = D.kids.filter(x => x.cls === c); return { cls: c, n: k.length, annual: sum(k, x => x.annual), paid: sum(k, x => x.paid), dueNow: sum(k, x => x.dueNow), yearBal: sum(k, x => x.yearBal), def: k.filter(x => x.dueNow > 0).length }; });
}
function clsHtml() {
  const R = clsRows().filter(c => c.n), T = { n: sum(R, c => c.n), annual: sum(R, c => c.annual), paid: sum(R, c => c.paid), dueNow: sum(R, c => c.dueNow), yearBal: sum(R, c => c.yearBal), def: sum(R, c => c.def) };
  const row = (c, tot) => `<tr class="${tot ? 'tot' : ''}"><td>${esc(tot ? 'Total' : c.cls)}</td><td>${c.n}</td><td>${rs(c.annual)}</td><td>${rs(c.paid)}</td><td class="red">${rs(c.dueNow)}</td><td>${c.def}</td><td>${rs(c.yearBal)}</td>
    <td><div class="fd-meter sm"><i style="width:${Math.min(100, pct(c.paid, c.paid + c.dueNow))}%"></i></div>${pct(c.paid, c.paid + c.dueNow)}%</td>${tot ? '<td></td>' : `<td class="np"><button onclick="TAB='call';F.cls='${esc(c.cls)}';F.list='all';render()">Call list</button></td>`}</tr>`;
  return `<div class="fd-card"><h3>Class-wise fee position</h3><div class="fd-tw"><table class="fd-t"><tr><th>Class</th><th>Students</th><th>Session Fee</th><th>Paid + Disc</th><th>Due till Now</th><th>Students with Due</th><th>Year Balance</th><th>Collected</th><th class="np"></th></tr>
    ${R.map(c => row(c)).join('')}${row(T, true)}</table></div></div>`;
}
function dayHtml() {
  const days = []; for (let i = 29; i >= 0; i--) { const d = addDays(D.today, -i); const x = D.receipts.filter(r => r.status !== 'Cancelled' && r.date === d); days.push({ d, n: x.length, v: sum(x, r => r.received), cash: sum(x.filter(r => (r.mode || 'Cash') === 'Cash'), r => r.received) }); }
  return `<div class="fd-card"><h3>Last 30 days</h3>${bars(days.map(x => ({ l: dmy(x.d), s: x.d.slice(8), v: x.v })), rs)}</div>
    <div class="fd-card"><div class="fd-tw"><table class="fd-t"><tr><th>Date</th><th>Receipts</th><th>Cash</th><th>Bank / UPI / Cheque</th><th>Total</th></tr>
    ${days.slice().reverse().filter(x => x.n).map(x => `<tr><td>${dmy(x.d)}</td><td>${x.n}</td><td>${rs(x.cash)}</td><td>${rs(x.v - x.cash)}</td><td><b>${rs(x.v)}</b></td></tr>`).join('') || '<tr><td colspan="5">No receipt in the last 30 days.</td></tr>'}
    <tr class="tot"><td>Total</td><td>${sum(days, x => x.n)}</td><td>${rs(sum(days, x => x.cash))}</td><td>${rs(sum(days, x => x.v - x.cash))}</td><td>${rs(sum(days, x => x.v))}</td></tr></table></div></div>`;
}
// ---- Fee Calling
function callList() {
  const t = D.today, q = F.q.trim().toLowerCase(), min = parseFloat(F.min) || 0;
  let k = D.t.defaulters.filter(x => (!F.cls || x.cls === F.cls) && x.dueNow >= min && (!q || [x.name, x.father, x.adm, x.phoneNo].some(v => String(v || '').toLowerCase().includes(q))));
  const L = {
    today: k.filter(x => x.promise && x.promise <= t && !(x.lastPaid && x.last && x.lastPaid.date >= x.last.date)),
    fresh: k.filter(x => !x.last || x.last.date < addDays(t, -6)),
    broken: k.filter(x => x.broken),
    all: k
  };
  return { L, rows: (L[F.list] || L.all).slice().sort((a, b) => b.dueNow - a.dueNow) };
}
function callHtml() {
  const { L, rows } = callList();
  const lists = [['today', 'Follow-up due today', L.today.length], ['fresh', 'Not called in 7 days', L.fresh.length], ['broken', 'Promise broken', L.broken.length], ['all', 'All with due', L.all.length]];
  return `<div class="fd-card np"><div class="fd-tabs sm">${lists.map(([k, l, n]) => `<button class="${F.list === k ? 'on' : ''}" onclick="F.list='${k}';render()">${esc(l)} (${n})</button>`).join('')}</div>
    <div class="fd-filt"><label>Class <select onchange="F.cls=this.value;render()"><option value="">All</option>${CLASSES.map(c => `<option ${F.cls === c ? 'selected' : ''}>${c}</option>`).join('')}</select></label>
    <label>Due at least <input value="${esc(F.min)}" inputmode="numeric" onchange="F.min=this.value;render()" style="width:80px"></label>
    <label>Search <input id="fdQ" value="${esc(F.q)}" oninput="F.q=this.value;render()" placeholder="Name / father / mobile / scholar no"></label>
    <span><b>${rows.length}</b> student(s) · <b class="red">${rs(sum(rows, x => x.dueNow))}</b></span></div></div>
    <div class="fd-card">${kidTable(rows, true)}</div>`;
}
function kidTable(rows, act) {
  if (!rows.length) return '<p class="fd-note">Nobody in this list.</p>';
  return `<div class="fd-tw"><table class="fd-t"><tr><th>#</th><th>Student</th><th>Class</th><th>Father / Mobile</th><th>Due till Now</th><th>Months</th><th>Last Paid</th><th>Last Call</th>${act ? '<th class="np"></th>' : ''}</tr>
    ${rows.map((k, i) => `<tr class="${k.broken ? 'brk' : ''}"><td>${i + 1}</td><td class="l"><b>${esc(k.name)}</b><br><small>${esc(k.adm)}</small></td><td>${esc(k.cls)}${k.section ? '-' + esc(k.section) : ''}</td>
      <td class="l">${esc(k.father)}<br><small>${esc(k.phoneNo || 'no mobile')}</small></td><td class="red"><b>${rs(k.dueNow)}</b>${k.old ? `<br><small>old ${rs(k.old)}</small>` : ''}</td><td>${esc(k.unpaidMonths.join(', '))}</td>
      <td>${k.lastPaid ? dmy(k.lastPaid.date) + (D.money ? '<br><small>' + rs(k.lastPaid.amt) + '</small>' : '') : '<small>never</small>'}</td>
      <td class="l">${k.last ? `${esc(k.last.outcome)}${k.promise ? '<br><small>date ' + dmy(k.promise) + (k.broken ? ' · <b>broken</b>' : '') + '</small>' : ''}<br><small>${dmy(k.last.date)} · ${esc(k.last.by || '')}</small>` : '<small>not called</small>'}</td>
      ${act ? `<td class="np fd-btns">${k.phoneNo ? `<a class="b-call" href="tel:+91${k.phoneNo}">Call</a><a class="b-wa" target="_blank" rel="noopener" href="https://wa.me/91${k.phoneNo}?text=${encodeURIComponent(remind(k))}">WhatsApp</a>` : ''}${D.canCall ? `<button onclick="OPEN=OPEN==='${esc(k.sid)}'?'':'${esc(k.sid)}';render()">${OPEN === k.sid ? 'Close' : 'Save Call'}</button>` : ''}</td>` : ''}</tr>
      ${act && OPEN === k.sid ? `<tr class="np"><td colspan="9"><div class="fd-save"><label>What happened <select id="cO">${D.outcomes.map(o => `<option>${esc(o)}</option>`).join('')}</select></label>
        <label>Date they gave <input id="cD" type="date" min="${D.today}" value="${addDays(D.today, 3)}"></label><label>Note <input id="cN" maxlength="200" placeholder="optional"></label>
        <button onclick="saveCall('${esc(k.sid)}')" ${BUSY ? 'disabled' : ''}>Save</button></div>
        ${k.calls.length ? `<div class="fd-hist">${k.calls.slice(0, 6).map(c => `${dmy(c.date)} · ${esc(c.outcome)}${c.promise ? ' · ' + dmy(c.promise) : ''} · ${esc(c.by || '')}${c.note ? ' · ' + esc(c.note) : ''}`).join('<br>')}</div>` : ''}</td></tr>` : ''}`).join('')}</table></div>`;
}
function remind(k) {
  return `नमस्ते 🙏 ${D.school} से।\n${k.name} (कक्षा ${k.cls}) की फीस ₹${Math.round(k.dueNow).toLocaleString('en-IN')} बाकी है${k.unpaidMonths.length ? ' (' + k.unpaidMonths.join(', ') + ')' : ''}।\nकृपया जल्द विद्यालय कार्यालय में जमा करें और रसीद ज़रूर लें। धन्यवाद।`;
}
async function saveCall(sid) {
  if (BUSY) return; BUSY = true;
  const body = { sid, outcome: $('cO').value, promise: $('cD').value, note: $('cN').value };
  if (!['Promised to pay', 'Call back later', 'Will visit school'].includes(body.outcome)) body.promise = '';
  try { const d = await api('office_api.php?a=call', body); D.calls.unshift(d.call); OPEN = ''; calc(); toast('Call saved.'); }
  catch (e) { toast(e.message); }
  BUSY = false; render();
}
function shareSummary() {
  const t = D.t, broken = D.kids.filter(k => k.broken);
  const txt = `*${D.school}* — Fee Report ${dmy(D.today)}\n\n` +
    `Aaj ki fee: ${rs(sum(t.today, r => r.received))} (${t.today.length} rasid)\n` +
    `Is mahine: ${rs(sum(t.month, r => r.received))}\n` +
    `Session ${longSes(D.ses)} me ab tak: ${rs(sum(t.ses, r => r.received))}\n\n` +
    `Abhi tak baaki: ${rs(t.dueNow)} (${t.defaulters.length} bachche)\n` +
    `Collection: ${pct(t.paid, t.target)}%\n\n` +
    `Aaj calls: ${D.calls.filter(c => c.date === D.today).length} · Vaada toota: ${broken.length}\n\n` +
    `Sabse zyada baaki (class): ` + clsRows().filter(c => c.dueNow > 0).sort((a, b) => b.dueNow - a.dueNow).slice(0, 3).map(c => `${c.cls} ${rs(c.dueNow)}`).join(', ');
  window.open('https://wa.me/?text=' + encodeURIComponent(txt), '_blank', 'noopener');
}
load(QS.get('session') ? QS.get('session').replace(/^(20\d\d)-20(\d\d)$/, '$1-$2') : '');
