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
  const SF = 'GreatVibes, "Brush Script MT", cursive';
  // ---- certificate v3 (owner, 20 Nov 2026: v2 "achha nahi laga — educators ka rankers wala feel nahi aa raha"): a formal academic
  // merit certificate — ivory paper, navy + gold border with corner ornaments, crest watermark, gold laurel wreath around the
  // child's photo, a gold rosette seal with the rank, serif capitals + script name, signatures of Class Teacher / Principal / Chairman.
  const GOLD = ['#8C6A1E', '#D9B45A', '#F6E3A1', '#B8902F'];
  function gold(x, x0, y0, x1, y1) { const g = x.createLinearGradient(x0, y0, x1, y1); g.addColorStop(0, GOLD[0]); g.addColorStop(.35, GOLD[2]); g.addColorStop(.55, GOLD[1]); g.addColorStop(1, GOLD[0]); return g; }
  function laurel(x, cx, cy, r, side, col) {   // one branch of a laurel wreath along an arc (side -1 left, +1 right)
    x.save(); x.translate(cx, cy); x.strokeStyle = col; x.fillStyle = col; x.lineWidth = 5;
    const a0 = Math.PI * .58, a1 = Math.PI * 1.42; x.beginPath();
    for (let t = 0; t <= 1.0001; t += .02) { const a = a0 + (a1 - a0) * t, px = side * -Math.cos(a) * r, py = -Math.sin(a) * r; t ? x.lineTo(px, py) : x.moveTo(px, py); } x.stroke();
    for (let i = 0; i < 12; i++) { const t = .03 + i * .08, a = a0 + (a1 - a0) * t, px = side * -Math.cos(a) * r, py = -Math.sin(a) * r, tang = Math.atan2(-Math.cos(a), side * Math.sin(a));
      [1, -1].forEach(o => { x.save(); x.translate(px, py); x.rotate(tang + Math.PI / 2 + o * .75); x.beginPath(); x.ellipse(0, -20, 8, 20, 0, 0, 7); x.fill(); x.restore(); }); }
    x.restore();
  }
  function rosette(x, cx, cy, r, rank) {
    x.save(); x.translate(cx, cy);
    // ribbon tails
    [[-1, '#7A1F2B'], [1, '#9B2335']].forEach(([s2, col]) => { x.fillStyle = col; x.beginPath(); x.moveTo(s2 * r * .25, r * .5); x.lineTo(s2 * r * .75, r * 1.65); x.lineTo(s2 * r * .5, r * 1.45); x.lineTo(s2 * r * .3, r * 1.75); x.lineTo(s2 * -r * .05, r * .6); x.closePath(); x.fill(); });
    // serrated edge
    x.beginPath(); for (let i = 0; i < 72; i++) { const a = i * Math.PI / 36, rr = i % 2 ? r : r * .9; x.lineTo(Math.cos(a) * rr, Math.sin(a) * rr); } x.closePath(); x.fillStyle = gold(x, -r, -r, r, r); x.fill();
    x.beginPath(); x.arc(0, 0, r * .78, 0, 7); x.fillStyle = '#7A1F2B'; x.fill();
    x.beginPath(); x.arc(0, 0, r * .72, 0, 7); x.lineWidth = 3; x.strokeStyle = GOLD[2]; x.stroke();
    x.fillStyle = GOLD[2]; x.textAlign = 'center';
    x.font = `700 ${r * .62}px Georgia, serif`; x.fillText(String(rank), -r * .08, r * .12);
    x.font = `700 ${r * .22}px Georgia, serif`; x.fillText(['', 'ST', 'ND', 'RD'][rank] || 'TH', r * .3, -r * .15);
    x.font = `700 ${r * .17}px Georgia, serif`; x.fillText('RANK', 0, r * .42);
    for (let i = 0; i < 5; i++) { const a = -Math.PI / 2 + (i - 2) * .32; star(x, Math.cos(a) * r * .55, Math.sin(a) * r * .55 + 4, r * .06, GOLD[2]); }
    x.restore();
  }
  function star(x, cx, cy, r, col) { x.save(); x.beginPath(); for (let i = 0; i < 10; i++) { const a = -Math.PI / 2 + i * Math.PI / 5, rr = i % 2 ? r * .45 : r; x.lineTo(cx + Math.cos(a) * rr, cy + Math.sin(a) * rr); } x.closePath(); x.fillStyle = col; x.fill(); x.restore(); }
  function corner(x, cx, cy, rot, col) { x.save(); x.translate(cx, cy); x.rotate(rot); x.strokeStyle = col; x.lineWidth = 3;
    x.beginPath(); x.moveTo(0, 70); x.lineTo(0, 0); x.lineTo(70, 0); x.stroke(); x.beginPath(); x.moveTo(14, 56); x.lineTo(14, 14); x.lineTo(56, 14); x.stroke();
    x.beginPath(); x.arc(14, 14, 9, 0, 7); x.fillStyle = col; x.fill(); x.beginPath(); x.moveTo(30, 30); x.quadraticCurveTo(60, 22, 90, 34); x.moveTo(30, 30); x.quadraticCurveTo(22, 60, 34, 90); x.stroke(); x.restore(); }
  async function certCanvas(k, D) {
    await fonts();
    const W = 1754, H = 1240, c = document.createElement('canvas'); c.width = W; c.height = H; const x = c.getContext('2d');
    const T = (t, X, Y, f, col, al, sp) => { x.font = f; x.fillStyle = col || '#1d2733'; x.textAlign = al || 'center'; if (sp && 'letterSpacing' in x) x.letterSpacing = sp + 'px'; x.fillText(String(t), X, Y); if ('letterSpacing' in x) x.letterSpacing = '0px'; };
    const NAVY = '#14294A', MAROON = '#7A1F2B';
    // paper
    const bg = x.createRadialGradient(W / 2, H / 2, 100, W / 2, H / 2, W * .7); bg.addColorStop(0, '#FFFDF6'); bg.addColorStop(1, '#F5ECD6'); x.fillStyle = bg; x.fillRect(0, 0, W, H);
    x.save(); x.globalAlpha = .05; x.strokeStyle = NAVY; x.lineWidth = 1; for (let i = -H; i < W; i += 14) { x.beginPath(); x.moveTo(i, 0); x.lineTo(i + H, H); x.stroke(); } x.restore();
    const logo = await loadImg('img/snmsis-logo.png');
    if (logo) { x.save(); x.globalAlpha = .06; x.drawImage(logo, W / 2 - 330, H / 2 - 300, 660, 660); x.restore(); }
    // border
    x.fillStyle = NAVY; x.fillRect(0, 0, W, 46); x.fillRect(0, H - 46, W, 46); x.fillRect(0, 0, 46, H); x.fillRect(W - 46, 0, 46, H);
    x.lineWidth = 6; x.strokeStyle = gold(x, 0, 0, W, H); x.strokeRect(60, 60, W - 120, H - 120); x.lineWidth = 2; x.strokeRect(74, 74, W - 148, H - 148);
    [[86, 86, 0], [W - 86, 86, Math.PI / 2], [W - 86, H - 86, Math.PI], [86, H - 86, -Math.PI / 2]].forEach(([cx, cy, r]) => corner(x, cx, cy, r, GOLD[3]));
    // crest + school
    if (logo) { x.save(); x.beginPath(); x.arc(W / 2, 175, 66, 0, 7); x.fillStyle = '#fff'; x.fill(); x.lineWidth = 4; x.strokeStyle = gold(x, W / 2 - 66, 110, W / 2 + 66, 240); x.stroke(); x.clip(); x.drawImage(logo, W / 2 - 60, 115, 120, 120); x.restore(); }
    T(SCHOOL.toUpperCase(), W / 2, 290, `700 44px Georgia, serif`, NAVY, 'center', 2);
    T(PLACE + '  ·  Session ' + SESL, W / 2, 330, `italic 24px Georgia, serif`, '#5b5040');
    // title
    T('CERTIFICATE', W / 2, 430, `700 92px Georgia, serif`, MAROON, 'center', 14);
    const rw = 620, ry = 455; x.fillStyle = gold(x, W / 2 - rw / 2, 0, W / 2 + rw / 2, 0);
    x.beginPath(); x.moveTo(W / 2 - rw / 2, ry); x.lineTo(W / 2 + rw / 2, ry); x.lineTo(W / 2 + rw / 2 - 22, ry + 26); x.lineTo(W / 2 + rw / 2, ry + 52); x.lineTo(W / 2 - rw / 2, ry + 52); x.lineTo(W / 2 - rw / 2 + 22, ry + 26); x.closePath(); x.fill();
    T('OF ACADEMIC EXCELLENCE', W / 2, ry + 36, `700 28px Georgia, serif`, NAVY, 'center', 5);
    // photo with laurel (left)
    const px = 300, py = 760, pr = 130;
    laurel(x, px, py + 10, pr + 62, -1, GOLD[3]); laurel(x, px, py + 10, pr + 62, 1, GOLD[3]);
    x.beginPath(); x.arc(px, py, pr + 14, 0, 7); x.fillStyle = gold(x, px - pr, py - pr, px + pr, py + pr); x.fill(); x.beginPath(); x.arc(px, py, pr + 4, 0, 7); x.fillStyle = '#fff'; x.fill();
    const ph = await loadImg(await photo(k.st.id));
    x.save(); x.beginPath(); x.arc(px, py, pr, 0, 7); x.clip();
    if (ph) { const sc = Math.max(2 * pr / ph.width, 2 * pr / ph.height); x.drawImage(ph, px - ph.width * sc / 2, py - ph.height * sc / 2, ph.width * sc, ph.height * sc); }
    else { x.fillStyle = '#E9EEF5'; x.fillRect(px - pr, py - pr, 2 * pr, 2 * pr); T(k.st.name.split(/\s+/).map(w => w[0]).slice(0, 2).join('').toUpperCase(), px, py + 36, `700 100px Georgia, serif`, NAVY); }
    x.restore();
    x.fillStyle = NAVY; x.beginPath(); x.moveTo(px - 125, py + pr + 58); x.lineTo(px + 125, py + pr + 58); x.lineTo(px + 110, py + pr + 84); x.lineTo(px + 125, py + pr + 110); x.lineTo(px - 125, py + pr + 110); x.lineTo(px - 110, py + pr + 84); x.closePath(); x.fill();
    T('RANK HOLDER', px, py + pr + 93, `700 22px Georgia, serif`, GOLD[2], 'center', 3);
    // seal (right)
    rosette(x, W - 300, 720, 112, k.rank);
    // text (centre)
    const tx = W / 2;
    T('This is to certify that', tx, 585, `italic 32px Georgia, serif`, '#3d352a');
    x.font = `112px ${SF}`; let nm = k.st.name; while (x.measureText(nm).width > 780 && nm.length > 4) nm = nm.slice(0, -1);
    T(nm, tx, 700, `112px ${SF}`, NAVY);
    x.fillStyle = gold(x, tx - 360, 0, tx + 360, 0); x.fillRect(tx - 360, 722, 720, 3);
    T(`${k.st.father ? 'Ward of Shri ' + k.st.father + '  ·  ' : ''}Class ${k.st.cls}${k.st.section ? '-' + k.st.section : ''}  ·  Adm. No. ${k.st.adm || '—'}`, tx, 770, `26px Georgia, serif`, '#3d352a');
    T(`has secured the ${ORD[k.rank]} RANK in the class`, tx, 840, `700 36px Georgia, serif`, MAROON);
    T(`in the Half-Yearly Examination ${SESL} with ${k.pct}% marks`, tx, 890, `28px Georgia, serif`, '#3d352a');
    T('and is honoured for outstanding academic achievement.', tx, 938, `italic 28px Georgia, serif`, '#3d352a');
    // signatures
    const sy = 1080; x.strokeStyle = NAVY; x.lineWidth = 2;
    [[tx - 520, 'Class Teacher', D.ct], [tx, 'Principal', ''], [tx + 520, 'Chairman', '']].forEach(([cx, l, v]) => {
      x.beginPath(); x.moveTo(cx - 150, sy); x.lineTo(cx + 150, sy); x.stroke(); if (v) T(v, cx, sy - 14, `40px ${SF}`, NAVY);
      T(l.toUpperCase(), cx, sy + 34, `700 20px Georgia, serif`, '#5b5040', 'center', 3); });
    T('Date: ' + new Date().toLocaleDateString('en-IN', { day: 'numeric', month: 'long', year: 'numeric' }), tx, 1000, `italic 22px Georgia, serif`, '#5b5040');
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
