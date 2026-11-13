// Quick Fee — phone-first fee collection (owner, 13 Nov 2026: "manager app se fee lena thoda smart banaiye").
// Search a child → months with what is due → tap "till which month" or type the amount (oldest dues are paid first)
// → Cash / UPI → one tap → receipt in the school series → WhatsApp to the parent. Uses feecollect_api.php only,
// so every rule of Fee Receipt (old due first, no overpay, deposit bank for non-cash, receipt number) stays the same.
(function () {
  'use strict';
  const Q = new URLSearchParams(location.search);
  const TOK = () => localStorage.getItem(Q.get('tk') === 'sal' ? 'sal_token' : 'sch_token') || '';
  const $ = id => document.getElementById(id);
  const esc = s => String(s == null ? '' : s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const rs = p => '₹' + (Math.round(p) / 100).toLocaleString('en-IN', { maximumFractionDigits: 2 });
  const MO = [4, 5, 6, 7, 8, 9, 10, 11, 12, 1, 2, 3], MN = { 4: 'Apr', 5: 'May', 6: 'Jun', 7: 'Jul', 8: 'Aug', 9: 'Sep', 10: 'Oct', 11: 'Nov', 12: 'Dec', 1: 'Jan', 2: 'Feb', 3: 'Mar' };
  const ord = m => MO.indexOf(+m);
  const isTr = n => /transport|van|bus|conveyance|vehicle/i.test(n);
  const dmy = d => d ? d.split('-').reverse().join('-') : '';
  let ST = null, SEARCH = '', CLS = '', SID = '', LD = null, UPTO = -1, AMT = null, MODE = 'Cash', BANK = '', REF = '', CHQ = '', LATE = '', REMARK = '', DONE = null, TODAY = null, BUSY = false;
  const RECENT_KEY = 'fq_recent';
  const recent = () => { try { return JSON.parse(localStorage.getItem(RECENT_KEY) || '[]'); } catch (e) { return []; } };
  const addRecent = id => { try { localStorage.setItem(RECENT_KEY, JSON.stringify([id, ...recent().filter(x => x !== id)].slice(0, 8))); } catch (e) { /* storage off */ } };
  function toast(t) { const e = $('toast'); e.textContent = t; e.style.display = 'block'; clearTimeout(toast.t); toast.t = setTimeout(() => { e.style.display = 'none'; }, 3500); }
  async function api(a, body, q) {
    const r = await fetch('feecollect_api.php?a=' + a + (q || ''), { method: body ? 'POST' : 'GET', headers: { 'Content-Type': 'application/json', 'X-School-Token': TOK() }, ...(body ? { body: JSON.stringify(body) } : {}) });
    let j = null; try { j = await r.json(); } catch (e) { throw new Error('Server did not answer. Try again.'); }
    if (!r.ok || !j.ok) throw new Error(j.error || 'Something went wrong.');
    return j;
  }
  const nonce = () => { const a = new Uint8Array(16); crypto.getRandomValues(a); return [...a].map(x => x.toString(16).padStart(2, '0')).join(''); };
  const frame = h => { $('q').innerHTML = h; };
  const sheet = h => { $('sh').innerHTML = h ? `<div class="sheet" onclick="if(event.target===this)fqClose()"><div class="in">${h}</div></div>` : ''; };
  window.fqClose = () => sheet('');

  // ---------- heads ----------
  const heads = () => LD && LD.ledger ? LD.ledger.heads.filter(h => !h.na).slice().sort((a, b) => ord(a.m) - ord(b.m) || a.i - b.i) : [];
  const dueHeads = () => heads().filter(h => h.amt - h.paid > 0);
  const nowOrd = () => ord(new Date().getMonth() + 1);
  // what this receipt pays: by "till month" (UPTO) or by a typed amount (oldest first)
  function alloc() {
    const D = dueHeads(); const out = [];
    if (AMT != null) { let left = Math.round(AMT * 100); for (const h of D) { if (left <= 0) break; const x = Math.min(h.amt - h.paid, left); out.push({ i: h.i, amt: x, h }); left -= x; } return out; }
    if (UPTO < 0) return out;
    for (const h of D) if (ord(h.m) <= UPTO) out.push({ i: h.i, amt: h.amt - h.paid, h });
    return out;
  }
  const allocTotal = () => alloc().reduce((a, x) => a + x.amt, 0);
  const lateP = () => Math.max(0, Math.round((parseFloat(LATE) || 0) * 100));

  // ---------- views ----------
  async function boot() {
    if (!TOK()) return frame('<div class="card">Please log in again.</div>');
    try { ST = await api('state'); } catch (e) { return frame(`<div class="card red">${esc(e.message)}</div>`); }
    loadToday(); draw();
  }
  async function loadToday() { try { TODAY = await api('today', null, '&ses=' + encodeURIComponent(ST.ses)); if (!SID) draw(); } catch (e) { TODAY = null; } }
  function draw() {
    if (!ST) return;
    if (!ST.live) return frame(`<div class="card"><h3>Quick Fee</h3><p>Fees for ${esc(ST.ses)} are not started in this ERP yet. The Principal starts it from Fee Receipt.</p></div>`);
    if (!ST.canCollect) return frame('<div class="card">This login can only view fees.</div>');
    if (DONE) return doneView();
    if (SID) return childView();
    searchView();
  }
  function searchView() {
    const S = ST.students.filter(s => !s.inactive);
    const q = SEARCH.trim().toLowerCase(), dg = q.replace(/\D/g, '');
    let L = [];
    if (q || CLS) L = S.filter(s => (!CLS || s.cls === CLS) && (!q || (s.name + ' ' + s.father + ' ' + s.adm).toLowerCase().includes(q) || (dg.length >= 4 && String(s.phone).replace(/\D/g, '').includes(dg)))).slice(0, 40);
    else L = recent().map(id => S.find(s => s.id === id)).filter(Boolean);
    const classes = [...new Set(S.map(s => s.cls))].filter(Boolean).sort((a, b) => a.localeCompare(b, 'en', { numeric: true }));
    const T = TODAY;
    frame(`<div class="card"><h3>💰 Quick Fee</h3><p class="mut" style="margin:0 0 8px">Next receipt <b>${esc(ST.next)}</b> · same series as Fee Receipt</p>
      <input id="fqS" type="search" placeholder="Name, father, admission no. or phone" value="${esc(SEARCH)}" oninput="fqSearch(this.value)" autocomplete="off">
      <div class="row" style="margin-top:8px"><select onchange="fqCls(this.value)"><option value="">All classes</option>${classes.map(c => `<option ${c === CLS ? 'selected' : ''}>${esc(c)}</option>`).join('')}</select></div></div>
      <div class="card">${!q && !CLS ? `<p class="mut" style="margin:0 0 4px">${L.length ? 'Recent children' : 'Type a name or choose a class.'}</p>` : ''}
        ${L.map(s => `<div class="li" onclick="fqOpen('${esc(s.id)}')"><div><b>${esc(s.name)}</b><small>${esc(s.cls)}${s.section ? ' ' + esc(s.section) : ''} · ${esc(s.father || '')}${s.adm ? ' · Adm ' + esc(s.adm) : ''}</small></div>${s.ledger ? '<span class="tag n">Open ›</span>' : '<span class="tag r">No fee card</span>'}</div>`).join('') || (q || CLS ? '<p class="mut">No child found.</p>' : '')}</div>
      ${T ? `<div class="card"><h3>Today's fee <small class="mut">${dmy(T.date)}</small></h3><div class="kp"><div><span>Receipts</span><b>${T.receipts.filter(r => r.status !== 'Cancelled').length}</b></div><div><span>Total</span><b class="grn">${rs(T.total)}</b></div><div><span>Cash</span><b>${rs(T.byMode.Cash || 0)}</b></div></div>
        ${Object.keys(T.byMode).filter(m => m !== 'Cash').map(m => `<span class="tag n" style="margin-right:4px">${esc(m)} ${rs(T.byMode[m])}</span>`).join('')}
        ${T.receipts.slice(0, 15).map(r => `<div class="li" style="cursor:default"><div><b>${esc(r.name)}</b><small>${esc(r.no)} · ${esc(r.cls)} · ${esc(r.time)} · ${esc(r.by)}</small></div><span class="tag ${r.status === 'Cancelled' ? 'r' : 'g'}">${r.status === 'Cancelled' ? 'Cancelled' : rs(r.received) + ' ' + esc(r.mode)}</span></div>`).join('')}</div>` : ''}`);
  }
  window.fqSearch = v => { SEARCH = v; clearTimeout(fqSearch.t); fqSearch.t = setTimeout(() => { draw(); const e = $('fqS'); if (e) { e.focus(); e.setSelectionRange(v.length, v.length); } }, 250); };
  window.fqCls = v => { CLS = v; draw(); };
  window.fqOpen = async id => { SID = id; LD = null; UPTO = -1; AMT = null; MODE = 'Cash'; REF = ''; CHQ = ''; LATE = ''; REMARK = ''; addRecent(id); frame('<div class="card">Opening…</div>');
    try { LD = await api('ledger', null, '&ses=' + encodeURIComponent(ST.ses) + '&sid=' + encodeURIComponent(id)); } catch (e) { toast(e.message); SID = ''; return draw(); }
    const D = dueHeads(), n = nowOrd(); UPTO = D.some(h => ord(h.m) <= n) ? n : -1; draw(); window.scrollTo(0, 0); };
  window.fqBack = () => { SID = ''; LD = null; draw(); };
  const stu = () => ST.students.find(s => s.id === SID) || {};

  function childView() {
    const s = stu(), L = LD.ledger, H = heads();
    const top = `<div class="card"><div class="row" style="flex-wrap:nowrap"><button class="btn s" style="flex:0 0 auto;white-space:nowrap" onclick="fqBack()">‹ Back</button><div class="hd" style="flex:1">${LD.photo ? `<img src="${esc(LD.photo)}" alt="">` : ''}<div><b style="font-size:16px">${esc(s.name)}</b><div class="mut">${esc(s.cls)}${s.section ? ' ' + esc(s.section) : ''} · ${esc(s.father || '')}${s.phone ? ' · ' + esc(s.phone) : ''}</div></div></div></div>`;
    if (!L) return frame(top + `<p class="warn">This child has no fee card for ${esc(ST.ses)} yet. The Principal makes it in Fee Receipt (copy the fee heads from a classmate).</p></div>`);
    const t = LD.totals, old = LD.oldDue || 0;
    const last = (LD.receipts || []).filter(r => r.status !== 'Cancelled').slice(-1)[0];
    let html = top + `<div class="kp"><div><span>Total fee</span><b>${rs(t.net)}</b></div><div><span>Paid</span><b class="grn">${rs(t.received)}</b></div><div><span>Due</span><b class="${t.balance > 0 ? 'red' : 'grn'}">${rs(t.balance)}</b></div></div>
      ${last ? `<p class="mut" style="margin:0">Last paid ${rs(last.received)} on ${dmy(last.date)} (${esc(last.no)})</p>` : ''}</div>`;
    if (old > 0) return frame(html + `<div class="card"><p class="warn"><b class="red">Old due ${rs(old)} is not on the fee card.</b> Like the old ERP, it must be paid first. Put it on the card (April, "Old Due"), then collect it here like any fee.</p><button class="btn y full" onclick="fqOldDue()">Add old due ${rs(old)} to the card</button></div>`);
    if (t.balance <= 0) return frame(html + `<div class="card ok"><div class="tick">✅</div><b>All fees paid for ${esc(ST.ses)}.</b></div>`);
    // month boxes
    const byM = MO.map(m => { const hs = H.filter(h => +h.m === m); return { m, hs, amt: hs.reduce((a, h) => a + h.amt, 0), due: hs.reduce((a, h) => a + h.amt - h.paid, 0) }; }).filter(x => x.hs.length);
    const A = alloc(), tot = allocTotal(), n = nowOrd(), D = dueHeads();
    const selI = new Set(A.map(x => x.i));
    html += `<div class="card"><h3>Which months?</h3><p class="mut" style="margin:0 0 8px">Tap the last month being paid — all dues up to it are picked. Yellow ring = this month.</p>
      <div class="months">${byM.map(x => { const o = ord(x.m), cls = x.due <= 0 ? 'paid' : x.hs.some(h => selI.has(h.i)) ? 'sel' : 'due';
        return `<button class="mo ${cls}${o === n ? ' now' : ''}" ${x.due <= 0 ? 'disabled' : `onclick="fqUpto(${o})"`}><b>${MN[x.m]}</b><small>${x.due <= 0 ? '✓ Paid' : rs(x.due)}</small></button>`; }).join('')}</div>
      <div class="row" style="margin-top:10px"><button class="btn s" onclick="fqUpto(${n})">Till ${MN[MO[n]]}</button><button class="btn s" onclick="fqAll()">Full due ${rs(t.balance)}</button><button class="btn s" onclick="fqUpto(-1)">Clear</button></div>
      <details style="margin-top:10px"><summary class="mut" style="cursor:pointer">Fee heads due (${D.length})</summary>${D.map(h => `<div class="li" style="cursor:default"><div><b>${esc(h.name)}</b><small>${MN[h.m]}${isTr(h.name) ? ' · 🚌 Transport' : ''}${h.paid ? ' · part paid ' + rs(h.paid) : ''}</small></div><span class="tag ${selI.has(h.i) ? 'g' : 'r'}">${rs(h.amt - h.paid)}</span></div>`).join('')}</details></div>`;
    // pay box
    const tr = A.filter(x => isTr(x.h.name)).reduce((a, x) => a + x.amt, 0), months = [...new Set(A.map(x => MN[x.h.m]))];
    const banks = (ST.banks || []).filter(b => !b.modes || b.modes.includes(MODE));
    if (MODE !== 'Cash' && !banks.some(b => b.name === BANK)) BANK = banks[0] ? banks[0].name : '';
    html += `<div class="pay"><div class="row" style="flex-wrap:nowrap"><div><span class="mut">Amount</span><div class="big">${rs(tot + lateP())}</div></div><div style="flex:0 0 130px"><input id="fqA" type="number" inputmode="decimal" min="1" placeholder="Other ₹" value="${AMT != null ? AMT : ''}" onchange="fqAmt(this.value)"></div></div>
      ${A.length ? `<div class="alloc">${months.join(', ')}${tr ? ` · school ${rs(tot - tr)} + 🚌 transport ${rs(tr)}` : ''}${AMT != null && A.length && A[A.length - 1].amt < A[A.length - 1].h.amt - A[A.length - 1].h.paid ? ' · last month part-paid' : ''}<br>Due after this: <b>${rs(t.balance - tot)}</b></div>` : '<p class="mut">Tap a month or type an amount.</p>'}
      <div class="modes">${['Cash', 'UPI', 'Cheque', 'NEFT'].filter(m => ST.modes.includes(m)).map(m => `<button class="btn s ${MODE === m ? 'on' : ''}" onclick="fqMode('${m}')">${m}</button>`).join('')}</div>
      ${MODE !== 'Cash' ? `<div class="row" style="margin-top:8px"><select onchange="fqSet('BANK',this.value)">${banks.map(b => `<option ${b.name === BANK ? 'selected' : ''}>${esc(b.name)}</option>`).join('')}</select>${MODE === 'Cheque' ? `<input placeholder="Cheque no." value="${esc(CHQ)}" onchange="fqSet('CHQ',this.value)">` : `<input placeholder="UTR / ref no. (optional)" value="${esc(REF)}" onchange="fqSet('REF',this.value)">`}</div>` : ''}
      <details style="margin-top:8px"><summary class="mut" style="cursor:pointer">Late fee / remark</summary><div class="row" style="margin-top:6px"><input type="number" inputmode="decimal" placeholder="Late fee ₹" value="${esc(LATE)}" onchange="fqSet('LATE',this.value)"><input placeholder="Remark" maxlength="200" value="${esc(REMARK)}" onchange="fqSet('REMARK',this.value)"></div></details>
      <button class="btn go full" style="margin-top:10px;font-size:17px" ${A.length && !BUSY ? '' : 'disabled'} onclick="fqConfirm()">Take ${rs(tot + lateP())} · ${esc(MODE)}</button></div>`;
    frame(html);
  }
  window.fqUpto = o => { UPTO = o; AMT = null; draw(); };
  window.fqAll = () => { UPTO = 99; AMT = null; draw(); };
  window.fqAmt = v => { const x = parseFloat(v); if (!x || x <= 0) { AMT = null; } else { const bal = LD.totals.balance / 100; if (x > bal) { toast('Due is only ' + rs(LD.totals.balance) + '.'); AMT = bal; } else AMT = Math.round(x * 100) / 100; } draw(); };
  window.fqMode = m => { MODE = m; draw(); };
  window.fqSet = (k, v) => { ({ BANK: () => { BANK = v; }, REF: () => { REF = v; }, CHQ: () => { CHQ = v; }, LATE: () => { LATE = v; }, REMARK: () => { REMARK = v; } })[k](); draw(); };
  window.fqOldDue = async () => { if (!confirm('Put the old due on this child\'s card (April, "Old Due")?')) return;
    try { await api('addFee', { ses: ST.ses, sids: [SID], type: 'Old Due', m: 4, amt: '', remark: 'Quick Fee', nonce: nonce() }); toast('Old due added. Now collect it.'); fqOpen(SID); } catch (e) { toast(e.message); } };
  window.fqConfirm = () => { const s = stu(), A = alloc(), amt = allocTotal() + lateP();
    if (MODE === 'Cheque' && !CHQ.trim()) return toast('Write the cheque number.');
    if (MODE !== 'Cash' && !BANK) return toast('Choose the bank.');
    sheet(`<h3>Take fee?</h3><p style="font-size:16px"><b>${esc(s.name)}</b> · ${esc(s.cls)}</p>
      <div class="alloc">${A.map(x => `${esc(x.h.name)} ${MN[x.h.m]} — ${rs(x.amt)}`).join('<br>')}${lateP() ? `<br>Late fee — ${rs(lateP())}` : ''}</div>
      <p class="big" style="margin:6px 0">${rs(amt)} <small class="mut" style="font-size:14px">${esc(MODE)}${MODE !== 'Cash' ? ' · ' + esc(BANK) : ''}</small></p><p class="mut">Receipt no. ${esc(ST.next)}</p>
      <div class="row"><button class="btn" onclick="fqClose()">Back</button><button class="btn go" onclick="fqSave()">✓ Save receipt</button></div>`); };
  window.fqSave = async () => { if (BUSY) return; BUSY = true; const A = alloc(), today = ST.today;
    const body = { ses: ST.ses, sid: SID, alloc: A.map(x => ({ i: x.i, amt: (x.amt / 100).toFixed(2) })), late: (lateP() / 100).toFixed(2), discount: '0', mode: MODE, date: today, nonce: nonce(),
      remark: [REMARK.trim(), MODE !== 'Cash' && MODE !== 'Cheque' && REF.trim() ? MODE + ' ref ' + REF.trim() : ''].filter(Boolean).join(' · ') };
    if (MODE !== 'Cash') Object.assign(body, { depositBank: BANK, depositDate: today, cheque: CHQ.trim(), chequeDate: today, bank: MODE === 'Cheque' ? BANK : 'NA' });
    try { const r = await api('collect', body); fqClose(); DONE = r.receipt; ST.next = r.receipt.no.replace(/\d+$/, n => String(+n + 1)); loadToday(); draw(); }
    catch (e) { fqClose(); toast(e.message); } finally { BUSY = false; } };

  // ---------- after saving ----------
  const rcText = r => `*${ST.school}*\nFee Receipt No. ${r.no}\nDate: ${dmy(r.date)}\n\nStudent: ${r.name} (${r.cls}${r.section ? ' ' + r.section : ''})\n` +
    (r.lines || []).map(l => `${l[1]} (${l[2]}): ${rs(l[3])}`).join('\n') + (r.late ? `\nLate fee: ${rs(r.late)}` : '') +
    `\n*Received: ${rs(r.received)}* (${r.mode})\nBalance due: ${rs(r.balanceAfter)}\n\nReceived by: ${r.by}\nThank you.`;
  function doneView() {
    const r = DONE, s = ST.students.find(x => x.id === r.sid) || {};
    frame(`<div class="card ok"><div class="tick">✅</div><h3>Receipt ${esc(r.no)} saved</h3><p class="big">${rs(r.received)}</p><p class="mut">${esc(r.name)} · ${esc(r.cls)} · ${esc(r.mode)}<br>Balance due now: <b class="${r.balanceAfter > 0 ? 'red' : 'grn'}">${rs(r.balanceAfter)}</b></p>
      <div class="alloc" style="text-align:left">${(r.lines || []).map(l => `${esc(l[1])} (${esc(l[2])}) — ${rs(l[3])}`).join('<br>')}</div>
      <button class="btn y full" onclick="fqWa()">📲 Send receipt on WhatsApp${s.phone ? ' (' + esc(s.phone) + ')' : ''}</button>
      <div class="row" style="margin-top:8px"><button class="btn" onclick="fqPrint()">🖨 Print</button><button class="btn go" onclick="fqNext()">Next child ›</button></div></div>`);
  }
  window.fqWa = () => { const r = DONE, s = ST.students.find(x => x.id === r.sid) || {}, ph = String(s.phone || '').replace(/\D/g, '').slice(-10);
    const a = document.createElement('a'); a.href = 'https://wa.me/' + (ph.length === 10 ? '91' + ph : '') + '?text=' + encodeURIComponent(rcText(r)); a.target = '_blank'; a.rel = 'noopener'; a.click(); };
  window.fqPrint = () => { const r = DONE, w = window.open('', '_blank');
    if (!w) return toast(/; wv\)/.test(navigator.userAgent) ? 'Printing works from the phone browser or a computer — send it on WhatsApp here.' : 'Allow pop-ups to print.');
    const one = c => `<div class="rc"><h2>${esc(ST.school)}</h2><h3>Fee Receipt <small>(${c})</small></h3><table><tr><th>Receipt No.</th><td>${esc(r.no)}</td><th>Date</th><td>${dmy(r.date)}</td></tr><tr><th>Student</th><td colspan="3">${esc(r.name)} — ${esc(r.cls)} ${esc(r.section || '')}${r.adm ? ' · Adm ' + esc(r.adm) : ''}</td></tr><tr><th>Father</th><td colspan="3">${esc(r.father || '')}</td></tr></table>
      <table style="margin-top:8px"><tr><th>#</th><th>Fee head</th><th>Months</th><th class="n">Amount</th></tr>${(r.lines || []).map(l => `<tr><td>${esc(l[0])}</td><td>${esc(l[1])}</td><td>${esc(l[2])}</td><td class="n">${rs(l[3])}</td></tr>`).join('')}${r.late ? `<tr><td></td><td colspan="2">Late fee</td><td class="n">${rs(r.late)}</td></tr>` : ''}<tr><th colspan="3">Received (${esc(r.mode)})</th><th class="n">${rs(r.received)}</th></tr><tr><td colspan="3">Balance due</td><td class="n">${rs(r.balanceAfter)}</td></tr></table>
      <p style="margin-top:26px">Received by: ${esc(r.by)}<span style="float:right">Signature: __________</span></p></div>`;
    w.document.write(`<!doctype html><html><head><meta charset="utf-8"><title>Receipt ${esc(r.no)}</title><style>body{font-family:Arial,sans-serif;font-size:13px;margin:14px}table{width:100%;border-collapse:collapse}th,td{border:1px solid #999;padding:5px;text-align:left}.n{text-align:right}h2,h3{text-align:center;margin:2px 0}.rc{border:1px solid #333;padding:12px;margin-bottom:16px;page-break-inside:avoid}</style></head><body>${one('Parent copy')}${one('Office copy')}<script>window.onload=()=>window.print()<\/script></body></html>`); w.document.close(); };
  window.fqNext = () => { DONE = null; SID = ''; LD = null; SEARCH = ''; draw(); setTimeout(() => { const e = $('fqS'); if (e) e.focus(); }, 50); };
  boot();
})();
