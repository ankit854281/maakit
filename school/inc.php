<?php
// ============================================================
// School ERP — saajha helper (database, login, niyam)
//
// Ye Maakit ki kisi file ko nahi chhoota. Sirf root ki
// config.php se database ki chaabi padhta hai (wahi jo baaki
// site use karti hai) aur sirf "sch_" tables par kaam karta hai.
// ============================================================

declare(strict_types=1);
date_default_timezone_set('Asia/Kolkata');

const SCH_KINDS = ['settings','classes','teacher','alloc','grid','exams','student','staffAtt','studAtt',
                   'diary','signed','syl','marks','leave','notice','behav','speak','lplan','cal','fees'];
const SCH_DATE_KINDS = ['staffAtt','studAtt','diary','signed','behav'];
const SCH_DELETABLE  = ['student','notice','teacher','behav','speak','cal'];
const SCH_SESSION_DAYS = 30;
const SCH_NO_LOAD = ['parent', 'student', 'accountant', 'reception'];   // inhe poore school ka data nahi milta
const SCH_MAX_RECORD = 400000;          // ek record max ~400 KB (timetable grid ke liye kaafi)

function sch_db(): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;
    $cfg = __DIR__ . '/../config.php';
    if (!is_file($cfg)) throw new RuntimeException('config.php missing');
    require_once $cfg;
    $pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    return $pdo;
}

function sch_json($data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function sch_fail(string $msg, int $code = 400): void { sch_json(['ok' => false, 'error' => $msg], $code); }

function sch_ip(): string { return substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45); }

function sch_now_ms(): int { return (int)floor(microtime(true) * 1000); }

// ---------- records ----------
function sch_rec_get(string $k, string $r): ?array {
    $st = sch_db()->prepare('SELECT d FROM sch_records WHERE k=? AND r=? AND deleted=0');
    $st->execute([$k, $r]);
    $d = $st->fetchColumn();
    if ($d === false || $d === null) return null;
    $v = json_decode((string)$d, true);
    return is_array($v) ? $v : null;
}

function sch_dkey(string $k, string $r): ?string {
    if (!in_array($k, SCH_DATE_KINDS, true)) return null;
    $d = explode('|', $r)[0];
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) ? $d : null;
}

function sch_rec_put(string $k, string $r, $data, string $uid): int {
    $ver = sch_now_ms();
    $st = sch_db()->prepare('INSERT INTO sch_records (k,r,d,deleted,dkey,ver,by_uid,updated_at) VALUES (?,?,?,0,?,?,?,NOW())
        ON DUPLICATE KEY UPDATE d=VALUES(d), deleted=0, dkey=VALUES(dkey), ver=VALUES(ver), by_uid=VALUES(by_uid), updated_at=NOW()');
    $st->execute([$k, $r, json_encode($data, JSON_UNESCAPED_UNICODE), sch_dkey($k, $r), $ver, $uid]);
    return $ver;
}

function sch_rec_del(string $k, string $r, string $uid): int {
    $ver = sch_now_ms();
    $st = sch_db()->prepare('UPDATE sch_records SET d=NULL, deleted=1, ver=?, by_uid=?, updated_at=NOW() WHERE k=? AND r=?');
    $st->execute([$ver, $uid, $k, $r]);
    return $ver;
}

function sch_rows_to_records(array $rows): array {
    $out = [];
    foreach ($rows as $row) {
        // Learning content has its own subject/child-scoped API; keys never use legacy sync.
        if (str_starts_with($row['k'], 'studio') || str_starts_with($row['k'], 'tool') || str_starts_with($row['k'], 'faculty') || str_starts_with($row['k'], 'quiz') || str_starts_with($row['k'], 'game') || str_starts_with($row['k'], 'acct') || str_starts_with($row['k'], 'fin')) continue;
        if (in_array($row['k'], ['learnKey','learnMission','learnTry','learnReview','skillCheck'], true)) continue;
        $out[] = ['k' => $row['k'], 'r' => $row['r'], 'v' => (int)$row['ver'], 'x' => (int)$row['deleted'],
                  'd' => $row['d'] === null ? null : json_decode((string)$row['d'])];   // object: {} stays {}
    }
    return $out;
}

// ---------- people ----------
function sch_teacher_name(?string $tid): string {
    if (!$tid) return '';
    $t = sch_rec_get('teacher', $tid);
    return $t && isset($t['name']) ? (string)$t['name'] : $tid;
}

function sch_phone10(string $p): string { $d = preg_replace('/\D/', '', $p); return strlen($d) >= 10 ? substr($d, -10) : ''; }

// Ek mobile number se jude saare bachche (parent ke bachche)
function sch_children(string $phone10): array {
    $out = [];
    if ($phone10 === '') return $out;
    foreach (sch_db()->query("SELECT d FROM sch_records WHERE k='student' AND deleted=0") as $row) {
        $s = json_decode((string)$row['d'], true);
        if (is_array($s) && sch_phone10((string)($s['phone'] ?? '')) === $phone10) $out[] = $s;
    }
    return $out;
}

// Admission number dohraaye bhi ja sakte hain (purane ERP se import), isliye list
function sch_students_by_adm(string $adm): array {
    $out = [];
    if ($adm === '') return $out;
    foreach (sch_db()->query("SELECT d FROM sch_records WHERE k='student' AND deleted=0") as $row) {
        $s = json_decode((string)$row['d'], true);
        if (is_array($s) && strcasecmp(trim((string)($s['adm'] ?? '')), $adm) === 0) $out[] = $s;
    }
    return $out;
}

function sch_uid_of(array $u): string {
    return match ($u['role']) { 'teacher' => 't:' . $u['tid'], 'parent' => 'p:' . $u['tid'], 'student' => 's:' . $u['tid'], default => (string)$u['role'] };
}

function sch_user_label(array $u): string {
    if ($u['role'] === 'chairman') return 'Chairman';
    if ($u['role'] === 'accountant' || $u['role'] === 'reception') {
        $s = sch_rec_get('settings', 'main') ?? [];
        $n = trim((string)($s[$u['role'] . 'Name'] ?? ''));
        return ($n !== '' ? $n : ($u['role'] === 'accountant' ? 'Akash Tiwari' : 'Shiva Rai')) . ($u['role'] === 'accountant' ? ' (Accountant)' : ' (Reception)');
    }
    if ($u['role'] === 'student') {
        $s = sch_rec_get('student', (string)$u['tid']);
        return ($s ? (string)($s['name'] ?? '') : (string)$u['tid']) . ' (Student)';
    }
    if ($u['role'] === 'parent') {
        $names = array_map(fn($s) => (string)($s['name'] ?? ''), sch_children((string)$u['tid']));
        return 'Parent of ' . ($names ? implode(', ', $names) : (string)$u['tid']);
    }
    $n = sch_teacher_name($u['tid']);
    if ($u['role'] === 'principal') return ($n !== '' ? $n : 'Principal') . ' (Principal)';
    return $n;
}

function sch_pin_ok(string $pin): bool { return (bool)preg_match('/^\d{4,8}$/', $pin); }
const SCH_DEFAULT_PIN = '1234';   // pehli baar sab isi se login karte hain, phir apna PIN chunte hain (sql/038)
const SCH_MAX_STRIKES = 15;      // itni baar kul galat PIN = login band, naya PIN lagana padega

// ---------- session ----------
// $allowMustChange: sirf PIN badalne aur logout ke liye true. Baaki har jagah
// 1234 wala aadmi tab tak ruka rahega jab tak apna PIN na chun le.
function sch_auth(bool $allowMustChange = false): array {
    $tok = (string)($_SERVER['HTTP_X_SCHOOL_TOKEN'] ?? '');
    if (!preg_match('/^[a-f0-9]{64}$/', $tok)) sch_fail('Please log in again.', 401);
    $st = sch_db()->prepare('SELECT s.uid, u.role, u.tid, u.must_change FROM sch_sessions s JOIN sch_users u ON u.uid = s.uid
                             WHERE s.token_hash = ? AND s.expires_at > NOW()');
    $st->execute([hash('sha256', $tok)]);
    $u = $st->fetch();
    if (!$u) sch_fail('Please log in again.', 401);
    if ((int)$u['must_change'] === 1 && !$allowMustChange) sch_fail('Please choose your own PIN first.', 428);
    sch_db()->prepare('UPDATE sch_sessions SET last_seen = NOW() WHERE token_hash = ?')->execute([hash('sha256', $tok)]);
    $u['label'] = sch_user_label($u);
    return $u;
}

function sch_log(array $u, string $k, string $x): void {
    $st = sch_db()->prepare('INSERT INTO sch_log (ts, uname, role, k, x) VALUES (?,?,?,?,?)');
    $st->execute([sch_now_ms(), mb_substr($u['label'] ?? 'System', 0, 80), $u['role'] ?? 'system', mb_substr($k, 0, 40), mb_substr($x, 0, 1000)]);
}

// ---------- who may write what ----------
// Principal: sab kuchh. Chairman: sirf dekh sakte hain.
// Teacher: sirf apna kaam — apni diary, apni class ki attendance
// (agar class teacher hain), apne subject ka syllabus aur marks,
// aur apni chhutti ki arzi (sirf "Pending").
function sch_can_write(array $u, string $k, string $r, $d, bool $isDelete): bool {
    if (!in_array($k, SCH_KINDS, true)) return false;
    if ($u['role'] === 'principal') return !$isDelete || in_array($k, SCH_DELETABLE, true);
    if ($u['role'] !== 'teacher') return false;
    $tid = (string)$u['tid'];
    $p = explode('|', $r);
    // Teacher apni hi daali behaviour/speaking entry hata sakta hai
    if ($isDelete) {
        if (!in_array($k, ['behav', 'speak'], true)) return false;
        $old = sch_rec_get($k, $r);
        return $old !== null && ($old['tid'] ?? '') === $tid;
    }
    switch ($k) {
        case 'behav':     // r = date|id — kisi bhi class ke bachche ko, jise padhate hain ya class teacher hain
        case 'speak':     // r = id
            if (!is_array($d) || ($d['tid'] ?? '') !== $tid || !sch_in_class($tid, (string)($d['cls'] ?? ''))) return false;
            if ($k === 'behav' && (count($p) !== 2 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $p[0]))) return false;
            $old = sch_rec_get($k, $r);
            return $old === null || ($old['tid'] ?? '') === $tid;
        case 'lplan':     // r = weekMonday|tid|cls|subj — sirf "Submitted"; Approved plan badla nahi ja sakta
            if (count($p) !== 4 || $p[1] !== $tid || !sch_teaches($tid, $p[2], $p[3])) return false;
            if (!is_array($d) || ($d['status'] ?? '') !== 'Submitted') return false;
            $old = sch_rec_get('lplan', $r);
            return $old === null || ($old['status'] ?? '') !== 'Approved';
        case 'diary':
            return count($p) === 3 && $p[1] === $tid && preg_match('/^\d{4}-\d{2}-\d{2}$/', $p[0]) && ctype_digit($p[2]) && !sch_diary_signed($p[0],$tid);
        case 'studAtt':
            if (count($p) !== 2) return false;
            static $classes = null; $classes ??= (array)sch_rec_get('classes', 'main');
            foreach ($classes as $c) if (($c['c'] ?? '') === $p[1] && ($c['ct'] ?? '') === $tid) return true;
            return false;
        case 'syl':
            return sch_teaches($tid, $p[0] ?? '', $p[1] ?? '');
        case 'marks':
            return count($p) === 3 && sch_teaches($tid, $p[1], $p[2]);
        case 'leave':
            if (!is_array($d) || ($d['tid'] ?? '') !== $tid || ($d['status'] ?? '') !== 'Pending') return false;
            $old = sch_rec_get('leave', $r);
            return $old === null || (($old['tid'] ?? '') === $tid && ($old['status'] ?? '') === 'Pending');
    }
    return false;
}

function sch_teaches(string $tid, string $cls, string $subj): bool {
    static $alloc = null; $alloc ??= (array)sch_rec_get('alloc', 'main');
    foreach ($alloc as $a) {
        if (($a['t'] ?? '') === $tid && ($a['c'] ?? '') === $cls && ($a['s'] ?? '') === $subj) return true;
    }
    return false;
}

function sch_in_class(string $tid, string $cls): bool {
    if ($cls === '') return false;
    static $alloc = null; $alloc ??= (array)sch_rec_get('alloc', 'main');
    foreach ($alloc as $a) if (($a['t'] ?? '') === $tid && ($a['c'] ?? '') === $cls) return true;
    static $classes = null; $classes ??= (array)sch_rec_get('classes', 'main');
    foreach ($classes as $c) if (($c['c'] ?? '') === $cls && ($c['ct'] ?? '') === $tid) return true;
    return false;
}

function sch_diary_signed(string $date,string $tid):bool{$q=sch_db()->prepare("SELECT 1 FROM sch_records WHERE k='signed' AND r=? AND deleted=0");$q->execute([$date.'|'.$tid]);return (bool)$q->fetchColumn();}
