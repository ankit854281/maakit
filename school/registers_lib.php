<?php
// ============================================================
// School Office registers — the old ERP's remaining menus.
//
// The old ERP (schoolerpindia.net/school/snmishra) had 45 menu groups.
// Fees, attendance, timetable, exams/marks, staff/salary, leave, lesson
// plans, homework, notices and calendar already exist in this ERP. Every
// other old menu is defined here as a REGISTER: a list of rows with fixed
// fields, who may read/write it, optional auto number, totals, and a print
// layout. One engine (registers_api.php + registers.js) serves all of them,
// so a new old-ERP menu is one array entry, not a new screen.
//
// Storage: sch_records, k='regRow', r='<module>|<id>' (JSON row);
// k='regSeq', r='<module>|<session>' (auto numbers). Both use the 'reg'
// prefix, which inc.php keeps OUT of legacy load/sync — office registers
// (gate passes, accounts, TCs) must never reach teachers' phones.
// ============================================================
declare(strict_types=1);

const REG_MAX_ROWS = 20000;          // per module
const REG_MAX_TEXT = 2000;

// Roles: P principal, C chairman, A accountant, R reception, T teacher
function reg_roles(string $s): array {
    $m = ['P' => 'principal', 'C' => 'chairman', 'A' => 'accountant', 'R' => 'reception', 'T' => 'teacher'];
    return array_values(array_map(fn($c) => $m[$c], array_filter(str_split($s), fn($c) => isset($m[$c]))));
}

function reg_classes(): array { return ['PG','LKG','UKG','1st','2nd','3rd','4th','5th','6th','7th','8th','9th','10th','11th','12th']; }

// Field shorthand: [key, label, type, extra]
// types: text, area, num, money, date, time, sel, yn, phone, student, teacher, cls, json
function reg_f(string $k, string $label, string $type = 'text', array $x = []): array { return ['k' => $k, 'label' => $label, 'type' => $type] + $x; }

function reg_modules(): array {
    static $M = null;
    if ($M !== null) return $M;
    $cls = ['opts' => reg_classes()];
    $modes = ['opts' => ['Cash','UPI','Cheque','NEFT','Card','Net Banking','Online','Wallet','Demand Draft']];
    $M = [
    // ---------------- Admission ----------------
    'enquiry' => ['title' => 'Admission Enquiry', 'group' => 'Admission', 'old' => 'Enquiry › New Enquiry / Follow-up', 'r' => 'PCR', 'w' => 'PR', 'auto' => ['no', 'ENQ'],
        'fields' => [reg_f('no','Enquiry No'), reg_f('date','Date','date',['req'=>1,'today'=>1]), reg_f('name','Student name','text',['req'=>1]), reg_f('dob','Date of birth','date'),
            reg_f('gender','Gender','sel',['opts'=>['Male','Female']]), reg_f('father','Father\'s name','text',['req'=>1]), reg_f('mother','Mother\'s name'), reg_f('phone','Father\'s mobile','phone',['req'=>1]),
            reg_f('phone2','Mother\'s mobile','phone'), reg_f('email','Email'), reg_f('prevSchool','Present school'), reg_f('cls','Class to be admitted','cls',$cls+['req'=>1]), reg_f('address','Address','area'),
            reg_f('source','Publicity medium','sel',['opts'=>['Paper','Hoarding','Paper Adv','Bulk SMS','Bulk Email','Pamphlets','Reference','Walk-in','Others']]),
            reg_f('etype','Enquiry type','sel',['opts'=>['Call from School','Visit from School','Guardian called us','Guardian mailed us','Guardian visited school']]),
            reg_f('status','Guardian\'s answer','sel',['opts'=>['Interested','Thinking','Shown interest for future','Fees issue','Registered','Admitted','Not interested','Old student'],'req'=>1]),
            reg_f('nextType','Next follow-up','sel',['opts'=>['Call','Visit','None']]), reg_f('nextDate','Next follow-up date','date'), reg_f('consultant','Consultant'), reg_f('remarks','Remarks','area')],
        'list' => ['no','date','name','cls','father','phone','status','nextDate'], 'due' => 'nextDate'],
    'admform' => ['title' => 'Admission Form (blank)', 'group' => 'Admission', 'old' => 'Admission Form (English/Hindi)', 'r' => 'PCR', 'w' => 'PR', 'auto' => ['no', 'ADF'],
        'fields' => [reg_f('no','Form No'), reg_f('date','Issued on','date',['today'=>1]), reg_f('name','Student name','text',['req'=>1]), reg_f('cls','Class','cls',$cls), reg_f('father','Father\'s name'), reg_f('phone','Mobile','phone'), reg_f('fee','Form fee (₹)','money'), reg_f('lang','Form language','sel',['opts'=>['English','Hindi']]), reg_f('remarks','Remarks','area')],
        'list' => ['no','date','name','cls','father','phone','fee'], 'sum' => ['fee'], 'print' => 'admission'],

    // ---------------- Student records ----------------
    'studinfo' => ['title' => 'Student Full Record', 'group' => 'Students', 'old' => 'Student Master / Student Edit (all 131 fields)', 'r' => 'PC', 'w' => 'P', 'keyed' => 'student',
        'fields' => [reg_f('student','Student','student',['req'=>1]), reg_f('status','Status','sel',['opts'=>['Active','Inactive (left)','TC issued','Deactive'],'req'=>1]),
            reg_f('admAs','Admission as','sel',['opts'=>['Day Scholar','Day Boarder','Boarder','Provisional']]), reg_f('stuType','Student type','sel',['opts'=>['New Student','Old Student']]),
            reg_f('doa','Date of admission','text'), reg_f('admittedCls','Admitted class','text'), reg_f('rollNo','Roll no'), reg_f('enrollNo','Enrollment no'), reg_f('penNo','PEN no'), reg_f('familyId','Family ID'),
            reg_f('aadhar','Aadhaar no'), reg_f('rfid','RFID card'), reg_f('house','House'), reg_f('medium','Medium'), reg_f('stream','Stream'), reg_f('optSub','Optional subject'),
            reg_f('gender','Gender','sel',['opts'=>['Male','Female']]), reg_f('nameHi','Name in Hindi'), reg_f('category','Category','sel',['opts'=>['GEN','OBC','SC','ST','EBC','EWS','General']]), reg_f('caste','Caste'),
            reg_f('religion','Religion'), reg_f('minority','Minority','yn'), reg_f('handicapped','Handicapped','yn'), reg_f('blood','Blood group'), reg_f('motherTongue','Mother tongue'), reg_f('nationality','Nationality'),
            reg_f('dobCity','Birth city'), reg_f('dobState','Birth state'), reg_f('fatherHi','Father\'s name (Hindi)'), reg_f('motherHi','Mother\'s name (Hindi)'), reg_f('fatherQual','Father\'s qualification'), reg_f('motherQual','Mother\'s qualification'),
            reg_f('fatherOcc','Father\'s occupation'), reg_f('motherOcc','Mother\'s occupation'), reg_f('fatherMobile','Father\'s mobile','phone'), reg_f('motherMobile','Mother\'s mobile','phone'), reg_f('email','Email'), reg_f('income','Family income'),
            reg_f('landmark','Landmark / stoppage'), reg_f('village','City / gram / post'), reg_f('district','District'), reg_f('state','State'), reg_f('pin','PIN code'),
            reg_f('guardian','Local guardian'), reg_f('guardianRel','Relation'), reg_f('guardianPhone','Guardian phone','phone'), reg_f('prevSchool','Previous school'), reg_f('prevCity','Previous school city'), reg_f('lastClass','Last class'),
            reg_f('scheme','Fee scheme'), reg_f('route','Transport route'), reg_f('vehicle','Vehicle'), reg_f('bankName','Bank name'), reg_f('bankAc','Bank account no'), reg_f('ifsc','IFSC'), reg_f('scholarship','Scholarship'), reg_f('ssid','SSID'),
            reg_f('leaving','Date of leaving'), reg_f('photoRef','Old ERP photo file'), reg_f('extra','Other old-ERP fields','json'), reg_f('remarks','Remarks','area')],
        'list' => ['student','status','gender','category','aadhar','fatherMobile','route']],
    'tc' => ['title' => 'Transfer Certificate', 'group' => 'Students', 'old' => 'Student TC › Create TC / TC Register', 'r' => 'PC', 'w' => 'P', 'auto' => ['no', 'TC'],
        'fields' => [reg_f('no','TC No'), reg_f('date','Date of issue','date',['req'=>1,'today'=>1]), reg_f('student','Student','student',['req'=>1]), reg_f('lastCls','Class last studied','cls',$cls), reg_f('admDate','Date of admission','date'),
            reg_f('leaveDate','Date of leaving','date',['req'=>1]), reg_f('result','Last exam result','sel',['opts'=>['Passed','Failed','Appeared','Not appeared']]), reg_f('promoted','Promoted to class','text'), reg_f('dues','All dues paid','yn'),
            reg_f('conduct','Conduct','sel',['opts'=>['Good','Very good','Excellent','Satisfactory']]), reg_f('reason','Reason for leaving','text',['req'=>1]), reg_f('workingDays','Working days'), reg_f('present','Days present'), reg_f('remarks','Remarks','area')],
        'list' => ['no','date','student','lastCls','leaveDate','reason'], 'print' => 'tc'],
    'cert' => ['title' => 'Certificates & Awards', 'group' => 'Students', 'old' => 'Certificate / DOB & Bonafide / Awards', 'r' => 'PC', 'w' => 'P', 'auto' => ['no', 'CRT'],
        'fields' => [reg_f('no','Certificate No'), reg_f('date','Date','date',['req'=>1,'today'=>1]), reg_f('kind','Certificate','sel',['opts'=>['Bonafide','Date of Birth','Character','Award','Participation','Merit'],'req'=>1]),
            reg_f('student','Student','student',['req'=>1]), reg_f('occasion','Occasion / event'), reg_f('position','Position / award'), reg_f('purpose','Purpose'), reg_f('remarks','Remarks','area')],
        'list' => ['no','date','kind','student','occasion','position'], 'print' => 'cert'],
    'idcard' => ['title' => 'ID Cards', 'group' => 'Students', 'old' => 'Student Report › I-Card / Parent / Transport / Library cards', 'r' => 'PC', 'w' => 'P', 'keyed' => 'student',
        'fields' => [reg_f('student','Student','student',['req'=>1]), reg_f('kind','Card','sel',['opts'=>['Student I-Card','Parent Card','Transport Card','Library Card','Sport Card'],'req'=>1]), reg_f('valid','Valid till','date'), reg_f('blood','Blood group'), reg_f('route','Route / stop'), reg_f('issued','Issued on','date',['today'=>1])],
        'list' => ['student','kind','valid','issued'], 'print' => 'idcard'],
    'sleave' => ['title' => 'Student Leave', 'group' => 'Students', 'old' => 'Leave Mgmt › Student Leave', 'r' => 'PCRT', 'w' => 'PR', 'fields' => [reg_f('student','Student','student',['req'=>1]), reg_f('from','From','date',['req'=>1]), reg_f('to','To','date',['req'=>1]), reg_f('reason','Reason','text',['req'=>1]), reg_f('status','Status','sel',['opts'=>['Pending','Approved','Rejected']])],
        'list' => ['student','from','to','reason','status']],
    'tag' => ['title' => 'Student Tags & Follow-up', 'group' => 'Students', 'old' => 'Tag Mgmt', 'r' => 'PCR', 'w' => 'PR', 'fields' => [reg_f('student','Student','student',['req'=>1]), reg_f('tag','Tag','sel',['opts'=>['DEFAULTER','DOC-COLL','DOC NOT REC','EFP','FAIL/REP','PEN WITH AADHAR','One Time Payment','Other'],'req'=>1]), reg_f('nextDate','Follow-up date','date'), reg_f('status','Status','sel',['opts'=>['Pending','Done']]), reg_f('remarks','Remarks','area')],
        'list' => ['student','tag','nextDate','status'], 'due' => 'nextDate'],
    'alumni' => ['title' => 'Alumni', 'group' => 'Students', 'old' => 'Alumni Report', 'r' => 'PC', 'w' => 'P', 'fields' => [reg_f('name','Name','text',['req'=>1]), reg_f('batch','Pass-out year'), reg_f('lastCls','Last class','cls',$cls), reg_f('phone','Mobile','phone'), reg_f('now','Now doing'), reg_f('city','City')],
        'list' => ['name','batch','lastCls','phone','now']],

    // ---------------- Exams ----------------
    'examroom' => ['title' => 'Exam Rooms & Seating', 'group' => 'Exams', 'old' => 'Exam Room Allocation', 'r' => 'PCT', 'w' => 'P', 'fields' => [reg_f('exam','Exam','text',['req'=>1]), reg_f('room','Room','text',['req'=>1]), reg_f('rows','Rows','num'), reg_f('cols','Benches per row','num'), reg_f('classes','Classes seated'), reg_f('invigilator','Invigilator','teacher')],
        'list' => ['exam','room','rows','cols','classes','invigilator']],
    'admit' => ['title' => 'Admit Cards', 'group' => 'Exams', 'old' => 'Admit Card › Roll no / Print / No dues', 'r' => 'PC', 'w' => 'P', 'fields' => [reg_f('exam','Exam','text',['req'=>1]), reg_f('student','Student','student',['req'=>1]), reg_f('roll','Exam roll no'), reg_f('room','Room / seat'), reg_f('noDues','No dues cleared','yn'), reg_f('temporary','Temporary card','yn')],
        'list' => ['exam','student','roll','room','noDues'], 'print' => 'admit'],
    'qpaper' => ['title' => 'Previous Question Papers', 'group' => 'Exams', 'old' => 'Previous Question Paper', 'r' => 'PCT', 'w' => 'PT', 'fields' => [reg_f('session','Session','text',['req'=>1]), reg_f('kind','Paper type','sel',['opts'=>['Unit Test','Half Yearly','Annual','Pre-Board','Board']]), reg_f('cls','Class','cls',$cls+['req'=>1]), reg_f('subject','Subject','text',['req'=>1]), reg_f('link','File link / location')],
        'list' => ['session','kind','cls','subject','link']],

    // ---------------- Transport ----------------
    'vehicle' => ['title' => 'Vehicles', 'group' => 'Transport', 'old' => 'Transport › Add Vehicle / Vehicle & Driver List', 'r' => 'PCA', 'w' => 'PA', 'fields' => [reg_f('number','Vehicle number','text',['req'=>1]), reg_f('kind','Type','sel',['opts'=>['Big Bus','Mini Bus','Magic','Van','Bolero','Other']]), reg_f('seats','Seats','num'), reg_f('driver','Driver'), reg_f('helper','Helper'), reg_f('insurance','Insurance till','date'), reg_f('fitness','Fitness till','date'), reg_f('permit','Permit till','date'), reg_f('pollution','Pollution till','date')],
        'list' => ['number','kind','seats','driver','helper','insurance','fitness'], 'due' => 'insurance'],
    'driver' => ['title' => 'Drivers & Helpers', 'group' => 'Transport', 'old' => 'Transport › Add Driver/Helper', 'r' => 'PCA', 'w' => 'PA', 'fields' => [reg_f('name','Name','text',['req'=>1]), reg_f('role','Role','sel',['opts'=>['Driver','Helper'],'req'=>1]), reg_f('phone','Mobile','phone'), reg_f('licence','Licence no'), reg_f('licenceTill','Licence valid till','date'), reg_f('vehicle','Vehicle'), reg_f('salary','Monthly pay (₹)','money')],
        'list' => ['name','role','phone','vehicle','licenceTill'], 'due' => 'licenceTill'],
    'route' => ['title' => 'Routes & Stops', 'group' => 'Transport', 'old' => 'Fee Master › Facilities (Route/Hostel)', 'r' => 'PCA', 'w' => 'PA', 'fields' => [reg_f('name','Route / stop','text',['req'=>1]), reg_f('facility','Facility','sel',['opts'=>['Transport','Hostel']]), reg_f('vehicle','Vehicle'), reg_f('fee','Yearly fee (₹)','money'), reg_f('km','Distance (km)','num'), reg_f('status','Status','sel',['opts'=>['Active','Inactive']])],
        'list' => ['name','facility','vehicle','fee','km','status']],
    'logbook' => ['title' => 'Vehicle Log Book', 'group' => 'Transport', 'old' => 'Transport › Log Book', 'r' => 'PCA', 'w' => 'PA', 'fields' => [reg_f('date','Date','date',['req'=>1,'today'=>1]), reg_f('vehicle','Vehicle','text',['req'=>1]), reg_f('kmStart','Meter start','num'), reg_f('kmEnd','Meter end','num'), reg_f('km','Km run','num'), reg_f('purpose','Trip / purpose'), reg_f('driver','Driver')],
        'list' => ['date','vehicle','kmStart','kmEnd','km','purpose'], 'sum' => ['km']],
    'diesel' => ['title' => 'Diesel / Fuel', 'group' => 'Transport', 'old' => 'Transport › Diesel IN', 'r' => 'PCA', 'w' => 'PA', 'fields' => [reg_f('date','Date','date',['req'=>1,'today'=>1]), reg_f('vehicle','Vehicle','text',['req'=>1]), reg_f('litres','Litres','num',['req'=>1]), reg_f('amount','Amount (₹)','money',['req'=>1]), reg_f('meter','Meter reading','num'), reg_f('pump','Pump / bill no')],
        'list' => ['date','vehicle','litres','amount','meter','pump'], 'sum' => ['litres','amount']],

    // ---------------- Store ----------------
    'supplier' => ['title' => 'Suppliers', 'group' => 'Store', 'old' => 'Store › Supplier Master', 'r' => 'PCA', 'w' => 'PA', 'fields' => [reg_f('name','Supplier','text',['req'=>1]), reg_f('phone','Mobile','phone'), reg_f('gst','GSTIN'), reg_f('address','Address','area')], 'list' => ['name','phone','gst']],
    'item' => ['title' => 'Store Items', 'group' => 'Store', 'old' => 'Store › Category / Item / Item Group', 'r' => 'PCA', 'w' => 'PA', 'fields' => [reg_f('name','Item','text',['req'=>1]), reg_f('category','Category','sel',['opts'=>['Books','Dress','Diary','Stationery','Sports','Furniture','Electrical','Cleaning','Other']]), reg_f('unit','Unit','sel',['opts'=>['Piece','Set','Packet','Kg','Litre','Box']]), reg_f('price','Sale price (₹)','money'), reg_f('minStock','Re-order below','num')],
        'list' => ['name','category','unit','price','minStock'], 'report' => 'stock'],
    'purchase' => ['title' => 'Purchases', 'group' => 'Store', 'old' => 'Store › New Purchase / Purchase Order / Due Bill', 'r' => 'PCA', 'w' => 'PA', 'auto' => ['no', 'PUR'], 'fields' => [reg_f('no','Purchase No'), reg_f('date','Date','date',['req'=>1,'today'=>1]), reg_f('supplier','Supplier','text',['req'=>1]), reg_f('item','Item','text',['req'=>1]), reg_f('qty','Quantity','num',['req'=>1]), reg_f('amount','Amount (₹)','money',['req'=>1]), reg_f('paid','Paid (₹)','money'), reg_f('bill','Supplier bill no')],
        'list' => ['no','date','supplier','item','qty','amount','paid'], 'sum' => ['qty','amount','paid']],
    'issue' => ['title' => 'Item Issue', 'group' => 'Store', 'old' => 'Store › Item Issue / Indent / Transfer', 'r' => 'PCA', 'w' => 'PA', 'auto' => ['no', 'ISS'], 'fields' => [reg_f('no','Issue No'), reg_f('date','Date','date',['req'=>1,'today'=>1]), reg_f('item','Item','text',['req'=>1]), reg_f('qty','Quantity','num',['req'=>1]), reg_f('to','Issued to (person / department)','text',['req'=>1]), reg_f('returnable','Returnable','yn')],
        'list' => ['no','date','item','qty','to'], 'sum' => ['qty']],
    'sale' => ['title' => 'Store Sale (books, dress)', 'group' => 'Store', 'old' => 'Store › New Sale / Store Fee Receipt / Sale Return', 'r' => 'PCA', 'w' => 'PA', 'auto' => ['no', 'SAL'], 'fields' => [reg_f('no','Sale No'), reg_f('date','Date','date',['req'=>1,'today'=>1]), reg_f('student','Student','student'), reg_f('item','Item','text',['req'=>1]), reg_f('qty','Quantity','num',['req'=>1]), reg_f('amount','Amount (₹)','money',['req'=>1]), reg_f('mode','Paid by','sel',$modes), reg_f('returned','Returned','yn')],
        'list' => ['no','date','student','item','qty','amount','mode'], 'sum' => ['qty','amount'], 'print' => 'receipt'],
    'repair' => ['title' => 'Repair & Maintenance', 'group' => 'Store', 'old' => 'Store › Item Repair & Maintenance', 'r' => 'PCA', 'w' => 'PA', 'fields' => [reg_f('date','Date','date',['req'=>1,'today'=>1]), reg_f('item','Item / asset','text',['req'=>1]), reg_f('problem','Problem'), reg_f('vendor','Repaired by'), reg_f('amount','Cost (₹)','money'), reg_f('status','Status','sel',['opts'=>['Sent','Repaired','Not repairable']])],
        'list' => ['date','item','problem','vendor','amount','status'], 'sum' => ['amount']],

    // ---------------- Accounts ----------------
    'acchead' => ['title' => 'Account Heads', 'group' => 'Accounts', 'old' => 'Account Master › Head / Sub Head', 'r' => 'PCA', 'w' => 'PA', 'fields' => [reg_f('name','Head','text',['req'=>1]), reg_f('side','Type','sel',['opts'=>['Income','Expense'],'req'=>1]), reg_f('sub','Sub head')], 'list' => ['name','side','sub']],
    'party' => ['title' => 'Parties & Banks', 'group' => 'Accounts', 'old' => 'Account Master › Party / Bank / Cheque', 'r' => 'PCA', 'w' => 'PA', 'fields' => [reg_f('name','Name','text',['req'=>1]), reg_f('kind','Kind','sel',['opts'=>['Party','Bank','Employee']]), reg_f('phone','Mobile','phone'), reg_f('account','Account no (last 4 only)'), reg_f('opening','Opening balance (₹)','money')], 'list' => ['name','kind','phone','opening']],
    'cashbook' => ['title' => 'Cash Book (income & expense)', 'group' => 'Accounts', 'old' => 'Account › Payment / Receipt / Bill Entry / Bank Deposit / Self Withdrawal', 'r' => 'PCA', 'w' => 'PA', 'auto' => ['no', 'VCH'],
        'fields' => [reg_f('no','Voucher No'), reg_f('date','Date','date',['req'=>1,'today'=>1]), reg_f('side','Entry','sel',['opts'=>['Receipt (money in)','Payment (money out)','Bank deposit','Self withdrawal'],'req'=>1]), reg_f('head','Account head','text',['req'=>1]), reg_f('party','Party'),
            reg_f('amount','Amount (₹)','money',['req'=>1]), reg_f('mode','Mode','sel',$modes), reg_f('ref','Cheque / UTR / bill no'), reg_f('narration','Narration','area')],
        'list' => ['no','date','side','head','party','amount','mode'], 'sum' => ['amount'], 'report' => 'cashbook'],
    'cashverify' => ['title' => 'Cash Verification', 'group' => 'Accounts', 'old' => 'Account › Cash Verification', 'r' => 'PCA', 'w' => 'PA', 'fields' => [reg_f('date','Date','date',['req'=>1,'today'=>1]), reg_f('book','Cash as per book (₹)','money',['req'=>1]), reg_f('counted','Cash counted (₹)','money',['req'=>1]), reg_f('by','Counted by'), reg_f('remarks','Remarks','area')],
        'list' => ['date','book','counted','by']],

    // ---------------- Front office ----------------
    'gatepass' => ['title' => 'Gate Pass', 'group' => 'Front Office', 'old' => 'Gate Pass (Guardian / Employee)', 'r' => 'PCR', 'w' => 'PR', 'auto' => ['no', 'GP'], 'fields' => [reg_f('no','Pass No'), reg_f('date','Date','date',['req'=>1,'today'=>1]), reg_f('time','Time out','time'), reg_f('kind','For','sel',['opts'=>['Student (taken by guardian)','Employee'],'req'=>1]), reg_f('student','Student','student'), reg_f('person','Taken by / employee','text',['req'=>1]), reg_f('relation','Relation'), reg_f('phone','Mobile','phone'), reg_f('reason','Reason','text',['req'=>1]), reg_f('back','Time back','time')],
        'list' => ['no','date','time','kind','student','person','reason'], 'print' => 'gatepass'],
    'visitor' => ['title' => 'Visitors', 'group' => 'Front Office', 'old' => 'Visitor Mgmt', 'r' => 'PCR', 'w' => 'PR', 'fields' => [reg_f('date','Date','date',['req'=>1,'today'=>1]), reg_f('in','Time in','time'), reg_f('name','Visitor','text',['req'=>1]), reg_f('phone','Mobile','phone'), reg_f('kind','Visiting type','sel',['opts'=>['Parent','Vendor','Official','Interview','Other']]), reg_f('meet','To meet'), reg_f('purpose','Purpose'), reg_f('out','Time out','time')],
        'list' => ['date','in','name','phone','kind','meet','purpose']],
    'reminder' => ['title' => 'Reminders & Tasks', 'group' => 'Front Office', 'old' => 'Reminders', 'r' => 'PCAR', 'w' => 'PAR', 'fields' => [reg_f('task','Task','text',['req'=>1]), reg_f('who','Given to','text',['req'=>1]), reg_f('due','Due date','date',['req'=>1]), reg_f('repeat','Repeat','sel',['opts'=>['Once','Daily','Weekly','Monthly','Yearly']]), reg_f('status','Status','sel',['opts'=>['Pending','Done']]), reg_f('remarks','Remarks','area')],
        'list' => ['task','who','due','repeat','status'], 'due' => 'due'],
    'event' => ['title' => 'Events & PTM', 'group' => 'Front Office', 'old' => 'Event/PTM', 'r' => 'PCRT', 'w' => 'PR', 'fields' => [reg_f('date','Date','date',['req'=>1]), reg_f('name','Event / PTM','text',['req'=>1]), reg_f('classes','Classes'), reg_f('incharge','In-charge','teacher'), reg_f('notes','Notes','area')], 'list' => ['date','name','classes','incharge']],
    'feedback' => ['title' => 'Feedback & Complaints', 'group' => 'Front Office', 'old' => 'Feedback', 'r' => 'PC', 'w' => 'PR', 'fields' => [reg_f('date','Date','date',['req'=>1,'today'=>1]), reg_f('from','From'), reg_f('student','Student','student'), reg_f('about','About'), reg_f('text','Feedback','area',['req'=>1]), reg_f('status','Status','sel',['opts'=>['Pending','Resolved']]), reg_f('action','Action taken','area')],
        'list' => ['date','from','student','about','status']],
    'document' => ['title' => 'School Documents', 'group' => 'Front Office', 'old' => 'Document', 'r' => 'PC', 'w' => 'P', 'fields' => [reg_f('name','Document','text',['req'=>1]), reg_f('kind','Type','sel',['opts'=>['Affiliation','Registration','Land','Fire NOC','Building safety','Water','Audit','Other']]), reg_f('number','Number'), reg_f('valid','Valid till','date'), reg_f('where','Kept at / link')],
        'list' => ['name','kind','number','valid','where'], 'due' => 'valid'],
    'liveclass' => ['title' => 'Online Class Links', 'group' => 'Front Office', 'old' => 'Live Classes', 'r' => 'PCT', 'w' => 'PT', 'fields' => [reg_f('date','Date','date',['req'=>1]), reg_f('cls','Class','cls',$cls), reg_f('subject','Subject'), reg_f('teacher','Teacher','teacher'), reg_f('link','Meeting link','text',['req'=>1])], 'list' => ['date','cls','subject','teacher','link']],
    'msgtpl' => ['title' => 'SMS / WhatsApp Templates', 'group' => 'Front Office', 'old' => 'SMS / Whatsapp (templates only — this ERP never auto-sends)', 'r' => 'PCR', 'w' => 'P', 'fields' => [reg_f('name','Template','text',['req'=>1]), reg_f('use','Used for','sel',['opts'=>['Due fee','Absent','Exam marks','Holiday','Enquiry','General']]), reg_f('text','Message','area',['req'=>1])], 'list' => ['name','use']],
    'website' => ['title' => 'Website Content', 'group' => 'Front Office', 'old' => 'Website (news, banners, jobs, disclosures)', 'r' => 'PC', 'w' => 'P', 'fields' => [reg_f('kind','Section','sel',['opts'=>['News','Banner','Gallery','Testimonial','Job post','Mandatory disclosure','Download','Page'],'req'=>1]), reg_f('title','Title','text',['req'=>1]), reg_f('date','Date','date',['today'=>1]), reg_f('body','Text','area'), reg_f('link','Link / file')],
        'list' => ['kind','title','date']],

    // ---------------- Masters ----------------
    'master' => ['title' => 'Masters (house, caste, religion…)', 'group' => 'Masters', 'old' => 'Master › House / Caste / Religion / Occupation / Blood Group / Documents / Consultant / City', 'r' => 'PC', 'w' => 'P', 'fields' => [reg_f('list','List','sel',['opts'=>['House','Caste','Religion','Occupation','Blood group','Document','Consultant','City / Gram / Post','Discount head','Payment mode','Section'],'req'=>1]), reg_f('value','Value','text',['req'=>1]), reg_f('order','Order','num')],
        'list' => ['list','value','order']],

    // ---------------- Old ERP archive (filled by import, read-only) ----------------
    'oldreceipt' => ['title' => 'Old ERP Receipts', 'group' => 'Old ERP Archive', 'old' => 'Fee Collection Report (receipt-wise) — original receipt numbers', 'r' => 'PCA', 'w' => '', 'import' => 'P', 'keyFrom' => ['session','receiptNo'],
        'fields' => [reg_f('session','Session','text',['req'=>1]), reg_f('receiptNo','Receipt No','text',['req'=>1]), reg_f('date','Date','date',['req'=>1]), reg_f('student','Student','student'), reg_f('scholarNo','Scholar No'), reg_f('name','Name','text',['req'=>1]),
            reg_f('cls','Class'), reg_f('section','Section'), reg_f('father','Father'), reg_f('months','Months'), reg_f('total','Total (₹)','money'), reg_f('discount','Discount (₹)','money'), reg_f('lateFee','Late fee (₹)','money'), reg_f('received','Received (₹)','money',['req'=>1]),
            reg_f('mode','Mode'), reg_f('bank','Bank'), reg_f('cheque','Cheque no'), reg_f('status','Status'), reg_f('user','Taken by'), reg_f('remark','Remark')],
        'list' => ['session','receiptNo','date','name','cls','months','total','discount','received','mode','user'], 'sum' => ['total','discount','lateFee','received'], 'print' => 'receipt'],
    'oldfee' => ['title' => 'Old ERP Fee Statement', 'group' => 'Old ERP Archive', 'old' => 'Fee Statement (per student, per session)', 'r' => 'PCA', 'w' => '', 'import' => 'P', 'keyFrom' => ['session','student'],
        'fields' => [reg_f('session','Session','text',['req'=>1]), reg_f('student','Student','student',['req'=>1]), reg_f('scholarNo','Scholar No'), reg_f('name','Name'), reg_f('cls','Class'), reg_f('scheme','Scheme'), reg_f('route','Route'),
            reg_f('oldDue','Old due (₹)','money'), reg_f('billed','Billed (₹)','money'), reg_f('transport','Transport (₹)','money'), reg_f('discount','Discount (₹)','money'), reg_f('otherDiscount','Other discount (₹)','money'), reg_f('adjust','Adjustment (₹)','money'), reg_f('received','Received (₹)','money'), reg_f('due','Net due (₹)','money'), reg_f('status','Status in old ERP')],
        'list' => ['session','student','scheme','billed','discount','received','due','status'], 'sum' => ['billed','transport','discount','otherDiscount','received','due']],
    'oldmarks' => ['title' => 'Old ERP Marks', 'group' => 'Old ERP Archive', 'old' => 'Examination result sheets', 'r' => 'PCT', 'w' => '', 'import' => 'P', 'keyFrom' => ['session','exam','student'],
        'fields' => [reg_f('session','Session','text',['req'=>1]), reg_f('exam','Exam','text',['req'=>1]), reg_f('student','Student','student',['req'=>1]), reg_f('name','Name'), reg_f('cls','Class'), reg_f('roll','Roll no'),
            reg_f('marks','Subject marks','json'), reg_f('total','Total'), reg_f('percent','Percent'), reg_f('grade','Grade'), reg_f('rank','Class rank'), reg_f('attendance','Attendance')],
        'list' => ['session','exam','student','total','percent','grade','rank','attendance']],
    ];
    foreach ($M as $id => &$m) { $m['id'] = $id; $m['fk'] = array_column($m['fields'], null, 'k'); }
    unset($m);
    return $M;
}

function reg_can(array $m, string $role, string $what): bool {
    $s = $what === 'w' ? ($m['w'] ?? '') : ($what === 'import' ? ($m['import'] ?? 'P') : ($m['r'] ?? ''));
    return in_array($role, reg_roles($s), true);
}

// ---------- values ----------
function reg_money($v): ?int {      // rupees in, paise out
    if ($v === '' || $v === null) return null;
    $s = str_replace([',', '₹', ' '], '', (string)$v);
    if (!preg_match('/^-?\d{1,9}(\.\d{1,2})?$/', $s)) throw new InvalidArgumentException('Enter a rupee amount like 1500 or 1500.50.');
    return (int)round(((float)$s) * 100);
}

function reg_clean(array $m, array $in, array $students, bool $import = false): array {
    $out = [];
    foreach ($m['fields'] as $f) {
        $k = $f['k']; $v = $in[$k] ?? '';
        if ($f['type'] === 'json') {
            if ($v === '' || $v === null) { $out[$k] = null; continue; }
            if (!is_array($v)) throw new InvalidArgumentException($f['label'] . ': invalid data.');
            $enc = json_encode($v, JSON_UNESCAPED_UNICODE);
            if (strlen($enc) > 20000) throw new InvalidArgumentException($f['label'] . ' is too large.');
            $out[$k] = $v; continue;
        }
        if (is_int($v) || is_float($v)) $v = (string)$v;
        if (!is_string($v)) throw new InvalidArgumentException($f['label'] . ': invalid value.');
        $v = trim(preg_replace('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/u', '', $v));
        if (mb_strlen($v) > REG_MAX_TEXT) throw new InvalidArgumentException($f['label'] . ' is too long.');
        if ($v === '') { if (!empty($f['req']) && empty($f['auto'])) throw new InvalidArgumentException('Fill in ' . $f['label'] . '.'); $out[$k] = $f['type'] === 'money' ? null : ''; continue; }
        switch ($f['type']) {
            case 'money': $out[$k] = reg_money($v); break;
            case 'num': if (!is_numeric($v)) throw new InvalidArgumentException($f['label'] . ' must be a number.'); $out[$k] = $v + 0; break;
            case 'date':
                $d = DateTimeImmutable::createFromFormat('!Y-m-d', $v);
                if (!$d || $d->format('Y-m-d') !== $v) throw new InvalidArgumentException($f['label'] . ': choose a valid date.');
                $out[$k] = $v; break;
            case 'time': if (!preg_match('/^\d{2}:\d{2}$/', $v)) throw new InvalidArgumentException($f['label'] . ': use HH:MM.'); $out[$k] = $v; break;
            case 'phone': $d = preg_replace('/\D/', '', $v); if (strlen($d) < 10 || strlen($d) > 13) throw new InvalidArgumentException($f['label'] . ': enter a 10-digit mobile.'); $out[$k] = substr($d, -10); break;
            case 'yn': $out[$k] = in_array(strtolower($v), ['yes','y','1','true'], true) ? 'Yes' : 'No'; break;
            case 'sel': case 'cls':
                if (!$import && !in_array($v, $f['opts'], true)) throw new InvalidArgumentException($f['label'] . ': choose from the list.');
                $out[$k] = $v; break;
            case 'student':
                if (!isset($students[$v])) throw new InvalidArgumentException($f['label'] . ': this student is not in the ERP.');
                $out[$k] = $v; break;
            default: $out[$k] = $v;
        }
    }
    return $out;
}

function reg_students(): array {
    $out = [];
    foreach (sch_db()->query("SELECT r, d FROM sch_records WHERE k='student' AND deleted=0") as $row) {
        $s = json_decode((string)$row['d'], true);
        if (is_array($s)) $out[(string)$row['r']] = ['id' => (string)$row['r'], 'name' => (string)($s['name'] ?? ''), 'cls' => (string)($s['cls'] ?? ''), 'section' => (string)($s['section'] ?? ''),
            'adm' => (string)($s['adm'] ?? ''), 'father' => (string)($s['father'] ?? ''), 'phone' => (string)($s['phone'] ?? ''), 'erpId' => (string)($s['erpId'] ?? '')];
    }
    return $out;
}

function reg_rows(string $mod): array {
    $st = sch_db()->prepare("SELECT r, d, ver FROM sch_records WHERE k='regRow' AND r LIKE ? AND deleted=0");
    $st->execute([str_replace(['%', '_'], ['\\%', '\\_'], $mod) . '|%']);
    $out = [];
    foreach ($st as $row) { $d = json_decode((string)$row['d'], true); if (is_array($d)) $out[] = $d; }
    return $out;
}

function reg_session(): string {
    $s = (string)((sch_rec_get('settings', 'main') ?? [])['session'] ?? '');
    $s = str_replace(['–', '—'], '-', trim($s));
    return preg_match('/^(20\d{2})-(?:20)?(\d{2})$/', $s, $m) ? $m[1] . '-' . $m[2] : '';
}

function reg_key_for(array $m, array $row): string {
    $parts = array_map(fn($k) => (string)($row[$k] ?? ''), $m['keyFrom']);
    foreach ($parts as $p) if ($p === '') throw new InvalidArgumentException('Each imported row needs ' . implode(', ', $m['keyFrom']) . '.');
    return substr(hash('sha256', implode("\x1f", $parts)), 0, 24);
}

// ---------- computed reports ----------
function reg_report(string $kind): array {
    if ($kind === 'stock') {
        $items = []; foreach (reg_rows('item') as $r) $items[$r['name']] = ['item' => $r['name'], 'unit' => $r['unit'] ?? '', 'in' => 0, 'issued' => 0, 'sold' => 0, 'min' => $r['minStock'] ?? ''];
        foreach (reg_rows('purchase') as $r) { $n = $r['item']; $items[$n] ??= ['item' => $n, 'unit' => '', 'in' => 0, 'issued' => 0, 'sold' => 0, 'min' => '']; $items[$n]['in'] += (float)($r['qty'] ?? 0); }
        foreach (reg_rows('issue') as $r) { $n = $r['item']; $items[$n] ??= ['item' => $n, 'unit' => '', 'in' => 0, 'issued' => 0, 'sold' => 0, 'min' => '']; $items[$n]['issued'] += (float)($r['qty'] ?? 0); }
        foreach (reg_rows('sale') as $r) { if (($r['returned'] ?? '') === 'Yes') continue; $n = $r['item']; $items[$n] ??= ['item' => $n, 'unit' => '', 'in' => 0, 'issued' => 0, 'sold' => 0, 'min' => '']; $items[$n]['sold'] += (float)($r['qty'] ?? 0); }
        foreach ($items as &$i) { $i['balance'] = $i['in'] - $i['issued'] - $i['sold']; $i['low'] = $i['min'] !== '' && $i['balance'] < (float)$i['min']; } unset($i);
        ksort($items);
        return ['title' => 'Stock Report', 'cols' => [['item','Item'],['unit','Unit'],['in','Purchased'],['issued','Issued'],['sold','Sold'],['balance','In stock'],['min','Re-order below']], 'rows' => array_values($items)];
    }
    if ($kind === 'cashbook') {
        $rows = reg_rows('cashbook'); usort($rows, fn($a, $b) => [$a['date'], $a['no'] ?? ''] <=> [$b['date'], $b['no'] ?? '']);
        $bal = 0; $out = [];
        foreach ($rows as $r) {
            $in = str_starts_with($r['side'], 'Receipt') || str_starts_with($r['side'], 'Self') ? (int)$r['amount'] : 0;
            $outA = str_starts_with($r['side'], 'Payment') || str_starts_with($r['side'], 'Bank') ? (int)$r['amount'] : 0;
            $bal += $in - $outA;
            $out[] = ['date' => $r['date'], 'no' => $r['no'] ?? '', 'head' => $r['head'], 'party' => $r['party'] ?? '', 'in' => $in, 'out' => $outA, 'balance' => $bal];
        }
        return ['title' => 'Cash Book (cash in hand)', 'money' => ['in','out','balance'], 'cols' => [['date','Date'],['no','Voucher'],['head','Head'],['party','Party'],['in','In (₹)'],['out','Out (₹)'],['balance','Balance (₹)']], 'rows' => $out];
    }
    throw new InvalidArgumentException('Unknown report.');
}
