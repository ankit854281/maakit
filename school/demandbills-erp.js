'use strict';
// Demand Bill in the web ERP (owner, 20 Nov 2026: "demand bill purane software jaisa — sibling ya single").
// Old-ERP look: Fee Report → Demand Bill and Demand Bill - II open demandbills.php (they used to open the Fee Department
// "Bills & charges" page by mistake). New look: menu item "Demand Bill" under Fees. Server allows Principal, Chairman,
// Accountant (and the Manager in the Salary app).
if (typeof VIEWS !== 'undefined' && typeof NAV !== 'undefined') {
  for (const role of ['principal', 'chairman', 'accountant']) if (NAV[role] && !NAV[role].some(x => x[0] === 'dbill')) NAV[role].push(['dbill', 'fa-file-invoice', 'Demand Bill']);
  if (typeof GROUPS !== 'undefined') for (const role of ['principal', 'chairman', 'accountant']) {
    const grp = (GROUPS[role] || []).find(x => /fee/i.test(x[0])); if (grp && !grp[2].includes('dbill')) grp[2].push('dbill'); }
  VIEWS.dbill = () => head('Demand Bill', 'Class, section, student — single or sibling-wise — upto month → print, WhatsApp or download.', '<a href="demandbills.php" target="_blank" class="btn btn-l"><i class="fa-solid fa-up-right-from-square"></i> Open full page</a>')
    + '<iframe class="edu-iframe" title="Demand Bill" src="demandbills.php" style="width:100%;min-height:85vh;border:0;border-radius:16px"></iframe>';
}
if (typeof CLASSIC_MENU !== 'undefined') {
  CLASSIC_MENU.forEach(g => (g[2] || []).forEach(it => { if (/^Demand Bill( - II)?$/.test(it[0])) it[1] = 'v:dbill'; }));
}
