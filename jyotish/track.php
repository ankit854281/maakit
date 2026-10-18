<?php
// ============================================================
// Maakit Jyotish — kaun sa hissa kitna use hua (POST JSON {k, device, ...})
//
// Sirf ye likhta hai: kya khola/kiya, shehar, rashi, lagna, nakshatra,
// janm ka saal, gender, milan ke ank, upaya ki category. Naam, poori
// janm-tareekh/samay aur check-in ka mood KABHI nahi aata.
// "forget" = is phone ka saara log mita do.
// ============================================================
declare(strict_types=1);
date_default_timezone_set('Asia/Kolkata');
require_once __DIR__ . '/ai_lib.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
function jt_out(int $code, array $d): never { http_response_code($code); echo json_encode($d); exit; }

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') jt_out(405, ['ok' => false]);
$in = json_decode((string)file_get_contents('php://input', false, null, 0, 4000), true);
$k = is_array($in) ? ($in['k'] ?? '') : '';
if (!in_array($k, JY_KINDS, true)) jt_out(400, ['ok' => false]);
try {
    $db = jy_db();
    if ($k === 'forget') {
        $dev = jy_device($in['device'] ?? '');
        if ($dev !== '') $db->prepare('DELETE FROM jy_events WHERE device = ?')->execute([$dev]);
        jt_out(200, ['ok' => true]);
    }
    if (!jy_event_ok($db, (string)($_SERVER['REMOTE_ADDR'] ?? ''))) jt_out(429, ['ok' => false]);
    unset($in['q'], $in['topic']);                    // sawaal sirf ai.php likhta hai
    jy_log($db, $k, $in);
    if (random_int(1, 200) === 1) jy_cleanup($db);
    jt_out(200, ['ok' => true]);
} catch (PDOException $e) { jt_out(503, ['ok' => false]); }
