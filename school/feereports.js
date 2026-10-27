'use strict';
// Fee Reports in the old ERP's own layout: the same names, filters, column choices and columns
// (copied from schoolerpindia.net, 27 Oct 2026). Data: feereports_api.php (old ERP + new receipts).
const $ = id => document.getElementById(id);
const esc = v => String(v ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
const QS = new URLSearchParams(location.search);
let D = null, R = QS.get('r') || 'fee1', F = {}, OUT = false;
function toast(t) { const el = $('smToast'); el.textContent = t; clearTimeout(toast.t); toast.t = setTimeout(() => { el.textContent = ''; }, 7000); }
const n0 = v => { const x = Math.round(v * 100) / 100; return Number.isInteger(x) ? String(x) : x.toFixed(2); };
const dmy = iso => iso ? iso.slice(8, 10) + '-' + iso.slice(5, 7) + '-' + iso.slice(0, 4) : '';
const ymdSlash = d => d.getFullYear() + '/' + String(d.getMonth() + 1).padStart(2, '0') + '/' + String(d.getDate()).padStart(2, '0');
const iso = s => { const m = /^(\d{4})[\/-](\d{2})[\/-](\d{2})$/.exec(String(s || '').trim()); return m ? m[1] + '-' + m[2] + '-' + m[3] : ''; };
const longSes = s => String(s || '').replace(/^(20\d\d)-(\d\d)$/, '$1-20$2');
const CLASSES = ['PG', 'LKG', 'UKG', '1st', '2nd', '3rd', '4th', '5th', '6th', '7th', '8th', '9th', '10th', '11th', '12th'];
const MONTHS = ['April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December', 'January', 'February', 'March'];
const MODES = ['Cash', 'Cheque', 'Card', 'Wallet', 'NEFT', 'UPI', 'Net Banking', 'Online'];
// The old fee head master, in its own order (columns of Fees Collection Report II); any other head found is added after.
const HEADS0 = ['Digital Class Room Fee', 'School Fee', 'Dress', 'Diary', 'Board Fee X', 'Admission Fee', 'Annual Fee', 'Tution Fee', 'Health And Sefety Fee', 'Exam Fee', 'Old Due', 'Transport', 'Book', 'Hostel', 'Sports Fee'];
let HEADS = HEADS0;
const live = r => r.status !== 'Cancelled';
const uniq = a => [...new Set(a.filter(Boolean))];
// ---------- filters, as on the old forms ----------
const FL = {
  dates: () => [['Start Date', 'sdate', 'date'], ['End Date', 'edate', 'date']],
  session: () => [['Session', 'ses', ['All', ...uniq(D.receipts.map(r => longSes(r.session))).sort()]]],
  cls: () => [['Class', 'cls', ['All', ...CLASSES]]],
  sec: () => [['Section', 'sec', ['All', ...uniq(D.receipts.map(r => r.section)).sort()]]],
  user: () => [['User', 'user', ['All', ...uniq(D.receipts.map(r => r.user))]]],
  scheme: () => [['Schemes', 'scheme', ['All', ...uniq(D.receipts.map(r => r.scheme)).sort()]]],
  mode: () => [['Payment Mode', 'mode', ['All', ...MODES]]],
  bank: () => [['Bank', 'bank', ['Select', ...uniq(D.receipts.map(r => r.bank)).sort()]]],
  head: () => [['Fee Head', 'head', ['All', ...HEADS]]],
  month: () => [['Month', 'month', ['All', ...MONTHS]]]
};
function pass(r, f) {
  const g = k => f[k] ?? (k === 'sdate' || k === 'edate' ? '' : 'All');
  const s = iso(g('sdate')), e = iso(g('edate'));
  if (s && r.date < s) return false; if (e && r.date > e) return false;
  if (g('ses') !== 'All' && longSes(r.session) !== g('ses')) return false;
  if (g('cls') !== 'All' && r.cls !== g('cls')) return false;
  if (g('sec') !== 'All' && r.section !== g('sec')) return false;
  if (g('user') !== 'All' && r.user !== g('user')) return false;
  if (g('scheme') !== 'All' && r.scheme !== g('scheme')) return false;
  if (g('mode') !== 'All' && r.mode !== g('mode')) return false;
  if (f.bank && f.bank !== 'Select' && r.bank !== f.bank) return false;
  return true;
}
function headAmt(r) { const o = {}; r.lines.forEach(l => { o[l.head] = (o[l.head] || 0) + l.amt; }); return o; }
const T = (head, rows, foot) => `<div class="lw"><table class="lst"><tr>${head.map(h => `<th>${esc(h)}</th>`).join('')}</tr>${rows.join('')}${foot ? `<tr class="tot">${foot.map(c => `<td>${esc(c)}</td>`).join('')}</tr>` : ''}</table></div>`;
const tr = cells => `<tr>${cells.map(c => typeof c === 'object' && c ? `<td class="${c.c || ''}">${c.h ?? esc(c.v)}</td>` : `<td>${esc(c)}</td>`).join('')}</tr>`;
const sum = (rows, k) => rows.reduce((a, r) => a + (typeof k === 'function' ? k(r) : r[k]), 0);
// ---------- the reports ----------
const REP = {
  fee1: { title: 'Fee Collection Report 1', page: 'report_fee.php', f: ['dates', 'session', 'cls', 'user', 'scheme', 'mode'],
    cols: [['Session', 0, r => longSes(r.session)], ['Last Followup Remark', 0, r => r.followRemark], ['Receipt No', 0, r => r.no], ['Date', 1, r => dmy(r.date)], ['Scholar No', 1, r => r.adm], ['Class', 1, r => r.cls], ['Section', 1, r => r.section], ['Name', 1, r => r.name], ["Father's Name", 0, r => r.father], ['Month', 0, r => r.months], ['Remark', 0, r => r.remark]],
    cols2: [['Customer Bank', 0, r => r.custBank], ['Cheque No', 0, r => r.cheque], ['Status', 0, r => r.status], ['Bank', 0, r => r.bank], ['PMode', 0, r => r.mode], ['UName', 0, r => r.user]],
    run(rows) {
      const pick = c => F['c:' + c[0]] ?? !!c[1], a = this.cols.filter(pick), b = this.cols2.filter(pick), ok = rows.filter(live);
      return T(['Sno', ...a.map(c => c[0]), 'Total Amount', 'Discount', 'Late Fee', 'Currently Received', ...b.map(c => c[0])],
        rows.map((r, i) => tr([i + 1, ...a.map(c => c[2](r)), n0(r.total), n0(r.discount), n0(r.late), n0(r.received), ...b.map(c => c[2](r))])),
        ['', ...a.map((c, i) => i === a.length - 1 ? 'Total' : ''), n0(sum(ok, 'total')), n0(sum(ok, 'discount')), n0(sum(ok, 'late')), n0(sum(ok, 'received')), ...b.map(() => '')]);
    } },
  fee2: { title: 'Fees Collection Report II', page: 'report_feen.php', f: ['dates', 'session', 'cls', 'sec', 'user', 'scheme', 'mode'], run: rows => headTable(rows, false) },
  conc: { title: 'Fees Concession Report', page: 'report_fee_oncession.php', f: ['dates', 'session', 'user', 'scheme', 'mode'], run: rows => headTable(rows.filter(r => r.discount > 0), true, true) },
  bank1: { title: 'Bank Report I', page: 'report_fee_bank.php', f: ['dates', 'session', 'user', 'scheme', 'mode', 'bank'],
    run(rows) {
      const ok = rows.filter(live);
      return T(['Sno', 'Bank Name', 'Cheque No', 'Currently Received', 'Scholar No', 'Name', 'Class', 'Section', 'Receipt No', 'Date', ...HEADS, 'Total Amount', 'Discount Amount', 'Late Fee', 'PMode', 'Cheque Date'],
        rows.map((r, i) => { const h = headAmt(r); return tr([i + 1, r.bank, r.cheque, n0(r.received), r.adm, { v: r.name, c: 't' }, r.cls, r.section, r.no, dmy(r.date), ...HEADS.map(x => h[x] ? n0(h[x]) : ''), n0(r.total), n0(r.discount), n0(r.late), r.mode, r.chequeDate]); }),
        ['', '', 'Total', n0(sum(ok, 'received')), '', '', '', '', '', '', ...HEADS.map(x => n0(sum(ok, r => headAmt(r)[x] || 0))), n0(sum(ok, 'total')), n0(sum(ok, 'discount')), n0(sum(ok, 'late')), '', '']);
    } },
  bank2: { title: 'Bank Report II', page: 'report_fee_bank_sec.php', f: ['dates', 'session', 'user', 'scheme', 'mode', 'bank'],
    run: rows => T(['Sno', 'Bank Name', 'Cheque No', 'Cheque Date', 'Currently Received'], rows.map((r, i) => tr([i + 1, r.bank, r.cheque, r.chequeDate, n0(r.received)])), ['', '', '', 'Total', n0(sum(rows.filter(live), 'received'))]) },
  fee4: { title: 'Fees Collection Report - IV', page: 'report_feecollection_four.php', f: ['dates', 'cls'],
    run(rows) {
      const by = {}; rows.filter(live).forEach(r => (by[r.date] = by[r.date] || []).push(r));
      const days = Object.keys(by).sort(), ok = rows.filter(live);
      return T(['Sno', 'Date', 'Receipt No', 'Total Fee', 'Late Fee', 'Discount', 'Recv Amount'],
        days.map((d, i) => { const x = by[d]; return tr([i + 1, dmy(d), x[0].no + (x.length > 1 ? ' to ' + x[x.length - 1].no : ''), n0(sum(x, 'total')) + '(' + x.length + ')', n0(sum(x, 'late')), n0(sum(x, 'discount')), n0(sum(x, 'received'))]); }),
        ['', '', 'Total', n0(sum(ok, 'total')) + '(' + ok.length + ')', n0(sum(ok, 'late')), n0(sum(ok, 'discount')), n0(sum(ok, 'received'))]);
    } },
  fee5: { title: 'Fees Collection Report V', page: 'report_feecollection_head.php', f: ['dates', 'session', 'user', 'scheme', 'cls', 'mode'],
    run(rows) {
      const by = {}; rows.filter(live).forEach(r => (by[r.date] = by[r.date] || []).push(r));
      const line = (x, label) => { const h = {}; x.forEach(r => Object.entries(headAmt(r)).forEach(([k, v]) => { h[k] = (h[k] || 0) + v; })); return [label, ...HEADS.map(k => h[k] ? n0(h[k]) : ''), n0(sum(x, 'total')), n0(sum(x, 'discount')), '0', n0(sum(x, 'received')), ...MODES.map(m => { const v = sum(x.filter(r => r.mode === m), 'received'); return v ? n0(v) : ''; })]; };
      const days = Object.keys(by).sort();
      return T(['Sno', 'Date', ...HEADS, 'Total Amount', 'Discount Amount', 'Other Fee', 'Currently Received', ...MODES], days.map((d, i) => tr([(i + 1) + '.', ...line(by[d], dmy(d))])), ['', ...line(rows.filter(live), 'Total')]);
    } },
  headwise: { title: 'Head Wise Collection Report', page: 'report_sheadfee.php', f: ['session', 'dates', 'cls', 'sec', 'head', 'month'],
    run(rows) {
      const out = []; rows.filter(live).forEach(r => r.lines.forEach(l => {
        if (F.head && F.head !== 'All' && l.head !== F.head) return;
        if (F.month && F.month !== 'All' && !l.months.split(',').map(s => s.trim()).includes(F.month)) return;
        out.push([r, l]);
      }));
      return T(['Sno', 'Date', 'Recpt. No.', 'Admission As', 'Student Type', 'Scholar No', 'Name', 'Class', 'Section', 'Stream', 'Head', 'Payment Mode', 'Month', 'Amount', 'Discount', 'Total Amount'],
        out.map(([r, l], i) => tr([i + 1, dmy(r.date), r.no, r.admAs, r.stuType, r.adm, { v: r.name, c: 't' }, r.cls, r.section, r.stream, l.head, r.mode, l.months, n0(l.amt), '0', n0(l.amt)])),
        ['', '', '', '', '', '', '', '', '', '', '', '', 'Total', n0(out.reduce((a, x) => a + x[1].amt, 0)), '0', n0(out.reduce((a, x) => a + x[1].amt, 0))]);
    } },
  monthly: { title: 'Monthly Collection Report', page: 'fees_monthlycollection.php', f: ['session'],
    run(rows) {
      const ok = rows.filter(live), mon = r => MONTHS[(+r.date.slice(5, 7) + 8) % 12];
      return T(['Month', 'Total Fee', 'Discount', 'Recv Amount'], MONTHS.map(m => { const x = ok.filter(r => mon(r) === m); return tr([m, n0(sum(x, 'total')), n0(sum(x, 'discount')), n0(sum(x, 'received'))]); }),
        ['Total', n0(sum(ok, 'total')), n0(sum(ok, 'discount')), n0(sum(ok, 'received'))]);
    } },
  classwise: { title: 'Class Wise Collection Report', page: 'fees_classwiseollection.php', f: ['session', 'dates'],
    run(rows) {
      const ok = rows.filter(live), keys = uniq(ok.map(r => r.cls + ' ' + r.section)).sort((a, b) => CLASSES.indexOf(a.split(' ')[0]) - CLASSES.indexOf(b.split(' ')[0]) || a.localeCompare(b));
      return T(['Class', 'Total Fee', 'Discount', 'Recv Amount'], keys.map(k => { const x = ok.filter(r => r.cls + ' ' + r.section === k); return tr([k, n0(sum(x, 'total')), n0(sum(x, 'discount')), n0(sum(x, 'received'))]); }),
        ['Total', n0(sum(ok, 'total')), n0(sum(ok, 'discount')), n0(sum(ok, 'received'))]);
    } }
};
function headTable(rows, conc, uname) {
  const ok = rows.filter(live);
  const head = ['Sno', ...(conc ? [] : ['Session']), 'Receipt No', 'Date', 'SCH.NO.', 'Name', "Father's Name", 'Class', 'Section', ...HEADS, 'Total Amount', 'Discount Amount', 'Other Fee', 'Currently Received', 'PMode', 'Remark', ...(uname ? ['UName'] : [])];
  return T(head, rows.map((r, i) => { const h = headAmt(r); return tr([i + 1, ...(conc ? [] : [longSes(r.session)]), r.no, dmy(r.date), r.adm, { v: r.name, c: 't' }, { v: r.father, c: 't' }, r.cls, r.section, ...HEADS.map(x => h[x] ? n0(h[x]) : ''), n0(r.total), n0(r.discount), '0', n0(r.received), r.mode, r.remark, ...(uname ? [r.user] : [])]); }),
    ['', ...(conc ? [] : ['']), '', '', '', '', '', '', 'Total', ...HEADS.map(x => n0(sum(ok, r => headAmt(r)[x] || 0))), n0(sum(ok, 'total')), n0(sum(ok, 'discount')), '0', n0(sum(ok, 'received')), '', '', ...(uname ? [''] : [])]);
}
// ---------- page ----------
function formHtml(rep) {
  const ctl = ([label, k, t]) => {
    const v = F[k] ?? (t === 'date' ? ymdSlash(new Date()) : t[0]);
    if (t === 'date') return `<tr><td class="l">${esc(label)}</td><td><input type="text" value="${esc(v)}" placeholder="YYYY/MM/DD" onchange="F['${k}']=this.value"></td></tr>`;
    return `<tr><td class="l">${esc(label)}</td><td><select onchange="F['${k}']=this.value">${t.map(o => `<option${o === v ? ' selected' : ''}>${esc(o)}</option>`).join('')}</select></td></tr>`;
  };
  const chooser = rep.cols ? `<div class="cols">${[...rep.cols, ...rep.cols2].map(c => `<label><input type="checkbox" ${(F['c:' + c[0]] ?? !!c[1]) ? 'checked' : ''} onchange="F['c:${c[0]}']=this.checked">${esc(c[0])}</label>`).join('')}</div>
    <div><button class="btn alt" onclick="setAll(true)">Check All</button> <button class="btn alt" onclick="setAll(false)">Uncheck All</button></div>` : '';
  return `<table class="flt" style="margin:0 auto"><tr><td class="l">School</td><td><input type="text" value="${esc(D.school)}" readonly></td></tr>${rep.f.flatMap(k => FL[k]()).map(ctl).join('')}</table>${chooser}
    <div style="text-align:center"><button class="btn" onclick="go()">Submit</button></div>`;
}
function setAll(v) { const rep = REP[R]; [...rep.cols, ...rep.cols2].forEach(c => { F['c:' + c[0]] = v; }); render(); }
function go() {
  // A date typed into a form is read even if the box was not left (old forms submit what is shown).
  document.querySelectorAll('.flt input[placeholder="YYYY/MM/DD"]').forEach((el, i) => { F[i ? 'edate' : 'sdate'] = el.value; });
  OUT = true; render();
}
function excel() {
  const t = document.querySelector('table.lst'); if (!t) return;
  const txt = [...t.rows].map(r => [...r.cells].map(c => '"' + c.innerText.replace(/"/g, '""') + '"').join(',')).join('\r\n');
  const a = document.createElement('a'); a.href = URL.createObjectURL(new Blob(['﻿' + txt], { type: 'text/csv' })); a.download = REP[R].title + '.csv'; a.click();
}
function render() {
  if (!D) return;
  const rep = REP[R] || REP.fee1;
  ['sdate', 'edate'].forEach(k => { if (rep.f.includes('dates') && F[k] === undefined) F[k] = ymdSlash(new Date()); });
  // Monthly and Class Wise had no form in the old ERP: they open on the running session straight away.
  if ((R === 'monthly' || R === 'classwise') && F.ses === undefined) { F.ses = longSes(D.session); if (R === 'classwise') { F.sdate = ''; F.edate = ''; } OUT = true; }
  const rows = OUT ? D.receipts.filter(r => pass(r, rep.f.includes('dates') ? F : { ...F, sdate: '', edate: '' })) : null;
  $('frRoot').innerHTML = `<div class="sm"><h3>${esc(rep.title)}</h3><div class="np">${formHtml(rep)}</div>
    ${rows ? `<div class="sch">${esc(D.school)}</div><div class="sub">${esc(rep.title)}${rep.f.includes('dates') ? ' — ' + esc(F.sdate) + ' to ' + esc(F.edate) : ''}</div>
    <div class="np" style="text-align:right"><button class="btn alt" onclick="excel()">Excel</button> <button class="btn alt" onclick="window.print()">Print</button></div>${rep.run(rows)}` : ''}</div>`;
}
async function boot() {
  try {
    const r = await fetch('feereports_api.php?a=receipts', { headers: { 'X-School-Token': localStorage.getItem('sch_token') || '' } });
    const d = await r.json(); if (!r.ok || !d.ok) throw Error(d.error || 'Could not open the report.');
    D = d; const seen = uniq(D.receipts.flatMap(x => x.lines.map(l => l.head))); HEADS = [...HEADS0, ...seen.filter(h => !HEADS0.includes(h))];
    render();
  } catch (e) { $('frRoot').innerHTML = `<div class="sm"><h3>Fee Reports</h3><p>${esc(e.message)}</p></div>`; }
}
boot();
