<?php
// ============================================================
// Database ki nakal utarne ka kaam — sirf function.
//
// Alag file isliye hai ki do jagah se chalta hai:
//   nakal.php        — jab Ankit khud dabaye
//   maakit-update.php — SQL chalne se theek pehle, apne aap
//
// Isme koi pehra nahi hai — pehra bulane wali file rakhti hai.
// Isme kisi aur file ki zaroorat bhi nahi, sirf PDO chahiye.
// ============================================================

/**
 * Poore database ki nakal utariye.
 *
 * mysqldump shared hosting par aksar band hota hai, isliye nakal
 * PHP se hi banti hai — thodi dheemi, par har jagah chalti hai.
 * Maakit ka database chhota hai, isliye farak nahi padta.
 */
function nakal_banao(PDO $pdo, $dir) {
    if (!is_dir($dir) && !@mkdir($dir, 0750, true)) return [false, 'jagah nahi ban payi'];

    $naam = 'maakit-' . date('ymd-His') . '.sql.gz';
    $path = $dir . '/' . $naam;

    // Publish only a complete archive; simultaneous backups must not overwrite it.
    $lock = @fopen($dir . '/.backup-lock', 'c');
    if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) { if ($lock) fclose($lock); return [false, 'nakal abhi ban rahi hai']; }
    if (is_file($path)) { flock($lock, LOCK_UN); fclose($lock); return [false, 'ek second baad dobara kijiye']; }
    $tmp = $path . '.part';
    $gz = @gzopen($tmp, 'wb6');
    if (!$gz) { flock($lock, LOCK_UN); fclose($lock); return [false, 'file nahi ban payi']; }
    @chmod($tmp, 0640);
    $snapshot = false;
    try {
    if ($pdo->inTransaction()) throw new RuntimeException('Backup requires its own snapshot');
    $pdo->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
    $pdo->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT');
    $snapshot = true;
    $w = function ($s) use ($gz) {
        $offset=0; $length=strlen($s);
        while ($offset<$length) { $n=gzwrite($gz,substr($s,$offset)); if (!$n) throw new RuntimeException('Backup write failed'); $offset+=$n; }
    };
    $ident = fn($name) => '`' . str_replace('`', '``', $name) . '`';

    $w("-- Maakit database ki nakal\n-- " . date('d/m/Y H:i') . "\n");
    $w("-- Wapas daalne ka tarika: phpMyAdmin > Import > yahi file\n\n");
    $w("SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n");

    // Fetch mode bulane wali file par nahi chhodna — warna "SHOW CREATE TABLE"
    // ka jawab galat khane se uthta hai aur nakal bekar ban jati hai.
    $tables = [];
    foreach ($pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_NUM) as $r) { $tables[] = $r[0]; }

    $ginti = 0;
    foreach ($tables as $t) {
        $table = $ident($t);
        $cr = $pdo->query("SHOW CREATE TABLE $table")->fetch(PDO::FETCH_NUM);
        if (empty($cr[1])) { continue; }                 // bana hi nahi to chhod dijiye
        $w("DROP TABLE IF EXISTS $table;\n" . $cr[1] . ";\n\n");

        // thode-thode karke — taaki badi table par memory na bhare
        $n = (int)($pdo->query("SELECT COUNT(*) FROM $table")->fetch(PDO::FETCH_NUM)[0] ?? 0);
        $ginti += $n;
        for ($off = 0; $off < $n; $off += 500) {
            $rows = $pdo->query("SELECT * FROM $table LIMIT 500 OFFSET $off")->fetchAll(PDO::FETCH_ASSOC);
            if (!$rows) break;
            foreach ($rows as $row) {
                $vals = [];
                foreach ($row as $v) {
                    $vals[] = ($v === null) ? 'NULL' : $pdo->quote((string)$v);
                }
                $w("INSERT INTO $table VALUES (" . implode(',', $vals) . ");\n");
            }
        }
        $w("\n");
    }

    $w("SET FOREIGN_KEY_CHECKS=1;\n");
    if (!gzclose($gz)) throw new RuntimeException('Backup close failed');
    $gz = null;
    $pdo->commit(); $snapshot = false;
    if (!@rename($tmp,$path)) throw new RuntimeException('Backup publish failed');
    @chmod($path, 0640);

    return [true, ['naam' => $naam, 'naap' => filesize($path), 'tables' => count($tables), 'rows' => $ginti]];
    } catch (Throwable $e) {
        if ($snapshot && $pdo->inTransaction()) $pdo->rollBack();
        if (is_resource($gz)) gzclose($gz);
        @unlink($tmp);
        error_log('Maakit backup failed: '.$e->getMessage());
        return [false, 'nakal poori nahi bani; dobara kijiye'];
    } finally { flock($lock, LOCK_UN); fclose($lock); }
}

function nakal_saaf($dir, $rakho) {
    $f = glob($dir . '/maakit-*.sql.gz') ?: [];
    sort($f);
    while (count($f) > $rakho) { @unlink(array_shift($f)); }
}

/**
 * Hafte me ek baar apne aap nakal utar lijiye.
 *
 * Ye tab chalta hai jab Ankit (ya koi admin) panel kholta hai.
 * Agar pichhli nakal 7 din se purani hai, to ek nayi bana deta hai.
 *
 * Isse na cron ki zaroorat padti hai, na kuchh yaad rakhne ki.
 * Dhyan: jaanch sirf file ki tareekh dekh kar hoti hai (bahut
 * sasta kaam), nakal tabhi banti hai jab sach me zaroorat ho.
 */
function nakal_apne_aap(PDO $pdo, $dir, $din = 7, $rakho = 8) {
    $f = glob($dir . '/maakit-*.sql.gz') ?: [];
    if ($f) {
        sort($f);
        $naya = (int)@filemtime(end($f));
        if ($naya > time() - ($din * 86400)) return null;   // abhi taaza hai
    }
    // Ek hi waqt me do nakal na banein
    $tala = $dir . '/.ban-rahi';
    if (is_file($tala) && @filemtime($tala) > time() - 600) return null;
    if (!is_dir($dir)) @mkdir($dir, 0750, true);
    @touch($tala);

    try { list($ok, $out) = nakal_banao($pdo, $dir); }
    catch (Throwable $e) { $ok = false; $out = 'galti'; }

    @unlink($tala);
    if ($ok) { nakal_saaf($dir, $rakho); return $out['naam']; }
    return false;
}
