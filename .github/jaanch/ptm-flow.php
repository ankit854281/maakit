<?php
// PTM Desk (school/ptm_api.php) — CI database only, synthetic rows only.
require __DIR__ . '/../../config.php';
if (DB_NAME !== 'maakit_jaanch') throw new RuntimeException('CI database only');
require __DIR__ . '/../../school/inc.php';
function tc($v, $m) { if (!$v) throw new RuntimeException('PTM flow: ' . $m); }
function ttok($uid, $role, $tid = null) {
    $db = sch_db();
    $db->prepare('INSERT INTO sch_users(uid,role,tid,must_change) VALUES(?,?,?,0) ON DUPLICATE KEY UPDATE role=VALUES(role),tid=VALUES(tid),must_change=0')->execute([$uid, $role, $tid]);
    $t = bin2hex(random_bytes(32));
    $db->prepare('INSERT INTO sch_sessions(token_hash,uid,expires_at) VALUES(?,?,DATE_ADD(NOW(),INTERVAL 1 DAY))')->execute([hash('sha256', $t), $uid]);
    return $t;
}
function tq($a, $token, $body = null, $q = '') {
    $h = ['X-School-Token: ' . $token]; $o = [CURLOPT_RETURNTRANSFER => true, CURLOPT_PROXY => '', CURLOPT_TIMEOUT => 30];
    if ($body !== null) { $o[CURLOPT_POST] = true; $h[] = 'Content-Type: application/json'; $o[CURLOPT_POSTFIELDS] = json_encode($body); }
    $o[CURLOPT_HTTPHEADER] = $h;
    $c = curl_init('http://127.0.0.1:8099/school/ptm_api.php?a=' . $a . $q); curl_setopt_array($c, $o);
    $raw = curl_exec($c); $code = curl_getinfo($c, CURLINFO_HTTP_CODE); curl_close($c);
    tc(is_string($raw) && !preg_match('/Fatal error|Warning:|Uncaught|Deprecated:|Notice:/', $raw), 'PHP response ' . $a . ': ' . substr((string)$raw, 0, 300));
    return [$code, json_decode((string)$raw, true)];
}
$db = sch_db(); $date = date('Y-m-d'); $y = (int)date('Y'); if ((int)date('n') < 4) $y--; $ses = $y . '-' . substr((string)($y + 1), 2);
$P = ttok('principal', 'principal', 'P'); $PW = ttok('t:PW', 'teacher', 'PW'); $T = ttok('t:PTX', 'teacher', 'PTX'); $C = ttok('chairman', 'chairman'); $F = ttok('p:9811155555', 'parent', '9811155555');
$clean = function () use ($db, $ses) { $db->exec("DELETE FROM sch_records WHERE k='ptmVisit' OR (k='student' AND r IN ('pt-s1','pt-s2','pt-s3')) OR (k='prcMarks' AND r LIKE '" . $ses . "|Term-1|7th|Synthetic%')"); };
try {
    $clean();
    foreach ([['pt-s1', 'Synthetic Ptm One', '9811155555'], ['pt-s2', 'Synthetic Ptm Two', '9811155555'], ['pt-s3', 'Synthetic Ptm Three', '9811166666']] as [$id, $n, $ph])
        sch_rec_put('student', $id, ['id' => $id, 'adm' => strtoupper($id), 'name' => $n, 'cls' => $id === 'pt-s2' ? '8th' : '7th', 'section' => 'A', 'father' => 'Synthetic Father', 'phone' => $ph], 'principal');
    sch_rec_put('prcMarks', "$ses|Term-1|7th|Synthetic Maths", ['subject' => 'Synthetic Maths', 'rows' => ['pt-s1' => ['pt' => 8, 'nb' => 3, 'sea' => 4, 'te' => 20], 'pt-s3' => ['pt' => 10, 'nb' => 5, 'sea' => 5, 'te' => 70]]], 'principal');
    sch_rec_put('prcMarks', "$ses|Term-1|7th|Synthetic English", ['subject' => 'Synthetic English', 'rows' => ['pt-s1' => ['pt' => 9, 'nb' => 3, 'sea' => 5, 'te' => 'AB'], 'pt-s3' => ['pt' => 10, 'nb' => 5, 'sea' => 5, 'te' => 75]]], 'principal');
    [$c] = tq('cls', $T, null, '&cls=7th'); tc($c === 403, 'another teacher cannot open the PTM desk');
    [$c] = tq('cls', $F, null, '&cls=7th'); tc($c === 403, 'parents cannot open the PTM desk');
    [$c, $d] = tq('cls', $PW, null, '&cls=7th&date=' . $date);
    $k = array_column($d['kids'] ?? [], null, 'id');
    tc($c === 200 && isset($k['pt-s1'], $k['pt-s3']) && !isset($k['pt-s2']), 'desk teacher sees class 7th ' . json_encode($d));
    tc(($k['pt-s1']['res']['pct'] ?? null) == 26 && ($k['pt-s3']['res']['rank'] ?? 0) === 1 && ($k['pt-s1']['res']['nbLow'] ?? 0) === 2, 'result summary ' . json_encode($k['pt-s1']['res'] ?? null));
    tc(count($k['pt-s1']['sib']) === 1 && $k['pt-s1']['sib'][0]['id'] === 'pt-s2', 'brother/sister by the parent mobile');
    [$c, $d] = tq('visit', $PW, ['date' => $date, 'sid' => 'pt-s1', 'parent' => 'Synthetic Father', 'phone' => '9811155555', 'slip' => true, 'promise' => $date, 'pamt' => '1500', 'mood' => 3, 'tags' => ['Padhai', 'Nope'], 'note' => 'Maths practice', 'complaint' => 'Synthetic complaint', 'cat' => 'Bus', 'siblings' => true]);
    tc($c === 200 && $d['visit']['tags'] === ['Academics'] && $d['visit']['cmp']['status'] === 'Open' && $d['visit']['cmp']['cat'] === 'Transport' && $d['also'] === ['Synthetic Ptm Two'], 'visit saved with complaint and sibling ' . json_encode($d));
    tc((bool)sch_rec_get('ptmVisit', "$date|pt-s2"), 'sibling marked as came');
    [$c] = tq('visit', $C, ['date' => $date, 'sid' => 'pt-s3']); tc($c === 403, 'Chairman reads, the desk writes');
    [$c, $d] = tq('report', $C, null, '&date=' . $date);
    tc($c === 200 && count($d['visits']) >= 2 && ($d['classes']['7th']['came'] ?? 0) >= 1 && in_array('pt-s3', array_column($d['classes']['7th']['not'] ?? [], 'id'), true) && count(array_filter($d['complaints'], fn($v) => $v['sid'] === 'pt-s1')) === 1, 'day report ' . json_encode($d['classes']['7th'] ?? null));
    [$c, $d] = tq('resolve', $C, ['key' => "$date|pt-s1", 'action' => 'Bus driver spoken to']); tc($c === 200 && $d['visit']['cmp']['status'] === 'Resolved', 'Chairman closes a complaint');
    [$c, $d] = tq('visit', $PW, ['date' => $date, 'sid' => 'pt-s1', 'complaint' => 'Synthetic complaint', 'mood' => 4]); tc($c === 200 && $d['visit']['cmp']['status'] === 'Resolved' && $d['visit']['cmp']['action'] === 'Bus driver spoken to', 'saving again keeps a closed complaint closed');
    [$c, , ] = tq('cls', $P, null, '&cls=7th'); tc($c === 200, 'Principal opens the desk');
    echo "PTM Desk: desk teacher only, result summary, siblings, visit + complaint, report, resolve passed\n";
} finally { $clean(); }
