'use strict';
// Half-Yearly (Term-1) marks slips + rankers' certificates — owner, 20 Nov 2026.
//  • Slip: every subject's Pre. Test (10) · Notebook (5) · SEA (5) · Term Exam (80) = 100, grade (CBSE 9-point), total, %, rank.
//  • Grace (owner chose "fail ko pass tak grace, khule me dikhe"): a subject between 28 and 32 (not absent) gets grace up to 33,
//    shown in its own column and in the note — the real marks are never changed. Rank and the rankers use the REAL marks.
//  • Rankers: 1st, 2nd, 3rd of the class (all subjects entered) → certificate with the child's photo (canvas → share / download / print).
// Data: progress_api.php?a=marks&ses=&exam=Term-1&cls= (students, subjects, marks), ?a=photos&sid= (photo). Read only.
(function () {
  const $ = id => document.getElementById(id);
  const esc = s => String(s == null ? '' : s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
  const CLASSES = ['PG', 'LKG', 'UKG', '1st', '2nd', '3rd', '4th', '5th', '6th', '7th', '8th', '9th', '10th'];
  const PARTS = [['pt', 'Pre. Test', 10], ['nb', 'Notebook', 5], ['sea', 'SEA', 5], ['te', 'Term Exam', 80]];
  const PASS = 33, GRACE_MAX = 5;
  const now = new Date(), y0 = now.getMonth() >= 3 ? now.getFullYear() : now.getFullYear() - 1, SES = y0 + '-' + String(y0 + 1).slice(2), SESL = y0 + '-' + (y0 + 1);
  const SCHOOL = 'SN Mishra Singapore International School', PLACE = 'Jagannathpur, Bhadohi (U.P.)';
  const tok = () => { try { return localStorage.getItem('sch_token') || ''; } catch (e) { return ''; } };
  async function get(path) {
    const r = await fetch(path, { cache: 'no-store', headers: { 'X-School-Token': tok() } }); let j = null; try { j = await r.json(); } catch (e) {}
    if (r.status === 401) throw Error('Please log in to the ERP first, then open this page again.');
    if (!r.ok || !j || !j.ok) throw Error((j && j.error) || 'Server problem. Please try again.'); return j;
  }
  const grade = p => p >= 91 ? 'A1' : p >= 81 ? 'A2' : p >= 71 ? 'B1' : p >= 61 ? 'B2' : p >= 51 ? 'C1' : p >= 41 ? 'C2' : p >= 33 ? 'D' : 'E';
  const n2 = v => Math.round(v * 100) / 100;
  let CLS = (location.hash.match(/cls=([^&]+)/) || [])[1] || '1st', TAB = 'slip', DATA = {}, PH = {};
  CLS = decodeURIComponent(CLS);

  // ---------- one class: every child's subjects, totals, grace, rank ----------
  function build(d) {
    const bySub = {}; Object.values(d.marks || {}).forEach(m => { bySub[m.subject] = m.rows || {}; });
    // every subject that has marks — the timetable order first, then subjects with marks that are not in the timetable
    // (owner, 20 Nov 2026: "class 9th me hindi ka marks show nahi kar raha" — Hindi had marks but no timetable entry)
    const order = [...d.subjects, ...Object.keys(bySub).filter(s => !d.subjects.includes(s)).sort()];
    const subs = order.filter(s => bySub[s] && Object.keys(bySub[s]).length);
    const kids = d.students.map(st => {
      let tot = 0, max = 0, gTot = 0; const rows = [];
      subs.forEach(s => { const m = (bySub[s] || {})[st.id]; if (!m) { rows.push({ s, none: true }); return; }
        const vals = PARTS.map(([k]) => m[k]), has = vals.some(v => v !== '' && v != null); if (!has) { rows.push({ s, none: true }); return; }
        const allAb = vals.every(v => v === 'AB' || v === '' || v == null) && vals.some(v => v === 'AB');
        const t = vals.reduce((a, v) => a + (typeof v === 'number' ? v : 0), 0), g = !allAb && t < PASS && t >= PASS - GRACE_MAX ? Math.ceil(PASS - t) : 0;
        tot += t; max += 100; gTot += g; rows.push({ s, vals, t: n2(t), g, ab: allAb });
      });
      const pct = max ? n2(tot * 100 / max) : null, done = rows.filter(r => !r.none).length;
      return { st, rows, tot: n2(tot), max, gTot, pct, done };
    });
    const full = Math.max(0, ...kids.map(k => k.done));
    const ranked = kids.filter(k => k.pct != null && k.done === full && full > 0).sort((a, b) => b.pct - a.pct);
    let last = null, r = 0; ranked.forEach((k, i) => { if (last === null || k.pct < last) { r = i + 1; last = k.pct; } k.rank = r; });
    return { subs, kids, ranked, full, n: kids.length };
  }
  async function load(c) {
    if (DATA[c]) return DATA[c];
    const d = await get(`progress_api.php?a=marks&ses=${SES}&exam=Term-1&cls=${encodeURIComponent(c)}`);
    return (DATA[c] = Object.assign(build(d), { ct: d.classTeacher || '' }));
  }
  function bar() {
    $('bar').innerHTML = `<div class="row"><b>Class</b><select id="cls">${CLASSES.map(c => `<option ${c === CLS ? 'selected' : ''}>${c}</option>`).join('')}</select>
      <div class="tabs"><button class="${TAB === 'slip' ? 'on' : ''}" data-t="slip">Marks slips</button><button class="${TAB === 'rank' ? 'on' : ''}" data-t="rank">Rankers' certificates</button></div>
      ${TAB === 'slip' ? '<button class="btn y" id="pall">🖨 Print all slips</button>' : ''}</div>
      <p class="note">Half-Yearly ${SESL}: Pre. Test 10 + Notebook 5 + SEA 5 + Term Exam 80 = 100 per subject. Grace: a subject from ${PASS - GRACE_MAX} to ${PASS - 1} gets grace up to ${PASS} (pass), shown separately; rank uses the real marks.</p>`;
    $('cls').onchange = e => { CLS = e.target.value; location.hash = 'cls=' + encodeURIComponent(CLS); draw(); };
    document.querySelectorAll('[data-t]').forEach(b => { b.onclick = () => { TAB = b.dataset.t; draw(); }; });
    if ($('pall')) $('pall').onclick = () => window.print();
  }
  async function draw() {
    bar(); $('out').innerHTML = '<div class="pan msg">Opening class ' + esc(CLS) + '…</div>';
    let D; try { D = await load(CLS); } catch (e) { $('out').innerHTML = `<div class="pan msg">${esc(e.message)}</div>`; return; }
    if (!D.subs.length) { $('out').innerHTML = '<div class="pan msg">No Half-Yearly marks are entered for this class yet.</div>'; return; }
    if (TAB === 'slip') slips(D); else rankers(D);
  }
  // ---------- slips ----------
  function slipHtml(D, k) {
    const st = k.st, rk = k.rank ? k.rank + ' of ' + D.n : '—';
    return `<div class="slip"><div class="sh"><b>${SCHOOL.toUpperCase()}</b><span>${PLACE}</span><br><i>HALF-YEARLY EXAMINATION ${SESL} · MARKS SLIP</i></div>
      <div class="info"><div>Name: <b>${esc(st.name)}</b></div><div>Class: <b>${esc(st.cls)}${st.section ? '-' + esc(st.section) : ''}</b></div><div>Father: <b>${esc(st.father || '—')}</b></div><div>Adm. No.: <b>${esc(st.adm || '—')}</b></div></div>
      <table class="m"><tr><th style="text-align:left">Subject</th>${PARTS.map(([, l, m]) => `<th>${l}<br>(${m})</th>`).join('')}<th>Total<br>(100)</th><th>Grace</th><th>Grade</th></tr>
      ${k.rows.map(r => r.none ? `<tr><td class="s">${esc(r.s)}</td><td colspan="7">Not entered</td></tr>` : `<tr><td class="s">${esc(r.s)}</td>${r.vals.map(v => `<td>${v === '' || v == null ? '—' : esc(v)}</td>`).join('')}<td><b class="${r.t + r.g < PASS ? 'red' : ''}">${r.ab ? 'AB' : r.t}</b></td><td>${r.g ? `<span class="gr">+${r.g}</span>` : '—'}</td><td>${r.ab ? '—' : grade(r.t + r.g)}</td></tr>`).join('')}
      <tr class="t"><td class="s">Total</td><td colspan="4"></td><td>${k.tot} / ${k.max}</td><td>${k.gTot ? '+' + k.gTot : '—'}</td><td>${k.pct != null ? grade(k.pct) : '—'}</td></tr></table>
      <div class="sum"><span>Percentage: <b>${k.pct != null ? k.pct + '%' : '—'}</b></span><span>Rank in class: <b>${rk}</b></span><span>Result: <b>${k.rows.some(r => !r.none && !r.ab && r.t + r.g < PASS) ? '<span class="red">Needs improvement</span>' : 'Pass'}</b></span></div>
      ${k.gTot ? `<p class="gn">Grace marks (+${k.gTot}) are given only to reach the pass mark (${PASS}) and are shown separately; total, percentage and rank are on the real marks.</p>` : ''}
      <p class="gn">The full report card will be given with the final result.</p>
      <div class="foot"><div><i>Class Teacher${D.ct ? ' (' + esc(D.ct) + ')' : ''}</i></div><div><i>Parent's Signature</i></div><div><i>Principal</i></div></div></div>`;
  }
  function slipText(D, k) {
    return `*${SCHOOL}*\nHalf-Yearly ${SESL} · Marks\n*${k.st.name}* (Class ${k.st.cls})\n\n` + k.rows.filter(r => !r.none).map(r => `${r.s}: ${r.ab ? 'AB' : r.t + '/100'}${r.g ? ' (grace +' + r.g + ')' : ''} — ${r.ab ? '-' : grade(r.t + r.g)}`).join('\n') +
      `\n\nTotal: ${k.tot}/${k.max} · ${k.pct != null ? k.pct + '%' : '-'}${k.rank ? ' · Rank ' + k.rank + ' of ' + D.n : ''}\nThe full report card will be given with the final result.`;
  }
  function slips(D) {
    const kids = D.kids.slice().sort((a, b) => a.st.name.localeCompare(b.st.name));
    $('out').innerHTML = `<div class="pan np"><b>${kids.length} slips</b> · class ${esc(CLS)} · ${D.subs.length} subjects · ${D.kids.filter(k => k.gTot).length} child(ren) with grace. "Print all slips" prints every slip (about 2 on a page).</div>` +
      kids.map((k, i) => slipHtml(D, k) + `<div class="slipbar">${(k.st.phone || '').replace(/\D/g, '').slice(-10).length === 10 ? `<a class="btn g" target="_blank" rel="noopener" href="https://wa.me/91${(k.st.phone || '').replace(/\D/g, '').slice(-10)}?text=${encodeURIComponent(slipText(D, k))}">WhatsApp to parent</a>` : ''}<button class="btn l" data-p="${i}">Print this slip</button></div>`).join('');
    document.querySelectorAll('[data-p]').forEach(b => { b.onclick = () => { const w = window.open('', '_blank'); if (!w) return alert('Allow pop-ups to print.');
      w.document.write(`<!doctype html><html><head><title>Marks slip</title>${document.querySelector('style').outerHTML}</head><body>${slipHtml(D, kids[+b.dataset.p])}<script>print()<\/script></body></html>`); w.document.close(); }; });
  }
  // ---------- rankers' certificates ----------
  const ORD = ['', 'FIRST', 'SECOND', 'THIRD'], MEDAL = ['', ['#F5C842', '#B8860B'], ['#D9DDE3', '#8E959E'], ['#E3A06B', '#A0582A']];
  function loadImg(src) { return new Promise(ok => { if (!src) return ok(null); const i = new Image(); i.onload = () => ok(i); i.onerror = () => ok(null); i.src = src; }); }
  async function photo(sid) { if (PH[sid] !== undefined) return PH[sid]; try { const j = await get('progress_api.php?a=photos&sid=' + encodeURIComponent(sid)); PH[sid] = j.photos[sid] || null; } catch (e) { PH[sid] = null; } return PH[sid]; }
  async function certCanvas(k, D) {
    const W = 1754, H = 1240, c = document.createElement('canvas'); c.width = W; c.height = H; const x = c.getContext('2d');
    const T = (t, X, Y, f, col, al) => { x.font = f; x.fillStyle = col || '#111'; x.textAlign = al || 'center'; x.fillText(String(t), X, Y); };
    const [m1, m2] = MEDAL[k.rank] || MEDAL[3];
    x.fillStyle = '#FFFDF6'; x.fillRect(0, 0, W, H);
    x.strokeStyle = '#17324D'; x.lineWidth = 18; x.strokeRect(30, 30, W - 60, H - 60); x.strokeStyle = m1; x.lineWidth = 6; x.strokeRect(62, 62, W - 124, H - 124);
    const logo = await loadImg('img/snmsis-logo.png'); if (logo) x.drawImage(logo, 105, 95, 125, 125);
    T(SCHOOL.toUpperCase(), W / 2 + 60, 160, 'bold 46px Georgia, serif', '#17324D'); T(PLACE, W / 2 + 60, 205, '26px Georgia, serif', '#555');
    T('Certificate of Merit', W / 2, 330, 'italic bold 92px Georgia, serif', '#8A5A00');
    T('This certificate is proudly presented to', W / 2, 420, '32px Georgia, serif', '#333');
    // photo (right) and medal (left)
    const ph = await loadImg(await photo(k.st.id)), px = W - 330, py = 560, pr = 150;
    x.save(); x.beginPath(); x.arc(px, py, pr + 12, 0, 7); x.fillStyle = m1; x.fill(); x.beginPath(); x.arc(px, py, pr, 0, 7); x.clip();
    if (ph) { const s = Math.max(2 * pr / ph.width, 2 * pr / ph.height); x.drawImage(ph, px - ph.width * s / 2, py - ph.height * s / 2, ph.width * s, ph.height * s); }
    else { x.fillStyle = '#E8EDF3'; x.fillRect(px - pr, py - pr, 2 * pr, 2 * pr); T(k.st.name.split(/\s+/).map(w => w[0]).slice(0, 2).join('').toUpperCase(), px, py + 30, 'bold 90px Arial', '#17324D'); }
    x.restore();
    const mx = 330, my = 560; x.fillStyle = '#C0392B'; x.beginPath(); x.moveTo(mx - 60, my - 190); x.lineTo(mx - 10, my - 60); x.lineTo(mx + 10, my - 60); x.lineTo(mx + 60, my - 190); x.closePath(); x.fill();
    x.beginPath(); x.arc(mx, my, 120, 0, 7); x.fillStyle = m2; x.fill(); x.beginPath(); x.arc(mx, my, 102, 0, 7); x.fillStyle = m1; x.fill();
    T(k.rank, mx, my + 20, 'bold 96px Georgia, serif', '#3a2a00'); T(['', 'st', 'nd', 'rd'][k.rank] || 'th', mx + 56, my - 22, 'bold 34px Georgia, serif', '#3a2a00');
    // name + line
    x.font = 'bold 84px Georgia, serif'; let nm = k.st.name; while (x.measureText(nm).width > 700 && nm.length > 4) nm = nm.slice(0, -1);
    T(nm, W / 2, 545, 'bold 84px Georgia, serif', '#17324D'); x.strokeStyle = m2; x.lineWidth = 3; x.beginPath(); x.moveTo(W / 2 - 360, 570); x.lineTo(W / 2 + 360, 570); x.stroke();
    T(`${k.st.father ? 'Ward of Shri ' + k.st.father + ' · ' : ''}Class ${k.st.cls}${k.st.section ? '-' + k.st.section : ''}`, W / 2, 630, '32px Georgia, serif', '#333');
    T(`for securing ${ORD[k.rank]} POSITION in the class`, W / 2, 720, 'bold 44px Georgia, serif', '#8A5A00');
    T(`in the Half-Yearly Examination ${SESL} with ${k.pct}% marks.`, W / 2, 780, '34px Georgia, serif', '#333');
    T('Keep shining — we are proud of you!', W / 2, 850, 'italic 32px Georgia, serif', '#555');
    // signs + date
    const sy = 1060; x.strokeStyle = '#333'; x.lineWidth = 2;
    [[340, 'Class Teacher' + (D.ct ? ' — ' + D.ct : '')], [W / 2, 'Date: ' + new Date().toLocaleDateString('en-IN')], [W - 340, 'Principal']].forEach(([cx, l], i) => { if (i !== 1) { x.beginPath(); x.moveTo(cx - 170, sy); x.lineTo(cx + 170, sy); x.stroke(); } T(l, cx, sy + 42, '28px Georgia, serif', '#333'); });
    return c;
  }
  async function rankers(D) {
    const top = D.ranked.filter(k => k.rank <= 3);
    if (!top.length) { $('out').innerHTML = '<div class="pan msg">No child has every subject entered yet — rankers come when all marks of the class are in.</div>'; return; }
    $('out').innerHTML = `<div class="pan np"><b>Class ${esc(CLS)} rankers</b> · on the real marks (no grace) · ${D.full} subjects each. <button class="btn y" id="cprint">🖨 Print all certificates</button></div><div class="certs" id="cl"></div>`;
    const urls = [];
    for (const k of top) {
      const c = await certCanvas(k, D), url = c.toDataURL('image/jpeg', 0.92); urls.push(url);
      const d = document.createElement('div'); d.className = 'cert';
      d.innerHTML = `<h4>${k.rank}${['', 'st', 'nd', 'rd'][k.rank]} · ${esc(k.st.name)} · ${k.pct}%</h4><img src="${url}" alt="Certificate"><div class="row" style="margin-top:8px"><button class="btn g" data-s>Share</button><a class="btn l" download="Certificate-${esc(k.st.name.replace(/\W+/g, '-'))}.jpg" href="${url}">Download</a></div>`;
      d.querySelector('[data-s]').onclick = () => share(c, 'Certificate-' + k.st.name.replace(/\W+/g, '-') + '.png', `${k.st.name} — ${ORD[k.rank]} position, Class ${k.st.cls}, Half-Yearly ${SESL}`);
      d.querySelector('img').onclick = () => { const z = window.open(''); if (z) { z.document.write(`<img src="${url}" style="width:100%">`); z.document.close(); } };
      $('cl').appendChild(d);
    }
    $('cprint').onclick = () => { const w = window.open('', '_blank'); if (!w) return alert('Allow pop-ups to print.');
      w.document.write(`<!doctype html><html><head><title>Certificates</title><style>@page{size:A4 landscape;margin:0}body{margin:0}img{width:100%;display:block;page-break-after:always}</style></head><body>${urls.map(u => `<img src="${u}">`).join('')}<script>setTimeout(print,600)<\/script></body></html>`); w.document.close(); };
  }
  async function share(c, name, text) {
    const b64 = c.toDataURL('image/png').split(',')[1];
    if (window.SNMSISFiles && typeof SNMSISFiles.shareFile === 'function') return SNMSISFiles.shareFile(name, b64, 'image/png');
    if (window.SNMSISFiles && typeof SNMSISFiles.shareImage === 'function') return SNMSISFiles.shareImage(name, b64);
    try { const blob = await new Promise(r => c.toBlob(r, 'image/png')), f = new File([blob], name, { type: 'image/png' });
      if (navigator.canShare && navigator.canShare({ files: [f] })) return await navigator.share({ files: [f], text }); } catch (e) { if (e && e.name === 'AbortError') return; }
    const a = document.createElement('a'); a.href = c.toDataURL('image/png'); a.download = name; document.body.appendChild(a); a.click(); a.remove();
  }
  draw();
})();
