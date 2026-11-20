'use strict';
// Half-Yearly marks slips + rankers' certificates in the web ERP (owner, 20 Nov 2026). Page: results.php (read only).
// Old-ERP look: Examination-I → "Half-Yearly Marks Slip & Rankers". New look: "Marks Slip & Rankers" (Principal, Chairman).
if (typeof VIEWS !== 'undefined' && typeof NAV !== 'undefined') {
  for (const role of ['principal', 'chairman']) if (NAV[role] && !NAV[role].some(x => x[0] === 'mslip')) NAV[role].push(['mslip', 'fa-award', 'Marks Slip & Rankers']);
  VIEWS.mslip = () => head('Half-Yearly · Marks Slip & Rankers', 'One slip per child (grace shown separately) and certificates for the 1st, 2nd and 3rd of each class.', '<a href="results.php" target="_blank" class="btn btn-l"><i class="fa-solid fa-up-right-from-square"></i> Open full page</a>')
    + '<iframe class="edu-iframe" title="Marks Slip" src="results.php" style="width:100%;min-height:85vh;border:0;border-radius:16px"></iframe>';
}
if (typeof CLASSIC_MENU !== 'undefined') {
  const g = CLASSIC_MENU.find(x => x[0] === 'Examination-I'); if (g && !g[2].some(it => it[1] === 'v:mslip')) g[2].unshift(['Half-Yearly Marks Slip & Rankers', 'v:mslip']);
}
