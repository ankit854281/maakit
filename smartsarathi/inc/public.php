<?php
// ============================================================
//  सारथी — बाहर वालों के पन्ने (बिना लॉगिन)
//
//    apply.php  — rider banne ki arzi (Maakit.in se yahan aaya)
//    book.php   — grahak: parcel bhejiye (pick & drop)
//    track.php  — grahak: parcel kahan hai
//
//  Teeno ka dhaancha ek jaisa — login page wala (body.sr-login).
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

/** Ek session me kitni baar — bot aur galti se baar-baar bhejne se bachav */
function sr_public_limit($key, $max) {
    $k = 'srp_' . $key;
    $n = (int)($_SESSION[$k] ?? 0);
    if ($n >= $max) return false;
    $_SESSION[$k] = $n + 1;
    return true;
}

function sr_public_tabs($on) {
    $tabs = [
        'book'  => [u('/book.php'),  t('Send a parcel', 'पार्सल भेजिए')],
        'track' => [u('/track.php'), t('Track', 'पार्सल कहाँ है')],
        'apply' => [u('/apply.php'), t('Become a rider', 'राइडर बनिए')],
        'login' => [u('/'),          t('Rider login', 'राइडर लॉगिन')],
    ];
    echo '<nav class="sp-tabs" aria-label="' . h(t('Sarathi', 'सारथी')) . '">';
    foreach ($tabs as $k => [$href, $lbl]) {
        echo '<a href="' . h($href) . '"' . ($k === $on ? ' class="on" aria-current="page"' : '') . '>' . h($lbl) . '</a>';
    }
    echo '</nav>';
}

function sr_public_head($tab, $title, $kicker, $h1a, $h1b, $lead) {
    ?><!doctype html>
<html lang="<?= h(sr_lang()) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="theme-color" content="#14283f">
<title><?= h($title) ?> — <?= h(t('Smart Sarathi', 'स्मार्ट सारथी')) ?></title>
<link rel="stylesheet" href="<?= h(u('/assets/app.css')) ?>?v=<?= (int)@filemtime(__DIR__ . '/../assets/app.css') ?>">
</head>
<body class="sr-login sd-native sp-public">
<?php sr_language_switch(true); ?>
<section class="sd-login-intro">
  <a class="sd-brand" href="<?= h(u('/book.php')) ?>"><span class="sd-mark">↗</span><span>Sarathi<span class="sd-brand-sub">SMART DELIVERY</span></span></a>
  <span class="sd-eyebrow"><?= h($kicker) ?></span>
  <h1><?= h($h1a) ?><br><?= h($h1b) ?></h1>
  <p><?= h($lead) ?></p>
  <div class="sd-login-art" aria-hidden="true"><span>↗</span></div>
  <small>SMART SARATHI · <?= h(SR_PHONE_SHOW) ?></small>
</section>
<div class="sr-wrap">
  <?php sr_public_tabs($tab); ?>
<?php
}

function sr_public_foot() {
    ?>
  <div class="sr-help">
    <b><?= h(t('Need help?', 'मदद चाहिए?')) ?></b>
    <p><?= h(t('Call or WhatsApp us — we answer quickly.', 'फ़ोन या WhatsApp कीजिए — जल्दी जवाब मिलेगा।')) ?></p>
    <a class="sr-call" href="tel:<?= h(SR_PHONE) ?>"><?= h(t('Call —', 'फ़ोन कीजिए —')) ?> <?= h(SR_PHONE_SHOW) ?></a>
  </div>
</div>
<script>
document.querySelectorAll('input[type=tel]').forEach(function (m) {
  m.addEventListener('input', function () { var d = this.value.replace(/\D/g, ''); if (d.length > 10 && d.indexOf('91') === 0) d = d.slice(2); this.value = d.slice(0, 10); });
});
document.querySelectorAll('form.sr-card').forEach(function (f) {
  f.addEventListener('submit', function () { var b = f.querySelector('button[type=submit]'); if (b) setTimeout(function () { b.disabled = true; b.classList.add('busy'); }, 0); });
});
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
