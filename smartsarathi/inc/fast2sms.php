<?php
/** Shared, framework-free Fast2SMS adapter. No credentials or plaintext OTP logs.
 * Own random OTP + local hash verification deliberately binds each code to one
 * browser, account and purpose; provider's mobile-only verify cannot do that.
 */
function f2s_config($key, $default = '') {
    return defined($key) ? constant($key) : (getenv($key) !== false ? getenv($key) : $default);
}
/** 'otp' = Fast2SMS ka bina-DLT OTP route ("Your OTP: 123456"), sirf login ke liye.
 *  'smart' = Smart OTP (DLT template IDs) — login + pickup/delivery SMS. */
function f2s_mode() {
    return f2s_config('FAST2SMS_ROUTE') === 'otp' ? 'otp' : 'smart';
}
function f2s_enabled() {
    return f2s_config('FAST2SMS_ENABLED') === '1' && f2s_config('FAST2SMS_API_KEY') !== ''
        && (f2s_mode() === 'otp' || f2s_config('FAST2SMS_LOGIN_ID') !== '');
}
function f2s_mobile($value) {
    if (!is_string($value)) return '';
    $s = preg_replace('/[\s()+-]/', '', $value);
    if (strlen($s) === 12 && substr($s, 0, 2) === '91') $s = substr($s, 2);
    return preg_match('/^[6-9][0-9]{9}$/D', $s) ? $s : '';
}
final class Fast2SmsOtp {
    private $pdo, $transport;
    public function __construct(PDO $pdo, ?callable $transport = null) {
        $this->pdo = $pdo;
        $this->transport = $transport;
    }
    public function install() {
        // Same lazy, additive installation convention as Sarathi applications.
        // Fixed SQL only; both databases remain independent.
        $this->pdo->exec('CREATE TABLE IF NOT EXISTS sms_otp_challenges (
            id VARCHAR(64) PRIMARY KEY, binding VARCHAR(64) NOT NULL,
            mobile VARCHAR(10) NOT NULL, purpose VARCHAR(40) NOT NULL,
            subject VARCHAR(100) NOT NULL, code_hash VARCHAR(255) NOT NULL,
            expires BIGINT NOT NULL, attempts INT NOT NULL DEFAULT 0,
            consumed INT NOT NULL DEFAULT 0, sent INT NOT NULL DEFAULT 0)');
        $this->pdo->exec('CREATE TABLE IF NOT EXISTS sms_rate_limits (
            bucket VARCHAR(64) PRIMARY KEY, hits INT NOT NULL DEFAULT 0, expires BIGINT NOT NULL)');
    }
    public function take($key, $limit, $seconds) {
        $now = time();
        // Fixed buckets are incremented atomically, including concurrent requests.
        $bucket = hash('sha256', $key . ':' . intdiv($now, $seconds));
        $ignore = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite' ? 'INSERT OR IGNORE' : 'INSERT IGNORE';
        $this->pdo->prepare("$ignore INTO sms_rate_limits(bucket,hits,expires) VALUES (?,0,?)")
            ->execute([$bucket, $now + $seconds]);
        $q = $this->pdo->prepare('UPDATE sms_rate_limits SET hits=hits+1 WHERE bucket=? AND hits<?');
        $q->execute([$bucket, $limit]);
        return $q->rowCount() === 1;
    }
    private function request(array $body) {
        if ($this->transport) return ($this->transport)($body) === true;
        if (!function_exists('curl_init')) return false;
        $key = (string)f2s_config('FAST2SMS_API_KEY');
        if ($key === '' || preg_match('/[\r\n]/', $key)) return false;
        // Bina-DLT OTP route: bulkV2 + route=otp. Fast2SMS khud "Your OTP: …" bhejta hai.
        if (isset($body['route'])) {
            $url = 'https://www.fast2sms.com/dev/bulkV2';
            $body = ['route'=>'otp', 'variables_values'=>(string)$body['variables_values'], 'numbers'=>(string)$body['numbers']];
        } else {
            $url = 'https://www.fast2sms.com/dev/otp/send';
        }
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_POST=>true,
            CURLOPT_HTTPHEADER=>['Authorization: '.$key, 'Content-Type: application/json', 'Accept: application/json'],
            CURLOPT_POSTFIELDS=>json_encode($body), CURLOPT_CONNECTTIMEOUT=>5, CURLOPT_TIMEOUT=>12,
            CURLOPT_FOLLOWLOCATION=>false, CURLOPT_SSL_VERIFYPEER=>true, CURLOPT_SSL_VERIFYHOST=>2]);
        $raw = curl_exec($ch); $status = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
        $data = is_string($raw) ? json_decode($raw, true) : null;
        return $status === 200 && is_array($data) && ($data['return'] ?? null) === true;
    }
    public function send($mobile, $purpose, $subject, $binding, $ip) {
        if (!f2s_mobile($mobile) || !preg_match('/^[a-z_]{1,40}$/D', $purpose)) return null;
        $this->install();
        if (!$this->take('all-day', max(1, (int)f2s_config('FAST2SMS_DAILY_LIMIT', 300)), 86400)
            || !$this->take('ip:'.$ip, 20, 3600)
            || !$this->take('phone:'.$mobile, 5, 3600)) return null;
        // Sliding resend delay, so a clock-minute boundary cannot bypass it.
        $q = $this->pdo->prepare('SELECT 1 FROM sms_otp_challenges WHERE mobile=? AND expires>?');
        $q->execute([$mobile, time()+240]);
        if ($q->fetchColumn()) return null;
        if (!$this->take('cooldown:'.$mobile, 1, 60)) return null;
        $id = bin2hex(random_bytes(32)); $code = (string)random_int(100000, 999999);
        $this->pdo->prepare('INSERT INTO sms_otp_challenges(id,binding,mobile,purpose,subject,code_hash,expires) VALUES (?,?,?,?,?,?,?)')
            ->execute([$id, hash('sha256', $binding), $mobile, $purpose, $subject, password_hash($code, PASSWORD_DEFAULT), time()+300]);
        $ok = $this->request(f2s_mode() === 'otp'
            ? ['route'=>'otp', 'numbers'=>$mobile, 'variables_values'=>$code, 'otp'=>$code]
            : ['mobile'=>$mobile, 'otp_id'=>(string)f2s_config('FAST2SMS_LOGIN_ID'), 'otp'=>$code,
               'otp_length'=>6, 'otp_expiry'=>5]);
        $this->pdo->prepare('UPDATE sms_otp_challenges SET sent=? WHERE id=?')->execute([$ok?1:0, $id]);
        if (random_int(1, 30) === 1) {
            $this->pdo->prepare('DELETE FROM sms_otp_challenges WHERE expires<?')->execute([time()-86400]);
            $this->pdo->prepare('DELETE FROM sms_rate_limits WHERE expires<?')->execute([time()-86400]);
        }
        return $ok ? $id : null;
    }
    public function verify($id, $purpose, $subject, $binding, $code) {
        if (!is_string($code) || !preg_match('/^[0-9]{6}$/D', $code)) return false;
        $params = [$id, $purpose, $subject, hash('sha256', $binding), time()];
        $q=$this->pdo->prepare('UPDATE sms_otp_challenges SET attempts=attempts+1 WHERE id=? AND purpose=? AND subject=? AND binding=? AND expires>? AND attempts<5 AND consumed=0 AND sent=1');
        $q->execute($params);
        if ($q->rowCount() !== 1) return false;
        $q=$this->pdo->prepare('SELECT code_hash FROM sms_otp_challenges WHERE id=?'); $q->execute([$id]);
        if (!password_verify($code, (string)$q->fetchColumn())) return false;
        // One winner even if two correct verifies arrive concurrently.
        $q=$this->pdo->prepare('UPDATE sms_otp_challenges SET consumed=1 WHERE id=? AND consumed=0 AND expires>?');
        $q->execute([$id, time()]);
        return $q->rowCount() === 1;
    }
    public function invalidate($id) {
        $this->pdo->prepare('UPDATE sms_otp_challenges SET consumed=1 WHERE id=?')->execute([$id]);
    }
    public function notify($mobile, $purpose, $reference, $code) {
        // Bina-DLT route me naam/booking number nahi ja sakta — "Your OTP: 4821"
        // paane wale ko dhokha lagega. Ye code screen aur WhatsApp se hi jaate hain.
        if (f2s_mode() === 'otp') return false;
        $template = (string)f2s_config($purpose === 'pickup' ? 'FAST2SMS_PICKUP_ID' : 'FAST2SMS_DELIVERY_ID');
        if ($template === '' || !f2s_mobile($mobile) || !preg_match('/^[0-9]{4,6}$/D', (string)$code)) return false;
        $this->install();
        // At most one attempt per event per day; duplicate job creation never resends.
        if (!$this->take('event:'.$purpose.':'.$reference, 1, 86400)
            || !$this->take('notify:'.$mobile, 10, 3600)
            || !$this->take('all-day', max(1,(int)f2s_config('FAST2SMS_DAILY_LIMIT',300)),86400)) return false;
        return $this->request(['mobile'=>$mobile, 'otp_id'=>$template, 'otp'=>(string)$code,
            'otp_length'=>strlen((string)$code), 'otp_expiry'=>10080,
            'variables_values'=>'{otp}|'.$reference]);
    }
}
/** Delivery SMS never rolls back a saved order. No code/phone/provider payload logged. */
function f2s_notify(PDO $pdo, $mobile, $purpose, $reference, $code) {
    if (!f2s_enabled() || f2s_mode() === 'otp') return false;
    try { $ok = (new Fast2SmsOtp($pdo))->notify($mobile,$purpose,$reference,$code); }
    catch (Throwable $e) { $ok = false; }
    if (!$ok) error_log('Fast2SMS: delivery SMS not sent; existing handover flow remains available.');
    return $ok;
}
