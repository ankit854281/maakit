<?php
// ============================================================
//  सारथी — ग्राहक कंपनी के लिए दरवाज़ा (API)
//
//  Maakit (ya koi bhi dukaan) yahan apni chaabi ke saath kaam
//  bhejti hai. Yahi Sarathi aur Maakit ke beech ka POORA
//  rishta hai — iske alawa dono ek doosre ka kuch nahi dekhte.
//
//  Teen hi kaam:
//    POST ?do=create  — naya kaam bhejiye
//    GET  ?do=status  — kaam kahan tak pahuncha
//    POST ?do=cancel  — kaam radd kijiye (uthane se pehle tak)
//
//  Chaabi header me jaati hai:
//    Authorization: Bearer sk_xxxxxxxx_yyyy...
//
//  Paise ka niyam: yahan sirf DELIVERY ka fee hai. Saaman ka
//  paisa Sarathi chhoota tak nahi — wo graahak seedha dukaan
//  ko deta hai.
// ============================================================
require_once __DIR__ . '/../inc/fn.php';
require_once __DIR__ . '/../inc/kaam.php';
require_once __DIR__ . '/../inc/client.php';

// chaabi dekhiye
$client = key_client($pdo, key_from_request());
if (!$client) {
    header('WWW-Authenticate: Bearer');
    api_fail(401, 'चाबी नहीं मिली या सही नहीं है');
}

$do = $_GET['do'] ?? '';

// Body do tarah se aa sakti hai — JSON ya saadharan form.
// Dono chalte hain, taaki jodne wale ko dikkat na ho.
$in = $_POST;
if (!$in && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw = file_get_contents('php://input', false, null, 0, 1024 * 256);
    if (is_string($raw) && $raw !== '') {
        $j = json_decode($raw, true);
        if (is_array($j)) $in = $j;
    }
}

try {
    if ($do === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        [$ok, $out] = job_create($pdo, $client, $in);
        api_out($ok ? 200 : 400, $ok ? ['ok' => true, 'job' => $out] : ['ok' => false, 'error' => $out]);
    }

    if ($do === 'status') {
        $ref = (string)($_GET['ref'] ?? ($in['ref'] ?? ''));
        if ($ref === '') api_fail(400, 'ref चाहिए');
        [$ok, $out] = job_status($pdo, $client, $ref);
        api_out($ok ? 200 : 404, $ok ? ['ok' => true, 'job' => $out] : ['ok' => false, 'error' => $out]);
    }

    if ($do === 'cancel' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $ref = (string)($in['ref'] ?? ($_GET['ref'] ?? ''));
        if ($ref === '') api_fail(400, 'ref चाहिए');
        [$ok, $out] = job_cancel($pdo, $client, $ref);
        api_out($ok ? 200 : 409, $ok ? ['ok' => true] + $out : ['ok' => false, 'error' => $out]);
    }

    // "ping" — jodne wala jaanch sake ki chaabi chal rahi hai
    if ($do === 'ping') {
        api_out(200, ['ok' => true, 'client' => $client['name'], 'fee' => (int)$client['fee']]);
    }

} catch (Throwable $e) {
    // Asli galti kabhi bahar mat bhejiye — usme table ke naam
    // aur query dikh jaate hain.
    error_log('Sarathi API: ' . $e->getMessage());
    api_fail(500, 'अभी नहीं हो पा रहा, थोड़ी देर बाद कोशिश कीजिए');
}

api_fail(400, 'do=create / status / cancel / ping में से एक चाहिए');
