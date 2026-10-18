<?php
// ============================================================
// Maakit Jyotish — AI Jyotishi (POST JSON {lang, chart, turns, ctx?, device?, nolog?})
// Jawab: {text, left}  ya  {crisis:true}  ya  {err: off|bad|limit|fail}
//
// Sawaal tabhi jy_events me likha jaata hai jab usage sharing on ho —
// Council mode ka personal text kabhi log nahi hota. "Mat bhejiye" (nolog)
// chune par bhi text save nahi hota.
// Pareshani ke shabd mile to: AI ko nahi bheja, text nahi likha,
// sirf ek ginti — aur phone madad ka panna khol deta hai.
// ============================================================
declare(strict_types=1);
date_default_timezone_set('Asia/Kolkata');
require_once __DIR__ . '/ai_lib.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function jy_out(int $code, array $d): never { http_response_code($code); echo json_encode($d, JSON_UNESCAPED_UNICODE); exit; }

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') jy_out(405, ['err' => 'bad']);
$in = json_decode((string)file_get_contents('php://input', false, null, 0, 40000), true);
try { $c = jy_clean(is_array($in) ? $in : []); } catch (InvalidArgumentException $e) { jy_out(400, ['err' => 'bad']); }
$q = end($c['turns'])['content'];
$ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');

try { $db = jy_db(); } catch (PDOException $e) { jy_out(503, ['err' => jy_ai_ready() ? 'fail' : 'off']); }

if (jy_crisis($q)) {
    try { if (jy_event_ok($db, $ip)) jy_log($db, 'crisis', []); } catch (PDOException $e) {}
    jy_out(200, ['crisis' => true]);
}
// Personal Council questions are sent to the AI provider to answer, but are
// never copied into Maakit's Jyotish usage log.
if (!$c['nolog'] && $c['mode'] !== 'council') {
    try { if (jy_event_ok($db, $ip)) jy_log($db, 'ai_q', ['device' => $c['device'], 'lang' => $c['lang'], 'topic' => jy_topic($q), 'q' => $q]); } catch (PDOException $e) {}
}
if (!jy_ai_ready()) jy_out(503, ['err' => 'off']);
try { if (!jy_take($db, $ip, $left)) jy_out(429, ['err' => 'limit']); } catch (PDOException $e) { jy_out(503, ['err' => 'fail']); }

try { jy_out(200, ['text' => jy_ask($c), 'left' => $left]); }
catch (Throwable $e) { jy_out(502, ['err' => 'fail']); }
