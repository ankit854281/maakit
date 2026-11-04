<?php
declare(strict_types=1);
require_once __DIR__ . '/../smartsarathi/inc/fast2sms.php';

// Activate only after the school has checked its registered numbers and provider.
function sch_teacher_otp_enabled(): bool {
    return filter_var(f2s_config('SCHOOL_TEACHER_OTP_ENABLED', false), FILTER_VALIDATE_BOOLEAN);
}

function sch_teacher_otp_subject(array $user, string $phone): string {
    return hash('sha256', $user['uid'] . '|' . $user['pin_hash'] . '|' . $phone);
}

function sch_teacher_otp_start(array $user): array {
    if (!f2s_enabled()) sch_fail('Mobile OTP service is unavailable. Please contact the school office.', 503);
    $teacher = sch_rec_get('teacher', (string)$user['tid']);
    $phone = f2s_mobile($teacher['phone'] ?? '');
    if ($phone === '') sch_fail('Ask the Principal to save your correct mobile number in Settings.', 403);
    $binding = bin2hex(random_bytes(32));
    $otp = new Fast2SmsOtp(sch_db());
    $id = $otp->send($phone, 'school_teacher_login', sch_teacher_otp_subject($user, $phone), $binding, sch_ip());
    if (!$id) sch_fail('OTP could not be sent. Wait a minute and try again, or contact the school office.', 429);
    return ['ok' => true, 'otpRequired' => true, 'challenge' => $id, 'binding' => $binding,
        'tid' => $user['tid'], 'mobile' => '******' . substr($phone, -4),
        'channel' => $otp->lastChannel, 'expiresIn' => 300];
}

function sch_teacher_otp_verify(array $input): array {
    if (!sch_teacher_otp_enabled()) sch_fail('Please start login again.', 409);
    $tid = (string)($input['tid'] ?? '');
    $challenge = (string)($input['challenge'] ?? '');
    $binding = (string)($input['binding'] ?? '');
    if (!preg_match('/^[A-Za-z0-9_]{1,20}$/D', $tid) || $tid === 'P'
        || !preg_match('/^[a-f0-9]{64}$/D', $challenge) || !preg_match('/^[a-f0-9]{64}$/D', $binding)) {
        sch_fail('Please start login again.', 403);
    }
    $db = sch_db();
    $otp = new Fast2SmsOtp($db);
    $otp->install();
    if (!$otp->take('school-verify:' . sch_ip(), 30, 900)) sch_fail('Too many tries. Wait 15 minutes.', 429);
    $db->beginTransaction();
    // Lock identity through verification and session creation. A PIN reset invalidates a challenge.
    $q = $db->prepare('SELECT * FROM sch_users WHERE uid=? AND role=? FOR UPDATE');
    $q->execute(['t:' . $tid, 'teacher']);
    $user = $q->fetch();
    $q = $db->prepare("SELECT d FROM sch_records WHERE k='teacher' AND r=? AND deleted=0 FOR UPDATE");
    $q->execute([$tid]);
    $teacher = json_decode((string)$q->fetchColumn(), true);
    $phone = f2s_mobile($teacher['phone'] ?? '');
    if (!$user || !$phone || (int)$user['strikes'] >= SCH_MAX_STRIKES
        || ($user['locked_until'] && strtotime($user['locked_until']) > time())) {
        $db->rollBack();
        sch_fail('Please contact the Principal and start login again.', 403);
    }
    if (!$otp->verify($challenge, 'school_teacher_login', sch_teacher_otp_subject($user, $phone), $binding, $input['code'] ?? '')) {
        // Keep the failed-attempt counter even when verification fails.
        $db->commit();
        sch_fail('OTP is incorrect, expired or already used. Try again or request a new OTP.', 403);
    }
    $token = bin2hex(random_bytes(32));
    $hash = hash('sha256', $token);
    $db->prepare('INSERT INTO sch_sessions(token_hash,uid,expires_at,last_seen) VALUES (?,?,?,NOW())')
        ->execute([$hash, $user['uid'], date('Y-m-d H:i:s', time() + SCH_SESSION_DAYS * 86400)]);
    $db->prepare('INSERT INTO mk_school_otp_sessions(token_hash,phone_hash) VALUES (?,?)')
        ->execute([$hash, hash('sha256', $phone)]);
    $me = ['role' => 'teacher', 'tid' => $tid];
    $me['label'] = sch_user_label($me);
    sch_log($me, 'Login', $me['label'] . ' logged in with mobile OTP');
    $db->commit();
    return ['ok' => true, 'token' => $token, 'me' => $me, 'mustChange' => (int)$user['must_change'] === 1];
}

function sch_teacher_otp_session(array $user, string $tokenHash): bool {
    $teacher = sch_rec_get('teacher', (string)$user['tid']);
    $phone = f2s_mobile($teacher['phone'] ?? '');
    if ($phone === '') return false;
    $q = sch_db()->prepare('SELECT phone_hash FROM mk_school_otp_sessions WHERE token_hash=?');
    $q->execute([$tokenHash]);
    $verified = $q->fetchColumn();
    return is_string($verified) && hash_equals($verified, hash('sha256', $phone));
}
