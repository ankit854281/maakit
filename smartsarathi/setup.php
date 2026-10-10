<?php
// ============================================================
//  सारथी — एक बार की तैयारी
//
//  phpMyAdmin aur File Manager se jujhne ki zaroorat nahi.
//  Ye panna teen kaam khud kar deta hai:
//
//    1. database se jud kar dekhta hai ki jaankari sahi hai
//    2. inc/config.php khud likh deta hai
//    3. saari table khud bana deta hai
//
//  SURAKSHA — do taale:
//
//    a) Ye panna SIRF tab khulta hai jab config.php abhi bani
//       nahi hai. Ek baar ban gayi, to ye panna khud ko band
//       kar leta hai. Dobara kabhi nahi khulega.
//
//    b) Aur bina sahi database password ke ye kuch nahi karta.
//       Wo password sirf aapko pata hai.
//
//  Isliye kaam ho jaane ke baad is file ko mitana zaroori nahi
//  hai — par mita dein to aur achha.
// ============================================================

$cfg = __DIR__ . '/inc/config.php';

// ---------- ताला 1: काम हो चुका है? ----------
if (is_file($cfg)) {
    http_response_code(403);
    header('Content-Type: text/html; charset=utf-8');
    exit('<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
       . '<div style="font-family:system-ui,sans-serif;max-width:430px;margin:60px auto;padding:0 18px;line-height:1.7">'
       . '<h2 style="color:#1E7A3C">तैयारी हो चुकी है ✓</h2>'
       . '<p>ये पन्ना अब बंद है — सुरक्षा के लिए।</p>'
       . '<p><a href="/" style="color:#7A1F1F;font-weight:700">सारथी खोलिए →</a></p></div>');
}

$err = ''; $done = false; $tables = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $host  = trim((string)($_POST['host']  ?? 'localhost'));
    $name  = trim((string)($_POST['name']  ?? ''));
    $user  = trim((string)($_POST['user']  ?? ''));
    $pass  = (string)($_POST['pass'] ?? '');
    $akey  = (string)($_POST['akey'] ?? '');
    $phone = preg_replace('/\D/', '', (string)($_POST['phone'] ?? '8429393903'));

    if ($name === '' || $user === '') {
        $err = 'database का नाम और user दोनों चाहिए।';
    } elseif (strlen($akey) < 16) {
        $err = 'मालिक की चाबी कम से कम 16 अक्षर की रखिए। (ये आपके पन्ने का ताला है)';
    } elseif (strlen($phone) < 10) {
        $err = 'फ़ोन नंबर सही लिखिए।';
    } else {
        // ---------- ताला 2: पहले जुड़ कर देखिए ----------
        try {
            $db = new PDO("mysql:host=$host;dbname=$name;charset=utf8mb4", $user, $pass,
                          [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        } catch (Throwable $e) {
            $err = 'database से जुड़ नहीं पाया। नाम, user या password में कुछ ग़लत है।';
            $db = null;
        }

        if (!$err && $db) {
            // ---------- table बनाइए ----------
            try {
                $sql = file_get_contents(__DIR__ . '/sql/001-base.sql');
                if ($sql === false) throw new RuntimeException('sql/001-base.sql नहीं मिली');
                $db->exec($sql);

                foreach ($db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN) as $t) $tables[] = $t;
                if (count($tables) < 9) throw new RuntimeException('सारी table नहीं बनीं');

                // Table ban jaana kaafi nahi — kiraye ki line bhi pahunchi
                // ho. Ek baar "ho gaya" keh diya to ye panna band ho jata
                // hai, isliye jhoot bilkul nahi bolna chahiye.
                $st = $db->query("SELECT COUNT(*) FROM settings WHERE k='fare_slab'");
                if ((int)$st->fetchColumn() !== 1) throw new RuntimeException('settings खाली रह गई');

                // ---------- config.php लिखिए ----------
                $q = fn($s) => "'" . str_replace(["\\", "'"], ["\\\\", "\\'"], (string)$s) . "'";
                $out = "<?php\n"
                     . "// सारथी — database settings.\n"
                     . "// ये फ़ाइल setup.php ने बनाई थी। इसे GitHub पर कभी मत डालिए।\n\n"
                     . "define('SR_DB_HOST', " . $q($host) . ");\n"
                     . "define('SR_DB_NAME', " . $q($name) . ");\n"
                     . "define('SR_DB_USER', " . $q($user) . ");\n"
                     . "define('SR_DB_PASS', " . $q($pass) . ");\n\n"
                     . "// मालिक का पन्ना खोलने की चाबी\n"
                     . "define('SR_ADMIN_KEY', " . $q($akey) . ");\n\n"
                     . "define('SR_PHONE', " . $q('+91' . $phone) . ");\n"
                     . "define('SR_PHONE_SHOW', " . $q(substr($phone, 0, 5) . ' ' . substr($phone, 5)) . ");\n";

                if (@file_put_contents($cfg, $out) === false) {
                    $err = 'table तो बन गईं, पर inc/config.php नहीं लिख पाया — उस folder में लिखने की अनुमति नहीं है।';
                } else {
                    @chmod($cfg, 0600);
                    $done = true;
                }
            } catch (Throwable $e) {
                error_log('Sarathi setup: ' . $e->getMessage());
                $err = 'table बनाते वक़्त दिक्कत आई। database user को सारे अधिकार (ALL PRIVILEGES) दिए हैं?';
            }
        }
    }
}

function v($k, $d = '') { return htmlspecialchars((string)($_POST[$k] ?? $d), ENT_QUOTES, 'UTF-8'); }
?><!doctype html>
<html lang="hi">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>सारथी — तैयारी</title>
<style>
  *{box-sizing:border-box}
  body{margin:0;background:#FBF4E6;color:#241A16;padding:22px 16px;
       font-family:system-ui,-apple-system,"Noto Sans Devanagari",sans-serif;line-height:1.65}
  .w{max-width:470px;margin:0 auto}
  h1{font-size:30px;margin:0 0 4px;color:#7A1F1F}
  .s{color:#6B5D55;margin:0 0 22px}
  .c{background:#fff;border:1px solid #E3D8C6;border-radius:15px;padding:18px;margin-bottom:14px}
  label{display:block;font-size:15px;color:#6B5D55;margin:14px 0 6px}
  label:first-child{margin-top:0}
  input{width:100%;padding:13px;border:2px solid #E3D8C6;border-radius:11px;
        font:inherit;font-size:17px;background:#fff}
  input:focus{outline:0;border-color:#7A1F1F}
  .h{font-size:13px;color:#8C8276;margin:5px 0 0}
  button{width:100%;margin-top:20px;background:#7A1F1F;color:#fff;border:0;border-radius:12px;
         padding:16px;font:inherit;font-size:18px;font-weight:700;min-height:56px;cursor:pointer}
  .e{background:#FBE9E7;border:1px solid #E8BDB7;color:#8A2A1F;border-radius:12px;
     padding:13px 15px;margin-bottom:14px;font-weight:600}
  .ok{background:#E8F3EA;border:1px solid #BFDCC6;color:#1C5130;border-radius:12px;padding:16px 18px}
  .ok h2{margin:0 0 8px;font-size:21px}
  .t{display:flex;flex-wrap:wrap;gap:6px;margin:12px 0}
  .t span{background:#fff;border:1px solid #BFDCC6;border-radius:7px;padding:4px 9px;font-size:13px}
  .go{display:block;text-align:center;background:#7A1F1F;color:#fff;text-decoration:none;
      border-radius:12px;padding:16px;font-weight:700;font-size:18px;margin-top:14px}
  .n{font-size:14px;color:#8C8276;margin-top:18px}
</style>
</head>
<body><div class="w">

<?php if ($done): ?>

  <h1>हो गया ✓</h1>
  <p class="s">सारथी तैयार है।</p>
  <div class="ok">
    <h2><?= count($tables) ?> table बन गईं</h2>
    <div class="t"><?php foreach ($tables as $t): ?><span><?= htmlspecialchars($t, ENT_QUOTES, 'UTF-8') ?></span><?php endforeach; ?></div>
    <p style="margin:0">अब ये पन्ना अपने आप बंद हो गया है — दोबारा नहीं खुलेगा।</p>
  </div>
  <a class="go" href="/admin/">मालिक का पन्ना खोलिए →</a>
  <p class="n">वहाँ वही चाबी डालिए जो अभी आपने बनाई। फिर Maakit को ग्राहक
     के रूप में जोड़िए और अपना सारथी जोड़िए।</p>

<?php else: ?>

  <h1>सारथी</h1>
  <p class="s">एक बार की तैयारी — बस यही एक बार भरना है।</p>

  <?php if ($err): ?><div class="e"><?= htmlspecialchars($err, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>

  <form method="post" class="c" autocomplete="off">
    <label>Database का पूरा नाम</label>
    <input name="name" value="<?= v('name') ?>" placeholder="जैसे sbs81w9g9z45_sarathi" required autofocus>
    <p class="h">cPanel → MySQL Databases में जो नाम बना था, पूरा वाला</p>

    <label>Database का user</label>
    <input name="user" value="<?= v('user') ?>" placeholder="जैसे sbs81w9g9z45_sarathi" required>

    <label>उसका password</label>
    <input name="pass" type="password" value="" required>
    <p class="h">वही जो आपने Password Generator से बनाया था</p>

    <label>मालिक के पन्ने की चाबी</label>
    <input name="akey" value="<?= v('akey') ?>" placeholder="कम से कम 16 अक्षर" required minlength="16">
    <p class="h"><b>ये आप अभी बना रहे हैं</b> — इसी से आपका पन्ना खुलेगा।
       कुछ भी लंबा लिख दीजिए और याद रख लीजिए। किसी को मत बताइए।</p>

    <label>फ़ोन नंबर</label>
    <input name="phone" type="tel" inputmode="numeric" value="<?= v('phone', '8429393903') ?>" required>
    <p class="h">जो सारथी के पन्नों पर दिखेगा</p>

    <input type="hidden" name="host" value="localhost">
    <button type="submit">तैयारी पूरी कीजिए</button>
  </form>

  <p class="n">ये पन्ना सिर्फ़ एक बार चलता है। काम होते ही अपने आप बंद हो जाएगा,
     और बिना सही database password के ये कुछ करता भी नहीं।</p>

<?php endif; ?>

</div></body></html>
