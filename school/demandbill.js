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
  // Rows of one child (owner, 20 Nov 2026): 1) Activity charges = every one-time head still to pay, added up (Digital + Health +
  // Exam + Sports = 4,500), 2) monthly heads (Tuition, then Transport) with "Jul–Nov · 5 months × 600", a discount inside the
  // billed months shown right under its fee as a minus row, 3) everything discounted BEFORE the billed months (April–June,
  // admission) is not in the table — it goes to the "Discount" box under the total.
  const isMonthly = n => /tuition|tution|transport|hostel/i.test(n);
  function rate(G) { const a = G.map(h => h.amt); return a.every(v => v === a[0]) ? a[0] : 0; }
  function lines(k) {
    const H = k.heads.filter(h => h.now), rows = [], below = [];
    const mon = H.filter(h => isMonthly(h.name)), payM = mon.filter(h => h.due > 0).map(h => ORD.indexOf(h.m));
    const start = payM.length ? Math.min(...payM) : 12;   // first billed month
    if (k.total.oldDue > 0) rows.push({ name: 'Previous balance (old due)', when: '', amt: k.total.oldDue });
    const one = H.filter(h => !isMonthly(h.name) && h.due > 0);
    if (one.length) rows.push({ name: 'Activity Charges', sub: one.map(h => h.name.replace(/\s*Fee$/i, '').replace(/Digital Class Room/i, 'Digital').replace(/Health And Sa?f?e?t?y|Health And Sefety/i, 'Health') + ' ' + Math.round(h.due).toLocaleString('en-IN')).join(' + '), when: 'Annual', amt: one.reduce((s, h) => s + h.due, 0) });
    H.filter(h => !isMonthly(h.name) && h.due <= 0 && h.conc > 0).forEach(h => below.push({ name: h.name, when: '', amt: h.conc }));
    const names = []; mon.forEach(h => { if (!names.includes(h.name)) names.push(h.name); });
    names.sort((p, q) => /transport|hostel/i.test(p) - /transport|hostel/i.test(q)).forEach(n => {
      const G = mon.filter(h => h.name === n), early = G.filter(h => ORD.indexOf(h.m) < start && h.conc > 0 && h.due <= 0), billed = G.filter(h => !early.includes(h) && (h.due > 0 || h.conc > 0));
      if (early.length) { const r = rate(early); below.push({ name: n, when: monthsLabel(early.map(h => h.m)) + (r ? ' (' + early.length + ' month' + (early.length > 1 ? 's' : '') + ' × ' + r.toLocaleString('en-IN') + ')' : ''), amt: early.reduce((s, h) => s + h.conc, 0) }); }
      if (billed.length) {
        const r = rate(billed), fee = billed.reduce((s, h) => s + h.amt - h.paid, 0), dc = billed.reduce((s, h) => s + h.conc, 0), tr = /transport|hostel/i.test(n);
        rows.push({ name: tr ? n.replace(/^Transport$/i, 'Transport Fee') : n, tr, when: monthsLabel(billed.map(h => h.m)) + (r ? ' · ' + billed.length + ' month' + (billed.length > 1 ? 's' : '') + ' × ' + r.toLocaleString('en-IN') : ''), amt: fee });
        if (dc > 0) rows.push({ name: 'Less: Discount on ' + (tr ? 'transport' : n.replace(/Tution/i, 'Tuition').toLowerCase()), when: '', amt: -dc, disc: true });
      }
    });
    return { rows, below };
  }
  function draw(B) {
    const W = 1240, P = 48, kids = B.kids, fam = kids.reduce((s, k) => s + k.total.now, 0), RH = 36;
    const LS = kids.map(lines), rows = LS.reduce((s, L) => s + Math.max(1, L.rows.length) + 1 + L.rows.filter(r => r.sub).length * 0.6, 0);
    const nb = LS.filter(L => L.below.length).length, H = Math.max(1754, 900 + rows * RH + (nb ? 80 + nb * 34 : 0));
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
    // table: S.No | Student / Class | Fee Head | Month(s) | Amount
    const C = [P, P + 64, P + 330, P + 790, W - P], num = v => (v < 0 ? '− ' : '') + Math.round(Math.abs(v || 0)).toLocaleString('en-IN');
    rect(P, y, W - 2 * P, RH + 4, NAVY);
    ['S.No', 'Student / Class', 'Fee Head', 'Month(s)', 'Amount (₹)'].forEach((h, i) => T(h, i === 4 ? C[4] - 12 : C[i] + 10, y + 26, 'bold 20px Arial', '#fff', i === 4 ? 'right' : 'left'));
    y += RH + 4; let sn = 0;
    kids.forEach((k, ki) => {
      const L = LS[ki].rows.length ? LS[ki].rows : [{ name: 'No fee due', when: '', amt: 0 }], top = y;
      L.forEach((l, li) => {
        const h = l.sub ? RH + 22 : RH;
        if (l.disc) rect(C[2], y, C[4] - C[2], h, '#EAF6EE'); else if (li % 2) rect(C[2], y, C[4] - C[2], h, '#f6f8fa');
        if (li === 0) { sn++; T(sn, C[0] + 22, y + 25, 'bold 20px Arial', '#111'); T(fit(k.name, C[2] - C[1] - 20, 'bold 20px Arial'), C[1] + 10, y + 25, 'bold 20px Arial', '#111'); T(fit('Class ' + k.cls + (k.section ? '-' + k.section : '') + ' · Adm ' + k.adm, C[2] - C[1] - 20, '17px Arial'), C[1] + 10, y + 50, '17px Arial', '#555'); }
        T(fit(l.name, C[3] - C[2] - 20, l.tr || l.name === 'Activity Charges' ? 'bold 20px Arial' : '20px Arial'), C[2] + 10, y + 25, l.tr || l.name === 'Activity Charges' ? 'bold 20px Arial' : '20px Arial', l.disc ? GREEN : l.tr ? '#0b4f9c' : '#111');
        if (l.sub) T(fit('(' + l.sub + ')', C[3] - C[2] - 16, '16px Arial'), C[2] + 10, y + 48, '16px Arial', '#555');
        T(fit(l.when, C[4] - C[3] - 100, '18px Arial'), C[3] + 10, y + 25, '18px Arial', '#333');
        T(num(l.amt), C[4] - 12, y + 25, l.name === 'Activity Charges' ? 'bold 20px Arial' : '20px Arial', l.disc ? GREEN : '#111', 'right');
        line(C[2], y + h, C[4], y + h, '#e1e6ec'); y += h;
      });
      if (y - top < 2 * RH) y = top + 2 * RH - RH;   // room for the class line
      rect(C[2], y, C[4] - C[2], RH, '#FFF8E6');
      T('Total of ' + k.name.split(' ')[0], C[2] + 10, y + 25, 'bold 20px Arial', '#5a3d00');
      T(num(k.total.now), C[4] - 12, y + 25, 'bold 21px Arial', '#5a3d00', 'right');
      y += RH;
      [C[0], C[1], C[2], C[4]].forEach(cx => line(cx, top, cx, y, '#9aa5b1')); line(C[3], top, C[3], y, '#d5dbe2');
      line(P, y, W - P, y, '#9aa5b1', 1.5);
    });
    // grand total
    y += 22; rect(P, y, W - 2 * P, 92, '#FDECEC', RED);
    T(kids.length > 1 ? 'TOTAL PAYABLE (' + kids.length + ' children) till ' + B.upto + ' ' + B.uptoYear : 'TOTAL PAYABLE till ' + B.upto + ' ' + B.uptoYear, P + 20, y + 40, 'bold 24px Arial', RED);
    T('₹ ' + Math.round(fam).toLocaleString('en-IN'), W - P - 20, y + 44, 'bold 38px Arial', RED, 'right');
    T(fit('Rupees ' + words(fam) + ' Only', W - 2 * P - 40, 'italic 19px Arial'), P + 20, y + 76, 'italic 19px Arial', '#333');
    y += 120;
    // discount given before the billed months (April–June, admission …) — not to be paid
    if (nb) {
      T('Discount given (not to be paid)', P, y, 'bold 21px Arial', GREEN); y += 12;
      kids.forEach((k, ki) => { const D = LS[ki].below; if (!D.length) return; y += 32;
        const tot = D.reduce((s, d) => s + d.amt, 0), txt = D.map(d => d.name.replace(/Tution/i, 'Tuition') + (d.when ? ' ' + d.when : '') + ' ₹' + Math.round(d.amt).toLocaleString('en-IN')).join('  ·  ');
        T(k.name.split(' ')[0] + ':', P + 10, y, 'bold 19px Arial', '#111'); T(fit(txt, W - 2 * P - 330, '19px Arial'), P + 150, y, '19px Arial', '#333');
        T('₹ ' + Math.round(tot).toLocaleString('en-IN'), W - P - 12, y, 'bold 19px Arial', GREEN, 'right'); });
      y += 44;
    }
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
  let CUR = null, URL_ = null, OPEN = null;
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
  st.textContent += `#dbSheet .db-acct{background:#F4FAF6;border:1px solid #D3EBDA;border-radius:14px;padding:8px 12px;margin:8px 0;font-size:13.5px}#dbSheet .db-acct summary{font-weight:700;color:#17324D;cursor:pointer;padding:3px 0}
  #dbSheet .db-kid{padding:6px 0;border-top:1px solid #E1EFE5}#dbSheet .db-kid:first-of-type{border-top:0}#dbSheet .db-kn{font-weight:700;color:#17324D;margin:2px 0 4px}
  #dbSheet .db-r{display:flex;justify-content:space-between;gap:10px;padding:2px 0}#dbSheet .db-r small{display:block;color:#667;font-size:11.5px}#dbSheet .db-rc{margin-top:5px;padding-top:5px;border-top:1px dashed #CFE3D5}
  #dbZoom{position:fixed;inset:0;z-index:9500;background:#0b1320;touch-action:none;overflow:hidden;user-select:none}
  #dbZoom img{position:absolute;left:0;top:0;pointer-events:none;-webkit-user-drag:none;transform-origin:0 0;max-width:none;will-change:transform}
  #dbZoom .zb{position:absolute;left:0;right:0;bottom:0;display:flex;gap:8px;justify-content:center;padding:12px 10px calc(12px + env(safe-area-inset-bottom));background:linear-gradient(transparent,rgba(0,0,0,.7))}
  #dbZoom .zb button{font:inherit;font-size:15px;font-weight:700;border:0;border-radius:99px;padding:11px 16px;background:#fff;color:#17324D;min-width:52px}#dbZoom .zb .x{background:#F2C14E}
  #dbZoom .zh{position:absolute;top:10px;left:0;right:0;text-align:center;color:#cfd8e3;font-size:12px}`;
  document.head.appendChild(st);
  // ---------------- account summary: what was paid, concession, what is left (owner, 23 Nov 2026: "parents puchh lete
  // kitna paid kiye kitna jama hai — vo bhi show kare to baat karne ke liye easy rahega") ----------------
  function acct(B) {
    const row = (a, b, col) => `<div class="db-r"><span>${a}</span><b${col ? ` style="color:${col}"` : ''}>${b}</b></div>`;
    return `<details class="db-acct" open><summary>💰 Fee account — kitna jama, kitna baaki</summary>${B.kids.map(k => { const t = k.total || {}, R = k.receipts || [];
      return `<div class="db-kid">${B.kids.length > 1 ? `<div class="db-kn">${esc(k.name)} · Class ${esc(k.cls)}</div>` : ''}
        ${row('Session fee (full year)', rs(t.fee))}${t.conc ? row('Concession / discount', '− ' + rs(t.conc)) : ''}${row('Jama (paid)', rs(t.paid), GREEN)}
        ${t.oldDue ? row('Old session due', rs(t.oldDue), RED) : ''}${row('Dena hai till ' + esc(B.upto), rs(t.now), RED)}${row('Poore saal ka baaki', rs(t.due))}
        ${R.length ? `<div class="db-rc">${R.slice().reverse().map(r => `<div class="db-r"><span>${esc(dmy(r.date))} · Rcpt ${esc(r.no)}<small>${esc((r.lines || []).map(l => l.head + (l.months ? ' (' + l.months + ')' : '')).join(', '))}</small></span><b>${rs(r.amt)}</b></div>`).join('')}</div>` : '<div class="db-r"><span style="color:#667">No receipt this session yet.</span></div>'}</div>`; }).join('')}</details>`;
  }
  // ---------------- zoom viewer: pinch / double-tap / drag / + − (the app's WebView has no page zoom) ----------------
  function zoom() {
    if (!URL_) return; let el = document.getElementById('dbZoom'); if (el) el.remove();
    el = document.createElement('div'); el.id = 'dbZoom';
    el.innerHTML = `<div class="zh">Do ungli se bada karein · double-tap = zoom · ungli se khiskayein</div><img src="${URL_}" alt="Fee demand bill" draggable="false"><div class="zb"><button data-z="-">−</button><button data-z="fit">Fit</button><button data-z="+">+</button><button class="x" data-z="close">✕ Close</button></div>`;
    document.body.appendChild(el);
    const img = el.querySelector('img'); let sc = 1, fit = 1, x = 0, y = 0; const P = new Map(); let pinch = null, lastTap = 0, drag = null;
    const apply = () => { const W = el.clientWidth, H = el.clientHeight, w = img.naturalWidth * sc, h = img.naturalHeight * sc;
      x = w <= W ? (W - w) / 2 : Math.min(0, Math.max(W - w, x)); y = h <= H ? (H - h) / 2 : Math.min(0, Math.max(H - h, y));
      img.style.transform = `translate(${x}px,${y}px) scale(${sc})`; };
    const zoomAt = (ns, cx, cy) => { ns = Math.max(fit, Math.min(fit * 6, ns)); x = cx - (cx - x) * ns / sc; y = cy - (cy - y) * ns / sc; sc = ns; apply(); };
    const doFit = () => { fit = Math.min(el.clientWidth / img.naturalWidth, el.clientHeight / img.naturalHeight); sc = fit; x = y = 0; apply(); };
    if (img.complete && img.naturalWidth) doFit(); else img.onload = doFit;
    el.querySelector('.zb').addEventListener('click', e => { const z = e.target.dataset.z, W = el.clientWidth / 2, H = el.clientHeight / 2;
      if (z === '+') zoomAt(sc * 1.5, W, H); else if (z === '-') zoomAt(sc / 1.5, W, H); else if (z === 'fit') doFit(); else if (z === 'close') el.remove(); });
    el.addEventListener('pointerdown', e => { if (e.target.closest('.zb')) return; el.setPointerCapture(e.pointerId); P.set(e.pointerId, { x: e.clientX, y: e.clientY });
      if (P.size === 2) { const [a, b] = [...P.values()]; pinch = { d: Math.hypot(a.x - b.x, a.y - b.y), s: sc }; drag = null; }
      else if (P.size === 1) { const now = Date.now(); if (now - lastTap < 300) { sc > fit * 1.05 ? doFit() : zoomAt(fit * 2.5, e.clientX, e.clientY); lastTap = 0; } else lastTap = now; drag = { x: e.clientX, y: e.clientY }; } });
    el.addEventListener('pointermove', e => { if (!P.has(e.pointerId)) return; P.set(e.pointerId, { x: e.clientX, y: e.clientY });
      if (pinch && P.size === 2) { const [a, b] = [...P.values()]; zoomAt(pinch.s * Math.hypot(a.x - b.x, a.y - b.y) / pinch.d, (a.x + b.x) / 2, (a.y + b.y) / 2); }
      else if (drag) { x += e.clientX - drag.x; y += e.clientY - drag.y; drag = { x: e.clientX, y: e.clientY }; apply(); } });
    const up = e => { P.delete(e.pointerId); if (P.size < 2) pinch = null; if (P.size === 1) { const [a] = [...P.values()]; drag = { x: a.x, y: a.y }; } else if (!P.size) drag = null; };
    el.addEventListener('pointerup', up); el.addEventListener('pointercancel', up);
    el.addEventListener('wheel', e => { e.preventDefault(); zoomAt(sc * (e.deltaY < 0 ? 1.2 : 1 / 1.2), e.clientX, e.clientY); }, { passive: false });
  }
  async function open(sid, tok, mode) {
    // mode 'single' = only this child; default = sibling bill (brothers / sisters with the same mobile in one bill)
    OPEN = { sid, tok, mode: mode === 'single' ? 'single' : 'sib' };
    sheet('<div class="db-msg">Making the demand bill…</div>');
    try { CUR = await get('demandbill_api.php?a=bill&sid=' + encodeURIComponent(sid) + (OPEN.mode === 'single' ? '&mode=single' : ''), tok); } catch (e) { return sheet(`<div class="db-msg">${esc(e.message)}</div><button class="l" style="width:100%" onclick="DemandBill.close()">Close</button>`); }
    const c = draw(CUR); URL_ = c.toDataURL('image/png'); CUR._canvas = c;
    const fam = CUR.kids.reduce((a, k) => a + k.total.now, 0), wv = /; wv\)/.test(navigator.userAgent);
    sheet(`<div class="db-top"><b>Demand bill · ${esc(CUR.kids[0].name)}</b><button class="l" onclick="DemandBill.close()">Close</button></div>
      <div class="db-acts"><button class="${OPEN.mode === 'sib' ? 'y' : 'l'}" onclick="DemandBill.mode('sib')">👨‍👩‍👧 Sibling bill</button><button class="${OPEN.mode === 'single' ? 'y' : 'l'}" onclick="DemandBill.mode('single')">👤 Single bill</button></div>
      <p class="db-sum">${CUR.kids.length > 1 ? CUR.kids.length + ' children (same mobile) · ' : ''}Payable till ${esc(CUR.upto)}: <b>${rs(fam)}</b></p>
      <div class="db-acts"><button class="g" onclick="DemandBill.share()">📤 Share bill picture</button><a class="db-b y" target="_blank" href="https://wa.me/${(CUR.kids[0].mobile.match(/\d{10}/) || [''])[0] ? '91' + CUR.kids[0].mobile.match(/\d{10}/)[0] : ''}?text=${encodeURIComponent(waText(CUR))}">💬 WhatsApp message</a>
        ${wv ? '' : '<button class="l" onclick="DemandBill.download()">⬇ Download picture</button><button class="l" onclick="DemandBill.print()">🖨 Print</button>'}</div>
      ${acct(CUR)}
      <button class="l full" style="width:100%;margin-bottom:8px" onclick="DemandBill.zoom()">🔍 Zoom bill (bada karke dekhiye)</button>
      <img src="${URL_}" alt="Fee demand bill" onclick="DemandBill.zoom()" style="cursor:zoom-in">
      <p class="db-sum">Share bill picture: choose WhatsApp and the parent. WhatsApp message: opens the parent's number with the amounts written.</p>`);
  }
  async function share() {
    if (!CUR) return; const name = 'Demand-bill-' + CUR.kids[0].name.replace(/\W+/g, '-') + '.png';
    // Inside the school's Android apps (Chairman 1.1+, Salary 1.2+): the app's own bridge sends the picture to the share sheet (20 Nov 2026)
    if (window.SNMSISFiles && typeof SNMSISFiles.shareFile === 'function') { SNMSISFiles.shareFile(name, CUR._canvas.toDataURL('image/png').split(',')[1], 'image/png'); return; }
    if (window.SNMSISFiles && typeof SNMSISFiles.shareImage === 'function') { SNMSISFiles.shareImage(name, CUR._canvas.toDataURL('image/png').split(',')[1]); return; }   // Teacher app
    const blob = await new Promise(r => CUR._canvas.toBlob(r, 'image/png')); const file = new File([blob], name, { type: 'image/png' });
    try { if (navigator.canShare && navigator.canShare({ files: [file] })) { await navigator.share({ files: [file], title: 'Fee demand bill', text: waText(CUR) }); return; } } catch (e) { if (e && e.name === 'AbortError') return; }
    if (/; wv\)/.test(navigator.userAgent)) return alert('This app is an old version — picture sharing needs the new app. Download it again from maakit.in/school/downloads (uninstall the old one first), or use "WhatsApp message" for now.');
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
  window.DemandBill = { open, zoom, mode: m => OPEN && open(OPEN.sid, OPEN.tok, m), share, download, print, picker, close: () => { const el = document.getElementById('dbSheet'); if (el) el.innerHTML = ''; const z = document.getElementById('dbZoom'); if (z) z.remove(); }, draw, waText };
})();
