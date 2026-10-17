<?php
// ============================================================
//  जाँच — Photo → text for Syllabus and Lesson Plans (school/scan_api.php)
//
//  Kitaab ke Contents panne ki line saaf (number, page number hate) →
//  sirf asli photo (JPG/PNG/WebP) → teacher sirf apne class-subject ki →
//  AI band ho to "phone padhega" (409 noAi) → AI chalu: chapter list aur
//  lesson plan topics → roz ki ginti → photo kahin save nahi → "scan"
//  records aam sync me nahi.
//  Ek alag server (8096) nakli AI (8097) ke saath chalaya jaata hai.
// ============================================================
require_once __DIR__ . '/../../config.php';
if (DB_NAME !== 'maakit_jaanch') throw new RuntimeException('CI database only');
require_once __DIR__ . '/../../inc/db.php';
require_once __DIR__ . '/../../school/inc.php';
require_once __DIR__ . '/../../school/learning_lib.php';
require_once __DIR__ . '/../../school/scan_lib.php';

function sf_check($ok, $m) { if (!$ok) throw new RuntimeException('Scan flow: ' . $m); }
function sf_http(int $port, string $path, $body = null, ?string $tok = null): array {
    $c = curl_init("http://127.0.0.1:$port" . $path);
    $h = ['X-School-Token: ' . ($tok ?? '')]; $o = [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30, CURLOPT_PROXY => ''];
    if ($body !== null) { $h[] = 'Content-Type: application/json'; $o[CURLOPT_POST] = true; $o[CURLOPT_POSTFIELDS] = json_encode($body); }
    $o[CURLOPT_HTTPHEADER] = $h; curl_setopt_array($c, $o);
    $raw = (string)curl_exec($c); $code = curl_getinfo($c, CURLINFO_HTTP_CODE); curl_close($c);
    sf_check(!preg_match('/Fatal error|Warning:|Uncaught|Deprecated/', $raw), "PHP error at $path: " . substr($raw, 0, 300));
    return [$code, json_decode($raw, true), $raw];
}

// ---- line cleaning ----
foreach (['1. Real Numbers ........ 1' => 'Real Numbers', 'Chapter 3: Polynomials 23-31' => 'Polynomials', 'अध्याय 2 – कबीर की साखी 15' => 'कबीर की साखी',
          'iv. The Sermon at Benares 42' => 'The Sermon at Benares', '(5) Life Processes' => 'Life Processes', 'Unit 1 Matter in Our Surroundings …… 1' => 'Matter in Our Surroundings',
          'Nelson Mandela: Long Walk to Freedom' => 'Nelson Mandela: Long Walk to Freedom', '12 Electricity 200' => 'Electricity'] as $in => $want)
    sf_check(sc_line($in) === $want, "line '$in' → '" . sc_line($in) . "'");
$c = sc_clean_chapters(['chapters' => [['name' => 'A1. x', 'topics' => []], ['name' => 'Trees', 'topics' => ['Roots 3', 'Roots', '']], ['name' => 'trees', 'topics' => []], ['name' => '9', 'topics' => []]]]);
sf_check(array_column($c, 'name') === ['A1. x', 'Trees'] && $c[1]['topics'] === ['Roots'], 'chapter clean/dedupe: ' . json_encode($c, JSON_UNESCAPED_UNICODE));
$bad = 0;
foreach (['nope', 'data:image/svg+xml;base64,' . base64_encode(str_repeat('<svg>', 100)), 'data:image/png;base64,' . base64_encode("\xFF\xD8\xFF" . str_repeat('a', 300)),
          'data:image/jpeg;base64,' . base64_encode("\xFF\xD8\xFF" . str_repeat('a', SC_MAX_BYTES))] as $x) { try { sc_image($x); } catch (InvalidArgumentException $e) { $bad++; } }
sf_check($bad === 4, 'bad photos refused');
$jpg = 'data:image/jpeg;base64,' . base64_encode("\xFF\xD8\xFF\xE0" . str_repeat("\x00", 600));
sf_check(sc_image($jpg)[0] === 'image/jpeg', 'jpeg accepted');

// ---- fresh school ----
foreach (['sch_sessions', 'sch_users', 'sch_records', 'sch_log', 'sch_login_fail'] as $t) $pdo->exec("DELETE FROM $t");
sch_rec_put('settings', 'main', ['school' => 'Scan Test School', 'session' => '2026–27', 'sessionStart' => '2026-04-01'], 'principal');
sch_rec_put('teacher', 'K', ['name' => 'Kartikey Sir', 'role' => 'SST'], 'principal');
sch_rec_put('alloc', 'main', [['c' => '10th', 's' => 'Mathematics', 't' => 'K', 'n' => 6]], 'principal');
sch_rec_put('syl', '10th|Mathematics', ['chapters' => [['n' => 'Real Numbers', 'done' => '2026-05-01'], ['n' => 'Polynomials', 'done' => null]]], 'principal');
$pin = fn($uid, $role, $tid, $p) => $pdo->prepare('INSERT INTO sch_users (uid, role, tid, pin_hash, must_change) VALUES (?,?,?,?,0)')->execute([$uid, $role, $tid, password_hash($p, PASSWORD_DEFAULT)]);
$pin('principal', 'principal', 'P', '2468'); $pin('t:K', 'teacher', 'K', '7777'); $pin('chairman', 'chairman', null, '6666');
$tok = [];
foreach ([['principal', ['role' => 'principal', 'pin' => '2468']], ['K', ['role' => 'teacher', 'tid' => 'K', 'pin' => '7777']], ['CH', ['role' => 'chairman', 'pin' => '6666']]] as [$n, $b]) {
    [, $j] = sf_http(8099, '/school/api.php?a=login', $b); $tok[$n] = $j['token'];
}

// ---- normal server: AI not switched on → phone reads ----
[$c, $j] = sf_http(8099, '/school/scan_api.php?a=status', null, $tok['K']); sf_check($c === 200 && $j['ai'] === false, 'status: AI off');
[$c] = sf_http(8099, '/school/scan_api.php?a=status', null, $tok['CH']); sf_check($c === 403, 'chairman cannot scan');
$body = ['mode' => 'chapters', 'cls' => '10th', 'subj' => 'Mathematics', 'images' => [$jpg]];
[$c] = sf_http(8099, '/school/scan_api.php?a=read', ['mode' => 'chapters', 'cls' => '9th', 'subj' => 'Mathematics', 'images' => [$jpg]], $tok['K']); sf_check($c === 403, 'teacher: own subject only');
[$c] = sf_http(8099, '/school/scan_api.php?a=read', ['mode' => 'other'] + $body, $tok['K']); sf_check($c === 400, 'unknown mode');
[$c] = sf_http(8099, '/school/scan_api.php?a=read', ['images' => array_fill(0, 5, $jpg)] + $body, $tok['K']); sf_check($c === 400, 'max 4 photos');
[$c] = sf_http(8099, '/school/scan_api.php?a=read', ['images' => ['data:text/html;base64,PGI+']] + $body, $tok['K']); sf_check($c === 400, 'not a photo');
[$c, $j] = sf_http(8099, '/school/scan_api.php?a=read', $body, $tok['K']); sf_check($c === 409 && $j['noAi'] === true, 'AI off → phone reads');

// ---- second server with the AI switched on (fake AI) ----
$root = realpath(__DIR__ . '/../..');
$env = array_merge(getenv(), ['SCHOOL_AI_API_KEY' => 'test-key', 'SCHOOL_AI_MODEL' => 'test-model', 'SCHOOL_AI_TEST_URL' => 'http://127.0.0.1:8097/ai.php', 'no_proxy' => '127.0.0.1', 'NO_PROXY' => '127.0.0.1']);
$fake = proc_open([PHP_BINARY, '-S', '127.0.0.1:8097', '-t', __DIR__ . '/scan-fake'], [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $p1, null, $env);
$srv  = proc_open([PHP_BINARY, '-S', '127.0.0.1:8096', '-t', $root], [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $p2, null, $env);
try {
    for ($i = 0; $i < 50; $i++) { $s = @fsockopen('127.0.0.1', 8096); $f = @fsockopen('127.0.0.1', 8097); if ($s && $f) break; usleep(100000); }
    [$c, $j] = sf_http(8096, '/school/scan_api.php?a=status', null, $tok['K']); sf_check($c === 200 && $j['ai'] === true, 'status: AI on');
    [$c, $j] = sf_http(8096, '/school/scan_api.php?a=read', $body, $tok['K']);
    sf_check($c === 200 && $j['via'] === 'ai' && array_column($j['chapters'], 'name') === ['Real Numbers', 'Polynomials', 'कबीर की साखी']
        && $j['chapters'][0]['topics'] === ['Euclid’s Division Lemma', 'HCF and LCM'] && $j['uncertain'] === false, 'AI chapters cleaned: ' . json_encode($j, JSON_UNESCAPED_UNICODE));
    [$c, $j] = sf_http(8096, '/school/scan_api.php?a=read', ['mode' => 'lplan'] + $body, $tok['principal']);
    sf_check($c === 200 && $j['topics'] === ['Polynomials — zeroes of a polynomial', 'Relationship between zeroes and coefficients'] && $j['homework'] === 'Ex 2.1 Q1–3' && $j['uncertain'] === true, 'AI lesson plan: ' . json_encode($j, JSON_UNESCAPED_UNICODE));
    sf_check((int)$pdo->query("SELECT COUNT(*) FROM sch_log WHERE x LIKE 'Photo read for Class 10th Mathematics: 3 chapter(s)%'")->fetchColumn() === 1, 'scan logged');
    $pdo->prepare("UPDATE sch_records SET d=? WHERE k='scanUse' AND r=?")->execute([json_encode(['n' => SC_PER_DAY]), 't:K|' . date('Y-m-d')]);
    [$c] = sf_http(8096, '/school/scan_api.php?a=read', $body, $tok['K']); sf_check($c === 429, 'daily limit');
} finally { proc_terminate($fake); proc_terminate($srv); }

// ---- nothing kept: no photo stored, scan counter not in normal load ----
sf_check((int)$pdo->query("SELECT COUNT(*) FROM sch_records WHERE k NOT IN ('settings','teacher','alloc','syl','scanUse')")->fetchColumn() === 0, 'no photo or result stored');
[, $j] = sf_http(8099, '/school/api.php?a=load', null, $tok['principal']);
sf_check(!array_filter($j['records'], fn($r) => str_starts_with($r['k'], 'scan')), 'scan records not in load');
echo "Scan flow: OK\n";
