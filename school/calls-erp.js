'use strict';
// Parent Calls in the web ERP (owner, 19 Nov 2026: "school ERP new me aur teachers app me calling feature aur feedback mark").
// The page itself is calls.php (+ calls.js / calls_api.php) — the same one the Teacher App opens — shown here in a frame.
// New look: menu item "Parent Calls" (Principal, Chairman, Accountant, Fee Calling). Old-ERP look: Event/PTM → PTM / Event Calling,
// Student Attendance → Absent Calling. Chairman and Accountant only view (the server decides).
if (typeof VIEWS !== 'undefined' && typeof NAV !== 'undefined') {
  for (const role of ['principal', 'chairman', 'accountant', 'reception']) if (NAV[role] && !NAV[role].some(x => x[0] === 'pcalls')) NAV[role].push(['pcalls', 'fa-phone-volume', 'Parent Calls']);
  if (typeof GROUPS !== 'undefined') for (const [role, g] of [['principal', 'Students'], ['chairman', 'Students'], ['accountant', 'Fees'], ['reception', 'Fees']]) {
    const grp = (GROUPS[role] || []).find(x => x[0] === g); if (grp && !grp[2].includes('pcalls')) grp[2].push('pcalls'); }
  VIEWS.pcalls = () => head('Parent Calls', 'PTM, function, exam and absent children (1 day, 2 days, 3+ days in a row) — call, mark what the parent said, see complaints.', '<a href="calls.php" target="_blank" class="btn btn-l"><i class="fa-solid fa-up-right-from-square"></i> Open full page</a>')
    + '<iframe class="edu-iframe" title="Parent Calls" src="calls.php?embed=1" style="width:100%;min-height:85vh;border:0;border-radius:16px"></iframe>';
}
if (typeof CLASSIC_MENU !== 'undefined') {
  const add = (g, item) => { const grp = CLASSIC_MENU.find(x => x[0] === g); if (grp && !grp[2].some(x => x[0] === item[0])) grp[2].push(item); };
  add('Event/PTM', ['PTM / Event Calling', 'v:pcalls']);
  add('Student Attendance', ['Absent Calling (1 / 3 days)', 'v:pcalls']);
}
