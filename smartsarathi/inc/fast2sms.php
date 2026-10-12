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
/** WhatsApp Business (Meta Cloud API) — DLT ki zaroorat nahi. $otp=true: login template bhi chahiye */
function wa_ready($otp = true) {
    return f2s_config('WA_TOKEN') !== '' && preg_match('/^[0-9]{5,25}$/D', (string)f2s_config('WA_PHONE_ID'))
        && (!$otp || f2s_config('WA_OTP_TEMPLATE') !== '');
}
function f2s_sms_ready() {
    return f2s_config('FAST2SMS_API_KEY') !== '' && (f2s_mode() === 'otp' || f2s_config('FAST2SMS_LOGIN_ID') !== '');
}
/** Pehle WhatsApp, na pahunche to SMS (jo jo setup ho) */
function f2s_enabled() {
    return (f2s_config('FAST2SMS_ENABLED') === '1' || f2s_config('OTP_ENABLED') === '1') && (wa_ready() || f2s_sms_ready());
}
function f2s_mobile($value) {
    if (!is_string($value)) return '';
    $s = preg_replace('/[\s()+-]/', '', $value);
    if (strlen($s) === 12 && substr($s, 0, 2) === '91') $s = substr($s, 2);
    return preg_match('/^[6-9][0-9]{9}$/D', $s) ? $s : '';
}
final class Fast2SmsOtp {
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
        if (($body['channel'] ?? '') === 'whatsapp') return $this->whatsapp($body);
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
    /** Meta WhatsApp Cloud API — approved template. Login template me "Copy code" button hota hai. */
    private function whatsapp(array $b) {
        $token = (string)f2s_config('WA_TOKEN'); $phone = (string)f2s_config('WA_PHONE_ID');
        if ($token === '' || preg_match('/[\r\n]/', $token) || !preg_match('/^[0-9]{5,25}$/D', $phone)) return false;
        $ver = (string)f2s_config('WA_API_VERSION', 'v21.0');
        if (!preg_match('/^v[0-9]{1,3}\.[0-9]{1,2}$/D', $ver)) $ver = 'v21.0';
        $comp = [['type'=>'body', 'parameters'=>array_map(fn($p) => ['type'=>'text', 'text'=>(string)$p], $b['params'])]];
        if (!empty($b['button'])) $comp[] = ['type'=>'button', 'sub_type'=>'url', 'index'=>'0', 'parameters'=>[['type'=>'text', 'text'=>(string)$b['params'][0]]]];
        $payload = ['messaging_product'=>'whatsapp', 'recipient_type'=>'individual', 'to'=>'91'.$b['to'], 'type'=>'template',
            'template'=>['name'=>(string)$b['template'], 'language'=>['code'=>(string)f2s_config('WA_LANG', 'en')], 'components'=>$comp]];
        $ch = curl_init('https://graph.facebook.com/'.$ver.'/'.$phone.'/messages');
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_POST=>true,
            CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$token, 'Content-Type: application/json', 'Accept: application/json'],
            CURLOPT_POSTFIELDS=>json_encode($payload), CURLOPT_CONNECTTIMEOUT=>5, CURLOPT_TIMEOUT=>12,
            CURLOPT_FOLLOWLOCATION=>false, CURLOPT_SSL_VERIFYPEER=>true, CURLOPT_SSL_VERIFYHOST=>2]);
        $raw = curl_exec($ch); $status = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
        $data = is_string($raw) ? json_decode($raw, true) : null;
        if ($status !== 200 && is_array($data)) error_log('WhatsApp OTP: provider error ' . (int)($data['error']['code'] ?? 0));
        return $status === 200 && is_array($data) && !empty($data['messages'][0]['id']);
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
        // Pehle WhatsApp (sasta, DLT nahi). Na pahunche (number WhatsApp par nahi) to SMS.
        $ok = false; $this->lastChannel = null;
        if (wa_ready()) {
            $ok = $this->request(['channel'=>'whatsapp', 'to'=>$mobile, 'template'=>(string)f2s_config('WA_OTP_TEMPLATE'), 'params'=>[$code], 'button'=>true, 'otp'=>$code]);
            if ($ok) $this->lastChannel = 'whatsapp';
        }
        if (!$ok && (f2s_sms_ready() || ($this->transport && !wa_ready()))) {
            $ok = $this->request(f2s_mode() === 'otp'
                ? ['route'=>'otp', 'numbers'=>$mobile, 'variables_values'=>$code, 'otp'=>$code]
                : ['mobile'=>$mobile, 'otp_id'=>(string)f2s_config('FAST2SMS_LOGIN_ID'), 'otp'=>$code,
                   'otp_length'=>6, 'otp_expiry'=>5]);
            if ($ok) $this->lastChannel = 'sms';
        }
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
        if (!f2s_mobile($mobile) || !preg_match('/^[0-9]{4,6}$/D', (string)$code)) return false;
        [$wa, $sms] = f2s_handover_templates($purpose);
        if ($wa === '' && $sms === '') return false;
        $this->install();
        // At most one attempt per event per day; duplicate job creation never resends.
        if (!$this->take('event:'.$purpose.':'.$reference, 1, 86400)
            || !$this->take('notify:'.$mobile, 10, 3600)
            || !$this->take('all-day', max(1,(int)f2s_config('FAST2SMS_DAILY_LIMIT',300)),86400)) return false;
        // WhatsApp utility template: {{1}} = code, {{2}} = booking number (Maakit ka naam template me)
        if ($wa !== '' && $this->request(['channel'=>'whatsapp', 'to'=>$mobile, 'template'=>$wa, 'params'=>[(string)$code, (string)$reference], 'button'=>false, 'otp'=>(string)$code])) return true;
        if ($sms === '') return false;
        return $this->request(['mobile'=>$mobile, 'otp_id'=>$sms, 'otp'=>(string)$code,
            'otp_length'=>strlen((string)$code), 'otp_expiry'=>10080,
            'variables_values'=>'{otp}|'.$reference]);
    }
}
/** [whatsapp template, sms template id] for pickup/delivery. Bina-DLT SMS route me SMS nahi
 *  ("Your OTP: 4821" bina naam/booking ke paane wale ko dhokha lagega). */
function f2s_handover_templates($purpose) {
    $wa  = wa_ready(false) ? (string)f2s_config($purpose === 'pickup' ? 'WA_PICKUP_TEMPLATE' : 'WA_DELIVERY_TEMPLATE') : '';
    $sms = f2s_mode() !== 'otp' ? (string)f2s_config($purpose === 'pickup' ? 'FAST2SMS_PICKUP_ID' : 'FAST2SMS_DELIVERY_ID') : '';
    return [$wa, $sms];
}
/** Delivery SMS never rolls back a saved order. No code/phone/provider payload logged. */
function f2s_notify(PDO $pdo, $mobile, $purpose, $reference, $code) {
    if (!f2s_enabled()) return false;
    [$wa, $sms] = f2s_handover_templates($purpose);
    if ($wa === '' && $sms === '') return false;
    try { $ok = (new Fast2SmsOtp($pdo))->notify($mobile,$purpose,$reference,$code); }
    catch (Throwable $e) { $ok = false; }
    if (!$ok) error_log('Fast2SMS: delivery SMS not sent; existing handover flow remains available.');
    return $ok;
}
