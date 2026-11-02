<?php
declare(strict_types=1);
require_once __DIR__ . '/madhav_core.php';
require_once __DIR__ . '/ai_provider.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
function madhav_out(int $status, array $payload): never {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}
// Same-origin browser POSTs only; hosting still needs WAF and network controls.
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$host = $_SERVER['HTTP_HOST'] ?? '';
if ($origin !== '' && $host !== '') {
    $originHost = parse_url($origin, PHP_URL_HOST);
    $requestHost = explode(':', $host)[0];
    if (!is_string($originHost) || strcasecmp($originHost, $requestHost) !== 0) {
        madhav_out(403, ['error' => 'origin']);
    }
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') madhav_out(405, ['error' => 'method']);
if (!str_starts_with(strtolower((string)($_SERVER['CONTENT_TYPE'] ?? '')), 'application/json')) {
    madhav_out(415, ['error' => 'content_type']);
}
if ((int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 12000) madhav_out(413, ['error' => 'too_large']);
$raw = file_get_contents('php://input', false, null, 0, 12001);
if (!is_string($raw) || strlen($raw) > 12000) madhav_out(413, ['error' => 'too_large']);
$input = json_decode($raw, true);
if (!is_array($input)) madhav_out(400, ['error' => 'json']);
try { madhav_validate($input); } catch (InvalidArgumentException $e) {
    madhav_out(400, ['error' => 'validation']);
}
// Optional inference can be costly: rate-limit before each model call.
function madhav_rate_ok(string $ip): bool {
    $key = hash('sha256', 'madhav|' . $ip . '|' . gmdate('Y-m-d-H'));
    $file = sys_get_temp_dir() . '/madhav-' . $key . '.count';
    $fp = @fopen($file, 'c+');
    if (!$fp || !flock($fp, LOCK_EX)) { if ($fp) fclose($fp); return false; }
    $hits = (int)trim((string)stream_get_contents($fp));
    if ($hits >= 10) { flock($fp, LOCK_UN); fclose($fp); return false; }
    rewind($fp); ftruncate($fp, 0); fwrite($fp, (string)($hits + 1)); fflush($fp);
    flock($fp, LOCK_UN); fclose($fp);
    return true;
}
$inference = null;
if (madhav_ai_enabled() && !madhav_crisis((string)$input['question'])) {
    if (!madhav_rate_ok((string)($_SERVER['REMOTE_ADDR'] ?? ''))) {
        madhav_out(429, ['error' => 'rate_limit']);
    }
    $inference = 'madhav_openai';
}
try { madhav_out(200, madhav_reply($input, $inference)); }
catch (Throwable $e) { madhav_out(503, ['error' => 'unavailable']); }
