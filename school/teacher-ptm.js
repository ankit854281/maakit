'use strict';
// PTM Desk card in the Teacher App for the desk teacher — Pawan Bhalla Sir (tid PW) or settings.ptmDesk (owner, 21 Nov 2026).
// Opens ptm.php (same login). Data/rules: ptm_api.php.
if (typeof vHome === 'function') {
  const _vptm = vHome;
  vHome = function () { const html = _vptm.apply(this, arguments);
    try { if (!S || !ME || (typeof pMode === 'function' && pMode())) return html;
      const L = (S.settings || {}).ptmDesk, mine = Array.isArray(L) ? L.includes(me()) : me() === 'PW';
      if (!mine) return html;
      return `<div class="card lpb-home"><div class="row"><div><b>👪 PTM Desk</b><small>Record parent visits, result discussion, fee, feedback and complaints.</small></div><button class="btn go" onclick="location.href='ptm.php'">Open</button></div></div>` + html;
    } catch (e) { return html; } };
}
