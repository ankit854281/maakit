<?php
// ============================================================
// Family app usage — maakit.in/school/famstats_api.php  (owner, 28 Nov 2026:
// "kitne student login kiye hain kaise pata chalega")
//
//   GET ?a=data        Principal / Chairman only
//     totals   students, families (one mobile number = one family), logged in (ever), own PIN set,
//              active today / 7 days / 30 days
//     trend    families + students who opened the app on each of the last 14 days
//     classes  per class: students, logged in (child or the family's parent login), active in 7 days
//     never    students whose family has never logged in — name, class, mobile (for a WhatsApp invite)
//
// "Logged in" = a parent login (p:<mobile>) or the child's own student login (s:<id>) exists in sch_users.
// "Active" = famSeen rows (one per user per day, written by sch_auth / login) or a session seen in the period.
// ============================================================
declare(strict_types=1);
require __DIR__ . '/inc.php';

try {
    $db = sch_db(); $u = sch_auth();
    if (!in_array($u['role'], ['principal', 'chairman'], true)) sch_fail('Only the Principal and the Chairman can see this report.', 403);
    if ((string)($_GET['a'] ?? 'data') !== 'data') sch_fail('Unknown action.', 404);

    $students = [];
    foreach ($db->query("SELECT r, d FROM sch_records WHERE k='student' AND deleted=0") as $row) {
        $s = json_decode((string)$row['d'], true);
        if (is_array($s) && empty($s['inactive']) && ($s['name'] ?? '') !== '') $students[(string)$row['r']] = $s;
    }
    $users = [];
    foreach ($db->query("SELECT uid, role, tid, must_change FROM sch_users WHERE role IN ('parent','student')") as $row) $users[(string)$row['uid']] = $row;
    $seen = [];   // uid => [date => true]
    $q = $db->prepare("SELECT r FROM sch_records WHERE k='famSeen' AND deleted=0 AND dkey >= ?"); $q->execute([date('Y-m-d', time() - 30 * 86400)]);
    foreach ($q as $row) { [$d, $uid] = array_pad(explode('|', (string)$row['r'], 2), 2, ''); if ($uid !== '') $seen[$uid][$d] = true; }
    foreach ($db->query("SELECT uid, MAX(last_seen) ls FROM sch_sessions GROUP BY uid") as $row) if ($row['ls']) $seen[(string)$row['uid']][substr((string)$row['ls'], 0, 10)] = true;

    $today = date('Y-m-d'); $d7 = date('Y-m-d', time() - 6 * 86400); $d30 = date('Y-m-d', time() - 29 * 86400);
    $activeSince = function (string $uid, string $from) use ($seen): bool { foreach ((array)($seen[$uid] ?? []) as $d => $_) if ($d >= $from) return true; return false; };

    $fam = []; $cls = []; $never = []; $T = ['students' => count($students), 'families' => 0, 'noPhone' => 0, 'famLogged' => 0, 'stuLogged' => 0, 'covered' => 0, 'ownPin' => 0,
        'today' => 0, 'week' => 0, 'month' => 0];
    foreach ($students as $id => $s) {
        $ph = sch_phone10((string)($s['phone'] ?? '')); $c = (string)($s['cls'] ?? '');
        $C = &$cls[$c]; $C = $C ?? ['cls' => $c, 'students' => 0, 'covered' => 0, 'student' => 0, 'parent' => 0, 'week' => 0];
        $C['students']++;
        $pu = $ph !== '' ? 'p:' . $ph : ''; $su = 's:' . $id;
        $hasP = $pu !== '' && isset($users[$pu]); $hasS = isset($users[$su]);
        if ($ph === '') $T['noPhone']++; else $fam[$ph] = true;
        if ($hasS) { $C['student']++; $T['stuLogged']++; }
        if ($hasP) $C['parent']++;
        if ($hasP || $hasS) { $C['covered']++; $T['covered']++; }
        if (($hasP && $activeSince($pu, $d7)) || ($hasS && $activeSince($su, $d7))) $C['week']++;
        if (!$hasP && !$hasS) $never[] = ['name' => (string)$s['name'], 'cls' => $c, 'phone' => $ph, 'adm' => (string)($s['adm'] ?? '')];
        unset($C);
    }
    $T['families'] = count($fam);
    foreach ($users as $uid => $x) {
        if ($x['role'] === 'parent' && !isset($fam[(string)$x['tid']])) continue;          // old number no longer in the school
        if ($x['role'] === 'student' && !isset($students[(string)$x['tid']])) continue;
        if ($x['role'] === 'parent') $T['famLogged']++;
        if (!(int)$x['must_change']) $T['ownPin']++;
        if ($activeSince($uid, $today)) $T['today']++;
        if ($activeSince($uid, $d7)) $T['week']++;
        if ($activeSince($uid, $d30)) $T['month']++;
    }
    $trend = [];
    for ($i = 13; $i >= 0; $i--) { $d = date('Y-m-d', time() - $i * 86400); $p = 0; $s2 = 0;
        foreach ($seen as $uid => $days) if (isset($days[$d]) && isset($users[$uid])) { if (str_starts_with($uid, 'p:')) $p++; else $s2++; }
        $trend[] = ['date' => $d, 'parents' => $p, 'students' => $s2]; }
    $order = ['PG', 'LKG', 'UKG', '1st', '2nd', '3rd', '4th', '5th', '6th', '7th', '8th', '9th', '10th'];
    uksort($cls, fn($a, $b) => ((array_search($a, $order, true) === false ? 99 : array_search($a, $order, true)) <=> (array_search($b, $order, true) === false ? 99 : array_search($b, $order, true))) ?: strcmp($a, $b));
    usort($never, fn($a, $b) => [array_search($a['cls'], $order, true), $a['name']] <=> [array_search($b['cls'], $order, true), $b['name']]);
    sch_json(['ok' => true, 'school' => (string)((sch_rec_get('settings', 'main') ?? [])['school'] ?? ''), 'totals' => $T, 'trend' => $trend, 'classes' => array_values($cls), 'never' => $never,
        'apk' => 'https://github.com/ankit854281/maakit/releases/download/family-android-v2/snmsis-family.apk']);
} catch (Throwable $e) {
    error_log('school famstats: ' . $e->getMessage());
    sch_fail('Something went wrong on the server. Please try again.', 500);
}
