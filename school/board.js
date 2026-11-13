/* Class 10 Board Prep (board.php) — owner, 13 Nov 2026: "class 10th ke liye students app — online test, board ki
   taiyari". Student: subjects → chapters → Practice (answer + explanation at once) / Chapter test (10 Q, 12 min) /
   Mock test (30 Q, 40 min, all chapters) → result with chapter-wise marks and full review; weak chapters; history.
   Teacher / Principal / Chairman: class results, weak chapters of the class, add questions. board_api.php. */
(function () {
  const $ = id => document.getElementById(id), store = { get: k => { try { return localStorage.getItem(k); } catch (e) { return null; } }, set: (k, v) => { try { v == null ? localStorage.removeItem(k) : localStorage.setItem(k, v); } catch (e) {} } };
  const esc = s => String(s == null ? '' : s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
  const toast = t => { const e = $('toast'); e.textContent = t; e.style.display = 'block'; clearTimeout(toast.t); toast.t = setTimeout(() => e.style.display = 'none', 3500); };
  const TK = 'brd_token', tok = () => store.get(TK) || store.get('sch_token') || '';   // students log in here; staff can use their ERP / Teacher App login on the same phone
  const pc = (r, t) => t ? Math.round(r / t * 100) : 0, L = 'ABCD';
  const NCERT = 'https://ncert.nic.in/textbook.php', CBSE = 'https://cbseacademic.nic.in/';
  let H = null, V = { v: 'home' }, RUN = null, MUST = false, CL = null, STAFF_TAB = 'class';
  if ('serviceWorker' in navigator) navigator.serviceWorker.register('board-sw.js').catch(() => {});
  async function api(a, body, q) {
    const r = await fetch('board_api.php?a=' + a + (q || ''), { method: body ? 'POST' : 'GET', cache: 'no-store', headers: { 'Content-Type': 'application/json', 'X-School-Token': tok() }, ...(body ? { body: JSON.stringify(body) } : {}) });
    let j = null; try { j = await r.json(); } catch (e) {}
    if (r.status === 401) { store.set(TK, null); H = null; draw(); throw Error('Please log in again.'); }
    if (r.status === 428) { MUST = true; draw(); throw Error('Choose your own PIN first.'); }
    if (!r.ok || !j || !j.ok) throw Error((j && j.error) || 'Server problem. Please try again.');
    return j;
  }
  const top = (title, sub, right) => `<div class="top"><div class="r"><div><b>${title}</b><small>${sub || ''}</small></div><div class="acts">${right || ''}</div></div></div>`;
  // ---------------- login ----------------
  function loginView(msg) {
    $('app').innerHTML = `<div class="lg"><h2>Board Prep · Class 10</h2><p class="mut">SN Mishra Singapore International School</p>${msg ? `<p style="color:#B91C1C">${esc(msg)}</p>` : ''}
      ${MUST ? `<div class="f"><label>Choose your own PIN (4–8 digits, not 1234)</label><input id="nP" type="password" inputmode="numeric" maxlength="8"></div><button class="btn go full f" onclick="bdPin()">Save my PIN</button>`
      : `<div class="f"><label>Admission number</label><input id="lA" autocomplete="username"></div><div class="f"><label>PIN</label><input id="lP" type="password" inputmode="numeric" maxlength="8" onkeydown="if(event.key==='Enter')bdLogin()"></div>
        <button class="btn go full f" onclick="bdLogin()">Start preparing</button><p class="mut f">First time: PIN 1234, then choose your own. It is the same login as the school's student app.</p>
        <p class="mut f">Teachers: open the ERP or Teacher App on this phone first, then open this page.</p>${/; wv\)/.test(navigator.userAgent) ? '' : '<p class="mut f"><a href="https://github.com/ankit854281/maakit/releases/download/board-android-v1/snmsis-board.apk">⬇ Download the Board Prep app (Android)</a></p>'}`}</div>`;
  }
  window.bdLogin = async () => {
    try { const res = await fetch('api.php?a=login', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ role: 'student', adm: $('lA').value.trim(), pin: $('lP').value.trim() }) }); const j = await res.json();
      if (!j.ok) return toast(j.error || 'Could not log in.'); store.set(TK, j.token); window._old = $('lP').value.trim(); MUST = !!j.mustChange; H = null; draw(); } catch (e) { toast('Check the internet and try again.'); } };
  window.bdPin = async () => { const n = $('nP').value.trim(); if (!/^\d{4,8}$/.test(n) || n === '1234') return toast('PIN must be 4–8 digits and not 1234.');
    try { const res = await fetch('api.php?a=setpin', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-School-Token': tok() }, body: JSON.stringify({ old: window._old || '1234', pin: n }) }); const j = await res.json(); if (!j.ok) return toast(j.error || 'Try again.'); MUST = false; toast('PIN saved.'); draw(); } catch (e) { toast(e.message); } };
  window.bdLogout = async () => { if (store.get(TK)) { try { await fetch('api.php?a=logout', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-School-Token': store.get(TK) }, body: '{}' }); } catch (e) {} store.set(TK, null); } H = null; V = { v: 'home' }; loginView(); };
  // ---------------- main ----------------
  async function draw() {
    if (MUST) return loginView();
    if (!tok()) return loginView();
    if (!H) { $('app').innerHTML = top('Board Prep', 'Opening…') + '<div class="pg"><div class="card mut">Opening…</div></div>'; try { H = await api('home'); } catch (e) { if (store.get(TK) || /Class 10/.test(e.message)) return loginView(e.message); return loginView(); } }
    if (H.role !== 'student') return staffView();
    if (V.v === 'quiz') return quizView(); if (V.v === 'result') return resultView(); if (V.v === 'subj') return subjView(); if (V.v === 'hist') return histView();
    homeView();
  }
  const subOf = c => H.subjects.find(s => s.code === c);
  const accOf = (s, n) => H.acc[s + '|' + n] || { r: 0, t: 0 };
  const subAcc = s => subOf(s).chapters.reduce((a, c) => { const x = accOf(s, c.n); return { r: a.r + x.r, t: a.t + x.t }; }, { r: 0, t: 0 });
  function homeView() {
    const d = H.done, mode = (m, s) => `bdStart('mock','${s}')`;
    $('app').innerHTML = top(`Hi ${esc(H.me.name.split(' ')[0])} 👋`, `Board Prep · Class 10 · ${H.total} questions`, '<button onclick="bdLogout()">Log out</button>') + `<div class="pg">
      <div class="kp"><div class="k"><b>${d.tests}</b><span>tests taken</span></div><div class="k"><b>${d.questions}</b><span>questions done</span></div><div class="k"><b>${pc(d.right, d.questions)}%</b><span>correct</span></div></div>
      <div class="card"><div class="row"><h3 style="margin:0">Full mock test</h3><span class="mut">30 questions · 40 minutes</span></div><p class="mut">Questions from every chapter, like a board paper. Answers and marks come at the end.</p>
        <div class="acts"><button class="btn go" onclick="${mode('mock', 'all')}">All subjects</button>${H.subjects.filter(s => s.chapters.some(c => c.count)).map(s => `<button class="btn l s" onclick="bdStart('mock','${s.code}')">${esc(s.name)}</button>`).join('')}</div></div>
      ${H.weak.length ? `<div class="card"><h3>Work on these chapters</h3>${H.weak.slice(0, 5).map(w => `<div class="ch row"><div><b>${esc(w.name)}</b><div class="mut">${esc(subOf(w.subj).name)} · ${w.pc}% correct</div></div><button class="btn s" onclick="bdStart('practice','${w.subj}',${w.ch})">Practise</button></div>`).join('')}</div>` : ''}
      <div class="subs">${H.subjects.map(s => { const a = subAcc(s.code), n = s.chapters.reduce((x, c) => x + c.count, 0); return `<button class="sub s-${s.code}" onclick="bdSubj('${s.code}')"><b>${esc(s.name)}</b><small>${s.chapters.length} chapter${s.chapters.length > 1 ? 's' : ''} · ${n ? n + ' questions' : 'teachers will add questions'}</small><div><small>${a.t ? pc(a.r, a.t) + '% correct' : 'Not started'}</small><div class="bar"><i style="width:${a.t ? pc(a.r, a.t) : 0}%"></i></div></div></button>`; }).join('')}</div>
      <div class="card" style="margin-top:12px"><div class="row"><h3 style="margin:0">Recent tests</h3><button class="btn l s" onclick="bdGo('hist')">All</button></div>${H.recent.length ? H.recent.map(r => `<div class="ch row"><div><b>${esc(r.mode === 'mock' ? 'Mock · ' + (r.subj === 'all' ? 'All subjects' : subOf(r.subj).name) : subOf(r.subj).name + ' · Ch ' + r.ch)}</b><div class="mut">${new Date(r.at).toLocaleDateString('en-IN', { day: 'numeric', month: 'short' })} · ${Math.round(r.secs / 60)} min</div></div><b style="color:#3B1F7A">${r.score}/${r.total}</b></div>`).join('') : '<p class="mut">No test yet — start with a chapter test.</p>'}</div>
      <div class="card"><h3>Official books & papers</h3><p class="mut">Read the textbook first, then test yourself here. NCERT books and CBSE sample papers open on their own official websites.</p>
        <div class="acts"><a class="btn l s" href="${NCERT}" target="_blank" rel="noopener">NCERT textbooks (Class 10)</a><a class="btn l s" href="${CBSE}" target="_blank" rel="noopener">CBSE sample papers & marking schemes</a></div></div>
      <p class="mut">Questions are written by the school for the CBSE 2026-27 Class 10 syllabus. Practice tests check understanding; board papers also have long written answers — write them in your notebook and show your teacher.</p></div>`;
  }
  window.bdGo = v => { V = { v }; draw(); };
  window.bdSubj = c => { V = { v: 'subj', s: c }; draw(); };
  function subjView() {
    const s = subOf(V.s);
    $('app').innerHTML = top(esc(s.name), `${s.chapters.length} chapters`, '<button onclick="bdGo(\'home\')">← Home</button>') + `<div class="pg"><div class="card">
      ${s.chapters.map(c => { const a = accOf(s.code, c.n); return `<div class="ch"><div class="row"><div><b>${c.n}. ${esc(c.name)}</b><div class="mut">${c.count} questions · ${a.t ? pc(a.r, a.t) + '% correct (' + a.t + ' answered)' : 'not started'}</div></div></div>
        <div class="bar d"><i style="width:${a.t ? pc(a.r, a.t) : 0}%"></i></div>
        <div class="acts" style="margin-top:8px">${c.count ? `<button class="btn l s" onclick="bdStart('practice','${s.code}',${c.n})">Practice</button><button class="btn s" onclick="bdStart('chapter','${s.code}',${c.n})">Chapter test · 12 min</button>` : '<span class="mut">Teachers will add questions here.</span>'}</div></div>`; }).join('')}
      </div><button class="btn go full" onclick="bdStart('mock','${s.code}')">${esc(s.name)} mock test · 30 questions</button></div>`;
  }
  // ---------------- quiz ----------------
  window.bdStart = async (mode, subj, ch) => {
    $('app').innerHTML = top('Getting questions…', '') + '<div class="pg"><div class="card mut">Please wait…</div></div>';
    try { const r = await api('start', { mode, subj, ch: ch || 0 }); RUN = { ...r, i: 0, ans: {}, fb: {}, end: r.min ? Date.now() + r.min * 60000 : 0 }; V = { v: 'quiz' }; draw(); tick(); }
    catch (e) { toast(e.message); V = V.v === 'quiz' ? { v: 'home' } : V; draw(); }
  };
  function tick() { clearInterval(tick.t); if (!RUN || !RUN.end) return; tick.t = setInterval(() => { const e = $('tm'); if (!RUN || V.v !== 'quiz') return clearInterval(tick.t); const left = RUN.end - Date.now();
    if (left <= 0) { clearInterval(tick.t); toast('Time is over — submitting.'); return bdSubmit(true); } if (e) { e.textContent = fmtT(left); e.classList.toggle('low', left < 120000); } }, 1000); }
  const fmtT = ms => { const s = Math.max(0, Math.round(ms / 1000)); return Math.floor(s / 60) + ':' + String(s % 60).padStart(2, '0'); };
  function quizView() {
    const R = RUN, q = R.questions[R.i], fb = R.fb[q.id], mine = R.ans[q.id], prac = R.mode === 'practice', n = R.questions.length, done = Object.keys(R.ans).length;
    $('app').innerHTML = top(esc(R.title), `Question ${R.i + 1} of ${n} · ${done} answered`, `${R.end ? `<span class="timer" id="tm">${fmtT(R.end - Date.now())}</span>` : ''}<button onclick="bdQuit()">✕</button>`) + `<div class="pg"><div class="card">
      ${q.t === 'ar' ? '<span class="tag">Assertion – Reason</span>' : ''}<div class="q">${esc(q.q)}</div>
      ${q.o.map((o, k) => `<button class="opt ${fb ? (k === fb.a ? 'ok' : k === mine ? 'bad' : '') : mine === k ? 'sel' : ''}" ${fb ? 'disabled' : ''} onclick="bdPick(${k})"><i>${L[k]}</i>${esc(o)}</button>`).join('')}
      ${fb ? `<div class="exp"><b>${fb.right ? '✓ Correct!' : '✗ Right answer: ' + L[fb.a]}</b><br>${esc(fb.e)}</div>` : ''}
      <div class="row" style="margin-top:12px"><button class="btn l" ${R.i ? '' : 'disabled'} onclick="bdMove(-1)">← Back</button>${R.i < n - 1 ? `<button class="btn" onclick="bdMove(1)">Next →</button>` : `<button class="btn go" onclick="bdSubmit()">${prac ? 'Finish' : 'Submit test'}</button>`}</div>
      <div class="pal">${R.questions.map((x, k) => `<button class="${R.ans[x.id] != null ? 'a' : ''} ${k === R.i ? 'cur' : ''}" onclick="bdJump(${k})">${k + 1}</button>`).join('')}</div></div>
      ${!prac ? `<button class="btn go full" onclick="bdSubmit()">Submit test (${done}/${n} answered)</button>` : ''}</div>`;
  }
  window.bdPick = async k => { const R = RUN, q = R.questions[R.i]; if (R.fb[q.id]) return; R.ans[q.id] = k;
    if (R.mode === 'practice') { try { R.fb[q.id] = await api('check', { run: R.run, qid: q.id, choice: k }); } catch (e) { toast(e.message); } }
    quizView(); };
  window.bdMove = d => { RUN.i = Math.min(RUN.questions.length - 1, Math.max(0, RUN.i + d)); quizView(); };
  window.bdJump = k => { RUN.i = k; quizView(); };
  window.bdQuit = () => { if (!confirm('Leave this test? Your answers will not be saved.')) return; clearInterval(tick.t); RUN = null; V = { v: 'home' }; draw(); };
  window.bdSubmit = async auto => { const R = RUN; if (!R) return; const left = R.questions.length - Object.keys(R.ans).length;
    if (!auto && R.mode !== 'practice' && left && !confirm(left + ' question(s) not answered. Submit anyway?')) return;
    clearInterval(tick.t);
    try { const r = await api('submit', { run: R.run, answers: R.ans }); V = { v: 'result', r, title: R.title, mode: R.mode }; RUN = null; H = null; const h = await api('home'); H = h; draw(); } catch (e) { toast(e.message); } };
  function resultView() {
    const r = V.r, p = pc(r.score, r.total), msg = p >= 90 ? 'Excellent! Board-ready 🌟' : p >= 75 ? 'Very good — keep going 👍' : p >= 50 ? 'Good start — revise the wrong ones' : 'Read the chapter again, then retry 💪';
    $('app').innerHTML = top('Result', esc(V.title), '<button onclick="bdGo(\'home\')">Home</button>') + `<div class="pg"><div class="card" style="text-align:center"><div class="big">${r.score}/${r.total}</div><b>${p}% · ${msg}</b><p class="mut">Time ${Math.floor(r.secs / 60)} min ${r.secs % 60} s${r.late ? ' · after the time limit' : ''}</p></div>
      ${r.chapters.length > 1 ? `<div class="card"><h3>Chapter-wise</h3>${r.chapters.sort((a, b) => pc(a.r, a.t) - pc(b.r, b.t)).map(c => `<div class="ch row"><div><b>${esc(c.name)}</b><div class="mut">${esc(subOf(c.subj).name)}</div></div><b style="color:${pc(c.r, c.t) < 60 ? '#B91C1C' : '#047857'}">${c.r}/${c.t}</b></div>`).join('')}</div>` : ''}
      <div class="card"><h3>Check your answers</h3>${r.review.map((q, k) => `<div class="rv"><b>${k + 1}.</b> <span style="white-space:pre-wrap">${esc(q.q)}</span>
        <div class="${q.mine === q.a ? 'y' : 'n'}">${q.mine < 0 ? '— Not answered' : (q.mine === q.a ? '✓ ' : '✗ Your answer: ') + L[q.mine] + '. ' + esc(q.o[q.mine])}</div>${q.mine !== q.a ? `<div class="y">✓ Right answer: ${L[q.a]}. ${esc(q.o[q.a])}</div>` : ''}${q.e ? `<div class="exp">${esc(q.e)}</div>` : ''}</div>`).join('')}</div>
      <button class="btn go full" onclick="bdGo('home')">Back to home</button></div>`;
  }
  async function histView() {
    let rows = []; try { rows = (await api('history')).rows; } catch (e) { toast(e.message); }
    $('app').innerHTML = top('My tests', rows.length + ' results', '<button onclick="bdGo(\'home\')">← Home</button>') + `<div class="pg"><div class="card">${rows.length ? rows.map(r => `<div class="ch row"><div><b>${esc(r.mode === 'mock' ? 'Mock · ' + (r.subj === 'all' ? 'All subjects' : subOf(r.subj).name) : (r.mode === 'practice' ? 'Practice · ' : 'Test · ') + subOf(r.subj).name + ' · Ch ' + r.ch)}</b><div class="mut">${new Date(r.at).toLocaleString('en-IN', { day: 'numeric', month: 'short', hour: 'numeric', minute: '2-digit' })}</div></div><b style="color:#3B1F7A">${r.score}/${r.total} · ${pc(r.score, r.total)}%</b></div>`).join('') : '<p class="mut">Nothing yet.</p>'}</div></div>`;
  }
  // ---------------- staff ----------------
  async function staffView() {
    if (V.v === 'quiz') return quizView(); if (V.v === 'result') return resultView();
    const seg = `<div class="seg">${[['class', 'Class 10 results'], ['add', 'Add a question'], ['mine', 'Questions added'], ['try', 'Try a test']].filter(x => x[0] !== 'add' || H.role !== 'chairman').map(([k, l]) => `<button class="${STAFF_TAB === k ? 'on' : ''}" onclick="bdTab('${k}')">${l}</button>`).join('')}</div>`;
    const frame = b => { $('app').innerHTML = top('Board Prep · Class 10', esc(H.me.name), store.get(TK) ? '<button onclick="bdLogout()">Log out</button>' : '') + `<div class="pg">${seg}${b}</div>`; };
    if (STAFF_TAB === 'add') return frame(`<div class="card"><h3>New question</h3><div class="f"><label>Subject and chapter</label><select id="aC">${H.subjects.map(s => s.chapters.map(c => `<option value="${s.code}|${c.n}">${esc(s.name)} · ${esc(c.name)}</option>`).join('')).join('')}</select></div>
      <div class="f"><label>Question</label><textarea id="aQ" rows="3" maxlength="600"></textarea></div>${[0, 1, 2, 3].map(k => `<div class="f"><label>Option ${L[k]}</label><input id="aO${k}" maxlength="200"></div>`).join('')}
      <div class="f"><label>Right option</label><select id="aA">${[0, 1, 2, 3].map(k => `<option value="${k}">${L[k]}</option>`).join('')}</select></div><div class="f"><label>Explanation (shown after answering)</label><textarea id="aE" rows="2" maxlength="400"></textarea></div>
      <button class="btn go full f" onclick="bdAdd()">Save question</button><p class="mut">Write your own questions — don't copy a guide book or paper word for word.</p></div>`);
    if (STAFF_TAB === 'mine') { let rows = []; try { rows = (await api('myq')).rows; } catch (e) { toast(e.message); }
      return frame(`<div class="card"><h3>Questions added (${rows.length})</h3>${rows.map(q => `<div class="rv"><span class="tag">${esc(q.subjName)} · ${esc(q.chName)}</span><div style="margin:4px 0;white-space:pre-wrap">${esc(q.q)}</div><div class="mut">${q.o.map((o, k) => (k === q.a ? '✓ ' : '') + L[k] + '. ' + esc(o)).join(' · ')}</div><div class="row"><span class="mut">by ${esc(q.by)}</span>${H.role !== 'chairman' ? `<button class="btn l s" onclick="bdDel('${q.id}')">Remove</button>` : ''}</div></div>`).join('') || '<p class="mut">None yet.</p>'}</div>`); }
    if (STAFF_TAB === 'try') return frame(`<div class="card"><h3>Try it like a student</h3><p class="mut">Your answers are not saved in any child's record.</p><div class="acts">${H.subjects.map(s => `<button class="btn l s" onclick="bdStart('mock','${s.code}')">${esc(s.name)} mock</button>`).join('')}<button class="btn go s" onclick="bdStart('mock','all')">All subjects mock</button></div></div>`);
    if (!CL) { frame('<div class="card mut">Opening…</div>'); try { CL = await api('class'); } catch (e) { return frame(`<div class="card">${esc(e.message)}</div>`); } }
    const used = CL.rows.filter(r => r.answered);
    frame(`<div class="kp"><div class="k"><b>${CL.rows.length}</b><span>Class 10 children</span></div><div class="k"><b>${used.length}</b><span>have started</span></div><div class="k"><b>${used.length ? Math.round(used.reduce((a, r) => a + r.pc, 0) / used.length) : 0}%</b><span>class average</span></div></div>
      ${CL.chapters.length ? `<div class="card"><h3>Weakest chapters (whole class)</h3>${CL.chapters.slice(0, 8).map(c => `<div class="ch row"><div><b>${esc(c.name)}</b><div class="mut">${esc(c.subj)} · ${c.t} answers</div></div><b style="color:${c.pc < 60 ? '#B91C1C' : '#047857'}">${c.pc}%</b></div>`).join('')}</div>` : ''}
      <div class="card"><h3>Children</h3><div style="overflow-x:auto"><table class="t"><tr><th>Child</th><th>Tests</th><th>Correct</th><th>Best mock</th><th>Weak chapters</th></tr>
      ${CL.rows.map(r => `<tr><td><b>${esc(r.name)}</b>${r.last ? `<div class="mut">${new Date(r.last).toLocaleDateString('en-IN', { day: 'numeric', month: 'short' })}</div>` : '<div class="mut">not started</div>'}</td><td>${r.tests}</td><td>${r.pc == null ? '—' : r.pc + '%'}</td><td>${r.mock == null ? '—' : r.mock + '%'}</td><td class="mut">${r.weak.map(esc).join(', ')}</td></tr>`).join('')}</table></div></div>`);
  }
  window.bdTab = k => { STAFF_TAB = k; if (k === 'class') CL = null; draw(); };
  window.bdAdd = async () => { const [subj, ch] = $('aC').value.split('|');
    try { await api('addq', { subj, ch: +ch, q: $('aQ').value, o: [0, 1, 2, 3].map(k => $('aO' + k).value), a: +$('aA').value, e: $('aE').value }); toast('Question saved — it is now in the tests.'); ['aQ', 'aE', 'aO0', 'aO1', 'aO2', 'aO3'].forEach(i => $(i).value = ''); H = null; await api('home').then(h => H = h); }
    catch (e) { toast(e.message); } };
  window.bdDel = async id => { if (!confirm('Remove this question?')) return; try { await api('delq', { id }); toast('Removed.'); H = null; H = await api('home'); draw(); } catch (e) { toast(e.message); } };
  draw();
})();
