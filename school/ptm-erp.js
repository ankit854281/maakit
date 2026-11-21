'use strict';
// PTM Desk in the web ERP (owner, 21 Nov 2026). Page: ptm.php. Old-ERP look: Event/PTM → "PTM Desk (parents)".
if (typeof VIEWS !== 'undefined' && typeof NAV !== 'undefined') {
  for (const role of ['principal', 'chairman', 'reception']) if (NAV[role] && !NAV[role].some(x => x[0] === 'ptm')) NAV[role].push(['ptm', 'fa-people-roof', 'PTM Desk']);
  VIEWS.ptm = () => head('PTM Desk', 'Parent entry, result talk, fee, feedback and complaints — one screen.', '<a href="ptm.php" target="_blank" class="btn btn-l"><i class="fa-solid fa-up-right-from-square"></i> Open full page</a>')
    + '<iframe class="edu-iframe" title="PTM Desk" src="ptm.php" style="width:100%;min-height:85vh;border:0;border-radius:16px"></iframe>';
}
if (typeof CLASSIC_MENU !== 'undefined') {
  const g = CLASSIC_MENU.find(x => x[0] === 'Event/PTM'); if (g && !g[2].some(it => it[1] === 'v:ptm')) g[2].unshift(['PTM Desk (parents)', 'v:ptm']);
}
