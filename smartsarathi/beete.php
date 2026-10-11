<?php
// ============================================================
//  सारथी — बीते काम
//  Din ke hisaab se baante hue, taaki rider khud dekh sake
//  "kal maine kitne kiye the". Bharosa ginti batane se nahi,
//  ginti DIKHNE se banta hai.
// ============================================================
require_once __DIR__ . '/inc/fn.php';
require_once __DIR__ . '/inc/kaam.php';
require_once __DIR__ . '/inc/ui.php';

$me = rider_me($pdo);
if (!$me) { redirect('/'); }
$rid = (int)$me['id'];

$past  = rider_past($pdo, $rid);
$ready = duty_until($pdo, $rid);
$jobs  = rider_jobs($pdo, $rid);

$din = [];
foreach ($past as $p) $din[date('Y-m-d', strtotime($p['delivered_at']))][] = $p;

function din_naam($d) {
    if ($d === date('Y-m-d')) return t('Today', 'आज');
    if ($d === date('Y-m-d', strtotime('-1 day'))) return t('Yesterday', 'कल');
    $m = ['01'=>t('January', 'जनवरी'),'02'=>t('February', 'फ़रवरी'),'03'=>t('March', 'मार्च'),'04'=>t('April', 'अप्रैल'),'05'=>t('May', 'मई'),'06'=>t('June', 'जून'),
          '07'=>t('July', 'जुलाई'),'08'=>t('August', 'अगस्त'),'09'=>t('September', 'सितंबर'),'10'=>t('October', 'अक्टूबर'),'11'=>t('November', 'नवंबर'),'12'=>t('December', 'दिसंबर')];
    return (int)date('j', strtotime($d)) . ' ' . $m[date('m', strtotime($d))];
}

sr_head($me, 'beete', t('History', 'बीते काम'), $ready, ['kaam' => count($jobs)]);
?>

<?php if (!$past): ?>
  <div class="sr-khali">
    <?= sr_icon('clock', 40) ?>
    <p><?= h(t('No completed jobs yet.', 'अभी कोई काम पूरा नहीं हुआ।')) ?></p>
    <p class="sr-khali-s"><?= h(t('Your completed deliveries will appear here.', 'जो आप पहुँचा देंगे, वो यहाँ जुड़ता जाएगा।')) ?></p>
  </div>
<?php else: foreach ($din as $d => $rows): ?>
  <div class="sr-day"><b><?= h(din_naam($d)) ?></b><span><?= count($rows) ?> <?= h(t('deliveries', 'डिलीवरी')) ?></span></div>
  <?php foreach ($rows as $r): ?>
    <div class="sr-past">
      <span class="sr-tick">✓</span>
      <div class="sr-past-mid">
        <b><?= h($r['drop_name']) ?></b>
        <small><?= h($r['drop_village'] ?: $r['drop_address']) ?> · <?= h($r['client_name']) ?>
          · <?= h(date('g:i a', strtotime($r['delivered_at']))) ?></small>
      </div>
      <?php if ($r['pod_photo']): ?>
        <a class="sr-past-pic" href="<?= h(u('/uploads/' . $r['pod_photo'])) ?>" target="_blank" rel="noopener"><?= h(t('Photo', 'फ़ोटो')) ?></a>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
<?php endforeach; endif; ?>

<?php sr_foot('beete', ['kaam' => count($jobs)]); ?>

