<?php
require_once __DIR__.'/inc/fn.php';
require_once __DIR__.'/inc/identity.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, no-store');
if (!sr_firebase_enabled() || $_SERVER['REQUEST_METHOD']!=='POST' || !csrf_ok()) {
    http_response_code(403); echo json_encode(['ok'=>false]); exit;
}
try {
    if (try_blocked($pdo,'firebase:'.($_SERVER['REMOTE_ADDR'] ?? ''))) throw new RuntimeException('Rate limit');
    $identity=sr_verify_phone_token((string)post('token'),sr_firebase_config()['projectId'],sr_firebase_certificates(),time());
    sr_identity_signin($pdo,$identity);
    try_ok($pdo,'firebase:'.($_SERVER['REMOTE_ADDR'] ?? ''));
    echo json_encode(['ok'=>true,'next'=>u(sr_home_path($pdo))]);
} catch(Throwable $e) {
    // Do not log tokens, mobile numbers, or provider responses.
    error_log('Smart Sarathi: Firebase login rejected');
    http_response_code(401);echo json_encode(['ok'=>false]);
}
