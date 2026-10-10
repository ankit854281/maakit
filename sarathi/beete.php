<?php
// ============================================================
//  सारथी — बीते काम
//
//  Jo nipat chuke. Din ke hisaab se baante hue, taaki rider
//  khud dekh sake "kal maine kitne kiye the". Bharosa ginti
//  batane se nahi, ginti DIKHNE se banta hai.
// ============================================================
require_once __DIR__ . '/../inc/fn.php';
require_once __DIR__ . '/../inc/sarathi.php';
require_once __DIR__ . '/../inc/sarathi-ui.php';

$me = sarathi_me();
if (!$me) { redirect('/sarathi/'); }
$uid = (int)$me['id'];

$beete    = sarathi_beete($pdo, $uid);
$duty_tak = sarathi_duty_tak($pdo, $uid);
$baaki    = sarathi_kaam($pdo, $uid);

// din ke hisaab se gatth banaiye
$din = [];
foreach ($beete as $b) { $din[date('Y-m-d', strtotime($b['kab']))][] = $b; }

function sr_din_naam($d) {
    if ($d === date('Y-m-d')) return 'आज';
    if ($d === date('Y-m-d', strtotime('-1 day'))) return 'कल';
    $m = ['01'=>'जनवरी','02'=>'फ़रवरी','03'=>'मार्च','04'=>'अप्रैल','05'=>'मई','06'=>'जून',
          '07'=>'जुलाई','08'=>'अगस्त','09'=>'सितंबर','10'=>'अक्टूबर','11'=>'नवंबर','12'=>'दिसंबर'];
    return (int)date('j', strtotime($d)) . ' ' . $m[date('m', strtotime($d))];
}

sr_shell_head($me, 'beete', 'बीते काम', $duty_tak, ['kaam' => count($baaki)]);
?>

<?php if (!$beete): ?>
  <div class="sr-khali">
    <?= sr_icon('clock', 40) ?>
    <p>अभी कोई काम पूरा नहीं हुआ।</p>
    <p class="sr-khali-s">जो आप पहुँचा देंगे, वो यहाँ जुड़ता जाएगा।</p>
  </div>
<?php else: foreach ($din as $d => $rows): ?>
  <div class="sr-day">
    <b><?= h(sr_din_naam($d)) ?></b>
    <span><?= count($rows) ?> डिलीवरी</span>
  </div>
  <?php foreach ($rows as $r): ?>
    <div class="sr-past">
      <span class="sr-tick">✓</span>
      <div class="sr-past-mid">
        <b><?= h($r['customer_name']) ?></b>
        <small><?= h($r['village']) ?><?= $r['shop'] ? ' · ' . h($r['shop']) : '' ?>
          · <?= h(date('g:i a', strtotime($r['kab']))) ?></small>
      </div>
      <?php if ($r['pod_photo']): ?>
        <a class="sr-past-pic" href="/uploads/<?= h($r['pod_photo']) ?>" target="_blank" rel="noopener">फ़ोटो</a>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
<?php endforeach; endif; ?>

<?php sr_shell_foot('beete', ['kaam' => count($baaki)]); ?>
