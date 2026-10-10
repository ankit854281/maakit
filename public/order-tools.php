<?php
require_once __DIR__.'/../inc/fn.php';
if(!cust())redirect('/account.php');
$page_title=t('Delivery safety and fee — Maakit','डिलीवरी सुरक्षा और शुल्क — Maakit');
$order=isset($_GET['order_id'])&&is_string($_GET['order_id'])?$_GET['order_id']:'';
if($order!==''&&!preg_match('/^[0-9a-f-]{36}$/iD',$order)){http_response_code(400);$order='';}
include __DIR__.'/../inc/head.php';
?>
<main class="wrap" style="max-width:760px" data-csrf="<?= h(csrf()) ?>" data-order-tools="<?= h($order) ?>">
<h1><?= t('Delivery safety and fee','डिलीवरी सुरक्षा और शुल्क') ?></h1>
<p><?= t('Goods payment stays directly with the shop. Only the delivery fee can use the configured online gateway.','सामान का भुगतान सीधे दुकान को होगा। केवल डिलीवरी शुल्क configured online gateway से दिया जा सकता है।') ?></p>
<p role="status" id="tools-status" aria-live="polite"></p><div id="tools-orders"></div>
<section id="tools-panel" hidden>
<div class="box"><h2><?= t('Family / emergency contact','परिवार / आपातकालीन संपर्क') ?></h2>
<p><?= t('Only your assigned delivery partner can see these numbers while your order is active.','आपका ऑर्डर चालू रहने पर केवल उसे मिले delivery partner को ये नंबर दिखेंगे।') ?></p>
<form id="tools-contacts">
<?php for($i=0;$i<3;$i++): ?><div class="field"><label><?= t('Contact name','संपर्क का नाम') ?> <?= $i+1 ?><input name="name<?= $i ?>" maxlength="80"></label><label><?= t('Mobile number','मोबाइल नंबर') ?><input name="phone<?= $i ?>" type="tel" maxlength="16"></label></div><?php endfor; ?>
<label><input type="checkbox" name="consent" required> <?= t('I am authorised to share these contacts for this delivery.','मैं इस डिलीवरी के लिए ये संपर्क साझा करने के लिए अधिकृत हूँ।') ?></label>
<button class="btn btn-brand" type="submit"><?= t('Save contacts','संपर्क सेव करें') ?></button>
</form></div>
<div class="box"><h2><?= t('Delivery tracking','डिलीवरी की स्थिति') ?></h2><p id="tools-tracking"></p><button id="tools-refresh" class="btn btn-line"><?= t('Refresh location','लोकेशन फिर देखें') ?></button><p class="help"><?= t('No location is shown after completion or when the last GPS update is older than one minute.','डिलीवरी पूरी होने या GPS update एक मिनट से पुराना होने पर लोकेशन नहीं दिखेगी।') ?></p></div>
<div class="box"><h2><?= t('Online delivery fee','ऑनलाइन डिलीवरी शुल्क') ?></h2><p id="tools-fee"></p><button id="tools-pay" class="btn btn-brand" disabled><?= t('Pay delivery fee','डिलीवरी शुल्क दें') ?></button><p class="help"><?= t('This payment does not pay for your goods. Please do not pay the same delivery fee again in cash after online confirmation.','इससे सामान का भुगतान नहीं होता। ऑनलाइन पुष्टि के बाद वही डिलीवरी शुल्क दोबारा नकद न दें।') ?></p></div>
</section><p><a href="/account.php"><?= t('My account','मेरा खाता') ?></a></p>
</main>
<script src="/assets/maakit-api.js" defer></script><script src="/assets/sarathi-customer.js" defer></script>
<?php include __DIR__.'/../inc/foot.php'; ?>
