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
  // "Instructions from the Principal" at the bottom of every slip (owner, 20 Nov 2026) — change the lines here.
  const INSTR = ['Please go through this slip with your child and sign it.', 'Give extra practice at home in the subjects shown in the Target box.',
    'Check the school diary every day for homework and notices.', 'Regular attendance and punctuality are important for the Final Examination.',
    'For any query, please meet the class teacher on the Parent-Teacher Meeting day.', 'Encourage reading for at least 20 minutes every day.'];
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
      let tot = 0, rtot = 0, max = 0, gTot = 0; const rows = [];
      subs.forEach(s => { const m = (bySub[s] || {})[st.id]; if (!m) { rows.push({ s, none: true }); return; }
        const vals = PARTS.map(([k]) => m[k]), has = vals.some(v => v !== '' && v != null); if (!has) { rows.push({ s, none: true }); return; }
        const allAb = vals.every(v => v === 'AB' || v === '' || v == null) && vals.some(v => v === 'AB');
        const t = vals.reduce((a, v) => a + (typeof v === 'number' ? v : 0), 0), g = !allAb && t < PASS && t >= PASS - GRACE_MAX ? Math.ceil(PASS - t) : 0;
        // Grace is given but NOT shown (owner, 20 Nov 2026: "grace marks do but show nahi kariye"): it is added to the Term Exam
        // marks on the slip, so the parts still add up. The saved marks are unchanged; rank uses the real marks (rtot).
        const v2 = g ? vals.map((v, i) => i === 3 ? (typeof v === 'number' ? v : 0) + g : v) : vals;
        tot += t + g; rtot += t; max += 100; gTot += g; rows.push({ s, vals: v2, t: n2(t + g), real: n2(t), g: 0, gh: g, ab: allAb });
      });
      // % to ONE decimal everywhere (owner, 20 Nov 2026: "actual 43.4% hai, upar 43% dikh raha") — rank uses the unrounded real ratio
      const pct = max ? Math.round(tot * 1000 / max) / 10 : null, rpct = max ? Math.round(rtot * 1000 / max) / 10 : null, rraw = max ? rtot / max : null, done = rows.filter(r => !r.none).length;
      return { st, rows, tot: n2(tot), max, gTot, pct, rpct, rraw, done };
    });
    // class average and highest per subject (real marks, absent excluded) — for the slip's comparison column and bar
    const stat = {}; subs.forEach(sb => { const v = kids.map(k => k.rows.find(r => r.s === sb)).filter(r => r && !r.none && !r.ab).map(r => r.t);
      stat[sb] = { avg: v.length ? n2(v.reduce((a, b) => a + b, 0) / v.length) : 0, max: v.length ? Math.max(...v) : 0 }; });
    const extra = (d.extra && d.extra.rows) || {};
    const full = Math.max(0, ...kids.map(k => k.done));
    const ranked = kids.filter(k => k.pct != null && k.done === full && full > 0).sort((a, b) => b.rraw - a.rraw || b.pct - a.pct);
    let last = null, r = 0; ranked.forEach((k, i) => { if (last === null || k.rraw < last - 1e-9) { r = i + 1; last = k.rraw; } k.rank = r; });
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
      <p class="note">Half-Yearly ${SESL}: Pre. Test 10 + Notebook 5 + SEA 5 + Term Exam 80 = 100 per subject. Grace: a subject from ${PASS - GRACE_MAX} to ${PASS - 1} gets grace up to ${PASS} (pass) — added to the Term Exam marks on the slip and not shown; saved marks and rank stay on the real marks.</p>`;
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
    return `<div class="ring" style="background:conic-gradient(${col} ${a}deg,#E6EAF0 ${a}deg)"><div><b>${p != null ? p + '%' : '—'}</b><small>${p != null ? grade(p) : ''}</small></div></div>`; }
  // Parents' "nowhere else" touches (owner, 20 Nov 2026: "jo parents ko lage aisa kahi nahi mil raha"): the Principal's letter in
  // Hindi written for this child, achievement badges, the child's place on a line of the whole class, and (certificate) proud parents.
  function badgesOf(D, k) {
    const B = [], R = k.rows.filter(r => !r.none && !r.ab), ex = D.extra[k.st.id] || {};
    if (k.rank === 1) B.push(['★', 'Class Topper']); else if (k.rank && k.rank <= 3) B.push(['★', 'Top 3 in Class']);
    R.forEach(r => { if (r.t >= 100) B.push(['100', 'Perfect Score · ' + r.s]); });
    R.filter(r => r.t > 0 && r.t === (D.stat[r.s] || {}).max && !(k.rank === 1 && R.length < 2)).slice(0, 2).forEach(r => B.push(['▲', 'Highest in ' + r.s]));
    if (R.length && R.every(r => r.vals[1] === 5)) B.push(['✓', 'Neat Notebook Star']);
    if (R.length >= 3 && R.every(r => /^A/.test(grade(r.t)))) B.push(['A', 'All-round A Grade']);
    if (+ex.days > 0 && +ex.present === +ex.days) B.push(['✓', 'Full Attendance']);
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
    return `<div class="tgt"><h5>Target for the Final Exam <small>— marks needed in each subject for the next grade</small></h5><div>${chips.join('')}${ob ? `<span>Overall: ${k.pct}% → <b>${ob[0]}%</b> <em>${ob[1]}</em></span>` : ''}</div></div>`;
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
        ${ring(k.pct)}${k.rank && k.rank * 2 <= D.ranked.length ? `<div class="rk"><small>Rank in class</small><b>${k.rank}</b><small>of ${D.ranked.length}</small></div>` : `<div class="rk"><small>Overall grade</small><b>${k.pct != null ? grade(k.pct) : '—'}</b><small>${fail ? 'Keep trying' : 'Passed'}</small></div>`}</div>
      <div class="letter">${letterOf(D, k)}</div>
      ${(() => { const B = badgesOf(D, k); return B.length ? `<div class="badges">${B.map(([i, l]) => `<span><i>${i}</i>${esc(l)}</span>`).join('')}</div>` : ''; })()}
      <table class="m"><tr><th style="text-align:left">Subject</th>${PARTS.map(([, l, m]) => `<th>${l.replace('Term Exam', 'Term')}<br><small>${m}</small></th>`).join('')}<th>Total<br><small>100</small></th><th>Grade</th><th>Class<br>avg</th><th>Highest</th><th style="width:22%">Child vs class</th></tr>
      ${k.rows.map(r => { if (r.none) return `<tr><td class="s">${esc(r.s)}</td><td colspan="9" class="mut">Not entered</td></tr>`; const S = D.stat[r.s] || { avg: 0, max: 0 }, g = r.ab ? '' : grade(r.t + r.g);
        return `<tr><td class="s">${esc(r.s)}</td>${r.vals.map(v => `<td>${v === '' || v == null ? '—' : esc(v)}</td>`).join('')}<td><b class="${r.t < PASS ? 'red' : ''}">${r.ab ? 'AB' : r.t}</b></td><td>${badge(g)}</td><td>${S.avg}</td><td>${S.max}</td>
          <td class="bars"><div class="bar"><i style="width:${r.ab ? 0 : Math.min(100, r.t)}%;background:${GCOL[g] || '#17324D'}"></i><u style="left:${Math.min(100, S.avg)}%"></u></div></td></tr>`; }).join('')}
      <tr class="t"><td class="s">Grand total</td><td colspan="4"></td><td>${k.tot}/${k.max}</td><td>${badge(k.pct != null ? grade(k.pct) : '')}</td><td colspan="3" style="text-align:left">Result: <b class="${fail ? 'red' : 'gr'}">${fail ? 'Needs improvement' : 'Pass'}</b></td></tr></table>
      ${targetHtml(k)}
      ${posLine(D, k)}
      <div class="low3">
        <div class="card rmk"><h5>${rm.auto ? 'Remark' : 'Class Teacher\'s Remark'}</h5><p>${esc(rm.text)}</p></div>
        ${attCard(ex)}
        ${cosCard(ex)}
      </div>
      <div class="instr"><h5>Instructions from the Principal</h5><ol>${INSTR.map(t => `<li>${esc(t)}</li>`).join('')}</ol></div>
      <p class="gn">The bar shows the child's marks; the thin line is the class average. The full report card will be given with the final result.</p>
      <div class="foot"><div><i>Class Teacher${D.ct ? ' (' + esc(D.ct) + ')' : ''}</i></div><div><i>Parent's Signature</i></div><div><i>Principal</i></div></div></div>`;
  }
  function slipText(D, k) {
    return `*${SCHOOL}*\nHalf-Yearly ${SESL} · Marks\n*${k.st.name}* (Class ${k.st.cls})\n\n` + k.rows.filter(r => !r.none).map(r => `${r.s}: ${r.ab ? 'AB' : r.t + '/100'} — ${r.ab ? '-' : grade(r.t)}`).join('\n') +
      `\n\nTotal: ${k.tot}/${k.max} · ${k.pct != null ? k.pct + '%' : '-'}${k.rank ? ' · Rank ' + k.rank + ' of ' + D.n : ''}\nThe full report card will be given with the final result.`;
  }
  function slips(D) {
    const kids = D.kids.slice().sort((a, b) => a.st.name.localeCompare(b.st.name));
    $('out').innerHTML = `<div class="pan np"><b>${kids.length} slips</b> · class ${esc(CLS)} · ${D.subs.length} subjects · ${D.kids.filter(k => k.gTot).length} child(ren) given grace (added to Term marks on the slip, not shown). "Print all slips" prints every slip (one slip per A4 page).</div>` +
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
  // certificate v4 (owner, 20 Nov 2026: "certificate color unique feel nahi de raha"): deep emerald + metal of the rank — 1st gold,
  // 2nd silver, 3rd bronze — with a guilloche (bank-note) security pattern on the frame and a guilloche rosette behind the text.
  const METAL = { 1: GOLD, 2: ['#5E6670', '#C3CAD2', '#F2F4F6', '#8E959E'], 3: ['#6E3F1C', '#D9945E', '#F6D2B0', '#A0582A'] };
  let PAL = GOLD;
  function gold(x, x0, y0, x1, y1) { const g = x.createLinearGradient(x0, y0, x1, y1); g.addColorStop(0, PAL[0]); g.addColorStop(.35, PAL[2]); g.addColorStop(.55, PAL[1]); g.addColorStop(1, PAL[0]); return g; }
  function guilloche(x, cx, cy, R, r, d, col, a, lw) { x.save(); x.globalAlpha = a; x.strokeStyle = col; x.lineWidth = lw || 1; x.beginPath();
    for (let t = 0; t <= Math.PI * 2 * r / gcd(R, r) + .01; t += .01) { const px = cx + (R - r) * Math.cos(t) + d * Math.cos((R - r) / r * t), py = cy + (R - r) * Math.sin(t) - d * Math.sin((R - r) / r * t); t ? x.lineTo(px, py) : x.moveTo(px, py); }
    x.stroke(); x.restore(); }
  function gcd(a, b) { return b ? gcd(b, a % b) : a; }
  function waveBand(x, x0, y0, len, h, vertical, col) { x.save(); x.strokeStyle = col; x.lineWidth = 1.2; x.globalAlpha = .55;
    for (let k = 0; k < 6; k++) { x.beginPath(); for (let t = 0; t <= len; t += 4) { const w = h / 2 + (h / 2 - 6) * Math.sin(t / 22 + k * Math.PI / 3); vertical ? (t ? x.lineTo(x0 + w, y0 + t) : x.moveTo(x0 + w, y0 + t)) : (t ? x.lineTo(x0 + t, y0 + w) : x.moveTo(x0 + t, y0 + w)); } x.stroke(); }
    x.restore(); }
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
    [[-1, 0], [1, 3]].forEach(([s2, ci]) => { x.fillStyle = PAL[ci]; x.beginPath(); x.moveTo(s2 * r * .25, r * .5); x.lineTo(s2 * r * .75, r * 1.65); x.lineTo(s2 * r * .5, r * 1.45); x.lineTo(s2 * r * .3, r * 1.75); x.lineTo(s2 * -r * .05, r * .6); x.closePath(); x.fill(); });
    // serrated edge
    x.beginPath(); for (let i = 0; i < 72; i++) { const a = i * Math.PI / 36, rr = i % 2 ? r : r * .9; x.lineTo(Math.cos(a) * rr, Math.sin(a) * rr); } x.closePath(); x.fillStyle = gold(x, -r, -r, r, r); x.fill();
    x.beginPath(); x.arc(0, 0, r * .78, 0, 7); x.fillStyle = '#13254B'; x.fill();
    x.beginPath(); x.arc(0, 0, r * .72, 0, 7); x.lineWidth = 3; x.strokeStyle = PAL[2]; x.stroke();
    x.fillStyle = PAL[2]; x.textAlign = 'center';
    x.font = `700 ${r * .62}px Georgia, serif`; x.fillText(String(rank), -r * .08, r * .12);
    x.font = `700 ${r * .22}px Georgia, serif`; x.fillText(['', 'ST', 'ND', 'RD'][rank] || 'TH', r * .3, -r * .15);
    x.font = `700 ${r * .17}px Georgia, serif`; x.fillText('RANK', 0, r * .42);
    for (let i = 0; i < 5; i++) { const a = -Math.PI / 2 + (i - 2) * .32; star(x, Math.cos(a) * r * .55, Math.sin(a) * r * .55 + 4, r * .06, PAL[2]); }
    x.restore();
  }
  function star(x, cx, cy, r, col) { x.save(); x.beginPath(); for (let i = 0; i < 10; i++) { const a = -Math.PI / 2 + i * Math.PI / 5, rr = i % 2 ? r * .45 : r; x.lineTo(cx + Math.cos(a) * rr, cy + Math.sin(a) * rr); } x.closePath(); x.fillStyle = col; x.fill(); x.restore(); }
  function corner(x, cx, cy, rot, col) { x.save(); x.translate(cx, cy); x.rotate(rot); x.strokeStyle = col; x.lineWidth = 3;
    x.beginPath(); x.moveTo(0, 70); x.lineTo(0, 0); x.lineTo(70, 0); x.stroke(); x.beginPath(); x.moveTo(14, 56); x.lineTo(14, 14); x.lineTo(56, 14); x.stroke();
    x.beginPath(); x.arc(14, 14, 9, 0, 7); x.fillStyle = col; x.fill(); x.beginPath(); x.moveTo(30, 30); x.quadraticCurveTo(60, 22, 90, 34); x.moveTo(30, 30); x.quadraticCurveTo(22, 60, 34, 90); x.stroke(); x.restore(); }
  let SIGNS = null;
  async function signs() { if (SIGNS) return SIGNS; try { SIGNS = (await get('teacher_api.php?a=comp&sg=1')).signs || {}; } catch (e) { SIGNS = {}; } return SIGNS; }
  // ---- certificate v6 (owner, 20 Nov 2026: none of the colours — "educational certificate jaisa feel nahi"). A classic Indian school
  // CERTIFICATE OF MERIT: cream paper with a faint pattern of school symbols (books, pencils, caps, atoms, globes, ABC/123), a royal
  // blue guilloche border with gold rules, crest + school name on top, a blue "Certificate of Merit" ribbon, a gold medal on blue/red
  // straps (left), the child's photo in a frame wearing a graduation cap (right), the traditional wording ("ward of Shri … & Smt. …,
  // a student of Class …, has secured the FIRST POSITION …"), three signatures, date, certificate no. and Scan-to-verify QR.
  let EBLUE = '#1A3E8C', EBLUE2 = '#0F2A66'; const ERED = '#C62828';
  // middle (paper) colour of the certificate — owner, 20 Nov 2026: "bich ka color kyu nahi badal rahe"
  const PAPERS = { cream: ['#FFFEFA', '#F6EEDB'], white: ['#FFFFFF', '#F3F5F9'], sky: ['#F7FBFF', '#DCEBFF'], lemon: ['#FFFDF0', '#FFF0B8'], mint: ['#FAFFFC', '#DDF3E6'] };
  let PAPER = (() => { try { return localStorage.getItem('sch_cert_paper') || 'cream'; } catch (e) { return 'cream'; } })();
  function icoCap(x, cx, cy, s2, col, fill) { x.save(); x.translate(cx, cy); x.fillStyle = col; x.strokeStyle = col; x.lineWidth = Math.max(1.5, s2 * .04);
    x.beginPath(); x.moveTo(-s2 * .32, s2 * .05); x.lineTo(-s2 * .32, s2 * .32); x.quadraticCurveTo(0, s2 * .48, s2 * .32, s2 * .32); x.lineTo(s2 * .32, s2 * .05); fill ? x.fill() : x.stroke();
    x.beginPath(); x.moveTo(0, -s2 * .34); x.lineTo(s2 * .62, 0); x.lineTo(0, s2 * .3); x.lineTo(-s2 * .62, 0); x.closePath(); fill ? x.fill() : x.stroke();
    x.beginPath(); x.moveTo(0, 0); x.lineTo(s2 * .5, s2 * .1); x.lineTo(s2 * .5, s2 * .46); x.stroke(); x.beginPath(); x.arc(s2 * .5, s2 * .5, s2 * .06, 0, 7); x.fill(); x.restore(); }
  function icoBook(x, cx, cy, s2, col) { x.save(); x.translate(cx, cy); x.strokeStyle = col; x.lineWidth = Math.max(1.5, s2 * .04);
    [-1, 1].forEach(d => { x.beginPath(); x.moveTo(0, s2 * .3); x.quadraticCurveTo(d * s2 * .3, s2 * .16, d * s2 * .62, s2 * .3); x.lineTo(d * s2 * .62, -s2 * .28); x.quadraticCurveTo(d * s2 * .3, -s2 * .42, 0, -s2 * .28); x.closePath(); x.stroke();
      for (let i = 0; i < 3; i++) { x.beginPath(); x.moveTo(d * s2 * .12, -s2 * .16 + i * s2 * .13); x.lineTo(d * s2 * .5, -s2 * .14 + i * s2 * .13); x.stroke(); } }); x.restore(); }
  function icoPencil(x, cx, cy, s2, col) { x.save(); x.translate(cx, cy); x.rotate(-Math.PI / 4); x.strokeStyle = col; x.lineWidth = Math.max(1.5, s2 * .04);
    x.strokeRect(-s2 * .4, -s2 * .09, s2 * .62, s2 * .18); x.beginPath(); x.moveTo(s2 * .22, -s2 * .09); x.lineTo(s2 * .45, 0); x.lineTo(s2 * .22, s2 * .09); x.stroke(); x.beginPath(); x.moveTo(-s2 * .28, -s2 * .09); x.lineTo(-s2 * .28, s2 * .09); x.stroke(); x.restore(); }
  function icoAtom(x, cx, cy, s2, col) { x.save(); x.translate(cx, cy); x.strokeStyle = col; x.fillStyle = col; x.lineWidth = Math.max(1.5, s2 * .035);
    for (let i = 0; i < 3; i++) { x.save(); x.rotate(i * Math.PI / 3); x.beginPath(); x.ellipse(0, 0, s2 * .5, s2 * .18, 0, 0, 7); x.stroke(); x.restore(); } x.beginPath(); x.arc(0, 0, s2 * .07, 0, 7); x.fill(); x.restore(); }
  function icoGlobe(x, cx, cy, s2, col) { x.save(); x.translate(cx, cy); x.strokeStyle = col; x.lineWidth = Math.max(1.5, s2 * .035);
    x.beginPath(); x.arc(0, 0, s2 * .42, 0, 7); x.stroke(); x.beginPath(); x.ellipse(0, 0, s2 * .18, s2 * .42, 0, 0, 7); x.stroke();
    [-.2, 0, .2].forEach(t => { const w = Math.sqrt(.42 * .42 - t * t) * s2; x.beginPath(); x.moveTo(-w, t * s2); x.lineTo(w, t * s2); x.stroke(); }); x.restore(); }
  function icoBulb(x, cx, cy, s2, col) { x.save(); x.translate(cx, cy); x.strokeStyle = col; x.lineWidth = Math.max(1.5, s2 * .035);
    x.beginPath(); x.arc(0, -s2 * .1, s2 * .3, Math.PI * .8, Math.PI * 2.2); x.lineTo(s2 * .13, s2 * .28); x.lineTo(-s2 * .13, s2 * .28); x.closePath(); x.stroke();
    x.beginPath(); x.moveTo(-s2 * .12, s2 * .36); x.lineTo(s2 * .12, s2 * .36); x.moveTo(-s2 * .08, s2 * .44); x.lineTo(s2 * .08, s2 * .44); x.stroke(); x.restore(); }
  function icoText(x, cx, cy, s2, col, t) { x.save(); x.fillStyle = col; x.font = `700 ${s2 * .42}px Georgia, serif`; x.textAlign = 'center'; x.textBaseline = 'middle'; x.fillText(t, cx, cy); x.restore(); }
  function eduPattern(x, W, H, col, alpha) {
    const ic = [icoBook, icoPencil, icoCap, icoAtom, (a, b, c2, d, e) => icoText(a, b, c2, d, e, 'ABC'), icoGlobe, icoBulb, (a, b, c2, d, e) => icoText(a, b, c2, d, e, '123'), (a, b, c2, d, e) => icoText(a, b, c2, d, e, 'π')];
    let seed = 11; const rnd = () => (seed = (seed * 16807) % 2147483647) / 2147483647;
    x.save(); x.globalAlpha = alpha; let n = 0;
    for (let yy = 70; yy < H; yy += 120) for (let xx = 70 + ((yy / 120) % 2) * 60; xx < W; xx += 125) { const f = ic[n++ % ic.length]; f(x, xx + (rnd() - .5) * 30, yy + (rnd() - .5) * 26, 54, col); }
    x.restore();
  }
  async function certMerit(k, D) {
    await fonts(); [EBLUE, EBLUE2] = MPAL[CV];
    const W = 1754, H = 1240, c = document.createElement('canvas'); c.width = W; c.height = H; const x = c.getContext('2d');
    const INK = '#2A2F3A', MUTE = '#5E5646', GD = '#9A7425', tx = W / 2;
    const T = (t, X, Y, f, col, al, sp) => { x.font = f; x.fillStyle = col || INK; x.textAlign = al || 'center'; if ('letterSpacing' in x) x.letterSpacing = (sp || 0) + 'px'; x.fillText(String(t), X, Y); if ('letterSpacing' in x) x.letterSpacing = '0px'; };
    const fit = (t, f, w) => { x.font = f; let s2 = t; while (x.measureText(s2).width > w && s2.length > 4) s2 = s2.slice(0, -2) + '…'; return s2; };
    const mixed = (parts, Y, f) => { x.font = f; const ws = parts.map(([t]) => x.measureText(t).width), tot = ws.reduce((a2, b2) => a2 + b2, 0); let X = tx - tot / 2; parts.forEach(([t, col], i) => { T(t, X, Y, f, col, 'left'); X += ws[i]; }); };
    PAL = GOLD;
    // paper + school-symbol pattern, kept light in the middle so the words stay clear
    const bg = x.createRadialGradient(tx, H / 2, 60, tx, H / 2, W * .65); const PP = PAPERS[PAPER] || PAPERS.cream; bg.addColorStop(0, PP[0]); bg.addColorStop(1, PP[1]); x.fillStyle = bg; x.fillRect(0, 0, W, H);
    eduPattern(x, W, H, EBLUE, .075);
    const veil = x.createRadialGradient(tx, H / 2 + 20, 120, tx, H / 2 + 20, 720); { const hx = PP[0], r0 = parseInt(hx.slice(1, 3), 16), g0 = parseInt(hx.slice(3, 5), 16), b0 = parseInt(hx.slice(5, 7), 16), rg = a2 => `rgba(${r0},${g0},${b0},${a2})`; veil.addColorStop(0, rg(.93)); veil.addColorStop(.75, rg(.55)); veil.addColorStop(1, rg(0)); } x.fillStyle = veil; x.fillRect(0, 0, W, H);
    const logo = await loadImg('img/snmsis-logo.png');
    if (logo) { x.save(); x.globalAlpha = .05; x.drawImage(logo, tx - 280, H / 2 - 230, 560, 560); x.restore(); }
    // border: blue guilloche band, gold rules, corner ornaments
    x.fillStyle = EBLUE; x.fillRect(0, 0, W, 40); x.fillRect(0, H - 40, W, 40); x.fillRect(0, 0, 40, H); x.fillRect(W - 40, 0, 40, H);
    waveBand(x, 0, 0, W, 40, false, '#BFD0F2'); waveBand(x, 0, H - 40, W, 40, false, '#BFD0F2'); waveBand(x, 0, 40, H - 80, 40, true, '#BFD0F2'); waveBand(x, W - 40, 40, H - 80, 40, true, '#BFD0F2');
    x.lineWidth = 6; x.strokeStyle = gold(x, 0, 0, W, H); x.strokeRect(52, 52, W - 104, H - 104); x.lineWidth = 2; x.strokeStyle = EBLUE; x.strokeRect(64, 64, W - 128, H - 128);
    [[76, 76, 0], [W - 76, 76, Math.PI / 2], [W - 76, H - 76, Math.PI], [76, H - 76, -Math.PI / 2]].forEach(([cx, cy, r]) => corner(x, cx, cy, r, GOLD[3]));
    // crest + school
    if (logo) { x.save(); x.beginPath(); x.arc(tx, 140, 62, 0, 7); x.fillStyle = '#fff'; x.fill(); x.lineWidth = 5; x.strokeStyle = gold(x, tx - 62, 78, tx + 62, 202); x.stroke(); x.clip(); x.drawImage(logo, tx - 57, 83, 114, 114); x.restore(); }
    T(SCHOOL.toUpperCase(), tx, 258, `700 44px Georgia, serif`, EBLUE2, 'center', 2);
    T(PLACE + '   ·   Session ' + SESL, tx, 296, `italic 24px Georgia, serif`, MUTE);
    // "Certificate of Merit" ribbon
    const bw = 700, by = 322, bh = 66;
    x.fillStyle = EBLUE2; [[-1], [1]].forEach(([d]) => { x.beginPath(); x.moveTo(tx + d * (bw / 2 - 20), by + 14); x.lineTo(tx + d * (bw / 2 + 60), by + 14); x.lineTo(tx + d * (bw / 2 + 36), by + 14 + bh / 2); x.lineTo(tx + d * (bw / 2 + 60), by + 14 + bh); x.lineTo(tx + d * (bw / 2 - 20), by + 14 + bh); x.closePath(); x.fill(); });
    x.fillStyle = EBLUE; x.fillRect(tx - bw / 2, by, bw, bh); x.lineWidth = 2; x.strokeStyle = GOLD[1]; x.strokeRect(tx - bw / 2 + 8, by + 7, bw - 16, bh - 14);
    T('CERTIFICATE OF MERIT', tx, by + 46, `700 38px Georgia, serif`, '#F6E3A1', 'center', 6);
    T('Academic Excellence Award  ·  Half-Yearly Examination', tx, 432, `italic 600 24px Georgia, serif`, GD, 'center', 1);
    // citation
    T('This is to certify that', tx, 494, `italic 30px Georgia, serif`, MUTE);
    const nm = fit(k.st.name, `112px ${SF}`, 820); T(nm, tx, 596, `112px ${SF}`, EBLUE2);
    x.fillStyle = gold(x, tx - 360, 0, tx + 360, 0); x.fillRect(tx - 360, 620, 720, 3);
    const par = [k.st.father ? 'Shri ' + k.st.father : '', k.st.mother ? 'Smt. ' + k.st.mother : ''].filter(Boolean).join(' & ');
    let yy = 674; if (par) { T(fit('ward of ' + par + ',', `27px Georgia, serif`, 860), tx, yy, `27px Georgia, serif`, INK); yy += 40; }
    T(`a student of Class ${k.st.cls}${k.st.section ? '-' + k.st.section : ''} (Adm. No. ${k.st.adm || '—'}),`, tx, yy, `27px Georgia, serif`, INK);
    mixed([['has secured the ', EBLUE2], [`${ORD[k.rank]} POSITION`, ERED], [' in the class', EBLUE2]], yy + 56, `700 38px Georgia, serif`);
    T(`in the Half-Yearly Examination ${SESL} with ${k.rpct}% marks.`, tx, yy + 104, `28px Georgia, serif`, INK);
    T('We are proud of this achievement and wish a very bright future ahead.', tx, yy + 148, `italic 26px Georgia, serif`, MUTE);
    // left: gold medal on blue / red straps
    const mx = 285, my = 640, mr = 96, M = METAL[k.rank] || GOLD;
    [[-1, EBLUE], [1, ERED]].forEach(([d, col]) => { x.fillStyle = col; x.beginPath(); x.moveTo(mx + d * 18, my - mr + 10); x.lineTo(mx + d * 100, my - 270); x.lineTo(mx + d * 30, my - 270); x.lineTo(mx - d * 40, my - mr + 4); x.closePath(); x.fill(); });
    x.fillStyle = gold(x, mx - 24, 0, mx + 24, 0); x.fillRect(mx - 30, my - mr - 18, 60, 22);
    PAL = M; x.beginPath(); x.arc(mx, my, mr, 0, 7); x.fillStyle = gold(x, mx - mr, my - mr, mx + mr, my + mr); x.fill();
    x.beginPath(); x.arc(mx, my, mr * .8, 0, 7); x.lineWidth = 3; x.strokeStyle = M[0]; x.stroke(); x.beginPath(); x.arc(mx, my, mr * .74, 0, 7); x.strokeStyle = M[2]; x.lineWidth = 1.5; x.stroke();
    x.beginPath(); x.arc(mx, my, mr * .68, 0, 7); x.fillStyle = EBLUE2; x.fill(); x.lineWidth = 3; x.strokeStyle = M[2]; x.stroke();
    for (let i = 0; i < 5; i++) { const a = -Math.PI / 2 + (i - 2) * .36; star(x, mx + Math.cos(a) * mr * .5, my + Math.sin(a) * mr * .5 + 2, mr * .07, M[2]); }
    T(String(k.rank), mx - 10, my + 30, `700 66px Georgia, serif`, M[2]); T(['', 'ST', 'ND', 'RD'][k.rank] || 'TH', mx + 26, my + 2, `700 22px Georgia, serif`, M[2]);
    T('RANK', mx, my + 54, `700 14px Georgia, serif`, M[2], 'center', 3); PAL = GOLD;
    // right: photo frame with a graduation cap
    const fx = W - 285, fy = 625, fw = 228, fh = 270;
    x.save(); x.shadowColor = 'rgba(0,0,0,.25)'; x.shadowBlur = 16; x.shadowOffsetY = 6; x.fillStyle = '#fff'; x.fillRect(fx - fw / 2 - 14, fy - fh / 2 - 14, fw + 28, fh + 28); x.restore();
    x.lineWidth = 6; x.strokeStyle = gold(x, fx - fw / 2, fy - fh / 2, fx + fw / 2, fy + fh / 2); x.strokeRect(fx - fw / 2 - 8, fy - fh / 2 - 8, fw + 16, fh + 16);
    const ph = await loadImg(await photo(k.st.id));
    x.save(); x.beginPath(); x.rect(fx - fw / 2, fy - fh / 2, fw, fh); x.clip();
    if (ph) { const sc = Math.max(fw / ph.width, fh / ph.height); x.drawImage(ph, fx - ph.width * sc / 2, fy - ph.height * sc / 2, ph.width * sc, ph.height * sc); }
    else { const ig = x.createLinearGradient(0, fy - fh / 2, 0, fy + fh / 2); ig.addColorStop(0, '#EEF3FC'); ig.addColorStop(1, '#D9E3F5'); x.fillStyle = ig; x.fillRect(fx - fw / 2, fy - fh / 2, fw, fh); T(k.st.name.split(/\s+/).map(w => w[0]).slice(0, 2).join('').toUpperCase(), fx, fy + 34, `700 96px Georgia, serif`, EBLUE2); }
    x.restore();
    x.save(); x.translate(fx - fw / 2 + 10, fy - fh / 2 - 6); x.rotate(-.32); icoCap(x, 0, 0, 150, '#14171F', true); x.restore();
    x.fillStyle = EBLUE; x.fillRect(fx - 120, fy + fh / 2 + 26, 240, 44); x.lineWidth = 2; x.strokeStyle = GOLD[1]; x.strokeRect(fx - 114, fy + fh / 2 + 31, 228, 34);
    T('RANK HOLDER', fx, fy + fh / 2 + 56, `700 20px Georgia, serif`, '#F6E3A1', 'center', 3);
    // signatures + QR
    const sy = 1022; x.strokeStyle = EBLUE2; x.lineWidth = 1.5;
    [[tx - 470, 'Class Teacher', D.ct], [tx, 'Principal', ''], [tx + 470, 'Chairman', '']].forEach(([cx, l, v]) => { x.beginPath(); x.moveTo(cx - 140, sy); x.lineTo(cx + 140, sy); x.stroke();
      if (v) T(fit(v, `40px ${SF}`, 270), cx, sy - 12, `40px ${SF}`, EBLUE2); T(l.toUpperCase(), cx, sy + 32, `700 18px Georgia, serif`, MUTE, 'center', 3); });
    const sgn = await signs(); if (sgn.P) { const im = await loadImg(sgn.P); if (im) { const h2 = 66, w2 = im.width * h2 / im.height; x.drawImage(im, tx - w2 / 2, sy - h2 - 4, w2, h2); } }
    const cert = (D.certs || {})[k.st.id];
    if (cert && window.qrcode) { try {
      const q = qrcode(0, 'M'); q.addData(new URL('verify-cert.php?c=' + cert.code, location.href).href); q.make();
      const nM = q.getModuleCount(), sz = 104, cell = sz / nM, qx = W - 214, qy = 916;
      x.fillStyle = '#fff'; x.fillRect(qx - 8, qy - 8, sz + 16, sz + 16); x.lineWidth = 2; x.strokeStyle = GOLD[3]; x.strokeRect(qx - 8, qy - 8, sz + 16, sz + 16);
      x.fillStyle = EBLUE2; for (let r = 0; r < nM; r++) for (let cc = 0; cc < nM; cc++) if (q.isDark(r, cc)) x.fillRect(qx + cc * cell, qy + r * cell, cell + .4, cell + .4);
      T('SCAN TO VERIFY', qx + sz / 2, qy + sz + 30, `700 13px Georgia, serif`, MUTE, 'center', 2);
    } catch (e) {} }
    let hsh = 0; for (const ch of 'HY|' + SES + '|' + k.st.id + '|' + k.rank) hsh = (hsh * 31 + ch.charCodeAt(0)) >>> 0;
    const dt = new Date().toLocaleDateString('en-IN', { day: 'numeric', month: 'long', year: 'numeric' });
    T(`Date: ${dt}   ·   Place: Bhadohi   ·   Certificate No. ${cert ? cert.no : 'SNMSIS/HY' + SES.slice(2, 4) + '/' + hsh.toString(36).toUpperCase().padStart(7, '0').slice(-7)}`, tx, H - 96, `600 17px Georgia, serif`, MUTE, 'center', 1);
    return c;
  }
  // ===== Three more certificate designs (owner, 20 Nov 2026: "format, colour, design sab change karo") — the owner picks one.
  //  Colourful trophy (owner's favourite) · Portrait Royal · Certificate of Merit (v6). Prashasti Patra was dropped by the owner.
  const CDESIGNS = [['auto', '★ Har certificate alag (Auto)'], ['bright', 'Sab Colourful trophy'], ['royal', 'Sab Portrait Royal'], ['merit', 'Sab Merit']];
  let CDESIGN = (() => { try { const v = localStorage.getItem('sch_cert_design'); return ['auto', 'bright', 'royal', 'merit'].includes(v) ? v : 'auto'; } catch (e) { return 'auto'; } })();
  function kit(x, W, H) {
    const T = (t, X, Y, f, col, al, sp) => { x.font = f; x.fillStyle = col; x.textAlign = al || 'center'; if ('letterSpacing' in x) x.letterSpacing = (sp || 0) + 'px'; x.fillText(String(t), X, Y); if ('letterSpacing' in x) x.letterSpacing = '0px'; };
    const fit = (t, f, w) => { x.font = f; let s2 = t; while (x.measureText(s2).width > w && s2.length > 4) s2 = s2.slice(0, -2) + '…'; return s2; };
    const mixed = (parts, cx, Y, f) => { x.font = f; const ws = parts.map(([t]) => x.measureText(t).width), tot = ws.reduce((a2, b2) => a2 + b2, 0); let X = cx - tot / 2; parts.forEach(([t, col], i) => { T(t, X, Y, f, col, 'left'); X += ws[i]; }); };
    const ini = k => k.st.name.split(/\s+/).map(w => w[0]).slice(0, 2).join('').toUpperCase();
    async function pic(k, cx, cy, r, rect, h2, bgc, inkc) {
      const ph = await loadImg(await photo(k.st.id)); x.save(); x.beginPath(); rect ? x.rect(cx - r, cy - h2, 2 * r, 2 * h2) : x.arc(cx, cy, r, 0, 7); x.clip();
      const ww = 2 * r, hh = rect ? 2 * h2 : 2 * r;
      if (ph) { const sc = Math.max(ww / ph.width, hh / ph.height); x.drawImage(ph, cx - ph.width * sc / 2, cy - ph.height * sc / 2, ph.width * sc, ph.height * sc); }
      else { x.fillStyle = bgc; x.fillRect(cx - ww / 2, cy - hh / 2, ww, hh); T(ini(k), cx, cy + r * .28, `700 ${r * .78}px Georgia, serif`, inkc); }
      x.restore();
    }
    function qr(D, k, qx, qy, sz, ink, frame, lab) { const cert = (D.certs || {})[k.st.id]; if (!cert || !window.qrcode) return; try {
      const q = qrcode(0, 'M'); q.addData(new URL('verify-cert.php?c=' + cert.code, location.href).href); q.make(); const nM = q.getModuleCount(), cell = sz / nM;
      x.fillStyle = '#fff'; x.fillRect(qx - 8, qy - 8, sz + 16, sz + 16); x.lineWidth = 2; x.strokeStyle = frame; x.strokeRect(qx - 8, qy - 8, sz + 16, sz + 16);
      x.fillStyle = ink; for (let r = 0; r < nM; r++) for (let cc = 0; cc < nM; cc++) if (q.isDark(r, cc)) x.fillRect(qx + cc * cell, qy + r * cell, cell + .4, cell + .4);
      T('SCAN TO VERIFY', qx + sz / 2, qy + sz + 28, `700 13px Georgia, serif`, lab, 'center', 2); } catch (e) {} }
    function certNo(D, k) { const cert = (D.certs || {})[k.st.id]; if (cert) return cert.no; let h = 0; for (const ch of 'HY|' + SES + '|' + k.st.id + '|' + k.rank) h = (h * 31 + ch.charCodeAt(0)) >>> 0; return 'SNMSIS/HY' + SES.slice(2, 4) + '/' + h.toString(36).toUpperCase().padStart(7, '0').slice(-7); }
    async function sigs(D, list, sy, ink, lab, w) { x.strokeStyle = ink; x.lineWidth = 1.5;
      list.forEach(([cx, l, v]) => { x.beginPath(); x.moveTo(cx - w / 2, sy); x.lineTo(cx + w / 2, sy); x.stroke(); if (v) T(fit(v, `40px ${SF}`, w), cx, sy - 12, `40px ${SF}`, ink); T(l.toUpperCase(), cx, sy + 30, `700 17px Georgia, serif`, lab, 'center', 3); });
      const sg = await signs(); const pr = list.find(z => z[1] === 'Principal'); if (sg.P && pr) { const im = await loadImg(sg.P); if (im) { const h2 = 62, w2 = im.width * h2 / im.height; x.drawImage(im, pr[0] - w2 / 2, sy - h2 - 4, w2, h2); } } }
    const dt = () => new Date().toLocaleDateString('en-IN', { day: 'numeric', month: 'long', year: 'numeric' });
    const parents = k => [k.st.father ? 'Shri ' + k.st.father : '', k.st.mother ? 'Smt. ' + k.st.mother : ''].filter(Boolean).join(' & ');
    return { T, fit, mixed, pic, qr, certNo, sigs, dt, parents };
  }
  // ---- Design 1: Portrait Royal — A4 portrait, royal blue page, ivory card, gold frame, big photo in a laurel, red rank ribbon
  async function certRoyal(k, D) {
    await fonts(); const W = 1240, H = 1754, c = document.createElement('canvas'); c.width = W; c.height = H; const x = c.getContext('2d'), K = kit(x, W, H), cx = W / 2;
    const RP = RPAL[CV], BL = RP[2], RED = '#B71C1C', INK = '#2A2F3A', MU = '#5E5646'; PAL = METAL[k.rank] || GOLD; const M = PAL; PAL = GOLD;
    const g0 = x.createLinearGradient(0, 0, W, H); g0.addColorStop(0, RP[0]); g0.addColorStop(.5, RP[1]); g0.addColorStop(1, RP[0]); x.fillStyle = g0; x.fillRect(0, 0, W, H);
    for (let i = 0; i < 4; i++) guilloche(x, [0, W, 0, W][i], [0, 0, H, H][i], 260, 37, 223, GOLD[1], .22, 1);
    // ivory card
    const m = 70; x.save(); x.shadowColor = 'rgba(0,0,0,.4)'; x.shadowBlur = 30; const pg = x.createRadialGradient(cx, H * .45, 100, cx, H * .45, 900); pg.addColorStop(0, '#FFFEF8'); pg.addColorStop(1, '#F5EAD0'); x.fillStyle = pg; x.fillRect(m, m, W - 2 * m, H - 2 * m); x.restore();
    x.lineWidth = 8; x.strokeStyle = gold(x, 0, 0, W, H); x.strokeRect(m + 18, m + 18, W - 2 * m - 36, H - 2 * m - 36); x.lineWidth = 2; x.strokeRect(m + 32, m + 32, W - 2 * m - 64, H - 2 * m - 64);
    [[m + 44, m + 44, 0], [W - m - 44, m + 44, Math.PI / 2], [W - m - 44, H - m - 44, Math.PI], [m + 44, H - m - 44, -Math.PI / 2]].forEach(([a, b2, r]) => corner(x, a, b2, r, GOLD[3]));
    const logo = await loadImg('img/snmsis-logo.png'); if (logo) { x.save(); x.globalAlpha = .05; x.drawImage(logo, cx - 330, H * .5 - 280, 660, 660); x.restore(); }
    if (logo) { x.save(); x.beginPath(); x.arc(cx, 210, 72, 0, 7); x.fillStyle = '#fff'; x.fill(); x.lineWidth = 6; x.strokeStyle = gold(x, cx - 72, 138, cx + 72, 282); x.stroke(); x.clip(); x.drawImage(logo, cx - 66, 144, 132, 132); x.restore(); }
    { let fs = 40; x.font = `700 ${fs}px Georgia, serif`; while (x.measureText(SCHOOL.toUpperCase()).width > 900) { fs--; x.font = `700 ${fs}px Georgia, serif`; } K.T(SCHOOL.toUpperCase(), cx, 340, `700 ${fs}px Georgia, serif`, BL, 'center', 1); }
    K.T(PLACE + '  ·  Session ' + SESL, cx, 378, `italic 24px Georgia, serif`, MU);
    x.font = `700 112px Georgia, serif`; x.textAlign = 'center'; if ('letterSpacing' in x) x.letterSpacing = '10px'; x.fillStyle = gold(x, cx - 400, 400, cx + 400, 500); x.fillText('CERTIFICATE', cx + 5, 500); x.lineWidth = 1.2; x.strokeStyle = GOLD[0]; x.strokeText('CERTIFICATE', cx + 5, 500); if ('letterSpacing' in x) x.letterSpacing = '0px';
    K.T('OF ACHIEVEMENT', cx, 552, `700 30px Georgia, serif`, BL, 'center', 10);
    // photo in laurel
    const py = 770, pr = 150; laurel(x, cx, py + 12, pr + 62, -1, M[3]); laurel(x, cx, py + 12, pr + 62, 1, M[3]);
    x.beginPath(); x.arc(cx, py, pr + 14, 0, 7); x.fillStyle = gold(x, cx - pr, py - pr, cx + pr, py + pr); x.fill(); x.beginPath(); x.arc(cx, py, pr + 4, 0, 7); x.fillStyle = '#fff'; x.fill();
    await K.pic(k, cx, py, pr, false, 0, '#E9EEF7', BL);
    // red rank ribbon across the bottom of the photo
    const ry = py + pr + 22, rw = 420; x.fillStyle = '#8E1414'; [[-1], [1]].forEach(([d]) => { x.beginPath(); x.moveTo(cx + d * (rw / 2 - 20), ry + 14); x.lineTo(cx + d * (rw / 2 + 50), ry + 14); x.lineTo(cx + d * (rw / 2 + 30), ry + 44); x.lineTo(cx + d * (rw / 2 + 50), ry + 74); x.lineTo(cx + d * (rw / 2 - 20), ry + 74); x.closePath(); x.fill(); });
    x.fillStyle = RED; x.fillRect(cx - rw / 2, ry, rw, 62); x.lineWidth = 2; x.strokeStyle = GOLD[1]; x.strokeRect(cx - rw / 2 + 7, ry + 6, rw - 14, 50);
    K.T(`★  ${ORD[k.rank]} RANK  ★`, cx, ry + 43, `700 32px Georgia, serif`, '#FFE9A8', 'center', 4);
    K.T('This is proudly presented to', cx, 1060, `italic 30px Georgia, serif`, MU);
    K.T(K.fit(k.st.name, `116px ${SF}`, 920), cx, 1170, `116px ${SF}`, BL); x.fillStyle = gold(x, cx - 360, 0, cx + 360, 0); x.fillRect(cx - 360, 1196, 720, 3);
    const par = K.parents(k); let yy = 1246; if (par) { K.T(K.fit('ward of ' + par, `26px Georgia, serif`, 940), cx, yy, `26px Georgia, serif`, INK); yy += 40; }
    K.T(`Class ${k.st.cls}${k.st.section ? '-' + k.st.section : ''}  ·  Adm. No. ${k.st.adm || '—'}`, cx, yy, `26px Georgia, serif`, INK);
    K.T(`for outstanding performance in the Half-Yearly Examination ${SESL}`, cx, yy + 50, `28px Georgia, serif`, INK);
    K.mixed([['securing ', BL], [`${k.rpct}% marks`, RED], [' and the ', BL], [`${ORD[k.rank]} position`, RED], [' in the class.', BL]], cx, yy + 94, `700 30px Georgia, serif`);
    await K.sigs(D, [[cx - 370, 'Class Teacher', D.ct], [cx, 'Principal', ''], [cx + 370, 'Chairman', '']], 1466, BL, MU, 240);
    K.qr(D, k, 236, 1536, 70, BL, GOLD[3], MU);
    K.T(`Date: ${K.dt()}`, W - m - 110, 1600, `600 16px Georgia, serif`, MU, 'right', 1);
    K.T(`Certificate No. ${K.certNo(D, k)}`, W - m - 110, 1628, `600 16px Georgia, serif`, MU, 'right', 1);
    return c;
  }
  // ---- Design 2: Colourful Achievement — white page, layered colour waves (blue, sky, yellow, orange), confetti, a big gold trophy with the rank
  function trophy(x, cx, cy, s2, M, rank, T) {
    x.save(); x.translate(cx, cy); const g = x.createLinearGradient(-s2, -s2, s2, s2); g.addColorStop(0, M[0]); g.addColorStop(.4, M[2]); g.addColorStop(.6, M[1]); g.addColorStop(1, M[0]);
    x.fillStyle = g; x.strokeStyle = M[0]; x.lineWidth = s2 * .05;
    [-1, 1].forEach(d => { x.beginPath(); x.arc(d * s2 * .62, -s2 * .45, s2 * .26, d > 0 ? -Math.PI / 2 : Math.PI / 2, d > 0 ? Math.PI / 2 : Math.PI * 1.5, false); x.stroke(); });
    x.beginPath(); x.moveTo(-s2 * .62, -s2 * .8); x.lineTo(s2 * .62, -s2 * .8); x.quadraticCurveTo(s2 * .6, s2 * .05, 0, s2 * .18); x.quadraticCurveTo(-s2 * .6, s2 * .05, -s2 * .62, -s2 * .8); x.fill();
    x.fillRect(-s2 * .1, s2 * .15, s2 * .2, s2 * .32); x.fillRect(-s2 * .38, s2 * .45, s2 * .76, s2 * .14); x.fillStyle = '#5A3A10'; x.fillRect(-s2 * .5, s2 * .59, s2 * 1, s2 * .2);
    T(String(rank) + (['', 'st', 'nd', 'rd'][rank] || 'th'), 0, -s2 * .3, `700 ${s2 * .42}px Poppins, Arial, sans-serif`, '#fff'); T('RANK', 0, s2 * .73, `700 ${s2 * .12}px Poppins, Arial, sans-serif`, '#FFE9A8', 'center', 3);
    x.restore();
  }
  async function certBright(k, D) {
    await fonts(); const W = 1754, H = 1240, c = document.createElement('canvas'); c.width = W; c.height = H; const x = c.getContext('2d'), K = kit(x, W, H);
    const BP = BPAL[CV], NV = BP.nv, OR = BP.acc, INK = '#2E3440', MU = '#5F6B7A';
    x.fillStyle = '#FFFFFF'; x.fillRect(0, 0, W, H);
    const wave = (pts, col) => { x.fillStyle = col; x.beginPath(); x.moveTo(pts[0][0], pts[0][1]); for (let i = 1; i < pts.length; i += 3) x.bezierCurveTo(...pts[i], ...pts[i + 1], ...pts[i + 2]); x.closePath(); x.fill(); };
    // top-left waves
    wave([[0, 0], [520, 0], [420, 120], [300, 160], [180, 260], [80, 360], [0, 420]], BP.d);
    wave([[0, 0], [380, 0], [300, 90], [200, 120], [120, 200], [60, 260], [0, 300]], BP.m);
    wave([[0, 0], [240, 0], [190, 60], [120, 80], [70, 130], [30, 170], [0, 190]], BP.a);
    // bottom-right waves
    wave([[W, H], [W - 560, H], [W - 450, H - 130], [W - 320, H - 170], [W - 190, H - 280], [W - 80, H - 380], [W, H - 440]], BP.d);
    wave([[W, H], [W - 400, H], [W - 320, H - 95], [W - 210, H - 125], [W - 130, H - 210], [W - 60, H - 270], [W, H - 310]], BP.b);
    wave([[W, H], [W - 250, H], [W - 200, H - 60], [W - 130, H - 85], [W - 75, H - 135], [W - 30, H - 175], [W, H - 195]], BP.a);
    // confetti
    let seed = 5; const rnd = () => (seed = (seed * 16807) % 2147483647) / 2147483647; const CC = [BP.a, '#FF7043', '#26A69A', '#42A5F5', '#AB47BC', '#EC407A'];
    for (let i = 0; i < 90; i++) { const px = rnd() * W, py = rnd() * H; if (px > 160 && px < W - 160 && py > 110 && py < H - 110) continue; x.save(); x.translate(px, py); x.rotate(rnd() * 6); x.fillStyle = CC[i % 6]; x.globalAlpha = .85; i % 3 ? x.fillRect(-7, -3, 14, 6) : (star(x, 0, 0, 9, CC[i % 6])); x.restore(); }
    x.lineWidth = 3; x.strokeStyle = BP.d; x.strokeRect(36, 36, W - 72, H - 72);
    const logo = await loadImg('img/snmsis-logo.png');
    // header
    if (logo) { x.save(); x.beginPath(); x.arc(W / 2, 128, 60, 0, 7); x.fillStyle = '#fff'; x.fill(); x.lineWidth = 5; x.strokeStyle = BP.a; x.stroke(); x.clip(); x.drawImage(logo, W / 2 - 55, 73, 110, 110); x.restore(); }
    K.T(SCHOOL.toUpperCase(), W / 2, 238, `700 40px Poppins, Arial, sans-serif`, NV, 'center', 1); K.T(PLACE + '  ·  Session ' + SESL, W / 2, 274, `500 22px Poppins, Arial, sans-serif`, MU);
    K.T('CERTIFICATE', W / 2 - 150, 384, `700 96px Poppins, Arial, sans-serif`, NV, 'center', 6);
    K.T('OF ACHIEVEMENT', W / 2 - 150, 432, `600 30px Poppins, Arial, sans-serif`, OR, 'center', 12);
    // text column (left/centre), trophy + photo (right)
    const tx = W / 2 - 150;
    K.T('This certificate is proudly awarded to', tx, 506, `500 26px Poppins, Arial, sans-serif`, MU);
    K.T(K.fit(k.st.name, `112px ${SF}`, 860), tx, 612, `112px ${SF}`, NV); x.fillStyle = OR; x.fillRect(tx - 330, 636, 660, 4);
    const par = K.parents(k); let yy = 684; if (par) { K.T(K.fit('ward of ' + par, `500 24px Poppins, Arial, sans-serif`, 880), tx, yy, `500 24px Poppins, Arial, sans-serif`, INK); yy += 38; }
    K.T(`Class ${k.st.cls}${k.st.section ? '-' + k.st.section : ''}  ·  Adm. No. ${k.st.adm || '—'}`, tx, yy, `500 24px Poppins, Arial, sans-serif`, INK);
    K.mixed([['for securing the ', NV], [`${ORD[k.rank]} RANK`, OR], [' in the class', NV]], tx, yy + 60, `700 36px Poppins, Arial, sans-serif`);
    K.T(`in the Half-Yearly Examination ${SESL} with ${k.rpct}% marks.`, tx, yy + 106, `500 25px Poppins, Arial, sans-serif`, INK);
    K.T('Keep shining and keep learning!', tx, yy + 150, `italic 600 24px Georgia, serif`, '#26A69A');
    // right: photo above trophy
    const rx = W - 380; x.beginPath(); x.arc(rx, 400, 128, 0, 7); x.fillStyle = BP.a; x.fill(); x.beginPath(); x.arc(rx, 400, 116, 0, 7); x.fillStyle = '#fff'; x.fill();
    await K.pic(k, rx, 400, 108, false, 0, '#E8F1FD', NV);
    trophy(x, rx, 700, 150, METAL[k.rank] || GOLD, k.rank, K.T);
    await K.sigs(D, [[tx - 440, 'Class Teacher', D.ct], [tx, 'Principal', ''], [tx + 440, 'Chairman', '']], 1010, NV, MU, 250);
    K.qr(D, k, W - 470, 940, 92, NV, BP.a, MU);
    K.T(`Date: ${K.dt()}   ·   Certificate No. ${K.certNo(D, k)}`, tx, H - 90, `500 16px Poppins, Arial, sans-serif`, MU);
    return c;
  }
  // "Har certificate alag design aur rang" (owner, 20 Nov 2026: "colourful trophy wala best; design 3 mat do; sab certificate alag
  // design aur colour ke"): AUTO — 1st rank = Colourful trophy, 2nd = Portrait Royal, 3rd = Merit; the colour set turns with the
  // class (and the child's place in the list), so no two certificates of a class — or of neighbouring classes — look the same.
  const BPAL = [{ d: '#1A3E8C', m: '#2E7DD7', a: '#FFC107', b: '#FF8F00', nv: '#13235B', acc: '#F57C00' }, { d: '#5B2A86', m: '#9C4DCC', a: '#F8BBD0', b: '#EC407A', nv: '#3B1A5A', acc: '#D81B60' },
    { d: '#0B6E4F', m: '#26A69A', a: '#D4E157', b: '#FFB300', nv: '#0B4436', acc: '#00897B' }, { d: '#B71C1C', m: '#E64A19', a: '#FFCA28', b: '#FB8C00', nv: '#7F1D1D', acc: '#E65100' }];
  const RPAL = [['#0B1F4D', '#1A3E8C', '#0F2A66'], ['#3E0A14', '#7A1830', '#5A1020'], ['#05281F', '#0E5A45', '#0B3D2E'], ['#1E0F40', '#4A2582', '#2E1660']];
  const MPAL = [['#1A3E8C', '#0F2A66'], ['#00796B', '#004D40'], ['#4A2582', '#2E1660'], ['#8E1B32', '#5A1020']];
  let CV = 0;
  async function certCanvas(k, D, i) {
    CV = (Math.max(0, CLASSES.indexOf(k.st.cls)) + (i || 0)) % 4;
    const d = CDESIGN === 'auto' ? (k.rank === 1 ? 'bright' : k.rank === 2 ? 'royal' : 'merit') : CDESIGN;
    return d === 'bright' ? certBright(k, D) : d === 'merit' ? certMerit(k, D) : certRoyal(k, D);
  }
  const certIsPortrait = k => (CDESIGN === 'auto' ? (k.rank === 2 ? 'royal' : '') : CDESIGN) === 'royal';
  async function rankers(D) {
    const top = D.ranked.filter(k => k.rank <= 3);
    if (!top.length) { $('out').innerHTML = '<div class="pan msg">No child has every subject entered yet — rankers come when all marks of the class are in.</div>'; return; }
    $('out').innerHTML = `<div class="pan np"><b>Class ${esc(CLS)} rankers</b> · on the real marks (no grace) · ${D.full} subjects each. <button class="btn y" id="cprint">🖨 Print all certificates</button> <button class="btn" id="stars">⭐ Our Stars poster (all classes)</button>
      <div class="row" style="margin-top:8px"><b>Certificate design:</b>${CDESIGNS.map(([kk, l]) => `<button class="btn ${kk === CDESIGN ? '' : 'l'}" data-cd="${kk}" style="padding:6px 10px">${esc(l)}</button>`).join('')}</div></div><div id="sp"></div><div class="certs" id="cl"></div>`;
    $('stars').onclick = () => starsPoster();
    document.querySelectorAll('[data-cd]').forEach(b => { b.onclick = () => { CDESIGN = b.dataset.cd; try { localStorage.setItem('sch_cert_design', CDESIGN); } catch (e) {} rankers(D); }; });
    if (!D.certs) { try { D.certs = (await get(`progress_api.php?a=certs&ses=${SES}&cls=${encodeURIComponent(CLS)}`)).certs || {}; } catch (e) { D.certs = {}; } }
    const urls = [];
    for (const [i, k] of top.entries()) {
      const c = await certCanvas(k, D, i), url = c.toDataURL('image/jpeg', 0.92); urls.push([url, certIsPortrait(k)]);
      const d = document.createElement('div'); d.className = 'cert';
      d.innerHTML = `<h4>${k.rank}${['', 'st', 'nd', 'rd'][k.rank]} · ${esc(k.st.name)} · ${k.pct}%</h4><img src="${url}" alt="Certificate"><div class="row" style="margin-top:8px"><button class="btn g" data-s>Share</button><a class="btn l" download="Certificate-${esc(k.st.name.replace(/\W+/g, '-'))}.jpg" href="${url}">Download</a></div>`;
      d.querySelector('[data-s]').onclick = () => share(c, 'Certificate-' + k.st.name.replace(/\W+/g, '-') + '.png', `${k.st.name} — ${ORD[k.rank]} position, Class ${k.st.cls}, Half-Yearly ${SESL}`);
      d.querySelector('img').onclick = () => { const z = window.open(''); if (z) { z.document.write(`<img src="${url}" style="width:100%">`); z.document.close(); } };
      $('cl').appendChild(d);
    }
    $('cprint').onclick = () => { const w = window.open('', '_blank'); if (!w) return alert('Allow pop-ups to print.');
      w.document.write(`<!doctype html><html><head><title>Certificates</title><style>@page{size:A4 landscape;margin:0}@page port{size:A4 portrait;margin:0}body{margin:0}img{width:100%;display:block;page-break-after:always}img.p{page:port;width:auto;height:100vh;margin:0 auto}</style></head><body>${urls.map(([u, pt]) => `<img class="${pt ? 'p' : ''}" src="${u}">`).join('')}<script>setTimeout(print,600)<\/script></body></html>`); w.document.close(); };
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
