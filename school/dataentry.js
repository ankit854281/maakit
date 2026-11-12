/* Data Entry page (dataentry.php) — non-teaching data-entry staff (role 'dataentry') and the Principal.
   Tabs: Student attendance (classes not marked by the class teacher, any day in the last 120 days) ·
   Staff attendance (staffatt.php) · Homework (all classes of a day, ready WhatsApp text per class) ·
   Diary & Kit (Diary / ID card / Dress / Books given — tick boxes per child, class-wise given / pending). */
(function () {
  const $ = id => document.getElementById(id), store = { get: k => localStorage.getItem(k), set: (k, v) => v == null ? localStorage.removeItem(k) : localStorage.setItem(k, v) };
  const esc = s => String(s == null ? '' : s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
  const pad = n => String(n).padStart(2, '0'), iso = d => d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()), today = iso(new Date());
  const fmt = d => new Date(d + 'T00:00:00').toLocaleDateString('en-IN', { weekday: 'short', day: '2-digit', month: 'short', year: 'numeric' });
  const toast = t => { const e = $('toast'); e.textContent = t; e.style.display = 'block'; clearTimeout(toast.t); toast.t = setTimeout(() => e.style.display = 'none', 3500); };
  let KIT = null, KC = null, KCH = {}, KF = '', TAB = 'att', SUB = 'day', MON = null, DATE = today, DAY = null, CLS = null, ST = {}, HW = null, ME = '', NAMES = {}, MUST = false;
  async function api(path, body) {
    const r = await fetch(path, { method: body ? 'POST' : 'GET', headers: { 'Content-Type': 'application/json', 'X-School-Token': store.get('sch_token') || '' }, ...(body ? { body: JSON.stringify(body) } : {}) });
    let j = null; try { j = await r.json(); } catch (e) {}
    if (r.status === 401) { store.set('sch_token', null); draw(); throw Error('Please log in.'); }
    if (r.status === 428) { MUST = true; loginView(); throw Error('Choose your own PIN first.'); }
    if (!r.ok || !j || !j.ok) throw Error((j && j.error) || 'Server problem. Please try again.');
    return j;
  }
  // ---------- login (own PIN; first PIN 1234, then choose your own) ----------
  async function loginView() {
    try { const s = await api('api.php?a=status'); NAMES = s.office || {}; } catch (e) {}
    $('app').innerHTML = `<div class="lg"><h2>Data Entry</h2><p class="mut">${esc(NAMES.dataentry || 'Data Entry')} · SN Mishra SIS</p>
      ${MUST ? `<div class="f"><label class="mut">Choose your own PIN (4–8 digits)</label><input id="nPin" type="password" inputmode="numeric" maxlength="8"></div><button class="btn go f" style="width:100%" onclick="deNewPin()">Save my PIN</button>`
        : `<div class="f"><label class="mut">PIN</label><input id="pin" type="password" inputmode="numeric" maxlength="8" onkeydown="if(event.key==='Enter')deLogin()"></div><button class="btn go f" style="width:100%" onclick="deLogin()">Open</button>
        <p class="mut f">First time: PIN 1234 — then choose your own. Forgot it? Ask the Principal to reset it (ERP → Settings → Logins).</p>${/; wv\)/.test(navigator.userAgent) ? '' : '<p class="mut f"><a href="https://github.com/ankit854281/maakit/releases/download/dataentry-android-v1/snmsis-dataentry.apk">⬇ Download the SNMSIS Data Entry app (Android)</a></p>'}`}</div>`;
  }
  window.deLogin = async () => {
    try { const r = await api('api.php?a=login', { role: 'dataentry', pin: $('pin').value.trim() }); store.set('sch_token', r.token); MUST = !!r.mustChange; if (MUST) { window._old = $('pin').value.trim(); return loginView(); } draw(); }
    catch (e) { toast(e.message); }
  };
  window.deNewPin = async () => { const n = $('nPin').value.trim(); if (!/^\d{4,8}$/.test(n) || n === '1234') return toast('PIN must be 4–8 digits and not 1234.');
    try { await api('api.php?a=setpin', { old: window._old || '1234', pin: n }); MUST = false; toast('PIN saved.'); draw(); } catch (e) { toast(e.message); } };
  window.deLogout = async () => { try { await api('api.php?a=logout', {}); } catch (e) {} store.set('sch_token', null); DAY = null; draw(); };
  // ---------- frame ----------
  function frame(body) {
    $('app').innerHTML = `<div class="top"><div><b>Data Entry</b><small>${esc(ME)} · ${esc(fmt(DATE))}</small></div><button onclick="deLogout()">Log out</button></div>
      <div class="tabs">${[['att', 'Student attendance'], ['staff', 'Staff attendance'], ['hw', 'Homework'], ['kit', 'Diary & Kit']].map(([k, l]) => `<button class="${TAB === k ? 'on' : ''}" onclick="deTab('${k}')">${l}</button>`).join('')}</div><div class="pg">${body}</div>`;
  }
  window.deTab = k => { if (TAB === 'kit' && k !== 'kit' && kitN() && !confirm('Leave without saving the ticks?')) return; TAB = k; CLS = null; KC = null; KCH = {}; if (k === 'kit') KIT = null; draw(); };
  window.deDate = v => { if (!v || v > today) return toast('Choose a past date.'); DATE = v; DAY = null; HW = null; CLS = null; if (MON && MON.ym !== v.slice(0, 7)) MON = null; draw(); };
  const dateBox = () => `<div class="card row"><label class="mut" style="white-space:nowrap">Date</label><input type="date" value="${DATE}" max="${today}" onchange="deDate(this.value)"></div>`;
  async function draw() {
    if (!store.get('sch_token') || MUST) return loginView();
    if (TAB === 'staff') return frame(`<iframe src="staffatt.php?embed=1" title="Staff attendance"></iframe><p class="mut">Tap boxes for teachers and other staff, then press Save. CSV download / upload for many days.</p>`);
    if (TAB === 'kit') return kitView();
    if (TAB === 'hw') {
      if (!HW) { frame(dateBox() + '<div class="card mut">Opening…</div>'); try { HW = await api('dataentry_api.php?a=homework&date=' + DATE); } catch (e) { return frame(dateBox() + `<div class="card">${esc(e.message)}</div>`); } }
      const by = {}; HW.items.forEach(i => (by[i.cls] = by[i.cls] || []).push(i));
      const msg = c => `*Homework — Class ${c}*\n${fmt(DATE)}\n\n` + by[c].map(i => `• ${i.subj}: ${i.hw}${i.hwDate ? ' (for ' + fmt(i.hwDate).slice(0, -5) + ')' : ''}`).join('\n') + `\n\n— SN Mishra Singapore International School`;
      return frame(dateBox() + (Object.keys(by).length ? Object.keys(by).map(c => `<div class="card"><div class="row"><h3>Class ${esc(c)}</h3><span class="mut">${by[c].length} subject${by[c].length > 1 ? 's' : ''}</span></div>
        ${by[c].map(i => `<div class="hw"><b>${esc(i.subj)}</b> <span class="mut">· ${esc(i.teacher)}</span><p>${esc(i.hw)}</p></div>`).join('')}
        <pre class="msg">${esc(msg(c))}</pre><div class="row"><button class="btn l s" onclick="deCopy(${JSON.stringify(msg(c)).replace(/"/g, '&quot;')})">Copy</button><a class="btn go s" style="text-decoration:none" target="_blank" href="https://wa.me/?text=${encodeURIComponent(msg(c))}">Share on WhatsApp</a></div></div>`).join('')
        : '<div class="card mut">No homework written in the teachers\' diaries for this day yet.</div>') + (HW.classes.filter(c => !by[c]).length ? `<div class="card"><h3>No homework yet</h3><p class="mut">${HW.classes.filter(c => !by[c]).map(esc).join(' · ')}</p></div>` : ''));
    }
    if (!DAY) { frame(dateBox() + '<div class="card mut">Opening…</div>'); try { DAY = await api('dataentry_api.php?a=day&date=' + DATE); ME = DAY.me; } catch (e) { return frame(dateBox() + `<div class="card">${esc(e.message)}</div>`); } }
    if (DAY.holiday) return frame(dateBox() + `<div class="card"><h3>${esc(DAY.holiday)}</h3><p class="mut">No attendance on this day.</p></div>`);
    if (!CLS && SUB === 'month') {
      const ym = DATE.slice(0, 7);
      if (!MON || MON.ym !== ym) { frame(subSeg() + dateBox() + '<div class="card mut">Opening…</div>'); try { MON = await api('dataentry_api.php?a=month&ym=' + ym); } catch (e) { return frame(subSeg() + dateBox() + `<div class="card">${esc(e.message)}</div>`); } }
      const W = MON.days.filter(d => !d.holiday && d.total), T = W.reduce((a, d) => ({ t: a.t + d.total, p: a.p + d.present }), { t: 0, p: 0 });
      return frame(subSeg() + dateBox() + `<div class="card"><div class="row"><h3>${esc(new Date(ym + '-01T00:00:00').toLocaleDateString('en-IN', { month: 'long', year: 'numeric' }))}</h3><span class="mut">${W.length} school days · ${T.t ? Math.round(T.p / T.t * 100) : 0}% present</span></div>
        <div style="overflow-x:auto"><table class="rep"><tr><th>Date</th><th>Classes marked</th><th>Total</th><th>Present</th><th>Absent</th><th>Leave</th><th>%</th></tr>
        ${MON.days.map(d => d.holiday ? `<tr class="hol"><td>${esc(fmt(d.date).slice(0, -6))}</td><td colspan="6">${esc(d.holiday)}</td></tr>`
          : `<tr onclick="deDate('${d.date}');deSub('day')" style="cursor:pointer"><td>${esc(fmt(d.date).slice(0, -6))}</td><td class="${d.marked < d.classes ? 'bad' : ''}">${d.marked}/${d.classes}</td><td>${d.total}</td><td>${d.present}</td><td class="${d.absent ? 'bad' : ''}">${d.absent}</td><td>${d.leave}</td><td><b>${d.total ? Math.round(d.present / d.total * 100) + '%' : '—'}</b></td></tr>`).join('')}</table></div>
        <p class="mut" style="margin:8px 0 0">Total / present count only the classes that were marked. Tap a day to see each class.</p></div>`);
    }
    if (!CLS) {
      const not = DAY.classes.filter(c => !c.att && !c.holiday);
      const C = DAY.classes.filter(c => !c.holiday), M = C.filter(c => c.att), cnt = c => { const L = c.att ? c.att.absent.filter(id => c.att.st && c.att.st[id] === 'L').length : 0; const A = c.att ? c.att.absent.length - L : 0; return { t: c.students.length, a: A, l: L, p: c.students.length - A - L }; };
      const S = M.reduce((x, c) => { const k = cnt(c); return { t: x.t + k.t, p: x.p + k.p, a: x.a + k.a, l: x.l + k.l }; }, { t: 0, p: 0, a: 0, l: 0 }), pc = (p, t) => t ? Math.round(p / t * 100) + '%' : '—';
      const repCard = `<div class="card"><div class="row"><h3>Day report · ${esc(fmt(DATE))}</h3><span class="mut">${M.length}/${C.length} classes marked</span></div>
        <div class="kpis"><div><b>${S.t}</b><span>Total</span></div><div class="g"><b>${S.p}</b><span>Present</span></div><div class="r"><b>${S.a}</b><span>Absent</span></div><div class="b"><b>${S.l}</b><span>Leave</span></div><div class="y"><b>${pc(S.p, S.t)}</b><span>Present %</span></div></div>
        <div style="overflow-x:auto"><table class="rep"><tr><th>Class</th><th>Total</th><th>Present</th><th>Absent</th><th>Leave</th><th>%</th><th>Marked by</th></tr>
        ${DAY.classes.map(c => { if (c.holiday) return `<tr class="hol"><td>${esc(c.c)}</td><td colspan="6">${esc(c.holiday)}</td></tr>`; if (!c.att) return `<tr onclick="deOpen('${esc(c.c)}')" style="cursor:pointer"><td><b>${esc(c.c)}</b></td><td>${c.students.length}</td><td colspan="4" class="bad">Not marked</td><td>${esc(c.ct)}</td></tr>`;
          const k = cnt(c); return `<tr onclick="deOpen('${esc(c.c)}')" style="cursor:pointer"><td><b>${esc(c.c)}</b></td><td>${k.t}</td><td>${k.p}</td><td class="${k.a ? 'bad' : ''}">${k.a}</td><td>${k.l}</td><td><b>${pc(k.p, k.t)}</b></td><td class="mut">${esc(c.att.by || '')}</td></tr>`; }).join('')}
        <tr class="tot"><td>Total</td><td>${S.t}</td><td>${S.p}</td><td>${S.a}</td><td>${S.l}</td><td>${pc(S.p, S.t)}</td><td></td></tr></table></div>
        <p class="mut" style="margin:8px 0 0">Totals count only the classes that are marked. Tap a class to mark or correct it.</p></div>`;
      return frame(subSeg() + dateBox() + repCard + `<div class="card"><div class="row"><h3>Classes</h3><span class="mut">${not.length ? not.length + ' not marked' : 'all marked ✓'}</span></div>
        <div class="cls">${DAY.classes.map(c => c.holiday ? `<button class="c-hol"><b>${esc(c.c)}</b><small>${esc(c.holiday)}</small></button>`
          : `<button class="${c.att ? 'c-ok' : 'c-no'}" onclick="deOpen('${esc(c.c)}')"><b>${esc(c.c)}</b><small>${c.att ? (c.students.length - c.att.absent.length) + '/' + c.students.length + ' present · ' + esc(c.att.by || '') : 'Not marked · ' + esc(c.ct || '')}</small></button>`).join('')}</div>
        <p class="mut" style="margin:10px 0 0">Red = the class teacher has not marked attendance. Tap it, mark, Save. Your name is saved with it.</p></div>`);
    }
    const c = DAY.classes.find(x => x.c === CLS), nA = Object.values(ST).filter(v => v === 'A').length, nL = Object.values(ST).filter(v => v === 'L').length;
    frame(dateBox() + `<div class="card"><div class="row"><h3>Class ${esc(c.c)}</h3><button class="btn l s" onclick="deOpen(null)">← Classes</button></div>
      <p class="mut" style="margin:0 0 6px">${c.students.length - nA - nL} present · ${nA} absent · ${nL} leave${c.att ? ' · last saved by ' + esc(c.att.by || '') : ''}</p>
      <div class="row" style="margin-bottom:6px"><button class="btn l s" onclick="deAll('P')">All present</button></div>
      ${c.students.map(s => `<div class="kid"><div><small>${esc(s.roll || '')}</small><b>${esc(s.name)}</b></div><div class="pal">${['P', 'A', 'L'].map(k => `<button class="${k} ${(ST[s.id] || 'P') === k ? 'on' : ''}" onclick="deSet('${s.id}','${k}')">${k}</button>`).join('')}</div></div>`).join('') || '<p class="mut">No children in this class.</p>'}
      <button class="btn go" style="width:100%;margin-top:12px" onclick="deSave()">Save attendance</button></div>`);
  }
  // ---------- Diary & Kit: Diary / ID card / Dress / Books given (dataentry_api.php ?a=kit) ----------
  const kitN = () => Object.values(KCH).reduce((a, r) => a + Object.keys(r).length, 0);
  const has = (s, k) => KCH[s.id] && k in KCH[s.id] ? KCH[s.id][k] : !!(s.kit && s.kit[k]);
  async function kitView() {
    if (!KIT) { frame('<div class="card mut">Opening…</div>'); try { KIT = await api('dataentry_api.php?a=kit'); ME = ME || KIT.me; } catch (e) { return frame(`<div class="card">${esc(e.message)}</div>`); } }
    const IT = Object.entries(KIT.items), cnt = (L, k) => L.filter(s => has(s, k)).length, all = KIT.classes.flatMap(c => c.students);
    const cell = (g, t) => `<td class="${g < t ? 'bad' : 'okc'}">${g}/${t}${g < t ? `<small> · ${t - g} left</small>` : ' ✓'}</td>`;
    if (!KC) return frame(`<div class="card"><div class="row"><h3>Diary · ID card · Dress · Books</h3><span class="mut">Session ${esc(KIT.session)}</span></div>
      <div class="kpis k4">${IT.map(([k, l]) => { const g = cnt(all, k); return `<div class="${g < all.length ? 'r' : 'g'}"><b>${g}/${all.length}</b><span>${esc(l)} given</span><span>${all.length - g} still to get</span></div>`; }).join('')}</div>
      <div style="overflow-x:auto"><table class="rep"><tr><th>Class</th><th>Children</th>${IT.map(([, l]) => `<th>${esc(l)}</th>`).join('')}</tr>
      ${KIT.classes.filter(c => c.students.length).map(c => `<tr onclick="deKit('${esc(c.c)}')" style="cursor:pointer"><td><b>${esc(c.c)}</b></td><td>${c.students.length}</td>${IT.map(([k]) => cell(cnt(c.students, k), c.students.length)).join('')}</tr>`).join('')}
      <tr class="tot"><td>Total</td><td>${all.length}</td>${IT.map(([k]) => `<td>${cnt(all, k)}/${all.length}</td>`).join('')}</tr></table></div>
      <p class="mut" style="margin:8px 0 0">Tap a class, tick what each child has got, press Save. The date and your name are kept with every tick.</p></div>`);
    const c = KIT.classes.find(x => x.c === KC), L = c.students.filter(s => !KF || !has(s, KF)), n = kitN();
    frame(`<div class="card"><div class="row"><h3>Class ${esc(c.c)}</h3><button class="btn l s" onclick="deKit(null)">← Classes</button></div>
      <div class="kpis k4">${IT.map(([k, l]) => { const g = cnt(c.students, k); return `<div class="${g < c.students.length ? 'r' : 'g'}"><b>${g}/${c.students.length}</b><span>${esc(l)}</span></div>`; }).join('')}</div>
      <div class="sub" style="flex-wrap:wrap"><button class="${KF ? '' : 'on'}" onclick="deKitF('')">All children</button>${IT.map(([k, l]) => `<button class="${KF === k ? 'on' : ''}" onclick="deKitF('${k}')">${esc(l)} not given</button>`).join('')}</div>
      <div style="overflow-x:auto"><table class="rep kit"><tr><th>Child</th>${IT.map(([k, l]) => `<th><button class="btn l s" onclick="deKitAll('${k}')" title="Tick all">${esc(l)}</button></th>`).join('')}</tr>
      ${L.map(s => `<tr><td><small class="mut">${esc(s.roll || '')}</small> <b>${esc(s.name)}</b></td>${IT.map(([k]) => { const on = has(s, k), w = s.kit && s.kit[k]; return `<td><label class="tick ${KCH[s.id] && k in KCH[s.id] ? 'ch' : ''}" title="${w ? esc('Given ' + w.on.split('-').reverse().join('-') + ' · ' + w.by) : 'Not given yet'}"><input type="checkbox" ${on ? 'checked' : ''} onchange="deKitT('${s.id}','${k}',this.checked)">${w && on ? `<small>${esc(w.on.slice(8) + '/' + w.on.slice(5, 7))}</small>` : ''}</label></td>`; }).join('')}</tr>`).join('') || `<tr><td colspan="5" class="mut">${KF ? 'Everyone has got it ✓' : 'No children in this class.'}</td></tr>`}</table></div>
      <button class="btn go" style="width:100%;margin-top:12px" ${n ? '' : 'disabled'} onclick="deKitSave()">Save${n ? ' ' + n + ' change' + (n > 1 ? 's' : '') : ''}</button>
      ${IT.map(([k, l]) => { const P = c.students.filter(s => !has(s, k)); return P.length ? `<p class="mut" style="margin:8px 0 0"><b>${esc(l)} not given (${P.length}):</b> ${P.map(s => esc(s.name)).join(', ')}</p>` : ''; }).join('')}</div>`);
  }
  window.deKit = c => { if (KC && c !== KC && kitN() && !confirm('Leave without saving the ticks?')) return; KC = c; KCH = {}; KF = ''; draw(); };
  window.deKitF = k => { KF = k; draw(); };
  window.deKitT = (id, k, v) => { const s = KIT.classes.find(x => x.c === KC).students.find(x => x.id === id), orig = !!(s.kit && s.kit[k]);
    KCH[id] = KCH[id] || {}; if (v === orig) delete KCH[id][k]; else KCH[id][k] = v; if (!Object.keys(KCH[id]).length) delete KCH[id]; draw(); };
  window.deKitAll = k => { const L = KIT.classes.find(x => x.c === KC).students, to = !L.every(s => has(s, k));
    L.forEach(s => { const orig = !!(s.kit && s.kit[k]); KCH[s.id] = KCH[s.id] || {}; if (to === orig) delete KCH[s.id][k]; else KCH[s.id][k] = to; if (!Object.keys(KCH[s.id]).length) delete KCH[s.id]; }); draw(); };
  window.deKitSave = async () => { try { const r = await api('dataentry_api.php?a=kitsave', { cls: KC, set: KCH }); toast(`Saved for Class ${KC} (${r.changed} change${r.changed === 1 ? '' : 's'}).`); KCH = {}; KIT = null; draw(); } catch (e) { toast(e.message); } };
  const subSeg = () => `<div class="sub">${[['day', 'Day'], ['month', 'Month (day by day)']].map(([k, l]) => `<button class="${SUB === k ? 'on' : ''}" onclick="deSub('${k}')">${l}</button>`).join('')}</div>`;
  window.deSub = k => { SUB = k; CLS = null; draw(); };
  window.deOpen = c => { CLS = c; ST = {}; if (c) { const x = DAY.classes.find(y => y.c === c); if (x.att) x.att.absent.forEach(id => ST[id] = (x.att.st && x.att.st[id]) === 'L' ? 'L' : 'A'); } draw(); };
  window.deSet = (id, k) => { if (k === 'P') delete ST[id]; else ST[id] = k; draw(); };
  window.deAll = k => { ST = {}; draw(); };
  window.deSave = async () => { try { await api('dataentry_api.php?a=att', { date: DATE, cls: CLS, st: ST }); toast('Attendance saved for Class ' + CLS + '.'); DAY = null; MON = null; CLS = null; draw(); } catch (e) { toast(e.message); } };
  window.deCopy = t => { navigator.clipboard && navigator.clipboard.writeText(t).then(() => toast('Copied.'), () => toast('Copy did not work — select and copy.')); };
  draw();
})();
