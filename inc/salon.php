<?php
// ================= सैलून (नाई / पार्लर) के काम की functions =================

function salon_modes() {
    return ['auto' => 'खुली है', 'busy' => 'व्यस्त है', 'off' => 'आज सेवा उपलब्ध नहीं'];
}

function salon_get(PDO $pdo, $id) {
    $st = $pdo->prepare("SELECT *, (salon_updated IS NOT NULL AND salon_updated > (NOW() - INTERVAL 180 MINUTE)) AS fresh
                         FROM businesses WHERE id=? AND salon_on=1 AND status='approved'");
    $st->execute([(int)$id]);
    return $st->fetch();
}

function salon_services(PDO $pdo, $bid, $only_active = true) {
    $sql = "SELECT * FROM services WHERE business_id=?" . ($only_active ? " AND active=1" : "") . " ORDER BY id";
    $st = $pdo->prepare($sql); $st->execute([(int)$bid]);
    return $st->fetchAll();
}

// अभी दुकान चालू है या नहीं (समय + mode दोनों देखकर)
function salon_live($b) {
    if (!$b || $b['mode'] === 'off') return false;
    $now = date('H:i:s');
    if ($b['open_time'] && $b['close_time'] && ($now < $b['open_time'] || $now > $b['close_time'])) return false;
    return !empty($b['fresh']) || $b['mode'] === 'auto';
}

function salon_status_label($b) {
    if (!$b) return ['⚪', 'उपलब्ध नहीं', 'tag-off'];
    if ($b['mode'] === 'off') return ['⚪', 'आज सेवा उपलब्ध नहीं', 'tag-off'];
    $now = date('H:i:s');
    if ($b['open_time'] && $now < $b['open_time']) return ['⚪', 'आज ' . salon_hm($b['open_time']) . ' से', 'tag-off'];
    if ($b['close_time'] && $now > $b['close_time']) return ['⚪', 'कल ' . salon_hm($b['open_time']) . ' से', 'tag-off'];
    if ($b['mode'] === 'busy') return ['🟡', 'व्यस्त है', 'tag-gold'];
    return ['🟢', 'खुली है', 'tag-live'];
}

function salon_hm($t) {
    if (!$t) return '';
    $ts = strtotime($t);
    $h = (int)date('G', $ts); $m = date('i', $ts);
    $ampm = $h < 12 ? 'सुबह' : ($h < 16 ? 'दोपहर' : ($h < 20 ? 'शाम' : 'रात'));
    $h12 = $h % 12; if ($h12 === 0) $h12 = 12;
    return $ampm . ' ' . $h12 . ($m !== '00' ? ':' . $m : '') . ' बजे';
}

/**
 * कुर्सियों और इंतज़ार की पूरी तस्वीर।
 * लौटाता है: chairs (हर कुर्सी पर कौन, कब तक), waiting (हर एक का अनुमानित समय)
 */
function salon_board(PDO $pdo, $b) {
    $bid = (int)$b['id'];
    $workers = max(1, (int)$b['workers']);

    $st = $pdo->prepare("SELECT * FROM bookings WHERE business_id=? AND DATE(created_at)=CURDATE()
                         AND status IN ('in_chair','waiting') ORDER BY FIELD(status,'in_chair','waiting'), id");
    $st->execute([$bid]);
    $rows = $st->fetchAll();

    $chairs = array_fill(1, $workers, null);
    $free_at = array_fill(1, $workers, time());
    $waiting = [];

    foreach ($rows as $r) {
        if ($r['status'] === 'in_chair') {
            $c = (int)$r['chair_no'];
            if ($c < 1 || $c > $workers || $chairs[$c] !== null) {
                for ($i = 1; $i <= $workers; $i++) { if ($chairs[$i] === null) { $c = $i; break; } }
            }
            $start = $r['started_at'] ? strtotime($r['started_at']) : time();
            $end = $start + ((int)$r['minutes'] * 60);
            $r['ends_at'] = $end;
            $r['left_min'] = max(0, (int)ceil(($end - time()) / 60));
            $chairs[$c] = $r;
            $free_at[$c] = max(time(), $end);
        } else {
            $waiting[] = $r;
        }
    }

    foreach ($waiting as $k => $r) {
        $c = 1; $min = $free_at[1];
        for ($i = 2; $i <= $workers; $i++) { if ($free_at[$i] < $min) { $min = $free_at[$i]; $c = $i; } }
        $waiting[$k]['eta'] = $min;
        $waiting[$k]['wait_min'] = max(0, (int)ceil(($min - time()) / 60));
        $waiting[$k]['chair_guess'] = $c;
        $free_at[$c] = $min + ((int)$r['minutes'] * 60);
    }

    return ['chairs' => $chairs, 'waiting' => $waiting, 'workers' => $workers];
}

function salon_wait_text($board) {
    $n = count($board['waiting']);
    if ($n === 0) {
        foreach ($board['chairs'] as $c) { if ($c) return 'कुर्सी खाली होने वाली है'; }
        return 'कुर्सी खाली है — अभी आ जाइए';
    }
    $last = end($board['waiting']);
    return $n . ' इंतज़ार में · करीब ' . max(5, (int)$last['wait_min']) . ' मिनट';
}

function salon_ref() { return strtoupper(bin2hex(random_bytes(3))); }

// ---------- दुकानदार का लॉगिन (कोड + 90 दिन का token) ----------
function shop_login_business(PDO $pdo) {
    if (!empty($_COOKIE['mk_shop'])) {
        $st = $pdo->prepare("SELECT b.* FROM shop_tokens t JOIN businesses b ON b.id=t.business_id
                             WHERE t.token=? AND t.expires > NOW() AND b.salon_on=1");
        $st->execute([$_COOKIE['mk_shop']]);
        if ($row = $st->fetch()) {
            $st2 = $pdo->prepare("SELECT *, (salon_updated IS NOT NULL AND salon_updated > (NOW() - INTERVAL 180 MINUTE)) AS fresh FROM businesses WHERE id=?");
            $st2->execute([$row['id']]);
            return $st2->fetch();
        }
    }
    return null;
}
function shop_start_session(PDO $pdo, $bid) {
    $tok = bin2hex(random_bytes(24));
    $pdo->prepare("INSERT INTO shop_tokens (business_id, token, expires) VALUES (?,?, NOW() + INTERVAL 90 DAY)")->execute([$bid, $tok]);
    setcookie('mk_shop', $tok, [
        'expires' => time() + 90 * 86400, 'path' => '/', 'samesite' => 'Lax',
        'secure' => !empty($_SERVER['HTTPS']), 'httponly' => true,
    ]);
}
function shop_logout(PDO $pdo) {
    if (!empty($_COOKIE['mk_shop'])) {
        $pdo->prepare("DELETE FROM shop_tokens WHERE token=?")->execute([$_COOKIE['mk_shop']]);
        setcookie('mk_shop', '', ['expires' => time() - 3600, 'path' => '/']);
    }
}
function shop_touch(PDO $pdo, $bid) {
    $pdo->prepare("UPDATE businesses SET salon_updated=NOW() WHERE id=?")->execute([(int)$bid]);
}
function make_access_code() {
    $a = strtoupper(substr(str_shuffle('ABCDEFGHJKLMNPQRSTUVWXYZ'), 0, 3));
    return $a . '-' . random_int(1000, 9999);
}

// ग्राहक का रिकॉर्ड (कितनी बार आया, कितनी बार नहीं आया)
function salon_customer(PDO $pdo, $mobile) {
    $st = $pdo->prepare("SELECT * FROM salon_customers WHERE mobile=?");
    $st->execute([$mobile]);
    return $st->fetch();
}
function salon_customer_badge($c) {
    if (!$c) return ['नए ग्राहक', 'tag-off'];
    if ((int)$c['no_shows'] >= 3) return [(int)$c['no_shows'] . ' बार नहीं आए', 'tag-gold'];
    if ((int)$c['visits'] >= 3) return [(int)$c['visits'] . ' बार आ चुके हैं', 'tag-live'];
    return [((int)$c['visits'] ?: 0) . ' बार आए', 'tag-off'];
}
