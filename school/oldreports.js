'use strict';
// Old ERP Reports — every list/report page of the old ERP, exactly its columns and rows (oldreports_api.php).
if (new URLSearchParams(location.search).get('look') === 'classic') document.documentElement.classList.add('classic');
const $ = id => document.getElementById(id);
const esc = v => String(v ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
const QS = new URLSearchParams(location.search);
let IX = null, CUR = null, ROWS = null, Q = '', SES = (QS.get('session') || '').replace(/^(20\d\d)-20(\d\d)$/, '$1-$2'), WANT = QS.get('page') || '', IMP = null, BUSY = false;
function toast(t) { const el = $('orToast'); el.textContent = t; clearTimeout(toast.t); toast.t = setTimeout(() => { el.textContent = ''; }, 7000); }
async function api(a, body, q) {
  const r = await fetch('oldreports_api.php?a=' + a + (q ? '&' + new URLSearchParams(q) : ''), { method: body ? 'POST' : 'GET', headers: { 'Content-Type': 'application/json', 'X-School-Token': localStorage.getItem('sch_token') || '' }, ...(body ? { body: JSON.stringify(body) } : {}) });
  let d; try { d = await r.json(); } catch (e) { throw Error(r.status === 413 ? 'This part is too big for the website.' : 'The server did not answer properly. Please try again.'); }
  if (!r.ok || !d.ok) throw Error(d.error || 'Could not complete this.');
  return d;
}
async function gunzip(b64) { const bin = Uint8Array.from(atob(b64), c => c.charCodeAt(0)); return await new Response(new Blob([bin]).stream().pipeThrough(new DecompressionStream('gzip'))).text(); }
async function boot() {
  try {
    IX = await api('index');
    if (WANT) { const ps = IX.pages.filter(p => p.page === WANT); const p = ps.find(x => x.session === SES) || ps[0]; if (p) return openPage(p.session, p.page); }
    render();
  } catch (e) { $('orRoot').innerHTML = `<div class="or"><h3>Old ERP Reports</h3><p>${esc(e.message)}</p></div>`; }
}
async function openPage(ses, page) {
  CUR = IX.pages.find(p => p.session === ses && p.page === page); ROWS = null; Q = ''; render();
  try { const d = await api('page', null, { ses, page }); ROWS = JSON.parse(await gunzip(d.page.h)); render(); } catch (e) { toast(e.message); }
}
function csv() {
  if (!ROWS) return;
  const txt = ROWS.map(r => r.map(c => '"' + String(c).replace(/"/g, '""') + '"').join(',')).join('\r\n');
  const a = document.createElement('a'); a.href = URL.createObjectURL(new Blob(['﻿' + txt], { type: 'text/csv' })); a.download = (CUR.title + ' ' + CUR.session).replace(/[^\w -]+/g, '') + '.csv'; a.click();
}
// Import: the file may be bigger than the website takes at once, so it is sent in small parts.
async function readImport(file) {
  try { const d = JSON.parse(await file.text()); if (d.kind !== 'oldReports' || !Array.isArray(d.pages)) throw Error('This is not an Old ERP Reports file.'); IMP = { pages: d.pages, done: 0 }; render(); } catch (e) { IMP = null; toast(e.message); render(); }
}
function parts(pages) { const out = []; let cur = [], size = 0; for (const p of pages) { const n = p.h.length + 300; if (cur.length && size + n > 700000) { out.push(cur); cur = []; size = 0; } cur.push(p); size += n; } if (cur.length) out.push(cur); return out; }
async function applyImport() {
  if (BUSY || !IMP) return; BUSY = true; let added = 0, same = 0;
  try {
    const ps = parts(IMP.pages);
    for (let i = 0; i < ps.length; i++) { IMP.done = i; render(); const r = await api('import', { pages: ps[i], apply: true }); added += r.toAdd; same += r.unchanged; }
    toast('Imported ' + added + ' pages, ' + same + ' already here.'); IMP = null; IX = await api('index');
  } catch (e) { toast(e.message + ' (pages before this part are saved; run the same file again to finish)'); }
  BUSY = false; render();
}
function render() {
  if (!IX) return;
  const sessions = [...new Set(IX.pages.map(p => p.session))].sort().reverse();
  if (!SES || !sessions.includes(SES)) SES = sessions[0] || '';
  const groups = {}; IX.pages.filter(p => p.session === SES).forEach(p => (groups[p.group] = groups[p.group] || []).push(p));
  const side = Object.keys(groups).sort().map(g => `<h4>${esc(g.slice(3))}</h4>` + groups[g].map(p => `<button class="${CUR && CUR.page === p.page && CUR.session === p.session ? 'on' : ''}" onclick="openPage('${esc(p.session)}','${esc(p.page)}')">${esc(p.title)} <small>${p.rows}</small></button>`).join('')).join('');
  let main = '';
  if (CUR) {
    const q = Q.trim().toLowerCase(), head = ROWS ? ROWS[0] : [], body = ROWS ? ROWS.slice(1).filter(r => !q || r.some(c => String(c).toLowerCase().includes(q))) : [];
    main = `<div class="or-head"><div><h3>${esc(CUR.title)}</h3><small>Old ERP page ${esc(CUR.page)} · session ${esc(CUR.session.replace(/^(20\d\d)-(\d\d)$/, '$1-20$2'))} · ${CUR.rows} rows, copied as the old ERP showed them</small></div>
      <div><input placeholder="Search" value="${esc(Q)}" oninput="Q=this.value;render()" id="orQ"> <button onclick="csv()">Excel (CSV)</button> <button onclick="window.print()">Print</button></div></div>
      ${!ROWS ? '<p>Opening…</p>' : `<div class="or-wrap"><table class="or-t"><thead><tr>${head.map(h => `<th>${esc(h)}</th>`).join('')}</tr></thead><tbody>${body.slice(0, 3000).map(r => `<tr>${head.map((_, i) => `<td>${esc(String(r[i] ?? '').replace(/^__id=.*/, ''))}</td>`).join('')}</tr>`).join('')}</tbody></table></div>${body.length > 3000 ? '<p>Showing 3,000 rows. Search or use Excel (CSV) for all.</p>' : ''}`}`;
  } else main = `<h3>Old ERP Reports</h3><p>Every list and report copied from the old ERP — staff, accounts, cash book, bank, enquiry, transport, store, masters and more — exactly as it was. Choose a session and a report on the left.</p>
    ${IX.isPrincipal ? `<div class="or-box"><b>Import from old ERP</b> — file <b>11-Old-ERP-Reports.json</b>. It is sent in small parts; a page already here is never changed.<br><input type="file" accept=".json" onchange="readImport(this.files[0])">
      ${IMP ? `<p>${IMP.pages.length} pages in the file. ${BUSY ? 'Sending part ' + (IMP.done + 1) + '…' : ''}</p><button onclick="applyImport()" ${BUSY ? 'disabled' : ''}>Import</button>` : ''}</div>` : ''}
    <p>${IX.pages.length} pages here.</p>`;
  $('orRoot').innerHTML = `<div class="or${CUR ? ' open' : ''}"><aside class="or-side"><label>Session <select onchange="SES=this.value;CUR=null;render()">${sessions.map(s => `<option ${s === SES ? 'selected' : ''} value="${esc(s)}">${esc(s.replace(/^(20\d\d)-(\d\d)$/, '$1-20$2'))}</option>`).join('')}</select></label>${CUR ? '<button class="back" onclick="CUR=null;render()">‹ All reports</button>' : ''}${side}</aside><main class="or-main">${main}</main></div>`;
  const s = $('orQ'); if (s && Q && document.activeElement !== s) { s.focus(); s.setSelectionRange(Q.length, Q.length); }
}
boot();
