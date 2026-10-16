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
    $uid = $role === 'principal' ? 'principal' : ($role === 'chairman' ? 'chairman'
         : ($role === 'parent' ? 'p:' . sch_phone10((string)($in['phone'] ?? '')) : 't:' . preg_replace('/[^A-Za-z0-9_]/', '', (string)($in['tid'] ?? ''))));
    $st = $db->prepare('SELECT * FROM sch_users WHERE uid=?');
    $st->execute([$uid]);
    $u = $st->fetch();
    if ((!$u || !$u['pin_hash']) && $role === 'parent') {
        $db->prepare('INSERT INTO sch_login_fail (ip) VALUES (?)')->execute([$ip]);
        sch_fail('Wrong mobile number or code. Ask the school office for your parent code.', 403);
    }
    if (!$u || !$u['pin_hash']) sch_fail($role === 'teacher' ? 'No PIN set for you yet. Ask the Principal to set it in Settings.' : 'This login is not set up yet.');
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
        sch_fail($f >= 5 ? 'Too many wrong PINs. Please wait 15 minutes.' : 'Wrong PIN.', 403);
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
    sch_json(['ok' => true, 'token' => $tok, 'me' => $me]);
}

// ------------------------------------------------------------
case 'logout': {
    $tok = (string)($_SERVER['HTTP_X_SCHOOL_TOKEN'] ?? '');
    if (preg_match('/^[a-f0-9]{64}$/', $tok)) {
        $u = sch_auth();
        sch_log($u, 'Logout', $u['label'] . ' logged out');
        $db->prepare('DELETE FROM sch_sessions WHERE token_hash=?')->execute([hash('sha256', $tok)]);
    }
    sch_json(['ok' => true]);
}

// ------------------------------------------------------------
case 'load': {
    $u = sch_auth();
    if ($u['role'] === 'parent') sch_fail('Not allowed.', 403);
    $days = max(7, min(400, (int)($_GET['days'] ?? 60)));
    $from = date('Y-m-d', time() - $days * 86400);
    $st = $db->prepare('SELECT k, r, d, ver, deleted FROM sch_records WHERE deleted=0 AND (dkey IS NULL OR dkey >= ?)');
    $st->execute([$from]);
    $recs = sch_rows_to_records($st->fetchAll());
    $log = $db->query('SELECT id, ts, uname, role, k, x FROM sch_log ORDER BY id DESC LIMIT 300')->fetchAll();
    $pins = [];
    foreach ($db->query('SELECT uid FROM sch_users WHERE pin_hash IS NOT NULL') as $row) $pins[$row['uid']] = true;
    sch_json(['ok' => true, 'me' => ['role' => $u['role'], 'tid' => $u['tid'], 'label' => $u['label']],
              'now' => sch_now_ms(), 'from' => $from, 'records' => $recs, 'log' => $log, 'pins' => $pins]);
}

// ------------------------------------------------------------
case 'since': {
    if (sch_auth()['role'] === 'parent') sch_fail('Not allowed.', 403);
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
    if (sch_auth()['role'] === 'parent') sch_fail('Not allowed.', 403);
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
    $putObj = isset($inObj->put) && is_array($inObj->put) ? $inObj->put : [];
    foreach ($put as $i => $p) {
        $k = (string)($p['k'] ?? ''); $r = (string)($p['r'] ?? ''); $d = $p['d'] ?? null;
        $raw = $putObj[$i]->d ?? null;
        if ($r === '' || strlen($r) > 190 || !is_array($d) || $raw === null || strlen(json_encode($raw)) > SCH_MAX_RECORD || !sch_can_write($u, $k, $r, $d, false)) {
            $rejected[] = ['k' => $k, 'r' => $r]; continue;
        }
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
        if ($u['role'] === 'parent') continue;
        if ($u['role'] === 'chairman' && !in_array($k, ['Summary', 'Results'], true)) continue;
        sch_log($u, $k, $x);
    }
    $db->commit();
    sch_json(['ok' => true, 'now' => sch_now_ms(), 'done' => $done, 'rejected' => $rejected]);
}

// ------------------------------------------------------------
case 'setpin': {
    if ($method !== 'POST') sch_fail('POST only', 405);
    $u = sch_auth();
    if ($u['role'] === 'parent') sch_fail('Parent codes are given by the school office.', 403);
    $pin = (string)($in['pin'] ?? '');
    if (!sch_pin_ok($pin)) sch_fail('PIN must be 4 to 8 digits.');
    $target = (string)($in['uid'] ?? '');
    $selfUid = $u['role'] === 'teacher' ? 't:' . $u['tid'] : $u['role'];
    if ($target === '' || $target === $selfUid) {
        $st = $db->prepare('SELECT pin_hash FROM sch_users WHERE uid=?');
        $st->execute([$selfUid]);
        if (!password_verify((string)($in['old'] ?? ''), (string)$st->fetchColumn())) sch_fail('Old PIN is wrong.', 403);
        $target = $selfUid;
    } elseif ($u['role'] !== 'principal') {
        sch_fail('Only the Principal can set other PINs.', 403);
    }
    if ($target === 'chairman') { $role = 'chairman'; $tid = null; }
    elseif ($target === 'principal') { $role = 'principal'; $tid = 'P'; }
    elseif (preg_match('/^t:([A-Za-z0-9_]{1,20})$/', $target, $m) && sch_rec_get('teacher', $m[1])) { $role = 'teacher'; $tid = $m[1]; }
    else sch_fail('Unknown user.');
    $db->prepare('INSERT INTO sch_users (uid, role, tid, pin_hash) VALUES (?,?,?,?)
                  ON DUPLICATE KEY UPDATE pin_hash=VALUES(pin_hash), fails=0, strikes=0, locked_until=NULL, updated_at=NOW()')
       ->execute([$target, $role, $tid, password_hash($pin, PASSWORD_DEFAULT)]);
    if ($target !== $selfUid) $db->prepare('DELETE FROM sch_sessions WHERE uid=?')->execute([$target]);   // purane phone se bahar
    $who = $role === 'teacher' ? sch_teacher_name($tid) : ucfirst($role);
    sch_log($u, 'PIN', 'PIN ' . ($target === $selfUid ? 'changed by ' . $u['label'] : 'set for ' . $who));
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
                  ON DUPLICATE KEY UPDATE pin_hash=VALUES(pin_hash), fails=0, strikes=0, locked_until=NULL, updated_at=NOW()")
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
    if ($u['role'] !== 'parent') sch_fail('Not allowed.', 403);
    $kids = sch_children((string)$u['tid']);
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
    $kidsOut = array_map(fn($k) => ['id' => $k['id'], 'name' => $k['name'] ?? '', 'cls' => $k['cls'] ?? '', 'adm' => $k['adm'] ?? '', 'ct' => $ct[$k['cls'] ?? ''] ?? ''], $kids);
    $set = sch_rec_get('settings', 'main') ?? [];
    sch_json(['ok' => true, 'me' => ['role' => 'parent', 'tid' => $u['tid'], 'label' => $u['label']],
        'school' => (string)($set['school'] ?? ''), 'session' => (string)($set['session'] ?? ''),
        'kids' => $kidsOut, 'att' => $att, 'hw' => $hw, 'marks' => $marks, 'behav' => $behav, 'speak' => $speak,
        'notices' => $notices, 'cal' => array_values($get('cal')), 'exams' => sch_rec_get('exams', 'main') ?? []]);
}

default:
    sch_fail('Unknown action.', 404);
}
} catch (Throwable $e) {
    if ($db->inTransaction()) $db->rollBack();
    error_log('school api: ' . $e->getMessage());
    sch_fail('Something went wrong on the server. Please try again.', 500);
}
