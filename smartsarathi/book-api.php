<?php
// स्मार्ट सारथी — ऐप की बुकिंग API (JSON). ghar.php ka JavaScript yahi bulata hai.
require_once __DIR__ . '/inc/public.php';
require_once __DIR__ . '/inc/booking.php';
require_once __DIR__ . '/inc/places.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');

function sr_json(array $d, int $code = 200): void { http_response_code($code); echo json_encode($d, JSON_UNESCAPED_UNICODE); exit; }

$a = (string)($_GET['a'] ?? $_POST['a'] ?? '');
$acc = sr_account($pdo);
$post = $_SERVER['REQUEST_METHOD'] === 'POST';
if ($post && !csrf_ok()) sr_json(['ok' => false, 'error' => t('Page expired. Please reload.', 'पेज पुराना हो गया। दोबारा खोलिए।'), 'reload' => true], 403);

try {
    if ($a === 'quote' && $post) {
        if (!sr_public_limit('quote', 300)) sr_json(['ok' => false, 'error' => t('Too many requests.', 'बहुत बार कोशिश हुई।')], 429);
        [$ok, $out] = sr_quote((string)post('pick_geo'), (string)post('drop_geo'));
        sr_json($ok ? ['ok' => true] + $out : ['ok' => false, 'error' => $out]);
    }
    // Jagah khoj — bina login (pehle kiraya, login baad me)
    if ($a === 'places') {
        if (!sr_public_limit('places', 600)) sr_json(['ok' => false, 'error' => t('Too many searches. Wait a little.', 'बहुत खोज हुई। थोड़ा रुकिए।')], 429);
        $lat = (float)($_GET['lat'] ?? 0); $lng = (float)($_GET['lng'] ?? 0);
        $near = ($lat > 6 && $lat < 37 && $lng > 68 && $lng < 98) ? [$lat, $lng] : null;
        $tok = preg_replace('/[^A-Za-z0-9_-]/', '', (string)($_GET['tok'] ?? ''));
        sr_json(['ok' => true, 'list' => sr_places_search($pdo, (string)($_GET['q'] ?? ''), $near, substr($tok, 0, 64))]);
    }
    if ($a === 'place') {
        if (!sr_public_limit('place', 300)) sr_json(['ok' => false, 'error' => t('Too many searches. Wait a little.', 'बहुत खोज हुई। थोड़ा रुकिए।')], 429);
        sr_places_upgrade($pdo);
        if (isset($_GET['lid'])) { $pdo->prepare('UPDATE sr_places SET uses=uses+1 WHERE id=? AND active=1')->execute([(int)$_GET['lid']]); sr_json(['ok' => true]); }
        $tok = preg_replace('/[^A-Za-z0-9_-]/', '', (string)($_GET['tok'] ?? ''));
        $p = sr_place_google($pdo, (string)($_GET['id'] ?? ''), substr($tok, 0, 64), (string)($_GET['main'] ?? ''));
        sr_json($p ? ['ok' => true] + $p : ['ok' => false, 'error' => t('Place not found. Set it on the map.', 'जगह नहीं मिली। नक़्शे पर पिन लगाइए।')]);
    }
    if (!$acc) sr_json(['ok' => false, 'login' => true, 'error' => t('Please log in with your mobile number.', 'मोबाइल नंबर से लॉगिन कीजिए।')], 401);

    if ($a === 'book' && $post) {
        if (try_blocked($pdo, 'book:' . $acc['id'])) sr_json(['ok' => false, 'error' => t('Too many attempts. Wait a little.', 'बहुत कोशिश हुई। थोड़ा रुकिए।')], 429);
        [$ok, $out] = sr_book($pdo, $acc, $_POST);
        if ($ok) { try_ok($pdo, 'book:' . $acc['id']); sr_json(['ok' => true] + $out); }
        sr_json(['ok' => false, 'error' => $out], 422);
    }
    if ($a === 'status') {
        sr_offers_sweep($pdo);   // grahak har 8 sec poochhta hai — isi se purane offer agle rider ko jaate hain
        $j = sr_my_job($pdo, $acc, (string)($_GET['no'] ?? ''));
        if (!$j) sr_json(['ok' => false, 'error' => t('Booking not found.', 'बुकिंग नहीं मिली।')], 404);
        sr_json(['ok' => true, 'job' => sr_job_view($j, $acc)]);
    }
    if ($a === 'mine') {
        sr_json(['ok' => true, 'jobs' => array_map(fn($j) => [
            'job_no' => $j['job_no'], 'status' => $j['status'], 'fare' => (int)$j['fee'],
            'drop' => $j['drop_address'], 'when' => date('d M, h:i A', strtotime($j['created_at'])),
        ], sr_my_jobs($pdo, $acc))]);
    }
    if ($a === 'cancel' && $post) {
        $ok = sr_cancel_mine($pdo, $acc, (string)post('no'));
        sr_json($ok ? ['ok' => true] : ['ok' => false, 'error' => t('This booking can no longer be cancelled here. Please call.', 'यह बुकिंग अब यहाँ से रद्द नहीं हो सकती। फ़ोन कीजिए।')], $ok ? 200 : 409);
    }
    sr_json(['ok' => false, 'error' => 'unknown'], 400);
} catch (Throwable $e) {
    error_log('Sarathi book-api ' . $a . ': ' . get_class($e) . ' ' . $e->getMessage());
    sr_json(['ok' => false, 'error' => t('Something went wrong. Please call us.', 'कुछ गड़बड़ हुई। फ़ोन कीजिए।')], 500);
}
