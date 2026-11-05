<?php
// Old ERP student attendance import (owner, 5 Nov 2026) — Principal page. Logic + rules: oldatt_api.php.
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; connect-src 'self'; frame-ancestors 'self'; base-uri 'none'; object-src 'none'");
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow">
<title>Old ERP Attendance Import · SNMSIS</title>
<style>
:root{--ink:#14213D;--mut:#5B6782;--line:#E3E8F1;--bg:#F3F5FA;--a:#17324D;--ok:#047857;--okbg:#E6F6EE;--bad:#B91C1C;--badbg:#FDECEC;--warn:#92400E;--warnbg:#FFF4E5}
*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--ink);font:14px/1.5 system-ui,-apple-system,'Segoe UI',Arial,sans-serif}
.w{max-width:860px;margin:0 auto;padding:16px}h1{font-size:20px;margin:6px 0 4px}p{margin:6px 0}.mut{color:var(--mut);font-size:13px}
.card{background:#fff;border:1px solid var(--line);border-radius:14px;padding:14px 16px;margin:12px 0}
.btn{border:0;border-radius:10px;padding:10px 16px;font:inherit;font-weight:600;cursor:pointer;background:var(--a);color:#fff}.btn[disabled]{opacity:.45;cursor:default}
.btn.go{background:#F2A93B;color:#14213D}input[type=file]{font:inherit;max-width:100%}
table{width:100%;border-collapse:collapse;font-size:13px}th,td{padding:7px 6px;border-bottom:1px solid var(--line);text-align:left;vertical-align:top}th{font-size:12px;color:var(--mut)}
td.n{text-align:right;white-space:nowrap}.pill{display:inline-block;border-radius:999px;padding:1px 8px;font-size:12px;font-weight:600}
.ok{background:var(--okbg);color:var(--ok)}.bad{background:var(--badbg);color:var(--bad)}.warn{background:var(--warnbg);color:var(--warn)}
.ban{border-radius:12px;padding:10px 12px;margin:10px 0}.small{font-size:12px;color:var(--mut)}
</style></head><body><div class="w">
<h1>Old ERP Attendance Import</h1>
<p class="mut">Student attendance from the old ERP (schoolerpindia.net) into the new ERP and the Teacher App. Days a teacher already marked in the new ERP are never changed. You can run it again — nothing is added twice.</p>
<div id="me"></div>
<div class="card"><b>1 · Choose the file</b><p class="mut">The file made from the old ERP's Attendance Register (old-erp-attendance-….json).</p><input type="file" id="f" accept=".json,application/json"></div>
<div id="out"></div>
<script>
'use strict';
const $ = id => document.getElementById(id), esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
const tok = (() => { try { return localStorage.getItem('sch_token') || ''; } catch (e) { return ''; } })();
let FILE = null, PREV = {}, BUSY = false;
if (!tok) $('me').innerHTML = '<div class="ban bad">Please log in to the School ERP as <b>Principal</b> first (maakit.in/school), then open this page again.</div>';
async function call(a, body) {
  const r = await fetch('oldatt_api.php?a=' + a, { method: 'POST', cache: 'no-store', headers: { 'Content-Type': 'application/json', 'X-School-Token': tok }, body: JSON.stringify(body) });
  let j = null; try { j = await r.json(); } catch (e) {}
  if (!r.ok || !j || !j.ok) throw new Error((j && j.error) || 'Server problem (' + r.status + '). Please try again.');
  return j;
}
$('f').onchange = async e => {
  const file = e.target.files[0]; if (!file) return;
  try { FILE = JSON.parse(await file.text()); } catch (er) { $('out').innerHTML = '<div class="ban bad">This is not the right file.</div>'; return; }
  if (!FILE || !FILE.classes || FILE.source !== 'schoolerpindia.net') { $('out').innerHTML = '<div class="ban bad">This is not an old ERP attendance file.</div>'; FILE = null; return; }
  PREV = {}; draw(); await run('preview');
};
async function run(a) {
  if (BUSY || !FILE) return; BUSY = true; draw();
  for (const [cls, c] of Object.entries(FILE.classes)) {
    if (!c || !c.kids || !c.kids.length) continue;
    PREV[cls] = Object.assign({}, PREV[cls] || {}, { busy: true }); draw();
    try { PREV[cls] = await call(a, { cls, days: c.days, kids: c.kids }); } catch (er) { PREV[cls] = { err: er.message }; }
    draw();
  }
  BUSY = false; draw();
}
function draw() {
  if (!FILE) return;
  const rows = Object.keys(FILE.classes).filter(k => FILE.classes[k] && FILE.classes[k].kids && FILE.classes[k].kids.length);
  const all = rows.map(k => PREV[k]).filter(p => p && !p.busy && !p.err && !p.skipped);
  const add = all.reduce((n, p) => n + (p.add || 0) + (p.update || 0), 0), done = all.length && all.every(p => p.applied), anyUn = all.some(p => (p.unmatched || []).length);
  $('out').innerHTML = `<div class="card"><b>2 · Check</b> <span class="small">${esc(FILE.from)} to ${esc(FILE.to)} · read from the old ERP on ${esc(FILE.made || '')}</span>
    <table><tr><th>Class</th><th class="n">Children old / new / matched</th><th class="n">Days to add</th><th class="n">Already in new ERP</th><th>Not found</th></tr>
    ${rows.map(k => { const p = PREV[k] || {}; return `<tr><td><b>${esc(k)}</b></td>${p.busy ? '<td colspan="4" class="small">checking…</td>' : p.err ? `<td colspan="4"><span class="pill bad">${esc(p.err)}</span></td>` : p.skipped ? `<td colspan="4" class="small">${esc(p.skipped)}</td>` : p.cls ? `
      <td class="n">${p.oldKids} / ${p.newKids} / <b>${p.matched}</b></td><td class="n"><b>${p.add}</b>${p.update ? ` <span class="pill warn">+${p.update} refresh</span>` : ''}${p.applied ? ' <span class="pill ok">✓ done</span>' : ''}</td><td class="n">${p.kept}</td>
      <td class="small">${(p.unmatched || []).length ? esc(p.unmatched.join(', ')) : '—'}</td>` : '<td colspan="4" class="small">waiting</td>'}</tr>`; }).join('')}</table>
    ${anyUn ? '<p class="small">“Not found” children are in the old ERP but not in this class of the new ERP (check Student Master). Their absent days are not added; everything else is.</p>' : ''}</div>
    <div class="card"><b>3 · Apply</b><p class="mut">${done ? '<span class="pill ok">Done.</span> Teachers see it after they reopen the Teacher App.' : `Adds <b>${add}</b> class-day(s) of attendance. Days already marked in the new ERP stay as they are.`}</p>
    <button class="btn go" ${BUSY || !add || done ? 'disabled' : ''} onclick="if(confirm('Add old ERP attendance now?'))run('apply')">${BUSY ? 'Working…' : 'Apply'}</button></div>`;
}
</script></div></body></html>
