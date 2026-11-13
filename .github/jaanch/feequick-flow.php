<?php
// जाँच — Quick Fee in the Manager app (owner, 13 Nov 2026: "manager app se fee lena thoda smart banaiye").
// feequick.js only uses feecollect_api.php; this checks the Manager can collect, ?a=today adds it up, and the old rules hold.
// Set FQ_KEEP=1 to leave the synthetic data for the browser check.
declare(strict_types=1);
require __DIR__ . '/../../config.php';
if (DB_NAME !== 'maakit_jaanch') throw new RuntimeException('CI database only');
require __DIR__ . '/../../school/inc.php';
require __DIR__ . '/../../school/feecollect_lib.php';
function fq($v, $m) { if (!$v) throw new RuntimeException('Quick Fee flow: ' . $m); }
function fqtok($uid, $role) { $db = sch_db();
    $db->prepare('INSERT INTO sch_users(uid,role,tid,must_change) VALUES(?,?,NULL,0) ON DUPLICATE KEY UPDATE role=VALUES(role),must_change=0')->execute([$uid, $role]);
    $t = bin2hex(random_bytes(32)); $db->prepare('INSERT INTO sch_sessions(token_hash,uid,expires_at) VALUES(?,?,DATE_ADD(NOW(),INTERVAL 1 DAY))')->execute([hash('sha256', $t), $uid]); return $t; }
function fqreq($a, $tok, $body = null, $q = '') { $h = ['X-School-Token: ' . $tok]; $o = [CURLOPT_RETURNTRANSFER => true, CURLOPT_PROXY => '', CURLOPT_TIMEOUT => 20];
    if ($body !== null) { $o[CURLOPT_POST] = true; $h[] = 'Content-Type: application/json'; $o[CURLOPT_POSTFIELDS] = json_encode($body); } $o[CURLOPT_HTTPHEADER] = $h;
    $c = curl_init('http://127.0.0.1:8099/school/feecollect_api.php?a=' . $a . $q); curl_setopt_array($c, $o); $raw = (string)curl_exec($c); $code = curl_getinfo($c, CURLINFO_HTTP_CODE); curl_close($c);
    fq(!preg_match('/Fatal error|Warning:|Uncaught|Notice:/', $raw), "PHP $a: " . substr($raw, 0, 300)); return [$code, json_decode($raw, true)]; }
$db = sch_db(); $old = sch_rec_get('settings', 'main');
$M = fqtok('manager', 'manager'); $CH = fqtok('chairman', 'chairman'); $n = fn() => bin2hex(random_bytes(16));
$clean = function () use ($db) { $db->exec("DELETE FROM sch_records WHERE k LIKE 'ofc%' OR (k='student' AND r LIKE 'fq-%')"); };
$keep = getenv('FQ_KEEP') === '1';
try {
    $clean(); $today = (new DateTimeImmutable('now', new DateTimeZone('Asia/Kolkata')))->format('Y-m-d');
    sch_rec_put('settings', 'main', ['session' => '2026-27', 'sessionStart' => '2026-04-01', 'school' => 'Quick Fee School'], 'principal');
    foreach ([['fq-1', 'Riya Quick', '9811100001'], ['fq-2', 'Om Quick', '9811100002']] as [$id, $nm]) sch_rec_put('student', $id, ['id' => $id, 'adm' => strtoupper($id), 'name' => $nm, 'cls' => '5th', 'section' => 'A', 'father' => 'F ' . $nm, 'phone' => $nm === 'Riya Quick' ? '9811100001' : '9811100002'], 'principal');
    $heads = []; $i = 0; foreach ([4, 5, 6, 7, 8, 9, 10, 11, 12] as $m) { $heads[] = ['i' => ++$i, 'm' => $m, 'name' => 'Tution Fee', 'amt' => 80000, 'paid' => $m <= 6 ? 80000 : 0, 'na' => false]; $heads[] = ['i' => ++$i, 'm' => $m, 'name' => 'Transport', 'amt' => 60000, 'paid' => $m <= 6 ? 60000 : 0, 'na' => false]; }
    foreach (['fq-1', 'fq-2'] as $sid) sch_rec_put('ofcLed', "2026-27|$sid", ['sid' => $sid, 'name' => $sid === 'fq-1' ? 'Riya Quick' : 'Om Quick', 'cls' => '5th', 'section' => 'A', 'adm' => strtoupper($sid), 'father' => 'F', 'mother' => 'M', 'scheme' => 'Regular', 'heads' => $heads, 'oldFee' => 0], 'principal');
    sch_rec_put('ofcSeq', '2026-27', ['prefix' => '1-', 'n' => 391], 'principal');
    sch_rec_put('ofcCfg', 'main', ['sessions' => ['2026-27' => ['from' => '2026-11-01', 'by' => 'Principal', 'at' => 1, 'firstNo' => '1-392']]], 'principal');

    [$c, $s] = fqreq('state', $M); fq($c === 200 && $s['canCollect'] === true && $s['next'] === '1-392', 'Manager may collect ' . json_encode([$c, $s['canCollect'] ?? null, $s['next'] ?? null]));
    // July + August tuition and transport (what "Till Aug" picks), cash
    [$c, $r] = fqreq('collect', $M, ['ses' => '2026-27', 'sid' => 'fq-1', 'alloc' => [['i' => 7, 'amt' => '800.00'], ['i' => 8, 'amt' => '600.00'], ['i' => 9, 'amt' => '800.00'], ['i' => 10, 'amt' => '600.00']], 'late' => '0.00', 'discount' => '0', 'mode' => 'Cash', 'date' => $today, 'nonce' => $n(), 'remark' => '']);
    fq($c === 200 && $r['receipt']['no'] === '1-392' && $r['receipt']['received'] === 280000 && $r['receipt']['balanceAfter'] === 560000, 'Manager cash receipt ' . json_encode($r));
    // typed amount ₹1000: oldest first → Sep tuition 800 + 200 of Sep transport, UPI with a deposit bank
    [$c, $r] = fqreq('collect', $M, ['ses' => '2026-27', 'sid' => 'fq-1', 'alloc' => [['i' => 11, 'amt' => '800.00'], ['i' => 12, 'amt' => '200.00']], 'late' => '0.00', 'discount' => '0', 'mode' => 'UPI', 'date' => $today, 'nonce' => $n(), 'depositBank' => $s['banks'][0]['name'], 'depositDate' => $today, 'remark' => 'UPI ref 123']);
    fq($c === 200 && $r['receipt']['no'] === '1-393' && $r['receipt']['mode'] === 'UPI', 'UPI part payment ' . json_encode($r));
    [$c] = fqreq('collect', $M, ['ses' => '2026-27', 'sid' => 'fq-2', 'alloc' => [['i' => 7, 'amt' => '900.00']], 'mode' => 'Cash', 'date' => $today, 'nonce' => $n()]); fq($c === 400, 'more than the head is refused');
    [$c] = fqreq('collect', $M, ['ses' => '2026-27', 'sid' => 'fq-2', 'alloc' => [['i' => 7, 'amt' => '800.00']], 'mode' => 'UPI', 'date' => $today, 'nonce' => $n()]); fq($c === 400, 'UPI without a deposit bank is refused');
    [$c] = fqreq('collect', $CH, ['ses' => '2026-27', 'sid' => 'fq-2', 'alloc' => [['i' => 7, 'amt' => '800.00']], 'mode' => 'Cash', 'date' => $today, 'nonce' => $n()]); fq($c === 403, 'Chairman cannot take fees');
    [$c, $t] = fqreq('today', $M, null, '&ses=2026-27'); fq($c === 200 && count($t['receipts']) === 2 && $t['total'] === 380000 && $t['byMode']['Cash'] === 280000 && $t['byMode']['UPI'] === 100000 && $t['receipts'][0]['no'] === '1-393', "today's fee " . json_encode($t));
    [$c, $t] = fqreq('today', $CH, null, '&ses=2026-27'); fq($c === 200 && $t['total'] === 380000, 'Chairman sees today');
    echo "Quick Fee flow: OK\n";
} finally { if (!$keep) { $clean(); if ($old !== null) sch_rec_put('settings', 'main', $old, 'principal'); } }
