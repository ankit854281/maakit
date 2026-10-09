<?php
require_once __DIR__.'/../inc/fn.php';
require_once __DIR__.'/../inc/salon.php';
$context='staff';$u=user();
if (($_GET['context']??'')==='shop') { if (!shop_login_business($pdo)) redirect('/login.php');$context='shop';$u=null; }
if ($context==='shop') {}
elseif (!$u) { if (shop_login_business($pdo)) $context='shop';else redirect('/login.php'); }
elseif (!in_array($u['role'],['admin','delivery'],true)) redirect(panel_home($u['role']));
$page_title=t('Dispatch — Maakit','डिलीवरी पैनल — Maakit');$hub_design=true;
include __DIR__.'/../inc/head.php';
?>
<main class="maakit-hub" data-csrf="<?= h(csrf()) ?>" data-dispatch-context="<?= h($context) ?>"><div class="wrap">
<h1><?= t('Dispatch dashboard','डिलीवरी पैनल') ?></h1>
<p><?= t('Only confirmed, packed orders and verified available riders enter dispatch.','पक्के और पैक किए ऑर्डर तथा सत्यापित उपलब्ध riders ही यहाँ आएँगे।') ?></p>
<?php if ($u&&$u['role']==='delivery'): ?><button class="btn btn-brand" data-duty><?= t('Available for deliveries','डिलीवरी के लिए उपलब्ध हूँ') ?></button><p class="help"><?= t('Availability lasts five minutes. Renew it while you are available.','उपलब्धता पाँच मिनट की है। उपलब्ध रहने पर इसे दोबारा चालू करें।') ?></p><?php endif; ?>
<button class="btn btn-line" data-dispatch-refresh><?= t('Refresh','फिर से देखें') ?></button>
<p role="status" data-dispatch-status aria-live="polite"></p>
<?php if ($context==='shop'||($u&&$u['role']==='admin')): ?>
<form class="hub-empty" data-parcel-form>
<h2><?= t('Courier parcel details','Courier पैक की जानकारी') ?></h2>
<label><?= t('Order','ऑर्डर') ?><select name="order_id" required data-parcel-orders><option value=""><?= t('Choose your order','अपना ऑर्डर चुनिए') ?></option></select></label>
<div class="hub-parcel-fields">
<?php foreach (['weight_g'=>['Weight (grams)','वजन (ग्राम)',100000],'length_mm'=>['Length (mm)','लंबाई (मिमी)',3000],'width_mm'=>['Width (mm)','चौड़ाई (मिमी)',3000],'height_mm'=>['Height (mm)','ऊँचाई (मिमी)',3000]] as $name=>$field): ?>
<label><?= h(t($field[0],$field[1])) ?><input type="number" name="<?= h($name) ?>" min="1" max="<?= (int)$field[2] ?>" required></label>
<?php endforeach; ?>
</div><button class="btn btn-brand" type="submit"><?= t('Save actual measurements','वास्तविक वजन और नाप सेव करें') ?></button>
</form>
<?php endif; ?>
<div data-dispatch-list class="hub-product-grid"></div>
<p class="help"><?= t('Courier confirmation appears only after a real provider response.','Courier की पुष्टि असली provider जवाब मिलने पर ही दिखेगी।') ?></p>
<a href="/public/index.php"><?= t('Back to Maakit','Maakit पर वापस जाइए') ?></a>
</div></main>
<script src="/assets/maakit-api.js" defer></script><script src="/assets/dispatch-dashboard.js" defer></script>
<?php include __DIR__.'/../inc/foot.php'; ?>
