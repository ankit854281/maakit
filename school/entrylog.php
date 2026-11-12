<?php
// Who entered what — every school record with who saved it and when (entrylog_api.php). Principal / Chairman.
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; connect-src 'self'; frame-ancestors 'self'; base-uri 'none'; object-src 'none'");
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Who entered what · SN Mishra SIS</title>
<style>
*{box-sizing:border-box}body{margin:0;font-family:Arial,Helvetica,sans-serif;font-size:14px;color:#17202a;background:#EEF2F5}
.top{background:#17324D;color:#fff;padding:12px 14px;display:flex;justify-content:space-between;align-items:center}.top b{font-size:17px}.top small{display:block;opacity:.8;font-size:12px}.top a{color:#fff}
.pg{padding:12px;max-width:1100px;margin:0 auto}.card{background:#fff;border-radius:16px;padding:12px 14px;margin-bottom:12px;box-shadow:0 1px 3px rgba(0,0,0,.06)}
.chips{display:flex;flex-wrap:wrap;gap:6px}.chips button{border:1px solid #cfd6de;background:#fff;border-radius:99px;padding:6px 11px;font:inherit;font-size:13px;cursor:pointer}.chips button.on{background:#17324D;color:#fff;border-color:#17324D}.chips button em{font-style:normal;opacity:.7;margin-left:4px}
select{font:inherit;border:1px solid #c7d0da;border-radius:10px;padding:7px}
table{width:100%;border-collapse:collapse;font-size:13px}th{background:#17324D;color:#fff;text-align:left;padding:7px;font-weight:normal;position:sticky;top:0}td{border-bottom:1px solid #eef1f4;padding:7px;vertical-align:top}
td.k{white-space:nowrap;color:#17324D;font-weight:bold}td.t{white-space:nowrap;color:#667}tr.rm td{color:#b42318}.mut{color:#667;font-size:12px}.tw{overflow:auto;max-height:70vh}
</style></head><body>
<div class="top"><div><b>Who entered what</b><small>Every record shows who saved it last and when</small></div><a href="index.php">← ERP</a></div>
<div class="pg" id="app"><div class="card">Opening…</div></div>
<script>
(function () {
  const esc = s => String(s == null ? '' : s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
  let DAYS = 1, WHO = '', KIND = '', D = null;
  async function load() {
    document.getElementById('app').innerHTML = '<div class="card">Opening…</div>';
    try { const r = await fetch(`entrylog_api.php?a=list&days=${DAYS}&who=${encodeURIComponent(WHO)}&kind=${encodeURIComponent(KIND)}`, { headers: { 'X-School-Token': localStorage.getItem('sch_token') || '' } });
      D = await r.json(); if (!r.ok || !D.ok) throw Error(D.error || (r.status === 401 ? 'Please log in to the ERP first.' : 'Could not open.')); draw(); }
    catch (e) { document.getElementById('app').innerHTML = `<div class="card">${esc(e.message)}</div>`; }
  }
  const when = s => { const d = new Date(s.replace(' ', 'T')); return d.toLocaleDateString('en-IN', { day: '2-digit', month: 'short' }) + ' ' + d.toLocaleTimeString('en-IN', { hour: '2-digit', minute: '2-digit' }); };
  function draw() {
    document.getElementById('app').innerHTML = `<div class="card"><div class="chips">${[[1, 'Today'], [2, 'Today & yesterday'], [7, 'Last 7 days'], [30, 'Last 30 days']].map(([d, l]) => `<button class="${DAYS === d ? 'on' : ''}" onclick="elDays(${d})">${l}</button>`).join('')}</div></div>
      <div class="card"><b>People</b> <span class="mut">— tap a name to see only their entries</span><div class="chips" style="margin-top:8px"><button class="${WHO ? '' : 'on'}" onclick="elWho('')">Everyone</button>${D.people.map(p => `<button class="${WHO === p.uid ? 'on' : ''}" onclick="elWho('${esc(p.uid)}')" title="${esc(Object.entries(p.kinds).map(([k, n]) => k + ': ' + n).join(', '))}">${esc(p.name)}<em>${p.n}</em></button>`).join('')}</div>
        <div style="margin-top:10px"><select onchange="elKind(this.value)"><option value="">All kinds of data</option>${D.kinds.sort().map(k => `<option ${k === KIND ? 'selected' : ''}>${esc(k)}</option>`).join('')}</select></div></div>
      <div class="card"><div class="tw"><table><tr><th>When</th><th>Who</th><th>What</th><th>Detail</th></tr>${D.rows.map(r => `<tr class="${r.removed ? 'rm' : ''}"><td class="t">${esc(when(r.at))}</td><td>${esc(r.who)}</td><td class="k">${esc(r.kind)}${r.removed ? ' (removed)' : ''}</td><td>${esc(r.what)}</td></tr>`).join('') || '<tr><td colspan="4" class="mut">Nothing in this period.</td></tr>'}</table></div>
      <p class="mut">Shows the LAST person who saved each record. The full history of every change is in ERP → Activity Log.${D.rows.length >= 600 ? ' Only the newest 600 are shown — choose a person or a kind to narrow it.' : ''}</p></div>`;
  }
  window.elDays = d => { DAYS = d; load(); }; window.elWho = w => { WHO = w; load(); }; window.elKind = k => { KIND = k; load(); };
  load();
})();
</script></body></html>
