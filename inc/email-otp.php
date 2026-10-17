<?php
/** Email OTP is independent of mobile verification and cannot claim guest orders. */
require_once __DIR__.'/../smartsarathi/inc/fast2sms.php';
function mk_email_address($value) {
    if (!is_string($value) || preg_match('/[\r\n]/', $value)) return '';
    $value = strtolower(trim($value));
    return strlen($value)<=254 && filter_var($value, FILTER_VALIDATE_EMAIL) ? $value : '';
}
function mk_email_enabled() {
    return (string)f2s_config('MAAKIT_EMAIL_OTP_ENABLED')==='1'
        && mk_email_address(f2s_config('MAAKIT_EMAIL_FROM'))!=='';
}
function mk_email_install(PDO $pdo) {
    $pdo->exec('CREATE TABLE IF NOT EXISTS mk_customer_emails (
        customer_id BIGINT PRIMARY KEY, email VARCHAR(254) NOT NULL UNIQUE,
        verified_at BIGINT NOT NULL)');
}
function mk_email_customer(PDO $pdo, $email) {
    $q=$pdo->prepare('SELECT c.* FROM customers c JOIN mk_customer_emails e ON e.customer_id=c.id WHERE e.email=? AND c.active=1');
    $q->execute([$email]); return $q->fetch();
}
function mk_email_fingerprint($c) {
    return $c['id'].':'.substr(hash('sha256',$c['password']),0,40);
}
final class MaakitEmailOtp {
    private $pdo, $transport;
    /** 'whatsapp' / 'sms' — aakhri code kis raaste gaya (page par batane ke liye) */
    public $lastChannel = null;
    public function __construct(PDO $pdo, ?callable $transport = null) {
        $this->pdo = $pdo;
        $this->transport = $transport;
    }
    public function install() {
        // Same lazy, additive installation convention as Sarathi applications.
        // Fixed SQL only; both databases remain independent.
        $this->pdo->exec('CREATE TABLE IF NOT EXISTS mk_email_challenges (
            id VARCHAR(64) PRIMARY KEY, binding VARCHAR(64) NOT NULL,
            mobile VARCHAR(254) NOT NULL, purpose VARCHAR(40) NOT NULL,
            subject VARCHAR(100) NOT NULL, code_hash VARCHAR(255) NOT NULL,
            expires BIGINT NOT NULL, attempts INT NOT NULL DEFAULT 0,
            consumed INT NOT NULL DEFAULT 0, sent INT NOT NULL DEFAULT 0)');
        $this->pdo->exec('CREATE TABLE IF NOT EXISTS mk_email_rate_limits (
            bucket VARCHAR(64) PRIMARY KEY, hits INT NOT NULL DEFAULT 0, expires BIGINT NOT NULL)');
    }
    public function take($key, $limit, $seconds) {
        $now = time();
        // Fixed buckets are incremented atomically, including concurrent requests.
        $bucket = hash('sha256', $key . ':' . intdiv($now, $seconds));
        $ignore = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite' ? 'INSERT OR IGNORE' : 'INSERT IGNORE';
        $this->pdo->prepare("$ignore INTO mk_email_rate_limits(bucket,hits,expires) VALUES (?,0,?)")
            ->execute([$bucket, $now + $seconds]);
        $q = $this->pdo->prepare('UPDATE mk_email_rate_limits SET hits=hits+1 WHERE bucket=? AND hits<?');
        $q->execute([$bucket, $limit]);
        return $q->rowCount() === 1;
    }
    private function request(array $body) {
        if ($this->transport) return ($this->transport)($body) === true;
        if (!mk_email_enabled()) return false;
        $from = mk_email_address(f2s_config('MAAKIT_EMAIL_FROM'));
        return mail($body['email'], 'Your Maakit sign-in code',
            "Your Maakit code is: ".$body['otp']."\nValid for 5 minutes. Do not share it.\nIf you did not request this, ignore this email.",
            ['From'=>'Maakit <'.$from.'>', 'Content-Type'=>'text/plain; charset=UTF-8']);
    }
    public function send($mobile, $purpose, $subject, $binding, $ip) {
        if (!mk_email_address($mobile) || !preg_match('/^[a-z_]{1,40}$/D', $purpose)) return null;
        $this->install();
        if (!$this->take('all-day', max(1, (int)f2s_config('MAAKIT_EMAIL_DAILY_LIMIT', 300)), 86400)
            || !$this->take('ip:'.$ip, 20, 3600)
            || !$this->take('phone:'.$mobile, 5, 3600)) return null;
        // Sliding resend delay, so a clock-minute boundary cannot bypass it.
        $q = $this->pdo->prepare('SELECT 1 FROM mk_email_challenges WHERE mobile=? AND expires>?');
        $q->execute([$mobile, time()+240]);
        if ($q->fetchColumn()) return null;
        if (!$this->take('cooldown:'.$mobile, 1, 60)) return null;
        $id = bin2hex(random_bytes(32)); $code = (string)random_int(100000, 999999);
        $this->pdo->prepare('INSERT INTO mk_email_challenges(id,binding,mobile,purpose,subject,code_hash,expires) VALUES (?,?,?,?,?,?,?)')
            ->execute([$id, hash('sha256', $binding), $mobile, $purpose, $subject, password_hash($code, PASSWORD_DEFAULT), time()+300]);
        $ok = $this->request(['email'=>$mobile, 'otp'=>$code]);
        $this->lastChannel = $ok ? 'email' : null;
        $this->pdo->prepare('UPDATE mk_email_challenges SET sent=? WHERE id=?')->execute([$ok?1:0, $id]);
        if (random_int(1, 30) === 1) {
            $this->pdo->prepare('DELETE FROM mk_email_challenges WHERE expires<?')->execute([time()-86400]);
            $this->pdo->prepare('DELETE FROM mk_email_rate_limits WHERE expires<?')->execute([time()-86400]);
        }
        return $ok ? $id : null;
    }
    public function verify($id, $purpose, $subject, $binding, $code) {
        if (!is_string($code) || !preg_match('/^[0-9]{6}$/D', $code)) return false;
        $params = [$id, $purpose, $subject, hash('sha256', $binding), time()];
        $q=$this->pdo->prepare('UPDATE mk_email_challenges SET attempts=attempts+1 WHERE id=? AND purpose=? AND subject=? AND binding=? AND expires>? AND attempts<5 AND consumed=0 AND sent=1');
        $q->execute($params);
        if ($q->rowCount() !== 1) return false;
        $q=$this->pdo->prepare('SELECT code_hash FROM mk_email_challenges WHERE id=?'); $q->execute([$id]);
        if (!password_verify($code, (string)$q->fetchColumn())) return false;
        // One winner even if two correct verifies arrive concurrently.
        $q=$this->pdo->prepare('UPDATE mk_email_challenges SET consumed=1 WHERE id=? AND consumed=0 AND expires>?');
        $q->execute([$id, time()]);
        return $q->rowCount() === 1;
    }
    public function invalidate($id) {
        $this->pdo->prepare('UPDATE mk_email_challenges SET consumed=1 WHERE id=?')->execute([$id]);
    }
}
