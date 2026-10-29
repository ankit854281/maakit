<?php
// In-app booking (inc/booking.php) on a real isolated CI database. No hosting config.
if (!getenv('SR_TEST_DB_HOST')) throw new RuntimeException('Isolated database required');
define('SR_FIREBASE_ENABLED', true);
define('SR_FIREBASE_ADMIN_MOBILES', ['9000000301']);
function t($en, $hi) { return $en; }
require_once __DIR__ . '/../smartsarathi/inc/booking.php';
function bk_check($ok, $label) { if (!$ok) throw new RuntimeException('FAILED: ' . $label); echo "ok  $label\n"; }
$pdo = new PDO('mysql:host=' . getenv('SR_TEST_DB_HOST') . ';dbname=sarathi_identity_test;charset=utf8mb4', 'root', getenv('SR_TEST_DB_PASSWORD'),
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]);
foreach (['001-base.sql', '002-unified-identity.sql'] as $f) $pdo->exec(file_get_contents(__DIR__ . '/../smartsarathi/sql/' . $f));
sr_jobs_upgrade($pdo);
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
$GLOBALS['pdo'] = $pdo;

sr_identity_signin($pdo, ['uid' => 'bk-admin', 'mobile' => '9000000301']); $admin = $_SESSION;
sr_identity_signin($pdo, ['uid' => 'bk-rider', 'mobile' => '9000000302']); $ra = sr_account($pdo); $rs = $_SESSION;
sr_profile_submit($pdo, $ra, ['name' => 'Booking Rider', 'vehicle_type' => 'bike'], '', '');
$_SESSION = $admin; sr_profile_review($pdo, (int)$ra['id'], 1, 'approved', 'CI');
$_SESSION = $rs; $_SESSION['sr_mode'] = 'rider'; $rider = rider_me($pdo); $rid = (int)$rider['id'];
duty_on($pdo, $rid); $pdo->prepare('UPDATE riders SET ready_until=NULL WHERE id<>?')->execute([$rid]);

sr_identity_signin($pdo, ['uid' => 'bk-cust', 'mobile' => '9000000303']); $cust = sr_account($pdo);
sr_identity_signin($pdo, ['uid' => 'bk-other', 'mobile' => '9000000304']); $other = sr_account($pdo);

$P = '25.395000,82.571000'; $D = '25.410000,82.580000';
[$ok, $q] = sr_quote($P, $D);
$svc = sr_services();
bk_check($ok && $q['km'] > 0 && $q['fares']['bike']['fare'] === sr_fare($svc['bike'], $q['km']), 'quote computed on server');
bk_check($q['fares']['cab']['fare'] >= $svc['cab']['min'], 'minimum fare applied');
[$bad] = sr_quote('abc', $D); bk_check(!$bad, 'invalid GPS refused');

[$ok, $b] = sr_book($pdo, $cust, ['service' => 'bike', 'name' => 'Ram', 'pick_address' => 'Bhadohi station', 'pick_geo' => $P,
    'drop_address' => 'Gopiganj market', 'drop_geo' => $D, 'when' => 'now', 'fee' => 1]);
bk_check($ok, 'bike booking created');
$j = sr_my_job($pdo, $cust, $b['job_no']);
bk_check($j && $j['status'] === 'new' && (int)$j['offer_rider'] === $rid, 'bike job offered to the approved online rider');
bk_check(job_offer_accept($pdo, $rid, (int)$j['id'])[0], 'rider accepts');
$j = sr_my_job($pdo, $cust, $b['job_no']);
bk_check($j['status'] === 'assigned' && (int)$j['rider_id'] === $rid, 'accepted job is assigned to that rider');
bk_check((int)$j['fee'] === $q['fares']['bike']['fare'] && (int)$j['fee'] !== 1, 'client-sent fare ignored');
bk_check($j['kind'] === 'ride' && $j['vehicle'] === 'bike' && $j['pick_mobile'] === '9000000303', 'job fields');
$v = sr_job_view($j, $cust);
bk_check(preg_match('/^\d{4}$/', (string)$v['start_otp']) && $v['rider']['name'] === 'Booking Rider', 'customer sees OTP and rider');
bk_check(sr_my_job($pdo, $other, $b['job_no']) === null, 'another customer cannot see the booking');
bk_check(count(sr_my_jobs($pdo, $cust)) >= 1 && count(sr_my_jobs($pdo, $other)) === 0, 'my bookings list is per customer');
bk_check(!sr_cancel_mine($pdo, $other, $b['job_no']), 'another customer cannot cancel');
bk_check(sr_cancel_mine($pdo, $cust, $b['job_no']), 'customer cancels before pickup');
bk_check(sr_my_job($pdo, $cust, $b['job_no'])['status'] === 'cancelled', 'status cancelled');

[$ok, $c] = sr_book($pdo, $cust, ['service' => 'cab', 'name' => 'Ram', 'pick_address' => 'Bhadohi', 'pick_geo' => $P,
    'drop_address' => 'Varanasi', 'drop_geo' => $D, 'when' => 'later', 'at' => date('Y-m-d\TH:i', time() + 86400)]);
bk_check($ok && sr_my_job($pdo, $cust, $c['job_no'])['status'] === 'new', 'scheduled cab waits for office (no auto-assign)');

[$ok, $p] = sr_book($pdo, $cust, ['service' => 'parcel', 'name' => 'Ram', 'pick_address' => 'Shop', 'pick_geo' => $P,
    'drop_address' => 'Home', 'drop_geo' => $D, 'self' => '0', 'drop_name' => 'Sita', 'drop_mobile' => '9000000305', 'items' => 'Tiffin']);
$pj = sr_my_job($pdo, $cust, $p['job_no']);
bk_check($ok && (int)$pj['offer_rider'] === $rid && job_offer_accept($pdo, $rid, (int)$pj['id'])[0], 'parcel offered and accepted');
$pj = sr_my_job($pdo, $cust, $p['job_no']);
bk_check($pj['kind'] === 'delivery' && $pj['drop_mobile'] === '9000000305' && $pj['status'] === 'assigned', 'parcel to another receiver, assigned after accept');
[$ok] = sr_book($pdo, $cust, ['service' => 'parcel', 'name' => 'Ram', 'pick_address' => 'Shop', 'pick_geo' => $P, 'drop_address' => 'Home', 'drop_geo' => $D, 'self' => '0', 'drop_name' => 'S', 'drop_mobile' => '123']);
bk_check(!$ok, 'bad receiver refused');
[$ok] = sr_book($pdo, $cust, ['service' => 'bike', 'name' => 'Ram', 'pick_address' => 'X', 'pick_geo' => 'nope', 'drop_address' => 'Y', 'drop_geo' => $D]);
bk_check(!$ok, 'booking without pickup GPS refused');

// --- Step-1 fixes (29 Oct 2026) ---
duty_off($pdo, $rid); duty_on($pdo, $rid);
$cj = sr_my_job($pdo, $cust, $c['job_no']);
bk_check($cj['status'] === 'new' && $cj['assign_mode'] === 'office', 'office booking stays with office even when a rider comes online');
bk_check(sr_job_view($cj, $cust)['mode'] === 'office', 'customer is told the office will assign');
bk_check(sr_job_view($pj, $cust)['mode'] === 'auto', 'auto booking is not shown as office');
$sv = $svc['saath'];
bk_check(sr_fare($sv, 3, 3) === 3 * sr_fare($sv, 3, 1) && sr_fare($sv, 3, 1) >= $sv['min'], 'Saath Safar: per-seat fare (minimum per seat) times seats');
bk_check(sr_vehicle_does_delivery('care') === 0 && sr_vehicle_does_delivery('bus') === 0 && sr_vehicle_does_delivery('loader') === 0 && sr_vehicle_does_delivery('bike') === 1, 'parcels only for bike/auto/e-rickshaw/car');
$_SESSION = ['sr_intent' => 'partner', 'sr_lang' => 'hi'];
sr_identity_signin($pdo, ['uid' => 'bk-newpartner', 'mobile' => '9000000306']);
bk_check(($_SESSION['sr_intent'] ?? '') === 'partner', 'partner intent survives login');
bk_check(sr_home_path($pdo) === '/apply.php', 'new partner from partner app goes to joining, not customer home');
echo "Booking checks passed\n";
