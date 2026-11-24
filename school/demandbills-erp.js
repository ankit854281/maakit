'use strict';
// Sibling Demand Bill in the web ERP (owner, 20 Nov 2026: "sibbling demand ke name se add kardo erp me, old wala change
// nahi karna hai"). A NEW menu item — the old Fee Report → Demand Bill / Demand Bill - II stay exactly as they were.
// Old-ERP look: Fee Report → "Sibling Demand Bill" (right after Demand Bill - II). New look: "Sibling Demand Bill" under Fees.
// The page is demandbills.php (sibling bill by default, single too). Server allows Principal, Chairman, Accountant (+ Manager).
if (typeof VIEWS !== 'undefined' && typeof NAV !== 'undefined') {
  for (const role of ['principal', 'chairman', 'accountant']) if (NAV[role] && !NAV[role].some(x => x[0] === 'dbill')) NAV[role].push(['dbill', 'fa-file-invoice', 'Sibling Demand Bill']);
  if (typeof GROUPS !== 'undefined') for (const role of ['principal', 'chairman', 'accountant']) {
    const grp = (GROUPS[role] || []).find(x => /fee/i.test(x[0])); if (grp && !grp[2].includes('dbill')) grp[2].push('dbill'); }
  VIEWS.dbill = () => head('Sibling Demand Bill', 'Brothers / sisters (same mobile) in one bill — or single. Class, section, student, upto month → print, WhatsApp or download.', '<a href="demandbills.php" target="_blank" class="btn btn-l"><i class="fa-solid fa-up-right-from-square"></i> Open full page</a>')
    + '<iframe class="edu-iframe" title="Sibling Demand Bill" src="demandbills.php" style="width:100%;min-height:85vh;border:0;border-radius:16px"></iframe>';
}
if (typeof CLASSIC_MENU !== 'undefined') {
  const grp = CLASSIC_MENU.find(g => g[0] === 'Fee Report');
  if (grp && !grp[2].some(it => it[1] === 'v:dbill')) {
    const at = grp[2].findIndex(it => it[0] === 'Demand Bill - II');
    grp[2].splice(at >= 0 ? at + 1 : grp[2].length, 0, ['Sibling Demand Bill', 'v:dbill']);
  }
}
// Full fee statement of one child, every session 2024-25 → now, current session up to March (owner, 24 Nov 2026: "Astha Tiwari
// class 10 ka fee pura detail 2024 session se, Chairman Sir ko dena hai"). Page feestatement.php (feecollect_api.php?a=statement).
if (typeof VIEWS !== 'undefined' && typeof NAV !== 'undefined') {
  for (const role of ['principal', 'chairman', 'accountant']) if (NAV[role] && !NAV[role].some(x => x[0] === 'fstmt')) NAV[role].push(['fstmt', 'fa-file-lines', 'Fee Statement (all sessions)']);
  if (typeof GROUPS !== 'undefined') for (const role of ['principal', 'chairman', 'accountant']) {
    const grp = (GROUPS[role] || []).find(x => /fee/i.test(x[0])); if (grp && !grp[2].includes('fstmt')) grp[2].push('fstmt'); }
  VIEWS.fstmt = () => head('Fee Statement (all sessions)', 'One child: every session from 2024-25, month by month, receipts, old due — and this session up to March. Print or save as PDF.', '<a href="feestatement.php" target="_blank" class="btn btn-l">Open full page</a>')
    + '<iframe class="edu-iframe" title="Fee Statement" src="feestatement.php" style="width:100%;min-height:85vh;border:0;border-radius:16px"></iframe>';
}
if (typeof CLASSIC_MENU !== 'undefined') {
  const grp = CLASSIC_MENU.find(g => g[0] === 'Fee Report');
  if (grp && !grp[2].some(it => it[1] === 'v:fstmt')) grp[2].push(['Fee Statement (all sessions)', 'v:fstmt']);
}
