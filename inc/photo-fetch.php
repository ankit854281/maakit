<?php
// ============================================================
//  Auto photos — server par photo utaarna (download) aur credit.
//
//  Photo SIRF in do muft, kanooni library se aati hai:
//    - Wikimedia Commons   (upload.wikimedia.org)
//    - Open Food Facts     (images.openfoodfacts.org)
//  Koi aur website ka link aaye to mana — Google ya brand ki
//  photo yahan se kabhi nahi aati.
//
//  In library ki shart: photo kheenchne wale ka naam + licence
//  dikhana. Wo catalog_photos.credit/license/source_url me rakha
//  jata hai aur /photo-credits.php par dikhta hai.
// ============================================================
require_once __DIR__ . '/catalog-photos.php';

const PF_HOSTS = ['upload.wikimedia.org', 'images.openfoodfacts.org', 'static.openfoodfacts.org'];

/** Sirf wahi licence jo dukaan (commercial) me chal sakte hain aur badalne dete hain. */
function pf_license_ok($lic) {
    $l = strtolower(trim((string)$lic));
    if ($l === '') return false;
    if (strpos($l, 'nc') !== false && preg_match('/\bnc\b|-nc-|-nc$/', $l)) return false;   // non-commercial — nahi
    if (preg_match('/\bnd\b|-nd-|-nd$/', $l)) return false;                                   // no-derivatives — nahi
    return (bool)preg_match('/^(cc0|cc-zero|pd|public domain|cc-by(-sa)?(-\d|$|\s)|cc by(-sa)?(\s|$)|attribution)/', $l);
}

/** URL theek library ka hai? */
function pf_url_ok($url) {
    $p = parse_url((string)$url);
    return $p && ($p['scheme'] ?? '') === 'https' && in_array(strtolower($p['host'] ?? ''), PF_HOSTS, true)
        && !isset($p['user']) && !isset($p['port']);
}

/**
 * Photo ko server par utaar kar ek temp file ka raasta deta hai (ya null).
 * 8 MB se badi, ya photo na ho, to null.
 */
function pf_download($url) {
    if (!pf_url_ok($url)) return null;
    // Sirf jaanch (test) ke liye: library ki jagah local server. Live par ye env kabhi set nahi hota.
    $fake = getenv('MAAKIT_PF_TEST');
    if ($fake) $url = preg_replace('#^https://[^/]+#', rtrim($fake, '/'), $url);

    $ua = 'MaakitPhotoBot/1.0 (https://maakit.in; hyperlocal shop catalogue; contact via website)';
    $tmp = tempnam(sys_get_temp_dir(), 'mkp');
    $ok = false;
    if (function_exists('curl_init')) {
        $fh = fopen($tmp, 'wb');
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_FILE => $fh, CURLOPT_USERAGENT => $ua, CURLOPT_TIMEOUT => 25, CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_FOLLOWLOCATION => false, CURLOPT_PROTOCOLS => CURLPROTO_HTTPS | ($fake ? CURLPROTO_HTTP : 0),
            CURLOPT_NOPROGRESS => false,
            CURLOPT_PROGRESSFUNCTION => function ($c, $dlTotal, $dl) { return $dl > 8 * 1024 * 1024 ? 1 : 0; },
        ]);
        $ok = curl_exec($ch) && curl_getinfo($ch, CURLINFO_RESPONSE_CODE) === 200;
        curl_close($ch); fclose($fh);
    } else {
        $ctx = stream_context_create(['http' => ['header' => "User-Agent: $ua\r\n", 'timeout' => 25, 'follow_location' => 0]]);
        $data = @file_get_contents($url, false, $ctx, 0, 8 * 1024 * 1024 + 1);
        if ($data !== false && strlen($data) <= 8 * 1024 * 1024) { file_put_contents($tmp, $data); $ok = true; }
    }
    if (!$ok || !filesize($tmp) || !@getimagesize($tmp)) { @unlink($tmp); return null; }
    return $tmp;
}

/** Credit ki ek saaf line (HTML hata kar, chhoti). */
function pf_clean($s, $max = 120) {
    $s = html_entity_decode(strip_tags((string)$s), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $s = trim(preg_replace('/\s+/u', ' ', $s));
    return mb_substr($s, 0, $max);
}

/**
 * Ek library photo ko saaman me jodna. [ok, msg/file]
 * $d = [url, source (commons|off), credit, license, page]
 */
function pf_take(PDO $pdo, $catId, array $d, $uid) {
    $catId = (int)$catId;
    $src = in_array($d['source'] ?? '', ['commons', 'off'], true) ? $d['source'] : '';
    if (!$src) return [false, t('Unknown photo library', 'अनजान फ़ोटो लाइब्रेरी')];
    $lic = pf_clean($d['license'] ?? '', 40);
    if ($src === 'off' && $lic === '') $lic = 'CC BY-SA 3.0';
    if (!pf_license_ok($lic)) return [false, t('This photo’s licence does not allow shop use', 'इस फ़ोटो का लाइसेंस दुकान में इस्तेमाल की इजाज़त नहीं देता')];
    if (!pf_url_ok($d['url'] ?? '')) return [false, t('Only Wikimedia Commons or Open Food Facts photos are allowed', 'सिर्फ़ Wikimedia Commons या Open Food Facts की फ़ोटो ली जा सकती है')];
    $page = (string)($d['page'] ?? '');
    if ($page !== '' && !preg_match('#^https://(commons\.wikimedia\.org|world\.openfoodfacts\.org|[a-z]{2}\.openfoodfacts\.org)/#', $page)) $page = '';

    $st = $pdo->prepare("SELECT COUNT(*) FROM catalog_items WHERE id=?"); $st->execute([$catId]);
    if (!(int)$st->fetchColumn()) return [false, t('Product not found', 'सामान नहीं मिला')];
    $st = $pdo->prepare("SELECT COUNT(*) FROM catalog_photos WHERE cat_id=?"); $st->execute([$catId]);
    $have = (int)$st->fetchColumn();
    if ($have >= CATALOG_PHOTO_MAX) return [false, t('Already has ' . CATALOG_PHOTO_MAX . ' photos', 'पहले से ' . CATALOG_PHOTO_MAX . ' फ़ोटो हैं')];
    // wahi photo dobara nahi
    if ($page !== '') {
        $st = $pdo->prepare("SELECT COUNT(*) FROM catalog_photos WHERE cat_id=? AND source_url=?"); $st->execute([$catId, $page]);
        if ((int)$st->fetchColumn()) return [false, t('This photo is already added', 'यह फ़ोटो पहले से लगी है')];
    }

    $tmp = pf_download($d['url']);
    if (!$tmp) return [false, t('Could not download the photo — try another one', 'फ़ोटो नहीं उतर पाई — दूसरी चुनिए')];
    $saved = save_photo_from($tmp, filesize($tmp), 'cph', 900);
    @unlink($tmp);
    if (!$saved) return [false, t('Not a usable photo', 'काम की फ़ोटो नहीं है')];

    $credit = pf_clean($d['credit'] ?? '', 150);
    if ($credit === '') $credit = $src === 'off' ? 'Open Food Facts contributors' : 'Wikimedia Commons';
    $pdo->prepare("INSERT INTO catalog_photos (cat_id, file, sort_no, added_by, source, credit, license, source_url) VALUES (?,?,?,?,?,?,?,?)")
        ->execute([$catId, $saved, 1000 + $have, (int)$uid, $src, $credit, $lic, $page ?: null]);
    $n = 0;
    $st = $pdo->prepare("SELECT id FROM catalog_photos WHERE cat_id=? ORDER BY sort_no, id"); $st->execute([$catId]);
    foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $pid) $pdo->prepare("UPDATE catalog_photos SET sort_no=? WHERE id=?")->execute([++$n * 10, (int)$pid]);
    $pdo->prepare("DELETE FROM catalog_photo_skip WHERE cat_id=?")->execute([$catId]);
    return [true, $saved];
}

/** [file => [credit, license, source, source_url]] — sirf library wali photo ke liye */
function pf_credits_for(PDO $pdo, array $files) {
    $files = array_values(array_filter($files, 'catalog_photo_ok'));
    if (!$files) return [];
    try {
        $st = $pdo->prepare("SELECT file, credit, license, source, source_url FROM catalog_photos WHERE source IS NOT NULL AND file IN (" . implode(',', array_fill(0, count($files), '?')) . ")");
        $st->execute($files);
        $out = [];
        foreach ($st->fetchAll() as $r) $out[$r['file']] = $r;
        return $out;
    } catch (Throwable $e) { return []; }
}

/** Saaman ke panne par chhoti si credit line (HTML). */
function pf_credit_line(PDO $pdo, array $files) {
    $c = pf_credits_for($pdo, $files);
    if (!$c) return '';
    $parts = [];
    foreach ($c as $r) {
        $lib = $r['source'] === 'off' ? 'Open Food Facts' : 'Wikimedia Commons';
        $txt = h($r['credit']) . ' · ' . h($r['license']) . ' · ' . $lib;
        $parts[] = $r['source_url'] ? '<a href="' . h($r['source_url']) . '" target="_blank" rel="noopener nofollow">' . $txt . '</a>' : $txt;
    }
    return '<p class="pf-credit">' . t('Photo', 'फ़ोटो') . ': ' . implode(' | ', array_unique($parts)) . ' · <a href="/photo-credits.php">' . t('All credits', 'सारे क्रेडिट') . '</a></p>';
}
