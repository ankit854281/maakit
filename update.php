<?php
// ============================================================
//  MAAKIT — UPDATER
//
//  Har update par bas iska link kholna hai:
//      maakit.in/update.php?key=<CHAABI>
//
//  Ye khud:
//    - GitHub se naya code utha legi
//    - purani file ka backup rakh legi
//    - DATABASE ki nakal utar legi (SQL chalne se pehle)
//    - nayi file apni-apni jagah bitha degi
//    - nayi SQL khud chala degi
//    - config.php aur uploads/ ko HAATH NAHI LAGAYEGI
//
//  ---- Purani wali se farak ----
//
//  Pehle chaabi isi file me likhi thi. Isliye ye file GitHub par
//  nahi ja sakti thi, aur isme koi sudhaar aapke server tak apne
//  aap nahi pahunchta tha — har baar haath se badalni padti.
//
//  Ab chaabi config.php me rehti hai (jo kabhi GitHub par nahi
//  jati). Isliye YE file GitHub par rah sakti hai, aur KHUD KO BHI
//  update kar leti hai. Yani aage se kuchh haath se nahi badalna.
// ============================================================

// ---- chaabi config.php se aati hai ----
// config.php me ye line honi chahiye:
//     define('UPDATE_KEY', 'aapki lambi anjaan line');
@include_once __DIR__ . '/config.php';

// ---- naya code kahan se aayega (ye badalna mat) ----
const MK_ZIP = 'https://codeload.github.com/ankit854281/maakit/zip/refs/heads/main';

// ---- jo file kabhi nahi badlegi ----
const MK_CHHODO = [
    'config.php',            // database ka password aur chaabi
    'maakit-update.php',     // purani wali updater — usko chhediye mat
    'README.md',
    'config.sample.php',
    '.cpanel.yml',
    '.gitignore',
];
// Dhyan: 'update.php' (yani khud ye file) is soochi me NAHI hai.
// PHP poori file pehle padh leta hai, phir chalata hai — isliye
// chalte-chalte apne aap ko badal lena surakshit hai. Isi wajah se
// aage koi sudhaar haath se nahi chadhana padega.
const MK_CHHODO_FOLDER = ['uploads', '.git', '.github'];

// ============================================================

@set_time_limit(300);
@ini_set('memory_limit', '256M');
header('Content-Type: text/html; charset=utf-8');

$ROOT   = __DIR__;
$BACKUP = dirname(__DIR__) . '/maakit-backups';   // web se bahar — koi dekh nahi sakta
$STATE  = $BACKUP . '/state.json';

// ---------- doosra darwaza: maalik (admin) ka login ----------
// Chaabi dhoondhna mushkil tha, isliye ab ek aur raasta: jo maakit.in
// par ADMIN login kiye hue hai, uske liye bhi ye panna khulta hai.
// Har baar database se pakka karte hain ki wo abhi bhi active admin
// hai. "Haan, lagao" (POST) par csrf bhi milate hain — taaki koi
// anjaan link admin ke phone se update na chala de. inc/fn.php jaan-
// boojh kar NAHI lagaya: site toot bhi jaye to updater chalna chahiye.
function mk_admin_csrf() {
    if (!defined('DB_HOST')) return null;
    try {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            ini_set('session.use_strict_mode', '1');
            $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
            session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax', 'secure' => $https]);
            session_start();
        }
        $id = (int)($_SESSION['user']['id'] ?? 0);
        if ($id <= 0 || ($_SESSION['user']['role'] ?? '') !== 'admin') return null;
        $db = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS,
                      [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $st = $db->prepare("SELECT 1 FROM users WHERE id=? AND role='admin' AND active=1");
        $st->execute([$id]);
        if (!$st->fetchColumn()) return null;
        if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
        return (string)$_SESSION['csrf'];
    } catch (Throwable $e) { return null; }
}
$admin_csrf = null;
if (!isset($_GET['key']) && !isset($_POST['key'])) {
    $admin_csrf = mk_admin_csrf();
    if ($admin_csrf !== null && $_SERVER['REQUEST_METHOD'] === 'POST'
        && !hash_equals($admin_csrf, (string)($_POST['csrf'] ?? ''))) {
        $admin_csrf = null;   // purana/naqli form — chalne mat do
    }
}

// ---------- chaabi ki jaanch ----------
$chaabi = defined('UPDATE_KEY') ? (string)UPDATE_KEY : '';

if ($admin_csrf === null && ($chaabi === '' || strlen($chaabi) < 12)) {
    // Chaabi daali hi nahi — saaf-saaf bata dijiye ki kya karna hai
    http_response_code(503);
    exit('<!doctype html><meta charset="utf-8">'
       . '<meta name="viewport" content="width=device-width,initial-scale=1">'
       . '<div style="font-family:system-ui,sans-serif;max-width:560px;margin:40px auto;padding:0 18px;line-height:1.65;color:#241A14">'
       . '<h2 style="color:#7A1F1F">चाबी नहीं मिली</h2>'
       . '<p>यह updater चलने के लिए <b>config.php</b> में एक लाइन चाहिए:</p>'
       . '<pre style="background:#F4EDE0;padding:12px;border-radius:10px;overflow:auto">'
       . "define('UPDATE_KEY', 'यहाँ-एक-लंबी-अनजान-लाइन');</pre>"
       . '<p><b>सबसे आसान:</b> पहले <a href="/login.php?as=team" style="color:#7A1F1F">maakit.in पर admin login</a> कीजिए, फिर यही पन्ना दोबारा खोलिए।</p>'
       . '<p>या cPanel → File Manager → <b>public_html</b> → config.php पर दायाँ क्लिक → '
       . '<b>Edit</b> → सबसे नीचे यह लाइन जोड़कर <b>Save</b>।</p>'
       . '<p style="color:#6C5B4D;font-size:14px">चाबी कोई भी लंबी अनजान लाइन हो सकती है। '
       . 'किसी को मत दीजिए — इससे वेबसाइट बदली जा सकती है।</p></div>');
}

$diya = (string)($_GET['key'] ?? $_POST['key'] ?? '');
if ($admin_csrf === null && ($chaabi === '' || !hash_equals($chaabi, $diya))) {
    usleep(400000);                          // andaaza lagane walon ko thaka do
    http_response_code(404);
    exit('<!doctype html><meta charset="utf-8"><p style="font-family:sans-serif;padding:24px">Not found.</p>');
}

// ---------- chhoti madad ----------
function mk_state($BACKUP, $STATE) {
    if (!is_dir($BACKUP)) @mkdir($BACKUP, 0750, true);
    $s = @json_decode(@file_get_contents($STATE), true);
    return is_array($s) ? $s : ['sql' => [], 'runs' => []];
}
function mk_save($STATE, $s) { @file_put_contents($STATE, json_encode($s, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)); }

function mk_skip($rel) {
    $rel = ltrim(str_replace('\\', '/', $rel), '/');
    if (in_array($rel, MK_CHHODO, true)) return true;
    $pehla = explode('/', $rel)[0];
    if (in_array($pehla, MK_CHHODO_FOLDER, true)) return true;
    if (substr($rel, -4) === '.yml' && strpos($rel, '/') === false) return true;
    return false;
}

function mk_get($url) {
    if (function_exists('curl_init')) {
        $c = curl_init($url);
        curl_setopt_array($c, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 120, CURLOPT_USERAGENT => 'maakit-updater',
        ]);
        $d = curl_exec($c);
        $code = curl_getinfo($c, CURLINFO_HTTP_CODE);
        $err = curl_error($c);
        curl_close($c);
        if ($d === false || $code >= 400) return [null, "GitHub se nahi aaya (code $code) $err"];
        return [$d, null];
    }
    if (ini_get('allow_url_fopen')) {
        $ctx = stream_context_create(['http' => ['timeout' => 120, 'user_agent' => 'maakit-updater']]);
        $d = @file_get_contents($url, false, $ctx);
        if ($d === false) return [null, 'GitHub se nahi aaya.'];
        return [$d, null];
    }
    return [null, 'Ye server bahar se file nahi utha sakta (na curl, na allow_url_fopen). Hosting wale se kahiye.'];
}

function mk_rm($d) {
    if (!is_dir($d)) return;
    foreach (scandir($d) as $f) {
        if ($f === '.' || $f === '..') continue;
        $p = "$d/$f";
        is_dir($p) ? mk_rm($p) : @unlink($p);
    }
    @rmdir($d);
}

// ============================================================
//  KAAM SHURU — sirf tab jab button dabaya gaya ho
// ============================================================
$chala = ($_SERVER['REQUEST_METHOD'] === 'POST');
$log = []; $err = null; $bad = [];

if ($chala) {
    $st = mk_state($BACKUP, $STATE);

    // ---- 1. zip utha lao ----
    list($zipData, $e) = mk_get(MK_ZIP);
    if ($e) { $err = $e; }

    $tmp = null; $work = null;
    if (!$err) {
        if (!class_exists('ZipArchive')) {
            $err = 'Is server par ZipArchive nahi hai. Hosting wale se chalu karwaiye.';
        } else {
            $tmp = $BACKUP . '/naya-' . date('ymd-His') . '.zip';
            if (@file_put_contents($tmp, $zipData) === false) {
                $err = 'Zip sambhaali nahi ja saki. ' . htmlspecialchars($BACKUP) . ' me likhne ki ijaazat nahi hai.';
            }
        }
    }

    // ---- 2. kholo ----
    if (!$err) {
        $work = $BACKUP . '/naya-' . date('ymd-His');
        $zip = new ZipArchive();
        if ($zip->open($tmp) !== true) { $err = 'Zip kharab aayi.'; }
        else { $zip->extractTo($work); $zip->close(); @unlink($tmp); }
    }

    // GitHub zip ke andar ek hi folder hota hai
    $src = null;
    if (!$err) {
        foreach (scandir($work) as $f) {
            if ($f !== '.' && $f !== '..' && is_dir("$work/$f")) { $src = "$work/$f"; break; }
        }
        if (!$src || !is_file("$src/index.php")) $err = 'Zip me website nahi mili.';
    }

    // ---- 3. backup + file bithao ----
    $naye = 0; $badle = 0; $waise = 0;
    $bkdir = $BACKUP . '/backup-' . date('ymd-His');

    if (!$err) {
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($src, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST);

        foreach ($it as $file) {
            $rel = ltrim(str_replace('\\', '/', substr($file->getPathname(), strlen($src))), '/');
            if ($rel === '' || mk_skip($rel)) continue;

            $to = "$ROOT/$rel";
            if ($file->isDir()) { if (!is_dir($to)) @mkdir($to, 0755, true); continue; }

            $nayaMd5 = md5_file($file->getPathname());
            if (is_file($to)) {
                if (md5_file($to) === $nayaMd5) { $waise++; continue; }   // same hai, chhod do
                if (!is_dir(dirname("$bkdir/$rel"))) @mkdir(dirname("$bkdir/$rel"), 0755, true);
                @copy($to, "$bkdir/$rel");
                $badle++;
            } else { $naye++; }

            if (!is_dir(dirname($to))) @mkdir(dirname($to), 0755, true);
            if (@copy($file->getPathname(), $to)) { @chmod($to, 0644); }
            else { $bad[] = $rel; }
        }
        $log[] = ['naye' => $naye, 'badle' => $badle, 'waise' => $waise];
    }

    // ---- 4. nayi SQL chalao (sirf sql/ folder wali, jo do baar chalne par bhi safe hain) ----
    $sqlChale = [];
    if (!$err && is_file("$ROOT/config.php")) {
        // mysqli ko exception phenkne se roko — warna ek chhoti galti poora page tod degi
        if (function_exists('mysqli_report')) mysqli_report(MYSQLI_REPORT_OFF);
        try {
            require_once "$ROOT/config.php";
            $my = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
            if ($my->connect_errno) {
                $bad[] = 'Database se judne me dikkat — SQL nahi chali. config.php dekh lijiye.';
            } else {
                $my->set_charset('utf8mb4');
                $files = glob("$ROOT/sql/*.sql") ?: [];
                sort($files);                                    // 001, 002, 003 … isi kram me

                // ---- SQL chalne se PEHLE database ki nakal ----
                // Yahi sabse khatre ka pal hai: ek galat SQL poora kaam mita
                // sakti hai. Isliye chhoone se pehle nakal utar lete hain.
                // Nakal na ban paye to bhi update rukta nahi — bas bata dete hain.
                $nakalKyaHui = null;
                $bachiHui = array_filter($files, fn($f) => ($st['sql'][basename($f)] ?? '') !== md5_file($f));
                if ($bachiHui && is_file("$ROOT/inc/nakal-fn.php")) {
                    try {
                        require_once "$ROOT/inc/nakal-fn.php";
                        $pdoNak = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
                                          DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
                        list($nok, $nout) = nakal_banao($pdoNak, $BACKUP . '/db');
                        if ($nok) { nakal_saaf($BACKUP . '/db', 8); $nakalKyaHui = $nout['naam']; }
                        else      { $nakalKyaHui = false; }
                        $pdoNak = null;
                    } catch (Throwable $e) { $nakalKyaHui = false; }
                }
                foreach ($files as $f) {
                    $naam = basename($f);
                    $hash = md5_file($f);
                    if (($st['sql'][$naam] ?? '') === $hash) continue;       // pehle chal chuki
                    try {
                        if ($my->multi_query(file_get_contents($f))) {
                            do { if ($r = $my->store_result()) $r->free(); }
                            while ($my->more_results() && @$my->next_result());
                            if ($my->errno) { $bad[] = "$naam — " . $my->error; }
                            else { $st['sql'][$naam] = $hash; $sqlChale[] = $naam; }
                        } else { $bad[] = "$naam — " . $my->error; }
                    } catch (Throwable $t) { $bad[] = "$naam — " . $t->getMessage(); }
                }
                $my->close();
            }
        } catch (Throwable $t) { $bad[] = 'SQL: ' . $t->getMessage(); }
    }

    // ---- 5. safaai ----
    if ($work) mk_rm($work);
    $purane = glob($BACKUP . '/backup-*') ?: [];
    sort($purane);
    while (count($purane) > 3) { mk_rm(array_shift($purane)); }   // sirf aakhri 3 backup

    if (!$err) {
        $st['runs'][] = ['kab' => date('Y-m-d H:i'), 'naye' => $naye, 'badle' => $badle];
        $st['runs'] = array_slice($st['runs'], -10);
        mk_save($STATE, $st);
    }
}

// ---- abhi kaun sa version chal raha hai ----
$ver = '?';
if (is_file("$ROOT/inc/version.php")) {
    $txt = file_get_contents("$ROOT/inc/version.php");
    if (preg_match("/MAAKIT_VERSION'\s*,\s*'([^']+)'/", $txt, $m)) $ver = $m[1];
    if (preg_match("/MAAKIT_VERSION_DATE'\s*,\s*'([^']+)'/", $txt, $m2)) $ver .= ' · ' . $m2[1];
}
$stNow = mk_state($BACKUP, $STATE);
$last  = end($stNow['runs']) ?: null;
?>
<!doctype html>
<html lang="hi"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>Maakit — Update</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:system-ui,-apple-system,'Segoe UI',sans-serif;background:#FBF4E6;color:#2B1A12;
  max-width:620px;margin:0 auto;padding:26px 16px 60px;line-height:1.6}
h1{font-size:26px;letter-spacing:-.5px;margin-bottom:4px}
h1 span{color:#7A1F1F}
.now{color:#6B4A2F;font-size:15px;margin-bottom:20px}
.card{background:#fff;border:2px solid #EAD7B5;border-radius:16px;padding:18px 20px;margin-bottom:14px}
.big{display:block;width:100%;background:#7A1F1F;color:#fff;border:0;border-radius:14px;
  padding:17px;font:inherit;font-size:18px;font-weight:700;cursor:pointer}
.big:active{opacity:.85}
.ok{background:#E5F3EA;border-color:#9ED3B4}
.ok b{color:#14502F}
.bad{background:#FDECEC;border-color:#E8C9C9}
.bad b{color:#B02A2A}
.num{display:flex;gap:10px;margin-top:12px;flex-wrap:wrap}
.num div{flex:1 1 100px;background:#F4E6CB;border-radius:11px;padding:10px 13px}
.num span{display:block;font-size:12px;color:#6B4A2F;font-weight:600}
.num b{font-size:24px}
code{font-family:ui-monospace,Menlo,monospace;font-size:13.5px;background:#F4E6CB;
  border-radius:5px;padding:1px 6px}
ul{margin:8px 0 0 20px;font-size:14.5px}
.help{font-size:14px;color:#6B4A2F;margin-top:10px}
a{color:#7A1F1F}
</style></head><body>

<h1>Maakit — <span>Update</span></h1>
<p class="now">अभी चल रहा है: <b>v<?= htmlspecialchars($ver) ?></b>
<?php if ($last): ?><br>पिछली बार: <?= htmlspecialchars($last['kab']) ?><?php endif; ?></p>

<?php if (!$chala): ?>

  <div class="card">
    <b style="font-size:18px;display:block;margin-bottom:6px">नया कोड लगा दें?</b>
    <p style="font-size:15px;color:#6B4A2F">
      GitHub से नया कोड उठाकर लग जाएगा। पुरानी फ़ाइलों का बैकअप पहले रख लिया जाएगा।<br>
      <code>config.php</code> और <code>uploads/</code> को हाथ नहीं लगाया जाएगा।
    </p>
    <form method="post" style="margin-top:16px">
      <?php if ($admin_csrf !== null): ?>
      <input type="hidden" name="csrf" value="<?= htmlspecialchars($admin_csrf) ?>">
      <?php else: ?>
      <input type="hidden" name="key" value="<?= htmlspecialchars($diya) ?>">
      <?php endif; ?>
      <button class="big" type="submit">हाँ, अभी लगा दीजिए</button>
    </form>
    <p class="help">कुछ भी गड़बड़ हो तो backup <code>maakit-backups</code> फ़ोल्डर में पड़ा है —
      वह public_html से बाहर है, कोई देख नहीं सकता।</p>
  </div>

<?php elseif ($err): ?>

  <div class="card bad">
    <b style="font-size:18px">रुक गया — कुछ नहीं बदला</b>
    <p style="margin-top:6px"><?= htmlspecialchars($err) ?></p>
    <p class="help">वेबसाइट जैसी थी वैसी ही चल रही है। यह संदेश Claude को भेज दीजिए।</p>
  </div>

<?php else: $l = $log[0] ?? ['naye'=>0,'badle'=>0,'waise'=>0]; ?>

  <div class="card ok">
    <b style="font-size:19px">हो गया ✓</b>
    <div class="num">
      <div><span>नई फ़ाइलें</span><b><?= (int)$l['naye'] ?></b></div>
      <div><span>बदली गईं</span><b><?= (int)$l['badle'] ?></b></div>
      <div><span>पहले से ठीक</span><b><?= (int)$l['waise'] ?></b></div>
    </div>
    <?php if ($sqlChale): ?>
      <p style="margin-top:12px;font-size:15px">डेटाबेस भी तैयार कर दिया:
        <?php foreach ($sqlChale as $s): ?><code><?= htmlspecialchars($s) ?></code> <?php endforeach; ?></p>
    <?php endif; ?>
    <?php if (!empty($nakalKyaHui)): ?>
      <p style="margin-top:8px;font-size:14px;opacity:.85">
        छूने से पहले डेटाबेस की नक़ल रख ली थी — <code><?= htmlspecialchars($nakalKyaHui) ?></code></p>
    <?php elseif (isset($nakalKyaHui) && $nakalKyaHui === false): ?>
      <p style="margin-top:8px;font-size:14px;color:#A33427">
        ध्यान दीजिए: डेटाबेस की नक़ल नहीं बन पाई। Admin → नक़ल से एक बार ख़ुद उतार लीजिए।</p>
    <?php endif; ?>
    <?php if (!$l['naye'] && !$l['badle']): ?>
      <p style="margin-top:12px;font-size:15px">सब कुछ पहले से नया था — कुछ बदलने की ज़रूरत नहीं पड़ी।</p>
    <?php endif; ?>
  </div>

  <?php if ($bad): ?>
    <div class="card bad">
      <b>ये नहीं हो पाईं</b>
      <ul><?php foreach ($bad as $b): ?><li><?= htmlspecialchars($b) ?></li><?php endforeach; ?></ul>
      <p class="help">यह सूची Claude को भेज दीजिए।</p>
    </div>
  <?php endif; ?>

  <div class="card">
    <b>अब देख लीजिए</b>
    <ul>
      <li><a href="/" target="_blank">वेबसाइट खोलिए</a> — ठीक दिख रही है?</li>
      <li>नीचे footer में version बदला हुआ दिखना चाहिए</li>
      <li><a href="/admin/" target="_blank">Admin panel</a> खुल रहा है?</li>
    </ul>
  </div>

<?php endif; ?>

</body></html>
