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
  try { ST = await api('state', null, { ses: ses || SES0 || '' }); START = null; if (SEL.sid) await openStudent(SEL.sid, true); else render(); }
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
function profRows() {
  const c = LD.card, L = LD.ledger;
  if (c && Array.isArray(c.profile)) return c.profile.slice(1).map(r => `<tr><td>${esc(r[0] || '')}</td><td><b>${esc(r[2] || '')}</b></td><td>${esc(r[3] || '')}</td><td><b>${esc(r[4] || '')}</b></td></tr>`).join('');
  const s = ST.students.find(x => x.id === SEL.sid) || {};
  return [['Class', (L && L.cls) || s.cls, 'Section', (L && L.section) || s.section], ['Session', ST.ses.replace(/^(20\d\d)-(\d\d)$/, '$1-20$2'), 'Scholar No /Roll No', s.adm], ['Name', s.name, "Father's Name", s.father], ["Father's Mobile No", s.phone, 'Schemes', L ? L.scheme : '']]
    .map(r => `<tr><td>${esc(r[0])}</td><td><b>${esc(r[1] || '')}</b></td><td>${esc(r[2])}</td><td><b>${esc(r[3] || '')}</b></td></tr>`).join('');
}
function cardHtml() {
  const L = LD.ledger, live = ST.live, T = (LD.card && LD.card.tables) || {};
  const formOn = live && L && ST.canCollect;
  const left = `<table class="fr-prof"><tr><td colspan="3" class="fr-red fr-b">${esc(ST.school)}</td><td rowspan="3" style="text-align:right">${/^data:image\/(jpeg|png|webp);base64,/.test(LD.photo || '') ? `<img src="${LD.photo}" height="60" width="60" alt="" style="object-fit:cover">` : ''}</td></tr>${profRows()}</table>
    ${formOn ? formHtml() : ''}${live && !L ? noLedger() : ''}${!live ? '<p class="fr-note">Old ERP record (copied ' + esc((LD.card || {}).asOn || '') + ').</p>' : ''}`;
  const right = `${L ? detailsHtml() : oldDetails()}
    ${['Fees Transfer', 'Other Fee Receipt'].map(t => T[t] ? `<h4 class="fr-red">${t}</h4>${tbl(T[t])}` : '').join('')}
    ${sumHtml()}`;
  return `<div class="fr-grid"><div>${left}</div><div>${right}</div></div>
    <h4 class="fr-red">Fee Receipt</h4>${receiptsHtml()}
    ${['Fee Refund', 'Enquiry Receipt', 'Cancel Receipt/Cheque', 'Fee Discount', 'Fee Adjustment'].map(t => T[t] && T[t].length > 1 ? `<h4 class="fr-red">${t}</h4>${tbl(T[t])}` : '').join('')}`;
}
function noLedger() {
  if (!ST.isPrincipal) return '<p class="fr-note">This child has no fee card for this session yet. The Principal sets it.</p>';
  const mates = ST.students.filter(s => s.ledger && s.id !== SEL.sid).sort((a, b) => (a.cls === SEL.cls ? 0 : 1) - (b.cls === SEL.cls ? 0 : 1) || a.name.localeCompare(b.name));
  return `<div class="fr-box">This child has no fee card for ${esc(ST.ses)} (for example a new admission). Copy the fee heads from a classmate with the same scheme — nothing is marked paid.
    <select id="nlFrom"><option value="">Choose classmate</option>${mates.map(s => opt(s.id, s.cls + ' · ' + s.name)).join('')}</select> <button onclick="copyLedger()">Copy fee heads</button></div>`;
}
function formHtml() {
  const nc = F.mode !== 'Cash';
  const inp = (k, extra) => `<input value="${esc(F[k])}" oninput="F.${k}=this.value;${extra || ''}" autocomplete="off">`;
  return `<table class="fr-form">
    <tr><td>Upto Month</td><td><select onchange="F.upto=this.value;fcalcu();render()">${opt('No', 'No', F.upto === 'No')}${opt('All', 'All', F.upto === 'All')}${MORDER.map(m => opt(String(m), ST.months[m], F.upto === String(m))).join('')}</select></td><td>Apply Fee</td><td><input value="${esc(F.apply)}" readonly></td></tr>
    <tr><td>Date</td><td><input type="date" value="${esc(F.date)}" max="${esc(ST.today)}" min="${esc(ST.live.from)}" onchange="F.date=this.value"></td><td>Receipt No</td><td><b>${esc(ST.next)}</b></td></tr>
    <tr><td>Total Fee</td><td><input id="fTotal" value="${esc(F.total)}" oninput="F.total=this.value;calcfee();rerenderKeep('fTotal')" autocomplete="off"></td><td class="fr-red">Late Fee</td><td><input id="fLate" value="${esc(F.late)}" oninput="F.late=this.value;calcudis();rerenderKeep('fLate')" autocomplete="off"></td></tr>
    <tr><td>Discount</td><td><input id="fDisc" value="${esc(F.discount)}" oninput="F.discount=this.value;calcudis();rerenderKeep('fDisc')" autocomplete="off"></td><td>Currently Received</td><td><input value="${esc(F.paid)}" readonly></td></tr>
    <tr><td>Remark</td><td><textarea oninput="F.remark=this.value">${esc(F.remark)}</textarea></td><td></td><td></td></tr>
    <tr><td>Payment Mode</td><td><select onchange="setMode(this.value)">${ST.modes.map(m => opt(m, m, m === F.mode)).join('')}</select></td><td>${nc ? 'Bank Name' : ''}</td><td>${nc ? inp('bank') : ''}</td></tr>
    ${nc ? `<tr><td>Cheque No</td><td>${inp('cheque')}</td><td>Cheque Date</td><td><input type="date" value="${esc(F.chequeDate)}" onchange="F.chequeDate=this.value"></td></tr>
    <tr><td>Deposit Bank Name</td><td><select onchange="F.depositBank=this.value">${opt('', 'Select', !F.depositBank)}${ST.banks.map(b => opt(b.name, b.name, b.name === F.depositBank)).join('')}</select></td><td>Bank Deposit Date</td><td><input type="date" value="${esc(F.depositDate)}" onchange="F.depositDate=this.value"></td></tr>` : ''}
    <tr><td>Followup Date</td><td><input type="date" value="${esc(F.fdate)}" onchange="F.fdate=this.value"></td><td>Followup Remark</td><td><textarea oninput="F.fremark=this.value">${esc(F.fremark)}</textarea></td></tr>
    <tr><td colspan="4" style="text-align:center"><button class="fr-submit" onclick="submitFee()" ${BUSY ? 'disabled' : ''}>${BUSY ? 'Saving…' : 'Submit'}</button></td></tr></table>`;
}
function rerenderKeep(id) { const el = $(id), pos = el ? el.selectionStart : null; render(); const n = $(id); if (n) { n.focus(); if (pos !== null) n.setSelectionRange(pos, pos); } }
function detailsHtml() {
  const H = heads(), formOn = ST.live && ST.canCollect;
  return `<table class="fr-t fr-fee"><tr class="fr-g"><td colspan="8" style="text-align:center"><b>Fee Details</b></td></tr>
    <tr class="fr-y"><td></td><td>D/A</td><td>Fee Name</td><td>Amount</td><td>Paid/Disc Amount</td><td>Balance</td><td>N Paid</td><td>Status</td></tr>
    ${MORDER.filter(m => H.some(h => h.m === m)).map(m => `<tr class="fr-g"><td colspan="8">${formOn ? `<input type="checkbox" onclick="tickMonth(${m})"> ` : ''}<b>${esc(ST.months[m])}</b></td></tr>` +
      H.filter(h => h.m === m).map(h => `<tr><td>${formOn ? `<input type="checkbox" ${F.chk[h.i] ? 'checked' : ''} ${open(h) ? '' : 'disabled'} onclick="F.chk[${h.i}]=this.checked;calcuamt();render()">` : ''}</td>
        <td>${formOn ? `<input type="checkbox" ${F.dchk[h.i] ? 'checked' : ''} ${open(h) ? '' : 'disabled'} onclick="F.dchk[${h.i}]=this.checked;calcuamt();render()">` : ''}</td>
        <td>${esc(h.name)}</td><td>${R(h.amt)}</td><td>${R(h.paid)}</td><td>${R(bal(h))}</td><td>${F && F.npaid[h.i] ? R(F.npaid[h.i]) : ''}</td><td>${h.na ? 'Not-Active' : bal(h) <= 0 ? 'Paid' : 'Not Paid'}</td></tr>`).join('')).join('')}</table>`;
}
function oldDetails() { return LD.cardMonths ? tbl([['', 'D/A', 'Fee Name', 'Amount', 'Paid/Disc Amount', 'Balance', 'N Paid', 'Status']].concat(LD.cardMonths.slice(1)), true) : '<p class="fr-note">No fee details for this session.</p>'; }
function sumHtml() {
  const t = LD.totals, c = LD.card || {};
  const v = t ? { old: R(LD.oldDue), tr: R((LD.ledger.transferFee) || 0), net: R(t.net), rec: R(t.received), bal: R(t.balance), due: R(t.balance) } : { old: c.oldFee, tr: c.transferFee, net: c.net, rec: c.received, bal: c.balance, due: c.feeDue };
  return `<table class="fr-sum"><tr><td>Old Fee : <b>${esc(v.old)}</b></td><td>Transfer Fee : <b>${esc(v.tr)}</b></td></tr><tr><td>Net Amount : <b>${esc(v.net)}</b></td><td>Currently Received : <b>${esc(v.rec)}</b></td></tr>
    <tr><td>Other Fee Receipt : <b>${esc(c.otherFee || '0')}</b></td><td>Balance Amount : <b class="fr-blue">${esc(v.bal)}</b></td></tr><tr><td>Transport Due : <b>${esc(c.transportDue || '0')}</b></td><td>Fee Due : <b class="fr-blue">${esc(v.due)}</b></td></tr><tr><td>Store Due Amount : <b>${esc(c.storeDue || '0')}</b></td><td></td></tr></table>`;
}
function tbl(rows, months) {
  if (!rows || !rows.length) return '';
  const head = rows[0], drop = head.map((h, i) => /^(Delete|Edit|Uploaded Receipt|Print)$/i.test(h) ? i : -1).filter(i => i >= 0), keep = r => r.filter((_, i) => !drop.includes(i));
  return `<table class="fr-t"><tr class="fr-g">${keep(head).map(h => `<td><b>${esc(h)}</b></td>`).join('')}</tr>${rows.slice(1).map(r => r.length === 1 && months ? `<tr class="fr-g"><td colspan="${keep(head).length}"><b>${esc(r[0])}</b></td></tr>` : `<tr>${keep(r).map(c => `<td>${esc(c)}</td>`).join('')}</tr>`).join('')}</table>`;
}
function receiptsHtml() {
  const old = ((LD.card || {}).receipts || []).slice(1).filter(r => r[2]), mine = LD.receipts || [];
  const rows = old.map((r, i) => ({ sno: i + 1, status: r[1], no: r[2], date: r[3], net: r[4], late: r[5], disc: r[6], paid: r[7], mode: r[8], by: r[9], remark: r[11], old: true }))
    .concat(mine.map(r => ({ status: r.status, no: r.no, date: r.date.split('-').reverse().join('-'), net: R(r.total), late: R(r.late), disc: R(r.discount), paid: R(r.received), mode: r.mode, by: r.by, remark: r.status === 'Cancelled' ? 'Cancelled: ' + r.cancelReason : r.remark, r })));
  rows.forEach((r, i) => r.sno = i + 1);
  const sum = k => R(rows.filter(r => r.status !== 'Cancelled').reduce((a, r) => a + P(r[k]), 0));
  return `<table class="fr-t"><tr class="fr-g"><td><b>Sno</b></td><td><b>Status</b></td><td><b>R.No.</b></td><td><b>Date</b></td><td><b>Net Amt</b></td><td><b>Late Fee</b></td><td><b>Discount</b></td><td><b>Paid Amt</b></td><td><b>PMode</b></td><td><b>UName</b></td><td><b>Remark</b></td><td><b>Print</b></td>${ST.isPrincipal ? '<td></td>' : ''}</tr>
    ${rows.map(r => `<tr class="${r.status === 'Cancelled' ? 'fr-cx' : ''}"><td>${r.sno}</td><td>${esc(r.status)}</td><td>${esc(r.no)}</td><td>${esc(r.date)}</td><td>${esc(r.net)}</td><td>${esc(r.late)}</td><td>${esc(r.disc)}</td><td>${esc(r.paid)}</td><td>${esc(r.mode)}</td><td>${esc(r.by)}</td><td>${esc(r.remark || '')}</td>
      <td><button class="fr-sm" onclick="${r.old ? `printOld('${esc(r.no)}')` : `printReceipt(LD.receipts.find(x=>x.no==='${esc(r.no)}'))`}">Print</button></td>${ST.isPrincipal ? `<td>${!r.old && r.status !== 'Cancelled' ? `<button class="fr-sm" onclick="cancelReceipt('${esc(r.no)}')">Cancel</button>` : ''}</td>` : ''}</tr>`).join('')}
    <tr><td></td><td></td><td></td><td><b>Total</b></td><td><b>${sum('net')}</b></td><td><b>${sum('late')}</b></td><td><b>${sum('disc')}</b></td><td><b>${sum('paid')}</b></td><td colspan="${ST.isPrincipal ? 5 : 4}"></td></tr></table>`;
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
async function printOld(no) {
  try {
    const r = await fetch('registers_api.php?a=rows&m=oldrcptdet', { headers: { 'X-School-Token': localStorage.getItem('sch_token') || '' } }); const d = await r.json();
    const x = (d.rows || []).find(z => z.session.replace(/^(20\d\d)-20(\d\d)$/, '$1-$2') === ST.ses && z.receiptNo === no);
    if (!x) return toast('The full old receipt ' + no + ' is not in the Old ERP Receipt Details.');
    printHtml(receiptHtml({ title: x.title, receiptNo: x.receiptNo, date: x.date, name: x.name, cls: x.cls, adm: x.adm, mother: x.mother, father: x.father, scheme: x.scheme, lines: x.lines, balance: x.balance, payable: x.payable, lateFee: x.lateFee, adjustment: x.adjustment, mode: x.mode, received: x.received, bank: x.bank, cheque: x.cheque, remark: x.remark, user: x.user }));
  } catch (e) { toast(e.message); }
}
boot();
