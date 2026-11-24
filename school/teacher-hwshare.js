'use strict';
// Teacher App — Homework share (owner, 20 Nov 2026: "PG to 10 sab homework sharing to class group on WhatsApp — sabhi teacher
// classwork / homework fill karein, but vo class ke according bankar Rati Ma'am ke paas aaye taaki vo share kar payen").
// Teachers keep writing the diary as before. Rati Ma'am (settings.hwSharers; not saved → Rati, teacher id RT) gets one ready
// message per class, PG–10th — every subject's class work and homework — with "Share on WhatsApp" (then it is marked ✓ Shared)
// and Copy. Classes nobody has filled yet are listed. Data: teacher_api.php?a=hwall / hwshared (kind tchHwShare).
let HWSH = {}, HWSHB = false;
const hwFmt = d => new Date(d + 'T00:00:00').toLocaleDateString('en-IN', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
function hwMsg(c, L, date) {
  return `*${(S.settings && S.settings.school) || 'SN Mishra Singapore International School'}*\n*Class ${c} — Class work & Homework*\n${hwFmt(date)}\n\n` +
    L.map(i => `*${i.subj || 'Subject'}*${i.cw ? `\nClass work: ${i.cw}` : ''}${i.hw ? `\nHomework: ${i.hw}${i.hwDate && i.hwDate !== date ? ' (by ' + fmtDate(i.hwDate) + ')' : ''}` : ''}`).join('\n\n') +
    `\n\nKripya bachchon ka homework roz check karein. 🙏`;
}
async function hwLoad(date, force) {
  if (HWSH[date] && !force) return; if (HWSHB) return; HWSHB = true;
  try { HWSH[date] = await api('teacher_api.php?a=hwall&date=' + date); } catch (e) { HWSH[date] = { err: e.message }; }
  HWSHB = false; render();
}
function vHwShare() {
  const date = P.date || todayISO(), D = HWSH[date];
  const top = `<div class="card"><div class="row"><div><b>📤 Homework share · PG to 10th</b><small>One message per class with every subject's class work and homework — send it to the class WhatsApp group.</small></div></div>
    <div class="row" style="margin-top:8px"><input type="date" class="inp" value="${date}" max="${todayISO()}" onchange="go('hwshare',{date:this.value})"><button class="btn l s" onclick="hwLoad('${date}',true)">↻ Refresh</button></div></div>`;
  if (!D) { hwLoad(date); return top + '<div class="card">Opening…</div>'; }
  if (D.err) return top + `<div class="card">${esc(D.err)}</div>`;
  const by = {}; D.items.forEach(i => (by[i.cls] = by[i.cls] || []).push(i));
  const ready = D.classes.filter(c => by[c]), none = D.classes.filter(c => !by[c]), done = ready.filter(c => D.shared[c]);
  let h = top + `<div class="card"><div class="row"><b>${ready.length} class${ready.length === 1 ? '' : 'es'} ready · ${done.length} shared</b><small>${none.length ? none.length + ' not filled yet' : 'all classes filled'}</small></div></div>`;
  ready.forEach(c => { const L = by[c], m = hwMsg(c, L, date), sh = D.shared[c];
    h += `<div class="card hws${sh ? ' done' : ''}"><div class="row"><b>Class ${esc(c)}</b><small>${L.length} entr${L.length > 1 ? 'ies' : 'y'}${sh ? ' · ✓ Shared ' + new Date(sh.at).toLocaleTimeString('en-IN', { hour: 'numeric', minute: '2-digit' }) : ''}</small></div>
      ${L.map(i => `<div class="hwi"><b>${esc(i.subj)}</b> <small>· ${esc(i.teacher)}</small>${i.cw ? `<p><span>CW</span> ${esc(i.cw)}</p>` : ''}${i.hw ? `<p><span>HW</span> ${esc(i.hw)}</p>` : '<p><span>HW</span> <i>No homework</i></p>'}</div>`).join('')}
      <div class="btns"><a class="btn go" style="text-decoration:none" target="_blank" rel="noopener" href="https://wa.me/?text=${encodeURIComponent(m)}" onclick="hwMark('${date}','${esc(c)}',1)">Share on WhatsApp</a>
        <button class="btn l" onclick="hwCopy(${JSON.stringify(m).replace(/"/g, '&quot;')})">Copy</button>${sh ? `<button class="btn l s" onclick="hwMark('${date}','${esc(c)}',0)">Undo ✓</button>` : ''}</div></div>`; });
  if (none.length) h += `<div class="card"><b>Not filled yet</b><p class="mut" style="margin:6px 0 0">${none.map(esc).join(' · ')}</p><small>Teachers write class work / homework in Classroom → Diary; it comes here by itself.</small></div>`;
  return h;
}
async function hwMark(date, c, on) {
  try { const d = await api('teacher_api.php?a=hwshared', { date, cls: c, on: on ? 1 : 0 }); if (HWSH[date] && !HWSH[date].err) { if (d.shared) HWSH[date].shared[c] = d.shared; else delete HWSH[date].shared[c]; } setTimeout(render, 400); }
  catch (e) { toast(e.message); }
}
function hwCopy(t) { (navigator.clipboard ? navigator.clipboard.writeText(t) : Promise.reject()).then(() => toast('Copied — paste it in the class group.')).catch(() => toast('Copy did not work on this phone — use Share on WhatsApp.')); }
// Home card for Rati Ma'am
if (typeof vHome === 'function') {
  const _vhw = vHome;
  vHome = function () { const html = _vhw.apply(this, arguments);
    if (!S || !ME || (typeof pMode === 'function' && pMode()) || typeof isHwSharer !== 'function' || !isHwSharer()) return html;
    return `<div class="card lpb-home"><div class="row"><div><b>📤 Homework share — PG to 10th</b><small>Every class's class work + homework, ready to send to the class WhatsApp groups.</small></div><button class="btn go" onclick="go('hwshare')">Open</button></div></div>` + html; };
}
(function () { const st = document.createElement('style'); st.textContent = `.hws.done{border:2px solid #22C55E33;background:#F2FBF5}.hwi{padding:8px 0;border-top:1px solid #eef1f4}.hwi p{margin:4px 0 0;font-size:13.5px}.hwi p span{display:inline-block;min-width:30px;font-size:11px;font-weight:700;color:#0F766E}.hws .btns{margin-top:10px}`; document.head.appendChild(st); })();
