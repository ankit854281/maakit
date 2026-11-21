'use strict';
// PTM Desk (owner, 21 Nov 2026: "parents aa rahe hain — entry, feedback, fee, complaint; Pawan Bhalla Sir naam note karein,
// result ke hisaab se suggestion ke saath baat karein aur fill karein"). Data: ptm_api.php. Records: ptmVisit 'date|sid'.
(function () {
  const $ = id => document.getElementById(id);
  const esc = s => String(s == null ? '' : s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
  const CLASSES = ['PG', 'LKG', 'UKG', '1st', '2nd', '3rd', '4th', '5th', '6th', '7th', '8th', '9th', '10th'];
  const MON = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
  const MOODS = [[4, '😀', 'Very satisfied'], [3, '🙂', 'Satisfied'], [2, '😐', 'Neutral'], [1, '😟', 'Dissatisfied']];
  const rs = n => '₹' + Math.round(n || 0).toLocaleString('en-IN');
  const today = () => { const d = new Date(); return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0'); };
  const tok = () => { try { return localStorage.getItem('sch_token') || ''; } catch (e) { return ''; } };
  let TAB = 'desk', DATE = today(), CLS = (() => { try { return localStorage.getItem('ptm_cls') || '1st'; } catch (e) { return '1st'; } })(), DATA = {}, SEL = null, F = null, Q = '', REP = null;
  async function api(path, body) {
    const r = await fetch(path, { method: body ? 'POST' : 'GET', cache: 'no-store', headers: { 'Content-Type': 'application/json', 'X-School-Token': tok() }, ...(body ? { body: JSON.stringify(body) } : {}) });
    let j = null; try { j = await r.json(); } catch (e) {}
    if (r.status === 401) throw Error('Please log in to the ERP or the Teacher App first, then open this page again.');
    if (!r.ok || !j || !j.ok) throw Error((j && j.error) || 'Server problem. Please try again.'); return j;
  }
  function toast(t) { const d = document.createElement('div'); d.className = 'toast'; d.textContent = t; document.body.appendChild(d); setTimeout(() => d.remove(), 2600); }
  const app = h => { $('app').innerHTML = h; };

  // ---------- talking points from the result, attendance and fee ----------
  function points(k) {
    const P = [], first = k.name.split(/\s+/)[0], r = k.res;
    if (r && r.pct != null) {
      if (r.rank && r.rank <= 3) P.push(`🎉 Secured the <b>${r.rank}${['', 'st', 'nd', 'rd'][r.rank]} rank</b> in class (${r.pct}%). Congratulate the parents.`);
      else P.push(`Half-Yearly result: <b>${r.pct}%</b>${r.rank ? ` · rank ${r.rank} of ${r.of}` : ''}.`);
      const strong = r.subs.filter(s => !s.ab && s.t >= 80).map(s => s.s), weak = r.subs.filter(s => !s.ab && s.t < 40), ab = r.subs.filter(s => s.ab).map(s => s.s);
      if (strong.length) P.push(`👍 Strong in <b>${esc(strong.join(', '))}</b> — appreciate ${esc(first)}'s effort.`);
      weak.forEach(s => P.push(`📘 <b>${esc(s.s)}: ${s.t}/100</b> — recommend 30 minutes of daily practice and a regular homework check at home.`));
      if (!weak.length) { const low = r.subs.filter(s => !s.ab && s.t < 55).map(s => s.s); if (low.length) P.push(`📗 <b>${esc(low.join(', '))}</b> needs a little more attention — the grade can improve in the Final Examination.`); }
      if (ab.length) P.push(`⚠️ Absent in the <b>${esc(ab.join(', '))}</b> exam — please ensure attendance in the Final Examination.`);
      if (r.nbLow >= 2) P.push('✍️ Notebook work is incomplete — request the parents to check the notebooks daily.');
    } else P.push('The result is not yet entered in the ERP — please refer to the class teacher\'s slip.');
    if (k.att && k.att.days) { const p = Math.round(k.att.present * 100 / k.att.days); if (p < 85) P.push(`🗓️ Attendance is <b>${p}%</b> (${k.att.days - k.att.present} days absent) — regular attendance is essential.`); else if (p >= 95) P.push(`🗓️ Excellent attendance (<b>${p}%</b>) — thank the parents.`); }
    if (k.fee) { if (k.fee.due > 0) P.push(`💰 Fee due: <b>${rs(k.fee.due)}</b>${k.fee.months.length ? ' (' + k.fee.months.map(m => MON[m]).join(', ') + ')' : ''} — request payment today or obtain a committed date.`); else P.push('💰 Fee is paid up to date — thank the parents.'); }
    return P;
  }

  // ---------- desk ----------
  async function loadCls(force) {
    if (DATA[CLS] && !force) return DATA[CLS];
    DATA[CLS] = await api(`ptm_api.php?a=cls&cls=${encodeURIComponent(CLS)}&date=${DATE}`); return DATA[CLS];
  }
  async function desk() {
    app(`<div class="chips">${CLASSES.map(c => `<button class="${c === CLS ? 'on' : ''}" data-c="${c}">${c}</button>`).join('')}</div><div id="lst"><div class="msg">Opening class ${esc(CLS)}…</div></div>`);
    document.querySelectorAll('[data-c]').forEach(b => { b.onclick = () => { CLS = b.dataset.c; try { localStorage.setItem('ptm_cls', CLS); } catch (e) {} SEL = null; desk(); }; });
    let D; try { D = await loadCls(); } catch (e) { $('lst').innerHTML = `<div class="msg">${esc(e.message)}</div>`; return; }
    if (SEL) return kid(D.kids.find(k => k.id === SEL));
    const came = D.kids.filter(k => k.visit).length;
    $('lst').innerHTML = `<input class="search" id="q" placeholder="🔍 Search student or father's name…" value="${esc(Q)}"><p style="margin:0 0 6px;color:#5a6b80;font-size:12.5px"><b>${came}</b> of ${D.kids.length} parents attended · tap a student to record the visit</p><div id="kl"></div>`;
    const draw = () => { const q = Q.toLowerCase(); $('kl').innerHTML = D.kids.filter(k => !q || (k.name + ' ' + k.father).toLowerCase().includes(q)).map(k => {
      const v = k.visit, due = k.fee && k.fee.due > 0;
      return `<div class="kid ${v ? 'came' : ''}" data-k="${esc(k.id)}"><div class="n"><b>${esc(k.name)}</b><small>${esc(k.father ? 'Pita: ' + k.father : '')}${k.res && k.res.pct != null ? ' · ' + k.res.pct + '%' : ''}</small></div><div class="st">${v ? `<span class="pill p-ok">✓ ${esc(v.at)}</span>` : ''}${v && v.cmp && v.cmp.status !== 'Resolved' ? '<span class="pill p-c">Complaint</span>' : ''}${due ? `<span class="pill p-f">${rs(k.fee.due)}</span>` : ''}</div></div>`; }).join('') || '<div class="msg">No student found.</div>';
      document.querySelectorAll('[data-k]').forEach(el => { el.onclick = () => { SEL = el.dataset.k; kid(D.kids.find(k => k.id === SEL)); scrollTo(0, 0); }; }); };
    $('q').oninput = e => { Q = e.target.value; draw(); }; draw();
  }
  function kid(k) {
    if (!k) { SEL = null; return desk(); }
    const D = DATA[CLS], v = k.visit || {}, r = k.res;
    F = { parent: v.parent || k.father || '', phone: v.phone || k.phone || '', slip: !!v.slip, paid: v.paid || '', promise: v.promise || '', pamt: v.pamt || '', mood: v.mood || 0, tags: (v.tags || []).slice(), note: v.note || '', complaint: (v.cmp && v.cmp.text) || '', cat: (v.cmp && v.cmp.cat) || '', cdue: (v.cmp && v.cmp.due) || '', siblings: true };
    const att = k.att && k.att.days ? Math.round(k.att.present * 100 / k.att.days) : null;
    $('lst').innerHTML = `<button class="btn l s" id="back">← Class ${esc(CLS)}</button>
      <div class="panel" style="margin-top:8px"><div class="ph"><div style="flex:1"><h2>${esc(k.name)}</h2><p>Class ${esc(CLS)}${k.section ? '-' + esc(k.section) : ''} · Adm. ${esc(k.adm || '—')}</p><p>Father: <b>${esc(k.father || '—')}</b>${k.mother ? ' · Mother: <b>' + esc(k.mother) + '</b>' : ''}</p>
        ${k.sib.length ? `<p>👨‍👩‍👧 Siblings: ${k.sib.map(s => esc(s.name) + ' (' + esc(s.cls) + ')').join(', ')}</p>` : ''}</div>${k.phone ? `<a class="ic c" href="tel:+91${k.phone}">📞 Call</a>` : ''}</div>
        <div class="stats"><div class="${r && r.pct != null && r.pct < 40 ? 'bad' : r && r.pct >= 80 ? 'good' : ''}"><b>${r && r.pct != null ? r.pct + '%' : '—'}</b><small>Result${r && r.rank ? ' · Rank ' + r.rank : ''}</small></div>
          <div class="${att != null && att < 85 ? 'bad' : att >= 95 ? 'good' : ''}"><b>${att != null ? att + '%' : '—'}</b><small>Attendance</small></div>
          <div class="${k.fee && k.fee.due > 0 ? 'bad' : 'good'}"><b>${k.fee ? rs(k.fee.due) : '—'}</b><small>Fee due</small></div></div>
        ${r && r.subs.length ? `<div class="subs">${r.subs.map(s => `<span class="${s.ab || s.t < 40 ? 'w' : s.t >= 80 ? 's' : ''}">${esc(s.s)} ${s.ab ? 'AB' : s.t}</span>`).join('')}</div>` : ''}
        <div class="talk"><b>🗣️ Discussion points for the parents</b><ul>${points(k).map(p => `<li>${p}</li>`).join('')}</ul></div>
        <div class="row2"><div><label class="l">Parent / guardian name</label><input class="i" id="fParent" value="${esc(F.parent)}"></div><div><label class="l">Mobile</label><input class="i" id="fPhone" inputmode="numeric" value="${esc(F.phone)}"></div></div>
        <label class="l">Visit</label><div class="tog"><button id="fSlip" class="${F.slip ? 'on' : ''}">📄 Result slip shown</button>${k.sib.length ? `<button id="fSib" class="on">👨‍👩‍👧 Mark siblings as attended ✓</button>` : ''}</div>
        <div class="row2"><div><label class="l">Fee received today (₹)</label><input class="i" id="fPaid" inputmode="numeric" value="${esc(F.paid)}" placeholder="0"></div><div><label class="l">Committed payment date</label><input class="i" type="date" id="fPromise" value="${esc(F.promise)}"></div></div>
        <label class="l">Committed amount (₹)</label><input class="i" id="fPamt" inputmode="numeric" value="${esc(F.pamt)}" placeholder="${k.fee && k.fee.due > 0 ? Math.round(k.fee.due) : ''}">
        <label class="l">Parent satisfaction</label><div class="mood">${MOODS.map(([n, e, t]) => `<button data-m="${n}" title="${t}" class="${F.mood === n ? 'on' : ''}">${e}</button>`).join('')}</div>
        <label class="l">Topics discussed</label><div class="tog tagb">${D.tags.map(t => `<button data-tg="${esc(t)}" class="${F.tags.includes(t) ? 'on' : ''}">${esc(t)}</button>`).join('')}</div>
        <label class="l">Feedback and advice given</label><textarea class="i" id="fNote" placeholder="e.g. Parent requested extra support in Maths; advised 30 minutes of daily practice.">${esc(F.note)}</textarea>
        <div class="cbox"><label class="l">⚠️ Complaint (if any)</label><textarea class="i" id="fCmp" placeholder="Record the complaint here — it will be reviewed by the Principal and the Chairman.">${esc(F.complaint)}</textarea>
          <div class="row2" style="margin-top:6px"><select class="i" id="fCat"><option value="">Category</option>${D.tags.map(t => `<option ${F.cat === t ? 'selected' : ''}>${esc(t)}</option>`).join('')}</select><input class="i" type="date" id="fCdue" value="${esc(F.cdue)}" title="Target resolution date"></div>
          ${v.cmp ? `<p style="margin:6px 0 0;font-size:12px">Status: <b>${esc(v.cmp.status)}</b>${v.cmp.action ? ' · ' + esc(v.cmp.action) : ''}</p>` : ''}</div>
        <div class="bar"><button class="btn g" id="save">✓ Save visit</button></div></div>`;
    $('back').onclick = () => { SEL = null; desk(); };
    $('fSlip').onclick = e => { F.slip = !F.slip; e.target.classList.toggle('on', F.slip); };
    if ($('fSib')) $('fSib').onclick = e => { F.siblings = !F.siblings; e.target.classList.toggle('on', F.siblings); };
    document.querySelectorAll('[data-m]').forEach(b => { b.onclick = () => { F.mood = +b.dataset.m; document.querySelectorAll('[data-m]').forEach(x => x.classList.toggle('on', +x.dataset.m === F.mood)); }; });
    document.querySelectorAll('[data-tg]').forEach(b => { b.onclick = () => { const t = b.dataset.tg; F.tags = F.tags.includes(t) ? F.tags.filter(x => x !== t) : [...F.tags, t]; b.classList.toggle('on'); }; });
    $('save').onclick = async () => {
      const body = { date: DATE, sid: k.id, parent: $('fParent').value, phone: $('fPhone').value, slip: F.slip, paid: $('fPaid').value, promise: $('fPromise').value, pamt: $('fPamt').value, mood: F.mood, tags: F.tags, note: $('fNote').value,
        complaint: $('fCmp').value, cat: $('fCat').value, cdue: $('fCdue').value, clearComplaint: !$('fCmp').value.trim(), siblings: k.sib.length ? F.siblings : false };
      $('save').disabled = true; $('save').textContent = 'Saving…';
      try { const d = await api('ptm_api.php?a=visit', body); k.visit = d.visit;
        Object.values(DATA).forEach(C => (C.kids || []).forEach(x => { if (d.also.includes(x.name) && !x.visit) x.visit = { at: d.visit.at, sid: x.id }; }));
        toast('✓ Visit saved: ' + k.name + (d.also.length ? ' (+ ' + d.also.join(', ') + ')' : '')); REP = null; done(k); }
      catch (e) { alert(e.message); $('save').disabled = false; $('save').textContent = '✓ Save visit'; }
    };
  }

  // ---------- after saving: thank-you message + result card to the parent on WhatsApp (owner, 21 Nov 2026) ----------
  const PASS = 33, BANDS = [[33, 'D'], [41, 'C2'], [51, 'C1'], [61, 'B2'], [71, 'B1'], [81, 'A2'], [91, 'A1']];
  const grade = p => p >= 91 ? 'A1' : p >= 81 ? 'A2' : p >= 71 ? 'B1' : p >= 61 ? 'B2' : p >= 51 ? 'C1' : p >= 41 ? 'C2' : p >= 33 ? 'D' : p >= 21 ? 'E1' : 'E2';
  const GCOL = { A1: '#1E7A43', A2: '#2E9E5B', B1: '#2D6CDF', B2: '#4F8BEA', C1: '#C98A00', C2: '#E0A100', D: '#E07B00', E1: '#C0392B', E2: '#A31515' };
  const SCHOOL = 'SN Mishra Singapore International School', SESL = (() => { const d = new Date(DATE), y = d.getMonth() >= 3 ? d.getFullYear() : d.getFullYear() - 1; return y + '-' + (y + 1); })();
  const dmy = d => (d || '').split('-').reverse().join('-');
  // same figures as the marks slip: grace (28–32 → 33) is added, not shown; rank only for the upper half of the class
  function card(k) {
    const r = k.res || { subs: [] }, rows = r.subs.map(x => { const g = !x.ab && x.t < PASS && x.t >= PASS - 5 ? Math.ceil(PASS - x.t) : 0; return { s: x.s, t: Math.round((x.t + g) * 100) / 100, ab: x.ab }; });
    const pct = rows.length ? Math.round(rows.reduce((a, x) => a + x.t, 0) * 10 / rows.length) / 10 : null, top = r.rank && r.of && r.rank * 2 <= r.of;
    const tgt = rows.filter(x => !x.ab).map(x => { const b = BANDS.find(([m]) => m > x.t); return b ? x.s + ' ' + b[0] + '+' : null; }).filter(Boolean);
    return { rows, pct, top, tgt, att: k.att && k.att.days ? Math.round(k.att.present * 100 / k.att.days) : null };
  }
  function message(k) {
    const v = k.visit || {}, c = card(k), first = k.name.split(/\s+/)[0], parent = v.parent || k.father;
    const L = [`Dear ${parent ? (/^(mr|mrs|ms|shri|smt)\.?\s/i.test(parent) ? parent : 'Mr. ' + parent) : 'Parent'},`, '', `Thank you for attending the Parent-Teacher Meeting at ${SCHOOL}.`, '',
      `*Half-Yearly Examination ${SESL}*`, `*${k.name}* — Class ${CLS}${k.section ? '-' + k.section : ''}`];
    c.rows.forEach(x => L.push(`• ${x.s}: ${x.ab ? 'Absent' : x.t + '/100 (' + grade(x.t) + ')'}`));
    if (c.pct != null) L.push(`*Overall: ${c.pct}% (${grade(c.pct)})*${c.top ? ` · Rank ${k.res.rank} of ${k.res.of}` : ''}`);
    if (c.att != null) L.push(`Attendance: ${c.att}%`);
    if (c.tgt.length) L.push('', `Targets for the Final Examination: ${c.tgt.join(', ')}`);
    if (v.note) L.push('', `Advice discussed: ${v.note}`);
    if (v.paid) L.push('', `Fee received today: ₹${Math.round(v.paid).toLocaleString('en-IN')}. Thank you.`);
    else if (v.promise) L.push('', `Fee due: ${rs(k.fee && k.fee.due)}. Committed payment date: ${dmy(v.promise)}${v.pamt ? ' (₹' + (+v.pamt).toLocaleString('en-IN') + ')' : ''}.`);
    else if (k.fee && k.fee.due > 0) L.push('', `Fee due till this month: ${rs(k.fee.due)}. Kindly clear it at the earliest.`);
    L.push('', `We look forward to ${first}'s continued progress.`, '', 'Regards,', 'Principal', SCHOOL);
    return L.join('\n');
  }
  function loadImg(src) { return new Promise(ok => { const i = new Image(); i.onload = () => ok(i); i.onerror = () => ok(null); i.src = src; }); }
  async function cardCanvas(k) {
    try { await Promise.all(['400', '600', '700'].map(w => document.fonts.load(w + ' 30px Poppins'))); } catch (e) {}
    const c = card(k), W = 1080, n = c.rows.length, H = 890 + n * 74 + (c.tgt.length ? 150 : 0), cv = document.createElement('canvas'); cv.width = W; cv.height = H; const x = cv.getContext('2d');
    const T = (t, X, Y, f, col, al) => { x.font = f; x.fillStyle = col; x.textAlign = al || 'left'; x.fillText(String(t), X, Y); };
    const NAVY = '#14294A', GOLD = '#D9B45A', F = 'Poppins, Arial, sans-serif';
    x.fillStyle = '#F6F8FC'; x.fillRect(0, 0, W, H);
    const g = x.createLinearGradient(0, 0, W, 220); g.addColorStop(0, '#0E1F3D'); g.addColorStop(.7, '#1F4A8A'); g.addColorStop(1, '#14294A'); x.fillStyle = g; x.fillRect(0, 0, W, 220); x.fillStyle = GOLD; x.fillRect(0, 220, W, 8);
    const logo = await loadImg('img/snmsis-logo.png'); if (logo) { x.save(); x.beginPath(); x.arc(110, 110, 70, 0, 7); x.fillStyle = '#fff'; x.fill(); x.lineWidth = 5; x.strokeStyle = GOLD; x.stroke(); x.clip(); x.drawImage(logo, 45, 45, 130, 130); x.restore(); }
    T('SN MISHRA SINGAPORE', 205, 92, `700 40px Georgia, serif`, '#fff'); T('INTERNATIONAL SCHOOL', 205, 140, `700 40px Georgia, serif`, '#fff'); T('Jagannathpur, Bhadohi (U.P.)', 205, 182, `400 24px ${F}`, '#DDE6F0');
    T(`HALF-YEARLY EXAMINATION ${SESL} · RESULT CARD`, W / 2, 290, `700 28px ${F}`, '#8C6A1E', 'center');
    // student
    x.fillStyle = '#fff'; x.strokeStyle = '#E3D9BE'; x.lineWidth = 2; x.beginPath(); x.roundRect(40, 320, W - 80, 170, 22); x.fill(); x.stroke();
    T(k.name, 72, 382, `700 44px Georgia, serif`, NAVY); T(`Class ${CLS}${k.section ? '-' + k.section : ''}  ·  Adm. No. ${k.adm || '—'}`, 72, 428, `400 26px ${F}`, '#4a5565'); if (k.father) T('Father: ' + k.father, 72, 466, `400 26px ${F}`, '#4a5565');
    if (c.pct != null) { const col = GCOL[grade(c.pct)], cx = W - 150, cy = 405; x.lineWidth = 18; x.strokeStyle = '#E6EAF0'; x.beginPath(); x.arc(cx, cy, 62, 0, 7); x.stroke(); x.strokeStyle = col; x.beginPath(); x.arc(cx, cy, 62, -Math.PI / 2, -Math.PI / 2 + Math.PI * 2 * c.pct / 100); x.stroke();
      T(c.pct + '%', cx, cy + 8, `700 30px ${F}`, NAVY, 'center'); T(grade(c.pct), cx, cy + 40, `700 22px ${F}`, col, 'center'); }
    // subjects
    let y = 540; x.fillStyle = NAVY; x.beginPath(); x.roundRect(40, y, W - 80, 60, [16, 16, 0, 0]); x.fill();
    T('SUBJECT', 70, y + 39, `600 22px ${F}`, '#fff'); T('MARKS', 560, y + 39, `600 22px ${F}`, '#fff', 'center'); T('GRADE', 690, y + 39, `600 22px ${F}`, '#fff', 'center'); T('PERFORMANCE', 890, y + 39, `600 22px ${F}`, '#fff', 'center'); y += 60;
    c.rows.forEach((r, i) => { x.fillStyle = i % 2 ? '#fff' : '#F2F5FA'; x.fillRect(40, y, W - 80, 74); const gd = r.ab ? '' : grade(r.t), col = GCOL[gd] || '#8a94a3';
      T(r.s, 70, y + 47, `600 28px ${F}`, NAVY); T(r.ab ? 'AB' : r.t, 560, y + 47, `700 30px ${F}`, r.ab || r.t < PASS ? '#B3261E' : NAVY, 'center');
      if (gd) { x.fillStyle = col; x.beginPath(); x.roundRect(650, y + 18, 80, 38, 19); x.fill(); T(gd, 690, y + 46, `700 22px ${F}`, '#fff', 'center'); }
      x.fillStyle = '#E6EAF0'; x.beginPath(); x.roundRect(780, y + 30, 230, 16, 8); x.fill(); x.fillStyle = col; x.beginPath(); x.roundRect(780, y + 30, Math.max(4, 230 * Math.min(100, r.ab ? 0 : r.t) / 100), 16, 8); x.fill(); y += 74; });
    x.strokeStyle = '#D6DEEA'; x.lineWidth = 2; x.strokeRect(40, 540, W - 80, y - 540);
    // summary chips
    y += 40; const chips = [c.pct != null ? ['Overall', c.pct + '% · ' + grade(c.pct)] : null, c.top ? ['Rank in class', k.res.rank + ' of ' + k.res.of] : null, c.att != null ? ['Attendance', c.att + '%'] : null].filter(Boolean);
    const cw = (W - 80 - (chips.length - 1) * 20) / Math.max(1, chips.length); chips.forEach(([a, b], i) => { const X = 40 + i * (cw + 20); x.fillStyle = '#fff'; x.strokeStyle = GOLD; x.lineWidth = 2; x.beginPath(); x.roundRect(X, y, cw, 100, 18); x.fill(); x.stroke();
      T(a, X + cw / 2, y + 38, `400 22px ${F}`, '#5a6b80', 'center'); T(b, X + cw / 2, y + 78, `700 32px ${F}`, NAVY, 'center'); });
    y += 130;
    if (c.tgt.length) { x.fillStyle = '#EEF4FD'; x.strokeStyle = '#C8DCF5'; x.beginPath(); x.roundRect(40, y, W - 80, 120, 18); x.fill(); x.stroke(); T('TARGETS FOR THE FINAL EXAMINATION', 70, y + 42, `700 22px ${F}`, '#1F4A8A');
      let line = '', yy = y + 84; c.tgt.forEach(t2 => { const nx = line ? line + '   ·   ' + t2 : t2; x.font = `600 24px ${F}`; if (x.measureText(nx).width > W - 150) { T(line, 70, yy, `600 24px ${F}`, NAVY); line = t2; yy += 32; } else line = nx; }); T(line, 70, yy, `600 24px ${F}`, NAVY); y += 150; }
    T('The detailed progress slip was shared at the Parent-Teacher Meeting.', W / 2, H - 70, `400 22px ${F}`, '#5a6b80', 'center'); T('Principal · ' + SCHOOL, W / 2, H - 34, `600 22px ${F}`, NAVY, 'center');
    return cv;
  }
  async function shareCard(k) {
    const cv = await cardCanvas(k), name = 'Result-' + k.name.replace(/\W+/g, '-') + '.png', b64 = cv.toDataURL('image/png').split(',')[1];
    if (window.SNMSISFiles && typeof SNMSISFiles.shareImage === 'function') return SNMSISFiles.shareImage(name, b64);
    if (window.SNMSISFiles && typeof SNMSISFiles.shareFile === 'function') return SNMSISFiles.shareFile(name, b64, 'image/png');
    try { const blob = await new Promise(r => cv.toBlob(r, 'image/png')), f = new File([blob], name, { type: 'image/png' }); if (navigator.canShare && navigator.canShare({ files: [f] })) return await navigator.share({ files: [f], text: message(k) }); } catch (e) { if (e && e.name === 'AbortError') return; }
    const a = document.createElement('a'); a.href = cv.toDataURL('image/png'); a.download = name; document.body.appendChild(a); a.click(); a.remove(); toast('Result card downloaded — attach it in WhatsApp.');
  }
  function done(k) {
    const v = k.visit || {}, ph = String(v.phone || k.phone || '').replace(/\D/g, '').slice(-10), txt = message(k);
    $('lst').innerHTML = `<div class="panel"><h2 style="margin:0 0 4px;color:#1E7A43">✓ Visit saved</h2><p style="margin:0 0 10px;color:#4a5565"><b>${esc(k.name)}</b> · ${esc(v.parent || k.father || '')}${ph ? ' · ' + ph : ''}</p>
      <label class="l">Message to the parent (you can edit it)</label><textarea class="i" id="wmsg" style="min-height:260px;font-size:13px">${esc(txt)}</textarea>
      <div class="tog" style="margin-top:10px">${ph ? `<button class="btn g" id="wsend" style="flex:1">💬 Send message on WhatsApp</button>` : '<p class="msg" style="padding:6px">No mobile number — enter it in the visit to send on WhatsApp.</p>'}<button class="btn" id="wcard" style="flex:1">🖼️ Share result card</button></div>
      <p style="font-size:12px;color:#5a6b80;margin:8px 0 0">Tip: send the message first (it opens the parent's chat), then share the result card into the same chat.</p>
      ${v.sent ? `<p style="font-size:12px;color:#1E7A43;margin:6px 0 0">Sent at ${esc(v.sent.at)}.</p>` : ''}
      <div class="bar"><button class="btn l" id="wnext">Next student →</button></div></div>`;
    const mark = how => api('ptm_api.php?a=sent', { date: DATE, sid: k.id, how }).then(d => { k.visit = d.visit; REP = null; }).catch(() => {});
    if ($('wsend')) $('wsend').onclick = () => { window.open(`https://wa.me/91${ph}?text=${encodeURIComponent($('wmsg').value)}`, '_blank'); mark('msg'); };
    $('wcard').onclick = async () => { $('wcard').disabled = true; try { await shareCard(k); mark('card'); } finally { $('wcard').disabled = false; } };
    $('wnext').onclick = () => { SEL = null; desk(); };
  }

  // ---------- day report ----------
  async function loadRep() { if (!REP) REP = await api('ptm_api.php?a=report&date=' + DATE); return REP; }
  async function report() {
    app('<div class="msg">Preparing the report…</div>');
    let R; try { R = await loadRep(); } catch (e) { return app(`<div class="msg">${esc(e.message)}</div>`); }
    const V = R.visits, n = Object.values(R.classes).reduce((a, c) => a + (c.n || 0), 0), paid = V.reduce((a, v) => a + (v.paid || 0), 0), prom = V.filter(v => v.promise), cmp = V.filter(v => v.cmp);
    const mood = [4, 3, 2, 1].map(m => V.filter(v => v.mood === m).length), rated = mood.reduce((a, b) => a + b, 0);
    const wa = (p, t) => p ? `<a class="ic w" target="_blank" rel="noopener" href="https://wa.me/91${p}?text=${encodeURIComponent(t)}">WhatsApp</a><a class="ic c" href="tel:+91${p}">Call</a>` : '';
    const missMsg = nm => `Dear Parent, greetings from SN Mishra Singapore International School. The Parent-Teacher Meeting for the Half-Yearly results of ${nm} was held today. We missed you — kindly visit the school at your convenience to meet the class teacher. Thank you.`;
    app(`<div class="grid"><div><b>${V.length}</b><small>Parents attended<br>(of ${n} students)</small></div><div><b>${rs(paid)}</b><small>Fee received today</small></div><div><b>${prom.length}</b><small>Payment commitments</small></div><div><b>${cmp.length}</b><small>Complaints</small></div></div><p style="margin:8px 0 0;color:#5a6b80;font-size:12.5px">Result shared on WhatsApp with <b>${V.filter(v => v.sent).length}</b> parents.</p>
      <h3>Parent satisfaction</h3><div class="grid">${MOODS.map(([m, e, t], i) => `<div><b>${e} ${mood[i]}</b><small>${t}${rated ? ' · ' + Math.round(mood[i] * 100 / rated) + '%' : ''}</small></div>`).join('')}</div>
      <h3>Class-wise attendance</h3><table class="t"><tr><th>Class</th><th>Attended</th><th>Not attended</th></tr>${CLASSES.filter(c => R.classes[c]).map(c => { const x = R.classes[c]; return `<tr><td>${c}</td><td><b>${x.came || 0}</b> / ${x.n}</td><td>${(x.not || []).length}</td></tr>`; }).join('')}</table>
      ${prom.length ? `<h3>Fee payment commitments</h3><table class="t"><tr><th>Student</th><th>Date</th><th>Amount</th></tr>${prom.sort((a, b) => a.promise.localeCompare(b.promise)).map(v => `<tr><td>${esc(v.name)} <small>(${esc(v.cls)})</small></td><td>${esc(v.promise.split('-').reverse().join('-'))}</td><td>${v.pamt ? rs(v.pamt) : '—'}</td></tr>`).join('')}</table>` : ''}
      ${V.filter(v => v.note).length ? `<h3>Feedback and advice</h3>${V.filter(v => v.note).map(v => `<div class="cm done"><small>${esc(v.at)} · ${esc(v.name)} (${esc(v.cls)}) · ${esc(v.parent || '')} ${(MOODS.find(m => m[0] === v.mood) || [])[1] || ''}</small><p>${esc(v.note)}</p></div>`).join('')}` : ''}
      <h3>Parents who did not attend (for follow-up)</h3>${CLASSES.filter(c => R.classes[c] && (R.classes[c].not || []).length).map(c => `<details><summary style="padding:6px 0;font-weight:600;color:#14294A">Class ${c} — ${(R.classes[c].not || []).length}</summary>${R.classes[c].not.map(s => `<div class="kid" style="cursor:default"><div class="n"><b>${esc(s.name)}</b><small>${esc(s.father || '')}</small></div>${wa(s.phone, missMsg(s.name))}</div>`).join('')}</details>`).join('') || '<p class="msg">All parents attended 🎉</p>'}
      <p style="margin-top:14px"><button class="btn l" id="rprint">🖨 Print report</button></p>`);
    $('rprint').onclick = () => window.print();
  }
  // ---------- complaints ----------
  async function complaints() {
    app('<div class="msg">Loading complaints…</div>');
    let R; try { R = await loadRep(); } catch (e) { return app(`<div class="msg">${esc(e.message)}</div>`); }
    const C = R.complaints, open = C.filter(v => v.cmp.status !== 'Resolved');
    app(`<p style="margin:0 0 8px;color:#5a6b80"><b>${open.length}</b> open · ${C.length - open.length} resolved today. Open complaints from earlier days are also listed.</p>` + (C.map(v => `<div class="cm ${v.cmp.status === 'Resolved' ? 'done' : ''}"><small>${esc((v.date || '').split('-').reverse().join('-'))} ${esc(v.at || '')} · <b>${esc(v.name)}</b> (${esc(v.cls)}) · ${esc(v.parent || '')} ${v.phone ? `<a class="ic c" href="tel:+91${esc(v.phone)}">Call</a>` : ''}</small>
      <p><span class="pill p-c">${esc(v.cmp.cat || 'Other')}</span> ${esc(v.cmp.text)}</p>${v.cmp.due ? `<small>Target date: ${esc(v.cmp.due.split('-').reverse().join('-'))}</small>` : ''}
      ${v.cmp.status === 'Resolved' ? `<p>✅ <b>Resolved:</b> ${esc(v.cmp.action || '')} <small>— ${esc(v.cmp.doneBy || '')}</small></p>` : R.canResolve ? `<div class="row2" style="margin-top:6px"><input class="i" placeholder="Action taken" data-act="${esc(v.date + '|' + v.sid)}"><button class="btn g s" data-res="${esc(v.date + '|' + v.sid)}">✓ Mark resolved</button></div>` : ''}</div>`).join('') || '<div class="msg">No complaints 🎉</div>'));
    document.querySelectorAll('[data-res]').forEach(b => { b.onclick = async () => { const key = b.dataset.res, act = document.querySelector(`[data-act="${CSS.escape(key)}"]`).value;
      if (!act.trim()) return alert('Please write the action taken.'); b.disabled = true;
      try { await api('ptm_api.php?a=resolve', { key, status: 'Resolved', action: act }); REP = null; toast('✓ Complaint resolved'); complaints(); } catch (e) { alert(e.message); b.disabled = false; } }; });
  }
  function go() { document.querySelectorAll('[data-t]').forEach(b => b.classList.toggle('on', b.dataset.t === TAB)); if (TAB === 'desk') desk(); else if (TAB === 'report') report(); else complaints(); }
  document.querySelectorAll('[data-t]').forEach(b => { b.onclick = () => { TAB = b.dataset.t; if (TAB !== 'desk') REP = null; go(); }; });
  { const a = document.querySelector('.top a'); if (/teacher\.php|chairman\.php/.test(document.referrer)) { a.textContent = '← App'; a.href = document.referrer; } }
  $('dt').value = DATE; $('dt').max = today(); $('dt').onchange = e => { DATE = e.target.value || today(); DATA = {}; REP = null; SEL = null; go(); };
  if (/report/.test(location.hash)) TAB = 'report'; if (/cmp/.test(location.hash)) TAB = 'cmp';
  go();
})();
