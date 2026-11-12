<?php
// ============================================================
//  जाँच — Staff attendance for all staff (school/staffatt_api.php, 12 Nov 2026)
//
//  Principal / Accountant mark teachers + non-teaching staff for many days at
//  once → teachers go to the same staffAtt record (arrangement, salary), half day
//  = from bell 4, substitutes kept → non-teaching to salAtt → salary uses it
//  (paid leave covers, no teacher bonus) → Chairman only views → Sunday /
//  holiday / future skipped → salAtt never in the normal load.
// ============================================================
require_once __DIR__ . '/../../config.php';
if (DB_NAME !== 'maakit_jaanch') throw new RuntimeException('CI database only');
require_once __DIR__ . '/../../inc/db.php';
require_once __DIR__ . '/../../school/inc.php';
require_once __DIR__ . '/../../school/salary_lib.php';

function sa_check($ok, $m) { if (!$ok) throw new RuntimeException('Staff attendance flow: ' . $m); }
function sa_http(string $path, $body = null, ?string $tok = null): array {
    $c = curl_init('http://127.0.0.1:8099' . $path);
    $h = ['X-School-Token: ' . ($tok ?? '')]; $o = [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30, CURLOPT_PROXY => ''];
    if ($body !== null) { $h[] = 'Content-Type: application/json'; $o[CURLOPT_POST] = true; $o[CURLOPT_POSTFIELDS] = json_encode($body); }
    $o[CURLOPT_HTTPHEADER] = $h; curl_setopt_array($c, $o);
    $raw = (string)curl_exec($c); $code = curl_getinfo($c, CURLINFO_HTTP_CODE); curl_close($c);
    sa_check(!preg_match('/Fatal error|Warning:|Uncaught|Deprecated/', $raw), "PHP error at $path: " . substr($raw, 0, 300));
    return [$code, json_decode($raw, true), $raw];
}
$api = fn($a, $b = null, $t = null) => sa_http('/school/api.php?a=' . $a, $b, $t);
$sa  = fn($a, $b = null, $t = null, $q = '') => sa_http('/school/staffatt_api.php?a=' . $a . $q, $b, $t);

foreach (['sch_sessions', 'sch_users', 'sch_records', 'sch_log', 'sch_login_fail'] as $t) $pdo->exec("DELETE FROM $t");
$put = fn($k, $r, $d) => sch_rec_put($k, $r, $d, 'principal');
$put('settings', 'main', ['school' => 'Staff Test School', 'session' => '2026-27', 'sessionStart' => '2026-04-01']);
$put('teacher', 'K', ['name' => 'Kartikey Sir', 'role' => 'Social Science']);
$put('teacher', 'MA', ['name' => 'Mamta Ma\'am', 'role' => 'Hindi']);
$put('salStaff', 'xdriver1', ['name' => 'Babulal Mishra', 'role' => 'Driver — Bus', 'monthly' => 26000, 'paidLeave' => 1, 'active' => true]);
$put('salStaff', 'xdai1', ['name' => 'Parwati', 'role' => 'Dai', 'monthly' => null, 'paidLeave' => 1, 'active' => true]);
$put('cal', 'h1', ['id' => 'h1', 'date' => '2026-09-15', 'title' => 'Holiday', 'type' => 'Holiday']);
$put('staffAtt', '2026-09-02', ['absent' => ['MA'], 'subs' => ['MA|1' => 'K']]);
$pin = fn($uid, $role, $tid, $p) => $pdo->prepare('INSERT INTO sch_users (uid, role, tid, pin_hash, must_change) VALUES (?,?,?,?,0)')->execute([$uid, $role, $tid, password_hash($p, PASSWORD_DEFAULT)]);
$pin('principal', 'principal', 'P', '2468'); $pin('accountant', 'accountant', null, '4321'); $pin('t:K', 'teacher', 'K', '7777'); $pin('chairman', 'chairman', null, '6666');
$tok = [];
foreach ([['P', ['role' => 'principal', 'pin' => '2468']], ['A', ['role' => 'accountant', 'pin' => '4321']], ['K', ['role' => 'teacher', 'tid' => 'K', 'pin' => '7777']], ['CH', ['role' => 'chairman', 'pin' => '6666']]] as [$n, $b]) {
    [, $j] = $api('login', $b); $tok[$n] = $j['token'] ?? null; sa_check($tok[$n], "login $n");
}

// ---- who ----
[$c] = $sa('month', null, $tok['K'], '&ym=2026-09'); sa_check($c === 403, 'teacher cannot open staff attendance');
[$c, $j] = $sa('month', null, $tok['CH'], '&ym=2026-09'); sa_check($c === 200 && $j['can'] === false, 'Chairman views only');
[$c] = $sa('save', ['ym' => '2026-09', 'set' => ['2026-09-03' => ['t:K' => 'A']]], $tok['CH']); sa_check($c === 403, 'Chairman cannot save');
[$c] = $sa('month', null, $tok['P'], '&ym=' . date('Y-m', strtotime('+1 month'))); sa_check($c === 400, 'no future month');
[, $j] = $sa('month', null, $tok['A'], '&ym=2026-09');
sa_check(count($j['staff']) === 4 && count($j['days']) === 30 && $j['att']['t:MA']['2026-09-02'] === 'A', 'month: 2 teachers + 2 staff, existing absence shown: ' . json_encode($j['att']));
$kinds = array_count_values(array_column($j['days'], 'kind')); sa_check($kinds['sun'] === 4 && $kinds['hol'] === 1 && $kinds['work'] === 25, 'Sundays and holiday marked');

// ---- bulk save (accountant) ----
[$c, $j] = $sa('save', ['ym' => '2026-09', 'set' => [
    '2026-09-02' => ['t:K' => 'H', 'xdriver1' => 'A'],          // K half day; MA stays absent, her substitute kept
    '2026-09-03' => ['t:MA' => 'A', 'xdriver1' => 'A', 'xdai1' => 'H'],
    '2026-09-04' => ['xdriver1' => 'A', 'nobody' => 'A', 't:K' => 'X'],
    '2026-09-06' => ['t:K' => 'A'],                               // Sunday — skipped
    '2026-09-15' => ['xdriver1' => 'A']]], $tok['A']);           // holiday — skipped
sa_check($c === 200 && $j['changed'] === 6 && $j['skipped'] === 4, 'bulk save counts: ' . json_encode($j));
$d2 = sch_rec_get('staffAtt', '2026-09-02');
sa_check(in_array('K', $d2['absent'], true) && in_array('MA', $d2['absent'], true) && (int)$d2['from']['K'] === 4 && $d2['subs']['MA|1'] === 'K', 'teacher half day = from bell 4, other absence and substitute kept: ' . json_encode($d2));
sa_check(sch_rec_get('staffAtt', '2026-09-06') === null && sch_rec_get('salAtt', '2026-09-15') === null, 'Sunday and holiday not saved');
sa_check(sch_rec_get('salAtt', '2026-09-03')['h'] === ['xdai1'], 'non-teaching half day');
// mark present again
$sa('save', ['ym' => '2026-09', 'set' => ['2026-09-04' => ['xdriver1' => 'P']]], $tok['P']);
sa_check(!in_array('xdriver1', sch_rec_get('salAtt', '2026-09-04')['a'], true), 'present again removes the absence');

// ---- salary uses it ----
$sheet = sal_sheet('2026-09'); $row = array_values(array_filter($sheet['rows'], fn($r) => $r['staff']['id'] === 'xdriver1'))[0];
$cc = $row['calc'];
sa_check($cc['auto']['total'] == 2 && $cc['unpaid'] == 1 && $cc['deduction'] === 1040 && $cc['autoBonus'] === 0 && $cc['workedOnly'] === false, 'driver: 2 absent − 1 paid leave = 1 day cut (26000/25), no teacher bonus: ' . json_encode($cc));
$rowK = array_values(array_filter($sheet['rows'], fn($r) => $r['staff']['id'] === 't:K'))[0];
sa_check($rowK['calc']['auto']['half'] === 1, 'teacher half day reaches salary');

// ---- privacy ----
[, $j] = $api('load', null, $tok['K']); sa_check(!array_filter($j['records'], fn($x) => $x['k'] === 'salAtt'), 'salAtt never in the normal load');
sa_check((int)$pdo->query("SELECT COUNT(*) FROM sch_log WHERE k='Staff Attendance' AND x LIKE 'Bulk update 2026-09%'")->fetchColumn() === 2, 'logged');

echo "Staff attendance flow: OK\n";
