<?php
// Poora raasta, shuru se aakhir tak: partner app se join → manzoori → grahak booking →
// paas wale Sarathi ko kaam → OTP se pickup → drop code se poora → grahak ko "delivered".
if (!getenv('SR_TEST_DB_HOST')) throw new RuntimeException('Isolated database required');
define('SR_FIREBASE_ENABLED', true);
define('SR_FIREBASE_ADMIN_MOBILES', ['9000000501']);
function t($en, $hi) { return $en; }
require_once __DIR__ . '/../smartsarathi/inc/booking.php';
set_exception_handler(function ($e) { echo '::error::E2E ' . get_class($e) . ': ' . str_replace(["\n","\r"], ' ', $e->getMessage()) . ' @' . basename($e->getFile()) . ':' . $e->getLine() . "\n"; exit(1); });
function e2e($ok, $label) { if (!$ok) throw new RuntimeException('FAILED: ' . $label); echo "ok  $label\n"; }
$pdo = new PDO('mysql:host=' . getenv('SR_TEST_DB_HOST') . ';dbname=sarathi_identity_test;charset=utf8mb4', 'root', getenv('SR_TEST_DB_PASSWORD'),
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]);
foreach (['001-base.sql', '002-unified-identity.sql'] as $f) $pdo->exec(file_get_contents(__DIR__ . '/../smartsarathi/sql/' . $f));
sr_jobs_upgrade($pdo); sr_docs_upgrade($pdo);
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
$GLOBALS['pdo'] = $pdo;
// Pichhle tests ke online riders hata do — is test me sirf hamara Sarathi hazir ho
$pdo->exec('UPDATE riders SET ready_until=NULL');
$f = fn() => bin2hex(random_bytes(24)) . '.jpg';

sr_identity_signin($pdo, ['uid' => 'e2e-admin', 'mobile' => '9000000501']); $admin = $_SESSION;

// 1. Partner app (sarathi.php) → login → joining
$_SESSION = ['sr_intent' => 'partner'];
sr_identity_signin($pdo, ['uid' => 'e2e-rider', 'mobile' => '9000000502']); $a = sr_account($pdo);
e2e(sr_home_path($pdo) === '/apply.php', '1. partner app login opens joining');
sr_profile_submit($pdo, $a, ['name' => 'Ramesh Kumar', 'vehicle_type' => 'bike', 'vehicle_number' => 'UP66AB5020'], '', '');
sr_profile_set_details($pdo, $a, ['vehicle_model' => 'Hero Splendor', 'work_area' => 'bhadohi', 'work_km' => 10, 'terms_at' => date('Y-m-d H:i:s'), 'terms_ver' => SR_TERMS_VER]);
sr_profile_set_doc($pdo, $a, 'aadhaar', ['aadhaar_front' => $f(), 'aadhaar_back' => $f()], ['aadhaar_last4' => '1111']);
sr_profile_set_doc($pdo, $a, 'pan', ['pan_photo' => $f()], ['pan_number' => 'ABCDE5020F']);
$p = sr_profile($pdo, (int)$a['id']);
e2e($p['status'] === 'pending' && !sr_profile_docs_missing($p) && !sr_terms_needed($p), '2. joining complete: vehicle, area, Aadhaar, PAN, rules');
$riderSession = $_SESSION;

// 2. Office approves
$_SESSION = $admin; sr_profile_review($pdo, (int)$a['id'], (int)$p['revision'], 'approved', 'E2E');
$_SESSION = $riderSession;
e2e(sr_home_path($pdo) === '/kaam.php', '3. after approval the same login opens the work dashboard');
$rider = rider_me($pdo); $rid = (int)$rider['id'];
duty_on($pdo, $rid);

// 3. Customer books a bike in Bhadohi
sr_identity_signin($pdo, ['uid' => 'e2e-cust', 'mobile' => '9000000503']); $cust = sr_account($pdo);
[$ok, $b] = sr_book($pdo, $cust, ['service' => 'bike', 'name' => 'Sita', 'pick_address' => 'Bhadohi station', 'pick_geo' => '25.405,82.565',
    'drop_address' => 'Gyanpur', 'drop_geo' => '25.35,82.47', 'when' => 'now']);
e2e($ok, '4. customer booking created');
$j = sr_my_job($pdo, $cust, $b['job_no']); $v = sr_job_view($j, $cust);
e2e($j['status'] === 'assigned' && (int)$j['rider_id'] === $rid, '5. booking goes to the nearby Sarathi');
e2e($v['rider']['vehicle'] === 'Hero Splendor' && $v['rider']['number'] === 'UP66AB5020', '6. customer sees Sarathi vehicle model and number');

// 4. Rider: wrong OTP refused, right OTP starts, drop code finishes
[$bad] = job_pick($pdo, $rid, (int)$j['id'], '0000' === $v['start_otp'] ? '1111' : '0000');
e2e(!$bad, '7. wrong pickup OTP refused');
[$ok] = job_pick($pdo, $rid, (int)$j['id'], $v['start_otp']);
e2e($ok && sr_my_job($pdo, $cust, $b['job_no'])['status'] === 'picked', '8. pickup with customer OTP');
[$ok] = job_deliver($pdo, $rid, (int)$j['id'], $j['drop_code']);
e2e($ok && sr_my_job($pdo, $cust, $b['job_no'])['status'] === 'delivered', '9. finished with drop code; customer sees completed');
echo "End-to-end: all steps passed\n";
