/* Fee Reminder Slip (owner, 25 Nov 2026) — feeslip.php. Uses the ERP / Teacher App / Chairman app login on this device.
   Slip tab: every family with fee due → choose → "Print & mark given" (3 slips per A4, with a tear-off part the parent signs).
   Given tab: who got a slip, when, due on the slip, by whom — counts + Excel. Data: feeslip_api.php. Fees are never changed. */
'use strict';
(() => {
  const $ = id => document.getElementById(id);
  const tok = () => { try { return localStorage.getItem('sch_token') || ''; } catch (e) { return ''; } };
  const esc = s => String(s == null ? '' : s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const rs = p => '₹' + Math.round((p || 0) / 100).toLocaleString('en-IN');
  const iso = d => new Date(d.getTime() - d.getTimezoneOffset() * 60000).toISOString().slice(0, 10);
  const add = (s, n) => { const d = new Date(s + 'T12:00:00'); d.setDate(d.getDate() + n); return iso(d); };
  const dmy = s => s ? s.slice(8, 10) + '-' + s.slice(5, 7) + '-' + s.slice(0, 4) : '';
  const MON = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
  const TODAY = iso(new Date());
  const INFRAME = window.top !== window;
  let D = null, G = null, TAB = 'slip', SEL = new Set(), PAYBY = add(TODAY, 7), GF = TODAY, GT = TODAY;
  const F = { cls: '', min: '', given: 'all', group: 'family', q: '', notag: true };   // notag: Company / FD families get no slip (owner, 25 Nov 2026)
  const TAGGED = r => /\b(Company|FD)\b/i.test(r.tag || '');
  let GQ = '', BACK = null;

  function toast(m) { const t = $('toast'); t.textContent = m; t.style.display = 'block'; clearTimeout(toast.t); toast.t = setTimeout(() => { t.style.display = 'none'; }, 3500); }
  async function api(path, body) {
    const r = await fetch(path, { method: body ? 'POST' : 'GET', cache: 'no-store', headers: { 'Content-Type': 'application/json', 'X-School-Token': tok() }, ...(body ? { body: JSON.stringify(body) } : {}) });
    const j = await r.json().catch(() => ({ ok: false, error: 'Server error.' }));
    if (!j.ok) { const e = new Error(j.error || 'Something went wrong.'); e.status = r.status; throw e; }
    return j;
  }
  function stop(m) {
    $('app').innerHTML = `<div class="msg"><h3>${esc(m)}</h3><p class="mut">This page opens with the login of the ERP, the Teacher App (fee callers) or the Chairman app on this phone / computer.</p>
      <p><a class="btn go" href="index.php">Open ERP</a> <a class="btn" href="teacher.php">Teacher App</a> <button class="btn" id="rt">Try again</button></p></div>`;
    $('rt').onclick = load;
  }
  async function load() {
    if (!tok()) return stop('Please log in first.');
    $('app').innerHTML = '<div class="msg">Opening Fee Reminder Slip…</div>';
    try { D = await api('feeslip_api.php?a=list'); SEL = new Set(groups().map(g => g.key)); draw(); } catch (e) { stop(e.message); }
  }
  async function loadGiven() {
    try { G = await api('feeslip_api.php?a=given&from=' + GF + '&to=' + GT); GF = G.from; GT = G.to; draw(); } catch (e) { toast(e.message); }
  }
  // ---- families ----
  function groups() {
    const q = F.q.trim().toLowerCase(), min = Math.max(0, Number(F.min) || 0) * 100, cut = F.given === 'week' ? add(TODAY, -6) : '';
    const rows = D.rows.filter(r => (!F.cls || r.cls === F.cls) && r.due >= Math.max(min, 1) && !(F.notag && TAGGED(r))
      && (F.given === 'all' || (F.given === 'never' ? !r.last : !r.last || r.last < cut))
      && (!q || (r.name + ' ' + r.father + ' ' + r.phone + ' ' + r.adm).toLowerCase().includes(q)));
    const m = new Map();
    for (const r of rows) { const k = F.group === 'family' && r.phone ? 'p:' + r.phone : 's:' + r.sid; if (!m.has(k)) m.set(k, { key: k, kids: [] }); m.get(k).kids.push(r); }
    return [...m.values()].map(g => Object.assign(g, { due: g.kids.reduce((n, r) => n + r.due, 0), old: g.kids.reduce((n, r) => n + r.oldDue, 0), last: g.kids.map(r => r.last).sort().pop() || '' }))
      .sort((a, b) => a.kids[0].cls.localeCompare(b.kids[0].cls, 'en', { numeric: true }) || a.kids[0].name.localeCompare(b.kids[0].name));
  }
  const uniq = a => [...new Set(a.filter(Boolean))].sort((x, y) => x.localeCompare(y, 'en', { numeric: true }));

  function draw() {
    const tab = (k, l) => `<button class="q ${TAB === k ? 'on' : ''}" data-tab="${k}">${l}</button>`;
    const head = `<div class="top"><div><b>🧾 Fee Reminder Slip</b><small>${esc(D.school)} · Session ${esc(D.session)}</small></div><div>${INFRAME ? '' : '<button id="bk">← Back</button>'}</div></div>`;
    if (TAB === 'given') return drawGiven(head, tab);
    const L = groups(), chosen = L.filter(g => SEL.has(g.key)), kids = chosen.reduce((n, g) => n + g.kids.length, 0), amt = chosen.reduce((n, g) => n + g.due, 0);
    const allFam = new Set(D.rows.map(r => r.phone ? 'p:' + r.phone : 's:' + r.sid)).size, givenToday = D.rows.filter(r => r.last === TODAY).length;
    $('app').innerHTML = head + `
      <div class="kp"><div><span>Children with fee due</span><b>${D.rows.length}</b></div><div><span>Families</span><b>${allFam}</b></div>
        <div><span>Total due till now</span><b>${rs(D.rows.reduce((n, r) => n + r.due, 0))}</b></div><div><span>Slip given today</span><b style="color:#166534">${givenToday}</b></div>
        <div><span>Never given a slip</span><b style="color:#991B1B">${D.rows.filter(r => !r.last).length}</b></div></div>
      <div class="tabs">${tab('slip', 'Make slips')}${tab('given', 'Slips given — list')}</div>
      <div class="bar"><select id="fCls"><option value="">All classes</option>${uniq(D.rows.map(r => r.cls)).map(c => `<option ${c === F.cls ? 'selected' : ''}>${esc(c)}</option>`).join('')}</select>
        <select id="fGiven">${[['all', 'Everyone with due'], ['week', 'No slip in last 7 days'], ['never', 'Never given a slip']].map(([k, l]) => `<option value="${k}" ${F.given === k ? 'selected' : ''}>${l}</option>`).join('')}</select>
        <select id="fGroup"><option value="family" ${F.group === 'family' ? 'selected' : ''}>One slip per family (siblings together)</option><option value="child" ${F.group === 'child' ? 'selected' : ''}>One slip per child</option></select>
        <label style="display:flex;align-items:center;gap:4px;color:#17202a"><input type="checkbox" id="fTag" ${F.notag ? 'checked' : ''}> Company / FD tag wale hatao (${D.rows.filter(TAGGED).length})</label>
        <label>Due above ₹</label><input id="fMin" type="number" min="0" inputmode="numeric" value="${esc(F.min)}" style="width:90px">
        <label>Pay by</label><input id="fPay" type="date" value="${PAYBY}" min="${TODAY}">
        <input id="fQ" placeholder="Search name, father, mobile, adm no." value="${esc(F.q)}" style="flex:1;min-width:160px"></div>
      <div class="act" style="padding-top:10px"><button class="btn gr" id="pm">🖨 Print & mark given (${chosen.length} slip${chosen.length === 1 ? '' : 's'} · ${kids} children)</button>
        <button class="btn" id="po">Print only</button><button class="btn" id="sa">Select all</button><button class="btn" id="sn">Select none</button>
        <span class="mut" style="align-self:center">Selected due: <b>${rs(amt)}</b> · 3 slips on one A4 page</span></div>
      <div class="wrap"><div class="card">${L.length ? `<table><tr><th><input type="checkbox" id="ca" ${chosen.length === L.length ? 'checked' : ''}></th><th>Student(s)</th><th>Class</th><th>Father</th><th>Mobile</th><th>Due till now</th><th>Last slip</th><th></th></tr>
        ${L.map(g => `<tr><td><input type="checkbox" data-k="${esc(g.key)}" ${SEL.has(g.key) ? 'checked' : ''}></td><td>${g.kids.map(r => `<b>${esc(r.name)}</b>${r.adm ? ` <span class="mut">(${esc(r.adm)})</span>` : ''}`).join('<br>')}</td>
          <td class="n">${g.kids.map(r => esc(r.cls + (r.section ? ' ' + r.section : ''))).join('<br>')}</td><td>${esc(g.kids[0].father)}</td><td class="n">${g.kids[0].phone ? `<a href="tel:${esc(g.kids[0].phone)}">${esc(g.kids[0].phone)}</a>` : '<span class="mut">—</span>'}</td>
          <td class="n"><b>${rs(g.due)}</b>${g.kids.some(TAGGED) ? ` <span class="pill s">${esc(g.kids.find(TAGGED).tag)}</span>` : ''}${g.kids.length > 1 ? `<div class="mut">${g.kids.map(r => rs(r.due)).join(' + ')}</div>` : ''}</td>
          <td class="n">${g.last ? `<span class="pill ${g.last === TODAY ? 'g' : 's'}">${dmy(g.last)}</span>` : '<span class="mut">Never</span>'}</td>
          <td>${g.kids[0].phone ? `<button class="btn wa" data-wa="${esc(g.key)}" style="padding:6px 9px">WhatsApp</button>` : ''}</td></tr>`).join('')}</table>` : '<div class="empty">No child with fee due for these filters.</div>'}</div></div>`;
    bindCommon();
    $('fCls').onchange = e => { F.cls = e.target.value; reselect(); };
    $('fGiven').onchange = e => { F.given = e.target.value; reselect(); };
    $('fGroup').onchange = e => { F.group = e.target.value; reselect(); };
    $('fMin').onchange = e => { F.min = e.target.value; reselect(); };
    $('fTag').onchange = e => { F.notag = e.target.checked; reselect(); };
    $('fPay').onchange = e => { if (e.target.value) PAYBY = e.target.value; };
    $('fQ').oninput = e => { F.q = e.target.value; clearTimeout(window._qt); window._qt = setTimeout(() => { reselect(); const el = $('fQ'); el.focus(); el.setSelectionRange(el.value.length, el.value.length); }, 350); };
    document.querySelectorAll('[data-k]').forEach(c => c.onchange = () => { c.checked ? SEL.add(c.dataset.k) : SEL.delete(c.dataset.k); draw(); });
    if ($('ca')) $('ca').onchange = e => { SEL = e.target.checked ? new Set(L.map(g => g.key)) : new Set(); draw(); };
    $('sa').onclick = () => { SEL = new Set(L.map(g => g.key)); draw(); };
    $('sn').onclick = () => { SEL = new Set(); draw(); };
    $('pm').onclick = () => printSlips(chosen, true);
    $('po').onclick = () => printSlips(chosen, false);
    document.querySelectorAll('[data-wa]').forEach(b => b.onclick = () => whatsapp(L.find(g => g.key === b.dataset.wa)));
  }
  function reselect() { SEL = new Set(groups().map(g => g.key)); draw(); }
  function bindCommon() {
    document.querySelectorAll('[data-tab]').forEach(b => b.onclick = () => { TAB = b.dataset.tab; if (TAB === 'given') { G = null; loadGiven(); } draw(); });
    if ($('bk')) $('bk').onclick = () => { if (history.length > 1) history.back(); else location.href = 'index.php'; };
  }
  async function mark(chosen, mode) {
    const sids = [], due = {}; chosen.forEach(g => g.kids.forEach(r => { sids.push(r.sid); due[r.sid] = r.due; }));
    const r = await api('feeslip_api.php?a=give', { sids, due, mode });
    D.rows.forEach(x => { if (due[x.sid] != null) { x.last = r.date; x.times++; } });
    return r.given;
  }
  async function printSlips(chosen, doMark) {
    if (!chosen.length) return toast('Choose at least one family.');
    $('print').innerHTML = chosen.map(slipHtml).join('');
    if (doMark) { try { const n = await mark(chosen, 'print'); toast(n + ' children marked as slip given today.'); } catch (e) { return toast(e.message); } }
    draw(); setTimeout(() => window.print(), 200);
  }
  function upto() { const d = new Date(TODAY + 'T12:00:00'); return MON[d.getMonth()] + ' ' + d.getFullYear(); }
  function slipHtml(g) {
    const k = g.kids, f = k[0];
    return `<div class="slip"><h2>${esc(D.school)}</h2><div class="sub">Bhadohi (U.P.)</div><h3>FEE REMINDER · शुल्क अनुस्मारक</h3>
      <div class="row"><span>Date: <b>${dmy(TODAY)}</b></span><span>Session: <b>${esc(D.session)}</b></span></div>
      <p style="margin:6px 0 4px">Dear Parent${f.father ? ' (<b>' + esc(f.father) + '</b>)' : ''}, / आदरणीय अभिभावक,<br>The school fee of your ward${k.length > 1 ? 's' : ''} is pending up to <b>${upto()}</b>. Details: / आपके बच्चे की फीस बाकी है:</p>
      <table><tr><th>Student</th><th>Class</th><th>Adm. No.</th><th style="text-align:right">Due (₹)</th></tr>
        ${k.map(r => `<tr><td>${esc(r.name)}</td><td>${esc(r.cls + (r.section ? ' ' + r.section : ''))}</td><td>${esc(r.adm)}</td><td style="text-align:right">${Math.round(r.due / 100).toLocaleString('en-IN')}</td></tr>`).join('')}
        <tr><td colspan="3" class="tot">Total due / कुल बकाया</td><td class="tot" style="text-align:right">${rs(g.due)}</td></tr></table>
      ${g.old > 0 ? `<div style="font-size:11.5px">Includes old session due ${rs(g.old)}.</div>` : ''}
      <p style="margin:5px 0">Kindly deposit the fee at the school office by <b>${dmy(PAYBY)}</b>. कृपया <b>${dmy(PAYBY)}</b> तक विद्यालय कार्यालय में फीस जमा करें। If already paid, please ignore this slip. / यदि फीस जमा कर दी है तो इस पर्ची को अनदेखा करें।</p>
      <div class="row" style="margin-top:14px"><span>Accountant</span><span>Principal</span></div>
      <div class="cut">✂ Return this part to the class teacher / यह भाग कक्षा अध्यापक को लौटाएँ<br>
        ${k.map(r => esc(r.name) + ' (' + esc(r.cls) + ')').join(', ')} — Due ${rs(g.due)} · I will pay on / मैं फीस इस दिन जमा करूँगा: ____________ &nbsp; Parent's sign / हस्ताक्षर: ____________</div></div>`;
  }
  async function whatsapp(g) {
    if (!g) return;
    const k = g.kids, msg = `नमस्ते 🙏 ${D.school} से।\n` + k.map(r => `${r.name} (कक्षा ${r.cls}) की फीस ${rs(r.due)} बाकी है।`).join('\n') + (k.length > 1 ? `\nकुल: ${rs(g.due)}` : '') +
      `\nकृपया ${dmy(PAYBY)} तक विद्यालय कार्यालय में फीस जमा करें। यदि फीस जमा कर दी है तो इस संदेश को अनदेखा करें।\nधन्यवाद।`;
    window.open('https://wa.me/91' + k[0].phone + '?text=' + encodeURIComponent(msg), '_blank', 'noopener');
    try { await mark([g], 'whatsapp'); toast('Marked: reminder sent on WhatsApp.'); draw(); } catch (e) { toast(e.message); }
  }
  // ---- given list ----
  function drawGiven(head, tab) {
    const quick = [['Today', TODAY, TODAY], ['Yesterday', add(TODAY, -1), add(TODAY, -1)], ['7 days', add(TODAY, -6), TODAY], ['This month', TODAY.slice(0, 8) + '01', TODAY]];
    const q = GQ.trim().toLowerCase(), R = G ? G.rows.filter(r => !q || (r.name + ' ' + r.father + ' ' + r.phone).toLowerCase().includes(q)) : [], fam = new Set(R.map(r => r.phone ? 'p:' + r.phone : 's:' + r.sid)).size, by = {};
    R.forEach(r => { by[r.by] = (by[r.by] || 0) + 1; });
    $('app').innerHTML = head + `<div class="tabs" style="padding-top:12px">${tab('slip', 'Make slips')}${tab('given', 'Slips given — list')}</div>
      <div class="bar">${quick.map(([l, f, t]) => `<button class="q ${GF === f && GT === t ? 'on' : ''}" data-f="${f}" data-t="${t}">${l}</button>`).join('')}
        <label>From</label><input type="date" id="gF" value="${GF}" max="${TODAY}"><label>To</label><input type="date" id="gT" value="${GT}" max="${TODAY}">
        <input id="gQ" placeholder="Search name, father, mobile" value="${esc(GQ)}" style="flex:1;min-width:150px">
        <button class="btn" id="gx">⬇ Excel</button><button class="btn" id="gp">🖨 Print list</button></div>
      <div class="kp"><div><span>Children given a slip</span><b>${R.length}</b></div><div><span>Families</span><b>${fam}</b></div>
        <div><span>Due on these slips</span><b>${rs(R.reduce((n, r) => n + (r.due || 0), 0))}</b></div><div><span>Printed / WhatsApp</span><b>${R.filter(r => r.mode !== 'whatsapp').length} / ${R.filter(r => r.mode === 'whatsapp').length}</b></div>
        <div><span>Slip wapas aayi</span><b style="color:#166534">${R.filter(r => r.ret).length}</b></div><div><span>Abhi wapas nahi aayi</span><b style="color:#991B1B">${R.filter(r => !r.ret && r.mode !== 'whatsapp').length}</b></div></div>
      ${Object.keys(by).length ? `<p class="mut" style="padding:0 12px">Given by: ${Object.entries(by).map(([k, n]) => esc(k) + ' — ' + n).join(' · ')}</p>` : ''}
      <div class="wrap"><div class="card" id="glist">${!G ? '<div class="empty">Opening…</div>' : R.length ? `<table><tr><th>#</th><th>Date</th><th>Student</th><th>Class</th><th>Father</th><th>Mobile</th><th>Due on slip</th><th>How</th><th>Given by</th><th>Slip wapas / parent ki date</th></tr>
        ${R.map((r, i) => `<tr><td>${i + 1}</td><td class="n">${dmy(r.date)}</td><td><b>${esc(r.name)}</b></td><td class="n">${esc(r.cls + (r.section ? ' ' + r.section : ''))}</td><td>${esc(r.father)}</td><td class="n">${esc(r.phone)}</td><td class="n">${rs(r.due)}</td><td>${r.mode === 'whatsapp' ? 'WhatsApp' : 'Printed slip'}</td><td>${esc(r.by)}</td>
          <td>${r.ret ? `<span class="pill g">↩ ${dmy(r.ret.promise)} tak${r.ret.amount ? ' · ' + rs(r.ret.amount) : ''}</span>${r.ret.note ? `<div class="mut">${esc(r.ret.note)}</div>` : ''}` : `<button class="btn go" data-back="${i}" style="padding:6px 9px">↩ Wapas aayi</button>`}</td></tr>`).join('')}</table>` : '<div class="empty">No slip given on these dates.</div>'}</div></div>`;
    bindCommon();
    document.querySelectorAll('[data-f]').forEach(b => b.onclick = () => { GF = b.dataset.f; GT = b.dataset.t; G = null; loadGiven(); draw(); });
    $('gQ').oninput = e => { GQ = e.target.value; clearTimeout(window._gq); window._gq = setTimeout(() => { draw(); const el = $('gQ'); el.focus(); el.setSelectionRange(el.value.length, el.value.length); }, 350); };
    document.querySelectorAll('[data-back]').forEach(b => b.onclick = () => { BACK = R[+b.dataset.back]; backSheet(); });
    $('gF').onchange = e => { if (e.target.value) { GF = e.target.value; G = null; loadGiven(); } };
    $('gT').onchange = e => { if (e.target.value) { GT = e.target.value; G = null; loadGiven(); } };
    $('gx').onclick = () => {
      const q = v => '"' + String(v == null ? '' : v).replace(/"/g, '""') + '"';
      const lines = [['Date', 'Student', 'Class', 'Section', 'Father', 'Mobile', 'Due on slip', 'How', 'Given by', 'Slip returned', 'Parent will pay on', 'Amount promised', 'Remark'].map(q).join(',')]
        .concat(R.map(r => [dmy(r.date), r.name, r.cls, r.section, r.father, r.phone, Math.round((r.due || 0) / 100), r.mode === 'whatsapp' ? 'WhatsApp' : 'Printed slip', r.by, r.ret ? dmy(r.ret.date) : '', r.ret ? dmy(r.ret.promise) : '', r.ret && r.ret.amount ? Math.round(r.ret.amount / 100) : '', r.ret ? r.ret.note : ''].map(q).join(',')));
      const a = document.createElement('a'); a.href = URL.createObjectURL(new Blob(['﻿' + lines.join('\r\n')], { type: 'text/csv;charset=utf-8' }));
      a.download = 'fee-slips-given-' + GF + '-to-' + GT + '.csv'; document.body.appendChild(a); a.click(); a.remove();
    };
    $('gp').onclick = () => { $('print').innerHTML = `<h2 style="text-align:center;margin:0">${esc(D.school)}</h2><p style="text-align:center;margin:4px 0 10px">Fee reminder slips given · ${dmy(GF)} to ${dmy(GT)} · ${R.length} children, ${fam} families</p>` + $('glist').innerHTML; setTimeout(() => window.print(), 100); };
  }
  // ---- slip came back: parent's date → Promise dates / fee-call follow-up ----
  function backSheet() {
    const r = BACK; if (!r) return;
    let el = $('backSheet'); if (!el) { el = document.createElement('div'); el.id = 'backSheet'; document.body.appendChild(el); }
    el.innerHTML = `<div style="position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:8;display:flex;align-items:center;justify-content:center;padding:12px">
      <div style="background:#fff;border-radius:12px;padding:16px;max-width:420px;width:100%">
        <h3 style="margin:0 0 4px">↩ Slip wapas aayi</h3><p class="mut" style="margin:0 0 10px">${esc(r.name)} · Class ${esc(r.cls)} · slip ${dmy(r.date)} · due ${rs(r.due)}</p>
        <label class="mut">Parent ne kis date tak fees dene ko likha?</label><input id="bD" type="date" min="${TODAY}" max="${add(TODAY, 60)}" style="width:100%;padding:9px;margin:4px 0 10px;border:1px solid #c9d2dc;border-radius:8px">
        <label class="mut">Kitni fees (₹) — likha ho to</label><input id="bA" type="number" inputmode="decimal" min="0" style="width:100%;padding:9px;margin:4px 0 10px;border:1px solid #c9d2dc;border-radius:8px">
        <label class="mut">Remark (zaroori nahi)</label><input id="bN" maxlength="300" style="width:100%;padding:9px;margin:4px 0 12px;border:1px solid #c9d2dc;border-radius:8px">
        <p class="mut" style="margin:0 0 10px">Ye date Fee Calling ki Promise dates me jayegi. Us din tak fees na aayi to ye bachcha calling list me aa jayega.</p>
        <div style="display:flex;gap:8px"><button class="btn gr" id="bS" style="flex:1">Save</button><button class="btn" id="bC">Cancel</button></div></div></div>`;
    $('bC').onclick = () => { BACK = null; el.innerHTML = ''; };
    $('bS').onclick = async () => {
      const promise = $('bD').value; if (!promise) return toast('Parent ki di hui date chuniye.');
      $('bS').disabled = true;
      try { const j = await api('feeslip_api.php?a=back', { sid: r.sid, promise, amount: $('bA').value.trim(), note: $('bN').value.trim() });
        G.rows.forEach(x => { if (x.sid === r.sid && x.date === j.slip.date) x.ret = j.slip.ret; }); BACK = null; el.innerHTML = ''; toast('Saved — date Promise dates me chali gayi.'); draw(); }
      catch (e) { $('bS').disabled = false; toast(e.message); }
    };
  }
  load();
})();
