<?php
// ============================================================
//  सारथी — खाता
//  Kitna bana, kitna mila, kitna baaki. Bas ginti.
// ============================================================
require_once __DIR__ . '/inc/fn.php';
require_once __DIR__ . '/inc/kaam.php';
require_once __DIR__ . '/inc/ui.php';

$me = rider_me($pdo);
if (!$me) { redirect('/'); }
$rid = (int)$me['id'];

$k     = rider_khata($pdo, $rid);
$ready = duty_until($pdo, $rid);
$jobs  = rider_jobs($pdo, $rid);

$st = $pdo->prepare("SELECT * FROM khata WHERE rider_id=? ORDER BY paid_on DESC, id DESC LIMIT 5");
$st->execute([$rid]);
$diye = $st->fetchAll();

sr_head($me, 'khata', 'खाता', $ready, ['kaam' => count($jobs)]);
?>

<?php if ($k['rate'] <= 0): ?>
  <div class="sr-err sr-err-soft">
    आपका रेट अभी तय नहीं हुआ है, इसीलिए कमाई ₹0 दिख रही है।
    दफ़्तर से एक बार बात कर लीजिए।
  </div>
<?php endif; ?>

<div class="sr-big">
  <div><span>आज</span><b>₹<?= $k['aaj'] ?></b><em><?= $k['aaj_n'] ?> डिलीवरी</em></div>
  <div><span>इस हफ़्ते</span><b>₹<?= $k['hafta'] ?></b><em><?= $k['hafta_n'] ?> डिलीवरी</em></div>
</div>

<section class="sr-card">
  <table class="sr-tab">
    <tr><td>अब तक कुल बना</td><td>₹<?= $k['kul'] ?> <small>(<?= $k['kul_n'] ?> डिलीवरी)</small></td></tr>
    <tr><td>अब तक मिल गया</td><td>₹<?= $k['diya'] ?></td></tr>
    <?php
      // Diya hua kamai se zyada ho sakta hai — advance diya ho ya
      // koi kaam baad me radd hua ho. Ulta number ("₹-40") likhna
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
      आपको कम चक्कर, दफ़्तर को कम ख़र्च।</p>
  <?php endif; ?>
</section>

<?php if ($diye): ?>
<section class="sr-card">
  <h2 class="sr-h2">पिछली बार कब मिला</h2>
  <table class="sr-tab">
    <?php foreach ($diye as $d): ?>
      <tr><td><?= h(date('j M', strtotime($d['paid_on']))) ?>
        <?php if ($d['how']): ?><small>· <?= h($d['how']) ?></small><?php endif; ?></td>
        <td>₹<?= (int)$d['amount'] ?></td></tr>
    <?php endforeach; ?>
  </table>
</section>
<?php endif; ?>

<p class="sr-note sr-note-q">
  <b>पैसा सारथी अपने पास नहीं रखता।</b> ये खाता सिर्फ़ गिनती रखता है —
  कितना बना, कितना मिला, कितना बाकी। पैसा दफ़्तर अपने खाते से सीधे
  आपके खाते में भेजता है।
</p>

<?php sr_foot('khata', ['kaam' => count($jobs)]); ?>
