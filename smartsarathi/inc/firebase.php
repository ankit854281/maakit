<?php
// Firebase phone identity only. No service-account secret is sent to the browser.
function sr_firebase_enabled(): bool {
    return defined('SR_FIREBASE_ENABLED') && SR_FIREBASE_ENABLED === true;
}
function sr_firebase_config(): array {
    return ['apiKey'=>defined('SR_FIREBASE_API_KEY') ? SR_FIREBASE_API_KEY : '',
        'authDomain'=>defined('SR_FIREBASE_AUTH_DOMAIN') ? SR_FIREBASE_AUTH_DOMAIN : '',
        'projectId'=>defined('SR_FIREBASE_PROJECT_ID') ? SR_FIREBASE_PROJECT_ID : '',
        'appId'=>defined('SR_FIREBASE_APP_ID') ? SR_FIREBASE_APP_ID : ''];
}
function sr_identity_dir(): string {
    if (!defined('SR_IDENTITY_PRIVATE_DIR')) throw new RuntimeException('Private directory not configured');
    $dir = realpath(SR_IDENTITY_PRIVATE_DIR);
    $root = realpath(__DIR__.'/../..');
    $web = realpath($_SERVER['DOCUMENT_ROOT'] ?? '') ?: $root;
    if (!$dir || !is_writable($dir)) throw new RuntimeException('Private directory unavailable');
    foreach ([$root, $web] as $r) {
        if ($dir === $r || str_starts_with($dir, $r.DIRECTORY_SEPARATOR)) throw new RuntimeException('Directory must be outside web root');
    }
    return $dir;
}
function sr_firebase_certificates(): array {
    $path = sr_identity_dir().'/firebase-certificates.json';
    $cached = json_decode((string)@file_get_contents($path), true);
    if (is_array($cached) && ($cached['until'] ?? 0) > time() && is_array($cached['keys'] ?? null)) return $cached['keys'];
    // Only this fixed Google endpoint is used; never a JWT-supplied URL.
    $curl = curl_init('https://www.googleapis.com/robot/v1/metadata/x509/securetoken@system.gserviceaccount.com');
    $maxAge = 300;
    curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_CONNECTTIMEOUT=>3, CURLOPT_TIMEOUT=>8,
        CURLOPT_FOLLOWLOCATION=>false, CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,
        CURLOPT_HEADERFUNCTION=>function($ch, $line) use (&$maxAge) {
            if (preg_match('/^cache-control:.*max-age=(\d+)/i', $line, $m)) $maxAge = min(21600, (int)$m[1]);
            return strlen($line);
        }]);
    $body = curl_exec($curl); $code = curl_getinfo($curl, CURLINFO_HTTP_CODE); curl_close($curl);
    $keys = is_string($body) ? json_decode($body, true) : null;
    if ($code !== 200 || !is_array($keys) || !$keys) throw new RuntimeException('Firebase certificates unavailable');
    $tmp = tempnam(sr_identity_dir(), 'cert-');
    if ($tmp === false) throw new RuntimeException('Certificate cache unavailable');
    chmod($tmp, 0600);
    file_put_contents($tmp, json_encode(['until'=>time()+$maxAge, 'keys'=>$keys]), LOCK_EX);
    rename($tmp, $path);
    return $keys;
}
function sr_jwt_part(string $part): string {
    if ($part === '' || !preg_match('/^[A-Za-z0-9_-]+$/D', $part)) throw new RuntimeException('Invalid token');
    $decoded = base64_decode(strtr($part, '-_', '+/'), true);
    if ($decoded === false) throw new RuntimeException('Invalid token');
    return $decoded;
}
// Keys and clock are explicit inputs so adversarial signed-token cases can be tested offline.
function sr_verify_phone_token(string $token, string $project, array $keys, int $now): array {
    if ($project === '' || strlen($token) > 12000) throw new RuntimeException('Invalid token');
    $parts = explode('.', $token);
    if (count($parts) !== 3) throw new RuntimeException('Invalid token');
    $head = json_decode(sr_jwt_part($parts[0]), true, 32, JSON_THROW_ON_ERROR);
    $data = json_decode(sr_jwt_part($parts[1]), true, 32, JSON_THROW_ON_ERROR);
    if (!is_array($head) || !is_array($data) || ($head['alg'] ?? '') !== 'RS256'
        || !is_string($head['kid'] ?? null) || !isset($keys[$head['kid']])) throw new RuntimeException('Invalid signing key');
    if (openssl_verify($parts[0].'.'.$parts[1], sr_jwt_part($parts[2]), $keys[$head['kid']], OPENSSL_ALGO_SHA256) !== 1) throw new RuntimeException('Invalid signature');
    foreach (['exp','iat','auth_time'] as $key) if (!is_int($data[$key] ?? null)) throw new RuntimeException('Invalid time');
    if ($data['exp'] <= $now || $data['iat'] > $now+30 || $data['auth_time'] > $now+30
        || $data['auth_time'] < $now-300 || $data['iat'] < $data['auth_time']-30
        || ($data['aud'] ?? '') !== $project || ($data['iss'] ?? '') !== 'https://securetoken.google.com/'.$project
        || !is_string($data['sub'] ?? null) || strlen($data['sub']) < 1 || strlen($data['sub']) > 128
        || ($data['firebase']['sign_in_provider'] ?? '') !== 'phone'
        || !is_string($data['phone_number'] ?? null) || !preg_match('/^\+91[6-9][0-9]{9}$/D', $data['phone_number'])) throw new RuntimeException('Invalid phone identity');
    return ['uid'=>$data['sub'], 'mobile'=>substr($data['phone_number'], 3)];
}
