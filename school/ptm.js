'use strict';
// PTM Desk (owner, 21 Nov 2026: "parents aa rahe hain — entry, feedback, fee, complaint; Pawan Bhalla Sir naam note karein,
// result ke hisaab se suggestion ke saath baat karein aur fill karein"). Data: ptm_api.php. Records: ptmVisit 'date|sid'.
(function () {
  const $ = id => document.getElementById(id);
  const esc = s => String(s == null ? '' : s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
  const CLASSES = ['PG', 'LKG', 'UKG', '1st', '2nd', '3rd', '4th', '5th', '6th', '7th', '8th', '9th', '10th'];
  const MON = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
  const MOODS = [[4, '😀', 'Bahut khush'], [3, '🙂', 'Theek'], [2, '😐', 'Thik-thak'], [1, '😟', 'Naraz']];
  const rs = n => '₹' + Math.round(n || 0).toLocaleString('en-IN');
  const today = () => { const d = new Date(); return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0'); };
  const tok = () => { try { return localStorage.getItem('sch_token') || ''; } catch (e) { return ''; } };
  let TAB = 'desk', DATE = today(), CLS = (() => { try { return localStorage.getItem('ptm_cls') || '1st'; } catch (e) { return '1st'; } })(), DATA = {}, SEL = null, F = null, Q = '', REP = null;
  async function api(path, body) {
    const r = await fetch(path, { method: body ? 'POST' : 'GET', cache: 'no-store', headers: { 'Content-Type': 'application/json', 'X-School-Token': tok() }, ...(body ? { body: JSON.stringify(body) } : {}) });
    let j = null; try { j = await r.json(); } catch (e) {}
    if (r.status === 401) throw Error('Pehle ERP / Teacher App mein login kijiye, phir yeh page kholiye.');
    if (!r.ok || !j || !j.ok) throw Error((j && j.error) || 'Server problem. Dobara try kijiye.'); return j;
  }
  function toast(t) { const d = document.createElement('div'); d.className = 'toast'; d.textContent = t; document.body.appendChild(d); setTimeout(() => d.remove(), 2600); }
  const app = h => { $('app').innerHTML = h; };

  // ---------- talking points from the result, attendance and fee ----------
  function points(k) {
    const P = [], first = k.name.split(/\s+/)[0], r = k.res;
    if (r && r.pct != null) {
      if (r.rank && r.rank <= 3) P.push(`🎉 Class mein <b>${r.rank}${['', 'st', 'nd', 'rd'][r.rank]} rank</b> (${r.pct}%) — parents ko badhai dijiye.`);
      else P.push(`Half-Yearly mein <b>${r.pct}%</b>${r.rank ? ` · class mein ${r.rank}/${r.of}` : ''}.`);
      const strong = r.subs.filter(s => !s.ab && s.t >= 80).map(s => s.s), weak = r.subs.filter(s => !s.ab && s.t < 40), ab = r.subs.filter(s => s.ab).map(s => s.s);
      if (strong.length) P.push(`👍 <b>${esc(strong.join(', '))}</b> mein bahut achha — ${esc(first)} ki taarif kijiye.`);
      weak.forEach(s => P.push(`📘 <b>${esc(s.s)}: ${s.t}/100</b> — roz 30 minute practice, ghar par homework zaroor check karein.`));
      if (!weak.length) { const low = r.subs.filter(s => !s.ab && s.t < 55).map(s => s.s); if (low.length) P.push(`📗 <b>${esc(low.join(', '))}</b> mein thoda aur dhyan — final mein grade badh sakta hai.`); }
      if (ab.length) P.push(`⚠️ <b>${esc(ab.join(', '))}</b> ka exam chhoot gaya (AB) — final exam mein zaroor bhejein.`);
      if (r.nbLow >= 2) P.push('✍️ Copy poori nahi (notebook marks kam) — roz copy dekh kar complete karwayein.');
    } else P.push('Result abhi ERP mein bhara nahi hai — class teacher se slip dekh lijiye.');
    if (k.att && k.att.days) { const p = Math.round(k.att.present * 100 / k.att.days); if (p < 85) P.push(`🗓️ Attendance sirf <b>${p}%</b> (${k.att.days - k.att.present} din absent) — roz school bhejna zaroori.`); else if (p >= 95) P.push(`🗓️ Attendance <b>${p}%</b> — bahut badhiya, dhanyavaad kahiye.`); }
    if (k.fee) { if (k.fee.due > 0) P.push(`💰 Fee <b>${rs(k.fee.due)}</b> baaki${k.fee.months.length ? ' (' + k.fee.months.map(m => MON[m]).join(', ') + ')' : ''} — aaj jama karwayein ya pakki tareekh lijiye.`); else P.push('💰 Fee is mahine tak poori jama — dhanyavaad kahiye.'); }
    return P;
  }

  // ---------- desk ----------
  async function loadCls(force) {
    if (DATA[CLS] && !force) return DATA[CLS];
    DATA[CLS] = await api(`ptm_api.php?a=cls&cls=${encodeURIComponent(CLS)}&date=${DATE}`); return DATA[CLS];
  }
  async function desk() {
    app(`<div class="chips">${CLASSES.map(c => `<button class="${c === CLS ? 'on' : ''}" data-c="${c}">${c}</button>`).join('')}</div><div id="lst"><div class="msg">Class ${esc(CLS)} khul rahi hai…</div></div>`);
    document.querySelectorAll('[data-c]').forEach(b => { b.onclick = () => { CLS = b.dataset.c; try { localStorage.setItem('ptm_cls', CLS); } catch (e) {} SEL = null; desk(); }; });
    let D; try { D = await loadCls(); } catch (e) { $('lst').innerHTML = `<div class="msg">${esc(e.message)}</div>`; return; }
    if (SEL) return kid(D.kids.find(k => k.id === SEL));
    const came = D.kids.filter(k => k.visit).length;
    $('lst').innerHTML = `<input class="search" id="q" placeholder="🔍 Bachche ya pita ka naam…" value="${esc(Q)}"><p style="margin:0 0 6px;color:#5a6b80;font-size:12.5px"><b>${came}</b> / ${D.kids.length} parents aaye · bachche par dabaiye</p><div id="kl"></div>`;
    const draw = () => { const q = Q.toLowerCase(); $('kl').innerHTML = D.kids.filter(k => !q || (k.name + ' ' + k.father).toLowerCase().includes(q)).map(k => {
      const v = k.visit, due = k.fee && k.fee.due > 0;
      return `<div class="kid ${v ? 'came' : ''}" data-k="${esc(k.id)}"><div class="n"><b>${esc(k.name)}</b><small>${esc(k.father ? 'Pita: ' + k.father : '')}${k.res && k.res.pct != null ? ' · ' + k.res.pct + '%' : ''}</small></div><div class="st">${v ? `<span class="pill p-ok">✓ ${esc(v.at)}</span>` : ''}${v && v.cmp && v.cmp.status !== 'Resolved' ? '<span class="pill p-c">Shikayat</span>' : ''}${due ? `<span class="pill p-f">${rs(k.fee.due)}</span>` : ''}</div></div>`; }).join('') || '<div class="msg">Koi bachcha nahi mila.</div>';
      document.querySelectorAll('[data-k]').forEach(el => { el.onclick = () => { SEL = el.dataset.k; kid(D.kids.find(k => k.id === SEL)); scrollTo(0, 0); }; }); };
    $('q').oninput = e => { Q = e.target.value; draw(); }; draw();
  }
  function kid(k) {
    if (!k) { SEL = null; return desk(); }
    const D = DATA[CLS], v = k.visit || {}, r = k.res;
    F = { parent: v.parent || k.father || '', phone: v.phone || k.phone || '', slip: !!v.slip, paid: v.paid || '', promise: v.promise || '', pamt: v.pamt || '', mood: v.mood || 0, tags: (v.tags || []).slice(), note: v.note || '', complaint: (v.cmp && v.cmp.text) || '', cat: (v.cmp && v.cmp.cat) || '', cdue: (v.cmp && v.cmp.due) || '', siblings: true };
    const att = k.att && k.att.days ? Math.round(k.att.present * 100 / k.att.days) : null;
    $('lst').innerHTML = `<button class="btn l s" id="back">← Class ${esc(CLS)} list</button>
      <div class="panel" style="margin-top:8px"><div class="ph"><div style="flex:1"><h2>${esc(k.name)}</h2><p>Class ${esc(CLS)}${k.section ? '-' + esc(k.section) : ''} · Adm. ${esc(k.adm || '—')}</p><p>Pita: <b>${esc(k.father || '—')}</b>${k.mother ? ' · Mata: <b>' + esc(k.mother) + '</b>' : ''}</p>
        ${k.sib.length ? `<p>👨‍👩‍👧 Bhai/behen: ${k.sib.map(s => esc(s.name) + ' (' + esc(s.cls) + ')').join(', ')}</p>` : ''}</div>${k.phone ? `<a class="ic c" href="tel:+91${k.phone}">📞 Call</a>` : ''}</div>
        <div class="stats"><div class="${r && r.pct != null && r.pct < 40 ? 'bad' : r && r.pct >= 80 ? 'good' : ''}"><b>${r && r.pct != null ? r.pct + '%' : '—'}</b><small>Result${r && r.rank ? ' · Rank ' + r.rank : ''}</small></div>
          <div class="${att != null && att < 85 ? 'bad' : att >= 95 ? 'good' : ''}"><b>${att != null ? att + '%' : '—'}</b><small>Attendance</small></div>
          <div class="${k.fee && k.fee.due > 0 ? 'bad' : 'good'}"><b>${k.fee ? rs(k.fee.due) : '—'}</b><small>Fee baaki</small></div></div>
        ${r && r.subs.length ? `<div class="subs">${r.subs.map(s => `<span class="${s.ab || s.t < 40 ? 'w' : s.t >= 80 ? 's' : ''}">${esc(s.s)} ${s.ab ? 'AB' : s.t}</span>`).join('')}</div>` : ''}
        <div class="talk"><b>🗣️ Parents se baat karne ke points</b><ul>${points(k).map(p => `<li>${p}</li>`).join('')}</ul></div>
        <div class="row2"><div><label class="l">Kaun aaya (naam)</label><input class="i" id="fParent" value="${esc(F.parent)}"></div><div><label class="l">Mobile</label><input class="i" id="fPhone" inputmode="numeric" value="${esc(F.phone)}"></div></div>
        <label class="l">Kya hua</label><div class="tog"><button id="fSlip" class="${F.slip ? 'on' : ''}">📄 Slip dikhai / di</button>${k.sib.length ? `<button id="fSib" class="on">👨‍👩‍👧 Bhai/behen ke bhi aaye ✓</button>` : ''}</div>
        <div class="row2"><div><label class="l">Fee aaj jama (₹)</label><input class="i" id="fPaid" inputmode="numeric" value="${esc(F.paid)}" placeholder="0"></div><div><label class="l">Ya fee kab denge</label><input class="i" type="date" id="fPromise" value="${esc(F.promise)}"></div></div>
        <label class="l">Us tareekh ko kitna denge (₹)</label><input class="i" id="fPamt" inputmode="numeric" value="${esc(F.pamt)}" placeholder="${k.fee && k.fee.due > 0 ? Math.round(k.fee.due) : ''}">
        <label class="l">Parent kaise lage (feedback)</label><div class="mood">${MOODS.map(([n, e, t]) => `<button data-m="${n}" title="${t}" class="${F.mood === n ? 'on' : ''}">${e}</button>`).join('')}</div>
        <label class="l">Kis baare mein baat hui</label><div class="tog tagb">${D.tags.map(t => `<button data-tg="${esc(t)}" class="${F.tags.includes(t) ? 'on' : ''}">${esc(t)}</button>`).join('')}</div>
        <label class="l">Feedback / sujhav (parent ne kya kaha, aapne kya samjhaya)</label><textarea class="i" id="fNote" placeholder="Jaise: Maths ke liye extra class chahiye; humne roz 30 min practice bataya">${esc(F.note)}</textarea>
        <div class="cbox"><label class="l">⚠️ Shikayat (agar hai)</label><textarea class="i" id="fCmp" placeholder="Shikayat yahan likhiye — Principal aur Chairman sir dekhenge">${esc(F.complaint)}</textarea>
          <div class="row2" style="margin-top:6px"><select class="i" id="fCat"><option value="">Shikayat kis baare mein</option>${D.tags.map(t => `<option ${F.cat === t ? 'selected' : ''}>${esc(t)}</option>`).join('')}</select><input class="i" type="date" id="fCdue" value="${esc(F.cdue)}" title="Kab tak theek hogi"></div>
          ${v.cmp ? `<p style="margin:6px 0 0;font-size:12px">Status: <b>${esc(v.cmp.status)}</b>${v.cmp.action ? ' · ' + esc(v.cmp.action) : ''}</p>` : ''}</div>
        <div class="bar"><button class="btn g" id="save">✓ Save — parent aaye</button></div></div>`;
    $('back').onclick = () => { SEL = null; desk(); };
    $('fSlip').onclick = e => { F.slip = !F.slip; e.target.classList.toggle('on', F.slip); };
    if ($('fSib')) $('fSib').onclick = e => { F.siblings = !F.siblings; e.target.classList.toggle('on', F.siblings); };
    document.querySelectorAll('[data-m]').forEach(b => { b.onclick = () => { F.mood = +b.dataset.m; document.querySelectorAll('[data-m]').forEach(x => x.classList.toggle('on', +x.dataset.m === F.mood)); }; });
    document.querySelectorAll('[data-tg]').forEach(b => { b.onclick = () => { const t = b.dataset.tg; F.tags = F.tags.includes(t) ? F.tags.filter(x => x !== t) : [...F.tags, t]; b.classList.toggle('on'); }; });
    $('save').onclick = async () => {
      const body = { date: DATE, sid: k.id, parent: $('fParent').value, phone: $('fPhone').value, slip: F.slip, paid: $('fPaid').value, promise: $('fPromise').value, pamt: $('fPamt').value, mood: F.mood, tags: F.tags, note: $('fNote').value,
        complaint: $('fCmp').value, cat: $('fCat').value, cdue: $('fCdue').value, clearComplaint: !$('fCmp').value.trim(), siblings: k.sib.length ? F.siblings : false };
      $('save').disabled = true; $('save').textContent = 'Save ho raha hai…';
      try { const d = await api('ptm_api.php?a=visit', body); k.visit = d.visit;
        Object.values(DATA).forEach(C => (C.kids || []).forEach(x => { if (d.also.includes(x.name) && !x.visit) x.visit = { at: d.visit.at, sid: x.id }; }));
        toast('✓ ' + k.name + ' — save ho gaya' + (d.also.length ? ' (+ ' + d.also.join(', ') + ')' : '')); SEL = null; REP = null; desk(); }
      catch (e) { alert(e.message); $('save').disabled = false; $('save').textContent = '✓ Save — parent aaye'; }
    };
  }

  // ---------- day report ----------
  async function loadRep() { if (!REP) REP = await api('ptm_api.php?a=report&date=' + DATE); return REP; }
  async function report() {
    app('<div class="msg">Report ban rahi hai…</div>');
    let R; try { R = await loadRep(); } catch (e) { return app(`<div class="msg">${esc(e.message)}</div>`); }
    const V = R.visits, n = Object.values(R.classes).reduce((a, c) => a + (c.n || 0), 0), paid = V.reduce((a, v) => a + (v.paid || 0), 0), prom = V.filter(v => v.promise), cmp = V.filter(v => v.cmp);
    const mood = [4, 3, 2, 1].map(m => V.filter(v => v.mood === m).length), rated = mood.reduce((a, b) => a + b, 0);
    const wa = (p, t) => p ? `<a class="ic w" target="_blank" rel="noopener" href="https://wa.me/91${p}?text=${encodeURIComponent(t)}">WhatsApp</a><a class="ic c" href="tel:+91${p}">Call</a>` : '';
    const missMsg = nm => `Namaste, SN Mishra Singapore International School se. ${nm} ka Half-Yearly result aur PTM aaj tha. Aap nahi aa paaye — kripya school aakar class teacher se mil lijiye. Dhanyavaad.`;
    app(`<div class="grid"><div><b>${V.length}</b><small>Parents aaye<br>(${n} bachchon mein se)</small></div><div><b>${rs(paid)}</b><small>Fee aaj jama</small></div><div><b>${prom.length}</b><small>Fee ki tareekh di</small></div><div><b>${cmp.length}</b><small>Shikayatein</small></div></div>
      <h3>Parents ki khushi</h3><div class="grid">${MOODS.map(([m, e, t], i) => `<div><b>${e} ${mood[i]}</b><small>${t}${rated ? ' · ' + Math.round(mood[i] * 100 / rated) + '%' : ''}</small></div>`).join('')}</div>
      <h3>Class-wise</h3><table class="t"><tr><th>Class</th><th>Aaye</th><th>Nahi aaye</th></tr>${CLASSES.filter(c => R.classes[c]).map(c => { const x = R.classes[c]; return `<tr><td>${c}</td><td><b>${x.came || 0}</b> / ${x.n}</td><td>${(x.not || []).length}</td></tr>`; }).join('')}</table>
      ${prom.length ? `<h3>Fee ki tareekh (promise)</h3><table class="t"><tr><th>Bachcha</th><th>Tareekh</th><th>Kitna</th></tr>${prom.sort((a, b) => a.promise.localeCompare(b.promise)).map(v => `<tr><td>${esc(v.name)} <small>(${esc(v.cls)})</small></td><td>${esc(v.promise.split('-').reverse().join('-'))}</td><td>${v.pamt ? rs(v.pamt) : '—'}</td></tr>`).join('')}</table>` : ''}
      ${V.filter(v => v.note).length ? `<h3>Feedback / sujhav</h3>${V.filter(v => v.note).map(v => `<div class="cm done"><small>${esc(v.at)} · ${esc(v.name)} (${esc(v.cls)}) · ${esc(v.parent || '')} ${(MOODS.find(m => m[0] === v.mood) || [])[1] || ''}</small><p>${esc(v.note)}</p></div>`).join('')}` : ''}
      <h3>Jo parents nahi aaye (kal call ke liye)</h3>${CLASSES.filter(c => R.classes[c] && (R.classes[c].not || []).length).map(c => `<details><summary style="padding:6px 0;font-weight:600;color:#14294A">Class ${c} — ${(R.classes[c].not || []).length}</summary>${R.classes[c].not.map(s => `<div class="kid" style="cursor:default"><div class="n"><b>${esc(s.name)}</b><small>${esc(s.father || '')}</small></div>${wa(s.phone, missMsg(s.name))}</div>`).join('')}</details>`).join('') || '<p class="msg">Sab parents aa gaye 🎉</p>'}
      <p style="margin-top:14px"><button class="btn l" id="rprint">🖨 Print report</button></p>`);
    $('rprint').onclick = () => window.print();
  }
  // ---------- complaints ----------
  async function complaints() {
    app('<div class="msg">Shikayatein khul rahi hain…</div>');
    let R; try { R = await loadRep(); } catch (e) { return app(`<div class="msg">${esc(e.message)}</div>`); }
    const C = R.complaints, open = C.filter(v => v.cmp.status !== 'Resolved');
    app(`<p style="margin:0 0 8px;color:#5a6b80"><b>${open.length}</b> shikayat baaki · ${C.length - open.length} aaj theek hui. Purani baaki shikayatein bhi yahan dikhti hain.</p>` + (C.map(v => `<div class="cm ${v.cmp.status === 'Resolved' ? 'done' : ''}"><small>${esc((v.date || '').split('-').reverse().join('-'))} ${esc(v.at || '')} · <b>${esc(v.name)}</b> (${esc(v.cls)}) · ${esc(v.parent || '')} ${v.phone ? `<a class="ic c" href="tel:+91${esc(v.phone)}">Call</a>` : ''}</small>
      <p><span class="pill p-c">${esc(v.cmp.cat || 'Other')}</span> ${esc(v.cmp.text)}</p>${v.cmp.due ? `<small>Kab tak: ${esc(v.cmp.due.split('-').reverse().join('-'))}</small>` : ''}
      ${v.cmp.status === 'Resolved' ? `<p>✅ <b>Theek:</b> ${esc(v.cmp.action || '')} <small>— ${esc(v.cmp.doneBy || '')}</small></p>` : R.canResolve ? `<div class="row2" style="margin-top:6px"><input class="i" placeholder="Kya kiya (action)" data-act="${esc(v.date + '|' + v.sid)}"><button class="btn g s" data-res="${esc(v.date + '|' + v.sid)}">✓ Theek ho gayi</button></div>` : ''}</div>`).join('') || '<div class="msg">Koi shikayat nahi 🎉</div>'));
    document.querySelectorAll('[data-res]').forEach(b => { b.onclick = async () => { const key = b.dataset.res, act = document.querySelector(`[data-act="${CSS.escape(key)}"]`).value;
      if (!act.trim()) return alert('Kya kiya, ek line likh dijiye.'); b.disabled = true;
      try { await api('ptm_api.php?a=resolve', { key, status: 'Resolved', action: act }); REP = null; toast('✓ Shikayat band'); complaints(); } catch (e) { alert(e.message); b.disabled = false; } }; });
  }
  function go() { document.querySelectorAll('[data-t]').forEach(b => b.classList.toggle('on', b.dataset.t === TAB)); if (TAB === 'desk') desk(); else if (TAB === 'report') report(); else complaints(); }
  document.querySelectorAll('[data-t]').forEach(b => { b.onclick = () => { TAB = b.dataset.t; if (TAB !== 'desk') REP = null; go(); }; });
  { const a = document.querySelector('.top a'); if (/teacher\.php|chairman\.php/.test(document.referrer)) { a.textContent = '← App'; a.href = document.referrer; } }
  $('dt').value = DATE; $('dt').max = today(); $('dt').onchange = e => { DATE = e.target.value || today(); DATA = {}; REP = null; SEL = null; go(); };
  if (/report/.test(location.hash)) TAB = 'report'; if (/cmp/.test(location.hash)) TAB = 'cmp';
  go();
})();
