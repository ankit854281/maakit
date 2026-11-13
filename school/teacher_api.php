<?php
// ============================================================================
//  Teacher App (3 Nov 2026, owner) — the school's phone app for teachers,
//  like the Chairman app: school/teacher.php + teacher.js + teacher.css.
//
//  Most of the teacher's work is saved through the normal api.php sync with the
//  SAME records the web ERP uses (diary = homework/classwork, studAtt =
//  attendance, lplan = weekly lesson plan, leave, syl) and progress_api.php
//  (Term-1/Term-2 marks → result). This file adds only what those do not have:
//
//   GET  ?a=state                         seeds the local holiday list once, returns AI status + the list
//   GET  ?a=month&cls=&ym=YYYY-MM          one class's attendance + class holidays + school holidays for a month
//                                          (attendance register / back-dated bulk update)
//   POST ?a=holiday {cls,date,title,on}    class teacher / Principal: this class has no school on this day
//                                          (kind clsHol 'date|cls'; any attendance of that day is removed —
//                                          a holiday is not counted as present or absent anywhere)
//   GET  ?a=policy / POST ?a=accept {ver}  leave rules + the teacher's one-time "I agree" (kind tchAccept, leavepolicy_lib.php)
//   GET  ?a=leavereport&ym=               Principal/Chairman: each teacher's leave, late leave, absent without permission, bonus
//   POST ?a=policyset {noticeHour,autoDays,bonus,warnAfter,noticeDays,emergency,maxLeave,again}   Principal: change the rules (new version = everyone accepts again)
//   GET  ?a=notes&cls=&subj=               chapter notes of a class (and subject)
//   GET  ?a=overview                      Principal/Chairman: class holidays and note metadata
//   POST ?a=note {id?,cls,subj,chapter,title,body,points[],words[],qa[]}   subject teacher / Principal
//   POST ?a=delnote {id}                   the teacher who wrote it / Principal
//   GET  ?a=photos&cls=                   small photos of a class's children (attendance cards)
//   POST ?a=photo {sid,data}               class teacher / Principal: add or change a child's photo (same records as Student Master)
//   GET  ?a=attstats&cls=                 session attendance per child (present % on the attendance cards and report)
//   GET  ?a=checks&cls=&subj=&date=        homework + copy checking of one class/subject/day (+ last 30 days' counts per child)
//   GET  ?a=fees&cls=                     CLASS TEACHER / Principal only: each child's fee due (same numbers as the Chairman app's
//                                          calling list, ff_snapshot), parent payment dates and the last calls
//   POST ?a=feecall {sid,outcome,promise,amount,note}  class teacher / Principal: one call result into the SAME acctCall
//                                          records the Chairman app and office read (via 'Teacher App')
//   POST ?a=check {date,cls,subj,hw,rows}  subject teacher / Principal: per child hw done|half|not, copy checked|not (kind tchChk 'date|cls|subj')
//
//   GET/POST ?a=kit                        daily check per child: Diary · ID card · Book · Dress (kind tchKit 'date|cls')
//   GET  ?a=stars&cls=                    star children + children who need attention (last 30 days)
//   GET/POST ?a=staffchat                  teacher–teacher chat, Staff room + one-to-one (kind tchMsg, kept as a record)
//   GET  ?a=report&cls=                   class report: each child's attendance, homework, daily check, marks, record, remarks
//   POST ?a=rec {sid,rec}                  class teacher: student record (diary/ID/book/dress/WhatsApp given?) + reading/learning levels
//   POST ?a=remark {sid,type,cat,text}     any teacher of the class: complaint or good thing (kept as a record)
//   POST ?a=delremark {id}                 the writer / Principal
//   GET  ?a=crosscheck&date=[&days=]      Principal: which classes filled the daily check, which homework checks were saved;
//                                          a teacher gets only his own (class-teacher daily check + his homework checks)
//   GET  ?a=chaptext&cls=&subj=[&full=1]   chapter texts of a subject (teachers of the class); POST {cls,subj,chapter,book,text}
//                                          subject teacher / Principal (kind tchChap; empty text removes it)
//
//  Kinds: clsHol (date-keyed, in legacy load so the web ERP shows "Holiday"),
//         tchNote (kept OUT of legacy load/sync — prefix "tch" in inc.php; parents get
//         only their own classes' notes through api.php?a=pdata).
// ============================================================================
declare(strict_types=1);
require __DIR__ . '/inc.php';
require_once __DIR__ . '/learning_lib.php';
require_once __DIR__ . '/scan_lib.php';
require_once __DIR__ . '/followup_lib.php';

$a = (string)($_GET['a'] ?? 'state');
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
try { $db = sch_db(); } catch (Throwable $e) { sch_fail('Server is not reachable right now. Please try again in a few minutes.', 503); }

// Basic Shiksha Parishad U.P. holiday list 2026 (order 19684-89/2025-26, 31 Dec 2025), as followed by
// schools of Bhadohi (221401–221409). Only dates still ahead in the 2026-27 session; Christmas and the
// winter vacation (31 Dec – 14 Jan) are already in the school calendar (bp-xmas, bp-winter).
// Add the 2027 list here when BSA Bhadohi issues it (usually end of December). Never edit a date
// that has passed: the id is the key, so a changed row would be added a second time.
const TCH_LOCAL_HOLIDAYS = [
    ['bsa26-diwali',    '2026-11-08', 'Deepawali (BSA holiday list)'],
    ['bsa26-govardhan', '2026-11-09', 'Govardhan Puja (BSA holiday list)'],
    ['bsa26-dooj',      '2026-11-11', 'Bhaiya Dooj / Chitragupt Jayanti (BSA holiday list)'],
    ['bsa26-chhath',    '2026-11-15', 'Chhath Puja (BSA holiday list)'],
    ['bsa26-gurunanak', '2026-11-24', 'Guru Nanak Jayanti / Kartik Purnima (BSA holiday list)'],
];

function tch_seed_holidays(): int {
    $db = sch_db(); $added = 0;
    $cal = [];
    foreach ($db->query("SELECT d FROM sch_records WHERE k='cal' AND deleted=0") as $row) { $e = json_decode((string)$row['d'], true); if (is_array($e)) $cal[] = $e; }
    $seen = $db->prepare("SELECT 1 FROM sch_records WHERE k='cal' AND r=?");      // a deleted row counts: the Principal removed it
    foreach (TCH_LOCAL_HOLIDAYS as [$id, $date, $title]) {
        $seen->execute([$id]); if ($seen->fetchColumn()) continue;
        foreach ($cal as $e) if (($e['type'] ?? '') === 'Holiday' && ($e['date'] ?? '') <= $date && (($e['to'] ?? '') ?: ($e['date'] ?? '')) >= $date) continue 2;
        sch_rec_put('cal', $id, ['id' => $id, 'date' => $date, 'to' => '', 'title' => $title, 'type' => 'Holiday', 'src' => 'BSA Bhadohi'], 'system');
        $added++;
    }
    if ($added) sch_log(['label' => 'Teacher App', 'role' => 'system'], 'Calendar', $added . ' holiday(s) added from the Basic Shiksha Parishad / BSA Bhadohi list (Principal can remove any in Calendar)');
    return $added;
}

// Gender lives in the Student Full Record (School Office register 'studinfo', field gender = Male / Female — the same
// row Student Master edits). sid => gender.
function tch_genders(PDO $db): array {
    $o = [];
    foreach ($db->query("SELECT d FROM sch_records WHERE k='regRow' AND r LIKE 'studinfo|%' AND deleted=0") as $row) { $d = json_decode((string)$row['d'], true);
        if (is_array($d) && ($d['student'] ?? '') !== '' && in_array($d['gender'] ?? '', ['Male', 'Female'], true)) $o[(string)$d['student']] = $d['gender']; }
    return $o;
}
function tch_is_ct(string $tid, string $cls): bool {
    foreach ((array)sch_rec_get('classes', 'main') as $c) if (($c['c'] ?? '') === $cls && ($c['ct'] ?? '') === $tid) return true;
    return false;
}
function tch_txt($v, int $max): string { return mb_substr(trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', (string)$v)), 0, $max); }
function tch_list($v, int $n, callable $f): array { $o = []; foreach (array_slice(is_array($v) ? $v : [], 0, $n) as $x) { $y = $f($x); if ($y !== null) $o[] = $y; } return $o; }

try {
$u = sch_auth();
$role = (string)$u['role']; $tid = (string)($u['tid'] ?? ''); $uid = sch_uid_of($u);
if (!in_array($role, ['teacher', 'principal', 'chairman'], true)) sch_fail('The Teacher App is for school staff.', 403);
$in = [];
if ($method === 'POST') {
    $in = json_decode(file_get_contents('php://input', false, null, 0, 400000) ?: '', true);
    if (!is_array($in)) sch_fail('Bad request.');
    if ($role === 'chairman') sch_fail('The Chairman login can only view.', 403);
}
$canClass = fn(string $cls): bool => $role === 'principal' || ($role === 'teacher' && tch_is_ct($tid, $cls));
// Fee calling for the whole school (owner, 13 Nov 2026: "Akash Sir wala fee calling Adarsh Sir ko — app me do"):
// teachers in settings.feeCallers (default Adarsh Dubey Sir, 'AD') call the parents of EVERY class from the Teacher App.
// No list saved → any teacher whose name has "Adarsh" (his id may be AD, AD2 … depending on how he was added).
$fcList = (sch_rec_get('settings', 'main') ?? [])['feeCallers'] ?? null;
$feeCaller = $role === 'teacher' && (is_array($fcList) ? in_array($tid, $fcList, true) : ($tid === 'AD' || stripos(sch_teacher_name($tid), 'adarsh') !== false));
$canSee = fn(string $cls): bool => $role !== 'teacher' || sch_in_class($tid, $cls);

switch ($a) {

// ---- Leave policy (12 Nov 2026, owner: "sab automated karo") — leavepolicy_lib.php ----
case 'policy': {            // the rules + has this teacher accepted the current version
    require_once __DIR__ . '/leavepolicy_lib.php';
    $p = lp_policy(); $acc = $role === 'teacher' ? lp_accepted($tid) : null;
    sch_json(['ok' => true, 'policy' => $p, 'rules' => lp_rules($p), 'accepted' => $role !== 'teacher' || $acc !== null, 'acceptedAt' => $acc['at'] ?? null]);
}
case 'accept': {            // POST {ver} — the teacher agrees to the rules (kept as a record with the time)
    require_once __DIR__ . '/leavepolicy_lib.php';
    if ($method !== 'POST' || $role !== 'teacher') sch_fail('Only a teacher accepts the leave rules.', 403);
    $p = lp_policy(); if ((int)($in['ver'] ?? 0) !== $p['ver']) sch_fail('The rules changed — read them again.');
    $name = (string)((sch_rec_get('teacher', $tid) ?? [])['name'] ?? $tid);
    sch_rec_put('tchAccept', $tid, ['tid' => $tid, 'name' => $name, 'ver' => $p['ver'], 'at' => sch_now_ms(), 'rules' => lp_rules($p)], $uid);
    sch_log($u, 'Leave policy', "$name accepted the leave rules (version {$p['ver']})");
    sch_json(['ok' => true]);
}
case 'leavereport': {       // GET &ym= — Principal / Chairman: each teacher's month (leave, late, without permission, bonus, accepted)
    require_once __DIR__ . '/leavepolicy_lib.php';
    if (!in_array($role, ['principal', 'chairman'], true)) sch_fail('Only the Principal and Chairman see the leave report.', 403);
    $ym = (string)($_GET['ym'] ?? date('Y-m')); if (!preg_match('/^20\d\d-(0[1-9]|1[0-2])$/', $ym)) sch_fail('Bad month.');
    sch_json(['ok' => true] + lp_month($ym));
}
case 'policyset': {         // POST {noticeHour, autoDays, bonus, warnAfter, again} — Principal; again = everyone accepts once more
    require_once __DIR__ . '/leavepolicy_lib.php';
    if ($method !== 'POST' || $role !== 'principal') sch_fail('Only the Principal changes the leave rules.', 403);
    $old = lp_policy(); $new = $old;
    foreach (['noticeHour' => [0, 23], 'autoDays' => [0, 10], 'bonus' => [0, 5000], 'warnAfter' => [1, 10], 'noticeDays' => [1, 15], 'emergency' => [0, 5], 'maxLeave' => [1, 15]] as $k => [$lo, $hi])
        if (isset($in[$k]) && is_numeric($in[$k])) $new[$k] = max($lo, min($hi, (int)$in[$k]));
    if (!empty($in['again']) || $new != $old) $new['ver'] = $old['ver'] + 1;
    sch_rec_put('tchPolicy', 'main', $new, $uid);
    sch_log($u, 'Leave policy', "Rules changed (version {$new['ver']}): apply {$new['noticeDays']} days ahead, {$new['emergency']} emergency/month before {$new['noticeHour']}:00, >{$new['maxLeave']} leave = Sundays unpaid, auto-approve up to {$new['autoDays']} day(s), bonus ₹{$new['bonus']}, warning after {$new['warnAfter']}");
    sch_json(['ok' => true, 'policy' => $new]);
}

case 'overview': {
    if ($method !== 'GET') sch_fail('GET only', 405);
    if (!in_array($role, ['principal', 'chairman'], true)) sch_fail('Only the Principal or Chairman can see this overview.', 403);
    $settings = sch_rec_get('settings', 'main') ?: [];
    $start = (string)($settings['sessionStart'] ?? '');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start) || !strtotime($start)) $start = '';
    $end = $start ? date('Y-m-d', strtotime($start . ' +1 year -1 day')) : '';
    $holidays = []; $notes = [];
    foreach ($db->query("SELECT k, r, d FROM sch_records WHERE k IN ('clsHol','tchNote') AND deleted=0") as $row) {
        $d = json_decode((string)$row['d'], true);
        if (!is_array($d)) continue;
        if ($row['k'] === 'clsHol') {
            $date = (string)($d['date'] ?? '');
            if ($start && ($date < $start || $date > $end)) continue;
            $holidays[] = ['cls' => (string)($d['cls'] ?? ''), 'date' => $date,
                           'title' => (string)($d['title'] ?? ''), 'by' => (string)($d['by'] ?? ''), 'at' => (int)($d['at'] ?? 0)];
        } else {
            $cls = (string)($d['cls'] ?? ''); $subj = (string)($d['subj'] ?? '');
            if ($cls === '' || $subj === '') continue;
            $notes[$cls][$subj][] = ['id' => (string)($d['id'] ?? $row['r']), 'cls' => $cls,
                'subj' => $subj, 'chapter' => (string)($d['chapter'] ?? ''), 'title' => (string)($d['title'] ?? ''),
                'by' => (string)($d['by'] ?? ''), 'at' => (int)($d['at'] ?? 0),
                'share' => (bool)($d['share'] ?? false), 'pointsCount' => count((array)($d['points'] ?? []))];
        }
    }
    usort($holidays, fn($x, $y) => strcmp($y['date'], $x['date']));
    ksort($notes);
    foreach ($notes as &$subjects) {
        ksort($subjects);
        foreach ($subjects as &$list) usort($list, fn($x, $y) => ($y['at'] <=> $x['at']));
        unset($list);
    }
    unset($subjects);
    sch_json(['ok' => true, 'holidays' => $holidays, 'notes' => (object)$notes,
              'session' => (string)($settings['session'] ?? '')]);
}

case 'state': {
    $added = tch_seed_holidays();
    sch_json(['ok' => true, 'ai' => sc_ai_ready(), 'added' => $added,
              'localHolidays' => array_map(fn($h) => ['id' => $h[0], 'date' => $h[1], 'title' => $h[2]], TCH_LOCAL_HOLIDAYS)]);
}

case 'month': {
    $cls = (string)($_GET['cls'] ?? ''); $ym = (string)($_GET['ym'] ?? '');
    if (!preg_match('/^20\d\d-(0[1-9]|1[0-2])$/', $ym) || $cls === '') sch_fail('Choose the class and the month.');
    if (!$canSee($cls)) sch_fail('You can see only your own classes.', 403);
    $st = $db->prepare("SELECT k, r, d FROM sch_records WHERE k IN ('studAtt','clsHol') AND deleted=0 AND dkey LIKE ? AND r LIKE ?");
    $st->execute([$ym . '-%', '%|' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $cls)]);
    $att = []; $hol = [];
    foreach ($st as $row) {
        [$date, $c] = array_pad(explode('|', (string)$row['r'], 2), 2, '');
        if ($c !== $cls) continue;
        $d = json_decode((string)$row['d'], true); if (!is_array($d)) continue;
        if ($row['k'] === 'studAtt') $att[$date] = $d; else $hol[$date] = $d;
    }
    sch_json(['ok' => true, 'cls' => $cls, 'ym' => $ym, 'att' => (object)$att, 'hol' => (object)$hol, 'canEdit' => $canClass($cls)]);
}

case 'holiday': {
    if ($method !== 'POST') sch_fail('POST only', 405);
    $cls = (string)($in['cls'] ?? ''); $date = (string)($in['date'] ?? '');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !strtotime($date) || $cls === '') sch_fail('Choose the class and the date.');
    if (!$canClass($cls)) sch_fail('Only the class teacher or the Principal can mark a holiday for this class.', 403);
    if ($date > date('Y-m-d', time() + 90 * 86400)) sch_fail('Choose a date within the next 3 months.');
    if ((int)date('w', strtotime($date)) === 0) sch_fail('Sunday is already a holiday.');
    $key = $date . '|' . $cls;
    $db->beginTransaction();
    $db->query("SELECT uid FROM sch_users WHERE uid='principal' FOR UPDATE")->fetch();
    if (($in['on'] ?? true) === false) {
        if (sch_rec_get('clsHol', $key)) { sch_rec_del('clsHol', $key, $uid); sch_log($u, 'Student Attendance', "Class $cls: holiday on $date removed"); }
        $db->commit(); sch_json(['ok' => true]);
    }
    $title = tch_txt($in['title'] ?? '', 120); if ($title === '') $title = 'Holiday';
    $had = sch_rec_get('studAtt', $key);
    if ($had) sch_rec_del('studAtt', $key, $uid);
    sch_rec_put('clsHol', $key, ['cls' => $cls, 'date' => $date, 'title' => $title, 'by' => $u['label'], 'at' => sch_now_ms()], $uid);
    sch_log($u, 'Student Attendance', "Class $cls: $date marked as holiday ($title)" . ($had ? '; the attendance saved for that day was removed' : ''));
    $db->commit();
    sch_json(['ok' => true, 'removedAttendance' => (bool)$had]);
}

// ---- Attendance v3 (owner, 5 Nov 2026: photo, name and percentage on every child's card) ----
case 'photos': {
    // Small photos of one class for the attendance cards. Thumbnails (112 px JPEG) are made once with GD and kept
    // as kind phoThumb keyed by the image hash ('pho' prefix: never in legacy load/sync). No GD: the original is sent.
    $cls = (string)($_GET['cls'] ?? '');
    if ($cls === '' || !$canSee($cls)) sch_fail('You can see only your own classes.', 403);
    $out = [];
    foreach ($db->query("SELECT r, d FROM sch_records WHERE k='student' AND deleted=0") as $row) {
        $s = json_decode((string)$row['d'], true); if (!is_array($s) || ($s['cls'] ?? '') !== $cls) continue;
        $p = sch_rec_get('phoStu', (string)$row['r']); if (!$p || empty($p['img'])) continue;
        $h = (string)$p['img'];
        $tk = 'v2|' . $h;                       // v2: thumbnails made after the crop fix (old ones were half black)
        $th = sch_rec_get('phoThumb', $tk);
        if (!$th) {
            $img = sch_rec_get('phoImg', $h); $data = (string)($img['data'] ?? ''); if ($data === '') continue;
            $small = $data;
            // EXIF-free JPEGs from phones can still come sideways from some uploads; GD keeps them as they are.
            if (function_exists('imagecreatefromstring')) {
                $bin = base64_decode(substr($data, strpos($data, ',') + 1), true);
                $src = $bin ? @imagecreatefromstring($bin) : false;
                if ($src) {
                    $w = imagesx($src); $hh = imagesy($src); $e = min($w, $hh); $dst = imagecreatetruecolor(112, 112);
                    // Square from the middle of the photo (a little higher for faces). Owner, 5 Nov 2026: the photos showed
                    // half black because the crop offset was given as the TARGET position; it belongs to the SOURCE.
                    imagecopyresampled($dst, $src, 0, 0, (int)(($w - $e) / 2), (int)(($hh - $e) / 3), 112, 112, $e, $e);
                    ob_start(); imagejpeg($dst, null, 78); $small = 'data:image/jpeg;base64,' . base64_encode((string)ob_get_clean());
                }
            }
            $th = ['data' => $small];
            if (strlen($small) < 60000) sch_rec_put('phoThumb', $tk, $th, 'system');
        }
        $out[(string)$row['r']] = ['h' => $h, 'src' => (string)$th['data']];
    }
    sch_json(['ok' => true, 'photos' => (object)$out]);
}

case 'photo': {
    // Class teacher (of the child's class) or Principal adds / changes a child's photo from the phone camera.
    // Same records as Student Master (phoImg + phoStu with 'prev'), so the web ERP and report cards show it too.
    if ($method !== 'POST') sch_fail('POST only', 405);
    $sid = (string)($in['sid'] ?? ''); $data = (string)($in['data'] ?? '');
    $stu = sch_rec_get('student', $sid); if (!$stu) sch_fail('Student not found.', 404);
    if (!$canClass((string)($stu['cls'] ?? ''))) sch_fail('Only the class teacher or the Principal can change this photo.', 403);
    if (!preg_match('#^data:image/jpeg;base64,#', $data) || strlen($data) > 400000) sch_fail('Choose a clear photo (JPG).');
    $bin = base64_decode(substr($data, strpos($data, ',') + 1), true);
    if ($bin === false || !@getimagesizefromstring($bin)) sch_fail('This file is not a picture.');
    $h = sha1($bin);
    $db->beginTransaction();
    if (!sch_rec_get('phoImg', $h)) sch_rec_put('phoImg', $h, ['data' => $data], $uid);
    $prev = sch_rec_get('phoStu', $sid);
    sch_rec_put('phoStu', $sid, ['img' => $h, 'from' => 'Teacher App', 'prev' => $prev['img'] ?? '', 'by' => $u['label'] ?? $uid], $uid);
    sch_log($u, 'Students', 'Photo ' . ($prev ? 'changed' : 'added') . ' for ' . ($stu['name'] ?? $sid) . ' — Teacher App');
    $db->commit();
    sch_json(['ok' => true, 'h' => $h]);
}

case 'attstats': {
    // Session attendance of one class: school days with attendance saved, and each child's present / absent / leave.
    // Counted only from saved attendance (studAtt). Holidays have no studAtt, so they are never counted.
    $cls = (string)($_GET['cls'] ?? '');
    if ($cls === '' || !$canSee($cls)) sch_fail('You can see only your own classes.', 403);
    $set = sch_rec_get('settings', 'main') ?: [];
    $start = (string)($set['sessionStart'] ?? ''); if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start)) $start = (date('n') < 4 ? date('Y') - 1 : date('Y')) . '-04-01';
    $st = $db->prepare("SELECT r, d FROM sch_records WHERE k='studAtt' AND deleted=0 AND dkey >= ? AND r LIKE ?");
    $st->execute([$start, '%|' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $cls)]);
    $days = 0; $month = 0; $ym = (new DateTime('now', new DateTimeZone('Asia/Kolkata')))->format('Y-m'); $per = [];   // school month, India time
    foreach ($st as $row) {
        [$date, $c] = array_pad(explode('|', (string)$row['r'], 2), 2, '');
        if ($c !== $cls) continue;
        $d = json_decode((string)$row['d'], true); if (!is_array($d)) continue;
        $days++; $inMonth = str_starts_with($date, $ym); if ($inMonth) $month++;
        $lv = (array)($d['st'] ?? []);
        foreach ((array)($d['absent'] ?? []) as $sid) {
            $sid = (string)$sid; $k = (($lv[$sid] ?? '') === 'L') ? 'l' : 'a';
            $per[$sid] ??= ['a' => 0, 'l' => 0, 'ma' => 0]; $per[$sid][$k]++; if ($inMonth) $per[$sid]['ma']++;
        }
    }
    sch_json(['ok' => true, 'from' => $start, 'days' => $days, 'monthDays' => $month, 'per' => (object)$per]);
}

case 'fees': {
    // Owner, 5 Nov 2026: the CLASS TEACHER calls the parents of their own class about fees, with amounts.
    // Other teachers never see fees. Amounts are paise, exactly as the Chairman app's calling list.
    if ($method !== 'GET') sch_fail('GET only', 405);
    $cls = (string)($_GET['cls'] ?? '');
    if ($cls === '' || !($canClass($cls) || $feeCaller)) sch_fail('Only the class teacher (or the school\'s fee caller) sees the fees of this class.', 403);
    $session = ff_session(); $snap = ff_snapshot($db, $session); $kids = []; $ids = [];
    foreach ($snap as $sid => $x) if (($x['cls'] ?? '') === $cls) {
        $last = end($x['receipts']) ?: null;
        $kids[] = ['sid' => $sid, 'name' => $x['name'], 'father' => $x['father'], 'phone' => $x['phone'], 'tag' => $x['tag'], 'due' => (int)$x['due'],
                   'overallDue' => $x['overallDue'] === null ? null : (int)$x['overallDue'], 'oldDue' => (int)$x['oldDue'], 'asOn' => $x['asOn'],
                   'last' => $last ? ['no' => (string)$last['no'], 'date' => (string)$last['date'], 'amount' => (int)round($last['received'] * 100)] : null];
        $ids[$sid] = true;
    }
    $promises = []; foreach (ff_rows('acctPromise') as $pr) if (($pr['session'] ?? '') === $session && isset($ids[$pr['sid'] ?? ''])) $promises[$pr['sid']] = ['date' => $pr['promise'], 'amount' => (int)$pr['amount']];
    $calls = []; foreach (ff_rows('acctCall') as $c) if (($c['session'] ?? $session) === $session && isset($ids[$c['sid'] ?? '']) && empty($c['initiated']))
        $calls[] = array_intersect_key($c, array_flip(['id', 'sid', 'outcome', 'promise', 'amount', 'note', 'by', 'at', 'date']));
    usort($calls, fn($x, $y) => ($y['at'] ?? 0) <=> ($x['at'] ?? 0));
    sch_json(['ok' => true, 'session' => $session, 'today' => date('Y-m-d'), 'school' => (string)(sch_rec_get('settings', 'main')['school'] ?? ''), 'children' => $kids,
              'promises' => (object)$promises, 'calls' => array_slice($calls, 0, 400), 'outcomes' => OF_OUTCOMES]);
}

case 'tours': {
    // Tour calling (owner, 13 Nov 2026: "teachers me tour calling ka option" + "tour wale me tag"): the class teacher (or the
    // school's fee caller) calls parents about the tour and tags each child. Tags: going (pay later), no, think, noans.
    // Paid children come from the Tour fee receipts (cashdesk_api.php). Tag 'going' also writes the name for the tour.
    if ($method !== 'GET') sch_fail('GET only', 405);
    $cls = (string)($_GET['cls'] ?? '');
    if ($cls === '' || !($canClass($cls) || $feeCaller)) sch_fail('Only the class teacher (or the school\'s fee caller) calls about the tour for this class.', 403);
    $set = sch_rec_get('settings', 'main') ?: []; $ses = trim((string)($set['session'] ?? '')) ?: date('Y');
    $rows = function (string $k) use ($db): array { $q = $db->prepare('SELECT r, d FROM sch_records WHERE k=? AND deleted=0'); $q->execute([$k]); $o = [];
        foreach ($q as $row) { $d = json_decode((string)$row['d'], true); if (is_array($d)) $o[(string)$row['r']] = $d; } return $o; };
    $pays = $rows('regTourPay'); $books = $rows('regTourBook'); $tags = $rows('regTourTag'); $out = [];
    $kids = []; foreach ($rows('student') as $sid => $st) if (empty($st['inactive']) && ($st['cls'] ?? '') === $cls) $kids[] = ['id' => (string)$sid, 'name' => (string)($st['name'] ?? ''), 'father' => (string)($st['father'] ?? ''), 'phone' => (string)($st['phone'] ?? '')];
    usort($kids, fn($x, $y) => strcmp($x['name'], $y['name']));
    foreach ($rows('regTour') as $tour => $t) {
        if (($t['session'] ?? '') !== $ses || (($t['classes'] ?? []) && !in_array($cls, (array)$t['classes'], true))) continue;
        $L = [];
        foreach ($kids as $k) { $key = $tour . '|' . $k['id']; $p = $pays[$key] ?? null; $bk = $books[$key] ?? null; $tg = $tags[$key] ?? null;
            $L[] = $k + ['paid' => $p && ($p['status'] ?? '') === 'Paid' ? ['no' => (string)$p['no'], 'amount' => (int)$p['amount'], 'date' => (string)$p['date']] : null,
                'book' => $bk && !empty($bk['on']), 'tag' => $tg ? ['tag' => (string)$tg['tag'], 'note' => (string)($tg['note'] ?? ''), 'by' => (string)($tg['by'] ?? ''), 'at' => (int)($tg['at'] ?? 0)] : null]; }
        $out[] = ['id' => (string)$tour, 'name' => (string)$t['name'], 'date' => (string)$t['date'], 'amount' => (int)$t['amount'], 'kids' => $L];
    }
    usort($out, fn($x, $y) => strcmp($y['date'], $x['date']));
    sch_json(['ok' => true, 'cls' => $cls, 'tours' => $out, 'school' => (string)($set['school'] ?? '')]);
}
case 'tourtag': {
    if ($method !== 'POST') sch_fail('POST only', 405);
    $tour = (string)($in['tour'] ?? ''); $sid = (string)($in['sid'] ?? ''); $tag = (string)($in['tag'] ?? ''); $note = mb_substr(trim(preg_replace('/[\x00-\x1f\x7f]/u', ' ', (string)($in['note'] ?? ''))), 0, 200);
    if (!in_array($tag, ['going', 'no', 'think', 'noans', ''], true)) sch_fail('Choose a tag.');
    $t = preg_match('/^t[a-f0-9]{10}$/', $tour) ? sch_rec_get('regTour', $tour) : null; if (!$t) sch_fail('Tour not found.');
    $st = sch_rec_get('student', $sid); if (!$st || !empty($st['inactive'])) sch_fail('Child not found.');
    $cls = (string)($st['cls'] ?? '');
    if (!($canClass($cls) || $feeCaller)) sch_fail('Only the class teacher (or the school\'s fee caller) tags children of this class.', 403);
    if (($t['classes'] ?? []) && !in_array($cls, (array)$t['classes'], true)) sch_fail('This child\'s class is not on this tour.');
    $key = $tour . '|' . $sid;
    $who = sch_user_label($u);
    sch_rec_put('regTourTag', $key, ['tag' => $tag, 'note' => $note, 'by' => $who, 'at' => sch_now_ms(), 'name' => (string)($st['name'] ?? ''), 'cls' => $cls], $uid);
    $paid = (sch_rec_get('regTourPay', $key)['status'] ?? '') === 'Paid';
    if (!$paid && ($tag === 'going' || $tag === 'no')) sch_rec_put('regTourBook', $key, ['on' => $tag === 'going', 'by' => $who, 'at' => sch_now_ms(), 'name' => (string)($st['name'] ?? ''), 'cls' => $cls], $uid);
    $lbl = ['going' => 'Going (pay later)', 'no' => 'Not going', 'think' => 'Will tell later', 'noans' => 'No answer', '' => 'tag removed'][$tag];
    sch_log($u, 'Tour calling', "{$st['name']} ($cls), {$t['name']}: $lbl" . ($note !== '' ? " — $note" : ''));
    sch_json(['ok' => true]);
}
case 'feecall': {
    if ($method !== 'POST') sch_fail('POST only', 405);
    $sid = (string)($in['sid'] ?? ''); $s = $sid !== '' ? sch_rec_get('student', $sid) : null;
    if (!$s || !($canClass((string)($s['cls'] ?? '')) || $feeCaller)) sch_fail('Only the class teacher (or the school\'s fee caller) can call about this child\'s fee.', 403);
    $out = (string)($in['outcome'] ?? ''); if (!in_array($out, OF_OUTCOMES, true)) sch_fail('Choose the call result.');
    $session = ff_session();
    $db->beginTransaction(); $db->query("SELECT uid FROM sch_users WHERE uid='principal' FOR UPDATE")->fetch();
    try {
        $snap = ff_snapshot($db, $session); $child = $snap[$sid] ?? null;
        if (!$child) throw new DomainException('A fee card is not available for this child. Please ask the office.');
        $need = in_array($out, ['Promised to pay', 'Call back later', 'Will visit school'], true);
        $date = $need ? ff_date($in['promise'] ?? '') : '';
        $amount = $out === 'Promised to pay' ? ff_amount($in['amount'] ?? '', (int)$child['due']) : 0;
        $now = sch_now_ms(); $today = date('Y-m-d'); $id = bin2hex(random_bytes(12));
        $family = sch_phone10((string)($s['phone'] ?? '')); $sids = [$sid];
        if ($family !== '') foreach ($snap as $x) if ($x['sid'] !== $sid && sch_phone10((string)(sch_rec_get('student', $x['sid'])['phone'] ?? '')) === $family) $sids[] = $x['sid'];
        $c = ['id' => $id, 'sid' => $sid, 'sids' => $sids, 'fam' => $family ?: 'sid:' . $sid, 'session' => $session, 'outcome' => $out, 'promise' => $date, 'amount' => $amount,
              'note' => ff_text($in['note'] ?? ''), 'by' => $u['label'] . ' (Teacher App)', 'uid' => $uid, 'at' => $now, 'date' => $today, 'startedAt' => $now, 'startedDate' => $today,
              'completedAt' => $now, 'completedDate' => $today, 'initiated' => false, 'tag' => $child['tag'], 'via' => 'teacher'];
        sch_rec_put('acctCall', $id, $c, $uid);
        sch_log($u, 'Fee Call', 'Class teacher call for ' . $child['name'] . ': ' . $out . ($date ? ' — ' . $date : '') . ' (Teacher App)');
        $db->commit();
    } catch (DomainException $e) { $db->rollBack(); sch_fail($e->getMessage(), 422); }
    sch_json(['ok' => true, 'call' => array_intersect_key($c, array_flip(['id', 'sid', 'outcome', 'promise', 'amount', 'note', 'by', 'at', 'date']))]);
}

case 'checks': {
    // Homework + copy checking (owner, 5 Nov 2026). Teachers of that class (any subject) and the Principal/Chairman read.
    if ($method !== 'GET') sch_fail('GET only', 405);
    $cls = (string)($_GET['cls'] ?? ''); $subj = (string)($_GET['subj'] ?? ''); $date = (string)($_GET['date'] ?? '');
    if ($cls === '' || $subj === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) sch_fail('Choose the class, subject and date.');
    if (!$canSee($cls)) sch_fail('You can see only your own classes.', 403);
    $rec = sch_rec_get('tchChk', "$date|$cls|$subj");
    // last 30 days: how often each child did not do homework in this subject (shown next to the name)
    $from = date('Y-m-d', strtotime($date . ' -30 days')); $miss = []; $n = 0;
    $q = $db->prepare("SELECT r, d FROM sch_records WHERE k='tchChk' AND deleted=0 AND r >= ? AND r < ?"); $q->execute([$from, $date]);
    foreach ($q as $row) { [$d0, $c0, $s0] = array_pad(explode('|', (string)$row['r'], 3), 3, '');
        if ($c0 !== $cls || $s0 !== $subj) continue; $n++;
        foreach ((array)((json_decode((string)$row['d'], true) ?: [])['rows'] ?? []) as $sid => $x) if (($x['hw'] ?? '') === 'not') $miss[$sid] = ($miss[$sid] ?? 0) + 1; }
    sch_json(['ok' => true, 'rec' => $rec, 'miss' => (object)$miss, 'checked' => $n]);
}

case 'check': {
    if ($method !== 'POST') sch_fail('POST only', 405);
    $cls = tch_txt($in['cls'] ?? '', 20); $subj = tch_txt($in['subj'] ?? '', 60); $date = (string)($in['date'] ?? '');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || $date > date('Y-m-d', time() + 86400)) sch_fail('Check the date.');
    if (!learn_allowed($u, $cls, $subj)) sch_fail('Only the teacher of this subject can check its homework.', 403);
    $ids = [];
    foreach ($db->query("SELECT r, d FROM sch_records WHERE k='student' AND deleted=0") as $row) { $d = json_decode((string)$row['d'], true); if (($d['cls'] ?? '') === $cls) $ids[(string)$row['r']] = true; }
    $rows = [];
    foreach ((array)($in['rows'] ?? []) as $sid => $x) {
        $sid = (string)$sid; if (!isset($ids[$sid]) || !is_array($x)) continue;
        $hw = in_array($x['hw'] ?? '', ['done', 'half', 'not'], true) ? $x['hw'] : '';
        $cp = in_array($x['cp'] ?? '', ['ok', 'not'], true) ? $x['cp'] : '';
        if ($hw !== '' || $cp !== '') $rows[$sid] = ['hw' => $hw, 'cp' => $cp];
    }
    if (!$rows) sch_fail('Mark at least one child.');
    $key = "$date|$cls|$subj"; $old = sch_rec_get('tchChk', $key);
    $d = ['date' => $date, 'cls' => $cls, 'subj' => $subj, 'hw' => tch_txt($in['hw'] ?? '', 500), 'rows' => $rows,
          'tid' => $role === 'teacher' ? $tid : 'P', 'by' => $u['label'], 'at' => sch_now_ms(), 'created' => $old['created'] ?? sch_now_ms()];
    sch_rec_put('tchChk', $key, $d, $uid);
    $not = count(array_filter($rows, fn($x) => $x['hw'] === 'not')); $done = count(array_filter($rows, fn($x) => $x['hw'] === 'done'));
    sch_log($u, 'Homework check', "Class $cls $subj ($date): $done done, $not not done, " . count(array_filter($rows, fn($x) => $x['cp'] === 'ok')) . ' copies checked');
    sch_json(['ok' => true, 'rec' => $d]);
}

// ---- Daily check: Diary · ID card · Book · Dress (owner, 5 Nov 2026: "dairy id book dress fill karne ka option") ----
// Kind tchKit 'date|cls' = {date, cls, rows:{sid:{d,i,b,u}}, by, at}. 1 = brought / wearing, 0 = missing.
// Only the class teacher (or Principal) fills it, for the day chosen — each day keeps its own entry.
case 'kit': {
    if ($method === 'GET') {
        $cls = (string)($_GET['cls'] ?? ''); $date = (string)($_GET['date'] ?? '');
        if ($cls === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) sch_fail('Choose the class and the date.');
        if (!$canSee($cls)) sch_fail('You can see only your own classes.', 403);
        $from = date('Y-m-d', strtotime($date . ' -30 days')); $miss = []; $days = 0;
        $q = $db->prepare("SELECT r, d FROM sch_records WHERE k='tchKit' AND deleted=0 AND r >= ? AND r < ?"); $q->execute([$from, $date]);
        foreach ($q as $row) { [, $c0] = array_pad(explode('|', (string)$row['r'], 2), 2, ''); if ($c0 !== $cls) continue; $days++;
            foreach ((array)((json_decode((string)$row['d'], true) ?: [])['rows'] ?? []) as $sid => $x) foreach (['d', 'i', 'b', 'u'] as $f) if (isset($x[$f]) && (int)$x[$f] === 0) $miss[$sid][$f] = ($miss[$sid][$f] ?? 0) + 1; }
        sch_json(['ok' => true, 'rec' => sch_rec_get('tchKit', "$date|$cls"), 'miss' => (object)$miss, 'days' => $days, 'canEdit' => $canClass($cls)]);
    }
    $cls = tch_txt($in['cls'] ?? '', 20); $date = (string)($in['date'] ?? '');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !strtotime($date) || $date > date('Y-m-d')) sch_fail('Choose today or a past day.');
    if (!$canClass($cls)) sch_fail('Only the class teacher or the Principal can fill the daily check of this class.', 403);
    $ids = [];
    foreach ($db->query("SELECT r, d FROM sch_records WHERE k='student' AND deleted=0") as $row) { $d = json_decode((string)$row['d'], true); if (($d['cls'] ?? '') === $cls) $ids[(string)$row['r']] = true; }
    $rows = [];
    foreach ((array)($in['rows'] ?? []) as $sid => $x) {
        $sid = (string)$sid; if (!isset($ids[$sid]) || !is_array($x)) continue;
        $r = []; foreach (['d', 'i', 'b', 'u'] as $f) if (isset($x[$f])) $r[$f] = (int)$x[$f] ? 1 : 0;
        if ($r) $rows[$sid] = $r;
    }
    if (!$rows) sch_fail('Mark at least one child.');
    $key = "$date|$cls"; $old = sch_rec_get('tchKit', $key);
    $d = ['date' => $date, 'cls' => $cls, 'rows' => $rows, 'by' => $u['label'], 'at' => sch_now_ms(), 'created' => $old['created'] ?? sch_now_ms()];
    sch_rec_put('tchKit', $key, $d, $uid);
    $cnt = fn($f) => count(array_filter($rows, fn($x) => isset($x[$f]) && $x[$f] === 0));
    sch_log($u, 'Daily check', "Class $cls ($date): diary missing {$cnt('d')}, ID card {$cnt('i')}, book {$cnt('b')}, dress {$cnt('u')} — Teacher App");
    sch_json(['ok' => true, 'rec' => $d]);
}

// ---- Star children + children who need attention (owner, 5 Nov 2026: "upar achhe bachon ko") ----
// Last 30 days of one class, only from what teachers saved: homework checks (tchChk), daily check (tchKit), attendance.
case 'stars': {
    if ($method !== 'GET') sch_fail('GET only', 405);
    $cls = (string)($_GET['cls'] ?? '');
    if ($cls === '' || !$canSee($cls)) sch_fail('You can see only your own classes.', 403);
    $today = date('Y-m-d'); $from = date('Y-m-d', strtotime('-30 days'));
    $kids = [];
    foreach ($db->query("SELECT r, d FROM sch_records WHERE k='student' AND deleted=0") as $row) { $d = json_decode((string)$row['d'], true); if (($d['cls'] ?? '') === $cls) $kids[(string)$row['r']] = (string)($d['name'] ?? ''); }
    $z = fn() => ['hw' => 0, 'hwOf' => 0, 'hwNot' => 0, 'kit' => 0, 'kitOf' => 0, 'kitMiss' => 0, 'abs' => 0];
    $p = []; foreach ($kids as $sid => $n) $p[$sid] = $z();
    $q = $db->prepare("SELECT k, r, d FROM sch_records WHERE k IN ('tchChk','tchKit','studAtt') AND deleted=0 AND r >= ? AND r < ?"); $q->execute([$from, date('Y-m-d', strtotime('+1 day'))]);   // next day as the end: '~' sorts before digits in this collation
    $attDays = 0; $chkN = 0; $kitN = 0;
    foreach ($q as $row) {
        $parts = explode('|', (string)$row['r']); if (($parts[1] ?? '') !== $cls) continue;
        $d = json_decode((string)$row['d'], true); if (!is_array($d)) continue;
        if ($row['k'] === 'studAtt') { $attDays++; foreach ((array)($d['absent'] ?? []) as $sid) if (isset($p[$sid]) && (($d['st'][$sid] ?? '') !== 'L')) $p[$sid]['abs']++; continue; }
        if ($row['k'] === 'tchChk') { $chkN++; foreach ((array)($d['rows'] ?? []) as $sid => $x) { if (!isset($p[$sid]) || ($x['hw'] ?? '') === '') continue; $p[$sid]['hwOf']++; if ($x['hw'] === 'done') $p[$sid]['hw']++; if ($x['hw'] === 'not') $p[$sid]['hwNot']++; } continue; }
        $kitN++; foreach ((array)($d['rows'] ?? []) as $sid => $x) { if (!isset($p[$sid])) continue; $p[$sid]['kitOf']++; $m = count(array_filter((array)$x, fn($v) => (int)$v === 0)); if ($m) $p[$sid]['kitMiss']++; else $p[$sid]['kit']++; }
    }
    $list = [];
    foreach ($p as $sid => $x) {
        $hwP = $x['hwOf'] ? $x['hw'] / $x['hwOf'] : null; $kitP = $x['kitOf'] ? $x['kit'] / $x['kitOf'] : null; $attP = $attDays ? ($attDays - $x['abs']) / $attDays : null;
        $parts = array_filter([$hwP, $kitP, $attP], fn($v) => $v !== null);
        $score = $parts ? array_sum($parts) / count($parts) : null;
        $why = [];
        if ($hwP !== null && $x['hwOf'] >= 2 && $hwP >= .9) $why[] = 'homework always done';
        if ($attP !== null && $attDays >= 5 && $x['abs'] === 0) $why[] = 'full attendance';
        if ($kitP !== null && $x['kitOf'] >= 2 && $kitP >= .99) $why[] = 'diary, ID, book, dress every day';
        $need = [];
        if ($x['hwNot'] >= 2) $need[] = 'homework not done ' . $x['hwNot'] . ' time(s)';
        if ($x['kitMiss'] >= 2) $need[] = 'diary/ID/book/dress missing ' . $x['kitMiss'] . ' day(s)';
        if ($attDays >= 5 && $x['abs'] >= 3) $need[] = 'absent ' . $x['abs'] . ' day(s)';
        $list[] = ['sid' => $sid, 'name' => $kids[$sid], 'score' => $score === null ? null : round($score * 100), 'why' => $why, 'need' => $need] + $x;
    }
    $stars = array_values(array_filter($list, fn($x) => $x['score'] !== null && $x['why']));
    usort($stars, fn($a, $b) => [$b['score'], count($b['why'])] <=> [$a['score'], count($a['why'])]);
    $needs = array_values(array_filter($list, fn($x) => $x['need'])); usort($needs, fn($a, $b) => count($b['need']) <=> count($a['need']));
    sch_json(['ok' => true, 'stars' => array_slice($stars, 0, 6), 'needs' => array_slice($needs, 0, 8), 'from' => $from, 'attDays' => $attDays, 'checks' => $chkN, 'kits' => $kitN]);
}

// ---- Staff chat (owner, 5 Nov 2026: "teachers bhi baat kar paye or chat record rakhiye") ----
// Kind tchMsg (never deleted — the record stays): {id, room, from, name, text, at}. room 'all' = Staff room (every
// teacher + Principal), 'dm|A|B' = two people (tids sorted; the Principal is 'P'). Teachers read only their rooms;
// the Principal and the Chairman read every room (the record).
case 'staffchat': {
    $mine = $role === 'teacher' ? $tid : 'P';
    $okRoom = function (string $room) use ($role, $mine): bool {
        if ($room === 'all') return true;
        if (!preg_match('/^dm\|([^|]{1,20})\|([^|]{1,20})$/', $room, $m)) return false;
        return $role !== 'teacher' || $m[1] === $mine || $m[2] === $mine;
    };
    if ($method === 'GET') {
        $room = (string)($_GET['room'] ?? ''); $since = (int)($_GET['since'] ?? 0);
        $from = sch_now_ms() - 120 * 86400000; $last = []; $msgs = [];
        foreach ($db->query("SELECT d FROM sch_records WHERE k='tchMsg' AND deleted=0") as $row) {
            $d = json_decode((string)$row['d'], true); if (!is_array($d) || (int)($d['at'] ?? 0) < $from) continue;
            $r = (string)($d['room'] ?? ''); if (!$okRoom($r)) continue;
            if (!isset($last[$r]) || $d['at'] > $last[$r]['at']) $last[$r] = $d;
            if ($r === $room && (int)$d['at'] > $since) $msgs[] = $d;
        }
        usort($msgs, fn($a, $b) => $a['at'] <=> $b['at']);
        sch_json(['ok' => true, 'me' => $mine, 'rooms' => array_values($last), 'messages' => array_slice($msgs, -300), 'now' => sch_now_ms()]);
    }
    $room = (string)($in['room'] ?? ''); $text = tch_txt($in['text'] ?? '', 1500);
    if ($text === '') sch_fail('Write a message.');
    if (preg_match('/^dm\|/', $room)) {
        $ab = explode('|', $room); $ok = count($ab) === 3 && ($ab[1] === $mine || $ab[2] === $mine);
        $other = $ab[1] === $mine ? ($ab[2] ?? '') : ($ab[1] ?? '');
        if (!$ok || ($other !== 'P' && !sch_rec_get('teacher', $other))) sch_fail('Choose a teacher to talk to.');
        $room = 'dm|' . implode('|', [min($ab[1], $ab[2]), max($ab[1], $ab[2])]);
    } elseif ($room !== 'all') sch_fail('Choose a chat.');
    $id = 'm' . bin2hex(random_bytes(8));
    $d = ['id' => $id, 'room' => $room, 'from' => $mine, 'name' => (string)($u['label'] ?? $mine), 'text' => $text, 'at' => sch_now_ms()];
    sch_rec_put('tchMsg', $id, $d, $uid);
    sch_json(['ok' => true, 'msg' => $d]);
}

// ---- Student report + record (owner, 5 Nov 2026: "record mention karne ka format — diary nahi hai, ID card nahi liya,
// WhatsApp student record add nahi kiya" + "class teacher ko total student report — complaint se lekar achhi baatein,
// comparison mein bachcha kya hai, learning, reading sab") ----
//   tchRec    'cls|sid' = {diary,id,book,dress,wa,waGroup (1 yes / 0 no), waNo, levels:{read,write,learn,speak,beh} 1–4, note}
//   tchRemark  <id>     = {id,sid,cls,type good|bad,cat,text,date,tid,by,at}  — kept as a record; the writer or Principal can delete
case 'report': {
    if ($method !== 'GET') sch_fail('GET only', 405);
    $cls = (string)($_GET['cls'] ?? '');
    if ($cls === '' || !$canSee($cls)) sch_fail('You can see only your own classes.', 403);
    $set = sch_rec_get('settings', 'main') ?: [];
    $start = (string)($set['sessionStart'] ?? ''); if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start)) $start = (date('n') < 4 ? date('Y') - 1 : date('Y')) . '-04-01';
    $kids = [];
    foreach ($db->query("SELECT r, d FROM sch_records WHERE k='student' AND deleted=0") as $row) { $d = json_decode((string)$row['d'], true); if (($d['cls'] ?? '') === $cls) $kids[(string)$row['r']] = true; }
    $z = ['a' => 0, 'l' => 0, 'hw' => 0, 'half' => 0, 'not' => 0, 'hwOf' => 0, 'kit' => 0, 'kitOf' => 0, 'miss' => ['d' => 0, 'i' => 0, 'b' => 0, 'u' => 0]];
    $per = []; foreach ($kids as $sid => $_) $per[$sid] = $z;
    $attDays = 0;
    $q = $db->prepare("SELECT k, r, d FROM sch_records WHERE k IN ('studAtt','tchChk','tchKit') AND deleted=0 AND r >= ? AND r < ?"); $q->execute([$start, date('Y-m-d', strtotime('+1 day'))]);
    foreach ($q as $row) {
        $pp = explode('|', (string)$row['r']); if (($pp[1] ?? '') !== $cls) continue;
        $d = json_decode((string)$row['d'], true); if (!is_array($d)) continue;
        if ($row['k'] === 'studAtt') { $attDays++; foreach ((array)($d['absent'] ?? []) as $sid) if (isset($per[$sid])) $per[$sid][(($d['st'][$sid] ?? '') === 'L') ? 'l' : 'a']++; }
        elseif ($row['k'] === 'tchChk') { foreach ((array)($d['rows'] ?? []) as $sid => $x) { $h = (string)($x['hw'] ?? ''); if (!isset($per[$sid]) || $h === '') continue; $per[$sid]['hwOf']++; $per[$sid][$h === 'done' ? 'hw' : ($h === 'half' ? 'half' : 'not')]++; } }
        else { foreach ((array)($d['rows'] ?? []) as $sid => $x) { if (!isset($per[$sid])) continue; $per[$sid]['kitOf']++; $bad = 0; foreach (['d', 'i', 'b', 'u'] as $f) if (isset($x[$f]) && (int)$x[$f] === 0) { $per[$sid]['miss'][$f]++; $bad++; } if (!$bad) $per[$sid]['kit']++; } }
    }
    // marks: latest term of this session with marks (Term-2, else Term-1) — total of each subject out of 100, averaged
    $ses = str_replace(['–', '—'], '-', trim((string)($set['session'] ?? ''))); $ses = preg_match('/^(20\d\d)-(?:20)?(\d\d)$/', $ses, $mm) ? $mm[1] . '-' . $mm[2] : '';
    $exam = ''; $marks = [];
    if ($ses !== '') foreach (['Term-2', 'Term-1'] as $ex) {
        $st = $db->prepare("SELECT d FROM sch_records WHERE k='prcMarks' AND deleted=0 AND r LIKE ?"); $st->execute([str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], "$ses|$ex|$cls|") . '%']);
        $acc = [];
        foreach ($st as $row) { $d = json_decode((string)$row['d'], true); foreach ((array)($d['rows'] ?? []) as $sid => $m) { if (!isset($per[$sid]) || !is_array($m)) continue; $t = 0; $any = false;
            foreach (['pt', 'nb', 'sea', 'te'] as $f) if (isset($m[$f]) && is_numeric($m[$f])) { $t += (float)$m[$f]; $any = true; }
            if ($any) $acc[$sid][] = min(100, $t); } }
        if ($acc) { $exam = $ex; foreach ($acc as $sid => $l) $marks[$sid] = round(array_sum($l) / count($l), 1); break; }
    }
    $recs = []; $st = $db->prepare("SELECT r, d FROM sch_records WHERE k='tchRec' AND deleted=0 AND r LIKE ?"); $st->execute([str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $cls) . '|%']);
    foreach ($st as $row) { [$c0, $sid] = array_pad(explode('|', (string)$row['r'], 2), 2, ''); if ($c0 === $cls) $recs[$sid] = json_decode((string)$row['d'], true); }
    $rem = [];
    foreach ($db->query("SELECT d FROM sch_records WHERE k='tchRemark' AND deleted=0") as $row) { $d = json_decode((string)$row['d'], true); if (is_array($d) && ($d['cls'] ?? '') === $cls && isset($kids[$d['sid'] ?? ''])) $rem[] = $d; }
    usort($rem, fn($a, $b) => ($b['at'] ?? 0) <=> ($a['at'] ?? 0));
    $logs = []; foreach ($db->query("SELECT d FROM sch_records WHERE k='tchLog' AND deleted=0") as $row) { $d = json_decode((string)$row['d'], true); if (is_array($d) && ($d['cls'] ?? '') === $cls && isset($kids[$d['sid'] ?? ''])) $logs[] = $d; }
    usort($logs, fn($a, $b) => [(string)($b['date'] ?? ''), (int)($b['at'] ?? 0)] <=> [(string)($a['date'] ?? ''), (int)($a['at'] ?? 0)]);
    sch_json(['ok' => true, 'logs' => array_slice($logs, 0, 3000), 'from' => $start, 'attDays' => $attDays, 'per' => (object)$per, 'marks' => (object)$marks, 'exam' => $exam,
              'recs' => (object)$recs, 'remarks' => array_slice($rem, 0, 1500), 'canEdit' => $canClass($cls), 'me' => $role === 'teacher' ? $tid : 'P']);
}

// ---- School board (owner, 5 Nov 2026: "total students ki profile, progress graph, ek notice board jisme competition
// type me serial wise dikhe"). Every class + every child of the session in ONE pass (same formulas as ?a=report):
// attendance % (session, this month, today), homework done %, daily check %, latest term marks %, score = average of
// what exists. Staff only (teachers, Principal, Chairman) — it is the staff-room notice board, never sent to parents.
case 'board': {
    if ($method !== 'GET') sch_fail('GET only', 405);
    $set = sch_rec_get('settings', 'main') ?: [];
    $start = (string)($set['sessionStart'] ?? ''); if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start)) $start = (date('n') < 4 ? date('Y') - 1 : date('Y')) . '-04-01';
    $today = date('Y-m-d'); $ym = date('Y-m');
    $classes = array_values(array_filter(array_column((array)sch_rec_get('classes', 'main'), 'c'), 'is_string'));
    $kids = []; $byCls = []; $gen = tch_genders($db);
    foreach ($db->query("SELECT r, d FROM sch_records WHERE k='student' AND deleted=0") as $row) { $d = json_decode((string)$row['d'], true); if (!is_array($d) || !in_array($d['cls'] ?? '', $classes, true)) continue;
        $sid = (string)$row['r']; $g = strtolower(substr(trim((string)($gen[$sid] ?? $d['gender'] ?? $d['sex'] ?? '')), 0, 1));
        $kids[$sid] = ['sid' => $sid, 'name' => (string)($d['name'] ?? ''), 'cls' => $d['cls'], 'g' => $g === 'm' || $g === 'b' ? 'b' : ($g === 'f' || $g === 'g' ? 'g' : ''),
                       'a' => 0, 'l' => 0, 'ma' => 0, 'hw' => 0, 'half' => 0, 'hwOf' => 0, 'kit' => 0, 'kitOf' => 0];
        $byCls[$d['cls']][] = $sid; }
    $attDays = []; $monDays = []; $todayAtt = [];
    $q = $db->prepare("SELECT k, r, d FROM sch_records WHERE k IN ('studAtt','tchChk','tchKit') AND deleted=0 AND r >= ? AND r < ?"); $q->execute([$start, date('Y-m-d', strtotime('+1 day'))]);
    foreach ($q as $row) {
        $pp = explode('|', (string)$row['r']); $cls = $pp[1] ?? ''; if (!isset($byCls[$cls])) continue;
        $d = json_decode((string)$row['d'], true); if (!is_array($d)) continue;
        if ($row['k'] === 'studAtt') { $attDays[$cls] = ($attDays[$cls] ?? 0) + 1; $inMon = substr($pp[0], 0, 7) === $ym; if ($inMon) $monDays[$cls] = ($monDays[$cls] ?? 0) + 1;
            if ($pp[0] === $today) $todayAtt[$cls] = count((array)($d['absent'] ?? []));
            foreach ((array)($d['absent'] ?? []) as $sid) if (isset($kids[$sid]) && $kids[$sid]['cls'] === $cls) { $kids[$sid][(($d['st'][$sid] ?? '') === 'L') ? 'l' : 'a']++; if ($inMon) $kids[$sid]['ma']++; } }
        elseif ($row['k'] === 'tchChk') { foreach ((array)($d['rows'] ?? []) as $sid => $x) { $h = (string)($x['hw'] ?? ''); if (!isset($kids[$sid]) || $h === '') continue; $kids[$sid]['hwOf']++; $kids[$sid][$h === 'done' ? 'hw' : ($h === 'half' ? 'half' : 'x')] = ($kids[$sid][$h === 'done' ? 'hw' : ($h === 'half' ? 'half' : 'x')] ?? 0) + 1; } }
        else { foreach ((array)($d['rows'] ?? []) as $sid => $x) { if (!isset($kids[$sid])) continue; $kids[$sid]['kitOf']++; $bad = 0; foreach (['d', 'i', 'b', 'u'] as $f) if (isset($x[$f]) && (int)$x[$f] === 0) $bad++; if (!$bad) $kids[$sid]['kit']++; } }
    }
    $ses = str_replace(['–', '—'], '-', trim((string)($set['session'] ?? ''))); $ses = preg_match('/^(20\d\d)-(?:20)?(\d\d)$/', $ses, $mm) ? $mm[1] . '-' . $mm[2] : '';
    $marks = []; $exam = '';
    if ($ses !== '') foreach (['Term-2', 'Term-1'] as $ex) {
        $st = $db->prepare("SELECT d FROM sch_records WHERE k='prcMarks' AND deleted=0 AND r LIKE ?"); $st->execute([str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], "$ses|$ex|") . '%']);
        $acc = [];
        foreach ($st as $row) { $d = json_decode((string)$row['d'], true); foreach ((array)($d['rows'] ?? []) as $sid => $m) { if (!isset($kids[$sid]) || !is_array($m)) continue; $t = 0; $any = false;
            foreach (['pt', 'nb', 'sea', 'te'] as $f) if (isset($m[$f]) && is_numeric($m[$f])) { $t += (float)$m[$f]; $any = true; } if ($any) $acc[$sid][] = min(100, $t); } }
        if ($acc) { $exam = $ex; foreach ($acc as $sid => $l) $marks[$sid] = round(array_sum($l) / count($l), 1); break; }
    }
    $pc = fn($a, $b) => $b ? (int)round($a / $b * 100) : null;
    $avg = function (array $v) { $v = array_values(array_filter($v, fn($x) => $x !== null)); return $v ? (int)round(array_sum($v) / count($v)) : null; };
    $out = []; $cout = [];
    foreach ($kids as $sid => $k) {
        $ad = $attDays[$k['cls']] ?? 0; $md = $monDays[$k['cls']] ?? 0;
        $att = $ad ? $pc($ad - $k['a'] - $k['l'], $ad) : null; $mon = $md ? $pc($md - $k['ma'], $md) : null;
        $hw = $k['hwOf'] ? (int)round(($k['hw'] + $k['half'] / 2) / $k['hwOf'] * 100) : null; $kit = $pc($k['kit'], $k['kitOf']); $mk = $marks[$sid] ?? null;
        $out[$sid] = ['sid' => $sid, 'name' => $k['name'], 'cls' => $k['cls'], 'g' => $k['g'], 'att' => $att, 'mon' => $mon, 'hw' => $hw, 'kit' => $kit, 'mk' => $mk, 'score' => $avg([$att, $hw, $kit, $mk])];
    }
    foreach ($classes as $c) { $ids = $byCls[$c] ?? []; $L = array_map(fn($s) => $out[$s], $ids);
        $col = fn($f) => $avg(array_column($L, $f));
        $cout[] = ['cls' => $c, 'n' => count($ids), 'boys' => count(array_filter($L, fn($x) => $x['g'] === 'b')), 'girls' => count(array_filter($L, fn($x) => $x['g'] === 'g')),
                   'days' => $attDays[$c] ?? 0, 'att' => $col('att'), 'mon' => $col('mon'), 'hw' => $col('hw'), 'kit' => $col('kit'), 'mk' => $col('mk'),
                   'today' => array_key_exists($c, $todayAtt) ? count($ids) - $todayAtt[$c] : null, 'score' => $avg([$col('att'), $col('hw'), $col('kit'), $col('mk')])]; }
    $ranked = array_values(array_filter($out, fn($x) => $x['score'] !== null));
    usort($ranked, fn($a, $b) => [$b['score'], $b['att'] ?? 0] <=> [$a['score'], $a['att'] ?? 0]);
    sch_json(['ok' => true, 'from' => $start, 'exam' => $exam, 'classes' => $cout, 'top' => array_slice($ranked, 0, 15), 'total' => count($kids),
              'students' => array_values($out)]);
}

// Boys / girls (owner, 5 Nov 2026: "Principal wale me add kar do, main abhi update kar dunga"). Principal only. Writes
// gender into the child's Student Full Record ('studinfo' row, same as Student Master; the earlier row goes to regHist).
case 'gender': {
    if ($method !== 'POST') sch_fail('POST only', 405);
    if ($role !== 'principal') sch_fail('Only the Principal can change this.', 403);
    $rows = is_array($in['rows'] ?? null) ? $in['rows'] : []; if (!$rows || count($rows) > 400) sch_fail('Nothing to save.');
    $info = [];
    foreach ($db->query("SELECT r, d FROM sch_records WHERE k='regRow' AND r LIKE 'studinfo|%' AND deleted=0") as $row) { $d = json_decode((string)$row['d'], true); if (is_array($d) && ($d['student'] ?? '') !== '') $info[(string)$d['student']] = [(string)$row['r'], $d]; }
    $n = 0; $db->beginTransaction();
    foreach ($rows as $sid => $g) {
        $sid = (string)$sid; if (!in_array($g, ['Male', 'Female'], true) || !sch_rec_get('student', $sid)) continue;
        [$key, $old] = $info[$sid] ?? ['studinfo|' . substr(hash('sha256', $sid . '|'), 0, 20), null];
        if ($old && ($old['gender'] ?? '') === $g) continue;
        if ($old) sch_rec_put('regHist', 'studinfo|' . substr($key, 9) . '|' . sch_now_ms() . $n, ['row' => $old, 'reason' => 'Gender set in the Teacher App', 'by' => $u['label'] ?? $uid], $uid);
        $new = ($old ?: ['student' => $sid, 'status' => 'Active']); $new['gender'] = $g; $new['_by'] = 'Teacher App (' . ($u['label'] ?? $uid) . ')'; $new['_at'] = sch_now_ms();
        sch_rec_put('regRow', $key, $new, $uid); $n++;
    }
    $db->commit();
    if ($n) sch_log($u, 'Student record', "Boys / girls set for $n child(ren) — Teacher App");
    sch_json(['ok' => true, 'saved' => $n]);
}

case 'rec': {
    if ($method !== 'POST') sch_fail('POST only', 405);
    $sid = (string)($in['sid'] ?? ''); $s = $sid !== '' ? sch_rec_get('student', $sid) : null;
    if (!$s) sch_fail('Student not found.', 404);
    $cls = (string)($s['cls'] ?? '');
    if (!$canClass($cls)) sch_fail('Only the class teacher or the Principal can change this record.', 403);
    $x = is_array($in['rec'] ?? null) ? $in['rec'] : [];
    $yn = fn($v) => $v === null || $v === '' ? null : ((int)$v ? 1 : 0);
    $lv = []; foreach (['read', 'write', 'learn', 'speak', 'beh'] as $f) { $v = (int)($x['levels'][$f] ?? 0); if ($v >= 1 && $v <= 4) $lv[$f] = $v; }
    $wa = preg_replace('/\D/', '', (string)($x['waNo'] ?? '')); $wa = strlen($wa) >= 10 ? substr($wa, -10) : '';
    $prev = sch_rec_get('tchRec', "$cls|$sid") ?: []; $hist = array_values((array)($prev['lvHist'] ?? []));
    if ($lv != (array)($prev['levels'] ?? [])) { $hist = array_values(array_filter($hist, fn($e) => ($e['d'] ?? '') !== date('Y-m-d'))); $hist[] = ['d' => date('Y-m-d'), 'l' => (object)$lv]; $hist = array_slice($hist, -24); }
    $d = ['diary' => $yn($x['diary'] ?? null), 'id' => $yn($x['id'] ?? null), 'book' => $yn($x['book'] ?? null), 'dress' => $yn($x['dress'] ?? null),
          'wa' => $yn($x['wa'] ?? null), 'waGroup' => $yn($x['waGroup'] ?? null), 'waNo' => $wa, 'levels' => (object)$lv, 'note' => tch_txt($x['note'] ?? '', 500),
          'lvHist' => $hist, 'by' => $u['label'], 'at' => sch_now_ms()];
    sch_rec_put('tchRec', "$cls|$sid", $d, $uid);
    $miss = []; foreach (['diary' => 'diary', 'id' => 'ID card', 'book' => 'books', 'dress' => 'dress', 'wa' => 'WhatsApp in record'] as $f => $l) if ($d[$f] === 0) $miss[] = $l;
    sch_log($u, 'Student record', 'Record of ' . ($s['name'] ?? $sid) . ' (Class ' . $cls . ') updated' . ($miss ? ' — not yet: ' . implode(', ', $miss) : '') . ' — Teacher App');
    sch_json(['ok' => true, 'rec' => $d]);
}

// One-time bulk entry for a whole class (owner, 6 Nov 2026: "learning / reading ek baar bulk me entry ho jaye, uske baad
// daily me rahe"). Class teacher / Principal. Merges into each child's tchRec (other fields kept); every change of levels
// is also kept in lvHist [{d, l}] (last 24) so progress over the year can be seen.
case 'recbulk': {
    if ($method !== 'POST') sch_fail('POST only', 405);
    $cls = (string)($in['cls'] ?? ''); if ($cls === '' || !$canClass($cls)) sch_fail('Only the class teacher or the Principal can fill this.', 403);
    $rows = is_array($in['rows'] ?? null) ? $in['rows'] : []; if (!$rows || count($rows) > 200) sch_fail('Nothing to save.');
    $today = date('Y-m-d'); $n = 0; $db->beginTransaction();
    foreach ($rows as $sid => $x) {
        $sid = (string)$sid; $s = sch_rec_get('student', $sid); if (!$s || ($s['cls'] ?? '') !== $cls || !is_array($x)) continue;
        $old = sch_rec_get('tchRec', "$cls|$sid") ?: []; $d = $old;
        if (isset($x['levels']) && is_array($x['levels'])) { $lv = (array)($old['levels'] ?? []);
            foreach (['read', 'write', 'learn', 'speak', 'beh'] as $f) if (array_key_exists($f, $x['levels'])) { $v = (int)$x['levels'][$f]; if ($v >= 1 && $v <= 4) $lv[$f] = $v; else unset($lv[$f]); }
            if ($lv != (array)($old['levels'] ?? [])) { $h = array_values(array_filter((array)($old['lvHist'] ?? []), fn($e) => ($e['d'] ?? '') !== $today)); $h[] = ['d' => $today, 'l' => $lv]; $d['lvHist'] = array_slice($h, -24); }
            $d['levels'] = $lv; }
        foreach (['diary', 'id', 'book', 'dress', 'wa', 'waGroup'] as $f) if (array_key_exists($f, $x)) $d[$f] = $x[$f] === null || $x[$f] === '' ? null : ((int)$x[$f] ? 1 : 0);
        if ($d == $old) continue;
        $d['by'] = $u['label']; $d['at'] = sch_now_ms(); $d['levels'] = (object)($d['levels'] ?? []);
        if (isset($d['lvHist'])) $d['lvHist'] = array_map(fn($e) => ['d' => $e['d'] ?? '', 'l' => (object)($e['l'] ?? [])], (array)$d['lvHist']);
        sch_rec_put('tchRec', "$cls|$sid", $d, $uid); $n++;
    }
    $db->commit();
    if ($n) sch_log($u, 'Student record', "Class $cls: levels / record filled for $n child(ren) together — Teacher App");
    sch_json(['ok' => true, 'saved' => $n]);
}

// Student log (owner, 6 Nov 2026: parent contact, PTM attendance, competition / activity). Kind tchLog:
//   call  — one talk with a parent: {sid, cls, kind:'call', date, mode: Call|Visit|WhatsApp|Meeting, text}   (id 'c…', any teacher of the class)
//   ptm   — PTM attended: key 'ptm|date|cls|sid' {present 1|0, title}                                      (class teacher / Principal, bulk)
//   act   — activity / competition: key 'act|date|cls|sid|<8 hex of title>' {title, pos: part|1|2|3}       (any teacher of the class, bulk)
case 'log': {
    if ($method !== 'POST') sch_fail('POST only', 405);
    $sid = (string)($in['sid'] ?? ''); $s = $sid !== '' ? sch_rec_get('student', $sid) : null; if (!$s) sch_fail('Student not found.', 404);
    $cls = (string)($s['cls'] ?? ''); if (!$canSee($cls)) sch_fail('You can write only about children of your own classes.', 403);
    $text = tch_txt($in['text'] ?? '', 500); if ($text === '') sch_fail('Write what was talked about.');
    $date = (string)($in['date'] ?? date('Y-m-d')); if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || $date > date('Y-m-d')) $date = date('Y-m-d');
    $mode = in_array($in['mode'] ?? '', ['Call', 'Visit', 'WhatsApp', 'Meeting'], true) ? $in['mode'] : 'Call';
    $id = 'c' . bin2hex(random_bytes(8));
    $d = ['id' => $id, 'sid' => $sid, 'cls' => $cls, 'kind' => 'call', 'date' => $date, 'mode' => $mode, 'text' => $text, 'tid' => $role === 'teacher' ? $tid : 'P', 'by' => $u['label'], 'at' => sch_now_ms()];
    sch_rec_put('tchLog', $id, $d, $uid);
    sch_log($u, 'Parent contact', $mode . ' with the parent of ' . ($s['name'] ?? $sid) . ' (Class ' . $cls . '): ' . $text);
    sch_json(['ok' => true, 'log' => $d]);
}
case 'logbulk': {
    if ($method !== 'POST') sch_fail('POST only', 405);
    $cls = (string)($in['cls'] ?? ''); $kind = (string)($in['kind'] ?? '');
    if (!in_array($kind, ['ptm', 'act'], true)) sch_fail('Bad request.');
    if ($kind === 'ptm' ? !$canClass($cls) : !$canSee($cls)) sch_fail($kind === 'ptm' ? 'Only the class teacher or the Principal can fill PTM attendance.' : 'Only teachers of this class can fill this.', 403);
    $date = (string)($in['date'] ?? ''); if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || $date > date('Y-m-d')) sch_fail('Choose the date (today or earlier).');
    $title = tch_txt($in['title'] ?? '', 120); if ($kind === 'act' && $title === '') sch_fail('Write the name of the activity / competition.');
    $rows = is_array($in['rows'] ?? null) ? $in['rows'] : []; if (!$rows || count($rows) > 200) sch_fail('Nothing to save.');
    $n = 0; $db->beginTransaction();
    foreach ($rows as $sid => $v) {
        $sid = (string)$sid; $s = sch_rec_get('student', $sid); if (!$s || ($s['cls'] ?? '') !== $cls) continue;
        $key = $kind === 'ptm' ? "ptm|$date|$cls|$sid" : "act|$date|$cls|$sid|" . substr(md5(mb_strtolower($title)), 0, 8);
        if ($v === '' || $v === null) { if (sch_rec_get('tchLog', $key)) { sch_rec_del('tchLog', $key, $uid); $n++; } continue; }
        if ($kind === 'ptm') { $d = ['sid' => $sid, 'cls' => $cls, 'kind' => 'ptm', 'date' => $date, 'present' => (int)$v ? 1 : 0, 'title' => $title ?: 'PTM']; }
        else { if (!in_array((string)$v, ['part', '1', '2', '3'], true)) continue; $d = ['sid' => $sid, 'cls' => $cls, 'kind' => 'act', 'date' => $date, 'title' => $title, 'pos' => (string)$v]; }
        $old = sch_rec_get('tchLog', $key); if ($old && array_intersect_key($old, $d) == $d) continue;
        $d += ['tid' => $role === 'teacher' ? $tid : 'P', 'by' => $u['label'], 'at' => sch_now_ms()];
        sch_rec_put('tchLog', $key, $d, $uid); $n++;
    }
    $db->commit();
    if ($n) sch_log($u, $kind === 'ptm' ? 'PTM' : 'Activity', "Class $cls — " . ($kind === 'ptm' ? 'PTM attendance' : $title) . " ($date): $n child(ren) — Teacher App");
    sch_json(['ok' => true, 'saved' => $n]);
}
case 'dellog': {
    if ($method !== 'POST') sch_fail('POST only', 405);
    $id = (string)($in['id'] ?? ''); $old = preg_match('/^c[a-f0-9]{16}$/', $id) ? sch_rec_get('tchLog', $id) : null; if (!$old) sch_fail('Not found.', 404);
    if ($role !== 'principal' && ($old['tid'] ?? '') !== $tid) sch_fail('Only the teacher who wrote it can delete it.', 403);
    sch_rec_del('tchLog', $id, $uid); sch_json(['ok' => true]);
}

// ---- Competitions (owner, 6 Nov 2026: Rangoli + Diya making — PG–UKG, 1st–5th, 6th–10th; teachers choose their
// children, alone or as a named group; positions; a certificate for each child and each group).
// Kind tchComp: 'ev|<id>' = {id, title, date, cats[], secs[{name, classes[]}], by, at}   (Principal)
//               'en|<event>|<id>' = {id, ev, cat, sec, cls, type: solo|group, name, sids[], pos: ''|1|2|3, by, tid, at}
//               (any teacher of the children's class / Principal; positions: Principal only, then 'Submit result'
//               locks them in ev.res['<cat>|<sec>'] — teachers see positions only after that; 'Edit result' unlocks)
// Each entry also writes the children's activity log (tchLog 'act', title "<event> — <cat>") so it shows in their report.
case 'coord': {
    // Coordinator (owner, 6 Nov 2026): the Principal names a teacher coordinator for some classes; the app hands her the work
    // (copy check by rotation with photo proof, diary check, reading levels, worksheets, thought of the day), a random
    // blind re-check by another teacher, the checked teacher's reply, and a weekly report card for the Principal.
    // Records: tchCoord  c|<tid> assignment · cc|<id> copy check · cp|<id>|<i> its photos · vf|<id> re-check task ·
    //          dc|<date>|<tid> diary marks · rd|<YYYY-MM>|<cls> reading levels · ws|<id> worksheet · sv|<date> thought.
    $rows = []; foreach ($db->query("SELECT r, d FROM sch_records WHERE k='tchCoord' AND deleted=0 AND r NOT LIKE 'cp|%'") as $row) { $d = json_decode((string)$row['d'], true); if (is_array($d)) $rows[(string)$row['r']] = $d; }
    $me = $role === 'teacher' ? $tid : 'P'; $myC = $rows["c|$me"] ?? null; $isCo = $role === 'teacher' && $myC;
    $coCls = fn(string $cls): bool => $role === 'principal' || ($isCo && in_array($cls, (array)$myC['classes'], true));
    if ($method === 'GET') {
        if (($_GET['cp'] ?? '') !== '') { $k = preg_replace('/[^a-z0-9|]/', '', (string)$_GET['cp']); $cc = $rows['cc|' . explode('|', $k)[0]] ?? null;
            if (!$cc || !($role === 'principal' || $cc['by'] === $me || $cc['tid'] === $me)) sch_fail('Not allowed.', 403);
            $p = sch_rec_get('tchCoord', "cp|$k"); sch_json(['ok' => true, 'data' => $p['data'] ?? '']); }
        $out = []; $from = date('Y-m-d', strtotime('-70 days'));
        foreach ($rows as $r => $d) { $t = explode('|', $r)[0];
            $ok = match ($t) { 'c', 'sv', 'ws' => true,
                'cc' => ($d['date'] ?? '') >= $from && ($role === 'principal' || $d['by'] === $me || $d['tid'] === $me),
                'vf' => $role === 'principal' || ($d['to'] ?? '') === $me || ($rows['cc|' . ($d['cc'] ?? '')]['by'] ?? '') === $me,
                'dc', 'rd' => $role === 'principal' || $isCo, default => false };
            if ($t === 'vf' && ($d['to'] ?? '') === $me && !isset($d['res'])) $d = array_diff_key($d, ['want' => 1]);   // blind: the re-checker never sees the coordinator's marks
            if ($ok) $out[$r] = $d; }
        sch_json(['ok' => true, 'rows' => (object)$out]);
    }
    $op = (string)($in['op'] ?? ''); $now = sch_now_ms(); $date = date('Y-m-d');
    $classes = array_column((array)sch_rec_get('classes', 'main'), 'c');
    $pts = fn($p) => array_map(fn($k) => (int)!empty(((array)$p)[$k]), array_combine(['d', 'cw', 'hw', 'sg', 'cr', 'nt'], ['d', 'cw', 'hw', 'sg', 'cr', 'nt']));
    if ($op === 'assign') {
        if ($role !== 'principal') sch_fail('Only the Principal names a coordinator.', 403);
        $t = (string)($in['tid'] ?? ''); if (!preg_match('/^[A-Za-z0-9_-]{1,20}$/', $t) || !sch_rec_get('teacher', $t)) sch_fail('Choose the teacher.');
        $cl = array_values(array_intersect(array_map('strval', (array)($in['classes'] ?? [])), $classes));
        if (!$cl) { sch_rec_del('tchCoord', "c|$t", $uid); sch_json(['ok' => true]); }
        sch_rec_put('tchCoord', "c|$t", ['tid' => $t, 'classes' => $cl, 'at' => $now], $uid); sch_log($u, 'Coordinator', "Coordinator: $t for " . implode(', ', $cl)); sch_json(['ok' => true]);
    }
    if ($op === 'cc') {
        $cls = (string)($in['cls'] ?? ''); if (!$coCls($cls)) sch_fail('Only the coordinator of this class can check copies.', 403);
        $t = (string)($in['tid'] ?? ''); $subj = tch_txt($in['subj'] ?? '', 40); if ($t === '' || $subj === '') sch_fail('Choose the subject.');
        $kids = tch_list($in['kids'] ?? [], 8, function ($k) use ($cls, $pts) { $s = sch_rec_get('student', (string)($k['sid'] ?? '')); return $s && ($s['cls'] ?? '') === $cls ? ['sid' => (string)$k['sid'], 'p' => $pts($k['p'] ?? [])] : null; });
        if (count($kids) < 3) sch_fail('Check at least 3 copies.');
        $good = tch_txt($in['good'] ?? '', 200); if ($good === '') sch_fail('Write one good thing (kya achha tha).');
        $id = bin2hex(random_bytes(6)); $sum = 0; foreach ($kids as $k) $sum += array_sum($k['p']);
        $d = ['id' => $id, 'date' => $date, 'cls' => $cls, 'subj' => $subj, 'tid' => $t, 'by' => $me, 'kids' => $kids, 'good' => $good, 'remark' => tch_txt($in['remark'] ?? '', 300), 'score' => round($sum / count($kids), 1), 'photos' => 0, 'at' => $now];
        sch_rec_put('tchCoord', "cc|$id", $d, $uid);
        // blind re-check of one copy by another teacher (never the coordinator, never the checked teacher)
        $pool = []; foreach ($db->query("SELECT r FROM sch_records WHERE k='teacher' AND deleted=0") as $row) { $x = (string)$row['r']; if ($x !== $me && $x !== $t && $x !== 'P') $pool[] = $x; }
        if ($pool) { $k = $kids[random_int(0, count($kids) - 1)]; $vid = bin2hex(random_bytes(6));
            sch_rec_put('tchCoord', "vf|$vid", ['id' => $vid, 'cc' => $id, 'to' => $pool[random_int(0, count($pool) - 1)], 'cls' => $cls, 'subj' => $subj, 'sid' => $k['sid'], 'want' => $k['p'], 'date' => $date, 'at' => $now], $uid); }
        sch_log($u, 'Coordinator', "Copy check: Class $cls $subj ($t) — score {$d['score']}/6"); sch_json(['ok' => true, 'cc' => $d]);
    }
    if ($op === 'ccphoto') {
        $id = (string)($in['id'] ?? ''); $i = (int)($in['i'] ?? -1); $cc = $rows["cc|$id"] ?? null; if (!$cc || $cc['by'] !== $me || $i < 0 || $i > 7) sch_fail('Not allowed.', 403);
        $data = (string)($in['data'] ?? ''); if (!preg_match('#^data:image/jpeg;base64,[A-Za-z0-9+/=]+$#', $data) || strlen($data) > 380000) sch_fail('Send a smaller photo.');
        sch_rec_put('tchCoord', "cp|$id|$i", ['data' => $data, 'at' => $now], $uid); $cc['photos'] = (int)$cc['photos'] | (1 << $i); sch_rec_put('tchCoord', "cc|$id", $cc, $uid); sch_json(['ok' => true]);
    }
    if ($op === 'vf') {
        $id = (string)($in['id'] ?? ''); $v = $rows["vf|$id"] ?? null; if (!$v || $v['to'] !== $me) sch_fail('Not your task.', 403); if (isset($v['res'])) sch_fail('Already done.');
        $res = $pts($in['p'] ?? []); $same = 0; foreach ($res as $k => $x) $same += (int)($x === (int)($v['want'][$k] ?? 0));
        $v['res'] = $res; $v['match'] = $same; $v['doneAt'] = $now; sch_rec_put('tchCoord', "vf|$id", $v, $uid); sch_json(['ok' => true, 'match' => $same]);
    }
    if ($op === 'resp') {
        $id = (string)($in['id'] ?? ''); $cc = $rows["cc|$id"] ?? null; if (!$cc || $cc['tid'] !== $me) sch_fail('Not allowed.', 403);
        $ans = ($in['ans'] ?? '') === 'no' ? 'no' : 'ok'; $cc['resp'] = ['ans' => $ans, 'note' => tch_txt($in['note'] ?? '', 300), 'at' => $now];
        sch_rec_put('tchCoord', "cc|$id", $cc, $uid); sch_json(['ok' => true]);
    }
    if ($op === 'decide') {
        if ($role !== 'principal') sch_fail('Only the Principal decides.', 403);
        $id = (string)($in['id'] ?? ''); $cc = $rows["cc|$id"] ?? null; if (!$cc || ($cc['resp']['ans'] ?? '') !== 'no') sch_fail('Nothing to decide.');
        $cc['resp']['decision'] = ($in['who'] ?? '') === 'teacher' ? 'teacher' : 'coord'; sch_rec_put('tchCoord', "cc|$id", $cc, $uid); sch_json(['ok' => true]);
    }
    if ($op === 'dc') {
        if (!$isCo) sch_fail('Only a coordinator.', 403); $dd = (string)($in['date'] ?? ''); if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dd)) sch_fail('Bad date.');
        $marks = []; foreach (array_slice((array)($in['marks'] ?? []), 0, 200, true) as $k => $v) if (preg_match('/^[A-Za-z0-9_-]{1,20}\|\d{1,2}$/', (string)$k)) $marks[$k] = $v ? 1 : 0;
        sch_rec_put('tchCoord', "dc|$dd|$me", ['date' => $dd, 'by' => $me, 'marks' => $marks, 'at' => $now], $uid); sch_json(['ok' => true]);
    }
    if ($op === 'rd') {
        $cls = (string)($in['cls'] ?? ''); if (!$coCls($cls)) sch_fail('Only the coordinator of this class.', 403); $m = date('Y-m');
        $lv = []; foreach (array_slice((array)($in['levels'] ?? []), 0, 80, true) as $sid => $v) if (in_array((int)$v, [1, 2, 3, 4, 5], true)) $lv[(string)$sid] = (int)$v;
        sch_rec_put('tchCoord', "rd|$m|$cls", ['month' => $m, 'cls' => $cls, 'by' => $me, 'levels' => (object)$lv, 'date' => $date, 'at' => $now], $uid); sch_json(['ok' => true]);
    }
    if ($op === 'ws') {
        if (!$isCo && $role !== 'principal') sch_fail('Only the coordinator.', 403); $cls = (string)($in['cls'] ?? ''); if (!in_array($cls, $classes, true)) sch_fail('Choose the class.');
        $qs = tch_list($in['qs'] ?? [], 12, fn($q) => ($t = tch_txt($q, 300)) === '' ? null : $t); if (count($qs) < 3) sch_fail('Write at least 3 questions.');
        $id = bin2hex(random_bytes(6)); $d = ['id' => $id, 'cls' => $cls, 'subj' => tch_txt($in['subj'] ?? '', 40), 'ch' => tch_txt($in['ch'] ?? '', 80), 'qs' => $qs, 'by' => $me, 'date' => $date, 'at' => $now];
        sch_rec_put('tchCoord', "ws|$id", $d, $uid); sch_json(['ok' => true, 'ws' => $d]);
    }
    if ($op === 'sv') {
        if (!$isCo && $role !== 'principal') sch_fail('Only the coordinator.', 403); $dd = (string)($in['date'] ?? ''); if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dd)) sch_fail('Bad date.');
        $txt = tch_txt($in['text'] ?? '', 200); if ($txt === '') sch_fail('Write the thought.');
        sch_rec_put('tchCoord', "sv|$dd", ['date' => $dd, 'text' => $txt, 'word' => tch_txt($in['word'] ?? '', 80), 'by' => $me, 'at' => $now], $uid); sch_json(['ok' => true]);
    }
    sch_fail('Bad request.');
}

case 'comp': {
    if ($method === 'GET') {
        $ev = []; $en = [];
        if (($_GET['sg'] ?? '') !== '') { $out = []; foreach ($db->query("SELECT r, d FROM sch_records WHERE k='tchComp' AND deleted=0 AND r LIKE 'sg|%'") as $row) { $d = json_decode((string)$row['d'], true); if (is_array($d)) $out[substr((string)$row['r'], 3)] = $d['data'] ?? ''; } sch_json(['ok' => true, 'signs' => $out]); }
        if (($_GET['ph'] ?? '') !== '') { $p = sch_rec_get('tchComp', (($_GET['k'] ?? '') === 'w' ? 'pr|' : 'ph|') . preg_replace('/[^a-z0-9|]/', '', (string)$_GET['ph'])); sch_json(['ok' => true, 'data' => $p['data'] ?? '']); }
        foreach ($db->query("SELECT r, d FROM sch_records WHERE k='tchComp' AND deleted=0 AND r NOT LIKE 'ph|%' AND r NOT LIKE 'pr|%' AND r NOT LIKE 'sg|%'") as $row) { $d = json_decode((string)$row['d'], true); if (!is_array($d)) continue;
            if (str_starts_with((string)$row['r'], 'ev|')) $ev[] = $d; else $en[] = $d; }
        usort($ev, fn($a, $b) => strcmp((string)($b['date'] ?? ''), (string)($a['date'] ?? '')));
        sch_json(['ok' => true, 'events' => $ev, 'entries' => $en]);
    }
    $op = (string)($in['op'] ?? '');
    $classes = array_column((array)sch_rec_get('classes', 'main'), 'c');
    if ($op === 'event') {
        if ($role !== 'principal') sch_fail('Only the Principal can create a competition.', 403);
        $id = preg_match('/^e[a-f0-9]{6,20}$/', (string)($in['id'] ?? '')) ? $in['id'] : 'e' . bin2hex(random_bytes(6));
        $title = tch_txt($in['title'] ?? '', 120); if ($title === '') sch_fail('Write the name of the competition.');
        $date = (string)($in['date'] ?? ''); if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) sch_fail('Choose the date.');
        $cats = tch_list($in['cats'] ?? [], 8, fn($x) => ($t = tch_txt($x, 40)) === '' ? null : $t); if (!$cats) sch_fail('Add at least one competition (e.g. Rangoli).');
        $secs = tch_list($in['secs'] ?? [], 8, fn($x) => is_array($x) && ($n = tch_txt($x['name'] ?? '', 40)) !== '' ? ['name' => $n, 'classes' => array_values(array_intersect((array)($x['classes'] ?? []), $classes))] : null);
        if (!$secs) sch_fail('Add at least one group of classes.');
        $d = ['id' => $id, 'title' => $title, 'date' => $date, 'cats' => $cats, 'secs' => $secs, 'by' => $u['label'], 'at' => sch_now_ms()];
        sch_rec_put('tchComp', "ev|$id", $d, $uid); sch_log($u, 'Competition', "Competition: $title ($date) — " . implode(', ', $cats));
        sch_json(['ok' => true, 'event' => $d]);
    }
    if ($op === 'sign') {           // own signature (drawn on the phone) for certificates — Principal = 'P'
        $who = $role === 'principal' ? 'P' : ($role === 'teacher' ? $tid : ''); if ($who === '') sch_fail('Not allowed.', 403);
        $data = (string)($in['data'] ?? '');
        if ($data === '') { sch_rec_del('tchComp', "sg|$who", $uid); sch_json(['ok' => true]); }
        if (!preg_match('#^data:image/png;base64,[A-Za-z0-9+/=]+$#', $data) || strlen($data) > 200000) sch_fail('Sign again — the picture is too big.');
        sch_rec_put('tchComp', "sg|$who", ['data' => $data, 'by' => $u['label'], 'at' => sch_now_ms()], $uid); sch_log($u, 'Competition', 'Saved signature for certificates');
        sch_json(['ok' => true]);
    }
    if ($op === 'photo') {          // rangoli / diya photo of one entry (jpeg data URL, made small in the browser)
        $evId = (string)($in['ev'] ?? ''); $id = (string)($in['id'] ?? '');
        $old = preg_match('/^e[a-f0-9]{6,20}$/', $evId) && preg_match('/^n[a-f0-9]{8,20}$/', $id) ? sch_rec_get('tchComp', "en|$evId|$id") : null; if (!$old) sch_fail('Entry not found.', 404);
        if ($role === 'teacher' && !sch_in_class($tid, (string)$old['cls']) && !tch_is_ct($tid, (string)$old['cls'])) sch_fail('Only teachers of this class can add the photo.', 403);
        $data = (string)($in['data'] ?? ''); if (!preg_match('#^data:image/jpeg;base64,[A-Za-z0-9+/=]+$#', $data) || strlen($data) > 700000) sch_fail('Send a smaller JPEG photo.');
        // two photos per entry (owner, 6 Nov 2026): kind 'team' (ph|, the one teachers already added) and kind 'w' = the rangoli / diya itself (pr|)
        $w = ($in['kind'] ?? '') === 'w';
        sch_rec_put('tchComp', ($w ? 'pr|' : 'ph|') . "$evId|$id", ['data' => $data, 'by' => $u['label'], 'at' => sch_now_ms()], $uid);
        $old[$w ? 'photoW' : 'photo'] = sch_now_ms(); sch_rec_put('tchComp', "en|$evId|$id", $old, $uid);
        sch_json(['ok' => true, 'entry' => $old]);
    }
    if ($op === 'res') {            // Principal submits (lock) / edits (unlock) the result of one competition + section
        if ($role !== 'principal') sch_fail('Only the Principal submits the result.', 403);
        $evId = (string)($in['ev'] ?? ''); $ev = preg_match('/^e[a-f0-9]{6,20}$/', $evId) ? sch_rec_get('tchComp', "ev|$evId") : null; if (!$ev) sch_fail('Competition not found.', 404);
        $cat = (string)($in['cat'] ?? ''); $sec = (string)($in['sec'] ?? '');
        if (!in_array($cat, (array)$ev['cats'], true) || !in_array($sec, array_column((array)$ev['secs'], 'name'), true)) sch_fail('Choose the competition and group.');
        $res = (array)($ev['res'] ?? []);
        if (!empty($in['lock'])) $res["$cat|$sec"] = ['by' => $u['label'], 'at' => sch_now_ms()]; else unset($res["$cat|$sec"]);
        $ev['res'] = (object)$res; sch_rec_put('tchComp', "ev|$evId", $ev, $uid);
        sch_log($u, 'Competition', (!empty($in['lock']) ? 'Result submitted: ' : 'Result opened for edit: ') . $ev['title'] . " — $cat · $sec");
        sch_json(['ok' => true, 'event' => $ev]);
    }
    if ($op === 'entry' || $op === 'pos' || $op === 'del') {
        $evId = (string)($in['ev'] ?? ''); $ev = preg_match('/^e[a-f0-9]{6,20}$/', $evId) ? sch_rec_get('tchComp', "ev|$evId") : null; if (!$ev) sch_fail('Competition not found.', 404);
        $id = (string)($in['id'] ?? ''); $old = preg_match('/^n[a-f0-9]{8,20}$/', $id) ? sch_rec_get('tchComp', "en|$evId|$id") : null;
        if ($op !== 'entry' && !$old) sch_fail('Entry not found.', 404);
        if ($op === 'entry') {
            $cat = (string)($in['cat'] ?? ''); if (!in_array($cat, (array)$ev['cats'], true)) sch_fail('Choose the competition.');
            $sids = array_values(array_unique(array_filter((array)($in['sids'] ?? []), 'is_string'))); if (!$sids || count($sids) > 20) sch_fail('Choose the children.');
            $cls = ''; foreach ($sids as $sid) { $s = sch_rec_get('student', $sid); if (!$s) sch_fail('Student not found.'); if ($cls === '') $cls = (string)$s['cls']; if ((string)$s['cls'] !== $cls) sch_fail('A group must be from one class.'); }
            if (!$canSee($cls)) sch_fail('You can choose only children of your own classes.', 403);
            $sec = ''; foreach ((array)$ev['secs'] as $x) if (in_array($cls, (array)$x['classes'], true)) { $sec = $x['name']; break; } if ($sec === '') sch_fail("Class $cls is not in this competition.");
            $type = count($sids) > 1 || ($in['type'] ?? '') === 'group' ? 'group' : 'solo'; $name = tch_txt($in['name'] ?? '', 60); if ($type === 'group' && $name === '') sch_fail('Write the group name.');
            $id = $old ? $id : 'n' . bin2hex(random_bytes(6));
            $d = ['id' => $id, 'ev' => $evId, 'cat' => $cat, 'sec' => $sec, 'cls' => $cls, 'type' => $type, 'name' => $name, 'sids' => $sids, 'pos' => (string)($old['pos'] ?? ''), 'by' => $u['label'], 'tid' => $role === 'teacher' ? $tid : 'P', 'at' => sch_now_ms()];
        } elseif ($op === 'pos') {
            // owner, 6 Nov 2026: "ranker ko bas Principal select karke submit kare, uske baad edit ka option aaye"
            if ($role !== 'principal') sch_fail('Only the Principal chooses the positions.', 403);
            if (!empty(((array)($ev['res'] ?? []))[$old['cat'] . '|' . $old['sec']])) sch_fail('Result is submitted. Tap “Edit result” first.');
            $pos = (string)($in['pos'] ?? ''); if (!in_array($pos, ['', '1', '2', '3'], true)) sch_fail('Bad position.');
            $d = $old; $d['pos'] = $pos; $d['posBy'] = $u['label']; $d['at'] = sch_now_ms();
        } else {
            if ($role !== 'principal' && ($old['tid'] ?? '') !== $tid) sch_fail('Only the teacher who added it can remove it.', 403);
            sch_rec_del('tchComp', "en|$evId|$id", $uid);
            foreach ((array)$old['sids'] as $sid) { $k = 'act|' . $ev['date'] . '|' . $old['cls'] . '|' . $sid . '|' . substr(md5(mb_strtolower($ev['title'] . ' — ' . $old['cat'])), 0, 8); if (sch_rec_get('tchLog', $k)) sch_rec_del('tchLog', $k, $uid); }
            sch_json(['ok' => true]);
        }
        sch_rec_put('tchComp', "en|$evId|$id", $d, $uid);
        $lt = $ev['title'] . ' — ' . $d['cat'];
        foreach ((array)$d['sids'] as $sid) sch_rec_put('tchLog', 'act|' . $ev['date'] . '|' . $d['cls'] . '|' . $sid . '|' . substr(md5(mb_strtolower($lt)), 0, 8),
            ['sid' => $sid, 'cls' => $d['cls'], 'kind' => 'act', 'date' => $ev['date'], 'title' => $lt . ($d['type'] === 'group' ? ' (group ' . $d['name'] . ')' : ''), 'pos' => $d['pos'] === '' ? 'part' : $d['pos'], 'tid' => $d['tid'], 'by' => $u['label'], 'at' => sch_now_ms()], $uid);
        sch_json(['ok' => true, 'entry' => $d]);
    }
    sch_fail('Bad request.');
}

case 'remark': {
    if ($method !== 'POST') sch_fail('POST only', 405);
    $sid = (string)($in['sid'] ?? ''); $s = $sid !== '' ? sch_rec_get('student', $sid) : null;
    if (!$s) sch_fail('Student not found.', 404);
    $cls = (string)($s['cls'] ?? '');
    if (!$canSee($cls)) sch_fail('You can write only about children of your own classes.', 403);
    $type = ($in['type'] ?? '') === 'good' ? 'good' : (($in['type'] ?? '') === 'bad' ? 'bad' : '');
    if ($type === '') sch_fail('Choose: complaint or good thing.');
    $text = tch_txt($in['text'] ?? '', 500); if ($text === '') sch_fail('Write what happened.');
    $date = (string)($in['date'] ?? date('Y-m-d')); if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || $date > date('Y-m-d')) $date = date('Y-m-d');
    $id = 'r' . bin2hex(random_bytes(8));
    $d = ['id' => $id, 'sid' => $sid, 'cls' => $cls, 'type' => $type, 'cat' => tch_txt($in['cat'] ?? '', 40), 'text' => $text, 'date' => $date,
          'tid' => $role === 'teacher' ? $tid : 'P', 'by' => $u['label'], 'at' => sch_now_ms()];
    sch_rec_put('tchRemark', $id, $d, $uid);
    sch_log($u, 'Student remark', ($type === 'good' ? 'Good: ' : 'Complaint: ') . ($s['name'] ?? $sid) . ' (Class ' . $cls . ') — ' . $text);
    sch_json(['ok' => true, 'remark' => $d]);
}

case 'delremark': {
    if ($method !== 'POST') sch_fail('POST only', 405);
    $id = (string)($in['id'] ?? ''); $old = preg_match('/^r[a-f0-9]{16}$/', $id) ? sch_rec_get('tchRemark', $id) : null;
    if (!$old) sch_fail('Not found.', 404);
    if ($role !== 'principal' && ($old['tid'] ?? '') !== $tid) sch_fail('Only the teacher who wrote it can delete it.', 403);
    sch_rec_del('tchRemark', $id, $uid);
    sch_log($u, 'Student remark', 'Deleted a remark about ' . ((sch_rec_get('student', (string)$old['sid']) ?? [])['name'] ?? $old['sid']) . ': ' . $old['text']);
    sch_json(['ok' => true]);
}

// ---- Principal cross-check (owner, 5 Nov 2026: "Principal Sir login karen to upar Teacher / Principal switch — jo nahi
// kiye hain unka cross-check aur message bhejne ka option"). Attendance and diary are already in the app's data;
// this adds the daily check (tchKit) and homework checks (tchChk) of one day.
case 'crosscheck': {
    if ($method !== 'GET') sch_fail('GET only', 405);
    // (owner, 5 Nov 2026: "jaise attendance cross-check banaya, vaise teacher ke baaki kaam ka bhi") — a teacher may ask
    // for his OWN cross-check: only the daily check of classes where he is the class teacher and his own homework checks.
    $date = (string)($_GET['date'] ?? ''); if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) sch_fail('Choose the date.');
    $own = $role === 'teacher';
    if (!$own && !in_array($role, ['principal', 'chairman'], true)) sch_fail('Only the Principal can see the cross-check.', 403);
    $days = max(1, min(7, (int)($_GET['days'] ?? 1)));                       // a teacher's "My work" looks back up to 7 days
    $from = $days > 1 ? date('Y-m-d', strtotime($date . ' -' . ($days - 1) . ' day')) : $date;
    $kit = []; $chk = [];
    $q = $db->prepare("SELECT k, r, d FROM sch_records WHERE k IN ('tchKit','tchChk') AND deleted=0 AND r >= ? AND r < ?"); $q->execute([$from, date('Y-m-d', strtotime($date . ' +1 day'))]);
    foreach ($q as $row) { $pp = explode('|', (string)$row['r']); $dd = $pp[0] ?? ''; if ($dd < $from || $dd > $date) continue; $d = json_decode((string)$row['d'], true) ?: [];
        if ($row['k'] === 'tchKit') { if ($own && !tch_is_ct($tid, (string)($pp[1] ?? ''))) continue;
            if ($days > 1) $kit[$dd . '|' . $pp[1]] = ['by' => (string)($d['by'] ?? ''), 'at' => (int)($d['at'] ?? 0)]; else $kit[$pp[1]] = ['by' => (string)($d['by'] ?? ''), 'at' => (int)($d['at'] ?? 0)]; }
        else { if ($own && ($d['tid'] ?? '') !== $tid) continue; $chk[] = ['date' => $dd, 'cls' => $pp[1] ?? '', 'subj' => $pp[2] ?? '', 'tid' => (string)($d['tid'] ?? ''), 'by' => (string)($d['by'] ?? '')]; } }
    sch_json(['ok' => true, 'date' => $date, 'from' => $from, 'kit' => (object)$kit, 'chk' => $chk]);
}

// ---- Chapter text (owner, 5 Nov 2026: "chapter name aur page add kare to hint do — pure chapter ko text me convert karke
// paste kar do, usse homework, lesson plan aur paper banane me help hogi"). The teacher pastes the whole chapter as text.
// Kind tchChap 'cls|subj|<16 hex of the chapter name>' = {cls, subj, chapter, book, text, words, by, tid, at}. Teachers of the
// class can read it; only the subject's teacher / Principal can write. Never in legacy load (prefix tch), never to parents.
case 'chaptext': {
    if ($method === 'GET') {
        $cls = (string)($_GET['cls'] ?? ''); $subj = (string)($_GET['subj'] ?? '');
        if ($cls === '' || $subj === '') sch_fail('Choose the class and subject.');
        if (!$canSee($cls)) sch_fail('You can see only your own classes.', 403);
        $full = ($_GET['full'] ?? '') === '1'; $out = [];
        $st = $db->prepare("SELECT d FROM sch_records WHERE k='tchChap' AND deleted=0 AND r LIKE ?"); $st->execute([str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], "$cls|$subj|") . '%']);
        foreach ($st as $row) { $d = json_decode((string)$row['d'], true); if (!is_array($d) || ($d['cls'] ?? '') !== $cls || ($d['subj'] ?? '') !== $subj) continue;
            if (!$full) unset($d['text']); $out[] = $d; }
        sch_json(['ok' => true, 'chapters' => $out]);
    }
    $cls = tch_txt($in['cls'] ?? '', 20); $subj = tch_txt($in['subj'] ?? '', 60); $chapter = tch_txt($in['chapter'] ?? '', 120);
    if (!learn_allowed($u, $cls, $subj)) sch_fail('You can add chapter text only for your own class and subject.', 403);
    if (mb_strlen($chapter) < 2) sch_fail('Write the chapter name.');
    $r = "$cls|$subj|" . substr(md5(mb_strtolower($chapter)), 0, 16);
    $text = mb_substr(trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', str_replace("\r", '', (string)($in['text'] ?? '')))), 0, 60000);
    if ($text === '') { if (sch_rec_get('tchChap', $r)) { sch_rec_del('tchChap', $r, $uid); sch_log($u, 'Syllabus', "Chapter text removed: Class $cls $subj — $chapter"); } sch_json(['ok' => true, 'removed' => true]); }
    $words = count(preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY));
    $d = ['cls' => $cls, 'subj' => $subj, 'chapter' => $chapter, 'book' => tch_txt($in['book'] ?? '', 80), 'text' => $text, 'words' => $words,
          'tid' => $role === 'teacher' ? $tid : 'P', 'by' => $u['label'], 'at' => sch_now_ms()];
    sch_rec_put('tchChap', $r, $d, $uid);
    sch_log($u, 'Syllabus', "Chapter text added: Class $cls $subj — $chapter ($words words)");
    unset($d['text']);
    sch_json(['ok' => true, 'chapter' => $d]);
}

case 'notes': {
    $cls = (string)($_GET['cls'] ?? ''); $subj = (string)($_GET['subj'] ?? '');
    if ($cls === '') sch_fail('Choose the class.');
    if (!$canSee($cls)) sch_fail('You can see only your own classes.', 403);
    $out = [];
    foreach ($db->query("SELECT r, d FROM sch_records WHERE k='tchNote' AND deleted=0") as $row) {
        $d = json_decode((string)$row['d'], true);
        if (!is_array($d) || ($d['cls'] ?? '') !== $cls || ($subj !== '' && ($d['subj'] ?? '') !== $subj)) continue;
        $out[] = $d;
    }
    usort($out, fn($x, $y) => [(string)$x['subj'], (int)($x['order'] ?? 0), (int)$x['at']] <=> [(string)$y['subj'], (int)($y['order'] ?? 0), (int)$y['at']]);
    sch_json(['ok' => true, 'notes' => $out]);
}

case 'note': {
    if ($method !== 'POST') sch_fail('POST only', 405);
    $cls = tch_txt($in['cls'] ?? '', 20); $subj = tch_txt($in['subj'] ?? '', 60);
    if (!learn_allowed($u, $cls, $subj)) sch_fail('You can write notes only for your own class and subject.', 403);
    $id = (string)($in['id'] ?? '');
    if ($id !== '' && !preg_match('/^n[a-z0-9]{6,30}$/', $id)) sch_fail('Bad note.');
    $old = $id !== '' ? sch_rec_get('tchNote', $id) : null;
    if ($id !== '' && $old === null) { $q = $db->prepare("SELECT 1 FROM sch_records WHERE k='tchNote' AND r=?"); $q->execute([$id]); if ($q->fetchColumn()) sch_fail('This note was deleted.', 409); }
    if ($old && $role !== 'principal' && ($old['tid'] ?? '') !== $tid) sch_fail('Only the teacher who wrote this note can change it.', 403);
    if ($old && (($old['cls'] ?? '') !== $cls || ($old['subj'] ?? '') !== $subj)) sch_fail('A note cannot move to another class or subject.');
    $title = tch_txt($in['title'] ?? '', 160); $chapter = tch_txt($in['chapter'] ?? '', 160);
    if ($title === '' && $chapter === '') sch_fail('Write the chapter name.');
    $body = tch_txt($in['body'] ?? '', 20000);
    $points = tch_list($in['points'] ?? [], 40, fn($x) => ($t = tch_txt($x, 400)) === '' ? null : $t);
    $words = tch_list($in['words'] ?? [], 40, fn($x) => is_array($x) && ($w = tch_txt($x['w'] ?? '', 80)) !== '' ? ['w' => $w, 'm' => tch_txt($x['m'] ?? '', 240)] : null);
    $qa = tch_list($in['qa'] ?? [], 40, fn($x) => is_array($x) && ($q = tch_txt($x['q'] ?? '', 400)) !== '' ? ['q' => $q, 'a' => tch_txt($x['a'] ?? '', 1500)] : null);
    if ($body === '' && !$points && !$qa) sch_fail('The note is empty. Write the notes or take a photo of the chapter.');
    $id = $id ?: 'n' . bin2hex(random_bytes(6));
    $d = ['id' => $id, 'cls' => $cls, 'subj' => $subj, 'chapter' => $chapter ?: $title, 'title' => $title ?: $chapter, 'order' => max(0, min(99, (int)($in['order'] ?? 0))),
          'body' => $body, 'points' => $points, 'words' => $words, 'qa' => $qa, 'via' => in_array($in['via'] ?? '', ['ai', 'phone', 'typed'], true) ? $in['via'] : 'typed',
          'share' => ($in['share'] ?? true) !== false, 'tid' => $old['tid'] ?? ($role === 'teacher' ? $tid : 'P'), 'by' => $u['label'], 'at' => sch_now_ms(), 'created' => $old['created'] ?? sch_now_ms()];
    sch_rec_put('tchNote', $id, $d, $uid);
    sch_log($u, 'Notes', ($old ? 'Updated' : 'Added') . " notes: Class $cls $subj — " . $d['chapter'] . ($d['share'] ? ' (shared with students)' : ' (not shared yet)'));
    sch_json(['ok' => true, 'note' => $d]);
}

case 'delnote': {
    if ($method !== 'POST') sch_fail('POST only', 405);
    $id = (string)($in['id'] ?? ''); $old = preg_match('/^n[a-z0-9]{6,30}$/', $id) ? sch_rec_get('tchNote', $id) : null;
    if (!$old) sch_fail('Note not found.', 404);
    if ($role !== 'principal' && ($old['tid'] ?? '') !== $tid) sch_fail('Only the teacher who wrote this note can delete it.', 403);
    sch_rec_del('tchNote', $id, $uid);
    sch_log($u, 'Notes', 'Deleted notes: Class ' . $old['cls'] . ' ' . $old['subj'] . ' — ' . $old['chapter']);
    sch_json(['ok' => true]);
}

default: sch_fail('Unknown action.', 404);
}
} catch (Throwable $e) {
    if (isset($db) && $db->inTransaction()) $db->rollBack();
    error_log('teacher_api: ' . $e->getMessage());
    sch_fail('Something went wrong. Nothing was saved. Please try again.', 500);
}
