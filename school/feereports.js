'use strict';
// Old ERP Fee Reports — first the old filter page (report_fee.php …), then the old result page (report_fee1.php …), same columns.
const $ = id => document.getElementById(id);
const esc = v => String(v ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
const QS = new URLSearchParams(location.search);
let M = null, K = QS.get('k') || 'fee1', RES = null, F = null, BUSY = false;
// report_fee.php: the columns the user may tick (the amounts are always shown); the old page ticks these six first.
const FEE1_PICK = ['Session', 'Last Followup Remark', 'Receipt No', 'Date', 'Scholar No', 'Class', 'Section', 'Name', "Father's Name", 'Month', 'Remark', 'Customer Bank', 'Cheque No', 'Status', 'Bank', 'PMode', 'UName', 'Print', 'Delete'];
const FEE1_ON = ['Date', 'Scholar No', 'Class', 'Section', 'Name', 'Print'];
const FEE1_ALWAYS = ['Sno', 'Total Amount', 'Discount', 'Late Fee', 'Currently Received'];
function toast(t) { const el = $('rpToast'); el.textContent = t; clearTimeout(toast.t); toast.t = setTimeout(() => { el.textContent = ''; }, 7000); }
async function api(a, q) {
  const r = await fetch('feereports_api.php?a=' + a + (q ? '&' + new URLSearchParams(q) : ''), { headers: { 'X-School-Token': localStorage.getItem('sch_token') || '' } });
  let d; try { d = await r.json(); } catch (e) { throw Error('The server did not answer properly. Please try again.'); }
  if (!r.ok || !d.ok) throw Error(d.error || 'Could not complete this.');
  return d;
}
const dmy = iso => iso ? iso.split('-').reverse().join('-') : '';
const opt = (v, t, on) => `<option value="${esc(v)}" ${on ? 'selected' : ''}>${esc(t)}</option>`;
const longSes = s => s.replace(/^(20\d\d)-(\d\d)$/, '$1-20$2');
async function boot() {
  try {
    M = await api('meta');
    if (!M.reports[K]) K = 'fee1';
    const ses = (QS.get('session') || '').replace(/^(20\d\d)-20(\d\d)$/, '$1-$2');
    F = { ses: M.sessions.includes(ses) ? ses : '', from: M.today, to: M.today, cls: '', sec: '', user: '', mode: '', cols: [...FEE1_ON] };
    form();
  } catch (e) { $('rpRoot').innerHTML = `<div class="rp"><h3>Fee Report</h3><p>${esc(e.message)}</p></div>`; }
}
function form() {
  RES = null;
  const t = M.reports[K][0];
  $('rpRoot').innerHTML = `<div class="rp"><h3>${esc(t.replace(/ I$/, ' 1'))}</h3>
    <div class="rp-form">
      <label><span>School</span><select><option>${esc(M.school)}</option></select></label>
      <label><span>Start Date</span><input type="date" value="${esc(F.from)}" onchange="F.from=this.value"></label>
      <label><span>End Date</span><input type="date" value="${esc(F.to)}" onchange="F.to=this.value"></label>
      <label><span>Session</span><select onchange="F.ses=this.value">${opt('', 'All', !F.ses)}${M.sessions.map(s => opt(s, longSes(s), s === F.ses)).join('')}</select></label>
      <label><span>Class</span><select onchange="F.cls=this.value">${opt('', 'All', !F.cls)}${M.classes.map(c => opt(c, c, c === F.cls)).join('')}</select></label>
      <label><span>User</span><select onchange="F.user=this.value">${opt('', 'All', !F.user)}${M.users.map(x => opt(x, x, x === F.user)).join('')}</select></label>
      <label><span>Payment Mode</span><select onchange="F.mode=this.value">${opt('', 'All', !F.mode)}${M.modes.map(x => opt(x, x, x === F.mode)).join('')}</select></label>
    </div>
    ${K === 'fee1' ? `<div class="rp-cols">${FEE1_PICK.map(c => `<label><input type="checkbox" ${F.cols.includes(c) ? 'checked' : ''} onchange="pick('${esc(c)}',this.checked)">${esc(c === 'Delete' ? 'Cancel Receipt' : c)}</label>`).join('')}</div>` : '<div style="height:16px"></div>'}
    <div class="rp-bar">${K === 'fee1' ? `<button class="b-y" onclick="F.cols=[...FEE1_PICK];form()">Check All</button><button class="b-y" onclick="F.cols=[];form()">Uncheck All</button>` : ''}<span class="sp"></span><button class="b-d" onclick="run()" ${BUSY ? 'disabled' : ''}>${BUSY ? 'Please wait…' : 'Submit'}</button><span class="sp"></span></div></div>`;
}
function pick(c, on) { F.cols = on ? [...new Set([...F.cols, c])] : F.cols.filter(x => x !== c); }
async function run() {
  if (BUSY) return; if (F.from && F.to && F.from > F.to) return toast('Start Date is after End Date.');
  BUSY = true; form();
  try { RES = await api('report', { k: K, ses: F.ses, from: F.from, to: F.to, cls: F.cls, sec: F.sec, user: F.user, mode: F.mode }); BUSY = false; result(); }
  catch (e) { BUSY = false; form(); toast(e.message); }
}
function cell(c, money) {
  if (c && typeof c === 'object') return c.print ? `<a href="feecollect.php?print=${encodeURIComponent(c.print)}" target="_blank" rel="noopener">Print</a>` : '';
  return esc(c);
}
const MONEY = /Amount|Discount|Late Fee|Received|Recv|Total|Fee$|^Amt|Due|Cash|Cheque|Card|Wallet|NEFT|UPI|Banking|Online|Remaining|Income|Expense/;
function result() {
  const H = RES.head[0]; let keep = H.map((_, i) => i);
  if (K === 'fee1') keep = keep.filter(i => FEE1_ALWAYS.includes(H[i]) || F.cols.includes(H[i]));
  const money = new Set(keep.filter(i => MONEY.test(H[i]) && !/^(Fee Name|Late Followup)$/.test(H[i]) && !['Name', "Father's Name", 'Class'].includes(H[i])));
  const range = F.from || F.to ? ` (${dmy(F.from)} - ${dmy(F.to)})` : '';
  const title = { fee1: 'Fee Collection Report', fee2: 'Fee Collection Report' }[K] || RES.title;
  const S = RES.summary;
  const summary = S ? `<tr><td><div class="rk-sum"><div><table class="rk" style="width:400px;margin:0 auto"><tr class="rk-y"><td colspan="2">Summary</td></tr><tr class="rk-y"><td>Payment Mode</td><td>Amount</td></tr>${S.modes.map(m => `<tr><td style="text-align:center">${esc(m[0])}</td><td style="text-align:center">${esc(m[1])}</td></tr>`).join('')}<tr class="rk-y"><td>Total</td><td>${esc(S.modeTotal)}</td></tr></table>
      <table class="rk" style="margin-top:80px"><tr><td colspan="2"><b>USER WISE FEE</b></td></tr>${S.users.map(x => `<tr><td>${esc(x[0])}</td><td>${esc(x[1])}</td></tr>`).join('')}</table></div>
      <div><table class="rk"><tr><td><b>HEAD</b></td><td><b>Total</b></td></tr>${S.heads.map(h => `<tr><td>${esc(h[0])}</td><td>${esc(h[1])}</td></tr>`).join('')}<tr><td><b>Total</b></td><td><b>${esc(S.headTotal)}</b></td></tr><tr><td>Discount(-)</td><td>${esc(S.discount)}</td></tr><tr><td>Late Fee(+)</td><td>${esc(S.late)}</td></tr><tr><td><b>Total</b></td><td><b>${esc(S.net)}</b></td></tr></table></div></div></td></tr>` : '';
  $('rpRoot').innerHTML = `<div class="noprint" style="padding:8px"><button class="b-d" onclick="form()">‹ Back</button> <button class="b-d" onclick="window.print()">Print</button> <span>${RES.count} rows</span></div>
  <div class="rk-wrap"><table class="rk" style="min-width:990px"><tr><td><table class="rk rk-name"><tr><td style="width:11%"><img src="img/snmsis-logo.png" height="70" alt=""></td><td><table class="rk"><tr><td class="n">${esc(M.school)}</td></tr><tr><td class="a">${esc(M.address)}</td></tr></table></td></tr></table></td></tr>
    <tr><td class="rk-t"><h3>${esc(title)}${esc(range)}</h3></td></tr>
    <tr><td><table class="rk" id="rpT"><tr class="rk-h">${keep.map(i => `<td>${esc(H[i])}</td>`).join('')}</tr>
      ${RES.rows.map(r => `<tr>${keep.map(i => i === 0 && K === 'fee1' ? `<td style="text-align:center"><span class="sno">${esc(r[0])}</span></td>` : `<td class="${money.has(i) ? 'r' : ''}">${cell(r[i])}</td>`).join('')}</tr>`).join('')}
      ${RES.total ? `<tr>${keep.map(i => `<td class="${money.has(i) ? 'r' : ''}"><b>${K === 'fee1' && RES.total[i] === 'Total' ? '' : esc(RES.total[i])}</b></td>`).join('')}</tr>` : ''}</table></td></tr>
    ${summary}</table></div>
  <div class="noprint" style="padding:8px"><button class="b-g" onclick="excel()">Export to Excel</button></div>`;
}
function excel() {
  const H = RES.head[0]; let keep = H.map((_, i) => i); if (K === 'fee1') keep = keep.filter(i => FEE1_ALWAYS.includes(H[i]) || F.cols.includes(H[i]));
  const txt = [H, ...RES.rows, RES.total || []].map(r => keep.map(i => '"' + String(r[i] && typeof r[i] === 'object' ? '' : r[i] ?? '').replace(/"/g, '""') + '"').join(',')).join('\r\n');
  const a = document.createElement('a'); a.href = URL.createObjectURL(new Blob(['﻿' + txt], { type: 'text/csv' })); a.download = RES.title + ' ' + dmy(F.from) + ' to ' + dmy(F.to) + '.csv'; a.click();
}
boot();
