/* Fee demand bill for parents (owner, 13 Nov 2026) — used by the Chairman app (chairman.js) and the Salary app
   (manager.js). Data: demandbill_api.php. The bill is DRAWN on a canvas (same design as the 12 Nov PDF: month-wise
   heads, Paid / Concession / Due apart, school fee rows yellow, transport rows blue, receipts, siblings + family total)
   so it can be shared as a picture on WhatsApp. Also gives a ready WhatsApp text with the amounts.
   window.DemandBill.open(sid, token) → shows the bill in a full-screen sheet with Share / WhatsApp text / Download. */
(function () {
  const rs = n => '₹' + Math.round(+n || 0).toLocaleString('en-IN');
  const esc = s => String(s == null ? '' : s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
  const dmy = d => d ? d.split('-').reverse().join('-') : '';
  const NAVY = '#17324D', GOLD = '#E0A100', RED = '#B30000', FEEBG = '#FFF8E6', TRBG = '#D8EBFF', GREEN = '#1D6B2F';
  async function get(path, tok) {
    const r = await fetch(path, { cache: 'no-store', headers: { 'X-School-Token': tok || '' } }); let j = null; try { j = await r.json(); } catch (e) {}
    if (!r.ok || !j || !j.ok) throw Error((j && j.error) || 'Server problem. Please try again.'); return j;
  }
  // ---------------- drawing ----------------
  // Compact one-page demand bill (owner, 20 Nov 2026: "aise demand bill banta hai? sahi karo" — the 12 Nov long
  // month-by-month sheet is replaced). Only what is payable till the chosen month, one line per fee head with its months
  // ("Tuition Fee · Jul–Nov · ₹3,000"), each child's subtotal, the family total in figures and words, note and signs.
  const M3 = { April: 'Apr', May: 'May', June: 'Jun', July: 'Jul', August: 'Aug', September: 'Sep', October: 'Oct', November: 'Nov', December: 'Dec', January: 'Jan', February: 'Feb', March: 'Mar' };
  const ORD = ['April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December', 'January', 'February', 'March'];
  function words(n) {
    n = Math.round(+n || 0); if (!n) return 'Zero';
    const a = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten', 'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];
    const t = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];
    const two = x => x < 20 ? a[x] : t[Math.floor(x / 10)] + (x % 10 ? ' ' + a[x % 10] : '');
    const three = x => (x >= 100 ? a[Math.floor(x / 100)] + ' Hundred' + (x % 100 ? ' ' : '') : '') + (x % 100 ? two(x % 100) : '');
    let out = []; const cr = Math.floor(n / 1e7); n %= 1e7; const la = Math.floor(n / 1e5); n %= 1e5; const th = Math.floor(n / 1e3); n %= 1e3;
    if (cr) out.push(three(cr) + ' Crore'); if (la) out.push(two(la) + ' Lakh'); if (th) out.push(two(th) + ' Thousand'); if (n) out.push(three(n));
    return out.join(' ');
  }
  function monthsLabel(ms) {   // ['July','August',…] → 'Jul–Nov' (runs joined with commas)
    const ix = ms.map(m => ORD.indexOf(m)).filter(i => i >= 0).sort((p, q) => p - q), runs = [];
    ix.forEach(i => { const r = runs[runs.length - 1]; if (r && i === r[1] + 1) r[1] = i; else runs.push([i, i]); });
    return runs.map(([p, q]) => p === q ? M3[ORD[p]] : M3[ORD[p]] + '–' + M3[ORD[q]]).join(', ');
  }
  function lines(k) {   // heads till the bill month: one line per fee head + kind (fully discounted / payable), with fee, discount, payable
    const g = {}, order = [];
    k.heads.filter(h => h.now && (h.due > 0 || h.conc > 0)).forEach(h => {
      const free = h.due <= 0, key = h.name + (free ? '|free' : '|due');
      if (!g[key]) { g[key] = { name: h.name, tr: h.tr, free, ms: [], fee: 0, disc: 0, amt: 0 }; order.push(key); }
      const o = g[key]; o.ms.push(h.m); o.fee += h.amt - h.paid; o.disc += h.conc; o.amt += h.due; });
    const L = order.map(n => g[n]).sort((p, q) => p.tr - q.tr);
    if (k.total.oldDue > 0) L.unshift({ name: 'Previous balance (old due)', ms: [], fee: k.total.oldDue, disc: 0, amt: k.total.oldDue });
    return L;
  }
  function draw(B) {
    const W = 1240, P = 48, kids = B.kids, fam = kids.reduce((s, k) => s + k.total.now, 0), RH = 35;
    const LS = kids.map(lines), rows = LS.reduce((s, L) => s + Math.max(1, L.length) + 1, 0);
    const H = Math.max(1754, 840 + rows * RH);   // A4 (1240 × 1754) unless the family is very big
    const c = document.createElement('canvas'); c.width = W; c.height = H; const x = c.getContext('2d');
    const T = (t, X, Y, f, col, al) => { x.font = f; x.fillStyle = col || '#111'; x.textAlign = al || 'left'; x.fillText(String(t), X, Y); };
    const fit = (t, max, f) => { x.font = f; t = String(t); if (x.measureText(t).width <= max) return t; while (t.length > 3 && x.measureText(t + '…').width > max) t = t.slice(0, -1); return t + '…'; };
    const line = (x1, y1, x2, y2, col, w) => { x.beginPath(); x.moveTo(x1, y1); x.lineTo(x2, y2); x.strokeStyle = col || '#9aa5b1'; x.lineWidth = w || 1; x.stroke(); };
    const rect = (X, Y, w, h, bg, bd) => { if (bg) { x.fillStyle = bg; x.fillRect(X, Y, w, h); } if (bd) { x.strokeStyle = bd; x.lineWidth = 1.5; x.strokeRect(X, Y, w, h); } };
    x.fillStyle = '#fff'; x.fillRect(0, 0, W, H);
    rect(24, 24, W - 48, H - 48, null, NAVY); x.lineWidth = 1; rect(30, 30, W - 60, H - 60, null, '#c9d2dc');
    // header
    let y = 92;
    T((B.school || 'SN Mishra Singapore International School').toUpperCase(), W / 2, y, 'bold 40px Arial', NAVY, 'center'); y += 36;
    T('Jagannathpur, Bhadohi (U.P.)', W / 2, y, '22px Arial', '#444', 'center'); y += 26;
    line(P, y, W - P, y, NAVY, 2); y += 46;
    rect(W / 2 - 190, y - 32, 380, 46, NAVY); T('FEE DEMAND BILL', W / 2, y, 'bold 26px Arial', '#fff', 'center'); y += 50;
    // details
    const k0 = kids[0], half = (W - 2 * P) / 2, det = [
      ['Session', B.ses.replace(/^(20\d\d)-(\d\d)$/, '$1-20$2'), 'Bill Date', B.date],
      ["Father's Name", k0.father || '—', 'Payable till', B.upto + ' ' + B.uptoYear],
      ["Mother's Name", k0.mother || '—', 'Mobile No.', k0.mobile || '—'],
      ['Transport', k0.transport && k0.transport !== 'No' ? k0.transport : 'No', '', '']];
    det.forEach(([l1, v1, l2, v2]) => {
      T(l1, P + 4, y, '21px Arial', '#555'); T(': ' + fit(v1, l2 ? half - 190 : W - 2 * P - 190, 'bold 21px Arial'), P + 170, y, 'bold 21px Arial', '#111');
      if (l2) { T(l2, P + half + 20, y, '21px Arial', '#555'); T(': ' + fit(v2, half - 170, 'bold 21px Arial'), P + half + 160, y, 'bold 21px Arial', '#111'); }
      y += 34; });
    y += 10;
    // table: S.No | Student / Class | Fee Head | Month(s) | Fee | Discount | Payable
    const C = [P, P + 58, P + 290, P + 590, P + 780, P + 910, P + 1030, W - P], num = v => v ? Math.round(v).toLocaleString('en-IN') : '—';
    rect(P, y, W - 2 * P, RH + 4, NAVY);
    ['S.No', 'Student / Class', 'Fee Head', 'Month(s)', 'Fee', 'Discount', 'Payable'].forEach((h, i) => T(h, i >= 4 ? C[i + 1] - 10 : C[i] + 8, y + 28, 'bold 19px Arial', '#fff', i >= 4 ? 'right' : 'left'));
    y += RH + 4; let sn = 0, famDisc = 0;
    kids.forEach((k, ki) => {
      const L = LS[ki].length ? LS[ki] : [{ name: 'No fee due', ms: [], fee: 0, disc: 0, amt: 0 }], top = y;
      let tf = 0, td = 0;
      L.forEach((l, li) => {
        if (li % 2) rect(C[2], y, C[7] - C[2], RH, '#f6f8fa');
        if (li === 0) { sn++; T(sn, C[0] + 20, y + 24, 'bold 19px Arial', '#111'); T(fit(k.name, C[2] - C[1] - 16, 'bold 19px Arial'), C[1] + 8, y + 24, 'bold 19px Arial', '#111'); }
        if (li === 1) T(fit('Class ' + k.cls + (k.section ? '-' + k.section : '') + ' · Adm ' + k.adm, C[2] - C[1] - 16, '17px Arial'), C[1] + 8, y + 24, '17px Arial', '#555');
        T(fit(l.name, C[3] - C[2] - 16, '19px Arial'), C[2] + 8, y + 24, l.tr ? 'bold 19px Arial' : '19px Arial', l.tr ? '#0b4f9c' : '#111');
        T(fit(monthsLabel(l.ms), C[4] - C[3] - 16, '19px Arial'), C[3] + 8, y + 24, '19px Arial', '#333');
        T(num(l.fee), C[5] - 10, y + 24, '19px Arial', '#111', 'right');
        T(l.disc ? '− ' + num(l.disc) : '—', C[6] - 10, y + 24, '19px Arial', l.disc ? GREEN : '#aaa', 'right');
        T(num(l.amt), C[7] - 10, y + 24, 'bold 19px Arial', l.amt ? '#111' : '#aaa', 'right');
        tf += l.fee; td += l.disc; line(C[2], y + RH, C[7], y + RH, '#e1e6ec'); y += RH;
      });
      famDisc += td;
      if (L.length < 2) T(fit('Class ' + k.cls + (k.section ? '-' + k.section : ''), C[2] - C[1] - 16, '17px Arial'), C[1] + 8, y + 24, '17px Arial', '#555');
      rect(C[2], y, C[7] - C[2], RH, '#FFF8E6');
      T('Total of ' + k.name.split(' ')[0] + (k.total.paid > 0 ? ' (paid ₹' + Math.round(k.total.paid).toLocaleString('en-IN') + ')' : ''), C[2] + 8, y + 24, 'bold 19px Arial', '#5a3d00');
      T(num(tf), C[5] - 10, y + 24, 'bold 19px Arial', '#5a3d00', 'right'); T(td ? '− ' + num(td) : '—', C[6] - 10, y + 24, 'bold 19px Arial', td ? GREEN : '#aaa', 'right');
      T(num(k.total.now), C[7] - 10, y + 24, 'bold 20px Arial', '#5a3d00', 'right');
      y += RH;
      [C[0], C[1], C[2], C[7]].forEach(cx => line(cx, top, cx, y, '#9aa5b1')); [C[3], C[4], C[5], C[6]].forEach(cx => line(cx, top, cx, y, '#d5dbe2'));
      line(P, y, W - P, y, '#9aa5b1', 1.5);
    });
    // grand total
    y += 24; rect(P, y, W - 2 * P, 96, '#FDECEC', RED);
    T(kids.length > 1 ? 'TOTAL PAYABLE (' + kids.length + ' children) till ' + B.upto + ' ' + B.uptoYear : 'TOTAL PAYABLE till ' + B.upto + ' ' + B.uptoYear, P + 20, y + 40, 'bold 24px Arial', RED);
    T('₹ ' + Math.round(fam).toLocaleString('en-IN'), W - P - 20, y + 44, 'bold 38px Arial', RED, 'right');
    T(fit('Rupees ' + words(fam) + ' Only' + (famDisc ? '   ·   Discount given: ₹' + Math.round(famDisc).toLocaleString('en-IN') : ''), W - 2 * P - 40, 'italic 19px Arial'), P + 20, y + 78, 'italic 19px Arial', '#333');
    y += 130;
    // note + signs
    T('Note:', P, y, 'bold 20px Arial', '#111'); y += 30;
    ['Kripya upar likhi rashi jald se jald school office mein jama karein aur receipt avashya lein.', 'Agar fee jama kar di hai to is bill ko anadekha karein. Kisi bhi jaankari ke liye school office se sampark karein.']
      .forEach(t => { T(fit(t, W - 2 * P, '19px Arial'), P, y, '19px Arial', '#333'); y += 28; });
    const sy = Math.max(y + 90, H - 120);
    line(P, sy, P + 260, sy, '#333'); line(W - P - 260, sy, W - P, sy, '#333');
    T('Accountant', P + 130, sy + 28, '20px Arial', '#333', 'center'); T('Principal', W - P - 130, sy + 28, '20px Arial', '#333', 'center');
    T('Computer generated bill · ' + (B.school || 'SN Mishra Singapore International School'), W / 2, H - 46, '16px Arial', '#888', 'center');
    return c;
  }
  function waText(B) {
    const fam = B.kids.reduce((a, k) => a + k.total.now, 0);
    return `*FEE DEMAND BILL*\n${B.school}\nSession ${B.ses} · Date ${B.date}\n\n` + B.kids.map(k => `*${k.name}* (Class ${k.cls})\nSchool fee due: ${rs(k.total.nowFee)}\nTransport due: ${rs(k.total.nowTr)}\n*Payable till ${B.upto}: ${rs(k.total.now)}*`).join('\n\n') +
      (B.kids.length > 1 ? `\n\n*Family total: ${rs(fam)}*` : '') + `\n\nKripya jaldi se jaldi school office me jama karein aur receipt zaroor lein. Dhanyavaad 🙏`;
  }
  // ---------------- sheet ----------------
  let CUR = null, URL_ = null;
  function sheet(html) {
    let el = document.getElementById('dbSheet'); if (!el) { el = document.createElement('div'); el.id = 'dbSheet'; document.body.appendChild(el); }
    el.innerHTML = `<div class="db-bg" onclick="if(event.target===this)DemandBill.close()"><div class="db-in">${html}</div></div>`;
  }
  const st = document.createElement('style');
  st.textContent = `#dbSheet .db-bg{position:fixed;inset:0;z-index:9000;background:rgba(10,20,35,.6);display:flex;align-items:flex-end;justify-content:center}
  #dbSheet .db-in{background:#fff;width:100%;max-width:720px;max-height:94vh;overflow:auto;border-radius:22px 22px 0 0;padding:14px;font-family:inherit}
  #dbSheet .db-top{display:flex;justify-content:space-between;align-items:center;gap:8px;margin-bottom:10px}#dbSheet .db-top b{font-size:17px;color:#17324D}
  #dbSheet img{width:100%;border:1px solid #e3e7ee;border-radius:12px;display:block}#dbSheet .db-acts{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin:10px 0}
  #dbSheet button,#dbSheet a.db-b{font:inherit;font-size:14px;border:0;border-radius:99px;padding:11px 10px;cursor:pointer;text-align:center;text-decoration:none;font-weight:700}
  #dbSheet .g{background:#22C55E;color:#fff}#dbSheet .y{background:#F2C14E;color:#1d1d1d}#dbSheet .l{background:#eef1f5;color:#17324D}#dbSheet .full{grid-column:1/-1}
  #dbSheet .db-sum{font-size:13px;color:#445;margin:6px 0}#dbSheet .db-msg{padding:20px;text-align:center;color:#445}
  .db-pick{display:block;width:100%;text-align:left;border:0;border-bottom:1px solid #eef1f4;background:#fff;padding:10px 4px;font:inherit;cursor:pointer}.db-pick b{display:block;font-size:14px}.db-pick small{color:#667;font-size:12px}.db-pick em{float:right;font-style:normal;font-weight:700;color:#B30000}`;
  document.head.appendChild(st);
  async function open(sid, tok) {
    sheet('<div class="db-msg">Making the demand bill…</div>');
    try { CUR = await get('demandbill_api.php?a=bill&sid=' + encodeURIComponent(sid), tok); } catch (e) { return sheet(`<div class="db-msg">${esc(e.message)}</div><button class="l" style="width:100%" onclick="DemandBill.close()">Close</button>`); }
    const c = draw(CUR); URL_ = c.toDataURL('image/png'); CUR._canvas = c;
    const fam = CUR.kids.reduce((a, k) => a + k.total.now, 0), wv = /; wv\)/.test(navigator.userAgent);
    sheet(`<div class="db-top"><b>Demand bill · ${esc(CUR.kids[0].name)}</b><button class="l" onclick="DemandBill.close()">Close</button></div>
      <p class="db-sum">${CUR.kids.length > 1 ? CUR.kids.length + ' children (same mobile) · ' : ''}Payable till ${esc(CUR.upto)}: <b>${rs(fam)}</b></p>
      <div class="db-acts"><button class="g" onclick="DemandBill.share()">📤 Share bill picture</button><a class="db-b y" target="_blank" href="https://wa.me/${(CUR.kids[0].mobile.match(/\d{10}/) || [''])[0] ? '91' + CUR.kids[0].mobile.match(/\d{10}/)[0] : ''}?text=${encodeURIComponent(waText(CUR))}">💬 WhatsApp message</a>
        ${wv ? '' : '<button class="l" onclick="DemandBill.download()">⬇ Download picture</button><button class="l" onclick="DemandBill.print()">🖨 Print</button>'}</div>
      <img src="${URL_}" alt="Fee demand bill">
      <p class="db-sum">Share bill picture: choose WhatsApp and the parent. WhatsApp message: opens the parent's number with the amounts written.</p>`);
  }
  async function share() {
    if (!CUR) return; const name = 'Demand-bill-' + CUR.kids[0].name.replace(/\W+/g, '-') + '.png';
    const blob = await new Promise(r => CUR._canvas.toBlob(r, 'image/png')); const file = new File([blob], name, { type: 'image/png' });
    try { if (navigator.canShare && navigator.canShare({ files: [file] })) { await navigator.share({ files: [file], title: 'Fee demand bill', text: waText(CUR) }); return; } } catch (e) { if (e && e.name === 'AbortError') return; }
    if (/; wv\)/.test(navigator.userAgent)) return alert('Picture sharing does not work inside this app on your phone. Use "WhatsApp message", or open this app in Chrome to share the picture.');
    download(); alert('Picture downloaded — attach it in WhatsApp. (This phone cannot share pictures directly.)');
  }
  function download() { if (!URL_) return; const a = document.createElement('a'); a.href = URL_; a.download = 'Demand-bill-' + CUR.kids[0].name.replace(/\W+/g, '-') + '.png'; document.body.appendChild(a); a.click(); a.remove(); }
  function print() { const w = window.open('', '_blank'); if (!w) return alert('Allow pop-ups to print.'); w.document.write(`<!doctype html><html><head><title>Demand bill</title><style>@page{size:A4;margin:8mm}body{margin:0}img{width:100%}</style></head><body><img src="${URL_}" onload="window.print()"></body></html>`); w.document.close(); }
  // student picker (for apps without their own student list)
  let LIST = null;
  async function picker(tok, mount) {
    const el = typeof mount === 'string' ? document.getElementById(mount) : mount; if (!el) return;
    if (!LIST) { el.innerHTML = '<div class="db-msg">Opening children list…</div>'; try { LIST = await get('demandbill_api.php?a=list', tok); } catch (e) { el.innerHTML = `<div class="db-msg">${esc(e.message)}</div>`; return; } }
    const draw2 = q => { q = (q || '').toLowerCase().trim(); const L = LIST.rows.filter(r => !q || (r.name + ' ' + r.adm + ' ' + r.cls + ' ' + r.father + ' ' + r.mobile).toLowerCase().includes(q)).slice(0, 80);
      document.getElementById('dbList').innerHTML = L.map(r => `<button class="db-pick" onclick="DemandBill.open('${esc(r.sid)}',DemandBill._tok)"><em>${r.due ? rs(r.due) : '✓'}</em><b>${esc(r.name)}</b><small>Class ${esc(r.cls)} · ${esc(r.father)} · ${esc(r.mobile)}</small></button>`).join('') || '<div class="db-msg">Nobody found.</div>'; };
    DemandBill._tok = tok;
    el.innerHTML = `<input id="dbQ" placeholder="Search child, father, class or mobile" style="width:100%;font:inherit;padding:11px;border:1px solid #c9d2dc;border-radius:12px" oninput="DemandBill._f(this.value)">
      <p class="db-sum">Session ${esc(LIST.ses)} · amount = payable till ${esc(LIST.upto)} · highest first</p><div id="dbList"></div>`;
    DemandBill._f = draw2; draw2('');
  }
  window.DemandBill = { open, share, download, print, picker, close: () => { const el = document.getElementById('dbSheet'); if (el) el.innerHTML = ''; }, draw, waText };
})();
