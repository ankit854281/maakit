<?php
// ============================================================
// School ERP — API (sirf JSON)
//
//   GET  ?a=status            login se pehle: setup hua ya nahi, teachers ke naam
//   POST ?a=setup             pehli baar: UPDATE_KEY + Principal ka PIN
//   POST ?a=login             role + (teacher) + PIN  ->  token
//   POST ?a=logout
//   GET  ?a=load&days=60      saara data (purani tareekhon ka sirf pichhle 60 din)
//   GET  ?a=since&v=&l=       jo badla hai (har 45 second)
//   GET  ?a=day&date=         kisi purani tareekh ka data
//   POST ?a=sync              badlaav bhejna (put / del / log)
//   POST ?a=setpin            PIN lagana / badalna
//   GET  ?a=logcsv            poora Activity Log (CSV)
//
// Token header "X-School-Token" me aata hai, cookie me nahi.
// ============================================================

declare(strict_types=1);
require __DIR__ . '/inc.php';
require_once __DIR__ . '/homework_lib.php';

$a = (string)($_GET['a'] ?? '');
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

try {
    $db = sch_db();
} catch (Throwable $e) {
    sch_fail('Server is not reachable right now. Please try again in a few minutes.', 503);
}

$in = [];
if ($method === 'POST') {
    $raw = file_get_contents('php://input', false, null, 0, 3000000) ?: '';
    $in = json_decode($raw, true);
    $inObj = json_decode($raw);              // khaali {} ko {} hi rakhne ke liye (array me badal kar [] na ho)
    if (!is_array($in)) sch_fail('Bad request.');
}

try {
switch ($a) {

// ------------------------------------------------------------
case 'status': {
    $hasP = (int)$db->query("SELECT COUNT(*) FROM sch_users WHERE role='principal' AND pin_hash IS NOT NULL")->fetchColumn();
    $pins = [];
    foreach ($db->query("SELECT tid FROM sch_users WHERE role='teacher' AND pin_hash IS NOT NULL") as $row) $pins[$row['tid']] = true;
    $teachers = [];
    foreach ($db->query("SELECT r, d FROM sch_records WHERE k='teacher' AND deleted=0") as $row) {
        $t = json_decode((string)$row['d'], true);
        $teachers[] = ['tid' => $row['r'], 'name' => (string)($t['name'] ?? $row['r']), 'pin' => isset($pins[$row['r']])];
    }
    usort($teachers, fn($x, $y) => strcmp($x['name'], $y['name']));
    $set = sch_rec_get('settings', 'main');
    sch_json(['ok' => true, 'setup' => $hasP === 0, 'teachers' => $teachers,
              'office' => ['accountant' => trim((string)($set['accountantName'] ?? '')) ?: 'Akash Tiwari', 'reception' => trim((string)($set['receptionName'] ?? '')) ?: 'Shiva Rai'],
              'school' => (string)($set['school'] ?? 'SN Mishra Singapore International School')]);
}

// ------------------------------------------------------------
case 'setup': {
    if ($method !== 'POST') sch_fail('POST only', 405);
    // Pehli baar setup, ya Principal PIN bhool gaye to wahi update-key se naya PIN
    $st = $db->prepare('SELECT COUNT(*) FROM sch_login_fail WHERE ip=? AND at > (NOW() - INTERVAL 15 MINUTE)');
    $st->execute([sch_ip()]);
    if ((int)$st->fetchColumn() >= 20) sch_fail('Too many wrong tries. Please wait 15 minutes.', 429);
    $hasP = (int)$db->query("SELECT COUNT(*) FROM sch_users WHERE role='principal' AND pin_hash IS NOT NULL")->fetchColumn();
    $key = defined('UPDATE_KEY') ? (string)UPDATE_KEY : '';
    if ($key === '' || !hash_equals($key, (string)($in['key'] ?? ''))) {
        $db->prepare('INSERT INTO sch_login_fail (ip) VALUES (?)')->execute([sch_ip()]);
        sch_fail('That is not the website update key.', 403);
    }
    $pin = (string)($in['pin'] ?? '');
    if (!sch_pin_ok($pin)) sch_fail('PIN must be 4 to 8 digits.');
    $db->prepare("INSERT INTO sch_users (uid, role, tid, pin_hash) VALUES ('principal','principal','P',?)
                  ON DUPLICATE KEY UPDATE pin_hash=VALUES(pin_hash), fails=0, strikes=0, locked_until=NULL")
       ->execute([password_hash($pin, PASSWORD_DEFAULT)]);
    if ($hasP) $db->exec("DELETE FROM sch_sessions WHERE uid='principal'");
    sch_log(['label' => 'Principal', 'role' => 'principal'], 'Setup', $hasP ? 'Principal PIN reset with the website update key' : 'School ERP set up; Principal PIN created');
    sch_json(['ok' => true]);
}

// ------------------------------------------------------------
case 'login': {
    if ($method !== 'POST') sch_fail('POST only', 405);
    $ip = sch_ip();
    $st = $db->prepare('SELECT COUNT(*) FROM sch_login_fail WHERE ip=? AND at > (NOW() - INTERVAL 15 MINUTE)');
    $st->execute([$ip]);
    if ((int)$st->fetchColumn() >= 20) sch_fail('Too many wrong tries. Please wait 15 minutes.', 429);

    $role = (string)($in['role'] ?? '');
    $pin = (string)($in['pin'] ?? '');
    $tid = null; $valid = false;
    switch ($role) {
        case 'principal': $uid = 'principal'; break;
        case 'chairman':  $uid = 'chairman'; $valid = true; break;
        case 'accountant':
        case 'reception': $uid = $role; $valid = true; break;   // school office (fees) — office_api.php
        case 'teacher':
            $tid = preg_replace('/[^A-Za-z0-9_]/', '', (string)($in['tid'] ?? ''));
            if ($tid === 'P') sch_fail('Please use the Principal login.');
            $uid = 't:' . $tid; $valid = $tid !== '' && sch_rec_get('teacher', $tid) !== null; break;
        case 'parent':
            $tid = sch_phone10((string)($in['phone'] ?? ''));
            $uid = 'p:' . $tid; $valid = $tid !== '' && (bool)sch_children($tid); break;
        case 'student':
            $found = sch_students_by_adm(trim((string)($in['adm'] ?? '')));
            if (count($found) > 1) sch_fail('More than one student has this admission number. Please ask the school office.');
            $tid = $found ? (string)$found[0]['id'] : '';
            $uid = 's:' . $tid; $valid = $tid !== ''; break;
        default: sch_fail('Choose who is logging in.');
    }
    $st = $db->prepare('SELECT * FROM sch_users WHERE uid=?');
    $st->execute([$uid]);
    $u = $st->fetch();
    if (!$u || !$u['pin_hash']) {
        // Pehli baar: PIN 1234 — sirf asli teacher / parent / student / chairman ke liye
        if ($role !== 'principal' && $valid && hash_equals(SCH_DEFAULT_PIN, $pin)) {
            $db->prepare('INSERT INTO sch_users (uid, role, tid, pin_hash, must_change) VALUES (?,?,?,?,1)
                          ON DUPLICATE KEY UPDATE pin_hash=VALUES(pin_hash), must_change=1, fails=0, strikes=0, locked_until=NULL, updated_at=NOW()')
               ->execute([$uid, $role, $tid, password_hash(SCH_DEFAULT_PIN, PASSWORD_DEFAULT)]);
            $st->execute([$uid]);
            $u = $st->fetch();
        } else {
            if ($role === 'principal') sch_fail('This login is not set up yet.');
            $db->prepare('INSERT INTO sch_login_fail (ip) VALUES (?)')->execute([$ip]);
            sch_fail($role === 'parent' ? 'Wrong mobile number or PIN. The first PIN is 1234.'
                   : ($role === 'student' ? 'Wrong admission number or PIN. The first PIN is 1234.' : 'Wrong PIN.'), 403);
        }
    }
    if ($u['locked_until'] && strtotime((string)$u['locked_until']) > time()) sch_fail('Too many wrong PINs. Please wait 15 minutes.', 429);
    if ((int)$u['strikes'] >= SCH_MAX_STRIKES) sch_fail($role === 'principal'
        ? 'This login is blocked after too many wrong PINs. Use "Forgot PIN" with the website update key.'
        : 'This login is blocked after too many wrong PINs. Ask the Principal to set a new PIN.', 423);

    if (!password_verify((string)($in['pin'] ?? ''), (string)$u['pin_hash'])) {
        $db->prepare('INSERT INTO sch_login_fail (ip) VALUES (?)')->execute([$ip]);
        $f = (int)$u['fails'] + 1;
        $strikes = (int)$u['strikes'] + 1;
        $db->prepare('UPDATE sch_users SET fails=?, strikes=?, locked_until=? WHERE uid=?')
           ->execute([$f >= 5 ? 0 : $f, $strikes, $f >= 5 ? date('Y-m-d H:i:s', time() + 900) : null, $uid]);
        if ($strikes >= SCH_MAX_STRIKES) sch_fail('Too many wrong PINs. This login is now blocked until a new PIN is set.', 423);
        sch_fail($f >= 5 ? 'Too many wrong PINs. Please wait 15 minutes.' : ($role === 'parent' ? 'Wrong mobile number or PIN.' : ($role === 'student' ? 'Wrong admission number or PIN.' : 'Wrong PIN.')), 403);
    }
    $db->prepare('UPDATE sch_users SET fails=0, strikes=0, locked_until=NULL WHERE uid=?')->execute([$uid]);
    $tok = bin2hex(random_bytes(32));
    $db->prepare('INSERT INTO sch_sessions (token_hash, uid, expires_at, last_seen) VALUES (?,?,?,NOW())')
       ->execute([hash('sha256', $tok), $uid, date('Y-m-d H:i:s', time() + SCH_SESSION_DAYS * 86400)]);
    $db->exec('DELETE FROM sch_sessions WHERE expires_at < NOW()');
    $db->exec('DELETE FROM sch_login_fail WHERE at < (NOW() - INTERVAL 1 DAY)');
    $me = ['role' => $u['role'], 'tid' => $u['tid']];
    $me['label'] = sch_user_label($me);
    sch_log($me, 'Login', $me['label'] . ' logged in');
    sch_json(['ok' => true, 'token' => $tok, 'me' => $me, 'mustChange' => (int)$u['must_change'] === 1]);
}

// ------------------------------------------------------------
case 'logout': {
    $tok = (string)($_SERVER['HTTP_X_SCHOOL_TOKEN'] ?? '');
    if (preg_match('/^[a-f0-9]{64}$/', $tok)) {
        $u = sch_auth(true);
        sch_log($u, 'Logout', $u['label'] . ' logged out');
        $db->prepare('DELETE FROM sch_sessions WHERE token_hash=?')->execute([hash('sha256', $tok)]);
    }
    sch_json(['ok' => true]);
}

// ------------------------------------------------------------
case 'load': {
    $u = sch_auth();
    if (in_array($u['role'], SCH_NO_LOAD, true)) sch_fail('Not allowed.', 403);
    $days = max(7, min(400, (int)($_GET['days'] ?? 60)));
    $from = date('Y-m-d', time() - $days * 86400);
    $st = $db->prepare('SELECT k, r, d, ver, deleted FROM sch_records WHERE deleted=0 AND (dkey IS NULL OR dkey >= ?)');
    $st->execute([$from]);
    $recs = sch_rows_to_records($st->fetchAll());
    $log = $db->query('SELECT id, ts, uname, role, k, x FROM sch_log ORDER BY id DESC LIMIT 300')->fetchAll();
    $pins = [];
    foreach ($db->query('SELECT uid FROM sch_users WHERE pin_hash IS NOT NULL AND must_change = 0') as $row) $pins[$row['uid']] = true;
    sch_json(['ok' => true, 'me' => ['role' => $u['role'], 'tid' => $u['tid'], 'label' => $u['label']],
              'now' => sch_now_ms(), 'from' => $from, 'records' => $recs, 'log' => $log, 'pins' => $pins]);
}

// ------------------------------------------------------------
case 'since': {
    if (in_array(sch_auth()['role'], SCH_NO_LOAD, true)) sch_fail('Not allowed.', 403);
    $v = (int)($_GET['v'] ?? 0) - 5000;          // 5 second peeche se — ek saath hue badlaav chhootein nahi
    $l = (int)($_GET['l'] ?? 0);
    $st = $db->prepare('SELECT k, r, d, ver, deleted FROM sch_records WHERE ver > ? ORDER BY ver LIMIT 2000');
    $st->execute([$v]);
    $recs = sch_rows_to_records($st->fetchAll());
    $st = $db->prepare('SELECT id, ts, uname, role, k, x FROM sch_log WHERE id > ? ORDER BY id DESC LIMIT 300');
    $st->execute([$l]);
    sch_json(['ok' => true, 'now' => sch_now_ms(), 'records' => $recs, 'log' => $st->fetchAll()]);
}

// ------------------------------------------------------------
case 'day': {
    if (in_array(sch_auth()['role'], SCH_NO_LOAD, true)) sch_fail('Not allowed.', 403);
    $date = (string)($_GET['date'] ?? '');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) sch_fail('Bad date.');
    $st = $db->prepare('SELECT k, r, d, ver, deleted FROM sch_records WHERE deleted=0 AND dkey = ?');
    $st->execute([$date]);
    sch_json(['ok' => true, 'records' => sch_rows_to_records($st->fetchAll())]);
}

// ------------------------------------------------------------
case 'sync': {
    if ($method !== 'POST') sch_fail('POST only', 405);
    $u = sch_auth();
    $uid = $u['role'] === 'teacher' ? 't:' . $u['tid'] : $u['role'];
    $put = is_array($in['put'] ?? null) ? $in['put'] : [];
    $del = is_array($in['del'] ?? null) ? $in['del'] : [];
    $logs = is_array($in['log'] ?? null) ? array_slice($in['log'], 0, 100) : [];
    if (count($put) > 3000 || count($del) > 500) sch_fail('Too many changes at once.');

    $done = []; $rejected = [];
    $db->beginTransaction();
    $db->query("SELECT uid FROM sch_users WHERE uid='principal' FOR UPDATE")->fetch();
    $putObj = isset($inObj->put) && is_array($inObj->put) ? $inObj->put : [];
    foreach ($put as $i => $p) {
        $k = (string)($p['k'] ?? ''); $r = (string)($p['r'] ?? ''); $d = $p['d'] ?? null;
        $raw = $putObj[$i]->d ?? null;
        if ($r === '' || strlen($r) > 190 || !is_array($d) || $raw === null || strlen(json_encode($raw)) > SCH_MAX_RECORD || !sch_can_write($u, $k, $r, $d, false)) {
            $rejected[] = ['k' => $k, 'r' => $r]; continue;
        }
        if ($k === 'diary') { try { tool_hw_guard($k,$r,$d,(string)((sch_rec_get('settings','main')??[])['session']??'')); } catch (InvalidArgumentException $e) { $rejected[]=['k'=>$k,'r'=>$r,'error'=>$e->getMessage()]; continue; } }
        sch_rec_put($k, $r, $raw, $uid);
        $done[] = ['k' => $k, 'r' => $r];
    }
    foreach ($del as $p) {
        $k = (string)($p['k'] ?? ''); $r = (string)($p['r'] ?? '');
        if (!sch_can_write($u, $k, $r, null, true)) { $rejected[] = ['k' => $k, 'r' => $r]; continue; }
        sch_rec_del($k, $r, $uid);
        $done[] = ['k' => $k, 'r' => $r];
    }
    foreach ($logs as $g) {
        $k = trim((string)($g['k'] ?? '')); $x = trim((string)($g['x'] ?? ''));
        if ($k === '' || $x === '') continue;
        if (in_array($u['role'], SCH_NO_LOAD, true)) continue;
        if ($u['role'] === 'chairman' && !in_array($k, ['Summary', 'Results'], true)) continue;
        sch_log($u, $k, $x);
    }
    $db->commit();
    sch_json(['ok' => true, 'now' => sch_now_ms(), 'done' => $done, 'rejected' => $rejected]);
}

// ------------------------------------------------------------
case 'importstudents': {
    if ($method !== 'POST') sch_fail('POST only', 405);
    $u=sch_auth();
    if ($u['role'] !== 'principal') sch_fail('Only the Principal can import student records.',403);
    require_once __DIR__ . '/student_import.php';
    try { $rows=sch_student_import_rows($in,(string)((sch_rec_get('settings','main')['session']??''))); }
    catch (InvalidArgumentException $e) { sch_fail($e->getMessage()); }
    $apply=($in['apply']??false)===true;
    $db->beginTransaction();
    // Serialise imports using the already-existing Principal user row, including an empty student table.
    $db->query("SELECT uid FROM sch_users WHERE uid='principal' FOR UPDATE")->fetch();
    $old=$db->query("SELECT r,d,deleted FROM sch_records WHERE k='student' FOR UPDATE")->fetchAll();
    $plan=sch_student_import_plan($rows,$old);
    if ($apply && $plan['conflicts']) { $db->rollBack(); sch_fail('Import stopped: existing student IDs or admission numbers need review. No records changed.',409); }
    if ($apply) {
        $insert=$db->prepare("INSERT INTO sch_records (k,r,d,deleted,dkey,ver,by_uid,updated_at) VALUES ('student',?,?,0,NULL,?,'principal',NOW())");
        foreach($plan['new'] as $s) $insert->execute([$s['id'],json_encode($s,JSON_UNESCAPED_UNICODE),sch_now_ms()]);
        sch_log($u,'Student Import',count($plan['new']).' ERP students imported for '.$in['session'].'; '.count($plan['unchanged']).' already present; teachers unchanged');
    }
    $db->commit();
    sch_json(['ok'=>true,'applied'=>$apply,'session'=>$in['session'],'total'=>count($rows),'added'=>$apply?count($plan['new']):0,'toAdd'=>count($plan['new']),'unchanged'=>count($plan['unchanged']),'conflicts'=>$plan['conflicts'],'duplicateAdmissions'=>$plan['duplicateAdmissions']]);
}

// ------------------------------------------------------------
case 'setpin': {
    if ($method !== 'POST') sch_fail('POST only', 405);
    $u = sch_auth(true);
    $pin = (string)($in['pin'] ?? '');
    if (!sch_pin_ok($pin)) sch_fail('PIN must be 4 to 8 digits.');
    $target = (string)($in['uid'] ?? '');
    $selfUid = sch_uid_of($u);
    if ($target === '' || $target === $selfUid) {
        if ($pin === SCH_DEFAULT_PIN) sch_fail('Choose a PIN other than 1234.');
        $st = $db->prepare('SELECT pin_hash FROM sch_users WHERE uid=?');
        $st->execute([$selfUid]);
        if (!password_verify((string)($in['old'] ?? ''), (string)$st->fetchColumn())) sch_fail('Old PIN is wrong.', 403);
        $target = $selfUid;
    } elseif ($u['role'] !== 'principal' || (int)$u['must_change'] === 1) {
        sch_fail('Only the Principal can set other PINs.', 403);
    }
    if ($target === 'chairman') { $role = 'chairman'; $tid = null; }
    elseif ($target === 'accountant' || $target === 'reception') { $role = $target; $tid = null; }
    elseif ($target === 'principal') { $role = 'principal'; $tid = 'P'; }
    elseif (preg_match('/^t:([A-Za-z0-9_]{1,20})$/', $target, $m) && $m[1] !== 'P' && sch_rec_get('teacher', $m[1])) { $role = 'teacher'; $tid = $m[1]; }
    elseif (preg_match('/^p:(\d{10})$/', $target, $m) && sch_children($m[1])) { $role = 'parent'; $tid = $m[1]; }
    elseif (preg_match('/^s:(.{1,180})$/', $target, $m) && sch_rec_get('student', $m[1])) { $role = 'student'; $tid = $m[1]; }
    else sch_fail('Unknown user.');
    if ($role === 'principal' && $target !== $selfUid) sch_fail('Only the Principal can set other PINs.', 403);
    // Principal kisi ka PIN 1234 kar de = "reset": us aadmi ko agli baar apna PIN chunna padega
    $must = ($target !== $selfUid && $pin === SCH_DEFAULT_PIN) ? 1 : 0;
    $db->prepare('INSERT INTO sch_users (uid, role, tid, pin_hash, must_change) VALUES (?,?,?,?,?)
                  ON DUPLICATE KEY UPDATE pin_hash=VALUES(pin_hash), must_change=VALUES(must_change), fails=0, strikes=0, locked_until=NULL, updated_at=NOW()')
       ->execute([$target, $role, $tid, password_hash($pin, PASSWORD_DEFAULT), $must]);
    if ($target !== $selfUid) $db->prepare('DELETE FROM sch_sessions WHERE uid=?')->execute([$target]);   // purane phone se bahar
    $who = match ($role) { 'teacher' => sch_teacher_name($tid), 'parent' => 'parent ' . substr((string)$tid, 0, 2) . 'xxxxxx' . substr((string)$tid, -2),
                           'student' => sch_user_label(['role' => 'student', 'tid' => $tid]), 'accountant', 'reception' => sch_user_label(['role' => $role, 'tid' => null]), default => ucfirst($role) };
    sch_log($u, 'PIN', 'PIN ' . ($target === $selfUid ? 'chosen by ' . $u['label'] : ($must ? 'reset to 1234 for ' : 'set for ') . $who));
    sch_json(['ok' => true]);
}

// ------------------------------------------------------------
case 'logcsv': {
    $u = sch_auth();
    if (!in_array($u['role'], ['principal', 'chairman'], true)) sch_fail('Not allowed.', 403);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="school-activity-log-' . date('Y-m-d') . '.csv"');
    header('Cache-Control: no-store');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Date & Time', 'User', 'Role', 'Type', 'Activity']);
    $st = $db->query('SELECT ts, uname, role, k, x FROM sch_log ORDER BY id DESC');
    while ($row = $st->fetch()) {
        fputcsv($out, [date('d-m-Y H:i', (int)floor($row['ts'] / 1000)), $row['uname'], $row['role'], $row['k'], $row['x']]);
    }
    exit;
}

// ------------------------------------------------------------
// Parent code: Principal ek mobile number ke liye 4 ank ka code banata hai
case 'parentcode': {
    if ($method !== 'POST') sch_fail('POST only', 405);
    $u = sch_auth();
    if ($u['role'] !== 'principal') sch_fail('Only the Principal can make parent codes.', 403);
    $phone = sch_phone10((string)($in['phone'] ?? ''));
    if ($phone === '' || !sch_children($phone)) sch_fail('No student has this mobile number.');
    $code = (string)random_int(1000, 9999);
    $db->prepare("INSERT INTO sch_users (uid, role, tid, pin_hash) VALUES (?, 'parent', ?, ?)
                  ON DUPLICATE KEY UPDATE pin_hash=VALUES(pin_hash), must_change=0, fails=0, strikes=0, locked_until=NULL, updated_at=NOW()")
       ->execute(['p:' . $phone, $phone, password_hash($code, PASSWORD_DEFAULT)]);
    $db->prepare('DELETE FROM sch_sessions WHERE uid=?')->execute(['p:' . $phone]);
    sch_log($u, 'Parent Code', 'Parent code made for ' . substr($phone, 0, 2) . 'xxxxxx' . substr($phone, -2));
    sch_json(['ok' => true, 'code' => $code]);
}

// ------------------------------------------------------------
// Parent ka data: sirf apne bachchon ka — attendance, homework, marks,
// behaviour, speaking, notices, calendar. Doosre bachchon ka kuchh nahi.
case 'pdata': {
    $u = sch_auth();
    if (!in_array($u['role'], ['parent', 'student'], true)) sch_fail('Not allowed.', 403);
    if ($u['role'] === 'student') { $one = sch_rec_get('student', (string)$u['tid']); $kids = $one ? [$one] : []; }
    else $kids = sch_children((string)$u['tid']);
    $ids = array_column($kids, 'id');
    $classes = array_values(array_unique(array_column($kids, 'cls')));
    $from = date('Y-m-d', time() - 75 * 86400);
    $get = function (string $k, ?string $since = null) use ($db): array {
        $q = 'SELECT r, d FROM sch_records WHERE k=? AND deleted=0' . ($since ? ' AND dkey >= ?' : '');
        $st = $db->prepare($q); $st->execute($since ? [$k, $since] : [$k]);
        $out = [];
        foreach ($st as $row) { $d = json_decode((string)$row['d'], true); if (is_array($d)) $out[$row['r']] = $d; }
        return $out;
    };
    $att = [];
    foreach ($get('studAtt', $from) as $r => $d) {
        [$date, $cls] = array_pad(explode('|', $r), 2, '');
        foreach ($kids as $kid) if ($kid['cls'] === $cls) $att[] = ['sid' => $kid['id'], 'date' => $date, 'present' => !in_array($kid['id'], (array)($d['absent'] ?? []), true)];
    }
    $hw = [];
    $hwFrom = date('Y-m-d', time() - 10 * 86400);
    foreach ($get('diary', $hwFrom) as $r => $d) {
        $cls = (string)($d['cls'] ?? '');
        if (in_array($cls, $classes, true)) $hw[] = ['date' => explode('|', $r)[0], 'cls' => $cls, 'subj' => (string)($d['subj'] ?? ''), 'topic' => (string)($d['topic'] ?? ''), 'hw' => (string)($d['hw'] ?? '')];
    }
    $marks = [];
    foreach ($get('marks') as $r => $d) {
        $p = explode('|', $r);
        if (count($p) !== 3 || !in_array($p[1], $classes, true)) continue;
        foreach ($kids as $kid) if ($kid['cls'] === $p[1] && array_key_exists($kid['id'], (array)($d['m'] ?? [])))
            $marks[] = ['sid' => $kid['id'], 'exam' => $p[0], 'subj' => $p[2], 'max' => $d['max'] ?? null, 'mark' => $d['m'][$kid['id']]];
    }
    $behav = [];
    foreach ($get('behav', $from) as $d) if (in_array($d['sid'] ?? '', $ids, true))
        $behav[] = ['sid' => $d['sid'], 'date' => $d['date'] ?? '', 'pts' => $d['pts'] ?? 0, 'reason' => $d['reason'] ?? '', 'note' => $d['note'] ?? ''];
    $speak = [];
    foreach ($get('speak') as $d) foreach ((array)($d['students'] ?? []) as $sid) if (in_array($sid, $ids, true))
        $speak[] = ['sid' => $sid, 'date' => $d['date'] ?? '', 'type' => $d['type'] ?? '', 'topic' => $d['topic'] ?? ''];
    $notices = array_values(array_filter($get('notice'), fn($n) => ($n['to'] ?? '') !== 'Teachers'));
    $teachers = $get('teacher');
    $ct = [];
    foreach ((array)sch_rec_get('classes', 'main') as $c) if (in_array($c['c'] ?? '', $classes, true)) $ct[$c['c']] = (string)($teachers[$c['ct'] ?? '']['name'] ?? '');
    $kidsOut = array_map(fn($k) => ['id' => $k['id'], 'name' => $k['name'] ?? '', 'cls' => $k['cls'] ?? '', 'adm' => $k['adm'] ?? '', 'ct' => $ct[$k['cls'] ?? ''] ?? '',
                                     'km' => isset($k['km']) && is_numeric($k['km']) ? (float)$k['km'] : null], $kids);
    $set = sch_rec_get('settings', 'main') ?? [];
    sch_json(['ok' => true, 'me' => ['role' => $u['role'], 'tid' => $u['tid'], 'label' => $u['label']],
        'school' => (string)($set['school'] ?? ''), 'session' => (string)($set['session'] ?? ''),
        'kids' => $kidsOut, 'att' => $att, 'hw' => $hw, 'marks' => $marks, 'behav' => $behav, 'speak' => $speak,
        'remarks' => array_values(array_filter($get('toolRemark'), fn($r) => in_array($r['sid']??'', $ids, true) && ($r['session']??'')===(string)($set['session']??''))),
        'notices' => $notices, 'cal' => array_values($get('cal')), 'fees' => sch_rec_get('fees', 'main'),
        // 'sent' = kis bachche ke ghar result gaya — doosre parents ko nahi dikhna chahiye
        'exams' => array_map(fn($e) => is_array($e) ? array_diff_key($e, ['sent' => 1]) : $e, (array)(sch_rec_get('exams', 'main') ?? []))]);
}

default:
    sch_fail('Unknown action.', 404);
}
} catch (Throwable $e) {
    if ($db->inTransaction()) $db->rollBack();
    error_log('school api: ' . $e->getMessage());
    sch_fail('Something went wrong on the server. Please try again.', 500);
}
