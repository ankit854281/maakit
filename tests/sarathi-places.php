<?php
// Jagah khoj: apni list, Hindi/English milaan, cache, Google link se coordinates. Bahar ka network nahi.
if (!getenv('SR_TEST_DB_HOST')) throw new RuntimeException('Isolated database required');
define('SR_FIREBASE_ENABLED', true);
function t($en, $hi) { return $en; }
require_once __DIR__ . '/../smartsarathi/inc/booking.php';
require_once __DIR__ . '/../smartsarathi/inc/places.php';
function pl($ok, $label) { if (!$ok) throw new RuntimeException('FAILED: ' . $label); echo "ok  $label\n"; }
$pdo = new PDO('mysql:host=' . getenv('SR_TEST_DB_HOST') . ';dbname=sarathi_identity_test;charset=utf8mb4', 'root', getenv('SR_TEST_DB_PASSWORD'),
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]);
foreach (['001-base.sql', '002-unified-identity.sql'] as $f) $pdo->exec(file_get_contents(__DIR__ . '/../smartsarathi/sql/' . $f));
$GLOBALS['pdo'] = $pdo;
sr_places_upgrade($pdo);
pl((int)$pdo->query('SELECT COUNT(*) FROM sr_places')->fetchColumn() >= 7, 'district towns seeded');
$pdo->prepare("INSERT INTO sr_places (name,name_hi,alias,area,lat,lng,src,uses) VALUES ('Ram Janki Mandir','राम जानकी मंदिर','mandir, ramjanki','Station Road',25.3951,82.5682,'office',5)")->execute();
pl(sr_places_local($pdo, 'janki')[0]['main'] === 'Ram Janki Mandir', 'English search finds the office place');
pl(sr_places_local($pdo, 'जानकी')[0]['lat'] === 25.3951, 'Hindi search finds it with coordinates');
pl(sr_places_local($pdo, 'mandir')[0]['src'] === 'l', 'alias search works and is marked local');
pl(sr_places_local($pdo, 'gyan')[0]['main'] === 'Gyanpur', 'town search');
pl(sr_places_local($pdo, '%') === [], 'wildcards are not special');
sr_cache_put($pdo, 'k1', [['a' => 1]]); pl(sr_cache_get($pdo, 'k1', 1) === [['a' => 1]], 'cache round-trip');
pl(sr_parse_latlng('https://www.google.com/maps/place/X/@25.3951,82.5682,17z') === [25.3951, 82.5682], 'Google Maps @ link parsed');
pl(sr_parse_latlng('https://maps.google.com/?q=25.40,82.57') === [25.40, 82.57], 'q= link parsed');
pl(sr_parse_latlng('25.3951, 82.5682') === [25.3951, 82.5682], 'plain lat,lng parsed');
pl(sr_parse_latlng('51.5, -0.1') === null, 'outside India refused');
pl(sr_places_key() === '', 'no Google key in CI — server never needs one to work');
// Jaankari ke liye (fail nahi karta): bina Google key, OpenStreetMap se asli khoj
foreach (['Bhadohi railway station', 'Gyanpur hospital', 'Gopiganj market'] as $qq) {
    $r = sr_places_osm($pdo, $qq, SR_PLACES_CENTER);
    echo '::notice::OSM "' . $qq . '": ' . count($r) . ' — ' . implode(' | ', array_map(fn($x) => $x['main'] . ' (' . round($x['lat'], 3) . ',' . round($x['lng'], 3) . ')', array_slice($r, 0, 3))) . "\n";
}
echo "Places: all checks passed\n";
