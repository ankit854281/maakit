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
//   POST ?a=note {id?,cls,subj,chapter,title,body,points[],words[],qa[]}   subject teacher / Principal
//   POST ?a=delnote {id}                   the teacher who wrote it / Principal
//
//  Kinds: clsHol (date-keyed, in legacy load so the web ERP shows "Holiday"),
//         tchNote (kept OUT of legacy load/sync — prefix "tch" in inc.php; parents get
//         only their own classes' notes through api.php?a=pdata).
// ============================================================================
declare(strict_types=1);
require __DIR__ . '/inc.php';
require_once __DIR__ . '/learning_lib.php';
require_once __DIR__ . '/scan_lib.php';

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
