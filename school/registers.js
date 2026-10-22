'use strict';
// School Office — one screen for every register defined in registers_lib.php.
let META = null, CUR = '', ROWS = [], Q = '', FCLS = '', FSTAT = '', BUSY = false, IMPORT = null, DET = null;
// Session chosen in the app header (old-ERP style session menu); '' = all sessions.
let VSES = (new URLSearchParams(location.search).get('session') || '').replace(/^(20\d\d)-20(\d\d)$/, '$1-$2');
const $ = id => document.getElementById(id);
const esc = v => String(v ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
const today = () => new Intl.DateTimeFormat('en-CA', { timeZone: 'Asia/Kolkata' }).format(new Date());
const rs = p => p === null || p === undefined || p === '' ? '' : '₹' + (Number(p) / 100).toLocaleString('en-IN', { maximumFractionDigits: 2 });
const dmy = d => /^\d{4}-\d{2}-\d{2}$/.test(d || '') ? d.slice(8) + '-' + d.slice(5, 7) + '-' + d.slice(0, 4) : (d || '');
function toast(t) { $('regToast').textContent = t; setTimeout(() => { if ($('regToast').textContent === t) $('regToast').textContent = ''; }, 6000); }
async function api(a, body) {
  const r = await fetch('registers_api.php?a=' + a, { method: body ? 'POST' : 'GET', headers: { 'Content-Type': 'application/json', 'X-School-Token': localStorage.getItem('sch_token') || '' }, ...(body ? { body: JSON.stringify(body) } : {}) });
  let d; try { d = await r.json(); } catch (e) { throw Error('The server did not answer properly. Please try again.'); }
  if (!r.ok || !d.ok) { const e = Error(d.error || 'Could not complete this.'); e.rows = d.rows; throw e; }
  return d;
}
const mod = () => META.modules.find(m => m.id === CUR);
const STU = () => Object.fromEntries(META.students.map(s => [s.id, s]));
const TCH = () => Object.fromEntries(META.teachers.map(t => [t.id, t.name]));
function show(f, v, row) {
  if (v === null || v === undefined || v === '') return '';
  if (f.type === 'money') return rs(v);
  if (f.type === 'date') return dmy(v);
  if (f.type === 'student') { const s = STU()[v]; return s ? `${s.name} (${s.cls}${s.section ? '-' + s.section : ''}, ${s.adm})` : (row && row.name) || v; }
  if (f.type === 'teacher') return TCH()[v] || v;
  if (f.type === 'json') return typeof v === 'object' ? Object.entries(v).map(([k, x]) => k + ': ' + (typeof x === 'object' ? Object.values(x).join('/') : x)).slice(0, 6).join('; ') : String(v);
  return String(v);
}
async function boot() {
  try { META = await api('meta'); CUR = new URLSearchParams(location.search).get('m') || META.modules[0].id; if (!mod()) CUR = META.modules[0].id; await openMod(CUR); }
  catch (e) { $('regRoot').innerHTML = `<section class="rg-panel"><h1>School Office</h1><p>${esc(e.message)}</p><a href="index.php" target="_top">Back to the school panel</a></section>`; }
}
async function openMod(id) { CUR = id; Q = ''; FCLS = ''; FSTAT = ''; ROWS = []; render(true); try { ROWS = (await api('rows&m=' + encodeURIComponent(id))).rows; } catch (e) { toast(e.message); } render(); }
function filtered() {
  const m = mod(), st = STU(), q = Q.toLowerCase(), sf = m.fields.find(f => f.type === 'student');
  return ROWS.filter(r => {
    if (VSES && m.fields.some(f => f.k === 'session') && String(r.session || '').replace(/^(20\d\d)-20(\d\d)$/, '$1-$2') !== VSES) return false;
    if (FSTAT && (r.status || '') !== FSTAT) return false;
    if (FCLS) { const c = r.cls || r.lastCls || (sf && st[r[sf.k]] ? st[r[sf.k]].cls : ''); if (c !== FCLS) return false; }
    if (!q) return true;
    return m.fields.some(f => show(f, r[f.k], r).toLowerCase().includes(q));
  }).sort((a, b) => String(b.date || b._created || '').localeCompare(String(a.date || a._created || '')) || String(b.no || b.receiptNo || '').localeCompare(String(a.no || a.receiptNo || ''), 'en', { numeric: true }));
}
function render(loading) {
  const m = mod(), fk = Object.fromEntries(m.fields.map(f => [f.k, f])), groups = {};
  META.modules.forEach(x => (groups[x.group] = groups[x.group] || []).push(x));
  const side = Object.entries(groups).map(([g, ms]) => `<h3>${esc(g)}</h3>` + ms.map(x => `<button class="${x.id === CUR ? 'on' : ''}" onclick="openMod('${x.id}')">${esc(x.title)}</button>`).join('')).join('');
  const rows = filtered(), cols = m.list.map(k => fk[k]).filter(Boolean), t = today();
  const hasStatus = !!fk.status && fk.status.opts, hasCls = m.fields.some(f => f.type === 'student' || f.k === 'cls' || f.k === 'lastCls');
  const sums = (m.sum || []).map(k => [fk[k], rows.reduce((s, r) => s + (Number(r[k]) || 0), 0)]);
  const body = loading ? '<p class="rg-muted">Loading…</p>' : !rows.length ? `<p class="rg-muted">${ROWS.length ? 'Nothing matches this search.' : 'No entries yet.'}</p>` : `<div class="rg-wrap"><table class="rg-table"><thead><tr>${cols.map(f => `<th class="${f.type === 'money' || f.type === 'num' ? 'n' : ''}">${esc(f.label)}</th>`).join('')}</tr></thead><tbody>${
    rows.slice(0, 1500).map(r => { const due = m.due && r[m.due] && r[m.due] <= t && !['Done', 'Admitted', 'Resolved', 'Not interested'].includes(r.status); return `<tr class="rg-row ${due ? 'rg-due' : ''}" onclick="openRow('${esc(r._id)}')">${cols.map(f => `<td class="${f.type === 'money' || f.type === 'num' ? 'n' : f.type === 'date' || f.type === 'time' ? 'nw' : ''}">${esc(show(f, r[f.k], r))}</td>`).join('')}</tr>`; }).join('')
  }</tbody>${sums.length ? `<tfoot><tr>${cols.map((f, i) => { const s = sums.find(x => x[0].k === f.k); return `<td class="${s ? 'n' : ''}">${s ? esc(f.type === 'money' ? rs(s[1]) : s[1].toLocaleString('en-IN')) : i === 0 ? 'Total (' + rows.length + ')' : ''}</td>`; }).join('')}</tr></tfoot>` : ''}</table></div>${rows.length > 1500 ? '<p class="rg-muted">Showing the first 1,500. Search or export to see all.</p>' : ''}`;
  const dueCount = m.due ? rows.filter(r => r[m.due] && r[m.due] <= t && !['Done', 'Admitted', 'Resolved', 'Not interested'].includes(r.status)).length : 0;
  $('regRoot').innerHTML = `<div class="rg-layout"><nav class="rg-side" aria-label="School Office registers"><h1 style="font-size:20px;margin:6px 8px">School Office</h1><small style="margin:0 8px">${esc(META.school)} · ${esc(META.session || 'session not set')}</small>${side}</nav>
  <section class="rg-panel"><div class="rg-head"><div><h2>${esc(m.title)}</h2><div class="rg-old">Old ERP: ${esc(m.old)}</div></div><div class="rg-tools">
    ${m.write ? `<button class="go" onclick="openRow('')">+ Add</button>` : ''}${m.import ? `<button onclick="openImport()">Import from old ERP</button>` : ''}${m.report ? `<button onclick="openReport('${m.report}')">${m.report === 'stock' ? 'Stock report' : 'Cash book'}</button>` : ''}
    ${m.id === 'oldfee' && META.canCarry ? `<button onclick="openCarry()">Carry old dues to Fee Desk</button>` : ''}<button onclick="csv()">Export CSV</button><button onclick="printList()">Print list</button></div></div>
    <div class="rg-kpis"><div class="rg-kpi"><b>${rows.length.toLocaleString('en-IN')}</b><small>entries</small></div>${sums.map(([f, v]) => `<div class="rg-kpi"><b>${esc(f.type === 'money' ? rs(v) : v.toLocaleString('en-IN'))}</b><small>${esc(f.label)}</small></div>`).join('')}${m.due ? `<div class="rg-kpi"><b>${dueCount}</b><small>due today or overdue</small></div>` : ''}</div>
    <div class="rg-tools" style="margin-bottom:12px"><input class="rg-search" type="search" placeholder="Search" value="${esc(Q)}" oninput="Q=this.value;render()" aria-label="Search">
    ${hasCls ? `<select class="rg-filter" onchange="FCLS=this.value;render()" aria-label="Class"><option value="">All classes</option>${['PG','LKG','UKG','1st','2nd','3rd','4th','5th','6th','7th','8th','9th','10th','11th','12th'].map(c => `<option ${c === FCLS ? 'selected' : ''}>${c}</option>`).join('')}</select>` : ''}
    ${m.fields.some(f => f.k === 'session') ? `<select class="rg-filter" onchange="VSES=this.value;render()" aria-label="Session"><option value="">All sessions</option>${[...new Set(ROWS.map(r => String(r.session || '').replace(/^(20\d\d)-20(\d\d)$/, '$1-$2')).concat(VSES ? [VSES] : []))].filter(Boolean).sort().reverse().map(x => `<option ${x === VSES ? 'selected' : ''}>${esc(x)}</option>`).join('')}</select>` : ''}
    ${hasStatus ? `<select class="rg-filter" onchange="FSTAT=this.value;render()" aria-label="Status"><option value="">All status</option>${fk.status.opts.map(o => `<option ${o === FSTAT ? 'selected' : ''}>${esc(o)}</option>`).join('')}</select>` : ''}</div>
    ${body}</section></div>`;
  const s = document.querySelector('.rg-search'); if (s && document.activeElement !== s && Q) { s.focus(); s.setSelectionRange(Q.length, Q.length); }
}
function input(f, v) {
  const id = 'f_' + f.k, req = f.req ? ' required' : '';
  if (f.type === 'area') return `<textarea id="${id}"${req}>${esc(v)}</textarea>`;
  if (f.type === 'sel' || f.type === 'cls') { const o = f.opts.slice(); if (v && !o.includes(v)) o.unshift(v); return `<select id="${id}"${req}><option value="">—</option>${o.map(x => `<option ${x === v ? 'selected' : ''}>${esc(x)}</option>`).join('')}</select>`; }
  if (f.type === 'yn') return `<select id="${id}"><option value="">—</option>${['Yes', 'No'].map(x => `<option ${x === v ? 'selected' : ''}>${x}</option>`).join('')}</select>`;
  if (f.type === 'student') { const list = META.students.slice().sort((a, b) => (a.cls + a.name).localeCompare(b.cls + b.name)); return `<select id="${id}"${req}><option value="">— choose student —</option>${list.map(s => `<option value="${esc(s.id)}" ${s.id === v ? 'selected' : ''}>${esc(s.cls + (s.section ? '-' + s.section : '') + ' · ' + s.name + ' · ' + s.adm)}</option>`).join('')}</select>`; }
  if (f.type === 'teacher') return `<select id="${id}"><option value="">—</option>${META.teachers.map(t => `<option value="${esc(t.id)}" ${t.id === v ? 'selected' : ''}>${esc(t.name)}</option>`).join('')}</select>`;
  if (f.type === 'json') return `<textarea id="${id}" readonly>${esc(v ? JSON.stringify(v, null, 1) : '')}</textarea>`;
  const type = { date: 'date', time: 'time', num: 'number', money: 'text', phone: 'tel' }[f.type] || 'text';
  const val = f.type === 'money' ? (v === null || v === undefined || v === '' ? '' : Number(v) / 100) : (v ?? '');
  return `<input id="${id}" type="${type}" value="${esc(val)}"${req}${f.type === 'money' ? ' inputmode="decimal"' : ''}${f.type === 'num' ? ' step="any"' : ''}>`;
}
function openRow(id) {
  const m = mod(), r = id ? ROWS.find(x => x._id === id) : null, auto = m.auto;
  const ro = !m.write;
  const fields = m.fields.map(f => {
    let v = r ? r[f.k] : (f.today ? today() : '');
    if (ro || (f.k === auto)) return `<label class="${f.type === 'area' || f.type === 'json' ? 'wide' : ''}">${esc(f.label)}<div>${esc(show(f, v, r) || (f.k === auto && !r ? 'Given on save' : '—'))}</div></label>`;
    return `<label class="${f.type === 'area' ? 'wide' : ''}">${esc(f.label)}${f.req ? ' <b class="req">*</b>' : ''}${input(f, v)}</label>`;
  }).join('');
  $('regDialogBody').innerHTML = `<h2>${esc(m.title)}${r ? '' : ' — new'}</h2>${r ? `<p class="rg-muted">Last change by ${esc(r._by || '')}${r._createdBy && r._createdBy !== r._by ? ' · created by ' + esc(r._createdBy) : ''}</p>` : ''}
    <form class="rg-form" onsubmit="event.preventDefault();saveRow('${r ? esc(r._id) : ''}')">${fields}</form>
    <div class="rg-tools" style="margin-top:16px;justify-content:flex-end">${r && m.print ? `<button onclick="printRow('${esc(r._id)}')">Print</button>` : ''}${r && (m.write || (META.role === 'principal' && m.import)) ? `<button class="danger" onclick="delRow('${esc(r._id)}')">Remove</button>` : ''}<button onclick="$('regDialog').close()">Close</button>${m.write ? `<button class="go" onclick="saveRow('${r ? esc(r._id) : ''}')">Save</button>` : ''}</div>`;
  $('regDialog').showModal();
}
async function saveRow(id) {
  if (BUSY) return; const m = mod(), row = {};
  for (const f of m.fields) { if (f.k === m.auto || f.type === 'json') { const old = id ? ROWS.find(x => x._id === id) : null; if (f.type === 'json' && old) row[f.k] = old[f.k]; continue; } const el = $('f_' + f.k); if (el) row[f.k] = el.value; }
  BUSY = true;
  try { const d = await api('save', { m: m.id, id, row }); const i = ROWS.findIndex(x => x._id === d.row._id); if (i >= 0) ROWS[i] = d.row; else ROWS.push(d.row); $('regDialog').close(); toast('Saved' + (m.auto && d.row[m.auto] ? ' — ' + d.row[m.auto] : '')); render(); }
  catch (e) { toast(e.message); } finally { BUSY = false; }
}
async function delRow(id) {
  const reason = prompt('Why is this row being removed? (kept in the Activity Log)'); if (!reason) return;
  try { await api('del', { m: CUR, id, reason }); ROWS = ROWS.filter(x => x._id !== id); $('regDialog').close(); toast('Removed'); render(); } catch (e) { toast(e.message); }
}
function csv() {
  const m = mod(), rows = filtered(), q = v => '"' + String(v ?? '').replace(/"/g, '""') + '"';
  const lines = [m.fields.map(f => q(f.label)).join(',')].concat(rows.map(r => m.fields.map(f => q(f.type === 'money' ? (r[f.k] === null || r[f.k] === undefined || r[f.k] === '' ? '' : Number(r[f.k]) / 100) : f.type === 'student' ? show(f, r[f.k], r) : f.type === 'json' ? JSON.stringify(r[f.k] || '') : r[f.k])).join(',')));
  const a = document.createElement('a'); a.href = URL.createObjectURL(new Blob(['﻿' + lines.join('\r\n')], { type: 'text/csv' })); a.download = m.id + '-' + today() + '.csv'; a.click();
}
function head() { return `<div class="pr-head"><h1>${esc(META.school)}</h1><p>${esc(META.place)} · Session ${esc(META.session)}</p></div>`; }
function printList() {
  const m = mod(), fk = Object.fromEntries(m.fields.map(f => [f.k, f])), cols = m.list.map(k => fk[k]).filter(Boolean), rows = filtered();
  $('regPrint').innerHTML = `<div class="pr-page">${head()}<div class="pr-title">${esc(m.title)} (${rows.length})</div><table class="pr-list"><tr><th>#</th>${cols.map(f => `<th>${esc(f.label)}</th>`).join('')}</tr>${rows.map((r, i) => `<tr><td>${i + 1}</td>${cols.map(f => `<td>${esc(show(f, r[f.k], r))}</td>`).join('')}</tr>`).join('')}</table></div>`;
  window.print();
}
// Old ERP receipt, laid out like the old ERP's print.php (logo, red school name, barcode strip, particulars table).
function oldReceiptHtml(d) {
  const t = v => esc(v === undefined || v === null ? '' : v);
  const lines = Array.isArray(d.lines) ? d.lines : [];
  return `<div class="or-wrap"><table class="or-head"><tr><td rowspan="2" class="or-logo"><img src="img/snmsis-logo.png" alt=""></td><td class="or-name"><span>${t(META.school)}</span><br><b>Jagannathpur, Near Sikhdi Khandak, Bhadohi - 221403, UP</b></td></tr>
    <tr><td class="or-bar"><img src="img/receipt-barcode.gif" alt=""></td></tr></table>
    <div class="or-title">${t(d.title || ('Fee Receipt(' + (d.session || '') + ')'))}</div>
    <table class="or-k"><tr><td><b>Receipt No</b></td><td>${t(d.receiptNo)}</td><td><b>Date</b></td><td>${t(d.date)}</td></tr>
      <tr><td><b>Name</b></td><td><b>${t(d.name)}</b></td><td><b>Class</b></td><td>${t(d.cls)}</td></tr>
      <tr><td><b>Adm No</b></td><td>${t(d.adm)}</td><td><b>Mother's Name</b></td><td>${t(d.mother)}</td></tr>
      <tr><td><b>Father's Name</b></td><td>${t(d.father)}</td><td><b>Schemes<br>Transport</b></td><td>${t(d.scheme).replace(/ \| /g, '<br>')}</td></tr></table>
    <table class="or-k or-p"><tr><th>Sno</th><th>PARTICULARS</th><th>Month</th><th>Amount</th></tr>${lines.map(l => `<tr><td>${t(l[0])}</td><td>${t(l[1])}</td><td>${t(l[2])}</td><td>${t(l[3])}</td></tr>`).join('')}</table>
    <table class="or-k"><tr><td><b>Balance<br>Amount</b></td><td><b>${t(d.balance)}</b></td><td><b>Payable Fee</b></td><td><b>${t(d.payable)}</b></td></tr>
      <tr><td><b>Late Fee</b></td><td><b>${t(d.lateFee)}</b></td><td><b>Adjustment</b></td><td><b>${t(d.adjustment)}</b></td></tr>
      <tr><td><b>Payment Mode</b></td><td><b>${t(d.mode)}</b></td><td><b>Currently Received</b></td><td><b>${t(d.received)}</b></td></tr>
      <tr><td><b>Bank Name</b></td><td><b>${t(d.bank)}</b></td><td><b>Cheque No/Date</b></td><td><b>${t(d.cheque)}</b></td></tr></table>
    <div class="or-rem"><b>Remark : </b>${t(d.remark)}</div>
    <div class="or-foot"><span><span class="or-copy">Parent Copy</span> Printed on ${new Date().toLocaleString('en-GB')}</span><span class="or-sign">${t(d.user)}<br>Auth. Sign</span></div></div>`;
}
async function printOldReceipt(r) {
  if (!DET) { try { DET = (await api('rows&m=oldrcptdet')).rows; } catch (e) { DET = []; } }
  const norm = x => String(x || '').replace(/^(20\d\d)-20(\d\d)$/, '$1-$2');
  const d = r.lines !== undefined ? r : DET.find(x => norm(x.session) === norm(r.session) && x.receiptNo === r.receiptNo);
  if (!d) return toast('The full receipt (with fee heads) for ' + r.receiptNo + ' has not been imported yet.');
  $('regPrint').innerHTML = `<div class="pr-page">${oldReceiptHtml(d)}</div>`; window.print();
}
function printRow(id) {
  const m = mod(), r = ROWS.find(x => x._id === id), s = STU()[r.student] || {}, fk = Object.fromEntries(m.fields.map(f => [f.k, f]));
  if (m.print === 'oldreceipt') return printOldReceipt(r);
  const kv = keys => `<table class="pr-kv">${keys.filter(k => fk[k]).map(k => `<tr><td>${esc(fk[k].label)}</td><td>${esc(show(fk[k], r[k], r))}</td></tr>`).join('')}</table>`;
  const sign = (a, b) => `<div class="pr-sign"><span>${a}</span><span>${b}</span></div>`;
  const stu = s.name ? `<table class="pr-kv"><tr><td>Student</td><td>${esc(s.name)}</td></tr><tr><td>Father's name</td><td>${esc(s.father)}</td></tr><tr><td>Class</td><td>${esc(s.cls + (s.section ? '-' + s.section : ''))}</td></tr><tr><td>Scholar / Adm. No</td><td>${esc(s.adm)}</td></tr></table><br>` : '';
  let html = '';
  if (m.print === 'tc') html = `${head()}<div class="pr-title">TRANSFER CERTIFICATE</div><p>TC No: <b>${esc(r.no)}</b> &nbsp; Date: <b>${dmy(r.date)}</b></p>${stu}${kv(['admDate','lastCls','leaveDate','result','promoted','dues','conduct','reason','workingDays','present','remarks'])}${sign('Prepared by', 'Principal')}`;
  else if (m.print === 'cert') html = `${head()}<div class="pr-title">${esc((r.kind || 'Certificate').toUpperCase())} CERTIFICATE</div><p>No: <b>${esc(r.no)}</b> &nbsp; Date: <b>${dmy(r.date)}</b></p><p style="font-size:15px;line-height:2">This is to certify that <b>${esc(s.name || '')}</b>, son/daughter of <b>${esc(s.father || '')}</b>, is a student of Class <b>${esc(s.cls || '')}</b> (Scholar No <b>${esc(s.adm || '')}</b>) of this school${r.occasion ? ' and took part in <b>' + esc(r.occasion) + '</b>' : ''}${r.position ? ', securing <b>' + esc(r.position) + '</b>' : ''}.${r.purpose ? ' This certificate is issued for ' + esc(r.purpose) + '.' : ''}</p>${sign('Class Teacher', 'Principal')}`;
  else if (m.print === 'idcard') html = `<div class="pr-cards"><div class="pr-card"><h4>${esc(META.school)}</h4><b>${esc(r.kind)}</b><br>${esc(s.name || '')}<br>Class ${esc(s.cls || '')}${s.section ? '-' + esc(s.section) : ''} · Adm ${esc(s.adm || '')}<br>Father: ${esc(s.father || '')}<br>Mobile: ${esc(s.phone || '')}${r.blood ? '<br>Blood group: ' + esc(r.blood) : ''}${r.route ? '<br>Route: ' + esc(r.route) : ''}<br>Valid till: ${dmy(r.valid)}</div></div>`;
  else if (m.print === 'gatepass') html = `${head()}<div class="pr-title">GATE PASS</div>${stu}${kv(['no','date','time','kind','person','relation','phone','reason'])}${sign('Guardian / Employee', 'Office')}`;
  else if (m.print === 'admit') html = `${head()}<div class="pr-title">ADMIT CARD — ${esc(r.exam)}</div>${stu}${kv(['roll','room','noDues','temporary'])}${sign('Class Teacher', 'Principal')}`;
  else if (m.print === 'receipt') html = `${head()}<div class="pr-title">${m.id === 'oldreceipt' ? 'FEE RECEIPT (copy from old ERP)' : 'RECEIPT'}</div>${stu}${kv(m.fields.map(f => f.k).filter(k => k !== 'student' && r[k] !== '' && r[k] !== null && r[k] !== undefined))}${sign('Parent', 'Accountant')}`;
  else html = `${head()}<div class="pr-title">${esc(m.title)}</div>${kv(m.fields.map(f => f.k))}${sign('', 'Principal')}`;
  $('regPrint').innerHTML = `<div class="pr-page">${html}</div>`; window.print();
}
async function openReport(k) {
  try {
    const { report: R } = await api('report&k=' + k), money = new Set(R.money || []);
    $('regDialogBody').innerHTML = `<h2>${esc(R.title)}</h2><div class="rg-wrap" style="margin:12px 0"><table class="rg-table"><thead><tr>${R.cols.map(c => `<th>${esc(c[1])}</th>`).join('')}</tr></thead><tbody>${R.rows.map(r => `<tr class="${r.low ? 'rg-low' : ''}">${R.cols.map(c => `<td class="${typeof r[c[0]] === 'number' ? 'n' : ''}">${esc(money.has(c[0]) ? rs(r[c[0]]) : c[0] === 'date' ? dmy(r[c[0]]) : r[c[0]])}</td>`).join('')}</tr>`).join('') || `<tr><td colspan="${R.cols.length}">No entries yet.</td></tr>`}</tbody></table></div><div class="rg-tools" style="justify-content:flex-end"><button onclick="$('regDialog').close()">Close</button></div>`;
    $('regDialog').showModal();
  } catch (e) { toast(e.message); }
}
function openImport() {
  const m = mod(); IMPORT = null;
  $('regDialogBody').innerHTML = `<h2>Import into ${esc(m.title)}</h2><div class="rg-note">Choose the JSON file prepared from the old ERP (<code>{"module":"${m.id}","rows":[…]}</code>). You will see a preview first. Rows that already exist are never overwritten.</div>
    <input type="file" accept=".json,application/json" onchange="readImport(this.files[0])"><div id="impOut" style="margin-top:12px"></div><div class="rg-tools" style="margin-top:14px;justify-content:flex-end"><button onclick="$('regDialog').close()">Close</button></div>`;
  $('regDialog').showModal();
}
async function readImport(file) {
  const out = $('impOut'); if (!file) return;
  try {
    const d = JSON.parse(await file.text()), rows = Array.isArray(d) ? d : d.rows;
    if (!Array.isArray(rows)) throw Error('This file has no rows.');
    if (d.module && d.module !== CUR) throw Error('This file is for "' + d.module + '", not ' + mod().title + '.');
    IMPORT = rows; const p = await api('import', { m: CUR, rows, apply: false });
    out.innerHTML = `<div class="rg-kpis"><div class="rg-kpi"><b>${p.total}</b><small>rows in file</small></div><div class="rg-kpi"><b>${p.toAdd}</b><small>new</small></div><div class="rg-kpi"><b>${p.unchanged}</b><small>already here, same</small></div><div class="rg-kpi"><b>${p.conflicts.length}</b><small>already here, different</small></div></div>
      ${p.conflicts.length ? `<p>These rows already exist with different values, so the import is blocked: ${esc(p.conflicts.slice(0, 15).join(', '))}</p>` : p.toAdd ? `<button class="go" onclick="applyImport()">Import ${p.toAdd} rows</button>` : '<p>Nothing new to import.</p>'}`;
  } catch (e) { out.innerHTML = `<p style="color:#a3360a">${esc(e.message)}</p>${e.rows ? '<ul>' + e.rows.map(x => `<li>${esc(x)}</li>`).join('') + '</ul>' : ''}`; }
}
async function applyImport() {
  if (BUSY || !IMPORT) return; BUSY = true;
  try { const p = await api('import', { m: CUR, rows: IMPORT, apply: true }); toast(p.added + ' rows imported'); $('regDialog').close(); await openMod(CUR); } catch (e) { toast(e.message); } finally { BUSY = false; }
}
function openCarry() {
  const sessions = [...new Set(ROWS.map(r => r.session))].sort();
  $('regDialogBody').innerHTML = `<h2>Carry old dues to the Fee Desk</h2><div class="rg-note">Each student's net due from the chosen old-ERP session becomes the "old due" in the Fee Desk. Students who already have an old due there are skipped. Nothing is overwritten.</div>
    <select id="carrySes">${sessions.map(s => `<option>${esc(s)}</option>`).join('')}</select><div id="carryOut" style="margin-top:12px"></div>
    <div class="rg-tools" style="margin-top:14px;justify-content:flex-end"><button onclick="$('regDialog').close()">Close</button><button onclick="carry(false)">Preview</button></div>`;
  $('regDialog').showModal();
}
async function carry(apply) {
  try {
    const p = await api('carry', { session: $('carrySes').value, apply });
    $('carryOut').innerHTML = `<div class="rg-kpis"><div class="rg-kpi"><b>${p.students}</b><small>students</small></div><div class="rg-kpi"><b>${rs(p.total)}</b><small>old dues</small></div><div class="rg-kpi"><b>${p.skipped.length}</b><small>skipped (old due already set)</small></div></div>${apply ? '<p><b>Done.</b> The Fee Desk now shows these old dues.</p>' : p.students ? `<button class="go" onclick="carry(true)">Carry ${rs(p.total)} for ${p.students} students</button>` : ''}`;
  } catch (e) { toast(e.message); }
}
boot();
