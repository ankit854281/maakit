<?php
// Progress Report (school/progress_api.php) — CI database only, synthetic rows only.
require __DIR__ . '/../../config.php';
if (DB_NAME !== 'maakit_jaanch') throw new RuntimeException('CI database only');
require __DIR__ . '/../../school/inc.php';
function pc($v, $m) { if (!$v) throw new RuntimeException('Progress flow: ' . $m); }
function prtok($uid, $role, $tid = null) {
    $db = sch_db();
    $db->prepare('INSERT INTO sch_users(uid,role,tid,must_change) VALUES(?,?,?,0) ON DUPLICATE KEY UPDATE role=VALUES(role),tid=VALUES(tid),must_change=0')->execute([$uid, $role, $tid]);
    $t = bin2hex(random_bytes(32));
    $db->prepare('INSERT INTO sch_sessions(token_hash,uid,expires_at) VALUES(?,?,DATE_ADD(NOW(),INTERVAL 1 DAY))')->execute([hash('sha256', $t), $uid]);
    return $t;
}
function prq($a, $token, $body = null, $q = '', $api = 'progress_api.php') {
    $h = ['X-School-Token: ' . $token]; $o = [CURLOPT_RETURNTRANSFER => true, CURLOPT_PROXY => '', CURLOPT_TIMEOUT => 20];
    if ($body !== null) { $o[CURLOPT_POST] = true; $h[] = 'Content-Type: application/json'; $o[CURLOPT_POSTFIELDS] = json_encode($body); }
    $o[CURLOPT_HTTPHEADER] = $h;
    $c = curl_init('http://127.0.0.1:8099/school/' . $api . '?a=' . $a . $q); curl_setopt_array($c, $o);
    $raw = curl_exec($c); $code = curl_getinfo($c, CURLINFO_HTTP_CODE); curl_close($c);
    pc(is_string($raw) && !preg_match('/Fatal error|Warning:|Uncaught|Deprecated:|Notice:/', $raw), 'PHP response ' . $a . ': ' . substr((string)$raw, 0, 300));
    return [$code, json_decode((string)$raw, true), (string)$raw];
}
$db = sch_db();
$oldAlloc = sch_rec_get('alloc', 'main'); $oldClasses = sch_rec_get('classes', 'main');
$P = prtok('principal', 'principal', 'P'); $T = prtok('t:PRT', 'teacher', 'PRT'); $T2 = prtok('t:PRX', 'teacher', 'PRX'); $F = prtok('p:9811177777', 'parent', '9811177777');
$clean = fn() => $db->exec("DELETE FROM sch_records WHERE k LIKE 'prc%' OR k LIKE 'pho%' OR (k='student' AND r IN ('pr-s1','pr-s2'))");
try {
    $clean();
    foreach ([['pr-s1', 'Synthetic Progress One'], ['pr-s2', 'Synthetic Progress Two']] as [$id, $n]) sch_rec_put('student', $id, ['id' => $id, 'adm' => strtoupper($id), 'name' => $n, 'cls' => '1st', 'section' => 'A', 'phone' => '9811177777'], 'principal');
    sch_rec_put('alloc', 'main', array_merge((array)$oldAlloc, [['t' => 'PRT', 'c' => '1st', 's' => 'Synthetic English']]), 'principal');
    // PRX teaches 1st too but is NOT its class teacher; PRT is (Classes › ct)
    sch_rec_put('alloc', 'main', array_merge((array)$oldAlloc, [['t' => 'PRT', 'c' => '1st', 's' => 'Synthetic English'], ['t' => 'PRX', 'c' => '1st', 's' => 'Synthetic Maths']]), 'principal');
    sch_rec_put('classes', 'main', array_merge(array_values(array_filter((array)$oldClasses, fn($x) => ($x['c'] ?? '') !== '1st')), [['c' => '1st', 'ct' => 'PRT']]), 'principal');

    [$c] = prq('index', $F); pc($c === 403, 'parents cannot open progress reports');
    // Old ERP card kept exactly, never overwritten
    $html = '<table class="k"><tr><td>Progress Report Session : 2025-2026 Synthetic</td></tr><tr><td><img src="uploads/fsynthetic.jpg"></td></tr></table>';
    $file = ['kind' => 'oldProgress', 'session' => '2025-26', 'styles' => ['Term-2' => '.k td{border:1px solid #000} @import url(x.css);'],
        'cards' => [['exam' => 'Term-2', 'cls' => '1st', 'section' => 'A', 'n' => 1, 'name' => 'Synthetic Progress One', 'roll' => '1', 'liveId' => 'pr-s1', 'photo' => 'uploads/fsynthetic.jpg', 'h' => base64_encode(gzencode($html))]]];
    [$c] = prq('import', $T, $file); pc($c === 403, 'only Principal imports');
    [$c, $d] = prq('import', $P, $file); pc($c === 200 && $d['toAdd'] === 1 && !$d['applied'], 'import preview');
    [$c, $d] = prq('import', $P, $file + ['apply' => true]); pc($c === 200 && $d['applied'], 'import apply');
    [$c, $d] = prq('import', $P, $file + ['apply' => true]); pc($c === 200 && $d['unchanged'] === 1 && $d['toAdd'] === 0, 'second import adds nothing');
    $bad = $file; $bad['cards'][0]['h'] = base64_encode(gzencode(str_replace('Synthetic', 'Changed', $html)));
    [$c] = prq('import', $P, $bad + ['apply' => true]); pc($c === 409, 'a changed card is never overwritten');
    [$c, $d] = prq('index', $T, null, '&ses=2025-26&exam=Term-2&cls=1st'); pc($c === 200 && count($d['cards']) === 1 && !isset($d['cards'][0]['h']), 'index lists cards without HTML');
    [$c, $d] = prq('cards', $T, null, '&keys=' . urlencode($d['cards'][0]['key'])); pc($c === 200 && gzdecode(base64_decode(array_values($d['cards'])[0])) === $html && !str_contains($d['styles']['2025-26|Term-2'], '@import'), 'card HTML exactly as imported, style cleaned');
    // Photos: linked to the student, served only to staff
    $jpg = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';
    [$c, $d] = prq('import', $P, ['kind' => 'photos', 'photos' => ['uploads/fsynthetic.jpg' => $jpg], 'links' => [['oldId' => '1', 'liveId' => 'pr-s1', 'path' => 'uploads/fsynthetic.jpg']], 'apply' => true]);
    pc($c === 200 && $d['linked'] === 1, 'photo import ' . json_encode($d));
    [$c, $d] = prq('photos', $T, null, '&sid=pr-s1&ids=uploads/fsynthetic.jpg'); pc($c === 200 && $d['photos']['pr-s1'] === $jpg && $d['photos']['uploads/fsynthetic.jpg'] === $jpg, 'photos by student and by old path');
    [$c] = prq('photos', $F, null, '&sid=pr-s1'); pc($c === 403, 'parents cannot read photos here');
    [$c] = prq('import', $P, ['kind' => 'photos', 'photos' => ['../../config.php' => $jpg], 'links' => []]); pc($c === 400, 'photo names are checked');
    // Marks: old ERP components and limits, only the subject teacher or Principal
    $rows = ['pr-s1' => ['pt' => '7', 'nb' => '4', 'sea' => '4', 'te' => '33'], 'pr-s2' => ['pt' => 'AB', 'nb' => '5', 'sea' => '5', 'te' => '80']];
    [$c] = prq('marks', $T2, ['ses' => '2026-27', 'exam' => 'Term-1', 'cls' => '1st', 'subject' => 'Synthetic English', 'rows' => $rows]); pc($c === 403, 'another teacher cannot enter these marks');
    [$c, $d] = prq('marks', $T, ['ses' => '2026-27', 'exam' => 'Term-1', 'cls' => '1st', 'subject' => 'Synthetic English', 'rows' => ['pr-s1' => ['pt' => '11']]]); pc($c === 400 && str_contains($d['error'], '0 to 10'), 'Pre. Test is out of 10');
    [$c] = prq('marks', $T, ['ses' => '2026-27', 'exam' => 'Term-1', 'cls' => '1st', 'subject' => 'Synthetic English', 'rows' => $rows]); pc($c === 200, 'subject teacher saves marks');
    [$c, $d] = prq('marks', $P, null, '&ses=2026-27&exam=Term-1&cls=1st');
    $m = $d['marks']['2026-27|Term-1|1st|Synthetic English']['rows'] ?? [];
    pc(($m['pr-s1']['te'] ?? null) === 33 && ($m['pr-s2']['pt'] ?? null) === 'AB' && in_array('Synthetic English', $d['subjects'], true), 'marks kept as entered ' . json_encode($m));
    [$c, $d] = prq('extra', $T, ['ses' => '2026-27', 'exam' => 'Term-1', 'cls' => '1st', 'rows' => ['pr-s1' => ['art' => 'D']]]); pc($c === 400, 'co-scholastic grades are A to C');
    [$c, $d] = prq('extra', $T, ['ses' => '2026-27', 'exam' => 'Term-1', 'cls' => '1st', 'rows' => ['pr-s1' => ['days' => '90', 'present' => '95']]]); pc($c === 400, 'presents cannot pass working days');
    [$c] = prq('extra', $T2, ['ses' => '2026-27', 'exam' => 'Term-1', 'cls' => '1st', 'rows' => ['pr-s1' => ['art' => 'C']]]); pc($c === 403, 'a subject teacher who is not the class teacher cannot change co-scholastic');
    [$c] = prq('extra', $T, ['ses' => '2026-27', 'exam' => 'Term-1', 'cls' => '1st', 'rows' => ['pr-s1' => ['art' => 'A', 'work' => 'B', 'health' => 'A', 'disc' => 'A', 'days' => '90', 'present' => '54', 'remark' => 'Synthetic remark']]]); pc($c === 200, 'class teacher saves co-scholastic');
    // Marks Status: English filled for both synthetic children, Maths (PRX) not started, co-scholastic counted; parents refused
    [$c, $d] = prq('status', $P, null, '&ses=2026-27&exam=Term-1'); $one = array_values(array_filter((array)($d['classes'] ?? []), fn($x) => $x['cls'] === '1st'))[0] ?? [];
    $sj = array_column((array)($one['subjects'] ?? []), null, 's');
    pc($c === 200 && ($sj['Synthetic English']['full'] ?? 0) >= 2 && ($sj['Synthetic Maths']['full'] ?? -1) === 0 && in_array('PRX', $sj['Synthetic Maths']['teachers'] ?? [], true) && ($one['extra'] ?? 0) >= 1, 'marks status ' . substr(json_encode($one), 0, 300));
    [$c] = prq('status', $F, null, '&ses=2026-27&exam=Term-1'); pc($c === 403, 'parents cannot open marks status');
    // Class teacher corrects any subject of her own class (owner, 18 Nov 2026) — PRT is class teacher of 1st, Maths is PRX's
    [$c, $d] = prq('marks', $T, null, '&ses=2026-27&exam=Term-1&cls=1st'); pc($c === 200 && in_array('Synthetic Maths', $d['mySubjects'], true) && in_array('Synthetic English', $d['mySubjects'], true), 'class teacher sees every subject of her class');
    [$c, $d] = prq('marks', $T2, null, '&ses=2026-27&exam=Term-1&cls=1st'); pc($c === 200 && $d['mySubjects'] === ['Synthetic Maths'], 'subject teacher still sees only own subject');
    [$c] = prq('marks', $T, ['ses' => '2026-27', 'exam' => 'Term-1', 'cls' => '1st', 'subject' => 'Synthetic Maths', 'rows' => ['pr-s1' => ['pt' => '9', 'nb' => '5', 'sea' => '5', 'te' => '70']]]); pc($c === 200, 'class teacher saves another teacher\'s subject');
    [$c] = prq('marks', $T, ['ses' => '2026-27', 'exam' => 'Term-1', 'cls' => '1st', 'subject' => 'Not In Timetable', 'rows' => []]); pc($c === 403, 'class teacher only for subjects of the class');
    pc((int)$db->query("SELECT COUNT(*) FROM sch_log WHERE x LIKE 'Term-1 marks saved: Class 1st Synthetic Maths%by the class teacher'")->fetchColumn() >= 1, 'class teacher correction logged');
    // A combined timetable name is not counted twice for the rank when its parts have their own marks (8th: Hindi, Sanskrit and Hindi/Sanskrit)
    sch_rec_put('prcMarks', '2026-27|Term-1|1st|Synthetic English/Synthetic Maths', ['subject' => 'Synthetic English/Synthetic Maths', 'rows' => ['pr-s2' => ['pt' => 10, 'nb' => 5, 'sea' => 5, 'te' => 80]]], 'principal');
    [$c, $d] = prq('certs', $P, null, '&ses=2026-27&cls=1st'); pc($c === 200 && ($d['certs']['pr-s1']['rank'] ?? 0) === 1 && !isset($d['certs']['pr-s2']), 'combined subject left out of the rank ' . json_encode($d));
    $db->exec("DELETE FROM sch_records WHERE k='prcMarks' AND r='2026-27|Term-1|1st|Synthetic English/Synthetic Maths'");
    // Attendance on the slip comes from the daily class attendance (studAtt) — owner, 20 Nov 2026
    sch_rec_put('studAtt', '2026-10-05|1st', ['absent' => ['pr-s2']], 'principal'); sch_rec_put('studAtt', '2026-10-06|1st', ['absent' => []], 'principal');
    [$c, $d] = prq('marks', $P, null, '&ses=2026-27&exam=Term-1&cls=1st');
    pc($c === 200 && ($d['att']['pr-s1']['days'] ?? 0) === 2 && ($d['att']['pr-s1']['present'] ?? 0) === 2 && ($d['att']['pr-s2']['present'] ?? -1) === 1, 'attendance from daily records ' . json_encode($d['att'] ?? null));
    $db->exec("DELETE FROM sch_records WHERE k='studAtt' AND r IN ('2026-10-05|1st','2026-10-06|1st')");
    // Rankers' certificate numbers + public "Scan to verify" (owner, 20 Nov 2026): the server works out the rank (s1 has both subjects, s2 only one)
    [$c, $d] = prq('certs', $P, null, '&ses=2026-27&cls=1st'); $ce = $d['certs'] ?? [];
    pc($c === 200 && ($ce['pr-s1']['rank'] ?? 0) === 1 && !isset($ce['pr-s2']) && preg_match('/^HY26-[0-9A-Z]{7}$/', $ce['pr-s1']['code'] ?? ''), 'certificate numbers ' . json_encode($d));
    [$c] = prq('certs', $F, null, '&ses=2026-27&cls=1st'); pc($c === 403, 'parents cannot make certificate numbers');
    $vg = function ($code) { $h = curl_init('http://127.0.0.1:8099/school/verify-cert.php?c=' . urlencode($code)); curl_setopt_array($h, [CURLOPT_RETURNTRANSFER => true, CURLOPT_PROXY => '']); $r = (string)curl_exec($h); curl_close($h); return $r; };
    $vp = $vg($ce['pr-s1']['code']); pc(str_contains($vp, 'Genuine certificate') && str_contains($vp, 'Synthetic Progress One') && str_contains($vp, '68.5%') && !str_contains($vp, 'Warning'), 'verify page shows the certificate');
    pc(str_contains($vg('HY26-ZZZZZZZ'), 'Not verified') && str_contains($vg('<script>'), 'Not verified'), 'verify page refuses unknown numbers');
    // marks change (s2 now has Maths too and tops the class) → s1's old 1st-rank certificate number is withdrawn
    [$c] = prq('marks', $T, ['ses' => '2026-27', 'exam' => 'Term-1', 'cls' => '1st', 'subject' => 'Synthetic Maths', 'rows' => ['pr-s1' => ['pt' => '9', 'nb' => '5', 'sea' => '5', 'te' => '70'], 'pr-s2' => ['pt' => '10', 'nb' => '5', 'sea' => '5', 'te' => '80']]]);
    [$c, $d] = prq('certs', $P, null, '&ses=2026-27&cls=1st'); $ce2 = $d['certs'] ?? [];
    pc(($ce2['pr-s2']['rank'] ?? 0) === 1 && ($ce2['pr-s1']['rank'] ?? 0) === 2 && str_contains($vg($ce['pr-s1']['code']), 'Not verified'), 'a certificate whose rank changed is withdrawn ' . json_encode($ce2));
    [$c, , $raw] = prq('load', $P, null, '', 'api.php'); $recs = json_encode((json_decode($raw, true) ?? [])['records'] ?? null);
    pc($c === 200 && $recs !== 'null' && !str_contains($recs, 'prcMarks') && !str_contains($recs, 'Synthetic remark') && !str_contains($recs, 'base64'), 'legacy sync hides progress reports and photos');
    echo "Progress Report: old cards kept exactly (never overwritten), photos by student, old ERP mark limits, teacher scoping, co-scholastic checks and sync privacy passed\n";
} finally {
    $clean();
    if ($oldClasses !== null) sch_rec_put('classes', 'main', $oldClasses, 'principal'); else $db->exec("DELETE FROM sch_records WHERE k='classes' AND r='main'");
    if ($oldAlloc !== null) sch_rec_put('alloc', 'main', $oldAlloc, 'principal'); else $db->exec("DELETE FROM sch_records WHERE k='alloc' AND r='main'");
    foreach (['t:PRT', 't:PRX', 'p:9811177777'] as $u) { $db->prepare('DELETE FROM sch_sessions WHERE uid=?')->execute([$u]); $db->prepare('DELETE FROM sch_users WHERE uid=?')->execute([$u]); }
}
