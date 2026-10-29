<?php
// ============================================================
//  जगह खोज (owner: "log jagah search karenge, nahi milegi to
//  wapas chale jayenge" — 29 Oct 2026)
//
//  Kram:
//   1. Bhadohi ki apni jagahein (sr_places) — dafter jodta hai,
//      aur Google se chuni gayi saarvajanik jagahein khud judti hain.
//   2. Google Places (agar config.php me SR_GOOGLE_PLACES_KEY ho) —
//      India ke liye 70,000 khoj/mahina muft (session token ke saath).
//   3. Bina key: Photon + Nominatim (OpenStreetMap), muft.
//  Key kabhi browser ko nahi jaati; har jawab DB me cache hota hai.
//  Kuch na mile tab bhi grahak atakta nahi: app "naam rakhkar
//  naqshe par pin" ka raasta deta hai (customer.js).
// ============================================================

/** Bhadohi ke paas — khoj isi ke aas-paas pehle */
const SR_PLACES_CENTER = [25.395, 82.568];

function sr_places_upgrade(PDO $pdo): void {
    static $done = false; if ($done) return; $done = true;
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS sr_places (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(120) NOT NULL,
            name_hi VARCHAR(120) NOT NULL DEFAULT '',
            alias VARCHAR(255) NOT NULL DEFAULT '',
            area VARCHAR(120) NOT NULL DEFAULT '',
            lat DECIMAL(10,7) NOT NULL, lng DECIMAL(10,7) NOT NULL,
            src VARCHAR(8) NOT NULL DEFAULT 'office',
            ext_id VARCHAR(255) DEFAULT NULL,
            uses INT NOT NULL DEFAULT 0,
            active TINYINT NOT NULL DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_ext (ext_id), KEY (active, uses)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $pdo->exec("CREATE TABLE IF NOT EXISTS sr_geo_cache (
            k CHAR(40) PRIMARY KEY, v MEDIUMTEXT NOT NULL, at DATETIME NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        // Shuruaat: zile ke kasbe (Wikipedia coordinates) — baaki dafter jode
        if (!(int)$pdo->query("SELECT COUNT(*) FROM sr_places")->fetchColumn() && function_exists('sr_work_areas')) {
            $ins = $pdo->prepare("INSERT INTO sr_places (name,name_hi,alias,area,lat,lng,src) VALUES (?,?,?,?,?,?,'office')");
            foreach (sr_work_areas() as $k => [$hi, $en, $lat, $lng]) $ins->execute([$en, $hi, $k, t('Bhadohi district', 'भदोही ज़िला'), $lat, $lng]);
        }
    } catch (Throwable $e) { error_log('Sarathi places upgrade: ' . get_class($e)); }
}

function sr_places_lang(): string { return function_exists('sr_lang') ? (sr_lang() === 'en' ? 'en' : 'hi') : 'en'; }
function sr_places_key(): string { return defined('SR_GOOGLE_PLACES_KEY') ? trim((string)SR_GOOGLE_PLACES_KEY) : ''; }

/** Chhota HTTP: JSON lautata hai ya null. Kabhi exception nahi. */
function sr_http_json(string $method, string $url, array $headers = [], ?array $body = null, int $timeout = 6): ?array {
    if (!function_exists('curl_init')) return null;
    $ch = curl_init($url);
    $h = array_merge(['Accept: application/json', 'User-Agent: SmartSarathi/1.0 (+https://maakit.in/smartsarathi/)'], $headers);
    if ($body !== null) { $h[] = 'Content-Type: application/json'; curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_UNICODE)); }
    curl_setopt_array($ch, [CURLOPT_CUSTOMREQUEST => $method, CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => $h,
        CURLOPT_TIMEOUT => $timeout, CURLOPT_CONNECTTIMEOUT => 4, CURLOPT_FOLLOWLOCATION => false]);
    $out = curl_exec($ch); $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE); curl_close($ch);
    if ($out === false || $code < 200 || $code >= 300) { if ($code) error_log('Sarathi places http ' . $code); return null; }
    $j = json_decode((string)$out, true);
    return is_array($j) ? $j : null;
}

function sr_cache_get(PDO $pdo, string $key, int $days) {
    $q = $pdo->prepare("SELECT v FROM sr_geo_cache WHERE k=? AND at > NOW() - INTERVAL ? DAY");
    $q->execute([sha1($key), $days]); $v = $q->fetchColumn();
    return $v === false ? null : json_decode($v, true);
}
function sr_cache_put(PDO $pdo, string $key, $val): void {
    $pdo->prepare("REPLACE INTO sr_geo_cache (k,v,at) VALUES (?,?,NOW())")->execute([sha1($key), json_encode($val, JSON_UNESCAPED_UNICODE)]);
}

/** Hindi/English dono me milaan: "station", "स्टेशन", "railway" */
function sr_places_local(PDO $pdo, string $q, int $limit = 5): array {
    $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q) . '%';
    $st = $pdo->prepare("SELECT id,name,name_hi,area,lat,lng FROM sr_places WHERE active=1 AND (name LIKE ? OR name_hi LIKE ? OR alias LIKE ?) ORDER BY uses DESC, id LIMIT " . (int)$limit);
    $st->execute([$like, $like, $like]);
    $hi = sr_places_lang() === 'hi';
    return array_map(fn($r) => ['src' => 'l', 'id' => (int)$r['id'],
        'main' => ($hi && $r['name_hi'] !== '') ? $r['name_hi'] : $r['name'], 'sub' => $r['area'],
        'lat' => (float)$r['lat'], 'lng' => (float)$r['lng']], $st->fetchAll());
}

/** Google Places Autocomplete (New). Coordinates nahi deta — chunne par sr_place_google(). */
function sr_places_google(PDO $pdo, string $q, array $near, string $tok): ?array {
    $key = sr_places_key(); if ($key === '') return null;
    $lang = sr_places_lang();
    $ck = 'g1|' . $lang . '|' . round($near[0], 2) . ',' . round($near[1], 2) . '|' . mb_strtolower($q);
    if (($c = sr_cache_get($pdo, $ck, 30)) !== null) return $c;
    $j = sr_http_json('POST', 'https://places.googleapis.com/v1/places:autocomplete', ['X-Goog-Api-Key: ' . $key], [
        'input' => $q, 'sessionToken' => $tok, 'languageCode' => $lang, 'regionCode' => 'in', 'includedRegionCodes' => ['in'],
        'locationBias' => ['circle' => ['center' => ['latitude' => $near[0], 'longitude' => $near[1]], 'radius' => 50000.0]],
    ]);
    if ($j === null) return null;
    $out = [];
    foreach (($j['suggestions'] ?? []) as $s) {
        $p = $s['placePrediction'] ?? null; if (!$p || empty($p['placeId'])) continue;
        $out[] = ['src' => 'g', 'id' => (string)$p['placeId'],
            'main' => (string)($p['structuredFormat']['mainText']['text'] ?? $p['text']['text'] ?? ''),
            'sub' => (string)($p['structuredFormat']['secondaryText']['text'] ?? '')];
        if (count($out) >= 6) break;
    }
    sr_cache_put($pdo, $ck, $out);
    return $out;
}
/** Chuni hui Google jagah ke coordinates (Essentials fields only — sasta) + apni list me yaad */
function sr_place_google(PDO $pdo, string $id, string $tok, string $main): ?array {
    if (!preg_match('/^[A-Za-z0-9_-]{10,250}$/D', $id)) return null;
    $ck = 'gd1|' . $id;
    if (($c = sr_cache_get($pdo, $ck, 90)) === null) {
        $key = sr_places_key(); if ($key === '') return null;
        $url = 'https://places.googleapis.com/v1/places/' . rawurlencode($id) . '?languageCode=' . (sr_places_lang())
             . ($tok !== '' ? '&sessionToken=' . rawurlencode($tok) : '');
        $j = sr_http_json('GET', $url, ['X-Goog-Api-Key: ' . $key, 'X-Goog-FieldMask: location,formattedAddress']);
        if (!$j || !isset($j['location']['latitude'])) return null;
        $c = ['lat' => (float)$j['location']['latitude'], 'lng' => (float)$j['location']['longitude'], 'addr' => (string)($j['formattedAddress'] ?? '')];
        sr_cache_put($pdo, $ck, $c);
    }
    // Saarvajanik jagah (dukaan, mandir, school…) — agli baar bina Google ke mil jaye
    $main = mb_substr(trim($main), 0, 120);
    if ($main !== '') try {
        $pdo->prepare("INSERT INTO sr_places (name,name_hi,area,lat,lng,src,ext_id,uses) VALUES (?,?,?,?,?,'google',?,1)
                       ON DUPLICATE KEY UPDATE uses=uses+1")
            ->execute([$main, preg_match('/\p{Devanagari}/u', $main) ? $main : '', mb_substr($c['addr'], 0, 120), $c['lat'], $c['lng'], 'g:' . $id]);
    } catch (Throwable $e) { error_log('Sarathi place learn: ' . get_class($e)); }
    return $c;
}

/** Bina Google key: OpenStreetMap (Photon, phir Nominatim). Coordinates saath me. */
function sr_places_osm(PDO $pdo, string $q, array $near): array {
    $lang = sr_places_lang();
    $ck = 'o1|' . $lang . '|' . mb_strtolower($q);
    if (($c = sr_cache_get($pdo, $ck, 30)) !== null) return $c;
    $out = [];
    $j = sr_http_json('GET', 'https://photon.komoot.io/api/?limit=8&lat=' . $near[0] . '&lon=' . $near[1] . '&q=' . rawurlencode($q));
    foreach (($j['features'] ?? []) as $f) {
        $p = $f['properties'] ?? []; $g = $f['geometry']['coordinates'] ?? null;
        if (!$g || ($p['countrycode'] ?? '') !== 'IN') continue;
        $sub = implode(', ', array_filter([$p['street'] ?? '', $p['district'] ?? $p['city'] ?? '', $p['state'] ?? '']));
        $out[] = ['src' => 'o', 'main' => (string)($p['name'] ?? $p['street'] ?? $q), 'sub' => $sub, 'lat' => (float)$g[1], 'lng' => (float)$g[0]];
        if (count($out) >= 5) break;
    }
    if (!$out) {
        $j = sr_http_json('GET', 'https://nominatim.openstreetmap.org/search?format=jsonv2&limit=5&countrycodes=in&accept-language=' . $lang
            . '&viewbox=82.2,25.1,83.2,25.7&q=' . rawurlencode($q));
        foreach (($j ?? []) as $r) {
            $parts = array_map('trim', explode(',', (string)$r['display_name']));
            $out[] = ['src' => 'o', 'main' => $parts[0] ?? $q, 'sub' => implode(', ', array_slice($parts, 1, 3)), 'lat' => (float)$r['lat'], 'lng' => (float)$r['lon']];
        }
    }
    sr_cache_put($pdo, $ck, $out);
    return $out;
}

/** "25.39, 82.56" ya Google Maps link (@lat,lng / q=lat,lng / !3d..!4d..) se coordinates */
function sr_parse_latlng(string $s): ?array {
    $s = urldecode(trim($s));
    foreach (['/!3d(-?\d+\.\d+)!4d(-?\d+\.\d+)/', '/@(-?\d+\.\d+),(-?\d+\.\d+)/', '/[?&](?:q|query|ll)=(-?\d+\.\d+),\s*(-?\d+\.\d+)/', '/^(-?\d+\.\d+)\s*,\s*(-?\d+\.\d+)$/'] as $re) {
        if (preg_match($re, $s, $m)) { $la = (float)$m[1]; $ln = (float)$m[2]; if ($la > 6 && $la < 37 && $ln > 68 && $ln < 98) return [$la, $ln]; }
    }
    return null;
}

/** Ek jawab: apni jagahein + Google (ya OSM). Duplicate naam hata kar. */
function sr_places_search(PDO $pdo, string $q, ?array $near, string $tok): array {
    sr_places_upgrade($pdo);
    $q = trim(preg_replace('/\s+/u', ' ', $q));
    if (mb_strlen($q) < 2 || mb_strlen($q) > 80) return [];
    $near = $near ?: SR_PLACES_CENTER;
    $list = sr_places_local($pdo, $q);
    $ext = sr_places_google($pdo, $q, $near, $tok);
    if ($ext === null) $ext = sr_places_osm($pdo, $q, $near);
    $seen = array_map(fn($r) => mb_strtolower($r['main']), $list);
    foreach ($ext as $r) {
        if (in_array(mb_strtolower($r['main']), $seen, true)) continue;
        $seen[] = mb_strtolower($r['main']); $list[] = $r;
        if (count($list) >= 8) break;
    }
    return $list;
}
