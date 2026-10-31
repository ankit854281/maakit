<?php
// Old ERP Reports (school/oldreports_api.php) — CI database only, synthetic rows only.
require __DIR__ . '/../../config.php';
if (DB_NAME !== 'maakit_jaanch') throw new RuntimeException('CI database only');
require __DIR__ . '/../../school/inc.php';
function orc($v, $m) { if (!$v) throw new RuntimeException('Old ERP Reports flow: ' . $m); }
function ortok($uid, $role, $tid = null) {
    $db = sch_db();
    $db->prepare('INSERT INTO sch_users(uid,role,tid,must_change) VALUES(?,?,?,0) ON DUPLICATE KEY UPDATE role=VALUES(role),tid=VALUES(tid),must_change=0')->execute([$uid, $role, $tid]);
    $t = bin2hex(random_bytes(32));
    $db->prepare('INSERT INTO sch_sessions(token_hash,uid,expires_at) VALUES(?,?,DATE_ADD(NOW(),INTERVAL 1 DAY))')->execute([hash('sha256', $t), $uid]);
    return $t;
}
function orq($a, $token, $body = null, $q = '', $api = 'oldreports_api.php') {
    $h = ['X-School-Token: ' . $token]; $o = [CURLOPT_RETURNTRANSFER => true, CURLOPT_PROXY => '', CURLOPT_TIMEOUT => 20];
    if ($body !== null) { $o[CURLOPT_POST] = true; $h[] = 'Content-Type: application/json'; $o[CURLOPT_POSTFIELDS] = json_encode($body); }
    $o[CURLOPT_HTTPHEADER] = $h;
    $c = curl_init('http://127.0.0.1:8099/school/' . $api . '?a=' . $a . $q); curl_setopt_array($c, $o);
    $raw = curl_exec($c); $code = curl_getinfo($c, CURLINFO_HTTP_CODE); curl_close($c);
    orc(is_string($raw) && !preg_match('/Fatal error|Warning:|Uncaught|Deprecated:|Notice:/', $raw), 'PHP response ' . $a . ': ' . substr((string)$raw, 0, 300));
    return [$code, json_decode((string)$raw, true), (string)$raw];
}
$db = sch_db();
$P = ortok('principal', 'principal', 'P'); $A = ortok('orx:acc', 'accountant'); $R = ortok('orx:rec', 'reception'); $F = ortok('p:9811166666', 'parent', '9811166666');
$clean = fn() => $db->exec("DELETE FROM sch_records WHERE k='regPage'");
$pg = fn($rows) => base64_encode(gzencode(json_encode($rows)));
try {
    $clean();
    $cash = [['Date', 'Particular', 'Amount'], ['01-04-2025', 'Synthetic cash entry', '500']];
    $master = [['Sno', 'Class'], ['1', 'Synthetic Class']];
    $file = ['pages' => [
        ['session' => '2025-26', 'page' => 'report_cashbook.php', 'title' => 'Cashbook', 'group' => '11 Accounts', 'h' => $pg($cash)],
        ['session' => '2025-26', 'page' => 'master_class.php', 'title' => 'Class Master', 'group' => '01 Masters & Settings', 'h' => $pg($master)],
    ]];
    [$c] = orq('index', $F); orc($c === 403, 'parents cannot open old ERP reports');
    [$c] = orq('import', $A, $file + ['apply' => true]); orc($c === 403, 'only the Principal imports');
    [$c, $d] = orq('import', $P, $file); orc($c === 200 && $d['toAdd'] === 2 && !$d['applied'], 'preview ' . json_encode($d));
    orc(!$db->query("SELECT COUNT(*) FROM sch_records WHERE k='regPage'")->fetchColumn(), 'preview saves nothing');
    [$c, $d] = orq('import', $P, $file + ['apply' => true]); orc($c === 200 && $d['applied'] && $d['toAdd'] === 2, 'apply');
    [$c, $d] = orq('import', $P, $file + ['apply' => true]); orc($c === 200 && $d['toAdd'] === 0 && $d['unchanged'] === 2, 'second import adds nothing');
    $bad = $file; $bad['pages'][0]['h'] = $pg([['Date', 'Particular', 'Amount'], ['01-04-2025', 'Changed', '9']]);
    [$c] = orq('import', $P, $bad + ['apply' => true]); orc($c === 409, 'a page already here is never changed');
    $bad = $file; $bad['pages'][0]['page'] = '../config.php';
    [$c] = orq('import', $P, $bad); orc($c === 400, 'page names are checked');
    $bad = $file; $bad['pages'][0]['h'] = 'bm90IGd6aXA=';
    [$c] = orq('import', $P, $bad); orc($c === 400, 'a damaged page is refused');
    // Who reads what
    [$c, $d] = orq('index', $P); orc($c === 200 && count($d['pages']) === 2 && !isset($d['pages'][0]['h']) && $d['isPrincipal'], 'Principal sees every page, no rows in the index');
    [$c, $d] = orq('index', $A); orc($c === 200 && array_column($d['pages'], 'page') === ['report_cashbook.php'] && !$d['isPrincipal'], 'accountant sees only account pages');
    [$c, $d] = orq('index', $R); orc($c === 200 && !$d['pages'], 'reception sees no account or master pages');
    [$c, $d] = orq('page', $A, null, '&ses=2025-26&page=report_cashbook.php'); orc($c === 200 && json_decode(gzdecode(base64_decode($d['page']['h'])), true) === $cash, 'page rows exactly as imported');
    [$c] = orq('page', $A, null, '&ses=2025-26&page=master_class.php'); orc($c === 404, 'accountant cannot open a master page');
    [$c] = orq('page', $P, null, '&ses=2025-26&page=..%2Fx.php'); orc($c === 400, 'bad page name');
    [$c, , $raw] = orq('load', $P, null, '', 'api.php'); $recs = json_encode((json_decode($raw, true) ?? [])['records'] ?? null);
    orc($c === 200 && $recs !== 'null' && !str_contains($recs, 'regPage') && !str_contains($recs, 'report_cashbook'), 'legacy sync leaves old ERP reports out');
    echo "Old ERP Reports: Principal-only import (preview, never overwrites, names and data checked), per-role pages, exact rows and sync privacy passed\n";
} finally {
    $clean();
    foreach (['orx:acc', 'orx:rec', 'p:9811166666'] as $u) { $db->prepare('DELETE FROM sch_sessions WHERE uid=?')->execute([$u]); $db->prepare('DELETE FROM sch_users WHERE uid=?')->execute([$u]); }
}
