/* Staff attendance — all staff, one month (staffatt_api.php). Tap a box: Present → Absent → Half day → Present.
   Tap a date at the top: whole day Present / Absent. "Download CSV" gives the month in a sheet; fill P / A / H and
   "Upload CSV" saves it (matched by ID, else by name). Approved teacher leave (L) is shown, not changed here. */
(function () {
  const $ = id => document.getElementById(id), tok = () => localStorage.getItem(/[?&]tk=sal/.test(location.search) ? 'sal_token' : 'sch_token') || '';   // tk=sal: inside the Salary app (own login)
  const esc = s => String(s == null ? '' : s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
  const emb = /[?&]embed=1/.test(location.search); if (emb) document.body.classList.add('emb');
  const now = new Date(), ymOf = d => d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0');
  let YM = ymOf(now), D = null, CH = {};
  const toast = t => { const e = $('toast'); e.textContent = t; e.style.display = 'block'; clearTimeout(toast.t); toast.t = setTimeout(() => e.style.display = 'none', 3200); };
  async function api(path, body) {
    const r = await fetch('staffatt_api.php?' + path, { method: body ? 'POST' : 'GET', headers: { 'Content-Type': 'application/json', 'X-School-Token': tok() }, ...(body ? { body: JSON.stringify(body) } : {}) });
    let j = null; try { j = await r.json(); } catch (e) {}
    if (!r.ok || !j || !j.ok) throw Error((j && j.error) || (r.status === 401 ? 'Please log in to the school ERP first.' : 'Server problem. Please try again.'));
    return j;
  }
  const val = (id, d) => (CH[d] && CH[d][id]) || (D.att[id] && D.att[id][d]) || 'P';
  const nChanges = () => Object.values(CH).reduce((a, r) => a + Object.keys(r).length, 0);
  async function load() {
    $('app').innerHTML = '<div class="msg">Opening…</div>';
    try { D = await api('a=month&ym=' + YM); CH = {}; draw(); } catch (e) { $('app').innerHTML = `<div class="msg">${esc(e.message)}</div>`; }
  }
  function months() { const o = []; for (let i = 0; i < 6; i++) { const d = new Date(now.getFullYear(), now.getMonth() - i, 1); o.push(ymOf(d)); } return o; }
  const mName = ym => new Date(ym + '-01T00:00:00').toLocaleDateString('en-IN', { month: 'long', year: 'numeric' });
  function draw() {
    const can = D.can, n = nChanges(), work = D.days.filter(d => d.kind === 'work' && !d.future);
    const groups = [['Teachers', D.staff.filter(s => s.teaching && !s.until)], ['Other staff', D.staff.filter(s => !s.teaching && !s.until)], ['Left the school (worked till the date shown)', D.staff.filter(s => s.until)]];
    const head = D.days.map(d => `<th class="${d.kind}" title="${esc(d.title)}" ${can && d.kind === 'work' && !d.future ? `onclick="saDay('${d.date}')"` : ''}>${+d.date.slice(8)}<small>${'SMTWTFS'[new Date(d.date + 'T00:00:00').getDay()]}</small></th>`).join('');
    const row = s => `<tr><td class="nm"><b>${esc(s.name)}</b><small>${esc(s.role)}${s.until ? ' · till ' + esc(s.until.split('-').reverse().join('-')) : ''}</small></td>${D.days.map(d => {
        if (s.until && d.date > s.until) return '<td class="c off">–</td>';
        if (d.kind !== 'work') return `<td class="c off">${d.kind === 'sun' ? 'S' : 'H'}</td>`.replace('>H<', '>·<');
        if (d.future) return '<td class="c fut"></td>';
        const v = val(s.id, d.date), ch = CH[d.date] && CH[d.date][s.id];
        return `<td class="c ${v}${ch ? ' ch' : ''}" ${can && v !== 'L' ? `onclick="saTap('${s.id}','${d.date}')"` : ''}>${v === 'P' ? '✓' : v === 'H' ? '½' : v}</td>`; }).join('')}
        <td class="tot ${work.some(d => ['A'].includes(val(s.id, d.date))) ? 'bad' : ''}">${(() => { let a = 0; work.forEach(d => { if (s.until && d.date > s.until) return; const v = val(s.id, d.date); a += v === 'A' || v === 'L' ? 1 : v === 'H' ? .5 : 0; }); return a ? a + ' off' : '✓'; })()}</td></tr>`;
    $('app').innerHTML = `<div class="bar"><select onchange="saMonth(this.value)">${months().map(m => `<option value="${m}" ${m === YM ? 'selected' : ''}>${mName(m)}</option>`).join('')}</select>
        ${can ? `<button class="go" ${n ? '' : 'disabled'} onclick="saSave()">Save ${n ? n + ' change' + (n > 1 ? 's' : '') : ''}</button>` : ''}
        <button onclick="saCsv()">Download CSV</button>${can ? '<label class="btn">Upload CSV<input type="file" accept=".csv,text/csv" onchange="saUpload(this)"></label>' : ''}
        ${n ? '<button onclick="saUndo()">Undo all</button>' : ''}</div>
      <div class="leg"><span><i style="color:#1d8f4e">✓</i>Present</span><span><i style="background:#FDE2E1;color:#b42318">A</i>Absent</span><span><i style="background:#FFF1CC;color:#8a5a00">½</i>Half day</span><span><i style="background:#E3EEFF;color:#1d4ed8">L</i>Approved leave</span><span><i style="background:#f2f4f6;color:#b8c0c8">S</i>Sunday / holiday</span></div>
      <div class="wrap"><table><thead><tr><th class="nm">${esc(mName(YM))} · ${work.length} days so far</th>${head}<th>Total</th></tr></thead><tbody>
      ${groups.filter(g => g[1].length).map(([g, L]) => `<tr class="grp"><td class="nm" colspan="1">${g} (${L.length})</td><td colspan="${D.days.length + 1}"></td></tr>` + L.map(row).join('')).join('')}</tbody></table></div>
      <p class="help">${can ? 'Tap a box: ✓ Present → A Absent → ½ Half day. Tap a date at the top to mark everyone present or absent that day. Press <b>Save</b> once at the end. ' : ''}Teachers' attendance here is the same as Staff Attendance in the ERP and Teacher App — the arrangement and salary use it by themselves. Approved leave (L) comes from the leave requests. CSV: download, fill P / A / H under each date, upload.</p>`;
  }
  window.saMonth = ym => { if (nChanges() && !confirm('Leave without saving your changes?')) return draw(); YM = ym; load(); };
  window.saTap = (id, d) => { const cur = val(id, d), nx = { P: 'A', A: 'H', H: 'P' }[cur] || 'P', orig = (D.att[id] && D.att[id][d]) || 'P';
    CH[d] = CH[d] || {}; if (nx === orig) delete CH[d][id]; else CH[d][id] = nx; draw(); };
  window.saDay = d => { const allP = D.staff.filter(s => !(s.until && d > s.until)).every(s => ['P', 'L'].includes(val(s.id, d))), to = allP ? 'A' : 'P';
    if (!confirm((to === 'A' ? 'Mark EVERYONE absent' : 'Mark everyone present') + ' on ' + d.split('-').reverse().join('-') + '?')) return;
    D.staff.forEach(s => { if (val(s.id, d) === 'L' || (s.until && d > s.until)) return; const orig = (D.att[s.id] && D.att[s.id][d]) || 'P'; CH[d] = CH[d] || {}; if (to === orig) delete CH[d][s.id]; else CH[d][s.id] = to; }); draw(); };
  window.saUndo = () => { CH = {}; draw(); };
  window.saSave = async () => {
    try { const r = await api('a=save', { ym: YM, set: CH }); toast(`Saved ${r.changed} entr${r.changed === 1 ? 'y' : 'ies'}` + (r.skipped ? ` · ${r.skipped} skipped` : '')); await load(); }
    catch (e) { toast(e.message); }
  };
  window.saCsv = () => {
    const days = D.days, lines = [['ID', 'Name', 'Role', ...days.map(d => d.date.slice(8))].join(',')];
    D.staff.forEach(s => lines.push([s.id, '"' + s.name.replace(/"/g, '""') + '"', '"' + s.role.replace(/"/g, '""') + '"', ...days.map(d => d.kind !== 'work' ? (d.kind === 'sun' ? 'S' : 'HOL') : d.future ? '' : val(s.id, d.date))].join(',')));
    const a = document.createElement('a'); a.href = URL.createObjectURL(new Blob(['﻿' + lines.join('\r\n')], { type: 'text/csv' })); a.download = 'staff-attendance-' + YM + '.csv'; a.click();
  };
  function parseCsv(t) { const rows = []; let row = [], f = '', q = false;
    for (let i = 0; i < t.length; i++) { const c = t[i];
      if (q) { if (c === '"' && t[i + 1] === '"') { f += '"'; i++; } else if (c === '"') q = false; else f += c; }
      else if (c === '"') q = true; else if (c === ',') { row.push(f); f = ''; } else if (c === '\n' || c === '\r') { if (c === '\r' && t[i + 1] === '\n') i++; row.push(f); rows.push(row); row = []; f = ''; } else f += c; }
    if (f || row.length) { row.push(f); rows.push(row); } return rows.filter(r => r.some(x => x.trim())); }
  window.saUpload = inp => {
    const file = inp.files && inp.files[0]; inp.value = ''; if (!file) return;
    const rd = new FileReader(); rd.onload = () => {
      const rows = parseCsv(String(rd.result).replace(/^﻿/, '')); if (rows.length < 2) return toast('The file is empty.');
      const H = rows[0].map(x => x.trim()), dayCol = {}; H.forEach((h, i) => { if (/^\d{1,2}$/.test(h)) dayCol[i] = YM + '-' + h.padStart(2, '0'); });
      const iId = H.findIndex(h => /^id$/i.test(h)), iNm = H.findIndex(h => /^name$/i.test(h));
      if (!Object.keys(dayCol).length || (iId < 0 && iNm < 0)) return toast('Use the downloaded format: ID, Name, Role, then 01, 02, 03 … as the first row.');
      const byName = {}; D.staff.forEach(s => byName[s.name.toLowerCase().replace(/\s+/g, ' ').trim()] = s.id);
      const work = new Set(D.days.filter(d => d.kind === 'work' && !d.future).map(d => d.date)); let n = 0; const miss = [];
      rows.slice(1).forEach(r => {
        let id = iId >= 0 ? r[iId].trim() : ''; if (!D.staff.some(s => s.id === id)) id = byName[(iNm >= 0 ? r[iNm] : '').toLowerCase().replace(/\s+/g, ' ').trim()] || '';
        if (!id) { if ((r[iNm] || r[iId] || '').trim()) miss.push((r[iNm] || r[iId]).trim()); return; }
        Object.entries(dayCol).forEach(([i, d]) => { const v = (r[i] || '').trim().toUpperCase().replace('½', 'H'); const sx = D.staff.find(x => x.id === id); if (!work.has(d) || !['P', 'A', 'H'].includes(v) || val(id, d) === 'L' || (sx.until && d > sx.until)) return;
          const orig = (D.att[id] && D.att[id][d]) || 'P'; CH[d] = CH[d] || {}; if (v === orig) delete CH[d][id]; else { CH[d][id] = v; n++; } });
      });
      draw(); toast(`${n} change${n === 1 ? '' : 's'} read from the file — check and press Save.` + (miss.length ? ` Not found: ${miss.slice(0, 3).join(', ')}${miss.length > 3 ? '…' : ''}` : ''));
    }; rd.readAsText(file);
  };
  window.addEventListener('beforeunload', e => { if (D && nChanges()) { e.preventDefault(); e.returnValue = ''; } });
  load();
})();
