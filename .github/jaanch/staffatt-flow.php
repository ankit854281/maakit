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
$put('salSeed', 'former-2026-10', ['test' => 1]);   // the one-time former-staff seed is checked separately at the end
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

// ---- staff came on a holiday / Sunday (owner, 13 Nov 2026) ----
[$c] = $sa('open', ['date' => '2026-09-15', 'on' => true], $tok['CH']); sa_check($c === 403, 'Chairman cannot open a holiday');
[$c] = $sa('open', ['date' => '2026-09-16', 'on' => true], $tok['P']); sa_check($c === 400, 'a normal working day cannot be opened');
[$c] = $sa('open', ['date' => '2026-09-15', 'on' => true], $tok['A']); sa_check($c === 200, 'holiday opened');
[$c] = $sa('open', ['date' => '2026-09-13', 'on' => true], $tok['P']); sa_check($c === 200, 'Sunday opened');
[, $j] = $sa('month', null, $tok['P'], '&ym=2026-09'); $dd = array_column($j['days'], null, 'date');
sa_check($dd['2026-09-15']['kind'] === 'work' && $dd['2026-09-15']['open'] && $dd['2026-09-13']['kind'] === 'work' && $dd['2026-09-14']['open'] === false, 'opened days are working days for staff: ' . json_encode($dd['2026-09-15']));
[, $j] = $sa('save', ['ym' => '2026-09', 'set' => ['2026-09-15' => ['xdriver1' => 'A', 't:K' => 'A']]], $tok['P']); sa_check($j['changed'] === 2 && $j['skipped'] === 0, 'attendance saved on the opened holiday');
sa_check(in_array('K', sch_rec_get('staffAtt', '2026-09-15')['absent'], true), 'teacher absent on the opened holiday');
sa_check(sal_sheet('2026-09')['workDays'] === 27, 'salary counts the opened days');
$sa('open', ['date' => '2026-09-13', 'on' => false], $tok['P']); sa_check(sal_sheet('2026-09')['workDays'] === 26 && sch_rec_get('salOpen', '2026-09-13') === null, 'closed again');
sa_check(sch_rec_get('cal', 'h1')['type'] === 'Holiday', 'school calendar unchanged');
$sa('open', ['date' => '2026-09-15', 'on' => false], $tok['P']); $pdo->exec("DELETE FROM sch_records WHERE k IN ('staffAtt','salAtt') AND r='2026-09-15'");

// ---- staff who left: worked till 31 Oct 2026 (one-time seed, owner 12 Nov 2026) ----
$put('teacher', 'BY', ['name' => 'Brijesh Yadav Sir', 'role' => 'Maths']);
$put('settings', 'main', ['school' => 'Staff Test School', 'session' => '2026-27', 'sessionStart' => '2026-04-01', 'leftTeachers' => ['AP' => "Amisha Pandey Ma'am"]]);
$pdo->exec("DELETE FROM sch_records WHERE k='salSeed'");
[, $j] = $sa('month', null, $tok['CH'], '&ym=2026-10'); sa_check(!sch_rec_get('salSeed', 'former-2026-10'), 'Chairman view does not write the seed');
[$c, $j] = $sa('month', null, $tok['P'], '&ym=2026-10'); $L = array_column($j['staff'], null, 'id');
sa_check($c === 200 && ($L['t:BY']['until'] ?? '') === '2026-10-31' && ($L['t:AP']['until'] ?? '') === '2026-10-31', 'existing teacher and left teacher found by name: ' . json_encode($j['staff']));
$new = array_values(array_filter($j['staff'], fn($x) => in_array($x['name'], ['Akash Tiwari', "Shiva Rai Ma'am"], true) && $x['until'] === '2026-10-31'));
sa_check(count($new) === 2 && count($j['staff']) === 8, 'Akash Tiwari and Shiva Rai added as staff till October: ' . json_encode($j['staff']));
$sa('month', null, $tok['P'], '&ym=2026-10'); sa_check((int)$pdo->query("SELECT COUNT(*) FROM sch_records WHERE k='salStaff' AND d LIKE '%Akash%'")->fetchColumn() === 1, 'seed runs once');
[, $j] = $sa('month', null, $tok['P'], '&ym=' . date('Y-m')); sa_check(!array_filter($j['staff'], fn($x) => $x['until'] !== ''), 'not shown after October');
[$c, $j] = $sa('save', ['ym' => '2026-10', 'set' => ['2026-10-05' => ['t:AP' => 'A', 't:BY' => 'H', $new[0]['id'] => 'A']]], $tok['P']);
sa_check($c === 200 && $j['changed'] === 3, 'October saved for them: ' . json_encode($j));
$o = sch_rec_get('staffAtt', '2026-10-05'); sa_check(in_array('AP', $o['absent'], true) && (int)$o['from']['BY'] === 4 && sch_rec_get('salAtt', '2026-10-05')['a'] === [$new[0]['id']], 'left teacher under old tid, other staff in salAtt');
if (date('Y-m') > '2026-11') {} else { [, $j] = $sa('save', ['ym' => date('Y-m'), 'set' => [date('Y-m') . '-02' => [$new[0]['id'] => 'A']]], $tok['P']); sa_check($j['changed'] === 0, 'no saving after the last day'); }
sa_check(!array_filter(sal_sheet(date('Y-m'))['rows'], fn($r) => $r['staff']['id'] === 't:BY'), 'not in salary after they left');

echo "Staff attendance flow: OK\n";
