/* Data Entry page (dataentry.php) — non-teaching data-entry staff (role 'dataentry') and the Principal.
   Tabs: Student attendance (classes not marked by the class teacher, any day in the last 120 days) ·
   Staff attendance (staffatt.php) · Homework (all classes of a day, ready WhatsApp text per class). */
(function () {
  const $ = id => document.getElementById(id), store = { get: k => localStorage.getItem(k), set: (k, v) => v == null ? localStorage.removeItem(k) : localStorage.setItem(k, v) };
  const esc = s => String(s == null ? '' : s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
  const pad = n => String(n).padStart(2, '0'), iso = d => d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()), today = iso(new Date());
  const fmt = d => new Date(d + 'T00:00:00').toLocaleDateString('en-IN', { weekday: 'short', day: '2-digit', month: 'short', year: 'numeric' });
  const toast = t => { const e = $('toast'); e.textContent = t; e.style.display = 'block'; clearTimeout(toast.t); toast.t = setTimeout(() => e.style.display = 'none', 3500); };
  let TAB = 'att', DATE = today, DAY = null, CLS = null, ST = {}, HW = null, ME = '', NAMES = {}, MUST = false;
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
        <p class="mut f">First time: PIN 1234 — then choose your own. Forgot it? Ask the Principal to reset it (ERP → Settings → Logins).</p>`}</div>`;
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
      <div class="tabs">${[['att', 'Student attendance'], ['staff', 'Staff attendance'], ['hw', 'Homework']].map(([k, l]) => `<button class="${TAB === k ? 'on' : ''}" onclick="deTab('${k}')">${l}</button>`).join('')}</div><div class="pg">${body}</div>`;
  }
  window.deTab = k => { TAB = k; CLS = null; draw(); };
  window.deDate = v => { if (!v || v > today) return toast('Choose a past date.'); DATE = v; DAY = null; HW = null; CLS = null; draw(); };
  const dateBox = () => `<div class="card row"><label class="mut" style="white-space:nowrap">Date</label><input type="date" value="${DATE}" max="${today}" onchange="deDate(this.value)"></div>`;
  async function draw() {
    if (!store.get('sch_token') || MUST) return loginView();
    if (TAB === 'staff') return frame(`<iframe src="staffatt.php?embed=1" title="Staff attendance"></iframe><p class="mut">Tap boxes for teachers and other staff, then press Save. CSV download / upload for many days.</p>`);
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
    if (!CLS) {
      const not = DAY.classes.filter(c => !c.att && !c.holiday);
      return frame(dateBox() + `<div class="card"><div class="row"><h3>Classes</h3><span class="mut">${not.length ? not.length + ' not marked' : 'all marked ✓'}</span></div>
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
  window.deOpen = c => { CLS = c; ST = {}; if (c) { const x = DAY.classes.find(y => y.c === c); if (x.att) x.att.absent.forEach(id => ST[id] = (x.att.st && x.att.st[id]) === 'L' ? 'L' : 'A'); } draw(); };
  window.deSet = (id, k) => { if (k === 'P') delete ST[id]; else ST[id] = k; draw(); };
  window.deAll = k => { ST = {}; draw(); };
  window.deSave = async () => { try { await api('dataentry_api.php?a=att', { date: DATE, cls: CLS, st: ST }); toast('Attendance saved for Class ' + CLS + '.'); DAY = null; CLS = null; draw(); } catch (e) { toast(e.message); } };
  window.deCopy = t => { navigator.clipboard && navigator.clipboard.writeText(t).then(() => toast('Copied.'), () => toast('Copy did not work — select and copy.')); };
  draw();
})();
