'use strict';
/* Family App usage (owner, 28 Nov 2026: "kitne student login kiye hain kaise pata chalega"). Principal / Chairman, using the
   login already on this device (sch_token). Data: famstats_api.php?a=data — read-only. */
(function () {
  const store = { get: k => { try { const v = localStorage.getItem(k); if (v && v[0] === '"') return JSON.parse(v); return v; } catch (e) { return null; } } };
  const $ = id => document.getElementById(id), app = $('app');
  const esc = s => String(s == null ? '' : s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const pct = (a, b) => b ? Math.round(a * 100 / b) : 0;
  const tone = p => p >= 70 ? '#0f8a5f' : p >= 40 ? '#f2a93b' : '#c0352b';
  let D = null, CLS = '', Q = '', FB = null;
  if (/[?&]embed=1/.test(location.search)) document.body.classList.add('emb');
  async function load() {
    const t = store.get('sch_token'); if (!t) { app.innerHTML = '<div class="msg">Please log in as the Principal or Chairman first, then open this page again.</div>'; return; }
    try { const r = await fetch('famstats_api.php?a=data', { cache: 'no-store', headers: { 'X-School-Token': t } }); const j = await r.json(); if (!r.ok || !j.ok) throw new Error(j.error || 'Could not load.'); D = j;
      try { const f = await fetch('famfb_api.php?a=list', { cache: 'no-store', headers: { 'X-School-Token': t } }); const fj = await f.json(); if (f.ok && fj.ok) FB = fj; } catch (e) {}
      draw(); }
    catch (e) { app.innerHTML = `<div class="msg">${esc(e.message)}</div>`; }
  }
  function invite(n) {
    const msg = `Dear Parent of ${n.name} (Class ${n.cls}),\n\nPlease install the SN Mishra Singapore International School app for students and parents:\n${D.apk}\n\nLog in with this mobile number. The first PIN is 1234 (you will then set your own PIN).\nHomework, attendance, results, calendar and school notices — all in one place.\n\nनमस्ते, कृपया बच्चे का स्कूल ऐप ऊपर दिए लिंक से इंस्टॉल करें। इसी मोबाइल नंबर से लॉग इन करें, पहला PIN 1234 है।\n— SN Mishra Singapore International School, Bhadohi`;
    return 'https://wa.me/91' + n.phone + '?text=' + encodeURIComponent(msg);
  }
  function draw() {
    const T = D.totals, mx = Math.max(5, ...D.trend.map(x => x.parents + x.students));
    const never = D.never.filter(n => (!CLS || n.cls === CLS) && (!Q || (n.name + ' ' + n.phone).toLowerCase().includes(Q)));
    app.innerHTML = `<div class="top"><span class="r"><button id="rf">Refresh</button><button onclick="print()">Print</button></span><b>📱 Student & Parents App — who is using it</b><small>${esc(D.school)} · updated ${new Date().toLocaleString('en-IN', { day: 'numeric', month: 'short', hour: 'numeric', minute: '2-digit' })}</small></div>
    <div class="kp">
      <div><span>Families logged in</span><b>${T.famLogged}</b><i>of ${T.families} · ${pct(T.famLogged, T.families)}%</i><div class="bar"><em style="width:${pct(T.famLogged, T.families)}%"></em></div></div>
      <div><span>Students covered (child or parent)</span><b>${T.covered}</b><i>of ${T.students} · ${pct(T.covered, T.students)}%</i><div class="bar"><em style="width:${pct(T.covered, T.students)}%;background:#14b8a6"></em></div></div>
      <div><span>Students' own logins</span><b>${T.stuLogged}</b><i>${pct(T.stuLogged, T.students)}%</i></div>
      <div><span>Opened the app today</span><b>${T.today}</b></div>
      <div><span>Active in the last 7 days</span><b>${T.week}</b></div>
      <div><span>Set their own PIN</span><b>${T.ownPin}</b></div>
    </div>
    <h3>Last 14 days — logins per day</h3><div class="card"><div class="trend">${D.trend.map(x => { const d = new Date(x.date + 'T00:00:00');
      return `<div title="${esc(x.date)}: ${x.parents} parents, ${x.students} students"><em>${x.parents + x.students || ''}</em><i class="s" style="height:${x.students / mx * 110}px"></i><i class="p" style="height:${x.parents / mx * 110}px"></i><small>${d.getDate()}</small></div>`; }).join('')}</div>
      <div class="leg"><span style="--c:#4338ca">Parents</span><span style="--c:#14b8a6">Students</span></div></div>
    <h3>Class-wise</h3><div class="card"><table><tr><th>Class</th><th class="n">Students</th><th class="n">Logged in</th><th>Coverage</th><th class="n">Parent logins</th><th class="n">Student logins</th><th class="n">Active · 7 days</th></tr>
      ${D.classes.map(c => { const p = pct(c.covered, c.students); return `<tr><td><b>${esc(c.cls)}</b></td><td class="n">${c.students}</td><td class="n">${c.covered}</td><td><div class="pb"><em style="width:${p}%;background:${tone(p)}"></em></div><span class="mut">${p}%</span></td><td class="n">${c.parent}</td><td class="n">${c.student}</td><td class="n">${c.week}</td></tr>`; }).join('')}</table></div>
    <h3>Not logged in yet (${D.never.length})</h3>
    <div class="bar2"><select id="cl"><option value="">All classes</option>${D.classes.map(c => `<option ${c.cls === CLS ? 'selected' : ''}>${esc(c.cls)}</option>`).join('')}</select>
      <input id="q" placeholder="Search name or number" value="${esc(Q)}"><button class="btn" id="csv">Download list (CSV)</button><span class="mut">Tap "Send invite" — WhatsApp opens with the app link and login steps.</span></div>
    <div class="card"><table class="nl"><tr><th>Student</th><th>Class</th><th>Mobile</th><th></th></tr>
      ${never.map(n => `<tr><td><b>${esc(n.name)}</b></td><td>${esc(n.cls)}</td><td>${n.phone ? esc(n.phone.slice(0, 5) + ' ' + n.phone.slice(5)) : '<span class="bad">No number</span>'}</td><td>${n.phone ? `<a class="btn wa" target="_blank" rel="noopener" href="${invite(n)}">Send invite</a>` : '<span class="mut">Add the number in Students</span>'}</td></tr>`).join('') || '<tr><td colspan="4" class="msg ok">Everyone has logged in. 🎉</td></tr>'}</table></div>
    ${FB ? `<h3>Parents' feedback (${FB.n})${FB.avg ? ' · ' + FB.avg + ' ★ average' : ''}</h3>
    ${Object.keys(FB.likes).length ? `<div class="card" style="padding:10px 12px"><b style="font-size:13px">What parents like most:</b> ${Object.entries(FB.likes).map(([k, n]) => `<span class="pill" style="background:#eef2ff;color:#312e81;margin:2px">${esc(k)} · ${n}</span>`).join('')}</div>` : ''}
    <div class="card"><table class="nl"><tr><th>When</th><th>Who</th><th>Message</th><th></th></tr>${FB.items.slice(0, 100).map(x => `<tr><td class="mut">${new Date(x.at * 1000).toLocaleDateString('en-IN', { day: 'numeric', month: 'short' })}</td><td>${esc(x.who)}<br><span class="mut">${esc(x.phone || x.role)}</span></td>
      <td><b>${x.kind === 'feedback' ? '★'.repeat(x.rating || 0) : x.kind === 'complaint' ? '📣 Complaint' : '💡 Suggestion'}</b>${(x.liked || []).length ? ' <span class="mut">likes: ' + esc(x.liked.join(', ')) + '</span>' : ''}${x.text ? '<br>' + esc(x.text) : ''}${x.reply ? `<br><span class="ok">Reply: ${esc(x.reply)}</span>` : ''}</td>
      <td>${x.reply ? '' : `<button class="btn" data-fb="${esc(x.id)}">Reply</button>`}</td></tr>`).join('') || '<tr><td colspan="4" class="msg">No feedback yet.</td></tr>'}</table></div>` : ''}
    <p class="mut" style="padding:10px 14px">"Logged in" = the family's parent login or the child's own student login has been used at least once. Counting of daily opens started on 28 Nov 2026.</p>`;
    document.querySelectorAll('[data-fb]').forEach(b => b.onclick = async () => { const text = prompt('Reply to this parent (shown in their app):'); if (!text) return;
      try { const r = await fetch('famfb_api.php?a=reply', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-School-Token': store.get('sch_token') || '' }, body: JSON.stringify({ id: b.dataset.fb, text }) }); const j = await r.json(); if (!j.ok) throw new Error(j.error); load(); } catch (e) { alert(e.message || 'Could not save.'); } });
    $('rf').onclick = load; $('cl').onchange = e => { CLS = e.target.value; draw(); }; $('q').oninput = e => { Q = e.target.value.toLowerCase(); const pos = e.target.selectionStart; draw(); const q = $('q'); q.focus(); q.setSelectionRange(pos, pos); };
    $('csv').onclick = () => { const rows = [['Student', 'Class', 'Admission no', 'Mobile'], ...never.map(n => [n.name, n.cls, n.adm, n.phone])];
      const a = document.createElement('a'); a.href = URL.createObjectURL(new Blob(['﻿' + rows.map(r => r.map(x => '"' + String(x).replace(/"/g, '""') + '"').join(',')).join('\n')], { type: 'text/csv' })); a.download = 'family-app-not-logged-in.csv'; a.click(); };
  }
  load();
})();
