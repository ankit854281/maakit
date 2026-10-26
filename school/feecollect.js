'use strict';
// Fee Receipt — the old ERP's Fee › Fee Receipt (studentfee.php), same form and the same maths.
// The calculation functions below follow the old page's getfcalcu(), getcalcfee(), calcuamt(), calcudis(),
// calcuamtmnth(), getpmode() and validateForm() line by line; the server checks everything again (feecollect_lib.php).
if (new URLSearchParams(location.search).get('look') === 'classic') document.documentElement.classList.add('classic');
const $ = id => document.getElementById(id);
const esc = v => String(v ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
const R = p => (Math.round(Number(p) || 0) / 100).toFixed(2).replace(/\.00$/, '');        // paise -> rupee text like the old ERP
const P = v => Math.round((parseFloat(String(v).replace(/,/g, '')) || 0) * 100);           // rupee text -> paise
const CLS = ['PG', 'LKG', 'UKG', '1st', '2nd', '3rd', '4th', '5th', '6th', '7th', '8th', '9th', '10th', '11th', '12th'];
const MORDER = [4, 5, 6, 7, 8, 9, 10, 11, 12, 1, 2, 3];
let ST = null, SEL = { cls: '', sec: '', sid: '', by: 'name' }, LD = null, F = null, START = null, BUSY = false;
const SES0 = (new URLSearchParams(location.search).get('session') || '').replace(/^(20\d\d)-20(\d\d)$/, '$1-$2');

function toast(t) { const el = $('ofcToast'); el.textContent = t; clearTimeout(toast.t); toast.t = setTimeout(() => { el.textContent = ''; }, 7000); }
async function api(a, body, q) {
  const r = await fetch('feecollect_api.php?a=' + a + (q ? '&' + new URLSearchParams(q) : ''), { method: body ? 'POST' : 'GET', headers: { 'Content-Type': 'application/json', 'X-School-Token': localStorage.getItem('sch_token') || '' }, ...(body ? { body: JSON.stringify(body) } : {}) });
  let d; try { d = await r.json(); } catch (e) { throw Error('The server did not answer properly. Please try again.'); }
  if (!r.ok || !d.ok) throw Error(d.error || 'Could not complete this.');
  return d;
}
const nonce = () => [...crypto.getRandomValues(new Uint8Array(16))].map(b => b.toString(16).padStart(2, '0')).join('');

async function boot(ses) {
  try {
    ST = await api('state', null, { ses: ses || SES0 || '' }); START = null;
    const want = boot.sid === undefined ? (boot.sid = new URLSearchParams(location.search).get('sid') || '') : '';   // opened from Student Search
    const w = want && ST.students.find(x => x.id === want); if (w) { SEL.cls = w.cls; SEL.sec = w.section; SEL.sid = w.id; }
    if (SEL.sid) await openStudent(SEL.sid, true); else render();
  }
  catch (e) { $('ofcRoot').innerHTML = `<div class="fr-box"><h3>Fee Receipt</h3><p>${esc(e.message)}</p></div>`; }
}
async function openStudent(sid, keep) {
  SEL.sid = sid; LD = null; F = null;
  if (!sid) return render();
  render();
  try { LD = await api('ledger', null, { ses: ST.ses, sid }); F = newForm(); } catch (e) { toast(e.message); }
  render();
}
function newForm() { return { upto: 'No', chk: {}, dchk: {}, npaid: {}, total: '', late: '0', discount: '0', paid: '', apply: '', date: ST.today, remark: '', mode: 'Cash', bank: '', cheque: '', chequeDate: '', depositBank: '', depositDate: ST.today, fdate: '', fremark: '' }; }
const heads = () => (LD && LD.ledger ? LD.ledger.heads : []);
const bal = h => h.amt - h.paid;
const open = h => bal(h) !== 0 && !h.na && bal(h) > 0;

// ---- old getfcalcu(): "Upto Month" picks every unpaid head of each month up to the chosen month (All = every month)
function fcalcu() {
  heads().forEach(h => { F.npaid[h.i] = 0; F.chk[h.i] = false; });
  let namt = 0; const s1 = F.upto;
  if (s1 === 'All') heads().forEach(h => { if (open(h) && !F.dchk[h.i]) { F.chk[h.i] = true; F.npaid[h.i] = bal(h); namt += bal(h); } });
  if (s1 !== 'All' && s1 !== 'No') for (const k1 of MORDER) {
    heads().forEach(h => { if (open(h) && h.m === k1 && !F.dchk[h.i]) { F.chk[h.i] = true; F.npaid[h.i] = bal(h); namt += bal(h); } });
    if (String(k1) === s1) break;
  }
  F.discount = '0'; F.total = R(namt); F.apply = R(namt);
  F.paid = R(namt + P(F.late) - 0);
}
// ---- old getcalcfee(): typing Total Fee spreads it over the heads in order (part payment allowed)
function calcfee() {
  const balance = LD.totals.balance; let totalfee = P(F.total);
  if (totalfee > balance) { toast('not received negative amount'); F.total = '0'; F.paid = '0'; F.discount = '0'; F.late = '0'; heads().forEach(h => { F.npaid[h.i] = 0; F.chk[h.i] = false; }); return; }
  heads().forEach(h => { F.npaid[h.i] = 0; F.chk[h.i] = false; });
  F.discount = '0'; F.paid = R(totalfee + P(F.late) - 0);
  for (const m of MORDER) for (const h of heads().filter(x => x.m === m)) {
    if (bal(h) !== 0 && !h.na && totalfee > 0 && !F.dchk[h.i]) { const ks = Math.min(totalfee, bal(h)); F.npaid[h.i] = ks; F.chk[h.i] = true; totalfee -= ks; }
  }
}
// ---- old calcuamt(): ticking heads by hand
function calcuamt() {
  let net = 0;
  heads().forEach(h => { if (F.dchk[h.i]) F.chk[h.i] = false; if (F.chk[h.i]) { net += bal(h); F.npaid[h.i] = bal(h); } else F.npaid[h.i] = 0; });
  F.total = R(net); F.paid = R(net + P(F.late));
}
// ---- old calcudis(): Currently Received = Total Fee + Late Fee - Discount
function calcudis() { F.paid = R(P(F.total) + P(F.late) - P(F.discount)); }
// ---- old calcuamtmnth(): the month row's tick selects every open head of that month
function tickMonth(m) { heads().forEach(h => { if (h.m === m && open(h)) F.chk[h.i] = true; }); calcuamt(); render(); }
// ---- old getpmode(): bank fields for anything but Cash; deposit bank chosen from the modes it accepts
function setMode(v) {
  F.mode = v;
  const b = ST.banks.find(x => (x.modes || []).includes(v)); if (b) F.depositBank = b.name;
  if (v !== 'Cash') { if (v !== 'Cheque') { F.bank = 'NA'; F.cheque = 'NA'; F.chequeDate = F.date; } }
  else { F.bank = ''; F.cheque = ''; F.chequeDate = ''; F.depositBank = ''; F.depositDate = ''; }
  render();
}
// ---- old validateForm()
async function submitFee() {
  if (BUSY) return;
  calcudis();
  const total = P(F.total);
  if (total > LD.totals.balance) { F.paid = '0'; F.discount = '0'; F.late = '0'; F.total = '0'; render(); return toast('not received negative amount-2'); }
  if (F.mode !== 'Cash' && !F.depositBank) return toast('Select Deposit Bank Name First');
  if (F.mode !== 'Cash' && !F.depositDate) return toast('Enter Bank Deposit Date');
  if (LD.oldDue > 0) return toast('Firstly Paid Old Due Amount');
  if (F.paid === '' || !total) return toast('Select Month');
  if (isNaN(parseFloat(F.paid))) return toast('Please Check Amount');
  const alloc = heads().filter(h => F.npaid[h.i] > 0).map(h => ({ i: h.i, amt: R(F.npaid[h.i]) }));
  if (alloc.reduce((a, x) => a + P(x.amt), 0) !== total) return toast('Tick the fee heads or use Upto Month so the heads add up to the Total Fee.');
  if (!confirm('Are you sure you want to Add this Record ?')) return;
  BUSY = true; render();
  try {
    const d = await api('collect', { ses: ST.ses, sid: SEL.sid, alloc, late: F.late, discount: F.discount, date: F.date, mode: F.mode, bank: F.bank, cheque: F.cheque, chequeDate: F.chequeDate,
      depositBank: F.depositBank, depositDate: F.depositDate, remark: F.remark, followupDate: F.fdate, followupRemark: F.fremark, nonce: F.nonce || (F.nonce = nonce()) });
    toast('Receipt ' + d.receipt.no + ' saved.'); BUSY = false;
    const last = d.receipt; ST = await api('state', null, { ses: ST.ses }); await openStudent(SEL.sid, true); printReceipt(last);
  } catch (e) { BUSY = false; toast(e.message); render(); }
}
async function cancelReceipt(no) {
  const reason = prompt('Why is receipt ' + no + ' cancelled? (the fee heads it paid become Not Paid again)'); if (!reason) return;
  try { await api('cancel', { ses: ST.ses, no, reason, nonce: nonce() }); toast('Receipt ' + no + ' cancelled.'); await openStudent(SEL.sid, true); } catch (e) { toast(e.message); }
}
async function startPreview() { try { START = await api('start', { ses: ST.ses, apply: false }); render(); } catch (e) { toast(e.message); } }
async function startApply() {
  const from = $('stFrom').value, c = $('stConfirm').value.trim();
  try { await api('start', { ses: ST.ses, apply: true, confirm: c, from }); toast('Fee Receipt started for ' + ST.ses + '.'); await boot(ST.ses); } catch (e) { toast(e.message); }
}
async function copyLedger() { const from = $('nlFrom').value; if (!from) return toast('Choose a classmate.'); try { await api('newLedger', { ses: ST.ses, sid: SEL.sid, from }); await openStudent(SEL.sid, true); } catch (e) { toast(e.message); } }

// ---------------------------------------------------------------- drawing
function opt(v, t, on) { return `<option value="${esc(v)}" ${on ? 'selected' : ''}>${esc(t)}</option>`; }
function stuLabel(s) { return s.name + '-' + (s.father || '') + (s.inactive ? ' (Inactive)' : ''); }
function render() {
  if (!ST) return;
  const list = ST.students.filter(s => (!SEL.cls || s.cls === SEL.cls) && (!SEL.sec || s.section === SEL.sec)).sort((a, b) => a.name.localeCompare(b.name));
  const secs = [...new Set(ST.students.filter(s => s.cls === SEL.cls).map(s => s.section).filter(Boolean))].sort();
  const byField = { name: s => s.name, mobile: s => s.phone, father: s => s.father, scholar: s => s.adm }[SEL.by];
  const live = ST.live;
  $('ofcRoot').innerHTML = `<div class="fr">
  <h3 class="fr-title">Fee Receipt <span class="fr-red">${live ? '' : 'NR - Not Received'}</span></h3>
  <div class="fr-sel">
    <label>Session<select onchange="SEL.sid='';LD=null;boot(this.value)">${ST.sessions.map(x => opt(x, x.replace(/^(20\d\d)-(\d\d)$/, '$1-20$2'), x === ST.ses)).join('')}</select></label>
    <label>School<select><option>${esc(ST.school)}</option></select></label>
    <label>Class<select onchange="SEL.cls=this.value;SEL.sec='';openStudent('')">${opt('', 'Select Class', !SEL.cls)}${CLS.map(c => opt(c, c, c === SEL.cls)).join('')}</select></label>
    <label>Section<select onchange="SEL.sec=this.value;openStudent('')">${opt('', 'Select', !SEL.sec)}${secs.map(c => opt(c, c, c === SEL.sec)).join('')}</select></label>
    <label>Name<select onchange="openStudent(this.value)">${opt('', 'Select Name', !SEL.sid)}${list.map(s => opt(s.id, stuLabel(s), s.id === SEL.sid)).join('')}</select></label>
    <span></span>
    <label>Search By<select onchange="SEL.by=this.value;render()">${[['name', 'Student Name'], ['mobile', 'Mobile No'], ['father', "Father's Name"], ['scholar', 'Scholar No']].map(([v, t]) => opt(v, t, v === SEL.by)).join('')}</select></label>
    <label>Search For<input list="frList" placeholder="(Enter Student Name &amp; Select From List)" onchange="const s=ST.students.find(x=>(x.name+' · '+x.cls+(x.section?' '+x.section:'')+' · '+(x.father||'')+' · '+(x.phone||'')+' · '+(x.adm||''))===this.value);if(s){SEL.cls=s.cls;SEL.sec=s.section;openStudent(s.id)}">
      <datalist id="frList">${ST.students.filter(s => byField(s)).map(s => `<option value="${esc(s.name + ' · ' + s.cls + (s.section ? ' ' + s.section : '') + ' · ' + (s.father || '') + ' · ' + (s.phone || '') + ' · ' + (s.adm || ''))}">${esc(byField(s))}</option>`).join('')}</datalist></label>
  </div>
  ${live ? `<p class="fr-note">Fees for ${esc(ST.ses)} are taken here from ${esc(live.from)} (started by ${esc(live.by)}). Next receipt number: <b>${esc(ST.next)}</b>.</p>` : startBox()}
  ${SEL.sid ? (LD ? cardHtml() : '<p class="fr-note">Opening…</p>') : ''}
  </div>`;
}
function startBox() {
  if (!ST.isPrincipal) return `<p class="fr-note">Fees for ${esc(ST.ses)} are still taken in the old ERP. The Principal switches this on once the old ERP stops taking fees.</p>`;
  if (!START) return `<div class="fr-box"><b>Fees for ${esc(ST.ses)} are still taken in the old ERP.</b> When the old ERP stops taking fees, start this session here: every child's fee card is copied from the Old ERP Fee Card and receipt numbers continue from the old ERP. <button onclick="startPreview()">Check before starting</button></div>`;
  return `<div class="fr-box"><b>Start Fee Receipt for ${esc(ST.ses)}</b>
    <p>${START.create} fee cards will be copied (${START.already} already here). ${START.skipped.length} not copied: ${esc(START.skipped.slice(0, 8).join(', '))}${START.skipped.length > 8 ? '…' : ''}.</p>
    <p>First receipt number here: <b>${esc(START.firstNo || '')}</b>. The cards were copied from the old ERP on <b>${esc(START.copiedOn || '?')}</b> — any fee taken in the old ERP after that day is NOT on them. Copy the cards again first if the old ERP took fees after that day.</p>
    <label>First date fees are taken here <input type="date" id="stFrom" value="${esc(ST.today)}"></label> <label>Type START <input id="stConfirm" autocomplete="off" style="width:90px"></label> <button onclick="startApply()">Start</button></div>`;
}
// ---- the card, laid out cell by cell like the old studentfee.php (table.k: Verdana 11px, 1px black borders; label | gap | value | label | value)
const FR_NOTE = 'Not in this ERP yet';
function goParent(v, p) { try { if (parent && parent !== window && parent.go) return parent.go(v, p); } catch (e) {} toast(FR_NOTE); }
function profRows() {
  const c = LD.card, L = LD.ledger, s = ST.students.find(x => x.id === SEL.sid) || {};
  let rows = c && Array.isArray(c.profile) ? c.profile.slice(3).filter(r => !/^Custom Text/.test(r[0] || '')) : null;
  if (!rows) rows = [['Session', '', ST.ses.replace(/^(20\d\d)-(\d\d)$/, '$1-20$2'), 'Scholar No /Roll No', s.adm], ['Name', '', s.name, "Father's Name", s.father], ["Father's Mobile No", '', s.phone, 'Schemes', L ? L.scheme : '']];
  return rows.map(r => r.length <= 3 ? `<tr><td>${esc(r[0])}</td><td></td><td colspan="3">${esc(r[2] || '')}</td></tr>`
    : `<tr><td>${esc(r[0])}</td><td></td><td>${/^(Name|Father's Name)$/.test(r[0]) ? '<b>' + esc(r[2] || '') + '</b>' : esc(r[2] || '')}</td><td>${esc(r[3] || '')}</td><td>${r[3] === 'Schemes' ? '<span class="fr-red">' + esc(r[4] || '') + '</span>' : /Name$/.test(r[3] || '') ? '<b>' + esc(r[4] || '') + '</b>' : esc(r[4] || '')}</td></tr>`).join('');
}
function topRows() {
  const c = LD.card, L = LD.ledger, s = ST.students.find(x => x.id === SEL.sid) || {}, pr = c && Array.isArray(c.profile) ? c.profile : [];
  const ph = String((L && L.mobile) || s.phone || (pr[8] || [])[2] || '').replace(/\D/g, '').slice(-10);
  const cls = (pr[1] || [])[2] || [(L && L.cls) || s.cls, (L && L.section) || s.section].filter(Boolean).join(' - ');
  const st = String((pr[1] || [])[3] || 'NA | Active').split('|');
  const sec = (pr[2] || [])[2] || (L && L.section) || s.section || '';
  const img = /^data:image\/(jpeg|png|webp);base64,/.test(LD.photo || '') ? `<img src="${LD.photo}" width="60" height="60" alt="" style="object-fit:cover">` : '<span class="fr-noimg"></span>';
  return `<tr><td colspan="3"><span class="fr-red">${esc(ST.school)}</span></td><td rowspan="3" style="text-align:center">${img}</td><td>${ph ? `<a class="fb fb-g" href="tel:+91${ph}">Call</a> <a class="fb fb-w" href="https://wa.me/91${ph}" target="_blank" rel="noopener">Whatsapp</a>` : ''}</td></tr>
    <tr><td>Class</td><td></td><td>${esc(cls)}</td><td><b>${esc(st[0].trim())}</b> | <span class="fr-red">${esc((st[1] || 'Active').trim())}</span></td></tr>
    <tr><td>Section</td><td></td><td>${esc(sec)}</td><td><a class="fb fb-r" onclick="goParent('fcalls')">Fee Followup</a> <a class="fb fb-g" onclick="goParent('office',{m:'tag'})">Tag Followup</a></td></tr>`;
}
function cardHtml() {
  const L = LD.ledger, live = ST.live, T = (LD.card && LD.card.tables) || {};
  const formOn = live && L && ST.canCollect;
  const box = (title, rows, h, opts) => `<tr><td colspan="5"><b class="fr-h">${title}</b></td></tr><tr><td colspan="5"><div class="fr-scroll" style="height:${h}px">${opts && opts.html ? opts.html : tbl(rows || [[title]], opts && opts.months)}</div></td></tr>`;
  const sib = T.siblings && T.siblings.length > 1 ? T.siblings : null;
  const left = `<table class="k fr-k">${topRows()}${profRows()}
    ${formOn ? formHtml() : ''}
    ${live && !L ? `<tr><td colspan="5">${noLedger()}</td></tr>` : ''}
    ${!live ? `<tr><td colspan="5"><div class="fr-note">Fees for ${esc(ST.ses.replace(/^(20\d\d)-(\d\d)$/, '$1-20$2'))} are still taken in the old ERP — this card was copied from it on ${esc((LD.card || {}).asOn || '')}.</div></td></tr>` : ''}
    <tr><td colspan="5"><table class="fr-btns"><tr>
      <td><a class="fb fb-d" onclick="toast('Online payment is not set up in this ERP.')">Online Transaction</a></td><td><a class="fb fb-g" onclick="goParent('office',{m:'document'})">Document</a></td>
      <td><a class="fb fb-i" onclick="goParent('office',{m:'cert'})">Fee Cert.</a></td><td><a class="fb fb-r" onclick="window.print()">Fee Card</a></td>
      <td><a class="fb fb-r" onclick="goParent('office',{m:'oldfee'})">Fee Statement</a></td><td><a class="fb fb-g" onclick="goParent('office',{m:'oldfeecard'})">Fees Consolidate</a></td></tr></table></td></tr>
    ${formOn ? `<tr><td></td><td colspan="3" style="text-align:center"><button class="fr-submit" onclick="submitFee()" ${BUSY ? 'disabled' : ''}>${BUSY ? 'Saving…' : 'Submit'}</button></td><td></td></tr>` : ''}
    ${sib ? `<tr><td colspan="5"><div class="fr-scroll" style="height:150px">${sibHtml(sib)}</div></td></tr>` : ''}
    ${box('Fee Receipt', null, 150, { html: receiptsHtml() })}
    ${box('Fee Refund', T['Fee Refund'] || [['Sno', 'Date', 'Fee', 'Amount', 'Remark', 'Print', 'Delete']], 150)}
    ${box('Enquiry Receipt', T['Enquiry Receipt'] || [['Sno', 'Date', 'Head', 'Amount', 'Remark', 'Print']], 100)}
    ${box('Cancel Receipt/Cheque', T['Cancel Receipt/Cheque'] || [['Sno', 'Date', 'Cheque Details', 'Amount', 'Remark']], 100)}</table>`;
  const rbox = (title, rows, h) => `<b class="fr-h">${title}</b><div class="fr-scroll" style="height:${h}px">${tbl(rows)}</div>`;
  const right = `<div class="fr-scroll" style="height:350px">${L ? detailsHtml() : oldDetails()}</div>
    ${rbox('Fees Transfer', T['Fees Transfer'] || [['Sno', 'Date', 'Type', 'Transfer Session', 'Amount', 'Remark', 'Delete']], 70)}
    ${rbox('Other Fee Receipt', T['Other Fee Receipt'] || [['Sno', 'Date', 'Fee', 'Amount', 'Remark', 'Print', 'Delete']], 100)}
    ${sumHtml()}
    ${rbox('Fee Discount', T['Fee Discount'] || [['Sno', 'Edit', 'Date', 'Month', 'Fee', 'Discount Amount', 'UName', 'Delete']], 150)}`;
  return `<table class="fr-outer"><tr><td class="fr-left">${left}</td><td class="fr-right">${right}</td></tr>
    <tr><td class="fr-left"><b class="fr-h">Fee Adjustment</b><div class="fr-scroll" style="height:150px">${tbl(T['Fee Adjustment'] || [['Sno', 'Date', 'Amount', 'Remark', 'Print', 'Delete']])}</div></td><td></td></tr></table>`;
}
function sibHtml(rows) {
  return `<table class="k fr-t"><tr class="fr-g">${rows[0].map(h => `<td class="c">${/Brother|Roll/.test(h) ? '<b>' + esc(h) + '</b>' : esc(h)}</td>`).join('')}</tr>${rows.slice(1).map(r => {
    const m = ST.students.find(x => x.name.toLowerCase() === String(r[3] || '').toLowerCase() && String(r[6] || '').startsWith(x.cls));
    return `<tr>${r.map((c, i) => `<td class="c">${i === 1 ? (m ? `<a class="fb fb-g" onclick="SEL.cls='${esc(m.cls)}';SEL.sec='${esc(m.section)}';openStudent('${esc(m.id)}')">Fee Receipt</a>` : '') : i === 7 || i === 8 ? '<b>' + esc(c) + '</b>' : esc(c)}</td>`).join('')}</tr>`;
  }).join('')}</table>`;
}
function noLedger() {
  if (!ST.isPrincipal) return '<p class="fr-note">This child has no fee card for this session yet. The Principal sets it.</p>';
  const mates = ST.students.filter(s => s.ledger && s.id !== SEL.sid).sort((a, b) => (a.cls === SEL.cls ? 0 : 1) - (b.cls === SEL.cls ? 0 : 1) || a.name.localeCompare(b.name));
  return `<div class="fr-box">This child has no fee card for ${esc(ST.ses)} (for example a new admission). Copy the fee heads from a classmate with the same scheme — nothing is marked paid.
    <select id="nlFrom"><option value="">Choose classmate</option>${mates.map(s => opt(s.id, s.cls + ' · ' + s.name)).join('')}</select> <button onclick="copyLedger()">Copy fee heads</button></div>`;
}
function formHtml() {
  const nc = F.mode !== 'Cash';
  const inp = (k, extra) => `<input style="width:120px" value="${esc(F[k])}" oninput="F.${k}=this.value;${extra || ''}" autocomplete="off">`;
  const row = (a, b, c, d) => `<tr><td>${a}</td><td></td><td>${b}</td><td>${c}</td><td>${d}</td></tr>`;
  return row('Upto Month', `<select style="width:100px" onchange="F.upto=this.value;fcalcu();render()">${opt('No', 'No', F.upto === 'No')}${opt('All', 'All', F.upto === 'All')}${MORDER.map(m => opt(String(m), ST.months[m], F.upto === String(m))).join('')}</select>`, 'Apply Fee', `<input style="width:100px" value="${esc(F.apply)}" readonly>`)
    + row('Date', `<input type="date" style="width:130px" value="${esc(F.date)}" max="${esc(ST.today)}" min="${esc(ST.live.from)}" onchange="F.date=this.value">`, 'Receipt No', esc(ST.next))
    + row('Total Fee', `<input id="fTotal" style="width:100px" value="${esc(F.total)}" oninput="F.total=this.value;calcfee();rerenderKeep('fTotal')" autocomplete="off">`, '<span class="fr-red">Late Fee</span>', `<input id="fLate" style="width:100px" value="${esc(F.late)}" oninput="F.late=this.value;calcudis();rerenderKeep('fLate')" autocomplete="off">`)
    + row('Discount', `<input id="fDisc" style="width:100px" value="${esc(F.discount)}" oninput="F.discount=this.value;calcudis();rerenderKeep('fDisc')" autocomplete="off">`, 'Currently Received', `<input style="width:100px" value="${esc(F.paid)}" readonly>`)
    + row('Remark', `<textarea style="width:100px;height:30px" oninput="F.remark=this.value">${esc(F.remark)}</textarea>`, '', '')
    + row('Payment Mode', `<select style="width:35mm" onchange="setMode(this.value)">${ST.modes.map(m => opt(m, m, m === F.mode)).join('')}</select>`, nc ? 'Bank Name' : '', nc ? inp('bank') : '')
    + (nc ? row('Cheque No', inp('cheque'), 'Cheque Date', `<input type="date" style="width:130px" value="${esc(F.chequeDate)}" onchange="F.chequeDate=this.value">`)
      + row('Deposit Bank Name', `<select style="width:35mm" onchange="F.depositBank=this.value">${opt('', 'Select', !F.depositBank)}${ST.banks.map(b => opt(b.name, b.name, b.name === F.depositBank)).join('')}</select>`, 'Bank Deposit Date', `<input type="date" style="width:130px" value="${esc(F.depositDate)}" onchange="F.depositDate=this.value">`) : '')
    + row('Followup Date', `<input type="date" style="width:130px" value="${esc(F.fdate)}" onchange="F.fdate=this.value">`, 'Followup Remark', `<textarea style="width:100px;height:30px" oninput="F.fremark=this.value">${esc(F.fremark)}</textarea>`);
}
function rerenderKeep(id) { const el = $(id), pos = el ? el.selectionStart : null; render(); const n = $(id); if (n) { n.focus(); if (pos !== null) n.setSelectionRange(pos, pos); } }
function detailsHtml() {
  const H = heads(), formOn = ST.live && ST.canCollect;
  return `<table class="k1 fr-fee"><tr class="fr-g"><td colspan="8" class="c"><b>Fee Details</b></td></tr>
    <tr class="fr-y"><td></td><td class="c"><b>D/A</b></td><td class="c"><b>Fee Name</b></td><td class="c"><b>Amount</b></td><td class="c"><b>Paid/Disc Amount</b></td><td class="c"><b>Balance</b></td><td class="c"><b>N Paid</b></td><td class="c"><b>Status</b></td></tr>
    ${MORDER.filter(m => H.some(h => h.m === m)).map(m => `<tr class="fr-g"><td colspan="8">${formOn ? `<input type="checkbox" onclick="tickMonth(${m})">` : ''}<b>${esc(ST.months[m])}</b></td></tr>` +
      H.filter(h => h.m === m).map(h => `<tr class="fr-y0"><td style="text-align:right">${formOn ? `<input type="checkbox" ${F.chk[h.i] ? 'checked' : ''} ${open(h) ? '' : 'disabled'} onclick="F.chk[${h.i}]=this.checked;calcuamt();render()">` : ''}</td>
        <td>${formOn ? `<input type="checkbox" ${F.dchk[h.i] ? 'checked' : ''} ${open(h) ? '' : 'disabled'} onclick="F.dchk[${h.i}]=this.checked;calcuamt();render()">` : ''}</td>
        <td>${esc(h.name)}</td><td>${R(h.amt)}</td><td>${R(h.paid)}</td><td>${R(bal(h))}</td><td>${F && F.npaid[h.i] ? R(F.npaid[h.i]) : R(bal(h))}</td><td>${h.na ? 'Not-Active' : bal(h) <= 0 ? 'Paid' : 'Not Paid'}</td></tr>`).join('')).join('')}</table>`;
}
function oldDetails() {
  if (!LD.cardMonths) return '<p class="fr-note">No fee details for this session.</p>';
  return `<table class="k1 fr-fee"><tr class="fr-g"><td colspan="8" class="c"><b>Fee Details</b></td></tr>
    <tr class="fr-y"><td></td><td class="c"><b>D/A</b></td><td class="c"><b>Fee Name</b></td><td class="c"><b>Amount</b></td><td class="c"><b>Paid/Disc Amount</b></td><td class="c"><b>Balance</b></td><td class="c"><b>N Paid</b></td><td class="c"><b>Status</b></td></tr>
    ${LD.cardMonths.slice(1).map(r => r.length === 1 ? `<tr class="fr-g"><td colspan="8"><b>${esc(r[0])}</b></td></tr>` : `<tr class="fr-y0">${r.slice(0, 8).map(c => `<td>${esc(c)}</td>`).join('')}</tr>`).join('')}</table>`;
}
function sumHtml() {
  const t = LD.totals, c = LD.card || {};
  const v = t ? { old: R(LD.oldDue), tr: R((LD.ledger.transferFee) || 0), net: R(t.net), rec: R(t.received), bal: R(t.balance), due: R(t.balance) } : { old: c.oldFee, tr: c.transferFee, net: c.net, rec: c.received, bal: c.balance, due: c.feeDue };
  return `<table class="fr-sum"><tr class="fr-y"><td><b>Old Fee : ${esc(v.old)}</b></td><td><b>Transfer Fee : ${esc(v.tr)}</b></td></tr><tr><td><b>Net Amount : ${esc(v.net)}</b></td><td><b>Currently Received : ${esc(v.rec)}</b></td></tr>
    <tr><td><b>Other Fee Receipt : ${esc(c.otherFee || '0')}</b></td><td><b class="fr-blue">Balance Amount : ${esc(v.bal)}</b></td></tr><tr><td><b>Transport Due : ${esc(c.transportDue || '0')}</b></td><td><b>Fee Due : ${esc(v.due)}</b></td></tr><tr><td><b>Store Due Amount : ${esc(c.storeDue || '0')}</b></td><td></td></tr></table>`;
}
// Old ERP side tables (k5): green head row; Delete/Edit columns stay as headings only — nothing is deleted from here.
function tbl(rows) {
  if (!rows || !rows.length) return '';
  const n = rows[0].length;
  return `<table class="k5 fr-t"><tr class="fr-g">${rows[0].map(h => `<td class="c"><b>${esc(h)}</b></td>`).join('')}</tr>${rows.slice(1).map(r => `<tr>${Array.from({ length: n }, (_, i) => `<td>${/^TOTAL$/i.test(r[i] || '') ? '<b>' + esc(r[i]) + '</b>' : esc(r[i] ?? '')}</td>`).join('')}</tr>`).join('')}</table>`;
}
function receiptsHtml() {
  const old = ((LD.card || {}).receipts || []).slice(1).filter(r => r[2]), mine = LD.receipts || [];
  const rows = old.map((r, i) => ({ sno: i + 1, status: r[1], no: r[2], date: r[3], net: r[4], late: r[5], disc: r[6], paid: r[7], mode: r[8], by: r[9], remark: r[11], old: true }))
    .concat(mine.map(r => ({ status: r.status, no: r.no, date: r.date.split('-').reverse().join('-'), net: R(r.total), late: R(r.late), disc: R(r.discount), paid: R(r.received), mode: r.mode, by: r.by, remark: r.status === 'Cancelled' ? 'Cancelled: ' + r.cancelReason : r.remark, r })));
  rows.forEach((r, i) => r.sno = i + 1);
  const sum = k => R(rows.filter(r => r.status !== 'Cancelled').reduce((a, r) => a + P(r[k]), 0));
  return `<table class="k fr-t fr-rc"><tr class="fr-g">${['Sno', 'Status', 'R.No.', 'Date', 'Net Amt', 'Late Fee', 'Discount', 'Paid Amt', 'PMode', 'UName', 'Uploaded Receipt', 'Remark', 'Print', 'Delete'].map(h => `<td class="c"><b>${h}</b></td>`).join('')}</tr>
    ${rows.map(r => `<tr class="${r.status === 'Cancelled' ? 'fr-cx' : ''}"><td>${r.sno}</td><td>${esc(r.status)}</td><td>${esc(r.no)}</td><td>${esc(r.date)}</td><td class="c">${esc(r.net)}</td><td class="c">${esc(r.late)}</td><td class="c">${esc(r.disc)}</td><td class="c">${esc(r.paid)}</td><td>${esc(r.mode)}</td><td>${esc(r.by)}</td><td></td><td>${esc(r.remark || '')}</td>
      <td><a class="fb fb-g" onclick="${r.old ? `printOld('${esc(r.no)}')` : `printReceipt(LD.receipts.find(x=>x.no==='${esc(r.no)}'))`}">Print</a></td><td>${ST.isPrincipal && !r.old && r.status !== 'Cancelled' ? `<a class="fb fb-r" onclick="cancelReceipt('${esc(r.no)}')">Cancel</a>` : ''}</td></tr>`).join('')}
    <tr><td></td><td></td><td></td><td class="c"><b>Total</b></td><td class="c">${sum('net')}</td><td class="c">${sum('late')}</td><td class="c">${sum('disc')}</td><td class="c">${sum('paid')}</td><td></td><td></td><td></td><td></td><td></td><td></td></tr></table>`;
}

// ---------------------------------------------------------------- receipt print, laid out like the old ERP's print.php
function receiptHtml(d) {
  const t = v => esc(v ?? '');
  return `<div class="or-wrap"><table class="or-head"><tr><td rowspan="2" class="or-logo"><img src="img/snmsis-logo.png" alt=""></td><td class="or-name"><span>${t(ST.school)}</span><br><b>Jagannathpur, Near Sikhdi Khandak, Bhadohi - 221403, UP</b></td></tr>
    <tr><td class="or-bar"><img src="img/receipt-barcode.gif" alt=""></td></tr></table>
    <div class="or-title">${t(d.title)}</div>
    <table class="or-k"><tr><td><b>Receipt No</b></td><td>${t(d.receiptNo)}</td><td><b>Date</b></td><td>${t(d.date)}</td></tr>
      <tr><td><b>Name</b></td><td><b>${t(d.name)}</b></td><td><b>Class</b></td><td>${t(d.cls)}</td></tr>
      <tr><td><b>Adm No</b></td><td>${t(d.adm)}</td><td><b>Mother's Name</b></td><td>${t(d.mother)}</td></tr>
      <tr><td><b>Father's Name</b></td><td>${t(d.father)}</td><td><b>Schemes<br>Transport</b></td><td>${t(d.scheme)}</td></tr></table>
    <table class="or-k or-p"><tr><th>Sno</th><th>PARTICULARS</th><th>Month</th><th>Amount</th></tr>${(d.lines || []).map(l => `<tr><td>${t(l[0])}</td><td>${t(l[1])}</td><td>${t(l[2])}</td><td>${t(l[3])}</td></tr>`).join('')}</table>
    <table class="or-k"><tr><td><b>Balance<br>Amount</b></td><td><b>${t(d.balance)}</b></td><td><b>Payable Fee</b></td><td><b>${t(d.payable)}</b></td></tr>
      <tr><td><b>Late Fee</b></td><td><b>${t(d.lateFee)}</b></td><td><b>Adjustment</b></td><td><b>${t(d.adjustment)}</b></td></tr>
      <tr><td><b>Payment Mode</b></td><td><b>${t(d.mode)}</b></td><td><b>Currently Received</b></td><td><b>${t(d.received)}</b></td></tr>
      <tr><td><b>Bank Name</b></td><td><b>${t(d.bank)}</b></td><td><b>Cheque No/Date</b></td><td><b>${t(d.cheque)}</b></td></tr></table>
    <div class="or-rem"><b>Remark : </b>${t(d.remark)}</div>
    <div class="or-foot"><span><span class="or-copy">Parent Copy</span> Printed on ${new Date().toLocaleString('en-GB')}</span><span class="or-sign">${t(d.user)}<br>Auth. Sign</span></div>${d.cancelled ? '<div class="or-cx">CANCELLED</div>' : ''}</div>`;
}
function printHtml(inner) { $('ofcPrint').innerHTML = `<div class="pr-page">${inner}</div>`; window.print(); }
function printReceipt(r) {
  if (!r) return;
  const y = r.session.replace(/^(20\d\d)-(\d\d)$/, '$1-20$2');
  printHtml(receiptHtml({ title: 'Fee Receipt(' + y + ')', receiptNo: r.no, date: r.date.split('-').reverse().join('-') + ' ' + (r.time || ''), name: r.name, cls: r.cls + (r.section ? ' ' + r.section : ''), adm: r.adm, mother: r.mother, father: r.father, scheme: r.scheme,
    lines: r.lines.map(l => [l[0], l[1], l[2], R(l[3])]), balance: R(r.balanceAfter), payable: R(r.total), lateFee: R(r.late), adjustment: R(r.discount), mode: r.mode, received: R(r.received),
    bank: r.bank, cheque: r.cheque ? r.cheque + (r.chequeDate ? ' / ' + r.chequeDate : '') : '-', remark: r.remark, user: r.by, cancelled: r.status === 'Cancelled' }));
}
async function printOld(no, ses) {
  try {
    let d; try { d = await api('receipt', null, { ses: ses || ST.ses, no }); } catch (e) { return toast('The full old receipt ' + no + ' is not in the Old ERP Receipt Details.'); }
    if (d.receipt) return printReceipt(d.receipt);
    const x = d.old;
    printHtml(receiptHtml({ title: x.title, receiptNo: x.receiptNo, date: x.date, name: x.name, cls: x.cls, adm: x.adm, mother: x.mother, father: x.father, scheme: x.scheme, lines: x.lines, balance: x.balance, payable: x.payable, lateFee: x.lateFee, adjustment: x.adjustment, mode: x.mode, received: x.received, bank: x.bank, cheque: x.cheque, remark: x.remark, user: x.user }));
  } catch (e) { toast(e.message); }
}
// Opened from an old-style report (feereports.php): ?print=<session>|<receipt no> prints that one receipt.
const PRINT0 = new URLSearchParams(location.search).get('print');
if (PRINT0 && /^20\d\d-\d\d\|[\w\/-]{1,30}$/.test(PRINT0)) api('state', null, { ses: PRINT0.split('|')[0] }).then(s => { ST = s; $('ofcRoot').innerHTML = ''; printOld(PRINT0.split('|')[1], PRINT0.split('|')[0]); }).catch(e => toast(e.message)); else boot();
