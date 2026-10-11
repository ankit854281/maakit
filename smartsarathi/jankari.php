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

$ready = duty_until($pdo, $rid);
$jobs  = rider_jobs($pdo, $rid);
$msg   = flash();

sr_head($me, 'jankari', t('My profile', 'मेरी जानकारी'), $ready, ['kaam' => count($jobs)]);
?>

<?php if ($msg): ?><div class="sr-flash"><?= h(sr_message($msg)) ?></div><?php endif; ?>
<?php if ($err): ?><div class="sr-err"><?= h(sr_message($err)) ?></div><?php endif; ?>

<section class="sr-card">
  <table class="sr-tab sr-tab-l">
    <tr><td><?= h(t('Name', 'नाम')) ?></td><td><?= h($me['name']) ?></td></tr>
    <tr><td><?= h(t('Mobile', 'मोबाइल')) ?></td><td><?= h($me['mobile']) ?></td></tr>
    <?php if ($me['vehicle']): ?><tr><td><?= h(t('Vehicle', 'गाड़ी')) ?></td><td><?= h($me['vehicle']) ?></td></tr><?php endif; ?>
    <?php if ($me['rc_number']): ?><tr><td><?= h(t('Registration number', 'RC नंबर')) ?></td><td><?= h($me['rc_number']) ?></td></tr><?php endif; ?>
    <?php if ($me['dl_number']): ?><tr><td><?= h(t('Driving licence', 'लाइसेंस')) ?></td><td><?= h($me['dl_number']) ?></td></tr><?php endif; ?>
    <tr><td><?= h(t('Per delivery', 'एक डिलीवरी का')) ?></td><td>
      <?= (int)$me['rate'] > 0 ? '₹' . (int)$me['rate'] : '<span class="sr-warn">' . h(t('Not set', 'तय नहीं')) . '</span>' ?>
    </td></tr>
  </table>
  <p class="sr-note"><?= h(t('Something incorrect? Call your office. Only the office can update these details.', 'इनमें कुछ ग़लत है? दफ़्तर को फ़ोन कीजिए — ये दफ़्तर ही बदल सकता है।')) ?></p>
  <a class="sr-call-sm" href="tel:<?= h(SR_PHONE) ?>"><?= sr_icon('phone',18) ?> <?= h(SR_PHONE_SHOW) ?></a>
</section>

<details class="sr-ord">
  <summary class="sr-head">
    <span class="sr-dot"></span>
    <span class="sr-head-mid"><b><?= h(t('Change your login code', 'अपना कोड बदलिए')) ?></b><small><?= h(t('The code you use to sign in', 'जो लॉगिन करते वक़्त डालते हैं')) ?></small></span>
  </summary>
  <div class="sr-body">
    <form method="post" class="sr-act">
      <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
      <input type="hidden" name="do" value="code">
      <label class="sr-lab"><?= h(t('Current code', 'पुराना कोड')) ?></label>
      <input class="sr-in" type="password" name="purana" required>
      <label class="sr-lab"><?= h(t('New code', 'नया कोड')) ?> <small><?= h(t('(at least 6 characters)', '(कम से कम 6 अक्षर)')) ?></small></label>
      <input class="sr-in" type="password" name="naya" minlength="6" required>
      <button class="sr-btn sr-btn-go" type="submit"><?= h(t('Change code', 'कोड बदलिए')) ?></button>
    </form>
  </div>
</details>

<?php sr_foot('jankari', ['kaam' => count($jobs)]); ?>

