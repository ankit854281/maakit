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
  function draw(B) {
    const W = 1080, P = 36, RH = 34, kids = B.kids, fam = kids.reduce((a, k) => a + k.total.now, 0);
    let H = 190 + (kids.length > 1 ? 96 : 0) + 120;
    kids.forEach(k => { H += 230 + 150 + 44 + k.heads.length * RH + 2 * RH + 50 + 40 + Math.max(1, k.receipts.length) * 30 + 50; });
    const c = document.createElement('canvas'); c.width = W; c.height = H; const x = c.getContext('2d');
    const T = (t, X, Y, f, col, al) => { x.font = f; x.fillStyle = col || '#111'; x.textAlign = al || 'left'; x.fillText(String(t), X, Y); };
    const fit = (t, max, f) => { x.font = f; t = String(t); while (t.length > 3 && x.measureText(t).width > max) t = t.slice(0, -2); return t; };
    const box = (X, Y, w, h, bg, bd, r) => { x.beginPath(); x.roundRect ? x.roundRect(X, Y, w, h, r || 10) : x.rect(X, Y, w, h); x.fillStyle = bg; x.fill(); if (bd) { x.strokeStyle = bd; x.lineWidth = 2; x.stroke(); } };
    x.fillStyle = '#fff'; x.fillRect(0, 0, W, H);
    // header
    x.fillStyle = NAVY; x.fillRect(0, 0, W, 170);
    T((B.school || 'SN Mishra Singapore International School').toUpperCase(), W / 2, 62, 'bold 36px Arial', '#fff', 'center');
    T('Jagannathpur, Bhadohi (U.P.)', W / 2, 98, '22px Arial', '#DDE6F0', 'center');
    box(W / 2 - 250, 116, 500, 40, GOLD, null, 20); T('FEE DEMAND BILL · Session ' + B.ses, W / 2, 144, 'bold 22px Arial', '#1b1b1b', 'center');
    let y = 205; T('Bill date: ' + B.date, P, y, '20px Arial', '#333'); T('Payable till: ' + B.upto + ' ' + B.uptoYear, W - P, y, 'bold 20px Arial', '#333', 'right'); y += 20;
    if (kids.length > 1) { box(P, y, W - 2 * P, 76, '#FFF1F0', RED, 14); T('FAMILY TOTAL — ' + kids.length + ' children (same mobile number)', P + 20, y + 32, 'bold 22px Arial', '#333'); T('Payable till ' + B.upto + ': ' + rs(fam), P + 20, y + 62, 'bold 26px Arial', RED); y += 96; }
    kids.forEach((k, ki) => {
      y += 14; x.fillStyle = NAVY; x.fillRect(P, y, W - 2 * P, 4); y += 40;
      T((kids.length > 1 ? (ki + 1) + '. ' : '') + k.name, P, y, 'bold 32px Arial', NAVY); T('Class ' + k.cls + (k.section ? ' / ' + k.section : '') + ' · Scholar No ' + k.adm, W - P, y, 'bold 22px Arial', '#333', 'right'); y += 36;
      const info = [['Father', k.father], ['Mother', k.mother], ['Mobile', k.mobile], ['Transport', k.transport], ['Address', k.address || '—']];
      info.forEach(([a, b], i) => { const cx = i % 2 ? W / 2 + 10 : P, cy = y + Math.floor(i / 2) * 30; T(a + ':', cx, cy, 'bold 20px Arial', '#555'); T(fit(b || '—', i === 4 ? W - 2 * P - 110 : W / 2 - 140, '20px Arial'), cx + 110, cy, '20px Arial', '#111'); });
      y += 100;
      // summary boxes
      const t = k.total, S = [['Session fee', rs(t.fee), '#fff', '#9aa3b8', '#111'], ['Paid', rs(t.paid), '#fff', '#9aa3b8', GREEN], ['Concession', rs(t.conc), '#fff', '#9aa3b8', GREEN],
        ['School fee due', rs(t.nowFee), FEEBG, GOLD, '#8a5a00'], ['Transport due', rs(t.nowTr), '#E3F1FF', '#2b7bd0', '#0b4f9c'], ['PAYABLE TILL ' + B.upto.toUpperCase().slice(0, 3), rs(t.now), '#FFF3D6', RED, RED]];
      const bw = (W - 2 * P - 5 * 10) / 6; S.forEach(([l, v, bg, bd, col], i) => { const bx = P + i * (bw + 10); box(bx, y, bw, 100, bg, bd, 12); T(l, bx + bw / 2, y + 34, (i === 5 ? 'bold ' : '') + '16px Arial', '#444', 'center'); T(v, bx + bw / 2, y + 76, 'bold ' + (i === 5 ? 28 : 24) + 'px Arial', col, 'center'); });
      y += 130;
      // table
      const col = [P, P + 170, P + 500, P + 630, P + 770, P + 888], hd = ['Month', 'Fee head', 'Fee', 'Paid', 'Concession', 'Due'];   // right-aligned figures end at col + 120 (last = W − P − 2)
      x.fillStyle = NAVY; x.fillRect(P, y, W - 2 * P, 40); hd.forEach((h, i) => T(h, i < 2 ? col[i] + 10 : col[i] + 120, y + 27, 'bold 18px Arial', '#fff', i < 2 ? 'left' : 'right')); y += 40;
      let lastM = '', cutDone = false; const nowDue = k.total.now;
      k.heads.forEach((h, i) => {
        if (!h.now && !cutDone) { x.fillStyle = '#FFF3D6'; x.fillRect(P, y, W - 2 * P, RH); x.fillStyle = GOLD; x.fillRect(P, y, W - 2 * P, 2); x.fillRect(P, y + RH - 2, W - 2 * P, 2); T('Total due till ' + B.upto + ' (payable now)', col[5] - 20, y + 23, 'bold 18px Arial', RED, 'right'); T(rs(nowDue), col[5] + 120, y + 23, 'bold 18px Arial', RED, 'right'); y += RH; cutDone = true; }
        x.fillStyle = h.tr ? TRBG : FEEBG; x.fillRect(col[1], y, W - P - col[1], RH); x.fillStyle = '#F6F7FB'; x.fillRect(P, y, col[1] - P, RH);
        x.strokeStyle = '#E1E5EE'; x.lineWidth = 1; x.beginPath(); x.moveTo(P, y + RH); x.lineTo(W - P, y + RH); x.stroke();
        const g = h.now ? '#111' : '#888';
        if (h.m !== lastM) { T(h.m + (h.now ? '' : ' (aage)'), col[0] + 10, y + 23, 'bold 17px Arial', g); lastM = h.m; }
        T(h.name, col[1] + 10, y + 23, (h.tr ? 'bold ' : '') + '17px Arial', h.tr ? '#0b4f9c' : g);
        T(h.amt.toLocaleString('en-IN'), col[2] + 120, y + 23, '17px Arial', g, 'right'); T(h.paid ? h.paid.toLocaleString('en-IN') : '—', col[3] + 120, y + 23, '17px Arial', h.paid ? g : '#aaa', 'right');
        T(h.conc ? h.conc.toLocaleString('en-IN') : '—', col[4] + 120, y + 23, '17px Arial', h.conc ? GREEN : '#aaa', 'right'); T(h.due ? h.due.toLocaleString('en-IN') : '—', col[5] + 120, y + 23, (h.due && h.now ? 'bold ' : '') + '17px Arial', h.due ? (h.now ? RED : '#a05050') : '#aaa', 'right');
        y += RH;
      });
      if (!cutDone) { x.fillStyle = '#FFF3D6'; x.fillRect(P, y, W - 2 * P, RH); T('Total due till ' + B.upto + ' (payable now)', col[5] - 20, y + 23, 'bold 18px Arial', RED, 'right'); T(rs(nowDue), col[5] + 120, y + 23, 'bold 18px Arial', RED, 'right'); y += RH; }
      x.fillStyle = '#EEF1F8'; x.fillRect(P, y, W - 2 * P, RH); T('Session total' + (t.oldDue ? ' (incl. old due ' + rs(t.oldDue) + ')' : ''), col[1] + 10, y + 23, 'bold 17px Arial', '#111');
      [[2, t.fee], [3, t.paid], [4, t.conc], [5, t.due]].forEach(([i, v]) => T(Math.round(v).toLocaleString('en-IN'), col[i] + 120, y + 23, 'bold 17px Arial', '#111', 'right')); y += RH + 30;
      // receipts
      T('Payment history (receipts)', P, y, 'bold 22px Arial', NAVY); y += 14;
      if (!k.receipts.length) { y += 26; T('No payment received in this session.', P + 10, y, 'bold 18px Arial', RED); y += 10; }
      k.receipts.forEach(r => { y += 30; T('Receipt ' + r.no + ' · ' + dmy(r.date), P + 10, y, '18px Arial', '#333'); T(fit(r.lines.map(l => l.head + ' (' + l.months.replace(/,/g, ', ') + ')').join(' · '), 560, '16px Arial'), P + 300, y, '16px Arial', '#555'); T(rs(r.amt), W - P, y, 'bold 18px Arial', '#111', 'right'); });
      y += 30;
    });
    y += 10; box(P, y, W - 2 * P, 70, '#F6F7FB', NAVY, 12);
    T('Kripya ' + rs(fam) + ' (' + B.upto + ' tak ka baaki) jaldi se jaldi school office me jama karein aur receipt zaroor lein.', W / 2, y + 30, 'bold 19px Arial', '#111', 'center');
    T('Kisi bhi galti ke liye school office se sampark karein.', W / 2, y + 56, '17px Arial', '#444', 'center'); y += 110;
    T('Accountant ____________', P + 20, y, '18px Arial', '#333'); T('Principal ____________', W - P - 20, y, '18px Arial', '#333', 'right');
    y += 40; T('Data as per school fee record' + (B.live ? '' : ' (old ERP copy ' + B.asOn + ')') + ' · Powered by Maakit', W / 2, y, '15px Arial', '#888', 'center');
    const out = document.createElement('canvas'); out.width = W; out.height = Math.min(H, y + 24); out.getContext('2d').drawImage(c, 0, 0);   // trim the spare space at the bottom
    return out;
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
