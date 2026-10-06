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

    $gz = @gzopen($path, 'wb6');
    if (!$gz) return [false, 'file nahi ban payi'];

    $w = function ($s) use ($gz) { gzwrite($gz, $s); };

    $w("-- Maakit database ki nakal\n-- " . date('d/m/Y H:i') . "\n");
    $w("-- Wapas daalne ka tarika: phpMyAdmin > Import > yahi file\n\n");
    $w("SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n");

    // Fetch mode bulane wali file par nahi chhodna — warna "SHOW CREATE TABLE"
    // ka jawab galat khane se uthta hai aur nakal bekar ban jati hai.
    $tables = [];
    foreach ($pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_NUM) as $r) { $tables[] = $r[0]; }

    $ginti = 0;
    foreach ($tables as $t) {
        $cr = $pdo->query("SHOW CREATE TABLE `$t`")->fetch(PDO::FETCH_NUM);
        if (empty($cr[1])) { continue; }                 // bana hi nahi to chhod dijiye
        $w("DROP TABLE IF EXISTS `$t`;\n" . $cr[1] . ";\n\n");

        // thode-thode karke — taaki badi table par memory na bhare
        $n = (int)($pdo->query("SELECT COUNT(*) FROM `$t`")->fetch(PDO::FETCH_NUM)[0] ?? 0);
        $ginti += $n;
        for ($off = 0; $off < $n; $off += 500) {
            $rows = $pdo->query("SELECT * FROM `$t` LIMIT 500 OFFSET $off")->fetchAll(PDO::FETCH_ASSOC);
            if (!$rows) break;
            foreach ($rows as $row) {
                $vals = [];
                foreach ($row as $v) {
                    $vals[] = ($v === null) ? 'NULL' : $pdo->quote((string)$v);
                }
                $w("INSERT INTO `$t` VALUES (" . implode(',', $vals) . ");\n");
            }
        }
        $w("\n");
    }

    $w("SET FOREIGN_KEY_CHECKS=1;\n");
    gzclose($gz);
    @chmod($path, 0640);

    return [true, ['naam' => $naam, 'naap' => filesize($path), 'tables' => count($tables), 'rows' => $ginti]];
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
