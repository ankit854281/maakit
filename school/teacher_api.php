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
$canSee = fn(string $cls): bool => $role !== 'teacher' || sch_in_class($tid, $cls);

switch ($a) {


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
        $th = sch_rec_get('phoThumb', $h);
        if (!$th) {
            $img = sch_rec_get('phoImg', $h); $data = (string)($img['data'] ?? ''); if ($data === '') continue;
            $small = $data;
            if (function_exists('imagecreatefromstring')) {
                $bin = base64_decode(substr($data, strpos($data, ',') + 1), true);
                $src = $bin ? @imagecreatefromstring($bin) : false;
                if ($src) {
                    $w = imagesx($src); $hh = imagesy($src); $e = min($w, $hh); $dst = imagecreatetruecolor(112, 112);
                    imagecopyresampled($dst, $src, (int)(($w - $e) / 2), (int)(($hh - $e) / 3), 0, 0, 112, 112, $e, $e);
                    ob_start(); imagejpeg($dst, null, 78); $small = 'data:image/jpeg;base64,' . base64_encode((string)ob_get_clean());
                }
            }
            $th = ['data' => $small];
            if (strlen($small) < 60000) sch_rec_put('phoThumb', $h, $th, 'system');
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
    if ($cls === '' || !$canClass($cls)) sch_fail('Only the class teacher sees the fees of this class.', 403);
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

case 'feecall': {
    if ($method !== 'POST') sch_fail('POST only', 405);
    $sid = (string)($in['sid'] ?? ''); $s = $sid !== '' ? sch_rec_get('student', $sid) : null;
    if (!$s || !$canClass((string)($s['cls'] ?? ''))) sch_fail('Only the class teacher can call about this child\'s fee.', 403);
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
