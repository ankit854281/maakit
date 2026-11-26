// ============================================================================
//  Teacher App — Parent Calls (owner, 19 Nov 2026: "PTM se related sabko call, kuch aur ho to usse related,
//  attendance se related calling — 1 day, 3 day continue, aur feedback mark karne ka option"). Loaded LAST.
//
//  The calling screens themselves are calls.php (+ calls.js, calls_api.php) — the same page the web ERP opens —
//  so both always show the same lists. Here: Connect → "📞 Calls" (summary + buttons), a card on Today /
//  Principal Home when something is waiting, and ☰ → Parents & staff → Parent calls.
//  calls.php opens as its own page (?from=t gives a "← Teacher App" link): tel: links work there in the
//  installed app (a frame cannot dial), and it never reloads in the middle of a call.
// ============================================================================
let PCS = null;   // calls_api.php?a=state, refreshed when older than 2 minutes
function pcCan() { return S && ME && (isP() || myCT().length > 0 || (typeof isFeeCaller === 'function' && isFeeCaller())); }
function pcNeed(force) {
  if (!pcCan()) return null;
  if (!force && PCS?.loading) return PCS;
  if (PCS && !force && (PCS.loading || Date.now() - PCS.at < 120000)) return PCS;
  const token = store.get('sch_token');
  const request = PCS = Object.assign({}, PCS || {}, { loading: true, at: Date.now() });
  const received = data => {
    if (token !== store.get('sch_token') || PCS !== request) return;
    PCS = Object.assign(data, { at: Date.now() });
    softRender();
  };
  api('calls_api.php?a=state').then(received).catch(e => received({ err: e.message }));
  return PCS;
}
function pcOpen(q) { location.href = 'calls.php?from=t' + (q || ''); }
function pcSum(D) {
  const A = D.att || {}, cls = Object.keys(A), t = k => cls.reduce((n, c) => n + (A[c][k] || 0), 0);
  const live = (D.campaigns || []).filter(c => c.active);
  return { d3: t('d3'), d2: t('d2'), d1: t('d1'), low: t('low'), todo: t('todo'), live, left: live.reduce((n, c) => n + (c.n.kids - c.n.called), 0), again: (D.again || []).length, cls };
}
function vCalls() {
  const D = pcNeed();
  if (!D || D.loading && !D.campaigns) return '<div class="load"><i></i></div>';
  if (D.err) return `<div class="card">${esc(D.err)} <button class="btn s l" onclick="PCS=null;render()">Try again</button></div>`;
  const s = pcSum(D);
  return `<div class="card"><h3>📞 Parent calls</h3><p class="mut" style="margin:0">PTM, function, exam, absent children — call, then tap what the parent said (will come, reason, happy / complaint). The Principal sees every call.</p>
      <button class="btn go full" style="margin-top:10px" onclick="pcOpen()">${ic('phone')} Open Parent Calls</button></div>
    ${s.again ? `<div class="card"><div class="row"><div><b>🔁 Call again today: ${s.again}</b><small class="mut" style="display:block">did not answer / asked to call back</small></div><button class="btn s l" onclick="pcOpen()">Open</button></div></div>` : ''}
    ${s.cls.length ? `<div class="card"><h3>📅 Absent children</h3><div class="kp3"><div><b style="color:#b42318">${s.d3}</b><span>3+ days in a row</span></div><div><b>${s.d2 + s.d1}</b><span>1–2 days</span></div><div><b>${s.low}</b><span>below 75%</span></div></div>
      <p class="mut" style="margin:6px 0">${s.todo} still to call.</p>${s.cls.length === 1 ? `<button class="btn s go" onclick="pcOpen('&cp=att&c=${encodeURIComponent(s.cls[0])}')">Call absent children</button>` : `<button class="btn s go" onclick="pcOpen()">See class-wise</button>`}</div>` : ''}
    ${s.live.map(c => `<div class="card"><div class="row"><div><b>${esc(c.title)}</b><small class="mut" style="display:block">${c.date ? esc(fmtDate(c.date)) + (c.time ? ' · ' + esc(c.time) : '') + ' · ' : ''}called ${c.n.called}/${c.n.kids}${['ptm', 'event'].includes(c.type) ? ' · will come ' + c.n.yes + ' · not coming ' + c.n.no : ''}${c.n.bad ? ' · 😟 ' + c.n.bad : ''}</small></div>
      <button class="btn s go" onclick="pcOpen('&cp=${encodeURIComponent(c.id)}&c=${encodeURIComponent(c.classes[0] || '')}')">Call</button></div></div>`).join('')}
    ${D.canCampaign ? `<button class="btn l full" onclick="pcOpen()">＋ New calling list (PTM, function …)</button>` : ''}`;
}
// small card on Today / Principal Home — only when there is something to do
function pcCard() {
  if (!pcCan()) return ''; const D = pcNeed(); if (!D || !D.campaigns) return '';
  const s = pcSum(D), items = [];
  if (s.again) items.push(`🔁 ${s.again} call again`);
  if (s.todo) items.push(`📅 ${s.todo} absent to call${s.d3 ? ' (' + s.d3 + ' for 3+ days)' : ''}`);
  s.live.forEach(c => { const left = c.n.kids - c.n.called; if (left > 0) items.push(`${c.type === 'ptm' ? '👨‍👩‍👧' : '📣'} ${esc(c.title)}: ${left} left`); });
  if (!items.length) return '';
  return `<div class="card lpb-home"><div class="row"><div><b>📞 Parent calls today</b><small>${items.slice(0, 4).join(' · ')}</small></div><button class="btn go" onclick="go('conn',{m:'calls'})">Open</button></div></div>`;
}
(function () {
  const _vHome = vHome; vHome = function () { return pcCard() + _vHome(); };
  if (typeof vPdash === 'function') { const _vP = vPdash; vPdash = function () { return pcCard() + _vP(); }; }
  if (typeof MENU !== 'undefined') MENU.forEach(g => { if (g[0] === 'Parents & staff' && !g[1].some(x => x[1] && x[1].m === 'calls')) g[1].unshift(['conn', { m: 'calls' }, 'phone', 'Parent calls', 'PTM, function, absent 1 / 3 days — feedback']); });
  // coming back from calls.php or the phone → fresh numbers
  document.addEventListener('visibilitychange', () => { if (!document.hidden && PCS && !PCS.loading) PCS.at = 0; });
})();
