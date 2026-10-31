'use strict';
// Student Master — the old ERP's Add Student, Edit/Update Student, Student Search and Admission Form,
// with its labels, order, lists and checks (copied from schoolerpindia.net, 27 Oct 2026). Data: stumaster_api.php.
const $ = id => document.getElementById(id);
const esc = v => String(v ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
const QS = new URLSearchParams(location.search);
let META = null, MODE = QS.get('m') || 'add', ID = QS.get('id') || '', F = {}, PHOTO = '', ROWS = null, BUSY = false, FLT = {}, SUG = [], SEL = '';
function toast(t) { const el = $('smToast'); el.textContent = t; clearTimeout(toast.t); toast.t = setTimeout(() => { el.textContent = ''; }, 7000); }
async function api(a, body, q) {
  const r = await fetch('stumaster_api.php?a=' + a + (q ? '&' + new URLSearchParams(q) : ''), { method: body ? 'POST' : 'GET', headers: { 'Content-Type': 'application/json', 'X-School-Token': localStorage.getItem('sch_token') || '' }, ...(body ? { body: JSON.stringify(body) } : {}) });
  let d; try { d = await r.json(); } catch (e) { throw Error('The server did not answer properly. Please try again.'); }
  if (!r.ok || !d.ok) throw Error(d.error || 'Could not complete this.');
  return d;
}
const MONTHS = [['4', 'April'], ['5', 'May'], ['6', 'June'], ['7', 'July'], ['8', 'August'], ['9', 'September'], ['10', 'October'], ['11', 'November'], ['12', 'December'], ['1', 'January'], ['2', 'February'], ['3', 'March']];
// The old form, row by row: [label, field, type, required]. Types: t text, d date (DD/MM/YYYY), a textarea, s:<list> select, cls class, m months, ro read-only.
const H = t => ['H', t];
const FORM = [
  H('Admission Details'),
  [['RFID(UID)', 'rfid_no', 't'], null],
  [['Admission As', 'admtype', 's:admtype'], ['Scholar No', 'rollno', 't', 1]],
  [['School', '_school', 'ro'], ['Student Type', 'stutype', 's:stutype']],
  [['Date of Admission', 'cdate', 'd'], ['Admitted Class', 'aclsid', 's:lastclass']],
  [['Date of Joining', 'date_ofjoin', 'd'], ['Session', '_session', 'ro']],
  [['Child Id', 'childid', 't'], ['Family Id', 'familyid', 't']],
  [['Aadhar Card', 'adharcard', 't'], null],
  [['Enrollment No', 'enrollno', 't'], ['Pen No', 'hsrollno', 't']],
  [['Roll No', 'crollno', 't'], null],
  [['House', 'houseid', 's:houseid'], ['Medium', 'smedium', 's:smedium']],
  [['Custom Text 1', 'custom_text1', 't'], ['Custom Text 2', 'custom_text2', 't']],
  [['Custom Text 3', 'custom_text3', 't'], ['Custom Text 4', 'custom_text4', 't']],
  [['Custom Text 5', 'custom_text5', 't'], ['Custom Text 6', 'custom_text6', 't']],
  H('Scholarship Section'),
  [['Bank Name', 'schbname', 't'], ['Account No', 'accountno', 't']],
  [['IFSC Code', 'ifsc', 't'], ['Account Holder Name', 'ahldname', 't']],
  [['Scholarship Name', 'schname', 't'], ['SSID', 'ssid', 't']],
  H('Student Details'),
  [['Name', 'name', 't', 1], ['Date of Birth(DD/MM/YYYY)', 'dob', 'd']],
  [['Name in Hindi', 'hname', 't'], null],
  [['Current Class', 'clsid', 'cls', 1], ['Section', 'secid', 's:secid', 1]],
  [['Stream', 'egroup', 's:egroup'], ['Optional Sub', 'optsubj', 's:optsubj']],
  [['Gender', 'gender', 's:gender', 1], ['Religion', 'religion', 's:religion']],
  [['Caste', 'stucast', 's:stucast'], ['Category', 'cast', 's:cast', 1]],
  [['Handicapped', 'handicp', 's:handicp'], ['Student Mother Tongue', 'mother_tongue', 't']],
  H('Student Place of Birth ( City & State )'),
  [['City', 'dob_city', 't'], ['State', 'dob_state', 's:dob_state']],
  [['Minority (Y/N)', 'minority', 's:minority'], ['Student Nationality', 'snationality', 't']],
  [['Student Blood Group', 'blood_group', 's:blood_group'], null],
  H('Family Details'),
  [["Father's Name", 'fname', 't', 1, 'Shri'], ["Mother's Name", 'mname', 't', 1, 'Smt.']],
  [["Father's Name (In Hindi)", 'hfname', 't'], ["Mother's Name(In Hindi)", 'hmname', 't']],
  [['Fathers DOB(DD/MM/YYYY)', 'fdob', 'd'], ['Mothers DOB(DD/MM/YYYY)', 'mdob', 'd']],
  [['Fathers Qualification', 'fqualif', 't'], ['Mothers Qualification', 'mqualif', 't']],
  [["Father's Occupation", 'fathoccu', 's:fathoccu'], ["Mother's Occupation", 'mothoccu', 's:mothoccu']],
  [['Fathers Office Name', 'foffice_name', 't'], ['Mothers Office Name', 'moffice_name', 't']],
  [['Fathers Designation', 'fdesignation', 't'], ['Mothers Designation', 'mdesignation', 't']],
  [['Fathers Office Address', 'foffice_add', 'a'], ['Mothers Office Address', 'moffice_add', 'a']],
  [["Father's Mobile No", 'fmobno', 't', 1], ["Mother's Mobile No", 'mothmobno', 't']],
  [['Date of Anniversary', 'fdoa', 'd'], ['Total Family Income Per Annual (in lacs)', 'fam_incom', 't']],
  [['Student Fathers Email id', 'femail', 't'], ['Student Mothers Email id', 'memail', 't']],
  [['SMS/Whatsapp No.', 'telphno', 't', 1], ['Email Id', 'email', 't']],
  H('Address'),
  [['Longitude', 'longitude', 't'], ['Laptitude', 'laptitude', 't']],
  [['Address', 'paddr', 'a'], ['Landmark Stoppage', 'mcgram', 't']],
  [['City Gram Post', 'croad', 't'], ['District', 'ctygram', 't']],
  [['Pin Code', 'cpincode', 't'], ['State', 'cstate', 's:cstate']],
  H('Details of Local Guardian'),
  [['Guardian Name', 'lg_name', 't'], ['Relation with student', 'lg_relation', 't']],
  [['contact number', 'lg_contact', 't'], ['Address', 'lg_paddr', 'a']],
  [['City', 'lg_city', 't'], ['Pincode', 'lg_pincode', 't']],
  H('Previous School Information'),
  [['School Name', 'pschname', 's:pschname'], ['City', 'pscity', 't']],
  [['Last Class', 'schlstclass', 's:lastclass'], ['Status', 'schstus', 's:schstus']],
  H('Brother/Sister Studying in the School'),
  [['Discount', 'bsisdisc', 's:bsisdisc'], null],
  ['SIB'],
  H('Fee Details'),
  [['Facilities', 'faclty', 's:faclty'], ['Route Hostel', 'routeid', 's:routeid']],
  [['Vehicle', 'vehicle_id', 's:vehicle_id'], ['Account Type', 'account_type', 's:account_type']],
  [['Schemes', 'feeapply', 's:feeapply'], ['SMS Category', 'smscategry', 's:smscategry']],
  ['M', 'Fees Month', 'fee_month'],
  ['M', 'Transport Month', 'transport_month'],
  [['Room No', 'roomno', 't'], ['Bed', 'bedno', 't']],
  [['Locker', 'lockerno', 't'], ['Token No', 'tokenno', 't']],
  H('Student Details'),
  ['PHOTO'],
  [['Student Status', 'cstatus', 's:cstatus'], ['Current Session Status', 'scount_status', 's:scount_status']],
  [['Date of Leaving', 'date_ofleave', 'd'], null],
  [['Remarks', 'remarks', 'a'], ['Consultant', 'consultant_id', 't']],
  H('Particulars regarding Xth Board Exam (Applicable for class XI only)'),
  ['ACAD'],
  H('Document Section'),
  [['Birth Certificate/ Last Marsksheet', 'doc1', 's:doc'], ['Adhar Card', 'doc2', 's:doc']],
  [['SSSM ID', 'doc3', 's:doc'], ['Document 4', 'doc4', 's:doc']]
];
const ALL = FORM.flatMap(r => Array.isArray(r[0]) ? r.filter(Boolean) : []).filter(c => !c[1].startsWith('_'));
function today() { const d = new Date(); return String(d.getDate()).padStart(2, '0') + '/' + String(d.getMonth() + 1).padStart(2, '0') + '/' + d.getFullYear(); }
function optsFor(type, v) {
  const list = type === 'cls' ? META.classes : (META.opts[type.slice(2)] || []);
  const out = [...list]; if (v != null && !out.includes(v) ) out.unshift(v);   // a value saved before is always shown as it is (even blank)
  return out;
}
const longSes = s => String(s || '').replace(/^(20\d\d)-(\d\d)$/, '$1-20$2');
function blank() {
  // A new student starts like the old form: first item of each list, Date of Admission today.
  const f = {}; ALL.forEach(([, n, t]) => { f[n] = t.startsWith('s:') ? ((META.opts[t.slice(2)] || [])[0] || '') : ''; });
  Object.assign(f, { cdate: today(), clsid: '', secid: '', gender: 'Male', cstatus: 'Active', scount_status: 'Active', fee_month: MONTHS.map(m => m[0]), transport_month: [] });
  return f;
}
function cell(c) {
  if (!c) return '<td class="l"></td><td class="c"></td>';
  const [label, n, t, req, pre] = c, v = n === '_school' ? META.school : n === '_session' ? longSes(META.session) : (F[n] ?? ''), ro = !META.canEdit || t === 'ro' ? ' disabled' : '';
  let ctl;
  if (t.startsWith('s:') || t === 'cls') ctl = `<select id="f_${n}" name="${n}"${ro}>${optsFor(t, v).map(o => `<option value="${esc(o)}"${o === v ? ' selected' : ''}>${esc(o === '' && req ? 'Select' : o)}</option>`).join('')}</select>`;
  else if (t === 'a') ctl = `<textarea id="f_${n}" name="${n}"${ro}>${esc(v)}</textarea>`;
  else ctl = `<input type="text" id="f_${n}" name="${n}" value="${esc(v)}"${t === 'd' ? ' placeholder="DD/MM/YYYY" maxlength="10"' : ''}${t === 'ro' ? ' readonly' : ro} autocomplete="off">`;
  return `<td class="l${req ? ' req' : ''}">${esc(label)}${req ? '*' : ''}${pre ? ' ' + esc(pre) : ''}</td><td class="c">${ctl}</td>`;
}
function siblings() {
  if (!ROWS) return '<tr><td colspan="4" class="note">Opening…</td></tr>';
  const ph = new Set([F.telphno, F.fmobno].filter(x => x && x.length >= 10));
  const sib = ROWS.filter(r => r.id !== ID && (ph.has(r.telphno) || ph.has(r.fmobno)));
  return `<tr><td colspan="4"><table class="sib" style="margin:4px auto;min-width:60%"><tr><th>Sno.</th><th>Scholar No</th><th>Relation</th><th>Name</th><th>Class</th><th>Section</th></tr>${sib.map((s, i) => `<tr><td>${i + 1}</td><td>${esc(s.rollno)}</td><td>${s.gender === 'Female' ? 'Sister' : 'Brother'}</td><td>${esc(s.name)}</td><td>${esc(s.clsid)}</td><td>${esc(s.secid)}</td></tr>`).join('') || '<tr><td colspan="6" class="note">No brother or sister with the same mobile number.</td></tr>'}</table></td></tr>`;
}
function months(label, n) {
  const sel = new Set(F[n] || []), ro = META.canEdit ? '' : ' disabled';
  return `<tr><td class="l">${esc(label)}</td><td colspan="3" class="c months">${MONTHS.map(([v, m]) => `<label><input type="checkbox" name="${n}" value="${v}"${sel.has(v) ? ' checked' : ''}${ro}>${m}</label>`).join('')}</td></tr>`;
}
function formHtml() {
  const rows = FORM.map(r => {
    if (r[0] === 'H') return `<tr><td colspan="3"><div class="h">${esc(r[1])}</div></td><td></td></tr>`;
    if (r[0] === 'SIB') return siblings();
    if (r[0] === 'M') return months(r[1], r[2]);
    if (r[0] === 'PHOTO') return `<tr><td class="l">Photo</td><td class="c"><img class="photo" id="smPhoto" alt="" src="${esc(PHOTO)}" ${PHOTO ? '' : 'style="visibility:hidden"'}>${META.canEdit ? `<input type="file" accept="image/jpeg,image/png" onchange="pickPhoto(this.files[0])"><div class="note">Files Supported : JPG | PNG${ID ? '' : ' — save the student first'}</div>` : ''}</td><td class="l"></td><td class="c"></td></tr>`;
    if (r[0] === 'ACAD') return `<tr><td colspan="4"><table class="acad" style="width:100%"><tr><th>Qualification</th><th>Board/University</th><th>Roll No</th><th>Institute</th><th>Year</th><th>Obt Marks</th><th>Max. Marks</th></tr><tr><td>10th</td>${['ac_name', 'ac_rollno', 'ac_inst', 'ac_year', 'ac_obtmarks', 'ac_maxmarks'].map(n => `<td><input type="text" id="f_${n}" name="${n}" value="${esc(F[n] || '')}"${META.canEdit ? '' : ' disabled'}></td>`).join('')}</tr></table></td></tr>`;
    return `<tr>${cell(r[0])}${cell(r[1])}</tr>`;
  }).join('');
  return `<div class="sm"><h3>${MODE === 'add' ? 'Add Student' : 'Student Edit/Update'}</h3>
    <form id="smForm" onsubmit="event.preventDefault();save()"><table class="f">${rows}</table>
    <div style="text-align:center" class="np">${META.canEdit ? `<button class="btn" type="submit" ${BUSY ? 'disabled' : ''}>Submit</button>` : ''}
    ${ID ? `<a class="btn alt" href="?m=print&id=${encodeURIComponent(ID)}" target="_blank">English Print</a>` : ''} <a class="btn alt" href="?m=list">Edit/Update Student</a></div></form></div>`;
}
function collect() {
  const f = {}; ALL.forEach(([, n]) => { const el = $('f_' + n); if (el) f[n] = el.value.trim(); });
  ['ac_name', 'ac_rollno', 'ac_inst', 'ac_year', 'ac_obtmarks', 'ac_maxmarks'].forEach(n => { f[n] = $('f_' + n).value.trim(); });
  ['fee_month', 'transport_month'].forEach(n => { f[n] = [...document.querySelectorAll(`input[name="${n}"]:checked`)].map(x => x.value); });
  return f;
}
// The old ERP's validateForm(): required fields and 10-digit mobiles, before anything is sent.
function check(f) {
  for (const [, n, , req] of ALL) if (req && !f[n]) { const el = $('f_' + n); if (el) el.focus(); return 'Please fill ' + ALL.find(c => c[1] === n)[0] + '.'; }
  for (const n of ['fmobno', 'telphno', 'mothmobno']) if (f[n] && f[n] !== F[n] && !/^[6-9]\d{9}$/.test(f[n])) { $('f_' + n).focus(); return 'Mobile number must be 10 digits.'; }
  return '';
}
async function save() {
  if (BUSY) return; const f = collect(), e = check(f); if (e) return toast(e);
  BUSY = true;
  try {
    const d = await api('save', { id: ID, f });
    if (d.added) { toast('Student added. Scholar No ' + f.rollno + '.'); ID = d.id; MODE = 'edit'; history.replaceState(null, '', '?m=edit&id=' + encodeURIComponent(ID)); }
    else toast(d.changed ? 'Saved.' : 'Nothing was changed.');
    F = (await api('get', null, { id: ID })).f;
  } catch (er) { toast(er.message); }
  BUSY = false; render();
}
async function pickPhoto(file) {
  if (!file) return; if (!ID) return toast('Save the student first, then add the photo.');
  try {
    const img = await createImageBitmap(file), k = Math.min(1, 300 / Math.max(img.width, img.height)), c = document.createElement('canvas');
    c.width = Math.round(img.width * k); c.height = Math.round(img.height * k); c.getContext('2d').drawImage(img, 0, 0, c.width, c.height);
    const data = c.toDataURL('image/jpeg', 0.85); await api('photo', { id: ID, data }); PHOTO = data; toast('Photo saved.'); render();
  } catch (e) { toast(e.message || 'This file is not a picture.'); }
}
// ---------- Edit/Update Student (studet.php → studet1.php) ----------
const FILTERS = [[['Student Type', 'stutype', ['All', 'New Student', 'Old Student']], ['Admission As', 'admtype', null]], [['Class', 'clsid', null], ['Section', 'secid', null]],
  [['Stream', 'egroup', null], ['Medium', 'smedium', null]], [['Image Upload Status', 'image', ['All', 'Y', 'N']], ['Schemes', 'feeapply', null]], [['Category', 'cast', null], null],
  [['Facilities', 'faclty', null], ['Route Hostel', 'routeid', null]], [['Student Status', 'cstatus', null], ['Current Session Status', 'scount_status', null]],
  [['Sorting', 'csort', ['Sort by Name', 'Sort by  Scholar No', 'Sort by Category', 'Sort by Roll No', 'Sort by Admission Date', 'Sort by Class & Name', 'Sort by Gender']], null]];
function fopts(n, fixed) {
  if (fixed) return fixed;
  if (n === 'clsid') return ['All', ...META.classes];
  const vals = new Set((META.opts[{ secid: 'secid', admtype: 'admtype', egroup: 'egroup', smedium: 'smedium', feeapply: 'feeapply', cast: 'cast', faclty: 'faclty', routeid: 'routeid', cstatus: 'cstatus', scount_status: 'scount_status' }[n]] || []));
  (ROWS || []).forEach(r => { if (r[n]) vals.add(r[n]); });
  return ['All', ...vals];
}
const PHOTOS = {};
function listRows() {
  const g = k => FLT[k] || (k === 'csort' ? 'Sort by Name' : 'All');
  let rows = (ROWS || []).filter(r => ['stutype', 'admtype', 'clsid', 'secid', 'egroup', 'smedium', 'feeapply', 'cast', 'faclty', 'routeid', 'cstatus', 'scount_status'].every(k => g(k) === 'All' || String(r[k]).toLowerCase() === g(k).toLowerCase())
    && (g('image') === 'All' || (g('image') === 'Y') === !!r._photo));
  const s = g('csort'), num = v => parseFloat(v) || 0, dt = v => { const m = /^(\d\d)[\/-](\d\d)[\/-](\d{4})$/.exec(v || ''); return m ? m[3] + m[2] + m[1] : ''; };
  const by = { 'Sort by Name': r => r.name.toLowerCase(), 'Sort by  Scholar No': r => r.rollno.toLowerCase(), 'Sort by Category': r => r.cast + r.name, 'Sort by Roll No': r => String(num(r.crollno)).padStart(6, '0') + r.name, 'Sort by Admission Date': r => dt(r.cdate) + r.name, 'Sort by Class & Name': r => r.name, 'Sort by Gender': r => r.gender + r.name }[s];
  rows.sort((a, b) => by(a) < by(b) ? -1 : by(a) > by(b) ? 1 : 0);
  return rows;
}
function table(rows, title) {
  const order = META.classes, groups = {};
  rows.forEach(r => (groups[r.clsid] = groups[r.clsid] || []).push(r));
  const cols = ['Sr No', 'Edit/Update', 'Call', 'whatsapp', 'Photo', 'Sec', 'Adm No', 'RFID(UID)', 'Roll No', 'Child Id', 'Student Name', 'Gender', "Father's Name", "Father's Mobile No", 'Schemes', 'Category', 'Facilities', 'Route Hostel', 'Status', 'Current Session Status', 'English Print'];
  return `<div class="sch">${esc(META.school)}</div><div class="sub">${esc(title)}</div>` + Object.keys(groups).sort((a, b) => order.indexOf(a) - order.indexOf(b)).map(c => `<div class="cl">Class:- ${esc(c)}</div><div class="lw"><table class="lst"><tr>${cols.map(h => `<th>${esc(h)}</th>`).join('')}</tr>${groups[c].map((r, i) => `<tr><td>${i + 1}</td><td><a href="?m=edit&id=${encodeURIComponent(r.id)}">Edit/Update</a></td>
    <td>${r.fmobno ? `<a class="call" href="tel:${esc(r.fmobno)}">&#9990; Call</a>` : ''}</td><td>${r.telphno ? `<a class="wa" target="_blank" rel="noopener" href="https://wa.me/91${esc(r.telphno)}">Whatsapp</a>` : ''}</td>
    <td>${PHOTOS[r.id] ? `<img class="thumb" alt="" src="${esc(PHOTOS[r.id])}">` : ''}</td><td>${esc(r.secid)}</td><td>${esc(r.rollno)}</td><td>${esc(r.rfid_no)}</td><td>${esc(r.crollno)}</td><td>${esc(r.childid)}</td>
    <td class="t">${esc(r.name)}</td><td>${esc(r.gender)}</td><td class="t">${esc(r.fname)}</td><td>${esc(r.fmobno)}</td><td>${esc(r.feeapply)}</td><td>${esc(r.cast)}</td><td>${esc(r.faclty)}</td><td class="t">${esc(r.routeid)}</td><td>${esc(r.cstatus)}</td><td>${esc(r.scount_status)}</td>
    <td><a href="?m=print&id=${encodeURIComponent(r.id)}" target="_blank">Print</a></td></tr>`).join('')}</table></div>`).join('') + (rows.length ? '' : '<p>No student matches.</p>');
}
function listHtml() {
  const sel = (n, o) => `<select onchange="FLT['${n}']=this.value">${o.map(x => `<option${(FLT[n] || o[0]) === x ? ' selected' : ''}>${esc(x)}</option>`).join('')}</select>`;
  const rows = FILTERS.map(r => `<tr>${r.map(c => c ? `<td class="l">${esc(c[0])}</td><td>${sel(c[1], fopts(c[1], c[2]))}</td>` : '<td></td><td></td>').join('')}</tr>`).join('');
  const shown = FLT._go ? listRows() : null;
  return `<div class="sm"><h3>Edit/Update Student</h3><div class="np"><table class="flt"><tr><td class="l">Session</td><td><input type="text" value="${esc(longSes(META.session))}" readonly></td><td class="l">School</td><td><input type="text" value="${esc(META.school)}" readonly></td></tr>${rows}</table>
    <button class="btn" onclick="FLT._go=1;render();loadPhotos()">Submit</button> ${shown ? `<button class="btn alt" onclick="excel()">Excel</button> <button class="btn alt" onclick="window.print()">Print</button>` : ''} ${META.canEdit ? '<a class="btn alt" href="?m=add">Add Student</a>' : ''}</div>
    ${META.canEdit ? `<div class="np" style="margin:14px 0;padding:10px;border:1px solid #F0C164;background:#FFFBF0"><b>Import from old ERP</b> — file <b>12-Old-ERP-Student-Details.json</b>. Fills Section, DOB, Mother's Name, Address, SMS No and Child Id only where they are empty. <input type="file" accept=".json" onchange="readFill(this.files[0])">${FILL ? `<p>${FILL.p.students} students will get: ${Object.entries(FILL.p.fields).map(([k, n]) => esc(k) + ' ' + n).join(', ') || 'nothing (already filled)'}.${FILL.p.mismatch.length ? ' Not matching Scholar No: ' + esc(FILL.p.mismatch.join(', ')) + '.' : ''}</p>${FILL.p.students ? '<button class="btn" style="margin-top:4px" onclick="applyFill()">Fill now</button>' : ''}` : ''}</div>` : ''}
    ${shown ? table(shown, 'Stream: ' + (FLT.egroup || 'All')) : ''}</div>`;
}
async function loadPhotos(rows) {
  const need = (rows || (FLT._go ? listRows() : [])).filter(r => r._photo && !(r.id in PHOTOS)).map(r => r.id);
  for (let i = 0; i < need.length; i += 40) {
    try { const d = await api('photos', null, { ids: need.slice(i, i + 40).join(',') }); need.slice(i, i + 40).forEach(id => { PHOTOS[id] = d.photos[id] || ''; }); render(); } catch (e) { return; }
  }
}
function excel() {
  const rows = listRows(), cols = [['rollno', 'Adm No'], ['rfid_no', 'RFID(UID)'], ['crollno', 'Roll No'], ['childid', 'Child Id'], ['name', 'Student Name'], ['clsid', 'Class'], ['secid', 'Sec'], ['gender', 'Gender'], ['fname', "Father's Name"], ['mname', "Mother's Name"], ['fmobno', "Father's Mobile No"], ['telphno', 'SMS/Whatsapp No.'], ['dob', 'DOB'], ['feeapply', 'Schemes'], ['cast', 'Category'], ['faclty', 'Facilities'], ['routeid', 'Route Hostel'], ['cstatus', 'Status'], ['scount_status', 'Current Session Status'], ['paddr', 'Address']];
  const txt = [cols.map(c => c[1]), ...rows.map(r => cols.map(c => r[c[0]] ?? ''))].map(r => r.map(c => '"' + String(c).replace(/"/g, '""') + '"').join(',')).join('\r\n');
  const a = document.createElement('a'); a.href = URL.createObjectURL(new Blob(['﻿' + txt], { type: 'text/csv' })); a.download = 'Edit Update Student.csv'; a.click();
}
let FILL = null;
async function readFill(file) {
  try { const d = JSON.parse(await file.text()); if (d.kind !== 'studentFill') throw Error('This is not the old ERP student details file.'); FILL = { rows: d.rows, p: await api('fill', { rows: d.rows }) }; } catch (e) { FILL = null; toast(e.message); }
  render();
}
async function applyFill() {
  try { const p = await api('fill', { rows: FILL.rows, apply: true }); toast(p.students + ' students updated from the old ERP.'); FILL = null; ROWS = (await api('list')).rows; } catch (e) { toast(e.message); }
  render();
}
// ---------- Student Search (student_search.php) ----------
const BY = [['name', 'Student Name'], ['fmobno', 'Mobile No'], ['fname', "Father's Name"], ['rollno', ' Scholar No']];
function searchHtml() {
  const q = (FLT.q || '').trim().toLowerCase(), by = FLT.by || 'name';
  SUG = q && ROWS ? ROWS.filter(r => String(by === 'fmobno' ? r.fmobno + ' ' + r.telphno : r[by]).toLowerCase().includes(q)).slice(0, 30) : [];
  const pick = ROWS && SEL ? ROWS.find(r => r.id === SEL) : null;
  return `<div class="sm"><h3>Student Search</h3><table class="flt"><tr><td class="l">Search By</td><td><select onchange="FLT.by=this.value;SEL='';render()">${BY.map(([v, l]) => `<option value="${v}"${by === v ? ' selected' : ''}>${esc(l)}</option>`).join('')}</select></td>
    <td class="l">Search For</td><td><input type="text" id="smQ" value="${esc(FLT.q || '')}" oninput="FLT.q=this.value;SEL='';render()" autocomplete="off"></td></tr></table>
    ${SUG.length && !pick ? `<div class="lw"><table class="lst" style="max-width:700px"><tr><th>Sno.</th><th>Scholar No</th><th>Student Name</th><th>Class</th><th>Father's Name</th><th>Mobile No</th></tr>${SUG.map((r, i) => `<tr style="cursor:pointer" onclick="SEL='${esc(r.id)}';render()"><td>${i + 1}</td><td>${esc(r.rollno)}</td><td class="t"><a>${esc(r.name)}</a></td><td>${esc(r.clsid)}-${esc(r.secid)}</td><td class="t">${esc(r.fname)}</td><td>${esc(r.fmobno)}</td></tr>`).join('')}</table></div>` : ''}
    ${pick ? table([pick], '') : ''}</div>`;
  if (pick && pick._photo && !(pick.id in PHOTOS)) setTimeout(() => loadPhotos([pick]));
}
// ---------- Admission Form print (admission_form_value.php) ----------
function printHtml() {
  const v = n => n === '_school' ? META.school : n === '_session' ? longSes(META.session) : Array.isArray(F[n]) ? F[n].map(m => (MONTHS.find(x => x[0] === m) || [, m])[1]).join(', ') : (F[n] || '');
  let out = '', pairs = [];
  const flush = () => { if (pairs.length) out += `<table>${pairs.map(p => `<tr>${p.map(c => c ? `<td class="k">${esc(c[0])}</td><td class="v">${esc(v(c[1]))}</td>` : '<td></td><td></td>').join('')}</tr>`).join('')}</table>`; pairs = []; };
  FORM.forEach(r => {
    if (r[0] === 'H') { flush(); out += `<div class="sec">${esc(r[1])} :</div>`; }
    else if (r[0] === 'M') pairs.push([[r[1], r[2]], null]);
    else if (r[0] === 'ACAD') { pairs.push([['10th Board/University', 'ac_name'], ['Roll No', 'ac_rollno']], [['Institute', 'ac_inst'], ['Year', 'ac_year']], [['Obt Marks', 'ac_obtmarks'], ['Max. Marks', 'ac_maxmarks']]); }
    else if (Array.isArray(r[0]) || Array.isArray(r[1])) pairs.push(r);
  }); flush();
  return `<div class="ad"><div class="top"><div></div><div><div class="nm">${esc(META.school)}</div><div class="adr">Jagannathpur, Near Sikhdi Khandak, Bhadohi - 221403, UP</div></div>${PHOTO ? `<img alt="" src="${esc(PHOTO)}">` : '<div></div>'}</div>
    <div class="tt">Admission Form ( ${esc(longSes(META.session))} )</div>${out}<div class="sign"><span>Signature of Parent</span><span>Signature of Principal</span></div>
    <div class="np" style="text-align:center"><button class="btn" onclick="window.print()" style="background:#2d353c;color:#fff;border:0;padding:7px 14px;margin-top:20px">Print</button></div></div>`;
}
function render() {
  if (!META) return;
  const html = MODE === 'list' ? listHtml() : MODE === 'search' ? searchHtml() : MODE === 'print' ? printHtml() : formHtml();
  const q = document.activeElement && document.activeElement.id === 'smQ';
  $('smRoot').innerHTML = html;
  if (q) { const s = $('smQ'); s.focus(); s.setSelectionRange(s.value.length, s.value.length); }
}
async function boot() {
  try {
    META = await api('meta');
    if ((MODE === 'edit' || MODE === 'print') && ID) { const d = await api('get', null, { id: ID }); F = d.f; PHOTO = d.photo; }
    else if (MODE === 'edit' || MODE === 'print') MODE = 'list';
    else F = blank();
    if (MODE === 'add' && !META.canEdit) MODE = 'list';
    render();
    ROWS = (await api('list')).rows; render();
  } catch (e) { $('smRoot').innerHTML = `<div class="sm"><h3>Student Master</h3><p>${esc(e.message)}</p></div>`; }
}
boot();
