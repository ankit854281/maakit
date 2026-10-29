<?php
sr_customer_head(t('Welcome','स्वागत है'),t('Choose how you want to continue.','आप कैसे आगे बढ़ना चाहते हैं, चुनिए।'),'/');
?>
<section class="sx-card sx-form" style="padding:28px">
<span class="sx-mark"><?= sr_icon('route',32) ?></span>
<h2><?= h(t('Your journey. Your Sarathi.','आपका सफ़र। आपका सारथी।')) ?></h2>
<p><?= h(t('Two separate apps. Choose your own login.','दो अलग ऐप। अपना लॉगिन चुनिए।')) ?></p>
<a class="sx-btn sx-btn-mint" href="<?= h(u('/customer-login.php')) ?>"><?= sr_icon('user') ?> <?= h(t('Continue as Customer','ग्राहक के रूप में आगे बढ़ें')) ?></a>
<a class="sx-btn sx-btn-navy" href="<?= h(u('/partner-login.php')) ?>"><?= sr_icon('bike') ?> <?= h(t('Sarathi Partner — login / register','सारथी पार्टनर — लॉगिन/रजिस्टर')) ?></a>
</section>
</main></body></html>
