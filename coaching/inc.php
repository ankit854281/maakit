<?php
// ============================================================
// Coaching Saathi — saajha helper (database, login, khata, UI)
//
// Alag product, "powered by Maakit" (jaise /school). Sirf root ki
// config.php se database ki chaabi padhta hai aur sirf "cs_" tables
// par kaam karta hai. Maakit ki kisi table ko nahi chhoota.
//
// SURAKSHA KA NIYAM: coaching_id hamesha session se aati hai
// (cs_me()['cid']), kabhi form ya URL se nahi. Har query jo kisi
// coaching ka data padhti/likhti hai, "coaching_id=?" ke saath chalti hai.
// ============================================================

declare(strict_types=1);
date_default_timezone_set('Asia/Kolkata');
if (is_file(__DIR__ . '/../config.php')) require_once __DIR__ . '/../config.php';   // chaabiyan (OTP, Razorpay) pehle se padh lo

const CS_SESSION_DAYS = 90;          // OTP baar-baar na lage (SMS ka kharcha)
const CS_TRIAL_DAYS   = 7;
const CS_SOFT_LIMIT   = 100;         // free owner: isse zyada bachche par suchna
const CS_TYPES = ['tuition' => 'Tuition / Board', 'computer' => 'Computer + Spoken English',
                  'sarkari' => 'SSC / Bank / Police', 'hobby' => 'Kala / Hobby'];
const CS_FEE_TYPES = ['monthly' => 'Har mahine', 'full' => 'Poora course (kishton me de sakte)', 'once' => 'Ek baar'];

function cs_cfg(string $k, $d = '') {
    return defined($k) ? constant($k) : (getenv($k) !== false ? getenv($k) : $d);
}

function cs_db(): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;
    $cfg = __DIR__ . '/../config.php';
    if (is_file($cfg)) require_once $cfg;
    if (!defined('DB_HOST')) throw new RuntimeException('config.php missing');
    $pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $pdo->exec("SET time_zone = '+05:30'");
    return $pdo;
}

function cs_q(string $sql, array $p = []): PDOStatement {
    $st = cs_db()->prepare($sql);
    $st->execute($p);
    return $st;
}
function cs_row(string $sql, array $p = []): ?array { $r = cs_q($sql, $p)->fetch(); return $r ?: null; }
function cs_all(string $sql, array $p = []): array { return cs_q($sql, $p)->fetchAll(); }
function cs_val(string $sql, array $p = []) { return cs_q($sql, $p)->fetchColumn(); }

function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function rs($n): string { $n = (int)$n; return ($n < 0 ? '-' : '') . '₹' . number_format(abs($n), 0, '.', ','); }
function cs_mobile($v): string {
    $s = preg_replace('/\D/', '', (string)$v);
    if (strlen($s) === 12 && substr($s, 0, 2) === '91') $s = substr($s, 2);
    return preg_match('/^[6-9]\d{9}$/', $s) ? $s : '';
}
function cs_date($v): string {
    $v = (string)$v;
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) && strtotime($v) ? $v : '';
}
function cs_int($v): int { return max(0, (int)preg_replace('/\D/', '', (string)$v)); }
function cs_post(string $k, string $d = ''): string { return trim((string)($_POST[$k] ?? $d)); }
function cs_get(string $k, string $d = ''): string { return trim((string)($_GET[$k] ?? $d)); }
function cs_wa(string $mobile, string $text): string { return 'https://wa.me/91' . $mobile . '?text=' . rawurlencode($text); }
function cs_base(): string {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $host = preg_replace('/[^a-z0-9.:-]/i', '', (string)($_SERVER['HTTP_HOST'] ?? 'maakit.in'));
    return ($https ? 'https' : 'http') . '://' . $host . '/coaching';
}
function cs_go(string $url): void { header('Location: ' . $url); exit; }

// ---------------- session / login ----------------
function cs_https(): bool {
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
}
function cs_cookie(string $name, string $val, int $days): void {
    setcookie($name, $val, ['expires' => $days ? time() + $days * 86400 : time() - 3600, 'path' => '/coaching/',
        'secure' => cs_https(), 'httponly' => true, 'samesite' => 'Lax']);
    $_COOKIE[$name] = $val;
}
/** Har browser ka apna binding — OTP usi browser se verify ho */
function cs_binding(): string {
    $b = (string)($_COOKIE['cs_b'] ?? '');
    if (!preg_match('/^[a-f0-9]{32}$/', $b)) { $b = bin2hex(random_bytes(16)); cs_cookie('cs_b', $b, 400); }
    return $b;
}

/** kind: 'staff' (owner/teacher, ek coaching) | 'family' (parent mobile, uske saare bachche) | 'verified' (OTP ho gaya, role chunna baaki) */
function cs_session_start(string $kind, string $mobile, ?int $cid = null, ?int $uid = null): void {
    $tok = bin2hex(random_bytes(32));
    cs_q('INSERT INTO cs_session (token_hash,kind,mobile,coaching_id,user_id,expires) VALUES (?,?,?,?,?,?)',
        [hash('sha256', $tok), $kind, $mobile, $cid, $uid, date('Y-m-d H:i:s', time() + CS_SESSION_DAYS * 86400)]);
    cs_cookie('cs_t', $tok, CS_SESSION_DAYS);
    if (random_int(1, 20) === 1) cs_q('DELETE FROM cs_session WHERE expires < NOW()');
}
function cs_session(): ?array {
    static $s = false;
    if ($s !== false) return $s;
    $tok = (string)($_COOKIE['cs_t'] ?? '');
    $s = null;
    if (preg_match('/^[a-f0-9]{64}$/', $tok)) {
        $s = cs_row('SELECT * FROM cs_session WHERE token_hash=? AND expires>NOW()', [hash('sha256', $tok)]);
        if ($s) $s['raw'] = $tok;
    }
    return $s;
}
function cs_logout(): void {
    $s = cs_session();
    if ($s) cs_q('DELETE FROM cs_session WHERE token_hash=?', [$s['token_hash']]);
    cs_cookie('cs_t', '', 0);
}
function cs_csrf(): string { $s = cs_session(); return $s ? substr(hash('sha256', $s['raw'] . '|cs-form'), 0, 32) : ''; }
function cs_csrf_field(): string { return '<input type="hidden" name="csrf" value="' . h(cs_csrf()) . '">'; }
function cs_csrf_ok(): bool { return cs_csrf() !== '' && hash_equals(cs_csrf(), (string)($_POST['csrf'] ?? '')); }

/** Owner / teacher. Login na ho to index par bhej do. Teacher deactivate ho to bahar. */
function cs_me(): array {
    static $me = null;
    if ($me) return $me;
    $s = cs_session();
    if (!$s || $s['kind'] !== 'staff') cs_go('index.php');
    $u = cs_row('SELECT * FROM cs_user WHERE id=? AND coaching_id=? AND mobile=? AND active=1', [$s['user_id'], $s['coaching_id'], $s['mobile']]);
    $c = $u ? cs_row('SELECT * FROM cs_coaching WHERE id=?', [$u['coaching_id']]) : null;
    if (!$u || !$c) { cs_logout(); cs_go('index.php?m=band'); }
    $me = ['cid' => (int)$c['id'], 'uid' => (int)$u['id'], 'role' => $u['role'], 'user' => $u, 'c' => $c];
    return $me;
}
function cs_owner(): bool { return cs_me()['role'] === 'owner'; }

// ---------------- OTP ----------------
function cs_otp_demo(): bool { return cs_cfg('CS_DEMO_OTP') === true || cs_cfg('CS_DEMO_OTP') === '1'; }
function cs_otp_engine(?callable $transport = null) {
    require_once __DIR__ . '/../smartsarathi/inc/fast2sms.php';
    return new Fast2SmsOtp(cs_db(), $transport);
}
/** [id, demo_code|null, error|null] */
function cs_otp_send(string $mobile): array {
    $ip = substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
    $demo = null;
    require_once __DIR__ . '/../smartsarathi/inc/fast2sms.php';
    if (f2s_enabled()) {
        $id = cs_otp_engine()->send($mobile, 'cs_login', 'cs:' . $mobile, cs_binding(), $ip);
    } elseif (cs_otp_demo()) {
        // Sirf testing ke liye: config.php me define('CS_DEMO_OTP', true). Code screen par dikhta hai.
        $id = cs_otp_engine(function ($b) use (&$demo) { $demo = (string)$b['otp']; return true; })
            ->send($mobile, 'cs_login', 'cs:' . $mobile, cs_binding(), $ip);
    } else {
        return [null, null, 'OTP abhi chalu nahi hai (SMS setup baaki). Maakit se sampark karein.'];
    }
    if (!$id) return [null, null, 'OTP nahi gaya. 1 minute baad dobara koshish karein.'];
    return [$id, $demo, null];
}
function cs_otp_check(string $id, string $mobile, string $code): bool {
    return cs_otp_engine()->verify($id, 'cs_login', 'cs:' . $mobile, cs_binding(), $code);
}

// ---------------- khata (fees) ----------------
function cs_month_list(string $from, string $to): array {
    $out = []; $d = new DateTime(substr($from, 0, 7) . '-01'); $end = substr($to, 0, 7);
    for ($i = 0; $i < 240 && $d->format('Y-m') <= $end; $i++) { $out[] = $d->format('Y-m'); $d->modify('+1 month'); }
    return $out;
}

/** Har active admission ki fees khaate me jodo (mahine ki 1 tareekh wala kaam).
 *  Cron ki zaroorat nahi: har page khulne par chalta hai, due_key se do baar nahi judta. */
function cs_post_dues(int $cid, ?int $sid = null): void {
    $sql = "SELECT e.id,e.student_id,e.fee,e.start_date,c.fee_type,c.naam AS course, c.months
            FROM cs_enroll e JOIN cs_batch b ON b.id=e.batch_id AND b.coaching_id=e.coaching_id
            JOIN cs_course c ON c.id=b.course_id AND c.coaching_id=e.coaching_id
            JOIN cs_student s ON s.id=e.student_id AND s.coaching_id=e.coaching_id
            WHERE e.coaching_id=? AND e.status='active' AND s.status='active' AND e.fee>0";
    $p = [$cid];
    if ($sid) { $sql .= ' AND e.student_id=?'; $p[] = $sid; }
    $ins = cs_db()->prepare("INSERT IGNORE INTO cs_ledger (coaching_id,student_id,enroll_id,kind,month,amount,note,due_key)
                             VALUES (?,?,?,'due',?,?,?,?)");
    $today = date('Y-m-d');
    foreach (cs_all($sql, $p) as $e) {
        if ($e['start_date'] > $today) continue;
        if ($e['fee_type'] === 'monthly') {
            $months = cs_month_list($e['start_date'], $today);
            if ((int)$e['months'] > 0) $months = array_slice($months, 0, (int)$e['months']);
            foreach ($months as $m)
                $ins->execute([$cid, $e['student_id'], $e['id'], $m, $e['fee'], $e['course'] . ' — ' . $m, 'e' . $e['id'] . '-' . $m]);
        } else {
            $ins->execute([$cid, $e['student_id'], $e['id'], substr($e['start_date'], 0, 7), $e['fee'], $e['course'] . ' (poori fees)', 'e' . $e['id'] . '-once']);
        }
    }
}

/** Baaki rakam. Plus = lena hai, minus = advance jama. */
function cs_balance(int $cid, int $sid): int {
    return (int)cs_val("SELECT COALESCE(SUM(CASE WHEN kind IN ('due','fix') THEN amount ELSE -amount END),0)
                        FROM cs_ledger WHERE coaching_id=? AND student_id=?", [$cid, $sid]);
}
/** Saare bachchon ka baaki ek query me: [student_id => balance] */
function cs_balances(int $cid): array {
    $out = [];
    foreach (cs_all("SELECT student_id, SUM(CASE WHEN kind IN ('due','fix') THEN amount ELSE -amount END) b
                     FROM cs_ledger WHERE coaching_id=? GROUP BY student_id", [$cid]) as $r) $out[(int)$r['student_id']] = (int)$r['b'];
    return $out;
}

/** Paisa mila: receipt + khata entry, ek saath. Same button do baar = ek hi receipt (nonce). */
function cs_take_payment(int $cid, int $sid, int $amount, string $mode, string $date, int $uid, string $nonce): int {
    $db = cs_db();
    if ($nonce !== '') {
        $old = cs_val('SELECT id FROM cs_receipt WHERE nonce=? AND coaching_id=?', [$nonce, $cid]);
        if ($old) return (int)$old;
    }
    $db->beginTransaction();
    try {
        cs_q('SELECT receipt_seq FROM cs_coaching WHERE id=? FOR UPDATE', [$cid]);
        cs_q('UPDATE cs_coaching SET receipt_seq=receipt_seq+1 WHERE id=?', [$cid]);
        $no = (int)cs_val('SELECT receipt_seq FROM cs_coaching WHERE id=?', [$cid]);
        cs_q('INSERT INTO cs_receipt (coaching_id,student_id,no,amount,mode,paid_on,token,nonce,by_user) VALUES (?,?,?,?,?,?,?,?,?)',
            [$cid, $sid, $no, $amount, $mode, $date, bin2hex(random_bytes(12)), $nonce !== '' ? $nonce : null, $uid]);
        $rid = (int)$db->lastInsertId();
        cs_q("INSERT INTO cs_ledger (coaching_id,student_id,kind,amount,note,receipt_id,by_user) VALUES (?,?,'pay',?,?,?,?)",
            [$cid, $sid, $amount, 'Receipt #' . $no . ' (' . ($mode === 'upi' ? 'UPI' : 'Cash') . ')', $rid, $uid]);
        $db->commit();
        return $rid;
    } catch (Throwable $e) {
        $db->rollBack();
        if ($nonce !== '' && ($old = cs_val('SELECT id FROM cs_receipt WHERE nonce=? AND coaching_id=?', [$nonce, $cid]))) return (int)$old;
        throw $e;
    }
}

/** "3 din se nahi aaya" — aakhri 3 haaziri lagatar A */
function cs_absent3(int $cid): array {
    $rows = cs_all("SELECT a.student_id, a.status FROM cs_attendance a JOIN cs_student s ON s.id=a.student_id AND s.coaching_id=a.coaching_id
                    WHERE a.coaching_id=? AND s.status='active' AND a.d >= CURDATE() - INTERVAL 30 DAY ORDER BY a.student_id, a.d DESC", [$cid]);
    $seen = []; $out = [];
    foreach ($rows as $r) {
        $id = (int)$r['student_id'];
        if (!isset($seen[$id])) $seen[$id] = ['n' => 0, 'stop' => false];
        if ($seen[$id]['stop']) continue;
        if ($r['status'] === 'A') { if (++$seen[$id]['n'] >= 3) { $out[$id] = true; $seen[$id]['stop'] = true; } }
        else $seen[$id]['stop'] = true;
    }
    return array_keys($out);
}
/** Fees 15 din se zyada baaki: 15 din se purani fees − ab tak ka saara jama/maaf > 0 */
function cs_fee_late(int $cid): array {
    $rows = cs_all("SELECT student_id,
          SUM(CASE WHEN kind IN ('due','fix') AND (CASE WHEN month IS NULL THEN DATE(created_at) ELSE CONCAT(month,'-01') END) <= CURDATE() - INTERVAL 15 DAY THEN amount ELSE 0 END)
        - SUM(CASE WHEN kind IN ('pay','waive') THEN amount ELSE 0 END) late
        FROM cs_ledger WHERE coaching_id=? GROUP BY student_id HAVING late > 0", [$cid]);
    $out = [];
    foreach ($rows as $r) $out[(int)$r['student_id']] = (int)$r['late'];
    return $out;
}

// ---------------- student app ka plan ----------------
function cs_pay_ready(): bool { return cs_cfg('CS_RAZORPAY_KEY_ID') !== '' && cs_cfg('CS_RAZORPAY_KEY_SECRET') !== ''; }
function cs_plans(): array {
    return ['6m' => ['amount' => (int)cs_cfg('CS_PRICE_6M', 54), 'months' => 6, 'label' => '6 mahine'],
            '12m' => ['amount' => (int)cs_cfg('CS_PRICE_12M', 99), 'months' => 12, 'label' => '12 mahine']];
}
/** ['open'=>bool, 'why'=>'trial|paid|free', 'till'=>date] — lock tabhi lagta hai jab payment chalu ho */
function cs_access(array $student): array {
    $till = cs_val("SELECT MAX(valid_till) FROM cs_sub WHERE student_id=? AND coaching_id=? AND status='paid'", [$student['id'], $student['coaching_id']]);
    if ($till && $till >= date('Y-m-d')) return ['open' => true, 'why' => 'paid', 'till' => $till];
    $trial = date('Y-m-d', strtotime(substr((string)$student['created_at'], 0, 10) . ' +' . CS_TRIAL_DAYS . ' days'));
    if ($trial >= date('Y-m-d')) return ['open' => true, 'why' => 'trial', 'till' => $trial];
    if (!cs_pay_ready()) return ['open' => true, 'why' => 'free', 'till' => null];
    return ['open' => false, 'why' => $till ? 'expired' : 'trial_over', 'till' => $till ?: $trial];
}
/** Payment ki pakki entry — webhook aur checkout dono yahi bulate hain. Do baar chale to bhi ek hi baar. */
function cs_sub_mark_paid(string $orderId, string $paymentId, int $amountPaise): bool {
    $db = cs_db();
    $db->beginTransaction();
    try {
        $s = cs_row('SELECT * FROM cs_sub WHERE order_id=? FOR UPDATE', [$orderId]);
        if (!$s || $s['amount'] * 100 !== $amountPaise) { $db->rollBack(); return false; }
        if ($s['status'] === 'paid') { $db->commit(); return true; }
        // Naya plan purane plan ke khatam hone ke baad se (ya aaj se). Ginti payment ki date se.
        $last = cs_val("SELECT MAX(valid_till) FROM cs_sub WHERE student_id=? AND status='paid'", [$s['student_id']]);
        $from = ($last && $last >= date('Y-m-d')) ? date('Y-m-d', strtotime($last . ' +1 day')) : date('Y-m-d');
        $months = $s['plan'] === '12m' ? 12 : 6;
        $till = date('Y-m-d', strtotime($from . " +$months months -1 day"));
        cs_q("UPDATE cs_sub SET status='paid', payment_id=?, paid_on=NOW(), valid_from=?, valid_till=? WHERE id=?", [$paymentId, $from, $till, $s['id']]);
        $db->commit();
        return true;
    } catch (Throwable $e) { $db->rollBack(); throw $e; }
}

// ---------------- UI ----------------
function cs_head(string $title, ?array $c = null, string $bodyClass = '', string $meta = ''): void {
    $color = $c && preg_match('/^#[0-9a-fA-F]{6}$/', (string)$c['color']) ? $c['color'] : '#0C831F';
    echo '<!doctype html><html lang="hi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">',
         '<title>', h($title), '</title>', $meta !== '' ? $meta : '<meta name="robots" content="noindex">',
         '<link rel="stylesheet" href="/smartsarathi/assets/fonts.css"><link rel="stylesheet" href="/coaching/cs.css?v=1">',
         '<style>:root{--c:', h($color), '}</style></head><body class="', h($bodyClass), '">';
}
function cs_foot(): void {
    echo '<footer class="pw">Coaching Saathi · powered by <a href="https://maakit.in">Maakit</a></footer></body></html>';
}
function cs_flash(): string {
    $m = ['ok' => 'Ho gaya ✓', 'saved' => 'Save ho gaya ✓', 'paid' => 'Paisa likh diya ✓', 'waived' => 'Maaf kar diya ✓',
          'csrf' => 'Page purana tha — dobara bhariye.', 'band' => 'Ye login band hai.', 'err' => 'Kuch galat bhara hai — dobara dekhiye.'];
    $k = cs_get('m');
    if (!isset($m[$k])) return '';
    $bad = in_array($k, ['csrf', 'band', 'err'], true);
    return '<div class="flash' . ($bad ? ' bad' : '') . '">' . h($m[$k]) . '</div>';
}
function cs_icon(string $n): string {
    $p = ['home' => 'M3 11l9-8 9 8v10h-6v-6H9v6H3z', 'users' => 'M16 11a4 4 0 1 0-8 0 4 4 0 0 0 8 0zM4 21c0-4 4-6 8-6s8 2 8 6',
          'rupee' => 'M6 4h12M6 9h12M6 4c7 0 7 9 0 9l9 8', 'check' => 'M4 12l5 5L20 6', 'phone' => 'M5 4h4l2 5-3 2a11 11 0 0 0 5 5l2-3 5 2v4a2 2 0 0 1-2 2A17 17 0 0 1 3 6a2 2 0 0 1 2-2z',
          'more' => 'M5 12h.01M12 12h.01M19 12h.01'];
    return '<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="' . ($p[$n] ?? '') . '"/></svg>';
}
