<?php
// ============================================================================
//  Student Master — the old ERP's Add Student / Edit-Update Student / Student
//  Search / Admission Form, field for field (27 Oct 2026).
//  Owner: "bilkul difference na feel ho ke mai purane software pe work kar
//  raha hu ya naye par".
//
//  One form, the old ERP's field names (rollno, clsid, fname, fmobno, …). Each
//  old field is kept in exactly one place:
//    's' → the student record (k='student'; used by every screen of the ERP)
//    'i' → the Student Full Record (School Office register 'studinfo')
//    'x' → studinfo.extra under the old ERP's own label (as the import did)
//  Principal saves; Principal and Chairman read. Old version of an edited
//  Full Record goes to regHist. Nothing is deleted here.
//
//   GET  ?a=meta                       options (old ERP lists + values in use)
//   GET  ?a=list&<old filters>         Edit/Update Student list
//   GET  ?a=get&id=                    one student, all old fields
//   POST ?a=save {id?, f:{…}}          add or update (Principal)
//   GET  ?a=photos&ids=a,b            list thumbnails (40 at a time)
//   POST ?a=photo {id, data}           student photo (Principal), one stored copy
// ============================================================================
declare(strict_types=1);
require __DIR__ . '/inc.php';
require __DIR__ . '/registers_lib.php';

$a = (string)($_GET['a'] ?? 'meta');
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
try { $db = sch_db(); } catch (Throwable $e) { sch_fail('Server is not reachable right now. Please try again in a few minutes.', 503); }
$u = sch_auth(); $role = (string)$u['role']; $uid = sch_uid_of($u);
if (!in_array($role, ['principal', 'chairman'], true)) sch_fail('Student Master is open for the Principal and Chairman.', 403);
$in = [];
if ($method === 'POST') { $raw = file_get_contents('php://input', false, null, 0, 900001) ?: ''; $in = json_decode($raw, true); if (!is_array($in)) sch_fail('Bad request.'); }

/** old form field => [store, key]. 's' student record, 'i' studinfo field, 'x' studinfo.extra label. */
const SM_MAP = [
    'rollno' => ['s', 'adm'], 'name' => ['s', 'name'], 'clsid' => ['s', 'cls'], 'secid' => ['s', 'section'], 'fname' => ['s', 'father'], 'mname' => ['s', 'mother'],
    'telphno' => ['s', 'phone'], 'paddr' => ['s', 'address'], 'dob' => ['s', 'dob'], 'childid' => ['s', 'childId'], 'faclty' => ['s', 'facilities'],
    'rfid_no' => ['i', 'rfid'], 'admtype' => ['i', 'admAs'], 'stutype' => ['i', 'stuType'], 'cdate' => ['i', 'doa'], 'aclsid' => ['i', 'admittedCls'], 'adharcard' => ['i', 'aadhar'],
    'enrollno' => ['i', 'enrollNo'], 'hsrollno' => ['i', 'penNo'], 'crollno' => ['i', 'rollNo'], 'familyid' => ['i', 'familyId'], 'houseid' => ['i', 'house'], 'smedium' => ['i', 'medium'],
    'schbname' => ['i', 'bankName'], 'accountno' => ['i', 'bankAc'], 'ifsc' => ['i', 'ifsc'], 'schname' => ['i', 'scholarship'], 'ssid' => ['i', 'ssid'], 'hname' => ['i', 'nameHi'],
    'egroup' => ['i', 'stream'], 'optsubj' => ['i', 'optSub'], 'gender' => ['i', 'gender'], 'religion' => ['i', 'religion'], 'stucast' => ['i', 'caste'], 'cast' => ['i', 'category'],
    'handicp' => ['i', 'handicapped'], 'mother_tongue' => ['i', 'motherTongue'], 'dob_city' => ['i', 'dobCity'], 'dob_state' => ['i', 'dobState'], 'minority' => ['i', 'minority'],
    'snationality' => ['i', 'nationality'], 'blood_group' => ['i', 'blood'], 'hfname' => ['i', 'fatherHi'], 'hmname' => ['i', 'motherHi'], 'fqualif' => ['i', 'fatherQual'],
    'mqualif' => ['i', 'motherQual'], 'fathoccu' => ['i', 'fatherOcc'], 'mothoccu' => ['i', 'motherOcc'], 'fmobno' => ['i', 'fatherMobile'], 'mothmobno' => ['i', 'motherMobile'],
    'email' => ['i', 'email'], 'fam_incom' => ['i', 'income'], 'mcgram' => ['i', 'landmark'], 'croad' => ['i', 'village'], 'ctygram' => ['i', 'district'], 'cpincode' => ['i', 'pin'],
    'cstate' => ['i', 'state'], 'lg_name' => ['i', 'guardian'], 'lg_relation' => ['i', 'guardianRel'], 'lg_contact' => ['i', 'guardianPhone'], 'pschname' => ['i', 'prevSchool'],
    'pscity' => ['i', 'prevCity'], 'schlstclass' => ['i', 'lastClass'], 'feeapply' => ['i', 'scheme'], 'routeid' => ['i', 'route'], 'vehicle_id' => ['i', 'vehicle'],
    'cstatus' => ['x', 'Student Status'], 'scount_status' => ['i', 'status'], 'date_ofleave' => ['i', 'leaving'], 'remarks' => ['i', 'remarks'],
    'date_ofjoin' => ['x', 'Date of Joining'], 'custom_text1' => ['x', 'Custom Text 1'], 'custom_text2' => ['x', 'Custom Text 2'], 'custom_text3' => ['x', 'Custom Text 3'],
    'custom_text4' => ['x', 'Custom Text 4'], 'custom_text5' => ['x', 'Custom Text 5'], 'custom_text6' => ['x', 'Custom Text 6'], 'ahldname' => ['x', 'Account Holder Name'],
    'fdob' => ['x', 'Fathers DOB'], 'mdob' => ['x', 'Mothers DOB'], 'foffice_name' => ['x', 'Fathers Office Name'], 'moffice_name' => ['x', 'Mothers Office Name'],
    'fdesignation' => ['x', 'Fathers Designation'], 'mdesignation' => ['x', 'Mothers Designation'], 'foffice_add' => ['x', 'Fathers Office Address'], 'moffice_add' => ['x', 'Mothers Office Address'],
    'fdoa' => ['x', 'Date of Anniversary'], 'femail' => ['x', 'Student Fathers Email id'], 'memail' => ['x', 'Student Mothers Email id'], 'longitude' => ['x', 'Longitude'], 'laptitude' => ['x', 'Laptitude'],
    'lg_paddr' => ['x', 'Guardian Address'], 'lg_city' => ['x', 'Guardian City'], 'lg_pincode' => ['x', 'Guardian Pincode'], 'schstus' => ['x', 'Previous School Status'],
    'bsisdisc' => ['x', 'Brother/Sister Discount'], 'account_type' => ['x', 'Account Type'], 'smscategry' => ['x', 'SMS Category'], 'fee_month' => ['x', 'Fees Month'],
    'transport_month' => ['x', 'Transport Month'], 'roomno' => ['x', 'Room No'], 'bedno' => ['x', 'Bed'], 'lockerno' => ['x', 'Locker'], 'tokenno' => ['x', 'Token No'],
    'consultant_id' => ['x', 'Consultant'], 'ac_name' => ['x', '10th Board/University'], 'ac_rollno' => ['x', '10th Roll No'],
    'ac_inst' => ['x', '10th Institute'], 'ac_year' => ['x', '10th Year'], 'ac_obtmarks' => ['x', '10th Obt Marks'], 'ac_maxmarks' => ['x', '10th Max. Marks'],
    'doc1' => ['x', 'Birth Certificate/ Last Marsksheet'], 'doc2' => ['x', 'Adhar Card'], 'doc3' => ['x', 'SSSM ID'], 'doc4' => ['x', 'Document 4'],
];
// Current Session Status: the old ERP's words <-> the Full Record's words (the import translated them).
// (Student Status — Active / Deactive / IActive / TC — is kept as the old ERP wrote it, in extra.)
const SM_STATUS = ['Active' => 'Active', 'Deactive' => 'Deactive', 'TC' => 'TC issued', 'IActive' => 'Inactive (left)'];
const SM_CLASSES = ['PG', 'LKG', 'UKG', '1st', '2nd', '3rd', '4th', '5th', '6th', '7th', '8th', '9th', '10th'];

function sm_info(string $sid): array {
    $st = sch_db()->prepare("SELECT r, d FROM sch_records WHERE k='regRow' AND r LIKE 'studinfo|%' AND deleted=0");
    $st->execute();
    foreach ($st as $row) { $d = json_decode((string)$row['d'], true); if (is_array($d) && ($d['student'] ?? '') === $sid) return [(string)$row['r'], $d]; }
    return ['studinfo|' . substr(hash('sha256', $sid . '|'), 0, 20), null];
}
/** Every old field of one student, by old field name. */
function sm_fields(array $s, ?array $i): array {
    $f = []; $x = (array)($i['extra'] ?? []);
    foreach (SM_MAP as $name => [$where, $key]) {
        $v = $where === 's' ? ($s[$key] ?? '') : ($where === 'i' ? ($i[$key] ?? '') : ($x[$key] ?? ''));
        if ($name === 'scount_status') { $back = array_search((string)$v, SM_STATUS, true); $v = $back !== false ? $back : (string)$v; }
        $f[$name] = is_array($v) ? $v : (string)$v;
    }
    return $f;
}
function sm_clean(string $name, $v) {
    if (in_array($name, ['fee_month', 'transport_month'], true)) {
        $v = array_values(array_unique(array_map('strval', array_filter((array)$v, fn($m) => in_array((string)$m, ['1','2','3','4','5','6','7','8','9','10','11','12'], true)))));
        return $v;
    }
    $v = trim(preg_replace('/\s+/u', ' ', str_replace("\r", '', (string)$v)) ?? '');
    if (in_array($name, ['paddr', 'foffice_add', 'moffice_add', 'lg_paddr', 'remarks'], true)) $v = trim(str_replace("\r", '', (string)$v));
    if (mb_strlen($v) > 500) throw new InvalidArgumentException('A field is too long.');
    return $v;
}
function sm_date_ok(string $v): bool { return $v === '' || (bool)preg_match('#^(0[1-9]|[12]\d|3[01])/(0[1-9]|1[0-2])/(19|20)\d\d$#', $v); }

try {
switch ($a) {
case 'meta': {
    // Old ERP lists (copied from its Student Master form, 27 Oct 2026) plus any value already in use, so nothing saved before is lost from a list.
    $opts = json_decode((string)file_get_contents(__DIR__ . '/stumaster_opts.json'), true) ?: [];
    $use = [];
    foreach (reg_rows('studinfo') as $r) foreach (['routeid' => 'route', 'feeapply' => 'scheme', 'stucast' => 'caste', 'religion' => 'religion', 'houseid' => 'house', 'egroup' => 'stream', 'fathoccu' => 'fatherOcc', 'mothoccu' => 'motherOcc', 'vehicle_id' => 'vehicle', 'pschname' => 'prevSchool', 'cast' => 'category', 'smedium' => 'medium', 'optsubj' => 'optSub', 'blood_group' => 'blood', 'stutype' => 'stuType', 'admtype' => 'admAs', 'lastclass' => 'lastClass'] as $n => $k) { $v = trim((string)($r[$k] ?? '')); if ($v !== '') $use[$n][$v] = 1; }
    foreach ($use as $n => $vals) $opts[$n] = array_values(array_unique(array_merge($opts[$n] ?? [], array_keys($vals))));
    $opts['mothoccu'] = array_values(array_unique(array_merge($opts['mothoccu'] ?? [], $opts['fathoccu'] ?? [])));
    $sessions = sch_rec_get('settings', 'main')['session'] ?? '';
    sch_json(['ok' => true, 'opts' => $opts, 'classes' => SM_CLASSES, 'session' => (string)$sessions, 'canEdit' => $role === 'principal', 'school' => (string)((sch_rec_get('settings', 'main') ?? [])['school'] ?? '')]);
}
case 'list': {
    $out = [];
    $infos = []; foreach (reg_rows('studinfo') as $r) if (($r['student'] ?? '') !== '') $infos[(string)$r['student']] = $r;
    foreach ($db->query("SELECT r, d FROM sch_records WHERE k='student' AND deleted=0") as $row) {
        $s = json_decode((string)$row['d'], true); if (!is_array($s) || !empty($s['sample'])) continue;
        $f = sm_fields($s, $infos[(string)$row['r']] ?? null); $f['id'] = (string)$row['r'];
        $out[] = $f;
    }
    $have = []; foreach ($db->query("SELECT r FROM sch_records WHERE k='phoStu' AND deleted=0") as $row) $have[(string)$row['r']] = true;
    foreach ($out as &$f) $f['_photo'] = isset($have[$f['id']]); unset($f);
    sch_json(['ok' => true, 'rows' => $out]);
}
case 'photos': {
    $ids = array_slice(array_filter(explode(',', (string)($_GET['ids'] ?? ''))), 0, 40); $out = [];
    foreach ($ids as $sid) { $p = sch_rec_get('phoStu', $sid); $img = $p ? sch_rec_get('phoImg', (string)$p['img']) : null; if ($img) $out[$sid] = (string)$img['data']; }
    sch_json(['ok' => true, 'photos' => $out]);
}
case 'get': {
    $sid = (string)($_GET['id'] ?? ''); $s = sch_rec_get('student', $sid);
    if (!$s) sch_fail('This student is not in the ERP.', 404);
    [, $i] = sm_info($sid);
    $f = sm_fields($s, $i); $f['id'] = $sid;
    $p = sch_rec_get('phoStu', $sid); $img = $p ? sch_rec_get('phoImg', (string)$p['img']) : null;
    sch_json(['ok' => true, 'f' => $f, 'photo' => $img['data'] ?? '', 'hasInfo' => $i !== null]);
}
case 'save': {
    if ($method !== 'POST') sch_fail('POST only', 405);
    if ($role !== 'principal') sch_fail('Only the Principal can add or change a student.', 403);
    $sid = (string)($in['id'] ?? ''); $raw = (array)($in['f'] ?? []);
    $f = []; foreach (SM_MAP as $name => $_) $f[$name] = sm_clean($name, $raw[$name] ?? (in_array($name, ['fee_month', 'transport_month'], true) ? [] : ''));
    $db->beginTransaction();
    $db->query("SELECT uid FROM sch_users WHERE uid='principal' FOR UPDATE")->fetch();
    $old = $sid !== '' ? sch_rec_get('student', $sid) : null;
    if ($sid !== '' && !$old) throw new InvalidArgumentException('This student is not in the ERP.');
    [$ikey, $info] = $old ? sm_info($sid) : ['', null];
    $prev = $old ? sm_fields($old, $info) : [];
    // A field is checked when it is new or changed — a value copied from the old ERP is never refused.
    $chk = fn(string $k): bool => $old === null || $f[$k] !== ($prev[$k] ?? '');
    // The old ERP's required fields (red labels) and checks.
    foreach (['name' => 'Name', 'clsid' => 'Current Class', 'secid' => 'Section', 'gender' => 'Gender', 'fname' => 'Father\'s Name', 'mname' => 'Mother\'s Name', 'fmobno' => 'Father\'s Mobile No', 'telphno' => 'SMS/Whatsapp No.', 'rollno' => 'Scholar No'] as $k => $label)
        if ($f[$k] === '' && $chk($k)) throw new InvalidArgumentException('Please fill ' . $label . '.');
    if (!in_array($f['clsid'], SM_CLASSES, true)) throw new InvalidArgumentException('Choose the Current Class.');
    if ($chk('gender') && !in_array($f['gender'], ['Male', 'Female'], true)) throw new InvalidArgumentException('Choose the Gender.');
    foreach (['fmobno' => 'Father\'s Mobile No', 'telphno' => 'SMS/Whatsapp No.', 'mothmobno' => 'Mother\'s Mobile No', 'lg_contact' => 'Guardian contact number'] as $k => $label)
        if ($f[$k] !== '' && $chk($k) && !preg_match('/^[6-9]\d{9}$/', $f[$k])) throw new InvalidArgumentException($label . ' must be a 10 digit mobile number.');
    foreach (['dob' => 'Date of Birth', 'cdate' => 'Date of Admission', 'date_ofjoin' => 'Date of Joining', 'fdob' => 'Fathers DOB', 'mdob' => 'Mothers DOB', 'fdoa' => 'Date of Anniversary', 'date_ofleave' => 'Date of Leaving'] as $k => $label)
        if ($chk($k) && !sm_date_ok($f[$k])) throw new InvalidArgumentException($label . ' must be DD/MM/YYYY.');
    if ($chk('adharcard') && $f['adharcard'] !== '' && !preg_match('/^\d{12}$/', $f['adharcard'])) throw new InvalidArgumentException('Aadhar Card must be 12 digits.');
    if ($chk('cpincode') && $f['cpincode'] !== '' && !preg_match('/^\d{6}$/', $f['cpincode'])) throw new InvalidArgumentException('Pin Code must be 6 digits.');
    if ($f['cstatus'] !== '' && $chk('cstatus') && !isset(SM_STATUS[$f['cstatus']])) throw new InvalidArgumentException('Choose the Student Status.');
    if ($f['scount_status'] !== '' && $chk('scount_status') && !in_array($f['scount_status'], ['Active', 'Deactive', 'TC'], true)) throw new InvalidArgumentException('Choose the Current Session Status.');
    if ($chk('accountno') && $f['accountno'] !== '' && !preg_match('/^\d{6,18}$/', $f['accountno'])) throw new InvalidArgumentException('Account No must be digits only.');
    foreach ($db->query("SELECT r, d FROM sch_records WHERE k='student' AND deleted=0") as $row) {
        $o = json_decode((string)$row['d'], true);
        if ($chk('rollno') && (string)$row['r'] !== $sid && is_array($o) && empty($o['sample']) && strcasecmp(trim((string)($o['adm'] ?? '')), $f['rollno']) === 0)
            throw new InvalidArgumentException('Scholar No ' . $f['rollno'] . ' is already given to ' . ($o['name'] ?? '') . '.');
    }
    $isNew = $old === null;
    if ($isNew) { $sid = 's' . bin2hex(random_bytes(6)); [$ikey, $info] = sm_info($sid); }
    $s = $old ?? ['id' => $sid, 'sample' => false];
    $i = $info ?? ['student' => $sid, '_id' => substr($ikey, 9), '_created' => sch_now_ms(), '_createdBy' => 'Student Master (' . ($u['label'] ?? $uid) . ')'];
    $x = (array)($i['extra'] ?? []);
    foreach (SM_MAP as $name => [$where, $key]) {
        $v = $f[$name];
        if ($name === 'scount_status') { if ($v === '' || !isset(SM_STATUS[$v])) continue; $v = SM_STATUS[$v]; }
        if ($where === 's') $s[$key] = $v;
        elseif ($where === 'i') $i[$key] = $v;
        elseif (is_array($v) ? $v : $v !== '') $x[$key] = $v; else unset($x[$key]);
    }
    if (($i['status'] ?? '') === '') $i['status'] = 'Active';
    $i['extra'] = $x;
    $changed = $isNew || json_encode($old) !== json_encode($s) || json_encode($info) !== json_encode($i);
    $i['_by'] = 'Student Master (' . ($u['label'] ?? $uid) . ')'; $i['_at'] = sch_now_ms();
    if ($changed) {
        if ($info) sch_rec_put('regHist', 'studinfo|' . substr($ikey, 9) . '|' . sch_now_ms(), ['row' => $info, 'reason' => 'Edited in Student Master', 'by' => $u['label'] ?? $uid], $uid);
        sch_rec_put('student', $sid, $s, $uid);
        sch_rec_put('regRow', $ikey, $i, $uid);
        sch_log($u, 'Students', ($isNew ? 'Added ' : 'Updated ') . $s['name'] . ' (Class ' . $s['cls'] . '-' . $s['section'] . ', Scholar No ' . $s['adm'] . ')');
    }
    $db->commit();
    sch_json(['ok' => true, 'id' => $sid, 'added' => $isNew, 'changed' => $changed]);
}
case 'photo': {
    if ($method !== 'POST') sch_fail('POST only', 405);
    if ($role !== 'principal') sch_fail('Only the Principal can change a photo.', 403);
    $sid = (string)($in['id'] ?? ''); $data = (string)($in['data'] ?? '');
    if (!sch_rec_get('student', $sid)) throw new InvalidArgumentException('Save the student first.');
    if (!preg_match('#^data:image/(jpeg|png);base64,#', $data) || strlen($data) > 400000) throw new InvalidArgumentException('Choose a JPG or PNG photo.');
    $bin = base64_decode(substr($data, strpos($data, ',') + 1), true);
    if ($bin === false || !@getimagesizefromstring($bin)) throw new InvalidArgumentException('This file is not a picture.');
    $h = sha1($bin);
    $db->beginTransaction();
    if (!sch_rec_get('phoImg', $h)) sch_rec_put('phoImg', $h, ['data' => $data], $uid);
    $prev = sch_rec_get('phoStu', $sid);
    sch_rec_put('phoStu', $sid, ['img' => $h, 'from' => 'Student Master', 'prev' => $prev['img'] ?? '', 'by' => $u['label'] ?? $uid], $uid);
    sch_log($u, 'Students', 'Photo changed for ' . (sch_rec_get('student', $sid)['name'] ?? $sid));
    $db->commit();
    sch_json(['ok' => true]);
}
default: sch_fail('Unknown action.', 404);
}
} catch (InvalidArgumentException $e) {
    if ($db->inTransaction()) $db->rollBack();
    sch_fail($e->getMessage());
} catch (Throwable $e) {
    if ($db->inTransaction()) $db->rollBack();
    error_log('stumaster_api: ' . $e->getMessage());
    sch_fail('Could not complete this. Nothing was saved.', 500);
}
