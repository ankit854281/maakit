<?php
// ============================================================
// Maakit Jyotish — AI Jyotishi (POST JSON {lang, chart, turns})
// Jawab: {text, left}  ya  {err: off|bad|limit|fail}
// ============================================================
declare(strict_types=1);
date_default_timezone_set('Asia/Kolkata');
require_once __DIR__ . '/ai_lib.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function jy_out(int $code, array $d): never { http_response_code($code); echo json_encode($d, JSON_UNESCAPED_UNICODE); exit; }

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') jy_out(405, ['err' => 'bad']);
if (!jy_ai_ready()) jy_out(503, ['err' => 'off']);
$in = json_decode((string)file_get_contents('php://input', false, null, 0, 40000), true);
try { $c = jy_clean(is_array($in) ? $in : []); } catch (InvalidArgumentException $e) { jy_out(400, ['err' => 'bad']); }

try {
    $db = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]);
    if (!jy_take($db, (string)($_SERVER['REMOTE_ADDR'] ?? ''), $left)) jy_out(429, ['err' => 'limit']);
} catch (PDOException $e) { jy_out(503, ['err' => 'fail']); }

try { jy_out(200, ['text' => jy_ask($c), 'left' => $left]); }
catch (Throwable $e) { jy_out(502, ['err' => 'fail']); }
