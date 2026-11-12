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
  let YM = ymOf(now), D = null, VIEW = 'all', MUST = false, ROLE = store.get('sal_role') || 'manager', NAMES = {};
  if ('serviceWorker' in navigator) navigator.serviceWorker.register('manager-sw.js').catch(() => {});
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
        <p class="mut f">Manager: first PIN 1234, then choose your own. Only the Chairman can reset the Manager's PIN.</p>`}</div>`;
  }
  window.ROLESET = v => { ROLE = v; store.set('sal_role', v); };
  window.mgLogin = async () => { const pin = $('lP').value.trim();
    try { const r = await api('api.php?a=login', { role: ROLE, pin }); store.set(TK, r.token); window._old = pin; MUST = !!r.mustChange; draw(); } catch (e) { toast(e.message); } };
  window.mgNewPin = async () => { const n = $('nP').value.trim(); if (!/^\d{4,8}$/.test(n) || n === '1234') return toast('PIN must be 4–8 digits and not 1234.');
    try { await api('api.php?a=setpin', { old: window._old || '1234', pin: n }); MUST = false; toast('PIN saved.'); draw(); } catch (e) { toast(e.message); } };
  window.mgLogout = async () => { try { await api('api.php?a=logout', {}); } catch (e) {} store.set(TK, null); D = null; draw(); };
  // ---------------- main ----------------
  function months() { const o = []; for (let i = -6; i <= 1; i++) o.push(ymOf(new Date(now.getFullYear(), now.getMonth() + i, 1))); return o.reverse(); }
  async function load() { $('app').innerHTML = '<div class="pg"><div class="card mut">Opening salary…</div></div>'; try { D = await sal('sheet', null, '&ym=' + YM); } catch (e) { $('app').innerHTML = `<div class="pg"><div class="card">${esc(e.message)}</div></div>`; return; } draw(); }
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
  function card(r) {
    const s = r.staff, c = r.calc, st = r.status === 'Not set' ? 'Not' : r.status, can = D.can.edit, id = s.id;
    return `<div class="st"><div class="h"><div><b>${esc(s.name)}<span class="tag ${st}">${r.status === 'Not set' ? 'Salary not set' : esc(r.status)}</span></b><small>${esc(s.role)}${s.teaching ? '' : ' · other staff'}</small></div>
      <div class="net"><small class="mut">Net pay</small><b>${r.status === 'Not set' ? '—' : rs(c.net)}</b></div></div>
      ${r.status === 'Not set' ? '' : `<div class="pills"><span class="pl">Salary ${rs(c.gross)}</span><span class="pl">${esc(why(c))}</span><span class="pl">Paid leave ${dd(c.paidLeave)}</span>
        ${c.deduction ? `<span class="pl r">Cut ${rs(c.deduction)} (${dd(c.unpaid)} day${c.unpaid == 1 ? '' : 's'})</span>` : ''}${c.workedOnly ? '<span class="pl r">More than 4 leave — paid for days worked</span>' : ''}
        ${c.advance ? `<span class="pl r">Advance −${rs(c.advance)}</span>` : ''}${c.autoBonus ? `<span class="pl g">Full attendance +${rs(c.autoBonus)}</span>` : ''}${c.bonus - (c.autoBonus || 0) ? `<span class="pl g">Bonus +${rs(c.bonus - (c.autoBonus || 0))}</span>` : ''}${c.note ? `<span class="pl y">${esc(c.note)}</span>` : ''}</div>`}
      ${r.pay ? `<p class="mut" style="margin:6px 0 0">Paid ${esc(dmy(r.pay.paidOn))} by ${esc(r.pay.mode)}${r.pay.ref ? ' (' + esc(r.pay.ref) + ')' : ''} · ${esc(r.pay.by || '')}</p>` : ''}
      <div class="acts">${r.status === 'Paid' ? `<button class="btn l" onclick="mgSlip('${id}')">Salary slip</button>${can ? `<button class="btn l" onclick="mgUnpay('${id}')">Reopen</button>` : ''}` : ''}
        ${can && r.status === 'Due' ? `<button class="btn go" onclick="mgPay('${id}')">Pay ${rs(c.net)}</button><button class="btn l" onclick="mgAdj('${id}')">Adjust</button>` : ''}
        ${can && r.status !== 'Paid' ? `<button class="btn l" onclick="mgStaff('${id}')">${r.status === 'Not set' ? 'Set salary' : 'Edit salary'}</button>` : ''}</div></div>`;
  }
  async function draw() {
    if (!store.get(TK) || MUST) return loginView();
    if (!D) return load();
    if (VIEW === 'att') return frame(`<iframe src="staffatt.php?embed=1&tk=sal" title="Staff attendance"></iframe><p class="mut">Attendance here drives the salary by itself.</p>`);
    const R = D.rows, set = R.filter(r => r.status !== 'Not set'), paid = R.filter(r => r.status === 'Paid'), sum = k => set.reduce((a, r) => a + (+r.calc[k] || 0), 0);
    const list = VIEW === 'due' ? R.filter(r => r.status === 'Due') : VIEW === 'paid' ? paid : VIEW === 'notset' ? R.filter(r => r.status === 'Not set') : R;
    const tch = list.filter(r => r.staff.teaching), oth = list.filter(r => !r.staff.teaching);
    frame(`<div class="kp"><div class="k"><span>Total salary</span><b>${rs(sum('gross'))}</b><small>${set.length} staff with salary set</small></div>
        <div class="k r"><span>Cut for absence</span><b>${rs(sum('deduction'))}</b><small>advance ${rs(sum('advance'))}</small></div>
        <div class="k g"><span>Net to pay</span><b>${rs(sum('net'))}</b><small>bonus ${rs(sum('bonus'))}</small></div>
        <div class="k y"><span>Paid</span><b>${paid.length} / ${set.length}</b><small>${rs(paid.reduce((a, r) => a + r.calc.net, 0))} paid</small></div></div>
      <div class="seg">${[['all', 'All ' + R.length], ['due', 'To pay ' + R.filter(r => r.status === 'Due').length], ['paid', 'Paid ' + paid.length], ['notset', 'Salary not set ' + R.filter(r => r.status === 'Not set').length], ['att', 'Staff attendance']].map(([k, l]) => `<button class="${VIEW === k ? 'on' : ''}" onclick="mgView('${k}')">${l}</button>`).join('')}</div>
      ${tch.length ? `<div class="card"><h3 style="margin:0 0 4px;color:#0F5132">Teachers (${tch.length})</h3>${tch.map(card).join('')}</div>` : ''}
      ${oth.length ? `<div class="card"><h3 style="margin:0 0 4px;color:#0F5132">Other staff (${oth.length})</h3>${oth.map(card).join('')}</div>` : ''}
      ${!list.length ? '<div class="card mut">Nobody in this list.</div>' : ''}
      <div class="acts">${D.can.staff ? '<button class="btn" onclick="mgStaff(\'\')">+ Add staff</button>' : ''}<button class="btn l" onclick="mgRegister()">Print register</button>${ROLE === 'chairman' || D.me === 'Chairman' ? '<button class="btn l" onclick="mgManager()">Manager login</button>' : ''}</div>
      <p class="mut">${D.workDays} working days in ${esc(ymName(YM))} (Sundays and holidays left out). Absence comes from Staff Attendance and approved leave by itself; staff rules (paid leave, no-permission cut, more-than-4-leave rule, full-attendance bonus) are applied by themselves. Only the Manager and the Chairman can open this app.</p>`);
  }
  function frame(body) {
    $('app').innerHTML = `<div class="top"><div><b>Salary</b><small>${esc(D ? D.me : '')}${D && !D.can.edit ? ' · view only' : ''}</small></div>
      <div style="display:flex;gap:6px"><select onchange="mgMonth(this.value)">${months().map(m => `<option value="${m}" ${m === YM ? 'selected' : ''}>${ymName(m)}</option>`).join('')}</select><button onclick="mgLogout()">Log out</button></div></div><div class="pg">${body}</div>`;
  }
  // ---------------- sheets ----------------
  const row = id => D.rows.find(r => r.staff.id === id);
  function sheet(html) { $('sh').innerHTML = `<div class="sheet" onclick="if(event.target===this)mgClose()"><div class="in">${html}</div></div>`; }
  window.mgClose = () => { $('sh').innerHTML = ''; };
  async function after(msg) { mgClose(); toast(msg); await load(); }
  window.mgStaff = id => { const s = id ? row(id).staff : { name: '', role: '', monthly: null, paidLeave: 1, active: true, teaching: false };
    sheet(`<h3>${id ? esc(s.name) : 'Add staff'}</h3>${s.teaching ? `<p class="mut">${esc(s.role)} · teacher</p>` : `<div class="f"><label>Name</label><input id="sN" value="${esc(s.name)}" maxlength="60"></div><div class="f"><label>Job (Driver, Dai, Watchman, Office…)</label><input id="sR" value="${esc(s.role)}" maxlength="40"></div>`}
      <div class="two"><div class="f"><label>Monthly salary (₹)</label><input id="sM" type="number" min="0" value="${s.monthly ?? ''}" placeholder="not set"></div><div class="f"><label>Paid leave a month</label><input id="sL" type="number" min="0" max="10" step="0.5" value="${s.paidLeave ?? 1}"></div></div>
      ${id ? `<div class="f"><label><input type="checkbox" id="sA" ${s.active ? 'checked' : ''} style="width:auto"> Working here (untick when the person leaves)</label></div>` : ''}
      <button class="btn go full f" onclick="mgStaffSave('${id}')">Save</button><button class="btn l full f" onclick="mgClose()">Cancel</button>`); };
  window.mgStaffSave = async id => { const g = x => $(x) ? $(x).value.trim() : null;
    try { await sal('staff', { id, name: g('sN'), role: g('sR'), monthly: g('sM') === '' ? null : +g('sM'), paidLeave: +g('sL'), active: $('sA') ? $('sA').checked : true }); after('Saved.'); } catch (e) { toast(e.message); } };
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
  window.mgUnpay = async id => { const reason = prompt('Why reopen this paid salary? (kept in the record)'); if (!reason) return;
    try { await sal('unpay', { ym: YM, id, reason }); after('Reopened.'); } catch (e) { toast(e.message); } };
  function slipHTML(r) { const c = r.calc, p = r.pay || {}, rowH = (a, b) => `<tr><td style="padding:6px 8px;border-bottom:1px solid #e5e7eb">${a}</td><td style="padding:6px 8px;border-bottom:1px solid #e5e7eb;text-align:right">${b}</td></tr>`;
    return `<div style="border:1.5px solid #0F5132;border-radius:14px;padding:16px;margin-bottom:16px;page-break-inside:avoid;font-family:Arial,sans-serif"><div style="display:flex;justify-content:space-between"><b style="font-size:16px">${esc(D.school || 'SN Mishra Singapore International School')}, Bhadohi</b><span>SALARY SLIP — ${esc(ymName(YM))}</span></div>
      <p style="margin:8px 0"><b>${esc(r.staff.name)}</b> · ${esc(r.staff.role)}</p><table style="width:100%;border-collapse:collapse;font-size:13px">${rowH('Monthly salary', rs(c.gross))}${rowH('Working days', c.workDays)}${rowH('Absent days', dd(c.absent))}${rowH('Paid leave allowed', dd(c.paidLeave))}
      ${rowH('Cut for absence', '− ' + rs(c.deduction))}${c.advance ? rowH('Advance recovered', '− ' + rs(c.advance)) : ''}${c.autoBonus ? rowH('Full attendance bonus', '+ ' + rs(c.autoBonus)) : ''}${c.bonus - (c.autoBonus || 0) ? rowH('Bonus / other', '+ ' + rs(c.bonus - (c.autoBonus || 0))) : ''}
      <tr><td style="padding:8px"><b>Net paid</b></td><td style="padding:8px;text-align:right"><b style="font-size:16px">${rs(c.net)}</b></td></tr></table>
      ${p.paidOn ? `<p style="font-size:12px">Paid on ${esc(dmy(p.paidOn))} by ${esc(p.mode)}${p.ref ? ' (' + esc(p.ref) + ')' : ''}</p>` : ''}<div style="display:flex;justify-content:space-between;margin-top:26px;font-size:12px"><span>Received by ______________</span><span>Manager ______________</span></div></div>`; }
  function printHTML(title, body) { const w = window.open('', '_blank'); if (!w) return toast('Allow pop-ups to print.'); w.document.write(`<!doctype html><html><head><meta charset="utf-8"><title>${esc(title)}</title></head><body style="margin:20px">${body}<script>window.onload=()=>window.print()<\/script></body></html>`); w.document.close(); }
  window.mgSlip = id => { const r = row(id); if (r) printHTML('Salary slip', slipHTML(r)); };
  window.mgRegister = () => { const R = D.rows.filter(r => r.status !== 'Not set'), td = 'style="border:1px solid #999;padding:5px;font-size:12px"';
    printHTML('Salary register', `<h2 style="font-family:Arial;margin:0">${esc(D.school || 'SN Mishra Singapore International School')} — Salary register ${esc(ymName(YM))}</h2><table style="border-collapse:collapse;width:100%;font-family:Arial;margin-top:10px"><tr>${['Name', 'Job', 'Salary', 'Absent', 'Cut', 'Advance', 'Bonus', 'Net', 'Status', 'Paid on', 'Sign'].map(h => `<th ${td}>${h}</th>`).join('')}</tr>
      ${R.map(r => `<tr><td ${td}>${esc(r.staff.name)}</td><td ${td}>${esc(r.staff.role)}</td><td ${td}>${rs(r.calc.gross)}</td><td ${td}>${dd(r.calc.absent)}</td><td ${td}>${rs(r.calc.deduction)}</td><td ${td}>${rs(r.calc.advance)}</td><td ${td}>${rs(r.calc.bonus)}</td><td ${td}><b>${rs(r.calc.net)}</b></td><td ${td}>${esc(r.status)}</td><td ${td}>${esc(r.pay ? dmy(r.pay.paidOn) : '')}</td><td ${td} style="width:90px"></td></tr>`).join('')}
      <tr><td ${td} colspan="7"><b>Total</b></td><td ${td}><b>${rs(R.reduce((a, r) => a + r.calc.net, 0))}</b></td><td ${td} colspan="3"></td></tr></table><div style="display:flex;justify-content:space-between;margin-top:30px;font-family:Arial;font-size:12px"><span>Manager ______________</span><span>Chairman ______________</span></div>`); };
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
