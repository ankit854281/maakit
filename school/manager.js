/* Salary app (manager.php) — owner, 12 Nov 2026: "manager ke liye app banao salary se related — bas manager,
   chairman sir dekh payen, aur koi nahi". Manager: set salary, add staff, adjust, mark paid, reopen, slips, register.
   Chairman: sees everything (read-only) + sets the Manager's name and PIN. Data: salary_api.php (Manager / Chairman only),
   staff attendance: staffatt.php. Nothing is cached on the phone. */
(function () {
  const $ = id => document.getElementById(id), store = { get: k => localStorage.getItem(k), set: (k, v) => v == null ? localStorage.removeItem(k) : localStorage.setItem(k, v) };
  const esc = s => String(s == null ? '' : s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
  const rs = n => '₹' + Math.round(+n || 0).toLocaleString('en-IN'), dd = n => (+n % 1 ? (+n).toFixed(1) : String(+n || 0));
  const pad = n => String(n).padStart(2, '0'), now = new Date(), ymOf = d => d.getFullYear() + '-' + pad(d.getMonth() + 1);
  const ymName = ym => new Date(ym + '-01T00:00:00').toLocaleDateString('en-IN', { month: 'long', year: 'numeric' });
  const dmy = d => d ? d.split('-').reverse().join('-') : '';
  const toast = t => { const e = $('toast'); e.textContent = t; e.style.display = 'block'; clearTimeout(toast.t); toast.t = setTimeout(() => e.style.display = 'none', 3500); };
  const TK = 'sal_token';                                   // own token key: the Salary app login never mixes with the ERP login on the same phone
  let SEC = store.get('mg_sec') || 'sal', YM = ymOf(now.getDate() <= 20 ? new Date(now.getFullYear(), now.getMonth() - 1, 1) : now), D = null, VIEW = 'all', MUST = false, ROLE = store.get('sal_role') || 'manager', NAMES = {};
  if (ROLE === 'chairman') VIEW = 'table';
  if ('serviceWorker' in navigator) navigator.serviceWorker.register('manager-sw.js').catch(() => {});
  // Opened from the Chairman app (?from=ch): use the Chairman's login already on this phone — no second PIN.
  if (/[?&]from=ch/.test(location.search) && !store.get(TK) && store.get('sch_token')) { store.set(TK, store.get('sch_token')); store.set('sal_shared', '1'); ROLE = 'chairman'; store.set('sal_role', 'chairman'); }
  async function api(path, body) {
    const r = await fetch(path, { method: body ? 'POST' : 'GET', cache: 'no-store', headers: { 'Content-Type': 'application/json', 'X-School-Token': store.get(TK) || '' }, ...(body ? { body: JSON.stringify(body) } : {}) });
    let j = null; try { j = await r.json(); } catch (e) {}
    if (r.status === 401) { store.set(TK, null); D = null; draw(); throw Error('Please log in again.'); }
    if (r.status === 428) { MUST = true; draw(); throw Error('Choose your own PIN first.'); }
    if (!r.ok || !j || !j.ok) throw Error((j && j.error) || 'Server problem. Please try again.');
    return j;
  }
  const sal = (a, body, q) => api('salary_api.php?a=' + a + (q || ''), body);
  // ---------------- login ----------------
  async function loginView() {
    $('sh').innerHTML = '';
    try { NAMES = (await api('api.php?a=status')).office || {}; } catch (e) {}
    $('app').innerHTML = `<div class="lg"><h2>Salary</h2><p class="mut">SN Mishra Singapore International School · Manager and Chairman only</p>
      ${MUST ? `<div class="f"><label>Choose your own PIN (4–8 digits, not 1234)</label><input id="nP" type="password" inputmode="numeric" maxlength="8"></div><button class="btn go full f" onclick="mgNewPin()">Save my PIN</button>`
      : `<div class="f"><label>Who are you?</label><select id="lR" onchange="ROLESET(this.value)"><option value="manager" ${ROLE === 'manager' ? 'selected' : ''}>${esc(NAMES.manager || 'Manager')} — Manager</option><option value="chairman" ${ROLE === 'chairman' ? 'selected' : ''}>Chairman</option></select></div>
        <div class="f"><label>PIN</label><input id="lP" type="password" inputmode="numeric" maxlength="8" onkeydown="if(event.key==='Enter')mgLogin()"></div><button class="btn go full f" onclick="mgLogin()">Open</button>
        <p class="mut f">Manager: use the PIN the Chairman gave you (if it was 1234 you will choose your own). Only the Chairman sets or resets the Manager's PIN.</p>${/; wv\)/.test(navigator.userAgent) ? '' : '<p class="mut f"><a href="https://maakit.in/school/downloads/snmsis-salary.apk">⬇ Download the SNMSIS Salary app (Android)</a></p>'}`}</div>`;
  }
  window.ROLESET = v => { ROLE = v; store.set('sal_role', v); };
  window.mgLogin = async () => { const pin = $('lP').value.trim();
    try { const r = await api('api.php?a=login', { role: ROLE, pin }); store.set('sal_shared', null); store.set(TK, r.token); window._old = pin; MUST = !!r.mustChange; draw(); } catch (e) { toast(e.message); } };
  window.mgNewPin = async () => { const n = $('nP').value.trim(); if (!/^\d{4,8}$/.test(n) || n === '1234') return toast('PIN must be 4–8 digits and not 1234.');
    try { await api('api.php?a=setpin', { old: window._old || '1234', pin: n }); MUST = false; toast('PIN saved.'); draw(); } catch (e) { toast(e.message); } };
  // a login carried over from the Chairman app is only forgotten here — logging it out would also log out the Chairman app
  window.mgLogout = async () => { if (!store.get('sal_shared')) { try { await api('api.php?a=logout', {}); } catch (e) {} } store.set('sal_shared', null); store.set(TK, null); D = null; draw(); };
  // ---------------- main ----------------
  function months() { const o = []; for (let i = -6; i <= 1; i++) o.push(ymOf(new Date(now.getFullYear(), now.getMonth() + i, 1))); return o.reverse(); }
  async function load() { $('app').innerHTML = '<div class="pg"><div class="card mut">Opening salary…</div></div>';
    try { D = await sal('sheet', null, '&ym=' + YM); }
    catch (e) { if (/only in the Salary app/.test(e.message)) { store.set(TK, null); store.set('sal_shared', null); toast('This login cannot open salary. Log in as the Manager or the Chairman.'); return loginView(); }
      $('app').innerHTML = `<div class="pg"><div class="card">${esc(e.message)}</div></div>`; return; }
    draw(); }
  window.mgMonth = v => { YM = v; load(); };
  window.mgView = v => { VIEW = v; draw(); };
  function why(c) {
    if (!c) return '';
    if (c.manual) return 'absent days typed: ' + dd(c.absent);
    if (!c.auto) return 'no attendance — type days in Adjust';
    const a = c.auto, p = [];
    if (a.nonTeaching) { if (a.leave) p.push(a.leave + ' absent'); if (a.half) p.push(a.half + ' half day'); return p.join(', ') || 'full attendance'; }
    if (a.leave) p.push(a.leave + ' leave'); if (a.half) p.push(a.half + ' half day'); if (a.absent) p.push(dd(a.absent) + ' without permission');
    return p.join(', ') || 'full attendance';
  }
  // Owner, 17 Nov 2026: "proper working days, present, absent, CL ye sab add kariye, chairman sir ko show kare kitna salary dena hai pure month ka"
  const clUsed = c => Math.max(0, (+c.absent || 0) - (+c.unpaid || 0)), present = c => Math.max(0, (+c.workDays || 0) - (+c.absent || 0));
  function card(r) {
    const s = r.staff, c = r.calc, st = r.status === 'Not set' ? 'Not' : r.status, can = D.can.edit, id = s.id;
    return `<div class="st"><div class="h"><div><b>${esc(s.name)}<span class="tag ${st}">${r.status === 'Not set' ? 'Salary not set' : esc(r.status)}</span></b><small>${esc(s.role)}${s.teaching ? '' : ' · other staff'}</small></div>
      <div class="net"><small class="mut">Net pay</small><b>${r.status === 'Not set' ? '—' : rs(c.net)}</b></div></div>
      ${r.status === 'Not set' ? '' : `<div class="pills"><span class="pl">Salary ${rs(c.gross)}</span><span class="pl">Working days ${c.workDays}</span><span class="pl g">Present ${dd(present(c))}</span><span class="pl ${c.absent ? 'r' : ''}">Absent ${dd(c.absent)}</span><span class="pl">CL used ${dd(clUsed(c))} / ${dd(c.paidLeave)}</span><span class="pl">${esc(why(c))}</span>
        ${c.deduction ? `<span class="pl r">Cut ${rs(c.deduction)} (${dd(c.unpaid)} day${c.unpaid == 1 ? '' : 's'})</span>` : ''}${c.workedOnly ? '<span class="pl r">More than 4 leave — paid for days worked</span>' : ''}
        ${c.advance ? `<span class="pl r">Advance −${rs(c.advance)}</span>` : ''}${c.autoBonus ? `<span class="pl g">Full attendance +${rs(c.autoBonus)}</span>` : ''}${c.bonus - (c.autoBonus || 0) ? `<span class="pl g">Bonus +${rs(c.bonus - (c.autoBonus || 0))}</span>` : ''}${c.note ? `<span class="pl y">${esc(c.note)}</span>` : ''}</div>`}
      ${r.pay ? `<p class="mut" style="margin:6px 0 0">Paid ${esc(dmy(r.pay.paidOn))} by ${esc(r.pay.mode)}${r.pay.ref ? ' (' + esc(r.pay.ref) + ')' : ''} · ${esc(r.pay.by || '')}</p>` : ''}
      <div class="acts">${r.status === 'Paid' ? `<button class="btn l" onclick="mgSlip('${id}')">Salary slip</button>${can ? `<button class="btn l" onclick="mgUnpay('${id}')">Reopen</button>` : ''}` : ''}
        ${can && r.status === 'Due' ? `<button class="btn go" onclick="mgPay('${id}')">Pay ${rs(c.net)}</button><button class="btn l" onclick="mgAdj('${id}')">Adjust</button>` : ''}
        ${can && r.status !== 'Paid' ? `<button class="btn l" onclick="mgStaff('${id}')">${r.status === 'Not set' ? 'Set salary' : 'Edit salary'}</button>` : ''}</div></div>`;
  }
  function kpis(set, paid, sum) {
    return `<div class="kp"><div class="k"><span>Total salary</span><b>${rs(sum('gross'))}</b><small>${set.length} staff with salary set</small></div>
        <div class="k r"><span>Cut for absence</span><b>${rs(sum('deduction'))}</b><small>advance ${rs(sum('advance'))}</small></div>
        <div class="k g"><span>Net to pay · full month</span><b>${rs(sum('net'))}</b><small>bonus ${rs(sum('bonus'))}</small></div>
        <div class="k y"><span>Paid</span><b>${paid.length} / ${set.length}</b><small>${rs(paid.reduce((a, r) => a + r.calc.net, 0))} paid</small></div></div>`;
  }
  // 📋 Month sheet: one line per staff — Working days, Present, Absent, CL, cut, net — and the month total (Chairman opens here).
  // Owner, 17 Nov 2026: "formatting aur share ya download karne ka option nahi diya, proper dekh bhi nahi raha" —
  // phone view: name column stays fixed while the numbers scroll; buttons: PDF (print → Save as PDF, works inside the
  // Android app through SNMSISFiles.printHtml), Excel (CSV through SNMSISFiles.saveCsv / browser download),
  // WhatsApp (summary text) and Share picture (browser share sheet; inside the app → PDF instead).
  const SCH = () => D.school || 'SN Mishra Singapore International School';
  const sheetRows = () => D.rows.filter(r => r.status !== 'Not set');
  const bonAdv = c => (c.bonus || 0) - (c.advance || 0);
  const COLS = ['Sr.', 'Name', 'Salary', 'W.D.', 'Present', 'Absent', 'CL', 'Cut days', 'Cut ₹', 'Bonus/Adv.', 'Net pay'];
  function monthTable() {
    const R = sheetRows(), ns = D.rows.filter(r => r.status === 'Not set'), tot = k => R.reduce((a, r) => a + (+r.calc[k] || 0), 0);
    const line = (r, i) => { const c = r.calc, ba = bonAdv(c);
      return `<tr class="${i % 2 ? 'z' : ''}"><td class="sr">${r.regNo || '—'}</td><td class="nm"><b>${esc(r.staff.name)}</b><small>${esc(r.staff.role)}${r.status === 'Paid' ? ' · <span style="color:#14532d">Paid</span>' : ''}</small></td>
        <td>${rs(c.gross)}</td><td>${c.workDays}</td><td>${dd(present(c))}</td><td class="${c.absent ? 'ab' : ''}">${dd(c.absent)}</td><td>${dd(clUsed(c))}</td><td>${dd(c.unpaid)}</td>
        <td class="ab">${c.deduction ? '−' + rs(c.deduction) : '—'}</td><td>${ba ? (ba > 0 ? '+' : '−') + rs(Math.abs(ba)) : '—'}</td><td class="np">${rs(c.net)}</td></tr>`; };
    return `<style>.ms{border-collapse:separate;border-spacing:0;font-size:12.5px;min-width:720px;width:100%}.ms th,.ms td{border-bottom:1px solid #e3ebe6;padding:7px 6px;text-align:center;white-space:nowrap;background:#fff}
      .ms th{background:#0F5132;color:#fff;font-size:11.5px;position:sticky;top:0;z-index:2}.ms tr.z td{background:#F6FAF7}.ms .sr{position:sticky;left:0;z-index:1;width:34px;color:#5b6b63}
      .ms .nm{position:sticky;left:34px;z-index:1;text-align:left;min-width:128px;max-width:150px;white-space:normal;border-right:1px solid #cfdcd4}.ms .nm small{display:block;color:#6b7b73;font-size:11px}
      .ms th.sr,.ms th.nm{z-index:3;background:#0F5132}.ms .ab{color:#b42318}.ms .np{font-weight:700;color:#0F5132;font-size:13.5px}.ms tr.t td{background:#E3F4EA;font-weight:700;border-top:2px solid #0F5132}
      .msw{overflow:auto;max-height:70vh;border:1px solid #dfe7e2;border-radius:12px}</style>
      <div class="card"><div style="display:flex;justify-content:space-between;gap:8px;flex-wrap:wrap;align-items:flex-start"><div><h3 style="margin:0;color:#0F5132">${esc(ymName(YM))} — Salary sheet</h3>
        <p class="mut" style="margin:2px 0 0">${D.workDays} working days · ${R.length} staff · Net to pay <b style="color:#0F5132">${rs(tot('net'))}</b></p></div></div>
      <div class="acts" style="margin:10px 0">${'<button class="btn go" onclick="mgSheetPdf()">📄 PDF / Print</button><button class="btn" onclick="mgSheetCsv()">⬇ Excel</button><button class="btn" style="background:#25D366" onclick="mgSheetWa()">WhatsApp</button><button class="btn l" onclick="mgSheetPic()">🖼 Share picture</button>'}</div>
      <div class="msw"><table class="ms"><thead><tr>${COLS.map((h, i) => `<th class="${i === 0 ? 'sr' : i === 1 ? 'nm' : ''}">${h}</th>`).join('')}</tr></thead><tbody>
      ${R.map(line).join('')}
      <tr class="t"><td class="sr"></td><td class="nm">Total (${R.length})</td><td>${rs(tot('gross'))}</td><td colspan="5"></td><td class="ab">−${rs(tot('deduction'))}</td><td></td><td class="np">${rs(tot('net'))}</td></tr></tbody></table></div>
      <p class="mut" style="margin:8px 0 0">Sr. = Teacher Attendance Register Sr. No. ("—" = not in it) · W.D. = working days · CL = paid leave used · ← → scroll the numbers, the name stays.</p>
      ${ns.length ? `<p style="margin:8px 0 0;color:#8a1c1c"><b>Salary not set (${ns.length}):</b> ${ns.map(r => esc(r.staff.name)).join(', ')} — not in the total.</p>` : ''}</div>`;
  }
  // A4 landscape payout sheet (same for PDF and print)
  function sheetDoc() {
    const R = sheetRows(), td = 'border:1px solid #888;padding:5px 4px;font-size:11px;text-align:center', tot = k => R.reduce((a, r) => a + (+r.calc[k] || 0), 0);
    const H = ['Sr.', 'Name', 'Designation', 'Monthly Salary', 'Working Days', 'Present', 'Absent', 'CL', 'Cut Days', 'Per Day', 'Deduction', 'Bonus / Advance', 'Net Payable', 'Signature'];
    return `<!doctype html><html><head><meta charset="utf-8"><title>Salary ${esc(ymName(YM))}</title><style>@page{size:A4 landscape;margin:10mm}body{font-family:Arial,sans-serif;color:#111;margin:0}table{border-collapse:collapse;width:100%}th{background:#0F5132;color:#fff}tr:nth-child(even) td{background:#f3f7f4}</style></head><body>
      <div style="text-align:center"><div style="font-size:18px;font-weight:bold">${esc(SCH())}, Bhadohi</div><div style="font-size:14px;margin-top:2px">Staff Salary Sheet — ${esc(ymName(YM))}</div>
      <div style="font-size:11px;color:#444;margin:3px 0 8px">${D.workDays} working days · Per day = salary ÷ days in the month · first absent day(s) covered by CL</div></div>
      <table><tr>${H.map(h => `<th style="${td};font-size:10.5px">${h}</th>`).join('')}</tr>
      ${R.map(r => { const c = r.calc, ba = bonAdv(c), dim = new Date(+YM.slice(0, 4), +YM.slice(5, 7), 0).getDate();
        return `<tr><td style="${td}">${r.regNo || '—'}</td><td style="${td};text-align:left"><b>${esc(r.staff.name)}</b></td><td style="${td}">${esc(r.staff.role)}</td><td style="${td}">${rs(c.gross)}</td><td style="${td}">${c.workDays}</td><td style="${td}">${dd(present(c))}</td><td style="${td}">${dd(c.absent)}</td><td style="${td}">${dd(clUsed(c))}</td><td style="${td}">${dd(c.unpaid)}</td><td style="${td}">${rs(c.gross / dim)}</td><td style="${td}">${c.deduction ? rs(c.deduction) : '—'}</td><td style="${td}">${ba ? (ba > 0 ? '+' : '−') + rs(Math.abs(ba)) : '—'}</td><td style="${td};font-weight:bold">${rs(c.net)}</td><td style="${td};width:70px">${r.status === 'Paid' ? 'Paid ' + esc(dmy(r.pay && r.pay.paidOn)) : ''}</td></tr>`; }).join('')}
      <tr><td style="${td};font-weight:bold;text-align:right" colspan="3">Total (${R.length} staff)</td><td style="${td};font-weight:bold">${rs(tot('gross'))}</td><td style="${td}" colspan="6"></td><td style="${td};font-weight:bold">${rs(tot('deduction'))}</td><td style="${td}"></td><td style="${td};font-weight:bold;font-size:13px">${rs(tot('net'))}</td><td style="${td}"></td></tr></table>
      <div style="display:flex;justify-content:space-between;margin-top:34px;font-size:12px"><span>Prepared by (Manager) ______________</span><span>Checked by ______________</span><span>Approved by (Chairman) ______________</span></div></body></html>`;
  }
  window.mgSheetPdf = () => { const doc = sheetDoc();
    if (window.SNMSISFiles && typeof SNMSISFiles.printHtml === 'function') { SNMSISFiles.printHtml('Salary ' + ymName(YM), doc); return toast('Printer me "Save as PDF" chuniye, phir PDF share kijiye.'); }
    const w = window.open('', '_blank'); if (!w) return toast('Allow pop-ups to print.'); w.document.write(doc.replace('</body>', '<script>window.onload=()=>window.print()<\/script></body>')); w.document.close(); };
  window.mgSheetCsv = () => { const R = sheetRows(), q = v => '"' + String(v).replace(/"/g, '""') + '"';
    const L = [['Sr.', 'Name', 'Designation', 'Monthly Salary', 'Working Days', 'Present', 'Absent', 'CL', 'Cut Days', 'Deduction', 'Bonus', 'Advance', 'Net Payable', 'Status'].join(',')];
    R.forEach(r => { const c = r.calc; L.push([r.regNo || '', q(r.staff.name), q(r.staff.role), c.gross, c.workDays, present(c), c.absent, clUsed(c), c.unpaid, c.deduction, c.bonus || 0, c.advance || 0, c.net, r.status].join(',')); });
    L.push(['', q('Total'), '', R.reduce((a, r) => a + r.calc.gross, 0), '', '', '', '', '', R.reduce((a, r) => a + r.calc.deduction, 0), '', '', R.reduce((a, r) => a + r.calc.net, 0), ''].join(','));
    const txt = '﻿' + L.join('\r\n'), fn = 'Salary-' + YM + '.csv';
    if (window.SNMSISFiles && typeof SNMSISFiles.saveCsv === 'function') return SNMSISFiles.saveCsv(fn, btoa(unescape(encodeURIComponent(txt))));
    const a = document.createElement('a'); a.href = URL.createObjectURL(new Blob([txt], { type: 'text/csv' })); a.download = fn; document.body.appendChild(a); a.click(); a.remove(); toast('Excel file downloaded.'); };
  function waText() { const R = sheetRows();
    return `*${SCH()}*\nStaff salary — ${ymName(YM)} (${D.workDays} working days)\n\n` + R.map(r => `${r.regNo ? r.regNo + '. ' : ''}${r.staff.name}: ${rs(r.calc.net)}${r.calc.absent ? ` (absent ${dd(r.calc.absent)}${r.calc.deduction ? ', cut ' + rs(r.calc.deduction) : ''})` : ''}`).join('\n')
      + `\n\n*Total to pay: ${rs(R.reduce((a, r) => a + r.calc.net, 0))}*`; }
  window.mgSheetWa = () => { const u = 'https://wa.me/?text=' + encodeURIComponent(waText()); const w = window.open(u, '_blank'); if (!w) location.href = u; };
  window.mgSheetPic = async () => { const R = sheetRows(), W = 1100, rh = 34, H = 120 + (R.length + 2) * rh + 30, c = document.createElement('canvas'); c.width = W; c.height = H; const x = c.getContext('2d');
    const X = [14, 60, 340, 450, 520, 600, 670, 730, 820, 930]; const hd = ['Sr.', 'Name', 'Salary', 'W.D.', 'Present', 'Absent', 'CL', 'Cut ₹', 'Bonus/Adv', 'Net pay'];
    x.fillStyle = '#fff'; x.fillRect(0, 0, W, H); x.fillStyle = '#0F5132'; x.font = 'bold 24px Arial'; x.fillText(SCH(), 14, 36); x.font = '17px Arial'; x.fillStyle = '#333'; x.fillText('Staff salary — ' + ymName(YM) + ' · ' + D.workDays + ' working days', 14, 64);
    let y = 84; x.fillStyle = '#0F5132'; x.fillRect(0, y, W, rh); x.fillStyle = '#fff'; x.font = 'bold 15px Arial'; hd.forEach((h, i) => x.fillText(h, X[i], y + 22)); y += rh;
    R.forEach((r, n) => { const cc = r.calc, ba = bonAdv(cc); if (n % 2) { x.fillStyle = '#F2F7F3'; x.fillRect(0, y, W, rh); } x.font = '15px Arial'; x.fillStyle = '#111';
      [String(r.regNo || '—'), r.staff.name.slice(0, 30), rs(cc.gross), String(cc.workDays), dd(present(cc)), dd(cc.absent), dd(clUsed(cc)), cc.deduction ? '−' + rs(cc.deduction) : '—', ba ? (ba > 0 ? '+' : '−') + rs(Math.abs(ba)) : '—', rs(cc.net)].forEach((v, i) => { x.font = (i === 9 ? 'bold ' : '') + '15px Arial'; x.fillStyle = i === 7 && cc.deduction ? '#b42318' : '#111'; x.fillText(v, X[i], y + 22); }); y += rh; });
    x.fillStyle = '#E3F4EA'; x.fillRect(0, y, W, rh); x.fillStyle = '#0F5132'; x.font = 'bold 16px Arial'; x.fillText('Total (' + R.length + ')', X[1], y + 23); x.fillText(rs(R.reduce((a, r) => a + r.calc.gross, 0)), X[2], y + 23); x.fillText(rs(R.reduce((a, r) => a + r.calc.net, 0)), X[9], y + 23);
    const blob = await new Promise(r => c.toBlob(r, 'image/png')), file = new File([blob], 'Salary-' + YM + '.png', { type: 'image/png' });
    try { if (navigator.canShare && navigator.canShare({ files: [file] })) return await navigator.share({ files: [file], title: 'Salary ' + ymName(YM) }); } catch (e) { if (e && e.name === 'AbortError') return; }
    if (window.SNMSISFiles) { toast('App me picture share nahi hoti — PDF bana raha hoon.'); return mgSheetPdf(); }
    const a = document.createElement('a'); a.href = c.toDataURL('image/png'); a.download = file.name; document.body.appendChild(a); a.click(); a.remove(); toast('Picture downloaded — WhatsApp me attach kijiye.'); };
  function segs() { const R = D.rows, paid = R.filter(r => r.status === 'Paid');
    return `<div class="seg">${[['table', '📋 Month sheet'], ['all', 'All ' + R.length], ['due', 'To pay ' + R.filter(r => r.status === 'Due').length], ['paid', 'Paid ' + paid.length], ['notset', 'Salary not set ' + R.filter(r => r.status === 'Not set').length], ['att', 'Staff attendance'], ['bill', '📄 Fee demand bill']].map(([k, l]) => `<button class="${VIEW === k ? 'on' : ''}" onclick="mgView('${k}')">${l}</button>`).join('')}</div>`; }
  // Sections (owner, 13 Nov 2026: "manager app me fee receipt, expense entry, opening aur tour ka fee lene ka format"):
  // Fee Receipt = feecollect.php (the one place fees are taken), Cash Book + Tour fee = cashdesk.php — same login (tk=sal).
  const SECS = [['sal', 'Salary'], ['fee', 'Fee Receipt'], ['cash', 'Cash Book'], ['tour', 'Tour fee']];
  window.mgFeeFull = () => { store.set('mg_feefull', store.get('mg_feefull') === '1' ? '0' : '1'); draw(); };
  window.mgSec = k => { SEC = k; store.set('mg_sec', k); draw(); };
  const secBar = () => `<div class="seg" style="margin-bottom:12px">${SECS.map(([k, l]) => `<button class="${SEC === k ? 'on' : ''}" onclick="mgSec('${k}')">${l}</button>`).join('')}</div>`;
  async function draw() {
    if (!store.get(TK) || MUST) return loginView();
    if (SEC !== 'sal') {
      // Fee (13 Nov 2026, owner: "manager app se fee lena thoda smart banaiye"): Quick Fee (feequick.php) first; the full ERP form stays one tap away.
      const full = SEC === 'fee' && store.get('mg_feefull') === '1';
      const src = { fee: full ? 'feecollect.php?embed=1&tk=sal' : 'feequick.php?embed=1&tk=sal', cash: 'cashdesk.php?embed=1&tk=sal&tab=day', tour: 'cashdesk.php?embed=1&tk=sal&tab=tour' }[SEC];
      return frame(`<iframe src="${src}" title="${SEC}" style="height:82vh"></iframe>${SEC === 'fee' ? `<p class="mut">${full ? 'Full Fee Receipt form (same as the ERP). Turn the phone sideways.' : 'Quick Fee — same receipts and numbers as the ERP Fee Receipt.'} <a href="#" onclick="mgFeeFull();return false">${full ? '‹ Back to Quick Fee' : 'Open the full form (discount, add fee, cancel) ›'}</a></p>` : ''}`);
    }
    if (!D) return load();
    if (VIEW === 'bill') { frame(segs() + `<div class="card"><h3 style="margin:0 0 8px;color:#0F5132">📄 Fee demand bill for parents</h3><div id="billPick"></div></div>`); return DemandBill.picker(store.get(TK), 'billPick'); }
    if (VIEW === 'att') return frame(segs() + `<iframe src="staffatt.php?embed=1&tk=sal&ym=${YM}" title="Staff attendance"></iframe><p class="mut">Attendance here drives the salary by itself.</p>`);
    const R = D.rows, set = R.filter(r => r.status !== 'Not set'), paid = R.filter(r => r.status === 'Paid'), sum = k => set.reduce((a, r) => a + (+r.calc[k] || 0), 0);
    if (VIEW === 'table') return frame(kpis(set, paid, sum) + segs() + monthTable());
    const list = VIEW === 'due' ? R.filter(r => r.status === 'Due') : VIEW === 'paid' ? paid : VIEW === 'notset' ? R.filter(r => r.status === 'Not set') : R;
    const tch = list.filter(r => r.staff.teaching), oth = list.filter(r => !r.staff.teaching);
    frame(kpis(set, paid, sum) + `
      ${segs()}
      ${R.some(r => r.status === 'Not set') ? `<div class="card" style="border:2px dashed #198754"><b>📥 Salary from the September 2026 sheet</b><p class="mut" style="margin:4px 0 8px">${R.filter(r => r.status === 'Not set').length} staff have no salary yet. Fill them from the school's September salary sheet in one tap — a salary already set is never changed.</p><button class="btn go" onclick="mgImport()">See and fill</button></div>` : ''}
      ${tch.length ? `<div class="card"><h3 style="margin:0 0 4px;color:#0F5132">Teachers (${tch.length})</h3>${tch.map(card).join('')}</div>` : ''}
      ${oth.length ? `<div class="card"><h3 style="margin:0 0 4px;color:#0F5132">Other staff (${oth.length})</h3>${oth.map(card).join('')}</div>` : ''}
      ${!list.length ? '<div class="card mut">Nobody in this list.</div>' : ''}
      <div class="acts">${D.can.staff ? '<button class="btn" onclick="mgStaff(\'\')">+ Add staff</button>' : ''}<button class="btn l" onclick="mgRegister()">Print register</button>${ROLE === 'chairman' || D.me === 'Chairman' ? '<button class="btn l" onclick="mgManager()">Manager login</button>' : ''}</div>
      <p class="mut">${D.workDays} working days in ${esc(ymName(YM))} (Sundays and holidays left out). Absence comes from Staff Attendance and approved leave by itself; per day = salary ÷ days in the month, the first absent day(s) are covered by paid leave (CL), then the more-than-4-leave rule and the full-attendance bonus apply by themselves. Only the Manager and the Chairman can open this app.</p>`);
  }
  function frame(body) {
    $('app').innerHTML = `<div class="top"><div><b>${SEC === 'sal' ? 'Salary' : esc(SECS.find(x => x[0] === SEC)[1])}</b><small>${esc(D ? D.me : 'Manager app')}${D && !D.can.edit ? ' · view only' : ''}</small></div>
      <div style="display:flex;gap:6px">${SEC === 'sal' ? `<select onchange="mgMonth(this.value)">${months().map(m => `<option value="${m}" ${m === YM ? 'selected' : ''}>${ymName(m)}</option>`).join('')}</select>` : ''}<button onclick="mgLogout()">Log out</button></div></div><div class="pg">${secBar()}${body}</div>`;
  }
  // ---------------- sheets ----------------
  const row = id => D.rows.find(r => r.staff.id === id);
  function sheet(html) { $('sh').innerHTML = `<div class="sheet" onclick="if(event.target===this)mgClose()"><div class="in">${html}</div></div>`; }
  window.mgClose = () => { $('sh').innerHTML = ''; };
  async function after(msg) { mgClose(); toast(msg); await load(); }
  window.mgStaff = id => { const s = id ? row(id).staff : { name: '', role: '', monthly: null, paidLeave: 1, active: true, teaching: false };
    sheet(`<h3>${id ? esc(s.name) : 'Add staff'}</h3>${s.teaching ? `<p class="mut">${esc(s.role)} · teacher</p>` : `<div class="f"><label>Name</label><input id="sN" value="${esc(s.name)}" maxlength="60"></div><div class="f"><label>Job (Driver, Dai, Watchman, Office…)</label><input id="sR" value="${esc(s.role)}" maxlength="40"></div>`}
      <div class="two"><div class="f"><label>Monthly salary (₹)</label><input id="sM" type="number" min="0" value="${s.monthly ?? ''}" placeholder="not set"></div><div class="f"><label>Paid leave a month</label><input id="sL" type="number" min="0" max="10" step="0.5" value="${s.paidLeave ?? 1}"></div></div>
      ${id ? `<div class="f"><label>Working from (month joined — salary and attendance start here; blank = always)</label><input id="sF" type="month" value="${esc((s.from || '').slice(0, 7))}"></div><div class="f"><label><input type="checkbox" id="sA" ${s.active ? 'checked' : ''} style="width:auto"> Working here (untick when the person leaves)</label></div>` : ''}
      <button class="btn go full f" onclick="mgStaffSave('${id}')">Save</button><button class="btn l full f" onclick="mgClose()">Cancel</button>`); };
  window.mgStaffSave = async id => { const g = x => $(x) ? $(x).value.trim() : null;
    try { await sal('staff', { id, name: g('sN'), role: g('sR'), monthly: g('sM') === '' ? null : +g('sM'), paidLeave: +g('sL'), active: $('sA') ? $('sA').checked : true, ...($('sF') ? { from: $('sF').value } : {}) }); after('Saved.'); } catch (e) { toast(e.message); } };
  window.mgAdj = id => { const r = row(id), c = r.calc;
    sheet(`<h3>Adjust · ${esc(r.staff.name)}</h3><p class="mut">${esc(ymName(YM))} · ${esc(why(c))}</p>
      <div class="two"><div class="f"><label>Absent days (blank = from attendance)</label><input id="aA" type="number" step="0.5" min="0" value="${c.manual ? c.absent : ''}" placeholder="auto ${dd(c.auto ? c.auto.total : 0)}"></div><div class="f"><label>Advance to recover (₹)</label><input id="aV" type="number" min="0" value="${c.advance || ''}"></div></div>
      <div class="two"><div class="f"><label>Bonus / other (₹)</label><input id="aB" type="number" min="0" value="${(c.bonus - (c.autoBonus || 0)) || ''}"></div><div class="f"><label>Note</label><input id="aN" maxlength="200" value="${esc(c.note || '')}"></div></div>
      ${c.autoBonus ? `<p class="mut">Full-attendance bonus ${rs(c.autoBonus)} is added by itself.</p>` : ''}
      <button class="btn go full f" onclick="mgAdjSave('${id}')">Save</button><button class="btn l full f" onclick="mgClose()">Cancel</button>`); };
  window.mgAdjSave = async id => { try { await sal('adjust', { ym: YM, id, manualAbsent: $('aA').value.trim(), advance: $('aV').value.trim(), bonus: $('aB').value.trim(), note: $('aN').value.trim() }); after('Saved.'); } catch (e) { toast(e.message); } };
  window.mgPay = id => { const r = row(id);
    sheet(`<h3>Pay ${esc(r.staff.name)}</h3><p style="font-size:22px;margin:4px 0"><b>${rs(r.calc.net)}</b></p><p class="mut">${esc(ymName(YM))} · ${esc(why(r.calc))}</p>
      <div class="two"><div class="f"><label>Paid by</label><select id="pM">${D.modes.map(m => `<option>${esc(m)}</option>`).join('')}</select></div><div class="f"><label>Paid on</label><input id="pD" type="date" value="${D.today}" max="${D.today}"></div></div>
      <div class="f"><label>Ref. / cheque / UPI no. (optional)</label><input id="pR" maxlength="60"></div>
      <button class="btn go full f" onclick="mgPaySave('${id}')">Mark paid</button><button class="btn l full f" onclick="mgClose()">Cancel</button>`); };
  window.mgPaySave = async id => { try { await sal('pay', { ym: YM, id, mode: $('pM').value, ref: $('pR').value.trim(), date: $('pD').value }); await after('Marked paid.'); mgSlip(id); } catch (e) { toast(e.message); } };
  // September 2026 Excel salaries (salary_api.php importplan / import) — preview first, then only "not set" staff get it.
  window.mgImport = async () => { let P; try { P = await sal('importplan'); } catch (e) { return toast(e.message); }
    const lab = { set: ['Will be filled', '#198754'], same: ['Already same', '#6c757d'], keep: ['Already set — not changed', '#b45309'], notfound: ['Not found in ERP — set by hand', '#dc3545'], many: ['More than one match — set by hand', '#dc3545'] };
    const n = P.rows.filter(r => r.action === 'set').length;
    sheet(`<h3>Salary from the September 2026 sheet</h3><p class="mut">Name in the sheet → name in the ERP. Only green rows are saved.</p>
      <div style="max-height:55vh;overflow:auto"><table style="width:100%;border-collapse:collapse;font-size:13px">${P.rows.map(r => `<tr style="border-bottom:1px solid #e5e7eb"><td style="padding:6px 4px">${esc(r.sheet)}${r.erpName ? `<br><small class="mut">ERP: ${esc(r.erpName)}</small>` : ''}</td><td style="padding:6px 4px;text-align:right"><b>${rs(r.monthly)}</b>${r.action === 'keep' ? `<br><small class="mut">now ${rs(r.current)}</small>` : ''}</td><td style="padding:6px 4px;color:${lab[r.action][1]};font-size:12px">${lab[r.action][0]}</td></tr>`).join('')}</table></div>
      ${P.can && n ? `<button class="btn go full f" onclick="mgImportSave()">Fill ${n} salar${n === 1 ? 'y' : 'ies'}</button>` : `<p class="mut">${P.can ? 'Nothing to fill.' : 'Only the Manager can fill salaries.'}</p>`}<button class="btn l full f" onclick="mgClose()">Close</button>`); };
  window.mgImportSave = async () => { try { const r = await sal('import', {}); after(r.count + ' salaries filled. Check the names marked red by hand.'); } catch (e) { toast(e.message); } };
  window.mgUnpay = async id => { const reason = prompt('Why reopen this paid salary? (kept in the record)'); if (!reason) return;
    try { await sal('unpay', { ym: YM, id, reason }); after('Reopened.'); } catch (e) { toast(e.message); } };
  function slipHTML(r) { const c = r.calc, p = r.pay || {}, rowH = (a, b) => `<tr><td style="padding:6px 8px;border-bottom:1px solid #e5e7eb">${a}</td><td style="padding:6px 8px;border-bottom:1px solid #e5e7eb;text-align:right">${b}</td></tr>`;
    return `<div style="border:1.5px solid #0F5132;border-radius:14px;padding:16px;margin-bottom:16px;page-break-inside:avoid;font-family:Arial,sans-serif"><div style="display:flex;justify-content:space-between"><b style="font-size:16px">${esc(D.school || 'SN Mishra Singapore International School')}, Bhadohi</b><span>SALARY SLIP — ${esc(ymName(YM))}</span></div>
      <p style="margin:8px 0"><b>${esc(r.staff.name)}</b> · ${esc(r.staff.role)}</p><table style="width:100%;border-collapse:collapse;font-size:13px">${rowH('Monthly salary', rs(c.gross))}${rowH('Working days', c.workDays)}${rowH('Present days', dd(present(c)))}${rowH('Absent days', dd(c.absent))}${rowH('CL (paid leave) used', dd(clUsed(c)) + ' of ' + dd(c.paidLeave))}
      ${rowH('Cut for absence', '− ' + rs(c.deduction))}${c.advance ? rowH('Advance recovered', '− ' + rs(c.advance)) : ''}${c.autoBonus ? rowH('Full attendance bonus', '+ ' + rs(c.autoBonus)) : ''}${c.bonus - (c.autoBonus || 0) ? rowH('Bonus / other', '+ ' + rs(c.bonus - (c.autoBonus || 0))) : ''}
      <tr><td style="padding:8px"><b>Net paid</b></td><td style="padding:8px;text-align:right"><b style="font-size:16px">${rs(c.net)}</b></td></tr></table>
      ${p.paidOn ? `<p style="font-size:12px">Paid on ${esc(dmy(p.paidOn))} by ${esc(p.mode)}${p.ref ? ' (' + esc(p.ref) + ')' : ''}</p>` : ''}<div style="display:flex;justify-content:space-between;margin-top:26px;font-size:12px"><span>Received by ______________</span><span>Manager ______________</span></div></div>`; }
  function printHTML(title, body) { if (window.SNMSISFiles && typeof SNMSISFiles.printHtml === 'function') { SNMSISFiles.printHtml(title, `<!doctype html><html><head><meta charset="utf-8"><title>${esc(title)}</title></head><body style="margin:20px">${body}</body></html>`); return toast('Printer me "Save as PDF" chuniye, phir PDF share kijiye.'); } const w = window.open('', '_blank'); if (!w) return toast(/; wv\)/.test(navigator.userAgent) ? 'Printing works from a computer or the phone browser: maakit.in/school/manager.php' : 'Allow pop-ups to print.'); w.document.write(`<!doctype html><html><head><meta charset="utf-8"><title>${esc(title)}</title></head><body style="margin:20px">${body}<script>window.onload=()=>window.print()<\/script></body></html>`); w.document.close(); }
  window.mgSlip = id => { const r = row(id); if (r) printHTML('Salary slip', slipHTML(r)); };
  window.mgRegister = () => mgSheetPdf();   // Print register = the same A4 salary sheet (17 Nov 2026)
  // Chairman: Manager's name and PIN
  window.mgManager = () => sheet(`<h3>Manager login</h3><p class="mut">Only you (Chairman) can set the Manager's name and reset the Manager's PIN.</p>
      <div class="f"><label>Manager's name</label><input id="mN" maxlength="40" value="${esc(NAMES.manager && NAMES.manager !== 'Manager' ? NAMES.manager : '')}"></div><button class="btn full f" onclick="mgManagerName()">Save name</button>
      <div class="f"><label>New PIN for the Manager (type 1234 to reset — the Manager then chooses their own)</label><input id="mP" type="password" inputmode="numeric" maxlength="8"></div><button class="btn go full f" onclick="mgManagerPin()">Set Manager PIN</button>
      <button class="btn l full f" onclick="mgClose()">Close</button>`);
  window.mgManagerName = async () => { try { await sal('managername', { name: $('mN').value.trim() }); NAMES.manager = $('mN').value.trim() || 'Manager'; toast('Saved.'); } catch (e) { toast(e.message); } };
  window.mgManagerPin = async () => { const p = $('mP').value.trim(); if (!/^\d{4,8}$/.test(p)) return toast('PIN must be 4–8 digits.');
    try { await api('api.php?a=setpin', { uid: 'manager', pin: p }); toast(p === '1234' ? 'Reset to 1234 — the Manager chooses a new PIN at login.' : 'Manager PIN set.'); mgClose(); } catch (e) { toast(e.message); } };
  draw();
})();
