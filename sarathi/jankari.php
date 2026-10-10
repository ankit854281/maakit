<?php
// ============================================================
//  सारथी — मेरी जानकारी
//
//  Rider apni jaankari dekh sakta hai. Badal NAHI sakta —
//  naam, number, gaadi, rate ye sab Maakit tay karta hai.
//  Yahan sirf dikhta hai, taaki rider ko pata rahe ki Maakit
//  ke kagaz me uske baare me kya likha hai. Kuch galat ho to
//  wo phone kar sakta hai.
//
//  Ek cheez wo khud badal sakta hai — apna code (password).
// ============================================================
require_once __DIR__ . '/../inc/fn.php';
require_once __DIR__ . '/../inc/sarathi.php';
require_once __DIR__ . '/../inc/sarathi-ui.php';

$me = sarathi_me();
if (!$me) { redirect('/sarathi/'); }
$uid = (int)$me['id'];

$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_ok() && post('do') === 'code') {
    $purana = (string)post('purana');
    $naya   = (string)post('naya');
    $st = $pdo->prepare("SELECT password FROM users WHERE id=?");
    $st->execute([$uid]);
    $hash = (string)$st->fetchColumn();

    if (!$hash || !password_verify($purana, $hash)) {
        $err = 'पुराना कोड सही नहीं है।';
    } elseif (mb_strlen($naya) < 6) {
        $err = 'नया कोड कम से कम 6 अक्षर का रखिए।';
    } else {
        $pdo->prepare("UPDATE users SET password=? WHERE id=?")
            ->execute([password_hash($naya, PASSWORD_DEFAULT), $uid]);
        flash('कोड बदल गया। अगली बार नए कोड से आइए।');
        redirect('/sarathi/jankari.php');
    }
}

$st = $pdo->prepare("SELECT name, username, mobile, role, rider_rate FROM users WHERE id=?");
$st->execute([$uid]);
$u = $st->fetch();

// agar partner aavedan se aaya hai to gaadi ki jaankari wahan hai
$gaadi = null;
try {
    $st = $pdo->prepare("SELECT vehicle_type, rc_number FROM mk_partner_applications
                          WHERE login_user_id=? AND status='APPROVED' LIMIT 1");
    $st->execute([$uid]);
    $gaadi = $st->fetch() ?: null;
} catch (Throwable $e) { /* purane rider ka aavedan nahi hota — koi baat nahi */ }

$duty_tak = sarathi_duty_tak($pdo, $uid);
$baaki    = sarathi_kaam($pdo, $uid);
$msg      = flash();

sr_shell_head($me, 'jankari', 'मेरी जानकारी', $duty_tak, ['kaam' => count($baaki)]);
?>

<?php if ($msg): ?><div class="sr-flash"><?= h($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="sr-err"><?= h($err) ?></div><?php endif; ?>

<section class="sr-card">
  <table class="sr-tab sr-tab-l">
    <tr><td>नाम</td><td><?= h($u['name']) ?></td></tr>
    <tr><td>मोबाइल</td><td><?= h($u['mobile'] ?: $u['username']) ?></td></tr>
    <tr><td>काम</td><td><?= $u['role'] === 'rider' ? 'सारथी' : 'डिलीवरी' ?></td></tr>
    <?php if ($gaadi): ?>
      <tr><td>गाड़ी</td><td><?= h($gaadi['vehicle_type']) ?></td></tr>
      <tr><td>RC नंबर</td><td><?= h($gaadi['rc_number']) ?></td></tr>
    <?php endif; ?>
    <tr><td>एक डिलीवरी का</td><td>
      <?= (int)$u['rider_rate'] > 0 ? '₹' . (int)$u['rider_rate'] : '<span class="sr-warn">तय नहीं</span>' ?>
    </td></tr>
  </table>
  <p class="sr-note">इनमें कुछ ग़लत है? Maakit को फ़ोन कीजिए — ये Maakit ही बदल सकता है।</p>
  <a class="sr-call-sm" href="tel:<?= h(MAAKIT_PHONE) ?>"><?= sr_icon('phone',18) ?> <?= h(MAAKIT_NUMBER_SHOW) ?></a>
</section>

<!-- अपना कोड बदलना -->
<details class="sr-ord">
  <summary class="sr-head">
    <span class="sr-dot"></span>
    <span class="sr-head-mid"><b>अपना कोड बदलिए</b><small>जो लॉगिन करते वक़्त डालते हैं</small></span>
  </summary>
  <div class="sr-body">
    <form method="post" class="sr-act">
      <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
      <input type="hidden" name="do" value="code">
      <label class="sr-lab">पुराना कोड</label>
      <input class="sr-in" type="password" name="purana" required>
      <label class="sr-lab">नया कोड <small>(कम से कम 6 अक्षर)</small></label>
      <input class="sr-in" type="password" name="naya" minlength="6" required>
      <button class="sr-btn sr-btn-go" type="submit">कोड बदलिए</button>
    </form>
  </div>
</details>

<?php sr_shell_foot('jankari', ['kaam' => count($baaki)]); ?>
