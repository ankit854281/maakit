// ============================================================================
//  Parent Calls (owner, 19 Nov 2026) — calls.php. Same page in the web ERP (Parent Calls) and inside the
//  Teacher App (Connect → Calls, ?embed=1). Server: calls_api.php (kind tchCall).
//
//  Home: Call again today · Attendance calls (1 day / 2 days / 3+ days in a row / below 75% this month) ·
//        Calling lists (PTM, event, exam, holiday …) with progress. Principal makes a list for any classes,
//        a class teacher for his / her own class.
//  List: one class at a time — what to say (script), filters, 📞 call → result sheet opens by itself:
//        what happened · will the parent come (PTM / event) or why absent + coming on (attendance) ·
//        parent's feedback (Happy / Normal / Complaint) · what was discussed · call again on.
//  Report: class-wise result, PTM "said will come" vs "came", every complaint, calls per person. Print.
//  No inline handlers (strict CSP): every button carries data-a + data-* and one click listener runs it.
// ============================================================================
(function () {
  'use strict';
  const EMB = /[?&]embed=1/.test(location.search); if (EMB) document.body.classList.add('emb');
  if (/[?&]from=t/.test(location.search)) { const b = document.getElementById('pcBack'); if (b) { b.href = 'teacher.php'; b.textContent = '← Teacher App'; } }   // opened from the Teacher App
  const $ = id => document.getElementById(id);
  const esc = s => String(s == null ? '' : s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
  const tok = () => { try { return localStorage.getItem('sch_token') || ''; } catch (e) { return ''; } };
  const DAY = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'], MON = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
  const fmtD = d => { if (!d) return ''; const x = new Date(d + 'T00:00:00'); return isNaN(x) ? d : DAY[x.getDay()] + ' ' + x.getDate() + ' ' + MON[x.getMonth()]; };
  const iso = x => x.getFullYear() + '-' + String(x.getMonth() + 1).padStart(2, '0') + '-' + String(x.getDate()).padStart(2, '0');
  const addDays = (d, n) => { const x = new Date(d + 'T00:00:00'); x.setDate(x.getDate() + n); return iso(x); };
  const ini = n => String(n || '?').split(/\s+/).filter(Boolean).map(w => w[0]).slice(0, 2).join('').toUpperCase();
  const tel = p => p ? `<a class="ib call" href="tel:+91${esc(p)}" target="_top" data-a="dial" aria-label="Call">📞</a>` : '<span class="ib dis" title="No phone number saved">📞</span>';
  const wa = (p, t) => p ? `<a class="ib" href="https://wa.me/91${esc(p)}?text=${encodeURIComponent(t)}" target="_blank" rel="noopener" aria-label="WhatsApp">💬</a>` : '';
  let toastT = 0;
  function toast(m) { const t = $('toast'); t.textContent = m; t.classList.add('on'); clearTimeout(toastT); toastT = setTimeout(() => t.classList.remove('on'), 2600); }
  async function api(path, body) {
    let r, d;
    try { r = await fetch('calls_api.php?' + path, { method: body ? 'POST' : 'GET', cache: 'no-store', headers: { 'Content-Type': 'application/json', 'X-School-Token': tok() }, ...(body ? { body: JSON.stringify(body) } : {}) }); }
    catch (e) { throw Error('No internet. Please try again.'); }
    try { d = await r.json(); } catch (e) { throw Error('Server problem. Please try again.'); }
    if (r.status === 401) throw Error('Please log in again (ERP / Teacher App), then open this page.');
    if (!r.ok || !d.ok) throw Error(d.error || 'Could not open.');
    return d;
  }

  let ST = null, V = { s: 'home' }, L = {}, REP = {}, SH = null, R = null, NEW = null, DIALED = null;
  const TYPE_IC = { ptm: '👨‍👩‍👧', event: '🎉', exam: '📝', holiday: '🏖', fee: '₹', other: '📣' };
  const app = () => $('app');
  function nav(v, push) { V = v; if (push !== false) try { history.pushState({ pc: v }, ''); } catch (e) {} draw(); window.scrollTo(0, 0); }
  window.addEventListener('popstate', e => { if (!$('sheet').hidden) { closeSheet(true); return; } V = (e.state && e.state.pc) || { s: 'home' }; draw(); });

  async function load(keep) {
    if (!keep) app().innerHTML = '<div class="load">Opening…</div>';
    try { ST = await api('a=state'); draw(); }
    catch (e) { app().innerHTML = `<div class="card">${esc(e.message)}<div class="btns"><button class="btn" data-a="reload">Try again</button></div></div>`; }
  }
  function draw() {
    if (!ST) return;
    try { app().innerHTML = V.s === 'list' ? vList() : V.s === 'report' ? vReport() : V.s === 'again' ? vAgain() : vHome(); }
    catch (e) { console.error(e); app().innerHTML = `<div class="card">This screen could not open: ${esc(e.message)}</div>`; }
  }
  const camp = id => ST.campaigns.find(c => c.id === id);

  // ------------------------------------------------------------------ home
  function vHome() {
    const A = ST.att, cls = Object.keys(A), tot = k => cls.reduce((n, c) => n + (A[c][k] || 0), 0);
    const live = ST.campaigns.filter(c => c.active), old = ST.campaigns.filter(c => !c.active);
    const again = ST.again.length ? `<div class="card"><h3>🔁 Call again today <span class="pill warn">${ST.again.length}</span></h3>
      <p class="mut" style="margin:0 0 6px">Parents who did not answer, or asked you to call back.</p>
      ${ST.again.slice(0, 4).map(rowAgain).join('')}${ST.again.length > 4 ? `<button class="btn s" data-a="nav" data-s="again">See all ${ST.again.length}</button>` : ''}</div>` : '';
    const att = !cls.length ? '' : `<div class="card attc"><h3>📅 Attendance calls <small>absent children — call and write the reason</small></h3>
      <div class="kp"><div class="r"><b>${tot('d3')}</b><span>3+ days in a row</span></div><div class="y"><b>${tot('d2')}</b><span>2 days</span></div><div><b>${tot('d1')}</b><span>1 day</span></div><div><b>${tot('low')}</b><span>below 75% (month)</span></div></div>
      <p class="mut" style="margin:8px 0 4px"><b>${tot('todo')}</b> still to call.</p>
      ${cls.length === 1 ? `<button class="btn go full" data-a="open" data-cp="att" data-c="${esc(cls[0])}">Open Class ${esc(cls[0])} absentees</button>`
        : `<div class="tw"><table><tr><th>Class</th><th class="c">3+ d</th><th class="c">2 d</th><th class="c">1 d</th><th class="c">&lt;75%</th><th class="c">To call</th><th></th></tr>${cls.map(c => { const x = A[c];
          return `<tr><td><b>${esc(c)}</b>${x.last && x.last !== ST.today ? `<br><small class="mut">marked ${esc(fmtD(x.last))}</small>` : !x.last ? '<br><small class="mut">not marked</small>' : ''}</td><td class="c">${x.d3 || ''}</td><td class="c">${x.d2 || ''}</td><td class="c">${x.d1 || ''}</td><td class="c">${x.low || ''}</td><td class="c"><b>${x.todo || ''}</b></td><td><button class="btn s" data-a="open" data-cp="att" data-c="${esc(c)}">Open</button></td></tr>`; }).join('')}</table></div>`}
      <div class="btns"><button class="btn s" data-a="nav" data-s="report" data-cp="att">Attendance call report</button></div></div>`;
    const newBtn = ST.canCampaign ? `<button class="btn gold" data-a="new">＋ New calling list</button>` : '';
    return again + att + `<div class="row" style="margin:6px 2px 8px"><h2 style="margin:0">Calling lists</h2>${newBtn}</div>
      ${live.map(cardCamp).join('') || `<div class="card mut">No calling list right now.${ST.canCampaign ? ' Make one for a PTM, function, exam or any news for parents — tap “New calling list”.' : ' The Principal / class teacher makes them (PTM, function, exam …).'}</div>`}
      ${old.length ? `<details class="card"><summary class="mut">Closed lists (${old.length})</summary>${old.map(cardCamp).join('')}</details>` : ''}
      <p class="mut">Fee calls are in the fee calling list (Teacher App → Connect → Fee calls; ERP → Dues Calling). Every call you save here also shows in the child's report.</p>`;
  }
  function cardCamp(c) {
    const n = c.n, pct = n.kids ? Math.round(100 * n.called / n.kids) : 0, ask = ['ptm', 'event'].includes(c.type);
    return `<div class="card camp t-${esc(c.type)} ${c.active ? '' : 'off'}"><h3>${TYPE_IC[c.type] || '📣'} ${esc(c.title)} ${c.active ? '' : '<span class="pill">closed</span>'}</h3>
      <p class="mut" style="margin:0">${c.date ? esc(fmtD(c.date)) + (c.time ? ' · ' + esc(c.time) : '') + ' · ' : ''}${c.allClasses ? 'All classes' : 'Class ' + esc(c.classes.join(', '))} · by ${esc(c.by)}</p>
      <div class="bar"><i style="width:${pct}%"></i></div>
      <div class="kp"><div><b>${n.called}/${n.kids}</b><span>called</span></div>${ask ? `<div class="g"><b>${n.yes}</b><span>will come</span></div><div class="r"><b>${n.no}</b><span>not coming</span></div>` : `<div class="g"><b>${n.talked}</b><span>talked</span></div><div class="y"><b>${n.kids - n.called}</b><span>left</span></div>`}<div class="y"><b>${n.noans}</b><span>no answer</span></div></div>
      ${n.bad ? `<p style="margin:8px 0 0"><span class="pill bad">😟 ${n.bad} complaint${n.bad > 1 ? 's' : ''}</span></p>` : ''}
      <div class="btns">${c.active ? `<button class="btn go s" data-a="open" data-cp="${esc(c.id)}" data-c="${esc(c.classes[0] || '')}">📞 Start calling</button>` : ''}<button class="btn s" data-a="nav" data-s="report" data-cp="${esc(c.id)}">Report</button>
        ${c.mine ? `<button class="btn s" data-a="edit" data-cp="${esc(c.id)}">Edit</button><button class="btn s" data-a="cpstate" data-cp="${esc(c.id)}" data-on="${c.active ? 0 : 1}">${c.active ? 'Close' : 'Reopen'}</button>` : ''}
        ${ST.role === 'principal' ? `<button class="btn s" data-a="cpdel" data-cp="${esc(c.id)}">Delete</button>` : ''}</div></div>`;
  }
  function rowAgain(x) {
    return `<div class="li"><span class="av y">${esc(ini(x.name))}</span><div class="n"><b>${esc(x.name)} <small style="display:inline">· Class ${esc(x.cls)}</small></b><small>${esc(x.title)} · last: ${esc(x.outcome)}${x.next !== ST.today ? ' · due ' + esc(fmtD(x.next)) : ''}${x.note ? ' · ' + esc(x.note) : ''}</small></div>
      <div class="acts">${tel(x.phone).replace('data-a="dial"', `data-a="dial" data-cp="${esc(x.cp)}" data-c="${esc(x.cls)}" data-sid="${esc(x.sid)}"`)}${ST.canCall ? `<button class="ib" data-a="open" data-cp="${esc(x.cp)}" data-c="${esc(x.cls)}" aria-label="Open list">›</button>` : ''}</div></div>`;
  }
  function vAgain() { return `<button class="btn s back" data-a="home">← Back</button><div class="card"><h3>🔁 Call again today <span class="pill warn">${ST.again.length}</span></h3>${ST.again.map(rowAgain).join('') || '<div class="empty">Nobody to call again. 👍</div>'}</div>`; }

  // ------------------------------------------------------------------ list (one class)
  function listKey() { return V.cp + '|' + V.cls; }
  function fetchList(force) {
    const k = listKey(); if (L[k] && !force && !L[k].err) return L[k];
    L[k] = { loading: true };
    api('a=list&cp=' + encodeURIComponent(V.cp) + '&cls=' + encodeURIComponent(V.cls)).then(d => { L[k] = d; if (listKey() === k) draw(); if (DIALED && DIALED.k === k) { const s = DIALED.sid; DIALED = null; result(s); } })
      .catch(e => { L[k] = { err: e.message }; if (listKey() === k) draw(); });
    return L[k];
  }
  const lastOf = (D, k) => k.calls[0] || null;
  function attDone(k) { const l = k.calls[0]; if (!l) return false; return k.abs.streak > 0 ? l.date >= k.abs.from : l.date >= addDays(ST.today, -7); }
  function stateOf(D, k) {
    const l = lastOf(D, k);
    if (D.cp === 'att') return attDone(k) ? (l.outcome === 'Talked' ? 'done' : 'noans') : 'todo';
    if (!l) return 'todo'; if (l.outcome !== 'Talked') return 'noans';
    return { 'Will come': 'yes', 'Not coming': 'no', 'Not sure': 'maybe' }[l.ans] || 'done';
  }
  function vList() {
    const isAtt = V.cp === 'att', c = isAtt ? null : camp(V.cp);
    const classes = isAtt ? Object.keys(ST.att) : (c ? c.classes : []);
    if (!classes.includes(V.cls)) V.cls = classes[0] || '';
    const back = `<button class="btn s back" data-a="home">← All calls</button>`;
    if (!isAtt && !c) return back + '<div class="card">This calling list is not open for you (it may have been deleted).</div>';
    const pick = classes.length > 1 ? `<div class="fld">Class<select class="inp" data-a="cls">${classes.map(x => `<option value="${esc(x)}" ${x === V.cls ? 'selected' : ''}>Class ${esc(x)}${isAtt && ST.att[x] && ST.att[x].todo ? ' — ' + ST.att[x].todo + ' to call' : ''}</option>`).join('')}</select></div>` : '';
    if (!V.cls) return back + '<div class="card">No class.</div>';
    const D = fetchList();
    const head = `<div class="card ${isAtt ? 'attc' : 'camp t-' + esc(c ? c.type : '')}"><h3>${isAtt ? '📅 Attendance calls' : (TYPE_IC[c.type] || '📣') + ' ' + esc(c.title)} <small>Class ${esc(V.cls)}</small></h3>${pick}</div>`;
    if (D.loading) return back + head + '<div class="load">Loading children…</div>';
    if (D.err) return back + head + `<div class="card">${esc(D.err)}<div class="btns"><button class="btn" data-a="relist">Try again</button></div></div>`;
    const K = D.kids, st = k => stateOf(D, k), n = s => K.filter(k => st(k) === s).length;
    let F;
    if (isAtt) {
      const f = V.f || (n('todo') ? 'todo' : 'all');
      const has = { todo: k => st(k) === 'todo', d3: k => k.abs.streak >= 3, d2: k => k.abs.streak === 2, d1: k => k.abs.streak === 1, low: k => k.abs.streak === 0, done: k => st(k) !== 'todo', all: () => true };
      F = [f, [['todo', 'To call'], ['d3', '3+ days'], ['d2', '2 days'], ['d1', '1 day'], ['low', 'Below 75%'], ['done', 'Called'], ['all', 'All']].map(([k, l]) => [k, l, K.filter(has[k]).length]), K.filter(has[f])];
    } else {
      const f = V.f || (n('todo') ? 'todo' : 'all'), ask = ['ptm', 'event'].includes(c.type);
      const flt = ask ? [['todo', 'Not called'], ['noans', 'No answer'], ['yes', 'Will come'], ['no', 'Not coming'], ['maybe', 'Not sure'], ['all', 'All']] : [['todo', 'Not called'], ['noans', 'No answer'], ['done', 'Talked'], ['all', 'All']];
      F = [f, flt.map(([k, l]) => [k, l, k === 'all' ? K.length : n(k)]), K.filter(k => f === 'all' || st(k) === f)];
    }
    const [f, chips, shown] = F;
    const script = isAtt ? `Namaste 🙏 ${ST.school || 'School'} se bol rahe hain. Aapka bachcha school nahi aa raha — kya baat hai? Sab theek hai? Bachche ko kal se zaroor bhejiye.` : (c.script || '');
    const sum = isAtt ? `<p class="mut" style="margin:0 0 6px">${D.last ? 'Attendance as on <b>' + esc(fmtD(D.last)) + '</b>' + (D.last !== ST.today ? ' — today\'s attendance is not marked yet' : '') : 'No attendance saved for this class in the last 60 days.'} · leave (L) is not counted as absent.</p>`
      : `<div class="kp" style="margin-bottom:8px"><div><b>${K.length - n('todo')}/${K.length}</b><span>called</span></div><div class="g"><b>${['ptm', 'event'].includes(c.type) ? n('yes') : n('done')}</b><span>${['ptm', 'event'].includes(c.type) ? 'will come' : 'talked'}</span></div><div class="r"><b>${n('no')}</b><span>not coming</span></div><div class="y"><b>${n('noans')}</b><span>no answer</span></div></div>`;
    return back + head + `${script ? `<div class="card"><div class="row"><b>What to say</b><button class="btn s" data-a="copy" data-t="${esc(script)}">Copy</button></div><div class="script" style="margin-top:6px">${esc(script)}</div></div>` : ''}
      ${sum}<div class="chips">${chips.map(([k, l, x]) => `<button class="chip ${f === k ? 'on' : ''}" data-a="f" data-f="${k}">${l} <b>${x}</b></button>`).join('')}</div>
      ${D.can ? '' : `<p class="mut">${c && !c.active ? 'This list is closed — you can only see it.' : 'You can only see this list.'}</p>`}
      <div class="list">${shown.map(k => rowKid(D, c, k)).join('') || '<div class="empty">Nobody in this list. 👍</div>'}</div>
      <p class="mut">Tap 📞 — after the call the result box opens by itself. “No answer” puts the parent on tomorrow's “Call again” list.</p>`;
  }
  function rowKid(D, c, k) {
    const l = k.calls[0], s = stateOf(D, k), isAtt = D.cp === 'att';
    const pill = s === 'todo' ? '<span class="pill">Not called</span>' : s === 'noans' ? `<span class="pill warn">${esc(l.outcome)} · ${esc(fmtD(l.date))}</span>`
      : s === 'yes' ? '<span class="pill ok">✓ Will come</span>' : s === 'no' ? '<span class="pill bad">✗ Not coming</span>' : s === 'maybe' ? '<span class="pill info">? Not sure</span>' : `<span class="pill ok">✓ Talked · ${esc(fmtD(l.date))}</span>`;
    const ab = isAtt ? (k.abs.streak ? `<span class="pill ${k.abs.streak >= 3 ? 'bad' : 'warn'}">Absent ${k.abs.streak} day${k.abs.streak > 1 ? 's' : ''}${k.abs.streak > 1 ? ' since ' + esc(fmtD(k.abs.from)) : ''}</span> ` : '') + (k.abs.pct !== null && k.abs.pct !== undefined ? `<span class="pill ${k.abs.pct < 75 ? 'bad' : ''}">Month ${k.abs.pct}%</span> ` : '') : '';
    const came = D.came && D.came[k.sid] !== undefined ? (D.came[k.sid] ? ' <span class="pill ok">✓ came to PTM</span>' : ' <span class="pill bad">did not come</span>') : '';
    const extra = l && s !== 'todo' ? [l.reason, l.comeOn ? 'coming ' + fmtD(l.comeOn) : '', l.fb === 'bad' ? '😟 complaint' : l.fb === 'good' ? '😊' : '', l.note].filter(Boolean).join(' · ') : '';
    const av = isAtt ? (k.abs.streak >= 3 ? 'r' : k.abs.streak ? 'y' : '') : s === 'yes' || s === 'done' ? 'g' : s === 'no' ? 'r' : s === 'noans' ? 'y' : '';
    return `<div class="li"><span class="av ${av}">${esc(ini(k.name))}</span><div class="n"><b>${esc(k.name)}</b><small>${k.father ? esc(k.father) + ' · ' : ''}${k.phone ? esc(k.phone) : '<span class="pill bad">no phone</span>'}</small><small>${ab}${pill}${came}${extra ? ' ' + esc(extra) : ''}${l && s !== 'todo' ? ' <span class="mut">— ' + esc(l.by) + '</span>' : ''}</small></div>
      <div class="acts">${D.can ? tel(k.phone).replace('data-a="dial"', `data-a="dial" data-sid="${esc(k.sid)}"`) + `<button class="ib" data-a="res" data-sid="${esc(k.sid)}" aria-label="Write call result">✓</button>` : ''}${wa(k.phone, waText(D, c, k))}</div></div>`;
  }
  function waText(D, c, k) {
    const sch = ST.school || 'School';
    if (D.cp === 'att') { const n = k.abs.streak, since = n > 1 ? ` (${fmtD(k.abs.from)} se)` : '';
      return n ? `Dear Parent,\n${k.name} (Class ${k.cls}) has been absent from school for ${n} day${n > 1 ? 's' : ''}${n > 1 ? ' since ' + fmtD(k.abs.from) : ''}. Kindly tell us the reason and send the child to school regularly.\n${k.name} ${n} दिन से${since} विद्यालय नहीं आ रहे हैं। कृपया कारण बताएँ और बच्चे को नियमित भेजें।\n— ${sch}`
        : `Dear Parent,\n${k.name} (Class ${k.cls})'s attendance this month is ${k.abs.pct}%. 75% attendance is required. Kindly send the child to school every day.\nइस माह ${k.name} की उपस्थिति ${k.abs.pct}% है। 75% उपस्थिति ज़रूरी है, कृपया बच्चे को रोज़ भेजें।\n— ${sch}`; }
    const when = c.date ? `${fmtD(c.date)}${c.time ? ', ' + c.time : ''}` : '';
    return `Dear Parent,\n*${c.title}*${when ? '\n📅 ' + when : ''}\n${c.script || ''}\n(${k.name}, Class ${k.cls})\n— ${sch}`;
  }

  // ------------------------------------------------------------------ result sheet
  function openSheet(html) { $('shBody').innerHTML = html; const s = $('sheet'); if (s.hidden) { s.hidden = false; try { history.pushState({ pc: V, sheet: 1 }, ''); } catch (e) {} } }
  function closeSheet(fromPop) { const s = $('sheet'); if (s.hidden) return; s.hidden = true; SH = null; R = null; NEW = null; if (!fromPop) try { history.back(); } catch (e) {} }
  function result(sid) {
    const D = L[listKey()]; if (!D || !D.kids) return; const k = D.kids.find(x => x.sid === sid); if (!k) return;
    if (!R || R.sid !== sid) R = { sid, out: '', ans: '', reason: '', fb: '', comeOn: addDays(ST.today, 1), next: '' };
    SH = 'res'; drawRes();
  }
  function drawRes() {
    const D = L[listKey()], k = D.kids.find(x => x.sid === R.sid), isAtt = D.cp === 'att', c = isAtt ? null : camp(D.cp), ask = c && ['ptm', 'event'].includes(c.type), talk = R.out === 'Talked';
    const ch = (key, val, label, cl) => `<button type="button" class="chip ${cl || ''} ${R[key] === val ? 'on' : ''}" data-a="r" data-k="${key}" data-v="${esc(val)}">${esc(label)}</button>`;
    const hist = k.calls.length ? `<p class="mut" style="margin:6px 0 0">Earlier: ${k.calls.slice(0, 3).map(x => esc(fmtD(x.date) + ' ' + x.outcome + (x.ans ? ' — ' + x.ans : '') + (x.reason ? ' — ' + x.reason : ''))).join(' · ')}</p>` : '';
    openSheet(`<h2>Call result · ${esc(k.name)}</h2><p class="mut" style="margin:0">Class ${esc(k.cls)}${k.father ? ' · ' + esc(k.father) : ''}${k.phone ? ' · ' + esc(k.phone) : ''} · ${isAtt ? 'Attendance' + (k.abs.streak ? ' — absent ' + k.abs.streak + ' day' + (k.abs.streak > 1 ? 's' : '') : ' — month ' + k.abs.pct + '%') : esc(c.title)}</p>${hist}
      <div class="step">1 · What happened?</div><div class="chips">${ST.outcomes.map(o => ch('out', o, o === 'Talked' ? '✅ Talked' : o, o === 'Talked' ? 'g' : 'y')).join('')}</div>
      ${talk && ask ? `<div class="step">2 · Will the parent come?</div><div class="chips">${ST.answers.map(a => ch('ans', a, a, a === 'Will come' ? 'g' : a === 'Not coming' ? 'r' : 'y')).join('')}</div>` : ''}
      ${talk && isAtt ? `<div class="step">2 · Why absent?</div><div class="chips">${ST.reasons.map(a => ch('reason', a, a)).join('')}</div>
        <label class="fld">Child will come to school on<input type="date" class="inp" id="rCome" min="${ST.today}" max="${addDays(ST.today, 30)}" value="${esc(R.comeOn)}"></label>` : ''}
      ${talk ? `<div class="step">${ask || isAtt ? '3' : '2'} · Parent's feedback</div><div class="chips">${ch('fb', 'good', '😊 Happy', 'g')}${ch('fb', 'ok', '😐 Normal')}${ch('fb', 'bad', '😟 Complaint', 'r')}</div>` : ''}
      <label class="fld">${R.fb === 'bad' ? '<b>Complaint — what did the parent say?</b> (Principal reads it)' : 'What was discussed (optional)'}<textarea class="inp" id="rNote" maxlength="400" placeholder="${isAtt ? 'e.g. fever, will come on Monday' : 'e.g. father will come, mother asked about the bus'}"></textarea></label>
      <label class="fld">Call again on ${talk ? '(optional)' : '(blank = tomorrow)'}<input type="date" class="inp" id="rNext" min="${ST.today}" max="${addDays(ST.today, 30)}" value="${esc(R.next)}"></label>
      <div class="btns"><button class="btn go full" data-a="rsave">Save call result</button></div>`);
  }
  async function saveRes() {
    const D = L[listKey()], note = ($('rNote') || {}).value || '';
    if (!R.out) return toast('Choose what happened on the call.');
    const body = { sid: R.sid, cp: D.cp, outcome: R.out, ans: R.ans, reason: R.reason, fb: R.fb, note, next: ($('rNext') || {}).value || '', comeOn: ($('rCome') || {}).value || '' };
    const b = document.querySelector('[data-a="rsave"]'); if (b) b.disabled = true;
    try { const r = await api('a=call', body); const k = D.kids.find(x => x.sid === R.sid); k.calls.unshift(r.call); closeSheet(); toast('Saved. The Principal can see it.'); draw(); load(true); }
    catch (e) { toast(e.message); if (b) b.disabled = false; }
  }

  // ------------------------------------------------------------------ new / edit calling list
  const SCRIPTS = {
    ptm: (d, t) => `Namaste 🙏 ${ST.school || 'School'} se bol rahe hain. ${d ? fmtD(d) + ' ko' : ''}${t ? ' ' + t + ' baje' : ''} Parent–Teacher Meeting (PTM) hai. Bachche ki padhai, copy aur result par baat hogi. Aap zaroor aaiye.\nParent–Teacher Meeting on ${d ? fmtD(d) : '—'}${t ? ' at ' + t : ''}. Please come.`,
    event: (d, t, ti) => `Namaste 🙏 ${ST.school || 'School'} se bol rahe hain. ${d ? fmtD(d) + ' ko' : ''} school mein "${ti || 'programme'}" hai${t ? ', ' + t + ' baje' : ''}. Aap parivaar ke saath aamantrit hain.`,
    exam: (d, t, ti) => `Namaste 🙏 ${ST.school || 'School'} se. ${ti || 'Exam'} ${d ? fmtD(d) + ' se' : ''} shuru ho rahe hain. Bachche ko roz padhaiye, date sheet WhatsApp group mein hai. Exam ke din bachcha time par aaye.`,
    holiday: (d, t, ti) => `Namaste 🙏 ${ST.school || 'School'} se. Soochna: ${ti || ''}${d ? ' — ' + fmtD(d) : ''}${t ? ' (' + t + ')' : ''}.`,
    fee: (d) => `Namaste 🙏 ${ST.school || 'School'} se. Fees ke baare mein soochna${d ? ' — ' + fmtD(d) + ' tak' : ''}. Kripya office mein sampark karein.`,
    other: (d, t, ti) => `Namaste 🙏 ${ST.school || 'School'} se. ${ti || ''}${d ? ' — ' + fmtD(d) : ''}${t ? ', ' + t : ''}.`
  };
  const autoTitle = (type, d) => ({ ptm: 'PTM', event: 'Annual function', exam: 'Exam', holiday: 'Holiday notice', fee: 'Fee notice', other: 'Information' })[type] + (d ? ' — ' + fmtD(d) : '');
  function newList(id) {
    const c = id ? camp(id) : null;
    NEW = c ? { id: c.id, type: c.type, title: c.title, date: c.date, time: c.time || '', classes: c.allClasses ? [] : c.classes.slice(), script: c.script || '', tEd: true, sEd: true }
      : { id: '', type: 'ptm', title: '', date: addDays(ST.today, 2), time: '10:00 AM', classes: ST.role === 'teacher' ? ST.ctClasses.slice() : [], script: '', tEd: false, sEd: false };
    if (!c) refreshAuto();
    SH = 'new'; drawNew();
  }
  function drawNew() {
    const N = NEW, isT = ST.role === 'teacher', all = !N.classes.length;
    openSheet(`<h2>${N.id ? 'Edit calling list' : 'New calling list'}</h2><p class="mut" style="margin:0">${isT ? 'For your own class.' : 'Choose the classes — the class teachers and the calling desk see it at once.'}</p>
      <div class="step">What is it about?</div><div class="chips">${Object.entries(ST.types).map(([k, l]) => `<button type="button" class="chip ${N.type === k ? 'on' : ''}" data-a="nt" data-v="${k}">${TYPE_IC[k] || ''} ${esc(l)}</button>`).join('')}</div>
      <label class="fld">Title<input class="inp" id="nTitle" maxlength="120" value="${esc(N.title)}"></label>
      <div class="row" style="gap:8px"><label class="fld" style="flex:1">Date<input type="date" class="inp" id="nDate" value="${esc(N.date)}"></label><label class="fld" style="flex:1">Time<input class="inp" id="nTime" maxlength="40" value="${esc(N.time)}" placeholder="10:00 AM"></label></div>
      <div class="step">Classes</div><div class="chips">${isT ? '' : `<button type="button" class="chip ${all ? 'on' : ''}" data-a="nc" data-v="">All classes</button>`}${ST.ctClasses.map(x => `<button type="button" class="chip ${N.classes.includes(x) ? 'on' : ''}" data-a="nc" data-v="${esc(x)}">${esc(x)}</button>`).join('')}</div>
      <label class="fld">What to say on the call (everyone calling reads this)<textarea class="inp" id="nScript" maxlength="600">${esc(N.script)}</textarea></label>
      <div class="btns"><button class="btn go full" data-a="nsave">${N.id ? 'Save changes' : 'Make the list — start calling'}</button></div>`);
  }
  function grabNew() { const N = NEW; if (!N) return; const g = id => ($(id) || {}).value;
    if (g('nTitle') !== undefined && g('nTitle') !== N.title) { N.title = g('nTitle'); N.tEd = true; }
    if (g('nScript') !== undefined && g('nScript') !== N.script) { N.script = g('nScript'); N.sEd = true; }
    N.date = g('nDate') ?? N.date; N.time = g('nTime') ?? N.time; }
  function refreshAuto() { const N = NEW; if (!N.tEd) N.title = autoTitle(N.type, N.date); if (!N.sEd) N.script = SCRIPTS[N.type](N.date, N.time, N.tEd ? N.title : autoTitle(N.type, '')); }
  async function saveNew() {
    grabNew(); const N = NEW;
    if (ST.role === 'teacher' && !N.classes.length) return toast('Choose your class.');
    const b = document.querySelector('[data-a="nsave"]'); if (b) b.disabled = true;
    try { const r = await api('a=campaign', { id: N.id, title: N.title, type: N.type, date: N.date, time: N.time, classes: N.classes, script: N.script });
      closeSheet(); toast(N.id ? 'Saved.' : 'Calling list made.'); await load(true);
      if (!N.id) { const c = camp(r.campaign.id); if (c) nav({ s: 'list', cp: c.id, cls: c.classes[0] }); } }
    catch (e) { toast(e.message); if (b) b.disabled = false; }
  }

  // ------------------------------------------------------------------ report
  function vReport() {
    const k = V.cp + '|' + (V.days || 30), isAtt = V.cp === 'att';
    if (!REP[k] || REP[k].err) { REP[k] = { loading: true }; api('a=report&cp=' + encodeURIComponent(V.cp) + '&days=' + (V.days || 30)).then(d => { REP[k] = d; draw(); }).catch(e => { REP[k] = { err: e.message }; draw(); }); }
    const D = REP[k], back = `<button class="btn s back" data-a="home">← All calls</button>`;
    if (D.loading) return back + '<div class="load">Loading report…</div>';
    if (D.err) return back + `<div class="card">${esc(D.err)}</div>`;
    const c = D.campaign, ask = c && ['ptm', 'event'].includes(c.type), ptm = c && c.type === 'ptm';
    const T = D.rows.reduce((t, r) => { for (const x of ['kids', 'called', 'talked', 'noans', 'yes', 'no', 'maybe', 'good', 'bad', 'came', 'saidCame']) t[x] = (t[x] || 0) + r[x]; return t; }, {});
    const reasons = {}; D.rows.forEach(r => Object.entries(r.reasons).forEach(([x, n]) => reasons[x] = (reasons[x] || 0) + n));
    const cols = isAtt ? [['called', 'Children called'], ['talked', 'Talked'], ['noans', 'No answer'], ['good', '😊'], ['bad', '😟']]
      : [['kids', 'Children'], ['called', 'Called'], ['talked', 'Talked'], ['noans', 'No answer'], ...(ask ? [['yes', 'Will come'], ['no', 'Not coming'], ['maybe', 'Not sure']] : []), ...(ptm ? [['came', 'Came'], ['saidCame', 'Said yes + came']] : []), ['good', '😊'], ['bad', '😟']];
    return back + `<div class="card"><div class="row"><h3 style="margin:0">${isAtt ? '📅 Attendance calls — last ' + D.days + ' days' : (TYPE_IC[c.type] || '📣') + ' ' + esc(c.title)}</h3><button class="btn s" data-a="print">🖨 Print</button></div>
      ${isAtt ? `<div class="chips">${[7, 30, 90].map(d => `<button class="chip ${(V.days || 30) === d ? 'on' : ''}" data-a="days" data-d="${d}">${d} days</button>`).join('')}</div>` : `<p class="mut" style="margin:4px 0 0">${c.date ? esc(fmtD(c.date)) + (c.time ? ' · ' + esc(c.time) : '') : ''} · ${D.total} calls saved</p>`}
      ${ptm && T.came === 0 && c.date && c.date <= ST.today ? '<p class="mut">Who came is counted from the PTM attendance the class teacher fills in the Teacher App (Attendance → Levels → PTM).</p>' : ''}
      <div class="tw" style="margin-top:8px"><table><tr><th>Class</th><th>Class teacher</th>${cols.map(([, l]) => `<th class="c">${l}</th>`).join('')}</tr>
      ${D.rows.map(r => `<tr><td><b>${esc(r.cls)}</b></td><td>${esc(r.ct)}</td>${cols.map(([x]) => `<td class="c">${r[x] || ''}</td>`).join('')}</tr>`).join('')}
      <tr class="tot"><td>Total</td><td></td>${cols.map(([x]) => `<td class="c">${T[x] || 0}</td>`).join('')}</tr></table></div></div>
      ${Object.keys(reasons).length ? `<div class="card"><h3>Why children were absent</h3><div class="chips">${Object.entries(reasons).sort((a, b) => b[1] - a[1]).map(([x, n]) => `<span class="pill info">${esc(x)} · <b>${n}</b></span>`).join(' ')}</div></div>` : ''}
      <div class="card"><h3>😟 Complaints from parents <span class="pill ${D.complaints.length ? 'bad' : 'ok'}">${D.complaints.length}</span></h3>
        ${D.complaints.map(x => `<div class="li"><span class="av r">${esc(ini(x.name))}</span><div class="n"><b>${esc(x.name)} <small style="display:inline">· Class ${esc(x.cls)}</small></b><small>${esc(x.note)}</small><small>${esc(fmtD(x.date))} · ${esc(x.by)}</small></div><div class="acts">${tel(x.phone)}</div></div>`).join('') || '<div class="empty">No complaint. 👍</div>'}</div>
      <div class="card"><h3>Calls by person</h3>${Object.entries(D.people).map(([p, n]) => `<span class="pill">${esc(p)} · <b>${n}</b></span>`).join(' ') || '<span class="mut">No calls yet.</span>'}</div>`;
  }

  // ------------------------------------------------------------------ clicks
  document.addEventListener('click', async e => {
    const t = e.target.closest('[data-a],[data-close]'); if (!t) return;
    if (t.hasAttribute('data-close')) return closeSheet();
    const a = t.dataset.a;
    switch (a) {
      case 'reload': return load();
      case 'home': return nav({ s: 'home' });
      case 'nav': return nav({ s: t.dataset.s, cp: t.dataset.cp || '' });
      case 'open': return nav({ s: 'list', cp: t.dataset.cp, cls: t.dataset.c || '', f: '' });
      case 'relist': fetchList(true); return draw();
      case 'f': V.f = t.dataset.f; return draw();
      case 'days': V.days = +t.dataset.d; return draw();
      case 'dial': {   // the phone app opens; when the teacher comes back the result box is waiting
        const sid = t.dataset.sid; if (!sid) return;
        if (t.dataset.cp) { V = { s: 'list', cp: t.dataset.cp, cls: t.dataset.c, f: '' }; try { history.pushState({ pc: V }, ''); } catch (x) {} DIALED = { k: listKey(), sid }; const D = L[listKey()]; draw();
          if (D && D.kids) setTimeout(() => { DIALED = null; result(sid); }, 1200); return; }
        setTimeout(() => result(sid), 1200); return;
      }
      case 'res': return result(t.dataset.sid);
      case 'r': { const k = t.dataset.k, v = t.dataset.v; const note = ($('rNote') || {}).value, nx = ($('rNext') || {}).value, cm = ($('rCome') || {}).value;
        R[k] = R[k] === v && k !== 'out' ? '' : v; if (nx !== undefined) R.next = nx; if (cm !== undefined) R.comeOn = cm;
        drawRes(); if (note) $('rNote').value = note; return; }
      case 'rsave': return saveRes();
      case 'new': return newList('');
      case 'edit': return newList(t.dataset.cp);
      case 'nt': grabNew(); NEW.type = t.dataset.v; refreshAuto(); return drawNew();
      case 'nc': { grabNew(); const v = t.dataset.v; if (!v) NEW.classes = []; else NEW.classes = NEW.classes.includes(v) ? NEW.classes.filter(x => x !== v) : [...NEW.classes, v]; return drawNew(); }
      case 'nsave': return saveNew();
      case 'cpstate': try { await api('a=cpstate', { id: t.dataset.cp, active: t.dataset.on === '1' }); toast('Saved.'); load(true); } catch (x) { toast(x.message); } return;
      case 'cpdel': if (!confirm('Delete this calling list? The calls already saved stay in each child\'s report.')) return;
        try { await api('a=cpdel', { id: t.dataset.cp }); toast('Deleted.'); load(true); } catch (x) { toast(x.message); } return;
      case 'copy': try { await navigator.clipboard.writeText(t.dataset.t); toast('Copied.'); } catch (x) { toast('Could not copy — press and hold the text.'); } return;
      case 'print': return window.print();
    }
  });
  document.addEventListener('change', e => {
    const t = e.target;
    if (t.dataset.a === 'cls') { V.cls = t.value; V.f = ''; try { history.replaceState({ pc: V }, ''); } catch (x) {} draw(); }
    if (NEW && (t.id === 'nDate' || t.id === 'nTime')) { grabNew(); refreshAuto(); drawNew(); }
  });
  // coming back from the phone app → fresh numbers (other teachers may have called too)
  document.addEventListener('visibilitychange', () => { if (!document.hidden && ST && $('sheet').hidden) { if (V.s === 'list') fetchList(true); load(true); } });
  try { history.replaceState({ pc: V }, ''); } catch (e) {}
  const q = new URLSearchParams(location.search); if (q.get('cp')) V = { s: 'list', cp: q.get('cp'), cls: q.get('c') || '' };
  load();
})();
