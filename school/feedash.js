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
let D = null, TAB = QS.get('tab') || '', F = { cls: '', list: 'today', q: '', min: '', tag: null, tq: '', tcls: '' }, OPEN = '', TEDIT = '', BUSY = false, MSEL = 0;
const isTr = name => /transport/i.test(name);
const NOTAG = '(No tag)';

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
  if (!r.ok || !d.ok) { const e = Error(d.error || 'Could not complete this.'); e.code = r.status; throw e; }
  return d;
}
async function load(ses) {
  try {
    D = await api('feedash_api.php?a=data' + (ses ? '&ses=' + encodeURIComponent(ses) : ''));
    if (!TAB) TAB = D.role === 'reception' ? 'call' : 'mon';
    calc(); render();
  } catch (e) {
    $('fdRoot').innerHTML = `<div class="fd"><h2>Fee Control Room</h2><p class="fd-note">${esc(e.message)}</p></div>`;
  }
}

// ---------------------------------------------------------------- the numbers
// "Due till now" = every fee head of the months up to this month that is not paid, plus old due.
// Next months' fee is not due yet, so it is shown apart as "rest of the year".
function monthIdx() {
  const y = +D.ses.slice(0, 4), t = D.today;
  if (t < y + '-04-01') return -1; if (t > (y + 1) + '-03-31') return 11;
  return MORDER.indexOf(+t.slice(5, 7));
}
// Each child gets 12 month rows {m, fd, fp, td, tp}: fee demand / paid and transport demand / paid of that month.
// Fixed plan (tag with a fixed amount a month, e.g. FD): the fee demand of every month is that amount, the fee paid on
// the card fills the months in order; transport stays as on the fee card ("transport plus").
function kidMonths(r) {
  const months = MORDER.map(m => { const H = r.heads.filter(h => h.m === m), tr = H.filter(h => isTr(h.name)), fe = H.filter(h => !isTr(h.name));
    return { m, fd: sum(fe, h => h.amt), fp: sum(fe, h => h.paid), td: sum(tr, h => h.amt), tp: sum(tr, h => h.paid) }; });
  if (r.fixed > 0) {
    let left = sum(months, x => x.fp);
    months.forEach(x => { x.fd = r.fixed; x.fp = Math.min(r.fixed, Math.max(0, left)); left -= x.fp; });
  }
  return months;
}
function calc() {
  const idx = monthIdx(), today = D.today;
  if (!MSEL) MSEL = idx < 0 ? 4 : MORDER[Math.min(idx, 11)];
  const callsBy = {};
  D.calls.forEach(c => (c.sids || []).forEach(s => (callsBy[s] = callsBy[s] || []).push(c)));
  const lastRc = {};
  D.receipts.forEach(r => { if (r.status !== 'Cancelled' && r.sid && (!lastRc[r.sid] || r.date > lastRc[r.sid].date)) lastRc[r.sid] = r; });
  D.all = D.dues.map(r => {
    const old = Math.max(0, r.oldDue - (r.oldPaid || 0)), mon = kidMonths(r);
    const owe = x => Math.max(0, x.fd - x.fp) + Math.max(0, x.td - x.tp);
    const annual = sum(mon, x => x.fd + x.td), paid = sum(mon, x => x.fp + x.tp);
    const dueNow = sum(mon.filter((x, i) => i <= idx), owe) + old, yearBal = sum(mon, owe) + old;
    const unpaidMonths = mon.filter((x, i) => i <= idx && owe(x) > 0).map(x => MN[x.m].slice(0, 3));
    const calls = callsBy[r.sid] || [], last = calls[0] || null;
    const lr = lastRc[r.sid], lastPaid = lr ? { date: lr.date, amt: lr.received } : r.lastDate ? { date: r.lastDate, amt: r.lastAmt } : null;
    let promise = '', broken = false;
    if (last && last.promise && last.outcome !== 'Wrong number') { promise = last.promise; broken = promise < today && !(lastPaid && lastPaid.date >= last.date); }
    return { ...r, tagName: r.tag || NOTAG, old, mon, annual, paid, dueNow, yearBal, unpaidMonths, calls, last, lastPaid, promise, broken, phoneNo: String(r.fatherMobile || r.phone || '').replace(/\D/g, '').slice(-10) };
  });
  D.tags = [...new Set(D.all.map(k => k.tagName))].sort((a, b) => (a === NOTAG) - (b === NOTAG) || a.localeCompare(b));
  if (F.tag !== null && !D.tags.includes(F.tag)) F.tag = null;
  D.kids = F.tag === null ? D.all : D.all.filter(k => k.tagName === F.tag);
  const inTag = new Set(D.kids.map(k => k.sid)), tagOf = Object.fromEntries(D.all.map(k => [k.sid, k.tagName]));
  D.tagOf = sid => tagOf[sid] || NOTAG;
  const ok = D.receipts.filter(r => r.status !== 'Cancelled' && (F.tag === null || inTag.has(r.sid)));
  D.ok = ok;
  const ym = today.slice(0, 7), yday = addDays(today, -1);
  D.t = {
    today: ok.filter(r => r.date === today), yday: ok.filter(r => r.date === yday), month: ok.filter(r => r.date.slice(0, 7) === ym), ses: ok.filter(r => r.session === D.ses),
    annual: sum(D.kids, k => k.annual), paid: sum(D.kids, k => k.paid), dueNow: sum(D.kids, k => k.dueNow), yearBal: sum(D.kids, k => k.yearBal), old: sum(D.kids, k => k.old),
    defaulters: D.kids.filter(k => k.dueNow > 0)
  };
  D.t.target = D.t.paid + D.t.dueNow;   // what should have come in by now
}
const ymOf = m => (m >= 4 ? +D.ses.slice(0, 4) : +D.ses.slice(0, 4) + 1) + '-' + String(m).padStart(2, '0');
// Money that came in during a calendar month, split into fee and transport by the receipt's printed lines.
function cashIn(rows) {
  const sp = rows.filter(r => r.split), ns = rows.filter(r => !r.split);
  return { n: rows.length, tr: sum(sp, r => r.tr), fee: sum(sp, r => r.received - r.tr), mixed: sum(ns, r => r.received), total: sum(rows, r => r.received) };
}
function monthPos(kids, m) {
  const i = MORDER.indexOf(m), X = kids.map(k => ({ k, x: k.mon[i] }));
  const fd = sum(X, o => o.x.fd), fp = sum(X, o => o.x.fp), td = sum(X, o => o.x.td), tp = sum(X, o => o.x.tp);
  const pend = X.filter(o => o.x.fd + o.x.td - o.x.fp - o.x.tp > 0);
  return { fd, fp, td, tp, due: fd + td - fp - tp, pend: pend.map(o => ({ ...o.k, monDue: o.x.fd + o.x.td - o.x.fp - o.x.tp })) };
}

// ---------------------------------------------------------------- drawing
function render() {
  if (!D) return;
  const tabs = [...(D.money ? [['mon', 'Month Collection'], ['sum', 'Summary'], ['tag', 'Tags']] : []), ['call', `Fee Calling (${D.t.defaulters.length})`], ['cls', 'Class-wise'], ...(D.money ? [['day', 'Daily']] : [])];
  if (!D.money && ['sum', 'day', 'mon', 'tag'].includes(TAB)) TAB = 'call';
  const body = TAB === 'call' ? callHtml() : TAB === 'cls' ? clsHtml() : TAB === 'day' ? dayHtml() : TAB === 'mon' ? monHtml() : TAB === 'tag' ? tagHtml() : sumHtml();
  const chips = D.tags.length > 1 || (D.tags[0] && D.tags[0] !== NOTAG) ? `<div class="fd-chips np"><span>Tag:</span><button class="${F.tag === null ? 'on' : ''}" onclick="setTagF(null)">All (${D.all.length})</button>${D.tags.map(t => `<button class="${F.tag === t ? 'on' : ''}" onclick='setTagF(${JSON.stringify(t)})'>${esc(t)} (${D.all.filter(k => k.tagName === t).length})</button>`).join('')}</div>` : '';
  const keep = document.activeElement && document.activeElement.id === 'fdQ';
  $('fdRoot').innerHTML = `<div class="fd">
    <div class="fd-top"><div><h2>Fee Control Room</h2><p class="fd-sub">${esc(D.school)} · Session ${esc(longSes(D.ses))} · as on ${dmy(D.today)}${F.tag !== null ? ' · Tag: <b>' + esc(F.tag) + '</b>' : ''}</p></div>
      <div class="fd-act np"><select onchange="load(this.value)">${D.sessions.map(s => `<option value="${esc(s)}" ${s === D.ses ? 'selected' : ''}>${esc(longSes(s))}</option>`).join('')}</select>
      ${D.money ? `<button onclick="shareSummary()">WhatsApp Summary</button>` : ''}<button onclick="window.print()">Print</button><button onclick="load(D.ses)">Refresh</button></div></div>
    ${D.live ? '' : `<p class="fd-note">Fees for ${esc(longSes(D.ses))} are still taken in the old ERP. Fee cards here are as copied from the old ERP${D.asOn ? ' on <b>' + esc(D.asOn) + '</b>' : ''}; receipts copied after that are counted in collection.</p>`}
    <div class="fd-tabs np">${tabs.map(([k, l]) => `<button class="${TAB === k ? 'on' : ''}" onclick="TAB='${k}';render();scrollTo(0,0)">${esc(l)}</button>`).join('')}</div>
    ${chips}${body}</div>`;
  if (keep) { const q = $('fdQ'); q.focus(); q.setSelectionRange(q.value.length, q.value.length); }
  const kt = document.activeElement && document.activeElement.id === 'fdTQ';
  if (kt) { const q = $('fdTQ'); q.focus(); q.setSelectionRange(q.value.length, q.value.length); }
}
function setTagF(t) { F.tag = t; calc(); render(); }

// ---- Month Collection: what came in this month (fee / transport), what should have come for this month, who is pending
function monHtml() {
  const m = MSEL, ym = ymOf(m), cin = cashIn(D.ok.filter(r => r.date.slice(0, 7) === ym)), P = monthPos(D.kids, m);
  const tagRows = D.tags.map(t => { const K = D.all.filter(k => k.tagName === t), set = new Set(K.map(k => k.sid)), q = monthPos(K, m), c = cashIn(D.receipts.filter(r => r.status !== 'Cancelled' && set.has(r.sid) && r.date.slice(0, 7) === ym)); return { t, n: K.length, q, c }; }).filter(x => x.n);
  const pend = P.pend.slice().sort((a, b) => b.monDue - a.monDue);
  return `<div class="fd-card np"><div class="fd-months">${MORDER.map(x => `<button class="${x === m ? 'on' : ''}" onclick="MSEL=${x};render()">${MN[x].slice(0, 3)}</button>`).join('')}</div></div>
    <h3 class="fd-h">${MN[m]} ${ym.slice(0, 4)} — money that came in</h3>
    <div class="fd-tiles">
      ${tile('t1', rs(cin.fee), 'Fee aayi', `school fee heads${cin.mixed ? ' · + ' + rs(cin.mixed) + ' old receipts without head split' : ''}`)}
      ${tile('t2', rs(cin.tr), 'Transport fee aayi', 'transport heads')}
      ${tile('t3', rs(cin.total), 'Total aaya', `${cin.n} receipt(s)`)}
      ${tile('t4', String(P.pend.length), 'Bachchon par kaam karna hai', `${MN[m]} ki fee baaki · ${rs(P.due)}`)}
    </div>
    <div class="fd-card"><h3>${MN[m]} ki fee — kitni aani chahiye, kitni aayi</h3><div class="fd-tw"><table class="fd-t k"><tr><th></th><th>Aani chahiye</th><th>Aa gayi (paid + disc)</th><th>Baaki</th><th>%</th></tr>
      <tr><td>School fee</td><td>${rs(P.fd)}</td><td>${rs(P.fp)}</td><td class="red">${rs(P.fd - P.fp)}</td><td>${pct(P.fp, P.fd)}%</td></tr>
      <tr><td>Transport fee</td><td>${rs(P.td)}</td><td>${rs(P.tp)}</td><td class="red">${rs(P.td - P.tp)}</td><td>${pct(P.tp, P.td)}%</td></tr>
      <tr class="tot"><td>Total</td><td>${rs(P.fd + P.td)}</td><td>${rs(P.fp + P.tp)}</td><td class="red">${rs(P.due)}</td><td>${pct(P.fp + P.tp, P.fd + P.td)}%</td></tr></table></div>
      <p class="fd-small">"Aani chahiye" = ${MN[m]} ke fee card heads (fixed plan wale bachchon ka fixed amount). "Aa gayi" = in heads par ab tak jama hui fee, kisi bhi din. "Fee aayi" upar = is mahine ki rasidon me aaya paisa.</p></div>
    ${tagRows.length > 1 || (tagRows[0] && tagRows[0].t !== NOTAG) ? `<div class="fd-card"><h3>Tag-wise · ${MN[m]}</h3><div class="fd-tw"><table class="fd-t"><tr><th>Tag</th><th>Bachche</th><th>Fee aani chahiye</th><th>Transport aani chahiye</th><th>Aa gayi</th><th>Baaki</th><th>Bachche baaki</th><th>Is mahine aaya (fee + transport)</th></tr>
      ${tagRows.map(x => `<tr><td><button class="fd-link" onclick='setTagF(${JSON.stringify(x.t)})'>${esc(x.t)}</button></td><td>${x.n}</td><td>${rs(x.q.fd)}</td><td>${rs(x.q.td)}</td><td>${rs(x.q.fp + x.q.tp)}</td><td class="red">${rs(x.q.due)}</td><td>${x.q.pend.length}</td><td>${rs(x.c.total)} <small>(${rs(x.c.fee + x.c.mixed)} + ${rs(x.c.tr)})</small></td></tr>`).join('')}
      <tr class="tot"><td>Total</td><td>${sum(tagRows, x => x.n)}</td><td>${rs(sum(tagRows, x => x.q.fd))}</td><td>${rs(sum(tagRows, x => x.q.td))}</td><td>${rs(sum(tagRows, x => x.q.fp + x.q.tp))}</td><td class="red">${rs(sum(tagRows, x => x.q.due))}</td><td>${sum(tagRows, x => x.q.pend.length)}</td><td>${rs(sum(tagRows, x => x.c.total))}</td></tr></table></div></div>` : ''}
    <div class="fd-card"><h3>${MN[m]} ki fee baaki — ${pend.length} bachche</h3>${kidTable(pend, D.canCall, true)}</div>`;
}
// ---- Tags: the Chairman's groups (Company, FD / fixed plan, staff ward…) side by side; the Principal sets them
function tagHtml() {
  const i = monthIdx(), cm = i < 0 ? 4 : MORDER[Math.min(i, 11)];
  const R = D.tags.map(t => { const K = D.all.filter(k => k.tagName === t), q = monthPos(K, cm); return { t, n: K.length, fx: K.filter(k => k.fixed > 0).length, annual: sum(K, k => k.annual), paid: sum(K, k => k.paid), dueNow: sum(K, k => k.dueNow), def: K.filter(k => k.dueNow > 0).length, q }; });
  const q = F.tq.trim().toLowerCase();
  const list = D.all.filter(k => (!F.tcls || k.cls === F.tcls) && (!q || [k.name, k.father, k.adm, k.tagName].some(v => String(v || '').toLowerCase().includes(q)))).sort((a, b) => CLASSES.indexOf(a.cls) - CLASSES.indexOf(b.cls) || a.name.localeCompare(b.name));
  const P = D.role === 'principal';
  return `<div class="fd-card"><h3>Tag-wise fee position · session ${esc(longSes(D.ses))}</h3><div class="fd-tw"><table class="fd-t"><tr><th>Tag</th><th>Bachche</th><th>Fixed plan</th><th>Session fee</th><th>Aa gayi</th><th>Abhi tak baaki</th><th>Bachche baaki</th><th>${MN[cm]} baaki</th><th>Collected</th></tr>
    ${R.map(x => `<tr><td><button class="fd-link" onclick='setTagF(${JSON.stringify(x.t)});TAB="mon";render()'>${esc(x.t)}</button></td><td>${x.n}</td><td>${x.fx || ''}</td><td>${rs(x.annual)}</td><td>${rs(x.paid)}</td><td class="red">${rs(x.dueNow)}</td><td>${x.def}</td><td>${rs(x.q.due)}</td><td>${pct(x.paid, x.paid + x.dueNow)}%</td></tr>`).join('')}
    <tr class="tot"><td>Total</td><td>${sum(R, x => x.n)}</td><td>${sum(R, x => x.fx) || ''}</td><td>${rs(sum(R, x => x.annual))}</td><td>${rs(sum(R, x => x.paid))}</td><td class="red">${rs(sum(R, x => x.dueNow))}</td><td>${sum(R, x => x.def)}</td><td>${rs(sum(R, x => x.q.due))}</td><td>${pct(sum(R, x => x.paid), sum(R, x => x.paid + x.dueNow))}%</td></tr></table></div>
    <p class="fd-small">Tag old ERP se aata hai; Principal yahan badal sakta hai. Fixed plan = har mahine sirf itni fee (transport alag, fee card ke hisaab se).</p></div>
    <div class="fd-card"><h3>Bachchon ke tag</h3><div class="fd-filt np"><label>Class <select onchange="F.tcls=this.value;render()"><option value="">All</option>${CLASSES.map(c => `<option ${F.tcls === c ? 'selected' : ''}>${c}</option>`).join('')}</select></label>
      <label>Search <input id="fdTQ" value="${esc(F.tq)}" oninput="F.tq=this.value;render()" placeholder="Name / father / tag"></label><span>${list.length} bachche</span></div>
      <datalist id="fdTags">${[...new Set(['Company', 'FD', 'Staff Ward', 'RTE', ...D.tags.filter(t => t !== NOTAG)])].map(t => `<option value="${esc(t)}">`).join('')}</datalist>
      <div class="fd-tw"><table class="fd-t"><tr><th>Student</th><th>Class</th><th>Tag</th><th>Fixed / month</th><th>Note</th><th>Abhi tak baaki</th>${P ? '<th class="np"></th>' : ''}</tr>
      ${list.slice(0, 300).map(k => TEDIT === k.sid ? `<tr class="np"><td class="l"><b>${esc(k.name)}</b><br><small>${esc(k.father)}</small></td><td>${esc(k.cls)}</td>
        <td><input id="tT" list="fdTags" value="${esc(k.tag)}" maxlength="30" style="width:120px"></td><td><input id="tF" value="${k.fixed || ''}" inputmode="decimal" placeholder="0" style="width:80px"></td><td><input id="tN" value="${esc(k.tagNote)}" maxlength="120"></td><td></td>
        <td class="fd-btns"><button onclick="saveTag('${esc(k.sid)}')" ${BUSY ? 'disabled' : ''}>Save</button><button onclick="TEDIT='';render()">Cancel</button></td></tr>`
        : `<tr><td class="l"><b>${esc(k.name)}</b><br><small>${esc(k.father)}</small></td><td>${esc(k.cls)}</td><td>${k.tag ? `<span class="fd-tag">${esc(k.tag)}</span>` : ''}${k.oldTag && k.oldTag !== k.tag ? `<br><small>old ERP: ${esc(k.oldTag)}</small>` : ''}</td><td>${k.fixed ? rs(k.fixed) : ''}</td><td>${esc(k.tagNote)}</td><td class="red">${k.dueNow ? rs(k.dueNow) : ''}</td>
        ${P ? `<td class="np fd-btns"><button onclick="TEDIT='${esc(k.sid)}';render()">Edit</button></td>` : ''}</tr>`).join('')}</table></div>${list.length > 300 ? '<p class="fd-small">Pehle 300 dikh rahe hain — class ya search se chhota karein.</p>' : ''}</div>`;
}
async function saveTag(sid) {
  if (BUSY) return; BUSY = true;
  try {
    const d = await api('feedash_api.php?a=setTag', { sid, tag: $('tT').value, fixed: $('tF').value, note: $('tN').value });
    const r = D.dues.find(x => x.sid === sid); if (r) { r.tag = d.tag.tag; r.fixed = d.tag.fixed / 100; r.tagNote = d.tag.note; }
    TEDIT = ''; calc(); toast('Tag saved.');
  } catch (e) { toast(e.message); }
  BUSY = false; render();
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
function kidTable(rows, act, mcol) {
  if (!rows.length) return '<p class="fd-note">Nobody in this list.</p>';
  return `<div class="fd-tw"><table class="fd-t"><tr><th>#</th><th>Student</th><th>Class</th><th>Father / Mobile</th>${mcol ? `<th>${MN[MSEL].slice(0, 3)} baaki</th>` : ''}<th>Due till Now</th><th>Months</th><th>Last Paid</th><th>Last Call</th>${act ? '<th class="np"></th>' : ''}</tr>
    ${rows.map((k, i) => `<tr class="${k.broken ? 'brk' : ''}"><td>${i + 1}</td><td class="l"><b>${esc(k.name)}</b><br><small>${esc(k.adm)}</small>${k.tag ? ` <span class="fd-tag">${esc(k.tag)}</span>` : ''}</td><td>${esc(k.cls)}${k.section ? '-' + esc(k.section) : ''}</td>
      <td class="l">${esc(k.father)}<br><small>${esc(k.phoneNo || 'no mobile')}</small></td>${mcol ? `<td class="red"><b>${rs(k.monDue)}</b></td>` : ''}<td class="red"><b>${rs(k.dueNow)}</b>${k.old ? `<br><small>old ${rs(k.old)}</small>` : ''}</td><td>${esc(k.unpaidMonths.join(', '))}</td>
      <td>${k.lastPaid ? dmy(k.lastPaid.date) + (D.money ? '<br><small>' + rs(k.lastPaid.amt) + '</small>' : '') : '<small>never</small>'}</td>
      <td class="l">${k.last ? `${esc(k.last.outcome)}${k.promise ? '<br><small>date ' + dmy(k.promise) + (k.broken ? ' · <b>broken</b>' : '') + '</small>' : ''}<br><small>${dmy(k.last.date)} · ${esc(k.last.by || '')}</small>` : '<small>not called</small>'}</td>
      ${act ? `<td class="np fd-btns">${k.phoneNo ? `<a class="b-call" href="tel:+91${k.phoneNo}">Call</a><a class="b-wa" target="_blank" rel="noopener" href="https://wa.me/91${k.phoneNo}?text=${encodeURIComponent(remind(k))}">WhatsApp</a>` : ''}${D.canCall ? `<button onclick="OPEN=OPEN==='${esc(k.sid)}'?'':'${esc(k.sid)}';render()">${OPEN === k.sid ? 'Close' : 'Save Call'}</button>` : ''}</td>` : ''}</tr>
      ${act && OPEN === k.sid ? `<tr class="np"><td colspan="11"><div class="fd-save"><label>What happened <select id="cO">${D.outcomes.map(o => `<option>${esc(o)}</option>`).join('')}</select></label>
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
    (F.tag !== null ? `Tag: ${F.tag}\n` : '') +
    `Aaj ki fee: ${rs(sum(t.today, r => r.received))} (${t.today.length} rasid)\n` +
    (() => { const c = cashIn(t.month), m = +D.today.slice(5, 7), p = monthPos(D.kids, m); return `Is mahine aaya: ${rs(c.total)} (Fee ${rs(c.fee + c.mixed)} + Transport ${rs(c.tr)})\n${MN[m]} ki fee aani chahiye: ${rs(p.fd + p.td)} · aa gayi ${rs(p.fp + p.tp)} · baaki ${rs(p.due)} (${p.pend.length} bachche)\n`; })() +
    `Session ${longSes(D.ses)} me ab tak: ${rs(sum(t.ses, r => r.received))}\n\n` +
    `Abhi tak baaki: ${rs(t.dueNow)} (${t.defaulters.length} bachche)\n` +
    `Collection: ${pct(t.paid, t.target)}%\n\n` +
    `Aaj calls: ${D.calls.filter(c => c.date === D.today).length} · Vaada toota: ${broken.length}\n\n` +
    `Sabse zyada baaki (class): ` + clsRows().filter(c => c.dueNow > 0).sort((a, b) => b.dueNow - a.dueNow).slice(0, 3).map(c => `${c.cls} ${rs(c.dueNow)}`).join(', ') +
    (F.tag === null && D.tags.some(t => t !== NOTAG) ? `\n\nTag-wise baaki: ` + D.tags.map(t => `${t} ${rs(sum(D.all.filter(k => k.tagName === t), k => k.dueNow))}`).join(', ') : '');
  window.open('https://wa.me/?text=' + encodeURIComponent(txt), '_blank', 'noopener');
}
load(QS.get('session') ? QS.get('session').replace(/^(20\d\d)-20(\d\d)$/, '$1-$2') : '');
