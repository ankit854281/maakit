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
    // class average and highest per subject (real marks, absent excluded) — for the slip's comparison column and bar
    const stat = {}; subs.forEach(sb => { const v = kids.map(k => k.rows.find(r => r.s === sb)).filter(r => r && !r.none && !r.ab).map(r => r.t);
      stat[sb] = { avg: v.length ? n2(v.reduce((a, b) => a + b, 0) / v.length) : 0, max: v.length ? Math.max(...v) : 0 }; });
    const extra = (d.extra && d.extra.rows) || {};
    const full = Math.max(0, ...kids.map(k => k.done));
    const ranked = kids.filter(k => k.pct != null && k.done === full && full > 0).sort((a, b) => b.pct - a.pct);
    let last = null, r = 0; ranked.forEach((k, i) => { if (last === null || k.pct < last) { r = i + 1; last = k.pct; } k.rank = r; });
    return { subs, kids, ranked, full, n: kids.length, stat, extra, withPhoto: d.withPhoto || [] };
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
  // Slip v2 (owner, 20 Nov 2026: "apne hisab se banakar dekhiye and slip bhi"): photo, % ring with grade, rank, every subject with
  // grade badge + class average + highest + a bar (child vs class), strengths / needs practice, teacher's remark (or one written
  // from the marks), co-scholastic grades and attendance when the class teacher filled them.
  const GCOL = { A1: '#1E7A43', A2: '#2E9E5B', B1: '#2D6CDF', B2: '#4F8BEA', C1: '#C98A00', C2: '#E0A100', D: '#E07B00', E: '#C0392B' };
  const badge = g => g ? `<span class="gb" style="background:${GCOL[g] || '#777'}">${g}</span>` : '—';
  function remarkOf(D, k) {
    const ex = D.extra[k.st.id] || {}; if ((ex.remark || '').trim()) return { text: ex.remark.trim(), auto: false };
    const R = k.rows.filter(r => !r.none && !r.ab), first = k.st.name.split(/\s+/)[0];
    const strong = R.filter(r => r.t >= 81 || r.t >= D.stat[r.s].avg + 12).sort((a, b) => b.t - a.t).slice(0, 2).map(r => r.s);
    const weak = R.filter(r => r.t + r.g < 50 || r.t <= D.stat[r.s].avg - 12).sort((a, b) => a.t - b.t).slice(0, 2).map(r => r.s);
    const p = k.pct || 0, open = p >= 85 ? `${first} has done excellent work this term.` : p >= 70 ? `${first} has done very good work this term.` : p >= 55 ? `${first} has done good work this term.` : p >= 40 ? `${first} is making progress.` : `${first} needs regular support at home and in school.`;
    return { text: open + (strong.length ? ` Strong in ${strong.join(' and ')}.` : '') + (weak.length ? ` More practice in ${weak.join(' and ')} will help.` : ' Keep it up!'), auto: true, strong, weak };
  }
  function ring(p) { const pp = Math.max(0, Math.min(100, p || 0)), col = GCOL[grade(pp)] || '#17324D', a = pp * 3.6;
    return `<div class="ring" style="background:conic-gradient(${col} ${a}deg,#E6EAF0 ${a}deg)"><div><b>${p != null ? Math.round(p) + '%' : '—'}</b><small>${p != null ? grade(p) : ''}</small></div></div>`; }
  // Parents' "nowhere else" touches (owner, 20 Nov 2026: "jo parents ko lage aisa kahi nahi mil raha"): the Principal's letter in
  // Hindi written for this child, achievement badges, the child's place on a line of the whole class, and (certificate) proud parents.
  function badgesOf(D, k) {
    const B = [], R = k.rows.filter(r => !r.none && !r.ab), ex = D.extra[k.st.id] || {};
    if (k.rank === 1) B.push(['🏆', 'Class Topper']); else if (k.rank && k.rank <= 3) B.push(['🥇', 'Top 3 in class']);
    R.forEach(r => { if (r.t >= 100) B.push(['🎯', 'Perfect score · ' + r.s]); });
    R.filter(r => r.t > 0 && r.t === (D.stat[r.s] || {}).max && !(k.rank === 1 && R.length < 2)).slice(0, 2).forEach(r => B.push(['⭐', 'Highest in ' + r.s]));
    if (R.length && R.every(r => r.vals[1] === 5)) B.push(['📒', 'Neat Notebook Star']);
    if (R.length >= 3 && R.every(r => /^A/.test(grade(r.t)))) B.push(['💎', 'All-round A grade']);
    if (+ex.days > 0 && +ex.present === +ex.days) B.push(['📅', 'Full Attendance']);
    return B.slice(0, 6);
  }
  // Principal's letter — in English (owner, 20 Nov 2026: "pradhanacharya ka message english me kardo"), written for each child.
  const ORDN = n => n + (['th', 'st', 'nd', 'rd'][(n % 100 - 20) % 10] || ['th', 'st', 'nd', 'rd'][n % 100] || 'th');
  function letterOf(D, k) {
    const rm = remarkOf(D, k), first = k.st.name.split(/\s+/)[0], fa = (k.st.father || '').trim();
    const strong = (rm.strong || k.rows.filter(r => !r.none && !r.ab && r.t >= 81).map(r => r.s)).slice(0, 2), weak = (rm.weak || []).slice(0, 2);
    const and = a => a.length > 1 ? a.slice(0, -1).join(', ') + ' and ' + a[a.length - 1] : a[0];
    const p = k.pct || 0, top = k.rank && k.rank * 2 <= D.ranked.length;
    const open = p >= 85 ? 'we are delighted to share that' : p >= 60 ? 'we are pleased to share that' : 'we would like to share that';
    return `Dear <b>${esc(fa ? 'Mr. ' + fa : 'Parent')}</b>, ${open} <b>${esc(first)}</b> has scored <b>${k.pct != null ? k.pct + '%' : '—'}</b> in the Half-Yearly Examination${top ? ` and secured the <b>${ORDN(k.rank)} position</b> in the class` : ''}. ` +
      (strong.length ? `${esc(first)}'s work in <b>${esc(and(strong))}</b> has been particularly impressive. ` : '') +
      (weak.length ? `With a little more practice in <b>${esc(and(weak))}</b>, the results will be even better. ` : `We encourage ${esc(first)} to keep up the same effort. `) +
      `Thank you for your continued support.<em>— Principal</em>`;
  }
  function posLine(D, k) {
    // Only for the upper half: a weak child's parents should not see their child at the bottom of a line (kind, not hidden — rank is in the box).
    if (!k.rank || D.ranked.length < 3 || k.rank * 2 > D.ranked.length) return '';
    const P = D.ranked.map(x => x.pct), lo = Math.min(...P), hi = Math.max(...P), sp = Math.max(1, hi - lo), at = p => 3 + (p - lo) / sp * 94;
    const top = Math.max(1, Math.ceil(k.rank * 100 / D.ranked.length));
    return `<div class="pos"><b>Where ${esc(k.st.name.split(/\s+/)[0])} stands in the class</b> — each dot is one child of the class (no names)
      <div class="ln">${D.ranked.filter(x => x !== k).map(x => `<i style="left:${at(x.pct)}%"></i>`).join('')}<b style="left:${at(k.pct)}%"></b><u style="left:${Math.min(88, Math.max(12, at(k.pct)))}%">Top ${top}% of the class</u></div>
      <div class="sc"><span>${lo}%</span><span>${hi}%</span></div></div>`;
  }
  // "Final exam ka target" (owner, 20 Nov 2026): for every subject, the marks that reach the NEXT grade in the final —
  // parents see exactly what to work on at home. A1 subjects: "keep it 91+".
  const BANDS = [[33, 'D'], [41, 'C2'], [51, 'C1'], [61, 'B2'], [71, 'B1'], [81, 'A2'], [91, 'A1']];
  function targetHtml(k) {
    const R = k.rows.filter(r => !r.none && !r.ab); if (!R.length) return '';
    const nx = t => BANDS.find(([m]) => m > t);
    const chips = R.map(r => { const b = nx(r.t); return b ? `<span>${esc(r.s)}: ${r.t} → <b>${b[0]}+</b> <em>${b[1]}</em></span>` : `<span>${esc(r.s)}: <em>keep ${r.t >= 100 ? '100' : '91+'} ✓</em></span>`; });
    const ob = k.pct != null ? nx(k.pct) : null;
    return `<div class="tgt"><b>🎯 Target for the Final Exam <small>· marks needed in each subject for the next grade</small></b><div>${chips.join('')}${ob ? `<span>Overall: ${k.pct}% → <b>${ob[0]}%</b> <em>${ob[1]}</em></span>` : ''}</div></div>`;
  }
  // Attendance and co-scholastic as graphs (owner, 20 Nov 2026: "attendance ka bhi graph, co-scholastic bhi graph me") — always shown,
  // "Not entered yet" when the class teacher has not filled them.
  function attCard(ex) {
    const d = +ex.days || 0, pr = +ex.present || 0, p = d ? Math.round(pr * 100 / d) : null, col = p == null ? '#C5CDD8' : p >= 90 ? '#1E7A43' : p >= 75 ? '#E0A100' : '#C0392B', a = (p || 0) * 3.6;
    return `<div class="card att"><h5>Attendance</h5><div class="attw"><div class="ring2" style="background:conic-gradient(${col} ${a}deg,#E6EAF0 ${a}deg)"><div><b>${p == null ? '—' : p + '%'}</b></div></div>
      <div class="attx">${d ? `<span><b>${pr}</b> present</span><span><b>${d - pr}</b> absent</span><span>of <b>${d}</b> working days</span>` : '<span class="mut">Not entered yet</span>'}</div></div></div>`;
  }
  const CO = [['art', 'Art Education'], ['work', 'Work Education'], ['health', 'Health & PE'], ['disc', 'Discipline']], COW = { A: 100, B: 66, C: 33 }, COC = { A: '#1E7A43', B: '#2D6CDF', C: '#E0A100' };
  function cosCard(ex) {
    return `<div class="card cos"><h5>Co-Scholastic</h5>${CO.map(([kk, l]) => { const g = String(ex[kk] || '').toUpperCase();
      return `<div class="cr"><span>${l}</span><div class="cb"><i style="width:${COW[g] || 0}%;background:${COC[g] || '#C5CDD8'}"></i></div><em style="background:${COC[g] || '#C5CDD8'}">${g || '–'}</em></div>`; }).join('')}
      <small>A = Outstanding · B = Very Good · C = Good</small></div>`;
  }
  function slipHtml(D, k) {
    const st = k.st, ex = D.extra[st.id] || {}, rm = remarkOf(D, k), fail = k.rows.some(r => !r.none && !r.ab && r.t + r.g < PASS);
    const strong = rm.strong || k.rows.filter(r => !r.none && !r.ab && r.t >= 81).map(r => r.s).slice(0, 2), weak = rm.weak || [];
    return `<div class="slip"><div class="shd"><img src="img/snmsis-logo.png" alt=""><div><b>${SCHOOL.toUpperCase()}</b><span>${PLACE}</span></div><i>HALF-YEARLY EXAMINATION ${SESL}<br><em>PROGRESS SLIP</em></i></div>
      <div class="stu"><div class="ph" data-sid="${esc(st.id)}">${PH[st.id] ? `<img src="${PH[st.id]}" alt="">` : `<span>${esc(st.name.split(/\s+/).map(w => w[0]).slice(0, 2).join('').toUpperCase())}</span>`}</div>
        <div class="who"><b>${esc(st.name)}</b><span>Class <b>${esc(st.cls)}${st.section ? '-' + esc(st.section) : ''}</b> · Adm. No. <b>${esc(st.adm || '—')}</b></span><span>Father: <b>${esc(st.father || '—')}</b>${st.mother ? ' · Mother: <b>' + esc(st.mother) + '</b>' : ''}</span></div>
        ${ring(k.pct)}<div class="rk"><small>Rank in class</small><b>${k.rank ? k.rank : '—'}</b><small>of ${D.n}</small></div></div>
      <div class="letter">${letterOf(D, k)}</div>
      ${(() => { const B = badgesOf(D, k); return B.length ? `<div class="badges">${B.map(([i, l]) => `<span><i>${i}</i>${esc(l)}</span>`).join('')}</div>` : ''; })()}
      <table class="m"><tr><th style="text-align:left">Subject</th>${PARTS.map(([, l, m]) => `<th>${l.replace('Term Exam', 'Term')}<br><small>${m}</small></th>`).join('')}<th>Total<br><small>100</small></th><th>Grace</th><th>Grade</th><th>Class<br>avg</th><th>Highest</th><th style="width:22%">Child vs class</th></tr>
      ${k.rows.map(r => { if (r.none) return `<tr><td class="s">${esc(r.s)}</td><td colspan="10" class="mut">Not entered</td></tr>`; const S = D.stat[r.s] || { avg: 0, max: 0 }, g = r.ab ? '' : grade(r.t + r.g);
        return `<tr><td class="s">${esc(r.s)}</td>${r.vals.map(v => `<td>${v === '' || v == null ? '—' : esc(v)}</td>`).join('')}<td><b class="${r.t + r.g < PASS ? 'red' : ''}">${r.ab ? 'AB' : r.t}</b></td><td>${r.g ? `<span class="gr">+${r.g}</span>` : '—'}</td><td>${badge(g)}</td><td>${S.avg}</td><td>${S.max}</td>
          <td class="bars"><div class="bar"><i style="width:${r.ab ? 0 : Math.min(100, r.t)}%;background:${GCOL[g] || '#17324D'}"></i><u style="left:${Math.min(100, S.avg)}%"></u></div></td></tr>`; }).join('')}
      <tr class="t"><td class="s">Grand total</td><td colspan="4"></td><td>${k.tot}/${k.max}</td><td>${k.gTot ? '+' + k.gTot : '—'}</td><td>${badge(k.pct != null ? grade(k.pct) : '')}</td><td colspan="3" style="text-align:left">Result: <b class="${fail ? 'red' : 'gr'}">${fail ? 'Needs improvement' : 'Pass'}</b></td></tr></table>
      ${targetHtml(k)}
      ${posLine(D, k)}
      <div class="low3">
        <div class="card rmk"><h5>${rm.auto ? 'Remark' : 'Class Teacher\'s Remark'}</h5><p>${esc(rm.text)}</p></div>
        ${attCard(ex)}
        ${cosCard(ex)}
      </div>
      <p class="gn">${k.gTot ? `Grace (+${k.gTot}) is given only to reach the pass mark (${PASS}) and shown separately; total, % and rank are on the real marks. ` : ''}The bar shows the child's marks; the thin line is the class average. The full report card will be given with the final result.</p>
      <div class="foot"><div><i>Class Teacher${D.ct ? ' (' + esc(D.ct) + ')' : ''}</i></div><div><i>Parent's Signature</i></div><div><i>Principal</i></div></div></div>`;
  }
  function slipText(D, k) {
    return `*${SCHOOL}*\nHalf-Yearly ${SESL} · Marks\n*${k.st.name}* (Class ${k.st.cls})\n\n` + k.rows.filter(r => !r.none).map(r => `${r.s}: ${r.ab ? 'AB' : r.t + '/100'}${r.g ? ' (grace +' + r.g + ')' : ''} — ${r.ab ? '-' : grade(r.t + r.g)}`).join('\n') +
      `\n\nTotal: ${k.tot}/${k.max} · ${k.pct != null ? k.pct + '%' : '-'}${k.rank ? ' · Rank ' + k.rank + ' of ' + D.n : ''}\nThe full report card will be given with the final result.`;
  }
  function slips(D) {
    const kids = D.kids.slice().sort((a, b) => a.st.name.localeCompare(b.st.name));
    $('out').innerHTML = `<div class="pan np"><b>${kids.length} slips</b> · class ${esc(CLS)} · ${D.subs.length} subjects · ${D.kids.filter(k => k.gTot).length} child(ren) with grace. "Print all slips" prints every slip (one slip per A4 page).</div>` +
      kids.map((k, i) => slipHtml(D, k) + `<div class="slipbar">${(k.st.phone || '').replace(/\D/g, '').slice(-10).length === 10 ? `<a class="btn g" target="_blank" rel="noopener" href="https://wa.me/91${(k.st.phone || '').replace(/\D/g, '').slice(-10)}?text=${encodeURIComponent(slipText(D, k))}">WhatsApp to parent</a>` : ''}<button class="btn l" data-p="${i}">Print this slip</button></div>`).join('');
    (async () => { const ids = kids.map(k => k.st.id).filter(id => D.withPhoto.includes(id) && PH[id] === undefined);
      const q = [...ids]; await Promise.all(Array.from({ length: 4 }, async () => { while (q.length) { const id = q.shift(); await photo(id);
        if (PH[id]) document.querySelectorAll(`.ph[data-sid="${id}"]`).forEach(e => { e.innerHTML = `<img src="${PH[id]}" alt="">`; }); } })); })();
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
  let SIGNS = null;
  async function signs() { if (SIGNS) return SIGNS; try { SIGNS = (await get('teacher_api.php?a=comp&sg=1')).signs || {}; } catch (e) { SIGNS = {}; } return SIGNS; }
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
    T(`Class ${k.st.cls}${k.st.section ? '-' + k.st.section : ''}  ·  Adm. No. ${k.st.adm || '—'}`, tx, 768, `26px Georgia, serif`, '#3d352a');
    if (k.st.father || k.st.mother) T('Proud parents: ' + [k.st.father ? 'Shri ' + k.st.father : '', k.st.mother ? 'Smt. ' + k.st.mother : ''].filter(Boolean).join(' & '), tx, 808, `italic 600 24px Georgia, serif`, '#8C6A1E');
    T(`has secured the ${ORD[k.rank]} RANK in the class`, tx, 858, `700 36px Georgia, serif`, MAROON);
    T(`in the Half-Yearly Examination ${SESL} with ${k.pct}% marks`, tx, 902, `28px Georgia, serif`, '#3d352a');
    T('and is honoured for outstanding academic achievement.', tx, 944, `italic 28px Georgia, serif`, '#3d352a');
    // signatures
    const sy = 1080; x.strokeStyle = NAVY; x.lineWidth = 2;
    [[tx - 520, 'Class Teacher', D.ct], [tx, 'Principal', ''], [tx + 520, 'Chairman', '']].forEach(([cx, l, v]) => {
      x.beginPath(); x.moveTo(cx - 150, sy); x.lineTo(cx + 150, sy); x.stroke(); if (v) T(v, cx, sy - 14, `40px ${SF}`, NAVY);
      T(l.toUpperCase(), cx, sy + 34, `700 20px Georgia, serif`, '#5b5040', 'center', 3); });
    T('Date: ' + new Date().toLocaleDateString('en-IN', { day: 'numeric', month: 'long', year: 'numeric' }), tx, 1004, `italic 22px Georgia, serif`, '#5b5040');
    // certificate number (same child + exam + rank → same number) and the Principal's saved signature (Teacher App → Mera sign)
    let hsh = 0; for (const ch of 'HY|' + SES + '|' + k.st.id + '|' + k.rank) hsh = (hsh * 31 + ch.charCodeAt(0)) >>> 0;
    const cert = (D.certs || {})[k.st.id];
    T('Certificate No. ' + (cert ? cert.no : 'SNMSIS/HY' + SES.slice(2, 4) + '/' + hsh.toString(36).toUpperCase().padStart(7, '0').slice(-7)), W / 2, H - 92, `600 16px Georgia, serif`, '#7a6a50', 'center', 1);
    // "Scan to verify" QR (owner, 20 Nov 2026) — only when the server has this certificate in its records (progress_api a=certs)
    if (cert && window.qrcode) { try {
      const q = qrcode(0, 'M'); q.addData(new URL('verify-cert.php?c=' + cert.code, location.href).href); q.make();
      const nM = q.getModuleCount(), sz = 112, cell = sz / nM, qx = W - 222, qy = 918;
      x.fillStyle = '#fff'; x.fillRect(qx - 8, qy - 8, sz + 16, sz + 16); x.lineWidth = 2; x.strokeStyle = GOLD[3]; x.strokeRect(qx - 8, qy - 8, sz + 16, sz + 16);
      x.fillStyle = NAVY; for (let r = 0; r < nM; r++) for (let cc = 0; cc < nM; cc++) if (q.isDark(r, cc)) x.fillRect(qx + cc * cell, qy + r * cell, cell + .4, cell + .4);
      T('SCAN TO VERIFY', qx + sz / 2, qy + sz + 30, `700 14px Georgia, serif`, '#5b5040', 'center', 2);
    } catch (e) {} }
    const sgn = await signs(); if (sgn.P) { const im = await loadImg(sgn.P); if (im) { const h2 = 70, w2 = im.width * h2 / im.height; x.drawImage(im, tx - w2 / 2, sy - h2 - 4, w2, h2); } }
    return c;
  }
  async function rankers(D) {
    const top = D.ranked.filter(k => k.rank <= 3);
    if (!top.length) { $('out').innerHTML = '<div class="pan msg">No child has every subject entered yet — rankers come when all marks of the class are in.</div>'; return; }
    $('out').innerHTML = `<div class="pan np"><b>Class ${esc(CLS)} rankers</b> · on the real marks (no grace) · ${D.full} subjects each. <button class="btn y" id="cprint">🖨 Print all certificates</button> <button class="btn" id="stars">⭐ Our Stars poster (all classes)</button></div><div id="sp"></div><div class="certs" id="cl"></div>`;
    $('stars').onclick = () => starsPoster();
    if (!D.certs) { try { D.certs = (await get(`progress_api.php?a=certs&ses=${SES}&cls=${encodeURIComponent(CLS)}`)).certs || {}; } catch (e) { D.certs = {}; } }
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
  // ---------- "Our Stars" poster (owner, 20 Nov 2026): every class's 1st/2nd/3rd with photos on one A3-style poster for the
  // school gate, WhatsApp and Facebook. Classes without complete marks are left out.
  function wrap2(x, t, w) { const W2 = t.split(/\s+/); let a = '', b = ''; for (const wd of W2) { if (!b && x.measureText((a + ' ' + wd).trim()).width <= w) a = (a + ' ' + wd).trim(); else b = (b + ' ' + wd).trim(); }
    while (b && x.measureText(b).width > w && b.length > 3) b = b.slice(0, -2) + '…'; return [a, b]; }
  async function starsPoster() {
    const box = $('sp'); box.innerHTML = '<div class="pan msg">Making the poster — opening every class…</div>';
    const groups = [];
    for (const c of CLASSES) { try { const D2 = await load(c); const top = D2.ranked.filter(k => k.rank <= 3).slice(0, 4); if (top.length) groups.push([c, top]); } catch (e) {} }
    if (!groups.length) { box.innerHTML = '<div class="pan msg">No class has all its marks yet.</div>'; return; }
    const all = groups.flatMap(g => g[1]); const q = all.map(k => k.st.id); await Promise.all(Array.from({ length: 4 }, async () => { while (q.length) await photo(q.shift()); }));
    await fonts();
    const W = 1754, H = 2480, c = document.createElement('canvas'); c.width = W; c.height = H; const x = c.getContext('2d');
    const T = (t, X, Y, f, col, al, sp) => { x.font = f; x.fillStyle = col; x.textAlign = al || 'center'; if ('letterSpacing' in x) x.letterSpacing = (sp || 0) + 'px'; x.fillText(String(t), X, Y); if ('letterSpacing' in x) x.letterSpacing = '0px'; };
    const NAVY = '#14294A', MAROON = '#7A1F2B';
    const bg = x.createLinearGradient(0, 0, W, H); bg.addColorStop(0, '#0B1A36'); bg.addColorStop(.55, '#1B3D73'); bg.addColorStop(1, '#0E1F3D'); x.fillStyle = bg; x.fillRect(0, 0, W, H);
    let seed = 7; const rnd = () => (seed = (seed * 16807) % 2147483647) / 2147483647;
    for (let i = 0; i < 140; i++) { x.globalAlpha = .15 + rnd() * .5; star(x, rnd() * W, rnd() * 560, 3 + rnd() * 7, i % 3 ? '#F6E3A1' : '#fff'); } x.globalAlpha = 1;
    x.lineWidth = 6; x.strokeStyle = gold(x, 0, 0, W, H); x.strokeRect(34, 34, W - 68, H - 68); x.lineWidth = 2; x.strokeRect(50, 50, W - 100, H - 100);
    const logo = await loadImg('img/snmsis-logo.png');
    if (logo) { x.save(); x.beginPath(); x.arc(W / 2, 160, 80, 0, 7); x.fillStyle = '#fff'; x.fill(); x.lineWidth = 6; x.strokeStyle = GOLD[1]; x.stroke(); x.clip(); x.drawImage(logo, W / 2 - 74, 86, 148, 148); x.restore(); }
    T(SCHOOL.toUpperCase(), W / 2, 300, `700 46px Georgia, serif`, '#fff', 'center', 2);
    T(PLACE, W / 2, 342, `italic 26px Georgia, serif`, '#DDE6F0');
    x.font = `700 150px Georgia, serif`; x.fillStyle = gold(x, W / 2 - 520, 360, W / 2 + 520, 500); x.textAlign = 'center'; if ('letterSpacing' in x) x.letterSpacing = '18px'; x.fillText('OUR STARS', W / 2, 500); if ('letterSpacing' in x) x.letterSpacing = '0px';
    T(`Half-Yearly Examination ${SESL}  ·  Class Rank Holders`, W / 2, 556, `italic 32px Georgia, serif`, '#F6E3A1');
    const cols = 3, gx = 30, mx = 80, rows = Math.ceil(groups.length / cols), top0 = 610, avail = H - 170 - top0;
    const cw = (W - 2 * mx - gx * (cols - 1)) / cols, ch = Math.min(470, (avail - gx * (rows - 1)) / rows);
    for (let gi = 0; gi < groups.length; gi++) {
      const [cl, kids] = groups[gi], col = gi % cols, row = Math.floor(gi / cols);
      const lastRow = row === rows - 1, inRow = lastRow ? groups.length - row * cols : cols, off = (cols - inRow) * (cw + gx) / 2;
      const X0 = mx + off + col * (cw + gx), Y0 = top0 + row * (ch + gx);
      x.save(); x.shadowColor = 'rgba(0,0,0,.35)'; x.shadowBlur = 18; x.fillStyle = '#FFFDF6'; x.beginPath(); x.roundRect(X0, Y0, cw, ch, 22); x.fill(); x.restore();
      x.lineWidth = 3; x.strokeStyle = GOLD[1]; x.beginPath(); x.roundRect(X0 + 8, Y0 + 8, cw - 16, ch - 16, 16); x.stroke();
      const rw2 = 230; x.fillStyle = MAROON; x.beginPath(); x.moveTo(X0 + cw / 2 - rw2 / 2, Y0 - 4); x.lineTo(X0 + cw / 2 + rw2 / 2, Y0 - 4); x.lineTo(X0 + cw / 2 + rw2 / 2 - 16, Y0 + 24); x.lineTo(X0 + cw / 2 + rw2 / 2, Y0 + 52); x.lineTo(X0 + cw / 2 - rw2 / 2, Y0 + 52); x.lineTo(X0 + cw / 2 - rw2 / 2 + 16, Y0 + 24); x.closePath(); x.fill();
      T('CLASS ' + cl.toUpperCase(), X0 + cw / 2, Y0 + 35, `700 26px Georgia, serif`, '#F6E3A1', 'center', 3);
      // podium order: 2nd · 1st · 3rd, the 1st a little bigger and higher; the whole group centred in the card
      const ord = kids.length === 3 && kids[0].rank === 1 ? [kids[1], kids[0], kids[2]] : kids;
      const n = ord.length, slot = (cw - 24) / n, r = Math.min(n === 3 ? 66 : 60, slot / 2 - 17, (ch - 200) / 2.3), body = 2 * r * 1.12 + 110, top = Y0 + 62 + Math.max(0, (ch - 62 - body) / 2);
      for (let i = 0; i < n; i++) {
        const k = ord[i], big = k.rank === 1 && n === 3, rr = big ? r * 1.12 : r, cx = X0 + 12 + slot * (i + .5), cy = top + r * 1.12 + (big ? -10 : 8), m = MEDAL[k.rank] || MEDAL[3];
        const g2 = x.createLinearGradient(cx - rr, cy - rr, cx + rr, cy + rr); g2.addColorStop(0, m[0]); g2.addColorStop(1, m[1]);
        x.beginPath(); x.arc(cx, cy, rr + 8, 0, 7); x.fillStyle = g2; x.fill(); x.beginPath(); x.arc(cx, cy, rr + 2, 0, 7); x.fillStyle = '#fff'; x.fill();
        const ph = await loadImg(PH[k.st.id]); x.save(); x.beginPath(); x.arc(cx, cy, rr, 0, 7); x.clip();
        if (ph) { const sc = Math.max(2 * rr / ph.width, 2 * rr / ph.height); x.drawImage(ph, cx - ph.width * sc / 2, cy - ph.height * sc / 2, ph.width * sc, ph.height * sc); }
        else { x.fillStyle = '#E9EEF5'; x.fillRect(cx - rr, cy - rr, 2 * rr, 2 * rr); T(k.st.name.split(/\s+/).map(w => w[0]).slice(0, 2).join('').toUpperCase(), cx, cy + rr * .3, `700 ${rr * .8}px Georgia, serif`, NAVY); }
        x.restore();
        x.beginPath(); x.arc(cx + rr * .72, cy + rr * .72, 22, 0, 7); x.fillStyle = MAROON; x.fill(); x.lineWidth = 3; x.strokeStyle = m[0]; x.stroke();
        T(k.rank + ['', 'st', 'nd', 'rd'][k.rank], cx + rr * .72, cy + rr * .72 + 7, `700 18px Georgia, serif`, '#fff');
        x.font = `700 22px Georgia, serif`; const [l1, l2] = wrap2(x, k.st.name, slot - 10);
        const ny = top + 2 * r * 1.12 + 50; T(l1, cx, ny, `700 22px Georgia, serif`, NAVY); if (l2) T(l2, cx, ny + 26, `700 22px Georgia, serif`, NAVY);
        T(k.pct + '%', cx, ny + (l2 ? 58 : 32), `700 22px Georgia, serif`, MAROON);
      }
    }
    T('Congratulations to all our rank holders!', W / 2, H - 112, `italic 700 38px Georgia, serif`, '#F6E3A1');
    T(SCHOOL + '  ·  ' + PLACE, W / 2, H - 70, `22px Georgia, serif`, '#DDE6F0');
    const url = c.toDataURL('image/jpeg', .92);
    box.innerHTML = `<div class="pan"><b>⭐ Our Stars — ${all.length} rank holders, ${groups.length} classes</b><div class="row" style="margin:8px 0"><button class="btn g" id="sps">Share</button><a class="btn l" download="Our-Stars-${SES}.jpg" href="${url}">Download</a><button class="btn l" id="spp">🖨 Print</button></div><img src="${url}" alt="Our Stars poster" style="width:100%;max-width:520px;display:block;border:1px solid #dfe4ea"></div>`;
    $('sps').onclick = () => share(c, `Our-Stars-${SES}.png`, `Our Stars — Half-Yearly ${SESL}`);
    $('spp').onclick = () => { const w = window.open('', '_blank'); if (!w) return alert('Allow pop-ups to print.'); w.document.write(`<!doctype html><html><head><title>Our Stars</title><style>@page{size:A3 portrait;margin:0}body{margin:0}img{width:100%;display:block}</style></head><body><img src="${url}"><script>setTimeout(print,600)<\/script></body></html>`); w.document.close(); };
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
