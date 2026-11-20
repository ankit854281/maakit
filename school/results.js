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
  // ---- certificate design v2 (owner, 20 Nov 2026: "education symbol, student ki image badi, flower jaisa kuchh — attractive")
  let FONTS = null;
  function fonts() {
    if (!FONTS) FONTS = Promise.all([['GreatVibes', 'fonts/great-vibes.woff2', '400'], ['Poppins', 'fonts/poppins-latin-400-normal.woff2', '400'], ['Poppins', 'fonts/poppins-latin-600-normal.woff2', '600'], ['Poppins', 'fonts/poppins-latin-700-normal.woff2', '700']]
      .map(([f, u, w]) => window.FontFace ? new FontFace(f, `url(${u})`, { weight: w }).load().then(x => document.fonts.add(x)).catch(() => {}) : 0));
    return FONTS;
  }
  const PF = 'Poppins, Arial, sans-serif', SF = 'GreatVibes, "Brush Script MT", cursive';
  const PAL = ['', { a: '#E0A100', b: '#FFD54A', c: '#8A5A00', petals: ['#FF7AA2', '#FFB347', '#FFD54A', '#FF7AA2', '#C77DFF', '#FFB347'] },
    { a: '#7E8A97', b: '#D9DEE5', c: '#3F4954', petals: ['#7FB3FF', '#B8C7FF', '#9EE6D0', '#7FB3FF', '#C9B8FF', '#9EE6D0'] },
    { a: '#B5652B', b: '#F2B27A', c: '#6B3410', petals: ['#FF9A76', '#FFC9A3', '#FFD27D', '#FF9A76', '#F7A1C4', '#FFC9A3'] }];
  function flower(x, cx, cy, r, cols, n) {   // simple 6–8 petal flower
    n = n || 6; x.save(); x.translate(cx, cy);
    for (let i = 0; i < n; i++) { x.save(); x.rotate(i * Math.PI * 2 / n); x.beginPath(); x.ellipse(0, -r * .62, r * .36, r * .62, 0, 0, 7); x.fillStyle = cols[i % cols.length]; x.globalAlpha = .9; x.fill(); x.restore(); }
    x.globalAlpha = 1; x.beginPath(); x.arc(0, 0, r * .28, 0, 7); x.fillStyle = '#FFE066'; x.fill(); x.beginPath(); x.arc(0, 0, r * .14, 0, 7); x.fillStyle = '#F4A300'; x.fill(); x.restore();
  }
  function leaf(x, cx, cy, l, ang, col) { x.save(); x.translate(cx, cy); x.rotate(ang); x.beginPath(); x.moveTo(0, 0); x.quadraticCurveTo(l * .5, -l * .32, l, 0); x.quadraticCurveTo(l * .5, l * .32, 0, 0); x.fillStyle = col || '#4CAF7A'; x.fill(); x.restore(); }
  function star(x, cx, cy, r, col) { x.save(); x.beginPath(); for (let i = 0; i < 10; i++) { const a = -Math.PI / 2 + i * Math.PI / 5, rr = i % 2 ? r * .45 : r; x.lineTo(cx + Math.cos(a) * rr, cy + Math.sin(a) * rr); } x.closePath(); x.fillStyle = col; x.fill(); x.restore(); }
  function cap(x, cx, cy, s, col) { x.save(); x.translate(cx, cy); x.fillStyle = col; x.beginPath(); x.moveTo(-s, 0); x.lineTo(0, -s * .45); x.lineTo(s, 0); x.lineTo(0, s * .45); x.closePath(); x.fill();
    x.fillRect(-s * .55, s * .1, s * 1.1, s * .42); x.strokeStyle = '#F5C842'; x.lineWidth = s * .07; x.beginPath(); x.moveTo(s * .7, 0); x.lineTo(s * .7, s * .7); x.stroke(); x.fillStyle = '#F5C842'; x.beginPath(); x.arc(s * .7, s * .75, s * .1, 0, 7); x.fill(); x.restore(); }
  function book(x, cx, cy, s, c1, c2) { x.save(); x.translate(cx, cy); x.fillStyle = c1; x.beginPath(); x.moveTo(0, -s * .1); x.quadraticCurveTo(-s * .5, -s * .45, -s, -s * .3); x.lineTo(-s, s * .45); x.quadraticCurveTo(-s * .5, s * .3, 0, s * .6); x.closePath(); x.fill();
    x.fillStyle = c2; x.beginPath(); x.moveTo(0, -s * .1); x.quadraticCurveTo(s * .5, -s * .45, s, -s * .3); x.lineTo(s, s * .45); x.quadraticCurveTo(s * .5, s * .3, 0, s * .6); x.closePath(); x.fill();
    x.strokeStyle = 'rgba(255,255,255,.7)'; x.lineWidth = s * .04; for (let i = 1; i <= 3; i++) { x.beginPath(); x.moveTo(-s * .85, -s * .2 + i * s * .17); x.quadraticCurveTo(-s * .45, -s * .3 + i * s * .17, -s * .1, -s * .05 + i * s * .17); x.stroke(); x.beginPath(); x.moveTo(s * .85, -s * .2 + i * s * .17); x.quadraticCurveTo(s * .45, -s * .3 + i * s * .17, s * .1, -s * .05 + i * s * .17); x.stroke(); } x.restore(); }
  function pencil(x, cx, cy, l, ang) { x.save(); x.translate(cx, cy); x.rotate(ang); const w = l * .14; x.fillStyle = '#FFC93C'; x.fillRect(0, -w / 2, l * .78, w); x.fillStyle = '#F28C9B'; x.fillRect(-l * .1, -w / 2, l * .1, w); x.fillStyle = '#C9C9C9'; x.fillRect(-l * .02, -w / 2, l * .04, w);
    x.fillStyle = '#F3D6A4'; x.beginPath(); x.moveTo(l * .78, -w / 2); x.lineTo(l, 0); x.lineTo(l * .78, w / 2); x.closePath(); x.fill(); x.fillStyle = '#333'; x.beginPath(); x.moveTo(l * .93, -w * .15); x.lineTo(l, 0); x.lineTo(l * .93, w * .15); x.closePath(); x.fill(); x.restore(); }
  function trophy(x, cx, cy, s, P) { x.save(); x.translate(cx, cy); x.fillStyle = P.a; x.beginPath(); x.moveTo(-s * .6, -s); x.lineTo(s * .6, -s); x.quadraticCurveTo(s * .6, -s * .1, 0, s * .05); x.quadraticCurveTo(-s * .6, -s * .1, -s * .6, -s); x.fill();
    x.lineWidth = s * .12; x.strokeStyle = P.a; x.beginPath(); x.arc(-s * .62, -s * .7, s * .25, Math.PI * .5, Math.PI * 1.5); x.stroke(); x.beginPath(); x.arc(s * .62, -s * .7, s * .25, -Math.PI * .5, Math.PI * .5); x.stroke();
    x.fillRect(-s * .1, 0, s * .2, s * .35); x.fillStyle = P.c; x.fillRect(-s * .42, s * .35, s * .84, s * .22); x.fillStyle = P.b; x.beginPath(); x.ellipse(-s * .2, -s * .7, s * .1, s * .2, -.3, 0, 7); x.fill(); x.restore(); }
  function globe(x, cx, cy, r) { x.save(); x.beginPath(); x.arc(cx, cy, r, 0, 7); x.fillStyle = '#5DADE2'; x.fill(); x.fillStyle = '#58D68D'; x.beginPath(); x.ellipse(cx - r * .3, cy - r * .2, r * .35, r * .25, .4, 0, 7); x.fill(); x.beginPath(); x.ellipse(cx + r * .35, cy + r * .3, r * .3, r * .2, -.3, 0, 7); x.fill();
    x.strokeStyle = 'rgba(255,255,255,.6)'; x.lineWidth = r * .06; x.beginPath(); x.ellipse(cx, cy, r * .45, r, 0, 0, 7); x.stroke(); x.beginPath(); x.moveTo(cx - r, cy); x.lineTo(cx + r, cy); x.stroke(); x.restore(); }
  async function certCanvas(k, D) {
    await fonts();
    const W = 1754, H = 1240, c = document.createElement('canvas'); c.width = W; c.height = H; const x = c.getContext('2d'), P = PAL[k.rank] || PAL[3];
    const T = (t, X, Y, f, col, al) => { x.font = f; x.fillStyle = col || '#1d2733'; x.textAlign = al || 'center'; x.fillText(String(t), X, Y); };
    // background: cream, soft rays, faint education icons
    const bg = x.createRadialGradient(W * .62, H * .45, 80, W * .62, H * .45, W * .8); bg.addColorStop(0, '#FFFDF5'); bg.addColorStop(1, '#FFF1D6'); x.fillStyle = bg; x.fillRect(0, 0, W, H);
    x.save(); x.globalAlpha = .07; [[300, 900], [1500, 300], [1450, 980], [820, 1080], [1150, 160]].forEach(([a, b], i) => [() => cap(x, a, b, 70, '#17324D'), () => book(x, a, b, 70, '#17324D', '#17324D'), () => globe(x, a, b, 55), () => pencil(x, a - 60, b, 140, -.5), () => star(x, a, b, 50, '#17324D')][i]()); x.restore();
    for (let i = 0; i < 60; i++) { const a = (i * 137.5) % 360, rx = 80 + ((i * 97) % (W - 160)), ry = 80 + ((i * 53) % (H - 160)); if (ry > 260 && ry < 1000 && rx > 520 && rx < 1680) continue; star(x, rx, ry, 5 + (i % 4) * 2, ['#F5C842', '#FF7AA2', '#7FB3FF', '#58D68D'][i % 4]); }
    // frame
    x.lineWidth = 16; x.strokeStyle = '#17324D'; x.strokeRect(28, 28, W - 56, H - 56); x.lineWidth = 5; x.strokeStyle = P.a; x.strokeRect(54, 54, W - 108, H - 108);
    x.setLineDash([2, 12]); x.lineCap = 'round'; x.lineWidth = 4; x.strokeStyle = P.a; x.strokeRect(70, 70, W - 140, H - 140); x.setLineDash([]);
    // flower corners
    [[70, 70, 0], [W - 70, 70, Math.PI / 2], [W - 70, H - 70, Math.PI], [70, H - 70, -Math.PI / 2]].forEach(([cx, cy, r]) => { x.save(); x.translate(cx, cy); x.rotate(r);
      leaf(x, 20, 20, 110, .25); leaf(x, 20, 20, 110, 1.3); flower(x, 40, 40, 58, P.petals, 8); flower(x, 135, 22, 30, P.petals.slice(2), 6); flower(x, 22, 135, 30, P.petals.slice(1), 6); x.restore(); });
    // header ribbon
    const logo = await loadImg('img/snmsis-logo.png');
    x.fillStyle = '#17324D'; x.beginPath(); x.moveTo(330, 95); x.lineTo(W - 330, 95); x.lineTo(W - 300, 155); x.lineTo(W - 330, 215); x.lineTo(330, 215); x.lineTo(300, 155); x.closePath(); x.fill();
    if (logo) { x.save(); x.beginPath(); x.arc(400, 155, 52, 0, 7); x.fillStyle = '#fff'; x.fill(); x.clip(); x.drawImage(logo, 348, 103, 104, 104); x.restore(); }
    T(SCHOOL.toUpperCase(), W / 2 + 70, 162, `700 36px ${PF}`, '#fff'); T(PLACE, W / 2 + 70, 198, `400 22px ${PF}`, '#DDE6F0');
    // left: student photo inside a big flower
    const px = 430, py = 640, pr = 175;
    for (let i = 0; i < 12; i++) { x.save(); x.translate(px, py); x.rotate(i * Math.PI / 6); x.beginPath(); x.ellipse(0, -pr - 22, 46, 70, 0, 0, 7); x.fillStyle = P.petals[i % P.petals.length]; x.globalAlpha = .95; x.fill(); x.restore(); }
    x.globalAlpha = 1; leaf(x, px - 150, py + 200, 120, 2.4); leaf(x, px + 150, py + 200, 120, .75);
    x.beginPath(); x.arc(px, py, pr + 14, 0, 7); x.fillStyle = P.a; x.fill(); x.beginPath(); x.arc(px, py, pr + 4, 0, 7); x.fillStyle = '#fff'; x.fill();
    const ph = await loadImg(await photo(k.st.id));
    x.save(); x.beginPath(); x.arc(px, py, pr, 0, 7); x.clip();
    if (ph) { const sc = Math.max(2 * pr / ph.width, 2 * pr / ph.height); x.drawImage(ph, px - ph.width * sc / 2, py - ph.height * sc / 2, ph.width * sc, ph.height * sc); }
    else { const g = x.createLinearGradient(px, py - pr, px, py + pr); g.addColorStop(0, '#E8F0FF'); g.addColorStop(1, '#CFE0FF'); x.fillStyle = g; x.fillRect(px - pr, py - pr, 2 * pr, 2 * pr); T(k.st.name.split(/\s+/).map(w => w[0]).slice(0, 2).join('').toUpperCase(), px, py + 40, `700 110px ${PF}`, '#17324D'); }
    x.restore();
    // rank ribbon under the photo
    x.fillStyle = '#C0392B'; x.beginPath(); x.moveTo(px - 150, py + pr + 40); x.lineTo(px + 150, py + pr + 40); x.lineTo(px + 130, py + pr + 75); x.lineTo(px + 150, py + pr + 110); x.lineTo(px - 150, py + pr + 110); x.lineTo(px - 130, py + pr + 75); x.closePath(); x.fill();
    T(`${k.rank}${['', 'st', 'nd', 'rd'][k.rank]} RANK`, px, py + pr + 92, `700 38px ${PF}`, '#fff');
    // education symbols around the photo
    cap(x, px - 205, py - 215, 56, '#17324D'); book(x, px + 215, py - 205, 58, '#E74C3C', '#3498DB'); pencil(x, px - 270, py + 30, 120, -1.2); globe(x, px + 250, py + 40, 40);
    // right: text
    const tx = 1120;
    trophy(x, tx, 330, 70, P); star(x, tx - 110, 285, 18, P.a); star(x, tx + 110, 285, 18, P.a);
    T('Certificate of Excellence', tx, 470, `92px ${SF}`, P.c);
    T('PROUDLY PRESENTED TO', tx, 535, `600 26px ${PF}`, '#5b6573');
    x.font = `110px ${SF}`; let nm = k.st.name; while (x.measureText(nm).width > 860 && nm.length > 4) nm = nm.slice(0, -1);
    T(nm, tx, 650, `110px ${SF}`, '#17324D');
    const lg = x.createLinearGradient(tx - 380, 0, tx + 380, 0); lg.addColorStop(0, 'rgba(0,0,0,0)'); lg.addColorStop(.5, P.a); lg.addColorStop(1, 'rgba(0,0,0,0)'); x.fillStyle = lg; x.fillRect(tx - 380, 675, 760, 4);
    T(`${k.st.father ? 'Ward of Shri ' + k.st.father + '  ·  ' : ''}Class ${k.st.cls}${k.st.section ? '-' + k.st.section : ''}`, tx, 722, `400 28px ${PF}`, '#333');
    T(`for securing ${ORD[k.rank]} POSITION in the class`, tx, 800, `700 38px ${PF}`, P.c);
    T(`in the Half-Yearly Examination ${SESL} with ${k.pct}% marks`, tx, 852, `400 30px ${PF}`, '#333');
    T('Keep learning, keep shining!', tx, 920, `56px ${SF}`, '#C0392B');
    // signatures
    const sy = 1075; x.strokeStyle = '#333'; x.lineWidth = 2;
    [[tx - 300, 'Class Teacher', D.ct], [tx, 'Date', new Date().toLocaleDateString('en-IN')], [tx + 300, 'Principal', '']].forEach(([cx, l, v], i) => {
      if (i === 1) { T(v, cx, sy - 10, `600 26px ${PF}`, '#333'); } else { x.beginPath(); x.moveTo(cx - 125, sy); x.lineTo(cx + 125, sy); x.stroke(); if (v) T(v, cx, sy - 14, `36px ${SF}`, '#17324D'); }
      T(l, cx, sy + 36, `600 22px ${PF}`, '#5b6573'); });
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
