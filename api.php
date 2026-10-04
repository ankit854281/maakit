<?php
// ============================================================
// Maakit — chhote kaam (JS se pukare jaate hain)
// Sirf wahi cheezein jo bina login ke surakshit hain.
// ============================================================
require_once __DIR__ . '/inc/fn.php';
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

$a = get('a', post('a'));

// kabhi-kabhi purane record saaf kar dete hain (jagah ka data rakhna nahi hai)
if (random_int(1, 40) === 1) {
    try { $pdo->query("DELETE FROM live_tracks WHERE updated_at < NOW() - INTERVAL 1 DAY"); } catch (Throwable $e) {}
}

// ---- log: logon ne kya dhoondha aur mila ya nahi ----
// (isse admin ko pata chalta hai ki kaun sa saaman rakhna hai)
if ($a === 'srch' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $q = mb_strtolower(trim(post('q')));
    $hits = (int)post('hits');
    if (mb_strlen($q) >= 2 && mb_strlen($q) <= 60) {
        // ek session me 40 se zyada nahi
        $_SESSION['sl'] = (int)($_SESSION['sl'] ?? 0);
        if ($_SESSION['sl'] < 40) {
            $_SESSION['sl']++;
            try {
                $pdo->prepare("INSERT INTO search_log (q, hits, times) VALUES (?,?,1)
                               ON DUPLICATE KEY UPDATE times = times + 1, hits = VALUES(hits)")
                    ->execute([$q, $hits]);
            } catch (Throwable $e) {}
        }
    }
    echo json_encode(['ok' => 1]); exit;
}

// ---- banner dabaya gaya ----
if ($a === 'bclick' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)post('id');
    if ($id) { try { $pdo->prepare("UPDATE banners SET clicks = clicks + 1 WHERE id=?")->execute([$id]); } catch (Throwable $e) {} }
    echo json_encode(['ok' => 1]); exit;
}

// ---- delivery partner apni jagah bhejta hai (har ~25 second) ----
if ($a === 'ping' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $u = user();
    if (!$u || !in_array($u['role'], ['delivery', 'admin'], true)) {
        http_response_code(403); echo json_encode(['ok' => 0]); exit;
    }
    $oid = (int)post('o');
    $lat = post('lat'); $lng = post('lng');
    if (!$oid || !is_numeric($lat) || !is_numeric($lng) || abs((float)$lat) > 90 || abs((float)$lng) > 180) {
        http_response_code(400); echo json_encode(['ok' => 0]); exit;
    }
    // sirf apne hi order ka
    $c = $pdo->prepare("SELECT id FROM orders WHERE id=? AND (delivery_user=? OR ?='admin')
                        AND status IN ('Assign','Pickup')");
    $c->execute([$oid, $u['id'], $u['role']]);
    if (!$c->fetch()) { http_response_code(403); echo json_encode(['ok' => 0]); exit; }

    $pdo->prepare("INSERT INTO live_tracks (order_id, user_id, lat, lng, acc)
                   VALUES (?,?,?,?,?)
                   ON DUPLICATE KEY UPDATE lat=VALUES(lat), lng=VALUES(lng), acc=VALUES(acc),
                                           user_id=VALUES(user_id), updated_at=NOW()")
        ->execute([$oid, $u['id'], round((float)$lat, 7), round((float)$lng, 7), (int)post('acc') ?: null]);
    echo json_encode(['ok' => 1]); exit;
}

// ---- grahak apne order ki gaadi kahan hai, ye poochhta hai ----
if ($a === 'where') {
    $no  = strtoupper(trim(get('no')));
    $mob = preg_replace('/\D/', '', get('m'));
    if ($no === '' || strlen($mob) !== 10) { http_response_code(400); echo json_encode(['ok' => 0]); exit; }
    $_SESSION['wq'] = (int)($_SESSION['wq'] ?? 0);
    if ($_SESSION['wq']++ > 400) { http_response_code(429); echo json_encode(['ok' => 0]); exit; }

    $st = $pdo->prepare("SELECT o.id, o.status, o.lat AS dlat, o.lng AS dlng,
                                t.lat, t.lng, t.updated_at, u.name AS rider
                         FROM orders o
                         LEFT JOIN live_tracks t ON t.order_id = o.id
                         LEFT JOIN users u ON u.id = o.delivery_user
                         WHERE o.order_no=? AND o.mobile=? LIMIT 1");
    $st->execute([$no, $mob]);
    $r = $st->fetch();
    if (!$r) { http_response_code(404); echo json_encode(['ok' => 0]); exit; }

    $live = false; $age = null;
    if ($r['lat'] !== null && in_array($r['status'], ['Assign', 'Pickup'], true)) {
        $age = time() - strtotime($r['updated_at']);
        $live = $age < 180;                       // 3 minute tak taza maante hain
    }
    echo json_encode([
        'ok'     => 1,
        'live'   => $live,
        'status' => $r['status'],
        'rider'  => $r['rider'],
        'lat'    => $live ? (float)$r['lat'] : null,
        'lng'    => $live ? (float)$r['lng'] : null,
        'dlat'   => $r['dlat'] !== null ? (float)$r['dlat'] : null,
        'dlng'   => $r['dlng'] !== null ? (float)$r['dlng'] : null,
        'age'    => $age,
    ]); exit;
}

http_response_code(400);
echo json_encode(['ok' => 0]);
