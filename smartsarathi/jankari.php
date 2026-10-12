<?php
// ============================================================
//  सारथी — मेरी जानकारी
//
//  Rider apni jaankari dekh sakta hai. Naam, number, gaadi,
//  rate — ye dafter tay karta hai, rider nahi badal sakta.
//  Dikhti isliye hai taaki use pata rahe ki kagaz me uske
//  baare me kya likha hai; kuch galat ho to wo phone kar sake.
//
//  Ek cheez wo khud badal sakta hai — apna code.
// ============================================================
require_once __DIR__ . '/inc/fn.php';
require_once __DIR__ . '/inc/kaam.php';
require_once __DIR__ . '/inc/ui.php';

$me = rider_me($pdo);
if (!$me) { redirect('/'); }
$rid = (int)$me['id'];
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_ok() && post('do') === 'code') {
    $purana = (string)post('purana');
    $naya   = (string)post('naya');
    if (!password_verify($purana, $me['pass_hash'])) {
        $err = t('The current code is incorrect.', 'पुराना कोड सही नहीं है।');
    } elseif (mb_strlen($naya) < 6) {
        $err = t('Use at least 6 characters for the new code.', 'नया कोड कम से कम 6 अक्षर का रखिए।');
    } else {
        $pdo->prepare("UPDATE riders SET pass_hash=? WHERE id=?")
            ->execute([password_hash($naya, PASSWORD_DEFAULT), $rid]);
        flash(t('Your code has changed. Use the new code next time.', 'कोड बदल गया। अगली बार नए कोड से आइए।'));
        redirect('/jankari.php');
    }
}

// Ek login, ek profile — rider khud chunta hai ki aaj kaunsa kaam lega.
// Sawari tabhi chalu ho sakti hai jab dafter ne ijaazat di ho (ride_ok).
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_ok() && post('do') === 'work') {
    sr_jobs_upgrade($pdo);
    $d = post('do_delivery') === '1' ? 1 : 0;
    $r = post('do_ride') === '1' && !empty($me['ride_ok']) ? 1 : 0;
    if (!$d && !$r) { $err = t('Keep at least one kind of work on.', 'कम से कम एक काम चालू रखिए।'); }
    else {
        $pdo->prepare("UPDATE riders SET do_delivery=?, do_ride=? WHERE id=?")->execute([$d, $r, $rid]);
        flash(t('Saved. New jobs will come as you chose.', 'सेव हो गया। अब काम आपकी पसंद से आएगा।'));
        redirect('/jankari.php');
    }
}

sr_jobs_upgrade($pdo);
$me = array_merge(['vtype' => null, 'do_delivery' => 1, 'do_ride' => 0, 'ride_ok' => 0], $me);
$ready = duty_until($pdo, $rid);
$jobs  = rider_jobs($pdo, $rid);
$msg   = flash();

sr_head($me, 'jankari', t('My profile', 'मेरी जानकारी'), $ready, ['kaam' => count($jobs)]);
?>

<?php if ($msg): ?><div class="sr-flash"><?= h(sr_message($msg)) ?></div><?php endif; ?>
<?php if ($err): ?><div class="sx-alert"><?= sr_icon('alert', 18) ?> <?= h(sr_message($err)) ?></div><?php endif; ?>
<?php $vt = sr_vtypes(); $vem = ['cycle' => '🚲', 'bike' => '🏍️', 'ev' => '🛵', 'auto' => '🛺', 'erick' => '🛺', 'none' => '🚶']; ?>

<section class="sx-card sx-prof">
  <div class="sx-prof-h">
    <span class="sx-prof-av"><?= h(mb_strtoupper(mb_substr(trim($me['name']), 0, 1))) ?></span>
    <div><b><?= h($me['name']) ?></b><small>+91 <?= h($me['mobile']) ?></small></div>
    <?php if ($me['vtype']): ?><span class="sx-prof-veh"><i><?= $vem[$me['vtype']] ?? '🏍️' ?></i><?= h($vt[$me['vtype']] ?? '') ?></span><?php endif; ?>
  </div>
  <div class="sx-prof-grid">
    <div><small><?= h(t('Per delivery', 'एक डिलीवरी का')) ?></small><b><?= (int)$me['rate'] > 0 ? '₹' . (int)$me['rate'] : h(t('Not set', 'तय नहीं')) ?></b></div>
    <?php if ($me['rc_number']): ?><div><small><?= h(t('Vehicle no.', 'गाड़ी नं.')) ?></small><b><?= h($me['rc_number']) ?></b></div><?php endif; ?>
    <?php if ($me['dl_number']): ?><div><small><?= h(t('Licence', 'लाइसेंस')) ?></small><b><?= h($me['dl_number']) ?></b></div><?php endif; ?>
  </div>
</section>

<form method="post" class="sx-card sx-work" data-work>
  <input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="do" value="work">
  <h3><?= h(t('My work', 'मेरा काम')) ?></h3>
  <p class="sx-work-sub"><?= h(t('One login for everything. Choose what jobs you want to receive.', 'एक ही लॉगिन से सब काम। चुनिए कौन-सा काम आपके पास आए।')) ?></p>
  <label class="sx-work-row">
    <span class="sx-work-ic d"><?= sr_icon('box', 22) ?></span>
    <span class="sx-work-t"><b><?= h(t('Deliveries', 'डिलीवरी')) ?></b><small><?= h(t('Shop orders and parcels', 'दुकान के ऑर्डर और पार्सल')) ?></small></span>
    <span class="sx-switch-row"><input type="checkbox" name="do_delivery" value="1" <?= !empty($me['do_delivery']) ? 'checked' : '' ?>><span class="sx-sw"></span></span>
  </label>
  <label class="sx-work-row<?= empty($me['ride_ok']) ? ' off' : '' ?>">
    <span class="sx-work-ic r"><?= sr_icon('bike', 22) ?></span>
    <span class="sx-work-t"><b><?= h(t('Rides', 'सवारी')) ?></b>
      <small><?= !empty($me['ride_ok']) ? h(t('Take passengers on your ', 'अपनी गाड़ी पर सवारी: ') . ($vt[$me['vtype']] ?? '')) : h(t('Ask the office to check your DL and vehicle papers to unlock rides.', 'सवारी चालू करवाने के लिए दफ़्तर में DL और गाड़ी के काग़ज़ दिखाइए।')) ?></small></span>
    <span class="sx-switch-row"><input type="checkbox" name="do_ride" value="1" <?= !empty($me['do_ride']) && !empty($me['ride_ok']) ? 'checked' : '' ?> <?= empty($me['ride_ok']) ? 'disabled' : '' ?>><span class="sx-sw"></span></span>
  </label>
  <noscript><button class="sx-btn sx-btn-navy" type="submit"><?= h(t('Save', 'सेव कीजिए')) ?></button></noscript>
</form>
<script>document.querySelectorAll('[data-work] input[type=checkbox]').forEach(function (c) { c.addEventListener('change', function () { c.form.submit(); }); });</script>

<section class="sx-card sx-prof-note">
  <p><?= h(t('Name, mobile, vehicle and rate are set by the office. Something wrong? Call us.', 'नाम, मोबाइल, गाड़ी और रेट दफ़्तर तय करता है। कुछ ग़लत है तो फ़ोन कीजिए।')) ?></p>
  <a class="sx-pill" href="tel:<?= h(SR_PHONE) ?>"><?= sr_icon('phone', 16) ?> <?= h(SR_PHONE_SHOW) ?></a>
</section>

<details class="sx-card sx-codebox">
  <summary><?= sr_icon('user', 18) ?> <span><b><?= h(t('Change your login code', 'अपना लॉगिन कोड बदलिए')) ?></b><small><?= h(t('The code you use to sign in', 'जो लॉगिन करते वक़्त डालते हैं')) ?></small></span></summary>
  <form method="post" class="sx-form">
    <input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="do" value="code">
    <label class="sx-field"><span class="sx-lab"><?= h(t('Current code', 'पुराना कोड')) ?></span><input type="password" name="purana" required autocomplete="current-password"></label>
    <label class="sx-field"><span class="sx-lab"><?= h(t('New code (at least 6 characters)', 'नया कोड (कम से कम 6 अक्षर)')) ?></span><input type="password" name="naya" minlength="6" required autocomplete="new-password"></label>
    <button class="sx-btn sx-btn-navy" type="submit"><?= h(t('Change code', 'कोड बदलिए')) ?></button>
  </form>
</details>

<?php sr_foot('jankari', ['kaam' => count($jobs)]); ?>

