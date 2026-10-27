// ============================================================================
//  Old-ERP look ("classic") for the school panel — owner decision, 22 Oct 2026
//
//  Ankit: "poora ERP purane jaisa" — the staff should see the same menu, the
//  same names in the same order, and the same colours as the old ERP
//  (schoolerpindia.net/school/snmishra), so nobody has to learn anything new.
//
//  How it works
//  - Office roles (principal, chairman, accountant, reception) get this layout
//    by default. Teachers, parents and students keep the normal layout.
//  - "New look" in the ADMIN menu switches back (per browser, localStorage
//    'sch_look' = 'new'); "Old ERP look" switches again.
//  - CLASSIC_MENU is the old ERP's left menu, copied group by group and item
//    by item (22 Oct 2026). Each item points at the new screen that does that
//    job:  'v:<view>' = a screen of app.js, 'o:<module>' = a School Office
//    register (registers_lib.php), 'f:<tab>' = a Fee Department tab
//    (finance.js), 'r:<page.php>' = that old ERP page as copied into Old ERP
//    Reports (oldreports.php), 'm:<mode>' = Student Master in the old ERP's
//    own form (stumaster.php: add, list, search), 'q:<report>' = a fee report
//    in the old ERP's layout (feereports.php). '' = the new ERP has no such screen yet: the item is kept
//    in the menu (so the menu stays the same) and opens a short note.
//  - Only the layout and colours change. No data is read or written here.
// ============================================================================

const CLASSIC_MENU = [
  ['Dashboard', 'fa-gauge', [['Dashboard', 'v:dash'], ['Inventory Dashboard', 'o:item'], ['Dashboard-II', 'v:admin'], ['Birthday Dashboard', 'v:students'], ['Timetable Dashboard', 'v:timetable'], ['Account Dashboard', 'o:cashbook'], ['Homework Dashboard', 'v:diaries'], ['Attendance Dashboard', 'v:attendance'], ['Attendance Followup Dashboard', 'v:attendance'], ['RFID Attendance Dashboard', ''], ['Reminders Dashboard', 'o:reminder'], ['Enquiry Dashboard', 'o:enquiry'], ['Admin Dashboard', 'v:admin'], ['Circular Dashboard', 'v:notices'], ['Mobile APP Dashboard', ''], ['Fees Followup Dashboard', 'v:fcalls'], ['Tags Followup Dashboard', 'o:tag'], ['Set Dashboard', '']]],
  ['Setting', 'fa-gears', [['School Setting', 'v:settings'], ['Setting', 'v:settings'], ['Exam Setting', 'v:exams'], ['Student Setting', 'v:settings'], ['APP Setting', ''], ['Backup History', ''], ['TC Setting', 'o:tc'], ['SMS Template', 'o:msgtpl'], ['Login Details', 'v:log'], ['CC Setting', 'o:cert'], ['Dob & Bonafide Certificate', 'o:cert'], ['Upload Image/Video', 'o:document'], ['Menu', ''], ['Submenu', ''], ['Search Receipt', 'f:receipts'], ['I-Card Setting', 'o:idcard']]],
  ['Admission Form', 'fa-user-plus', [['Admission Form(English)', 'o:admform'], ['Admission Form(Hindi)', 'o:admform']]],
  ['Master', 'fa-suitcase', [['Class Master', 'v:settings'], ['Session Master', 'v:settings'], ['Add Discount Head', 'f:setup'], ['Add City Gram Post Block Zone', 'o:master'], ['Add New Consultant', 'o:master'], ['Blood Group Master', 'o:master'], ['Time Slot Master', 'v:timetable'], ['Section Master', 'v:settings'], ['Documents Master', 'o:master'], ['Add Occupation', 'o:master'], ['Add Religion', 'o:master'], ['Subject Master', 'v:settings'], ['SMS Category', 'o:msgtpl'], ['Caste Master', 'o:master'], ['House Master', 'o:master'], ['School Master', 'v:settings']]],
  ['Import', 'fa-cloud-arrow-up', [['Student Import', 'v:students'], ['Employee Import', 'v:staff'], ['Enquiry Import', 'o:enquiry'], ['Fee Receipt Upload', 'o:oldreceipt'], ['Due Fee Upload', 'o:oldfee'], ['Student Image Upload In Bulk', 'v:students'], ['Attendance Import In Exam Module', '']]],
  ['Previous Question Paper', 'fa-gauge', [['Add Previous Question Paper Type', 'o:qpaper'], ['Add Previous Question Paper Session', 'o:qpaper'], ['Add Previous Question Paper', 'o:qpaper']]],
  ['User Master', 'fa-users', [['User Master', 'v:settings'], ['User Details', 'v:log']]],
  ['Whatsapp', 'fa-comments', [['Whatsapp Configration', 'o:msgtpl'], ['Create Group', 'o:msgtpl'], ['Whatsapp Add participants', 'o:msgtpl'], ['Whatsapp Group', 'o:msgtpl'], ['Whatsapp Report', 'o:msgtpl'], ['Whatsapp Add participants -II', 'o:msgtpl']]],
  ['Fee Master', 'fa-money-bill', [['Fee Head', 'f:setup'], ['Schemes Category', 'f:setup'], ['Schemes Master', 'f:setup'], ['Facilities Master', 'o:route'], ['Fee  Master', 'v:fees'], ['Fee Copy', 'v:fees'], ['Fees Summary', 'v:fees'], ['Fee Copy Session Wise', 'v:fees'], ['Fee Edit/Update', 'f:billing'], ['Fees Transfer', 'f:billing']]],
  ['Tag Mgmt', 'fa-tags', [['Add Tag', 'o:tag'], ['TAG Allocation', 'o:tag'], ['TAG Deallocation', 'o:tag'], ['Tags Pending Followup', 'o:tag'], ['Tags Done Followup', 'o:tag'], ['TAG Delete', 'o:tag']]],
  ['Transport', 'fa-bus', [['Add Driver/Helper', 'o:driver'], ['Add Vehicle', 'o:vehicle'], ['Log Book', 'o:logbook'], ['Diesel IN', 'o:diesel'], ['Logbook & Diesel Report', 'o:logbook'], ['Vehicle & Driver List', 'o:vehicle'], ['Class Wise Bus Student', 'o:route']]],
  ['Enquiry', 'fa-phone', [['New Enquiry', 'o:enquiry'], ['Enquiry Details', 'o:enquiry'], ['Enquiry Details - II', 'o:enquiry'], ['Done Followup', 'o:enquiry'], ['Pending follow up', 'o:enquiry'], ['Enquiry Search', 'o:enquiry'], ['Enquiry Status', 'o:enquiry']]],
  ['Student Master', 'fa-child', [['Add Payment Mode', 'f:setup'], ['Add Student', 'm:add'], ['Student Search', 'm:search'], ['Create Group Leader', 'v:students'], ['Group Leader List', 'v:students'], ['Promote Student', 'v:students'], ['Student Edit', 'm:list'], ['Shift Student', 'v:students']]],
  ['Student Edit/Update', 'fa-pen-to-square', [['Edit/Update Student', 'm:list'], ['Edit/Update Admission Details', 'm:list'], ['Edit/Update Scholarship Section', 'm:list'], ['Edit/Update Student Details', 'm:list'], ['Edit/Update Family Details', 'm:list'], ['Edit/Update Address', 'm:list'], ['Edit/Update Previous School Information', 'm:list'], ['Edit/Update Fee Details', 'f:billing'], ['Edit/Update Student Health Details', 'm:list']]],
  ['Fee', 'fa-indian-rupee-sign', [['Fee Receipt', 'v:feereceipt'], ['Fee Receipt(Head Wise)', 'v:feereceipt'], ['Fee Discount(Head Wise)', 'f:billing'], ['Student Fees Received -II', 'v:feereceipt'], ['Fee Receipt Transport', 'v:feereceipt'], ['Fee  Adjustment', 'f:billing'], ['Fee  Discount', 'f:billing'], ['Advance Fee Receipt', 'f:students'], ['Other Fee Receipt', 'f:students'], ['Fee Refund', 'f:receipts'], ['Add Other Fee', 'f:billing'], ['Add Other Fee(Bulk)', 'f:billing']]],
  ['Fee Report', 'fa-credit-card', [['Add Other Fee Report', 'f:reports'], ['Other Fee Receipt', 'f:receipts'], ['Fee Refund', 'f:receipts'], ['Fee Reminder', 'v:fcalls'], ['Discount Todays Fee', 'f:reports'], ['Demand Bill', 'f:billing'], ['Demand Bill - II', 'f:billing'], ['Student Fee Card Bulk', 'o:oldfeecard'], ['Fee Defaulter', 'q:defaulter'], ['Fee Defaulter - II', 'f:follow'], ['Fee Defaulter Head Wise', 'f:follow'], ['Fee Statement Head Wise', 'f:reports'], ['Zero Due Report', 'q:zerodue'], ['Fee Statement', 'q:statement'], ['Fee Statement-III', 'f:reports'], ['Fee Statement-II', 'f:reports'], ['Fee Statement(Deactive)', 'o:oldfee'], ['Fee Collection (Hold Fee)', 'f:reports'], ['Fee Register', 'q:register'], ['Fee Collection Report I', 'q:fee1'], ['Fee Collection Report II', 'q:fee2'], ['Fee Collection(Settlement Date Wise)', 'f:reports'], ['Fees Concession Report', 'q:conc'], ['Bank Report I', 'q:bank1'], ['Bank Report II', 'q:bank2'], ['Fees Pending Followup', 'v:fcalls'], ['Fees Done Followup', 'v:fcalls'], ['Deleted Fee Receipt', 'f:receipts'], ['Class Wise Collection Report', 'q:classwise'], ['Monthly Collection Report', 'q:monthly'], ['Fee Collection Report IV', 'q:fee4'], ['Fee Collection Report V', 'q:fee5'], ['Fee  Adjustment Report', 'f:reports'], ['Session Wise Fee', 'o:oldfee'], ['Head Wise Collection Report', 'q:headwise'], ['Head Wise Fee Statement', 'f:reports'], ['Old ERP Receipts', 'o:oldreceipt']]],
  ['Leave Mgmt', 'fa-right-from-bracket', [['Student Leave Apply', 'o:sleave'], ['Student Leave Apply Details', 'o:sleave']]],
  ['Exam Room Allocation', 'fa-mobile-screen', [['Layout Master', 'o:examroom'], ['Room Master', 'o:examroom'], ['Room Allocation', 'o:examroom'], ['Room Deallocation', 'o:examroom']]],
  ['Student Attendance', 'fa-clipboard', [['Student Attendance', 'v:attendance'], ['Student Holiday Mgmt', 'v:calendar'], ['Attendance Register', 'v:attendance'], ['Attendance Status', 'v:attendance'], ['Absentees Report', 'v:attendance'], ['Absentees Report II', 'v:attendance'], ['Attendance Summary', 'v:attendance']]],
  ['SMS', 'fa-comment', [['Due Fee SMS 1', 'o:msgtpl'], ['Due Fee SMS 2', 'o:msgtpl'], ['Send SMS to Employee', 'o:msgtpl'], ['Send APP Link', 'o:msgtpl'], ['Send Bulk Sms', 'o:msgtpl'], ['Send  Sms', 'o:msgtpl'], ['Send Exam Marks', 'o:msgtpl']]],
  ['Student TC', 'fa-clone', [['Create New TC', 'o:tc'], ['Student TC', 'o:tc'], ['TC Register', 'o:tc']]],
  ['Student Report', 'fa-bars', [['Year Wise Report', 'v:students'], ['Religion Wise Report', 'o:studinfo'], ['Age Wise Report', 'v:students'], ['Student Dynamic Report Master', 'o:studinfo'], ['Student Report Master', 'o:studinfo'], ['Student Dynamic Report', 'o:studinfo'], ['Any Notice', 'v:notices'], ['Scholar Register', 'o:studinfo'], ['Admission Register', 'o:studinfo'], ['Student List', 'v:students'], ['APP Link', 'r:report_applink.php'], ['Student  Document Report', 'o:document'], ['Category Wise Report', 'o:studinfo'], ['Student Sibling Report', 'v:students'], ['Admission/TC Report', 'o:tc'], ['Student Strength Format I', 'v:students'], ['Student Strength Format III', 'v:students'], ['Student Strength Format II', 'v:students'], ['Student Strength Format IV', 'v:students'], ['I Card Print-I', 'o:idcard'], ['I Card Print-II', 'o:idcard'], ['Parent Card', 'o:idcard'], ['Transport Card Print', 'o:idcard'], ['Sport Card Print', 'o:idcard'], ['SN I Card Print', 'o:idcard'], ['SN I Card Print-II', 'o:idcard'], ['Library Card Print', 'o:idcard']]],
  ['Reminders', 'fa-calendar', [['Add Reminder', 'o:reminder'], ['Reminder Details', 'o:reminder']]],
  ['Mobile APP', 'fa-mobile-screen', [['Calender', 'v:calendar'], ['Subject Master', 'v:syllabus'], ['Gallery / Video Category', 'o:website'], ['Homework', 'v:diaries'], ['Home work Details', 'v:diaries'], ['Assignment Details', 'v:diaries'], ['Syllabus', 'v:syllabus'], ['Contact us', 'o:website'], ['Circular', 'v:notices'], ['News/Gallery', 'o:website'], ['Video', 'o:website'], ['Academic Video', 'v:learning'], ['Academic Video Details', 'v:learning'], ['Academic Notes', 'v:learning'], ['Academic Notes Details', 'v:learning'], ['Download', 'o:website'], ['APP Attendance Present Report', 'v:attendance']]],
  ['Live Classes', 'fa-table-cells-large', [['Add Online Class URL', 'o:liveclass'], ['Live Class', 'o:liveclass']]],
  ['HR Management', 'fa-money-bill', [['New Department', 'v:staff'], ['Add Feedback Employee', 'o:feedback'], ['Pending Employee Feedback', 'o:feedback'], ['Employee Feedback Reprt', 'o:feedback'], ['New Employee', 'v:staff'], ['View Employee', 'v:staff'], ['Add Employee Category', 'v:staff'], ['Employee I Card Print', 'o:idcard'], ['Edit Employee', 'v:staff'], ['Generate Salary', 'v:salary'], ['View Salary', 'v:salary'], ['Employee Attendance', 'v:staff'], ['Employee Attendance Report', 'v:staff'], ['Employee Ateendance Import', 'v:staff'], ['Attendance Report', 'v:staff'], ['Add Leave Application', 'v:staff'], ['Leave Application Report', 'v:staff'], ['Leave Allocation', 'v:staff'], ['Add Leave Type', 'v:staff'], ['Holiday Mgmt', 'v:calendar'], ['Employee Holiday Mgmt Report', 'v:calendar']]],
  ['Online Exam', 'fa-table-cells-large', [['Add New Exam', 'v:quizt'], ['Add New Question', 'v:quizt'], ['MCQ Exam Details', 'v:quizt'], ['Add New Question Excel', 'v:quizt'], ['Subjective Exam Details', 'v:quizt'], ['Add New Question(SUBJECTIVE)', 'v:quizt']]],
  ['Account Master', 'fa-building-columns', [['Bank Master', 'o:party'], ['Cheque Entry', 'o:party'], ['Head Master', 'o:acchead'], ['Sub Head Master', 'o:acchead'], ['Party Master', 'o:party']]],
  ['Account', 'fa-calculator', [['Bill  Entry', 'o:cashbook'], ['Payment', 'o:cashbook'], ['Receipt', 'o:cashbook'], ['Self Withdrawal', 'o:cashbook'], ['Bank Deposit', 'o:cashbook'], ['Cash Verification', 'o:cashverify']]],
  ['Account Report', 'fa-indian-rupee-sign', [['Due Bill', 'o:purchase'], ['Dainikee', 'o:cashbook'], ['Cashbook', 'o:cashbook'], ['Bank  Statement', 'o:cashbook'], ['Cash Summary Report', 'o:cashbook'], ['Bank  Report', 'o:cashbook'], ['Income & Expenditure Report', 'o:cashbook'], ['Receipts & Payments Consolidated', 'o:cashbook'], ['Receipts & Payments Detailed', 'o:cashbook'], ['Receipts & Payments', 'o:cashbook'], ['Receipt Summary', 'o:cashbook'], ['Bill Summary', 'o:cashbook'], ['Payment Report', 'o:cashbook'], ['Payment Summary', 'o:cashbook'], ['Party Ledger (Bill/Payment)', 'o:party'], ['Bank Deposit Report', 'o:cashbook'], ['Party Ledger (Receive/Payment)', 'o:party'], ['Employee Ledger', 'v:salary'], ['Userwise Collection Report', 'f:reports']]],
  ['Admit Card', 'fa-table-list', [['Exam Type Management', 'v:exams'], ['Admit Card  Master', 'o:admit'], ['Admit Card  Details', 'o:admit'], ['Student Check List', 'o:admit'], ['Admit Card Print', 'o:admit'], ['Student Roll No Allocation', 'o:admit'], ['Blank Sheet - I', 'v:exams'], ['Blank Sheet - II', 'v:exams'], ['Admit Card Print II', 'o:admit'], ['Admit Card Print III', 'o:admit'], ['Admit Card-V', 'o:admit'], ['No Dues Print', 'o:admit'], ['Temporary Admit Card', 'o:admit'], ['Temporary Admit Card Details', 'o:admit']]],
  ['Timetable Mgmt', 'fa-table', [['Period Master', 'v:timetable'], ['Time Table Allocation', 'v:timetable']]],
  ['Examination(Master)', 'fa-table-cells', [['Progress Card Send to Student', 'v:results'], ['Grade Master', 'v:exams'], ['Subject Master', 'v:exams'], ['Co-Scholastic Master', 'v:exams'], ['Student Ranking', 'v:results']]],
  ['Examination-I', 'fa-paper-plane', [['Marks Entry(Pre Term)', 'v:progress'], ['Marks Entry(Term)', 'v:progress'], ['Co-Scholastic Marks Entry', 'v:progress'], ['Pre Term Progress Card', 'v:progress'], ['Term Progress Card', 'v:progress'], ['Result Sheet (Pre Term)', 'v:progress'], ['Result Sheet ( Term)', 'v:progress'], ['Marks Entry(IX)', 'v:progress'], ['Subject Allocation Report', 'v:exams'], ['Result Sheet(Term1+Term2)', 'v:progress'], ['Marks Entry(Additional)', 'v:progress'], ['Class Teacher Attendance/Remark', 'v:progress'], ['Progress Card(I-VIII)', 'v:progress'], ['Progress Card(IX)', 'v:progress'], ['Subject Allocation', 'v:exams'], ['Old ERP Marks', 'o:oldmarks']]],
  ['Examination-II', 'fa-paper-plane', [['Activity Master', 'v:exams'], ['Subject Activity Master', 'v:exams'], ['Subject Activity Master - II', 'v:exams'], ['Marks Entry- II', 'v:results'], ['Student Attendance/Remark', 'v:results'], ['Co-Scholastic Marks Entry-II', 'v:results'], ['Term Progress Card', 'v:results'], ['Final Progress Card', 'v:results'], ['Result Sheet-I', 'v:results'], ['Result Sheet-II', 'v:results']]],
  ['Examination-III', 'fa-paper-plane', [['Add New Exam Type', 'v:exams'], ['Subject Master', 'v:exams'], ['Subject Allocation', 'v:exams'], ['Subject Allocation Report', 'v:exams'], ['Marks Entry - III', 'v:results'], ['Student Attendance/Remark', 'v:results'], ['Additional Subject Allocation', 'v:exams'], ['Co-Scholastic Marks Entry - III', 'v:results'], ['Progress Card-I', 'v:results'], ['Progress Card-II', 'v:results'], ['Progress Card-III', 'v:results'], ['Progress Card-IV', 'v:results'], ['Progress Card-V', 'v:results'], ['Progress Card-VI', 'v:results'], ['Result Sheet-I', 'v:results'], ['Result Sheet-II', 'v:results'], ['Exam Result Sheet -III', 'v:results'], ['Exam Result Sheet -IV', 'v:results']]],
  ['Store', 'fa-cart-shopping', [['Supplier Master', 'o:supplier'], ['Category Master', 'o:item'], ['Item Master', 'o:item'], ['Department Master', 'o:item'], ['New Purchase', 'o:purchase'], ['Purchase Details', 'o:purchase'], ['Purchase Details(Item)', 'o:purchase'], ['Item Issue', 'o:issue'], ['Item Issue Details', 'o:issue'], ['Item Issue Details(Item)', 'o:issue'], ['Stock Report', 'o:item'], ['Add Purchase Order', 'o:purchase'], ['Purchase Order Details', 'o:purchase'], ['Add Indent Voucher', 'o:issue'], ['Indent Voucher Details', 'o:issue'], ['Add Item Repair & Maintenance', 'o:repair'], ['Repair & Maintenance Details', 'o:repair'], ['New Sale', 'o:sale'], ['Sale Report', 'o:sale'], ['Add Customer', 'o:sale'], ['Customer Details', 'o:sale'], ['Purchase Due Bill', 'o:purchase'], ['Item Group', 'o:item'], ['Store Receipt Summary', 'o:sale'], ['Item Transfer', 'o:issue'], ['Item Transfer Details', 'o:issue'], ['Store Fee Receipt', 'o:sale'], ['Store Fee Refund', 'o:sale'], ['Sale Return', 'o:sale'], ['Delete Sale Report', 'o:sale'], ['Sale Due Bill', 'o:sale']]],
  ['ACADEMIC', 'fa-gauge', [['Academic Category Master', 'v:exams'], ['Academic Level Master', 'v:exams'], ['Add Test Schedule', 'v:exams']]],
  ['Gate Pass', 'fa-ticket', [['Gate Pass For Guardian', 'o:gatepass'], ['Gate Pass For Employee', 'o:gatepass'], ['Gate Pass Details(S)', 'o:gatepass'], ['Gatepass via Biometric', ''], ['Gate Pass Details(E)', 'o:gatepass']]],
  ['Document', 'fa-file', [['Add Document', 'o:document'], ['Document Report', 'o:document'], ['Test Schedule Report', 'v:exams']]],
  ['Alumni', 'fa-graduation-cap', [['Alumni Report', 'o:alumni']]],
  ['Website', 'fa-globe', [['Website Setting', 'o:website'], ['Generate Image Link', 'o:website'], ['TC Upload', 'o:website'], ['Download', 'o:website'], ['Website Banner Category', 'o:website'], ['Website Banner', 'o:website'], ['Add Menu Category', 'o:website'], ['Mandatory Disclosures', 'o:website'], ['Add Menu', 'o:website'], ['Add Pages', 'o:website'], ['Add News', 'o:website'], ['Add Slider', 'o:website'], ['Gallery/Video Category', 'o:website'], ['News/Gallery', 'o:website'], ['Add Testimonials', 'o:website'], ['Video', 'o:website'], ['Academic Calender', 'v:calendar'], ['Web Post Subject', 'o:website'], ['Add Job Title', 'o:website'], ['Add Job Post', 'o:website'], ['Education Title Master', 'o:website'], ['Career Report', 'o:website'], ['Website Staff Master', 'o:website'], ['Contact Us Details', 'o:website'], ['News Letter Details', 'o:website']]],
  ['Certificate', 'fa-certificate', [['Occasion Master', 'o:cert'], ['Awards & Certificates Issue', 'o:cert'], ['Participate Master', 'o:cert'], ['Awards & Certificates Details', 'o:cert']]],
  ['Feedback', 'fa-comment', [['Add Feedback', 'o:feedback'], ['Pending Feedback', 'o:feedback'], ['App Feedback Report', 'o:feedback']]],
  ['Lesson Plan', 'fa-chart-pie', [['Add Lesson Plan', 'v:lplans'], ['Lesson Plan Report', 'v:lplans'], ['Lesson Plan', 'v:lplans'], ['Prev. Ques. Paper Report', 'o:qpaper']]],
  ['Visitor Mgmt', 'fa-square-arrow-up-right', [['Visiting Type', 'o:visitor'], ['Add New Visitor', 'o:visitor'], ['Visitor Report', 'o:visitor']]],
  ['Event/PTM', 'fa-indent', [['Add Event/PTM', 'o:event'], ['Create Event/PTM', 'o:event'], ['Event/PTM Report', 'o:event']]],
  // Not in the old ERP's menu: every list/report page copied from it before the licence ends (oldreports.php).
  ['Old ERP Data', 'fa-box-archive', [['Old ERP Reports', 'v:oldreports']]]
];

let CL_OPEN = '', CL_ITEM = '', CL_Q = '', CL_SIDE = window.innerWidth > 860, CL_NOTE = '';
function clLook() { try { return localStorage.getItem('sch_look') || ''; } catch (e) { return ''; } }
function classicOn() { return !!U && ['principal', 'chairman', 'accountant', 'reception'].includes(U.role) && clLook() !== 'new'; }
function setLook(x) { try { if (x === 'new') localStorage.setItem('sch_look', 'new'); else localStorage.removeItem('sch_look'); } catch (e) {} render(); }
// Can this login open the target? Views must be in the role's own menu; registers and fees ride on 'office' / 'finance'.
function clCan(t) {
  if (!t) return true;
  const [k, v] = [t[0], t.slice(2)], has = x => NAV[U.role].some(n => n[0] === x);
  return k === 'v' ? has(v) : k === 'o' ? has('office') : k === 'f' ? has('finance') : k === 'r' ? has('oldreports') : k === 'm' ? has('stumaster') : k === 'q' ? has('feereports') : false;
}
function clMenu() {
  return CLASSIC_MENU.map(([g, ic, items]) => [g, ic, items.filter(([, t]) => clCan(t))]).filter(([, , items]) => items.length);
}
function clGo(t, label) {
  CL_ITEM = label; CL_NOTE = ''; if (window.innerWidth <= 860) CL_SIDE = false;
  if (!t) { CL_NOTE = label; render(); window.scrollTo(0, 0); return; }
  const v = t.slice(2);
  if (t[0] === 'v') return go(v);
  if (t[0] === 'o') return go('office', { m: v });
  if (t[0] === 'f') return go('finance', { tab: v });
  if (t[0] === 'r') return go('oldreports', { page: v });
  if (t[0] === 'm') return go('stumaster', { m: v });
  if (t[0] === 'q') return go('feereports', { r: v });
}
function clGroupToggle(g) { CL_OPEN = CL_OPEN === g ? '' : g; render(); }
function clSearch(v) { CL_Q = v; const box = document.getElementById('cl-menu'); if (box) box.innerHTML = clMenuHtml(); }
function clMenuHtml() {
  const q = CL_Q.trim().toLowerCase();
  return clMenu().map(([g, ic, items]) => {
    const shown = q ? items.filter(([l]) => l.toLowerCase().includes(q) || g.toLowerCase().includes(q)) : items;
    if (q && !shown.length) return '';
    const open = q || CL_OPEN === g;
    return `<li class="${open ? 'active' : ''}"><a onclick="clGroupToggle(${jsq(g)})"><i class="fa-solid ${ic}"></i>${esc(g)}<span class="cl-cv fa-solid fa-chevron-${open ? 'up' : 'down'}"></span></a>
      ${open ? `<ul class="cl-child">${shown.map(([l, t]) => `<li class="${CL_ITEM === l && (CL_OPEN === g || q) ? 'current' : ''}${t ? '' : ' cl-none'}"><a onclick="CL_OPEN=${jsq(g)};clGo(${jsq(t)},${jsq(l)})">${esc(l)}</a></li>`).join('')}</ul>` : ''}</li>`;
  }).join('');
}
function clNote(label) {
  return `<div class="cl-head"><h3>${esc(label)}</h3></div><div class="card"><p><b>${esc(label)}</b> — ye option purane ERP mein tha, naye ERP mein abhi alag se nahi hai.</p>
    <p class="text-sm text-slate-500">This old-ERP screen has no separate page in the new ERP yet. Nothing from it is lost: the old data is kept in the Old ERP Archive (School Office) and in the Full Export files.</p></div>`;
}
function clYear(s) { const m = String(s || '').match(/^(20\d\d)-(\d\d)$/); return m ? m[1] + '-20' + m[2] : s; }
function classicShell(body, name, roleName) {
  if (!CL_OPEN) { const hit = CLASSIC_MENU.find(([, , items]) => items.some(([l]) => l === CL_ITEM)); if (hit) CL_OPEN = hit[0]; }
  if (CL_NOTE) body = clNote(CL_NOTE);
  const ses = typeof viewSession === 'function' ? viewSession() : (S.settings.session || '');
  return `<div class="cl-body ${CL_SIDE ? '' : 'cl-mini'}">
  <aside class="cl-left noprint" id="sidebar">
    <div class="cl-title" onclick="CL_ITEM='Dashboard';CL_OPEN='Dashboard';clGo('v:${NAV[U.role][0][0]}','Dashboard')"><img src="img/snmsis-logo.png" alt=""><span>SCHOOL ERP</span></div>
    ${canProfile() ? `<div class="cl-search"><input id="g-q" placeholder="Student Search" autocomplete="off" oninput="gsearch(this.value)" onkeydown="if(event.key==='Enter')openProfile(null,null,this.value)"><button onclick="openProfile(null,null,document.getElementById('g-q').value)" aria-label="Search"><i class="fa-solid fa-magnifying-glass"></i></button><div id="g-res" class="srch-res"></div></div>` : ''}
    <div class="cl-search"><input placeholder="Search Menu" value="${esc(CL_Q)}" oninput="clSearch(this.value)" autocomplete="off"></div>
    <ul class="cl-side" id="cl-menu">${clMenuHtml()}</ul>
  </aside>
  <div class="cl-main">
    <div class="cl-top noprint">
      <button class="cl-burger" onclick="CL_SIDE=!CL_SIDE;render()" aria-label="Menu"><i class="fa-solid fa-bars"></i></button>
      <div class="cl-top-r">
        ${typeof canViewSession === 'function' && canViewSession() ? `<div class="dd dd-r"><button onclick="ddToggle(this)" class="cl-tl"><i class="fa-solid fa-arrows-rotate"></i> ${esc(clYear(ses))} <i class="fa-solid fa-angle-down"></i></button>
          <div class="dd-panel">${sessionList().map(x => `<button onclick="ddClose();setViewSession('${x}')" class="dd-item ${x === ses ? 'on' : ''}"><span>${esc(clYear(x))}${x === curSession() ? ' (current)' : ''}</span></button>`).join('')}</div></div>` : ''}
        <span class="cl-tl cl-school"><i class="fa-solid fa-school"></i> ${esc(S.settings.school || '')}</span>
        ${clCan('o:msgtpl') && NAV[U.role].some(n => n[0] === 'office') ? `<button class="cl-tl" onclick="CL_ITEM='SMS Template';clGo('o:msgtpl','SMS Template')"><i class="fa-solid fa-comment"></i> SMS</button>` : ''}
        <div class="dd dd-r"><button onclick="ddToggle(this)" class="cl-tl"><i class="fa-solid fa-user"></i> ${esc(String(roleName).toUpperCase())} <i class="fa-solid fa-angle-down"></i></button>
          <div class="dd-panel"><div class="px-3 py-2"><b class="block text-sm">${esc(name)}</b><small class="text-slate-500">${esc(roleName)}</small></div>
            ${U.role === 'principal' ? `<button onclick="ddClose();CL_ITEM='Setting';go('settings')" class="dd-item"><i class="fa-solid fa-gear"></i><span>Settings & PINs</span></button>` : ''}
            <button onclick="ddClose();setLook('new')" class="dd-item"><i class="fa-solid fa-wand-magic-sparkles"></i><span>New look</span></button>
            <button onclick="ddClose();logout()" class="dd-item"><i class="fa-solid fa-arrow-right-from-bracket"></i><span>Log out</span></button></div></div>
      </div>
    </div>
    <main class="cl-right space-y-4">${body}</main>
  </div></div>`;
}
