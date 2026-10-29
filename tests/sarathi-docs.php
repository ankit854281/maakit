<?php
// Partner documents (Aadhaar / PAN / licence / RC) on an isolated CI database.
if (!getenv('SR_TEST_DB_HOST')) throw new RuntimeException('Isolated database required');
define('SR_FIREBASE_ENABLED', true);
define('SR_FIREBASE_ADMIN_MOBILES', ['9000000401']);
function t($en, $hi) { return $en; }
require_once __DIR__ . '/../smartsarathi/inc/booking.php';
function dc_check($ok, $label) { if (!$ok) throw new RuntimeException('FAILED: ' . $label); echo "ok  $label\n"; }
$pdo = new PDO('mysql:host=' . getenv('SR_TEST_DB_HOST') . ';dbname=sarathi_identity_test;charset=utf8mb4', 'root', getenv('SR_TEST_DB_PASSWORD'),
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]);
foreach (['001-base.sql', '002-unified-identity.sql'] as $f) $pdo->exec(file_get_contents(__DIR__ . '/../smartsarathi/sql/' . $f));
sr_jobs_upgrade($pdo); sr_docs_upgrade($pdo);
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
$GLOBALS['pdo'] = $pdo;
$f = fn() => bin2hex(random_bytes(24)) . '.jpg';

sr_identity_signin($pdo, ['uid' => 'dc-admin', 'mobile' => '9000000401']); $admin = $_SESSION;
sr_identity_signin($pdo, ['uid' => 'dc-rider', 'mobile' => '9000000402']); $a = sr_account($pdo); $rs = $_SESSION;
sr_profile_submit($pdo, $a, ['name' => 'Docs Rider', 'vehicle_type' => 'auto', 'vehicle_number' => ''], '', '');
$p = sr_profile($pdo, (int)$a['id']);
dc_check(sr_doc_count($p) === [0, 2], 'only Aadhaar and PAN are required');
dc_check(sr_profile_docs_missing($p), 'missing documents flagged');

sr_profile_set_doc($pdo, $a, 'photo', ['photo' => $f()], []);
sr_profile_set_doc($pdo, $a, 'aadhaar', ['aadhaar_front' => $f(), 'aadhaar_back' => $f()], ['aadhaar_last4' => '4321']);
sr_profile_set_doc($pdo, $a, 'pan', ['pan_photo' => $f()], ['pan_number' => 'ABCDE1234F']);
sr_profile_set_doc($pdo, $a, 'licence', ['licence_photo' => $f(), 'dl_back' => null], ['dl_number' => 'UP6620190012345']);
$p = sr_profile($pdo, (int)$a['id']);
dc_check(sr_doc_count($p) === [2, 2] && !sr_profile_docs_missing($p), 'Aadhaar + PAN complete the profile');
dc_check($p['aadhaar_last4'] === '4321' && strlen($p['aadhaar_last4']) === 4, 'only last 4 Aadhaar digits stored');
dc_check($p['status'] === 'pending', 'documents do not change approval status');

$threw = false; try { sr_profile_set_doc($pdo, $a, 'pan', ['rc_photo' => $f()], []); } catch (RuntimeException $e) { $threw = true; }
dc_check($threw, 'a document cannot write another document\'s column');

$_SESSION = $admin; sr_profile_review($pdo, (int)$a['id'], (int)$p['revision'], 'approved', 'CI');
$_SESSION = $rs;
sr_profile_set_doc($pdo, $a, 'rc', ['rc_photo' => $f()], ['vehicle_number' => 'UP66AB1234']);
$p = sr_profile($pdo, (int)$a['id']);
dc_check(sr_doc_done($p, 'rc') && sr_doc_done($p, 'licence'), 'optional licence and RC saved');
dc_check($pdo->query('SELECT rc_number FROM riders WHERE id=' . (int)$p['rider_id'])->fetchColumn() === 'UP66AB1234', 'RC number synced to rider');
dc_check($p['status'] === 'approved', 'approved rider stays approved after adding RC');

sr_identity_signin($pdo, ['uid' => 'dc-care', 'mobile' => '9000000403']); $c = sr_account($pdo);
sr_profile_submit($pdo, $c, ['name' => 'Care Saathi', 'vehicle_type' => 'care', 'vehicle_number' => ''], '', '');
dc_check(sr_doc_optional(sr_profile($pdo, (int)$c['id'])) === ['photo'], 'Care Saathi is not offered licence or RC');

$pdo->prepare("UPDATE sr_rider_profiles SET status='rejected',review_note='fix' WHERE account_id=?")->execute([$c['id']]);
sr_profile_set_doc($pdo, $c, 'photo', ['photo' => $keep = $f()], []);
sr_profile_resubmit($pdo, $c, 'Care Saathi Two', 'care');
$cp = sr_profile($pdo, (int)$c['id']);
dc_check($cp['status'] === 'pending' && $cp['photo'] === $keep && $cp['name'] === 'Care Saathi Two', 'resubmit keeps documents');

// Vehicle details, area, rules — and area-aware dispatch
sr_identity_signin($pdo, ['uid' => 'dc-area', 'mobile' => '9000000404']); $z = sr_account($pdo); $zs = $_SESSION;
sr_profile_submit($pdo, $z, ['name' => 'Area Rider', 'vehicle_type' => 'bike', 'vehicle_number' => 'UP66AB4040'], '', '');
sr_profile_set_details($pdo, $z, ['vehicle_model' => 'Hero Splendor', 'work_area' => 'gopiganj', 'work_km' => 5, 'terms_at' => date('Y-m-d H:i:s'), 'terms_ver' => SR_TERMS_VER]);
$zp = sr_profile($pdo, (int)$z['id']);
$_SESSION = $admin; sr_profile_review($pdo, (int)$z['id'], (int)$zp['revision'], 'approved', 'CI');
$zp = sr_profile($pdo, (int)$z['id']); $zr = $pdo->query('SELECT * FROM riders WHERE id=' . (int)$zp['rider_id'])->fetch();
dc_check($zr['vehicle'] === 'Hero Splendor' && $zr['rc_number'] === 'UP66AB4040' && $zr['work_area'] === 'gopiganj' && (int)$zr['work_km'] === 5, 'approval copies model, number and area to the rider');
duty_on($pdo, (int)$zr['id']);
$pdo->exec("INSERT INTO clients(name,fee) VALUES ('Area client',20)"); $cl = $pdo->query('SELECT * FROM clients WHERE id=' . (int)$pdo->lastInsertId())->fetch();
$far = job_create($pdo, $cl, ['ref' => 'AREA-far', 'drop_name' => 'Far Customer', 'drop_mobile' => '9000000444', 'drop_address' => 'Suriyawan', 'pick_address' => 'Suriyawan', 'pick_geo' => '25.467,82.417', 'drop_geo' => '25.47,82.42']);
$farJob = $pdo->query("SELECT rider_id FROM jobs WHERE client_ref='AREA-far'")->fetch();
dc_check((int)$farJob['rider_id'] !== (int)$zr['id'], 'order outside the chosen area is not given to that rider');
job_create($pdo, $cl, ['ref' => 'AREA-near', 'drop_name' => 'Near Customer', 'drop_mobile' => '9000000445', 'drop_address' => 'Gopiganj', 'pick_address' => 'Gopiganj', 'pick_geo' => '25.285,82.435', 'drop_geo' => '25.29,82.44']);
$nearJob = $pdo->query("SELECT rider_id FROM jobs WHERE client_ref='AREA-near'")->fetch();
dc_check((int)$nearJob['rider_id'] === (int)$zr['id'], 'order inside the area goes to the nearby rider');
dc_check(abs(sr_geo_km([25.42, 82.57], [25.35, 82.47]) - 12.8) < 1.5, 'distance Bhadohi–Gyanpur about 13 km');
echo "Documents: all checks passed\n";
