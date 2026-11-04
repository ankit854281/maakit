<?php
declare(strict_types=1);
require_once __DIR__ . '/../../school/login_otp.php';
function otp_check(bool $ok, string $message): void {
    if (!$ok) throw new RuntimeException($message);
}
function sch_db(): PDO {
    static $db;
    return $db ??= new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
}
function sch_rec_get(string $kind, string $id): ?array { return $GLOBALS['otpTeacher']; }
$GLOBALS['otpTeacher'] = ['phone' => '+91 9000000001'];
putenv('SCHOOL_TEACHER_OTP_ENABLED=1');
otp_check(sch_teacher_otp_enabled(), 'Private activation flag');
$user = ['uid' => 't:test', 'tid' => 'test', 'pin_hash' => password_hash('5678', PASSWORD_DEFAULT)];
$subject = sch_teacher_otp_subject($user, '9000000001');
otp_check($subject !== sch_teacher_otp_subject($user, '9000000002'), 'Changed phone invalidates challenge');
otp_check($subject !== sch_teacher_otp_subject(array_replace($user, ['pin_hash' => 'new']), '9000000001'), 'Reset PIN invalidates challenge');
$code = '';
$otp = new Fast2SmsOtp(sch_db(), function (array $body) use (&$code): bool { $code = $body['otp']; return true; });
$id = $otp->send('9000000001', 'school_teacher_login', $subject, 'browser-secret', 'test');
otp_check(is_string($id), 'Send challenge');
otp_check(!$otp->verify($id, 'school_teacher_login', $subject, 'wrong-browser', $code), 'Browser binding');
otp_check(!$otp->verify($id, 'customer_login', $subject, 'browser-secret', $code), 'Purpose binding');
otp_check(!$otp->verify($id, 'school_teacher_login', 'another-teacher', 'browser-secret', $code), 'Teacher binding');
otp_check($otp->verify($id, 'school_teacher_login', $subject, 'browser-secret', $code), 'Valid verification');
otp_check(!$otp->verify($id, 'school_teacher_login', $subject, 'browser-secret', $code), 'One use only');
$expired = $otp->send('9000000002', 'school_teacher_login', $subject, 'browser-secret', 'test');
sch_db()->prepare('UPDATE sms_otp_challenges SET expires=? WHERE id=?')->execute([time() - 1, $expired]);
otp_check(!$otp->verify($expired, 'school_teacher_login', $subject, 'browser-secret', $code), 'Expired code rejected');
$limited = $otp->send('9000000003', 'school_teacher_login', $subject, 'browser-secret', 'test');
$wrong = $code === '999999' ? '888888' : '999999';
for ($attempt = 0; $attempt < 5; $attempt++) {
    otp_check(!$otp->verify($limited, 'school_teacher_login', $subject, 'browser-secret', $wrong), 'Wrong code rejected');
}
otp_check(!$otp->verify($limited, 'school_teacher_login', $subject, 'browser-secret', $code), 'Five failures exhaust challenge');
sch_db()->exec('CREATE TABLE mk_school_otp_sessions(token_hash TEXT PRIMARY KEY,phone_hash TEXT)');
otp_check(!sch_teacher_otp_session($user, 'legacy'), 'Old PIN-only session rejected');
sch_db()->prepare('INSERT INTO mk_school_otp_sessions VALUES (?,?)')->execute(['verified', hash('sha256', '9000000001')]);
otp_check(sch_teacher_otp_session($user, 'verified'), 'Verified session accepted');
$GLOBALS['otpTeacher']['phone'] = '9000000002';
otp_check(!sch_teacher_otp_session($user, 'verified'), 'Phone change revokes access');
$GLOBALS['otpTeacher'] = null;
otp_check(!sch_teacher_otp_session($user, 'verified'), 'Deleted teacher loses access');
echo "Teacher OTP checks passed\n";
