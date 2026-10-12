<?php
// ============================================================
//  सारथी — बाहर वालों के पन्ने (बिना लॉगिन)
//
//    index.php  — rider login
//    apply.php  — rider banne ki arzi (Maakit.in se yahan aaya)
//
//  Smart Sarathi SIRF riders ka app hai. Grahak (sawari bookh
//  karna, parcel bhejna, track karna) maakit.in par jaata hai —
//  maakit.in/ride.php → API → yahan "kaam". book.php/track.php
//  ab wahin bhejte hain (sr_maakit_url()).
//  Nayi table yahin apne aap ban jaati hai, taaki maalik ko
//  phpMyAdmin kholna na pade.
// ============================================================
require_once __DIR__ . '/fn.php';
require_once __DIR__ . '/lang.php';
require_once __DIR__ . '/kaam.php';

/** Rider ki arziyan — pehli baar khulte hi table ban jaati hai */
function sr_apply_table(PDO $pdo) {
    static $done = false;
    if ($done) return;
    $pdo->exec("CREATE TABLE IF NOT EXISTS applications (
        id          INT AUTO_INCREMENT PRIMARY KEY,
        name        VARCHAR(80)  NOT NULL,
        mobile      VARCHAR(15)  NOT NULL,
        village     VARCHAR(80)  DEFAULT NULL,
        vehicle     VARCHAR(30)  DEFAULT NULL,
        dl_number   VARCHAR(30)  DEFAULT NULL,
        rc_number   VARCHAR(30)  DEFAULT NULL,
        hours       VARCHAR(40)  DEFAULT NULL,
        note        VARCHAR(300) DEFAULT NULL,
        status      ENUM('new','approved','rejected') NOT NULL DEFAULT 'new',
        rider_id    INT DEFAULT NULL,
        created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        KEY (status), KEY (mobile)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $done = true;
}

/** Seedhe grahak (website se parcel) — ek "client" ki tarah, apne aap banta hai */
function sr_direct_client(PDO $pdo) {
    $st = $pdo->query("SELECT * FROM clients WHERE name='सीधे ग्राहक (वेबसाइट)' LIMIT 1");
    if ($c = $st->fetch()) return $c;
    $pdo->prepare("INSERT INTO clients (name, contact, fee, extra_fee, active) VALUES ('सीधे ग्राहक (वेबसाइट)', 'smartsarathi book.php', 0, 0, 1)")->execute();
    return $pdo->query("SELECT * FROM clients WHERE id=" . (int)$pdo->lastInsertId())->fetch();
}

/** Grahak wale panne Maakit par hain — wahan ka pata (config me SR_MAAKIT_URL badal sakte hain) */
function sr_maakit_url($path = '/') {
    $base = defined('SR_MAAKIT_URL') ? rtrim(SR_MAAKIT_URL, '/') : 'https://maakit.in';
    return $base . $path;
}

/** Ek session me kitni baar — bot aur galti se baar-baar bhejne se bachav */
function sr_public_limit($key, $max) {
    $k = 'srp_' . $key;
    $n = (int)($_SESSION[$k] ?? 0);
    if ($n >= $max) return false;
    $_SESSION[$k] = $n + 1;
    return true;
}

function sr_public_tabs($on) {
    if (!function_exists('sr_icon')) require_once __DIR__ . '/ui.php';
    $tabs = [
        'apply' => [u('/apply.php'), t('Join as rider', 'राइडर बनिए'), 'bike'],
        'login' => [u('/'),          t('Rider login', 'राइडर लॉगिन'), 'user'],
    ];
    echo '<nav class="sxp-tabs" aria-label="' . h(t('Sarathi', 'सारथी')) . '">';
    foreach ($tabs as $k => [$href, $lbl, $ic]) {
        echo '<a href="' . h($href) . '"' . ($k === $on ? ' class="on" aria-current="page"' : '') . '>' . sr_icon($ic, 18) . '<span>' . h($lbl) . '</span></a>';
    }
    echo '</nav>';
}

/**
 * $points = [[icon, text], ...] — hero ke neeche 3 chhoti khoobiyan
 */
function sr_public_head($tab, $title, $kicker, $h1a, $h1b, $lead, array $points = []) {
    if (!function_exists('sr_icon')) require_once __DIR__ . '/ui.php';
    ?><!doctype html>
<html lang="<?= h(sr_lang()) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="theme-color" content="#0B1B36">
<meta name="description" content="<?= h($lead) ?>">
<title><?= h($title) ?> — <?= h(t('Smart Sarathi', 'स्मार्ट सारथी')) ?></title>
<?php sr_assets(); ?>
</head>
<body class="sx sx-pub sx-pub-<?= h($tab) ?>">
<header class="sxp-top">
  <a class="sx-logo light" href="<?= h(u('/')) ?>"><span class="sx-mark"><?= sr_icon('bolt', 18) ?></span><span>Sarathi<small>SMART DELIVERY</small></span></a>
  <?php sr_language_switch(true); ?>
</header>
<div class="sxp">
  <section class="sxp-hero">
    <span class="sxp-kicker"><?= h($kicker) ?></span>
    <h1><?= h($h1a) ?> <em><?= h($h1b) ?></em></h1>
    <p><?= h($lead) ?></p>
    <?php if ($points): ?><ul class="sxp-points"><?php foreach ($points as [$ic, $tx]): ?><li><?= sr_icon($ic, 18) ?><span><?= h($tx) ?></span></li><?php endforeach; ?></ul><?php endif; ?>
    <div class="sxp-art" aria-hidden="true">
      <svg viewBox="0 0 320 150" fill="none"><path d="M10 120 C 80 120, 90 40, 160 60 S 250 110, 310 30" stroke="url(#g)" stroke-width="3" stroke-dasharray="6 8" stroke-linecap="round"/><circle cx="10" cy="120" r="7" fill="#16E0A8"/><circle cx="310" cy="30" r="9" fill="#FFC83D"/><circle cx="310" cy="30" r="18" stroke="#FFC83D" stroke-opacity=".35" stroke-width="2"/><defs><linearGradient id="g" x1="0" y1="0" x2="320" y2="0"><stop stop-color="#16E0A8"/><stop offset="1" stop-color="#FFC83D"/></linearGradient></defs></svg>
    </div>
  </section>
  <div class="sxp-col">
    <?php sr_public_tabs($tab); ?>
<?php
}

function sr_public_foot() {
    ?>
    <div class="sxp-help">
      <div><b><?= h(t('Need help?', 'मदद चाहिए?')) ?></b><small><?= h(t('Call or WhatsApp — we answer quickly', 'फ़ोन या WhatsApp — जल्दी जवाब मिलेगा')) ?></small></div>
      <a class="sx-pill" href="tel:<?= h(SR_PHONE) ?>"><?= sr_icon('phone', 16) ?> <?= h(SR_PHONE_SHOW) ?></a>
    </div>
  </div>
</div>
<div class="sx-toast" id="sxToast" role="status" aria-live="polite"></div>
<script>
document.querySelectorAll('input[type=tel]').forEach(function (m) {
  m.addEventListener('input', function () { var d = this.value.replace(/\D/g, ''); if (d.length > 10 && d.indexOf('91') === 0) d = d.slice(2); this.value = d.slice(0, 10); this.closest('.sx-field') && this.closest('.sx-field').classList.toggle('ok', this.value.length === 10); });
});
document.querySelectorAll('form.sx-form').forEach(function (f) {
  f.addEventListener('submit', function (e) { if (!f.checkValidity()) return; var b = f.querySelector('button[type=submit]'); if (b) setTimeout(function () { b.disabled = true; b.classList.add('busy'); }, 0); });
});
document.addEventListener('click', function (e) { if (e.target.closest('button, .sx-btn, .sxp-tabs a')) { try { navigator.vibrate && navigator.vibrate(8); } catch (x) {} } });
window.sxToast = function (m) { var t = document.getElementById('sxToast'); t.textContent = m; t.classList.add('show'); clearTimeout(t._h); t._h = setTimeout(function () { t.classList.remove('show'); }, 2600); };
</script>
</body>
</html>
<?php
}

/** Kaam ki halat — grahak ki bhasha me */
function sr_job_steps($status) {
    $steps = ['new' => 0, 'assigned' => 1, 'picked' => 2, 'delivered' => 3];
    return [$steps[$status] ?? -1, [
        t('Booked', 'बुक हुआ'), t('Rider assigned', 'राइडर मिला'), t('Picked up', 'उठा लिया'), t('Delivered', 'पहुँच गया'),
    ]];
}
