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
$oldAlloc = sch_rec_get('alloc', 'main');
$P = prtok('principal', 'principal', 'P'); $T = prtok('t:PRT', 'teacher', 'PRT'); $T2 = prtok('t:PRX', 'teacher', 'PRX'); $F = prtok('p:9811177777', 'parent', '9811177777');
$clean = fn() => $db->exec("DELETE FROM sch_records WHERE k LIKE 'prc%' OR k LIKE 'pho%' OR (k='student' AND r IN ('pr-s1','pr-s2'))");
try {
    $clean();
    foreach ([['pr-s1', 'Synthetic Progress One'], ['pr-s2', 'Synthetic Progress Two']] as [$id, $n]) sch_rec_put('student', $id, ['id' => $id, 'adm' => strtoupper($id), 'name' => $n, 'cls' => '1st', 'section' => 'A', 'phone' => '9811177777'], 'principal');
    sch_rec_put('alloc', 'main', array_merge((array)$oldAlloc, [['t' => 'PRT', 'c' => '1st', 's' => 'Synthetic English']]), 'principal');

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
    [$c] = prq('extra', $T, ['ses' => '2026-27', 'exam' => 'Term-1', 'cls' => '1st', 'rows' => ['pr-s1' => ['art' => 'A', 'work' => 'B', 'health' => 'A', 'disc' => 'A', 'days' => '90', 'present' => '54', 'remark' => 'Synthetic remark']]]); pc($c === 200, 'class teacher saves co-scholastic');
    [$c, , $raw] = prq('load', $P, null, '', 'api.php'); $recs = json_encode((json_decode($raw, true) ?? [])['records'] ?? null);
    pc($c === 200 && $recs !== 'null' && !str_contains($recs, 'prcMarks') && !str_contains($recs, 'Synthetic remark') && !str_contains($recs, 'base64'), 'legacy sync hides progress reports and photos');
    echo "Progress Report: old cards kept exactly (never overwritten), photos by student, old ERP mark limits, teacher scoping, co-scholastic checks and sync privacy passed\n";
} finally {
    $clean();
    if ($oldAlloc !== null) sch_rec_put('alloc', 'main', $oldAlloc, 'principal'); else $db->exec("DELETE FROM sch_records WHERE k='alloc' AND r='main'");
    foreach (['t:PRT', 't:PRX', 'p:9811177777'] as $u) { $db->prepare('DELETE FROM sch_sessions WHERE uid=?')->execute([$u]); $db->prepare('DELETE FROM sch_users WHERE uid=?')->execute([$u]); }
}
