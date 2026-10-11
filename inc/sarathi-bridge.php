<?php
// ============================================================
//  Maakit → Smart Sarathi (delivery platform)
//
//  Oct 2026 se delivery ka poora kaam Smart Sarathi par hai —
//  riders wahin login karte hain (maakit.in/smartsarathi).
//  Maakit ab Smart Sarathi ka ek "client" hai: order uski API
//  (api/v1.php) par kaam ban kar jaata hai.
//
//  Settings (Admin → Sarathi → "Smart Sarathi से जोड़िए"):
//    sarathi_api_url — jaise https://maakit.in/smartsarathi/api/v1.php
//    sarathi_api_key — Smart Sarathi admin me Maakit client ki chaabi
//    sarathi_auto    — 1 = "Confirm" hote hi order apne aap jaye
//
//  Codes ek hi rehte hain: dukaan wala OTP (orders.pick_otp) aur
//  grahak ka code (orders.code) hi Sarathi ke pick_otp/drop_code
//  bante hain — grahak/dukaan ko kuch naya yaad nahi rakhna.
// ============================================================

function sb_cfg(PDO $pdo) {
    return [
        'url'  => trim((string)setg($pdo, 'sarathi_api_url', '')),
        'key'  => trim((string)setg($pdo, 'sarathi_api_key', '')),
        'auto' => setg($pdo, 'sarathi_auto', '0') === '1',
    ];
}
function sb_ready(PDO $pdo) { $c = sb_cfg($pdo); return $c['url'] !== '' && $c['key'] !== ''; }

/** API call → [ok, body-array or error-text] */
function sb_call(PDO $pdo, $do, array $data = null) {
    $c = sb_cfg($pdo);
    if ($c['url'] === '' || $c['key'] === '') return [false, 'Smart Sarathi जुड़ा नहीं है'];
    if (!preg_match('#^https?://#', $c['url'])) return [false, 'API URL सही नहीं'];
    $url = $c['url'] . (strpos($c['url'], '?') === false ? '?' : '&') . 'do=' . urlencode($do);
    if ($data !== null && $do === 'status') { $url .= '&ref=' . urlencode((string)($data['ref'] ?? '')); $data = null; }
    if (!function_exists('curl_init')) return [false, 'server par curl nahi'];
    $ch = curl_init($url);
    $opt = [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 12, CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $c['key'], 'Accept: application/json', 'Content-Type: application/json']];
    if ($data !== null) { $opt[CURLOPT_POST] = true; $opt[CURLOPT_POSTFIELDS] = json_encode($data, JSON_UNESCAPED_UNICODE); }
    curl_setopt_array($ch, $opt);
    $raw = curl_exec($ch); $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE); $cerr = curl_error($ch);
    curl_close($ch);
    if ($raw === false) { error_log('Sarathi bridge: ' . $cerr); return [false, 'Smart Sarathi से जवाब नहीं आया']; }
    $j = json_decode((string)$raw, true);
    if (!is_array($j)) return [false, "Smart Sarathi: HTTP $code"];
    if (empty($j['ok'])) return [false, (string)($j['error'] ?? "HTTP $code")];
    return [true, $j];
}

/** Ek order Smart Sarathi ko bhejiye. [ok, msg] */
function sb_send(PDO $pdo, $oid) {
    $st = $pdo->prepare("SELECT o.*, b.name AS b_name, b.mobile AS b_mobile, b.village AS b_village
                           FROM orders o LEFT JOIN businesses b ON b.id=o.business_id WHERE o.id=?");
    $st->execute([(int)$oid]);
    $o = $st->fetch();
    if (!$o) return [false, 'ऑर्डर नहीं मिला'];
    if (!empty($o['sarathi_job'])) return [true, 'पहले से भेजा हुआ: ' . $o['sarathi_job']];
    if (in_array($o['status'], ['Delivered', 'Cancel', 'Paisa jama'], true)) return [false, 'यह ऑर्डर पूरा/रद्द है'];

    if (empty($o['pick_otp'])) {
        $o['pick_otp'] = str_pad((string)random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        $pdo->prepare("UPDATE orders SET pick_otp=COALESCE(pick_otp,?) WHERE id=?")->execute([$o['pick_otp'], (int)$o['id']]);
    }
    $cash = stripos((string)$o['payment'], 'upi') === false && stripos((string)$o['payment'], 'paid') === false;
    $total = (int)$o['goods_amount'] + (int)$o['delivery_charge'];
    [$ok, $r] = sb_call($pdo, 'create', [
        'ref'          => $o['order_no'],
        'pick_name'    => $o['b_name'] ?: ($o['shop'] ?: null),
        'pick_mobile'  => $o['b_mobile'] ?: null,
        'pick_address' => trim(($o['shop'] ?: $o['b_name']) . ($o['b_village'] ? ', ' . $o['b_village'] : ($o['market'] ? ', ' . $o['market'] : ''))) ?: null,
        'drop_name'    => $o['customer_name'],
        'drop_mobile'  => $o['mobile'],
        'drop_address' => trim($o['village'] . ($o['landmark'] ? ' — ' . $o['landmark'] : '')),
        'drop_village' => $o['village'],
        'items'        => mb_substr((string)$o['items'], 0, 1900),
        'note'         => trim((string)$o['goods_note'] . ' ' . (string)$o['note']),
        'cod_amount'   => ($cash && $total > 0) ? $total : null,
        'pick_otp'     => preg_match('/^\d{4}$/', (string)$o['pick_otp']) ? $o['pick_otp'] : null,
        'drop_code'    => preg_match('/^\d{4}$/', (string)$o['code']) ? $o['code'] : null,
    ]);
    if (!$ok) return [false, $r];
    $job = $r['job'] ?? [];
    $pdo->prepare("UPDATE orders SET sarathi_job=?, sarathi_status=?, sarathi_checked=NOW(),
                   status=IF(status IN ('Naya','Confirm'),'Assign',status), assigned_at=COALESCE(assigned_at,NOW()) WHERE id=?")
        ->execute([(string)($job['job_no'] ?? ''), (string)($job['status'] ?? 'new'), (int)$o['id']]);
    return [true, 'Smart Sarathi को भेज दिया: ' . ($job['job_no'] ?? '')];
}

/** Smart Sarathi ki halat Maakit ke order par (har order ke liye 60 sec me ek baar) */
function sb_sync(PDO $pdo, array $o, $force = false) {
    if (empty($o['sarathi_job']) || in_array($o['status'], ['Delivered', 'Cancel', 'Paisa jama'], true)) return $o;
    if (!$force && !empty($o['sarathi_checked']) && strtotime($o['sarathi_checked']) > time() - 60) return $o;
    $pdo->prepare("UPDATE orders SET sarathi_checked=NOW() WHERE id=?")->execute([(int)$o['id']]);
    [$ok, $r] = sb_call($pdo, 'status', ['ref' => $o['order_no']]);
    if (!$ok || empty($r['job']['status'])) return $o;
    $s = $r['job']['status'];
    $map = ['assigned' => 'Assign', 'picked' => 'Pickup', 'delivered' => 'Delivered'];
    $new = $map[$s] ?? null;
    $sql = "UPDATE orders SET sarathi_status=?";
    $args = [$s];
    if ($new && $new !== $o['status']) {
        $sql .= ", status=?"; $args[] = $new;
        if ($s === 'picked') $sql .= ", picked_at=COALESCE(picked_at,NOW())";
        if ($s === 'delivered') $sql .= ", picked_at=COALESCE(picked_at,NOW()), delivered_at=COALESCE(delivered_at,NOW())";
        $o['status'] = $new;
    }
    $args[] = (int)$o['id'];
    $pdo->prepare($sql . " WHERE id=?")->execute($args);
    $o['sarathi_status'] = $s;
    return $o;
}

/** "Apne aap" chalu ho to: Confirm hue, abhi tak na bheje order (ek baar me 10) */
function sb_auto(PDO $pdo) {
    if (!sb_ready($pdo) || !sb_cfg($pdo)['auto']) return 0;
    try {
        $ids = $pdo->query("SELECT id FROM orders WHERE status='Confirm' AND sarathi_job IS NULL AND delivery_user IS NULL
                             AND created_at > NOW() - INTERVAL 3 DAY ORDER BY id LIMIT 10")->fetchAll(PDO::FETCH_COLUMN);
    } catch (Throwable $e) { return 0; }
    $n = 0;
    foreach ($ids as $id) { [$ok] = sb_send($pdo, (int)$id); if ($ok) $n++; }
    return $n;
}
