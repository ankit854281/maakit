/* Cash Book (cashdesk.php) — owner, 13 Nov 2026: "manager app me fee receipt, expense entry, opening aur tour ka fee
   lene ka format". Tabs: Day (opening cash → every entry of the day → closing; add expense / income / opening balance /
   bank deposit; cash count) · Month (day by day) · Tour fee (receipt per child, class-wise paid / left).
   Data: cashdesk_api.php — the same money as the account reports. ?tk=sal = inside the Manager app (its own login). */
(function () {
  const $ = id => document.getElementById(id), Q = new URLSearchParams(location.search);
  const TOK = () => localStorage.getItem(Q.get('tk') === 'sal' ? 'sal_token' : 'sch_token') || '';
  if (Q.get('embed') === '1') document.body.classList.add('emb');
  const esc = s => String(s == null ? '' : s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
  const rs = n => '₹' + (Math.round((+n || 0) * 100) / 100).toLocaleString('en-IN', { maximumFractionDigits: 2 });
  const pad = n => String(n).padStart(2, '0'), iso = d => d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()), today = iso(new Date());
  const dmy = d => d ? d.split('-').reverse().join('-') : '', ymName = ym => new Date(ym + '-01T00:00:00').toLocaleDateString('en-IN', { month: 'long', year: 'numeric' });
  const toast = t => { const e = $('toast'); e.textContent = t; e.style.display = 'block'; clearTimeout(toast.t); toast.t = setTimeout(() => e.style.display = 'none', 3800); };
  const KIND = { fee: 'Fee receipt', tour: 'Tour fee', expense: 'Expense', income: 'Income', opening: 'Opening balance', deposit: 'Bank deposit', withdraw: 'Self withdrawal' };
  let TAB = ['day', 'month', 'tour'].includes(Q.get('tab')) ? Q.get('tab') : 'day', DATE = today, YM = today.slice(0, 7), D = null, M = null, T = null, TID = '', TCLS = '', TQ = '', TST = '';
  async function api(a, body, q) {
    const r = await fetch('cashdesk_api.php?a=' + a + (q || ''), { method: body ? 'POST' : 'GET', cache: 'no-store', headers: { 'Content-Type': 'application/json', 'X-School-Token': TOK() }, ...(body ? { body: JSON.stringify(body) } : {}) });
    let j = null; try { j = await r.json(); } catch (e) {}
    if (!r.ok || !j || !j.ok) throw Error((j && j.error) || (r.status === 401 ? 'Please log in again.' : 'Server problem. Please try again.'));
    return j;
  }
  const sheet = h => { $('sh').innerHTML = `<div class="sheet" onclick="if(event.target===this)cdClose()"><div class="in">${h}</div></div>`; };
  window.cdClose = () => { $('sh').innerHTML = ''; };
  // inside the Manager app the Tour fee has its own section, so the embedded day book shows only Day / Month
  const EMB_TOUR = Q.get('embed') === '1' && Q.get('tab') === 'tour', EMB_DAY = Q.get('embed') === '1' && !EMB_TOUR;
  const tabs = () => EMB_TOUR ? '' : `<div class="tabs">${[['day', 'Day book'], ['month', 'Month'], ['tour', 'Tour fee']].filter(x => !(EMB_DAY && x[0] === 'tour')).map(([k, l]) => `<button class="${TAB === k ? 'on' : ''}" onclick="cdTab('${k}')">${l}</button>`).join('')}</div>`;
  window.cdTab = k => { TAB = k; draw(); };
  const frame = h => { $('app').innerHTML = tabs() + h; };
  async function draw() {
    if (TAB === 'month') return monthView();
    if (TAB === 'tour') return tourView();
    return dayView();
  }
  // ---------------- Day book ----------------
  async function dayView() {
    if (!D || D.date !== DATE) { frame('<div class="card mut">Opening…</div>'); try { D = await api('day', null, '&date=' + DATE); } catch (e) { return frame(`<div class="card">${esc(e.message)}</div>`); } }
    const can = D.can, k = D.byKind;
    frame(`<div class="card row"><input type="date" value="${DATE}" max="${today}" onchange="cdDate(this.value)" style="max-width:200px"><span class="mut">${esc(new Date(DATE + 'T00:00:00').toLocaleDateString('en-IN', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }))}</span></div>
      <div class="kp"><div class="k"><span>Opening cash</span><b>${rs(D.opening)}</b><small>start of the day</small></div><div class="k g"><span>Money in</span><b>${rs(D.in)}</b><small>fee ${rs(k.fee)} · tour ${rs(k.tour)}</small></div>
        <div class="k r"><span>Money out</span><b>${rs(D.out)}</b><small>expense ${rs(k.expense)} · bank ${rs(k.deposit)}</small></div><div class="k y"><span>Closing cash</span><b>${rs(D.closing)}</b><small>cash in hand (book)</small></div></div>
      ${can ? `<div class="card"><div class="acts"><button class="btn r" onclick="cdEntry('expense')">− Expense</button><button class="btn" onclick="cdEntry('income')">+ Other income</button><button class="btn l" onclick="cdEntry('deposit')">Bank deposit</button><button class="btn l" onclick="cdEntry('withdraw')">Cash from bank</button><button class="btn l" onclick="cdEntry('opening')">Opening balance</button><button class="btn go" onclick="cdCount()">Closing — count cash</button></div>
        <p class="mut" style="margin:8px 0 0">Fee receipts come here by themselves from Fee Receipt, tour fee from the Tour fee tab. Online fee (UPI, cheque) also counts in cash until a Bank deposit entry moves it out — the same way as the old ERP.</p></div>` : ''}
      ${D.verify.length ? `<div class="card"><h3>Cash counted</h3>${D.verify.map(v => `<p style="margin:4px 0">${esc(v.by)}: counted <b>${rs(v.counted)}</b>, book ${rs(v.book)} — ${v.counted === v.book ? '<b style="color:#14532d">matches ✓</b>' : `<b style="color:#b42318">difference ${rs(v.counted - v.book)}</b>`}${v.remarks ? ' · ' + esc(v.remarks) : ''}</p>`).join('')}</div>` : ''}
      <div class="card"><div class="row"><h3>Entries (${D.rows.length})</h3><span class="acts"><button class="btn l s" onclick="cdShare()">WhatsApp</button><button class="btn l s" onclick="cdPrint()">Print</button></span></div>
        ${D.rows.length ? `<div style="overflow-x:auto"><table class="t"><tr><th>Details</th><th>Mode</th><th class="n">In</th><th class="n">Out</th><th></th></tr>
        ${D.rows.map((r, i) => `<tr><td><span class="tg ${r.kind}">${KIND[r.kind] || r.kind}</span> <span class="mut">${esc(r.vno)}</span><br><b>${esc(r.head === 'Fee' ? r.name : r.head)}</b>${r.head !== 'Fee' && r.name ? '<br><span class="mut">' + esc(r.name) + '</span>' : ''}${r.remark ? '<br><span class="mut">' + esc(r.remark) + '</span>' : ''}<br><span class="mut">by ${esc(r.user)}</span></td>
          <td>${esc(r.mode || 'Cash')}${r.ref && r.ref !== 'NA' ? '<br><span class="mut">' + esc(r.ref) + '</span>' : ''}</td><td class="n">${r.in ? rs(r.in) : ''}</td><td class="n">${r.out ? rs(r.out) : ''}</td>
          <td>${can && r.id && r.kind !== 'tour' ? `<button class="btn r s" onclick="cdDel(${i})">Delete</button>` : ''}</td></tr>`).join('')}
        <tr class="tot"><td colspan="2">Total</td><td class="n">${rs(D.in)}</td><td class="n">${rs(D.out)}</td><td></td></tr></table></div>` : '<p class="mut">No entry on this day.</p>'}</div>`);
  }
  window.cdDate = v => { if (!v || v > today) return toast('Choose a past date.'); DATE = v; draw(); };
  window.cdEntry = t => {
    const T = { expense: 'Expense (money out)', income: 'Other income (money in)', deposit: 'Cash deposited in the bank', withdraw: 'Cash taken out of the bank', opening: 'Opening balance — cash in hand when this book starts' }[t];
    sheet(`<h3>${T}</h3>${t === 'opening' ? '<p class="mut">Enter this only once: the cash in hand on the first day you start using this book.</p>' : ''}
      <div class="two"><div class="f"><label>Date</label><input id="eD" type="date" value="${DATE}" max="${today}"></div><div class="f"><label>Amount (₹)</label><input id="eA" type="number" inputmode="decimal" min="1" step="0.01"></div></div>
      ${t === 'expense' ? `<div class="f"><label>Spent on</label><select id="eH">${D.heads.map(h => `<option>${esc(h)}</option>`).join('')}</select></div>` : t === 'income' ? '<div class="f"><label>Income from (e.g. Book sale, Dress sale, Donation)</label><input id="eH" maxlength="60"></div>' : ''}
      ${['expense', 'income'].includes(t) ? `<div class="f"><label>${t === 'expense' ? 'Paid to (shop / person)' : 'Received from'}</label><input id="eP" maxlength="80"></div>
        <div class="two"><div class="f"><label>Mode</label><select id="eM">${D.modes.map(m => `<option>${m}</option>`).join('')}</select></div><div class="f"><label>Bill / UTR / cheque no.</label><input id="eR" maxlength="40"></div></div>` : t !== 'opening' ? '<div class="f"><label>Bank / slip no.</label><input id="eR" maxlength="40"></div>' : ''}
      <div class="f"><label>Note</label><input id="eN" maxlength="300"></div>
      <button class="btn go full f" onclick="cdSave('${t}')">Save</button>`);
  };
  window.cdSave = async t => { const v = id => $(id) ? $(id).value.trim() : '';
    try { const r = await api('entry', { type: t, date: v('eD'), amount: v('eA'), head: v('eH'), party: v('eP'), mode: v('eM') || 'Cash', ref: v('eR'), narration: v('eN') }); cdClose(); toast('Saved — ' + r.no); DATE = v('eD') || DATE; D = null; M = null; draw(); }
    catch (e) { toast(e.message); } };
  window.cdDel = async i => { const r = D.rows[i], why = prompt('Delete ' + r.vno + ' (' + r.head + ', ' + rs(r.in || r.out) + ')?\nWrite why:'); if (!why) return;
    try { await api('del', { id: r.id, reason: why }); toast('Deleted.'); D = null; M = null; draw(); } catch (e) { toast(e.message); } };
  window.cdCount = () => sheet(`<h3>Closing — count the cash</h3><p class="mut">Book says <b>${rs(D.closing)}</b> should be in hand at the end of ${dmy(DATE)}. Count the notes and write what is really there.</p>
      <div class="f"><label>Cash counted (₹)</label><input id="cC" type="number" inputmode="decimal" min="0" step="0.01"></div><div class="f"><label>Remarks</label><input id="cR" maxlength="200"></div>
      <button class="btn go full f" onclick="cdCountSave()">Save count</button>`);
  window.cdCountSave = async () => { try { const r = await api('verify', { date: DATE, counted: $('cC').value, remarks: $('cR').value }); cdClose(); toast(r.diff ? 'Saved — difference ' + rs(r.diff) : 'Saved — cash matches ✓'); D = null; draw(); } catch (e) { toast(e.message); } };
  const dayText = () => `*Cash Book — ${dmy(DATE)}*\n${D.school}\n\nOpening cash: ${rs(D.opening)}\n` + Object.entries(D.byKind).map(([k, v]) => `${KIND[k] || k}: ${rs(v)}`).join('\n') + `\nMoney in: ${rs(D.in)}\nMoney out: ${rs(D.out)}\n*Closing cash: ${rs(D.closing)}*\n— ${D.me}`;
  window.cdShare = () => { const a = document.createElement('a'); a.href = 'https://wa.me/?text=' + encodeURIComponent(dayText()); a.target = '_blank'; a.click(); };
  function printHTML(title, body) {
    const w = window.open('', '_blank'); if (!w) return toast(/; wv\)/.test(navigator.userAgent) ? 'Printing works from a computer or the phone browser — use WhatsApp here.' : 'Allow pop-ups to print.');
    w.document.write(`<!doctype html><html><head><meta charset="utf-8"><title>${esc(title)}</title><style>body{font-family:Arial,sans-serif;font-size:13px;margin:18px;color:#111}table{width:100%;border-collapse:collapse}th,td{border:1px solid #999;padding:5px 6px;text-align:left}td.n,th.n{text-align:right}h2,h3{margin:0}.c{text-align:center}.rc{border:1px solid #333;padding:14px;max-width:520px;margin:0 auto 18px}</style></head><body>${body}<script>window.onload=()=>window.print()<\/script></body></html>`); w.document.close();
  }
  window.cdPrint = () => printHTML('Cash Book ' + dmy(DATE), `<h2 class="c">${esc(D.school)}</h2><h3 class="c">Cash Book — ${dmy(DATE)}</h3><p>Opening cash: <b>${rs(D.opening)}</b></p>
    <table><tr><th>Type</th><th>No.</th><th>Details</th><th>Mode</th><th class="n">In</th><th class="n">Out</th></tr>${D.rows.map(r => `<tr><td>${KIND[r.kind] || r.kind}</td><td>${esc(r.vno)}</td><td>${esc(r.head === 'Fee' ? r.name : r.head + (r.name ? ' — ' + r.name : ''))}</td><td>${esc(r.mode || 'Cash')}</td><td class="n">${r.in ? rs(r.in) : ''}</td><td class="n">${r.out ? rs(r.out) : ''}</td></tr>`).join('')}
    <tr><th colspan="4">Total</th><th class="n">${rs(D.in)}</th><th class="n">${rs(D.out)}</th></tr></table><p>Closing cash: <b>${rs(D.closing)}</b></p><p style="margin-top:40px">Prepared by: ${esc(D.me)} &nbsp; &nbsp; &nbsp; Checked by: ____________</p>`);
  // ---------------- Month ----------------
  async function monthView() {
    if (!M || M.ym !== YM) { frame('<div class="card mut">Opening…</div>'); try { M = await api('month', null, '&ym=' + YM); } catch (e) { return frame(`<div class="card">${esc(e.message)}</div>`); } }
    const tin = M.days.reduce((a, d) => a + d.in, 0), tout = M.days.reduce((a, d) => a + d.out, 0), last = M.days[0];
    const ms = []; for (let i = 0; i < 12; i++) { const d = new Date(); d.setDate(1); d.setMonth(d.getMonth() - i); ms.push(iso(d).slice(0, 7)); }
    frame(`<div class="card row"><select onchange="cdYm(this.value)" style="max-width:220px">${ms.map(m => `<option value="${m}" ${m === YM ? 'selected' : ''}>${ymName(m)}</option>`).join('')}</select></div>
      <div class="kp"><div class="k"><span>Opening (1st)</span><b>${rs(M.opening)}</b></div><div class="k g"><span>Money in</span><b>${rs(tin)}</b></div><div class="k r"><span>Money out</span><b>${rs(tout)}</b></div><div class="k y"><span>Closing</span><b>${rs(last ? last.closing : M.opening)}</b></div></div>
      <div class="card"><div style="overflow-x:auto"><table class="t"><tr><th>Date</th><th class="n">Opening</th><th class="n">In</th><th class="n">Out</th><th class="n">Closing</th></tr>
      ${M.days.map(d => `<tr onclick="cdDate('${d.date}');cdTab('day')" style="cursor:pointer"><td>${dmy(d.date)}${d.entries ? '' : ' <span class="mut">· no entry</span>'}</td><td class="n">${rs(d.opening)}</td><td class="n">${d.in ? rs(d.in) : ''}</td><td class="n">${d.out ? rs(d.out) : ''}</td><td class="n"><b>${rs(d.closing)}</b></td></tr>`).join('')}</table></div>
      <p class="mut" style="margin:8px 0 0">Tap a day to see its entries.</p></div>`);
  }
  window.cdYm = v => { YM = v; draw(); };
  // ---------------- Tour fee ----------------
  async function tourView() {
    if (!T) { frame('<div class="card mut">Opening…</div>'); try { T = await api('tours'); } catch (e) { return frame(`<div class="card">${esc(e.message)}</div>`); } }
    if (!T.tours.length) return frame(`<div class="card"><h3>Tour fee</h3><p class="mut">No tour set up yet for session ${esc(T.session)}.</p>${T.can ? '<button class="btn go" onclick="cdTour(\'\')">+ Set up a tour</button>' : ''}</div>`);
    if (!T.tours.some(t => t.id === TID)) TID = T.tours[0].id;
    const t = T.tours.find(x => x.id === TID), paid = t.kids.filter(s => s.pay && s.pay.status === 'Paid'), coll = paid.reduce((a, s) => a + s.pay.amount, 0);
    const cls = t.classes.length ? t.classes : [...new Set(t.kids.map(s => s.cls))];
    const isPaid = s => s.pay && s.pay.status === 'Paid', later = t.kids.filter(s => s.book && !isPaid(s));
    const st = s => isPaid(s) ? 'paid' : s.book ? 'later' : 'no';
    const L = t.kids.filter(s => (!TCLS || s.cls === TCLS) && (!TST || st(s) === TST) && (!TQ || (s.name + ' ' + s.father).toLowerCase().includes(TQ.toLowerCase())));
    frame(`<div class="card"><div class="row"><select onchange="cdTid(this.value)" style="max-width:320px">${T.tours.map(x => `<option value="${x.id}" ${x.id === TID ? 'selected' : ''}>${esc(x.name)} · ${dmy(x.date)}</option>`).join('')}</select>
        ${T.can ? `<span class="acts"><button class="btn l s" onclick="cdTour('${t.id}')">Edit tour</button><button class="btn l s" onclick="cdTour('')">+ New tour</button></span>` : ''}</div>
        <p class="mut" style="margin:6px 0 0">${rs(t.amount)} per child · Classes ${esc(cls.join(', '))}</p></div>
      <div class="kp"><div class="k"><span>Children</span><b>${t.kids.length}</b></div><div class="k g"><span>Paid</span><b>${paid.length}</b><small>${rs(coll)}</small></div><div class="k r"><span>Going · pay later</span><b>${later.length}</b><small>${rs(later.length * t.amount)} due</small></div><div class="k y"><span>Total going</span><b>${paid.length + later.length}</b><small>${rs(coll)} collected</small></div></div>
      <div class="card"><div style="overflow-x:auto"><table class="t"><tr><th>Class</th><th class="n">Children</th><th class="n">Paid</th><th class="n">Left</th><th class="n">₹</th></tr>
        ${cls.map(c => { const K = t.kids.filter(s => s.cls === c), P = K.filter(s => s.pay && s.pay.status === 'Paid'); return `<tr onclick="cdTc('${esc(c)}')" style="cursor:pointer"><td><b>${esc(c)}</b></td><td class="n">${K.length}</td><td class="n">${P.length}</td><td class="n" style="${K.length - P.length ? 'color:#b42318;font-weight:bold' : ''}">${K.length - P.length}</td><td class="n">${rs(P.reduce((a, s) => a + s.pay.amount, 0))}</td></tr>`; }).join('')}</table></div></div>
      <div class="card"><div class="row"><select onchange="cdTc(this.value)" style="max-width:160px"><option value="">All classes</option>${cls.map(c => `<option ${c === TCLS ? 'selected' : ''}>${esc(c)}</option>`).join('')}</select><select onchange="cdTst(this.value)" style="max-width:200px"><option value="">Everyone</option><option value="later" ${TST === 'later' ? 'selected' : ''}>Going · pay later</option><option value="paid" ${TST === 'paid' ? 'selected' : ''}>Paid</option><option value="no" ${TST === 'no' ? 'selected' : ''}>Not going yet</option></select><input placeholder="Search child or father" value="${esc(TQ)}" oninput="cdTq(this.value)" style="max-width:260px">${later.length ? '<button class="btn l s" onclick="cdLater()">🖨 Pay-later list</button>' : ''}</div>
        ${L.map(s => { const p = s.pay, ok = p && p.status === 'Paid', i = t.kids.indexOf(s);
          return `<div class="kid"><div><b>${esc(s.name)}</b><small>${esc(s.cls)}${s.father ? ' · ' + esc(s.father) : ''}${p && !ok ? ` · <span class="cnl">receipt ${esc(p.no)} cancelled: ${esc(p.reason)}</span>` : ''}</small></div>
            ${ok ? `<div class="paid">✓ ${esc(p.no)} · ${rs(p.amount)} ${esc(p.mode)}<br>${dmy(p.date)} · ${esc(p.by)}<div class="acts" style="justify-content:flex-end;margin-top:4px"><button class="btn l s" onclick="cdRc(${i})">Receipt</button><button class="btn l s" onclick="cdRcWa(${i})">WhatsApp</button>${T.can ? `<button class="btn r s" onclick="cdTcancel(${i})">Cancel</button>` : ''}</div></div>`
              : s.book ? `<div class="paid" style="color:#9a6700">✔ Going · fee ${rs(t.amount)} later<br>${esc(s.book.by)}${T.can ? `<div class="acts" style="justify-content:flex-end;margin-top:4px"><button class="btn go s" onclick="cdTpay(${i})">Take ${rs(t.amount)}</button><button class="btn l s" onclick="cdTbook(${i},0)">Remove</button></div>` : ''}</div>`
              : T.can ? `<span class="acts"><button class="btn l s" onclick="cdTbook(${i},1)">✔ Going · pay later</button><button class="btn go s" onclick="cdTpay(${i})">Take ${rs(t.amount)}</button></span>` : '<span class="mut">Not going yet</span>'}</div>`; }).join('') || '<p class="mut">No child found.</p>'}</div>`);
  }
  window.cdTid = v => { TID = v; TCLS = ''; draw(); };
  window.cdTc = v => { TCLS = v; draw(); };
  window.cdTst = v => { TST = v; draw(); };
  window.cdTbook = async (i, on) => { const s = T.tours.find(x => x.id === TID).kids[i]; if (!on && !confirm('Remove ' + s.name + ' from the tour list?')) return;
    try { await api('tourbook', { tour: TID, sid: s.id, on: !!on }); toast(on ? s.name + ' — name written, fee later.' : 'Removed.'); T = null; draw(); } catch (e) { toast(e.message); } };
  window.cdLater = () => { const t = T.tours.find(x => x.id === TID), L = t.kids.filter(s => s.book && !(s.pay && s.pay.status === 'Paid')).sort((a, b) => a.cls.localeCompare(b.cls, 'en', { numeric: true }) || a.name.localeCompare(b.name));
    printHTML('Pay-later list — ' + t.name, `<h2 class="c">${esc(T.school)}</h2><h3 class="c">${esc(t.name)} — ${dmy(t.date)}<br><small>Going, fee not paid yet: ${L.length} children · ${rs(L.length * t.amount)} due</small></h3>
      <table><tr><th>#</th><th>Student</th><th>Class</th><th>Father</th><th>Phone</th><th>Fee due</th><th>Paid / Sign</th></tr>${L.map((s, n) => `<tr><td>${n + 1}</td><td>${esc(s.name)}</td><td>${esc(s.cls)}</td><td>${esc(s.father || '')}</td><td>${esc(s.phone || '')}</td><td>${rs(t.amount)}</td><td></td></tr>`).join('')}</table>`); };
  window.cdTq = v => { TQ = v; clearTimeout(cdTq.t); cdTq.t = setTimeout(() => { draw(); const e = document.querySelector('input[placeholder^="Search"]'); if (e) { e.focus(); e.setSelectionRange(v.length, v.length); } }, 350); };
  window.cdTour = id => { const t = T.tours.find(x => x.id === id) || { name: id ? '' : "Children's Day Prayagraj Tour", date: '', amount: '', classes: [] };
    sheet(`<h3>${id ? 'Edit tour' : 'New tour'}</h3><div class="f"><label>Tour name</label><input id="tN" maxlength="80" value="${esc(t.name)}"></div>
      <div class="two"><div class="f"><label>Tour date</label><input id="tD" type="date" value="${esc(t.date)}"></div><div class="f"><label>Fee per child (₹)</label><input id="tA" type="number" inputmode="decimal" min="1" value="${t.amount || ''}"></div></div>
      <div class="f"><label>Classes going</label><div class="chips">${T.classes.map(c => `<label><input type="checkbox" class="tC" value="${esc(c)}" ${t.classes.includes(c) ? 'checked' : ''}>${esc(c)}</label>`).join('')}</div></div>
      <button class="btn go full f" onclick="cdTourSave('${id}')">Save tour</button>`); };
  window.cdTourSave = async id => { try { const r = await api('tour', { id, name: $('tN').value, date: $('tD').value, amount: $('tA').value, classes: [...document.querySelectorAll('.tC:checked')].map(x => x.value) }); cdClose(); toast('Tour saved.'); TID = r.id; T = null; draw(); } catch (e) { toast(e.message); } };
  window.cdTpay = i => { const t = T.tours.find(x => x.id === TID), s = t.kids[i];
    sheet(`<h3>Tour fee — ${esc(s.name)}</h3><p class="mut">${esc(s.cls)} · ${esc(t.name)}</p>
      <div class="two"><div class="f"><label>Amount (₹)</label><input id="pA" type="number" inputmode="decimal" value="${t.amount}"></div><div class="f"><label>Date</label><input id="pD" type="date" value="${today}" max="${today}"></div></div>
      <div class="two"><div class="f"><label>Mode</label><select id="pM">${T.modes.map(m => `<option>${m}</option>`).join('')}</select></div><div class="f"><label>UPI / cheque no. (if not cash)</label><input id="pR" maxlength="40"></div></div>
      <button class="btn go full f" onclick="cdTpaySave(${i})">Save and make receipt</button>`); };
  window.cdTpaySave = async i => { const s = T.tours.find(x => x.id === TID).kids[i];
    try { const r = await api('tourpay', { tour: TID, sid: s.id, amount: $('pA').value, date: $('pD').value, mode: $('pM').value, ref: $('pR').value }); cdClose(); toast('Receipt ' + r.no + ' made.'); T = null; D = null; await draw();
      const k = T.tours.find(x => x.id === TID).kids.findIndex(x => x.id === s.id); if (k >= 0) cdRcWa(k, true); } catch (e) { toast(e.message); } };
  window.cdTcancel = async i => { const s = T.tours.find(x => x.id === TID).kids[i], why = prompt('Cancel receipt ' + s.pay.no + ' of ' + s.name + '?\nWrite why:'); if (!why) return;
    try { await api('tourcancel', { tour: TID, sid: s.id, reason: why }); toast('Receipt cancelled.'); T = null; D = null; draw(); } catch (e) { toast(e.message); } };
  const rcText = (t, s) => `*${T.school}*\nTour Fee Receipt ${s.pay.no}\nDate: ${dmy(s.pay.date)}\n\nStudent: ${s.name} (${s.cls})\nTour: ${t.name} — ${dmy(t.date)}\nAmount received: ${rs(s.pay.amount)} (${s.pay.mode}${s.pay.ref ? ' ' + s.pay.ref : ''})\nReceived by: ${s.pay.by}\n\nThank you.`;
  window.cdRcWa = (i, ask) => { const t = T.tours.find(x => x.id === TID), s = t.kids[i]; if (ask && !confirm('Receipt made. Send it to the parent on WhatsApp?')) return;
    const ph = String(s.phone || '').replace(/\D/g, '').slice(-10), a = document.createElement('a'); a.href = 'https://wa.me/' + (ph.length === 10 ? '91' + ph : '') + '?text=' + encodeURIComponent(rcText(t, s)); a.target = '_blank'; a.click(); };
  window.cdRc = i => { const t = T.tours.find(x => x.id === TID), s = t.kids[i], p = s.pay, one = copy => `<div class="rc"><h2 class="c">${esc(T.school)}</h2><h3 class="c">Tour Fee Receipt <small>(${copy})</small></h3><br>
      <table><tr><th>Receipt No.</th><td>${esc(p.no)}</td><th>Date</th><td>${dmy(p.date)}</td></tr><tr><th>Student</th><td colspan="3">${esc(s.name)} — Class ${esc(s.cls)}</td></tr>${s.father ? `<tr><th>Father</th><td colspan="3">${esc(s.father)}</td></tr>` : ''}
      <tr><th>Tour</th><td colspan="3">${esc(t.name)} — ${dmy(t.date)}</td></tr><tr><th>Amount</th><td><b>${rs(p.amount)}</b></td><th>Mode</th><td>${esc(p.mode)}${p.ref ? ' · ' + esc(p.ref) : ''}</td></tr></table>
      <p style="margin-top:30px">Received by: ${esc(p.by)}<span style="float:right">Signature: ____________</span></p></div>`;
    printHTML('Tour receipt ' + p.no, one('Parent copy') + one('Office copy')); };
  draw();
})();
