/* Fee Calling Report (owner, 25 Nov 2026) — callreport.php. Uses the login of the ERP / Teacher App / Chairman app
   on this device (sch_token). Data: callreport_api.php?a=data&from=&to=  — read-only. */
'use strict';
(() => {
  const $ = id => document.getElementById(id);
  const store = { get: k => { try { return localStorage.getItem(k); } catch (e) { return null; } } };
  const esc = s => String(s == null ? '' : s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const rs = p => '₹' + Math.round((p || 0) / 100).toLocaleString('en-IN');
  const iso = d => { const x = new Date(d.getTime() - d.getTimezoneOffset() * 60000); return x.toISOString().slice(0, 10); };
  const add = (s, n) => { const d = new Date(s + 'T12:00:00'); d.setDate(d.getDate() + n); return iso(d); };
  const dmy = s => s ? s.slice(8, 10) + '-' + s.slice(5, 7) + '-' + s.slice(0, 4) : '';
  const tm = ms => ms ? new Date(ms).toLocaleTimeString('en-IN', { hour: '2-digit', minute: '2-digit' }) : '';
  const qs = new URLSearchParams(location.search);
  const INFRAME = window.top !== window;
  const TODAY = iso(new Date());
  let BY = qs.get('by') === 'promise' ? 'promise' : 'call';   // owner 27 Nov 2026: report by call date OR by the date the family gave to pay
  let FROM = qs.get('from') || (BY === 'promise' ? TODAY : add(TODAY, -6)), TO = qs.get('to') || TODAY, D = null, TAB = BY === 'promise' ? 'prom' : 'all';
  const weekMon = () => { const d = new Date(TODAY + 'T12:00:00'); return add(TODAY, -((d.getDay() + 6) % 7)); };
  const F = { by: '', res: '', cls: '', q: '' };

  async function load() {
    $('app').innerHTML = '<div class="msg">Opening Fee Calling Report…</div>';
    if (!store.get('sch_token')) return noLogin('Please log in first.');
    try {
      const r = await fetch('callreport_api.php?a=data&from=' + FROM + '&to=' + TO + (BY === 'promise' ? '&by=promise' : ''), { cache: 'no-store', headers: { 'X-School-Token': store.get('sch_token') || '' } });
      const j = await r.json().catch(() => ({ ok: false, error: 'Server error.' }));
      if (r.status === 401) return noLogin(j.error || 'Please log in again.');
      if (!j.ok) return noLogin(j.error || 'Could not open the report.');
      D = j; FROM = j.from; TO = j.to; draw();
    } catch (e) { noLogin('No internet connection. Please try again.'); }
  }
  function noLogin(m) {
    $('app').innerHTML = `<div class="msg"><h3>${esc(m)}</h3><p class="mut">This report opens with the login of the ERP, the Teacher App (fee callers) or the Chairman app on this phone / computer.</p>
      <p><a class="btn go" href="index.php">Open ERP</a> <a class="btn" href="teacher.php">Teacher App</a> <button class="btn" id="rt">Try again</button></p></div>`;
    $('rt').onclick = load;
  }
  const stu = sid => (D.students[sid] || { name: sid, cls: '', section: '', father: '', phone: '' });
  // paid by the family (all children on the call) on or after the call date
  function paidAfter(c) {
    let t = 0, last = null;
    for (const s of c.sids) for (const [d, p, no] of (D.paid[s] || [])) if (d >= c.date) { t += p; if (!last || d > last.d) last = { d, no }; }
    return { t, last };
  }
  function status(c) {
    const p = paidAfter(c);
    if (c.outcome === 'Already paid') return ['Said already paid', 's', p];
    if (p.t > 0 && (!c.amount || p.t >= c.amount)) return ['Paid ' + rs(p.t), 'g', p];
    if (p.t > 0) return ['Part paid ' + rs(p.t), 'y', p];
    if (!c.promise) return ['—', '', p];
    if (c.promise < D.today) return ['Date passed', 'r', p];
    if (c.promise === D.today) return ['Due today', 'y', p];
    return ['Waiting', 'b', p];
  }
  function rows() {
    const q = F.q.trim().toLowerCase();
    return D.calls.filter(c => {
      const s = stu(c.sid);
      if (F.by && c.by !== F.by) return false;
      if (F.res && c.outcome !== F.res) return false;
      if (F.cls && s.cls !== F.cls) return false;
      if (q && !(s.name + ' ' + s.father + ' ' + s.phone + ' ' + c.note + ' ' + c.by).toLowerCase().includes(q)) return false;
      return true;
    });
  }
  const opts = (L, cur, all) => `<option value="">${all}</option>` + L.map(x => `<option ${x === cur ? 'selected' : ''}>${esc(x)}</option>`).join('');
  const uniq = a => [...new Set(a.filter(Boolean))].sort((x, y) => x.localeCompare(y, 'en', { numeric: true }));

  function draw() {
    const R = rows(), fams = new Set(R.map(c => c.sids.slice().sort().join(','))).size;
    const prom = R.filter(c => c.outcome === 'Promised to pay'), promAmt = prom.reduce((n, c) => n + c.amount, 0);
    const seen = new Set(); let paidAmt = 0; R.forEach(c => { const k = c.sids.slice().sort().join(','); if (seen.has(k)) return; seen.add(k); paidAmt += paidAfter(c).t; });
    const over = R.filter(c => status(c)[0] === 'Date passed').length;
    const P = BY === 'promise';
    const quick = P ? [['Aaj', TODAY, TODAY], ['Kal', add(TODAY, 1), add(TODAY, 1)], ['Beeta kal', add(TODAY, -1), add(TODAY, -1)], ['Is hafte', weekMon(), add(weekMon(), 6)], ['Date nikal gayi', add(TODAY, -60), add(TODAY, -1)]]
      : [['Today', TODAY, TODAY], ['Yesterday', add(TODAY, -1), add(TODAY, -1)], ['7 days', add(TODAY, -6), TODAY], ['This month', TODAY.slice(0, 8) + '01', TODAY]];
    const pf = R.filter(c => status(c)[1] === 'g' || status(c)[0] === 'Said already paid'), pp = R.filter(c => status(c)[1] === 'y' && /^Part/.test(status(c)[0])), notPaid = R.length - pf.length - pp.length;
    const pAmt = R.reduce((n, c) => n + (c.amount || 0), 0);
    const tab = (k, l) => `<button class="q ${TAB === k ? 'on' : ''}" data-tab="${k}">${l}</button>`;
    $('app').innerHTML = `<div class="top"><div><b>📞 Fee Calling Report</b><small>${esc(D.school)} · ${P ? 'Payment date' : 'Call date'} ${dmy(FROM)}${TO !== FROM ? ' to ' + dmy(TO) : ''}</small></div>
      <div class="noprint"><button id="xl">⬇ Excel</button> <button id="pr">🖨 Print</button> ${INFRAME ? '' : '<button id="bk">← Back</button>'}</div></div>
      <div class="tabs noprint" style="padding-top:10px"><button class="q ${P ? '' : 'on'}" data-mode="call">📞 Call date ke hisaab se</button><button class="q ${P ? 'on' : ''}" data-mode="promise">💰 Payment date ke hisaab se (kis din dene ko bola)</button></div>
      <div class="bar">${quick.map(([l, f, t]) => `<button class="q ${FROM === f && TO === t ? 'on' : ''}" data-f="${f}" data-t="${t}">${l}</button>`).join('')}
        <label>From</label><input type="date" id="fF" value="${FROM}" ${P ? '' : `max="${TODAY}"`}><label>To</label><input type="date" id="fT" value="${TO}" ${P ? '' : `max="${TODAY}"`}>
        <select id="fBy">${opts(uniq(D.calls.map(c => c.by)), F.by, 'All callers')}</select>
        <select id="fRes">${opts(uniq(D.calls.map(c => c.outcome)), F.res, 'All results')}</select>
        <select id="fCls">${opts(uniq(D.calls.map(c => stu(c.sid).cls)), F.cls, 'All classes')}</select>
        <input id="fQ" placeholder="Search name, father, mobile, remark" value="${esc(F.q)}" style="flex:1;min-width:170px"></div>
      ${P ? `<div class="kp"><div><span>${FROM === TO ? dmy(FROM) + ' ko' : 'In dino'} payment karne ko bola</span><b>${R.length} famil${R.length === 1 ? 'y' : 'ies'}</b></div><div><span>Vaade ki rakam</span><b>${rs(pAmt)}</b></div>
        <div><span>De diya</span><b style="color:#166534">${pf.length} · ${rs(paidAmt)}</b></div><div><span>Aadha diya</span><b style="color:#92400E">${pp.length}</b></div>
        <div><span>Abhi nahi diya</span><b style="color:#991B1B">${notPaid}</b></div></div>`
      : `<div class="kp"><div><span>Calls</span><b>${R.length}</b></div><div><span>Families called</span><b>${fams}</b></div>
        <div><span>Promised to pay</span><b>${prom.length} · ${rs(promAmt)}</b></div><div><span>Paid after the call</span><b style="color:#166534">${rs(paidAmt)}</b></div>
        <div><span>Promise date passed</span><b style="color:#991B1B">${over}</b></div></div>`}
      <div class="tabs noprint">${tab('all', 'All calls')}${tab('prom', 'Promise dates')}${tab('by', 'By caller')}${tab('fam', 'Family-wise (last call)')}</div>
      <div class="wrap"><div class="card">${R.length ? (TAB === 'prom' ? promTable(R) : TAB === 'by' ? byTable(R) : TAB === 'fam' ? famTable(R) : allTable(R)) : (P ? '<div class="empty">In dates par kisi family ne payment karne ko nahi bola.</div>' : '<div class="empty">No calls saved for these dates / filters.</div>')}</div></div>`;
    document.querySelectorAll('[data-f]').forEach(b => b.onclick = () => { FROM = b.dataset.f; TO = b.dataset.t; load(); });
    document.querySelectorAll('[data-tab]').forEach(b => b.onclick = () => { TAB = b.dataset.tab; draw(); });
    document.querySelectorAll('[data-mode]').forEach(b => b.onclick = () => { if (BY === b.dataset.mode) return; BY = b.dataset.mode; FROM = BY === 'promise' ? TODAY : add(TODAY, -6); TO = TODAY; TAB = BY === 'promise' ? 'prom' : 'all'; F.res = ''; load(); });
    $('fF').onchange = e => { if (e.target.value) { FROM = e.target.value; load(); } };
    $('fT').onchange = e => { if (e.target.value) { TO = e.target.value; load(); } };
    $('fBy').onchange = e => { F.by = e.target.value; draw(); };
    $('fRes').onchange = e => { F.res = e.target.value; draw(); };
    $('fCls').onchange = e => { F.cls = e.target.value; draw(); };
    $('fQ').oninput = e => { F.q = e.target.value; clearTimeout(window._qt); window._qt = setTimeout(() => { draw(); const el = $('fQ'); el.focus(); el.setSelectionRange(el.value.length, el.value.length); }, 350); };
    $('xl').onclick = () => excel(R);
    $('pr').onclick = () => print();
    if ($('bk')) $('bk').onclick = () => { if (history.length > 1) history.back(); else location.href = 'index.php'; };
  }
  const sCell = s => `<b>${esc(s.name)}</b><div class="mut">${esc(s.cls)}${s.section ? ' ' + esc(s.section) : ''}${s.father ? ' · ' + esc(s.father) : ''}</div>`;
  const ph = s => s.phone ? `<a href="tel:${esc(s.phone)}">${esc(s.phone)}</a>` : '';
  const resPill = o => `<span class="pill ${({ 'Promised to pay': 'b', 'Already paid': 'g', 'Not answered': 'r', 'Switched off': 'r', 'Wrong number': 'r', 'Call back later': 'y', 'Will visit school': 'y' })[o] || 's'}">${esc(o)}</span>`;
  const stPill = c => { const [t, k] = status(c); return k ? `<span class="pill ${k}">${esc(t)}</span>` : '<span class="mut">—</span>'; };
  function allTable(R) {
    return `<table><tr><th>#</th><th>Call date</th><th>Student</th><th>Mobile</th><th>Result</th><th>Date given</th><th>Amount</th><th>Remark</th><th>Called by</th><th>Payment after call</th></tr>
      ${R.map((c, i) => { const s = stu(c.sid); return `<tr><td>${i + 1}</td><td class="n">${dmy(c.date)}<div class="mut">${tm(c.at)}</div></td><td>${sCell(s)}${c.sids.length > 1 ? `<div class="mut">+${c.sids.length - 1} sibling${c.sids.length > 2 ? 's' : ''}</div>` : ''}</td><td class="n">${ph(s)}</td>
        <td>${resPill(c.outcome)}</td><td class="n">${dmy(c.promise) || '<span class="mut">—</span>'}</td><td class="n">${c.amount ? rs(c.amount) : ''}</td><td class="note">${esc(c.note)}${c.with ? `<div class="mut">Spoke to: ${esc(c.with)}</div>` : ''}</td><td>${esc(c.by)}</td><td>${stPill(c)}</td></tr>`; }).join('')}</table>`;
  }
  function promTable(R) {
    const L = R.filter(c => c.promise).sort((a, b) => a.promise.localeCompare(b.promise));
    if (!L.length) return '<div class="empty">No family gave a date in these calls.</div>';
    return `<table><tr><th>Date given</th><th>Status</th><th>Student</th><th>Mobile</th><th>Result</th><th>Amount</th><th>Remark</th><th>Call date</th><th>Called by</th></tr>
      ${L.map(c => { const s = stu(c.sid); return `<tr><td class="n"><b>${dmy(c.promise)}</b></td><td>${stPill(c)}</td><td>${sCell(s)}</td><td class="n">${ph(s)}</td><td>${resPill(c.outcome)}</td><td class="n">${c.amount ? rs(c.amount) : ''}</td><td class="note">${esc(c.note)}</td><td class="n">${dmy(c.date)}</td><td>${esc(c.by)}</td></tr>`; }).join('')}</table>`;
  }
  function byTable(R) {
    const G = {}; R.forEach(c => { const g = G[c.by] = G[c.by] || { n: 0, fam: new Set(), res: {}, prom: 0, amt: 0, days: new Set() }; g.n++; g.fam.add(c.sids.slice().sort().join(',')); g.res[c.outcome] = (g.res[c.outcome] || 0) + 1; g.days.add(c.date); if (c.outcome === 'Promised to pay') { g.prom++; g.amt += c.amount; } });
    const res = uniq(R.map(c => c.outcome));
    return `<table style="min-width:600px"><tr><th>Called by</th><th>Calls</th><th>Families</th><th>Days</th>${res.map(o => `<th>${esc(o)}</th>`).join('')}<th>Promised ₹</th></tr>
      ${Object.entries(G).sort((a, b) => b[1].n - a[1].n).map(([k, g]) => `<tr><td><b>${esc(k)}</b></td><td>${g.n}</td><td>${g.fam.size}</td><td>${g.days.size}</td>${res.map(o => `<td>${g.res[o] || ''}</td>`).join('')}<td class="n">${rs(g.amt)}</td></tr>`).join('')}</table>`;
  }
  function famTable(R) {
    const last = {}; R.forEach(c => { const k = c.sids.slice().sort().join(','); if (!last[k]) last[k] = { c, n: 0 }; last[k].n++; });
    return `<table><tr><th>Student</th><th>Mobile</th><th>Calls</th><th>Last result</th><th>Date given</th><th>Last remark</th><th>Last call</th><th>Called by</th><th>Payment after call</th></tr>
      ${Object.values(last).map(({ c, n }) => { const s = stu(c.sid); return `<tr><td>${sCell(s)}</td><td class="n">${ph(s)}</td><td>${n}</td><td>${resPill(c.outcome)}</td><td class="n">${dmy(c.promise) || '—'}</td><td class="note">${esc(c.note)}</td><td class="n">${dmy(c.date)}</td><td>${esc(c.by)}</td><td>${stPill(c)}</td></tr>`; }).join('')}</table>`;
  }
  function excel(R) {
    const q = v => '"' + String(v == null ? '' : v).replace(/"/g, '""') + '"';
    const head = ['Call date', 'Time', 'Student', 'Class', 'Section', 'Father', 'Mobile', 'Result', 'Date given', 'Amount', 'Remark', 'Spoke to', 'Called by', 'Payment after call', 'Paid amount'];
    const lines = [head.map(q).join(',')].concat(R.map(c => { const s = stu(c.sid), [st, , p] = status(c);
      return [dmy(c.date), tm(c.at), s.name, s.cls, s.section, s.father, s.phone, c.outcome, dmy(c.promise), c.amount ? Math.round(c.amount / 100) : '', c.note, c.with, c.by, st, p.t ? Math.round(p.t / 100) : ''].map(q).join(','); }));
    const a = document.createElement('a'); a.href = URL.createObjectURL(new Blob(['﻿' + lines.join('\r\n')], { type: 'text/csv;charset=utf-8' }));
    a.download = 'fee-calling-report-' + FROM + '-to-' + TO + '.csv'; document.body.appendChild(a); a.click(); a.remove();
  }
  load();
})();
