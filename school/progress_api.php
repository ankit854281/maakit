<?php
// ============================================================================
//  Progress Report + student photos — owner decision, 24 Oct 2026
//  Ankit: progress reports must look and add up exactly like the old ERP's
//  (Examination-I: Pre. Test 10 · Notebook 5 · SEA 5 · Term 80, 9-point grades,
//  co-scholastic, remark, attendance), and every student record and photo must
//  come across from the old ERP.
//
//  Old ERP cards are kept EXACTLY as the old ERP printed them (its own HTML,
//  gzip+base64, one row per card). progress.js shows them with the old
//  header, signature and photos, after removing anything that is not plain
//  layout (no scripts, no links, no outside images).
//  New marks (this ERP) use the same components and print the same card.
//
//   GET  ?a=index&ses=&exam=&cls=          cards of a class (no HTML)
//   GET  ?a=cards&keys=k1,k2…              the HTML of up to 60 cards + the old card style
//   GET  ?a=photos&ids=…  | &sid=          photos as data URLs (staff only)
//   GET  ?a=marks&ses=&exam=&cls=          new marks (Exam-I) of a class
//   POST ?a=marks {ses,exam,cls,subject,rows} save one subject's marks (teacher of that subject / Principal)
//   POST ?a=extra {ses,exam,cls,rows}      co-scholastic, remark, attendance (class teacher / Principal)
//   POST ?a=import {kind,…,apply}          Principal: old ERP cards or photos; never overwrites
//
//  Kinds: prcOld 'ses|exam|cls|n', prcStyle 'ses|exam', prcMarks 'ses|exam|cls|subject',
//         prcExtra 'ses|exam|cls', phoImg <sha1>, phoStu <studentId>, phoOld <old path>
//  (prefixes prc / pho are kept out of legacy sync in inc.php)
// ============================================================================
declare(strict_types=1);
require __DIR__ . '/inc.php';
require __DIR__ . '/registers_lib.php';

$a = (string)($_GET['a'] ?? 'index');
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
try { $db = sch_db(); } catch (Throwable $e) { sch_fail('Server is not reachable right now. Please try again in a few minutes.', 503); }
$u = sch_auth();
$role = (string)$u['role']; $uid = sch_uid_of($u); $tid = (string)($u['tid'] ?? '');
$staff = in_array($role, ['principal', 'chairman', 'accountant', 'reception', 'teacher'], true);
$readCards = in_array($role, ['principal', 'chairman', 'teacher'], true);
$isP = $role === 'principal';
if (!$staff) sch_fail('Progress reports are not open for this login.', 403);
$in = [];
if ($method === 'POST') {
    $raw = file_get_contents('php://input', false, null, 0, 9000001) ?: '';
    if (strlen($raw) > 9000000) sch_fail('This file is too large. Import one session at a time.');
    $in = json_decode($raw, true); if (!is_array($in)) sch_fail('Bad request.');
}
const PRC_EXAMS = ['Term-1', 'Term-2', 'Final'];
const PRC_CLASSES = ['PG', 'LKG', 'UKG', '1st', '2nd', '3rd', '4th', '5th', '6th', '7th', '8th', '9th', '10th', '11th', '12th'];
// Examination-I components exactly as the old ERP (Exam Setting: PAT grid 3, Term grid 3)
const PRC_PARTS = [['pt', 'Pre. Test', 10], ['nb', 'Notebook', 5], ['sea', 'SEA', 5], ['te', 'Term Exam', 80]];
function prc_ses(string $s): string { $s = str_replace(['–', '—'], '-', trim($s)); return preg_match('/^(20\d\d)-(?:20)?(\d\d)$/', $s, $m) ? $m[1] . '-' . $m[2] : ''; }
function prc_txt($v, int $n = 200): string { return mb_substr(trim(preg_replace('/[\x00-\x1f\x7f]/u', ' ', (string)$v)), 0, $n); }
function prc_like(string $p): string { return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $p) . '%'; }
$rows = function (string $k, string $prefix) use ($db): array {
    $st = $db->prepare('SELECT r, d FROM sch_records WHERE k=? AND r LIKE ? AND deleted=0'); $st->execute([$k, prc_like($prefix)]); $o = [];
    foreach ($st as $row) { $d = json_decode((string)$row['d'], true); if (is_array($d)) $o[(string)$row['r']] = $d; } return $o;
};
$teachesSubject = function (string $cls, string $subj) use ($role, $tid): bool { return $role === 'principal' || ($role === 'teacher' && sch_teaches($tid, $cls, $subj)); };
$classTeacher = function (string $cls) use ($role, $tid): bool { return $role === 'principal' || ($role === 'teacher' && sch_in_class($tid, $cls)); };

try {
switch ($a) {
case 'index': {
    if (!$readCards) sch_fail('Progress reports are not open for this login.', 403);
    $ses = prc_ses((string)($_GET['ses'] ?? '')); $exam = (string)($_GET['exam'] ?? ''); $cls = (string)($_GET['cls'] ?? '');
    $sum = [];
    foreach ($db->query("SELECT r FROM sch_records WHERE k='prcOld' AND deleted=0") as $row) { [$s, $e, $c] = explode('|', (string)$row['r']); $sum[$s][$e][$c] = ($sum[$s][$e][$c] ?? 0) + 1; }
    $list = [];
    if ($ses !== '' && in_array($exam, PRC_EXAMS, true) && in_array($cls, PRC_CLASSES, true))
        foreach ($rows('prcOld', "$ses|$exam|$cls|") as $k => $d) { unset($d['h']); $d['key'] = $k; $list[] = $d; }
    usort($list, fn($x, $y) => (int)$x['n'] <=> (int)$y['n']);
    sch_json(['ok' => true, 'summary' => $sum, 'cards' => $list, 'parts' => PRC_PARTS, 'classes' => PRC_CLASSES, 'exams' => PRC_EXAMS, 'session' => reg_session(), 'role' => $role, 'isPrincipal' => $isP,
        'school' => (string)((sch_rec_get('settings', 'main') ?? [])['school'] ?? 'SN Mishra Singapore International School')]);
}
case 'cards': {
    if (!$readCards) sch_fail('Progress reports are not open for this login.', 403);
    $keys = array_slice(array_filter(explode(',', (string)($_GET['keys'] ?? ''))), 0, 60); $out = []; $styles = [];
    foreach ($keys as $k) {
        if (!preg_match('/^20\d\d-\d\d\|(Term-1|Term-2|Final)\|[0-9A-Za-z]+\|\d{1,4}$/', $k)) continue;
        $d = sch_rec_get('prcOld', $k); if (!$d) continue;
        $out[$k] = $d['h']; [$s, $e] = explode('|', $k);
        if (!isset($styles["$s|$e"])) $styles["$s|$e"] = (string)((sch_rec_get('prcStyle', "$s|$e") ?? [])['css'] ?? '');
    }
    sch_json(['ok' => true, 'cards' => $out, 'styles' => $styles]);
}
case 'photos': {
    $out = [];
    if (isset($_GET['sid'])) { $sid = (string)$_GET['sid']; $p = sch_rec_get('phoStu', $sid); if ($p && ($img = sch_rec_get('phoImg', (string)$p['img']))) $out[$sid] = $img['data']; }
    foreach (array_slice(array_filter(explode(',', (string)($_GET['ids'] ?? ''))), 0, 80) as $path) {
        $o = sch_rec_get('phoOld', $path); if ($o && ($img = sch_rec_get('phoImg', (string)$o['img']))) $out[$path] = $img['data'];
    }
    sch_json(['ok' => true, 'photos' => $out]);
}
case 'marks': {
    $ses = prc_ses((string)($in['ses'] ?? $_GET['ses'] ?? '')); $exam = (string)($in['exam'] ?? $_GET['exam'] ?? ''); $cls = (string)($in['cls'] ?? $_GET['cls'] ?? '');
    if ($ses === '' || !in_array($exam, ['Term-1', 'Term-2'], true) || !in_array($cls, PRC_CLASSES, true)) throw new InvalidArgumentException('Choose the session, Term-1 or Term-2, and the class.');
    if ($method === 'GET') {
        if (!$readCards) sch_fail('Not open for this login.', 403);
        $subs = []; foreach ((array)sch_rec_get('alloc', 'main') as $al) if (($al['c'] ?? '') === $cls && ($al['s'] ?? '') !== '' && !in_array($al['s'], $subs, true)) $subs[] = (string)$al['s'];
        $mine = array_values(array_filter($subs, fn($x) => $teachesSubject($cls, $x)));
        $kids = array_values(array_filter(reg_students(), fn($x) => $x['cls'] === $cls)); usort($kids, fn($x, $y) => strcmp($x['name'], $y['name']));
        $photoIds = []; foreach ($kids as $k) if (sch_rec_get('phoStu', $k['id'])) $photoIds[] = $k['id'];
        sch_json(['ok' => true, 'subjects' => $subs, 'mySubjects' => $mine, 'classTeacher' => $classTeacher($cls), 'students' => $kids, 'withPhoto' => $photoIds, 'marks' => $rows('prcMarks', "$ses|$exam|$cls|"), 'extra' => sch_rec_get('prcExtra', "$ses|$exam|$cls") ?? ['rows' => []],
            'other' => $exam === 'Term-2' ? ['marks' => $rows('prcMarks', "$ses|Term-1|$cls|"), 'extra' => sch_rec_get('prcExtra', "$ses|Term-1|$cls") ?? ['rows' => []]] : null]);
    }
    $subj = prc_txt($in['subject'] ?? '', 40);
    if ($subj === '') throw new InvalidArgumentException('Choose the subject.');
    if (!$teachesSubject($cls, $subj)) sch_fail('Only the teacher of this subject or the Principal can enter these marks.', 403);
    $students = reg_students(); $clean = [];
    foreach ((array)($in['rows'] ?? []) as $sid => $m) {
        if (!isset($students[$sid]) || $students[$sid]['cls'] !== $cls) throw new InvalidArgumentException('A student in the list is not in class ' . $cls . '.');
        $r = [];
        foreach (PRC_PARTS as [$k, $label, $max]) {
            $v = strtoupper(trim((string)(((array)$m)[$k] ?? '')));
            if ($v === '' || $v === 'AB') { $r[$k] = $v; continue; }
            if (!preg_match('/^\d{1,3}(\.\d{1,2})?$/', $v) || (float)$v > $max) throw new InvalidArgumentException($students[$sid]['name'] . ': ' . $label . ' must be 0 to ' . $max . ' (or AB).');
            $r[$k] = $v + 0;
        }
        $clean[$sid] = $r;
    }
    sch_rec_put('prcMarks', "$ses|$exam|$cls|$subj", ['subject' => $subj, 'rows' => $clean, 'by' => $u['label'] ?? $uid, 'at' => sch_now_ms()], $uid);
    sch_log($u, 'Progress Report', "$exam marks saved: Class $cls $subj (" . count($clean) . ' students)');
    sch_json(['ok' => true]);
}
case 'extra': {
    if ($method !== 'POST') sch_fail('POST only', 405);
    $ses = prc_ses((string)($in['ses'] ?? '')); $exam = (string)($in['exam'] ?? ''); $cls = (string)($in['cls'] ?? '');
    if ($ses === '' || !in_array($exam, ['Term-1', 'Term-2'], true) || !in_array($cls, PRC_CLASSES, true)) throw new InvalidArgumentException('Choose the session, Term-1 or Term-2, and the class.');
    if (!$classTeacher($cls)) sch_fail('Only the class teacher or the Principal can enter this.', 403);
    $students = reg_students(); $clean = []; $grades = ['', 'A', 'B', 'C'];
    foreach ((array)($in['rows'] ?? []) as $sid => $x) {
        if (!isset($students[$sid]) || $students[$sid]['cls'] !== $cls) throw new InvalidArgumentException('A student in the list is not in class ' . $cls . '.');
        $x = (array)$x; $r = [];
        foreach (['art', 'work', 'health', 'disc'] as $k) { $g = strtoupper(trim((string)($x[$k] ?? ''))); if (!in_array($g, $grades, true)) throw new InvalidArgumentException('Co-scholastic grades are A, B or C.'); $r[$k] = $g; }
        foreach (['days', 'present'] as $k) { $v = trim((string)($x[$k] ?? '')); if ($v !== '' && !preg_match('/^\d{1,3}$/', $v)) throw new InvalidArgumentException('Working days and presents are whole numbers.'); $r[$k] = $v; }
        if ($r['days'] !== '' && $r['present'] !== '' && (int)$r['present'] > (int)$r['days']) throw new InvalidArgumentException($students[$sid]['name'] . ': presents are more than working days.');
        $r['remark'] = prc_txt($x['remark'] ?? '', 300); $r['result'] = prc_txt($x['result'] ?? '', 20); $r['promoted'] = prc_txt($x['promoted'] ?? '', 20);
        $clean[$sid] = $r;
    }
    sch_rec_put('prcExtra', "$ses|$exam|$cls", ['rows' => $clean, 'by' => $u['label'] ?? $uid, 'at' => sch_now_ms()], $uid);
    sch_log($u, 'Progress Report', "$exam co-scholastic / remark / attendance saved: Class $cls");
    sch_json(['ok' => true]);
}
case 'import': {
    if ($method !== 'POST') sch_fail('POST only', 405);
    if (!$isP) sch_fail('Only the Principal imports from the old ERP.', 403);
    $apply = ($in['apply'] ?? false) === true; $kind = (string)($in['kind'] ?? '');
    $plan = []; $same = 0; $conflict = [];
    if ($kind === 'oldProgress') {
        $ses = prc_ses((string)($in['session'] ?? '')); if ($ses === '') throw new InvalidArgumentException('The file has no session.');
        $cards = (array)($in['cards'] ?? []); if (!$cards || count($cards) > 3000) throw new InvalidArgumentException('The file must have 1 to 3000 cards.');
        foreach ($cards as $c) {
            $c = (array)$c; $exam = (string)($c['exam'] ?? ''); $cls = (string)($c['cls'] ?? ''); $n = (int)($c['n'] ?? 0);
            if (!in_array($exam, PRC_EXAMS, true) || !in_array($cls, PRC_CLASSES, true) || $n < 1 || $n > 9999) throw new InvalidArgumentException('A card has a wrong exam, class or number.');
            $h = (string)($c['h'] ?? ''); $raw = base64_decode($h, true); $html = $raw === false ? false : @gzdecode($raw);
            if ($html === false || strlen($html) > 200000 || !str_contains($html, 'Progress Report')) throw new InvalidArgumentException('A card in the file is damaged (' . $cls . ' ' . $exam . ' #' . $n . ').');
            $d = ['session' => $ses, 'exam' => $exam, 'cls' => $cls, 'section' => prc_txt($c['section'] ?? '', 10), 'n' => $n, 'name' => prc_txt($c['name'] ?? '', 120), 'roll' => prc_txt($c['roll'] ?? '', 20),
                'oldId' => prc_txt($c['oldId'] ?? '', 20), 'liveId' => prc_txt($c['liveId'] ?? '', 40), 'photo' => preg_match('#^uploads(/1/student)?/[\w.-]{1,80}$#', (string)($c['photo'] ?? '')) ? (string)$c['photo'] : '', 'h' => $h];
            $key = "$ses|$exam|$cls|$n"; $old = sch_rec_get('prcOld', $key);
            if ($old) { if ($old['h'] === $h) $same++; else $conflict[] = "$cls $exam #$n"; continue; }
            $plan[$key] = $d;
        }
        if ($conflict) sch_json(['ok' => false, 'error' => count($conflict) . ' cards already here are different (' . implode(', ', array_slice($conflict, 0, 5)) . '). Nothing was imported.'], 409);
        if ($apply) {
            $db->beginTransaction();
            foreach ($plan as $k => $d) sch_rec_put('prcOld', $k, $d, $uid);
            foreach ((array)($in['styles'] ?? []) as $exam => $css) if (in_array($exam, PRC_EXAMS, true) && !sch_rec_get('prcStyle', "$ses|$exam"))
                sch_rec_put('prcStyle', "$ses|$exam", ['css' => preg_replace('/@import[^;]*;|url\s*\([^)]*\)|expression\s*\(|<[^>]*>/i', '', mb_substr((string)$css, 0, 20000))], $uid);
            sch_log($u, 'Old ERP Import', count($plan) . " progress report cards of $ses imported; $same already present");
            $db->commit();
        }
        sch_json(['ok' => true, 'applied' => $apply, 'toAdd' => count($plan), 'unchanged' => $same, 'total' => count($cards)]);
    }
    if ($kind === 'photos') {
        $photos = (array)($in['photos'] ?? []); $links = (array)($in['links'] ?? []);
        if (!$photos || count($photos) > 2000) throw new InvalidArgumentException('The file must have 1 to 2000 photos.');
        $students = reg_students(); $imgs = []; $add = 0; $linked = 0; $skipped = [];
        foreach ($photos as $path => $data) {
            if (!preg_match('#^(uploads(/1/student)?|website_upload/\d{4}/\d\d)/[\w.-]{1,80}$|^princ_sign1\.png$#', (string)$path)) throw new InvalidArgumentException('A photo has a wrong name.');
            if (!preg_match('#^data:image/(jpeg|png|webp);base64,[A-Za-z0-9+/=]+$#', (string)$data) || strlen((string)$data) > 400000) throw new InvalidArgumentException('Photo ' . $path . ' is damaged or too large.');
            $bin = base64_decode(substr((string)$data, strpos((string)$data, ',') + 1), true);
            if ($bin === false || !@getimagesizefromstring($bin)) throw new InvalidArgumentException('Photo ' . $path . ' is not a picture.');
            $imgs[(string)$path] = sha1($bin);
            if (!sch_rec_get('phoOld', (string)$path)) $add++; else $same++;
        }
        $plan = [];
        foreach ($links as $l) {
            $l = (array)$l; $sid = (string)($l['liveId'] ?? ''); $path = (string)($l['path'] ?? '');
            if (!isset($imgs[$path])) continue;
            if ($sid === '' || !isset($students[$sid])) { $skipped[] = (string)($l['oldId'] ?? ''); continue; }
            $have = sch_rec_get('phoStu', $sid);
            if ($have) { if (($have['from'] ?? '') !== $path) $conflict[] = $students[$sid]['name']; continue; }
            $plan[$sid] = $path; $linked++;
        }
        if ($conflict) sch_json(['ok' => false, 'error' => count($conflict) . ' students already have a different photo (' . implode(', ', array_slice($conflict, 0, 5)) . '). Nothing was imported.'], 409);
        if ($apply) {
            $db->beginTransaction();
            foreach ($imgs as $path => $h) {
                if (!sch_rec_get('phoImg', $h)) sch_rec_put('phoImg', $h, ['data' => (string)$photos[$path]], $uid);
                if (!sch_rec_get('phoOld', $path)) sch_rec_put('phoOld', $path, ['img' => $h], $uid);
            }
            foreach ($plan as $sid => $path) sch_rec_put('phoStu', $sid, ['img' => $imgs[$path], 'from' => $path, 'by' => $u['label'] ?? $uid], $uid);
            sch_log($u, 'Old ERP Import', "$add old ERP photos imported, $linked linked to students");
            $db->commit();
        }
        sch_json(['ok' => true, 'applied' => $apply, 'toAdd' => $add, 'unchanged' => $same, 'linked' => $linked, 'notInErp' => count(array_filter($skipped)), 'total' => count($photos)]);
    }
    throw new InvalidArgumentException('This file is not a progress report or photo file from the old ERP.');
}
default: sch_fail('Unknown action.', 404);
}
} catch (InvalidArgumentException $e) {
    if ($db->inTransaction()) $db->rollBack();
    sch_fail($e->getMessage());
} catch (Throwable $e) {
    if ($db->inTransaction()) $db->rollBack();
    error_log('progress_api: ' . $e->getMessage());
    sch_fail('Could not complete this. Nothing was saved.', 500);
}
