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
// ---------- reports worked out from each child's dues (Fee Receipt ledger, or the copied old ERP fee card) ----------
let DUES = {}, DUELOAD = '';
const MNUM = { April: 4, May: 5, June: 6, July: 7, August: 8, September: 9, October: 10, November: 11, December: 12, January: 1, February: 2, March: 3 };
const ord = m => (m + 8) % 12, isTr = h => /^(Transport|Hostel)$/i.test(h.name);
function due(r, M) {
  const upto = ord(MNUM[M] || 3), hs = r.heads.filter(h => ord(h.m) <= upto), add = (a, f) => a.reduce((x, h) => x + f(h), 0);
  const cur = add(hs, h => h.amt), curPaid = add(hs, h => h.paid), tr = hs.filter(isTr), fee = hs.filter(h => !isTr(h));
  const oldLeft = Math.max(0, r.oldDue - r.oldPaid), trDue = add(tr, h => h.amt - h.paid), feeDue = add(fee, h => h.amt - h.paid) + oldLeft;
  return { cur, total: cur + r.transfer, trAmt: add(tr, h => h.amt), feeAmt: add(fee, h => h.amt), curDue: cur - curPaid, trDue, feeDue, due: trDue + feeDue, netPay: cur + r.transfer + r.late + r.oldDue };
}
const FD = {
  dses: () => [['Session', 'dses', uniq([longSes(D.session), ...D.receipts.map(r => longSes(r.session))]).sort().reverse()]],
  dcls: () => [['Class', 'cls', ['All', ...CLASSES]]], dsec: () => [['Section', 'sec', ['All', 'A', 'B', 'DIAMOND']]],
  dscheme: () => [['Schemes', 'scheme', ['All', ...uniq(curDues().map(r => r.scheme)).sort()]]], dfac: () => [['Facilities', 'faclty', ['All', 'No', 'Transport', 'Hostel']]],
  droute: () => [['Route Hostel', 'route', ['All', ...uniq(curDues().map(r => r.route)).sort()]]], dadm: () => [['Admission As', 'admAs', ['All', 'Boarder', 'Day Boarder', 'Day Scholar']]],
  dtype: () => [['Student Type', 'stuType', ['All', 'New Student', 'Old Student']]], dmonth: () => [['Upto Month', 'upto', MONTHS]],
  dstatus: () => [['Student Status', 'status', ['All', 'Active', 'Deactive', 'IActive', 'TC']]]
};
const curDues = () => (DUES[F.dses || longSes(D.session)] || {}).rows || [];
function dpass(r) { const g = k => F[k] || 'All'; return ['cls', 'scheme', 'faclty', 'route', 'admAs', 'stuType', 'status'].every(k => g(k) === 'All' || String(r[k]) === g(k)) && (g('sec') === 'All' || r.section === g('sec')); }
const byClass = (a, b) => CLASSES.indexOf(a.cls) - CLASSES.indexOf(b.cls) || a.section.localeCompare(b.section) || a.name.localeCompare(b.name);
const DF = ['dses', 'dcls', 'dsec', 'dscheme', 'dfac', 'droute', 'dadm', 'dtype', 'dmonth', 'dstatus'];
Object.assign(REP, {
  defaulter: { title: 'Fee Defaulter', page: 'studuedfaulter.php', dues: 1, f: DF,
    run(rows) {
      const M = F.upto || 'March', x = rows.map(r => [r, due(r, M)]).filter(([, d]) => d.due > 0);
      return T(['Sno', 'Status', 'Last Followup Remark', 'Last Payment Date', 'Last Paid Amount', 'Last Paid Month', 'Admission As', 'Student Type', 'Tag', 'Schemes', 'Class', 'Section', 'Stream', 'Scholar No', 'Name', "Father's Mobile No", "Mother's Mobile No", 'SMS/Whatsapp No.', "Father's Name", 'Address', 'Facilities', 'Route Hostel', 'Prev Transfer Fee', 'Cur Session Fee', 'Total Amount', 'Late Fees', 'Old Due', 'Fee Discount', 'Discount Amount', 'Net Payable', 'Currently Received', 'Cur Session Due Fee', 'Due Amount', 'Oth Fee Rec', 'Fee Refund', 'Transfer Fee', 'Hold Amount'],
        x.map(([r, d], i) => tr([i + 1, r.status, r.followRemark, dmy(r.lastDate), r.lastDate ? n0(r.lastAmt) : '', r.lastMonths, r.admAs, r.stuType, r.tag, r.scheme, r.cls, r.section, r.stream, r.adm, { v: r.name, c: 't' }, r.fatherMobile, r.motherMobile, r.phone, { v: r.father, c: 't' }, { v: r.address, c: 't' }, r.faclty, { v: r.route, c: 't' },
          n0(r.transfer), n0(d.cur), n0(d.total), n0(r.late), n0(r.oldDue), '0', n0(r.discount), n0(d.netPay), n0(r.received), n0(d.curDue), n0(d.due), '0', '0', '0', ''])),
        ['', '', '', '', '', '', '', '', '', '', '', '', '', '', 'Total (' + x.length + ')', '', '', '', '', '', '', '', n0(sum(x, ([r]) => r.transfer)), n0(sum(x, ([, d]) => d.cur)), n0(sum(x, ([, d]) => d.total)), n0(sum(x, ([r]) => r.late)), n0(sum(x, ([r]) => r.oldDue)), '0', n0(sum(x, ([r]) => r.discount)), n0(sum(x, ([, d]) => d.netPay)), n0(sum(x, ([r]) => r.received)), n0(sum(x, ([, d]) => d.curDue)), n0(sum(x, ([, d]) => d.due)), '0', '0', '0', '']);
    } },
  statement: { title: 'Fee Statement', page: 'studuefeedet.php', dues: 1, f: DF,
    run(rows) {
      const M = F.upto || 'March', x = rows.map(r => [r, due(r, M)]);
      return `<div class="sub">Session: ${esc(F.dses || longSes(D.session))} Month ${esc(M)}</div>` + T(['Sno', 'Admission As', 'Status', 'Student Type', 'Class', 'Section', 'Stream', 'Scholar No', 'Name', "Father's Name", 'Father Mobile No', 'Address', 'Scheme', 'Route', 'Old Due', 'Prev Transfer Fee', 'Cur Session Fee', 'Total Amount', 'Transfer Fee', 'Transport/H Amount', 'Fees. Amount', 'Inst. Count', 'First Inst. Date', 'Last Inst. Date', 'Oth Disc Amount', 'Discount Amount', 'Fee Adjust', 'Currently Received', 'Transport Due Amount', 'Fee Due Amount', 'Net Due Amount', 'Oth Fee Rec', 'Fee Refund'],
        x.map(([r, d], i) => tr([i + 1, r.admAs, r.status, r.stuType, r.cls, r.section, r.stream, r.adm, { v: r.name, c: 't' }, { v: r.father, c: 't' }, r.fatherMobile, { v: r.address, c: 't' }, r.scheme, { v: r.route, c: 't' }, n0(r.oldDue), n0(r.transfer), n0(d.cur), n0(d.total), '0', n0(d.trAmt), n0(d.feeAmt), r.count, dmy(r.first), dmy(r.lastDate), '0', n0(r.discount), '0', n0(r.received), n0(d.trDue), n0(d.feeDue), n0(d.due), '0', '0'])),
        ['', '', '', '', '', '', '', '', 'Total (' + x.length + ')', '', '', '', '', '', n0(sum(x, ([r]) => r.oldDue)), n0(sum(x, ([r]) => r.transfer)), n0(sum(x, ([, d]) => d.cur)), n0(sum(x, ([, d]) => d.total)), '0', n0(sum(x, ([, d]) => d.trAmt)), n0(sum(x, ([, d]) => d.feeAmt)), n0(sum(x, ([r]) => r.count)), '', '', '0', n0(sum(x, ([r]) => r.discount)), '0', n0(sum(x, ([r]) => r.received)), n0(sum(x, ([, d]) => d.trDue)), n0(sum(x, ([, d]) => d.feeDue)), n0(sum(x, ([, d]) => d.due)), '0', '0']);
    } },
  zerodue: { title: 'Zero Due Report', page: 'student_zerodue.php', dues: 1, f: DF,
    run(rows) {
      const M = F.upto || 'March', x = rows.map(r => [r, due(r, M)]).filter(([, d]) => d.due <= 0);
      return T(['Sno', 'Status', 'Last Followup Remark', 'Admission As', 'Student Type', 'Class', 'Section', 'Stream', 'Scholar No', 'Name', "Father's Mobile No", "Mother's Mobile No", 'SMS/Whatsapp No.', "Father's Name", 'Address', 'Total Amount', 'Late Fees', 'Old Due', 'Fee Discount', 'Discount Amount', 'Currently Received', 'Due Amount', 'Oth Fee Rec', 'Fee Refund', 'Hold Amount'],
        x.map(([r, d], i) => tr([i + 1, r.status, r.followRemark, r.admAs, r.stuType, r.cls, r.section, r.stream, r.adm, { v: r.name, c: 't' }, r.fatherMobile, r.motherMobile, r.phone, { v: r.father, c: 't' }, { v: r.address, c: 't' }, n0(d.total), n0(r.late), n0(r.oldDue), '0', n0(r.discount), n0(r.received), n0(d.due), '0', '0', ''])));
    } },
  register: { title: 'Fee Register', page: 'report_dcollection.php', dues: 1, f: ['dses', 'dcls', 'dsec', 'dstatus'],
    run(rows) {
      const ms = [4, 5, 6, 7, 8, 9, 10, 11, 12, 1, 2, 3];
      const head = `<tr>${['Sno', 'Status', 'Scholar No', 'Sec', 'Group', 'Name', 'Father', 'Father Mob No', 'Scheme', 'Transport', 'Old Due'].map(h => `<th rowspan="2">${h}</th>`).join('')}${MONTHS.map(m => `<th colspan="2">${m}</th>`).join('')}${['Total Fee', 'Amt Paid', 'Transport Due Amount', 'Tranfer Amount', 'Balance Amount', 'Remark'].map(h => `<th rowspan="2">${h}</th>`).join('')}</tr><tr>${MONTHS.map(() => '<th>R No.</th><th>Amt</th>').join('')}</tr>`;
      const body = rows.map((r, i) => { const d = due(r, 'March'); return `<tr>${[i + 1, r.status, r.adm, r.section, r.stream].map(c => `<td>${esc(c)}</td>`).join('')}<td class="t">${esc(r.name)}</td><td class="t">${esc(r.father)}</td><td>${esc(r.fatherMobile || r.phone)}</td><td>${esc(r.scheme)}</td><td class="t">${esc(r.route)}</td><td>${n0(r.oldDue)}</td>${ms.map(m => { const x = r.receipts.filter(p => +p.date.slice(5, 7) === m); return `<td>${esc(x.map(p => p.no).join(', '))}</td><td>${x.length ? n0(sum(x, 'received')) : ''}</td>`; }).join('')}<td>${n0(d.total)}</td><td>${n0(r.received)}</td><td>${n0(d.trDue)}</td><td>${n0(r.transfer)}</td><td>${n0(d.due)}</td><td></td></tr>`; }).join('');
      const tot = `<tr class="tot"><td colspan="10">Total</td><td>${n0(sum(rows, 'oldDue'))}</td>${ms.map(m => `<td></td><td>${n0(sum(rows, r => sum(r.receipts.filter(p => +p.date.slice(5, 7) === m), 'received')))}</td>`).join('')}<td>${n0(sum(rows, r => due(r, 'March').total))}</td><td>${n0(sum(rows, 'received'))}</td><td>${n0(sum(rows, r => due(r, 'March').trDue))}</td><td>${n0(sum(rows, 'transfer'))}</td><td>${n0(sum(rows, r => due(r, 'March').due))}</td><td></td></tr>`;
      return `<div class="lw"><table class="lst">${head}${body}${tot}</table></div>`;
    } }
});
// ---------- more dues reports (Fee Report menu) ----------
// Heads on the old fee card keep paid and discount together ("Paid/Disc Amount"), so these reports show that one column.
const upto = (r, M) => r.heads.filter(h => ord(h.m) <= ord(MNUM[M] || 3));
const headNames = rows => uniq(rows.flatMap(r => r.heads.map(h => h.name))).sort();
FD.dhead = () => [['Fee Head', 'dhead', headNames(curDues())]];
Object.assign(REP, {
  defaulter2: { title: 'Fee Defaulter - II', page: 'studuedfaulter_new.php', dues: 1, f: ['dses', 'dcls', 'dsec', 'dadm', 'dtype', 'dmonth'],
    run(rows) {
      const M = F.upto || 'March', x = rows.map(r => [r, due(r, M)]).filter(([, d]) => d.due > 0);
      return T(['Sno', 'Sch No', 'Name', 'Class', 'Sec', 'Mobile Number', 'Student Type', 'AC Type', 'Total Fee - Disc - Rec + Trns = Due', 'Late Fee', 'Payable Due', 'Total Payable', 'Cur FEE', 'PRV DUE', 'Trns Fee', 'Apply Fees', 'Disc', 'Disc(%)', 'Recv Fees', 'Due Fees', 'Due %', 'Last Pay'],
        x.map(([r, d], i) => { const apply = d.cur + r.oldDue + r.transfer; return tr([i + 1, r.adm, { v: r.name + ' / ' + r.father, c: 't' }, r.cls, r.section, r.phone, r.stuType, 'Single', `${n0(apply)} - ${n0(r.discount)} - ${n0(r.received)} + ${n0(r.transfer)} = ${n0(d.due)}`, n0(r.late), n0(d.due), n0(d.due + r.late), n0(d.cur), n0(r.oldDue), n0(r.transfer), n0(apply), n0(r.discount), apply ? (r.discount * 100 / apply).toFixed(2) + '%' : '0%', n0(r.received), n0(d.due), apply ? (d.due * 100 / apply).toFixed(2) + '%' : '0%', r.lastDate ? dmy(r.lastDate) : '']); }),
        ['', '', 'Total (' + x.length + ')', '', '', '', '', '', '', n0(sum(x, ([r]) => r.late)), n0(sum(x, ([, d]) => d.due)), n0(sum(x, ([r, d]) => d.due + r.late)), n0(sum(x, ([, d]) => d.cur)), n0(sum(x, ([r]) => r.oldDue)), n0(sum(x, ([r]) => r.transfer)), '', n0(sum(x, ([r]) => r.discount)), '', n0(sum(x, ([r]) => r.received)), n0(sum(x, ([, d]) => d.due)), '', '']);
    } },
  statement2: { title: 'Fee Statement-II', page: 'studuefeedet_quarter.php', dues: 1, f: ['dses', 'dcls', 'dsec', 'dadm', 'dtype', 'dmonth', 'dstatus', 'dscheme'],
    run(rows) {
      const M = F.upto || 'March', x = rows.map(r => [r, due(r, M)]);
      return `<div class="sub">Session: ${esc(F.dses)} Installment : ${esc(M)}</div>` + T(['Sno', 'Status', 'Student Type', 'Class', 'Section', 'Stream', 'Scholar No', 'Name', "Father's Name", 'Scheme', 'Total Amount', 'Currently Received', 'Net Due Amount'],
        x.map(([r, d], i) => tr([i + 1, r.status, r.stuType, r.cls, r.section, r.stream, r.adm, { v: r.name, c: 't' }, { v: r.father, c: 't' }, r.scheme, n0(d.total + r.oldDue), n0(r.received), n0(d.due)])),
        ['', '', '', '', '', '', '', 'Total (' + x.length + ')', '', '', n0(sum(x, ([r, d]) => d.total + r.oldDue)), n0(sum(x, ([r]) => r.received)), n0(sum(x, ([, d]) => d.due))]);
    } },
  statement3: { title: 'Fee Statement-III', page: 'studuefeedet_quarter_new.php', dues: 1, f: ['dses', 'dcls', 'dsec', 'dadm', 'dtype', 'dstatus', 'dscheme'],
    run(rows) {
      const ms = [4, 5, 6, 7, 8, 9, 10, 11, 12, 1, 2, 3], mt = (r, m, k) => sum(r.heads.filter(h => h.m === m), k);
      const head = `<tr>${['Sno', 'Status', 'Student Type', 'Class', 'Section', 'Stream', 'Scholar No', 'Name', "Father's Name", "Father's Mobile No", "Mother's Mobile No", 'Scheme'].map(h => `<th rowspan="2">${h}</th>`).join('')}${MONTHS.map(m => `<th colspan="3">${m}</th>`).join('')}<th rowspan="2">Net Paid Amount</th><th rowspan="2">Net Due Amount</th></tr><tr>${MONTHS.map(() => '<th>Total</th><th>Paid/Disc</th><th>Due</th>').join('')}</tr>`;
      const body = rows.map((r, i) => `<tr>${[i + 1, r.status, r.stuType, r.cls, r.section, r.stream, r.adm].map(c => `<td>${esc(c)}</td>`).join('')}<td class="t">${esc(r.name)}</td><td class="t">${esc(r.father)}</td><td>${esc(r.fatherMobile)}</td><td>${esc(r.motherMobile)}</td><td>${esc(r.scheme)}</td>${ms.map(m => { const a = mt(r, m, 'amt'), p = mt(r, m, 'paid'); return `<td>${n0(a)}</td><td>${n0(p)}</td><td>${n0(a - p)}</td>`; }).join('')}<td>${n0(sum(r.heads, 'paid'))}</td><td>${n0(due(r, 'March').due)}</td></tr>`).join('');
      return `<div class="sub">Session: ${esc(F.dses)}</div><div class="lw"><table class="lst">${head}${body}</table></div>`;
    } },
  headstmt: { title: 'Head Wise Fee Statement', page: 'report_feestatement.php', dues: 1, f: ['dses', 'dcls', 'dsec', 'dmonth'],
    run(rows) {
      const M = F.upto || 'March', hs = headNames(rows), sumH = (n, k) => sum(rows, r => sum(upto(r, M).filter(h => h.name === n), k));
      return T(['Fee Head', 'Amount', 'Paid/Disc Amount', 'Due Amount'], hs.map(n => tr([n, n0(sumH(n, 'amt')), n0(sumH(n, 'paid')), n0(sumH(n, 'amt') - sumH(n, 'paid'))])),
        ['Total', n0(sum(hs, n => sumH(n, 'amt'))), n0(sum(hs, n => sumH(n, 'paid'))), n0(sum(hs, n => sumH(n, 'amt') - sumH(n, 'paid')))]);
    } },
  sthead: { title: 'Fee Statement Head Wise', page: 'stu_fee_statement_head.php', dues: 1, f: ['dses', 'dcls', 'dsec', 'dmonth', 'dstatus'],
    run(rows) {
      const M = F.upto || 'March', hs = headNames(rows), v = (r, n, k) => sum(upto(r, M).filter(h => h.name === n), k);
      const head = `<tr>${['Sno', 'Status', 'Admission As', 'Student Type', 'Class', 'Section', 'Scholar No', 'Name', 'Mobile Number', "Father's Name", 'Route'].map(h => `<th rowspan="2">${h}</th>`).join('')}${hs.map(n => `<th colspan="3">${esc(n)}</th>`).join('')}<th rowspan="2">Total Amt</th><th rowspan="2">Total Paid/Disc</th><th rowspan="2">Total Due Amt</th><th rowspan="2">Old Due</th><th rowspan="2">Discount Amount</th><th rowspan="2">Currently Received</th><th rowspan="2">Due Amount</th></tr><tr>${hs.map(() => '<th>Amt</th><th>Paid/Disc</th><th>Due</th>').join('')}</tr>`;
      const body = rows.map((r, i) => { const d = due(r, M), ta = sum(upto(r, M), 'amt'), tp = sum(upto(r, M), 'paid'); return `<tr>${[i + 1, r.status, r.admAs, r.stuType, r.cls, r.section, r.adm].map(c => `<td>${esc(c)}</td>`).join('')}<td class="t">${esc(r.name)}</td><td>${esc(r.phone)}</td><td class="t">${esc(r.father)}</td><td class="t">${esc(r.route)}</td>${hs.map(n => `<td>${n0(v(r, n, 'amt'))}</td><td>${n0(v(r, n, 'paid'))}</td><td>${n0(v(r, n, 'amt') - v(r, n, 'paid'))}</td>`).join('')}<td>${n0(ta)}</td><td>${n0(tp)}</td><td>${n0(ta - tp)}</td><td>${n0(r.oldDue)}</td><td>${n0(r.discount)}</td><td>${n0(r.received)}</td><td>${n0(d.due)}</td></tr>`; }).join('');
      return `<div class="sub">Session: ${esc(F.dses)} Month ${esc(M)}</div><div class="lw"><table class="lst">${head}${body}</table></div>`;
    } },
  defhead: { title: 'Fee Defaulter Head Wise', page: 'studuedfaulter_head.php', dues: 1, f: ['dses', 'dcls', 'dsec', 'dhead', 'dmonth'],
    run(rows) {
      const M = F.upto || 'March', n = F.dhead || headNames(rows)[0], v = (r, k) => sum(upto(r, M).filter(h => h.name === n), k);
      const x = rows.filter(r => v(r, 'amt') - v(r, 'paid') > 0);
      return `<div class="sub">Session: ${esc(F.dses)} Month ${esc(M)}</div>` + T(['Sno', 'Status', 'Admission As', 'Student Type', 'Class', 'Section', 'Stream', 'Scholar No', 'Name', 'Mobile Number', "Father's Name", 'Address', 'Route', 'Total Amount', n + ' (Amt)', n + ' (Paid/Disc Amt)', n + ' (Due Amt)', 'Old Due', 'Discount Amount', 'Currently Received', 'Due Amount'],
        x.map((r, i) => { const d = due(r, M); return tr([i + 1, r.status, r.admAs, r.stuType, r.cls, r.section, r.stream, r.adm, { v: r.name, c: 't' }, r.phone, { v: r.father, c: 't' }, { v: r.address, c: 't' }, { v: r.route, c: 't' }, n0(d.total), n0(v(r, 'amt')), n0(v(r, 'paid')), n0(v(r, 'amt') - v(r, 'paid')), n0(r.oldDue), n0(r.discount), n0(r.received), n0(d.due)]); }),
        ['', '', '', '', '', '', '', '', 'Total (' + x.length + ')', '', '', '', '', '', n0(sum(x, r => v(r, 'amt'))), n0(sum(x, r => v(r, 'paid'))), n0(sum(x, r => v(r, 'amt') - v(r, 'paid'))), '', '', '', '']);
    } },
  deleted: { title: 'Deleted Fee Receipt', page: 'report_delete_fee.php', f: ['dates', 'session'],
    run(rows) { const x = rows.filter(r => r.status === 'Cancelled'); return T(['Sno', 'Session', 'Receipt No', 'Date', 'Scholar No', 'Class', 'Section', 'Name', 'Total Amount', 'Discount', 'Late Fee', 'Currently Received', 'PMode', 'Remark', 'UName'], x.map((r, i) => tr([i + 1, longSes(r.session), r.no, dmy(r.date), r.adm, r.cls, r.section, { v: r.name, c: 't' }, n0(r.total), n0(r.discount), n0(r.late), n0(r.received), r.mode, { v: r.remark, c: 't' }, r.user])), ['', '', '', '', '', '', '', 'Total', n0(sum(x, 'total')), n0(sum(x, 'discount')), n0(sum(x, 'late')), n0(sum(x, 'received')), '', '', '']) + '<p class="note">Receipts cancelled in the new Fee Receipt (with the reason). Old ERP deletions are in Old ERP Reports.</p>'; } }
});
// ---------- Account reports (old ERP Account Report menu): fee receipts + old vouchers + School Office Cash Book ----------
let ACC = null, ACCLOAD = false;
async function loadAcc() {
  if (ACC || ACCLOAD) return; ACCLOAD = true;
  try { const r = await fetch('feereports_api.php?a=accounts', { headers: { 'X-School-Token': localStorage.getItem('sch_token') || '' } }); const d = await r.json(); if (!r.ok || !d.ok) throw Error(d.error || 'Could not open the report.'); ACC = d; }
  catch (e) { toast(e.message); }
  ACCLOAD = false; render();
}
const accIn = rows => { const s = iso(F.sdate), e = iso(F.edate); return rows.filter(r => (!s || r.date >= s) && (!e || r.date <= e) && (!F.user || F.user === 'All' || r.user === F.user)); };
const AFL = ['dates', 'auser'];
FL.auser = () => [['User', 'user', ['All', ...uniq((ACC ? ACC.rows : []).map(r => r.user))]]];
const kindName = { Fee: 'Fee', RV: 'Receipt', OFREC: 'Other Fee', JV: 'Payment', SV: 'Payment', BD: 'Bank Deposit', SW: 'Self Withdrawal' };
Object.assign(REP, {
  cashbook: { title: 'Cashbook', page: 'report_cashbook.php', acc: 1, f: AFL,
    run(rows) {
      const t = k => sum(rows, k), cashRec = t('drCash'), bankDr = t('drBank'), cashCr = t('crCash'), bankCr = t('crBank');
      const bd = sum(rows.filter(r => r.kind === 'BD'), 'crCash'), cashPay = cashCr - bd, selfW = sum(rows.filter(r => r.kind === 'SW'), 'crBank');
      return T(['Sno', 'Date', 'Session', 'Vch No/Rec No', 'Name', 'Scholar No', 'Class', 'Remark', 'Particular', 'Cash Dr', 'Bank Dr', 'Cash Cr', 'Bank Cr', 'PMode', 'UName'],
        rows.map((r, i) => tr([i + 1, dmy(r.date), longSes(r.session), r.vno, { v: r.name, c: 't' }, r.adm, r.cls, { v: r.remark, c: 't' }, r.sub, n0(r.drCash), n0(r.drBank), n0(r.crCash), n0(r.crBank), r.pmode, r.user])),
        ['', '', '', '', '', '', '', '', 'Total', 'Cash Receipts ' + n0(cashRec) + ' Total ' + n0(cashRec), 'Bank Deposit ' + n0(bankDr - selfW * 0) + ' Total ' + n0(bankDr), 'Cash Payments ' + n0(cashPay) + ' Bank Deposit ' + n0(bd) + ' Total ' + n0(cashCr), 'Bank Payments ' + n0(bankCr - selfW) + ' Self Withdrawal ' + n0(selfW) + ' Total ' + n0(bankCr), '', '']);
    } },
  cashsum: { title: 'Cash Summary Report', page: 'report_cashb.php', acc: 1, f: AFL,
    run(rows) { const x = rows.filter(r => r.drCash || r.crCash); return T(['Sno', 'Date', 'Name', 'Particular', 'Vch/Rec No', 'Cash Debit', 'Cash Credit', 'PMode', 'UName'], x.map((r, i) => tr([i + 1, dmy(r.date), { v: r.name, c: 't' }, r.sub, r.vno, n0(r.crCash), n0(r.drCash), r.pmode, r.user])), ['', '', '', '', 'Total', n0(sum(x, 'crCash')), n0(sum(x, 'drCash')), '', '']); } },
  bankstmt: { title: 'Bank Statement', page: 'report_bankstatement.php', acc: 1, f: AFL,
    run(rows) {
      const s = iso(F.sdate); let bal = ACC.openBank + sum(ACC.rows.filter(r => s && r.date < s), r => r.drBank - r.crBank);
      const x = rows.filter(r => r.drBank || r.crBank);
      return T(['Sno', 'Date', 'Name', 'Particular', 'Remark', 'Vch/Rec No', 'Opening Balance', 'Bank Debit', 'Bank Credit', 'Closing Balance', 'PMode', 'UName'],
        x.map((r, i) => { const o = bal; bal += r.drBank - r.crBank; return tr([i + 1, dmy(r.date), { v: r.name, c: 't' }, r.kind === 'BD' ? 'BD' : r.sub, { v: r.remark, c: 't' }, r.vno, n0(o), r.crBank ? n0(r.crBank) : '', r.drBank ? n0(r.drBank) : '', n0(bal), r.pmode, r.user]); }),
        ['', '', '', '', '', 'Total', '', n0(sum(x, 'crBank')), n0(sum(x, 'drBank')), n0(bal), '', '']);
    } },
  bankrep: { title: 'Bank Report', page: 'report_bank.php', acc: 1, f: AFL,
    run(rows) { const x = rows.filter(r => r.drBank || r.crBank); return T(['Sno', 'Date', 'Name', 'Particular', 'Vch/Rec No', 'Remark', 'Bank Debit', 'Bank Credit', 'PMode', 'UName'], x.map((r, i) => tr([i + 1, dmy(r.date), { v: r.name, c: 't' }, r.kind === 'BD' ? 'BD' : r.sub, r.vno, { v: r.remark, c: 't' }, n0(r.crBank), n0(r.drBank), r.pmode, r.user])), ['', '', '', '', 'Total', '', n0(sum(x, 'crBank')), n0(sum(x, 'drBank')), '', '']); } },
  payment: { title: 'Payment Report', page: 'cashbook_summery.php', acc: 1, f: AFL,
    run(rows) { const x = rows.filter(r => r.kind === 'JV' || r.kind === 'SV'); return T(['Sno', 'Date', 'Name', 'Particular', 'Vch/Rec No', 'Remark', 'Amount', 'Status', 'PMode', 'UName'], x.map((r, i) => tr([i + 1, dmy(r.date), { v: r.name, c: 't' }, r.sub, r.vno, { v: r.remark, c: 't' }, n0(r.crCash + r.crBank), 'Active', r.pmode, r.user])), ['', '', '', '', '', 'Total', n0(sum(x, r => r.crCash + r.crBank)), '', '', '']); } },
  incexp: { title: 'Income & Expenditure Report', page: 'report_incexpregrpt.php', acc: 1, f: AFL,
    run(rows) { const x = rows.filter(r => r.kind === 'JV' || r.kind === 'SV'); return T(['Sno', 'Date', 'Name', 'Particular', 'Voucher No', 'Amount', 'Cash/ Cheque', 'Remarks'], x.map((r, i) => tr([i + 1, dmy(r.date), { v: r.name, c: 't' }, r.head !== r.sub ? r.head + '->' + r.sub : r.sub, r.vno, n0(r.crCash + r.crBank), r.mode, { v: r.remark, c: 't' }])), ['', '', '', 'Total', '', n0(sum(x, r => r.crCash + r.crBank)), '', '']); } },
  rcptsum: { title: 'Receipt Summary', page: 'report_receipt_summary.php', acc: 1, f: AFL,
    run(rows) { const x = rows.filter(r => r.kind === 'RV' || r.kind === 'OFREC'); return T(['Sno', 'Date', 'Scholar No', 'Name', 'Particular', 'Voucher No', 'Amount', 'Cash/Cheque', 'Remarks'], x.map((r, i) => tr([i + 1, dmy(r.date), r.adm, { v: r.name, c: 't' }, r.sub, r.vno, n0(r.drCash), r.mode, { v: r.remark, c: 't' }])), ['', '', '', '', '', 'Total', n0(sum(x, 'drCash')), '', '']); } },
  bdeposit: { title: 'Bank Deposit Report', page: 'report_bdeposit.php', acc: 1, f: AFL,
    run(rows) { const x = rows.filter(r => r.kind === 'BD'); return T(['Sno', 'Deposit Date', 'Voucher No', 'Bank', 'Amount', 'PMode', 'Remark', 'UName'], x.map((r, i) => tr([i + 1, dmy(r.date), r.vno, { v: r.name, c: 't' }, n0(r.drBank), r.pmode, { v: r.remark, c: 't' }, r.user])), ['', '', '', 'Total', n0(sum(x, 'drBank')), '', '', '']); } },
  dainikee: { title: 'Dainikee', page: 'report_dainikee.php', acc: 1, f: AFL,
    run(rows) { const x = rows.filter(r => r.drCash); return T(['Date', 'Session', 'Rcpt No', 'Amount', 'Payment Mode', 'Scholar No', 'Particular', 'Head', 'Class/Ledger Folio', 'Remark', 'Uname'], x.map(r => tr([dmy(r.date), longSes(r.session), r.vno, n0(r.drCash), r.mode, r.adm, { v: r.name, c: 't' }, r.kind === 'Fee' ? 'Fee-> Fee' : r.head + '-> ' + r.sub, r.cls, { v: r.remark, c: 't' }, r.user])), ['', '', 'Total', n0(sum(x, 'drCash')), '', '', '', '', '', '', '']); } }
});
async function loadDues2(ses, key) {
  if (DUELOAD === key) return; DUELOAD = key;
  try { const r = await fetch('feereports_api.php?a=dues&ses=' + encodeURIComponent(ses), { headers: { 'X-School-Token': localStorage.getItem('sch_token') || '' } }); const d = await r.json(); if (!r.ok || !d.ok) throw Error(d.error || 'Could not open the report.'); DUES[key] = d; }
  catch (e) { toast(e.message); }
  DUELOAD = ''; render();
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
  return `<table class="flt" style="margin:0 auto"><tr><td class="l">School</td><td><input type="text" value="${esc(D.school)}" readonly></td></tr>${rep.f.flatMap(k => (FL[k] || FD[k])()).map(ctl).join('')}</table>${chooser}
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
  if (rep.dues) { if (!F.dses) F.dses = longSes(D.session); if (!F.upto) F.upto = MONTHS[(new Date().getMonth() + 9) % 12]; const ses = F.dses.replace(/^(20\d\d)-20(\d\d)$/, '$1-$2'); if (!DUES[F.dses]) { DUES[F.dses] = null; delete DUES[F.dses]; loadDues2(ses, F.dses); } }
  if (rep.acc) loadAcc();
  const dd = rep.dues ? DUES[F.dses] : null;
  const rows = OUT ? rep.acc ? (ACC ? accIn(ACC.rows) : null) : (rep.dues ? (dd ? dd.rows.filter(dpass).sort(byClass) : null) : D.receipts.filter(r => pass(r, rep.f.includes('dates') ? F : { ...F, sdate: '', edate: '' }))) : null;
  $('frRoot').innerHTML = `<div class="sm"><h3>${esc(rep.title)}</h3><div class="np">${formHtml(rep)}</div>
    ${OUT && ((rep.dues && !dd) || (rep.acc && !ACC)) ? '<p>Opening…</p>' : ''}
    ${rows ? `<div class="sch">${esc(D.school)}</div><div class="sub">${esc(rep.title)}${rep.f.includes('dates') ? ' — ' + esc(F.sdate) + ' to ' + esc(F.edate) : ''}</div>${dd && !dd.live ? `<p class="note" style="text-align:center">Fee Receipt is not started for this session yet, so dues are from the old ERP fee cards as copied on ${esc(dd.asOn)}.</p>` : ''}
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
