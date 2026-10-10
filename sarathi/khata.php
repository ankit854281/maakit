<?php
// ============================================================
//  सारथी — खाता
//
//  Kitna bana, kitna mila, kitna baaki. Bas ginti.
//  Paisa Maakit apne paas nahi rakhta — na wallet, na gateway.
// ============================================================
require_once __DIR__ . '/../inc/fn.php';
require_once __DIR__ . '/../inc/sarathi.php';
require_once __DIR__ . '/../inc/sarathi-ui.php';

$me = sarathi_me();
if (!$me) { redirect('/sarathi/'); }
$uid = (int)$me['id'];

$k        = sarathi_khata($pdo, $uid);
$duty_tak = sarathi_duty_tak($pdo, $uid);
$baaki    = sarathi_kaam($pdo, $uid);

// pichhle paanch bhugtan — "mila kab kab"
$st = $pdo->prepare("SELECT * FROM sarathi_khata WHERE rider_user=? ORDER BY paid_on DESC, id DESC LIMIT 5");
$st->execute([$uid]);
$diye = $st->fetchAll();

sr_shell_head($me, 'khata', 'खाता', $duty_tak, ['kaam' => count($baaki)]);
?>

<?php if ($k['rate'] <= 0): ?>
  <div class="sr-err sr-err-soft">
    आपका रेट अभी तय नहीं हुआ है, इसीलिए कमाई ₹0 दिख रही है।
    Maakit से एक बार बात कर लीजिए।
  </div>
<?php endif; ?>

<!-- आज और इस हफ़्ते — दो बड़े खाने -->
<div class="sr-big">
  <div><span>आज</span><b>₹<?= $k['aaj'] ?></b><em><?= $k['aaj_n'] ?> डिलीवरी</em></div>
  <div><span>इस हफ़्ते</span><b>₹<?= $k['hafta'] ?></b><em><?= $k['hafta_n'] ?> डिलीवरी</em></div>
</div>

<!-- बाकी कितना -->
<section class="sr-card">
  <table class="sr-tab">
    <tr><td>अब तक कुल बना</td><td>₹<?= $k['kul'] ?> <small>(<?= $k['kul_n'] ?> डिलीवरी)</small></td></tr>
    <tr><td>अब तक मिल गया</td><td>₹<?= $k['diya'] ?></td></tr>
    <?php
      // Diya hua kamai se zyada ho sakta hai — advance diya ho ya
      // koi order baad me radd hua ho. Ulta number ("₹-40") likhna
      // galat aur uljhan wala hai; baat seedhi kahiye.
    ?>
    <?php if ($k['baaki'] >= 0): ?>
      <tr class="sr-baaki"><td>बाकी मिलना है</td><td>₹<?= $k['baaki'] ?></td></tr>
    <?php else: ?>
      <tr class="sr-baaki"><td>एडवांस लिया हुआ</td><td>₹<?= -$k['baaki'] ?></td></tr>
    <?php endif; ?>
  </table>
  <?php if ($k['baaki'] < 0): ?>
    <p class="sr-note">अगली डिलीवरी की कमाई पहले इसी में से कटेगी।</p>
  <?php endif; ?>
</section>

<!-- रेट -->
<section class="sr-card">
  <h2 class="sr-h2">आपका रेट</h2>
  <table class="sr-tab">
    <tr><td>एक डिलीवरी का</td><td>₹<?= $k['rate'] ?></td></tr>
    <?php if ($k['extra'] > 0): ?>
      <tr><td>एक ही चक्कर में अगली का</td><td>₹<?= $k['extra'] ?></td></tr>
    <?php endif; ?>
  </table>
  <?php if ($k['extra'] > 0): ?>
    <p class="sr-note">एक बार निकलकर कई जगह निपटाना दोनों के फ़ायदे का है —
      आपको कम चक्कर, Maakit को कम खर्च।</p>
  <?php endif; ?>
</section>

<!-- कब-कब मिला -->
<?php if ($diye): ?>
<section class="sr-card">
  <h2 class="sr-h2">पिछली बार कब मिला</h2>
  <table class="sr-tab">
    <?php foreach ($diye as $d): ?>
      <tr>
        <td><?= h(date('j M', strtotime($d['paid_on']))) ?>
          <?php if ($d['how']): ?><small>· <?= h($d['how']) ?></small><?php endif; ?></td>
        <td>₹<?= (int)$d['amount'] ?></td>
      </tr>
    <?php endforeach; ?>
  </table>
</section>
<?php endif; ?>

<p class="sr-note sr-note-q">
  <b>पैसा Maakit अपने पास नहीं रखता।</b> ये खाता सिर्फ़ गिनती रखता है —
  कितना बना, कितना मिला, कितना बाकी। पैसा Maakit अपने खाते से सीधे
  आपके खाते में भेजता है।
</p>

<?php sr_shell_foot('khata', ['kaam' => count($baaki)]); ?>
