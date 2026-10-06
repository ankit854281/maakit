<?php
// ============================================================
// Maakit — DATABASE KI NAKAL (backup)
//
// Abhi tak updater sirf FILE ka backup leta tha. Database ka
// nahi. Yani agar database bigad jaye — galat SQL, hosting ki
// gadbad, ya kisi ki galti — to saara kaam chala jata:
// order, grahak, saaman, daam, kitaabein, sab.
//
// File to GitHub par rakhi hai, wapas aa jati hai.
// Database kahin nahi rakha tha.
//
// Ye file poore database ki nakal utar kar web se BAHAR rakh
// deti hai — wahin jahan updater file ka backup rakhta hai.
// Aakhri 8 nakal sambhal kar rakhi jaati hain.
//
// Kaise chalaiye:
//   Admin me login hain  -> Admin > नक़ल दबाइए
//   Chaabi se (bot ke liye):
//     maakit.in/nakal.php?key=<MUNEEM_KEY>
//   Utarne ke liye:
//     maakit.in/nakal.php?get=<file ka naam>
//
// Ye kuchh BADALTI NAHI — sirf padh kar nakal utarti hai.
// ============================================================
require_once __DIR__ . '/inc/fn.php';
require_once __DIR__ . '/inc/nakal-fn.php';

$NAKAL = dirname(__DIR__) . '/maakit-backups/db';   // web se bahar
$RAKHO = 8;                                         // itni nakal sambhaliye

// ---------- kaun chala sakta hai ----------
$u     = user();
$admin = $u && ($u['role'] ?? '') === 'admin';

$chaabi = defined('MUNEEM_KEY') ? (string)MUNEEM_KEY : '';
$diya   = (string)($_GET['key'] ?? '');
$key_ok = $chaabi !== '' && $diya !== '' && hash_equals($chaabi, $diya);

if (!$admin && !$key_ok) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    exit("Band hai. Admin me login kijiye.\n");
}

// ============================================================
// Nakal utarna (download)
// ============================================================
if (!empty($_GET['get'])) {
    $naam = basename((string)$_GET['get']);
    $path = $NAKAL . '/' . $naam;
    // sirf wahi file jo humne hi banayi ho
    if (!preg_match('/^maakit-\d{6}-\d{6}\.sql\.gz$/', $naam) || !is_file($path)) {
        http_response_code(404);
        exit('Nakal nahi mili.');
    }
    header('Content-Type: application/gzip');
    header('Content-Disposition: attachment; filename="' . $naam . '"');
    header('Content-Length: ' . filesize($path));
    readfile($path);
    exit;
}


// ============================================================
$chali = null; $galti = null;
if (($_GET['do'] ?? '') === 'ab' || $key_ok) {
    list($ok, $out) = nakal_banao($pdo, $NAKAL);
    if ($ok) { $chali = $out; nakal_saaf($NAKAL, $RAKHO); }
    else     { $galti = $out; }
}

// bot ke liye chhota jawab
if ($key_ok && !$admin) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['theek' => (bool)$chali, 'nakal' => $chali, 'galti' => $galti], JSON_UNESCAPED_UNICODE);
    exit;
}

$purani = array_reverse(glob($NAKAL . '/maakit-*.sql.gz') ?: []);
$page_title = 'नक़ल — Maakit';
include __DIR__ . '/inc/panel.php';

$mb = fn($b) => $b > 1048576 ? round($b / 1048576, 1) . ' MB' : max(1, round($b / 1024)) . ' KB';
?>
<section><div class="wrap" style="max-width:680px">

  <div class="box">
    <h2 style="margin:0 0 6px">डेटाबेस की नक़ल</h2>
    <p class="lead" style="margin:0 0 4px">
      वेबसाइट की फ़ाइलें GitHub पर रखी हैं — वो कभी भी वापस आ जाती हैं।
      पर <b>डेटाबेस कहीं नहीं रखा था</b>: ऑर्डर, ग्राहक, सामान, दाम, किताबें — सब यहीं था।
      यह पन्ना उसी की नक़ल उतारता है।
    </p>
    <p class="help" style="margin:8px 0 0">
      नक़ल वेबसाइट से <b>बाहर</b> रखी जाती है, जहाँ कोई पहुँच नहीं सकता।
      आख़िरी <?= (int)$RAKHO ?> नक़लें सँभाल कर रखी जाती हैं।
    </p>
  </div>

  <?php if ($galti): ?>
    <div class="err" style="margin-top:12px">नक़ल नहीं बन पाई — <?= h($galti) ?></div>
  <?php elseif ($chali): ?>
    <div class="box" style="margin-top:12px;border-color:var(--ok)">
      <b style="color:var(--ok)">नक़ल बन गई ✓</b>
      <p class="help" style="margin:5px 0 10px">
        <?= (int)$chali['tables'] ?> टेबल · <?= number_format((int)$chali['rows']) ?> पंक्तियाँ ·
        <?= h($mb($chali['naap'])) ?>
      </p>
      <a class="btn btn-brand btn-sm" href="?get=<?= urlencode($chali['naam']) ?>">अपने फ़ोन में उतार लीजिए</a>
    </div>
  <?php endif; ?>

  <form method="get" style="margin-top:14px">
    <input type="hidden" name="do" value="ab">
    <button class="btn btn-brand" style="width:100%">अभी नक़ल उतारिए</button>
  </form>

  <?php if ($purani): ?>
    <h3 class="ghead" style="margin-top:22px">पहले की नक़लें</h3>
    <div class="box">
      <?php foreach ($purani as $p): $n = basename($p); ?>
        <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;padding:7px 0;border-bottom:1px solid var(--line)">
          <div>
            <b style="font-size:14.5px"><?= h(date('d/m/Y, h:i A', (int)@filemtime($p))) ?></b>
            <div class="help"><?= h($mb(filesize($p))) ?></div>
          </div>
          <a class="btn btn-ghost btn-sm" href="?get=<?= urlencode($n) ?>">उतारिए</a>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <div class="box" style="margin-top:16px">
    <b>महीने में एक बार यह ज़रूर कीजिए</b>
    <p class="help" style="margin:6px 0 0">
      ऊपर वाला बटन दबाइए, फिर <b>अपने फ़ोन में उतार लीजिए</b>।<br>
      नक़ल सर्वर पर रहने से डेटाबेस बिगड़ने पर बचाव होता है — पर अगर पूरी
      होस्टिंग ही चली जाए, तो सर्वर वाली नक़ल भी चली जाएगी।
      <b>इसलिए एक नक़ल अपने फ़ोन या लैपटॉप में भी रखिए।</b>
    </p>
    <p class="help" style="margin:8px 0 0">
      वापस डालना हो तो: cPanel → phpMyAdmin → Import → यही फ़ाइल चुन दीजिए।
    </p>
  </div>

</div></section>
<?php include __DIR__ . '/inc/foot.php'; ?>
