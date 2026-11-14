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
  // Print / Save as PDF. In the Android Salary app (v1.1+) the native bridge opens Android's print screen, where
  // "Save as PDF" is one of the printers (owner, 13 Nov 2026: "save as pdf ka option nahi de raha").
  function printHTML(title, body) {
    const doc = `<!doctype html><html><head><meta charset="utf-8"><title>${esc(title)}</title><style>body{font-family:Arial,sans-serif;font-size:13px;margin:18px;color:#111}table{width:100%;border-collapse:collapse}th,td{border:1px solid #999;padding:5px 6px;text-align:left}td.n,th.n{text-align:right}h2,h3{margin:0}.c{text-align:center}.rc{border:1px solid #333;padding:14px;max-width:520px;margin:0 auto 18px}</style></head><body>${body}</body></html>`;
    if (window.SNMSISFiles && typeof SNMSISFiles.printHtml === 'function') { SNMSISFiles.printHtml(title, doc); toast('Choose "Save as PDF" as the printer, then share the PDF.'); return; }
    const w = window.open('', '_blank'); if (!w) return toast(/; wv\)/.test(navigator.userAgent) ? 'Update the Salary app (new version) to print or save PDF — or open this page in Chrome.' : 'Allow pop-ups to print.');
    w.document.write(doc.replace('</body>', '<script>window.onload=()=>window.print()<\/script></body>')); w.document.close();
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
    const isPaid = s => s.pay && s.pay.status === 'Paid', isJP = s => !!(s.book && s.book.jp) && !isPaid(s), later = t.kids.filter(s => s.book && !isPaid(s) && !isJP(s)), jpk = t.kids.filter(isJP);
    const st = s => isPaid(s) ? 'paid' : isJP(s) ? 'jp' : s.book ? 'later' : s.tag ? 't_' + s.tag.tag : 'no';
    const TAGL = { going: '✅ Going · pay later', no: '❌ Not going', think: '🤔 Will tell', noans: '📵 No answer' }, TAGC = { going: '#fef3c7', no: '#fee4e2', think: '#e0f2fe', noans: '#eef2f0' };
    const tagChip = s => s.tag ? ` · <span style="background:${TAGC[s.tag.tag] || '#eee'};border-radius:99px;padding:1px 7px;font-size:11px;font-weight:700">${TAGL[s.tag.tag] || esc(s.tag.tag)}</span>${s.tag.note ? ' ' + esc(s.tag.note) : ''} <span class="mut">(${esc(s.tag.by)})</span>` : '';
    const L = t.kids.filter(s => (!TCLS || s.cls === TCLS) && (!TST || st(s) === TST) && (!TQ || (s.name + ' ' + s.father).toLowerCase().includes(TQ.toLowerCase())));
    frame(`<div class="card"><div class="row"><select onchange="cdTid(this.value)" style="max-width:320px">${T.tours.map(x => `<option value="${x.id}" ${x.id === TID ? 'selected' : ''}>${esc(x.name)} · ${dmy(x.date)}</option>`).join('')}</select>
        ${T.can ? `<span class="acts"><button class="btn l s" onclick="cdTour('${t.id}')">Edit tour</button><button class="btn l s" onclick="cdTour('')">+ New tour</button></span>` : ''}</div>
        <p class="mut" style="margin:6px 0 0">${rs(t.amount)} per child · Classes ${esc(cls.join(', '))}</p></div>
      <div class="kp"><div class="k"><span>Children</span><b>${t.kids.length}</b></div><div class="k g"><span>Paid</span><b>${paid.length}</b><small>${rs(coll)}</small></div><div class="k r"><span>Going · pay later</span><b>${later.length}</b><small>${rs(later.length * t.amount)} due</small></div><div class="k y"><span>Total going</span><b>${paid.length + later.length + jpk.length}</b><small>${jpk.length ? '🎟 JP Sir ' + jpk.length + ' · ' : ''}${rs(coll)} collected</small></div></div>
      <div class="card"><h3 style="margin:0 0 6px">Class-wise — who is going</h3><div style="overflow-x:auto"><table class="t"><tr><th>Class</th><th class="n">Going</th><th class="n">Paid</th><th class="n">Pay later</th><th class="n">🎟 JP Sir</th><th class="n">₹ collected</th><th class="n">₹ due</th></tr>
        ${cls.map(c => { const K = t.kids.filter(s => s.cls === c), P = K.filter(isPaid), J = K.filter(isJP), B = K.filter(s => s.book && !isPaid(s) && !isJP(s)); return `<tr onclick="cdTc('${esc(c)}')" style="cursor:pointer"><td><b>${esc(c)}</b> <small class="mut">of ${K.length}</small></td><td class="n"><b>${P.length + B.length + J.length}</b></td><td class="n" style="color:#14532d">${P.length}</td><td class="n" style="${B.length ? 'color:#9a6700;font-weight:bold' : ''}">${B.length}</td><td class="n" style="color:#5b21b6">${J.length || ''}</td><td class="n">${rs(P.reduce((a, s) => a + s.pay.amount, 0))}</td><td class="n">${B.length ? rs(B.length * t.amount) : ''}</td></tr>`; }).join('')}
        <tr><td><b>Total</b></td><td class="n"><b>${paid.length + later.length + jpk.length}</b></td><td class="n"><b>${paid.length}</b></td><td class="n"><b>${later.length}</b></td><td class="n"><b>${jpk.length}</b></td><td class="n"><b>${rs(coll)}</b></td><td class="n"><b>${rs(later.length * t.amount)}</b></td></tr></table></div>
        <div class="acts" style="margin-top:8px"><button class="btn go s" onclick="cdSimple('cls')">📄 Class-wise list (Paid · Pending · JP Sir)</button><button class="btn go s" onclick="cdSimple('all')">📄 All classes together</button><button class="btn l s" onclick="cdGoing()">📋 Full going list (class-wise)</button><button class="btn l s" onclick="cdGoingWa(0)">📲 Counts on WhatsApp</button><button class="btn s" onclick="cdAtt('')">🙋 Tour attendance</button></div></div>
      <div class="card"><div class="row"><select onchange="cdTc(this.value)" style="max-width:160px"><option value="">All classes</option>${cls.map(c => `<option ${c === TCLS ? 'selected' : ''}>${esc(c)}</option>`).join('')}</select><select onchange="cdTst(this.value)" style="max-width:200px"><option value="">Everyone</option><option value="later" ${TST === 'later' ? 'selected' : ''}>Going · pay later</option><option value="paid" ${TST === 'paid' ? 'selected' : ''}>Paid</option><option value="jp" ${TST === 'jp' ? 'selected' : ''}>🎟 JP Sir (no fee)</option><option value="no" ${TST === 'no' ? 'selected' : ''}>Not called / no tag</option><option value="t_think" ${TST === 't_think' ? 'selected' : ''}>🤔 Will tell</option><option value="t_noans" ${TST === 't_noans' ? 'selected' : ''}>📵 No answer</option><option value="t_no" ${TST === 't_no' ? 'selected' : ''}>❌ Not going</option></select><input placeholder="Search child or father" value="${esc(TQ)}" oninput="cdTq(this.value)" style="max-width:260px">${later.length ? '<button class="btn l s" onclick="cdLater()">🖨 Pay-later list</button>' : ''}</div>
        ${L.map(s => { const p = s.pay, ok = p && p.status === 'Paid', i = t.kids.indexOf(s);
          return `<div class="kid"><div><b>${esc(s.name)}</b><small>${esc(s.cls)}${s.father ? ' · ' + esc(s.father) : ''}${!ok && !isJP(s) ? tagChip(s) : ''}${p && !ok ? ` · <span class="cnl">receipt ${esc(p.no)} cancelled: ${esc(p.reason)}</span>` : ''}</small>${phOf(s).length === 10 ? `<div class="acts" style="margin-top:4px"><a class="btn l s" href="tel:+91${phOf(s)}">📞 Call</a>${s.book && !ok && !isJP(s) ? `<a class="btn l s" target="_blank" rel="noopener" href="https://wa.me/91${phOf(s)}?text=${encodeURIComponent(remTxt(t, s))}">💬 Fee reminder</a>` : ''}</div>` : ''}</div>
            ${ok ? `<div class="paid">✓ ${esc(p.no)} · ${rs(p.amount)} ${esc(p.mode)}${s.book && s.book.jp ? ' · <b style="color:#5b21b6">🎟 JP Sir</b>' : ''}<br>${dmy(p.date)} · ${esc(p.by)}<div class="acts" style="justify-content:flex-end;margin-top:4px">${T.can ? (s.book && s.book.jp ? `<button class="btn l s" onclick="cdTbook(${i},0)">Remove JP Sir</button>` : `<button class="btn l s" onclick="cdTbook(${i},1,1)">🎟 JP Sir</button>`) : ''}<button class="btn l s" onclick="cdRc(${i})">Receipt</button><button class="btn l s" onclick="cdRcWa(${i})">WhatsApp</button>${T.can ? `<button class="btn r s" onclick="cdTcancel(${i})">Cancel</button>` : ''}</div></div>`
              : isJP(s) ? `<div class="paid" style="color:#5b21b6">🎟 Going · JP Sir (no fee)<br>${esc(s.book.by)}${T.can ? `<div class="acts" style="justify-content:flex-end;margin-top:4px"><button class="btn l s" onclick="cdTbook(${i},1)">Pay later instead</button><button class="btn l s" onclick="cdTbook(${i},0)">Remove</button></div>` : ''}</div>`
              : s.book ? `<div class="paid" style="color:#9a6700">✔ Going · fee ${rs(t.amount)} later<br>${esc(s.book.by)}${T.can ? `<div class="acts" style="justify-content:flex-end;margin-top:4px"><button class="btn go s" onclick="cdTpay(${i})">Take ${rs(t.amount)}</button><button class="btn l s" onclick="cdTbook(${i},1,1)">🎟 JP Sir</button><button class="btn l s" onclick="cdTbook(${i},0)">Remove</button></div>` : ''}</div>`
              : T.can ? `<span class="acts"><button class="btn l s" onclick="cdTbook(${i},1)">✔ Going · pay later</button><button class="btn l s" onclick="cdTbook(${i},1,1)">🎟 JP Sir</button><button class="btn go s" onclick="cdTpay(${i})">Take ${rs(t.amount)}</button></span>` : '<span class="mut">Not going yet</span>'}</div>`; }).join('') || '<p class="mut">No child found.</p>'}</div>`);
  }
  window.cdTid = v => { TID = v; TCLS = ''; draw(); };
  window.cdTc = v => { TCLS = v; draw(); };
  window.cdTst = v => { TST = v; draw(); };
  window.cdTbook = async (i, on, jp) => { const s = T.tours.find(x => x.id === TID).kids[i]; const pd = s.pay && s.pay.status === 'Paid'; if (!on && !pd && !confirm('Remove ' + s.name + ' from the tour list?')) return;
    try { await api('tourbook', { tour: TID, sid: s.id, on: !!on, jp: !!jp }); toast(!on ? (pd ? 'JP Sir tag removed.' : 'Removed.') : jp && pd ? s.name + ' — JP Sir tag added (receipt stays).' : jp ? s.name + ' — going as per JP Sir, no fee.' : s.name + ' — name written, fee later.'); T = null; draw(); } catch (e) { toast(e.message); } };
  const goingOf = t => t.kids.filter(s => (s.pay && s.pay.status === 'Paid') || s.book).sort((a, b) => a.cls.localeCompare(b.cls, 'en', { numeric: true }) || a.name.localeCompare(b.name));
  const tCls = t => t.classes.length ? t.classes : [...new Set(t.kids.map(s => s.cls))];
  // Going list (13 Nov 2026, owner: "proper achhe se") — opens inside the app (the Android app cannot print), with WhatsApp + Print.
  const pdOf = s => s.pay && s.pay.status === 'Paid', jpOf = s => !!(s.book && s.book.jp) && !pdOf(s), ltOf = s => !pdOf(s) && !jpOf(s);
  // JP Sir (owner, 13 Nov 2026: "kuchh bachche JP Sir ke according jaate hain jo payment nahi dete") — going, no fee, never "pending".
  const goingSum = t => { const G = goingOf(t), P = G.filter(pdOf), J = G.filter(jpOf), B = G.filter(ltOf);
    return { G, P, J, B, coll: P.reduce((a, s) => a + s.pay.amount, 0), due: B.length * t.amount,
      rows: tCls(t).map(c => { const K = G.filter(s => s.cls === c); return { c, all: t.kids.filter(s => s.cls === c).length, K, p: K.filter(pdOf).length, j: K.filter(jpOf).length, b: K.filter(ltOf).length }; }) }; };
  const jpTxt = r => r.j ? ', JP Sir ' + r.j : '';
  const jpName = s => pdOf(s) && s.book && s.book.jp ? ' (JP Sir)' : '';
  // Call + fee from the going lists (owner, 14 Nov 2026: "tour me attended ya jo bachhe add huye hain unse fee ka aur calling ka option do").
  const phOf = s => String(s.phone || '').replace(/\D/g, '').slice(-10);
  const remTxt = (t, s) => `Namaste, ${T.school} se.\n${s.name} (${s.cls}) ka naam *${t.name}* (${dmy(t.date)}) me likha hai.\nTour fee ${rs(t.amount)} abhi baaki hai — kripya jaldi school me jama karein.\nDhanyavaad.`;
  const tActs = (t, s, ctr) => { const ph = phOf(s), i = t.kids.indexOf(s), due = ltOf(s) && !pdOf(s);
    return `<div class="acts" style="margin-top:4px${ctr ? ';justify-content:flex-end' : ''}">${ph.length === 10 ? `<a class="btn l s" href="tel:+91${ph}">📞 Call</a>${due ? `<a class="btn l s" target="_blank" rel="noopener" href="https://wa.me/91${ph}?text=${encodeURIComponent(remTxt(t, s))}">💬 Fee reminder</a>` : ''}` : '<small class="mut">No phone</small>'}${due && T.can ? `<button class="btn go s" onclick="cdTpay(${i})">💰 Take ${rs(t.amount)}</button>` : ''}</div>`; };
  window.cdGoing = () => { const t = T.tours.find(x => x.id === TID), S = goingSum(t);
    const tag = s => pdOf(s) ? `<span style="background:#dcfce7;color:#14532d;border-radius:99px;padding:2px 8px;font-size:12px;font-weight:700;white-space:nowrap">✓ Paid ${rs(s.pay.amount)}</span>` : jpOf(s) ? `<span style="background:#ede9fe;color:#5b21b6;border-radius:99px;padding:2px 8px;font-size:12px;font-weight:700;white-space:nowrap">🎟 JP Sir</span>` : `<span style="background:#fef3c7;color:#92400e;border-radius:99px;padding:2px 8px;font-size:12px;font-weight:700;white-space:nowrap">⏳ Later ${rs(t.amount)}</span>`;
    sheet(`<h3>🚌 ${esc(t.name)}</h3><p class="mut" style="margin:0 0 10px">${dmy(t.date)} · ${rs(t.amount)} per child</p>
      <div class="kp"><div class="k"><span>Going</span><b>${S.G.length}</b></div><div class="k g"><span>Paid</span><b>${S.P.length}</b><small>${rs(S.coll)}</small></div><div class="k r"><span>Pay later</span><b>${S.B.length}</b><small>${rs(S.due)} due</small></div>${S.J.length ? `<div class="k"><span>🎟 JP Sir</span><b>${S.J.length}</b><small>no fee</small></div>` : ''}</div>
      ${S.rows.map(r => `<div style="margin-top:12px;border:1px solid #e3ebe6;border-radius:14px;overflow:hidden"><div style="background:#0F5132;color:#fff;padding:8px 12px;display:flex;justify-content:space-between;gap:8px"><b>Class ${esc(r.c)}</b><span style="font-size:13px">${r.K.length} going · ✓${r.p} · ⏳${r.b}${r.j ? ' · 🎟' + r.j : ''}</span></div>
        ${r.K.length ? r.K.map((s, n) => `<div style="display:flex;justify-content:space-between;align-items:center;gap:8px;padding:7px 12px;border-top:1px solid #eef3f0"><div><b style="font-size:14px">${n + 1}. ${esc(s.name)}</b>${jpName(s) ? ' <small style="color:#5b21b6">🎟 JP Sir</small>' : ''}<small style="display:block;color:#6b7b73">${esc(s.father || '')}${s.phone ? ' · ' + esc(s.phone) : ''}</small></div><div style="text-align:right">${tag(s)}${tActs(t, s, 1)}</div></div>`).join('') : '<p class="mut" style="padding:8px 12px;margin:0">No child going yet.</p>'}</div>`).join('')}
      <div class="acts" style="margin-top:14px"><button class="btn go" onclick="cdGoingWa(1)">📲 Full list on WhatsApp</button><button class="btn l" onclick="cdGoingWa(0)">📲 Only counts</button><button class="btn l" onclick="cdGoingPrint()">🖨 Print</button><button class="btn l" onclick="cdClose()">Close</button></div>`); };
  window.cdGoingWa = full => { const t = T.tours.find(x => x.id === TID), S = goingSum(t);
    const body = S.rows.map(r => `*Class ${r.c}* — ${r.K.length} going (paid ${r.p}, later ${r.b}${jpTxt(r)})` + (full && r.K.length ? '\n' + r.K.map((s, n) => `${n + 1}. ${s.name}${jpName(s)} — ${pdOf(s) ? '✅ Paid' : jpOf(s) ? '🎟 JP Sir' : '⏳ Later'}`).join('\n') : '')).join(full ? '\n\n' : '\n');
    const txt = `🚌 *${t.name} — ${dmy(t.date)}*\n${T.school}\n\n${body}\n\n*Total going: ${S.G.length}*\n✅ Paid: ${S.P.length} (${rs(S.coll)})\n⏳ Pay later: ${S.B.length} (${rs(S.due)} due)${S.J.length ? `\n🎟 JP Sir (no fee): ${S.J.length}` : ''}`;
    const a = document.createElement('a'); a.href = 'https://wa.me/?text=' + encodeURIComponent(txt); a.target = '_blank'; a.click(); };
  window.cdGoingPrint = () => { const t = T.tours.find(x => x.id === TID), S = goingSum(t);
    printHTML('Going list — ' + t.name, `<style>.hd{border:2px solid #0F5132;border-radius:10px;padding:10px;text-align:center}.hd h2{color:#0F5132}.sm{display:flex;gap:8px;margin:10px 0}.sm div{flex:1;border:1px solid #bbb;border-radius:8px;padding:6px;text-align:center}.sm b{display:block;font-size:20px}h4{background:#0F5132;color:#fff;padding:6px 8px;margin:14px 0 0;-webkit-print-color-adjust:exact;print-color-adjust:exact}.ok{color:#14532d;font-weight:bold}.lt{color:#92400e;font-weight:bold}.cls{page-break-inside:avoid}</style>
      <div class="hd"><h2>${esc(T.school)}</h2><h3>${esc(t.name)} — ${dmy(t.date)}</h3><div>${rs(t.amount)} per child</div></div>
      <div class="sm"><div>Going<b>${S.G.length}</b></div><div>Paid<b>${S.P.length}</b>${rs(S.coll)}</div><div>Pay later<b>${S.B.length}</b>${rs(S.due)} due</div>${S.J.length ? `<div>JP Sir<b>${S.J.length}</b>no fee</div>` : ''}</div>
      <table><tr><th>Class</th><th class="n">Going</th><th class="n">Paid</th><th class="n">Pay later</th><th class="n">JP Sir</th></tr>${S.rows.map(r => `<tr><td>${esc(r.c)}</td><td class="n">${r.K.length}</td><td class="n">${r.p}</td><td class="n">${r.b}</td><td class="n">${r.j}</td></tr>`).join('')}<tr><th>Total</th><th class="n">${S.G.length}</th><th class="n">${S.P.length}</th><th class="n">${S.B.length}</th><th class="n">${S.J.length}</th></tr></table>
      ${S.rows.filter(r => r.K.length).map(r => `<div class="cls"><h4>Class ${esc(r.c)} — ${r.K.length} going (paid ${r.p}, later ${r.b}${jpTxt(r)})</h4><table><tr><th>#</th><th>Student</th><th>Father</th><th>Phone</th><th>Fee</th><th>Sign</th></tr>${r.K.map((s, n) => `<tr><td>${n + 1}</td><td>${esc(s.name)}${esc(jpName(s))}</td><td>${esc(s.father || '')}</td><td>${esc(s.phone || '')}</td><td>${pdOf(s) ? `<span class="ok">Paid ${rs(s.pay.amount)}</span> · ${esc(s.pay.no)}` : jpOf(s) ? '<b style="color:#5b21b6">JP Sir (no fee)</b>' : `<span class="lt">Pay later ${rs(t.amount)}</span>`}</td><td style="width:70px"></td></tr>`).join('')}</table></div>`).join('')}
      <p style="margin-top:30px">Prepared by: ${esc(T.me)}<span style="float:right">Checked by: ____________</span></p>`); };
  // Simple register (13 Nov 2026, owner: "class · student name · paid · pending" → then "sab mix list ban raha hai —
  // class ke paid, pending, JP Sir alag"): class by class, and inside each class three separate groups.
  const grpOf = s => s.book && s.book.jp ? 'jp' : pdOf(s) ? 'paid' : 'later';
  const GRP = [['paid', '✅ Paid', '#14532d', '#dcfce7'], ['later', '⏳ Pending', '#b45309', '#fef3c7'], ['jp', '🎟 JP Sir', '#5b21b6', '#ede9fe']];
  const regOf = t => { const G = goingOf(t); return { G, cls: tCls(t).map(c => { const K = G.filter(s => s.cls === c); return { c, g: Object.fromEntries(GRP.map(([k]) => [k, K.filter(s => grpOf(s) === k)])), n: K.length }; }).filter(x => x.n),
    tot: Object.fromEntries(GRP.map(([k]) => [k, G.filter(s => grpOf(s) === k)])), coll: G.filter(pdOf).reduce((a, s) => a + s.pay.amount, 0) }; };
  const amtOf = (s, t) => pdOf(s) ? rs(s.pay.amount) : grpOf(s) === 'jp' ? 'No fee' : rs(t.amount) + ' due';
  // "Sabhi class ka ek sath" (owner, 13 Nov 2026): ALL classes together — one Paid list, one Pending list, one JP Sir list (class shown per child).
  let SMODE = 'cls';
  const sortCls = (a, b) => a.cls.localeCompare(b.cls, 'en', { numeric: true }) || a.name.localeCompare(b.name);
  window.cdSimple = m => { if (m) SMODE = m; const t = T.tours.find(x => x.id === TID), R = regOf(t), td = 'padding:6px 8px;border-top:1px solid #e3ebe6';
    const modeBar = `<div class="acts" style="margin:0 0 8px"><button class="btn s ${SMODE === 'cls' ? 'go' : 'l'}" onclick="cdSimple('cls')">Class-wise</button><button class="btn s ${SMODE === 'all' ? 'go' : 'l'}" onclick="cdSimple('all')">All classes together</button></div>`;
    const totBox = `<div style="margin-top:12px;border:2px solid #0F5132;border-radius:14px;padding:10px;font-size:14px"><b>Total going: ${R.G.length}</b><br>✅ Paid: ${R.tot.paid.length}<br>⏳ Pending: ${R.tot.later.length} — ${rs(R.tot.later.length * t.amount)} due<br>🎟 JP Sir: ${R.tot.jp.length}<br>💰 Collected: ${rs(R.coll)}</div>`;
    const btns = `<div class="acts" style="margin-top:14px"><button class="btn go" onclick="cdSimpleWa()">📲 WhatsApp</button><button class="btn l" onclick="cdSimplePrint()">🖨 Print</button><button class="btn l" onclick="cdClose()">Close</button></div>`;
    if (SMODE === 'all') return sheet(`<h3>📄 ${esc(t.name)} — all classes</h3><p class="mut" style="margin:0 0 8px">${dmy(t.date)} · ${R.G.length} going</p>${modeBar}
      ${GRP.map(([k, l, fg, bg]) => { const L = R.tot[k].slice().sort(sortCls); return `<div style="margin-top:12px;border:1px solid #d6e2db;border-radius:14px;overflow:hidden"><div style="background:${bg};color:${fg};font-weight:800;padding:8px 12px">${l} — ${L.length}${k === 'later' ? ' · ' + rs(L.length * t.amount) + ' due' : k === 'paid' ? ' · ' + rs(L.reduce((a, s) => a + s.pay.amount, 0)) : ''}</div>
        ${L.length ? `<table style="width:100%;border-collapse:collapse;font-size:13px">${L.map((s, n) => `<tr><td style="${td};width:28px">${n + 1}</td><td style="${td};width:52px">${esc(s.cls)}</td><td style="${td}"><b>${esc(s.name)}</b></td><td style="${td};text-align:right;color:${fg};font-weight:700;white-space:nowrap">${amtOf(s, t)}</td></tr>`).join('')}</table>` : '<p class="mut" style="margin:6px 12px">Nobody.</p>'}</div>`; }).join('')}${totBox}${btns}`);
    sheet(`<h3>📄 ${esc(t.name)} — list</h3><p class="mut" style="margin:0 0 8px">${dmy(t.date)} · ${R.G.length} going · ✅ ${R.tot.paid.length} · ⏳ ${R.tot.later.length} · 🎟 ${R.tot.jp.length}</p>${modeBar}
      ${R.cls.map(x => `<div style="margin-top:12px;border:1px solid #d6e2db;border-radius:14px;overflow:hidden"><div style="background:#0F5132;color:#fff;padding:8px 12px;display:flex;justify-content:space-between"><b>Class ${esc(x.c)}</b><span style="font-size:13px">${x.n} going</span></div>
        ${GRP.map(([k, l, fg, bg]) => x.g[k].length ? `<div style="background:${bg};color:${fg};font-weight:800;padding:5px 12px;font-size:13px">${l} — ${x.g[k].length}${k === 'later' ? ' · ' + rs(x.g[k].length * t.amount) + ' due' : k === 'paid' ? ' · ' + rs(x.g[k].reduce((a, s) => a + s.pay.amount, 0)) : ''}</div>
          <table style="width:100%;border-collapse:collapse;font-size:13px">${x.g[k].map((s, n) => `<tr><td style="${td};width:28px">${n + 1}</td><td style="${td}"><b>${esc(s.name)}</b></td><td style="${td};text-align:right;color:${fg};font-weight:700;white-space:nowrap">${amtOf(s, t)}${k === 'later' ? tActs(t, s, 1) : ''}</td></tr>`).join('')}</table>` : '').join('')}</div>`).join('')}
      <div style="margin-top:12px;border:2px solid #0F5132;border-radius:14px;padding:10px;font-size:14px"><b>Total going: ${R.G.length}</b><br>✅ Paid: ${R.tot.paid.length}<br>⏳ Pending: ${R.tot.later.length} — ${rs(R.tot.later.length * t.amount)} due<br>🎟 JP Sir: ${R.tot.jp.length}<br>💰 Collected: ${rs(R.coll)}</div>
      <div class="acts" style="margin-top:14px"><button class="btn go" onclick="cdSimpleWa()">📲 WhatsApp</button><button class="btn l" onclick="cdSimplePrint()">🖨 Print</button><button class="btn l" onclick="cdClose()">Close</button></div>`); };
  window.cdSimpleWa = () => { const t = T.tours.find(x => x.id === TID), R = regOf(t);
    const txt = `🚌 *${t.name} — ${dmy(t.date)}*\n` + (SMODE === 'all' ? GRP.map(([k, l]) => { const L = R.tot[k].slice().sort(sortCls); return `\n*${l} (${L.length})*` + (L.length ? '\n' + L.map((s, n) => `${n + 1}. ${s.cls} · ${s.name}${pdOf(s) ? ' — ' + rs(s.pay.amount) : ''}`).join('\n') : ''); }).join('\n') : R.cls.map(x => `\n*━━ Class ${x.c} (${x.n} going) ━━*` + GRP.map(([k, l]) => x.g[k].length ? `\n_${l} (${x.g[k].length})_\n` + x.g[k].map((s, n) => `${n + 1}. ${s.name}${k === 'later' ? '' : pdOf(s) ? ' — ' + rs(s.pay.amount) : ''}`).join('\n') : '').join('')).join('\n'))
      + `\n\n*Total going: ${R.G.length}*\n✅ Paid: ${R.tot.paid.length}\n⏳ Pending: ${R.tot.later.length} — ${rs(R.tot.later.length * t.amount)} due\n🎟 JP Sir: ${R.tot.jp.length}\n💰 Collected: ${rs(R.coll)}`;
    const a = document.createElement('a'); a.href = 'https://wa.me/?text=' + encodeURIComponent(txt); a.target = '_blank'; a.click(); };
  window.cdSimplePrint = () => { const t = T.tours.find(x => x.id === TID), R = regOf(t);
    printHTML('Tour list — ' + t.name, `<style>h4{background:#0F5132;color:#fff;padding:6px 8px;margin:14px 0 0;-webkit-print-color-adjust:exact;print-color-adjust:exact}h5{margin:6px 0 2px;font-size:13px}.cls{page-break-inside:avoid}td.n{width:110px}.sm{border:2px solid #0F5132;padding:8px;margin-top:14px}</style>
      <h2 class="c">${esc(T.school)}</h2><h3 class="c">${esc(t.name)} — ${dmy(t.date)}</h3><p class="c">${rs(t.amount)} per child · ${R.G.length} going</p>
      ${SMODE === 'all' ? GRP.map(([k, l, fg]) => { const L = R.tot[k].slice().sort(sortCls); return `<div class="cls"><h4>${l} — ${L.length}${k === 'later' ? ' · ' + rs(L.length * t.amount) + ' due' : ''}</h4><table><tr><th>#</th><th>Class</th><th>Student name</th><th class="n">Fee</th></tr>${L.map((s, n) => `<tr><td style="width:30px">${n + 1}</td><td style="width:60px">${esc(s.cls)}</td><td>${esc(s.name)}</td><td class="n">${amtOf(s, t)}</td></tr>`).join('')}</table></div>`; }).join('') : R.cls.map(x => `<div class="cls"><h4>Class ${esc(x.c)} — ${x.n} going</h4>${GRP.map(([k, l, fg]) => x.g[k].length ? `<h5 style="color:${fg}">${l} — ${x.g[k].length}</h5><table>${x.g[k].map((s, n) => `<tr><td style="width:30px">${n + 1}</td><td>${esc(s.name)}</td><td class="n">${amtOf(s, t)}</td></tr>`).join('')}</table>` : '').join('')}</div>`).join('')}
      <div class="sm"><b>Total going: ${R.G.length}</b> &nbsp; · &nbsp; Paid: ${R.tot.paid.length} &nbsp; · &nbsp; Pending: ${R.tot.later.length} (${rs(R.tot.later.length * t.amount)} due) &nbsp; · &nbsp; JP Sir: ${R.tot.jp.length} &nbsp; · &nbsp; Collected: ${rs(R.coll)}</div>
      <p style="margin-top:30px">Prepared by: ${esc(T.me)}<span style="float:right">Checked by: ____________</span></p>`); };
  window.cdLater = () => { const t = T.tours.find(x => x.id === TID), L = t.kids.filter(s => s.book && !s.book.jp && !(s.pay && s.pay.status === 'Paid')).sort((a, b) => a.cls.localeCompare(b.cls, 'en', { numeric: true }) || a.name.localeCompare(b.name));
    printHTML('Pay-later list — ' + t.name, `<h2 class="c">${esc(T.school)}</h2><h3 class="c">${esc(t.name)} — ${dmy(t.date)}<br><small>Going, fee not paid yet: ${L.length} children · ${rs(L.length * t.amount)} due</small></h3>
      <table><tr><th>#</th><th>Student</th><th>Class</th><th>Father</th><th>Phone</th><th>Fee due</th><th>Paid / Sign</th></tr>${L.map((s, n) => `<tr><td>${n + 1}</td><td>${esc(s.name)}</td><td>${esc(s.cls)}</td><td>${esc(s.father || '')}</td><td>${esc(s.phone || '')}</td><td>${rs(t.amount)}</td><td></td></tr>`).join('')}</table>`); };
  // Tour attendance (owner, 14 Nov 2026: "attendance kaise lagayen" → tour ki attendance): on the tour day mark each going child ✅ / ❌.
  let ACLS = '', AST = '';
  // Owner, 14 Nov 2026: "absent present sab ek me hi dikh raha hai, aur jinko maine add kiya" → separate tabs: All / Present / Absent / Not marked / Added (name written without paying: fee later or JP Sir).
  const addOf = s => !!s.book && !(s.pay && s.pay.status === 'Paid');
  const attFil = s => !AST || (AST === 'P' ? s.att && s.att.p === 'P' : AST === 'A' ? s.att && s.att.p === 'A' : AST === 'N' ? !s.att : AST === 'ADD' ? addOf(s) : true);
  const ATTAB = { '': 'Going', P: 'Present', A: 'Absent', N: 'Not marked', ADD: 'Added' };
  window.cdAttTab = v => { AST = v; cdAtt(); };
  const attSum = G => ({ p: G.filter(s => s.att && s.att.p === 'P'), a: G.filter(s => s.att && s.att.p === 'A'), n: G.filter(s => !s.att) });
  window.cdAtt = c => { if (c !== undefined) ACLS = c; const t = T.tours.find(x => x.id === TID), G = goingOf(t), cls = tCls(t).filter(x => G.some(s => s.cls === x));
    if (ACLS && !cls.includes(ACLS)) ACLS = ''; const LC = G.filter(s => !ACLS || s.cls === ACLS), L = LC.filter(attFil), A = attSum(LC), B = attSum(L), nAdd = LC.filter(addOf).length;
    const tb = (v, cl, n) => `<button class="k ${cl}" style="border:2px solid ${AST === v ? '#0F5132' : 'transparent'};cursor:pointer;font:inherit;text-align:left" onclick="cdAttTab('${v}')"><span>${ATTAB[v]}</span><b>${n}</b></button>`;
    const pb = (s, v, l, on, off) => `<button class="btn s" style="min-width:74px;${s.att && s.att.p === v ? on : off}" onclick="cdAttSet(['${esc(s.id)}'],'${s.att && s.att.p === v ? '' : v}')">${l}</button>`;
    sheet(`<h3>🙋 Tour attendance</h3><p class="mut" style="margin:0 0 8px">${esc(t.name)} · ${dmy(t.date)}</p>
      <div class="kp">${tb('', '', LC.length)}${tb('P', 'g', A.p.length)}${tb('A', 'r', A.a.length)}${tb('N', 'y', A.n.length)}${tb('ADD', '', nAdd)}</div>
      <p class="mut" style="margin:6px 0 0;font-size:12px">Box dabaiye — sirf wahi bachche dikhenge. Added = jinka naam fee ke bina likha gaya (fee later / JP Sir).</p>
      <div class="acts" style="margin:10px 0"><button class="btn s ${!ACLS ? '' : 'l'}" onclick="cdAtt('')">All</button>${cls.map(c => `<button class="btn s ${ACLS === c ? '' : 'l'}" onclick="cdAtt('${esc(c)}')">${esc(c)}</button>`).join('')}</div>
      ${T.can && B.n.length ? `<button class="btn go full" onclick="cdAttSet(${esc(JSON.stringify(B.n.map(s => s.id)))},'P')">✅ Mark the other ${B.n.length} present${ACLS ? ' (' + esc(ACLS) + ')' : ''}</button>` : ''}
      <div style="margin-top:10px">${L.map((s, n) => `<div style="display:flex;justify-content:space-between;align-items:center;gap:8px;padding:8px 2px;border-top:1px solid #eef3f0"><div><b style="font-size:14px">${n + 1}. ${esc(s.name)}</b><small style="display:block;color:#6b7b73">${esc(s.cls)}${s.father ? ' · ' + esc(s.father) : ''}${addOf(s) ? (s.book.jp ? ' · 🎟 JP Sir' : ' · ➕ Added, fee later') : ''}${s.att ? ' · by ' + esc(s.att.by) : ''}</small></div>
        ${T.can ? `<span class="acts" style="flex-wrap:nowrap">${pb(s, 'P', '✅ Present', 'background:#14532d;color:#fff', 'background:#eef2f0;color:#14532d')}${pb(s, 'A', '❌ Absent', 'background:#b42318;color:#fff', 'background:#eef2f0;color:#b42318')}</span>` : `<b>${s.att ? (s.att.p === 'P' ? '✅ Present' : '❌ Absent') : '—'}</b>`}</div>`).join('') || `<p class="mut">${G.length ? 'Is box me koi bachcha nahi.' : 'Nobody on the going list yet.'}</p>`}</div>
      <div class="acts" style="margin-top:14px"><button class="btn go" onclick="cdAttWa()">📲 WhatsApp</button><button class="btn l" onclick="cdAttPrint()">🖨 Print${AST ? ' (' + ATTAB[AST] + ')' : ''}</button><button class="btn l" onclick="cdClose()">Close</button></div>`); };
  window.cdAttSet = async (ids, p) => { const t = T.tours.find(x => x.id === TID), y = window.scrollY, sc = document.querySelector('.sheet .in'), st = sc ? sc.scrollTop : 0;
    try { await api('touratt', { tour: TID, sids: ids, p }); t.kids.forEach(s => { if (ids.includes(s.id)) s.att = p ? { p, by: T.me } : null; }); cdAtt(); const s2 = document.querySelector('.sheet .in'); if (s2) s2.scrollTop = st; if (ids.length > 1) toast(ids.length + ' marked present.'); } catch (e) { toast(e.message); } };
  const attRows = (t, f) => tCls(t).map(c => { const K = goingOf(t).filter(s => s.cls === c && (!f || f(s))); return { c, K, ...attSum(K) }; }).filter(r => r.K.length);
  window.cdAttWa = () => { const t = T.tours.find(x => x.id === TID), A = attSum(goingOf(t));
    const txt = `🚌 *${t.name} — ${dmy(t.date)}*\n*Tour attendance*\n` + attRows(t).map(r => `\n*Class ${r.c}* — ✅ ${r.p.length} · ❌ ${r.a.length}${r.n.length ? ' · not marked ' + r.n.length : ''}` + (r.a.length ? '\nAbsent: ' + r.a.map(s => s.name).join(', ') : '')).join('\n') + `\n\n*Going: ${A.p.length + A.a.length + A.n.length}*\n✅ Present: ${A.p.length}\n❌ Absent: ${A.a.length}${A.n.length ? '\nNot marked: ' + A.n.length : ''}`;
    const a = document.createElement('a'); a.href = 'https://wa.me/?text=' + encodeURIComponent(txt); a.target = '_blank'; a.click(); };
  window.cdAttPrint = () => { const t = T.tours.find(x => x.id === TID), A = attSum(goingOf(t).filter(attFil)), mk = s => s.att ? (s.att.p === 'P' ? '<b style="color:#14532d">Present</b>' : '<b style="color:#b42318">Absent</b>') : '';
    printHTML('Tour attendance — ' + t.name, `<style>h4{background:#0F5132;color:#fff;padding:6px 8px;margin:14px 0 0;-webkit-print-color-adjust:exact;print-color-adjust:exact}.cls{page-break-inside:avoid}</style>
      <h2 class="c">${esc(T.school)}</h2><h3 class="c">${esc(t.name)} — ${dmy(t.date)}<br><small>Tour attendance${AST ? ' — ' + ATTAB[AST] + ' list' : ''} · Going ${A.p.length + A.a.length + A.n.length} · Present ${A.p.length} · Absent ${A.a.length}${A.n.length ? ' · Not marked ' + A.n.length : ''}</small></h3>
      ${attRows(t, attFil).map(r => `<div class="cls"><h4>Class ${esc(r.c)} — present ${r.p.length}, absent ${r.a.length}</h4><table><tr><th>#</th><th>Student</th><th>Father</th><th>Phone</th><th>Attendance</th><th>Sign</th></tr>${r.K.map((s, n) => `<tr><td>${n + 1}</td><td>${esc(s.name)}</td><td>${esc(s.father || '')}</td><td>${esc(s.phone || '')}</td><td>${mk(s)}</td><td style="width:70px"></td></tr>`).join('')}</table></div>`).join('')}
      <p style="margin-top:30px">Prepared by: ${esc(T.me)}<span style="float:right">Checked by: ____________</span></p>`); };
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
